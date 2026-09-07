# PRD: Analyst Profile — phase 3, the lean and the bands

**Specification. Nothing built. Rewritten 2026-09-03 under D11**
([`12-assessment.md`](12-assessment.md)) — the first version of this document
specified score-band dispositions (`band()`, `malicious_floor`, adding
SUSPICIOUS); that model is superseded and the rewrite replaces it wholesale.
Depends on phase 2 ([`03-signals.md`](03-signals.md)), which produces the
quality ledger this phase anchors, and on phase 6 for the warninglist
categories the lean reads.

Covers the **lean derivation**, the **quality banding**, the `thresholds` and
`escalations` sections, derived `changers`, and what happens to
`ValueDisposition`.

## 1. What ships

The lean derivation — the categorical answer to *"what does the record assert
this value is?"* — plus the re-anchoring that turns phase 2's threat-signed
sum into the quality number, plus the banding behind the four-segment gauge,
plus `changers` derived per axis.

Exit criterion: **all five regression cases reach their stated lean and
quality band, and the contested one reaches it through a named rule that
renders in the meta line.**

## 2. The re-anchoring — how quality gets its sign

**Decided 2026-09-03** (with D11; the mechanics half). Signal implementations
and profiles stay **threat-signed** — an author declares "does this evidence
point at a threat, and how hard", exactly as `03-signals.md` §2 has it, and
no `points` declaration changes. The engine then **anchors the ledger to the
lean** at assembly:

```
polarity   = +1 if lean is threat, −1 if lean is benign
row shown  = threat_signed_points × polarity
quality    = Σ shown rows                    # the exact-sum invariant's home
direction  = sign(shown row)                 # + supports the lean, − disputes
```

Checked against the fixture: the malicious value is unchanged (`+84`, every
sign as authored). The benign value's rows all flip — the warninglist hit
renders `+38` *supporting* benign, wide reporting `−11` *disputing* it — and
the sum is **`+91`: the same quality number the page already shows**, with
the same `with`/`against` arrows the fixture already renders, row for row.
The re-anchoring is the fixture's own direction semantics, stated as the
mechanism.

Two consequences:

- **`direction` is the sign of the row, full stop.** No derivation from the
  total, no escalation special-case. The old model's contradiction — phase 2
  deriving direction from `sign(total)` while §5.1 of the old text flipped it
  under an escalation — is gone (`review-2026-09-02.md` B2).
- **A negative quality is meaningful**: the record disputes its own
  assertion. It does not render as a negative gauge; it emits a contested
  lean (§3, rule 7) — the state the old model could not express.

On a **contested** lean there is no polarity; the ledger renders
threat-signed and the tug shows the two one-sided sums (§5). On a **none**
lean there is no ledger at all.

## 3. The lean derivation

Inputs, all already on the page or in the profile: the per-org `to_ids`
stance (the *Who says what* panel's column), the warninglist category
resolution (`07-reference.md` §3.2), and the named rules of §4.

**Stances are counted per organisation, not per occurrence** — one org
spamming forty `to_ids = 1` events is one vote, which is the same
independence argument `reporting.independent_orgs` already makes:

```
threat_orgs   = orgs holding ≥ 1 occurrence with to_ids = 1
benign_orgs   = orgs whose every occurrence has to_ids = 0
threat_share  = threat_orgs / (threat_orgs + benign_orgs)
```

First match wins:

```
1. no occurrences                                       → none
2. an enabled escalation fires (§4)                     → contested, named
3. false_positive-category hit
   and threat_share < lean_supermajority                → benign
4. threat_share ≥ lean_supermajority                    → threat
5. threat_share ≤ 1 − lean_supermajority                → benign
6. otherwise                                            → contested (stance split)
7. after scoring: quality < 0 (§2)                      → contested (record
                                                          disputes its assertion)
```

Checked against the regression set: the malicious values are unanimous
threat stances → rule 4. `8.8.8.8` hits the resolver list with a minority
threat share → rule 3, benign. The conflicted value is rule 2 (§4). The
median value is one org asserting → rule 4, threat — with the quality band
saying how little that is worth (§6).

**Rule 3 before rule 4 is deliberate**, and it is what makes the benign
value's `curves_note` story *reachable*: the 2025-06-24 step is the resolver
list gaining the value — the profile's knowledge changed, the lean flipped
from rule-6 muddle to rule-3 benign, same evidence. Under the old score
bands that flip needed a 105-point swing from a 44-point signal
(`review-2026-09-02.md` B5); under the lean it is one rule taking
precedence.

## 4. `escalations` — named contested rules

```json
"escalations": [
  { "id": "conflict:known-infrastructure-vs-reporting",
    "enabled": true, "emits": "contested",
    "when": { "warninglist_category": "known",
              "min_independent_reports": 3 } },

  { "id": "conflict:listed-vs-asserted",
    "enabled": true, "emits": "contested",
    "when": { "warninglist_category": "false_positive",
              "threat_share_at_least": "supermajority" } }
]
```

An escalation **names a contradiction the counting rules would flatten**. The
first is the one already on screen: a known-category hit against three or
more independent reports — both true at once, neither discounted. The second
is new and is rule 3's guard: when a false_positive-category list *and* a
supermajority of orgs disagree, silently letting either win would discard a
deliberate judgement; the contradiction is the honest answer.

When one fires: the lean is `contested`, `rule` carries the id and its
prose, rendered by `value_verdict_meta.ctp:47`, and the ledger is **not**
discarded — its threat-signed rows are what the tug is built from (§5).

**Implementations, not expressions.** `when` is declarative in the profile
but each id resolves to a class, the same as a signal (`03-signals.md`
§2.2). A general predicate language was Q11's problem and stays rejected
(`03-signals.md` §8.7).

**And discovered the same way** (D12, 2026-09-07). An escalation class is
found on the filesystem — `app/Model/ValueEscalations/` shipped,
`app/Lib/ValueEscalations/` for what an instance admin drops in — under every
rule `03-signals.md` §8 sets for signals: a dropped file is available and not
active until a profile lists its id, a colliding id is refused rather than
overriding, and a file that will not load is logged and skipped rather than
fataling. The `conflict:` namespace on the two ids above always implied
siblings; this is how they arrive without a MISP release.

One difference from a signal, and it matters: an escalation that fails to load
**cannot** be reported in `not_counted`, because `not_counted` is a property
of the quality ledger and an escalation contributes nothing to it. A rule that
could not run leaves the lean unescalated, which is a silent change of answer
— so the loader's error list has to reach the Assessment tab's meta line, not
only the admin's log. Phase 9 renders it; `03-signals.md` §8.5 is the loader's
half.

Multiple escalations firing: **first match in list order**, and the meta
line names the one that decided. Order is the profile author's to arrange —
recorded because the old text left it undefined (`review-2026-09-02.md`,
C-series).

The old model's "escalation to BENIGN over a +84 ledger" case dissolves: an
escalation no longer overrides a score-derived disposition, it names a
contradiction. An analyst who wants *"my own infrastructure, never flag it"*
wants an exclusion or a benign stance, not a contested rule.

## 5. The contested tug

The conflicted layout's tug renders **two derivable quantities**: the sum of
the threat-signed positive rows (the assertion's support) against the sum of
the negative rows (the dispute). Both come from the same ledger the other
leans render — no third bucket, no separate computation.

The fixture's `unresolved => 12` segment is **retired** in phase 9's fixture
pass: it was never derivable from the engine (`review-2026-09-02.md` B3),
and the two-sided tug says everything the layout needs.

## 6. `thresholds`, and the quality bands

```json
"thresholds": {
  "lean_supermajority": 0.66,
  "quality_bands": { "high": 60, "medium": 30 },
  "quality_high_min_signals": 4
}
```

The four-segment gauge — the field the corpus knew as `confidence`, flagged
since phase 23 as the one number with no derivation story — is the **quality
band**, derived:

```
none     empty ledger
high     quality ≥ 60 and ≥ 4 fired signals
medium   quality ≥ 30
low      otherwise
```

Bands are calibration, and the stakes are deliberately low: a misplaced band
miscolours a gauge, it does not flip MALICIOUS — which is what remains of
`review-2026-09-02.md` B7 after D11. Its two mitigations still apply, to
these numbers: the editor's band strip shows the attainable range under the
enabled catalogue, and save-time validation warns when a band exceeds it.

The **median value's calibration rule** (`03-signals.md` §7.4) restates as:
*a single-org, sighting-free record never leaves the `low` band under the
shipped default.* Same constraint, new vocabulary.

## 7. `ValueDisposition` — relabel, not extend

`ValueDisposition::TREATMENTS` keeps four entries; the keys become the lean
states and SUSPICIOUS is **dropped, not added** — a weak threat-leaning
record is `threat` at `low` quality, and needs no fifth colour token, no new
glyph, no `--vp-susp`:

| Lean | Was | Colour | `definite` |
|---|---|---|---|
| `threat` | MALICIOUS | `--vp-mal` | true |
| `benign` | BENIGN | `--vp-ben` | true |
| `contested` | CONFLICTED | unchanged | false |
| `none` | UNKNOWN | unchanged | false |

`isDefinite()` finally gets its caller: `contested` and `none` take the quiet
treatment its docblock always described, `threat` and `benign` the solid one.
**Wire it in this phase** — the reason is unchanged from the old text: the
treatment exists, is documented, and has never been styled.

The three fixture strings promising SUSPICIOUS are rephrased in phase 9's
copy pass, in band-and-lean vocabulary (*"drops to the low band"*).

## 8. `changers` — falsifiability, per axis

Still derived, now one per axis, which reads better than three score
distances ever did:

| Axis | Example, derived from the shipped default |
|---|---|
| Lean | *"One more organisation asserting `to_ids` takes the stance past 2/3 — the lean firms to threat"* |
| Relevance | *"No independent corroboration for 45 more days — the assessment expires"* (phase 5's runway, solved for the boundary) |
| Quality | *"Two more independent orgs — the record reaches the high band"* |

Derive the cheapest changer per axis, not all of them; the block renders
three rows and now has a natural one from each axis.

## 9. Verification

1. The lean derivation at every rule boundary: no occurrences; the known-rule
   firing at exactly 3 reports and not at 2; a false_positive hit under and
   over the supermajority; `threat_share` at exactly `0.66`, just under, just
   over `0.34`; a stance split landing in rule 6.
2. Re-anchoring against the fixture ledgers as literal input: the malicious
   value unchanged at `+84`; the benign value's rows flipped, summing `+91`,
   every `direction` matching what the fixture authored. This is B2's
   regression test and needs no database.
3. Negative anchored quality (rule 7): a threat lean whose ledger sums below
   zero renders contested, with the tug showing why.
4. Both escalations: the known-rule on the conflicted value, prose in the
   meta line; the listed-vs-asserted rule on a supermajority + fp-hit case;
   both firing → first in list order decides and is named.
5. The tug on a contested value: two sums, both derivable from the rendered
   ledger by hand, no third segment.
6. Quality bands at the boundaries, and the gauge deriving from them — with
   the median case pinned to `low` (§6).
7. `isDefinite()` wired: quiet treatment on `contested`/`none`, solid on
   `threat`/`benign`, in both themes — a visual change to two existing
   dispositions that needs its own before/after.
8. All five regression cases reach their stated lean and band.

## 10. Out of scope

- The relevance axis (phase 5 owns the runway and the expiry `changers`
  row).
- The warninglist categories the lean reads (phase 6; the shipped
  `WarninglistCategory.php` map is what lets rules 2–3 fire on day one).
- A predicate language for escalations. Rejected with Q11, decided as D12: an
  escalation is a discovered class, not an expression (§4, `03-signals.md`
  §8.8).
- The template and constant renames (`value_verdict_*.ctp`,
  `ValueDisposition` keys) — phase 9 touches the shipped code; this phase
  specifies the mapping (§7).
