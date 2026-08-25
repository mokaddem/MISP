# PRD: Value Profile — Occurrences tab

**Phase 9.** Implements candidate **`O2`**, chosen 2026-08-25.
Artifact: <https://claude.ai/code/artifact/b9ab9ec9-e3cf-42e4-86b7-d53242c9447f>
Depends on `00-shared.md`.

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

## 11. Verification

1. `php -l` on both new elements.
2. `viewOccurrenceTable` returns 200 for all four demo values; the panel
   resolves off its spinner in the tab.
3. Malicious value: nine columns, twelve offered, `Showing 6 of 10 · 7 events ·
   4 organisations`, eight facet groups, the banner/rail discrepancy note
   present, one proposal badge, one struck-through row behind the switch.
4. Facets filter client-side and both counts update together; `Clear all`
   restores.
5. Unknown value: no rail, one empty state, no facet block of zeroes.
6. Light and dark: the bulk bar is legible (the `00-shared.md` §9 fix), org dots
   and type badges hold their contrast, struck-through rows stay readable.
7. `index_table` callers elsewhere render byte-identically.

## 12. Exit criterion

Artifact `O2` is recognisable in the browser on the malicious value in both
themes; the facet rail filters; and the unknown value renders the tab as one
honest empty state rather than an empty rail beside an empty table.
