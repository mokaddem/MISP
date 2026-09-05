# PRD: Value Profile — Timeline goes live

**Phase 25**, the fourth live phase. Converts `value_timeline` — the tab's
one endpoint and the three regions inside it — from `ValueProfileFixture`
to the database. Depends on [`00-contract.md`](00-contract.md) §14 and on
the three phases before it, whose seam, facade and tools this extends. The
tab's fixture-era design is
[`06-timeline.md`](../value-profile-tabs/06-timeline.md); what MISP can and
cannot date for it is `value-profile-page.md` §8.2, and this phase is the
one §8.2 named as having to close its open choice.

**Opened 2026-09-04, closed 2026-09-05.** §1 is the task board, §1.1
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
| T10 | Proposals lane | §10, §25 | **done** |
| T11 | Event reports lane | §10, §25 | **done** |
| T12 | The spine's grain taken from the range, not pinned | §9, §16.2 | **done** |
| T13 | Remove the ACL band §14.6 forbids and §14.6's table missed | §12 | **done** |
| T14 | The board rows: §14.12 `viewTimeline`, and this document's numbers | §14.12, §16.5 | **done** |
| T15 | The axis names its years; the ruler follows the brush and names them too | §17.1, §17.2 | **done** |
| T16 | The chronology stops denying entries the counts state | §17.3 | **done** |
| T17 | The brush's mask stops where the plot area does, at any axis height | §18.1 | **done** |
| T18 | `forTimeline` takes a window; the brush fetches one on release | §18.2, §18.3 | **done** |
| T19 | Each lane bands the span it counts and has no row to draw | §19 | **done** |
| T20 | `TIMELINE_ROW_CAP` 300 → 1,000 | §19.5, §20.3 | **done** |
| T21 | The band's tooltip fires; a mark's says what the server wrote | §20.1, §20.2 | **done** |
| T22 | The spine's key filters the chart and the chronology | §21 | **done** |
| T23 | Tag and cluster attachments leave the Edits lane for one of their own | §22.1, §22.2 | **done** |
| T24 | The tab says when the instance first held the value | §22.3 | **done** |
| T25 | The tag set is placed at each tag's first attach | §22.7 | **done** |
| T26 | The seen lane's span labels stop overlapping | §26 | **done** |
| T27 | The axis runs to today, and the empty end is drawn as a wait | §27 | **done** |
| T28 | The seen lane falls back to the object's span, labelled | §28.3 | **done** |
| T29 | The object-date lane — D8 reopened and widened | §28.5 | **done** |
| T30 | Every entry opens the record behind it; the lane labels too | §29 | **done** |

**Where the phase stands. Every row is done and the phase is closed
(2026-09-05).** The tab reads the database: the endpoint is wired,
**nine** dated lanes and the off-axis strip are live, and the panel
renders with no fixture behind it for either reader class and with the
audit log on or off. §16 is the build log; §17 to §22 are what the first
reader of the built tab asked for over six rounds, and T15 to T25 are
that. **§25 is T10 and T11**, the two lanes the coverage survey owed,
and it closes `value-profile-coverage.md` §2.4 — the one item in that
survey with a cost per live phase deferred — by giving
`Value::conditionsFor()` the `alias` option a second value table needs.
**§26 is T26**, one round of reader feedback over the closed phase: the
seen lane was printing its span labels on top of each other. **§27 is
T27**, a second such round: the axis stopped at the value's last activity,
so a value dead ten weeks drew the same picture as a live one. **§28 is
T28 and T29**, a third: the reader asked which of the three places a date
can live this tab was reading, and the answer was one of them. **§29 is
T30**, from the same round: the panel named records it gave the reader no
way to open, and had no link anywhere in it.

One row is deliberately not here. The tab badge needs nothing: the
registry gives Timeline no count and §14.13's *"whoever converts a tab
next: check its badge"* is satisfied by there being none to check. The
question was put again on 2026-09-05 — *should the tab carry an activity
badge, like Relationships?* — and the answer is still no, now with a
number behind it: a badge that agreed with this panel would have to read
its lanes, and `viewTimeline` is 156 ms on a quiet value and seconds on
`443`. What a cheap badge could say instead is the *edit* lane's
recency, which is not the tab's. The fact strip above the tab bar
already carries the recency question, is visible from every tab, and
already links here.

**The passive-dns lane is no longer deferred.** §15 named it D8; §28.5
reopened it, found the framing too narrow by two orders of magnitude —
51,994 objects on this instance hold a `datetime` attribute, not 829 —
and built it as the general *Object dates* lane.

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
| D8 | ~~The passive-dns lane is deferred a second time, with the cost named~~ — **reopened and built, §28.5.** Reopened by the reader asking for it, and the reopening falsified the row's own scope: the source is `datetime` attributes in *any* object, 51,994 of them, not 829 passive-dns ones | §15, §28.5 | Closed |
| D11 | The seen lane draws the containing object's span where the occurrence carries none, as its own source, and never where the occurrence has one | §28.2, §28.3 | A viewer class for whom an object's span is *not* a claim about a value inside it. The de-duplication half would reopen on an instance where object and attribute spans disagree often — here it is 1 object in 36 |
| D12 | `datetime` attributes in the value's objects are their own lane, never folded into Seen, and every mark carries the relation that named it | §28.5 | A vocabulary in which the relations *are* one notion. `compilation-timestamp` and `send-date` are why this one is not |
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

**It is built; this section orients a reader picking the closed phase
up, and is no longer a plan.** `ValuesController::viewTimeline`
(`ValuesController.php:600`) calls `ValueProfile::forTimeline`
(`ValueProfile.php:6364`), and nothing in that path reads
`ValueProfileFixture`. What the phase did not have to build: the
endpoint, its `ACLComponent` entry (`ACLComponent.php:1083`,
`theming_enabled`), and the skeleton descriptor in `Values/view.ctp`.
`value_timeline.ctp` was 1,255 lines rendering the fixture's array when
the phase opened; it is 3,040 rendering the database now.

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
>
> **Extended in §27.** The bins still *begin* at the range and the grain
> is still chosen from it, but the axis now ends at today rather than at
> the value's last entry. The two numbers came apart there and everything
> below that says "the range" means the first of them.

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

> **§22.2 adds a tenth**, Tag changes, which this plan does not have
> because the fixture filed a tag attachment as an edit too — so nothing
> here knew there was a lane missing.

| Lane | Live source | Fetcher / accessor | Tier | The risk |
|---|---|---|---|---|
| Sightings | `sightings` | phase 23's `sightingContext`, reused whole | 1 | none new — but the count is the viewer's, per §14.6, and this tab must not restate it as the instance's |
| Publications | `events.first_publication`, `publish_timestamp` | one `Event::fetchSimpleEvents` for all N | 1 | epoch-0 is common — 4,235 of 4,287 events carry a publish timestamp and only 2,858 a first publication; and event 4116 carries a first publication with **no** current one. Two points per event is a ceiling, not a promise |
| Notes / Opinions | `notes`, `opinions` | `AnalystData::fetchChildNotesAndOpinions` over the occurrence ∪ event union | 1 | §8's labelling; `rearrangeOrganisation`'s nesting; one `object_type` that is not a type |
| Edits | `audit_logs` | §5's three id-scoped reads | 1 rows, 2 counts | §3.3's 162,539; and the not-recorded branch, which this instance cannot show |
| Seen spans | `attributes.first_seen` / `last_seen` | already on the occurrence rows | 1 | §7's cap; microsecond epochs; instants outnumbering spans 2:3 |
| Proposals | `shadow_attributes.timestamp` | ACL'd fetch, `old_id` kept for the row's wording | **2**, and see §25.2 | thin data — 23 rows instance-wide |
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
| Proposals | **yes — built 2026-09-05** | §10 for the verdict, **§25 for the build**. `shadow_attributes.timestamp` is dated, which §8.2's scoreboard missed; the lane is thin on this instance and real, and §25.2 found it needs two scopes rather than the one §10 assumed |
| Feeds / servers | **no — already settled** | `06-timeline.md` §12 proves it from `Feed.php:1573`: one timestamp per feed, rewritten on every refresh. §22.6 then removed the hatched lane entirely — there is no period for a feed to be quiet in, and the off-axis chip keeps the fact on the tab |
| Event reports | **yes — built 2026-09-05** | §10 for the verdict, **§25 for the build**. `event_reports.timestamp`, 174 on the instance, 8 on `8.8.8.8`'s events and 7 of those to an org admin of another org — which is the ACL working where `attachReportCountsToEvents` would have returned 0 |

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

**~~The passive-dns lane~~** (`06-timeline.md` §16, `24-relationships.md`
§26.7) — **no longer deferred; built in §28.5 as the object-date lane.**
829 passive-dns objects on the instance, 665 carrying both `time_first`
and `time_last`. The query is the one `value_relation_dated` already runs
and caches, so the data is close to free; what is new is a lane, its
hatching rule when the value sits in no relational object, and the
chronology rows. It stayed deferred because this phase was already taking
three decisions and adding two lanes, and because the eighth source's
absence was honest — the Relationships tab shows those dates today. The
cost of deferring: a value whose whole story is in passive-dns dates
reads on this tab as a value with almost nothing dated.

**What reopening it found.** The deferral costed the *passive-dns*
template, which is the one `value_relation_dated` folds — but the source
is `datetime` attributes in any object, and **51,994 of the instance's
69,992 objects hold one**. So the deferred item was two orders of
magnitude larger than its own cost line, and the lane it became is not
the one described here: it reads no far value, needs no second date, and
does not go through the relationship scan. §28.5 has the built shape.

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

> **Superseded by §21.4.** A filtered list is now counted the same way,
> because the filter has an aggregate of its own to be counted from. The
> plain sentence keeps only the window that is empty of everything the
> reader asked for.

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
follow-up and was **held pending review of this band**, because a wider
cap makes the band rarer and the right time to look at it is while it is
still easy to reach. Reviewed and raised in §20.3, which also records
what the band looks like once it is rare.

## 20. The band gets a tooltip, and the cap goes to 1,000

§19's band shipped with a `title` on it. It never fired.

### 20.1 An inline `<svg>` hit-tests as one box

`elementFromPoint` over the band returned `svg`, everywhere across it.
The lane's `.vp-lane-svg` is an inline SVG at `z-index: 1` covering the
whole axis, and it answers for every pixel of that box — including the
four fifths of it that are empty — so nothing underneath it can be
hovered. The band's tooltip was unreachable from the moment it was
written.

The axis now passes the pointer through its empty space
(`pointer-events: none` on the SVG, `auto` on its children, scoped by
`[data-vp-tl-axis]` so `.vp-strip` on the Sightings tab is untouched).
The marks keep their own hit area, which they must: a mark's `<title>`
is the only place it says what it is. Verified both ways — the band is
the hit target across its width and carries its `title`, the marks
still resolve to `rect.vp-lane-mark` with their own `<title>`, and an
empty lane with no band falls through to the axis and offers nothing.

`.vp-lane-fill` gets the same treatment for the one lane that can wear
both hatches — with the audit log off, the edit lane explains its own
grey hatch and can still be banded over a busy window — with its text
keeping pointer events so it stays selectable.

### 20.2 And the marks' own tooltips were a dump of the row

Found while checking the above, and older than any of this. `tlEntries`
read each mark's tooltip out of the row's `.vp-tl-main` text, which
holds the source label, the title, the precision chip **and the
template's indentation**. So the server rendered `abuse.ch` and one
paint later the same mark said `Sighting\n            abuse.ch\n
exact`. The row now carries `data-vp-tl-title`, which is the same string
the server puts in the mark, and the script reads that: one source, and
the two renderers cannot drift.

### 20.3 `TIMELINE_ROW_CAP` 300 → 1,000

The constant was `OCCURRENCE_CAP`'s number for `OCCURRENCE_CAP`'s
reason — a pager that renders one button per page inline and collapses
past twenty. **The chronology has no pager**; it shows a windowful and
reveals the rest in place. What actually bounded it was that brushing
past the newest 300 gave an empty list, and §18 fixed that by letting
the brush ask for a window. With the fetch and §19's band in place the
cap stopped deciding whether the panel is *honest* and went back to
deciding only how often a reader has to brush — so it buys the wider
one.

**Measured, on a quiet box, before and after:**

| | 300 | 1,000 |
|---|---|---|
| `8.8.8.8` fragment / rows | 578 KB / 323 | 836 KB / 482 |
| `193.161.193.99` fragment / rows | 571 KB / 323 | 1,796 KB / 1,092 |
| `193.161.193.99` windowed | 739 KB | 2,395 KB |
| `443` fragment / rows | 557 KB / 310 | 1,739 KB / 1,022 |
| `viewTimeline` on `193.161.193.99` | 156 ms | 224 ms |
| `viewTimeline` on `443` | 3,696 ms | 3,766 ms |

**1.8 MB decoded is 79 KB over the wire.** Nginx serves the fragment
gzipped and the chronology is the most repetitive markup on the page, so
it compresses about 23:1. The endpoint costs ~70 ms more on a busy value
and nothing measurable on `443`, whose 3.7 seconds are
`Value::occurrenceIdsFor` and were never the rows.

**What it cost the browser, and what was done about it.** 1,092 rows is
9,502 nodes in the panel, and a brush re-scopes all of them on every
pointer move. Ten frames of a drag measured 278 ms — 28 ms a frame,
over a 60 Hz budget. Two things were being redone per frame that never
change while a fragment is on screen: `tlEntries` rebuilt the whole
entry array out of the DOM, and `tlRefreshList` re-queried both row
sets. Both are now built once per fragment and dropped in
`initTimeline`, and the visibility writes are guarded against setting
`hidden` to what it already is. Ten frames: **278 ms → 204 ms → 178 ms**.
What remains is the lanes' mark rendering, which scales with how much of
the window is brushed rather than with the cap.

**What changed on the instance's values.** `8.8.8.8` is 447 entries and
is no longer capped at all: no cap notice, no band, and brushing its
first active bar now lists the rows with **no round trip** — the case
that opened §17 is simply gone. `143.14.244.37` still reports a cap, and
that is the seen lane's own 25-of-32, which this constant does not
govern. `193.161.193.99` and `443` still need everything §18 and §19
built: 1,000 of 2,256 and 1,000 of 174,299.

**One thing the wider cap makes visible.** The band's width is time, not
volume, and on `193.161.193.99` the two come apart hard — 1,256 of its
entries land in the first two days of a 280-day range, so the span the
cap leaves out is 0.9% of the axis. The band was 25% wide at 300 and is
a 9-pixel tick at 1,000, with the same 1,256 behind it. It keeps a 3px
floor so it cannot round away, and the count stays in the sentence
above, which is the part that is discoverable; the band says *where*.

### 20.4 Verified

| # | Check | Result |
|---|---|---|
| 1 | `parallel-lint`, `node --check`, 80 columns over the diff | clean |
| 2 | **The band's tooltip** | hit target across its whole width, `title` present; mid, left and near-edge all resolve to `div.vp-lane-cut` |
| 3 | **The marks keep theirs** | three probes resolve to `rect.vp-lane-mark` with `<title>` `ADMIN`, `CIRCL`, `abuse.ch` — the organisation, as the server wrote it |
| 4 | **An empty lane claims nothing** | a point in the analyst lane resolves to `div.vp-lane-axis`, no title |
| 5 | **§14's six values at the new cap** | 6/6 hold every invariant. `8.8.8.8` 447 of 447, `443` 1,000 of 174,299 |
| 6 | **`8.8.8.8` needs no fetch now** | brushed to November 2024: 5 rows on screen, `Reset window` offered, and **no request** on the wire |
| 7 | **The band at 1,000** | `193.161.193.99` over its range: bands on Publications and Edits, 111 and 1,145, note *1,256*, `--vp-cut` 0.0086, band right edge and first mark both at 9px |
| 8 | **Digits group the same both ways** | the raw fragment and the repainted panel both read *1,256* and *the newest 1,000*; `tlCount` matches `number_format`'s defaults rather than the browser's locale |
| 9 | Both themes, console | reads in light and dark; no page error, no console error |

---

## 21. The key filters the chart

Asked for by the reader in one line: *make the legend of the timeline
filter said timeline when clicking on an item.*

The key was a caption. On `8.8.8.8` the spine is 447 entries of which
369 are edits, so the chart is a wall of grey with the sightings, the
publications and the analyst notes drawn as a hairline along the floor —
and the only control over it was a brush, which narrows *time*. There
was no way to ask the chart about a source.

### 21.1 What a press does

**Plain click solos.** The chart draws that source alone, the count axis
closes over it — `8.8.8.8`'s y axis goes 100 → 20 for sightings and →
3 for publications — and the chronology lists the same source's rows.
Pressing it again lets it go, which is the gesture the lane buttons
already offered.

**Shift, ctrl or meta-click drops one.** From no filter that is the
subtractive reading a stacked legend usually has — take the tall segment
out and read the rest against their own axis — and from a filter it adds
or removes one source at a time. A selection that ends up naming every
source, or none, is stored as *no filter*: keeping it would leave the
note claiming a narrowing that is not one, and an empty chart offers
nothing to press a way out of.

`aria-pressed` on a key means **this source is drawn** and starts true,
which is deliberately the opposite of a lane button's *this lane is the
whole filter*. Two gestures, and each one's `title` says which. Off is
legible without the dimming carrying it alone: the swatch hollows to an
inset ring in its own hue, so the state survives a display that cannot
separate two opacities.

### 21.2 One filter, two controls, and neither can lie

`tl.filter` was a comma-joined lane string that only the chronology
read. It is now an array of source keys — or null for all of them — and
three things render it: the key, the lane buttons and the note over the
chronology. All three are recomputed from the state by `tlSyncFilter`
rather than toggled where the click landed, which is what makes a lane
press move the keys and a key press release the lane.

The note names the filter the shortest way that is true, in this order:

| The filter is | The note reads |
|---|---|
| exactly one lane's sources | *Showing **Sightings*** — the lane's own label, not its three sources spelled out |
| everything but one source | *Hiding **Sighting*** — what a shift-click took out |
| anything else | *Showing **Published, Note, Edit*** |

`Showing` and `Hiding` travel in the payload's `labels` so the sentence
is the server's vocabulary, not the script's.

The spine is **rebuilt** on a filter change (`bootChart` hands back a
refresh, not the instance — the same shape the Sightings panel's
`hiddenOrgs` uses). That costs one rebuild per click and none per brush
frame: the spine covers the whole range whatever the window is, so a
filter is the only thing that can change what it draws. The datasets are
marked `hidden` rather than dropped, so an index and a colour cannot
move under a press.

### 21.3 What it deliberately does not touch

**The lanes.** They are one lane per source already, so filtering them
would blank rows rather than answer anything, and each lane's `In
window` count stays that lane's own truth. The lanes' header keeps the
unfiltered window total for the same reason.

**A lane whose sources this value has nothing dated of no longer gets a
button.** With the filter reaching the chart, pressing the Sightings
lane on `193.161.193.99` — which has only publications and edits — drew
an empty spine under a note naming a source that was never there, which
reads as a broken panel rather than as an answer. Those lanes now render
their chip as a label with a `title` saying there is nothing to narrow
to. The lane itself still renders in full: §8.2's rule is that what is
missing must be as visible as what is not.

### 21.4 The capped empty state now answers for a filter too

§17.3 gave the chronology a second empty state — *N dated entries fall
in this window, and none of them are among the rows this list carries* —
and confined it to unfiltered lists, because the only number available
was the window's whole total. The filtered total is the same aggregate
sum restricted to the selected sources, so the state is no longer
confined: a reader who narrows to a source whose rows the cap dropped
gets the sentence that is true instead of *nothing dated falls in this
window*, which contradicts the count beside the window label. The key
makes that one press away, so the fix ships with it.

### 21.5 Verified

Driven in a real browser against the instance —
`prd/value-profile-live/25-key-filter-harness.mjs <value> [theme]`, which
logs in, opens the tab and reads the chart's datasets, the axis maximum,
both controls' `aria-pressed`, the note, the visible rows by source and
both empty states after each gesture.

| # | Check | Result |
|---|---|---|
| 1 | `node --check`, `php -l`, 80 columns over the diff | clean |
| 2 | **The server ships a caption** | 7 keys, all `disabled` and `aria-pressed="true"` in the raw fragment; live after `initTimeline`, like the brush |
| 3 | **Solo** | `8.8.8.8`, press `Sighting`: 1 of 7 datasets visible, y axis 100 → 20, note *Showing Sighting*, 14 rows all `sighting` |
| 4 | **Solo something small** | press `Published`: y axis → 3, one row, and the Publications *lane* button presses itself because the filter is exactly its source |
| 5 | **Release** | pressing the same key again: 7 of 7 visible, y axis back to 100, note hidden |
| 6 | **Shift-drop** | shift-press `Sighting`: 6 of 7 visible, its swatch hollow with a `1.5px inset` ring in its own hue, note *Hiding Sighting* |
| 7 | **Shift back** | every source selected is stored as no filter: note hidden, nothing pressed |
| 8 | **The lane drives the same state** | pressing the Sightings lane presses its three keys and unpresses the other four; releasing it restores all seven |
| 9 | **`clear` in the note** | releases both controls and the chart |
| 10 | **A filter survives a brush** | brushed while soloed on `sighting`: window 28 → 329 entries, the hidden datasets stay hidden |
| 11 | **And a brush that refetches** | `193.161.193.99` brushed onto its first active bar: `viewTimeline` on the wire, the new fragment arrives unfiltered and coherent, and a press on it filters — 2,000 → 200 on the axis |
| 12 | **Filtered, capped, empty** | `193.161.193.99` soloed on `Published` over a window whose rows the cap dropped: the capped sentence with the filtered count, not *nothing dated falls in this window* |
| 13 | **No dead ends** | `193.161.193.99` renders lane buttons only for Publications and Edits; `8.8.8.8` loses only its Seen spans button; a value with nothing dated has no `data-vp-tl` at all and no key |
| 14 | Both themes, console | reads in light and dark; no page error, no console error across every gesture above |

---

## 22. Two questions from the reader, and what the data said

> *In the edit lane, I see entries such as "added tag xxx", wouldn't that
> entry qualify for the "tags" lane? Maybe we could also give more
> visibility to "first time seen on this instance"?*

Both were right, and the first one is bigger than it looks.

### 22.1 The Edits lane was mostly not edits

`audit_logs` on this instance, by action:

| action | rows |
|---|---|
| `tag` | 5,132,220 |
| `add` | 4,200,523 |
| `soft_delete` | 122,795 |
| **`edit`** | **28,862** |
| `galaxy` | 8,182 |
| everything else | < 1,000 each |

**Tagging is the most common audit action by two orders of magnitude
over editing.** A tag attachment is an `audit_logs` row whose `model` is
`Attribute`, `Object` or `Event` and whose `action` is one of `tag`,
`tag_local`, `remove_tag`, `remove_local_tag` — with the tag's name in
`model_title`, which is the one case where that column holds something
other than the model the row names. The audit reader was picking them all
up and filing them under `source => 'edit'`.

What that did to the panel, measured on two values:

| | Edits lane before | after |
|---|---|---|
| `8.8.8.8` | 369 edits | 197 edits · 126 tags · 46 clusters |
| `94.98.224.81` | 5,861 edits | 1,474 edits · 4,387 tags |

Three quarters of the busiest value's *edit* history was tag
attachments — and the tab was drawing them three rows above a Tags lane
whose own text read *"a tag can be dated only by an audit_logs row"*.
Both statements were true. The rows were in the wrong lane.

### 22.2 One lane out, one lane in, and no new query

`AuditActionMeta::group()` is the new home of the judgement — the shared
action vocabulary, so the History tab can read the same one — and it
answers `tag`, `cluster` or `edit`. Everything not listed is `edit`:
whatever the action did, its subject was the record itself.

The split costs **nothing**. `auditCountsFor` already grouped by day
*and* action, because the lane's own breakdown needed it, so keying the
day map by group instead of flattening it to a per-day total is a
different fold over the same rows. `timelineAuditLanes` then slices one
read and one aggregate into two lanes: each takes its share of the
newest cap-many rows the query returned, and each states counts from the
uncapped aggregate underneath. Per-group `first`/`last` come out of the
same fold, which is what lets each lane band the span its own capped
rows could not reach (§19).

`tag` and `cluster` share the new lane because they share one mechanism
— a cluster attachment is a tag underneath — and stay two sources
because the spine stacks them separately and §21's key filters on them.
The colours are MISP's own `--tag` and `--galaxy`, so a tag mark here is
the colour a tag is everywhere else in the product.

**What the undated Tags row keeps saying.** The tag *set* a value
carries now still has no date of its own — `attribute_tags` has no
`created` column on any instance — so it stays on the off-axis strip and
in its own structurally-empty lane, with its reason rewritten to point
at where the dated half went. Two facts, two places, and neither claims
the other's ground.

**And one string this found.** The Edits lane's sub-label read *latest
per occurrence* in both of its shapes. That is the fallback's
description — one point from `attributes.timestamp` — and over the audit
branch, where the lane draws one mark per logged change, it was simply
false. It now says which shape it is in.

### 22.3 *First time seen on this instance* is two different answers

MISP stores **no creation date for an attribute.** `timestamp` is the
last modification; `first_seen` is an analyst's claim about when the
threat was seen in the world, which this tab already draws in a lane of
its own; an event's `date` is the intel's date, not the record's. So the
tab cannot print one number and call it the arrival — but it can print
what the rows support, and say which of two things that is:

| Evidence | Sentence |
|---|---|
| An `add` row in the audit log, no later than every other trace | **On this instance since 2026-09-01** — *the oldest creation the audit log holds for these records.* |
| An occurrence's last-modified stamp, older than anything dated | **On this instance by 2022-06-28** — *from an occurrence's last-modified stamp; MISP records no creation date for an attribute, so it may be older.* |
| Only the oldest dated entry | **On this instance by 2026-05-09** — *the oldest dated thing here.* |

One sentence in two prepositions — *since* is a date, *by* is a bound —
with the clause after it naming the row, so the distinction never rests
on the preposition alone. And never the words *first seen*: that phrase
belongs to the seen-span lane, which is about the world rather than
about this instance.

The `add` row is usable only where it is no later than every other trace
of the value. The audit log on this instance begins **2024-11-11**, and
a record created before that leaves the oldest `add` row describing some
*later* arrival — so `8.8.8.8`, whose oldest occurrence stamp is
2022-06-28, gets the bound and not the audit date. That is the common
case here and the reason the bound exists at all.

It sits under the axis whose left edge it qualifies, because that edge
answers a different question: **`8.8.8.8`'s chart starts in November
2024 and the value has been on this instance since at least June
2022.** Nothing on the tab said so before.

### 22.4 Verified

Both branches of `MISP.log_new_audit`, and the off one **without
touching the instance**: `setSetting` does not reach PHP-FPM behind
opcache, and flipping it for real on a shared box is not worth it, so
the setting was written in-process from a throwaway Console shell that
rendered the element the way `renderPanel` does (`render-ctp-via-console-shell`,
deleted before the commit).

| # | Check | Result |
|---|---|---|
| 1 | `php -l`, `node --check`, 80 columns over the diff | clean |
| 2 | **The split is exact** | `8.8.8.8` 369 → 197 + 126 + 46; total dated still 447, and `94.98.224.81` 5,861 → 1,474 + 4,387 |
| 3 | **Rows read as what they are** | *Tagged “stone:source="OSINT"” — event 4182* under a Tag chip; *Galaxy attached “Exploit Public-Facing Application - T1190”* under a Galaxy cluster chip; the Edits lane left with *Edited — event 3753* and *Added — object 72001* |
| 4 | **The spine stacks them apart** | `94.98.224.81`'s 3,000-tall December bar is 2,400 tag and 600 edit where it was one grey block |
| 5 | **Each lane bands its own span** | `94.98.224.81`: Publications, Edits and Tag changes all carry the §19 hatch, each over its own uncovered span, with *4,866 with no mark* in the header |
| 6 | **§21's key and lane filter pick it up with no change** | 9 keys; pressing the Tag changes lane leaves Tag + Galaxy cluster visible with y axis 100 → 35 and the note *Showing Tag changes*; pressing the Galaxy cluster key alone → 20, note *Showing Galaxy cluster*, and no lane pressed, because the filter is one of that lane's two sources |
| 7 | **Audit log off, model** | `audit_recorded: false`, no `tag` or `cluster` source at all, edit lane back to 26 occurrence stamps, 104 dated of the 447 |
| 8 | **Audit log off, rendered** | 7 keys not 9; 4 lane buttons not 5, because the Tag changes lane is dead and §21.3's rule drops its button; the hatch renders inside `data-vp-tl-axis="tagging"` — *a tag is dated only by an audit_logs row, and MISP.log_new_audit is off* — and the Edits sub is back to *latest per occurrence* |
| 9 | **The two branches agree about arrival** | both say `8.8.8.8` was here by 2022-06-28, reached two different ways: an occurrence stamp with the log on, the oldest dated record with it off |
| 10 | **All three first-here sentences** | *since* on `dns.google` (created 2026-09-01, after the log began), *by* from a stamp on `8.8.8.8` and `193.161.193.99` (2021-03-31, against an axis starting 2025-11), *by* from a record on `143.14.244.37` |
| 11 | Both themes, console | reads in light and dark; no page error, no console error |

**One thing this cost, and it is worth writing down.** The first attempt
at the audit-off branch ran `cake Admin setSetting` through `docker exec`
as root, which rewrote `app/Config/config.php` root-owned. The web
server could then no longer read its own config and every request
redirected to `/users/login` — the instance was down until the
ownership was put back. `cake` inside the container runs as
`-u www-data`, always.

### 22.5 The Tags lane stays, and says less

Asked, once the dated lane existed: *what is the Tags lane for?*

It is §8.2's rule — a source the tab promises gets a full-size lane
whether or not MISP can date it, so an absence is as visible as a
presence. The lane carries the tag set the value holds **now**, which is
a different fact from the attach and detach events next to it: those are
a stream that starts wherever the audit log starts, and you cannot read
the current set off them. **And with `MISP.log_new_audit` off — MISP's
default — it is the only row on the tab that says the value is tagged at
all.** So it stays.

What was wrong was the wording, not the lane. The sub-label said *no
column exists, any instance*, which describes a missing column rather
than the row, and the body said the same thing twice at three clauses'
length. Now: **what it carries now, undated**, over *"The tags this
value carries now. attribute_tags has no created column, so nothing
dates them — when each was attached is in the Tag changes lane."* Same
facts, one sentence shorter, and the first four words say what the row
is. The cluster row and the audit-off hatch took the same pass.

### 22.6 The feed lane comes out

> *If you cannot say anything about the feed appearance, remove the
> lane. It's useless.*

Correct, and the lane's own text said as much: the feed cache is a Redis
set with **one timestamp for the whole feed**, rewritten on every
refresh, so there is no date for *this value in that feed* at all — not
an imprecise one, none. A full-size row was spending the tab's most
expensive space on that.

This is a partial reversal of §8.2's rule, and the test it fails is
worth writing down, because the Tags lane passes it. The rule buys
visibility for an absence **a reader could mistake for a quiet period**.
Tags are that: a value with no tag marks on the axis looks like a value
nobody tagged, so the lane says otherwise. Feeds are not: there is no
period to be quiet in, and which feeds hold the value is answered in
full — names included — by the External sources panel on the
Relationships tab.

What stays is the one-line chip on the off-axis strip, *Feed appearances
3 — as of 2026-09-04 04:59*, with the cache's own explanation in its
`title`. That keeps the panel's *N named but undatable* subtitle
checkable against something, which removing the row as well would
break.

The lane grid is seven rows again, one of them hatched, and the two
lines that counted them are corrected.

> **§22.7 dated that hatched row too**, so the counts moved again: six
> lanes carry marks, and the header and footer name the tag set rather
> than a kind MISP cannot date.

### 22.7 The tag set goes on the axis

> *Why not place the tags from the "tags" lane where they first appear.
> I think first tag occurence is good enough since there's no deletion.
> A nicely formated tag label with a clear indication where it first
> appear would be cool. Only one occurence per tag.*

Right on every count, and it makes the Tags lane the seventh lane that
can put something on the axis rather than the one that cannot.

**One mark per tag, at its first attach.** A tag is re-attached on every
re-import — `dark-web:structure="test"` five times on one attribute
inside half an hour — so the raw stream says how often something
re-tagged the value, and the first attach says when the value *became*
that thing. `ValueProfile::timelineTagState` joins the set the value
carries now (`Value::ownTagsFor`, already read for the strip) to
`MIN(created)` over the tag and galaxy actions on its own occurrences
and objects, matched on `model_title` — the only handle an audit row
gives — and **never on the event rows**: an event tag of the same name
is the event's claim, and dating this value's own tag from it would
report someone else's action as this value's.

**Two corrections to the premise, both visible on the panel.**

*Removals do exist* — 174 `remove_tag` and 129 `remove_local_tag` rows
here. They do not need modelling, and the reason is the one the request
gives from the other side: the lane draws the set the value carries
**now**, so a tag that was taken off is simply not in it, and one that
was removed and re-added keeps its first attach, which is still when
this value first became that thing. Detachments stay in Tag changes,
where a stream belongs.

*The audit log has a horizon.* It begins 2024-11-11 on this instance, so
a tag attached before that — or by an import that did not log — has no
row and no date. Those are kept with a null date rather than dropped:
the lane's sub-label reads **first attached · 6 of 8 datable** and the
rest are named on the off-axis strip. Dropping them would have made the
lane claim the value was untagged until its oldest datable tag.

**The readable half is not on the axis, and that is the design.** First
attaches cluster at the *start* of a value's history while the window
defaults to the last month: on `8.8.8.8` all seven land between April
and December 2025, so a lane that said this only in marks would say
nothing at all until the reader brushed back two years. So the lane is
a pair — marks on the axis for the window, and a full grid row under it
that is window-independent:

```
FIRST ATTACHED  17 Apr 2025 [PAP:RED] │ 20 Apr 2025 [tlp:white] │
                26 Nov 2025 [asyncrat] [c2] [historicalandnew]
                [mightcontainvariantsofasyncrat] │ 2 Dec 2025 [Gh0stRAT]
```

Real MISP tag badges, oldest first, **grouped by day** — `193.161.193.99`
took 77 tags on one afternoon, so a date per chip printed *26 Nov 2025*
twelve times and buried the one thing it was there to say. Twelve chips
then `+65 more, newer`, which is `TIMELINE_CHIP_CAP`'s existing rule.

**A mark wears the tag's own colour**, which is what makes eight marks
in one lane distinguishable — and a tag's colour is whatever an analyst
picked: nine tags on this instance are `#ffffff` and fourteen are
`#000000`, each of which is the lane's own ground in one of the two
themes. The marks take a non-scaling hairline so they stay marks
whatever they are filled with.

**What this retired.** The undated Tags lane, and with it the last
`draw => 'undated'` lane — §22.6 removed the feed one and this dates the
tag one, so the branch, its `$undatedBy` map and the
`.vp-lane-undated*`/`.vp-tl-src-none` rules are gone rather than left
unreachable. §8.2's rule loses its lane and keeps its purpose: what is
unplaceable is now a *subset* of a lane's own subject, and the lane that
holds those tags states its own gap — which is the visibility the rule
was buying, said by the row that has the facts. The strip's chips stop
reading *no date column*, which was true of a tag only while nothing
dated one; they now read **no audit row**.

**What it costs.** One grouped read, on the same `model_id` index the
audit aggregate uses and bounded by the tag names the value carries:
**9–11 ms** on `193.161.193.99`, the worst case on this instance at 77
distinct tags over 670 attachments. Nothing at all on `443`, which
carries no own tags — the method returns before querying, and
`ownTagsFor` was already being called for the strip, so there is one of
those and not two.

### 22.8 Verified

| # | Check | Result |
|---|---|---|
| 1 | `php -l`, `node --check`, 80 columns over the diff | clean |
| 2 | **All datable, few** | `8.8.8.8`: four date groups, seven badges, `0 of 7 tags` in the default window |
| 3 | **All datable, many** | `193.161.193.99`: one group, twelve badges, `+65 more, newer`, `of 77 tags` |
| 4 | **Partly datable** | `1.162.239.42`: sub-label *first attached · 6 of 8 datable*, strip *Galaxy clusters 2 — no audit row*, subtitle *18 dated · 2 named but undatable* |
| 5 | **No tags at all** | `143.14.244.37`: *Nothing has tagged this value.* on the hatch, and no *of 0 tags* against it |
| 6 | **Both renderers agree** | server, asked for 2025-11-01→12-31: 5 marks, `5 of 7 tags`; the same window brushed in the browser: 5 marks, same titles, `5 of 7 tags` |
| 7 | **One mark per tag, not per attachment** | 5 marks for 5 tags where the raw stream holds 172 tag and cluster rows |
| 8 | **A tag's own colour** | four distinct fills read off the marks, matching the badges; `tlp:white` (`#ffffff`) visible against the light lane and `#000000` against the dark one, on the hairline |
| 9 | **The chip row ignores the window** | 7 chips at the default window with 0 marks, and still 7 after brushing to Nov 2025 |
| 10 | **Cost** | new read 9–11 ms on the 77-tag value, none on `443`; endpoint 58 ms warm on `8.8.8.8`, 139 ms on `193.161.193.99` |
| 11 | Both themes, console | reads in light and dark; no page error, no console error |

## 23. Four encodings for the lane axis, benched

> *"For the timeline 'source in this window', I find all the square hard
> to read and I feel like the visualisation could be improved. You can
> keep the current lane design, but could you prototype 3 other type of
> visualisation for each lane? Tags is fine, though when some were added
> at the same time, only one entry is visible."*

`prd/value-profile-live/25b-lane-designs.html` — a standalone bench, not
part of the application. Served from disk:

```
python3 -m http.server 8899 -d prd/value-profile-live
http://localhost:8899/25b-lane-designs.html
```

### 23.1 What a square cannot say

The lane draws one 5×13 rect per fetched row at that row's moment. Three
things follow, and all three are visible on this instance:

| | |
|---|---|
| **No magnitude** | `8.8.8.8`: a lone Sightings mark is one sighting; the mark beside it, at the right-hand end, is twelve. Nothing distinguishes them. Only the count column has the number, and it has one number for the whole window. |
| **No mix** | Tag changes is `126 Tag, 46 Galaxy cluster`. The lane is almost uniformly orange, because a cluster mark is one square among many and lands under a tag mark as often as not. |
| **Collision is silent** | Marks at one x are one square. `8.8.8.8` draws **7 tags as 4 marks**; the Edits lane has 42 rows on one x and 197 rows on 66 distinct positions. |

At the dense end it is worse than lossy, it is misleading:
`193.161.193.99` holds 204 publications, 952 edits and 1,100 tag changes
in the window, and the three lanes draw **the same eighteen squares**.
A reader comparing those lanes reads three equal rows.

### 23.2 The three proposals

Only the axis cell changes. The label, the sub-label, the ruler, the
count column, the cut band and the tag chip row are the shipping ones in
all four, and every design places a moment at the same fraction of the
window the shipping scale uses — so a column in A, a cell in B and a
stem in C land on the pixel a square lands on today.

**A · density profile.** Calendar bins, magnitude as bar height on the
lane's own linear scale, sources stacked inside the bin with a surface
gap, the peak bin direct-labelled and nothing else. Reads shape and
volume at a glance. Loses the exact moment to the bin, and loses the
tail wherever one bin dominates — on `193.161.193.99` a 203-row week
flattens a 4-row week to a hairline.

**B · aligned heat strip.** Cells that cannot overlap, on boundaries
every lane shares, tinted on a five-step square-root ramp of the lane's
own hue, with the count printed in any cell over one. The only design
that answers *what else was this value doing that month* by reading one
column downwards, and the only one that stays fully legible at 1,100
entries — the tail `4, 9, 10, 11, 3` that A flattens is read out here as
figures. Multi-source cells are split into **equal** stripes, not
proportional ones: at ~28px a cell, one false positive against eighteen
sightings is a sub-pixel sliver, so the stripe carries identity and the
numeral carries quantity.

**C · coincidence stems.** No binning: every row keeps its exact moment,
and rows sharing a moment become one stem whose height is how many. One
pip per row while a pip is at least 2px, so small pile-ups are countable
and large ones degrade to a segmented bar. This is the smallest change
from what ships and the only proposal that keeps the exact day. A busy
lane reads as a comb.

### 23.3 The Tags lane, which was the specific complaint

All three fix it, differently, and none of them touches the chip row:

- **A** — a column whose height is how many tags were first attached
  then. `193.161.193.99` peaks at **41 in one week**, which the shipping
  lane draws as one square.
- **B** — up to six stripes in the tags' own colours inside the cell,
  countable; past six a tint and a figure, because a numeral over six
  saturated hairlines is unreadable on all of them.
- **C** — a tower of pips, one per tag, each in its own colour. On
  `8.8.8.8` the four tags of 26 Nov 2025 are four pips.

### 23.4 Separable from the choice

Three changes are orthogonal to which encoding wins and are worth taking
with whichever does:

1. **Month rules** behind every lane (toggleable on the bench). No
   design needs them; all four read better with them.
2. **Packed spans** — the Seen lane puts overlapping spans on their own
   rows, so a row means an overlap. Today they are drawn on one line and
   overlap composites into a darker patch, which is the alpha-arithmetic
   problem §24b of `24b-relationships.md` raised for the strip.
3. **The dead space.** A lane box is 90px tall because the count column
   needs three lines for `47 Sighting, 4 False positive, 2 Expiration`;
   the shipping marks occupy the top 38px and the rest is empty. The
   three proposals centre their track in the box.

### 23.5 The bench's data is real

`25b-lane-designs.data.js` was read off the running instance on
2026-09-04. Three values fetched at
`/values/viewTimeline/<b64>/<from>/<to>`; every mark's x, colour and
tooltip taken out of the rendered DOM and its moment inverted from x
through the shipping scale; the colour tokens read with
`getComputedStyle` in both themes. Nothing is seeded.

| Value | Window | Why it is on the bench |
|---|---|---|
| `8.8.8.8` | 2024-11-01 → 2026-09-04 | All nine sources, 447 entries, nothing capped, and the 7-tags-as-4-marks case |
| `mughalmotifs.com` | 2024-01-01 → 2026-09-04 | The only lane that draws intervals: 52 of 52 occurrences carry a span, 25 drawn |
| `193.161.193.99` | 2025-11-01 → 2026-09-04 | 2,256 entries against a 1,000-row fetch, 1,256 behind the cut band, 77 tags |

### 23.6 Verified

| # | Check | Result |
|---|---|---|
| 1 | Renders, no console or page error | clean on 3 values × 2 themes |
| 2 | No horizontal page overflow | none, at 1500px |
| 3 | No mark escapes its lane box | 0 of 461 / 156 / 1,154 marks, all designs |
| 4 | The cut band survives all four | 12 bands on `193.161.193.99` (3 mark lanes × 4 designs) |
| 5 | Spans survive all four | 75 bars on `mughalmotifs.com` (25 × 3 proposals) |
| 6 | The chip row is untouched | present in all four designs, every value |
| 7 | Marks lanes only for the cut band | the Seen lane takes none — its cap cuts the newest, so a band from the window's start would be backwards |
| 8 | Heat numerals legible in both themes | ink chosen from the mixed tint's measured luminance, not from the mix step: `--vp-tl-edit` at full strength is near-black in light and a light grey in dark |
| 9 | The palette, checked rather than eyeballed | the ten source tokens pass CVD separation (worst adjacent ΔE 16.6 deutan) but fail the lightness band on `--vp-tl-edit` and `--vp-tl-seen` — which is why every proposal carries magnitude on position or size, and B carries a scale legend and a table view |
| 10 | A colour-carried number has a twin | B ships the per-bin table view; the matrix's glyphs are shape, not hue |

## 24. The lanes become density profiles

§23's bench put three encodings beside the shipping one and the answer
came back **A, the density profile — with the Tags lane's segments in
each tag's own colour**. This is that, in the tab.

### 24.1 What a lane draws now

One column per calendar bin, its height that bin's share of the lane's
own tallest bin, segmented by source — or, on the Tags lane, by tag.
The bins are `ValueProfileBuckets`, the helper the spine already uses,
over the window rather than over the value's whole range, at a grain
this axis chooses for itself:

```php
$laneRule = array(
    array('days' => 120, 'unit' => ValueProfileBuckets::DAY),
    array('days' => 730, 'unit' => ValueProfileBuckets::WEEK),
    array('days' => null, 'unit' => ValueProfileBuckets::MONTH),
);
```

740 viewBox units wide, so past about 120 columns a column is thinner
than the gap beside it. The default 30-day window gets days;
`8.8.8.8`'s two years get weeks; `mughalmotifs.com`'s 978 days get
months.

**Every lane bins identically**, which is the point of binning at all: a
reader comparing two lanes reads straight down a column boundary.

### 24.2 The Tags lane wears the tags' colours

A segment per tag first attached in that bin, in the colour an analyst
gave that tag, oldest at the bottom — the fact that lane exists to
carry, and the one the chip row underneath spells out in names. A
source lane's segments answer *which source*; these answer *which tag*.

Two consequences of a colour nobody on this side chose:

- **An outline on the column**, because nine tags on this instance are
  `#ffffff` and fourteen are `#000000`, each of which is the lane's own
  ground in one of the two themes. Without it a white tag at the top of
  a stack is a shorter column. Each segment takes a hairline of its own
  once it is 3 units tall; below that the hairline is most of the
  segment.
- **Shared boundaries, not independent heights.** `193.161.193.99` puts
  41 tags in one week — 41 segments in 25 units. Rounding each height
  on its own left a sub-pixel crack between every pair and the column
  read as a barcode. Each rect now takes its edges from the same two
  rounded numbers its neighbours use, so they tile exactly.

### 24.3 What is unchanged, and why

| | |
|---|---|
| **The seen lane** | Still spans. It draws intervals, not instants, and a first-seen span binned into a column would be a bar saying *something lasted a while somewhere in here*. |
| **The cut band** | Unchanged, and still mark-lanes-only. A column drawn from rows the fetch did not carry would be a lie the band is there to prevent. |
| **The chip row** | Untouched. It is the window-independent half of the Tags lane. |
| **The count column** | Unchanged — still the aggregate, still allowed to exceed what the lane drew. |
| **The key filter** | Never scoped the lanes and still does not. |

### 24.4 The peak, and nothing else, is labelled

A height with no number is a texture: a reader can see that this bin is
twice that one and cannot tell whether either is three rows or three
hundred. So each lane direct-labels its tallest column — *peak 203* —
and labels nothing else, because a figure on every column is the thing
nobody reads. Below three it is not printed at all; a lane whose
busiest bin holds two rows is saying nothing the bars have not.

It is HTML over the plot and never SVG text, for the reason
`.vp-lane-tag` is: `preserveAspectRatio="none"` smears a word along
with the box. The bars stop 13 units short of the plot's top so it has
somewhere to sit — and it wears `line-height: 1`, because the
inherited line box put a 0.62rem label 5px into the column it named.

### 24.5 `.vp-lane-plot`, and the baseline that was floating

A lane box is as tall as the tallest of the three cells in its grid
row, and the count column runs to three lines —
`47 Sighting, 4 False positive, 2 Expiration` — so the box is 90px and
the plot is 38. Top-aligned, that put the columns' baseline halfway up
the lane with nothing underneath it, which reads as a chart that has
come loose. It never showed while the lane drew a band of marks with no
baseline to misplace.

So the axis centres its content, and the plot is a box of its own —
everything placed over the columns is now placed against the columns
rather than against a lane box whose height belongs to the cell beside
it. The peak label and the seen lane's span labels both moved into it.

### 24.6 One vocabulary, two renderers

The server draws the fragment and the script redraws it on every frame
of a brush. The two have to agree bin for bin and rect for rect, or the
lane jumps when the reader lets go of the handle. So the grain rule,
the column geometry and the bin's name are decided once in PHP and
shipped — `lane.rule`, `lane.base`, `lane.bar`, `lane.gap`, and the
twelve month names `months` already carried:

- `tlLaneBins()` mirrors `ValueProfileBuckets::series()` and
  `::locate()`, and takes its thresholds from `lane.rule`.
- `tlColumns()` mirrors `$columnsFor` — the same scale, the same
  hairline condition, the same shared-boundary tiling.
- The bin's title is built from `months` by one rule for all three
  grains, in both. `ValueProfileBuckets::describe()` writes
  *November 2025* for a month bin and the script has only the
  abbreviations, so reusing its title would have put the two renderers
  a word apart on every month bin.

### 24.7 Verified

| # | Check | Result |
|---|---|---|
| 1 | `php -l`, `node --check`, 80 columns over the diff | clean (the four over-80 lines in `value-profile.css` are pre-existing, line 15650+) |
| 2 | **The two renderers agree** | rect-for-rect identical — class, x, y, width, height, style — on every lane, plus the same peak label and position: `8.8.8.8` 2024-11-01→2026-09-04 (week grain, 119 rects), `193.161.193.99` 2025-11-01→2026-09-04 (week, 127), `mughalmotifs.com` 2024-01-01→2026-09-04 (month, 36), and the default 30-day window (day, 8) |
| 3 | **The brush rebins** | dragged over the spine on `8.8.8.8`: window 2026-08-05→2026-09-03 becomes 2025-04-01→2026-01-31, lanes redraw, peaks update *9 → 66*, tag colours survive, no console error |
| 4 | **Four values, both themes** | `8.8.8.8`, `mughalmotifs.com`, `193.161.193.99`, `443` (3,860 tags, 48,255 occurrences): 7 lanes and 7 plots each, no page or console error |
| 5 | **Nothing escapes its plot** | 0 bars or peak labels outside the plot box, 0 non-positive heights, 0 rects crossing the baseline, over all eight renders |
| 6 | **The peak clears its column** | 0px overlap on every labelled lane in every render; it was 4.9px before `line-height: 1` |
| 7 | **41 tags in 25 units** | tiles with no crack, and the outline keeps `tlp:white` a band |
| 8 | **The cut band survives** | `193.161.193.99` bands publications, edits and tag changes, and the header still reads *1,256 with no column* |
| 9 | **The seen lane is untouched** | 25 span rects on `mughalmotifs.com` at full window, server and client identical |

**Not exercised:** the hatched Edits lane's ground rect, which needs
`MISP.log_new_audit` off. That is an instance-wide setting and flipping
it on this stack risks the `config.php` ownership fault, so the branch
is written to match the mark version it replaces and is stated here as
untested rather than demonstrated.

---

## 25. The two lanes the coverage survey owed

**T10 and T11, built 2026-09-05, and the phase closes with them.** §10
chose both fetchers before anything was built and neither choice was
overturned; what the build found is below, and one of the three findings
falsifies a claim §24.7 made about the whole tab rather than about these
two lanes.

### 25.1 The seam grew the parameter §2.4 said it would

`value-profile-coverage.md` §2.4 named `Value::conditionsFor()`'s
hardcoded `Attribute` alias as **the one item in that survey with a cost
per live phase that ships**, because `shadow_attributes` carries its own
`value1`/`value2` pair and §14.3's rule is that no file but `Value` may
spell those columns at all. This phase is the one that needed it, so
this phase added it:

```php
Value::conditionsFor($value, array('alias' => 'ShadowAttribute'))
// → ['OR' => ['ShadowAttribute.value1' => …, 'ShadowAttribute.value2' => …]]
```

It defaults to `Attribute`, and **none of the fourteen existing call
sites passes the key** — three of them pass a page-wide options array
that could have carried one, so the new fetcher builds its own array
rather than forwarding the caller's. Asserted rather than assumed: the
three call shapes in use (no options, `types`, and an options array
carrying `limit`/`page`/`order`) each produce an array `===` to the
literal the old code built.

### 25.2 Two scopes, and one row on the instance proves both are needed

The obvious scope for a proposals lane is *proposals against this
value's occurrences*. It is wrong on its own, and so is its opposite.

Proposal 12 proposes `2.2.2.3` against attribute 1495259, which holds
`2.2.2.2`:

| Reader is on | Reaches it by | What the row says |
|---|---|---|
| `2.2.2.2` | the **target** it points at | *ADMIN — proposes 2.2.2.2 → 2.2.2.3* |
| `2.2.2.3` | the proposal's **own** columns | the same row, from the other end |

Neither scope alone carries both readings, and this is not hypothetical:
it is one of the instance's 23 proposals, and the **only** one of the 17
attribute-targeted ones whose value differs from its target's. The other
16 are reached both ways, which is why the union deduplicates by id
rather than concatenating, and why `proposed` wins the tie — a proposal
naming this value is about it more directly than one that merely targets
a row holding it.

**Two statements, not one `OR`.** The scopes sit on different columns of
different tables, and an `OR` spanning those is the shape that cost the
co-occurrence panel a full table scan. Each half matches a prefix index
of its own; `shadow_attributes` carries `value1(255)` and `value2(255)`,
the same shape `attributes` has.

**The target scope is a join, not an id list.** `old_id` is a `belongsTo`
to `MispAttribute`, so `ShadowAttribute::buildConditions` has already
joined `Attribute` to express its own ACL — the value predicate rides
that join for nothing. Written as `old_id IN (occurrence ids)` it would
be an `IN` list of 48,255 integers on `443`.

### 25.3 `deleted = 1` is *resolved*, and the schema cannot say how

The build went looking for the word *withdrawn* and could not honestly
write it. Accepting a proposal and discarding one both end at
`ShadowAttribute::setDeleted()` (`:510`, reached from the accept path at
`:991` and `:1024` and from discard at `:1075`), and that method writes
`deleted = 1` **and stamps `timestamp` with the moment it ran**.

Two consequences, both carried by the rows rather than smoothed over:

- **A resolved proposal sits on the axis at its resolution**, not at its
  proposal. There is no column that would place it anywhere else.
- **No row may say *withdrawn*.** It says *Resolved on this date —
  accepted or discarded, and MISP records only that it closed.*

`shadow_attributes` has no `created` column at all, so every row on this
lane is `latest` precision, which is the tab's existing chip for exactly
this. `event_reports` has no `created` either — `timestamp` is set on
create and rewritten by `EventReport::touch()` — so the reports lane is
`latest` too. **The two are not symmetric**, and the sub-labels say so:
a soft-deleted report saves only its `deleted` column, so a withdrawn
report keeps the date of its last content edit where a resolved proposal
is stamped with its resolution.

### 25.4 What each lane draws

| | Proposals | Event reports |
|---|---|---|
| Source | `shadow_attributes.timestamp` | `event_reports.timestamp` |
| Scope | the value's own columns ∪ its occurrences' | the value's events |
| Gate | `ShadowAttribute::buildConditions` | `EventReport::buildACLConditions` |
| Precision | `latest` | `latest` |
| Placed beside | Edits — a proposal is an edit that has not happened | Notes / Opinions — the same union's other half |

**The report lane does not use `attachReportCountsToEvents`**, and D6 did
not reopen: that method's non-site-admin branch ANDs
`distribution IN (1,2,3,5)` with `distribution = 4` where an `'OR' =>`
was intended (`EventReport.php:392-407`), returning 0 for every event the
viewer's org does not own. It ships and it is on the standing
do-not-fix list. Reading through `fetchReports` instead is visible in the
verification: an org admin of org 9 sees **7** of `8.8.8.8`'s 8 reports,
which is an ACL narrowing the event scope — not the zero the broken
method would have returned.

**The proposal lane's ACL is looser than the occurrence fetcher's, and
that is MISP's rule rather than this page's.**
`value-profile-coverage.md` §2.2 found that a standalone proposal
(`old_id = 0`) is OR'd past the whole attribute-and-object distribution
test, because there is no attribute to test, and is therefore gated by
**event visibility alone**. Applied as MISP wrote it and stated here
rather than tightened silently.

### 25.5 The colour was measured, not chosen

An event report is `--report` on every other MISP surface, so it is
`--report` here — the same argument that gave the tag and cluster marks
MISP's own two.

A proposal has no MISP colour to inherit. The product's answer is amber:
Overmind paints a standalone proposal row `--bs-warning`
(`mainOvermind.css:1174`). **It cannot go there**, for two reasons that
compound — the palette already spends three sources in the warm band
(`#d97706`, `#f39a1f`, `#DB6A47`), and **`.vp-lane-cut` hatches in
`--bs-warning` inside this very lane**, so an amber column and *these
rows were not fetched* would be the same hue in the same box. §19.3 made
that distinction load-bearing; an amber proposal would undo it.

So it was picked by pairwise CIEDE2000 over the full palette under
normal, deutan, protan and tritan simulation, in both themes:

| Candidate | Worst ΔE | Nearest |
|---|---|---|
| `#1d4ed8` **chosen** | **10.2** | sighting (protan) |
| `#4f46e5` indigo | 9.9 | cluster (protan) |
| `#0f766e` teal | 8.8 | note (protan) |
| `#6366f1` first try | **0.7** | cluster (protan) |

`#1d4ed8` sits inside the shipping lightness band (L\* 39.0, band
31.9–75.9) and clears 6.7:1 on the light ground; it manages only 2.5:1
on the dark one, so it takes a dark-theme override the way `note` and
`edit` already do — `#6ea8fe`, Bootstrap's own dark link blue, at 7.0:1
and 8.2 ΔE from its nearest neighbour.

**And the measurement found something it was not looking for.** The ten
tokens that ship today have a pair at **ΔE 0.9 under deutan
simulation — `opinion` `#f39a1f` and `seen` `#97CC04`** — in both
themes. §23.6's check 9 measured *worst adjacent* separation and got
16.6; these two are not adjacent in the legend, so nothing looked at
them. They are in different lanes, but they are adjacent segments in the
spine's own stack and in its key. **Pre-existing, not introduced here,
and not fixed here** — recorded in the open backlog because changing a
shipping source colour is a decision about the whole palette rather than
about these two lanes.

### 25.6 §24.6's invariant is not quite true, and was not true before this

Verifying the two renderers over the new lanes turned up a drift in the
shared column geometry, so the check was widened to all seven mark
lanes. The two spell the same rounding in a different order:

```php
// value_timeline.ctp, $binBox
$x  = $fractionFor(…) * $LANE_W;              // unrounded
return array(round($x, 2), round(max(1.0, $to - $x - $BIN_GAP), 2));
//                                        ↑ width from the UNROUNDED x
```
```js
// value-profile.js, tlColumns
var x = Math.round(edge(…) * 100) / 100;      // rounded first
var w = Math.round(Math.max(1, edge(…) - x - gapX) * 100) / 100;
//                                       ↑ width from the ROUNDED x
```

So the server's width can differ from the client's by up to 0.01 viewBox
units — `6.71` against `6.70`, `6.45` against `6.46`. Over 740 units
rendered at about 700px that is under a hundredth of a pixel, and it is
invisible; what it is not is *rect-for-rect identical*, which is what
§24.7's check 2 claimed for the whole tab.

**Measured, not asserted**: 28 lane comparisons over 219 rects, four
windows across three values, server fetch against client redraw —
**0 real differences, 0 peak labels differing, 78 rects differing in
`width` alone by ≤0.011**. The drift is spread across every lane; the
two new ones carry their proportional share and no more. Left unfixed
deliberately: `tlColumns` is shared by all seven mark lanes, so changing
it is a change to the built tab that needs its own re-verification of
all of them, which is not T10 or T11.

### 25.7 What it costs

Endpoint total, `forTimeline` end to end, best of four runs, and the two
new fetchers timed on their own. **These are not comparable with §16.4's
figures** — that table was taken at load 0.76 and this one between 1.30
and 1.75, which is why the two lanes' own cost is given separately
rather than as a difference of endpoint totals.

| Value | Reader | `proposalsFor` | `fetchReports` | Endpoint |
|---|---|---|---|---|
| `8.8.8.8` | site admin | 4 ms, 1 row | 9 ms, 8 rows | 27 ms |
| `8.8.8.8` | org admin (org 9) | 3 ms, 1 row | 6 ms, 7 rows | 35 ms |
| `193.161.193.99` | site admin | 4 ms, 0 rows | 6 ms, 0 rows | 100 ms |
| `443` | site admin | 3 ms, 0 rows | 18 ms, 2 rows | 3,234 ms |

**Three statements between them** — two for the proposal union, one for
the reports — and neither scales with the value. `443` is the case that
could have: 1,844 events in the report scope and 48,255 occurrences that
the target scope deliberately does not turn into an `IN` list. It costs
21 ms of a 3,234 ms endpoint whose cost is where §16.4 left it, in
`occurrenceIdsFor` and the audit aggregate.

### 25.8 Verified

| # | Check | Result |
|---|---|---|
| 1 | `php -l` on all three PHP files, 80 columns over the diff | clean |
| 2 | **The seam is behaviour-identical** | all three existing call shapes `===` the literal the old code built; `alias` returns the `ShadowAttribute` pair |
| 3 | **Both directions of the value-change case** | `2.2.2.2` draws proposal 12 as *2.2.2.2 → 2.2.2.3*; `2.2.2.3` draws the same row from the other end |
| 4 | **All five proposal shapes render** | adding (standalone, `tinyurl.com`), deleting (`5.6.3.4`), value change (`2.2.2.2`), editing (`8.8.8.8`), resolved (`tinyurl.com`) |
| 5 | **Three reader classes** | site admin / plain user / org admin of another org on `8.8.8.8`: 8 / 8 / **7** reports, 1 / 1 / 1 proposal, and the whole entry set 456 / 429 / 257 |
| 6 | **The tokens resolve before any colour is asserted** (§6.1) | `--vp-tl-sighting` non-empty first; then `--vp-tl-proposal` `#1d4ed8`/`#6ea8fe` and `--vp-tl-report` `#4DA167` |
| 7 | **The columns wear their own token** | `rgb(29,78,216)` light and `rgb(110,168,254)` dark for proposals, `rgb(77,161,103)` both for reports — computed fill against the resolved token, not against a literal |
| 8 | **Nothing escapes its plot** | 0 bars outside the plot box, both lanes, both themes |
| 9 | **The two renderers agree** | 28 lanes / 219 rects: 0 real differences, 0 peak labels differing (§25.6 has the 78 width-drift rects) |
| 10 | **The brush rebins the new lanes** | `8.8.8.8` full range → 2025-02-01→2025-12-31: reports 5 → 3 columns, proposals 1 → 0, tag colours survive, no console error |
| 11 | **Nine lanes on four values** | `8.8.8.8`, `2.2.2.2`, `193.161.193.99`, `443` — 9 axes each, no page or console error, no horizontal overflow at 1500px |
| 12 | **The empty state** | `mughalmotifs.com` draws both lanes with 0 columns and no band — a lane with nothing in it, not a lane that lies |
| 13 | Counts agree with rows | `8.8.8.8`: the count column reads *8 Report* / *1 Proposal* and the chronology carries 8 and 1 |

**Not exercised.** A proposal or report with `timestamp = 0`: the column
is `NOT NULL DEFAULT 0` on both tables so the row is possible, and none
of this instance's 23 proposals or 174 reports is one. Both branches are
written from the schema and excluded from the axis rather than plotted
in 1970, matching the publications lane — stated here as untested rather
than demonstrated.

### 25.9 What these two lanes still do not reach

**A value that exists only as a proposal still renders as unknown**, and
this phase did not change that. `forTimeline` returns null when the
viewer holds no occurrence of the value, so `2.2.2.3` — which the
proposals fetcher finds a row for — gets no timeline at all. That is
`value-profile-coverage.md` §2.2's finding, it is a property of the whole
page rather than of this tab, and a Timeline that rendered for values the
rest of the page calls unknown would be a tab disagreeing with its own
page. It stays in the open backlog, one item less blind than it was: the
fetcher that would serve it now exists.

---

## 26. The seen lane's labels stop printing over each other

Reported from the built tab on `143.14.244.37`: the bars overlapping is
fine — two spans that ran at once is a fact and the composite says it —
but the **words** were printing on top of each other.

### 26.1 Eight labels on one pixel

Every span drew a `.vp-lane-tag` naming its attribute, absolutely
positioned at its own `left:` and at a shared `top: 1px`. Nothing
checked whether two of them landed together, and on that value eight did
— at the same coordinate, to the pixel. The result read
`29344589344598`: not a dense label, an unreadable one.

Measured before the fix, over the whole panel: **138 overlapping text
pairs**, 128 of them `.vp-lane-tag` over `.vp-lane-tag`.

**Bars may overlap and words may not**, and the asymmetry is the whole
of the rule. Two bars on one line composite into a darker patch that
means *more than one span here*, which is true and readable. Two words
on one line mean nothing at all.

### 26.2 A label is drawn only where it clears the last one

Greedy, left to right, over the spans in ascending order: place a label,
remember its right edge, and skip any whose left edge falls inside it.
The dropped ones lose nothing a reader could have read, and their
`<title>` still names them — it is on the bar, not on the word.

**The estimate, and why it is conservative.** The labels are HTML at a
fixed `font-size` positioned in *percent* of a plot whose pixel width is
responsive, so whether two collide depends on a number the server does
not have. Measured on the instance: **6.22px per digit at 0.68rem**, and
the plot runs 1,078px at a 1500px viewport down to 570px at 992px. The
rule takes the **narrow end** as its reference — sizing it for the widest
plot would let labels collide on a narrow one, which is the defect;
sizing it for the narrowest drops the occasional label on a wide screen
that would in fact have fitted. One direction is a bug and the other is
a lane that says slightly less than it could.

`TAG_CHAR` and `TAG_REF` ship in the `lane` payload beside `bar`, `gap`
and `rule`, for §24.6's reason: the script rebuilds these labels on every
brush, and a second copy of the two numbers would be a second
vocabulary.

### 26.3 Two ways the renderers disagreed, both found by checking

Neither was visible as an overlap; both would have shown up as a label
changing when the reader let go of the brush.

**The script was walking the rows backwards.** It builds its copy of a
lane's rows out of the chronology's DOM, and the chronology is newest
first — so a greedy pass in arrival order runs *right to left* and keeps
the last label of a cluster where the server kept the first. On
`143.14.244.37` the server drew two labels and the script redrew one, in
the same window. The script now sorts ascending rather than trusting the
order it inherited.

**`at` alone does not order these rows.** Twenty-four of
`45.178.180.13`'s spans share one instant, so a sort on the timestamp
leaves the tie to whatever order each side's array happened to be in.
The two then agreed on *where* every label went and disagreed about
*which one it named* — `2934358` against `2934342`, at the same
`49.73%`. The tie-break is the attribute id ascending, in both.

### 26.4 Verified

Four values × three viewports × two themes, before and after a brush —
24 cases, 96 checks.

| # | Check | Result |
|---|---|---|
| 1 | `php -l`, `node --check` | clean |
| 2 | **No two labels intersect** | worst overlap **0px** in all 24 cases, against 128 overlapping pairs before |
| 3 | Every label stays inside its plot | 0 escaped, all cases |
| 4 | **Client and server name the same labels at the same places** | identical strings and `left:` values in all 24; the two disagreements above were found here and fixed |
| 5 | Still true after a brush | 0px worst overlap on the redraw path, which is the renderer the fix had to reach |
| 6 | Narrow viewports | 992px (570px plot) is the reference case and passes with the same sets |
| 7 | The rest of the tab is untouched | the §25.8 lane harness and the §25.6 rect comparison both re-run: all checks pass, 0 real rect differences over 219 rects |
| 8 | Panel-wide text overlap | 138 pairs → 18, and **none of the 18 is a `.vp-lane-tag`** — the remainder are the detector counting a `<b>` inside its own sentence |

The one thing this does not do is make every span nameable. A cluster of
twenty-four spans on one instant gets one label and always will; that is
the point rather than a shortfall, and §23.4's *packed spans* — giving
overlapping spans their own rows — is still the change that would let
the lane name more of them.

---

## 27. The axis runs to today, and the wait is part of the picture

From the reader of the built tab: *wouldn't it be better if the right
side was set to the current time instead of the last time the value had
activity? It would give a sense of how long compared to now.*

Yes — and not by doing only that, because doing only that costs the
chart something it cannot afford.

### 27.1 The right edge was the last thing that happened

`§9` put the spine over the value's whole dated range, and the range
ends at `counts['last']`. So on `143.14.244.37` the axis stopped on
**1 July 2026** while the panel was being read on **5 September**, and
the eight weekly bars filled the plot exactly as they would have if the
value had been seen that morning. Sixty-six days of silence were on the
page in one place only: the last tick label, which is the half of a
chart a reader skims.

That is the defect. Two values in two tabs, one live and one dead ten
weeks, drew **the same picture** and differed in a date the reader had
to subtract from today in their head.

### 27.2 The naive fix is worse, and the reason is not the obvious one

The obvious cost of `series($rangeFrom, $today)` is compression: a value
busy through 2016 and quiet since spends nine tenths of its axis on
nothing and draws its actual history as a sliver — and it does that
worst for the values whose history is most worth reading, the long-lived
ones.

The cost that is not obvious is **the grain**. §16.2's rule reads the
range: 45 days or fewer bins by day, 400 or fewer by week, more by
month. Widen the range with emptiness and the rule answers for the
emptiness. `yovtube.co` has one dated day; measured against the range
this section now draws — 339 days — it falls from days to weeks, and its
entire record is redrawn as a week-wide bar *because of the eleven
months of silence that came after it*. Emptiness would be deciding how
finely the data is drawn.

So the grain still comes from `$rangeDays`, which is the data's own
span, and the drawn range is a separate number arrived at afterwards.
One line apart in the template and the whole of why this is not a
one-line change.

### 27.3 Half the width of the data, and no more

The axis is `series($rangeFrom, max($rangeTo, $today))` — one series and
not a spliced-on tail, which matters for a reason worth writing down: at
month grain the last data bin is a *clipped* month (`443` ended
`2026-09-03`, not the 30th), so appending a second series starting the
next day would have produced two adjacent bins both labelled `Sep`.
Running one series to today extends that bin instead, and `443` keeps
its 80 bins with the last one now ending on the 5th.

The bins after the one holding `counts['last']` are the tail. **It may
take half the width of the data and no more** — a third of the drawn
axis — and past that the bins nearest today are kept and the rest fold
into a single elided bin.

The floor is two bins, for the values with no width to halve. A value
whose whole dated record is one bin has no resolution inside it to
protect, so the fraction has nothing to say and the tail gets the break
plus the bin holding today. That is why the share measured below is 33%
on the values with a history and 67% on the ones with a single day: the
first number is the rule and the second is the floor, and the floor
costs nothing because there is nothing behind it to squash.

The elided bin is **a real bin**. It carries a date span, a title, and
its own slot in `locate()`, so `tlBins()` and `tlWindow()` need no
special case, the axis stays contiguous, and a reader can brush it and
get the honest empty window it describes. What it is not is to scale.

### 27.4 The empty end is not hatched, and that is the point

This panel already spends two hatches: `.vp-lane-fill`, grey at 135°,
for *MISP cannot date this*, and §19.3's band, warning-toned at 45°, for
*this is dated and was not fetched*. Both mean **the record is missing
here**. The tail means the opposite — the record is complete and says
nothing happened — and a third texture would have made three things to
learn where the likely fourth reading is that they are all one thing.

So the tail is drawn the way an empty month has always been drawn on
this chart: with nothing in it. What marks it is a flat tint behind the
plot, painted in `beforeDraw` so the **grid lines stay on top of it** —
those lines are what say *zero* rather than *not plotted*, and a tint
over them would take away the one cue that separates the two.

The break is the printed convention: a gap cut out of the plot in the
canvas's own background colour, edged by two leaning rules. It is a
statement about the axis rather than a layer of it, so it goes in
`afterDatasetsDraw`, over everything.

And *today* is a word at the right-hand end rather than a rule somewhere
in the middle, because `series()` clamps its last bin to the current day
— the axis genuinely ends there. It is dropped where the band is too
narrow to hold the word, which is the 2%-tail case below.

### 27.5 *Quiet for n* is a claim, and claims get a floor

The band is drawn for any tail at all; the axis reaching today needs no
excuse. The sentence under the chart is different — it asserts that the
value has gone quiet — and the first cut of it said so about
`193.161.193.99`, last seen **four days** before. That is not a quiet
value; it is a value.

The floor is **one bin of the spine's own grain**, which makes it
proportionate rather than absolute. The grain already follows the
value's range, so a value with five years behind it has to go a month
silent before the panel says anything and one with three weeks has to go
two days. A flat thirty days would have called the first quiet at a
fraction of its own rhythm and never said a word about the second.

The sentence and the band are both shipped, not one or the other. The
band is what a reader sees without looking — it is the thing that makes
a dormant value and a live one different *pictures* — and a length read
off an axis is an estimate. The sentence is the number, it names the
date, and it is the only form the wait takes for a reader on a screen
reader or with no script at all. Where the axis is broken it says so and
names the span the break stands for, which is the one fact the break
itself cannot carry.

### 27.6 What does not move

- **The default window.** Still `TIMELINE_WINDOW_DAYS` back from the
  newest entry, clamped. Anchoring it to today instead would have opened
  every dormant value on an empty window with a blank chronology under
  it — the lesson `ValueProfile.php:443` already records against taking
  the calendar month of the newest entry.
- **The longest-gap notice.** Its loop never commits a *trailing* run,
  because §16's wording is that a trailing gap "is not a gap either; it
  is the present". Written before there was a tail and exactly right
  once there is one.
- **The lanes, the ruler and the chronology.** All three read the
  window, and the window has not moved.
- **The year ticks**, except that the elided bin is passed over. Its
  `to` is the far end of a span that may cross several years, so a year
  written under a `⋯` would date the break to the year it ends in and
  leave the first bin drawn to scale again unlabelled.

### 27.7 Verified

Eleven values through the fragment, four through a real browser in both
themes and at two viewports.

| # | Check | Result |
|---|---|---|
| 1 | `php -l`, `node --check`, 80 columns over the diff | clean |
| 2 | **The cap holds** | tail share 33% on `143.14.244.37` and `45.178.180.13` (12 bins, 8 data), 2% on `193.161.193.99` (41 bins), 67% on the four one-bin values — the rule and then the floor |
| 3 | **Exactly one elided bin, and the axis stays contiguous** | 11 values, 0 bin overlaps, `elided` set on the tail's first bin and nowhere else |
| 4 | **A live value is untouched** | `443` 80 bins and `8.8.8.8` 23 bins, before and after, no tail and no note — their last entry falls in the bin that holds today |
| 5 | **The extreme case** | `yovtube.co`: 337 day-bins folded into one, a 3-bin axis reading *bar, break, today*, note *Quiet for 11 months*. It drew a single full-width bar before, indistinguishable from a value seen this morning |
| 6 | **Painted, not just computed** | band `233,236,239` against a `255,255,255` cut in light and `52,58,64` against `33,37,41` in dark, sampled off the canvas; band left edge at 970px of a 1,417px plot = the 33% the server said |
| 7 | **The break survives a narrow plot** | 992px viewport: band, break, `today` and all twelve tick labels still drawn |
| 8 | **Brushing the tail** | `143.14.244.37` dragged across the whole empty stretch → window `2026-07-04 → 2026-09-05`, *0 entries*, every lane 0, `Reset window` offered, no console error |
| 9 | **The claim has a floor** | `193.161.193.99`, quiet four days at week grain, draws the band and no sentence |
| 10 | Console | no page error and no console error on any run |

### 27.8 One thing found on the way, and deliberately left alone

**The spine's tooltip is unreachable, and has been since the brush
shipped.** `.vp-brush` covers the plot with `pointer-events: auto` —
`elementFromPoint` over the middle of the chart returns the brush, never
the canvas — so `tooltip.callbacks.title` has never fired on this chart.
The elided bin's title is therefore written and not readable.

It is left as it is here. Forwarding hover through the overlay to the
chart is a change to the brush rather than to this section, and the one
thing the tooltip would have said that a reader cannot get elsewhere —
the span the break stands for — is in the sentence under the chart for
exactly that reason. The title stays on the bin, correct and waiting.

---

## 28. A date about a value lives in three places; the tab drew one

From the reader of the built tab: *I guess you consider FS/LS on the
attribute? But what about the one set on the object containing it? And
what if the object contains another attribute with FS/LS. I think these
should be represented on a lane.*

All three exist in the schema. The seen lane drew the first of them, and
the answer to *why not the other two* turned out to be **nothing** —
one was a column already joined into a query this tab runs, and the
other was a lane §15 had deferred under a name (*passive-dns*) narrow
enough to hide how general it was.

### 28.1 The three levels, and what MISP does with them

| # | Where | What it is |
|---|---|---|
| 1 | `attributes.first_seen` / `last_seen` | MISP's own columns, on the occurrence itself |
| 2 | `objects.first_seen` / `last_seen` | the same two columns, on the object holding it |
| 3 | a `datetime` attribute inside that object | a date the *template* chose to record — `time_first`, `send-date` |

(1) and (2) are the same claim at two granularities, and MISP treats the
second as the first's default: `MispObject::saveObject` copies the
object's span onto every attribute saved without one. But **only on the
add path.** `deltaMerge` calls `syncObjectAndAttributeSeen` with
`$applyOnAttribute = false`, so editing an object's span never reaches
its attributes, and the two drift from there.

(3) is a different kind of evidence and not a third granularity of the
same one — see §28.5.

### 28.2 The census, and why (2) was worth having

Counted on the verification instance:

| | |
|---|---|
| objects | 69,992 |
| …carrying `first_seen` | 319 |
| …carrying `last_seen` | 75 |
| of the 319, objects **no member attribute** carries a `first_seen` for | **280** |
| …fully copied down | 36 |
| …copied in part | 3 |
| objects where the object's date and an attribute's **disagree** | **1** |
| attributes carrying a span inside an object carrying none | 168 |

Two readings, and the phase took both.

**The drift is nearly the whole population.** 280 of 319 is seven-eighths
of the object-level spans on the instance sitting in a column the tab
could not see, one join from a table it already joins for the ACL. A
value whose only recorded span was its object's drew on this axis as a
value with no span at all.

**Where both are set they agree**, 35 times out of 36. So this is a
de-duplication rule and not a preference between two rival dates: an
object earns a bar only from an occurrence that carries no span of its
own, which skips the 36 and catches the 280 and the 3.

### 28.3 The seen lane takes the object's span, and says so

`seen_object` is its own source rather than more rows in `seen`, and the
reason is that the key, the spine and the filter all key on source: a
reader can press the object-level spans away, the stacked spine draws
them apart, and the lane's sub-label counts them separately —
*0 of 1 occurrences carry one · 1 more dated by its object*.

The claim is weaker than the attribute's and every mark says so: *The
object carries this date, the occurrence carries none. A claim about the
object this value sits in.*

`Value::occurrenceIdsFor` was already joining `Object` — `buildConditions`
names `Object.*`, so the ACL cannot be expressed without it — and was
selecting nothing from it. That is the trap `occurrencesFor`'s own
docblock records one screen down: **with an explicit `fields` list on the
attribute, Containable takes nothing from a `belongsTo` unless told**, so
a bare `contain` joins the table, satisfies the ACL, and hands back rows
with no `Object` columns on them. Naming three fields is the whole of the
data change; there is no new query.

### 28.4 What it does to `first_here`

`168.181.48.248` is the case that shows why this is not cosmetic. Its
only occurrence carries no span; the `passive-dns` object holding it
carries **2017-04-14 → 2017-04-14**, and its every other dated trace is
October 2025.

Before: the axis began in October 2025 and §22.3's line read *on this
instance by* that date. After: the axis begins in **April 2017**, the
line reads **2017-04-14**, and the eight-year emptiness between them is
drawn as the empty band §19 built for exactly this. The tab was
understating how long the instance had held the value by eight years,
and the correction came out of a column it was already fetching the row
of.

### 28.5 The object-date lane, and why it is not part of Seen

§15 deferred this as *the passive-dns lane* (D8), costed at 829
passive-dns objects and 665 carrying both `time_first` and `time_last`.
That framing was too narrow by two orders of magnitude: **51,994 of the
instance's 69,992 objects hold at least one `datetime` attribute.**
`passive-dns` is one template among many that date themselves.

And the vocabulary is not one notion:

| relation | rows | relation | rows |
|---|---|---|---|
| `time_generated` | 32,892 | `compilation-timestamp` | 136 |
| `first-seen` | 11,318 | `published` | 61 |
| `last-seen` | 11,193 | `modified` | 60 |
| `last-submission` | 6,744 | `creation-date` | 48 |
| `time_first` | 665 | `send-date` | 20 |
| `time_last` | 665 | `expiration-date` | 11 |

**A compilation timestamp folded into a lane called *Seen* would be the
panel asserting something nobody recorded.** So it is its own lane, every
mark carries the relation that named it, and the lane's sub-label prints
the vocabulary this value actually has — *2 dates · time_first,
time_last* — so a reader knows which kind of lane they are looking at
before reading a single mark.

**Paired where the template pairs them.** `TIMELINE_DATE_PAIRS` holds
three: `time_first`/`time_last`, `first-seen`/`last-seen`,
`validity-not-before`/`validity-not-after`. A pair draws one bar,
everything else an instant. No fourth pair is guessed: `last-submission`
in particular reads like the far end of a window MISP does not record the
near end of, and drawing it as one would invent the near end.

The lane is deliberately not a second rendering of Relationships' *Dated
relations*. That fold needs **two** dates plus a linking value in the
same object, because what it dates is a relation between two values.
This dates the object, so one row is enough and no far value is needed —
which is why `168.181.48.248` gets a bar here and a row there from the
same two attributes.

### 28.6 What it costs, measured

Counts from a grouped aggregate, rows from a capped read — §16.1's rule,
and this lane is the second reader that needs it as much as the edit lane
does. Both reads are their own queries, so this is the one part of §28
that is not free:

| value | objects in scope | aggregate | rows read | `datetime` rows |
|---|---|---|---|---|
| `8.8.8.8` | 15 | 2 ms | 2 ms | 11 |
| `143.14.244.37` | 32 | 2 ms | 2 ms | 32 |
| `443` | 394 | 14 ms | 16 ms | 14 |
| `0.0.0.0` | 32,922 | **415 ms** | **400 ms** | **32,893** |

30 ms on `443` and 4 ms on the two mid-sized values; 815 ms on the value
that sits in 32,922 objects, which is the value every cost table in this
document is bounded by. `TIMELINE_OBJECT_DATE_CAP` is 1,000 — the
chronology's own cap, since a lane handing up more would be building rows
for `timelineCap` to discard.

**One count was dropped rather than qualified.** The first cut printed
*32,893 dates in 1,000 objects* on `0.0.0.0`, where the 1,000 was the cap
counting itself — the aggregate groups by day and relation and cannot
yield a distinct-object total without a third query. The lane now states
the date count and the vocabulary, both from the aggregate, and no object
count at all. §16.1 met by removing a number, which is the cheaper of the
two ways to meet it.

### 28.7 The colour that says this palette is full

Both new sources needed one, and the axis already carried twelve.

`seen_object` did not need a new hue: it is the same notion as `seen` one
level up, so it is the same hue at a different lightness —
`color-mix(in srgb, var(--vp-tl-seen) 55%, var(--bs-body-color))`, which
is one declaration that steps away from the ground in whichever direction
the theme leaves room for. A reader who has to learn a new hue to be told
*the same date, recorded on the object* has been told the wrong thing.

`objdate` did. It was picked the way `--vp-tl-proposal` was — a sweep of
hue × chroma × lightness scored on the smallest CIEDE2000 distance to
every source already on the axis, over normal, protan, deutan and tritan
vision, in both themes, filtered to ≥3:1 on both grounds. The winner,
`#876a1d`, scores **7.6 ΔE** (nearest: the tag's `#DB6A47` under
protanopia) at 5.11:1 on the light ground.

**That is below the 10.2 the proposal colour cleared, and it is the
finding.** Nothing in the space does better with fourteen sources on the
axis; the best both-ground candidate overall was a red at 8.4, which this
palette cannot spend on a lane about dates. Thirteen is what this
encoding holds comfortably. **A fifteenth source should change the
encoding rather than add a hue** — the lane grid already separates by
row, and only the stacked spine needs colour to tell sources apart.

`#876a1d` clears only 3.02:1 on the dark ground, which is the threshold
and not a margin for a 5px mark, so it takes a dark-theme override like
the three before it: `#d9cda6`, **10.7 ΔE** and 9.7:1 — better separated
there than the light value is on its own ground, because the warm band
empties once the palette lightens.

### 28.8 Two bugs in MISP's own propagation, found on the way

Neither is this page's and neither is fixed here; both are why §28.2's
drift is as wide as it is.

**`MispObject.php:1146` and `:1155` are dead branches.** The test reads
`!array_key_exists('first_seen', $object['Object']) && !is_null($object['Object']['first_seen'])`
— if the key is absent the second half is false, and if it is present the
first half is. Always false. So adding a single new attribute to an
existing object never inherits the object's dates. Line 523 has the same
test written correctly, which is what makes it a typo rather than a
design.

**`syncObjectAndAttributeSeen:1002` uses `elseif`.** When both dates are
forced, only `first_seen` reaches the attributes and `last_seen` is
silently dropped.

### 28.9 Verified

Over the values the phase has used throughout, plus two chosen for the
new branches, in both themes, as a site admin:

- **`168.181.48.248`** — the object-span fallback and a paired
  object-date span from one `passive-dns` object. Seen lane reads *0 of 1
  occurrences carry one · 1 more dated by its object*; Object dates reads
  *2 dates · time_first, time_last*; the axis runs from April 2017 and
  §22.3's line reads *on this instance by 2017-04-14*, both of which
  said October 2025 before.
- **`80`** — the mixed case, *24 of 32296 occurrences carry one · 8 more
  dated by their objects (8)*.
- **`0.0.0.0`** — the cap: *32,893 dates · time_generated,
  compilation-timestamp · drawing 1,000*, over a 32,922-object scope.
- **`8.8.8.8`, `143.14.244.37`, `443`, `2.2.2.2`, `45.155.205.233`,
  `193.161.193.99`** — no regression; every endpoint 200, no notice or
  warning in any fragment.
- The five panels sharing `Value::occurrenceIdsFor` — `viewSightingChart`,
  `viewVerdictCard`, `viewOccurrenceTable`, `viewLifecycle`,
  `viewTimeline` — all still 200 after its `contain` was narrowed from a
  bare `['Event', 'Object']` to named fields. This was the change most
  able to break something silently, since the ACL is expressed in columns
  the field list does not name; the join survives, which is what the
  query erroring rather than under-filtering would have shown.
- Both themes, on the lane grid, the spine and the key.

**Not measured against the phase's own cost table**, and the reason is the box:
it carried a load average of
3.0 throughout, and `443`'s endpoint read 5.8 s against §27's recorded
3.7 s *before* any of this section was in the request path. The two new
reads were therefore timed in isolation, in-process, which is §28.6 —
30 ms of the 5.8 s. Re-run the endpoint table on a quiet box before
quoting a whole-endpoint number for this phase again.

---

## 29. Every entry opens the record behind it

From the reader: *one thing missing is the ability to reach the content
by clicking on some value. For example the objects or attributes that
created these entries.*

The panel had **no link in it at all** — not one `href` in 2,900 lines.
Every other panel on this page links its rows; this one printed
`attribute 266583` as text and left the reader to find it.

### 29.1 Where a link can go, and where it cannot

To `/events/view2/<event>` with a tab anchor, which is the rule
`value_relation_asserted` §60 and the sightings table already settled and
wrote down: **this theme's event view takes no `focus:` parameter, and
`/attributes/view` and `/objects/view` redirect to the event and lose
which record they were asked about.** So the anchor is as close as a link
can get, and the link's `title` carries the record the anchor cannot:
*Open event 1416 — attribute 266583 is on its Attributes tab*.

Four destinations, from the event view's own tab ids:

| `ref['kind']` | opens |
|---|---|
| `attribute` | `#tab-attributes` |
| `object` | `#tab-objects` |
| `report` | `#tab-reports` |
| `event` | the event, no anchor |

### 29.2 The kind is per entry, not per lane

`ref['kind']` is set by the facade at all ten places an entry is built,
and it has to be, because **two lanes hold rows of more than one kind**:

- the **tag lane** draws `audit_logs` rows whose `model` is `Attribute`,
  `Object` or `Event` — a tag attached to an object and one attached to
  an attribute sit side by side and open different tabs. The kind is
  `strtolower($row['model'])`, taken from the row rather than from the
  lane it was filed in: what the reader is being sent to is the thing
  that was tagged, not the lane the tagging was drawn in.
- the **proposals lane** has the standalone-addition case
  (`value-profile-coverage.md` §2.2) — a proposal against no attribute,
  which now sends the reader to the event's proposal list rather than to
  a record that does not exist.

The two lanes §28 added take the kinds the sources mean: `seen_object`
and `objdate` are both `object`. For `objdate` that is deliberate even
though the row was read off a `datetime` *attribute* — the row's subject
is *what the object records*, and the object tab is where that field can
be seen beside the rest of its template.

**One key changed meaning.** `ref['event']` was `null` on an analyst note
about an attribute — true of the target, and useless to the only consumer
the key has ever had. It is now the event either way, which is what a
link needs and what nothing else read.

### 29.3 Two links per entry, one of them rebuilt in the browser

The chronology row's **title** is the link, not the whole row: the row is
a grid whose other parts include a source chip that already means *press
to filter*, and two gestures in one box — one of which navigates away —
is how a reader loses the window they brushed.

The **lane span labels** are the second, and they are the ones the reader
asked about most directly: the attribute id floating over a bar in the
Seen and Object dates lanes now opens it. Those labels are rebuilt by
`value-profile.js` on every window change, so the URL travels on the row
as `data-vp-tl-href` and the script creates an `<a>` or a `<span>`
depending on whether there is one — the same choice the server makes, so
a label does not become a dead link the first time the brush moves.
`.vp-lane-tag` carries `pointer-events: none` so a label cannot swallow
the hover belonging to the marks beneath it; the anchor form turns it
back on, which it can afford because the label sits in the 12 units above
the bars that `.vp-lane-peak` was given for the same reason.

The SVG marks themselves are **not** links. Making a `<rect>` navigable
is a change to the mark renderer and to the brush that covers it, and the
row it stands for is one glance below with the same destination.

### 29.4 The cue, which the first cut did not have

Colour-inherited and nothing else, which is this page's idiom for a link
inside a cell — and it was wrong here. 74 links in one list, none of them
announcing itself: the affordance existed and only a hover found it.

Link-blue on every title is worse: the chronology becomes a list of
links, and the source chips that are its actual vocabulary sink under
them. What ships is a dotted rule at 35% of the text colour, going solid
and link-coloured on hover and focus — the smallest mark that says *this
opens*. The panel's own line says it too, which costs four words:
*Newest first. Click a source in the key or a lane above to narrow to it,
**or an entry to open the record behind it**.*

### 29.5 Verified

- `104.207.76.157` — **74 row links and 2 lane-label links**, the labels
  reading `/events/view2/4169#tab-objects` for the Object dates lane.
- After pressing a lane to force `value-profile.js` to repaint: **2
  anchors, 0 plain spans**. The rebuild produces the same element the
  server did.
- Followed a lane label in the browser: lands on `/events/view2/4169`,
  and `#tab-objects` exists on the page it lands on. The anchor is not
  aspirational.
- `168.181.48.248` — every source on the value linked, and the anchors
  right per kind: 6 × `#tab-objects`, 2 × `#tab-attributes`, the rest
  bare events.
- Both themes, on the chronology and the lane grid; no endpoint slower
  and none over 200.
