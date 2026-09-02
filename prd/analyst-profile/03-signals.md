# PRD: Analyst Profile — phase 2, signals and the engine

**Specification. Nothing built.** Depends on phase 1
([`02-store.md`](02-store.md)). This is the phase the Verdict tab has been
blocked on since the skeleton pass.

The picture is [`01-profile.md`](01-profile.md); §5.1 and §5.2 there are the
two invariants this phase implements and must not break.

## 1. What ships

The `signals` section's contract, and `ValueVerdictTool` — the thing that
reads a profile plus a value and produces a verdict array in exactly the shape
the fifteen existing templates already render.

**The exit criterion is a number.** The shipped default profile, run against
`185.234.219.24` on the dev instance, produces a ledger whose contributions
sum to its score, and that score lands in the MALICIOUS band. Not "84" — see
§7.3 for why exact reproduction is not achievable and should not be the test.

## 2. The mechanism

One signed accumulator, verified against all three scored fixture values
(`01-profile.md` §5.2):

```
for each enabled signal in profile.signals:
    evaluate it against the value           → an outcome
    if it fired:  points = f(outcome, signal.points)      # signed, threat-positive
                  emit a ledger row
    if it was silent:      record it, emit no row         # §4.2
    if it could not run:   record it as not_counted       # §4.3

total = Σ points                                          # signed
disposition = band(total)                                 # phase 3
score       = abs(total)
each row's rendered `direction` = sign(row) == sign(total) ? 'with' : 'against'
```

Three properties follow, and each is load-bearing:

- **The sum is the score, by construction** rather than by convention. There is
  no second code path that could disagree with the ledger, which is what
  `01-profile.md` §5.1 requires and what makes a profile diff renderable.
- **`direction` is derived, not stored.** This is why the same signal renders
  upward on a malicious value and downward on a benign one. A profile storing
  direction would have to keep it in step with the sign of its own points.
- **Points are declared threat-signed.** `sightings.false_positive` carries
  negative points; `reporting.independent_orgs` positive. The profile author
  never thinks about dispositions, only about "does this evidence point at a
  threat, and how hard".

### 2.1 Where it lives

`app/Lib/Tools/ValueVerdictTool.php`, and **no view dependency whatsoever** —
`01-profile.md` §5.5 makes this a requirement rather than a preference, because
phase 10 needs to call it in bulk from a REST path. A signal implementation that
reaches for `$this->Html` or a helper makes phase 10 a rewrite.

It takes `$user` — unlike `ValueDecayTool` and the other aggregation tools,
which `00-contract.md` §14.5 establishes take none. The exception is argued
here rather than assumed: every count the verdict reads is already the viewer's
(`00-contract.md` §14.6), the ACL exclusion note is part of the verdict's own
output, and a tool that computed a verdict from data it could not scope would
be computing somebody else's verdict.

### 2.2 A signal implementation

One class per signal id, under `app/Lib/Tools/ValueSignal/`, resolved by id the
way `DecayingModel` resolves a formula class name
(`DecayingModel::__include_formula_file_and_return_instance()`).

```php
interface ValueSignalInterface
{
    /** @return string the ledger group: Reporting|Sightings|Attribution|Lifecycle */
    public function group();

    /**
     * @param array $context  the value's aggregated facts, already ACL-scoped
     * @param array $config    this signal's entry from profile.signals
     * @return array|null      null = silent; otherwise a ledger row
     */
    public function evaluate(array $context, array $config);
}
```

`$context` is built once per verdict and shared by every signal — the
occurrence tally, the sighting rows, the tag and galaxy sets, the warninglist
result, the corroboration dates. It is the same aggregate the live panels
already assemble through `ValueProfile::forX()`, and reusing it is the
difference between one query pass and twelve.

**A signal returns a row, not a number**, because it owns the prose. Only the
signal knows how to say *"47 sightings from 4 orgs, last 2 days ago"* and put
`12 sightings in the last 30 days` in `evidence`. The profile owns the points;
the implementation owns the sentence.

## 3. The `signals` contract

```json
{ "id": "reporting.independent_orgs",
  "group": "Reporting",
  "enabled": true,
  "band": "strong",
  "trust_weighted": true,
  "points": { "per_org": 7, "cap": 28 },
  "config": {} }
```

| Field | Owner | Notes |
|---|---|---|
| `id` | profile | Names the implementation class. An unknown id is an honest state, not a fatal — §4.4 |
| `group` | profile | The ledger group. Overridable so an analyst can move a signal between groups; defaults to the implementation's `group()` |
| `enabled` | profile | A disabled signal is not evaluated and emits nothing, not even a silent record |
| `band` | profile | `strong` / `moderate` / `weak`. Editorial — see §5 |
| `points` | profile | Signed, threat-positive. Shape is per-implementation; every implementation documents its keys |
| `trust_weighted` | profile | Whether per-org trust (phase 6) scales this signal. Only meaningful on org-derived signals |
| `config` | profile | Implementation-specific, non-points parameters. `lifecycle.staleness` puts its TTL table here (phase 5) |

`points` deliberately has no fixed schema. `reporting.independent_orgs` wants
`per_org` and `cap`; `lifecycle.warninglist` wants `no_hit` and
`false_positive_hit`; `attribution.galaxy` wants `per_cluster`, `cap` and
`absent`. Forcing one shape would mean either a lowest common denominator or a
lot of nulls. **Validation is per-implementation**, declared by the class and
run on profile save (phase 1 §3, `validateParameters()`).

## 4. Four outcomes, and the page's vocabulary for each

`value-profile-verdict-engine.md` §3.1 flagged that the engine needs a rule for
which signals are *evaluated*, which *fire*, and which are *shown* — and that
the page had no vocabulary for one that was evaluated and stayed silent. It
turns out the page has most of it already.

### 4.1 Fired — a ledger row

The normal case. Renders through `value_verdict_ledger.ctp`, grouped by `kind`,
with `signal`, `evidence`, `contribution`, `source` and `as_of`.

### 4.2 Silent — evaluated, nothing to say, no row

A warninglist signal on a value that hits no list. A galaxy signal where there
is no galaxy — **except** that on the benign value *absence itself fired*
(*"No galaxy and no technique on any occurrence"*, +7 toward benign). So
"silent" and "fired on absence" are both real and the implementation decides
which, from its `points`: if `absent` is present in `points`, absence fires; if
not, absence is silent.

Silent signals emit **no row and no note**. A ledger listing everything that
did not happen is unreadable, and the count is available if a later phase wants
it ("14 of 20 signals fired").

### 4.3 Could not run — `not_counted`

Evidence the verdict did not use, and the page already renders this
(`value_verdict_not_counted.ctp`, reached from `value_verdict_aside.ctp:49,58`).
The flux value already uses it for exactly this case: the correlation engine
stored nothing, so the relationship signals had no input, and `not_counted`
says so rather than leaving a reader to notice the Relationships tab is empty
and wonder whether the score used it.

**`not_counted` carries two different things and phase 4 separates them** —
profile policy (an exclusion the analyst configured) versus a fact about the
viewer or the data (ACL, a missing correlation). Both belong on screen; only
one is configurable.

### 4.4 Unknown id — an honest state, never a fatal

A profile naming a signal implementation this instance does not have. Happens
after a downgrade, or on importing a profile from an instance with a plugin.

The row **must not** be silently dropped: a score computed from eight of nine
configured signals, presented as if nine ran, is exactly the kind of quiet lie
`01-profile.md` §1.3 forbids. It goes in `not_counted` with its id named, and
the hero still names the profile — because the profile *was* the thing that
weighted the verdict, imperfectly.

## 5. Q5 — is the band derived or editorial?

**The corpus rules out "derived", decisively.** Tabulating all 24 rows by
absolute contribution:

| Band | Absolute contributions in the fixture |
|---|---|
| `strong` | 38, 31, 28, 26, 24, 24, 17 |
| `moderate` | 16, 14, 13, 12, 12, 11, 9, 8, 7, 7, 6, 5 |
| `weak` | 7, 7, 6, 6, 4 |

**`7` appears in both `moderate` and `weak`**, and `17` (`strong`) sits above
`16` (`moderate`) while `5` (`moderate`) sits below `6` (`weak`). No threshold
on the contribution can produce this labelling.

The pattern that *does* hold is that the band tracks the **signal**, not the
number. *"5 of 7 events are published"* (+9) and *"121 of 137 events are
published"* (+7) are the same signal, different inputs, and both `moderate`.

**Recommendation: the band is declared per signal in the profile**, as §3 has
it. It answers a different question from the contribution — the band says *how
much this kind of evidence matters in principle*, the contribution says *how
much it produced here*. Two rows with the same number and different bands is
then not an inconsistency; it is two different kinds of evidence that happened
to land on the same integer.

**One fixture inconsistency has to be fixed either way.**
`attribution.galaxy` is `moderate` on the malicious value (*"Linked to galaxy:
APT28 (2 events)"*, +14) and `strong` on the flux value (*"QakBot, on 107
occurrences"*, +17). Under a per-signal band those must agree. Phase 9 changes
the fixture; this is one of the rows it changes.

**Not formally decided** — it is Q5 and it is the phase's to close, but the
"derived" option is off the table on evidence rather than preference.

## 6. The catalogue

Twelve distinct signals produce the fixture's 24 rows. This is the v1
catalogue, and it is derived from what the page already claims rather than
invented — which is the point.

| id | Group | Reads | Fires on absence | Rows in fixture |
|---|---|---|---|---|
| `reporting.independent_orgs` | Reporting | distinct orgs holding an occurrence | no | 3 |
| `reporting.published_ratio` | Reporting | published vs draft events | no | 2 |
| `reporting.to_ids_stance` | Reporting | `to_ids` across occurrences | no | 1 |
| `sightings.volume_recency` | Sightings | sighting count, org spread, recency | yes (`none_recent`) | 3 |
| `sightings.false_positive` | Sightings | false-positive sightings and their org spread | no | 3 |
| `attribution.galaxy` | Attribution | galaxy clusters on occurrences | yes (`absent`) | 3 |
| `attribution.technique` | Attribution | ATT&CK techniques | no | 1 |
| `lifecycle.warninglist` | Lifecycle | warninglist hit and its category | yes (`no_hit`) | 2 |
| `lifecycle.feeds` | Lifecycle | presence in enabled feeds | yes (`no_feed`) | 1 |
| `lifecycle.continuity` | Lifecycle | months without a gap in activity | no | 1 |
| `lifecycle.recency` | Lifecycle | how recently it was reported | no | 1 |
| `lifecycle.staleness` | Lifecycle | TTL against last corroboration — **phase 5** | n/a | replaces 3 decay rows |

`lifecycle.decay` is **not** in the catalogue. Its three fixture rows (+12, −8,
+16) are retired by D7 and replaced by `lifecycle.staleness`, specified in
[`06-staleness.md`](06-staleness.md).

Two signals the page displays but that are **not** in v1, deliberately:

- **`analyst.opinion`.** Opinions are plausible input and the page renders an
  opinion histogram, but *which* opinions count — value-scoped,
  occurrence-scoped, or both — is open (`../value-profile-writes.md` §10.1).
  Scoring an aggregate whose meaning is undecided would bake the answer in.
- **`relationships.*`.** The flux value already puts the correlation engine's
  silence in `not_counted`, so the page has a state for relationships not
  counting. Adding relationship signals means deciding what a correlation
  *means* about a value, which is a larger question than this phase.

Both are catalogue additions later, which is exactly the extensibility D2 buys.

## 7. The shipped default's weights

### 7.1 Authored against the fixture, not invented

The four demo values are the regression set. The default profile's points are
chosen so that running the engine over them produces recognisably the verdicts
already on screen — that is the only available ground truth, and it is a good
one because the fixture's numbers were authored by someone reasoning about real
evidence.

### 7.2 Trust-weighting is off in the shipped default

`trust_weighted` is declared on the org-derived signals but the default's
`org_trust` map is empty (phase 6), so it is inert. `01-profile.md` §1.3's
"empty means as before" — the feature ships changing nothing until an analyst
grades an org.

### 7.3 Exact reproduction is not achievable, and must not be the test

**The fixture is not internally consistent, and this phase should say so rather
than fail against it.** The clearest case: `reporting.independent_orgs` fires on
4 organisations on both the malicious value (+28 threat) and the benign value
(+11 threat). Same signal, same input count, contributions differing by a
factor of 2.5.

That is not a bug in the fixture — each verdict was authored to make its own
argument read well, which is what a fixture is for. But it means **no single
profile can reproduce all four exactly**, and an acceptance test demanding 84,
91 and 93 to the unit would be unsatisfiable.

The test is therefore: **the disposition matches on all four values, the score
is within a stated tolerance, and every divergence from the fixture is named in
this document's verification section.** The alternative — bending the shipped
weights until the numbers match — would produce a default profile shaped by
four hand-written examples rather than by any coherent judgement, which is the
opposite of what §7.1 is for.

## 8. Q11 — the extension point

**Open, and D2 already did the structural half.** `signals` is a list whose
contract is "emit one row, contribute one signed integer", so an
analyst-authored signal needs no template change and cannot break §5.1's
invariant.

What is not decided:

- **Is a user-authored signal an implementation class, or data?** A class means
  writing PHP and dropping a file on the server — fine for an instance admin,
  useless for an analyst. Data means a small expression language over
  `$context`, which is a language to design and a sandbox to defend.
- **Are they visible to others?** `value-profile-verdict-engine.md` §3.6 argues
  they cannot sync, but two users on one instance is a different question, and
  under D3 an org profile carrying a custom signal already affects everyone in
  the org who has not forked.

**Recommendation for v1: neither.** Ship the twelve-signal catalogue with
per-signal weights, bands and enable flags. That already lets an analyst
express most of what they would write a custom signal for, and it defers a
language design that would otherwise be made under pressure. Revisit once
there is a real request that reweighting cannot serve.

## 9. Verification

1. `parallel-lint` over the tool and every signal implementation.
2. Unit: the accumulator against the three fixture ledgers as literal input —
   `+84`, `+93`, `−91`, and the derived `direction` on each row matching what
   the fixture authored. This is the invariant test and it does not need a
   database.
3. Unit: `sign(total)` selecting the disposition, including a total of exactly
   `0` (which must be a stated outcome, not a coin flip — phase 3 owns the
   rule).
4. Against the dev instance, for all four demo values: the ledger's rows sum to
   the score, to the unit. Rendered, not computed in a test — the assertion is
   on what the page shows.
5. A profile with an unknown signal id: the verdict renders, the id appears in
   `not_counted`, the hero still names the profile.
6. A profile with every signal disabled: an empty ledger, no score, no profile
   name in the hero (`01-profile.md` §5.3), UNKNOWN disposition.
7. `resolveFor()` returning `null` (phase 1 §3.1): same as (6), and no error.
8. Dark theme, both verdict layouts, after the ledger is real — the fixture's
   row count changes, and `value_verdict_ledger.ctp` has never rendered a group
   with one row or twenty.

## 10. Out of scope

- The disposition bands and the escalation rules (phase 3). This phase produces
  a signed total and calls `band()`; phase 3 implements it.
- `lifecycle.staleness` (phase 5) — the catalogue reserves its id.
- Trust weighting's data (phase 6) — this phase declares the flag and applies
  a scale factor of 1 until there is a map.
- Any writing, caching or storing of a verdict. Render-time only
  (`01-profile.md` §5.5).
- Q11, recommended but not decided.
