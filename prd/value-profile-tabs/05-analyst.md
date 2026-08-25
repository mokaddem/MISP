# PRD: Value Profile — Analyst data tab

**Phase 13.** Implements candidate **`A1`**, chosen 2026-08-25.
Artifact: <https://claude.ai/code/artifact/09f056ee-be74-4560-90e1-b14cda8f832c>
Depends on `00-shared.md`.

## 1. What ships

**Open on where each organisation stands on the 0–100 scale, then read the
argument in the order it happened.**

Disagreement between organisations is the signal, and a mean hides it. The
Verdict tab already makes this argument for the conflicted value with a bimodal
histogram and a note that its mean is meaningless; this tab must not undo it by
leading with an average. `A1` was chosen because it is the only candidate that
leads with the split *and* keeps one chronological thread underneath — the only
arrangement that shows a reply next to what it replies to, which matters when
MISP lets notes and opinions carry notes and opinions two levels deep.

On the malicious value the mean is **50** and the nearest actual opinion is 26
points away. The strip draws the mean as a marker sitting in an empty band. That
is the whole design in one detail.

**Not taken.** `A2`'s graft — rendering the distribution through the existing
`value_verdict_opinions.ctp` element rather than marking it up again — is
deferred per the 2026-08-25 decision. Note that this defers the *element* reuse,
not the class reuse: `.vp-hist*` is mandatory either way (`00-shared.md` §4), so
the two histograms look like the same object even while they are two templates.
Collapsing them into one element stays the obvious cleanup.

## 2. Layout

One full-width slot (`right => null`). Two stacked panels; the first lays out an
internal row (`col-lg-4` histogram, `col-lg-8` per-organisation table).

## 3. Controller

| Action | URL | Renders |
|---|---|---|
| `viewAnalystStanding($b64value)` | ajax | `value_analyst_standing` |
| `viewAnalystThread($b64value)` | ajax | `value_analyst_thread` |

`viewAnalystPreview` stays the Overview's card and is untouched.

## 4. Templates

```
app/View/Themed/Overmind/Elements/Values/View/
    value_analyst_standing.ctp    the position strip, histogram and org table
    value_analyst_thread.ctp      the chronological thread and the composer
```

The thread's items are `.vp-analyst` blocks — the same primitive the Overview
preview and the Relationships tab's asserted claims use (`00-shared.md` §4). The
position strip is a static inline SVG scale, not a chart (`00-shared.md` §7).

## 5. Fixture additions

```php
'analyst' => [
    'opinions' => [
        ['org' => 'Org A', 'score' => 82, 'label' => 'Strongly agree',
         'reads' => 'supports the value', 'notes' => 0,
         'last' => '2025-08-22'],
        ['org' => 'Org B', 'score' => 76, 'label' => 'Agree', ...],
        ['org' => 'Org C', 'score' => 24, 'label' => 'Disagree',
         'reads' => 'disputes the value', ...],
        ['org' => 'Org D', 'score' => 18, 'label' => 'Strongly disagree', ...],
    ],
    'aggregate' => [
        'mean'    => 50,
        'count'   => 4,
        'buckets' => [0,1,1,0,0,0,0,1,1,0],   // ten bands, 0-100
        'gap'     => ['from' => 30, 'to' => 70],
        'note'    => 'two clusters 52 points apart · nothing between 30 and 70',
        // Nothing in MISP computes any of this — see §11.
        'computed' => 'at render',
    ],
    'thread' => [
        [
            'kind'         => 'opinion',       // opinion | note
            'score'        => 82,
            'label'        => 'Strongly agree',
            'org'          => 'Org A',
            'date'         => '2025-08-22',
            'distribution' => 'All communities',
            'attached_to'  => ['kind' => 'attribute', 'type' => 'ip-dst',
                               'event' => 1284],
            'markdown'     => true,
            'children'     => [
                ['kind' => 'note', 'org' => 'Org C', ...,
                 'attached_to' => ['kind' => 'event', 'event' => 1284],
                 'children' => [
                     // depth 2 — the last level MISP returns
                     ['kind' => 'opinion', 'score' => 68, 'org' => 'Org A',
                      'rates' => 'note',   // rates the note, not the value
                      'children' => [], 'max_depth_reached' => true],
                 ]],
            ],
        ],
        ...
    ],
    'counts' => ['items' => 6, 'opinions' => 4, 'notes' => 2, 'replies' => 3],
],
```

**`rates => 'note'` is load-bearing.** An opinion written on a note rates the
note, not the value, and must be excluded from the aggregate. Getting this wrong
is a bug waiting to be written, so the fixture marks it explicitly rather than
leaving the template to infer it.

The conflicted value carries the bimodal seven; the benign value five; the
unknown value zero. On zero, `aggregate` is `null` — not a mean of `0`, which
would be a claim nobody made.

## 6. Panel one — where the organisations stand

Header: analyst-data glyph, title **Where the organisations stand**, sub-line
`4 opinions from 4 organisations · two clusters 52 points apart · nothing
between 30 and 70`, and a `computed at render` chip. The chip is not decoration:
none of these numbers exists in MISP (§11), and a reader deserves to know the
page derived them.

**The position strip** (`.vpa-strip`) spans the panel: a 0–100 scale with one
marker per organisation, labelled, plus a bracket spanning the empty middle and
the mean drawn as a struck-through diamond inside that bracket. Markers that
collide merge into one badged `×3` rather than overlapping — which is what the
conflicted value's three opinions in the 11–20 band do.

**The histogram** (`col-lg-4`): the same ten buckets the Verdict tab draws,
through `.vp-hist`, with `.vp-hist-bar-ben` below 50 and `.vp-hist-bar-mal`
above, empty bands rendered as a 2% stub so the gaps are visible as gaps. A
count row above and a `0 / 50 / 100` axis below. Closing note: *"Two clusters,
five empty bands. The same ten buckets the Verdict tab draws, so a reader who
has seen one recognises the other."*

**The per-organisation table** (`col-lg-8`): `Organisation · Opinion · Reads the
value as · Position · Notes · Last activity`, with the mean shown as a chip
beside the sub-head (`mean 50 over 4 opinions`) rather than as a headline. Rows
carry the label and the score together (`Strongly agree · 82/100`), the reading
in words (`supports the value` / `disputes the value`), a `.vp-opinion` bar, the
count of notes that organisation wrote, and its last activity date. Between the
last supporting row and the first disputing one, a gap row: *"no organisation
between 30 and 70"* — the empty middle rendered as a row, because it is the most
important thing on the panel.

Closing note, which settles a contradiction MISP currently ships: *"Colour is
the reading, not the agreement: above 50 argues the value is hostile, below 50
argues it is not — the two hues the Verdict tab already uses. An opinion written
on a note takes neither."*

## 7. Panel two — notes and opinions

Header: title **Notes and opinions**, sub-line `6 items on this value · 4
opinions, 2 notes · 3 replies written on them · newest first`. Controls: a sort
(`Newest` / `Oldest` / `By organisation`) and filter pills carrying counts
(`All 6`, `Notes 2`, `Opinions 4`).

The thread, one `.vp-analyst` per item:

- **Kind** — `Opinion` or `Note`, with MISP's own glyph
  (`misp-icon-analyst-opinion`, `misp-icon-analyst-note`).
- **A side marker** (`.vpa-side-mal` / `.vpa-side-ben` / `.vpa-side-none`)
  carrying the reading, so scrolling the thread shows the argument's shape
  without reading it. An item that rates a note, not the value, takes
  `.vpa-side-none` and says so inline: *"about the note above, not about the
  value — not in the aggregate"*.
- **An attachment chip** (`.vpa-chip`) naming what the item actually hangs off —
  `ip-dst in #1284`, or, for an event-level item, *"Attached to the event, so it
  is inherited by every occurrence in it"*. Analyst data attaches to an
  `object_uuid`, never to a value, and this chip is where the page stops
  pretending otherwise.
- **The body**, markdown-rendered (`.vpa-md`).
- **A meta line**: organisation, date, distribution.
- **Replies** nested via `.vpa-reply` / `.vpa-reply-2`, and at the second level
  the note *"Two levels is what MISP returns. Anything written below this one is
  flagged but not fetched."* — `_max_depth_reached` rendered rather than
  silently truncating.

Footer: the composer, disabled — a note/opinion switch, a text area, an
`Attach to` picker naming which occurrence or event the item would hang off, and
the disabled explanation. The picker is disabled like everything else, but it is
drawn, because a composer with no target is the thing that cannot ship.

## 8. States

| State | What renders |
|---|---|
| Populated | as above |
| Bimodal (`104.21.34.198`) | three markers collide into a badged `×3`, the gap bracket narrows to 30 points, and the mean at 46 still lands in an empty band — the arrangement holds |
| Zero | the strip is replaced by one `.vp-empty` line, *"No organisation has recorded an opinion on this value"*, and the thread panel keeps its composer so the page reads as usable rather than broken |
| ACL | the `.vp-acl-note` states that items exist which are not shown — **without a count**, because the count is not obtainable (§11) |
| Unknown value | both panels in their empty state, composer still drawn and disabled |

## 9. Interactions

Working, client-side: sort, the three filter pills, reply expansion, and hover
on a strip marker highlighting its table row.

Disabled with a `title`: the composer, its `Attach to` picker, and its submit.

## 10. CSS

New: `.vpa-strip` (the scale), `.vpa-mean`, `.vpa-side-*`, `.vpa-chip`,
`.vpa-md`, `.vpa-reply`, `.vpa-hist-counts`, `.vpa-thread`.

Reused as-is: `.vp-analyst*`, `.vp-hist*`, `.vp-opinion*`, `.vp-panel*`,
`.vp-table`, `.vp-empty`, `.vp-acl-note`, `.vp-subhead`.

## 11. Deferred, and what live data will hit

**Deferred by choice:** `A2`'s element-level reuse of `value_verdict_opinions`.

**From §7.9:**

- **A value is not a valid analyst-data target.** Notes, opinions and
  relationships hang off `object_uuid` + `object_type`. This tab is a
  controller-assembled union over the value's occurrences and their events —
  there is no single query, and **no pagination across it**. The composer must
  carry an explicit "attach to" target, which is why the picker is drawn.
- **Nothing computes the aggregate.** No mean, no buckets, no per-organisation
  rollup exists anywhere in MISP; the Verdict tab's histogram is fixture data.
  Whoever wires this also has to decide — in code — that an opinion written on a
  note rates the note and not the value.
- **Markdown is stored and never rendered.** `Analyst_data/thread.ctp` prints
  notes `pre-line` with no parser. `markdown-it.js` ships for event reports, but
  there is no per-note markup flag (`language` is a natural-language code), so
  enabling it enables it for every note on the instance — a decision, not a
  detail.
- **`authors` is free text**, not a user reference. Only `Org`/`Orgc` are
  relational, so no grouping below organisation level is reliable.
- **The withheld-by-ACL count is not obtainable.** `buildConditions($user)`
scopes the fetch and there is no unscoped count to subtract, which is why the
ACL note here states existence without a number — unlike Occurrences, where
the count is knowable.
- **Depth 2 is a fetch limit**, not a display choice
  (`fetchChildNotesAndOpinions($user, $item, false, 2)`).
- **MISP colours opinions two contradictory ways today**: the Overview preview
  paints "Agree" green, the Verdict histogram paints everything above 50 red.
  This tab unifies on the Verdict reading. The contradiction needs settling
  before either goes live, and the Overview card is the one that should change.

## 12. Verification

1. `php -l` on both elements.
2. Both endpoints return 200 for all four demo values; both panels resolve.
3. Malicious value: four markers on the strip, the bracket over the empty
   middle, the mean struck through inside it, ten histogram bands with five
   empty, the org table with its gap row, and the colour-is-the-reading note.
4. The thread: six items, two levels of nesting, the depth note at level two,
   one item marked as rating a note rather than the value and excluded from the
   aggregate, one event-level item carrying the inheritance chip.
5. Conflicted value: the collided `×3` marker and the mean in an empty band.
6. Unknown value: both empty states, composer drawn and disabled.
7. Light and dark: `.vp-hist-bar-mal` / `-ben` match the Verdict tab's rendering
   exactly — the same buckets must not be two different greens.

## 13. Exit criterion

Artifact `A1` is recognisable in the browser; the split is visible before a word
is read; the mean is on screen without being the headline; and a reply sits next
to what it replies to.

## 14. Verification — what was run

Against the Docker stack serving this worktree, as an authenticated user.

1. **`php -l`** over both new elements, the controller, the fixture, `view.ctp`
   and `value_verdict_opinions.ctp` — clean. `node --check` on
   `value-profile.js` — clean.

2. **Eight endpoint fetches, eight 200s.** `viewAnalystStanding` and
   `viewAnalystThread` for all four demo values. No PHP notice, warning or
   undefined index in any fragment. Four full-page fetches also 200, and the
   Analyst data tab now points at two panel endpoints rather than the
   placeholder.

3. **The tab-bar count is the panel's own count.** `counts['analyst']` is
   derived from the thread's top-level items, so the pill reads `(6)` on the
   malicious value and the panel header reads `6 items on this value` because
   they are one number. Same for `(7)` and `(5)`.

4. **The tab driven in a real browser, both themes, all four values.**
   55 assertions on the malicious value, 57 on the conflicted, 53 on the
   benign, 11 on the unknown — all passing in light and in dark. Every tab on
   the page is opened first, so the regression checks are made against panels
   that actually loaded.

   What the driver establishes, beyond the endpoints resolving:

   - **The strip.** Four markers on the malicious value, three on the
     conflicted (one of them merged), three on the benign; every marker's ink
     resolved to a real colour rather than an unresolved `var()`; ten histogram
     bands every time.
   - **The collision.** The conflicted value's `Team-CIRCL|ORGNAME` marker
     carries `×2`, and hovering it lights *both* table rows — the only way a
     reader finds out which organisations collided. Hovering an ordinary marker
     lights exactly one row, and leaving puts it out.
   - **The two histograms are one object.** On the conflicted value, the only
     value that draws a histogram on two tabs, `.vp-hist-bar-mal` measures
     `rgb(220, 53, 69)` in both places and `.vp-hist-bar-ben` measures
     `rgb(77, 161, 103)` in both places, in both themes. Not by matching class
     names — by measuring the two elements on one page.
   - **The thread.** Replies nested, the depth note rendered exactly where a
     branch reaches the fetch limit and nowhere else, and every item that rates
     a note both saying so and taking neither side.
   - **Markdown consumed, not printed.** No `####` or `- ` survives into any
     rendered body, and the only tags inside a `.vpa-md` are the seven the
     renderer itself emits — which is what makes escape-then-mark-up a
     safety argument rather than a claim.
   - **Sort and filter compose.** `Oldest` reverses the thread, `By
     organisation` groups it, `Newest` restores it; the kind pills split 6 into
     2 + 4 and put it back; and applying one does not silently undo the other.
   - **Replies travel with what they reply to.** After every reorder, each
     reply is still inside the item it answers — the property that makes the
     top-level item, not the claim, the unit that moves.
   - **Reply expansion** folds and unfolds the right block: the sibling of the
     claim the button sits in, never a nested item's own replies.
   - **Nothing writes.** Six or more dead controls in the composer, every one
     carrying a `title` saying why, and the `Attach to` picker drawn and
     disabled.

5. **Contrast, measured rather than eyeballed.** The not-in-the-aggregate chip
   is 5.92:1 light and 8.47:1 dark; the attachment chip 15.43:1 and 11.85:1;
   the depth note the same. The lit table row and the composer both resolve a
   ground of their own in both themes — which needed the measurement to read
   `color(srgb …)`, since anything out of a `color-mix()` comes back in that
   form and not as `rgb()`.

6. **The four states.** Populated, bimodal, zero and ACL:

   - the **malicious** value: four markers, a 30-point bracket, the mean
     outside it and not struck through, the gap row, the colour-is-the-reading
     note;
   - the **conflicted** value: the merged `×2`, a 48-point bracket, and
     `mean 40.5 — a reading no organisation holds` drawn *inside* it, with the
     chip struck through to match;
   - the **benign** value: a 55-point bracket, mean 31 inside it, seven empty
     bands;
   - the **unknown** value: one `.vp-empty` line in place of the strip, the
     thread saying nobody has written anything, no sort control over an empty
     list, no `computed at render` chip over a panel with nothing computed on
     it, and the composer still drawn and still disabled.

7. **No regression.** The Occurrences facet rail, the Sightings chart, the
   Relationships tab and the Enrichment rail all still resolve on every value,
   and the Overview's analyst preview renders byte-for-byte what it did before:
   the same two notes, the same two opinions, the same `85/100` and `30/100`
   the standing panel now shows for the same two organisations.

## 15. Where this differs from the brief above

**The opinions are the value's own.** §5 gives the malicious value 82 / 76 /
24 / 18 and a mean of 50 sitting 26 points from anything. Those numbers cannot
ship: the Verdict tab's per-organisation table already states CIRCL 85,
CthulhuSPRL.be 75, Team-CIRCL 60, ORGNAME 30 for this value, and the Overview's
analyst preview already prints two of them. Three tabs of one page would have
carried three different opinion sets. The tab therefore renders what the page
already claims, and the consequence is that **the malicious value is not the
one that demonstrates the empty middle** — its mean of 62.5 has an opinion 2.5
points away, so the mean is shown plainly rather than struck through. The
conflicted value carries the demonstration instead, and carries it better:
mean 40.5, nearest opinion 19.5 points away, drawn inside a 48-point bracket.

That is also why **the mean's treatment is conditional**. Striking through a
mean that describes somebody would be theatre; the strip and the chip strike it
only when no organisation holds a position within half a band of it, and the
strip's caption changes with it.

**The collision is `×2`, not `×3`.** §8 wants three markers merging on the
conflicted value. Four organisations hold an opinion on it, two of them two
points apart, so two merge. The badge, the merged label, the score range and
the multi-row hover are all exercised; only the count differs.

**The Verdict tab's opinion card is now derived from these rows.** It claimed
seven opinions with a mean of 46 over buckets holding three opinions in 11–20
and none at all in 0–10 or 51–60 — which its own per-organisation table
contradicts, since that table lists 80, 60 and 10. The two were written
independently and could not both be true. The card now reads the standing
panel's aggregate, so the count, the mean and the ten buckets are one
computation; its note is regenerated from the same numbers. This is the
element-level reuse §11 defers, arriving from the other direction: not one
template rendering two panels, but one array feeding two.

**One organisation holds an opinion and no occurrence.** Team-CIRCL has written
on the conflicted value without appearing in its Verdict tab organisation list.
That is not a fixture slip — analyst data hangs off an object UUID, so anybody
who can see an attribute can write about it, and the set of organisations with
an opinion is simply not the set with an occurrence. The tab is where that
becomes visible, and it is worth keeping.

**Nothing states a number the thread can contradict.** §5 gives the fixture a
`counts` block; it is computed from the thread instead, along with the mean,
the ten buckets, the empty-band count, the widest gap, each organisation's note
count and its last activity. The header sub-lines are assembled from those
values rather than written as prose, so a fixture edit cannot leave a sentence
behind describing the old data. The gap row's two numbers come from the rows on
either side of it, so it is true even if the widest gap stops coinciding with
the side boundary.

**The markdown chip is earned.** §7 puts `rendered from markdown` on notes. A
plain sentence with no markup in it has not been rendered from anything, so the
chip appears only where the body actually uses a construct the renderer knows.

**The composer offers what the viewer can see.** The mockup's picker says
*"Choose one of the 10 occurrences of this value"* — the total. Four of those
ten are hidden by distribution, and an occurrence you cannot see is not one you
can attach a note to. The picker offers the six.

**The panel header's chip is withheld on an empty panel.** `computed at render`
is a statement about numbers, and a panel with no numbers on it does not get to
make it. The same reasoning removes the sort control and the four zeroes from
the thread's sub-line on a value nobody has written about.

**`A2`'s element-level reuse stays deferred**, as §11 says. So does the
contradiction it names: the Overview's preview still paints "Agree" green while
this tab paints anything above 50 red. Settling it means changing the Overview
card, which is a decision about a shipped panel rather than a detail of this
one — recorded here, not taken.
