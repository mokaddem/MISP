# Value Profile — one brush, three callers

**Phase 20.** Extracted from [`value-profile-page.md`](../value-profile-page.md), where this
was §12. **The section numbering is deliberately kept:** the corpus carries
over a hundred references of the form `§12.x`, and they resolve to the
headings below rather than to anything in the main document.

---

## 12. Phase 20 — one brush, three callers

No new behaviour *for the reader*, and **blocked on phase 19**: it exists to
collapse three implementations of one gesture into one, and the third is what
phase 19 writes.

Not "no new code", though, and an earlier draft of this section was wrong to
imply it. The three callers bucket their bars by three different rules, so a
primitive that cannot be told its bucket unit cannot serve them — the parameter
is a requirement of the extraction, not an extra.

### 12.1 What is duplicated

Line numbers are as this section was written, which is before the phase
that removes them.

| Caller | Where | What it drives | Bucket |
|---|---|---|---|
| Sightings | `value-profile.js:1038` (`sight` state, `sight.brush`) | hides table rows | **adaptive**, day ≤ 90d else week |
| Timeline | `value-profile.js:2473`, `:3337` (`[data-vp-tl-brush]`) | two regions from one spine | month, twelve bins |
| History | `value-profile.js:2548` (`audit` state), `:2826` | writes the period inputs | month, whole span |

Three renderings of the same gesture — drag a range over an activity chart —
with three sets of pointer handling, three clamping rules and three ways of
saying where the brush currently sits.

**Two things writing the third one found, which the extraction has to
settle rather than preserve.** `.vp-tl-mask` and `.vp-sight-brush`'s masks are
flex items with the window absolute between them, so the pair packs from the
left and leaves the undimmed strip at the far right instead of under the window
— invisible on a brush sitting at the end of its chart, which is where both of
those land by default, and wrong everywhere else. History's mask carries the
one-line fix (`margin-left: auto`) and the other two do not, because §11.2
decision 10 keeps the shipped brushes untouched. And the click rule differs:
those two read a click off *which bucket the pointer came up on*, which makes a
one-bucket range unselectable; History reads it off whether the pointer
travelled. On a monthly chart the first rule means two months is the finest
period a reader can pick, so the primitive should take History's rule.

### 12.2 The bucket unit is a parameter

`sightingRange` (`ValueProfileFixture.php:4148-4180`) already switches unit by
range, with the reasoning in its own comment — *"than the chart has pixels; past
a quarter the bucket is a week"*:

```php
$step = ($days !== null && $days <= 90) ? 1 : 7;
```

That is the right instinct hardcoded in one caller. The extracted primitive takes
the unit — `day`, `week`, `month` — and optionally a rule mapping visible span
to unit, so Sightings' day/week switch becomes configuration rather than an `if`,
and Timeline and History pass `month` outright.

The reason the unit has to be settable and not merely derived: the three callers
disagree about what a bar *means*. A Sightings bar is a count of reports, and a
day is the honest grain because a report has a timestamp. A Timeline bar is a
density over sources that cannot all be dated to the day (§8's whole hatching
argument), so a monthly bar is the finest claim it can make. Deriving the unit
from the span alone would let the Timeline draw daily bars it has no right to.

So: unit is given, and the span-to-unit rule is an *option* a caller may use
where its data supports it.

### 12.3 Why after, not before

The extraction is only obvious with three cases in front of you. With two, the
shared shape is a guess; the third caller is what shows which parts vary — and
phase 19's is the one that differs most, because it writes its result into
existing form inputs rather than filtering directly.

### 12.4 What ships

**A bucket series, on the server.** `ValueProfileBuckets`
(`app/Lib/Tools/`) turns a span and a unit into buckets:
`series($from, $to, $unit, $anchor)` over `day`, `week` and `month`,
`unitForSpan($days, $rule)` for the caller whose data lets it choose, and
`locate($buckets)` for the tally that follows. Each caller keeps its unit
where a reader of that caller will find it — `AUDIT_UNIT` in
`ValueProfileFixture` for History, `$spineUnit` in `value_timeline.ctp`
for the Timeline, and `unitForSpan()` in `sightingRange()` for Sightings,
which is the one that opts in to choosing rather than naming.

**A second parameter the data forced, which §12.2 did not anticipate.**
The unit alone is not enough, because `week` also has to know which end
of the span it is aligned to. Sightings' ranges are *last 90 days* and
*last 365 days*, so their last bucket has to end today or the bar the
reader looks at first is a partial week; the History chart runs from the
log's first day, so its first bucket has to start there. The anchor was
built in rather than discovered, so nothing here is a caught bug — but
laying every series forwards would have moved every weekly bar on the
Sightings chart, and §12.5.2's byte diff is the check that would have
said so. `month` ignores the anchor — a
month bucket starting mid-month would be a bar labelled `Mar` that is not
March — and `day` has no two ends to disagree about.

**A brush, in the browser.** `window.VP.brush` is `attach(strip, on)`
and `paint(root, bounds, count)`. `on.count()` is a callback and not a
captured number, because the Sightings range select swaps 63 weekly
buckets for 90 daily ones underneath a live brush. `on.range(from, to)`
is what a range means to that tab, `on.clear()` is the click, and
`on.settle()` is an optional once-on-release hook — the History
re-fetch, which cannot run per `pointermove` without being a request
every few pixels.

**One brush in the markup and one in the stylesheet.** Three sets of
classes and data attributes become `.vp-brush`, `.vp-brush-mask` and
`.vp-brush-window`. What the three actually varied becomes two custom
properties with defaults: `--vp-brush-floor`, the 22px of month labels
the Timeline's spine must not cover, and `--vp-brush-dim`, History's 80%
against the other two's 62%. A fourth caller needs no CSS of its own.

**Three differences settled rather than preserved.**

1. **The mask flex bug**, per §12.1. `margin-left: auto` on the right
   mask is in the shared rule now, so the Sightings and Timeline brushes
   stop leaving the undimmed strip at the far right instead of under the
   window.
2. **The click rule.** A click is a pointer that travelled under 4px, on
   all three. The *which bucket did it come up on* rule is gone, and a
   one-bucket range is selectable everywhere.
3. **`touch-action: none`**, which only Sightings had. Not named in
   §12.1, because it was not visible until the three declarations were
   side by side — which is the argument for the extraction restated.
   Without it a drag on the other two brushes also scrolls the page.

**One payload rename.** `step: 1|7` becomes `unit: 'day'|'week'`, and
the pair of caption strings the browser used to choose between with
`range.step === 1` becomes `labels.perUnit`, keyed by unit. That is
§12.2's *configuration rather than an `if`* in the one place a reader
sees it.

### 12.5 Verification

The claim this phase has to support is a negative one — *nothing
changed* — so the checks are built to find a change rather than to
demonstrate the refactor.

1. `php -l` over `ValueProfileBuckets.php`, `ValueProfileFixture.php`
   and the three templates, `node --check` over `value-profile.js`.
   Clean, and no line this phase adds is over 80 columns.

2. **Every panel of every value, rendered before and after and diffed
   byte for byte.** 23 panels × 5 values plus the raw profile, 120 files
   a pass. Phase 19's `allpanels.php` could not render two of the panels
   this phase touches, so the harness gained the real `TextColour`
   helper — without which the Timeline throws on its tag chips — and the
   four lazily loaded sighting sub-panels, which is where the navigator
   and its payload actually live. The 21 failures left are the harness's
   own gaps (`mbstring`, CakePHP's `DistributionLevel` and `Paginator`)
   and all sit in panels this phase does not touch.

   With the three server-side builders rewired and before the markup
   changed, **every rendered panel was byte-identical** except the
   Sightings payload's renamed keys. Every bucket boundary, label,
   count and decay curve was character-for-character what it had been,
   including the two cases most likely to move: the clipped first weekly
   bucket (`2024-11-02` → `2024-11-03`) and the `today` label on the
   last one.

   With the markup collapsed as well, 17 of 115 rendered panels differ:

   | panel | lines | changed | size |
   |---|---|---|---|
   | `45.155.205.233` Timeline | 46,778 | **4** | 3.4 MB |
   | `45.155.205.233` History | 4,439 | **8** | 218 KB |
   | `45.155.205.233` Sightings chart | 233 | **5** | 34 KB |
   | five values' Verdict | — | 1 | — |

   The four Timeline lines and the eight History ones are the brush's
   three elements renamed. The five Sightings lines are those plus the
   payload. The Verdict panels' single line is `Computed at render,
   <timestamp>`, which differs between two runs of the *same* tree and
   is the harness's own noise.

3. **196 assertions in headless Chrome over 12 pages**, light and dark,
   zero failures — six panel-and-value combinations across the three
   tabs. Every page's first assertion is that the stylesheet resolved,
   per §6.1: the geometry assertions are worthless if it did not. The
   drag is synthetic `PointerEvent`s with `setPointerCapture` stubbed,
   because capture is a browser behaviour and what is under test is the
   arithmetic above it.

   What they establish beyond *it still works*:

   - **The undimmed strip sits under the window on all three.** The
     left mask's right edge meets the window's left edge and the right
     mask's left edge meets its right, to within 2px, on a range
     deliberately placed mid-chart where the old bug showed.
   - **The dim is the theme's own background at the right strength.**
     `color(srgb 1 1 1 / 0.62)` in light and
     `color(srgb 0.129 0.145 0.161 / 0.62)` in dark on Sightings and
     Timeline, `/ 0.8` on History — so `--vp-brush-dim` is doing what
     the two deleted rules did.
   - **A one-bucket range is selectable.** Six pixels inside one bucket
     selects that bucket on all three; zero pixels and two pixels both
     clear. Under the old rule the first of those three was also a
     clear.
   - **The count really is a callback.** Switching the Sightings range
     select takes the chart from 63 weekly buckets to 90 daily ones,
     clears the brush, and a drag over the new chart lands on the new
     buckets — without the brush being rewired.
   - **`settle` fires once and asks for the right thing.** Brushing the
     History chart past the fetched window produces
     `…/viewHistory/<b64>/2024-06-01/2024-06-30` exactly once, on
     release.
   - **The Timeline's floor holds.** The brush stops 22px above the
     spine's bottom, so it never covers the month labels.

4. **A negative control for the geometry assertion.** An assertion that
   cannot fail measures nothing, so the same three pages were re-run
   with `margin-left: auto` overridden back to `0`. All three fail, and
   by the distance the bug is worth: the right mask starts 455px left of
   the window's end on the Timeline, 368px on Sightings, 20px on
   History.

5. **§12.4's own check — the unit is a parameter.** Changing
   `AUDIT_UNIT` from `ValueProfileBuckets::MONTH` to `::DAY` is the
   one-argument change it was supposed to be. History's chart becomes
   **437 daily buckets from 2024-06-14 to 2025-08-24** instead of 15
   monthly ones, the tab renders, and the brush still writes the date
   inputs and still re-fetches past the window — 42 assertions, zero
   failures. One assertion is skipped rather than failed, and the reason
   is phase 21's whole subject: at daily grain in a three-column rail a
   bar is **0.68px**, so a drag the gesture is willing to call a drag
   necessarily crosses about ten of them. The flip was reverted.

6. **The three brushes, looked at.** Screenshots in both themes with a
   range brushed mid-chart. The Sightings navigator reads
   `2024-11-04 → 2025-02-23` with its list at *107 in the selected
   range · 418 in total*; the Timeline's Jan–Mar sit undimmed between
   two primary rules with the axis labels clear of the mask; History's
   three bars likewise inside a 64px rail chart.

### 12.6 Exit criterion

Met. One brush primitive, three callers, each naming its own bucket
unit, and the Sightings, Timeline and History tabs re-verified in both
themes. The one behavioural change any of the three tabs' own steps can
detect is the one §12.1 asked for: a one-bucket range is now selectable
on the two brushes where it was not. Switching History's unit from
`month` to `day` is one argument and the tab still renders — §12.5.5.

### 12.7 Deferred

- **The chart still holds one grain, chosen on the server.** Nothing
  here lets the browser re-aggregate, which is §13's subject and why
  §13.1 wants a measurement before a design.
- **`ValueProfileBuckets::START` is exercised only by the flip.** All
  three shipped callers want calendar months or an end anchor, so the
  forward-laid weekly series has no caller yet. It is not speculative —
  it is what `month` and `day` already take, and what the flip in
  §12.5.5 ran through — but it is not covered by a shipped tab.
- **The History re-fetch is verified to the URL, not round-tripped.**
  Phase 19 §11.5.4 round-tripped it against parked fragments; what this
  phase changes about that path is that the check now runs from a
  `settle` callback, and that is what §12.5.3 checks. No route, action
  or ACL surface changed, so §11.5.10's unverified live endpoint is
  inherited rather than extended.

