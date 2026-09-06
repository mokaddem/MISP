# Enrichment: MISP against the other TIPs

**Investigated 2026-09-06**, the day after phase 28 took the Value
Profile's Enrichment tab live stateless
([`value-profile-live/28-enrichment.md`](value-profile-live/28-enrichment.md)).
Two questions: what do the professional tools do that we do not, and
what should we build next.

Every MISP number below was read off the dev instance or the source
tree on that date, not recalled. Every competitor claim is sourced in
§9, and §8 says which ones are documentation and which are weaker.

---

## 1. Why this doc exists

The tab shipped with six state-bearing features deliberately removed
because nothing in MISP could source them: a last-run timestamp,
staleness, the delta band, dismissals, the awaiting-review count, and
the cost chips. That was the right call for the phase — a fixture
staleness chip beside a live result is a lie — but it left an open
question the phase doc could not answer from inside MISP: *are those
features table stakes elsewhere, or did we drop things nobody has?*

The answer is mixed, and useful. Two of them are table stakes and one
of them is rarer than we assumed.

---

## 2. What our tab does today — the baseline

So a future reader does not have to reconstruct it from 45 kB of phase
docs.

- **A rail of the modules eligible for this value**, from a live
  `GET /modules` on misp-modules. Eligibility is a **union over the
  value's types**, so a module eligible via three of them is one row,
  and the row names the type a run will use (the most frequent, since
  `Value::typesFor` orders by occurrence count).
- **Each row states what the module *is*** — kind, format, that type —
  and never what it will do. There is no "misconfigured" chip; §2.3 of
  the phase doc killed it because it would have been wrong on two of
  the three modules that would have worn it.
- **Nothing auto-runs.** Not on page load, not on tab switch, not on
  selecting a row. Walking the whole rail makes zero requests, checked
  in a browser.
- **A run is a POST** (`viewEnrichmentRun`), one module per request,
  backed by the reader's own first visible occurrence of that type so
  the `enrichment-before-query` workflow trigger still bites. A batch
  is *n* sequential POSTs, never a batch request.
- **Six states, all the same object** — never run, running, answered,
  silent, timed out, module error. *Silent* ("queried, nothing back"),
  *timed out* and *service down* are three different things and read
  differently.
- **The result is drawn as a MISP object** — object template name,
  relation, type, `to_ids` — with `Already in MISP` marking a returned
  attribute that would be a duplicate. The pane caps what it renders
  and states the total beside the cap.
- **Nothing writes.** Verified by counting `attributes`, `objects` and
  `events` either side of six real module queries. `Add to event`,
  `New event`, `Dismiss` and `Add all` all render visibly disabled.

Instance measurements that matter to the comparison:

| Fact | Value |
|---|---|
| Modules in the catalogue | **157** (9 ms to list) |
| Enabled on this instance | **7** (3–4 ms) |
| Modules declaring `ip-src` / `domain` | 47 / 39 |
| `8.8.8.8` | 4 types → 3 eligible modules |
| Largest result seen | `circl_passivedns` on `8.8.8.8` — **1,374 objects in 4.9 s** |
| `Plugin.Enrichment_timeout` here | 30 s (Cortex 120 s) |
| Enrichment result cache, anywhere in MISP | **none** |

That last row is the finding this investigation turned up that the
phase doc did not have. `app/Model/Module.php` contains no cache of any
kind, and neither does `AttributesController::hoverEnrichment` — so the
hover popover re-queries the third party on **every mouseover**, and
has always done so. The tab is not uniquely stateless; MISP is.

For contrast, `app/Model/ValueProfile.php` already caches its expensive
reads in Redis under `misp:value_profile:relation_digest`,
`:relation_scan` and `:relation_references`. The mechanism exists in the
same model. Nothing has pointed it at module results.

---

## 3. The tools, and what each actually does

### 3.1 Cortex / TheHive (StrangeBee) — the closest analogue

The tool whose model is nearest to ours, and the one that has solved
most of what we deferred.

- **Report cache with a TTL.** `cache.job` — default **10 minutes** —
  suppresses re-execution of the same analyzer on the same observable
  inside the window. The global value is **overridable per analyzer**
  in the analyzer's own config dialog, which is the right granularity:
  a passive DNS answer and a sandbox verdict do not age at the same
  rate.
- **Taxonomy short reports.** Each analyzer implements `summary()` and
  returns taxonomy labels — reputation, behaviours, MITRE techniques,
  CVEs — which render as small coloured badges (`VT:Score = 3/60`).
  **You read the verdict without opening the report.** This is the
  single biggest UX difference between their list and our rail.
- **Rate limiting as first-class config.** Quotas per organisation,
  plus per-org analyzer configuration and rate limits, explicitly so
  one org cannot drain a shared API key.
- **Jobs are async, with IDs, and reports are retained per observable**
  — so an analyst sees that an analyzer ran before, when, and what it
  said.

### 3.2 OpenCTI (Filigran)

- Manual enrichment is a **cloud icon** at the top right of an entity
  that opens **a side panel listing the connectors available for that
  object**. Activating one contacts the remote source and imports data
  — which may create relationships, add external references, or
  complete the object's own fields.
- Automatic enrichment is a per-connector **`auto: true|false`** flag,
  and the docs themselves **advise against it for quota-based paid
  connectors**, because it drains quota and inflates data volume.
- **Playbooks** (automation) route a STIX 2.1 bundle to a named
  enrichment connector and take the modified bundle back, which is how
  targeted rather than blanket enrichment is expressed.
- **What the documented enrichment panel does *not* carry:** a
  last-enrichment timestamp, per-connector run history, per-connector
  run status, or any quota/cost indication. Run state lives in the
  admin connectors view, not beside the observable.

So the thing we were most worried about being behind on — per-module
state next to the value — is something OpenCTI's enrichment panel does
not show at all.

### 3.3 Cortex XSOAR (Palo Alto)

- **Enrichment cache with a long TTL:** default **4,320 minutes — 3
  days** — after an indicator is updated, configured in the **indicator
  type profile**, so the TTL is a property of *what the indicator is*
  rather than of the source. Notably it applies **only to automatic
  enrichment** (`enrichIndicators`); a manual reputation command
  (`!ip`) bypasses it. That split is exactly right and worth copying:
  the analyst who explicitly asks gets a fresh answer.
- **DBotScore** — a verdict per indicator carrying indicator, type,
  vendor and score, i.e. the vendor is part of the score record.
- **Reliability-weighted merge.** When two sources give different
  values for one field, the value from the source with the **highest
  reliability score** wins; equal reliability falls back to **most
  recent**. Conflict is resolved rather than displayed.

### 3.4 ThreatConnect

- **CAL** (Collective Analytics Layer) enrichment on the indicator's
  Details card, in a **ThreatAssess & CAL** section, with a reputation
  score **out of 1000**.
- Enrichment adds **impact factors and classifiers**: observations,
  false positives and impressions aggregated across all communities and
  sources. **Recent real-network observations move the score**, up or
  down depending on the indicator's nature.
- The interesting structural point: enrichment is not a blob attached
  to the indicator, it is *inputs to a score that the page explains*.

### 3.5 EclecticIQ Intelligence Center

- **Enrichers** plus **enricher rules**: the enricher fetches, the rules
  sift the response and link it to entities as **enrichment
  observables**, which then fuse into the platform-wide dataset. The
  extraction step is configurable, not implicit.
- **Rate limits are product features**: a data rate limit and a
  **monthly execution cap** per enricher.
- **A hard cap of 50 observables or entities per enricher run**, for all
  enrichers. Our D8 render cap is the same instinct, arrived at
  independently after `circl_passivedns` returned 1,374 objects.
- Concurrency-limit failures are retried quietly, up to five times —
  i.e. the enricher owns its own backoff.

### 3.6 Recorded Future — the model for explaining a verdict

- **Risk score 0–99** in named bands: Very Malicious 90–99, Malicious
  65–89, Suspicious 25–64, Unusual 5–24, No Current Evidence of Risk 0.
- **The band is set by the highest-severity currently-triggered risk
  rule**; additional lower-severity rules nudge the score up within it.
- **Each rule triggers on specific collected evidence, and each ages out
  independently.** The Intelligence Card shows a **timeline of triggered
  rules** with the supporting sources linked back to the original
  documents.

That combination — a score that is a *function of named, aging,
individually-sourced pieces of evidence* — is the pattern worth stealing
for MISP's disposition, because it never asks the analyst to trust a
number.

### 3.7 Maltego

Credits are consumed per transform, and costs vary by transform. Worth
naming only because it is the industry's clearest statement that
*running an integration costs money and the analyst should see it
before committing* — which is what our dropped cost chips were for.

### 3.8 The OPSEC dimension

Not a product feature anywhere I found, but it is the argument for our
most unusual design choice. The distinction analysts actually care
about is **passive versus active**, and it is not the same as
free-versus-paid:

- A VirusTotal hash **lookup** is passive; **uploading** the file is an
  OPSEC failure, because a unique sample appearing in VT tells its owner
  they have been found.
- PassiveTotal (later RiskIQ Community) performed near-immediate DNS
  lookups against every domain searched — so a nominally passive tool
  fired live traffic at adversary-controlled infrastructure.

A module list that does not say which modules leave the building is
asking the analyst to hold that map in their head. **This is the real
content of the `Third party` chip we dropped**, and it is a safety
feature, not decoration.

---

## 4. Where we are already ahead

1. **Per-module state beside the value.** Four post-run states that read
   differently, plus service-down as its own condition. OpenCTI's panel
   has none of this; most tools collapse silent / timed out / errored
   into one red job.
2. **Letting the module answer instead of predicting failure.** `whois`
   fails in 7 ms with its own message and no external call. Cheaper
   *and* more honest than a pre-emptive badge.
3. **Multi-type eligibility.** A MISP-specific problem — one value is
   several attribute types — handled explicitly, with the chosen type
   named. One observable = one type elsewhere, so peers never face it.
4. **Results in the platform's own vocabulary.** Object template,
   relation, type, `to_ids` — not a vendor-shaped card per integration,
   which is what XSOAR and ThreatConnect render. `Already in MISP` has
   no equivalent I found anywhere.
5. **Read-only by construction, and provably so.** Peers write on
   enrich by default: OpenCTI creates relationships and external
   references, XSOAR merges fields into the indicator. Ours writes
   nothing and the write controls are visibly disabled — a half-feature
   today, but the safe half.
6. **Nothing auto-runs, by default rather than by flag.** OpenCTI has
   to warn users away from `auto: true`; our default is the careful one
   and the eager path does not exist yet.
7. **Honest capping** — cap the render, state the total, never let the
   capped number read as the whole. EclecticIQ hard-caps at 50 and
   agrees.

---

## 5. What they have that we do not

Ordered by how much it costs us.

| # | Gap | Who has it, concretely | Where we stand |
|---|---|---|---|
| 1 | **Cached result with a TTL** | Cortex `cache.job`, 10 min default, per-analyzer override. XSOAR, 3 days, per indicator type, automatic runs only | Nothing, anywhere in MISP — including hover |
| 2 | **A one-line verdict per module, before opening it** | Cortex taxonomy short reports from each analyzer's `summary()` | Rail says "6 elements", not what the answer was |
| 3 | **Enrichment feeding an explainable score** | Recorded Future risk rules with aging evidence; ThreatConnect CAL/ThreatAssess out of 1000; XSOAR DBotScore | This page has a disposition engine and enrichment contributes nothing to it |
| 4 | **Async jobs with progress and retained history** | Cortex jobs; OpenCTI connector works | Synchronous under a 30 s timeout; a batch is *n* blocking POSTs; `enrichmentRouter()`'s background branch is unreachable code |
| 5 | **Cost, quota and egress metadata** | EclecticIQ rate limits + monthly caps; Cortex per-org rate limits; Maltego credits | Chips dropped — module introspection carries no such field |
| 6 | **Policy-driven automation** | OpenCTI playbooks, XSOAR, ThreatConnect | We have the *guard* (`enrichment-before-query`) but no policy-driven run path |
| 7 | **Conflict resolution across sources** | XSOAR: highest source reliability wins, most-recent breaks ties | Two disagreeing modules are shown side by side with nothing saying they disagree |
| 8 | **Bulk enrichment across many values** | Every TIP | One value — correct for this page, but the capability gap is real |

On (7), showing both is arguably more MISP-like than picking a winner —
we do not hide sources. But there is no affordance that says *these two
disagree*, and that is a gap regardless of how it is resolved.

---

## 6. What to build next

In order. Effort is relative, and the blocker column is the honest part.

| | Work | Why it is first | Blocker |
|---|---|---|---|
| 1 | **Module-run cache in Redis** — key on value + module + type, TTL configurable per module, short default, and *bypassed on an explicit re-run* (XSOAR's split) | Unlocks "ran 2 h ago", the staleness chip, and makes re-run an act rather than the only act. Also fixes hover, which re-queries on every mouseover. **No schema.** `ValueProfile.php` already caches under `misp:value_profile:*` | none |
| 2 | **A summary line per module in the rail** | The biggest readability win available; turns a rail you click through into one you scan | Needs a misp-modules contract — a short `results.summary`, Cortex's `summary()` is the working model. Goes upstream beside [`misp-modules-query-options.md`](misp-modules-query-options.md). Interim: derive a weak line (*n* objects · *n* attributes · first `to_ids` type) and label it derived |
| 3 | **Queue the run** so a batch is one job with progress | Decides whether the tab survives an instance that enables broadly — `ip-src` alone is declared by 47 catalogue modules | `Event::enrichmentRouter()` returns above its own `MISP.background_jobs` branch at `Event.php:7997`; only `POST /attributes/enrich` queues |
| 4 | **Dismissal and the delta band** | The two features whose absence an analyst notices second | A schema phase. Cheaper after (1): the store then holds *decisions*, not results |
| 5 | **`meta.egress` / `meta.quota` upstream**, then the two cost chips return honestly | It is a safety feature (§3.8), not decoration | All effort is upstream and social; the tab change is trivial |
| 6 | **Feed the disposition** — enrichment as named evidence with its own aging, Recorded Future-style | The strategically interesting one | Requires (1); design belongs with [`value-profile-verdict-engine.md`](value-profile-verdict-engine.md) |

(1) and (2) change how the tab feels. (3) decides whether it holds up on
a real instance.

---

## 7. Two ecosystem observations

**157 modules, 7 enabled.** Our catalogue is larger than most
commercial integration lists; our out-of-the-box *configured* set loses
to all of them, because every module is gated behind
`Plugin.Enrichment_<name>_enabled` and an instance has to know to turn
it on. A shipped **recommended enrichment set** — a handful of
no-credentials-required modules enabled by default — would do more for
perceived quality than any change to this tab.

**Enrichment quality is a misp-modules question more than a MISP one.**
Three of the six items in §6 are blocked on module metadata that does
not exist: a summary contract, an egress flag, a quota hint. MISP core
can only render what modules declare. That makes the misp-modules
proposal channel the highest-leverage place to spend effort, and there
is already a live proposal there to attach to.

---

## 8. Confidence, and what to re-check on revisiting

Vendor products move; this doc will age unevenly.

**Solid — vendor documentation, quote-level specifics.** Cortex
`cache.job` and taxonomy short reports; OpenCTI's enrichment panel,
`auto` flag and playbooks; XSOAR's 3-day cache, its automatic-only
scope, and reliability-weighted merge; EclecticIQ's rate limits and the
50-per-run cap; ThreatConnect's CAL/ThreatAssess scoring.

**Solid — read directly.** Every MISP number in §2, from the instance
and the source tree on 2026-09-06.

**Weaker, flagged.** Recorded Future's bands and rule-aging come from
the vendor's own blog and support pages, not a spec. Maltego's
per-transform credit costs sit behind a login, so I could confirm that
costs are per-transform but not what the UI shows before you run one.
The OPSEC examples in §3.8 come from a practitioner write-up, and the
PassiveTotal behaviour described is historical — RiskIQ has since been
absorbed into Microsoft Defender TI and may not behave that way now.

**Not checked at all.** Anomali ThreatStream, Cyware, Splunk Threat
Intelligence Management, Microsoft Defender TI, Mandiant Advantage,
CrowdStrike Falcon Intelligence, Sekoia, Intel471. Nothing suggests
they would change the ordering in §6, but the "who has it" column would
get fuller.

**Re-check first, if revisiting:** whether OpenCTI's enrichment panel
has gained per-connector state (it is the most likely to change, and it
is our clearest lead); whether misp-modules has gained any summary or
metadata contract; and whether MISP has gained an enrichment cache in
the meantime, since that is item (1) and someone else may get there
first.

---

## 9. Sources

Retrieved 2026-09-06.

- Cortex analyzer development — cache, `summary()`, taxonomies:
  <https://thehive-project.github.io/Cortex-Analyzers/dev_guides/how-to-create-an-analyzer/>
- Cortex advanced configuration — `cache.job`, rate limiting:
  <https://docs.strangebee.com/cortex/installation-and-configuration/advanced-configuration/>
- OpenCTI, enrichment connectors:
  <https://docs.opencti.io/latest/usage/enrichment/>
- OpenCTI, automation and playbooks:
  <https://docs.opencti.io/7.260306.0/usage/automation/>
- Cortex XSOAR, indicator concepts — enrichment cache expiration:
  <https://cortex-docs.paloaltonetworks.com/xsoar-6-administrator-guide/6.13/customize-cortex-xsoar/customize-and-configure-cortex-xsoar/indicators/indicator-concepts>
- Cortex XSOAR, reputation and DBotScore:
  <https://xsoar.pan.dev/docs/integrations/dbot>
- ThreatConnect, ThreatAssess and CAL:
  <https://knowledge.threatconnect.com/docs/threatassess-and-cal>
- ThreatConnect, CAL indicator enrichments:
  <https://knowledge.threatconnect.com/docs/cal-indicator-enrichments>
- EclecticIQ, about enrichers:
  <https://docs.eclecticiq.com/ic/current/integrations/extensions/enrichers/about-enrichers/>
- EclecticIQ, VirusTotal enricher — caps and retries:
  <https://docs.eclecticiq.com/extensions/current/integrations/virustotal/enricher-virustotal-apiv2/>
- Recorded Future, inside the Intelligence Card:
  <https://www.recordedfuture.com/blog/intel-cards-overview>
- Recorded Future, Domain Intelligence Cards:
  <https://support.recordedfuture.com/hc/en-us/articles/115001398988-Domain-Intelligence-Cards>
- Maltego, credit usage:
  <https://docs.maltego.com/en/support/solutions/articles/15000059029-credit-usage>
- Threat hunting OPSEC — passive vs active sources:
  <https://medium.com/@0x4f47/threat-hunting-opsec-1eed027c850d>
- misp-modules expansion module list:
  <https://misp.github.io/misp-modules/expansion/>
