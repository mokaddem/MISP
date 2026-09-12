# PRD: Analyst Profile — phase 7, enrichment defaults

**Built 2026-09-07 under D15 (shape B).** The profile *declares* which
modules matter for a type; the Enrichment tab arrives with them
**ticked, not run**; and every place the declaration and the instance
disagree is a stated condition. The badge the original ask named is
still blocked, and this document says why in §1 rather than deferring
the reason.

Depends on phase 1 ([`02-store.md`](02-store.md)) for the section.

## 1. The ask, and what blocks it

> *A profile also contains default enrichment modules to be run when
> opening a value profile page (result to be displayed in a badge at
> the top level).*

Three blockers, all pre-existing, all recorded in
`../value-profile-tabs/04-enrichment.md` §11 as that tab's own deferred
items. **All three still stand**, which is the whole reason D15 chose
declaration over behaviour.

### 1.1 Nothing records that a module ran

`Module` is `useTable = false`. There is no per-value, per-module
last-run timestamp anywhere in MISP. Without it, "run the default
modules on page open" means **run them on every page open** — nine
third-party queries per visit, per analyst, forever.

`../value-profile-writes.md` §6.4 proposes the answer: a plain cache
table keyed by value and module holding last run, by whom, status, and
the dismissed elements — deliberately kept out of the assertion stores
because it is evictable and instance-owned rather than org-owned.

**That table is the badge's hard prerequisite.** It is specified there
and built by nobody.

### 1.2 The interactive path is synchronous whatever the setting says

`Event::enrichmentRouter()` returns before its own `MISP.background_jobs`
branch — it returns at `Event.php:7997` and strands the branch at
`7998`. Only `POST /attributes/enrich` queues a job.

So a page that runs even three modules on load blocks the render on
three HTTP round trips to third parties, each bounded by
`Plugin.Enrichment_timeout` (10 s; Cortex 120 s). **The badge needs the
queued path**, which means either fixing `enrichmentRouter` or routing
this page through the queueing endpoint.

### 1.3 No cost or quota metadata exists

Module introspection carries `name`, `type`, `mispattributes` and
`meta` — and `meta` is `author`, `config`, `description`, `features`,
`input`, `logo`, `module-type`, `name`, `output`, `references`,
`require_standard_format`, `requirements` and `version`. **Measured
2026-09-07 across the dev instance's 146 modules**: not one field says
anything about money, rate limits, or whether asking the module tells
anybody outside the instance.

The cost half stays out of scope and stays where
`../value-profile-tabs/04-enrichment.md` §11 puts it — *"next to the
module list, not in this page"*. The **locality** half is what §3.1
shipped: a hand-maintained roster saying whether asking a module tells
anybody outside the instance.

> **Revised 2026-09-12 (`09b-revisions.md` 3.21).** Locality was built
> as the prerequisite for a *posture* — a setting that withheld a
> module whose locality was not local. The posture has been withdrawn,
> because under D15 nothing runs without a press and so it withheld a
> checkbox rather than a query. Locality stays and is drawn as a
> **label**: the reader consults it before pressing run. Every
> paragraph below that describes the posture as in force is marked.

## 2. What the profile declares

The section, and its resolution against instance policy.

```json
"enrichment": {
  "auto_run": {
    "ip-src":  ["virustotal"],
    "ip-dst":  ["virustotal"],
    "domain":  ["dns"],
    "md5":     []
  },
  "locality":         { "dns": "local" },
  "max_age_hours":    24
}
```

The `locality` override is measured rather than assumed: **`dns`
resolves through Google's `8.8.8.8`** unless
`Plugin.Enrichment_dns_nameserver` says otherwise (`dns.py:55`), so an
operator who repointed their resolver is the only party who can say the
module is local for them — which is why `locality` exists and why the
profile sits above the shipped roster.

**Revised 2026-09-12 (3.22): the shipped default is no longer empty.**
`default-v1.json` v10 declares thirteen types and twelve modules,
CIRCL-first. It still runs nothing and enables nothing; what it does is
**narrow** — a type it names arrives with those modules ticked and
every other module for that type unticked. The sentence this paragraph
used to carry, *the tab is the one phase 28 shipped until someone
chooses otherwise*, is no longer true and is not left standing.

**`auto_run` is keyed by attribute type in the document**, because
module validity is type-scoped — `Module::getEnabledModules($user, $type)` filters on
`meta.module-type` and the tab's own header names the type for exactly
this reason (*"9 modules valid for ip-dst"*). A value with several
types resolves the union, deduplicated, and a module declared under two
of them is **one** selection carrying both.

The type a selection would run under is the **declared** one wherever
the module accepts it, not the value's most common: an analyst filing
`virustotal` under `ip-dst` said which question they wanted asked. It
falls back to the row's default type when the declaration cannot be
honoured.

**The editor is keyed by module** since 3.19, and the document is not:
one row per module, offering only the types `mispattributes.input`
says it accepts. `AnalystProfileFormTool::transposeModules()` is the
single place that knows the two axes differ. The stored shape is
unchanged — every reader still speaks `auto_run`.

### The posture, withdrawn 2026-09-12

**`locality_posture`** was `local_only` (default) | `allow_external`,
and `local_only` withheld any module whose resolved locality was not
local. It was called `cost_posture` until 2026-09-10 and never gated
cost (3.17); `ask` was dropped in the same pass for being
byte-identical to `allow_external`.

**All of it is gone (3.21.)** The setting decided whether a module
arrived ticked, and under D15 a module that arrives unticked and one
that arrives ticked both send nothing until the reader presses — so it
bought a bucket, a condition id, a pane and a legend in exchange for
saving a click. `withheld` is gone from `resolve()`, `C_POSTURE` from
the conditions, and the pane from the editor. A stored
`locality_posture` or `cost_posture` key is **ignored, not migrated**:
it selected nothing, so there is nothing to carry. `legacyShapes()`
names it and the next save drops it.

What the strip says where the posture label sat is `leavingCount()`'s
number — *"3 of these would leave the instance"* — a fact about the
selection in front of the reader rather than a setting.

**`locality`** is the override map §3.1 needs — a module name to
`local` or `external`, empty by default, deferring to the shipped
roster. It is the one thing a map shipped in code can never know: an
operator who pointed `dns` at their own resolver, or `clamav` at
somebody else's, is the only party who can say so.

**`max_age_hours`** is the reuse window — how stale a cached result may
be before the page re-runs the module. Meaningless without §1.1's
table, so it is carried with `reuse_inert` beside it and phase 8's
editor states that it governs nothing yet. Carried rather than dropped,
so that adding the store later is not a format change to every stored
profile.

### 2.1 A profile can only ever narrow, never widen

`Module::getEnabledModules()` (`Module.php:111`) filters on three
things: the instance setting `Plugin.Enrichment_<name>_enabled`, the
requested type, and `canUse()` (`Module.php:412`) — which is
site-admin-always, else `Plugin.Enrichment_<name>_restrict` must be
empty or equal the user's `org_id`.

So the instance decides what exists and a profile picks from that set.
**Four** honest states follow — the specification named two and
building it found two more — and none may be a silent drop:

| Condition | What it means |
|---|---|
| `module.disabled` | the instance has it turned off |
| `module.restricted` | `_restrict` reserves it for another organisation |
| `module.not_offered` | no module of that name is in this build |
| `module.type_mismatch` | it is enabled and usable and does not accept the type it was filed under |

Plus two that are not about a single module: `type.unused` (the
profile names modules for types this value is not) and
`service.unreachable` (nothing could be checked this visit). And `module.unresolved`, which is the honest
non-answer when a caller did not fetch the facts — see §4.2.

Silently dropping any of them would mean a profile whose stated
enrichment policy is not the one in effect, which is the class of quiet
lie `01-profile.md` §1.3 forbids.

### 2.2 Recovering the reason costs a second call

By the time the tab has a catalogue, `getEnabledModules()` has already
discarded *why* a module is not in it. Recovering the difference means
one more `GET /modules` — 1–2 ms on the dev instance — and
`ValueEnrichmentTool::needsFacts()` exists so that it is paid **only
when a declared module is missing from the eligible set**. A
declaration that resolves cleanly pays nothing, and the shipped default,
which declares nothing, can never pay it. Measured in §6.

### 2.3 Three run states, two of them built. Decided 2026-09-10, D17

**The declaration gains a third state per module. Two ship; the third is
declared and not implemented.**

Before D17 `auto_run` was `type => [module names]` and yielded one
outcome: **selected** — the box arrives ticked, a human still presses.
"Cannot be run" is not a profile decision at all — only the instance
can forbid a module.

The shape becomes `type => {module: state}`, with three states:

| State | Meaning | Ships |
|---|---|---|
| `ticked` | arrives ticked, still needs a press — today's `selected` | **yes** |
| `never` | this profile will not run this module, at all | **yes** |
| `auto` | runs without being asked | **no — deferred** |

**Why `auto` is deferred.** Three reasons, and the third is the one that
is not just plumbing:

1. **There is still no last-run store.** This is D15's original reason
   and it has not moved: `Module` is `useTable = false`, nothing records
   that a module was asked about a value, so *"run the declared modules
   on open"* means running them on **every** open.
2. **The queued path is dead code.** `Event::enrichmentRouter()` returns
   before its `MISP.background_jobs` branch, so there is no working
   route to run enrichment off the request.
3. **Auto-run widens what a profile does**, and §2.1 says a profile may
   only ever narrow, never widen. Every other setting in this document
   removes something: fewer modules ticked, fewer leaving the instance.
   `auto` is the first that would make a profile *cause* outbound
   requests that would not otherwise happen — on somebody else's
   instance, under a profile they may have forked and forgotten. That
   needs a consent story, not a scheduler.

**D15 therefore stands**: nothing auto-runs. This decision does not
reverse it; it names the state so the schema is shaped for it.

**Why the schema lands now anyway.** Adding a third state later means a
second pass over the same map in every stored profile. The cost of
carrying an unimplemented enum value is a line in a validator; the cost
of migrating twice is not.

**`never` must be enforced server-side** — in `ValueProfile::enrichmentRun()`,
not merely by disabling the checkbox. The run endpoint takes a module
name from the request, so a view-only guard is not a guard.

**Back-compat:** a bare list keeps meaning *"every module named here is
`ticked`"*, which is exactly what it means today. Read both shapes.

#### Built 2026-09-10

`planFor()` normalises to `type => {module: state}` and reads both
shapes **per entry**, not per type, because a hand-edited document can
mix them: an integer key is a list entry and means `ticked`. A state
this version does not recognise reads as `ticked` rather than refusing
the module — the failure mode of strictness here is a page that will
not render, which is the rule the rest of this normalisation already
follows.

`resolve()` gains a **second bucket, `refused`**, beside `selected`.
(It was a third until the posture's `withheld` was withdrawn in 3.21.)
A refused module still counts as `applicable`, because they declared it and a
count that disagreed with the document would be the worse lie.

An `auto` resolves as `selected` and adds **one** condition
(`state.auto_inert`) naming all of them, not one each: they all failed
for the same reason and it is not about any particular module.

**The run guard.** `enrichmentRun()` built its catalogue with no
profile deliberately — a *selection* is a preference and must not
decide what the instance offers. `never` is the exception, and the
comment there now says why: it is the reader's own refusal, and the
endpoint takes a module name from the request. The check needs the plan
only, not the modules service, so it costs one profile read and no
second `GET /modules`. A refused run returns the new state
**`profile_refused`**, worded in the result pane as the reader's own
choice rather than a restriction — it is the only refusal there they
can lift themselves.

**The editor.** `enrichment.auto_run` stays a `map` block; no fifth
block kind was invented. Its `value_type` moves from `multiselect` to
`module_states`, and a row carries `state_options` (all three) beside
`states_built` (the two that work), so a design cannot draw `auto` as
though it ran. POST semantics are unchanged and now asserted: with
`__present` on a type's map the modules not posted are removed, and
without it on the outer map the types the form never showed survive.

## 3. Locality: which modules answer from inside

### 3.1 The knowledge ships as code, and cannot be derived

`ModuleLocality` (`app/Lib/Tools/ModuleLocality.php`) is V1: a static
roster of the modules that answer without anything leaving the
instance, following `WarninglistCategory`'s V1 and `GalaxyColour`
before it — canonical knowledge shipped as code, deterministic, no
migration, no store.

**The obvious heuristic is wrong in both directions**, and that is
measured rather than argued:

- **`countrycode`** declares `config: []` and `requirements: []` and
  fetches `http://www.geognos.com/api/en/countries/info/all.json` over
  plain HTTP to expand a ccTLD.
- **`clamav`** takes one config key and reaches nothing but the `clamd`
  socket the operator pointed it at.

So the same introspection shape says both things, exactly as phase 6's
fixture put `7` in two weight bands and thereby foreclosed a derived
band (**D14**).

### 3.2 The test that decides membership

**Does anything about this value reach a party the instance operator
does not control?**

- **No → local.** Pure computation (`extract_url_components`), a local
  file (`geoip_*` read Maxmind's database off disk), or an endpoint
  that can only ever be the operator's own — `clamav` has no default
  connection string at all.
- **Yes → external**, and *configurable* is not *local*:
  `mmdb_lookup` defaults to CIRCL's `ip.circl.lu` and `dns` to
  `8.8.8.8`, so both leave the building on a deployment nobody has
  configured. An operator who has repointed one says so in
  `enrichment.locality`.

The roster is 22 modules: the parsing and syntax ones, the attachment
readers, the three `geoip_*`, and `clamav`. It is short because most
enrichment *is* a lookup against somebody else's data — that is what
enrichment is for — and the asymmetry is the honest shape of the
platform rather than a gap in the reading. §7.4 is what it costs.

### 3.3 An omission is the safe direction, and there are omissions

A module the roster does not name resolves `unknown`, and `local_only`
treats `unknown` exactly as `external`. So the failure mode of an
incomplete map is *a local module that does not auto-select*, never *a
value quietly sent somewhere*.

That the map is incomplete is measured, not hoped: the roster is the
modules whose source was read, and **reading source is not proof
either** — `socialscan` shows no outbound call in its own file because
the library it wraps makes them. The map carries what it can defend.

### 3.4 Retirement

`ModuleLocality::retirable()` is the mechanical criterion, in code so
that *"can this file go?"* is answered by an instance rather than by
reading a roadmap. It is satisfied when **every module the instance
offers** declares its own locality in introspection — not merely the
ones this map names, because a field that exists for 22 modules and not
the other 124 leaves `unknown` meaning two different things, which is
the state this file exists to avoid. Upstream would be a `meta` field
in `misp-modules`; nobody has proposed one.

## 4. What it looks like

### 4.1 The rail arrives ticked

The declared modules are pre-selected checkboxes, each carrying the
declared run type, with a `profile` chip on the row so that a reader
who has never opened the editor can see where the selection came from.
The press is unchanged: **nothing runs on arrival**, and the tray's
`Run n selected` is the same control it always was, defaulting to the
analyst's own list instead of to nothing.

The tray's cost line is now honest per module rather than per
selection. It has said *"n queries leave this instance"* since phase
28, counting every ticked box, because nothing knew better; a selection
of local modules now says *"n queries, none of which leave this
instance"* instead. **Two states, though `ModuleLocality` has three**
— §7.3 is why.

### 4.2 The strip states the conditions, above every empty state

`value_enrichment_profile.ctp` renders between the panel header and the
tab's branch, silent when nothing is declared. It is above the branch
because the condition it most needs to carry is the one with no rail to
hang it on: a profile naming a module the instance disabled, on a value
where no other module is eligible, would otherwise leave the reader
looking at *"no enabled module accepts this value's types"* while their
own profile names one.

Each condition names the module, and the sentence says what a reader
can do about it. Two deliberate refusals:

- **A near miss is named, never substituted.**
  `Plugin.Enrichment_<name>_enabled` is an exact key, so treating
  `VirusTotal` as `virustotal` would be the page enabling a module the
  profile did not name. The condition says *"It does offer
  virustotal"* and leaves the edit to the reader.
- **`module.unresolved` is a real branch, not a defensive one.** A
  caller that skips §2.2's second call gets *"not available, and why
  was not established"* rather than one of the four reasons picked at
  random.

## 5. Verification

1. `auto_run` set for three types; the profile round-trips through
   save, export and import unchanged. **Live probe** — the section
   comes back out of `resolveFor()` with its integer window intact.
2. A module named that the instance has disabled: stated condition, no
   error, and the tab's rail is unaffected. **Live probe**, on
   `virustotal`, which is in the build and off here.
3. A module named that `_restrict` reserves for another org, viewed as
   a member of neither: stated condition. **Live probe**, as
   `orgadmin@circl.lu` with the restriction written in-process — and
   with the site admin's reading asserted beside it, because `canUse()`
   passes them through every restriction and telling them the module
   was reserved away would be false.
4. ~~`locality_posture: local_only` with an external module in
   `auto_run`~~ — **withdrawn 2026-09-12 (3.21)**, along with the
   posture itself. There is no conflict left to state: an external
   module is selected like any other and carries its locality for the
   tab to draw. What replaced the check is that every selection
   reports `locality` and `locality_source`, asserted in the harness.
5. Nothing runs. No third-party request is made by any page load.
   **Asserted three ways**: the row counts either side of every call,
   the modules service's own request log read from outside the process
   (`POST /query` **3 → 3** across 16 catalogue builds), and a run with
   the service unreachable, which is indistinguishable from a
   reachable one when nothing is declared.

Plus what the specification did not name and building it required:
the union over a value's types, the run type, the four-way precedence,
the near miss, the locality override end to end, and the second call
being paid only where a condition needs explaining.

## 6. How it is verified

- **`08-enrichment-harness.php`** — 121 checks, no database and no
  modules service. The resolution arithmetic, every condition id, the
  normalisation of a hand-edited document, and the invariants that are
  structural rather than numeric: an empty declaration produces nothing
  at all, a document still carrying either retired posture key resolves
  **byte-identically** to one without it, and what the shipped default
  declares is read off `default-v1.json` rather than restated — every
  module checked against the types it says it accepts, because a
  shipped default that filed a module under a type it cannot answer
  about would be the one profile nobody edits and everybody inherits.
- **`08-enrichment-live-probe.php`** — 46 checks under `run` and 9
  under `unreachable`, against real rows, real settings and the real
  modules service. Writes no profile: every declaration goes through
  `forEnrichment()`'s `profile` option, which is the seam phase 8's
  editor needs.
- **`08-enrichment-render.php`** — the tab rendered in five states
  (nothing declared, `local_only`, `allow_external`, a broken
  declaration, service down), because *"the boxes arrive ticked"* is
  not a claim an assertion about an array can settle.

## 7. What building it found

### 7.1 The instance's modules port was wrong, and the probe nearly passed anyway

`Plugin.Enrichment_services_port` was `6677` on the dev instance while
misp-modules listens on `6666`, so every enrichment surface there —
this tab included — was reporting a dead service. Phase 28 measured the
same tab at 9 ms a day earlier, so the setting moved in between.

**The first probe run reported 24 failures and 21 passes, and the
passes were the dangerous half**: with nothing reachable, *"the shipped
default changes nothing"* and *"nothing is selected"* both hold for the
wrong reason. So the probe gained a preflight that corrects the port
**for its own process only** and refuses to continue if that does not
recover the service. It is phase 5 §7.5's lesson in a new place — there
the datasource log had stopped recording and the probe cheerfully
reported *"0 queries, 0 touching decaying_models"*.

### 7.2 One fact, one producer — caught by an assertion about wording

`resolve()`'s first version computed each module's locality itself,
from the same map and the same overrides the catalogue row already
carried. The harness's check that *an unclassified module is not told
it leaks* failed, because the tool called both the external module and
the unclassified one `unknown` while the row said one of them was
`external`.

They would have agreed in production — both paths call
`ModuleLocality` with the same overrides — and *"they agree today"* is
what `../value-profile-page.md` §1.4's frame hazard sounds like every
time before it stops being true. The withholding decision now reads the
row (`localityOf()`), so the chip on the rail, the number in the tray
and the reason in the strip are literally one value. The harness
asserts it from both sides: an override reaches the decision through
the row, and an override the rows did not carry does not sneak in
afterwards.

### 7.3 The third locality state cannot survive contact with the tray

`ModuleLocality` has three states and the tray was built with three
sentences, the third being *"at least n of m queries leave this
instance"* for a selection containing an unclassified module.

Measured on the dev instance, that is **the normal case, not the
exotic one**: 1 of the 5 modules eligible for `8.8.8.8` is on the local
roster and the other 4 are unclassified, so the honest-looking line
reads *"at least 0 of 4"* — which understates the presumption the whole
design runs on. An enrichment module enriches from somewhere else
unless it is known not to.

So the tray has two states and rounds `unknown` into *leaves*, while
the per-module chip keeps all three: *asking it tells somebody* and
*nobody has established whether asking it tells somebody* are
different facts, and only one of them is a reason to go and classify a
module. The rounding is in the safe direction and it is the claim the
tab was already making about every module before this map existed.

### 7.4 `local_only` selected almost nothing, which is why it is gone

The local roster is dominated by attachment readers and syntax
validators, which a *value* page rarely has a type for. On `8.8.8.8`,
with 5 eligible modules, exactly one was local — and it was on the rail
only because the value happens to carry two `text` occurrences, which
is what made `convert_markdown_to_pdf` eligible.

This was written up as *`local_only` doing precisely what it says on a
platform where enrichment means asking somebody else*. **Read again in
2026-09-12's light, it is the finding that condemned the setting.** A
default that selects nothing on almost every value is not a safe
default, it is an inert one: the reader ticks the boxes by hand, sends
exactly the same queries, and the only thing the posture achieved was
the clicking. Under D15 it could never have achieved more, because
**a tick is not a query** — the press is.

What the finding really established is that locality is worth
*knowing* and not worth *gating*, which is what shipped: the chip on
the rail, and `leavingCount()`'s line on the strip.

### 7.5 `ask`, and then the whole setting

`ask` meant *check with me before spending this*, and a page where
every run takes a press is already asking. It was kept as a separate
value on the argument that it would diverge the moment anything ran
without a press — which is what §1.1's missing store would unblock.

**Retired 2026-09-10**, because the argument was for a divergence that
never arrived and a three-option select where two options behave
identically is its own defect.

**The other two followed on 2026-09-12 (3.21)**, and for the same
reason one step further on: if `ask` was `allow_external` because the
press is the asking, then `local_only` was `allow_external` with extra
clicking, because the press is also the sending. The setting is
withdrawn entirely. A stored key of either name is ignored — it
selected nothing, so there is nothing to migrate — and named once by
`legacyShapes()` so a reader is not left wondering where their setting
went.

### 7.6 A diagnostic that counts prose is worse than none

The render shell reported *"ticked: 1 of 0 checkboxes"* on the
service-down case. There were no checkboxes and no ticks: it was
counting the string `checked` in the strip's own sentence, *"none of
these could be checked against what this instance offers"*. Recorded
because the same shape — a substring count standing in for a structural
one — is how a verification tool starts confirming itself.

### 7.7 The tab's own deferred list shrinks by half a bullet

`../value-profile-tabs/04-enrichment.md` §11's cost bullet ends *"the
map should live next to the module list, not in this page"*. Half of it
now exists and lives exactly there: `ModuleLocality` is a fact about
modules, keyed by module name, next to the module list and not in the
page. The other half — rate limits and credits — is untouched and stays
out of scope.

## 8. Out of scope

- Building `../value-profile-writes.md` §6.4's cache table.
- Fixing `Event::enrichmentRouter()`.
- The cost and quota half of the metadata map (§1.3).
- Enrichment *results* becoming occurrences. `../value-profile-writes.md`
  §6.4 has the answer — a new event owned by the analyst's own org —
  and it is a write, which this feature does not do.
- **The badge.** Under D15 there is nothing to badge: no run has
  happened, and a badge saying *"3 modules selected"* in the frame,
  fed by a different code path from the tab it summarises, is
  `../value-profile-page.md` §1.4's frame hazard by construction. When
  §1.1's store lands, the badge reads from the same aggregate as the
  tab or it does not ship.
