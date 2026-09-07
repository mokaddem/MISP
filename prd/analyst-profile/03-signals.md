# PRD: Analyst Profile — phase 2, signals and the engine

**Specification. Nothing built.** Depends on phase 1
([`02-store.md`](02-store.md)). This is the phase the Verdict tab has been
blocked on since the skeleton pass.

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
