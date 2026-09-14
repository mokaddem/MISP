# PRD: Value Profile — the two tag scopes fold into one list

**Phase 33.** Opened and built 2026-09-14, the day after
[32](32-tag-scope.md), from four change requests on what that phase
shipped:

> On the overview's "Tags and Galaxies" panel, do not make the split
> with attribute and event tags. Since an attribute inherit a tag from
> it's event, the split doesn't bring much value. Actually, I expect
> 90% of the time attributes won't have any tags. That's a special
> case. So remove the `event` icon you added inside it. Shouldn't have
> told you to make a distinction between like this.
>
> Also regrouping clusters together based on their galaxy would be nice
>
> For the occurrences pane:
> - Also remove the event icon from the tag
> - Only use one Tag filtering facet. No need for "tag" and "event tag"

Phase 32 found the missing labelling and was right about that. What it
got wrong is what it did with it: it drew the finding — *these came
from the event and those from the attribute* — on every surface that
carries a tag, and that distinction is about where MISP stored a row,
not about what anybody said of the value.

---

## 1. Why the split was the wrong shape

**An event's labelling covers the attributes inside it.** `tlp:amber`
on a report is a statement about the indicator in it; MISP's own export
paths inherit it that way. So *tagged on the report* and *tagged on the
indicator* are one statement made at two removes, and a reader asking
*what has been said about this value* wants both in one answer.

**And the attribute scope is the rare one.** Most attributes carry no
tag at all — on the verification instance `8.8.8.8` has 7 distinct
attribute tags against 48 event tags, and 8 of its 26 occurrences carry
none of their own. A split spends a heading, a glyph in every chip and
half a card's height separating a long list from a usually empty one.

The split cost, surface by surface, what phase 32 charged for it:

| Surface | What the split bought | What it cost |
|---|---|---|
| Context card | two sections, each counting in its own unit | a scope heading, a second filter note, **534px** where the card had been 223 |
| Both occurrence tables | a glyph inside every chip of the commoner set | ink in every populated cell, on a distinction the cell cannot act on |
| Facet rail | a second group, `Event tag` | a reader who has to tick two boxes to ask one question |

---

## 2. What the fold costs, and what is not bought back

**The `×N` beside each chip on the context card.** `topTagsFor` counts
the occurrences carrying a tag and `eventTagsFor` counts the events
carrying it, so on `8.8.8.8` `tlp:white` reads **2** against one
denominator and **8** against the other. Their union is a third number
neither read holds — an occurrence tagged `tlp:white` inside an event
tagged `tlp:white` is one thing counted on both sides — and there is no
cheap query that produces it:

- counting it at occurrence scope needs the value's `attribute_tags`
  grouped by *(tag, event)* so the double count can be subtracted,
  which is a second pass over the scan `topTagsFor` already makes —
  **≈450 ms** on `443`, for a number in small print;
- counting it at event scope needs the event-id *sets* rather than the
  counts, same problem on the attribute side;
- taking the larger of the two understates a real union, and summing
  them is [the defect the sightings card was carrying](32-tag-scope.md)
  two days before this.

So `ValueProfile::mergeTagScopes` keeps both counts exactly as they
were read, sorts the merged list on the larger of them — a ranking,
which claims nothing about how many of what — and the card states each
count **in the chip's title**, where it can name its own unit:

> On 8 events this value appears in, and on 2 occurrences of it
> directly

Every `×N` still on this page counts one thing. The facet rail, which
counts *rows*, keeps its numbers: there the two scopes have one
denominator and §3.3 is how it stays exact.

---

## 3. The four surfaces

### 3.1 The context card is one list

`forContext` reads both scopes as before and folds them by tag name
before anything is grouped. A tag applied at both is one entry, marked
`local` only where **every** attachment was local — each reader already
applies that rule inside its own scope, and a tag attached locally on
an occurrence and globally on its event is a globally attached tag.

`value_context_scope.ctp` is gone; it existed only to be called twice.

**The caps collapse into one pair.** `CONTEXT_EVENT_TAG_CAP` (12) and
`CONTEXT_EVENT_GALAXY_CAP` (6) were height decisions for a section that
no longer exists, so the event scope is read at `CONTEXT_TAG_CAP` /
`CONTEXT_GALAXY_CAP` like the other one — free, because `eventTagsFor`
is an indexed `IN` and §6 shows the endpoint got *cheaper*. The merged
list is then cut to the same 60, which is not what capping each read
does: two reads of 60 fold to as many as 120.

`8.8.8.8` now draws 28 labels in 10 taxonomies and 24 clusters where
the split drew 7+12 and 0+6.

### 3.2 Neither occurrence table marks a scope

`Badges/tag.ctp` loses the `scope` parameter and the event glyph, and
is back to what every other caller in MISP renders. `value_tag_list`
still reads both paths, still draws a tag on both **once**, and still
puts the attribute's first so the weaker claim is what folds behind the
`+N` — the ordering is free and the glyph was not.

### 3.3 One `Tag` facet, counted once per row

`event_tag` is gone from `ValueStatsTool::occurrenceFacets` and from
`value_occurrence_table`'s token builder. Both now walk the row's
`AttributeTag` and `EventTag` together and **deduplicate by token**, so
a row whose attribute *and* whose event carry `tlp:white` is one row
matching that filter rather than two bumps of one counter.

That dedupe on both sides is the invariant the rail and the table share;
§7 checks it on 131 facets across four values.

### 3.4 Clusters group under their galaxy

`ValueContextTool::galaxies` returns one entry per galaxy rather than a
flat run, keyed on the galaxy's own name. Flat, the card put a tool, a
threat actor and twenty ATT&CK techniques in one list with every chip
repeating its own kind in small print; the galaxy is what they have in
common, so it heads them and the kind is said once.

**The name is read from inside the cluster.** `fetchGalaxyClusters`
contains `Galaxy` and then `arrangeData` moves it *into* the
`GalaxyCluster` array — read at the top level it is silently absent,
which is how the first build shipped groups headed `threat-actor` and
`tea-matrix` instead of *Threat Actor* and *Tea Matrix*.

---

## 4. Height, again

Folding made the card taller before it made it shorter: one list of ten
taxonomies is ten rows at 49px each whether a row holds one chip or
ten, and `8.8.8.8`'s galaxies went from a capped 6 to 24 across four
galaxies. First measurement of the merged card: **1,009px**, against
the 534 phase 32 left and the 223 before it.

Three bounds bring it back, and each is a statement about the card
rather than about the data:

1. **A row per taxonomy, up to five.** Past that the taxonomies flow
   into one band, each still carrying its own name inline and its
   conflict dot — so what is given up is the column alignment and the
   ordinal scale, not the grouping. Five is where a value's taxonomies
   stop fitting as rows; below it the card draws exactly what it drew
   before it had two scopes to fold, `sage.png` included, whose
   `admiralty-scale` position still renders.
2. **The same budget for galaxies.** `443` is attributed to **nine** of
   them, which as a row each was 550px — more than the whole card was.
3. **Six clusters per galaxy**, then *and N more clusters*. ATT&CK
   alone gives `8.8.8.8` twenty, which was 237px of one galaxy.

No scale is lost to (1) in practice: a position means *one tag of this
dimension*, and a value broad enough to flow carries `tlp:white`,
`tlp:amber` and `tlp:red` from three different reports.

### 4.1 What the pane costs now

| | before 32 | after 32 | **after 33** |
|---|---|---|---|
| Occurrences preview | 433px | 441px | 441px |
| Tags and galaxies | 223px | 534px | **507px** |
| left column, cards summed | 1,418px | 1,730px | **1,702px** |
| rail | 1,451px | 1,451px | 1,451px |
| **the pane** | **1,515px** | **1,794px** | **1,767px** |

The card is 27px shorter than the split it replaces while drawing 28
labels instead of 19 and 24 clusters instead of 6. `443`, the widest
value on the instance, is **627px** — it was 998 before the two
budgets.

---

## 5. What the reader no longer sees

Stated because the page's rule is that a bound is said rather than
inferred:

- **The scope of a label.** A chip no longer says whether the row or
  its report carries it. The context card's title still does, per tag;
  the occurrence tables do not, and that is the request.
- **`×N` on the context card.** §2.
- **Clusters past the sixth of a galaxy**, counted in words at the end
  of the group.

Nothing here is a permission. Every cap on this page is a drawing
decision and §14.6 of the contract is why they are all written down.

---

## 6. What it costs

`forContext`, measured per value with the model re-initialised between
calls:

| value | Q | ms | what the card draws |
|---|---|---|---|
| `8.8.8.8` | 15 | 36 | 10 taxonomies, 4 galaxies, 24 clusters |
| `443` | 9 | 935 | 3 taxonomies (capped at 60), 9 galaxies, 37 clusters |
| `0.0.0.0` | 9 | 463 | 6 taxonomies, 1 galaxy |
| `sage.png` | 8 | 5 | 3 taxonomies |
| `1.162.239.42` | 11 | 6 | 3 taxonomies, 1 galaxy |

**Q drops from 9–18 to 8–15** — one `fetchGalaxyClusters` instead of
two, because there is one merged galaxy-tag list to rule on. The time
is where [32 §5.3](32-tag-scope.md) found it and predates both phases:
`443`'s 935 ms is `topTagsFor`'s pair, and raising the event cap from
12 to 60 cost nothing measurable.

---

## 7. What was checked, live

On the verification instance, signed in as the site admin:

- **No event glyph survives anywhere on the page** — 0 matches for
  `.badge .misp-icon-event` on the Overview and the Occurrences tab, on
  every value below.
- **The rail and the table agree, exactly.** For every tag facet, the
  rail's count equals the number of rows carrying that token:
  `8.8.8.8` 27 facets over 26 rows, `0.0.0.0` 2 over 300, `443` **101**
  over 300, `sage.png` 3 over 1. No mismatch.
- **Filtering narrows and restores.** `tlp:white` on `8.8.8.8` takes
  the table from 26 rows to 8 — the count the rail shows — and
  unticking returns 26.
- **Both card layouts render.** `sage.png` keeps the row-per-taxonomy
  layout and draws its `admiralty-scale` position; `8.8.8.8` and `443`
  flow, with taxonomy names and conflict dots intact.
- **No console or page errors** on any of the five values.
