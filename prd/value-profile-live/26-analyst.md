# PRD: Value Profile — Analyst data goes live, and the tab becomes Collaboration

**Phase 26**, the fifth live phase. Converts `viewAnalystStanding` and
`viewAnalystThread` — the tab's two endpoints and the two panels inside
them — from `ValueProfileFixture` to the database, and adds a third,
`viewAnalystReports`, for the narrative list the coverage survey placed
here and nobody had built. Depends on
[`00-contract.md`](00-contract.md) §14 and on the four phases before it,
whose seam, facade and tools this extends. The tab's fixture-era design
is [`05-analyst.md`](../value-profile-tabs/05-analyst.md), and that
document's §11 is the list this phase exists to close.

**Opened and built 2026-09-05.** §1 is the task board, §1.1 the
decisions — seven taken before building and three the build forced,
§1.2 what a session picking this up cold needs to know, §11 how the
three calls this phase could not take alone were settled, §12
verification as run, and §16 the build log and what it cost. **§17 and
§18 are what the maintainer's two readings of the built tab changed:**
the tab's new name and a lane bug it shipped with, then links on every
chip that names a record and a proposal drawn as a change rather than as
a message.

**The tab is called *Collaboration* from §17.1 onward**, and this
document is titled for the phase rather than for the tab. Everything
before §17 says *Analyst data*, which is what it was called while the
phase was being written; the elements, the endpoints, the tab's id and
this file all still carry `analyst`, because an id is not a name.

**A naming collision, so nobody trips.** The `26-object-graph-*.php` and
`26-panel-harness.mjs` files in this directory are **not** this phase's.
They are named after §26 and §27 of [`24-relationships.md`](24-relationships.md)
— phase 24's object re-founding — and predate this document. Every
artifact this phase adds is prefixed `26a-`, and there are two:
[`26a-analyst-check.mjs`](26a-analyst-check.mjs), §12.5's browser pass,
and [`26a-lane-geometry.mjs`](26a-lane-geometry.mjs), §17.2's.

---

## 1. The task board

Every row is `todo` until its own section says otherwise, and a row moves
to `done` only when §12's verification has run against it.

| # | Task | Section | Status |
|---|---|---|---|
| T1 | `ValueProfile::forAnalystStanding` and `forAnalystThread` — the two facade methods | §4 | **done** |
| T2 | The anchor set: phase 25's union, widened by two anchors | §5 | **done** |
| T3 | The thread read per level, not per item — no `fetchChildNotesAndOpinions` | §7 | **done** |
| T4 | The aggregate computed in the facade: mean, buckets, gap, per-org rollup | §6 | **done** |
| T5 | What an opinion on a note rates, and what the aggregate therefore counts | §6.2, §8 | **done** |
| T6 | The per-organisation ledger, on the built `B4` design | §4, `05-analyst.md` §16.3 | **done, and the design changed** — §6.3 |
| T7 | Orphan and unknown anchors survive the render | §5.4 | **done** |
| T8 | Proposals in the thread, or excluded in words | §9.1 | **done** — included, labelled |
| T9 | Event reports as the narrative list | §9.2 | **done** — a third panel and a third endpoint |
| T10 | §14.6: the ACL band removed, the standing panel's permanent caveat added, both rows written into the table | §10.1 | **done** |
| T11 | The tab badge, which is a fixture literal today | §10.2 | **done** — dropped |
| T12 | The board rows: §14.12's two `—` cells, and this document's numbers | §12.4 | **done** — three cells, not two |

**Where the phase stands. Built 2026-09-05, in four commits.** Both
endpoints read the database, a third endpoint was added for the report
list, and the numbers are in §16. What is *not* done and was never this
phase's: the Overview's analyst preview card, which §11's second call
left on the fixture deliberately.

### 1.1 The decisions this phase has taken

Taken when the phase opened, before any of it was built, because each one
changes what gets built rather than how. **A task that contradicts a row
here is a task that has found something, not a task that may proceed** —
reopen the row, in this document, with what it found.

| # | Decided | Where the argument is | What would reopen it |
|---|---|---|---|
| D1 | The anchor set is phase 25's union — occurrence, its event, its object — **plus** the value's galaxy clusters and the analyst rows already hanging off those three. It is one union across both tabs, not two | §5 | A galaxy cluster that is not a claim about the values tagged with it. The union half does not reopen without phase 25's D4 reopening with it |
| D2 | The thread is read **one query per level**, over the whole anchor set, and never through `AnalystData::fetchChildNotesAndOpinions` | §7 | A level count that makes the batched read wider than the per-item one — which needs more nesting than any instance has |
| D3 | The aggregate is computed in the facade and is a *stated* computation, not an inherited one: mean, ten buckets, the widest empty band, and a per-organisation rollup at `Orgc` | §6 | The verdict engine arriving and wanting the same numbers, in which case they move and both readers cite one source |
| D4 | An opinion whose anchor is a `Note`, `Opinion` or `Relationship` rates **that row**, not the value, and is excluded from the aggregate while still being drawn in the thread | §6.2, §8 | A reading in which rating a claim about a value is a claim about the value. §8 argues it is not, and the instance has 9 such rows to look at |
| D5 | Rows whose anchor resolves to nothing render as rows with an unresolved target, never dropped and never fatal | §5.4 | Nothing. This is a robustness rule, and the instance already has one such row |
| D6 | Markdown stays unrendered. Notes print `pre-line`, as MISP's own thread does | §13 | A per-note markup flag existing. There is none — `language` is a natural-language code — so enabling a parser here enables it for content authored without one |
| D7 | The standing panel is **§14.6's third computed-judgement member** and carries the permanent caveat. The withheld-by-ACL note `05-analyst.md` §11 assumed is *removed*, not reworded — an existence claim without a number is still an oracle | §10.1 | §14.6 itself, which names the oracle risk as the first thing to revisit if it is ever judged acceptable |

**Not decided here, and deliberately.** The three calls in §11. And
nothing about the verdict engine: the aggregate this phase computes is
the Analyst tab's own reading of opinions, and the four `blocked` rows on
§14.12 stay blocked — a phase that computes a mean has not computed a
verdict, and treating the one as the other is how the campaign would
acquire an algorithm nobody reviewed.

**Three more the build forced.** Each one is a row above that turned out
to be wrong, or a question the seven did not reach, and each was found by
running the code against the instance rather than by reading it.

| # | Decided | Where the argument is | What would reopen it |
|---|---|---|---|
| D8 | A ledger row is **an opinion, not an organisation**. An organisation's rows sit together, groups are ordered by their strongest opinion, and the tug-bar counts opinions | §6.3 | A ledger that draws an organisation's several opinions on one lane, which is the truer answer and a redraw this phase did not take |
| D9 | The ledger groups on the organisation's **uuid**, and prints the name | §6.4 | Nothing. Eleven notes on this instance name an organisation that no longer exists, and two of those must not merge into one lane for want of a name |
| D10 | Event reports are a **third panel and a third endpoint**, not more thread items | §9.2 | A report list short enough everywhere that it reads as part of the conversation. It is not: the instance's longest is 994 characters and nothing bounds it |

**D8 is the one that contradicts a built design**, and by §1.1's own
rule that makes it a finding rather than a permission. `05-analyst.md`
§16.3 chose `B4`, one lane per organisation, from a fixture in which
every organisation held exactly one opinion. §6.3 is what the instance
said about that.

### 1.2 Starting from cold

**It is built. This section is what it was written against, kept for the
record, plus what a session arriving after it needs.**

Before the phase: `viewAnalystStanding` and `viewAnalystThread` both
called `profileFor()`, which is `ValueProfileFixture`, and the two
elements — 605 and 635 lines — rendered the whole tab against the
fixture's array.

After it: three endpoints, all live. `forAnalystStanding` and
`forAnalystThread` are two readings of one `analystContext`;
`forAnalystReports` is the new third and shares only the occurrence
read. The elements were **fed rather than redrawn**, with four
exceptions, each argued where it is made: the ledger's row identity
(§6.3), two new attachment chips for the cluster and unresolved anchors
(§5.4), the proposal kind (§9.1), and the two removals T10 required.

**`viewAnalystPreview` is still the fixture's, and that is now a
deliberate lie rather than an inherited one.** It is the Overview's card
(`ACLComponent.php:1054`, `value_analyst_preview.ctp`, 155 lines), it
reads the same union, and it sits one tab away from three panels that no
longer agree with it. §11's second call is where that was decided and
by whom.

**Where.** The corpus and the code are both in the
`attribute-value-page-brief` worktree, branch
`worktree-attribute-value-page-brief`. Three other worktrees carry copies
of `prd/` that lag this one; check `git log -1 -- prd/` before believing
any of them.

**Build order.** T2 then T3 — the anchor set and the level read — because
every panel hangs off them, and D1 and D2 are the two decisions that
would be expensive to unpick after both panels are written against them.
T4 depends on T2 only for its input set, and can be written beside it.

**Three traps, all of which look like success.**

1. **Verify only on `8.8.8.8` and the Attribute anchor never runs.** Its
   analyst content is entirely event-level: 2 notes and 4 opinions, all
   on its events, none on its occurrences. §12.2 names five values that
   do exercise the attribute branch, and they are small.
2. **`AnalystData::rearrangeOrganisation` fails silently and expensively
   at once.** Absent the contained association, every row reports
   *Unknown organisation* **and** costs one `Organisation` find per row
   per side. Contain `Org` and `Orgc`. Phase 24 recorded it; phase 25
   repeated it; it is on this list because it has now cost two phases.
3. **The recursion memo is never reset within a request** (§7.2). A
   second call for a uuid already walked returns no children, so a union
   that reaches one note twice draws its replies once — and which time it
   draws them depends on iteration order. Code that passes verification
   on one anchor ordering can be wrong on another.

---

## 2. Why Analyst goes fifth

Because it is the last tab whose data MISP actually holds. Of the four
`—` rows left on §14.12 after this one, the Overview's belong to a tab
that must leave its verdict card on the fixture, Enrichment's gating
decision is not taken, History's is a window question phase 25 has
already half-answered, and Verdict's four are `blocked` on an engine that
does not exist. Analyst data is the only remaining tab where the
conversion is a matter of reading rows.

It also goes fifth because the two phases before it built most of it
without meaning to. Phase 24's `assertedClaims` (`ValueProfile.php:4847`)
already assembles the anchor triple by uuid, already reads an
`AnalystData` subclass through `buildConditions($user)`, already contains
`Org` and `Orgc` against the `rearrangeOrganisation` defect, and already
resolves a far end that may be any of three types. Phase 25's D4 already
decided the union and already labelled every row with its target. This
phase generalises one reader rather than writing a third.

---

## 3. What the instance holds

Measured 2026-09-05 against the instance the worktree serves. The
instance is a working dev box and it moves; re-derive before trusting.

| Table | Rows |
|---|---|
| `notes` | 75 |
| `opinions` | 43 |
| `relationships` | 128 |
| `event_reports` | 174 |
| `shadow_attributes` | 23 |

**By anchor**, which is the number that decides the union:

| `object_type` | notes | opinions |
|---|---|---|
| `Event` | 44 | 28 |
| `Attribute` | 9 | 3 |
| `Object` | 8 | 1 |
| `GalaxyCluster` | 4 | 2 |
| `Note` | 7 | 5 |
| `Opinion` | 2 | 2 |
| `Relationship` | — | 2 |
| `Event1556` | 1 | — |

Three readings, and each one is a decision above.

**The event anchor is the tab.** 72 of 118 notes and opinions hang off an
event. Take only occurrence-level rows and the tab is empty on almost
every value; this is phase 25's D4 arriving again from the other side,
and it is why D1 does not reopen it.

**`GalaxyCluster` is a real anchor and the union does not have it.** Six
rows, and phase 24's claim reader already counts a plain galaxy *tag* on
a value's event as relevant. A note written *on the cluster* is the same
statement with an author, and dropping it would rank the label above the
argument — the exact inversion `assertedClaims` was widened to avoid.

**Eighteen rows anchor on another analyst row.** 7 notes on notes and 2
on opinions; 5 opinions on notes, 2 on opinions and 2 on relationships.
These are the thread's nesting. **Nine of them are opinions**, and those
nine are the class D4 excludes from the aggregate — a note nested under a
note rates nothing and was never in it.

### 3.1 The opinion distribution is bimodal, and the fixture guessed it

| Score | 0 | 10 | 20 | 30 | 40 | 50 | 60 | 70 | 80 | 90 | 100 |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Rows | 6 | 4 | 2 | 1 | — | — | — | 1 | 3 | 10 | 16 |

Thirteen rows at 30 or below, thirty at 70 or above, and **nothing
between**. The instance-wide mean is **67.9**, which is a score no
organisation on this instance has written.

`05-analyst.md` §1 built candidate `A1` around exactly this shape — *"the
mean is 50 and the nearest actual opinion is 26 points away … the strip
draws the mean as a marker sitting in an empty band"* — from a fixture
invented before anyone counted. The instance-wide gap is 30→70 and the
fixture's declared gap is `['from' => 30, 'to' => 70]`. **The design was
right about the shape for the wrong reason**, and the phase should say
so rather than quietly inherit the luck: what the panel must not do is
assume a gap, since a per-value sample of three opinions has whatever
shape it has.

Six organisations write opinions on this instance; thirteen write notes.

---

## 4. What ships

Two facade methods over the built elements — **and a third endpoint,
which the plan did not have.** §9.2 is where that grew: the coverage
survey forecast *one new element, probably two*, and T9 was already on
the board without anywhere named to put it.

| Action | Facade | Element | Panel |
|---|---|---|---|
| `viewAnalystStanding` | `forAnalystStanding` | `value_analyst_standing` | the tug-bar and the ledger |
| `viewAnalystThread` | `forAnalystThread` | `value_analyst_thread` | the chronological thread, nested to depth 2, proposals, and the composer |
| `viewAnalystReports` | `forAnalystReports` | `value_analyst_reports` | **new** — the event reports written about this value's events |

All three take `array $user, $value, array $options`; the first two
return the tab's existing array shape and the third returns its own.
The ledger's built design is `05-analyst.md` §16.3 (`B4`, the lane
ledger with the tug-bar) and its sorting is §16.6 — this phase feeds
those and changed one thing about them, which is §6.3.

**The standing panel has no histogram**, and this document's §6.1 said
it did. `05-analyst.md` §16 deleted it before this phase opened: ten
bands over three or four opinions is a chart of almost nothing, and it
was the one place on the panel painting the axis the other way round.
The ten buckets are still computed and `empty_bands` is still drawn —
*7 of ten bands unoccupied* is one of the summary chips — but no element
reads `buckets` itself today. It stays because the panel states a count
derived from it and because the Verdict tab's histogram, when that phase
is unblocked, is the same ten.

**The composer is not this phase's.** Writes are out of the campaign's
scope by `00-contract.md` §1; the picker stays drawn and stays disabled,
and `value-profile-writes.md` owns it.

---

## 5. Decision — the union is phase 25's, widened by two anchors

`05-analyst.md` §11 states the constraint the whole phase turns on: **a
value is not a valid analyst-data target.** Notes, opinions and
relationships hang off `object_uuid` + `object_type`, and no value has a
uuid. Every panel here is a controller-assembled union, there is no
single query for it, and **there is no pagination across it.**

### 5.1 The anchor set

Phase 24's `assertedClaims` builds three sets by uuid from one
`occurrenceUuidsFor` read — `Attribute`, `Event`, `Object` — at no extra
query, because the occurrence read already joins `Event` and `Object`.
This phase takes that set and adds two:

- **`GalaxyCluster`**, for §3's six rows. The value's clusters are
  reachable from its events' tags, which the page already reads.
- **the analyst rows themselves**, which is the thread's nesting and is
  §7's per-level read rather than a fourth anchor in the first query.

**And not relationships, which costs two rows.** §6.2 counts nine
opinions that rate another analyst row, and two of those rate a
`Relationship`. D1's set does not contain relationships, so those two are
not in this union and this tab does not draw them — §8's *excludes
nothing from the page* is about the rows the union returns, not about
every row in the class. The argument for leaving it: a claim about the
value is phase 24's panel, `assertedClaims` already reads it, and
widening the anchor set here would put a second reader of the same rows
on a second tab. The cost is named rather than absorbed, and one of the
two is an orphan anyway — its relationship does not exist.

**Both levels of galaxy tag, and on this instance only one of them
reaches anything.** A galaxy tag on the occurrence classifies the value;
one on its event classifies the neighbourhood the value is part of,
which is the same argument that admits event-level notes at all. Measured
2026-09-05: no attribute on the instance carries a galaxy tag that has
analyst data on it, and exactly one event does — event 1522, tagged
`misp-galaxy:sector="Employment"`, whose cluster carries one note. So the
occurrence half of this branch is written from the schema and the event
half is the one that fires. §12.2 verifies on a value in that event.

### 5.2 One query per model, six branches per anchor kind

`assertedClaims` already demonstrates the shape: each anchor kind
contributes one equality pair per direction, all `OR`-ed into a single
condition, so the read stays one query however many kinds there are. For
`Note` and `Opinion` only the forward direction exists — an analyst row
names its target, and nothing points back — so it is one branch per kind
rather than two.

**This does not violate the OR-across-columns rule.** The branches are
`(object_type, object_uuid)` pairs on one table with one index; the rule
that bit earlier phases was `OR` across *different* columns of
`attributes`, which is a different query.

### 5.3 Every row names its target

Phase 25's D4, unchanged: *note on event 3753* is a different claim from
*note on attribute 481920*, and a union that flattens the two promotes an
event's narrative into a statement about the value. The ledger groups by
organisation; the thread keeps the target on every row.

### 5.4 Anchors that resolve to nothing — D5

One `notes` row on this instance carries `object_type = 'Event1556'`, a
type that is not a type. Phase 25 recorded it and sent it to §14.7's
report-do-not-fix list. This phase looked at the other half: **its
`object_uuid` resolves to no event, attribute or object at all.** Event
1556 exists and has a different uuid. So the row is not merely
mistyped — it is an orphan, and MISP's own UI wrote it.

The rule this settles is not about one row. Any code that switches on
`object_type` must treat the set as open, and any code that resolves an
anchor must treat resolution as fallible. A row whose target does not
resolve is drawn with an unresolved target, never dropped — dropping it
would let a write the instance accepted vanish from the one page whose
subject is who said what.

**How it landed, and the honest limit of it.** The union looks rows up
*by* the anchor uuids, so a root item resolves by construction and the
`Event1556` row simply never matches anything — the code keys on the
uuid and never switches on `object_type`, which is the half of D5 that
does work every request. The unresolved branch is therefore reachable
only if a row comes back for a uuid the target map does not hold; it is
written, it has its own dashed chip and its own *not in the aggregate*
wording, and **nothing on this instance exercises it.** Recorded as
written-from-the-schema rather than claimed as verified.

---

## 6. Decision — the aggregate is computed here, and stated

`05-analyst.md` §11: **nothing computes the aggregate.** No mean, no
buckets, no per-organisation rollup exists anywhere in MISP, and the
Verdict tab's histogram is fixture data. So this phase computes it, and
D3's weight is on the second half — that it is a *stated* computation,
whose rule the panel can name, rather than a number that appears.

### 6.1 What it computes

Over the opinions the union returns and D4 admits: the count, the mean,
ten buckets of ten points, the widest empty band, and a rollup at
`Orgc`. The panel already renders all five (`05-analyst.md` §6, §16.3).

**The mean is drawn and is never the headline.** That is the whole point
of candidate `A1`, and §3.1 is the instance agreeing: a mean of 67.9
over a distribution with nothing in its middle describes no one.

**The gap is measured, not assumed.** §3.1's 30→70 is instance-wide. A
value with three opinions has whatever shape three opinions have, and the
panel must state the empty band it found rather than the one the design
was drawn around — including finding none.

### 6.2 What it counts — D4

An opinion anchored on a `Note`, an `Opinion` or a `Relationship` rates
**that row**. It is drawn in the thread, where it is a reply and reads
as one, and it is excluded from the aggregate, where it would otherwise
be counted as an organisation's position on the value. Nine rows on this
instance are in this class, including two opinions on relationships —
an organisation rating somebody's *claim*, which is a position about an
argument and not about an indicator.

`05-analyst.md` §11 put this as *"whoever wires this also has to
decide — in code — that an opinion written on a note rates the note and
not the value."* Decided, and widened: the same holds for opinions on
opinions and on relationships, which that sentence did not reach.

**Verified on `google.com`**, which is the only value on the instance
that exercises it: the thread draws five items and the standing panel
says *4 opinions from 1 organisation*. The fifth is an 80/100 written on
a note, drawn with its own *about the item above, not about the value —
not in the aggregate* marker, and absent from the mean. A reader who
counts the thread and compares finds the difference explained rather
than apparent, which is the whole reason §8 draws it at all.

### 6.3 A ledger row is an opinion — D8

**The built ledger assumed one opinion per organisation, and the
campaign's own default value breaks that assumption in the worst
direction.** `05-analyst.md` §16.3 chose `B4`, one lane per
organisation, from a fixture in which every organisation held exactly
one position. On the instance, `8.8.8.8` carries four opinions and all
four are ADMIN's, written within 42 seconds of each other on event 47:
**100, 100, 80, then 10.**

Rolled up to one lane, whichever rule picks the score:

- *the latest* draws one `Strongly disagree` lane at 10/100 over a set
  that is three-quarters agreement, and the tug-bar's clause reads
  *every organisation disputes*;
- *the mean* paints ADMIN at 72.5, a position nobody wrote, on the one
  panel whose entire argument is that a mean can describe nobody.

Both are worse than the shape they were meant to summarise. So every
opinion gets its lane, an organisation's lanes sit together, groups are
ordered by their strongest opinion and rows within a group by score —
which keeps the panel readable as the scale §16.3 built it to be, and
keeps `vp-sort-default` able to restore that order on the third click.

What the change costs, stated: the *Organisation* column repeats a name
down an organisation's rows, and the *Notes* and *Last activity* columns
are properties of the organisation repeated on each of its rows. The
tug-bar's caption changed from *sized by headcount* to *sized by number
of opinions*, because it now counts opinions and a caption that said
otherwise would be the second thing on the panel to mislead.

**The truer answer was not taken.** `B4`'s lane is already a 0–100 axis,
so an organisation's several opinions could be several marks on its own
lane — one row per organisation *and* no opinion lost. That is a redraw
of the lane markup, which §4 says is not this phase's, and it is the
obvious next move on this panel rather than a defect in it.

### 6.4 The ledger groups on a uuid — D9

Eleven of this instance's 75 notes carry an `orgc_uuid` that matches no
`organisations` row: the organisation was deleted, the contained
association comes back empty, and `AnalystData::rearrangeOrganisation`
hands back a record with a null name. So *Unknown organisation* is a
path live data takes on this page, not a defensive branch — phase 25's
note lane already prints it, and this tab prints it on `1.2.3.4`.

Grouping the ledger on the printed label would then merge two deleted
organisations into one lane group for want of a name to tell them apart,
which is an aggregation the reader could not see happening. The key is
`orgc_uuid`, falling back to `org_uuid` and only then to the label; the
label is what gets printed. Free — both columns are already fetched.

---

## 7. Decision — the thread is read per level, not per item

MISP ships `AnalystData::fetchChildNotesAndOpinions`
(`AnalystData.php:458`), and this phase does not call it. Two reasons,
and the second is the one that matters.

### 7.1 It is two queries per item, recursively

One `Note` find and one `Opinion` find per item per level, then the same
again for every child, and at depth 0 a `hasMoreNotesOrOpinions`
(`AnalystData.php:562`) that is one or two more finds per leaf. Six root
items — which is `8.8.8.8`'s whole thread — is twelve queries before a
single child is counted, and the count grows with the rows returned.

Phase 24 measured the same shape on `Relationship` and recorded it as *a
`find` on `Relationship` is one ACL'd fetch per row*. The pattern the
campaign has settled on since is one query per level over a known uuid
set, which is what §5.2 already does for the first level. A thread of
depth 2 is three queries per model, not two per item.

### 7.2 The recursion memo is never reset

`$fetchedUUIDFromRecursion` (`AnalystData.php:65`) is instance state,
written at `:465`, `:549` and `:555`, and **read at `:460` to short-
circuit** — and nothing clears it. Within one request a second call for
a uuid already walked returns no children.

For MISP's own thread view, which walks one root, this is a cycle guard
and correct. For a page that assembles a union over many roots, the same
note reachable from two anchors yields its replies on the first reach and
nothing on the second — so **which replies the page draws depends on the
order the union happens to iterate its anchors.** That is not a
performance cost; it is an output that changes for reasons the reader
cannot see, and it is a new finding: the contract's row for this method
(`00-contract.md:186`, citing `00-shared.md` §7.9) records only that
*depth 2 is a fetch limit*.

**Recorded, not fixed.** Fixing it means changing a shared model's
lifecycle for every caller, which is not this phase's to do from a value
page. This document reports it, and §11's third call is whether that
report should become a patch.

---

## 8. What a reply rates, and why the thread still draws it

D4 excludes nine rows from a mean and excludes nothing from the page.

The argument for drawing them is the tab's charter. The subject is who
says what about this value, and a disagreement *about a disagreement* is
part of that — the Verdict tab's whole reason for existing is that
disagreement between organisations is the signal. The argument for
excluding them from the aggregate is that the aggregate answers a
narrower question, *where do organisations stand on the value*, and a
reply to a note is not an answer to it.

Both hold at once, which is why the row is drawn in one panel and absent
from the other, and why the standing panel has to say so — a reader who
counts opinions in the thread and compares the total to the histogram
must find the difference explained rather than apparent.

---

## 9. Two things the coverage survey owes this tab

[`value-profile-coverage.md`](../value-profile-coverage.md) §4.5 and §6
place two concepts here, and neither has an endpoint.

### 9.1 Proposals — T8

The survey's argument, §5: *a proposal is how MISP let a third party
disagree before analyst data existed, and this tab's subject is who says
what about the value.* Its conclusion is that the thread **either
includes proposals as claims or excludes them explicitly** — silently
omitting them leaves the tab claiming to show every organisation's view
while dropping the oldest mechanism for expressing one.

23 `shadow_attributes` rows on this instance. Phase 25 has already built
the proposals lane for the Timeline (T10) and phase 22 owns the
occurrence-table rows, so the read is a known quantity; what is open is
whether a proposal is a *claim* in this thread's sense. §11's first call.

**Answered: included, labelled.** `Value::proposalsFor` — phase 25's
reader, both directions, unchanged — supplies them as root thread items
with their own `proposal` kind. What that decided in passing:

- **They filter as their own pill**, which needed no JavaScript: the
  thread's kind filter already matches `data-vp-a-kind-filter` against
  each item's `data-vp-a-kind` generically. The pill appears only when
  the value has proposals, because a permanent `Proposals 0` on a tab
  where every other pill filters to something is a control that does
  nothing.
- **They carry `Open` or `Resolved`, and never *accepted* or
  *discarded*.** Both paths end at `ShadowAttribute::setDeleted`, which
  writes one column; the schema that would tell them apart does not
  exist, so the chip states what MISP recorded and stops.
- **Every date says *(last moved)*.** `shadow_attributes` has no
  `created`, and `setDeleted` stamps `timestamp` at resolution — so a
  resolved proposal sits at its resolution rather than at its proposal.
- **They reach neither the aggregate nor the ledger**, and they do not
  set an organisation's *Last activity*: a proposal is a change somebody
  wants made to a row, and its date is when it last moved rather than
  when that organisation was last heard from.
- **What the proposal proposes is stated by the page, not left in the
  comment** — the comment is often empty, and the value belongs outside
  the markdown renderer, which would read `*` in an indicator as
  emphasis. `2.2.2.2` draws *Proposes 2.2.2.3 in place of 2.2.2.2 on
  attribute 1495259*.

**And it broadened the tab past its own name**, which the maintainer
raised in the same breath as the answer. §11.4.

### 9.2 Event reports — T9

The survey, §4.5: reports are *the natural home for the list* — narrative
analyst content about the value's context, beside the notes and opinions,
with `viewSummary` for a preview. 174 reports on this instance; 8 on
`8.8.8.8`'s events.

The survey forecasts *one new element, probably two* and says **the phase
that adds them adds the rows** to §14.12. Phase 25 built the Timeline's
report lane through its own ACL'd fetch and never
`EventReport::attachReportCountsToEvents` (its D6), and that decision
does not reopen while the defect behind it ships.

**Built as one element, one endpoint and one row — D10.**
`value_analyst_reports`, third on the tab, under
`viewAnalystReports`. Three things it settles:

- **A panel and not more thread items.** A report is a document, not a
  turn in a conversation; dropped between two one-line notes it buries
  both. The thread stayed a thread and the list became a list.
- **Its own endpoint**, because it is one `fetchReports` over the value's
  events and should not wait on the thread's five-anchor union — which
  is the same reasoning that split this tab into two endpoints in the
  first place. It costs 2 to 18 queries where the thread costs 7 to 28.
- **The extract is the report's own opening, not a summary.** MISP holds
  no summary of a report and this page will not write one: an abstract
  the page invented would be the page's claim about somebody else's
  document. The first 600 characters are carried, the markdown is
  stripped for the extract — MISP's `@![attribute](uuid)` element
  references included, since they render as a card in the report and as
  a uuid in a three-line preview — and the full length is printed beside
  it. `viewSummary` was not used: it is a controller action rendering a
  modal, and this panel is a server-rendered list.

The `fetchReports` half of phase 25's D6 carries over unchanged, and
`attachReportCountsToEvents` is not reached from here either.

---

## 10. §14.6, and the two notes on this tab

### 10.1 The third computed-judgement panel — D7

**§14.6's table has no row for this tab.** Phase 25 found the same
omission for Timeline and added its own row; this phase adds two, and
they point in opposite directions.

`05-analyst.md` §11 records that **the withheld-by-ACL count is not
obtainable** — `buildConditions($user)` scopes the fetch and there is no
unscoped count to subtract — and concludes that the tab's note therefore
states existence without a number. **That conclusion does not survive
§14.6.** The whole of §14.6 is that a panel must not be usable as an
existence oracle, and *"analyst data exists here that you cannot see"* is
an existence oracle with the number filed off. It is a worse one than a
count, not a milder one: it is the same disclosure with no way to gauge
it. The band is removed, as it was from Occurrences and Timeline.

**But the standing panel then earns the exception, and it is the third
member the contract predicted.** §14.6's rule, after phase 23 found the
second: *a panel that renders a computed judgement gets a permanent
caveat; a panel that renders a count does not* — and *"a later phase that
computes rather than counts should expect to add the third."*

This phase computes. The mean, the buckets, the empty band and the
per-organisation ledger are all derived from the opinions this reader may
see, and `buildConditions` means two readers may see different sets. Two
colleagues can therefore read **different means off the same value on the
same afternoon**, which is exactly the Verdict tab's case and exactly the
decay score's. So `value_analyst_standing` carries the permanent line:
always shown, on every value, identical for every reader, including
values with nothing hidden — because a line that never varies carries no
information, which is what separates it from the band above.

`value_analyst_thread` gets nothing. It renders rows, not a judgement.

T10 is both halves: the band removed, the caveat added, and the two rows
written into §14.6's table.

### 10.2 The badge is a fixture literal — T11

`00-contract.md` §14.13: *"whoever converts a tab next: check its badge.
Relationships, Enrichment and Analyst are each carrying a fixture literal
that will start lying the day their panels stop doing so."*

This is that day for Analyst. Unlike Timeline, which has no badge and so
had nothing to check, this tab has a number on it that comes from the
fixture. Three ways out — read it from the union, drop it, or leave it
and say so — and the phase must take one deliberately. The cost argument
that killed Timeline's badge applies here too: a badge that agreed with
this panel would have to do this panel's work.

**Dropped**, which is the second of the three, and for both of the
reasons the Timeline and History tabs already carry in
`Values/view.ctp`:

1. **It is the viewer's count.** `buildConditions` scopes every note and
   opinion, and §12.5's seventh check is a CIRCL org admin reading four
   items on `8.8.8.8` where a site admin reads six. Two readers would
   read two numbers off one value's tab bar.
2. **It would cost the panel's own work.** 7 to 28 queries, at page
   load, on a tab nobody may open.

Taken by removing the `count` key from the tab's registry entry rather
than by unsetting it in `forTabCounts` — `view.ctp` already reads
`$tab['count'] ?? null`, so a countless tab is a tab that declares no
count, which is how Timeline and History spell the same thing.
`$counts['analyst']` is still produced by the fixture and now read by
nobody.

The Overview's preview card keeps its own numbers and its own lie; that
is §11's second call and not this row.

---

## 11. Three calls this phase cannot take on its own

Each of these changes what gets built, none is settled by measurement,
and taking them in passing would be the wrong way to take them.

**All three were put to the maintainer on 2026-09-05, before any of the
phase was built, and all three came back.** The answers are recorded
under each, and where an answer differs from the recommendation the
recommendation is left standing rather than edited away.

| # | Recommended | Decided | Built |
|---|---|---|---|
| 1 | include, labelled | **include, labelled** — with a naming question raised alongside it, §11.4 | §9.1 |
| 2 | convert the Overview card here | **leave it on the fixture, and record that it lies** | §1.2, §10.2 |
| 3 | route around the memo and record it | **route around and record** | §7.2 |

**1. Are proposals claims in this thread?** §9.1. Including them makes
the thread the complete record of third-party disagreement and mixes two
mechanisms with different ACLs, different lifecycles and no shared
author model. Excluding them is defensible only if the tab says it in
words. *Recommendation: include, labelled as proposals, because the
survey's argument is sound and an unlabelled omission is the one option
that is clearly wrong.*

**2. Does the Overview's analyst preview move with this phase?**
`05-analyst.md` §3 says it stays untouched; §14.13 says its badge starts
lying the moment these panels go live. The card is 155 lines and reads
the same union. *Recommendation: convert it here.* Leaving a fixture card
next to a live tab reproduces exactly the Occurrences banner problem
phase 22 spent a section on — but it is the Overview's row on the board,
so the campaign's own rule says its phase owns it.

**3. Does §7.2's memo finding become a patch?** The order-dependence is
real and is in a shared model. This phase can route around it and
record it, which is the campaign's default for a shipped defect, or it
can fix `AnalystData` for every caller. *Recommendation: route around
and record.* It is a value page's job to read, and a lifecycle change to
a shared model deserves its own review — but the finding is new, so the
default has not actually been argued for this case.

**One thing that is not a call.** The colour contradiction
`05-analyst.md` §11 ends on — the Overview preview paints *Agree* green,
the Verdict histogram paints everything above 50 red — needs settling
before either goes live, and the tab already decided which way it goes:
it unifies on the Verdict reading. What is unresolved is the *Overview
card*, which is call 2 wearing a different hat.

**Call 2's answer makes that contradiction visible rather than
theoretical**, and it is the price the answer names. The Overview's card
now reads the fixture's opinions beside three panels reading the
database, so on `8.8.8.8` a reader meets one set of numbers on the
Overview and a different set one tab across. That is exactly the
Occurrences banner problem phase 22 spent a section on, taken
deliberately this time: the campaign's rule is that a tab's row belongs
to the tab's phase, and buying consistency by reaching into the
Overview's row is how a board stops describing the code.

### 11.4 A naming question the first answer raised — settled

**Settled 2026-09-05: the tab is *Collaboration*.** The maintainer
raised the question, offered that name and left the choice open; it is
the one taken, and §17 is what it cost.

What follows is the argument as it stood when the question was open.

Including proposals broadens the thread past what its tab is called.
*Analyst data* names a MISP feature — notes, opinions and relationships,
the three `AnalystData` subclasses — and the tab now also carries
proposals, which are `shadow_attributes` and predate that feature, and a
list of event reports, which are neither. The tab's subject has become
*everything anybody has said about this value*, and its name still names
one of the four mechanisms.

**Raised by the maintainer alongside call 1 and not taken here.**
*Collaboration* was the suggestion. The panels have moved in the
meantime, which is the cheap half:

- the thread's title reads **Notes, opinions and proposals** on a value
  that has proposals, and *Notes and opinions* on one that does not;
- the new report panel is titled **Event reports**;
- the standing panel is unchanged — it really is about analyst opinions
  and nothing else.

What is left is the **tab label** in `Values/view.ctp:628` and the
matching entries in `value-profile-page.md` and `05-analyst.md`, whose
filenames carry the old name. Renaming a tab renames it in every
document that cites it, so it is one edit and a sweep, and it is a
naming decision rather than a build one.

---

## 12. Verification — as run

**Run 2026-09-05.** Eight values across three endpoints, fetched as real
authenticated HTTP fragments and read back as rendered text; a second
reader class through the facade; and the whole tab driven in Chromium
for the controls and both themes. Seventeen checks, all passing —
§12.5.

### 12.1 The shape

Per `00-contract.md` §14 and the four phases before it: both endpoints
return 200 for every verification value for both reader classes; the
query count is measured and lands on §14.12; the raw fragment and the
browser agree; both themes; no console error. The audit log is not this
tab's dependency, so phase 25's on/off matrix does not apply.

### 12.2 The values, and why five of them are new

`8.8.8.8` is the campaign's default and it exercises **only the event
anchor** — 26 occurrences across 20 events, 2 notes and 4 opinions all
event-level, 8 event reports. Verify on it alone and the Attribute branch
of §5.2 never executes.

These five carry attribute-anchored analyst data, and all are small:

| Value | Occurrences | Events | Attribute-anchored |
|---|---|---|---|
| `https://google.com` | 2 | 2 | 3 notes |
| `https://circl.lu` | 2 | 2 | 3 notes |
| `google.com` | 9 | 9 | 1 note |
| `6.6.6.6` | 2 | 2 | 1 note |
| `127.0.0.1` | 4 | 4 | 1 opinion |

Plus a value with none of any kind, for the empty states, and a
non-ADMIN reader on a value whose analyst rows are partly out of scope —
phase 25's third trap, which is that all ACL models look identical to a
site admin.

### 12.3 What the aggregate must be checked against

Not the fixture. The per-value opinion set read straight from
`opinions`, with D4's exclusions applied by hand, against what the panel
prints — mean, bucket heights, the empty band, and the ledger's per-org
rows. §3.1 is the instance-wide control.

**The five were replaced by six chosen against the branches rather than
against the anchor kind alone.** The table above was built from a query
for attribute-anchored notes; three of its five turned out to exercise
nothing the others did not, while three branches it named no value for —
the object anchor, the cluster anchor and the nesting — each needed one.
What was actually verified is §12.5.

### 12.3 What the aggregate must be checked against

Not the fixture. The per-value opinion set read straight from
`opinions`, with D4's exclusions applied by hand, against what the panel
prints — mean, bucket heights, the empty band, and the ledger's per-org
rows. §3.1 is the instance-wide control.

**Checked on `8.8.8.8`.** The four opinions in `opinions` are 100, 100,
80 and 10, all ADMIN's, all on event 47. The panel prints *4 opinions
from 1 organisation · two positions 70 points apart · nothing between 10
and 80*, four lanes in that order, *7 of ten bands unoccupied*, and a
mean of **72.5** struck through as one nobody holds — the nearest actual
opinion is 80, which is 7.5 away and so past the half-band threshold.
Every one of those numbers follows from the four scores by hand.

### 12.4 The board — T12

**Three cells, not two.** §14.12 now carries `viewAnalystStanding` and
`viewAnalystThread` at 7–28 queries and the new `viewAnalystReports` at
2–18, each scaling with *how much analyst content exists* rather than
with the value's size — `443` has 48,255 occurrences and costs 8 and 2.
§14.13's phase row, §14.6's two new rows and the badge note under
§14.11 were written in the same pass. The Overview's row is untouched,
which is call 2's answer.

### 12.5 What ran, and what did not

| # | Check | Result |
|---|---|---|
| 1 | `php -l` over every changed file | clean. `parallel-lint` not run — no `app/Vendor/` in this checkout |
| 2 | **Every anchor kind executes** | 4/4. Attribute — `google.com`, a note on attribute 1 with the `domain in #1` chip. Event — `8.8.8.8`, four opinions on event 47. Object — `94.156.177.68`, the note on object 24033 rendering as `url in #1545` with the object glyph. Cluster — a sha256 in event 1522, drawing the `Employment cluster` note through the galaxy chip |
| 3 | **The nesting, to its full depth** | `google.com`. Note on an attribute → opinion 80/100 on that note → note on that opinion, rendered `vpa-reply` then `vpa-reply-2`, with *the note above* and *the opinion above* on the two replies. Counts read *5 items · 4 opinions, 1 note · 2 replies* |
| 4 | **D4's exclusion** | same value: thread 5 items, standing *4 opinions*. The 80/100 on a note is drawn, marked, and absent from the mean |
| 5 | **The aggregate by hand** | §12.3 |
| 6 | **Both empty states** | `b1`, a value in events with no analyst content at all. Standing renders *No organisation has recorded an opinion on this value*; the thread renders *Nobody has written a note or an opinion*, and keeps its composer, which still names the 3 occurrences it would offer |
| 7 | **A non-site-admin reader** | through the facade, as CIRCL's org admin. `8.8.8.8` goes 6 items → 4: the THA-CERT note and the ADMIN event note drop, the four community-distributed opinions stay. This is §14.6's case made concrete and it is why the standing panel took the permanent caveat |
| 8 | **Proposals** | `8.8.8.8` draws one *Open* proposal reading *Proposes Network activity / ip-dst for attribute 2, whose value it leaves alone*; `2.2.2.2` draws two, one of them *Proposes 2.2.2.3 in place of 2.2.2.2 on attribute 1495259* — the row `value-profile-coverage.md` §5.1 cites. Neither reaches the ledger or moves an organisation's last activity |
| 9 | **The report list** | `8.8.8.8`, 8 reports on 20 events, newest change first, each with its event chip, organisation, *(last changed)* date, distribution and length. Extracts survive MISP's own `@![attribute](uuid)` references, image links and backslash escapes |
| 10 | **The occurrence cap is stated** | `443`. Both live panels now say the union was built from the newest 300 occurrences, and the report panel says its list covers the 19 events those reach — outside the empty branch, so *nobody has written one* can no longer stand over a slice |
| 11 | **The ACL entry for the new endpoint** | `queryACL/findMissingFunctionNames` lists only this controller's private helpers. `viewAnalystReports` is mapped |
| 12 | **The tab badge is gone** | the page's tab bar renders *Analyst data* with no pill, while Relationships and Enrichment keep theirs |
| 13 | Query counts and timings | §16.1 |
| 14 | **Both themes** | 5/5, in Chromium. Every new or changed node — `.vpa-proposal-what`, `.vpa-report-extract`, `.vpa-report-name`, the standing panel's caveat and `.vpa-chip` — resolves to a token in both, and contrast against its own painted background runs **13.0–15.4 in light and 8.8–11.9 in dark**. No new rule carries a fixed colour |
| 15 | **The no-JavaScript render** | holds, and it is what every fragment above was fetched as |
| 16 | **The filter and sort controls with real clicks** | pass. On `8.8.8.8`: `Notes` leaves 2 items, `Opinions` 4, the new `Proposals` 1, `All` restores 7; `Oldest` reverses the seven dates exactly and `Newest` restores them. The proposal pill needed no JavaScript — `refreshAnalyst` already matches `data-vp-a-kind-filter` against each item's `data-vp-a-kind` generically |
| 17 | **The whole tab in a real browser** | all three panels resolve after the lazy load — 4 ledger lanes, 7 thread items of which 1 is a proposal, 8 report rows, the permanent caveat present — with **no page error and no console error** |

Checks 14, 16 and 17 are
[`26a-analyst-check.mjs`](26a-analyst-check.mjs), which logs in, opens
the tab, waits for the three fragments, clicks every pill and both sort
buttons, and measures computed colour against each node's own painted
background in each theme. Run it with `node`; it prints one JSON report.

---

## 13. Deferred, with the cost named

**Markdown rendering — D6.** `Analyst_data/thread.ctp` prints notes
`pre-line` with no parser. `markdown-it.js` ships for event reports, but
there is no per-note markup flag — `language` is a natural-language code
— so enabling it here enables it for every note on the instance,
including notes authored by people who never opted into markup. Cost: a
note written with `*` or `_` in prose renders as emphasis on this page
and as literal text in MISP's own thread view. That inconsistency is the
price, and it is smaller than the alternative.

**Grouping below organisation.** `authors` is free text, not a user
reference; only `Org` and `Orgc` are relational. The ledger's finest
grain is the organisation, and no per-analyst rollup is reliable. Cost:
two people at one organisation who disagree appear as one lane.

**`A2`'s element reuse.** `05-analyst.md` §1 deferred rendering the
distribution through `value_verdict_opinions.ctp` rather than marking it
up again, and that deferral stands — `.vp-hist*` is mandatory either way,
so the two histograms already look like one object. Collapsing them into
one element stays the obvious cleanup, and it is cheaper once the Verdict
tab is unblocked and both are live.

**Pagination.** There is none across the union and there cannot be one:
the rows come from up to five anchor sets in two tables. If a value is
ever found whose thread is too long to render, the answer is a cap with a
stated remainder — phase 25's D2 pattern — and not a page parameter.

---

## 16. The build log

Six commits on `worktree-attribute-value-page-brief`, in the order they
landed. Three of code, three of record — plus §17's two, which came
after the phase was recorded as built and are listed there.

| Commit | What |
|---|---|
| `c8685de2a` | the union, the level read, the aggregate, the ledger, T10's two halves — T1–T7 and T10 |
| `6198ba359` | proposals in the thread, and the tab badge dropped — T8 and T11 |
| `471938122` | the report panel and its endpoint, plus the occurrence-cap notes on all three — T9 |
| `3d11ae64c` | the documents: §14.6, §14.12, §14.13 and §14.11's badge note in the contract, this board and this section — T12 |
| `74c673e5b` | the browser pass, closing the two checks §12.5 had left owed |
| `30128e63c` | `value-profile-page.md` §1.4, the tab-level half of T12 |

**What was touched outside this tab**, since each is a shared surface:

- `Value::occurrenceUuidsFor` gained `Object.name`. Free — the `Object`
  join is already there for the uuid beside it — and it is what lets the
  attachment chip print *url in #1545* rather than a uuid.
- `ValuesController::renderRelationPanel` became `renderLivePanel`. The
  name had already stopped being true at `viewExternal`; this phase is
  the third tab to use it.
- `ACLComponent` gained one entry, `viewAnalystReports`.

### 16.1 What it costs

Per endpoint, site admin, measured through the facade on a quiet box
(load average 1.5) with the query log's 200-row cap lifted:

| Value | Standing | Thread | Reports | Q |
|---|---|---|---|---|
| `8.8.8.8` | 13 ms | 13 ms | 6 ms | 22 / 22 / 18 |
| `google.com` | 12 ms | 11 ms | 5 ms | 28 / 28 / 16 |
| `2.2.2.2` | 7 ms | 7 ms | 3 ms | 12 / 12 / 10 |
| `94.156.177.68` | 7 ms | 6 ms | 2 ms | 14 / 14 / 4 |
| `b1` | 3 ms | 3 ms | 1 ms | 7 / 7 / 2 |
| `443` | 111 ms | 114 ms | 100 ms | 8 / 8 / 2 |

**The query count tracks how much analyst content exists, not the
value's size** — which is §14.4's batching rule holding. `443` has
48,255 occurrences and is the *cheapest* value on the board in queries,
because nothing is written about it: the union finds no anchors with
rows and the level read stops immediately. `google.com` is the most
expensive at 28 and has nine occurrences, because its thread nests three
deep and each level is two more reads.

**`443`'s ~100 ms is `Value::occurrenceUuidsFor` and not this tab.**
It is the same component phase 25 measured at 1,067 ms on the same
value; the difference is the cap — 300 rows here against that phase's
larger read — and it is the whole of all three endpoints' time on that
value.

**Where the queries go**, on a value with content: one occurrence read,
two tag reads and one `fetchGalaxyClusters` for the anchor set, then two
per thread level including the probe past the last drawn one, then two
for proposals. The ACL machinery behind `fetchGalaxyClusters` and
`fetchReports` accounts for the rest.

### 16.2 Two findings for whoever owns these models

Neither is this phase's to fix and both are recorded rather than
absorbed.

1. **`AnalystData::$fetchedUUIDFromRecursion` is never cleared within a
   request** — §7.2. Routed around here by not calling the method at
   all, which is call 3's answer, so this page cannot be the thing that
   surfaces it. It remains true for every caller of
   `fetchChildNotesAndOpinions`.
2. **Eleven `notes` rows name an organisation that does not exist**, and
   `rearrangeOrganisation` returns a record with a null name rather than
   saying so. Every reader of analyst data on this instance prints
   *Unknown organisation* for them; this page groups on the uuid so at
   least two such organisations stay apart (§6.4).

---

## 17. After the build — the rename, and a bug the ledger shipped with

Two changes on 2026-09-05, after §12's verification had run and the
phase was recorded as built. Both came from the maintainer reading the
result, which is the pass phase 25 got over its closed phase too.

| Commit | What |
|---|---|
| `f649ea10f` | the ledger numeral flips near the ends of the axis — §17.2 |
| `530020069` | the tab is renamed, the thread's title stops varying, the report panel gets its missing skeleton, and this section — §17.1 |

The fix is committed before the rename, and the rename carries both
halves of this section, so §17.2 describes code that already landed
rather than code the reader has to take on trust.

### 17.1 The tab is *Collaboration* — §11.4's open question, closed

**Taken as offered.** *Analyst data* named a MISP feature — the three
`AnalystData` subclasses — and this phase put two things on the tab that
are not it: proposals, which are `shadow_attributes` and predate the
feature, and event reports, which are neither. What the tab is *about*
is everything anybody has said about this value, and that is the one
axis separating it from the rest of the page: every other tab is
machine-derived. *Collaboration* is the only single word that covers a
note, an opinion, a proposal and a report without stretching one of
them, and a proposal is literally somebody else's edit offered to your
event.

**The id did not move.** `#tab-analyst` is an address — the Overview
card's *Open thread* button points at it, and anything bookmarked does
too — and an id is not a name. Renaming it would break those in order to
rename nothing a reader can see. The three elements keep their
`value_analyst_*` filenames for the same reason, and this document keeps
its own.

**Two things renamed with it, and one deliberately not:**

- The thread panel's title is now **Notes, opinions and proposals**, and
  it is unconditional. It had been conditional on the value having any —
  which meant the loading skeleton could not match it, so a value with
  proposals renamed its own panel mid-load. Naming what a panel can hold
  rather than what this value has is also what *Tags and galaxies* on
  the Overview already does.
- **The reports panel had no loading skeleton at all**, found while
  wiring the one above: `panelChrome` had no entry for
  `viewAnalystReports`, so the card arrived with nothing where both its
  siblings show one — which on a tab of three reads as a panel that
  failed rather than one still loading. Added.
- The **Overview's preview card keeps the title *Analyst data***. It
  shows notes and opinions and nothing else, so the name is still true
  of it, and it is the Overview's row by §11's second call. Its *Open
  thread* button never named the tab, so nothing there had to change.

### 17.2 The ledger's score numeral left its lane at 100

**Reported by the maintainer, and it shipped in the phase's first
commit.** `.vpa-lane-val` was placed 13px *outward* from its dot — away
from the pivot, at the end of the bar it measures — which is right
everywhere except at the ends of the axis, where outward is off the
lane. Measured on `8.8.8.8`, whose two `100`s made it visible: the
numeral sat **34.3px past its lane's right edge**, the grid gap to the
next column is 8px, and the *Reads it as* cell begins there. The `100`
was drawn on top of the word *agrees*.

Symmetric at the other end and not hypothetical: the instance holds six
opinions at 0 and four at 10, and `8.8.8.8`'s own 10 overflowed the left
edge into the *Organisation* cell — by less, because a two-digit numeral
is narrower, which is why only the right-hand collision was visible
enough to report.

**Fixed by flipping, not by clamping.** Near either end the numeral goes
to the *inward* side of its dot and takes a backing, since inward means
over its own bar and `--vpa-side-ink` on `--vpa-side` is one hue on
itself. Which property is used is the outward side XOR the flip, which
is the whole of the logic.

Clamping — pinning the numeral at the lane's edge and letting the
percentage run past it — was the other option and is worse: between 96
and 100 the dot walks over the pinned numeral, which trades a collision
with the next column for a collision with the mark the numeral is
labelling.

**The thresholds are derived from the layout, not chosen.**
`.vpa-ledger`'s lane column is `minmax(320px, 1fr)`, so 320px is the
narrowest the lane ever gets — below that the ledger scrolls instead of
shrinking. The numeral needs its 13px offset plus its own width, and it
is monospace with `tabular-nums`: 3ch ≈ 21px at three digits, 2ch ≈ 14px
at two. 34px of 320px is 10.7% of the axis and 27px is 8.5%, which puts
the flip at **88 and at 12** rather than at a rounder pair. The
asymmetry is the third digit.

**Verified at both extremes of the layout**, not only at the width it
was found on — [`26a-lane-geometry.mjs`](26a-lane-geometry.mjs), which
measures every numeral against its lane's edges, its own dot and the
reading cell, at a wide viewport and at one narrow enough to drive the
lane to its 320px floor:

| Lane | Score | Placement | Inside the lane | Clear of its dot | Hits *Reads it as* |
|---|---|---|---|---|---|
| 918.8px | 100 | flipped | 13.0px | 6.5px | no |
| 918.8px | 80 | outward | 156.5px | 6.5px | no |
| 918.8px | 10 | flipped | 104.9px | 6.5px | no |
| **320px** | 100 | flipped | 13.0px | 6.5px | no |
| **320px** | 80 | outward | 36.8px | 6.5px | no |
| **320px** | 10 | flipped | 45.0px | 6.5px | no |

**Every numeral keeps the same 6.5px clearance from its mark whether it
flipped or not**, which is the geometry mirroring rather than a second
rule: 13px from the dot's centre, and the dot's radius is 6.5px.

**One thing measured and left alone.** The thread's own mini scale
(`.vpa-scale-dot`) overflows its track by 4.5px at 100/100, because it
is a marker centred on the axis end and half of it is past that end —
the ledger's dot does the same. It collides with nothing, it is the
convention both scales share, and clipping it would move the mark off
the value it marks.

---

## 18. A second reading — links, and a proposal that looked like a note

Two more from the maintainer on 2026-09-05, reading the built tab.

### 18.1 The page knew addresses and kept them

**Reported as an inconsistency, and it was one.** The report list linked
its report titles and nothing else, so a row could read *#177 Event
created via the API as an example* — naming an event, its id and its
title — with no way to open it. The thread was worse: **every** one of
its attachment chips named a record and none of them was a link.

The rule taken is phase 25's for the Timeline, arriving one tab later:
**a chip that names a record is a link to that record.** What that
turned out to cover:

| Where | Names | Now opens |
|---|---|---|
| thread chip | an event | `/events/view2/<id>` |
| thread chip | an object | the event, on its Objects tab |
| thread chip | an attribute | the event, on its Attributes tab |
| thread chip | a galaxy cluster | `/galaxy_clusters/view/<id>`, the address three other panels here already use |
| thread meta | the organisation | `/organisations/view/<id>` |
| report row | its event | the event, on its Reports tab |
| report row | the organisation | `/organisations/view/<id>` |
| ledger row | the organisation | `/organisations/view/<id>` |

**Two chips are deliberately still inert**, and they are the two with
nowhere to go: a reply's *the note above*, whose target is drawn
directly above it on the same screen, and an unresolved target, which is
the whole point of §5.4. The report list's *N characters* chip is not a
link either — it names a length, not a record.

**Three ids had to be carried to make it possible**, and each was free:
`GalaxyCluster.id` in the anchor (`fetchGalaxyClusters` already returns
it), `Orgc.id` on a thread item (`rearrangeOrganisation` already
contains it), and `Org.id` on a proposal — one field added to
`Value::proposalsFor`, whose contain already joins `Org` for the name
beside it. An organisation that no longer resolves gets no link, which
is the same eleven rows §6.4 is about: no name to print and no page to
open.

**Verified** by extracting every `href` the three panels emit and
requesting each one: 42 links on `8.8.8.8`, and `/events/view2/177`,
`/galaxy_clusters/view/24248`, `/organisations/view/1`,
`/organisations/view/9`, `/eventReports/view/155` and
`/events/view2/3325` all return 200. `26a-analyst-check.mjs` now also
lists every `.vpa-chip` that is *not* an anchor, so a chip added later
without a link shows up as a named string rather than as silence.

### 18.2 A proposal read as a note with a different word on it

**It carried a glyph, a label and a status badge, and everything else
about it was a note**: same border, same ground, same prose block. On a
thread where a proposal is the one item that is not somebody's writing
*about* the value — it is an edit somebody wants made *to a row* — that
is the wrong shape.

**Colour first, and borrowed rather than invented.** A proposal now
takes `--vp-tl-proposal`, which is the Timeline's proposals lane: the
same rows on two surfaces, so a reader who has met one recognises the
other. It was chosen there against a colour-vision sweep and clears
6.7:1 on the light ground, so nothing had to be re-derived here.

**A specificity bug found doing it.** `.vp-analyst.vpa-side-none` sets
the left border grey at (0,2,0), and a proposal carries `vpa-side-none`
because it takes no position on the agree/dispute axis — so a
`.vp-analyst-proposal` rule at (0,1,0) lost silently and the card kept a
note's border while its label went blue. *Takes no side* and *is not an
opinion at all* are different things and only one of them has a colour,
so the rule is doubled to `.vp-analyst.vp-analyst-proposal` and wins on
purpose rather than by luck. Measured: the proposal card's left border
is now `rgb(29, 78, 216)` against a note's `rgb(222, 226, 230)` in
light, and `rgb(110, 168, 254)` against `rgb(73, 80, 87)` in dark.

**Then the sentence became a change.** It had read *Proposes 2.2.2.3 in
place of 2.2.2.2 on attribute 1495259*, which buries the only two
strings the reader is comparing in the middle of a line of prose. It is
now a strip: an op chip, the old value struck through, an arrow, the new
value, the category and type, and the target linked at the far end —
both values in monospace, so `2.2.2.2` and `2.2.2.3` differ visibly
rather than on a second reading.

`ValueProfile::proposalOp` names which of the four a row is, because
`shadow_attributes` says it in three columns and the panel draws each
one differently:

| Op | What the schema says | Drawn as |
|---|---|---|
| `delete` | `proposal_to_delete = 1` | the value, struck through, with no arrow |
| `add` | `old_id = 0` — standing behind no attribute | the value alone, and *a new attribute on #N* |
| `replace` | a target whose value differs | `old → new` |
| `refile` | a target whose value is **the same** | the value, unstruck, and *value unchanged* — because an arrow between two identical strings is a diff that says nothing |

`2.2.2.2` holds one of each of the last two, and both render correctly.

**The state is in the form as well as in the badge.** An open proposal's
strip is dashed — it is not part of the event yet — and a resolved one
is solid, greyed and stepped back. The badge still carries the word, and
still refuses to say *accepted* or *discarded*, because `setDeleted`
writes one column for both.

**The attachment chip is dropped for proposals.** The change strip names
the target with the attribute id the chip does not carry, so keeping
both was the same record said twice at two grains.

**Contrast, both themes**, every node in the new treatment measured
against its own painted background: **6.7–15.4 in light and 6.4–11.9 in
dark**, the floor in each being the op chip's inverted text on the
proposal blue.

**One harness bug fixed to get those numbers.** `color-mix()` computes
to `color(srgb r g b)` with channels in 0–1, not to `rgb()` with
channels in 0–255, and the checker divided both by 255 — so it reported
the change strip's near-white ground at **1.35:1**, and would have
reported every mixed surface on this page as a failure. It parses both
forms now, which is why §12.5's row 14 numbers are worth trusting and
were worth re-running.
