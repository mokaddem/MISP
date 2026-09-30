# PRD: Enrichment from the graph

**Status:** CONTRACT 2026-09-30 — §4 grilled and ruled (E1–E13, plus H1–H3 the grilling
added); phase B done, look B picked (§5.4). Next: phase C, wiring (§8).
**Owner:** Sami Mokaddem (Claude-assisted)
**Created:** 2026-09-30
**Parent:** [`pivot-explorer-v16-prd.md`](pivot-explorer-v16-prd.md), which ruled enrichment its own
later pass (R3: "a pivot — `fetch` = the module query, `save` = persisting into the event").
**Reuses:** the Value Profile's enrichment engine
([`../../prd/value-profile-live/28-enrichment.md`](../../prd/value-profile-live/28-enrichment.md),
[`13-auto-run.md`](../../prd/analyst-profile/13-auto-run.md)) and pivotick's pivot interface
(`~/git/pivotick/prd/pivot-enrichment-interface.md`, complete).

---

## 1. Why

An analyst on the Pivot Explorer can already walk outwards through MISP's own data:
correlations, another event's contents, tags, clusters, feeds. What they cannot do is ask the
outside world. To learn what `8.8.8.8` resolves to, who registered `deadnxuyla.ru`, or what
passive DNS knows, they leave the graph for the Value Profile's Enrichment tab. That tab answers
in a table, and nothing it finds can join the picture they were building.

Both halves already exist:

- **MISP** has an enrichment engine that picks the modules a value is eligible for, runs one,
  normalises what comes back into attributes and objects, marks which returned values MISP
  already holds, and stores the answer per organisation so it is not asked twice. It is the
  Value Profile's, and it renders HTML only.
- **Pivotick** has the whole pivot flow: offer, count, narrow, fetch, triage in Review, ingest,
  undo, and a `save` hook with a ledger of unsaved results.

This PRD joins them: one more pivot, offered on every explorer host, whose results land on the
canvas like any other pivot's.

### State

| Part | Status | Note |
|---|---|---|
| Contract (§4 ruled, §5 frozen) | ✅ | grilled 2026-09-30 |
| Non-enrichment deny-list, `ModuleRole` (§5.1) | ⬚ | E7; the Value Profile tab honours it too |
| JSON endpoints on `ValuesController` (§5.1) | ⬚ | H1, H2; the model methods already return arrays |
| ACL entries | ⬚ | `perm_add` for all three (E11) |
| *Enrich* pivot in the shared kit (§5.2) | ⬚ | both hosts |
| Landing, `enrichment` edge kind, `scope: 'module'` (§5.3–§5.4) | ⬚ | E4, E5 |
| Run toast (§5.5) | ⬚ | E6 |
| Prototypes: result node look + edge (§8, phase B) | ✅ | B picked (§5.4); three pivotick asks filed |
| Unit tests, acceptance (§7) | ⬚ | |
| Save into the event (§5.7) | — | E8: later pass |

## 2. What the analyst sees

Select an attribute node — or, on the value page, a value node — and open *Pivot ▸*. A new entry,
**Enrich**, is offered when at least one expansion module accepts the node's type and the viewer
may run modules. A Read Only user never sees it.

The pivot panel has one control, **Module**: every eligible module, each labelled with what the
organisation already knows about it for this value — `circl_passivedns — 3 h ago` with its
result count, `whois — timed out yesterday`, or the bare name if it has never been asked. The
modules the analyst profile ticks, and those with a fresh stored answer, start ticked. The
analyst adjusts the ticks and presses Fetch.

A module whose stored answer is fresh answers at once, from the store. The others are asked, five
at a time. What comes back goes to Review:

- **Objects** a module returned (a `passive-dns` record, a `dns-record`) as closed object nodes
  with their attributes.
- **Attributes** it returned (an IP, a domain) as attribute nodes; a legacy module's bare values
  the same way, marked as untyped by the module.
- Each joined to the node it was run on by an **enrichment** edge labelled with the module.
- A returned value MISP already holds says so on the node, and in the sidebar its value opens
  the value's profile.

A single toast says what did not come back: a module that answered with nothing, one that
errored, one that timed out, an answer cut to 200 of its results.

Ingest lands the ticked rows; undo takes them away, like any pivot. The Provenance legend gains
**From enrichment**, so everything the outside world said can be hidden at once. The results are
the canvas's only: nothing is written into MISP. The stored answer, though, is the
organisation's, so the Value Profile's Enrichment tab shows the same run afterwards.

## 3. What the engine gives, and what it does not

Measured from the code on 2026-09-30. `VP` is `app/Model/ValueProfile.php`.

**It is scoped to a value and a type, not to an attribute.** `enrichmentRun(user, value, module,
type, mode)` (VP:14918) takes no attribute id. For a `misp_standard` module it sends the reader's
*first* visible attribute holding that value and type (`enrichmentOccurrence`, VP:15265), not
necessarily the one clicked, and it sends no object or event context. A `simplified` module gets
`{type: value}`. The same lookup is what keeps a user from enriching a value they cannot see.

**It picks modules by the value's types.** `enrichmentCatalogue` (VP:14182) unions, over every
type the value is held as, the enabled expansion and hover modules that accept it
(`enrichmentEligible`, VP:14843, over `Module::getEnabledModules`: the service switch, the
per-module switch, the per-org restriction). Every call is a round trip to the module server.
Each row carries `name`, `kinds`, the types, the output `format`, a description, `locality`, and
the latest stored answer. `can_run` is `perm_add` (VP:14241). **Nothing filters out modules that
are not enrichment** — sample submitters, uploaders, document transforms — so the Value
Profile's tab offers them today.

**It stores every answer, per organisation.** Table `value_enrichment_runs`, unique on
`(org_id, sha256(value), module, type)`, upserted: state, when, how long it took, the counts, and
the shaped result. `mode=auto` serves a fresh stored answer (`max_age_hours`, default 24) and asks
only when there is none; `mode=stored` never asks; anything else always asks. A `running` row is
the in-flight lock. Failures are stored too.

**It runs synchronously, one module per request.** Timeout `Plugin.Enrichment_timeout`, 10 s by
default. The Value Profile caps itself at 5 parallel requests and closes the session first so they
overlap.

**It normalises the answer** (`enrichmentShape`, VP:15340) into:

| Key | Holds |
|---|---|
| `state` | `ok` · `silent` (answered with nothing) · `error` (the module said it cannot) · `refused` (MISP's trigger declined) · `unreachable` · `timeout` · `ineligible` · … |
| `attributes[]` | `{type, value, category, comment, to_ids, correlates, known}` |
| `objects[]` | `{name, meta_category, description, comment, attributes[{relation, type, value, …, known}]}` |
| `elements[]` | a legacy module's flat `{types[], value, known}`, with no structure |
| `total`, `shown`, `capped` | 200 results at most per answer, the true total kept |

**A `misp_standard` answer echoes the attribute it was asked about** in `attributes[]` — every
recorded answer on the dev instance does (`8.8.8.8` as `ip-dst` comes back in circl_passivedns',
mmdb_lookup's and ipasn's). Landed as-is it would draw a loose duplicate of the origin; §5.3 drops
it. On that instance every real result is therefore an **object**: no enabled module returns a
loose attribute or a legacy element.

`known` (`enrichmentKnown`, VP:15487) is one ACL-scoped prevalence probe over the returned values
that MISP correlates on, leaving out the value asked about. It is recomputed on every read, never
stored.

**It writes nothing into events.** Write-back exists elsewhere: `EventsController::
handleModuleResults($id)` takes resolved MISP-format JSON for an event the user can modify.

**Legacy output is shrinking, not gone.** misp-modules' `modules-answering-in-objects` branch
(18 commits over `main` at `953bd2b6`, unmerged) moves 15 flattening modules to `misp_standard`
— dns, reversedns, geoip_*, securitytrails, urlscan, hibp, vulners, … On its tip 39 non-hover
expansion modules still answer `simplified`; 15 of those are real enrichment (apifreaks,
apiosintds, backscatter_io, domaintools, eupi, intel471, iprep, mwdb, otx, passivetotal,
threatcrowd, threatfox, threatminer, vulndb, and whois by choice), the other 24 are not
enrichment at all (E7).

## 4. Decisions — ruled 2026-09-30

| # | Question | Ruling |
|---|---|---|
| H1 | **Which hosts, and where the endpoints live.** | **Value-scoped JSON on `ValuesController`**, beside the `viewEnrichment*` actions; the pivot is in the shared kit. The event page and the value page (attribute *and* `value` nodes) get *Enrich* now, the Intelligence Graph when it lands. The engine is value-scoped, so an event id would only tie the feature to one host. |
| H2 | **How a node learns it is eligible.** `appliesTo` must be synchronous; the catalogue costs a module-server call per request. | **Two reads.** A type → modules map fetched once per page decides eligibility, by the **node's own type** (a value node: each type it is held as). The stored answers are read in `summarize`, for the origin only. The graph does not union the value's other types the way the Value Profile does: the node *is* that type. |
| E1 | **One pivot or many.** | **One *Enrich* pivot, a `multiselect` Module facet.** Pivotick's facet option is `{label, value, count}`, so the stored state is a label suffix (`— 3 h ago`, `— timed out yesterday`) and the count is the stored total. **Pre-ticked:** the profile's `ticked` and `auto` modules, and every module with a fresh stored answer (it costs nothing). The pivot's `total` is the stored totals of the ticked modules; a never-asked module adds 0. |
| E2 | **Which modules.** | **Expansion only.** A module that is hover *and* expansion stays, run as expansion; hover-only and cortex modules are out. |
| E3 | **Value or attribute.** | **Value-scoped** (follows from H1), which buys the per-org store and answers shared with the Value Profile. Object-input modules stay out. |
| E4 | **Where results sit, and what they are.** | **Attribute: `enr:<type>:<value>`**, one node shared across modules, origins and runs, each module adding its own edge. **Object: `enr-obj:<module>:<hash>`**, the hash over its name and sorted `(relation, type, value)` triples, so identical records collapse, reruns keep their ids and two origins never collide. Its attributes are its own children, `<object id>:<relation>:<value>`, never shared `enr:` nodes. A result is never merged into a MISP attribute or value node; `known` says the value is in MISP. |
| E5 | **Provenance.** | **`scope: 'module'`, labelled *From enrichment*.** The Provenance legend and facet appear whenever a module result is on the canvas, even on a host that turns provenance off: *This event · Elsewhere · From enrichment* on the event page, *In MISP · From enrichment* on the value page. |
| E6 | **What did not come back.** | **One `graph.notifier.warning` per run**, listing each module that gave nothing and why, and each capped answer. A run where no module answered throws with their reasons. No pivotick change: the notifier is already the explorer's, and the stored failure shows in the facet label next time. |
| E7 | **Legacy text modules, and modules that are not enrichment.** | **A legacy element lands as its first listed type**, flagged untyped by the module, with its other types in the sidebar; an ip-src/ip-dst duplicate against a typed module's answer is accepted. **A shipped deny-list, `ModuleRole`**, removes the modules that are not enrichment — submitters and uploaders (they send a sample out, with side effects), document transforms, query builders and validators — with a profile override like `ModuleLocality`'s. The Value Profile's tab honours it too. |
| E8 | **Saving into the event.** | **Not this pass.** No `save` is declared, so nothing is counted unsaved. The value page has no event to save into; §5.7 stays as the seam. |
| E9 | **Potential.** | **No rim badge for enrichment**, stored answer or not. |
| E10 | **Many origins.** | **5 calls in flight; at most 25 asked pairs** (node × module) per run, stored answers not counted. Over it, `fetch` refuses **before any call**, with the number, the limit and the way out. `maxCandidates: NODE_BUDGET` as every other pivot. |
| E11 | **Readers who may not run modules.** | **No `perm_add`, no *Enrich*** — `appliesTo` returns nothing and the endpoints refuse. Among the shipped roles only *Read Only* lacks it, and a role's permission tier 0 is what clears it (`Role.php:52`, `:80`). |
| E12 | **The analyst profile's declarations.** | **`ticked` and `auto` pre-tick; `never` removes the module from the facet.** Nothing runs without a press on the graph. |
| E13 | **A result that is known.** | **Its sidebar value links to `/values/view/<b64>`** (new tab) and is a hover-card trigger (`MISP.value_hover_card`), as an object attribute's value is — **only when `known`**. Unknown values get neither. A *Where this is in MISP* pivot stays out. |

## 5. Design

### 5.1 Server

**`app/Lib/Tools/ModuleRole.php`** — a shipped list of modules that are not enrichment, in
`ModuleLocality`'s style, with the analyst profile able to override it per module.
`enrichmentEligible` drops them, so the Value Profile and the graph agree.

Three JSON actions on `ValuesController`, ACL `perm_add` (E11), each resolving through the
engine with no new engine code:

- `GET /values/enrichmentTypes.json` → `{types: {<type>: [module names]}, modules: {<name>:
  {format, description}}, profile: {ticked: {<type>: [names]}, never: {<type>: [names]}}}`.
  Expansion modules only (E2), `ModuleRole` applied (E7), `auto` folded into `ticked` (E12). One
  `getEnabledModules` per page.
- `POST /values/enrichmentStored.json` with `{items: [{value, type}]}` → for each item, per module,
  the stored answer's `state`, `ran_at`, `total`, and whether it is fresh. A store read only.
  Items the user holds no visible attribute for come back empty, as if never asked.
- `POST /values/enrichmentRun.json` with `{value, type, module, mode}` → the shaped answer (§3).
  `session_write_close()` first, as the Value Profile does. The value must be one the user can
  see under that type (`enrichmentOccurrence`), or the answer is `ineligible`.

### 5.2 The pivot

```js
{
    id: 'enrich', label: 'Enrich',
    appliesTo: nodes => canRun ? nodes.filter(n => typesOf(n).some(t => typeMap[t])) : [],
    summarize: async (nodes, narrowing) => /* enrichmentStored for the origin */ ({
        total: /* stored totals of the ticked modules */,
        facets: [{ key: 'module', label: 'Module', type: 'multiselect',
                   options: /* eligible, minus `never`; label = name + stored-state suffix,
                               count = stored total */,
                   default: /* profile ticked/auto + fresh stored */ }]
    }),
    fetch: (nodes, narrowing, ctx) => /* refuse over 25 asked pairs; else pairs 5 at a time,
                                         ctx.signal forwarded; one toast for what did not come */
    maxCandidates: NODE_BUDGET
}
```

In the shared kit, so both hosts list it. `typesOf(n)` is the attribute's type, or every type a
value node is held as. No `save` (E8), no `autoIngest`, no `setPotential` (E9).

### 5.3 Landing

| Result | Lands as | Joined by |
|---|---|---|
| `attributes[]` | attribute node `enr:<type>:<value>` — **except the echo** of the origin's own value and type, which is dropped | `enrichment` edge from the origin, label = module |
| `objects[]` | closed object node `enr-obj:<module>:<hash>`, its attributes as its own children | `enrichment` edge from the origin |
| `elements[]` | attribute node `enr:<first type>:<value>`, flagged untyped | `enrichment` edge from the origin |

The node data carries `scope: 'module'`, the module name, `known`, the other candidate types of an
untyped element, and whether the answer came from the store and how old it is. The sidebar shows
them; a `known` value is a profile link and a hover-card trigger (E13).

### 5.4 Look

**Picked in phase B (2026-09-30): candidate B, a mark on the node**
(`prd/pivot-enrichment/candidates/b-mark/`).

- **The node** is drawn by `misp-pivot-nodes` exactly as MISP's own attribute or object, and wears
  **one pivotick badge at `ne`**: a white `fa-wand-magic-sparkles` (the icon MISP's UI already
  puts on enrichment) on a disc of MISP's `--bs-enrichment` hue, lightened to read on the canvas
  — `#6A6396` light, `#8C84B5` dark. Same mark on every result of every kind. `ne` because none of
  the explorer's badges (analyst `nw`, feed hit `sw`, tags `se`) can land on a result, and edges
  from the origin arrive from the upper left and cover `nw` at S.
- **Its `title`**: *From enrichment — <modules>*, *Not in MISP: a module said this*, plus
  *Untyped* and *Stored answer, N h ago* when the data says so.
- **A group** (Simplify) wears the mark only when every member is a result. The explorer's group
  key gains `scope`, so a group never mixes MISP records with results.
- **The `enrichment` edge**: solid, 1.25 px, the same violet, labelled with the module. Solid
  because dashes already mean correlation, feed, server and tag.
- **The legend**: *From enrichment* takes the violet, *In MISP* a neutral grey.

**Against v16 D2c** ("from another event" gets no canvas encoding): the mark does not fade, tint or
restyle the node, so D2c's objection — a cue that judges the subject — does not apply; it states a
fact about the data, that MISP does not hold it. Phase B drew D2c's own answer (candidate A, the
edge alone) beside it: it fails whenever the edge is not drawn — the `enrichment` legend row
toggled off, focus mode, a result dragged away — and the results become MISP's nodes.

**Library asks** (pivotick, `prd/misp/`): `focus-tier-keeps-badges.md` — the hover/selection card
draws no badges and covers the base drawing's, so the mark (and every MISP badge) vanishes when
the analyst looks closest; `badge-screen-size-floor.md` — the mark is a 3 px dot at zoom 0.5;
`legend-entry-icon.md` — the legend can only show a dot, not the mark. B ships without them and
gains when they land.

**Wiring gotcha:** a legend section whose `entries` function returns `[]` makes pivotick derive
entries of its own (the Element list, repeated). The value page's Provenance section must return
its two entries whenever it is shown, not an empty list before results land.

### 5.5 What did not come back

One toast per run (E6). The states that are not results: `silent` → "answered with nothing";
`error` → the module's message; `timeout`, `unreachable` → as the Value Profile words them;
`refused` → "MISP's enrichment workflow declined the query"; a `capped` answer → "*module*: 200
of 1,374 shown". The refusal over the pair cap reads: "30 module queries would be sent; 25 at most per
run — untick modules or select fewer nodes." Nothing a user may not see is counted or hinted at.

### 5.6 Cost

A module is asked at most once per value, type and organisation within the freshness window,
whoever asks, from the graph or the Value Profile. Each ask holds one PHP worker for up to the
timeout; 5 at once is what the Value Profile already accepts. The module server is asked for its
module list once per page, not once per node.

### 5.7 Saving (later pass)

When E8 is taken up: `save` builds the MISP-format JSON of the ingested nodes still unsaved and
posts it to `handleModuleResults` for this event — event-page host only, only where the origin
attribute is this event's own and the user can modify it. The library's ledger then counts and
offers them.

## 6. Out of scope

- Writing results into an event (E8), a *Where this is in MISP* pivot (E13).
- Hover-only and cortex modules (E2), object-input modules (E3), per-query module options
  (`userConfig`): no expansion module declares one, and letting a reader set `meta.config` is a
  credential leak.
- Auto-running modules on the graph (E12), enrichment potential badges (E9).
- Enrichment of events, objects as a whole, tags or clusters.

## 7. Acceptance

Against the dev instance, logged in, dev server on this worktree:

1. On an event with an IP attribute, *Enrich* is offered on it and not on an object or event node;
   on the value page it is offered on the value node.
2. The Module facet lists only expansion modules that accept the type — no submitter, uploader or
   transform — each with its stored state, and starts with the profile's and the fresh ones ticked.
3. A run with two modules, one answering and one not, lands the first's results joined by
   `enrichment` edges, and one toast says why the second did not.
4. Running the same module again inside the freshness window answers from the store, without
   asking the module server.
5. The Value Profile's Enrichment tab for that value shows the graph's run.
6. A Read Only user is not offered *Enrich*, and `enrichmentRun.json` refuses them.
7. Undo takes the landed results away; the stored answer stays.
8. A legacy module's elements land as untyped attributes; none is dropped.
9. The same record returned from two origins lands as one object node with two edges.
10. Asking more than 25 pairs is refused before any module is called.
11. *From enrichment* in the Provenance legend hides every result, on both hosts.

## 8. Phases

- **A — Contract.** ✅ Grilled 2026-09-30; rulings in §4, JSON frozen in §5.1.
- **B — Prototypes.** ✅ B picked 2026-09-30 (§5.4). One round, three cold-built candidates for the result node's *From
  enrichment* look and the `enrichment` edge — **A** the edge alone (D2c-consistent), **B** a mark
  on the node, **C** a drawing of its own. Kit in `prd/pivot-enrichment/` (`brief.md`, `frame.html`,
  `capture.mjs`): the value page's full graph on `8.8.8.8` with the real vendored stack, and a
  fixture-backed *Enrich* pivot (`enrich-baseline.js`, §5.2–§5.3 as ruled) run on the centre from
  answers recorded through `forEnrichmentRun(mode=stored)` — mmdb_lookup, ipasn, whois (error),
  and circl_passivedns (199 objects, 200 of 1,376) as the stress case. Kept local. The facet and
  toast wording are settled here, not prototyped.
- **C — Wiring.** `ModuleRole`, the endpoints, the pivot, tests, acceptance.

## 9. Files (expected)

| File | Change |
|---|---|
| `app/Lib/Tools/ModuleRole.php` | new: the non-enrichment deny-list (E7) |
| `app/Model/ValueProfile.php` | `enrichmentEligible` drops `ModuleRole`'s modules; array forms for the three actions |
| `app/Controller/ValuesController.php`, `app/Controller/Component/ACLComponent.php` | `enrichmentTypes`, `enrichmentStored`, `enrichmentRun` + ACL |
| `app/webroot/js/pivot-explorer.js` | the pivot in the kit, landing, `enrichment` kind and style, `scope: 'module'` legend and facet, the result badge in `nodeBadges` and on groups, `scope` in the Simplify group key |
| `app/webroot/js/value-neighbourhood.js` | list the pivot; the provenance legend when module results exist |
| `app/webroot/js/pivot-sidebar-model.js` | module, stored age, untyped types, `known` links |
| `tests/js/pivot-explorer-graph.test.js` | the pivot, ids, landing |
