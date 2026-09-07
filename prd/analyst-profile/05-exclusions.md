# PRD: Analyst Profile — phase 4, exclusions

**Built 2026-09-07.** Depends on phase 2
([`03-signals.md`](03-signals.md)). Small phase, one structural fix.

Covers the `exclusions` section, and the split of `not_counted` into the two
different things it currently carries.

What shipped: `ValueExclusionTool`, `orgs.own` as a predicate in
`Value::conditionsFor()`, `sightings.self` as a row filter inside the sighting
build, `feeds.mirrored` as a provider fold, the `reason` key on every
`not_counted` entry, and the removal of every per-value statement about the
reader's permissions. Verified by **42 harness checks with no database and 18
against the dev instance**. Five findings are in §7, and three of them changed
the design: the section needs three mechanisms rather than one (§7.1), the
ACL row is gone rather than rebuilt (§7.2), and the harness spent its first
run validating a context shape that does not exist (§7.3).

## 1. What ships

Filters that run over evidence **before** any signal sees it, and the change to
`not_counted` that stops the page attributing the viewer's ACL to the analyst's
profile.

## 2. `not_counted` is two things wearing one coat

The fixture documents `not_counted` at `ValueProfileFixture.php:2261` as
*"evidence the profile deliberately set aside"*. Its actual entries on the
malicious value are:

| Entry | What it really is |
|---|---|
| *"4 occurrences — outside your ACL. Excluded, not hidden. The score you see is the score for your permissions."* | **The viewer's permissions** — and not something the page should say at all (§7.2) |
| *"Feed presence alone — feeds that merely mirror CIRCL OSINT are not independent corroboration and score once, not three times"* | **Profile policy.** A de-duplication rule |
| *"Self-sightings — 3 sightings from the same org that created the attribute, within an hour of creation"* | **Profile policy.** An exclusion with a time window |

And on the flux value, a fourth kind: *"21,904 correlations"* — the correlation
engine gave up, so a signal had no input. Neither ACL nor policy; a fact about
the data.

**Three kinds, one block, no visual distinction.** A reader cannot tell which
of those they could change by editing their profile, which is precisely the
question the block exists to answer once profiles are real.

### 2.1 The split

`not_counted` keeps its template (`value_verdict_not_counted.ctp`) and gains a
`reason` on each entry:

```php
'not_counted' => array(
    array('reason' => 'acl',      'title' => '4 occurrences',   'note' => '…'),
    array('reason' => 'policy',   'title' => 'Self-sightings',  'note' => '…',
          'exclusion_id' => 'sightings.self'),
    array('reason' => 'nodata',   'title' => '21,904 correlations', 'note' => '…'),
)
```

- **`acl`** — **dropped, and so is the row (§7.2).** The page says nothing
  about the reader's permissions, on any value. `reason` therefore has two
  values in practice, not three.
- **`policy`** — an exclusion the profile applied. Carries `exclusion_id`, which
  makes it **linkable to the profile's own editor** (phase 8). This is the
  payoff: *"why doesn't this count?"* becomes a click.
- **`nodata`** — a signal had no input. Distinct from silent
  (`03-signals.md` §4.2), because silent means *evaluated and nothing to say*
  while this means *could not evaluate*.

The visual treatment stays one list; the difference is that a `policy` entry is
actionable and a `nodata` one is a statement. Minimum viable version: `policy`
rows carry a link, the others do not.

## 3. The `exclusions` contract

```json
"exclusions": [
  { "id": "sightings.self",    "enabled": true, "within_hours": 1 },
  { "id": "feeds.mirrored",    "enabled": true, "dedupe_by": "provider" },
  { "id": "orgs.own",          "enabled": false },
  { "id": "evidence.window",   "enabled": true, "days": 90,
    "min_occurrences": 10000 }
]
```

**Applied once, during the build, not per signal.** Two signals reading
sightings must see the same filtered set or the ledger's rows disagree about
how many sightings exist, and a reader summing them by hand would be right to
complain. That property holds; the *mechanism* is three, not one, and §7.1 is
why — the aggregate half of a value's evidence never exists as rows for a
filter to walk, so `orgs.own` is a query predicate rather than a pass over
`$context`.

**Not class-per-id.** The four ids operate at three layers and could not share
an interface without one of them pretending: one contributes SQL, one filters
rows, one folds a list. §6 already rules out the discovery a class-per-id shape
would exist to serve, so the set is closed and `ValueExclusionTool` holds all
of it.

### 3.1 The four in v1

**`sightings.self`** — sightings from the org that created the occurrence,
within `within_hours` of its creation. The argument is on screen already: an
org confirming its own fresh report is not corroboration. Note the window
matters — a self-sighting a year later *is* information ("we still see this"),
which is why this is a window and not a blanket rule.

**`feeds.mirrored`** — feeds whose content derives from another source counted
once, not per feed. Needs a notion of a feed's upstream, which MISP does not
store — `feeds` has `provider`, `url` and `source_format`, and nothing that
says "this mirrors CIRCL OSINT". So the dedupe key is either `provider` (cheap,
wrong when one provider runs unrelated feeds) or a profile-supplied map (honest,
and more reference data). **Recommendation: `provider` in v1, with the map as a
phase 6 extension if anyone asks.** State the imprecision on the page rather
than implying an exactness that is not there.

**`orgs.own`** — exclude the viewer's own organisation's occurrences and
sightings, so the verdict reflects *what others say*. Off by default and
included because it is the one exclusion an analyst will genuinely want and
cannot get any other way: an org that reported a value cannot currently tell
whether the community agrees with it.

**`evidence.window`** — the engine's cost budget, decided 2026-09-03
(`03-signals.md` §2.3, review A4). On a value with more than
`min_occurrences` occurrences, row evidence older than `days` is not fetched;
below the threshold it does nothing. Two properties set it apart from its
three siblings and both are stated rather than implied:

- **It filters row evidence only.** The aggregate class — the staleness
  clock, reporting breadth, continuity buckets — is computed whole-history
  regardless (`03-signals.md` §2.3), or the window would blind the freshness
  clock and undercount long-history breadth. It is therefore the one
  exclusion the clock in `06-staleness.md` §3.3 does *not* see.
- **It runs first**, before the semantic exclusions, because it defines what
  is fetched at all; the others then filter what arrived.

Its `not_counted` sentence names the numbers: *"long history — scored from
the last 90 days; 41,000 older occurrences not counted."* The hot-value tier
above it — `over_correlating_values`, where row-hungry signals give up
entirely — is not an exclusion: it is a fact about the data, and it lands
under `nodata` like the flux value's correlations.

### 3.2 What is deliberately not an exclusion

**ACL.** Filtering by permission is not a rule the profile applies; it is the
boundary of what the profile can see. Making it an `exclusion` id — even a
locked one — would put it in the same list as configurable policy and invite
somebody to add an `enabled: false`.

**Blocklists.** `org_blocklists`, `event_blocklists` and
`sighting_blocklists` already filter at the query layer, instance-wide. A
profile re-litigating them would either duplicate or contradict an
administrator's decision.

## 4. Interaction with the exact-sum invariant

Exclusions change *inputs*, not contributions, so §5.1 is untouched: fewer
sightings means `sightings.volume_recency` produces a smaller number, and the
ledger still sums to the score.

The one thing to get right is **ordering with trust weighting** (phase 6). An
org excluded by `orgs.own` must not also be counted at its trust grade — so
exclusions run first, unconditionally, and trust weighting applies to what
survives. Stated here because the opposite order produces plausible-looking
numbers that are wrong.

## 5. Verification

**Where each item is asserted.** Items 1, 2, 4 and 5 are
`05-exclusions-harness.php`, 44 checks with no database. Item 3 is
`05-exclusions-live-probe.php`, 18 checks against the dev instance, because a
condition-class exclusion's entire mechanism is SQL. Item 4 changed shape with
§7.2: there is no `acl` row to render beside a `policy` one, so what is
asserted is that no row claims to be about the ACL and the assessment carries
no permissions caveat either.

1. Each exclusion toggled on and off on the same value: the ledger row's number
   changes, the sum still equals the score, and a `policy` entry appears and
   disappears from `not_counted`.
2. `sightings.self` with `within_hours` at 1 and at 8760 — the same value
   produces different sighting counts, and the note states the window.
3. `orgs.own` as a member of a reporting org: the reporting-breadth row drops
   by one org and the note says so.
4. An `acl` entry and a `policy` entry rendered together: the ACL one leads,
   only the policy one is a link.
5. A value where every sighting is excluded: `sightings.volume_recency` must be
   *silent* (`03-signals.md` §4.2), not fire with zero, and the exclusion
   appears in `not_counted` — otherwise the page shows a `0` row and a note
   saying the sightings were excluded, which reads as a contradiction.

## 6. Out of scope

- The feed-upstream map (§3.1, deferred to phase 6 if requested).
- Any change to blocklists or ACL.
- Exclusion rules an analyst writes themselves. **Still out after D12**,
  which gave a filesystem loader to `signals` and `escalations` only — those
  two resolve to implementations, whereas an exclusion is configuration the
  engine applies directly (`03-signals.md` §10). A custom exclusion would need
  either a loader of its own or the expression language D12 rejected; neither
  is v1.

## 7. What building it changed

### 7.1 One sentence, three mechanisms

§3 says exclusions are *"applied to `$context`, once, not per signal"*, and
that describes one property correctly and the implementation not at all —
because **half a value's evidence never exists as rows**. The occurrence
tally, the reporting breadth, the publication split and the monthly activity
are `COUNT DISTINCT` aggregates computed in SQL, so there is no set of
organisations sitting in the context for a filter to walk.

So the section is three mechanisms, chosen by the layer the evidence lives at:

| Rule | Mechanism | Why it cannot be the others |
|---|---|---|
| `orgs.own` | a predicate in `Value::conditionsFor()` | the counts are aggregates; filtering after the fact would leave the tally and the breadth naming different sets |
| `sightings.self` | a row filter, before the rows are tallied | the rows exist, and `evidence.window` already filters them there |
| `feeds.mirrored` | a fold over one fetched list | there is nothing to filter, only duplicates to merge |

**`conditionsFor` is what makes the first one honest.** It is the single place
every value-scoped aggregate in `Value.php` builds its value predicate — all
fourteen of them — so one `exclude_orgs` key reaches the tally, the stance
table, the types, the monthly activity and the sighted-occurrence set at once.
The live probe asserts the agreement that follows and it is the phase's
load-bearing check: with the rule on, `8.8.8.8` goes from 26 occurrences in 8
organisations to 14 in 7, and the stance table's row count still equals the
tally's org count. A post-filter would have produced 7 stance rows against a
tally still reading 8, which is two numbers on one page that cannot both be
right and neither of which looks wrong.

What survives from the one-sentence version is the property that mattered:
every signal sees the same evidence, because the filtering happens once,
during the build, and nothing downstream can opt out.

### 7.2 The page says nothing about the reader's permissions

**Decided 2026-09-07, and it removes a specified feature rather than
rebuilding it.** §2.1 gives `not_counted` three reasons and puts `acl` first:
*"4 occurrences — outside your ACL. Excluded, not hidden."*

The rule is simpler than the design had it: **MISP discloses what a reader is
allowed to see. That is how the platform works, the people using it know it,
and the page does not remind them.** A per-value line about permissions tells
a reader nothing they had not already assumed, and it hints at the existence
of records they have no business knowing about — on a page that accepts any
value typed into the URL, that hint is available for every indicator on the
instance.

Two things follow, and the second is the one this document got wrong first
time round:

- **The count cannot be computed anyway.** Knowing how many occurrences a
  viewer may *not* see requires a count taken without their ACL. Every other
  count on this page is the viewer's own precisely so the page is not an
  oracle, and one exception would undo all of them.
- **Removing the count is not enough.** The first implementation kept the
  caveat and dropped the number — an unconditional *"computed from what your
  permissions allow"* in the provenance band. That is still the page
  volunteering that something might be missing, on every value, forever; it
  is the same hint at one bit per page load, plus a line of noise on the
  values where nothing is hidden at all. It is gone.

So `reason` has **two** values, `policy` and `nodata`, and every row in the
block is either something the analyst chose or something the data refused.
`ValueVerdictTool` emits no `acl_note`, `value_verdict_meta.ctp` no longer has
a slot for one, and the occurrences panel's own
`Showing 6 of 10 — 4 are hidden by distribution rules` band is gone with it.

**What is not affected**, because it is not a per-value statement: a panel
saying what *the instance's policy* or *the reader's role* does on every
value. *"Sync server hits require site admin, so they are not counted here, on
any value"* reveals nothing about the value on screen and explains why a panel
is empty; the same goes for the sighting-policy note and the history panel's
scope line. The distinction is per-value versus per-instance, and it is the
line to hold when phase 9 rewrites this copy.

**One place for phase 9 to keep honest.** The Occurrences panel's subhead
reads *"Showing 6 of 10 occurrences"* from `$stats['shown']` and
`$stats['total']`. As pagination that is fine — *six rows rendered of your
ten* — and it is what the template will mean once `total` is the viewer's own
count, which §14.6 requires of every count on the page. The fixture authors it
as 6 of 10 *with four hidden by ACL*, which is the retired leak wearing a
pagination label. The rule: `total` is what this reader can see, never what
exists.

### 7.3 The harness validated a context shape that does not exist

**Found by the live probe, and only because it checked the rule's inputs
rather than its output.** `sightings.self` compares a sighting's org against
the organisation that reported the occurrence, which it looks up in the map
from `Value::sightedOccurrenceIdsFor()`. That accessor does not return
CakePHP's nested result — `keyById()` folds each row into a flat
`id => array('timestamp', 'type', …)` — and both the implementation and the
harness's fixture read it as `['Attribute']['timestamp']` and
`['Event']['orgc_id']`.

The failure mode is the dangerous kind. An occurrence the rule cannot resolve
makes its sightings **undecidable**, which is the correct conservative answer
— keep the sighting, and say how many could not be checked. So the rule
excluded nothing, reported nothing, and looked exactly like a rule with
nothing to do. 44 harness checks passed against the invented shape, and the
first probe run reported *"0 self-sightings excluded"* as a clean result.

What caught it was an assertion about the inputs: *every sighted occurrence
names the organisation that reported it*. It read 0 of 8. With the shape fixed
— and `orgc_id` added to `keyById()` under the same *absent means not asked
for* guard the object columns already use — the same value excludes **30**
self-sightings over a ten-year window.

Two rules for this corpus come out of it, and both are cheap:

- **A filter's tally is not evidence that the filter ran.** Zero removed and
  zero decidable are the same number. Assert the inputs.
- **A harness fixture that the implementation's author also wrote proves
  agreement, not correctness.** The two agreed perfectly about a shape neither
  had checked.

### 7.4 The shipped window excludes nothing on this instance, and that is fine

At `within_hours: 1` the rule removes **0** sightings from every value on the
dev instance; at ten years it removes 30 from `8.8.8.8`. So every
self-sighting in this data was filed well after the report it confirms —
which under §3.1's own argument is information (*we still see this*) rather
than self-confirmation, and is exactly what the window exists to distinguish.

Recorded because the probe now says which of *"the rule removed nothing"* and
*"the rule could not run"* happened. Before §7.3 it could not, and the
difference was the whole bug.

### 7.5 A server is not a mirror

`feeds.mirrored` folds by `provider`, and `externalPresence` returns MISP
servers in the same `sources` list as feeds. A server sharing a provider
string with a feed is not a copy of it — it is another instance's own holding
of the value, which is a second opinion and the most valuable kind of external
corroboration the page has. Servers are therefore never folded, and the fold
is scoped to `scope === 'feed'`.

Also: `provider` was not on the assembled source array at all — `externalPresence`
selected `id`, `name`, `url`, `kind` and `scope` — so the dedupe key the
specification recommended did not reach the tool. It is one field, added.
A feed naming no provider folds under its own name, so it is never merged with
anything, which keeps the imprecision §3.1 admits to from growing a second
head.
