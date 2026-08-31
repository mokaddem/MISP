# PRD: Value Profile — three concepts the campaign owes

Companion to [`value-profile-page.md`](value-profile-page.md). §14 of that
document is the contract for taking the nine tabs live as reads; this one names
three things the page has been designed *around* without ever being designed
*for*:

1. **Proposals on the value** — what other organisations have proposed about it.
2. **Presence in feeds and sync servers** — who outside this instance holds it.
3. **Event reports** written about the events that carry it.

None of the three is a defect and none is missing outright. Each is reachable
from a panel the inventory already lists, each was implemented to about a third
of its depth, and that is exactly how they stayed out of sight: a badge, a card
title and a markdown parser were enough to make them look handled.

This document does two things. §2–§4 give each concept the survey §7.9 and §8.2
gave theirs — what MISP supplies, what it cannot, and what that means for the
page. §5 turns the survey into a **per-phase obligation**: a table with one row
per remaining live phase and a verdict per concept, so that no phase converts a
tab without having looked. `live/00-contract.md` §14.9 gains a row pointing
here.

**Nothing here is built, and nothing here blocks a phase.** Nine of the
twenty-four verdicts in §5 are "not relevant, and here is why" — including all
three for Sightings — which is a finished assessment, not an omission. The point
is that the answer is recorded before the phase rather than discovered after it.

---

## 1. Why these three, and why now

The page's own inventory hints at all three and delivers about half of one.

| Concept | Where it surfaces today | What is actually there |
|---|---|---|
| Proposals | `value_occurrence_table` state facet and per-row badge; §1.4 records `O4`'s proposal diff as deferred | a **count**, and only for proposals against occurrences the viewer can already see. Nothing renders what was proposed |
| Feeds and servers | `value_external` on the Overview right rail — *"feeds holding the value, sync servers with a cache hit, SightingDB hit count"* | a fixture card and an unconverted endpoint. The primitive behind it exists, is ACL'd differently from the rest of the page, and disagrees with the event view about what a hit is |
| Event reports | one incidental mention, in `tabs/05-analyst.md` §11, that `markdown-it.js` ships for them | **nothing.** No panel, no endpoint, no row on §14.12's board |

The asymmetry is instructive. Proposals got a badge because the Occurrences
table is a table of attributes and proposals hang off attributes. External
presence got a card because §2.6 was written from the event view's right rail,
where a feed-hit column exists. Event reports got nothing because they hang off
*events*, and every panel on this page is anchored to a value or to an
occurrence — an event is a column, never a subject.

That is the shape of the gap: **the page reasons well about attributes and
poorly about the things attached to the events its attributes live in.**

One of the three is not merely implied but promised. §1.1 states the page
aggregates occurrences *"across events, organisations, sightings, opinions,
correlations, decay models and **feeds** into one view"* — so external presence
is in the purpose sentence, and §3 is the only place in the corpus that asks
what MISP will actually supply for it.

---

## 2. Proposals on the value

### 2.1 What MISP supplies, and it is more than the page uses

`shadow_attributes` is close to a mirror of `attributes`, and crucially it is
indexed the same way:

```
KEY value1 (value1(255)),
KEY value2 (value2(255)),
KEY old_id (old_id),
KEY event_id (event_id)
... ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin
```

Three consequences, all favourable:

- **A value → proposals lookup is a tier-1 indexed query.** Same prefix-index
  shape as §14.3 describes for `attributes`, same caveat and no worse.
- **The collation is `utf8mb3_bin`, identical to `attributes`.** Proposals share
  the page's value identity exactly. This is the only one of the three concepts
  for which that is true, and it is worth noticing before §3 and §4 spend their
  length on the two that don't.
- **`ShadowAttribute::buildConditions($user)`** (`ShadowAttribute.php:757`) is
  the ACL builder, and it composes the way §14.4 tier 1 wants.

A proposal carries `value1`/`value2`, `type`, `category`, `to_ids`, `comment`,
`first_seen`/`last_seen`, `timestamp`, `org_id` (the proposer),
`event_org_id` (whom it is proposed to), `deleted`, and `proposal_to_delete`.
So there are three kinds of proposal, not one:

| Kind | Discriminator | What it means |
|---|---|---|
| Proposed edit | `old_id != 0`, `proposal_to_delete = 0` | someone wants this occurrence changed — its `to_ids`, its type, its comment, its dates |
| Proposed deletion | `proposal_to_delete = 1` | someone wants this occurrence gone |
| Proposed addition | `old_id = 0` | someone wants **a new attribute** with this value, in an event that may not carry it yet |

`ShadowAttribute.php:972` and `:1008` are where the code states the `old_id`
discriminator in its own words.

### 2.2 The one case the page cannot reach, by construction

Phase 22's query 6 is *"`ShadowAttribute` grouped count over the row ids"*, and
`live/22-occurrences.md` is explicit that the id set comes from query 2's rows
rather than from a second resolution. That is the right call for a per-row
badge, and it has a consequence the phase did not have to face:

> **A proposed addition (`old_id = 0`) is invisible to the entire page.**

It has no `old_id` in the row set because it has no attribute at all. So:

- A value that exists on this instance **only as a proposal** renders as the
  sparse unknown page (§2.12). Not "0 occurrences, 2 proposals" — nothing.
- A value with three occurrences and a fourth proposed in a different event
  shows three rows and a rail that says three.

This also puts a hole in phase 22's summary of the ACL model. That document
says a proposal *"is visible to whoever can see the attribute it proposes
against"* — true for `old_id != 0`, and `buildConditions()` itself shows the
other branch: `'ShadowAttribute.old_id' => '0'` is OR'd past the whole
attribute-and-object distribution test (`ShadowAttribute.php:789`), because
there is no attribute to test. **A proposed addition is gated by event
visibility alone.** Whoever wires a proposals panel inherits a looser ACL
branch than the occurrence fetcher has, and must not assume the two match.

### 2.3 Overmind already renders proposals — one page over

This is the argument that makes the gap hard to defend. Commit `8fc9017bc`
(*"new: [Overmind] Proposal are now operational in Overmind"*) added
`Event::__attachProposals()` (`Event.php:2171`, called at `:2082`) whose own
comment describes the pattern:

> *attach pending proposed edits/deletions inline on their target attribute and
> surface standalone "new attribute" proposals as their own rows*

and `Elements/Attributes/index.ctp` renders it: an `is_proposal` row flag that
suppresses every action on a proposal row (`:494`, and the twelve action
predicates from `:243` on), plus a `Proposals` toggle button carrying a count
and a `/proposal:<n>` named param (`:457`, `:466`).

Next door, `Values/View/value_occurrence_table.ctp` has `proposal_count` and
nothing else: a `state:proposal` facet token (`:121`) and a badge (`:268`).
**The same theme renders proposal rows on the event view and a bare count on
the value page.**

Two cautions before anyone calls this cheap reuse:

- **`__attachProposals` is `private` and event-scoped.** It filters
  `ShadowAttribute.event_id => $eventId`, applies **no ACL of its own** (it
  trusts `fetchEvent`), and fetches standalone proposals on page 1 only. It is
  the pattern to copy, not the function to call.
- **There is no value-scoped proposal surface anywhere.**
  `ShadowAttributesController::index($eventId = false)` (`:843`) is
  event-scoped, and `AttributesController::index` does not honour a `proposal`
  named param at all — the toggle in that element only works in its
  `$inEventView` context. So this cannot be delivered as a deep link. It needs
  a fetch.

### 2.4 The seam consequence — §14.3 needs one more parameter

§14.3's rule is that **`Value` is the only file naming `value1` or `value2`**,
and phase 22 built `Value::conditionsFor()` as:

```php
['OR' => ['Attribute.value1' => $value, 'Attribute.value2' => $value]]
```

The alias is hardcoded. A proposals fetcher needs
`ShadowAttribute.value1`/`value2` — a second pair of value columns, in a second
table, with the same semantics. Under §14.3's rule that condition may not be
written anywhere but `Value`, so `conditionsFor` needs to know which model it is
building for:

```php
Value::conditionsFor($value, ['alias' => 'ShadowAttribute'])
```

The argument for adding it now is the one §14.3 already makes for
`$options['types']`: it costs a parameter nobody has to pass, and retrofitting
it means revisiting every call site. The argument is stronger here, because a
value table landing later will have to answer the proposals question too, and
`shadow_attributes.value1` is a column that migration will have to either move
or leave behind.

**This is the one item in this document that has a cost if deferred.** Everything
else is a panel that does not exist yet; this is a signature that gets harder to
change with each live phase that calls it.

### 2.5 Where it belongs

- **The Occurrences table** keeps the badge, and gains the `old_id = 0` rows —
  matching `__attachProposals`' own pattern of standalone rows. The rail's
  `state` facet then needs a third token, because "has a pending proposal" and
  "is a pending proposal" are not the same row state.
- **`O4`'s proposal diff**, currently deferred, is where the *content* of a
  proposal renders. §1.4 lists it as a design deferral; §2.1 above says the data
  for it is one indexed query away.
- **Accept and discard are writes** (`ShadowAttributesController::accept`
  `:105`, `discard` `:164`) and stay disabled under §14, like every other
  control that would write.

---

## 3. Presence in feeds and sync servers

### 3.1 One primitive already answers the question

`Feed::searchCaches($value, bool $limited = false)` (`Feed.php:1990`) is
precisely what `value_external` needs, and it is better than the card promises.
It checks `misp:feed_cache:combined` first, then each `caching_enabled` feed,
then does the same for servers — returning per hit the feed's `id`, `name`,
`url`, `source_format`, a `type` of `MISP Feed` or `Feed`, and `direct_urls`.

For MISP-format feeds it goes further: `misp:feed_cache:event_uuid_lookup:<md5>`
stores `<feedId>/<eventUuid>` per cached value, so a hit names **the remote
events that carry the value**, deep-linkable as
`feeds/previewEvent/<feedId>/<uuid>`. Servers keep the same structure
(`Server.php:5252`).

That is a genuinely richer answer than "3 feeds": it is *which* feeds, and for
MISP feeds, which events inside them.

### 3.2 Two primitives, two permission models — and the page must pick the stricter

This is the finding that matters most in this section, because getting it wrong
is not a missing panel but a disclosure.

MISP has two readers of the same cache, and they gate on different things:

| Reader | Gate | Where |
|---|---|---|
| `attachFeedCorrelations()` — the event view's feed column | **`perm_view_feed_correlations`**, early return if absent | `Feed.php:519`, check at `:521` |
| `searchCaches()` — the `/feeds/searchCaches` page | **no role check at all**; ACL is `'searchCaches' => ['*']` | `Feed.php:1990`, `ACLComponent.php:476` |

And the shipped roles make that gap wide. Parsing the `roles` seed in
`INSTALL/MYSQL.sql`, `perm_view_feed_correlations` is set on **exactly one of
the six shipped roles**:

| Role | `perm_view_feed_correlations` |
|---|---|
| admin (site admin) | 1 |
| Org Admin | 0 |
| **User** (`default_role = 1`) | **0** |
| Publisher | 0 |
| Sync user | 0 |
| Read Only | 0 |

So on a stock instance the ordinary user — and their Org Admin — sees no feed
correlations on any event view. If `value_external` calls `searchCaches()` and
renders what comes back, **the Value Profile page shows that same user a fact
MISP deliberately withholds from them everywhere else.**

> **The rule for whoever wires this panel:** apply
> `perm_view_feed_correlations` in the panel, even though `searchCaches()` does
> not. Render the ACL-gated empty state, not an empty result.
>
> **Taken, with one exception argued in `tabs/03-relationships.md` §20.9:**
> feeds an administrator has set `lookup_visible = 1` on. The column defaults
> to `0` (`INSTALL/MYSQL.sql:572`), so on an instance where nobody has touched
> the flag that exception is empty and this rule is unchanged.

`$limited` is a second, narrower gate on the same call, and it is not a
substitute for the first. `FeedsController.php:1654` sets
`$limited = !isSiteAdmin && org_id !== MISP.host_org_id`, and inside
`searchCaches` it does two things: restricts feeds to `lookup_visible = 1`, and
**skips the server branch entirely** (`Feed.php:2062`). So *"sync servers with a
cache hit"*, as §2.6 words the card, is a site-admin-and-host-org fact. For
everyone else that line of the card has no data by design, and the panel needs a
state that says so rather than a zero.

### 3.3 The cache is md5, and the two readers hash differently

The cache stores `md5` of the value. Every write path hashes the **raw** string:

- freetext feeds — `array_map('md5', array_column($values, 'value'))`
  (`Feed.php:1600`)
- MISP feeds — `md5($v)` per attribute value (`Feed.php:1663`)
- the quick-cache path takes hashes **straight from the feed's own cache file**
  (`Feed.php:1702`), so their normalisation is the *publisher's*, not ours

The two readers disagree with each other:

- `searchCaches()` hashes `md5(strtolower(trim($v)))` (`Feed.php:2007`)
- `attachFeedCorrelations()` hashes `md5($part)`, raw (`Feed.php:579`)

Which produces a two-way disagreement on any value containing an uppercase
character:

| Feed holds | Attribute is | Event view | `searchCaches` |
|---|---|---|---|
| `evil.COM` | `evil.COM` | **hit** | miss |
| `evil.com` | `evil.COM` | miss | **hit** |

Neither is more correct; they are two normalisations, and the page's own
identity — `utf8mb3_bin`, per §14.3 — is a **third**. A phase converting
`value_external` therefore states which normalisation it used and accepts that
its answer can differ from the event view's for the same value. It is not a bug
the page can fix from where it sits, and it is not one the page may silently
inherit either.

### 3.4 What a feed hit cannot tell you

Four limits, all structural:

- **No date per value.** Already in §8.2: `misp:feed_cache_timestamp:<feedId>`
  is one integer per feed, rewritten on every re-cache (`Feed.php:1573`). It
  dates the fetch, not the value. This is why §3 has no Timeline verdict in §5.
- **No CIDR, no substring, no near-match.** Set membership on an md5. A feed
  carrying `1.2.3.0/24` does not hit for `1.2.3.4`, and cannot be made to.
- **Nothing is cached by default.** A feed needs `caching_enabled = 1` *and* a
  completed caching job. On a fresh instance every lookup is a miss, so the
  panel's real default state is empty — which §14.8 should account for the way
  it accounts for the demo values.
- **Some types are never cached.** The MISP-feed path skips
  `NON_CORRELATING_TYPES` (`Feed.php:1652`), so for those types "no feed hit"
  means "never looked". Note that the *caching* path stops there, while the
  event view's reader additionally skips any attribute carrying
  `disable_correlation` (`Feed.php:554`) — and `searchCaches` skips neither,
  since it is handed a bare value and never sees an attribute. A third way the
  two readers of one cache disagree, on top of §3.2's permission and §3.3's
  hashing.

### 3.5 Where it belongs

`value_external` on the Overview right rail, which is where §2.6 already puts
it — with the permission gate of §3.2, the normalisation statement of §3.3, and
three distinguishable empty states rather than one: *no hit*, *not permitted*,
and *nothing cached on this instance*. Collapsing those three into a zero is the
failure mode here.

The MISP-feed event uuids from §3.1 are the one piece of genuine upside, and §5
routes them to Relationships rather than to Overview.

> **Settled 2026-08-31, and the split is finer than this section states.**
> `tabs/03-relationships.md` §20 is the agreed design and
> `live/24-relationships.md` §17 the measurements against a populated cache.
> The card keeps the *count*; the *detail* — which source, and which remote
> event — is a fourth section on Relationships, and one method filters for
> both so they cannot disagree (§20.1). Two refinements to this section: the
> three empty states of §3.5 survive but the *not permitted* one is keyed on
> the viewer's **role** rather than on the value, which is what lets it exist
> at all under `live/00-contract.md` §14.6 (§20.5); and §3.2's instruction
> below is taken with one argued exception (§20.9).

---

## 4. Event reports on the value's events

The concept with nothing built, and the one whose right shape is least obvious —
so this section is as much about what *not* to build.

### 4.1 There is no value → report path, and there should not be one

A report is `event_reports`: `event_id`, `name`, `content` as `mediumtext`,
`distribution`, `sharing_group_id`, `timestamp`, `deleted`. The indexes are
`u_uuid`, `name`, `event_id` — **and nothing on `content`.** No fulltext index.

Three candidate mechanics, and only one survives:

| Mechanic | How | Verdict |
|---|---|---|
| **Reports on the events that carry the value** | `EventReport.event_id IN (<the value's event ids>)` via `fetchReports()` | **this one.** Uses the `event_id` index, one query, and the event id set is already in hand from the occurrence fetch |
| Reports whose prose mentions the value | `content LIKE '%<value>%'` | **no.** Unindexed scan of a `mediumtext`; substring false positives (`1.2.3.4` matches `11.2.3.44`); and `event_reports` is `utf8mb4_general_ci` — case-**insensitive**, so it would answer under a third identity model, exactly as §3.3 found for feeds |
| Reports citing the occurrence by reference | `content LIKE '%@[attribute](<uuid>)%'` per occurrence uuid | **not as a fetch.** Same unindexed scan, once per occurrence. See §4.4 |

So the mechanic is **event-scoped**, and the honest label for it is *"reports on
the events where this value appears"* — not *"reports about this value"*. The
page must not imply the stronger claim, because it cannot support it.

`EventReport::fetchReports($user, $options)` (`:570`) ANDs `$options['conditions']`
onto its own ACL conditions, so the whole thing is one call.

### 4.2 A report is not visible because its event is

`EventReport::buildACLConditions($user)` (`:354`) ANDs the event conditions with
a report-level test:

```
Event.org_id = mine
  OR EventReport.distribution IN (1,2,3,5)
  OR (EventReport.distribution = 4 AND EventReport.sharing_group_id IN sgids)
```

**A report carries its own `distribution` and `sharing_group_id`.** Event
visibility is necessary and not sufficient. The occurrence rail's event set is
the right input to the query and the wrong basis for a count — so any "N
reports" figure must come from the ACL'd query itself, which is what §14.6
demands of every count on the page anyway.

### 4.3 The obvious count helper is broken for exactly this case

`EventReport::attachReportCountsToEvents($user, $events)` (`:386`) is what a
phase would reach for, and it must not be used. For an event whose org is not
the viewer's it appends:

```php
'EventReport.distribution' => [1, 2, 3, 5],
'AND' => [
    'EventReport.distribution' => 4,
    'EventReport.sharing_group_id' => $sgids,
]
```

Sibling keys in a CakePHP conditions array are AND-ed, so this requires
`distribution IN (1,2,3,5)` **and** `distribution = 4` — unsatisfiable. The
`'OR' =>` wrapper that `buildACLConditions` has is missing here. The count is
therefore **always 0 for any event the viewer's org does not own**, and its two
callers are `EventsController.php:1139` (the event index) and `:2015` (the event
view), so the defect is shipped and visible today.

Secondarily, it issues one `find('count')` per event in a loop — N queries,
against §14.4's batching rule.

Both reasons point the same way: a report panel uses `fetchReports()` with an
`event_id IN (...)` condition and counts what it gets. This goes in §1.4's
shared-code defects row with the others — **reported, deliberately unfixed**,
because a value page spanning many orgs' events is the exact case it gets wrong
and the fix belongs to whoever owns the event index.

### 4.4 What a report can cite, and what that is worth

Reports reference elements inline as `@[attribute](<uuid>)`
(`EventReport.php:1114`, `:1174`), rendered by
`replaceMISPElementByTheirValue()`. So a report *can* point at a specific
occurrence of the value, and that is a stronger relationship than sharing an
event.

It is not worth a fetch (§4.1), but it is worth something at render time: once a
report has been fetched by `event_id`, checking its content for the occurrence
uuids already in hand is a string operation on data the page holds, not a query.
That turns into a distinction the panel can draw — *cites this occurrence*
versus *same event* — for the cost of a loop.

Whoever builds this should decide it deliberately rather than inherit it: the
cheap version is one badge on a row that already exists.

### 4.5 Where it belongs

Not a tenth tab. Reports are context on the events, and the two panels whose
subject is already "what other people say" are the natural homes — §5 routes it
to Overview for the count and Analyst data for the list, and Timeline and
History each get a lane.

Rendering is a solved problem: `EventReportsController::view($reportId, $ajax)`
(`:70`) already has an ajax mode, `viewSummary` (`:98`) and `viewRendered`
(`:112`) exist, and the Overmind theme ships `Elements/EventReports/index.ctp`
plus its `View/` directory. A panel can list and deep-link without rendering
markdown itself — which also side-steps the *"stored, never rendered"* markdown
item already in §1.4's backlog.

---

## 5. The per-phase assessment

**This is the obligation.** One row per remaining live phase, one verdict per
concept. A phase's write-up records its three verdicts under §14.9 row 9 — and
`no` is a complete answer when the reason is written down.

Verdicts here are **the assessment, not the decision.** A phase may overturn one
in its own document by arguing it; what it may not do is convert a tab without
addressing all three.

**The table covers the eight phases that have not run.** Occurrences is absent
from it because it has already converted — and that is exactly why it needs
§5.1: the heaviest proposals finding in this document lands on the one tab with
no remaining phase to own it. An obligation attached only to future phases would
route the work nowhere.

| Phase | Proposals | Feeds / servers | Event reports |
|---|---|---|---|
| Overview | **yes** — a decision | **yes** — this is its home | **yes** — the count |
| Verdict *(blocked)* | **yes** — a signal | **yes** — a signal | no |
| Sightings | no | no | no |
| Relationships | **yes** | **yes** — the strong case | weak |
| Enrichment *(blocked)* | no | no | weak |
| Analyst data | **yes** | no | **yes** — the list |
| Timeline | **yes** — a datable source §8.2 missed | no — settled | **yes** |
| History | **yes** — already half-present | no | **yes** |

Twenty-four verdicts: **thirteen `yes`, nine `no`, two `weak`.** One phase —
Sightings — draws a `no` on all three, and that row is as finished as any other.
The reasons, phase by phase:

**Overview.** `value_external` is the feeds/servers panel and §3 is written for
this phase — the permission gate of §3.2 is a precondition, not a refinement.
Reports want a count and a link, no more, and §4.2 says the count comes from the
ACL'd query rather than from the event set. Proposals need a *decision*: §2.6's
`value_occurrences` field list carries no state column, so the Overview preview
today shows no proposal badge while the full table does. Either is defensible;
the phase says which and why. Note also §14.12's standing constraint — the
verdict card stays on the fixture, so this phase is partial whatever it decides
here.

**Verdict.** Blocked on the engine, and both `yes` verdicts are inputs to that
engine's design rather than to a wiring phase. A pending proposal is a
**recorded disagreement**, which is what §2.6's *"Contradictions & conflicts,
explicitly not netted off"* band exists to show — a proposal to flip `to_ids` or
to delete an occurrence is the same species of signal as the `to_ids`
disagreement already rendered there. A feed hit is a provenance signal of the
kind the ledger's four groups already carry, but it arrives with §3.2's
permission gate attached, which means **the score itself becomes
viewer-dependent** — consistent with §14.6, and something the engine must know
before it is designed rather than after. Reports are prose, not signals; at most
a citation in the ledger's `Source panel` column. Recorded in
`value-profile-verdict-engine.md` §4's territory.

**Sightings.** None of the three. Proposals carry no sighting —
`shadow_attributes` has no sighting relation, and `Sighting::saveSightings`
matches attribute values. Feed presence is not an observation and has no date
(§3.4), so it cannot join a sighting series; SightingDB is on `value_external`,
a different panel. Reports have no sighting content. **A clean no, and it is
what an assessment looks like when the answer is no.**

**Relationships.** Two real cases. Proposals: a proposed addition (`old_id = 0`)
in an event that already holds this value is **a co-occurrence that does not
exist yet** — so `value_relation_cooccurrence` has to decide whether proposed
attributes count toward co-occurrence, and either answer needs stating. Feeds
are the strong case and the one piece of upside in §3: for MISP-format feeds,
`misp:feed_cache:event_uuid_lookup:<md5>` names the remote event uuids carrying
the value (§3.1), which is *co-occurrence outside this instance* — a graph the
page cannot otherwise draw, and directly relevant to `tabs/03-relationships.md`
§12's finding that no value-centred graph feed exists. **The cheap half only:**
naming the feed events and deep-linking them costs nothing extra, whereas
expanding them into a graph means one remote preview fetch per event and belongs
nowhere near a page render. Reports are weak — two attributes cited in one
report is an asserted relationship of a sort, but `value_relation_asserted` is
about object references, and stretching it to report citations is a design
change, not a wiring decision.

**Enrichment.** Blocked on the persistence §7.9 found missing, and the three
concepts do not change that. Proposals are *not* an enrichment write-back path
in this codebase — worth stating because it is a plausible guess: MISP's
proposal-creation path is `AttributesController.php:1705`'s `is_proposal`
branch, driven by the user, and no module result routes to `ShadowAttribute`.
The `Event.php:2079` comment about ordering proposals after enrichment is about
`fetchEvent` composition, nothing more. Feeds are a different subsystem.
Reports are weak but not empty: `extractAllFromReport`,
`transformFreeTextIntoSuggestion` and `sendToLLM` make a report an
enrichment-adjacent surface — the wrong direction for this page, which wants
"which reports mention it" and not "what can be extracted from this report".

**Analyst data.** Proposals belong here conceptually: a proposal is how MISP let
a third party disagree *before* analyst data existed, and this tab's subject is
who says what about the value. The thread either includes proposals as claims or
excludes them explicitly — silently omitting them leaves the tab claiming to
show every organisation's view while dropping the oldest mechanism for
expressing one. Reports are the natural home for the *list* (§4.5): narrative
analyst content about the value's context, beside the notes and opinions, with
`viewSummary` for a preview. Feeds are not analyst data.

**Timeline.** The most interesting row, because it **adds to §8.2's
scoreboard**. That survey counted the datable sources and concluded only
sightings fully work, with tags and feed appearances having no usable timestamp
at all. Proposals were not counted, and they should have been:
`shadow_attributes.timestamp` is `int NOT NULL DEFAULT 0`, plus nullable
`first_seen`/`last_seen` — **a proposal can join the axis.** Two caveats to
carry: `timestamp` is the last-modified time, so an edited proposal moves; and
the `DEFAULT 0` means an epoch-zero row is possible and must be excluded rather
than plotted in 1970. Reports are datable on the same terms —
`event_reports.timestamp` is `int NOT NULL`, same last-modified semantics — so
"a report was written on an event carrying this value" is one lane. Feeds stay
out, and that assessment is **already complete**: `tabs/06-timeline.md`
§12 proves it from `Feed.php:1573`, and the hatched `Feed appearances` row in
that document's own §8.2 lanes table renders the exclusion honestly. Nothing to
redo.

**History.** Proposals are already half-present — `tabs/07-history.md` §7's
fixture carries a `model` facet with `'ShadowAttribute' => 1`, so the tab
already claims to count proposal audit entries. `ShadowAttribute` does have
`AuditLog` in `$actsAs` (`ShadowAttribute.php:24`), so the claim is supportable;
the live phase's job is to confirm the facet is real rather than fixture-shaped
and to say which proposal actions produce entries. Reports are audited too —
`EventReport::$actsAs` includes `AuditLog` (`EventReport.php:13`) — so report
creation and editing is a genuine history lane, subject to §8.2's standing
constraint that a plain user sees only their own org's entries. Feeds produce no
audit entries against a value; caching is a job, not a change to anything the
audit log tracks.

---

### 5.1 The converted tab — what Occurrences still owes

Phase 22 is built and `viewOccurrenceTable` is the one filled row on §14.12's
board. Its verdicts are **proposals yes, feeds yes, reports no** — and unlike
every row above, two of those are amendments to shipped code rather than
decisions for an unwritten phase.

**Proposals — six things, and the first is the only one that changes what a
reader sees.**

1. **Standalone proposal rows.** §2.2: a proposed addition (`old_id = 0`) is
   invisible, so a value held only as a proposal renders as §2.12's unknown
   page. `Event::__attachProposals` already establishes the pattern — edits
   inline on their target row, standalone proposals as rows of their own — and
   `Elements/Attributes/index.ctp`'s `is_proposal` flag already suppresses every
   action on such a row. Neither is callable as-is (§2.3), but neither has to be
   designed.
2. **A second `state` token.** The rail's `state` group has one token today,
   `proposal`, meaning *this row has a pending proposal against it*. A
   standalone row **is** a proposal, which is a different state, and one token
   cannot mean both — the facet would filter to a mix of the two.
3. **The cap, and what the counts count.** §6 fetches at most 300 rows ordered
   `Attribute.timestamp DESC`. Proposals carry their own `timestamp`, so they
   interleave without inventing an order — but three questions need answers:
   whether a standalone proposal consumes cap, whether the header's *"N
   attribute rows across M events · K organisations"* counts it, and whether the
   footer band's two numbers stay comparable if it does. The safest reading is
   that a proposal is not an attribute row and the header should not say it is.
4. **The looser ACL branch.** §2.2: `buildConditions()` ORs
   `ShadowAttribute.old_id = '0'` past the whole attribute-and-object
   distribution test, so a standalone proposal is gated on **event visibility
   alone**. The occurrence row set's ACL reasoning does not carry over, and
   phase 22's summary of that model describes only the `old_id != 0` branch.
5. **`O4`'s proposal diff.** Already recorded as a deliberate deferral in §1.4
   and `tabs/01-occurrences.md` §10. §2.1 is the news: the data for it is one
   indexed query, not a schema problem. It is where *what* was proposed renders,
   as against the fact that something was.
6. **The seam parameter.** §2.4. The fetch needs
   `ShadowAttribute.value1`/`value2`, which §14.3's rule permits only inside
   `Value`, so `conditionsFor` needs its `alias` option before any of the above
   can be written. **This is the gating item, and the one that gets more
   expensive with each phase that ships against the current signature.**

**Feeds — and here the tab has a better primitive than the Overview does.**
§3.2's hazard is that `searchCaches()` enforces no permission. That hazard does
not apply to this panel, because this panel holds **attribute rows**, and rows
are what the event view's own reader takes:

```php
$fakeEventArray = [];
$this->Feed->attachFeedCorrelations(array_column($rows, 'Attribute'), $user, $fakeEventArray);
```

That exact call, with that exact fake-event workaround, is already shipped at
`AttributesController.php:1888` for the attribute index — a flat,
value-searchable attribute list, structurally the same thing as this table. One
Redis pipeline for all rows, `perm_view_feed_correlations` enforced by the
primitive itself (`Feed.php:521`), and the same raw hashing the event view uses,
so a feed column here **agrees with the event view by construction**.

The consequence is worth stating plainly, because it looks like a bug and is
not: `value_external` on the Overview has only a bare value to work with, so it
is stuck with `searchCaches` and must add the permission gate by hand (§3.2) and
accept the lowercased hash (§3.3). **The two panels will therefore disagree
about the same value whenever it carries an uppercase character** — the table
saying hit and the card saying nothing, or the reverse. Two panels, two
primitives, each correct for its own shape. Whoever wires the second one names
this rather than discovering it.

**Reports — no.** The Event column could carry a report indicator, but §4.5
places reports on Overview and Analyst data, and §4.3's helper is broken for
exactly the cross-org case this table is full of. Nothing to add here.

**Who owns it.** This is the amendment §6 warns about: a filled row whose `Q`
ceiling of 9 and tier table both change. It is not a new phase's natural work
and it is too large to fold into an unrelated one, so the honest options are a
short phase of its own or the next phase that touches `Value` taking the seam
parameter with it. Either way the numbers move, and phase 22's document needs a
pointer to wherever they move to.

---

## 6. What the conversion board gains

§14.12 records **twenty-seven endpoints, and all twenty-seven exist.** Nothing
in this document changes that count, because a row moves off `—` only when a
phase document records its numbers, and none of what follows is built.

What §5 implies for the board, as a forecast rather than a change:

| Concept | Board impact |
|---|---|
| Proposals | **no new endpoint.** Extends `viewOccurrenceTable` (already converted at phase 22) with the `old_id = 0` rows — §5.1 is the full list — and `viewAnalystThread` / `viewTimeline` / `viewHistory` with a lane each. A converted row being *extended* is a case §14.12 has not had to describe yet — phase 22 owns those numbers, and a later phase changing them has to update the row it did not fill |
| Feeds and servers | **no new endpoint.** `viewExternal` exists and is unconverted; §3 is its brief. §5.1 adds a second, unforeseen caller: a feed column on `viewOccurrenceTable`, which reaches the same cache through `attachFeedCorrelations` rather than `searchCaches` and is the better primitive for it |
| Event reports | **one new element, probably two.** A count on the Overview (inside `viewExternal`'s neighbourhood or its own card) and a list on Analyst data. The phase that adds them adds the rows |

The proposals line is the one worth flagging now: **it reopens a converted
row.** Phase 22's `Q = 9` ceiling and its tier table are the record for
`viewOccurrenceTable`, and adding a standalone-proposal fetch changes both — as
does the feed column, which adds a Redis pipeline rather than a query and so
needs a word about how §14.4's tiers count a cache lookup at all. That is not a
problem, but it is the first time the campaign will have to amend a finished row
rather than fill an empty one, and §14.12's rule as written does not say who
owns that. §5.1 lays out the work and names the two honest options for placing
it.

---

## 7. Hazards found writing this

Four, in the shape §14.10 uses. All four are reported and none is fixed here.

**`perm_view_feed_correlations` is off on five of six shipped roles, and
`searchCaches` does not check it.** §3.2. The disclosure risk in the campaign:
`value_external` calling the unchecked primitive would show ordinary users what
the event view hides from them. Only the site-admin role ships with the
permission — the default role does not, and neither does Org Admin.

**The feed cache is written raw and read lowercased.** §3.3.
`searchCaches` hashes `md5(strtolower(trim($v)))` (`Feed.php:2007`) against a
cache written from `md5($raw)` (`Feed.php:1600`, `:1663`, `:1702`), while
`attachFeedCorrelations` hashes raw (`Feed.php:579`). The two readers disagree
in both directions on any value with an uppercase character, and the page's own
`utf8mb3_bin` identity is a third answer. Not fixable from this page; not
silently inheritable either.

**`EventReport::attachReportCountsToEvents` returns 0 for every event the
viewer's org does not own.** §4.3. A missing `'OR' =>` wrapper makes the
condition unsatisfiable (`EventReport.php:386`); shipped, and visible on the
event index (`EventsController.php:1139`) and event view (`:2015`). Also N
queries for N events. Belongs in §1.4's shared-code defects row — the value page
must not reuse it, and the fix belongs to the event index's owner.

**A value that exists only as a proposal renders as an unknown value.** §2.2.
Not a bug in anything shipped — a consequence of counting proposals over the
occurrence row ids, which was the right choice for a badge. §1.1's promise is
*"every occurrence"*, and a proposed addition is not one, so the letter holds.
What does not hold is §2.12: the page renders the sparse **unknown** state for a
value this instance demonstrably holds a record of, and "unknown" is a claim
about the instance rather than about the attribute table.

One correction to record, too, in the shape §14.10 used for
`fetchSimpleEvents`:

**§8.2's datable-source scoreboard is missing a source.** It counted the
sources that can join a time axis and found sightings the only one that fully
works. `shadow_attributes.timestamp` and `event_reports.timestamp` are both
`int NOT NULL` and both usable, with the last-modified caveat that applies to
every `timestamp` column in MISP. Two more sources can join the axis than that
survey accounted for — which is a change to Timeline's evidence base, not to its
verdict on tags and feeds.

---

## 8. Out of scope

- **Writes.** Accept/discard on a proposal, and authoring a report, are writes.
  [`value-profile-writes.md`](value-profile-writes.md) owns the design; under
  §14 they render disabled.
- **Fixing the four hazards in §7.** Each is reported where it belongs. The
  `attachReportCountsToEvents` defect and the feed-cache normalisation split
  both live in shared code with callers outside this feature, and §14.7's
  three-part test governs anyone who wants to touch them.
- **A tenth tab.** All three concepts land on panels that exist or on the two
  time-axis tabs. §4.5 argues the reports case specifically; the same reasoning
  covers the other two.
- **Expanding feed events into a graph.** §5's Relationships row takes the
  cheap half — naming and deep-linking the remote events. Fetching them is
  remote HTTP per event and does not belong in a page render.
- **Sequencing.** This document does not order the campaign, exactly as §14.13
  does not. It gives each phase three questions and a starting answer.
