# PRD: Value Profile — Enrichment goes live (stateless)

**Phase 28.** Converts `viewEnrichment` / `value_enrichment`. Opened
2026-09-06. Inherits [`00-contract.md`](00-contract.md) (§14) and rebuilds
the tab phase 12 shipped, [`../value-profile-tabs/04-enrichment.md`](../value-profile-tabs/04-enrichment.md).

---

## 1. Why this phase exists, and why it is not the phase §1.4 predicted

Every version of the campaign board has carried Enrichment as **blocked**,
and the blocker was never the data — it was the *store*. Phase 12 §11
listed eight things live data would hit and six of them were persistence:
a last-run timestamp, staleness, the delta band, dismissals, the awaiting-
review count, and the cost metadata that has no source anywhere. Re-checked
2026-09-04 and again at the head of this phase, all of it still true:
`Module` is `useTable = false` (`app/Model/Module.php:7`), no per-value
per-module run store exists among the instance's tables, and
`Event::enrichmentRouter()` returns at `Event.php:7997` above its own
`MISP.background_jobs` branch, so the interactive path is synchronous
whatever the setting says.

**The 2026-09-06 decision is to drop the store rather than build it.** The
tab becomes what it can be without one: *an interface that lists the
enrichment modules eligible for this value, runs one on demand, and renders
what came back.* Persistence is a later phase and this document does not
design it.

That is a smaller tab than phase 12 drew, and the important part of this
phase is **what it removes**. A rail carrying fixture staleness chips beside
a live result is precisely the hazard `value-profile-page.md` §1.4 names one
level up about the page frame: a number in the chrome contradicting the
panel it labels. Six state-bearing features have no source, so they come
out; they do not get to stay as fixture decoration.

**E2's rail survives. What it loses is memory.** The candidate was chosen in
phase 12 because all six of the tab's states are the same object — a rail
row — and that is still true stateless. A module that has not been run this
visit, one that is running, one that answered, one that answered with
nothing, one that errored and one that timed out are six rail rows and one
pane. What no longer exists is the *historical* dimension: whether it ran
last week, and what changed since.

---

## 2. What the probe established

[`28-enrichment-probe.php`](28-enrichment-probe.php) — copy in, run,
remove. Read against the dev instance as `admin@admin.test` (site admin) on
2026-09-06. Six checks; four confirmed the design and two changed it.

### 2.1 The service is live and the enabled set is small

`GET /modules` reports **157 modules** in **9 ms**.
`Module::getEnabledModules($user)` returns **7** in **3–4 ms**, because
every module is gated on `Plugin.Enrichment_<name>_enabled` and this
instance sets eight of them (one of which, `convert_markdown_to_pdf`, is
not present on the endpoint the Enrichment family points at).

| Module | Kinds | Format | Inputs | Config |
|---|---|---|---|---|
| `onion_lookup` | expansion+hover | `misp_standard` | 1 | none needed |
| `html_to_markdown` | expansion | `simplified` | 1 | none needed |
| `hashlookup` | expansion+hover | `misp_standard` | 3 | **missing** `custom_API` |
| `whois` | expansion | `simplified` | 3 | **missing** `server`, `port` |
| `mmdb_lookup` | expansion+hover | `misp_standard` | 4 | **missing** 3 keys |
| `docx_enrich` | expansion | `simplified` | 1 | none needed |
| `circl_passivedns` | expansion+hover | `misp_standard` | 6 | complete (2) |

**The rail's length is the instance's business, not this page's.** Phase 12
drew nine rows and worried in §11 that the shape must survive "three modules
and thirty"; on the catalogue the service reports, `ip-src` is declared by
**47** modules and `domain` by 39, so an instance that enables broadly makes
a rail of dozens. The `_enabled` gate is what bounds it here, and the design
still may not assume the gate is narrow.

### 2.2 A value is several types — the fixture models one

`Value::typesFor()` already exists, is already ACL-scoped, and is the input
this tab needs. `8.8.8.8` is **four types**: `ip-dst` 17, `ip-src` 5,
`text` 2, `ip-dst|port` 2. The fixture carries a single
`'type' => 'ip-dst'` and the whole tab is built on it.

Eligibility is therefore a **union over the value's types**, and a module
eligible via three of them is one rail row rather than three. Measured:

| Value | Types | Eligible |
|---|---|---|
| `8.8.8.8` | 4 | 3 — `whois`, `mmdb_lookup`, `circl_passivedns` |
| `github.com` | 1 (`domain` 21) | 2 — `whois`, `circl_passivedns` |
| `f1d3ff…7262` | 1 (`md5` 23) | 1 — `hashlookup` |
| `45.155.205.233` | 1 (`ip-dst\|port` 2) | 2 — `mmdb_lookup`, `circl_passivedns` |

`text` contributes nothing: no enabled module declares it. A type that
matches no module is not an error and gets no row.

### 2.3 **Config completeness cannot predict failure** — the first change

The probe was written expecting `meta.config` to name *required* keys, so
that a module with unset keys could be labelled as certain to fail before
it is run. That is wrong, and wrong in both directions:

- `whois`, missing `server` and `port`, **fails** — `module error: Whois
  local instance address is missing`, returned in **7 ms**.
- `mmdb_lookup`, missing all three of its keys, **works** — 3 objects in
  98–247 ms.
- `hashlookup`, missing `custom_API`, **works** — 1 object in 146 ms.

Nothing in module introspection distinguishes a required key from an
optional override. **So the rail carries no "misconfigured" chip**, because
it would be a lie on two of the three modules that would wear it. The honest
treatment is to let the module answer and render its own message as a
first-class state — which costs 7 ms and no external call, so the cheap
outcome is also the correct one.

### 2.4 Every run wrote nothing — verified, not asserted

Six real module queries across four values, with `attributes`, `objects` and
`events` counted either side of each. **Every one: `wrote: NOTHING`.**

This is the page's prime rule and the first phase where it needed
demonstrating rather than restating, because this is the first panel that
executes third-party code. `Module::queryModuleServer()` is the non-writing
call — the one `AttributesController::hoverEnrichment` uses. The writing
wrapper is `Event::enrichment()`, which takes a module response and creates
attributes from it, and **this phase never calls it.**

### 2.5 Result volume is the real scaling problem — the second change

`circl_passivedns` on `8.8.8.8` returned **1,374 objects in 4.9 s**. On
`github.com`, 87 objects in 371–488 ms. On `45.155.205.233`, 2.

Phase 12 §11 named the cost worry as *time* — one `POST /query` under
`Plugin.Enrichment_timeout`, no progress inside a module — and that is
handled: 30 s here, one module per request, 98 ms to 4.9 s measured. What it
did not predict is **result size**. A pane that renders 1,374 objects
inline is the same defect this campaign has now met on five tabs, and it
takes the same answer: cap the render, say it capped, and never let the
capped number read as the total.

### 2.6 A blocked workflow trigger would block the whole tab, silently

`queryModuleServer()` runs `enrichment-before-query` through
`__prepareAndExecuteTrigger()` unless `$skipTrigger` is passed. Read that
method with empty trigger data and no `attribute_uuid` or `event_id` in the
payload — which is what a naive value-page call looks like — and it falls to
its last `else`, dereferences `$triggerData['Event']['Attribute'][0]['id']`
on an empty array, then hits `if (empty($triggerData)) return false;`.
`queryModuleServer` turns that into the string *"Trigger
`enrichment-before-query` blocked enrichment"*, for every module, on every
value.

**Latent on this instance and not observed**: `Workflow_enable` is true, but
no workflow is bound to that trigger and all 24 rows in `workflows` are
`enabled = 0`, so `isTriggerCallable()` returns false and the probe's runs
went straight through. On an instance that binds it, this tab would be dead
in a way that names a workflow rather than itself.

The answer is **not** `$skipTrigger`. That trigger is how an instance
forbids particular attributes from leaving the building, and a page that
skipped it would be quietly disabling a control somebody configured on
purpose. The phase passes real trigger data instead — see D3.

---

## 3. Decisions

**D1 — The phase converts the tab and removes what has no source.** Live:
the catalogue, eligibility, the run, the result, timing, and the service's
reachability. Removed: `last_run`, `ran_at`, `last_ran_at`, `stale_days`,
the group headers that sort by staleness, the delta band and its "of which
new", `pending` / awaiting-review, dismissal and restore, and the cost
chips. Nine fixture keys stop being rendered; §5 lists them one by one so
the removal is auditable rather than inferred from a diff.

**D2 — Two endpoints, not one.** Phase 12 had a single `viewEnrichment`
because the rail and the pane were one fixture read. They no longer are: the
rail is cheap and local, the pane costs an outbound query and up to five
seconds. So `viewEnrichment` renders the rail and the empty pane, and a new
`viewEnrichmentRun` returns one module's result. This is the per-panel ajax
pattern the page already uses, applied one level down.

**D3 — A run is backed by one of the reader's own occurrences.** Both
formats need it and for different reasons: `misp_standard` sends the
attribute itself, and *every* format needs it as trigger data so §2.6's
control still bites. The rule is **the reader's first visible occurrence of
the type the run is for**, found through `MispAttribute::buildConditions` so
it cannot name a row the reader may not see. A value with no visible
occurrence has no eligible modules and no run — which is the same answer the
rest of the page gives, and D9 is where that is argued.

**D4 — `viewEnrichmentRun` is a POST.** It writes nothing to MISP, but it
spends the instance's money and quota and it announces interest in the value
to a third party. A GET is prefetchable, replayable and crawlable; none of
those are acceptable for an action with an outbound side effect.

**D5 — The type a run uses is shown, and chosen when it is ambiguous.** A
module eligible via three of the value's types must run against one of them.
Default: the most common — `typesFor` is ordered by occurrence count
descending — and the rail names it, so a run is never against a type the
reader did not see chosen.

**D6 — No "misconfigured" chip.** §2.3. The rail states what a module *is*
(kind, format, the type it would use); what it *did* comes from running it.

**D7 — Nothing auto-runs.** Carried unchanged from phase 12 §9 and with
more force now that the query is real: not on page load, not on tab switch,
not on selecting a rail row. The untouched state is the designed landing.

**D8 — The pane caps.** §2.5. A cap on rendered elements, stated, with the
total beside it.

**D9 — The three concepts: `no` on all three, one reason shared.**
*Proposals* — a proposed attribute has a type, and `Value::proposalsFor()`
would find it, but D3 requires an occurrence to back a run and a proposal is
not one; a tab that offered a run it could not perform would be worse than
one that says nothing. *Feeds and sync servers* — a feed hit says the value
is in somebody's list, which is the Relationships tab's fourth section and a
different question from what a module returns. *Event reports* — module
enrichment of a report exists (`EventReport.php:1033`, `:1457`) and is a
report's own feature, not this value's.

**D10 — The tab badge is dropped, not corrected.** `value-profile-page.md`
§1.4 records Enrichment's as the last fixture number in the page frame and
predicts it becomes wrong the day this tab converts. The honest number is
the eligible-module count, and it is cheap — 9 ms for the catalogue plus
2–26 ms for `typesFor`. It is still dropped, because it would make **every
page load, on every tab, depend on an external HTTP service**, and pay that
service's 1 s timeout when it is down. Precedent is Relationships (§3) and
Collaboration (§10.2), which dropped theirs for their own reasons. After
this phase the tab bar carries exactly two numbers, both the viewer's, both
read live.

---

## 4. What §14.9 requires

1. **Panels converted:** `value_enrichment` (`viewEnrichment`), plus a new
   `value_enrichment_result` (`viewEnrichmentRun`).
2. **Query count and tier:** filled by the build, in §6. The catalogue is
   **not a database read at all** — it is an outbound HTTP call plus
   `Configure::read`, so like `viewExternal` it is **none of §14.4's three
   tiers**, and for the same reason recorded there. `typesFor` is tier 1.
3. **Fetchers used:** `Value::typesFor`, `Value::conditionsFor`,
   `MispAttribute::buildConditions`. No event access, so no N+1 to answer
   for.
4. **Shared elements touched:** none intended. `Module` is read through its
   public API and not modified. If the pane reuses
   `Themed/Overmind/Events/resolved_misp_format.ctp` it will be a **fork**,
   not an edit — that element is built around selecting elements to write
   into an event, which is the one thing this tab must not offer.
5. **§14.6 required changes applied:** this tab's ACL band (a run is
   backed by the reader's own occurrence, D3) — confirmed in the build.
6. **Deferred, with the cost named:** all of persistence (§5), and with it
   staleness, dismissal and the delta. The cost is that a reader who runs
   the same module twice in two visits is told nothing about the first, and
   that re-running re-proposes everything they rejected. Also deferred:
   Cortex as a second rail, and phase 12's `E1` staging tray.
7. **Values verified against:** `8.8.8.8` (four types, three modules),
   `github.com` (one type, an error and a large result), the md5
   `f1d3ff8443297732862df21dc4e57262` (one module, one object),
   `45.155.205.233` (a `|port` composite), and a value with no occurrence
   for the sparse state.
8. **Both themes**, with §6.1's trap: assert a token resolves before
   asserting a colour.
9. **The three concepts:** D9 — `no` on all three, argued.

---

## 5. The nine fixture keys this phase stops rendering

Auditable list, so the removal can be checked rather than inferred.
`enrichModuleRow` in `ValueProfileFixture` builds twelve columns; these are
the ones with no live source.

> **This list is complete for what persistence killed, and it was
> mistaken for the whole of what the build removed.** It is not: the
> first build also dropped seven features that have nothing to do with
> a store — select-all and the batch run, the merged `All results` row,
> `Already in MISP`, the disabled write controls, *timed out* as its
> own state, the object fold, and half the provenance line. §8 is that
> sweep and they are all built now. A removal list covering only the
> justified removals reads as an audit and is worse than none.

| Key | What it drew | Why it goes |
|---|---|---|
| `ran_at` | "ran at, in the last run" | no run store |
| `last_ran_at` | "last ran at, ever" | no run store |
| `stale_days` | the staleness chip | derived from the above |
| `state` | never-run / staged / answered grouping | historical; stateless it is per-visit |
| `new` | "of which new" — the delta band | needs the previous run |
| `cost.quota` | the quota chip | no metadata exists anywhere |
| `cost.external` | "leaves the building" | no metadata; a curated map, and §11 says it belongs next to the module list, not here |
| `pending` | the header's "awaiting review" | counts undismissed elements; no dismissal store |
| `service.checked` | "last checked at" | reachability is now measured in the request that renders it |

`elements` survives but changes meaning: it was "returned *and still
standing*, dismissals subtracted", and becomes simply what this run
returned.

---

## 6. Build log

Built 2026-09-06. Nine files.

| File | What |
|---|---|
| `app/Model/ValueProfile.php` | the Enrichment section: `forEnrichment`, `forEnrichmentRun` and six privates, plus `ENRICHMENT_ELEMENT_CAP` and the badge drop in `forTabCounts` |
| `app/Controller/ValuesController.php` | `viewEnrichment` converted, `viewEnrichmentRun` added, two `beforeFilter` lines |
| `app/Controller/Component/ACLComponent.php` | the new action's entry |
| `Values/view.ctp` | the Enrichment badge dropped |
| `value_enrichment.ctp` | rewritten — four states and the split |
| `value_enrichment_rail.ctp` | rewritten — one row per module, no grouping |
| `value_enrichment_brief.ctp` | new — what a module would be asked |
| `value_enrichment_button.ctp` | new — the one enabled control on the page |
| `value_enrichment_result.ctp` | new — the run's answer, six states |
| `value_enrichment_pane.ctp` | **deleted** (809 lines) — it rendered staged, dismissed and delta states that no longer exist |
| `value-profile.js` | the tab's block rewritten; 101 lines of tray/select/delta handling removed |
| `value-profile.css` | `.vp-e-val` turned from a withheld bar into a value |

### 6.1 `.vp-e-val` was a hatched bar, and had to stop being one

The fixture rendered every returned value as a **withheld bar** — a
fixed-width hatch — and that was right for the tab it was drawn for:
nothing had queried anybody, so inventing what a third party would have
said was "the one thing this tab must not do". Live, the value *is* the
answer, and a placeholder over real content stops being a scruple and
becomes an obstruction. Caught by looking at the rendered page rather
than at the fragment: the first screenshot showed `passive-dns` records
with every value hatched out.

It now wraps and breaks anywhere, because a returned value is arbitrary
text from somebody else's service and the pane has to absorb a long TXT
record rather than push the panel into horizontal overflow.

### 6.2 Three headings printed `%d`

`__n()` picks the plural form; it does not substitute. `%d attribute`
rendered literally as **`%D ATTRIBUTE`** in three headings. Same
screenshot, same reason it was caught.

### 6.3 **A use-once CSRF token cannot survive this page** — the finding

The run is a POST (D4) and therefore needs a CSRF token, and the first
build embedded the one `SecurityComponent` minted for the panel's own
render. It worked, then it did not, then it did.

`generateToken()` is a read-modify-write on a single session key: read
`_Token.csrfTokens`, add the nonce just minted, write the map back.
**This page loads its panels lazily and in parallel** — around twenty
requests land together — so two overlapping requests both read the same
map and the later write drops the earlier one's nonce. The Enrichment
panel's token is as likely as any other to be the one dropped, and the
run it embedded a token for then blackholes at 400.

Measured, because *intermittent* is not a diagnosis:

| Path | Concurrency | Runs | 400s |
|---|---|---|---|
| Browser (`28-enrichment-check.mjs`) | ~20 parallel panel loads | 5 | **2** |
| Plain HTTP (`28-enrichment-fetch.py`) | none | 6 | 0 |

That gap is the whole tell: the defect is in the page's concurrency,
not in the endpoint. Fixed with `Security->csrfUseOnce = false`, scoped
to this controller — a stable per-session token, which is the
synchroniser-token pattern every later CakePHP uses. **Not a
weakening**: CSRF turns on an attacker being unable to *read* the token
cross-origin, never on its being fresh. Every concurrent write now
writes the same key, so the lost update stops mattering, and a reader
can run several modules without each press spending the next one's
token. Five of five after.

This is a page-wide hazard that happened to be found here, because this
is the page's first POST. Anything else that ever posts from this page
inherits the fix.

### 6.4 What the frame now says that the panel does not

The fact strip reads **3 types** on `8.8.8.8` where the panel reads
**4** — `text` is a type the reader holds two occurrences of and the
fixture's banner chips do not carry. This is the standing page-frame
hazard `value-profile-page.md` §1.4 records, not a new defect, and it
belongs to the Overview's phase along with the rest of the frame. Noted
because it is now visible on one screen: the chips and the panel
sub-line sit four inches apart and disagree.

---

## 7. Verification

Two harnesses, both re-runnable, plus the probe from §2.

### 7.1 `28-enrichment-fetch.py` — the endpoints

Logs in, fetches the catalogue for five value shapes, runs every module
each one offers, and asserts three refusals.

```
=== the catalogue ===
8.8.8.8                          200  12,783 bytes  rows=3 panes=4 ran=False none
github.com                       200   8,677 bytes  rows=2 panes=3 ran=False none
f1d3ff8443297732862df21dc4e57262 200   5,591 bytes  rows=1 panes=2 ran=False none
45.155.205.233                   200   8,876 bytes  rows=2 panes=3 ran=False none
no-such-value-anywhere.invalid   200   1,191 bytes  rows=0 panes=0 ran=False none

=== a run ===
8.8.8.8      circl_passivedns  200 ok      elements=200 capped=True
8.8.8.8      mmdb_lookup       200 ok      elements=4   capped=False
8.8.8.8      whois             200 error   elements=0
github.com   circl_passivedns  200 ok      elements=88  capped=False
github.com   whois             200 error   elements=0
f1d3ff…7262  hashlookup        200 ok      elements=2   capped=False

=== refusals ===
GET instead of POST             -> 405
POST with no CSRF token         -> 400
POST naming an unoffered module -> 200 ineligible
```

`ran=False` on every catalogue row is the tab's central promise checked
rather than asserted: **a GET runs nothing.** The `ineligible` row is
the ACL band — `hashlookup` is enabled and eligible for an md5, and
posting it against an IP is refused by the catalogue rather than by a
second opinion written beside the request.

### 7.2 `28-enrichment-check.mjs` — the page

```
light  accent=#48435C rows=3 panes=4 rail=416px pane=1058px overflow=0 shown=1
dark   accent=#a79dd4 rows=3 panes=4 rail=416px pane=1058px overflow=0 shown=1

walked 3 rows -> 0 requests (want 0)
ran mmdb_lookup: 1 request(s), state=ok row="Answered" dot=vp-e-dot-ok
```

**Both themes**, with the accent token asserted resolved before any
colour is read — §6.1's trap. **Walking every row makes zero requests**,
which is the promise phase 12 built the tab around and the one thing a
fixture could not be trusted to have preserved. One press makes exactly
one request, fills the pane it was pressed from and no other, and
paints its own row.

The harness prefers a **local** module (`mmdb_lookup`, ~100 ms off a
database on this host) over the alphabetically first one. Not
squeamishness: a harness meant to be re-run should not re-query somebody
else's service every time, and `circl_passivedns` began timing out
under exactly that.

### 7.3 The `perm_add` gate

```
perm_add=1 -> can_run=true   modules=3
perm_add=0 -> can_run=false  modules=3
```

`28-enrichment-probe.php perms`. The dev instance has **no role without
`perm_add`**, and adding one is a write to somebody's database for the
sake of a boolean, so the probe strips the permission from the loaded
user in memory and reads the flag the rail draws its control from. It
also clears `perm_site_admin`, which would otherwise mask the flag
being consulted at all.

`modules=3` either way is the design rather than an oversight: **the
catalogue stays readable to everyone.** What *could* be asked is not a
secret, and only the control that would ask is gated — which is this
page's standing treatment for a control the reader may not press.

### 7.4 The ACL entry

`queryACL/findMissingFunctionNames` lists nothing for `viewEnrichmentRun`.
The `values` entries it does return are the controller's private
helpers — `renderPanel`, `decodeValue`, `renderLivePanel` and the new
`runParam` — which is the check's standing noise rather than a gap.

### 7.5 The restored features (§8)

```
select all -> picked=3 count=3 ext=3 run_enabled=true cost=true requests=0
run 2 selected -> 2 requests (want 2), results=2 merged_items=4
                  all_pane_shown=true selection_cleared=true
  All results sub: "4 elements across 1 module"
furniture: known=20 action_groups=8 enabled_write_buttons=0 (want 0)
           folds=6 states=ok,error
fold toggles: false -> true
```

`Select all` makes **zero** requests, `Run 2 selected` makes **exactly
two** — the sequence, not a batch — and **no write button is ever
enabled**. The merged pane counts one module rather than two because
`whois` errored, which is the right answer and not a miscount.

### 7.6 Nothing wrote

Six real module queries in `28-enrichment-probe.php`, with `attributes`,
`objects` and `events` counted either side of each: **`wrote: NOTHING`
on all six.** §2.4.

---

## 8. The sweep — what the first build dropped that it should not have

Maintainer review, 2026-09-06, immediately after §6: *"you're missing
some features that were part of the mockup"*, naming select-all/run-all.
A sweep of `../value-profile-tabs/04-enrichment.md` §5–§10 against the
built tab found **seven**, not one.

**The error §5 hides.** That section lists nine fixture keys with no
live source and reads as though it were the whole of what came out. It
is not. Those nine are the ones persistence killed; the seven below had
nothing to do with persistence and were dropped on reasoning that does
not survive being written down. A removal list that only covers the
justified removals is worse than none, because it reads as an audit.

| # | Feature | Spec | Why it was dropped | Why that was wrong |
|---|---|---|---|---|
| A | `Select all`, `n of m selected`, `Run n selected` | §7, §10 | "a multi-module run is a request per module, and the queued path that would make one press safe is the one that writes" | **Conflates one module per *request* with one module per *press*.** The constraint is on the server; n sequential POSTs from the client honour it exactly and keep the feature |
| B | The `All results` merged row | §7 | dropped silently with the grouping | The grouping needed history; **merging does not**. §7 calls this "the one addition `E2` makes to the direction it came from" — the rail costs cross-module reading and this buys it back |
| C | `Already in MISP` | §8.3 | dropped with the `New since …` delta beside it | The delta needs a previous run; **this needs only the database now**. §8.3 calls it "what stops an analyst adding a duplicate", and §2 of this document had already identified it as live-able — then the build did not build it |
| D | `Add to event`, `New event`, `Dismiss`, `Add all` | §8, §10 | writes, so omitted | The page's rule is that a control which would write renders **visibly disabled**, never absent — "not implemented", "nothing to show" and "you may not" are three different things and a missing button says none of them |
| E | *Timed out* as a state | §9 | collapsed into *unreachable* | Two different facts. One is worth pressing again, the other is worth telling an admin. `queryModuleServer`'s `$throwException` recovers the distinction |
| F | Per-object expansion | §10 | dropped with the fixture pane | Nothing to do with persistence, and it matters **more** live: `circl_passivedns` returns 200 objects |
| G | The provenance line's kind and format | §8 | thinned to "asked as … · ms" | Both are known at run time and both were in the spec |

All seven are now built. What stays gone is exactly the nine of §5, and
§10 of the tab document is now accurate about which of its controls are
disabled rather than missing.

### 8.1 Three things the restore had to decide for itself

**`Run n selected` is sequential, and that is the design.** Firing the
selection in parallel would put n simultaneous outbound queries on the
instance's quota and leave the reader no way to stop after the first
answer. One at a time keeps the press honest about what it is doing,
and the rail shows it happening row by row. The button counts down —
`Running 2 of 3…` — and the tab lands on `All results` when it finishes,
because comparing them is why anybody ran several.

**The tray prices the selection in queries, not in quota.** Phase 12's
two chips were *n spend quota* and *n query a third party*, and neither
has any source: module introspection carries a name, accepted types, a
description, kinds and config keys, and nothing about money or rate
limits. What is knowable is how many separate queries leave the
building, which is the same thing the reader is agreeing to. One chip,
true.

**The probe's cost, measured.** `forEnrichment` stays at **Q=1**.
`forEnrichmentRun` goes from 3 to **4**, and to **5** on a capped
answer — `PREVALENCE_CHUNK` is 250, so 200 rendered objects carrying
~1,400 distinct values take two statements where four elements take
one. A run whose module errored costs 3 and a refused one costs 1. The
growth is in the *answer*, never in the value: `443` costs what
`8.8.8.8` costs, because neither number reaches this endpoint.
Re-measured by [`28-enrichment-count.php`](28-enrichment-count.php),
which now carries the capped case for exactly this reason.

> **§9.5 corrects the two paragraphs below.** The cap claim is wrong —
> a capped answer probes ~1,400 values against a 1,500 cap, not
> comfortably under it — and the defence of the untyped probe answers a
> question nobody was asking. The chip's real fault was probing values
> that are not identities at all, and the cost figures moved when that
> was fixed.

**`Already in MISP` is a claim about the value string, not about the
value under its type.** One probe for the whole result keeps this to a
single query; a per-type probe would be one query per distinct type.
`Value::prevalenceFor` is the instrument — built for another panel,
already ACL-scoped, already capped, and its 1,500-value probe cap is
well above this tab's 200-element render cap. Checked against the
instance rather than assumed: `mmdb_lookup`'s `United States` matches
`text` rows and its `38` matches `float` rows, so the untyped probe is
not in practice matching across types. The chip's title says *holds
this value*, which is the claim the code makes and the one §8.3 makes.

### 8.2 What the sweep confirms is correctly gone

Unchanged from §5, and all six are persistence: the staleness chips and
their `Never` variant, the three group headers, the delta band and its
`Show only new` toggle, the `Review all n` header action, the dismissed
footer and its `Restore`, and the awaiting-review count. Cortex as a
second rail is deferred rather than dead — it is a second service on a
second port with a second timeout, and §11 of the tab document already
called merging it "not free".

---

## 9. The result pane, drawn the way MISP draws an object

Maintainer review, 2026-09-06, after §8: *"use how we currently render an
object, but make sure its attributes are visible since they're important
part of the enrichment result"*, and then a polish pass over the panel.

**The finding.** §8's item F restored per-object *expansion* but not the
object. A returned object was a name, a count, a disclosure and a list of
`relation — value — type` lines, which is less than MISP shows for an
object anywhere else and less than the answer contains. And the reason
the analyst pressed Run is inside the object, never on its shell:
`mmdb_lookup` returns three objects, `hashlookup` one of eight rows,
`circl_passivedns` two hundred — and in every case the finding is an
attribute.

### 9.1 What the card now carries, and where it came from

`Objects/index.ctp` — MISP's own object accordion — is the source. The
head is the control, as it is there: the hexagon glyph, the template
name, the meta-category pill, the comment, the attribute count. The body
is the same attribute table: **Relation, Value, Type, Category, IDS**.

Three of those columns did not exist in the model's shape.
`enrichmentObject()` carried `name` and `relation/type/value` only, so
`meta-category`, `description`, `comment` and the attributes' `category`,
`comment` and `to_ids` are now carried too. Every one is null where the
module omitted it and the view draws only what arrived —
`mmdb_lookup` sends no comment, `hashlookup` no object comment.

**What a returned object has not got is absent, not blank.** No id, no
uuid, no tags, no galaxies, no sightings, no distribution, no related
events: those are `Objects/index.ctp` columns about a *stored* object,
and an empty Sightings column over an answer nobody has stored is a
question the object cannot have. The same rule runs per card: a Category
or IDS column no attribute in that object fills is dropped rather than
drawn over ten blanks, because ten blanks read as ten negatives.

### 9.2 The attributes are visible, and the budget is rows

Folding was decided on the object count (`<= 5` open). It is now decided
on **attribute rows**, budget 60, first object always open. `mmdb_lookup`
opens all three; `circl_passivedns` opens the first eight of 199, which
is where the reader is looking. Two controls cover the rest:

- **`Expand all` / `Collapse all`** on the objects heading, one press for
  the whole answer in both directions.
- **A folded head says what it holds** — its first three values, so no
  card is a shell somebody has to open to find out whether it is worth
  opening.

**The peek skips what cannot distinguish one card from another.** All 199
passive-DNS records say `origin: https://www.circl.lu/pdns/`; a relation
with one value across the whole answer is not offered to the peek, and a
value under three characters (`count: 1`, `rrtype: A`) is held back and
used only to top up a card that would otherwise have nothing. Measured on
the instance: the head went from `1 · https://www.circl.lu/pdns/ · A` to
`51cie.com · 2023-10-24T13:04:21 · …`.

### 9.3 The polish pass

- **A filter over the answer**, offered from 12 elements up. It hides rows
  already on the page and **asks nobody anything** — the tab's standing
  promise. A hit inside a folded object opens it; clearing the box puts
  every card back the way the reader left it, not the way the server sent
  it. A section whose rows are all hidden goes with them.
- **The head is a head.** Mark, claim, and the provenance line as separate
  chips rather than a middot-run: they are five unrelated facts and a
  reader wants one at a time. Four marks over seven states, and the mark
  never carries a claim the wording does not.
- **An element is two lines, not one that wraps** — the value at full
  width, then type, category, IDS and comment in a fixed order, so on a
  200-row answer the same fact is in the same place on every row.
- **Group headings** carry MISP's own glyph for the kind.
- **`.vp-e-cold` was scattering its children.** It is a `1fr 21rem` grid
  and the brief handed it five, so a module's description sat opposite its
  title and the ledger opposite the "nothing is written" note. Two
  children now, and the ledger's rows stack label over value — an opposed
  pair in a 21rem aside left the long ones as two words a line down a
  ragged gutter. The resting pane takes a one-column variant.
- **The merged pane wears a result's head**, because that is what it is.
- **Ten dead rule sets removed** from `value-profile.css` — `.vp-e-stale*`,
  `.vp-e-delta*`, `.vp-e-prov*`, `.vp-e-cost*`, `.vp-e-new`,
  `.vp-e-el-new`, `.vp-e-obj-new`, `.vp-e-quiet`, `.vp-e-withheld`,
  `.vp-e-disc`. All of them styled §5's fixture keys or §8.2's
  persistence features and none had a selector left in any view or script.

### 9.4 Three the status review turned up

Maintainer question after §9.3: *"what's still open for the enrichment
phase? Are we done?"* A read of `../value-profile-tabs/04-enrichment.md`
§7–§11 against the built tab found one defect and two gaps.

**A timed-out module said `Answered`, in green.** `ENRICH_STATES` in
`value-profile.js` carried six outcomes and `running`, and **not
`timeout`**; the lookup fell back to `ENRICH_STATES.ok`. So the row
claimed the best of the seven things it might have been while the pane
beside it correctly said the module had run out of time — undoing §8
item E, the restore whose whole point was that *worth pressing again*
and *worth telling an admin* are different facts. The fallback is now a
neutral `Unknown` rather than `ok`, for the same reason: a row that
cannot name what happened must not claim success. `28-enrichment-check`
did not catch it because it only ever reaches `ok` and `error`.

**The rail never said how much came back.** Spec §7's answered sub-line
is `6 elements`; the row carried the type, the kinds and the word
`Answered`, which is the same word for two elements and for two hundred
— on a rail built for reading modules against each other. The row now
carries the count, and a capped answer carries both numbers
(`200 of 1375`), because the one rendered is not the one that came back.
The result fragment ships `data-vp-e-shown` / `data-vp-e-total`, and the
wordings are declared once on the panel so the client is not inventing
English.

**A filtered section counted what it was sent.** `199 objects` stood
over twelve visible cards. A narrowed section now reads
`12 of 199 objects` and goes back to `199 objects` when the box is
cleared.

### 9.5 `Already in MISP` was crying wolf

Maintainer instruction after §9.4's list: *"fix the 'Already in MISP'
chip firing on trivial values"* — `count: 1`, `rrtype: A`,
`SSDEEP 3::`, `FileSize 4`.

**§8.1 defended the wrong thing.** It checked that the untyped probe was
not matching *across* types and concluded the chip was sound.
[`28b-known-probe.php`](28b-known-probe.php) put that to the test by
running an untyped probe beside one statement per distinct type, over
all three runnable modules:

| Module | distinct (type, value) | types | chips a typed probe would remove |
|---|---|---|---|
| `mmdb_lookup` | 8 | 2 | **0** |
| `hashlookup` | 8 | 7 | **0** |
| `circl_passivedns` | 3,181 | 3 | **0** |

**Not one.** MISP genuinely holds a `counter` with value 1 and a `text`
equal to `A`; the claim was true and useless. So type-scoping is not the
fix — it would have cost six statements more on `hashlookup` and removed
nothing.

**The fix is to ask about fewer values, and MISP already says which.**
The probe found `disable_correlation` on the wire for every module, set
from the object template, and the templates are a curated answer to
exactly this question:

```
passive-dns   count, origin, rrtype, time_first, time_last  -> disable_correlation
              rdata, rrname                                 -> correlating
file          size-in-bytes, filename, path, entropy        -> disable_correlation
              md5, sha1, sha256, ssdeep                     -> correlating
```

`enrichmentCorrelates()` reads that flag, and falls back to
`MispAttribute::NON_CORRELATING_TYPES` where a hand-built module omits
it, so a `counter` never wears the chip on anybody's say-so. A value
that fails the test is never probed and is `known => false`: the page
did not look, because there was nothing worth looking for.

Measured on the instance, chips per module before and after:

| Module | Rows | Chipped before | Chipped after | Kept |
|---|---|---|---|---|
| `mmdb_lookup` | 12 | 8 | **4** | `country`, `countrycode` |
| `hashlookup` | 8 | 6 | **4** | MD5, SHA-1, SHA-256, SSDEEP |
| `circl_passivedns` | 1,393 | ~600 | **0** | — |

The zero is honest rather than a silent loss: `rrname` and `rdata` are
correlating and still probed, and none of those domains is in this
instance. The four that survive on `hashlookup` are precisely the hashes
an analyst would be about to duplicate.

**It also costs less.** `PREVALENCE_CHUNK` is 250 and the cap is 1,500;
200 rendered `passive-dns` objects carried ~1,400 values, near enough
the cap to silently probe a prefix. Dropping the three relations of
seven MISP does not correlate on leaves ~800, and re-measured with
[`28-enrichment-count.php`](28-enrichment-count.php) the capped run
goes **Q=5 → Q=4**. Every other case is unchanged: `ok` 4, `error` 3,
`ineligible` 1, `forEnrichment` 1.

### 9.6 No module offers a per-query option, and why that is upstream

Maintainer question: whether any enrichment module declares options
meant for the *query* — a date range, say — as distinct from the API
keys that belong in the instance's settings.

**The mechanism exists and no expansion module uses it.**
`mispattributes.userConfig` is a typed, validated, per-run form
(`Module::CONFIG_TYPES`), and MISP merges it into the same `config`
dict the instance settings go into. Of the 146 modules the service
reports, 11 declare one and **all 11 are import modules** — `taxii21`
even has the date range, `added_after`, on the wrong side of the line.
Expansion or hover modules with a `userConfig`: none.

Nine expansion modules do carry query-shaping options, declared as
instance settings indistinguishable from credentials: `abuseipdb`'s
`max_age_in_days`, `virustotal`'s `event_limit`, `farsight_passivedns`'s
`limit`, `mmdb_lookup`'s `db_source_filter`, and so on.

**Exposing `meta.config` to the reader instead was considered and
rejected.** Several of those keys are URLs and hosts — `custom_API`,
`server`, `mwdb_url`, `api_url`, the `proxy_*` family — and MISP sends
the whole merged dict, so a reader who overrode `server` while `apikey`
kept its stored value would have the modules container post the
instance's credential to a host they chose. Not disclosing the stored
value does not help; the attack never reads it. Inferring which keys
are safe from the shape of the stored value does not work either:
**nine** `Enrichment_*` settings are set on this instance and not one is
a per-module query key, because unset is the normal state.

So the fix is a declaration upstream, and it is written up as
[`../misp-modules-query-options.md`](../misp-modules-query-options.md).
Nothing is built here: MISP's enrichment path does not read
`userConfig` either, and teaching it to is worth doing once something
declares one to render.

### 9.7 Verification

Against the dev instance as `admin@admin.test`, 2026-09-06, both themes:

| Case | Result |
|---|---|
| `mmdb_lookup` on `8.8.8.8` | 3 objects, 12 rows, all open, no panel overflow |
| `hashlookup` on an md5 | 1 object of 8 rows, IDS column drawn, uniform row heights |
| `circl_passivedns` on `8.8.8.8` | 199 objects + 1 attribute, capped 200 of 1,375, 8 open |
| Filter `circl.lu` | 199 of 200 shown, matches opened, attribute section hidden |
| Filter cleared | back to 8 open — the state the reader left, not the served one |
| `Expand all` / `Collapse all` | 199 open, label flips, 199 shut |
| `whois` on `8.8.8.8` | `error`, module's own message, `Run again` offered |
| [`28-enrichment-check.mjs`](28-enrichment-check.mjs) | unchanged: 0 requests on a 5-row walk, 2 requests for 2 selected, 0 enabled write buttons, folds toggle |

§9.4's three, verified by rewriting the real fragment on the wire so the
whole `enrichAsk` → `setEnrichState` path runs as it would:

| Case | Rail row |
|---|---|
| `mmdb_lookup`, `ok` | `Answered`, dot ok, **`4 elements`** |
| `circl_passivedns`, capped | `Answered`, dot ok, **`200 of 1375`** |
| `whois`, `error` | `Module error`, dot err, no count |
| state rewritten to `timeout` | **`Timed out`**, dot timeout — was `Answered`, dot ok |
| fragment with no result element | **`Unknown`**, dot none — was `Answered`, dot ok |
| filter `51cie.com` over 200 | headings `0 of 1 attribute`, `1 of 199 objects`; cleared, back to `1 attribute`, `199 objects` |

§9.5's chips, counted off the rendered object tables:

| Module | Object rows | Chipped | On what |
|---|---|---|---|
| `mmdb_lookup` | 12 | 4 | `country`, `countrycode` — not `latitude`, `longitude`, `text` |
| `hashlookup` | 8 | 4 | MD5, SHA-1, SHA-256, SSDEEP — not `FileSize`, `source` |
| `circl_passivedns` | 1,393 | 0 | nothing — `count`, `origin`, `rrtype` no longer asked about |

Query cost re-measured with the same script §8.1 used: the capped run
`8.8.8.8` / `circl_passivedns` goes **Q=5 → Q=4**, everything else
unchanged.

The `elements` branch — a `simplified` module answering with bare
`types`/`values` — is markup-identical to the attributes branch and was
not exercised live: no module eligible for the probe values both uses
that format and succeeds on this instance (`whois` is `simplified` and
errors for want of its `server` setting).
