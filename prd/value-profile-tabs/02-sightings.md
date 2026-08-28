# PRD: Value Profile — Sightings tab

**Phase 10.** Implements candidate **`S1`**, chosen 2026-08-25.
Artifact: <https://claude.ai/code/artifact/715b4822-2644-409e-beb2-3ac09474c4d2>
Depends on `00-shared.md`.

## 1. What ships

**One overlay and one brush: the curve is drawn through the bars that move it,
and the table underneath shows exactly the stretch you dragged.** MISP has
computed a decay curve per attribute for years and has never had anywhere to
draw it against the sightings that move it. That overlay is the tab. A design
that puts the curve in a card of its own has lost the argument before it starts,
which is why `S1` keeps the panel-plus-rail grammar the Overview and Verdict
tabs already speak and spends the width on one chart instead.

**Not taken.** Per the 2026-08-25 decision to ship bare picks, two grafts were
deferred. Both are now settled — one built, one declined — and §11 carries the
reasoning:

- `S4`'s gap rows (*"8 days with no sighting · NIDS 80 → 55 · crossed below 60
  on 2025-08-07"*). The phase 7 review called this the best single idea in the
  set, and it is a loop over the sighting list. It is the first thing to add
  after the tab renders.
  **Declined on 2026-08-28.** It would put a derived claim about a score into a
  table of reports people filed, and the chart already answers the question it
  asks.
- `S3`'s baseline split, drawing false positives *below* the axis rather than
  stacked on top so a contradiction can never read as an addition. Until then
  the false-positive series is a distinct hue in the stack and the panel says in
  words that it moves no score.
  **Built.** Contradictions and expirations hang below the axis at every grain
  and in both themes, and colour was freed to carry identity alone.

## 2. Layout

`col-lg-9` + `col-lg-3`, the page's usual split. Left: the chart panel, then the
individual-sightings panel. Right: three rail cards.

## 3. Controller

| Action | URL | Renders |
|---|---|---|
| `viewSightingChart($b64value)` | ajax | `value_sighting_chart` |
| `viewSightingList($b64value)` | ajax | `value_sighting_list` |
| `viewSightingDecay($b64value)` | ajax | `value_sighting_decay` |
| `viewSightingReporters($b64value)` | ajax | `value_sighting_reporters` |
| `viewSightingAdd($b64value)` | ajax | `value_sighting_add` |

`viewSightings` stays the Overview's rail card and this phase does not touch it.
It was converted to the database afterwards, on 2026-08-28, precisely because it
is built out of this tab's `sightingContext` — see
[`../value-profile-live/23-sightings.md`](../value-profile-live/23-sightings.md)
§12.1.

## 4. Templates

```
app/View/Themed/Overmind/Elements/Values/View/
    value_sighting_chart.ctp        overlay + navigator + legend
    value_sighting_list.ctp         the individual sightings table
    value_sighting_decay.ctp        rail: per-model score
    value_sighting_reporters.ctp    rail: who reports it
    value_sighting_add.ctp          rail: the disabled, value-scoped write
```

**The chart is Chart.js, not the mockup's SVG** (`00-shared.md` §7): a bar
dataset per organisation on the primary axis, a line dataset per decaying model
on a second 0–100 axis, plus two threshold annotations. The navigator strip
below it is a second, low-height canvas with a drag-selectable window; the
selection updates the list panel's range without a request. Canvas ids are
namespaced per panel.

The rail cards reuse `.vp-decay*` and `.vp-reporter*` as they stand
(`00-shared.md` §4) — both families already exist from the Overview.

## 5. Fixture additions

```php
'sightings' => [ /* total, fp, expiration, spark, reporters, last — exists */ ],

// NEW: the tab's own series and rows.
'sighting_series' => [
    'window'   => 90,                 // days, matching the default range
    'buckets'  => [                   // one entry per day in the window
        ['date' => '2025-08-13', 'by_org' => ['Org A' => 2, 'Org C' => 1],
         'fp' => 0, 'expiration' => 0],
        ...
    ],
    'curves'   => [                   // one per decaying model, same axis
        ['model' => 'NIDS Simple Decaying Model', 'threshold' => 60,
         'points' => [ /* one score per bucket */ ]],
        ['model' => 'Phishing Model', 'threshold' => 50, 'points' => [...]],
    ],
    'ranges'   => [90, 365, 'all'],   // 'all time · from 2024-09-14'
],
'sighting_rows' => [
    ['org' => 'Org C', 'source' => 'Source A', 'date' => '2025-08-13 16:31',
     'type' => 'sighting', 'against' => ['event' => 1284, 'type' => 'ip-dst']],
    ...
],
'sighting_notes' => [
    // The two sentences the tab must not omit.
    'fp_moves_nothing' => 'The false positive on 2025-08-01 leaves both curves
        flat. MISP resets the decay clock on type-0 sightings only, so a
        contradiction is visible on the axis but moves no score.',
    'policy'           => 'Sightings you can see. This instance\'s sighting
        policy hides sightings reported by other organisations on events your
        organisation does not own, so this count is yours, not the instance\'s.',
],
```

All four demo values supply these. `8.8.8.8` is the sparse case — 17 sightings
over a year — and is the reason the range control exists: seventeen sightings in
a 90-day window is a nearly empty chart, and the design must be judged on it.

## 6. The chart panel — `value_sighting_chart`

Header: sighting glyph, title **Sightings over time**, sub-line
`47 sightings · 46 sightings, 1 false positive, 0 expirations · last one 2 days
ago`.

Controls on the header row:

- Three type toggles, each carrying its count: `Sightings 46`,
  `False positives 1`, `Expirations 0`. A type with a zero count is rendered
  disabled with a `title` explaining why — *"No expiration sighting has been
  reported for this value"* — never hidden, because absence is information.
- A range select: `Last 90 days`, `Last 365 days`, `All time · from 2024-09-14`.

Body, in order:

1. Two `.vp-subhead` captions naming both axes — `Sightings per day, stacked by
   organisation` on the left, `Decay score · 0–100` on the right. An overlay
   with two scales must label both or it is a trick.
2. The overlay canvas: org-stacked bars, one line per model, dotted threshold
   lines labelled `threshold 60` / `threshold 50`, an x-axis ending in `today`.
3. The navigator canvas, captioned *"Drag the rail to change the range — the
   table below follows it"*, with the current window printed beside it
   (`2025-07-10 → 2025-08-13`).
4. Legend: one entry per organisation with its count, then `False positive` as
   its own entry, then one entry per model with its current score.
5. The `fp_moves_nothing` note. This is the sentence that justifies the whole
   overlay: it is the one thing a curve in a separate card could never show.

## 7. The list panel — `value_sighting_list`

Header: **Individual sightings**, sub-line `20 sightings in the selected range ·
47 in total`. It carried a disabled `Export selection` beside that; the button
is **removed** — the panel has no selection to export and nothing on this page
writes, so it was an affordance for a feature that was never specified.

Below it a `.vp-filter-note` bound to the brush — `Range 2025-07-10 →
2025-08-13 · 20 of 47 sightings` — with a `.vp-filter-clear` that returns to the
full window. The note is the same primitive the Overview already uses.

Table (`.vp-table`): Organisation · Source · Date · Type · Reported against.
`Source` prints `—` where the sighting carries no source string, which is most
of them. `Type` is a badge, with the false-positive row visibly distinct.
`Reported against` names the occurrence the sighting was filed on, because a
value-scoped list otherwise loses which of ten occurrences was actually seen.

All five columns sort, clicking the heading, in the occurrence table's three
states: ascending, descending, then back to the order the model sent — newest
report first, which no column would otherwise bring back. Comparison is by the
`data-vp-sort-<column>` tokens the template stamps on each row and not by cell
text: `Event 9` sorts after `Event 10` as text, and `Type` reads as three
unrelated words where the order wanted is MISP's own. The sort is a reorder of
rows the panel already holds, so it composes with the brush rather than
replacing it — the brush still chooses which rows are candidates, and the
reorder covers every row so that widening the brush again recovers them in
order.

Two columns leave the page. `Organisation` links to `/organisations/view/<id>`,
except where the row has no organisation to name — `Plugin.Sightings_anonymise`
blanks the name and zeroes the id on a foreign report, and all of those print as
one unlinked `Others`, so the label and the link cannot disagree about who filed
it. `Reported against` links to the event's Attributes tab
(`/events/view2/<id>#tab-attributes`) rather than to the event itself, because
the column names an occurrence and that tab is the nearest the event view gets
to one — `/attributes/view` redirects to the event and loses which attribute it
was asked about. Its title carries the occurrence's own attribute id, which is
the only thing that tells two occurrences of this value in one event apart.

Footer: `Showing 10 of 20 · load the rest` (client-side), then the
`.vp-acl-note` carrying the `policy` sentence — the count in the tab title is
the viewer's, not the instance's, and this is where that is said.

## 8. The rail

**`value_sighting_decay`** — title **Decay models**, meta `2 apply`. One
`.vp-decay` block per model: name, score, a track with the score filled and the
threshold marked, and a line of provenance (*"Last reset by Org A on
2025-08-22"*). A model permanently under its own threshold carries
`.vp-decay-expired`, the `decayed` flag, and says why in words (*"Base score 35
— permanently under its own threshold for this value"*). Closing note: *"Each
line on the chart is this model's score. The bar under each name is the same
number now."* — the two representations are the same data and the card says so.

**`value_sighting_reporters`** — title **Reporters**, sub-line `4 organisations
· 47 sightings`, then `.vp-reporter` bars, one per org, ordered by count.

**`value_sighting_add`** — the write, disabled. Three buttons (`Sighting`,
`False positive`, `Expiration`) and the fan-out sentence: *"Scoped to the value.
One sighting row is written to each of the 10 occurrences you can see, across 7
events and 4 organisations."* One click becoming ten rows is exactly the kind of
thing a disabled control must state before it is ever enabled.

## 9. States

| State | What renders |
|---|---|
| Populated | as above |
| Sparse (`8.8.8.8`, 17 sightings) | the chart keeps its axes; the sawtooth is *more* legible, not less. The range control defaults to the window that contains the data, not to 90 days |
| Zero sightings | the chart card keeps its axes and carries *"Nobody has reported seeing this"*; the rail still shows a decay score, because MISP scores an un-sighted attribute from its `last_seen`. This distinction is the point: no sightings does not mean no score |
| Zero of one type | that toggle is disabled with a reason, never hidden |
| Unknown value | one `.vp-empty` per panel; the Add-sighting card still renders, disabled, so the page does not appear broken |

## 10. Interactions

Working: the three type toggles, the range select, the navigator brush (which
drives the list panel), `load the rest`, and `Clear` on the range note. All
client-side against data already in the DOM.

Disabled with a `title`: the three Add-sighting buttons. `Export selection` was
the fourth and is gone (§7).

## 11. Deferred, and what live data will hit

**`S3`'s baseline split is built** — contradictions and expirations are drawn
below the axis, at every grain and in both themes.

**`S4`'s gap rows will not be built.** Decided on 2026-08-28, and it is a
decision rather than another deferral: the row it proposed — *"8 days with no
sighting · NIDS 80 → 55 · crossed below 60 on 2025-08-07"* — puts a derived
claim about a decay score into a table whose every other row is one report
somebody filed. The two are not the same kind of thing and interleaving them
makes the list harder to read, not easier. The question it answers, *when did
this stop being trusted*, is the chart's question and the chart already draws
it: the curve crosses the threshold line where the gap row would have said so.

**From §7.9 — this tab has the hardest live-data story of the five:**

- **The decay curve is per attribute, not per value.**
  `DecayingModel::getScoreOvertime($user, $model_id, $attribute_id, $overrides)`
  takes one attribute id and derives its base score from that attribute's own
  type and numerically-tagged taxonomies. Ten occurrences can carry ten curves
  per model. There is no value-scoped endpoint and **no aggregation rule** — the
  phase 7 deck proposes *max across occurrences, labelled with the occurrence it
  came from*, and that is a decision to take before this tab goes live, not
  during.
- **The curve's axis is not the histogram's axis.** It returns hourly samples
  from the attribute's first timestamp to *last sighting + lifetime*, so it
  starts arbitrarily and runs into the future. Aligning it to a 90-day window
  means resampling in PHP.
- **Cost.** That loop is hourly, per attribute, per model — ten occurrences by
  two models is twenty loops per page load. It needs a coarser sampler or a
  cache before it is wired.
- **False positives and expirations move nothing.** `getScoreOvertime` asks
  `listSightings(..., $sightingsType = 0)`. The chart already states this; the
  live implementation must not quietly imply otherwise.
- **The count is the viewer's.** `Plugin.Sightings_policy` hides whole
  sightings; `Plugin.Sightings_anonymise` blanks name and org for foreign ones
  and files them as *Others*, which collapses the org stack to two colours.
  `MISP.Sightings_range` (365d) caps the statistics series, so `All time` needs
  its own query.
- **The write fans out.** `Sighting::saveSightings(false, [$value], ...)`
  matches
  `Attribute.value1`/`value2` and writes one row per visible attribute.

What needs *no* new query: the org-stacked histogram.
`Sighting::attributesStatistics()` already groups org × attribute × type × date
in SQL.

## 12. Verification

1. `php -l` on all five elements.
2. All five endpoints return 200 for all four demo values; every rail card
   resolves off its spinner.
3. Malicious value: bars stacked by four orgs, two curves, two labelled
   thresholds, both axis captions, the navigator, the `fp_moves_nothing` note.
4. The brush changes the list panel's range note and row count.
5. `8.8.8.8`: 17 sightings over a year read legibly; the range control lands on
   a window containing data.
6. Unknown value: *"Nobody has reported seeing this"* with axes intact, and a
   decay score still present in the rail.
7. Light and dark: every Chart.js colour comes from a theme token read at init,
   not a hardcoded hex — the mockup source contains zero hex colours and the
   template must keep that property.

## 13. Exit criterion

Artifact `S1` is recognisable in the browser: one chart carrying both the bars
and the curves, a brush that drives the table under it, and a page that states —
in words, on screen — that a false positive moves no score.

---

## 14. Verification — what was run

Against the Docker stack serving this worktree, as an authenticated user,
2026-08-25.

1. **`php -l`** over every changed and new file, `node --check` on
   `value-profile.js`. Clean. Every new file is inside 80 columns; the three
   over-length lines in the diff are all pre-existing.
2. **All five endpoints, all four demo values — twenty fetches, twenty 200s**,
   no PHP notice or warning in any body. Forty content assertions over the
   returned markup: the sub-lines, both axis captions, the navigator caption,
   the `fp_moves_nothing` note, the disabled expiration toggle and its reason,
   four organisation legend keys, both threshold labels, the policy band, the
   false-positive badge, the em-dash source, `Reported against`, and every
   panel's empty state on the unknown value.
3. **The tab driven in a real browser**, both themes, with the fragments served
   locally so the shipped CSS and JS are what runs. The chart is a live
   `Chart` instance with 9 datasets over 90 labels, three scales (`x`, `y`,
   `score`), a score axis capped at 100, and **zero unresolved `var(--…)`
   colours** — the first bar dataset resolves to `#4c78a8` in light and the
   palette inverts in dark.
4. **Every interaction §10 promises.** Toggling *False positives* takes the
   chart from 9 datasets to 8 and back, and flips `aria-pressed`; the
   expiration toggle is disabled with its reason. The range select moves
   between 90 daily buckets and 50 weekly ones, and the axis caption follows
   it from *per day* to *per week*. A drag on the navigator narrows the list
   from 47 rows to 29, prints `2025-03-17 → 2025-08-10` in both the note and
   the window label, and positions the handle at 54%/4%. `load the rest` takes
   10 rows to 29 and hides itself. `Clear` restores the full window and hides
   the note.
5. **The sparse case behaves differently, and correctly.** `8.8.8.8` opens on
   `All time · from 2024-11-02` with 43 weekly buckets and all 17 rows, not on
   90 days with 6. Switching to 90 days does drop it to 6, which is the number
   the range control exists to let the reader discover. The NIDS curve steps up
   six times over eleven false positives — the tab's whole claim, visible
   rather than asserted.
6. **The zero-sightings state**, which no demo value carries, was driven by
   temporarily emptying the benign rows: the chart keeps its canvas and its
   series, carries *"Nobody has reported seeing this"* across the axes, reads
   *Never sighted* in the sub-line, disables all three toggles — and the rail
   still prints a score, with *"Never sighted — decaying from 2024-11-02"* as
   its provenance. The fixture was restored and re-verified afterwards.
7. **No regression on the two tabs this phase touched shared code for.** The
   Verdict rail's curve chart still builds through the refactored
   `value_chart.ctp`: 2 datasets, 40 points, zero unresolved colours. The
   Occurrences facet rail still filters — 6 rows, 21 facet boxes, one facet
   narrowing to 3, `Clear all` restoring 6 and going inert.

Per `00-shared.md` §9, §12's item 7 is not a claim about a Bootstrap utility in
dark mode; it is about Chart.js colours, and it was measured as item 3 above.

## 15. Where this differs from the brief above

**The curve is computed from the rows, not drawn beside them.** §5 specified
`curves` as a literal array of points per model. They are instead derived, with
MISP's own polynomial — `base × (1 − (t / lifetime) ^ (1 / decay_rate))`, `t`
being days since the last **type-0** sighting. Three things follow that a
literal array could not have given:

- the tab's central claim is now true by construction rather than by
  assertion. A false positive is type 1, so it cannot enter `t`, so it cannot
  move the line. §6's note describes something the reader can check.
- the curve's last point *is* the number the rail card prints, so §8's closing
  sentence — *"the bar under each name is the same number now"* — is not a
  promise the fixture could quietly break.
- the Verdict tab's `NIDS decay score` line now comes from the same function.
  One quantity, two tabs, one shape. It previously had its own hand-drawn
  array, which disagreed with the rail's own numbers.

**`Last 365 days` is not offered.** §5 listed three ranges. All four demo
values are younger than 365 days, so that option would have drawn the same
chart as `All time` behind a different label. The range list is derived: 90
always, 365 only for a value older than it, all time always. A control that
changes nothing is worse than one that is absent.

**The brush opens covering the whole range** rather than on the sub-window §7
illustrates, and the `.vp-filter-note` stays hidden until a drag narrows
something — the idiom the Overview already uses. A note that always says
"showing everything" teaches the reader to stop reading it.

**The list's page size is a `load the rest`, not a pager.** §7 asked for
exactly that, so this is not a deviation, but it is worth recording that the
Occurrences table's `value_pager` primitive is deliberately *not* reused here:
47 rows in one panel do not need pages, they need a fold.

**Two corrections to data that predates this phase.** `8.8.8.8`'s five visible
occurrences carry five distinct owning organisations, while its fact strip and
`occurrence_stats` both said four — a total smaller than its own visible
subset. The Add-sighting card's fan-out sentence counts the rows rather than
trusting the stat, which is how it surfaced; both numbers are now five. And the
three hand-drawn `*Spark()` helpers were replaced by one that buckets the
sighting rows, so the Overview's sparkline and this tab cannot disagree about
how busy the last 90 days were — two of the three previously did.

**One sentence the brief did not ask for.** Three counts with three scopes are
on screen at once: the toggles count the whole value, the legend counts the
selected range, and the Reporters card counts every report an organisation
filed of any type. `CIRCL 4` and `CIRCL 10` are both correct and both visible,
so the chart panel says which is which rather than leaving the reader to work
it out.

**A categorical palette, declared in CSS.** Everything else on this page
colours by meaning. A stack of organisations cannot: the hues carry no argument
and exist only to be told apart. Six `--vp-sight-org-*` variables are declared
in `:root` with a dark counterpart, following the precedent `--vp-conflict`
already set, and cycled by position from JavaScript. No template contains a hex
value.

## 16. What the live phase still inherits

> **Superseded by phase 23.** The tab went live on 2026-08-27 and every item
> below is resolved, moot or measured — see
> [`../value-profile-live/23-sightings.md`](../value-profile-live/23-sightings.md),
> whose §8.4 answers §11 row by row. Two things are worth carrying back here
> rather than leaving only in that document. **The aggregation rule is decided**
> and it is the one this section predicted: the maximum across occurrences,
> labelled with the occurrence holding it (§5 there). And **one claim in §11 was
> wrong** — `Sighting::attributesStatistics()` does group org × attribute × type
> × date in SQL, but its public method collapses the date dimension out of the
> org breakdown before returning, so the org-stacked histogram *did* need
> different work. The rest of this section is left as written, because it is the
> record of what was expected before anything was measured.

§11's list is unchanged and none of it was solved here — this phase is fixture
work, and the derived curve is a model of `getScoreOvertime`, not a call to it.
One item is now sharper rather than softer:

**The aggregation rule is still undecided, and the fixture now has an opinion.**
`decayModels` computes one score per value from one row set. Live, ten
occurrences can carry ten curves per model, and §11 records that there is no
aggregation rule and that the phase 7 deck proposed *max across occurrences,
labelled with the occurrence it came from*. The rail card's provenance line —
*"Last reset by CIRCL on 2025-08-22"* — is the slot that label lands in, and
`lastResetAt` is the function that would have to become per-occurrence. That
decision is still to be taken before this tab goes live, not during.

Added by this phase, and small: `sightingSeries` resamples into daily buckets
under 90 days and weekly ones above. §11 already noted that aligning
`getScoreOvertime`'s hourly samples to a window means resampling in PHP; the
bucket-width rule and the `step_label` caption that states it are now written
down, so the live version has a shape to match rather than one to invent.
