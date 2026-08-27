# Value Profile — zooming the activity chart

**Phase 21.** Extracted from [`value-profile-page.md`](../value-profile-page.md), where this
was §13. **The section numbering is deliberately kept:** the corpus carries
over a hundred references of the form `§13.x`, and they resolve to the
headings below rather than to anything in the main document.

---

## 13. Phase 21 — zooming the activity chart

The follow-up phase 20's parameter makes possible: when a month holds a thousand
entries, being able to look inside that month. Blocked on phase 20, because
zooming without a shared primitive means writing it three times.

### 13.1 The measurement, taken

The instinct is that zooming needs a fetch per zoom level, because §11.2 went to
some trouble to stop shipping what it will not show. That reasoning does not
transfer, and the difference is worth stating plainly: **sections are markup and
bars are numbers.** 748 sections cost 2.4 MB at ~3.2 KB each. A bar is a label
and a count.

So this section asked for a measurement before any zoom behaviour was designed:
render `45.155.205.233`'s chart at daily grain over its whole span and record
the bytes. Taken, by flipping `AUDIT_UNIT` the way §12.5.5 did — the span is
437 days, not the 438 this section guessed:

| grain | buckets | bucket shape as it ships | counts only |
|---|---|---|---|
| month — ships today | 15 | 1.5 KB | 92 B |
| week | 63 | 7.5 KB | 172 B |
| **day** | **437** | **45.8 KB** | **919 B** |

Against a History panel of 217.5 KB, the finest grain in the shape the bucket
already has is +21%, and as counts alone it is +0.4%. The hypothesis holds with
room to spare: **the chart can hold every bucket over the value's whole span
and re-aggregate in the browser, and this phase is client-side arithmetic.**

Three things the measurement found that this section had not predicted.

**Dense beats sparse — on this caller, and not on the other.** A sparse map of
only the days that carry an entry is the obvious saving, and on the History
chart it is a false one: non-zero days run from 1% (`8.8.8.8`, 6 entries) to 82%
(`45.155.205.233`, 623 entries) across the four values, so sparse wins on the
quiet values by tens of bytes and loses on the busy one, 2,810 B against 919 B.
Dense is bounded at about a kilobyte and needs no decode.

The Sightings navigator turned out to be the opposite regime, which this section
also failed to predict, and §13.4 records it: twenty-three organisations over
fourteen months with a few hundred reports between them is twenty-three parallel
series that are each nearly all zero. So the encoding is a caller's choice and
a statement about what its series *is*, not a rule this phase makes once.

**The claim that Sightings would get smaller was wrong.** An earlier draft of
this section, written after the measurement and before the work, said the tab
that looked most expensive to convert was the one that would shrink: three
precomputed ranges cost 39.8 KB, and one daily series with all 23 organisations
is 21.6 KB as parallel arrays of counts. Both numbers are right and the
conclusion did not follow — it compared `sighting_series` against a lean
estimate and forgot that the plan ships labels too. Measured on the rendered
panel, the sighting chart goes 35.4 KB → 36.6 KB, **+3.6%**. Cheap, not free.

**The fixture has no month holding a thousand entries.** The busiest value
carries 623 audit entries over 437 days: its busiest month holds 48 and its
busiest *day* holds 6. The Timeline is denser — 2,018 dated entries over 12
months, ~168 a month. So the exit criterion is demonstrable as a mechanism and
the crowding it relieves is not in this data, which is a limit on the
verification rather than on the phase.

### 13.2 Zoom and adaptive bucketing are one mechanism

If the chart holds the finest grain and derives the drawn unit from the visible
span, then "choose the unit" and "zoom in" are the same feature seen twice: zoom
narrows the visible span, and the span picks the unit. `sightingRange`'s
`$days <= 90 ? 1 : 7` is that rule with two steps and no zoom to drive it.

So phase 21 does not add a second mechanism to phase 20's. It gives phase 20's
span-to-unit option a gesture, and §12.2's constraint carries over: a caller
whose data cannot honestly claim a finer grain — the Timeline, per §8 — declines
the option and stays monthly however far it is zoomed.

**What the measurement forced here, which the paragraph above gets wrong.** One
rule cannot be shared. `unitForSpan`'s shipped rule maps 437 days to `week`, and
History's whole span is 437 days drawn at `month`; adopting the shared rule
would turn its landing chart from 15 monthly bars into 63 weekly ones as a side
effect of a phase whose reader-visible change is supposed to be a gesture. So
**the span-to-unit rule is per caller**, chosen so each tab's landing state is
the one it ships today and the ladder only opens as the reader zooms in:

| caller | finest | rule | at landing |
|---|---|---|---|
| History | day | ≤45d day, ≤200d week, else month | 437d → month, unchanged |
| Sightings | day | ≤90d day, else week — shipped | 90d → day, unchanged |
| Timeline | month | declines the gesture, below | 12 monthly bins, unchanged |

**And the Timeline declines the gesture, not merely the finer grain.** Writing
the other two made the reason visible, and it is not the one this section
started from. The argument above is about what a Timeline *bar* may claim, and
it is sound; what settles the question is what its *spine* already is. That
spine is twelve monthly bins ending at the window's end, not the value's whole
history — entries older than the first bin are counted under it rather than
drawn (§8). Zoom exists to open a span too wide to read, and twelve bars is not
one. With a monthly floor the whole gesture is 12 bars → 6 → dead, and a control
that can be pressed once before greying out is worse than no control. So the
Timeline gets the shared brush it got in phase 20 and no zoom, and §13.6 counts
that as met rather than deferred.

### 13.3 The decision this phase must not fudge, settled

**Brushing and zooming are two gestures on one chart, and phase 19 already spent
the drag.** There, dragging *selects a period to filter by*; zoom *changes what
the chart shows*. Conflating them would mean a reader who wants to look closer
at March cannot avoid also filtering the tab to March, and a reader who wants to
filter to March cannot avoid losing sight of the rest of the year.

The measurement settles two of the three options without an interview.

**Drag-to-zoom with a separate period control is not just an undoing of §11.2
decision 5 — it is strictly worse, and for a reason that section did not see.**
Sightings and the Timeline have no date inputs. Brushing is the *only* range
control either of them has, so reassigning the drag to zoom would mean inventing
a period control for two tabs that have never needed one — and this section's
own rule is that whichever option wins applies to all three callers at once. The
cost is two new controls, to buy a gesture the other option gets from four
buttons.

**A modifier on the drag** stays dismissed on the grounds already given.

So **the drag keeps selecting, and zoom gets a control of its own.** Which
control was the remaining question, and the measurement answers it too, because
what it establishes is that zoom is free arithmetic over data already on the
page. That rules out the two gestures the original list offered:

- **The wheel** would trap the page. History's chart is a 64px strip in a
  three-column rail inside a long scrolling page, and capturing `wheel` there
  stops the page under the reader's pointer. Phase 20 added `touch-action: none`
  for the drag; a wheel zoom would need more of the same to no better end.
- **Double-click** collides with the click phase 20 deliberately made the shared
  rule on all three brushes: a double-click's first click clears the brush. It
  also offers no way back out.

**Four buttons on the visible span.** `−` and `+` halve and double it around its
centre, `◀` and `▶` step it sideways by half a window, and the drawn unit
follows from the span by that caller's rule. It reaches any month in the log,
which is what the exit criterion asks for and what an end-anchored window
select — the cheap option, generalising Sightings' shipped `90 / 365 / all` —
cannot do: no sequence of *last N days* windows reaches March 2024.

**Zoom snaps to bucket boundaries; a named span does not.** Halving a span and
landing its edges mid-month would draw a bar labelled `Mar` that is not March,
which is the thing §12.4 already refused for the low end of a series. So a zoom
step snaps its span outwards to whole buckets of the drawn unit, and its pan
step is a whole number of buckets.

The distinction the work forced, which this section had flattened: **a named
span is taken exactly.** `last 365 days` has to cover 365 days, so its first
weekly bar is clipped to whatever part of that week falls inside — which is what
`stepSpansFromEnd` did when the server built those ranges itself. Snapping it
out instead moved that bar six days and changed the range's own row count from
346 to 352, which §13.5.3 caught. A zoom step has no span to honour and so
snaps; a preset, and the reader's own selection, are honoured and so do not.

**Zoom-to-selection is offered as well, and that is not the conflation this
section forbids.** The harm named above is that a reader who wants to look
closer *cannot avoid* filtering; the four buttons are that independent path.
Once it exists, letting a reader who has already brushed March jump straight to
it costs nothing and forces nothing.

### 13.4 What ships

**A plan, on the server.** `ValueProfileBuckets::plan($from, $to, $rule,
$anchor)` returns one bucket series per unit the caller's rule permits, over one
span. The split it draws is the one that keeps the phase honest: **this class
ships every string and the browser computes every number.** A grain is two
arrays of labels and one array of start offsets, not a list of bucket objects,
because `j M` and `F Y` are decisions — duplicating them in JavaScript would be
a second formatter to keep in step with `describe()` — while a bucket's start
and end are arithmetic over the span. A bucket ends where the next begins and
the last ends with the span, so the browser derives every boundary and every
date. Sent as bucket objects the same payload cost 45.8 KB for a 437-day span
against the 14 KB it costs this way, most of the difference field names and
`Y-m-d` dates repeated five times a bar.

`starts` is `null` where the offsets are the identity, which `day` always is.
The month grain's first offset is normally negative, because a month bucket is
a whole calendar month at the low end — a bar labelled `Jun` that begins on
the 14th is not June — and the browser clips it into the span.

**Two encodings for a tally, and the choice is the caller's.**
`tally()` returns one count per day of the span; `sparse()` returns only the
offsets that carry something. §13.1 measured both regimes and neither wins
generally: History's single series counting every audit entry has something on
82% of its days on the busiest value, and a sparse map of it costs three times
what a dense array does; the Sightings navigator's twenty-three
per-organisation series are each nearly all zero, and dense cost 21 KB of that
payload against 3 KB sparse. So the caller picks, and what it is picking on is a
statement about what its series *is*. The browser inflates a sparse tally to
dense once when the panel lands, so only the wire format differs.

**A zoom, in the browser.** `window.VP.zoom` is `make(plan, selection)`,
`wire(root, zoom, changed)` and `paint(root, zoom, labels, note)`. The state
is a visible span in day offsets and the unit it is drawn at; the unit is
stored rather than re-derived on read, because every transition snaps to the
unit the *requested* span asked for and re-deriving from the snapped span
could pick a different one and never settle. `window()` hands back the drawn
bars — each with the server's label and title, the dates this side worked out,
and its index in the grain, which is what a caller whose data is parallel
arrays needs. `spanText()` names the two ends off the daily grain's titles
rather than off the first and last drawn buckets': a weekly bucket's own title
is a range, and a caption built from two of those reads as four dates with
three dashes.

`selection` is a callback, for the reason phase 20 made the brush's bucket count
one: the answer changes under a control that is wired once. It is also what
keeps the zoom from knowing what a selection is on a given tab — it asks for two
dates. A caller that passes none is offered no such step.

**One control in the markup and one in the stylesheet.**
`Values/View/value_zoom` is five steps and a caption, and a sixth where the
caller gave it a selection to read. The caption says three things and every one
of them is a string the caller or the server wrote: the span, labelled
`showing`, because a tab may state more than one span and two unlabelled date
pairs a line apart read as one fact in two notations; what one bar is worth,
keyed by unit, because §12.2's argument survives the zoom; and a note for when
the selection has gone off screen. What the callers vary is whether the caption
fits beside the buttons, which is `--vp-zoom-where-basis` — 100% in History's
three-column rail, `auto` on the full-width navigator. Rendered hidden, for the
reason the brush is: without the script the buttons frame a chart they cannot
move.

**Two callers converted, and one that declines.**

- **History** replaces `AUDIT_UNIT` with `AUDIT_RULE`, and its payload's
  `months` with a plan and one tally a day. Everything on the tab that read a
  fixed array of months reads the drawn bars instead, because after §13.2 there
  is no fixed array. `auditWhole()` is the log's own bounds and stays what the
  period control offers: zooming changes what the reader can see, and a filter
  that silently narrowed to it would make the two gestures one after all.
- **Sightings** replaces three precomputed ranges with one series over the
  value's whole life, and the select that swapped them with presets that set the
  span. The whole conversion sits behind `sightRange()` — which the chart, the
  navigator, the legend and the list already read — and which now derives its
  answer rather than looking one up. Counts sum over the days a bar covers; the
  decay curves are sampled at each bar's last day, because a count is additive
  and a score is the value as of a date. Reset moves the select onto the preset
  that means the same thing rather than leaving it naming a span the chart is no
  longer drawing.
- **The Timeline** keeps phase 20's brush and gets no zoom, per §13.2.

**Four things the work found, three of them bugs in it before it shipped.**

1. **A leaked observer per press.** `VP.chart.boot` hands back a refresh rather
   than the chart, and it is the right thing to call: it rebuilds from the same
   builder, destroys the instance it replaces, and leaves the theme observer
   watching. Booting again per zoom step stacked a `MutationObserver` on the
   canvas each time.
2. **A window that lied.** Phase 19 painted a period covering no bar by
   collapsing the brush onto the nearer edge, on the grounds that the reader has
   to see where they are. That was a once-in-a-while state when the chart always
   showed the whole log and is now one press away, where a one-bar window pinned
   to an edge reads as a selection at that edge. `paint` takes a null bounds and
   dims the whole strip instead — which is truthful and also invisible, because
   a uniform dim has nothing to contrast against, so the caption says it in
   words.
3. **A caption that truncated the span it exists to name.** A flex line shrinks
   its flexible items before it wraps, so three captions in a three-column rail
   cut `14 Jan 2025 – 17 Feb 2025` down to `14 Jan 2025 – 1…`. Nothing in the
   caption shrinks now and the line wraps instead.
4. **The named-span rule**, per §13.3 — found by the byte-for-byte comparison
   against the ranges it replaced, not by reading the code.

### 13.5 Verification

Two of this phase's claims are negative — *the landing state is what it was* on
both converted callers — and one is positive, so the checks are built to find a
change and then to drive the gesture that is new.

1. `php -l` over `ValueProfileBuckets.php`, `ValueProfileFixture.php` and the
   three templates, `node --check` over `value-profile.js`. Clean, and no line
   this phase adds is over 80 columns.

2. **Every panel of every value rendered before and after and diffed byte for
   byte**, on phase 20's harness. 17 of 115 rendered panels differ, and every
   one of them is a panel this phase touches or a known harness artefact:

   | panel | before | after | delta |
   |---|---|---|---|
   | `45.155.205.233` History | 222,753 | 237,043 | **+6.4%** |
   | `185.234.219.24` History | 83,025 | 94,792 | +14.2% |
   | `104.21.34.198` History | 67,639 | 75,477 | +11.6% |
   | `8.8.8.8` History | 11,626 | 19,244 | +65.5% |
   | `45.155.205.233` Sightings chart | 35,373 | 36,633 | **+3.6%** |
   | `185.234.219.24` Sightings chart | 15,855 | 22,336 | +40.9% |
   | five values' Verdict | — | — | timestamp only |

   The proportions are largest on the smallest panels and the absolute cost is
   the daily grain's labels over a value's whole life, which is the trade §13.4
   describes. The Verdict panels' single differing line is `Computed at render,
   <timestamp>`, which differs between two runs of the *same* tree. Nothing else
   moved — in particular the Sightings *list* fragment is byte-identical, so no
   row moved on the tab whose payload changed most.

3. **The re-aggregation, checked against what the server used to send.** This is
   the claim everything else rests on: the browser is handed counts and grains
   and has to reproduce bars the server used to compute.

   - **History: 1,575 checks, zero failures.** Every monthly bar of every value
     reproduced — label, title and total — by summing the daily tally over the
     days that bar covers. Plus that each grain tiles its span contiguously and
     ends exactly at it, that every grain sums to the same log, and that the
     tally has one slot per day and holds every entry.
   - **Sightings: 8,619 checks, zero failures.** Every one of the three
     precomputed ranges reproduced bar for bar from the plan and the daily
     tallies: boundaries, labels, all 23 per-organisation stacks, false
     positives, expirations, the decay curve samples, and the `in_range` count
     the list caption states. This is the check that caught §13.3's named-span
     rule: with a preset snapped rather than clipped, the `365` range's first
     bar moved from `2024-08-25..2024-08-25` to `2024-08-19..2024-08-25` and its
     row count from 346 to 352 — seven failures, all of them that one bar.

4. **502 assertions in headless Chrome over 12 pages**, light and dark, zero
   failures — two values on each of the three tabs. Every page's first assertion
   is that the stylesheet resolved, per §6.1.

   What they establish beyond *it still works*:

   - **The landing state is untouched.** History lands on the same 15 monthly
     bars summing to the same 623 entries; every Sightings preset draws the bars
     its range drew — 90 daily, 53 weekly, 63 weekly — at the grain its span
     asks for.
   - **A month can be opened.** Seven presses take History's chart down the
     ladder `15 monthly → 8 monthly → 18 weekly → 10 weekly → 35 daily → 18 →
     9 → 5`, and the Sightings navigator from 63 weekly bars to 4 daily ones.
     The decay curves keep exactly one sample per bar at every step.
   - **Any month, not a recent one.** 77 presses of `◀` at daily grain reach the
     log's first day, still at daily grain, and `◀` then reports itself dead.
   - **The two gestures compose.** A three-bar drag at daily grain is a
     three-day period, where the finest this chart could offer was a month; on
     Sightings a brush over the zoomed navigator filters the list from 418 rows
     to 106, and to exactly what the brushed bars hold.
   - **Zoom leaves the period alone**, which is §13.3's whole argument: zooming
     out, panning and zooming back in all leave the two date inputs byte-equal.
   - **The selection step behaves.** Dead unfiltered, live once a period is set,
     lands on exactly that period — `15 Jun 2024 → 17 Jun 2024` for a three-day
     filter — leaves the period untouched, and disables itself afterwards.
   - **A selection out of view says so.** The strip is fully dimmed and no
     window is drawn, and the caption reads `the period is not in view` —
     and all three clear the moment a range inside the view is brushed,
     which is the negative
     half that stops the assertion passing on a mark that is stuck on.
   - **The brush's geometry survives a second bucket count.** The undimmed strip
     still sits under the window, to within 2px, on a chart the zoom has changed
     the bar count of — phase 20's §12.5.3 assertion re-run at a count that only
     exists now.
   - **The Timeline is unchanged**, verified by re-running *phase 20's own
     probe* against it: 64 assertions over four pages, zero failures.

5. **The three brushes and both zooms, looked at.** Screenshots in both themes
   of History landed, zoomed, brushed and looked-inside, and of the Sightings
   navigator landed and zoomed. The zoomed History rail reads `showing 14 Jan
   2025 → 17 Feb 2025 · one bar a day` over 35 bars; the zoomed navigator reads
   `showing 18 Dec 2024 → 15 Feb 2025 · one column per day` over 60, with the
   decay curve drawn through them and the legend counts rescoped to the span.

### 13.6 Exit criterion

Met. A month can be opened to show its distribution within that month, on both
callers whose data supports the finer grain, and the gesture that does it is
four buttons rather than the drag that filters — which is unchanged, on all
three brushes. The Timeline declines, and §13.2 records that as a property of
its twelve-bin spine rather than as work left over.

The criterion's own example is not demonstrable on this fixture, per §13.1: no
month in it holds a thousand entries, and the busiest holds 48.

### 13.7 Deferred

- **The pan step is small at the finest grain.** Crossing fourteen months at
  five daily bars takes 77 presses. The reader who wants to go far zooms out,
  pans and zooms back in — three or four presses — so this is a shape of the
  arithmetic rather than a hole in it, but nothing in the control says so.
- **`ZOOM_MIN_BUCKETS` is four**, which lets the reader zoom to five daily bars.
  That is deeper than the criterion asks for and harmless; it has not been
  argued about, only chosen.
- **The zoom is not stated in the URL or remembered.** A re-fetched History
  panel lands on its whole span again, which is right for a period the server
  applied and arbitrary for a zoom the reader set.
- **The Sightings preset select and the zoom caption can disagree.** Zooming to
  a span that is not one of the presets leaves the select naming the last one
  chosen while the caption names what is drawn. The caption is emphasised and
  labelled `showing` for exactly this reason, and an exact match syncs the
  select, but a reader who reads only the select can be misled.
- **`ValueProfileBuckets::START` is still exercised only by a flip**, inherited
  from §12.7: both converted callers want calendar months or an end anchor.
- **The History re-fetch is still verified to the URL, not round-tripped**, per
  §12.7. Nothing this phase changes touches that path — the zoom never fetches.

