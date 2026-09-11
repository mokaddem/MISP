# PRD: Analyst Profile — phase 2, signals and the engine

**Built 2026-09-07.** Depends on phase 1
([`02-store.md`](02-store.md)). This was the phase the Verdict tab had been
blocked on since the skeleton pass; §9 carries the verification results and
§11 what building it changed.

**Re-scoped by D11 (2026-09-03):** the accumulator this phase builds produces
the **quality** axis of the assessment ([`12-assessment.md`](12-assessment.md));
lean is derived categorically from `to_ids` stance and warninglist categories,
and relevance is phase 5's axis. The mechanism, the invariants and the
catalogue below hold, read through D11's rename map. Reworked in place
2026-09-03: `reporting.to_ids_stance` promoted into the lean derivation,
`lifecycle.staleness` moved to the relevance axis,
`record.temporal_precision` added, and the accumulator anchored to the lean
(§2, `04-dispositions.md` §2).

The picture is [`01-profile.md`](01-profile.md); §5.1 and §5.2 there are the
two invariants this phase implements and must not break.

## 1. What ships

The `signals` section's contract, `ValueVerdictTool` — the thing that reads a
profile plus a value and produces a verdict array in exactly the shape the
fifteen existing templates already render — and **the loader that discovers
signal implementations from the filesystem** (D12, §8), so that adding a
signal is dropping a file rather than editing MISP.

**The exit criterion is a number.** The shipped default profile, run against
`185.234.219.24` on the dev instance, produces a ledger whose contributions
sum to its score, and that score lands in the MALICIOUS band. Not "84" — see
§7.3 for why exact reproduction is not achievable and should not be the test.

### 1.1 What landed, 2026-09-07

| File | What |
|---|---|
| `app/Model/ValueSignals/ValueSignalBase.php` | the contract, the identity the loader reads, and the `$context` documentation every signal author reads |
| `app/Model/ValueSignals/*.php` | §6's eleven, one file each |
| `app/Lib/Tools/ValueSignalLoader.php` | D12's directory read: two roots, memoised per request, collisions refused, failures logged and kept |
| `app/Lib/Tools/ValueVerdictTool.php` | the accumulator — outcomes, anchoring, row validation, `not_counted`, the budget's two tiers, grouping, banding |
| `app/Lib/Tools/ValueStatsTool.php` | `verdictComposition()` and `sightingSignals()` — the composition card §14.5 always said belonged here, now that there is a ledger to derive it from |
| `app/Model/Value.php` | `recordSummaryFor()`, `orgStanceFor()`, `activityMonthsFor()` — three aggregates over value storage, which is this file's seam |
| `app/Model/ValueProfile.php` | `verdictContextFor()` and its helpers: one context build, seven queries |
| `app/files/analyst-profiles/default-v1.json` | the eleven-signal catalogue and its weights, `version` 2 so `updateDefaults()` replaces phase 1's provisional six |
| `03-signals-engine-harness.php` | 96 checks, no database |
| `03-signals-live-probe.php` | 36 checks against the dev instance |

**The name stays `ValueVerdictTool`.** D11's rename map makes it
`ValueAssessmentTool` *"at implementation time"*, and this implementation
declines — for the same reason the map exempts `ValueDisposition` and the
`value_verdict_*.ctp` templates until phase 9. The output feeds those
fifteen templates today; a tool called `ValueAssessmentTool` filling an array
called `verdict` for `value_verdict_ledger.ctp` would leave the codebase
half-renamed for four phases. Phase 9's copy pass renames the templates, the
constants and this class in one commit, and until then the vocabulary is
consistent: verdict in the code, assessment in the design. The array it
returns already carries the axis keys (`lean`, `quality`, `band`) with
`disposition`, `score` and `confidence` as aliases beside them, so phase 9's
rename is a deletion rather than a translation.

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

polarity = +1 (threat lean) | −1 (benign lean)            # phase 3 derives it
row      = points × polarity                              # anchored to the lean
quality  = Σ anchored rows                                # the exact-sum home
each row's rendered `direction` = sign(row)               # + supports, − disputes
```

On a **contested** lean there is no polarity: the ledger renders
threat-signed and feeds the two-sided tug (`04-dispositions.md` §5). A
**negative** anchored quality emits the contested lean itself
(`04-dispositions.md` §3, rule 7). A **none** lean has no ledger.

Three properties follow, and each is load-bearing:

- **The sum is the quality, by construction** rather than by convention. There
  is no second code path that could disagree with the ledger, which is what
  `01-profile.md` §5.1 requires and what makes a profile diff renderable.
- **`direction` is derived, not stored.** This is why the same signal renders
  upward on a malicious value and downward on a benign one. A profile storing
  direction would have to keep it in step with the sign of its own points.
- **Points are declared threat-signed.** `sightings.false_positive` carries
  negative points; `reporting.independent_orgs` positive. The profile author
  never thinks about dispositions, only about "does this evidence point at a
  threat, and how hard".

**The anchoring is the engine's, not the author's** (decided 2026-09-03 with
D11, `04-dispositions.md` §2). Implementations return threat-signed points
and never see the lean; the engine multiplies by the lean's polarity at
assembly. So the same row renders `+38` supporting a benign lean and `−38`
disputing a threat one, from one declaration. The verdict-relative staleness
exception an earlier draft carried is gone with its signal — staleness left
the ledger for the relevance axis (`06-staleness.md`), and every remaining
signal is plainly threat-signed.

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

One class per signal id, **discovered from the filesystem** rather than
registered anywhere in code — `app/Model/ValueSignals/` for the shipped
catalogue, `app/Lib/ValueSignals/` for what an instance admin drops in. That
is D12; §8 is the mechanism, its two in-tree precedents
(`Workflow`'s two module roots, `DecayingModel::listAvailableFormulas()`) and
the five rules a directory that executes its contents needs.

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

The base class §8.2 specifies wraps this interface with the identity the
loader reads — `id`, `group`, `description`, `default_band` and
`points_schema` — so that a signal the editor has never seen still renders a
configuration form.

**Built with four fields §8.2 did not name**, each because the mechanism
around it needs one:

| Field | Why |
|---|---|
| `config_schema` | `points_schema`'s sibling. §3 splits *what evidence is worth* from *the implementation's thresholds*, and the editor has to render both or a signal whose threshold lives in `config` is configurable only by hand-edited JSON — the half-usable drop-in `points_schema` exists to prevent |
| `absence_key` | which `points` key fires on absence, so §4.2's rule is declared rather than re-implemented per signal. `absenceFires()` on the base carries the exclusion guard with it |
| `reads` | which `$context` keys the signal needs, checked against `$context['missing']`. Without it a fact that could not be read would be scored as absent, which is §4.2's error in a different coat |
| `evidence_class` | `aggregate` or `row`, so §2.3's budget is enforceable rather than aspirational. It is the field the hot tier reads |

`$context` is built once per verdict and shared by every signal — the
occurrence tally, the sighting rows, the tag and galaxy sets, the warninglist
result, the corroboration dates. It is the same aggregate the live panels
already assemble through `ValueProfile::forX()`, and reusing it is the
difference between one query pass and twelve.

**A signal returns a row, not a number**, because it owns the prose. Only the
signal knows how to say *"47 sightings from 4 orgs, last 2 days ago"* and put
`12 sightings in the last 30 days` in `evidence`. The profile owns the points;
the implementation owns the sentence.

### 2.3 The context has a budget, and it is a time window

The context is every aggregate at once, and the Overview rail card computes
the verdict too — so this cost lands on every page open, not only on the
Verdict tab. For an `8.8.8.8`-class value the row counts are exactly what the
page's lazy panels exist to defer, and the corpus already owns the honest
precedent for data that cannot be processed: the flux value's 21,904
correlations, stated in `not_counted` rather than half-computed.

**Decided 2026-09-03** (`review-2026-09-02.md` A4). The budget is expressed
in evidence time — not in rows, and never on a stopwatch — and it is tiered:

- **Normal values: everything.** No budget in play, no note on the page.
- **Long-history values: the evidence window.** Past a volume threshold, the
  context's *row evidence* — sighting rows, per-occurrence tag and galaxy
  detail, the material signals quote in their prose — is fetched for the last
  `days` only, 90 by default. The cut is the `evidence.window` exclusion
  (`05-exclusions.md` §3), which makes it profile policy: a `policy` entry in
  `not_counted`, linked to the editor, one honest sentence — *"long history —
  scored from the last 90 days"*.
- **Hot values: the row-hungry signals bow out.** A value flagged in
  `over_correlating_values` — MISP's own "too hot" mechanism, and the flux
  value's — is one a window cannot bound: a live campaign puts everything
  inside 90 days. Signals needing row evidence are not evaluated and land in
  `not_counted` as `nodata` (*"too much data, not evaluated"*); the verdict
  is computed from the signals that can still run.

**Two evidence classes, declared per implementation.** Dates and counts —
the staleness clock's MAX, `reporting.independent_orgs`' COUNT DISTINCT,
`lifecycle.continuity`'s per-month buckets — are index aggregates, cheap at
any cardinality, and are computed **whole-history, always**: a window would
blind the freshness clock (a hash's TTL is 730 days) and undercount a
long-history value's reporting breadth. Row evidence is what the window
bounds, because rows were the cost. Each signal implementation declares which
class it reads, so the budget is enforceable rather than aspirational.

**Rejected: row caps and wall-clock budgets, on determinism.** The same
evidence must always produce the same verdict — the page, the simulator and
phase 10's worker all compute it, and they must agree. A row cap scores
whichever rows the query happened to return; a wall-clock budget scores by
server load. A stopwatch never decides what a verdict contains.

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

**Absent because excluded is not absent** (decided 2026-09-03,
`review-2026-09-02.md` B4). `$context` carries, per fact, the count the
exclusions removed. A signal whose input set was *emptied by exclusions* must
not fire its absence key — `sightings.volume_recency` seeing zero sightings
because `sightings.self` removed them all is not seeing a value nobody
sighted. The signal stays silent, and the exclusion's `policy` entry in
`not_counted` carries the explanation (`05-exclusions.md` §2.1, whose
verification 5 demanded exactly this). Absence keys fire only on genuine
absence.

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

**Widened by D12.** A drop-in directory adds a second way to reach this state:
an id the instance *has* but could not load, or one whose `evaluate()` threw.
Same treatment, different reason string — §8.5.

## 5. Q5 — is the band derived or editorial? **Decided 2026-09-07, D14**

> **Superseded by D16 on 2026-09-10: the band is removed entirely.**
> What follows is still the record of why it is not *derived* — that
> part holds. What it got wrong is the conclusion that it therefore
> had to be kept, and which quantity to test against. Read §5.1
> before acting on anything below.

**Editorial: the band is declared per signal in the profile.** Not a
preference — the fixture forecloses the alternative, and the table below is
the evidence rather than an illustration of it.

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

**Decided as D14** on the evidence above rather than on preference: no
threshold on `contribution` can reproduce the fixture's own labelling, so
"derived" was never available. The band answers a different question from the
contribution — *how much this kind of evidence matters in principle* against
*how much it produced here* — so two rows with the same number and different
bands is not an inconsistency to fix.

**One consequence for the schema, and it is already there.** `band` is a
profile field (§3) while `direction` is not (§2): the band is the author's
judgement and has nowhere else to live, the direction is the sign of a number
and would have to be kept in step if it were stored. The two look alike on
screen and are opposites in the data model.

`attribution.galaxy`'s fixture inconsistency above is still owed, and it is
phase 9's — one of the rows it changes.

### 5.1 Superseded — the band is removed. Decided 2026-09-10, D16

**D14 is right about what it proved and wrong about what follows.** The
band is not derived; it does not follow that it should be kept.

**D14 tested the wrong quantity.** It asked whether the label could be
derived from the **contribution** — what a signal produced on one value.
It cannot, and it never could: the contribution is a function of the
evidence in front of it, so the same signal produces 9 on one value and
7 on another (D14's own example). No threshold on a per-value number can
label a per-signal property.

The quantity that *does* express "what this kind of evidence is worth in
principle" is the **ceiling**: the most the signal could ever contribute.
It exists for every signal — `cap` where there is one, the largest
positive entry in the points map where there is not — and it is **already
computed and already on screen**, because the attainable bound is
precisely the sum of these:

| Signal | Band | Ceiling |
|---|---|---|
| `reporting.independent_orgs` | strong | 28 |
| `sightings.volume_recency` | strong | 24 |
| `attribution.galaxy` | strong | 21 |
| `lifecycle.continuity` | moderate | 12 |
| `reporting.published_ratio` | moderate | 9 |
| `attribution.technique` | **weak** | **9** |
| `lifecycle.feeds` | moderate | 8 |
| `lifecycle.recency` | moderate | 8 |
| `lifecycle.warninglist` | weak | 6 |
| `record.temporal_precision` | weak | 4 |
| `sightings.false_positive` | **moderate** | **0** |

`28 + 24 + 21 + 12 + 9 + 9 + 8 + 8 + 6 + 4 + 0 = 129`, which is the
shipped default's attainable bound exactly.

**Against the right quantity the labelling nearly works, and then does
not.** `strong` is cleanly the top three (21–28). Below that it stops
meaning anything: `attribution.technique` is `weak` at a ceiling of 9
while `reporting.published_ratio` is `moderate` at the same 9, with no
stated reason; and `sightings.false_positive` is labelled `moderate`
though its ceiling is **0** — it can only ever subtract. Calling a purely
subtractive signal a grade of evidence is not an editorial judgement, it
is a field nobody was maintaining.

So the band is a word approximating a number the engine already knows,
and in the two places the word departs from the number, the departure
explains nothing and changes nothing.

**Decided: remove `band` from the signal schema.** Not renamed —
removed.

What goes:

- the per-signal `band` entry in the profile document, and
  `ValueSignalBase::$default_band`;
- `AnalystProfileFormTool::BANDS`, the select, and its validation;
- the ledger row's `weight` key in `ValueVerdictTool`, the copy of it
  `ValueVerdictDiffTool` carried into every diff row, and the three
  templates that print it (`value_verdict_ledger.ctp` — the span and
  the tooltip beside the points — `value_verdict_card.ctp` and
  `value_verdict_conflicted.ctp`), with their three now-dead CSS rules;
- the column from the editor's signals table.

**Removed 2026-09-10.** The list above was written from a survey that
found two templates; the removal found a third, `value_verdict_conflicted.ctp`,
and a second writer, `ValueVerdictDiffTool`. `bands.json` and
`simulate.json`'s `axes.band` keep their `band` — that key is the
quality band, which is the one the word now belongs to.

**Back-compat:** `parameters` is opaque JSON, so a `band` stored on an
existing fork is simply ignored once nothing reads it. No migration.
Confirmed before removing the writer: after the change no file under
`app/` reads `default_band`, and every surviving `band` is the quality
band.

**Three things this buys.**

1. **The word *band* means one thing again.** It was naming both this
   label and the derived quality band (`none`/`low`/`medium`/`high`) —
   on the same screen, in the same ledger. That collision was the
   reported confusion; the rename would have fixed the symptom.
2. **The editor stops showing a control that looks like a dial.** A
   reader who lowered `strong` to `weak` expecting the score to move was
   right to expect it: it sits beside a points column and reads as
   arithmetic.
3. **D14's outstanding obligation dissolves.** §5 records a fixture
   inconsistency that "has to be fixed either way" — `attribution.galaxy`
   labelled `moderate` on one value and `strong` on another. With no
   field there is nothing to reconcile, and phase 9 has one less row to
   change.

**What is lost, stated plainly.** An organisation can no longer say *"in
our shop this kind of evidence is weak"* without changing numbers. That
was the case for keeping it. It is not worth a field that contradicts the
numbers beside it — and the honest way to make that statement was always
to change the points, which is the thing that actually scores.

## 6. The catalogue

Eleven signals form the v1 catalogue, derived from what the page already
claims rather than invented — which is the point — after two promotions and
one addition under D11: `reporting.to_ids_stance` left the catalogue for the
lean derivation (`04-dispositions.md` §3), `lifecycle.staleness` left it for
the relevance axis (`06-staleness.md`), and `record.temporal_precision` joins
as the quality reading of the relevance axis's honesty inputs.

| id | Group | Reads | Fires on absence | Rows in fixture |
|---|---|---|---|---|
| `reporting.independent_orgs` | Reporting | distinct orgs holding an occurrence | no | 3 |
| `reporting.published_ratio` | Reporting | published vs draft events | no | 2 |
| `record.temporal_precision` | Lifecycle | `first_seen` presence, created-to-published lag (`06-staleness.md` §3.6) | no | 0 — new under D11 |
| `sightings.volume_recency` | Sightings | sighting count, org spread, recency | yes (`none_recent`) | 3 |
| `sightings.false_positive` | Sightings | false-positive sightings and their org spread | no | 3 |
| `attribution.galaxy` | Attribution | galaxy clusters on occurrences | yes (`absent`) | 3 |
| `attribution.technique` | Attribution | ATT&CK techniques | no | 1 |
| `lifecycle.warninglist` | Lifecycle | warninglist hit and its category | yes (`no_hit`) | 2 |
| `lifecycle.feeds` | Lifecycle | presence in enabled feeds | yes (`no_feed`) | 1 |
| `lifecycle.continuity` | Lifecycle | months without a gap in activity | no | 1 |
| `lifecycle.recency` | Lifecycle | how recently it was reported | no | 1 |
`lifecycle.decay` is **not** in the catalogue. Its three fixture rows (+12, −8,
+16) are retired by D7, and under D11 nothing replaces them in the ledger —
the time story is the relevance axis, its own chip and runway
([`06-staleness.md`](06-staleness.md)).

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

The four demo values anchor the regression set. The default profile's points
are chosen so that running the engine over them produces recognisably the
verdicts already on screen — that is the only available ground truth, and it
is a good one because the fixture's numbers were authored by someone reasoning
about real evidence. It is also a skewed one: all four are outliers, which is
§7.4's subject.

### 7.2 Trust-weighting is off in the shipped default

`trust_weighted` is declared on the org-derived signals but the default's
`org_trust` map is empty (phase 6), so it is inert. `01-profile.md` §1.3's
"empty means as before" — the feature ships changing nothing until an analyst
grades an org.

**Honoured 2026-09-07, and still inert by default.** Phase 6 made the flag
mean something: the three signals declaring it replace their organisation and
row counts with weighted ones (`07-reference.md` §2.4), reading
`ValueTrustTool` rather than implementing the arithmetic each, so a drop-in
signal declaring `trust_weighted` gets it too. The default is unchanged
because an empty `org_trust` map takes the mechanism out of the path entirely
rather than weighting everything at `1.0` — which makes this paragraph
structural instead of arithmetic (§7.6 there).

One thing that follows for this document: **the three signals now read a tool
the loader does not provide.** A signal file is discovered and `require`d, so
it carries no `App::uses` of its own and its dependencies are the engine's to
declare — `ValueVerdictTool` does. A dropped-in signal reaching for something
the engine has not loaded lands in `not_counted` with the class name on the
page, which is §8.5's guard doing its job; it is also how three of this
corpus's own harnesses spent an afternoon printing `ok` against every
assertion that did not name those signals (`07-reference.md` §7.7).

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

The test is therefore: **the disposition matches on all five regression cases
(§7.4), the score is within a stated tolerance, and every divergence from the
fixture is named in this document's verification section.** The alternative —
bending the shipped weights until the numbers match — would produce a default
profile shaped by four hand-written examples rather than by any coherent
judgement, which is the opposite of what §7.1 is for.

### 7.4 The median value joins the regression set

**Decided 2026-09-03** (`review-2026-09-02.md` A1). The four demo values are
all outliers — heavily corroborated, multi-org, sighted, attributed. The
production median is none of that: **one occurrence, one organisation, no
sightings, no galaxy, no warninglist hit.** On most instances that shape *is*
most of the corpus, so a default calibrated only against the outliers makes
the verdict on the majority of real pages an accident of leftover weights
rather than a decision.

The regression set is therefore five cases: the four demo values, plus the
median shape seeded as an **ordinary value on the dev instance** —
deliberately not a fifth fixture demo. The demo values carry authored arrays
because each makes an argument; the median's entire point is what the engine
produces unaided.

**The intended outcome (restated 2026-09-03 under D11): lean `threat`,
quality `low`, nothing more.** One organisation asserted it and nobody
corroborated — the page says exactly that, which is more honest than this
section's first draft (a deliberate UNKNOWN): the assertion is real *and*
thin, and the three axes can finally say both. The summary reads as *one
organisation, no corroboration*, never as an error.

Stated as the calibration rule: **a single-org, sighting-free record never
leaves the `low` quality band under the shipped default**
(`04-dispositions.md` §6). An analyst who wants single reports from their own
trusted source to scream is one fork away from it; the default must not ship
it.

### 7.5 The weights as shipped, and what they produce

Authored 2026-09-07. The shape of each is the implementation's; the numbers
are this table, and every one of them is a judgement an analyst can fork.

| id | `points` | `config` | Band |
|---|---|---|---|
| `reporting.independent_orgs` | `per_org 7`, `cap 28` | `named 4` | strong |
| `reporting.published_ratio` | `scale 9`, `none -2` | `min_events 1` | moderate |
| `record.temporal_precision` | `dated 4`, `undated -6`, `lagged -6` | `lag_days 30` | weak |
| `sightings.volume_recency` | `cap 24`, `none_recent -4` | `saturation 50`, `stale_days 90`, `stale_factor 0.5` | strong |
| `sightings.false_positive` | `per -3`, `per_extra_org -4`, `cap -26` | — | moderate |
| `attribution.galaxy` | `per_cluster 7`, `cap 21`, `absent -7` | — | strong |
| `attribution.technique` | `per_technique 3`, `cap 9` | — | weak |
| `lifecycle.warninglist` | `no_hit 6`, `false_positive_hit -38`, `known_hit 0` | — | weak |
| `lifecycle.feeds` | `per_feed 4`, `cap 8`, `no_feed -2` | — | moderate |
| `lifecycle.continuity` | `per_month 1`, `cap 12` | `min_months 3` | moderate |
| `lifecycle.recency` | `recent 8`, `old -4` | `recent_days 30`, `old_days 365` | moderate |

Four of them carry a judgement worth reading twice, because the number alone
does not show it:

- **`lifecycle.warninglist`'s `known_hit` is zero**, and that is the design
  rather than an unset field. A `known`-category hit says *this is shared
  infrastructure*, not *this is harmless*: the row belongs on the page,
  counted for neither side, and the contradiction with wide reporting is
  named by an escalation instead of netted off in arithmetic
  (`04-dispositions.md` §4). Until phase 6 ships the name map, an
  unresolved hit reads as `false_positive` — the column's own default, and
  what MISP's warning banner has always meant by a hit.
- **`sightings.volume_recency` saturates logarithmically.** `cap 24` with
  `saturation 50` puts 47 sightings at +24 and 418 at +24, which is what the
  fixture authored for both — the step from 1 to 10 says far more than the
  step from 400 to 410, and recency multiplies the whole row rather than
  subtracting from it, so a stale history cannot be rescued by volume.
- **`attribution.galaxy` pays per cluster, not per occurrence.** The flux
  value's *"QakBot, on 107 occurrences"* is one judgement repeated; a
  per-occurrence weight would have paid for it 107 times. The count belongs
  in the prose, where it is context rather than arithmetic.
- **`reporting.published_ratio` is a ratio, so a small record can outscore a
  large one on it.** One published event out of one is +9; five out of seven
  is +6. That reads oddly until you notice the fixture does the same thing in
  the same direction — *"5 of 7"* is +9 there and *"121 of 137"* is +7 —
  because breadth is `reporting.independent_orgs`' question and this one is
  only *did they stand behind it*. The floor keeps a single published event
  from being worth nothing.

### 7.6 What they produce, measured

Synthetic contexts built from the page's own claims about the demo values
(harness), and real rows (probe). §7.3 said exact reproduction is neither
achievable nor the test; these are the numbers it is:

| Case | Quality | Band | Against |
|---|---|---|---|
| the malicious demo value's facts | **98** | high | the fixture's 84, also high |
| the same facts under a benign lean | −98 | low | the anchoring, checked by negation |
| the median shape (§7.4) | **21** | low | the rule §7.4 states |
| the instance's own median value | 0 | low | — |
| `8.8.8.8` on the dev instance | −3 | low | the fixture's BENIGN 91 — §11.2 |
| the instance's hot value | 45 | medium | seven signals of eleven |

The malicious value's +14 over the fixture is the sum of rows the fixture
does not carry — continuity, recency, feed presence and temporal precision
are four signals the artboard's nine rows never had — and its `high` band is
the assertion that matters.

## 8. Q11 — the extension point: **decided 2026-09-07, D12**

**Signals are discovered from the filesystem, not registered in code.** An
instance admin drops a PHP file in a directory and the engine picks it up.
Nothing about the catalogue is hardcoded: §6's eleven signals are eleven
files, a twelfth signal is a twelfth file, and the code that would have held
a list of them does not exist.

This overrides the "neither, in v1" recommendation this section carried until
2026-09-07. That recommendation was argued on the cost of designing an
expression language — and it is still right about the language, which stays
rejected. What it got wrong was treating "an implementation class" as the
expensive option. It is the cheap one, because **MISP has already built this
loader twice** and this phase can follow it rather than invent it.

### 8.1 Two roots, following `Workflow`

| Root | Holds | On upgrade |
|---|---|---|
| `app/Model/ValueSignals/` | the shipped catalogue — §6's eleven | replaced |
| `app/Lib/ValueSignals/` | what the admin drops in | never touched |

Exactly `Workflow::MODULE_ROOT_PATH` and `Workflow::CUSTOM_MODULE_ROOT_PATH`
(`Workflow.php:77-78`), which are `app/Model/WorkflowModules/` and
`app/Lib/WorkflowModules/`. `DecayingModel` is the same idea with one root:
`__listPHPFormulaFiles()` is a `Folder::find('.*\.php', true)` over
`app/Model/DecayingModelsFormulas/` minus `Base.php`, and
**`listAvailableFormulas()` builds the formula catalogue by walking that
listing** — the decay UI's formula dropdown is already a directory read.

Two roots rather than one because the split is what makes the drop-in
durable: a local signal in `app/Lib/` survives every `git pull`, and a
shipped signal is never something an admin has to merge.

**This moves the shipped signals out of `app/Lib/Tools/ValueSignal/`**, where
§2.2 put them. `app/Lib/Tools/` is an `App::uses` target in MISP, not a
discovery root — nothing scans it. `ValueVerdictTool` itself stays there;
only the signal implementations move.

### 8.2 A signal declares itself

The interface in §2.2 gains what discovery needs, and the shape is
`WorkflowBaseModule`'s: the class carries its own identity, and the catalogue
is the union of what the files say about themselves.

```php
abstract class ValueSignalBase
{
    public $id;             // 'reporting.independent_orgs' — the profile's key
    public $group;          // Reporting|Sightings|Attribution|Lifecycle
    public $description;    // one line, shown in the editor's palette
    public $default_band;   // strong|moderate|weak — the profile may override
    public $points_schema;  // the keys this signal reads from profile.points
    public $version = 1;

    public function getConfig();                          // for the palette
    abstract public function evaluate(array $context, array $config);
}
```

`points_schema` is the one addition that is not Workflow's. It exists so the
editor (phase 8) can render a form for a signal it has never seen — a custom
signal reading `{"per_asn": 5, "cap": 20}` gets two number fields and a
label, without which the drop-in is only half usable: the admin would have to
hand-edit JSON to configure the file they just wrote.

### 8.3 Discovery makes a signal available, never active

**A dropped file changes no score until a profile references its id.** The
profile's `signals` list stays the enable list, exactly as in §3.

This is load-bearing rather than conservative. If discovery meant activation,
an admin copying a file would move every quality number on the instance with
no edit to any profile and no revision bump — and phase 10's materialised
assessments key on `profile_revision` (`11-restsearch.md` §4.2), so they
would not even recompute. It would also break the profile diff §5.1 buys:
"change a weight, see every affected row's old and new contribution" needs
the profile to be the whole story.

So a new signal is two steps: drop the file, add it to a profile. The editor
makes the second one a click (phase 8, §4).

### 8.4 A colliding id is refused, not overridden

A custom file declaring an id the shipped catalogue already uses is **rejected
with a named error**, and the shipped signal keeps the id.
`Workflow::__getClassFromModuleFiles()` throws
`WorkflowDuplicatedModuleIDException` on the same condition; this phase logs
and skips rather than throwing, per §8.5.

Letting the custom file win is the tempting choice and it is wrong here. The
profile references ids, and a ledger row names the id it came from, so a
silent override means two instances render `reporting.independent_orgs` in a
ledger while computing two different things — with nothing on screen saying
so. An admin who wants to replace a shipped signal disables it in the profile
and enables `reporting.independent_orgs.local` instead: one line of JSON, and
the ledger tells the truth about which ran.

### 8.5 A broken file is an honest state, never a fatal

Three failure modes, all of which a drop-in directory invites and none of
which may take the page down:

| What | Result |
|---|---|
| The file does not parse, or the class is missing | logged, skipped; the id is unavailable |
| It loads but is not a `ValueSignalBase` | same |
| `evaluate()` throws, or returns a malformed row | the signal goes to `not_counted` with the reason |

The last one is why the engine validates the returned row rather than
trusting it: `contribution` must be an integer, and a signal returning a
float, a string or an array would break §5.1's exact sum silently — the one
invariant that cannot be allowed to fail quietly. A malformed return is
treated as "could not run" (§4.3), which is a state the page already renders.

**§4.4 widens to cover this.** It was written for an id the instance does not
have; a drop-in directory adds *an id the instance has but could not load*.
Both go in `not_counted` with the id named, and the difference is the reason
string. `Workflow` keeps exactly this in `$error_while_loading` and surfaces
it in the UI, which phase 8 should copy: an admin who drops a file with a
typo in it needs to see the typo, not an absent row.

### 8.6 One scan per request, and no cache

`Workflow` memoises with a `module_initialized` flag and re-scans each
request; `DecayingModel` re-lists the directory on every lookup and comments
that it is "redundant in some cases but better be safe than sorry". Follow
the first: memoise per request, do not cache across requests.

The Overview rail card computes an assessment on every page open (§2.3), so
this runs on more than the Assessment tab — but the scan is a `Folder::find`
over a dozen files, and a Redis-cached catalogue would mean an admin drops a
file and nothing happens until something busts a key they have never heard
of. The failure mode of the cache is worse than its cost.

### 8.7 What this settles, and what it does not

**The "class or data" half of Q11 is answered: class.** A small expression
language over `$context` stays rejected for the reason the old
recommendation gave — a language to design and a sandbox to defend.

**The "visible to others" half dissolves.** It was posed as a per-user
question, and a file on the filesystem is not one: a dropped signal is
instance-wide by construction. What is per-profile is whether it is
*enabled*, and D3's resolution already answers that — an org profile
enabling a custom signal affects everyone in the org who has not forked,
which is exactly what an org profile is for.

**The honest consequence: this is an admin feature, not an analyst one.**
Writing a signal needs filesystem access to `app/`, so an analyst who wants
a new signal asks their instance admin. Reweighting, enabling and disabling
stay self-service, and that is where the original recommendation's argument
still holds: most of what an analyst would write a custom signal for is
reachable by reweighting the eleven.

**No privilege is added.** Writing PHP under `app/` is already total instance
compromise, so a directory that executes what is put in it grants nothing
new. Stated to close the question rather than leave it implicit — and it
carries one prohibition: **nothing in the UI ever writes a signal file.** No
upload, no in-browser editor, no import path that lands PHP on disk. The
profile editor edits JSON; signals arrive by whatever mechanism the admin
already uses to deploy code.

### 8.8 Escalations get the same treatment

`escalations` are implementations too (`04-dispositions.md` §4 — *"`when` is
declarative in the profile but each id resolves to a class"*), so they are
discovered from `app/Model/ValueEscalations/` and `app/Lib/ValueEscalations/`
under every rule above. One loader, two subject directories, and the
`conflict:` id namespace already implies siblings.

## 9. Verification

**Results, 2026-09-07.** The harness is `03-signals-engine-harness.php` (96
checks, no database, run with `php`); the probe is
`03-signals-live-probe.php` (36 checks, copied into
`app/Console/Command/AnalystSignalProbeShell.php` and run through `cake`).

| Item | Where | Outcome |
|---|---|---|
| 1 | container's `parallel-lint` | 17 files, no syntax error; nothing over 80 columns |
| 2 | harness | **84, 93 and 91** from the fixture's own rows, every row's authored direction kept, and the composition card summing to the same number by its own route |
| 3 | harness | a negative sum stays negative and bands `low`; a sum of exactly `0` is `low`, not `none` — `none` is the empty ledger's band |
| 4 | probe | the rows sum to the quality **to the unit on every value scored**, five of them. The *rendered* half is phase 9's, and only two of the four demo values exist on this instance — §11.2 records what they score |
| 5 | harness | the assessment computes, the id is in `not_counted` as `unavailable`, the hero still names the profile |
| 6 | harness | empty ledger, no quality, band `none`, and no note — a disabled signal is silent by design |
| 7 | harness | a null profile is the same and raises nothing |
| 8 | — | **phase 9.** Nothing renders a computed ledger yet |
| 9 | harness + probe | the median shape lands in `low` — 21 synthetic, 0 on the instance's own median value. The rule's universal form does not hold on weights alone: §11.1 |
| 10 | probe | deterministic across two runs; every aggregate-class row identical with the window on and off; the `policy` note appears only in force |
| 11 | probe | the instance's hot value (24,407 occurrences): the four row-class signals in `not_counted` as `nodata`, seven signals still fired, the sum exact, 10 queries |
| 12 | — | **phase 8.** There is no palette to appear in; the loader half is item 13 |
| 13 | harness | a dropped file no profile enables leaves the verdict **byte-identical** — asserted by comparing the whole array, not by reading the code |
| 14 | harness + probe | a file that will not parse, one with no class, one that is not a signal: each logged, skipped, id unavailable, and the other signals still score |
| 15 | harness | the collision is refused, the shipped implementation keeps the id and still fires |
| 16 | harness | `evaluate()` throwing, and a `contribution` that is a float, a string and an array: all four land in `not_counted` as `broken` **and the remaining rows still sum exactly** |
| 17 | — | **phase 3.** Escalations have no base class yet; the loader takes the subject when they do (§8.8) |
| 18 | harness + probe | discovery finds the shipped catalogue and says nothing about a directory that is not there |

The items, as specified:

1. `parallel-lint` over the tool and every signal implementation.
2. Unit: the accumulator against the three fixture ledgers as literal input,
   anchored to each value's lean — `+84`, `+93` (threat) and `+91` (benign,
   every row flipped) — with each row's `direction` matching what the fixture
   authored. This is the invariant test and B2's regression; no database.
3. Unit: a ledger whose anchored sum is negative emits the contested lean
   (`04-dispositions.md` §3, rule 7), and a sum of exactly `0` is a stated
   outcome, not a coin flip.
4. Against the dev instance, for all four demo values: the ledger's rows sum to
   the score, to the unit. Rendered, not computed in a test — the assertion is
   on what the page shows.
5. A profile with an unknown signal id: the verdict renders, the id appears in
   `not_counted`, the hero still names the profile.
6. A profile with every signal disabled: an empty ledger, no quality, no
   band. The lean is still derivable from stances and categories — how the
   hero words a lean that no quality computation backs, and whether §5.3's
   no-profile rule covers it, is flagged to phase 9 rather than decided here.
7. `resolveFor()` returning `null` (phase 1 §3.1): same as (6), and no error.
8. Dark theme, both verdict layouts, after the ledger is real — the fixture's
   row count changes, and `value_verdict_ledger.ctp` has never rendered a group
   with one row or twenty.
9. The median case (§7.4): one occurrence, one org, no sightings. Lean
   `threat`, quality in the `low` band, a full ledger rendered, the profile
   named — and the calibration rule asserted: no reweighting of the shipped
   default lifts this case out of `low`.
10. The evidence window (§2.3) on a long-history value: recomputing the
    verdict is deterministic with the window on and off; the aggregate-class
    rows (reporting breadth, staleness) are identical under both; the
    `policy` entry appears only with the window in force.
11. A hot value (`over_correlating_values`): row-hungry signals in
    `not_counted` as `nodata`, aggregate-class signals still fire, and the
    verdict renders rather than timing out.

**The loader, D12 (§8).** Every case below is a file dropped in
`app/Lib/ValueSignals/` on the dev instance and removed afterwards — the
mechanism is only worth having if a mistake in a dropped file cannot take the
page down, and that is what most of these assert.

12. A well-formed custom signal appears in the editor's palette, marked
    custom, and contributes a ledger row once a profile enables it — with its
    points form generated from the `points_schema` it declares, never
    hand-edited JSON.
13. **The same file, not enabled in any profile: every score on the instance
    is byte-identical to before it was dropped** (§8.3). Verified by
    recomputing all four demo values either side, not by reading the code.
14. A file that does not parse; a file whose class is missing; a class that is
    not a `ValueSignalBase`. Each: the page renders, the other signals score,
    the id is unavailable, the error is in the log and on the editor's page
    (§8.5).
15. A file declaring an id the shipped catalogue owns: refused, the shipped
    signal keeps the id and still fires, and the collision is named (§8.4).
16. `evaluate()` throwing, and `evaluate()` returning a `contribution` that is
    a float, a string and an array. All four: the signal lands in
    `not_counted`, **and the remaining rows still sum to the quality exactly**
    — this is the case that protects §5.1 from third-party code.
17. An escalation dropped in `app/Lib/ValueEscalations/` that fails to load:
    the lean is unescalated *and the meta line says a rule could not run*
    (`04-dispositions.md` §4). The one loader failure that cannot hide in
    `not_counted`.
18. Both custom directories absent entirely — a fresh install, or an admin who
    deleted them. Discovery finds the shipped catalogue and says nothing.

## 10. Out of scope

- The disposition bands and the escalation rules (phase 3). This phase produces
  a signed total and calls `band()`; phase 3 implements it.
- `lifecycle.staleness` (phase 5) — the catalogue reserves its id.
- Trust weighting's data (phase 6) — this phase declares the flag and applies
  a scale factor of 1 until there is a map.
- Any writing, caching or storing of a verdict. Render-time only
  (`01-profile.md` §5.5).
- A signal authored as **data** — an expression language over `$context`.
  Rejected with Q11 (§8.7); a signal is a class.
- Any UI that writes a signal file. §8.7's one prohibition.
- Discovery for the other sections. `exclusions` and `relevance` are
  configuration the engine reads directly; only `signals` and `escalations`
  resolve to implementations, and §8.8 covers the second.

## 11. What building it changed

Eight things the specification did not know, each measured rather than
argued.

### 11.1 §7.4's calibration rule needs a clamp, not weights

**The rule as written does not hold, and cannot.** *"A single-org,
sighting-free record never leaves the `low` quality band under the shipped
default"* is satisfied for the median *shape* — one occurrence, one month, 21
points — but a single organisation reporting the same value every month for
fourteen months, carried by three feeds, reaches **quality 43, band
`medium`** under these weights. The harness prints that case rather than
asserting the rule it fails.

It is not obviously the wrong answer: a fourteen-month record on three feeds
is not the thin record the rule was written about. But it is not what the
rule says, and no weighting closes the gap — the positives a record of that
shape can attain sum past `medium` unless every one of them is shrunk to the
point of saying nothing about the values that *do* have corroboration.

**So the rule belongs in the banding, as a clamp, and that is phase 3's
section** (`04-dispositions.md` §6). Phase 2's honest half is asserted: the
median shape lands in `low` with room to spare, and the three absences that
put it there — nobody sighted it, nobody attributed it, no feed carries it —
are asserted individually so a future reweighting cannot quietly remove them.

**Closed 2026-09-07 by phase 3.** `thin_record_clamp` ships in the default's
`thresholds`, with the whole condition in the profile — one source, no
sightings, ceiling `low`. This section's own measured case is now asserted
twice over: 43 points, `medium` on the weights alone, `low` once the clamp
applies. Phase 3 found one thing this section did not anticipate — the
falsifiability line has to check the clamp *before* offering a points gap, or
it promises a band the clamp will refuse (`04-dispositions.md` §11.2).

### 11.2 `8.8.8.8` is a contested value on this instance, not a benign one

The fixture scores it BENIGN 91. The shipped default, over the dev instance's
own rows, scores it **−3 threat-signed**: eight organisations reporting it
(+28) against the public-resolver list (−38), four false-positive sightings
from three orgs (−20), no galaxy (−7), a 302-day encoding lag (−10), three
feeds (+8), continuity (+4), recency (+8) and 5 of 20 events published (+2).

Two readings, and both are worth having on the record:

- **A quality near zero is the engine saying the record argues with
  itself**, which is exactly what this value's evidence does. Under a benign
  lean it is +3 — a thin benign record, not a confident one.
- **The profile's own escalation is what should decide it.**
  `conflict:listed-vs-asserted` fires on a `false_positive` category plus a
  supermajority threat share, and this value has both. So the right answer
  for it is a **contested lean with a named rule**, which is phase 3's to
  emit — and it means the fixture's 91 is not a number these weights failed
  to reach. It is a different data set: nine occurrences authored to make an
  argument, against twenty-six real ones that disagree with each other.

**Closed 2026-09-07 by phase 3**, and the second reading is the one that held.
On the same rows, with nothing forced, the engine now reaches a contested lean
named by `conflict:listed-vs-asserted` — eight of eight organisations assert
it against the public-resolver list — with the tug at 74 supporting against 77
disputing. The quality is still −3, and that is now a legible number rather
than an awkward one: a contested ledger renders threat-signed, so a value
whose two sides are within three points of each other reads as exactly that
(`04-dispositions.md` §11.5).

### 11.3 A numeric value handed over as an integer scans the table twice

Found by the probe walking into it. PHP turns a numeric array key into an
integer, so iterating a map of values hands the value `1` over as `int 1` —
and comparing an integer against a `varchar` column makes MariaDB convert
**the column** rather than use its index. Measured on this instance's largest
value: **31 ms as a string, 9.4 seconds as an integer**, and worse than slow
— the loose comparison matched rows the string never would, so the ledger's
own evidence line quoted an occurrence count that was not the value's.

`ValueProfile::verdictContextFor()` therefore casts, with the measurement in
the comment; the probe casts too. `Value::prevalenceFor` already documents
the same trap from the other direction, which is the second time this feature
has paid for it.

### 11.4 The context is a fixed handful of queries, and the largest value is the cheapest

Measured on the dev instance, warm, under a load average of 3–8 — indicative
rather than a benchmark:

| Value | Occurrences | Queries | Context build |
|---|---|---|---|
| the instance's median value | 1 | 11 | 3 ms |
| `45.155.205.233` | 2 | 17 | 4 ms |
| `1.1.1.1` | 12 | 19 | 61 ms |
| `8.8.8.8` | 26 | 26 | 76 ms |
| the hot value | 24,407 | **10** | 16 ms |

The largest value is the cheapest, which is the budget working as designed:
the hot tier does not fetch row evidence, so the queries that remain are four
index aggregates. Each of those four costs 4–35 ms on the 24,407-occurrence
value, measured directly — so §2.3's *"cheap at any cardinality"* holds for
the aggregate class on this instance's worst case.

### 11.5 The dev environment cannot serve two of this feature's directories

`misp-track` mounts `app/Model`, `app/Controller`, `app/View`, `app/Console`,
`app/webroot`, `app/Locale` and six named `app/Lib` subdirectories. Neither
`app/files` nor `app/Lib/ValueSignals` is among them, so:

- the shipped profile had to be copied into the container by hand for
  `updateDefaults()` to see the eleven-signal catalogue;
- **a custom drop-in signal cannot be tested through the mount at all** —
  which is why §9's item 12 and half of 14 are the harness's rather than the
  probe's.

Two mount lines fix both, and phases 6, 8 and 9 will want them. Not this
phase's to change: the dev server's mounts are the user's.

### 11.6 The shipped default profile was never committed

`.gitignore` line 36 is `/app/files/*`, so phase 1's
`app/files/analyst-profiles/default-v1.json` — the file `updateDefaults()`
reads and the only source of an instance's default profile — existed in the
working tree and in nobody's clone. Phase 1's commit carries the migration,
the model and five documents, and not the profile.

The consequence is not cosmetic: a fresh clone has no default profile, so
`resolveFor()` returns `null` for every user and the whole feature is inert
with no error anywhere — phase 1's own §9 hands `null` on as *"not an error
path"*, which is exactly what would have hidden it.

Fixed here the way the other shipped data under `app/files` is tracked —
`git add -f`, as `dashboard-templates/`, `feed-metadata/` and
`community-metadata/` all are. Gitignore does not affect a tracked file, so
the next edit to the profile shows up normally.

### 11.7 A value with no occurrence had a ledger

Found in self-review, before the guard existed. §2 says *"a `none` lean has
no ledger"*, and the accumulator honoured that only if the caller passed the
lean — which phase 2 cannot, because the derivation is phase 3's. So a value
with no occurrence this viewer can see scored **no warninglist hit (+6), no
galaxy (−7), nobody has sighted it (−4)**: three absence keys, all of them
true, none of them about the value.

The rule is therefore stated as a fact about the *context* rather than about
the lean — no occurrence, no ledger — which is phase 3's rule 1 arriving
early because the alternative is an engine that reads its own blindness as
evidence. It is the same mistake as §4.2's excluded-versus-absent and §4.3's
unreadable fact, in the one place the specification had not looked for it.

### 11.8 `record.temporal_precision` is aggregate evidence

The specification listed it as a quality signal without saying which evidence
class it reads, and the implementation makes it `aggregate`: both facts — the
`first_seen` count and the worst encoding lag — arrive as a `SUM` and a `MAX`
in the same single-row aggregate the tally comes from. So it survives the hot
tier, which is the right way round: how honest a record's dates are is
exactly the sort of thing worth knowing about a value too big to read.

### 11.9 Half of `record.temporal_precision` was measuring nothing. Removed 2026-09-11

The signal read two facts. The second — `lagged`, a further **-4** when
the "encoding date" lagged the event's own dates past `lag_days` — is
gone, and the reason is that neither column it was built from means what
it was read as.

`MAX(TIMESTAMPDIFF(DAY, Event.date, FROM_UNIXTIME(Attribute.timestamp)))`
compares an analyst-typed date against a **last-modified** timestamp.
`Attribute.timestamp` is bumped by an edit, a tag, a sync update or a
delete, and it is the column sync compares to decide which copy is
newer; **the `attributes` table has no created column at all**, so there
was never an encoding date in MISP to measure from. On `8.8.8.8` the
302-day maximum came from an attribute last written the day *after* its
event was published, and a second occurrence sat under an event modified
a full year later. `Event.date` is the other half of the objection,
raised by the user who found this: it carries the very delay the
measurement was hunting for.

**This mattered more than the relevance axis it was built beside.**
Relevance emits no ledger row, so its half of the mistake only mislabelled
a state. This one deducted 4 points from the quality on every value the
bogus number flagged — so an unsound reading was moving the verdict, which
is the one thing the axis separation exists to prevent.

`config_schema` is now empty and `points_schema` is `dated` / `undated`.
The fixtures that still pass `max_lag_days` in their `temporal` block are
left alone: nothing reads it, and a fixture that keeps a retired key is
the cheapest possible assertion that nothing does.

What replaces it is not in this signal at all. `06-staleness.md` §7.11
records it: a declared assumption on the relevance axis, visible on
every page that shows one, rather than a measurement dressed up as a
reading.
