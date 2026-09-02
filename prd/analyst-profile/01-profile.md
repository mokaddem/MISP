# PRD: The Analyst Profile

**The main document. This one sets the picture and carries the state table.**
Each phase is specified in its own file; §1.4's table is the only phase-level
record and is the thing to update as each lands.

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
  nobody touching it.
- **Empty means "as before".** Every map in a profile is an override set. An
  empty org-trust map weights all organisations equally; an empty warninglist
  map defers to the warninglists table. Adding the feature changes no
  behaviour until someone edits something.
- **Honest states over silent defaults.** A profile naming a module the
  instance disabled, a TTL for a type the value does not have, an org trust
  entry for an org that has left — each renders as a stated condition, not a
  quiet omission. The page has form for this.

### 1.4 Where this stands

**Nothing is built.** Phases 1–6, 8 and 9 are specifications; phase 7 is
blocked on a store that does not exist; phase 10 is a recorded direction with
its work deliberately deferred.

The order below is a dependency order, not a schedule. Phase 1 gates
everything. Phases 2–6 are the profile's six sections and can proceed
independently once phase 1 lands. Phase 9 is the payoff — the Verdict tab goes
live — and needs 1–5.

| Phase | What | Written up in | Status |
|---|---|---|---|
| 1 | **The store** — the table, the model, ownership and resolution, the shipped default, fork, permissions | [`02-store.md`](02-store.md) | specification |
| 2 | **Signals and the engine** — the `signals` section, and the mechanism that turns it into a ledger and a score | [`03-signals.md`](03-signals.md) | specification |
| 3 | **Dispositions** — `thresholds` and `escalations`; score-to-disposition bands, the conflict rules, `changers`, and SUSPICIOUS | [`04-dispositions.md`](04-dispositions.md) | specification |
| 4 | **Exclusions** — the `exclusions` section, and splitting policy from ACL in `not_counted` | [`05-exclusions.md`](05-exclusions.md) | specification |
| 5 | **Staleness** — per-type TTL against last independent corroboration, and retiring `decaying_models` from the page | [`06-staleness.md`](06-staleness.md) | specification |
| 6 | **Reference** — per-org trust and warninglist category overrides | [`07-reference.md`](07-reference.md) | specification |
| 7 | **Enrichment defaults** — the module list and the top-level badge | [`08-enrichment.md`](08-enrichment.md) | **scope note — blocked.** Needs the per-value/per-module last-run store, which does not exist |
| 8 | **The editor** — index, view, edit, fork, and the profile simulator | [`09-editor.md`](09-editor.md) | specification |
| 9 | **Wiring the Verdict tab live** — the page reads a profile, and the shipped copy that is now wrong gets corrected | [`10-wiring.md`](10-wiring.md) | specification |
| 10 | **The verdict in restSearch** — verdict-based freshness gating, replacing `excludeDecayed` | [`11-restsearch.md`](11-restsearch.md) | **direction only.** Named so the page may stop explaining export gating without that being an unrecorded regression |

The Value Profile campaign's own tab-level table
([`../value-profile-page.md`](../value-profile-page.md) §1.4) carries one row
pointing here, and `../value-profile-live/00-contract.md` §14.12's panel board
is where the Verdict tab's panels move from fixture to live when phase 9 runs.

## 2. Decisions

Taken in the grilling session of 2026-09-02. Each is recorded in
[`00-discovery.md`](00-discovery.md) against the question it answers, with the
reasoning and the rejected alternatives; this is the index.

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

Still open: Q5, Q7, Q9, Q10, Q11 — see §8.

## 3. What a profile contains

Six sections. Five are scoring (D2) and one is enrichment, which was in the
ask from the start and is not part of the score.

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

    { "id": "lifecycle.staleness", "group": "Lifecycle",
      "band": "moderate",
      "points": { "fresh": 12, "expired": -18 },
      "config": {
        "clock": "last_independent_corroboration",
        "decay_speed": 1,
        "ttl_days": { "default": 180, "ip-dst": 90, "ip-src": 90,
                      "domain": 120, "url": 60, "md5": 730, "sha256": 730 }
      } },

    { "id": "lifecycle.warninglist", "group": "Lifecycle",
      "band": "weak",
      "points": { "no_hit": 6, "false_positive_hit": -38 } }
  ],

  "thresholds": {
    "malicious_floor": 65,
    "suspicious_floor": 40,
    "conflict_min_reports": 3
  },

  "escalations": [
    { "id": "conflict:known-infrastructure-vs-reporting",
      "enabled": true, "emits": "CONFLICTED" }
  ],

  "exclusions": [
    { "id": "sightings.self", "within_hours": 1 },
    { "id": "feeds.mirrored", "dedupe_by": "upstream_source" }
  ],

  "reference": {
    "org_trust":            { "<organisation-uuid>": "B" },
    "warninglist_category": { "<warninglist-uuid-or-name>": "known" }
  },

  "enrichment": {
    "auto_run":     { "ip-dst": ["virustotal"], "domain": ["dns"] },
    "cost_posture": "local_only"
  }
}
```

Illustrative, not normative — the field-level contract is each phase's job.
What matters here is the shape and the division of labour:

**`signals`** (phase 2) — the list. Each entry names a signal implementation by
`id`, places it in a ledger group, and gives it a direction, a band and its
points. **This is the only extensible section**: an analyst-authored signal
appends here, the existing ledger renders it with no template change, and
§5.1's invariant still holds because the contract is "emit one row, contribute
one signed integer".

**`thresholds`** (phase 3) — scalars that the bands and the escalations read.

**`escalations`** (phase 3) — named rules that **replace** the disposition
rather than contributing to the score. `conflict:known-infrastructure-vs-reporting`
is already on screen today, rendered as `Conflict rule: <text>`, with a
namespace prefix that implies siblings.

**`exclusions`** (phase 4) — filters applied to evidence *before* scoring. What
they exclude is reported on the page in `not_counted`, which today conflates
profile policy with ACL truth; phase 4 separates them.

**`reference`** (phase 6) — what the analyst believes about their sources.
Override maps, keyed by uuid, empty by default.

**`enrichment`** (phase 7) — which modules run on page open, and whether this
profile will spend quota or contact third parties to do it.

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

### 5.2 The score is support for the disposition, not a malice reading

MALICIOUS 84 and BENIGN 91 both mean *well evidenced*. A `with` row supports
the stated verdict; an `against` row argues against it — which is why wide
reporting is **−11** on the benign value.

**A single threat-signed accumulator produces all of this**, which is the
mechanism phase 2 is built on and is verified against the fixture:

| Value | Ledger, threat-signed | Sum | Rendered as |
|---|---|---|---|
| `185.234.219.24` | `28, 9, 24, −6, 14, 5, 12, −8, 6` | **+84** | MALICIOUS 84 |
| `45.155.205.233` | `31, 7, 24, −5, 17, 12, 7` | **+93** | MALICIOUS 93 |
| `8.8.8.8` | `11, −13, −26, 4, −7, −38, −16, −6` | **−91** | BENIGN 91 |

Each signal contributes points *toward or away from threat*. The **sign of the
total picks the disposition** and its **magnitude is the score**. A row's
rendered `direction` is then derived — `with` when the row's sign matches the
total's, `against` when it does not — which is why *"4 organisations carry an
occurrence"* renders as a downward row on a benign value while *"4 independent
organisations reported it"* renders upward on a malicious one.

**Consequence: `direction` is not a profile field.** It is computed. The
profile declares signed *points*, and the sign of the points is the polarity.
An engine that stored a fixed direction per signal would have to store it twice
and keep the two in step.

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

**Phase 10 is where this gets spent**, knowingly: verdict-gated `restSearch`
cannot render-time-compute ten thousand verdicts per call, so it needs a cached
score and the hero's sentence has to change. The mitigation is recorded now
because it also bounds the cost: cache by **`(value, profile)`**, never
`(value, user)`. Profiles are shared, so cardinality is profiles × values —
a two-hundred-analyst instance realistically runs one or two profiles per org.

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
| *"3 or more false-positive sightings from 2+ orgs → drops to SUSPICIOUS"* | `changers`, three fixture strings | `SUSPICIOUS` is not in `ValueDisposition::TREATMENTS` and renders as UNKNOWN. Phase 3 adds it |

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

Carried from [`00-discovery.md`](00-discovery.md) §10, each against the phase
that has to answer it.

| Q | Question | Lands in |
|---|---|---|
| Q5 | Is a weight **band** derived from the contribution or an independent editorial label? They overlap in the fixture — a `strong` of +17 sits below a `moderate` of +16 | phase 2 |
| Q7 | Which permission gates ownership — ride `perm_decaying`, add a flag, or `perm_admin` for org-scoped and nothing for user-scoped? | phase 1 |
| Q9 | Must the standing per-viewer caveat now state **both** reasons two readers differ, ACL and profile? | phase 9 |
| Q10 | How much of the enrichment plumbing is in scope — the last-run store and the queued path both block the badge | phase 7 |
| Q11 | Is an **extension point** in v1? D2 makes `signals` structurally extensible; whether analyst-authored signals ship, and whether they are visible to others, is not decided | phase 2 |

Two more the corpus hands to this feature, both from
[`../value-profile-verdict-engine.md`](../value-profile-verdict-engine.md) §4:

- **Which opinions count** — value-scoped, occurrence-scoped, or both. If
  opinions feed a signal, the answer changes the score
  (`../value-profile-writes.md` §10.1). Lands in phase 2.
- **The opinion colour contradiction** — the Overview preview paints "Agree"
  green while the Verdict histogram paints anything above 50 red. Stays with
  the engine work, not the profile.
