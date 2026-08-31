# PRD: Pivot Explorer — leveraging Pivotick v1.6.0 on `/events/view2`

**Status:** DRAFT — all decisions settled (D1–D4, D5′, D6–D13; D5 withdrawn). Ready for implementation.
**Owner:** Sami Mokaddem (Claude-assisted)
**Created:** 2026-08-28
**Grilled:** 2026-08-28 → 2026-08-31 — see §5 for what was settled and what changed as a result
**Working dir:** /home/sami/git/MISP
**Branch:** `worktree-pivotick-v16` off `develop` (bundle upgrade already committed there)
**Library:** Pivotick v1.6.0 (`app/webroot/js/pivotick.iife.js`, `app/webroot/css/pivotick.css`)

---

## 1. Summary

Pivotick v1.6.0 closes three of the four library gaps that were blocking the Pivot Explorer:
**edges now come in kinds that can be styled and switched off**, **nodes carry rim badges**, and
**the legend keys several dimensions at once**. A data dock, a real panel lifecycle, async
content renderers and a full set of before-write hooks landed alongside them.

The review that produced this revision changed the framing. The graph is **not a renderer for an
event** — it is an **exploration surface seeded by the event's authored relationships and grown
on demand**. Three consequences drive the whole design:

1. **One implementation, not two.** Editing and exploring are modes of one graph, not two
   graphs. Whether you may write is decided by *what you clicked*, not which page you are on
   (D8).
2. **The graph has resolution levels**, and the seed takes the highest that fits a 1,500-node
   budget: correlated-event proxies, then authored relationships (uncapped — bounded in practice
   at 2,362 edges), then relationship-less objects as containment-only clusters (D5′, D10, D12).
   A behemoth event is not a special case; it is an event that only affords the lower levels.
3. **Per-attribute correlations load on demand** (D9) — the most numerous relationship by far, and
   the aggregate that replaces them at low resolution (`RelatedEvent`) is already in the payload.
4. **Five edge kinds on two dimensions** (D1): how an edge came to exist (`kind` — reference,
   analyst assertion, correlation, feed, server) and what it asserts (`relationship_type`, ~143
   values). Feed and server correlations bring `feed`/`server` nodes onto the canvas.

The one library gap that did **not** close is lazy cluster expansion (`childrenProvider` /
`onBeforeNodeExpansion`), which is what expandable correlated events need. §4 scopes that out
and §11 keeps it on the list.

## 2. Background & Motivation

### 2.1 Two activities, one graph

Two distinct things an analyst does with a graph:

- **Curation** — "this file dropped that payload, which contacted that C2." Authoring structure
  while building an event. Single-event, precise, infrequent.
- **Exploration** — "I have this hash; what else touches it?" Iterative, crosses event
  boundaries, unbounded, frequent.

The tempting conclusion is two graphs, one editable and one read-only. **Rejected** (D8), because
the most valuable output of an exploration session is a cross-event assertion — *this event is
attributed-to that actor* — and a two-graph split puts the write capability in the graph that
structurally cannot reach the nodes worth writing about: the single-event editor has no foreign
nodes, and the read-only explorer has no write.

Pivotick already models this correctly: `ModeRailOptions` (`GraphUI.ts:134-142`) reserves
`explore` and `enrich` as coming-soon rail modes beside Select / Create / View, and
`editors.{nodeEditor,nodeCreator,deletion,edgeEditor}.enabled` (`:640-693`) removes write
affordances rather than vetoing them. The library's own roadmap says *modes*, not *graphs*.

### 2.2 Three kinds of write, not one

"Read-only" conflates three things with three different gates. This taxonomy is the backbone of
the gating model in §6.6:

| Kind | Examples | Persists? | Gate |
|---|---|---|---|
| **View write** | pivot in a node, hide, expand, re-layout, ephemeral enrichment, save a layout | no (except saved layouts) | `perm_auth` for `modules/queryEnrichment`; `perm_add` for `eventGraph add` |
| **Structural write** | object reference CRUD; enrichment persisted into the event; delete attribute/object | yes | `perm_add` **+ event edit rights** |
| **Assertional write** | note, opinion, analyst relationship | yes | `perm_add` + `perm_analyst_data`, **no ownership of the target** |

The third row is the important one: `ACLComponent.php:23` gates `analystData add` on role
permissions alone. Any user holding them may annotate **anything they can see**, including
another organisation's event. That is the entire design of analyst data, and any surface that
confines writing to "the event you own" throws that capability away.

Note also that ephemeral enrichment (`modules/queryEnrichment`, `perm_auth` —
`ACLComponent.php:581`) is available to a user who cannot edit the event at all, whereas
persisting enrichment into the event (`events/queryEnrichment`, `perm_add` — `:405`) is not.

## 3. Current State (evidence)

### 3.1 What the Pivot Explorer builds today

`event_pivot_explorer.ctp` is now 117 lines — markup, the editor CSS, and the `data-pe-*`
config attributes on `#pe-card`. All behaviour lives in `app/webroot/js/pivot-explorer.js`
(936 lines), loaded by the element's `assetLoader` call beside `pivotick.iife`. **Bare
`:NNN` references in this section and in §6 point into that module.** It fetches
`/events/view/{id}.json` and builds:

| Element | Source | Notes |
|---|---|---|
| Object nodes | `ev.Object` | Only objects `computeConnectivity()` finds connected |
| Attribute nodes | `obj.Attribute` | Nested as cluster **children** of their object (`:317-329`) |
| Event-level attribute nodes | `ev.Attribute` | Only when an object reference points at them (`:299-305`) |
| Edges | `obj.ObjectReference` | The **only** edge kind; label = `relationship_type` |
| Editor tray | `UI.extraPanels` | Unconnected attributes/objects as draggable chips |

Node style is keyed on kind via `nodeStyleMap` — event = green hexagon, object = blue square,
attribute = orange circle (`:386-391`) — with `iconClass` carrying the misp-iconify glyph,
`imagePath` carrying attachment thumbnails, and `styleCb` spending `strokeColor`/`strokeWidth`
on the pending-reference ring (`:414-419`). Every styling channel is committed.

### 3.2 What v1.6.0 adds that this graph can use

| Capability | API | Verified at |
|---|---|---|
| Edge kinds | `render.edgeTypeAccessor`, `render.edgeStyleMap` | `RendererOptions.ts:178,197` |
| Relationship layers | `UI.filter.edgeFacets`; `{ key: 'kind' }` is complete | CHANGELOG §*Edges come in kinds* |
| Layer toggle without moving the graph | link force gates on `Edge.visibleIgnoringLayer` | idem |
| Rim badges | `NodeStyle.badges`, `callbacks.onBadgeClick` | `RendererOptions.ts:438` |
| Multi-dimension legend | `UI.legend: { position, sections: [...] }`, `scope: 'edge'` | `GraphUI.ts:53` |
| Orphan control | `UI.filter.hideDisconnected` + View flyout switch | CHANGELOG §*Nodes with nothing left attached* |
| Data dock | `UI.table`, `UI.dock`, `UIManager.addDockTab()` | CHANGELOG §*The dock holds more than the table* |
| Before-write hooks | `onBeforeEdgeCreate`, `onBeforeDelete`, `isValidConnection`, … | CHANGELOG §*Every user write goes through a hook* |
| Write-affordance removal | `editors.{nodeEditor,nodeCreator,deletion,edgeEditor}.enabled` | `GraphUI.ts:640-693` |
| Reserved rail modes | `UI.modeRail: { explore, enrich }` (disabled "SOON") | `GraphUI.ts:134-142` |
| Panel lifecycle | `ExtraPanel` re-invoked per selection; `id`/`order`/`reactive` | CHANGELOG §*Sidebar panels* |
| Async content | every content hook may return a `Promise`; `RenderContext{signal}` | CHANGELOG §*Content renderers* |

**Not available**, and relied on by nothing here: a declarative initial filter value.
`FilterOptions` is `{ facets, excludeKeys, edgeFacets, hideDisconnected }` and `FilterFacet` is
`{ key, label, type, options, matchMode, accessor, predicate, order }` — neither carries an
opening value, so "open with this layer switched off" is only reachable imperatively via
`queryEngine.setEdgeFilter()` *after* construction, which forfeits v1.6.0's "filters apply
before the first layout" guarantee. This is what killed the default-hidden design (D9) and is
logged as a library ask in §11.

### 3.3 Upgrade compatibility audit (v1.4.0 → v1.6.0) — already performed

MISP shipped a bundle dated 2026-07-23, i.e. **~v1.4.0** (v1.4.0 tagged 07-21, v1.5.0 07-29).
v1.5.0 carries two breaking sections. Audit result: **the existing integration is unaffected.**

- Uses none of the removed/renamed API: no `UI.selectionMenu`, `graphControls`, `graphToolbar`,
  `graphNaviation`.
- The extra panel's `render` returns an **HTMLElement** (`pivot-explorer.js:889`), so the
  1.5.0 "a `string` never
  renders as markup" change does not bite. Its `innerHTML` writes are all to its own DOM.
- `graph.on('edgeAdd', …)` (`pivot-explorer.js:894`) and `simulation.d3LinkDistance` still exist.
- v1.6.0's breaking changes are confined to physics presets. As the code stands, the graph
  **configures** `d3LinkDistance: 200` (`pivot-explorer.js:441-442`), so physics stays
  `'manual'` — auto declines
  to take over a graph that tuned any knob it drives, and layout is unchanged by the upgrade
  alone. **D7 then deliberately opts into `'auto'`.**
- Bundle verified: `node --check` passes, the `window.Pivotick` footer is present, and the
  deployed file is byte-identical (md5 `140ead0d…`) to a fresh `vite build` of the `v1.6.0` tag.

One **user-visible** change to communicate, not fix: 1.5.0 replaced full-mode chrome with the B3
mode rail and removed the `e` "Edit Graph" toggle in favour of **Create mode**. The comment that
still described "pivotick's Edit ▸ Add edge tool" has been refreshed accordingly
(`pivot-explorer.js:526-529`) — the second half of task 1.

### 3.4 MISP data already available

- **Analyst data is already on the wire.** `/events/view/{id}.json` takes the REST path, which
  sets `includeAnalystData = true` unconditionally (`EventsController.php:1787`). Attributes,
  objects and event reports carry flat `Note` / `Opinion` / `Relationship` arrays plus flattened
  child notes/opinions; the event itself also carries `RelationshipInbound`.
- **`RelatedEvent` is already on the wire** — `includeEventCorrelations` defaults true
  (`Event.php:2953-2954`), with `id, uuid, info, date, threat_level_id, analysis, published,
  distribution, org_id, orgc_id` + `Org`/`Orgc`.
- **`RelatedAttribute` is not**, for REST: `includeGranularCorrelations` is set only when
  `!_isRest()` or the named param is present (`EventsController.php:1858-1862`). Under D9 this
  is a **feature**, not a gap — see §6.7.
- **Inbound analyst relationships are missing on attributes/objects** — the bulk path
  (`attachAnalystDataBulk`) does not add `RelationshipInbound`; only the event level gets it.
- **Saved graph layouts already exist** — `EventGraphController` + `EventGraph` model, ACL
  `view: *`, `add: perm_add`, `delete: perm_modify` (`ACLComponent.php:1105-1110`). The legacy
  event graph used this via `quickSaveNetworkHistory`.

### 3.5 Measured shape of the data (dev instance, 4,167 events with attributes)

Everything below is a live measurement, and it is what the seed rule (D5′) is built on.

**Event size:**

| Attributes/event | Events |
|---|---|
| ≤50 | 1,377 |
| 51–500 | 1,984 |
| 501–5k | 714 |
| 5k–50k | 89 |
| >50k | 3 |

**Six largest events:**

| Event | Attributes | Objects | Live refs | Correlations |
|---|---|---|---|---|
| 4116 | 369,822 | 28,410 | **0** | 5,629 |
| 4134 | 58,146 | 4,617 | 0 | — |
| 4176 | 51,620 | 11,064 | 0 | — |
| 4114 | 31,495 | 0 | 0 | — |
| 2561 | 27,495 | 0 | 0 | — |
| 1195 | 20,970 | 4,724 | 2,362 | 350 |

**Relationship density:**

- Object references: **11,330** live, across 69,957 objects. Only **345 of 4,167 events (8%)
  have any object reference at all**; of those, 315 have ≤50 and the maximum in one event is
  2,362.
- Analyst relationships: 120 rows instance-wide (largely synthetic test data — a deployment
  using analyst data heavily would differ; the object-reference figures would not).
- Correlations: **336,221** rows. Event 4116 alone has 5,629.
- 80% of all attributes (2.2M of 2.8M) are **event-level**, not inside an object.

**Payload size — the unbudgeted cost.** `/events/view/{id}.json` returns the whole event, and the
Pivot Explorer fetches it today:

| Event | Attribute JSON (conservative) |
|---|---|
| 4116 | **~81 MB** |
| 1195 | ~5.5 MB |

That is attributes alone, before 28,410 objects, tags, analyst data or feed correlations — so
event 4116's view is on the order of a **100 MB download**. The seed budget (D12) caps the canvas
at 1,500 nodes while still transferring 370,000 attributes to get there, and no client-side
paging can fix a payload that has already arrived. See **D13**.

**Two conclusions drawn from this:**

1. **The authored relationship set is small and safe to always show.** 2,362 edges worst case.
2. **For ~92% of events, correlations are the only relationship there is.** Today's
   reference-only rule therefore renders an **empty canvas** for most large events —
   `computeConnectivity()` finds nothing connected, so `buildGraphData` emits nothing. Four of
   the six largest events render blank today. That is not an edge case; it is the norm.

### 3.6 Gap statement

The graph draws one relation kind out of at least four, gives no indication that an element
carries analyst data, names one of its encodings, cannot say which event a node belongs to, and
renders nothing at all for the majority of large events — while the data for most of it is
already in the response it fetches, and the library now has the channels to show it.

## 4. Goals / Non-Goals

### Goals

1. Draw object references, analyst relationships and feed correlations as **distinct, switchable
   edge layers** (all three already in the payload), fetch per-attribute correlations **on
   demand**, and add `relationship_type` as a second, orthogonal edge filter (D1).
2. Indicate analyst data (note count, endorsed/disputed) with **rim badges**, detail in a
   selection-reactive sidebar panel.
3. Name every encoding in a **sectioned legend** that doubles as the layer control.
4. Show **event provenance** — which nodes belong to the event being viewed and which do not.
5. Replace the bespoke editor tray with a **dock pane** listing the whole event, since the graph
   now seeds small and the dock is the route to everything else.
6. Move edge creation onto **`onBeforeEdgeCreate` + `isValidConnection`**, and gate write
   affordances with **`editors.*.enabled`** per the taxonomy in §2.2.
7. Never render a silently empty canvas: an event with no authored relationships says so and
   offers the correlation fetch.

### Non-Goals

- **A second entry point (pivot route).** The component is to be *parameterised* for it (seed +
  default mode) but the route is not built here. See §11.
- **Lazy expansion of correlated events.** Needs `childrenProvider` / a fired
  `onBeforeNodeExpansion`, which v1.6.0 did not ship. Correlated events appear as leaf proxy
  nodes only (§6.4).
- **Writing notes and opinions from the graph.** Read-only — they are displayed (badge + panel)
  but not authored here. Analyst **relationships** *are* written (D2b): they are the assertion an
  exploration produces, and D8 turns on being able to record one. Notes and opinions are a form
  problem rather than a graph problem, and the existing `analystData/add` UI already solves it.
- **Enrichment from the graph** (either variety). The taxonomy in §2.2 exists so the design
  accommodates it; no enrichment call is made here.
- **Backend aggregation of objects.** A behemoth's L2 is *skipped*, not summarised. The
  correlation aggregate already exists (`RelatedEvent`), and feed correlations already degrade to a
  count past 10,000 hits (D1); the object one does not exist, and building it is new endpoint work.
  See §11.
- **`server-correlation` as a shipped layer.** The kind is defined (D1) but the data is absent from
  the REST payload by default; whether to request it is deferred (§11).
- **Analyst-data threads.** Flat list in the sidebar panel; no nested renderer.
- Relationship targets outside the graph's node universe (Galaxy, Organisation, SharingGroup).
- Retiring the legacy `/events/view_graph`, or touching `EventGraphTool` / `getEventGraph*`.

## 5. Design Decisions

### All settled in review (2026-08-28 → 2026-08-31)

#### D8 — One implementation; writes gated by what you clicked ✅ SETTLED

One graph component, not two. Whether a write is offered is decided by the element under the
cursor and the user's permissions, **not** by which page is open:

- **Object references** are offered only where there is a single-event context to attach them to,
  and only on nodes belonging to that event (`scope === 'self'`).
- **Notes, opinions and analyst relationships** are offered on any element, anywhere, because
  `ACLComponent.php:23` gates them on role permissions alone.

Rejected: two graphs (one editable/event-scoped, one read-only/exploratory). The cross-event
assertion — the most valuable product of exploration — is available in neither half of that
split. Exploring and editing become **modes**, matching the `explore`/`enrich` rail modes
pivotick already reserves.

#### D5′ — Authored relationships are always seeded, uncapped ✅ SETTLED (supersedes D5)

The elements participating in an **object reference** or an **analyst relationship** are always
seeded, with no node budget. Justified by §3.5: 2,362 edges worst case, 315 of 345
reference-having events under 50.

This is **L1** of D12's resolution levels — L0 (correlated-event proxies) sits below it and is
also always present; L2 (relationship-less objects) sits above it and *is* budgeted, per D10.
"Uncapped" applies to L1 alone.

**D5 as originally written is withdrawn.** It proposed building every attribute and object into
the graph and hiding orphans with `hideDisconnected`. On event 4116 that is ~398,000 nodes
constructed before anything is hidden. `hideDisconnected` survives as a **user-facing switch**
(it is on the View flyout of every graph regardless), not as a load strategy.

#### D9 — Correlations load on demand, not hidden-by-default ✅ SETTLED

Correlations are **absent from the opening payload**. A control fetches them; the cap applies at
fetch time.

Rejected: shipping correlations and switching the layer off by default. Three reasons, all
concrete:

1. **A layer-hidden edge still shapes the layout** — the link force deliberately gates on
   `Edge.visibleIgnoringLayer` so that toggling doesn't reshuffle. Event 4116 would open laid out
   by 5,629 invisible edges.
2. **Orphan flood** — a foreign node whose only edge is a hidden correlation has no visible
   edge; thousands of disconnected dots unless `hideDisconnected` is forced on.
3. **No declarative way to do it** (§3.2): it takes a post-construction
   `queryEngine.setEdgeFilter()`, so the correlations reach the first layout and are then
   hidden — losing the "filters apply before the first layout" guarantee.

On-demand loading delivers what default-hidden was for (uncluttered opening, correlations one
click away) while making the first paint *cheaper*, and it turns the 92% empty case into a
designed first step rather than a defect.

#### D12 — Resolution levels: the seed takes the highest level that fits the budget ✅ SETTLED

The graph has four resolution levels. The seed takes the highest that fits a **single node budget
of 1,500** (pivotick's own detail threshold — past it the minimap stops resolving per-node style
and reads as a density map), and the same budget caps D9's correlation fetch.

| Level | Content | Source | Worst case observed | Cost |
|---|---|---|---|---|
| **L0** | event + correlated-event proxy nodes | `RelatedEvent` | **86 nodes** (event 4116) | free, already in payload |
| **L1** | authored relationships — object references + analyst relationships | inline | 2,362 edges (event 1195) | free, already in payload |
| **L2** | objects with no relationship, containment only | inline | budget-capped (D10) | free, already in payload |
| **L3** | per-attribute correlations | `RelatedAttribute` | fetch-capped (D9) | one extra request |

**Why this matters:** a behemoth event is no longer a special case needing an apology. Event 4116
(369,822 attributes, 0 references, 5,629 correlations) affords L0 + L1 — 86 nodes saying *this
event touches 86 others* — and is told that L2 does not fit. That is a useful graph, not a
message.

**The correlation aggregate already exists.** Event 4116's 5,629 correlations collapse to 86
distinct related events, and `RelatedEvent` — that exact aggregate — is already loaded by default
(`Event.php:2953-2954`). So backend aggregation for the dimension that actually explodes
(336,221 correlation rows vs 11,330 references) costs nothing and is available today.

**What is *not* aggregated:** objects. There is no existing "28,410 objects → 12,000 file, 8,000
url" roll-up, and building one is real endpoint work. D10's ceiling means Phase 1 does not need
it — objects above the budget simply are not drawn and the dock lists them. Logged in §11.

#### D10 — Objects with no relationship: seeded below a budget ✅ SETTLED

**(c)** — objects with no relationship are seeded as containment-only clusters up to the 1,500-node
budget (L2 in D12); above it the seed stops at L0+L1. Justified by the distribution across the
350 events that have objects but no references:

| Nodes if seeded (objects + attribute children) | Events | Total nodes |
|---|---|---|
| ≤100 | 306 | 6,786 |
| 101–1k | 36 | 8,880 |
| 1k–5k | 5 | 11,772 |
| **>5k** | **3** | **523,677** |

342 of 350 events cost under 1,000 nodes; three events account for half a million. A single
threshold separates them with almost nothing in between.

**Event-level attributes are never seeded without a relationship**, and the asymmetry is
principled rather than convenient: an object is a cluster with a template type and named
children, so "12 file objects and 3 url objects" conveys the event's composition at a glance. A
bare event-level attribute has no structure — drawn with no relationship it is an isolated dot
conveying strictly less than the dock's table row, which shows the full value instead of
truncating at 42 characters. The asymmetry is also load-bearing: 80% of all attributes are
event-level, so seeding those means seeding the whole event again.

> **Governing principle:** the graph draws things with **structure or relationships**; the table
> draws things that are **just values**.

#### D11 — The empty-graph case is explicit ✅ SETTLED (consequence of D5′ + D9 + D12)

An event with genuinely nothing to draw at any resolution level opens with a statement and an
action, not a blank canvas: *"No relationships in this event — 5,629 correlations available"* plus
a button. This replaces today's silent blank, which four of the six largest events produce.

Note that D12 **demotes this message considerably**: it now fires only when L0, L1 and L2 are all
empty, rather than whenever the event is large. A behemoth gets L0's aggregated view instead.

#### D1 — Two edge dimensions, five kinds, plus feed/server nodes ✅ SETTLED

Edges carry **two orthogonal dimensions**, because "show me only analyst relationships" and "show
me only `communicates-with`" are different questions:

| Dimension | Meaning | Values | Control |
|---|---|---|---|
| `kind` | how the edge came to exist | 5 | `multiselect` edge facet + legend section (the layer switch) |
| `relationship_type` | what it asserts | ~143 | `text`/`regex` edge facet |

`relationship_type` is deliberately **not** a `multiselect`: object references alone use 143
distinct values (`analysed-with` 6,968, then a long tail through `opened`, `includes`,
`communicates-with`, `child-of`, `calls`). A 143-row dropdown is unusable, and the library already
concedes the pattern — its table row filters switch from a dropdown to a text box past 50 distinct
values. A substring box ("everything `*-of`") is the usable form.

**The five `kind` values:**

| `kind` | Source | In payload? |
|---|---|---|
| `object-reference` | `obj.ObjectReference` | yes |
| `analyst-relationship` | `.Relationship[]` + `.RelationshipInbound[]` | yes |
| `correlation` | `RelatedAttribute` | **no** — on demand (D9) |
| `feed-correlation` | `attribute.Feed[]` | **yes, free** (`includeFeedCorrelations = 1` unconditionally, `EventsController.php:1857`) |
| `server-correlation` | `attribute.Server[]` | no — needs `includeServerCorrelations:1` (forced to 0 for REST, `:1864-1866`) |

**Inbound analyst relationships share the `analyst-relationship` kind.** The graph is
`isDirected: true`, so the arrowhead already carries which way the assertion points; a separate
layer would double the vocabulary to say what the arrow says. The "another org asserted this about
my data" signal is carried in the sidebar panel instead.

**Two new node types: `feed` and `server`.** Reversing an earlier recommendation to exclude them —
the exclusion rested on a misreading. The `$isSiteAdmin` guard at `Event.php:3374` is on the
**ShadowAttribute** branch; the Attribute path (`:3398`) has no such check. The real gate is a
dedicated role permission, **`perm_view_feed_correlations`** (`Feed.php:521-522`), so ordinary
users do see these correlations and they belong on the canvas.

They are also cheap. `attachFeedCorrelations` attaches per-attribute hits to
`attribute['Feed'][]` / `attribute['Server'][]` **and** a deduplicated source map to
`event['Feed'][id]` / `event['Server'][id]` — so it is one node per feed or server, not one per
correlation, exactly as `RelatedEvent` is for events.

Three constraints the implementation must honour (§7 carries the edge cases):

- **A built-in degraded mode already exists.** Past 10,000 hits without `overrideLimit`, the
  sources are dropped and you get `attribute['FeedHit'] = true` plus `event['FeedCount']`
  (`Feed.php:604-611`). This is precisely the backend aggregation pattern requested for objects —
  already implemented here. The graph must render both shapes.
- **Server fields are restricted.** A non-site-admin outside the host org sees only `id` and
  `name` on a `Server` source (`Feed.php:613-620`), and server event-UUID hits are withheld
  entirely (`:648-652`).
- Containment stays **nesting**, not an edge. `tag`/`galaxy` edges remain out of scope.

#### D2 — Channel allocation; the "pending" state is removed ✅ SETTLED

A node can only look so many ways, and v1.6.0 took one of the channels MISP was using. Selection
now draws on the node's **rim** ("keeps its own `color` and takes the selection colour on its
rim"), where highlight already lived — so the rim has four claimants, two of them library-owned
and overriding: selection, highlight, MISP's pending-reference ring, and MISP's image-node frame.

**Final allocation:**

| What it says | Channel |
|---|---|
| what kind of thing it is (attribute / object / event / feed / server) | fill `color`, `shape`, `size` |
| which attribute or object type | `iconClass` (misp-iconify) |
| attachment preview | `imagePath` |
| **an analyst has commented here** | **one folded badge** — count as `text`, colour as sentiment |
| from another event | **undecided — see D2c** |
| selected / hovered | **rim — left entirely to the library** |

**Provenance is binary** wherever it is drawn. There are four ways to be foreign — extended
event, correlated event, feed, server — but feed, server and event-proxy nodes already announce
themselves through node *type* (own shape and fill). The only ambiguous node is an attribute or
object that looks identical to this event's but is not, so the cue need only say mine / not-mine.

The *channel* for it is **not** settled — an earlier revision of this document recorded
saturation as decided, which over-read the review. It is now D2c.

**Notes and opinions fold into one badge.** They are the same message to an analyst — *somebody
has commented on this* — and one badge carries both: the note count as its text, endorsed /
disputed / neutral as its colour. This matters because only **two** badge corners are free on an
expandable node (both East corners are reserved for the expand affordance) and object nodes are
expandable.

**The "pending / not yet saved" state is removed entirely**, and its `styleCb` ring
(`:414-419`) is deleted. Rationale: putting an attribute on the canvas is a **view write** in
§2.2's taxonomy — the attribute was always in the event, and showing it changes nothing in MISP.
Only the edge is a save. The old pending ring existed because the previous design treated a
dragged-in node as half-created; it never was.

Two things fall out for free:

- **A bug in today's code disappears.** The pending ring is invisible whenever the node is
  selected — and a freshly dropped node is selected on drop, which is exactly when "unsaved"
  matters. Deleting the concept removes the collision rather than working around it.
- **The badge budget drops to one**, comfortable even on expandable nodes.

#### D2b — Which link kind a drawn edge becomes ✅ SETTLED

Drawing an edge can mean two different writes, and the constraints do **not** always pick one.

- An object reference's **source is always an object**: `ObjectReferencesController::add()`
  resolves it strictly in the `Object` table (`Object.uuid`/`Object.id`, `deleted = 0`) and throws
  `Invalid object.` otherwise. Its *target* may be an attribute (`referenced_type 0`) or an object
  (`1`). It is intra-event by construction.
- An analyst relationship's endpoints may be any of `AnalystData::valid_targets` — Attribute,
  Event, EventReport, GalaxyCluster, Galaxy, Object, Note, Opinion, Relationship, Organisation,
  SharingGroup — and **not** Feed or Server.

| Source → Target | Possible link |
|---|---|
| object → object/attribute, **same event** | **either kind — ambiguous** |
| attribute → anything | analyst relationship only (an attribute cannot be a reference source) |
| anything → node in another event | analyst relationship only |
| anything → feed / server | **nothing** — not a valid analyst target either |

Only the first row is undecidable from the endpoints, and it is the most common gesture on this
page.

**Resolution: narrow first, ask only when genuinely ambiguous.** One function answers "what could
this edge be", from the endpoints *and* the user's rights:

```js
function possibleKinds(source, target) {
    const s = source.getData?.() || {}, t = target.getData?.() || {};
    const kinds = [];
    // an object reference's source is ALWAYS an object, and it is intra-event
    if (canEdit && s.type === 'object'
        && (t.type === 'object' || t.type === 'attribute')
        && s.scope === 'self' && t.scope === 'self') {
        kinds.push('object-reference');
    }
    // feed/server are not in AnalystData::valid_targets
    if (permAnalystData && t.type !== 'feed' && t.type !== 'server') {
        kinds.push('analyst-relationship');
    }
    return kinds;
}
```

| `possibleKinds()` | Behaviour |
|---|---|
| 0 | `isValidConnection` returns `false` — the target is marked invalid live during the drag, and `onBeforeEdgeCreate` is never consulted |
| 1 | no link-type question; the form asks only for the relationship type |
| 2 | the form carries a **pre-filled link type**, defaulted to `object-reference` |

The extra field therefore appears only where both writes are genuinely available — which for a
user without event edit rights is **never**. This cannot produce an impossible write (unlike
"always an object reference"), does not make identical gestures mean different things for
different users (unlike inferring from rights alone), and does not hide the decision in rail-mode
state.

**The two kinds do not share a vocabulary**, which decides the form's shape:

- **Object references** draw on `object_relationships` — **262 rows** on the dev instance, so it
  is populated and authoritative. The module's hardcoded `DEFAULT_RELATIONSHIPS` (25 entries,
  `:34`)
  is a stale fallback.
- **Analyst relationships** take free text (`relationship_type varchar(255)`, no vocabulary).

So the relationship-type field depends on the chosen link type, which the declarative
`promptData({ fields })` form cannot express. The two-kind case needs `promptData`'s custom
variant (`render` + `getValues`); the one-kind case can stay declarative.

**A latent stored-XSS is removed on the way.** The current picker builds its `<option>` list by
string concatenation into `innerHTML` (`:847-850`). It is fed from the hardcoded 25-entry array
today, so nothing is exploitable — but `object_relationships` contains a row literally named
`<script>alert('name')</script>`, so wiring the real vocabulary into that builder would introduce
stored XSS. `ctx.promptData()` removes the sink, and v1.5.0's rule that a consumer `string`
renders as text rather than markup means the library will not reintroduce it.

`isValidConnection` **rejects feed and server nodes as targets outright** — no persistable edge
can point at them.

#### D3 — Two legend sections; provenance is not one of them ✅ SETTLED

**Two** sections, not three:

| Section | Keys on | Rows |
|---|---|---|
| `Element` | the `render.nodeTypeAccessor` dimension (neither `key` nor `entries`) | 5 — attribute, object, event, feed, server |
| `Relationship` | `scope: 'edge'`, `key: 'kind'` | 5 — the D1 kinds |

Sections AND together, so "attributes, correlated" is expressible. The `Relationship` section
names the same key as the `edgeFacets` declaration, so panel and legend are two views of one
filter, and it is the affordance that switches the correlation layer on once D9's fetch has run.

**Provenance is deliberately excluded.** The legend is *descriptive* — it samples the colour the
renderer resolved and reports it. Provenance is not encoded in colour (D2c), so any provenance
section would have to invent swatches that appear nowhere on the canvas, and the library warns
about exactly this case. A legend row that disagrees with the canvas is worse than no row.

Provenance filtering instead lives in the **filter panel** as an ordinary `scope` node facet — a
checkbox, which is the right control for a dimension with no colour. Nothing is lost: a facet
there filters exactly as a legend row would.

Rejected alternative worth recording: swatches showing the same hue at two saturations, so the
swatch demonstrates the encoding. It only tells the truth for one node kind — a saturated and
desaturated orange keys it to attributes, while objects are blue and feeds different again.

Corner budget is fine: the legend defaults to `bottom-left`, the minimap to `bottom-right`
(`Minimap.ts:113`), so they do not collide.

#### D2c — Provenance has no canvas encoding ✅ SETTLED

Foreign nodes are **not** dimmed, tinted or otherwise marked. Provenance lives in the sidebar
panel, the dock table's column, and the `scope` filter facet (D3) — nothing on the canvas.

**Rationale, and it is the important part:** fading is not a neutral cue, it encodes a *judgment*
that this event is the subject. An analyst may legitimately pivot outward and make the foreign
material the focus — following a correlation into a campaign, treating this event as one exhibit
among several — and a baked-in fade fights that the whole way. A filter facet is symmetric: it
isolates `self` or `foreign` with equal ease, and lets the analyst declare what the subject is
instead of the renderer assuming it.

Rejected: **(a)** fading (asymmetric, per above) and **(c)** foreign variants of each node type
(`attribute (other event)`, …), which would make provenance legend-describable but multiplies the
type space and makes the `Element` section do two jobs.

**Consequence — the chrome becomes the only "you are here".** With no canvas encoding, nothing
inside the graph says which event seeded it. That is tolerable on `/events/view2`, where the
surrounding page identifies the event, but not once the same component is mounted from a pivot
route (§11) with no event page around it. So the component carries its own header, via
`UI.mainHeader.render` or the card header:

```
Event 1234 · <info> · <orgc> · <date>
seeded L0+L1 · 86 nodes · L2 skipped (28,410 objects not shown) · 5,629 correlations available
```

The second line is not decoration: D11 and D12 both *require* the graph to state which resolution
levels it seeded and what it left out, and this is where that statement lives.

#### D4 — "Unlinked attributes" becomes search + a server-paged table ✅ SETTLED

Today's sidebar panel titled **"Unlinked attributes"** (`:887`) lists every attribute and object
not on the canvas as draggable chips. It is doing two jobs, and they scale differently:

- *getting one specific element onto the canvas* — has to live inside the graph, and is the only
  thing the panel uniquely provides;
- *browsing the event's contents* — which the event page's own attribute table already does
  properly, with paging, sorting and filtering.

**Resolution:** the pane keeps a **search box at every size** — type `evil.com`, get a handful of
matches, click to add to the canvas — and adapts its listing to the event:

| Event size | Listing |
|---|---|
| short / medium | the full list, as today |
| large | a **server-paged, sortable** table |

Search is bounded by construction and works identically on a 20-attribute event and a
370,000-attribute one, which is what makes it the primitive rather than the list.

**The listing is server-backed, not paged in the browser.** MISP already provides it twice:
`EventsController::viewEventAttributes($id, $all)` (ACL `*`, `ACLComponent.php:441`) — the
endpoint the event page's own table uses — and `AttributesController::index()`, which accepts
`page`, `limit`, `sort`, `direction` alongside `value`/`type`/`category`/`uuid` filters
(`:104`). Neither needs building.

The library's own table goes in the drawer beside it and shows **what is in the graph** — a small
set after the seed rule — with its `Visibility` column explaining what is hidden and why. The two
panes have different scopes on purpose: yours is the event, the library's is the canvas.

Rejected: leaving the panel in the sidebar (a narrow column for long attribute values, and it
splits two differently-scoped lists across two places), and deleting it outright in favour of the
page's table (which would remove the only in-graph route to adding an element).

It mounts via `UIManager.addDockTab()` — a wide horizontal region suits long attribute values far
better than a narrow sidebar column — and the sidebar keeps the analyst-data panel (§6.2).

**Why this is load-bearing rather than cosmetic:** the graph now seeds on relationships and a
budget, so for the ~92% of events with no authored relationship, and for every event above 1,500
nodes, this pane is the *only* in-graph route to the event's contents.

#### D13 — A dedicated seed endpoint, in its own PRD ✅ SETTLED

Exposed by §3.5's payload measurement: every budget in this document caps something *downstream*
— D12 the canvas, D9 the correlation fetch, D4 the listing — while the opening fetch stays
unbounded. `/events/view/4116.json` is ~100 MB and the graph draws 86 nodes from it. Once D4's
pane is server-paged, **nothing in the design needs the full payload in memory**.

**Resolution: build a dedicated graph endpoint returning `{nodes, edges, meta}`** — the server
knows which elements carry relationships and can answer in kilobytes what now costs 100 MB. It
also collapses two other items: the dedicated correlation endpoint (§11) and the client-side
graph construction that is the bulk of `pivot-explorer.js`.

**Deferred to its own document:** [`pivot-explorer-graph-endpoint-prd.md`](pivot-explorer-graph-endpoint-prd.md).
It is a new endpoint with its own ACL, sharing-group filtering and test surface, whereas every
other decision here is implementable against the existing payload. So this PRD ships against
`/events/view/{id}.json` unchanged, and **knowingly accepts that large events stay slow to
open** until the endpoint lands.

Rejected: named parameters to trim `fetchEvent` — it grows an already-large option surface for a
partial win, and would still ship the whole event's tags and analyst data.

#### D6 — Deletion removes relationships, never elements ✅ SETTLED

D6's original content — `onBeforeEdgeCreate`, `isValidConnection`, `editors.*.enabled` — was
absorbed into **D2b** with more detail. What remained was the one unexamined part of the write
path, and the riskiest: deletion. Pivotick puts Delete in the bulk-action row **directly beside
Hide**, and the two mean entirely different things.

**Resolution:**

- **Deleting an edge deletes the underlying relationship** — the object reference or the analyst
  relationship — which is the exact inverse of what D2b creates.
- **Deleting a node is not offered.** `editors.deletion.enabled` is a single flag and cannot
  distinguish nodes from edges, so node deletes are **vetoed in `onBeforeDelete`** with an
  explanation.
- **Every edge deletion goes behind `ctx.confirm()`**, naming the specific relationship.

Rationale for refusing node deletion:

- Remove-from-canvas is already **Hide**. Two adjacent buttons where one is reversible and the
  other soft-deletes event data is an accident waiting to happen.
- Deleting an attribute that is a cluster child *inside* an object is a different operation from
  deleting the object; `cascadingEdges` makes that ambiguity visible rather than resolving it.
- MISP's event view already deletes attributes properly, with context and confirmation. The graph
  does not need to be a second, worse place to do something destructive.

`DeleteContext` gives `{ nodes, edges, notes, cascadingEdges, origin, confirm }`, with `edges` and
`cascadingEdges` guaranteed not to overlap — so each deletion can be persisted exactly once. With
node deletion vetoed, `cascadingEdges` is always empty here.

#### D7 — Physics opts into `'auto'` ✅ SETTLED (revises the original recommendation)

Keep `d3LinkDistance: 200` **and** add `physics: 'auto'`.

The original D7 said stay manual so that "layout does not change under this release". **D12
invalidated that reasoning:** the seed rule means the canvas is 86 nodes, or 20, or 1,500
containment-only clusters depending on the event, so layout was never going to stay unchanged, and
one hand-tuned link distance cannot suit that range. Auto re-tunes from node count, node sizes and
canvas size and keeps doing so as the graph changes — precisely the variability D12 introduced.

Setting both is explicitly legal: the explicit d3 values **seed the opening frame** and auto takes
over from there. So the first paint is the layout that exists today, and it adapts afterwards
rather than staying tuned for a graph shape that no longer occurs.

Note this supersedes the §3.3 audit line that inferred physics would stay `'manual'` — that was
true of the code as it stands, not of the code this PRD produces.

## 6. Detailed Design

### 6.1 Relationship layers and the seed rule (D1, D5′)

`buildGraphData` produces two edge kinds at load and a third on demand:

```js
// object references — existing, now tagged
edges.push({ from: objId, to: refId,
             data: { kind: 'object-reference', label: rel } });

// analyst relationships — from attr/obj .Relationship[] (+ .RelationshipInbound[])
edges.push({ from: srcId, to: dstId,
             data: { kind: 'analyst-relationship', label: r.relationship_type,
                     authors: r.authors, orgc: r.orgc_uuid } });

// correlations — added later, by §6.7's second request
```

Seed membership follows D12's resolution levels, taking the highest that fits the 1,500-node
budget:

```
L0  always      event node + one proxy node per RelatedEvent
L1  always      elements participating in an object reference or analyst relationship
                (+ the attribute children of any object node, as today)
L2  if it fits  objects with no relationship, as containment-only clusters
L3  on demand   per-attribute correlations (§6.7)
```

`computeConnectivity()` generalises from "a reference touches it" to "any authored relationship
touches it", and gains a second pass for L2 that adds remaining objects while the budget holds.
Event-level attributes are never added by L2 — see D10's governing principle.

Feed and server correlations add two node types and two more kinds (D1). Source nodes come from
the deduplicated `event['Feed']` / `event['Server']` maps — one node per source — and the edges
from each attribute's `Feed[]` / `Server[]` hits:

```js
// one node per feed/server, not per correlation
Object.values(ev.Feed || {}).forEach(f => nodes.push({
    id: 'feed:' + f.id, data: { type: 'feed', label: f.name, scope: 'external' } }));

// per-attribute hits; degraded shape carries no sources at all
(attr.Feed || []).forEach(f => edges.push({ from: attrId, to: 'feed:' + f.id,
    data: { kind: 'feed-correlation', label: '' } }));
```

Options:

```js
render: {
    edgeTypeAccessor: e => e.getData()?.kind,
    edgeStyleMap: {
        'object-reference':     { strokeColor: '#428bca' },
        'analyst-relationship': { strokeColor: '#f39a1f', dashed: true },
        'correlation':          { strokeColor: '#888', dashed: true },
        'feed-correlation':     { strokeColor: '#5bc0de', dashed: true },
        'server-correlation':   { strokeColor: '#9b59b6', dashed: true },
    },
},
UI: { filter: { edgeFacets: [
    { key: 'kind',              label: 'Relationship', type: 'multiselect' },
    { key: 'relationship_type', label: 'Asserts',      type: 'text' },
]}},
```

An analyst relationship whose `related_object_uuid` does not resolve to a node is **skipped** —
`getRelatedElement()` returns `[]` for an unresolvable target, and `addEdge` already refuses an
edge with a missing endpoint (`:277`). Count the skips and report them in the panel (§7).

### 6.2 Analyst-data badges and panel

Notes and opinions fold into **one** badge (D2): the count as its text, the sentiment as its
colour.

```js
badges: node => {
    const a = node.getData()?.analyst;
    if (!a || !a.count) return [];              // [] is how a node says it wears none
    return [{
        position: 'nw',
        text:  String(a.count),                 // >3 chars renders as 99+
        color: a.mood === 'disputed' ? '#b94a48'
             : a.mood === 'endorsed' ? '#6fbe80' : '#999',
        title: a.count + ' notes/opinions — ' + a.mood,
    }];
}
```

`'nw'` deliberately: on an expandable node **both East corners are reserved** for the expand
affordance (north-east collapsed, south-east expanded), and object nodes are expandable. Folding
the two facts into one badge leaves the second free corner genuinely spare.

Badges do not aggregate children, so an object's count must walk `node.children` in the `badges`
function if it should include its attributes (§7).

Opinion → `disputed` uses the existing bands (`opinion_scale.ctp:11`): mean `< 41` disputed,
`> 60` endorsed, else neutral. Dev-instance opinion values are strongly bimodal (13 in 0–30 vs
30 in 70–100), so a two-state signal reflects real usage better than a gradient.

Detail goes in a selection-reactive `ExtraPanel` — v1.6.0 re-invokes `render` per selection with
the selected `Node`, which is what makes this panel possible. Content is already in the payload,
so no fetch; a later thread fetch may return a `Promise` and `ctx.signal` cancels a superseded
one. `callbacks.onBadgeClick` selects the node and opens the panel — a badge with no `onClick`
lets its click fall through, so declaring one is required for interactivity.

### 6.3 Sectioned legend (D3)

```js
UI: {
    legend: {
        position: 'bottom-left',
        sections: [
            { title: 'Element' },                                  // nodeTypeAccessor dimension
            { title: 'Relationship', scope: 'edge', key: 'kind' },
        ],
    },
    filter: {
        facets: [{ key: 'scope', label: 'Provenance', type: 'multiselect' }],   // D3
        edgeFacets: [
            { key: 'kind',              label: 'Relationship', type: 'multiselect' },
            { key: 'relationship_type', label: 'Asserts',      type: 'text' },
        ],
    },
}
```

Two sections, not three — provenance is a filter-panel facet rather than a legend section (D3),
because the legend can only sample colour and provenance is not encoded in colour.

Exactly one section may omit both `key` and `entries` (the `nodeTypeAccessor` dimension); a second
is dropped with a warning. The `Relationship` section names the same key as the `edgeFacets`
declaration, so panel and legend become two views of one filter — and it is the affordance that
turns the correlation layer on after §6.7 has fetched it. (`LegendEntry`, if ever declared
explicitly, requires `id` and `color`; `label` defaults to a prettified `id`.)

### 6.4 Event provenance and correlated events (D2)

Every node gains `data.scope` (`'self'` | `'foreign'`) and `data.event_uuid`, derived from the
`event_id` each attribute/object already carries. It drives the `scope` **filter facet**, the
sidebar panel and the dock's column — and **nothing on the canvas** (D2c). With the pending ring
also deleted (D2), `styleCb` has no remaining job on this page and the rim belongs entirely to the
library.

Correlated events (`RelatedEvent`) render as **leaf proxy nodes** of type `event` — the
`nodeStyleMap` already registers a green hexagon for `event` that nothing currently creates —
labelled from `info`/`date`/`org`. They are **not** expandable containers in this phase (§4);
double-click navigates to that event's own `view2`.

Extended events (`extended:1` merges foreign attributes/objects into the same arrays, provenance
in `Event.extensionEvents`) are `scope: 'foreign'` and, unlike correlated events, are real nodes
with real edges. Whether they get a container enclosure is deferred (§11).

### 6.5 Editor tray → dock pane (D4)

`createEditor()` keeps its inventory logic and chip DOM; only the mount changes:

```js
var handle = _graph.UIManager.addDockTab({
    label: 'Event elements', order: 10,
    render:  function () { return panelEl || buildPanel(); },
    toolbar: function () { return buildFilterInput(); },
});
```

`render` is called once, lazily, on first activation, so the pane keeps its scroll position;
`handle.refresh()` re-renders after a chip is staged. Under D5′ the dock is **load-bearing, not
a convenience**: the graph seeds on relationships, so for most events the dock is the only route
to the event's contents. `UI.table` gives a second pane whose `Visibility` column explains where
every element stands — the honest version of what the tray approximated.

The relationship picker overlay is **removed** in favour of `ctx.promptData()` (§6.6).

### 6.6 Write path and the gating model (D6, D8)

```js
editors: {                       // affordance removal, not veto
    edgeEditor:  { enabled: canEdit || permAnalystData },
    nodeCreator: { enabled: canEdit },
    deletion:    { enabled: canEdit },
},
callbacks: {
    // (source: Node | Note, target: Node) => boolean — runs on every pointer move.
    // Cheap: property lookups only. Zero possible kinds ⇒ the target reads invalid.
    isValidConnection: (source, target) => possibleKinds(source, target).length > 0,

    // (context: EdgeCreateContext) => EdgeCreateDecision | Promise<…>  — ONE argument
    onBeforeEdgeCreate: async (ctx) => {
        if (ctx.kind !== 'edge') return true;               // note-links pass through
        const kinds = possibleKinds(ctx.source, ctx.target);
        if (!kinds.length) return false;

        // one kind: declarative form, no link-type question
        // two kinds: custom form, because the vocabulary depends on the choice (D2b)
        const values = kinds.length === 1
            ? await ctx.promptData({ fields: fieldsFor(kinds[0]) })
            : await ctx.promptData({ render: renderLinkTypeForm, getValues });
        if (!values) return false;                          // cancelled → veto

        const kind = values.kind || kinds[0];
        const ok = kind === 'object-reference'
            ? await persistObjectReference(ctx.source, ctx.target, values.relationship_type)
            : await persistAnalystRelationship(ctx.source, ctx.target, values.relationship_type);
        return ok ? { accept: true, data: { kind, label: values.relationship_type } } : false;
    },

    // (context: DeleteContext) => DeleteDecision | Promise<…>            (D6)
    // { nodes, edges, notes, cascadingEdges, origin, confirm }
    onBeforeDelete: async (ctx) => {
        if (ctx.nodes.length) {
            notify('Remove a node from the canvas with Hide. '
                 + 'Attributes and objects are deleted from the event view.');
            return false;                          // node deletion is never offered (D6)
        }
        if (!ctx.edges.length) return false;
        const labels = ctx.edges.map(describeRelationship).join(', ');
        if (!await ctx.confirm({ body: 'Delete ' + labels + '?' })) return false;
        return { edges: await deleteRelationships(ctx.edges) };   // narrow to what persisted
    },
}
```

Physics (D7) — the explicit value seeds the opening frame, auto adapts from there:

```js
simulation: { physics: 'auto', d3LinkDistance: 200 }
```

Four notes:

- **`ctx.promptData()` replaces the bespoke relationship picker**, and removes a latent
  stored-XSS sink on the way — see D2b. `ctx.promptLabel()` is the one-field variant. Net code
  deletion.
- **`isValidConnection` is the single gate**, driven by `possibleKinds()` (D2b). It turns the
  legacy Event Graph's after-the-fact refusal — `can_be_referenced()`'s *"Cannot reference a node
  not belonging in this event"* (`event-graph.js:1684`) — into a cursor state during the drag, and
  it covers the feed/server rejection at the same time. `onBeforeEdgeCreate` is not consulted for
  a target it rejects.
- **`edgeEditor` stays enabled for a user with only `perm_analyst_data`.** They cannot create
  object references, but they can assert analyst relationships (D8), so removing the affordance
  wholesale on `!canEdit` would deny the write MISP permits.
- Programmatic mutation never invokes these hooks, so putting a node on the canvas from the dock
  (`graph.addNode`) is unaffected — and under D2 that is a pure view write with nothing to save.

### 6.7 Payload: two requests (D9)

**Request 1 (load)** — `/events/view/{id}.json`, unchanged. Already carries analyst data and
`RelatedEvent`. Notably it must **not** gain `includeGranularCorrelations:1`: under D9 the
absence of `RelatedAttribute` from the REST default is exactly what is wanted.

**Request 2 (on demand)** — `/events/view/{id}.json/includeGranularCorrelations:1` when the user
asks for correlations, taking `RelatedAttribute` from the response and discarding the rest. Cap
applied here, before nodes are built.

Refetching the whole event to obtain one key is wasteful; a dedicated
`/events/correlations/{id}.json` returning only `RelatedAttribute` is the clean version and is
logged in §11. Reusing the existing named parameter first keeps this phase free of controller
changes.

Two smaller items:

- **Annotation counts** are derived client-side in `attributeNodeData()` / `objectNodeData()`
  from the inline `Note`/`Opinion` arrays. No endpoint work.
- **Inbound analyst relationships** are absent from the bulk path. Phase 1 derives them
  client-side by inverting the outbound set (both endpoints are usually in the same event); a
  fix to `attachAnalystDataBulk` is Phase 2.

## 7. Edge Cases

- **The 92% case.** Most events have no authored relationship, so L1 is empty and the seed rests
  on L0 + L2. For the 306 events costing ≤100 nodes that is a complete picture of the event's
  composition; D11's message now fires only when L0, L1 and L2 are all empty.
- **Event 4116.** 369,822 attributes, 28,410 objects, 0 references, 5,629 correlations. L1 empty,
  L2 does not fit (523,677 nodes across the three monsters), so it seeds at **L0: 86
  correlated-event proxies**. The graph must say that L2 was skipped and why, or the analyst reads
  86 nodes as the whole event.
- **The L2/`hideDisconnected` collision.** A containment-only cluster parent has no edges, so
  `hideDisconnected` treats it as disconnected and flipping *Hide unconnected* blanks an
  L2-seeded canvas. The library deliberately leaves a cluster's *interior* alone but not its
  parent. Not a blocker — but the switch's label and D11's message must both speak about
  **relationships**, not about content, or the behaviour reads as a bug.
- **The dock on a huge event.** Resolved by D4: the pane pages server-side via
  `viewEventAttributes` / `attributes/index` rather than listing an in-memory 398,000 rows.
- **`hideDisconnected` after a correlation fetch.** Turning the correlation layer back off
  strands the foreign nodes it brought in. The View flyout switch is the user's remedy; we should
  not force it on, since it moves the graph.
- **`reapply()` and manual hides.** Re-deriving visibility undoes `graph.hideNode()`. Any code we
  write that hides a node must use `queryEngine.excludeNode()` instead.
- **Badge capacity.** Four on a plain node, two on an expandable one. Object nodes are
  expandable; child aggregation would want three.
- **Unresolvable analyst-relationship targets** — skipped (§6.1); surface a count so a dropped
  assertion is visible rather than silently absent.
- **Deleted records.** `isDeleted()` tombstones attributes/objects/references; analyst data has
  no `deleted` flag, so an analyst relationship can point at a soft-deleted attribute. Treat as
  unresolvable.
- **Cluster stand-in edges** are deduped by node pair and can speak for several kinds; the
  library keeps them alive while any represented edge passes the filter. Nothing to do, but a
  stand-in's style may not match any single layer.
- **Feed correlations in degraded mode.** Past 10,000 hits without `overrideLimit` the sources are
  dropped and the payload carries only `attribute['FeedHit'] = true` and `event['FeedCount']`
  (`Feed.php:604-611`). There is nothing to draw an edge *to* — no feed node exists. Render this as
  a **badge** on the attribute ("in a feed") rather than an edge, and say so at the graph level
  from `FeedCount`. Both shapes must be handled; a big event will hit this.
- **Server fields are restricted.** Non-site-admin users outside the host org get only `id` and
  `name` on a `Server` source (`Feed.php:613-620`), so a server node's label is all there is —
  no tooltip detail, no URL. Server event-UUID hits are withheld entirely (`:648-652`).
- **Server correlations are absent by default.** `includeServerCorrelations` is forced to 0 for
  REST, so the `server-correlation` layer is empty unless the fetch asks for it. Decide whether it
  joins the D9 on-demand request or is simply never shown on this page (§11).
- **Self-referencing analyst relationship** — `Relationship::beforeValidate` rejects
  `object_uuid == related_object_uuid`; guard anyway.

## 8. Test Plan

Manual, on `/events/view2/{id}` against the dev instance (75 notes, 43 opinions, 120 analyst
relationships, and the events in §3.5 as fixtures):

1. **Upgrade regression** — graph renders, objects expand, chips drag in, an edge can be created
   and persists. Gate before any new feature lands.
2. Event 1195 (2,362 refs): seeds with the authored spine; layers toggle independently; layout,
   selection and camera unchanged across a toggle.
3. Event 4116 (0 refs, 5,629 correlations): D11 message appears; correlation fetch is capped;
   the graph is navigable afterwards.
4. An event with references *and* analyst relationships shows both layers distinctly.
5. Badges appear only on elements with analyst data; clicking one opens the panel; clicking a
   node's shape still selects it.
6. Legend: three sections folding independently; two sections filtering AND together;
   `Relationship` section and the panel's `Relationships` section stay in sync.
7. Provenance: an `extended:1` event's foreign nodes are visually identical to local ones (D2c);
   the `scope` facet isolates each set; the header states which event seeded the graph; a
   correlated-event proxy navigates on double-click.
8. Write gating: with `$mayModify` false, no Create affordances in the chrome at all; with it
   true, an invalid connect target is marked during the drag, a vetoed edge leaves no node
   behind, a persisted edge survives reload.
8b. Deletion (D6): deleting an edge confirms, names the relationship, and the deletion survives
   reload; deleting a node is refused with the Hide explanation and removes nothing; a
   multi-selection containing both nodes and edges is refused whole rather than partially applied.
8c. Physics (D7): the opening frame matches today's layout on event 1195, and a 1,500-node L2 seed
   is legibly spaced rather than piled up.
9. Dock: pane lists the event, filter narrows it, scroll position survives a tab switch.
10. An event with zero analyst data renders exactly as before (no badges, no empty sections).

## 9. Implementation Plan (sequential, one commit per task)

| # | Task | Depends on |
|---|---|---|
| 0 | ✅ Bundle to v1.6.0 + compatibility audit | — |
| 1 | Regression pass on the existing graph under v1.6.0 (§8.1); refresh the stale Edit▸Add-edge comment | 0 |
| 2 | ✅ Tag object-reference edges with `kind`; add `edgeTypeAccessor`/`edgeStyleMap`/`edgeFacets` (one layer) | 1 |
| 3 | ✅ Generalise `computeConnectivity()` to any authored relationship; add analyst-relationship edges as a second layer (L1, D5′) | 2 |
| 3b | L0: event node + `RelatedEvent` proxy nodes (free, already in payload) | 2 |
| 3c | L2: budget-capped containment-only objects, with a "skipped, N not shown" statement (D10, D12) | 3, 3b |
| 4 | D11 empty-state message + wiring for the on-demand fetch | 3c |
| 5 | On-demand correlation fetch as a third layer, capped (D9, §6.7) | 4 |
| 5b | `feed`/`server` node types + `feed-correlation` layer (free in payload), incl. the `FeedHit` degraded shape (D1) | 2 |
| 5c | `relationship_type` text facet as the second edge dimension (D1) | 2 |
| 6 | Analyst-data badges + selection-reactive sidebar panel | 1 |
| 7 | Sectioned legend | 3, 5, 6 |
| 8 | `data.scope` facet + header (event identity + resolution statement) + correlated-event proxy nodes (D2c) | 5 |
| 9 | "Unlinked attributes" → dock pane: search box + full list, server-paged table above a size threshold (D4); library `UI.table` as a second pane | 1 |
| 10 | `possibleKinds()` + write path onto `onBeforeEdgeCreate` / `isValidConnection` / `editors.*.enabled`; delete the innerHTML picker and the pending ring (D2, D2b) | 1, 8 |
| 10b | Analyst-relationship persistence (`analystData/add`) as the second write target (D2b) | 10 |
| 10c | `onBeforeDelete`: edge deletion behind `ctx.confirm()`, node deletion vetoed (D6) | 10 |
| 11 | `simulation.physics: 'auto'` alongside `d3LinkDistance: 200` (D7) | 1 |

Tasks 2, 6, 9 and 10 are mutually independent. All seed-related decisions (D5′, D9, D10, D12) are
settled, so tasks 3–5 are unblocked.

**✅ Done (prerequisite, not a task above).** The inline JS is extracted out of the `.ctp` into
`app/webroot/js/pivot-explorer.js`, leaving the element at 117 lines of markup + CSS + config.
The move was behaviour-preserving: the 745 lines of graph and editor logic are byte-identical,
and only the five PHP interpolation points changed shape. Config now arrives via `data-pe-*`
attributes on `#pe-card` (`event-id`, `baseurl`, `can-edit`, and the two translated error
strings), read in a new `boot()` that waits for `DOMContentLoaded` because `assetLoader`
emits the `<script>` ahead of the element's own markup.

Two follow-ups this exposes, neither blocking: the editor's ~15 UI strings (`Filter…`,
`Add relationship`, `Cancel`, `Save`, …) were hardcoded English in the `.ctp` and still are
in the module — they want the same `data-pe-*` treatment to become translatable; and the
`<style>` block (78 lines, still inline under `if ($canEdit)`) is the same extraction again
for CSS.

## 10. Files Touched

| File | Change |
|---|---|
| `app/webroot/js/pivotick.iife.js` | ✅ replaced (v1.6.0) |
| `app/webroot/css/pivotick.css` | ✅ replaced (v1.6.0) |
| `app/View/Themed/Overmind/Elements/Events/View/event_pivot_explorer.ctp` | ✅ trimmed to markup + CSS + `data-pe-*` config (858 → 117 lines) |
| `app/webroot/js/pivot-explorer.js` | ✅ new — all behaviour, extracted from the `.ctp`; all of §6.1–§6.7 lands here |
| `app/Model/Behavior/AnalystDataParentBehavior.php` | Phase 2 only — `RelationshipInbound` in the bulk path |
| `docs/dev/pivot-explorer-v16-prd.md` | this document |

No controller change, no new endpoint, no schema change in Phase 1.

## 11. Open Questions / Phase 2

1. **Object aggregation** (backend). The one dimension with no existing roll-up: "28,410 objects
   → 12,000 file, 8,000 url" as aggregate nodes, so a behemoth's L2 degrades to a summary instead
   of being skipped. Correlations already have their aggregate for free (`RelatedEvent`, D12);
   objects do not. This is the natural successor to D10's ceiling and needs a new endpoint.
2. **Lazy expansion.** `childrenProvider` / a fired `onBeforeNodeExpansion` is the one library
   PRD from the set that did not ship (`prd/misp/async-children-provider.md`, still *Proposed*).
   Until it lands, correlated events cannot expand in place. Highest-value remaining library ask.
3. **Declarative initial filter value** (library ask). `FilterOptions`/`FilterFacet` carry no
   opening value (§3.2). Not needed under D9, but any future default-off layer needs it, and it
   would let a filter apply before the first layout as v1.6.0 intends.
4. **A dedicated correlation endpoint** — folded into D13's graph endpoint
   ([`pivot-explorer-graph-endpoint-prd.md`](pivot-explorer-graph-endpoint-prd.md)) rather than
   built separately; until it lands, §6.7 refetches the event with a named parameter.
5. **The pivot entry point** — a route seeding the same component from one indicator, defaulting
   to Explore. §4 keeps it out of scope; the seed/mode parameterisation is designed for it.
6. **Analyst-data and enrichment write paths** — both gated in §2.2's taxonomy, neither built.
   Analyst assertion is the natural first one, since D8 already says it is offered everywhere.
7. **Dock paging** on very large events (§7).
8. **Extended-event enclosure** — does a foreign event get a container, and can object clusters
   nest inside it three levels deep (event → object → attribute)?
9. **Badge aggregation** over an object's attributes: sum, max, or neither?
10. **Physics `'auto'`** (D7 deferred) — worth an isolated before/after on a large event.
11. **`RelatedAttribute` cost.** Measure on a heavily-correlated event before shipping task 5.
