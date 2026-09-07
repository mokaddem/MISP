# Analyst Profile — executive summary

**Snapshot, 2026-09-07.** This file is the entry point for someone who has
not followed the corpus. It summarises; it decides nothing. The living state
table is [`01-profile.md`](01-profile.md) §1.4, the decisions index is
§2 there, and every claim below carries a pointer to the document that owns
it.

## What this is

MISP's Value Profile page displays an assessment of a value — what the
record asserts it is, whether that still matters, how much the record can be
trusted — and until 2026-09-07 **nothing computed any of it**. The **Analyst
Profile** is the configuration object the engine reads: a forkable JSON
document holding every judgement the scoring engine needs — signal weights, thresholds, exclusions,
TTLs, source trust, enrichment defaults — so the engine can be a mechanism
rather than a shipped opinion. An instance ships one default; an organisation
or an analyst forks it and edits their copy; exactly one is in force per
viewer (nearest owner wins). **Phases 1 and 2 — the store, and the engine
that reads it — are built as of 2026-09-07**; the other eight phases are
specifications. The corpus is fifteen documents, phase by phase.

## The headline: the Assessment (D11)

The computed object is **not a maliciousness verdict** — an engine reading
MISP tables can only honestly measure *the record*, and "MALICIOUS 84"
claimed more. It is an **Assessment** on three axes
([`12-assessment.md`](12-assessment.md)):

| Axis | The question | Sourced from |
|---|---|---|
| **Lean** | what does the record assert this is? — `threat` / `benign` / `contested` / `none` | `to_ids` stance per org, warninglist categories |
| **Relevance** | does it still matter today? — `current` / `aging` / `expired` / `timeline uncertain` | per-type TTL against last independent corroboration, temporal precision |
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

## The design in fourteen decisions

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
| D14 | **A weight band is editorial, not derived from the contribution.** The fixture forecloses "derived" — `7` is both `moderate` and `weak` in it. The band says what this kind of evidence is worth in principle; the contribution says what it produced here. Closes Q5 | `03-signals.md` §5 |

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
  gap. The rule becomes a clamp in phase 3's banding
  (`03-signals.md` §11.1).
- **The engine got a budget** — an evidence-time window (90 days of row
  evidence on long-history values; whole-history aggregates always), with
  `over_correlating_values` as the hot-value give-up. Deterministic by
  design: no row caps, no stopwatch deciding content.
- **Warninglist categories exist but nothing sets or even imports them** —
  verified: 0 of 89 upstream lists, and `Warninglist::__updateList()` drops
  the field. V1 ships a hardcoded name→`known` map
  (`WarninglistCategory.php`); V2 is the upstream PR plus a one-line core
  fix, with a mechanical retirement criterion.
- **The trust scale misread its own authority** — the shipped taxonomy says
  `f = 50 = c` (neutral) and has a seventh grade `g` (deliberately
  deceptive, = 0). The scale now follows it.
- **Staleness must never pick a side** — time is its own axis (relevance),
  so silence can no longer promote a value to definite BENIGN.
- **`version` split from `revision`** — the upstream match key and the local
  edit counter collide in one column; the materialisation keys on revision.

## Status and what remains

Phases (living table: `01-profile.md` §1.4): **phases 1 and 2 are built;
3–6 and 8–10 are specifications; 7 (enrichment) is a scope note blocked on a
store that does not exist.** Build order: 1 (store) gates all → 2–6 → 8 → 9
(the tab goes live) → 10.

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

Open questions: Q9 (per-viewer caveat, phase 9), Q10 (enrichment scope,
phase 7), Q13 (`includeAssessment` exposure gate, phase 10), plus two
phase-9 items — the curves' historical derivation and the hero's three-axis
composition. **Nothing gating phases 1–5 is open any more.**

Three questions closed in three days, and two closed against this corpus's
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

External prerequisites: the `misp-warninglists` category PR and the MISP
core import fix (V2 above).

## Reading map

| File | What it holds |
|---|---|
| [`01-profile.md`](01-profile.md) | **The main PRD**: purpose, scope, principles, the decisions index, invariants, the state table |
| [`00-discovery.md`](00-discovery.md) | The discovery pass and the grilling record — why, with the rejected alternatives |
| [`02-store.md`](02-store.md) … [`11-restsearch.md`](11-restsearch.md) | Phases 1–10, one file each |
| [`12-assessment.md`](12-assessment.md) | D11 — the assessment's semantic model and rename map |
| [`review-2026-09-02.md`](review-2026-09-02.md) | The adversarial review, findings and their resolutions |
