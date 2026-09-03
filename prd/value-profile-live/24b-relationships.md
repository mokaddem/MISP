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
| B1 | The references panel's object link | `value_relation_references` | XS | no | no | **done** |
| B2 | Absent engines say so in one line | `value_relation_near_match` | S | no | no | **done** |
| B3 | Outside this instance: counts first, absence framed as novelty | `value_relation_external` | S | no | no | **done** |
| B4 | Sibling table: linking fields before describing ones | `value_relation_cooccurrence` | M | no | no | **done** |
| B5 | Warninglist de-emphasis in co-occurrence | `value_relation_cooccurrence`, `value_relation_dated` | M | no | no | **done** |
| B6 | A "Most specific" rank | `value_relation_cooccurrence`, `siblings` | M | **yes** | no | **done** |
| B7 | Dated strip: per-value lanes when rows are few | `value_span_strip` caller | S | no | no | **done** |
| B8 | Named threats in this neighbourhood | new rail card | L | **yes** | **yes — frontend-design** | **done** |
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

### 3.1 Done — 2026-09-01

The chip's `href` is now `/events/view2/<event id>` plus the tab
anchor its two sibling panels already use, and the reason for that
destination sits in the cell as a docblock the way near-match's does.
Chip label and icon are untouched. Two things came out of doing it
that the task did not anticipate.

**It removed a broken link, not merely an unthemed one.** The cell
decided it had an object worth linking by testing `$far['object'] !==
null`, then built the URL out of `$far['id']` — and those two fields
do not always describe the same record. When the far end is an
*attribute sitting inside an object*, `object` holds the **parent
object's** name while `id` is the **attribute's** id, so the row
rendered `/objects/view/<attribute id>`: an object URL pointing into
the attribute id space. Event 15 has exactly one such row — object 28
(`file`, "Kobalos for RHEL") carries a `Child` reference to attribute
126, a sha1 inside object 30 — and its old link was
`/objects/view/126`. Object 126 does not exist. That link was a hard
404, not a trip to the unthemed page. Taking `$far['id']` out of the
href ends the class rather than the instance: the href now comes from
`$far['event']`, which names the same record whichever field the cell
tested.

**The tab anchor follows `kind`, and the title had to move with it.**
The two sibling panels pick `#tab-objects` or `#tab-attributes` by
what the record *is*, so this one does too — and `kind` is the field
that knows, not the chip. A far end that is an attribute inside an
object keeps showing the object's name, because that is what the
reader will recognise, but the reference points at the attribute and
that is the tab it opens. The old `title` said "Open this file
object", which the new destination cannot honour: it opens an event
tab and this theme's event view takes no `focus:`. So the title now
carries the record's own id — "Attribute 126 in event 15", "file
object 28 in event 15" — which is the near-match panel's answer to
the same problem, in the same words. This is a change beyond "chip
label and icon unchanged"; the label and icon are indeed unchanged,
but a title promising to open the object had to go with the link that
no longer does.

**Left alone.** A far end that is a *bare* attribute — one in no
object at all — still renders as an unlinked chip, as it did before.
It is the one case where the cell has no object name to print, and the
event id on the line below is already a link, so the reader is not
stranded. Giving that chip a link is a change to what the panel offers
rather than a correction to where an existing link goes, which is not
what B1 is.

**Verified** by fetching the panel live for both ends of that
reference. `fbf0a76c…` (the sha1 in object 28) renders the outbound
row: cube chip "file", `/events/view2/15#tab-attributes`, title
"Attribute 126 in event 15". `479f470e…` renders the inbound
direction plus four more object far ends, every one
`…#tab-objects` with the object's id in the title. No `/objects/view`
string survives in either render.

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

### 4.1 Done — 2026-09-01

`$engineLine($state, $name, $reason)` renders a non-running engine as
one row: the state column the active block already uses, the engine's
name, an em dash, and the reason. The two collapsed states now read

> **NOT APPLICABLE** · ssdeep fuzzy similarity — compares `ssdeep`
> attributes; does not run on `ip-dst`.
>
> **NO ENGINE** · Domain / TLD tree — nothing in MISP computes a
> parent-domain, registrable-domain or public-suffix relation.

and the CSS is one modifier, `.vp-rel-engine-line`, that swaps a
section's padding for a list item's and puts the name on the state's
baseline. Nothing else about the row changes, so the three engines
still align down the state column and still read as one list.

**Measured on the live instance**, section height, viewport 1400px:

| Value | Engine that ran | Before | After | Saved |
|---|---|---|---|---|
| `8.8.8.8` | CIDR, 4 rows | 758px | 498px | 260px (34%) |
| `3072:u4PrXcuQ…` | ssdeep, 0 rows | 523px | 287px | 236px (45%) |
| `github.com` | none | 525px | 233px | 292px (56%) |

Per engine: a *not applicable* block was 73–96px and is now 40px; the
*no engine* stub was 185px inside a 32px wrapper — 217px — and is now
38px. The PRD's estimate of ~760px for `8.8.8.8` was exactly right.

**Three states collapse, not two.** The task named *does not apply*
and *does not exist*. The live panel has a fourth state — *cannot run*,
where MISP ships the engine and it applies to this value but the PHP
extension behind it is not loaded — and that one **keeps its block**.
It is the only one of the four that reports on the instance rather than
on the value, and the only one anybody can act on; shrinking it would
be shrinking the one non-active state with something to say. The
template's header docblock now lists four states and says which two get
a block and why.

**What the collapse dropped.** The *not applicable* paragraph for
ssdeep also taught what a score looks like, with a sample
`ssdeep 92%` badge — on the one screen where the engine never produces
one. The active branch says the same thing where the reader can watch
it happen, so the lesson moved rather than went. The TLD stub's longer
note was two things at once: the fact (no public-suffix list, no table,
no code path) and an argument to the next reader of the template about
why a gap in the brief is drawn rather than dropped. The fact is on the
line; the argument is now a docblock, which is where it was always
addressed.

**Considered and not done: putting the active engine first.** With
ssdeep active, CIDR's line sits *above* the block that ran, because the
engines render in a fixed order. Reordering by state would read better
on that one value and would cost the reader the stable position of each
engine, which is worth more once B10 adds a fourth. Left as it is;
B10's grilling is the place to reopen it.

**Verified** by driving the real page — logged in, Relationships tab
clicked, lazy panel awaited — and reading `getBoundingClientRect()` off
the panel and every engine row, in light and dark. The *cannot run*
block was rendered by forcing the state in the model for one fetch and
reverting it, since this instance has the ssdeep extension loaded.

### 4.2 Found while measuring: ssdeep compares 100 candidates, not all

Not a B2 change and not fixed here, recorded because the measurement
walked into it.

`ValueProfile::ssdeepEngine` fetches candidates with
`occurrencesOfType(..., RELATION_ROW_CAP)` — 100, ordered by
`Attribute.timestamp DESC` — and then compares the value against those
100. The cap is a *row* cap everywhere else on the tab, where it bounds
what is shown; here it bounds what is **compared**, before any
comparison happens, so it changes the verdict rather than the listing.

The verification instance has 1,399 `ssdeep` attributes. Comparing one
seeded family member against all of them with
`ssdeep_fuzzy_compare()` directly finds **45 partners over the
threshold of 40, the closest at 96**. The panel, for that same value,
says *"Compared against every other `ssdeep` attribute you can see, **0
pairs** cleared the threshold of 40."* Both halves of that sentence are
wrong: it compared 100 of them, and it found none only because the
family was not in the 100 most recent.

This is the subphase's own inherited rule — every cap states itself in
the panel — failing in the one place where the cap is load-bearing, and
it is why the ssdeep seed family has never shown a row. Needs a task:
either raise the candidate set to the type's full population with a
stated bound, or keep a bound and say what it was in the sentence.

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

### 5.1 Done — 2026-09-01

Both halves landed as specified, and a third thing came out of the pass
that was larger than either.

**The hit state.** One row per source, and the row's headline is its
count: the number, in the section's own colour, and the unit beside it.
The pills sit behind that count in a native `<details>`, and opening it
shows them exactly as before — the task asked for no change to them and
they got none. `<details>` rather than the ledger's Bootstrap collapse
because this fragment is injected lazily and binds no JS of its own; a
fold that needs `misp:container-loaded` to have fired is a fold that can
arrive inert. The header now leads with sources — *"2 feeds and 1 sync
server · 6 remote events"* — and drops the events clause when a source
holds the value but names no event.

**The miss state has two forms, not one, and the second is the point.**
The novelty sentence renders as specified for a reader whose role
searched something: *"In 0 of 5 cached feeds and 1 sync server this
instance holds. Locally unique, as far as this instance can see."* But
the denominator is the sources **this reader's role searched**, never
the instance's total — "0 of 5" when four of the five were never looked
in is arithmetic that reads as coverage the reader did not get — so a
restricted reader gets *"In 0 of 5 cached feeds your role can read"* and
the claim narrows with it. And a reader whose role reaches **no** cached
source at all gets no novelty claim of any kind, because none was
measured: *"This instance caches 5 feeds and 1 sync server, and your
role may be told about none of them, so nothing was looked up. This is
not a statement that the value is absent from them."* The same sentence
would otherwise have been false for that reader on every value they ever
opened, which is the exact shape of claim §14.6 exists to refuse. The
Overview card takes the same branch — subtitle *"Nothing your role can
search"* — so the pair still cannot disagree.

Neither new branch is keyed on the value: both read `visible` against
`cached`, which are properties of the role and the instance config.

**The ACL rule was wrong, and it was disclosing feeds.** Checked because
the task's own framing — *counts first* — meant deciding what the
denominators may say to whom. `externalVisibility()` admitted a
`lookup_visible = 0` feed to any holder of
`perm_view_feed_correlations`. All three surfaces MISP ships withhold
such a feed's identity from everyone but a site admin: the event view
conditions on `lookup_visible = 1` for `!perm_site_admin`
(`Feed::getCachedFeedsOrServers`), and `/feeds/index` and
`/feeds/searchCaches` add a host-org branch that cannot fire, because
they compare a session `org_id` (string `'1'`) to `MISP.host_org_id`
(int `1`) with `!==`. `AppModel`'s migration sets the permission to 1
for every existing role and `lookup_visible` defaults to 0, so this was
the common case on an upgraded instance, not a corner.

Reproduced before the fix by flipping `CIRCL OSINT Feed` to
`lookup_visible = 0` and running one value through every reader, against
what each MISP surface would hand the same reader:

| Reader | Panel, before | `searchCaches` | `/feeds/index` |
|---|---|---|---|
| site admin | CIRCL, Botvrij, Training Main | same | all five |
| Org Admin, org CIRCL | Botvrij | Botvrij | four, no CIRCL |
| **same, `perm_view_feed_correlations` = 1** | **CIRCL + URL + 2 event links**, Botvrij | Botvrij | four, no CIRCL |

The panel also cleared its own withheld-sources band for that reader —
`restricted` went to false — so it claimed full coverage while showing a
feed the instance withholds. After the fix the third row is identical to
the second, and the band fires. `tabs/03-relationships.md` §20.2 and
§20.9 carry the corrected rule and the reasoning error behind it.

The host-org branch is deliberately not reproduced. Copying it would
mean copying a comparison that does not do what it reads as doing, and
fixing it belongs to those two surfaces rather than to a page that only
reads them. The effect here is that the page is stricter than a host-org
non-admin could argue for — which is the safe direction, and the
withheld-sources band tells them so on every value alike.

**One bug the markup could not show.** The pills were wrapped in
Bootstrap's `.d-flex`, which is declared `!important` and therefore
beats a closed `<details>`: the fold rendered, the chevron turned, and
the pills never hid. Caught by asserting the pill's height in a browser
rather than by reading the fragment. The fold's closed state is now
stated in `value-profile.css` rather than left to the UA stylesheet, so
a future utility class cannot quietly undo it again.

**Verified.** Six states rendered through the real view class and theme
— hit as site admin, hit restricted, novel as site admin, novel
restricted, nothing-visible, nothing-cached — the last two by flipping
`lookup_visible` and then `caching_enabled` on the five cached feeds and
the one cached server, and reverting both. Both themes screenshotted,
the fold driven in Chromium (closed → pills hidden; summary clicked →
pills shown), no console errors, no horizontal overflow. Then the two
endpoints fetched over authenticated HTTPS — `/values/viewRelationExternal`
and `/values/viewExternal` — which the console render does not exercise:
200 on both, markup matching. The probe and render shells are
`24b-external-render.php` beside this document; the reader-versus-surface
diff in the table above is the check §20.2 had asserted in prose and
never run.

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

### 6.1 Done — 2026-09-01

The order, the facet and the dimming landed as specified. Two things
were decided differently from the sketch above, both because the live
data said so.

**The vote is per field, not per row.** The sketch put the majority
inside each `(template, relation, value)` triple. On `github.com` that
renders `url-honeypot-detection · last-seen` **both ways in one table**
— the field is flagged 0 on 376 attributes and 1 on 10,688, so a
timestamp whose own attribute carries 0 was dimmed one row above a
timestamp that was not, with nothing on screen to explain the
difference. The panel calls the control *Field kind* and that is what
it should mean: the field votes once, over the attributes this panel
read under it, and every row beneath it agrees. Verified as 0
both-ways fields on all three values checked. The vote stays local to
what the panel read, so the same field can be classed differently on
two different values — the caption says *the attributes this panel
read* for that reason, and the alternative is an instance-wide census
the panel never runs.

**The order makes a dead control out of the facet, and it had to be
fixed rather than noted.** On `0.0.0.0` the value has 795 sibling
triples and the table carries 100; with linking rows sorting first,
**all** 100 carried are linking, so *Descriptive 550* is a filter that
can only ever empty the table. The bar's counts are fold-wide and its
list has no narrowing endpoint behind it — unlike the ranked table
above, an unanswerable tick cannot be handed to the server. So:

- `siblingFacets` now takes the post-cap rows as well as the fold, and
  marks every entry in **all five** groups with `listed`, the number of
  carried rows it reaches. This is the mechanism `value_facet_group`
  already documented and no caller had ever supplied.
- `value_facet_group` gained one optional flag, `local`, for a panel
  with no server-side narrowing. Under it an entry with a count and
  `listed = 0` is greyed and disabled, tooltipped with both numbers,
  the same treatment the element already gives a vocabulary zero. The
  ranked bar does not pass it and is unchanged.
- The caption states the cut where it bites: *"550 descriptive
  siblings are counted below and not listed: the table carries 100
  rows and the linking ones fill them."* Rendered only when the number
  is above zero.

This also greys the four groups' pre-existing dead entries — on
`0.0.0.0`, `time_generated 500` and `srcloc 41` were controls that
emptied the table before B4 existed.

**Row tokens moved into the fold.** The sibling rows' `data-vp-facet`
tokens were built in the template while the bar counted its own; the
`listed` pass needs the two to be the same strings, and the template's
comment already claimed one place built them. `siblingRowTokens` is now
that place and the closure is gone.

**Not a new element.** The dimming is a modifier on the existing
`.vp-relation` chip — flat and untinted against the linking chip's
object tint, checked in both themes — and Field kind is a fifth
dropdown in a bar that already had four.

**Verified.** Three values, each on a forced fresh scan:

| Value | Siblings | Page one, before | Page one, after |
|---|---|---|---|
| `8.8.8.8` | 36, uncapped | `0.0.0.0` then 7 rows of `paloalto` bookkeeping | all 5 linking fields, then 3 descriptive |
| `github.com` | 83 | mixed hashes, urls and dates | 6 hashes |
| `0.0.0.0` | 795, cut to 100 | mixed | 100 linking `src` addresses |

On `8.8.8.8` the facet reads *Linking 5 / Descriptive 31* and both are
fully listed, so the descriptive count equals the rows it cuts exactly
— §6's verify criterion. Driven in Chromium against the real
`value-profile.js` with paging asserted live first (36 rows → 8 shown):
ticking Descriptive gives 31 rows over 4 pages, all dimmed; ticking
Linking gives 5 rows on one page, none dimmed; on `0.0.0.0` the
Descriptive box is disabled. Both themes screenshotted, no console
errors.

### 6.2 Found while verifying: a cached fold outlives its code

`forRelationCooccurrence` keeps its scan in Redis for
`RELATION_SCAN_TTL`, five minutes, and the key captured the viewer, the
value and the options — everything about *what may be seen*, which is
the risk `00-contract.md` §14.4 names, and nothing about *what shape
was stored*. So for one TTL after any change to the fold, the templates
are new and the arrays they read are old.

This was not theoretical. Adding `tokens` to the sibling rows produced
a real 500 on `0.0.0.0` mid-session — *Undefined array key "tokens"*,
then a `TypeError` out of `implode` — because the panel was rendering a
payload written minutes earlier by the previous fold. It self-heals in
five minutes, which is exactly long enough to look like an intermittent
bug and long enough to be an outage on a deploy.

Fixed at the key: `ValueProfile::CACHE_SHAPE` is now a component of all
three profile cache keys, to be bumped in the same commit as any change
to what they store. A deploy then retires the old payloads instead of
waiting out the clock, at the cost of one cold read. `00-contract.md`
§14.4 carries the rule as the second thing such a key must capture.

**And a measurement trap that follows from it.** A fragment fetched
over HTTP after a code change can be the *old* fold rendered by the new
template, so it can report a fix that has not applied — the first run
of this verification did exactly that, and read as a per-field vote
that had not taken. Append `?fresh=1` to every panel fetch; the panel's
own *Scan again* link is the same switch.

### 6.3 The rule got a name, and the caption stopped guessing

Two follow-ups from reading B4 back. Neither changes what the panel
decides; both change where the decision is written down.

**`ValueFieldKind`.** The linking/descriptive reading of
`disable_correlation` had four callers in three files and no name in
the code, spelled `empty()`, `!empty()` and `=> 0` — while
`03-relationships.md` §24.1 had been calling it *one rule* since phase
24. It is now one class with the four callers behind it:

| Caller | Decides | Was |
|---|---|---|
| `Value::referenceFacesFor` | which attributes may identify a far object | `'Attribute.disable_correlation' => 0` |
| `ValueRelationTool::siblings` | the graph's edge label | `empty(...)` |
| `ValueRelationTool::dated` | a dated row's far value | `!empty(...)` |
| `ValueRelationTool::siblings` | the sibling field vote | `empty(...) ? … : …` |

The SQL caller is why the class exposes `linkingConditions()` beside
`isLinking()`: one of the four applies the rule to rows it has not read
yet. `LINKING` and `DESCRIPTIVE` are constants because the same two
strings are the facet tokens, the fold's array keys and the row's
`kind` — and the row now carries that string rather than a boolean
three places had to re-spell.

**The question that prompted it also settles the template one.** The
declaration *is* already in misp-objects, per element
(`object_template_elements.disable_correlation`). Nothing needs adding
there, and the panel still must not read it: core copies the flag onto
the attribute at creation and never reconciles, so 15,721 of 559,277
attributes in template-backed objects disagree with their template
today; 11,064 objects on this instance belong to a template that is not
installed at all; and the attribute is the only one of the two the
correlation engine consults. Classifying from the template would
promise pivots MISP will not make. The class docblock carries this so
the next reader does not re-derive it.

**The caption names fields off its own table.** §6.1's caption said
*"a file's filename, a capture's timestamps"* — hand-written examples,
and `filename` was the wrong one twice over: MISP declines to correlate
on it, and the flag splits 708 to 2,576 across the instance's
filenames, so the sentence was right on one file object and wrong on
the next. The fold now returns two field names of each kind from the
rows the table carries, and the caption prints those:

| Value | Caption reads | Table opens with |
|---|---|---|
| `8.8.8.8` | *Here `dst` and `domain` link, `type` and `threatid` describe.* | `dst`, `domain`, then `type`, `threatid` |
| `github.com` | *Here `hash` and `url` link, `mime-type` and `first-seen` describe.* | three `hash` rows |
| a file sha256 | *Here `sha1` and `md5` link, `state` and `filename` describe.* | `sha1`, `md5`, `state`, `filename` |
| `0.0.0.0` | *Every field this table carries is a linking one.* | 100 linking `src` rows |

A sentence read off the rows cannot go stale against them. The panel's
first caption line keeps its hand-written examples — *a file's other
hashes, an IP's resolved domains* — because that one says what the
section contains, not how anything is classified, which is the same
safe form the dated panel's caption uses for its date-field names.

**Verified.** The four values above re-fetched **without** `?fresh=1`,
which is the second check in one: the row shape changed again
(`linking` → `kind`), `CACHE_SHAPE` went to 3, and every payload came
back in the new shape rather than fataling the way §6.2's did. Facet
counts, ordering, dimming and the greyed unreachable entries all
unchanged from §6.1. The Chromium drive re-run on `8.8.8.8` — paging
live, Descriptive cuts to 31 over 4 pages, Linking to 5 on one, no
console errors — and the file-hash panel screenshotted in dark theme,
where the caption's four named fields sit directly above the four rows
they name.

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

Extended after the fact to the two other tables on this tab that carry
a value a reader can pivot to — the object siblings and the dated
relations — and deliberately not to the human-authored sections. §7.2.

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

### 7.1 Done — 2026-09-02

**Fold-wide, and the measurement said so.** §7 made the choice a
precondition: check the carried page and admit the facet is page-local,
or measure a fold-wide check first and adopt it only if it is genuinely
cheap. Measured on the dev instance, 8 enabled lists of 97 installed,
one scenario per fresh process because the model's `entriesCache` and
the shared Redis value cache both warm on first use:

| Scope | n | Redis-backed batch | No cache at all |
|---|---|---|---|
| the carried page | 100 | 65.8 ms | 41.8 ms |
| the whole fold | 10,040 | 85.9 ms | 64.5 ms |

The fixed cost is what dominates — `getEnabled` plus building the entry
sets is ~30 ms of it, most of that the three CIDR lists — and the
marginal cost is **~2.3 µs a value**. Reading the whole neighbourhood
costs 23 ms more than reading the page, so the page-local compromise
was not taken and no label has to admit anything: the `Warninglist`
facet counts the fold, exactly like every other entry in that bar.

**One query, and it earns its place.** The check itself is Redis. The
one SQL it costs is `assignComments`, which
`attachWarninglistToAttributes` issues whenever anything matched —
measured in isolation over the same 10,187 probe rows, twice in one
process, `Q=1` both times. Rather than pay for it and throw the result
away, the row badge's tooltip prints the entry's note where the list
carries one. The endpoint's cold total is now 18 and its warm total 3;
§14.12's row is updated with both.

**Held with the rows it describes.** The verdicts are computed in
`readRelationScan` and travel inside the cached scan, so a narrowing —
which re-folds rows already in hand — does not re-check 10,040 values
to redraw eight. The staleness that buys is bounded by
`RELATION_SCAN_TTL` and is already disclosed: the panel prints the
scan's age, and *Scan again* re-reads. `CACHE_SHAPE` goes to 4.

**`ValueWarninglistTool`, the page's one warninglist read.** The frame's
*Warninglist hit* chip is fixture-built and so is the verdict card's
band, so §7's instruction was to build the lookup such that the
Overview's live phase converts onto it rather than inventing a second
regime. It is the model-injected shape §14.5 allows — no `$user`, and
none would mean anything, since which lists are enabled is instance
state identical for every viewer.

Two decisions are written into it. It is **core's own check**, not a
re-implementation: `attachWarninglistToAttributes` keys its cache on
`md5(type:value)` alone, so an event view that has already checked
`1.1.1.1` warms this lookup and this lookup warms the next event view.
And **`to_ids` is deliberately not consulted**. Core gates the check on
`to_ids || MISP.warning_for_all` because there it is asking *should this
attribute have been exported*; here the question is *is this value known
benign infrastructure*, which is a property of the value, and a
co-occurrence row folds many occurrences that need not agree on the
flag. A value is reported under **any** type it appeared as, which is
the rule the type facet already uses — a `sha1` seen once as `md5` would
otherwise escape the empty-file list it is on.

**A dropdown, and for one day a switch beside it.** The complement was
in the facet group first and broke it: `value_facet_group` scales its
bars to the largest entry, so *Not on any list* at 10,003 drew 100% and
every list beside it drew 0%. The first answer was to move the
complement out to a **Hide warninglisted** switch and leave the dropdown
to the names. **§7.3 replaced that**: the group now carries both halves
of the partition as entries the element knows not to put on the bars'
scale, and the switches are gone. The narrowing is the same either way —
one `f[warninglist][]` token, one rule in the fold.

Driven in Chromium with `reloadAjaxTabIndex` stubbed to record what the
panel sends; without the stub `narrowRemotely` gives up silently and the
panel filters the hundred rows it holds, which is not the behaviour
under test:

    No hit tick    …?f[warninglist][]=_clear
    RFC 5735 tick  …?f[warninglist][]=list-of-rfc-5735-cidr-blocks

The token is `_clear` and the underscore is load-bearing:
`ValueStatsTool::facetToken` maps everything outside `[a-z0-9]` to `-`,
so no list's slug can contain one however the list is named. A bare
`clear` would collide with a warninglist called *Clear*. The first
spelling, `-clear`, collided with nothing but read as an option flag
everywhere a token reaches a command line.

**Ranking is untouched**, and the caption says so in the same breath as
the dimming, because a reader who sees dimmed rows at the top of a
ranked table will otherwise assume the rank already accounts for them.
It does not — that is B6.

#### Verified

`8.8.8.8`, live: 37 of 10,040 listed, 18 of the carried 100, and the
three §7 named are among them — `1.1.1.1` and `9.9.9.9` badged and
dimmed at ranks 1 and 5. **`google.com` is not badged, and that is the
data and not a fault**: §7 predicted it before anything was measured,
and no *enabled* list on this instance names it — the Cisco Umbrella
top-1000 list is one of the 89 installed-but-disabled. The facet reads
*RFC 5735 CIDR blocks 21, public DNS resolvers 11, BT02 domains 2*, and
four singletons; ticking one fetches its rows from beyond the carried
hundred (21 rows for RFC 5735, all of them on it), and the switch
returns 100 unlisted rows re-ranked over the fold rather than the 82
leftovers a client-side cut would have left. `172.17.164.8|2404` matches
as a composite, which is `checkValue` splitting on the pipe.

**Byte-identical where there is nothing to say, and it was diffed
rather than argued.** §7 asked that a value with no listed neighbours
render exactly as it did before B5. A grep for absent markup is not that
claim, so the panel was rendered twice over one profile — once through
the current template, once through `git show HEAD:`'s, both from a
Console shell — and the bytes compared. `03634e2eab…`, three unlisted
neighbours: **37,112 bytes, identical.**

Getting there cost four fixes, all the same bug in four places. `?>`
swallows the newline that follows it, and an `if` block in the markup
leaves its own indentation behind on every row it skips. The first
attempt was 67 bytes long. The caption and the switch are therefore
built with `ob_start()` and echoed against the closing tag of what
precedes them — the idiom `$headerExtra` already uses — the cell
defaults its key inline rather than in a statement of its own, and the
eighth facet group is **appended** to `$facetGroups` rather than
declared in it, because the bar's render loop prints its indentation on
every pass including the ones that `continue`.

**Both themes**, driven in Chromium at 1320px with the fragment's own
JS live (100 rows in the DOM, 8 shown — the witness that pagination
actually ran). Light: the chip is `--warninglist-soft` on `--warninglist`
and the four dimmed values in the first eight read clearly lighter than
the four beside them. Dark: the chip takes its `color-mix` background
and body-colour text, `.vp-rel-listed` resolves to
`rgba(222,226,230,0.75)`. The mark is `.vp-warninglist-chip` plus a
`.vp-warninglist-mark` size modifier rather than a second class, so the
banner's chip and a row's mark cannot drift apart in colour — which is
the same reason there is one lookup.

Dimming lands on **the value cell only**. `1.1.1.1` being a public
resolver says nothing about the four organisations that reported it or
the date they last did, and dimming those cells would claim it did.

**The comment line was exercised, not assumed.** No warninglist entry on
this instance carries a comment — zero of them — so the tooltip's second
line was unreachable against live data. One entry was given a comment,
the fragment re-fetched, and the entry reverted to `NULL`: the tooltip
read *"Test domain (false_positive) — matched circl.lu / CIRCL own
domain"*.

That flip also turned up an upstream quirk worth recording, since it
bounds what the line can ever show. `assignComments` looks entries up by
the **match** string, but a CIDR list's match is what `CidrTool`
returned, not what the table stores: `1.1.1.1` is stored, `1.1.1.1/32`
is matched, and the lookup finds nothing. So comments surface for
`string`, `substring`, `hostname` and `regex` lists and never for `cidr`
ones. Core's own event-view popover has the same hole. Nothing here
works around it — the line is simply absent where core cannot supply it.

### 7.2 The other two tables that carry a far value — 2026-09-02

Reading B5 back raised the obvious question: the marking applies to the
ranked table's values and to nothing else on the tab. Three of the six
sections list a value a reader can pivot to, and only one of them said
anything about whether MISP already knows it to be benign.

**Extended to the sibling table and the dated relations. Not to the
rest**, and the exclusions are the interesting half:

| Section | Marked? | Why |
|---|---|---|
| ranked values | yes | B5 |
| object siblings | **yes** | the same reading, in the same panel — and B4 had just reordered this table to *lead with the fields you can pivot on*, so a pivot onto a public resolver is precisely the one worth marking before it is taken |
| dated relations | **yes** | the far value is a resolution; a history resolving to `8.8.8.8` is a real resolution and still the least interesting row in it |
| near-match | no | a CIDR near-match and a CIDR warninglist are the *same kind of claim*. Marking one with the other may be saying a thing twice rather than adding a signal, and that needs deciding before it is built |
| asserted claims | **deliberately not** | somebody wrote those edges down on purpose. De-emphasising a value a human chose to link is a different claim from de-emphasising one a frequency count surfaced |
| references | **deliberately not** | same argument: an object reference is authored |
| the value's own occurrences, timeline, history | n/a | all the same value, so the frame's chip is the place for it. Per-row would repeat one fact down a column |

**A second lookup, and it cannot be the first one.** The sibling and
dated folds share `objectSections`' rows, which are the attributes of
the *objects* this value sits in — not the attributes of the *events*
the co-occurrence scan read. An object survives an event the scan
skipped for being oversized, which is the whole reason the sibling table
renders under a suppressed band, so a sibling value need not appear in
the other probe set at all. `objectSections` therefore runs its own
`ValueWarninglistTool::hitsFor` over its own rows and passes the map
into the one context both folds already share.

It costs one more query — `assignComments` again, and only where
something matched. Cold `viewRelationCooccurrence` goes 18 → **19**;
warm stays **3**. The per-value work on the overlap is nearly free:
`attachWarninglistToAttributes` keys its Redis cache on `(type, value)`,
so every value the two reads share is a hit the second time.

**Each table's cut says what it reaches.** Neither of these bars has a
narrowing endpoint — the sibling section's `[data-vp-list]` carries no
`data-vp-narrow-url` and neither does the dated panel's — so
`narrowingIsLocal` keeps both in the page. That is a different promise
from the ranked table's, which re-ranks the whole fold and comes back
with a fresh hundred, so the sibling note says so in as many words:
*"narrows the rows this table carries rather than the fold behind
them."* Verified in Chromium with `reloadAjaxTabIndex` stubbed to catch
any request: **none was made** by either.

    siblings, No hit    36 rows → 35, the one dimmed row gone
    dated, With a hit   2 rows, both of them listed on `dns.google`

Both were a *Hide warninglisted* switch on the day they were built;
§7.3 folded them into their groups.

The dated case is worth keeping: a switch that can empty its own table
is fine here because the panel already owns that state, and it renders
*"No dated relation survives that narrowing. The strip above dims the
spans it removed rather than redrawing without them."* Checked, rather
than assumed: the empty host is shown and the row host hidden.

**One mark, three tables.** The chip moved out of a closure in
`value_relation_cooccurrence` into `value_warninglist_mark`, because
the dated panel is a different template and one mark drawn twice is two
things to keep in step. It is written as pure PHP with no literal text
outside the tags and no closing `?>`, so it adds no whitespace to the
rows it sits in, and every caller guards the call rather than relying
on its early return — an unlisted row costs no element render.

#### Verified

`8.8.8.8`: 36 siblings, one listed — `0.0.0.0` on the RFC 5735 list,
dimmed with its chip, a `Warninglist 1` dropdown in the sibling bar and
the switch beside it. `dns.google`: both dated rows resolve to `8.8.8.8`
and both are marked, with the facet group carrying its own note —
*"2 of the rows below resolve to a value on a warninglist. They are
dimmed, not removed."* Both themes driven at 1340px with the fragment's
own JS live (36 rows in the DOM, 8 shown).

**Byte-identical where nothing is listed, diffed against the B5 commit**
so that only this change is in the comparison. Two cases, because
*absent* and *inert* are not the same claim:

| Value | Case | Result |
|---|---|---|
| `03634e2eab…` | no siblings, no dated rows | identical, both templates |
| `5db53f33d2…` | **five sibling rows, none listed** | identical, 252,517 bytes |

The second is the one that matters: it proves the sibling path renders
nothing rather than merely having nothing to render. Both facet groups
are appended to their template's group array rather than declared in it,
for the reason §7.1 records — a render loop prints a group's
indentation on the passes that `continue` past an empty one.

**A grammar bug caught in the screenshot, not in the markup.** The
sibling note read *"One sibling value of them are on a warninglist"* on
the singular. The count and the verb are now in one `__n` call, the way
the `$sibUnreached` sentence beside it already does it.

**§14.12's board has no row for `viewRelationDated` at all** — nor for
`viewRelationReferences`, nor for the Relationships tab's own
`viewRelationExternal`. Three endpoints phase 24 built and the board
never recorded, found while looking for the row this change should
update. `viewRelationDated` is measured here and filled in; the other
two are named in the board so the gap is visible rather than silent,
and stay blank because nothing has measured them.

### 7.3 The group carries the partition, and the switches go — 2026-09-02

The `Warninglist` group enumerated the lists and nothing else, which
left it unable to answer the two questions a reader actually arrives
with. *Show me only the noise* meant ticking every list in turn; *show
me none of it* was not in the group at all, because a value on no list
carries no list's token — it was on a **Hide warninglisted** switch
beside the group instead.

**Both halves are now entries, above the names:**

    With a hit                                 37
    No hit                                  10,003
    List of RFC 5735 CIDR blocks                21  ████████████
    List of known IPv4 public DNS resolvers     11  ██████
    BT02 domains                                 2  █
    …

`_hit` joins `_clear` as a token, stamped on a listed row beside its
list tokens, in all three tables. The two counts partition the set —
37 + 10,003 = 10,040 — while the list counts sum to more than 37,
because a value on two lists is counted under both, the way the tag
facet already counts one.

**Why they were not entries to begin with, and what changed.**
`value_facet_group` scales its bars to the largest entry, so *No hit*
at 10,003 flattened every list beside it to a 0% bar — §7.1 moved the
complement out to a switch for exactly that reason. The right fix was
in the element, not in the data: an entry may now declare itself a
`partition`, which takes it out of the bar scale **and off the bars
altogether**. A partition entry is not a member of the enumeration
beside it — it is one of the two halves the enumeration divides — so a
bar comparing it to a list's count compares two different quantities.
The bar element stays in the grid, transparent, so the row keeps the
height of the ones around it; an empty track next to filled bars would
read as zero rather than as *not on this scale*.

**The switches had to go, and not only for tidiness.** A switch and a
facet entry that carry the same `data-vp-facet-key` and the same value
are two controls on one token. Tick either and the other stays visibly
off while its narrowing is applied — the panel showing *Hide
warninglisted: off* over a table with the listed rows hidden — and tick
both and `narrowUrl` sends the token twice while the filter summary
counts two filters for one narrowing. That is a contradiction the
markup cannot resolve, so one of the two had to own the dimension. The
group owns it: it carries the counts, and the tab's own convention is
already one control per dimension — *Object siblings only* is a switch
with no dropdown, every other dimension is a dropdown with no switch.

#### Verified

`8.8.8.8` ranked table: *With a hit 37* (18 of them carried), *No hit
10,003* (82 carried), then seven lists whose bars read 100/52/10/5/5/5/5
— the scale they had before the partition entries existed. Ticking
either half goes to the fold, because neither is complete in the carried
rows: `_hit` returns 37 rows of 37 matched, `_clear` returns 100 of
10,003. Siblings and dated filter in the page, and were driven there:

    siblings  _hit    8 visible → 1        no request made
    siblings  _clear  8 visible, 0 dimmed  no request made
    dated     _hit    2 visible, both      no request made
    ranked    _hit    → ?f[warninglist][]=_hit

`dns.google`'s dated group shows *No hit 0* — every relation it holds
resolves to something listed. The element greys a zero rather than
offering a tick that could only empty the table, which is the behaviour
it already had for a vocabulary-counted group, and the row is worth
keeping for what it says.

**Byte-identical where nothing is listed**, re-checked on all three
cases after the switches came out — including `5db53f33d2…`, five
unlisted siblings, 252,517 bytes. Removing a `<?= … ?>` takes away a
`?>` that was eating a newline, so the filter row gained a blank line
and had to be given one back; the sibling loop needed no such fix,
because its switch had been appended without an extra newline in the
first place. Same class of bug as §7.1's four, in the other direction.

**Not changed: the counts are unformatted.** *No hit* prints `10003`
where the caption above it prints `10,040`. `value_facet_group` renders
every count raw and has since it was written, so a `number_format` here
would be a change to every facet on every tab — out of scope for this,
and worth its own pass if anyone minds.

### 7.4 A narrowed page said it was complete — 2026-09-02

Reported from the tab: tick **With a hit**, which filters correctly;
untick it and tick **No hit**, and the table reports *"No correlation
matches the filter you set"* over a neighbourhood with 10,003 of them.

**Not the cache.** The fold was right at every step — `?f[warninglist]
[]=_clear` returns 100 carried rows of 10,003 matched, and each row
carries the `warninglist:_clear` token. The second request was never
made.

**`data-vp-narrow-cut` was read off the wrong number.** The attribute
tells `narrowingIsLocal` whether this markup can answer a narrowing by
itself, and the template computed it as

    $co['matched'] > count($valueRows)

— *did the filter that produced this page leave more rows than the page
carries*. Narrowing `8.8.8.8` to `_hit` gives 37 matched and 37 carried,
so the flag cleared, and `narrowingIsLocal` returns `true` on its first
line when the flag is empty. The next tick was therefore answered from
the 37 rows in hand, not one of which is a *No hit*, and the panel
showed its empty state. `narrowRemotely` was never reached, which is why
nothing appeared in the network log.

The question the script actually asks is *could some other narrowing
want a row this markup does not have*, and the answer to that never
depends on which rows the current one kept. It is a property of the
neighbourhood against the page:

    $co['distinct_values'] > count($valueRows)

**The flag can only gain `1`, never lose it**, because `matched` is
always `<= distinct_values` — so nothing that used to be answered
locally has stopped being. `8.8.8.8` unfiltered was `1` before and
after; the `_hit` page goes `''` → `1`; a value whose whole
neighbourhood is carried (`03634e2eab…`, three of three) stays `''` and
still filters in the page.

**Pre-existing, and older than B5.** Any two ticks could reach it where
the first landed inside `row_cap` and the second wanted rows outside it
— an organisation with 40 neighbours, then a type none of those 40
carries. What the warninglist pair added was certainty: `_hit` and
`_clear` are complements, so the second tick matches *exactly zero* of
the first tick's rows every time. That is why it surfaced now.

**It also gates the rank pills** (`value-profile.js:1299`), so on a
filtered page whose matches all fit, *Most recent* was re-sorting the
carried rows instead of re-ranking the fold. Same fix, same reasoning.
The cost is one avoidable round-trip in the one case where a filtered
set is wholly carried and the reader changes rank; the two questions —
*is the filter's result complete* and *is the neighbourhood complete* —
would need two attributes to tell apart, and one conservative flag is
worth more than a saved fetch.

#### Verified

Reproduced first, in Chromium, against real fragments fetched from the
instance and served back by query — `reloadAjaxTabIndex` stubbed to
fetch and swap them, because the bug lives in the round-trip and a
harness without one cannot show it. Before the fix the second tick made
no request and emptied the table; after it:

| Sequence | Requests | Rows | Pager |
|---|---|---|---|
| `_hit`, untick, `_clear` | 2 | 8 shown | of 100 |
| `_hit`, untick, RFC 5735 (complete) | **1** | 8 shown | of 21 |
| `_clear`, untick, `_hit` | 2 | 8 shown | of 37 |
| RFC 5735, untick, `_clear` | 2 | 8 shown | of 100 |

The second row is the one that proves the fix is not just *always ask
the server*: RFC 5735's whole count is present in the carried rows, it
is marked `data-vp-complete`, and the panel still answers it without a
fetch.

### 7.5 Unticking is a narrowing too — 2026-09-02

Reported from the tab, against the fix above: on **In the same events**
tick a warninglist, which filters correctly, then untick it. The panel
says *No filter applied* and lists 21 rows, every one of them
warninglisted, beside a facet bar counting 10,003 that are not.

**§7.4 fixed which rows the next tick is answered from. The untick is a
state of its own, and was still answered from the rows the filter
left.** `narrowingIsLocal` read the controls and asked whether any of
them named rows the markup might lack; with nothing ticked, nothing did,
so it returned `true` and `refreshList` un-hid every row in hand. The
rows in hand are the trap: on a page the fold narrowed they are the top
of the *filter's* matches, not the top of the neighbourhood, so there is
nothing in the markup to widen back out to. `data-vp-narrow-active`
already marked those pages — `switchGroup` reads it for exactly this
reason — and the locality test did not.

**A page the fold narrowed can only be narrowed further.** Where that
flag is set, a state must name at least one `data-vp-complete` entry to
be answerable here; an unconstrained one goes back to the fold. One
complete entry is enough because `listed` is counted against the
neighbourhood's own `count`, so an entry that reaches every row it names
reaches them whichever filter fetched the page — and a state
constraining a set the markup holds whole is a subset of rows the markup
holds. It follows that unticking the *last* control is the only gesture
this costs a request, which is the request the reader just asked for.

**The same page's `Reset` was already right** (`value-profile.js:6148`),
which is what made the shape obvious: a reset over a served list was
told to re-fetch because *the rows it wants back are the ones the fold
cut*. Unticking the one box a reset would clear is the same sentence.

### 7.6 The event roll-up was emptied by a filter that does not apply

Found tracing the above, and reachable in two clicks: narrow the panel
to a warninglist, then switch **Group by** to *Events*. The table went
to zero rows and showed *"No correlation matches the filter you set"* —
directly under the note that says **narrowing applies to the value
roll-up**.

Three true things met. `switchGroup` keeps the ticks over a fold-served
narrowing rather than clearing them, because the rows on screen *are*
the narrowed ones until a request says otherwise. The fold narrows the
value roll-up only — `eventRollup` and `objectRollup` fold the whole
neighbourhood — so an event row carries no `warninglist:` token, or any
other facet token, of its own. And `refreshList` applied the ticked
facet to whatever rows were showing, which for a row with no tokens is a
test nothing passes.

So the client now says what the fold and the note already said: a
control inside a `[data-vp-group-only]` wrapper places no constraint
while another roll-up is on screen (`controlNarrows`). The value pane
keeps its narrowing, the event and object panes are whole, and switching
back restores the ticks and the 21 rows. Every other faceted list on the
page is unaffected by construction — they declare no `data-vp-group-*`
at all, so the test is `true` on their every control.

#### Reviewed and sound

The report asked whether the held data reloads when it has to, so the
rest of the path was read for the same defect:

- **The Redis scan** holds raw scan rows, not a fold, so a filtered
  request re-folds rather than re-reading, and no filtered result is
  ever stored under a key that could serve an unfiltered one.
- **The per-request memo** (`cooccurrenceContext`) is keyed on the
  narrowing as well as the value, and `relationDigest` calls it with no
  filters — so the rail cards' numbers are the neighbourhood's even on
  a filtered request.
- **`markListed`** measures each entry against `count`, which is folded
  over the neighbourhood, so `data-vp-complete` means the same thing on
  a filtered page as on an unfiltered one. That is what makes the rule
  above sound.
- **Re-applying a served filter locally cannot drop rows**: the fold
  matches `tokensFor()`, which is what the row carries in
  `data-vp-facet`, and its text and threshold tests read the same value
  and the same shared-event count the row prints.
- **The rank pills and `Reset`** go remote over a cut table and carry
  the active narrowing with them.

#### The pager line, which this pass left alone and §7.7 fixed

Noted here first as *a further local narrowing can strand the pager's
`(N in total)`*, which is wrong: that state cannot arise. See §7.7 for
what the pager's number actually got wrong, and for the proof that the
one described here does not happen.

#### Verified

Driven in headless Chrome against the live instance, logged in, on
`8.8.8.8` — 10,040 distinct neighbours, 100 carried, 37 warninglisted —
narrowing on `list-of-rfc-5735-cidr-blocks`, whose 21 are 5 in the
carried page and so cannot be answered locally. The same script was
replayed with the pre-fix `value-profile.js` served by request
interception, which is the *before* column.
`24b-narrow-locality-harness.js` is the first four rows and
`24b-narrow-matrix-harness.js` the last four; both take `VP_JS=<path>`
to swap the build under test. Driven for **With a hit** as well as for
a named list (`VP_FACET=_hit`), the partition entry being the one the
report names: 37 rows on the tick, 100 with 82 clear on the untick.

**One trap the first re-test walked into.** The page pins the script as
`/js/value-profile.js?v=203` — MISP's version, not the file's — and
nginx sends it with an ETag but no `Cache-Control`. A browser that had
the tab open before the change goes on running the old script under the
same URL, and the symptom is identical to the bug. A hard reload is the
difference; `performance.getEntriesByType('resource')` reporting
`transferSize: 0` for that script is how to tell from inside the page.

| Gesture | Before | After |
|---|---|---|
| Tick the list | fetch, 21 rows, all hits | same |
| Untick it | **no fetch, 21 rows, all hits, *No filter applied*** | fetch, 100 rows, 18 hits / 82 clear, *No filter applied* |
| Group by → Events | **0 of 18 rows, filter empty state** | 8 of 18 rows, no empty state |
| Group by → Value | 21 rows, ticked | 21 rows, ticked |
| Tick a complete entry (11 of 11) | no fetch, of 11 | no fetch, of 11 |
| Untick that one | no fetch, of 100 | no fetch, of 100 |
| `Reset` over a served narrowing | fetch, of 100 | fetch, of 100 |
| *Most recent* over a served narrowing | fetch, of 21, still ticked | fetch, of 21, still ticked |

The last four rows are the regression half, run both ways rather than
reasoned about: nothing that was answered in the page before it is
answered by the fold now, and the one gesture that gained a request is
the untick. The occurrences,
history, sibling and dated-relations lists were driven too — one tick
each, rows narrowing in the page, no request — because `controlNarrows`
sits in the collectors every faceted list on this page shares.

### 7.7 The pager counted values beside a range counting events

Asked directly whether the pager's total is a bug. It is, twice, and
neither is the case §7.6 filed:

**"In total" was the wrong sentence for the number.** The pager's
`total` slot is documented as *the value's own count, which filtering
never changes*, and every caller on the page passes exactly that —
except this one, which passes `matched`, the count the fold's filter
left. Narrowed to *No hit* the line therefore read `1–8 of 100 rows
(10,003 in total)` on a value with **10,040** neighbours: the two
numbers disagree by exactly the 37 rows the filter dropped, and the
smaller one was wearing the label of the larger. The fold has said
which sentence it meant since it started computing the number —
`1–8 of 100 (9,791 match)` is in the comment beside it — and the
shared element had no way to say it. It has one now, and unfiltered
(where `matched` *is* the neighbourhood) nothing changed.

**The number outlived its roll-up.** `Group by` switches the pane under
the pager, and the range follows the pane while the note did not, so
the event roll-up read `1–8 of 18 rows (10,040 in total)` — a count of
values beside a count of events, on one line, in the same breath. The
object pane said it too. Both are pre-existing and neither needs a
filter: they are there on first load.

The fix is the page's own switch rather than new script. The note
declares which roll-up it counts (`totalGroup`), which renders as the
`data-vp-group-only` the group pills already act on, so the number goes
away with the rows it describes and comes back with them. Nothing is
lost while it is away: each pane's heading carries its own total — 18
events, 100 objects — which is the count that pane's range agrees with.

**And the case §7.6 filed cannot arise.** It supposed a *local*
narrowing stacked on a served one, leaving `(10,003 match)` beside a
smaller range. For the browser to answer a narrowing itself, every
ticked entry must be marked `data-vp-complete` — every row those
entries name is carried — so the filter's whole result is carried, so
`matched` equals the carried count, and the template prints no note at
all when those two are equal. Ticking a complete entry beside the
incomplete `_clear` therefore goes to the fold, which is what the
instance does: it re-requested and came back `1–1 of 1 row`, no note.
Filed as noted-not-changed, which was the right call for the wrong
reason.

#### Verified

Section E of `24b-narrow-matrix-harness.js`, on the same `8.8.8.8`.
Lines are `innerText`, so what is hidden is absent rather than merely
marked:

| Where | Before | After |
|---|---|---|
| Unfiltered, value pane | `1–8 of 100 rows (10040 in total)` | unchanged |
| Narrowed to *No hit* | `1–8 of 100 rows (10003 in total)` | `1–8 of 100 rows (10003 match)` |
| Narrowed, event pane | `1–8 of 18 rows (10040 in total)` | `1–8 of 18 rows` |
| Narrowed, object pane | `1–8 of 100 rows (10040 in total)` | `1–8 of 100 rows` |
| Back to the value pane | — | `1–8 of 100 rows (10003 match)` |
| Plus a complete entry | fetch, `1–1 of 1 row` | fetch, `1–1 of 1 row` |

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

**The hub premise did not hold here, and §8.1 records what replaced
it.** Measured against the live panel rather than against SQL, `Most
shared` already leads with the campaign on this instance's hub values,
because the fold keys on the composed `value1|value2` and splits a hub
IP into its per-port rows. The rank still earns its place — it reorders
on evidence the frequency column cannot show — but not by rescuing a
page one that was never lost.

**What changes.** A third `RANK BY` pill — **Most specific** — on the
co-occurrence values table, and a column beside `Shared events` that
every rank carries: *"in 8 of its 204 events"*, *"in all 11 of its
events"*. The rank leads with shared events and settles their ties on
`shared ÷ the neighbour's own event count`. The sibling table gains the
same column, counted in objects, and orders `objects² ÷ total` inside
each half of B4's linking/descriptive split. The verdict-weighted
version of sibling ranking stays deferred to the engine; this task
takes only the evidence-based part.

**How.** The denominator is one grouped aggregate over candidate
values — `occurrenceSummaryFor`'s shape, plural — and it lives in the
model layer beside it, because the verdict engine's scope note wants
exactly this signal and a view-side rank would have to be rebuilt for
it. It is viewer-scoped like every other count on the page, and it
travels inside the cached relation scan next to B5's warninglist
verdicts, so a narrowing does not re-read it.

### 8.1 Done — 2026-09-02

**Two of the three grilling questions had factual answers.**
`over_correlating_values.occurrence` cannot serve as a denominator:
the table holds 1,627 rows and `MIN(occurrence) = MAX(occurrence) = 0`
— the column is not merely unmaintained on the read path, it is empty,
and it is partly keyed on CIDR blocks (`8.8.8.0/24`) rather than on
plain values. And the cost question resolved in favour of the whole
fold, so **the panel takes on no re-rank-a-page contract**: chunked
`IN` lookups on the indexed value columns cost 626 ms in the database
over `8.8.8.8`'s 9,520 neighbours, and the endpoint that carries them
is 6.0 s cold and 0.2 s warm on that value, 2.1 s and 0.5 s on `443`.

**Getting there cost three wrong diagnoses, and the last one is worth
keeping.** The lookup first ran at 171 s against 0.6 s for the same
work in SQL, and four of its sixteen queries took 40 s each while an
983-row query among them took 41 — the cost tracked neither rows nor
joins. The cause was PHP: `array_keys()` turns an array key that looks
like an integer back into one, so real neighbours like `443` and
`1204` — a port and a passive-DNS record count — left the fold as
strings and reached CakePHP as ints. Bound as integers against a
varchar column, MariaDB converted the column to compare and abandoned
the `value1` index, full-scanning 3.2M rows joined twice, but only for
the chunks that happened to hold a numeric-looking value.
`array_map('strval', ...)` took the lookup from **171 s to 0.93 s**.
Two real problems were found on the way and both fixes stand: the
composite pass originally OR'd a thousand `(value1 = A AND value2 = B)`
arms into one condition, and the grouping needs `order => false` beside
it, because `MispAttribute`'s default `Attribute.event_id DESC` turns
the group into a temporary table and a filesort. Grouping without that
was 401 s; dropping the group to escape the sort was 1,431 s and
hydrated 35,884 rows a chunk where 1,197 would do. Neither is the
answer alone.

Timings taken while another job was running its test suite are not
timings; the numbers above were re-taken with the machine idle, and
the 171 s baseline reproduced exactly.

**The formula question was decided twice, and the first answer was
wrong.** Pure lift was rejected on evidence and stayed rejected: 8,960
of `8.8.8.8`'s 9,520 neighbours appear in no other event on the
instance, so 94% of the table ties at 1.0, and on
`awake-weaves.cyou` it ranks seven unremarkable `2 of its 2` addresses
above `wrathful-jammy.cyou`, which shares 10 of its 11 events and is
the sibling C2 domain of the same campaign. The PRD's `shared >= 2`
floor does not rescue it — at that floor only 62 of the 9,520 qualify
and 51 owe their place to one pair of near-duplicate events (176 and
2016).

`shared² ÷ total` was then chosen and built, on numbers taken from SQL.
**It did not survive the live panel.** The simulation keyed neighbours
on `value1`, which merges composite attributes: `193.161.193.99|29763`
and its siblings folded into `193.161.193.99` and inflated its shared
count to 8, which manufactured the hub-dominated page one the formula
was picked to fix. The fold keys on `Attribute.value`, so the panel
splits those rows per port and **`Most shared` already led with the
campaign**. Worse, the scan's `RELATION_SCAN_BUDGET` compresses shared
counts — the `.cyou` rows reach 3, not 5 — so the square had nothing to
outrun and three `2 of its 2` rows finished above them. Shrinking by a
prior was measured too: `shared ÷ (total + 10)` bought exactly one
worthwhile promotion (`9.9.9.9`, 3 of its only 3 events) and paid a
`2 of 2` row in fourth place on the hub; at `+20` and above it
collapses into frequency-first anyway.

**So frequency leads and specificity breaks its ties**, which loses
nothing because ties are the normal case: 9,458 of those 9,520
neighbours share exactly one event, so the tie-break orders almost the
whole table. Verified live on both witnesses:

| Value | `Most specific`, page one |
|---|---|
| `8.8.8.8` | `1.1.1.1` 7/12, `google.com` 5/9, `2.2.2.2` 5/13, `circl.lu` 4/8, `9.9.9.9` 3/3, `1.2.3.4` 3/8 |
| `147.185.221.24` | the three `/api` URLs 3/8, then the three `.cyou` domains 3/11, then `103.57.130.241|54984` 2/2 |

`google.com` above `2.2.2.2` at five shared each, and `9.9.9.9` above
`1.2.3.4` at three each, are the reordering the task was for. No
one-off reaches page one on either value.

**The two tables rank by different keys, deliberately.** The sibling
table divides outright — `objects² ÷ total` — where the ranked table
only breaks ties, because the two face opposite hazards. The ranked
table folds thousands of one-event neighbours and any key that lets a
rare one overtake a frequent one fills its page one with noise. The
sibling table's one-object noise is already in the other block: B4's
kind split put `8.8.8.8`'s six `time_first`/`time_last` stamps, two
passive-DNS record counts and two `origin` names below the linking
fields, each of them a perfect 1.0 that never competes. What is left in
the linking block is a handful of genuine pivot fields, and dividing is
the only key that catches the placeholder holding the block's highest
object count:

```
LINKING            google.com                      4 obj   4 of its 5
                   google-public-dns-a.google.com  1 obj   its only
                   dns.google (rrname)             1 obj   1 of its 2
                   dns.google (domain)             1 obj   1 of its 2
                   0.0.0.0                         5 obj   5 of its 32,922
```

Each table's column heading sorts by its own table's key, not by a
shared approximation of both.

**What the column says, and what it is allowed to claim.** A fraction
in words rather than a score: a ratio cannot say both *frequent here*
and *frequent everywhere*, and a percentage would invite comparison
between denominators that are nothing alike. The numerator counts only
the events the scan read while the denominator counts every event the
neighbour is in, so the fraction understates specificity on values with
skipped events and never the reverse — `147.185.221.24` reads `3 of its
8` over 31 of its 34 events. The caption carries that scope
(*"Specificity is counted over the 31 events read."*) and the caption's
`ranked by shared events` — hard-coded while both ranks agreed about
what reaches the cut — is now rank-aware.

**Warninglists are not accounted for, exactly as `Most shared` does not
account for them**, so B5's sentence stands unchanged and the facet's
*No hit* entry is how a reader drops them. It only bites on one kind of
value: on a public resolver the most specific neighbour is another
public resolver (`9.9.9.9`, dimmed, on `8.8.8.8`). On both real
witnesses no page-one row is listed at all — campaign infrastructure is
not on benign lists. Excluding them was ruled out by the panel's own
words: the caption prints *"Nothing here is ranked away"* whenever the
whole neighbourhood fits, which is the common case.

**Composite keys get their own denominator.** 16% of this instance's
attributes carry a `value2`, and 2,231 of `8.8.8.8`'s 10,040 neighbour
keys are the composed `value1|value2` — which is no identity at all,
since `conditionsFor` matches `value1` **or** `value2` and finds
nothing for it. A key's count is therefore the union of both readings:
the composed value is `A|B` exactly when `value1` is `A` and `value2`
is `B`, or when `value1` is the literal `A|B` and `value2` is empty.
The union is assembled from the returned rows rather than expressed in
SQL — two plain `IN` passes over the two indexed value columns, with
the composed key rebuilt in PHP from the pair each row already carries,
and the left-hand halves joining the first pass's list so those rows
come back at all. The consequence is visible and correct:
`193.161.193.99|29763` reads *"in its only event"* while plain
`193.161.193.99` is in 204.

**`CACHE_SHAPE` goes to 5**, because the scan now carries `prevalence`.
A scan cached without it renders neither the pill nor the column rather
than sorting by nothing — `spread_read`, on the same reasoning as
B5's `warninglist_read`.

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

### 9.1 Done — 2026-09-02

Verified on the live instance with
`24b-lane-grouping-harness.js`, which reads the rendered lane labels,
tokens, counts and computed styles for four witnesses and then ticks a
facet, because the grouping change moves the key the narrowing pairs a
lane's axis to its count cell by. The brief named two witnesses; two
more were picked out of the database for the cases it did not think of
— the tall end of the value grouping, and a label long enough to
collide. Both found something.

**The threshold is the table's own page, not an 8.** `page_size` is
already 8 (`ValueProfile::RELATION_PAGE_SIZE`) and the fold already
receives it, so `datedLaneGrouping` compares the row count against
that rather than against a number of its own. The two now cannot
drift: whatever a page holds is what the strip is willing to name.

**`8.8.8.8` draws two lanes, not three, and the brief above was wrong
about the data.** Its three dated relations are
`google-public-dns-a.google.com` 2013-01-15→2018-09-30 and
`dns.google` 2019-06-04→2026-08-20, both `passive-dns`, plus
`dns.google` again 2024-03-11→2025-11-02 under `domain-ip` — three
rows over **two** distinct far values. The reading the task wanted
arrives anyway, and arrives more cleanly than the brief predicted:

- *before*, the `passive-dns` lane held two bars that were two
  different names, so the hand-off was inside a lane and therefore
  invisible; `domain-ip` held the third.
- *after*, `google-public-dns-a.google.com` ends 2018 in its own lane
  and `dns.google` begins 2019 in the next one, with its `domain-ip`
  re-observation overlapping in the same lane. Reading downwards reads
  the succession, and the second lane's two spans read as one name
  confirmed twice rather than as a second name.

So the verify line's *three* was an assumption about `8.8.8.8`'s rows
and the sentence it was standing in for — *whose spans read the
hand-off* — is what actually held.

**`draculax.myq-see.com.` is the case the whole section was written
for, and it was the one this fixes.** Five `passive-dns` relations: it
resolved to `141.255.159.82`, `168.181.48.248`, `168.181.51.45` and
`141.255.147.117` between 11 and 25 April 2017, then to nothing for
four years, then to `200.101.151.150` on 30 March 2021. Grouped by
template all five were one `passive-dns` lane holding five specks —
the dormant-then-reactivated *shape* was there and not one of the
names was. Grouped by value it draws five named lanes: four stacked in
April 2017, a four-year gap, then a fifth. §26.2 of
`03-relationships.md` says the strip exists so that shape is read in a
glance; it now carries who as well as when. Five lanes is also the
tallest this grouping can get short of eight, and at ~215px of strip
over a five-row table the panel is still the smallest on the tab.

**The strip did change, contrary to "nothing in the strip changes".**
It hard-coded three things a caller has to own once there are two
groupings: the label column's heading (`Template`), the lane chip's
icon (`fa-cube`), and the assumption that a lane label is a name. It
now takes `stripLaneHead`, `stripLaneIcon` (`''` for none),
`stripLaneMono` and `stripNote`, all defaulted to what it did before,
and the lane's own label field is `label` rather than `object` — a
lane keyed on a value carrying a key called `object` is the kind of
drift the fold's other comments exist to prevent. One producer, one
consumer, so the rename is total.

**A value lane label is verbatim, and that needed two classes.**
`.vp-rel-tag` uppercases and letter-spaces its text, which is right
for a template name and prints a value MISP never stored. The first
pass added `.vp-strip-tag-mono` as a single class; `.vp-rel-tag` sits
*later* in the stylesheet with equal specificity and won, so the
harness read `text-transform: uppercase` back off a chip whose rule
said `none` — the same trap `.vp-lane-span-off` documents a hundred
lines above it. Scoped to `.vp-strip .vp-strip-tag-mono` it reads
`none`. The icon is dropped for value lanes because the table cell
printing the same string gives it none either.

**The label column had to widen, and that came out of the render.**
`luxtrust-unlock.com` has six dated neighbours, two of which are
`ns-1769.awsdns-29.co.uk` and the SOA record beginning with the same
23 characters. At the strip's 11rem both elided to
`ns-1769.awsdns-29.co…` — two lanes claiming to be the same value,
which reads as a drawing fault and is the reading this section refuses
elsewhere by printing *same instant* rather than the same timestamp
twice. `.vp-strip-wide` takes the column to 16rem under the value
grouping only, where the axis is carrying at most eight spans and can
afford the 5rem; the two now read `ns-1769.awsdns-29.co.uk` and
`ns-1769.awsdns-29.co.uk awsdns-ho…`. The four rules that have to
agree about that width — the lane grid, the legend's indent, the
note's, and the wide variant — read it from one custom property, named
on the note as well as the strip because the note is the strip's
sibling and inherits nothing from it.

It is an elision and not a guarantee: two values sharing 38 characters
would collide again, and the chip's `title` is what holds the whole
string in either case. Widening further was not worth the axis.

**The grouping is stated twice and the threshold once.** The column
heading carries the grouping in both cases, in the table's own words —
`Related value` against `Template` — and the accessible name of the
strip names it too. The threshold line (*"One lane per related value,
oldest first, because the table is a single page at 3 rows. Above 8
rows the lanes are object templates instead."*) renders **only** under
the value grouping: the template grouping is what this strip has
always drawn and what the panel header already names, so a line
explaining it would spend three lines of height on the case nobody
asks about — and leaving it out is what keeps `github.com` byte-
identical, which the verify line asked for.

The line sits *outside* `.vp-strip` rather than beside the legend
because the strip is `role="img"` with an accessible name, so
assistive tech is told to ignore everything inside it; an explanation
of why the lanes are what they are would have been read by nobody.

**A lane token has to stay unique.** `VP.paintSpanStrips` finds a
lane's count cell by the token on its axis, and template names slug
distinctly by construction where values do not — `a.b` and `a-b` slug
alike. `datedLaneToken` suffixes a second claimant, so two lanes can
never share a cell and silently stop counting. Confirmed live under
both groupings: ticking `datedtype` repaints `21/46` on `github.com`,
`1/1` and `1/2` on `8.8.8.8`, and all six lanes on
`luxtrust-unlock.com` — including the two whose labels elide alike.

**`CACHE_SHAPE` goes to 6**, because the scan carries `dated.lanes`
and the lane's label field was renamed under it. A payload cached by
the old fold would hand the strip lanes with no `label` and no
`lanes_by`; the template defaults `lanes_by` to `template` so it could
only degrade rather than fatal, but the key retires those payloads at
the deploy, which is the rule §14.4 carries.

**`github.com` is unchanged**, checked rather than assumed: one
`url-honeypot-detection` lane, uppercase, cube icon, 46 spans, the
moment/span legend present, no note, and the same accessible name as
before. Only whitespace inside the chip moved, which an inline-flex
container ignores.

### 9.2 The overlap paint — shipped 2026-09-02

Per-value lanes put two spans in one lane for the first time, and
`8.8.8.8`'s `dns.google` — held from 2019 by `passive-dns` and again
from 2024 by `domain-ip` — was the first strip on the tab to draw an
overlap. It showed up as a darker patch, which was **alpha
compositing rather than a decision**, and the arithmetic behind it
turned out to be worse than it looked.

**Source-over cannot report depth.** Stacking `opacity: .55` is
affine: each layer moves the shade a fixed fraction of the way to the
hue and then there is nowhere left to go, so it asymptotes *on the
hue*. Measured on a bench built for the question
(`24b-overlap-mockup.html` — frozen geometry, seeded data, a ruler
stacking a known depth of 1 to 6 and a swatch ramp out to 14, in both
grounds): **depths 4, 5, 6, 8, 10 and 14 are the same pixel.** A
50-span lane was one flat bar that could not say *where* the activity
was.

**Multiply is geometric** — each layer scales the result rather than
nudging it toward a fixed point — so the ramp never arrives and never
runs out. `screen` is the mirror on the dark ground, where the hue is
the light end and an overlap correctly reads *brighter*.

**40% of the hue, and the bench chose the number.** Because
`isolation: isolate` fixes the group's backdrop as transparent,
multiply against it *is* source-over — so a lone span is always just
that alpha over the card. At 55% a lone span is therefore
pixel-identical to the old paint, but the readable range only goes
from three to about four, which throws away the point. At 22% the
ramp runs past a dozen and a lone span reads washed out — and a lone
span is the common case, because the value grouping caps a lane at a
page of rows and most hold one. 40% keeps a lone span near its old
weight and separates to about five.

**The alpha moves from `opacity` into the `fill`.** Element opacity
would make each rect its own group at 0.55 and multiply *that*,
putting the effective fill back at 22% — the washed-out end of the
sweep, arrived at by accident.

**Scoped to `.vp-strip`, and this one is not caution.** The Timeline
tab draws `.vp-lane-span` too, and its span lanes carry several
sources at once, each with its own `--vp-tl-hue` — there the hue *is*
the source's identity. Multiplying two hues invents a third colour
belonging to neither source, which would be a lane lying about who
reported what. Every span in this strip takes one `$stripHue` from
its caller, so here the blend can only darken. The cost is a lone
span slightly lighter here than on the Timeline; the mark still means
the same thing, which is what §26.1 of `03-relationships.md` actually
asks of the shared stylesheet.

**The claim is now in the legend**, because a drawn claim with no key
is what this strip's own comments refuse elsewhere: *"deeper where
spans cover the same dates"*, with a swatch that stacks two spans
through the same rule the lanes use rather than hand-picking a darker
fill. It renders **only where an overlap is actually drawn**, measured
in viewBox units through `$xFor` — two spans a minute apart on a
four-year axis are the same pixels, so what counts as overlapping is
what the eye is shown. Verified live: the key appears on `8.8.8.8`
and `github.com` and is **absent on `draculax.myq-see.com.`**, whose
five relations are all moments.

**The narrowing still dims.** `.vp-lane-span-off`'s `opacity: 0.1`
matches the new rule at equal specificity and won only by sitting
later in the file, so it is restated at three classes — read back as
`0.1` on 25 dimmed spans on `github.com`. Checked in Chrome only;
`mix-blend-mode` inside SVG wants a Firefox look.

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
clusters reachable through this value's events, ranked by independent
organisations, then events:

> **APT29** — actor · 3 orgs · 4 events
> **Remcos** — malware · on the value · 1 org · 1 event

Top 8 rows and an expander. Each row links to the cluster. The card
states its read the way every panel does:
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

**Grilling settled it — 2026-09-03.** Every item below was decided in
session; §10.1 records what was built and what the decisions cost.

- **A named threat is a galaxy cluster, and nothing else.** Freetext
  tags are out and taxonomies contribute nothing — both measured, in
  §10.1.
- **The classification does not live in this page.** It is a property
  of the *galaxy*, so it moved out to `GalaxyCategory` in
  `app/Lib/Tools`, keyed on galaxy type, with `named-threat` split
  into four kinds: actor, campaign, malware, tool.
- **Events only in the first cut.** Neighbour attribute tags add 2
  clusters on `8.8.8.8`, both attack-patterns — zero named threats for
  a second source.
- **Asserted far ends contribute**, as a fourth way in.
- **Local-only galaxies are skipped**, pending the real fix: the
  category landing on the galaxy itself.
- **The `value_context` boundary is a word on the row, not an
  exclusion.** One list, ranked by organisations then events, with the
  attachment marked and *not* in the sort.
- **Rail, middle position**, and the notion-colour question dissolved:
  both existing rail cards are neutral.

**How.** Five indexed queries, held in the five-minute rail digest the
other two cards already share. **Not** read off the co-occurrence
scan, which was the plan and is wrong: the scan skips an event too
large to fold, but an event's tags cost the same whatever its size, so
tying the card to the attribute budget would drop named threats for an
unrelated reason. Reading the events directly also means the card
answers on values whose neighbourhood table is suppressed entirely.
Ranking is `COUNT(DISTINCT org)` then `COUNT(DISTINCT event)`, folded
in PHP. The fold is a model-layer method for the reason given: the
verdict engine's scope note lists neighbourhood context among its
wanted signals, and the Overview's future count line is a second
caller.

**UI.** Ran the **frontend-design** skill. The brief pinned the
direction — it has to look native to two existing rail cards, and this
tab rations colour strictly — so the work went into row grammar
rather than palette, and the one liberty taken is typographic.

**Verify.** `8.8.8.8` shows **APT29** and **Flying Kitten**. The
spec's original witness was wrong: it named `LummaC2`, which is a
freetext tag, not a cluster.

### 10.1 Done — 2026-09-03

The card is live at `value_relation_threats`, third in the rail,
between the graph and "What is counted".

**Freetext tags are out, and the numbers are why.** 10,840 freetext
tags exist on the instance and 173 are used on events. Ranked by event
count the top two are the word `malware` (165 events) and ` C2` (72),
so a card admitting them would lead with a category noun. One malware
family carries seven spellings — `misp-galaxy:malpedia="Lumma
Stealer"` plus freetext `LummaC2`, `Lumma`, `Lumma Stealer`,
`LummaStealer`, `lummaC`, `ViaLumma` — so it would also double-count
whatever it did recognise. The spec's own example row was one of them.

**Taxonomies contribute nothing, checked rather than assumed.** The
three that sound like they name threats classify instead:
`malware_classification` by category, `ms-caro-malware` by type and
platform, `adversary` by infrastructure status (the latter two are not
even enabled here). The only namespaced tags on the instance that do
name a threat — `Threat:Sofacy/APT28`, `Banker: TrickBot` — are absent
from the `taxonomies` table: freetext with a colon in it.

**The classification moved out of the page.** `GalaxyCategory`
classifies 89 of the 130 galaxies misp-galaxy ships across seven
categories, of which the card reads one. It is keyed on galaxy `type`
— the string in every tag name and in `galaxy_clusters.type` — so no
caller needs a join. Of the families actually tagged on events here,
28 classify as `named-threat` (1,101 event-hits: actor 406, malware
461, tool 234) and 10 as `technique`; 23 event-hits fall through
unrecognised, every one a locally created galaxy (`stix-2.1-*`,
`ls26-threat-actors`, `tea-matrix`), a legacy misspelling
(`mitre-entreprise-attack-*`), or genuinely out of scope.

**The spec's five-family list would have dropped most of it.** Four of
the five exist — there is no galaxy called `malware` at all — and
between them they reach 614 event-hits. The families the list omits
reach another 487, `malpedia` (133 events) and `mitre-intrusion-set`
(87) among them.

**This file is the interim home and says so.** The right place is a
`category` field on each galaxy in the misp-galaxy repository,
ingested by `Galaxy::__load_galaxies` and editable per galaxy, so an
administrator can classify the galaxies they created. Until then a
galaxy absent from the table is *unrecognised* rather than judged
harmless, and the card skips it — which is why this instance's three
locally created threat-actor galaxies ("LS26 - Threat Actors", "CC25 -
Threat Actors", "GSMA - TA") contribute nothing. The docblock carries
the destination.

**`orgs` counts what the schema can support, and the card says so.**
Neither `event_tags` nor `attribute_tags` records who applied a tag —
no org column, no user column — so the count is the *creator
organisations of the events carrying the cluster*, stated in the
card's footer rather than left to be read as three independent
attributions. A claim is the one source that does record an author,
and contributes that org instead.

**Three ways in, one word out, and a fourth is coming.** A cluster
arrives on the value's own occurrence, on one of its events, or as the
far end of a claim; where more than one applies the most specific
wins. Only the tighter two are marked, because an event arrival's own
event count already says so. Objects cannot be tagged yet — there is
no `object_tags` table and `MispObject` reaches a tag only through its
attributes — so `ValueProfile::neighbourhoodThreats` names both
`object_tags` and `ObjectTag` in its docblock, and `threatRank`
records where the fourth rank belongs. A grep for either lands on
both.

**`CACHE_SHAPE` 6 → 7.** `eventMetadata` now keeps galaxy tag names
instead of dropping them, which changes what the scan holds.

**The design pass cut its own device.** A leading glyph per kind was
built, rendered and removed: it encoded exactly what the text beside
it already said, and at 0.72rem `fa-user-secret` — the most frequent
of the four — is a blob.

**Then the card was rebuilt twice**, and both earlier cuts were wrong
in the same direction: they *described* the neighbourhood instead of
letting a reader work it.

The first was eight equally-weighted rows with `1 org · 2 events`
repeated down the column, the constant words in front of the figures
the card ranks on, and no focal point in a card whose purpose is to
produce a name. The second added a lead sentence and a static
composition line — and both failed review for the same reason: the
line said the neighbourhood held 63 malware and then offered no way to
see them, and the lead promoted one row for a reason no reader could
infer from looking at it. *"I'm not sure what the sits next to is
meant to show."*

What shipped:

| Element | Answers | How |
|---|---|---|
| subtitle | *what is a named threat?* | names the four kinds — the term is defined where it is used, instead of assumed |
| count pills | *what sort, and show me* | `All 125` `Actors 28` `Malware 63` `Tools 34`; picking one shows **every** cluster of that kind, not the eight the card opens with |
| cluster badges | *which ones* | MISP's own cluster badge, tinted per galaxy by `GalaxyColour` |
| figures | *how well corroborated* | numbers at body weight, units muted |

**The counts had to become a control.** As a sentence they were a fact
with nowhere to go. As pills they answer the same question and act on
it, and the per-row kind label hides itself while a kind is picked
because the pill already says it. `.vp-pill` is the tab's own control,
so the active state borrows `--primary` — a UI accent, not one of the
seven notion hues, which stay unspent.

**Clusters look like clusters.** `GalaxyColour` derives a hue from the
galaxy's name and every galaxy view in MISP tints its clusters with
it, so a Threat Actor cluster is the same colour here as on the event
page. A galaxy hue is not a notion hue, so the notion grammar is
untouched, and dark mode needs nothing: `mainOvermind.css` lifts
`--galaxy-alpha` from 0.12 to 0.92 so the badge's own text colour
still reads. Reaching for a monochrome treatment to protect the notion
palette was over-caution — consistency with the rest of MISP was the
stronger claim, and it costs the palette nothing.

**Removed:** the lead, the static composition line, and the footer
stating what `orgs` counts. The footer's other half — that the card
reads galaxy clusters — moved into the subtitle, where it doubles as
the definition.

**Rendering found defects the markup did not.** `Malicious` drew
`APT28 - G0007` three times with near-identical counts, because MITRE
ships APT28 as an intrusion set in the enterprise, mobile and
pre-attack galaxies. They are three real records with three real
links, but the rows read as a bug — so a row whose name collides with
another now names its galaxy, and the rest do not. It earns its place
on the filtered view too: `Malicious` holds two different `ARS VBS
Loader` clusters, one from Malpedia and one from RAT, and `Azorult`
beside `AZORult`.

Two more that only rendering could show. An intermediate row layout
stranded the kind alone on a second line while the name shared line
one with the figures, costing seven rows a half-empty line. And the
fold was scoped wrongly: `.vp-threat-folded` hid every row past the
opening eight *unconditionally*, so picking **Malware 63** showed the
two that happened to fall inside the first eight. The fold is now
scoped to the `top` view, which is the whole point of the pills.

**Verified live**, each state against a real value:

| Value | What it exercises | Result |
|---|---|---|
| `8.8.8.8` | the spec's witness | **APT29**, **Flying Kitten**, both actor, over 20 events — 2 of its 26 event clusters, 21 of the rest being attack-patterns that belong to B9 |
| `Malicious` | the cap and the expander | 125 rows, 8 shown, "Show 117 more"; `tool`, `actor` and `malware` kinds; rows from `microsoft-activity-group` and the deprecated `mitre-mobile-attack-intrusion-set`; the sort putting 2-org rows above 1-org rows and ordering those by events |
| `5.101.86.76` | the `on the value` chip | **Remcos** · malware · on the value, from `malpedia` on 3 of its occurrences |
| `github.com` | the empty state | states that nothing in its 1 event names an actor, a campaign, malware or a tool |

The four existing relation panels were re-fetched after the
`CACHE_SHAPE` bump and render clean.

The pills were verified by driving a real click rather than by reading
the markup: with `Malware 63` picked, 62 of `Malicious`'s 125 rows
carry `vp-threat-off`, the card carries `vp-threats-filtered`, the
per-row kind label is gone, and the list scrolls inside its 260px cap.
Both themes checked. The harness needs `data-controller="values"` on
`body` or `init()` returns early and no listener is attached — which
is what made the first click test silently do nothing.

**The human-claim path shipped broken, and the missing data hid it.** All three cluster-naming claims on the instance were
unusable at build time — one cluster-to-cluster with no value at its
near end, one targeting `country="belarus"`, one pointing at a cluster
that does not resolve — so the path was flagged as exercised by
nothing. It was worse than unexercised. `assertedClaims` returns a
*section* (`total`, `orgs`, `hidden`, `occurrences`, `capped`,
`prose_absent`, `claims`) and the digest passed the whole array where
the fold wanted `['claims']`, so the fold iterated the counts as
though each were a claim. `$claim['target']` on an integer is null,
null is not `GalaxyCluster`, and every claim was skipped in silence.

Found on 2026-09-03 when a claim was authored on purpose: an
`8.8.8.8` occurrence (attribute `669dc845`, event 4074) `related-to`
`threat-actor="APT1"`. The asserted section listed it and the card did
not, which is what localised the fault to the fold rather than to the
read. Fixed at the call site, and `8.8.8.8` now draws **APT1 · actor ·
Human claim · 1 org** — no event count, because a claim contributes
its author's organisation and no event.

The lesson is the flag's, not the bug's: *unverified* was recorded
honestly and still read as *probably fine*. A path with no data to run
on is not a caveat, it is an untested branch.

`campaign` remains undrawn, Tidal Campaigns being installed but
unused here.

### 10.3 A claim can be written about the container — 2026-09-03

A second claim authored the same day exposed the next gap: event 4074,
which contains an `8.8.8.8` occurrence, `linked-to` the same `APT1`
cluster. It appeared neither on the card nor in the asserted section,
because `assertedClaims` matched only claims whose near end is one of
the value's *attribute* occurrences.

That was too narrow, and the asymmetry gave it away: **the tab already
counts a plain galaxy tag on an event the value appears in, and was
dropping a claim on that same event** — ranking a label above a
deliberate, authored, typed statement. The parent object is the same
argument one level tighter: a claim about the `domain-ip` object an
address sits in is a claim about the thing the address is part of.

**Widened in the section, not in the card**, because the page's idiom
is that the card counts and the section lists off one method so the
two cannot disagree. `assertedClaims` now asks about three anchors —
the occurrence, its event, its object — as six equality pairs in one
`OR`, still one query. `claimFrom` matches the near end on the *pair*,
type and uuid, so an Event uuid can never be taken for an Attribute
one, and it returns which anchor matched.

**The containers cost no query.** `occurrenceUuidsFor` already joins
`Event` and `Object` — the ACL needs them — so their uuids come off
the fetch that was already happening. One catch, and it is the kind
that hides: with an explicit `fields` list, Containable selects
exactly what is named, so `Event.uuid` and `Object.uuid` had to be
asked for. Every earlier caller read only `Attribute.*`, so the
omission had cost nothing until now — and it failed silently, the
event-anchored claim simply staying absent, which is what sent the
first fix in the wrong direction.

The asserted section grew from 40.7 KB to 57.8 KB on `8.8.8.8`, which
is the claims it had been dropping.

### 10.4 The claim mark, and what it says on hover — 2026-09-03

The card's mark is the tab's own `.vp-rel-prov-human` —
`fa-user-pen` in `--vp-rel-human`, reading **Human claim** — rather
than a chip of this card's invention. This is the one place the rail
*should* spend a notion hue: it is the human-claim notion marking a
human claim, which strengthens the four-fold separation the tab
carries (colour, form, word, place) instead of overloading it. Only
the size is local, that chip being built for a 0.72rem panel header
against this line's 0.64rem.

**The word had to move with the styling, and at first it did not.**
The mark shipped saying *claimed by an analyst* — the same notion
under a second name, in a card that had just borrowed the styling of
the first. The separation rule works *because* the word is the same
word in every region, so a synonym quietly weakens the thing the
colour was borrowed to reinforce. `Human claim` is what the asserted
and references panels say, so it is what this says.

Two words cannot say who claimed what, so the rest is on hover, one
line per claim:

> ADMIN claimed "linked-to" on an event it appears in · 2026-09-03
> ADMIN claimed "related-to" on this value · 2026-09-03
> Shown in full under Asserted by analysts.

Which anchor matched is the point of that line — without it, *claimed
by an analyst* hides the difference between a statement about this
address and one about a report that merely contains it. The tooltip
ends by naming the section that lays the claim out properly, with its
author, direction and type; this is the peek, not the record.

**Note for verification:** the rail cards take no `fresh` parameter —
only the co-occurrence endpoint does — so `?fresh=1` is ignored here
and a code change is invisible for up to `RELATION_SCAN_TTL`. The
digest lives in redis **DB 13**, not DB 0, which is why a scan of the
default database reports no keys to drop.

### 10.2 Follow-up: galaxies and taxonomies as neighbour context

Raised while closing B8. The co-occurrence table's notion of a
neighbour is *another attribute in the same event*. It could as well
carry **galaxy clusters and taxonomy tags as neighbours in their own
right** — one table of everything sharing this value's events,
attributes and labels alike, with the same facets, ranks and pager
over all of it.

This card would then stop being a separate read and become **a subset
of that table with its own prioritisation**: the rows whose neighbour
is a cluster, filtered to `named-threat` by `GalaxyCategory` and
ranked for the rail. The card stays — a rail summary is not a ledger —
but it would share the table's fold instead of running its own five
queries.

Worth investigating before B10, because it changes what "neighbour"
means on the tab and therefore what the co-occurrence facets are
facets *of*. Not scoped here.

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

**What B8 left ready.** `GalaxyCategory::typesIn(GalaxyCategory::TECHNIQUE)`
returns the 24 technique-naming galaxies, and the `kind` beside each
separates the three sorts this group has to treat differently:
`attack-pattern` for the ATT&CK-shaped families that carry
`kill_chain_order` (25 galaxies do), `technique` for other frameworks'
own technique lists, and `tactic` for the galaxies that name a phase
directly and so need no collapsing. `neighbourhoodThreats` already
resolves every galaxy tag on the value's events through
`fetchGalaxyClusters` and then discards the non-threats — B9 is the
second reader of that same resolved set, so it belongs inside that
method rather than beside it.

On `8.8.8.8` the group has real material: 21 of its 26 event clusters
are `mitre-attack-pattern`, which is also why B8 excludes them — folded
into one list they would bury the two names beside them.

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
- **The ssdeep candidate cap** — §4.2, found while measuring B2 and
  unowned. Listed here so it is not mistaken for done; it wants a task
  of its own, because it is a wrong answer rather than a small one.
- **The Timeline source lane** — the Timeline tab's phase;
  `url-honeypot-detection` joined `passive-dns` as a candidate.
- **WHOIS history, JARM/favicon/cert pivots, contacted
  infrastructure** — every one needs external acquisition, which is
  the Enrichment tab's job. Importing them here would be the moment
  "What is counted" stops being cheap to keep true. Rejected, not
  deferred.
