# PRD: Value Profile — History tab

**Phase 16.** Implements candidate **`H2`** with `H1`'s facet rail grafted,
chosen 2026-08-26.
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
