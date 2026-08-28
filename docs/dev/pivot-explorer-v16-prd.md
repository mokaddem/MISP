# PRD: Pivot Explorer — leveraging Pivotick v1.6.0 on `/events/view2`

**Status:** DRAFT — D1–D4, D6, D7 await sign-off; D5′, D8–D12 settled (D5 withdrawn)
**Owner:** Sami Mokaddem (Claude-assisted)
**Created:** 2026-08-28
**Grilled:** 2026-08-28 — see §5 for what was settled and what changed as a result
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
   graphs. Whether you may write is decided by *what you clicked*, not which page you are on.
2. **The seed is what humans authored** — object references and analyst relationships. These are
   bounded in practice (§3.5) and need no cap.
3. **Correlations load on demand.** They are the most numerous relationship by far, and are
   fetched when asked for rather than shipped in the opening payload.

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

`event_pivot_explorer.ctp` (858 lines) fetches `/events/view/{id}.json` and builds:

| Element | Source | Notes |
|---|---|---|
| Object nodes | `ev.Object` | Only objects `computeConnectivity()` finds connected |
| Attribute nodes | `obj.Attribute` | Nested as cluster **children** of their object (`:324-336`) |
| Event-level attribute nodes | `ev.Attribute` | Only when an object reference points at them (`:307-313`) |
| Edges | `obj.ObjectReference` | The **only** edge kind; label = `relationship_type` |
| Editor tray | `UI.extraPanels` | Unconnected attributes/objects as draggable chips |

Node style is keyed on kind via `nodeStyleMap` — event = green hexagon, object = blue square,
attribute = orange circle (`:367-372`) — with `iconClass` carrying the misp-iconify glyph,
`imagePath` carrying attachment thumbnails, and `styleCb` spending `strokeColor`/`strokeWidth`
on the pending-reference ring (`:395-400`). Every styling channel is committed.

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
- The extra panel's `render` returns an **HTMLElement** (`:834`), so the 1.5.0 "a `string` never
  renders as markup" change does not bite. Its `innerHTML` writes are all to its own DOM.
- `graph.on('edgeAdd', …)` (`:839`) and `simulation.d3LinkDistance` still exist.
- v1.6.0's breaking changes are confined to physics presets. Because the graph **configures**
  `d3LinkDistance: 200` (`:412-413`), physics stays `'manual'` — auto explicitly declines to
  take over a graph that tuned any knob it drives, so layout is unchanged.
- Bundle verified: `node --check` passes, the `window.Pivotick` footer is present, and the
  deployed file is byte-identical (md5 `140ead0d…`) to a fresh `vite build` of the `v1.6.0` tag.

One **user-visible** change to communicate, not fix: 1.5.0 replaced full-mode chrome with the B3
mode rail and removed the `e` "Edit Graph" toggle in favour of **Create mode**. The comment at
`:477-481` describing "pivotick's Edit ▸ Add edge tool" is stale guidance.

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

1. Draw object references and analyst relationships as **distinct, switchable edge layers**, and
   fetch correlations as a third layer **on demand**.
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
- **Writing analyst data from the graph.** Read-only in this phase. The *gating model* (§6.6) is
  built so that adding it later is configuration, not restructuring — but the write path itself
  is Phase 2.
- **Enrichment from the graph** (either variety). The taxonomy in §2.2 exists so the design
  accommodates it; no enrichment call is made here.
- **Backend aggregation of objects.** A behemoth's L2 is *skipped*, not summarised. The
  correlation aggregate already exists (`RelatedEvent`); the object one does not, and building it
  is new endpoint work. See §11.
- **Analyst-data threads.** Flat list in the sidebar panel; no nested renderer.
- Relationship targets outside the graph's node universe (Galaxy, Organisation, SharingGroup).
- Retiring the legacy `/events/view_graph`, or touching `EventGraphTool` / `getEventGraph*`.

## 5. Design Decisions

### Settled in review (2026-08-28)

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

### Open — sign-off requested

#### D1 — Edge `kind` vocabulary

Proposed `edge.data.kind` values: `object-reference`, `analyst-relationship`, `correlation`.
Object/attribute containment stays **nesting**, not an edge. `tag`/`galaxy` edges out of scope.

#### D2 — Which channel carries event provenance

`color`/`shape`/`size` are spent on node kind and `strokeColor` on pending state, so provenance
goes on **desaturation** (and, where warranted, a container). **Badges are reserved for analyst
data** — provenance would badge every foreign node, and only two corners are free on an
expandable node anyway (both East corners are reserved for the expand affordance).

Consequence: a provenance legend section is keyed on a dimension the colours do **not** encode,
so it must declare `entries` with explicit `color` values or it takes the first node's colour
and warns.

#### D3 — Legend sections

Three: `Element` (node kind, the `nodeTypeAccessor` dimension), `Provenance` (declared
`entries`, per D2), `Relationship` (`scope: 'edge'`). Sections AND together — "attributes, in
this event, that are correlated".

#### D4 — Editor tray → dock pane

Move the unconnected-element tray from `UI.extraPanels` to `UIManager.addDockTab()`. The dock's
intended loop — *table to find, canvas to understand, sidebar to read* — is the tray's job, and
it is a wide horizontal region rather than a narrow sidebar column, which suits a list of
attribute values. The sidebar keeps the analyst-data panel (§6.2).

**D5′ raises the stakes:** the graph now seeds on relationships, so for the ~92% of events with
no authored relationship the dock is the *only* route to the event's contents. It stops being a
convenience and becomes the primary navigation surface — which also means §7's dock-paging
concern is on the critical path, not a nicety.

#### D6 — Write path onto the before-hooks

Replace `graph.on('edgeAdd', onEdgeAdd)` (`:839`) with `onBeforeEdgeCreate` (veto and narrow
before anything is added) plus `isValidConnection` (mark an invalid target live during the drag).
Additionally `editors.*.enabled` ← `$mayModify`, per §6.6.

#### D7 — Physics: stay manual

Keep `d3LinkDistance: 200` so physics stays `'manual'` and layout does not change under this
release. Opting into `'auto'` is a separate isolated experiment.

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

Options:

```js
render: {
    edgeTypeAccessor: e => e.getData()?.kind,
    edgeStyleMap: {
        'object-reference':     { strokeColor: '#428bca' },
        'analyst-relationship': { strokeColor: '#f39a1f', dashed: true },
        'correlation':          { strokeColor: '#888', dashed: true },
    },
},
UI: { filter: { edgeFacets: [{ key: 'kind', label: 'Relationship' }] } },
```

An analyst relationship whose `related_object_uuid` does not resolve to a node is **skipped** —
`getRelatedElement()` returns `[]` for an unresolvable target, and `addEdge` already refuses an
edge with a missing endpoint (`:277`). Count the skips and report them in the panel (§7).

### 6.2 Analyst-data badges and panel

```js
badges: node => {
    const d = node.getData();
    const out = [];
    if (d.noteCount) out.push({ position: 'nw', text: String(d.noteCount),
                                color: '#6fbe80', title: d.noteCount + ' notes' });
    if (d.disputed)  out.push({ position: 'sw', iconClass: '…exclamation',
                                color: '#b94a48', title: 'Disputed' });
    return out;
}
```

`'nw'`/`'sw'` deliberately: on an expandable node **both East corners are reserved** for the
expand affordance (north-east collapsed, south-east expanded), and object nodes are expandable.
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
            { title: 'Provenance', key: 'scope', entries: [
                { id: 'self',    label: 'This event',  color: '#f39a1f' },
                { id: 'foreign', label: 'Other event', color: '#6b6b6b' },
            ]},
            { title: 'Relationship', scope: 'edge', key: 'kind' },
        ],
    },
}
```

`LegendEntry` requires `id` (the toggle key and the value written to the filter) and `color`;
`label` defaults to a prettified `id`. Exactly one section may omit both `key` and `entries` (the
`nodeTypeAccessor` dimension); a second is dropped with a warning. The `Relationship` section
names the same key as the `edgeFacets` declaration, so panel and legend become two views of one
filter — and it is the affordance that turns the correlation layer on after §6.7 has fetched it.

### 6.4 Event provenance and correlated events (D2)

Every node gains `data.scope` (`'self'` | `'foreign'`) and `data.event_uuid`, derived from the
`event_id` each attribute/object already carries. `styleCb` desaturates `foreign` — and because
`styleCb` already returns the pending ring, provenance must be merged into the same return value
(`styleCb` wins outright over the style maps rather than merging).

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
    edgeEditor:  { enabled: canEdit },
    nodeCreator: { enabled: canEdit },
    deletion:    { enabled: canEdit },
},
callbacks: {
    // (source: Node | Note, target: Node) => boolean — runs on every pointer move
    isValidConnection: (source, target) =>
        source.getData?.()?.type === 'object' && target.getData?.()?.scope === 'self',

    // (context: EdgeCreateContext) => EdgeCreateDecision | Promise<…>  — ONE argument
    onBeforeEdgeCreate: async (ctx) => {
        if (ctx.kind !== 'edge') return true;               // note-links pass through
        const values = await ctx.promptData({ fields: [
            { name: 'relationship_type', type: 'select',
              label: 'Relationship', options: DEFAULT_RELATIONSHIPS },
        ]});
        if (!values) return false;                          // cancelled → veto
        const ok = await persistObjectReference(ctx.source, ctx.target,
                                               values.relationship_type);
        return ok ? { accept: true, data: { kind: 'object-reference',
                                            label: values.relationship_type } }
                  : false;
    },
}
```

Three notes:

- **`ctx.promptData()` replaces the bespoke relationship picker.** The overlay built today — a
  backdrop plus a `<select>` assembled with `innerHTML` (`:793-795`) — is what
  `promptData({ fields })` provides, with the shadow-edge preview held up while it is open.
  `ctx.promptLabel()` is the one-field variant. This deletes code rather than adding it.
- `isValidConnection` encodes both halves of D8's rule — object source, `scope === 'self'`
  target — as a live affordance, which is the legacy Event Graph's `can_be_referenced()` refusal
  (`event-graph.js:1684`) turned from an after-the-fact error into a cursor state.
  `onBeforeEdgeCreate` is not consulted for a target it rejects.
- Programmatic mutation never invokes these hooks, so the drag-in staging path (`graph.addNode`)
  is unaffected.

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
- **The dock on a huge event.** It lists everything, so on event 4116 that's a 398,000-row
  table. Needs paging or a server-side listing; the graph's seed rule does not protect it.
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
7. Provenance: an `extended:1` event shows desaturated foreign nodes; a correlated-event proxy
   navigates on double-click.
8. Write gating: with `$mayModify` false, no Create affordances in the chrome at all; with it
   true, an invalid connect target is marked during the drag, a vetoed edge leaves no node
   behind, a persisted edge survives reload.
9. Dock: pane lists the event, filter narrows it, scroll position survives a tab switch.
10. An event with zero analyst data renders exactly as before (no badges, no empty sections).

## 9. Implementation Plan (sequential, one commit per task)

| # | Task | Depends on |
|---|---|---|
| 0 | ✅ Bundle to v1.6.0 + compatibility audit | — |
| 1 | Regression pass on the existing graph under v1.6.0 (§8.1); refresh the stale Edit▸Add-edge comment | 0 |
| 2 | Tag object-reference edges with `kind`; add `edgeTypeAccessor`/`edgeStyleMap`/`edgeFacets` (one layer, no behaviour change) | 1 |
| 3 | Generalise `computeConnectivity()` to any authored relationship; add analyst-relationship edges as a second layer (L1, D5′) | 2 |
| 3b | L0: event node + `RelatedEvent` proxy nodes (free, already in payload) | 2 |
| 3c | L2: budget-capped containment-only objects, with a "skipped, N not shown" statement (D10, D12) | 3, 3b |
| 4 | D11 empty-state message + wiring for the on-demand fetch | 3c |
| 5 | On-demand correlation fetch as a third layer, capped (D9, §6.7) | 4 |
| 6 | Analyst-data badges + selection-reactive sidebar panel | 1 |
| 7 | Sectioned legend | 3, 5, 6 |
| 8 | `data.scope` + desaturation + correlated-event proxy nodes | 5 |
| 9 | Editor tray → dock pane; enable `UI.table` | 1 |
| 10 | Write path onto `onBeforeEdgeCreate` + `isValidConnection` + `editors.*.enabled` | 1 |

Tasks 2, 6, 9 and 10 are mutually independent. All seed-related decisions (D5′, D9, D10, D12) are
settled, so tasks 3–5 are unblocked.

Extraction of the inline JS out of the `.ctp` into a real asset file is not a task above, but
should be considered before task 3 — the file is 858 lines and every task adds to it.

## 10. Files Touched

| File | Change |
|---|---|
| `app/webroot/js/pivotick.iife.js` | ✅ replaced (v1.6.0) |
| `app/webroot/css/pivotick.css` | ✅ replaced (v1.6.0) |
| `app/View/Themed/Overmind/Elements/Events/View/event_pivot_explorer.ctp` | all of §6.1–§6.7 |
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
4. **A dedicated correlation endpoint** — `/events/correlations/{id}.json` returning only
   `RelatedAttribute`, instead of refetching the whole event with a named parameter (§6.7).
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
