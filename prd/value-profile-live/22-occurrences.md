# PRD: Value Profile — Occurrences goes live

**Phase 22. The first live phase.** Converts one endpoint —
`viewOccurrenceTable`, rendering `value_occurrence_table` and the
`value_occurrence_facets` rail inside it — from `ValueProfileFixture` to the
database.

Inherits [`00-contract.md`](00-contract.md) (§14) in full. Implements the tab
specified in [`../value-profile-tabs/01-occurrences.md`](../value-profile-tabs/01-occurrences.md)
(§9), whose §10 named what live data would hit; this is that.

---

## 1. Why Occurrences goes first

§14.13 left the order open and asked whichever tab went first to argue for
itself. Three reasons, in the order they matter.

**It is the tab whose live shape is best understood.** §14.13 already said so.
Its data is `attributes` rows and the events that own them — the one thing on
this page MISP has a mature, years-old ACL fetcher for. Nothing about it is
waiting on a decision: contrast Sightings, whose decay aggregation rule is
still undecided (§14.5), Enrichment, which has no persistence to read from
(§7.9), and the four verdict panels, which have no engine (§14.12).

**It is the tab that forces the two files the whole campaign needs.** Every
other live phase will call `Value` for its occurrence set — Sightings needs the
attribute ids to ask `Sighting` about, History needs the event ids, Relationships
needs the occurrence UUIDs. Building the value-identity seam (§14.3) against the
panel that is *only* the occurrence set means the seam is exercised by the
simplest possible caller rather than discovered underneath a complicated one.

**It is the tab that proves the §14.1 claim.** The contract asserts that
everywhere except `profileFor()`'s whole-profile shape, *"a panel going live is a
data source swapped under an unchanged template."* That is a claim about fifty-one
elements made while none of them had ever seen a database row. If it is wrong,
the cheapest place to find out is the panel with the most template and the least
computation. It largely held; §12 records the four places it did not.

**What it does not touch.** The Overview's `value_occurrences` preview renders
the same rows in a shorter table and stays on the fixture. It is a different
endpoint, it belongs to the Overview's phase, and the Overview cannot go live as
a tab anyway until the other tabs it previews do — plus its `value_verdict_card`
is one of §14.12's four blocked rows. Phase 22 is one row of the board.

---

## 2. What ships

| Layer | File | New? |
|---|---|---|
| Identity seam | `app/Model/Value.php` | new |
| Per-panel facade | `app/Model/ValueProfile.php` | new |
| Aggregation | `app/Lib/Tools/ValueStatsTool.php` | new |
| Endpoint | `ValuesController::viewOccurrenceTable` | rewired |
| Fetcher | `MispAttribute::fetchAttributesSimple` | extended, §7 |
| Templates | `value_occurrence_table.ctp`, `value_occurrence_facets.ctp` | §14.6 changes, §8; review changes, §13, §14 |
| Interactions | `app/webroot/js/value-profile.js` | extended, §13.2 and §14.2 |
| Shared header | `IndexTable/headers.ctp` | one guarded key, §14.2 |
| Pager | `value_pager.ctp` | one optional slot, §14.1 |
| Styles | `app/webroot/css/value-profile.css` | the sort affordance, §14.2 |

`ValueProfileFixture` is untouched. Every other endpoint still calls
`profileFor()` and still renders fixture data; §14.8's unit-test-double future
for it is unchanged and unstarted.

---

## 3. The seam — `app/Model/Value.php`

`useTable = false`, per §14.2. It reaches `attributes` through
`ClassRegistry::init('MispAttribute')`, whose `$alias` is `Attribute`, so every
row it returns is keyed the way the templates already read.

Three public methods, which is fewer than §14.3 sketched:

```php
Value::conditionsFor($value, array $options = [])
Value::occurrenceCountFor(array $user, $value, array $options = [])
Value::occurrencesFor(array $user, $value, array $options = [])
```

`conditionsFor` is §14.3's condition fragment, unchanged in intent:

```php
['OR' => ['Attribute.value1' => $value, 'Attribute.value2' => $value]]
```

`$options['types']` is accepted and narrows to `Attribute.type IN (…)`. It is
the parameter §14.3 asked for so that a per-type value table can be selected
later without revisiting call sites; phase 22 has no caller that passes it, and
that is the point — it costs a parameter nobody uses now and saves a sweep
later.

**`occurrencePartsFor` and `uuidFor` are not built.** §14.3 lists them, and both
have their first caller elsewhere: the part indicator renders in §2.3's banner
`value2_note`, which is the Overview's phase, and `uuidFor` is a writes concern
(`value-profile-writes.md`), and writes are out of scope under §14. Building
them now would mean two accessors with no caller to hold them honest. The rule
§14.3 actually states — *`Value` is the only file that names `value1` or
`value2`* — is unaffected: a later phase adds its accessor to this file, which
is what the rule is for.

**Why `Value` owns the row fetch rather than `ValueProfile`.** §14.2's table
gives `Value` "its ACL'd occurrence set" and forbids `ValueProfile` from issuing
"its own SQL against attribute value storage". Fetching the rows *is* a query
against attribute value storage, so it lives here. `ValueProfile` decorates what
it gets back.

---

## 4. The facade — `app/Model/ValueProfile.php`

`useTable = false`. One public method for the one panel this phase converts:

```php
ValueProfile::forOccurrenceTable(array $user, $value, array $options = [])
```

It returns exactly the four keys `value_occurrence_table.ctp` reads —
`value`, `occurrences`, `occurrence_stats`, `occurrence_facets` — plus
`occurrence_cap`, which §6 explains. `profileFor()` is not called and the
whole-profile array is not built, which is §14.1's one structural change,
arriving with the first panel that needs it.

### 4.1 The queries — at most nine, and none of them per-occurrence

Per §14.9 row 2. **The count is constant in the number of occurrences** —
it does not grow with the value, which is the one performance rule §14.4
commits to.

| # | What | How | Tier |
|---|---|---|---|
| 1 | the viewer's occurrence total | `find('count')` under `buildConditions($user)` + `conditionsFor($value)` | 2 |
| 2 | the occurrence rows, capped | `fetchAttributesSimple`, `contain` Event, Object, SharingGroup, AttributeTag | 1 |
| 3 | the rows' attribute tags | the `AttributeTag` half of query 2's `contain` (Cake runs a hasMany separately) | 1 |
| 4 | the tag records behind them | `MispAttribute::attachTagsToAttributes` | 1 |
| 5 | `Event.Orgc` for the N events | `Event::fetchSimpleEvents($user, …, true)` — **one** call for all N | 1 |
| 6 | pending proposals per row | `ShadowAttribute` grouped count over the row ids | 2 |
| 7 | sharing group names, only where a row resolves to level 4 | `SharingGroup::fetchAllAuthorised($user, 'name')` | 1 |

Seven of ours, and **two more that belong to MISP's ACL builder**:
`buildConditions()` calls `SharingGroup::authorizedIds($user)`, which is two
selects over `sharing_group_servers` and `sharing_group_orgs`. They are absent
for a site admin, whose conditions are empty, and `MispAttribute`'s own
`aclConditionsCache` means they run once per request rather than once per
`buildConditions()` call. Counted rather than glossed, because §14.9 row 2 asks
for the panel's query count and not for the count of queries this phase wrote.

Measured (§10.2): **9 at the ceiling**, 7 on a value with no tags and no
sharing group, and **4 for a value with no occurrence the viewer may see** —
nothing is decorated when there are no rows to decorate, so the floor is the two
ACL queries plus the count and the row fetch. Queries 4 to 7 each skip
themselves when their input is empty: no tags means no tag-record query, no
level-4 row means no sharing-group query.

**Query 1's tier-2 reason.** The answer is a single number. Materialising rows
to count them is precisely the trap §7.9 named — and worse here, because the
count's whole job is to be the total that the capped row set is *not*. It cannot
be derived from the rows by construction.

**Query 6's tier-2 reason.** The answer is a count per row, and a proposal row
carries `value`, `comment`, `type` and `category` this panel never renders.
`ShadowAttribute::buildConditions()` (`ShadowAttribute.php:757`) mirrors the
attribute visibility model — a proposal is visible to whoever can see the
attribute it proposes against — so counting over an id set that came out of
query 2 is already correct without re-applying it. That is exactly the guarantee
§14.4 tier 2 exists to give: permissions were settled before the aggregate ran.

**Where the id set comes from.** §14.4 says a tier-2 aggregate "receives the id
set from `Value::occurrenceIdsFor($user, …)`". Query 6 receives it from query
2's rows instead, which are the same ACL'd set one step further along, and
resolving the ids twice would let the two drift. `occurrenceIdsFor` is therefore
not built either; a phase whose aggregate has no accompanying row fetch will
need it.

**No call is per-event or per-occurrence.** §14.4's batching rule is the one
this panel could most easily have broken: the naive shape is one
`Event::fetchEvent` per distinct event to get an organisation name. Query 5 is
one `fetchSimpleEvents` for all of them, which is the cheap path §14.10 found
that §8.2 had missed.

### 4.2 Why the event fetch is a second query and not a nested `contain`

`contain => ['Event' => ['Orgc']]` would have folded query 5 into query 2 at
zero cost, since both are `belongsTo` and become joins. It was not taken, for
one reason that matters: `fetchSimpleEvents` applies `Event::createEventConditions($user)`,
so the event's own ACL is checked by the model that owns events, independently
of `MispAttribute::buildConditions`. The two should always agree. If they ever
disagree, an occurrence whose event this viewer may not open drops out of the
table — the stricter answer wins, and it wins silently in the safe direction.
§14.2's table asks each domain model to enforce "their own ACL, as they do for
every other page"; one extra indexed primary-key lookup is what that costs.

---

## 5. The aggregation — `app/Lib/Tools/ValueStatsTool.php`

Pure and static, `ValueProfileBuckets`'s shape, which §14.5 names as preferred.
**It takes no `$user` and issues no SQL** — it computes over rows it is handed,
so it cannot leak what the viewer may not see, and that is checkable by reading
its signature rather than by tracing its callers.

```php
ValueStatsTool::facetToken($text)                     the slug rule
ValueStatsTool::occurrenceStats(array $rows, $total)  the header's numbers
ValueStatsTool::occurrenceFacets(array $rows, $total) the rail
ValueStatsTool::effectiveDistribution($row, $sgNames) who can see a row (§13.1)
```

**`facetToken` is the reason this tool exists at all.** The rail's counts and
the table's row tokens have to agree on a string. Today `value_occurrence_table.ctp`
derives the row's token with an inline `preg_replace` and the fixture wrote the
matching slug down by hand — two implementations of one rule, which is the
drift §14.8 says no fixture-backed test would notice. Both now call
`ValueStatsTool::facetToken()`; the template's closure delegates rather than
duplicating. `App::uses(…, 'Tools')` from a Values element is established here
already — `value_history.ctp` does it for `AuditActionMeta`, `value_timeline.ctp`
for `ValueProfileBuckets`.

**No tier-2 `GROUP BY`, which §10 of the tab brief expected.** Every facet is
tallied in PHP over the rows the panel was given. That is honest here and only
here, for the reason §6 sets out: the rows the panel is given are the whole
viewer-scoped set, unless the cap says otherwise on the page.

### 5.1 What the eight groups are counted from

`value_occurrence_facets.ctp` owns the order, heading and glyph — phase 9 §13
moved them out of the fixture and they stay out. The tool supplies only counts
and the domain values behind them.

| Group | Counted from | Token |
|---|---|---|
| organisation | `Event.Orgc.id` | the org id |
| type | `Attribute.type` | `facetToken(type)` |
| category | `Attribute.category` | `facetToken(category)` |
| ids | `Attribute.to_ids` | `set` / `unset` |
| distribution | `Attribute.distribution` | the level, as a string |
| sharing_group | `SharingGroup.id`, only where distribution is 4 | the sharing group id |
| tag | non-galaxy `AttributeTag.Tag.name` | `facetToken(name)` |
| state | `proposal_count` | `proposal` |

Every group is sorted by count descending, then by label, so a redraw of the
same data cannot reorder the rail. `value_facet_group.ctp` already folds past
ten, offers a search box past fifty and dims a zero — none of that needed
touching, which is phase 8's groundwork doing its job at the first live caller.

**Two decisions inside the tag group.** A tag attached locally on one occurrence
and globally on another is one facet, because the row token is the tag's name
and carries no local/global distinction; its `local` flag is set only when
*every* attachment is local, so the chip never claims a global tag is local.
And `attachTagsToAttributes` is called with `includeAllTags`, so a
`Tag.exportable = 0` tag still appears — this page is an inspection view, not an
export, and a tag the reader can see on the event page should not vanish here.

### 5.2 The seen sparkline

Forty buckets across the span of the occurrences that carry `first_seen` or
`last_seen`, each counting the occurrences whose interval covers it — the
fixture's semantic, kept. A single-point occurrence (a `first_seen` with no
`last_seen`) covers the one bucket it falls in.

`ValueProfileBuckets` was **not** reused, and §14.5 lists it among the things to
reuse rather than rebuild, so the departure is worth stating. It buckets a span
into calendar units keyed `Y-m-d`; forty equal slices of an arbitrary span are
not a calendar unit, and its `tally()`/`sparse()` take a day-keyed count map
rather than a set of intervals. Forcing the sparkline through it would mean
converting intervals to days first, which is the expensive part and the part it
does not do.

**When no occurrence carries either date**, `seen_spark` is empty and
`seen_from`/`seen_to` are null. The rail then renders the *"N occurrences carry
no first/last seen at all"* line on its own, without a sparkline of zeroes and
without two date inputs pre-filled from nothing. §8 covers the template change
that required.

---

## 6. The cap, and the one promise this phase renegotiates

**Decided: the panel fetches at most `ValueProfile::OCCURRENCE_CAP` rows,
ordered `Attribute.timestamp DESC`, and says so on the page when the cap bites.**

**The cap is 300**, raised from the 100 this section first argued for once §14.2
made the page size the reader's choice — the number below is the reasoning, and
§14.1 records why it moved.

Two regimes, one code path:

| | Rows fetched | The rail counts | The page says |
|---|---|---|---|
| total ≤ cap | all of them | the whole viewer-scoped set | nothing extra |
| total > cap | the most recent `cap` | those rows | a footer band naming both numbers |

**Why a cap at all.** On the instance this was verified against, `443` resolves
to 48,255 occurrences and `0.0.0.0` to 33,109 — `443` because it is the port
half of forty-eight thousand `ip-dst|port` attributes, which is exactly the
composite identity §14.3 says a value has. Fetching those with their events,
objects, sharing groups and tags, and rendering them into one fragment, is not a
slow page but a page that does not arrive: measured before the cap was settled,
1,000 rows of `443` rendered a **5.9 MB** fragment. §14.4 tier 3 forbids a panel
"whose query count grows per occurrence without a stated cap"; the query count
here does not grow at all, but the *result size* does, and §14.9 row 6 exists
precisely because §9.1 once read a claim about query cost as a claim about result
size. The cap is on the result: at 100 rows the same value renders 556 KB, at
300 it renders 1.85 MB.

**Why not more.** Because of what the panel can draw. The page control renders
one button per page inline, so the button count is rows ÷ page size and it shares
the panel header with the subtitle. Measured at a 1500px viewport by trimming the
rendered control one button at a time:

| Buttons | Rows | The subtitle |
|---|---|---|
| 6 | 40 | one line, 41px |
| 12 | 100 | two lines, 62px — readable |
| 15 | 130 | three lines, 83px |
| 20 | 180 | squeezed to a 16px column, 878px tall |
| 25 | 230 | destroyed, and the panel overflows horizontally by 96px |
| 102 | 1,000 | destroyed, 2,573px of overflow |

**This ceiling is the page control's, not the query's** — see §12.1 for the
defect and who owns it. A cap this phase chooses should not routinely put the
reader in a regime the panel cannot render, which is the whole argument for the
number: at the default page size of 60, a 300-row cap is five pages and seven
buttons, and the smallest size the picker offers keeps it inside the measured
band. §14.2 records why 25 rows per page is not on that list.

**What the cap costs, stated plainly.** `01-occurrences.md` §8 promised that the
rail's counts *"come from the whole set, not the page — which is a promise the
live implementation has to keep (§10)"*. In the capped regime this phase does not
keep it: the rail describes the most recent 100 occurrences, not all 48,255. It
is not kept silently — the footer band names both numbers — but a band is not
the same thing as a count. A second, smaller cost: at this cap no value on the
verification instance reaches the fifty distinct values past which
`value_facet_group` offers a search box, so that state is now reachable only
from the fixture. `193.161.193.99` carries 77 distinct tags across its 327
occurrences and 44 within the capped 100.

**What would keep it, and why that is not this phase.** §10 named the fix: a
`GROUP BY` under the same conditions the fetcher builds, which would let the rail
count all 48,255 while the table shows 100. That is eight tier-2 aggregates,
or one aggregate over the facet dimensions' cross-product, and it wants to land
with server-side pagination rather than before it — because the moment the rail's
counts cover rows the table does not hold, the facet checkboxes are filtering
client-side against a set they no longer describe. §14.7 already says real
`Paginator` inside the ajax action is where pagination lands. Server-side rows,
server-side facet filtering and `GROUP BY` counts are one change, and this phase
deliberately does not start it halfway.

**The band is a cap notice, not an ACL note.** It reuses `.vp-acl-note`, whose
CSS comment already describes it as *"a statement about the query, not a row"*,
with `fa-layer-group` rather than `fa-eye-slash` and wording that names a limit
rather than a permission. §14.6 keeps cap notices for exactly this reason — *"the
cap notice stays, since a cap is not a permission"*. Reusing that chrome also
means the band inherits its measured contrast unchanged: 13.01:1 light and
8.84:1 dark, the same figures `01-occurrences.md` §11 recorded for the ACL band
it replaces.

It says *"the 300 most recent of 48255 occurrences you can see"* — both numbers,
because a ratio is not something a reader can act on, and `you can see` because
under §14.6 the total is the viewer's own and not the instance's. Plain
integers, no thousands separators, matching every other count on the page.

---

## 7. The shared-code change — `fetchAttributesSimple`

§14.7's three-part test, applied: **small, so the change is made in place.**

`fetchAttributesSimple` passes `conditions`, `fields` and `contain` through and
hardcodes `'order' => false`. The capped fetch needs an order and a limit.
Added: optional `order`, `limit` and `page`, each guarded by `isset`, with
`order` still defaulting to `false`. A caller that passes none reaches the same
query it reaches today.

**The six existing callers, named and re-checked**, none of which passes any of
the three keys:

| Caller | Passes |
|---|---|
| `Sighting.php:160` | conditions, fields |
| `Sighting.php:841` | conditions, fields |
| `Sighting.php:1270` | conditions, fields |
| `MispObject.php:1415` | conditions, fields |
| `AttributesController.php:1143` | conditions, contain |
| `AuditLogsController.php:604` | conditions, fields |

**Why not fork it.** §14.7 says a change that alters an existing caller's output
gets a Value-Profile-owned element or method instead. This one cannot: the guard
is `isset($options['order'])`, and no caller sets it. **Why not bypass it** and
build the query in `Value` with `buildConditions($user)` directly, as
`Sighting::listSightings` does at `Sighting.php:229`? Because §14.4 tier 1 asks
for MISP's existing ACL fetchers *and nothing else*, and a fetcher that cannot
express `LIMIT` is a fetcher this campaign will keep wanting to bypass. Fixing
it once is cheaper than every live phase reasoning about `buildConditions` on its
own.

---

## 8. §14.6's required changes, applied

Per §14.9 row 5. Four of the nine rows in §14.6's table belong to this panel;
all four are applied here. The other five belong to panels this phase does not
convert and are untouched.

| §14.6 row | Applied |
|---|---|
| `01-occurrences.md` §7, tab table footer `.vp-acl-note` | **removed.** The block reading `occurrence_acl_note` is gone from `value_occurrence_table.ctp`; the facade never supplies the key |
| `01-occurrences.md` §6, facet rail `.vp-facet-note` | **removed.** The banner-versus-rail sentence is gone from `value_occurrence_facets.ctp`, and `banner_note` with it — there is no longer a gap to explain |
| `01-occurrences.md` §8, "everything hidden by ACL" state | **collapsed.** The state has no fixture key and no template branch left; a value whose every occurrence is hidden now renders the same empty state as a value with none |
| tab counts and banner type chips, instance-wide → viewer-scoped | **applied for this panel.** Every number in the header, the rail and the pager comes from `buildConditions($user)`. The banner is `Values/view.ctp` and still fixture-backed, so its chips are the Overview phase's to scope |

The founding principle §14.6 spends: `01-occurrences.md` §8's fifth state is
gone. A reader can no longer tell, on this tab, whether a value has nothing or
has everything hidden from them. §14.6 records why that trade was taken — the
page would otherwise answer "does this indicator exist on your instance" for any
value anyone types — and records itself as the decision to revisit first. Phase
22 only applies it.

**Two further template changes, neither from §14.6:**

- The sparkline and its date inputs are wrapped in a guard, per §5.2, so a value
  whose occurrences carry no seen dates renders the explanatory line alone.
- The row-token slug delegates to `ValueStatsTool::facetToken()`, per §5.

Both are edits to Value-Profile-owned elements with one caller each, so §14.7's
test does not apply — there is nobody else to render byte-identically.

---

## 9. What this phase does not touch

- **Every other endpoint.** Twenty-six of the twenty-seven rows on §14.12's
  board are still `—`.
- **The Overview's `value_occurrences` preview**, which renders the same rows
  from the fixture. §1.
- **Writes.** Every control in the bulk bar is still disabled with its title,
  and `value-profile-writes.md` is still unstarted.
- **Caching.** §14.4 defers it and the batching rule is the whole performance
  commitment. Nothing here caches anything.
- **The value table.** §14.3's seam is built; the table is another feature's.

---

## 10. Verification — what was run

Against the Docker stack serving this worktree: **3,778,094 attributes across
4,175 events, 88 organisations, 11 sharing groups, 22 shadow attributes and
993,144 soft-deleted rows.** §14.9 row 7 asks which values, because the four
demo values hold nothing on a real instance and no seeder is built (§14.8).

### 10.1 The values, and why each one

None of them is a demo value. Each was found by querying for the shape it
proves.

| Value | Occurrences | What it is for |
|---|---|---|
| `2.2.2.2` | 13 | the populated case: 2 types, 2 categories, 5 organisations, a distribution-4 row with a real sharing group, 2 rows inside objects, **2 genuine pending proposals**, a real tag |
| `1.1.1.1` | 11 | a composite matched on `value1` — one row is an `ip-dst\|port`; and the case where **no occurrence carries either seen date** |
| `443` | 48,255 | matched on **`value2`** — the port half of tens of thousands of `ip-dst\|port` and `hostname\|port` rows. The seam's OR branch, and the cap |
| `0.0.0.0` | 33,109 | the cap again, and the heaviest count query on the instance |
| `github.com` | 21 | one **soft-deleted** occurrence among twenty current ones |
| `193.161.193.99` | 327 | the long-tailed facet: 77 distinct tags over the value, 44 within the cap |
| `no-such-value-at-all` | 0 | the empty state |

`2.2.2.2` was additionally rendered as three different viewers, which is what
§14.6 needed proving: a site admin and org 1 both see **13**, org 9 sees **10**,
and no page says a word about the three it is not showing.

### 10.2 Query counts and timings, measured

Read off the datasource log, per §14.9 row 2. Wall time is the facade call, not
the render.

One process per value, so every row is a cold ACL-conditions cache and includes
its two `authorizedIds` queries.

| Value | Occurrences | Queries | Wall | Cap |
|---|---|---|---|---|
| `no-such-value-at-all` | 0 | 4 | 22 ms | — |
| `jane.doe@hotel.com` | 2 | 7 | 22 ms | — |
| `github.com` | 21 | 7 | 33 ms | — |
| `2.2.2.2` | 13 | **9** | 32 ms | — |
| `193.161.193.99` | 335 | 8 | 24 ms | 100 of 335 |
| `443` | 48,255 | 8 | 191 ms | 100 of 48255 |
| `0.0.0.0` | 33,109 | 7 | 167 ms | 100 of 33109 |

The count is flat while the occurrence count moves by four orders of magnitude —
and it is `2.2.2.2`, with thirteen occurrences, that hits the ceiling, because
what varies is which decorations a value needs rather than how much data it has.

Per-query, on `443` (measured before the cap was lowered to 100, so the row
fetch is the 1,000-row one; the two heavy queries scale with the value, not the
cap):

```
369 ms  COUNT(*)                       the whole 48,255-row set
388 ms  the capped rows                 ORDER BY timestamp DESC LIMIT 100
 35 ms  attribute_tags for those rows
  4 ms  the tag records
  1 ms  fetchSimpleEvents — 56 events in ONE call
  2 ms  the grouped proposal count
```

**That 1 ms is §14.4's batching rule earning its place.** The naive shape is one
`Event::fetchEvent` per distinct event to get an organisation name; §8.2 costed
exactly that and concluded there was no cheaper path. Fifty-six events, one
query, one millisecond.

The two heavy queries are the two that must touch every row the value has, and
both are single statements. `attributes.value1` and `value2` are 255-character
prefix indexes over `text` columns (§14.3), and MySQL merges the two branches of
the `OR`; 369 ms to count 48,255 of 3.8 million rows is that working, not
failing.

### 10.3 What was asserted

**`php -l` clean** over all seven changed and new files.

**§14.3's grep returns nothing.** It found one hit on the first run — a docblock
in `value_sighting_add.ctp` naming the two columns while describing
`Sighting::saveSightings`. Reworded to state the identity rule instead of the
storage, which is what the rule is for and which stays true after the value
table lands. The rule as written is a plain grep, so a comment counts.

**All six `fetchAttributesSimple` callers are on the old path.** Verified by
grep: none of `Sighting.php:160/841/1270`, `MispObject.php:1415`,
`AttributesController.php:1143` or `AuditLogsController.php:604` passes `order`,
`limit` or `page`, and the new code is reached only through `isset`.

**69 structural assertions** over seven fragments rendered through the real View
against real data. The rail at `col-lg-3` before the table at `col-lg-9`; the
seven element-driven facet groups in design order plus the two bespoke ones;
thirteen `<th>` of which nine are shown and `Columns (9 of 12)` agrees;
`Showing 13 of 13 occurrences · 13 events · 5 organisations`; two
pending-proposal badges against a rail that says 2; one struck-through row and
one `Include 1 soft-deleted` switch shipped checked; forty spark buckets of
which thirty-nine are empty on a value seen at a single instant; and on the
unknown value exactly one empty state, no rail, no bulk bar, no band.

**Negative assertions, which are what §14.6 needs.** No `.vp-facet-note`, no
`.vp-acl-note` outside the capped regime, and the strings *"hidden by"*,
*"cannot see"* and *"you cannot"* appear nowhere in any fragment, for any
viewer.

**Also asserted: the coherence checks §14.8 keeps runnable.** For every value,
each of the five partitioning facet groups sums exactly to the row count, and
**every token the rail offers matches at least one row it was counted from** —
405 distinct row tokens on the pre-cap `443`, 15 on `2.2.2.2`. This is the check
that would have caught the slug rule drifting between the tool and the template,
which is the whole reason `facetToken` has one home (§5).

**91 interaction assertions driven in a real browser**, headless Chrome against
the shipped CSS and JS with the fragments served over HTTP, in both themes. The
§6.1 trap first: `--attribute` is asserted to resolve before any colour is
measured, because an unstyled page passes a colour check for the wrong reason.
Then, for **every facet the rail offers**, ticking it must leave exactly the
number of rows the rail claimed — not a sampled few, all of them. Alternatives
within a key union (every type ticked returns the whole set); keys conjoin
across (organisation + type equals the rows carrying both tokens, computed from
the DOM rather than hardcoded); `Clear all` restores and goes inert; hiding Tags
drops the ratio to 8 of 12 and takes the `<th>` with it; unticking the reveal
drops 21 to 20 and reticking restores it; selecting two rows reveals the bulk bar
with `2 rows · N events · M organisations` and **every one of its ten controls
disabled** — by one `<fieldset disabled>` carrying the reason, which is why the
check is `:disabled` and not the IDL property.

**Both themes, measured not eyeballed.** The cap band reads 13.01:1 light and
8.84:1 dark. The soft-deleted row keeps `line-through` and 0.6 opacity in both —
on its cells, not its row, so its checkbox stays undimmed and selectable,
because history is a legitimate thing to export.

**No JavaScript errors** on any fragment in either theme.

### 10.4 What the browser showed that assertions did not

Screenshots of the populated value in both themes, and of the capped value.
Three things worth recording because no assertion was watching for them:

- MISP's organisation renderer draws a real logo where an organisation has one
  and `.vp-occ-orgdot` falls back to a lettered disc where it does not — on real
  organisations, mixed down the same column, which is what phase 9 §13 changed
  the disc to do and had only fixture orgs to show it with.
- Distribution level **5** renders as `Inherited` through MISP's own badge.
  Almost every attribute on this instance is level 5, a level no demo value
  carries, and nothing in the fixture campaign had exercised it.
- The Context column distinguishes `Standalone attribute` from a real object
  occurrence (`network-connection` / `ip-dst`). Those rows exist only because
  the fetcher choice in §3 kept them — `fetchAttributes` would have dropped every
  one.

### 10.5 The fixture still renders

Both fixture-backed occurrence fragments were rendered through the same path
after the §14.6 edits: `185.234.219.24` at 79 KB and `45.155.205.233` at
3.97 MB. Neither errors, so the templates still read the fixture's shapes and
§14.8's unit-test-double plan is not foreclosed by this phase. The second
number is also the yardstick §12 needs.

---

## 11. Exit criterion

**Met.** `viewOccurrenceTable` renders real attribute rows for a real value in
both themes: the counted rail on the left, the nine-column table beside it, the
bulk bar above the rows and every control in it disabled. The rail filters the
rows, and every count it offers is exactly the number of rows ticking it leaves.
A value with no occurrence the viewer may see renders one honest empty state at
full width. The same value shows one viewer thirteen rows and another ten, and
says nothing to either about the difference. §14.3's grep returns nothing
outside `Value.php`.

**One thing the exit criterion does not cover**, and it is named rather than
implied: the panel was verified through the real View and the real CSS and JS,
but not over an authenticated HTTP request — the verification stack's admin
credentials are unset, so no session could be established. The ajax path itself
is unchanged by this phase (same action, same `renderPanel`, same element) and
`viewOccurrenceTable`'s ACL entry at `ACLComponent.php:1065` is untouched, so
what is unverified is the transport and not the panel. Worth one `curl` from a
logged-in browser before this is called finished.

---

## 12. Where this differs from the contract, and what it found

Nothing here changes what §14 is. These are the points where following it
literally would have contradicted something it inherits, plus the two things
going live turned up.

### 12.1 The page control cannot draw a large table — and already couldn't

**The finding, and the phase's most significant one.** `value_pager.ctp` renders
one button per page inline, and `renderPager` in `value-profile.js` redraws it
the same way. Past about twenty buttons the panel header's subtitle is squeezed
to a 16px column and the whole panel overflows horizontally. §6 has the
measurements.

**It predates this phase.** The shipped demo value `45.155.205.233` renders 748
occurrence rows and 77 buttons from the fixture, with the subtitle collapsed to
zero width and 1,781px of horizontal overflow, today, with no code from this
phase. The first capped implementation here used 1,000 rows and reproduced it at
102 buttons; the cap was then lowered to 100 so that going live does not make a
pre-existing defect the common case on real values.

**Not fixed here, deliberately.** The fix is to window the control — first and
last plus a window around the current page — which touches `value_pager.ctp` and
the JS that redraws it, both of which serve the Sightings and History tabs as
well. §14.7's posture on a defect in shared code is to report it and not fix it
in a phase whose review is about queries, and that applies with more force to a
phase that is the first of its campaign. It is the change that has to land before
`OCCURRENCE_CAP` can usefully rise, and it belongs with the server-side
`Paginator` work §14.7 already names.

### 12.2 Two of §14.3's accessors were not built

§14.3 names `occurrencePartsFor` and `uuidFor` alongside `conditionsFor`. Both
have their first caller in another phase — the part indicator renders in the
Overview's banner, and `uuidFor` is a writes concern — so building them now
would mean two accessors with nothing to hold them honest. §3 argues it; the rule
§14.3 actually states is unaffected, and the grep proves it.

Likewise `occurrenceIdsFor`, which §14.4 names as where a tier-2 aggregate gets
its id set. The one tier-2 aggregate here takes its ids from the row fetch
instead — the same ACL'd set one step further along — because resolving the value
twice would let the two drift. §4.1 records it.

### 12.3 `ValueProfileBuckets` was not reused for the sparkline

§14.5 lists it among the things to reuse rather than rebuild. It buckets a span
into calendar units keyed `Y-m-d` and tallies a day-keyed count map; forty equal
slices of an arbitrary span are not a calendar unit, and the input here is a set
of intervals rather than a day map. §5.2 argues it. The forty-bucket tally lives
in `ValueStatsTool` instead, which is where §14.5 puts histograms anyway.

### 12.4 A shared fetcher was extended rather than left alone

§14.4 tier 1 asks for MISP's existing ACL fetchers *and nothing else*, and the
one this panel needs could not express `LIMIT`. §7 records the three guarded
options added to `fetchAttributesSimple` and the six callers re-checked. The
alternative — building the query in `Value` with `buildConditions($user)`, as
`Sighting.php:229` does — would have left every later live phase reasoning about
ACL conditions on its own.

### 12.5 §14.10 gains one hazard, and one is retired

**New: `fetchAttributes` cannot serve this page.** It forces
`Attribute.deleted = 0` for anyone without `perm_sync`, so the soft-deleted
occurrences this tab reveals by default would be unreachable for every ordinary
user; and it forces `Attribute.object_id = 0` unless `flatten`, dropping every
occurrence inside an object — the rows the Context column exists to describe.
`fetchAttributesSimple` has neither behaviour. Any later live phase reaching for
the better-known fetcher needs to know this: §14.4's tier-1 table lists both
without distinguishing them, and only one of them works here.

**Retired: §14.10's `fetchSimpleEvents` note is now measured rather than
predicted.** 56 events, one query, 1 ms. §10.2.

### 12.6 The rail's distribution facet was useless, and review caught it

Recorded here because the *first* implementation of this phase shipped it and
§13.1 is what replaced it. The rail counted `Attribute.distribution` — the
column — which on the verification instance is level 5 for 3,777,682 of
3,778,094 attributes. The facet therefore read `Inherited: 12 / Sharing group: 1`
on the phase's own primary verification value, and every row in the Distribution
column drew the same purple fork glyph.

Nothing was wrong with the wiring; the number was faithfully what the column
said. It is a reminder that a live phase's job includes noticing when a
fixture-shaped design stops meaning anything against real data — the fixture's
demo values spread themselves across levels 0, 1, 3 and 4, so this looked
informative for fourteen phases and could not have looked otherwise until the
database was behind it.

### 12.7 One number in §14.1 was wrong in the reassuring direction

§14.1 estimated the live cost of the whole-profile shape at *"nine tabs of
queries per panel request, twenty-odd panel requests per tab visit"*. The first
converted panel needs at most eight queries for itself, so the multiplier is
real but the base was a guess. Worth restating with a measurement now that there
is one, because the argument for a per-panel facade did not need the number to
be large and should not rest on one nobody checked.

---

## 13. After review: the effective distribution, and a time filter

Two changes asked for after the phase first shipped. Both are the same kind of
finding — a control that was defensible against fixture data and says nothing
against a database.

### 13.1 Distribution is the whole chain, not the attribute's column

**The comment.** *"There're multiple distribution: the attribute itself, its
potential object and its event. These combined can dramatically change the
'final' distribution. I'd reflect the final distribution instead of what's
defined on the attribute (which will be 99% of the time `inherited`)."*

It is 99.99%: on the verification instance **3,777,682 of 3,778,094 attributes
are at level 5**. §12.6 records what the rail looked like because of it.

**The rule, and where it comes from.** MISP has no packaged helper for this, so
the rule is stated once in `ValueStatsTool::effectiveDistribution()` and derived
from the authority that already exists — `MispAttribute::buildConditions()`,
which decides whether a row is visible at all by requiring the **event** to
allow the viewer *and* the attribute *and* the object, with level 5 passing
through. Effective distribution is that same conjunction, reported instead of
enforced.

Two steps:

1. **Resolve inheritance.** A link at level 5 states nothing and defers outward.
   An event can never be 5, so at least one link always states a level.
2. **The tightest stated level wins**, by an explicit order: `0, 4, 1, 2, 3`.

**Where the order is honest, and where it is not.** `0` is one organisation and
is strictly tightest. `1`–`3` widen by community. `4` is a *named list* of
organisations, which is why it sits second — but it is **not truly comparable**
with `1`–`3`, because a sharing group can carry an `all_orgs` server entry and so
be wider than "this community only". A chain mixing the two therefore describes
an intersection that no single level expresses: *these organisations, and only
within that community*.

Rather than flatten that silently, the resolution returns `intersects`, and the
cell draws a `fa-link` marker whose title says *"Both apply, so the real audience
is narrower than any one of them"*. It is set only where the ambiguity is real: a
level 0 anywhere dominates every other constraint, and the same sharing group
stated twice is one constraint, so neither sets it.

**What the reader now gets.** On `2.2.2.2`, the facet went from
`Inherited: 12 / Sharing group: 1` to:

| Level | Rows |
|---|---|
| This community only | 6 |
| Your organisation only | 3 |
| Sharing group | 2 |
| Connected communities | 1 |
| All communities | 1 |

The Distribution column draws five different badges where it drew one, and the
sharing-group facet names **two** groups where it named one — the second is an
*event*-level group, which an attribute-only reading could never have surfaced.

**Three implementation notes.**

- **The chain is in the cell's `title`**: `Attribute: Inherited → Event: This
  community only`. A level nobody set on the attribute is otherwise untraceable
  to whoever did set it, and this column is not editable here.
- **A custom cell, not the shared renderer.** The shared `distribution` field
  renderer could have been pointed at a computed path, but it has no slot for the
  chain or the intersection marker. §14.7's test makes that a compose-rather-
  than-edit: the cell is Value-Profile-owned and calls MISP's own badge element
  inside.
- **A row with no object must not be read as an object at level 0.** The `Object`
  key is always present after a `contain`, holding nulls where the LEFT JOIN
  found nothing, so the object link counts only when it has an id. This is the
  one place the resolution could have silently made every standalone attribute
  organisation-only.

**Cost: one query, conditionally.** Every level needed is already on the row.
Sharing-group *names* are not, and level 4 can now be reached through the event
or the object, so names come from `SharingGroup::fetchAllAuthorised($user,
'name')` — which returns only the groups this viewer is authorised for, so a
group name cannot be read off a row whose event the reader merely owns. A
level-4 row whose group does not resolve keeps its badge and loses only the name.
The query runs only when some row resolves to 4.

### 13.2 A time filter on the attribute's timestamp and the event's publication

**The comment.** *"Add a time filter in the filters panel. We should be able to
filter on timestamp (of the attribute?) and published timestamp (of the event of
course)."*

Both, as two independent ranges under a new **Time** group, and both wired.
`Attribute last modified` is `attributes.timestamp`; `Event published` is
`events.publish_timestamp`. They are different questions and neither is
answerable from the other — an occurrence edited yesterday on an event published
last year is not the reverse.

**The shared JS gained a named range, and nothing else changed.** There was
already an unnamed period (`data-vp-filter-from|-to` against `tr[data-vp-time]`)
with exactly one caller, `value_history`'s audit rail, and a named *numeric*
threshold (`data-vp-filter-min` against `tr[data-vp-num]`) with three. Neither
supports two independent date ranges. §14.7's three-part test says a change that
would alter an existing caller gets forked; so rather than generalise the
period, this adds a parallel concept beside it:

```
tr[data-vp-times]         `key:YmdHi` pairs
[data-vp-range-from|-to]  bounds against one of those keys, named by value
```

`activeRanges()` and `rowMatchesRanges()` are new; `refreshList` gains one `&&`
that returns true when no range is set; the active-filter count, `Clear all` and
both delegated handlers gain the two new selectors. The digit-parsing logic is
extracted from `periodBound()` into `boundDigits()` so both share it — including
its reason, which is worth keeping: bounds are compared as the digits of the
**printed wall clock**, not epochs, because a row's time is rendered server-side
and comparing epochs would hand a reader in another timezone a different set of
rows than the times on those rows say the window holds.

**Three honest states the ranges need.**

- **A row with no date under a key being cut on is dropped, not kept.** "I do
  not know when this happened" is not evidence that it happened inside the
  window.
- **How many that is sits beside the control**: *"7 occurrences sit on events
  that were never published, and a date cut here removes them."* Same rule
  `seen_unset` follows.
- **A key no row carries gets no control at all.** On `jane.doe@hotel.com` no
  event is published, so the publication range is replaced by *"None of these
  occurrences sits on a published event."* — rather than two live-looking inputs
  over a column that is empty for every row.

**The inputs start empty** and carry the span as `min`/`max`, with the span
printed underneath. A control pre-filled with the whole span looks like a filter
already applied, and "no bound" must not render identically to "the widest
bound".

**First/last seen stays disabled, and now says why.** It sits directly below two
working ranges, so *"not wired in this pass"* would read as an oversight. It is a
different question: `timestamp` and `publish_timestamp` are instants and cutting
them is a point-in-range test, while first/last seen is an **interval** and the
question a reader asks of it — *was this live during my window* — is an overlap
test, which the range filter does not do. The title now says that. Wiring it is
one more matcher and is not in this phase.

### 13.3 What was verified, and one finding

**274 assertions across five harnesses**, all passing:

| Harness | Assertions | What |
|---|---|---|
| structural | 121 | nine server-rendered fragments |
| browser | 123 | interactions and contrast, both themes |
| rule | 13 | `effectiveDistribution()` against hand-written chains |
| History regression | 10 | the unnamed period still cuts |
| relations regression | 7 | the numeric threshold still cuts |

**The resolution rule is unit-tested, not only observed.** The instance has no
intersecting chain — a survey of every non-inherit attribute found only `3/3`,
`0/2`, `1/2` and one `4/4` with matching groups — so the thirteen rule cases are
hand-written against the pure function rather than hunted for in data, and no
demo row was authored to host a state. They cover both intersecting shapes (a
sharing group against a community level, and two different sharing groups), the
level-0-dominates case, and the object link winning.

Two real values carry the cases that *do* exist and both were rendered:
`jane.doe@hotel.com`, where the attribute's own level 0 beats its event's level 2
— the conjunction actually biting — and `2.2.2.2`, whose thirteen rows resolve
across five levels and two sharing groups.

**The time ranges are checked against the rows' own stamps**, read out of the
DOM, so an assertion cannot agree with a wrong implementation: a lower bound
leaves exactly the rows at or after it, a single-day window leaves exactly the
rows inside it, the two keys conjoin, each bound counts as one active filter, and
`Clear all` empties the date inputs as well as the checkboxes.

**Both existing consumers of the shared JS were driven, not reasoned about.**
History's period filter still cuts and restores, and phase 22's named ranges have
not leaked into that panel; the relations' threshold still narrows and restores.

**One finding, in shared code, reported and not fixed.** `DistributionLevel`'s
tints are hardcoded hex and do not follow the theme, so a badge reads identically
in light and dark — measured once:

| Level | Contrast |
|---|---|
| Sharing group | 12.71:1 |
| Inherited | 9.50:1 |
| All communities | 8.06:1 |
| Your organisation only | 7.08:1 |
| Connected communities | 6.72:1 |
| **This community only** | **4.09:1** |

Level 1 misses WCAG AA's 4.5:1 for text. It is **pre-existing** — the shipped
fixture fragments already draw that badge with those colours — and this phase
only makes it the level most rows land on. §14.7's posture on a defect in shared
code applies: `DistributionLevel` is rendered by every page in MISP that shows a
distribution, so darkening `#b45309` needs a review this phase is not. Added to
the standing list beside `multi_select_toolbar.ctp:18` and `Badges/type.ctp:12`.

In the table the badge is glyph-only, where AA asks 3:1 of a graphical object, so
the shortfall is the rail's label alone. The harness asserts the 3:1 floor and
that level 1 is the only value below 4.5:1, so a future change to those tints
cannot pass unnoticed.

---

## 14. After review: sortable columns, and a page the reader sizes

Two more changes asked for after §13. Both are about the table rather than the
data, and between them they move the cap.

### 14.1 Rows per page: 10 → 60, and the cap 100 → 300

**The comment.** *"Instead of showing 10 entries in the table, could we go for
more?"*

**60, because that is MISP's own.** `AttributesController::$paginate['limit']`
and `EventsController`'s are both 60, so a reader arriving from an attribute
index now finds the same number of rows rather than a sixth of them. Ten was
never argued for — it was the fixture's six-row demo value never needing a second
page.

**And the reader can change it**: a `Per page` picker beside the page control,
offering **60 / 150 / 300**, default 60. `value_pager` takes an optional `sizes`
list; its other two callers pass none and render exactly as before. Changing it
repages what is already on screen and returns to page one, because page four of
sixty-row pages is not page four of a hundred and fifty.

**This is what let the cap rise.** §6 set `OCCURRENCE_CAP` at 100 for one
reason: the page control draws one button per page, and 100 rows at 10 per page
was ten pages, which was the most the panel header could carry. A bigger page is
fewer buttons, so the same measured band now allows three times the rows —
**300**, which at the default is five pages and seven buttons. The cap moved
because its binding constraint moved, not because the constraint was wrong.

**The new bound is fragment weight, which is the more honest place for it.** A
row costs about 5.7 KB of markup, so 300 rows of `443` is 1.85 MB against the
5.9 MB that 1,000 rows produced when this phase started. Raising it further is
now a question about how much HTML one ajax fragment should carry.

**Why 25 is not offered**, though it was at first: 300 rows at 25 is twelve pages
and fourteen buttons, and measured with the picker also in the header that
squeezes the subtitle to a 156px column across four lines. Every size the picker
offers has to leave a header that renders, which is the same rule §6 applied to
the cap. The harness measures the subtitle at all three.

**One thing the bigger cap gives back.** §6 recorded that at 100 rows no value on
the verification instance reached the fifty distinct values past which
`value_facet_group` turns a group into a search box, so that state was reachable
only from the fixture. At 300 it is reachable again: `443` draws 93 distinct tag
facets and `193.161.193.99` draws 73.

### 14.2 Sortable columns

**The comment.** *"Also allowing ordering on the column would be nice."*

All twelve columns, clicking the heading, three states: ascending, descending,
then back to the order the model sent.

**Three states and not two.** MISP's paginated headings toggle asc/desc and stop
there. This table's default order is itself meaningful — most recently modified
first — and `Attribute.timestamp` is not one of the twelve columns, so without a
third click the reader could sort once and never get the default back.

**`client_sort`, not `sort`.** `headers.ctp` already has a `sort` key, and it
builds a `$paginator->sort()` link carrying `?sort=&direction=` and reloads the
page — which this panel cannot use, because it pages client-side over rows it
already holds and the request would arrive with no idea what the fragment was
showing. Added beside it: `client_sort` renders the same affordance as a button
naming its column, reusing MISP's own `sortable-header` and `sort-icon` classes
so a sortable heading here looks like a sortable heading anywhere. Guarded and
new, so none of `headers.ctp`'s many callers across MISP reaches it — §14.7's
three-part test, and the same shape phase 9 used to add `header_class` to this
very element.

**What gets compared, and why it is not the cell.** Cell text cannot do this.
Three columns render a glyph and no words at all — IDS, Distribution, State — an
event id sorts as `10, 9` when compared as text, and a distribution's audience
has an order (`0, 4, 1, 2, 3`) that neither its label nor its level number
expresses. So each row carries one token per column in `data-vp-sort-<column>`,
built server-side to sort lexicographically: zero-padded numbers, `YmdHi` dates,
lowercased text. The script needs one comparison and no per-column knowledge.

Distribution sorts by the rank `ValueStatsTool` resolved the chain with, which is
why `effectiveDistribution()` now returns it — the order stays decided in one
place. Sorting that column tightest-audience-first is visible in the browser as
the red *Your organisation only* badges rising to the top of a column that has no
text in it at all.

**An empty token sorts last in both directions.** It means the row has no value
for that column, and "no last-seen date" is not earlier than every date; putting
it at the top of an ascending sort would bury the rows the reader asked to see.

**Reordering is destructive, so the default order is carried.** Sorting moves the
rows in the DOM, so clearing the sort cannot simply stop comparing — the order is
already gone. Each row therefore carries `data-vp-sort-default`, its position in
the model's order, and "unsorted" is a sort by that. This was a real bug in the
first cut of the change, found by the assertion that a third click restores the
first ordering.

**`aria-sort` on the sorted heading is the only marker.** It is what a screen
reader announces, and the caret styling keys off it, so the visual and the
announced state cannot disagree. The heading is a real `<button>`, so it is
reachable and operable from the keyboard, and carries a focus ring — but none of
a button's chrome, because a heading has to keep looking like a heading.

**One naming bug worth recording**, because it is the kind that would recur: the
sort state was first written as `list.dataset.vpSortCol`, which puts
`data-vp-sort-col` on the list element — where a
`querySelector('[data-vp-sort-col="org"]')` finds the container before the button
it was looking for. The state attributes are `vp-sorted-col` / `vp-sorted-dir`;
the controls keep `vp-sort-col`. A state attribute must not share a name with the
control it describes.

### 14.3 What was verified

**419 assertions across six harnesses**, all passing:

| Harness | Assertions | What |
|---|---|---|
| structural | 228 | nine live fragments |
| browser | 156 | interactions and contrast, both themes |
| rule | 14 | `effectiveDistribution()`, including the rank order |
| shared-code regression | 35 | the additions are inert in four other panels |
| History regression | 10 | the unnamed period still cuts |
| relations regression | 7 | the numeric threshold and its select still work |

**Sorting is checked against the rows' own tokens**, read out of the DOM, so an
assertion cannot agree with a wrong implementation. Ascending is ordered,
descending is its reverse, a third click restores the original sequence exactly,
the same rows are still present after each, only one column is ever marked, a
glyph-only column orders correctly, blanks stay last in both directions, and a
facet still narrows a table that is sorted.

**The page size is driven, not assumed**: each offered size shows that many rows,
produces the expected button count, moves the range line, returns to page one,
and leaves a subtitle wider than 200px with no horizontal overflow — measured at
all three sizes.

**The shared additions are shown inert rather than argued to be.** Four other
fixture-backed panels that render through `headers.ctp`, `value_pager` or the
sort path — History, co-occurrence, near-matches and the Overview's occurrence
preview — were rendered and asserted to carry no sort buttons, no per-page
picker, no sort tokens and no named ranges, while keeping their own period,
threshold, select and pager. For `headers.ctp`, which every index in MISP
renders, the guard is asserted over the source: exactly one caller sets
`client_sort`, and Paginator's own branch is untouched.
