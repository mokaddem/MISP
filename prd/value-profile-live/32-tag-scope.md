# PRD: Value Profile — the page could only see a seventh of the labelling

**Phase 32.** Opened and built 2026-09-14, from one reading of the
built tab:

> For the occurrence tab and the occurrence widget in the overview
> pane. There's a "tags" column. This column seems to only show tags
> attached to the attribute. It would be valuable to also include tags
> attached to the event as well. Maybe add a small visual element that
> indicates whether it's on the attribute or on the event.
> Do it in both places.
> I guess the "tags and galaxies" panel on the overview will have to be
> updated as well?

The observation is right, the last question answers itself yes, and the
gap is bigger than the column it was noticed in.

> **Partly superseded the next day by
> [33](33-tag-scope-fold.md).** The finding below stands — the page was
> reading a seventh of the labelling, and §2's access argument is still
> what lets the event scope be read at all. What was withdrawn is the
> *distinction* this phase drew with it: the two sections of the
> context card (§4.3), the event glyph inside the chip (§3), and the
> `Event tag` facet (§3.4) are one list, one unmarked chip and one
> `Tag` group. §4.4's height table is superseded by 33 §4.1, and the
> two event-scope caps it set no longer exist.

---

## 1. How much the page could not see

Every tag read on this profile joined `attribute_tags`. Not one of them
touched `event_tags`. Measured on the verification instance:

| | `8.8.8.8` | `443` |
|---|---|---|
| distinct **attribute** tags | 7 | 3,860 |
| distinct **event** tags, across its events | **48** | **246** |
| its events | 20 | 1,844 |

An analyst tags the *report* far more often than the indicator inside
it, so on an ordinary value the page was drawing a **seventh** of what
anybody had said. And it was not a random seventh: the 48 hold every
`tlp:`, the `type:OSINT` marking, `osint:certainty`, the
`event-classification`, and the MITRE ATT&CK clusters.

**The context card was printing the evidence.** Its subtitle read

> 3 taxonomies · **0 galaxy clusters**

on a value whose events are attributed to *Abuse Elevation Control
Mechanism — T1626*, *Code Signing — T1553.002*, *Deobfuscate/Decode
Files or Information — T1140* and three more. Nothing on the page was
wrong about its own scope; the scope was never stated, so *0* read as a
fact about the value.

---

## 2. What may be shown, and why that is not a new question

**An event tag is visible to whoever can see the event.** There is no
per-tag access rule to reproduce:

- `EventTag.local` is an **export** rule, not an access one.
  `excludeLocalTags` is set by `Server::push` and `Sighting`'s sync
  path, defaults to `false` in `EventsController::view`, and is
  otherwise a caller-supplied API parameter. A reader who opens the
  event sees its local tags there, so withholding them here would
  differ from MISP without protecting anything. They are kept and
  marked, exactly as the attribute scope already marks them.
- `Tag.exportable` is likewise not filtered, matching `attachTags`,
  which passes `includeAllTags`. Two tag lists in one table cell
  obeying two different export rules would be a distinction the cell
  cannot draw.

So both new reads are scoped by the rule the page already uses:

- **`Value::eventTagsFor`** builds `topTagsFor`'s scope exactly —
  `buildConditions` over the attributes matching the value,
  `Attribute.deleted = 0`, `Event` and `Object` contained because the
  ACL is not expressible without them — and takes the event ids out of
  it. An event reaches the result only by holding an occurrence the
  reader may already see.
- **`ValueProfile::attachEventTags`** adds nothing at all: every row it
  decorates came back through `fetchAttributesSimple` under
  `buildConditions`, so its event is one the reader may open.

§9's live probe re-runs under a second reader and the 163 checks hold.

---

## 3. The four surfaces

Three were asked for. The fourth would have been left contradicting the
first.

### 3.1 The two occurrence tables

`Fields/tag_list` → **`Fields/value_tag_list`**, new, in both
`value_occurrences` (Overview preview) and `value_occurrence_table`
(tab).

- **Attribute tags first, then the event's.** The order is the strength
  of the claim: a tag on this row is about this occurrence, a tag on its
  event is about the report it arrived in. When the cap bites it is the
  weaker set that folds behind the `+N`.
- **A tag on both scopes is drawn once, as the attribute's.**
  `tlp:white` on the attribute and on its event is one statement made
  twice; two chips would read as two sources agreeing.
- **Galaxy tags stay skipped in both scopes**, which is
  `Fields/tag_list`'s own rule. A cluster is not a label and the page
  draws it as a cluster — in the context card, which gained the event
  scope in the same pass.

**The marker is `Badges/tag`'s, and only the event side carries one.**
An optional `scope` parameter draws MISP's own event glyph inside the
chip, in the chip's text colour, so it cannot fight the tag's palette.
Absent — every caller but this page — the badge renders exactly as
before. Marking one side rather than both halves the ink and costs
nothing: an attribute tag is what this column has always drawn. It is
the same asymmetry `local` already uses one line below.

### 3.2 The facet rail — not asked for, and it had to move

The tab's rail counts a **Tag** facet over the rows. Left alone it would
have counted `tlp:white` as **2** beside a column now showing it on
**ten** rows — a rail disagreeing with the table next to it, which is
the single thing `forOccurrenceTable` being one fetch exists to
prevent.

So the rail gained an **Event tag** group, and the rows gained
`event_tag:` tokens. A separate key rather than the same one, because
*this occurrence is labelled X* and *this occurrence arrived in a report
labelled X* are two questions; one group answering both would count rows
for two reasons and name one. On `8.8.8.8` the rail now reads
`tlp:white 2` under Tag and `tlp:white 8` under Event tag — which is the
blindness, stated.

Verified end to end: 22 event-tag facets, ticking `tlp:white` narrows
26 rows to 8 — the count the rail predicted — and clearing restores 26.

### 3.3 The context card

Two scopes, drawn apart, because **the count means two different
things**: `topTagsFor` counts the occurrences carrying a tag,
`eventTagsFor` the events carrying it, so `tlp:white` is ×2 above and
×8 below. One list under one `×N` column would put one glyph over two
denominators — the defect the sightings card was carrying the day
before this was written. Each scope's tooltip names its own unit.

The subtitle splits with them: *3 taxonomies on its occurrences · 7 on
its events · 6 galaxy clusters*. That makes the old *0 galaxy clusters*
a fact about a scope rather than about the value.

**It does not move the Assessment, and now it does not appear to
argue with it either.** `reporting.attribution` scores *no galaxy on any
occurrence* and still means exactly that — a cluster on the event is a
weaker claim about the value and is not evidence the engine was asked
for. Before this phase a reader comparing the two panels saw *0 galaxy
clusters* and a signal saying the same thing, agreeing for the wrong
reason. Now they see clusters here and *no galaxy on any occurrence*
there, and the card's own headings say why both are true.

---

## 4. Height, which is where most of the work went

This phase adds content to a page the phase before it spent entirely on
removing content. Every surface was measured before and after.

### 4.1 The preview card: 433 → 756 → **441**

Four chips in a 211px cell wrap one per line, so a 37px row became
100px and the card went **433px → 756px** — undoing, in one column, the
whole of what shortened it.

Three things brought it back:

1. **`max_visible` is the caller's.** The tab shows four, which is
   `Fields/tag_list`'s own number and right for a table a reader came to
   read. The preview shows **one** and a `+N`.
2. **A chip is one line.** `Badges/tag` writes
   `white-space:normal; word-wrap:break-word` into the chip's *inline
   style attribute*, which no stylesheet rule can outrank — so the first
   cap only narrowed the chip and let it wrap inside the new width, a
   176px chip 34px tall. `!important` was the fix and the comment says
   why it is there.
3. **A chip has a widest.** 9rem in the preview, so chip plus `+N` fits
   one line.

**441px**, against 433 before the column carried two scopes.

### 4.2 The tab's table: max row 388 → **137**

`table-layout: auto` fields nine columns in 1,174px and gave Tags
**70px** — narrow enough that every chip wrapped into a vertical stack
whatever its own width. Median row 132px, tallest **388px**, table
4,648px.

A `min-width: 13rem` floor on the column fixes it: tallest **137px**,
median **74**, table **2,405px** — *shorter than before this phase*,
because the untagged rows stopped being dragged by the tagged ones. It
costs 142px more horizontal scroll on a table that already scrolls and
carries a column chooser.

### 4.3 The context card: 223 → 1,060 → **534**

Grouped by taxonomy, the event scope is ten rows of name-plus-body where
the occurrence scope is three. The card rendered at **1,060px** and took
the pane from 1,515 to 2,320.

**The event scope renders flat** — one cloud of chips ordered by how
many events carry them — and its caps are its own:
`CONTEXT_EVENT_TAG_CAP` 12, `CONTEXT_EVENT_GALAXY_CAP` 6, against 60 and
40 at occurrence scope. **534px.**

What flat gives up is the per-taxonomy scale and the inline conflict
dot. The scales were already withheld on a capped read for a stated
reason and every event-scope read is capped; the conflict **dot travels
with the chip** instead, so the header's *1 in conflict* still has
somewhere to point. On `8.8.8.8` that is the `tlp` taxonomy, whose
events say white, clear and amber.

### 4.4 What the pane costs

| | before | after |
|---|---|---|
| Occurrences preview | 433px | 441px |
| Tags and galaxies | 223px | 534px |
| left column, cards summed | 1,418px | 1,730px |
| rail | 1,451px | 1,451px |
| **the pane** | **1,515px** | **1,794px** |

**+279px, and it is stated rather than hidden.** The phase before this
one shortened a card that was *redundant* — twenty-five rows of a table
available in full one tab over. This lengthens one that was
*incomplete*: the 12 event tags and 6 clusters it now draws are not
reachable from anywhere else on the Overview. The levers, if that trade
is wrong, are the two caps in §4.3 and they are one constant each.

---

## 5. Two performance findings

### 5.1 A join before a grouping multiplies by the wrong thing

`eventTagsFor` was first written the obvious way: join `attributes` to
`event_tags` on `event_id`, group by tag. That multiplies every event's
tags by every occurrence the value has in that event — and `443` has
48,255 occurrences across 1,844 events. The pair of calls `forContext`
makes measured **≈650 ms**.

Asked as *which events*, then *which tags on those events*, the
multiplication never happens: the first is the indexed aggregate every
other count on this page runs, the second an `IN` against `event_tags`'
own index. **The `IN` list is bounded by the value's events, not its
occurrences** — 1,844 against 48,255 — which is what makes it safe.

### 5.2 And the same scope was being read twice

`forContext` calls `eventTagsFor` twice, once for plain tags and once
for galaxies, and both need the same event-id scope. Unmemoised that is
the same aggregate over the same occurrences twice: on `0.0.0.0`,
**213 ms and 175 ms** for two reads whose tag halves are milliseconds.

`taggableEventIdsFor` memoises one entry, keyed on the reader as well as
the value — the scope is `buildConditions`' and two readers do not have
the same one — with `galaxy` dropped from the key, since it narrows the
tags and never the events. The second call drops to **8 ms** on `443`
and **30 ms** on `0.0.0.0`.

### 5.3 Where the card's time actually goes

| read | `8.8.8.8` | `443` | `0.0.0.0` |
|---|---|---|---|
| `topTagsFor` ×2 — **pre-existing** | 21 ms | **916 ms** | 216 ms |
| `eventTagsFor` ×2 — **this phase** | 3 ms | **129 ms** | 245 ms |

The attribute scope is where `viewContext` spends its time and did
before any of this. It is not this phase's to fix and is not fixed here.

---

## 6. The board

[`00-contract.md`](00-contract.md) §14.12 amends three rows. `Q` is
re-measured by [`32-tag-scope-count.php`](32-tag-scope-count.php).

- **`viewOccurrences`** — Q 5–7 → **6–7**; one `EventTag` fetch for
  every event on the page, never one per row.
- **`viewOccurrenceTable`** — Q 10 → **7–9**.
- **`viewContext`** — Q 4 → **9–18**, and it scales with the reader's
  taxonomy count rather than with the value. §5.3 has where the time
  goes.

---

## 7. Verification

- [`32-tag-scope-check.py`](32-tag-scope-check.py) — **24 checks**, new.
  Both tables draw event-scope chips and respect their own cap; no tag
  is drawn twice in a cell; the rail carries the Event tag group and the
  rows carry its tokens; one tag appears in both groups keyed apart;
  the context card heads both scopes, counts them apart, draws clusters,
  and names each unit; a value nobody tagged grows no scope headings.
- [`31-panel-check.py`](31-panel-check.py) — 102 checks, unchanged.
- [`29-overview-harness.php`](29-overview-harness.php) — 65 checks.
- [`29-overview-live-probe.php`](29-overview-live-probe.php) — **163
  checks under two readers**, which is the one that matters here: the
  new scope is read through the same ACL construction, and a CIRCL org
  admin seeing 45 of `443`'s 1,844 events still sees no more than a site
  admin.
- [`27-panel-regression.py`](27-panel-regression.py) — the seven panels
  sharing code, two values, all 200.
- Rendered and measured in a real browser, five values, **both themes**.
  The facet filter exercised end to end.
