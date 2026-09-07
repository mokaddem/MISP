# PRD: Analyst Profile — phase 5, staleness

**Specification. Nothing built.** Depends on phase 2
([`03-signals.md`](03-signals.md)). Implements **D7** and **D8** — the page
stops reading `decaying_models` and the profile owns a per-type TTL.

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
made it so.**

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
it was written with.

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

1. `parallel-lint` over the new tool, model method, controller action and
   templates.
2. `grep -rn "DecayingModel\|decay" app/Controller/ValuesController.php
   app/Model/ValueProfile.php app/View/Themed/Overmind/Elements/Values/` —
   no live reads remain. This is the exit criterion, mechanically checkable.
3. The state at `elapsed = 0`, `ttl × 0.5`, `ttl × 0.8`, `ttl`, `ttl + 1 day`
   with `aging_fraction: 0.33`: `current`, `current`, `aging`, `expired`,
   `expired` — the discontinuity asserted, and **no ledger row at any of
   them**. The lean and the quality sum are byte-identical with the axis at
   `current` and at `expired`; only the chip, the runway and the relevance
   `changers` row move.
3b. Temporal precision (§3.6): a value with no `first_seen` and a 61-day
   created-to-published lag renders `timeline uncertain` with the lag named,
   and `record.temporal_precision` deducts in the quality ledger — the
   D11-forcing phishing example, asserted end to end.
4. `decay_speed` at `0.5` and `2` on the same value — three distinct curves,
   all reaching 0 at the TTL.
5. All three `clock` settings on the malicious value: three different
   corroboration dates, each naming the occurrence or sighting that supplied
   it.
6. A value occurring as two types with different TTLs, under all three
   `type_rule` settings; the panel names the type in force each time.
7. A value with **one** occurrence and no sightings: nothing has independently
   corroborated it, ever. The clock falls back to the occurrence's own date and
   the panel says so — this is the majority case in production and must not
   render as an error or a blank.
8. An instance with no enabled decaying models at all: the page is unchanged by
   this phase, because it no longer reads them. Confirms the retirement is
   total.
9. The TTL runway overlay on the sightings chart and in the verdict curves, in
   both themes, with `curves_note` rewritten.

## 7. Out of scope

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
