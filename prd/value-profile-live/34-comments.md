# PRD: Value Profile — the comment column becomes a table

**Phase 34.** Opened and built 2026-09-14, from two change requests on
the Collaboration tab:

> Collaboration tab:
> - Add the "comment" field from the Attributes into it's own table
> - Then, do a UI pass. Most of the panels take too much space for what
>   they show. I'm sure we could make that page shorter in height.

The two are one phase because the first answers the second. The tab
spent 2,466px on `8.8.8.8` rendering seven notes, eight reports and a
disabled form, and the thing that was missing from it renders a fact in
a 30px row. Building the table set the bar the other three panels are
now measured against.

---

## 1. The column nobody was reading

`attributes.comment` is free text an analyst types beside an indicator.
**2,108,595 of this instance's attributes carry one**, against 75 notes
and 43 opinions in the two tables the tab was built on. It is, by three
orders of magnitude, the most common form of analyst writing MISP
holds, and the Value Profile page did not show it anywhere.

It is also the only such writing that is **about the value**. Every
other item on this tab is anchored to a uuid — a note on an event, an
opinion on a note, a report on an event — and each spends a chip per row
saying which container it is really about, because
[`26-analyst.md`](26-analyst.md) §5 established that *a value is not a
valid analyst-data target*. A comment is a column on the occurrence. For
once the sentence *somebody wrote this about this value* needs no
qualification at all.

### 1.1 Why it is a table and not more thread items

Because the same sentence is written hundreds of times.
`94.98.224.81` carries

> Xtreme RAT botnet C2 server (confidence level: 100%)

on **1,459 occurrences across five events** — one thing somebody wrote,
stamped onto every row by a feed. A row per occurrence is 1,459
identical lines; the interesting fact is the 1,459 itself, and a number
beside a sentence is a column.

The shape is the instance's, not one value's:

| value | commented occurrences | distinct sentences |
|---|---|---|
| `94.98.224.81` | 1,459 | 1 |
| `193.161.193.99` | 336 | 33 |
| `147.185.221.29` | 39 | 11 |
| `8.8.8.8` | 26 occurrences, 3 commented | 3 |

So `Value::commentsFor` is a **grouped aggregate**, not a row fetch, and
the table is a list of statements with counts.

### 1.2 Grouped on the sentence *and* the organisation

A comment has no author column. Its only attribution is the `orgc_id`
of the event its attribute sits in — so two organisations writing the
same sentence are two statements, not one made twice, and grouping on
the text alone would have merged them under whichever org the aggregate
happened to keep.

The grouping is byte-exact. `attributes.comment` collates `utf8mb3_bin`,
so `Blocked` and `blocked` are two rows here. That is the honest
reading: the panel reports what was written, and a case fold would be
the page deciding two people wrote the same thing.

### 1.3 Three things the panel refuses to imply

| The column says | Not | Because |
|---|---|---|
| **Event's org** | *Author* | `attributes` has no author column for a comment. The event's creator org is the only attribution there is |
| **Row last written** | *Written on* | The only date is `attributes.timestamp`, a row write that any later edit to any other column moves |
| **Rows** / **Events** | *Sightings* | They count occurrences carrying the sentence, over every occurrence the reader can see — never over the rows in the table |

The date deserves the extra note. Every other date on this page runs
through `Value::OBSERVED_AT`, which reads `last_seen` first because it
answers *when was this value observed*. A comment is not an
observation — it is text in a column — so this one read is deliberately
`Attribute.timestamp` and nothing else. Reporting a declared sighting
date as when somebody wrote a sentence would have been the page
inventing an authorship date it does not have.

---

## 2. The engine

Two methods on `Value`, because both name `value1`/`value2` and §14.3
of [`00-contract.md`](00-contract.md) is that no other file may:

- **`commentsFor`** — the grouped read. `GROUP BY Attribute.comment,
  Event.orgc_id`, ordered by the group's newest row, limited to
  `ANALYST_COMMENT_CAP`. Per group it returns the exact occurrence and
  event counts, the first and last row writes, whether *every* carrier
  is soft-deleted (`MIN(Attribute.deleted)` — a sentence still live on
  one occurrence is live), and one event id to open.
- **`commentSummaryFor`** — the denominators a capped list must not be
  asked for: how many distinct sentences exist, how many rows carry one,
  across how many events. `COUNT(DISTINCT comment, orgc_id)` is the
  same pair the grouped read groups on, so the header's total and the
  rows under it cannot disagree.

**The limit bounds the groups drawn, never the work done.** MariaDB
reads and groups every matching row before the limit applies, which is
why the exact total rides along in the other aggregate rather than being
inferred from asking for one group more — the trick `ownTagsFor` uses,
and the one that does not work when what is capped is groups.

### 2.1 The event the row links to

`SUBSTRING_INDEX(GROUP_CONCAT(Event.id ORDER BY Attribute.timestamp
DESC), ',', 1)` — the event of the group's most recent occurrence, so
*Latest in* lands where the sentence was last written rather than on
whichever row the aggregate happened to keep. `GROUP_CONCAT` truncates
at `group_concat_max_len` from the tail and this reads the head, so a
group spanning 203 events answers as exactly as one spanning two.

`ValueProfile::commentEventNames` then resolves the drawn ids through
`fetchSimpleEvents` — one call for the whole table, §14.4's commitment
that nothing is ever one call per row — which buys the event's `info`
for the chip's title and a second pass of `createEventConditions` over
ids that already came from an ACL'd read.

### 2.2 What it costs

`forAnalystComments`, model re-initialised between calls
(`34-comments-count.php`):

| value | Q | ms | what the table draws |
|---|---|---|---|
| `8.8.8.8` | 4 | 19 | 3 rows of 3, on 3 occurrences in 3 events |
| `147.185.221.29` | 4 | 5 | 11 of 11, on 39 occurrences in 29 events |
| `193.161.193.99` | 4 | 15 | 33 of 33, on 336 occurrences in 203 events |
| `94.98.224.81` | 4 | 32 | 1 of 1, on **1,459** occurrences in 5 events |
| `flood` | **2** | **226** | nothing — 65,717 occurrences, no comment |
| `a2c9…2247` | 4 | 2 | 1 of 1, a **921-character** comment |

**Four queries, or two when there is nothing**: the two aggregates, then
the organisation list and the event resolve, both skipped on an empty
table. It is by a wide margin the cheapest panel on the tab — its
neighbours cost 7 to 28 — which is why it is third on it rather than
last.

`flood`'s 226 ms is the one number worth naming: 65,717 occurrences
matched by the index and then filtered on a `TEXT` column with no index
of its own, producing nothing. It is the same scan shape the occurrence
table already pays on that value and the panel cannot be cheaper than
finding out there is nothing to draw.

### 2.3 The cap

`ANALYST_COMMENT_CAP = 50`, the report list's number for the report
list's reason: the panel is a list of what was written rather than a
table with a rail, so it has nothing to narrow itself with and a stated
remainder is what an unbounded read would owe the reader anyway.

The cap counts **sentences**, never occurrences — which is the whole
point of grouping. The instance's busiest value by commented rows fills
one row of fifty; its busiest by sentences fills 33. Nothing on the
instance reaches the cap, so the remainder line was rendered against a
cap temporarily lowered to five, which is the state §6 records.

---

## 3. The table

| Comment | Rows | Events | Latest in | Event's org | Row last written |
|---|---|---|---|---|---|
| XWorm botnet C2 server (confidence level: 100%) | 16 | 14 | `#3870` | abuse.ch | 2026-02-14 · 7 months ago |
| NjRAT botnet C2 server (confidence level: 100%) | 6 | 6 | `#3664` | abuse.ch | 2025-11-26 · 10 months ago |

**`table-layout: fixed`, and the sentence truncates rather than wraps.**
`auto` measures the longest comment on the value and hands it the table;
the instance holds a 9,232-character comment, and the longest on a value
whose page can be opened is 921. A five-row table becomes a
five-paragraph one. The full text is in the cell's title, and the panel
is a scan of what was written rather than a reader for it — every other
column is sized, so the sentence takes exactly the remainder.

**The date is paired with its age**, `2026-02-14 · 7 months ago`, which
is what the ledger one panel up already does: the date answers *which
day* and the age answers *is this current*. The column is 12.5rem
because the instance holds `2017-08-05 109 months ago` and at 10.5 it
clipped to `109 months ag`, which reads as a rendering fault rather than
a narrow column.

---

## 4. What was checked, live

On the verification instance, signed in as the site admin:

- **Every count in the table matches the database, in the same order.**
  `147.185.221.29`'s eleven rows against the same `GROUP BY` run
  directly: 16/14, 6/6, 1/1, 1/1, 1/1, 1/1, 3/3, 2/2, 4/2, 3/3, 1/1 —
  row for row. Its first and last dates render `2026-02-14` and
  `2025-06-30` against raw timestamps 1771082592 and 1751262468. The
  header's *11 distinct comments on 39 occurrences in 29 events*
  matches `COUNT(DISTINCT comment, orgc_id)`, `COUNT(DISTINCT id)` and
  `COUNT(DISTINCT event_id)` over the same predicate.
- **The cap states a remainder and keeps its counts exact.** With
  `ANALYST_COMMENT_CAP` temporarily at 5, `193.161.193.99` renders
  *Showing the 5 most recently written of 33 distinct comments* over
  rows whose own counts are still over all 336 occurrences. Reverted.
- **A 921-character comment renders in a 30px row** with no horizontal
  page overflow — `scrollWidth` 1600 against `clientWidth` 1600.
- **No cell clips what it holds.** Across four values and 48 rows, every
  column but the comment fits its content exactly; the comment is the
  one that is meant to truncate.
- **No ACL entry is missing.** `/values/queryACL/findMissingFunctionNames`
  returns `[]`.
- **Every endpoint on the tab answers 200** with no PHP error, on eight
  values including `443` (48,255 occurrences) and `flood` (65,717).

---

## 5. Board

[`00-contract.md`](00-contract.md) §14.12 gains a row:

| Collaboration | `viewAnalystComments` | `value_analyst_comments` | 2–4 | nothing — two grouped aggregates, then one organisation list and one event resolve over what is drawn | 2, both aggregates | **34** |
