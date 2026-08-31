# Pivot Explorer v1.6.0 — Implementation Progress

Delivery tracker for [`pivot-explorer-v16-prd.md`](pivot-explorer-v16-prd.md).
**Task definitions live in the PRD (§9); this file tracks only state.** Update it in the
same pass as the code, not in a catch-up sweep.

- **Branch:** `worktree-pivotick-v16` (tracks `mokaddem/worktree-pivotick-v16`)
- **Last updated:** 2026-08-31
- **Status:** 5 done · 1 part-done and blocked · 14 not started
- **Tests:** `node tests/js/pivot-explorer-graph.test.js` — 96 assertions, no dependencies

`✅` done · `🔜` next · `⏸` blocked · `⬚` not started

---

## 1. Tasks

One commit per task, per PRD §9. `E` and `T` are prerequisites, not numbered PRD tasks;
task 1 is split into `1a`/`1b` because only one half needs the dev server.

| # | Task | Status | Depends on | Commit / note |
|---|---|---|---|---|
| 0 | Bundle to v1.6.0 + compatibility audit | ✅ | — | `e02a24710` (2026-08-28) |
| E | Extract inline JS out of the `.ctp` into `webroot/js/pivot-explorer.js` | ✅ | 0 | `edc6a0caa` (2026-08-31) |
| T | Graph-builder unit tests, `tests/js/pivot-explorer-graph.test.js` | ✅ | E | Not a PRD task; possible only once E made the builder loadable outside a browser |
| 1a | Refresh the stale `Edit ▸ Add edge` comment | ✅ | 0 | Comment only, nothing to verify |
| 1b | Regression pass under v1.6.0 (§8.1) | 🔜 ⏸ | 0 | **Gate — blocks 2, 6, 9, 11.** Needs the dev server; see §3 |
| 2 | Tag object-reference edges with `kind`; add `edgeTypeAccessor` / `edgeStyleMap` / `edgeFacets` (one layer) | ✅ | 1 | Built ahead of the 1b gate, deliberately. Edge stroke becomes explicit blue — see §2 |
| 3 | Generalise `computeConnectivity()` to any authored relationship; analyst-relationship edges as a second layer (L1, D5′) | ✅ | 2 | Also fixed a pre-existing seeding bug — see §2 |
| 3b | L0: event node + `RelatedEvent` proxy nodes (free, already in payload) | ⬚ | 2 | |
| 3c | L2: budget-capped containment-only objects + "skipped, N not shown" statement (D10, D12) | ⬚ | 3, 3b | |
| 4 | D11 empty-state message + wiring for the on-demand fetch | ⬚ | 3c | |
| 5 | On-demand correlation fetch as a third layer, capped (D9, §6.7) | ⬚ | 4 | |
| 5b | `feed` / `server` node types + `feed-correlation` layer, incl. the `FeedHit` degraded shape (D1) | ⬚ | 2 | |
| 5c | `relationship_type` text facet as the second edge dimension (D1) | ⬚ | 2 | |
| 6 | Analyst-data badges + selection-reactive sidebar panel | ⬚ | 1 | |
| 7 | Sectioned legend | ⬚ | 3, 5, 6 | |
| 8 | `data.scope` facet + header (event identity + resolution statement) + correlated-event proxy nodes (D2c) | ⬚ | 5 | |
| 9 | "Unlinked attributes" → dock pane: search box + full list, server-paged table above a size threshold (D4); library `UI.table` as a second pane | ⬚ | 1 | |
| 10 | `possibleKinds()` + write path onto `onBeforeEdgeCreate` / `isValidConnection` / `editors.*.enabled`; delete the `innerHTML` picker and the pending ring (D2, D2b) | ⬚ | 1, 8 | |
| 10b | Analyst-relationship persistence (`analystData/add`) as the second write target (D2b) | ⬚ | 10 | |
| 10c | `onBeforeDelete`: edge deletion behind `ctx.confirm()`, node deletion vetoed (D6) | ⬚ | 10 | |
| 11 | `simulation.physics: 'auto'` alongside `d3LinkDistance: 200` (D7) | ⬚ | 1 | |

### Critical path

```
0 ✅ ─ E ✅ ─ T ✅
         └──── 1b ⏸ ─┬─ 2 ─┬─ 3 ─┬─ 3c ─ 4 ─ 5 ─ 8 ─ 10 ─┬─ 10b
                      │     │     │                        └─ 10c
                      │     ├─ 3b ─┘
                      │     ├─ 5b
                      │     └─ 5c
                      ├─ 6 ──────────── 7   (also needs 3, 5)
                      ├─ 9
                      └─ 11
```

Task 1b unblocks four independent fronts (2, 6, 9, 11). The longest chain behind it is
`2 → 3 → 3c → 4 → 5 → 8 → 10 → 10b/10c`, so **task 10's write path is eight tasks deep** —
worth knowing before promising the editor rework early.

---

## 2. Verification ledger

What has actually been checked, and how. Manual test-plan items are PRD §8.

| Scope | Verified | Method | Still owed |
|---|---|---|---|
| v1.6.0 bundle (task 0) | ✅ | `node --check`; `window.Pivotick` footer present; byte-identical (md5 `140ead0d…`) to a fresh `vite build` of the `v1.6.0` tag | Browser regression = §8.1 |
| Extraction (E) — PHP side | ✅ | Stub-harness render, 3 cases: `data-pe-*` populate, `"` in `$baseurl` escapes to `&quot;`, no `<script>` left, `<style>` still gated on `$canEdit`; `php -l` clean | — |
| Extraction (E) — JS side | ✅ | `node --check`; no PHP tags remain; `diff` proves the 745 logic lines byte-identical; stubbed-DOM harness **24/24** (boot timing, lazy tab activation, `_initialized` guard, URL assembly, error-as-text, `canEdit` gating) | **Browser check — folded into §8.1** |
| Graph builder — connectivity, nesting, tombstones, edge dedupe, `compact()`, truncation, image detection, tray/canvas invariant | ✅ | `tests/js/pivot-explorer-graph.test.js`, 96 assertions over 27 cases, zero-dependency plain node | Nothing — this layer no longer needs the server |
| Task 2 — `kind` tagging, `edgeTypeAccessor`, `edgeStyleMap`, `edgeFacets` | ✅ data + config | Same suite: every edge tagged, accessor resolves it, and the invariant that each emitted kind is a styled kind | **The grey→blue stroke change is visual — §8.1** |
| Task 3 — generalised seeding, analyst-relationship layer | ✅ data + config | Same suite: D5′ seeding from a relationship alone, target-type gating, tombstones on both kinds, provenance on the edge, skip cases | **Dashed-orange rendering of the new layer — §8.1** |
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

## 3. Blockers

**Task 1b needs the dev server, which is not ours to point.** misp-track selects which tree
`misp-core` serves; on 2026-08-31 it was serving `attribute-value-page-brief` for a parallel
job. **The user owns that switch** — read `~/git/misp-docker-2.5/.misp-track.state` to see
the current selection and ask; never repoint it.

Credentials for the authenticated `/events/view2/{id}` render are available.

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
- **~16 hardcoded English UI strings** in `pivot-explorer.js` — `'Unlinked attributes'`
  (`:776`), `'Filter…'` (`:505`), `'Unlinked '` (`:497`), the empty states (`:538`), four
  notifier messages (`:664`, `:723`, `:728`), and the picker's own labels. Untranslatable
  as they stand, and unchanged by the extraction — they were identical inline. Pivotick has
  no consumer-facing i18n (no `setLocale` / `translations`), so anything MISP writes stays
  MISP's to translate. Task 10 absorbs the picker strings; ~9 remain.
- **Dedicated graph endpoint (D13)** — deferred to
  [`pivot-explorer-graph-endpoint-prd.md`](pivot-explorer-graph-endpoint-prd.md). Until it
  lands, this PRD knowingly ships against `/events/view/{id}.json`, so large events stay
  slow to open (~100 MB for event 4116 to draw 86 nodes).
- **Phase 2 open questions** — object aggregation, lazy expansion via `childrenProvider`,
  declarative initial filter value. PRD §11.
