# PRD: A graph endpoint for the Pivot Explorer

**Status:** DRAFT — not started. Deferred from
[`pivot-explorer-v16-prd.md`](pivot-explorer-v16-prd.md) D13.
**Owner:** Sami Mokaddem (Claude-assisted)
**Created:** 2026-08-31
**Depends on:** nothing — but the parent PRD's decisions (D1, D9, D10, D12) define the payload
this endpoint must produce, so read §2 there before designing the response.

---

## 1. Why

`/events/view/{id}.json` returns the whole event, and the Pivot Explorer fetches it to build a
graph. Measured on the dev instance:

| Event | Attributes | Objects | Live refs | Correlations | Attribute JSON |
|---|---|---|---|---|---|
| 4116 | 369,822 | 28,410 | 0 | 5,629 | **~81 MB** |
| 1195 | 20,970 | 4,724 | 2,362 | 350 | ~5.5 MB |

81 MB is attributes alone — before objects, tags, analyst data or feed correlations — so event
4116's view is roughly a **100 MB download**, from which the graph draws **86 nodes**.

The parent PRD budgets everything downstream of the fetch (canvas at 1,500 nodes, correlations on
demand, the element listing paged server-side) and deliberately leaves the fetch alone, so large
events stay slow to open. This endpoint is the fix.

Secondary win: it moves graph construction out of
`app/View/Themed/Overmind/Elements/Events/View/event_pivot_explorer.ctp`, which is 858 lines of
inline JS doing `buildGraphData()`, `computeConnectivity()` and node/edge identity by hand.

## 2. What to build

`GET /events/graph/{id}.json` (name to bikeshed) returning:

```json
{
  "nodes": [ { "id": "...", "type": "attribute|object|event|feed|server",
               "label": "...", "uuid": "...", "scope": "self|foreign",
               "event_uuid": "...", "children": [ ... ],
               "annotations": { "count": 3, "mood": "disputed" } } ],
  "edges": [ { "from": "...", "to": "...",
               "kind": "object-reference|analyst-relationship|correlation|feed-correlation|server-correlation",
               "relationship_type": "communicates-with" } ],
  "meta":  { "event": { "id": 4116, "info": "...", "orgc": "...", "date": "..." },
             "levels_seeded": ["L0", "L1"],
             "skipped": { "L2": { "objects": 28410, "reason": "budget" } },
             "available": { "correlations": 5629 } }
}
```

The server owns node identity, which is the point: the client currently invents `attr:<uuid>` /
`obj:<uuid>` prefixes, so the graph's identity scheme lives in a `.ctp`.

**`meta` is not decoration.** The parent PRD's D11 and D12 *require* the graph to state which
resolution levels it seeded and what it left out; `meta` is where that comes from, and it feeds
the header (D2c) directly.

### The resolution levels it must implement (parent D12)

| Level | Content | Always? |
|---|---|---|
| L0 | event node + one proxy per `RelatedEvent` | yes — 86 nodes worst case observed |
| L1 | elements in an object reference or analyst relationship, + object children | yes — 2,362 edges worst case |
| L2 | objects with no relationship, containment only | only if the 1,500-node budget allows |
| L3 | per-attribute correlations (`RelatedAttribute`) | no — separate request (parent D9) |

Event-level attributes are **never** seeded without a relationship (parent D10): 80% of all
attributes are event-level, so seeding them means seeding the whole event again.

## 3. What already exists (do not rebuild)

- **`app/Lib/Tools/EventGraphTool.php`** — the precedent. Returns `{items, relations}` with
  `node_type` and `event_id` per node, driven by a POSTed `filtering` body and an `extended` named
  param, behind `EventsController::getEventGraph{References,Tags,Generic}` (`:6960-7035`). This is
  the same idea done before there was a graph library to feed; read it before designing the
  response, but note it has no budget, no levels and no analyst data.
- **`RelatedEvent`** — already in `fetchEvent` output (`includeEventCorrelations` defaults true,
  `Event.php:2953-2954`), carrying `id, uuid, info, date, threat_level_id, analysis, published,
  distribution, org_id, orgc_id` + `Org`/`Orgc`. This *is* L0, and it is the correlation aggregate:
  event 4116's 5,629 correlations collapse to 86 distinct events.
- **`RelatedAttribute`** — `includeGranularCorrelations` (`EventsController.php:1858-1862`), off by
  default for REST. That default is wanted; L3 is a separate request.
- **Analyst data** — attached by `AnalystDataParentBehavior`; `attachAnalystDataBulk` gives
  attributes/objects flat `Note`/`Opinion`/`Relationship` arrays but **not**
  `RelationshipInbound` (only the event level gets that). Fixing the bulk path is a candidate
  sub-task.
- **Feed/server correlations** — `Feed::attachFeedCorrelations` puts per-attribute hits on
  `attribute['Feed'][]` / `['Server'][]` plus a deduplicated source map on `event['Feed'][id]` /
  `['Server'][id]`. One node per source, not per correlation. Past 10,000 hits it already degrades
  to `attribute['FeedHit'] = true` + `event['FeedCount']` — handle both shapes.
- **Paged element listing** — `EventsController::viewEventAttributes()` (ACL `*`) and
  `AttributesController::index()` (`page`, `limit`, `sort`, `direction` + filters). The parent
  PRD's D4 pane uses these; this endpoint does **not** need to serve them.

## 4. ACL — the part to get right

The endpoint must not become a way around any of these. Reuse `fetchEvent`'s user-scoped path
rather than querying tables directly.

| Data | Gate |
|---|---|
| event / attributes / objects | `fetchEvent($user, …)` — distribution + sharing-group filtering |
| analyst data | own org, or `distribution IN (1,2,3)`, or distribution 4 with an authorised sharing group (`AnalystDataBehavior::fetchForUuids`) |
| feed / server correlations | **`perm_view_feed_correlations`** (`Feed.php:521-522`) |
| server source fields | non-site-admin outside the host org sees only `id`, `name`; server event-UUID hits withheld entirely (`Feed.php:613-620, 648-652`) |
| correlations | `Correlation::getRelatedEventIds($user, …)` with authorised sharing groups |

Add the ACL entry when the action lands — `queryACL` / `findMissingFunctionNames` reports a
missing one.

## 5. Open questions

1. **Endpoint shape** — `/events/graph/{id}` versus extending `getEventGraphGeneric` with a new
   lens. The latter inherits `EventGraphTool`'s POSTed filter body; the former is a clean start.
2. **Does it also serve the expand/pivot direction?** The parent PRD defers a pivot entry point
   seeded from one indicator. A `?from={uuid}&hops=1` mode would make this endpoint the answer to
   both, and would finally give lazy cluster expansion something to call — see
   `prd/misp/async-children-provider.md` in the pivotick repo, still *Proposed*.
3. **Where does the budget live?** Server-side (the endpoint returns at most N nodes and says what
   it dropped) or client-side (the endpoint returns levels and the client picks)? Server-side is
   the only version that shrinks the payload, which is the whole point.
4. **Caching.** The response is a pure function of the event's `timestamp` plus the user's ACL
   scope — cacheable in Redis, but the ACL scope is part of the key.
5. **Object aggregation.** L2 is *skipped* above the budget rather than summarised. A "28,410
   objects → 12,000 file, 8,000 url" roll-up is the natural successor and has no existing
   equivalent (unlike correlations, which have `RelatedEvent`).
6. **Does the `.ctp` keep any graph-building?** If the endpoint returns pivotick-shaped nodes and
   edges, `buildGraphData()` disappears and the view becomes options plus callbacks. That is the
   larger prize and worth designing for explicitly.

## 6. Acceptance criteria

- `/events/graph/{id}.json` returns `{nodes, edges, meta}` for every event in §1's table in
  **well under a megabyte**, event 4116 included.
- The levels seeded match parent D12, and `meta` states what was seeded and what was skipped, with
  counts.
- No data reaches a user that `/events/view/{id}.json` would not have given them — verified per row
  of §4, including `perm_view_feed_correlations` and the server field restriction.
- Node identity is stable across calls, so a client can diff two responses.
- An event with no relationships at any level returns L0 plus an honest empty `nodes`/`edges`, not
  an error.
- The Pivot Explorer builds its graph from the response with no `computeConnectivity()` or
  `buildGraphData()` of its own.
- ACL entry present; `findMissingFunctionNames` clean.
