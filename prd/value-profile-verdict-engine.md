# PRD: Value Profile — the verdict engine

**Not designed. This is a scope note.** It exists so the blocked phase has
something to point at, and so what the shipped page already asserts about
verdict signals is written down where it was found rather than rediscovered by
whoever picks this up.

The Verdict tab's live wiring is **blocked** on it. See
`value-profile-page.md` §1.4 and `value-profile-live/00-contract.md` §14.12.

**How it gets unblocked:** a dedicated PRD and a grilling session, in the shape
the tab phases used — questions first, decisions recorded, then a specification.
Nothing in this file is a decision.

---

## 1. Why the Verdict tab cannot be wired with the others

Every other tab displays data MISP already holds. The Verdict tab displays a
**computed judgement**, and nothing computes it — `value-profile-page.md` §5 has
said so since the skeleton pass: *"A verdict scoring engine. The page displays a
verdict; it does not compute one."*

For eight tabs that was a clean separation. For this one it means the live phase
has no data source to swap in: the fixture is not standing in for a query, it is
standing in for an algorithm that does not exist. Wiring it would mean designing
the engine inside a phase whose stated job is replacing fetchers, which is how a
contract gets quietly broken.

## 2. What has to be designed

The three questions, as raised:

1. **How signals are computed.** What each signal reads, when it fires, and how
   it produces a direction, a weight and a contribution.
2. **How signals behave under user preferences.** The shipped page already
   promises this (§3.4 below), and the promise and the ask do not currently
   agree on *whose* preferences.
3. **How users add their own signals.** An extension point that has to produce
   rows the existing ledger can render and a score the existing invariant can
   still satisfy.

## 3. What the shipped page already asserts

This is the constraint set. The templates, the fixture and nine tabs of prose
already commit to all of it, so the engine is not designing on a blank page —
it is satisfying claims already on screen.

### 3.1 Four signal groups, twenty-four rows

The ledger is grouped by `kind`, and across the three scored demo values the
groups are exactly: **Reporting**, **Sightings**, **Attribution**, **Lifecycle**.

Each signal row carries `direction` (`up`/`down`), `weight`
(`strong`/`moderate`/`weak`), `contribution` (a signed integer), `signal` (the
claim in prose), `evidence`, `source` (which panel supplies it) and `as_of`.

The twenty-four rows the fixture claims, by value:

| Value | Rows | Examples |
|---|---|---|
| `185.234.219.24` MALICIOUS 84 | 9 | 4 independent orgs reported it (+28), 47 sightings (+24), galaxy APT28 (+14), 1 false-positive sighting (−6), decayed under Phishing Model (−8) |
| `8.8.8.8` BENIGN 91 | 8 | hits a known-public-DNS warninglist (+38), 11 false-positive sightings (+26), 8 of 9 occurrences set `to_ids = no` (+13), 4 orgs carry an occurrence (−11) |
| `45.155.205.233` MALICIOUS 93 | 7 | 23 independent orgs (+31), 418 sightings (+24), QakBot on 107 occurrences (+17), fourteen months without silence (+12) |

**The catalogue is not fixed.** A warninglist signal fires only where there is a
hit; *"fourteen months without a month of silence"* fires only on a long-lived
value. So the engine needs a rule for which signals are *evaluated*, which
*fire*, and which are *shown* — and the page has no vocabulary today for a
signal that was evaluated and stayed silent.

### 3.2 Contributions sum to the score, exactly

Verified against the fixture: 84, 91 and 93 are the arithmetic sum of their
ledger contributions, to the unit. `value-profile-page.md` §6 already verifies
it (*"its ledger rows sum to the score the hero and the rail's composition card
both state"*).

This is the hardest constraint on the design and the most valuable. It means the
score is not a model output with an explanation attached — the explanation *is*
the score. Any engine that computes a score some other way and then narrates it
breaks a property the page currently guarantees.

### 3.3 Two shapes, not one

The conflicted value carries `score => null` and an **empty ledger**. It renders
the opposed-cases layout instead (`value_verdict_conflicted`), with a
tug-of-war bar, unresolved signals counted for neither side, and *"the conflict
rule that produced the state"* named on screen (§2.6).

So the engine has to produce either a summed ledger or a conflict, and it needs
a stated rule for which — the page already promises to name that rule.

### 3.4 The weighting profile is already promised as editable

The fixture renders, on every scored value:

> *"Weights come from the default-v3 profile. An instance admin can edit the
> profile; the tab always names the one in force."*

Nothing behind it. `default-v3` is a string in four places. Note that this copy
says **instance admin**, while the ask above says **user preferences** — per
user, per org, or per instance is an open question, and the shipped sentence has
already picked one.

It compounds §14.6. That section established that the verdict is never *"the
community's view"*, only *"the view available to you"*, because occurrence
visibility is per viewer. Per-user weights would make two readers differ for a
second, independent reason. The Verdict tab's standing caveat may need to say
both.

### 3.5 Direction is relative to the verdict, not to malice

§2.6: the score is *support for the stated disposition*, so `up` supports the
verdict and `down` argues against it — which is why wide reporting is `−11` on
the benign value. Red stays the malicious reading in both layouts via
`--vp-dir-with` / `--vp-dir-against`.

An engine that emits "maliciousness" rather than "support" would invert half the
ledger.

### 3.6 Not stored, not synchronised

The hero says so, and §5 renders it as given. This is an asset rather than a
limitation: a render-time computation makes per-viewer weighting cheap and gives
user-defined signals no sync blast radius. Worth not giving up by accident.

### 3.7 The weight band and the contribution do not agree

`strong`, `moderate` and `weak` are rendered as bands, and `contribution` is a
number, and nothing relates them. They overlap in the fixture: a `strong` of
`+17` sits below a `moderate` of `+16` elsewhere. Either the band is derived
from the contribution and the fixture is inconsistent, or it is an independent
editorial label — and the design has to say which.

## 4. Questions the corpus adds

Beyond the three in §2, these are already open and land on this engine:

- **The decay aggregation rule.** Undecided, and now owned by `ValueDecayTool`
  (§14.5). Two ledger rows read decay scores, so the engine cannot be specified
  while it is open.
- **The opinion colour contradiction.** The Overview preview paints "Agree"
  green while the Verdict histogram paints anything above 50 red
  (`value-profile-tabs/05-analyst.md` §11). Analyst opinion is a plausible
  signal input, and it currently means two opposite things on one page.
- **Which opinions count.** `value-profile-writes.md` §10 asks whether the
  aggregate counts value-scoped opinions, occurrence-scoped ones, or both. If
  opinions feed a signal, that answer changes the score.
- **What a `known`-category warninglist hit means.** §14.10 found that no
  shipped list sets `category`, so the +38 benign signal and §2.6's
  shared-infrastructure argument may both rest on a category nothing produces.
- **Whether user-defined signals are visible to others.** They cannot sync, per
  §3.6, but two users on one instance are a different question.

## 5. Out of scope for this document

Everything. This file records the problem; it does not choose an approach, a
weighting scheme, a configuration surface or an extension mechanism. Those are
the grilling session's output.
