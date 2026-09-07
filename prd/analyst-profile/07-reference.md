# PRD: Analyst Profile — phase 6, reference data

**Specification. Nothing built.** Depends on phase 2
([`03-signals.md`](03-signals.md)) and phase 3
([`04-dispositions.md`](04-dispositions.md)). Implements **D6**.

Two maps, both override sets, both filling gaps MISP has nowhere else to put.

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

## 5. Verification

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
