# PRD: Enrichment from the graph

**Status:** DRAFT 2026-09-30 — every decision in §4 is **open, to be grilled**. Each carries a
recommendation so the grilling has something to push against; none is settled.
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

This PRD joins them: one more pivot on attribute nodes, whose results land on the canvas like any
other pivot's.

### State

| Part | Status | Note |
|---|---|---|
| JSON endpoints over the engine (§5.1) | ⬚ | the model methods already return arrays |
| ACL entries | ⬚ | `perm_add` to run, as the Value Profile |
| *Enrich* pivot (§5.2) | ⬚ | |
| Landing shape, `enrichment` edge kind, result provenance (§5.3–§5.4) | ⬚ | E4, E5 |
| Per-module outcome in Review (§5.5) | ⬚ | E6; may need a library ask |
| Prototypes (§8, phase B) | ⬚ | |
| Save into the event (§5.7) | ⬚ | E8 — maybe not this pass |
| Unit tests, acceptance (§7) | ⬚ | |

## 2. What the analyst sees

Select an attribute node and open *Pivot ▸*. A new entry, **Enrich**, is offered when at least
one enrichment module accepts the attribute's type and the viewer may run modules.

The pivot panel lists the eligible modules, each with what the organisation already knows about
it for this value: *answered 3 h ago · 12 results*, *timed out yesterday*, or nothing if it has
never been asked. The analyst ticks the modules to run and presses Fetch.

A module whose stored answer is fresh answers at once, from the store. The others are asked, a few
at a time. What comes back goes to Review:

- **Objects** a module returned (a `passive-dns` record, a `whois` record) as closed object nodes
  with their attributes.
- **Attributes** it returned (an IP, a domain) as attribute nodes.
- Each joined to the attribute it was run on by an **enrichment** edge labelled with the module.
- A returned value MISP already holds says so on the node.

Review also says what did not come back: a module that answered with nothing, one that errored,
one that timed out, one that returned only text.

Ingest lands the ticked rows; undo takes them away, like any pivot. The results are the canvas's
only: nothing is written into MISP (unless E8 decides otherwise). The stored answer, though, is
the organisation's, so the Value Profile's Enrichment tab shows the same run afterwards.

## 3. What the engine gives, and what it does not

Measured from the code on 2026-09-30. `VP` is `app/Model/ValueProfile.php`.

**It is scoped to a value and a type, not to an attribute.** `enrichmentRun(user, value, module,
type, mode)` (VP:15526) takes no attribute id. For a `misp_standard` module it sends the reader's
*first* visible attribute holding that value and type (`enrichmentOccurrence`, VP:15873), not
necessarily the one clicked, and it sends no object or event context. A `simplified` module gets
`{type: value}`. So an attribute inside an object enriches as its bare value. E3 asks whether that
is enough.

**It picks modules by the value's types.** `enrichmentCatalogue` (VP:14790) unions, over every
type the value is held as, the enabled expansion, cortex and hover modules that accept it
(`Module::getEnabledModules`: the service switch, the per-module switch, the per-org
restriction). Each row carries `name`, `kinds`, the types, the output `format`, a description, and
the latest stored answer. `can_run` is `perm_add`.

**It stores every answer, per organisation.** Table `value_enrichment_runs`, unique on
`(org_id, sha256(value), module, type)`, upserted: state, when, how long it took, the counts, and
the shaped result. `mode=auto` serves a fresh stored answer (`max_age_hours`, default 24) and asks
only when there is none; `mode=stored` never asks; anything else always asks. A `running` row is
the in-flight lock. Failures are stored too.

**It runs synchronously, one module per request.** Timeout `Plugin.Enrichment_timeout`, 10 s by
default. The Value Profile caps itself at 5 parallel requests and closes the session first so they
overlap.

**It normalises the answer** (`enrichmentShape`, VP:15948) into:

| Key | Holds |
|---|---|
| `state` | `ok` · `silent` (answered with nothing) · `error` (the module said it cannot) · `refused` (MISP's trigger declined) · `unreachable` · `timeout` · `ineligible` · … |
| `attributes[]` | `{type, value, category, comment, to_ids, correlates, known}` |
| `objects[]` | `{name, meta_category, description, comment, attributes[{relation, type, value, …, known}]}` |
| `elements[]` | a legacy module's flat `{types[], value, known}`, with no structure |
| `total`, `shown`, `capped` | 200 results at most per answer, the true total kept |

`known` is one ACL-scoped prevalence probe over the returned values that MISP correlates on,
leaving out the value asked about. It is recomputed on every read, never stored.

**It writes nothing into events.** Write-back exists elsewhere: `EventsController::
handleModuleResults($id)` takes resolved MISP-format JSON for an event the user can modify.

## 4. Decisions — open, to be grilled

| # | Question | Recommendation |
|---|---|---|
| E1 | **One pivot or many.** One *Enrich* pivot with a module picker, one pivot per module, or one per module *kind*? | **One pivot, a `multiselect` Module facet.** 146 modules as pivots would bury the Pivot menu. The facet shows each module's stored state beside its name. |
| E2 | **Which modules.** Expansion only, or hover and cortex too? | **Expansion and cortex; not hover.** Hover modules are built to be cheap and shallow for a popover; the Value Profile shows them in its own place. |
| E3 | **Value or attribute.** Is the engine's value-scoped run enough, or must the module see the clicked attribute, its object and its event? | **Value-scoped for this pass**, which buys the per-org store and the Value Profile's shared answers. Object-input modules (inputs matched on object name) stay out, and say so. Revisit if a module needs the object. |
| E4 | **Where results sit, and what they are.** A returned attribute has no uuid and belongs to no event. How is it identified, and does the same value from two modules become one node? | **One node per `type` + `value`** (`enr:<type>:<value>`), shared across modules and runs, each module adding its own edge. A returned object is one node per run (`enr-obj:<module>:<n>`), closed with its attributes. A result is never merged into a MISP attribute of the same value; `known` says the value is in MISP. |
| E5 | **Provenance.** Every node is *This event* or *Elsewhere* today (v16 D2). Module output is neither. | **A third value, *From a module*,** with its own look and its own entry in the Provenance legend filter, so an analyst can hide everything the outside world said at once. |
| E6 | **What did not come back.** A pivot's result is nodes and edges only, and a thrown error fails the whole run. How does Review say "whois timed out, passive DNS answered with nothing, shodan returned only text"? | **Ask pivotick for per-run notices** on `PivotResult` (a list of `{level, text}` Review draws above its rows). Until it ships, a MISP notification per module that did not answer. A run where no module answered fails with their reasons. |
| E7 | **Legacy text modules.** Half the modules answer `simplified`: bare values with a list of possible types. | **Land each as an attribute node of its first listed type**, flagged as untyped by the module. Never drop one silently. |
| E8 | **Saving into the event.** Pivotick's `save` hook would write an ingested result into MISP. | **Not this pass.** Results are canvas-only. Leave the seam: the pivot declares no `save`, so nothing is counted unsaved. A later pass adds `save` over `handleModuleResults`, for this event's own attributes and editors only. |
| E9 | **Potential.** Should an attribute wear a rim badge before anything is run? | **Only for a stored answer**, with its result count: "the organisation already asked, and 12 came back". An eligible-module count is not potential; it would put a badge on every IP. |
| E10 | **Many origins.** Ten attributes selected, three modules ticked, is thirty requests. | **Up to 5 at once, and a cap of 25 attribute × module pairs per run**, stated in the summary like any cap. Stored answers do not count toward it. |
| E11 | **Readers who may not run modules.** `perm_add` gates running. | **They get Enrich with stored answers only** (`mode=stored`): what their organisation already asked, nothing new. The facet says which modules have no answer. |
| E12 | **The analyst profile's declarations.** Profiles mark modules `ticked`, `never` or `auto` per type. | **`ticked` pre-ticks the facet; `never` hides the module; `auto` is not honoured here.** Nothing runs without a press on the graph. |
| E13 | **A result that is known.** Should a returned value MISP holds offer a way into MISP? | **Not this pass.** It says *In MISP* on the node. A later pivot, *Where this is in MISP*, would need a value search endpoint. |

## 5. Design (assuming the recommendations)

### 5.1 Endpoints

Two JSON actions on `EventsController`, beside `correlationCounts` and `cardElements`, so the event
the explorer is open on scopes them:

- `POST /events/enrichmentModules/{id}.json` with `{attribute_uuids}` → for each attribute, the
  catalogue rows for its value and type: `name`, `kinds`, `format`, `description`, and the stored
  answer's `state`, `ran_at`, `total`. One catalogue per distinct value. ACL `*`, like the view.
- `POST /events/enrichmentRun/{id}.json` with `{attribute_uuid, module, mode}` → the shaped answer
  (§3) for that attribute's value and type. ACL `perm_add` for any mode that may ask; `stored`
  open to readers (E11). `session_write_close()` first, as the Value Profile does.

Both resolve the attribute through `fetchAttributesSimple($user)`, so an attribute the user cannot
see is not enriched, and both call the existing `ValueProfile::forEnrichment*` methods. No new
engine code.

### 5.2 The pivot

```js
{
    id: 'enrich', label: 'Enrich',
    appliesTo: nodes => nodes.filter(n => isAttribute(n) && eligible(n)),
    summarize: (nodes, narrowing) => ({
        total: /* stored totals of the picked modules */,
        facets: [{ key: 'module', label: 'Module', type: 'multiselect',
                   options: /* one per eligible module, count = stored total */,
                   default: /* the profile's ticked modules (E12) */ }]
    }),
    fetch: (nodes, narrowing, ctx) => /* pairs, 5 at a time, ctx.signal forwarded */
}
```

`eligible(n)` reads the catalogue, fetched once per distinct value when the node lands, the way
correlation counts are. No `save` (E8), no `autoIngest`.

### 5.3 Landing

| Result | Lands as | Joined by |
|---|---|---|
| `attributes[]` | attribute node `enr:<type>:<value>` (E4) | `enrichment` edge from the origin attribute, label = module |
| `objects[]` | closed object node `enr-obj:<module>:<n>`, its attributes as children | `enrichment` edge from the origin |
| `elements[]` | attribute node of its first type, flagged untyped (E7) | `enrichment` edge from the origin |

The node data carries `provenance: 'module'` (E5), the module name, `known`, and whether the
answer came from the store and how old it is. The sidebar shows them.

### 5.4 Look

A result node is drawn by `misp-pivot-nodes` like any attribute or object, with the module
provenance on its own channel (to be chosen in the prototypes; v16 D2 lists the channels already
taken). The `enrichment` edge kind gets its own colour and legend entry.

### 5.5 What did not come back

Per E6. The states that are not results: `silent` → "answered with nothing"; `error` → the
module's message; `timeout`, `unreachable` → as the Value Profile words them; `refused` → "MISP's
enrichment workflow declined the query". Nothing a user may not see is counted or hinted at.

### 5.6 Cost

A module is asked at most once per value, type and organisation within the freshness window,
whoever asks, from the graph or the Value Profile. Each ask holds one PHP worker for up to the
timeout; 5 at once is what the Value Profile already accepts.

### 5.7 Saving (later pass)

When E8 is taken up: `save` builds the MISP-format JSON of the ingested nodes still unsaved and
posts it to `handleModuleResults` for this event, only where the origin attribute is this event's
own and the user can modify it. The library's ledger then counts and offers them.

## 6. Out of scope

- Writing results into an event (E8), a *Where this is in MISP* pivot (E13).
- Hover modules (E2), object-input modules (E3), per-query module options (`userConfig`): no
  expansion module declares one, and letting a reader set `meta.config` is a credential leak.
- Auto-running modules on the graph (E12).
- Enrichment of events, objects as a whole, tags or clusters.

## 7. Acceptance

Against the dev instance, logged in, dev server on this worktree:

1. On an event with an IP attribute, *Enrich* is offered on it and not on an object or event node.
2. The Module facet lists only modules that accept the type, with stored answers counted.
3. A run with two modules, one answering and one not, lands the first's results joined by
   `enrichment` edges and says why the second did not.
4. Running the same module again inside the freshness window answers from the store, without
   asking the module server.
5. The Value Profile's Enrichment tab for that value shows the graph's run.
6. As `user@admin.test`: *Enrich* offers stored answers only if the user lacks `perm_add`.
7. Undo takes the landed results away; the stored answer stays.
8. A legacy module's elements land as untyped attributes; none is dropped.

## 8. Phases

Following the pattern the Value Profile used (contract, competing prototypes, wiring):

- **A — Contract.** Grill §4; record the rulings here; freeze §5.1's JSON.
- **B — Prototypes.** Two or three cold-built candidates, as static pages against a recorded
  answer (`circl_passivedns` on `8.8.8.8`, 1,374 objects, is the stress case):
  - the Module facet with stored state,
  - Review rows for enrichment results with the per-module notices,
  - the result node's provenance look.
- **C — Wiring.** The endpoints, the pivot, tests, acceptance.

## 9. Files (expected)

| File | Change |
|---|---|
| `app/Controller/EventsController.php`, `app/Controller/Component/ACLComponent.php` | `enrichmentModules`, `enrichmentRun` + ACL |
| `app/webroot/js/pivot-explorer.js` | the pivot, landing, `enrichment` kind, provenance value |
| `app/webroot/js/misp-pivot-nodes.js` | the module provenance on the node drawing |
| `app/webroot/js/pivot-sidebar-model.js` | module, stored age, `known` in the sidebar |
| `tests/js/pivot-explorer-graph.test.js` | the pivot and landing |
| `~/git/pivotick/prd/` | per-run notices on `PivotResult` (E6), if taken |
