# PRD: Value Profile — Relationships tab

**Phase 11.** Implements candidate **`R1`** with **`R3`'s faceted pane** grafted
in, chosen 2026-08-25.
Artifact: <https://claude.ai/code/artifact/0eaa5580-c273-451a-b7ba-6444dd58296e>
Depends on `00-shared.md`.

## 1. What ships

**Three ledgers — one section per notion, each with the cap its own engine
needs — with `R3`'s narrowing controls and exact facet counts on the section
that grows.**

`R1`'s structure is the base because it encodes what is actually true here: the
three notions of "related" have different cardinalities, different failure modes
and different empty states. Co-occurrence is always truncated and must say so.
Near-matches are few but carry three engine states, one of which does not exist
in MISP. Asserted claims are written one at a time by people, are never ranked
and never truncated. Three sections that each own their own rules cannot later
collapse into one blended list — which is the failure this tab dies of.

**The graft.** `R3`'s pane brought two things `R1` lacked: a `Narrow by` bar
whose facet counts are exact at any cardinality, and pagination instead of
capping. Both are applied to the co-occurrence section only, because that is the
section that grows:

| Section | Narrowing | Cut |
|---|---|---|
| Co-occurrence | full `R3` filter row + `Narrow by` facets with counts | paginates |
| Near-matches | none — bounded by the engines, three rows | none |
| Asserted | relationship-type filter only | none; ACL is the only cut |

Deliberately **not** taken from `R3`: its segmented control. `R1` shows all
three notions at once, which is the whole point; a segmented control would hide
two of them and risk the failure §7.5.3 names — nobody ever opening the asserted
segment.

## 2. Layout

`col-lg-9` + `col-lg-3`. Left: three section panels, top to bottom. Right: the
neighbourhood sketch and a card naming the engine settings the tab depends on.

## 3. Controller

| Action | URL | Renders |
|---|---|---|
| `viewRelationCooccurrence($b64value)` | ajax | `value_relation_cooccurrence` |
| `viewRelationNearMatch($b64value)` | ajax | `value_relation_near_match` |
| `viewRelationAsserted($b64value)` | ajax | `value_relation_asserted` |
| `viewRelationGraph($b64value)` | ajax | `value_relation_graph` |
| `viewRelationSettings($b64value)` | ajax | `value_relation_settings` |

Three separate endpoints for the three notions, not one: they have different
costs when they go live, and one slow correlation query must not hold up the
asserted claims, which are cheap and complete.

## 4. Templates

```
app/View/Themed/Overmind/Elements/Values/View/
    value_relation_cooccurrence.ctp   siblings + values table + facets
    value_relation_near_match.ctp     one block per engine, including the absent one
    value_relation_asserted.ctp       human claims as .vp-analyst blocks
    value_relation_graph.ctp          rail: the neighbourhood sketch
    value_relation_settings.ctp       rail: what is counted
```

## 5. The rule the tab lives or dies by

**A machine relation is a table row. A human claim is never a row — it is
`.vp-analyst`, with an author on it.** Separation is carried four ways at once,
never by hue alone:

| | Co-occurrence | Near-match | Asserted |
|---|---|---|---|
| Colour | `--vp-rel-co` | `--vp-rel-near` | `--vp-rel-human` |
| Form | table row, left stripe | table row, dashed stripe | `.vp-analyst` block |
| Word | `Machine-derived` | `Machine-derived` + engine name | `Human claim` + org |
| Place | first section | second section | third section |

`.vp-analyst` already exists (`00-shared.md` §4) and is the same block the
Overview's analyst preview and the Analyst data tab use. That reuse is the
point: a claim looks like a claim everywhere on the page.

## 6. Section one — co-occurrence

Header: title **Co-occurrence**, a `.vp-rel-tag` naming the notion, sub-line
`18 distinct values across 7 events · correlation engine · Machine-derived`, and
two selects — rank (`shared events` / `most recent`) and group (`value` /
`event` / `object`).

Then, in order:

1. **`.vp-rel-cap`** — the cut, in words: *"Top 18 of 18 distinct values, ranked
   by shared events."* At `104.21.34.198`'s 1,847 it names the page and the
   total; at `8.8.8.8`'s 21,904 the roll-up switches from values to events,
   because 21,904 correlations are rarely 21,904 distinct values and "top 25
   events" is a reading a person can use.
2. **The `R3` filter row** — `Any type`, `Any category`, `Any organisation`,
   `Any event`, `Shared events ≥`, an `Object siblings only` switch, and
   `Reset`.
3. **The `Narrow by` facet bar** — `Event 7`, `Organisation 4`, `Type 5`,
   `Object 3`, `Tag 9`, `Distribution 4`, using the shared facet control
   (`00-shared.md` §5) in its compact dropdown form. Carries the line *"Facet
   counts are exact at every count — they are a `GROUP BY` on the correlation
   table, not a count of the page."* That sentence is the graft's whole
   justification and must not be dropped.
4. **Object siblings** — a sub-section, `Object · Relation · Sibling value ·
   Type · Event · Reported by`. The highest-signal case on the tab: if this
   value sits in a `file` object, its filename, sha256 and ssdeep are one click
   away. It is listed first because it is the only co-occurrence that is
   structural rather than statistical.
5. **Values that appear in the same events** — `Value · Type · Shared events ·
   Organisations · Last together · Distribution · Tags`, with `Shared events`
   drawn as a `.vp-weight` bar carrying its number, and pagination
   (`00-shared.md` §6).
6. Selection actions, disabled: `Tag the selection`, `Add selection to a
   collection`, `Open all 18 as a search`.
7. `.vp-acl-note` — *"4 further correlations point into events you cannot see.
   They are counted in the 31 and are not listed."*

## 7. Section two — near-matches

Header sub-line: `3 matches from 1 engine · 2 engines do not apply here ·
Machine-derived`, and a `Similarity ≥` control.

`.vp-rel-cap` carries the sentence that keeps this section from being read as
equality: *"A near-match is **not equality**. Every row names the engine that
produced it and how close the match is; a row here never means the two values
are the same."*

Then one block per engine, each in one of three states:

- **Active — CIDR containment.** `.vp-rel-engine-on`. Table: `Containing block ·
  Closeness · Addresses · Event · Reported by · Distribution`, closeness drawn
  as a bar reading `/22`, `/18`, `/16` and the address count printed
  (`1,024`, `16,384`, `65,536`) so "closeness" is grounded in something.
  Carries: *"Re-derived at render time from the CIDR list — the stored
  correlation row does not record which engine wrote it."*
- **Not applicable — ssdeep.** `.vp-rel-engine-off`. Explains that ssdeep
  compares `ssdeep` attributes and this value is an `ip-dst`, so the engine
  never runs; shows what a row *would* look like (`ssdeep 92%`) and states that
  the number is recomputed per row because MISP stores the threshold test, not
  the score.
- **No engine in MISP — domain / TLD tree.** `.vp-panel-stub` with its badge.
  Nothing in MISP computes a parent-domain, registrable-domain or public-suffix
  relation: no list, no table, no code path. It is drawn so the gap in the brief
  is visible rather than quietly dropped — which is different from empty and
  different from not-applicable, and all three appear in this one section.

## 8. Section three — asserted by analysts

Header sub-line: `4 claims from 3 organisations · analyst-data relationships ·
Human claim`, plus an all-types filter.

`.vp-rel-cap-complete` states the inverse of section one's cap: *"All 4 claims
are shown. Asserted relationships are written one at a time by people, so this
section is never ranked and never truncated — the only cut is ACL. It stays
complete at 1,847 and at 21,904, where the section above cannot."*

Each claim is a `.vp-analyst` block: the relationship type as its kind
(`related-to`, `similar-to`, `derived-from`, `connects-to`), a direction chip
(`outbound` — this value is the source; `inbound` — something else claims a
relationship to it), the target's kind (`Event`, `GalaxyCluster`, `Object`,
`Attribute`), the claim text, and a meta line with org, date and distribution.

Footer: a disabled `Add a relationship`, and the sentence that explains the
list's shape — *"Claims are stored against an occurrence, not against the value
— this list is the union over the 10 occurrences, de-duplicated by relationship
UUID."* Then `.vp-acl-note`: *"2 claims are held at a distribution you are
outside of. Their existence is counted; their text and their target are not
shown."*

## 9. The rail

**`value_relation_graph`** — title **Neighbourhood**, sub-line `The value at the
centre · 7 of 31 edges drawn`. A static inline SVG sketch (`00-shared.md` §7:
there is no value-centred graph feed to drive a real one), then a key with one
entry per notion — solid for co-occurrence, dashed for near-match, arrowed for
asserted — each naming its source in plain words (`— correlation engine`,
`— CIDR / ssdeep`, `— an analyst said so`). Closing: a disabled `Open the full
graph`.

**`value_relation_settings`** — title **What is counted**, sub-line *"The engine
settings this tab depends on"*. `.vp-fact-line` rows:

- `Correlation limit 20.` (warn-toned) *"Above it MISP stores no correlations at
  all and records the value in `over_correlating_values` instead. That is a
  fourth state for the first section, not an empty one."*
- `ssdeep threshold 40.`
- The correlation count the page states, and how much of it is visible.

This card exists because every number in section one is conditional on settings
the reader cannot see, and a count whose rules are invisible is a count nobody
should trust.

## 10. States

| State | What renders |
|---|---|
| Populated | as above, malicious value |
| 1,847 (`104.21.34.198`) | section one paginates and names the total; facet counts stay exact; sections two and three are unchanged, because neither grows with the correlation count |
| **Suppressed** (`8.8.8.8`, `over_correlating => true`) | section one renders `.vp-suppressed` (`00-shared.md` §8): the value is past `MISP.correlation_limit`, MISP stored **no** correlations, and the honest reading is "too many to store", not "none". Sections two and three still render normally |
| Empty | three separately worded empty states — *"No correlation the engine has stored for this value"* · *"No near-match engine applies to `ip-dst`"* · *"No analyst has asserted a relationship for this value"*. One generic "nothing here" across all three would erase the distinction the tab exists to make |
| Unknown value | the three empty states above, plus the rail's settings card, which is still true |

## 11. Interactions

Working, client-side: rank and group selects, the filter row, the facet
dropdowns, `Reset`, pagination, `Similarity ≥`, the relationship-type filter,
and row selection in section one.

Disabled with a `title`: `Tag the selection`, `Add selection to a collection`,
`Open all 18 as a search`, `Add a relationship`, `Open the full graph`.

## 12. Deferred, and what live data will hit

**From §7.9 — this tab's blockers are the most structural of the five:**

- **Domain/TLD-tree relations do not exist in MISP.** No public-suffix list, no
  tree, no code path. The design renders the absence; nothing can render the
  data.
- **Correlations carry no provenance.** The table stores `value`, two event ids,
  two attribute ids, org and distribution — that is all. Exact matches, CIDR
  containment and ssdeep rows are written into it together by
  `Correlation::__addAdvancedCorrelations()` with nothing to tell them apart, so
  splitting co-occurrence from near-match means re-deriving per render (which is
  what the CIDR block says it does) or adding a column.
- **The ssdeep score is discarded.** `Correlation::ssdeepCorrelation()` computes
  `ssdeep_fuzzy_compare()` only to test `MISP.ssdeep_correlation_threshold` (40)
  and keeps the pair, not the number. Rendering a percentage needs a per-row
  recompute and the `ssdeep` extension.
- **`MISP.correlation_limit` defaults to 20**
  (`OnDemandCorrelationBehavior:185`, `OverCorrelatingValue:86`, `Event:5553`),
  so the fixture's 21,904 is a state, not a row count. The suppressed band is
  the correct live rendering for such a value.
- **Object siblings do not come from the engine at all** — they are a join on
  `Attribute.object_id` over occurrences the page has already fetched, with
  typed edges from `ObjectReference.relationship_type`. Cheapest thing on the
  tab, and the highest signal.
- **Analyst relationships are per attribute.** `Relationship` hangs off an
  `object_uuid`, so the list is the union over the value's occurrence UUIDs,
  both directions, de-duplicated.
- **No value-centred graph feed exists.** `CorrelationGraphTool` expands events,
  not values. A real graph needs a new node/edge endpoint, which is why the rail
  ships a sketch and a disabled button rather than a broken canvas.

## 13. Verification

1. `php -l` on all five elements.
2. All five endpoints return 200 for all four demo values; every panel resolves.
3. Malicious value: three sections in order, each with its own provenance word
   and cap sentence; the `Narrow by` bar with six counted facets and the
   `GROUP BY` note; object siblings above the values table; three engine states
   in section two including the `No engine in MISP` stub; four `.vp-analyst`
   claims with directions and targets.
4. A human claim is visually unmistakable for a table row in both themes, with
   colour disabled — check by rendering greyscale.
5. `8.8.8.8`: section one is the suppressed band, not an empty state, and
   sections two and three still render.
6. Unknown value: three differently worded empty states.
7. Light and dark: the three notion colours stay distinguishable, and the type
   badge is legible (the `00-shared.md` §9 fix).

## 14. Exit criterion

Artifact `R1` is recognisable in the browser with `R3`'s narrowing bar on its
first section; the three notions cannot be mistaken for one another; and
`8.8.8.8` renders as suppressed rather than empty.
