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

`col-lg-9` + `col-lg-3`. Left: three section panels, top to bottom — **four
since §20**. Right: the neighbourhood sketch and a card naming the engine
settings the tab depends on.

## 3. Controller

| Action | URL | Renders |
|---|---|---|
| `viewRelationCooccurrence($b64value)` | ajax | `value_relation_cooccurrence` |
| `viewRelationNearMatch($b64value)` | ajax | `value_relation_near_match` |
| `viewRelationAsserted($b64value)` | ajax | `value_relation_asserted` |
| `viewRelationExternal($b64value)` | ajax | `value_relation_external` (§20) |
| `viewRelationGraph($b64value)` | ajax | `value_relation_graph` |
| `viewRelationSettings($b64value)` | ajax | `value_relation_settings` |

Separate endpoints per notion, not one: they have different
costs when they go live, and one slow correlation query must not hold up the
asserted claims, which are cheap and complete.

## 4. Templates

```
app/View/Themed/Overmind/Elements/Values/View/
    value_relation_cooccurrence.ctp   siblings + values table + facets
    value_relation_near_match.ctp     one block per engine, including the absent one
    value_relation_asserted.ctp       human claims as .vp-analyst blocks
    value_relation_external.ctp       remote events, per source (§20)
    value_relation_graph.ctp          rail: the neighbourhood sketch
    value_relation_settings.ctp       rail: what is counted
```

## 5. The rule the tab lives or dies by

> **§20.6 adds a fourth column to the table below.** `Outside this
> instance` is a fourth machine-derived notion; it takes third place on
> the page and asserted becomes fourth.

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
> narrowing runs in the fold rather than in the browser. **§27 corrects
> item 4:** a file's hashes are one click away, its filename usually is
> not, because MISP does not correlate on filenames.

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

> **§22 rewrites the claim block.** A claim is three lines: the target
> is linked, a detail line under it says what the target actually is,
> and the meta line names which of the two organisations it is naming.
> The `.vp-acl-note` below went with the other two — `24-relationships.md`
> §8.

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

> **The graph became real in phase 24 and is re-evaluated in
> `24-relationships.md` §22.** The sketch and the disabled button are
> gone; what replaced them is a *star*, which carries nothing the three
> panels below it do not print. §22 lists the twelve reads only a graph
> can serve, splits them into this rail's peek and the overlay's full
> read, and finds twenty-one of twenty-nine already free in the held
> scan. **`24-relationships.md` §23** then reads pivotick 1.6.0
> (released 2026-08-28) against that list: the key this section
> specifies is now a library feature that filters, and twelve of the
> twenty-nine move into the library — three of them whole, nine of them
> as far as the rendering, with the derivation still ours.
>
> **§23 of this document supersedes the card described below.**
> `24-relationships.md` §24 then measured the topology and emptied six
> of the twelve, and §25 found why: the structure is inside objects, not
> between events. The rail still holds a canvas, but it draws `value →
> object → value`, rolled up per template to 1–5 nodes. §26 there
> records the eleven decisions.

**`value_relation_graph`** — title **Neighbourhood**, sub-line `The value at the
centre · 7 of 31 edges drawn`. A static inline SVG sketch (`00-shared.md` §7:
there is no value-centred graph feed to drive a real one), then a key with one
entry per notion — solid for co-occurrence, dashed for near-match, arrowed for
asserted — each naming its source in plain words (`— correlation engine`,
`— CIDR / ssdeep`, `— an analyst said so`). Closing: a disabled `Open the full
graph`.

**`value_relation_settings`** — title **What is counted**, sub-line *"The engine
settings this tab depends on"*. `.vp-fact-line` rows:

> **§21.6 extends this card to the fourth section.** A fourth row names the
> feed and server caches, the split has four bars carrying their units, and
> the sub-line covers caches as well as engine settings.

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

---

## 20. Section four — outside this instance

**Agreed 2026-08-31.** `24-relationships.md` §15.1 item 2 named feed
co-occurrence as a follow-up and deferred it on two grounds: the
permission question was a decision rather than a wiring detail, and the
instance had no populated cache to build against. A feed and a sync
server were then enabled and cached, which settles the second; this
section settles the first. `24-relationships.md` §17 carries the
measurements, and the defect this section waits on.

The notion is **co-occurrence the instance never recorded**: a
MISP-format feed or a cached sync server holds this value inside a
remote event, and that event can be named and opened. §12 recorded that
no value-centred graph feed existed and §10 of the live phase built one;
this is the same argument one step further out. The neighbourhood does
not stop at the instance boundary, and until now the page could not see
past it.

### 20.1 One filter, two panels

`Feed::searchCaches($value)` answers for the whole instance and applies
**no role check at all** (`Feed.php:1990`; its ACL entry is
`'searchCaches' => ['*']`). Nothing on this page may render that output
directly.

One method — `externalPresenceFor($value, $user)` — returns only the
sources this viewer may see, and both panels read it:

| Panel | Reads | Renders |
|---|---|---|
| `value_external`, Overview rail | that method | a count per kind, linking here |
| this section | that method | a row per source, with its remote events |

Two panels filtering independently is the failure mode: the looser one
becomes the disclosure and the stricter one's accuracy is decorative.
The count on the card and the rows in this section are one list, counted
in the first place and listed in the second.

### 20.2 Who may see which source

Per source, not per viewer:

| Source | Visible to |
|---|---|
| Feed, `lookup_visible = 1` | every role |
| Feed, `lookup_visible = 0` | site admin |
| Sync server | site admin only |

> **Corrected by B3, 2026-09-01.** The middle row read
> `perm_view_feed_correlations` from 2026-08-31 until B3, and that was a
> disclosure — the row below this table asserted the event view hands a
> permission holder *every cached feed*, and it does not.
> `Feed::getCachedFeedsOrServers` conditions on `lookup_visible = 1` for
> anyone without `perm_site_admin` (`Feed.php`, the `Feed` branch), so
> the permission gates *whether feed correlations appear at all*, never
> *which feeds may be named*. `AppModel`'s migration sets the permission
> to 1 for every existing role, and `lookup_visible` defaults to 0, so
> on any upgraded instance the old rule named every cached feed to
> every reader. Measured and then re-measured after the fix in
> `live/24b-relationships.md` §5.1.

The rule is chosen so that **the page is never looser than any surface
MISP already ships, and stricter in one documented place.** Check it
against all three existing readers:

| Reader | Gives | This rule gives |
|---|---|---|
| the event view (`attachFeedCorrelations` + `getCachedFeedsOrServers`) | `lookup_visible = 1` feeds to a permission holder, every cached feed to a site admin | the same, minus the permission — which is looser only for a reader the next two rows already serve |
| `/feeds/searchCaches`, plain user (`$limited`) | `lookup_visible = 1` feeds, no servers | the same |
| `/feeds/searchCaches` or `/feeds/index`, site admin **or host org** | every feed, **and servers** | stricter for a host-org non-admin: no withheld feeds, no servers |

**The host-org half of that third row cannot fire**, which is why this
page does not reproduce it. Both surfaces compare a session `org_id` —
a string, `'1'` — against `(int)Configure::read('MISP.host_org_id')`
with `!==`, so every non-site-admin takes the limited path regardless of
org. Measured on the dev instance 2026-09-01. Copying the branch would
mean copying a comparison that does not do what it reads as doing;
fixing it belongs to those two surfaces, not to a page that only reads
them.

**`lookup_visible` defaults to `0`** (`INSTALL/MYSQL.sql:572`), so on a
stock instance the first row of the first table is empty and a plain
user sees nothing — which is what `value-profile-coverage.md` §3.2 is
protecting, and it is protected. §20.9 argues the one place this
departs from that document's instruction.

The server row is one notch stricter than the event view, which admits
the host org (`Feed.php:650`). It is stricter because
`servers/previewEvent` is **site admin only** (`ACLComponent.php:742` —
the empty array, which the component's header defines as site-admin
only) while `feeds/previewEvent` is open to every role (`:472`), and the
link is the row's whole value. A host-org reader would otherwise get
rows they cannot open.

Do not reach any of this by passing `$limited`. That flag conflates two
decisions — restrict feeds to `lookup_visible`, and drop the server
branch entirely (`Feed.php:2062`) — so it cannot express *site admin
sees servers, host org does not*.

### 20.3 The Overview card

One line per kind, counting only the sources this viewer may see:

```
4 hits in feeds
1 hit on sync servers
```

The number is the link, anchored to this section. Nothing else changes
on the card: it stays a count, and the detail lives one click away
rather than in the rail.

### 20.4 The section

`Source · Kind · Remote events`, one row per visible source. `Kind` is
`searchCaches`' own word — `MISP Feed`, `Feed`, `MISP Server` — because
the distinction it draws is the one that decides whether a row has
events at all.

- **A source with no published event still gets a row**, marked as
  holding the value with no event to open. A CSV or freetext feed never
  names one. Drop those rows and the card's count stops matching the
  section's rows, which is the thing that makes a reader distrust both
  numbers.
- **No pager, no facet bar.** Measured, the neighbourhood out there is
  small: a median of 2 remote events per hitting value, 4 at p95, 52 at
  the maximum (`24-relationships.md` §17.4). A plain table with a
  25-row cap and the standard cap notice covers the instance. A cap is
  not a permission — §8 — so that notice stays whoever is reading.
- **Opening a remote event leaves the instance.** It is the only
  affordance on this page that is not a local read: `previewEvent` reads
  from the feed or server at request time. The row says so rather than
  looking like the internal links above it.

  **The word is *preview*, never *fetch*.** In MISP, fetching a remote
  event is what pulling it into the local database is called, and
  `previewEvent` does not do that — it renders somebody else's event
  without storing any of it. A tooltip saying *fetch* would promise the
  destructive-ish version of an entirely read-only action, so both the
  per-link tooltip and the note under the table say *preview*. That word
  carries it on its own; spelling out that nothing is saved locally only
  raises the question it was answering.

### 20.5 States, and the two ways this design was nearly wrong

`00-contract.md` §14.6 governs, and it is stricter than either of the
first two drafts of this section. It decides that the page states
nothing about data hidden from the viewer — *not a count, not a
proportion, and not the bare fact that something is hidden* — because
the URL takes any value a reader types, so a count that includes
invisible sources turns the page into a membership oracle.

**So there is no count of hidden sources.** *"2 hits in feeds you cannot
see"* is forbidden, and it is also very nearly the whole intelligence
fact: *which* feed is decoration next to *this value is in somebody's
feed*. A viewer who may see no source therefore reads exactly what a
viewer whose value hit nothing reads. §14.6 states that cost in its own
words and accepts it deliberately.

**And there is no permanent caveat.** §14.6's exception is for a panel
that renders a *computed judgement*; the rule it draws is that a panel
which renders a **count** does not get one. This panel counts.

**A role-keyed notice is neither of those, and it is what ships.** The
bands removed in `24-relationships.md` §8 all said *N things are hidden
from you on this value*. A sentence keyed on the viewer's **role**
instead — shown on every value that viewer opens, whether or not
anything hit — says nothing about any value:

```
Your role cannot view feed correlations.
Sync server hits require site admin.
```

This is a small extension of §14.6, recorded as one rather than
smuggled: **a statement keyed on the viewer's role rather than on the
value is not a statement about hidden data.** The test is the oracle
test — vary the value, and the sentence does not move. It gives back
the transparency §14.6's cost paragraph gives up, without giving back
the oracle.

The empty states are three, and they are three different sentences:

| State | What it says |
|---|---|
| no visible source holds it | not present in any feed or server you can see |
| the viewer may see no source of that kind | the role notice above |
| no feed or server has caching enabled | a configuration fact, safe to state: it is instance-wide and does not vary by value or by reader |

### 20.6 Where it sits in §5's separation

§5's table gains a fourth column, and the section takes third place on
the page — machine-derived before human claims, which is what §5's
`Place` row encodes:

| | Outside this instance |
|---|---|
| Colour | `--vp-rel-external` |
| Form | table row, outlined stripe — distinct from near-match's dashed |
| Word | `Machine-derived` + `feed cache` |
| Place | third section; asserted becomes fourth |

Asserted moving down one is a real cost against the worry §7.5.3 of the
page brief names. The answer is its anchor in the tab bar, not its
scroll position: breaking the machine-before-human ordering to protect a
scroll position trades a structural rule for a habit.

### 20.7 What this section is not

- **Not a presence list.** *Which* feeds hold the value, with no event
  to open, is the card's answer and stays there.
- **Not a graph.** Expanding a remote event into its attributes costs
  one remote fetch per event. The graph gains a fourth edge kind only
  once that cost is paid somewhere other than a page render — the
  ruling in `24-relationships.md` §14 stands.
- **Not a near-match source.** The cache is set membership on an md5:
  no CIDR, no substring, no fuzzy partner. It cannot feed section two,
  and a feed carrying `1.2.3.0/24` does not hit for `1.2.3.4`.
- **Not datable.** One timestamp per feed, rewritten on every re-cache,
  so there is no Timeline lane here. Settled already in
  `06-timeline.md` §12.

### 20.8 Was blocked on — cleared

`Feed::searchCaches` misattributed remote event uuids across sources on
**11% of hitting values**, so the links this section exists to render
were wrong for one row in nine. **Fixed 2026-08-31**, mirroring the
reader that already got it right; `24-relationships.md` §17.2 has the
before-and-after and the invariant it was verified against.

Nothing else gates the build. The hash mismatch of §17.6 is fixed too,
in the same pass and pipelined, so this read is now both more complete
and faster than the one measured in §17.3 — and the empty state of §20.5
no longer has to carry a caveat about values the lookup could not reach.

### 20.9 Why this does not gate every feed on `perm_view_feed_correlations`

`value-profile-coverage.md` §3.2 gives whoever wires this panel a direct
instruction, and it is the one thing in the corpus §20.2 departs from:

> apply `perm_view_feed_correlations` in the panel, even though
> `searchCaches()` does not. Render the ACL-gated empty state, not an
> empty result.

**Taken, with one exception: feeds an administrator has set
`lookup_visible = 1` on.**

The reasoning §3.2 gives is that on a stock instance a plain user sees no
feed correlations on any event view, so rendering `searchCaches` output
would hand them a fact MISP withholds everywhere else. That is correct,
and it **stays** correct under §20.2 — `lookup_visible` defaults to `0`,
so on an instance where nobody has touched the flag the two rules are
the same rule and produce the same empty state.

Where an administrator *has* set it, the flag is not an oversight: it
means *this feed's membership may be looked up by anyone*, and MISP
already acts on it. `/feeds/searchCaches` is reachable by every role
(`ACLComponent.php:476`) and returns those feeds by name. Gating them a
second time here would hide, on this page, what the same reader gets by
pasting the value into a page one click away — which teaches them to
distrust the page rather than to trust the gate.

The two readers are also different shapes of question, which is why MISP
gates them differently and why this page follows the one it resembles.
`attachFeedCorrelations` decorates attributes inside an event the reader
already holds — the permission is about enriching something they can see.
`searchCaches` takes a bare value from anyone and answers who holds it,
which is this page's own shape: §14.6's whole argument turns on the URL
accepting any value a reader types.

So a feed the administrator has not published is not named here, which
on a stock instance is all of them. `value_external` and this section
apply that identically, through the one method of §20.1.

**What this section got wrong until B3, and the lesson in it.** The
paragraphs above are still the argument, but until 2026-09-01 they were
implemented as `perm_view_feed_correlations` rather than site admin —
and the difference is the whole disclosure. The permission answers *may
this reader see feed correlations*; `lookup_visible` answers *may this
reader be told this feed's name*. Reading the first as if it settled the
second is what put a withheld feed's name, URL and remote events in
front of a reader `/feeds/index` will not list it for. The check that
would have caught it is the one §20.2's table claims to make and did not
finish: not *which surface has a similar rule*, but *run the same reader
through every surface and diff the output*. That is now a probe rather
than a paragraph — `live/24b-relationships.md` §5.1.

---

## 21. Built, and the three places it departed from §20

**Built 2026-08-31**, against the populated cache and the two
`searchCaches` fixes of `24-relationships.md` §17.

| Piece | Where |
|---|---|
| the filtered lookup | `ValueProfile::externalPresence()`, private |
| the card's facade | `ValueProfile::forExternal()` |
| the section's facade | `ValueProfile::forRelationExternal()` |
| endpoints | `ValuesController::viewExternal`, `::viewRelationExternal` |
| templates | `value_external.ctp`, `value_relation_external.ctp` |
| notion colour | `--vp-rel-external`, `.vp-rel-k-external` |
| ACL | `'viewRelationExternal' => ['theming_enabled']` |

Both facades return the same `externalPresence()` array, which is §20.1's
requirement expressed as the only way to get the data.

`Feed::searchCaches` is called once for the whole instance and its hits
are then intersected with the ids this reader may be told about, rather
than filtered by passing `$limited` — which §20.2 forbids because that
flag cannot say *site admin sees servers, host org does not*. The feed
id set comes from a separate query that reads `lookup_visible`, a column
`searchCaches` does not select.

### 21.1 The role notice is unconditional, which §20.5 asked for and the
first cut got wrong

The first implementation showed the notice only when the reader had **no
visible sources at all**. That is wrong twice over, and the second way is
the one that matters:

- A reader who can see feeds but not servers was never told servers
  existed. The section silently answered half a question.
- Gating on *this value produced nothing visible* makes the notice's
  presence depend on the value, which is the one-bit disclosure §14.6
  names.

It now renders whenever the reader's **role** cannot reach a kind of
source the instance holds cached, on every value alike. Verified against
the property that matters: withholding a feed this value does **not** hit
changed the notice and left the count at `2 remote events across 2 feeds`
— the notice moved, the number did not.

### 21.2 SightingDB stays a stub, and says which primitive it needs

§2.6 of the page brief promises the card three things and this build
delivers two. `Sightingdb::queryValues` exists, so the gap is a decision
rather than a missing primitive: reading it means querying a third party
at render time, which is the kind of call the Enrichment tab exists to
require a press for. Drawn with `.vp-panel-stub` and its badge, which is
§1.3's *not implemented* state — distinct from the two empty states
beside it, and not a zero.

### 21.3 A link to a tab had to start working

The card's count links to `#tab-relationships`, and nothing was listening
for it: `activateTabFromHash` ran on `DOMContentLoaded` only, so an
in-page link moved the hash and left the tab where it was. One line in
`genericElementsBS5/Layout/view_layout.ctp` — a `hashchange` listener on
the function that already existed. Every deep link to a tab now works,
not just this one.

**The link lands on the tab, not on the section.** The section is third
of four panels, so a reader may have to scroll. Carrying an anchor
*through* a tab switch means either a second hash convention or a scroll
in JS, and neither is worth it for one card; the section has an
`id="vp-external-presence"` for whoever decides otherwise.

### 21.4 Verified

Rendered through `24-relationships-render.php`, `debug = 2`, so a missing
key or an undefined index lands in the markup:

| Reader | Value | Reads |
|---|---|---|
| site admin | `zxzhjlk.artenadigital.com` | `3 remote events across 2 feeds, 1 sync server`; three rows, each event under the source that holds it |
| plain user | same | `2 remote events across 2 feeds`; no server row, notice naming the server restriction |
| plain user, feed 2 unpublished | same | both restrictions named in one sentence, **count unchanged** |
| site admin | a value in no cache | `No feed or sync server you can see holds this value.` |

No PHP notice or warning in any fragment. `lookup_visible` on feed 2 was
flipped for the third row and put back.

The state not exercised: **nothing cached on the instance**. It needs
`caching_enabled = 0` across five feeds and a server, which is a bigger
flip than the one above and undoes work this phase depended on getting.
The branch is one `empty()` on two counts that the other rows prove are
populated.

### 21.5 The remote-event links were white on white

The first cut styled them as `<a class="badge … vp-external-event">`,
borrowing Bootstrap's pill shape. BS5's `.badge` sets `color: #fff` and
**no background**, so the chip was legible only while
`value-profile.css` supplied the rest — and the asset URL's `?v=` is
MISP's version rather than a file timestamp, so a browser that had
already fetched that stylesheet kept the old copy and rendered white
text on the white card.

Measured on the running instance, with the sheet blocked to stand in for
the cached one: `color: rgb(255, 255, 255)`, background
`rgba(0, 0, 0, 0)`. With it present the colours were correct all along,
which is why a cold-cache check did not reproduce it.

**Fixed by not borrowing `.badge`.** The chip carries its own radius,
size and weight, exactly as `.vp-rel-tag` above it already did — the
convention was there and the first cut ignored it. Blocked-sheet
fallback is now Bootstrap's ordinary link colour,
`rgb(24, 146, 177)`, which is readable rather than invisible. Contrast
was raised at the same time, 8% → 12% of the notion colour in light and
a 24% dark-theme rule matching the chip beside it.

**The lesson worth keeping:** a chip must not need a second stylesheet to
be readable. Whatever a missing rule degrades to has to be legible,
because `?v=` will not change when only a CSS file does.

### 21.6 "What is counted" now covers all four sections

§9 specifies this card as the engine's settings plus the arithmetic, and
after §20 it described three quarters of the tab. Four changes:

**A fourth fact line, for the rules section four answers to.** The other
three lines name correlation settings; this one names the cache — how many
feeds and servers have caching switched on, how many of them this reader
may read, and the per-source event cap, with the md5-membership caveat
that rules out a date or a near-match. Warn-toned when nothing is cached
at all, because then section four is empty for every value and the reason
is configuration rather than absence.

**It says how many sources the reader may read, and that is not a §14.6
disclosure.** *"5 feeds and 1 sync server cached. You may read 5 feeds and
0 servers of them."* names a difference the reader is on the wrong side
of — but it is a statement about the instance and their own role, not
about any value. Vary the value and the sentence does not move, which is
the same oracle test §20.5 applies to the role notice, and the same
answer. Stating it here is also what §9 says this card is *for*: rules
behind numbers that are otherwise invisible.

**Four bars, and each names its unit.** `Co-occurrence (values)`,
`Near-match (values)`, `Outside (events)`, `Asserted (claims)`. The units
were already mixed before — matches and claims are not values either —
but a fourth notion made the pretence expensive, so the labels now carry
it. `--vp-rel-external` keeps the section's own colour.

**The heading stopped claiming a sum.** It read *"The 1,214
machine-derived, split three ways"*, printing co-occurrence plus
near-match as one machine total. Remote events cannot join that number —
an event is not a value — and a total that silently excludes one of the
three machine notions is worse than no total. It now reads **"Four
notions, counted apart"**, which is §5's rule rather than an arithmetic
claim. `summary.correlations` still exists and still means co-occurrence
plus near-match; nothing prints it.

**A non-zero count never draws as an empty bar.** Three remote events
beside 1,214 co-occurring values rounds to `0%`, and the bar then
contradicted the `3` printed next to it. Anything present now floors at
4%; only a real zero is empty. This was already latent for a single
near-match.

Verified on the running instance in both themes, and through the Console
renderer as a plain user: `You may read 5 feeds and 0 servers of them`,
followed by *"The rest are withheld from your role, on every value
alike."*

---

## 22. Section three — a claim says what it points at

§8 gave a claim a target's kind and label, and a meta line with org, date
and distribution. Both turned out to be dead ends on live data: the label
named the far end and went nowhere, and a bare `ADMIN` beside a date did
not say which of the two organisation columns it was.

**Every name in a claim block is now reachable, and the far end carries
one line saying what it actually is.** The build, the query cost and the
verification are in
[`../value-profile-live/24-relationships.md`](../value-profile-live/24-relationships.md)
§19; what changes in *this* document is §8's description of the block.

**A claim is three lines, not two.** The target line, a detail line under
it, and the meta line — and the detail line is drawn in the same order
for every kind, *where it lives, what it is, who made it*, so four kinds
of target still read down one column:

| Kind | Label, linked | The line under it |
|---|---|---|
| `Event` | `#4182 AgentTesla host indicators [2026-06-30]` | `2026-06-30 · unpublished · StoneCo` |
| `Attribute` | `domain · deadnxuyla.ru` | `#4345 Test phishing event for SkillAegis · Network activity · url ↦ domain · ADMIN` |
| `Object` | `domain-ip · #1` | `#1 Test event · network · ADMIN` |
| `GalaxyCluster` | `TAG-53` | `Threat Actor · MISP Project` |

**A galaxy cluster resolves now.** §8 listed `GalaxyCluster` among the
target kinds and the fixture drew it as a name; live it was a bare UUID,
because `Relationship::getRelatedElement` stops at six types and this is
not one of them. The panel fetches it itself.

**A target that cannot be shown says so**, in place of the detail line
and warn-toned: *"Not held on this instance, or not visible to you — the
claim is shown by the UUID it names."* Both reasons, because the two
readers behind it are ACL'd and nothing here can tell them apart —
guessing at one would be the §14.6 disclosure, and naming both says
nothing about whether the thing exists.

**The meta line names which organisation is which.** `Asserted by ADMIN`,
and — only when `org_uuid` and `orgc_uuid` actually differ, which on an
instance nothing has synced into is never — `held by StoneCo` beside it.

**One link colour per claim, and it is the target.** The other three
names on the block (the event the target lives in, and the two
organisations) sit on lines that are already meta and keep those lines'
colour, underlining and colouring on hover. Four permanently blue runs
per block read as a page of links rather than as a sentence somebody
wrote — the same argument §19's sightings table makes for its two
columns.

**What the target *is* goes on hover**, not on a fourth line — built
straight after, and in `24-relationships.md` §20 and §21. A small `ⓘ`
between the target's kind and its name opens a card to the right of it,
with two sections:

- **`<kind>`** — the target's own stored columns. `Date`, `Analysis`,
  `Attributes` and its tags and clusters for an event; `Category`, `IDS
  flag`, `Comment` and first/last seen for an attribute; `Template` and
  the same for an object; `Tag` and a clipped `Description` for a
  cluster. Then its audience in words, and its UUID.
- **`In event #N`** — for an attribute or an object, which are always
  inside one: the event's own row of the same kind, tags and clusters
  included. An event target has no second section, because it is the
  event.

**The card says nothing about the claim.** The first cut opened with the
claim's type, author, date and audience — all four already on the block —
so a reader scrolled past what they had just read to reach what they
opened it for. `24-relationships.md` §21.

**A galaxy cluster is named, not printed as its tag string**, and a
cluster the viewer may not read is dropped rather than disclosed by the
tag that stores it.

**Hover and keyboard focus in CSS, not a Bootstrap tooltip** — this panel
arrives through `loadAjaxContainer` and MISP's only tooltip initialiser
runs at `DOMContentLoaded`, so a declared one would never bind. The glyph
sits *before* the target's name so the card's left edge does not inherit
a label of unbounded length; §21.3 has the measurement that forced it.

**Still not taken:** a claim's child Notes (§12's list, item 3). The card
is where their absence is now most visible.

---

## 23. The Neighbourhood, re-founded

**Design settled 2026-09-01.** Four sections of `24-relationships.md`
lead here — **§22** evaluated the shipped graph, **§23** read pivotick
1.6 against it, **§24** measured the topology and emptied six of the
twelve reads §22 rested on, and **§25** traced the three pivots that
pay. **§26** there records the eleven decisions and what each rejected.
This section is what ships. Every bare § below is this document's own.

§9 of this brief specifies a rail card whose graph puts the value at the
centre and hangs its neighbours off it. That graph was built in phase 24
and it is a **star** — every edge incident on the centre — which carries
nothing the three panels beneath it do not already print, and which on
live data draws 36 of `8.8.8.8`'s 10,024 neighbours. Neither the caption
nor the picture is wrong. The relation being drawn is.

**What replaces it: the object, not the event.** Two values that share an
*event* share a container, and §24 measured what that is worth —
components equal event count, no neighbour spans two, bridges fire once
in sixteen values. Two values that share an *object* are held together
by something that was written down: a `passive-dns` object says *this
name resolved to this address between these dates*, a `domain-ip` says
*this domain is on this address*. §25 traces three chains that pay, and
all three run through objects.

The arithmetic follows: `8.8.8.8` has **22** object-mediated neighbours
against 10,024 event-mediated ones, and **95.6 %** of values that sit in
objects have fifty or fewer.

### 23.1 Five layers

Each switchable, each with its own stroke, shape and row in the key.

| Layer | What an edge means | Typical count |
|---|---|---|
| **Object siblings** | shares an object with this value | 14–42 |
| **Events** | this value appears in this event | 1–35 |
| **Near-match** | CIDR containment, ssdeep proximity | a handful |
| **Asserted** | an analyst wrote this claim | a handful |
| **Object references** | MISP's own typed relation between two objects | 4–17 |

**The event layer draws events, not their values.** An event node is the
event and stops there; it does not expand into the ten thousand
attributes inside it. That is what keeps the layer affordable and what
makes it worth having — *which events is this value in* is a real
question, and it is the one an event node answers.

**Object references bring object nodes.** A reference is recorded
between two objects, so drawing it anywhere else would be a re-telling.
`referenced_type` is `0` for an attribute target (1,142 rows on the
verification instance) and `1` for an object target (10,191); both
resolve. This is the only layer with a node kind that is not a value or
an event, and the key says so.

**Near-match and asserted survive unchanged.** They are small, they are
semantically distinct from an object join, and §5's separation is the
thing this tab lives or dies by. An analyst claim is the only edge on
the page a human wrote.

### 23.2 Edges say what they are; nothing is classified

Every sibling edge carries the object's own words: **`passive-dns ·
rrname → rdata`**, **`file · md5 ↔ sha256`**, **`domain-ip · domain →
ip`**. A reference edge carries `relationship_type` verbatim —
`hosted-by`, `communicates-with`, and `Crush` where somebody typed that.

The alternative was a relational/descriptive split, sorting templates
that link two things from templates that describe one. It is not taken,
for a reason that is a fact rather than a preference: **MISP records no
such distinction.** All 373 object templates carry `attributes`,
`description`, `meta-category`, `name`, `uuid`, `version`, `required`
and `requiredOneOf` — nothing structural — and `meta-category` is a
domain label that puts `passive-dns` and descriptive network objects in
one bucket and `virustotal-report` in `misc`.

So the split would have to be hand-maintained against 373 templates from
an upstream project, and it would be wrong at the edges anyway: the
`url` object holds `url`, `domain`, `host`, `ip`, `resource_path` — the
domain is both a different thing and a part of the URL, and no boolean
is true about it.

A label is true where a classification would be arguing. It also tells
the reader more: *`rrname → rdata`* says which end they are standing on,
which a flag cannot.

**Deferred, and separable:** a short promote list used only to rank
pivot-worthy templates higher. Ranking is a different decision from
meaning, it needs its own evidence, and an unlisted template must still
draw and still label.

### 23.3 The roll-up, and the bound that is not the wire

Above a legibility bound, siblings collapse into **one node per
template**, carrying its object count and expandable in place. `0.0.0.0`
— which sits in 32,922 `paloalto-threat-event` objects and one `pe` —
draws two nodes, and the count is the finding: 32,922 near-identical
objects reads as flood-capture noise at a glance.

**Nothing is ever truncated.** Every sibling is drawn or counted in a
roll-up, so no caption anywhere states a fraction of a whole the reader
cannot reach. That is the specific defect §22.1 identified, removed
rather than reduced.

**The bound is what a reader can take in, not what the wire can carry.**
Those give very different numbers: the fragment budget would allow
roughly 2,500 nodes (phase 22 measured 5.9 MB as *"a fragment that does
not arrive"*, and this tab's heaviest today is 1.18 MB), and 2,500 nodes
is an unreadable hairball that arrived intact. Bounding on legibility
means the payload never approaches the wire on any value, and pivotick's
eventual graph-coarsening becomes an enhancement here rather than
something the design leans on.

**The threshold is ~150–200 and is not yet fixed.** It must be measured
against real fragment weight **across all five layers at once**, not per
layer, before it is written into a constant.

### 23.4 Two surfaces, two modes

**The rail is a peek.** `UI.mode: 'viewer'`, with `navigation` left
unconfigured so its viewport rail does not mount — `UIManager.ts:241`
gates it on `o.navigation?.enabled`. The result is a card that can be
nudged and zoomed but carries no controls of its own. It rolls up hard:
**one node per template, 1–5 in practice**. `luxtrust.support` draws a
single `passive-dns · 6 objects`; `8.8.8.8` draws three. The only
control is MISP's own `Open the full graph` beneath it.

Rolling up per template is what makes the rail legible at 340px without
labels overlapping — §10.3 of `24-relationships.md` measured 37 labels
as illegible there, and this draws five.

**The overlay is the workbench.** `UI.mode: 'light'`, carrying
pivotick's legend, filter panel and chrome. `editors.deletion`,
`nodeCreator`, `nodeEditor` and `edgeEditor` are all `enabled: false` —
`light` mounts the mode rail and tool panel, and those carry the Create
tools. The Notes affordance is switched off through the upstream flag
being added to pivotick for this purpose; until it lands the overlay is
mounted without it by whatever means that flag provides.

The overlay rolls up at the §23.3 threshold and shows values rather than
templates.

**The data dock is not used.** `24-relationships.md` §23.4 argued for it
against a 10,000-node graph with no table equivalent. The graph now
holds tens of nodes and this tab already carries two sortable, faceted,
paginated tables that do the job better.

### 23.5 Two new sections

**`value_relation_dated`** — title **Dated relations**, sub-line naming
the relational objects it read. One row per dated edge: the far value,
what the object calls it, `first seen`, `last seen`, and the object's
own origin where it records one.

`draculax.myq-see.com.` renders as five rows — `141.255.159.82`
2017-04-11, `168.181.48.248` 2017-04-14, `168.181.51.45` 2017-04-18,
`141.255.147.117` 2017-04-25, then `200.101.151.150` 2021-03-30. Four
addresses in fourteen days, four years of nothing, then one more.

This is a table and not a canvas because the insight is entirely in the
dates, and a canvas has nowhere to put them. §25.1 is the worked case.

**`value_relation_references`** — title **Object relationships**,
sub-line stating its coverage. What this value is related to through
`ObjectReference`, in both directions: **directly**, where the reference
targets this value's own attribute, and **through its parent object**,
where the reference is between the object this value sits in and
another. Each row: the relationship type verbatim, the direction, the
far object's template, and the far object's own identifying values.

`18.117.184.102` renders a `hosted-by` reaching the `passive-dns` object
whose `rdata` is `cns-lu.com` — which is §25.3's bridge, recorded by a
person rather than inferred by a join.

**Both sections state their coverage rather than let a reader infer
completeness.** 568,606 attributes sit in objects against 2,216,345 that
do not, and 7,905 of 69,976 objects carry any reference at all. A reader
looking at an empty Object relationships panel is looking at the common
case, and the panel says so.

### 23.6 The library

pivotick **1.6.0** (released 2026-08-28). Both files are taken from the
`worktree-pivotick-v16` branch — `pivotick.iife.js` 775,624 B and
`pivotick.css` 196,431 B, byte-identical to `~/git/pivotick/dist`.

A JS-only swap would be wrong: the CSS carries the renamed flyout hooks
(`.pvt-viewflyout-*` → `.pvt-flyout-*`, `24-relationships.md` §23.8).

**The event Pivot Explorer is not re-verified here.** That work is owned
elsewhere, on its own branch. This tab takes the bundle for the features
it needs and does not claim the wider verification.

What the design reads from 1.6: `render.edgeTypeAccessor` and
`edgeStyleMap` for the five kinds, `UI.filter.edgeFacets` for the layer
switches (a toggle does not move the layout — the link force gates on
`Edge.visibleIgnoringLayer`), `UI.legend` with `sections` for the key,
`NodeStyle.badges` for roll-up counts, and `textTruncate: false` for
labels. Cluster collapse is not new — `hasChildren` is already in the
bundle MISP ships.

### 23.7 States

| State | What renders |
|---|---|
| Populated | five layers, rail rolled to 1–5 template nodes |
| No objects | the ~80 % case. The object, reference and dated panels state it plainly; the event, near-match and asserted layers still draw |
| Rolled up | `0.0.0.0`: two template nodes carrying 32,922 and 1, expandable |
| No dated relations | the value sits in no object that records a date — said in those words, not as a generic empty |
| No references | the common case (11 % coverage), and the panel says which case it is |
| Unreadable | every event oversized, as `1.0.155.105` and `github.com`: no neighbours to fold, and the suppressed band rather than an empty one |

### 23.8 Verification values

§12.1 of `24-relationships.md` names six. Three are added, because none
of the six exercises what this design is built on:

- **`draculax.myq-see.com.`** — five dated `passive-dns` resolutions
  spanning four years. The Dated relations panel.
- **`45.77.250.80`** — 42 siblings across 23 `domain-ip` objects, whose
  22 domains are a RedAlpha target list. The fan, at a size that draws.
- **`18.117.184.102`** — four names across two impersonated brands, and
  a `hosted-by` reference to the object holding a third. The bridge, and
  the reference layer.

`0.0.0.0` (35,102 siblings, 2 templates) and `443` (845 siblings, 5
templates) are the roll-up stress cases.

### 23.9 Not in this phase

- **Live expand-one-hop.** Clicking a neighbour and fetching *its*
  relationships. The Object relationships section gives the second hop
  as a panel, which is how every other notion here works, and §25's
  three chains are all readable that way. Recorded as the next
  increment.
- **The promote list** (§23.2).
- **A passive-DNS source lane on the Timeline tab.** Resolutions carry
  dates, so by that tab's charter they belong in its chronology beside
  sightings and publications. Timeline is built and verified, and a new
  lane means re-verifying it, so it is named here and scheduled there.

---

## 24. Built, and the seven places it departed from §23

Shipped 2026-09-01. §23 is the design; this is what the code does and
where the data made it do something else. Measurements are in
`24-relationships.md` §27.

**The tab is six sections now**, top to bottom: co-occurrence, **Dated
relations**, near-matches, outside this instance, **Object
relationships**, asserted. The two new ones sit where their notion
belongs — the dated table directly under the object join it dates, and
the references immediately before the analyst claims, because those two
are the pair a person wrote.

### 24.1 `disable_correlation` is the rule §23.2 was looking for

§23.2 rejects a relational/descriptive split of templates on the
grounds that **MISP records no such distinction**. That is true of
templates. It is not true of *attributes*: every attribute carries
`disable_correlation`, and it is the template's own statement of which
of its fields are there to link and which are there to describe.

    passive-dns   rrname 0 · rdata 0
                  rrtype 1 · count 1 · origin 1
                  time_first 1 · time_last 1

Four things rest on it, and none is a classification of anything.
They read it through `ValueFieldKind` since §27; before that each spelled
the same test itself.

- **The far value in a dated row.** Without it `draculax.myq-see.com.`
  renders `rrtype = A` and `count = 1` as dated relations, which the
  object does say and no reader wants.
- **The edge label.** The bookkeeping fields outnumber the linking ones
  on every `passive-dns` object, so a label ranked over all of them read
  **`rrname → count, origin, time_first`** — naming everything the
  object says except the thing it exists to say. Ranked over the
  linking ones it reads **`rrname → rdata`**, which is §23.2's own
  example.
- **The sibling table's order and its Field kind facet**, added later —
  §27.
- **Which attributes may identify a far object**, in
  `Value::referenceFacesFor` — a far object is named by what it links,
  not by its bookkeeping columns. This one applies the rule in SQL,
  which is why the class exposes the condition as well as the predicate.

It is imperfect where the data is: `domain-ip` carries
`disable_correlation = 0` on 41 of its `first-seen` rows and 1 on 22 of
them, so `45.77.250.80` labels `ip → domain, first-seen, last-seen`.
That is what those objects say, and the panel says it. §27 says how the
sibling table votes on the same dirt.

### 24.2 A dated relation is an object recording *two* dates

§23.5 says "one row per dated edge" without saying what makes an edge
dated. The instance answers it: **40,098 objects carry exactly one
`datetime` attribute** and 32,892 of those are `paloalto-threat-event`
recording when its row was generated, with another 6,740
`virustotal-report` recording a last submission. Neither is a claim
about when a relation held.

So the rule is a **pair**: an object recording two or more `datetime`
attributes recorded a span, and one recording a single one recorded a
moment. It keeps `passive-dns` (`time_first`/`time_last`) and
`url-honeypot-detection` (`first-seen`/`last-seen`), drops the
bookkeeping, and needs no per-template list. The panel's caption states
the rule rather than leaving a reader to infer it.

**First and last are the earliest and the latest**, and each cell
carries the object's own word for its end underneath. Where the two are
one instant the second cell reads *same instant* rather than repeating
the timestamp, which looked like a rendering fault in the first render.

### 24.3 The roll-up reads the census, not the fold

§23.3 says `0.0.0.0` draws two nodes carrying 32,922 and 1. Built from
the fold alone it draws **one**, and the count is wrong: the object read
stops at `SIBLING_OBJECT_CAP`, all 500 of those objects are
`paloalto-threat-event`, and the single `pe` object — the interesting
one — is never seen at all.

`ValueProfile::objectCensus` therefore replaced `objectFootprint`: the
same query that already ran when the cap bit now carries
`GROUP BY Object.name`, so it returns the per-template breakdown as well
as the total for the same cost. `0.0.0.0` draws
`paloalto-threat-event · 32,921 objects` and `pe · 1 object`.

A template the fold never reached draws its count and no values, which
is exactly what is known about it.

### 24.4 The rail rolls every layer, not only the object one

§23.4 specifies the object layer rolled to one node per template and
leaves the other four unstated; §23.7's *no objects* row requires them
to draw. Measured, drawing them one node at a time puts `8.8.8.8` at
**fourteen labelled nodes in 340px**, which is the illegibility §10.3 of
`24-relationships.md` measured.

So the rail draws one node per object template and **one counted node
per other layer** — `17 events`, `6 analyst claims`, `6 references`.
Two to ten nodes on every verification value, labelled and readable, and
what it folds is one click away in the panel beneath it. The overlay
draws each one.

### 24.5 Three findings about pivotick 1.6.0

- **`navigation` now defaults to on.** §26.9 of `24-relationships.md`
  settled `UI.mode: 'viewer'` on the reading that leaving `navigation`
  unconfigured keeps its viewport rail unmounted, because
  `UIManager.ts:241` gates on `o.navigation?.enabled`. **1.6.0 ships
  `navigation: {enabled: !0}` as the default**, so silence now opens the
  gate rather than closing it, and the rail grew four buttons over its
  own canvas. It is passed `enabled: false` explicitly. The overlay
  keeps them.
- **`textTruncate: false` belongs to the rail only.** It is what lets
  the rail print `paloalto-threat-event` instead of `pa…nt`, and it is
  what turns the overlay into a wall of text: fifty-two nodes there
  include event titles like *Kunai Analysis Report — Malware Sample
  Abusing Open Recursive DNS for Exfiltration*. The overlay keeps
  pivotick's truncation and its tooltip.
- **A rolled node takes its layer's name**, so the reference layer's
  roll-up is `kind: 'reference'` while its expanded nodes are
  `kind: 'object'`. The style map needs both keys; without the alias
  pivotick draws its own default and `6 references` was a blue circle in
  a card where blue means nothing.

### 24.6 The sketch became a composition strip

The fallback for a browser where the 776 KB bundle does not arrive drew
one labelled SVG region per notion, and there were three. There are
five, and five regions in a 300×260 box is a diagram about its own
layout. It is now five rows — notion, count, and the reason when a
count is zero — which is what a reader actually needs when there is no
canvas, and it drops about 150 lines of hand-placed SVG.

### 24.7 A reference with both ends in this value's own set is dropped

Not in §23.5, and it is a third of the rows. `18.117.184.102` sits in
four `passive-dns` objects and **each of them carries a `hosted-by`
pointing back at the bare attribute** — the object holding the value
saying the value hosts it. Three of those survive the direct read and
one the parent read; all four relate the value to itself.

The rule is exact rather than heuristic: a reference is dropped when its
far end is one of *this value's own* parent objects or attributes.
`18.117.184.102` renders eight rows instead of twelve, and every one of
the eight names something else.

### 24.8 What is still open

- **The `light` overlay** with pivotick's own legend and
  `UI.filter.edgeFacets` layer switches (§23.4, §23.6). The feed carries
  the five edge kinds the facets would read; both surfaces are `viewer`
  until the upstream flag that switches Notes off lands.
- **The legibility bound is measured and is not the binding one.**
  `GRAPH_SIBLING_BOUND` is 150, and the fold's own `RELATION_ROW_CAP` of
  100 binds first — the overlay expands only when the fold carried every
  sibling it counted, because drawing 100 of 120 would put a fraction
  back on the canvas. Raising the bound alone changes nothing.
- **The promote list** (§23.2), **the Timeline source lane** and **live
  expand-one-hop** (§23.9) are unchanged.

---

## 25. One word for direction, and a contents page over seven tables

Two complaints about the built tab, both about reading it rather than
about what it holds.

### 25.1 The tab said the same thing in two vocabularies

`ObjectReference` and analyst-asserted `Relationship` are the two
notions on this tab a **person wrote**, and both of them relate this
value to something at the other end of an arrow somebody drew. §5's
separation puts them in sections of their own, correctly. It did not
stop them describing the same property in two different ways:

| | Object relationships | Asserted by analysts |
|---|---|---|
| words | *points at it* / *points away from it* | `INBOUND` / `OUTBOUND` |
| shape | grey text, long arrow | bordered uppercase pill |
| class | `.vp-ref-dir` | `.vp-rel-dir` |

The convention underneath was already the same — inbound is the far end
naming this value, outbound is this value's end naming something else,
and both drew the arrow the matching way. Only the surface disagreed,
and it disagreed twice in a page's scroll.

**Resolved onto the pill and onto `inbound` / `outbound`.** The prose
form is nine words to say what the arrow already shows, it wraps inside
a table cell as narrow as a relationship name, and `inbound` is the word
the section's own explanatory text, the model and `ObjectReference`
itself already use. The sentence it replaced is not lost: it is the
chip's tooltip, and it now says what the direction means **in that
section's terms** — *"Somebody else's object names this value"* against
*"Something else claims a relationship to this value"*. That is the one
thing the two sections genuinely do not share, so it is the one thing
that stayed per-section.

One element, `value_relation_direction.ctp`, and one class. The arrow
takes `--vp-panel-color`, so the chip is purple in the reference table
and crimson in the claims list without either section restyling it —
same shape, section's own colour, which is the rule the rest of the tab
already follows.

### 25.2 Seven tables, and no way to see what is in them

The tab is seven tables tall — the co-occurrence endpoint draws two —
and a reader who wants to know whether anybody **asserted** anything
about this value has to scroll past a hundred co-occurring values to
find out. Every section states its own headline number in its own
header, honestly and in words, and all seven of those numbers are below
the fold.

**A contents strip, seven cards, at the top of the tab.** Each card
carries one section's headline figure, its unit and its name, and a
press scrolls to that section. Order is page order, so the strip reads
as the contents it is.

**It holds no data and costs nothing.** This is the constraint the
design turns on: the six panels are lazily loaded *because* some of them
are expensive — the co-occurrence scan reads up to 20,000 attribute rows
— and the tab bar itself carries no count for exactly that reason
(§`24-relationships.md` on `forTabCounts`). A strip that computed its
own seven totals would run every one of those reads again to print seven
integers. So the strip is markup, and each panel stamps its own figure
on itself as it lands:

```
data-vp-rel-summary="references"   which card this is
data-vp-rel-count="8"              the figure, formatted by PHP
data-vp-rel-note="not read"        the qualifier a bare integer would lie without
```

`initRelationSummary` in `value-profile.js` copies them across on
`misp:container-loaded`.

**Not yet read is not nothing.** Until its panel arrives a card shows a
pulsing placeholder rather than a zero — the distinction this whole tab
is built on, applied to its own contents page. A section that really is
empty keeps its card and loses its tint, so the eye lands on what is
there.

**Seven cards over six endpoints.** Two of them point into the
co-occurrence container, and the siblings card is *dropped* when that
panel renders no sibling table — a value sitting in no object beside
anything else has no such section, and a card pointing at a table that
does not exist is worse than no card. The rule is general: a card whose
container has loaded without producing its section is removed.

**The press does not touch the address bar.** The cards are anchors —
real destinations, so middle-click works and they survive the script
never running — but the handler takes the press over to scroll smoothly
and leave the hash alone. The hash on this page routes *tabs*, and
`#vp-rel-sec-dated` in the URL would send a reload to the Overview tab.
The anchor is on the lazily-loaded **container**, not on the panel, so
it can be reached while the fetch is still in flight; once the panel is
there the jump prefers it. Both carry `scroll-margin-top: 76px`, because
the navbar is fixed and would otherwise eat the panel header the press
was aimed at.

### 25.3 Verified

Driven in a real browser against the dev instance, both themes.

- **`8.8.8.8`** — all seven cards fill: 22 siblings, 10,024 values, 0
  relations, 0 matches, 16 remote events, 6 references, 6 claims. Both
  sections render both chips, identical in class, size, casing and
  border, and each arrow takes its own section's colour
  (`#6d3fd1` / `#8f2d56`).
- **`213.205.40.169`** — sits in no object, the co-occurrence panel
  draws no siblings table, and the strip drops that card and renders
  six.
- **The press** — a jump to Dated relations lands the panel at exactly
  76px, clearing the navbar; the hash is unchanged; the panel flashes
  once. A jump to the last section stops at the document bottom, which
  is the browser's floor and not a defect.
- **Widths** — 2 columns below 768px, 4 up to 1800px, 7 above it. No
  card title is truncated at any of the three; 7-up was measured to
  truncate below 1800px, which is why the tier sits there.
- **`.vp-ref-dir` and the string "points away from it"** — zero
  occurrences in the rendered page.

The suppressed co-occurrence branch — the card reading `—` with a *not
read* note — is the one state not exercised live: it needs a value
whose every event is over `size_cap`, and none on the instance is.

### 25.4 "What is counted" was counting four of seven

The rail card's whole claim is *this is the tab's arithmetic, stated
rather than left to be inferred*. It listed four notions. The tab has
seven, and has since the object-mediated sections were split into panels
of their own — so the card was quietly short of three of the numbers
printed beside it, and a reader taking it at its word would have read
the sections above as the whole of the tab.

**All seven, in page order, in the panels' own words.** The rail, the
contents strip and the panel headers now name each section identically,
so the three surfaces cannot be read as describing different things.
Units go with them: values, relations, remote events, references,
claims — five units over seven notions, each row naming its own, nothing
summed.

**A fourth rule, which is that three sections have none.** Every setting
the card states governs *some* of the tab, and with seven bars beneath
them they read as governing all of it. The two object joins read
attributes and the reference section reads `object_references`, so
neither the correlation limit nor the ssdeep threshold touches them —
they survive a value the limit suppressed, which is exactly the case
`0.0.0.0` is. Saying where nothing governs is the same disclosure as
the three rules above it.

**Floors, and the one place a floor is nonsense.** `siblings_capped`,
`dated_capped` and `references_capped` are new on the summary, because
a rail printing `795` beside a panel printing `≥ 795` is the card
contradicting itself. The `≥` is dropped when the count is zero: `≥ 0`
means *at least none*, and reads as a rendering fault. A capped zero
prints `0` and carries the bound in its tooltip.

**Two sentences at the foot were wrong at zero.** `__n`'s plural gave
*"The 0 claims are counted apart from all of them"*, which is not
English — a zero now gets its own sentence. And the sibling clause said
*"795 sit in an object beside it"* on the same value whose bar read
`≥ 795`; it now says *"At least 795 values sit…"*.

**Its own row component.** `.vp-reporter`, which the Sightings rail
shares, fixes the label at 40% of a card a quarter of the page wide —
about twenty characters. Right for `CIRCL`, not for `Outside this
instance` beside the unit it counts. `.vp-split-row` puts the name and
its figure on one line and the bar on the next, so all seven names are
whole; the Sightings panels are untouched.

**Verified.** Every one of the seven rows was read out of the rendered
page and matched against the strip card of the same name — seven of
seven agree, on `8.8.8.8` (light) and `0.0.0.0` (dark). No label is
clipped in either. `0.0.0.0` exercises all three of the new states at
once: a capped non-zero (`≥ 795 siblings`), two capped zeros rendered
plain with tooltips, and the zero-claims sentence.


---

## 26. A short timeline over the dated table

Shipped 2026-09-01. Section five was a table only, and the argument for
that was written into its own docblock: *the insight in a resolution
history is entirely in the dates*, and a canvas has nowhere to put
dormant-then-reactivated. That argument was right about a canvas
**instead of** the table and wrong about one **above** it — the shape of
four addresses in fourteen days, four years of nothing, then one more is
read in a glance and never in a column of five timestamps.

### 26.1 The Timeline tab's lanes, not a new drawing

The strip is `Elements/Values/View/value_span_strip.ctp`, and it reuses
the Timeline tab's stylesheet rather than restating it: `.vp-lanes`,
`.vp-lane-axis`, `.vp-lane-svg`, `.vp-lane-span`, `.vp-lane-mark` and the
`--vp-tl-…` palette. A span here and a span on the Timeline are the same
mark, because they are the same claim.

What is deliberately **not** shared is the machinery. Those lanes are
redrawn in JavaScript from a JSON feed, over a rolling twelve-month
window a brush moves, across eight fixed sources. This strip is rendered
by the server, over the rows' own extent, with lanes the caller names.
Sharing the CSS and not the code is what keeps the two from drifting
without making either carry the other's requirements.

### 26.2 Three decisions the data forced

**The axis is the extent, not a calendar.** Twelve months is right for
*what happened lately*, which is the Timeline's question. This section
asks *how long did each of these hold*, and `draculax.myq-see.com.` —
2017 to 2021 — would be an empty strip under a twelve-month axis.

**A lane is a template, not a row.** `github.com` has 46 dated relations
and one template. 46 lanes is a second table; one lane is a reading, and
the strip stays two rows tall on every value on the instance. It is also
the grouping the panel header already names, so the strip introduces no
vocabulary of its own.

**A span too short to draw is drawn as a moment.** This one came out of
the render. All five of `draculax.myq-see.com.`'s resolutions lasted an
instant or thirteen minutes; over a four-year axis all five are two
pixels wide, so bars made the section's own argument invisible, and
widening them would have claimed days MISP never recorded. Under four
viewBox units a span becomes the Timeline's point mark instead — narrow,
taller, fully opaque — which reads as *a thing happened here* rather than
*this lasted a while*, and is the distinction the table beside it already
draws with the words *same instant*. The legend appears only when the
strip is mixing the two, because that is the only time the difference is
a question.

### 26.3 Filterable through the list that already owns the rows

No new filter framework. The panel was already a `[data-vp-list]`, so the
rows gained `data-vp-facet` tokens and three counted groups — template,
origin, related-value type — through `value_facet_group`, and the fold
stamps the tokens beside the counts so the string a facet counts and the
string a filter matches come off the same line.

**The window is interval overlap, expressed in a point-in-range
control.** `data-vp-range-from` is bound to the row's `last` and
`data-vp-range-to` to its `first`. A row therefore survives when its span
*ends* at or after the window opens and *starts* at or before it closes,
which is overlap. Binding both bounds to one key — the obvious reading —
asks whether a single instant falls inside the window, and the
resolution that ran 2013→2018 would vanish from a 2015 window it covers
completely. Measured: a 2014-01-01→2015-01-01 window on `8.8.8.8` keeps
exactly the 2013→2018 row and drops the two that begin later.

**The strip dims, it does not redraw.** `paintSpanStrips` runs inside
`refreshList` for any list holding a `[data-vp-span-strip]`, and marks
the filtered-out spans rather than removing them: the axis is the period
the *section* covers, so a narrowing that drops the oldest resolution
must not shorten the axis under the reader — the gap it left is the
reading. Lane counts fall to what survived and carry the total beneath
them, because a lane reading 46 over three visible spans is a number
nothing on screen agrees with.

One CSS trap worth recording: `.vp-lane-span` sets an opacity six hundred
lines further down the same stylesheet, so a single-class dim rule has
equal specificity and loses on order. It looked right in the file and did
nothing on the page. `.vp-lane-span.vp-lane-span-off` fixes it.

### 26.4 Verified

Read out of the rendered page in a browser, light and dark:

| Value | Lanes | Marks | Reads |
|---|---|---|---|
| `8.8.8.8` | 2 | 3 spans | the seeded history, both templates |
| `draculax.myq-see.com.` | 1 | 5 moments | §25.1's shape — cluster, four-year gap, one more |
| `github.com` | 1 | 46, mixed | one lane, still legible; both legend keys |
| `9.9.9.9` | — | — | empty state unchanged, no strip |

Ticking `passive-dns` on `8.8.8.8` takes the table to two rows, dims the
`domain-ip` span, and drops that lane to `0` over `of 1`. The two windows
above behave as §26.3 states. Dark theme resolves `--vp-rel-object` to
its light-on-dark variant for the marks, the lane borders and the ticks.

A pre-existing page error, unrelated and untouched:
`mispOvermind.js:2489` calls `e.target.closest` on a target that has no
`closest`. It fires on values with no dated relations too.

## 27. The sibling table leads with the fields you can pivot on

`In the same object` ranked every field by object count, so on
`8.8.8.8` its first screen was one pivot and seven rows of
`paloalto-threat-event` bookkeeping — `type = THREAT`,
`threatid = UDP Flood`, `srcloc = United States`, `app =
not-applicable`. Descriptions of the telemetry that caught the address,
in the panel whose caption calls itself *where you pivot next*. The tab
had known the difference since §24.1 and only the graph and the dated
table were using it.

**The order.** Linking siblings first — `disable_correlation = 0` —
then object count as before. Nothing is hidden and nothing is dropped;
the descriptive rows keep their order and their pages. Sorting a column
still returns to this order on the third click, and the panel says so.

**The kind is a property of the field.** The vote is taken per
`(template, relation)` over the attributes the panel read, not inside
each `(template, relation, value)` triple. Taken per triple it renders
the *same field* two ways in one table:
`url-honeypot-detection · last-seen` carries 0 on 376 attributes and 1
on 10,688, so one timestamp is dimmed and the next is not with nothing
on screen to say why. A facet called **Field kind** has to mean the
field. The vote is local to what the panel read — the same field can be
classed differently on two values — which is the honest reading and the
caption states it.

**File objects are the cleanest case, and they cost the caption its
example.** Every hash `file` declares is `disable_correlation = 0` and
the instance agrees without exception — md5 8,645, sha256 10,115, sha1
8,088, ssdeep 762, tlsh 449, vhash 96, all flagged 0, not one flagged 1.
`filename` is the opposite: the template declares it 1 and the
attributes split 708 to 2,576 the same way. That is MISP being right —
correlating on `invoice.pdf` is a false-positive engine — but the
panel's caption named *a file's other hashes and its filename* as where
you pivot next, and the table now dims the second half of its own
example. Worse, it dims it *unpredictably*: on a hash whose object
holds one of the 708 the filename is undimmed, and on the next hash it
is not. The caption now names hashes as the pivot and filename as the
clearest descriptive field, which is what the reader sees two rows
down.

**Three surfaces, no new element.** A descriptive row's relation chip
is dimmed and untinted, against the linking chip's object tint. A fifth
dropdown, **Field kind**, joins the four in the sibling `Narrow by`
bar, counted against a two-word vocabulary so it can show a zero. And
the caption states the rule the same way the dated panel states its
pair rule.

**What the order costs, and what pays for it.** The sibling bar counts
over the whole fold and the table carries the first hundred rows, and
this list — unlike the ranked one beside it — has no narrowing endpoint
to hand an unanswerable tick to. Once linking rows sort first, a value
whose siblings run past a hundred fills the table with them: `0.0.0.0`
has 795 triples, 245 linking, and all 100 carried are linking. So
*Descriptive 550* would be a control that can only empty the table.

The fold now marks every entry in all five groups with `listed`, the
number of carried rows it reaches; `value_facet_group` takes a `local`
flag for a panel with no endpoint behind it and greys such an entry
rather than offering it; and the caption names the cut where it bites
— *"550 descriptive siblings are counted below and not listed: the
table carries 100 rows and the linking ones fill them."* This also
retires four groups' pre-existing dead entries, which had been emptying
the table since phase 18.
