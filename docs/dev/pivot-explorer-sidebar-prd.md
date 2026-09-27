# PRD: A sidebar that completes the node

**Status:** Draft 2026-09-27. Exploration not started.
**Owner:** Sami Mokaddem (Claude-assisted)
**Parent:** [`pivot-explorer-v16-prd.md`](pivot-explorer-v16-prd.md).
**Depends on:** the persona branch's context priority (§4), not yet merged; the sidebar works without it.

---

## 1. Why

A node's card is a glance: an event card shows its title, org, date and a few tags; an attribute
card its value, type and marks. The sidebar is where the analyst reads the rest, and today it
does not do that job.

- **It is a flat list of fields.** `nodeProperties` (`pivot-explorer.js`) returns name/value rows
  per entity: *Info, Date, Organisation, Tags, Galaxy clusters, Event ID, UUID* for an event.
  Nothing is ranked, grouped or explained, and some of it (ids, uuids) is what the analyst
  needs least.
- **It says less than the card in places.** The event card draws threat level, analysis,
  distribution and counts; the sidebar lists none of them.
- **Context is missing.** Nothing says what the node's tags and clusters mean, which feeds or
  servers saw it, how widely it correlates, who sighted it, or which of its context matters to
  *this* analyst.
- **A multi-selection cannot show what nodes share.** Pivotick aggregates each property across
  the selection, with counts and keep/exclude chips, but MISP joins all tags into one string
  (`Tags: a, b, c`), so shared tags never add up.

The goal: for every kind of node, the sidebar shows **everything on the card, plus the context
and other information an analyst needs to decide what to do next, in the order that matters**.

## 2. Requirements

### 2.1 Per entity

Each entity's sidebar holds what its card shows, then its context. The lists are what must be
*reachable*; which is shown first and how is the exploration's question (§5).

| Entity | On the card (must repeat) | Context to add |
|---|---|---|
| **Event** (this one, another, a feed's) | info, org, date, threat level, analysis, distribution, published, attribute/object counts, provenance | tags and clusters, grouped by taxonomy and galaxy; analyst data; correlation summary (how many events share values with it); feeds and servers that hold it; extends / extended by; reports; a link out to its own page |
| **Object** | template name, meta-category, top attributes | all its attributes, ranked by template priority, with their marks; references in and out; tags, clusters and warninglist hits rolled up from its attributes; correlation count per attribute |
| **Attribute** | value, type, IDS flag, marks (warninglist, tags, analyst, feed) | category, comment, first/last seen; tags and clusters; warninglists by name and category; feed and server hits; correlation count; sightings; the object and event it belongs to |
| **Tag** | name, colour | taxonomy, predicate and value descriptions (numerical value where the taxonomy has one) |
| **Galaxy cluster** | value, galaxy | description, synonyms, meta (country, refs, …), relation count |
| **Feed / server** | name, provider | URL, format, how many of the canvas's attributes it holds |
| **Edge** | kind, relationship type | comment, provenance, whether it is authored (deletable) or derived |

### 2.2 Multi-selection

Selecting several nodes answers **what do they share**: tags, clusters, orgs, and later shared
values, each with how many of the selection carry it, using pivotick's aggregated table and
its keep/exclude filter. Every multi-valued field is emitted as one entry per value.

### 2.3 Rules

- **Nothing hints at records the viewer cannot see.** No counts or caveats about invisible
  events, attributes or orgs. A count is of what the viewer can read.
- **Lazy beyond the payload.** What the node data carries renders at once; anything that needs
  a request (sightings, cluster meta, correlation summary) loads in the panel with pivotick's
  async render and its abort signal, never on every selection of the whole canvas.
- **Both themes, sidebar width.** The panel is pivotick's sidebar column; designs work at its
  width, in light and dark.
- **One grammar across entities.** An analyst learns the layout once.

## 3. What pivotick offers

The sidebar is pivotick's; MISP fills it through hooks, all of which receive a node, an edge,
an array of either (a multi-selection) or `null`, and may return a promise (with a
`RenderContext` whose `signal` aborts a superseded render):

| Hook | Place |
|---|---|
| `UI.mainHeader.render` | the header above everything |
| `UI.propertiesPanel.render` / `nodePropertiesMap` | the properties body; `nodePropertiesMap` rows feed the multi-selection aggregation |
| `UI.neighborsPanel.render` | the neighbours list |
| `UI.extraPanels[]` | ordered, optionally reactive, extra panels (MISP's analyst panel is one) |

A design that needs something these cannot do is written up for pivotick, not built around.

## 4. Context priority

The persona work (`worktree-personas`, not merged: 493 commits ahead of `develop` on
2026-09-27) orders context by the analyst's profile. Its rules are in
`prd/personas/02-context-priority.md` and `04-label-surfaces.md`.

- **What it covers: taxonomies and galaxies only.** A profile's
  `parameters.context.{taxonomies|galaxies}.{pinned|preferred|demoted}` lists taxonomy
  namespaces and galaxy types. Order is pinned, preferred, unlisted (count order kept), demoted.
  Nothing is hidden (D40); an instance-disabled taxonomy beats a pin (D41); the instance's
  highlighted tags outrank a pin (D53). Pinned `tlp` / `PAP` lead with the most restrictive label.
  Individual clusters, warninglists, feeds and orgs are **not** in it.
- **Which profile:** `AnalystProfile::resolveFor($user)`, nearest scope wins: user, then org, then
  instance, then the shipped `default-v1`, which declares nothing, so pages look as today.
- **The call:** `ValueLabelPriority::planFor($profile)` normalises a profile into
  `[scope][tier] => keys`, and `order($groups, $plan, $scope)` sorts groups carrying a `key`,
  adding `priority`. They are pure functions: no user, no query. The event page already passes
  the plan to its views (`labelPlan`, `EventsController.php:1767` on that branch), which order
  the tag and galaxy columns of event and attribute rows. There is no per-event or per-object
  equivalent of the value page's `ValueProfile::forContext`, and missing pins are only drawn on
  the value page (D54).

**What the sidebar takes from it.** Tags are grouped by taxonomy namespace and clusters by
galaxy type, and those groups are ordered by the active plan. The explorer receives the plan as
the event page does (a `data-pe-*` attribute or the seed payload) and applies the same order
in the view-model. Whether the sidebar also shows missing pins is a question for the
exploration, since D54 keeps them on the value page for now.

**What it does not decide.** The order of everything else (warninglists, feeds, correlations,
sightings, analyst data, the entity's own fields) is not in the profile. The exploration
proposes a fixed order for it, one per entity.

**The dependency.** Until personas is merged into this branch's base, the plan is absent and
the sidebar uses the `default-v1` behaviour: groups in count order. The contract phase builds
the view-models to take a plan, and tests them with one of the shipped profiles
(`incident-response-v1` pins `tlp, PAP, admiralty-scale` and prefers
`threat-actor, mitre-intrusion-set, …`), so nothing changes when the merge lands.

## 5. Phases

### 5.1 Contract

No templates. Built and checked before any design starts:

- **A view-model per entity**, built from node data plus the lazy reads above, with the
  priority order of §4 applied. One function per entity, unit-tested.
- **Fixtures**: the view-models dumped as JSON from real events on the dev instance, covering
  the hard cases: an event with many galaxies (4242), a warninglisted attribute (4074's
  `8.8.8.8`), an object with many attributes, a foreign event card and a feed's card (1562,
  2014), a tag and a cluster node (1525), an authored and a derived edge, a multi-selection of
  three events sharing tags.
- **A page frame**: a static page that renders a candidate sidebar at the real width, in both
  themes, from the fixtures, with an `?only=<fixture>` filter so headless Chrome can capture
  each case.

### 5.2 Exploration

Three candidates, each built **cold by its own agent** from a written brief, so they are
independent designs rather than three passes of one hand. The brief gives the reading list
(this PRD, the fixtures, the page frame, the node designs in `prd/pivot-node-designs`), a
do-not list (no invented data, no fields outside the view-model, no library changes), the
build and capture commands, and one assigned direction:

- **A. The card, continued.** The header redraws the node's card at sidebar width; sections
  follow in a fixed order (context, links, activity), each collapsible.
- **B. Priority first.** A short ranked list of what matters about this node for this analyst
  (§4) leads; the full detail follows below a fold.
- **C. Questions.** Sections named after what the analyst is deciding: *Is it noise?*, *Who
  else has it?*, *What is it tied to?*, *What do we know?*

Each covers every fixture. Deliverables are local files under `prd/pivot-sidebar/`, with
screenshots per fixture and theme.

### 5.3 Pick

The owner compares the three on the same fixtures and picks one, or a combination, recorded
here with the reason.

### 5.4 Wiring

The picked design is built into `pivot-explorer.js` on the contract's view-models, through the
§3 hooks, and checked live on the fixture events.

## 6. Out of scope

- Editing from the sidebar (tags, analyst data): the existing MISP forms do that.
- Shared *values* across a multi-selection: needs a server read, a later step.
- Saving a selection or a graph as a collection: parked, scope open.
