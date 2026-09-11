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

**A revision round followed.** The reviewer's feedback on B, checked
item by item against the built code, and the plan for acting on it, are
[`09b-revisions.md`](09b-revisions.md). A and C are abandoned as of
2026-09-10 and are not kept in step with B.

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
| Thresholds | **lean + quality** | the bands are cut from the quality score and the fired count (§2.3) — **and `lean_supermajority` lives here too** (`AnalystProfileFormTool::sectionThresholds()`) |
| Conflict rules | **lean** | an enabled escalation fires → `contested`, named (`04-dispositions.md` §3, rule list) |
| Exclusions | quality | they remove ledger rows; the ledger still sums to the score (`05-exclusions.md`) |
| Relevance | **relevance** | its own axis, never points (`06-staleness.md` §1, D11) |
| Sources & reputation | quality — trust weighting | applies to signals declaring `trust_weighted` (`07-reference.md` §3) |
| Enrichment | none — context | it declares which modules are ticked; it emits no ledger row and no axis |
| Raw JSON | all three | it is the whole document |

**Corrected 2026-09-11** (`09b-revisions.md` §9.1). This table said
Thresholds was `quality — the bands`, and it is not: the pane holds the
lean's supermajority as well, which is precisely why a reviewer hunting
for *how is the lean decided* found neither of the two panes that decide
it. The lean has no single home — the threshold is here and the
escalations are in Conflict rules — so both panes now carry an axis tag
of their own rather than leaving the rail to say it. *Reference data* was
renamed *Sources & reputation* in the same round.

**One thing this map cannot say, and should not be read as saying:**
that a section configures an axis does not mean the axis reads that
section. Relevance reads none of the others and none of them reads it
(D11), and Enrichment reads and is read by nothing at all.

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
3. **The relevance runway in the editor's view-model.** The fixture and
   the mockup now have it; the controller does not yet assemble it. 8c
   should carry `ValueRelevanceTool`'s `runway`, `runway_days`,
   `elapsed_days`, `ttl` and `aging_fraction` through to the bench
   rather than reducing the axis to its state string a second time.
4. **One remaining blocker in
   [`09b-prototypes.md`](09b-prototypes.md) §9.2** — profile 23 carries
   four names across three fixtures while `index.json` says 52 is the
   one in force, so the simulate board's before column draws a profile
   the index says is not in force.

   **Dissolved 2026-09-11.** It was a fixture's inconsistency and the
   pages 8c built read the instance: `simulate` resolves the profile
   in force for the reader and names it, and the index names the same
   one because both call `resolveFor()`. Nothing carried the
   discrepancy across.

   The other one is closed, and was misdiagnosed here: the missing
   signal was recorded as a view-model defect that would block 8c, and
   the view-model was never at fault. `sectionSignals()` unions the
   catalogue with the profile's entries and emits the row already; only
   the edit board's *fixture* lacked it, and it now carries the same
   synthesis the palette fixture always had. Worth keeping as a note on
   method: the first reading compared two fixtures and inferred a defect
   in code neither of them was, and reading the tool settled it in one
   look.
5. **The scaffolding fixes of §9.1**, already applied.

## 7. Still open

- **The refinement stops here for now.** The copy and layout of the
  assessment head are a first pass and the mockup remains the place to
  settle them; nothing else in B was touched.
- **`relevance` reads `uncertain` on the assessed value** in every
  fixture, which is the least self-explanatory state of the three axes
  and the one a reader is most likely to meet first
  (`09-editor.md` §549 flags the same thing). Worth a fixture that shows
  `current` before 8c draws the real thing.
- **Relevance's magnitude — found missing, now drawn.** Only quality
  carried a bar in all three candidates. For the lean that is
  permanently right: it is categorical and timeless
  (`12-assessment.md` §2.1), so `contested` is not *more* than `threat`
  and a bar would invent an ordering. For relevance it was wrong. The
  shipped `value_relevance.ctp` already draws a shelf with `runway_days`,
  and `06-staleness.md` §4.2 designs the value page's chart as
  **evidence strength against remaining shelf life** — two quantities,
  both drawn. But `09a-fixtures/` carried **no runway at all**: the
  relevance axis was a bare string, so a candidate obeying *invent no
  number* had nothing to draw, and every candidate rendered relevance as
  a word. The same bias as §3, one level down — the axis with the
  arithmetic got the apparatus, because the fixture that fed the design
  had already dropped the other axis's.

  **Closed 2026-09-08.** `simulate.json` now carries
  `axes.relevance.runway` for the assessed value and for all four pinned
  values, read from the dev instance's own
  `/values/viewRelevance/<b64>` — `ValueRelevanceTool` through
  `value_relevance.ctp` — with each row recording its `runway_source`.
  B draws it on all three benches and in the comparison column, as the
  shipped shelf does: a track, a fill, and a mark where aging begins,
  coloured `--bs-correlation` because that is what the shipped relevance
  card uses. Deliberately **not** `--vp-dir-with`/`--vp-dir-against`:
  that pair means *supports / disputes* and a clock says neither.

  The comparison set is now the argument for the whole change. `1.1.1.1`
  reads quality **34, medium band**, and **111 days over** its TTL at the
  same time — well evidenced and long expired, which is the clearest
  statement on the page that these are separate axes. `185.234.219.24`
  has no clock at all, the same value whose lean is `none` and whose
  ledger does not exist.


- **The fixture and the live page disagree on two labels.**
  **Closed 2026-09-11** (`09c-wiring.md` §4.2): the label has one
  writer, `ValueRelevanceTool::stateLabel()`, which all three render
  sites now read, and the editor prints the label with the uncertainty
  marked separately rather than composing a second reading of the
  axis. The original note follows.
  `simulate.json` calls `45.155.205.233` and `1.1.1.1`
  *"expired · uncertain"*; the instance's own relevance card renders both
  as plain *"expired"*. The runway figures were taken from the instance
  and the composed strings were left as the fixture had them, so the
  mockup currently shows both — the state string from 8a's dump and the
  runway from the engine. One of the two is stale, and 8c should not
  inherit both.

- **The composed relevance form.** The fixtures carry
  `expired · uncertain`, and `value_relevance.ctp` renders one labelled
  state. Whether the shipped page says *"expired · timeline uncertain"*,
  *"expired"* with the uncertainty stated separately, or something else
  is unsettled, and the editor should follow the value page rather than
  lead it. The mockup relabels the standalone state only.
