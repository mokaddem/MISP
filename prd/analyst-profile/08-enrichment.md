# PRD: Analyst Profile — phase 7, enrichment defaults

**Scope note. Blocked, and honestly so.** Depends on phase 1
([`02-store.md`](02-store.md)) for the section, and on a store that **does not
exist** for the behaviour.

This is the one part of the ask this feature cannot deliver on its own. The
section is specifiable; the badge is not, until something records that a module
ran.

## 1. The ask, and what blocks it

> *A profile also contains default enrichment modules to be run when opening a
> value profile page (result to be displayed in a badge at the top level).*

Three separate blockers, all pre-existing, all recorded in
`../value-profile-tabs/04-enrichment.md` §11 as that tab's own deferred items.

### 1.1 Nothing records that a module ran

`Module` is `useTable = false`. There is no per-value, per-module last-run
timestamp anywhere in MISP. Without it, "run the default modules on page open"
means **run them on every page open** — nine third-party queries per visit,
per analyst, forever.

`../value-profile-writes.md` §6.4 proposes the answer: a plain cache table keyed
by value and module holding last run, by whom, status, and the dismissed
elements — deliberately kept out of the assertion stores because it is evictable
and instance-owned rather than org-owned.

**That table is this phase's hard prerequisite.** It is specified there and
built by nobody.

### 1.2 The interactive path is synchronous whatever the setting says

`Event::enrichmentRouter()` returns before its own `MISP.background_jobs`
branch — it returns at `Event.php:7997` and strands the branch at `7998`. Only
`POST /attributes/enrich` queues a job.

So a page that runs even three modules on load blocks the render on three HTTP
round trips to third parties, each bounded by `Plugin.Enrichment_timeout`
(10 s; Cortex 120 s). **The badge needs the queued path**, which means either
fixing `enrichmentRouter` or routing this page through the queueing endpoint.

### 1.3 No cost or quota metadata exists

Module introspection carries `name`, `mispattributes`, `meta.description`,
`meta.module-type` and `meta.config` — and nothing about rate limits, credits,
or whether a module leaves the building. The Enrichment tab's cost chips are
already fixture-only for this reason, and
`../value-profile-tabs/04-enrichment.md` §11 says the map *"should live next to
the module list, not in this page"*.

A profile shared to an organisation that auto-runs a paid module spends
somebody else's quota. Without cost metadata, the profile cannot even warn.

## 2. What is specifiable now

The section itself, and its resolution against instance policy. This can land
with phase 1 and sit inert.

```json
"enrichment": {
  "auto_run": {
    "ip-src":  ["virustotal"],
    "ip-dst":  ["virustotal"],
    "domain":  ["dns"],
    "md5":     []
  },
  "cost_posture": "local_only",
  "max_age_hours": 24
}
```

**`auto_run` is keyed by attribute type**, because module validity is
type-scoped — `Module::getEnabledModules($user, $type)` filters on
`meta.module-type` and the tab's own header names the type for exactly this
reason (*"9 modules valid for ip-dst"*). A value with several types resolves
the union, deduplicated.

**`cost_posture`** is `local_only` (default) | `allow_external` | `ask`.
`local_only` auto-runs nothing that leaves the instance, which is the only
defensible default for a setting that can be set by one person and applied to
their whole organisation.

**`max_age_hours`** is the reuse window — how stale a cached result may be
before the page re-runs the module. Meaningless without §1.1's table.

### 2.1 A profile can only narrow, never widen

`Module::getEnabledModules()` (`Module.php:111`) filters on three things: the
instance setting `Plugin.Enrichment_<name>_enabled`, the requested type, and
`canUse()` (`Module.php:412`) — which is site-admin-always, else
`Plugin.Enrichment_<name>_restrict` must be empty or equal the user's `org_id`.

So the instance decides what exists and a profile picks from that set. Two
honest states follow, and neither may be a silent drop:

- **A profile names a module the instance has disabled.** Rendered as a stated
  condition on the module list, and linkable from the profile editor.
- **A profile shared to an org names a module restricted to one org.**
  `_restrict` is a single `org_id`, not a list, so this is reachable in normal
  use. Same treatment.

Silently dropping either would mean a profile whose stated enrichment policy is
not the one in effect, which is the class of quiet lie `01-profile.md` §1.3
forbids.

## 3. The badge

*"Result to be displayed in a badge at the top level"* — the fact strip or the
banner, above the tabs.

Two properties it must have, both consequences of §1:

- **A pending state.** With the queued path, the page cannot promise a result
  on first paint. The badge renders *queued*, then *n of m answered*, then a
  result — which is the same progress vocabulary the Enrichment tab's running
  state already draws (*n of m modules*, because there is no progress inside a
  module: one `POST /query` per module and nothing streams).
- **It must not become the frame hazard again.** `../value-profile-page.md`
  §1.4 records this: the tab badges, the fact strip and the banner chips are
  built in one fixture call, and every panel conversion has left a number in
  the frame contradicting the panel it names. Two badges were corrected in
  phase 23 and a third dropped its number entirely in phase 24. **A new badge
  in the frame, fed by a different code path from the Enrichment tab it
  summarises, is that hazard by construction.** It reads from the same
  aggregate as the tab or it does not ship.

## 4. Q10 — how much is in scope

**Open.** Three shapes:

- **A.** This phase builds `../value-profile-writes.md` §6.4's cache table and
  fixes the queued path, then the badge. Largest, and it makes a profile
  feature responsible for enrichment plumbing two other documents already own.
- **B.** The section ships inert with phase 1; the badge waits for the cache
  table to be built by whoever owns `value-profile-writes.md`. The profile
  *declares* its module list, the editor lets you set it, and nothing auto-runs.
- **C.** Drop `auto_run` from v1 entirely; the profile holds no enrichment
  config.

**Recommendation: B.** The declaration is genuinely useful before the
behaviour — it is the only place an analyst can record which modules they care
about for a type, and the Enrichment tab could pre-select them without running
anything. It also means the profile's shape is complete on day one, so adding
the behaviour later is not a format change to every stored profile.

C is wrong because the ask names enrichment explicitly and a profile without it
answers a smaller question than the one asked. A is wrong because it makes this
feature's schedule depend on fixing `enrichmentRouter`, which is a bug in event
enrichment with its own blast radius.

## 5. Verification, for the part that can ship under B

1. `auto_run` set for three types; the profile round-trips through save,
   export and import unchanged.
2. A module named that the instance has disabled: stated condition in the
   editor, no error, and the Enrichment tab's rail is unaffected.
3. A module named that `_restrict` reserves for another org, viewed as a member
   of neither: stated condition.
4. `cost_posture: local_only` with an external module in `auto_run`: the
   editor states the conflict rather than silently ignoring one of the two.
5. Nothing runs. No third-party request is made by any page load. Asserted with
   the modules service unreachable — the page must be indistinguishable from
   the same load with it reachable.

## 6. Out of scope

- Building `../value-profile-writes.md` §6.4's cache table.
- Fixing `Event::enrichmentRouter()`.
- The cost metadata map (§1.3), which belongs next to the module list.
- Enrichment *results* becoming occurrences. `../value-profile-writes.md` §6.4
  has the answer — a new event owned by the analyst's own org — and it is a
  write, which this feature does not do.
- The badge, under recommendation B.
