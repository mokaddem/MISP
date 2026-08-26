# PRD: Value Profile — History tab

**Phase 16 — built 2026-08-26.** Implements candidate **`H2`** with `H1`'s
facet rail grafted, chosen 2026-08-26.
Artifact: <https://claude.ai/code/artifact/41280107-5222-435c-8d48-5b51b8acd1d3>
Depends on `00-shared.md`, and on `prd/value-profile-page.md` §8.2 for what MISP
does and does not record.

## 1. What ships

**One collapsible section per occurrence, so the copy of this value that keeps
being edited is visible before a single row is read.**

§8.5 sets the test: this tab has to add something to the seven per-event audit
logs the analyst already has, and the addition has to be visible on arrival.
Grouping by occurrence is that addition. It can say attribute 4831022 carries
nine entries and 4828810 carries two — which no per-event log can say, because
each of them only ever sees one occurrence of the value. A day-grouped stream at
a different scope, which is what `H1` is, cannot say it either.

Three things follow from the grouping and are part of the design rather than
consequences of it:

- **Event-level actions get their own section.** Publications and event tags
  belong to the value's story and to no single occurrence. Repeating them inside
  every section would multiply six entries into thirty-six; dropping them would
  lose the publications entirely.
- **The ACL-hidden occurrences are named and not listed.** Four of this value's
  ten occurrences are invisible to the demo viewer. A grouped tab is the only
  one of the four candidates where that absence has an obvious shape — six
  sections where there should be ten — so the footer states it, and states that
  their entry counts are not obtainable either.
- **A section's counts move with the filter.** If the rail hides rows, a header
  still reading `9 entries` over three visible rows is two numbers that
  disagree. Filtered sections read `3 of 9`.

**Grafted from `H1`:** the counted facet rail. `00-shared.md` §5 already built
it, so this is reuse and not new work.

**Grafted from `H1`/`H4`:** the **rename callout**. The `2025-06-14` entry where
occurrence 4830441's value was rewritten from `185.234.219.240` renders with a
warning rule and an explicit line saying why a row in this value's history names
a different value. This is not a stylistic preference — it is §8.2's
`model_title` finding made visible, and it is the one row that explains why the
tab is scoped by attribute id.

**Not taken.** `H3`'s per-organisation grouping is deferred, and the reason is
recorded here because it is not a design objection: it is the best question of
the four and `AuditLogsController::__applyAuditAcl` makes it unanswerable for
anyone who is not a site admin, collapsing three of its four cards to *unnamed
users*. Revisit it as a site-admin view. `H4`'s field-level unpacking is
deferred on cost — it needs `audit_logs.change` decoded for every row at render
time, which is exactly what `fullChange` exists to avoid.

## 2. What the pick costs that the brief assumed it would not

`prd/value-profile-page.md` §8.3 says
`Themed/Overmind/Elements/Logs/timeline.ctp` "is the History tab's body". That
was true of `H1` and is **not true of `H2`**: the shared element groups by
calendar day, and this tab groups by occurrence. So the pick gives up the free
body, and the size estimate in the deck reflects that (M, not S).

What is still reused is the element's **action vocabulary** — the mapping from
each `AuditLog::ACTION_*` constant to a colour and a glyph. Both places must
agree, or the same action reads as two different things on two pages of the same
product.

- Add `app/Lib/Tools/AuditActionMeta.php`: a static map, one entry per action
  constant, returning `['colour' => …, 'icon' => …, 'label' => …]`, plus a
  fallback. The new element reads it.
- `Logs/timeline.ctp` keeps its own inline `$meta` **in this phase**. It has two
  existing callers (`Overmind/AuditLogs/admin_index.ctp:168`,
  `Overmind/Logs/index.ctp:92`), and rewriting a shared element with live
  callers is not fixture-first work. Migrating it onto `AuditActionMeta` is a
  one-line change and the obvious follow-up; until then the class is the source
  of truth for anything new and the duplication is recorded, not hidden.
- The colours in that element are literal hex with light pastel fills
  (`#d1e7dd`, `#cfe2ff`, `#e8d5f5`, …). `AuditActionMeta` resolves through
  `--bs-*-bg-subtle` / `--bs-*-text-emphasis` instead, so the new element is
  theme-aware where the old one is theme-blind. The two therefore will not match
  pixel for pixel, which is the correct trade and is stated rather than
  discovered.

## 3. The tab bar carries no count

The registry has History with no `count` (`§2.5`), and it stays that way. An
audit-entry count would be the *viewer's* count, not the value's — a plain
analyst, an org admin and a site admin get three different numbers for the same
value (§8.2) — and on a default instance it is `0` for a reason that has nothing
to do with the value at all. A number that means three things and is usually
zero is worse than no number.

## 4. Layout

One full-width slot (`right => null`), and the panel owns its own internal row:
the facet rail at `col-lg-3` and the sections at `col-lg-9`.

Not this page's outer 9/3 split. The facet control wires checkboxes to rows by
walking up to the nearest `data-vp-list` region (`00-shared.md` §5.1), so the
rail and the rows have to be inside one container. Putting the rail in the tab's
`right` slot makes it a separate `.ajax-card` that resolves independently, and a
rail whose rows have not loaded yet is a rail wired to nothing.

## 5. Controller

| Action | URL | Renders |
|---|---|---|
| `viewHistory($b64value)` | ajax | `value_history` |

One endpoint. The rail is a partial inside the same template, not a second
request, for the reason in §4.

Register `viewHistory` in `ACLComponent::ACL_LIST`'s `values` block with
`theming_enabled`, alongside the fourteen `00-shared.md` §3.1 added. **Verify it
as a non-site-admin**, or read the entry: §3.1 is explicit that a missing entry
is invisible to whoever is most likely to be testing.

## 6. Templates

```
app/View/Themed/Overmind/Elements/Values/View/
    value_history.ctp        the rail, the occurrence sections, the
                             event-level section, and the states
```

Includes `value_facet_group.ctp` once per facet key (`00-shared.md` §5.2) and
`value_pager.ctp` inside any section over 25 rows.

Entry rows are a new primitive, `.vp-audit-*`, because nothing in the 257-item
inventory is an audit row: `.vp-analyst` is a block with an author and a body,
not a timestamped action with a diff. Four classes, no more —
`.vp-audit-row`, `.vp-audit-act` (the round action glyph),
`.vp-audit-diff` (the field/was/is table) and `.vp-audit-mix` (the
per-occurrence action-mix bar).

## 7. Fixture additions

```php
'history' => [
    'recorded'        => true,   // false renders §9's not-recorded state
    'entries'         => 34,
    'occurrences'     => 6,
    'events'          => 5,
    'hidden'          => 4,      // ACL-hidden occurrences, no counts available
    'first'           => '2024-09-14 00:00:00',
    'last'            => '2025-08-22 08:41:07',
    'facets'          => [
        'action' => [ 'edit' => 11, 'add' => 6, 'tag' => 7, 'remove_tag' => 2,
                      'galaxy' => 3, 'publish' => 4, 'soft_delete' => 1,
                      'undelete' => 0 ],
        'model'  => [ 'Attribute' => 27, 'Event' => 6,
                      'ShadowAttribute' => 1, 'Object' => 0 ],
        'org'    => [ 'CIRCL' => 19, 'CthulhuSPRL.be' => 8,
                      'Team-CIRCL' => 5, 'ORGNAME' => 2 ],
        'actor'  => [ /* resolvable only inside the viewer's org — §8.2 */ ],
    ],
    'groups'          => [ /* one per visible occurrence, newest first */ ],
    'event_entries'   => [ /* publications and event tags */ ],
]
```

Each `groups` entry:

```php
[
    'attribute_id' => 4831022,
    'event_id'     => 1284,
    'event_info'   => 'OSINT - Emotet malspam campaign targeting .lu',
    'org'          => 'CIRCL',
    'deleted'      => false,
    'count'        => 9,
    'last'         => '2025-08-19 21:22:00',
    'mix'          => [ 'edit' => 3, 'add' => 1, 'tag' => 3,
                        'galaxy' => 1, 'publish' => 1 ],
    'entries'      => [ /* rows */ ],
]
```

Each row: `created`, `action`, `model`, `model_id`, `model_title`, `actor`
(nullable — see §8.2), `org`, `request_type`, `change` (an array of
`field / was / is`, or null), and `renamed` (true on the one row §1 calls out).

Every number in the tab is derived from `groups` and `event_entries` at render
time, not written twice. `entries` must equal the sum of the group counts plus
`event_entries`; the facet tallies must equal what the rows carry. Phase 13
established the rule and the reason: two independently written numbers on one
page eventually disagree, and the reader has no way to tell which is wrong.

The other three demo values: the conflicted value gets a populated `history`;
the benign value gets `recorded => true` with zero entries (§9's *empty*, which
is not §9's *not recorded*); the unknown value has no `history` key at all and
keeps the sparse page.

## 8. The panel

**Header.** `34 entries · 6 occurrences, 5 events · 14 Sep 2024 → 22 Aug 2025`,
a free-text filter over the visible rows, and a *By occurrence* / *Expand all*
pair. The grouping control renders as a set with only *By occurrence* enabled —
the other groupings are `H3`'s and `H4`'s, deferred, and a disabled control is
how this page has said "designed, not built" since phase 5.

**The ACL band.** Directly under the header, not at the foot: *You see every
entry on the 4 events your organisation created, and only entries on occurrences
you may read on the other 3. A site admin sees more rows here than you do.*
This is the one page in MISP where a reader can be misled into thinking they are
looking at the whole record, and the sentence costs one line.

**One section per occurrence.** Header row: disclosure chevron, attribute id
(monospace) over `event <id>`, the event info over the creating org, the action
mix bar with `n entries` under it, last activity, and a link out to the
occurrence. Soft-deleted occurrences carry the `deleted` badge the Occurrences
tab already uses. The first section is open; the rest are closed.

**Rows.** Time, action glyph, a title naming what changed, a sub-line with the
model title and any note, the actor and org right-aligned, an `API` chip where
`request_type` says so, and a disclosure for the diff.

**The rename row** renders with a left warning rule and its own sub-line. See
§1.

**Event-level section**, below a rule, with the note from §1 explaining why it
is not inside the groups.

**Footer.** The hidden-occurrence statement from §1.

**The rail.** Four facet groups — Action, Model, Organisation, Actor — through
`value_facet_group.ctp`. Zero-count values render as a dimmed row rather than
vanishing: *undelete 0* tells the reader nothing was ever undeleted, and an
absent row tells them nothing at all. The Actor group carries the note that
foreign-org actors are filed as their organisation because MISP strips the user.

**Facets and sections.** Rows carry `data-vp-facet="action:edit model:Attribute
org:CIRCL"`. The whole panel is the `data-vp-list` region and rows are marked
`data-vp-list-row`, because the rows are not one `tbody`. Two behaviours the
contract does not cover and this panel adds:

1. A section whose rows are all filtered out collapses to its header and reads
   `0 of 9`, greyed. It is not removed — a section disappearing under a filter
   would misreport how many occurrences exist.
2. Section headers read `n of m` while any facet is set, and `m entries` when
   none is. The mix bar is not re-proportioned: it describes the occurrence, not
   the filter, and redrawing it would make the filter look like history.

## 9. States

Five, and the fifth is new to this tab.

1. **Populated** — the malicious and conflicted values.
2. **Not recorded** — `recorded => false`. `MISP.log_new_audit` defaults to
   false (`Server.php:6649`), so this is the state a default instance renders,
   and it is the common case rather than the edge one. It replaces the whole
   panel: a heading saying no history is being recorded *on this instance*, the
   explicit line that this is not the same as nothing having happened to this
   value, the settings path, and a rail card listing what is still knowable
   without the audit log (latest edit per occurrence, first and last
   publication, sightings) with a link to the Timeline tab. It also states that
   enabling it records forward and never reconstructs the past — an analyst who
   turns it on and comes back expecting the last year is owed that sentence
   before they turn it on.
3. **Empty** — recorded, and nothing for this value. Distinct from 2 in wording
   and in what it offers: here the audit log works, so the answer *is* about the
   value.
4. **Hidden by ACL** — the band and the footer. Where every occurrence is
   hidden, the whole panel becomes the suppressed state (`00-shared.md` §8) and
   names the number it cannot show.
5. **Filtered to nothing** — `data-vp-list-empty`. Fires only when a filter
   caused it (`00-shared.md` §5.1).

## 10. Interactions

Progressive enhancement on top of a server-rendered panel, per phase 5:

- Section disclosure, and *Expand all* / *Collapse all* as one toggle.
- Row diff disclosure. Diffs come from the fixture in this phase; live, this is
  where `AuditLogsController::fullChange` is called, because
  `audit_logs.change` is brotli-compressed above 256 bytes
  (`AuditLog::COMPRESS_MIN_LENGTH`) and capped at 64KB.
- Facets, reveals, search and paging: all `00-shared.md` §5, no new JS.
- Free-text filter narrows visible rows and updates the `n of m` counts.
- Everything that would write is absent, not disabled. There is nothing to write
  here: an audit log is a record, and this tab has no verb.

## 11. CSS

`value-profile.css` gains the four `.vp-audit-*` classes from §6 and nothing
else. Colours resolve through `AuditActionMeta`'s tokens (§2); no literal hex
reaches the stylesheet.

## 12. Deferred, and what live data will hit

- **`H3`'s per-organisation view** and **`H4`'s field-level unpacking**, both
  per §1.
- **Converging `Logs/timeline.ctp` onto `AuditActionMeta`**, per §2.
- **Which ACL model.** §8.2 sets out the choice and it is a live-data decision,
  not a template one: the per-event model (`__createEventIndexConditions`) is
  the only one that shows a plain analyst anything useful, and it costs a full
  `fetchEvent()` per event to build the WHERE clause — seven for this value.
  Scoping by `model = 'Attribute' AND model_id IN (…)` is one query and drops
  every event-level action, which this design has a whole section for. The
  fixture is shaped for the per-event model.
- **Scope by id, never by `model_title`.** `AuditLogBehavior.php:65-72` files an
  edit under the value it produced, so a title match would import another
  value's deletions and lose this value's own — and `model_title` is an
  unindexed `text` column, so it is a scan as well as wrong.
- **No pagination across the union.** Each section pages its own rows client-
  side. A server-side Paginator over a union of N event-scoped queries has no
  stable ordering key, and this is where that problem lands.
- **Actor resolution** stays as §8.2 describes it. Nothing this page can do
  changes `__applyAuditAcl`.

## 13. Verification

1. `parallel-lint` over the new PHP and `.ctp`.
2. Load the malicious value's History tab: six sections, the first open, the
   event-level section present, the footer naming four hidden occurrences.
3. Confirm the header count equals the sum of the section counts plus the
   event-level entries, and that each facet tally equals the rows carrying it.
4. Set a facet and confirm every section header switches to `n of m`, that an
   emptied section collapses to a greyed header rather than disappearing, and
   that the mix bars do not move.
5. Clear the facets and confirm the counts return to `m entries` and page one.
6. Confirm the rename row renders its warning rule and its explanation.
7. Set `recorded => false` and confirm state 2, including the rail card and the
   records-forward sentence.
8. Confirm state 3 on the benign value and that its wording differs from state 2.
9. **Load the tab as a non-site-admin user** and confirm it renders at all —
   `00-shared.md` §3.1's trap.
10. Both themes, and confirm no literal hex survives in `.vp-audit-*`.

## 14. Exit criterion

The malicious value's History tab renders six occurrence sections and an
event-level section whose counts provably sum to the header, the facet rail
narrows them without any number in the panel contradicting another, the rename
row explains itself, and flipping `recorded` to false replaces the panel with a
state that a reader cannot mistake for "nothing happened to this value".

## 15. What was built, and what changed on the way

Every §13 item passes. What follows is only what the build decided that the
spec left open, or found to be wrong.

**Every number is derived; §7's are illustrative.** §7 sketches `entries => 34`
with a facet breakdown, and also says every number must be tallied from
`groups` and `event_entries`. Those two cannot both hold, and the second is the
one that matters — so `ValueProfileFixture::history()` counts the rows and the
sketch's totals moved. The C2 value carries **38** entries: 28 across six
occurrence sections and 10 event-level. The differences are all consequences of
agreeing with the rest of the page rather than with the sketch:

- **The publications are the Timeline tab's.** §7 has `publish => 4`;
  `auditPublications()` reads the same `maliciousPublications()` array the
  Timeline draws, which holds a first and a last per event and so yields
  **8** rows over five published events. A second, smaller count of the same
  publications was the one thing this tab could not afford.
- **`publish` is not in a section's mix.** §7's example `mix` includes it,
  which contradicts §1's own rule that event-level actions live in their own
  section. §1 wins; the mixes hold attribute-scoped actions only.
- **`6 occurrences, 6 events`, not 5.** The value's six visible occurrences
  sit on six distinct events. §7's `events => 5` is the number of *published*
  events, which is what the event-level section spans — event 1265 has never
  been published and contributes nothing to it. Both numbers are now derived
  from the rows, so they disagree honestly rather than by accident.
- **The ACL band counts 3 and 3.** §8 writes 4 and 3. The band's numbers are
  now derived from the occurrence set against the viewer's organisation, and
  CIRCL created three of the six events holding a visible occurrence. The
  wording also follows `__createEventIndexConditions` more closely than §8
  did: on someone else's event you see the event-level rows *and* the parts of
  it you may fetch, not only the latter.

**Sixteen actions, six colours.** §2 says `AuditActionMeta` resolves through
`--bs-*-bg-subtle` / `--bs-*-text-emphasis`. Only Bootstrap's eight theme
colours actually have those, and only in both themes — MISP's own palette
entries (`--bs-tag`, `--bs-galaxy`, …) are *referenced* by the component
variants but never defined, in either theme, so they were not candidates. The
class therefore groups the sixteen actions onto six usable colours by the kind
of change — green creates or restores, blue modifies or distributes, cyan
annotates, grey removes an annotation, amber removes reversibly, red removes
for good — and lets the glyph name the action. Two neighbouring segments in a
mix bar can then share a colour, so the segments carry a separator.

**`.vp-audit-row` was not written here.** Phase 15 built it for the Timeline's
chronology and reserved it for this tab in a comment, because both draw the
same shape. So §6's four classes are three new ones plus that reuse, and the
row markup on this tab is that grid's three children. The only adjustment is a
wider first track, scoped to `[data-vp-audit]`: this tab's sections span
months, so a row needs a date as well as a time where the Timeline's chronology
is already grouped by day.

**Zero-count facet rows moved into the shared element.** §8 asks for them;
`value_facet_group.ctp` rendered every row alike. It now dims a zero row and
disables its checkbox, which is additive — the other two callers build their
groups from values that turned up, so none of them has a zero. The group-level
rule is untouched: a group whose total is zero still renders nothing.

**The section behaviour is new JavaScript, and §10 is right that the rest is
not.** Facets, reveals, search and the row set are the shared contract
unchanged. What this panel adds is the two behaviours §8 names — per-section
`n of m` counts, and an emptied section collapsing to a greyed header instead
of vanishing — plus per-section paging, which replaces the list-level
`paginate()` for this list rather than running beside it. One pager over a
union of sections would page rows out of one section to make room for
another's.

**The pager is exercised, not just written.** No section of the C2 value
reaches §6's 25-row threshold, so the conflicted value was given a section that
does: 28 entries on occurrence 5061455, 22 of them `to_ids` set and cleared and
set again over the five weeks to that event's last publication. That is what
this value's conflict looks like written as a record instead of a score, and it
is the only section on the page that pages. Its two occurrences on event 1402
also cover the case no per-event audit log can separate.

### Two defects the build found, both in the geometry

1. **The diff table collapsed to one character per line.** `word-break:
   break-word` puts a cell's minimum content width at one character, and a
   `<table>` shrinks to fit — so a diff on `last_seen` rendered 115px wide and
   436px tall, an ISO timestamp set vertically. The first fix was wrong in an
   instructive way: stretching the table to `width: 100%` did not remove the
   collapse, it moved it to whichever column auto layout then squeezed, and a
   comment broke instead. The fix is `overflow-wrap: break-word`, which allows
   the same break without lowering the minimum, so a column is as wide as its
   longest word and only a word too long for the row is broken at all.
2. **`.vp-audit-row` was already a grid.** The first draft laid the row out
   with its own flex line, not knowing phase 15 had defined the class. The two
   definitions did not collide visibly — the row still looked right — but the
   diff table inside it was a grid item sized to a min-content track, which is
   what made defect 1 reachable at all. Found by printing the parent's
   computed `display`, not by reading either file.

Both were invisible to `php -l`, to the fixture's own consistency check, and to
a reading of the source. They were caught by rendering the panel with MISP's
real stylesheets and real `value-profile.js` in headless Chrome and measuring
`offsetWidth` / `offsetHeight`.

### Verification

- `php -l` over the five changed PHP and `.ctp` files and `node --check` over
  the JavaScript. `parallel-lint` is not installed in this checkout
  (`app/Vendor/` is absent), so §13.1 ran as `php -l`.
- A standalone consistency check over the fixture: for all four values, the
  header equals the sum of the section counts plus the event-level entries,
  every section's mix sums to its own count, each of the four facet groups sums
  to the entry total, every value a row carries has a facet row, sections are
  ordered newest-first and so are the rows inside them.
- 37 structural assertions over the rendered panel HTML, and 37 (C2) / 40
  (conflicted) driven assertions per theme in headless Chrome: section
  disclosure and its `aria-expanded`, expand-all in both directions, the row
  diff opening to a measured height, a facet switching all seven sections to
  `n of m` with each count equal to its own visible rows, an emptied section
  greyed and still occupying space, the mix bars unmoved across filter and
  clear, the header total equal to the sum of the sections, the free-text
  filter, the filtered-to-nothing state, and the 28-row section paging to 25
  and then 3 without touching its neighbours.
- §13.9's trap answered with MISP's own audit rather than a grep:
  `/values/queryACL/findMissingFunctionNames` reports only the controller's
  four private helpers, so `viewHistory` is registered.
- State 2 rendered by flipping `recorded` to false, checked for the rail card
  and the records-forward sentence, and reverted.
- Both themes by computed style: the action glyph resolves to a real fill and a
  contrasting ink in each (`rgb(207, 226, 255)` / `rgb(5, 44, 101)` light,
  `rgb(3, 22, 51)` / `rgb(110, 168, 254)` dark), and the rename row keeps its
  amber rule against both grounds. No `[data-bs-theme="dark"]` block was
  needed and none was written, because every colour resolves through a token
  both themes define — recorded in the stylesheet so the absence reads as a
  decision rather than an omission.
