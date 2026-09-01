# PRD: Value Profile — Relationships goes live

**Phase 24**, the third live phase. Converts all five panels of the
Relationships tab from `ValueProfileFixture` to the database.
Depends on [`00-contract.md`](00-contract.md) §14, and on
[`22-occurrences.md`](22-occurrences.md) and
[`23-sightings.md`](23-sightings.md), whose seam, facade and tools it
extends. The tab's fixture-era design is
[`03-relationships.md`](../value-profile-tabs/03-relationships.md).

---

## 1. Why Relationships went third

§14.13 declines to sequence the campaign and asks whichever phase goes
next to argue for itself. The argument is that this tab is the one whose
**brief rests on an assumption about MISP that live data refutes**, and
an assumption like that gets more expensive the longer it stands: three
of the tab's five panels, the wording of a sixth on the Overview, and
one line in §14.12's own board all describe section one as correlation
output. It is not, it cannot be, and §3 is why.

Everything else about the tab argued for going now rather than later.
`03-relationships.md` §12 lists seven blockers and six of them are
statements about MISP's schema that a wiring phase can only confirm or
correct — which is what a wiring phase is for. The tab needed no new
decision of the kind phase 23 had to close first. And its rail carried
the one *disabled* control on the page whose reason for being disabled
was a missing feed rather than a missing write path (§10), so it was the
one place where converting a tab could turn a stub into a feature.

---

## 2. What ships

Five panels, five endpoints, no new ones:

| Element | Source |
|---|---|
| `value_relation_cooccurrence` | the value's events, and the objects it sits in |
| `value_relation_near_match` | the CIDR list, and `ssdeep_fuzzy_compare()` |
| `value_relation_asserted` | `relationships`, both directions |
| `value_relation_graph` | all three of the above, as nodes and edges |
| `value_relation_settings` | `MISP.correlation_limit`, `over_correlating_values`, `correlation_exclusions` |

One new file every later phase inherits — `app/Lib/Tools/ValueRelationTool.php`
— plus six accessors on `Value`, one new public method on `Correlation`,
and the rail's graph, which is no longer a drawing.

The tab keeps its nine-tab registry position, its five element names, its
five URLs, its five honest states and its disabled write controls. What
changed inside those constraints is the data source, the words that
described the wrong source, and three `.vp-acl-note` bands that §14.6
does not allow.

---

## 3. The finding: the correlation engine has nothing to say about a value

### 3.1 What a correlation row actually is

`Correlation::correlateValue($value)` collects every attribute carrying
`$value` and writes one row per **pair of them in different events**
(`Correlation.php:367`). `createCorrelationEntry($value, $a, $b)` puts
`$a` on the `1_` side, `$b` on the bare side, and `$value` — the value
both of them carry — into `value_id`.

So a `default_correlations` row means: *these two attributes carry the
same value.* For one value, the engine therefore returns **other
occurrences of that same value**, which is the Occurrences tab, and
nothing else — except one case. `__addAdvancedCorrelations` pairs our
attribute with a *different* value's attribute: the containing CIDR
block, or an ssdeep partner past the threshold. Those are the two
engines of section **two**.

The engine never returns a third value. There is no row in that table,
for any value, that says *these two different values keep turning up
together*.

### 3.2 What that leaves for section one

`03-relationships.md` §6 asks for **"Values that appear in the same
events"**, with columns `Value · Type · Shared events · Organisations ·
Last together · Distribution · Tags`, and a sub-line reading
`18 distinct values across 7 events · correlation engine ·
Machine-derived`. The columns and the sub-line disagree, and the columns
are the ones that describe something real: a table of *other* values,
which the engine cannot produce.

So section one is an **event join** — the other attributes in the events
this value occurs in, folded by value. Its provenance word changes from
`correlation engine` to `shared events`; `Machine-derived` stays, because
it still is. §12 of the tab brief called object siblings *"the only
co-occurrence that is structural rather than statistical"*; the honest
reading now is that both halves of section one are joins over attributes
the page can already see, one tight (same object) and one loose (same
event), and neither is engine output.

**This is a departure from the brief, taken deliberately.** The
alternative was a table of the value's own occurrences under a heading
that says *Values that appear in the same events*, which is not a
narrower claim than the brief's — it is a false one.

### 3.3 What it leaves for the settings card

Everything the brief gives that card is still true, and it is now the
**only** panel on the tab that reads the correlation engine's state:
`MISP.correlation_limit`, whether this value is in
`over_correlating_values`, whether it matches `correlation_exclusions`,
and `MISP.ssdeep_correlation_threshold`.

It also gains a reason to exist that the brief did not have. An
over-correlating value is one MISP will not link to its own other
occurrences **anywhere else in the interface** — the event view's related
events, the attribute's related attributes, all of it comes back empty.
None of that is visible from this tab's three sections, which do not read
the table. So the card is where a reader learns why the rest of MISP has
gone quiet about a value, and it says so in those words when the value is
past the limit.

`.vp-suppressed` moves with it. It no longer means *past the correlation
limit* — nothing on the tab depends on that any more — it means **every
event this value appears in is too large to read**, which is §4.2, and it
is still the *"too many to show"* claim rather than the *"nothing here"*
one that an empty state would make.

---

## 4. The scan, and the three bounds that make it affordable

An event join over a value's events is not free, and on this instance it
is not even close. Measured on `8.8.8.8` — 23 occurrences over 19 events
— the unbounded aggregate took **4.77 seconds and returned 41,513
distinct neighbours**, because one of those 19 events holds 369,822
attributes. The largest event on the instance holds 843,976.

### 4.1 Read the events before choosing them

`Value::occurrenceEventsFor` returns the value's events newest-first as
one grouped aggregate, and `ValueProfile::eventSizes` then asks how big
each of them is — one `COUNT(*) … GROUP BY event_id`, index-only on
`attributes.event_id`. **61 ms over `8.8.8.8`'s 19 events**, against the
4.77 seconds it then avoids.

That query is the whole design. Choosing which events to read is cheap;
reading the wrong ones is not.

`RELATION_EVENT_CAP` is 200 and bounds the candidate list before the size
query runs — `443` sits in 1,844 events, and asking about all of them
cost **519 ms** against **44 ms** for the most recent 200. Nothing below
this line depends on how many were discarded here, because the budget
discards far more.

### 4.2 An event too large to have a neighbourhood

`RELATION_EVENT_SIZE_CAP` is 10,000, and it is an editorial line before
it is a performance one. **In an event holding 369,822 attributes, every
value co-occurs with every other**, so a neighbour list drawn from it
describes the event and says nothing about this value. Dropping that one
event took `8.8.8.8` from 4.77 s / 41,513 neighbours to **0.19 s / 10,029
neighbours**.

The panel states it in words rather than applying it quietly: *"1 event
was left out for holding more than 10,000 attributes, where co-occurrence
describes the event rather than the value."*

**When every candidate event is oversized, the section is suppressed
rather than empty**, and the rest of the tab still renders. Verified on
`1.0.155.105`, whose only event is the 369,822-attribute one: section one
shows the band, and the sibling sub-section under it lists 11 rows from
that same event — because an object does not get larger because the event
around it did, and the band says so.

### 4.3 The budget, and what the panel says it did

`RELATION_SCAN_BUDGET` is 20,000 attribute rows. Events are taken
newest-first until the next one would exceed it. This is the one number
that bounds the section's cost on any instance whatever shape its events
are, and the panel prints it along with what it actually read:

> Read from **18 of this value's 19 events**, newest first, within a
> budget of 20,000 attribute rows — 10,168 rows read. 1 event was left
> out for holding more than 10,000 attributes, where co-occurrence
> describes the event rather than the value.

Every count in the panel is exact over that scope and over no other,
which is the distinction §14.4 draws between folding a complete set and
tallying a page. The facet bar's own sentence was rewritten to say so:
*"Facet counts are exact at every count — they are folded from all 10,168
rows read, not from the page. A count larger than the table can show
means the value it names is outside the 100 carried."*

**A narrower reader can read more events.** On `8.8.8.8` a site admin
reads 18 events and 10,168 rows; a CIRCL org admin reads **11 of 11** and
211 rows, because the ACL removes most of the attributes in the events
that survived and the budget then reaches further. Both numbers are the
reader's own, both are printed, and §14.6 is satisfied by construction —
neither says anything about the other's rows.

---

## 5. The queries

### 5.1 Co-occurrence — nine of ours, plus MISP's own

Tier 1 except where stated, and none of them is per-event or
per-occurrence:

1. the value's events, newest first, capped — `Value`, **tier 2**
2. how big each of those events is — `MispAttribute`, **tier 2**
3. the neighbour rows inside the budget — `Value`
4. their attribute tags (the `hasMany` half of 3's contain)
5. the tag records behind those — `MispAttribute`
6. the objects this value sits in, capped — `Value`, **tier 2**
7. the sibling rows inside those objects — `Value`
8. creator organisation names — `Organisation`
9. event metadata and event tags for the event roll-up — `Event`, `EventTag`

Plus one `SharingGroup::authorizedIds` inside `buildConditions` for any
non-site-admin, one `SharingGroup::fetchAllAuthorised` when some
neighbour resolves to distribution 4, and one more tier-2 aggregate
(`objectFootprint`) **only when the sibling object cap actually bit** —
below it the answer is a number already in hand, and asking the database
for it would be the mistake `occurrenceSummaryFor` was written to stop.

**The written reasons for the four tier-2 aggregates.** 1, 2 and 6 all
return one number per group and exist to decide what is worth reading;
materialising rows to derive them is the cost the section is built to
avoid. 2 in particular applies no ACL and needs none — its answer never
reaches the page, it only decides which events the ACL'd fetch then
reads.

**Rows and not an aggregate for 3 and 7, and that is the cheaper answer
here.** The panel needs six things per neighbour — shared events, last
seen together, which organisations, which events, which object template,
what audience — and five are multi-valued per neighbour. As aggregates
that is one `GROUP BY` per column over the same rows; `GROUP_CONCAT`
would fold them into one query but appears nowhere else in MISP and is
not portable. One scan and a PHP fold gives all six, and it is only
affordable because §4 bounded the scope first.

### 5.2 Near-match — one to three

One query always: the value's types, so the engines can be asked about a
type rather than about a string. Then, only when the CIDR engine applies,
`Correlation::getCidrList()` (Redis, or one query on a cold cache) and
one fetch for whichever blocks contained us — capped, one row per block,
because a `/8` is an attribute in dozens of events and the panel names a
containment rather than a report.

The ssdeep engine adds one fetch when the value is itself an `ssdeep`
hash. Neither engine reads the correlation table; there is nothing in it
to read (§3.1, and §12 of the tab brief).

### 5.3 Asserted — four, plus about one per claim

> **§19.7 adds at most two more, for the section rather than per claim:**
> one `GalaxyCluster` fetch when a claim names one, and one
> `Organisation::find('list')` when an `Event` or cluster target arrives
> holding an id and no name. Both skipped when nothing needs them —
> `8.8.8.8` goes 13 → 15 and `deadnxuyla.ru` stays at 8.

Two of ours: the value's occurrence UUIDs, capped at 300, and one
`Relationship::find` over both endpoint columns with
`AnalystData::buildConditions` as the ACL. Two of MISP's:
`SharingGroup::authorizedIds` inside that predicate, and the
`Organisation` joins.

**Then one ACL'd fetch per claim, and it is not ours.**
`Relationship::afterFind` resolves `related_object` for every row it
returns, whether the caller wants it or not — `getRelatedElement` runs
`fetchSimpleEvent`, `fetchAttributeSimple` or `fetchObjectSimple`
depending on the target's type. Measured: **6 claims cost 13 queries, 0
claims cost 4.** Since the fetch happens anyway the panel reads its
result rather than ignoring it and asking again, and the one thing it has
to ask for itself is the *near* end of an inbound claim, which nothing
resolves.

This is the tab's only per-row query count, it is bounded by how many
claims people have written on one value, and it is recorded here rather
than hidden because §14.4's *"never one call per event"* rule has a
sibling this phase found: never assume a `find` on an analyst-data model
is one query.

### 5.4 The two rail cards pay for all three

`viewRelationGraph` and `viewRelationSettings` each need the tab's whole
arithmetic — the graph draws one region per notion, the card states the
split three ways — so each of them runs all three sections. On `8.8.8.8`
that is 37 queries and ~0.5 s per card against the co-occurrence panel's
16 and 0.38 s.

**Deferred, with the cost named.** A per-request context shared across
the five endpoints would remove the repeat, and §14.11 puts caching out
of scope. Phase 23 accepted the same shape for the same reason — its five
panels each rebuild `sightingContext` — and the two rail cards are the
first place on this page where the repeat is of something expensive
rather than of something cheap. Whoever revisits §14.11 should start
here.

---

## 6. Near-match: three engines, four states

The brief has three states — *active*, *not applicable*, *no engine in
MISP* — and says that having all three visible in one section is the
point. Live data adds a fourth that is none of them.

**CIDR containment.** Applies when the value's types include one of
`ip-src`, `ip-dst`, `ip-src|port`, `ip-dst|port`, which is what
`Correlation::__buildAdvancedCorrelationConditions` keys on. Re-derived
from `Correlation::getCidrList()` — the same Redis-backed list the engine
itself walks, 45 entries here — so the answer is the engine's own rather
than an approximation of it. The blocks are then fetched as attributes,
so each row carries an event, a reporter and an audience the reader may
actually see; a block nobody may see contributes no row, which is §14.6
applied to somebody else's value.

Containment is tested on the packed address, prefix-first, so IPv6 works
and agrees with `Correlation::__ipv6InCidr`. **Closeness is grounded
twice**, as §16 of the brief asks: the bar is the prefix as a share of
the address width — 32 or 128, which the fixture could hardcode as 32 and
live data cannot — and the column beside it is the address count. A `/8`
of IPv6 is 2^120 addresses, which no integer here holds, so the count is
formatted from a power of two; the v4 case stays exact and printable,
which is the case the argument rests on.

**A new state: *active, and it found nothing.*** `1.1.1.1` is in no block
on this instance. The panel says *"No network block on this instance
contains this address. The engine applies; it found nothing"* — which is
neither the empty state nor *not applicable*.

**The list is a cache, and it goes stale.** `getCidrList()` reads a Redis
set that is only ever rebuilt by `Correlation::advancedCorrelationsUpdate`
on an attribute save, so a block written outside that path — direct SQL,
`fast_update`, `skip_correlation`, the `OnDemand` engine — is invisible to
the correlation engine *and* to this panel until something calls
`updateCidrList()`. It cost a debugging session: `8.8.8.0/28` sat in
`attributes` with zero correlation rows while the cache held 44 entries,
so `8.8.8.8` reported *active, found nothing* against a block the database
plainly contained. Re-deriving from the engine's list is still right — it
is what keeps the panel from naming a containment the engine denies — but
the failure mode to recognise is a **cached** list, not a wrong one.

**ssdeep.** *Not applicable* unless the value is itself an `ssdeep`
attribute, which is the state the brief designs the block around and the
state almost every value is in. When it **is** one, two more:

- **`active`.** The extension is loaded, and the score is computed here
  because MISP keeps neither the number nor which engine wrote a row —
  `Correlation::ssdeepCorrelation` computes it only to test the threshold
  and throws it away. Verified on a real `ssdeep` value: the engine ran,
  compared against the other `ssdeep` attributes the reader may see, and
  reported that no pair cleared 40.
- **`unavailable`.** MISP ships this engine and it applies to this value,
  and `ssdeep_fuzzy_compare()` is a PHP extension MISP does not require.
  Saying *not applicable* there would be a lie about the value; saying
  *no engine in MISP* would be a lie about MISP. The panel says the
  engine is present and inert, and names the function.

**The candidate set does not go through MISP's own index**, and that is
deliberate. `Correlation::ssdeepCorrelation` narrows through
`fuzzy_correlate_ssdeep`, which is built at save time and holds **zero
rows on this instance against 1,387 `ssdeep` attributes** — so narrowing
through it would have returned nothing and reported it as *no match*
rather than as *no index*. Comparing against the type directly is what
`ssdeep_fuzzy_compare` is for, is bounded by the row cap, and cannot
silently inherit an empty index.

**Domain / TLD tree.** `absent`, on every value, exactly as the brief
has it. Nothing in MISP computes a parent-domain, registrable-domain or
public-suffix relation — no list, no table, no code path — and the stub
is how the gap stays visible.

---

## 7. Asserted: a claim with no text

> **§19 extends what a claim shows.** The target is linked and carries a
> line of its own facts, a galaxy cluster resolves, and the meta line
> names which organisation is which. A claim still has no prose — that
> part of this section stands.

`relationships` carries `relationship_type`, the two endpoints,
`authors`, an author organisation, `created`, `modified`, `distribution`
and `sharing_group_id`. It carries **no free-text column at all** —
unlike `notes.note` and `opinions.comment` beside it in the same
subsystem.

So the claim prose the brief renders, and the fixture's four paragraphs
of it, have nowhere to come from. This is a schema fact, not a fetch
this phase skipped.

What ships: the relationship type as the block's kind, the direction, the
target's kind and label, the author organisation, the date and the
distribution — and the block simply draws no prose where the fixture drew
a paragraph. The absence is explained **once, at the foot of the panel**,
rather than as a placeholder on every claim:

> A relationship carries no text of its own: `relationships` has the
> type, the two ends, an author and a distribution, and no prose column.
> Anything written *about* one is a Note attached to it, which this pass
> does not fetch.

**Deferred, with the cost named.** A Note hangs off a Relationship's
UUID like any other analyst data, so the prose does exist in MISP — it is
just one more fetch per claim on top of the one `afterFind` already
makes (§5.3), and `fetchChildNotesAndOpinions` recurses to depth 2. A
section whose whole argument is *never ranked, never truncated* should
not acquire an unbounded per-claim cost without deciding what its own
bound is.

**Direction is not a column.** A row is *outbound* when one of this
value's occurrences is `object_uuid` and *inbound* when it is
`related_object_uuid`, and reading it off the two endpoint columns is the
only way to know. It also decides which end is the interesting one to
name — and the far end of an inbound claim is the one nothing resolves
for us.

**The cap is on the lookup, not on the list.** Claims are written one at
a time by people, so the list is never truncated; the UUID set it is
looked up against is capped at 300 occurrences, newest first. A claim
written against an older occurrence would not be found, and the panel
says so when the cap bit — a section arguing *never truncated* has to
name the one place it can still miss something.

---

## 8. §14.6 — three notes removed

Every number on this tab is now the viewer's own. Three bands went, and
one sentence that named the same number by subtraction:

| Location | Was | Now |
|---|---|---|
| `03-relationships.md` §6 item 7 | `.vp-acl-note` — *"4 further correlations point into events you cannot see. They are counted in the 31 and are not listed."* | band removed |
| §6 item 1's second branch | *"%d of the %d stored correlation rows are visible to you"* | reworded; it named the hidden count by subtraction |
| §9.6/§9.7 siblings | `.vp-acl-note` — *"%d further attributes sit in these objects but carry a distribution that does not reach you"* | band removed (this is §14.6's own required-changes row for siblings, applied) |
| §8 asserted | `.vp-acl-note` — *"%d claims are held at a distribution you are outside of. Their existence is counted"* | band removed |

The three `hidden` keys survive in the array shapes and are hard zero,
because the templates read them; what does not survive is any sentence
that could state one.

**Every cap notice stays**, and this phase adds two: the scan statement
(§4.3) and the sibling row cap (§12.3). A cap is not a permission — an
oversized event is oversized for everybody, and 100 rows is 100 rows for
every reader.

**No new member of §14.6's exception.** The exception is for a panel that
renders a *computed judgement*; this tab renders counts and joins.
Nothing here needs a permanent caveat, and §14.6's rule — *a panel that
counts does not get one* — is what says so.

---

## 9. What MISP would not give, and what had to be worked around

### 9.1 `over_correlating_values.occurrence` is zero on every row

1,622 rows on the verification instance, and **every one has
`occurrence = 0`**. The column is filled by
`OverCorrelatingValue::generateOccurrences()`, a separate router job, and
nothing on the read path notices that it never ran.

The brief has the suppressed band printing that number as *"This value
occurs 21,904 times"*. It would have printed zero.

It is also the wrong number for a different and more important reason:
`occurrence` is **instance-wide**, so printing it would state a count
over rows the reader may not see, which §14.6 forbids outright. The
band's number is the viewer's own occurrence count, from
`occurrenceSummaryFor`.

### 9.2 `Relationship::afterFind` issues one ACL'd fetch per row

§5.3. A `find` on `Relationship` is never one query. It is also a
**latent fatal outside a web request**: `afterFind` calls
`getRelatedElement(array $user, …)` with `$this->__currentUser`, which
comes from `Configure::read('CurrentUserId')` — set by
`AppController::beforeFilter` and by nothing else. A console or worker
caller gets `null` passed to a typed `array` parameter. Not this
feature's to fix (§14.7), and worth knowing before somebody reads
relationships from a shell.

### 9.3 `rearrangeOrganisation` nests `Orgc` and re-queries when it is absent

`AnalystData::afterFind` moves a contained `Orgc` to
`$row['Relationship']['Orgc']` and unsets the top-level key — so the
obvious read finds nothing and every claim silently reports *Unknown
organisation*. Worse, when `Org` and `Orgc` are **not** contained it
fetches each of them per row: six claims is twelve `Organisation` finds
nobody asked for. Containing both turns that into two joins, which is
what this phase does; the query count in §5.3 is the contained figure.

### 9.4 `fuzzy_correlate_ssdeep` is empty

§6. 1,387 `ssdeep` attributes, zero index rows, and an engine that would
have reported *no match* for *no index*.

### 9.5 Two relationship targets point at attributes that no longer exist

Of the instance's 120 `relationships`, two point at an attribute UUID
with no attribute behind it, and `getRelatedElement` returns an empty
array for them. A claim about something deleted is still a claim somebody
made, so the row keeps its UUID as its label rather than being dropped.
`GalaxyCluster` targets never resolve either, for a different reason:
`getRelatedElement` handles Event, Attribute, Object, Note, Opinion and
Relationship and stops there, while `AnalystData::valid_targets` allows
six more.

### 9.6 `correlation_values.value` is `varchar(191)`

The correlation engine's own value table truncates at 191 characters
(`CorrelationValue::getIds`), so a value longer than that shares a
`value_id` with every other value having the same 191-character prefix.
Nothing in this phase reads that table — §3 is why — but it is the seam's
neighbour and worth recording beside §14.3's note that
`attributes.value1`'s index is a 255-character prefix.

---

## 10. The neighbourhood graph is now real

`03-relationships.md` §12 recorded that **no value-centred graph feed
exists** — `CorrelationGraphTool` expands events, not values — and
shipped a static SVG sketch with a visibly disabled *Open the full graph*
button rather than a canvas that looked live and was not. That was the
right call then and it is the one stub on this tab whose missing piece
was a feed rather than a write path.

> **§22 re-evaluates what this graph draws.** The feed below is real
> and stays; what it builds is a **star**, and a star carries nothing
> the three panels underneath it do not already print. §22 lists what a
> CTI analyst wants from a value-centred graph, splits it into the
> rail's peek and the overlay's full read, and names the one edge the
> scan already holds and this function discards.

### 10.1 The feed

`ValueProfile::graphFor` builds nodes and edges from the tab's own three
sections — not from the correlation table, which has nothing to say (§3).
The value is the centre; up to twelve neighbours per notion hang off it;
each edge carries its notion as its kind. §5 of the tab brief says the
tab lives or dies by keeping the three notions apart, and the graph
carries that separation four ways, as the tables do: shape (hexagon /
circle / square / triangle), colour (`--vp-rel-co` / `--vp-rel-near` /
`--vp-rel-human`), stroke (solid / dashed / arrowed) and the words in the
key and the tooltip.

**Every neighbour node carries the URL of its own Value Profile**, and a
double-click follows it. That is the one thing a value-centred graph can
do that an event-centred one cannot, and it is a double-click rather than
a click because a single click is how pivotick selects and how a reader
drags.

Twelve per notion, because the graph is a neighbourhood and not the table
with springs on it. The sub-line says how many of the total are drawn.

### 10.2 What the shipped pivotick build does and does not read

The library is already in `app/webroot/js/pivotick.iife.js` and already
used by the event Pivot Explorer, so nothing new is vendored. It is an
older build than the current documentation describes, and four things had
to be found by reading it:

- **`render.type` must be `'svg'`.** `defaultEdgeStyle` and the style
  maps apply only under the SVG renderer. Left at the default, all three
  notions draw in one colour and no dash ever appears.
- **A per-edge style is nested under `edge`.** The build reads
  `this.style?.edge`, so a flat `style` object is silently ignored — no
  error, no colour, and nothing to say which of the two it was. The
  callback forms of `dashed`, `markerEnd` and `styleCb` on
  `defaultEdgeStyle`, which the current documentation recommends, are not
  read at all.
- **`diamond` is not a shape.** The build knows circle, square, hexagon
  and triangle; an unknown shape draws no shape element, and pivotick
  then measures a node that is not there and throws `getBBox is not a
  function` on every render tick.
- **An empty string from `labelAccessor` is not "no label".** The build
  falls back to `data.label`, so the rail is handed a copy of the graph
  with the labels stripped out of the data instead.

Two more that are the build behaving correctly and the caller having to
know:

- **A `d-none` container is 0×0**, and pivotick sizes its viewport from
  the element it is handed. Built hidden it produces an empty `<svg>` and
  no shapes, with nothing thrown. The stage is revealed *before* the
  constructor runs and put back if it throws.
- **The node label is drawn inside the node and shortened to fit.** The
  character budget comes from the radius and so does the font size, so a
  bigger node buys no letters — about six either way. A non-zero
  `textVerticalShift` moves the label outside, which multiplies the
  budget by 2.5; with larger nodes in the overlay that is about sixteen
  characters, and the tooltip carries the whole value.

**Not swapped.** pivotick 1.6 ships *edge layers* — edges keyed by kind,
styled per kind and switched on and off without moving the graph — which
is precisely the three-notion switch this tab wants. It **released on
2026-08-28**, and three of the six findings above closed with it:
`styleCb` on a default style block is called, `textTruncate: false`
retires the six-character label budget, and a custom SVG path gives the
fourth notion a shape. §23.6 has the detail. Swapping the bundle still
touches the event Pivot Explorer and is still not a wiring phase's work,
so what is recorded as the follow-up is the swap (§15 item 4), not the
feature.

### 10.3 The rail shows the shape, the overlay shows the names

The rail draws **no labels**: thirty-seven of them in a 340px column
overlap into illegibility, and the card's job there is the same one the
sketch had — the shape of a neighbourhood. *Open the full graph* opens a
full-screen overlay with the same nodes, larger, labelled, and with the
edge labels on (shared-event counts on co-occurrence edges, the
relationship type on asserted ones).

**The sketch is still in the markup**, as the fallback. It stays visible
until the library reports for duty and stays for good if the 560 KB
script never arrives — a rail that renders a drawing beats a rail that
renders a hole. The loader re-creates every script the fragment brings
and lets an external one fetch asynchronously while the inline one runs
immediately, so the panel polls for `window.Pivotick` for six seconds
before giving up.

The feed is a literal inside that inline script rather than a
`<script type="application/json">` beside it, because `loadAjaxContainer`
re-creates scripts **without copying `type`** — a JSON block would be
appended to `<head>` as executable JavaScript and throw.

### 10.4 No edit affordance, in either place

Both graphs run in pivotick's `viewer` mode, and the overlay could have
had more. `light` and `full` add pivotick's main header, and that header
carries **Edit Graph** and **Notes** — two affordances that mutate the
canvas. They write nothing to MISP, which is exactly why they would be
wrong here: a reader dragging out an edge would think they had asserted a
relationship. §14 keeps every write control on this page disabled, and
the honest way to keep one disabled is not to offer it.

*Open the full graph* is the one control on this tab that stopped being
disabled, and its reason went away rather than being deferred again.

> **§23.5 re-opens half of this.** 1.6 adds
> `editors.<editor>.enabled: false`, which *removes* an editing
> affordance rather than vetoing it — the verb this section says it
> wanted. **Notes** has no such switch, so the objection holds for that
> one alone, and it is now the only thing keeping this graph out of
> `full` mode and away from the data dock.

---

## 11. Shared code

### 11.1 One new public method on `Correlation`

`Correlation::isValueExcluded($value)` — four lines wrapping the private
`__preventExcludedCorrelations`, which owns the leading/trailing-`%`
wildcard matching that `correlation_exclusions` uses. A caller cannot ask
that question without restating the rule, and restating it is how two
copies drift. §14.7's *small — make the change in place*: no existing
caller is affected, because there was no way to call it.

### 11.2 Two defects in this feature's own files, fixed here

Both are in `value_relation_cooccurrence.ctp`, which this feature owns,
so §14.7's *report, do not fix* does not apply:

- **The `Distribution` and `Tag` facet dropdowns rendered blank labels**,
  and had since phase 11. `value_facet_group` draws `html` where a caller
  supplies one and the bare `label` otherwise, and neither of those two
  facets has a label — the Occurrences rail has built the badge and the
  chip since phase 22 and this pane never did. It does now.
- **The rank select's `aria-label` said *"Roll the correlations up
  by"***, which was wrong before this phase for a different reason and is
  wrong twice now.

### 11.3 What was not touched

`value-profile.js` is unchanged: the facet bar, the filter row, the
roll-up switch, the pager, the `Similarity ≥` threshold, the
relationship-type filter and row selection all operate on markup whose
shape this phase did not alter. `value_facet_group`, `value_pager` and
`value_panel_header` are unchanged, and so is every badge element.

`value-profile.css` gains one block — the graph's own container, overlay
and close button — and changes nothing above it. The sketch's rules stay,
because the sketch is still the fallback.

The standing report-do-not-fix list from §7.9 and §14.7 is unchanged and
still unfixed: `multi_select_toolbar.ctp:18`'s `bg-light` bulk bar,
`Badges/type.ctp:12`'s `border border-dark`, and
`DistributionLevel.php`'s 4.09:1 level-1 tint.

---

## 12. Verification — what was run

Against the Docker stack serving this worktree, 2026-08-28, as **two
users**: the site admin (org 1) and a CIRCL org admin (org 9, no
`perm_site_admin`).

### 12.1 The values, and why each one

| Value | Why |
|---|---|
| `8.8.8.8` | 23 occurrences, 19 events, 12 objects — the populated case, and the one whose events include a 369,822-attribute one |
| `443` | the heaviest value on the instance: 48,255 occurrences, **1,844 events**, 394 objects — the event cap and the budget both bite |
| `0.0.0.0` | **32,922 distinct objects**, one per row of a flood capture — the sibling cap bites, and 2 of its 9 events are oversized |
| `185.92.180.100` | 1 occurrence, 1 event, and it sits inside `185.92.180.0/24` — the only value that exercises the CIDR engine end to end |
| `1.0.155.105` | its only event is the 369,822-attribute one — **the suppressed state** |
| `github.com` | 21 occurrences in a single event |
| an `ssdeep` hash | the ssdeep engine's `active` state |
| `no-such-value-anywhere.example` | the unknown value: five panels, no data, no invented anything |

`8.8.8.8` also carries **six seeded analyst relationships**, because the
instance had none that a value could reach: 112 of its 120
`relationships` are Object→Object and **not one** is anchored to an
attribute as its source. The seed is
[`24-relationships-seed.php`](24-relationships-seed.php), beside this
document rather than under `app/Console/Command/` for the reason §14.8
gives, and it writes both directions, four target kinds including one
MISP cannot resolve, two organisations and one claim at distribution 0.

### 12.2 Query counts and timings

Site admin, fresh facade per call, warm caches:

| Value | Co-occurrence | Near-match | Asserted | Graph | Settings |
|---|---|---|---|---|---|
| `8.8.8.8` | 16 / 382 ms | 1 / 1 ms | 13 / 8 ms | 37 / 543 ms | 37 / 434 ms |
| `443` | 14 / 1,025 ms | 1 / 218 ms | 4 / 79 ms | 15 / 1,320 ms | 15 / 1,437 ms |
| `0.0.0.0` | 15 / 813 ms | 3 / 163 ms | 4 / 66 ms | 18 / 954 ms | 18 / 1,035 ms |
| `185.92.180.100` | 11 / 30 ms | 3 / 3 ms | 4 / 7 ms | 14 / 10 ms | 14 / 9 ms |
| `1.0.155.105` | 7 / 86 ms | 1 / 2 ms | 4 / 8 ms | 9 / 41 ms | 9 / 37 ms |
| unknown | 4 / 22 ms | 1 / 1 ms | 1 / 1 ms | 5 / 3 ms | 5 / 3 ms |
| `8.8.8.8` as org admin | 16 / 37 ms | 1 / 2 ms | 7 / 9 ms | 24 / 25 ms | 24 / 19 ms |

**What the count scales with**: the number of *decorations* a
neighbourhood needs, not its size. The ceiling of 16 is reached by
`8.8.8.8`, a 23-occurrence value; `443`, with 48,255, issues 14. The
asserted panel is the one exception and scales with the number of claims
(§5.3), which is why its ceiling shows on the value that has some.

**What the *time* scales with** is the scan budget, and it is flat above
it: `443` and `0.0.0.0` both read ~20,000 and ~11,000 rows and both take
about a second, where a 23-occurrence value takes 0.38 s and a
single-event value 0.03 s. The budget is the knob.

### 12.3 Fragment weight, and two caps that came from it

Phase 22 measured 5.9 MB as *"a fragment that does not arrive"* and
capped its own table at ~1.7 MB. Two caps here came out of the same
measurement:

- **`RELATION_ROW_CAP` is 100, not 200.** At 200 the co-occurrence panel
  was 1.05 MB on `8.8.8.8`, because it ships all three roll-ups at once
  so the reader can switch between them without a request. 100 is also
  what phase 22's pager measurement allows: 13 page buttons at 8 a page,
  where 25 pushes the panel header into horizontal overflow.
- **The sibling list is capped at 100 rows and its total is not.** `443`
  sits in 394 objects whose 2,691 sibling attributes fold to some two
  thousand triples; listing all of them made one fragment **2.39 MB**,
  and **2.80 MB** for the org admin. The badge and the pager print the
  real total, so the cut reads as *1–8 of 100 (2,041 in total)* rather
  than as a table that quietly stops.
- **A facet group is capped at 40 entries and every count in it is
  exact.** `8.8.8.8` produces 128 distinct tags across its
  neighbourhood; the uncapped bar was 178 KB of markup behind a *"n
  more"* button nobody opens.

After all three, the heaviest fragment on the instance is **1.18 MB**
(`443`, org admin) and `8.8.8.8` is 740 KB.

### 12.4 What was asserted

**Sixteen combinations — eight values × two users — five panels each, 80
renders, no PHP diagnostic in any body** with `debug = 2`, so a missing
array key or an undefined variable would have landed in the markup.
Rendered against real data through a scratch shell
(`ValueRenderShell`) rather than over HTTP, because this session had no
session cookie for the instance; the shell drives the same elements with
the same view class and theme.

Read out of the rendered markup:

- **`8.8.8.8`**: `100 of 10,024 distinct values are carried`, the scan
  line naming 18 of 19 events and the one left out, the facet bar's
  folded-from-10,168 sentence, six counted facet dropdowns with the
  `Distribution` and `Tag` chips now rendering, `Object siblings — the
  same object, other relations`, and six `.vp-analyst` claim blocks with
  no `<table>` in that panel.
- **`185.92.180.100`**: `All 41 distinct values are listed`, the CIDR
  engine `Active` with `185.92.180.0/24`, `/24`, `256 addresses`, event
  `#1551`, `CIRCL`, distribution 3 — and the `Similarity ≥` control's
  bar reading 75%, which is the prefix as a share of 32 and what the
  control filters on.
- **`1.0.155.105`**: `Too large to read`, *"Every one of the 1 events
  this value appears in holds more than 10,000 attributes"*, and the
  sibling section listing 11 rows **under** that band — §17 of the tab
  brief demanded exactly that, and it holds.
- **The unknown value**: five panels, three differently worded empty
  states, `engines: []` so the near-match panel says *"This value has no
  attribute on the instance, so there is nothing for a near-match engine
  to compare"*, and a settings card that is still true.
- **The ACL, both ways.** The org admin sees **3 of the 6** claims on
  `8.8.8.8`: one is at distribution 0 and owned by another organisation,
  and two are anchored to occurrences that reader cannot see. Both cuts
  are real and neither is announced — which is §14.6.

### 12.5 The browser pass

The graph is JavaScript, so it was driven in a real browser at 340px
(the rail's width at a 1500px viewport) with the fragment served
locally, through **the same script-recreation the MISP loader does** —
`innerHTML`, then every `<script>` rebuilt and appended to `<head>`
without its `type`. That ordering is what forced the polling in §10.3
and would have hidden the JSON-block bug.

Measured rather than eyeballed, in **both themes**:

- **Dark**: 12 co-occurrence edges at `rgb(240,169,95)` = `#f0a95f` =
  `--vp-rel-co`, 6 asserted at `rgb(229,140,173)` = `#e58cad` =
  `--vp-rel-human`. Both resolve; neither is a literal.
- **Light**: 10 co-occurrence at `#b4610b`, 1 near-match at `#0b7f61`,
  and that one edge carries pivotick's `.dashed` class computing to
  `6px 4px`. So the dash renders and the three notions differ by stroke
  as well as by hue.
- **The canvas ground follows the page.** Pivotick paints its own and
  defaults to light; read from `data-bs-theme`, a dark page gets a dark
  canvas instead of a white rectangle in the middle of the rail.
- **The stage replaces the sketch and the button appears** only after the
  constructor returns; on a thrown constructor the sketch comes back and
  the console says why.
- **The overlay**: 19 nodes, 18 edges, 37 label texts, arrowheads
  pointing *out* of the centre for `connects-to`, `similar-to` and
  `derived-from` and *into* it for the inbound `blocks` and
  `related-to`. Labels legible — `google.com`, `kunai.json.gz`,
  `circl.lu`, `#4182 AgentT…30]`. No `Edit Graph`, no `Notes`, no
  sidebar.

### 12.6 Not verified, and why

- **The tab in the real page, with a session.** No credentials were
  available to this session — the dev stack's `.env` carries none — so
  every render went through the console shell and the browser pass went
  through a local server. What that leaves unchecked is the frame around
  the panels: the tab badge, the lazy-load trigger, and the four
  panels of *other* tabs re-rendering beside these. The panels
  themselves, their data, their markup and their JavaScript were all
  exercised.
- **A second `Similarity ≥` engine.** Only one near-match row exists on
  this instance, so the control was verified against one row rather than
  against the three the fixture had.
- **The tag facet's `local` flag** is always 0 here: no attribute tag on
  the instance is attached locally, so the chip's local marking is
  untested.
- **IPv6 containment.** The instance holds no IPv6 network block, so the
  packed-prefix path was exercised only on v4 and by reading.

---

## 13. What this changes in the contract

- **§14.12's board** gains five filled rows. The `Q` ceiling on the
  co-occurrence panel is 16 and it scales with decorations rather than
  with the value; the asserted panel is the first row on the board whose
  count scales with *rows returned*, and its reason is somebody else's
  `afterFind`.
- **§14.10's ledger** gains four hazards: `over_correlating_values.occurrence`
  is never filled by the read path (§9.1); a `find` on `Relationship` is
  one ACL'd fetch per row and a fatal outside a web request (§9.2);
  `AnalystData::rearrangeOrganisation` both nests its result and
  re-queries per row when the association is not contained (§9.3); and
  `fuzzy_correlate_ssdeep` can be empty while the extension is loaded
  (§9.4).
- **§14.6** has its siblings row applied, and two more removals recorded
  above it. The exception gains no third member.
- **§14.4's tiers** gain a case the vocabulary handles but the table does
  not mention: a tier-2 aggregate that applies **no** ACL because its
  answer never reaches the page and only decides what the ACL'd fetch
  then reads (§5.1, query 2).
- **§14.11's caching exclusion** now has a named first customer: the two
  rail cards each repeat all three sections (§5.4).
- **The tab badge is gone rather than corrected**, which is the third
  answer §14.12's badge note now has. Occurrences took a real number,
  Sightings took none because the true number is expensive, and
  Relationships takes none because the fixture's number counted
  *correlations* — something the live tab no longer claims at all. The
  key is unset in `forTabCounts` and the `count` line is off the tab
  entry.
- **One number about relationships is still the fixture's, and it is on
  another tab.** `value_lifecycle` on the Overview prints
  `%s correlations` from `$valueProfile['correlations']`. That is now the
  only place on the page stating a correlation count, and it is the
  Overview's phase to convert. Whoever does it should read §3 first: the
  number is real — MISP does store correlations for a value — but it
  describes other occurrences of the same value, so the card should not
  imply it is a count of things the Relationships tab lists.
- **`03-relationships.md` §12's seven blockers**, item by item: the
  domain/TLD engine is still absent and still drawn; correlations still
  carry no provenance, and it no longer matters, because nothing on the
  tab reads them; the ssdeep score is still discarded and is recomputed
  here; `MISP.correlation_limit` is still 20 and its state is now on the
  settings card rather than over section one; object siblings are indeed
  the cheapest and highest-signal thing on the tab; analyst
  relationships are indeed per attribute and both directions; and **no
  value-centred graph feed existed — there is one now** (§10).

---

## 14. The three concepts, assessed

`value-profile-coverage.md` §5 gives this phase **yes / yes (the strong
case) / weak**. All three are addressed and all three are **deferred**,
each with a reason rather than a shrug.

> **§17 supersedes the feeds and servers paragraph below.** Both grounds
> for that deferral are gone: the cache is populated, and the permission
> question is answered in `03-relationships.md` §20. The one MISP defect
> that stood in the way is fixed — §17.2.

**Proposals — deferred, and the decision is stated.** A proposed
addition (`ShadowAttribute.old_id = 0`) in an event that already holds
this value is a co-occurrence that does not exist yet, and §5 is right
that either answer needs stating. **The answer here is no: a proposal is
not a co-occurrence.** Section one counts what is in an event, and a
proposal is by definition not in it yet — it is a request to put it
there. Counting it would make `shared_events` a number that includes
things nobody has accepted, on the one tab whose whole design is about
not blending kinds of evidence. What a proposal *is* is a recorded
disagreement, which is the Verdict tab's language, and
`value-profile-coverage.md` §5's Verdict row already routes it there.
The cost of this answer: a reader watching a value arrive in a new event
sees the co-occurrence only after somebody accepts the proposal.

**Feeds and sync servers — deferred, and it is the one piece of real
upside left on this tab.** §5 is right that this is the strong case.
`Feed::searchCaches($value)` reads
`misp:feed_cache:event_uuid_lookup:<md5>` and returns the remote event
UUIDs carrying the value, plus a `feeds/previewEvent` deep link per
event — **co-occurrence outside this instance**, which is exactly the
graph §12 of the tab brief said could not be drawn, and which the graph
built in §10 could now hold as a fourth edge kind.

Not built here, for two reasons and one of them is a blocker.
`Feed::searchCaches` **applies no role check** — `$limited` filters on
`lookup_visible` and nothing else — while `perm_view_feed_correlations`
gates feed correlations everywhere else in MISP (`Feed.php:521`). A
panel that rendered its output unfiltered would show ordinary users what
that permission withholds from them, which is
`value-profile-coverage.md` §7's disclosure finding, and adding the gate
is a decision about who sees what rather than a wiring detail. The
second reason is that it cannot be verified here: the instance holds 88
feeds, **none enabled**, 5 with caching switched on, so the cache is
empty and a panel built against it would ship unexercised.

**Event reports — no, with the reason §5 predicted.** Two attributes
cited in one report is an asserted relationship of a sort, but this tab's
asserted section is `relationships` rows written on purpose, and
stretching it to cover *"these two strings appear in the same
markdown"* would put a machine-derived inference into the one section
that exists to hold human claims. That is the failure §5 of the tab
brief names. `no` with a reason is a complete answer.

---

## 15. Exit criterion, and what is left

`03-relationships.md` §14's exit criterion still holds against live
data: `R1` is recognisable with `R3`'s narrowing bar on its first
section, the three notions cannot be mistaken for one another, and a
value whose neighbourhood cannot be read renders as suppressed rather
than as empty. What changed is which value that is — `8.8.8.8`, the
fixture's suppressed example, has a perfectly readable neighbourhood on
this instance, and `1.0.155.105` is the one that cannot be read.

### 15.1 Follow-ups this phase names

1. **A tab-level context** — **done**, and neither of the two shapes this
   item guessed at. Not one endpoint and not a per-request assembly: the
   duplication is across the six requests, so it took a held digest of
   what the rail cards actually need. §18 has the design and the
   measurements — 663 ms to 222 ms warm on `8.8.8.8`.
2. **Feed co-occurrence** — **done.** `03-relationships.md` §20 is the
   design and §21 the build; §17 has the measurements and the two
   `Feed::searchCaches` fixes it needed. The gate turned out to be per
   source rather than one permission (§20.2, §20.9). **Still open from
   it:** the graph's fourth edge kind (§20.7 — one remote fetch per
   event, so it waits on item 4), SightingDB on the Overview card
   (§21.2 — the primitive exists, the decision does not), and the
   *nothing cached on this instance* state, which §21.4 could not
   exercise without undoing the cache this phase needed.

   It also **made item 1 worse**: `forRelationSettings` now assembles a
   fourth section as well, so the settings card repeats all four and
   `externalPresence` runs twice per Relationships render.
3. **Claim prose**, as child Notes on each relationship (§7), once
   that section has decided its own per-claim bound.
4. **pivotick's edge layers** — **released**, in 1.6.0 on 2026-08-28,
   and the release carries far more than this item asked for (§23). The
   shipped bundle still predates it, so the bundle swap and the event
   Pivot Explorer's re-verification stand. What does **not** stand is
   the upstream ask: edge layers need nothing from us, and the
   `../pivotick` `prd/` item is now `editors.notes.enabled`, which is
   what still keeps this tab out of `full` mode (§23.5).
5. **A `Sighting`-style counting method for `Relationship`**, so the tab
   bar could carry a claim count without running the panel — the same
   shape phase 23's §12.1 asked for on sightings, and the same reason.
6. **`over_correlating_values.occurrence`** is dead weight on the read
   path (§9.1). Either the router job runs, or the column should stop
   being offered as a number anything can print.
7. **A claim's tooltip** — **done**, §20. It carries what §19.9 kept out
   of the block: the occurrence the claim is written *against*, the
   claim's own UUID, `created` beside `modified`, `authors`, the sharing
   group behind a level-4 distribution, and per-kind columns of the far
   end. No queries; the fragment doubles. **Still open from it:** a
   claim's child Notes are item 3 and the card is where their absence is
   now most visible — the one thing a reader hovering a claim might
   expect to find and cannot.

## 16. After the phase: what the follow-up passes changed

Phase 24 put the five panels on live data. Reading them against a real
instance then found things the fixture could not show, and the passes
below are what came of that. They are recorded here rather than folded
into the sections above, for §18's reason in `03-relationships.md`: the
reasoning that produced the original is worth reading beside the
correction.

### 16.1 The section became two panels

`Co-occurrence` held the object siblings and the ranked table under one
header, and the two answer different questions — one structural, one
statistical. They are now **`In the same object`** and **`In the same
events`**, each a panel with its own header, its own narrowing bar and
its own pager. Every narrowing control moved beside the table it
governs; the sibling table had none of its own before and was narrowed
by controls sitting above a different table.

`Group by` and `Rank by` are pill groups rather than selects — a select
hides its alternatives behind a click, which is how the roll-up came to
be the least-used control on the tab. Both tables sort by column
heading, three states: ascending, descending, then back to the order
the model sent, because that order is itself an answer and no column
would bring it back.

**Row selection is gone.** The checkbox column, its header select-all,
the `N selected` readout and the two disabled actions beside it —
`Tag the selection`, `Add selection to a collection` — were a bulk-write
affordance on a page that does not write, and the column cost a cell in
every row to say so. `Open all N as a search` stays, and now
counts what the narrowing matched. `03-relationships.md` §6.6 and §11
still describe them, marked as superseded by its §19.

### 16.2 Three cells were rounding what they had

**The sibling `Objects` count is a bar.** It is how many objects one
folded row stands for, and it was a bare right-aligned number while the
same kind of magnitude in the panel below was drawn on
`.vp-rel-bar`. It now uses the same bar; the bar prints its own
reading, so the count is still
exact, and `$weightBar` takes the `≥` floor marker as a prefix so a
capped section does not lose it.

**A sibling row with one event links to it.** The fold dropped the event
id unless the row stood for a single object, so a row folding five
objects that all sit in the same event read a bare `1` while holding the
id of the event it meant. The link now survives wherever the fold left
one event to name:

```php
$oneEvent = $held === 1 || ($exact && count($events) === 1);
```

`$exact` is `in_objects <= cap`: above 500 objects the fold is partial,
so *"these all sit in one event"* would be a claim about what was read
rather than about the value. The single-object case keeps the link it
always had, so no row loses one. On `8.8.8.8` this takes linked rows
from 10 of 22 to 20 of 22; on `443` from 0 to 100 of 100, all naming
`#3984` — one event holding 271 `ddos-config` objects that each carry
the value, a case the old rule hid completely because none of those rows
stands for a single object. Cost: +0.8% fragment, and a fold time inside
the noise band (best 3.24–3.33 ms either way over 857 triples).

**`Distribution` is a set, not a badge.** The column kept the *widest*
audience among a row's occurrences and dropped the rest, so a row could
read `All communities` while one of the records behind it was org-only,
and `Sharing group` never said which group. The row now carries the
distinct `(effective level, sharing group)` pairs, widest first by
`ValueStatsTool`'s own `restrictionRank`, drawn as MISP's badge once per
audience with `+N more` past three and the group named and linked to
`/sharing_groups/view/<id>` — the affordance the Occurrences tab already
had.

`effectiveDistribution` is unchanged and still per occurrence: the
conjunction of attribute, object and event, tightest wins. What went is
the *second* fold on top of it, which a set of records spread over
events cannot honestly have. On `8.8.8.8`, 10 of 100 listed rows have
more than one audience and were all showing a single badge.

### 16.3 Narrowing now reaches the neighbourhood

**The measurement that opened this.** Ticking a facet emptied the table
it had just been counted in. Swept across every checkbox on the panel
for `8.8.8.8`, **60 of 107 led to an empty table**:

| Group | boxes | dead |
|---|---|---|
| Tag | 40 | 35 |
| Type | 26 | 14 |
| Object | 11 | 7 |
| Event | 17 | 3 |
| Organisation | 6 | 1 |
| Distribution / Sharing group | 6 | 0 |

The worst were the ones a reader is most likely to click: `abuse.ch`
said **9,791** and showed none, `#2120 ThreatFox IOCs for 2023-09` said
6,630, `ip-dst|port` said 2,229.

**Two causes, and they compound.** The first is the order of operations:
fold every value, count the facets over all of them, rank by shared
events, `array_slice` to `RELATION_ROW_CAP`, ship — and then filter *in
the browser* over what survived that cut. The counts describe the
neighbourhood; the filter reached the hundred. `abuse.ch`'s values each
share one event, so they rank at the bottom and never cross.

The second is smaller and older: the facets count a value under every
type, category and object it appeared as — §5's own note says so — while
the row carried only its `dominant()` one. `type` and `object` could not
find rows they had just counted, cap or no cap.

**A row's tokens are the fold's now.** `tokensFor()` defines one key's
tokens for one group and is read by both the row builder and the filter,
so the string a facet counts and the string a filter matches cannot
drift; the slug is `ValueStatsTool::facetToken`, which is what built the
facet entries. Every value the group held is emitted, not the dominant
one. `sibling:yes` is among them, which is why `siblingSection()` now
runs before the fold and passes `our_objects` — a context key
`cooccurrence()`'s own signature had documented since the phase and
nothing had ever set.

**The filter runs before the cut.** `cooccurrence()` takes `filters` and
applies them to the ranked list before slicing, so the table holds the
top hundred *of what was asked for*, and returns `matched` so the pager
reads `1–8 of 100 (9,791 in total)`. Facet counts stay folded over the
whole scope — they answer *what else is there*, and recomputing them
under the active filter would make the bar move under the reader's hand.

Semantics match the browser's exactly, because the browser's are the
ones people already learned: disjunctive inside a key, conjunctive
across keys, and a select kept apart from the facet arrays under
`select` because the panel means them differently — two ticks in a
dropdown are *either*, a select on top of them is *and also* (§16 of
`03-relationships.md` argued this and it still holds).

**The wire.** `f[<key>][]=<token>` for facets, `f[select][<key>]=<token>`,
`f[text]`, `f[min_shared]`, `f[rank]`. Nothing is trusted:
`cleanFilters()` drops every key the tool did not declare, and the values
are only ever compared against tokens the fold generated itself, so they
reach no query and no output. The panel re-requests its own endpoint —
`viewRelationCooccurrence` — through `reloadAjaxTabIndex`, and comes back
with every control set the way it was left: boxes ticked, selects
chosen, text and threshold filled, rank pill on.

**The browser still answers what it provably can**, which is the common
case. Two things stay local:

- **Nothing was cut.** `AF_INET`'s 51 neighbours all fit, so every
  control on that panel is exact in the markup and no request is ever
  made.
- **The entry's whole count is already present.** The fold stamps
  `data-vp-complete` where a facet's `listed` equals its `count`. Because
  the client then holds *every* row carrying that token, it holds every
  subset of them too — so a combination of complete entries is itself
  complete, whether the boxes are read as *either* or as *and*. 16 of
  106 entries qualify on `8.8.8.8`.

The search box and the `Shared events ≥` threshold range over values the
panel never received, so neither can be proven complete and both go to
the fold whenever a cut happened. Anything remote is debounced 300 ms —
ticking three boxes is one question — over rows dimmed by `.vp-narrowing`
rather than filtered to an answer nobody asked for.

**Measured.** `organisation=abuse-ch`: 0 rows before, 9,791 matched and
100 listed after, 462 ms. `event=2120`: 6,630 / 398 ms. `type=ip-dst-port`:
2,229 / 413 ms. `tag=tlp-white`: 4. `min_shared=3`: 6. `text=cdn`: 19,
including `ghdyuienah123.freedynamicdns.org` at one shared event, which
the browser's own search could never have found. `organisation=hack-lu`,
complete at 54 of 54: **no request at all**.

A new **`Sharing group`** facet sits beside `Distribution`, keyed per
group, so *which neighbours touch this group* is a question the bar can
answer — it never could, because level 4 was one bucket however many
groups were in it. On `8.8.8.8` it names one group over 30 values, and
`Your organisation only` moves from a fold-shadowed count to **9,848**.

### 16.4 `Rank by` ranks the neighbourhood

The pills were `data-vp-sort` over the rows already shipped, so `Most
recent` answered *the most recent of the most shared* while saying *most
recent*. On `8.8.8.8` its best was `2026-02-26`; the neighbourhood's
newest values are `2026-07-08`, each sharing a single event and none of
them anywhere near the cut.

The ranking travels with the narrowing because it decides the same
thing — which values reach the cut — and takes the same hybrid path: a
panel holding its whole neighbourhood reorders in place with no request
(`AF_INET`, verified at zero fetches), one that was cut asks the fold.
Switching roll-up no longer clears a narrowing the fold applied:
clearing the controls while the rows stay narrowed leaves the panel
saying something it is not doing, and `Reset` is what undoes it.

### 16.5 Smaller

- **`1 filters`.** Both counted nouns in both narrowing bars carry their
  two `__()` forms and the script picks by the number, so a translation
  can put the boundary where it wants.
- **The browser harness drove nothing.** It rendered a panel and
  reported on inert markup: `value-profile.js` returns from `init()`
  unless the body carries `data-controller="values"`, and a panel's list
  machinery binds off `misp:container-loaded`. A sort assertion came back
  green over a table no click had touched. Fixed, with the rule that
  would have caught it, in
  [`24-relationships-browser.md`](24-relationships-browser.md).

### 16.6 What is still true of the cut

`RELATION_ROW_CAP` is unchanged and so is the reasoning for it: 100 rows
is thirteen pager buttons, and the fragment is already three-quarters of
a megabyte on `8.8.8.8`. What changed is that the cap now applies to the
set the reader asked for rather than to the set they did not. A
narrowing that matches more than 100 still says so — the pager prints
`(9,791 in total)` — and the facet bar's own sentence was rewritten to
match: narrowing on a count larger than the hundred carried *fetches its
rows* rather than emptying the table.

### 16.7 The scan is held for five minutes, and says so

Narrowing re-requests the panel (§16.3), and each request repeated the
whole scan to fold the same rows against a different filter. The reads
— the event sizing, the neighbour scan, `attachTags`, the sibling join
and the two name lookups — now come from Redis where they are still
warm, so the first request pays and every narrowing after it is a fold
over rows already in hand.

| | cold | warm |
|---|---|---|
| `8.8.8.8` | 431–535 ms | 173–203 ms |
| `443` | 1,153 ms | 454–491 ms |
| second filter, warm (`8.8.8.8`) | — | 255–302 ms, against 400–480 |

**The entry is smaller than the rows are.** 11.6 MB serialised and
**408 KB stored** on `8.8.8.8`, 18.3 MB and 670 KB on `443`: the rows
repeat their own shape and `RedisTool::compress` collapses them about
28:1. A minute of one reader on one value costs well under a megabyte.

**Keyed on the viewer.** Every row in there went through
`buildConditions($user)`, so two readers of one value do not see the
same neighbourhood and cannot share an entry. That means a cold cache
per reader, which is the price of not having to reason about whether a
cache can leak across an ACL boundary. Redis being unavailable is not an
error: `RedisTool::init()` throwing falls through to the live scan and
the page is as slow as it was before.

**Five minutes, and the reason it is not sixty seconds.** Nothing
invalidates this entry — no event-change hook reaches here — so on its
own the clock would be the only bound on a stale neighbourhood, and
somebody who had just added an attribute would be left wondering why it
was missing. Two things buy the longer window:

- **The panel says how old the read is.** The sentence that already
  describes the read ends with `Scanned 3 minutes ago.` The phrase is
  relative because that is what a reader can act on; the exact stamp is
  in the `title`, and it is the half that stays true if the tab is left
  open, because the fragment is server-rendered and the words freeze
  where they were.
- **`Scan again` beside it**, which re-requests the panel with
  `fresh=1`: the held entry is skipped on the way in and rewritten on
  the way out, so one press lands on new rows rather than on an empty
  cache. It carries the narrowing with it, so pressing it under a filter
  comes back filtered. Verified: the stored read time moves rather than
  merely being bypassed.

**Why a refresh parameter and not a clear-cache endpoint.** A clear
endpoint takes two steps to get to fresh data — clear, then reload —
and costs a new action, a new ACL entry and a state-changing `GET` that
a prefetcher could fire. `fresh=1` is the request the narrowing already
makes. Its cost is bounded by what the page did before any of this: one
scan.

**It still does not go higher than five minutes.** The reader now has a
cue for *I* just added something and none at all for *somebody else*
did, and no affordance fixes that — only invalidation would. That is the
condition for raising this, and it is the same shape as §15.1's other
follow-ups: a hook that does not exist yet.

---

## 17. External presence — the design is settled, and what the cache showed

§14 deferred feeds and servers on two grounds and §15.1 item 2 carried
them forward: the permission question was a decision rather than a
wiring detail, and the instance held 88 feeds with **none enabled**, so a
panel built against the cache would ship unexercised.

**Both grounds are now gone.** A feed and a sync server were enabled and
cached on 2026-08-31, and the permission question is answered in
`03-relationships.md` §20 — which is the design, agreed the same day.
This section is the reality against it: what the populated cache
actually holds, and the two defects found reading it. One of them blocks
the build.

### 17.1 The cache, as it now stands

| Source | Format | Enabled | `lookup_visible` | Cached values |
|---|---|---|---|---|
| Feed 1 — CIRCL OSINT Feed | misp | 1 | 1 | 536,803 |
| Feed 2 — The Botvrij.eu Data | misp | 0 | 1 | 28,342 |
| Feed 41 — URLHaus Malware URLs | csv | 0 | 1 | 15,266 |
| Feed 62 — Malware Bazaar | csv | 0 | 1 | 744 |
| Feed 64 — Threatfox | misp | 0 | 1 | 1,545,743 |
| Server 1 — Training Main | — | — | — | 542,072 |

`misp:feed_cache:combined` holds 2,092,449 hashes and
`misp:server_cache:combined` 542,072. `MISP.host_org_id` is 1.

Two things to read off this table, and the second one matters more:

**Caching is independent of enabling.** Four of the five feeds are
disabled and cached. So the section's rows can name a source the
instance is not pulling from, and its remote-event links still resolve,
because `previewEvent` fetches from the feed URL rather than from
anything local.

**All five carry `lookup_visible = 1`, which is not the default.**
`INSTALL/MYSQL.sql:572` defaults the column to `0`. This instance is
therefore *more* permissive than a stock one, and cannot be used to
judge how §20.5's role notice will read in the field — here a plain user
sees five feeds, and on a stock instance the same user sees none. Any
verification of the notice has to flip `lookup_visible` to 0 rather than
trust what this instance shows.

### 17.2 The defect that blocked the section — fixed

`searchCaches` reads the **global** per-value uuid set inside its
per-source loop and strips the source id without checking it
(`explode('/', $url)[1]`, `Feed.php:2022` for feeds and `:2072` for
servers). Every hitting source is then handed every uuid.

Measured on `zxzhjlk.artenadigital.com`, whose uuid set is exactly two
entries from two different feeds:

```
1/031fb9c1-32e9-4363-aa51-6f4df779cb14      CIRCL OSINT Feed
64/fd94eeff-6af7-453d-ab6e-3552ad2dcbba     Threatfox
```

`searchCaches` returns feed 1 with **both** uuids and feed 64 with
**both** uuids — four `previewEvent` links, of which two name a feed
that does not hold that event.

**543 of the 4,761 hitting values in a 40,000-value sample — 11% — have
a uuid set spanning more than one feed**, so this is not a corner case.
It lands precisely on the affordance §20 exists to provide.

`attachFeedCorrelations` already gets this right, at `Feed.php:670`:

```php
list($feedId, $eventUuid) = explode('/', $url);
if ($feedId != $sourceId) {
    continue; // just process current source, skip others
}
```

**Fixed 2026-08-31** — that filter, in the two places `searchCaches` was
missing it. Both branches now build their uuid list by reading the set
once and keeping only the entries whose prefix is the source being
processed, which also removes the index-rewriting the server branch was
doing in the middle of composing its `direct_urls`.

Same value, after:

```
MISP Feed    1   CIRCL OSINT Feed   feeds/previewEvent/1/031fb9c1-…
MISP Feed    64  Threatfox          feeds/previewEvent/64/fd94eeff-…
MISP Server  1   Training Main      servers/previewEvent/1/031fb9c1-…
```

Four links became three, and each one names the source that holds it.

**Verified by invariant, not by inspection.** For every distinct local
value with a cache hit, every uuid `searchCaches` returns for a source
must appear in the lookup set under that source's own prefix, and every
uuid the set holds for a hitting source must be returned:

| | |
|---|---|
| values with a cache hit checked | 4,761 |
| of which the lookup set names more than one source | 543 |
| event uuids returned | 10,011 |
| attributed to a source that does not hold them | **0** |
| held by the source and not returned | **0** |

The second row is the one that matters: the 543 multi-source values are
exactly the population that was broken, and they are inside the run that
comes back clean. The two-sided check is what rules out a fix that
simply drops uuids — 10,011 returned against a measured median of 2 per
value is the same volume as before, redistributed correctly.

`24-external-presence-verify.php`, beside this document, is that check;
it runs the same way as the probe of §17.7.

This was a MISP fix rather than a page fix, and it is in this branch
alongside the design.

### 17.3 The card's count is not a lighter read

The Overview card was proposed as a "light check" on the grounds of
cost. There is no cost to save:

| Read | Before §17.6 | After |
|---|---|---|
| `searchCaches($v)`, value already lowercase | 0.565 ms | 0.302 ms |
| `searchCaches($v)`, mixed case — two hashes | — | 0.389 ms |
| `searchCaches($v)`, no hit | 0.038 ms | 0.020 ms |
| the two `sismember` calls on the combined sets alone | 0.09–0.15 ms | unchanged |

All of them are noise beside the 173–535 ms §16.7 measures for section
one. So the card being a count is an **editorial** decision about what a
rail has room for, recorded as such in §20.3 — not a performance one,
and nothing stops the card carrying more later.

### 17.4 The section is small

Remote events per hitting value, over the same 4,761:

| min | median | p95 | max |
|---|---|---|---|
| 0 | 2 | 4 | 52 |

Exactly one value has zero, and it is the non-MISP-format case of
§17.5. This is why §20.4 gives the section no pager and no facet bar: a
25-row cap covers the instance with room to spare, and the whole
neighbourhood out there is smaller than a single page of section one.

### 17.5 The two panels agree here, and that is instance-specific

`onlyOtherFormatFeed` — a value that hits a CSV or freetext feed and
nothing else, so the card counts a hit the section has no event for —
came to **1 value in 40,000**:
`http://79.135.225.50:13477/.i`, feed 41.

So on this instance the card's count and the section's rows will
essentially always match. That is a property of *which* feeds are
cached: the two non-MISP feeds hold 15,266 and 744 values against
2.09M combined. An instance caching a large freetext feed would widen
the gap. §20.4's "present, no event to open" row is therefore cheap
insurance rather than a common state, and it stays for that reason.

Separately, 45 values hit **only** the server cache. Those are the ones
that make §20.2's server rule visible: read through the `$limited` path,
which drops the server branch entirely (`Feed.php:2062`), all 45 return
zero hits — a value sitting on the sync server, reported as absent.
`mailout-us.gmx.ru` is one, and it is the value to test that state
against.

### 17.6 A second defect: written raw, read lowercased

The writer hashes the value **raw** — `$md5 = md5($v)` at
`Feed.php:1663`, written to the per-feed set, the combined set and the
uuid lookup at `:1666`. `searchCaches` hashes it **lowercased and
trimmed** — `md5(strtolower(trim($v)))` at `:2007`. The event view's
reader hashes raw, `md5($part)` at `:579`, so it agrees with the writer
and `searchCaches` does not.

**21 of the 40,000 values are in the feed cache under `md5($value)` and
absent under the lowercased hash**, so `searchCaches` cannot see them at
all while the event view can:

```
XOR
US
POST
https://x.com/Malwarehunterr/status/2071679859819237847
https://x.com/Fact_Finder03/status/2071599266104275219
```

Mixed-case URLs are the systematic class, and they are ordinary threat
intel.

**Fixed 2026-08-31.** `searchCaches` now tests **both** hashes rather
than changing either side's normalisation, because a false positive is
impossible — a hit on either hash means some publisher cached that
exact string — and because the quick-cache path at `:1705` takes hashes
straight from the feed's own file, so mixed normalisation is baked in and
cannot be legislated away from one end. The second hash is only added
when it differs, so a lowercase value still does exactly one lookup.

**Pipelined in the same pass**, which is what made the fix free. The
method used to issue one `sismember` per source in a loop, and to re-read
the *same* global uuid set once per hitting MISP feed. It now issues two
round trips per value — the combined checks, then every per-source test
and both uuid sets together — because the uuid key depends on the hash
alone and never on which source hit. `attachFeedCorrelations` already
pipelined; the precedent was in the same file.

| `searchCaches($v)` | Before | After |
|---|---|---|
| value already lowercase | 0.565 ms | **0.302 ms** |
| mixed case, so two hashes | 0.565 ms, and wrong | **0.389 ms** |
| no hit | 0.038 ms | **0.020 ms** |

**Verified, both directions.** The six known gap values return two hits
each where they returned none. Across the same value scan, with the
invariant of §17.2 rebuilt to take truth from both hashes:

| | Before | After |
|---|---|---|
| values found to have a cache hit | 4,761 | **4,798** |
| event uuids returned | 10,011 | **10,158** |
| attributed to a source that does not hold them | 0 | **0** |
| held by the source and not returned | 0 | **0** |

37 values and 147 remote events that were in the cache and unreachable.
The invariant staying at zero is the half that matters: the widened
lookup did not start crediting sources with events belonging to another.


**What the second lookup costs, measured.** The worry is a feed with
millions of entries. It is not the cost that scales:

| Set | Cardinality | `sismember` |
|---|---|---|
| Malware Bazaar | 744 | 0.0368 ms |
| URLHaus | 15,266 | 0.0460 ms |
| Botvrij | 28,342 | 0.0424 ms |
| CIRCL OSINT | 536,803 | 0.0302 ms |
| Threatfox | 1,545,743 | 0.0393 ms |
| `feed_cache:combined` | 2,092,449 | 0.0347 ms |

Flat across three orders of magnitude, because a Redis set membership
test is a hash lookup. **A feed's size is irrelevant to this read.** What
scales is the number of cached *sources*, linearly, at about one round
trip each.

Against that, the second hash never doubles the complexity of anything —
it doubles a count of O(1) calls, and only for a value carrying
uppercase or surrounding whitespace. Measured on the unpipelined shape
the method used to have, so that the two effects can be read apart:

| Values | 1 hash | 2 hashes |
|---|---|---|
| already lowercase | 0.199 ms, 12 calls | 0.164 ms, 12 calls |
| mixed case | 0.332 ms, 12 calls | 0.791 ms, 24 calls |

The first row is the same work twice — for a lowercase value the two
hashes are the same string and the second lookup collapses away — so its
two columns differing by 20% is the run-to-run noise on this box, and it
sets the floor for reading the rest.

**Where the source count bites, and it is not the entry count.** Six
cached sources cost 28 unpipelined calls for two hashes; 98 — this
instance's feed count, were they all cached — cost 396, about 14 ms of
round trips for a single value. Pipelined it is two round trips whatever
the source count. So the ceiling on this read is the number of sources
and whether the loop batches; neither has anything to do with how many
entries a feed holds.

`24-external-presence-bench.php`, beside this document, is the
measurement, and the numbers above are the state it found before the
fix.

### 17.7 The probe

`24-external-presence-probe.php`, beside this document. It reports the
cache state, classifies every distinct local value against both caches,
and prints the misattribution case and the normalisation gap with
examples. Same convention as this phase's other two scratch shells:

```
cp prd/value-profile-live/24-external-presence-probe.php \
   app/Console/Command/ValueExternalProbeShell.php
app/Console/cake ValueExternalProbe
rm app/Console/Command/ValueExternalProbeShell.php
```

The `40000` limit on its value scan is the only tuning knob; the
`MispAttribute` alias rather than `Attribute` is not optional, since PHP
8's built-in `Attribute` wins the `ClassRegistry` lookup otherwise.

---

## 18. §15.1 item 1 — the rail cards stopped re-assembling the tab

**Done 2026-08-31.** The tab fires six requests and two of them are rail
cards that describe the other four. They described them by building them
again.

### 18.1 What it cost, before

Six endpoints, timed one after another against a warm cache, `8.8.8.8`:

| Endpoint | Warm |
|---|---|
| `viewRelationCooccurrence` | 190.0 ms |
| `viewRelationNearMatch` | 1.3 ms |
| `viewRelationExternal` | 2.7 ms |
| `viewRelationAsserted` | 14.3 ms |
| **`viewRelationGraph`** | **244.8 ms** |
| **`viewRelationSettings`** | **210.0 ms** |
| sum of six | 663.0 ms |

**The two rail cards were 455 ms of 663 ms** — each costing more than the
section it summarises. Neither needed the rows: the graph draws at most
`GRAPH_NODE_CAP` nodes per notion and the settings card prints four
integers and a flag. They were inflating an 11.6 MB scan out of Redis and
re-folding 21,904 rows to do it.

### 18.2 What ships

**Not one endpoint, and not an in-request assembly.** One endpoint was
rejected when the tab was designed and the reason still holds — the
sections cost wildly different amounts and one slow scan must not hold up
the claims. An in-request memo fixes nothing either: each of the six is a
separate PHP request, and inside its own request no builder runs twice.
The duplication is *across* requests, so the fix has to be a held read,
which is what §16.7 already established for the scan.

`relationDigest()` holds what the rail cards actually need — four counts,
the suppressed flag, and the graph feed, so kilobytes rather than
megabytes — under its own key, on the same terms as the scan: same TTL,
same per-viewer key because every number in it went through
`buildConditions($user)`, and Redis being unavailable falls through to
computing it.

| Endpoint | Warm, before | Warm, after |
|---|---|---|
| `viewRelationGraph` | 244.8 ms | **0.5 ms** |
| `viewRelationSettings` | 210.0 ms | **1.1 ms** |
| sum of six | 663.0 ms | **222.4 ms** |

Three times faster over the whole tab, and the remaining 214 ms is the
co-occurrence section reading its own scan — which is the panel that
needs the rows.

`zxzhjlk.artenadigital.com`, the mid-size case: 97.9 ms → **27.2 ms**.

### 18.3 Cold is unchanged, and that is not hidden

Whichever request misses first assembles everything, and on a cold tab
several miss at once because the six fire in parallel. Sequentially the
bench shows 1,009 ms → 712 ms, but that gain is an artefact of the graph
card running before the settings card and leaving the digest behind;
in a browser they race. **What this removes is the repeat, not the first
read.** Nothing bounds the first read except the caps already in §4.

### 18.4 Cached numbers have to say how old they are

§16.7 held the scan and made the section print `Scanned 3 minutes ago`,
on the argument that a cached read which does not disclose its age is a
trap. The same argument now applies to the rail cards, so both print it:
`read 43 seconds ago` on the graph card, `counts read 43 seconds ago` on
the settings card.

**The stamp is the scan's, not the digest's.** The co-occurrence counts
are the oldest thing in the digest, so the honest age to show is the
oldest one — and it means all three panels print the same number, which
they would not if each timed its own write.

The phrasing moved into `Values/View/value_read_age`, used by all three.
It was inline in the co-occurrence template; a second and third copy
would have drifted.

**What this does not fix:** `Scan again` re-requests the co-occurrence
panel only, so the rail cards keep their held numbers until the reader
reloads. That was already true of them as separate fragments, and the age
they now print is what makes it visible rather than silent.

### 18.5 A pre-existing JS error, found while verifying

The page logs `e.target.closest is not a function` three times on this
tab. **It is not from this change** — the same error appears on the
Sightings tab, and it comes from the shipped bundles rather than from
anything the Relationships panels do. Recorded here because it was found
here, and left alone because fixing it belongs to whoever owns that
handler.

---

## 19. A claim now says what it points at, and links to it

§7 shipped a claim as a type, a direction, a target's kind and label, an
author organisation, a date and a distribution. Read against the
instance, two of those turned out to be dead ends: the label named the
far end and went nowhere, and `ADMIN` beside a date did not say which of
the two organisation columns it was.

**What ships: every name in a claim block is now reachable, and the far
end carries a line saying what it actually is.**

### 19.1 The far end, in four kinds and one order

`claimTarget` builds one shape for all four, and the panel draws the
facts in the same order every time — *where it lives, what it is, who
made it* — so six claims pointing at four kinds of thing still read down
one column:

| Kind | Label | The line under it |
|---|---|---|
| `Event` | `#4182 AgentTesla host indicators [2026-06-30]` | `2026-06-30 · unpublished · StoneCo` |
| `Attribute` | `domain · deadnxuyla.ru` | `#4345 Test phishing event for SkillAegis · Network activity · url ↦ domain · ADMIN` |
| `Object` | `domain-ip · #1` | `#1 Test event · network · ADMIN` |
| `GalaxyCluster` | `TAG-53` | `Threat Actor · MISP Project` |

Three of those facts are worth naming individually.

**`unpublished`, and only when it is true.** An unpublished event has not
left this instance, so a claim pointing at one points somewhere nobody
else can follow. The silence in the other direction is what *published*
means, and it needs no word.

**`url ↦ domain` is where in the object, not just which object.** An
attribute's meaning inside an object is its relation, and the object name
alone loses it. Same arrow the analyst-data popover uses for the same
thing.

**The organisation is the target's, not the claim's**, and it is the last
fact for every kind so the two never swap places. On `8.8.8.8` they
differ on every event claim — written by `ADMIN`, pointing at a
`StoneCo` event — which is exactly the pair the old block collapsed into
one unlabelled `ADMIN`.

### 19.2 Where the links go, and the one that is not the obvious one

| Kind | Opens |
|---|---|
| `Event` | `/events/view2/<id>` |
| `Attribute` | `/events/view2/<event_id>#tab-attributes` |
| `Object` | `/events/view2/<event_id>#tab-objects` |
| `GalaxyCluster` | `/galaxy_clusters/view/<id>` |

**Not `/attributes/view` and not `focus:`.** MISP's own analyst-data
popover builds `/events/view/<id>/focus:<uuid>`, and this theme's event
view takes no `focus:` parameter — verified: `view2` never reads it. The
two flat views redirect to the event and lose which record they were
asked about. So the link goes to the event's own tab for that kind, which
is the choice the sightings table already made for the same reason, and
the record the reader wanted is on the link's title:
*"Attribute #3858978, in the Attributes tab of event #4345"*.

Verified by following all 18 links the panel draws on `8.8.8.8`: every
one answers 200, the org links land on `Organisation StoneCo`,
`Organisation ADMIN` and `Organisation CIRCL`, and the two anchors select
the pane they name — `#tab-objects` activates `tab-objects`,
`#tab-attributes` activates `tab-attributes`, and an event link with no
anchor stays on `tab-general`.

### 19.3 A galaxy cluster is resolved now, by this panel

§7 recorded that `Relationship::getRelatedElement` handles Event,
Attribute, Object, Note, Opinion and Relationship and stops there, while
`AnalystData::valid_targets` allows six more — so a cluster target
rendered as a bare UUID with nothing to say and nowhere to go.

`claimClusters` fills that in with one `GalaxyCluster::fetchGalaxyClusters`
for the whole section, keyed on every cluster UUID the claims name. It is
the reader the galaxy pages themselves use, so a cluster the viewer may
not see stays unresolved rather than appearing.

**This does not fix the other five kinds** — `EventReport`, `Galaxy`,
`Note`, `Opinion`, `Organisation`, `SharingGroup` — and it deliberately
does not try. Each needs its own fetch, its own label and its own URL,
and none of them appears on this instance. A kind the panel has no link
and no facts for keeps its UUID and falls through to the unresolved line,
which is the same honest outcome as before, reached explicitly rather
than by accident.

### 19.4 A target this instance cannot show says so

> ⃝? Not held on this instance, or not visible to you — the claim is
> shown by the UUID it names.

The verification instance has exactly one: a `similar-to` claim naming a
galaxy cluster that is not stored here. It used to render as a UUID and
no explanation, which reads as a bug in the panel rather than as a fact
about the data.

**Both reasons, and the panel does not know which.** `getRelatedElement`
and `fetchGalaxyClusters` are both ACL'd, so an empty answer means *gone*
or *not yours* and nothing distinguishes them from here. Naming both is
what §14.6 allows: the claim itself is already visible to this reader,
the UUID is already on it, and the sentence says nothing about whether
the thing it names exists. Guessing at one reason would be the disclosure.

The line is warn-toned rather than muted. It is the one line in a block
that says a fact is missing, and the claim above it is still true.

### 19.5 Two organisations, named

The meta line reads **`Asserted by ADMIN · 2026-08-28 · [distribution]`**.
The two words are new and they earn their place: a second organisation
now appears two lines above, and a bare `ADMIN` beside a date said
nothing about which of the two it was.

`relationships` carries `org_uuid` and `orgc_uuid` — owner and creator —
and on an instance nothing has synced into they are the same row on every
claim, which is true of all six here. So the second name is drawn **only
when the two columns differ**, compared on the uuids rather than on the
two contained names, because that is the comparison the schema stores.
Then it reads `Asserted by CIRCL · held by StoneCo · …`.

That branch was exercised by flipping `org_uuid` on one seeded claim,
capturing it, and reverting — the instance has no synced analyst data to
show it with, and authoring one to host the state would be worse than
borrowing an existing row for a minute.

### 19.6 One blue link per claim

The first cut linked all four names and left the target the muted colour
the unlinked label had. That is backwards twice over: four blue runs per
block read as a page of links rather than as a sentence somebody wrote,
and the one thing the claim is *about* was the only thing that did not
look reachable.

So the target carries the link colour and the other three inherit their
line's — hover and focus underline them and bring the colour up. Same
rule the sightings table applies to its two columns, and the same reason.

`.vp-claim-link` sets its own colour rather than borrowing `.badge` or a
Bootstrap utility, so a stylesheet still cached under an older `?v=`
degrades these to plain link colour and never to invisible — the failure
`03-relationships.md` §21.5 was written for.

### 19.7 What it costs

Measured with the same probe before and after, site admin, first call in
the process:

| Value | Claims | Queries before | Queries after |
|---|---|---|---|
| `8.8.8.8` | 6 | 13 | **15** |
| `deadnxuyla.ru` | 1 | 8 | **8** |
| `443`, `0.0.0.0`, `185.92.180.100`, `1.0.155.105` | 0 | 2 | **2** |
| unknown value | 0 | 1 | **1** |

**Two queries for the section, not two per claim**, and each is skipped
when nothing needs it. `deadnxuyla.ru` is the proof: its one claim points
at an `Attribute`, which arrives with its event's creator organisation
already nested by `Relationship::rearrangeData`, and it names no cluster
— so neither query fires and the count does not move. `8.8.8.8` pays both:
one `fetchGalaxyClusters` because a cluster is named, and one
`Organisation::find('list')` because an `Event` target is fetched with no
contain at all and arrives holding an `orgc_id` and no name.

This amends §5.3's heading. Asserted is **four, plus about one per claim,
plus at most two for the section** — and the per-claim term is still the
one that is not ours.

### 19.8 Verified

- Both themes on the running instance, `8.8.8.8` (six claims, all four
  target kinds, both resolved and unresolved) and `deadnxuyla.ru` (one
  claim, inbound, an attribute that is not inside an object — so the
  `↦` fact is absent rather than empty).
- All 18 links followed: 200 each, correct destinations, both tab
  anchors selecting their pane.
- The relationship-type filter still narrows the new markup: 6 → 2
  (`similar-to`) → 1 (`derived-from`) → 6, with no JS errors.
- The two rail cards still render — `graphFor` reads `target.label` and
  `target.kind`, both of which survive the reshape, and the graph now
  gets a cluster's name wherever one resolves instead of a UUID.

### 19.9 What this pass did not take

**The near end.** A claim is written against one occurrence of the value,
and with 23 occurrences of `8.8.8.8` the panel still does not say which.
`occurrenceUuidsFor` already returns the id, the event and the type per
UUID, so it costs nothing to fetch — it costs a fourth line in a block
that just gained a third.

That, the claim's own UUID, `created` beside `modified`, `authors`, and
the sharing group behind a level-4 distribution are what the follow-up
tooltip should carry. The block says what a reader scanning needs; the
hover is where the rest belongs.

---

## 20. The claim's hover card — §15.1 item 7

> **§21 reshapes this card.** It is now about the target only: the
> `THIS CLAIM` and `WRITTEN AGAINST` sections below are gone, the glyph
> moved beside the target's kind, the card opens to the right, and the
> event gains its tags and clusters. The mechanism §20.5 argues for is
> unchanged, and the sections here are left as written because the
> reasoning that produced them is worth reading beside the correction.

§19.9 named what it kept out of the block and why: a claim had just
gained a third line, and the rest of its record would have made a fourth.
This is the rest of the record, on hover.

### 20.1 Three sections, because a claim has three parts

The card reads down in the order the claim is made of — **what was
asserted, which end of it is ours, what the other end is**:

```
THIS CLAIM
Type         connects-to
Written      2026-08-28 11:59:17
Authors      vp-phase-24-seed
Audience     [All communities]
UUID         2d33e488-0d0c-4d83-8689-47b1eead3dd7

WRITTEN AGAINST
Occurrence   ip-dst · #2117833 in event #3888        ← links
UUID         52f425d7-e045-4ab5-9a03-07893f4bb726

POINTS AT · ATTRIBUTE
Target       domain · deadnxuyla.ru                  ← links
IDS flag     set
Audience     [Inherited]
UUID         a6679030-617a-49e3-a730-75ad44ee9e74
```

**`WRITTEN AGAINST` is the section this was worth building for.** The
panel's footer has said since it shipped that a claim is stored against
an occurrence and not against the value; on `8.8.8.8`, with 23 of them,
nothing on the page said *which*. It costs nothing — `claimFrom` has
already decided which endpoint column holds one of ours in order to
work out the direction, and `occurrenceUuidsFor` already returned the id,
the event and the type for it.

### 20.2 The card lists stored columns

That is the rule, and it settles what goes in. `direction` is the one
thing left out that a reader might expect: it is derived from which
endpoint column matched, not stored, and the chip two lines above
already says it.

The same rule is what admits the rest — `to_ids`, `attribute_count`,
`template_version`, `tag_name`, `analysis` — and what keeps the card from
drifting into commentary. Per kind, beyond what the block already shows:

| Kind | Rows |
|---|---|
| `Event` | Analysis, Attributes, Audience |
| `Attribute` | IDS flag, Comment, Audience |
| `Object` | Template, Comment, Audience |
| `GalaxyCluster` | Tag, Description, Audience |

**`attribute_count` is denormalised and can lag.** It is still the right
number: it is what MISP's own event index prints for the same event, so a
reader who follows the link and counts something else has found a
discrepancy in the instance rather than in this card.

**A cluster's description is clipped at `CLAIM_PROSE_CAP` (180).** It is
the only free text that reaches this section and it runs to paragraphs.
A hover is not a page, and the card links to the one that is.

**An empty column is not a row.** Most attributes carry no comment, and a
`Comment` label with nothing beside it is worse than no label — the
detail list is filtered before it is drawn.

### 20.3 An unresolved target does not say the same thing twice

An unresolved target's *label is* its UUID, so the first cut printed it
as `Target` and again as `UUID` — two rows, one fact. It now gets
`Status  not held here, or not visible to you` and the UUID once.

The section head names the kind in words too: `POINTS AT · GALAXY
CLUSTER`, not the uppercased model name.

### 20.4 A level-4 claim names its group beside the badge

`AnalystData::rearrangeSharingGroup` nests the group on a distribution-4
row — and fetches it when it is not contained, whether this panel reads
the result or not — so the name is free wherever it exists.

The first cut put it on its own row, directly under an `Audience` badge
already reading *Sharing group*: a whole line spent repeating the label
to deliver one word. It now reads `Audience  [Sharing group] Test SG`.

No claim on this instance is level 4, so the row was exercised by
flipping one seeded claim to `distribution = 4, sharing_group_id = 1`,
capturing it, and reverting — the same borrow §19.5 made for `held by`,
and for the same reason.

### 20.5 CSS on hover and focus, not a Bootstrap tooltip

**A `data-bs-toggle="tooltip"` here would never bind.** MISP's only
initialiser is in `mispOvermind.js` and runs once on `DOMContentLoaded`;
this panel arrives later through `loadAjaxContainer`. It would have
failed silently, which is the worst way for it to fail.

So the card is `:hover` and `:focus-within` on a wrapper, with a small
`ⓘ` button as the trigger. That has no lifecycle to get wrong, adds no
JS to a page that already runs plenty, and reaches the keyboard — which a
native `title` does not. Verified: focusing the button shows the card
(`opacity: 1, visibility: visible`), and the button is in the tab order.

**The card takes the pointer**, unlike `.vp-tip` above it in the
stylesheet. That readout is a chart's and must not be hoverable; this one
has two links in it, and a card you cannot move into is a card whose
links do not exist.

**A trigger, not the whole block.** Hovering a claim to open a card would
fire five of them while a reader scans the list.

**Nothing clips it.** Measured on the running instance: no ancestor
between the card and `<body>` sets `overflow` to anything but `visible`,
so opening downward from the last claim in the list is safe. The card is
336 × 351 at the widest content this instance produces.

### 20.6 What it costs

**No queries at all.** Every column it prints was on a row already
fetched. Measured with the same probe as §19.7, and the counts are
identical to that pass: 15 on `8.8.8.8`, 8 on `deadnxuyla.ru`, 2 on
every claimless value, 1 on an unknown one.

**The fragment doubles**, and that is the real cost:

| | Raw | Over the wire |
|---|---|---|
| Before | 23,894 B | 3,993 B |
| After | 45,285 B | 6,048 B |

Six claims, so ~3.6 KB of markup per claim raw and ~340 B gzipped. The
section is never truncated, so this scales with how many claims people
have written on one value: a hundred would be ~380 KB raw, ~34 KB gzipped,
which is well inside what §12.3 measured the co-occurrence table at. It is
recorded rather than capped, because capping *this* section is the one
thing its whole argument forbids.

### 20.7 Verified

- All four target kinds and the unresolved one, in both themes, on
  `8.8.8.8`; the `held by` and sharing-group rows by temporary flip and
  revert.
- Keyboard: the trigger is in the tab order and focus shows the card.
  (An earlier read said otherwise and was wrong — it sampled the
  computed style inside the 0.1 s transition.)
- Six rows, six triggers, six cards; the relationship-type filter still
  narrows 6 → 2 → 6; no JS errors from this panel.
- The `e.target.closest is not a function` error is still logged and is
  still not ours — §18.5.

---

## 21. The card is about the target, not about the claim

§20 built the hover card as a record of the *claim* — its provenance,
the occurrence it was written against, then the far end. Read against
the page, the first two sections were repeating the row: the type, the
author, the date and the audience are all already on the block, and a
reader had to scroll past four facts they had just read to reach the one
they opened the card for.

**The card is now entirely about the far end.** It moved to sit beside
it, `THIS CLAIM` and `WRITTEN AGAINST` are gone, and what it says about
the target grew to fill the space they left.

### 21.1 Two sections, and the second is the event

```
ATTRIBUTE
Category     Network activity
IDS flag     set
Audience     [Inherited]
UUID         a6679030-617a-49e3-a730-75ad44ee9e74

IN EVENT #4345
Info         Test phishing event for SkillAegis
Date         2026-07-08
Analysis     Initial
Attributes   61
Audience     [This community only]
Tags (2)     [adversary:infrastructure-type="c2"] [C2]
Clusters (3) [Account Discovery - T1087] [0kilobypt] [APT-C-27]
UUID         985f91d5-f651-4ffb-9bfa-3924274a6438
```

**An attribute and an object are always inside an event**, and whether a
claim matters usually turns on which event that is — its date, how far
the analysis got, and what it was labelled with. An `Event` target has no
second section, because it *is* the event: the same rows appear once,
under `EVENT`.

**One definition, drawn twice.** `claimEventFacts` builds the event block
for both cases. Two definitions would drift, and the reader would be told
different things about the same event depending on which claim they
happened to hover.

**`Type` is not a row.** The label the card hangs off reads `type ·
value`, so an attribute's `Type` row printed the first half of it back.

### 21.2 Tags and clusters, which are the reason to open it at all

`getRelatedElement` contains `Event` for an attribute and an object
target and fetches the event itself for an event target, so every column
above except the labels was already in hand. The labels were not.

**`claimEventTags` is one `EventTag` find for every event in the
section** — the target events and the parent events together — with the
same `Tag` contain `eventMetadata` above uses.

**Galaxy tags are kept rather than dropped, and that inverts this page's
own habit.** Everywhere else in `ValueProfile` a galaxy tag is filtered
out, because the Tags column does not draw one and a facet on something
invisible is not a facet. Here they are the point: a cluster is what an
analyst reaching for context is looking for.

**A cluster is named, not printed raw.** `misp-galaxy:threat-actor=
"TAG-53"` is a storage key, not a label. The names come from
`fetchGalaxyClusters`, which `claimClusters` was already calling for
cluster *targets* — so the tag names join that call as a second `OR`
branch rather than a second query.

That also keeps the ACL honest: a galaxy tag whose cluster the viewer may
not read resolves to nothing and is dropped. Printing the tag string
instead would have disclosed, in a string, the cluster the instance was
withholding.

**The count is in the label.** `Clusters (6)` — the chip list scrolls
past four wrapped rows, and a reader who cannot see the bottom of it has
no way to know whether they are looking at four labels or forty.

### 21.3 It opens to the right, and the glyph moved to make that safe

Opening downward put the card over the two claims below it, which is the
wrong place to read something *about* the one above. It now opens beside
the glyph.

**Which is why the glyph sits before the target's name, not after it.**
The card's left edge is the glyph's position, and a glyph placed after a
label of unbounded length inherits that label's length: measured, an
event title on `8.8.8.8` pushed the card's right edge to 1031 px and gave
the whole page a horizontal scrollbar below 1024 px. Before the name, the
glyph's x is bounded by the longest kind word, and the widest card on
this instance ends at 742 px in a 900 px viewport.

`Object ⓘ domain-ip · #1` also reads correctly: the glyph is attached to
the kind, and what it opens is that kind's record.

**The 900 px horizontal scrollbar is still there and is not this.**
`.vp-pivot` overflows to 1238 px with nothing hovered at all. Found while
measuring the above, left alone: it belongs to whoever owns that element.

### 21.4 What it costs

| Value | Claims | §20 | Now |
|---|---|---|---|
| `8.8.8.8` | 6 | 15 | **22** |
| `deadnxuyla.ru` | 1 | 8 | **9** |
| `443`, `0.0.0.0`, `185.92.180.100`, `1.0.155.105` | 0 | 2 | **2** |
| unknown value | 0 | 1 | **1** |

**One of those seven is mine.** `deadnxuyla.ru` shows it: its event
carries no galaxy tag, so the whole cost is the one `EventTag` find.

The other six are what `GalaxyCluster::fetchGalaxyClusters` does once it
returns rows rather than nothing — `SharingGroup::authorizedIds`, a
sharing-group fetch with its orgs and servers, an organisation list and a
user setting. §20 called the same method and paid none of it, because the
one cluster UUID on this instance does not resolve and the fetch came
back empty.

**It is per section, not per claim or per cluster**, which is what makes
it acceptable: six claims across five events and eleven clusters cost the
same six. This is the same category as §5.3's note about
`Relationship::afterFind` — *never assume a `find` on one of these models
is one query* — and it is recorded here for the same reason.

**The fragment got smaller**, because two sections left and one grew:
41,280 B raw and 5,899 B gzipped, against §20.6's 45,285 and 6,048.

### 21.5 Verified

- All four target kinds and the unresolved one, both themes, on
  `8.8.8.8`; `deadnxuyla.ru` for an inbound attribute target whose
  parent event has tags but no clusters.
- Right-edge behaviour measured at 1700, 1280, 1024 and 900 px: no card
  overflows at any of them, and the page's own horizontal scroll at
  900 px is the pre-existing `.vp-pivot` one.
- Keyboard: the glyph is in the tab order and focus opens the card.
- Six rows, six glyphs, six cards; the type filter still narrows
  6 → 2 → 6; no JS errors from this panel.
- The model keys the removed sections used — `against`, `uuid`,
  `created`, `authors`, `sharing_group` — went with them rather than
  being left unread. **`authors`, `created` and the claim's own UUID are
  now on no surface of this page**, which is a real subtraction and is
  named here in case it should come back somewhere.

---

## 22. The neighbourhood graph, re-evaluated

§10 turned the rail's static sketch into a real node/edge feed and
un-disabled *Open the full graph*. That closed the one stub on this tab
whose missing piece was data rather than a write path, and none of it is
being taken back here. What is being asked is the next question, and it
is a fair one: now that the picture is real, **does it tell a reader
anything the three panels underneath it do not?**

The answer, as it stands, is no. §22.1 says why in structural terms,
§22.2 names the one edge the page already holds and refuses to draw,
§22.3 lists what a CTI analyst actually wants from a value-centred
graph, and §22.5 and §22.6 split that list into the rail's peek and the
overlay's full read.

### 22.1 The complaint is structural, and it is right

Every edge `graphFor` builds is incident on the centre. `co:*`, `near:*`
and `human:*` all attach to `value` and to nothing else. The result is a
**star**, and a star of degree *n* carries exactly *n* plus its edge
labels — three degree counts and a list of neighbour names.

The three panels below print those same three counts in their headers,
print the same neighbour names in sortable, filterable, copyable rows,
and print six columns per row that the star has nowhere to put. So the
graph's entire information content is a strict subset of the tables',
rendered less precisely. It is a spring-embedded list.

This is not fixable by making the star bigger, prettier, or labelled.
§10.3 already gave the overlay labels and larger nodes, and the overlay
is a labelled star. The test a graph has to pass is narrower than that:

> **A graph earns its pixels only when it draws a relation between two
> things that are neither of them the centre.**

Nothing in the current feed does. Twelve neighbours per notion arranged
around a hub is a table with springs on it, and the honest reading of
the rail's `7 of 31 edges drawn` is that it is a rendering statistic —
it says how much of a list was drawn, not what the drawing found.

### 22.2 The one edge the page already holds and does not draw

The co-occurrence fold keeps, per neighbour value, **the set of event
ids it shares with the centre** (`ValueRelationTool::emptyGroup`,
`$group['events']`). Two neighbours that appear in the same event are
related to each other, and the scan already knows it. No query, no
column, no new model method — the relation is sitting in the digest and
`graphFor` discards it.

The naive use of it is wrong. Projecting the bipartite graph down to
value→value edges gives a complete graph per event: twelve neighbours
from one event is sixty-six edges, and the hairball that results says
*less* than the star does, because a clique carries no shape either.

So **draw the event.**

    value ──▶ event ──▶ neighbour

Two-mode, and `eventRollup` already builds precisely these nodes with
`info`, `date`, `org`, `shared_values`, `distribution` and `tags` on
them. Edge count falls from O(n²) per event to O(n), the layout has real
structure to lay out, and every read in family **A** below falls out of
the topology instead of having to be computed and printed as a number.

Two constraints come with it:

- **An event node is not a value node.** It links to `/events/view/<id>`,
  it has no Value Profile to pivot to, and it must not carry the
  double-click affordance §10.1 gave neighbours. Different shape, and
  the key has to say so.
- **The object is the tighter version of the same idea.** A sibling
  shares an *object*, not just an event — `domain-ip`, `file`,
  `email` — which is a hard relation where the event join is a soft one
  (§3.2). Object nodes belong in the same two-mode picture, drawn
  distinctly, or the graph flattens the one distinction the tab was
  built to keep.

### 22.3 What a CTI analyst wants — the whole list

Twenty-nine items, in five families. Each is stated as the question a
reader arrives with, not as a feature.

**A. The shape of the neighbourhood** *(topology; a table cannot hold
these at all)*

1. **How many distinct clusters is this value in?** One coherent
   infrastructure set, or several unrelated ones that happen to share
   one attribute.
2. **What is the glue?** Which single event — or object — supplies most
   of the neighbourhood. "Thirty-one neighbours" from one report is one
   fact, not thirty-one.
3. **Is this value itself a bridge?** Does it join two clusters that are
   otherwise unconnected. That is the difference between a pivot point
   and a member.
4. **Which *neighbour* is the bridge?** The neighbour attached to two
   otherwise-separate clusters is the highest-value next click on the
   page, and nothing on this tab currently ranks it.
5. **Which neighbours are hubs?** A neighbour that co-occurs with
   everything is usually infrastructure noise — a CDN address, a
   sinkhole, a shared host, an empty-file hash — and should be visible
   as noise rather than rank third in a table.
6. **Which are isolates?** Neighbours reached through exactly one event
   and connected to nothing else. The long tail, and often the freshest
   and most specific indicators in the set.
7. **How redundant is the tie?** Is this value bound to its cluster
   through one event or through several independent ones. One event is
   one report is one source, and a claim resting on it is fragile.
8. **What is two hops out?** Values that never share an event with the
   centre but sit one neighbour away. This is the pivot suggestion a
   table structurally cannot make.
9. **Structural or statistical?** Same object versus same event. A
   file's C2 address and an email's sender are hard relations; sharing
   a 400-attribute event is a weak one.

**B. Provenance and trust**

10. **Which notion is this edge?** Shared event, near-match engine, or a
    human claim — §5's separation. Already carried four ways; keep it.
11. **How many organisations attest to it?** An edge four orgs
    independently reported is not the same object as one org's.
12. **Which part of this rests on a single org?** Colouring by reporter
    exposes the echo chamber — a neighbourhood that looks corroborated
    but is one report copied forward.
13. **Did *we* see this?** Our own org's contribution versus someone
    else's picture we are reading.
14. **What can be re-shared?** Which neighbours are community-visible
    and which are org-only. This governs what can go in the outgoing
    report, and it is a decision made while looking at the graph.
15. **What is not shown?** The per-notion cap, the events skipped by the
    scan budget, the events too large to read (§4.2), and the ACL cut. A
    graph that truncates silently lies more loudly than a table that
    does, because a table looks like a page of a list and a graph looks
    like the whole world.

**C. What it means**

16. **Which actor or campaign?** The galaxy clusters and tags reaching
    this value through its events and its neighbours. *"Sits between an
    APT28 cluster and a commodity loader"* is the single highest-value
    sentence this page could produce.
17. **What is it surrounded by?** A value ringed by hashes is a delivery
    node; ringed by addresses, infrastructure; ringed by e-mail
    attributes, a phishing artefact. Type composition names the role.
18. **Where in the intrusion?** The category mix — Payload delivery,
    Network activity, Persistence — read off the neighbourhood rather
    than off one attribute.
19. **What is it, inside its object?** `domain-ip`'s `ip` versus
    `file`'s `md5` versus `email`'s `from`. The object template names
    the role the value plays, and the sibling section already has it.
20. **Is any of this known-benign?** Warninglist hits among the
    neighbours, de-emphasised on the canvas rather than silently ranked
    into third place.
21. **Does anyone outside this instance agree?** Neighbours that also
    appear in the feeds and servers this instance caches (§17).

**D. Time**

22. **How old is each relation?** A 2019 co-occurrence and a last-week
    one should not draw identically.
23. **Did this form in a burst or accrete?** A neighbourhood that
    appeared inside one week is a campaign. One that grew over four
    years is long-lived infrastructure, or benign.
24. **Is it still live?** The date of the newest edge, read without
    sorting a column.

**E. Doing something with it**

25. **Pivot** to a neighbour's own Value Profile. *(Exists — §10.1.)*
26. **Expand a neighbour one hop, in place**, without losing the
    current canvas. This is what turns a picture into an investigation,
    and it is what makes item 8 reachable.
27. **Focus and hide** — drop a notion, drop a hub, re-layout on what is
    left. The hairball is survivable if the reader can subtract.
28. **Trace** — pick two nodes, show the path between them and the
    events along it.
29. **Hand a selection to a search.** Not a write, so it does not touch
    §14's rule that every write control on this page stays disabled.

### 22.4 What only the graph can say

Several items above are already answered elsewhere on the tab, and the
brief should say so rather than claim them: item 2 is the event
roll-up's whole purpose, 10 is the three panels, 11/13/14 are table
columns, 17/18 are facets, 22 is the `Last together` column, and 15 is
the cap sentences.

Strip those out and what is left is the actual justification for keeping
a canvas on this tab at all:

> **1, 3, 4, 5, 6, 7, 8, 9, 12, 16, 23, 26.**

Twelve reads, every one of which is about a relation between two things
that are not the centre, or about the shape of the whole. None of them
can be put in a column. **This is the list the graph exists to serve**,
and the current star serves none of them.

### 22.5 Section one — the rail's peek

Measured constraints, from §10.3: a 340 px column, no labels (thirty-seven
of them overlap into illegibility), lazily loaded, no queries of its own
beyond the digest, and it shares the rail with the settings card. What a
reader can take from a picture that size, without text, is **the number
of blobs, their relative size, colour mass, and one highlighted node.**

That is enough for five of the twelve, and the rail should carry exactly
those and stop there:

| # | Read | How it renders at 340 px |
|---|---|---|
| 1 | How many clusters | separated blobs |
| 2 | The glue | one visibly dominant hub node |
| 5 | Hubs | a node most edges pass through |
| 6 | Isolates | single-edge nodes on the rim |
| 10 | Notion mix | colour mass, already there |
| 15 | The cut | one line of text, already there |

And one thing that is new, and is the most valuable single change in
this section:

**The rail states its finding in words.** The sub-line stops being
`7 of 31 edges drawn` — a fact about the renderer — and becomes a
sentence about the neighbourhood, computed in PHP beside the feed so it
cannot disagree with the panels below:

    One cluster · event 4471 supplies 18 of its 21 values
    Three clusters · 3.94.98.10 is the only value in two of them
    21 values, no shared structure — each from its own event
    Nothing to draw — none of this value's events could be read

A picture at 340 px is a claim the reader cannot check. The sentence is
what makes the picture trustworthy, and on the days when the graph has
nothing to say the sentence still does. It is also the honest test of
whether the redesign worked: if no sentence can be written from the
topology, the topology was not worth drawing.

Explicitly **not** in the rail: labels, time, organisations, tags,
clusters, warninglists, and every action. Each needs either text the
column cannot fit or a visual channel already spent on the three
notions.

### 22.6 Section two — the full graph

The overlay is where the other twenty-four live. Staged, because the
order matters more than the list:

**Tier A — makes it a graph rather than a table with springs.**
Items **1–9** and **15**. The two-mode `value → event → neighbour`
model of §22.2, with objects as the tighter second mode; siblings drawn
as their own hard edge kind rather than folded into co-occurrence; hub
and bridge nodes marked as such; and the cut stated **on the canvas**,
not only in the panel that opened it.

This tier is the whole of §22.4's justification list except 12, 16, 23
and 26. Without it the overlay stays a labelled star and none of the
rest is worth building.

**Tier B — makes it CTI rather than topology.**
Items **11–14**, **16–24**. Colour by galaxy cluster or tag rather than
by notion, as a switchable mode; a time brush over the edges, which the
page already has the vocabulary for from §21's chart work; edge weight
or opacity by attesting organisation count; warninglist hits knocked
back; type glyphs on nodes; a marker for neighbours corroborated in the
feed caches.

The rule for this tier is **one channel at a time**. Colour is already
spent on the three notions; a cluster-colour mode has to *replace* it
and say so in the key, not fight it.

**Tier C — makes it a workbench.**
Items **25–29**. Expand-one-hop is the large one and the one that makes
item 8 real; it is also the only item in the whole list that needs a new
endpoint. Focus/hide is cheap and buys back the hairball. Path trace and
hand-off-to-search are the smallest and can wait.

pivotick's **edge layers** are what Tier B's mode switching and Tier C's
focus want, and **they shipped on 2026-08-28** — along with a filtering
legend, a data dock and rim badges that between them cover six more of
these items. §23 reads the release against this list; what is left after
it is Tier A, which no library was ever going to supply.

### 22.7 What each of these costs

The finding that matters for planning: **twenty-one of the twenty-nine
need no new query.** The digest already holds them and `graphFor`
discards them.

**Free from the held scan** — 1, 2, 3, 4, 5, 6, 7, 9, 10, 11, 12, 13,
14, 15, 16, 17, 18, 19, 22, 23, 24. Every one is derivable from
`$group['events']`, `$group['orgs']`, `$group['tags']`,
`$group['types']`, `$group['categories']`, `$group['distributions']`,
`$group['last']`, the sibling section, and `event_meta` — all of which
§18's digest already computes and holds for five minutes.

**Cheap, one new read** — 20. `Warninglist` already carries
`attachWarninglistToAttributes()`, which takes the neighbour rows by
reference; one call per render.

**Expensive, and already named** — 21 (external presence per neighbour
is one remote fetch per event, which is §15.1 item 2's still-open
sub-item and waits on the same thing).

**A new endpoint** — 8 and 26. Expanding a node is another relation scan
for that value, which is exactly what `relationScan` already does and
already caches per user per value. The cost is bounded and the cache is
written; what is missing is the route and the client-side merge.

**Client-side only** — 27, 28, 29. **Already shipping** — 25.

Cluster detection for the rail's sentence is a connected-components pass
over the two-mode graph, O(V+E), no library, computed in PHP so the
sentence and the panels agree — and **no library is not a shortcut**:
pivotick 1.6 computes no communities either (§23.7), so this pass is
ours whichever bundle is served.

> **§23 revises the client half of this costing, not the server half.**
> Every query cost above stands — a library release cannot change what
> the digest holds. What it changes is the *build* cost of items 5, 6,
> 10, 11, 14, 16, 17, 20, 21, 26, 27 and 29, which 1.6 turns from code
> into configuration (§23.2), against the price of one bundle swap
> (§23.8).

### 22.8 What this does not solve

- **The hairball is deferred, not removed.** Two-mode drawing avoids the
  per-event clique, but a value in forty events still draws forty event
  nodes. The cap has to move from twelve-per-notion to something the
  topology chooses — highest-degree events first, and the sentence in
  §22.5 has to say what that left out.
- **The overlay needs its own cap sentence.** The rail has one. The
  overlay currently inherits the same twelve and says nothing.
- **`GRAPH_NODE_CAP = 12` is a rendering constant standing in for an
  analytic one.** Twelve arbitrary neighbours cannot show a bridge if
  the bridge ranked thirteenth. Whatever ranks nodes for the graph has
  to rank them by structural role, not by the table's `shared_events`
  order — which is the one place where the graph and the table below it
  should legitimately disagree.
- **None of this makes the correlation engine say more.** §3 still
  stands: the engine has nothing to say about a value's neighbours, and
  every edge here remains a join over attributes the page can already
  see.

---

## 23. pivotick 1.6.0 shipped, and it moves most of §22

§22 was written against the bundle MISP ships and against §15.1 item 4,
which recorded edge layers as an upstream ask. **1.6.0 was released on
2026-08-28** and it is a much larger release than that item anticipated.
This section reads it against §22's list.

**MISP is on a pre-1.6 build.** `app/webroot/js/pivotick.iife.js` is
559,576 B dated 24 Aug; `~/git/pivotick/dist/pivotick.iife.js` is
775,624 B dated the release day, and `package.json` reads `1.6.0`.
Checked by marker rather than by date, per the standing rule that `dist`
can lag:

| Marker | MISP | 1.6 dist |
|---|---|---|
| `edgeTypeAccessor` · `edgeStyleMap` · `edgeFacets` | 0 | present |
| `layerVisible` · `visibleIgnoringLayer` · `setEdgeFilter` | 0 | present |
| `hideDisconnected` | 0 | present |
| `addDockTab` · `tableColumns` | 0 | present |
| `minimap` · `getContentBounds` · `setViewport` | 0 | present |
| `setLegend` · `legendToggle` | 0 | present |
| `badges` · `onBadgeClick` | 0 | present |
| `setBorderBox` · `getBorderDistance` | 0 | present |
| `asyncContent` · `textTruncate` | 0 | present |
| `selectElements` · `addToSelection` | 0 | present |

Not one of them is in the copy MISP serves. Everything below therefore
costs a bundle swap first, which is §23.8.

### 23.1 §15.1 item 4 is no longer an upstream ask

Edge layers shipped, and they arrived as three cooperating pieces rather
than the one the follow-up named:

    render.edgeTypeAccessor   name the kind
    render.edgeStyleMap       style it apart
    UI.filter.edgeFacets      switch it off

`docs/edge-layers.md` opens with `reference` / `correlation` / `sighting`
as its worked example, so the vocabulary the feature was designed
against is already ours.

The property that matters for this tab is stated as the reason the
feature exists: **switching a layer off does not move the graph.**
Layout, selection and camera come back bit-for-bit identical, because
the link force gates on the new `Edge.visibleIgnoringLayer` rather than
on `visible` — an edge whose layer is off goes on pulling its endpoints
together. That is precisely what §5's three notions want: three lenses
over one neighbourhood, not three different pictures.

It also retires a hand-rolled workaround. `ValueProfile::graphEdge`
carries a per-edge style nested under `edge` because the shipped build
silently ignores a flat one (§10.2); under 1.6 the three notions are a
declaration — one accessor, one map of three entries — and the
per-edge style object goes away.

### 23.2 What 1.6 hands us, item by item

Against §22.3's numbering. *Ours* is what still has to be built on the
MISP side after the swap.

| # | §22 read | 1.6 gives | Ours |
|---|---|---|---|
| 5 | hub neighbours | dock `Degree` / `degreeIn` / `degreeOut`, sortable | mark hubs on the canvas |
| 6 | isolates | `UI.filter.hideDisconnected`, dock `Visibility` | — |
| 10 | notion separation | edge layers + legend section | the accessor |
| 11 | corroboration | `EdgeStyle.strokeWidth` per kind | the org count |
| 14 | distribution | a second legend section | the key |
| 16 | actor / campaign | `NodeStyle.badges`, or a legend section | the cluster read |
| 17 | type composition | `nodeTypeAccessor` + auto legend with counts | — |
| 20 | known-benign | `badges` — a channel nothing else is spending | the lookup |
| 21 | external corroboration | `badges` | the fetch (still §15.1 item 2) |
| 26 | expand one hop | async content hooks + `RenderContext` | the endpoint |
| 27 | focus and hide | legend click, `Alt`-click show-only, layer toggles | — |
| 29 | hand off a selection | shared dock selection, CSV / JSON export | the search URL |

**Twelve of the twenty-nine move**, and it is worth being exact about
how far. Three come over whole — 6, 17 and 27 need nothing from us at
all. The other nine come over in halves: 1.6 supplies the rendering and
the control, and the derivation behind them stays ours — the library
draws a badge, it does not know a warninglist; it stacks a legend
section, it does not know which organisations attested.

Tier A — the two-mode feed — is untouched by the release and is still
entirely ours (§23.7).

### 23.3 The legend is the key we already hand-drew, and it filters

`03-relationships.md` §9 specifies a key that "draws line samples rather
than squares, so it teaches the edge styles as well as the colours", and
the `.ctp` hand-draws it. `UI.legend` **is** that key: a swatch, a label
and a count per category, rows that are real buttons carrying
`aria-pressed`, and a hidden row drawn with a hollow swatch so colour is
never the only signal — which is §13's greyscale check, met by the
library. A section taking `scope: 'edge'` lists the kinds with a **line**
swatch showing stroke colour, dash and marker as the renderer resolved
them.

**Clicking a row hides that category**, which makes the key the fastest
filter on the canvas, and a toggle writes to `graph.queryEngine` rather
than being a parallel mechanism — so it composes with everything else
instead of fighting it.

`sections` is the part that fits this tab exactly. The changelog's own
motivating case is *"a graph that encodes kind in the fill, provenance in
an enclosure and sharing in the stroke needs three keys, not one"* —
which is this tab's notion / organisation / distribution problem in the
release notes' own words. Each section is independent: its own entries,
counts, fold state and filter, and switching `attribute` off in one and
`self` off in another leaves the nodes that are neither.

One caution the docs are explicit about: **the legend can only sample
colour.** A section keyed on a dimension the colours do not encode takes
the first node's colour and warns. A provenance or distribution section
here has to declare its own `entries` with `color`.

### 23.4 The data dock reframes the complaint that opened §22

§22.1 argued the graph loses to the tables below it. 1.6's answer is
that this was the wrong contest: `UI.table` splits a dock off the bottom
of the canvas and states the intended loop as **table to find, canvas to
understand, sidebar to read**. The reasoning is the same as §22.1's — *"a
force layout is structurally bad at three things people do constantly:
reading exact values, selecting at scale, and working out where to
start"*.

Two things in it are worth this tab specifically:

- **`Apply to graph`** pushes the dock's column filters onto the canvas,
  which is the bridge between narrowing what you *read* and narrowing
  what is *drawn*. It lands as a single filter under a reserved key, so
  it composes with the panel rather than clobbering it.
- **The `Visibility` gutter lists the whole graph, not the canvas** —
  `visible`, `filtered`, `excluded`, `endpoint`, `nested`. §22.3 item 15
  asked for the cut to be stated rather than implied; the dock states it
  per row, which is stronger than the sentence §22.5 proposes and does
  not replace it.

**The dock is `full` mode only**, which is the next section.

### 23.5 §10.4 is half re-opened

§10.4 kept both graphs in `viewer` because `light` and `full` carry the
main header, and that header carries **Edit Graph** and **Notes** — two
affordances that mutate the canvas and write nothing to MISP. The
sentence it closed on was *"the honest way to keep one disabled is not to
offer it."*

**1.6 provides exactly that verb.** `editors.<editor>.enabled: false`
*removes* an affordance rather than vetoing it, and the documentation
gives the same reason §10.4 did — a veto is the wrong answer when an
operation is never allowed:

| Flag | Removes |
|---|---|
| `editors.deletion.enabled` | bulk **Delete**, node / edge / note delete entries |
| `editors.nodeCreator.enabled` | **Create ▸ Add node**, canvas **Add Node Here** |
| `editors.nodeEditor.enabled` | **Create ▸ Edit node** |
| `editors.edgeEditor.enabled` | the edge menu's **Edit Edge** |

So the editing half of §10.4's objection is answerable by configuration.

**The Notes half is not.** `src/interfaces/GraphUI.ts` carries no notes
switch — `note` appears there only in `editors.deletion`'s comment about
which delete entries it removes — so `full` mode still mounts a
note-authoring affordance on a page where §14 holds every write control
disabled. The two honest readings are: stay in `viewer` and forgo the
dock, the sidebar and the legend's home; or ask upstream for
`editors.notes.enabled`.

**That ask replaces §15.1 item 4 as this tab's `../pivotick` `prd/`
item**, and it is very much smaller than the one it replaces.

### 23.6 Three of §10.2's six findings are closed

§10.2 recorded six things that had to be found by reading the bundle.
Against 1.6:

- **`styleCb` on a default style block is finally called.**
  `render.defaultNodeStyle`, `defaultEdgeStyle` and `defaultLabelStyle`
  each accepted one that no drawer ever invoked. Fixed, with the
  precedence documented as specificity ordering rather than
  callback-beats-static.
- **The label budget is no longer six characters.** `textTruncate: false`
  draws the whole label, on the themed pill a floated label already uses.
  §10.2's `textVerticalShift` workaround — a 2.5× budget, about sixteen
  characters in the overlay — is not needed under 1.6.
- **`diamond` is reachable.** `StandardShape` is still
  `circle | square | triangle | hexagon | none`, so it is not a fourth
  standard shape; but `CustomNodeShape` takes an SVG path `d`, so a
  fourth notion can have a shape of its own without the
  `getBBox is not a function` throw §10.2 hit. `shape: 'none'` is new
  beside it, and makes an HTML card the node itself.

Still the caller's problem, unchanged: **`render.type` must be `'svg'`**
for the full style vocabulary — the canvas renderer gained
`edgeStyleMap` in 1.6 but still resolves only four of the nine
`EdgeStyle` properties — and **a `d-none` container is still 0×0**, so
the stage still has to be revealed before the constructor runs.

### 23.7 What 1.6 does *not* give, and §22 still has to build

**No community detection.** Nothing in the library computes clusters —
no Louvain, no modularity, no connected components exposed as data. Its
"clusters" are declared parent/child groupings, not found ones. So
§22.7's costing stands unchanged: the cluster count, the bridge, the
hub-by-structural-role and the rail's verdict sentence are a
connected-components pass in **PHP**, over the two-mode graph, computed
beside the feed so the sentence and the panels agree.

**The two-mode feed is untouched by the release.** `value → event →
neighbour` (§22.2), the ranking that decides which nodes survive the cap,
and every number the sentence prints remain ours. 1.6 supplies the
*rendering* and the *controls*; the *reading* does not come in a bundle.

Worth naming so it is not mistaken for the answer: pivotick has an
**`egoTree`** layout, which *"builds a star from the root's own
neighbours"*. That is the shape §22.1 objects to, with a name. Adopting
it would make the current graph official rather than better.

### 23.8 What the swap costs

One bundle, and the **event Pivot Explorer re-verified against it** —
which is the same condition §15.1 item 4 attached, and it has not got
cheaper.

Breaking changes a consumer can trip on: `PHYSICS_PRESETS.default` is
gone, `PhysicsKnobs` widened from four fields to six, the
`PHYSICS_KNOB_RANGES.linkDistance` ceiling moved to 600,
`toggleView()` / `isViewActive()` are deprecated in favour of
`toggleFlyout(mode)`, and the flyout CSS hooks renamed from
`.pvt-viewflyout-*` to `.pvt-flyout-*`. `UI.modeRail` is removed, but on
`Unreleased` rather than in 1.6.0.

**Value Profile's own graph reads none of them.** It constructs in
`viewer` with a feed, a node style map and a per-edge style object, and
touches no physics preset, no flyout and no CSS hook of the library's.
The exposure of the swap is the event Pivot Explorer's, not this tab's —
which is an argument for doing the swap on this tab's schedule and
paying the re-verification once.

---

## 24. §22 measured, and most of it does not survive

§22 is entirely reasoned. It argues the graph should stop being a star,
become two-mode — `value → event → neighbour` — and then read clusters,
bridges, hubs and isolates off the topology. §22.4 names twelve reads
"only the graph can serve" and rests the whole redesign on them.

This section measures that against the instance, before any of it is
built. **Most of it does not survive.**

### 24.1 What was run

`24-graph-topology-probe.php`, a scratch shell that calls the tab's own
private `relationScan` by reflection — so it measures exactly the rows
the panel reads, not a re-derivation — folds them into
`value → set(event)`, and computes the two-mode topology at four cuts:
the shipped `GRAPH_NODE_CAP` of 12, then 30, 60, and uncapped.

Components are computed **over the neighbours alone**. The centre
touches every neighbour by construction, so a graph including it is
always one component and the number would say nothing; what the rail's
proposed sentence wants to report is whether the neighbours hang
together once the value they all share is taken out.

Three populations, 2026-08-31, as the site admin:

- **§12.1's six**, the tab's own verification values.
- **Eighteen sampled by attribute id** — which turned out to be
  contaminated: the walk struck a run of domains all belonging to one
  event, so most rows are the same measurement repeated. Recorded as an
  artifact, not a finding, and not counted below.
- **Ten values occurring in 8–15 events** — the graph's *best case*,
  and the only population where a topology can exist at all. A
  single-event value is a star by construction and proves nothing
  either way.

### 24.2 The best case, at the sizes a canvas can draw

| Value | Events | Neighbours | Components @ 12 / 30 / 60 | Bridges | Isolates |
|---|---|---|---|---|---|
| `31.57.216.28` | 15 | 14,254 | 2 / 2 / 4 | 0 | 0 / 0 / 1 |
| `45.9.74.70` | 13 | 5,020 | 1 / 1 / 1 | 0 | 0 |
| `75.2.11.125` | 12 | 8,860 | 3 / 3 / 3 | 0 | 0 |
| `216.9.225.163` | 11 | 8,299 | 1 / 1 / 1 | 0 | 0 |
| `1536` | 10 | 1,619 | 1 / 1 / 1 | 0 | 0 |
| `84.46.239.239` | 10 | 7,637 | 1 / 1 / 1 | 0 | 0 |
| `120.24.210.164` | 9 | 6,616 | 1 / 1 / 1 | 0 | 0 |
| `37/68` | 9 | 11,858 | 1 / 1 / 1 | 0 | 0 |
| `…r2.dev/captcha-verify…` | 9 | 4,132 | 1 / 1 / 1 | 0 | 0 |
| `23.247.130.245` | 8 | 8,684 | 1 / 2 / 2 | 0 | 0 |

And §12.1's six: `8.8.8.8` 1/1/1, `443` 1/2/1, `0.0.0.0` 2/2/2,
`185.92.180.100` 1/1/1 (one event, 100 % glue — a pure star, honestly
drawn). **`1.0.155.105` and `github.com` produce no neighbours at all**
— their only event is oversized, so the scan reads nothing and there is
nothing to draw. That is two of the tab's six verification values where
the graph is empty.

### 24.3 Bridges: one, across sixteen values and four cuts

**Item 4 — "which neighbour is the bridge, the highest-value next click
on the page" — fires once in the entire measurement**: `443` at cap 30.
Everywhere else, at every cut, the count is zero.

It was one of §22.4's twelve, and §22.6 called it the thing Tier A
exists to surface. The data says there is nothing to surface.

**Item 6 — isolates — is zero in every case but one** (`31.57.216.28`
at cap 60, a single node). Also dead.

### 24.4 Components: one clump and crumbs

At drawable caps the answer is 1 in eleven of sixteen values, and where
it is 2–4 the extra components are remainders, not clusters: largest 10
of 12, 28 of 30, 33 of 60. So **the rail's proposed verdict sentence
would read "One cluster" on almost every value on this instance** — a
sentence that is true, cheap to compute, and says nothing a reader did
not assume.

Item 1 was the headline of §22.5. It is answerable, and the answer is
almost always the same one.

### 24.5 The scale the panel is not telling the truth about

This is the one finding that is worse than §22 thought rather than
better. Neighbour counts on real values:

    1,619 · 4,132 · 5,020 · 6,616 · 7,637 · 8,299 · 8,684
    8,860 · 10,024 · 10,647 · 11,858 · 14,254 · 18,859

`03-relationships.md` §9 quotes the sub-line as **`7 of 31 edges
drawn`**, and an earlier draft of this section called that a live
defect. **It is not, and the correction matters.** `7 of 31` is fixture
output. On live data the denominator is
`relationSummary()['correlations']` — `distinct_values + near` — which
is the true size of the neighbourhood the fold saw, and the numerator is
the real edge count. The shipped panel prints an honest ratio.

What it honestly prints, on `8.8.8.8`, is **36 of 10,024**. On
`443`, 36 of 18,859. The canvas draws **a third of one per cent of the
neighbourhood it is captioned as summarising.**

So there is no defect here to fix. There is something worse: a caption
that is accurate, and whose accuracy is the argument against the thing
it captions. A reader who reads it carefully learns that the picture
above it is a sample of 0.4 %, chosen by a ranking (§24.6) that is not
the one they would have chosen.

### 24.6 The cap ranking does not just lose nodes, it destroys the structure

§22.8 recorded that ranking by `shared_events` "cannot show a bridge if
the bridge ranked thirteenth" and left the fix undefined. The
measurement is sharper than that.

`216.9.225.163` occurs in **11 events**. At cap 12, 30 and 60 the graph
draws **one event** and twelve, thirty, sixty neighbours with
`multi-event 0`. Same for `84.46.239.239` (10 events → 1 drawn) and
`120.24.210.164` (9 → 1). Ranking by shared events picks the neighbours
of the single largest event and nothing else, so a value with a genuine
multi-event spread is rendered as a single-event star.

Uncapped, those three read **components 11, 10 and 9 against 11, 10 and
9 events** — one component per event, `multi-event 0`. Which is the
next finding, and the one that explains all of the others.

### 24.7 Why: events do not share attributes

For a large share of values the events are **disjoint in their
neighbour sets**. Components equals event count, and no neighbour
belongs to two events. The two-mode graph is then a set of disjoint
stars sharing only the centre — a star of stars, with no cross-link
anywhere for a bridge to be.

That is not a property of the drawing, the cap or the ranking. It is a
property of the data: **MISP events are largely self-contained, and
co-occurrence through an event therefore recovers the event, not a
cluster.** The "clusters" the graph would find *are the events*, which
the co-occurrence panel's event roll-up already lists in a sortable
table with `info`, `date`, `org` and `shared_values` on it.

§3 found that the correlation engine has nothing to say about a value's
neighbours. This is the same shape of finding one level out: the event
join has nothing to say about a value's *structure*.

### 24.8 What does survive

- **The two-mode edge count is real.** 10,100 against 26,975,914
  projected on `8.8.8.8`; 12,079 against 29,453,286 on `37/68`. §22.2's
  arithmetic holds — it is simply an efficiency argument for a picture
  that has little to show.
- **The glue percentage is a genuine finding, and it is a sentence.**
  "One event supplies 100 % of what is drawn" is true on nine of the
  sixteen at cap 12, and it is worth telling a reader. It needs no
  canvas.
- **Item 9 — structural versus statistical — is untouched by this.**
  Object siblings are a join on `object_id`, not on the event, and they
  remain the one co-occurrence notion with semantics behind it. Nothing
  measured here weakens them.
- **Items 8, 12, 16, 23 and 26** — two-hop reach, the single-org
  subgraph, the actor read, burst-versus-accretion and expand-one-hop —
  were **not** tested. They need the expand endpoint or a different
  probe. Nothing here says they are dead; nothing here says they are
  alive.
- **The `full`-mode and bundle-swap questions (§23) are unaffected.**
  They are about what the library offers, not about what the data holds.

### 24.9 What this does to §22

Six of §22.4's twelve — items 1, 3, 4, 5, 6 and 7 — are the topology
reads, and the measurement kills or empties all six on this instance.
That is the list §22 rested the redesign on, so **Tier A, the tier §22.6
says everything else depends on, has no structure to find.**

The diagnosis in §22.1 stands unchanged: the current graph is a star and
a star carries nothing the panels do not print. What does not stand is
§22's cure. Rebuilding it two-mode would produce a picture that is
better-founded, cheaper in edges, and still substantially a star —
because on this data, that is what the neighbourhood is.

Three readings are open, and this document does not pick between them:

1. **Drop the canvas from the rail**, keep the sentence, and spend the
   space on the glue percentage and the honest scale. Cheapest, and
   the measurement supports it.
2. **Keep a canvas only in the overlay**, built two-mode, and justify it
   on the untested items (8, 12, 16, 23, 26) rather than on topology —
   which means testing those first.
3. **Build it as designed anyway**, on the argument that this instance's
   data is not every instance's. Defensible, but it should be said out
   loud rather than assumed, because nothing here is instance-specific
   in an obvious way — self-contained events are how MISP is used.

**Independent of all three: §24.5's ratio is *correct* on the shipped
page, and what it correctly reports is 36 of 10,024.** There is no
quick fix to take instead of a decision.

---

## 25. Three pivots that actually pay, and what they change

§24 measured co-occurrence *through events* and found it flat: one
component, no bridges, no isolates. This section asks the opposite
question — not "what does the neighbourhood look like" but **"what
does an analyst learn by walking from one value to the next"** — and
answers it with three chains traced end to end on this instance.

All three work. And tracing them turns up the reason §24 came back
empty, which is the more important finding: **§24 measured the wrong
relation.**

### 25.1 Example one — a hostname's resolution history

**Start:** `draculax.myq-see.com`
**Event 1416** — CIRCL, 2021-03-31, *"OSINT — Cheating the cheater: How
adversaries are using backdoored video game cheat engines and modding
tools"*, `tlp:white`, `type:OSINT`.

Five dated resolutions, from `passive-dns` objects:

| Resolved to | First seen | Last seen |
|---|---|---|
| `141.255.159.82` | 2017-04-11 22:13 | 2017-04-11 22:13 |
| `168.181.48.248` | 2017-04-14 20:25 | 2017-04-14 20:26 |
| `168.181.51.45` | 2017-04-18 01:03 | 2017-04-18 01:13 |
| `141.255.147.117` | 2017-04-25 10:38 | 2017-04-25 10:38 |
| `200.101.151.150` | 2021-03-30 16:00 | 2021-03-30 16:00 |

**What an analyst reads off it:** four addresses in fourteen days in
April 2017 — infrastructure churn, and two of the four sit in the same
`/16` pair, which is a hosting choice rather than a coincidence — then
nothing for four years, then a single Brazilian host the day before the
report. Dormant-then-reactivated is a different story from
continuously-live, and it is not visible from any one attribute.

**The sibling:** `dracula4000.duckdns.org` → `179.253.227.97`
(2021-01-24) sits in the same event. A naming convention, and therefore
a second thing to hunt.

**The catch, and it matters:** every one of those six IPs occurs in
**exactly one event, one row**. As neighbours they are dead ends. So
the value of this pivot is entirely in the *history* — the dates on the
edge — and not at all in the neighbourhood the IP leads to. A graph
drawn to show "what is near this hostname" would draw six dead ends and
call it a picture; the timeline is the artefact that carries the
insight.

### 25.2 Example two — one address, and the target list falls out

**Start:** `45.77.250.80`
**Event 1179** — CIRCL, 2018-06-26, *"OSINT — RedAlpha: New Campaigns
Discovered Targeting the Tibetan Community"*, tagged
`misp-galaxy:threat-actor="RedAlpha"`, `misp-galaxy:rat="NJRat"`,
`misp-galaxy:tool="njRAT"`, `misp-galaxy:sector="NGO"`, `tlp:white`.

**One hop** — the `domain-ip` objects that address sits in — gives 22
domains:

    apple. · artvoice. · blog.tibetcul. · business. · cfr. ·
    chinaaid. · doc. · docs. · epochtimes. · item. · ndtv. · oc. ·
    rediff. · savetibet. · thewire. · tibet. · tootopia. · video. ·
    vot. · www.apple. · www.doc.        [all *.internetdocss.com]

plus `my.anti-spammail.services`.

**What an analyst reads off it:** the subdomain list *is* the targeting
picture. Tibetan civil society (`savetibet`, `tibet`, `vot` — Voice of
Tibet, `blog.tibetcul`, `chinaaid`), diaspora and India-facing news
(`epochtimes`, `ndtv`, `rediff`, `thewire`, `artvoice`), a US foreign
policy institution (`cfr`), and a credential-harvest lure
(`apple`, `docs`). One pivot from a bare IP produces a victim profile, a
named actor and a tool — without opening the report.

**This is the strongest of the three**, and note what carries it: not
the topology, but the *labels on the far end*. Twenty-two nodes in a
fan, which §22.1 would correctly call a star — and the star is fine
here, because the reading is the list, not the shape.

### 25.3 Example three — an address that bridges two impersonated brands

**Start:** any one of `luxtrust.support`, `cns-lu.com`, `ccss-public.com`
**Event 1507** — CIRCL, 2023-12-19, *"Phishing targeting Luxembourg
services (hosted and served on/from AWS)"*.

Pivot to the IP, and the IP carries names from **more than one brand**:

| Address | Names on it |
|---|---|
| `18.117.184.102` | `ccss-public.com` · `cns-lu.com` · `luxtrust.help` · `luxtrust.support` |
| `3.71.1.255` | `ccss-lu.eu` · `cns-public.eu` · `www-cns-lu.com` |
| `35.177.103.239` | `luxtrust.co` · `tango-lu.com` · `www-cns-lu.com` |
| `13.48.203.238` | `luxtrust-cancel.com` · `www-cns.com` |
| `35.180.136.109` | `ccss-sante-lu.com` · `luxtrust-unlock.com` |
| `54.93.211.218` | `luxtrust.co` · `www-cns-lu.com` |

**What an analyst reads off it:** CNS and CCSS are the Luxembourg
national health bodies, LuxTrust is the national eID, and `tango-lu` is
a telco. Standing on `luxtrust.support` you see a LuxTrust phishing
domain. Standing on `18.117.184.102` you see that **the same operator is
impersonating the health service and the eID from one host** — and
`35.177.103.239` adds the telco. That is a claim about the campaign that
no single domain's page can make, and it is exactly §22.3's item 4, *the
neighbour that bridges two otherwise-separate clusters.*

**§24 said that read never fires. Here it fires six times in one event.**

### 25.4 Why §24 missed all of it

Every address in §25.3 occurs in **exactly one event — 1507**. Checked:
seven of seven. So co-occurrence *through events* puts the entire
campaign in one component and reports "one cluster", which is what §24
measured and what §24 correctly reported.

The structure is not between events. **It is inside objects.**

    §24 measured:   value ── shares an event ── value      flat
    the pivots use: value ── shares an object ── value      typed

A `passive-dns` object exists to assert *this name resolved to this
address, between these dates*. A `domain-ip` object exists to assert
*this domain is on this address*. These are **relational objects** —
their whole purpose is to link two values — and the link carries a type
and often a pair of timestamps. An event, by contrast, is a container:
sharing one means almost nothing, which is precisely what §24 found.

§22's item 9 — *structural versus statistical, same object versus same
event* — was the one item §24.8 left standing. It is not a survivor. **It
is the whole thing**, and §22 ranked it ninth.

**Scale on this instance:** 568,606 attributes sit in objects against
2,216,345 that do not, across 66,587 objects holding two or more
attributes. So the object-mediated graph is real and substantial — and
it covers roughly a fifth of attributes, which is a bound the design has
to state rather than discover.

### 25.5 Relational objects and descriptive ones

The distinction the design needs, and neither §22 nor the tab brief
draws it:

- **Relational** — the object *is* an edge: `passive-dns` (827),
  `domain-ip` (456), `url` (549), `network-connection` (718),
  `whois` (62), `rogue-dns` (56). Pivoting through one of these is
  meaningful, and the `object_relation` names which end you are on
  (`rrname` / `rdata`, `domain` / `ip`).
- **Descriptive** — the object describes one thing in several fields:
  `file` (12,608), `pe-section`, `virustotal-report` (7,118),
  `ghidra-function`. Siblings here are *facets of the same artefact* —
  an md5 and a sha256 of one file are not two neighbours, they are two
  names for one thing.

Drawing both as one "sibling" edge, which is what the tab does today,
merges *"resolved to"* with *"is the same file as"*. Those are not the
same claim, and only the first is a pivot.

`ObjectReference.relationship_type` is the third layer and it is
populated: `communicates-with` (153), `downloaded-from` (36),
`redirects-to` (24), `drops` / `dropped-by` (27 / 24),
`connected-to` (67), `detected-as` (29). Typed edges between objects,
already in the database, drawn nowhere on this page.

### 25.6 What this does to §22 and §24

- **§24's negative result stands, and its scope narrows.** Event
  co-occurrence is flat. That is true and it is worth having measured.
  It is not a verdict on the graph; it is a verdict on the *relation
  §22 chose to draw*.
- **§22.2's two-mode model is right in shape and wrong in the middle
  node.** Not `value → event → neighbour` but **`value → object →
  value`**, with the object's template naming the edge and
  `object_relation` naming the direction. Same two-mode argument, same
  avoidance of the per-event clique, an incomparably better edge.
- **§22.4's twelve are re-scored.** Item 4 (the bridge) is alive after
  all — §25.3 is six of them in one event. Items 1, 5, 6 and 7 remain
  as §24 found them. Item 9 moves from ninth to first.
- **Item 26 — expand one hop — stops being optional.** All three
  examples are two or three hops. A page that shows one hop and links
  out is a page where the analyst does the walking; the whole value of
  §25.2 and §25.3 is in the *second* hop.
- **The timeline is an artefact the graph does not have.** §25.1's
  insight is entirely in `time_first` / `time_last` on the edge. A
  canvas draws six dead ends; a dated table draws the story. That is an
  argument for putting resolution history on the page as *its own
  panel*, and it belongs to the Timeline tab as much as to this one.

### 25.7 What was run

Read-only SQL against the instance the worktree serves, 2026-08-31:
object-template and `object_references.relationship_type` census; the
`passive-dns`, `domain-ip` and `url` object shapes by
`object_relation`; the three chains above traced from value to event to
tags; and a check that each address in §25.3 occurs in exactly one
event. No writes, and nothing installed in `app/`.

The three values are worth adding to §12.1's verification set —
`draculax.myq-see.com`, `45.77.250.80` and `18.117.184.102` — because
between them they exercise a dated relational object, a 22-way fan and
a genuine bridge, and the tab currently has no value that exercises any
of the three.

---

## 26. The design, decided — eleven questions and what they rejected

§22 evaluated the graph, §23 read pivotick 1.6 against it, §24 measured
the topology and emptied six of the twelve reads §22 rested on, and §25
found the relation that was actually being missed. This section records
what was then decided, question by question, with what each one rejected
— which is the part a later reader needs and the part that is otherwise
lost. The specification itself is `03-relationships.md` §23.

Settled 2026-09-01.

### 26.1 A correction that reshaped the first question

An earlier draft of §24.5 called the rail's `7 of 31 edges drawn` a live
defect. **It is not.** `7 of 31` is fixture output quoted in
`03-relationships.md` §9; the live denominator is
`relationSummary()['correlations']` — `distinct_values + near` — which
is the true size of the neighbourhood the fold saw. The shipped panel
prints an honest ratio.

What it honestly prints is **36 of 10,024**. The caption is accurate,
and its accuracy is the argument against the thing it captions. §24.5
and §24.9 are corrected; the option this removed was *"fix the defect
and stop"*, which was never available.

### 26.2 Scope — a full re-founding

Rejected: re-pointing the existing canvas at object edges and stopping;
removing the canvas and keeping the panels; parking the work.

The measurement in §24 could reasonably have ended the graph. What kept
it is §25: the reads are there, on a relation nobody had drawn.

### 26.3 Edges say what they are

Rejected: a curated relational/descriptive allowlist; a heuristic on
value types; blocking on an upstream template marker.

The deciding fact is that **MISP records no such distinction** — 373
templates carrying nothing structural, and `meta-category` is a domain
label. So a split would be hand-maintained against an upstream project,
and it would still be wrong on `url`, whose `domain` is both a different
thing and a part of the URL.

**A promote list is deferred rather than dropped**, and deliberately
scoped to *ranking* rather than meaning: an unlisted template must still
draw and still label. Ranking needs its own evidence, and merging the
two decisions would have let a maintenance list quietly decide what a
reader is allowed to see.

### 26.4 Both layers, and the event layer collapses

Rejected: object edges only; both layers at full expansion.

The shape that made this work is not one either option offered. The
event layer draws **the events themselves and stops**, rather than
expanding into their values — so `8.8.8.8` contributes 17 event nodes
where the current graph would contribute 10,024 value nodes. *Which
events is this value in* is a real question and an event node answers
it; *what else is in those events* is the flat relation §24 measured,
and the co-occurrence table already serves it better.

### 26.5 The tail rolls up, and the bound is legibility

Rejected: a hard ranked cap; refusing to draw above a threshold.

A hard cap is the current defect at smaller scale — the caption still
reads *N of 35,102* and the ranking still decides what is never seen.
Refusing gives the 3 % nothing.

The roll-up gives them an answer instead: `0.0.0.0` draws two nodes
carrying 32,922 and 1. **Nothing is truncated anywhere**, which removes
the fraction problem rather than shrinking it.

**On whether pivotick's future graph-coarsening changes this** — it does
not remove the need, and the reason is the wire. Phase 22 measured
5.9 MB as a fragment that does not arrive; `0.0.0.0`'s 35,102 siblings
are roughly 7 MB of nodes, so no client-side algorithm can help a
payload that never lands. A server-side bound is required either way.
What coarsening *would* change is the middle band, and bounding on
legibility rather than transport keeps the payload so far from the wire
that the question stops mattering here.

**The threshold is an estimate.** ~150–200, to be measured against real
fragment weight across all five layers at once before it becomes a
constant.

### 26.6 The rail keeps a canvas

Rejected: §22.5's verdict sentence and composition strip; a rail at full
detail.

§22.5 proposed dropping the canvas because 10,024 nodes cannot be drawn
at 340px. Object edges give 14–42, and rolled per template, 1–5 — so the
premise is gone. Full detail was rejected on §10.3's own measurement:
37 labels overlap into illegibility at that width.

Rolling the rail harder than the overlay turns the two surfaces into
progressive disclosure rather than two versions of one picture, and
`Open the full graph` gains a specific meaning: it expands the
templates into values.

### 26.7 Dated relations here, Timeline later

Rejected: Timeline only; both at once; an edge hover card.

The dates are a property of the edge and the reader is already on this
tab when they want them. A hover card is the wrong instrument for five
rows you scan and compare.

Timeline is the right long-term home by its own charter — *everything
about this value that carries a date* — but it is built and verified,
and a new source lane means re-verifying it. Recorded there as a
paragraph, scheduled separately.

### 26.8 Swap first

Rejected: building against the current bundle and swapping last;
swapping as its own phase.

`hasChildren` is already in the shipped bundle, so a rolled-up canvas
could have been built without 1.6 — but the layer switch, the badge
counts, the full labels and the legend all need it, and building against
two targets to defer a file copy is not a saving.

**The event Pivot Explorer is not re-verified by this phase.** That
work is owned elsewhere and already in progress on
`worktree-pivotick-v16`; this tab takes the same two files from that
branch — byte-identical to `~/git/pivotick/dist` — and makes no claim
about the wider verification. That removes almost all of the cost this
question was weighing.

### 26.9 The rail is `viewer`, the overlay is `light`

Rejected for the rail: `static`. Rejected for the overlay: `viewer` with
a hand-rolled key.

The rail is a peek. `static` cannot be nudged; `viewer` with
`navigation` left unconfigured can be panned and zoomed and still mounts
no controls, because `UIManager.ts:241` gates the viewport rail on
`o.navigation?.enabled`.

For the overlay, the question was framed around a constraint that has
since been removed. `legend` and `mainHeader` mount in exactly the same
modes (`['full', 'light']`), the header appends its notes button
unconditionally (`Mainheader.ts:72`), and `Shift+N` is registered
against it whether or not it is visible — so it could not be closed off
from the consumer side. **pivotick is gaining a flag to disable it**,
which settles it. `editors.deletion / nodeCreator / nodeEditor /
edgeEditor` stay `false` regardless: `light` mounts the mode rail and
tool panel, and those carry the Create tools.

### 26.10 Near-match and asserted stay

Rejected: dropping near-match to its panel; drawing object edges only.

Both are small and cost nothing. Dropping them would narrow the canvas
below the tab it sits on, and §5's separation of the notions is the
thing this tab lives or dies by. An analyst claim is the only edge on
the page a human wrote, which is the strongest reason of the three not
to lose it in a redesign about object joins.

### 26.11 Typed references, with object nodes — and a section of their own

Rejected: value-to-value reference edges with no object node; deferring
references entirely.

A reference is recorded between two objects. Drawing it between values
would be a re-telling, and it would make *which object* unanswerable.
The node budget absorbs it: 4–17 references on real values.

**The section was not in the options and is the better half of the
answer.** *Object relationships* lists what this value is related to
through `ObjectReference` — directly, where the target is its own
attribute (`referenced_type = 0`, 1,142 rows), and through its parent
object (`referenced_type = 1`, 10,191). It gives every layer on the
canvas a panel counterpart, which is how the rest of this tab works, and
it renders §25.3's bridge as a recorded fact rather than an inference.

### 26.12 Expand-one-hop waits

Rejected: building the endpoint now; calling roll-up expansion
"expansion".

All three of §25's chains are two or three hops, which is why §25.6
called this mandatory. What changed is that the Object relationships
section delivers the second hop as a panel. A live endpoint is a
client-side merge, a growing feed that breaks the bound §26.5 just set,
and a caching story of its own — worth doing once the object graph has
proved itself.

### 26.13 What is still open

- **The legibility threshold**, measured across five layers at once.
- **The promote list** (§26.3), as its own brief.
- **The Timeline source lane** (§26.7).
- **Live expand-one-hop** (§26.12).
- **Coverage sentences.** ~20 % of attributes sit in objects and 11 % of
  objects carry a reference. Both panels must state their own bound; the
  exact wording is not yet written.

---

## 27. The re-founding, built and measured

§26 recorded eleven decisions; `03-relationships.md` §23 is the
specification and its §24 is what shipped and where the data moved it.
This section is the measurement, run against the instance the worktree
serves on 2026-09-01 with
[`26-object-graph-probe.php`](26-object-graph-probe.php),
[`26-object-graph-harness.mjs`](26-object-graph-harness.mjs) and
[`26-panel-harness.mjs`](26-panel-harness.mjs).

### 27.1 The legibility bound, measured across all five layers

§23.3 left the threshold at *"~150–200, to be measured against real
fragment weight across all five layers at once"*. Measured, as JSON
bytes of the whole feed:

| Value | Object layer | Rail feed | Overlay feed |
|---|---|---|---|
| `1.0.155.105` | 1 template, 11 values | 2 nodes, **442 B** | 12 nodes, 4.2 KB |
| `github.com` | 1 template, 83 values | 2 nodes, **471 B** | 84 nodes, **37.7 KB** |
| `45.77.250.80` | 1 template, 42 values | 3 nodes, 805 B | 44 nodes, 17.2 KB |
| `draculax.myq-see.com.` | 1 template, 21 values | 4 nodes, 1.1 KB | 28 nodes, 10.4 KB |
| `18.117.184.102` | 1 template, 15 values | 4 nodes, 1.1 KB | 25 nodes, 9.4 KB |
| `0.0.0.0` | 2 templates, 32,922 objects | 5 nodes, 1.3 KB | 11 nodes, 3.7 KB |
| `8.8.8.8` | 3 templates, 22 values | 7 nodes, 2.0 KB | 52 nodes, 18.8 KB |
| `443` | 7 templates, 399 objects | 10 nodes, 3.0 KB | 56 nodes, 21.3 KB |

**About 0.45 KB per node, all five layers included.** At the proposed
150 that is ~68 KB and at 200 ~90 KB, against phase 22's 5.9 MB *"a
fragment that does not arrive"* and this tab's heaviest measured
fragment — the co-occurrence panel on `443` — at **915 KB**. The wire is
two orders of magnitude away, which settles §23.3's argument in its own
favour: **the bound is legibility, and nothing about it is about
transport.**

`GRAPH_SIBLING_BOUND` is therefore set at 150. It is not the binding
number: the fold caps its rows at `RELATION_ROW_CAP` (100) and the
overlay expands only when the fold carried every sibling it counted, so
100 binds first. Recorded rather than hidden, because raising one
without the other does nothing.

### 27.2 The rail is two to ten nodes, and every label is whole

§23.4 predicted "one node per template, 1–5 in practice". Measured over
the eight values: **1 to 7 templates**, and 2 to 10 rail nodes once the
four rolled layer nodes are counted. `443` is the outlier at seven
templates; every other value is one to three.

Driven in a real browser at 340px, in both themes, no label is
truncated and none overlaps:

    8.8.8.8   paloalto-threat-event · domain-ip · network-socket
              17 events · 6 analyst claims · 6 references
              src → dst · ip → domain
              ip-dst → address-family, protocol, dst-port

    0.0.0.0   paloalto-threat-event · pe · 7 events · 1 near-match
              dst → src

`0.0.0.0` is §23.3's specified render arriving exactly: two template
nodes, the caption *10 edges · tail rolled up, nothing dropped*, and
the `pe` object — one of 32,922, and the only interesting one — drawn
rather than ranked away. The old graph drew twelve of 35,102 and
captioned the fraction.

Computed strokes were read back against the theme tokens in both themes,
which is §6.1's standing rule applied to a canvas:
`--vp-rel-object` #524948/#c0b3b0, `--vp-rel-event` #14748d/#5cc4de,
`--vp-rel-near` #0b7f61/#4fd6b0, `--vp-rel-reference` #6d3fd1/#b79dfa,
`--vp-rel-human` #8f2d56/#e58cad. All five resolve.

### 27.3 What the two panels hold on real values

| Value | Dated relations | Object relationships |
|---|---|---|
| `draculax.myq-see.com.` | **5 rows, 5 `passive-dns` objects** | 5 `related-to` |
| `45.77.250.80` | 23 rows, 23 `domain-ip` objects | none |
| `18.117.184.102` | 4 rows, 4 `passive-dns` objects | **8 `hosted-by`** |
| `github.com` | 46 rows, 21 `url-honeypot-detection` | none |
| `443` | 4 rows of 397 objects read | 17, three types |
| `8.8.8.8` | none — no object records a span | 6, five types |
| `0.0.0.0` | none of 500 read; 32,922 in all | none of 500 read |
| `1.0.155.105` | none — its one object records none | none |

**§25.1 renders as written.** `draculax.myq-see.com.` gives five rows
oldest-first — `141.255.159.82` 2017-04-11, `168.181.48.248` 04-14,
`168.181.51.45` 04-18, `141.255.147.117` 04-25, then
`200.101.151.150` 2021-03-30 — with `time_first`/`time_last` named under
each date and CIRCL as the reporter. Four addresses in fourteen days,
four years of nothing, one more. The panel is the artefact §25.1 said
the timeline had to be.

**§25.3's bridge renders as a recorded fact.** `18.117.184.102` gives
eight `hosted-by` rows whose far objects name `ccss-public.com`,
`cns-lu.com` and `luxtrust.support` — the health service, the eID and a
third brand, on one host, each a link to its own page. Standing on the
address, the campaign is on the screen without opening the report.

**The relationship types on this instance are not all MISP's.**
`hosted-by`, `related-to`, `connect`, `connected-to`, `analysed-with`
and `authored-by` are; `Crush`, `Co-worker` and `Child` are on
`8.8.8.8` because somebody typed them. They print verbatim, which is
§23.2's rule and the only honest option.

### 27.4 Cost, cold and warm

Milliseconds, per facade call, cold after a cache flush and warm on the
second read:

| Value | Dated | References | Graph |
|---|---|---|---|
| `draculax.myq-see.com.` | 67 / 13 | 7 / 1 | 24 / 0 |
| `45.77.250.80` | 32 / 14 | 5 / 0 | 17 / 0 |
| `18.117.184.102` | 29 / 9 | 6 / 0 | 8 / 0 |
| `github.com` | 17 / 1 | 5 / 0 | 7 / 0 |
| `1.0.155.105` | 64 / 0 | 6 / 0 | 7 / 0 |
| `8.8.8.8` | 830 / 294 | 8 / 2 | 45 / 0 |
| `0.0.0.0` | 1,422 / 273 | 298 / 1 | 572 / 0 |
| `443` | 7,690 / 776 | 1,701 / 2 | 11,188 / 1 |

Three readings, and only one of them is new cost.

**Dated costs nothing of its own.** It is folded inside the
co-occurrence scan over rows that scan has already fetched, so its
number *is* the scan's — the probe calls it first and it pays for
everything. On the tab it is a Redis read behind whichever panel missed
first.

**References is genuinely independent and genuinely cheap** — 5 to 8 ms
on six of the eight values. The two exceptions are the two heaviest
values on the instance, where its own `occurrenceObjectIdsFor` groups
over 32,922 and 2,691 rows: 298 ms and 1,701 ms cold, 1 ms and 2 ms
warm. That is the price of not queueing behind a 20,000-row scan, and
`443`'s co-occurrence panel takes 7.7 s cold on the same page.

**The graph's own arithmetic is free.** Its 11 s on `443` is the digest
assembling a cold scan; the two feeds are built from data already in
hand and the folding is not measurable beside it.

### 27.5 A memo that could not say which value it held

Found by the probe on its second value and worth recording, because it
was invisible inside the application. `ValueProfile` memoises the
co-occurrence fold and the occurrence summary per request, and a request
serves one value — so nothing keyed the memo on the value. A console
shell walking eight verification values got `draculax.myq-see.com.`'s
neighbourhood eight times, with no key to notice it by.

The memos now carry the value they hold and a `forget()` clears them the
moment a different one is asked for. Nothing in the application
behaved differently; every future loop over values will.

### 27.6 What was run

- `26-object-graph-probe.php` over the eight §23.8 values, cold and
  warm, for shapes, counts, feed weight and timing.
- `24-relationships-render.php`, extended to the six sections, rendering
  all eight panels for all eight values under `debug = 2` — 64
  fragments, no notice, no undefined key, no exception.
- Real HTTP against the running instance with a logged-in session: all
  eight `viewRelation*` actions return 200, which exercises the two new
  `ACLComponent` entries as well as the render.
- `26-object-graph-harness.mjs` in headless Chromium at 460×1100, light
  and dark, reading computed strokes and the label list off the SVG, and
  screenshotting the rail and the expanded overlay.
- `26-panel-harness.mjs` for the two new tables, with the witness
  `24-relationships-browser.md` insists on: 46 rows collapsing to 8 with
  a six-page control before any assertion about what the page showed.
- Read-only SQL for the `disable_correlation`, `datetime` and
  `object_references` censuses quoted above.

Nothing was installed in `app/`; both scratch shells were copied in,
run and removed.

### 27.7 What is still open

- **The `light` overlay** with pivotick's legend and `edgeFacets` layer
  switches. The server side is done — the feed carries five edge kinds
  and a `layers` summary — and both surfaces stay `viewer` until the
  upstream flag that switches Notes off lands (§26.9).
- **The promote list** (§26.3).
- **The Timeline source lane** (§26.7). `url-honeypot-detection` joins
  `passive-dns` as a candidate: `github.com` alone has 46 dated
  relations across 21 of them.
- **Live expand-one-hop** (§26.12).
- **Coverage sentences** (§26.13) are written and are the *value's* own
  arithmetic rather than the instance's: *"Read from 5 occurrences and 4
  objects of this value. 4 of them carry a reference."* An
  instance-wide `7,905 of 69,976` is true and costs two counts over the
  whole database to print, and it is not the number a reader of one
  value's page is asking about.
