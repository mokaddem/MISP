# PRD: Value Profile — Timeline goes live

**Phase 25**, the fourth live phase. Converts `value_timeline` — the tab's
one endpoint and the three regions inside it — from `ValueProfileFixture`
to the database. Depends on [`00-contract.md`](00-contract.md) §14 and on
the three phases before it, whose seam, facade and tools this extends. The
tab's fixture-era design is
[`06-timeline.md`](../value-profile-tabs/06-timeline.md); what MISP can and
cannot date for it is `value-profile-page.md` §8.2, and this phase is the
one §8.2 named as having to close its open choice.

**Opened 2026-09-04.** Nothing is built yet. §1 is the task board, §1.1
the eight decisions the phase took before building anything, and §1.2 what
a session picking this up cold needs to know before it writes a line.

---

## 1. The task board

Every row is `todo` until its own section says otherwise, and a row moves
to `done` only when §14's verification has run against it.

| # | Task | Section | Status |
|---|---|---|---|
| T1 | `ValueProfile::forTimeline` — the facade method, per-panel | §4, §16 | **done** |
| T2 | The audit reader on the decided ACL model, three model scopes | §5, §5.5 | **done** |
| T3 | The counts/rows split: one grouped aggregate, one capped read | §6, §16.1 | **done** |
| T4 | Sightings lane from phase 23's context, no second read | §11 | **done** |
| T5 | Publications from one `fetchSimpleEvents`, epoch-0 excluded | §11 | **done** |
| T6 | Analyst lane — the occurrence ∪ event union, rows labelled | §8 | **done** |
| T7 | Seen-span lane, no merging, capped, remainder stated | §7 | **done** |
| T8 | Edit lane on the audit rows, with the not-recorded branch kept | §5, §6 | **done** |
| T9 | Undated strip: tags, galaxy clusters, feeds — matched by key | §11, §14.1 | **done** |
| T10 | Proposals lane | §10 | todo |
| T11 | Event reports lane | §10 | todo |
| T12 | The spine's grain taken from the range, not pinned | §9, §16.2 | **done** |
| T13 | Remove the ACL band §14.6 forbids and §14.6's table missed | §12 | **done** |
| T14 | The board rows: §14.12 `viewTimeline`, and this document's numbers | §14.12, §16.5 | **done** |
| T15 | The axis names its years; the ruler follows the brush and names them too | §17.1, §17.2 | **done** |
| T16 | The chronology stops denying entries the counts state | §17.3 | **done** |
| T17 | The brush's mask stops where the plot area does, at any axis height | §18.1 | **done** |
| T18 | `forTimeline` takes a window; the brush fetches one on release | §18.2, §18.3 | **done** |
| T19 | Each lane bands the span it counts and has no row to draw | §19 | **done** |
| T20 | `TIMELINE_ROW_CAP` 300 → 1,000 | §19.5 | todo — held for review of T19 |

**Where the phase stands.** Seventeen of twenty rows are done and the tab reads
the database: the endpoint is wired, five dated lanes and the off-axis strip
are live, and the panel renders with no fixture behind it for either reader
class and with the audit log on or off. **T10 and T11 are the whole of what is
left** — two additive lanes, proposals and event reports, whose fetchers §10
has already chosen and whose absence today is a lane that does not exist rather
than a lane that lies — and T20, which is a constant waiting on a look at the
thing that made it safe to raise. §16 is the build log; §17 to §19 are what the
first reader of the built tab found over three rounds, and T15 to T20 are
that.

Two rows are deliberately not here. The passive-dns lane
(`06-timeline.md` §16) is §15, deferred with its reason. And the tab
badge needs nothing: the registry gives Timeline no count and §14.13's
*"whoever converts a tab next: check its badge"* is satisfied by there
being none to check.

### 1.1 The decisions this phase has taken

Taken when the phase opened, before any of it was built, because each one
changes what gets built rather than how. **A task that contradicts a row
here is a task that has found something, not a task that may proceed** —
reopen the row, in this document, with what it found.

| # | Decided | Where the argument is | What would reopen it |
|---|---|---|---|
| D1 | The value-scoped audit history is the rows about ids the viewer may already see: `Attribute` by occurrence id, `Object` by occurrence object id, `Event` by the ACL'd event ids. Neither of the two models MISP ships is adopted whole | §5 | A viewer class for whom this returns *more* than `__createEventIndexConditions` would grant on the same event. The safety argument is a subset claim, and a subset claim is falsifiable |
| D2 | Counts come from a grouped aggregate, rows from a capped read, and the panel states both numbers. The tab's one-array invariant moves from the template into the facade | §6 | A measured cap at which the chronology stops being useful in practice — not an argument that 300 feels low |
| D3 | The seen lane merges nothing, caps at 25 bars ordered by span start, states the remainder, and draws instants as marks rather than zero-width bars | §7 | An agreed aggregation rule for merging spans, which is precisely what §8.2 records nobody has |
| D4 | Analyst data is the union over the value's occurrences **and** its events, and every row names its target | §8 | The Analyst tab's own phase deciding otherwise — in which case both tabs change together, because they read one union |
| D5 | The spine's grain is planned from the range through `ValueProfileBuckets::plan()`, not pinned at twelve months | §9 | Nothing local. This is a reuse, and it reopens only if `plan()` stops fitting |
| D6 | Proposals and event reports each get a lane; the report lane reads through its own ACL'd fetch and never `EventReport::attachReportCountsToEvents` | §10, §13 | The coverage survey's verdicts changing. The fetch decision does not reopen while that defect ships |
| D7 | The tab's `.vp-acl-note` band and its `acl_note` key are removed, and §14.6's required-changes table gains the row it was missing | §12 | §14.6 itself, which names the oracle risk as the first thing to revisit if it is ever judged acceptable |
| D8 | The passive-dns lane is deferred a second time, with the cost named | §15 | Whoever picks it up; the data and the query both exist |
| D9 | The counts are grouped **per day**, not per month, and every lane hands one up over all of its rows | §16.1 | A grain the panel cannot answer a question at. Day answers all three of its questions; month answers only the widest |
| D10 | The default window is 30 days ending at the value's newest dated entry, clamped to its oldest | §16.2 | A measured reading of what readers open the tab for. The rule this replaced — the calendar month of the newest entry — is *falsified*, not merely disliked: it gives a one-day window to any value whose newest entry falls on the 1st |

**Two rows were taken during the build rather than before it**, against §1.1's
own preference, and both for the same reason: each is a decision the build
*found* — D9 because a month grain cannot answer a 30-day window that straddles
two months, D10 because the first rule tried produced a degenerate window on a
verification value. Neither contradicts a row above; §16 has the working.

**Not decided here, and deliberately.** Whether the publications lane
should draw `ACTION_PUBLISH` audit rows beside the two columns MISP keeps
(§15) — D1's reader already has those rows in hand, which is what makes
the question live rather than academic. And nothing about Enrichment: §2
says why that tab is not this phase's to take, and taking its gating
decision in passing would be the wrong way to take it.

### 1.2 Starting from cold

**Nothing is built.** `ValuesController::viewTimeline` still calls
`profileFor()`, which is `ValueProfileFixture`. What already exists and is
*not* the work: the endpoint, its `ACLComponent` entry
(`ACLComponent.php:1083`, `theming_enabled`), the skeleton descriptor in
`Values/view.ctp`, and `value_timeline.ctp` itself — 1,255 lines that
render the whole tab against the fixture's array.

**Where.** The corpus and the code are both in the
`attribute-value-page-brief` worktree, branch
`worktree-attribute-value-page-brief`. Three other worktrees carry copies
of `prd/` that lag this one; check `git log -1 -- prd/` before believing
any of them.

**Re-derive the numbers before trusting them.**
[`25-timeline-probe.sql`](25-timeline-probe.sql) is every instance
measurement in §3, §5.2, §10 and §11, one block per section, with the
command in its header. The instance is a working dev box and it moves.

**Build order.** T1 and T2 first — the facade method and the audit reader
— because every lane hangs off them and D1 and D2 are the two decisions
that would be expensive to unpick after six lanes are written against
them.

**Three traps, all of which look like success.**

1. **The fixture says the audit log is off; this instance says on.** Read
   only this instance and the not-recorded branch ships untested (§3.1).
2. **A probe matching only `value1` measures a different value than the
   page will.** `443` is a 395-occurrence value on one side and a
   48,255-occurrence value to the seam (§3.3).
3. **Verified as a site admin, all three audit ACL models look
   identical.** §8.2 says this in as many words, and §5.2's first row is
   what it costs: a non-ADMIN reader gets an empty tab under the model
   this phase rejects, and cannot tell it from a quiet value.

---

## 2. Why Timeline goes fourth

§14.13 declines to sequence the campaign and asks whichever phase goes
next to argue for itself. Three arguments, and the first is the weakest.

**The other candidates are blocked, and one of them was re-checked
today.** Overview is partly blocked and Verdict wholly so, both on the
engine that does not exist. Enrichment is blocked on persistence, and the
2026-09-04 re-check found every part of that still true: `Module` is
still `useTable = false`, none of the instance's 106 tables is a
per-value per-module run store or a dismissal store, and
`Event::enrichmentRouter()` still returns at `Event.php:7998` — above its
own `MISP.background_jobs` branch, which is therefore unreachable code
and the interactive path synchronous whatever the setting says. Enrichment
is not a conversion phase at all; it is a schema phase wearing one, and
`04-enrichment.md` §11 states its gating decision plainly enough that
taking it by accident inside a live phase would be the wrong way to take
it. That leaves Analyst data, Timeline and History.

**Timeline holds one named, undecided question, and this is the phase
§8.2 assigned it to.** The audit log can be scoped to a value two ways;
`value-profile-page.md` §8.2 sets both out and closes with *"The choice
between them is a design decision this phase has to make explicitly, not
a detail to settle in the controller."* That is the shape of argument
phase 23 used for Sightings — the hard part is a decision, and parking a
decision while easier tabs land is how a campaign accumulates the debt
§14.11 lists. §5 closes it.

**Going first is worth more than going second here.** `06-timeline.md`
§12 says the edit lane, once the audit log is on, *"is the same union
`07-history.md` assembles. The two tabs should read the same rows;
whichever goes live second reuses the first's scoping."* History is the
larger consumer of that reader and the smaller contributor to it: it is
audit rows and nothing else, where this tab has to make the audit rows sit
on one axis beside six other sources. Deciding the scoping under the tab
that has to reconcile it with everything else, and handing History a
reader it inherits rather than negotiates with, is the cheaper order.

**And it clears a row that closed work is waiting on.**
`24b-relationships.md` §20.2 carries *"The Timeline source lane — waiting
on the Timeline tab's own phase."* That lane is still deferred here (§15),
but the row stops waiting on a phase that has not started.

---

## 3. Three facts about this instance the fixture does not have

§14.8 says a live phase verifies against real instance values because the
four demo values hold nothing real. Doing that first, before writing any
code, turned up three things that change what this phase is.

### 3.1 The audit log is on, and every fixture value says it is off

`MISP.log_new_audit` is `true` in this instance's `config.php:39`, and
`audit_logs` holds **9,512,515 rows** spanning 2024-11-11 to 2026-09-03
across 41 models, 9,192,832 of them `Attribute`.

`ValueProfileFixture::timeline()` hard-codes `audit_recorded` to false
with the comment that the setting defaults to false, which is true of a
default instance and false of this one. So **the tab's live default here
is the branch no fixture value has ever rendered**: the edit lane without
its hatch, drawing a real history rather than one point per occurrence.

That is a gain and a trap. The gain is that the branch `06-timeline.md`
§12 could only describe now has data behind it. The trap is that the
hatched branch is what a default MISP shows, so it is the *more* common
state in the world and the *less* common one here — and a phase verified
only against this instance would ship the honest-about-nothing state
untested. §14 verifies both, by reading with the setting on and by
reading a value whose occurrences predate the log.

### 3.2 The value that has the sightings has no spans

`8.8.8.8` is this campaign's populated value — 53 sightings across 6
organisations and 3 types, 26 occurrences in 20 events, 54 audit rows, 8
event reports on its events. It carries **no `first_seen` at all**, and
neither does any other sightings-rich value on the instance.

Spans are not rare: **179,878** of the instance's 2,922,284 live
attributes carry a `first_seen`, of which 109,320 are real spans
(`first_seen <> last_seen`), 68,083 are instants and 2,475 are open-ended.
They are simply on different values — typosquat-shaped domains and hosts
imported with dates.

So **no single value verifies this tab**, and §14.9 row 7 gets a list
rather than a name. `143.14.244.37` is the span case: 32 occurrences in
one event, all 32 dated, 24 of them real spans, and no sightings at all.

### 3.3 The heaviest history is 162,539 rows, and 162,136 of them are a port

Scoping audit rows to a value's occurrences is cheap on every value this
campaign has used so far — 54 rows on `8.8.8.8`, 407 on
`213.226.123.172`, 1,007 on `193.161.193.99` and its 337 occurrences.

On `443` it is **162,539**.

That is the *attribute* scope. The reader §5 decided has three, and its
three-scope total on `443` is **172,426** — 162,539 attribute rows,
9,493 event rows and 394 object rows. §5.5 has the measurement; the
figure to quote when costing this tab is the larger one.

The reason is the seam, not the log. §14.3 fixes identity as the value and
not the pair, so `Value::conditionsFor` matches `value1` *or* `value2`,
and `443` is 395 occurrences as a value and **47,860 as the port half of a
composite** — 48,255 live occurrences in total, the number phase 24 used
as its heavy case. Split by side: 403 audit rows from the value1
occurrences and 162,136 from the value2 ones.

This is the phase's cost problem, and §6 is what it does about it. It is
worth stating in the general form, because the next phase to touch a
per-occurrence read will meet it too: **a composite's second side is part
of the value's occurrence set, so any per-occurrence fan-out is sized by
the commonest port, protocol or filename on the instance and not by the
value the reader typed.**

---

## 4. What ships

One endpoint, one element, one facade method.

| Endpoint | Element | Facade method |
|---|---|---|
| `viewTimeline` | `value_timeline` | `ValueProfile::forTimeline` |

New files: none expected. Extended: `app/Model/Value.php` (the audit id
sets, if the existing accessors do not cover them),
`app/Model/ValueProfile.php` (one public method plus the audit reader),
`app/Lib/Tools/ValueStatsTool.php` (the month/action rollup),
`app/Lib/Tools/AuditActionMeta.php` (read, not changed). Templates
touched: `value_timeline.ctp` only, and it is Value-Profile-owned.

The endpoint stays one endpoint. `06-timeline.md` §4 argues that from the
brush — one control driving two regions that must exist when it fires —
and nothing about going live changes it. The controller comment already
records the reasoning; the swap is `profileFor()` out, `forTimeline()` in,
per §14.2.

---

## 5. Decision — the audit ACL model

**Closes the choice `value-profile-page.md` §8.2 left open.**

### 5.1 The two models MISP ships

Both still exist as §8.2 described them, verified today.
`AuditLogsController::__applyAuditAcl` (`:356`) restricts a non-admin to
`AuditLog.user_id = $user['id']` and an org admin to
`AuditLog.org_id = $user['org_id']`. `__createEventIndexConditions`
(`:488`) returns every row for an event when the viewer is a site admin or
in the event's creating org, and otherwise runs a full `fetchEvent()` to
enumerate the attribute, object, proposal and object-reference ids the
viewer may see, then restricts to those.

### 5.2 What each costs here, measured

| Model | On `8.8.8.8` (26 occ, 20 events) | On `193.161.193.99` (337 occ, 204 events) |
|---|---|---|
| Per-user (`__applyAuditAcl`) | **0 rows** for any reader outside ADMIN — every one of the 54 rows is ADMIN's | same shape |
| Per-event, unscoped by model | 20 `fetchEvent()` calls, then a read over the events' whole audit history | 204 `fetchEvent()` calls, over **816,041 rows** |
| Id-scoped (below) | 54 attribute rows + 299 event rows, no `fetchEvent` | 1,007 + 1,045, no `fetchEvent` |

The first row is the one worth pausing on. §8.2 predicted that only the
per-event model shows a plain analyst anything; on this instance that is
not a prediction but an arithmetic fact — **all 54 of `8.8.8.8`'s audit
rows carry `org_id` ADMIN**, so under the per-user model every other
reader on the instance gets an empty tab and no way to tell that from a
quiet value. And §8.2's own warning applies to the verification as much as
the design: read as a site admin, all three rows above look identical.

The second row is why the per-event model cannot simply be adopted. §8.2
costed it in `fetchEvent()` calls; the row count is the half it did not
measure. A value in 204 events is not 204 expensive queries followed by a
cheap read — it is 204 expensive queries followed by a read over 816,041
rows, of which the ones about this value are 1,007.

### 5.3 The decision

**The value-scoped history is the audit rows about the objects the viewer
may already see, scoped by id, in three model scopes:**

```
model = 'Attribute' AND model_id IN Value::occurrenceIdsFor($user, $value)
model = 'Object'    AND model_id IN Value::occurrenceObjectIdsFor($user, …)
model = 'Event'     AND model_id IN (the ACL'd event ids)
```

**The third line reads `model_id` and not `event_id`, and that is a
correction to this section rather than a detail of it.** `event_id` is
one of `audit_logs`' two indexes and `model` is not indexed at all, so
it looks like the column to name. MISP writes `event_id` on *every*
model's rows — an `Attribute` row carries the event it belongs to — so
`event_id IN (…)` matches the whole audit history of those events and
filters `model = 'Event'` from each row afterwards. On `443`'s 1,844
events that is the 816,041 rows §5.2 costed the per-event model at,
reached by a different route, and it measured **14,330 ms** against
**129 ms** for `model_id` over the same ids. The substitution is sound
because the two columns hold the same number on every `model = 'Event'`
row — verified over all 28,048 of them, none null — and the two forms
return the identical 9,493 rows. §5.5 has the rest.

Every id set is produced by an accessor that has already applied
`buildConditions($user)`, so permissions are settled before the audit
table is touched — §14.4's tier-2 shape, and the reason this is a read of
rows rather than an aggregate does not change the argument: the rows are
*about* ids the viewer may see.

**Why this is safe rather than merely cheap.** For any given viewer and
any given event, this returns a **subset** of what
`__createEventIndexConditions` would already hand them on that event's own
audit index: that model grants every `model = 'Event'` row plus the rows
for the attributes, objects, proposals and references the viewer may see,
and this asks for the same thing narrowed to the occurrences of one value.
The page therefore discloses nothing MISP does not already disclose on a
page that ships. It costs no `fetchEvent()`, because the narrowing that
model pays `fetchEvent()` to compute is the narrowing `Value`'s accessors
have already done.

**Why not the per-user model.** It is not a scoping of the value's
history; it is a different subject — *my* actions, filtered to this value
— and rendering it under a heading that says what happened to this value
would be false for every reader who is not the person it happened to.

### 5.4 What it drops, and that is stated on the tab

Three things, and the third is the one a reader could misread:

- **Sibling rows.** An edit to another attribute in an event this value
  sits in is not this value's history and is not shown.
- **`ShadowAttribute` and `ObjectReference` rows**, which
  `__createEventIndexConditions` includes for an event. Proposals reach
  this tab as their own lane from `shadow_attributes` (§10), which is
  dated and needs no audit row; object references do not reach it at all.
- **An occurrence that used to be a different value.** §8.2 records that
  `model_title` prefers the new value, so an occurrence edited *into* this
  value carries audit rows describing what it was before. They are this
  occurrence's rows and they are shown; the chronology row names the
  occurrence and the action, never the title, and §11's row contract says
  so. Scoping by title instead would be both wrong and a scan —
  `model_title` is an unindexed `text` column.

### 5.5 What the reader cost, and the two things that made it

T2's verification, on a quiet box (load 1.9 on 12 cores — the earlier
figures in this section's first draft were taken at load 31 and are not
quoted). `25-audit-reader-probe.php` is the harness: it runs the reader
rather than re-writing its SQL by hand, reads as a chosen user so
§8.2's *read as a site admin and all three models look identical* trap
is something the probe can fail, and checks D2's invariant on every
value.

**The first shape was 40× too slow, and the cap was not why.**

| | aggregate | capped read |
|---|---|---|
| `443`, three scopes OR-ed into one `WHERE` | 54,501 ms | 13,025 ms |
| `443`, one statement per scope, `model_id` throughout | **497 ms** | **459 ms** |

Every count is identical across the two — 172,426 on `443`, 369 on
`8.8.8.8`, 2,052 on `193.161.193.99` — so this is a query-plan fix and
not a change to what the tab says. Two independent causes, and both
were things the first draft did on purpose:

1. **The `OR` across scopes.** `EXPLAIN` on the OR-ed form is
   `type = ALL`, `key = NULL`, `rows = 8,558,097` — a full scan of the
   table. An `OR` whose branches name two different columns makes the
   optimizer abandon both indexes. A single scope with a flat `IN` list
   plans as a materialised subquery joined `ref` on `model_id`, one row
   per lookup. So the three scopes are three statements merged in PHP.
2. **`event_id` on the event scope**, per §5.3's correction above.

**Chunking was the first cause, not a mitigation for it.** The first
draft chunked the id lists at 1,000 to keep `IN` lists short, which is
what built the 52-branch `OR` that scanned. A flat 48,255-id `IN` is
*faster* than the chunked form by two orders of magnitude, so
`AUDIT_ID_CHUNK` now bounds statement size only, a chunk is its own
statement, and it sits at 25,000 — where `443` takes two chunks, so the
merge path is the path the phase's heaviest value runs rather than a
branch nothing reaches.

**The merge is exact, not approximate.** Each scope is read newest-first
under the same cap, so the global newest *n* is a subset of the union of
the per-scope newest *n*. The merged set is at most three chunks' worth
of caps — hundreds of rows — and never the 172,426 the aggregate
counted, which is the §7.9 trap this reader has to stay out of.

**§5.2's first row, demonstrated.** That row was arithmetic: *all 54 of
`8.8.8.8`'s audit rows carry `org_id` ADMIN, so under the per-user model
every other reader gets an empty tab.* Read as `orgadmin@circl.lu` —
CIRCL, no `perm_site_admin` — the decided reader gives:

| Value | site admin | CIRCL org admin |
|---|---|---|
| `8.8.8.8` | 26 occ / 20 events / 369 rows | 14 occ / 12 events / **226 rows** |
| `443` | 48,255 occ / 1,844 events / 172,426 rows | 829 occ / 45 events / **1,926 rows** |
| `193.161.193.99` | 337 occ / 204 events / 2,052 rows | 3 occ / 3 events / **42 rows** |
| `2.2.2.2` | 13 occ / 13 events / 164 rows | 10 occ / 10 events / **144 rows** |
| `45.155.205.233` | 2 occ / 1 event / 13 rows | 0 occ / **0 rows** |

Every one of those *newest* rows is authored by `admin@admin.test`, so
the per-user model would have handed this reader **nothing** on all five
— which is the difference D1 was taken for, now measured rather than
predicted. The ACL bites in the right place and by the right amount: the
CIRCL reader's row count tracks their occurrence and event counts, and
the reader issues no statement at all where the scope is empty, so
`45.155.205.233` is a zero rather than a whole-table read.

**And the pathological value is a site-admin pathology.** `443` is
48,255 occurrences to the admin and 829 to CIRCL. The scope build —
`Value::occurrenceIdsFor`, which is `Value`'s cost and not this
reader's — is 1,067 ms of the admin's `443` and 152 ms of CIRCL's, and
is now the largest single number on the endpoint.

---

---

## 6. Decision — one array cannot survive 162,539 rows

`06-timeline.md` §7 is the tab's central constraint: the spine's bars, the
lanes' counts and the chronology's list are *"three aggregates over one
array"*, derived in the template, so the panel cannot state two numbers
that disagree. §3.3 is the case that breaks it — a value whose entry set
is 162,539 rows before a single sighting is added.

**Decided: the invariant is kept and moves into the facade. Counts come
from a grouped aggregate; rows come from a capped read; both run over the
same scoped id set and the same window, so they are two grains of one
query rather than two queries.**

- **Counts** — per month, per source, and the lane totals — are one
  grouped aggregate per source family. Tier 2 under §14.4, with the
  reason §14.4 asks for: the answer is a group, materialising 162,539 rows
  to count them in PHP is the wrong shape, and the id set was ACL'd before
  the aggregate ran.
- **Rows** — the chronology — are read newest-first under a cap. The cap
  is `ValueProfile::OCCURRENCE_CAP`'s sibling and starts at the same 300,
  for the reason phase 22 recorded: the page control renders one button
  per page inline and collapses past about twenty.
- **The panel says both numbers.** *Showing 300 of 162,539 entries in this
  window* is not a disagreement; it is the same query at two grains, and
  it is the shape §15 of `06-timeline.md` already chose when it made
  entries older than the spine *"counted out loud"*.

**Rejected: cap the entry set and let the template keep deriving.** That
is the §7.9 trap phase 22 and phase 23 both recorded — *"tallying the
fetched page works at ten rows and stops being honest the moment the table
paginates."* A spine drawn from 300 of 162,539 rows is a chart of the last
fortnight labelled as a year.

**A consequence for the template.** `value_timeline.ctp` today computes
every count from `$entries` at render time. Under this decision the
element takes the counts alongside the rows and stops deriving the ones
that are capped. That is a change to a Value-Profile-owned element, so
§14.7's fork test does not fire; but it is the one place where the live
shape is not *"a data source swapped under an unchanged template"*, and
§14.1's claim that this happens nowhere else on the page now has one
exception, recorded here.

---

## 7. Decision — the seen-span lane keeps its rule and takes a cap

`06-timeline.md` §8.2 refuses to merge spans, on the grounds that merging
needs an aggregation rule nobody has agreed on — the same problem §7.9
found for the decay curve, which phase 23 solved by *deciding* rather than
inventing. The fixture shows one bar because the demo value has one span.

Live, `143.14.244.37` has **24 real spans and 8 instants across 32
occurrences**, all in one event. `mughalmotifs.com` has 52 occurrences,
every one an instant.

**Decided: no merging, a cap of 25 bars ordered by span start, and the
remainder stated in the lane's sub-label.** A cap is not a permission, so
§14.6 permits saying so — the distinction phase 22 already drew for the
siblings section, where *"the cap notice stays, since a cap is not a
permission."*

**Instants are drawn as instants.** 68,083 of the instance's dated
occurrences have `first_seen == last_seen`, and the fixture's own note
calls that *"one instant, not a span"*. A lane that draws them as
zero-width bars says nothing; they draw as marks, in the lane, with the
span rows.

**One implementation note that is not a decision.** `first_seen` and
`last_seen` are `bigint(20)` microsecond epochs in the database. The
fixture carries ISO-8601 because that is what the fetcher hands back, and
the facade must convert from whatever `fetchAttributesSimple` returns
rather than from the column — the seam is the fetcher, not the schema.

---

## 8. Decision — analyst data on the value's events is in, and labelled

`05-analyst.md` §11 records that a value is not a valid analyst-data
target: notes and opinions hang off `object_uuid` + `object_type`, so this
tab inherits phase 13's controller-assembled union over the value's
occurrences *and their events*.

The instance says why that matters. It holds 75 notes and 43 opinions;
**9 notes and 3 opinions are on attributes** and 44 notes and 28 opinions
are on events. None of the attribute-level ones is on a candidate value's
occurrence, while `8.8.8.8`'s events carry 2 notes, `1.1.1.1`'s carry 4
and `2.2.2.2`'s 1. Take only the occurrence-level ones and this lane is
empty on every value worth verifying; take both and most of what it draws
is about an event rather than about the value.

**Decided: both, and every row names its target.** *Note on event 3753* is
a different claim from *note on attribute 481920*, the tab's whole charter
is provenance, and a union that flattens the two promotes an event's
narrative into a statement about the value. The lane counts them together
because they are both dated analyst statements a reader of this value
should know exist; the rows keep them apart.

The Analyst tab inherits this union rather than inventing a second one,
which is the same bargain §2 takes with the audit reader.

**One thing to guard.** `AnalystData::rearrangeOrganisation` nests its
result and re-queries when the association is absent — phase 24 recorded
it, and the failure mode is every row silently reporting *Unknown
organisation*. Contain both `Org` and `Orgc`.

**And one anomaly, recorded not fixed.** One `notes` row carries
`object_type = 'Event1556'` — a type that is not a type, presumably an id
concatenated onto the model name by whatever wrote it. It is one row of
75; the union must not assume `object_type` is one of the known set, and
this belongs on §14.7's report-do-not-fix list rather than in this phase.

---

## 9. The spine's bins come from the range

> **Built.** §16.2 has what shipped, the grain table it shipped with, and
> why it reuses `unitForSpan()` and `series()` rather than `plan()` —
> which is D5's stated reopening condition met rather than ignored.

`06-timeline.md` §12 lists this as live-data work the fixture pins:
*"twelve monthly bins because the range is a year. A value first seen last
week needs daily bins."* The template hard-codes twelve months ending at
the window's end.

**Nothing new is built for it.** `ValueProfileBuckets::plan()` — phase
21's, three callers, and carrying phase 23's payload fix that stopped it
shipping 1,095 day labels — already plans a grain from a range, in
`plan()` beside `series()` and `locate()`. `ValueDecayTool::SPAN_CAP_DAYS`
(1,095) is the precedent for a ceiling, and the precedent is the *shape*
rather than the number: that cap exists because a curve costs formula
evaluations per day, and a spine costs nothing per empty month. What
bounds this panel is the read behind it (§6), not the width of its axis.

The one thing to decide per panel rather than per tool is the unit rule,
which §15.2 of `22-occurrences.md` already records as the caller's:
`ValueProfileBuckets` buckets an instant well and an interval not at all,
so the spine bins entries and the seen lane, whose input is a set of
intervals, keeps drawing itself.

---

## 10. Two lanes the coverage survey owes

`value-profile-coverage.md` §5 gives this phase **proposals yes, feeds no
— settled, event reports yes**, and the proposals verdict comes with a
finding: `shadow_attributes.timestamp` is dated, so §8.2's scoreboard
undercounted the datable sources by one.

**Proposals.** The instance holds 23, of which 6 are standalone
(`old_id = 0`), 16 are soft-deleted, and — usefully — **none is
epoch-zero**, so the `DEFAULT 0` row the survey warned about is a
possibility here rather than an observation. Two are against events
holding `8.8.8.8`, one is on `2.2.2.2`. The lane is thin but real, and
the two caveats the survey names are both carried: `timestamp` is
last-modified, so an edited proposal moves on the axis and the row says
so; and an epoch-zero row is excluded rather than plotted in 1970.

**Event reports.** 174 on the instance, dated 2020-12-30 to 2026-07-09,
none epoch-zero, 2 soft-deleted. `8.8.8.8`'s events carry 8,
`google.com`'s 7, `1.1.1.1`'s 5. Same last-modified semantics, same
treatment.

**The report lane must not use `EventReport::attachReportCountsToEvents`.**
Its non-site-admin branch ANDs `distribution IN (1,2,3,5)` with
`distribution = 4` where an `'OR' =>` was intended
(`EventReport.php:392-407`), so it returns 0 for every event the viewer's
org does not own. That is on the standing report-do-not-fix list — it
ships, and it is visible on the event index and the event view — and this
phase reads reports through an ACL'd fetch of its own rather than
inheriting the defect into a fifth surface.

---

## 11. The conversion plan, lane by lane

Nine lanes where the fixture-era tab had seven. `Q` is left blank; §14.9
row 2 is filled by measurement, not by estimate, and a blank here is a row
§14 will not let the board claim.

| Lane | Live source | Fetcher / accessor | Tier | The risk |
|---|---|---|---|---|
| Sightings | `sightings` | phase 23's `sightingContext`, reused whole | 1 | none new — but the count is the viewer's, per §14.6, and this tab must not restate it as the instance's |
| Publications | `events.first_publication`, `publish_timestamp` | one `Event::fetchSimpleEvents` for all N | 1 | epoch-0 is common — 4,235 of 4,287 events carry a publish timestamp and only 2,858 a first publication; and event 4116 carries a first publication with **no** current one. Two points per event is a ceiling, not a promise |
| Notes / Opinions | `notes`, `opinions` | `AnalystData::fetchChildNotesAndOpinions` over the occurrence ∪ event union | 1 | §8's labelling; `rearrangeOrganisation`'s nesting; one `object_type` that is not a type |
| Edits | `audit_logs` | §5's three id-scoped reads | 1 rows, 2 counts | §3.3's 162,539; and the not-recorded branch, which this instance cannot show |
| Seen spans | `attributes.first_seen` / `last_seen` | already on the occurrence rows | 1 | §7's cap; microsecond epochs; instants outnumbering spans 2:3 |
| Proposals | `shadow_attributes.timestamp` | ACL'd fetch, `old_id` kept for the row's wording | 1 | thin data — 23 rows instance-wide |
| Event reports | `event_reports.timestamp` | ACL'd fetch, **not** `attachReportCountsToEvents` | 1 | §10's shipped defect |
| Tags — undated | `attribute_tags`, `event_tags` | `Value::ownTagsFor` | 1 | `193.161.193.99` carries **670** attribute tags and `8.8.8.8`'s events 69 event-tag rows over 48 distinct tags. The strip's chip list needs a bound the fixture never needed |
| Feeds — undated | Redis feed/server caches | phase 24's `forExternal`, unchanged | the fourth tier §14.12 describes and §14.4 lacks | none new; the `as_of` is `misp:feed_cache_timestamp:<id>` and dates the fetch |

Galaxy clusters stay where the fixture put them — a chip row on the
off-axis strip, not a lane — and inherit the tag bound. No candidate value
carries one; the instance's cluster-tagged values are single-occurrence
hosts with four clusters apiece, and one of them is the verification case.

### 11.1 A latent defect the live facade must not inherit

> **Closed, both halves.** The facade emits a stable `key` beside the
> translated `kind`, and the template matches on the key. The chip list
> also took the bound this section asks for: `TIMELINE_CHIP_CAP`, with
> the whole count stated beside what is drawn — `443` resolves 3,858
> distinct tags and shows twelve of them.

`value_timeline.ctp` matches undated rows to lanes by their **translated
label** — `$undatedBy[__('Tags')]`, `$undatedBy[__('Feed appearances')]`.
The fixture supplies `kind` through the same `__()` call, so the two agree
in English and in any locale where both strings are translated
identically, and silently stop agreeing otherwise: the lane renders its
*absent* text while the strip below it lists the chips. The live facade
emits a stable key beside `kind`, and the template matches on the key.

---

## 12. §14.6 missed this tab, and the band it renders

> **Applied.** The band and the `acl_note` key are gone, and §14.6's
> table row — which this phase added when it opened — now reads
> *applied, phase 25*. §16.3 carries the part this section did not
> anticipate: the no-timeline state's own wording had the same defect in
> reverse, asserting that MISP had never held the value, which is false
> for a reader who merely cannot see its events.

`value_timeline.ctp:1235` renders a `.vp-acl-note` band from
`timeline.acl_note`, and the fixture's text for the benign value is:

> *Four of this value's nine occurrences are on events you cannot see, and
> nothing they contribute is in this chronology.*

That is exactly what §14.6 forbids — a note whose presence is the
disclosure, on a page whose URL takes any value the reader types. **It is
not in §14.6's required-changes table.** That table lists the Occurrences
tab's two, the Overview's footer, the History footer graft, phase 19's
suppressed state, the siblings notes and the tab counts; the Timeline's
band was missed, and phase 25 is the first phase in a position to notice.

**Decided: the band and the key go**, and §14.6's table gains the row so
the next reader of that table sees a complete list rather than a list that
happened to be complete for the three tabs that had converted. The panel
where everything is hidden then renders as the panel where nothing is
dated, which is the loss §14.6 already priced and took deliberately.

---

## 13. The three concepts, assessed

§14.9 row 9, with `value-profile-coverage.md` §5's starting verdicts.

| Concept | Verdict | Why |
|---|---|---|
| Proposals | **yes — built** | §10. `shadow_attributes.timestamp` is dated, which §8.2's scoreboard missed; the lane is thin on this instance and real |
| Feeds / servers | **no — already settled** | `06-timeline.md` §12 proves it from `Feed.php:1573`: one timestamp per feed, rewritten on every refresh. The hatched lane renders the exclusion honestly, and there is nothing to redo |
| Event reports | **yes — built** | §10. `event_reports.timestamp`, 174 on the instance, 8 on `8.8.8.8`'s events |

---

## 14. Verification — the plan, and the values

§14.9's nine rows are filled when the phase closes. The plan:

1. `php -l` over every changed PHP file; `parallel-lint` if `app/Vendor/`
   is present in the checkout the phase runs in.
2. The grep §14.3 makes the whole of its rule: no `value1`/`value2`
   outside `Value.php`. And phase 23's addition — no live element names
   `ValueProfileFixture`.
3. **Both audit branches.** With `log_new_audit` on, against a value whose
   occurrences the log covers; and the not-recorded branch, which this
   instance can only show on a value whose occurrences all predate
   2024-11-11 or by reading with the setting off.
4. **The counts/rows split cannot disagree** (§6): on `443`, the lane
   totals and the spine must sum to the aggregate's number while the
   chronology shows its cap, and the panel must say both.
5. **A non-site-admin reader** — §5.2's whole point, and §8.2's stated
   trap. As a site admin all three ACL models look identical.
6. Both themes, per §14.9 row 8.
7. The no-JavaScript render, which `06-timeline.md` §13.9 already
   requires and going live does not excuse.

**The values.** No one value exercises this tab (§3.2):

| Value | What it proves |
|---|---|
| `8.8.8.8` | the populated case — 53 sightings, 54 audit rows, 20 events, 2 event notes, 8 event reports, and **no spans** |
| `143.14.244.37` | the span lane — 32 dated occurrences, 24 real spans, 8 instants, no sightings |
| `443` | §3.3 and §6 — 48,255 occurrences, 162,539 audit rows |
| `193.161.193.99` | the wide-event case — 337 occurrences over 204 events, and 670 attribute tags for the strip's bound |
| `2.2.2.2` | the viewer-scoping case phases 22 and 23 both used, plus the instance's one proposal on a candidate value |
| `45.155.205.233` | the sparse case — 2 occurrences, 8 audit rows, 3 sightings |

---

## 15. Deferred, with the cost named

**The passive-dns lane** (`06-timeline.md` §16, `24-relationships.md`
§26.7). 829 passive-dns objects on the instance, 665 carrying both
`time_first` and `time_last`. The query is the one `value_relation_dated`
already runs and caches, so the data is close to free; what is new is a
lane, its hatching rule when the value sits in no relational object, and
the chronology rows. It stays deferred because this phase is already
taking three decisions and adding two lanes, and because the eighth
source's absence is honest — the Relationships tab shows those dates
today. The cost of deferring: a value whose whole story is in passive-dns
dates reads on this tab as a value with almost nothing dated.

**A real publication history.** Two points per event is a ceiling MISP
sets, not a choice this phase makes; `ACTION_PUBLISH` audit rows would
give the rest, and §5's model already reads `model = 'Event'` rows.
Whether the publications lane should draw them beside the two columns —
and how it would say that one lane holds two kinds of evidence — is a
question this phase names and does not answer.

**~~A window the chronology can go back for~~** — no longer deferred.
Added here by §17.3 and built in §18, one review round later: the reader
who found the contradiction did not want a better sentence about it, and
was right.

**`T2` standalone and `T1`**, unchanged from `06-timeline.md` §12.

---

## 16. The build log

What the build found, in the order it found it. Every number here was
taken on a quiet box — load 0.76 on 12 cores — because the first set was
taken at load 31 and was four times too slow across the board;
`measure only on a quiet machine` is not advice this phase can skip when
half its decisions are cost decisions.

### 16.1 The counts are per day, and no lane tallies its own rows

**D9.** §6 decided that counts come from an aggregate and rows from a
capped read. It did not say at what grain, and the first build grouped by
month — which cannot answer the question the lane grid asks. The default
window is 30 days (D10) and therefore straddles two months on most
values, so a monthly map answers *how many entries this year* and needs a
second, date-bounded aggregate for *how many in this window*. A day
answers all three of the panel's questions from one query: the spine bins
days into whatever width its range wants, the lane grid sums the days
inside the window, and the header sums all of them. `443`'s eleven months
of audit history come back as 49 rows, because its 172,426 rows sit on 49
days — a bulk-import instance is bursty, and that is the shape that makes
the day grain cheap rather than expensive.

**And the rule became absolute rather than case-by-case.** The first
build let the lanes that *could* materialise all their rows tally them,
and gave a grouped map only to the edit lane that could not. Two things
were wrong with that:

1. **A lane that happens to fit today stops fitting tomorrow.** The
   publication lane is up to two rows per event, and `443` sits in 1,844
   events, so it offered 1,847 entries; with the audit log off the edit
   lane is one row per occurrence, which is 48,255. Both were building
   arrays to have them thrown away by the merge.
2. **A lane is not a source.** The sightings lane owns three —
   `sighting`, `false_positive`, `expiration` — and the analyst lane two.
   A per-lane tally attributed to the lane's first source would have
   reported `8.8.8.8`'s four false positives and two expirations as six
   sightings: right total, wrong word in the breakdown, same colour on
   the spine, and nothing on the page to notice it by. Every lane's map
   is therefore keyed by day **and** by source.

So: every lane returns a per-day per-source map over all of its rows and
at most `TIMELINE_ROW_CAP` of the rows themselves, the merge keeps the
newest cap-many of the union, and **nothing the panel prints is tallied
from the rows it printed.** The one exception is the chronology's own
precision tally, which is a statement about the list and sums to it,
which is what makes it worth printing.

**The cap moved to the merged array.** The first build capped only the
audit read, so `443` shipped 2,173 entries — 1,847 of them publications.
The fragment's weight is the sum of the lanes, so the bound has to be on
the sum.

**Counted before the cap, shipped after it**, and stated: *Showing the
newest 300 of 174,299 entries.*

### 16.2 The spine's grain, and where D5 did not fit

**D5 said the grain is planned from the range through
`ValueProfileBuckets::plan()`. It is planned from the range through
`unitForSpan()` and `series()` instead, and D5's own reopening condition
is what allows it** — *it reopens only if `plan()` stops fitting.*
`plan()` ships every grain the caller's rule permits and lets the browser
re-aggregate a single series. This spine is **stacked per source**, so a
grain is a matrix rather than a row, and the counts it stacks are
server-side already. Using it would have meant either shipping a matrix
per grain or rewriting the spine as a browser-side aggregation — a larger
change than the row it appears under. The reuse is from the same class
and the same range, which is what D5 was actually for.

The rule is the panel's own, because a bar here does not mean what a bar
on the Sightings navigator means:

| Range | Grain | Why |
|---|---|---|
| ≤ 45 days | day | The whole range is what the reader is asking about |
| ≤ 400 days | week | The fixture's twelve monthly bins, at the resolution a year deserves |
| wider | month | `443` spans 2020 to 2026 and gets 80 monthly bars |

Measured on the verification values: `8.8.8.8` draws 23 monthly bins,
`443` 80 monthly, `143.14.244.37` 8 weekly over its 53-day range, and
`45.155.205.233` 39 weekly. **The grain is named in the panel's own
subtitle** — *111 dated entries by week, stacked by source* — because two
values on this page can now carry two different bar widths, and a reader
who is not told will read one as the other.

`$before` — the count of entries older than the spine's first bin — is
now structurally zero, since the spine begins where the value does. The
branch is kept rather than deleted, because a future ceiling on the axis
would need it back and deleting it would take its wording too.

### 16.3 Three bugs the build made and one it inherited

**`$byDay`, twice.** The counts map was named `$byDay` in the template's
header and the chronology's day-grouping already used that name 300 lines
down — so the grouping silently overwrote the map before the payload was
built. The spine was unaffected, because it is binned above the
collision; the payload shipped a map of the *capped rows*, which is what
the brush counts from, so a brushed window would have reported `443`'s
whole history as 8 days. Caught by comparing the payload's key count
against the facade's: 8 against 49, and 67 against 95 on `8.8.8.8`. Both
ends are now named for what they hold.

**A lane's map attributed to one source**, above.

**An empty timeline where the fixture had none.** `ValueProfileFixture`
returns `timeline => null` for a value MISP has never held, with the note
that an empty timeline *"would be inventing a period of silence that
never happened."* The live facade returned an array unconditionally, so a
value with no visible occurrence drew an axis, empty bins and seven lanes.
It now returns null, and the panel's no-timeline state carries it — 736
bytes, and 2 queries for a reader who can see nothing.

**And the fixture's wording for that state was wrong**, which only
mattered once it had a second cause. It read *"Nothing has happened to
this value, because MISP has never held it"* — true for an unknown value
and false for a value held only in events this reader cannot open, which
is now the commoner case. It is also §14.6's problem in reverse: a
sentence asserting non-existence makes the panel answer *does this exist
on the instance*. One sentence now, true both ways, identical for every
reader: *There is no occurrence of this value here to place on an axis.*

**Inherited, and reported not fixed:** `AnalystData::afterFind` calls
`setUser()`, which reads only `Configure::read('CurrentUserId')`, and
then hands the result to `rearrangeSharingGroup(array $user)` — typed
`array`, called unconditionally. So **any** `find` on a `Note` or an
`Opinion` with `CurrentUserId` unset is a `TypeError` rather than a
degraded row, which makes every CLI and worker path that touches analyst
data fatal. Found by the facade probe on its first run. The page sets the
key, so this phase is unaffected; it belongs on §14.7's
report-do-not-fix list.

### 16.4 What it costs

Endpoint total, `forTimeline` end to end, on a quiet box:

| Value | Site admin | Org admin | Queries |
|---|---|---|---|
| `8.8.8.8` | 47 ms | 53 ms | 33 / 30 |
| `143.14.244.37` | 11 ms | 11 ms | 16 |
| `443` | **2,263 ms** | 391 ms | 27 / 20 |
| `193.161.193.99` | 54 ms | 13 ms | 16 |
| `2.2.2.2` | 26 ms | 22 ms | 27 / 24 |
| `45.155.205.233` | 12 ms | 2 ms | 20 / 2 |

**The query count does not scale with the value.** It runs 16 to 33 and
tracks *which lanes have data* — a value with sightings pays for
`listSightings`, one with analyst data pays for two `fetchForUuids` — not
how many occurrences or events the value has. `193.161.193.99` sits in
204 events and costs 16 queries, which is §14.4's batching rule holding:
one `fetchSimpleEvents` for all of them, never one call per event.

**`443` is the one number that needs watching, and it is a site-admin
pathology.** 2,263 ms to a site admin against 391 ms to an org admin, on
the same value, because the site admin's occurrence set is 48,255 rows
and the org admin's is 829. Two components account for most of it, each
measured on its own: `Value::occurrenceIdsFor` at 1,067 ms — `Value`'s
cost, not this endpoint's, and the largest single number on it — and the
audit reader's aggregate plus capped read at 956 ms (§5.5). The rest is
`ownTagsFor`, which resolves 3,858 distinct tags on that value.

### 16.5 Verification, run

§14's plan, executed. Eighteen checks: six values × three
configurations.

| # | §14's requirement | Result |
|---|---|---|
| 1 | `php -l` over every changed file | clean; `parallel-lint` not run — no `app/Vendor/` in this checkout. `node --check` on the changed JS, clean |
| 2 | No `value1`/`value2` outside `Value.php`; no live element naming `ValueProfileFixture` | clean. The four `value1` hits in `ValueProfile.php` are phase 24's prose, not queries |
| 3 | **Both audit branches** | on: 6/6 values hold every invariant. Off, forced per-run so the instance's 9.5 M rows stay put: 6/6, and the edit lane collapses to one point per occurrence — 26 on `8.8.8.8`, 48,255 on `443`, matching each occurrence count exactly |
| 4 | **The counts/rows split cannot disagree** on `443` | holds. The panel states three numbers about three different things and the lane grid sums to the window count on every value: `443` reads *11 in window*, *showing 300 of 174,299*, and 0+1+0+10+0 = 11 across the lanes. The 11 was checked independently in SQL — 8 attribute + 0 object + 2 event audit rows, plus 1 publication |
| 5 | **A non-site-admin reader** | 6/6. `8.8.8.8` goes 447 entries → 249 and 53 sightings → 8; `443` goes 174,299 → 1,999 and 48,255 occurrences → 829; `45.155.205.233` goes to the null timeline. Under the model D1 rejected this reader would have got **nothing** on all of them (§5.5) |
| 6 | Both themes | **not run.** No colour, token or literal was added to the element; every new string rides an existing class. A visual pass is still owed |
| 7 | **The no-JavaScript render** | holds, and it is what §16.4's fragments were fetched as. Every count in the served HTML is the server's, correct for the default window, with no script having run |

The values that carry each case are §14's six, and the split is the one
§3.2 predicted: `143.14.244.37` is the only one that exercises the seen
lane — 32 of 32 occurrences dated, 25 drawn, and the sub-label states all
three numbers — and `8.8.8.8`, which has the sightings, has no span at
all.

**Two occurrence-level analyst values the probe turned up**, which §8
predicted did not exist among the candidates and was right about:
`https://circl.lu` and `https://google.com` each carry 3 notes on their
own occurrences. §14.9's row 7 should gain them when the analyst lane is
next touched — every candidate value's analyst data is on events, so the
occurrence half of D4's union is currently verified only by construction.

## 17. Two objections from the first reader, and what they found

The tab went in front of a reader on `8.8.8.8`. Two things came back, one
asked as a request and one as a question, and the question turned out to
be the more serious of the two.

### 17.1 The axis names a month and never a year

`8.8.8.8` spans 2024-11-11 to 2026-09-03 — 662 days, so §16.2's rule
picks the month grain and draws 23 bars. Their labels came from
`ValueProfileBuckets::describe`, which formats a month bucket as `M`. So
the axis read `Nov Dec Jan Feb … Nov Dec Jan …`: two `Nov`s, two `Dec`s,
and no way for a reader to say which one they had brushed.

The label is not the place to fix it. `describe()` is read by three
charts — the Sightings navigator, this spine and the History months —
and a bucket's own name is the same string in all three; widening it to
`Nov 2024` would pay for the year on every bar of every chart to answer
a question only a multi-year one asks.

So the year is a **second line, on the bars that open a year**, written
in the spine's tick callback rather than in the bucket. The first bar
always opens one, so the axis names its own start; between then and the
next January it says nothing, which is the point. It works at every
grain the spine offers, because a week grain crosses a year as readily
as a month one: `45.155.205.233` is 39 weekly bars and reads
`2 Dec/2025 … 6 Jan/2026 …`.

**The mechanism is `autoSkip`, and it decided the design.** Chart.js
drops ticks that will not fit — 39 weekly bars in a 760 px panel come
down to 13 — and it does so *after* the label callback has run. A year
written on a bar that is then skipped is a year the axis loses, and
there is no second pass to move it: `Scale.afterAutoSkip` exists in
Chart.js 4.1.1 as an empty stub, not as a dispatch to
`options.afterAutoSkip`, so the hook that would let a caller relabel the
survivors is not wired. What *is* available is `afterBuildTicks`, which
dispatches, and `autoSkip`'s own rule that **every major tick survives**.
So the year boundaries are marked major, and the thinning happens around
them. At 760 px `45.155.205.233` keeps both of its year ticks and loses
26 of its 37 month ticks, which is the right trade in that order.

The newest bar is marked major too, and it carries no year. Past its
last major, `autoSkip` labels one average major spacing further and then
stops — which on `8.8.8.8` silently dropped `Aug` and `Sep 2026`, the
two bars a reader is most certain about and least willing to count back
to. One extra major, no extra label.

### 17.2 The ruler over the lanes was labelling a window that had moved

Found while fixing the axis, and worse than it. The five-tick ruler in
the lane header is server-rendered from `$window`, and **nothing redrew
it after a brush**. So a reader who brushed `8.8.8.8` back to
2024-11-01 had the marks re-placed against the new window under a ruler
still reading `5 Aug 2026 · 12 · 19 · 27 · 3 Sep`.

Its labels were also `j M` for the first tick and a bare `j` for the
other four, which is a ruler that only works if a window fits inside one
month. The default window is 30 days and often does not — `5 Aug · 12 ·
19 · 27 · 3` puts the last tick in the first tick's month — and a
brushed window is whatever the reader dragged, which on this value is up
to two years.

Both halves are fixed at once: each tick names its month where the month
changed and its year where the year did, and the whole ruler is
recomputed on every window change. `2.2.2.2` brushed over its full range
reads `1 Oct 2024 · 23 Mar 2025 · 13 Sep · 6 Mar 2026 · 26 Aug`.

The rule is now written twice, in `$rulerLabel` and in `tlRuler`, and
that is the same duplication the lane marks already carry — rendered by
the template for the window the fragment arrives with and by the script
for every window after it. The month *names* are not duplicated: they
travel in the payload, because `toLocaleString` follows the browser's
locale and `format('M')` follows PHP's, and a ruler that renamed
November on first brush would be a worse bug than the one being fixed.

### 17.3 The chronology denied entries the panel had just counted

The question was: *I brushed the first month that shows activity and
nothing is displayed in the sources and the chronology. Why?*

Because the panel was telling the reader two contradictory things and
one of them was a sentence it had no business saying. Brushing
`8.8.8.8`'s first active bar gives a window of 14 entries — the window
label says `14 entries`, the lane grid says `2 Sighting` and `12 Edit`,
and every one of those numbers is right. The chronology under them said
**Nothing dated falls in this window.**

That is §16.1's split working exactly as designed and then lying about
itself. The counts are aggregates over every dated thing the viewer may
see; the rows are the newest `TIMELINE_ROW_CAP` of them. On `8.8.8.8`
that is 300 of 447, and the newest 300 begin at 2025-11-16 — so the
first eleven bars of a 23-bar axis are chart-only by construction. The
lanes admit this already: §16.1 states that the marks are a sample of
the count and a lane may show fewer marks than it counts. The chronology
did not, and *nothing dated falls in this window* is not a softer way of
saying *these are past the cap* — it is the opposite of the number
beside it, so the only reading left to the reader is that the panel is
broken. Which is how it was read.

So the chronology gets a second empty state, for the window that holds
entries none of whose rows the fragment carries:

> **14** dated entries fall in this window, and none of them are among
> the rows this list carries. It holds the newest 300 of 447 — brush a
> more recent period to read them.

Two caps can produce it and the sentence names whichever applies.
`TIMELINE_ROW_CAP` over the merged chronology is the common one and its
numbers are worth printing. `TIMELINE_SPAN_CAP` is the other: the seen
lane draws 25 spans of however many it counted, and it takes the
*oldest* 25, so a value whose chronology fits whole can still have a day
in the aggregate with no row against it. That branch says *a lane caps
the rows it draws* and prints no numbers, because the ones it would
print are the wrong pair.

The plain sentence stays for the window that really is empty, and for
any window a source filter emptied — that is the reader's own doing and
they have the filter note beside it.

**What this does not fix, and what would.** The rows for an old window
are not on the client at all, so no amount of wording puts them on
screen. The fix would be a window parameter on `viewTimeline`, the way
the History tab takes its period in the path (§11) — the brush would
re-fetch when it reaches past what the fragment carries. That is a
model-and-controller change with a cache key on it, and it is deferred
here rather than smuggled into a wording fix. §15 gains the row.

### 17.4 One string §16 left false

The spine's canvas carried `aria-label="Dated entries per month over the
last twelve months, stacked by source"`. The twelve months were the
fixture's window; §16.2 replaced it with the value's whole range at one
of three grains, and the label was not touched. It was therefore wrong
for every value but one, and wrong in the one way an `aria-label` cannot
be recovered from — the reader it serves has no chart to check it
against. It now names the grain and the two dates: *Dated entries by
month from 2024-11-11 to 2026-09-03, stacked by source*.

### 17.5 Verified

Against the live instance, logged in, in a real browser — not the
harness, because three of these four are `autoSkip`, layout and brush
behaviour and a harness would confirm all of them wrongly.

| # | Check | Result |
|---|---|---|
| 1 | `php -l` + `parallel-lint` on the element, `node --check` on the JS | clean |
| 2 | Year ticks, month grain | `8.8.8.8`, 23 bars: `Nov/2024`, `Jan/2025`, `Jan/2026`; all 23 labelled at 1600 px. `2.2.2.2`, 23 bars: `Oct/2024`, `Jan/2025`, `Jan/2026` |
| 3 | Year ticks, week grain | `45.155.205.233`, 39 bars: `2 Dec/2025`, `6 Jan/2026` |
| 4 | **`autoSkip`** | `45.155.205.233` at 760 px: 39 bars → 13 ticks, **both** year ticks and the newest bar survive. `2.2.2.2` at 760 px: all 23 kept, rotated 50°, both lines rotate together |
| 5 | The ruler follows the brush | `8.8.8.8` brushed to its first active bar: `1 Nov 2024 · 16 · 1 Dec · 16 · 31`, against `5 Aug 2026 · 12 · 19 · 27 · 3 Sep` before. `2.2.2.2` over its full range: `1 Oct 2024 · 23 Mar 2025 · 13 Sep · 6 Mar 2026 · 26 Aug` |
| 6 | The out-of-reach empty state | `8.8.8.8` brushed to 2024-11-01…12-31: window count 14, lanes 2 + 12, 0 rows, and the capped sentence with `14`, `300`, `447` in it. The plain sentence stays hidden |
| 7 | It does **not** fire where it must not | `2.2.2.2` (201 of 201, uncapped) and `45.155.205.233` (17 of 17): neither empty state shows at the default window or over the full range |
| 8 | Dark theme | the second tick line reads in both, on the same `--bs-secondary-color` as the first; no new token |
| 9 | Console | no page error and no console error on any of the six loads |

The one thing not covered: the no-JavaScript render of the ruler is the
template's, which is what §16.5 row 7 already exercises — but a brushed
window has no no-JS equivalent, so the recomputed labels are verified
with a script running and only that way.

## 18. The brush goes back to the server

§17 answered the reader's two objections and got two more back, one of
them the same objection refusing the answer it was given.

### 18.1 The mask was dimming the month names

§17.1's second tick line made the axis taller, and `.vp-tl-spine` was
telling the brush how much of the canvas is axis in a constant:

```css
/* The 22px the month labels below the bars occupy */
--vp-brush-floor: 22px;
```

At 38px of axis, 16px of the mask sat over the month names — the brush's
dimming is `color-mix` with `--bs-body-bg`, so in the light theme it
reads as a white wash over exactly the labels §17.1 had just made worth
reading.

The constant was already approximate before that: Chart.js rotates tick
labels when they will not fit, and a rotated axis on this panel is 55px.
So the number is not one the stylesheet can hold. The spine's chart now
carries a plugin that writes `--vp-brush-floor` from
`chart.height - chart.chartArea.bottom` in `afterLayout`, which is the
axis's real height for the grain, the width and the rotation Chart.js
settled on, and it re-runs on every resize. The CSS keeps a default —
34px, one line plus a year — for the moment before the chart exists.

Measured after: the brush's bottom edge and the plot area's bottom edge
are the same pixel, in both themes, at both the unrotated 38px axis and
the rotated 55px one.

### 18.2 The chronology fetches the window it is asked about

§17.3 shipped a sentence saying *these 14 entries exist and this list
cannot show them*, and §15 kept the fetch that would show them as
deferred. The reader's reply was that they had expected the brush to go
and get them, the way the Relationships tab's tables go back for what
the front end does not hold. That is the right expectation, and the
sentence was a smaller answer than the question deserved.

**`forTimeline` takes a window, and it narrows the rows and nothing
else.** `$options['window']` is a `from`/`to` pair of `Y-m-d`, and the
whole design is one line: it reaches the *lanes*, not their output.
Every lane caps at `TIMELINE_ROW_CAP` and the cap means *the newest
cap-many*, so a filter applied after it would be selecting from a
fortnight; applied before it, the same cap means the newest 300 of what
the reader is looking at. Two lanes needed more than
`timelineLane`'s filter:

- **The edit lane with the audit log on** does not build rows in PHP —
  it reads them under a `LIMIT` with `id DESC`. So the window has to
  reach the query, as `AuditLog.created` bounds. It buys no speed
  (`created` is unindexed), and it costs none: the scan is already
  bounded by `model`/`model_id` to the value's own rows, which is what
  `auditCountsFor` scans anyway.
- **The seen lane is deliberately left whole.** It caps at 25 bars and
  takes the *oldest*, so it is cheap to carry entire, the client already
  filters marks to the window, and windowing it would make its
  sub-label — three whole-value numbers — start describing a slice.

**The counts are never windowed**, and that is what makes this a
different list rather than a different panel: `by_day`, `by_source`,
`total` and the range are tallied over every row each lane found, so a
chronology fetched for November 2024 still draws two years of bars above
it, with the brush painted over the month it was fetched for. `in_window`
is the one count that takes the window, and it is a sum over that same
whole map.

**The window is in the path**, and `self::period` validates it —
`viewHistory`'s shape and `viewHistory`'s validator. It is not the same
kind of thing there: History's period is the panel's subject, and what
that endpoint returns for one window is a different fragment top to
bottom, where this one returns the same spine whatever it is given. It
takes the path shape anyway, because a reader of the two actions should
not have to learn that one page states a window two ways.

**The gesture is `settle`.** `attachBrush` has offered a release hook
since the History tab needed one, and it is the seam: `range` fires
every few pixels and re-scopes what is already here, `settle` fires once
on release and asks the server if the window holds rows the fragment
does not carry. Two guards, both of which fire in practice — the window
the fragment already is (a brush over every bin of a spine fetched for
every bin), and a window whose rows are all present. A click clears the
brush, and on a fetched fragment that means going back for the default
window; `Reset window` does the same, and the server renders it visible
when it was asked for a window, so a reader who arrived by URL has the
way back before any script runs.

`reloadAjaxTabIndex` keeps the old markup and dims it, so a release is a
panel that dims and re-fills rather than one that collapses to a
spinner and pushes the page around.

### 18.3 What the list says it is showing

`Showing the newest 300 of 447 entries` is a statement about the
fragment, and a fragment fetched for a window is a different claim: the
rows are the newest of *it*. So the header names which set the cap bit
into — *the newest 300 of 2,256 entries in this window* — and, where the
window fits whole, says nothing at all, which is the usual case after a
fetch and the point of having one.

§17.3's empty state stays, and it is now a state the brush passes
*through* rather than lands in: it is what a reader sees for as long as
they hold the pointer. Its last clause is the script's, because only the
script knows whether the fetch is available, and its advice is
*release the brush to fetch this window*.

### 18.4 Verified

Live instance, real browser, logged in, plus the facade probe for the
model layer.

| # | Check | Result |
|---|---|---|
| 1 | `parallel-lint` on the three changed PHP files, `node --check` on the JS, 80-column rule over the diff | clean |
| 2 | **§14's six values, unwindowed** | 6/6 hold every invariant. No count, range or row set moved: the default path is what it was |
| 3 | **The windowed facade** — counts identical, window honoured, rows accounting for the aggregate per source | `8.8.8.8` / Nov 2024: 13 listed against an aggregate of 13, `{edit: 12, sighting: 1}` both ways; `total` 447, range and `by_day` byte-identical to the unwindowed call |
| 4 | **Over the cap** | `193.161.193.99` over its whole range: 2,256 in window, 300 listed, `{edit: 276, publication: 24}`, header reads *the newest 300 of 2,256 entries in this window* |
| 5 | **The heaviest value, an old window** | `443` / 2020: 174,299 total, 1 entry in the window, 1 listed. 21 seen-lane rows carried outside it by design. 3.4 s windowed against 3.8 s unwindowed — the occurrence read dominates and the window does not add to it (busy box; not a clean number) |
| 6 | **The audit window reaches the query** | `8.8.8.8` / Nov 2024 lists `audit_logs` rows dated 11–15 November 2024, which are 300 rows older than the unwindowed read returns |
| 7 | **The gesture, end to end** | brush the first active bar → mid-drag the empty state with *release the brush to fetch this window* → release → one request to `…/2024-11-01/2024-11-30` → 13 rows, lane marks, `Reset window` shown, no cap notice. `Reset window` → the default fragment. A click on a fetched spine → the default fragment. Exactly one request per gesture |
| 8 | **The mask** | brush bottom == plot bottom, both themes, at 38px and 55px of axis |
| 9 | Console | no page error and no console error across every run |

**One thing observed and not touched:** the panel is fetched twice on a
page load that arrives with `#tab-timeline`. Both are the plain endpoint,
before any gesture, and nothing on either of §17's or §18's paths can
issue them — `loadAjaxContainer` guards on `dataset.loaded`, which is
only set when a response lands, so two triggers firing before the first
returns both pass. It is `mispOvermind.js`'s, it predates this phase, and
on `443` it is a 3.8-second read done twice.

## 19. The lanes say which span they have no rows for

§18 made the cap survivable and left one thing dishonest. Brush a window
holding more entries than the cap carries — `193.161.193.99` over its
whole range is 2,256 against 300 — and the fetch comes back with the
newest 300, which is the right answer. But the lanes then draw marks
across four fifths of the axis and nothing across the first fifth, while
their `IN WINDOW` cells read *204 Published* and *2,052 Edit*. A reader
looking at the left of that axis sees a quiet period. It is not quiet;
it is unfetched, and nothing on the panel said so.

**The band is that admission, put where the silence is.** It runs from
the window's start to the oldest row the fragment carries, on each lane
that has entries it cannot draw.

### 19.1 One cut line, and why it is one

The row cap is applied once, to the merged array: `timelineCap` keeps
the newest 300 of the union, so **every** lane's rows are newer than the
300th newest overall. One boundary is therefore true for all of them,
and the band's right edge is the same x in every lane — which is what
makes it read as one fact about the fetch rather than seven facts about
seven lanes.

`null` — no row at all in the window — is the mid-drag state, and the
band then covers the whole axis. That is the same window §17.3 gave an
empty chronology to, now with lanes that agree with it: on `8.8.8.8`
brushed to November 2024, Sightings and Edits are hatched end to end and
their counts read 1 and 12.

### 19.2 Which lanes get one, and which must not

Per lane, the band appears when the aggregate for its sources exceeds
the rows it drew. So a lane with nothing in the window stays clean —
Sightings, Notes/Opinions and Seen spans are blank on the
`193.161.193.99` case, because a hatch over a span where that lane has
nothing would be claiming a truncation that never happened.

**The seen lane never gets one.** Its own cap takes the *oldest* 25 of
its spans, so the entries it drops are the newest and a band anchored to
the window's start would point at the wrong end of the axis. Its
sub-label already states all three of its numbers. The undated lanes
have no time axis to band. Both exclusions are the `draw` kind, checked
in one place in each renderer.

The grid's total is summed over the lanes that drew a band rather than
taken as *window total less rows carried*, so the seen lane's own
truncation is never counted into a sentence about the row cap. On
`193.161.193.99`: 180 publications and 1,776 edits, and the note reads
1,956.

### 19.3 A different hatch, and that is the point

`.vp-lane-fill` is already a hatch on this panel — grey, 135°, on the
lanes MISP cannot date. It means *this can never be drawn*. The band
means *this is dated and was not fetched*, which is a hole in the
request rather than in the record, and a reader who cannot tell the two
apart learns something false about the instance. So the band is
warning-toned, at 45°, with a solid edge at the cut, and the sentence
naming it carries the same hatch at a legend's size — a swatch and not a
word, because what the reader has to match is a texture.

The advice in that sentence is real: *brush the hatched span to fetch
them*. The newest cap-many of a narrower window reaches further back, so
brushing into the band is what un-hatches it — which is why the band and
§18's fetch had to ship in that order and not the other.

**One geometry note.** The band's width is
`calc((100% - 10px) * var(--vp-cut))` and not a percentage, because a
percentage width on an absolutely positioned child resolves against
`.vp-lane-axis`'s padding box while the SVG beside it resolves against
the content box. Ten pixels of difference is a band whose edge misses
the first mark — on the one element whose whole job is to say exactly
where the marks start. Measured: the band's right edge and the first
mark's left edge are within one pixel, which is the mark's own inset.

### 19.4 Verified

| # | Check | Result |
|---|---|---|
| 1 | `php -l` on the element, `node --check` on the JS, 80 columns over the diff | clean |
| 2 | **Over the cap** | `193.161.193.99` fetched for its whole range: bands on Publications and Edits only, `--vp-cut` 0.2545 on both, titles reading *180 / 1,776 … older than the 300 rows this fetch carries*, note *1956 with no mark* |
| 3 | **Nothing cut** | the same value's default window: no band, note hidden, `n` 0 |
| 4 | **Nothing carried** (mid-drag) | `8.8.8.8` over November 2024: `--vp-cut` 1, full-width bands on Sightings and Edits, note *13*; and after the release fetches it, no band and the note hidden again |
| 5 | **Alignment** | band right edge 274px, first mark 273px, from the same origin |
| 6 | **The two renderers agree** | the raw fragment for `…/2025-11-26/2026-09-01`, fetched with no script running, carries the same two bands, the same `0.2545`, the same 1,956 and the same title as the browser draws |
| 7 | Both themes | the 45° warning hatch is legible against the 135° grey one in light and dark; the legend swatch matches the band |
| 8 | Console | no page error, no console error |

### 19.5 Next, and not yet done

The cap itself. `TIMELINE_ROW_CAP` is 300, and with §18's fetch and this
band the number is no longer load-bearing for honesty — it only decides
how often a reader has to brush. Raising it to 1,000 is the obvious
follow-up and is **held pending review of this band**, because a wider
cap makes the band rarer and the right time to look at it is while it is
still easy to reach.
