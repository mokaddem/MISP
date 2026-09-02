# PRD: Analyst Profile — phase 9, wiring the Verdict tab live

**Specification. Nothing built.** Depends on phases 1–5. Phase 6 is not a hard
prerequisite but the conflict escalation cannot fire without it.

This is the payoff phase: the tab that has been blocked since the skeleton pass
reads real data, and the copy the page has been asserting for six months
becomes either true or retracted.

## 1. What ships

`ValueProfile::forVerdict()` and friends replacing the fixture behind the
Verdict tab and the Overview's verdict card, plus every shipped string this
feature has made wrong.

It follows the contract every other live phase followed —
[`../value-profile-live/00-contract.md`](../value-profile-live/00-contract.md)
§14 — and it is the phase that finally moves the last blocked rows on §14.12's
conversion board.

## 2. The panels

Seven endpoints, all currently fixture-backed:

| Action | Panel | Notes |
|---|---|---|
| `viewVerdict` | `value_verdict` | The agreeing layout: hero, ledger, contradictions, orgs, composition, curves |
| `viewVerdictAside` | `value_verdict_aside` | `not_counted` and `changers` |
| `viewVerdictCard` | `value_verdict_card` | The Overview rail card — **`../value-profile-page.md` §1.4 names this one as blocked on the verdict engine specifically** |
| — | `value_verdict_conflicted` | The conflicted layout, reached from `viewVerdict` on disposition |
| — | `value_verdict_ledger` | Grouped rows |
| — | `value_verdict_composition` | Segments and total |
| — | `value_verdict_orgs` | *Who says what*, now carrying phase 6's real grades |

**One `$context` build, seven readers.** `03-signals.md` §2.2 makes the
aggregate shared; this phase makes it a single pass so a page load does not
compute the verdict seven times. The card and the tab **must** come from one
computation — a card and a tab on one page disagreeing about the same value is
the hazard `../value-profile-page.md` §1.4 records phase 23 converting the
Overview's sightings card out of order to avoid.

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

So the rule is: group the fired rows by `group`, sum the **positives** per
group, and collect **all** the negatives into one segment. No separate logic and
no second source of truth — the composition card is a faithful regrouping of the
ledger, and the same invariant holds one level up.

## 3. Shipped copy this phase changes

`01-profile.md` §6 is the inventory. Each is either corrected or retracted; a
claim left standing that the code no longer honours is what
`prd-tracks-reality` exists to prevent.

| String | Becomes |
|---|---|
| `Weighting profile default-v3` | `Analyst profile <name>`, linked (`09-editor.md` §5.1). **`default-v3` → `default-v1`** — the fixture's string was an artboard literal and a real shipped profile must not claim a version history it does not have |
| *"An instance admin can edit the profile; the tab always names the one in force."* | The second half stays and becomes true. The first half is wrong under D3 — most readers see a profile they or their org own. Replace with something that states the scope of the profile in force |
| *"3 or more false-positive sightings from 2+ orgs → drops to SUSPICIOUS"* | Derived (`04-dispositions.md` §6), and SUSPICIOUS now exists |
| *"No sighting for 45 days → decay takes the score under 50"* | Restated in TTL terms — there is no decay (D7) |
| *"Weights come from the default-v3 profile"* | Names the real profile |
| `NIDS decay score` dashed curve, `curves_note` | The TTL runway (`06-staleness.md` §4.2) |
| `attribution.galaxy` band, `moderate` on one value and `strong` on another | One band, per `03-signals.md` §5 |

### 3.1 The one the page has always got right

`value_verdict_meta.ctp:38`'s conditional — *"a verdict reached from no signal
at all does not name a profile"* — needs no change and must not be lost.
`01-profile.md` §5.3. It is the only piece of this whole surface that was
already correct about a thing that did not exist yet.

## 4. Q9 — does the standing caveat now state both reasons?

**Open, and this phase has to close it.**

`../value-profile-live/00-contract.md` §14.6 established that every count on the
page is the viewer's, and that the verdict is therefore never *"the community's
view"*, only *"the view available to you"*. The `acl_note` says it per value:
*"4 occurrences you cannot see were excluded from this assessment."*

Under D3, per-user profiles add a **second, independent** reason two readers
differ about the same value. Today's caveat explains one.

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
   internally consistent, each with its own `acl_note`. This is §14.6 made
   real and is the first time the page has been able to demonstrate it.
6. Two users with different profiles on the same value: two scores, both
   ledgers summing, both heroes naming their own profile. This is the feature's
   headline claim (`01-profile.md` §1.1) and it has never been demonstrable.
7. Q9's chosen treatment rendered.
8. The conflicted value reaching CONFLICTED, with its ledger **populated** —
   the fixture's empty ledger was a convenience and phase 3 §5 says the rows
   are what the tug bar is built from.
9. Nine-tab bar at 1920, 1600 and 992 px. `../value-profile-page.md` §6.1
   records it wraps to two rows below 1600 and that this was reported rather
   than restyled; a badge change must not make it worse.
10. Dark theme, both layouts, with `--vp-susp` from phase 3 in place.
11. The harness caveat from `../value-profile-page.md` §6.1: panels are checked
    in headless Chrome against saved fragments and the CSS fetch fails
    intermittently, so assert `--vp-mal` resolves before asserting any colour.

## 6. Out of scope

- Storing or caching a verdict. Render-time only; phase 10 changes that
  knowingly (`01-profile.md` §5.5).
- The enrichment badge (phase 7, blocked).
- Linking values from the event view's attribute table. Still deferred per
  `../value-profile-page.md` §5 — though the reason given there (*"with two
  demo values almost every such link would land on the sparse state"*) is
  weaker now that the page is mostly live, so it is worth re-asking after this
  phase rather than after this document.
