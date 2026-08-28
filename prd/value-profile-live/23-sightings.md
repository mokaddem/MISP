# PRD: Value Profile — Sightings goes live

**Phase 23**, the second live phase. Converts all five panels of the
Sightings tab from `ValueProfileFixture` to the database.
Depends on [`00-contract.md`](00-contract.md) §14 and on
[`22-occurrences.md`](22-occurrences.md), whose seam and facade this
extends. The tab's fixture-era design is
[`02-sightings.md`](../value-profile-tabs/02-sightings.md).

---

## 1. Why Sightings went second

§14.13 declines to sequence the campaign and asks whichever phase goes
next to argue for itself. The argument here is not that Sightings is
easy — `02-sightings.md` §11 says it has *"the hardest live-data story of
the five"* and that is still true. It is that the hard part was **one
named, undecided question**, and leaving a decision parked while easier
tabs land is how a campaign accumulates the kind of debt §14.11 lists.

§14.5 created `ValueDecayTool` specifically so *"that decision has one
home"* and recorded it as still open. Three documents in a row —
§7.9, `02-sightings.md` §11, then §16 — restated the same gap without
closing it, each one sharpening the wording. This phase closes it (§5),
and the closing is most of the phase.

**Two things also forced the order.** The instance held **eleven
sightings**, all type 0, no sources, three organisations — less data than
any single fixture value depicts, and not enough to judge a chart on. And
it held **no decaying models at all**, so nothing on the tab had a curve
to draw. Both had to be dealt with before a line of this phase could be
verified, and neither is something a later phase would have found more
convenient. §2 is what was done about it.

---

## 2. The instance had no data for this tab

Two gaps, and the difference between them matters. One was missing
configuration and one was missing reports.

**No decaying models.** `decaying_models` and
`decaying_model_mappings` were both empty. `DecayingModel::update()`
loads the three MISP ships — NIDS Simple Decaying Model, Phishing model,
Vishing model — from `app/files/misp-decaying-models/models/`, and
`decaying_models.enabled` defaults to 0, which
`attachScoresToAttribute` filters on. All three were loaded and enabled,
which is the ordinary administrator action; there is no CLI for it, only
`POST /decayingModel/update`.

That gave a better test than two models would have. **Vishing covers
only `phone-number` and `prtn`**, so on every value this tab was verified
against, two models apply and one does not — which means the "which
models apply to this value" filter is exercised rather than
vacuously true. And the two that apply are usefully unlike each other:
NIDS has a 120-day lifetime and Phishing a 3-day one, so a value sighted
two days ago reads 70 on one curve and 17 on the other. One healthy
model and one decayed model is exactly the pair the rail card
distinguishes in words, and no single model would have shown it.

**Eleven sightings.** §14.8 declines to build a seeder, and that
declining is about the four *demo values* — it says a live phase verifies
against real instance values instead. It does not say the tab under test
may have no data. 101 reports were written, taking the instance to 112
across 12 organisations: 98 sightings, 11 false positives, 3 expirations.

Everything went through `Sighting::saveSightings`, so the rows are
written the way MISP writes them — uuid, validation, `afterSave`. The
script is [`23-sightings-seed.php`](23-sightings-seed.php), kept beside
this document rather than under `app/Console/Command/` because it is
verification scaffolding and not a feature; its header says how to run
and how to undo it.

The set was shaped to exercise the tab rather than to look plausible:

| Value | Reports | Shape it proves |
|---|---|---|
| `8.8.8.8` | 53 | the populated case — 6 organisations, 8 different occurrences, 2024-11 to two days ago, 7 carrying a `source`. 90-day, 365-day and all-time are three different charts |
| `2.2.2.2` | 27 | the **viewer-scoping** case, the value phase 22 used for the same purpose. Four of its occurrences sit on ADMIN-owned events and three do not, so the default sighting policy makes three readers see three different tabs (§7) |
| `1.1.1.1` | 14 | the **sparse** case: fourteen reports over 497 days, which is what the range control exists for |
| `github.com` | 6 | the **soft-delete** case — two of the six are filed against a soft-deleted occurrence (§8.1) |
| `45.155.205.233` | 3 | **contradiction only**: three false positives and nothing else, so the `Sightings` toggle renders disabled with its reason and no curve has ever been reset |
| `193.161.193.99` | 0 | left alone. 335 occurrences, no report — the state where the chart is empty and the rail still carries a score |

---

## 3. What ships

Five endpoints, five elements, all five converted:

| Endpoint | Element | Facade method |
|---|---|---|
| `viewSightingChart` | `value_sighting_chart` | `ValueProfile::forSightingChart` |
| `viewSightingList` | `value_sighting_list` | `ValueProfile::forSightingList` |
| `viewSightingDecay` | `value_sighting_decay` | `ValueProfile::forSightingDecay` |
| `viewSightingReporters` | `value_sighting_reporters` | `ValueProfile::forSightingReporters` |
| `viewSightingAdd` | `value_sighting_add` | `ValueProfile::forSightingAdd` |

New files: `app/Lib/Tools/ValueDecayTool.php`.
Extended: `app/Model/Value.php` (two accessors),
`app/Model/ValueProfile.php` (five public methods),
`app/Lib/Tools/ValueStatsTool.php` (six),
`app/Lib/Tools/ValueProfileBuckets.php` (one, moved — §9.2).
Templates touched: all five of the tab's, all Value-Profile-owned.

**The Overview tab's `value_sightings` rail card is untouched and still
fixture-backed.** It reads `sightings.spark`, which nothing here
produces; §14.12's board keeps it on `—`.

---

## 4. The queries

Per §14.9 row 2. **No count on this tab grows with the number of
occurrences a value has**, which took two rewrites to achieve (§8.2).

### 4.1 The shared start — three queries

Every panel except the write card begins here.

| # | What | How | Tier |
|---|---|---|---|
| 1 | the occurrences carrying at least one report | `Value::sightedOccurrenceIdsFor` — `attributes` INNER JOIN `sightings` under `buildConditions($user)`, grouped by attribute | 2 |
| 2 | `listSightings`' own attribute re-fetch | `MispAttribute::fetchAttributes`, inside the fetcher | 1 |
| 3 | the report rows | `Sighting::listSightings($user, $ids, 'attribute')` | 1 |

**Query 1's tier-2 reason, and it is the load-bearing decision of the
phase.** The obvious shape is to resolve the value's occurrences and hand
them to `listSightings`. On `443` that is 48,255 ids handed to a fetcher
that re-resolves every one of them — **measured at 1.6 to 3.4 seconds per
panel, for three sightings.** The answer wanted is *which occurrences
have been reported*, which is a group, not a row set, and it is 3 rows
instead of 48,255. Narrowing first is the difference between this tab
working on a real instance and not.

The sighting policy is **not** applied in query 1 and does not need to
be: `listSightings` applies it to the rows, and query 1's output never
reaches the page. An occurrence whose only report the reader may not see
contributes no row, no count, and no curve distinguishable from an
un-reported one.

Query 1 is also the one place in this feature that joins another model's
table. The table name is read off the `Sighting` model rather than spelled
in `Value`, so the model that owns the data still owns where it lives.

### 4.2 The decay panels — five more

`forSightingChart` and `forSightingDecay` add:

| # | What | How | Tier |
|---|---|---|---|
| 4 | the occurrence summary — count, events, organisations, oldest, newest | `Value::occurrenceSummaryFor` — one grouped aggregate | 2 |
| 5 | the newest N occurrences, capped | `Value::occurrenceIdsFor` with `limit` and `order` | 1 |
| 6 | those occurrences' rows and attribute tags | `fetchAttributesSimple` + `attachTagsToAttributes` | 1 |
| 7 | the tag records behind them | the same call's second query | 1 |
| 8 | the event tags over every event they sit in | `Event->EventTag->find`, **one call for all N events** | 1 |
| 9 | every enabled model this viewer may use, with its types | `DecayingModel::fetchAllAllowedModels($user, true, [], ['enabled' => true])` | 1 |

**Query 4's tier-2 reason.** Five numbers over *every* occurrence: the
write card's fan-out sentence needs the occurrence, event and
organisation counts, and the chart's span needs the oldest occurrence
date. Materialising rows to count them cost **617 ms on `443`** and
280 ms on `0.0.0.0`; the aggregate is 4 ms of query time. It is also
memoised per request, because two of the five panels never need it and
making them wait for it made them four times slower for nothing.

**Query 9 replaced a worse shape, and the replacement is worth
recording.** `DecayingModelMapping::getAssociatedModels($user, $type)` is
the route `attachScoresToAttribute` takes, and it was tried first. It
asks per attribute type and re-reads every default model each time:
**thirteen queries for a value with three types**, where
`fetchAllAllowedModels` is two and does not grow with the type count. The
two answer the same question — the type union
`getAssociatedModels` builds with `array_merge_recursive` is the one
`$full` builds with `Hash::extract` — so this is a substitution, not a
narrowing.

### 4.3 The write card — one query

`forSightingAdd` needs query 4 and nothing else. One aggregate, three
numbers, no rows.

### 4.4 Plus MISP's own, which are not flat

Two shared-code costs this phase inherits rather than introduces, both
inside tier-1 fetchers:

- **`SharingGroup::authorizedIds($user)`** — two selects inside
  `buildConditions()`, absent for a site admin, once per request thanks
  to `aclConditionsCache`. Phase 22 counted these too.
- **`MispAttribute::fetchAttributes` issues one `Org->find('first')` per
  distinct organisation.** `MispAttribute.php:2363` caches in
  `$this->orgs_cache` but resolves each id with its own query, so a value
  whose occurrences span nine organisations costs nine selects inside
  query 2. This is the same class of finding as §14.10's
  `fetchSimpleEvents` note — the batched form is one `IN (…)` — and it is
  reachable from every attribute index in MISP, which is exactly why
  §14.7 says report it and do not fix it here.

---

## 5. The aggregation rule — decided

§14.5 named `ValueDecayTool` as the home for this and left it open.
§7.9, `02-sightings.md` §11 and `02-sightings.md` §16 each restated the
gap. It is now closed.

> **The value's decay score is the per-day maximum across its
> occurrences, labelled with the occurrence holding it.**

**Why maximum.** A value is as live as its best-corroborated occurrence.
A mean would let a stale duplicate drag down a value somebody reported an
hour ago. A minimum would make *adding* an occurrence a way to lower a
score, which is an incentive nobody should have.

**What maximum costs, stated rather than glossed.** It is not monotone in
evidence. An untagged occurrence carrying a model's
`default_base_score` can outrank a well-sighted one whose numerical
taxonomies score it low — on this instance `8.8.8.8`'s tagged
occurrences base at 95 while the model's default is 80, so the direction
is real. **This is why the label is half the rule and not a decoration
on it.** The rail card prints *"Highest of 23 scored occurrences · held
by the one in event 1"*, so a reader who disagrees with the number knows
where to go and argue with it.

`02-sightings.md` §16 named the slot the label would land in — the
provenance line, *"Last reset by CIRCL on 2025-08-22"* — and it landed
beside it rather than in it, because they are different facts: one says
what last moved the clock, the other says whose curve is on top.

**The envelope, not one occurrence's curve.** `owner` is per day, the
same length as `points`, because the occupant changes along the line. A
value whose newest report lands on a different occurrence each week has
an envelope stitched from several curves, and a single label on the whole
line would be wrong for most of it.

### 5.1 Making it affordable

`02-sightings.md` §11 costed the naive shape at *"hourly, per attribute,
per model — ten occurrences by two models is twenty loops per page
load"* and said it *"needs a coarser sampler or a cache before it is
wired."* It got the coarser sampler; §14.4 keeps caching out.

Three bounds, each with a statable consequence rather than an unknown
error:

**A daily grid, not hourly.** `getScoreOvertime` samples every hour from
the attribute's first timestamp to *last sighting + lifetime*. The chart
draws at most a few hundred bars and reads one sample per drawn bar's
last day, so a day is the finest grain anything on screen can use. The
grid's last point is `time()` rather than the end of today, which is what
makes the rail's number equal the curve's last point exactly — see §6.

**`SPAN_CAP_DAYS = 1095`.** The span is the value's whole life, which on
a real instance is unbounded: `0.0.0.0` first appears in 2015, or 3,948
daily samples per model for a value whose reports are all recent. The
curve is the only *dense* series in the payload — a count can be sparse
because most days have none, a score cannot because every day has one —
so this is the number that bounds the fragment. When it bites the chart
says so, because a cap is not a permission (§14.6). Measured payloads:
11 KB at a 287-day span, 43 KB at the cap.

**`OCCURRENCE_CAP = 100`.** The envelope is a maximum, so a cap can only
lower it: **what the chart draws over a capped set is a lower bound**,
and the rail prints `100 of 335 occurrences scored` when the two differ.
The bound is tight because of *which* hundred: every occurrence that has
been reported, unconditionally, plus the newest of the rest by
`Attribute.timestamp DESC`. A score falls as elapsed time grows and an
un-reported occurrence's clock runs from its own date, so among
un-reported occurrences the newest *is* the highest-scoring for any two
sharing a base score. The ordering is the exact answer in the common
case, not a heuristic about which rows matter.

### 5.2 The grouping, which is exact

Even bounded, 100 occurrences × 2 models × 1,095 days is 219,000
`computeScore` calls. The chart panel measured **172 ms** on `8.8.8.8`
before this.

`Polynomial`'s score is `base × (1 − (elapsed / lifetime) ^ (1 / speed))`
clamped at zero — strictly non-increasing in elapsed time for a fixed
base. So among occurrences sharing a base score, the one with the
smallest elapsed time *is* the maximum, and the other twenty-two are work
with a known answer. `ValueDecayTool::groupByBase()` collapses them,
keeping per day the smallest elapsed time and which occurrence held it.

**Only for that one formula, and the condition is checked rather than
assumed.** `PolynomialExtended` zeroes a score from the attribute's own
`retention` tags and `Sightings` ignores elapsed time altogether, so
under both, two occurrences with one base score can legitimately differ.
`ValueProfile` tests `get_class($formula) === 'Polynomial'` and takes the
per-occurrence path otherwise. No shipped model uses either, so nothing
on this instance exercises the fallback — recorded as a gap, not as
coverage.

Measured on `8.8.8.8` (23 occurrences, 2 models, a 1,095-day span, two
distinct base scores between them): the chart panel 172 ms → 86 ms, and
the decay rail, which does the same envelope work with the ACL caches
already warm, 52 ms → 16 ms. **The scores are byte-identical either
way**, which is what makes this an optimisation rather than a change.

One further hoist, found while measuring: elapsed time is a property of
the occurrence and the day and not of the model, so it is computed once
and read by every model. It had been inside the model loop — the same
walk twice.

### 5.3 What the tool owns, and what it does not

`ValueDecayTool` is pure, static, and takes no `$user` (§14.5). It issues
no query and resolves no permission.

**It does not own the formula.** `computeScore` and `computeBasescore`
stay in `app/Model/DecayingModelsFormulas/` and `ValueProfile` calls
them. This is a deliberate departure from the fixture, which
reimplemented MISP's polynomial — `02-sightings.md` §15 was right that
deriving beat asserting, and calling beats deriving. What the tool owns
is the *sampling*: the day grid, each occurrence's elapsed time on each
day of it, the base grouping, and which occurrence wins.

---

## 6. The rail's number is the curve's last point

`02-sightings.md` §8 has the card close with *"Each line on the chart is
this model's score. The bar under each name is the same number now."*
Under a fixture that was a promise the data could quietly break. It is
now true by construction: the rail reads
`$envelope['points'][count - 1]`, the same array the chart plots, and the
grid's last point is `time()` — which is the instant
`DecayingModelBase::computeCurrentScore` uses.

It is asserted rather than assumed. §10.4's payload check compares the
rail's printed score against the last element of the plotted curve for
every model on every verification value.

---

## 7. §14.6, and the exception that turned out to have two members

§14.6 requires every count to be the viewer's, and grants **one**
exception: a permanent, always-shown, reader-independent caveat line on
the Verdict tab, because that tab *"renders a computed judgement, and two
readers can honestly get different verdicts for the same value; without
this line, neither has any way to know why."* It ends *"Nowhere else on
the page carries it."*

**That last sentence is now wrong, and this phase is why.** Measured on
`2.2.2.2`, same value, same day:

| Viewer | Reports | Organisations | Occurrences | NIDS | Phishing |
|---|---|---|---|---|---|
| site admin | 27 | 5 | 13 | 73 | 34 |
| org 1, no admin rights | 18 | 2 | 13 | 73 | 34 |
| CIRCL org admin | 7 | 1 | 10 | **59** | **0** |

The counts differing is §14.6 working. **The score differing is a
computed judgement differing**, and for exactly the Verdict tab's reason:
`Plugin.Sightings_policy` hides the report that last reset the clock, so
the CIRCL reader's curve decays from an older reset. A CIRCL analyst and
a site admin would read 59 and 73 off the same card on the same
afternoon, and nothing on the page told either of them why.

So the decay card gets the Verdict tab's treatment — a line that is
always shown, on every value, identical for every reader, and therefore
carrying no information about what any particular reader cannot see:

> *A decay score counts the reports you can see. Two readers whose
> sighting visibility differs can honestly read different scores for this
> value on the same day.*

**§14.6 needs amending, not working around**, and §11.1 records the
amendment. The rule the exception is really drawing is *a computed
judgement gets a permanent caveat*; "nowhere else" was a statement about
what had been built, not a principle.

### 7.1 The other required changes

Nothing else in §14.6's table belongs to this tab. It listed no
`.vp-acl-note` on any Sightings panel, and the tab's own
`policy` sentence at the foot of the list panel was already
viewer-neutral — *"this instance's sighting policy hides sightings
reported by other organisations on events your organisation does not
own, so this count is yours, not the instance's"* — always shown, no
value-specific number in it. It survives unchanged.

---

## 8. What MISP would not give, and what had to be rewritten

### 8.1 `listSightings` cannot see a report on a soft-deleted occurrence

`Sighting::listSightings` re-resolves the attribute ids it is handed
through `fetchAttributes` with `Attribute.deleted = 0` forced. So a
report filed against a soft-deleted occurrence is invisible to this tab
while the occurrence itself is visible on the Occurrences tab, which
phase 22 built to show exactly those rows.

**Measured, deliberately.** `github.com` was seeded with six reports,
two of them against soft-deleted occurrence 2827379. The tab reads
**four**. The write card beside it says twenty-one occurrences, because
`saveSightings` resolves through `fetchAttributesSimple`, which has no
such rule — so the same page correctly reports a fan-out wider than the
list it can show.

This is a defect in shared code and §14.7 says report it, do not fix it
here: the fix is inside a fetcher used by the attribute index, the API
and the sync code. What this phase does is pre-filter the ids, which is
also what stops `listSightings` throwing `MethodNotAllowedException`
instead of returning an empty list when handed nothing but deleted ids.

Phase 22 found the neighbouring half of this — `fetchAttributes` forces
`deleted = 0` and `object_id = 0` — and concluded *"only one of them
works."* The same sentence now applies one layer up.

### 8.2 Two rewrites, both found by measuring rather than by reading

The first version of this phase scoped `listSightings` by the value's
whole occurrence set and counted the fan-out from materialised rows. It
passed every content assertion and every payload check. On the two
heaviest values on the instance it took **1.6 to 3.4 seconds per panel**.

Neither was a query-count problem — the counts were 18 and 27, flat.
Both were §14.4's tier-2 lesson arriving from the other direction: *the
answer is a count or a group, and materialising rows to get it is the
wrong shape.* §14.4 warns about counting a *page* and calling it a total;
this was counting the *whole set* correctly and paying seconds for it.

What it cost, and what it costs now, on `443` — 48,255 occurrences behind
three reports:

| Panel | Before | After |
|---|---|---|
| chart | 1357 ms | 309 ms |
| individual sightings | 721 ms | 50 ms |
| decay rail | 1083 ms | 362 ms |
| reporters | 653 ms | 23 ms |
| add sighting | 413 ms | 199 ms |

**The lesson worth carrying forward** is that the panel which looked
cheapest was the worst offender. The reporters card renders six bars from
three numbers; it was spending 653 ms because it shared a helper that
resolved every occurrence. A shared `sightingContext()` is the right
shape and was also how the cost hid — which is why the occurrence summary
is now lazy rather than eager.

### 8.3 The bug only the browser found

Both models' curves were drawn with a **flat plateau at full base score**
across every day between an occurrence's first report and its last edit
— on `8.8.8.8`, four months at 78 on a model whose lifetime is *three
days*. A decay curve that does not decay is the one thing this panel
exists to make visible, and it survived every assertion listed above:
34 content checks, 34 payload checks, 45 clean renders. It took drawing
it.

**The cause.** Two kinds of thing reset a decay clock, and both are
events with a date. A report is the obvious one;
`DecayingModelBase::computeCurrentScore` adds the other — *if the
attribute was modified after its last report, the clock restarts at the
modification*, because an analyst touching a row is a statement about it.
`ValueDecayTool::elapsed()` implemented that as
`max(last report, attribute date)`, which is right at "now" and wrong
everywhere else: it applies the attribute's date as a reset **on days
that precede it**, pinning elapsed time at zero for the whole stretch
between.

**The fix** is to treat the attribute's own date as one more reset event
rather than as a floor — the clock start on day D is the latest reset
that has *happened by* D. At the last grid point every occurrence's date
has occurred, so this reduces to exactly `computeCurrentScore`'s rule:
**every current score is unchanged and only the history behind it
moved.** Re-measured after the fix, `8.8.8.8` still reads Phishing 8 and
NIDS 69, and the two curves now visibly behave differently — the 3-day
model spikes and crashes within days of each report while the 120-day
one sawtooths gently, which is the comparison the overlay is for.

**Worth carrying forward:** a curve is not verifiable by assertion. Every
check that could be written against the payload passed, because the
numbers were internally consistent, in range, monotone where they should
be and equal to the rail at the last point. What was wrong was the
*shape*, and shape is a thing you look at.

### 8.4 What §11 of the tab brief predicted, item by item

| `02-sightings.md` §11 said | What happened |
|---|---|
| the curve is per attribute, no aggregation rule | decided — §5 |
| the curve's axis is not the histogram's; aligning means resampling in PHP | done — a daily grid over a capped span, §5.1 |
| cost: hourly, per attribute, per model | daily, and grouped by base score where the formula permits — §5.2 |
| false positives and expirations move nothing | true by construction: `resetStamps()` keeps type 0 only, and the curve is derived from the same rows the table lists |
| the count is the viewer's | measured across three viewers, §7 — and it turned out the *score* is too |
| `Sightings_anonymise` collapses the org stack to two colours | handled: anonymised reports collapse to one *Others* key, never one key per hidden organisation, or the legend would leak how many there are |
| `MISP.Sightings_range` caps the statistics series, so All time needs its own query | **moot.** That cap lives in `generateStatistics`, which this phase does not use |
| the write fans out | stated from a real count, §4.3 |
| the org-stacked histogram needs no new query, `attributesStatistics` already groups org × attribute × type × date | **wrong, and this is the correction.** The *SQL* groups by all four; `generateStatistics()` then collapses the date dimension out of the org breakdown and returns per-type totals plus a date-only sparkline. The grouped rows exist in `fetchGroupedSightings()`, which is private. This phase buckets `listSightings` rows in PHP instead — one fetch serves both the table and the histogram, and the two cannot disagree about which day a report lands on |

---

## 9. Shared code, and one thing that moved

§14.9 row 4. **No shared element was touched.** All five templates are
Value-Profile-owned, under
`app/View/Themed/Overmind/Elements/Values/View/`.

### 9.1 `value-profile.js` is unchanged

Not one line. The payload keeps phase 21's shape — parallel sparse daily
tallies plus a `plan` of grains — deliberately, so a zoom step and a
preset switch stay the same arithmetic in the browser. Two keys were
added (`from`, `clipped`) and the JS ignores both.

"The JS is unchanged so it must still work" is not a verification, and
the *data* behind it changed a great deal: 1,095-day spans against the
fixture's 440, six organisations, curves with leading nulls, and an org
list that can be empty. §10.4 asserts the invariants the JS relies on
against the real payload, and §10.6 drives the whole tab in a browser —
which is what found §8.3's bug.

### 9.2 `columnLabels()` moved out of the fixture

`value_sighting_chart.ctp` called
`ValueProfileFixture::columnLabels()` — harmless while the panel was
fixture-backed, and a live panel calling a test double the moment it was
not. The labels are about bucket units, so they moved to
`ValueProfileBuckets::columnLabels()`; the fixture delegates, so the two
cannot drift.

A grep is the check, and it is now part of the phase's lint pass:
`ValueProfileFixture` must not appear in any converted element.

### 9.3 One dead line removed

`value_sighting_list.ctp` assigned `$series` and never read it. Removed,
and `forSightingList` no longer builds a series for it — the panel that
draws no chart now builds none.

---

## 10. Verification — what was run

Against the Docker stack serving this worktree, 2026-08-27:
**3.8 million attributes, 4,175 events, 88 organisations, 112 sightings
across 12 organisations, 3 decaying models.**

### 10.1 The values, and why each one

Per §14.9 row 7. Six carry seeded reports (§2), three do not.

| Value | Occurrences | Reports | What it is for |
|---|---|---|---|
| `8.8.8.8` | 23 | 53 | the populated case: 6 organisations, 8 sighted occurrences, all three types, sources on 7 rows, both curves live |
| `2.2.2.2` | 13 | 27 | §14.6 across three viewers, and the value phase 22 used for it |
| `1.1.1.1` | 11 | 14 | sparse: 14 reports over 497 days. Its occurrences reach back to 2016, so the span cap bites |
| `github.com` | 21 | 6 → **4 visible** | the soft-delete gap, §8.1 |
| `45.155.205.233` | 2 | 3, all false positives | the disabled `Sightings` toggle, and a curve nothing has ever reset |
| `193.161.193.99` | 335 | 0 | the zero-report state **with a score** — and the occurrence cap, at 100 of 335 |
| `443` | 48,255 | 3 | matched on `value2`; the heaviest value on the instance and the one that found §8.2 |
| `0.0.0.0` | 33,110 | 0 | 3,948 days of span against the 1,095-day cap |
| `no-such-value-at-all` | 0 | 0 | five empty states |

### 10.2 Query counts and timings

One panel per process, so every row is a cold ACL-conditions cache and
its two `authorizedIds` queries — which is what a panel request is. The
MySQL buffer pool was warm; the first uncached touch of `443` measured up
to 1.2 s on the chart panel.

| Value | chart | list | decay | reporters | add |
|---|---|---|---|---|---|
| `no-such-value-at-all` | 2q / 15 ms | 1q / 24 ms | 2q / 27 ms | 1q / 21 ms | 1q / 20 ms |
| `github.com` | 16q / 45 ms | 9q / 20 ms | 16q / 44 ms | 9q / 21 ms | 1q / 15 ms |
| `2.2.2.2` | 20q / 43 ms | 12q / 20 ms | 20q / 54 ms | 12q / 29 ms | 1q / 20 ms |
| `8.8.8.8` | 21q / 85 ms | 13q / 30 ms | 21q / 52 ms | 13q / 21 ms | 1q / 30 ms |
| `45.155.205.233` | 18q / 120 ms | 10q / 83 ms | 18q / 90 ms | 10q / 77 ms | 1q / 59 ms |
| `1.1.1.1` | 19q / 171 ms | 11q / 87 ms | 19q / 99 ms | 11q / 59 ms | 1q / 55 ms |
| `193.161.193.99` | 9q / 48 ms | 1q / 14 ms | 9q / 67 ms | 1q / 26 ms | 1q / 34 ms |
| `0.0.0.0` | 8q / 293 ms | 1q / 28 ms | 8q / 223 ms | 1q / 22 ms | 1q / 133 ms |
| `443` | 18q / 309 ms | 10q / 50 ms | 18q / 362 ms | 10q / 23 ms | 1q / 199 ms |

**The ceiling is 21, on a value with 23 occurrences** — not on either of
the two with tens of thousands. What varies is how many organisations a
value's reports and events involve, because of §4.4's per-organisation
select inside `fetchAttributes`; what does not vary is the occurrence
count. `443` and `0.0.0.0` are slower not because they issue more
queries but because two of their queries have to touch every occurrence:
the summary aggregate and the capped `ORDER BY timestamp DESC` fetch.

### 10.3 What was asserted

- **`php -l` clean** over all thirteen changed and new files, including
  the seed script. Every line inside 80 columns; the one over-length line
  in the diff (`ValuesController.php:66`) is pre-existing.
- **The §14.3 grep returns nothing.** `value1` and `value2` appear in
  `Value.php` and nowhere else in the feature — not in `ValueProfile`,
  not in either tool, not in the controller, not in any element.
- **No tool takes a `$user`.** Grep over
  `ValueStatsTool`, `ValueDecayTool`, `ValueProfileBuckets`: no method
  signature contains one.
- **No converted element names `ValueProfileFixture`** (§9.2).
- **45 element renders** — five elements × nine values — through the same
  `View::element()` path `renderPanel` uses, with an error handler
  installed. **Zero notices, zero warnings, zero exceptions.**
- **The fixture still drives all five elements** (§14.8): four demo
  values plus an unknown one, 25 renders, clean. `45.155.205.233`'s
  sightings list is a 537 KB fragment there, which is the fixture's own
  data and not this phase's business.
- **Three viewers on `2.2.2.2`** — §7's table.
- **The empty states**, on `no-such-value-at-all`: `sighting_series` is
  null, the chart renders 768 bytes of empty state with no payload block
  at all, and the write card still renders, disabled, at 2,196 bytes.

### 10.4 The payload, checked against what the JS reads

34 assertions per value, over the JSON in `data-vp-sight-data`, on all
eight values that have one. All pass.

- every sparse daily offset falls inside the span;
- `daily.org` has exactly one series per entry in `orgs`, so the bar
  datasets and their labels cannot slip against each other;
- each curve has exactly one point per day of the span, stays within
  0–100, and has **no interior gaps** — a null is allowed only as a
  leading run, where the occurrence did not exist yet;
- every grain's label list ends on `today`, its bucket starts ascend from
  zero and the last one begins inside the span;
- the default preset is one of the presets offered, and every preset's
  window lies inside the plan;
- `models` and `curves` are the same length and in the same order;
- **the rail's printed score equals the curve's last point**, per model
  (§6).

Payloads: 11 KB at a 287-day span (`github.com`), 20 KB at 554 days
(`2.2.2.2`), 37–43 KB at the 1,095-day cap. The fixture's was 21.6 KB
over 440 days, so the cap is roughly twice it — and the day grain's 1,095
labels and titles are most of the difference, not the curves. §12.1 has
the follow-up.

### 10.5 What the rendered fragments say

Read off the markup rather than inferred:

- `8.8.8.8`'s sub-line: *"53 reports · 47 sightings, 4 false positives,
  2 expirations · last one 2 days ago"* — and all three toggles live.
- its `fp_moves_nothing` note, derived and naming the value's own last
  contradiction: *"The 4 false positives, the last of them on
  2026-08-21, leave every curve flat…"*
- its clip notice: *"Charted from 2023-08-29. This value was first
  recorded on 2022-06-28…"* — two different dates, which is the whole
  point of the notice. (An earlier version printed the same date twice;
  caught here.)
- six organisation legend keys, and a Reporters card summing to 53:
  CIRCL 17, ADMIN 13, CthulhuSPRL.be 8, abuse.ch 6, CUDESO 5, DECEA 4.
- both models' provenance: *"Last reset by ADMIN on 2026-08-25 ·
  lifetime 3 days"* and *"… lifetime 120 days"*, plus *"Highest of 23
  scored occurrences · held by the one in event 1"*.
- the contradiction clause, counting both non-resetting types: *"The 6
  reports that contradict the value are in neither…"*
- `8.8.8.8`'s fan-out: *"One row is written to each of the 23
  occurrences you can see, across 19 events and 8 organisations."*
- `193.161.193.99`: *"Nobody has reported seeing this."* across intact
  axes, *Never sighted* in the sub-line — **and NIDS at 2 in the rail.**
  That distinction is §9 of the tab brief calling itself the point, and
  it renders.
- its cap notice: *"Highest of 100 scored occurrences · held by the one
  in event 4091 · 100 of 335 occurrences scored."*
- `45.155.205.233`: three reports, all false positives, so the chart's
  organisation stack is empty while the Reporters card ranks two
  organisations — the two counts the panel's own legend note explains.

### 10.6 The browser pass

§14.9 row 8. The panel endpoints render HTML and MISP's API-key
authentication only applies to REST requests, so a fragment cannot be
fetched over HTTP without a session cookie. The tab was driven the way
phase 10 drove it instead (`02-sightings.md` §14 item 3): the five
fragments as `ValuesController::renderPanel` produces them, stitched into
one page with the shipped `value-profile.css`, `mainOvermind.css`,
`Chart.min.js` and `value-profile.js`, served over `http://127.0.0.1`,
and driven in headless Chrome. What runs is the shipped JavaScript
against the database's own data.

The harness carries `data-controller="values"` on its body because
`value-profile.js` gates every initialiser on it — so the gate is
exercised rather than bypassed.

**38 assertions on `8.8.8.8`, in both themes, all passing:**

- a live `Chart` instance with **12 datasets over 157 weekly labels**,
  three scales (`x`, `y`, `score`), the score axis capped at 100, and
  both bar and line datasets;
- **zero unresolved `var(--…)` colours** — the first bar dataset resolves
  to `#4c78a8` in light and `#6d9cc9` in dark, and the palette inverts,
  which is the property `02-sightings.md` §12 item 7 asks for;
- every `--vp-sight-*` and theme token resolves in both themes;
- toggling *False positives* takes the chart from 12 datasets to 11 and
  back, and flips `aria-pressed`;
- the range select moves between **90 daily columns and 157 weekly ones**,
  and the axis caption follows it from *per day* to *per week*;
- a drag on the navigator prints `2025-04-19 → 2026-07-10` in the range
  note, narrows the list from 53 rows to 25, and the panel sub-line
  agrees with the note;
- `load the rest` takes 10 rows to 53 and hides itself; `Clear` hides the
  note and restores the full window;
- the zoom renders its six steps (`out`, `in`, `left`, `right`, `reset`,
  `selection`) with the grain caption beside them;
- the rail draws two models — Phishing 8 flagged `decayed`, NIDS 69 not —
  each track filled to its own score, the viewer-scoping line present,
  six reporter bars, and every write control disabled;
- **no console error, no page error, no failed request.**

**And the eight states the main driver cannot assert against one value**,
each built and driven in turn — `193.161.193.99` and `0.0.0.0` (no
reports: **zero bar datasets, four line datasets, the "Nobody has
reported seeing this" overlay across intact axes, and two decay cards
still scoring**), `45.155.205.233` (the `Sightings` toggle disabled while
the false-positive series still draws one bar), `1.1.1.1`, `github.com`,
`443` (its cap notice reading *101 of 48255 occurrences scored* on
screen), `2.2.2.2` **as the CIRCL viewer** (7 rows, one organisation),
and `no-such-value-at-all` (no chart panel at all and four empty states).
All pass, none logs an error.

### 10.7 Not verified, and why

**A `Sightings`-formula or `PolynomialExtended` model.** No shipped model
uses either, so the per-occurrence fallback path in §5.2 is written and
unexercised.

> **Carried to the Verdict phase** (2026-08-28). It stays unexercised
> here rather than being covered by a model written for the purpose: the
> Verdict tab reworks the decaying model substantially, so the formula
> classes will be under a microscope there and exercising this path is
> that phase's work rather than a bolt-on to this one. It is the only
> unexercised branch this phase leaves.

**`Plugin.Sightings_anonymise`.** Off on this instance. The *Others*
collapse is implemented and unrun.

> **Now run** (2026-08-28), while linking the `Organisation` column: the
> setting was turned on, `8.8.8.8` rendered for a CIRCL org admin, and
> the foreign reports collapsed to one unlinked *Others* row while the
> reader's own organisation stayed named and linked. The setting was
> turned off again. Nothing on the instance has a null `org_id`, so
> flipping it is the only way to reach this state and there is no seeded
> row standing in for it.

---

## 11. What this changes in the contract

### 11.1 §14.6's exception has two members

The amendment §7 argues for. §14.6 as written grants the permanent
caveat line to the Verdict tab and adds *"Nowhere else on the page
carries it."* The decay card now carries one, for the same reason and
under the same test — always shown, identical for every reader,
therefore carrying no information.

The rule to read out of it: **a panel that renders a computed judgement
gets a permanent caveat; a panel that renders a count does not.** A count
being viewer-scoped is invisible and harmless. A judgement being
viewer-scoped is a number two colleagues will disagree about out loud.

### 11.2 §14.5's open decision is closed

`ValueDecayTool` exists, and the aggregation rule §14.5, §7.9,
`02-sightings.md` §11 and §16 all left open is decided in §5. §14.11's
last bullet — *"The decay aggregation rule. Named, given an owner in
`ValueDecayTool`, and still undecided."* — comes off the out-of-scope
list.

### 11.3 §14.10 gains one hazard

**`MispAttribute::fetchAttributes` resolves organisations one query at a
time** (§4.4). It is the mirror of §14.10's `fetchSimpleEvents` note: a
batched form exists trivially, the fetcher does not use it, and the cost
grows with the number of organisations a page touches. Every attribute
index in MISP pays it.

### 11.4 One §11 prediction was wrong

`02-sightings.md` §11 closed with *"What needs no new query: the
org-stacked histogram. `Sighting::attributesStatistics()` already groups
org × attribute × type × date in SQL."* The SQL does; the public method
throws the date dimension away before returning. §8.4's last row has it.

### 11.5 The three concepts

§14.9 row 9, per
[`../value-profile-coverage.md`](../value-profile-coverage.md) §5.

- **Proposals on the value — no, and it is not this tab's.** A
  `ShadowAttribute` proposes a change to an attribute; nothing proposes
  a sighting. There is no shape for it here.
- **Presence in feeds and sync servers — no, with a reason that is
  nearly a yes.** A sighting pulled from a sync peer is
  indistinguishable in this tab from a local one: `Sighting` has no
  provenance column, only `org_id` and an optional free-text `source`,
  and the seeded data shows the source is usually empty. Naming a
  peer would need `sightings` to record where a row came from, which is
  a schema change and out of §14.11's scope. **`Plugin.Sightings_anonymise_as`
  makes this sharper**: an instance can rewrite every foreign sighting's
  organisation to one stand-in on push, so a peer's tab may already be
  showing one organisation where there were six. Worth stating wherever
  the feeds panel lands.
- **Event reports on the value's events — no.** An event report is prose
  attached to an event; a sighting is filed against an attribute. The
  overlap is the event id and nothing this tab draws is per event.
  `value-profile-coverage.md` §6 forecasts a standalone element for
  event reports and that is where it belongs.

None of the three extends a row this phase filled, so §14.12's
hole — *"who owns a row that a later phase amends"* — is not touched
here.

---

## 12. Exit criterion, and what is left

`02-sightings.md` §13 asks that artifact `S1` be recognisable in the
browser: one chart carrying both the bars and the curves, a brush that
drives the table under it, and a page that states in words that a false
positive moves no score. **All three are now backed by the database
rather than by a literal**, and the third has stopped being a statement
the fixture could contradict: the curve is derived from type-0 reports
only, so a contradiction cannot move it.

It is verified server-side (§10) **and in the browser** (§10.6), where
the whole tab was driven in both themes across nine values — and where
the one real defect of the phase turned up (§8.3).

### 12.1 Follow-ups this phase names

**Five were named. Four are done and one will not be built** — settled on
2026-08-28, in the commits that follow this phase. The list is left as
written with each outcome under it, because what was predicted and what
it cost are both worth keeping.

- **The day grain's labels are most of the payload.** 1,095 labels plus
  1,095 titles is roughly 26 KB of the 43 KB ceiling, and every one is
  derivable in the browser from `plan.from` plus an offset. Trimming them
  is a JS change and would roughly halve the fragment. Not done here
  because this phase deliberately did not touch `value-profile.js`.

  **Done.** The chart fragment went 52.0 KB → 27.6 KB and its payload
  38.4 KB → 13.9 KB, so *roughly halve* was right.
  `ValueProfileBuckets::plan` sends the day grain's `label` and `title`
  as null and `zoomDayLabel` writes them. Two things the estimate did
  not include: the grain needs a `count`, since one with no label array
  cannot be measured by it; and `today` — the last bar's label at every
  grain — is a translated word and the one string that cannot be
  derived, so it ships once as `plan.last_label` and the browser
  substitutes it. All 1,095 derived titles were diffed against
  `describe()` and are identical.

- **The gap rows `02-sightings.md` §1 called the best single idea in the
  set** — *"8 days with no sighting · NIDS 80 → 55 · crossed below 60 on
  2025-08-07"* — are still deferred, and are now cheaper than when they
  were deferred: the envelope already carries a per-day score and a
  per-day owner, so the crossing dates are a walk over an array the
  panel has in hand.

  **Will not be built**, and cheapness is why the decision had to be
  taken rather than left: it would have been easy to build the wrong
  thing. A gap row puts a derived claim about a score into a table whose
  every other row is one report somebody filed, and the two are not the
  same kind of thing. The question it answers is the chart's, and the
  chart answers it — the curve crosses the threshold line exactly where
  the row would have said so. `02-sightings.md` §11 carries the
  decision.

- **`S3`'s baseline split**, false positives drawn below the axis, still
  deferred. Also cheaper now: `daily.fp` is already its own series.

  **Done**, before this list was next read: contradictions and
  expirations hang below the axis at every grain and in both themes.
  This entry was already stale when the tab was reviewed on 2026-08-28.

- **The Overview's `value_sightings` card** is the one sightings-shaped
  element still on the fixture. It needs `sightings.spark`, which is 40
  buckets over 90 days — derivable from the same rows this tab already
  fetches, and the reason it was not done here is that it belongs to the
  Overview's phase and would have meant converting one card of a tab
  whose verdict card is blocked.

  **Done**, and converting one card of a blocked tab turned out to be
  the right shape rather than a compromise — §14.12 already says a tab
  is not indivisible. `ValueProfile::forSightings` shares this tab's
  `sightingContext`, so it costs the same 13 queries the list and the
  reporters panels do, and the card and the tab cannot disagree because
  they are not two readings of the database but one. The rest of the
  Overview, `value_verdict_card` included, stays on the fixture.

- **Every Values panel returns 500 for a `.json` extension**, including
  the four phase 22 converted and the fixture-backed ones. Pre-existing,
  not this phase's, and harmless — these endpoints are not API surface —
  but it is the reason API-key authentication cannot be used to fetch a
  fragment for verification, so it is worth someone's ten minutes.

  **Done, and the diagnosis was wrong in a way worth recording.** The
  500 is not about JSON: `AppController` resolves a theme only for a
  non-REST request, an extension makes a request REST, and every element
  on this page lives only under `Themed/Overmind` — so the render had no
  theme path and threw `MissingViewException`. The same exception is
  reachable with no extension at all, on an ordinary browser request, if
  `MISP.enable_themes` is off. `ValuesController` now refuses a
  non-HTML extension with a 404 and falls back to its own theme when it
  has none.

  **It does not buy what this entry wanted it for.** Fetching a fragment
  with an API key needs the request to be REST for the authkey to be
  read and non-REST for the theme to resolve, and those are the same
  flag. Verification still goes through a browser or a console render.
