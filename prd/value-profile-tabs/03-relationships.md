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

> **§19 supersedes items 2, 3, 5 and 6 below, and the two selects.** The
> section is two panels now, the selects are pill groups, and the
> narrowing runs in the fold rather than in the browser.

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

> **§19 supersedes this section.** Row selection is gone, `Open the full
> graph` works, and narrowing and ranking are only client-side where the
> panel provably holds every row they could reach.

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
7. Light and dark: the three notion colours stay distinguishable, and the bulk
   bar and type badge are legible — which, per `00-shared.md` §9, they already
   are, so this is a measurement and not a fix.

## 14. Exit criterion

Artifact `R1` is recognisable in the browser with `R3`'s narrowing bar on its
first section; the three notions cannot be mistaken for one another; and
`8.8.8.8` renders as suppressed rather than empty.

---

## 15. Verification — what was run

Against the Docker stack serving this worktree, as an authenticated user,
2026-08-25.

1. **`php -l`** over every changed and new file, `node --check` on
   `value-profile.js`. Clean. Every new file is inside 80 columns.
2. **All five endpoints, all four demo values — twenty fetches, twenty 200s**,
   plus the four full pages, and no PHP notice or warning in any body.
   **136 content assertions** over the returned markup, all passing: the three
   provenance words appear in their own section and nowhere else
   (`Machine-derived` never in the asserted panel, `Human claim` never in the
   co-occurrence one); four `.vp-analyst` claim blocks with no `<table>`
   anywhere in that panel, and no `.vp-analyst` anywhere in section one; 18/6/3
   rows in the three roll-ups; six counted facet dropdowns and the `GROUP BY`
   note; the three engine states including the `No engine in MISP` stub; the
   three CIDR blocks with their address counts; both ACL notes; the rail's
   `7 of 31 edges drawn`, three edge kinds and 3 + 2 + 2 nodes.
3. **The tab driven in a real browser, both themes**, with the fragments served
   locally so the shipped CSS and JS are what runs. **62 assertions per theme,
   all passing.** Every interaction §11 promises:
   - **the facet bar** — `domain` narrows 18 to 5; adding `sha256` on the same
     key widens to 9, so values within a key are alternatives; adding `ORGNAME`
     on a second key cuts to 3, so keys conjoin;
   - **the filter row** — the `Any type` select on top of those facets narrows
     3 to 2 rather than widening, which is the whole reason a select and a
     counted dropdown may share a key; `Search value` finds the 7
     `cdn-analytics` values; `Shared events ≥ 2` leaves the 4, the 3 and the 2;
     `Object siblings only` leaves the two rows that sit in an object this
     value is itself in;
   - **`Reset`** restores 18, puts the selects back to `Any …`, and goes inert;
   - **ranking** — `Most recent first` puts 2025-08-19 on top and 2025-07-30
     second; `Most shared first` puts the 4 back;
   - **the roll-up switch** — 6 event rows, 3 object rows, no value rows
     showing, the narrowing block put away with a line saying why, and the
     pager hidden because six rows need no pages;
   - **pagination** — `1–8 of 18`, three pages plus two arrows, page three
     holding rows 17–18, and a facet set from page three returning the reader
     to page one rather than to a page that no longer exists;
   - **selection** — one row reads `1 selected` and tints, the header box goes
     indeterminate, and select-all takes the page's 8 rather than all 18;
   - **`Similarity ≥`** — 60% leaves only the `/22`, 99% leaves none and says
     so instead of showing a bare table;
   - **the relationship-type filter** — `similar-to` leaves the one claim and
     clearing brings all four back, over blocks that are still not rows.
4. **The suppressed and empty states probed in both themes.** `8.8.8.8` renders
   `.vp-suppressed` with a ground in both themes and a badge at 5.06:1 light /
   5.82:1 dark — and sections two and three render normally beside it, three
   CIDR rows and two claims, because neither reads the correlation table. The
   unknown value renders three differently-worded empty states and a settings
   card that is still true. No two empty states on the tab say the same thing.
5. **Light and dark, measured not eyeballed.** The three notion inks resolve
   and differ in both themes — light `#b4610b` / `#0b7f61` / `#8f2d56`, dark
   `#f0a95f` / `#4fd6b0` / `#e58cad` — and each clears 3:1 against its own
   tinted chip ground: 3.87 / 4.23 / 6.44 light, 4.88 / 5.20 / 4.27 dark. The
   sketch's edges resolve too, dashes included.
6. **The greyscale check (§13.4), rendered rather than argued.** With
   `grayscale(1)` on the document a claim is still unmistakable for a row: it
   is an indented block with a monospace kind gutter, a direction chip, prose
   and an author line, against two dense grids above it. The stripe carries the
   distinction a second way — `solid` on a correlation row, `dashed` on a
   near-match, a 3px block border on a claim — and the words carry it a third.
7. **No regression on the two tabs whose shared JS this phase changed.** The
   Occurrences facet rail still opens on 6 rows, still narrows to 4 on
   `ip-dst`, still counts one filter and still restores 6 on `Clear all`, with
   the soft-delete reveal still on. The Sightings chart still builds with 9
   datasets over 90 labels and no unresolved `var(--…)`, its list still reads
   47, and the Verdict tab's canvas still has a live `Chart` on it.

Per `00-shared.md` §9, §13's item 7 is not a claim about a Bootstrap utility in
dark mode; it is about this tab's own palette, and it was measured as item 5.

## 16. Where this differs from the brief above

**The rank select's options are worded for all three roll-ups.** §6 names the
two orderings `shared events` and `most recent`; the control reads **`Most
shared first`** and **`Most recent first`**. The orderings are the ones the
brief asks for, but the select survives a change of roll-up, and *"rank by
shared events"* over a table of events would be a label that means nothing. The
`Shared events ≥` threshold keeps its exact name and is put away with the value
roll-up, where it is exactly true.

**The narrowing controls belong to the value roll-up, and say so when they are
not showing.** §6 lists the filter row and the `Narrow by` bar without saying
what happens to them under a different roll-up. They are hidden, everything set
is cleared, and a line takes their place: *a facet like Type is a property of a
correlated value; an event row is not a value.* The alternative — leaving a
`Type` facet applying to rows that have no type — is a control that empties the
table and cannot say why.

**A select and a counted dropdown may share a key, and they conjoin.** The
graft puts `Any type` in the filter row and a counted `Type` dropdown in the
`Narrow by` bar. Merging them into one bucket would have made the select a
third alternative of the dropdown — pick `domain` in one and tick `sha256` in
the other and you would get *nine* rows, not two. `data-vp-filter-key` is a
separate conjunct from `data-vp-facet-key` for exactly that reason, and the
driver asserts the arithmetic both ways.

**The third rail card was folded into the second.** The `R1` mockup has three
rail cards; §3's endpoint table names two. The split-three-ways bars are the
foot of `What is counted`, which is where §9 already asks for *"the correlation
count the page states, and how much of it is visible"* — one card, no
unspecified sixth endpoint.

**The arithmetic was wrong in the mockup and is fixed here.** `R1`'s rail reads
*23 + 4 + 4 = 31*, with the 31 also being the correlation count the Overview's
lifecycle card prints. Those cannot both be true: an analyst claim is a
`Relationship` row and not a correlation. The split is now **28 co-occurrence +
3 near-match = the 31**, with the **4 claims counted apart** and the card
saying so in words — *"nothing here is summed into one strength"* is the
mockup's own sentence, and it now describes what the numbers do.

**`over_correlating` could not decide the suppressed state on its own.** §10
keys the band on `correlations.over_correlating`, but the fixture predating this
phase carries `true` for *both* `104.21.34.198` and `8.8.8.8`, while §10 wants
the first to paginate and the second to suppress. The tab reads its own
`relationships.cooccurrence.suppressed` instead, and the two remaining
`over_correlating` readers — the Overview's lifecycle card, on a softer
threshold of 50 — are untouched. What the band claims is narrower and truer
than a boolean: MISP stored **no** correlation, and `21,904` is the
`over_correlating_values.occurrence` count it kept in their place.

**Object siblings survive the suppressed band, and that is the point.** §10 says
section one renders `.vp-suppressed` and stops there. The object join reads
attributes the page has already fetched — `Attribute.object_id`, not the
correlation table — so it is not the engine's to suppress, and `8.8.8.8` still
lists `dns.google` under a band that says nothing was stored. The sub-section
carries the sentence explaining why.

**Closeness is grounded twice.** §7 asks for a bar reading `/22` and an address
count. The bar's *share* is the prefix as a fraction of the 32-bit width, which
is a defensible number rather than an invented one, and `Similarity ≥` filters
on it — so the control and the bar cannot disagree. The address count is
computed from the prefix rather than typed in, which is how `8.8.8.8`'s `/9`
correctly reads 8,388,608 without anybody checking the arithmetic by hand.

**Two small rendering fixes the mockup could not have caught.** `network-block`
at 11px overflows its node and SVG clips at its viewport, so a node label past
ten characters drops to 9px; and the `ASSERTED` region label sat on the last
baseline of the viewBox and lost its descenders, so it moved under the divider.
Both were found by rendering the sketch, not by reading it.

## 17. What the live phase still inherits

§12's list is unchanged and none of it was solved here — this phase is fixture
work. Three items are now sharper:

**The roll-ups are three queries, not one.** The pane renders `by value`,
`by event` and `by object` from three row sets that this pass ships together
and pages client-side. Live, only one of them is ever needed at a time, and the
right shape is one `GROUP BY` per roll-up rather than one fetch reshaped in
PHP — the switch becomes a request, and the honesty rule §6 states (the facet
counts are a `GROUP BY` on the correlation table, not a count of the page) is
what keeps the facet bar valid across all three without refetching it.

**The facet counts and the rows come from *different* queries here, and must.**
`00-shared.md` §5 says counts and rows come from one fetch, and the Occurrences
tab obeys it. This pane deliberately cannot: at 1,462 distinct values a count
tallied over the page would say 8. The fixture states the counts independently
and the pane renders the sentence that tells the reader which regime they are
in. Live, that is a `GROUP BY` per facet key alongside the paged `SELECT` —
more queries than the Occurrences rail needs, and the reason the note is not
optional.

**A suppressed value still needs three queries, not zero.** The temptation on
seeing `over_correlating_values` is to render the band and skip the tab. Two of
the three sections do not read the correlation table at all, and `8.8.8.8`
proves it: the object join and the analyst relationships both return rows. The
live implementation must keep firing `viewRelationNearMatch` and
`viewRelationAsserted` for a suppressed value, and must keep the sibling join
inside `viewRelationCooccurrence` outside whatever short-circuits the band.

---

## 18. What the live phase found

**Phase 24 converted all five panels**, and it is written up in
[`../value-profile-live/24-relationships.md`](../value-profile-live/24-relationships.md).
Four things in this document are now known to be wrong, and they are left
in place above rather than edited, because the reasoning that produced
them is worth reading beside the correction. §19 does the same for the
passes that came after the phase.

**§6's provenance word.** Section one is not correlation-engine output
and cannot be. A `default_correlations` row links two attributes that
carry the *same* value, so for one value the engine returns other
occurrences of it — the Occurrences tab — plus its CIDR and ssdeep
partners, which are section two. It never returns a third value. §6's own
column list is what describes something real, and it describes an **event
join**: the other attributes in the events this value occurs in. The
sub-line now reads `shared events` rather than `correlation engine`;
`Machine-derived` stands. `24-relationships.md` §3.

**§10's suppressed state.** Keyed on the correlation limit here, and the
limit no longer governs anything on the tab. The band now fires when
**every event the value appears in is too large to read** — measured:
the largest event on the verification instance holds 843,976 attributes,
and in an event that size every value co-occurs with every other. §17's
demand survives intact and was verified: the sibling sub-section still
lists rows *under* the band.

**§17's *"`GROUP BY` per facet key alongside the paged `SELECT`"*.** The
shape live is one bounded scan folded once — the facet counts and the
rows come out of the same fold, and they can, because the scope is
chosen and stated before anything is read. What §17 was protecting
against is real and is still avoided: the counts are not a tally of the
page. `24-relationships.md` §4 and §5.1.

**§12's *"no value-centred graph feed exists"*.** There is one now, and
the rail draws a real graph with pivotick rather than a sketch. *Open the
full graph* is no longer disabled — the one control on this page whose
reason for being disabled went away rather than being deferred again.
`24-relationships.md` §10. The static sketch stays in the markup as the
fallback.

**And one thing this document did not know it had.** The `Distribution`
and `Tag` facet dropdowns have been rendering blank labels since this
phase shipped: `value_facet_group` draws `html` where a caller supplies
one and the bare `label` otherwise, and neither facet has a label. Fixed
in phase 24.

## 19. What the follow-up passes changed

Phase 24 converted the panels; reading them against a real instance then
found what the fixture could not show. `24-relationships.md` §16 carries
the detail and the measurements. In brief, and in the order a reader
meets them:

**The section is two panels.** `In the same object` and `In the same
events`, each with its own header, narrowing bar and pager — one answers
a structural question and one a statistical one. Every narrowing control
moved beside the table it governs; §6.2 and §6.3 describe one bar above
both.

**The two selects are pill groups**, and both tables sort by column
heading (ascending, descending, back to the model's order). §16's
argument for the rank control's wording survives the change of control.

**Row selection is gone** — the checkbox column, the select-all, the
`N selected` readout and the two disabled actions of §6.6. It was a
bulk-write affordance on a page that does not write, costing a cell in
every row to say so. `Open all N as a search` stays, and now counts what
the narrowing matched.

**§6.5's `Distribution` column is a set.** One badge per distinct
*(effective level, sharing group)* the row's occurrences state, widest
first, `+N more` past three, and the sharing group named and linked. The
single badge was the *widest* of them, so a row could read `All
communities` while a record behind it was org-only. A **`Sharing group`**
facet joins §6.3's list, because level 4 was one bucket however many
groups were in it.

**§6.4's sibling table** draws its `Objects` count on the same weight
bar as everything else, and its `Event` column links wherever the fold
left one event to name rather than only where the row stands for a
single object.

**§6.3's facet counts now reach the table.** They were exact over the
neighbourhood while the filter ran in the browser over the hundred rows
that survived the cut — so on `8.8.8.8`, 60 of 107 checkboxes emptied a
table they had just been counted in, `abuse.ch` promising 9,791 and
showing none. The narrowing runs in the fold, before the cut, and the
panel re-requests itself for anything its own markup cannot answer. The
browser still answers what it provably holds — everything when nothing
was cut, and any facet whose whole count is present — so the quick path
covers the common case. The sentence §6.3 says must not be dropped is
still there, rewritten: narrowing on a count larger than the hundred
carried *fetches its rows*.

**§11's ranking has the same shape.** `Most recent` reordered the rows
already shipped, which is the most recent *of the most shared*; it now
ranks the neighbourhood, locally where nothing was cut and through the
fold where something was.

**§6.7's `.vp-acl-note` is gone**, with the other two §14.6 notes —
`24-relationships.md` §8.

**The scan behind section one is held for five minutes.** Narrowing
re-requests the panel, so the same scan would otherwise run again to
fold the same rows differently; cached, a session of narrowing costs one
scan (`8.8.8.8`: 431 ms cold, ~190 ms warm, 408 KB stored, keyed on the
viewer because every row is ACL'd to them). Nothing invalidates it, so
the panel carries the two things that make a window that long honest:
the read's age on the sentence that already describes the read —
`Scanned 3 minutes ago` — and a `Scan again` beside it that re-requests
with `fresh=1`, carrying the current narrowing. `24-relationships.md`
§16.7.
