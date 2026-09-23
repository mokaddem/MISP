# Pivot Explorer (Pivotick v2) — Implementation Progress

Delivery tracker for [`pivot-explorer-v16-prd.md`](pivot-explorer-v16-prd.md).
**Task definitions live in the PRD (§9); this file tracks only state.** Update it in the
same pass as the code, not in a catch-up sweep.

- **Branch:** `pivotick-v2`, off `worktree-pivotick-v16` (the v1.6.0 work)
- **Library:** Pivotick v2 — `develop` at `d220446` (v2.0.1 + 29 unreleased commits). PRD §3.7
- **Last updated:** 2026-09-23
- **Status:** 14 done · 2 built and blocked upstream (5, 5d) · 8 not started · §3.7 answered 2026-09-23 (PRD §5 *Rulings*, P0 + R1–R6); **in progress while the Pivotick fix is out: 4, 6 (9, 10 done)**
- **Tests:** `node tests/js/pivot-explorer-graph.test.js` — 71 cases, 250 assertions, no dependencies

`✅` done · `🔜` next · `⏸` blocked · `⬚` not started

---

## 1. Tasks

One commit per task, per PRD §9. `E` and `T` are prerequisites, not numbered PRD tasks;
task 1 is split into `1a`/`1b` because only one half needs the dev server.

| # | Task | Status | Depends on | Commit / note |
|---|---|---|---|---|
| 0 | Bundle to v1.6.0 + compatibility audit | ✅ | — | `e02a24710` (2026-08-28) |
| 0b | Bundle to v2 + audit; edge save onto `onBeforeEdgeCreate` + `isValidConnection` | ✅ | 0 | `3c4d1f0b1` bundle, `9ed240f92` write path (2026-09-23) — see §2 |
| E | Extract inline JS out of the `.ctp` into `webroot/js/pivot-explorer.js` | ✅ | 0 | `edc6a0caa` (2026-08-31) |
| T | Graph-builder unit tests, `tests/js/pivot-explorer-graph.test.js` | ✅ | E | Not a PRD task; possible only once E made the builder loadable outside a browser |
| 1a | Refresh the stale `Edit ▸ Add edge` comment | ✅ | 0 | Comment only, nothing to verify |
| 1b | Regression pass under v2 (§8.1) | ✅ | 0b | 2026-09-23 on the dev instance — see §2. Found and fixed: the editor was never offered on view2 (`608229a2b`) |
| 2 | Tag object-reference edges with `kind`; add `edgeTypeAccessor` / `edgeStyleMap` / `edgeFacets` (one layer) | ✅ | 1 | Built ahead of the 1b gate, deliberately. Edge stroke becomes explicit blue — see §2 |
| 3 | Generalise `computeConnectivity()` to any authored relationship; analyst-relationship edges as a second layer (L1, D5′) | ✅ | 2 | Also fixed a pre-existing seeding bug — see §2 |
| 3b | L0: event node + `RelatedEvent` proxy nodes (free, already in payload) | ✅ | 2 | `7ab4f859f` (2026-08-31), shared with 3c — see §2 |
| 3c | L2: budget-capped containment-only objects + "skipped, N not shown" statement (D10, D12) | ✅ | 3, 3b | `7ab4f859f` (2026-08-31). **Changes what most events draw** — see §2 |
| 4 | D11 empty-state message, pointing at the correlation pivot | ⬚ | 3c, 5 | Now follows 5: the message points at the pivot |
| 5e | Count source — `GET /events/correlationCounts/{id}.json` (R1, first slice of D13) | ✅ | — | `7f0b6d041` (2026-09-23) — see §2 |
| 5f | Fetch path — `POST /events/correlatedAttributes/{id}.json` (`attribute_uuids` / `event_ids`) | ✅ | 5e | `261e06772` — pairs match 5e's counts exactly, per attribute and per event |
| 5 | Correlations as a pivot — `appliesTo` / `summarize` from 5e / `fetch` / `maxCandidates`, no `save` (R1) | ⏸ | 5e, 5f, **pivotick fix** | `65b782926`. Nodes land; **correlation edges do not** — `pivotick/prd/misp/pivot-edges-to-children.md` |
| 5d | Related-event pivot on L0 proxies + declared potential as the rim badge (R2) | ⏸ | 3b, 5e, 5f, **pivotick fix** | `65b782926`. Badge counts match 5e on every related event; same edge gap |
| 5b | `feed` / `server` node types + `feed-correlation` layer, incl. the `FeedHit` degraded shape (D1) | ⬚ | 2 | |
| 5c | `relationship_type` text facet as the second edge dimension (D1) | ⬚ | 2 | |
| 6 | Analyst-data badges + selection-reactive sidebar panel | ⬚ | 1 | |
| 7 | Sectioned legend | ⬚ | 3, 5, 6 | |
| 8 | `data.scope` facet + header (event identity + resolution statement) + correlated-event proxy nodes (D2c) | ⬚ | 5 | |
| 9 | "Unlinked attributes" → dock pane: search box + full list, server-paged table above a size threshold (D4); library `UI.table` as a second pane | ✅ | 1 | 2026-09-23 — built as PRD §11.7's origin-less pivot, not a bespoke pane (P0). See §2 |
| R5 | Read-only users: every persistence editor off, no editor hooks, no tray | ✅ | 0b | `5a5770d9c` (2026-09-23) — verified in the harness for both roles |
| 10 | `possibleKinds()`; `ctx.promptData` replaces the `innerHTML` picker; delete the pending ring (D2, D2b, P0) | ✅ | 1 | 2026-09-23 — see §2. Did not need 8: ownership is read off the payload, not a `scope` field. Needed the vocabulary endpoint fixed first (`e39908012`) |
| 10b | Analyst-relationship persistence (`analystData/add`) as the second write target (D2b); `edgeCreator` for `perm_analyst_data` alone (R5) | ⬚ | 10 | |
| 10c | `onBeforeDelete`: edge deletion behind a `danger` `ctx.confirm()` saying it cannot be undone, `persisted: true`; node deletion vetoed (D6, R4) | ⬚ | 10 | Pivotick locks the history row itself |
| 11 | `simulation.physics: 'auto'` alongside `d3LinkDistance: 200` (D7) | ⬚ | 1 | |

### Critical path

```
0 ✅ ─ E ✅ ─ T ✅
         └──── 1b ⏸ ─┬─ 2 ✅ ─┬─ 3 ✅ ─┬─ 3c ✅ ─ 5 ─ 4 ─ 8 ─ 10 ─┬─ 10b
                      │        │        │   ↑                     └─ 10c
                      │        │        │  5e ✅ ─┐
                      │        ├─ 3b ✅ ─┴─────── 5d
                      │        ├─ 5b
                      │        └─ 5c
                      ├─ 6 ──────────── 7   (also needs 3, 5)
                      ├─ 9
                      └─ 11
```

Task 1b unblocks four independent fronts (2, 6, 9, 11). The seed chain is now complete through
3c. Under R1 the next link is **task 5**; its count (5e) is built, and what it still needs is a
fetch path for the correlated elements. The longest chain is `5 → 4 → 8 → 10 → 10b/10c`. Nothing on the write-path branch
(10, 10c) needs the count: its dependency on 8 is the scope facet, so 10's P0 work — the
`promptData` picker — could go first if the count question takes time.

---

## 2. Verification ledger

What has actually been checked, and how. Manual test-plan items are PRD §8.

| Scope | Verified | Method | Still owed |
|---|---|---|---|
| v1.6.0 bundle (task 0) | ✅ | `node --check`; `window.Pivotick` footer present; byte-identical (md5 `140ead0d…`) to a fresh `vite build` of the `v1.6.0` tag | Browser regression = §8.1 |
| Extraction (E) — PHP side | ✅ | Stub-harness render, 3 cases: `data-pe-*` populate, `"` in `$baseurl` escapes to `&quot;`, no `<script>` left, `<style>` still gated on `$canEdit`; `php -l` clean | — |
| Extraction (E) — JS side | ✅ | `node --check`; no PHP tags remain; `diff` proves the 745 logic lines byte-identical; stubbed-DOM harness **24/24** (boot timing, lazy tab activation, `_initialized` guard, URL assembly, error-as-text, `canEdit` gating) | **Browser check — folded into §8.1** |
| Graph builder — connectivity, nesting, tombstones, edge dedupe, `compact()`, truncation, image detection, tray/canvas invariant | ✅ | `tests/js/pivot-explorer-graph.test.js`, zero-dependency plain node — 27 cases when this row was written, 48 now | Nothing — this layer no longer needs the server |
| Task 2 — `kind` tagging, `edgeTypeAccessor`, `edgeStyleMap`, `edgeFacets` | ✅ data + config | Same suite: every edge tagged, accessor resolves it, and the invariant that each emitted kind is a styled kind | **The grey→blue stroke change is visual — §8.1** |
| Task 3 — generalised seeding, analyst-relationship layer | ✅ data + config | Same suite: D5′ seeding from a relationship alone, target-type gating, tombstones on both kinds, provenance on the edge, skip cases | **Dashed-orange rendering of the new layer — §8.1** |
| Task 3b — L0 nodes, `event-correlation` edges, `Event` as a relationship target | ✅ data + config | Same suite: proxy per `RelatedEvent`, dedupe, no self-proxy, the edge-gate on the event node, `Event`-typed analyst targets resolving to both the event and its neighbours, label/description/`event_id` shape, and `onNodeDbclick` navigating only for a foreign `event` node | **The green hexagons and dashed-green edges are visual — §8.1**; §8.7's double-click check |
| Task 3c — the budget, L2 clusters, the resolution statement | ✅ data + config | Same suite: the boundary (1,500 fits, 1,501 skips whole), cost counted with children and without tombstones, L2 never seeding a bare attribute, the tray losing exactly the objects L2 drew, and the statement's own text in eight states | **How the statement reads in the card — §8.1**; the `hideDisconnected` collision (§7) is still unexercised |
| v2 bundle + write path (task 0b) | ✅ | Clean `npm run build` of `d220446`, `node --check`, md5 `1183ba8c…`; every option/call `pivot-explorer.js` makes checked against `dist/types`; suite 170/170; **headless Chromium harness** — real bundle + real `pivot-explorer.js`, stubbed `fetch`, hand-built fixture (L0 pair, one reference, one L2 object, one tray attribute): renders with no console error, L0+L1+L2 seeded, both edge colours, tray drop pinned + pending, four edge gestures each checked for picker / POST / edge / history | §8.1 on the real instance with events 1195 and 4116 |
| Count endpoint (task 5e) | ✅ model + aggregation | `php -l` on every file; `CorrelationCountToolTest` 3/3 under the container's PHPUnit; the method body run from a check shell against the live models for two users × two events — counts, timings, sizes, the 404, and agreement with `RelatedEvent` (graph-endpoint PRD §7) | **The HTTP route itself** — JSON extension, ACL entry, 404 — needs the dev server on this branch |
| Real instance, v2 (task 1b) | ✅ | Playwright, logged in, dev server on `pivotick-v2`. `correlationCounts` over HTTP: admin 1195 → 350 / 18 events, 4116 → 708 / 78; org 9 admin 1195 → 346 / 15, 4116 → 404 — identical to the check shell. Pivot Explorer: 1195 opens in 4.7 s, layout settles in ~17 s (4,743 top-level nodes, 2,362 references); 4116 opens in 21 s (L0 only, 90 nodes — the D13 payload); 2014 in 0.4 s. No console error from the explorer. Edit rights: admin → editor, plain org-1 User on an org-9 event → read-only. A drawn reference on 2014 POSTs 200, lands as `object-reference`, records `persisted: true`, survives a reload — then deleted (`objectReferences/delete/11378/1`) | Glyphs: see below |
| Drawn edges (task 10) | ✅ | Suite 225/225 (9 new: editor gating per role, the ownership gate on both ends, refusal before any form, vocabulary sorted/defaulted/fetched once, the POST and the persisted decision, custom beats list, blank and cancel save nothing, a refused save, the free-text fallback retrying); 7 targeted mutants, 7 caught. Live, admin, event 2014, through Pivotick's click-connect: the form is Pivotick's themed modal with 262 relationships defaulting to `related-to`, the `<script>` row rendered as text (no `<script>` element in the modal); a list choice and a typed one both POST, land as `object-reference` with the typed label, no console error — references 11379/11380, then hard-deleted | The perm-only analyst kind is 10b |
| Element pivot (task 9) | ✅ | Suite 250/250 (6 new cases: shape, what is offered, search scope, narrowing and summary = fetch, the form's facets and counts, cache invalidation; the tray tests now read the pivot's offer, and the invariant holds against it); 13 targeted mutants, 13 caught. Live, admin, through `graph.pivots` and the real panel/Review tab: 2014 offers its 1 unlinked attribute, ingest puts it on the canvas (29 → 30) and the offer drops to 0, undo takes both back. 4116 offers 28,410 objects (every attribute is inside one); unnarrowed the run is **refused on the cap** (28,410 > 1,500); `80.66.83.162` finds 5 in 197 ms on the first search (search text built then), stages in 70 ms, ingests 5, undo restores. No console error | A read-only user was not driven live — the pivot is declared regardless of edit rights, which the suite checks |
| Pivots (tasks 5, 5d, 5f) | ✅ except edges | Suite 192/192 (8 new: declaration, cap, no `save`, `appliesTo` before/after counts, never this event, fetch bodies, container shape, stable edge ids, this event's side brought along). Live, admin, via `graph.pivots`: `correlatedAttributes` pairs = counts on 1195 (350), 4116 (708), one attribute, one event, and for the org 9 admin (346); Pivot rail button present; `related-event` potential on 23/23 related events of 2014 and 78/89 of 4116 (the other 11 have no count), each equal to 5e; a related-event run ingests its attributes into the proxy (2014: 4, 4116: 33) and undo takes them back | **Correlation edges: 90 staged, 0 landed** — upstream |
| Everything else | ⬚ | — | PRD §8.2–§8.10 |

**Task 2 has one visible consequence.** Pivotick's default edge stroke is grey
(`var(--pvt-edge-stroke, #999)`); D1 allocates `#428bca` to `object-reference`, and every
edge on the canvas today is one — so all edges go grey→blue. That is the settled palette
(task 3 adds orange for analyst relationships), not a regression, but it is a rendering
change no harness can confirm. Everything else about task 2 is data and config, and tested.

**Task 3 fixed a pre-existing seeding bug, found by its own new test.** The source of a
relationship was seeded *before* the target was checked, so an element whose only link
dangled — pointing at another event, a tombstone, or an element type the canvas does not
draw — arrived on the canvas alone with no edge, which is exactly what the connectivity
rule exists to prevent. Both kinds now resolve the far end first (`exists()`), and such an
element stays in the tray where it can be dragged in deliberately. **This changes existing
behaviour for dangling object references**, in the direction the rule always intended.

The task-2 dedupe-key gap is **closed**: a second kind makes `kind`-in-the-key observable,
and that mutant is now caught.

**Task 3c changes what most events draw, and it is the largest behaviour change of the set.**
Under D10(c) every live object is now on the canvas — as an L1 spine member if a relationship
touches it, otherwise as an L2 containment-only cluster — so long as the whole L2 set fits the
1,500-node budget. Two consequences worth stating plainly:

- **The tray keeps only event-level attributes.** Objects used to be its bulk; below the budget
  they are all on the canvas instead, and the tray/canvas invariant moves them out of it. Above
  the budget they all come back, which is D4's "the dock is load-bearing" case arriving for real.
- **Twelve existing tests changed expectations**, and none of them weakened. They were pinning
  pre-L2 canvas membership; the seeding rule they were really about (L1 vs nothing) is now
  observable through the resolution statement, which names the levels, and through a new companion
  test that pushes L2 past the budget so the pure L1 rule is visible on its own. That companion
  carries the exact assertion task 3 shipped.

**Two decisions the PRD had left open, now settled in it** (§D1, §D10, §D12):

1. **`event-correlation` is a sixth `kind`**, not `correlation`. Event 4116 has 86 of one and
   5,629 of the other saying the same thing at different resolutions; one kind for both would stop
   the layer switch keeping the cheap aggregate while hiding the expensive detail, and would make
   the `correlation` layer look populated before D9's fetch ever runs.
2. **The event node is drawn only when something connects to it.** A bare hexagon on every event
   would make L0 permanently non-empty and put D11's "nothing to draw" message (task 4) out of
   reach — it would also be exactly the floating dot D5 was withdrawn over.

**Why 3b and 3c share one commit**, against the one-commit-per-task rule. `computeSeed()` costs L0
before it can judge whether L2 fits, so the budget arithmetic spans both tasks. Splitting would
have meant committing an intermediate L0-only helper that the next commit deletes — noise, not
history. The two tasks were built and tested together as "finish task 3".

**The new work is mutation-tested to the same standard.** Twenty-six further mutants aimed at
tasks 3b and 3c — the budget gate and its off-by-one, object cost with and without children,
tombstoned children, L0's own budget charge, the tray filter, the event-node edge gate, the L0
edge kind, `Event` target resolution in three places, and every clause of the statement —
**26 caught, 0 escaped**, for 59 over the suite's life. Two needed a second pass: a duplicate
`RelatedEvent` turned out to be invisible in the graph itself (`addNode`/`addEdge` dedupe it) and
observable only as a node the budget paid for and the canvas never drew, so the dedupe test now
asserts the statement; and "draw proxies without the event node" proved to be an *equivalent*
mutant — proxies are non-empty only when the event node is seeded — replaced by one that breaks
the coupling at its source.

The graph-builder suite is **mutation-tested**: seventeen targeted breaks — ten — dropped tombstone guard, removed connectivity gate, removed edge-existence check, disabled dedupe, kept nulls, disabled truncation, broken image regex, unreferenced attributes admitted, dropped `related-to` fallback, nested deleted children — plus task 2's kind dimension and task 3's seeding, target-type
gating, relationship walking and tombstone handling. **33 mutants, 33 caught, none
escaping.** Three of them were only caught after mutation testing exposed fixtures that
were passing by luck: a target type rescued by a uuid that happened not to exist, an
event-level attribute never used as a relationship *source*, and a deleted link between
two elements both on the canvas for other reasons. So a green run means something.

**The extraction has never been opened in a browser.** Harnesses covered the config
plumbing and boot order, not rendering. §8.1's five interactions — graph renders, objects
expand, chips drag in, an edge is created, it persists — cover the bundle bump *and* the
file split in one sitting, which is why task 1b is doing double duty.

---

**Task 0b's write-path change is the only code the v2 bump needed.** v2 records a hand-drawn
edge in `graph.history` when it lands; the v1.6 `edgeAdd` listener saved or removed it *after*
that, so a refused save left an undo row for a vanished edge and a saved one undid as unsaved.
The edge now lands only once `objectReferences/add` succeeds, marked `persisted: true`. Verified
by gesture in the harness, not by the unit suite — the editor has no unit tests.

**One visible regression is left open on purpose:** `render.minLabelFontSize` (9 px) hides every
label at the opening fit on the harness fixture (zoom 0.62). The library default, so the call is
the owner's — PRD §3.7.

**What the live pass turned up:**

- **The editor was never offered on `/events/view2`** — `view2()` sets no `mayModify`, which the
  element read, so `canEdit` was always false, for a site admin on their own event too. The
  element now asks `$this->Acl->canModifyEvent($data)`, as `event_attachments.ctp` and its other
  siblings already do. Pre-dates the v2 work (`4061b1b8c`).
- **No misp-iconify glyph on any node.** The icon class resolves and `misp-iconify.css` loads, but
  Pivotick draws no icon element. **Not pursued:** node rendering is to be replaced by another
  renderer once the functional work is done (owner's call, 2026-09-23).
- **`/events/view2` exists only under the Overmind theme** — a user on the default theme gets
  *View file "Events/view2.ctp" is missing*. Only users 1 and 2 are on Overmind here, which is
  why no non-admin editor could be exercised live; the rule is the siblings' own.
- **Pre-existing page error, not ours:** `mispOvermind.js:2489` listens for `mouseenter` in the
  capture phase on `document` and calls `e.target.closest` unguarded, so the pointer entering the
  page throws `e.target.closest is not a function` (`a6a06665f`).
- **Side effect of the save test:** event 2014's timestamp moved to 2026-09-23 13:35; the
  reference itself is gone.

**Task 10 — what was decided while building it.**

- **Node creation, node editing and edge editing are off for everyone**, editors included. None of
  them writes anything to MISP: a created node would be a phantom, and an edited label would
  disagree with the saved reference. Drawing an edge and deleting stay for editors (deletion's
  MISP backing is 10c).
- **"Is this one of this event's elements" is answered from the payload**, not from task 8's
  `scope` field: a node is own if its uuid is a live attribute or object of the event. That is
  what made 10 independent of 8. A correlated attribute a pivot brings in fails it, so it can
  never be referenced.
- **The vocabulary is `object_relationships`**, from `/objectRelationships/index.json`, fetched on
  the first drawn edge and cached. That endpoint returned a 500 for every user — it checked a
  REST payload only CRUD actions set — and is fixed on its own (`e39908012`). The hardcoded
  25-entry list is deleted; if the fetch fails, the form is a single free-text field and the next
  edge asks again.
- **The form is declarative**: a select (defaulting to `related-to`) and a free-text field, the
  typed value winning. Pivotick has no combobox, so a list plus a text field is the closest
  honest shape. Only one kind is ever possible until 10b, so there is no link-type question.

**Task 9 — the tray became a pivot.** P0 settled what PRD §11.7 had left as a candidate: Pivotick
already has a searchable, filterable, paged table with a commit step (a pivot's Review tab), so the
event's elements are an **origin-less pivot**, *Event elements*, and ingesting is putting them on
the canvas. What that changed:

- **The tray, its drag-and-drop, the drop ghost and all of the element's CSS are deleted.** Elements
  no longer land where they were dropped; the library places them.
- **Undo now covers it.** A tray drop was a programmatic `addNode`, outside the history (§4 below);
  an ingest is a history row.
- **Every viewer gets it, read-only users included.** Putting an element on the canvas writes
  nothing (D2), so there was never a reason to reserve it for editors; the tray was editor-only
  only because it lived in the editor.
- **What it offers is what the canvas lacks**: live event-level attributes and whole objects not
  drawn, read off the live graph, so it tracks ingests, undos and pivots. Summaries are dropped on
  every `nodeAdd` / `nodeRemove`, since the library caches them until told.
- **The form**: a search box (value, type, category and comment; an object answers for its live
  attributes, since it is what gets ingested), then *Element* and *Category* selects with counts.
  `maxCandidates` is the 1,500 budget, so an unnarrowed 28,410-object event is refused with its
  number — D4's "search is the primitive", enforced by the library.
- **Not server-paged.** D4 asked for server paging above a size threshold, but the whole event is
  already in memory (D13 is not built), so paging from the server would fetch again what the page
  holds. When D13 lands, `fetch` is the one place to change. The Review tab pages at 100 rows.
- **The library's `UI.table`** was already the dock's first tab; nothing to add.
- **Not exercised:** an own attribute the correlation pivot brought in on its own (a child of an
  object L2 skipped), followed by ingesting that object here. The ids would collide; Pivotick
  skips an id already on canvas, but whether that holds for a container's children is untested.

**Tasks 5 and 5d are blocked on Pivotick, not on MISP.** `PivotManager.ingest()` lands a carried
edge only when an endpoint is a *top-level* node the run landed: descendants of a new container are
not counted, and children merged into a container already on canvas are merged after the edges
are decided. Both pivots return their results as containers (the related event holding its
correlated attributes), so every `correlation` edge is dropped. Written up for a Pivotick session:
`~/git/pivotick/prd/misp/pivot-edges-to-children.md`. No MISP workaround, per P0.

**Event 4116 offers the correlation pivot nothing to start from** — every correlated attribute is
inside an object, and L2 is skipped. Correct, and it is task 9's dock pane that will reach them.

## 3. Blockers

**Task 1b needs the dev server, which is not ours to point.** misp-track selects which tree
`misp-core` serves; on 2026-08-31 it was serving `attribute-value-page-brief` for a parallel
job. **The user owns that switch** — read `~/git/misp-docker-2.5/.misp-track.state` to see
the current selection and ask; never repoint it.

Credentials for the authenticated `/events/view2/{id}` render are available.

**The harness did part of it (2026-09-23)**: rendering, seeding, both edge kinds, the tray drop
and the edge gestures under v2. What still needs the real instance is real payloads (1195, 4116),
the misp-iconify glyphs (the harness loads no font), the CSP-served worker, and the saved
reference surviving a reload.

**What 1b now owes has grown.** Beyond the bundle bump and the file split, an unopened browser has
never seen: the grey→blue edge stroke (task 2), the dashed-orange analyst layer (task 3), the green
event hexagons and their dashed-green aggregate edges (3b), L2's containment clusters on an
ordinary event, and the `#pe-resolution` line in the card header (3c). All of it is tested as data
and config; none of it is tested as pixels.

Fixture events, per PRD §8: **1195** (2,362 refs — the authored-spine seed case) and
**4116** (0 refs, 5,629 correlations — the D11 empty-state and cap case).

---

## 4. Not scheduled

Real work, deliberately outside PRD §9. Listed so it is not rediscovered as a surprise.

- **Hardcoded English UI strings** in `pivot-explorer.js` — the pivots' labels and facet labels
  (`'Event elements'`, `'Search'`, `'Element'`, `'Category'`, `'Correlations'`, …), the
  relationship form's (`'Add relationship'`, `'Relationship type'`, `'Or a custom one'`), the
  notifier messages, and the five fragments `resolutionStatement()` assembles (`'Seeded '`,
  `' node(s)'`, `'L2 skipped (N objects not shown)'`, `'N relationships not drawable'`).
  Untranslatable as they stand. Pivotick has no consumer-facing i18n (no `setLocale` /
  `translations`), so anything MISP writes stays MISP's to translate. Tasks 9 and 10 deleted the
  tray's and the picker's strings, and added about as many in the library's forms. The statement
  is the one group with a natural home already: task 8 moves it into the header, and `data-pe-*`
  is the established route for a translated string — though a sentence with counts and plurals
  wants more than one attribute.

- **Dedicated graph endpoint (D13)** — deferred to
  [`pivot-explorer-graph-endpoint-prd.md`](pivot-explorer-graph-endpoint-prd.md). Until it
  lands, this PRD knowingly ships against `/events/view/{id}.json`, so large events stay
  slow to open (~100 MB for event 4116 to draw 86 nodes).
- **Pivotick: `getEdges()` reports stale provenance** — a hand-drawn edge's read-only view
  says `getSources()` = `["seed"]` while `getMutableEdge()` says `["manual"]`. No MISP code
  reads it; upstream, not a workaround here.
- **Phase 2 open questions** — object aggregation, lazy expansion via `childrenProvider`,
  declarative initial filter value. PRD §11.
