# PRD: Value Profile — Enrichment tab

**Phase 12.** Implements candidate **`E2`**, chosen 2026-08-25.
Artifact: <https://claude.ai/code/artifact/ee197bd7-e9ec-46f3-9b51-c3797236a4ee>
Depends on `00-shared.md`.

## 1. What ships

**The module is the navigation: a rail of nine that always carries its own
state, and one module's results at a time beside it.**

This tab has six states and most visits find several at once — never run,
staged, running, answered, silent, timed out. `E2` was chosen because it is the
only candidate where all six are the *same object*, a rail row. "Nothing queried
yet" is a column of dashed rows rather than an empty page, and a module that
timed out is one row wearing a clock while the other eight are untouched — a
timeout is structurally incapable of reading as total failure. It is also the
only candidate whose shape does not change between three modules and thirty.

**Not taken.** `E1`'s full staging tray — the panel that prices a whole run in
quota and third-party exposure before you commit to it — is deferred per the
2026-08-25 decision. `E2` as drawn keeps a compact version in its rail footer
(the two cost chips and the run button), so the argument is present; what is
deferred is `E1`'s treatment of the spend decision as the page's organising
idea.

## 2. Layout

One full-width slot (`right => null`). The panel owns an internal
`.vp-e-split`: rail at ~40%, results pane filling the rest. Not a Bootstrap
row — the rail scrolls independently once there are more modules than fit.

## 3. Controller

| Action | URL | Renders |
|---|---|---|
| `viewEnrichment($b64value)` | ajax | `value_enrichment` |

One endpoint. The rail and the pane are one fragment because the rail's state
chips and the pane's contents are the same data read two ways, and because
switching modules in this pass is client-side against data already in the DOM
(§2.11) — no request per module.

## 4. Templates

```
app/View/Themed/Overmind/Elements/Values/View/
    value_enrichment.ctp          the split; owns the panel header
    value_enrichment_rail.ctp     the nine rows, their groups and the tray
    value_enrichment_pane.ctp     one module's results
```

## 5. Fixture additions

```php
'enrichment' => [
    'type'         => 'ip-dst',        // the type the modules were matched on
    'last_run'     => '2025-08-24 09:14',
    'pending'      => 23,              // elements awaiting review
    'service'      => ['reachable' => true, 'checked' => '2025-08-25 07:02'],
    'modules'      => [
        [
            'name'      => 'virustotal',
            'kind'      => 'expansion',    // expansion | hover | cortex
            'state'     => 'ok',           // ok | timeout | none | never
            'elements'  => 6,
            'new'       => 2,
            'ran_at'    => '2025-08-24 09:14',
            'took'      => 1.8,
            'shape'     => 'misp_standard',
            'cost'      => ['quota' => true, 'external' => true],
            'stale_days'=> 1,
        ],
        // shodan   => state timeout, "Gave up at 10 s"
        // rbl      => state none,    "Queried, nothing back"
        // Abuse_Finder_3_0 => kind cortex, state never in this run, 83 days stale
        ...
    ],
    'results'      => [
        'virustotal' => [
            'delta' => ['new' => 2, 'previous_run' => '2025-08-11 14:02',
                        'unchanged' => 4],
            'objects' => [
                ['name' => 'virustotal-report', 'attributes' => 3,
                 'is_new' => true,
                 'elements' => [
                     ['relation' => 'permalink', 'type' => 'link'],
                     ['relation' => 'detection-ratio', 'type' => 'text'],
                     ['relation' => 'last-submission', 'type' => 'datetime'],
                 ]],
            ],
            'attributes' => [
                ['type' => 'hostname', 'to_ids' => true, 'date' => '2025-08-24',
                 'is_new' => true],
                ['type' => 'domain', 'to_ids' => true, 'date' => '2025-08-24',
                 'known' => true],     // "Already in MISP"
                ['type' => 'url', 'to_ids' => true, 'date' => '2025-08-24'],
            ],
            'dismissed' => 1,
        ],
    ],
],
```

Module names are MISP's real enabled modules for an IP; **no fabricated
verdicts, scores, ASNs or hostnames** (§7.4). A returned element is a type and
a shape, not an intelligence claim.

All four demo values supply `enrichment`. The unknown value supplies the module
list with every `state => 'never'` and no `results` — which is the "nothing
queried yet" state, and is the majority case in production.

## 6. The panel header

Title **Enrichment**, sub-line `9 modules valid for ip-dst · last run
2025-08-24 09:14 · 23 elements awaiting review`, and a `Review all 23` action.
The type is named because module validity is type-scoped: a reader has to know
which of the value's three types the nine were matched against.

## 7. The rail — `value_enrichment_rail`

Top: `Select all` and `3 of 9 selected`.

Then rows, grouped, with the group header naming the group's size:

- **`All results`** — a row above the groups, `23 elements across 6 modules · 3
  new`. This is the one addition `E2` makes to the direction it came from: the
  rail costs you cross-module reading, and this row buys it back. Selecting it
  puts the merged set in the pane.
- **`Ran 2025-08-24 09:14 (8)`** — the last run's modules.
- **`Not in the last run (1)`** — modules valid for this type that the last run
  did not include, e.g. the Cortex analyser.
- **`Never run (9)`** — the whole rail in the untouched state.

Each row carries, in one line: an optional kind chip (`Cortex`, because it is a
different service on a different port with a different timeout), the module
name, a sub-line, a state dot, and a staleness chip.

The sub-line is the state in words, and the four wordings are the design:

| State | Sub-line | Dot |
|---|---|---|
| answered | `6 elements · 2 new` | ok |
| timed out | `Gave up at 10 s` | timeout |
| silent | `Queried, nothing back` | none |
| never run | the cost chips (`Spends quota`, `Third party`) | — |

Staleness: `1 d`, `83 d`, or `Never`. A module nobody has ever run is not stale,
it is unused, and the chip says so.

Rail footer, the tray: the cost chips for the current selection (`2 spend
quota`, `3 query a third party`), a `Run 3 selected` button, and a service line
(`Module service reachable`). Service-down is a distinct state from
"nothing queried yet" and lives here, because a rail of dashed rows with an
unreachable service means something different from the same rail with a healthy
one.

## 8. The pane — `value_enrichment_pane`

Header: the module name, a provenance line `Expansion · misp_standard · ran
2025-08-24 09:14 in 1.8 s`, a status chip (`6 elements`), and two actions:
`Re-run` and `Add all 6` — both disabled.

Then, in order:

1. **The delta band** — *"2 new values since the run on 2025-08-11 14:02. The
   other 4 elements were already returned last time."* plus a `Show only new`
   toggle. Analysts re-run enrichment constantly; the delta is the reason they
   look at this pane at all.
2. **One card per returned object** (`.vp-e-obj`): the object template name, its
   attribute count, a `New since 2025-08-11` chip where it is new, per-object
   actions, and one row per element showing relation and type.
3. **Loose attributes** (`.vp-e-el`): the type badge, a `to_ids` chip where set,
   the date, and one of two provenance chips — `New since …` or `Already in
   MISP` (title: *"This value already exists in MISP"*). The second is what
   stops an analyst adding a duplicate.
4. **Per-element actions on every row**: `Add to event`, `New event`, `Dismiss`.
   Never per module. MISP enrichment returns attributes and objects, and the
   decision to keep one is per element — a module-level "accept" would write
   things nobody looked at.
5. **The dismissed footer** — `1 element dismissed in this run` with a `Restore`
   action, so a dismissal is visible rather than a disappearance.

## 9. States

Each rendered by the same rail-plus-pane, which is why `E2` was chosen:

| State | Rail | Pane |
|---|---|---|
| Answered | rows with counts and dots | results as above |
| Nothing queried yet | nine rows under `Never run (9)`, each showing its cost chips | a brief: what running would cost, what it would query, and that nothing has been sent |
| Running | `n of m` progress, per-row spinners | the module's partial output as it arrives |
| Timed out | one row with a clock, the other eight untouched | the timeout stated with the limit that produced it |
| Silent | `Queried, nothing back` | *"This module answered with nothing"* — different from never run, and said so |
| Service down | the tray's service line goes red | the reason, and no implication that the modules are stale |
| Unknown value | nine `Never run` rows | the brief |

**Nothing auto-runs.** Not on page load, not on tab switch, not on module
select. Running a module costs money and quota, and querying an adversary's
infrastructure announces your interest — so the untouched state is a designed
first-class state, not an empty one to apologise for.

## 10. Interactions

Working, client-side: selecting a rail row swaps the pane; `Select all`; the
selection updating the tray's cost chips and the run button's count; `Show only
new`; per-object and per-element expansion.

Disabled with a `title`: `Run n selected`, `Re-run`, `Add all`, every
`Add to event` / `New event` / `Dismiss` / `Restore`, and `Review all`.

## 11. Deferred, and what live data will hit

**Deferred by choice:** `E1`'s full staging tray.

**From §7.9 — most of this tab's state has nowhere to live yet:**

- **Nothing records that a module ran.** `Module` is `useTable = false`; there
  is no store anywhere for a per-value, per-module last-run timestamp. Every
  staleness chip, the group headers, and the entire delta band depend on
  persistence that does not exist. This is the tab's gating decision: a small
  new table, or the tab ships without staleness and without a delta.
- **A dismissal is not remembered.** Today the per-element checkboxes in
  `resolved_misp_format.ctp` are DOM state in one modal; re-running re-proposes
  everything the analyst rejected. The dismissed footer needs the same new
  store.
- **No cost or quota metadata exists.** Module introspection carries `name`,
  `mispattributes`, `meta.description`, `meta.module-type` and `meta.config` —
  nothing about rate limits, credits, or whether a module leaves the building.
  The cost chips need a curated map, and it should live next to the module list,
  not in this page.
- **There is no progress inside a module.** One `POST /query` under
  `Plugin.Enrichment_timeout` (10 s; Cortex 120 s). Progress can only ever be
  *n of m modules*, which is what the running state draws.
- **Enrichment is not queued on this path.** `Event::enrichmentRouter()` returns
  before its own `MISP.background_jobs` branch (`Event.php:7995` — unreachable
  code), so the interactive path is synchronous whatever the setting says. Only
  `POST /attributes/enrich` queues a job. A tab that runs nine modules
  synchronously in one request is not viable; this needs the queued path.
- **"Add to event" has no target.** Enrichment is scoped to one event and this
  value sits in seven, so the write needs an explicit event picker that no
  existing endpoint expects.
- **The module list is not cached** — a live `GET /modules` per render with a
  1 s
  timeout, which is why service reachability is a rendered state.
- **Cortex is a second list**, its own service and timeout. Merging it into one
  rail is a decision this design makes; it is not free.

## 12. Verification

1. `php -l` on all three elements.
2. `viewEnrichment` returns 200 for all four demo values; the panel resolves.
3. Malicious value: nine rail rows in three groups, an `All results` row, four
   distinct state wordings visible at once, the tray with cost chips and the
   service line, and a pane with the delta band, one object card and three loose
   attributes each carrying its own three actions.
4. Selecting a rail row swaps the pane without a request; the tray's chips and
   run count follow the selection.
5. Unknown value: nine `Never run` rows and the brief — no spinner, no empty
   page, and nothing sent.
6. Light and dark: the four state dots stay distinguishable, and the type badges
   are legible (the `00-shared.md` §9 fix).
7. No request leaves the browser on load or on tab switch — check the network
   panel. This is the one behavioural assertion the tab must pass.

## 13. Exit criterion

Artifact `E2` is recognisable in the browser; four module states read at a
glance in one rail; the untouched state is a full page rather than an empty
one; and nothing queries anything.
