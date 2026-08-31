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
itself walks, 44 entries here — so the answer is the engine's own rather
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

**A new state: *active, and it found nothing.*** `8.8.8.8` is in no block
on this instance. The panel says *"No network block on this instance
contains this address. The engine applies; it found nothing"* — which is
neither the empty state nor *not applicable*.

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

**Not upgraded.** pivotick 1.6 ships *edge layers* — edges keyed by kind,
styled per kind and switched on and off without moving the graph — which
is precisely the three-notion switch this tab wants. Swapping the bundle
would touch the event Pivot Explorer too and is not a wiring phase's
work; `../pivotick`'s own `prd/` is where that belongs. Recorded as a
follow-up (§15).

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
> question is answered in `03-relationships.md` §20. What remains is one
> MISP defect — §17.2.

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

1. **A tab-level context.** The two rail cards each repeat all three
   sections (§5.4). The fix is one shared per-request assembly or one
   endpoint, and it is the first concrete customer §14.11's caching
   exclusion has had.
2. **Feed co-occurrence** — **designed, and blocked on one MISP
   defect.** `03-relationships.md` §20 is the design, §17 the
   measurements against a now-populated cache. The gate turned out to be
   per source rather than one permission (§20.2, §20.9), and the blocker
   is `searchCaches` misattributing remote event uuids across sources
   (§17.2). The graph's fourth edge kind stays deferred for the reason
   §20.7 gives.
3. **Claim prose**, as child Notes on each relationship (§7), once
   that section has decided its own per-claim bound.
4. **pivotick's edge layers.** 1.6 ships exactly the three-notion switch
   this tab wants; the shipped bundle predates it (§10.2). Upstream, via
   `../pivotick`'s `prd/`, and then one bundle swap that the event Pivot
   Explorer has to be re-verified against.
5. **A `Sighting`-style counting method for `Relationship`**, so the tab
   bar could carry a claim count without running the panel — the same
   shape phase 23's §12.1 asked for on sightings, and the same reason.
6. **`over_correlating_values.occurrence`** is dead weight on the read
   path (§9.1). Either the router job runs, or the column should stop
   being offered as a number anything can print.

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

### 17.2 The defect that blocks the section

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

The fix is that filter, in the two places `searchCaches` is missing it.
It is a MISP fix rather than a page fix, and it lands before the section
is built: a panel whose entire content is *which remote event* cannot
ship on a primitive that misattributes them.

### 17.3 The card's count is not a lighter read

The Overview card was proposed as a "light check" on the grounds of
cost. There is no cost to save:

| Read | Time |
|---|---|
| `searchCaches($v, false)` — everything, including uuids | 0.5–1.5 ms |
| `searchCaches($v, true)` — the `$limited` path | 0.0–0.7 ms |
| the two `sismember` calls on the combined sets alone | 0.09–0.15 ms |

All three are noise beside the 173–535 ms §16.7 measures for section
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

This one does not block §20 — it under-reports rather than
misattributes, which is the failure direction the page can carry — but
it belongs in the same MISP change. The honest fix is to test **both**
hashes on read rather than to change either side's normalisation: a
false positive is impossible, since a hit on either hash means some
publisher cached that exact string, and the quick-cache path at `:1705`
takes hashes straight from the feed's own file, so mixed normalisation
is baked in and cannot be legislated away from one end.

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
