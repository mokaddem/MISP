# PRD: Analyst Profile — phase 5, staleness

**Built 2026-09-07.** `ValueRelevanceTool`, the completed `relevance`
section, and the retirement of every decay read on the page. Depends on
phase 2 ([`03-signals.md`](03-signals.md)). Implements **D7** and **D8** —
the page stops reading `decaying_models` and the profile owns a per-type
TTL. Verified by 106 checks with no database
([`06-relevance-harness.php`](06-relevance-harness.php)) and 58 against
the dev instance ([`06-relevance-live-probe.php`](06-relevance-live-probe.php));
six findings are in §7, and the one that changed the design is that the
four states are not four (§7.1).

This is the phase that **retires live code**. Two panels and a chart overlay
currently read MISP's decaying models through `ValueDecayTool`, and all of it
went live in phase 23. That is a deliberate retirement with a recorded reason,
not a cleanup.

**Under D11 (2026-09-03) this phase is the relevance axis**
([`12-assessment.md`](12-assessment.md) §2.2): the same TTL, clock and runway,
no longer emitted as ledger points — relevance never touches the lean or the
quality sum. Temporal precision (a missing `first_seen`, the
created-to-published lag) joins as an input (§3.6). Reworked in place the
same day.

## 1. What ships

The **relevance axis** — `current` / `aging` / `expired` / `timeline
uncertain` — plus the TTL table it reads, plus the replacement of everything
on the page that currently displays decay. No ledger row: relevance is its
own axis (D11), never points.

Exit criterion: **`decaying_models` is not read anywhere under
`ValuesController`, and every value page states how fresh its value is and what
made it so.** Met, and checked from the query log rather than by grep —
§7.5 says why that distinction mattered.

## 2. Why the page stops reading `decaying_models`

Recorded in full at [`00-discovery.md`](00-discovery.md) Q8. The short form:

`DecayingModelsFormulas/Base.php:102` computes a model's `base_score` as
`Σ (taxonomy_ratio × tag.numerical_value)`, falling back to
`default_base_score` only when no tag matches a configured taxonomy. So a decay
score is largely **a restatement of the value's tags**, multiplied by a time
factor — while the verdict's `attribution.*` signals already score those same
tags directly.

Same evidence, two paths, no visibility into the overlap. And unattributable:
`tag_numerical_value_override` is per-user, stays in `user_settings` outside the
profile (D4), and moves the base score — so a knob the profile does not own
would silently change a number the profile appears to explain.

**The resolution is to take the time factor and leave the base score.** MISP's
decay answers *how bad is this* and *how stale is this* at once; the ledger
already answers the first from more evidence with a per-row audit trail. A TTL
is the second question with the first removed.

## 3. The `relevance` section

```json
"relevance": {
  "clock": "last_independent_corroboration",
  "decay_speed": 1,
  "type_rule": "shortest",
  "aging_fraction": 0.33,
  "lag_uncertain_days": 30,
  "ttl_days": {
    "default": 180,
    "ip-src": 90, "ip-dst": 90, "domain": 120, "hostname": 120,
    "url": 60, "email-src": 120,
    "md5": 730, "sha1": 730, "sha256": 730,
    "btc": 365, "filename": 365
  }
}
```

The pre-D11 draft carried this as `lifecycle.staleness`'s `config` inside the
`signals` list; it is now a **top-level profile section** — the seventh —
because it configures an axis, not a ledger row.

### 3.1 The curve

**MISP's polynomial, with `decay_speed` defaulting to 1** (D8).
`Polynomial.php:17` is:

```
score = base × (1 − (elapsed / lifetime)^(1 / decay_speed))     clamped at 0
```

At `decay_speed = 1` the exponent is 1 and this is linear. The two are not
alternatives — one contains the other — which is why "polynomial or linear" was
a false choice. `decay_speed < 1` holds value then falls off a cliff at the
lifetime; `> 1` drops fast then lingers. Neither is the default and neither
needs to be touched.

Exponential was rejected outright: `e^(−λt)` asymptotes and never expires, so
it has no TTL, which is the opposite of the requirement.

**Reuse the formula class, not the model.** `Polynomial::computeScore()` takes
`($model, $attribute, $base_score, $elapsed_time)`. Either call it with a
synthetic single-parameter model and `base_score = 1` to get the pure fraction,
or lift the one-line expression. **Recommendation: lift the expression.**
Passing a fake `DecayingModel` array through a class that expects a real one is
the kind of coupling that makes phase 10 hard and makes a future change to
`Polynomial` break this page for no reason.

### 3.2 From fraction to state

The curve gives a fraction in `[0, 1]`: 1 at zero elapsed, 0 at the TTL.
Under D11 it is never converted to points — relevance is its own axis. It
renders as a state, plus the runway (the fraction, drawn — §4.2) and the
dates that produced it:

```
current              fraction ≥ aging_fraction
aging                0 < fraction < aging_fraction
expired              elapsed ≥ ttl
timeline uncertain   the clock itself is not trustworthy (§3.6)
```

The discontinuity at the boundary is deliberate — **expiry is an event, not
a gradient.** A value one day past its TTL should read differently from one
a day before it, because that is what a TTL means and it is what makes the
relevance `changers` row (`04-dispositions.md` §8) worth stating.

**History, compressed.** The first draft emitted staleness as threat-signed
ledger points (fresh `+12`, expired `−18`), under which silence promoted
values to definite BENIGN and a freshly-confirmed `8.8.8.8` fell *out* of
BENIGN. The A6 correction (`review-2026-09-02.md`) made the points
verdict-relative; D11 then removed points entirely. Both failure cases are
now impossible by construction: an axis that never touches the lean cannot
push a value into BENIGN, and fresh confirmation of a benign record makes it
*current* — not more benign, not less.

### 3.3 The clock — `last_independent_corroboration`

**This is the load-bearing decision, more than the curve.** MISP's own answer
is *last sighting*, falling back to `last_seen`, then `timestamp`
(`Base.php:139–155`), and its consequence is that **a heavily-sighted value
never decays** — the term does nothing for exactly the values with the most
activity.

The profile's default is `last_independent_corroboration`: the most recent of

- an occurrence created by an organisation that did not already hold one, or
- a sighting from an organisation other than the occurrence's reporter,

after the phase 4 exclusions have run — so a self-sighting cannot reset the
clock, which is the same argument in a different place. The one exclusion the
clock does **not** see is `evidence.window` (`05-exclusions.md` §3.1): the
clock is a whole-history aggregate by declaration (`03-signals.md` §2.3),
because a windowed clock could not tell stale-since-91-days from
stale-since-three-years while `ttl_days` reaches 730.

The rationale: the clock should reset when *someone new confirms it*, because
that is what keeps an old indicator credible. An org re-sighting its own report
for the two-hundredth time is activity, not corroboration.

`clock` is configurable, with `last_sighting` and `last_occurrence` as
alternatives, because an analyst who disagrees should be able to say so and
because `last_sighting` is what MISP does and someone will want parity.

### 3.4 A value has several types — `type_rule`

`185.234.219.24` occurs as both `ip-src` and `ip-dst`, and the fixture's own
`to_ids` conflict rows show both. MISP's per-attribute decay never had to
answer this; a value-centric page does.

`type_rule` is `shortest` (default), `longest` or `most_common`. **`shortest`
because it is the conservative reading** — a value that is stale in any of its
roles is worth re-checking — and because the alternative silently extends a
short-lived indicator's life on the strength of a type it barely appears as.

**Honest state required:** the panel names *which* type supplied the TTL and
that others were shorter or longer. A single number with no provenance is how
someone concludes the page is wrong about a value they know well.

### 3.5 Defaults are conservative on purpose

`01-profile.md` §1.3 requires defaults that work unedited, and D9's direction
means these numbers may eventually gate exports. A TTL that is too short
silently drops indicators; too long, and it merely fails to flag a stale one.
**The asymmetry favours long**, so the shipped table errs generous — 90 days
for network indicators against MISP's shipped decay models' 30–60, and two
years for file hashes, which do not stop being the hash of a malicious file.

### 3.6 Temporal precision — the clock's own honesty

The example that forced D11: a phishing URL encoded two months after the
incident, no `first_seen`, so the clock falls back to a creation timestamp
and the axis would call a dead campaign *current*. The inputs that detect
this are already in the rows:

- **`first_seen` absent** on every occurrence — the observation date is
  unknown; only the encoding date is known.
- **Created-to-published lag** — the attribute's `timestamp` against the
  event's own dates; a lag beyond `lag_uncertain_days` means the encoding
  date is a poor proxy for the observation date.

When either trips, the state is **`timeline uncertain`**, rendered with the
measurement that tripped it (*"encoded ≥ 61 days after the event's dates; no
first_seen"*) — never a silently wrong `current`. The same facts feed
`record.temporal_precision`, the quality signal (`03-signals.md` §6): a
record that cannot date its own observations is a weaker record. The
uncertainty finally has somewhere to go.

## 4. What gets retired, and what replaces it

| What | Where | Replacement |
|---|---|---|
| Per-model decay bars with the `decayed` flag | `value_lifecycle.ctp` (161 lines, Overview rail) | A staleness statement: last corroboration, elapsed, TTL in force, runway left |
| The decay panel | `value_sighting_decay.ctp` (259 lines, Sightings tab, **live**) | The same statement at panel scale, with the corroboration timeline |
| Decay curve overlay | `value_sighting_chart.ctp:447–452`, iterating `$decay` | The **TTL runway** as the overlay |
| Dashed `NIDS decay score` comparison line | verdict `curves`, fixture `1133`, `3535`, `11463` | The TTL runway plotted against the verdict score |
| `ValueProfile::forSightingDecay()` | `ValueProfile.php:601` | `forStaleness()` |
| `ValueDecayTool` | `app/Lib/Tools/ValueDecayTool.php` (338 lines) | `ValueStalenessTool` |
| `ValuesController::viewSightingDecay()` | `ValuesController.php:275` | `viewStaleness()` |

Under D11's rename map the replacement names read `forRelevance()`,
`ValueRelevanceTool` and `viewRelevance()`; the table keeps the draft names
it was written with, and **those are the names that shipped**. Two rows
resolved differently from the plan:

- the rail card is `value_relevance.ctp` and the endpoint
  `viewRelevance`, with the panel registered as *Shelf life* rather than
  *Decay models*;
- the verdict `curves` and `curves_note` did **not** move here. They
  exist only in `ValueProfileFixture`, which phase 9 replaces wholesale,
  so rewriting the sentence now would be writing it twice — see §7.7 for
  what phase 9 owes.

The Lifecycle card is the one row that changed shape rather than
contents: it is a fixture-backed panel, so this phase made its freshness
third live and left the other two lines alone (§7.7).

### 4.1 The aggregation decision transfers intact

`ValueDecayTool`'s 338 lines go. **The decision phase 23 recorded does not.**
Its problem was *"turn per-attribute time facts into one value-level
statement"*, and its answer was *"take the per-day maximum across occurrences,
and label it with the occurrence holding it"* (`../value-profile-live/23-sightings.md`
§5).

`last_independent_corroboration` has exactly that problem — the corroboration
date is a maximum over occurrences and sightings — and exactly that answer,
including the labelling. **Name the occurrence that supplied the clock**, the
same way the decay panel names the occurrence holding the maximum. That is the
part of phase 23 worth carrying and it is why this retirement costs less than
the line count suggests.

### 4.2 The TTL runway, and why it is a better chart

The verdict's 90-day curves currently plot the synthesised verdict against a
dashed NIDS decay score — a second decay opinion, on a page that will have one.
Replacing it with the TTL runway plots **evidence strength against remaining
shelf life**, which is two different quantities rather than two estimates of
one, and answers a question an analyst actually has.

`curves_note` on every scored value explains the current curve in decay terms
(*"the dips between are decay"*). It is rewritten here, not in phase 9 —
the sentence is about this phase's subject.

### 4.3 What the page can no longer say, stated plainly

`excludeDecayed` is a real `restSearch` filter (`RestSearchComponent.php:50`,
applied at `MispAttribute.php:2208` and `2421`). After this phase, the Value
Profile page cannot explain why an occurrence stopped being exported.

**This is accepted, on the strength of D9**: the verdict score is to become the
freshness gate ([`11-restsearch.md`](11-restsearch.md)), so the page explains
the thing that will *become* the gate rather than the thing that is one today.
Recorded here so the gap is a known interim state rather than an unnoticed
regression — and it is the one item in this feature a reviewer is most likely
to raise.

`decaying_models` itself is untouched. The decaying tool, the REST surface,
`excludeDecayed`, `includeDecayScore` and every non-Value-Profile caller keep
working exactly as they do.

## 5. Relevance on screen

The four-segment bar the corpus knew as `confidence` — the one field with no
derivation story — is settled elsewhere under D11: it is the **quality
band**, derived in `04-dispositions.md` §6, and staleness no longer feeds it.
*"Strong evidence, 400 days old"* and *"weak evidence, fresh"* are now simply
two different (quality, relevance) pairs, which is what one number could
never say.

Relevance renders as its own chip beside the lean — `current` / `aging` /
`expired` / `timeline uncertain` — with the runway, the clock's date, and the
type that supplied the TTL (§3.4's honest state). **It renders on every lean,
benign included** (settling `12-assessment.md` §7's open point): a benign
classification ages too — the list membership and the stances that carried it
are themselves dated — and *"benign, last corroborated 2023"* is exactly the
prompt to recheck that hiding the chip would swallow. The copy adapts per
lean; the machinery does not.

## 6. Verification

**Run 2026-09-07. All nine items pass**, item 2 rewritten because the
grep it names cannot answer the question it is asked (§7.5). The harness
is [`06-relevance-harness.php`](06-relevance-harness.php), 106 checks
with no database; the probe is
[`06-relevance-live-probe.php`](06-relevance-live-probe.php), 58 checks
against the dev instance.

1. `parallel-lint` over the new tool, model method, controller action and
   templates. **Clean**, plus `node --check` over `value-profile.js`.
2. **The exit criterion, from the query log rather than from a grep.**
   `grep -rn "DecayingModel"` over the page's controller, model, tools and
   elements matches only prose about the retirement — but a grep cannot
   say that no *query* reaches `decaying_models`, which is what the
   criterion claims. The probe counts queries per endpoint and asserts
   both halves: **`forRelevance` 10 queries and `forSightingChart` 11,
   neither touching `decaying_models`**, and the log grew, so the zero is
   a measurement (§7.5). Both endpoints were 21 under the decay envelope.
3. The state at `elapsed = 0`, `ttl × 0.5`, `ttl × 0.8`, `ttl`,
   `ttl + 1 day` with `aging_fraction: 0.33`: **`current`, `current`,
   `aging`, `expired`, `expired`** — asserted, with the runway at 1.0,
   0.5 and 0.0 respectively and `runway_days` closing at exactly 0 on the
   boundary and −1 past it. **No ledger row at any of them**: the lean,
   the quality, the band, the tug, the composition and every ledger row
   are byte-identical across the boundary. §7.2 records why that had to
   be proved off a profile knob rather than off the value's dates.
4. `decay_speed` at `0.5`, `1` and `2` — three distinct curves, all
   starting at a full runway and all reaching 0 at the TTL; below 1 the
   value is held then falls off a cliff, above 1 it drops fast and
   lingers.
5. All three `clock` settings, on the harness's synthetic value and on
   the instance's busiest real one. On `8.8.8.8`: **2026-08-23**
   (independent sighting, CthulhuSPRL.be), **2026-08-25** (last sighting),
   **2026-09-01** (last occurrence) — three dates, each named, and the
   independent clock is never newer than the other two, which is §3.3's
   argument holding on real rows.
6. A value occurring as two types with different TTLs, under all three
   `type_rule` settings: `shortest` 60 (url), `longest` 90 (ip-src),
   `most_common` 60 — each naming the type in force and carrying both
   candidates. Real data adds a case the spec did not: `8.8.8.8` occurs
   as `ip-dst`, `ip-src`, `ip-dst|port` and `text`, so the panel reads
   *"TTL 90 days from ip-dst · shortest rule, over ip-src 90,
   ip-dst|port 180, text 180"*. The two composite types fall to the
   default, which the panel says.
7. A value with one occurrence and no sightings. Found on the instance
   rather than invented: **`circl.lu`** — one organisation, clock falls
   back to 2025-12-01, state `expired`, and the panel says *"Nothing has
   independently corroborated this value — one organisation reporting it
   is the claim, not its confirmation."* Not an error, not a blank.
8. The retirement is total — item 2's query-log assertion is this item's
   evidence too. An instance with no enabled decaying models is now
   indistinguishable from one with a dozen, because nothing on the page
   asks.
9. The runway overlay on the sightings chart, in both themes, driven by
   the real `value-profile.js` against a real fragment: **one line
   dataset labelled *Shelf life left*, 157 points spanning 0–100 on a
   pinned 0–100 axis, eleven bar datasets beside it, no JS errors.** The
   rail card and the two card-scale states were measured rather than
   eyeballed — chip colour, border style, fill width against track width
   and the aging tick's offset, in both themes, with no horizontal
   overflow. The verdict curves are phase 9's (§7.7).

3b. Temporal precision (§3.6): asserted end to end. A value with no
   `first_seen` and a 61-day lag renders `timeline uncertain` with the
   lag named, and `record.temporal_precision` deducts in the quality
   ledger. On real rows it is `8.8.8.8` — 0 of 26 occurrences dated, a
   302-day lag — which turned out to be the common case rather than the
   exotic one (§7.3).

## 7. What building it changed

Six findings, built 2026-09-07. The first three changed the design; the
last three are the retirement's own accounting.

### 7.1 The four states are not four, and expiry outranks uncertainty

§3.2 lists four states as though they were exclusive, and
[`12-assessment.md`](12-assessment.md) §3 reads the late-encoded phishing
URL as *"expired / timeline uncertain"* — two of them at once. Both are
right about something and the implementation had to reconcile them.

**The state is one word and the uncertainty is also a flag**, and the
order they resolve in is an argument rather than a preference between
labels. An encoding date is *later* than the observation it stands for,
so elapsed time measured from it is a **lower bound** on the true
elapsed time. A lower bound already past the TTL is past it on any
honest reading — so `expired` survives an untrustworthy clock, while
`current` and `aging` do not and degrade to `timeline uncertain`. The
page reads *"expired · timeline uncertain"* because both facts travel.

Measured on the dev instance: `45.155.205.233` is 1,728 days past a
180-day TTL with no `first_seen` anywhere, and reads `expired` with the
missing field named underneath. The same value inside its TTL would read
`uncertain`.

### 7.2 The axes share inputs — D11's invariant is *directional*

The harness's first attempt at *"the lean and the quality are
byte-identical with the axis at `current` and at `expired`"* moved the
value's dates and **failed by two points**. It was right to. Relevance's
fallback clock is `occurrences.newest`, and `lifecycle.recency` reads
that same key as evidence about the record — so moving it moves the
quality, legitimately.

So D11 does not say relevance and quality are functions of disjoint
data. It says **nothing downstream reads the relevance block**: the
ledger, the band, the tug and the composition are all computed before
the axis is assembled, and the axis emits no row. Proving *that* needs a
relevance-only knob, and `ttl_days` is one — the same context under a
90-day TTL and a 5-day TTL moves the axis with no input to any other
axis touched.

Both the harness and the live probe now prove it that way. Against real
rows, on three values: moving the TTL alone flips the state and leaves
the quality, the lean, the band and the whole ledger byte-identical.
`8.8.8.8` holds at −3, `45.155.205.233` at 4, `2.2.2.2` at 55.

### 7.3 `timeline uncertain` is the common state, not the exotic one

§3.6 introduces temporal precision through one pathological example.
Measured, it is the norm: **`8.8.8.8` has 0 of 26 occurrences carrying
`first_seen`, and a 302-day created-to-published lag.**
`45.155.205.233` has 0 of 2. Only `2.2.2.2` escapes — and it escapes on
*one* dated occurrence out of thirteen, because §3.6's rule is
`first_seen` absent on **every** occurrence.

That flip-on-one-row rule is kept, and the reason is the division of
labour D11 set up. Relevance asks *can this record date its observations
at all* — a yes/no about whether the clock means anything.
`record.temporal_precision` asks *how well*, grades the ratio, and
deducts proportionally in the quality ledger. An axis that degraded on a
*fraction* of undated occurrences would be scoring the record, which is
the other axis's job.

The consequence is worth stating rather than discovering: **the shipped
default will call most real values' timelines uncertain.** That is the
honest reading of MISP data — encoding dates are what MISP mostly
records — and not a calibration error. `lag_uncertain_days` is the knob
for an instance that disagrees, and a value whose org sets `first_seen`
gets a definite state immediately.

### 7.4 A fold that keeps dates and drops names reads as a data gap

The clock's sighting half is folded to one entry a day, because the
runway samples a day at a time and a second report on a day that already
has one cannot move the curve. The first version folded to bare
timestamps and labelled only the newest of them, on the reasoning that
nothing reads a name off a point on a curve.

Rendered, that put **`unnamed` on five of the six rows** of the
corroboration timeline — and §4.1's whole point is that naming what
supplied the clock is half the aggregation rule, not a decoration on it.
A name per distinct day costs 39 strings on this instance's busiest
value, so the thrift was about nothing. The fold now keeps the day's
newest report whole.

It also removed a latent bug rather than only bad copy. A folded day
stamp is midnight and a labelled entry kept its exact time, so the two
tied on a report filed at exactly 00:00 — and the unlabelled one won the
maximum, leaving the panel with a clock and nothing to name as its
source. With whole entries there is no duplicate to tie with.

### 7.5 A probe that measures nothing passes

§6 item 8 asks whether the retirement is total. A grep proves no source
line names `DecayingModel`; only a query log can prove no query reaches
`decaying_models`, so the probe counts queries. Its first run reported
**"forRelevance — 0 queries, 0 touching decaying_models"** and passed.

`DboSource` keeps 200 log entries and this page had already spent them,
so the log was not growing. Zero found and zero looked at are the same
number — which is phase 4's own §7.3 rule, reproduced inside the probe
written to honour it. Fixed by lifting `_queriesLogMax` and, more
usefully, by asserting that the log grew at all: a measurement now has
to be a measurement before its result counts.

With the cap off: **`forRelevance` 10 queries, `forSightingChart` 11,
neither touching `decaying_models`.** Both endpoints were 21 under the
decay envelope.

### 7.6 What the retirement cost, and the cap that did not survive

`ValueDecayTool` (338 lines) is deleted, `value_sighting_decay.ctp` (259)
with it, and `ValueProfile` lost 392 lines against 211 added — five
private methods gone, replaced by the facade and the runway curve.
`ValueStatsTool::anchorStamp` went too: MISP's *"`last_seen` if it has
one, else the timestamp"* rule has no caller left.

**The replacement is bigger than what it replaced and the machinery is
smaller**, which is worth stating rather than hiding.
`ValueRelevanceTool` is 1,039 lines and `value_relevance.ctp` 399, so
this is not a line-count win — it is a corpus whose files carry their own
arguments, and that tool holds the whole definition of an axis where the
deleted one held a sampling rule. What actually shrank is the shape of
the work: no per-occurrence envelope, no cap and no argument about which
occurrences could hold a maximum, no coupling to a formula class, and the
two endpoints that ran it went from 21 queries each to 10 and 11.

`SPAN_CAP_DAYS` moved across intact, argument unchanged: the runway is
still the only dense series in the sightings payload, because a count
can be sparse and a shelf life cannot.

**`OCCURRENCE_CAP` did not move, and its absence is the real
simplification.**
The decay curve was an envelope over one curve per occurrence, so it
needed a cap of 100 and the cap needed an argument about which
occurrences could hold the maximum — plus `groupByBase`, an exactness
proof for collapsing equal base scores, and a *"N of M occurrences
scored"* line for when the bound bit. A runway is computed from
aggregates and a list of dates, so **its cost does not track the
occurrence count at all** and there is nothing to cap. That is the sense in which
this retirement costs less than it looks: it did not reimplement the
envelope more cheaply, it removed the problem the envelope existed for.

### 7.7 Two things this phase did not do, and where they went

- **The Lifecycle card went a third live** rather than being left
  rendering a fixture literal in the retired bars' place. Its freshness
  line is this phase's subject, so `ValuesController::viewLifecycle`
  computes the axis and merges it into the fixture profile the card
  still serves for its warninglist and correlation lines. A card is not
  indivisible either, which is `00-contract.md` §14.12's note used at
  card scale rather than tab scale.
- **`curves_note` and the verdict's dashed NIDS line are phase 9's**,
  against §4.2's expectation that this phase would rewrite them. They
  exist only in `ValueProfileFixture` — four sites — which the wiring
  phase replaces wholesale, so rewriting the sentence here would be
  writing it twice. What phase 9 owes is recorded: the second curve
  becomes the TTL runway plotted against the quality, and the note says
  the dips are the shelf life running down between corroborations
  rather than *"the dips between are decay"*.

## 8. Out of scope

- Gating exports on the TTL. Phase 10, and stated as out of scope in
  `01-profile.md` §3.1 because "per-type TTL" reads like an export feature.
- Deprecating `decaying_models` anywhere but this page.
- Exercising the never-run `Sightings` and `PolynomialExtended` formulas. That
  task was carried into the verdict engine's scope note on the assumption this
  phase would put the formula classes under a microscope; **it no longer
  applies**, because the page stops calling them. It returns to
  `../value-profile-verdict-engine.md` §4 as decay work, unrelated to profiles.
- A per-galaxy or per-tag TTL. §3.4 notes type is a weak predictor of
  staleness; the better predictor is what the value was used for, which lives
  in tags, which is the door back to double counting. Not opened here.
