# Analyst Profile — executive summary

**Snapshot, 2026-09-13.** This file is the entry point for someone who has
not followed the corpus. It summarises; it decides nothing. The living state
table is [`01-profile.md`](01-profile.md) §1.4, the decisions index is
§2 there, and every claim below carries a pointer to the document that owns
it.

## What this is

MISP's Value Profile page displays an assessment of a value — what the
record asserts it is, whether that still matters, how much the record can be
trusted — and until 2026-09-13 **nothing computed any of it**. The **Analyst
Profile** is the configuration object the engine reads: a forkable JSON
document holding every judgement the scoring engine needs — signal weights, thresholds, exclusions,
TTLs, source trust, enrichment defaults — so the engine can be a mechanism
rather than a shipped opinion. An instance ships one default; an organisation
or an analyst forks it and edits their copy; exactly one is in force per
viewer (nearest owner wins). **Phases 1 to 7 — the store, the engine that
reads it, the lean and bands that turn its ledger into an assessment, the
exclusions that decide what the ledger may see, the relevance axis that
says whether any of it still matters, the reference data that says what
the analyst believes about their sources, and the enrichment modules it
declares — are built**, and so is **phase 8**, the editor — its contract
(8a), three cold prototypes of which one was picked (8b), and the wiring
of that design into pages (8c, 2026-09-11). **Phase 9 closed 2026-09-13**:
the Assessment tab reads a real profile, and the page that had been
displaying an assessment since the skeleton pass now computes the one it
displays. Phases 1 to 6 are every phase that can change a number; phase 7
changes none. Only phase 10 — the export gate — is still a
specification. The corpus is nineteen documents, phase by phase.

## The headline: the Assessment (D11)

The computed object is **not a maliciousness verdict** — an engine reading
MISP tables can only honestly measure *the record*, and "MALICIOUS 84"
claimed more. It is an **Assessment** on three axes
([`12-assessment.md`](12-assessment.md)):

| Axis | The question | Sourced from |
|---|---|---|
| **Lean** | what does the record assert this is? — `threat` / `benign` / `contested` / `none` | `to_ids` stance per org, warninglist categories |
| **Relevance** | does it still matter today? — `current` / `aging` / `expired` / `timeline uncertain`, with uncertainty also carried as a flag | per-type TTL against last independent corroboration, temporal precision |
| **Quality** | how much can the record be trusted? — the number, with a ledger | the scored signals: corroboration, trust, attribution |

A late-encoded phishing URL reads *"asserted threat · thin record · likely
over"* — every word defensible from rows, where the one-number design said
"MALICIOUS, current" and was wrong twice. Three properties make the model
hold together: **quality's ledger sums to it exactly** (nothing normalised —
the audit trail is the number); the profile stays **threat-signed** and the
engine anchors rows to the lean, so a row visibly *supports* or *disputes*
the record's own assertion; and the three axes are precisely MISP's three
export gates (`to_ids`, `excludeStale`, `minQuality`), so the Assessment tab
is the page that explains the gates. The framing is deliberately
admiralty-shaped: org trust grades (source reliability) go in, the assessment
(information credibility) comes out.

## The design in twenty-three decisions

| # | Decision | Owner |
|---|---|---|
| D1 | The object is the **Analyst Profile** (near-collision with Analyst Data weighed; name stands) | `00-discovery.md` §6 |
| D2 | A profile is a **struct with named sections**; `signals` is the extensible list | `00-discovery.md` Q2 |
| D3 | **Three scopes, nearest owner wins** — exactly one profile in force per viewer | Q3 |
| D4 | A **dedicated table**, one JSON blob; `user_settings` cannot hold it | Q4, `02-store.md` |
| D5 | **Fork is a frozen copy** — no lineage; override maps are what keep forks tracking upstream | Q12 |
| D6 | The profile holds **reference data as override maps**: org trust (admiralty A–G, taxonomy-anchored) and warninglist categories (V1 hardcoded map in code, V2 upstream PR + core import fix) | `07-reference.md` |
| D7 | The page **stops reading `decaying_models`** — MISP decay double-counts tags, unattributably | Q8, `06-staleness.md` |
| D8 | The time curve is MISP's polynomial at `decay_speed 1` (linear); exponential has no TTL | Q8 |
| D9→D10 | The export gate is a **materialised instance assessment** — a background worker stores one row per value under the instance default profile; `restSearch` filters the row; the page stays render-time. Matches the industry's status-flip model; per-analyst feeds rejected | `11-restsearch.md` |
| D11 | The verdict becomes the **Assessment**: lean · relevance · quality. Dissolves SUSPICIOUS, the UNKNOWN conflation, the underived confidence bar, the disposition floors | `12-assessment.md` |
| D12 | **Signals and escalations are discovered from the filesystem** — an admin drops a PHP file in `app/Lib/ValueSignals/` and it is picked up; nothing in code holds a list. Two roots after `Workflow`, shipped and custom. Closes Q11: a signal is a class, discovery makes it available, a profile makes it active | `03-signals.md` §8 |
| D13 | **No new permission flag gates ownership.** A user profile needs no grant, an org profile needs `perm_admin`, the default is site-admin only. `perm_decaying` rejected — riding it silently widens every existing grant. Also the reversible direction: adding a flag later is additive. Closes Q7 | `02-store.md` §3.3 |
| D14 | **A weight band is editorial, not derived from the contribution.** The fixture forecloses "derived" — `7` is both `moderate` and `weak` in it. The band says what this kind of evidence is worth in principle; the contribution says what it produced here. Closes Q5. **Superseded by D16 on 2026-09-10 — the band is removed; D14's proof stands, its conclusion does not** | `03-signals.md` §5 |
| D15 | **The profile declares enrichment modules; nothing auto-runs.** The tab arrives with the analyst's modules ticked and a run still takes a press — the badge needs a per-value per-module last-run store that does not exist, and without one "run the defaults on page open" means running them on every page open. Closes Q10 | `08-enrichment.md` §1 |
| D16 | **The weight band is removed, not renamed.** It changes no arithmetic, and the number it gestures at — the most a signal could ever contribute — is already computed per signal and already shown, as the attainable bound. Against that ceiling the labelling overlaps (`weak` at 9 beside `moderate` at 9) and calls a purely subtractive signal `moderate`. Supersedes D14 | `03-signals.md` §5.1 |
| D17 | **The enrichment declaration gains a third state; two are built.** Per module: `ticked`, `never`, `auto`. `auto` is declared and not implemented — D15's missing last-run store is still missing, the queued path is dead code, and auto-running *widens* what a profile does, against §2.1. The schema lands now so adding it later needs no second migration. D15 stands. **The editor stopped offering it 2026-09-13 (D23)**; a stored `auto` is unchanged | `08-enrichment.md` §2.3 |
| D18 | **TTL becomes four named buckets plus per-type overrides**, not a row per attribute type — short/medium/long/very long at 90/120/365/730, `url` at 60 the one shipped override, `default` 180. Four rather than three because three cannot express the shipped table without changing real shelf life on every instance. Forks carry the flat map and need a read-time shim or they lose their TTLs silently | `06-staleness.md` §3.7 |
| D19 | **The posture was never about cost, then never about anything.** `cost_posture` was renamed `locality_posture` on 2026-09-10 — the one thing it did was withhold a module whose resolved locality is not local, and no module in MISP or misp-modules declares anything about money or rate limits — and `ask` was retired as byte-identical to `allow_external`. On **2026-09-12 the whole setting was withdrawn**: under D15 every run takes a press, so it withheld a checkbox rather than a query. Locality survives as a **label** — the per-module chip, and the strip's *"n of these would leave the instance"*. A stored key of either name is ignored, not migrated | `08-enrichment.md` §7.4–7.5, `09b-revisions.md` 3.21 |
| D20 | **A date aggregate reads the observation, not the row write.** `Attribute.timestamp` is a last-modified column, and four aggregates on `Value` read it as *when this was seen* — two of them feeding scored signals, so it reached the assessment and not just a label. `Value::OBSERVED_AT` and `OBSERVED_FROM` are the chain as SQL, one definition each, so the clock, `lifecycle.recency` and `lifecycle.continuity` cannot disagree about what a date means; `Attribute.created_at` has its slot reserved for the day MISP carries one. A sighting-based clock clears the timeline-uncertain flag for the same reason — a sighting *is* an observation date. 84% of attributes declare no seen date, and for those nothing changes | `06-staleness.md` §7.12–7.13 |
| D21 | **The enrichment editor is keyed by module; the document stays keyed by type.** 194 attribute types × 146 modules was unreadable and unmaintainable as a type-keyed map offering every module for every type. A module declares what it accepts (`mispattributes.input`, median 3 types), so one row per module offering only its own types is small — and bounded by what an administrator enabled, not by what MISP can store. `transposeModules()` is the single place the two axes meet. A derived per-type table was built to disclose an un-ticking side effect and then removed, because the side effect does not exist: the rail ticks only what the profile declared, so a declaration adds ticks rather than removing them | `09b-revisions.md` 3.19 |
| D22 | **The shipped default declares a CIRCL-first mapping.** `default-v1.json` v9 fills `auto_run` for thirteen types and twelve modules, nine of them CIRCL-operated; v11 carries the same mapping under a description written for the people reading it rather than the people building it (D23). It runs nothing and enables nothing; it **narrows** — a type it names arrives with those modules ticked and the rest unticked | `09b-revisions.md` 3.22 |
| D23 | **The editor offers only what is implemented; the document keeps what is storable.** `auto` (D17) and the enrichment reuse window were drawn inert — honest for a reviewer reading a prototype, an unfinished promise for an analyst configuring an instance — so neither is drawn at all now. Both stay valid in the document: `states()` still carries `auto`, `mergeAssoc()` keeps a field no form posted, and a state select offers any value it finds stored, so a save cannot rewrite a declaration nobody touched. Reverses `09b-revisions.md` §8.1's *"drawn as inert rather than hidden"* | `09-editor.md` §7h |

## Stress-tested

An adversarial review ([`review-2026-09-02.md`](review-2026-09-02.md)) hunted
inconsistencies and real-data failure modes; every finding is resolved,
withdrawn, or dissolved, with dated annotations. The ones that changed the
design:

- **The median value is the corpus** — most real values are one org, no
  sightings. The regression set gained that case and the default carries a
  calibration rule: a single-org record never leaves the `low` quality band.
  **Measured 2026-09-07 and half true**: the median *shape* lands in `low`
  under the shipped weights, but a single organisation reporting the same
  value for fourteen months reaches `medium`, and no weighting closes that
  gap. **Shipped 2026-09-07 as `thin_record_clamp`** in phase 3's banding —
  one source, no sightings, ceiling `low`, with the whole condition stated in
  the profile so an analyst who disagrees edits three numbers
  (`04-dispositions.md` §6).
- **The engine got a budget** — an evidence-time window (90 days of row
  evidence on long-history values; whole-history aggregates always), with
  `over_correlating_values` as the hot-value give-up. Deterministic by
  design: no row caps, no stopwatch deciding content.
- **Warninglist categories exist but nothing sets or even imports them** —
  verified: 0 of 89 upstream lists, and `Warninglist::__updateList()` drops
  the field. V1 ships a hardcoded name→`known` map
  (`WarninglistCategory.php`); V2 is the upstream PR plus a one-line core
  fix, with a mechanical retirement criterion. **Shipped 2026-09-07 in phase
  6** — 25 lists, all present on the dev instance, none of them carrying the
  category in the database, so `WarninglistCategory::retirable()` says V1
  stays (`07-reference.md` §7.9).
- **The trust scale misread its own authority** — the shipped taxonomy says
  `f = 50 = c` (neutral) and has a seventh grade `g` (deliberately
  deceptive, = 0). The scale now follows it.
- **Staleness must never pick a side** — time is its own axis (relevance),
  so silence can no longer promote a value to definite BENIGN.
- **`version` split from `revision`** — the upstream match key and the local
  edit counter collide in one column; the materialisation keys on revision.

## Status and what remains

Phases (living table: `01-profile.md` §1.4): **phases 1 to 9 are built;
10 is a specification.** Build order: 1 (store) gates all → 2–7 → 8a
(the contract) → 8b (three prototypes, one picked) → 8c (the wiring) →
9 (the tab goes live) → 10.

**Phase 8 closed 2026-09-11.** The editor is six pages under
`View/Themed/Overmind/AnalystProfiles/`: an index that names the profile
in force and why every other row is not, a read-only viewer, the
workbench — the document on the left, the value under assessment on the
right, recomputed by the engine on every change — the same bench given
the whole width, an import form and one confirm. The design is 8b's
workbench ([`09b-decision.md`](09b-decision.md)); the build and its ten
findings are [`09c-wiring.md`](09c-wiring.md).

**Phase 9 closed 2026-09-13, and with it the corpus's whole point.**
The Assessment tab computes an assessment, says all three of D11's axes
in the hero, and names the profile that weighted it with a link to the
editor that owns it — which is also what two of 8c's deliverables had
been waiting on.

**It opened 2026-09-13 with a read-back**, because `10-wiring.md` was
written before the engine existed and D11 renamed its subject
afterwards. Eight findings, in §7 there. The engine already returns a
verdict-shaped array and the editor's simulator has been rendering off
it since 8a — but the fifteen verdict templates read **twenty-five**
keys, `ValueVerdictTool` emits **twenty-three**, and the two sets
overlap in **twelve**. So the phase is not a swap: thirteen keys have
no producer in any of phases 1–8, and `summary`, `orgs` and `cases` are
read with no guard at all, which makes the first attempt error rather
than degrade. The value with nothing to assess errors first, because
the Overview card reads `summary` only where there is no ledger to
list. Five of the thirteen are derivable from phase 5's and phase 6's
own tools, one is a copy change the document already schedules, one is
out of scope by `01-profile.md` §7 and should stay empty — and the
remaining six, the hero's prose and the conflicted layout's pair of
cases above all, are the phase's real work.

**The spine went in the same day** (§8 there): `forVerdict()`, the
three endpoints off the fixture, and the thirteen keys defaulted in one
place. The Verdict tab now reads a real profile. What that makes true
for the first time is the invariant this whole corpus rests on —
**the ledger printed on the page sums to the score printed on the
page**: `8.8.8.8` prints ten rows totalling `−1` under a hero reading
`−1`, with the rail and the Overview card agreeing from two further
requests that share nothing with it. It closes as **CONFLICTED, quality
−1, band low, under `default-v1`**, which is phase 3 §11.5's reading
reached through the page rather than through a probe. 43 checks over
HTTP; the eight harnesses unchanged at 828. Four findings in §8.1–8.4,
the sharpest being that the rail went on branching on the disposition
after the tab had stopped — a full ledger beside a zero-byte column,
and a 200 with an empty body passes every assertion that does not
measure a size.

**Nine increments followed, all on 2026-09-13** — the five derivable
keys (§9), the copy pass (§11), D11's rename (§12), the hero (§13), the
contested layout (§14), the query counts (§15), the opinion
aggregate (§16), the clock band (§17), the lean band (§18) and the
band's floor (§19). §2.2's thirteen unproduced keys close as **eleven
produced, two deliberately empty**: `resolutions` and `changer_actions`
are writes this feature does not do, and neither is drawn as an inert
promise. **89 checks over HTTP** on the flagship value and **85 to 87**
on each of three others, up from 43; the eight harnesses at **907**,
the render harness at **98**, and a browser harness at **28** across
both themes.

**The last of them gave relevance its working back** (§17). The tab had
three axes and could show its work for two: lean had its rule and its
per-organisation table, quality had a ledger that sums to itself
exactly, and relevance had one clause of the hero's sentence and a bare
chart in the rail. The clock band draws the state, the runway, the date
the clock runs from, the type that supplied the TTL, the day it expires
and the four newest corroborations — all of it out of
`$verdict['relevance']`, which the engine had been building and the
page discarding, so it **costs no query**. It is the Lifetime card's
own elements rather than a second rendering of them, which is the
finding below turned into a mechanism.

**And the one after it finished the set** (§18). Lean looked done
because `8.8.8.8` takes the single exit of seven that carries prose —
a conflict rule, wired up by §13.3. The other six said nothing, so an
ordinary value asserted a threat and named neither the organisations
that asserted it nor the threshold they cleared, while `stances` and
`rule_errors` were computed on every value and read by no template in
the application. The band draws the counts, the split against both
supermajority marks, a sentence for each exit and any conflict rule
that could not run — for no query, again. **All three of D11's axes
show their working now**: the ledger, the clock and the split. The
reading it reaches that nothing else did is rule 6 — *neither side
reaches this profile's supermajority of 66% — the split is 1 to 1 — so
the record contradicts itself* — which is a contested value explaining
itself without an escalation having to catch it.

**The last of the three was the smallest** (§19), because quality is
the axis that always had its working — the ledger, and §5.1's rule that
its rows sum to the hero's number exactly. What it never said is where
the boundary is: `Quality low · 19 / 100` over a table summing to 19,
with nothing naming the floor that made it `low`. The ledger gained a
foot rather than a fourth band, since the table above it is the
working. And on two records in four the band is not the points at all —
the thin-record clamp lowers it and `quality_high_min_signals` holds
it, both deliberate, both leaving a number and a band that contradict
each other until something says so. Neither is reachable on the
verification instance, whose best single-source record scores **9**
against a floor of 30, so both are asserted in the harness — §13.1's
pattern for the third time in one phase.

The headline reading, on the flagship value:

> **Contested** — *What is recorded here contradicts itself. The record
> behind that is thin, and it has 69 days of shelf life left.*
> Threat case **70** · **71** benign case · Analyst profile
> `default-v1`

**Nine findings, and the sharpest is a page contradicting itself.**
`github.com` is flagged over-correlating, so the evidence budget leaves
its rows unread — and the relevance clock fell back silently, drawing
*expired, 33 days over* on the Assessment tab beside *64 days left* on
the Sightings tab. A clock missing its sighting half can only run slow,
so the axis stands down rather than guessing. It was caught by §9.5's
cross-panel shape, which is the third time this corpus has been bitten
by two panels computing one quantity twice — and the reason the opinion
histogram reads the Collaboration tab's own union at 2 to 27 queries
rather than a cheap count of its own, and the reason the clock band is
the Lifetime card's element rather than a copy of it.

**The same value found the other two** (§17.2, §17.3). `noClock()` has
returned `no_record` or `rows_not_read` since phase 5 and says in its
docblock that a caller should branch on them; **none ever did**, so a
value with hundreds of occurrences whose rows the budget declined to
read was told *nothing is recorded for this value* — false, on the
values most likely to be looked at, in the words most likely to be read
as the page being broken. And the band's first caption said the
Sightings tab *carries the same clock in full*, which on that same
value would have re-shipped the contradiction above as a sentence: the
two panels genuinely differ there, and what the caption says now is why.

**D11's rename landed with it** (§12): `ValueVerdictTool::LEAN_DISPOSITION`
is gone, the page says `lean` / `quality` / `band`, and
`ValueDisposition` became `ValueLean`, keyed by the axis the engine
computes. The internal names — `ValueVerdictTool`, the fifteen
`value_verdict*.ctp` files, the `viewVerdict*` URLs — keep the old word
deliberately: they rename with `value_verdicts`, `includeVerdict` and
`minVerdictScore`, which is phase 10's table. What a reader reads is
renamed now; what a reader cannot see renames with the table it is
named after.

**Five of the thirteen keys landed first** (§9 there) — *Who
says what*, the warninglist band and the rail's chart, all folded from
the context the engine scored rather than queried again. The
organisations table and the ledger row beside it count the same eight
organisations, which the probe now asserts instead of the docblock
arguing for it. And one of the five turned out not to be derivable:
**a verdict over time cannot be drawn**, because the page stores
nothing and phase 10 stores a current row rather than a series, so the
card draws the **relevance runway** — reconstructed from dates rather
than from scores, and the only thing the Verdict tab says about the
second of D11's three axes. It agrees with the Sightings tab's
relevance card to the percentage point, across two requests.

**Phase 8 is three passes, split 2026-09-07** (`09-editor.md` §1.1). It is the
first phase of this corpus whose deliverable is a *look* rather than a
computation. **8a** is the contract — the controller, the ACL, the mechanics,
the validation, every action's REST representation, and real JSON fixtures
dumped from the dev instance — and it writes no templates, so it can be
verified with no design decisions made at all. **8b** draws three deliberately
different designs against those fixtures, one agent per candidate, from the
cold brief in `09b-prototypes.md`; the user picks one. **8c** implements the
picked one. The order exists because a prototype drawn against invented data
cannot be built — `value-profile-live/` is the long record of that — and
because templates written before the design is picked are thrown away, two of
three by construction.

**Phase 8a, built 2026-09-07.** `AnalystProfilesController` and its twelve
actions, the ACL block, `AnalystProfileFormTool` (the seven sections as four
block kinds, the palette, the attainable bound, the validation and the
form→document merge), `ValueVerdictDiffTool`, `ValueUrlTool`,
`AnalystProfile::indexFor()`, a per-user comparison set, five fixtures and the
frame 8b draws into. Verified by 77 checks with no database, 44 against the dev
instance, 35 over HTTP and 27 over the fixtures, with all eight of the corpus's
harnesses still passing — 570 checks. Nine findings in `09-editor.md` §7d;
four of them are defects in code that shipped in earlier phases and were
invisible until something read it from a new direction:

- **`evidence.window` ignored its own `enabled` flag.** Every other exclusion
  has honoured it since phase 4; this one read the raw section rather than the
  plan, so the window applied whatever the profile said — and the editor was
  about to draw a toggle with nothing behind it (§7d.1).
- **Every refusal answered HTTP 200.** `RestResponse->viewData()` builds a
  fresh response and always passes 200, so the status set beforehand was
  discarded and a rejected save told an automated caller it had succeeded
  (§7d.2).
- **The ACL check that phase 1 deferred its work to was useless when it was
  needed.** `findMissingFunctionNames()` treats any method not prefixed with an
  underscore as an action, and 39 false positives — all of them this feature's
  two controllers — buried the real signal. Both now follow MISP's convention
  and the check returns `[]`. It also only reports one direction, so the live
  probe asserts the other: no ACL entry names an action that does not exist
  (§7d.5).
- **A fork of the shipped default introduced itself as the shipped default**,
  because `forkProfile()` copied the description verbatim — false of the copy,
  on the one field a colleague reads to decide whether to adopt it (§7d.4).

And one that changes what 8b may promise: **a weighting is invisible past a
cap.** The fixture dump's first attempt produced a diff with no changed row in
it, because the weight it lowered was already saturated. So a design promising
*"change a weight and watch the row move"* will be wrong for some signals some
of the time; the honest promise is *change a number and the diff shows what
actually happened* (§7d.7).

**Phase 1, built 2026-09-07.** Migration 160 and the `analyst_profiles`
table, `app/Model/AnalystProfile.php`, and the shipped
`app/files/analyst-profiles/default-v1.json`. Its exit criterion —
`resolveFor()` returns exactly one profile for every user — is asserted by
`02-store-resolve-harness.php`, 33 checks with no database. The controller
moved to phase 8, and four of the nine verification items need a live
instance (`02-store.md` §7 says which).

**Phase 2, built 2026-09-07.** The accumulator
(`app/Lib/Tools/ValueVerdictTool.php`), D12's filesystem loader
(`ValueSignalLoader`), the eleven signals as eleven files under
`app/Model/ValueSignals/`, the one-build context
(`ValueProfile::verdictContextFor()` and three new aggregates on `Value`),
and the eleven-signal default profile. Verified by 96 checks with no database
and 36 against the dev instance: the ledger sums to the quality to the unit
on every value scored, a dropped file no profile enables leaves every number
byte-identical, and a signal that throws or returns a float lands in
`not_counted` with the rest of the ledger still summing exactly. Eight findings
are recorded in `03-signals.md` §11 — the load-bearing one is that §7.4's
calibration rule needs a clamp in phase 3's banding rather than a weighting,
measured rather than assumed.

**Phase 3, built 2026-09-07.** The lean derivation (`ValueLeanTool` — seven
rules, first match wins, stances counted per organisation), the two shipped
conflict rules discovered from `app/Model/ValueEscalations/` under D12's
second pair of roots, rule 7 and the two-sided tug in `ValueVerdictTool`, the
`thin_record_clamp` threshold, the falsifiability lines derived per axis
(`ValueChangersTool`), and `isDefinite()` finally wired into the two
dispositions that were being drawn as though they were answers. Verified by
100 checks with no database and 52 against the dev instance. Six findings are
in `04-dispositions.md` §11; three are worth knowing about from here:

- **A supermajority written as a decimal cannot be met on both sides.** A
  value held by 34 of 100 organisations misses the mirror of `0.66` by a
  floating-point hair and lands in the stance split. The rules are now stated
  as *a supermajority on either side*, with an explicit tolerance (§11.1).
- **A derived falsifier can still lie**, and two of them did. A quality line
  offered a band the clamp would refuse; a lean line offered a change into
  the state the value was already in. Both are fixed, and both were found by
  reading the output rather than by the assertions (§11.2, §11.3).
- **`8.8.8.8` closes as a contested value with a name on it** — eight of eight
  organisations against the public-resolver list, tug at 74 against 77 — which
  is the reading phase 2 predicted and could not reach (§11.5).

**Phase 4, built 2026-09-07.** `ValueExclusionTool` and the `exclusions`
section: `sightings.self` (a row filter with a window, because a
self-sighting a year later is news rather than self-confirmation),
`feeds.mirrored` (a provider fold, with the imprecision named on the page),
`orgs.own` (a query predicate — see below), and the `reason` key that finally
tells a reader which rows of *not counted* they could change. Verified by 42
checks with no database and 18 against the dev instance. Five findings are in
`05-exclusions.md` §7; three are worth knowing about from here:

- **An exclusion is not one mechanism.** Half a value's evidence is a
  `COUNT DISTINCT` and never exists as rows, so `orgs.own` had to become a
  predicate in `Value::conditionsFor()` — the one place all fourteen
  value-scoped aggregates build their predicate. Filtering after the fact
  would have left the reporting breadth naming 7 organisations while the
  occurrence tally still counted 8 (§7.1).
- **The page says nothing about the reader's permissions.** MISP discloses
  what a reader may see, the people using it know it, and a per-value caveat
  tells them nothing while hinting at records they have no business knowing
  exist. So the fixture's *"4 occurrences outside your ACL"* is gone, and so
  is the de-numbered caveat the first implementation put in its place — an
  unconditional *"computed from what your permissions allow"* is the same hint
  at one bit per page load. A note about the *instance's* policy or the
  reader's *role* is untouched: it explains an empty panel and reveals
  nothing about the value (§7.2).
- **44 harness checks passed against a context shape that does not exist.**
  The occurrence map is flat; the implementation and its harness fixture both
  read it as nested, so the self-sighting rule declared every row undecidable,
  excluded nothing, and looked exactly like a rule with nothing to do. The
  live probe caught it by asserting the rule's *inputs*; the same value then
  excluded 30 (§7.3).

**Phase 5, built 2026-09-07.** The relevance axis
(`ValueRelevanceTool`) — the clock, the curve at `decay_speed` 1, the
per-type TTL and the four states — plus the retirement D7 promised: the
page reads MISP's decaying models **nowhere**. `ValueDecayTool`, the decay
panel and `ValueProfile`'s decay path are deleted, the rail card is
`value_relevance.ctp`, and the sightings overlay plots the TTL runway
against the report bars, which is two quantities where it used to be two
estimates of one. Verified by 106 checks with no database and 58 against
the dev instance. Six findings are in `06-staleness.md` §7; three are
worth knowing about from here:

- **The four states are not four.** `12-assessment.md` §3 reads the
  late-encoded phishing URL as *"expired · timeline uncertain"* — two of
  them at once. So the state is one word and the uncertainty is also a
  flag, and expiry outranks uncertainty for a reason rather than by
  preference: an encoding date is later than the observation it stands
  for, so elapsed time measured from it is a **lower bound**, and a bound
  already past the TTL is past it on any honest reading (§7.1).
- **D11's invariant is directional.** The harness's first attempt at
  *"the quality is byte-identical across the boundary"* failed by two
  points, correctly — `occurrences.newest` is the fallback clock *and*
  `lifecycle.recency`'s evidence. D11 forbids anything *reading* the
  relevance block, not the two axes sharing a date; proving it needs a
  relevance-only knob, and the TTL is one (§7.2).
- **`timeline uncertain` is the common state, not the exotic one.**
  `8.8.8.8` has 0 of 26 occurrences carrying `first_seen` and a 302-day
  encoding lag. The shipped default will call most real values' timelines
  uncertain, which is the honest reading of MISP data rather than a
  calibration error — and it is why relevance asks *can this be dated at
  all* while `record.temporal_precision` grades *how well* (§7.3).

Also measured: the two rebuilt endpoints went from 21 queries each to 10
and 11, and the retirement is asserted from the query log rather than by
grep — a grep cannot say that no query reaches a table, and the probe's
first run reported *"0 queries, 0 touching decaying_models"* because the
datasource log had stopped recording (§7.5).

**Phase 6, built 2026-09-07.** The two reference maps — `ValueTrustTool`
(admiralty grades A–G keyed by `organisations.uuid`, the editable
grade→multiplier scale, and the weighted counts the three `trust_weighted`
signals read) and `WarninglistCategory` (V1's shipped 25-list map, the
four-step category resolution, and a mechanical criterion for retiring
itself) — plus the uuid→id join in `ValueProfile::verdictTrust()` and
per-organisation sighting tallies. Verified by 114 checks with no database
and 84 against the dev instance. **The escalation phase 3 shipped can now
reach its own precondition**: `conflict:known-infrastructure-vs-reporting`
requires `warninglist_category: known` on a platform where nothing sets that
column — `0` of `89` upstream lists carry the field, re-confirmed, and core's
`__updateList()` drops it — so the knowledge ships as code and the rule fires
on the instance's own rows. Nine findings are in `07-reference.md` §7; four
are worth knowing about from here:

- **A weighting is invisible where a signal is saturated.**
  `reporting.independent_orgs` caps at four weighted voices, so grading
  `8.8.8.8`'s eight reporters `D` moves the row by nothing — and §5's own
  items 2, 3 and 5 could not be observed as written. Recorded rather than
  fixed, because the cap is the analyst's number and weighting before it is
  what the design requires; what changed is that the tests now assert the
  mechanism — `min(per_org × Σ factor, cap)` — and make directional claims
  only off the cap (§7.1).
- **A grade nearly turned a false positive into corroboration.** The
  extra-organisation term pays `per_extra_org × (orgs − 1)`, and a single
  `E`-graded filer sums to `0.25` voices — so `Σ − 1` is `−0.75` and the
  product is `+3`. Clamped at zero. Found by writing the arithmetic out, not
  by reading a page: the row would have been small and positive, and nothing
  says which sign a row is supposed to have (§7.2).
- **A category override does not always change the lean.** On `8.8.8.8` the
  value was already contested by `conflict:listed-vs-asserted`, so the
  `known` override handed the contradiction to the other rule and changed the
  prose rather than the word. *"The value goes CONFLICTED"* is the wrong
  thing to look for on most values an override will touch (§7.5).
- **Three earlier harnesses had been dead since phase 5** — it added a
  relevance call to the engine without adding the `require_once` — and phase
  6 would have hidden it, because its new dependency lands the three heaviest
  signals in `not_counted` while every other assertion still prints `ok`.
  Fixed: all six harnesses run, 493 checks, and all five live probes pass
  with them (§7.7).

**Phase 7, built 2026-09-07.** The enrichment declaration
(`ValueEnrichmentTool`), the locality roster it needs (`ModuleLocality` —
22 modules, a profile override map, and a mechanical criterion for
retiring itself), the profile strip above the tab's every empty state, and
a rail that arrives with the analyst's modules **ticked rather than run**.
Verified by 102 checks with no database, 55 against the dev instance, and
five rendered states of the tab. Seven findings are in `08-enrichment.md`
§7; four are worth knowing about from here:

- **The badge is still blocked, and this is the phase that says so with
  receipts.** Nothing in MISP records that a module ran, so *"run the
  defaults on page open"* means running them on every page open; and the
  interactive path is synchronous whatever `MISP.background_jobs` says,
  because `Event::enrichmentRouter()` returns at `Event.php:7997` and
  strands its own queued branch at `7998` (§1).
- **Locality cannot be derived, and the receipt is two modules.**
  `countrycode` declares no config and no requirements and fetches
  `geognos.com` over plain HTTP; `clamav` takes one config key and reaches
  nothing but the operator's own `clamd`. The same introspection shape says
  both things — D14's argument in a new place — so the roster ships as code
  (§3.1).
- **The instance's modules port was wrong and the first probe run nearly
  passed anyway.** 21 of its assertions held with nothing reachable,
  because *"nothing is selected"* is true when the service is down too. The
  probe now refuses to continue unless the service answers — phase 5 §7.5's
  lesson, where a stopped query log reported *"0 queries"* (§7.1).
- **`local_only` selects almost nothing on an ordinary value**: 1 of the 5
  modules eligible for `8.8.8.8` answers from inside, because the local
  roster is attachment readers and syntax validators. That was the posture
  doing exactly what it says on a platform where enrichment means asking
  somebody else (§7.4).

Open questions: Q9 (per-viewer caveat, phase 9 — **half-answered**, since
phase 4 removed the ACL half and phase 6 made the profile half real), Q13
(`includeAssessment` exposure gate, phase 10), plus one phase-9 item — the
hero's three-axis composition. **Nothing gating phases 1–7 is open any
more, and neither phase 6 nor phase 7 opened anything.**

Four questions closed in three days, and two closed against this corpus's
own recorded recommendation:

- **Q11 → D12** (2026-09-07). The extension point ships in v1 after all,
  because the loader it needs already exists in MISP twice and the expensive
  half of the question — an expression language — was not what was being
  asked for.
- **Q7 → D13** (2026-09-07). No new `perm_*` flag; `perm_admin` for an org
  profile, nothing for a user's own. The recommendation held, and the
  deciding argument was reversibility rather than the design merit.
- **Q5 → D14** (2026-09-07). The band is editorial. Decided on the fixture's
  own numbers rather than on preference — `7` appears in two bands, so
  "derived" was never available.
- **Q10 → D15** (2026-09-07). The enrichment plumbing stays out of scope and
  the profile declares rather than triggers — the recommendation held, and
  building it added the part the recommendation had missed: *inert* was not
  available, because the tab cannot say a module leaves the instance
  without a map that knows which ones do.

External prerequisites: the `misp-warninglists` category PR and the MISP
core import fix (V2 above). A third would retire `ModuleLocality` the same
way — a `meta` field in `misp-modules` saying whether asking a module
leaves the instance — and unlike the warninglist one, nobody has proposed
it yet (`08-enrichment.md` §3.4).

## Reading map

| File | What it holds |
|---|---|
| [`01-profile.md`](01-profile.md) | **The main PRD**: purpose, scope, principles, the decisions index, invariants, the state table |
| [`00-discovery.md`](00-discovery.md) | The discovery pass and the grilling record — why, with the rejected alternatives |
| [`02-store.md`](02-store.md) … [`11-restsearch.md`](11-restsearch.md) | Phases 1–10, one file each |
| [`09b-prototypes.md`](09b-prototypes.md) | Phase 8b's brief, written to be executed cold — the only file in the corpus addressed to someone who has read none of the others |
| [`12-assessment.md`](12-assessment.md) | D11 — the assessment's semantic model and rename map |
| [`review-2026-09-02.md`](review-2026-09-02.md) | The adversarial review, findings and their resolutions |
