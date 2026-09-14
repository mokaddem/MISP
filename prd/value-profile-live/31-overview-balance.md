# PRD: Value Profile — the Overview stops being an occurrence table

**Phase 31**, the first phase after the campaign's own close. Phase 29
took the Overview live and [§14.10](29-overview.md) recorded what that
left; this is the first thing anyone said about the result once it was
on a screen, and it was not about correctness:

> The Occurrences panel takes so much place, it's almost worth the
> overview pane. Make it shorter, and investigate other small widget you
> could bring from other pane.

Two tasks and they are one task. The card is too big **because it is
answering questions a table is the wrong shape for**, and shrinking it
without answering them differently would take information off the page
rather than move it.

**Opened and built 2026-09-14**, against the live instance, measured in
a real browser at 1600×1000 before and after.

---

## 1. The measurement, before anything was changed

`8.8.8.8`, Overview tab, 1600×1000, light theme:

| Card | Column | Height |
|---|---|---|
| **Occurrences** | left, col-lg-9 | **792px** |
| Tags and galaxies | left | 223px |
| Analyst data | left | 468px |
| Assessment | rail, col-lg-3 | 375px |
| Sightings | rail | 453px |
| Lifecycle | rail | 255px |
| External presence | rail | 383px |
| **left column**, its cards summed | | **1483px** |
| **rail**, its cards summed | | **1466px** |
| **the pane** | | **1531px** |

So the complaint is exact rather than impressionistic: **the occurrence
card was 52% of the column it sits in** and 32% of the pane, and it was
the largest thing on a tab whose job is to be a summary.

Three things made it that tall, and only the first is about the number
of rows:

1. **25 rows.** `OVERVIEW_OCCURRENCE_CAP`, set by phase 29 §5.1 with the
   argument that a card with no pager has nothing arguing for a *larger*
   number. Nothing argued for that one either.
2. **95px a row.** The `Event` column renders `Badges/event`, which is a
   bordered block with a tinted header strip over the event's info — two
   lines and two borders. It is the right rendering on an index whose
   subject is the event. On eight rows about a value it is the row
   height.
3. **A 70vh clip.** `.vp-panel .table-scroll` caps the table, so past
   about seven rows the card **scrolled internally** — a summary a
   reader had to scroll, inside a page they were already scrolling.

And under it, a `multi_select_toolbar` with six mass actions, every one
of them rendered disabled.

### 1.1 What the rows were being read for

A reader does not scroll twenty-five rows of a preview to read
twenty-five rows. Watching what the card is *for* — the columns it
carries and the ones the tab has that it dropped — it is being read for
two things it cannot answer:

- **Who reports this value, and how much of it is one source?** By
  counting the `Reported by` column. Over a capped sample, which is not
  the value.
- **Is this current, or is this an old record?** By reading down `Last
  seen`, a column that on this instance is `Not set` on **26 of 26**
  occurrences of the flagship value.

Both are one grouped aggregate each, and **both are already drawn
elsewhere on this page** — on tabs a reader has to know exist.

---

## 2. The decisions, taken before building

**D1 — The cap goes to 8, not to 12 or 15.** The card is a sample with
*Open full table* under it. Once the sample is not the value, its size
is set by what fills the card without taking the page; eight rows at the
compact height is ~300px of table, which is the same order as the
Reporting card beside it and the Tags card under it. The header still
prints the uncapped total, so the cap narrows what is shown and never
what is claimed — phase 29 §5.1's rule, unchanged.

**D2 — The event badge is not used here, and it is not changed there.**
`Badges/event` is shared with every attribute index in MISP and it is
correct on all of them. A compact one-line reference is a *different*
rendering for a different job, so it is a new field element
(`value_event_ref`) rather than a flag on the shared one. The
Occurrences tab keeps the badge: it has the width, and the reader went
there for the rows.

**D3 — The mass-action toolbar is deleted rather than disabled.** This
is phase 29's D23 applied again — *the page offers only what is
implemented* — and it has a second reason of its own here. A selection
made on this card could only ever be a selection of eight of a value's
twenty-six rows, chosen by recency, with nothing to do with it. The
toolbar belongs to the tab where every row is present, sortable and
paged, and it is still there.

**D4 — `Last seen` goes with them, and it is the one cut that removes
information.** The fact strip above the tabs already prints this value's
first and last seen, and *when* is now the Reporting card's entire left
half. The column was also 104px of a table that was 13px too wide for
its card. A preview of eight rows is answering *where*.

**D5 — The new panel goes in the left column, and the rail is not
touched.** The rail's four cards summed to 1466px against the left
column's 1483 — the two were within 2% of each other — so **the rail,
not the occurrence card, is what sets this tab's height** the moment the
card is shortened. Anything added to the rail makes the Overview taller,
which is the opposite of the ask. §5 names the candidate that lost on
exactly this and nothing else.

**D6 — One panel, not two.** *Who* and *when* are two summaries, and
they are two halves of one card rather than two cards, because neither
fills a col-lg-9 on its own and both answer *where did this value come
from*. One endpoint, one fetch, one header carrying both denominators.

**D7 — Both halves read the method the owning tab reads.** The split
calls `Value::orgStanceFor` and the stance word comes from
`ValueProfile::verdictStanceWord`, which is what the Assessment tab's
*Who says what* uses; the strip calls `Value::activityMonthsFor`, which
is what `lifecycle.continuity` scores and the Timeline tab draws. A
reader who opens either tab must find the same numbers there. Counting
rows here instead would have been cheaper to write and would have
produced a third answer.

---

## 3. What was built

### 3.1 The occurrence card — `value_occurrences`

- `OVERVIEW_OCCURRENCE_CAP` **25 → 8**.
- `Fields/event` → **`Fields/value_event_ref`**, new: the id as the
  anchor and the info beside it, truncated rather than wrapped so a long
  event title cannot decide the row height.
- The **checkbox column** and the **`multi_select_toolbar`** are gone,
  and the element's `$noWrites` string with them — it has no write
  control left.
- The **`Last seen`** column is gone (D4). Seven columns, from nine.
- A density rule scoped to `[data-vp-occurrences]` and nowhere else:
  5px cell padding, and the type badge trimmed to the line it contains.

That last one is worth stating plainly because it was the surprise. With
the event reference on one line the row was still **51px**, and the
reason was the `Type` column: MISP's type badge is a bordered box that
measured **34px** against the 18–26px every other cell in the row drew,
so it — not the text — was setting the row height. Trimmed, the row is
**37px**.

There is a second finding underneath it. Before the badge was trimmed
the table was **1187px wide inside a 1174px card**, which squeezed
`Type` to 55px, which broke `ip-dst` across two lines, which made the
box 58px, which made the row 75px. A 13px overflow was costing 24px a
row. `white-space: nowrap` on `.vp-panel .idx-col-type` and the `Last
seen` cut fixed the width; the table is 1174px in a 1174px card now.

### 3.2 The reporting card — `value_reporting`, new

`ValuesController::viewReporting` → `ValueProfile::forReporting`.

**Left half — *Reported in*.** A bar a month over the value's whole
life, oldest first, with the silent months drawn rather than skipped:
`activityMonthsFor` answers a `GROUP BY month`, so a quiet month is
simply absent from its result, and a strip drawn straight off those keys
would show a value reported every month of its life however long it went
quiet. `ValueProfile::reportingMonths` materialises the gaps. The run is
the reading — it is what `lifecycle.continuity` scores and what the
Assessment tab prints as *4 months without a month of silence*.

**Right half — *Occurrences by organisation*.** Up to
`REPORTING_ORG_CAP` (6) organisations, most first, each with a bar and
its `to_ids` stance in the Assessment tab's own vocabulary — `yes`,
`no`, `mixed`, `none`. `mixed` gets its own mark rather than a shade
between the other two: an organisation holding the value both ways has
not made half a decision, it has made none.

**The header carries both denominators**, because both are read as *out
of what*: *Reported in 16 of 52 months · 26 live occurrences from 8
organisations*.

**`live` is doing work in that sentence.** `orgStanceFor` excludes
soft-deleted rows and the fact strip's occurrence total does not, so the
two are different numbers on a value with soft-deleted occurrences. The
split's denominator is the sum of its own rows — what the bar actually
divides — and printing the strip's total here would claim the bar
accounts for rows it left out.

---

## 4. What it cost, measured

`31-query-count.php`, five values, model re-initialised per measurement:

| | `8.8.8.8` | `443` | `0.0.0.0` | `sage.png` | `1.162.239.42` |
|---|---|---|---|---|---|
| `forReporting` Q | 3 | 3 | 3 | 3 | 3 |
| `forReporting` ms | 16 | **347** | 281 | 2 | 1 |
| `forOccurrences` Q **(was)** | **5** (9) | 5 (6) | 5 (5) | 7 (6) | 6 (6) |
| `forOccurrences` ms | 8 | 223 | 161 | 8 | 5 |

Two things in that table are worth naming.

**`forReporting` is three queries flat** — the stance aggregate, the
organisation names behind it, and the month aggregate — and it stays
three on a value with 48,255 occurrences, because both reads are grouped
aggregates over the value's own indexed rows. 347ms on `443` makes it
the slowest panel on the Overview for that one value; it is lazily
loaded beside seven others and holds none of them up.

**The cap cut bought queries as well as pixels.** `forOccurrences` went
from **9 to 5** on the flagship, and the reason is
[§11](29-overview.md)'s deferred item: `attachCreatorOrgs` issues one
select per distinct organisation among the rows it was handed, so the
cost of that N+1 is bounded by the cap — and the cap just fell by two
thirds. Eight rows of `8.8.8.8` carry four organisations where
twenty-five carried eight. The defect is still MISP-wide and still not
this page's to fix; this phase made it cheaper by accident and the
number is recorded so the board is not carrying a stale 9.

---

## 5. What was weighed and not built

**The Assessment card's *What would change this*, on the Overview
rail.** Three lines naming what would move the assessment, already
computed: `viewVerdictCard` calls `forVerdict`, which fills
`verdict['changers']`, and the card renders three of the ledger's
signals and throws the rest away. Adding the falsification lines to it
is **free** — no query, no endpoint, one element include.

It is not built, and the reason is D5 rather than doubt about its value.
It is worth about 100px, and it would land on the **rail**, which is
this tab's tallest column once the occurrence card is shortened. The ask
was that the Overview is too tall. Buying a good widget by making the
pane taller answers a different complaint from the one that was made.

It stays the best thing still on the table for this tab, and the way to
take it is to find the rail 100px rather than to add 100px to the rail.

**The occurrence facet rail**, condensed from the Occurrences tab. Its
counts are computed by `ValueStatsTool::occurrenceFacets` over the rows
the tab fetched, capped at 300 — so on the Overview, at a cap of 8, a
facet rail would be a count of eight rows presented as a summary of a
value. Making it true means the aggregate the tab does not run either.

**Anything from the Relationships tab.** The neighbourhood is a scan of
up to 20,000 attribute rows and a second of wall clock on `8.8.8.8`;
[`24b`](24b-relationships.md) refuses it for the tab's own badge. The
one cheap relationship fact — `objectCountFor`, already on the tab
bar — is a card that would say *15 objects* and nothing else.

---

## 6. Verification, as it ran

Against the live instance, logged in as a real session, measured with
`getBoundingClientRect` rather than read from the source.

**Layout, `8.8.8.8`, 1600×1000:**

| | before | after |
|---|---|---|
| Occurrences card | 792px | **433px** |
| …as a share of its column | 52% | **28%** |
| row height | 95px | **37px** |
| rows | 25, inner-scrolled at 70vh | 8, no inner scroll |
| Reporting card | — | 280px |
| left column, cards summed | 1483px | **1404px** |
| rail, cards summed | 1466px | 1466px |
| **pane** | **1531px** | **1531px** |

The pane is the same height and carries one more card.

**Five values, both themes, checked for HTTP errors, horizontal
overflow and empty states:**

- `8.8.8.8` — 52 months, 8 organisations, 6 shown, *2 further not
  shown*.
- `443` — 112 months, 12 organisations, one holding 47,469 of 48,255.
  The strip is a decade of reporting with a shape; the split's tail is a
  sliver, which is what 390 out of 48,255 looks like and is not
  corrected.
- `0.0.0.0` — 128 months, 4 organisations, all shown.
- `sage.png` — **one month, one organisation.** This is the branch that
  changed the drawing: at `flex: 1 1 0` a single month drew a 560×120
  slab of solid green, which is not a chart of anything. Capped at 22px
  a bar, one month is one bar and the empty axis beside it is the
  reading. The axis also prints **one** label where the span is one
  month — `2017-01 … 2017-01` is a range that does not range.
- `1.162.239.42` — one occurrence, nothing else.

**ACL:** `queryACL/findMissingFunctionNames` returns `[]` with
`viewReporting` in place.

**Harnesses, all green:**

- [`31-panel-check.py`](31-panel-check.py) — **90 checks**, new. The two
  panels over HTTP on six values, asserting *what the fragment
  contains* rather than only that it answered 200: at most eight rows,
  no toolbar, no selection column, no `Last seen`, the compact
  reference used and the badge not, the split capped at six with a
  stance on every row, both denominators in the header, and an axis
  that does not print one month twice.
- [`29-overview-harness.php`](29-overview-harness.php) — 65 checks, and
  it still passes: its `Last seen` assertions are about the **fact
  strip**, not the column this phase took off the table.
- [`29-overview-live-probe.php`](29-overview-live-probe.php) — 163
  checks under two readers' permissions, including `shown <= total`,
  which the new cap does not disturb.
- [`27-panel-regression.py`](27-panel-regression.py) — the seven panels
  sharing code with the ones touched here, on two values, all 200.

**Both themes:** light and dark, the latter through `localStorage
darkMode` as the layout sets it, not through `prefers-color-scheme`
(which MISP does not read). The stance chips, the month bars and the
event reference resolve their tokens in both.

### 6.1 Two defects the build found

**The organisation slice was not deterministic.** `orgStanceFor` orders
by occurrence count and nothing else, and five of `8.8.8.8`'s
organisations hold the value **once each** — so which of them survived
`REPORTING_ORG_CAP` changed between two loads of the same page, with *2
further organisations not shown* underneath naming a different two each
time. MariaDB is entitled to that; a card is not. Sorted by count then
name, which is the tiebreak `contextOrgs` already settles its own ties
with — so the two cards listing one value's organisations cannot list
them in two orders.

**The type filter's empty state became a lie.** A banner type chip
narrows this card, and its empty state read *No occurrence you can see
has type X* — a claim about the value, made from a sample of it. At 25
rows it was usually also true; at 8 it is the ordinary case that it is
not. `8.8.8.8` carries five `ip-src` occurrences and **none of them is
among its eight newest**, so pressing that chip asserted the value has
no `ip-src` rows at all. It now reads *None of the occurrences shown
here has type X* with a link to the full table, and the filter note
says *N of the 8 rows shown here* rather than *N of M rows*.

---

## 7. The board

[`00-contract.md`](00-contract.md) §14.12 gains a row and amends one:

- **`viewReporting` / `value_reporting`** — Q=3, scales with nothing,
  tier 1 with two aggregates at 2, phase **31**.
- **`viewOccurrences`** — Q **9 → 5–7**, and the `Scales` cell's *capped
  at 25 rows* becomes *capped at 8*. §4 has the measurement and the
  reason the number moved.

The Overview now carries **five** panels in its left column and four in
its rail, and it is the first tab on this page to have gained a panel
since phase 26 added the Collaboration tab's report list.
