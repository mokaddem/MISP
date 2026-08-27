# Value Profile — building §9, and the fourth demo value

**Phase 18.** Extracted from [`value-profile-page.md`](../value-profile-page.md), where this
was §10. **The section numbering is deliberately kept:** the corpus carries
over a hundred references of the form `§10.x`, and they resolve to the
headings below rather than to anything in the main document.

---

## 10. Phase 18 — building §9, and the fourth demo value

§9 is a specification and phase 17 shipped it as one: commit `5a31d3f6`
touched `prd/value-profile-page.md` and nothing else. Phase 18 is that
specification built — the aggregated siblings section, the fourth demo value it
cannot be built without, and the one piece of shared machinery that had to give
way for two paged sections to live in one panel.

### 10.1 The sibling section, as it now reads

`siblings` stopped being a list of attribute rows and became a section that
knows its own bounds. The fixture holds:

| Key | What it is |
|---|---|
| `rows` | aggregate rows, ranked by `objects` descending |
| `total` | distinct `(template, relation, value)` triples the value has |
| `raw` | sibling attributes those triples stand for |
| `objects` / `in_objects` | objects joined, and objects before the cap |
| `cap` | `SIBLING_JOIN_CAP` and whether it bit |
| `hidden` | siblings inside a readable object that distribution withholds |
| `page_size` | rows per page, the tab's existing eight |

Two builders produce it, and the split is the point. `relSiblings()` takes the
raw seven-column table the three existing values already had and groups it, so
*the aggregation of three rows is demonstrably three rows*. `relSiblingGroups()`
takes a pre-aggregated table for a value whose raw join is 926 rows, which is
not a thing to write down — the same *listed prefix, stated total* idiom the
co-occurrence pane beside it already uses for 1,462 correlations.

The row carries `object template · relation · sibling value · type · objects ·
events · reported by`. Two details are not in §9.7 and are there anyway:

- **A row standing for one object keeps its event link.** Aggregating to a
  triple loses the object ids, so a row standing for many can only give a
  count — but the singleton case is unambiguous, and the three existing values
  are all singletons. §9.10.2 asks for their row content to be unchanged, and a
  link silently demoted to the digit `1` would not have been.
- **Every count is written `≥` when the cap bit.** §9.6 says an aggregate over
  a truncated set is the one thing this section must not produce. The reading
  taken here is that it must not produce an *unlabelled* one: the cap notice
  states `500 of 683`, and each count that could be higher says so in the cell.

### 10.2 Two paged sections in one panel

The siblings section needed its own pager, and `value-profile.js`'s faceted-list
machinery had no notion of one list inside another: an outer `[data-vp-list]`
took the *first* `[data-vp-list-rows]` under it, which after this change is the
siblings table. Left alone, the co-occurrence pane would have paged the sibling
rows and printed a range belonging to neither section.

Fixed generally rather than worked around. `ownNodes()`/`ownNode()` return the
nodes whose nearest `[data-vp-list]` ancestor *is* this list, and the row host,
the pager, the range spans, the sort control and the empty-state lookups all go
through them. The alternative — lifting the siblings into a fourth card on the
tab — was rejected: phase 11 put it inside section one deliberately, and
changing the tab's section count to route around a selector is the wrong repair.

Narrowing controls are still not nested, and the JS says so: the inner section
has none, and one that grew them would need the facet lookups scoped the same
way.

### 10.3 `45.155.205.233`

A fast-flux C2 node on bulletproof hosting: 812 occurrences in 137 events from
23 organisations over fourteen months, and past `MISP.correlation_limit`, so the
engine stored nothing. MALICIOUS, score 93.

Suppressed *and* numerous is the deliberate combination. §9.3 said the worst
case is the value whose correlation band says nothing was stored, because the
one unbounded section is then the only content on the tab — and that case had
to be looked at rather than inferred. It is now the fourth value's own
Relationships tab.

Almost nothing is typed in row by row. The 748 occurrences are generated from an
explicit plan and every count the page states is tallied back from the rows that
were generated, so the plan and the page cannot drift:

```
type          601 ip-dst · 118 ip-src · 29 domain|ip
category      664 Network activity · 71 Payload delivery · 13 External analysis
to_ids        592 set · 156 unset
distribution  402 all · 96 connected · 171 community · 52 sharing group · 27 own
objects       683 in one — 397 domain-ip, 229 network-connection, 57 ip-port
deleted       11        seen dates absent 21        pending proposals 6
```

The permutations are index arithmetic on multipliers coprime with 748, not
randomness: the fixture has to render the same page twice.

**The siblings arithmetic, so a later reader can check it rather than take it.**
683 objects hold the value and the join reads the most recent 500. Those 500
hold 926 sibling attributes — 291 `domain` and 96 `first-seen` on `domain-ip`,
168 `dst-port` and 168 `layer4-protocol` and 121 `hostname` on
`network-connection`, 41 `dst-port` and 41 `first-seen` on `ip-port`. They
collapse to 494 triples, of which 32 are listed and account for 459 of the 926.
57 further siblings are withheld by distribution and are named, not counted in.

§9.5's two shapes are both in that one table, and neither is contrived:
`layer4-protocol tcp` is 168 objects carrying one fact and collapses to a single
row that says 168; the flux domains are 247 distinct facts and rank and page.

**Where fidelity was capped rather than authored**, per §9.7's last paragraph:
the siblings section lists 32 of 494 and says so; the analyst thread is four
items, because an argument at 812 occurrences is the same argument as at ten.
Everything else — occurrences, sightings, publications, the per-organisation
verdict rows — is generated in full, because a sample would have made a count
somewhere on the page false. That distinction is the rule this phase actually
followed: cap a *list*, never a *count*.

### 10.4 Verification

Ran against the live stack, both themes.

1. `php -l` over the changed PHP and `.ctp`, `node --check` over the JS. Clean,
   and no added line over 80 columns.
2. **33 assertions over the four rendered Relationships fragments.** The three
   existing values render 3, 3 and 1 sibling rows — unchanged in content and
   order, event links intact, no cap notice, no ACL note, no `≥`. The fourth
   renders 32 ranked rows summing to 459, states `≥ 494`, pages `1–8 of 32
   (494 in total)`, and carries both notices.
3. **37 assertions in headless Chrome** over five harness pages, light and dark.
   §6.1's caveat is honoured: the first assertion is that `--vp-mal` resolves
   and nothing else is checked if it does not. The two pagers are independent —
   on the malicious value the outer states `1–8 of 18` while the siblings state
   `1–3 of 3`, and clicking the siblings' page 2 on the conflicted value moves
   the siblings to rows 9–16 and leaves the outer range and rows untouched.
4. **Every panel of every value returns 200**, including the sparse page. The
   fourth value's nine tabs all render.
5. `Object siblings only` still tags the same two rows on the malicious and
   conflicted values and still narrows the table below.

Three things the fourth value turned up, in the order they were found.

**A `graph` block with an integer `nodes`.** `value_relation_graph.ctp` reads
`$graph['nodes']` as three bands, and a count where an array belongs is a 500,
not a degraded panel. Written up only because it is the shape a live
implementation will reach for first: the count is derived, the bands are the
data.

**§9.8 is confirmed, and measured.** History renders one collapsible section per
occurrence handed to it, so at 748 occurrences it renders 748 sections — a
2.4 MB fragment, from **three** audit entries. Measured with a throwaway probe
that gave the value three entries, fetched the panel, and reverted; the shipped
fixture leaves it in the *recorded, nothing logged* state the benign value is
also in, because shipping a tab that cannot be scrolled is not a way of
reporting that it cannot be scrolled. `hidden` is `total_occurrences` minus the
occurrences the panel was given, so the panel gets all 748 rather than a sample:
a sample would have made its ACL footer state a number that is not the ACL's.

**The defect is wider than History, and in a different way.** Every paged panel
here pages client-side over rows the fragment already carries — `value_pager`'s
docblock says so plainly, "no second request, no Paginator". At 748 occurrences
that means the whole set ships in the markup:

| Panel | Bytes |
|---|---|
| `viewOccurrenceTable` | 4.00 MB |
| `viewTimeline` | 3.46 MB |
| `viewOccurrences` | 3.21 MB |
| `viewSightingList` | 0.54 MB |
| everything else | under 90 KB |

This is not History's defect. History renders a *section* per occurrence;
these render every *row* of every page up front and then hide all but eight.
Both are consequences of the same design decision — that the fragment is the
data source — and that decision is exactly the one a live implementation
replaces. It is recorded here rather than fixed for the same reason §9.8 gave.

A note on why the facet rail and the row list agree at this scale, since they
easily might not have. The rail's own copy promises *"counts cover the N
occurrences you can see"*, and they do — because the fragment carries all 748 of
them. The moment the Occurrences tab starts fetching pages, that promise and
`data-vp-facet-rows` become two different numbers in one panel.

### 10.5 Exit criterion

Met. `45.155.205.233`'s Relationships tab, with its correlation section
suppressed, is bounded: a siblings section that fits on the screen, states how
many objects each row stands for, states its own total, and says both when it
has been capped and when distribution has narrowed it. The three existing
values render as they did.

### 10.6 Deferred

- **History's per-occurrence grouping at scale**, still §9.12's item and now
  a measurement rather than a prediction: 748 sections, 2.4 MB.
- **Client-side paging over the whole set**, per §10.4. The Occurrences,
  Timeline and Sightings panels each ship every row they will ever page
  through. Whichever phase makes the page fetch pages settles all three at once.
- **A datetime relation does not collapse.** `first-seen` produces one triple
  per object — 137 of the 494 here — so the tail of the ranking is single-object
  timestamps carrying nothing reusable. The aggregation is right; the question
  is whether a per-occurrence timestamp is a sibling worth ranking at all, and
  that is a design call this phase had no mandate to make.
- **A sibling row's own object ids**, and **cross-value sibling ranking**, both
  unchanged from §9.12.

