# PRD: Analyst Profile — phase 3, dispositions

**Specification. Nothing built.** Depends on phase 2
([`03-signals.md`](03-signals.md)), which produces the signed total this phase
turns into a disposition.

Covers the `thresholds` and `escalations` sections, the `changers` block, and
the one missing entry in `ValueDisposition`.

## 1. What ships

`band()` — the function phase 2's accumulator calls — plus the escalation
mechanism, plus `SUSPICIOUS` in `ValueDisposition::TREATMENTS`, plus derived
`changers`.

Exit criterion: **all four demo values reach the disposition the fixture
claims, and the conflicted one reaches it through a named rule that renders in
the meta line.**

## 2. Two sums, not one

Phase 2 accumulates a signed total. This phase needs **both one-sided sums as
well**, because the conflicted layout already renders them: the conflicted
value's `tug` is `malicious => 71, benign => 66, unresolved => 12`.

```
positive = Σ points where points > 0      # toward threat
negative = Σ |points| where points < 0    # away from threat
net      = positive − negative            # phase 2's total
```

`unresolved` is a third bucket — points that counted for neither side. It
exists only on a CONFLICTED verdict, and it is why the exact-sum invariant does
not bind there: a conflicted verdict carries `score => null`, so there is no
number for the ledger to sum to. **The invariant binds when and only when
there is a score** — worth stating because it looks like an exception and is
not.

## 3. `band()` — from `net` to a disposition

```json
"thresholds": {
  "malicious_floor":    65,
  "suspicious_floor":   40,
  "benign_floor":       65,
  "conflict_min_each":  55,
  "conflict_max_gap":   15
}
```

Order matters; the first match wins:

```
1. any enabled escalation fires                            → its `emits`
2. positive ≥ conflict_min_each
   and negative ≥ conflict_min_each
   and |positive − negative| ≤ conflict_max_gap             → CONFLICTED
3. net ≥ malicious_floor                                    → MALICIOUS, score = net
4. net ≥ suspicious_floor                                   → SUSPICIOUS, score = net
5. net ≤ −benign_floor                                      → BENIGN, score = |net|
6. otherwise                                                → UNKNOWN, score = |net|
```

Checked against the fixture: `+84` → MALICIOUS (rule 3), `+93` → MALICIOUS,
`−91` → BENIGN (rule 5). The conflicted value's `71` and `66` satisfy rule 2 at
these defaults — both above 55, gap of 5 — which is the point: **CONFLICTED
becomes arithmetically derivable** rather than only reachable by a named rule.

### 3.1 Rule 2 is the interesting one

The conflicted value's own summary already argues for it: *"Both readings are
supported. Averaging them would produce a number that describes neither."* Rule
2 is that sentence as arithmetic — two large opposed sums that nearly cancel
produce a net near zero, and reporting "UNKNOWN 5" for a value with 63
sightings and a warninglist hit would be the averaging the page refuses to do.

So there are **two paths to CONFLICTED**: this one, and an escalation. The page
supports both — the conflicted value names a rule *and* renders a tug bar, and
`value_verdict_meta.ctp:47` renders `Conflict rule: <text>` only when a rule
produced the state. A conflict reached by rule 2 renders the tug bar and the
meta line falls back to *"Not stored, not synchronised"*, which is the existing
branch.

### 3.2 `net == 0`, and the middle negative band

Two edge cases that must be stated rather than fall out:

**`net == 0` with a ledger.** Not CONFLICTED unless rule 2's magnitudes are
also met — two rows of `+3` and `−3` cancelling is not a conflict, it is
nothing much. Falls to rule 6: UNKNOWN, score 0, with a ledger.

**`net` between `−benign_floor` and `suspicious_floor`.** Also rule 6. This
means **UNKNOWN carries two meanings**: *nobody has reported this* (the sparse
page, empty ledger) and *there is evidence and it is inconclusive* (a full
ledger).

That is a real conflation and the cheap resolution is to accept it, because the
page already distinguishes them on screen: `01-profile.md` §5.3 means an empty
ledger renders no profile name and no score, while an inconclusive verdict
renders both. The alternative is a fifth disposition (`INCONCLUSIVE`), which
means a second colour token, a second glyph, and a second entry to keep in step
across four render sites.

**Recommendation: accept the conflation, and make the summary carry the
difference** — *"No organisation has reported this value"* versus *"The
evidence is real on both sides and neither carries it"*. Flagged rather than
silently decided, because a reviewer will notice UNKNOWN on a page with nine
ledger rows and should find this paragraph when they do.

## 4. `SUSPICIOUS` — the missing disposition

`ValueDisposition::TREATMENTS` has four entries. `SUSPICIOUS` is promised in
three fixture strings (`ValueProfileFixture.php:1190, 3545, 3589`) and exists
nowhere in code, so a value scoring into rule 4's band today falls through to
`ValueDisposition::NEUTRAL` and renders as a grey question mark labelled
SUSPICIOUS — degrading to *unknown*, the opposite of what it means.

One entry:

```php
'SUSPICIOUS' => array(
    'colour'   => 'var(--vp-susp)',
    'icon'     => 'fas fa-circle-exclamation',   // see below
    'slug'     => 'suspicious',
    'definite' => true,
),
```

Three things to settle with it:

- **`--vp-susp` is a new token** and must be theme-stable in both themes.
  `../value-profile-page.md` §6.1 records what happens when a token is not:
  `--bs-secondary-color` inverted between themes and produced a 1.4:1 hero
  badge. Amber is the obvious reading and has to be checked against both
  grounds, not assumed.
- **The glyph collides with CONFLICTED**, which already uses
  `fa-circle-exclamation`. Two dispositions with one glyph defeats the reason
  `ValueDisposition` exists — the tab-bar state pill is glyph-only at small
  widths. `fa-triangle-exclamation` is MALICIOUS's. A distinct third is needed.
- **`definite` is `true`.** SUSPICIOUS names a state; it does not refuse to.
  This is the first real caller of `isDefinite()`, which
  `../value-profile-page.md` §6.1 records as still unused — *"the quiet
  treatment its docblock describes for CONFLICTED and UNKNOWN was never wired
  to a style."* Adding a fifth disposition without wiring it means three
  dispositions now share a treatment they were meant to be distinguished from.
  **Wire it in this phase**, or the entry makes an existing gap worse.

## 5. `escalations`

```json
"escalations": [
  { "id": "conflict:known-infrastructure-vs-reporting",
    "enabled": true,
    "emits": "CONFLICTED",
    "when": { "warninglist_category": "known",
              "min_independent_reports": 3 } }
]
```

An escalation **replaces the disposition** and does not contribute points —
which is D2's whole argument for named sections. When one fires:

- `disposition` is its `emits`.
- `score` is `null` if `emits` is CONFLICTED, otherwise `net` as normal. An
  escalation to SUSPICIOUS keeps its score; an escalation to CONFLICTED does
  not, because the page's conflicted layout has no score slot.
- `rule` carries the id and its prose, rendered by
  `value_verdict_meta.ctp:47`.
- The ledger is **not** discarded. The conflicted fixture value has an empty
  ledger, but that is a fixture convenience rather than a requirement — the
  rows were evaluated and they are what rule 2's tug bar is built from. Phase 9
  populates it.

**Implementations, not expressions.** `when` is declarative in the profile but
each escalation id resolves to a class, the same as a signal
(`03-signals.md` §2.2). The alternative — a general predicate language over
`$context` — is the same design problem as Q11's extension point and is
deferred for the same reason.

**v1 ships one escalation**, the one already on screen. Its `when` depends on
warninglist categories, which nothing sets today, so phase 6's override map is
what makes it fire at all.

### 5.1 An escalation that names a disposition the profile cannot reach

An escalation emitting `BENIGN` on a value whose net is `+84` is legal and
possibly intended (*"this org's own infrastructure, never flag it"*). The
engine obeys it, and the ledger then shows nine rows summing to +84 under a
BENIGN hero.

That is not a broken invariant — §2's rule is that the ledger sums to *the
score*, and the score here is `|net| = 84` with the disposition overridden. But
it reads as a contradiction, so the escalation's prose has to carry the
explanation, and the ledger's derived `direction` flips accordingly (every
positive row becomes `against`). Worth a verification case.

## 6. `changers` — falsifiability, derived

The fixture's `changers` block is *"what it would take to move the
disposition"*, with the note that *"a verdict that cannot say what would
falsify it is an opinion"*. Today it is three hand-written strings per value.

**With thresholds and signals both in the profile, it is computable.** Given
`net`, the bands, and each signal's `points`, the engine can state the distance
to each boundary in the units the analyst can act on:

| Fixture string | Derivation |
|---|---|
| *"3 or more false-positive sightings from 2+ orgs → drops to SUSPICIOUS"* | `(net − suspicious_floor) / |sightings.false_positive.per|`, plus the signal's own org-spread condition |
| *"No sighting for 45 days → decay takes the score under 50"* | phase 5's TTL curve solved for the band boundary |
| *"A warninglist hit of category known → CONFLICTED immediately"* | the escalation's `when`, restated |

This is the best argument in the feature for putting thresholds in the profile
rather than in code: **a profile that holds its own boundaries can say what
would falsify its own verdict, automatically.** A hardcoded engine cannot,
which is why those three strings are hand-written today.

Scope note: derive the two or three cheapest changers, not all of them. Solving
every signal for every boundary is a search problem, and the block renders
three rows.

## 7. Verification

1. `band()` unit tests at every boundary: `net` of exactly
   `malicious_floor`, one below, `suspicious_floor`, one below, `0`,
   `−benign_floor`, one above.
2. Rule 2 against the conflicted value's own numbers (`71`, `66`) and against
   a near-miss (`71`, `50` — gap 21, no conflict) and a both-small case
   (`20`, `18` — under `conflict_min_each`, no conflict).
3. All four demo values reach the fixture's disposition.
4. `SUSPICIOUS` rendered in all four sites — hero, tab-bar state pill, Overview
   rail pill, card border — in both themes, with a contrast check on
   `--vp-susp` against both grounds.
5. `isDefinite()` wired: CONFLICTED and UNKNOWN render the quiet treatment,
   MALICIOUS / BENIGN / SUSPICIOUS the solid one. This is a visual change to
   two existing dispositions and needs its own before/after.
6. The escalation fires on the conflicted value and its prose reaches
   `value_verdict_meta.ctp:47`.
7. §5.1's contradictory escalation: a BENIGN hero over a `+84` ledger, every
   row flipped to `against`, and the prose explaining it.
8. A conflict reached by rule 2 rather than an escalation: tug bar renders, meta
   line falls back to *"Not stored, not synchronised"*.

## 8. Out of scope

- The TTL curve that `changers` row two needs (phase 5).
- The warninglist category map that the escalation needs (phase 6). Until it
  lands, the escalation is specified and inert.
- A predicate language for escalations (§5, and Q11).
- A fifth disposition for the inconclusive case (§3.2, recommended against).
