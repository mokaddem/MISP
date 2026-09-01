# PRD: Value Profile — going live

**The contract, not a phase.** Extracted from
[`value-profile-page.md`](../value-profile-page.md), where this was §14.
**The section numbering is deliberately kept:** the corpus carries 29
references of the form `§14.x`, and they resolve to the headings below.

This directory is to the live campaign what `value-profile-tabs/` was to the
fixture campaign: the contract first, then one document per phase beside it.
The difference is that `00-shared.md` there *was* phase 8, because it built
primitives. This builds nothing — it is rules — so it carries no phase number
and the first live phase is 22.

---

## 14. Going live — the wiring contract

Every phase from 8 to 21 was fixture-first: real templates, real ajax endpoints,
real interactions, and no model query behind any of them. All nine tabs render
that way still, with one exception — **phase 22 took the Occurrences tab live**,
and §14.12's board is where to see how much of the page that is (one row of
twenty-seven). This section is the contract for taking the rest live — the rules
a phase follows when it replaces `ValueProfileFixture` with the database, and the
reasoning behind each one.

**It is not a phase.** §7 through §13 are each a phase brief; this is what they
inherit, in the role `value-profile-tabs/00-shared.md` played for the fixture
passes. Nine tabs across fifty-one elements is not one phase's work, and these
rules have to hold for whichever of them goes first.

Writes are not here either. The analyst's own tags, opinions and notes on a
value need new storage and touch sync, which is a different risk profile from
replacing a read; they are specified in
[`value-profile-writes.md`](value-profile-writes.md). Under §14 the page still
reads only, and every disabled control stays disabled.

### 14.1 What this replaces, and what it must not disturb

Replaced: the data source. Nothing else.

Untouched by a live phase — and a live phase that changes one of these has
grown out of its remit: the nine-tab registry (§2.5), the element names and
their locations, the 27 endpoint URLs, the five honest states, the disabled
treatment of every write control, and the both-themes requirement.

The one structural change is *where* the fixture is read.
`ValuesController::profileFor()` calls `ValueProfileFixture::forValue()`, which
builds **every** tab's data and hands the whole array to whichever endpoint
asked for it. With a fixture that is one array literal and costs nothing. Live
it is nine tabs of queries per panel request, twenty-odd panel requests per tab
visit. So the live facade answers **per panel**, and `profileFor()`'s
whole-profile shape does not survive the transition.

That is the only place the skeleton was built around a property a fixture has
and a database does not. Everywhere else, §1.3's *"fixture shaped like the real
thing"* held: the templates read MISP's own array shapes and field names, so a
panel going live is a data source swapped under an unchanged template.

### 14.2 Two models, and what each owns

`ValuesController` already declares `public $uses = array()` with a comment
saying the subject is a value rather than a row of one table, so panels load
their own models as they land. This is that, and it keeps the page inside
MISP's MVC rather than beside it.

**Why two models and not one.** §4 refused to build this page inside
`AttributesController` on the grounds that ~3,800 lines across 40+ actions is
not somewhere to add a feature. A single `Value` model carrying nine tabs of
panel assembly reaches the same size by the same route, and the refusal would
have bought nothing.

```
app/Model/Value.php          identity and value→occurrence resolution.
                             Small, and deliberately kept small.
app/Model/ValueProfile.php   useTable = false. The per-panel facade:
                             one public method per panel, returning the
                             array shape that panel's template already
                             reads.
```

A table-less model is established practice here — `Community`, `Module` and
`EventLock` all set `useTable = false`.

| Layer | Owns | Must not |
|---|---|---|
| `Value` | the value's identity, its ACL'd occurrence set, which part of a composite matched | assemble a panel, know what a tab looks like |
| `ValueProfile` | per-panel assembly: call `Value` for the occurrence set, the domain models for their own data, the tools for aggregates | issue its own SQL against attribute value storage |
| domain models (`Sighting`, `AnalystData`, `AuditLog`, `MispObject`, `DecayingModel`) | their own data and their own ACL, as they do for every other page | be bypassed because a value-scoped query would be shorter |
| `app/Lib/Tools/Value*` | computation over data handed to them | accept `$user`, or fetch anything |

The swap is then one line per endpoint:

```php
// before
$this->renderPanel($this->profileFor($b64value), 'value_sighting_chart');
// after
$this->renderPanel(
    $this->ValueProfile->forSightingChart($this->Auth->user(), $value, $opts),
    'value_sighting_chart'
);
```

Tools are constructed by the model that owns the data, which is MISP's own
convention — `new TrendingTool($this)` inside `Event.php:9969`.

### 14.3 The value-identity seam

There is a feature coming that moves `attributes.value` into a table of its own
— possibly several tables, split by type where that helps. **This page does not
build it, does not wait for it, and does not assume its shape.** What it must do
is arrange that when it lands, one file changes.

The rule, and it is the whole of §14.3:

> **`Value` is the only file in this feature that names `value1` or `value2`.**

Three accessors, each with a today form and a tomorrow form:

```php
// A condition fragment. Composes with buildConditions($user) untouched,
// so every existing ACL fetcher keeps working exactly as it does today.
Value::conditionsFor($value, array $options = [])
    today     ['OR' => ['Attribute.value1' => $v, 'Attribute.value2' => $v]]
    tomorrow  a subquery or join against the value table(s)

// An ACL'd id set, for the aggregation path in §14.4 tier 2.
Value::occurrenceIdsFor(array $user, $value, array $options = [])

// Which side of a composite matched, per occurrence.
Value::occurrencePartsFor(array $user, $value)   → [attribute_id => 1|2]
```

`$options['types']` exists for the split: a caller that knows which types it
wants passes them and a per-type table can be selected; a caller that does not
gets the union. Adding it now costs a parameter nobody has to use; retrofitting
it means revisiting every call site.

The part indicator is not a storage detail leaking upward. §2.3 already renders
`value2_note` — *"1 occurrence has it as value2 of domain|ip"* — and
`Sighting::saveSightings` (`Sighting.php:795`) already matches `value1` OR
`value2` when writing a sighting by value. Which side matched is something the
page states; it belongs in the seam's answer.

**Identity is the value, not the pair `(type, value)`.** STIX's `ipv4-addr` has
one id-contributing property, MISP's correlation engine already correlates
across types, and §2.3's `types` array already treats type as a facet of the
value rather than part of what the value *is*. A composite attribute therefore
contributes two identities, which is the same reading `saveSightings` takes.

`Value::uuidFor($value)` lives here too. It is the deterministic identity the
writes document needs, and keeping it beside the resolution logic means reads
and writes normalise a value through one function rather than two that drift.

Today's lookup is index-backed but the indexes are prefixes —
`KEY value1 (value1(255))` over a `text` column. MySQL uses the prefix and then
rechecks the row, so `value1 = ?` stays exact; the cost is selectivity for
values sharing a 255-character prefix, not correctness. Worth knowing before
someone reads a slow query as a wrong one.

**Verification is a grep**, which is the point of stating the rule this way:

```
grep -rn "value1\|value2" app/Model/ValueProfile.php \
    app/Lib/Tools/Value*.php app/Controller/ValuesController.php \
    app/View/Themed/Overmind/Elements/Values/
```

must return nothing.

### 14.4 Rows come from fetchers; counts may use their own SQL

Three tiers, and a live phase states which one each of its panels used.

**Tier 1 — the default. MISP's existing ACL fetchers, and nothing else.**
They already handle who may see what, and that is code with years of use behind
it. The ones this page needs:

| Need | Fetcher | Note |
|---|---|---|
| occurrence rows | `MispAttribute::fetchAttributes` / `fetchAttributesSimple` | `fetchAttributesSimple` scopes via `buildConditions($user)` (`MispAttribute.php:2052`) |
| event metadata for N events | `Event::fetchSimpleEvents($user, $params, $includeOrgc)` | **one** ACL'd query for all N (`Event.php:2862`); `recursive => -1` |
| the full event graph | `Event::fetchEvent($user, ['eventid' => [...]])` | the id goes into an `IN (…)`, so this also takes a list |
| sightings | `Sighting::listSightings` / `attributesStatistics` | carries `Sightings_policy` and anonymisation |
| notes and opinions | `AnalystData::fetchChildNotesAndOpinions` | depth 2 is a fetch limit, per §7.9 |
| audit rows | the ACL model §8.2 settled | per-event, not `model_title` |

**Never one call per event.** A value in seven events is one
`fetchSimpleEvents` — or one `fetchEvent` with seven ids — and not seven calls.
§8.2 measured the alternative at seven `fetchEvent()`s before a single audit row
is read, and that was with `fetchSimpleEvents` unaccounted for. This is the one
performance rule §14 does commit to, and it is a batching rule rather than an
optimisation: the cost of getting it wrong grows with the value.

**Tier 2 — permitted, with a written reason. An aggregate query over an
already-ACL'd id set.** Allowed only where the answer is a count or a group and
materialising rows to count them in PHP would be the wrong shape. It lives in a
model or a tool, never in a controller, and it receives the id set from
`Value::occurrenceIdsFor($user, …)` — so permissions were settled before the
aggregate ran.

Counting in PHP is not the safe fallback it looks like. §7.9 already found why:
*"tallying the fetched page works at ten rows and stops being honest the moment
the table paginates"* — you are then counting one page and labelling it a total.
§10.4 (phase 18) records the same trap from the other side, noting that the
facet rail and the row list agree today only because the fragment carries all
748 rows.

**Tier 3 — forbidden.** Any query that applies its own ACL. Any query that
reaches attribute value storage outside `Value`. Any panel whose query count
grows per occurrence without a stated cap — §9 is a worked example of what that
costs when nobody checks.

**Caching is deliberately not in this contract.** Optimisation is a later stage,
and a cache over permission-scoped data is the wrong thing to add before the
uncached shape is measured. The batching rule above is the mitigation §14 takes
now. When optimisation happens, the risk to weigh first is that a cache key
must capture everything affecting what a viewer may see; missing one component
shows one user another's view, which is the worst defect this page could carry.

### 14.5 Aggregation tools take no `$user`

> **No tool under `app/Lib/Tools/Value*` accepts a `$user` parameter.**

The owning model pre-scopes and hands the tool a set that is already filtered.
A tool therefore *cannot* leak data the viewer may not see, and that is
checkable rather than argued.

This is already the house pattern rather than a new rule.
`ValueProfileBuckets` — shipped in phase 20, three callers — is a pure static
class whose `tally()` and `sparse()` aggregate over arrays handed to them, and
it has never needed a user. Two shapes are acceptable, and the invariant is the
same for both:

- **Pure and static**, like `ValueProfileBuckets`, when the tool computes over
  data it is given. Preferred.
- **Model-injected**, like `TrendingTool`, when the tool issues its own tier-2
  aggregate SQL and needs a connection. Still no `$user`.

New:

| Tool | Owns |
|---|---|
| `ValueStatsTool` | facet counts, org and type rollups, histograms, the verdict's composition segments — the cross-cutting aggregates several tabs share |
| `ValueDecayTool` | the decay series: §11's hourly-to-daily/weekly resampling, and the aggregation of ten per-attribute curves into one per-value score — **decided by phase 23**, `23-sightings.md` §5 |

`ValueDecayTool` exists partly so that decision has one home. §7.9 recorded
that `DecayingModel::getScoreOvertime()` is per attribute, that there is no
value-scoped endpoint and no aggregation rule, and that the phase 7 deck
proposed *max across occurrences, labelled with its occurrence*.
`02-sightings.md` §16 sharpened it and left it open.

**Phase 23 closed it, and took the deck's proposal.** The rule is the per-day
maximum across occurrences, labelled with the occurrence holding it, and the
label is half the rule rather than a decoration on it — maximum is not monotone
in evidence, so a reader who disagrees with the number is owed somewhere to go
and argue with it. `23-sightings.md` §5 carries the argument, the three bounds
that make it affordable, and the one case where the base-score grouping behind
it does not apply.

Reused rather than rebuilt: `Sighting::attributesStatistics()` (already groups
org × attribute × type × date in SQL — §7.9), `ValueProfileBuckets`,
`AuditActionMeta`, `CustomPaginationTool`, `CidrTool`, `ValueDisposition`.

### 14.6 Every count on the page is the viewer's

**Decided.** Every number the page renders counts only what the viewer may see.
The page states nothing about data hidden from them — not a count, not a
proportion, and not the bare fact that something is hidden.

**Why.** The URL takes any value the reader types. A count that includes
invisible occurrences therefore turns the page into a membership oracle: anyone
can probe any indicator and learn whether it exists on the instance, regardless
of distribution. Subtracting the viewer-scoped facet rail from an
instance-wide banner leaks the *types* of the hidden rows as well. A note that
appears only when something is hidden is the same disclosure at one bit — its
presence is the signal. This is also the posture MISP already takes elsewhere:
§7.9 records that the sightings count is the viewer's, because
`Sightings_policy` hides whole sightings and the number reflects only what may
be seen.

**Required changes.** These are consequences to be applied by the live phases
that own them, not defects:

| Location | Today | Under §14.6 |
|---|---|---|
| §2.3 fixture contract `:147` | `occurrence_acl_note` → *"Showing 6 of 10 … 4 are hidden by distribution rules"* | key removed |
| §2.3 fixture contract `:165` | verdict `acl_note` → *"4 occurrences you cannot see were excluded"* | key removed |
| §2.6, Overview preview panel footer | `.vp-acl-note` band carrying the truncation note | band removed |
| `01-occurrences.md` §7, tab table footer | `.vp-acl-note` → *"Showing 6 of 10 occurrences. 4 are hidden by …"* | band removed — **applied, phase 22** |
| `01-occurrences.md` §6, facet rail | `.vp-facet-note` explaining that the banner counts 10 while the rail counts 4 | sentence removed — there is no longer a gap to explain — **applied, phase 22** |
| `01-occurrences.md` §8, states | *"everything hidden by ACL"* as a distinct rendered state | collapses into the empty state — **applied, phase 22** |
| §8.7, History footer graft | *"four of the ten occurrences are ACL-hidden"* | graft withdrawn |
| §11 (phase 19) suppressed state | *"All %d occurrences … are on events you cannot see"* | state withdrawn |
| §9.6/§9.7 siblings | `.vp-acl-note` on the aggregated section | removed; the cap notice stays, since a cap is not a permission |
| tab counts and banner type chips | instance-wide | viewer-scoped, so banner and facet rail agree by construction — **applied for the Occurrences tab, phase 22; the banner is still fixture-backed and is the Overview's.** The two *badges* naming converted tabs were corrected on 2026-08-28 — see §14.10 |
| the Sightings tab | — | nothing to remove: §14.6 listed no note on any of its five panels, and the list panel's standing `policy` sentence is already viewer-neutral and always shown. **Phase 23 added** the computed-judgement line above |

**The exception: a permanent line wherever the page renders a computed
judgement.** Always shown, on every value, identical for every reader —
including values with nothing hidden. Because it never varies, its presence
carries no information, which is exactly what separates it from the notes above.
It exists because a *computed judgement* can honestly differ between two
readers of the same value, and without the line neither has any way to know why.

**This was written as "the Verdict tab, and nowhere else." Phase 23 found a
second member.** A decay score is computed from the reports the reader can see,
and `Plugin.Sightings_policy` hides whole reports — measured on `2.2.2.2`, a
site admin reads NIDS 73 and a CIRCL org admin reads 59, same value, same day,
because the report that last reset the clock is on an event CIRCL does not own.
Two colleagues would read different numbers off the same card in the same
afternoon. So `value_sighting_decay` carries one too (`23-sightings.md` §7).

The rule to read out of the exception, now that it has two members: **a panel
that renders a computed judgement gets a permanent caveat; a panel that renders
a count does not.** A count being viewer-scoped is invisible and harmless — that
is the whole of §14.6. A judgement being viewer-scoped is a number people
disagree about out loud. A later phase that computes rather than counts should
expect to add the third.

**What this costs, stated plainly.** §1.3 founded the page on three visually
distinct states — *not implemented*, *nothing to show*, *hidden from you by
ACL* — later grown to five. §14.6 removes the third from every panel except the
Verdict tab's standing caveat: a panel where everything is hidden now renders
identically to a panel where nothing exists. That is a real loss of a founding
principle, taken deliberately in exchange for a page that cannot be used as an
existence oracle. It is recorded here rather than glossed, and it is the
decision to revisit first if the oracle risk is ever judged acceptable.

### 14.7 UI elements — the three-part test

§1.3 requires that every edit outside this feature leave existing callers
rendering byte-identically. §14.7 makes that a test with one answer.

**Small — make the change in place.** It adds a new optional input, guarded so
it is skipped when absent, and every existing caller renders byte-for-byte
identically. The phase names those callers and re-checks them. This is how
`view_layout`'s `badge` key, `headerSection`'s `$headerBreadcrumb` and the
`.ajax-card` fix all landed (§2.7).

**Not small — fork it.** Any change that alters an existing caller's output,
*including improving it*, gets a Value-Profile-owned element under
`app/View/Themed/Overmind/Elements/Values/View/` instead. Phase 16 already took
this route: `Logs/timeline.ctp` groups by calendar day, `H2` needed grouping by
occurrence, and the shared element was left alone with only its action
vocabulary extracted into `AuditActionMeta`.

**A brand-new file in a shared folder — always fine.** It breaks nobody. New
field renderers go to
`genericElementsBS5/IndexTable/Fields/`, as `value_object_context.ctp` already
did.

**A defect in shared code — report it, do not fix it here.** A live phase's
review is about queries; a shared-element fix needs a review covering every
other page that uses it. The standing list from §7.9 —
`multi_select_toolbar.ctp:18`'s `bg-light` bulk bar, `Badges/type.ctp:12`'s
`border border-dark` — stays unfixed and stays recorded, and phase 22 adds one:
`DistributionLevel.php`'s level-1 tint measures **4.09:1**, below AA for text,
and none of its tints follows the theme. Every page in MISP that draws a
distribution badge renders it, which is exactly why it is not fixed here.

One live-data pressure point is already settled and needs no new decision.
Pagination: `00-shared.md` §6 built the page control as real Bootstrap
`pagination` markup operating on rows already in the DOM, and stated that when
these panels go live *"this is where `Paginator` inside an ajax action lands.
The markup is shaped for it now so that change is local."* It lands there.
Wiring real pagination into the shared `index_table` instead would change
existing callers, which the test above makes a fork rather than an edit.

### 14.8 What the fixture becomes

`ValueProfileFixture` is **not** deleted and **not** a runtime fallback. A page
that invents threat intelligence when the database is empty is the opposite of
everything §1.3 set out.

It becomes a **unit-test double**. Its arrays are handed straight to the
elements, so a test renders a panel with no database at all.

What that covers:

- every template still renders against MISP's real array shapes;
- the cross-panel number checks stay runnable — the ledger rows summing to the
  hero's score, the sibling `objects` column reconciling with its header count,
  a tab badge equalling its row count. These are what caught the Verdict tab
  claiming seven opinions its own organisation table contradicted (§7.8, phase
  13 §15).

What it does not cover, and this is stated rather than implied: **query
correctness.** No fixture-backed test proves that `Value::conditionsFor` selects
the right rows, that an aggregate respects the id set it was given, or that a
fetcher was ACL-correct. That verification is manual, per phase, against real
data — and because the four demo values will hold nothing on a real instance,
each live phase states which values it verified against and what it observed.
No seeder is built.

The traps from earlier verification passes still apply and are not re-litigated
here: §6.1's harness must still assert `--vp-mal` resolves before it asserts
any colour, because an unstyled page passes a colour check for the wrong reason.

### 14.9 What every live phase must state

A live phase's write-up carries this, and a phase that cannot fill a row has
found something worth knowing:

1. **Which panels it converts**, by element name.
2. **Per panel: query count, what the count scales with, and its tier** — with
   the written reason where that is tier 2.
3. **Which fetchers it used**, and for any event access, that it is one call
   rather than N.
4. **Which shared elements it touched**, the callers re-verified, and which
   changes were forks instead.
5. **Which of §14.6's required changes it applied.**
6. **What it deferred, with the cost named** — the §9.1 failure was a claim
   about query cost read as a claim about result size, and this row exists to
   make that distinction explicit.
7. **Which values it verified against**, since the demo values no longer supply
   data.
8. **Both themes.**
9. **The three concepts, assessed** — proposals on the value, presence in feeds
   and sync servers, and event reports on the value's events.
   [`../value-profile-coverage.md`](../value-profile-coverage.md) §5 carries a
   starting verdict per phase — and §5.1 the same for Occurrences, which is
   already converted and still owes two of the three. The phase records its own
   verdicts, and **`no` with a reason is a complete answer.** The row exists
   because all three were
   reachable from panels §2.6 already lists and all three were built to about a
   third of their depth — a badge, a card title and a markdown parser were
   enough to make them look handled.

### 14.10 Hazards this contract inherits, and two found writing it

§7.9 and §8.2 are the standing ledger of what MISP cannot supply, and every item
in them still stands. **`value-profile-coverage.md` §7 is the third entry in
that ledger** — four hazards and one correction to §8.2's datable-source
scoreboard, found surveying the three concepts §14.9 row 9 now requires every
phase to assess. One of them is a disclosure risk rather than a gap:
`Feed::searchCaches()` applies no role check, so a panel that renders its output
unfiltered shows ordinary users the feed correlations `perm_view_feed_correlations`
withholds from them everywhere else. §14 adds two of its own, both found while
writing it:

**No shipped warninglist sets `category` explicitly.** `warninglists.category`
is `NOT NULL DEFAULT 'false_positive'`, and across the 71 shipped lists checked,
none sets the key in its `list.json` — so all of them import as
`false_positive`. `Warninglist.php:12-13` defines `CATEGORY_KNOWN` and
`:44` validates against both, so the category exists as a concept. But §2.6's
conflicted layout argues specifically that *"a `known`-category hit means shared
infrastructure rather than benign"*, and there may be no shipped list that
produces one. Whoever wires the warninglist band has to check that before the
argument can be rendered from real data.

**`MispAttribute::fetchAttributes` resolves organisations one query at a
time.** `MispAttribute.php:2363` caches each answer in `$this->orgs_cache` but
fetches each id with its own `Org->find('first')`, so a page whose attributes
span nine organisations pays nine selects inside one fetcher call. Found by
phase 23 counting the queries behind `Sighting::listSightings`, which calls it.
It is the mirror of the note below: a batched form is one `IN (…)`, the fetcher
does not use it, and every attribute index in MISP pays for that.

**`Event::fetchSimpleEvents` exists.** §8.2 costed the per-event ACL model at
one `fetchEvent()` per event and concluded *"there is no value-scoped equivalent
and no cheaper path."* That is true of the full event graph and not of event
metadata: `fetchSimpleEvents($user, $params, $includeOrgc)` (`Event.php:2862`)
is one ACL'd query for N events at `recursive => -1`. The panels that need only
event info, orgc and publication timestamps have a cheap path §8.2 did not
account for.

### 14.11 Out of scope

- **Writes.** [`value-profile-writes.md`](value-profile-writes.md). Under §14
  the page still reads only.
- **Caching and query optimisation.** A later stage, per §14.4. The batching
  rule is the only performance commitment made here.
- **The value table itself.** §14.3 prepares for it; building it is another
  feature's work.
- **A verdict scoring engine.** Still out, as §5 has it. §14 wires the display
  of a verdict; what computes one is not decided by this contract — and because
  nothing computes one, the four rows in §14.12 that render a verdict are
  blocked rather than merely unstarted. See
  [`../value-profile-verdict-engine.md`](../value-profile-verdict-engine.md).
- ~~**The decay aggregation rule.**~~ **Closed by phase 23** — the per-day
  maximum across occurrences, labelled with the occurrence holding it.
  `23-sightings.md` §5. Left listed rather than deleted, because three
  documents in a row restated this gap without closing it and the record of
  that is worth more than a tidy list.

### 14.12 The conversion board

Twenty-seven endpoints, each rendering one element. This is the fine-grained
record of the campaign: a live phase fills in its rows and nothing else claims
to know which elements are still fixture-backed. Tab-level status lives in
`value-profile-page.md` §1.4 — **§1.4 says whether, this table says what.**

`Q` is the query count. `Scales` is what that count grows with. `Tier` is
§14.4's classification, and a tier 2 row carries its reason in the phase
document that filled it.

| Tab | Endpoint | Element | Q | Scales | Tier | Phase |
|---|---|---|---|---|---|---|
| — | `view` | `Values/view.ctp` (full page) | — | — | — | — |
| Overview | `viewOccurrences` | `value_occurrences` | — | — | — | — |
| Overview | `viewContext` | `value_context` | — | — | — | — |
| Overview | `viewAnalystPreview` | `value_analyst_preview` | — | — | — | — |
| Overview | `viewVerdictCard` | `value_verdict_card` | — | — | — | **blocked** |
| Overview | `viewSightings` | `value_sightings` | 13 | organisations, not occurrences | 1, one aggregate at 2 | **23** |
| Overview | `viewLifecycle` | `value_lifecycle` | — | — | — | — |
| Overview | `viewExternal` | `value_external` | — | — | — | — |
| Verdict | `viewVerdict` | `value_verdict` | — | — | — | **blocked** |
| Verdict | `viewVerdict` | `value_verdict_conflicted` | — | — | — | **blocked** |
| Verdict | `viewVerdictAside` | `value_verdict_aside` | — | — | — | **blocked** |
| Occurrences | `viewOccurrenceTable` | `value_occurrence_table` | 9 | nothing — flat in occurrence count | 1, two aggregates at 2 | **22** |
| Sightings | `viewSightingChart` | `value_sighting_chart` | 21 | organisations, not occurrences | 1, three aggregates at 2 | **23** |
| Sightings | `viewSightingList` | `value_sighting_list` | 13 | organisations, not occurrences | 1, one aggregate at 2 | **23** |
| Sightings | `viewSightingDecay` | `value_sighting_decay` | 21 | organisations, not occurrences | 1, three aggregates at 2 | **23** |
| Sightings | `viewSightingReporters` | `value_sighting_reporters` | 13 | organisations, not occurrences | 1, one aggregate at 2 | **23** |
| Sightings | `viewSightingAdd` | `value_sighting_add` | 1 | nothing | 2 | **23** |
| Relationships | `viewRelationCooccurrence` | `value_relation_cooccurrence` | 16 | decorations, not the value's size | 1, four aggregates at 2 | **24** |
| Relationships | `viewRelationNearMatch` | `value_relation_near_match` | 3 | nothing | 1 | **24** |
| Relationships | `viewRelationAsserted` | `value_relation_asserted` | 13 | **rows returned** — one fetch per claim | 1 | **24** |
| Relationships | `viewRelationGraph` | `value_relation_graph` | 37 | all three sections at once | 1, four aggregates at 2 | **24** |
| Relationships | `viewRelationSettings` | `value_relation_settings` | 37 | all three sections at once | 1, four aggregates at 2 | **24** |
| Enrichment | `viewEnrichment` | `value_enrichment` | — | — | — | — |
| Analyst | `viewAnalystStanding` | `value_analyst_standing` | — | — | — | — |
| Analyst | `viewAnalystThread` | `value_analyst_thread` | — | — | — | — |
| Timeline | `viewTimeline` | `value_timeline` | — | — | — | — |
| History | `viewHistory` | `value_history` | — | — | — | — |

Twelve rows are filled; the rest are `—` because nothing else is wired. A row
moves off `—` only when its phase document records the same numbers, so the two
cannot disagree without one of them being visibly blank.

**One of the twelve is on a tab whose phase has not run.** `viewSightings` is
the Overview's sightings card, converted after phase 23 because it is made of
that phase's `sightingContext` and because leaving it meant a card and a tab on
one page that could disagree about the same value. It is filled against **23**,
whose §12.1 records its numbers. The Overview's other rows stay `—`, and
whichever phase converts them inherits one row already done rather than a tab
half-owned — which is the note below about a tab not being indivisible, used in
earnest.

`Q` on every converted row is its **ceiling**, measured, and on every one of
them the ceiling is reached by a *small* value rather than a large one.

For Occurrences: nine, of which two are `SharingGroup::authorizedIds` inside
`buildConditions()` rather than queries the panel issues. Seven on a value with
no tags and no sharing group, four on a value with no occurrence the viewer may
see. What varies is which decorations a value needs, not how much data it has —
the ceiling is reached by a thirteen-row value. `22-occurrences.md` §4.1 has the
breakdown and §10.2 the measurements.

For Sightings: 21 on a 23-occurrence value, 8 on a 33,110-occurrence one. The
two heaviest values on the instance issue *fewer* queries than `8.8.8.8` and are
slower anyway, because two of theirs have to touch every occurrence. **What the
count grows with is the number of organisations** a value's reports and events
involve — `MispAttribute::fetchAttributes` resolves one per query (§14.10) —
and not the occurrence count. `23-sightings.md` §4 has the breakdown and §10.2
the measurements, including the two rewrites §8.2 there records: the first
version was flat in query count and took 3.4 seconds.

The Overview's card is 13, which is the same ceiling as the two Sightings
panels that do no decay work — it shares their `sightingContext` and adds one
in-memory fold. A panel converted by reusing another's context costs what that
context costs, and nothing more.

For Relationships: 16 on `8.8.8.8`, a 23-occurrence value, against 14 on
`443`'s 48,255 — so **the count scales with how many decorations a
neighbourhood needs and not with the value's size**, and the time scales with
one number the panel prints rather than with the data (§4 of
`24-relationships.md`). Two of its rows break a pattern the board had held
until now. `value_relation_asserted` is the first row whose count grows **per
row returned** — 4 queries at zero claims and 13 at six — and the growth is
not this feature's: `Relationship::afterFind` resolves each row's far end with
its own ACL'd fetch whether the caller wants it or not. And the two rail cards
are 37 apiece because each of them needs the whole tab's arithmetic and so runs
all three sections; that is the first place on this page where a repeat is of
something expensive, and it is §14.11's first named customer.

**Four rows are blocked rather than unstarted**, and they are not all on the
Verdict tab: the Overview's `value_verdict_card` shows the disposition and the
top three signals, so it needs the engine as much as the tab does. They cannot
be wired at all until a verdict engine exists — nothing computes a verdict
today, so the fixture there stands in for an algorithm rather than for a query.
Scope is in
[`../value-profile-verdict-engine.md`](../value-profile-verdict-engine.md),
which needs its own PRD and grilling session before a phase can claim these
rows. **A live phase that touches the Overview tab must leave that one card
on the fixture and say so**, rather than treating the tab as indivisible.

Two rows to watch, both named before the campaign starts rather than found
during it. `viewVerdict` renders one of two elements depending on the value's
disposition, so it is one endpoint and two conversions. And `viewHistory` takes
a period in its URL, so its query count is a function of the window as well as
of the value.

**A third thing to watch, and it is a hole in the rule above.**
`value-profile-coverage.md` §6 forecasts what the three concepts of §14.9 row 9
add to this board: one new element for event reports, probably two, and no new
endpoint for the other two concepts — but two of them **extend
`viewOccurrenceTable`, a row phase 22 has already filled.** Surfacing a
standalone proposal (`ShadowAttribute.old_id = 0`) means another fetch, and a
feed column means a Redis pipeline that §14.4's tiers have no vocabulary for, so
both the `Q` ceiling of 9 and the tier table behind it change. The rule as
written — *a row moves off `—` only when its phase document records the same
numbers* — does not say who owns a row that a later phase amends.
`value-profile-coverage.md` §5.1 is the work list and names two options for
placing it. Whoever gets there first: amend the row, and record the new numbers
in your own document with a pointer from phase 22's, so the two still cannot
disagree without one being visibly blank.

### 14.13 The phase index

One row per live phase, filled in as they land. A phase's own document is where
its decisions and deferrals live; this is only the map.

| Phase | Converts | Document | Status |
|---|---|---|---|
| 22 | Occurrences — `value_occurrence_table` and its rail | [`22-occurrences.md`](22-occurrences.md) | built |
| 23 | Sightings — all five panels | [`23-sightings.md`](23-sightings.md) | built |
| 24 | Relationships — all five panels, and the rail's graph | [`24-relationships.md`](24-relationships.md) | built |
| 24B | Relationships — the insight pass over the built tab; converts nothing, re-ranks and adds two evidence reads | [`24b-relationships.md`](24b-relationships.md) | **planned** — its §1 is the task board |
| — | Verdict, and the Overview's verdict card | [`../value-profile-verdict-engine.md`](../value-profile-verdict-engine.md) | **blocked on the verdict engine** |

The order is deliberately not fixed here. §14 does not sequence the campaign,
and the argument for going first differs by tab: Occurrences is the one whose
live shape is best understood, Sightings has the hardest live-data story of the
nine (`value-profile-tabs/02-sightings.md` §11), and Enrichment cannot go live
at all without the persistence §7.9 found missing. Whichever goes first states
why in its own document.

Sightings went second **because of** that hardest-story label rather than in
spite of it: the hard part was one named, undecided question that three
documents in a row had restated without closing, and parking a decision while
easier tabs land is how a campaign accumulates the debt §14.11 lists.
`23-sightings.md` §1.

**What phase 22 leaves behind for the phases after it.** Three files every later
phase inherits — `app/Model/Value.php` (the §14.3 seam),
`app/Model/ValueProfile.php` (the per-panel facade) and
`app/Lib/Tools/ValueStatsTool.php` — plus four findings that change what a later
phase should expect:

- **`fetchAttributes` cannot serve this page.** It forces `deleted = 0` for
  anyone without `perm_sync` and `object_id = 0` without `flatten`, so it drops
  soft-deleted occurrences and every occurrence inside an object.
  `fetchAttributesSimple` has neither behaviour. §14.4's tier-1 table lists both
  without distinguishing them; only one of them works.
- **`fetchAttributesSimple` now takes `order`, `limit` and `page`**, guarded, so
  a later phase does not have to bypass it to paginate.
- **The page control cannot draw a large table**, and could not before this
  phase either: it renders one button per page inline, and past ~20 buttons the
  panel header collapses and overflows horizontally. This is what caps the
  Occurrences table at 300 rows, and it will cap every other paginating panel
  the same way. `22-occurrences.md` §12.1.
- **§14.10's `fetchSimpleEvents` note is now a measurement**: 56 events, one
  query, 1 ms.
- **A distribution is a chain, not a column.** `Attribute.distribution` is level
  5 — *inherit* — for 3,777,682 of the 3,778,094 attributes on the verification
  instance, so any panel reporting that column reports nothing.
  `ValueStatsTool::effectiveDistribution()` resolves the attribute-object-event
  chain the way `buildConditions()` enforces it, and every panel that shows a
  distribution should use it. `22-occurrences.md` §13.1.
- **The row-narrowing JS now supports named date ranges** —
  `tr[data-vp-times]` plus `[data-vp-range-from|-to]` — alongside the single
  unnamed period it already had. A panel that cuts on more than one date does not
  need to invent it again. §13.2.
- **And client-side column sorting.** `headers.ctp` takes a guarded
  `client_sort` key rendering a heading button, rows carry
  `data-vp-sort-<column>` tokens built to sort lexicographically, and the list
  keeps `vp-sorted-col`/`vp-sorted-dir`. Three states, the third restoring the
  order the model sent from a `vp-sort-default` token — reordering moves rows, so
  the default has to be carried rather than recomputed. §14.2.
- **`value_pager` takes an optional `sizes` list**, so a panel can let the reader
  choose its page size. Every size offered must leave a header that renders,
  which is the same constraint that bounds the cap. §14.1.
- **Phase 20's brush primitive has two more callers, and they cost no canvas.**
  A 32px strip of `.vp-spark` bars under `[data-vp-timebrush]` is enough to pick
  a range and see its shape; `window.VP.brush` needed no change to take it. A
  panel wanting a range picker does not need Chart.js and does not need
  History's 64px. §15.1.
- **`ValueProfileBuckets` buckets an instant well and an interval not at all.**
  §12.3 declined it for the seen-density sparkline, whose input is a set of
  intervals; §15.2 uses it for the two time strips, whose input is a `Y-m-d`
  count map. `series()` plus `locate()` is the pair, and the unit rule belongs
  to the caller. §15.2.
- **`DistributionLevel`'s level-1 tint is 4.09:1**, below AA for text, and its
  tints do not follow the theme. Pre-existing, shared by every page in MISP that
  draws a distribution, and on the §14.7 report-do-not-fix list.

**What phase 23 leaves behind.** One new file every later phase inherits —
`app/Lib/Tools/ValueDecayTool.php` — plus two accessors on `Value`, six methods
on `ValueStatsTool`, and five findings that change what a later phase should
expect:

- **`Value` has two more accessors, and one of them is the shape to copy.**
  `occurrenceSummaryFor($user, $value)` returns the occurrence, event and
  organisation counts plus the oldest and newest occurrence dates in **one
  grouped aggregate**. Any panel that wants a number about the whole occurrence
  set should call it rather than fetch rows: fetching them to count three
  numbers cost 617 ms on `443`, and it is 4 ms. `occurrenceIdsFor` is the light
  six-column id set, and it now takes `limit` and `order` because its callers
  cap it.
- **Do not scope another model's fetcher by the value's whole occurrence set.**
  `Sighting::listSightings` re-resolves every id it is handed; on `443` that is
  48,255 ids for three sightings and 1.6–3.4 seconds per panel.
  `Value::sightedOccurrenceIdsFor` narrows first with a join. The same trap is
  waiting for any panel that wants notes, correlations or audit rows over a
  value's occurrences, and none of them will look like a query-count problem —
  the counts were flat and correct throughout. `23-sightings.md` §8.2.
- **`Sighting::listSightings` cannot see a report on a soft-deleted
  occurrence**, because its internal `fetchAttributes` forces
  `Attribute.deleted = 0`. Measured: six seeded reports on `github.com`, four
  visible. Phase 22 found the neighbouring half of this. The two together mean
  **any fetcher going through `fetchAttributes` disagrees with the Occurrences
  tab about which rows exist.**
- **`fetchAllAllowedModels($user, true, [], ['enabled' => true])` is the cheap
  way to ask which decaying models apply.** `getAssociatedModels($user, $type)`
  — the route `attachScoresToAttribute` takes — asks per attribute type and
  re-reads every default model each time: thirteen queries where this is two.
- **`ValueProfileBuckets::columnLabels()` moved out of the fixture.** A live
  element must not name `ValueProfileFixture`, and that is now a grep in the
  phase lint pass. Any element a later phase converts should be checked for the
  same thing.
- **A curve is not verifiable by assertion.** Phase 23 drew both decay
  models with a flat plateau at full base score for months at a time, and
  it survived 34 content assertions, 34 payload assertions and 45 clean
  renders — every number was internally consistent, in range, and equal
  to the rail at the last point. What was wrong was the *shape*.
  `23-sightings.md` §8.3. Any later phase that draws a line should plan
  to look at it, and `23-sightings.md` §10.6 describes the harness that
  makes that a ten-minute job without a session.
- **The chart payload's bulk is bucket labels, not data.** At the 1,095-day span
  cap a Sightings fragment carries ~43 KB of JSON, of which roughly 26 KB is the
  day grain's 1,095 labels and titles — all derivable in the browser from
  `plan.from` plus an offset. Any later panel reusing phase 21's `plan` shape
  inherits that. `23-sightings.md` §12.1. §13.3.
  **Fixed on 2026-08-28** for the day grain, in `ValueProfileBuckets::plan`, so
  a later panel inherits the fix rather than the problem: the fragment went
  52.0 KB → 27.6 KB. Week and month still ship their strings, and should.
- **Converting a tab makes its own tab badge lie, and the badge is not the
  tab's to fix.** The page frame — every badge, the fact strip, the banner
  chips — is one `ValueProfileFixture` call in `ValuesController::view()` and
  belongs to the Overview's phase. That is invisible while every tab is
  fixture-backed, because both halves agree; the first conversion makes a
  number in the tab bar contradict the panel two inches below it. It went
  unnoticed through two phases: on `8.8.8.8` the badges read 9 and 17 against
  23 occurrences and 53 reports.

  Corrected on 2026-08-28, and the shape of the correction is the part worth
  reusing. **Occurrences takes a real number**, from the same
  `occurrenceCountFor` call its own header counts with — one `COUNT`, 12 ms
  typical and 146 ms on `443`, the heaviest value on the instance, which is
  what a badge on every page load costs there. **Sightings takes no number**,
  the key removed rather than zeroed: a sighting count is the viewer's, so
  getting it means running the sighting policy over fetched rows — the panel's
  own thirteen queries, paid on every page load for a tab most readers never
  open. The Timeline and History tabs already carry no badge for that reason.

  **Revisit when `Sighting` can count under the policy in SQL** rather than in
  PHP over rows. The Overview's phase needs the same number for the fact
  strip's sightings line, so it is one piece of work and not two.
  `ValueProfile::forTabCounts` is where both decisions live.

  **Whoever converts a tab next: check its badge.** Relationships, Enrichment
  and Analyst are each carrying a fixture literal that will start lying the day
  their panels stop doing so.

**What phase 24 leaves behind.** One new file every later phase inherits —
`app/Lib/Tools/ValueRelationTool.php` — plus six accessors on `Value`, one new
public method on `Correlation`, and eight findings that change what a later
phase should expect:

- **The correlation engine has nothing to say about a value.** A
  `default_correlations` row links two attributes carrying the *same* value, so
  for one value the engine returns other occurrences of it — which is the
  Occurrences tab — plus its CIDR and ssdeep partners. It never returns a third
  value. Any later panel that expects the correlation table to describe what a
  value is *related to* will find it describes where the value already is.
  `24-relationships.md` §3.
- **Choosing which rows to read is cheaper than reading the wrong ones.** The
  pattern this phase leaves is: one grouped aggregate to enumerate the candidate
  scope, one index-only `COUNT` to size it, then read what survives a stated
  budget. Measured on `8.8.8.8`: 61 ms to size 19 events, against the 4.77
  seconds the sizing then avoided. Any panel whose scope is *"everything in the
  events this value touches"* needs it — an event on the verification instance
  holds 843,976 attributes. §4.
- **A tier-2 aggregate may legitimately apply no ACL.** §14.4's tier table does
  not have vocabulary for this and should: the event-size query is unscoped
  because its answer never reaches the page — it only decides which events the
  ACL'd fetch then reads. The rule the case suggests is *an unscoped aggregate is
  tier 2 when its result is a decision rather than a datum*.
- **A `find` on `Relationship` is one ACL'd fetch per row**, and a fatal outside
  a web request. `Relationship::afterFind` resolves every row's far end through
  `getRelatedElement`, using `Configure::read('CurrentUserId')` — set by
  `AppController::beforeFilter` and nothing else, so a console or worker caller
  passes `null` to a typed `array` parameter. Measured: 6 claims cost 13 queries,
  0 claims cost 4. §9.2.
- **`AnalystData::rearrangeOrganisation` nests its result and re-queries when
  the association is absent.** A contained `Orgc` is moved to
  `$row['<Alias>']['Orgc']` and the top-level key unset, so the obvious read
  finds nothing and every row silently reports *Unknown organisation*; and when
  `Org`/`Orgc` are *not* contained it issues one `Organisation` find per row for
  each. Contain both. §9.3.
- **`over_correlating_values.occurrence` is zero on every row.** 1,622 rows on
  the verification instance. It is filled by a separate router job, and it is
  instance-wide anyway — so §14.6 forbids printing it even when it is populated.
  §9.1.
- **`fuzzy_correlate_ssdeep` can be empty while the extension is loaded.** 1,387
  `ssdeep` attributes, zero index rows — a dozen seeded ones later put 879 rows
  in it and left the 1,387 untouched. A panel narrowing candidates through
  MISP's own index would report *no match* for *no index*. §9.4, §28.7.
- **A shipped JS library is not the documented one.** The pivotick build in
  `app/webroot/js` predates its current API in four ways that all fail silently
  — an unread flat `style`, ignored style callbacks, a shape name that throws
  inside the renderer, and an empty-string label that falls back to the data.
  Read the bundle, not the docs, and verify in a browser. §10.2.

  Two of those are the library behaving correctly and the caller having to know,
  and both generalise: **a container at `display: none` is 0×0**, so anything
  that sizes its viewport from the element must be constructed after the reveal;
  and **`loadAjaxContainer` re-creates every script the fragment brings without
  copying its `type`**, so a `<script type="application/json">` data block is
  appended to `<head>` as executable JavaScript and throws. Put the data in the
  script that reads it. §10.3.
