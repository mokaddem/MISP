# Value Profile — History at occurrence scale

**Phase 19.** Extracted from [`value-profile-page.md`](../value-profile-page.md), where this
was §11. **The section numbering is deliberately kept:** the corpus carries
over a hundred references of the form `§11.x`, and they resolve to the
headings below rather than to anything in the main document.

---

## 11. Phase 19 — History at occurrence scale

Phase 18 §10.4 measured what §9.8 had predicted: the History tab renders one
collapsible section per occurrence, so `45.155.205.233` renders **748 sections
in a 2.4 MB fragment — from three audit entries**. This phase is that, and it is
the first phase whose design was settled by interview rather than by a candidate
deck, because §9.9's test comes out the other way here: the treatment is not
prescribed by the data, and four defensible answers existed.

### 11.1 What is actually wrong

Not the payload, though 2.4 MB is bad. **The organising unit stops organising.**
Phase 16 chose one section per occurrence deliberately, and renders one for
every occurrence whether or not anything happened to it — *"an occurrence
nobody has touched is a fact about the value, and a missing section would read
as one fewer copy of it."* That is a good argument at ten occurrences. At 812 it
means a tab whose job is "show me what happened" makes the reader scroll past
745 places where nothing did.

Phase 16's reasoning is not overturned here. It is scoped: it was right about a
value with ten copies and silent about a value with eight hundred.

### 11.2 The ten decisions, and why

1. **The occurrence stays the organising unit.** It is still the thing a
   value-scoped history adds over the per-event logs, which was §8's whole test.
2. **An occurrence with no entries gets no section — it becomes a stated
   count.** Phase 16's objection is answered by naming the number rather than by
   drawing 745 boxes. This is a server-side reduction: 748 sections become the
   ~190 that were actually touched.
3. **A brushable monthly activity chart is added**, driving the period. This came
   from review rather than from the design: the tab already has a period filter
   (`5033cd7b5`), and an audit trail's natural narrowing axis is time.
4. **A section the brushed period empties is dropped, not dimmed.** Today
   `setAuditCount` (`value-profile.js:2567`) dims it to `opacity-50`; at 190
   sections the dimmed ones are the whole problem restated. The count that
   replaces them names the period, so the reader sees a filter narrowing rather
   than data vanishing.
5. **The brush and the existing from/to inputs are one control.** Brushing
   writes the dates and fires `change`, so `activePeriod()` and the entire
   existing filter path run unchanged. The brush becomes a second way to set a
   value the tab already honours — and the window stays statable as two dates,
   which is what the empty-window copy needs.
6. **On landing the brush covers a fixed recent window**, as the Timeline's does
   (its `window` is a per-value literal, `2025-08-01 → TODAY` on three values).
   The chart draws the value's whole span regardless, so activity outside the
   window is visible rather than inferred. An empty window gets its own state:
   what is outside it, when the most recent change was, and `show all time`.
7. **The facet rail's counts follow the brushed period**, and say so. This
   reverses the recommendation this phase's interview started from, and the
   reversal is recorded because the argument for the other answer is seductive:
   *the rail is the only thing that can tell you 18 deletes sit outside your
   window*. True of the pre-brush design, and false the moment the chart exists
   — the chart is that signal. Every log browser in this class (Kibana, Splunk,
   Datadog Logs, Loki, Sentry, CloudWatch Insights) scopes sidebar counts to the
   selected range; none keeps corpus-wide counts. Two numbers per facet was
   considered and rejected: no tool in this class does it, it doubles the width
   of every row in a four-group rail, and it answers a comparison nobody asked
   for. The corpus total stays on screen **once**, in the header, which
   `value_history.ctp:700` already renders as `Showing <filtered> of <all>`.

   **This does not contradict the co-occurrence facet doctrine.** That rule was
   never *counts must not move*; it was *counts describe the value, not the
   page*. Paging is not a semantic narrowing, so counts that moved when you paged
   would lie. A time window **is** one — "this value, in March" is a real
   subject — and the co-occurrence bar has no time control, so it has nothing to
   scope to.
8. **The window is applied server-side.** This is the actual fix and the reason
   the phase is worth doing: ~30 KB rather than the ~600 KB that decision 2
   alone would leave, against 2.4 MB today. Per-section overhead measured at
   ~3.2 KB from phase 18's probe.
9. **`show all time` returns every changed occurrence and pages in the
   browser.** No new request pattern. The tab is bounded on landing and
   unbounded only when a reader explicitly asks for everything, which is a
   different proposition from 2.4 MB unasked. Recorded in §11.6 beside the
   existing client-side-paging entry rather than counted as fixed.
10. **The third brush is written here; collapsing all three is §12.** Each phase
    keeps to one kind of risk, and the two shipped brushes stay verified.

Two calls made without interview: the chart's bars are **monthly**, matching the
Timeline's spine over a 14-month span; and the **per-section entry pagers stay**,
appearing only where one occurrence has more than eight changes. A pager over
sections can coexist with them only because of phase 18 §10.2's nesting fix.

### 11.3 The five states, and which value shows each

Which state the tab renders follows from the value's data, so a state without a
demo value is a state nobody can look at.

| State | Renders | Demo |
|---|---|---|
| no `history` key | the sparse page | unknown value |
| `recorded === false` | the log is off; what the page still knows | **none** |
| recorded, no entries | the log runs, nothing for this value | **none** after this phase |
| populated | sections | `185.234.219.24`, `104.21.34.198`, `45.155.205.233` |
| populated, window empty | what is outside, and `show all time` | `8.8.8.8` |

The `recorded === false` gap is **pre-existing** — all four values pass
`'recorded' => true` — and it is the state `value_history.ctp:98` itself calls
*"the common case rather than the edge one: `MISP.log_new_audit` defaults to
false, so this is what a default instance renders"*.

Neither gap gets a demo value. A state only has to be **displayed once**: render
it by flipping the fixture, capture it, revert, and write the flip down. Two
one-line flips reach both:

```
recorded === false    fluxHistory():   'recorded' => false
recorded, no entries  benignHistory(): drop the six entries §11.4 adds
```

Authoring a fifth demo value — which by §9.7.5's rule renders all nine tabs —
to host one empty state is disproportionate, and rewriting a verified tab to
host one is the move §9.7.5 already declined.

### 11.4 What ships

**The numbers, so they are not re-decided.** The default window is **30 days**
back from `TODAY`. Sections page at **eight**, the size every other list on the
tab uses. The chart's bars are **monthly** over the value's whole span. The
sibling join's `SIBLING_JOIN_CAP` has no counterpart here: the window is the
bound, and it is a date rather than a count.

**Fixture.** `45.155.205.233` gets a real fourteen-month audit history: a few
hundred entries over roughly 190 of its 748 occurrences, with recent activity so
it lands populated. It must land populated — 748 occurrences arriving
continuously means `add` entries arriving continuously, so a value sighted
yesterday cannot have a log that went quiet in March. `8.8.8.8` gets six entries
in Feb–Mar 2025 and nothing since, so it lands on the empty-window state: a
public resolver whose tags were tidied once and never touched again.

**Server.** `viewHistory` takes a period. `history()` builds sections only for
occurrences with entries inside it; an occurrence with no entries at all gets
none at any period. `hidden` stays `total_occurrences` minus the occurrences the
panel was given, so the ACL footer keeps stating the ACL's number and not the
window's — which is why the window is applied to *sections*, not by handing the
builder a subset. The monthly bar data covers the whole span regardless of
window. The empty-window state and `show all time`.

**Template.** The chart above the existing date inputs; a pager over sections;
the dropped-occurrence counts, naming the period; the header's existing
`<filtered> of <all> entries`; the rail's note rewritten to name the period its
counts cover.

**Browser.** History's own brush, modelled on the Sightings one (which already
hides rows). It writes the date inputs and fires `change`. The rail re-tallies
from the `data-vp-facet` tokens rows already carry (`value_history.ctp:475`),
inside the loop `refreshList` already runs.

### 11.5 Verification

Ran against the rendered fragments and in headless Chrome, both themes. The one
step not covered is at the end.

1. `php -l` over `ValuesController.php`, `ValueProfileFixture.php` and
   `value_history.ctp`, `node --check` over `value-profile.js`. Clean, and no
   added line over 80 columns. (`ValuesController.php:62` is 82 and predates
   this phase.)

2. **Every count, by window, tallied from the fixture.** `silent` is
   occurrences with nothing logged at any period; `outside` is occurrences
   changed outside the window.

   | value | window | corpus | shown | sections | silent | outside | event rows |
   |---|---|---|---|---|---|---|---|
   | `185.234.219.24` | 30d | 38 | 18 | 2 | 0 | 4 | 3 of 10 |
   | `185.234.219.24` | all | 38 | 38 | **6** | 0 | 0 | **10** |
   | `104.21.34.198` | 30d | 51 | 12 | 3 | 0 | 2 | 4 of 9 |
   | `104.21.34.198` | all | 51 | 51 | **5** | 0 | 0 | **9** |
   | `8.8.8.8` | 30d | 6 | **0** | 0 | 4 | 1 | 0 of 2 |
   | `8.8.8.8` | all | 6 | 6 | 1 | 4 | 0 | 2 |
   | `45.155.205.233` | 30d | 623 | 50 | 17 | 551 | 180 | 9 of 121 |
   | `45.155.205.233` | all | 623 | 623 | 197 | 551 | 0 | 121 |

   The bold row is check 2 as it was written: at `all` the two populated values
   render 6 and 5 sections over 38 and 51 entries plus 10 and 9 event rows,
   which is what they rendered before this phase, entry for entry. Both have
   **no** silent occurrences, so decision 2 is invisible on them — which was
   the point of choosing them as the control.

3. **Payload.** `gzip -6` stands for `mod_deflate`, because the raw number is a
   DOM-size fact and the gzipped one is what crosses the wire.

   | fragment | raw | gzipped | sections | rows |
   |---|---|---|---|---|
   | flux, 30-day window | 218 KB | **11.7 KB** | 17 | 50 |
   | flux, brushed to March | 178 KB | 10.3 KB | 17 | 38 |
   | flux, `show all time` | **2.24 MB** | 76 KB | 197 | 623 |
   | `8.8.8.8`, 30-day window | 11 KB | 2.6 KB | 0 | 0 |
   | `185.234.219.24`, all | 150 KB | 9.9 KB | 6 | 38 |
   | `104.21.34.198`, all | 186 KB | 9.6 KB | 5 | 51 |

   **Two corrections to §11 the measurement forces, and they run opposite
   ways.** The 2.4 MB `HEAD` was supposed to render does not exist. At `HEAD`
   the flux fixture carried *no audit entries at all*, so its History tab
   rendered the *recorded, nothing logged* state in **1.1 KB** — the 748
   sections and 2.4 MB §11 opens with were phase 18's probe with entries
   injected to see what would happen, not what shipped. So this phase cannot
   claim a 2.4 MB → 218 KB cut; what it can claim is that the fourteen-month
   log it adds would have rendered as 2.24 MB unbounded, and lands as 218 KB
   instead.

   And §11.4's `~30 KB` was low, because it was derived from phase 18's 3.2 KB
   per *section* over a fixture with no *rows*. A rendered audit row is ~2.5
   KB, about half of it template indentation, so 50 of them cost more than the
   17 sections holding them. 11.7 KB over the wire is the number that matters,
   and 17 collapsible sections over 50 rows is a page a reader can hold, so
   the miss changes nothing about the decision — but the estimate was wrong
   and the reasoning behind it is worth not repeating.

4. **192 assertions in headless Chrome over 12 pages**, light and dark, zero
   failures. The pages carry the real fragments, the real `value-profile.css`
   and the real `value-profile.js`, plus the eight lines of `mispOvermind.js`
   the panel actually reaches for, and the `viewHistory` URLs parked where the
   panel's own base URL looks for them — so a re-fetch is a real fetch. §6.1's
   caveat is honoured: the first assertion on every page is that the stylesheet
   resolved, and the geometry assertions are worthless if it did not.

   What they establish, beyond the counts:

   - `45.155.205.233` lands with **eight occurrence sections and the pinned
     event-level one**, the section pager reading `1–8 of 17`, and page two
     holding another eight. The brush is over the window and not the chart.
   - Narrowing to one action **drops** the emptied sections rather than dimming
     them: every blank section carries `d-none` and none carries `opacity-50`,
     and the count above the list appears, says *no entry matching these
     filters*, and goes away again when the tick is cleared.
   - The rail **does not follow the tick**. `Added` reads the same before and
     after ticking `Added`, which is decision 7 as written — the counts follow
     the period, and a group that followed its own selection would drop every
     sibling to zero.
   - Brushing inside the fetched window is instant: the two date inputs take
     the brushed month, the rail re-tallies *down*, and the dropped count
     switches to naming the two dates. A click clears both.
   - Brushing **past** the fetched window re-fetches: the container's URL
     becomes `…/2025-03-01/2025-03-31`, the panel comes back listing sections,
     its header names March, and its date inputs are empty again — because the
     window is the fragment now, not a filter over it.
   - `8.8.8.8` lands on the empty-window state, naming the period, `6 entries,
     the most recent on 4 Mar 2025 — 144 days before this period begins`, with
     the brush pinned at the chart's right-hand end and the bars far to its
     left. `show all time` brings back the one section and the way back to 30
     days.

5. **Two visual bugs the screenshots found, both fixed.** The right-hand mask
   is a flex item and the window between the two masks is absolute, so the pair
   sat side by side from the left and left the *uncovered* strip at the far
   right instead of under the window — visible as one undimmed bar the moment
   the period was anywhere but the end of the chart. And at the Timeline's 62%
   the mask did not visibly dim 64px of bare bars in either theme; it is 80%
   here. **`.vp-tl-mask` has the same flex bug** and phase 20 inherits it
   (§12.1).

6. **One rule deliberately different from the two shipped brushes.** They read
   a click off *which bucket the pointer came up on*, so releasing on the
   bucket you pressed always clears and a one-bucket range cannot be selected
   at all. On a monthly chart that makes two months the finest period a reader
   can brush, which is not a control. Here a click is a pointer that travelled
   less than 4px. Phase 20 should carry this rule, not the other one.

7. **The two undemoed states, rendered once by §11.3's flips and reverted.**
   `fluxHistory(): 'recorded' => false` renders the *not recorded* panel with
   its `Knowable without it` rail — 748 occurrences' latest edits, the
   publication pair, 418 sightings. `benignHistory()` with its groups emptied
   renders *5 occurrences, nothing logged* and `None of its 5 visible
   occurrences has been touched since recording began`. That second one is only
   right because of check 8's fix; before it, the same state said `0`.

8. **Two states keyed on a number whose meaning this phase changed.**
   `history()['occurrences']` used to be *the occurrences the viewer can open*
   and is now *the sections built*. The suppressed state (`All %d occurrences …
   are on events you cannot see`) and the nothing-logged state both read it,
   and `8.8.8.8`'s quiet window made the first of them fire on a value with
   five readable occurrences. Both now read `visible`, which is the old meaning
   under a name that says so.

9. **Every panel of every value, with warnings promoted to errors.** 19 panels
   × 5 values, and the History panel renders for all five — 81, 66, 11, 218 and
   1 KB. The errors the harness reports are all in panels this phase does not
   touch and are all the harness's own gaps (no `mbstring`, no CakePHP
   `DistributionLevel`/`TextColour`/`Paginator` helpers).

10. **Not verified: the live endpoint.** `viewHistory/<b64>/<from>/<to>` and
    `…/all` resolve through CakePHP's default route and carry no `.`, so
    `parseExtensions('xml','json','csv')` cannot bite them, and the action name
    is unchanged so its `theming_enabled` ACL entry still covers it — but this
    was reasoned, not requested. The dev instance's session had expired and a
    fresh one needs a login.

### 11.6 Exit criterion

`45.155.205.233`'s History tab lands on a bounded window rather than one
section per occurrence — 17 rather than 197, and §11.5.3 records why the 748 in
this phase's opening was never a number `HEAD` rendered — states how many
occurrences it left out and why — never touched, or
not touched in this period — and lets the reader reach any period by brushing.
A value whose log went quiet says so on landing instead of looking empty. The
three existing values render as they did.

### 11.7 Deferred

- **Payload on explicit request**, per decision 9. `show all time` on the
  197-section value measures **2.24 MB raw, 76 KB gzipped** — not the ~600 KB
  estimated here before the fixture had rows, for §11.5.3's reason. This joins
  §10.6's entry: the Occurrences, Timeline and Sightings panels each ship every
  row they will ever page through, and whichever phase makes the page fetch
  pages settles all of them.
- **The two undemoed states**, per §11.3. Displayed once and written down, not
  garrisoned.
- **`recorded === false` has no demo and is the default-instance rendering.**
  Recorded as a gap this phase inherited rather than created.

