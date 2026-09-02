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

**Values are admiralty-scale reliability grades** `A` through `F`, because the
column already renders them and MISP ships the `admiralty-scale` taxonomy. Not
a free-form number: a scale with published semantics is defensible in a way
that "CIRCL: 0.8" is not, and an analyst who has to invent a numeric scale will
invent a different one from their colleague.

**Empty by default.** `01-profile.md` §1.3 — an empty map weights every
organisation equally, which is today's behaviour exactly. Nobody has to grade
200 organisations to get a working profile; grading the three they have an
opinion about is a minute's work.

### 2.3 Grade to multiplier

```json
"org_trust_scale": {
  "A": 1.25, "B": 1.10, "C": 1.00, "D": 0.75, "E": 0.50, "F": 0.25,
  "unrated": 1.00
}
```

Held in the profile alongside the map, so an analyst who wants D to mean 0.9
rather than 0.75 can say so. `C` and `unrated` are both `1.00` by default,
which means **grading an org C is a deliberate statement that changes nothing**
— useful, because it records that you considered them.

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

### 3.1 The gap

`warninglists.category` exists — `varchar(20) NOT NULL DEFAULT
'false_positive'` (`INSTALL/MYSQL.sql:1765`) — and its validation enum is
exactly `['false_positive', 'known']` (`Warninglist.php:43`).

`../value-profile-live/00-contract.md` §14.10 found that **no shipped list sets
it**, so every list defaults to `false_positive`. *(Not re-verified here: the
`warninglists` submodule is not checked out in this worktree or the parent. The
column and the enum were verified; the corpus's finding about the list files is
cited. **Confirm before authoring the default map** — if upstream has since set
categories, most of this section becomes unnecessary.)*

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
    "a1b2c3d4-…": "known",
    "List of known Cloudflare IP ranges": "known"
  }
}
```

Resolution, in order:

```
1. the profile's map, keyed by warninglist uuid
2. the profile's map, keyed by exact warninglist name
3. warninglists.category from the database
4. 'false_positive'                                 # the column's own default
```

**An empty map defers entirely to the database**, which is what makes this an
override set rather than a copy — and it is the answer to D5's fork-freezing
problem (`00-discovery.md` §9.2). A fork that overrides nothing keeps tracking
upstream; only the entries an analyst deliberately set are frozen. If upstream
starts setting categories properly, every profile that has not overridden that
list picks it up on the next warninglist update.

**Keyed by uuid first, name second.** `warninglists` has no `uuid` column
today — it is `id, name, type, description, version, enabled, default,
category`. So v1 keys by **name**, with uuid support written in for when
warninglists gain one. Name is not stable across upstream renames, and that is
a real weakness: a renamed list silently loses its override and falls back to
the database. **The panel must state which source supplied the category**, so
a lost override is visible rather than silent.

### 3.3 Why not fix it upstream instead

Considered and rejected on the ask. The argument for upstream was that a
category is a *fact about the list* — a Cloudflare edge range is shared
infrastructure regardless of who is looking — so setting it once in
`misp-warninglists` would serve every instance rather than each fork.

The decision (D6) is that both maps live in the profile. The override semantics
in §3.2 are what reconcile the two: the profile can express a local judgement,
and an upstream fix still reaches everyone who did not override.

**Worth doing anyway, separately:** a PR to `misp-warninglists` setting
`category: known` on the infrastructure lists would make the shipped default
profile's map empty, which is the best possible outcome for this section. Out
of scope for this phase and worth someone's afternoon.

## 4. Honest states

Both maps can name things that are not there, and each needs a stated
treatment rather than a silent skip:

| Case | Treatment |
|---|---|
| A trust grade for an org uuid not on this instance | Kept, ignored, and listed in the profile editor as *"3 grades for organisations not known here"*. Never deleted — the org may return, or the profile may be shared |
| A trust grade for an org with no occurrence of this value | Silent. Normal and uninteresting |
| A category override for a warninglist not on this instance | Same as row 1 |
| A category override for a **disabled** warninglist | The list does not match, so nothing fires. Not an error |
| Every contributing org unrated | No weighting note in the ledger row (§2.5) |

## 5. Verification

1. `org_trust` empty: every ledger row identical to phase 2's output, to the
   unit. This is the "empty means as before" assertion and it is the most
   important test in the phase.
2. Grade the four orgs on the malicious value `B/B/C/D`: the
   `reporting.independent_orgs` row drops, the ledger still sums to the score,
   and `evidence` names the grades.
3. Grade every org `A`: the row rises and respects its `cap`.
4. Grade one org `F` and confirm the row falls but stays positive — a weighting
   must not flip a signal's sign, or `direction` becomes unstable for a reason
   nobody can see.
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

## 6. Out of scope

- A `uuid` column on `warninglists`. Written for, not added (§3.2).
- The upstream `misp-warninglists` PR (§3.3).
- The feed-upstream map that `05-exclusions.md` §3.1 deferred here. Still
  deferred — `provider` remains the dedupe key until someone asks.
- Per-org trust anywhere but a verdict. It is not a general MISP feature, does
  not affect sync, correlation or exports, and is not visible to anyone but the
  profile's owner.
- Taxonomy trust (C11). `tag_numerical_value_override` already exists per user
  and stays in `user_settings` (D4). Whether the profile should name it as an
  input it does not own is an engine question, open, and recorded in
  `00-discovery.md` Q4.
