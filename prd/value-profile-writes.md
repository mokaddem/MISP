# PRD: Value Profile — analyst writes on a value

Companion to [`value-profile-page.md`](value-profile-page.md). §14 of that
document is the contract for taking the nine tabs live as **reads**; this one
is how an analyst puts their own view — tags, opinions, notes, sightings,
enrichment results — onto a *value* rather than onto somebody's event.

Held separate deliberately. §14 adds no schema and leaves *"nothing writes"*
standing; this adds tables and touches sync, which is a different risk profile.
A live read phase must not be blocked on agreeing any of what follows.

Nothing here is built. This is the design and the constraints behind it.

---

## 1. The problem

The page's subject is a value. Every write anchor MISP has is an
`attribute_id` (plus `event_id`) or an `object_uuid`. So an analyst who wants to
record *"I distrust this IP"* has nowhere to put it that is not inside some
organisation's event — and the value sits in seven of them, owned by four orgs,
with occurrence spans that can be years apart.

The only value-keyed tables in the schema are these, and none can own per-org
user data:

| Table | What it is | Why not |
|---|---|---|
| `correlation_values` | the correlation engine's value dictionary | truncates to 191 chars (`CorrelationValue::getIds()`), so it cannot even *identify* a long value; owned by the engine and rebuilt with it |
| `over_correlating_values` | derived cache of suppressed values | derived, not asserted |
| `correlation_exclusions` | site-admin allowlist | one per instance, no org, never synced |
| `warninglist_entries` | list content | belongs to a distributed list, not to a user |

## 2. Why not a dedicated event

Rejected, and not on taste. An event holding the analyst's annotations creates
**new occurrences of the value**, so the annotations feed back into the page
that aggregates them: your opinion on `185.234.219.24` becomes an eleventh
occurrence, joins the correlation graph, and moves the verdict it was written to
comment on. It would also inherit a publication lifecycle, which an assertion
does not have — an event is a report, and *"I distrust this"* is not a report.

## 3. Five kinds of claim, four lifecycles

The list of things an analyst wants to write is not one thing, and one table for
all of it is where this design goes wrong.

| Claim | Nature | Needs |
|---|---|---|
| opinion, note | attributed assertion, scaled or prose | sync, distribution, threading, audit |
| tag | attributed classification with a relational referent | foreign key to `tags`, taxonomy validation, a timestamp |
| `to_ids` | export-time **policy**, not an annotation | consulted by every export path |
| enrichment runs, dismissals | machine cache | evictable, instance-local, never synced |
| watch | per-user preference | not shared at all |

## 4. What MISP already permits, and what it forbids

Two mechanisms already write cross-org onto data the writer does not own, and
they are exactly the two that carry their own identity and their own date.

**Sightings already have a value-scoped write.**
`Sighting::saveSightings($id, $values, …)` (`Sighting.php:795`) takes `$id` as
`false` and a list of values, builds an `OR` over `value1` and `value2`, and
fans out through `fetchAttributesSimple` — which scopes by
`buildConditions($user)` (`MispAttribute.php:2052`). One row per *visible*
attribute, each carrying the writer's own `date_sighting` and `org_id`. On the
malicious demo value that is six rows, not ten. This case is solved; the page's
job is to state what it did, and to note that the `value2` match means a
sighting of an IP also lands on the `domain|ip` composite the page already
flags as `value2_note`.

**Analyst data was already decoupled from the event graph.**
`AnalystData::valid_targets` (`AnalystData.php:15`) lists eleven targets, and
two of them — `Organisation` and `SharingGroup` — are not event children at all.
So "analyst data about something outside the event graph" is a shipped concept
rather than a new one. Further:

- `object_uuid` is validated **only** as a well-formed UUID
  (`AnalystData.php:88-93`); `object_type` is not validated at the model layer
  at all.
- `AnalystDataController::add` (`:43`) performs **no ownership check** on the
  target, and `deduceType()` — which resolves a UUID by scanning tables — is
  skipped entirely when `object_type` is passed explicitly (`:53-55`).
- `Event::captureAnalystData` (`Event.php:9913`) carries `object_type` and
  `object_uuid` through without interpreting them.
- Most importantly, analyst data has a **standalone sync channel** that moves
  rows by UUID and timestamp independently of any event: `AnalystData::push`
  (`Server.php:1432`), `pushAnalystData` (`:505`),
  `filterAnalystDataForPush` (`:452`), `indexMinimal` (`:466`),
  `fetchUUIDsFromServer` (`Server.php:785`), and a pull step at
  `Server.php:691`. Gated on `perm_sync` and `perm_analyst_data`.

**Tags are the opposite, and this is the finding that shapes the plan.**
`ACLComponent::canModifyTag` (`:1232`) requires `perm_tagger` or `perm_sync`,
then grants the write only via `canModifyEvent` — or, for a **local** tag, only
if the writer is in the **host org** (`:1248`). A normal organisation's analyst
cannot tag another org's occurrence at all, local or not. And
`attribute_tags` is `(id, attribute_id, event_id, tag_id, local,
relationship_type)` — no `created`, no org — so §8.2's finding that tags have no
timestamp anywhere holds: a tag's date exists only as an audit row, gated behind
`MISP.log_new_audit`, which defaults to false.

So tag fan-out is both forbidden for most users and, where permitted, an
*undated* assertion smeared across ten occurrences whose active spans nobody
chose.

## 5. Options considered

| | Option | Verdict |
|---|---|---|
| A | Extend analyst data with a `Value` target: `object_type='Value'`, `object_uuid` derived from the value | **taken** for notes/opinions/relationships. Zero schema change; inherits sync, distribution, sharing groups, `buildConditions` ACL, two-level threading and audit (`AnalystData.php` `actsAs AuditLog`) |
| B | One generic `value_annotations` table, EAV-shaped | rejected. A tag becomes a JSON string: no join to `tags`, no taxonomy validation, `tag_list.ctp` cannot render it, nothing indexes it. The entire value of a MISP tag is that it is a relational row |
| C | A `value_tags` table mirroring `attribute_tags` / `event_tags` | **taken** for tags. Idiomatic — MISP already has this table per scope — and keeps the `tag_id` foreign key |
| D | A full `values` entity table with child tables | rejected. Cleanest diagram, largest migration, and unnecessary: a derived identity gives identity without existence, and everything a `values` row would carry (type, first/last seen) is derived from occurrences. It also opens a garbage-collection question with no good answer |
| E | Reuse `collections` / `collection_elements` | rejected. Right addressing shape (`element_uuid` + `element_type`, org-owned, synced) but wrong semantics: a collection is a *set of things*, not an assertion *about one thing*. The annotation would end up in `collection_elements.description` |

## 6. What ships

Three stores, one dictionary, and two things that need no new storage at all.

### 6.1 Notes, opinions, relationships — `Value` as an analyst-data target

`object_type = 'Value'`, `object_uuid = Value::uuidFor($value)` (§7). Add
`'Value'` to `AnalystData::valid_targets`, and give `deduceType()` a branch —
it resolves a UUID by scanning tables, and a value has no table to scan.

This is the largest slice of "the analyst's own view" and it costs almost
nothing: distribution, sharing groups, org attribution, threading two levels
deep, audit and cross-instance sync all come from code that already runs.

### 6.2 Tags — `value_tags`, local-only in v1

```
value_tags
  id
  value_uuid        FK → value_dictionary
  tag_id            FK → tags
  org_id            NEW vs attribute_tags — the tag is now a cross-org
                    assertion, not a property of your own event
  local             1 in v1, always
  relationship_type
  created           NEW vs attribute_tags — §8.2's missing-timestamp defect
                    is not worth re-importing at a new scope
```

**Local-only in v1** buys the most of any scoping decision here. It defers the
whole sync channel *and* the distribution question — a value tag has no parent
to inherit distribution from, unlike `attribute_tags` — and it maps onto MISP's
existing `local` semantics: my instance's view, not the community's. Critically
it has no host-org problem, because `canModifyTag`'s restriction exists to
protect other orgs' event containers and there is no container here.

`local = 0` plus a sync channel is a later phase, taken when someone wants it
shared.

The cost to state on the page: a tag that is not in `attribute_tags` will not be
found by any existing MISP attribute search.

### 6.3 `to_ids` — why it stays per-occurrence

The one item on the list that cannot be value-scoped cheaply. A value-level
`to_ids` that does not change export output is a lie, and making it change
output means consulting a new store from restSearch's `to_ids` filter, the NIDS
rule exports, STIX export and feed generation. Conceptually it is a *detection
policy over a value*, which is what warninglists and `correlation_exclusions`
already are.

**v1: `to_ids` stays per-occurrence**, and the page offers propose-per-occurrence
where the viewer lacks edit rights. An org-scoped allowlist is recorded as the
real answer and is not built here. This is also the industry posture — no
professional CTI platform lets one party edit another's assertion either — so it
is not a limitation to apologise for on the page.

### 6.4 Enrichment state — an instance-local cache

§7.9 found that nothing anywhere records that a module ran (`Module` is
`useTable = false`), and that a dismissal is not remembered either — the
per-element checkboxes are DOM state in one modal, so a re-run re-proposes
everything the analyst rejected.

A plain cache table, keyed by value and module: last run, by whom, status, and
the dismissed elements. **Kept out of the assertion stores on purpose** — it has
a different lifecycle (evictable), no distribution story, and is owned by the
instance rather than by an org.

Enrichment *results* are a separate question, and §7.9's *"add to event has no
target when the value sits in seven of them"* has an honest answer: results are
about the value, so writing them into a stranger's event is a category error.
The default target is a new event owned by the analyst's own org, which MISP
already supports as *add as new event*. That has a pleasing closure property —
the result then appears as an occurrence on this very page. Note that the
interactive path is synchronous whatever `MISP.background_jobs` says, because
`Event::enrichmentRouter()` returns before its own branch
(`Event.php:7995`), so a tab running nine modules needs the queued path.

### 6.5 Watch — `user_settings`

No new schema. `user_settings` is `(id, setting, value, user_id, timestamp)`
with a unique key on `(user_id, setting)`. Per-user, never shared, never synced —
which is exactly what a watch is.

### 6.6 `value_dictionary` — making a one-way identity renderable

Option A has one structural flaw worth naming. A UUIDv5 is one-way: a synced
opinion arrives carrying `object_uuid` and **nothing tells you which value it is
about**. If the instance has never seen that value, the note is un-renderable.

```
value_dictionary
  value_uuid   char(36)   PK   -- Value::uuidFor(normalise(value))
  value        text            -- untruncated, the readable form
  created      datetime
```

This is `correlation_values` done right: that table cannot serve, because it
truncates at 191 chars and only ever holds values that actually correlated.
Populate the dictionary on local write and on attribute ingestion; `value_tags`
takes its foreign key from here.

The residual gap becomes a **state rather than a bug**: an assertion about a
value this instance has never seen renders as an honest state alongside empty,
not-implemented, suppressed and not-recorded. That is the same move the main PRD
makes with every other gap.

## 7. Identity and normalisation — the contract

One function, `Value::uuidFor($value)`, defined in §14.3 of the main PRD so that
reads and writes normalise through the same code.

**Use STIX 2.1's rule rather than a MISP-private namespace.** Cyber Observable
Objects get deterministic ids —
`uuid5(00abedb4-aa42-466c-9c01-fed23315a9b7, <canonicalised id-contributing
properties>)` — and MISP already vendors the implementation
(`app/files/scripts/cti-python-stix2/stix2/base.py:29,477`). MISP's own
`stix2misp.py:123,135` already uses `uuid.uuid5` for deterministic
re-derivation, so the technique is in the codebase.

Two things follow, and they are the whole reason to prefer the standard:

1. **Value-scoped analyst data becomes addressable by non-MISP peers.** Every
   STIX-native platform already calls `185.234.219.24` by the id this rule
   produces, so the assertion is portable rather than MISP-private.
2. **The canonicalisation is specified rather than invented**, which removes the
   single largest risk in the design. Normalisation is protocol here: get it
   wrong and two instances write about "different" values. It covers refanging
   (already true — `ComplexTypeTool::refangValue()` normalises on ingestion),
   lowercasing for domains, hostnames and email addresses, punycode for IDN,
   lowercase hex for digests, and no trailing dot.

**Identity is the value, not the pair `(type, value)`** — the same reading §14.3
takes for reads, and the same one `saveSightings` takes when it matches `value1`
or `value2`. A composite attribute contributes two identities.

## 8. The three write scopes the page must keep apart

Trying to make one *Add tag* button serve scopes 1 and 3 is what makes this
frightening. Keeping them visibly distinct is the design, and it is the same
discipline as the honest states.

| Scope | Where it lands | Who may |
|---|---|---|
| **On the value** — opinion, note, value tag, enrichment provenance, watch | the new stores above; no occurrence touched | anyone who can see the value |
| **On my visible occurrences** — sighting | ACL-scoped fan-out, one row per visible attribute, each carrying its own date | anyone, via `saveSightings` today |
| **On one occurrence** — `to_ids`, distribution, comment, per-occurrence tag, enrichment write-back | that attribute, or a `ShadowAttribute` proposal | the event owner, else propose only |

Every write control states its scope *before* it is used and its result
*after* — with the §14.6 caveat that the result may not name a count of things
the viewer cannot see. *"Sighting recorded against your 6 visible
occurrences"*, not *"6 of 10"*.

## 9. Why the time ranges are a page problem, not a write problem

The occurrences of one value can carry `first_seen`/`last_seen` spans years
apart, and any of them can be null. The instinct is to give each assertion a
validity window. Resist it: `notes` and `opinions` carry only `created` and
`modified`, and adding validity columns is a protocol change to a synced object.

The alternative already exists on this page. `185.234.219.24` may have been a
C2 in March 2025 and a reassigned hosting address by August 2026 — both
assertions true of different periods. Without windowing, the Verdict tab reads
genuine temporal *succession* as CONFLICTED, which is the one failure mode that
tab exists to prevent. The fix is the page's own time window: sightings are
dated, analyst data is dated by `created`, occurrences have `first_seen` and
`last_seen` where non-null, and phase 20's `ValueProfileBuckets` plus the
brush already give the page a window control with three callers. Promote it to a
page-level input and every aggregate is computed as of it.

Tags and feed appearances have no usable date at all (§8.2) and sit outside the
window, marked — which is the treatment `T-final` already ships.

## 10. Open decisions

1. **Does the Analyst tab's aggregate count value-scoped opinions,
   occurrence-scoped ones, or both with a visible split?** These are different
   claims and both are legitimate — *"this IP is malicious"* versus *"this
   attribute in event 1284 is a false positive"*. `05-analyst.md` §11 already
   ruled that an opinion on a note rates the note; the same rule one level up
   says an opinion on an occurrence rates that org's assertion, not the value.
   This decides whether the tab's headline number means anything.
2. **Is the derived-UUID normalisation contract worth taking**, or should
   value-scoped data stay instance-local for a first pass?
3. **Is a value-level tag offered at all**, given it will not be found by any
   existing MISP attribute search?
4. **Visibility of a value has no owner.** MISP's `distribution` is per row, so a
   value-anchored note has a distribution but the *value* has none. Two orgs can
   hold value-scoped opinions neither can see, so the Analyst tab's aggregate is
   per-viewer by construction — the same shape as the sightings count being the
   viewer's. It means the verdict is never *"the community's view"*, only *"the
   view available to you"*, which is what §14.6's Verdict-tab caveat already
   says.

## 11. Out of scope

- Everything in §14 of the main PRD: this document adds no read path.
- A sync channel for `value_tags` — v1 is local-only.
- An org-scoped detection allowlist, the real answer for value-level `to_ids`.
- Retraction across federation. STIX has `revoked`; nothing compels a peer to
  honour it, and MISP's soft-delete has the same shape. Not solved here or
  anywhere.
- A verdict scoring engine, still out per §5.
