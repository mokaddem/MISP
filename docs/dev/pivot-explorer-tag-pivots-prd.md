# PRD: From a tag or cluster to the rest of the instance

**Status:** DRAFT — decided in review 2026-09-25, not built.
**Owner:** Sami Mokaddem (Claude-assisted)
**Created:** 2026-09-25
**Parent:** [`pivot-explorer-v16-prd.md`](pivot-explorer-v16-prd.md). Builds on task 30 in
[`pivot-explorer-v16-progress.md`](pivot-explorer-v16-progress.md).

---

## 1. Why

Task 30 made tags and galaxy clusters visible in the Pivot Explorer. Attributes and events carry
them, the sidebar lists them, and the **Tags & clusters** pivot draws one node per tag or cluster,
joined to every element on the canvas that carries it.

Those nodes are dead ends today. An analyst looking at `APT28` on the canvas cannot ask the two
questions it invites:

- **Where else does this appear?** Which other events carry the tag or cluster.
- **What is it related to?** Which clusters the galaxy says it `uses`, is `similar` to, is a
  `subtechnique-of`, and so on.

This PRD adds one pivot for each.

## 2. What the analyst sees

Select a tag or cluster node and open *Pivot ▸*:

- **Events with this tag** — on tag and cluster nodes. Other events land as event cards (the same
  card the correlation and feed pivots draw), each joined to the tag node it carries. When the
  event carries the tag only through one of its attributes, the edge says `via attribute`.
- **Related clusters** — on cluster nodes only. The clusters this cluster's galaxy points at land
  as cluster nodes, each edge labelled with the relation (`uses`, `similar`, …) and pointing away
  from the selected cluster.

Neither pivot is offered on attributes, objects or events directly. The path is always
element → *Tags & clusters* → tag node → one of these, so every event or cluster that lands has
the tag or cluster node it came from to attach to.

## 3. Decisions

Settled in review on 2026-09-25.

| # | Question | Decision |
|---|---|---|
| T1 | What lands from a tag or cluster | **Other events, as cards.** Not the tagged attributes inside them: from a card, the correlation and element pivots go deeper. |
| T2 | A tag on thousands of events (`tlp:white`) | **Newest 200 by `timestamp`**, the total stated in the summary, rows ticked in Review. No run is refused and no namespace is excluded. |
| T3 | Cluster relations: which direction | **Outbound only** — the relations stored on the selected cluster. See §6.2 for what that leaves out. |
| T4 | Several tag nodes selected | **Intersection by default** — events carrying all of them — with **union** as a narrowing option. One tag behaves the same either way. |
| T5 | Where the pivots are offered | **Only on tag and cluster nodes.** |
| T6 | Event-level vs attribute-level carrier | **Label only the attribute case** (`via attribute`); a directly tagged event draws a plain edge. |

## 4. Pivot 1 — Events with this tag

### 4.1 Endpoint

`POST /events/taggedEvents/{id}.json`, where `{id}` is the event the explorer is open on.

```json
{ "tags": ["misp-galaxy:threat-actor=\"APT28\"", "tlp:amber"], "mode": "and" }
```

Response:

```json
{
  "total": 4812,
  "events": {
    "812": { "id": "812", "uuid": "…", "info": "…", "Orgc": {…}, "Tag": [ … ], "Galaxy": [ … ], … }
  }
}
```

- **Matching.** For each tag name, the event ids from `event_tags` ∪ `attribute_tags` (live
  attributes only). `mode: "and"` intersects the per-tag sets, `"or"` unions them. Unknown tag
  names match nothing, which under `and` means no events.
- **Access.** Only events the user may see, via `Event::createEventConditions()`, the same as
  every other explorer endpoint. The event `{id}` itself is left out.
- **Order and cap.** `ORDER BY Event.timestamp DESC LIMIT 200`. `total` counts every event that
  matched and is visible, before the cap.
- **Cards.** Built by the existing `Event::correlatedEventCards()`, so the card is identical to a
  correlated event's. Its `Tag` list is event-level only, and the client relies on that for T6.
- **Why not `/events/index`.** Its `tags` filter unions event and attribute tags only in OR mode;
  its AND branch reads `event_tags` alone, so it cannot answer T4's default.

**Cost to watch.** A hub tag's id set is large (every event carrying `tlp:white`). The id columns
are indexed and only integers leave the database until the final 200, but measure on a real hub
tag before calling it done. If it is slow, intersect in SQL for `and` instead of in PHP.

### 4.2 Client

- **`appliesTo`** — tag and cluster nodes. A cluster queries by its `tag_name`, a tag by its
  `name`.
- **`summarize`** — calls the endpoint and returns `{ total }`, plus one narrowing facet:
  `mode`, a select of *All of them* (default) or *Any of them*, shown only when more than one
  node is selected.
- **`fetch`** — the same call. Each event lands as `event:<uuid>` with `eventNodeData(card)`;
  one already on the canvas merges by id. For each selected tag node the event carries, one edge
  `event → tag node`, kind `tag`:
  - label `''` when the card's `Tag` list names the tag (event-level);
  - label `via attribute` when it does not (the match came from an attribute).
- Under **union**, an event carries only some of the selected tags. The same card test decides
  which edges to draw for event-level tags, but an attribute-level match does not say which tag
  it was. So union asks once per tag, each capped at 200, rather than once for all. Intersection
  needs no such split: every returned event carries every tag.
- `maxCandidates: 200`, no `save`, no rim potential. The count needs a request, and declared
  potential is never queried (v16 task 15).

## 5. Pivot 2 — Related clusters

### 5.1 Endpoint

`GET /galaxy_clusters/relatedClusters/{uuid}.json` — outbound relations of one cluster.

```json
{
  "relations": [
    { "relation": "similar",
      "cluster": { "id": "47753", "uuid": "…", "value": "APT28 - G0007",
                   "type": "mitre-intrusion-set", "galaxy_name": "Intrusion Set",
                   "tag_name": "misp-galaxy:mitre-intrusion-set=\"APT28 - G0007\"" } }
  ]
}
```

What the existing code does and does not give:

- `/galaxy_clusters/view/{id}.json` returns `GalaxyClusterRelation` **without** the target
  cluster. Only the HTML `viewRelations` attaches targets, through
  `GalaxyCluster::attachClusterToRelations()`, one fetch per relation.
- **Target uuids are not unique.** APT28's `similar` target *APT28 - G0007* has one uuid shared by
  four MITRE galaxies (enterprise, mobile, pre-attack, intrusion-set). Resolving by uuid, as
  `attachClusterToRelations()` does, picks one of them arbitrarily. Resolve by
  `referenced_galaxy_cluster_id`, which names exactly one, in a single batched fetch.
- **Access.** The source cluster through `fetchIfAuthorized($user, $uuid, 'view')`. Each relation
  is kept only if the user may see the relation (its distribution) and its target cluster.
  `fetchGalaxyClusters($user, …)` applies the cluster side.
- A relation whose target is not on this instance (id 0, or a galaxy not installed) is dropped.

### 5.2 Client

- **`appliesTo`** — cluster nodes with a `uuid`. Clusters parsed from a bare tag name have none
  (task 30). The endpoint could also accept a `tag_name`, but only once a real case needs it.
- **`summarize`** — `{ total: relations.length }`. The counts are small (APT28's threat-actor
  cluster: 16 rows, 4 visible), so no cap beyond the canvas budget.
- **`fetch`** — each target lands as `cluster:<tag_name>`, so it merges with a cluster node the
  Tags & clusters pivot already drew. One edge per relation, `selected → target`, kind
  `cluster-relation`, label the relation type.
- A new edge kind, `cluster-relation`: its own entry in `edgeStyleMap`, `KIND_LABELS`
  (*Galaxy relation*) and the Relationship legend. Solid, in the galaxy hue, since it is an
  authored assertion rather than a derived one.

## 6. Scope

### 6.1 In

The two endpoints and the two pivots above, their ACL entries (check with
`findMissingFunctionNames`), unit tests in `tests/js/pivot-explorer-graph.test.js`, and a PHP
test for each endpoint's access rules if the suite has a pattern for it.

### 6.2 Out, knowingly

- **Inbound cluster relations (T3).** MISP stores a relation once, on the cluster whose galaxy
  declared it. Measured on the dev instance: `parent-of`/`child-of` are fully mirrored, `similar`
  mostly (3,605 of 5,059), `uses` almost never (566 of 19,247). So from a technique or malware
  cluster, *who uses it* is nearly always inbound and this pass will not show it. If that is
  needed, draw each inbound relation as stored (arrow from the declaring cluster, never an
  invented `used-by`), behind T2's cap.
- **The tagged attributes themselves (T1).** Reached from the event card with existing pivots.
- **A pivot straight from an element (T5).**
- **Excluding namespaces** such as `tlp:*` or `pap:*` (T2). The count in the summary does that job.
- **Tagging or untagging from the canvas.** Deleting a `tag` edge stays canvas-only (task 30).

## 7. Acceptance

1. On event 4242, selecting the `mitre-attack-pattern` cluster nodes and running *Events with
   this tag* lands only events carrying all of them. Switching to *Any of them* lands the union,
   with one edge per tag each event actually carries.
2. An event matched only through an attribute draws its edge labelled `via attribute`; a directly
   tagged one draws it unlabelled.
3. `tlp:white` (or the instance's most used tag) states its full total, lands at most 200 events,
   newest `timestamp` first. Its request time on the dev instance is measured and recorded in the
   tracker.
4. The open event never lands as its own card.
5. *Related clusters* on APT28 (threat-actor) lands its visible `similar` targets, each resolved
   to the right galaxy, merged with any cluster node already on the canvas.
6. A reader who cannot see an event or cluster never receives it, and no count includes it.
   Check with one of the lesser dev readers.
7. Unit suite green; tracker row added in the same commit as the code.
