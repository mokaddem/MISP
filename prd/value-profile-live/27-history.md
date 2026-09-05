# PRD: Value Profile — History goes live

**Phase 27**, the sixth live phase and the last unblocked tab. Converts
`viewHistory` — one endpoint, one element, one panel — from
`ValueProfileFixture` to the database. Depends on
[`00-contract.md`](00-contract.md) §14 and, more than any phase before
it, on the one immediately preceding: phase 25 built the audit reader
**in this tab's shape and said so**, so most of §4 is wiring rather than
design.

The tab's fixture-era design is [`07-history.md`](../value-profile-tabs/07-history.md)
(phase 16, built 2026-08-26) as amended by
[`19-history-scale.md`](../value-profile-phases/19-history-scale.md)
(phase 19, the occurrence-scale rebuild). Those two documents are the
specification this phase converts, and **§3 is where three of their
load-bearing assumptions meet the instance and lose.**

**Opened 2026-09-05.** §1 is the task board, §1.1 the decisions taken
before building and §1.2 what a session picking this up cold needs to
know. §3 is the instance survey, §8 a disclosure phase 25 shipped and
this phase has to fix before it renders it twice as loudly, and §12 the
verification plan.

---

## 1. The task board

Every row is `todo` until its own section says otherwise, and a row moves
to `done` only when §12's verification has run against it.

| # | Task | Section | Status |
|---|---|---|---|
| T1 | `ValueProfile::forHistory` — the facade method, and `viewHistory` onto `renderLivePanel` | §4 | `todo` |
| T2 | The window pushed into the scope conditions, not filtered in PHP | §5.2 | `todo` |
| T3 | Sections built from the entries returned, not from the occurrence list | §6 | `todo` |
| T4 | The corpus totals, the chart and the span from `auditCountsFor` | §7 | `todo` |
| T5 | `silent` retired; `outside` computed or dropped with its reason | §7.2 | `todo` |
| T6 | The actor redaction `eventIndex` applies and phase 25 did not | §8 | `todo` |
| T7 | The same redaction back-applied to the Timeline's chronology | §8.3 | `todo` |
| T8 | The diff rendered from the row already read — `fullChange` not called | §9 | `todo` |
| T9 | §14.6's two standing History rows: the footer graft and the suppressed state | §10.1 | `todo` |
| T10 | The ACL band under the header — kept, reworded, or withdrawn | §10.2 | `todo` |
| T11 | The action vocabulary widened to what the instance actually writes | §11.2 | `todo` |
| T12 | Proposals: the `ShadowAttribute` scope, or the facet row withdrawn | §13.1 | `todo` |
| T13 | Event reports as history — the coverage survey's third concept | §13.2 | `todo` |
| T14 | Feeds: `no`, argued | §13.3 | `todo` |
| T15 | The board rows — §14.12's `viewHistory` cell and §14.13's phase row | §12.4 | `todo` |
| T16 | The tab badge, which the registry already declines to render | §11.3 | `todo` |

---

### 1.1 The decisions, taken before building

Seven, and the first three are all consequences of §3.

**D1 — `silent` is retired rather than computed.** Phase 19's decision 2
made *an occurrence with no entries gets no section* the server-side
reduction that justified the phase: 748 sections become ~190. §3.1
measures that reduction on the database and finds it returns **nothing**
— every occurrence of every value tested carries at least an `add` row,
because `AuditLogBehavior` writes one when the attribute is created.
A count that is structurally zero is not worth a query, and a panel
stating *and 0 occurrences have never been touched* is worse than
silence.

**D2 — the window is the only bound, and it is therefore pushed into
SQL.** With D1 gone, nothing else bounds this panel. Phase 19 already
made the window the bound (its decision 8, "the actual fix"); what it
did not have to face is that the fixture filters in PHP over an array it
authored. Live, `443` is 162,539 attribute rows and the default window
holds **8** of them, so reading the lot to discard 162,531 is the
difference between a panel and an outage. §5.2.

**D3 — sections are built from the entries returned, never from the
occurrence list.** The inversion follows from D2. The fixture walks
`$spec['occurrences']` and looks up each one's entries; live, the window
returns entries and the occurrences they name are a small set to be
fetched afterwards. Walking 48,255 occurrences to find the 8 with a row
in the window is the same read D2 exists to avoid, wearing a loop. §6.

**D4 — the row read pays for `change`, and the diff never calls
`fullChange`.** `07-history.md` §10 nominated `AuditLogsController::fullChange`
as the live diff source. It cannot be: it applies `__applyAuditAcl`, which
restricts a non-site-admin to their own rows, and **ADMIN wrote
9,325,454 of this instance's 9,512,515 audit rows** — so the disclosure
would 404 on a row the page had just rendered. Phase 25 already built
the `change` option into `auditRowsFor` and named this tab as the caller
that would want it. §9.

**D5 — the actor is redacted the way `eventIndex` redacts it.** Not a
design preference: it is the subset claim phase 25's reader rests on,
applied to the row's *contents* as well as to its membership. §8.

**D6 — the default window stays 30 days.** §3.2 shows that lands most
values on the empty-window state, and the temptation is to widen it
until the tab looks populated. That would be choosing the number to
flatter the panel. The window is a claim about what *recent* means, the
empty-window state was built for exactly this and already names what
lies outside, and phase 19's decision 6 wrote the chart precisely so
activity outside the window is visible rather than inferred. What
changes is the **expectation**: the state is the common landing, not the
edge case, and §11 records that so nobody reads it as a bug.

**D7 — one endpoint, unchanged.** The rail and the rows arrive in one
container because the facet control walks up to the nearest
`data-vp-list` region, and the period stays in the path because it is the
panel's identity rather than a filter over it. Both arguments are already
in `ValuesController::viewHistory`'s docblock and neither is touched by
reading the database.

---

### 1.2 What a session picking this up cold needs to know

**The reader already exists.** `ValueProfile::auditRowsFor`,
`auditCountsFor`, `auditScopeQueries` and `auditRow` were written by
phase 25 and `auditRow`'s docblock says what they were written for:
*"The shape is the History tab's, which the fixture's `auditRow()`
already writes and its panel already renders — so that tab goes live
against a reader it inherits rather than one it negotiates with."* This
phase should be adding a facade method and a window, not a reader.

**The panel already exists and is verified.** `value_history.ctp` is a
built, browser-tested template with five states, a brush, per-section
paging and a facet rail. Phase 16 and phase 19 between them ran 37
structural and 40 driven assertions over it in both themes. **Feed it;
do not redraw it.** Every change to it in this phase should be traceable
to a row in §1's board.

**The scope is settled and is not `__applyAuditAcl`.**
`25-timeline.md` §5 closed `value-profile-page.md` §8.2's open choice
with a third answer: id-scoping across `Attribute`, `Object` and `Event`,
each `model_id IN` a set an accessor has already run
`buildConditions($user)` over. §8.2 now carries a pointer to it. Do not
re-derive it, and in particular do not reach for `event_id` — that column
is indexed and wrong, and the substitution measured 129 ms against
14,330 ms.

**Three of the design's assumptions are false on real data**, and they
are false in ways that make the tab quieter rather than louder: no
occurrence is silent, the default window is nearly always empty, and the
log is made of tags and adds where the tab is designed around edits. §3.
None of the three is a defect in the design — each was true of the
fixture it was written against.

**Where.** The corpus and the code are both in the
`attribute-value-page-brief` worktree, branch
`worktree-attribute-value-page-brief`. Three other worktrees carry copies
of `prd/` that lag this one; check `git log -1 -- prd/` before believing
any of them.

**Naming.** Every artifact this phase adds is prefixed `27-`.

---

## 2. Why this tab, and what it inherits

`25-timeline.md` §2 argued the order in advance and this phase is the
other half of it: *"the edit lane, once the audit log is on, is the same
union `07-history.md` assembles. The two tabs should read the same rows;
whichever goes live second reuses the first's scoping… History is the
larger consumer of that reader and the smaller contributor to it."*

What that bought, concretely — four things this phase does not have to
build:

| Inherited | From | What it would otherwise cost |
|---|---|---|
| The ACL model | `25-timeline.md` §5 | The decision `07-history.md` §12 listed as its own largest deferral, plus the measurement that rejected both of §8.2's candidates |
| `auditRowsFor` / `auditRead` | `ValueProfile.php:8977` | A three-scope reader, the merge-and-cap argument, and the `change` opt-in already written for this caller |
| `auditCountsFor` | `ValueProfile.php:9138` | The corpus total, the per-day fold the chart needs, and the span — one aggregate, already grouped by day |
| `AuditActionMeta::group()` | `25-timeline.md` §22.2 | The shared action vocabulary, built there explicitly *"so the History tab can read the same one"* |

It also inherits one thing it would rather not, and §8 is that.

---

## 3. Three facts about this instance the design does not have

Measured 2026-09-05 against the dev instance: **9,512,515 audit rows**,
`2024-11-11 08:58:11` to `2026-09-03 07:59:14`, over 3,915,429
attributes.

### 3.1 No occurrence is silent — phase 19's reduction returns nothing

Phase 19 exists because `45.155.205.233` rendered **748 sections in a
2.4 MB fragment from three audit entries**, and its decision 2 was the
server-side half of the fix: an occurrence with no entries gets no
section, so 748 sections become the ~190 that were actually touched.

On the database that reduction is **inert**. Every occurrence carries at
least one row:

| Value | Occurrences | Attribute-scope rows | Occurrences touched |
|---|---|---|---|
| `443` | 48,255 | 162,539 | **48,255** |
| `193.161.193.99` | 337 | 1,007 | **337** |
| `8.8.8.8` | 26 | 54 | **26** |
| `2.2.2.2` | 13 | 18 | **13** |
| `google.com` | 9 | 11 | **9** |

And not only for these five. Taking the **oldest 20,000 attributes on
the instance and the newest 20,000**, every one of the 40,000 has an
`Attribute`-scope audit row. The mechanism is not subtle:
`AuditLogBehavior` writes an `add` row when an attribute is created, so
an occurrence with no history is an occurrence that predates the log —
and `MISP.log_new_audit` was on here before the bulk of the ingestion.
`193.161.193.99`'s entire history is **337 `add` rows and 670 `tag`
rows**: one add per occurrence, and nothing else ever happened.

**What this does and does not mean.** It does not mean phase 19 was
wrong — the fixture it was written against authored entries for some
occurrences and not others, which is a reasonable shape to invent and
not the shape MISP produces. It does not mean the count is
*structurally* impossible either: an instance that switched the audit
log on last month has silent occurrences by the million. It means the
reduction **cannot be relied on to bound this panel**, so D2 stands
alone, and that a `silent` count computed at real cost would read `0` on
every value anybody looks at here. §7.2 takes that to its conclusion.

### 3.2 The default window renders an almost-empty tab, and that is honest

Phase 19's decision 6 lands the brush on a fixed recent window, 30 days.
On the fixture that shows a populated tab, because the fixture gave
`45.155.205.233` "recent activity so it lands populated". On the
instance, `8.8.8.8` — the value with the *richest* audit history of the
demo set — lands like this:

| | Whole log | Default 30-day window |
|---|---|---|
| Attribute-scope rows | 54 | **4** |
| Event-scope rows | 299 | **4** |
| Sections | 26 | **4** |

**Eight rows of 353, and 2% of the log.** `193.161.193.99` is worse: 6
rows across 2 sections, out of 1,007 rows across 337 occurrences. And
`443`, the heaviest value on the instance, is the same story at a
hundred times the size — **8 rows across 8 sections, out of 162,539 rows
across 48,255 occurrences.** The window does not narrow this tab so much
as decide it.

The instance-wide shape explains it. Audit activity is not a trickle, it
is a series of import bursts:

| Month | `Attribute` rows |
|---|---|
| 2025-11 | 4,311,355 |
| 2026-06 | 1,836,469 |
| 2025-12 | 1,180,251 |
| 2026-09 | 411,840 |
| **2026-08** | **8** |

A 30-day window over a log like that is a lottery, and widening it to 90
days would only move which month you gamble on. **D6 keeps the 30 days**
and reclassifies the empty-window state: `19-history-scale.md` §11.3
lists it as one of five states with `8.8.8.8` as its demo, which
undersells it. It is what most values render on landing, the chart above
it is drawn over the whole span precisely so the reader can see where
the activity actually is, and `show all time` is one click. The state
was built for this; what was wrong was expecting it to be rare.

### 3.3 The tab is designed around edits, and the log is tags and adds

`07-history.md` builds the row around a diff: `.vp-audit-diff` is one of
its four new primitives, the row carries a disclosure for the
field/was/is table, and `H4` was rejected only because unpacking *every*
row's diff at render time was too expensive. Instance-wide:

| Action | Rows | Share |
|---|---|---|
| `tag` | 5,132,220 | 54.0% |
| `add` | 4,200,523 | 44.2% |
| `soft_delete` | 122,795 | 1.3% |
| `edit` | **28,862** | **0.3%** |
| `delete` | 18,759 | 0.2% |
| `galaxy` | 8,182 | 0.1% |
| `publish` | **173** | 0.002% |

**Edits are three rows in a thousand, and publications are 173 rows in
9.5 million.** The event-level section — which `07-history.md` §1 made a
first-class part of the design, on the argument that publications belong
to the value's story and to no single occurrence — is a section built
for 173 rows instance-wide.

`8.8.8.8` is unrepresentative in the useful direction: 26 `add`, 12
`edit`, 9 `tag`, 3 `tag_local`, 3 `remove_local_tag`, 1 `remove_tag`.
It is the value to build against, and precisely because it is atypical
it must not be the only one verified. §12.2.

**No task falls out of this**, and that is deliberate — the design's
answer to a tag-heavy log is already correct, since a `tag` row *is* a
row with a subject and the mix bar already colours it. It is recorded
because the next reader of `07-history.md` will meet a document whose
centre of gravity is the diff, and should know the diff is the rare
case before deciding how much to invest in it.

---

## 4. The facade method

One method, and `viewHistory` moves onto the seam every other converted
panel uses.

```php
public function forHistory(array $user, $value, array $options = array())
```

`ValuesController::viewHistory` becomes
`renderLivePanel($b64value, 'forHistory', 'value_history', array('window' => …))`,
with `self::period($from, $to)` resolved exactly as it is today — the
literal `all` for the unbounded request, a from/to pair, or null for the
default. The action's docblock keeps both of its arguments, since
reading the database changes neither.

**The return contract is the fixture's, key for key**, because the panel
is built and verified against it. `value_history.ctp` reads twenty
top-level keys:

`recorded`, `window`, `default_window`, `span`, `chart`, `entries`,
`shown`, `occurrences`, `visible`, `silent`, `outside`, `events`,
`hidden`, `total_occurrences`, `viewer_events`, `other_events`,
`facets`, `vocab`, `groups`, `event_entries`.

Four of those do not survive this phase: `silent` (D1/§7.2), and
`hidden` / `total_occurrences` / the pair behind the ACL band (§10).
Every other key is produced live, and §7 says from which read.

**`recorded`** is `AuditLogBehavior::isEnabled()`, which reads
`MISP.log_new_audit` — the one key on this panel that is a fact about
the instance rather than about the value, and the one that decides
whether anything else runs at all. On a default instance it is `false`
and the whole panel is state 2.

---

## 5. The scope, and the window

### 5.1 The scope

Three id sets, from accessors that have already applied
`buildConditions($user)`:

```
attributes → Value::occurrenceIdsFor($user, $value)
objects    → Value::occurrenceObjectIdsFor($user, $value)
events     → the ACL'd event ids
```

which is exactly the array `auditRowsFor` and `auditCountsFor` already
take. Measured on `8.8.8.8`, the three scopes return **54 / 16 / 299**
rows — the event scope is five times the attribute scope, which is worth
knowing before assuming the sections are the expensive part. On
`193.161.193.99` it is 1,007 / — / 1,045; on `443`, 162,539 / — / 9,493.

### 5.2 The window belongs in the conditions — T2

`auditScopeQueries` builds `model` + `model_id IN (chunk)` and nothing
else, and `auditRowsFor` takes `limit`, `change` and `order`. There is no
date condition anywhere in the reader, because the Timeline never needed
one: its spine is the whole range by construction.

This tab does need one. Without it, the default window on `443` reads
162,539 rows to render the handful inside 30 days. The change is a
`created >=` / `created <` pair added to each scope's conditions when a
window is given, and it is additive — a caller that passes no window
gets the reader it has today, which keeps phase 25's four panels
byte-identical.

**Whole days on both ends**, matching `ValueProfileFixture::auditInWindow`'s
own note: a window given to the minute drops entries from the day the
reader named, which reads as the log having lost them. So `to` becomes
`< (to + 1 day)` rather than `<= to`.

**The cap stays, and it is a second bound rather than a replacement.** A
window is a date, not a count, and a value can have a bad day — `443`
took 411,840 rows in 2026-09 alone. `auditRowsFor`'s `limit` already
does the right thing across three scopes (each read newest-first under
the same cap, merged, re-sliced), and the header already renders
`Showing <filtered> of <all>`, so a capped read has somewhere honest to
say so.

---

## 6. Sections are built from entries, not from occurrences — T3

The fixture's `history()` opens `foreach ($occurrences as $occurrence)`
and looks up `$authored[$id]`. That is the right shape when the
occurrence list is six long and the entries are an array you wrote.

Live it inverts:

1. `auditRowsFor` returns the windowed rows, newest first.
2. Bucket them by `attribute_id` — the rows already carry it, because
   `auditRow` sets it for `model = 'Attribute'`.
3. Fetch **only the occurrences those buckets name** for the section
   headers: event id, event info, creating org, the `deleted` badge.
4. Rows whose `model` is `Event` are the event-level section, exactly as
   today.

Step 3 is the one that matters. `Value::occurrencesFor` contains `Event`,
`Object`, `SharingGroup` and `AttributeTag` and takes no cap unless one
is passed — running it over `443`'s 48,255 occurrences to decorate four
sections is the read D2 exists to prevent, arrived at from the other
direction. The set to fetch is `array_unique` over the buckets' keys,
which on every measurement in §3.2 is single digits.

**`groups[]['total']`** — the occurrence's whole-log count, which the
section header states beside the window's so the reader can see they are
looking at three of eleven changes — is the one number this inversion
cannot produce. §7.2.

---

## 7. What the aggregate gives, and what it does not

### 7.1 Free from `auditCountsFor`

One aggregate over the same three scopes, grouped by day and action,
already returns everything the panel needs *about the corpus* — which is
to say, everything the window is not allowed to change:

| Key | From |
|---|---|
| `entries` (the `of all` total) | `counts['total']` |
| `chart` | `counts['by_day']`, folded through `ValueProfileBuckets::plan` / `tally` exactly as the fixture folds its own day map |
| `span` | `counts['first']` / `counts['last']` |

This is the split `25-timeline.md` §6 made for its own reasons — counts
from an aggregate, rows from a read — reused wholesale. It is also why
the chart can span two years while the panel renders eight rows: they
come from different queries by design, not by accident.

### 7.2 `silent` and `outside` — T5

Both are per-occurrence facts, and `auditCountsFor` groups by day and
action rather than by `model_id`, so neither is free. A third aggregate
grouped by `model_id` would answer both — and on `443` it returns 48,255
groups. `LIMIT` bounds the rows returned and not the rows read, so a
capped grouped read has no distinct count to report — it cannot say how
many occurrences it did not reach.

**`silent` is dropped.** §3.1 measured it at zero on every value tested
and gave the mechanism for why it is structurally zero on any instance
that logged from the start. The template's `silent` branch goes with it.

**`outside` is kept, and it is cheap.** It is *occurrences with entries,
none of them in the window* — which is `counts` over the whole log minus
the buckets the window produced, and the first half of that subtraction
is a `COUNT(DISTINCT model_id)` over the attribute scope with no
grouping and no window. One scalar, one query, and it is the number
phase 19's dropped-occurrence line exists to state.

**`groups[]['total']` follows the same rule**: it needs a per-occurrence
count and there is no bounded way to get one for 48,255 occurrences. It
*is* bounded for the occurrences the window named — a handful — so the
count is fetched for those and only those, in the same read as step 3's
metadata. An occurrence outside the window has no section and so no
header to state a total in.

---

## 8. The actor phase 25 disclosed, and this tab would disclose louder — T6, T7

This is the finding this phase would rather not have made, and it is not
about History.

### 8.1 What MISP does

`AuditLogsController::eventIndex` — the per-event audit log, the page a
reader already has — paginates its rows and then, before rendering:

```php
if (!$this->_isSiteAdmin()) {
    // Remove all user info about users from different org
    …
    if (!in_array($item['User']['id'], $orgUserIds)) {
        unset($list[$k]['User']);
        unset($list[$k]['AuditLog']['user_id']);
    }
}
```

A non-site-admin sees the actor's email only for actors **in their own
organisation**. Rows with `user_id = 0` — 186,929 of them here, the
system's own — are skipped and keep their (absent) actor. The redaction
is unconditional on the actor's org and has nothing to do with who owns
the event: it applies on the viewer's own events too.

### 8.2 What phase 25's reader does

`ValueProfile::auditRow` sets `'actor' => $row['User']['email']` with no
such test, and `timelineAuditLanes` renders it: `'note' =>
sprintf(__('audit_logs · %s'), $actor)`. So the Timeline's chronology
shows a plain analyst the email address of a user in another
organisation, on rows MISP's own audit index would have stripped.

**On this instance that is not hypothetical.** Three users in ADMIN
wrote 9,325,454 rows and two in CIRCL wrote 123. `8.8.8.8`'s 26
occurrences sit on events created by eight different organisations, and
**all 54 of its attribute-scope rows carry `org_id` ADMIN** — the same
arithmetic fact `25-timeline.md` §5.2 used to reject the per-user model.
So the CIRCL org admin who opens `8.8.8.8` reads `admin@admin.test` on
every row the scope lets them see — CIRCL created three of the eight
organisations' events — where `eventIndex` would have shown them none on
any of them, their own three included.

Phase 25's subset argument is not wrong; it is narrower than it reads.
Its docblock claims *"for any viewer and any event this returns a subset
of what `__createEventIndexConditions` would hand them"* — and that is a
claim about **which rows**, which holds. `eventIndex`'s redaction is not
in `__createEventIndexConditions` at all; it is applied in the controller
after `paginate()`, so a reader that inherits the conditions inherits
none of it. The docblock even names the falsification test — *"a viewer
class for whom this returns more than that model would grant on the same
event reopens the decision"* — and a non-site-admin is that class.

### 8.3 What this phase does

**T6.** `auditRow` gains the same test: the actor survives when the
viewer is a site admin or the row's user is in the viewer's org, and is
otherwise null. `auditRow` already has the null path and the callers
already have the wording — *`<org> (unnamed)`* — chosen in phase 25 for
the deleted-account case, which is the same shape: the organisation is
what survives, and it is what the History tab's rail note already says it
files foreign-org actors under (`07-history.md` §8).

The test needs the viewer's org's user ids, which is one
`User.find('column')` — the query `eventIndex` runs — hoisted so the
three-scope merge pays it once.

**T7 is the part that is not this tab's**, and it is here because the
rule this campaign has followed since the badge corrections is that
whoever notices fixes it and records where. The Timeline ships the same
disclosure today. The fix is the same line in the same method, so T6 and
T7 are one change and two verifications; §12.2 runs the Timeline's
chronology as a CIRCL org admin as well.

**What it costs.** A non-site-admin's Actor facet group collapses to
their own colleagues plus one row per foreign organisation. That is a
real loss of resolution on a rail group `07-history.md` §8 designed with
four groups, and it is the loss MISP has already chosen everywhere else.

---

## 9. The diff comes from the row, not from `fullChange` — T8, D4

`07-history.md` §10 nominated the endpoint: *"live, this is where
`AuditLogsController::fullChange` is called, because `audit_logs.change`
is brotli-compressed above 256 bytes."*

`fullChange` cannot serve this panel. Its first line is
`$acl = $this->__applyAuditAcl($this->Auth->user())`, which for a
non-site-admin restricts to `AuditLog.user_id = $user['id']` and for an
org admin to `AuditLog.org_id = $user['org_id']`, and then throws
`NotFoundException` on an empty find. Against an instance where ADMIN
wrote 98% of the rows, that is a 404 on almost every diff the panel has
just rendered — the exact failure mode `25-timeline.md` §5.2 measured
when it rejected the per-user model for the rows themselves.

**The row already carries it.** `auditRowsFor`'s `change` option was
written for this caller and says so. Measured, the cost is small: on
`8.8.8.8`'s 54 attribute rows the blobs total **5,939 bytes**, average
110, maximum 240 — **none of them over the 256-byte compression
threshold**, so there is nothing to decompress. Instance-wide the average
is 126 bytes and **11.8%** are above the threshold; every row on the
instance has a non-null `change`, across all 9,192,832 `Attribute` rows,
so the disclosure never opens on nothing. `AuditLog::CHANGE_MAX_SIZE`
caps a blob at 64 KB and the largest here is 52,636 bytes, which is the
one row that would be worth a cap of its own if a window ever returned
many like it.

So the diff is server-rendered into the row's disclosure from data the
panel already has, there is no second request, and the ACL question does
not arise because the row passed the scope test to be there at all.

---

## 10. §14.6, and the band this tab has to decide about

### 10.1 The two standing rows — T9

§14.6's required-changes table has carried two History rows since it was
written, and both are still `todo` because this is the first phase in a
position to apply them:

| Location | Today | Under §14.6 |
|---|---|---|
| §8.7, History footer graft | *"four of the ten occurrences are ACL-hidden"* | **graft withdrawn** |
| §11 (phase 19) suppressed state | *"All %d occurrences … are on events you cannot see"* | **state withdrawn** |

Both are live in `value_history.ctp` today: the footer at `:1611` and the
band at `:375`, both keyed on `$history['hidden']`, and the suppressed
state at `:277` keyed on `$history['visible'] === 0` and stating
`$history['total_occurrences']`. All three go, and `hidden` and
`total_occurrences` leave the return contract with them — which is what
makes the withdrawal structural rather than cosmetic: a template cannot
re-grow a band whose data is not sent.

The suppressed state collapses into state 3, the empty state, per
§14.6's own note that this is the cost being paid deliberately: *"a panel
where everything is hidden now renders identically to a panel where
nothing exists."*

### 10.2 The ACL band under the header — T10, and an open call

`value_history.ctp:1105` renders a third band, and it is not in §14.6's
table because it is a different thing: *You see every entry on the N
events your organisation created, and only entries on occurrences you may
read on the other M.* It discloses no hidden count. It describes the
viewer's own position.

Three readings, and this phase has to pick one:

1. **Withdraw it.** §14.6's rule as sharpened by phases 23 and 26 is *a
   panel that renders a computed judgement gets a permanent caveat; a
   panel that renders a count does not*. History renders rows and counts.
   By the letter, no band.
2. **Keep it and make it permanent.** The exception exists because a
   reader has no other way to know why their numbers differ from a
   colleague's. This tab is the one place in MISP where a reader can most
   easily mistake a partial record for the whole record — `07-history.md`
   §8 says exactly that, and it costs one line.
3. **Keep it, reworded to drop `viewer_events` / `other_events`.** The
   sentence's force does not come from the two numbers; it comes from *a
   site admin sees more rows here than you do*. Dropping them makes it
   invariant across readers, which is the property §14.6 requires of a
   permanent line.

**The recommendation is 3**, and the argument for it is that it satisfies
both rules rather than choosing between them: the band becomes identical
for every reader, so its presence carries no information, and the
sentence a reader actually needs survives. It also removes two more keys
from the contract. Recorded as a call rather than taken, because §14.6's
exception was written as *the Verdict tab and nowhere else*, has twice
grown a member, and each time the phase that grew it argued the case in
its own document first.

---

## 11. States, the vocabulary, and the badge

### 11.1 The five states, live

| State | Renders | Live demo |
|---|---|---|
| no `history` key | the sparse page | a value with no occurrences |
| `recorded === false` | the log is off; what the page still knows | **flip `MISP.log_new_audit`** — §12.3 |
| recorded, no entries | the log runs, nothing for this value | **none found** — §3.1 says why |
| populated | sections | `8.8.8.8` with `show all time` |
| populated, window empty | what is outside, and `show all time` | `8.8.8.8`, `193.161.193.99` — **on landing**, §3.2 |

Two rows changed meaning. The empty-window state stops being the rare
one, and **the recorded-but-empty state becomes hard to demonstrate at
all**: it needs a value every occurrence of which predates the audit log,
which §3.1 could not find in 40,000 sampled attributes. If none exists,
it is displayed by flipping — capture it, revert, and write the flip
down, the way phase 19 §11.3 reached its own two unreachable states — and if
even that is not reachable, §12 records it as a state verified by
construction rather than by sight, which is the honest outcome and not a
gap to paper over.

### 11.2 The action vocabulary — T11

`ValueProfileFixture::auditVocab` lists ten actions. The instance writes
**fourteen**, and the five it does not list are not exotic:
`tag_local` (678 rows), `remove_local_tag` (129), `galaxy_local` (11),
`remove_local_galaxy` (1), plus `publish_sightings` (2). **`8.8.8.8`
carries six distinct actions and two of them are unlisted** — three
`tag_local` and three `remove_local_tag`, six of its 54 attribute-scope
rows. A ninth of the richest demo value's history arrives as an action
the rail was not told about.

`auditFacets` tallies an unlisted key rather than dropping it, so nothing
is lost; but the vocabulary is what orders the rail and what supplies the
zero rows phase 16 argued for (*undelete 0* tells the reader nothing was
ever undeleted). An action that arrives unlisted sorts to the end, after
the zeros. The vocabulary moves onto `AuditActionMeta`, which already
knows all sixteen constants and already groups them — the same
single-source move phase 25 made for the lane grouping, for the same
reason.

`undelete` is worth keeping as a zero row: the instance has no rows for
it, and that is a fact about the instance worth rendering rather than
hiding.

### 11.3 The badge — T16

Nothing to do, and it is the only tab that can say so. `07-history.md` §3
declined the count at design time — *"a number that means three things
and is usually zero is worse than no number"* — and the registry has
carried History with no `count` ever since. This phase is the fourth to
face the badge question (Occurrences took a real number, Sightings and
Relationships took none, Relationships later took a different one, Analyst
took none) and the first to find the answer already correct. **After this
phase the tab bar carries exactly three numbers**, unchanged from §1.4's
count today.

---

## 12. Verification — planned

### 12.1 The shape

`27-history-check.mjs`, modelled on `26a-analyst-check.mjs`: the panel
served from the worktree, driven in headless Chrome, both themes, with
the assertions below. Phase 16's and phase 19's own harnesses already
cover the interactions against the fixture; **what this phase adds is
that the same assertions hold over rows the database returned**, so the
existing structural checks are re-run rather than rewritten.

### 12.2 The values, and the readers

Five values, and the reader matters as much as the value:

| Value | Why |
|---|---|
| `8.8.8.8` | The richest audit history of the demo set: 54 + 299 + 16 rows, six action kinds, 26 sections. Lands on the empty-window state; `show all time` is the populated case |
| `193.161.193.99` | 337 sections and 204 events — the second scope dominating, and the value whose whole history is adds and tags |
| `443` | 162,539 rows and 48,255 occurrences. The value the window and the cap exist for, and the one to time |
| `2.2.2.2` | 18 rows over 13 occurrences — small, and already the value phase 23 used for a cross-reader ACL difference |
| `google.com` | 11 rows; the near-empty populated case |

**Two readers on every value**: `admin@admin.test` (site admin) and
`orgadmin@circl.lu` (CIRCL org admin). §8 is unverifiable with one, and
`25-timeline.md` §5.2's warning applies verbatim — *read as a site admin,
all three models look identical*.

### 12.3 What must be checked, not assumed

1. **The header total equals the corpus, not the window.** The aggregate
   and the read are different queries (§7.1); a value where they agree
   by coincidence proves nothing. `8.8.8.8` at the default window has 8
   rendered rows against 353 in the header.
2. **Section counts sum to the rendered rows**, and each section's mix
   sums to its own count — phase 16's consistency rule, re-run over live
   rows.
3. **The facet groups sum to the entry total** and every value a row
   carries has a facet row — the check that catches T11.
4. **The actor redaction**, both directions: as CIRCL, no row exposes an
   email outside CIRCL, and the rail's Actor group contains no foreign
   address; as site admin, the same rows do carry them. Run over the
   Timeline's chronology too (T7).
5. **The diff opens from the row** with no network request — the check
   that catches a `fullChange` regression.
6. **`recorded === false`** by flipping `MISP.log_new_audit`, capturing,
   and reverting. **Flip it as `www-data`**: `cake Admin setSetting` run
   as root rewrites `config.php` with root ownership, `www-data` can then
   no longer read it, and the instance 302-loops on every request until
   the ownership is restored.
7. **`443` timed**, cold and warm, at the default window and at
   `show all time`, with the query count taken the way §14.12's rows
   require — `$db->fullDebug` with `_queriesLogMax` lifted by reflection
   first, since `getLog` stops recording at 200 rows and the count
   otherwise plateaus silently.
8. **Both themes**, by computed style, over every node phase 16
   measured.

### 12.4 The board — T15

§14.12's `viewHistory` row and §14.13's phase row, filled with the same
numbers this document records. Note that the board currently carries
**three** endpoints reading the database with a blank `Q` —
`viewRelationReferences`, `viewRelationExternal` and `viewAnalystPreview`
— and this phase must not become the fourth.

---

## 13. The three concepts

`00-contract.md` §14.9 row 9 requires all three assessed.
`value-profile-coverage.md` §5 gives History a starting verdict of
**yes / no / yes**, and §5's own prose says what each means.

### 13.1 Proposals — T12

**Yes, and already half-claimed.** `07-history.md` §7's fixture carries a
`model` facet with `'ShadowAttribute' => 1`, so the tab already renders a
row asserting it counts proposal audit entries. `ShadowAttribute` does
have `AuditLog` in `$actsAs` (`ShadowAttribute.php:25`), so the claim is
supportable — and the instance has **37 `ShadowAttribute` audit rows**,
which is small but not zero.

The problem is that phase 25's reader explicitly excludes them: its
docblock lists *"`ShadowAttribute` and `ObjectReference` rows, which that
model includes for an event"* among what it drops, on the reasoning that
proposals reach the Timeline as their own dated lane. History has no such
lane. So either a fourth scope is added — `model = 'ShadowAttribute' AND
model_id IN` the value's proposal ids, which `25-timeline.md` §25.2 found
needs **two** scopes rather than one — or the facet row is withdrawn.
**Withdrawing a row the tab already renders is the worse of the two**,
because it is a visible claim being retracted rather than a feature not
built; the recommendation is the fourth scope, and its cost is one more
statement in `auditScopeQueries`.

### 13.2 Event reports — T13

**Yes.** `EventReport::$actsAs` includes `AuditLog` (`EventReport.php:14`)
and the instance carries **314 `EventReport` audit rows**, so report
creation and editing is a genuine history lane. Phase 26 built the report
*list* for the Collaboration tab and phase 25 built the report *lane* for
the Timeline; this is the third surface and the cheapest of the three,
because both of those already resolve which reports the viewer may see.
Same shape as §13.1: one more scope, over ids an accessor already
produces.

### 13.3 Feeds — T14

**No, and cleanly.** Feeds produce no audit rows against a value: caching
is a job, not a change to anything `AuditLogBehavior` is attached to.
The instance's 106 `Feed` audit rows are edits to feed *definitions*,
which are not this value's history under any reading. This is a `no` with
a reason, which §14.9 row 9 says is a complete answer.

---

## 14. Deferred, with the cost named

- **`H3`, the per-organisation grouping.** Still the best question of the
  four candidates and still unanswerable: §8's redaction is now applied
  by this page too, so three of its four cards collapse to organisations
  for a non-site-admin. Revisit as a site-admin-only view, where it is
  fully answerable — which is a stronger position than phase 16 left it
  in, because the reason is now a rule this page enforces rather than one
  it inherits.
- **`H4`, field-level unpacking.** Rejected on cost at design time. §9
  changes the arithmetic — the blobs are already in memory, average 126
  bytes — so the cost is now render-time and not fetch-time. Still
  deferred, but the reason has changed and the next phase to look should
  re-measure rather than re-read.
- **No pagination across the union.** `07-history.md` §12 parked this and
  said *"this is where that problem lands"*. It lands here, and it stays
  parked: each section pages its own rows and `show all time` pages in
  the browser. A server-side Paginator over a union of N scoped queries
  has no stable ordering key, which is the same finding phase 26 recorded
  for the analyst thread.
- **Converging `Logs/timeline.ctp` onto `AuditActionMeta`.** Phase 16
  left the shared element's inline `$meta` alone because rewriting a
  shared element with two live callers is not fixture-first work. It is
  not live-phase work either, and T11 makes `AuditActionMeta` the single
  source for this page's vocabulary without touching the other two
  callers. Recorded, still owed.
- **The 64 KB blob.** §9 measured one row at 52,636 bytes. A window that
  returned many like it would ship a fragment the size of the one phase
  19 was written to prevent. Not bounded in this phase; the cap on rows
  bounds it in practice, and a per-blob truncation with a *show full
  change* link is the fix if it is ever seen.
