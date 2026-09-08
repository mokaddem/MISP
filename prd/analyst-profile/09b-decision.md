# PRD: Analyst Profile — phase 8b, the decision

**The workbench wins, and it is a hybrid.** Decided 2026-09-08 from the
three candidates published by [`09b-prototypes.md`](09b-prototypes.md).
§7 of that file requires a hybrid to be written down before 8c starts,
because *"the table from A with the split from B"* is a design decision
and not an implementation detail. This is that record.

| | Direction | Source | Artifact |
|---|---|---|---|
| A | the ledger sheet | `mockups/ledger-sheet.html` | published, not picked |
| **B** | **the workbench** | **`mockups/workbench.html`** | **picked** |
| C | the stated judgement | `mockups/stated-judgement.html` | published, not picked |

## 1. Against §7's four questions

Answered honestly, including the one B lost.

| Question | Winner | Why |
|---|---|---|
| Which would an analyst who has never seen the schema **read correctly**? | **C** | And B *failed* it in the first reading by a real reader. §3. |
| Which makes changing one number **fastest**? | **B** | One pane, sections switched from the rail, nothing to navigate to. |
| Which makes the consequence **unavoidable** rather than merely available? | **B** | By construction: the bench is the right half of the editor, so it cannot be skipped. This is the question that decided the phase. |
| Which still works at **1280px**, and on a profile a colleague wrote? | **B** | Its degradation is a built container query shown live in the page, not a caveat in a report. |

B was picked on the question the phase exists to answer. MISP already
shipped a simulator nobody used (`09-editor.md` §2); a simulator that is
a destination is a simulator that is not used, and B is the only
candidate that stops making it one. Losing the comprehension question is
a real cost, and §3 is the price paid for it rather than a note that it
was noticed.

## 2. The hybrid, stated

**B's frame, C's legibility for the assessment head.** Precisely:

- **Taken from C:** that the three axes are *named and glossed in the
  reader's language* rather than listed as fields, and that the relation
  between them is stated in a sentence the reader can read once and keep.
  C's whole bet was that a profile has readers who did not write it; the
  assessment head is where B needed that bet.
- **Not taken from C:** the document metaphor, chapters, numbered
  clauses, the serif measure, the deliberate simulate step. B stays a
  workbench.
- **Not taken from A:** nothing. A's folio-referenced schedules are a
  good answer to a question B does not have, because B's rail already
  solves *seven sections do not fit one table*.

## 3. What B got wrong, and what was changed

The first reader of the published candidate reported:

> I thought we had 3 axis: relevance, lean and quality but the main
> visualisation aspect is for quality.

That is a correct reading of the mockup and a wrong picture of the
engine, which makes it B's defect and not the reader's. Under D11 the
verdict *is* the assessment on three axes
([`12-assessment.md`](12-assessment.md)), and the value page's own hero
is to read `lean · relevance · quality`.

**Why every candidate drifted the same way.** Quality is the only axis
with an additive ledger, so it is the only one a design can *show its
work* for — and all three candidates put their visual mass where the
arithmetic was. B then listed `lean` and `relevance` inside
`bench-axes`, a flat six-item grid they shared with `signals fired`,
`attainable bound` and `saved`. Two of the three axes were rendered as
peers of *"saved: nothing, ever"*.

**Changed in the mockup on 2026-09-08**, before 8c rather than after:

1. **An assessment head** on all three benches: lean, relevance and
   quality as three cells at one rank. Verified rendering at equal width
   (spread 0px) at both 1600px and 1280px — equal rank is the claim, so
   it is measured rather than asserted.
2. **The relation stated once**, in the bench, and true of the value on
   screen: the lean anchors the ledger — every row is `points ×
   polarity` and quality is that anchored sum, with the band cut from
   it; this value is `contested`, so there is no polarity and the ledger
   renders threat-signed; a lean of `none` has no ledger at all;
   relevance is the clock and touches neither
   (`04-dispositions.md` §2, §2.4 of `12-assessment.md`).
3. **The ledger labelled as quality's** — *the quality ledger, the only
   axis that sums* — so the arithmetic below it is visibly one axis's
   evidence and not the page's verdict.
4. **The rail says which axis each section configures** (§4). Six of the
   seven sections reach exactly one axis, which is the fastest available
   proof that a profile is not a set of quality weights.
5. **The leftover metadata demoted** to a `bench-meta` row: `signals
   fired`, `attainable bound` and `saved` are not axes and no longer sit
   with them.

No figure changed. Every number in the new markup was already in the
file, and therefore already from a fixture.

## 4. The section-to-axis map

Sourced, because 8c has to build it and a wrong entry here teaches the
wrong thing on every page.

| Section | Axis it configures | Source |
|---|---|---|
| Signals | quality | contributions sum to quality (`12-assessment.md` §2.3) |
| Thresholds | quality — the bands | the banding is cut from the quality score and the fired count (§2.3) |
| Conflict rules | **lean** | an enabled escalation fires → `contested`, named (`04-dispositions.md` §3, rule list) |
| Exclusions | quality | they remove ledger rows; the ledger still sums to the score (`05-exclusions.md`) |
| Relevance | **relevance** | its own axis, never points (`06-staleness.md` §1, D11) |
| Reference data | quality — trust weighting | applies to signals declaring `trust_weighted` (`07-reference.md` §3) |
| Enrichment | none — context | it narrows which modules run; it emits no ledger row and no axis |
| Raw JSON | all three | it is the whole document |

## 5. Vocabulary, reconciled against what is built

Checked before any copy was changed, on the rule that the editor must
not invent a second name for something the value page already says. The
first pass at this check looked in `app/View/Elements/values/`, which
does not exist, and concluded there was nothing to reconcile. The value
page's elements are under
`app/View/Themed/Overmind/Elements/Values/View/`, and there are two
findings there.

**The relevance axis is already shipped, with a label.** Phase 5's
`value_relevance.ctp` maps the state key to display text:

```php
'current' => __('current'), 'aging' => __('aging'),
'expired' => __('expired'), 'uncertain' => __('timeline uncertain'),
```

So `06-staleness.md` §1's *"timeline uncertain"* and the engine's
`uncertain` were never drift — one is the label, the other the key. **The
mockup was printing the key**, which is precisely the second vocabulary
this check exists to catch, and it now prints the label. The fixtures'
composed form (`expired · uncertain`) is left as the fixture has it:
how the shipped page composes a qualifier onto an expired clock is not
the mockup's decision (§7).

**The lean is not rendered anywhere.** Zero word-boundary matches for
`lean` across the value view elements. What *is* built is
`value_disposition.ctp`, drawing the superseded model — a pill reading
`MALICIOUS | BENIGN | CONFLICTED | UNKNOWN` with a score beside it,
which is the one-number verdict D11 dissolves.

So the reconciliation does not conclude *"nothing to collide with"*. It
concludes: **the value page today asserts the vocabulary D11 retires,
and the editor is the first surface to render the replacement.** 8c will
ship an editor naming three axes while the page it configures still
shows `MALICIOUS 84`, until phase 9 converts the tab. A stated temporary
inconsistency is better than a discovered one.

Everything else lines up. The built engine's strings match the fixtures
exactly — lean `threat` / `benign` / `contested` / `none`; relevance
`current` / `aging` / `expired` / `uncertain`; bands `none` / `low` /
`medium` / `high` — and the three axes are always named in D11's order,
**lean · relevance · quality**, which is the order the mockup now uses.

## 6. What 8c inherits

1. **The refined B**, at `mockups/workbench.html`.
2. **The section-to-axis map of §4**, which is view-model shaped: the
   editor's view-model should carry the axis per section rather than
   leaving each template to remember it.
3. **The two blockers in [`09b-prototypes.md`](09b-prototypes.md) §9.2**
   — `profile.json` omits the signal the edit page is required to show,
   and profile 23 carries four names across three fixtures while
   `index.json` says 52 is the one in force. Both are 8a fixture and
   view-model defects, and 8c trips on both.
4. **The scaffolding fixes of §9.1**, already applied.

## 7. Still open

- **The refinement stops here for now.** The copy and layout of the
  assessment head are a first pass and the mockup remains the place to
  settle them; nothing else in B was touched.
- **`relevance` reads `uncertain` on the assessed value** in every
  fixture, which is the least self-explanatory state of the three axes
  and the one a reader is most likely to meet first
  (`09-editor.md` §549 flags the same thing). Worth a fixture that shows
  `current` before 8c draws the real thing.
- **Relevance has a magnitude, and no candidate could draw it.** Only
  quality carries a bar in all three candidates, and for the lean that is
  permanently right — it is categorical and timeless
  (`12-assessment.md` §2.1), so `contested` is not *more* than `threat`
  and a bar would invent an ordering. For relevance it is wrong. The
  shipped `value_relevance.ctp` already draws a shelf with
  `runway_days` — *"N days left"*, *"N days over"* — and
  `06-staleness.md` §4.2 designs the value page's chart precisely as
  **evidence strength against remaining shelf life**, two quantities
  both drawn. But `09a-fixtures/` carries **no runway at all**: the
  relevance axis is a bare string (`"uncertain"` before and after), so
  a candidate obeying *invent no number* had nothing to draw and every
  candidate rendered relevance as a word.

  This is the same bias as §3, one level down: the axis with the
  arithmetic gets the apparatus, and the fixture that fed the design had
  already dropped the other axis's. **8c should carry the runway into
  the editor's view-model**, and the fixture should carry it first.

- **The composed relevance form.** The fixtures carry
  `expired · uncertain`, and `value_relevance.ctp` renders one labelled
  state. Whether the shipped page says *"expired · timeline uncertain"*,
  *"expired"* with the uncertainty stated separately, or something else
  is unsettled, and the editor should follow the value page rather than
  lead it. The mockup relabels the standalone state only.
