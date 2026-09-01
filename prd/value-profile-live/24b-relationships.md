# PRD: Value Profile — Relationships subphase B: from listing to insight

Phase 24 put the tab's six sections on live data and kept every one of
them honest. A sanity-check pass on 2026-09-01 — the tab rendered live
for `8.8.8.8` (every section populated) and `github.com` (dated-heavy,
human sections empty), the fragments' markup grepped, and the tab read
against what OpenCTI, VirusTotal, Recorded Future, ThreatConnect and
Silent Push put in front of an analyst — found that the *structure* is
right and nothing is filler, but three panels stop one step short of
the insight they are sitting on, and the tab never says what the
neighbourhood **means**. Subphase B is that list, ordered for
implementation one task at a time.

Two rules inherited unchanged from `00-contract.md` §14 and the phase:
**nothing writes**, and every cap or fold a task introduces states
itself in the panel. One rule new to this subphase, from the sizing
question the findings raised: **the tab does not grow.** A new element
lands as a rank or fold change to an existing panel, as a rail card, or
as a strip card; a new left-column panel must shrink or displace
something first. The tab measured ~5,700px tall on `8.8.8.8` against a
rail of ~1,700px — the rail is where the headroom is. Splitting the tab
in two was considered and rejected: the spec's own reason for refusing
a segmented control (nobody ever opens the asserted segment) applies
with more force to a second tab, and the sections a split would exile
are the smallest and most human ones. The contents strip is the
long-page answer, and it already exists.

## 1. State

Update this table in the same pass as the code, not in a catch-up
sweep. A task is `done` only when its change is verified against the
live instance and recorded in its own section below.

| # | Task | Surface | Size | Grilling first? | New UI element | Status |
|---|---|---|---|---|---|---|
| B1 | The references panel's object link | `value_relation_references` | XS | no | no | todo |
| B2 | Absent engines say so in one line | `value_relation_near_match` | S | no | no | todo |
| B3 | Outside this instance: counts first, absence framed as novelty | `value_relation_external` | S | no | no | todo |
| B4 | Sibling table: linking fields before describing ones | `value_relation_cooccurrence` | M | no | no | todo |
| B5 | Warninglist de-emphasis in co-occurrence | `value_relation_cooccurrence` | M | no | no | todo |
| B6 | A "Most specific" rank | `value_relation_cooccurrence` | M | **yes** | no | todo |
| B7 | Dated strip: per-value lanes when rows are few | `value_span_strip` caller | S | no | no | todo |
| B8 | Named threats in this neighbourhood | new rail card | L | **yes** | **yes — frontend-design** | todo |
| B9 | Where in the intrusion: tactic mix | B8's card | S | no | no — extends B8 | todo |
| B10 | A typosquat engine for near-matches | `value_relation_near_match` | L | **yes** | no | todo |

## 2. The order, and why

Two one-line-class fixes open the subphase because they are guaranteed
wins that touch the templates the later tasks build on. Then the two
shrinkers (B2, B3), so the page's vertical budget is banked before
anything spends it. Then the two evidence re-orderings (B4, B5) before
the new rank (B6): dimming the noise changes what a specificity rank
has to beat, and B6's grilling should happen with B4/B5 already
visible. B7 is independent and small; it sits where it does only to
keep the co-occurrence run contiguous. B8 lands before B9 because B9
is a second group on B8's card and shares its fetch. B10 goes last
because it is the only task that adds a *claim class* MISP's own
correlation engine never makes, and that decision deserves the most
context.

Grilling marks three tasks. B6 changes ranking semantics and has a real
cost question; B8 decides what counts as a "named threat" and where the
card sits; B10 re-opens the hazard the ssdeep pass documented (a panel
reporting matches the engine denies). None of the others need input:
they are evidence-based, reversible, and follow patterns the tab
already owns.

### 2.1 Reviewed against the whole page — 2026-09-01

Before implementation, each task was re-checked against the other
eight tabs — their specs, their templates, and the controller's actual
data paths — for overlaps, for elements that belong on another tab,
and for the split/merge question one level up. Three things came out
of it.

**No split, and no merge.** At page level the question gets sharper,
not different. The Analyst tab holds the value's own standing — notes
and opinions — while this tab's asserted section holds *edges*;
merging them would blend the two things the analyst-data model keeps
distinct. The Timeline tab is already scheduled to carry dated
relations as a source lane (`06-timeline.md` §16) — a second home over
the same query, not a move. And the page's working idiom for
Overview-versus-tab is settled and load-bearing: **the card counts,
the section lists, both read one method so they cannot disagree.**
B3 and B8 now name that idiom explicitly instead of accidentally
conforming to it.

**One stale record, found and fixed.** The Overview's external
presence card (`viewExternal` → `ValueProfile::forExternal`) has been
live since phase 24 built the fourth section — the controller and the
template's docblock both say so — but §14.12's board still carried
`—`. Corrected in `00-contract.md` and `value-profile-page.md` §1.4
with this review; the row's `Q` was never measured and its cells say
so.

**Task by task.**

| Task | Outcome |
|---|---|
| B1, B2, B4 | unchanged — nothing on another tab touches them |
| B3 | revised — must agree with the live Overview card it details |
| B5 | note added — the banner's warninglist chip is fixture-built; B5 is the page's first live warninglist read |
| B6 | hint added — the rank lives in the model layer, because the verdict engine is its second customer |
| B7 | note added — the Timeline's scheduled lane reads the same query and is unaffected |
| B8 | revised — the boundary with the Overview's "Tags and galaxies" card is now a grilling item, and the fold lives in the model layer |
| B9 | inherits B8's boundary; otherwise unchanged |
| B10 | grilling gains the direction question — look-alikes *of* this value, or this value *as* a look-alike |

## 3. B1 — the references panel's object link

**Why.** `/objects/view/<id>` redirects to the unthemed `/events/view`,
which is why the near-match panel links `/events/view2` and carries a
docblock saying so (`value_relation_near_match.ctp:305`). The
references panel still links `/objects/view`
(`value_relation_references.ctp:212`) — recorded by the ssdeep pass
(`24-relationships.md` §28.9) as a one-line fix in a panel that pass
was not about, and verified still present on 2026-09-01.

**What changes.** The object chip's `href` becomes
`/events/view2/<event id>` — the event id is already printed two lines
below in the same cell. Chip label and icon unchanged.

**How.** Mirror the near-match panel's link, docblock included, so the
next reader of either template finds the same reason in both places.

## 4. B2 — absent engines say so in one line

**Why.** On `8.8.8.8` the near-match section spends ~760px to show
four CIDR rows, because the two engines that do not run
(ssdeep — wrong type; domain/TLD tree — does not exist in MISP) each
get a full block with heading and prose. The section's information is
the engine that ran; the ones that did not are one fact each.

**What changes.** An engine that does not apply to this value's type,
or does not exist, collapses to a single line in the section's hint
style: *"ssdeep fuzzy similarity — compares hash attributes; does not
run on ip-dst."* The ACTIVE engine's block is unchanged. Honesty is
kept — every engine is still named, with the reason it is not running —
in a tenth of the height. The one-line absent-engine slot is also where
B10 will land, so this task should leave that line trivially
replaceable by a block.

**Verify.** Render `8.8.8.8` (CIDR active, two lines) and a hash value
from the ssdeep seed family (ssdeep active, two lines); measure the
section height before and after.

## 5. B3 — outside this instance: counts first, absence framed as novelty

**Why.** Two findings, one panel. The hit state renders a wall of UUID
pills — Training Main alone shows eleven of
`Event ff2d1e23-caba-…` — and a UUID is a click target, not
information; nothing on the row says *how much* presence a source
holds. The miss state ("No feed or sync server you can see holds this
value") buries the lede: absence **is** the signal. A value in no
cached feed is locally novel, and novelty is one of the strongest
triage facts this page can state — it is the product GreyNoise built.

**What changes.**
- Hit state: one line per source — name, kind chip, **hit count**, and
  the pills folded behind an expander (the tab's existing collapse
  pattern; `<details>` is acceptable). Opening the expander shows the
  pills exactly as today.
- Miss state: *"In **0** of 5 cached feeds and 1 sync server this
  instance holds — locally unique, as far as this instance can see."*
  The source counts come from the same context the rail's settings
  card already prints, so the two cannot disagree.

**The Overview's card is live and already owns the headline.**
`value_external` counts what this section lists — one `forExternal`
behind both, by design (`03-relationships.md` §20.1) — and its miss
state already reads *"Not seen outside this instance"*. B3's novelty
sentence is that card's sentence with the denominator attached; the
two surfaces must keep saying the same thing, and if the wording
changes it changes in both.

**How.** `Feed::searchCaches` returns event UUIDs and no dates — show
counts, do not invent ages. The section's existing cache-age sentence
("read just now", the five-minute hold) already covers freshness and
stays where it is.

## 6. B4 — sibling table: linking fields before describing ones

**Why.** On `8.8.8.8` the entire first page of "In the same object" is
`paloalto-threat-event` bookkeeping: `type = THREAT`,
`threatid = UDP Flood`, `srcloc = United States`, `direction`,
`subtype`, `app = not-applicable`. Descriptions of telemetry, not
pivots. The tab already knows the difference — `disable_correlation`
picks the dated table's far values and the graph's edge labels
(`03-relationships.md` §24.1) — but the sibling table ranks all fields
equally, so the panel's first screen is its worst one.

**What changes.** The default order puts linking siblings
(`disable_correlation = 0`) first, then object count as today. A
**Field kind** facet (linking / descriptive, with counts) joins the
existing NARROW BY bar so descriptive rows are one click to cut.
Nothing is hidden by default — the honesty rule holds — the noise just
stops being page one.

**How.** The fold aggregates to (template, relation, value); carry a
per-group linking bit from the rows' `disable_correlation`. The flag
is per-attribute and the data is dirty at the edges — `domain-ip`
carries 0 on 41 `first-seen` rows and 1 on 22 (§24.1) — so majority
wins per group, and the panel caption states the rule the same way the
dated panel states its pair rule. The facet is `value_facet_group`,
which costs no new UI.

**Verify.** `8.8.8.8` page one should open on `0.0.0.0` (ip-dst) and
the other linking fields; the descriptive count in the facet should
equal the rows it cuts.

## 7. B5 — warninglist de-emphasis in co-occurrence

**Why.** The top-ranked co-occurrents of `8.8.8.8` are `1.1.1.1`,
`google.com`, `2.2.2.2`, `circl.lu`, `9.9.9.9` — resolvers and CDNs,
most of them warninglist-listed — and the fragment contains zero
warninglist markup (grepped 2026-09-01). An analyst triaging reads the
top of that table as signal; today it is exactly the noise the
wish-list (`24-relationships.md` §22.3 item 20) said to de-emphasise.

**What changes.** A listed neighbour's value cell renders dimmed with
a small badge; the badge's tooltip names the list(s). A facet or
toggle cuts listed rows. **Ranking is unchanged** — that is B6's job —
this task only makes benign-ness visible.

**How.** The Redis-backed check the event view already uses on the
Warninglist model. Check the carried page (≤100 rows), not the 10,040
fold — and say so: either the facet count is page-local and its label
admits it, or a fold-wide check is measured first and adopted only if
it is genuinely cheap. Whichever way it lands, the panel states which.
Dimming reuses the tab's existing de-emphasis style; the badge is the
existing tag-chip shape, not a new element.

**Adjacency.** The page banner's *Warninglist hit* chip is
fixture-built like the rest of the frame (`value-profile-page.md`
§1.4), so until the Overview's live phase runs, the frame's chip and
this panel's badges come from different regimes — the §14.10
frame-versus-panel hazard, one level up. B5 is the page's first live
warninglist read: build the lookup so the frame conversion can reuse
it rather than invent a second one.

**Verify.** `1.1.1.1`, `google.com`, `9.9.9.9` badged on `8.8.8.8`;
a value with no listed neighbours renders byte-identical to today.

## 8. B6 — a "Most specific" rank — **grilling session first**

**Why.** "Most shared" is a hub-finder: raw frequency ranks the
neighbour that co-occurs with *everything* first, which is the
opposite of what a hunting analyst wants. The pivot worth clicking is
the value that appears almost nowhere *except* beside this one.
Recorded Future ranks related entities by bare co-mention frequency
and has this exact weakness — this is a chance to be better, not just
at parity. It also un-parks half of phase 17's deferred **cross-value
sibling ranking**, which tied "which sibling matters most" to the
verdict engine; specificity is frequency arithmetic and needs no
verdict.

**What changes.** A third `RANK BY` pill — **Most specific** — on the
co-occurrence values table: rank key `shared events ÷ neighbour's
total events` (lift), tie-broken by shared events. The same rank
offered on the sibling table, where the denominator is the sibling
value's instance-wide prevalence. The verdict-weighted version of
sibling ranking stays deferred to the engine; this task takes only
the evidence-based part.

**Grilling decides.**
- The formula: pure lift promotes one-event flukes (1 ÷ 1 = 1.0), so a
  floor on shared events, or `shared² ÷ total`, or lift-above-a-prior —
  pick one and defend it.
- The denominator's cost: a grouped COUNT over the whole 10,040-value
  fold, or re-rank only the carried top-N with denominators fetched per
  page. The fold owns ranking today, so re-ranking a page is a change
  of contract the panel must state if taken.
- Whether `over_correlating_values.occurrence` may serve as a cheap
  hub denominator, given follow-up item 6 already flags that column as
  unmaintained on the read path.

**How.** The denominator is one grouped aggregate over candidate
values — `occurrenceSummaryFor`'s shape, plural. Nothing else new: the
pill, the sort, and the fold's rank hook all exist. The computation
belongs in the model layer, beside `occurrenceSummaryFor`, not in the
panel: the verdict engine's scope note wants exactly this kind of
signal, and a view-side rank would have to be rebuilt for it.

## 9. B7 — dated strip: per-value lanes when rows are few

**Why.** Lanes are per template, so `8.8.8.8`'s three resolutions
collapse into two lanes and the succession story — which value held
when, which replaced which — is only recoverable from the table. That
story is the reading a resolution history exists for, and it was the
founding example (`draculax.myq-see.com.`). Template lanes were chosen
against `github.com`'s 46-row single-template case; a threshold gives
both.

**What changes.** Rows ≤ 8: one lane per related value, label the
value (truncated, monospace). Rows > 8: template lanes exactly as
today. The caption states which grouping the strip is using.

**How.** `value_span_strip.ctp` already takes lanes the caller names —
the panel switches its grouping key on the row count and nothing in
the strip changes. Legend, moment marks, and the overlap-window filter
are untouched. The Timeline tab's scheduled dated-relations lane
(`06-timeline.md` §16) reads the same query but groups per source;
this grouping switch is panel-local and leaves it unaffected.

**Verify.** `8.8.8.8` draws three named lanes whose spans read the
hand-off; `github.com` is pixel-identical to today.

## 10. B8 — Named threats in this neighbourhood — **grilling session first, frontend-design for the card**

**Why.** Nothing on the tab answers *"what campaign, actor or malware
does this value sit next to?"* — and that is what every peer platform
leads with: Recorded Future's card opens with Related Malware and
Related Threat Actors, OpenCTI's knowledge tab has thematic threat
views, ThreatConnect groups associations by threat type. The wish-list
(§22.3 item 16) calls it "the single highest-value sentence this page
could produce". The raw material is already read and already visible —
`LummaC2` sits as a tag chip on one of `8.8.8.8`'s co-occurrence rows —
but per-row, where nobody folds it. The data is local: the
co-occurrence fold reads the value's ≤20 events; their tags and galaxy
clusters are one decoration query away.

**What changes.** A rail card under **Neighbourhood**: the galaxy
clusters and name-like tags reachable through this value's events,
ranked by independent organisations, then events:

> **APT28** — galaxy · via 3 events / 2 orgs
> **LummaC2** — tag · via 1 event / 1 org

Top 5–8 rows and an "and N more" line. Each row links to the cluster
or a tag search. The card states its read the way every panel does:
*"Read from this value's 19 events."* Placement is the rail because
that is where the headroom is (a full screen of dead space under
"What is counted") and because "what does this mean" is a summary, not
a ledger.

**The boundary with the Overview.** The Overview's context card
(`value_context`, fixture today) is *Tags and galaxies* — what this
value's **own occurrences** carry, grouped by taxonomy. This card is
the other half: what reaches the value **through its events**. On a
heavily-tagged value the two would largely repeat each other unless
the card marks or excludes clusters the value already carries
directly — and the association signal is interesting precisely where
it is *not* direct. When the Overview's live phase runs it inherits
this boundary: its card stays direct, at most gaining a one-line count
of named threats nearby, in the card-counts/section-lists idiom the
external pair already uses.

**Grilling decides.**
- Which cluster families count as a *named threat*: threat-actor,
  malware, tool, ransomware, botnet — with `mitre-attack-pattern`
  explicitly excluded (that is B9's group).
- Whether attribute-level tags on the neighbours join event-level
  tags, or events only in the first cut.
- Whether the asserted section's far ends contribute — a claim naming
  a GalaxyCluster is already on the tab and is arguably the strongest
  named-threat evidence the page holds.
- Local-only and freetext tags: in, out, or folded under "unnamed".
- The `value_context` boundary: exclude clusters the value's own
  occurrences already carry, or keep them marked `direct` beside the
  `by association` rows — and which of the two the ranking favours.
- Placement confirmation (rail card vs eighth strip card), and the
  card's notion colour — it spans notions, so it may need its own.

**How.** Event ids come from the cached tab context (§18), so the card
costs one `EventTag`+`Tag` fetch over ≤20 events plus cluster
resolution — measure it, and hold it in the same five-minute digest
the rail cards already share. Ranking is `COUNT(DISTINCT org)` then
`COUNT(DISTINCT event)` per cluster/tag, folded in PHP at these sizes.
The fold is a model-layer method (`ValueProfile` or `ValueStatsTool`),
not view logic: the verdict engine's scope note lists neighbourhood
context among its wanted signals, and the Overview's future count line
is a second caller.

**UI.** This is a new card element — run the **frontend-design** skill
for it before building: rows-with-chips in the rail's card idiom, a
count treatment that does not read as a score, light/dark checked. The
element should be reusable by B9 as a second group.

**Verify.** `8.8.8.8` shows LummaC2 (tag, via its one tagged event) —
the row visible in the 2026-09-01 render is the witness; a value whose
events carry no clusters or name-tags states that rather than
rendering empty.

## 11. B9 — where in the intrusion: tactic mix

**Why.** The category/technique mix over the neighbourhood names the
value's role — ringed by delivery, C2, or exfiltration — which is the
wish-list's item 18 and ThreatConnect's technique roll-up. It answers
a different question from B8 (what stage, not who), which is why it is
a separate group and not more rows in the same list.

**What changes.** A second group on B8's card:
`mitre-attack-pattern` clusters over the same events, collapsed to
**tactic**, ordered by kill-chain order, one count per tactic. A chip
row or a single compact bar — whichever B8's design pass already
established; no separate design pass, no new element.

**How.** Zero additional queries: B8's tag fetch already returned
these clusters; the tactic comes from the galaxy's kill-chain meta.
Values with no attack-pattern clusters simply do not render the group.

## 12. B10 — a typosquat engine for near-matches — **grilling session first**

**Why.** The absent third engine is currently sketched as a domain/TLD
tree — taxonomy, not detection. A permutation engine is a detection:
*"3 registered look-alikes of this domain exist in this instance"* is
an actionable sentence about brand impersonation. Silent Push and
haveibeensquatted sell exactly this pivot; dnstwist is the reference
implementation. A local-only version needs no external calls and fits
the section's contract — computed here, never equality, engine-named.

**What changes.** For `domain` / `hostname` values (and the host part
of `url` — scope below): generate a bounded permutation set
(character omission, transposition, homoglyph, bitsquat, hyphenation,
TLD swap), check it against the local attribute index in one
`value IN (...)` query, and render hits in the section's existing row
shape: look-alike value, permutation class as the closeness column,
where it sits, reported by, distribution. The engine block replaces
the one-line absent slot B2 leaves.

**Grilling decides — and the hazard goes first.**
- **This engine reports matches MISP's correlation engine never
  claims.** That is the exact failure class the ssdeep pass documented
  (§6, §28.9: a panel that compares what the engine denies costs a
  debugging session). The counter-argument is the section's own
  framing — a near-match is never equality and every row names its
  engine. One of these wins on purpose, in writing, before any code.
- Permutation classes and the cap: long domains explode
  combinatorially; pick a generation bound, and the block states what
  was not generated (the no-silent-caps rule).
- Which value types run, and whether the URL host is in the first cut.
- **The direction, and it changes the audience.** Look-alikes *of this
  value* registered on the instance is campaign mapping; this value
  *as a look-alike* of a prominent domain — warninglists' top-domain
  lists are the obvious reference set — is the triage read (*"this is
  a google.com typosquat"*). The same generator run opposite ways; the
  first cut may take one, the grilling picks which.
- Whether generated look-alikes are also checked against the feed
  cache (the external section's set-membership primitive) — *"2
  look-alikes sit in Threatfox"* — or whether that is a later pass.
- Where a hit links: its record, its event, and — the promote-list
  question again — its own value page.

**How.** Generation is pure PHP over the label; the check is set
membership, the same shape as the feed-cache read. `Attribute.value1`
is indexed; one IN query over a few hundred candidates is the whole
cost. The similarity threshold control the section header already has
should not pretend to apply to this engine — permutation class is not
a percentage, and the column should say the class, not a number.

## 13. Not in this subphase

Named so the list above stays a list of things this subphase will
actually do.

- **A claim's prose** — child Notes on relationships. Phase 24
  follow-up item 3; blocked on the per-claim bound decision, and the
  hover card is where the absence shows.
- **The promote list** — its own brief, deliberately (ranking is a
  different decision from meaning). B6 takes the frequency half of
  sibling ranking; the curated half stays here.
- **The `light` overlay and live expand-one-hop** — waiting on
  pivotick's `editors.notes.enabled` flag and on the re-founded
  graph's next pass, respectively.
- **A `Relationship` counting method** for the tab badge — model work,
  follow-up item 5.
- **A matched hash opening its own value page** — §28.9; the
  promote-list question.
- **The Timeline source lane** — the Timeline tab's phase;
  `url-honeypot-detection` joined `passive-dns` as a candidate.
- **WHOIS history, JARM/favicon/cert pivots, contacted
  infrastructure** — every one needs external acquisition, which is
  the Enrichment tab's job. Importing them here would be the moment
  "What is counted" stops being cheap to keep true. Rejected, not
  deferred.
