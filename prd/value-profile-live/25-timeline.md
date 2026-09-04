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
| T1 | `ValueProfile::forTimeline` — the facade method, per-panel | §4 | todo |
| T2 | The audit reader on the decided ACL model, three model scopes | §5 | todo |
| T3 | The counts/rows split: one grouped aggregate, one capped read | §6 | todo |
| T4 | Sightings lane from phase 23's context, no second read | §11 | todo |
| T5 | Publications from one `fetchSimpleEvents`, epoch-0 excluded | §11 | todo |
| T6 | Analyst lane — the occurrence ∪ event union, rows labelled | §8 | todo |
| T7 | Seen-span lane, no merging, capped, remainder stated | §7 | todo |
| T8 | Edit lane on the audit rows, with the not-recorded branch kept | §5, §6 | todo |
| T9 | Undated strip: tags, galaxy clusters, feeds — matched by key | §11, §14.1 | todo |
| T10 | Proposals lane | §10 | todo |
| T11 | Event reports lane | §10 | todo |
| T12 | The spine's grain planned from the range, not pinned | §9 | todo |
| T13 | Remove the ACL band §14.6 forbids and §14.6's table missed | §12 | todo |
| T14 | The board rows: §14.12 `viewTimeline`, and this document's numbers | §14.12 | todo |

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
model = 'Event'     AND event_id IN (the ACL'd event ids)
```

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

`value_timeline.ctp` matches undated rows to lanes by their **translated
label** — `$undatedBy[__('Tags')]`, `$undatedBy[__('Feed appearances')]`.
The fixture supplies `kind` through the same `__()` call, so the two agree
in English and in any locale where both strings are translated
identically, and silently stop agreeing otherwise: the lane renders its
*absent* text while the strip below it lists the chips. The live facade
emits a stable key beside `kind`, and the template matches on the key.

---

## 12. §14.6 missed this tab, and the band it renders

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

**`T2` standalone and `T1`**, unchanged from `06-timeline.md` §12.
