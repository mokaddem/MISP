# PRD: Analyst Profile — phase 9, wiring the Verdict tab live

**Building since 2026-09-13. The spine is in (§8) and five of the thirteen
keys with it (§9); eight keys, the copy pass, the rename and the hero are
not (§10).** Depends on phases 1–5.
Phase 6 is not a hard prerequisite but the conflict escalation cannot fire
without it. **Every phase it depends on is built**, and phase 8 landed
beside them.

**Read back against the shipped code 2026-09-13**, because this document was
written before the engine existed and D11 renamed its subject afterwards. What
the read-back found is §7; the corrections are in place above it, and the
sentence each one replaced is quoted there rather than deleted silently. The
short version: the panel count was wrong, the composition rule is already
built, and the swap this phase describes as *replacing the fixture* is
thirteen keys short of a swap.

This is the payoff phase: the tab that has been blocked since the skeleton pass
reads real data, and the copy the page has been asserting for six months
becomes either true or retracted.

**D11 (2026-09-03) grows this phase's copy pass and renames its subject**
([`12-assessment.md`](12-assessment.md)): the Verdict tab becomes the
Assessment tab, the hero composes *lean · relevance · quality* (the one open
point D11 leaves this phase), the fixture's verdict arrays re-express on
three axes, and the template/constant renames land here. The panel list and
the contract below hold, read through D11's rename map.

## 1. What ships

`ValueProfile::forVerdict()` and friends replacing the fixture behind the
Verdict tab and the Overview's verdict card, plus every shipped string this
feature has made wrong.

**Less of it is new than this reads.** `ValueVerdictTool::verdictFor()` already
returns a verdict-shaped array — `disposition`, `score`, `confidence`,
`ledger`, `tug`, `composition`, `not_counted`, `changers`, `profile`,
`profile_id`, `computed_at`, and the three axes beside them — and the editor's
simulator has been rendering off it since 8a. `ValueProfile::forVerdict()` is
the facade that is missing, not the computation. What the facade cannot supply
on its own is §2.2's thirteen keys: the templates read them, the fixture
carries them, and no phase 1–8 code produces them. **That list is the phase**,
and three of the thirteen are read unguarded, so the first swap does not render
an empty card — it errors.

It follows the contract every other live phase followed —
[`../value-profile-live/00-contract.md`](../value-profile-live/00-contract.md)
§14 — and it is the phase that finally moves the last blocked rows on §14.12's
conversion board.

## 2. The panels

**Three endpoints, four top-level elements, eleven sub-elements** — fifteen
`value_verdict*.ctp` files, all currently fixture-backed. *"Seven endpoints"*
stood here until the read-back counted them; §14.12's conversion board has it
right at four blocked rows, because the board is keyed by endpoint and element
and this table was keyed by neither.

| Action | Top-level element | Renders |
|---|---|---|
| `viewVerdict` | `value_verdict` | The agreeing layout. Includes `_meta`, `_warninglist`, `_ledger`, `_orgs` |
| `viewVerdict` | `value_verdict_conflicted` | The conflicted layout, picked on disposition. Includes `_meta`, `_warninglist`, `_orgs` |
| `viewVerdictAside` | `value_verdict_aside` | The rail. Agreeing branch: `_composition`, `_curves`, `_not_counted`, `_changers`. Conflicted branch: `_resolve`, `_case_composition`, `_opinions`, `_curves`, `_not_counted` |
| `viewVerdictCard` | `value_verdict_card` | The Overview rail card — **`../value-profile-page.md` §1.4 names this one as blocked on the verdict engine specifically** |

The eleven sub-elements are `_meta`, `_warninglist`, `_ledger`, `_orgs`,
`_composition`, `_case_composition`, `_curves`, `_not_counted`, `_changers`,
`_opinions`, `_resolve`. Four of them — `_meta`, `_warninglist`, `_curves`,
`_changers` — were not in this table at all, and three of those four carry a
row in §3's copy pass, which is how a document can schedule a change to a file
its own panel list does not mention.

**One `$context` build per request, and agreement across requests comes from
determinism rather than from sharing.** The sentence here used to be *"one
`$context` build, seven readers … so a page load does not compute the verdict
seven times"*, and it conflated two things the read-back had to separate. The
three endpoints are three lazy HTTP requests — `viewVerdict`,
`viewVerdictAside`, `viewVerdictCard` each land in their own PHP process — so
there is no build for them to share. What is shared is *within* a request: the
aside renders five sub-elements off one verdict, and `03-signals.md` §2.2's
aggregate plus `AnalystProfile::resolveFor()`'s per-request memo make that one
pass.

The card and the tab **must** still agree, and what makes them agree is that
`assess()` takes a context and a profile and returns the same array every time.
Any future caching sits behind that seam or the guarantee is gone. A card and a
tab on one page disagreeing about the same value is the hazard
`../value-profile-page.md` §1.4 records phase 23 converting the Overview's
sightings card out of order to avoid, and verification item 3 is where it is
asserted rather than assumed.

### 2.1 The composition card

`value_verdict_composition` renders segments by group plus a total, and the
fixture's malicious value gives them as *Reporting 37, Sightings 24,
Attribution 19, Lifecycle 18, Signals against −14* — with a comment noting the
last segment is *"the two downward signals, collected"* and explicitly not the
contradictions, because *"labelling this line after them would send a reader
tracing −14 to the wrong rows."*

Under phase 2's mechanism this is derivable, and it checks out exactly against
the malicious value's own ledger rows:

| Segment | Derivation | Fixture |
|---|---|---|
| Reporting | `28 + 9` | 37 |
| Sightings | `24` (the `−6` goes to *against*) | 24 |
| Attribution | `14 + 5` | 19 |
| Lifecycle | `12 + 6` (the `−8` goes to *against*) | 18 |
| Signals against | `−6 + −8` | −14 |
| **Total** | `37 + 24 + 19 + 18 − 14` | **84** |

So the rule is: group the fired rows by group, sum the **positives** per group,
and collect **all** the negatives into one segment. No separate logic and no
second source of truth — the composition card is a faithful regrouping of the
ledger, and the same invariant holds one level up.

**This is already built**, and the read-back's job here was to check it rather
than to specify it. `ValueStatsTool::verdictComposition()` walks the grouped
ledger, sums the positives per group, collects the negatives into one *Signals
against* segment, and drops a group that earned nothing — the rule above,
exactly, and the live contract's §14.2 tool table already names
`ValueStatsTool` as owning *"the verdict's composition segments"*. Two
corrections fall out of reading it:

- **The key is `kind`, not `group`.** The profile's entry carries `group` and
  `ValueVerdictTool::anchor()` writes it onto the ledger row as `kind`. A
  specification naming the wrong key is how an implementer writes a
  `$row['group']` that is always empty, and every segment then lands in one
  unnamed bucket.
- **The segment label will read *Reporting*, not *Reporting breadth*.** The
  segment is labelled with the group's own name, so the composition card and
  the ledger heading above it say the same word. The fixture said *Reporting*
  in the ledger and *Reporting breadth* in the composition — two names for one
  group, which is exactly what a single source of truth removes. The live page
  is right and the table above is quoting the fixture; nothing needs changing
  in code, and the phase should not "fix" the card back to the fixture's
  wording.

### 2.2 What the engine emits, and the thirteen keys that have no producer

The fifteen templates read **25 distinct keys** off `$valueProfile['verdict']`.
`ValueVerdictTool::verdict()` emits **23**, and the two sets overlap in twelve.
This table is the phase, and it is what *"replacing the fixture"* actually
costs.

**Twelve the engine already answers**, so the swap carries them for free:
`disposition`, `score`, `confidence` (D11's rename shim), `ledger`, `tug`,
`composition`, `not_counted`, `changers`, `profile`, `profile_id`, `rule`,
`computed_at`.

**Eleven the engine emits that nothing on the page reads yet**: `lean`,
`derived_lean`, `relevance`, `band`, `quality`, `stances`, `polarity`,
`rule_errors`, `signals`, `profile_revision`, `as_of`. These are D11's three
axes and their workings, waiting for the hero. The rename shim exists so the
templates need not read them on day one — `ValueVerdictTool::LEAN_DISPOSITION`
carries the comment *"Dropped when phase 9 renames them"*, which makes its
removal this phase's work and not a later tidy-up.

**Thirteen the templates read that no phase 1–8 code produces:**

| Key | Read by | What could produce it |
|---|---|---|
| `summary` | `_card`, `value_verdict`, `_conflicted` — **all three unguarded** | Nothing. This is D11's open point: the hero's prose over lean · relevance · quality |
| `orgs` | `value_verdict` (**unguarded**), `_orgs` | Derivable — `ValueLeanTool::stancesFor()` counts stances per organisation, `ValueTrustTool` holds the grades, phase 6 built the per-org sighting tallies |
| `cases` | `_conflicted` (**unguarded**), `_case_composition` | Nothing. The conflicted layout's two opposed arguments; phase 3's escalations produce a `rule`, not a pair of cases |
| `conflicts` | `_ledger` | Nothing. The contradictions that survived, under the ledger |
| `ambiguities` | `_conflicted` | Nothing |
| `warninglist` | `value_verdict`, `_conflicted`, `_warninglist` | Derivable — phase 6's `WarninglistCategory` and the context's hits |
| `curves`, `curves_span`, `curves_note` | `_curves` | `ValueRelevanceTool::runwaySeries()`, which is what §3 already schedules the NIDS line to become |
| `composition_note` | `_composition` | §3's copy row — the sentence about the profile in force |
| `changer_actions` | `_changers` | Nothing. `ValueChangersTool` returns `axis`, `direction` and `text`; the fixture's buttons are writes |
| `opinions` | `_opinions` | The rows exist — `ValueProfile` already reads analyst notes and opinions for Collaboration and Timeline — but no histogram aggregate does |
| `resolutions` | `_resolve` | **Out of scope, and cleanly so.** The card's own docblock says every control is disabled because the page does not write; `01-profile.md` §7 says this feature writes nothing. The card is `?? array()`-guarded, so it simply never renders until `../value-profile-writes.md` lands |

**The thirteen split four ways**, and only the last group is open design:

- **Five are derivable from tools phases 5 and 6 already shipped** — `orgs`
  from the stances, the grades and the per-org tallies; `warninglist` from
  `WarninglistCategory`; `curves`, `curves_span` and `curves_note` from
  `runwaySeries()`. Work, but no decisions. **Built 2026-09-13 (§9)** — with
  one decision after all, since *a verdict over time* turned out not to be
  derivable at all and the card draws shelf life instead (§9.4).
- **One is a copy change this document already schedules** — `composition_note`
  is §3's *"An instance admin can edit the profile"* row.
- **One is out of scope and should stay empty** — `resolutions`, per
  `01-profile.md` §7.
- **Six have no producer and no obvious derivation**: `summary`, `cases`,
  `conflicts`, `ambiguities`, `changer_actions`, `opinions`. The hero's prose
  and the conflicted layout are the phase's real work, and `summary` is D11's
  own open point rather than an oversight.

**Three of the thirteen are read without a guard**, and that is the finding
that changes the build order. Ten degrade to an empty card because the template
wrote `?? array()` or `?? null`; `summary`, `orgs` and `cases` did not. So the
first swap does not produce a page with some cards missing — it produces
notices on the agreeing layout, on the conflicted layout, and on the Overview
card.

**The sparse value breaks first**, which is the case verification item 4
covers. `_card` reads `summary` only when there are no ledger rows to list, and
a value with no occurrence this viewer can see is exactly what
`ValueVerdictTool::nothingToAssess()` returns — an empty ledger, band `none`,
quality 0. The one path with no signals is the one path that needs the key
nothing produces.

**The tug's dead wedge is also this phase's, and one word covers two
quantities.** `tug()` returns `unresolved` as a hard zero with the comment that
it *"stays in the return as a zero until the layout stops reading it"*, and
`value_verdict_conflicted.ctp` reads it twice — the bar's total and the middle
wedge's width, both of which go to zero. The foot **under that same wedge**
prints *"%s unresolved"* from `count($ambiguities)`, a different quantity with
no producer at all. So the bar and its own label disagree about what
*unresolved* counts, and the fixture hid it by giving both a value. Retiring
the wedge and the word together is the honest fix.

**And `cases` is read positionally.** The tug's two feet are
`count($cases[0]['rows'])` and `count($cases[1]['rows'])` — the layout assumes
exactly two cases, in order, each carrying `rows`. An empty `cases` is not a
degraded render but an undefined index, and a producer returning one case or
three would break it as surely as returning none. Whatever fills this key owes
the template a pair.

## 3. Shipped copy this phase changes

`01-profile.md` §6 is the inventory. Each is either corrected or retracted; a
claim left standing that the code no longer honours is what
`prd-tracks-reality` exists to prevent.

| String | Becomes |
|---|---|
| `Weighting profile default-v3` | `Analyst profile <name>`, linked (`09-editor.md` §5.1). **`default-v3` → `default-v1`** — the fixture's string was an artboard literal and a real shipped profile must not claim a version history it does not have |
| *"An instance admin can edit the profile; the tab always names the one in force."* | The second half stays and becomes true. The first half is wrong under D3 — most readers see a profile they or their org own. Replace with something that states the scope of the profile in force |
| *"3 or more false-positive sightings from 2+ orgs → drops to SUSPICIOUS"* | Derived per axis (`04-dispositions.md` §8), rephrased in band-and-lean vocabulary — SUSPICIOUS is dropped, not added (D11) |
| *"No sighting for 45 days → decay takes the score under 50"* | Restated in TTL terms — there is no decay (D7) |
| *"Weights come from the default-v3 profile"* | Names the real profile |
| `NIDS decay score` dashed curve, `curves_note` | The TTL runway (`06-staleness.md` §4.2). `ValueRelevanceTool::runwaySeries()` is built and unread; the fixture still carries the dashed line at three places |
| ~~`attribution.galaxy` band, `moderate` on one value and `strong` on another~~ | **Void — closed by D16, not by this phase.** The band was removed rather than made consistent, and the read-back counted the remnants: `'band' =>` appears **0** times in the fixture, `['band']` **0** times in the fifteen templates, `"band"` **0** times in `default-v1.json`. There is nothing left to correct |

**Two more rows of `01-profile.md` §6 are already closed**, both by phase 5 and
neither marked there: the per-model decay bars (`value_sighting_decay.ctp`,
161 + 259 lines) went with the decay panel, and the decay curve overlay at
`value_sighting_chart.ctp:447` is gone — the only surviving mention of decay in
that file is two lines of docblock explaining why no threshold is drawn. §6
should be annotated rather than left implying this phase owes them.

**Every line reference in §6 and §3.1 has drifted**, because the templates grew
after the inventory was taken. `value_verdict_meta.ctp:40` is now **:67**,
`value_verdict_card.ctp:56` is now **:77**, and §3.1's `:38` is now **:44**. The
strings themselves are all still there — `default-v3` five times in the
fixture, *"Weights come from the default-v3 profile"* twice, all three
SUSPICIOUS strings, the `NIDS decay score` label three times — so the inventory
is right about what is wrong and only wrong about where.

### 3.1 The one the page has always got right

`value_verdict_meta.ctp:44`'s conditional — *"a verdict reached from no signal
at all does not name a profile"* — needs no change and must not be lost.
`01-profile.md` §5.3. It is the only piece of this whole surface that was
already correct about a thing that did not exist yet.

**It has since grown the other half of itself.** The same block now links the
name to the editor when `profile_id` is set and prints it as plain text when it
is not, on 8c's argument that *"a link to a profile the page did not actually
use would answer it wrongly"*. The two conditions are not the same one: the
outer guard is the ledger, §5.3's rule, and the link is decided inside it by
`profile_id`. A ledger cannot be non-empty without a profile — rows exist only
where a profile enabled a signal — so within the guard the plain-text branch is
reached only by a profile with no row id, which is precisely the case it was
written for. **This is one of the two 8c deliverables that becomes visible only
when this phase runs**, since a fixture verdict has no profile id to link.

## 4. Q9 — does the standing caveat now state both reasons?

**Half of it was answered by phase 4, and against the direction recorded
here.** The half that remains is genuinely open.

`../value-profile-live/00-contract.md` §14.6 established that every count on the
page is the viewer's, and that the verdict is therefore never *"the community's
view"*, only *"the view available to you"*. This section assumed the page
should *say* so, and pointed at the `acl_note`: *"4 occurrences you cannot see
were excluded from this assessment."*

**That caveat no longer exists** ([`05-exclusions.md`](05-exclusions.md) §7.2).
MISP discloses what a reader is allowed to see; the people using it know it,
and a per-value line about permissions tells them nothing while hinting at
records they have no business knowing about. Both the counted version and the
de-numbered version that briefly replaced it are gone. §14.6 remains true of
the *computation* — every count is the viewer's — and stops being something
the page narrates.

So the ACL half of Q9 is closed by deletion. What is left is the profile half,
and it is a different question with a different answer available: under D3,
per-user profiles are a **second, independent** reason two readers differ about
the same value, and unlike permissions that one is worth stating — the hero
already names the profile in force, which is the caveat in its most useful
form. Q9 becomes: *is naming the profile enough, or does the difference need a
sentence?*

Three options:

- **A.** Extend the standing caveat to name both — the viewer's permissions and
  the viewer's profile. One sentence, two causes, on every verdict.
- **B.** Leave it. The hero already names the profile, so the profile's
  influence is on screen; the caveat is specifically about *hidden* data, and a
  profile is not hidden.
- **C.** Say it once, contextually — when the profile in force is **not** the
  instance default, add a line saying so.

**Recommendation: C.** It is true precisely when it matters and silent when it
does not, and the commonest case by far is an analyst on their org's or the
instance's profile, for whom a warning about per-user divergence is noise. B
under-states it: the hero naming a profile does not tell a reader that a
colleague would see a different number. A is honest but adds a permanent
sentence to a hero that already carries four.

**The read-back leaves C standing and makes it cheap.** Two things moved under
this section since it was written. The name in the meta block is now a **link**
to the profile that weighted the value (§3.1), which is a stronger form of B
than B described — a reader can go and look at the thing — and still not an
answer to *would a colleague see this differently*, so it does not displace C.
And the test C needs costs nothing: `resolveFor()` already returns the row the
verdict was computed against, and the scope is on it — `user_id`, `org_id` and
`default` are the three columns that decide which owner won, so *"the profile
in force is not the instance default"* is a field read on an array the page is
holding, not a second query. The call is memoised per request besides. What C
still owes is its sentence; the wording is the open part, not the mechanism.

## 5. Verification

Follows `../value-profile-live/00-contract.md` §14.9's requirements for a live
phase, plus:

1. `parallel-lint`.
2. All four demo values render their fixture disposition against the live
   engine, and each ledger sums to its own score, **read off the rendered
   page** rather than computed in a test.
3. The Overview card and the Verdict tab agree on disposition, score and
   profile name for all four. One computation, asserted.
4. A value with no occurrences: sparse page, UNKNOWN, no ledger, **no profile
   name** (§3.1).
5. A value visible to one user and not another: two different scores, each
   internally consistent, **and neither page saying why**. This is §14.6 made
   real — the computation is per-viewer and the page does not narrate the
   reason (`05-exclusions.md` §7.2).
6. Two users with different profiles on the same value: two scores, both
   ledgers summing, both heroes naming their own profile. This is the feature's
   headline claim (`01-profile.md` §1.1) and it has never been demonstrable.
7. Q9's chosen treatment rendered.
8. The conflicted value reaching CONFLICTED, with its ledger **populated** —
   the fixture's empty ledger was a convenience and phase 3 §5 says the rows
   are what the tug bar is built from.
9. Nine-tab bar at 1920, 1600 and 992 px. `../value-profile-page.md` §6.1
   records it wraps to two rows below 1600 and that this was reported rather
   than restyled; a badge change must not make it worse. **Still nine**, counted
   at the read-back: Overview, Verdict, Occurrences, Sightings, Relationships,
   Enrichment, Collaboration, Timeline, History.
10. Dark theme, both layouts, with **`--vp-conflict`** in place. This item said
    `--vp-susp`, and **there is no such token anywhere in the codebase** — not
    in `value-palette.css`, not in a template, not in a stylesheet. D11
    dissolved SUSPICIOUS and the contested lean is painted with
    `--vp-conflict`, which is what the palette actually defines, in both
    themes.
11. The harness caveat from `../value-profile-page.md` §6.1: panels are checked
    in headless Chrome against saved fragments and the CSS fetch fails
    intermittently, so assert `--vp-mal` resolves before asserting any colour.
12. **No undefined index on any of the four layouts**, asserted with PHP
    notices escalated rather than by looking at the page. §2.2's three
    unguarded keys are the whole reason: a notice-suppressed render of the
    sparse value looks like a clean render of a sparse value.
13. **`ValueVerdictTool::LEAN_DISPOSITION` is gone**, and with it the
    `disposition` / `score` / `confidence` keys `verdict()` writes for
    templates that have not been through the copy pass. The shim carries
    *"Dropped when phase 9 renames them"* in its own docblock; leaving it is
    how a rename becomes permanent.
14. **The tug renders no zero wedge and no zero label** (§2.2), on the
    conflicted value, in both themes.
15. **`value_verdict_resolve` renders nothing at all**, and this is the pass
    rather than the failure. The card asks for writes the feature does not do;
    an empty `resolutions` is the correct answer until
    `../value-profile-writes.md` lands, and asserting it keeps the card from
    being quietly filled with something derived.

## 6. Out of scope

- Storing or caching a verdict. Render-time only; phase 10 changes that
  knowingly (`01-profile.md` §5.5).
- The enrichment badge (phase 7, blocked).
- Linking values from the event view's attribute table. Still deferred per
  `../value-profile-page.md` §5 — though the reason given there (*"with two
  demo values almost every such link would land on the sparse state"*) is
  weaker now that the page is mostly live, so it is worth re-asking after this
  phase rather than after this document.

## 7. The read-back, 2026-09-13

This document was written before phases 2–8 existed. Every claim in it was
checked against the shipped code before the phase started, because a
specification written against an imagined engine is exactly the thing
`prd-tracks-reality` exists to catch, and this one had six phases and a
renamed subject land underneath it.

**What held.** §2.1's arithmetic is still exact — the malicious fixture's
composition is `37 / 24 / 19 / 18 / −14` summing to `84`, and the comment about
the last segment not being the contradictions is still on the array. The four
demo values are still four. The tab bar is still nine. Every string §3 promises
to correct is still on screen: `default-v3` five times in the fixture, *"Weights
come from the default-v3 profile"* twice, all three SUSPICIOUS strings, the
`NIDS decay score` label three times. §3.1's conditional is still right, and
`ValueDisposition::TREATMENTS` carries exactly the four dispositions the
engine's rename map targets, with no SUSPICIOUS among them.

**What did not.**

### 7.1 The panel list counted endpoints it did not have

*"Seven endpoints, all currently fixture-backed"* named three endpoints, three
top-level elements and one sub-element as though they were one kind of thing,
and left four sub-elements out — including `_curves` and `_changers`, which
§3's own copy pass schedules changes to. The board in
`../value-profile-live/00-contract.md` §14.12 had it right the whole time at
four blocked rows. Corrected in §2, with the real include tree.

### 7.2 Thirteen keys have no producer, and three of them are unguarded

The finding that changes the phase's size. The templates read 25 keys; the
engine emits 23; they overlap in twelve. §2.2 is the table. Ten of the thirteen
missing keys degrade to an empty card, which is what *"replacing the fixture"*
implies — but `summary`, `orgs` and `cases` are read with no `??` at all, and
`cases` is read positionally as `$cases[0]` and `$cases[1]`. The first swap
errors rather than degrades, and **the sparse value errors first**, because
`_card` reads `summary` only on the no-ledger path that
`nothingToAssess()` returns.

### 7.3 The composition card is built, and it renames a group

§2.1 specified a derivation that `ValueStatsTool::verdictComposition()` already
implements, down to dropping a group that earned nothing. Two things fall out
of reading it rather than specifying it again: the ledger key is `kind`, not
`group` — the profile entry's `group` is written onto the row as `kind` by
`anchor()`, so an implementer following §2.1 literally gets an empty bucket —
and the segment takes the group's own name, so the card will read *Reporting*
where the fixture read *Reporting breadth*. The fixture had two names for one
group; the engine has one. The live wording is the correct one.

### 7.4 One `$context` build, and three separate HTTP requests

*"…so a page load does not compute the verdict seven times"* described a
sharing that cannot happen: the card, the tab and the rail are three lazy
requests in three PHP processes. What makes them agree is that `assess()` is
deterministic over a context and a profile, which is a different guarantee with
a different failure mode — it survives caching only if the cache sits behind
that seam. Restated in §2.

### 7.5 A verification item named a token that does not exist

Item 10 asked for `--vp-susp` *"from phase 3"*. There is no `--vp-susp` in any
stylesheet, template or PHP file in the repository. D11 dissolved SUSPICIOUS
before phase 3 shipped, and the contested lean is painted `--vp-conflict`,
which `value-palette.css` defines in both themes. An unverifiable item is worse
than a missing one, because it passes by being skipped.

### 7.6 The copy inventory is right about what and wrong about where

Every line reference in `01-profile.md` §6 and in §3.1 has drifted as the
templates grew: `value_verdict_meta.ctp:40` → `:67`, `value_verdict_card.ctp:56`
→ `:77`, §3.1's `:38` → `:44`. Two of §6's rows are also already closed, by
phase 5 and not by this phase — the per-model decay bars and the
`value_sighting_chart.ctp:447` overlay both went with the decay panel. And one
row of §3 is **void**: the `attribution.galaxy` band cannot be made consistent
because D16 removed the field, with `'band' =>` now appearing zero times in the
fixture, zero times in the fifteen templates and zero times in
`default-v1.json`.

### 7.7 The rename is scheduled by a docblock and nowhere else

`ValueVerdictTool::LEAN_DISPOSITION` is D11's rename map with the comment
*"Dropped when phase 9 renames them"*, and `verdict()` writes `disposition`,
`score` and `confidence` beside the three axes for the same reason. Nothing in
this document said so before the read-back. A shim whose removal is recorded
only in the file it lives in is a shim that stays.

### 7.8 The tug's wedge and the tug's label count different things

`tug()` returns `unresolved` as a hard zero — the fixture's third wedge was
never derivable — and the conflicted layout reads it for the bar total and the
middle wedge's width. The foot directly under that wedge prints
*"%s unresolved"* from `count($ambiguities)`, which is a different quantity and
has no producer either. One word, two sources, and the fixture concealed it by
supplying both. They retire together.

### 7.9 What the read-back did not need to find

Three of the eight findings above are corrections to this document and
five are facts about the code, and none of them is a defect in the
engine. `ValueVerdictTool` does what phases 2 to 6 said it does; the
distance between it and a live tab is display plumbing and thirteen
keys, not arithmetic. That is worth stating because a read-back this
long reads like a list of things that are wrong.

## 8. The spine, built 2026-09-13

The first increment: the three endpoints answer from the engine. The
thirteen keys are still thirteen — this pass produced none of them — so
what is live is the ledger, the score, the band, the composition, the
falsifiability lines, the exclusions and the profile in force, and what
is dark is every card that needs a key nothing computes.

**What shipped.**

| | |
|---|---|
| `ValueProfile::forVerdict()` | The facade. One engine call, the envelope every `value_verdict*.ctp` reads |
| `ValueProfile::VERDICT_UNPRODUCED` | §2.2's thirteen keys, defaulted in one place, merged *under* the engine's array so a producer landing later overwrites a line rather than needing a second edit |
| `ValuesController::viewVerdict`, `viewVerdictAside`, `viewVerdictCard` | Off `__profileFor()` and onto `__verdictFor()`. Four Overview panels still read the fixture and are the Value Profile campaign's own |
| `ValueDisposition::hasConflictedLayout()` | Which layout a verdict gets, in one place because two readers of it disagreeing is a page that contradicts itself (§8.1) |
| `value_verdict_meta.ctp` | Formats `computed_at`, which the engine emits as unix seconds (§8.2) |
| `value_verdict.ctp`, `_conflicted`, `_card` | The hero paragraph drawn only where there is one; the score bar clamped (§8.3) |

**Verified.** 828 checks across the corpus's eight standalone harnesses,
unchanged and all green; 97 in `09c-wiring-harness.php`, which renders
`value_verdict_meta` and is the one harness that could see these edits;
and **43 new checks over HTTP** in
[`10-wiring-http-probe.sh`](10-wiring-http-probe.sh), against the dev
instance, as the reader.

The three that were the point of the exercise:

- **The ledger on the page sums to the score on the page.** Read out of
  the markup rather than out of an array: `8.8.8.8` prints rows of
  `+28, +2, +24, −20, −7, −6, −38, +4, +4, +8`, and the hero prints
  `−1`. §5 item 2, and the first time `01-profile.md` §5.1 has been
  asserted anywhere a reader can see it.
- **The rail prints the same total as the tab** — *"How −1 was reached"*
  beside `−1 / 100` — from a separate request that shares nothing with
  it. So does the Overview card: CONFLICTED, −1, `default-v1`, all
  three agreeing (§5 item 3).
- **A value nobody has reported names no profile**, on the tab and on
  the card, which says *Nothing to weigh* instead. §3.1's conditional
  has been right since the skeleton pass and had nothing to be right
  about until now (§5 item 4).

`8.8.8.8` reads **CONFLICTED, quality −1, band low, under `default-v1`**,
with the ledger naming 8 independent organisations, 53 sightings from 6
orgs, 4 false-positive sightings from 3, no galaxy on any occurrence, a
known-benign warninglist hit at −38, and *"4 months without a month of
silence"*. That is phase 3 §11.5's reading, reached through the page
rather than through a probe.

### 8.1 The rail branched on the disposition and the tab had stopped

**Found by the probe, one commit after it was introduced, and by this
document's own §2.2.** Routing a contested value to the agreeing layout
(because `cases` has no producer) is a controller decision, and
`value_verdict_aside.ctp` was making the *old* decision independently —
`$conflicted = $verdict['disposition'] === 'CONFLICTED'`. So `8.8.8.8`
drew a full ledger in the main column beside a rail that picked the
conflicted branch, whose five cards are all keyed to unproduced data and
all rendered nothing: **a 200 with a zero-byte body**.

This is §2's own hazard arriving by the shortest possible route — not
two computations disagreeing, but two *readings of one computation*
disagreeing about which layout it gets. The fix is one definition on
`ValueDisposition` that both callers read, and the probe now asserts the
rail is non-empty on a scored value, which is what caught it.

Worth keeping: a zero-byte 200 passed every assertion the first probe
made. It answered, it carried no notice, it carried no fixture string.
*"Nothing rendered"* is indistinguishable from *"nothing to render"*
unless something asserts a size — the same shape as phase 7 §7.1, where
21 assertions held with the modules service down.

### 8.2 The top of the tab printed a unix timestamp

`computed_at` is emitted as unix seconds because it is the key phase
10's materialisation stores and compares. The fixture emitted `null`,
so `value_verdict_meta.ctp`'s `?? date('Y-m-d H:i:s')` fallback had been
doing the formatting for every render since the skeleton pass — and the
moment a real value arrived, the fallback stopped firing and the page
printed `Computed at render, 1789291994`.

A default that formats is not the same as a formatter. The template
formats it now, and the probe asserts no run of nine or more digits
follows *"Computed at render,"* — which is a check about the shape a
reader sees rather than about the key being present.

### 8.3 A quality can be negative, and the fixture's could not

The fixture's four values score `84`, `91` and two nulls. The engine's
quality is the ledger's exact sum, so a record whose evidence disputes
its own lean nets below zero — `8.8.8.8` closes at `−1` — and the hero
drew its bar as `style="width: -1%"`. A negative width is an invalid
declaration, which browsers drop rather than clamp, leaving the fill at
whatever width it inherits instead of at empty.

The bar is clamped and the number is not, because the number is the
assessment. `value_disposition.ctp`'s docblock said `0-100` and now says
what is actually true.

**What this leaves open is a reading, not a bug.** `−1 / 100` is
honest arithmetic and an odd sentence, and the hero is exactly where
D11's open point lives. It belongs to the composition pass rather than
to a clamp.

### 8.4 The agreement check passed by reading nothing

The probe's first run reported `ok profile named ()`. Both extractors
had missed their markup — the tab wraps the name in a span inside the
anchor and the card does not — and two empty strings compare equal. The
same run reported the score check passing for the same reason, because
`grep` is line-based and the tab prints the number on its own line
inside the span.

Both are fixed, and both now carry a second assertion that what was read
is a name and a number. This corpus has now recorded the shape four
times — phase 5 §7.5's stopped query log, phase 7 §7.1's unreachable
modules service, phase 7 §7.6's diagnostic counting its own prose, and
this — which is enough that a new probe should assume it until it has
proved otherwise.

## 9. The five derivable keys, built 2026-09-13

§2.2's first group, answered. `ValueProfile::verdictPanels()` is where the
display keys the engine does not emit are folded out of the context it
scored — **derived, never invented**, and it takes no `$user`, because
everything it reads was already scoped to the viewer (§14.5).

| Key | Producer | Notes |
|---|---|---|
| `orgs` | `verdictOrgTable()` | Name and occurrences from the context's org rows, sightings and false positives from `sightings.by_org` / `by_org_fp`, the stance from the two `to_ids` tallies, the grade from `ValueTrustTool::gradeFor()`. Widest reporter first |
| `warninglist` | `verdictWarninglistBand()` | The hit that resolved to the category the context settled on, plus `WarninglistCategory::note()` |
| `curves`, `curves_span`, `curves_note` | `verdictCurves()` | The relevance runway over 90 days, from `ValueRelevanceTool::runwaySeries()` |

**Eight keys remain** (§10). Five is what phases 5 and 6 made answerable and
the count has not moved since: nothing here produced `summary`, `cases`,
`conflicts`, `ambiguities`, `changer_actions` or `opinions`.

Verified by **six new checks** in `10-wiring-http-probe.sh`, 49 in total on a
value that hits a warninglist and 47 on one that does not — plus the eight
harnesses unchanged at 828 and `09c-wiring-harness.php` at 97.

### 9.1 The card and the argument count the same organisations

The probe's strongest new assertion, and the reason the table is folded from
the context rather than queried: *Who says what* lists **8** organisations for
`8.8.8.8`, and the ledger row beside it reads *"8 independent organisations
reported it"*. They are the same eight because `reporting.independent_orgs`
and this table read one context. A card beside an argument that counts
differently from it is the hazard this panel exists to avoid, and it is now
asserted on every run rather than argued for in a docblock.

### 9.2 `opinion` is the column with no source, and it says so

Five columns are fixed in the table and four of them fold cleanly. The fifth
is the per-organisation opinion, and **nothing aggregates one**: MISP holds
analyst opinions and this page already reads them for Collaboration and the
Timeline, but per organisation and per value is not computed anywhere.

It renders *none stated*, which the template already had a branch for. The
alternative was zero, and **zero is an opinion** — the strongest available
disagreement — so a value nobody has opined on would have read as a value
eight organisations thought worthless.

### 9.3 An ungraded organisation is `unrated`, not blank

`ValueTrustTool::gradeFor()` answers `null` for an organisation the analyst
has not graded, and `null` is not a grade. The column shows `unrated`, which
is the scale's own word for it and — this is the part that matters — **the
grade the engine actually weighted that organisation with**:
`ValueTrustTool::factor()` falls back to `factorForGrade($plan, UNRATED)` for
exactly the same rows. A blank cell would have implied a missing lookup; the
word says the analyst has expressed no opinion, which is true and is what the
ledger counted.

Found while writing it: `gradeFor()` takes the **whole context**, not the
trust block — `blockFrom()` reads `$context['trust']` itself, so handing it
the block makes it look for `trust.trust`, miss, and grade every organisation
null. That reads exactly like an empty map, which is also the true state of
this instance, so the bug and the correct answer were indistinguishable on the
page. Caught by reading `blockFrom()` rather than by the render.

### 9.4 There is no verdict over time, so the card draws shelf life

**§3's row said the `NIDS decay score` curve becomes the TTL runway. Building
it showed the other curve cannot survive either.** The card plotted two lines:
a dashed NIDS decay score, retired by D7, and a *synthesised verdict* over 90
days — a history of verdicts. Nothing has one. The page computes at render and
stores nothing (`01-profile.md` §5.5), so there is no yesterday's score to
plot, and phase 10's materialisation stores a current row rather than a
series. A 90-day quality history would mean re-scoring the value ninety times
against ninety reconstructed contexts, which is not a chart, it is a batch job.

The runway survives because it is **reconstructed from dates rather than from
stored scores** — `runwaySeries()` walks a day grid and asks what the shelf
life was on each one — which is precisely why one axis can be drawn backwards
and the other cannot. So the card is one line, titled *Shelf life*, and it is
also the only thing the Verdict tab says about the second of D11's three axes.

`06-staleness.md` §4.2 asked for *evidence strength against remaining shelf
life*, two quantities. It gets one, and the other half of the sentence is the
ledger and the composition sitting on the same rail.

### 9.5 Two panels, one axis, asserted across the gap

The chart's last point is today's shelf life, and the Sightings tab's
relevance card draws the same quantity as a bar. They are separate endpoints
in separate requests, and the probe now reads both and compares: **77 and 77**
on `8.8.8.8`, 18 and 18 on a value most of the way through its TTL, 0 and 0 on
an expired one.

This is asserted across the panels rather than inside one because phase 5
§7.2 shipped exactly this bug: 79% drawn under 46% printed, the assumed days
riding the series but not the bar. A check inside either panel would have
passed.

### 9.6 The version is not in the shape core caches

The band names the list version it matched against, and
`Warninglist::getEnabledAndCacheWarninglist()` selects `id, name, type,
category` and serialises *that* into redis — so `version` is not available
from the roster every match already comes out of. Widening core's field list
would change what every caller of `getEnabled()` deserialises, for one band.

`ValueWarninglistTool::versionsFor()` reads it instead: one keyed query on the
matched ids, made only where something matched, which is a minority of values
and always one already paying for `assignComments`. The first render drew
`v` with nothing after it, which is how this was found — a template printing a
prefix it has no value for.

## 10. What is still ahead

Two increments in, and none of what is left is blocked on anything
outside this corpus:

1. ~~**The five derivable keys**~~ — **done, §9.**
2. ~~**The copy pass**~~ — **done, §11.** Two edits; five rows closed by
   the fixture no longer being read.
3. ~~**D11's rename**~~ — **done, §12.** The tab is the Assessment tab,
   the shim is gone, and the page says `lean` / `quality` / `band`.
4. ~~**The hero**~~ — **done, §13.** `summary` composes the three axes
   in one sentence, and relevance reaches the tab in words for the
   first time.
5. ~~**The conflicted layout**~~ — **done, §14.** `8.8.8.8` draws it,
   the wedge is gone, and `hasConflictedLayout()` retired its own
   second condition.
6. ~~**The query counts**~~ — **done, §15.** 9–27 for the card, 12–44
   for the two endpoints that show an opinion, and the board's
   *converted, unmeasured* state is retired with them.
7. ~~**A per-organisation opinion aggregate**~~ — **done, §16.** Both
   panels read the Collaboration tab's union rather than a cheaper one
   of their own, which is what the spread in item 6 is.
8. **`changer_actions` stays empty, like `resolutions`** (§16.2). The
   fixture's three buttons are writes and `01-profile.md` §7 says this
   feature performs none.

Q9 is unchanged and still recommends C, now at a known cost (§4).


## 11. The copy pass, done 2026-09-13

§3's table, closed. It cost **two edits**, and the interesting part is
why the other five rows needed none.

| Row | Closed by |
|---|---|
| `Weighting profile default-v3` | An edit. `Analyst profile` in `value_verdict_card.ctp` and `value_verdict_meta.ctp`; the name and its link were already the resolved profile's, from §8 |
| *"An instance admin can edit the profile…"* | An edit. `verdictCompositionNote()`, §11.1 |
| *"Weights come from the default-v3 profile"* | The same note replaces the whole sentence |
| *"…→ drops to SUSPICIOUS"* | The tab stopped reading the fixture (§8) |
| *"No sighting for 45 days → decay takes the score under 50"* | The same — and `ValueChangersTool` writes the real one from the profile's TTL: *"No independent corroboration for 69 more days — the assessment expires"* |
| `NIDS decay score` dashed curve | §9.4, which retired both curves and drew shelf life |
| ~~`attribution.galaxy` band~~ | D16, before this phase |

### 11.1 The note says how far the profile reaches

The retracted sentence had two halves and they failed differently. *"The
tab always names the one in force"* was true, is now true of a real
profile, and is said one card away by `value_verdict_meta` — so
repeating it in the composition card was only repetition. *"An instance
admin can edit the profile"* is wrong under D3 for most readers, and
correcting it to *"you or an admin can edit it"* would have been a
sentence about permissions in a card about arithmetic.

What the note says instead is the thing the meta line **cannot** say:
which of D3's three scopes owns the profile, and therefore whose pages
an edit moves. A reader who disagrees with a weight is about to fork or
edit something, and *"editing it changes what you see here and nothing
anyone else sees"* is the fact that decides whether they should.

`AnalystProfile::scopeOf()` answers it from the row's own columns, so
`verdictPanels()` keeps §14.5's property of taking no `$user`:
`resolveFor()` returns only rows that already match the viewer, which is
what makes a bare `user_id` mean *this reader's* without a comparison.

### 11.2 Three rows closed by deleting the array they lived in

`SUSPICIOUS`, the 45-day decay changer and the NIDS curve were fixture
literals. Phase 9's spine replaced the array, not the strings — so the
strings are **still in `ValueProfileFixture.php`**, all of them, and
none of them can reach a reader.

This is why the inventory is now verified against rendered markup rather
than against a grep. A grep over `app/` reports `default-v3` five times
and SUSPICIOUS at three lines and concludes four rows are still owing;
the three endpoints print those four strings **zero** times between
them. `10-wiring-http-probe.sh` asserts the absence on every endpoint on
every run, which is the only form of this check that stays true as the
fixture is deleted piece by piece.

### 11.3 The label and the name are one assertion

Renaming the label is half a row. The other half is that the name beside
it is the profile that actually weighted these rows — which is what
makes it worth linking — and the probe now reads it out of the tab's
markup and compares it with the profile the rail's note names, **two
requests apart**: `default-v1` and `default-v1`. Same shape as §9.5, and
for the same reason: the two panels could only agree by computing the
same thing, and a check inside either would have passed regardless.

**66 checks** over HTTP, up from 49; the eight harnesses unchanged at
828.


## 12. D11's rename, done 2026-09-13

The Assessment tab exists. `ValueVerdictTool::LEAN_DISPOSITION` is gone,
and with it the three keys `verdict()` wrote beside the axes for
templates that had not caught up — so the page now reads `lean`,
`quality` and `band`, which are the names of the things the engine
actually computes.

| Was | Is | Where |
|---|---|---|
| the Verdict tab | the Assessment tab | the tab bar, the tab title, the Overview card's panel header, and `#tab-verdict` → `#tab-assessment` |
| `ValueDisposition` | `ValueLean`, keyed `threat` / `benign` / `contested` / `none` | one file, nine call sites |
| `MALICIOUS` / `BENIGN` / `CONFLICTED` / `UNKNOWN` | *Asserted threat* / *Asserted benign* / *Contested* / *Nothing asserted*, from `ValueLean::label()` | the hero, both layouts, the pill, the tab badge |
| `disposition` | `lean` | every template and the controller's layout branch |
| `score` | `quality` | the hero, the card, the composition card's heading |
| `confidence` (the bar) | `band` (the meter) | the Overview card |
| `value_disposition.ctp` | `value_lean.ctp` | the pill element |

**Not renamed, and deliberately.** `ValueVerdictTool`, the fifteen
`value_verdict*.ctp` filenames, the three `viewVerdict*` actions and
their URLs, and the `vp-vc-` / `vp-disposition-` CSS prefixes all keep
the old word. None of them is addressable by a reader, and every one of
them renames alongside `value_verdicts`, `includeVerdict` and
`minVerdictScore` — which is phase 10's table and REST surface
(`12-assessment.md` §5). Splitting one vocabulary across two migrations
costs more than carrying an internal name for one phase. **What a
reader reads is renamed now; what a reader cannot see renames with the
table it is named after.**

### 12.1 The tab bar contradicted the tab, and the rename is what found it

The Assessment tab's pill renders on the **synchronous** page build,
from the profile `ValuesController::view()` assembles — which is still
`ValueProfileFixture`'s frame, because §14.12 has the Overview's own
conversion as a later phase. It read `disposition` off that frame, and
after the rename there was no such key: the pill drew *Nothing
asserted* over a body reading *Contested*.

It had been wrong before the rename too, and quietly — the pill was
naming the **fixture's** verdict beside a tab naming the engine's, and
on `8.8.8.8` the two happened to be the same word. The rename turned a
silent disagreement into a visible one, which is the only reason it was
caught.

The fix is the one `counts` already had: `view()` overlays the real
assessment onto the fixture's frame. It costs **a fourth
`forVerdict()`** — three lazy endpoints each compute their own, and
there is nothing to share between four PHP processes (§2) — so the page
now pays one assessment to put a word in the tab bar. That is the price
of a badge that cannot be caught lying, and §14.12's `Q` column is
where it gets stated rather than assumed.

### 12.2 The old vocabulary inverts rather than degrades

`ValueLean::directionStyle()` decides on a single equality — *is this
lean benign* — so a call site still passing `BENIGN` does not fall back
to a neutral pair. It takes the **threat** branch, and every arrow on
that card points the wrong way: the row supporting a benign record is
painted in the colour of a threat.

That is why every call site had to move rather than most of them, and
`09c-wiring-harness.php` now asserts the inversion directly — the check
is worth more than a passing one, because it says what a missed call
site would have looked like. **98 checks** there, up from 97.

### 12.3 What the shim was really protecting

`07-reference-harness.php` asserted *"the word the templates still read
is CONFLICTED"*. Deleting the shim deleted the subject of that check, so
it was rewritten to the property underneath it: the engine emits exactly
one word for what the record asserts, and `ValueLean` is the only place
that turns it into English. **828 checks, unchanged.**

**67 checks** over HTTP, up from 66 — the new one reads the tab bar's
pill off the whole page and compares it with the tab body's lean, two
requests apart, which is §12.1 asserted rather than remembered.


## 13. The hero, built 2026-09-13

D11's one open point, closed: *three axes in one line without three
competing numbers* (`12-assessment.md` §7). `summary` is one sentence
of three clauses — what the record asserts, how much record there is,
and whether it still holds — and it is where **relevance arrives on
this tab in words**. Until now the second of D11's three axes reached
the Assessment tab only as a chart in the rail (§9.4); the hero, which
is the part of the page a reader actually reads, said nothing about it
at all.

`8.8.8.8` closes as:

> **Contested** · Quality low · −1 / 100
> *What is recorded here contradicts itself. The record behind that is
> thin, and it has 69 days of shelf life left.*

**One number, and it is the one the hero has nowhere else.** The
quality and its band are already printed beside the badge, so a
sentence repeating them would be D11's second and third competing
numbers. Days appear only here. The bands are **named, never
re-derived** — `thin` is D11 §3's own word for `low` — and
`runway_days` is printed, never recomputed, which is what keeps the
sentence, the relevance card and the rail's chart from drifting apart
the way phase 5 §7.2's bar and series did.

D11 §3's two other test readings fall out of the same builder: an old
well-attested hash reads *"reads as a threat … well evidenced, and its
shelf life ran out 400 days ago"* — **well-documented historic
threat**, the phrase the one-number design could not say — and a
late-encoded phishing URL reads *"thin, and its shelf life ran out 1
day ago — on a timeline nothing records"*, which is *asserted threat ·
thin record · likely over* in a sentence.

### 13.1 It is a tool, because only a tool can be asserted

`ValueSummaryTool` sits beside `ValueChangersTool` and for the same
reason: both turn a finished assessment into English, both are pure
functions of the array the engine returned, and §14.5 puts the
arithmetic in a tool and the queries in the model. Prose over an array
the model already holds is the tool's side of that line.

The practical half of that is coverage. **Three of the sentence's
branches are reachable on the verification instance and six are not**
— it has no `benign` lean, no `high` band, no `aging` value, and
nothing that expires today. A sentence whose rarest readings are never
rendered until a real analyst meets one is exactly the shape of copy
that ships wrong, so the branches are asserted in
`04-lean-bands-harness.php` against arrays built for the purpose:
**100 checks there became 111**, and two of the eleven are D11 §3's own
test values quoted back.

### 13.2 The two clauses that must not be assembled

A lean with nothing weighed behind it stops after one clause, and a
record with no clock to run stops after two. Both were sentences that
ended mid-phrase in the first draft — *"The record behind that is"*
with no band, and *"and"* with no shelf life — because the builder
assumed three clauses always existed. They are separate assertions in
the harness rather than one, since the two absences arrive from
different axes and a fix for either would hide the other.

The `none` lean is the third, and it is not a truncation but a
different sentence: *"Nothing you can see records this value, so there
is nothing to assess."* It is also **the branch `summary` is read
first** — `value_verdict_card.ctp` prints prose only where there are no
ledger rows to list instead, which §2.2 records as the reason the
sparse value was the one that broke.

### 13.3 A rule was being computed, given prose, and shown nowhere

Found by dumping the live assessment rather than by reading the page.
`8.8.8.8` fires `conflict:listed-vs-asserted`, and the escalation
carries prose written for exactly this moment: *"A warninglist marks
this as a false positive and 8 of 8 organisations report it as a threat
regardless. Both judgements are deliberate; the page will not pick
one."*

Nothing printed it. `value_verdict_meta` takes the rule as a parameter
and only `value_verdict_conflicted.ctp` passed one — and a contested
value renders the **agreeing** layout whenever `cases` is empty, which
is every contested value today (§7.8, `ValueLean::hasConflictedLayout`).
So the one sentence that said *why* the lean was contested was
unreachable on the only layout a contested value could reach.

`value_verdict.ctp` passes it now. It is not the hero's sentence and
should not be: the hero says the record contradicts itself, the meta
line says which rule found the contradiction, and the two are a
summary and its small print rather than two attempts at the same
thing.

**73 checks** over HTTP, up from 67 — including the sentence's days
against the relevance card's days, **69 and 69**, two requests apart.
The harnesses are at **839**, up from 828.


## 14. The contested layout, built 2026-09-13

`8.8.8.8` draws it. Three keys, one new tool, and the switch that had
been holding the layout shut since the skeleton pass —
`ValueLean::hasConflictedLayout()`'s second condition, *"and `cases` is
non-empty"* — now opens on its own, exactly as its docblock said it
would.

> **Contested**
> *What is recorded here contradicts itself. The record behind that is
> thin, and it has 69 days of shelf life left.*
> Threat case **70**  ·  **71** benign case  ·  6 signals / 4 signals
> Conflict rule: *A warninglist marks this as a false positive and 8 of
> 8 organisations report it as a threat regardless…*
> **Reads as a threat — 70** │ **Reads as benign — 71**
> ◆ Settled by rule, not by evidence — *2 organisations hold it both
> ways*

### 14.1 The cases are the ledger, read twice

Rule 7 re-anchors a contested ledger threat-signed before it is banded
(`04-dispositions.md` §2), which means **the ledger already holds both
arguments**: its positive rows are what says this is a threat and its
negative rows are what says it is not. `ValueContestedTool::casesFor()`
folds them apart and does nothing else. `04-dispositions.md` §5 asked
for exactly this — *two derivable quantities, no third bucket, no
separate computation* — and the payoff is that a reader can add up
either column by hand and arrive at the bar above it.

**The exact-sum invariant survives the layout change and gets stronger
in it.** The agreeing layout prints a ledger and a quality and the rows
have to sum to it; the contested one prints two totals and **no
quality at all**, because a single number would be the mean of two
incompatible readings. `support − dispute` is that number, and the
Overview card still prints it — in its own request, off its own
context. So the probe now asserts `70 − 71 = −1` **across two
templates and two requests**, which is a check neither could pass
alone.

**A pair or nothing.** The layout reads `$cases[0]` and `$cases[1]`
positionally and its tug has two feet, so one case or three throws
rather than degrades (§2.2). `casesFor()` returns two or zero, and the
zero has two causes: a lean that is not contested, and a contested lean
whose every row falls one way — which rule 7 can produce. Both fall
back to the agreeing layout, which carries a ledger and says the same
thing in the shape that fits it.

### 14.2 `conflicts` and `ambiguities` were never two facts

The finding that made them cheap. The fixture carried two keys and they
hold **one derivation shown in two places**: a contradiction the engine
settled by rule rather than by evidence belongs under the ledger on the
agreeing layout and under the two cases on the contested one, and it is
the same contradiction either way. One producer, handed out twice, and
the two cannot disagree.

What does *not* go in them is anything the ledger netted off — the
exact-sum invariant means a fact that moved the quality is already
visible as a row, and naming it again here would be double-counting in
prose. Only two facts on this page survive scoring without being
scored:

- **An organisation holding the value both ways.** Some of its
  occurrences carry `to_ids` and some do not. `ValueLeanTool` counts it
  with the asserters, because one occurrence carrying the flag is an
  assertion — and its other occurrences are netted off nowhere.
  `8.8.8.8` has two.
- **Warninglists disagreeing about the kind of listing.** When hits
  resolve to more than one category the context settles on
  `false_positive`, and the `known` reading is then carried by no
  signal at all.

**The heading had to change with them.** It read *"Unresolved — counted
for neither side"*, which is the one thing these items are not: a split
organisation **is** counted, and a reader taking that heading literally
would go looking for points that are in the column above. What is true
of all of them is that a rule decided where they went, so that is what
it says now.

### 14.3 A panel said `expired` while another said `current`

The sharpest finding of the phase, and it came out of §9.5's shape
rather than out of reading any code.

`github.com` is flagged by MISP as **over-correlating**, so the evidence
budget leaves its rows unfetched — which the Assessment tab says
plainly, in `not_counted`, for the four signals that could not run. What
nothing said is that **the relevance clock was degraded by the same
give-up**: with no sighting rows to read it fell back to
`Attribute.timestamp`, 153 days against a 120-day TTL, and the tab drew
*expired, 33 days over*. The Sightings tab reads the same value with no
budget, finds an independent sighting 56 days old, and draws *64 days
left*.

Two panels, one axis, opposite answers. Neither said so.

A clock missing its sighting half **can only run slow**, so the state it
produces can only be too stale — which is not a caveat, it is an answer
that is wrong in one direction. `ValueRelevanceTool::relevanceFor()`
now stands the axis down instead: no state, reason `rows_not_read`, no
chart, and the hero's sentence ends after the band. §13.2's *"a record
with no clock to run ends after the band"* was written a commit before
anything could reach it.

Deliberately narrower than the clock's own `rows_read`, which is also
false when a **sighting policy** hides rows. That case keeps its state
and its caveat on the relevance card: rows the reader may not see are a
caveat, rows nobody read are an absence.

### 14.4 The wedge and the word retired together

§7.8, closed. `tug()` returned five keys — `support`, `dispute`, two
D11-era aliases, and `unresolved` as a hard zero — and the layout drew
the zero as a striped middle wedge while the foot **directly beneath
it** printed *"%s unresolved"* from `count($ambiguities)`, a different
quantity with a different producer. One word over two sources, and the
fixture concealed it by supplying both.

The tug is two keys now, the bar is two wedges, and the feet are two
signal counts. What the middle foot was reaching for is a card of its
own further down the page, where it can say what each item is instead
of how many there are.

### 14.5 The scope note went missing on the layout it had never met

`composition_note` (§11.1) is drawn by `value_verdict_composition`,
which is the **agreeing** rail's card. The contested rail draws
`value_verdict_case_composition` instead — so the moment a value could
actually reach the contested layout, the sentence saying whose weights
these are stopped being drawn for it. It is on both cards now. A reader
who wants to argue with a weight needs to know whose it is on either
layout, and this is the kind of gap that only appears the first time a
branch is reachable.

**75 checks** over HTTP on the contested value and 73 on an agreeing
one, up from 73/71; the harnesses are at **862**, up from 839, with the
pair contract and both unresolved derivations asserted there rather
than on whichever value the instance happens to have.


## 15. The query counts, measured 2026-09-13

`../value-profile-live/00-contract.md` §14.12's four rows, filled.
`10-query-counts.php` is the measurement, counting the way every other
live phase counted — the datasource's own log, cleared between
endpoints, with the model and the profile store dropped so each run pays
what a cold request pays.

| Endpoint | Q | What it grows with |
|---|---|---|
| `viewVerdictCard` | **9–27** | organisations, not occurrences |
| `viewVerdict` | **12–44** | the same, **+2 to 27 for the analyst union** |
| `viewVerdictAside` | **12–44** | the same |

Measured across six values chosen to occupy different branches: a bare
one (10), an over-correlating one whose rows the budget leaves unread
(9), a two-organisation threat (13), a warninglist-hitting contested one
(20), and `8.8.8.8` at the top (27).

**The spread is one option, not one value.** The two endpoints that
show an opinion ask for the Collaboration tab's own union; the card,
which shows none, does not — §16 is why they ask rather than count for
themselves.

### 15.1 Three ways the harness lied before it told the truth

Worth recording, because a query counter that is wrong is worse than
none — it produces a number with a table under it.

- **The log is a ring buffer.** `getLog()` keeps the last 200
  statements, so a delta taken across `count($log)` stops growing when
  the buffer fills and then *shrinks*. A six-value run reported `3
  queries` and then `0` for endpoints that had just taken twenty. The
  count comes from `getLog()['count']`, which is cumulative; the log is
  read only for the per-table breakdown, and only its tail.
- **The profile resolution is memoised per request.** Leaving
  `AnalystProfile` in the registry between values handed every value
  after the first a lookup a real request pays for — the first read 27
  and the second 8, and one of the nineteen was the profile.
- **The harness changed the number it was measuring.**
  `Configure::write('CurrentUserId')` is needed for the analyst union to
  run in a console at all, and with it set from the start the card's own
  count came out one higher. It is scoped to the run that needs it.

### 15.2 An N+1 that is not this phase's

The breakdown shows `8.8.8.8` taking **five `organisations` statements**,
one per sighting organisation, each `WHERE id = N LIMIT 1`. They come
from core's `Sighting::listSightings`, which the four already-measured
Sightings rows share — which is why every one of them reads
*organisations, not occurrences* in the board's `Scales` column.

Recorded rather than fixed. The fix is in a core model four converted
endpoints depend on, and phase 9's business is the Assessment tab.

## 16. The opinion aggregate, built 2026-09-13

*Who says what*'s fifth column had no source (§9.2) and the contested
rail's histogram had no producer. Both have one now, and it is **the
Collaboration tab's own union** — `analystContext()`, unchanged, read
through an option the two endpoints that show an opinion pass and
`viewVerdictCard` does not.

`8.8.8.8` draws the histogram at **mean 72.5** over four opinions, which
is the number the Collaboration tab prints, and ADMIN's row in *Who says
what* reads **100** — its strongest, the same opinion at the top of its
lane one tab across.

### 16.1 The expensive one, on purpose

A cheaper aggregate was available and was the wrong answer. Opinions
anchored to the value's attributes alone is one query; the Collaboration
tab's union walks five anchor kinds over two tables at 7 to 28. On the
verification instance the difference is not academic — of 43 opinions,
**one** is on an attribute and 28 are on events, so the cheap version
would have drawn *no opinions* under a tab reading *four opinions from
one organisation*.

That is the bug §14.3 had just finished fixing on the relevance axis,
and §9.5's cross-panel check exists because phase 5 shipped it once
before that. Three instances of one class of defect in one corpus is
enough to pay for the union rather than approximate it. The probe now
reads the rail's mean and the Collaboration tab's mean **three requests
apart** and compares them.

What it costs is in §15 and on the board: 2 to 27 queries, and the whole
reason the assessment rows carry the widest spread on it.

### 16.2 `changer_actions` joins `resolutions`

The last of §2.2's thirteen, and it is **out of scope rather than
unbuilt**. The fixture's three buttons — *Mark false positive*, *Record
an opinion*, *Add a sighting* — are writes, and `01-profile.md` §7 says
this feature writes nothing. The key stays on the skeleton, the
template's `?? array()` guard keeps the row of buttons from rendering,
and neither is drawn as an inert promise — which is D23's rule applied
to the one surface D23 did not name.

So §2.2's thirteen close as **eleven produced, two deliberately empty**.
`resolutions` waits on `../value-profile-writes.md`; `changer_actions`
waits on the same thing, and both should be built by whatever phase
gives this page a write.

**77 checks** over HTTP on `8.8.8.8`, up from 75; the harnesses are
unchanged at **862** and the render harness at **98**.
