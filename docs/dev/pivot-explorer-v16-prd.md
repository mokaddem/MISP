# PRD: Pivot Explorer — leveraging Pivotick v1.6.0 on `/events/view2`

**Status:** DRAFT — awaiting review/sign-off on §5 decisions
**Owner:** Sami Mokaddem (Claude-assisted)
**Created:** 2026-08-28
**Working dir:** /home/sami/git/MISP
**Branch:** `worktree-pivotick-v16` off `develop` (bundle upgrade already committed there)
**Library:** Pivotick v1.6.0 (`app/webroot/js/pivotick.iife.js`, `app/webroot/css/pivotick.css`)

---

## 1. Summary

Pivotick v1.6.0 closes three of the four library gaps that were blocking the Pivot Explorer
from representing what MISP actually holds: **edges now come in kinds that can be styled and
switched off**, **nodes carry rim badges**, and **the legend keys several dimensions at once**.
A data dock, a real panel lifecycle, async content renderers and a full set of before-write
hooks landed alongside them.

This PRD is the implementation plan for spending that on the Pivot Explorer graph in
`app/View/Themed/Overmind/Elements/Events/View/event_pivot_explorer.ctp`, as rendered by
`/events/view2`. It covers five capabilities the graph does not have today — relationship
layers, analyst-data indicators, a multi-dimension legend, event provenance, and a dock-based
element browser — plus moving the existing write path onto the new hooks.

The one gap that did **not** close is lazy cluster expansion (`childrenProvider` /
`onBeforeNodeExpansion`), which is what a fully merged correlation-plus-event graph needs. §4
scopes that out and §11 keeps it on the list.

## 2. Background & Motivation

Two design threads fed this:

1. **Representing analyst data** (notes, opinions, analyst relationships) in the graph. The
   conclusion was that analyst *relationships* are edges, *opinions* are node decorations, and
   *notes* belong in a panel with an opt-in pin-to-canvas — but the graph had no spare visual
   channel and no way to switch a relation layer off.
2. **Merging the legacy Correlation Graph and Event Graph** into one view. The conclusion was
   that containment should mean membership, with the current event as the uncontained ambient
   canvas — but there was no way to name more than one encoding in the legend, and no lazy
   expansion for correlated events.

Both were library-blocked. Three of the four blockers are now gone.

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

Deliberately **not** drawn: correlations, analyst relationships, analyst notes/opinions, event
provenance, and anything from another event.

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
| Panel lifecycle | `ExtraPanel` re-invoked per selection; `id`/`order`/`reactive` | CHANGELOG §*Sidebar panels* |
| Async content | every content hook may return a `Promise`; `RenderContext{signal}` | CHANGELOG §*Content renderers* |

### 3.3 Upgrade compatibility audit (v1.4.0 → v1.6.0) — already performed

The bundle MISP shipped was dated 2026-07-23, i.e. **~v1.4.0** (v1.4.0 tagged 07-21, v1.5.0
tagged 07-29). v1.5.0 carries two breaking sections. Audit result: **the existing integration
is unaffected.**

- Uses none of the removed/renamed API: no `UI.selectionMenu`, `graphControls`, `graphToolbar`,
  `graphNaviation`.
- The extra panel's `render` returns an **HTMLElement** (`:834`), so the 1.5.0 "a `string` never
  renders as markup" change does not bite. Its `innerHTML` writes are all to its own DOM.
- `graph.on('edgeAdd', …)` (`:839`) and `simulation.d3LinkDistance` still exist.
- v1.6.0's breaking changes are confined to physics presets. Because the graph **configures**
  `d3LinkDistance: 200` (`:412-413`), physics stays `'manual'` — auto explicitly declines to take
  over a graph that tuned any knob it drives, so layout is unchanged.
- Bundle verified: `node --check` passes, the `window.Pivotick` footer is present, and the
  deployed file is byte-identical (md5 `140ead0d…`) to a fresh `vite build` of the `v1.6.0` tag.

One **user-visible** change to communicate, not fix: 1.5.0 replaced full-mode chrome with the B3
mode rail and removed the `e` "Edit Graph" toggle in favour of **Create mode**. The comment at
`:477-481` describing "pivotick's Edit ▸ Add edge tool" is now stale guidance.

### 3.4 MISP data already available

- **Analyst data is already on the wire.** `/events/view/{id}.json` takes the REST path, which
  sets `includeAnalystData = true` unconditionally (`EventsController.php:1787`). Attributes,
  objects and event reports carry flat `Note` / `Opinion` / `Relationship` arrays plus flattened
  child notes/opinions; the event itself also carries `RelationshipInbound`.
- **`RelatedEvent` is already on the wire** — `includeEventCorrelations` defaults true
  (`Event.php:2953-2954`), with `id, uuid, info, date, threat_level_id, analysis, published,
  distribution, org_id, orgc_id` + `Org`/`Orgc`.
- **`RelatedAttribute` is not**, for REST: `includeGranularCorrelations` is set only when
  `!_isRest()` or the named param is present (`EventsController.php:1858-1862`).
- **Inbound analyst relationships are missing on attributes/objects** — the bulk path
  (`attachAnalystDataBulk`) does not add `RelationshipInbound`; only the event level gets it.

### 3.5 Gap statement

The graph draws one relation kind out of at least four, gives no indication that an element
carries analyst data, names one of its encodings, and cannot say which event a node belongs to
— while the data for all of it is already in the response it fetches, and the library now has
the channels to show it.

## 4. Goals / Non-Goals

### Goals

1. Draw correlations and analyst relationships as **distinct, individually switchable edge
   layers** alongside object references.
2. Indicate analyst data (note count, endorsed/disputed) with **rim badges**, detail in a
   selection-reactive sidebar panel.
3. Name every encoding in a **sectioned legend** that doubles as the layer control.
4. Show **event provenance** — which nodes belong to the event being viewed and which do not.
5. Replace the bespoke editor tray with a **dock pane**, and let `hideDisconnected` do the
   orphan-hiding the server-side pre-filter does today.
6. Move edge creation onto **`onBeforeEdgeCreate` + `isValidConnection`**.

### Non-Goals

- **Lazy expansion of correlated events.** Needs `childrenProvider` / a fired
  `onBeforeNodeExpansion`, which v1.6.0 did not ship. Correlated events appear as leaf proxy
  nodes only (§6.4), not as expandable containers.
- **Writing analyst data from the graph.** Read-only. Analyst data has its own ACL,
  distribution and `locked` semantics; a wrong write is worse than no write.
- **Analyst-data threads.** Notes on notes / opinions on opinions stay in the sidebar panel's
  flat list; no nested renderer.
- Relationship targets outside the graph's node universe (Galaxy, Organisation, SharingGroup).
- Retiring the legacy `/events/view_graph` page.
- Any change to `EventGraphTool` or the legacy `getEventGraph*` endpoints.

## 5. Design Decisions (sign-off requested)

### D1 — Edge `kind` vocabulary

Proposed values on `edge.data.kind`: `object-reference`, `correlation`, `analyst-relationship`.
Object/attribute containment stays **nesting**, not an edge — it already works and costs no
edge. `tag`/`galaxy` edges are out of scope.

### D2 — Which channel carries event provenance

`color`/`shape`/`size` are spent on node kind and `strokeColor` on pending state, so provenance
goes on **enclosure + saturation**: foreign-event nodes are desaturated, and (where a container
is warranted) grouped in a cluster. **Badges are reserved for analyst data** — provenance would
put a badge on every foreign node, which is noise, and only two corners are free on an
expandable node anyway (both East corners are reserved for the expand affordance).

Consequence for the legend: a provenance section is keyed on a dimension the colours do **not**
encode, so it must declare `entries` with explicit `color` values or it will take the first
node's colour and warn.

### D3 — Legend sections

Three sections: `Element` (node kind, the existing `nodeTypeAccessor` dimension),
`Provenance` (declared `entries`, per D2), `Relationship` (`scope: 'edge'`). Sections AND
together, which is the useful semantic here — "attributes, in this event, that are correlated".

### D4 — Editor tray → dock pane

Move the unconnected-element tray from `UI.extraPanels` to `UIManager.addDockTab()`. The dock's
intended loop — *table to find, canvas to understand, sidebar to read* — is exactly the tray's
job, and the dock is a wide horizontal region rather than a narrow sidebar column, which suits a
list of attribute values. The sidebar keeps the new analyst-data panel (§6.2), and the
relationship picker overlay is **removed** in favour of `ctx.promptData()` (§6.6).

Alternative considered: keep the tray in the sidebar and add the table as a second dock pane.
Rejected because the sidebar then holds two competing lists.

### D5 — Stop pre-filtering unconnected elements server-side

Today `buildGraphData` omits unconnected objects and unreferenced event-level attributes, and
the tray exists to make them reachable. With `UI.filter.hideDisconnected` the graph can carry
**every** element and hide the orphans client-side, with the View flyout's *Hide unconnected*
switch as the way back — and the dock pane then lists the whole event rather than a curated
subset.

Proposal: build all non-deleted attributes/objects into the graph, default
`hideDisconnected: true`. This is the one change in the plan that alters what the canvas shows
on first paint, and it is the one I most want challenged: it trades a smaller graph for an
honest one. Large events are the risk — see §7.

### D6 — Write path onto the before-hooks

Replace the `graph.on('edgeAdd', onEdgeAdd)` post-hoc interception (`:839`) with
`onBeforeEdgeCreate` (veto + narrow before anything is added) plus `isValidConnection` (mark an
invalid target live while connecting). Today an invalid edge is added and then reasoned about;
after this it is never added, and the source-must-be-an-object rule shows up during the drag.

### D7 — Physics: stay manual

Keep `d3LinkDistance: 200`, so physics stays `'manual'` and layout does not change under this
release. Opting into `'auto'` is a separate, isolated experiment — worth doing, not worth
bundling with five other changes.

## 6. Detailed Design

### 6.1 Relationship layers (D1)

`buildGraphData` gains three edge producers, each tagging `data.kind`:

```js
// object references — existing, now tagged
edges.push({ from: objId, to: refId, data: { kind: 'object-reference', label: rel } });

// analyst relationships — from attr/obj .Relationship[] (+ .RelationshipInbound[] at event level)
edges.push({ from: srcId, to: dstId,
             data: { kind: 'analyst-relationship', label: r.relationship_type,
                     authors: r.authors, orgc: r.orgc_uuid } });

// correlations — from RelatedAttribute (needs §6.7)
edges.push({ from: aId, to: bId, data: { kind: 'correlation', label: '' } });
```

Options:

```js
render: {
    edgeTypeAccessor: e => e.getData()?.kind,
    edgeStyleMap: {
        'object-reference':     { strokeColor: '#428bca' },
        'correlation':          { strokeColor: '#888', dashed: true },
        'analyst-relationship': { strokeColor: '#f39a1f', dashed: true },
    },
},
UI: { filter: { edgeFacets: [{ key: 'kind', label: 'Relationship' }] } },
```

An analyst relationship whose `related_object_uuid` does not resolve to a node in the graph is
**skipped** — `getRelatedElement()` returns `[]` for an unresolvable target, and the existing
`addEdge` already refuses an edge with a missing endpoint (`:277`).

### 6.2 Analyst-data badges and panel

Badges read the counts the payload already carries:

```js
badges: node => {
    const d = node.getData();
    const out = [];
    if (d.noteCount)  out.push({ position: 'nw', text: String(d.noteCount),
                                 color: '#6fbe80', title: d.noteCount + ' notes' });
    if (d.disputed)   out.push({ position: 'sw', iconClass: '…exclamation',
                                 color: '#b94a48', title: 'Disputed' });
    return out;
}
```

`'nw'`/`'sw'` are chosen deliberately: on an expandable node **both East corners are reserved**
for the expand affordance (north-east collapsed, south-east expanded), and object nodes are
expandable. Badges do not aggregate children, so an object's count must be computed in the
`badges` function by walking `node.children` if we want it to include its attributes — see §7.

Opinion → `disputed` uses the existing bands (`opinion_scale.ctp:11`): mean opinion `< 41`
disputed, `> 60` endorsed, else neutral. Dev-instance opinion values are strongly bimodal
(13 values in 0–30 vs 30 in 70–100), so a two-state signal reflects real usage better than a
gradient.

Detail goes in a selection-reactive `ExtraPanel` — v1.6.0 re-invokes `render` per selection with
the selected `Node`, which is what makes this panel possible at all. Content is already in the
payload, so no fetch; if we later fetch threads on demand, the hook may return a `Promise` and
`ctx.signal` cancels a superseded fetch.

`callbacks.onBadgeClick` selects the node and opens that panel. A badge with no `onClick` lets
its click fall through, so declaring one is required for it to be interactive.

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

Exactly one section may omit both `key` and `entries` (the `nodeTypeAccessor` dimension); a
second is dropped with a warning. The `Relationship` section names the same key as the
`edgeFacets` declaration, so panel and legend become two views of one filter.

### 6.4 Event provenance and correlated events (D2)

Every node gains `data.scope` (`'self'` | `'foreign'`) and `data.event_uuid`, derived from the
`event_id` each attribute/object already carries. `styleCb` desaturates `foreign`. Because
`styleCb` already returns the pending ring, provenance must be merged into the same return value
— `styleCb` wins outright over `edgeStyleMap`/`nodeStyleMap` rather than merging.

Correlated events (`RelatedEvent`) render as **leaf proxy nodes** of type `event` — the
`nodeStyleMap` already registers a green hexagon for `event` that nothing currently creates —
labelled from `info`/`date`/`org`, linked to the local attribute they correlate with by a
`correlation` edge. They are **not** expandable containers in this phase (see §4 Non-Goals);
double-click navigates to that event's own `view2`.

Extended events (`extended:1` merges foreign attributes/objects into the same arrays, with
provenance in `Event.extensionEvents`) are `scope: 'foreign'` and, unlike correlated events, are
real nodes with real edges. Whether they additionally get a cluster enclosure is deferred — it
needs a container primitive we would have to nest existing object clusters inside.

### 6.5 Editor tray → dock pane (D4, D5)

`createEditor()` keeps its inventory logic and chip DOM; only the mount changes:

```js
var handle = _graph.UIManager.addDockTab({
    label: 'Event elements', order: 10,
    render: function () { return panelEl || buildPanel(); },
    toolbar: function () { return buildFilterInput(); },
});
```

`render` is called once, lazily, on first activation, so the pane keeps its own scroll position;
`handle.refresh()` re-renders after a chip is staged. With D5 the pane lists the whole event
rather than only the unconnected remainder, and `UI.table` gives a second pane for free — the
`Visibility` column then explains where every hidden element stands, which is the honest version
of what the tray was approximating.

### 6.6 Write path (D6)

```js
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

Two things worth calling out:

- **`ctx.promptData()` replaces the bespoke relationship picker.** The overlay the graph builds
  today — a backdrop plus a `<select>` assembled with `innerHTML` (`:793-795`) — is exactly what
  `promptData({ fields })` provides, with the shadow-edge preview held up while it is open.
  `ctx.promptLabel()` is the one-field variant. This deletes code rather than adding it.
- `isValidConnection` encodes the legacy Event Graph's scope rule — the foreign-node refusal of
  `can_be_referenced()` (`event-graph.js:1684`) — as a live affordance instead of an error
  message after the fact, and `onBeforeEdgeCreate` is not consulted for a target it rejects.

Programmatic mutation never invokes these hooks, so the drag-in staging path (`graph.addNode`)
is unaffected.

### 6.7 Payload changes (MISP side)

1. **Add `includeGranularCorrelations:1`** to the fetch URL so `RelatedAttribute` arrives:
   `/events/view/{id}.json/includeGranularCorrelations:1`. No controller change.
2. **Annotation counts.** Notes/opinions are already inline per element; the counts are derived
   client-side in `attributeNodeData()` / `objectNodeData()`. No endpoint work.
3. **Inbound analyst relationships on attributes/objects** are absent from the bulk path. Phase
   1 derives them client-side by inverting the outbound set (both endpoints are usually in the
   same event); a MISP-side fix to `attachAnalystDataBulk` is Phase 2.

## 7. Edge Cases

- **Large events.** D5 puts every attribute in the graph. A 5 000-attribute event currently
  draws a handful of connected objects; afterwards it builds 5 000 nodes and hides most of them.
  `hideDisconnected` is computed after layers *and* node filters, and filters now apply **before
  the first layout**, so hidden nodes never reach the canvas — but they are still built,
  normalised and listed in the dock. Needs a node-count ceiling above which D5 reverts to the
  server-side pre-filter.
- **Every layer off.** Nothing is connected, so with `hideDisconnected` on, nothing is drawn.
  The library documents this and leaves the switch as the way back; we should not special-case it.
- **`reapply()` and manual hides.** Re-deriving visibility undoes `graph.hideNode()`. Any code we
  write that hides a node must use `queryEngine.excludeNode()` instead.
- **Badge capacity.** A plain node fits four badges, an expandable one two. Object nodes are
  expandable; if aggregation over children is added, an object could want three.
- **Unresolvable analyst-relationship targets** — skipped, per §6.1. Worth a count in the panel
  so a dropped assertion is visible rather than silently absent.
- **Deleted records.** `isDeleted()` already tombstones attributes/objects/references; analyst
  data has no `deleted` flag, so an analyst relationship can point at a soft-deleted attribute.
  Treat as unresolvable.
- **A cluster's stand-in edges** are deduped by node pair and can speak for several kinds; the
  library keeps them alive while any represented edge passes the filter. Nothing for us to do,
  but it means a stand-in's style may not match any single layer.
- **Self-referencing analyst relationship** — `Relationship::beforeValidate` rejects
  `object_uuid == related_object_uuid`, so a self-loop should not occur; guard anyway.

## 8. Test Plan

Manual, on `/events/view2/{id}` against the dev instance (which holds 75 notes, 43 opinions,
120 analyst relationships):

1. **Upgrade regression** — the graph renders, objects expand, chips drag in, an edge can be
   created and persists. This is the gate before any new feature lands.
2. Each layer toggles independently; layout, selection and camera unchanged across a toggle.
3. Badges appear on elements with analyst data and nowhere else; clicking one opens the panel;
   clicking a node's shape still selects it.
4. Legend: three sections, each folding independently; two sections filtering AND together;
   the `Relationship` section and the filter panel's `Relationships` section stay in sync.
5. Provenance: an `extended:1` event shows desaturated foreign nodes; a correlated-event proxy
   navigates on double-click.
6. `hideDisconnected` on/off round-trips; the flyout row reports a count.
7. Write path: an invalid connect target is marked during the drag; a vetoed edge leaves no node
   behind; a persisted edge survives reload.
8. Dock: pane lists the event, filter narrows the list, scroll position survives a tab switch.
9. An event with zero analyst data renders exactly as before (no badges, no empty sections).

## 9. Implementation Plan (sequential, one commit per task)

| # | Task | Depends on |
|---|---|---|
| 0 | ✅ Bundle to v1.6.0 + compatibility audit | — |
| 1 | Regression pass on the existing graph under v1.6.0 (§8.1); refresh the stale Edit▸Add-edge comment | 0 |
| 2 | Tag object-reference edges with `kind`; add `edgeTypeAccessor`/`edgeStyleMap`/`edgeFacets` (one layer, no behaviour change) | 1 |
| 3 | Add analyst-relationship edges as a second layer | 2 |
| 4 | Add `includeGranularCorrelations:1` + correlation edges as a third layer | 2 |
| 5 | Analyst-data badges + selection-reactive sidebar panel | 1 |
| 6 | Sectioned legend | 3, 4, 5 |
| 7 | `data.scope` + desaturation + correlated-event proxy nodes | 4 |
| 8 | Editor tray → dock pane; enable `UI.table` | 1 |
| 9 | D5: build all elements + `hideDisconnected`, with the §7 node ceiling | 8 |
| 10 | Write path onto `onBeforeEdgeCreate` + `isValidConnection` | 1 |

Tasks 2–5 are independent of each other and can be parallelised; 6 needs them all.

## 10. Files Touched

| File | Change |
|---|---|
| `app/webroot/js/pivotick.iife.js` | ✅ replaced (v1.6.0) |
| `app/webroot/css/pivotick.css` | ✅ replaced (v1.6.0) |
| `app/View/Themed/Overmind/Elements/Events/View/event_pivot_explorer.ctp` | all of §6.1–§6.6 |
| `app/Model/Behavior/AnalystDataParentBehavior.php` | Phase 2 only — `RelationshipInbound` in the bulk path (§6.7.3) |
| `docs/dev/pivot-explorer-v16-prd.md` | this document |

No controller change, no new endpoint, no schema change.

## 11. Open Questions / Phase 2

1. **Lazy expansion.** `childrenProvider` / a fired `onBeforeNodeExpansion` is the one PRD from
   the set that did not ship (`prd/misp/async-children-provider.md` in the pivotick repo,
   still *Proposed*). Until it lands, correlated events cannot expand in place and the merged
   correlation-plus-event graph stays half-built. This is the highest-value remaining library ask.
2. **Extended-event enclosure.** Does a foreign event get a real container, and can object
   clusters nest inside it three levels deep (event → object → attribute)?
3. **Node ceiling for D5** — what number, and does the dock stay complete above it?
4. **Badge aggregation** over an object's attributes: sum, max, or neither?
5. **Physics `'auto'`** (D7 deferred) — worth an isolated before/after on a large event.
6. **`RelatedAttribute` cost.** `includeGranularCorrelations` is off for REST by default, which
   suggests it is not cheap; measure on a heavily-correlated event before shipping task 4.
