# PRD: Analyst Profile — phase 6, reference data

**Built 2026-09-07.** Depends on phase 2
([`03-signals.md`](03-signals.md)) and phase 3
([`04-dispositions.md`](04-dispositions.md)). Implements **D6**.

Two maps, both override sets, both filling gaps MISP has nowhere else to put.

What landed: `app/Lib/Tools/ValueTrustTool.php` (the grades, the scale, the
weighted counts and the evidence clause), `app/Lib/Tools/WarninglistCategory.php`
(V1's shipped map, the four-step resolution and its retirement criterion),
`reference.org_trust_scale` in the shipped default, the trust join in
`ValueProfile::verdictTrust()`, per-organisation sighting tallies in
`ValueStatsTool::sightingsByOrg()`, and trust weighting inside the three
signals §2.4 names. Verified by **114 checks with no database**
([`07-reference-harness.php`](07-reference-harness.php)) and **84 against the
dev instance** ([`07-reference-live-probe.php`](07-reference-live-probe.php),
74 in `run` and 10 in `dayone`). Nine findings are in §7; the load-bearing
one is §7.1 — the cap makes a weighting invisible on any value with four or
more equally-graded reporters, which is most of them.

## 1. What ships

`reference.org_trust` and `reference.warninglist_category` — what the analyst
believes about their sources — plus the trust weighting that makes the first
one affect a score, plus the category resolution that makes the conflict
escalation able to fire at all.

The page already promises the profile holds knowledge of this kind. The benign
value's `curves_note` says it outright:

> *"The step on 2025-06-24 is the address being added to the public-resolver
> warninglist. Before that the page called it SUSPICIOUS on the strength of the
> same four reports — the evidence did not change, the profile's knowledge of
> it did."*

## 2. Per-org trust

### 2.1 It has nowhere else to live

The Verdict tab's *"Who says what"* panel renders a Reliability column with
admiralty-scale grades — `CIRCL: B`, `Team-CIRCL: C`, `ORGNAME: D`
(`ValueProfileFixture.php:1057–1084`, and again at `2318`, `3444`). The fixture
admits it invented them:

> *"Opinion, `to_ids` stance and reliability are editorial and are not
> derivable from a row count, so they are given for the four organisations the
> summary names and defaulted for the nineteen it does not."*
> — `ValueProfileFixture.php:10386`

There is no store. `admiralty-scale` is a taxonomy, so it exists as *tags* —
and tags attach to events, attributes and galaxy clusters. **Nothing in MISP
attaches a tag, or anything else, to an organisation.** `organisations` has
`name, type, nationality, sector, contacts, local, restricted_to_domain,
landingpage` and no room for a judgement.

So this map is not the profile taking on someone else's job. It is the only
available home for a claim the page already makes.

### 2.2 Shape

```json
"reference": {
  "org_trust": {
    "55f6ea5e-2c60-40e5-964f-47a8950d210f": "B",
    "55f6ea62-fb60-40e5-964f-47a8950d210f": "D"
  }
}
```

**Keyed by `organisations.uuid`, never `org_id`.** `org_id` is local — the same
organisation carries different ids on different instances, so a profile keyed by
id would silently grade the wrong org after an export/import or against a peer.
`organisations.uuid` exists and is unique.

**Values are admiralty-scale reliability grades** `A` through `G` — the
shipped taxonomy's own set: `admiralty-scale:source-reliability` runs `a`
(*Completely reliable*) through `g` (*Deliberately deceptive*), seven grades,
not the six a reader assumes. Not a free-form number: a scale with published
semantics is defensible in a way that "CIRCL: 0.8" is not, and an analyst who
has to invent a numeric scale will invent a different one from their
colleague. The taxonomy's other predicate, `information-credibility`, grades
claims rather than sources and deliberately stays out of the profile.

**Empty by default.** `01-profile.md` §1.3 — an empty map weights every
organisation equally, which is today's behaviour exactly. Nobody has to grade
200 organisations to get a working profile; grading the three they have an
opinion about is a minute's work.

### 2.3 Grade to multiplier

```json
"org_trust_scale": {
  "A": 1.25, "B": 1.10, "C": 1.00, "D": 0.75, "E": 0.25,
  "F": 1.00, "G": 0.00,
  "unrated": 1.00
}
```

**Corrected 2026-09-03** (`review-2026-09-02.md` A5) — the first draft put `F`
at the bottom (`0.25`), misreading the scale it claims as its authority. In
the Admiralty System **F is "Reliability cannot be judged"** — a neutral
statement, the semantic twin of `unrated` — and the shipped taxonomy says so
in numbers: its `numerical_value` column runs `a=100, b=75, c=50, d=25, e=0,
f=50, g=0`, so **f equals c**, the midpoint. The default scale now follows
that structure — `A > B > C = F = unrated > D > E ≥ G` — which makes the
"published semantics" argument above literal: the taxonomy's own values are
the receipt.

Two deliberate departures from copying the numbers outright. `E`
(*Unreliable*) is `0.25`, not the taxonomy's zero — unreliable still means
*sometimes right*, and a floor keeps their evidence visibly discounted in the
ledger rather than silently erased. `G` (*Deliberately deceptive*) is a true
`0.00`: an accusation of deception, not a quality judgement, and it zeroes
that org's evidence **in both directions** — a deceptive org's false-positive
sightings, whitewashing a value it controls, count for exactly as much as its
reports.

Held in the profile alongside the map, so an analyst who wants D to mean 0.9
rather than 0.75 can say so. `C`, `F` and `unrated` are all `1.00` by
default, which means **grading an org C or F is a deliberate statement that
changes nothing** — useful, because it records that you considered them.

### 2.4 Where it applies

Only to signals declaring `trust_weighted: true` (`03-signals.md` §3), and only
to signals whose contribution is derived from *which organisations* said
something:

| Signal | Trust-weighted | Why |
|---|---|---|
| `reporting.independent_orgs` | yes | The largest positive contribution on two demo values (+28, +31) |
| `sightings.volume_recency` | yes | Sightings are attributable to an org |
| `sightings.false_positive` | yes | A false positive from a D-grade source is weaker evidence |
| `reporting.published_ratio` | no | A property of events, not of who holds them |
| `attribution.galaxy` | no | A galaxy cluster is not an organisation's claim in the same way |
| `lifecycle.*` | no | Nothing to attribute |

`reporting.independent_orgs` becomes, instead of `count × per_org`:

```
Σ over contributing orgs of (per_org × scale[grade(org)])     capped
```

**Rounded once, at the end**, to a signed integer — §5.1's invariant needs
integers in the ledger, and rounding per-org then summing produces a number
that does not match the same calculation done the other way.

**Built 2026-09-07, and the three signals turned out to be one rule.** Trust
weighting replaces *a count of organisations* with *a weighted count of
organisations*, and *a count of their rows* with *a weighted count of their
rows*:

```
reporting.independent_orgs   count(orgs)      → Σ factor
sightings.volume_recency     count(sightings) → Σ count × factor
sightings.false_positive     count(fp)        → Σ fp × factor
                             extra orgs       → max(0, Σ factor − 1)
```

Every one of those is the unweighted number when every factor is `1.0`, which
is what makes §5 item 1 arithmetic rather than luck; `ValueTrustTool::weigh()`
and `weighOrgs()` are the whole of it, so a drop-in signal declaring
`trust_weighted` has the arithmetic available rather than having to
reimplement it. Two details the formula above does not carry:

- **`sightings.volume_recency` weights the count, not the points.** The factor
  goes in *before* the saturation curve. The whole judgement in that signal is
  that volume saturates — the step from 1 sighting to 10 says more than the
  step from 400 to 410 — so a factor applied to the finished points would not
  be weighting volume at all. Weighted, half the sightings is about 80% of the
  points, which is the curve doing its job.
- **The extra-organisation term is clamped at zero**, or a single `E`-graded
  filer makes a false positive argue *for* the threat. §7.2.

### 2.5 The ledger row has to say it happened

A row reading *"4 independent organisations reported it — +24"* where the
unweighted number would be +28 is unexplainable without help. The row's
`evidence` names the weighting:

> `CIRCL (B), CthulhuSPRL.be (B), Team-CIRCL (C), ORGNAME (D) — weighted by
> your reliability grades`

And when the map is empty, it says nothing extra, because nothing happened.
This matters more than it looks: **trust weighting is the one part of this
feature that changes a number for a reason invisible in the underlying data**,
and a reader who cannot see why will conclude the page is broken.

### 2.6 Ordering with exclusions

Exclusions run first, unconditionally; trust weighting applies to what survives
(`05-exclusions.md` §4). An org excluded by `orgs.own` is not also counted at
its grade.

## 3. Warninglist categories

### 3.1 The gap — verified, and one gap deeper than the corpus knew

`warninglists.category` exists — `varchar(20) NOT NULL DEFAULT
'false_positive'` (`INSTALL/MYSQL.sql:1765`) — and its validation enum is
exactly `['false_positive', 'known']` (`Warninglist.php:43`).

`../value-profile-live/00-contract.md` §14.10's finding is now **confirmed**
(2026-09-03, against the sibling checkout `~/git/misp-warninglists`): **0 of
89 `list.json` files carry a `category` field.** Every list defaults to
`false_positive`.

And the gap is one layer deeper: **MISP core would drop the field even if
upstream set it.** `Warninglist::__updateList()` saves exactly
`['name', 'version', 'description', 'type', 'enabled']`
(`Warninglist.php:394`) — `category` is not imported, for shipped lists or
via `import()`. It is only settable on custom lists through the add/edit
controller path (`WarninglistsController.php:232`), which is why the column
is not dead code. So an upstream fix alone changes nothing; the import has
to learn the field too. Both are §3.3's V2.

Three things on the page depend on the distinction:

- The benign value's largest signal — *"Hits List of known IPv4 public DNS
  resolvers"*, `−38` threat — is a `false_positive` reading.
- The conflicted value's entire premise: a `known`-category hit means *widely
  used infrastructure, not a false positive*, so *"an action against this
  address will hit unrelated services too — it does not say the reports are
  wrong."*
- The escalation `conflict:known-infrastructure-vs-reporting`, whose `when`
  requires `warninglist_category: known` (`04-dispositions.md` §5). **Without a
  category source it can never fire**, so this section is what makes phase 3's
  one shipped escalation more than a specification.

### 3.2 Shape, and override semantics

```json
"reference": {
  "warninglist_category": {
    "List of known Cloudflare IP ranges": "known"
  }
}
```

Resolution, in order:

```
1. the profile's map, keyed by exact warninglist name
2. the shipped category map — WarninglistCategory.php (§3.3, V1)
3. warninglists.category from the database
4. 'false_positive'                                 # the column's own default
```

**An empty profile map defers entirely to the sources below it**, which is
what makes this an override set rather than a copy — and it is the answer to
D5's fork-freezing problem (`00-discovery.md` §9.2). A fork that overrides
nothing keeps tracking the shipped map and the database; only the entries an
analyst deliberately set are frozen. **The shipped default profile's map is
empty** — under V1 the shipped knowledge lives in code, not in the profile,
so `01-profile.md` §1.3's "empty means as before" holds for this map without
exception.

The shipped map sits **above** the database deliberately: for shipped lists
the column is not a statement, it is an unset default — nothing imports it
(§3.1) — while the map's every entry is deliberate. Custom lists, where the
column *is* settable and deliberate, are simply absent from the map and so
fall through to the database. Under V2 the map empties and the order
degenerates to profile → database → default.

**Keyed by name, and that is fine.** `warninglists` has no `uuid` column and
upstream lists carry none either; MISP core itself matches shipped lists by
name on every update (`Warninglist.php:298`), so a rename upstream is already
a new list as far as core is concerned. Name-keying the maps is no more
fragile than the platform underneath them. The residual risk — a renamed list
silently losing its profile override or map entry — is handled by the
standing requirement: **the panel states which source supplied the category**
(profile, shipped map, list, or default), so a lost entry is visible rather
than silent.

### 3.3 The category source, staged — V1 in code, V2 upstream

A category is a *fact about the list* — a Cloudflare edge range is shared
infrastructure regardless of who is looking — so the right long-term home is
`misp-warninglists`, where it serves every instance and every non-MISP
consumer of the repo. §3.1 shows that path is currently dead on arrival
(nothing upstream sets it, and core would not import it), so the fix is
staged. **Decided 2026-09-03.**

**V1 — a hardcoded map, shipped with this phase.**
`app/Lib/Tools/WarninglistCategory.php`: a static class mapping exact list
names to `known`, following the `GalaxyColour.php` precedent — canonical
knowledge shipped as code, deterministic, no store. It plugs into the
resolution at §3.2 step 2, and `ValueWarninglistTool.php:110` (which already
surfaces `warninglist_category` per hit) is the integration point. The map
carries only `known` entries; anything unlisted falls through.

The test that decides membership: **can a competent report naming this value
as malicious be simultaneously true?** Yes → `known` (the hit contextualises;
acting on the value has collateral). No → `false_positive` (the hit refutes;
the report is probably an extraction error or the impersonation target). An
AWS range is `known` — C2 on EC2 is routine; an RFC1918 address is
`false_positive` — meaningless in shared intel by definition; a public DNS
resolver stays `false_positive` — 8.8.8.8 reported as an IOC is nearly always
an artifact of resolution logging, which is exactly how the benign demo value
reads it.

The initial roster, by the test — shared/multi-tenant infrastructure and
research scanners (directory names; the file keys by each list's exact
`name`):

> `amazon-aws`, `microsoft-azure` (+ `-china`, `-germany`, `-us-gov`),
> `google-gcp`, `ovh-cluster`, `stackpath`, `cloudflare`, `akamai`, `fastly`,
> `url-shortener`, `dynamic-dns`, `disposable-email`, `link-in-bio`,
> `parking-domain`, `parking-domain-ns`, `public-ipfs-gateways`, `vpn-ipv4`,
> `vpn-ipv6`, `sinkholes`, `censys-scanning`, `tenable-cloud-ipv4`,
> `tenable-cloud-ipv6`, `check-host-net`

Everything else — structural artifacts (`rfc*`, `empty-hashes`, `tlds`),
popularity lists (`tranco*`, `cisco_top*`, `alexa`), vendor telemetry noise
(`google`, `microsoft`, `apple`, `captive-portals`, `crl-*`) — stays
`false_positive`, which the fall-through already says.

**V2 — upstream, the named successor.** Two PRs, in either order, both
required before the V1 file can retire:

1. **`misp-warninglists`**: add `"category": "known"` to the roster's
   `list.json` files, bump each list's `version` (or `Warninglist::update()`
   skips it — the `>` comparison at `Warninglist.php:312`), and extend the
   repo's CI validation schema to admit the field.
2. **MISP core**: add `category` to `__updateList()`'s `$fieldsToSave` with
   `$list['category'] ?? 'false_positive'`, guarded by the existing
   validation enum. One line plus a default; arguably a standalone fix, since
   the column has been importable-in-theory and dropped-in-practice since it
   shipped.

When both have landed and the lists re-imported, the database is authoritative
for shipped lists, the V1 map's entries are redundant, and the file empties to
a shim (or is deleted and step 2 of §3.2's resolution removed). The retirement
criterion is mechanical: every roster entry's database row carries the same
category the map hardcodes.

## 4. Honest states

Both maps can name things that are not there, and each needs a stated
treatment rather than a silent skip:

| Case | Treatment |
|---|---|
| A trust grade for an org uuid not on this instance | Kept, ignored, and listed in the profile editor as *"3 grades for organisations not known here"*. Never deleted — the org may return, or the profile may be shared |
| A trust grade for an org with no occurrence of this value | Silent. Normal and uninteresting |
| A category override for a warninglist not on this instance | Same as row 1 |
| A category override for a **disabled** warninglist | The list does not match, so nothing fires. Not an error |
| A shipped-map entry for a list renamed or absent upstream | Inert — nothing matches the old name. Not an error; the V2 retirement check (§3.3) is where it surfaces |
| Every contributing org unrated | No weighting note in the ledger row (§2.5) |

**Built 2026-09-07: phase 6 produces these states, phase 8 renders them.**
The context's trust block carries `unknown` (grades whose uuid is on no
organisation here) and `invalid` (entries whose grade is not a grade), both
kept rather than dropped, and the editor's *"3 grades for organisations not
known here"* is phase 8's line to draw from them. Nothing about them reaches
the value's own page: a grade for an organisation with no occurrence of this
value is row 2's *silent and uninteresting*, and so is a grade for an
organisation that is not here — the value has nothing to say about either,
and a per-value note about the reader's profile would appear on every value
they have ever graded anybody for. One state moved: `ValueStatsTool::
sightingsByOrg()` adds a seventh, **an organisation this viewer cannot name**,
treated as unrated for the reason §7.4 gives.

## 5. Verification

Where each item landed, now that the phase is built: **1–5, 8, 11 and 12** are
`07-reference-harness.php`, with no database; **1–9 and 12** run again against
the dev instance in `07-reference-live-probe.php`; **10** is that probe's
`dayone` command. Items 2, 3, 5 and 6 do not say quite what they were written
to say — §7.1 and §7.5 record why, and both the harness and the probe assert
the corrected form.

1. `org_trust` empty: every ledger row identical to phase 2's output, to the
   unit. This is the "empty means as before" assertion and it is the most
   important test in the phase.
2. Grade the four orgs on the malicious value `B/B/C/D`: the
   `reporting.independent_orgs` row drops, the ledger still sums to the score,
   and `evidence` names the grades.
3. Grade every org `A`: the row rises and respects its `cap`.
4. Grade one org `E` and confirm the row falls but stays positive — a weighting
   must not flip a signal's sign, or `direction` becomes unstable for a reason
   nobody can see. Grade one org `F`: nothing changes, identically to unrated.
   Grade one org `G`: its evidence counts for nothing in both directions —
   reports and false-positive sightings alike — and the evidence note says so.
5. `org_trust_scale` edited so `D` is `0.95`: the same map produces a different
   number, proving the scale is data.
6. A category override making a `false_positive` list `known` on the benign
   value: the escalation fires, the value goes CONFLICTED, and the hero names
   the rule. **This is the end-to-end test of D6 plus phase 3** — it is the
   case the fixture's conflicted value has been illustrating with no mechanism
   behind it.
7. The same override removed: the value returns to BENIGN. Together with (6)
   this demonstrates the sentence in §1 — the evidence did not change, the
   profile's knowledge did.
8. A trust grade for an unknown org uuid: no error, listed in the editor.
9. Export a profile with both maps, import to a second instance where one org
   is absent: the present org still weights correctly, the absent one is listed.
10. **The day-one case (V1):** an AWS-range value with 3+ independent reports,
    the `amazon-aws` list enabled, and an **empty** profile map: the shipped
    map supplies `known`, the escalation fires, CONFLICTED — with nobody
    having edited anything. This is §3.3's V1 doing the conflicted demo
    value's job on real data.
11. A profile entry setting a shipped-map `known` list back to
    `false_positive`: the profile wins (§3.2 order), and the panel names the
    profile as the source. Then remove it: the shipped map resumes, named.
12. The category source named in the panel for all four origins — profile,
    shipped map, list column, default — one value each.

## 6. Out of scope

- A `uuid` column on `warninglists`. Dropped, not deferred: upstream lists
  carry no uuid and core matches them by name (§3.2), so a local uuid would
  identify nothing.
- V2's two PRs (§3.3). Named successors with a mechanical retirement
  criterion, not this phase's work.
- The feed-upstream map that `05-exclusions.md` §3.1 deferred here. Still
  deferred — `provider` remains the dedupe key until someone asks.
- Per-org trust anywhere but a verdict. It is not a general MISP feature, does
  not affect sync, correlation or exports, and is not visible to anyone but the
  profile's owner.
- Taxonomy trust (C11). `tag_numerical_value_override` already exists per user
  and stays in `user_settings` (D4). Whether the profile should name it as an
  input it does not own is an engine question, open, and recorded in
  `00-discovery.md` Q4.

## 7. What building it found

Nine findings, 2026-09-07. The first three changed the implementation; the
next three are decisions the specification did not make; the last three are
about the verification rather than the feature.

### 7.1 The cap makes a weighting invisible on most values

**§5 items 2 and 3 cannot be observed on the value they were written for.**
`reporting.independent_orgs` pays `per_org 7` to a `cap 28`, so the cap binds
above **four weighted voices** — and §5 item 2's own scenario is exactly four
organisations. Graded `B/B/C/D` they are worth `7 × (1.10 + 1.10 + 1.00 +
0.75) = 27.65`, which rounds to `28`: the weighted row and the unweighted row
are the same number. The dev instance's widest value is worse — `8.8.8.8` has
eight reporters, `56` uncapped, so grading **every one of them `D`** still
leaves `42` and the row does not move at all. Only `E` (`2.00` voices, `14`)
and `G` (`0.00`, `0`) come off the cap.

Recorded rather than fixed, because both halves are right: the cap is the
analyst's own number, and applying the weighting *before* it is what §2.4
requires — the alternative multiplies a capped row and breaks the cap. What
changed is the verification. Both the harness and the probe now assert **the
mechanism itself** — the row is exactly `min(per_org × Σ factor, cap)`,
rounded once — and the directional claims (*rises*, *falls*, *the scale is
data*) are made on a case that is off the cap. §5 item 5 moved from `D` to
`E` for the same reason: eight organisations at `D` are over the cap, so `D`
at `0.75` and `D` at `0.95` both produce `28` and prove nothing.

The general shape is worth keeping in view for phase 8's simulator: **a
weighting is only visible where the signal is not saturated**, and an
analyst grading their sources will most often be looking at a row that
cannot move. The honest place to show them the difference is a row that is
below its cap.

### 7.2 The extra-organisation term turned a false positive into corroboration

`sightings.false_positive` pays `per −3` per filing plus `per_extra_org −4`
for each organisation beyond the first. Weighted, the organisation count
becomes `Σ factor` — and a single `E`-graded filer sums to `0.25` voices, so
`Σ − 1` is **−0.75** and `per_extra_org × −0.75` is **+3**. A false-positive
signal contributing *towards* a threat lean, from a grade whose only stated
effect is to discount evidence.

Clamped at zero: `per_extra_org × max(0, Σ factor − 1)`, which is `orgs − 1`
exactly when nobody is graded. This is §5 item 4's rule — *a weighting must
not flip a signal's sign* — arrived at through the second term rather than
through the first, and §2.3's `E ≥ 0` floor does not prevent it because the
sign flip is in the subtraction, not in the factor.

Found by writing the arithmetic out rather than by reading a page: the row
would have been small and positive, and nothing on the Assessment tab says
which sign a row is supposed to have. The scale's own negative-factor guard
(`max(0.0, …)` in `ValueTrustTool::scaleFrom`) is the same finding from the
other direction — a profile setting `D` to `-4` would flip every row it
touched.

### 7.3 Grading every organisation `G` leaves a row worth zero, not silence

With `Σ factor` at zero the reporting row contributes `0`. The harness
asserted *no row at all* first, on the reasoning that nobody is a voice so
the signal has nothing to say — and that is wrong. **The reports exist.**
What happened is that the reader's own profile discounted them to nothing,
and a silent signal hides exactly that: the ledger comes out seven points
short with no line explaining where they went. So a zero row it is, and the
evidence line is what makes it readable:

> `CIRCL (G), CthulhuSPRL.be (G) — weighted by your reliability grades;
> CIRCL (G), CthulhuSPRL.be (G) counts for nothing, in either direction`

The rule generalises: **a weighting may take a row to zero but never off the
page.** A signal that fired is a signal a reader is entitled to see the
arithmetic of.

### 7.4 An organisation this viewer cannot name is unrated, deliberately

`Plugin.Sightings_anonymise` zeroes `org_id` as well as the name, so most of
this case does not arise — there is nothing left to grade. What remains is a
row carrying an id whose organisation name is not disclosed, and grading
*that* would move the number for a reason the reader can find nowhere on the
page, which is precisely what §2.5 forbids. So `ValueStatsTool::
sightingsByOrg()` files such rows as ungradeable — the same both-or-neither
test the rest of that class uses, so the weighted and unweighted tallies
never disagree about which rows are attributable — and
`ValueTrustTool::factor()` reads id `0` as `unrated`.

It costs a little accuracy on a configuration almost nobody runs, and it
keeps the evidence line honest on every configuration. It is also the same
reasoning as phase 4 §7.2's: the page does not tell a reader about records
their permissions hide, including by arithmetic.

### 7.5 A category override does not always change the lean

§5 items 6 and 7 say the value *"goes CONFLICTED"* and *"returns to
BENIGN"*. That is true only where the value's organisations do not assert
it. On `8.8.8.8` — eight of eight organisations against the public-resolver
list — `conflict:listed-vs-asserted` has **already** made the value
contested, so the `known` override does not change the lean at all. It hands
the contradiction to `conflict:known-infrastructure-vs-reporting`, and what a
reader sees change is the rule and its prose:

> *"A warninglist marks this as a false positive and 8 of 8 organisations
> report it as a threat regardless. Both judgements are deliberate; the page
> will not pick one."*
>
> → *"MISP knows this as shared infrastructure, and 8 organisations report it
> as a threat anyway. Both are true; neither discounts the other."*

Which is the feature working — the two rules name two different
contradictions and the category is what decides which one the value is in —
but *"the value goes CONFLICTED"* is the wrong thing to look for on
three-quarters of the values a category override will touch. Both the harness
and the probe now assert the rule identity and the prose difference, and the
probe asserts it on the instance's own rows rather than on an invented value.

Getting a `benign` before-state at all took more than flipping the stances,
and that is the second half of the finding: with organisations voting `to_ids
no` over a ledger full of threat evidence, **phase 3's rule 7 makes the value
contested before any escalation is consulted** — a record disputing its own
assertion — so `benign` is unreachable and item 7 cannot be written. The
harness therefore builds `8.8.8.8`'s whole shape: four organisations
declining to export it, eleven false-positive sightings, no galaxy, no feed,
and a history that stopped over a year ago.

### 7.6 The empty map is the switch, not a scale of ones

§2.3 makes the scale editable, which means *"an empty map weights every
organisation equally"* is not enough on its own: an analyst who moves
`unrated` to `0.5` and grades nobody would silently halve every score on the
instance, and every row would carry §2.5's weighting note explaining a
weighting nobody asked for. So an empty `org_trust` map takes the mechanism
**out of the path entirely** (`ValueTrustTool::planFor()`'s `in_force`), and
`unrated` only means anything once there is a graded organisation for it to
contrast with.

That makes §5 item 1 — *every ledger row identical to phase 2's output* —
structural rather than arithmetic, and it is asserted in the strong form:
the harness and the probe both edit the scale *and* leave the map empty, and
require the ledger, the quality and the lean to be unchanged. The switch also
reads the **validated** map, so a profile whose only entry is a typo weights
nothing rather than putting the mechanism in the path with every factor at
`1.0` — a state indistinguishable on the page from a working map.

### 7.7 Three earlier harnesses were already dead, and phase 6 would have hidden it

Phase 5 added a `ValueRelevanceTool::relevanceFor()` call to
`ValueVerdictTool::verdict()` and did not add the `require_once` to phases
2, 3 and 4's harnesses. All three had been fataling on their first
`assess()` since phase 5 landed, and nothing had run them since. Phase 6 then
gave the three trust-weighted signals a dependency the filesystem loader does
not provide: in the live path `App::uses('ValueTrustTool', 'Tools')` in the
engine covers it — the engine is the one thing guaranteed to be loaded before
any signal evaluates — but in a bare-PHP harness it does not, and the three
signals threw into `not_counted` with `Class "ValueTrustTool" not found` in
the note.

Which is §8.5's guard working exactly as designed, and is also the reason it
was worth fixing rather than noting: a harness whose three heaviest signals
have quietly become *could not run* still prints `ok` against every
assertion that does not name them, and every number in the file has moved.
`ValueRelevanceTool`, an `__n()` stub and `ValueTrustTool` are now required
by all three. **All six harnesses run: 33 + 98 + 100 + 42 + 106 + 114 = 493
checks, no failures**, and all five live probes pass with them.

Two of those totals are not the numbers their own documents record — phase 2
reads 98 where §9 says 96, and phase 3's live probe 57 where §11 says 52.
Both probes count assertions emitted inside loops over live rows and over
`not_counted`, so a total moves when the instance does; neither is a
regression in what is asserted, and phase 6's own probe rules out the
alternative directly by requiring **every ledger row, the quality and the
lean byte-identical** on all four demo values under the shipped profile.

The general lesson is the loader's: a signal is discovered from the
filesystem and `require`d, so the file has no `App::uses` of its own and its
dependencies are the *engine's* to declare. A drop-in signal reaching for a
tool the engine does not load lands in `not_counted` on a live instance too,
with the class name on the page — which is the right failure, and worth
saying in §8 of `03-signals.md` when a third-party signal first does it.

### 7.8 §5 item 10 is not reachable on this instance as written

The day-one case wants *an AWS-range value with 3+ independent reports and
`amazon-aws` enabled*. Checked by CIDR containment over all 19 multi-org
IPv4 values on the dev instance against all eight roster CIDR lists it
carries: **no multi-org value falls inside any of them.** The only
roster-listed multi-org value here is `bit.ly` on the URL-shorteners list,
with two reporting organisations against the shipped rule's `3`.

So the case runs in two halves rather than being skipped or having data
authored for it:

- **The category half**, which is all of V1's contribution, on real rows with
  **both profile maps empty**: `bit.ly` hits *"List of known URL Shorteners
  domains"*, whose database row says `false_positive`, and the hit resolves
  to `known` with `category_source: shipped`. Nobody edited anything.
- **The escalation half**, with `min_independent_reports` moved to `2` and
  the move stated in the output — and the shipped threshold asserted
  separately in the same section, so the change cannot pass unnoticed.

The list is enabled for the run and put back in a `finally`, which is also
why the day-one case is its own command: `Warninglist::getEnabled()` memoises
per process, so a list enabled after that first read stays invisible for the
rest of the run.

### 7.9 The 0-of-89 finding, re-confirmed from both ends

Upstream, re-read 2026-09-07 against `~/git/misp-warninglists`: **0 of 89
`list.json` files carry a `category` field.** On the dev instance: all **25**
roster names match a warninglist that is actually present — the names, not
the directory names, are the load-bearing part of `WarninglistCategory.php`
— and **not one of them carries `known`**. 97 lists, exactly one `known`, and
that one is a custom list, which is §3.2's fall-through case working: for a
custom list the column *is* a deliberate statement, so it is absent from the
shipped map and answers for itself with `category_source: list`.

`WarninglistCategory::retirable()` therefore says V1 cannot retire, which is
§3.3's criterion answered by an instance rather than by reading two PRs.

**Also measured.** The trust join costs **exactly one query, and only when
the map has something in it** — 18 queries to build `8.8.8.8`'s context with
an empty map, 19 with one grade. The lookup is keyed by the *map* rather than
by the value's own organisations, because §4 needs the difference: a grade
whose uuid is on this instance but not on this value is silent and
uninteresting, while a grade whose uuid is on no organisation at all is kept,
ignored and reported.
