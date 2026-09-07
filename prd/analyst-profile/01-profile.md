# PRD: The Analyst Profile

**The main document. This one sets the picture and carries the state table.**
Each phase is specified in its own file; §1.4's table is the only phase-level
record and is the thing to update as each lands. A newcomer's entry point is
[`README.md`](README.md) — a dated executive summary that defers to this
document for anything living.

The discovery pass and the grilling agenda are
[`00-discovery.md`](00-discovery.md). Decisions taken in the grilling session
are recorded there against the question they answer, and consolidated here in
§2. Questions still open are listed in §8 against the phase they land in.

---

## 1. Overview

### 1.1 Purpose

The Value Profile page displays a verdict — a disposition, a score, and a
ledger of signals that sums to it — and **nothing computes any of it**. The
page has said so since the skeleton pass, and the Verdict tab is the only one
of nine whose live phase is blocked as a result.

The blocker is not the algorithm. It is that a scoring algorithm has no
defensible defaults: *"four independent organisations reported it"* is worth
+28 to one analyst and +12 to another, and neither is wrong. An engine with the
weights baked in would be a hidden editorial position shipped as arithmetic.

**The Analyst Profile is the object that holds those judgements**, so that the
engine can be a mechanism rather than an opinion. It defines:

- which signals are evaluated, and what each contributes;
- the thresholds that turn a score into a disposition;
- the named rules that override the score entirely;
- which evidence is deliberately set aside;
- how long a value stays fresh, and what resets that clock;
- what the analyst believes about their sources — which organisations they
  trust, and which warninglists mean *shared infrastructure* rather than
  *false positive*;
- which enrichment modules run when a value page opens.

An instance ships one, enabled by default. An organisation or an analyst forks
it and edits their copy. The Verdict tab always names the one in force, which
it already does — `Weighting profile default-v3` is on screen today, backed by
nothing.

**What it achieves, stated as the thing that is currently impossible.** Two
analysts looking at the same value cannot presently disagree in a way the
system can express. One of them writes a note. With profiles, the disagreement
becomes a legible artefact: *the same evidence, two profiles, two scores, and a
ledger each that shows exactly which row diverged.* That is the feature. The
verdict engine is the mechanism it needs.

**Decided 2026-09-03 (D11): the computed object is no longer a verdict.** It
is an **assessment** on three axes — lean (what the record asserts),
relevance (whether it still matters), quality (how much the record can be
trusted) — specified in [`12-assessment.md`](12-assessment.md). This document
and the phase documents predate the rename and still say *verdict*
throughout; each is read through D11's rename map until its rework lands.

### 1.2 Scope of this PRD

This document covers **what a profile is** — its contents, its ownership, its
resolution, and the invariants that constrain every phase. It specifies no
schema (that is phase 1) and no algorithm (phase 2).

It also does not re-litigate the Value Profile page. The page's nine tabs, its
fixture, its templates and its live campaign are
[`../value-profile-page.md`](../value-profile-page.md) and its subdirectories.
This feature adds a configuration object and unblocks one tab.

### 1.3 Design principles

- **The score is its own explanation.** Contributions sum to the score
  exactly. Nothing is normalised, calibrated or post-processed. Verified
  against three demo values today, and non-negotiable — see §5.1.
- **A profile is data, not code.** Every judgement is a value in a JSON
  document that an analyst can read. Anything that has to be code is a signal
  *implementation*, and its weight still comes from the profile.
- **Defaults that work unedited.** The stated reason MISP's decaying models
  went unused is that they lack defaults and demand taxonomy knowledge nobody
  has. The shipped profile must produce sensible verdicts on day one with
  nobody touching it — and *sensible* includes the median value, one
  organisation and no sightings, where the deliberate day-one answer is
  UNKNOWN with a small score, not a definite verdict conjured from thin
  evidence ([`03-signals.md`](03-signals.md) §7.4).
- **Empty means "as before".** Every map in a profile is an override set. An
  empty org-trust map weights all organisations equally; an empty warninglist
  map defers to the warninglists table. Adding the feature changes no
  behaviour until someone edits something.
- **Honest states over silent defaults.** A profile naming a module the
  instance disabled, a TTL for a type the value does not have, an org trust
  entry for an org that has left — each renders as a stated condition, not a
  quiet omission. The page has form for this.

### 1.4 Where this stands

**Phases 1 to 7 are built as of 2026-09-07**; phases 8–10 are
specifications. Phase 10 was a recorded direction until 2026-09-03, when D10
settled its design and it became specifiable. **Phase 9 — the tab going live —
now has every phase it depends on**, and phase 6 was the last one that could
still change a number under it: phase 7 changes no number at all, which is why
it could land after the axis work rather than before it.

Phase 2 built the accumulator, the eleven-signal catalogue and D12's
filesystem loader, and its exit criterion holds against real rows: the
ledger sums to the quality to the unit on every value the probe scores
([`03-signals.md`](03-signals.md) §9).

**Phase 3 completes the assessment's first two axes.** The lean is now
derived rather than passed, the quality has a band with a story, and both of
phase 2's open items are closed: §7.4's calibration rule landed as a clamp in
the banding ([`04-dispositions.md`](04-dispositions.md) §6), and `8.8.8.8` —
which phase 2 measured at −3 and could not name — reaches a contested lean
through `conflict:listed-vs-asserted` on the instance's own rows (§11.5
there).

**Phase 4 closed the `not_counted` block's own ambiguity**, and removed a
specified feature rather than rebuilding it: the page now says **nothing about
the reader's permissions, on any value**. MISP discloses what a reader may
see, the people using it know it, and a per-value caveat both tells them
nothing and hints at records they have no business knowing exist — so the
fixture's *"4 occurrences outside your ACL"* is gone rather than
de-numbered, and `reason` carries two values rather than three
([`05-exclusions.md`](05-exclusions.md) §7.2). It also put an exclusion into
the query layer rather than over the context, because half a value's evidence
is an aggregate and never exists as rows (§7.1 there).

**Phase 5 completes the assessment's remaining axis and retires live
code.**
The page no longer reads MISP's decaying models anywhere:
`ValueDecayTool` and the decay panel are deleted and `ValueProfile` lost
392 lines, replaced by an axis computed from a handful of dates — and the
two endpoints that drew a curve went from 21 queries each to 10 and 11.
The new tool is *longer* than what it replaced while the machinery is
smaller, which §7.6 states rather than hides. The retirement is asserted from the query log rather than
by grep, because a grep cannot say that no query reaches a table
([`06-staleness.md`](06-staleness.md) §7.5).

Two findings there are worth knowing from here. **The four states are not
four**: the late-encoded phishing URL is *"expired · timeline uncertain"*
in `12-assessment.md` §3, so the state is one word and the uncertainty is
also a flag — and expiry outranks uncertainty because an encoding date is
later than what it stands for, making elapsed time measured from it a
lower bound (§7.1). And **D11's invariant is directional**: relevance and
quality share inputs — `occurrences.newest` is the fallback clock *and*
`lifecycle.recency`'s evidence — so what D11 forbids is anything reading
the relevance block, not the two axes touching one date. Proving it needs
a relevance-only knob, and both the harness and the probe now use the TTL
(§7.2).

**Phase 6 gives the profile the last thing it can say about a score.** Both
maps ship empty and both stay override sets, so the shipped default scores
exactly as it did before the section existed — asserted in the strong form,
with the *scale* edited and the map left empty, on every demo value
([`07-reference.md`](07-reference.md) §7.6). What it unblocks is phase 3's
one shipped escalation, which required `warninglist_category: known` in a
platform where **nothing sets that column** — `0` of `89` upstream lists
carry the field and `Warninglist::__updateList()` drops it on import — so V1
ships the knowledge as code and the escalation fires on the instance's own
rows for the first time (§3.3, §7.9).

Two findings there change how the feature should be read. **A weighting is
invisible where a signal is saturated**: `reporting.independent_orgs` caps at
four weighted voices, so grading eight reporters `D` moves the row by nothing
at all, and §5's own items 2, 3 and 5 could not be observed as written
(§7.1). And **a category override does not always change the lean** — on
`8.8.8.8` the value was already contested, so the override handed the
contradiction from one rule to the other and changed the prose rather than
the word (§7.5).

**Phase 7 closed the last section, and closed it by not building the
thing that was asked for.** Q10 became **D15**: the profile *declares*
enrichment modules per attribute type, the Enrichment tab arrives with
them **ticked rather than run**, and the badge waits for the per-value
per-module store that does not exist — because without it, *"run the
defaults on page open"* means running them on every page open
([`08-enrichment.md`](08-enrichment.md) §1.1). Building it needed one
thing the specification had filed under *out of scope*: a posture that
cannot tell a local module from an external one is not a posture, so
`ModuleLocality` ships the same way phase 6's warninglist categories
did — as code, with a mechanical retirement criterion — and for the
same reason, which is that introspection carries no such field and the
obvious heuristic is wrong in both directions (§3.1 there).

Three findings there are worth knowing from here. **The instance's
modules port was wrong and the first probe run nearly passed anyway**:
21 of its assertions held with nothing reachable, because *"nothing is
selected"* is true when the service is down too, so the probe gained a
preflight that refuses to continue (§7.1). **One fact needs one
producer** — `resolve()`'s first version computed each module's
locality itself rather than reading the row the rail already carried,
and a harness check about *wording* caught it (§7.2). And
**`local_only` selects almost nothing on an ordinary value**: 1 of the
5 modules eligible for `8.8.8.8` answers from inside, because the local
roster is attachment readers and syntax validators. That is the
posture doing exactly what it says on a platform where enrichment means
asking somebody else, not a calibration error (§7.4).

**What remains before the tab can go live is nothing in this feature's
dependency chain.** Phase 9 is next in build order; phase 8 (the editor) is
independent of it, and is where phase 6's `unknown` and `invalid` grade lists
get drawn — in 8c, against a design 8b picks.

Building phase 1 closed **Q7 as D13** and, alongside it, **Q5 as D14** —
the two questions that gated phases 1 and 2; building phase 2 left every
remaining question where it was, and added one item to phase 3's list, which
phase 3 closed. Q11 had closed two days earlier
as D12. **Every question gating the next phase is now answered**; Q9, Q10 and
Q13 land in phases 9, 7 and 10 and block nothing before them. Phase 5
opened none: it settled the state-versus-flag question in
[`12-assessment.md`](12-assessment.md) §7's remaining list only insofar as
the axis itself is concerned, and the hero's three-axis composition is
still phase 9's.

The order below is a dependency order, not a schedule. Phase 1 gates
everything. Phases 2–6 are the profile's six sections and can proceed
independently once phase 1 lands. Phase 9 is the payoff — the Verdict tab goes
live — and needs 1–5, **all of which are now built**, with 6 built beside
them.

**Phase 8 became 8a, 8b and 8c on 2026-09-07** ([`09-editor.md`](09-editor.md)
§1.1). It is the first phase whose deliverable is a look rather than a
computation, and the two halves fail in opposite directions when built
together: a prototype drawn against invented data cannot be built, and
templates written before the design is picked are thrown away — two of three,
by construction. So the contract lands first and can be verified with no
design decisions made at all, three candidates are then drawn against its real
output, and only the picked one becomes templates.

| Phase | What | Written up in | Status |
|---|---|---|---|
| 1 | **The store** — the table, the model, ownership and resolution, the shipped default, fork, permissions | [`02-store.md`](02-store.md) | **built 2026-09-07** — migration 160, `AnalystProfile.php`, `default-v1.json`; closed Q7 as D13. The controller moved to phase 8 (§6 there), and four of nine verification items need a live instance (§7) |
| 2 | **Signals and the engine** — the `signals` section, the mechanism that turns it into a ledger and a score, and the loader that discovers signal implementations from the filesystem | [`03-signals.md`](03-signals.md) | **built 2026-09-07** — `ValueVerdictTool`, `ValueSignalLoader`, eleven signal files, the context builder, the eleven-signal default. 96 harness checks and 36 live; eight findings in §11, one of them phase 3's to close |
| 3 | **The lean and the bands** — the lean derivation, `thresholds` and `escalations`, the quality bands, derived `changers` | [`04-dispositions.md`](04-dispositions.md) | **built 2026-09-07** — `ValueLeanTool`, `ValueChangersTool`, the `escalation` loader subject with two shipped rules, rule 7 and the tug, `thin_record_clamp`, `isDefinite()` wired. 100 harness checks and 52 live; six findings in §11. Closes phase 2's §11.1 and §11.2 |
| 4 | **Exclusions** — the `exclusions` section, and splitting policy from ACL in `not_counted` | [`05-exclusions.md`](05-exclusions.md) | **built 2026-09-07** — `ValueExclusionTool`, `orgs.own` as a query predicate, `sightings.self`, `feeds.mirrored`, the `reason` key. 44 harness checks and 18 live; five findings in §7, and `acl` is retired before it shipped (§7.2) |
| 5 | **Staleness** — per-type TTL against last independent corroboration, and retiring `decaying_models` from the page | [`06-staleness.md`](06-staleness.md) | **built 2026-09-07** — `ValueRelevanceTool`, the completed `relevance` section, `value_relevance.ctp` and the TTL runway overlay; `ValueDecayTool` and the decay path deleted. 106 harness checks and 58 live; six findings in §7 |
| 6 | **Reference** — per-org trust and warninglist category overrides | [`07-reference.md`](07-reference.md) | **built 2026-09-07** — `ValueTrustTool`, `WarninglistCategory` (V1's shipped map and the four-step resolution), `org_trust_scale`, the uuid→id join, trust weighting in the three signals §2.4 names. 114 harness checks and 84 live; nine findings in §7, and phase 3's one shipped escalation can finally reach its own precondition |
| 7 | **Enrichment defaults** — the module list and the top-level badge | [`08-enrichment.md`](08-enrichment.md) | **built 2026-09-07** — `ValueEnrichmentTool`, `ModuleLocality` (V1's 22-module roster and its retirement criterion), the `locality` override, the profile strip and a rail that arrives ticked. 102 harness checks, 55 live, five rendered states; seven findings in §7. Closes Q10 as D15; the badge stays blocked on the store §1.1 names |
| 8a | **The editor's contract** — the controller, the ACL, the mechanics, the validation, every action's REST representation, and the fixtures the prototypes draw against. No templates | [`09-editor.md`](09-editor.md) §1.1 | **built 2026-09-07** — `AnalystProfilesController` (twelve actions), `AnalystProfileFormTool`, `ValueVerdictDiffTool`, `ValueUrlTool`, `AnalystProfile::indexFor()`, the ACL block, five fixtures and 8b's frame. 77 harness checks, 44 live, 35 over HTTP, 27 over the fixtures; nine findings in §7d, four of them defects in earlier phases |
| 8b | **Three prototypes** — one design each, cold, from the brief; the user picks one | [`09b-prototypes.md`](09b-prototypes.md) | brief written 2026-09-07; not started |
| 8c | **The wiring** — the picked design as templates, and the links in from the verdict | [`09-editor.md`](09-editor.md) §7c | blocked on 8b |
| 9 | **Wiring the Verdict tab live** — the page reads a profile, and the shipped copy that is now wrong gets corrected | [`10-wiring.md`](10-wiring.md) | specification |
| 10 | **The verdict in restSearch** — a materialised instance verdict, set by a background worker, filtered at export | [`11-restsearch.md`](11-restsearch.md) | specification — rewritten 2026-09-03 (D10); the page's per-viewer verdict stays render-time |

The Value Profile campaign's own tab-level table
([`../value-profile-page.md`](../value-profile-page.md) §1.4) carries one row
pointing here, and `../value-profile-live/00-contract.md` §14.12's panel board
is where the Verdict tab's panels move from fixture to live when phase 9 runs.

## 2. Decisions

D1–D9 were taken in the grilling session of 2026-09-02 and are recorded in
[`00-discovery.md`](00-discovery.md) against the question each answers, with
the reasoning and the rejected alternatives; D10 and D11 were taken
2026-09-03 and are recorded where each was decided — D10 in
[`11-restsearch.md`](11-restsearch.md) §3, D11 in
[`12-assessment.md`](12-assessment.md). This is the index.

| # | Decision | Where |
|---|---|---|
| D1 | The object is the **Analyst Profile**. `Analyst Posture` rejected — it also declares enrichment behaviour, which is not a posture | `00-discovery.md` §6 |
| D2 | A profile is a **struct with named sections**, and `signals` is a list whose entries each emit exactly one ledger row. A flat rule list was rejected: an escalation replaces the answer rather than contributing to it | Q2 |
| D3 | **Three scopes, nearest owner wins.** Exactly one profile in force per viewer: theirs, else their org's, else the instance default. Layered deltas rejected — the hero names one profile and must be able to name it honestly | Q3 |
| D4 | A **dedicated table**, one JSON blob in `parameters`. `user_settings` cannot hold it: `user_id` is `NOT NULL`, so it can express one of the three scopes. `all_orgs` is out. Existing user settings are untouched | Q4 |
| D5 | **Fork does not track provenance.** No `parent_uuid`, no diff-against-parent. Forking exists to get started, not to carry lineage | Q12 |
| D6 | The profile holds **reference data**, as override maps: per-org trust and warninglist categories. Empty maps mean today's behaviour | Q6 |
| D7 | The page **stops reading `decaying_models` entirely.** The profile owns a per-type TTL against last independent corroboration. MISP's decay would double-count tags via `base_score`, and unattributably | Q8 |
| D8 | The curve is **MISP's polynomial with `decay_speed` defaulting to 1**, which is linear. Exponential rejected — it asymptotes, so it has no TTL | Q8 |
| D9 | **Direction: the verdict score is to be exposed to restSearch**, eventually replacing `excludeDecayed`. Deferred to phase 10 with its prerequisites named | Q8 |
| D10 | The export gate is a **materialised instance verdict** — a background worker computes one verdict per value under the instance default profile, and `restSearch` filters on the stored row. Per-request computation and per-analyst gating rejected: the industry runs status-flip materialisation, and a worker must pick its profile before any caller exists. The page's per-viewer verdict stays render-time | `11-restsearch.md` §3 |
| D11 | The verdict becomes the **Assessment**: three axes — **lean** (the record's assertion, from `to_ids` stance and warninglist categories, categorical and timeless), **relevance** (the TTL machinery, with temporal precision as an input), **quality** (the scored ledger, now the exact-sum invariant's sole home). Dissolves SUSPICIOUS, the UNKNOWN conflation, the underived confidence bar and the disposition floors. "Verdict" rejected as the overclaim distilled | `12-assessment.md` |

| D12 | **Signals and escalations are discovered from the filesystem.** An instance admin drops a PHP file in `app/Lib/ValueSignals/` and the engine picks it up; the shipped catalogue is `app/Model/ValueSignals/` and nothing anywhere holds a list of signals. Follows `Workflow`'s two module roots and `DecayingModel::listAvailableFormulas()`. Closes Q11 — a signal is a class, not an expression language; discovery makes it available, a profile makes it active | `03-signals.md` §8 |


| D13 | **No new permission flag gates profile ownership.** A user profile needs no grant — it changes only its owner's page, the reasoning that leaves `user_settings` ungated; an org profile needs `perm_admin`; the default stays site-admin only. `perm_decaying` rejected because riding it silently widens every existing grant. Chosen partly as the reversible direction: adding a flag later is additive, withdrawing one is a migration. Closes Q7 | `02-store.md` §3.3 |
| D14 | **A signal's weight band is editorial, declared per signal — not derived from its contribution.** The fixture forecloses "derived": `7` appears in both `moderate` and `weak`, and `17` (strong) sits above `16` (moderate), so no threshold reproduces the labelling. The band says how much this *kind* of evidence matters in principle; the contribution says what it produced here. Closes Q5 | `03-signals.md` §5 |
| D15 | **The profile declares enrichment modules; nothing auto-runs.** Q10's shape B: `auto_run` is a per-type declaration, the Enrichment tab pre-selects it, and the badge waits for `../value-profile-writes.md` §6.4's last-run store. Shape A rejected — it makes this feature's schedule depend on fixing `Event::enrichmentRouter()`; shape C rejected — the ask names enrichment explicitly. Two consequences the specification did not carry: locality has to ship as a map (`ModuleLocality`), because a posture needs it and introspection has no such field, and `ask` cannot differ from `allow_external` while every run takes a press | `08-enrichment.md` §1, §3 |

Still open: Q9 and Q13 — the `includeVerdict` exposure gate — see §8.
**Q11 closed 2026-09-07 as D12, Q7 as D13, Q5 as D14 and Q10 as D15**, so
nothing gating phases 1–7 remains open. Phase 6 opened none of its own: the two
maps are D6 and its shape questions were settled in the review, and the one
thing it had to decide for itself — what an empty map means when the *scale* is
also data — is recorded as a property rather than a question
([`07-reference.md`](07-reference.md) §7.6). Phase 7 opened none either, and
answered its own question against the specification's recommendation in one
respect: shape B was recommended and taken, but its *"the section ships inert"*
turned out to need a locality map to be honest at all
([`08-enrichment.md`](08-enrichment.md) §3.1).

## 3. What a profile contains

Seven sections. Five shape the quality ledger and the lean (D2), `relevance`
configures the third axis (D11), and `enrichment` was in the ask from the
start and is not part of any of them.

```json
{
  "format": 1,

  "signals": [
    { "id": "reporting.independent_orgs", "group": "Reporting",
      "band": "strong", "trust_weighted": true,
      "points": { "per_org": 7, "cap": 28 } },

    { "id": "sightings.volume_recency", "group": "Sightings",
      "band": "strong",
      "points": { "cap": 24, "none_recent": -4 } },

    { "id": "sightings.false_positive", "group": "Sightings",
      "band": "moderate",
      "points": { "per": -3, "cap": -26 } },

    { "id": "attribution.galaxy", "group": "Attribution",
      "band": "strong",
      "points": { "per_cluster": 7, "cap": 21, "absent": -7 } },

    { "id": "lifecycle.warninglist", "group": "Lifecycle",
      "band": "weak",
      "points": { "no_hit": 6, "false_positive_hit": -38 } },

    { "id": "record.temporal_precision", "group": "Lifecycle",
      "band": "weak",
      "points": { "dated": 4, "undated": -6 } }
  ],

  "thresholds": {
    "lean_supermajority": 0.66,
    "quality_bands": { "high": 60, "medium": 30 },
    "quality_high_min_signals": 4
  },

  "escalations": [
    { "id": "conflict:known-infrastructure-vs-reporting",
      "enabled": true, "emits": "contested",
      "when": { "warninglist_category": "known",
                "min_independent_reports": 3 } }
  ],

  "exclusions": [
    { "id": "sightings.self", "within_hours": 1 },
    { "id": "feeds.mirrored", "dedupe_by": "provider" },
    { "id": "evidence.window", "days": 90, "min_occurrences": 10000 }
  ],

  "relevance": {
    "clock": "last_independent_corroboration",
    "type_rule": "shortest",
    "aging_fraction": 0.33,
    "lag_uncertain_days": 30,
    "ttl_days": { "default": 180, "ip-dst": 90, "ip-src": 90,
                  "domain": 120, "url": 60, "md5": 730, "sha256": 730 }
  },

  "reference": {
    "org_trust":            { "<organisation-uuid>": "B" },
    "warninglist_category": { "<warninglist-name>": "known" }
  },

  "enrichment": {
    "auto_run":     { "ip-dst": ["virustotal"], "domain": ["dns"] },
    "cost_posture": "allow_external",
    "locality":     { "dns": "local" }
  }
}
```

Illustrative, not normative — the field-level contract is each phase's job.
What matters here is the shape and the division of labour:

**`signals`** (phase 2) — the list. Each entry names a signal implementation by
`id`, places it in a ledger group, and gives it a band and its points. **This
is the only extensible section**: a new signal appends here, the existing
ledger renders it with no template change, and §5.1's invariant still holds
because the contract is "emit one row, contribute one signed integer".

Under **D12** the extensibility is not only structural. The implementation
behind an `id` is **discovered from the filesystem**, so a signal MISP does
not ship is a PHP file an admin drops in `app/Lib/ValueSignals/` — no core
edit, no release, nothing hardcoded to append to. `escalations` resolves to
implementations the same way. `03-signals.md` §8.

**`thresholds`** (phase 3) — the lean derivation's boundaries and the quality
bands.

**`escalations`** (phase 3) — named rules that emit a **contested** lean
rather than contributing to the ledger.
`conflict:known-infrastructure-vs-reporting` is already on screen today,
rendered as `Conflict rule: <text>`, with a namespace prefix that implies
siblings.

**`exclusions`** (phase 4) — filters applied to evidence *before* scoring. What
they exclude is reported on the page in `not_counted`, which today conflates
profile policy with ACL truth; phase 4 separates them.

**`relevance`** (phase 5) — the third axis's configuration: the clock, the
per-type TTLs, and the temporal-precision tripwires.

**`reference`** (phase 6) — what the analyst believes about their sources.
Override maps, keyed by uuid, empty by default.

**`enrichment`** (phase 7) — which modules this profile cares about for a
type, and whether it will contact third parties to use them. **Under D15 it
declares rather than triggers**: the Enrichment tab arrives with the declared
modules ticked and a run still takes a press, because nothing in MISP records
that a module ran and *"run them on page open"* therefore means *"run them on
every page open"*. `locality` is its override map, in the `reference` mould —
the shipped roster says which modules answer from inside the instance and an
operator who repointed one says so here.

### 3.1 Not in a profile

- **Presentation.** Default tab, panel state, hidden columns. Those are
  `user_settings`, four of their kind already live there, and this feature adds
  nothing to that registry (D4). A version bump on the object that explains a
  score should never mean somebody changed their default tab.
- **Anything that widens instance policy.** A profile picks enrichment modules
  from the set the instance has enabled; it cannot enable one. Same shape for
  warninglists and taxonomies — a profile reweights what exists.
- **Export semantics.** The profile's TTL does not gate `restSearch` today.
  That is phase 10, and it is called out here because "per-type TTL" reads like
  an export feature and is not one yet.

## 4. Ownership and resolution

One table, one JSON blob, an ownership triple of `user_id` / `org_id` /
`default` with exactly one set (D4). Resolution is D3's nearest-owner-wins:

```
the viewer's own profile        (user_id = me, enabled)
  else their organisation's     (org_id = my org, enabled)
  else the instance default     (default = 1, enabled)
```

Exactly one profile is therefore in force for any (viewer, value) pair, which
is what lets the hero name it — and it already tries to, in two places:
`value_verdict_meta.ctp:40` on the Verdict tab and `value_verdict_card.ctp:56`
on the Overview rail card.

**A fork is a full copy** with a fresh uuid and no memory of its parent (D5).
The consequence is accepted rather than mitigated: an improved default never
reaches an existing fork. This is why every map in §3 is an override set —
a fork that overrides nothing keeps tracking the underlying source, so the
frozen surface is only what the analyst deliberately changed.

**Editing the default requires site admin**, mirroring
`DecayingModel::isEditableByCurrentUser()`. For an ordinary analyst, fork is
the only path, which makes fork a first-class one-click action rather than the
export/import round trip `DecayingModelController` offers today.

## 5. Invariants

Five properties the page guarantees today or that these decisions create. Each
is cheap to break by accident and expensive to restore.

### 5.1 Contributions sum to the score, exactly

84, 91 and 93 are the arithmetic sums of their ledger contributions, to the
unit. Verified against the fixture; `../value-profile-page.md` §6 step 4 checks
it as an acceptance criterion.

This forbids a whole class of design: no normalisation, no calibration, no
model whose output is narrated after the fact. A weight is not a multiplier on
an opaque sub-score — the number in the ledger row **is** what the profile
produced. It is also what makes a profile *diffable*: change a weight and every
affected row shows its old and new contribution, and the totals still add up.

### 5.2 Quality is lean-anchored support, not a malice reading

A quality of 84 on a threat lean and 91 on a benign lean both mean *well
evidenced*. A `with` row supports the record's assertion; an `against` row
disputes it — which is why wide reporting is **−11** on the benign value.

**A single threat-signed accumulator, anchored to the lean, produces all of
this** (D11, `04-dispositions.md` §2), verified against the fixture:

| Value | Ledger, threat-signed | Lean | Anchored sum | Rendered as |
|---|---|---|---|---|
| `185.234.219.24` | `28, 9, 24, −6, 14, 5, 12, −8, 6` | threat | **+84** | threat · quality 84 |
| `45.155.205.233` | `31, 7, 24, −5, 17, 12, 7` | threat | **+93** | threat · quality 93 |
| `8.8.8.8` | `11, −13, −26, 4, −7, −38, −16, −6` | benign | **+91** (rows flipped) | benign · quality 91 |

Signals declare points *toward or away from threat* and never see the lean;
the engine multiplies by the lean's polarity at assembly. A row's rendered
`direction` is its anchored sign — `with` supports the assertion, `against`
disputes it — which is why *"4 organisations carry an occurrence"* renders as
a disputing row on the benign value while *"4 independent organisations
reported it"* supports the malicious one. An anchored sum that goes
**negative** — the record disputing its own assertion — emits the contested
lean (`04-dispositions.md` §3, rule 7).

**Consequence: `direction` is not a profile field.** It is computed, as the
sign of one number. An engine that stored a fixed direction per signal would
have to store it twice and keep the two in step.

### 5.3 A verdict from no signals names no profile

`value_verdict_meta.ctp:38` already enforces it: *"naming the profile that
would have weighted it claims a computation that did not happen."* The UNKNOWN
value renders no profile name. This survives every phase.

### 5.4 Every number is the viewer's

Occurrence visibility is per viewer, so the verdict is never *"the community's
view"*, only *"the view available to you"* — `00-contract.md` §14.6. Profiles
add a **second, independent** reason two readers differ. Today's standing
caveat explains one of them; whether it must now say both is Q9, open.

### 5.5 Not stored, not synchronised

The hero renders it, and render-time computation is what makes per-viewer
weighting cheap and analyst-authored signals free of any sync blast radius.

**Phase 10 spends this on a different object, not on this one** (D10):
verdict-gated `restSearch` cannot render-time-compute ten thousand verdicts
per call, so a background worker materialises an **instance verdict** — one
row per value, computed under the instance default profile from
instance-wide visibility — and exports filter on the stored row. The
viewer's verdict is never stored, so the hero's sentence stays true for the
thing it describes; the instance verdict is a second object with a different
scope, and the page names it as such
([`11-restsearch.md`](11-restsearch.md) §3.3).

**Actionable from phase 2, and cheap:** the engine is a tool with no view
dependency. If scoring logic ends up in a `.ctp`, phase 10 becomes a rewrite
instead of a new caller.

## 6. Shipped copy this feature makes wrong

Every phase inherits the rule that a claim on screen is either satisfied or
deliberately retracted. These are already wrong, or become wrong:

| What it says | Where | Why it breaks |
|---|---|---|
| `Weighting profile default-v3` | `value_verdict_meta.ctp:40`, `value_verdict_card.ctp:56` | The object holds more than weighting, and the name becomes real and linkable |
| *"An instance admin can edit the profile"* | `composition_note`, every scored value | Under D3 most readers see a profile they or their org own |
| *"No sighting for 45 days → decay takes the score under 50"* | `changers`, malicious value | There is no decay under D7; it is a TTL against last corroboration |
| `NIDS decay score`, dashed comparison line | verdict `curves`, fixture 1133 / 3535 / 11463 | The page stops reading `decaying_models` (D7). Phase 5 proposes the TTL runway in its place |
| Per-model decay bars | `value_lifecycle.ctp`, `value_sighting_decay.ctp` (161 + 259 lines) | Replaced by a per-value staleness statement, phase 5 |
| Decay curve overlay | `value_sighting_chart.ctp:447` | Same |
| *"3 or more false-positive sightings from 2+ orgs → drops to SUSPICIOUS"* | `changers`, three fixture strings | `SUSPICIOUS` never existed in `ValueDisposition::TREATMENTS`, and under D11 it is dropped, not added — the strings rephrase in band-and-lean vocabulary (`04-dispositions.md` §7) |

## 7. What this feature does not do

- **Deprecate MISP's decaying models.** D7 is a decision about one page and one
  profile. `decaying_models`, `excludeDecayed`, the decaying tool and the REST
  surface are untouched everywhere else in MISP.
- **Gate exports.** Phase 10, and not before.
- **Sync.** A profile is instance-local. It has a uuid so that export/import
  and a shipped default can match across instances, not so that peers exchange
  them.
- **Write anything about a value.** Notes, opinions, tags and `to_ids` are
  [`../value-profile-writes.md`](../value-profile-writes.md), which this
  feature meets only at phase 7's enrichment cache.
- **Score anything but a value.** Attribute- and event-level scoring is what
  `decaying_models` is for.

## 8. Open questions

Carried from [`00-discovery.md`](00-discovery.md) §10 — except Q13, raised
2026-09-03 in [`11-restsearch.md`](11-restsearch.md) §7 — each against the
phase that has to answer it.

**Q5 and Q7 are no longer here either**, both closed 2026-09-07 when phase 1
was built. Q7 became **D13** — no new permission flag, `perm_admin` for an org
profile, nothing for a user's own. Q5 became **D14** — the band is editorial
and declared per signal, which the fixture had already forced by putting `7` in
two different bands.

**And Q10 is gone**, closed 2026-09-07 as **D15** when phase 7 was
built. It asked how much of the enrichment plumbing was in scope, and the
answer is the recommendation the phase document had already made: none of it.
The profile declares, the tab pre-selects, and the badge waits for the
last-run store — which is still specified by `../value-profile-writes.md` §6.4
and built by nobody. What the closure added to the recommendation is that
*inert* was not available: the posture needs to know which modules leave the
instance, nothing in MISP records that, and so `ModuleLocality` ships the
knowledge as code ([`08-enrichment.md`](08-enrichment.md) §3).

**Q11 is no longer here.** It asked whether an extension point ships in v1 and
whether analyst-authored signals are visible to others; both halves were
answered 2026-09-07 as D12 (`03-signals.md` §8). A signal is a class
discovered on the filesystem, so "visible to others" was never a per-user
question — a file is instance-wide by construction, and what a profile carries
is whether it is enabled.

| Q | Question | Lands in |
|---|---|---|
| Q9 | Must the standing per-viewer caveat now state **both** reasons two readers differ, ACL and profile? **Half-answered, 2026-09-07:** phase 4 removed the ACL half from the page entirely (`05-exclusions.md` §7.2), and phase 6 made the profile half real — an org's grades now move a row for a reason invisible in the rows, which is why the *ledger row* names the weighting (`07-reference.md` §2.5). What is left for phase 9 is whether the hero needs to say it too | phase 9 |
| Q13 | What may `includeVerdict` attach, and to whom? Per-caller computation is ruled out; the gate is a role, the host org, fully-public evidence, or a composition of the three | phase 10 |

Two more the corpus hands to this feature, both from
[`../value-profile-verdict-engine.md`](../value-profile-verdict-engine.md) §4:

- **Which opinions count** — value-scoped, occurrence-scoped, or both. If
  opinions feed a signal, the answer changes the score
  (`../value-profile-writes.md` §10.1). Lands in phase 2.
- **The opinion colour contradiction** — the Overview preview paints "Agree"
  green while the Verdict histogram paints anything above 50 red. Stays with
  the engine work, not the profile.
