# PRD: Value Profile — Analyst data goes live

**Phase 26**, the fifth live phase. Converts `viewAnalystStanding` and
`viewAnalystThread` — the tab's two endpoints and the two panels inside
them — from `ValueProfileFixture` to the database. Depends on
[`00-contract.md`](00-contract.md) §14 and on the four phases before it,
whose seam, facade and tools this extends. The tab's fixture-era design
is [`05-analyst.md`](../value-profile-tabs/05-analyst.md), and that
document's §11 is the list this phase exists to close.

**Opened 2026-09-05.** §1 is the task board, §1.1 the decisions taken
before building anything, §1.2 what a session picking this up cold needs
to know, and **§11 the three calls this phase cannot take on its own.**

**A naming collision, so nobody trips.** The `26-object-graph-*.php` and
`26-panel-harness.mjs` files in this directory are **not** this phase's.
They are named after §26 and §27 of [`24-relationships.md`](24-relationships.md)
— phase 24's object re-founding — and predate this document. Every
artifact this phase adds is prefixed `26a-`.

---

## 1. The task board

Every row is `todo` until its own section says otherwise, and a row moves
to `done` only when §12's verification has run against it.

| # | Task | Section | Status |
|---|---|---|---|
| T1 | `ValueProfile::forAnalystStanding` and `forAnalystThread` — the two facade methods | §4 | todo |
| T2 | The anchor set: phase 25's union, widened by two anchors | §5 | todo |
| T3 | The thread read per level, not per item — no `fetchChildNotesAndOpinions` | §7 | todo |
| T4 | The aggregate computed in the facade: mean, buckets, gap, per-org rollup | §6 | todo |
| T5 | What an opinion on a note rates, and what the aggregate therefore counts | §6.2, §8 | todo |
| T6 | The per-organisation ledger, on the built `B4` design | §4, `05-analyst.md` §16.3 | todo |
| T7 | Orphan and unknown anchors survive the render | §5.4 | todo |
| T8 | Proposals in the thread, or excluded in words | §9.1 | todo |
| T9 | Event reports as the narrative list | §9.2 | todo |
| T10 | §14.6: the ACL band removed, the standing panel's permanent caveat added, both rows written into the table | §10.1 | todo |
| T11 | The tab badge, which is a fixture literal today | §10.2 | todo |
| T12 | The board rows: §14.12's two `—` cells, and this document's numbers | §12.4 | todo |

**Where the phase stands. Nothing is built.** Both endpoints still call
`profileFor()`. The two elements exist and are 605 and 635 lines of
markup rendering the fixture's array; neither is this phase's to redraw.

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

### 1.2 Starting from cold

**Nothing is built.** `ValuesController::viewAnalystStanding`
(`ValuesController.php:553`) and `viewAnalystThread`
(`ValuesController.php:561`) both call `profileFor()`, which is
`ValueProfileFixture`. What already exists and is *not* the work: both
endpoints, their `ACLComponent` entries (`ACLComponent.php:1081` and
`:1082`, `theming_enabled`), the skeleton descriptors in
`Values/view.ctp`, and the two elements —
`value_analyst_standing.ctp` (605 lines) and `value_analyst_thread.ctp`
(635 lines) — which render the whole tab against the fixture's array.

**`viewAnalystPreview` is not this phase's.** It is the Overview's card
(`ACLComponent.php:1054`, `value_analyst_preview.ctp`, 155 lines), and
`05-analyst.md` §3 leaves it untouched. It will start lying the day these
two panels stop — see §10.2, which is a row on this board precisely so
the lie is deliberate rather than inherited.

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

Two facade methods and no new endpoint, no new element and no new route.

| Action | Facade | Element | Panel |
|---|---|---|---|
| `viewAnalystStanding` | `forAnalystStanding` | `value_analyst_standing` | the position strip, the histogram, the per-organisation ledger |
| `viewAnalystThread` | `forAnalystThread` | `value_analyst_thread` | the chronological thread, nested to depth 2, and the composer |

Both take `array $user, $value, array $options` and return the tab's
existing array shape. The ledger's built design is `05-analyst.md` §16.3
(`B4`, the lane ledger with the tug-bar) and its sorting is §16.6 — this
phase feeds those, it does not redraw them.

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

---

## 11. Three calls this phase cannot take on its own

Each of these changes what gets built, none is settled by measurement,
and taking them in passing would be the wrong way to take them.

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

---

## 12. Verification — the plan, and the values

Nothing here has run. This is what §12 will be filled with.

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

### 12.4 The board — T12

Two `—` cells on §14.12, `viewAnalystStanding` and `viewAnalystThread`,
plus whatever §9.2's element adds and whatever call 2 decides about the
Overview's row. A row moves off `—` only when this document records the
same numbers.

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
