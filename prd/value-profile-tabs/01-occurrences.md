# PRD: Value Profile — Occurrences tab

**Phase 9.** Implements candidate **`O2`**, chosen 2026-08-25.
Built 2026-08-25; §11 records what was verified and §13 where the
implementation departs from the sections above.
Artifact: <https://claude.ai/code/artifact/b9ab9ec9-e3cf-42e4-86b7-d53242c9447f>
Depends on `00-shared.md`.

**Went live in phase 22** —
[`../value-profile-live/22-occurrences.md`](../value-profile-live/22-occurrences.md).
Four things stated below no longer render, all of them §14.6's viewer-scoped-count
rule rather than defects: §6's `.vp-facet-note` banner-versus-rail sentence, §7's
`.vp-acl-note` table footer, §8's *"everything hidden by ACL"* state, and §5's
`banner_note` and `occurrence_acl_note` fixture keys. In their place §7's footer
carries a **cap** notice, because the live table shows at most 100 rows and says
so. §10's list of what live data would hit is answered there.

## 1. What ships

**A counted facet rail beside the table, so the shape of the value's
occurrences is readable before a single row is.** The tab's whole justification
is that the Overview already previews this table: what this one adds is
filtering, selection and the three columns the preview drops. `O2` makes that
addition the first thing on screen — every filter carries its own count, so the
filter set and the summary of the value are the same object.

**Not taken.** Two grafts the phase 7 review recommended are deferred, per the
2026-08-25 decision to ship bare picks:

- `O1`'s bottom-docked bulk bar. `O2`'s bar is top-docked, which shoves the
  table down at the moment the reader is looking at rows. Revisit after the
  first look at it in the browser.
- `O4`'s pending-proposal diff as a row expander. The indicator ships; the
  field-by-field diff behind it does not.

## 2. Layout

One full-width slot (`right => null`), and the panel lays out its own internal
row — the rail is on the **left** at `col-lg-3`, the table at `col-lg-9`, which
is the reverse of the page's usual split. Both come from one fetch, so the
counts and the rows cannot disagree (`00-shared.md` §3).

## 3. Controller

| Action | URL | Renders |
|---|---|---|
| `viewOccurrenceTable($b64value)` | ajax | `value_occurrence_table` |

One endpoint, not two: a facet count and the row it counts must be computed
together.

## 4. Templates

```
app/View/Themed/Overmind/Elements/Values/View/
    value_occurrence_table.ctp      the internal row; owns the panel headers
    value_occurrence_facets.ctp     the rail
```

The table itself is `index_table`, not bespoke markup. `O2` was drawn with
MISP's own field renderers and the outline shows it: `idx-col-event`,
`idx-col-organisation`, `idx-col-type`, `idx-col-distribution`,
`idx-col-value_object_context`, `idx-col-datetime`, `idx-col-tag_list`. The
`value_object_context` renderer added in phase 3 carries the object column
unchanged.

The bulk bar is **`IndexTable/multi_select_toolbar.ctp`**, with the dark-theme
fix from `00-shared.md` §9. The mockup drew its own `vp-o-bulk` because a
published artifact cannot call PHP; that is not a reason to keep a second
implementation. The one addition the design makes is the scope line beside the
count — `3 rows · 3 events · 2 organisations` — which the element takes as an
optional slot.

## 5. Fixture additions

The `occurrences` array already exists and already carries `category`,
`comment`, `first_seen`, `last_seen`, `deleted` and `object_relation`. Three
additions:

```php
'occurrences' => [
    [
        'Attribute' => [ /* as today */ ],
        // NEW: a pending shadow attribute on exactly one occurrence.
        'proposal_count' => 1,
        ...
    ],
],

// NEW: the rail. Stated in the fixture rather than derived in the template so
// the numbers are written down once, and so the ACL discrepancy is explicit
// rather than an accident of which rows the fixture happens to list.
'occurrence_facets' => [
    'visible'   => 6,       // rows these counts cover
    'total'     => 10,      // rows the value has
    'groups'    => [
        ['key' => 'organisation', 'label' => 'Organisation',
         'icon' => 'fas fa-building',
         'values' => [['label' => 'Org A', 'count' => 3], ...]],
        ['key' => 'type', ...],          // ip-dst 4, ip-src 1, domain|ip 1
        ['key' => 'category', ...],      // Network activity 5, Payload delivery 1
        ['key' => 'ids', ...],           // to_ids set 4, unset 2
        ['key' => 'distribution', ...],  // one row per level, with its badge
        ['key' => 'sharing_group', ...],
        ['key' => 'tag', ...],           // rendered as tag chips, not text
        ['key' => 'state', ...],         // with a pending proposal: 1
    ],
    'seen_spark'   => [ /* 40 ints, first/last-seen density */ ],
    'seen_unset'   => 2,     // "2 occurrences carry no first/last seen at all"
    'deleted'      => 1,     // the include-soft-deleted switch's count
    'banner_note'  => [      // the honesty line (00-shared.md §5)
        'chip' => 'ip-dst', 'banner' => 7, 'rail' => 4,
    ],
],
```

`proposal_count` is new to the fixture because **no demo value carries a
pending proposal today** (§7.9). The indicator is required by the design, so
the fixture grows one rather than the template rendering a state that never
appears.

All four demo values supply `occurrence_facets`. On the unknown value it is
`null`, not a set of zero groups — a rail of zeroes is a lie about the value
(`00-shared.md` §5).

## 6. The rail — `value_occurrence_facets`

Panel header: `fa-filter` glyph, title **Filters**, sub-line
`No filter applied · 6 rows` which becomes `2 filters · 3 rows` as facets are
checked, and a `Clear all` button that is inert until something is checked.

Directly under the header, `.vp-facet-note`: *"Counts cover the **6 occurrences
you can see**. The banner counts all 10 — it says **ip-dst 7**, this rail says
**4**."* This is not decoration. It is the one place on the page where the ACL
gap between the banner and the table is stated as a number.

Then one `.vp-facetgrp` per group, in this order — organisation, type, category,
IDS flag, distribution, sharing group, tag, first/last seen, row state. Rules:

- Each row is a `<label>` wrapping a checkbox, so the whole row is the hit area.
- Distribution rows render the real distribution badge, tag rows the real tag
  chip. The label *is* the component wherever MISP has one.
- `First / last seen` is not a facet list: a sparkline of seen-density over the
  value's lifetime, two `type="date"` inputs, and the line *"2 occurrences carry
  no first/last seen at all"* — because `first_seen`/`last_seen` are optional
  and a date filter over a frequently-empty column needs to say so.
- `Row state` carries `With a pending proposal (1)` and, below it, the
  include-soft-deleted switch labelled with its count: `Include 1 soft-deleted`.

## 7. The table — `value_occurrence_table`

Panel header: attribute glyph, title **Occurrences**, sub-line
`Showing 6 of 10 occurrences · 7 events · 4 organisations`. On the right, a
`Columns (9 of 12)` dropdown and `1–6 of 6` beside the page control.

**Twelve columns, nine shown.** Shown: State, Event, Reported by, Type, IDS,
Distribution, Context, Last seen, Tags. Behind the Columns menu: Category,
Comment, First seen. The header states the ratio so a reader knows the table is
not everything the row has.

Column notes:

- **State** is the row's exception column and is empty (`—`) for an ordinary
  row. It carries the pending-proposal badge (`fa-code-pull-request` + count,
  title *"A pending shadow attribute proposes a change to this occurrence"*) and
  the soft-deleted badge (`fa-trash` + `Del`, title *"Soft-deleted — history,
  not current state"*).
- **Event** renders the event id as a bordered card with an external-link
  affordance and the truncated info beneath, so a 90-character event title
  cannot set the row height.
- **Reported by** carries `.vp-o-orgdot` — a one-letter disc keyed to the org —
  before the org name, so four organisations are distinguishable down the
  column without reading them.
- **IDS** is a shield glyph, warning-toned when set and secondary when not,
  with a `title`; not the word.
- **Last seen** prints `Not set` — the words, never a dash — where the column is
  empty, so "unknown" cannot be confused with "no value".
- Soft-deleted rows get `.vp-occ-deleted`: struck through, dimmed, and still
  selectable, because history is a legitimate thing to export.

Panel footer: `.vp-acl-note` — *"Showing 6 of 10 occurrences. 4 are hidden by
distribution rules on events owned by other organisations."*

## 8. States

| State | What renders |
|---|---|
| Populated | as above, malicious value |
| High cardinality (`104.21.34.198`, `8.8.8.8`) | the rail is unchanged in size; the table paginates. The rail's note says its counts come from the whole set, not the page — which is a promise the live implementation has to keep (§10) |
| Long-tailed facet | top ten values and an `n more`; past ~50 the group becomes a search box (`00-shared.md` §5) |
| Empty (unknown value) | no rail at all; one `.vp-empty` in the table panel: *"This value has no occurrences on this instance."* |
| Everything hidden by ACL | the table shows `.vp-empty` and the `.vp-acl-note` band states the count that exists and is not shown — the two are different sentences and must not merge |

## 9. Interactions

Working, client-side, against rows already in the DOM (§2.11): facet checkboxes
filter rows and update both the rail's sub-line and the table's `Showing n of
m`; the include-soft-deleted switch reveals the struck-through rows; row
selection reveals the bulk bar and recomputes the scope line; the Columns menu
toggles column visibility; `Clear all` resets; the page control moves between
pages of rows already present (`00-shared.md` §6).

Disabled with a `title`, per §2.11: every bulk operation, `Export selection`,
and the `Propose edit` control. The disabled note sits in the bar itself —
*"Disabled in this pass — the Value Profile page does not write to the database
yet."*

## 10. Deferred, and what live data will hit

**Deferred by choice:** `O1`'s bottom-docked bulk bar; `O4`'s proposal diff.

**From §7.9, for the live phase:**

- **No endpoint returns per-facet counts over an ACL-scoped attribute set.**
  Tallying the fetched set in PHP is honest at ten rows and stops being honest
  the moment the table paginates; past that it needs a `GROUP BY` under the same
  conditions `fetchAttributes` builds. The rail's note is what makes the current
  regime visible instead of implied.
- **A cross-event bulk write does not exist.** Every bulk action fans out per
  attribute, and one selection can mix rows the user may edit with rows they may
  only *propose* against. No endpoint or confirmation dialogue expresses that
  today, which is why the whole bar is disabled rather than half of it.
- **The event ids behind ACL-hidden occurrences are not obtainable.** The count
  is; the rows are not. Any future "show me what I'm missing" affordance can
  name a number and never a row.

## 11. Verification — what was run

Against the Docker stack serving this worktree, logged in and rendering the
Overmind theme.

1. **`php -l`** over every changed and new file, plus `node --check` on
   `value-profile.js`. Clean.
2. **The fixture is internally coherent** — 143 assertions. Every stated facet
   count is recomputed from the rows it claims to cover; every token a row will
   carry names a facet the rail offers, and every facet the rail offers matches
   at least one row; `visible`/`total` agree with `occurrence_stats`;
   `banner_note` quotes the banner's own chip count and the rail's own type
   count and the two differ. This is the check the design needs precisely
   because §5 writes the numbers down rather than tallying them: nothing else
   would notice them drifting from the rows.
3. **`viewOccurrenceTable` returns 200 for all four demo values** and the panel
   resolves off its spinner in the tab.
4. **The fragment's structure — 61 assertions** across the four values. On the
   malicious value: rail at `col-lg-3` before the table at `col-lg-9`; thirteen
   `<th>` of which ten are shown — nine columns and the checkbox; `Columns
   (9 of 12)`; `Showing 6 of 10 occurrences · 7 events · 4 organisations`; the
   seven element-driven facet groups in design order plus the two bespoke ones;
   the banner/rail note carrying `6`, `10`, `ip-dst 7` and `4`; six rows with
   facet tokens, one proposal badge, one struck-through row; forty spark
   buckets. On the unknown value: no rail, no bulk bar, no ACL band, exactly
   one empty state.
5. **The interactions, driven in a real browser** — 32 assertions, in light and
   dark, against the shipped CSS and JS with the real ajax path running. Six
   rows on arrival; `ip-dst` narrows to 4 and both counts follow; adding
   `ip-src` widens to 5 (alternatives within a key); adding `CIRCL` cuts to 2
   (conjunction across keys); a tag chip filters like any other facet;
   `Clear all` restores 6 and goes inert; the soft-deleted reveal arrives on
   and unticking it drops the struck-through row and the header to 5; hiding
   Tags takes its heading with it and drops the ratio to 8 of 12; unfolding
   Category brings it back to 9; selecting rows reveals the bulk bar with
   `2 rows · 2 events · 2 organisations`, then `3 rows · 3 events · 2
   organisations`, every control in it disabled; the unwired date range is
   disabled.
6. **Light and dark, measured not eyeballed.** The bulk bar inverts correctly
   (ground `#f8f9fa`/ink `#212529` → `#212529`/`#f8f9fa`, 14.63:1 both ways) and
   the type badge's border inverts with it — 00-shared §9's finding, restated
   here because this brief's original item asked for a "fix" that was
   withdrawn as unfounded. The rail's honesty note reads 14.41:1 light and
   11.04:1 dark, the ACL band 13.01 and 8.84, and the struck-through row keeps
   its line-through and 0.6 opacity in both. The org discs measured 3.33:1 at
   first and their lightness was taken from 45%/42% to 45%/35%, which puts
   every hue between 4.60 and 5.91.
7. **`index_table` and `headers.ctp` callers render byte-identically.**
   `events/index`, `attributes/index`, `feeds/index`, `tags/index`,
   `warninglists/index` and the diagnostics page fetched with and without the
   change: after normalising the per-request ids CakePHP mints, nothing else
   differs, and the `<th>` census is identical across all 45 headers on those
   pages. `header_class` appears in none of them.

## 12. Exit criterion

Met. `O2` is recognisable in the browser on the malicious value in both
themes — the counted rail on the left, the nine-column table beside it, the
bulk bar above the rows and the ACL band under them. The rail filters. The
unknown value renders the tab as one honest empty state at full width rather
than an empty rail beside an empty table.

## 13. Where this differs from the brief above

Nine departures, each with its reason. Nothing here changes what the tab is;
they are the points where following the brief literally would have contradicted
something it inherits.

- **The facet groups' identity lives in the template, not the fixture.** §5's
  sketch put `label` and `icon` in `occurrence_facets`. `value-profile-page.md`
  §1.3 says the fixture carries nothing presentational, and the order, heading
  and glyph of the eight groups are the same for every value — so the fixture
  supplies only what varies (counts, and the domain values behind them) and
  `value_occurrence_facets` owns the rest. Three copies of the same eight
  labels would have been three places to change them.
- **`occurrence_facets` also carries `seen_from` and `seen_to`.** The design's
  date inputs are pre-filled with the value's span; §5's sketch had no field to
  fill them from.
- **The date inputs ship disabled, with a title.** Date filtering is not on
  §9's list of what works, and this page's own rule is that a control which
  cannot do its job says so rather than looking live. Implementing it was
  outside what the brief asked for; rendering it live-looking and inert was the
  one option the page's rules rule out.
- **A fourth shared hook was needed, which `00-shared.md` §5.2 did not
  anticipate: `header_class` on `headers.ctp`.** The Columns menu has to hide a
  column's heading along with its cells, and `row.ctp` already hangs
  `field['class']` on the `<td>` while `headers.ctp` had no counterpart.
  Reusing `class` was not open: `feeds/index`, `tags/index` and others pass
  `'class' => 'short'` for a cell width they do not mean to impose on the
  heading, and honouring it there would have changed live pages. `header_class`
  is the name two health-diagnostics tables already use for exactly this,
  though they render through the legacy index table and never reach this
  element — so the hook is dormant for every existing caller, which item 7
  above measures rather than assumes.
- **`.vp-o-orgdot` became `.vp-occ-orgdot`, and the disc is a fallback rather
  than an addition.** The rename follows phase 8's own precedent, where the
  mockups' per-candidate `vp-o-facet*` became `vp-facet*`. The behaviour
  changed because MISP's organisation renderer already draws a logo where an
  organisation has uploaded one — CIRCL has — and a disc beside a logo is two
  glyphs where the column needs one. The disc now stands in where there is no
  logo, which is what an avatar fallback is for and keeps the per-organisation
  colour cue down the whole column.
- **The rail's sparkline is `.vp-spark`, not a new primitive.** The mockup drew
  an SVG because a published artifact cannot call PHP; `00-shared.md` §7 keeps
  CSS bars as the standing exception to the Chart.js rule, and `.vp-spark`
  already exists for the Overview's sightings strip. It takes a colour
  modifier and forty buckets instead of ninety days.
- **`.vp-facet-summary-on` had no CSS.** Phase 8 shipped the JS that toggles
  the class and nothing that styled it, so the rail's "No filter applied" /
  "2 filters" line had no second state. Added to the shared facet block, where
  the rest of the control lives.
- **The soft-deleted reveal ships checked**, unlike the Overview preview's
  switch. The preview shows the value's current state, so it starts them
  hidden; this tab is the whole table, its header says "Showing 6 of 10" and
  the rail's counts cover six — all three of which are only true with the
  soft-deleted row in. Unticking it drops the row and the header to five
  together.
- **Plural nouns are static.** "1 filters", "1 rows" — the same compromise the
  shipped `.vp-filter-note` already makes ("%s of %s rows"), because the
  numbers are updated by script and `__n()` cannot reach them. Worth fixing
  once, for both, rather than differently here.

Two behaviours worth knowing rather than defending:

- **No demo value paginates.** The page control is real and rendered, and the
  header carries `1–6 of 6 (10 in total)`, but with six, five and five visible
  rows against a page size of ten, §8's high-cardinality row — "the table
  paginates" — is not reachable from this fixture. The control was exercised
  in phase 8 against 25 injected rows; here it is present and correct and
  idle. Growing a demo value's visible rows past ten is the way to see it, and
  is a fixture change no part of this design needs.
- **The bulk bar's `mass_*` buttons appear only for a user who may modify the
  owning event**, because `checkbox.ctp` asks the real ACL about fixture rows.
  Export and the four custom actions always show. The Overview's bulk bar has
  behaved this way since phase 3; both are disabled either way.

## 14. What the live phase still inherits

§10 stands unchanged — no endpoint returns per-facet counts over an ACL-scoped
attribute set, no cross-event bulk write exists, and the event ids behind
ACL-hidden occurrences are not obtainable. Two more, learned here:

- **Selection survives filtering.** A checked row that a facet then hides stays
  selected and stays in the scope line's count, matching MISP's own
  `selectedItems`. Harmless while every action is disabled; a live bulk write
  has to decide whether "selected" means "selected and visible".
- **Nine columns overflow `col-lg-9` below roughly 1800px** and scroll inside
  `.table-scroll`, which is what every MISP index does. The Columns menu is the
  release valve the design already gives the reader.
