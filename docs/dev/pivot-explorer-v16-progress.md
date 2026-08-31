# Pivot Explorer v1.6.0 — Implementation Progress

Delivery tracker for [`pivot-explorer-v16-prd.md`](pivot-explorer-v16-prd.md).
**Task definitions live in the PRD (§9); this file tracks only state.** Update it in the
same pass as the code, not in a catch-up sweep.

- **Branch:** `worktree-pivotick-v16` (tracks `mokaddem/worktree-pivotick-v16`)
- **Last updated:** 2026-08-31
- **Status:** 2 done · 1 next (blocked) · 16 not started

`✅` done · `🔜` next · `⏸` blocked · `⬚` not started

---

## 1. Tasks

One commit per task, per PRD §9. `E` is the prerequisite extraction, which was not a
numbered task.

| # | Task | Status | Depends on | Commit / note |
|---|---|---|---|---|
| 0 | Bundle to v1.6.0 + compatibility audit | ✅ | — | `e02a24710` (2026-08-28) |
| E | Extract inline JS out of the `.ctp` into `webroot/js/pivot-explorer.js` | ✅ | 0 | `edc6a0caa` (2026-08-31) |
| 1 | Regression pass under v1.6.0 (§8.1); refresh the stale `Edit ▸ Add edge` comment | 🔜 ⏸ | 0 | **Gate — blocks 2, 6, 9, 11.** Needs the dev server; see §3 |
| 2 | Tag object-reference edges with `kind`; add `edgeTypeAccessor` / `edgeStyleMap` / `edgeFacets` (one layer, no behaviour change) | ⬚ | 1 | |
| 3 | Generalise `computeConnectivity()` to any authored relationship; analyst-relationship edges as a second layer (L1, D5′) | ⬚ | 2 | |
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
0 ✅ ─ E ✅ ─ 1 ⏸ ─┬─ 2 ─┬─ 3 ─┬─ 3c ─ 4 ─ 5 ─ 8 ─ 10 ─┬─ 10b
                   │     │     │                        └─ 10c
                   │     ├─ 3b ─┘
                   │     ├─ 5b
                   │     └─ 5c
                   ├─ 6 ──────────── 7   (also needs 3, 5)
                   ├─ 9
                   └─ 11
```

Task 1 unblocks four independent fronts (2, 6, 9, 11). The longest chain behind it is
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
| Everything else | ⬚ | — | PRD §8.2–§8.10 |

**The extraction has never been opened in a browser.** Harnesses covered the config
plumbing and boot order, not rendering. §8.1's five interactions — graph renders, objects
expand, chips drag in, an edge is created, it persists — cover the bundle bump *and* the
file split in one sitting, which is why task 1 is doing double duty.

---

## 3. Blockers

**Task 1 needs the dev server, which is not ours to point.** misp-track selects which tree
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
  (`:747`), `'Filter…'` (`:479`), `'Unlinked '` (`:471`), the empty states (`:512`), four
  notifier messages (`:638`, `:694`, `:699`), and the picker's own labels. Untranslatable
  as they stand, and unchanged by the extraction — they were identical inline. Pivotick has
  no consumer-facing i18n (no `setLocale` / `translations`), so anything MISP writes stays
  MISP's to translate. Task 10 absorbs the picker strings; ~9 remain.
- **Dedicated graph endpoint (D13)** — deferred to
  [`pivot-explorer-graph-endpoint-prd.md`](pivot-explorer-graph-endpoint-prd.md). Until it
  lands, this PRD knowingly ships against `/events/view/{id}.json`, so large events stay
  slow to open (~100 MB for event 4116 to draw 86 nodes).
- **Phase 2 open questions** — object aggregation, lazy expansion via `childrenProvider`,
  declarative initial filter value. PRD §11.
