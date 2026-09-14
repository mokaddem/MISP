# PRD: Value Profile — the Overview borrows the split, and what else it could borrow

**Phase 35.** Opened and built 2026-09-14, from one change request with
two halves:

> For the overview pane, there are visualisation that could be pulled
> from other panes, for example, the tug-war from the collaboration pane
> (not the full ledger, but just the bar is enough to give a quick peak
> to users). Once you've added it (you're free to place it where it
> makes the most sense), list other potential candidates that could have
> the same treatment (there might be none, either because they don't
> make sense or they are too costly to have it there).

This is [phase 31](31-overview-balance.md) §5 reopened by name. That
section is titled *What was weighed and not built* and it closes with
three candidates and a rule for judging them; the ask is to take the one
it did not consider and then produce the list properly, with the cost
of each measured rather than guessed.

So the document is in two halves too. [§1–4](#1-what-was-built) are the
build. [§5](#5-the-other-candidates) is the list, and it is the larger
half.

---

## 1. What was built

The Collaboration tab's **tug-bar** — one stacked bar sized by how many
opinions fall each side of 50, with what it amounts to stated beside it
in words — now opens the Overview's **Analyst data** card.

Not the ledger under it, which is the ask's own line and the right one:
the bar is a shape a reader takes in without reading anything, and the
ledger is one lane, one score, one organisation and one date per
opinion, which is a table. The Overview already sent its tables to the
tabs that own them.

```
┌─ Analyst data ─────────────── 2 notes · 4 opinions · 1 proposal · 8 reports ─┐
│                                                                              │
│  THE SPLIT — 4 OPINIONS ON THE VALUE   most agree; 1 opinion of 4 does not    │
│  ┌──────────────┬──────────────────────────────────────────────────────────┐ │
│  │ 1  dispute   │                                             agree      3 │ │
│  └──────────────┴──────────────────────────────────────────────────────────┘ │
│  DISPUTES          SIZED BY NUMBER OF OPINIONS, NOT BY SCORE          AGREES │
│  ─────────────────────────────────────────────────────────────────────────── │
│  OPINION  ● Agree · 80/100                                                   │
│           Good event                                                         │
```

### 1.1 One element, drawn twice, and that is the whole trick

The bar moved out of `value_analyst_standing.ctp` into
**`value_analyst_tug.ctp`**, which both surfaces include. It was not
copied.

That is not tidiness. The panel this bar sits in deleted a histogram in
phase 26 for painting the axis the opposite way round from the table
beside it — two renderings of one encoding, disagreeing about which end
was agreement. A second copy of the tug-bar on the Overview is the same
bug with a longer fuse: the two would be a tab apart, so nothing would
bring them into view together until a reader noticed the colours
disagreed.

One element cannot disagree with itself, and the two callers hand it the
same array from the same union — `analystContext` builds `standing`
once, and both `forAnalystStanding` and `forAnalystPreview` return it.

### 1.2 The lead carries the denominator, and the tab's does not

The standing panel's sub-line already reads *4 opinions from 1
organisation*, so its lead is the bare words **The split**. The
Overview's card is headed *2 notes · 4 opinions · 1 proposal · 8 reports
on its events*, and **those opinions are not this bar's**:

- `analystCounts` counts **top-level items of any anchor** — an opinion
  written on a note is one of them.
- `analystStanding` counts **opinions at any depth that rate the
  value** — that opinion on a note is not one of them, and a reply that
  rates the value is.

The two sets differ in both directions and nothing makes them agree. On
this instance they happen to match — 4 and 4 on `8.8.8.8`, 1 and 1 on
`127.0.0.1`, confirmed by [`35-query-count.php`](35-query-count.php),
which prints both and flags a disagreement — but *happens to* is not a
guarantee, and a bar summing to three under a header saying four is a
card a reader can catch out. So the caller that cannot rely on its own
header states the count in the lead: **The split — 4 opinions on the
value**.

The card already has the vocabulary for the gap. An opinion the ledger
leaves out is drawn with the chip *about the item above, not about the
value — not in the aggregate*.

### 1.3 It is the only thing on that card that is not capped

This is what earns the bar its ~100px, and not merely the fact that it
was free.

The list under it is `ANALYST_PREVIEW_CAP` — **the newest four items of
any kind**. A value with two notes and six opinions can fill all four
slots with notes and show the reader no opinion at all, under a subtitle
that says there are six. The bar is over every opinion that rates the
value, however many there are and however old.

---

## 2. It costs no query, and that is measured rather than argued

`forAnalystPreview` **already built the ledger and then deleted it**.
The note that used to sit over that `unset` said so itself:

> The ledger is built and then discarded, and that is the cheaper
> mistake. Teaching `analystContext` to skip it would give this page two
> assemblies of one union — the exact thing that method exists to
> prevent — to save some array grouping over rows already in memory. No
> query is involved.

Phase 35 is what gave it a reader. The whole model-side change is one
key no longer being unset.

Measured with [`35-query-count.php`](35-query-count.php), the model
re-initialised per value, run with the `unset` restored and then
removed:

| | `8.8.8.8` | `127.0.0.1` | `443` | `0.0.0.0` | `sage.png` | `1.162.239.42` |
|---|---|---|---|---|---|---|
| `forAnalystPreview` Q, **before** | 19 | 13 | 9 | 9 | 8 | 9 |
| `forAnalystPreview` Q, **after** | 19 | 13 | 9 | 9 | 8 | 9 |
| opinions on the bar | 4 | 1 | 0 | 0 | 0 | 0 |

**Four of the six values draw no bar at all**, because nobody has
recorded an opinion that rates them. That is the ordinary case on this
instance: 43 opinions exist, 28 of them on events, and a value reaches
one only through an anchor it shares. A bar over no opinions would be an
empty state inside a card that already has one, so the block is absent
rather than empty.

---

## 3. The pixels, and the column that changed sides since phase 31

[Phase 31](31-overview-balance.md) D5 is the rule this has to answer to:

> The rail's four cards summed to 1466px against the left column's
> 1483 — the two were within 2% of each other — so **the rail, not the
> occurrence card, is what sets this tab's height** the moment the card
> is shortened. Anything added to the rail makes the Overview taller,
> which is the opposite of the ask.

**That is no longer true, and it is worth recording before anything is
weighed against it.** Measured at 1600×1000, light and dark identical:

| | left column | rail | which sets the pane |
|---|---|---|---|
| **phase 31, `8.8.8.8`** | 1483 → 1404 | 1466 | the rail |
| **today, `8.8.8.8`** | **1804** | 1451 | **the left column**, by 353px |
| today, `443` | 1526 | 1450 | the left column, by 75px |
| today, `127.0.0.1` | 1311 | 1250 | the left column, by 60px |
| today, `sage.png` | 902 | **1266** | the rail, by 364px |

The rail has not moved. The left column gained 400px, and it is the
**Tags and galaxies** card: 223px when phase 31 measured it, **507px**
today, after [32](32-tag-scope.md) added the second tag scope and
[33](33-tag-scope-fold.md) folded the two together.

So D5 has inverted on the flagship — the rail is now the column with
slack — and it has *not* inverted on a thin value like `sage.png`, where
the rail's fixed-height cards still dominate a left column with almost
nothing in it. **There is no permanently cheap column.** §5 uses the
per-value table above rather than a single rule.

### 3.1 What the block costs

| | |
|---|---|
| wrapper padding, top | 16.0px |
| the lead line | 17.6px |
| its margin | 7.2px |
| **the bar** | **30.0px** |
| the caption | 14.9px + 2 |
| block padding and rule, bottom | 12.0px + 1 |
| **total** | **100.7px** |

And what that costs *the pane*, measured by hiding the block and reading
the page again rather than by subtracting — because which column is
taller can change when one of them shrinks:

| | pane before | pane after | cost |
|---|---|---|---|
| `8.8.8.8` | 1767.3 | 1867.9 | **+100.6px** (+5.7%) |
| `127.0.0.1` | 1314.2 | 1374.6 | **+60.4px** — the rail absorbs 40 of it |
| `443` | 1589.7 | 1589.7 | 0 — no opinion, no bar |
| `sage.png` | 1330.0 | 1330.0 | 0 — no opinion, no bar |

The Overview is **100px taller on the one value in five that carries an
opinion**, and unchanged on the other four. That is stated rather than
softened: phase 31's complaint was that this tab is too tall, and this
phase makes it slightly taller on some values. The trade is 100px for
the only uncapped statement on a card whose list is capped at four, and
the ask asked for it.

### 3.2 The block is the tab's, unchanged — only its surroundings differ

On the standing panel the bar opens a `.p-3` that continues into the
ledger. On the Overview it is the top of a card whose body is its own
padded list, so stacking the defaults would put **61px of nothing**
between the caption and the first note: a 16px wrapper gap on a 14px
block margin on the list's own 16px padding. `.vp-analyst-split` pads
three sides and lets the block's own bottom padding and rule be the
separator. Nothing inside the block changed.

---

## 4. Files

| | |
|---|---|
| `Elements/Values/View/value_analyst_tug.ctp` | **new** — the bar, extracted |
| `Elements/Values/View/value_analyst_standing.ctp` | the inline bar → the element |
| `Elements/Values/View/value_analyst_preview.ctp` | draws it, with its own lead |
| `Model/ValueProfile.php` | `forAnalystPreview` stops unsetting `standing` |
| `webroot/css/value-profile.css` | `.vp-analyst-split`, 7 lines |
| [`35-overview-split.mjs`](35-overview-split.mjs) | **new** — geometry, two themes |
| [`35-query-count.php`](35-query-count.php) | **new** — Q, and the two opinion counts |
| [`35-free-keys.php`](35-free-keys.php) | **new** — the evidence behind §5 |

---

## 5. The other candidates

The ask's own criterion sorts this list, and it is not taste: *there
might be none, either because they don't make sense or they are too
costly to have it there*.

The split turned out to be free in the strictest possible sense — the
panel that draws it was **already fetching the array and throwing it
away**. So the first question for every other candidate is whether it is
in the same position, and that is a fact, not a judgement.
[`35-free-keys.php`](35-free-keys.php) answers it by calling `forVerdict`
the way `viewVerdictCard` calls it — *without* `with_opinions` — and
printing every key the Overview's rail card holds and never reads:

| key | `8.8.8.8` | `127.0.0.1` | `443` | `0.0.0.0` | `sage.png` | `1.162…` |
|---|---|---|---|---|---|---|
| `stances` | 5 | 5 | 5 | 5 | 5 | 5 |
| `composition` | 4 | 3 | 2 | 3 | 3 | 4 |
| `tug` | 2 | 2 | 2 | 2 | 2 | 2 |
| `changers` | 3 | 3 | 2 | 2 | 3 | 3 |
| `curves` | 1 | 1 | **0** | **0** | 1 | 1 |
| `lean_ledger` | 2 | 1 | **0** | 1 | **0** | **0** |
| `orgs` | 8 | 4 | 12 | 4 | 1 | 1 |
| `warninglist` | 6 | 6 | — | 6 | — | — |
| `cases` | **0** | **0** | **0** | **0** | **0** | **0** |
| `opinions` | — | — | — | — | — | — |

Every row above except the last two is a free candidate on the
arithmetic. What follows is what each one would actually say.

### 5.1 Worth taking — the lean's stance bar

**`value_verdict_lean`'s track, from the Assessment tab.** The Overview's
verdict card states the lean as a **pill** — one coloured word — and
prints the three heaviest ledger rows under it. What it never shows is
the arithmetic that produced the word: how many organisations take each
side, and where the supermajority mark that decided it sits.

That is a bar with two marks on it, and the data is `stances`, which is
**5 keys on all six values** and which the card holds and ignores. The
element's own docblock already says it: *It costs no query. `stances` is
computed by `ValueLeanTool::stancesFor()` on every value and, until this
band, was read by no template in the application.*

It is the closest thing on the page to the tug-bar in both shape and
job — a headcount split with a threshold — and it answers the single
most-asked question about the card it would sit in, which is *why does
it say that*. **The strongest candidate on this list.**

Two things to settle before building it. The band in
`value_verdict_lean.ctp` is 281 lines — counts, track, a sentence, a
band list and a rows ledger — so the extraction is the same surgery this
phase did to the tug-bar and should produce the same thing: one shared
element for the track, included by both, not a copy. And it must not
land beside §5.2 without a decision, for the reason §5.2 gives.

### 5.2 Worth taking with care — the case tug-of-war

**`verdict['tug']`, the contested hero's bar.** Present on **all six
values** and drawn today only by `value_verdict_conflicted`, on the
Assessment tab, on the contested branch. Two of these six values —
`8.8.8.8` and `0.0.0.0` — lean *contested*, so it is not an exotic
branch.

The hazard is specific and it is the reason this is not §5.1. **It is
also a horizontal stacked bar with two opposed sides, and it means
something completely different from the one this phase just added.** The
analyst tug-bar is sized by *headcount of opinions*; this one is sized
by *summed signal weight*. Two bars that look alike and count different
things, on one pane, is the class of bug phase 26's deleted histogram
belongs to — and it would be worse here, because both would be visible
at once rather than a tab apart.

Takeable, but only with a decision about how the two are told apart, and
that decision is the work. The caption under the analyst bar already
says *sized by number of opinions, not by score*, which suggests the
shape of the answer.

### 5.3 Worth taking — what would change this

**`verdict['changers']`.** Already named by [phase 31](31-overview-balance.md)
§5, which called it *the best thing still on the table for this tab*,
and its measurement holds: **2 to 3 lines on every value**, held by the
card, never drawn.

Two corrections to that entry. It is **not a visualisation** — it is
three sentences naming what would move the assessment — so it is not
strictly *the same treatment* the ask is about. And phase 31 rejected it
on D5, that it would land on the rail and make the tallest column
taller; §3 above shows the rail is now the **shorter** column on three of
four values. The reason it was refused has expired on most of the page,
and it is worth about 100px.

### 5.4 Worth taking if a chart is wanted — the shelf-life curve

**`value_verdict_curves`.** `curves` is built by `forVerdict` for every
caller and the Overview card ignores it. Free.

Two caveats, both real. It is **empty on two of six values** —
`443` and `0.0.0.0` return no series — so the card has to have an absent
state and not merely a flat line. And the Overview's **Lifecycle** card
already draws the relevance runway *as a state*: where the value sits on
the clock, and when the clock last moved. The curve's unique fact is
**when** the line crossed, which a snapshot cannot carry. That is a
genuine addition and a narrower one than the other three.

It also costs a Chart.js instance and ~120px, where §5.1 and §5.2 are
inline CSS bars.

### 5.5 Probably not — the composition strip

**`verdict['composition']`.** 2 to 4 segments on **every** value, free.
The stacked strip *How the quality was reached* — earned and deducted
points on one scale.

Against it: the Overview card already prints the three heaviest ledger
rows **and** the count of the ones that did not fit, which is the same
ledger read a different way. The strip's addition over that is the
*proportion* between groups. That is a real reading, but it is a second
view of a thing the card is already showing, where §5.1 is the first
view of a thing it is not showing at all. Take §5.1 first and see whether
this is still wanted.

### 5.6 No — the opinion histogram

**`verdict['opinions']`.** Null on every value in the table, and that is
not an accident: it is gated behind `with_opinions`, which runs
`analystContext` — **7 to 28 queries**, the Collaboration tab's whole
union. `viewVerdict` and `viewVerdictAside` pay for it because they draw
a column of it; `viewVerdictCard` deliberately does not.

It is also the wrong thing to want. The histogram over those opinions is
the object phase 26 **deleted** for being a chart of almost nothing —
ten bands over three or four opinions — and for painting the axis the
wrong way round. The bar this phase added is the surviving reading of
exactly that data, and it is on the Overview now.

### 5.7 No — the two opposed cases

**`verdict['cases']`.** **0 on all six values**, including the two that
lean contested. `ValueContestedTool::casesFor()` produces nothing on
this instance's data, which is `00-contract.md` §14.11's open item and
not this phase's to close. A candidate nobody can see is not a
candidate; revisit when something produces a case.

### 5.8 No — the occurrence facet rail

Phase 31 §5's rejection stands unchanged and it is not about cost.
`ValueStatsTool::occurrenceFacets` counts over **the rows the panel
fetched**, and the Overview's cap is 8. A facet rail there would be a
count of eight rows presented as a summary of a value with 48,255 of
them. Making it true means running the aggregate the tab does not run
either.

### 5.9 No — anything from Relationships

Also unchanged from phase 31 §5, and this is the pure-cost row. The
neighbourhood is a scan of up to `RELATION_SCAN_BUDGET` = **20,000
attribute rows**, ~914ms on `443` and ~379ms on `8.8.8.8`.
[`24b`](24b-relationships.md) refuses it for the tab's own *badge*,
which is one number; a rail card is not a smaller ask than a badge.

The one cheap relationship fact — `objectCountFor`, already on the tab
bar — is not a visualisation. It is the number 15.

### 5.10 No — anything from Enrichment

Not a cost question but a rule. The Enrichment tab is the one tab that
**reads nothing from the database**: its content is what a third party
said when asked over HTTP. Phase 28 §14.4 and phase 24b both refuse to
put an external call on a page load — `circl_passivedns` on `8.8.8.8` is
**4.9 seconds**, and the Overview renders eight panels whether or not a
module is up. There is nothing here to borrow that is not a network
request.

### 5.11 No — the Timeline lane grid, and the History facets

**Timeline** is already borrowed and the borrowing is invisible because
it was done properly. The Overview's **Reporting** card draws
`activityMonthsFor` as a month strip — the same aggregate the Timeline
tab scores its continuity from, which is [phase 31](31-overview-balance.md)
D7. What is left on that tab is the *lane grid*, and it is not a
drawing: it is redrawn in JavaScript from a JSON feed over a rolling
window that a brush control moves. A brush with nothing to brush is a
control, not a widget.

**History** has no visualisation at all — it is audit rows behind a
facet rail — and the count is the *viewer's*: a plain analyst, an org
admin and a site admin get three different numbers for one value, and on
a default instance `MISP.log_new_audit` is off and it is zero for a
reason that has nothing to do with the value.

### 5.12 No — the rest of Collaboration

The ask already drew this line and it was the right one. What is left on
that tab after the bar is the lane ledger, the thread, the comment table
and the report list. **None of them is a shape**; all four are lists
whose rows are the content. The Overview's job is to say what they add
up to and link to them, which the *Open thread* button on that card has
done since phase 26.

### 5.13 The summary

| candidate | free? | verdict |
|---|---|---|
| the lean's stance bar (`stances`) | yes | **take** — first |
| what would change this (`changers`) | yes | **take** — not a chart, but §5.3 |
| the case tug-of-war (`tug`) | yes | take, after deciding how it is told apart from the bar just added |
| the shelf-life curve (`curves`) | yes | take if a chart is wanted; empty on 2 of 6 |
| the composition strip (`composition`) | yes | second view of something already shown |
| the opinion histogram (`opinions`) | **no**, 7–28 Q | no, and it was deleted for being wrong |
| the two opposed cases (`cases`) | yes | nothing produces one yet |
| the occurrence facet rail | n/a | would summarise 8 rows as a value |
| anything from Relationships | **no**, 20k rows | no |
| anything from Enrichment | **no**, HTTP | no |
| the Timeline lane grid | — | already borrowed, as the month strip |
| History | — | nothing to draw |
| the rest of Collaboration | — | lists, not shapes |

---

## 6. Verification, as it ran

Against the live instance, logged in as a real session, measured with
`getBoundingClientRect` rather than read from the source.

- [`35-overview-split.mjs`](35-overview-split.mjs) — **52 checks**, new.
  Four values × two themes: no page error, the theme actually applied,
  no horizontal overflow, the phase's CSS arrived (a stale stylesheet
  leaves the bar drawn and flush against the card's edge, which looks
  like a layout opinion rather than a missing file), the bar 30px, the
  block ~101px, the lead carrying its denominator, every drawn segment
  with width and a resolved colour, and — on the two values that carry
  no opinion — no bar rather than an empty one.
- [`35-query-count.php`](35-query-count.php) — §2's table, run twice with
  the `unset` restored and removed. Identical Q both ways.
- [`35-free-keys.php`](35-free-keys.php) — §5's table.
- [`27-panel-regression.py`](27-panel-regression.py) — the seven panels
  sharing code with the ones touched, two values, all 200.
- [`31-panel-check.py`](31-panel-check.py) — **111 checks**, unchanged.
- [`26a-analyst-check.mjs`](26a-analyst-check.mjs) — the Collaboration
  tab after the extraction: standing panel present, **4 lanes**, the
  pivot holding, the readings agreeing with their scores.

**The tab it was taken from is byte-identical in effect.** The standing
panel's fragment still reports the same two segments at the same widths
(`dispute 25%`, `agree 75%` on `8.8.8.8`; `dispute 100%` on
`127.0.0.1`) and the same verdict clause, which is the check that the
extraction moved the bar without changing it.

**No ACL entry.** No new controller action — the split rides the
endpoint that was already fetching it.

---

## 7. Board

`00-contract.md` §14.12's rule is that a row moves only when its phase
document records the same numbers. This phase touches one row —
`viewAnalystPreview` — and §2 is the record that it **does not move**:
the query count is identical with and without the change, on six values,
because the array the bar draws was already being built and deleted.
