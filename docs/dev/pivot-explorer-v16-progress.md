# Pivot Explorer (Pivotick v2) — Implementation Progress

Delivery tracker for [`pivot-explorer-v16-prd.md`](pivot-explorer-v16-prd.md).
**Task definitions live in the PRD (§9); this file tracks only state.** Update it in the
same pass as the code, not in a catch-up sweep.

- **Branch:** `pivotick-v2`, off `worktree-pivotick-v16` (the v1.6.0 work)
- **Library:** Pivotick v2 — `develop` at `d220446` (v2.0.1 + 29 unreleased commits). PRD §3.7
- **Last updated:** 2026-09-23
- **Status:** 11 done · 1 part-done and blocked · 12 not started · §3.7 answered 2026-09-23 (PRD §5 *Rulings*, P0 + R1–R6); count source built (5e); **5 and 5d next, needing a fetch path**
- **Tests:** `node tests/js/pivot-explorer-graph.test.js` — 48 cases, 170 assertions, no dependencies

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
| 1b | Regression pass under v2 (§8.1) | 🔜 ⏸ | 0b | **Gate — blocks 2, 6, 9, 11.** Rendering half done in a headless harness (§2); the real-instance half needs the dev server — §3 |
| 2 | Tag object-reference edges with `kind`; add `edgeTypeAccessor` / `edgeStyleMap` / `edgeFacets` (one layer) | ✅ | 1 | Built ahead of the 1b gate, deliberately. Edge stroke becomes explicit blue — see §2 |
| 3 | Generalise `computeConnectivity()` to any authored relationship; analyst-relationship edges as a second layer (L1, D5′) | ✅ | 2 | Also fixed a pre-existing seeding bug — see §2 |
| 3b | L0: event node + `RelatedEvent` proxy nodes (free, already in payload) | ✅ | 2 | `7ab4f859f` (2026-08-31), shared with 3c — see §2 |
| 3c | L2: budget-capped containment-only objects + "skipped, N not shown" statement (D10, D12) | ✅ | 3, 3b | `7ab4f859f` (2026-08-31). **Changes what most events draw** — see §2 |
| 4 | D11 empty-state message, pointing at the correlation pivot | ⬚ | 3c, 5 | Now follows 5: the message points at the pivot |
| 5e | Count source — `GET /events/correlationCounts/{id}.json` (R1, first slice of D13) | ✅ | — | `7f0b6d041` (2026-09-23) — see §2 |
| 5 | Correlations as a pivot — `appliesTo` / `summarize` from 5e / `fetch` / `maxCandidates`, no `save` (R1) | 🔜 | 5e, fetch path | Count in hand; `fetch` needs the correlated elements with stable ids |
| 5d | Related-event pivot on L0 proxies + declared potential as the rim badge (R2) | 🔜 | 3b, 5e, fetch path | Badge `n` = 5e's `events` entry |
| 5b | `feed` / `server` node types + `feed-correlation` layer, incl. the `FeedHit` degraded shape (D1) | ⬚ | 2 | |
| 5c | `relationship_type` text facet as the second edge dimension (D1) | ⬚ | 2 | |
| 6 | Analyst-data badges + selection-reactive sidebar panel | ⬚ | 1 | |
| 7 | Sectioned legend | ⬚ | 3, 5, 6 | |
| 8 | `data.scope` facet + header (event identity + resolution statement) + correlated-event proxy nodes (D2c) | ⬚ | 5 | |
| 9 | "Unlinked attributes" → dock pane: search box + full list, server-paged table above a size threshold (D4); library `UI.table` as a second pane | ⬚ | 1 | |
| R5 | Read-only users: every persistence editor off, no editor hooks, no tray | ✅ | 0b | `5a5770d9c` (2026-09-23) — verified in the harness for both roles |
| 10 | `possibleKinds()`; `ctx.promptData` replaces the `innerHTML` picker; delete the pending ring (D2, D2b, P0) | ⬚ | 1, 8 | Hooks landed in 0b, read-only gating in R5. The editor role still has Pivotick's Add node / Edit node / Delete node, none of them backed by MISP — decide here |
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

- **Editor CSS still inline** — 78 lines under `if ($canEdit)` in the `.ctp`, ~29 `.pe-*`
  selectors. Three groups: tray (~15 lines), relationship picker (~11), drag ghost + drop
  outline (~4). Pivotick styles only its own `pvt-*` chrome; all of this is MISP-injected
  DOM. **The picker third is deleted by task 10**, which routes the write path through
  pivotick's themed `promptData()` — so only the tray and ghost (~19 lines) are a genuine
  CSS-extraction candidate.
- **~21 hardcoded English UI strings** in `pivot-explorer.js` — `'Unlinked attributes'`,
  `'Filter…'`, `'Unlinked '`, the empty states, four notifier messages, the picker's own labels,
  and now the five fragments `resolutionStatement()` assembles (`'Seeded '`, `' node(s)'`,
  `'L2 skipped (N objects not shown)'`, `'N relationships not drawable'`). Untranslatable as they
  stand. Pivotick has no consumer-facing i18n (no `setLocale` / `translations`), so anything MISP
  writes stays MISP's to translate. Task 10 absorbs the picker strings; ~14 remain. The statement
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
- **Tray drops are outside the undo history** — `graph.addNode` is programmatic, so Ctrl+Z does
  not take back a dragged-in chip. Consistent with v2's rule; worth knowing before task 9 builds
  the dock pane on the same path.
- **Phase 2 open questions** — object aggregation, lazy expansion via `childrenProvider`,
  declarative initial filter value. PRD §11.
