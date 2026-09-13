# PRD: Analyst Profile — the assessment: lean, relevance, quality

**Decision record — D11, decided 2026-09-03.** Not a phase. This document
redefines the object the Verdict tab computes, and the phase documents are
read through it: phases 2, 3 and 5 predate it and carry banners pointing
here until their rework lands. It grew out of `review-2026-09-02.md` A6 and
the verdict-semantics discussion that followed it.

---

## 1. Why the verdict had to go

The engine reads MISP tables, so the only thing it can honestly measure is
**the record** — what the community wrote down about a value. The verdict
took that measurement and worded it as a fact about **the world**:
`MALICIOUS 84` promises a thing no query can know. The corpus half-admitted
this from the start — `01-profile.md` §5.2 says verbatim that *"the score is
support for the disposition, not a malice reading"* — but the vocabulary
kept overclaiming, and every unverifiable assumption underneath (a missing
`first_seen`, a timestamp standing in for one) corrupted a claim the page
had no right to make.

The example that forced it: a phishing URL encoded two months after the
incident, no `first_seen`. The staleness clock falls back to the creation
timestamp, so the engine believes the value is fresh, and the page says
*"MALICIOUS, current"* about infrastructure that died in June. Under a
maliciousness verdict there is no way to say *"I do not know when this was
actually seen"* — the uncertainty has nowhere to go, so it silently becomes
a lie. The fix is not a better guess; it is a vocabulary in which the
uncertainty is a stated, scored property of the record.

The second forcing observation: a maliciousness verdict conflates three
questions with three different answers. An old malware hash **is still
malicious** — its nature never changes — but its exploitability is so low
that nobody should spend attention on it, and the record documenting it may
be excellent or garbage independently of both. One signed number cannot say
*"well-documented historic threat"*. Three axes can.

## 2. The three axes

| Axis | The question it answers | Sourced from | Rendered as |
|---|---|---|---|
| **Lean** | What does the record assert this value is, or was? | `to_ids` stance across occurrences; warninglist category; false-positive sightings | `threat` / `benign` (known infrastructure) / `contested` / `none` |
| **Relevance** | Does that assertion still matter operationally, today? | the TTL machinery: clock, per-type TTL, temporal precision | `current` / `aging` / `expired` / `timeline uncertain` |
| **Quality** | How much can the record be trusted? | the scored ledger: corroboration breadth, org trust, attribution, published ratio, temporal precision | the number, with the ledger as its audit trail |

### 2.1 Lean — the record's assertion, read rather than invented

`to_ids` is the community's explicit, per-occurrence, machine-readable vote
that a value is an actionable threat indicator. It is already in the
database, it is what IDS exports already filter on, and reading it makes the
engine's lean a **report of the community's assertion** instead of an
invented judgement. Illustrative derivation (the phase 3 rework owns the
contract):

```
none       no occurrences
contested  a known-category warninglist hit alongside threat assertions
           (the existing conflict escalation, with a cleaner meaning), or
           a to_ids stance split beyond tolerance
threat     threat assertions dominate
benign     no threat assertion, or false-positive evidence dominates,
           or a false_positive-category warninglist hit carries it
```

Lean is **categorical and timeless**. A malware hash leans `threat`
forever; time cannot move it, only new assertions can. The fixture already
renders contested lean without naming it — the `to_ids` conflict rows
(ip-src says 1, ip-dst says 0) are exactly this.

**The honesty note that makes it safe:** `to_ids` is noisy in practice —
MISP sets per-type defaults, feed imports set it wholesale, many orgs never
curate it. The three-axis structure absorbs this instead of being corrupted
by it: lean reports what the record asserts, and **quality is where sloppy
assertions get graded**. One uncurated org's `to_ids = 1` is a threat lean
with low quality; four independent orgs deliberately confirming is the same
lean with high quality. The axes cover each other's weaknesses.

### 2.2 Relevance — the timing, quarantined

Everything temporal moves here: the per-type TTL, the
`last_independent_corroboration` clock, `expires_at`, the TTL runway —
`06-staleness.md`'s machinery wholesale, no longer expressed as ledger
points. A6's verdict-relative staleness rule migrates into this axis, which
is where it always belonged: fresh corroboration makes the assessment more
current, expiry makes it less, and the clock never touches the lean —
resolving A6's failure cases *by construction* rather than by a sign trick.

Relevance is also where the phishing example's real problem becomes
expressible: **temporal precision is an input.** No `first_seen`, a large
created-to-published lag, a timestamp standing in for an observation date —
each degrades relevance to `timeline uncertain`, stated on the page with
the lag it measured, and deducts from quality (§2.3). The uncertainty
finally has somewhere to go.

### 2.3 Quality — the scored ledger, and the exact-sum invariant's new home

The accumulator, the ledger, trust weighting, exclusions, the evidence
window — phase 2's machinery survives intact, and **the exact-sum invariant
now governs the quality number alone**: contributions sum to quality, to
the unit, with nothing normalised. What changes is what the number claims:
not "how malicious" but "how much corroborated, attributed, temporally
precise weight stands behind the record".

The four-segment `confidence` bar — the one field the corpus flagged as
having no derivation story (`06-staleness.md` §5) — stops being a mystery:
it is the quality banding, derived from the quality score and the fired
signal count.

Temporal precision joins the catalogue as a quality signal: a record with
`first_seen` set and a short encoding lag earns points a
timestamp-as-proxy record does not.

**Amended 2026-09-13 — the invariant governs the quality *rows*.** The
sentence above says *contributions sum to quality*, and until the axis
split it meant every contribution, including the two signals that read
the value. Those are §2.1's inputs, not §2.3's: the list above names
corroboration breadth, org trust, attribution, published ratio and
temporal precision, and a warninglist hit is none of them. So the
quality rows sum to the quality, to the unit, and the lean rows sum to
`lean_weight` beside it — two exact sums over two axes rather than one
over a mixture. The ledger table renders the first; the lean band
renders the second, which is where a reader looks to find out how the
reading was decided anyway.

### 2.4 The sign, re-anchored — decided 2026-09-03, narrowed 2026-09-13

Profiles and signal implementations stay **threat-signed**; the engine
anchors the ledger to the lean at assembly — `row = points × polarity`,
and `direction` is the row's own sign (supports / disputes). Full
mechanics in `04-dispositions.md` §2. Three things carried the decision:
the arithmetic is unchanged (the benign demo value's quality is the same
91, its rows flipped to match the `with`/`against` arrows the fixture
already renders); review B2's direction contradiction dissolves —
direction is one sign, no total, no escalation special-case; and a
**negative anchored sum** becomes the one honest state the old model
could not express — a record disputing its own assertion, emitting the
contested lean.

**Narrowed 2026-09-13: the polarity reaches the lean rows and nothing
else** (`review-2026-09-13.md` §A1). The decision above is right about
the rows it was reasoning from and wrong about how far they reach, and
the evidence it cited is where the gap shows: *the benign demo value's
quality is the same 91* was true of the fixture, whose ledger contained
`8 of 9 occurrences set to_ids = no` (+13) and `Decayed under both
models` (+16). §6 promoted the first out of the catalogue into the lean
derivation and moved the second into the relevance axis with no ledger
points, in this same document. **The flip stayed and the two rows that
justified it left in the same rework**, and what it then flipped was
nine signals that measure the record.

The result on real rows: *4 independent organisations reported it*
rendering `−28` **against** a benign reading, *nobody has sighted this
value* rendering `+4` **for** one, and a benign quality that scored
higher the emptier the record was — §2.3's definition inverted. So a
signal declares its axis, quality rows keep their declared sign
whatever the lean, and `direction` on a quality row means *adds to /
deducts from the record* rather than *supports / disputes the lean*.
The two meanings are drawn apart rather than left to be inferred from a
sign.

A negative sum survives as an honest state and changes owner: it is a
negative **`lean_weight`** that emits the contested lean, which is what
the sentence above was reaching for — *a record disputing its own
assertion*. A negative **quality** now means only what it says, that
the absences outweighed what the record carries, and the `low` band
beside it says the same thing in a word.

## 3. The three test values

**The late-encoded phishing URL.** Lean `threat` — the report was true.
Relevance `expired / timeline uncertain` — url TTL 60 days, no `first_seen`,
61-day encoding lag, stated. Quality low — one org, no sightings, no
temporal precision. The page reads: **asserted threat · thin record ·
likely over.** Every word defensible from rows; the old design said
"MALICIOUS, current" and was wrong twice.

**The old malware hash.** Lean `threat`, permanently. Relevance `historic`.
Quality high — well documented, attributed, corroborated. **Well-documented
historic threat** — the phrase an analyst would actually say, impossible in
the one-number design.

**`8.8.8.8`.** Lean `benign` — `to_ids` 0 everywhere, resolver list.
Relevance current, quality high. The benign story survives whole; the
contested story (a known-infrastructure hit against three real reports)
becomes the `contested` lean, which is the old conflict escalation stated
as what it always was.

## 4. What the reframe dissolves

Problems that were artifacts of forcing three axes through one number:

- **SUSPICIOUS** — no fifth disposition, no new colour token, no glyph
  collision. It was always "a weak threat-leaning record": lean `threat`,
  low quality. The three fixture strings promising it are rephrased in
  phase 9's copy pass.
- **UNKNOWN's two meanings** (`04-dispositions.md` §3.2's accepted
  conflation) — separates naturally: *no record* is lean `none`; *thin,
  inconclusive record* is a lean with low quality and a full ledger.
- **The underived confidence bar** — becomes the quality banding (§2.3).
- **The tug bar's `unresolved` bucket** (`review-2026-09-02.md` B3, an
  underivable third quantity) — under a contested lean the tug is assertion
  counts against benign evidence, both derivable; the exact rendering is
  the phase 3 rework's to settle.
- **Disposition floors racing the catalogue** (review B7, the larger half)
  — there is no `malicious_floor` for a growing signal catalogue to
  silently inflate past. Quality banding remains a calibration to state,
  with far lower stakes: a band misplaced miscolours a gauge, it does not
  flip MALICIOUS.

## 5. The name — Assessment

**The object is the Assessment.** "Verdict" was the overclaim distilled: a
court's final word, exactly what a reading of an evolving record is not. An
assessment is what an analyst produces from available evidence — CTI-native,
explicitly provisional, and honest about being a judgement *of the record*.
Rejected: **Judgement** (same finality), **Reading** (too vague to name a
REST parameter after), **Rating** (commercial), **Quality** alone (it is
one axis of three, and it cannot carry the lean).

The admiralty anchor makes the identity precise: `org_trust` is
source-reliability going in (`07-reference.md` §2), and the assessment is
information-credibility coming out — the taxonomy's other predicate, whose
published semantics MISP already ships. The feature is **an automated
admiralty assessment of the value's record**, which is a thing CTI doctrine
already has words for and nobody can accuse of lying.

**The rename map**, applied at implementation time and to every phase doc
at its rework (specs are read through this map until then):

| Was | Becomes |
|---|---|
| the Verdict tab | the Assessment tab (phase 9 copy pass) |
| verdict (the computed object) | assessment |
| the instance verdict (phase 10) | the instance assessment |
| `value_verdicts` | `value_assessments` |
| `ValueVerdictTool` | `ValueAssessmentTool` |
| `includeVerdict` | `includeAssessment` (attaches lean, relevance, quality) |
| `minVerdictScore` | `minQuality` |
| `excludeStale` | unchanged — it was a relevance gate all along |
| disposition (MALICIOUS / BENIGN / CONFLICTED / UNKNOWN) | lean (`threat` / `benign` / `contested` / `none`) |
| score | quality |
| confidence (the bar) | quality band |

`ValueDisposition::TREATMENTS` and the `value_verdict_*.ctp` templates are
shipped code; they are renamed when phase 9 touches them, not before. The
colour tokens survive with their meanings (`--vp-mal` serves the `threat`
lean).

**A satisfying alignment fell out of the mapping:** the three axes are the
three gates MISP exports already care about. `to_ids = 1` is the standing
restSearch filter for lean; `excludeStale` (né `excludeDecayed`) gates
relevance; `minQuality` is the one new gate phase 10 adds. The Assessment
tab becomes the page that *explains the export gates*, which is the most
defensible identity this feature could have.

## 6. What survives, what reworks

| Phase | Fate under D11 |
|---|---|
| 1 — store | **Untouched.** Ownership, resolution, fork, the shipped default: all orthogonal |
| 2 — signals | **Mechanism survives; scope narrows to quality.** The accumulator, exact-sum, silent/fired/not_counted, the evidence window: intact. `reporting.to_ids_stance` is promoted out of the catalogue into the lean derivation; temporal precision joins as a quality signal; the catalogue's rows re-read as quality contributions |
| 3 — dispositions | **Restructured — rework landed 2026-09-03** (`04-dispositions.md`). Score bands → lean states; `band()` → the lean derivation plus quality banding; escalations survive as contested-lean rules; `changers` survives per axis; SUSPICIOUS is dropped, not added |
| 4 — exclusions | **Untouched.** Filters feed the quality ledger as before; `not_counted` unchanged |
| 5 — staleness | **Becomes the relevance axis — rework landed 2026-09-03** (`06-staleness.md`). Same TTL, clock, runway, honest states; no ledger points — A6's verdict-relative rule is subsumed by construction. Temporal-precision inputs (missing `first_seen`, encoding lag) joined it |
| 6 — reference | **Untouched, strengthened.** `org_trust` is now explicitly the source-reliability half of an admiralty assessment; warninglist categories feed the lean |
| 7 — enrichment | **Untouched** |
| 8 — editor | **Adjusts.** The simulator diffs quality ledgers and shows lean/relevance flips per regression case; threshold strips become quality bands |
| 9 — wiring | **Copy pass grows.** Tab rename, hero rewording (*lean · relevance · quality*), the fixture's verdict arrays re-expressed on three axes |
| 10 — restSearch | **Rename-level.** The materialised row stores lean, quality, `expires_at` (relevance); gates as in §5's map. Q13 (what `includeAssessment` may attach, to whom) carries over unchanged |

## 7. Open points the rework must settle

Four of the five were settled by the 2026-09-03 rework: the lean
derivation's contract (`04-dispositions.md` §3), the quality banding
(`04-dispositions.md` §6), the contested tug's two quantities
(`04-dispositions.md` §5 — B3's third bucket retired), and relevance on a
benign lean — it renders on every lean, copy adapted (`06-staleness.md` §5).

The fifth closed on 2026-09-13: the hero's composition — three axes in one
line without three competing numbers — is `ValueSummaryTool`, which names
the lean, bands the quality in words and prints the one number nothing else
in the hero carries, the days.

The sixth opened and closed on 2026-09-13, in the read-back of the
built tab (`review-2026-09-13.md`) and the pass that followed it:

- **§2.4's anchoring was applied to the whole catalogue, and eight of
  the eleven shipped signals have no polarity to anchor.** Corroboration
  breadth, published ratio, feed presence, recency, continuity, sighting
  volume, temporal precision and technique attribution all measure the
  record rather than read it, and `row = points × polarity` reverses every
  one of them on a `benign` lean — so quality there measures how empty the
  record is, which is §2.3's definition inverted. The decision's own
  evidence was the fixture's benign ledger closing at 91, and it closed
  there on two rows §6 removed in the same rework: `to_ids` stance (+13,
  promoted into the lean derivation) and decay (+16, moved into relevance
  with no ledger points). Measured consequence on the verification
  instance: 60 of 120 values render `contested`, 55 of them by rule 7
  firing on a thin record — which is the state §4 says should read as *a
  lean with low quality and a full ledger*.

  **Settled 2026-09-13 — a signal declares its axis, and only the lean's
  anchors.** `ValueSignalBase::AXIS_LEAN` / `AXIS_QUALITY`, overridable
  per row for a signal whose poles are not all one kind. Two shipped
  signals declare `AXIS_LEAN` — `lifecycle.warninglist`'s hits and
  `sightings.false_positive` — which is §2.1's list minus the `to_ids`
  stance, and that one is not a ledger row at all. The warninglist's
  *no hit* pole declares quality per row: *nothing matched, 8 lists
  checked* is the control case, and anchored it was enough on its own
  to tip an uncontested benign value into rule 7.

  Three things follow, and §2.3 and §2.4 are rewritten around them:

  - **Quality is the sum of the quality rows**, and the lean rows sum
    to `lean_weight` beside it. A warninglist hit says nothing about
    how well documented a record is, which is why §2.3 never listed it
    among quality's sources. `8.8.8.8` bands `medium` on 57 where it
    banded `low` on `−1`.
  - **Rule 7 weighs `lean_weight`**, so it fires on a record whose own
    reading of the value disputes its assertion and not on a thin one.
    Contested went from 60 of 120 sampled values to 6.
  - **The contested cases fold out of the lean ledger**, so a case
    titled *Reads as benign* contains only rows that read the value as
    benign. On the shipped catalogue that leaves one side empty and the
    agreeing layout carries contested values, with the contradiction
    stated in the lean band. §4's *assertion counts against benign
    evidence* is the rendering that would bring the two columns back,
    and it is still open.
