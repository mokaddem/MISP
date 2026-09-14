# PRD: Value Profile — Overview goes live

**Phase 29**, the eighth and last live phase, and the one that empties
`ValueProfileFixture` of readers. Converts three panels —
`value_occurrences`, `value_context` and the two thirds of
`value_lifecycle` phase 5 left alone — plus **the page frame**, which is
the only surface on this page that is not a panel and the only one that
is fetched synchronously. Depends on
[`00-contract.md`](00-contract.md) §14, and on more finished work than
any phase before it: every data source this phase needs was built by a
phase converting some other tab.

Carried as **"22+"** since the campaign opened, because the Overview
mirrors every other tab and the order was never fixed. It takes 29 now
that it is last, and the reason it is last is the reason it was blocked:
its verdict card needed an engine that did not exist until
2026-09-13.

**Opened 2026-09-13 and built 2026-09-14.** §1's board is done, §1.1
holds the decisions taken before building, and **§14 is the build log** —
what the build changed about the plan, and the five defects it found
that no amount of reading would have. The fixture-era design is
[`value-profile-page.md`](../value-profile-page.md) §3 (phase 3, the
skeleton's Overview) as amended by every phase that has since taken one
of its cards.

---

## 1. The task board

Every row is `todo` until its own section says otherwise, and a row moves
to `done` only when §9's verification has run against it.

| # | Task | Section | Status |
|---|---|---|---|
| T1 | `ValueProfile::forFrame` — the frame's one synchronous read, and `view()` onto it | §4.1 | **done** — 6 queries |
| T2 | The banner's type chips from `Value::typesFor` | §4.2 | **done** |
| T3 | The banner's warninglist chip from `ValueWarninglistTool::hitsFor` | §4.3 | **done** |
| T4 | `value2_note` — derived, or withdrawn with its reason | §4.4 | **done** — derived, §14.3 |
| T5 | The fact strip's six facts, four of them from one aggregate | §4.5 | **done** — **five** cells from **one** aggregate, §14.1 |
| T6 | The pivot rail — **withdrawn** (D1), and the element deleted with it | §8.1 | **done** — element and CSS both gone |
| T7 | `ValueProfile::forOccurrences` — the card, capped, on `fetchAttributesSimple` | §5.1 | **done** — cap 25 |
| T8 | `occurrence_stats` — five of its six keys; `hidden` withdrawn (D3) | §5.2 | **done**, and §14.2 is the defect it exposed |
| T9 | `ValueProfile::forContext` — tags grouped by taxonomy, from `ownTagsFor` | §6.1 | **done** — from `topTagsFor`, which §14.4 explains |
| T10 | The ordinal scale, against the taxonomy's own `numerical_value` | §6.2 | **done** — §14.5 is the two bugs in it |
| T11 | Galaxy clusters behind `fetchGalaxyClusters`, not behind their tag names | §6.3 | **done** |
| T12 | The Lifecycle card's warninglist line — the same tool, a second read | §7.1 | **done** — via `verdictWarninglist`, checked against the tab |
| T13 | The correlation line — the flag kept, the count withdrawn (D2) | §7.2 | **done** |
| T14 | The three concepts: the proposals decision, and the event-report count | §10 | **deferred, with reasons** — §14.8 |
| T15 | The board rows — §14.12's four cells and §14.13's phase row | §12 | **done** |
| T16 | Verification over HTTP, on five values, in both themes | §9 | **done** — §14.9 |

---

### 1.1 The decisions, taken before building

Eight. Three of them withdraw something rather than convert it, which is
what a phase converting a *skeleton's* tab should expect: the Overview
was drawn in phase 3 against invented data, and invented data has no
opinion about what MISP can supply.

**D1 — the pivot rail is withdrawn, not converted.** Its five chips are
*containing CIDR*, *ASN*, *geolocation*, *ports seen* and *passive DNS*.
MISP stores none of the five for a value. Every one of them is an
enrichment answer, and the Analyst Profile's **D15** settled that nothing
on this page auto-runs a module — so converting the rail means either
running five modules on page load, which D15 forbids, or drawing five
chips that can never be filled. The element already says so in its own
docblock (*"the pivots are fixture hints, not resolved values, so a chip
has nothing real to point at yet"*) and renders every chip inert. This
phase deletes it. §8.1 carries the alternative that was weighed — a rail
re-founded on pivots that *are* resolvable from the database — and why
it is a different feature rather than this one converted.

**D2 — the correlation count goes; the over-correlating flag stays.**
The Lifecycle card prints *"N correlations"* beside an over-correlation
warning. Phase 24 established that **the correlation engine has nothing
to say about a value** — correlations attach to attributes, so a value's
count is the union over its occurrences and costs a query that grows with
them (tier 3 under §14.4 with no stated cap). The flag is different: it is
live, it is already read by the assessment's evidence budget, and it is
the half of the line that changes what a reader should do. The card keeps
the warning and drops the number. §7.2.

**D3 — nothing on this page counts what the viewer cannot see.** The
fixture carries `occurrence_acl_note` — *"4 are hidden by distribution
rules on events owned by other organisations"* — and
`occurrence_stats['hidden']`. **The note already has no reader**; it was
dropped at some point without being removed from the fixture, and this
phase does not resurrect it. `hidden` goes the same way. The card's empty
state already says the honest form of this — *"No event you can see
carries this value"* — which distinguishes absent from hidden without
quantifying the gap. A count of withheld rows is a disclosure, not a
caveat.

**D4 — the strip's dates read the observation, and say so when there is
none.** `occurrenceSummaryFor` already computes `oldest`/`newest` over
`Value::OBSERVED_FROM`/`OBSERVED_AT`, which is **D20** as SQL. The trap
this phase has to avoid is the one D20 caught four aggregates committing:
falling back to `Attribute.timestamp`, which is a last-modified column,
when the observation dates are absent. **They are absent most of the
time** — 6.2% of this instance's attributes carry `first_seen` and 16.2%
carry `last_seen` — so *First seen* is unknown on the majority of values
and the strip must be able to print that rather than a row-write date
wearing an observation's label. §4.5.

**D5 — the frame is synchronous, and its budget is stated before it is
spent.** Every other surface on this page is lazily loaded; the frame is
not, so a query added here is a query on the critical path of every page
load. `view()` already pays **2** for `forTabCounts` and **9–27** for the
assessment behind the tab pill. This phase adds the type chips, the
warninglist chip, the `value2` note and the fact strip. §4.1 makes that
one method with a stated count rather than four reads accumulating
unremarked, and §9 measures it.

**D6 — galaxy names come from `fetchGalaxyClusters`, not from tag
names.** Galaxy clusters reach attributes as tags, and `ownTagsFor`
returns them with `is_galaxy` set — but its docblock is explicit that
*"a galaxy tag is still worthless until `fetchGalaxyClusters` says the
viewer may know its cluster exists; that ruling belongs to the caller."*
This phase is that caller. Printing cluster names straight off the tag
rows names clusters the viewer may not be permitted to know exist.

**D7 — the occurrence card is a preview, capped, and reads
`fetchAttributesSimple`.** Phase 22's inheritance note says
`fetchAttributes` *cannot serve this page*: it forces `deleted = 0` for
anyone without `perm_sync` and `object_id = 0` without `flatten`, so it
drops exactly the two row classes this card draws — the soft-deleted ones
behind its toggle, and every occurrence inside an object. The card is
also not the table: it has no pagination, no sort keys and nine columns
rather than ten, so its cap is small and fixed, and its header states
`shown` against `total` the way it does today.

**D8 — one warninglist read per request, and the duplication is stated
rather than hidden.** The banner chip and the Lifecycle card both need
the hits, and they are in **different requests** — the frame is
synchronous, the card is lazy. Nothing can be shared between them (the
same constraint §2 of `10-wiring.md` states for the assessment), so this
is two reads of one tool, each `Q=1`. What must not happen is the two
disagreeing: both call `ValueWarninglistTool::hitsFor` with the same
pairs, so they agree for the same reason the Assessment tab's ledger and
the Overview card agree — determinism, not a shared cache.

---

### 1.2 What a session picking this up cold needs to know

- **The page is live except for this.** Eight tabs read the database.
  What is left is three panels and the frame; §3 is the exhaustive list,
  read out of the templates rather than out of a plan.
- **Almost every source already exists.** This phase writes facade
  methods and elements, not readers. `typesFor`, `occurrenceSummaryFor`,
  `ownTagsFor`, `occurrenceEventsFor`, `fetchAttributesSimple` and
  `ValueWarninglistTool` were all built by earlier phases for other tabs.
  Where this phase does write a reader, §13 says so.
- **The Overview mirrors; it does not originate.** Four of its seven
  cards were taken by the phase that converted the tab each mirrors, on
  the rule that *a card and a tab on one page that could disagree about
  the same value is worse than a tab converted out of order*. The three
  panels left have no tab to mirror — the occurrence card previews a
  table that is already live, the context card has no tab at all, and
  the Lifecycle card is three questions from three different places.
- **`ValueProfileFixture` is not deleted when this lands.** §14.8 makes
  it a unit-test double: its arrays go on being handed to elements so a
  template renders with no database. What changes is that nothing in
  `ValuesController` reads it. The keys this phase withdraws (D1, D2, D3)
  come out of the *templates*; the fixture may keep them.
- **The page still writes nothing.** Every control that would write
  renders visibly disabled, including the occurrence card's checkbox
  column and its multi-select toolbar, which this phase leaves disabled.

---

## 2. Why this phase is last, and what it inherits

It was blocked, then it was deferred, and only one of those was about the
Overview.

**Blocked** until 2026-09-13: `value_verdict_card` had no engine to read.
That card is now live — the Analyst Profile's phase 9 converted it with
the Assessment tab, and it computes its own assessment rather than
reading the tab's, because the two are separate requests.

**Deferred** for everything else, deliberately. The campaign's rule was
that a tab's Overview card belongs to the phase converting that tab, and
by the time the rule had run its course four of the seven cards were
already live: `value_sightings` (phase 23), `value_external` (phase 24),
`value_analyst_preview` (phase 26 §20) and `value_verdict_card`
(analyst-profile phase 9). What was left is precisely the set with no tab
behind it.

What it inherits, beyond the readers in §13:

- **§14.12's board** already carries this phase's four rows, three of
  them empty. Filling them is T15.
- **Phase 22's four findings**, of which D7 above uses two.
- **The coverage survey's starting verdict** for this tab:
  proposals *yes — a decision*, feeds *yes — this is its home*, event
  reports *yes — the count*. One of the three is already delivered:
  `value_external` is the feeds answer and went live with phase 24. §10.
- **The §14.10 hazard about warninglist categories** — no shipped list
  sets `category` explicitly, so all 71 checked import as
  `false_positive`. The Lifecycle card prints *"category X"* per hit. It
  will print `false_positive` for every hit on any instance, which is a
  true statement about the database and a misleading one about the list;
  `WarninglistCategory` (analyst-profile phase 6) is the V1 map that
  exists because of this, and §7.1 decides whether the card reads it.

---

## 3. What is actually left

Read out of the four templates on 2026-09-13, not out of a plan. Nine
keys, three panels and one frame.

| Surface | Fixture key | What it draws |
|---|---|---|
| frame | `types` | one chip per MISP type, with its occurrence count; the chip filters the occurrence card |
| frame | `warninglists` | a *Warninglist hit* chip beside the value |
| frame | `value2_note` | *"1 occurrence has it as the second half of a `domain\|ip`"* |
| frame | `facts` | the six-cell fact strip, each cell linking to a tab |
| frame | `pivots` | the *Pivot to* rail — **withdrawn, D1** |
| `value_occurrences` | `occurrences` | rows shaped like a `fetchAttributes` result, nine columns |
| `value_occurrences` | `occurrence_stats` | `total`, `shown`, `events`, `orgs`, `deleted`; `hidden` **withdrawn, D3** |
| `value_context` | `tags` | tags grouped by taxonomy, with conflict flags and ordinal scales |
| `value_context` | `galaxies` | cluster name, kind, and occurrences attributed |
| `value_lifecycle` | `warninglists`, `warninglists_checked` | the hit lines, and *"N lists checked"* |
| `value_lifecycle` | `correlations` | the count — **withdrawn, D2** — and the over-correlating flag |

Three keys the frame reads are **already live** and are listed so nobody
converts them twice: `counts` (`forTabCounts`, phase 9's tab-pill work),
`verdict` (the assessment behind the pill) and `value` itself.

---

## 4. The frame

### 4.1 One read, one stated count — T1

`ValueProfile::forFrame($user, $value)` returns `types`, `warninglists`,
`warninglists_checked`, `value2_note` and `facts`. One method rather than
four calls in the controller, for the reason D5 gives: this is the
synchronous path, and four reads that each look cheap is how a page load
grows a second without anyone deciding it should.

The controller keeps `forTabCounts` and the assessment call as they are.
`view()` then pays, per load:

| Read | Q | Scales with |
|---|---|---|
| `forTabCounts` | 2 | nothing — two aggregates |
| the assessment (tab pill) | 9–27 | organisations, not occurrences |
| `forFrame` | **to be measured** | see below |

Expected shape of `forFrame`'s count, to be confirmed by §9 rather than
asserted here: one aggregate for the four occurrence facts, one group-by
for the types, one `hitsFor` (`Q=1`, measured at 10,187 rows on
`8.8.8.8`), one `enabledCount`, and one aggregate for the sightings fact.
The `value2` note may ride on the types group-by (§4.4).

### 4.2 The type chips — T2

`Value::typesFor($user, $value)` returns `[['type' => 'ip-dst',
'count' => 7], …]`, most common first, which is the array the frame
already walks. Its docblock says the frame carries the fixture's version
of the same shape, so this is a substitution with no template change.

The chip's slug is derived in the template and must go on matching the
`row_class_callable` slug in `value_occurrences` — a MISP type can hold
characters a class name cannot (`domain|ip`), and the two forms have to
agree or a chip selects nothing. Unchanged by this phase, and a §9 check.

**A value with no occurrence the viewer may see correctly has no type**,
rather than a guessed one — `typesFor` says so explicitly. The banner
then renders the value with no chips, which is the right reading of a
value that exists in the URL and nowhere the reader can look.

### 4.3 The warninglist chip — T3

`ValueWarninglistTool::hitsFor($warninglist, $pairs)` where `$pairs` is
the value and its types. Returns, per value, a list of `id`, `name`,
`category` and `matched`. The chip needs `name` only.

### 4.4 The `value2` note — T4

The fixture's note is *"1 occurrence has it as the second half of a
`domain|ip`"*. It is a real and useful disclosure: `Value::conditionsFor`
matches `value1` **or** `value2`, so a page about `8.8.8.8` legitimately
includes rows whose attribute value is `evil.example|8.8.8.8`, and a
reader who does not know that will read the occurrence count as wrong.
The Assessment tab's audit hit exactly this on `23.227.38.32`, *"reaching
the value through `value2` on a `domain|ip`"*.

Two ways to derive it, and the phase picks one at build time:

1. **From the types group-by.** A composite type in `typesFor`'s answer
   implies the match may be on either half. Free, but imprecise: it
   cannot say *how many* occurrences, and `domain|ip` appearing does not
   prove this value was the second half.
2. **Its own small aggregate** — a count grouped on whether `value2`
   matched. One extra query on the synchronous path, exact.

Recommend 2, and state its cost. An approximate disclosure about which
rows are on the page is worse than none: the note exists to make a count
trustworthy.

### 4.5 The fact strip — T5

Six cells. **Four come from one aggregate** —
`Value::occurrenceSummaryFor` returns `occurrences`, `events`, `orgs`,
`oldest` and `newest` in a single row, measured at 4 ms on `443`
(48,255 occurrences) where materialising rows to count them cost 617 ms.

| Cell | Value | Sub | Source |
|---|---|---|---|
| First seen | `oldest`, via `OBSERVED_FROM` | relative age | `occurrenceSummaryFor` |
| Last seen | `newest`, via `OBSERVED_AT` | relative age | `occurrenceSummaryFor` |
| Occurrences | `occurrences` | *"N types"* | `occurrenceSummaryFor` + `typesFor` |
| Events | `events` | *"N published"* | `occurrenceSummaryFor` + `recordSummaryFor` |
| Organisations | `orgs` | *"CIRCL + 3"* | `occurrenceSummaryFor` |
| Sightings | total | *"1 false positive"* | the sightings aggregate |

Three things to settle in the build:

- **D4's absent dates.** `oldest` and `newest` come back `null` when no
  occurrence dates itself, which is the common case. The cell says so in
  words — the Assessment tab's clock band already has the pattern, where
  *"where a value has no `first_seen` the card says so in words and does
  not pretend the age is measured."* It does not fall back to
  `Attribute.timestamp`.
- **The *N published* sub** is the only fact needing a second aggregate.
  `recordSummaryFor` already computes the published share for the quality
  ledger, over the same join. If it cannot be had for free, the sub is
  dropped rather than bought — a sub-label is not worth a query.
- **The organisations cell links to the Assessment tab** in the fixture
  (`'tab' => 'verdict'`). The tab id is still `verdict`; the **label** is
  Assessment. Check the anchor against the live registry rather than the
  fixture, since D11's rename moved what the reader sees and deliberately
  not the address.

---

## 5. The occurrence card

### 5.1 The rows — T7

`ValueProfile::forOccurrences($user, $value)` over
`fetchAttributesSimple` (D7), with `contain` covering what the nine
columns read: `Event` (id, info, Orgc), `Object` (id, name),
`AttributeTag` → `Tag`, and `SharingGroup` for the distribution column.

**Events resolve in one call, never one per event** — §14.4's only
committed performance rule. Where event metadata is needed beyond what
the attribute contain supplies, it is one `Event::fetchSimpleEvents` for
all N.

The cap is small and fixed. This is the preview, not the table: the
header already says *"Showing X of Y occurrences"* and offers *Open full
table*, which is the Occurrences tab at 300 rows with facets, sorting and
paging. A cap between 10 and 25 is the design's intent — the fixture
shows 6 of 10 — and the build states the number it picks.

**`MispAttribute::fetchAttributes` resolves organisations one query at a
time** (§14.10) — `orgs_cache` memoises per id but fetches each with its
own `find('first')`. A capped preview spanning nine organisations pays
nine selects. At this cap that is tolerable and it is *stated*, not
discovered later; if the count offends, the fix is a batched org read in
the facade, not a smaller cap.

### 5.2 The stats — T8

`total`, `events` and `orgs` come from `occurrenceSummaryFor` — the same
call the fact strip makes in a *different request*, so both pay for it.
They agree because the aggregate is deterministic.

`shown` is what the cap returned. `deleted` is the count of soft-deleted
rows among them, which drives the *Include N soft-deleted* toggle — and
which only exists because D7 chose the fetcher that does not silently
drop them.

`hidden` is **withdrawn** (D3).

---

## 6. The context card

The card with no tab behind it, and the only panel in this phase whose
shape is not already produced somewhere on the page.

### 6.1 Tags, grouped by taxonomy — T9

`Value::ownTagsFor($user, $value, $eventIds)` returns `tag name => tag`
(id, name, colour, `is_galaxy`) plus per-event occurrence counts,
**grouped in SQL** rather than materialised — a value can occur 48,255
times and the answer is a handful of names either way. `$eventIds` comes
from `occurrenceEventsFor`, which is what makes the ACL argument short.

From that the facade builds the template's shape: split on `is_galaxy`
(§6.3), group the rest by taxonomy via `Taxonomy::splitTagToComponents`,
sum the per-event counts into the per-tag `count`, and set `conflict`
where one taxonomy contributes two or more distinct tags that are
mutually exclusive readings — which is the card's whole argument for
grouping (*"two events putting `tlp:amber` and `tlp:green` on the same
value is a fact about the value, and a flat list hides it"*).

**One thing the reader does not supply: the per-tag organisations.** The
tag tooltip reads *"On N occurrences, from CIRCL, …"* and `ownTagsFor`
returns events, not orgs. Either map events to `Orgc` through the
`occurrenceEventsFor` answer already in hand — free if that answer
carries the org, one `fetchSimpleEvents` if not — or drop the *from* half
of the tooltip. Decide in the build; do not invent the orgs from the
event ids.

### 6.2 The ordinal scale — T10

Where a taxonomy is ordinal, the group renders as a position on a scale
(`position` of `of`, with a `reading` and a `label`) instead of a string
nobody reads. Live, that is the taxonomy's own `numerical_value`.

There is precedent in this corpus rather than a new mechanism:
`ValueTrustTool` reads `admiralty-scale`'s `numerical_value` for the
org-trust grades, including the detail that the shipped taxonomy has
**seven** grades where a reader assumes six. Reuse that reading; do not
hardcode a scale table.

A taxonomy that is not enabled on the instance supplies no numerical
values, and the group falls back to the flat tag list. That is the same
shape as a non-ordinal taxonomy and needs no separate state.

### 6.3 Galaxies — T11

`is_galaxy` finds the candidates; `fetchGalaxyClusters` decides which of
them the viewer may know about (D6). The card draws `name`, `kind` and
`n` (occurrences attributed), so the cluster read has to supply the kind
— the galaxy the cluster belongs to — and not only the name.

A cluster the viewer may not see is **absent**, with nothing said about
it. D3's rule is not specific to occurrences.

---

## 7. The Lifecycle card's last two lines

Its first third — *is the record still current* — went live with the
Analyst Profile's phase 5 and is untouched here.

### 7.1 The warninglist line — T12

`ValueWarninglistTool` again (D8), and it supplies all three things the
line prints: `hitsFor` gives `name` and `category`, the versions read
gives `version`, and `enabledCount` gives the *"N lists checked"* figure
— whose docblock names this very surface, noting the strip *"has printed
'84 lists checked' under 'No warninglist hit' since phase 7."*

**A miss is as informative as a hit**, which is why the count of lists
checked is printed at all: *no hit* and *not checked* are different
claims and the card already distinguishes them.

**The category is the open question** (§2's inherited hazard). Every
shipped list imports as `false_positive` because none sets the key, so
*"category false_positive"* will print on every hit on every instance.
Three options: print the stored value and be accurate about the database;
print `WarninglistCategory`'s V1 map, which is what the Assessment tab's
warninglist band reads; or drop the category from this line and leave it
to that band. Recommend the second — two surfaces on one page saying
different categories for one hit is the cross-panel contradiction this
corpus has now been bitten by three times.

### 7.2 The correlation line — T13

The count is withdrawn (D2). What the line becomes is the flag, drawn
only when it is set: a value MISP has marked over-correlating is one
whose correlations mean nothing, and that is a statement worth a line.
When the flag is clear the line says nothing rather than saying *0
correlations*, which would be false — the correlations exist, they are
just not counted here.

`ValueProfile` already produces the flag (the Relationships tab reads it,
and the assessment's evidence budget gives up on it).

**This line is also a live contradiction risk.** The Assessment tab's
clock stands down on an over-correlating value because its rows were not
read, and the Sightings tab's relevance card does not — which was one of
phase 9's three sharpest findings. Whatever this line says, §9 checks it
against those two on `github.com`.

---

## 8. What cannot be converted

### 8.1 The pivot rail — T6

D1 withdraws it. The alternative weighed, and rejected **for this phase**
rather than forever:

A rail re-founded on pivots the database can resolve — the other half of
a composite value (`domain|ip` → the domain), values sharing an event
with this one, the object this occurrence sits in. Those are real,
ACL-able and already reachable: `neighbourRowsFor` and
`occurrenceObjectIdsFor` exist. But it is a **different feature**: a new
design, a new set of chips, and a navigation surface whose usefulness has
never been argued anywhere in this corpus. Converting a rail means making
the drawn thing true; this would mean drawing a different thing and
calling it a conversion. It belongs in its own phase, with its own
argument, and the Relationships tab is the better home for most of it.

Deleting the element rather than leaving it inert is deliberate: an inert
chip is an unfinished promise to an analyst, which is **D23**'s rule from
the editor — *the page offers only what is implemented*.

### 8.2 And one that can, but not here

*Verdict over time* — the Overview's mirror of a series the page does not
store. Already settled by the Assessment tab's own wiring: the page
stores nothing and phase 10 stores a current row rather than a series, so
the card draws the relevance runway instead, reconstructed from dates.
Nothing for this phase to do; recorded so it is not re-opened.

---

## 9. Verification — planned

The shape follows phase 9's: assertions over HTTP against a running
instance, re-run per value, plus a browser pass for the things HTTP
cannot see.

**The values**, chosen to exercise the branches rather than to look
representative:

| Value | Why |
|---|---|
| `8.8.8.8` | the flagship: 10 occurrences, 8 organisations, contested, a warninglist hit |
| `443` | 162,539 occurrences — the cap, the aggregate costs, and the toggle |
| `github.com` | over-correlating: §7.2's line, and the clock contradiction |
| `23.227.38.32` | reached through `value2` on a `domain\|ip` — §4.4's note |
| a value with one occurrence and no tags | every empty state in one request |

**What must be checked, not assumed:**

1. **The strip against the panels.** *Occurrences* equals the occurrence
   card's `total`; *Events* and *Organisations* equal what the card's
   subtitle says; *Sightings* equals the sightings card. Different
   requests, and the cross-panel check is the one that has caught three
   defects in this corpus.
2. **The frame's query count**, measured rather than predicted (§4.1),
   with the assessment call's own 9–27 separated out.
3. **D4's absent dates** on a value with no `first_seen` — the cell says
   so in words, and no row-write date appears anywhere on the strip.
4. **The type chip round trip** — a chip's slug selects rows in the card,
   and `domain|ip` is among the values tested.
5. **The soft-deleted toggle** actually has rows to reveal, which means
   verifying on a value that has some; `fetchAttributesSimple` is the
   reason they are there at all (D7).
6. **Galaxy clusters as a non-privileged reader** — a cluster withheld by
   `fetchGalaxyClusters` is absent and unmentioned (D6, D3).
7. **The warninglist category** agrees with the Assessment tab's band for
   the same hit (§7.1).
8. **The over-correlating line** against the Assessment tab's clock and
   the Sightings tab's relevance card on `github.com` (§7.2).
9. **Both themes** (§14.9 row 8), and the harness asserts a custom
   property resolves before it asserts any colour — an unstyled page
   passes a colour check for the wrong reason.

---

## 10. The three concepts — T14

The survey's starting verdict for this tab is *yes* on all three. One is
already delivered.

**Feeds and sync servers — done, by phase 24.** `value_external` is the
card the survey named as this concept's home; it went live with the
Relationships tab's fourth section, reading one `forExternal` so the card
counts what the section lists. Nothing for this phase. The permission
gate the survey required (`perm_view_feed_correlations`, which
`Feed::searchCaches()` does not apply itself) went with it.

**Proposals — the decision the survey asked for.** The occurrence card is
a preview of a table that already carries a per-row proposal badge, and
phase 22 still owes standalone proposal rows. The question here is
narrow: does the *preview* carry the badge its full table carries? Say
yes if it is free — the proposals-per-row query already exists in
`forOccurrenceTable` — and no with a reason if it is a sixth query for a
card that shows ten rows. Do not invent a third rendering.

**Event reports — the count.** The survey routes the count to Overview
and the list to Collaboration, and the list is built (phase 26's third
panel). The count is a small aggregate over the value's events. Where it
goes is the open question: the fact strip is full at six cells, and the
context card is about labels rather than narrative. Candidates are a
seventh fact cell or a line on the Collaboration preview card — which
already mirrors the tab that holds the list, and is the answer this phase
should prefer.

---

## 11. Deferred, with the cost named

- **The pivot rail's resolvable successor** (§8.1). Cost: the page loses
  a navigation surface it has never actually had. No reader loses
  anything that worked.
- **A batched organisation read** behind `fetchAttributes` (§5.1). Cost:
  N selects for N organisations on the occurrence preview, bounded by the
  cap. It is a MISP-wide defect, not this page's, and every attribute
  index pays it.
- **The *N published* sub** on the Events fact, if `recordSummaryFor`
  cannot supply it free (§4.5). Cost: one sub-label.
- **The per-tag organisations** in the context tooltip, if the event→org
  map is not already in hand (§6.1). Cost: the tooltip says how many
  occurrences but not whose.

---

## 12. The board rows — T15

Two records, and this phase is the last to add to either.

- **§14.12** — three rows are dashes today and this phase fills all
  three: `viewOccurrences`, `viewContext`, and **the board's own first
  row, `view`** — the full page, which has carried an empty row since
  the board was written and is the only synchronous read on it.
  `viewLifecycle` is *"partly"* and becomes plain built. That leaves the
  board with no unconverted endpoint: **29 of 32 rows read live data
  today and 26 of them carry their numbers**, and when this phase closes
  it is 32 and — §9 permitting — 29. The three that stay unnumbered are
  `viewAnalystPreview`, `viewRelationReferences` and
  `viewRelationExternal`, none of them this phase's to record.
- **§14.13** — the phase row, and the campaign's own status line. When
  this closes, `ValueProfileFixture` has no reader in `ValuesController`
  and §14.8's unit-test double is all it is.

---

## 13. What each surface is fed, and by which read

| Surface | Method | Reads | New? |
|---|---|---|---|
| frame | `ValueProfile::forFrame` | `typesFor`, `occurrenceSummaryFor`, `recordSummaryFor`, `ValueWarninglistTool`, the sightings aggregate, the `value2` count | **facade new; readers exist except the `value2` count** |
| `value_occurrences` | `ValueProfile::forOccurrences` | `fetchAttributesSimple`, `occurrenceSummaryFor` | **facade new; readers exist** |
| `value_context` | `ValueProfile::forContext` | `occurrenceEventsFor`, `ownTagsFor`, `Taxonomy::splitTagToComponents`, `fetchGalaxyClusters` | **facade new; the taxonomy grouping and the scale are new work** |
| `value_lifecycle` | `ValueProfile::forLifecycle` (extend) | `forRelevance` (live), `ValueWarninglistTool`, the over-correlating flag (live) | **extended, not new** |

Three of the four are assembly. The one genuinely new piece of reasoning
in this phase is §6 — grouping a value's tags by taxonomy, deciding when
a taxonomy contradicts itself, and rendering an ordinal one as a
position. That is the part to build first and verify hardest, and it is
the only part with no other surface on the page to check itself against.


---

## 14. The build log

Built 2026-09-14, against the running instance the whole way rather than
at the end of it. Every number below was read off a rendered page or a
query, and the five findings are in the order they surfaced.

**61 checks in [`29-overview-harness.php`](29-overview-harness.php)**,
which covers the two folding tools and no database — they take no
`$user` and issue no query, so every rule they carry is assertable with
arrays. The SQL is checked by §14.9 instead.

### 14.1 The strip lost a cell and gained a warning

Planned as six cells from one aggregate plus a second for the sightings
total. It ships as **five from one**.

**The sightings cell is not built**, and the reasoning is
`forTabCounts`' own, which had already refused the matching tab badge:
a sighting count has to be the viewer's, `Sightings_policy` hides whole
reports, and getting the viewer's number means running the policy over
fetched rows. That docblock nominated this phase to fix it — *"worth
doing when the Overview's phase converts the frame, since the fact
strip's `%d sightings` line needs exactly the same number"* — and the
fix does not exist. `Sighting::createConditionsByAttributes` builds the
policy as **SQL** rather than as PHP, so it could be counted instead of
fetched; what it keys on is the attribute id set, so `443` would have to
materialise 48,255 ids before the cheap count could run. No number is
better than a wrong one, and the Sightings tab states the real one a
click away.

**The dates gained a warning that turned out to be the common case.**
D4 anticipated an absent date; what the chain actually does is fall
through to `Attribute.timestamp`, so `oldest` and `newest` are never
null and a caller printing them bare states a row write as a sighting.
`occurrenceSummaryFor` grew `dated_from` and `dated_at` — each counting
the occurrences that declare the column *leading its own chain* — and
the cell says *record date, not observed* when the count is zero. **On
`8.8.8.8` both are zero**: the page's flagship value has no declared
observation date at all, so this is the ordinary rendering rather than
an edge case. `443` has both and reads *9 years ago* and *1 month ago*.

**And the organisations cell was pointing at a tab that no longer
exists.** The fixture sent it to `#tab-verdict`; D11 renamed the tab id
to `assessment` eleven days ago and the fixture was never followed. The
live cell links to `#tab-assessment`, and the harness asserts it,
because this is the second dead thing found in the fixture's frame after
`occurrence_acl_note`.

### 14.2 The card counted one page and labelled it a total

The §14.4 trap, caught on the first render. `ValueStatsTool::
occurrenceStats` derives `events` and `orgs` by walking the rows it was
handed, which is exact while every row is in hand and becomes a count of
one page the moment a cap bites. The tab's cap is 300 and rarely bites;
**this card's is 25 and bites constantly** — `8.8.8.8` has 26
occurrences across 20 events, and the card headed itself *19 events*
beside a fact strip reading *20*.

Both numbers now come from the aggregate that already ran for the total,
so the card, the strip and the tab read one query's answer rather than
three tallies that ought to agree. `shown` stays the row count, which is
the one number on that line that is about the page.

**The Occurrences tab has the same construction and so the same defect
above 300 occurrences** — `443` has 48,255. It is `forOccurrenceTable`'s
to fix, the fix is this one, and this phase hands it on rather than
changing a built and verified panel from here.

### 14.3 The `value2` note is exact, and it is not `8.8.8.8` that needed it

§4.4 weighed deriving it from the type group-by against its own
aggregate and recommended the aggregate. Built that way:
`Value::value2CountFor` counts occurrences matching on `value2` with
`value1` **not** equal, which is what makes it a disclosure rather than
a tally — a composite whose `value1` is the literal `A|B` with an empty
`value2` matches on the first half and is not a second-half row.

The instance moved the example. `8.8.8.8` reaches nothing through
`value2` and draws no note; **`443` reaches 47,860 rows that way** and
reads *"47,860 occurrences have it as the second half of a
ip-dst|port"*. §9's value list named `23.227.38.32` for this and `443`
is the better case by three orders of magnitude.

### 14.4 The context card was a 2.9 MB fragment

The finding that reshaped the phase. The first build scoped tags through
`occurrenceEventsFor`, read them with `ownTagsFor`, and resolved every
carrying event's creator organisation with one `fetchSimpleEvents` so
each tag could name who applied it. On `8.8.8.8` — 20 events, 7 tags —
that is a 7 KB card. On **`443` it is 1,844 events and 3,860 distinct
tags**, which `ownTagsFor` returns as 42,039 grouped rows and the card
rendered in full: **2,951,173 bytes**, for a panel whose heading is a
summary.

This is the tier-3 shape §14.4 forbids, in result size rather than in
query count, and it was invisible to every check that does not measure
one. Three changes:

- **`Value::topTagsFor`** — a new reader grouping by tag alone, ordered
  by occurrence count, capped. `ownTagsFor` groups by tag *and* event
  because the neighbourhood table needs a label's reach per event, and
  a `LIMIT` over those pairs would cut a tag's own rows in half and
  report a short count. It asks for one row more than the card draws, so
  *there are more* is answered by the fetch rather than by a second
  aggregate.
- **`CONTEXT_TAG_CAP = 60`**, stated on the panel when it bites, because
  a cap is not a permission. The card fell to **48,757 bytes** on `443`
  — sixty times smaller — and is untouched on every ordinary value.
- **The per-tag organisations went**, and they are the part worth
  missing least. Neither `attribute_tags` nor `event_tags` records who
  applied a tag, so that list was never *who said this*, only *whose
  events carry it* — a weaker claim than the tooltip was making. §6.1
  had already offered this as the alternative to a second query; the
  size is what settled it.

**No scale is drawn on a capped read at all.** A position means *one tag
of this dimension is present*, and a truncated list cannot tell that
from *one that was read*.

### 14.5 Two bugs in the ordinal scale, and the instance found both

§6.2 was the part with no other surface to check itself against, and it
shipped wrong twice before it shipped right.

**The order was inverted.** The taxonomy's numbers run best-high —
`admiralty-scale` gives *Confirmed by other sources* 100 and *Improbable*
0 — and sorting ascending drew a bar that fills up as a source gets less
reliable. It sorts descending, so position 1 is the top of the scale,
which is also the reading the fixture's `B — 2 of 6` assumed.

**And every numerically-keyed taxonomy drew *position 1*.** PHP casts a
numeric-string array key to an integer, so `array_search('2', [5, 4, 3,
6, 2, 1], true)` is `false`, `false + 1` is `1`, and
`admiralty-scale:information-credibility` — keyed `1` to `6` — reported
position 1 whatever it was tagged. Caught on `sage.png`, which renders
*2 — Probably true* and drew *(1 of 6)*; it reads *(2 of 6)* now. The
keys are compared as strings and a search that fails returns no scale
rather than the first position.

**The denominator is what the taxonomy holds, not what a reader
assumes.** `source-reliability` has **seven** grades — `ValueTrustTool`
had to correct the same assumption in the profile's trust map — and two
pairs tie on `numerical_value` (`c` with `f`, `e` with `g`). PHP 8's
sort is stable, so a tie keeps the taxonomy's own order.

**On this instance only entry-level scales can draw.** `tlp`, `type` and
`workflow` carry **no** `numerical_value` on any predicate, and
`admiralty-scale` carries them on all 13 entries — so `tlp:amber` draws
a flat tag list and the predicate-level path, though built and
asserted, has nothing live to render. `position()` returning null unless
*every* member is numbered is what makes that silent rather than wrong.

### 14.6 Two shapes the real tag data broke

Neither is a taxonomy in the sense the card was designed around, and
both are on the page's flagship value.

**Freetext tags are not taxonomies of one.** `8.8.8.8` carries
`asyncrat`, `c2`, `Gh0stRAT`, `historicalandnew` and
`mightcontainvariantsofasyncrat` — five tags in no namespace at all,
which the first cut headed as five groups. They share one group now,
headed *Not in a taxonomy*.

**And a namespace is matched case-insensitively.** The same value
carries `PAP:RED` while the taxonomy is stored as `pap`. MISP's columns
collate `utf8mb3_bin` and `Taxonomy::getTaxonomyForTag` compares
`LOWER()` on both sides for exactly this reason; keyed by the raw
spelling, `PAP:RED` and `pap:amber` would head two groups and neither
would find its taxonomy. The fold is lowercase throughout.

### 14.7 What the warninglist line cost, and what it bought

Built through `verdictWarninglist` — the Assessment tab's own resolver —
so both surfaces run one hit through the same four-step category order
and the same override map. Verified on `8.8.8.8`, in one session: the
Lifecycle card says *List of known IPv4 public DNS resolvers · version
20240615 · category false_positive* and the tab's band says *v20240615 ·
category false_positive · matched by CIDR 8.8.8.8/32*. Same list, same
version, same category, and both say *8 lists checked*.

The banner chip does **not** take that path. It needs names only, and
the categories cost the profile; it calls `hitsFor` with the same pairs,
so the two cannot disagree about which lists matched — two readings of
one answer rather than two answers.

**§14.10's hazard is confirmed on the page.** The category prints
`false_positive`, because no shipped list sets one. It is true of this
list — a public-DNS-resolver list *is* a false-positive list — and the
tab's band already says in words what the category does and does not
claim.

### 14.8 The three concepts, as recorded

- **Feeds and sync servers — already delivered**, by phase 24's
  `value_external`. Nothing for this phase.
- **Proposals — no, and the reason is the panel.** The occurrence card
  runs `attachProposalCounts` like the table it previews, so every row
  carries its badge; what this phase does not add is a standalone
  proposal row, which is phase 22's outstanding half and belongs with
  the table that can paginate them.
- **Event reports — deferred, and the cost is a count.** §10 recommended
  the Collaboration preview card over a seventh fact cell. That card is
  phase 26's and live; adding a line to it is an amendment to a built
  panel rather than this phase's conversion, and it is named here so it
  is not lost.

### 14.9 Verification, as it ran

Over HTTP against the running instance, logged in as site admin, on
**five values chosen for their branches** rather than for looking
representative:

| Value | What it exercises | What it showed |
|---|---|---|
| `8.8.8.8` | the flagship | 26 occurrences, 20 events, 8 orgs; both dates undeclared; a warninglist hit; freetext and `PAP:RED` tags |
| `443` | the heaviest | 48,255 occurrences, 1,844 events, 3,860 tags; the `value2` note; the tag cap |
| `0.0.0.0` | over-correlating | the flag line draws, with no count beside it; 33,110 occurrences |
| `2a0c:5cc0:1:1::293c:1518` | soft-deleted rows | *Showing 25 of 33* with *Include 24 soft-deleted*, and the strip agreeing at 33 |
| `sage.png` | an ordinal taxonomy | the scale, and both of §14.5's bugs |
| `1.162.239.42` | galaxy clusters | two clusters named, two galaxy tags resolving to none and absent |

**Cross-panel, which is what has caught every contradiction on this
page:** the strip's occurrence, event and organisation counts against
the occurrence card's subtitle on all five, in separate requests; the
Lifecycle card's warninglist against the Assessment tab's band (§14.7);
and the card's total against `occurrenceCountFor`'s own SQL, run
directly against the database.

**Both themes.** The one piece of new markup is the cap note, which
reuses `vp-filter-note` — already themed, and built from
`--bs-border-color`, `--bs-body-bg`, `--bs-body-color` and `--primary`,
so it takes whatever the theme sets. Everything else on these four
surfaces is markup that was already verified in both.

**What is not verified here.** The ACL, on any panel: every read goes
through `buildConditions($user)` and the fetchers §14.4 sanctions, and
the verification ran as a site admin, so what it proves is that the
right method was called rather than that the method is right. §14.8 of
the contract says that verification is manual and per phase; this phase
inherits readers whose ACL earlier phases verified, and the two it adds
— `topTagsFor` and `value2CountFor` — build their conditions the same
way every other aggregate in `Value` does.

### 14.10 What this leaves

`ValueProfileFixture` has **no reader in `ValuesController`**. It is
what §14.8 said it would become: a unit-test double, its arrays handed
straight to elements so a template can render with no database. The keys
this phase withdrew — `pivots`, the correlation count, `hidden`,
`occurrence_acl_note` — came out of the *templates*; the fixture may
keep them.

Two things are handed on:

1. **`forOccurrenceTable`'s page-counted `events` and `orgs`** (§14.2),
   which is the same defect at a cap of 300.
2. **A sightings count that can be told at page-load cost** (§14.1),
   which needs `Sighting` growing a count that applies the policy in SQL
   *without* keying on the attribute id set. The fact strip and the tab
   badge both take it the day it exists.
