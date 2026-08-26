# PRD: Value Profile — Timeline tab

**Phase 15.** Implements candidate **`T-final`**, chosen 2026-08-26.
Artifact: <https://claude.ai/code/artifact/7c2bbad2-fcf2-49c8-8bf3-b60f122cadbb>
Depends on `00-shared.md`, and on `prd/value-profile-page.md` §8.2 for what MISP
can and cannot date.

## 1. What ships

**One window, three depths: when the value was busy, which sources say so, and
what exactly happened — all of it the same selection.**

`T-final` is not one of the four candidates the deck opened with. It was drawn
after review, because each of the four solves one third of the tab and loses the
other two: T3 knows *when* and cannot say *from whom*; T2 knows *from whom* and
shows no entry; T1 lists the entries and gives no way to find a period; T4 is
honest about every fact and has no shape at all. Stacking them against one
selection is the design:

1. **The spine** — twelve months of dated entries, stacked by source. It is a
   *control*, not a second chart: brushing it sets the window for everything
   below.
2. **The source lanes** — one per source the tab promises, reporting what the
   window holds. Four carry marks. Three are hatched and say why.
3. **The chronology** — the entries in the window, newest first, each row
   carrying how well it is dated.

**Why this and not `T1`.** T1 is cheaper by a wide margin and remains the
fallback if this phase has to be small. What it cannot do is put the record's
absence in the reading path: its undated facts live in a rail, and a reader who
never looks right sees a complete-looking timeline missing three sources. This
tab's whole argument is provenance, and §8.2 says two of its seven promised
sources have no timestamp on any instance. A design that hides that in a margin
is the wrong one here.

**Why the spine is safe now.** T3 was rejected on its own for a specific reason:
a density spine cannot tell a quiet month from an unrecorded one, and on the
demo value December is the second. Inside `T-final` that objection is answered
rather than ignored — the spine no longer stands alone, and the lanes underneath
it state which sources could have recorded the months it draws.

**The cost, recorded rather than discovered.** This is the tallest panel on the
page and the most expensive of the five to build: a lane primitive, a brush
driving two dependent regions, and three aggregates over one set. Size **L**.

## 2. The placeholder note is wrong and gets rewritten

`view.ctp` currently promises: *"One merged chronology: publications, first and
last seen, sightings, tags, opinions, feed appearances and edits."* It was
written in phase 1, before anyone checked. Of those seven, one is fully dated
and addressable by value, one needs an assembled union, two are truncated to
first-and-last, one is null on half this value's visible occurrences, and two
cannot be dated at all. The note is replaced with one that does not promise what
MISP cannot supply:

> Everything about this value that carries a date, on one axis — and, named
> rather than dropped, everything that does not.

## 3. The tab bar carries no count

Same reasoning as the History tab (`07-history.md` §3). "67 dated entries" is
the *viewer's* count: `Sightings_policy` can hide whole sightings and
`Sightings_anonymise` refiles foreign orgs, so two users get two numbers for one
value. The registry keeps Timeline without a count.

## 4. Layout

One full-width slot (`right => null`), three stacked cards inside one panel.

Not this page's outer 9/3, and not three panels. The brush is one control
driving two regions that must already exist when it fires. Three `.ajax-card`s
resolve independently, so a spine that loads first would be a brush wired to
nothing — the same reasoning that keeps the History tab's facet rail inside its
panel (`07-history.md` §4).

## 5. Controller

| Action | URL | Renders |
|---|---|---|
| `viewTimeline($b64value)` | ajax | `value_timeline` |

One endpoint, for the reason in §4. Register it in `ACLComponent::ACL_LIST`'s
`values` block with `theming_enabled`, and **verify as a non-site-admin** —
`00-shared.md` §3.1's trap.

## 6. Templates

```
app/View/Themed/Overmind/Elements/Values/View/
    value_timeline.ctp        the spine, the lanes, the chronology, the states
```

Stream rows reuse `.vp-audit-row` from `07-history.md` §6 where the two tabs
draw the same shape — a timestamped thing with a glyph and a sub-line. Whichever
phase lands first defines it; the second reuses it and does not redefine it.

New primitives, four: `.vp-lane` (the label / axis / count grid),
`.vp-lane-fill` (an absolutely-positioned hatch with its explanation),
`.vp-lane-tag` (a span label positioned along an axis) and `.vp-prec` (the
precision chip, with `-exact` / `-part` / `-none`).

## 7. One list of entries, three views of it

This is the phase's central constraint, and it is what phase 13 spent its
reckoning on: the spine's monthly bars, the lanes' in-window counts and the
chronology's list are **three aggregates over one array**, derived at render
time. None of them is written into the fixture as a number.

```php
'timeline' => [
    'entries'  => [ /* every dated thing, one row each, ascending */ ],
    'undated'  => [ /* what cannot be placed, with the reason */ ],
    'window'   => ['from' => '2025-08-01', 'to' => '2025-08-24'],
    'audit_recorded' => false,   // gates the edit lane's hatch
]
```

Each `entries` row:

```php
[
    'at'        => '2025-08-19 21:22:00',
    'source'    => 'edit',        // sighting|false_positive|publication|
                                  // note|opinion|edit|seen
    'precision' => 'latest',      // exact|first_last|latest|cache|none
    'title'     => 'attribute 4831022 — to_ids 0 → 1',
    'note'      => 'attributes.timestamp. Earlier edits are not recorded.',
    'org'       => 'CIRCL',
    'ref'       => ['attribute' => 4831022, 'event' => 1284],
    'span_to'   => null,          // set on a seen-span row
]
```

Each `undated` row carries `kind`, `count`, `reason`, `chips` and an optional
`as_of` — the feed rows use `as_of` and everything else leaves it null, because
"as of the last cache" is a real if useless timestamp and "no column exists" is
not a timestamp at all.

If a number in the panel cannot be derived from `entries` or `undated`, it does
not go in the panel. The tab must not be able to state two counts that disagree.

## 8. The three cards

### 8.1 The spine

Chart.js stacked bars, per `00-shared.md` §7 — twelve monthly bins, one stack
segment per source, a brush, and the axis labelled at the quarter. A month with
no entries draws nothing, which is how December reads as the gap it is.

Below it, always, the **off-axis strip**: one chip per `undated` kind with its
count and its reason, and the line *The December gap is a gap in the record, not
a quiet month.* The strip is not conditional on the brush — nothing on it is in
any window.

### 8.2 The lanes

Seven rows in a `label | axis | in-window count` grid. Header: the window's
dates, the entry count, and the note that every promised source gets a lane
whether or not MISP records it.

| Lane | Drawn from | Sub-label |
|---|---|---|
| Sightings | `source: sighting`, plus `false_positive` in the danger colour | `date_sighting, exact` |
| Publications | `source: publication` | `first & last only` |
| Notes / Opinions | `source: note`, `opinion` | `created, exact` |
| Edits | `source: edit`, over a hatch when `audit_recorded` is false | `latest per occurrence` |
| Seen spans | `source: seen` with `span_to` | `n of m occurrences carry one` |
| Tags | nothing — hatched | `no column exists, any instance` |
| Feed appearances | nothing — hatched | `one date per feed, moves on refresh` |

Inline SVG, not Chart.js. `00-shared.md` §7's rule is about charts, and a lane
is not one: it has no axis of its own, no legend and no scale — seven Chart.js
instances would be seven canvases for a shape that needs marks and a `<title>`.
If a lane ever needs a real tooltip or a zoom, that is a local change.

**The seen-spans lane does not merge spans.** §8.2 flags this as needing an
invented aggregation rule; the answer here is to invent nothing. One bar per
occurrence that carries a span, labelled with its attribute id, stacked within
the lane. Ten occurrences would be ten bars and the lane would grow. On the demo
value and the August window that is one bar, which is why the mockup shows one.

**Lane text lives in HTML over the axis, never inside the SVG.** The axis is
drawn with `preserveAspectRatio="none"`, which stretches glyphs with it and
turns an explanation into a smear. `.vp-lane-fill` and `.vp-lane-tag` exist for
exactly this.

**A mark on a hatch needs a ground.** The one recorded edit against an
unrecorded background is that lane's whole point, and a secondary-coloured mark
on a secondary-coloured hatch loses it. The mark is drawn over a
`--bs-body-bg` rect.

### 8.3 The chronology

The window's entries, newest first, day-grouped. Header carries the precision
tally — and it tallies only what is in the list, so it has an *exact* and a
*partial* bucket and **no** *no date* bucket: the undated facts are in the lanes
and on the strip, not in this list. A "no date" count in a list of dated entries
is a number that describes nothing.

Rows: time, source glyph, title, precision chip, sub-line. Same-day same-source
runs collapse to one summary row with a count and an expander — 47 sightings
must not be 47 rows. Footer: how many more are in the window.

## 9. States

1. **Populated** — the malicious and conflicted values.
2. **No dated entries** — spine flat, the four dated lanes empty, the two
   structurally-hatched lanes and the off-axis strip still carrying the tab.
   This is the state that justifies the design: the tab still says something
   true when nothing is dated.
3. **Edits not recorded** — not a whole-tab state but a lane state, driven by
   `audit_recorded`. The hatch names `MISP.log_new_audit`.
4. **Hidden by ACL** — the entry set is the viewer's. Where every occurrence is
   hidden, the panel becomes the suppressed state (`00-shared.md` §8) and names
   the number it cannot show.
5. **Unknown value** — no `timeline` key, the sparse page as phase 5 built it.

## 10. Interactions

- **Brush the spine** → both the lanes and the chronology re-scope. One shared
  window object; the two regions read it, neither owns it.
- **Click a lane** → the chronology narrows to that source. Clicking a hatched
  lane does nothing and says so in its `title` — there is nothing to narrow to.
- **Reset window** → back to the default from the fixture.
- **Expand a collapsed run** → the individual rows.
- Everything that would write is absent. A chronology has no verb.
- No brush, no JavaScript: the panel server-renders the default window and every
  count is already correct. Phase 5's posture.

## 11. CSS

`value-profile.css` gains the four classes from §6. The source colours come from
the same tokens the spine's Chart.js datasets use, defined once — a lane and its
stack segment must be the same colour or the two regions read as two subjects.

## 12. Deferred, and what live data will hit

- **`T2` standalone**, the design to revisit if the undated sources ever become
  datable: its lanes stop being hatched and it becomes the better tab.
- **`T1`** as the small fallback, per §1.
- **Tags and galaxy clusters can never join the axis.** `attribute_tags` and
  `event_tags` carry no `created`. No configuration changes this; only a schema
  change would.
- **Feed appearances can never join the axis either.**
  `misp:feed_cache_timestamp:<feedId>` is one integer per feed, rewritten on
  every refresh (`Feed.php:1573`), so it dates the fetch and not the value.
- **Publications stay two points per event.** `publish_timestamp` and
  `first_publication` are both already in `Event::fetchEvent`'s field list
  (`Event.php:3187`), so this lane needs no new query — it needs the honesty
  that anything between the two is not recorded.
- **The edit lane goes from one point per occurrence to a real history** only
  where `MISP.log_new_audit` is on, and then it is the same union
  `07-history.md` assembles. The two tabs should read the same rows; whichever
  goes live second reuses the first's scoping.
- **Sightings are the one source that needs no new work** —
  `attributesStatistics()` already groups org × attribute × type × date in SQL
  (§7.9) — but the count is the viewer's, per §3.
- **The spine's bins are monthly because the range is a year.** A value first
  seen last week needs daily bins. Choosing the bin from the range is live-data
  work and the fixture pins it.

## 13. Verification

1. `parallel-lint` over the new PHP and `.ctp`.
2. Load the malicious value's Timeline: spine over twelve months with a visible
   December gap, seven lanes, three hatched, the chronology under them.
3. Confirm the spine's total, the lanes' in-window counts and the chronology's
   header all derive from one array — change one fixture entry and confirm all
   three move.
4. Confirm the chronology's precision tally sums to the window's entry count and
   has no *no date* bucket.
5. Brush a different window and confirm the lanes and the chronology both
   follow, and that the off-axis strip does not.
6. Confirm the hatched lanes' text is legible at the pinned width in both
   themes — it is HTML, not SVG text (§8.2).
7. Confirm the single recorded edit is visible against its own hatch.
8. Set the fixture to no dated entries and confirm state 2 still says something
   true.
9. Load with JavaScript disabled and confirm the default window renders with
   correct counts.
10. Both themes; confirm a lane mark and its spine segment are the same colour.

## 14. Exit criterion

The malicious value's Timeline renders a brushable spine, seven source lanes of
which three are hatched with their reasons, and a chronology whose counts
provably derive from the same array as the other two; brushing moves all three
together; and with no dated entries at all the tab still tells the reader which
sources MISP was never able to date.
