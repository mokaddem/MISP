# PRD: Analyst Profile — the discovery pass

**Status: discovery. Nothing here is a decision.**

This document is the input to a grilling session, not its output. It does
three things and stops:

1. Records what the shipped Value Profile page **already commits to** about a
   profile — every string, with its file and line, because the page has been
   asserting a profile exists since the skeleton pass and those assertions are
   the constraint set.
2. Records what MISP **already has** that this feature must sit on or mirror,
   and what it **does not have** — including three stores that do not exist and
   which the profile is therefore a candidate to become.
3. Puts a candidate inventory and a numbered question list in front of the
   grilling session (§10).

It unblocks [`../value-profile-verdict-engine.md`](../value-profile-verdict-engine.md),
which has been a scope note since 2026-08-28 and which says its own successor
must be *"a dedicated PRD and a grilling session, in the shape the tab phases
used — questions first, decisions recorded, then a specification."* This is the
questions-first half.

---

## 1. The ask

> A MISP instance should have a default profile available and enabled by
> default. Each org/user can fork this profile and modify it to fit their
> needs/preferences.
>
> A profile defines how a verdict (in the value profile) behaves and contains
> all the rules, weights and how they apply for the verdict scoring. A profile
> also contains default enrichment modules to be run when opening a value
> profile page (result to be displayed in a badge at the top level).
>
> It can also contain more configuration or posture.

Three claims worth separating, because they have different natures and the
design will want to treat them differently:

- **A forkable, ownable object with a shipped default** — a lifecycle question.
  MISP has solved this exact problem once already (§4.1).
- **The verdict's rules and weights** — a computation question. This is the
  hard half, and it is the part the corpus has been deferring.
- **Default enrichment modules, and a badge** — a side-effect question. Opening
  a page would now *do* something outward-facing, which no other Value Profile
  tab does (§5.3, §9.4).

"More configuration or posture" is left open on purpose; §7 proposes an
inventory to argue over.

## 2. Why this lands now

The Verdict tab is the **only** tab of nine whose live phase is blocked, and
it is blocked on exactly this. Every other tab displays data MISP holds; this
one displays a computed judgement and nothing computes it
(`value-profile-page.md` §1.4, §5).

`value-profile-verdict-engine.md` §2 raised three questions and could not
answer them:

1. How signals are computed.
2. **How signals behave under user preferences** — *"the promise and the ask do
   not currently agree on whose preferences"*.
3. How users add their own signals.

Question 2 is this document's subject, and it turns out to be upstream of the
other two: you cannot specify how a signal is computed without knowing what
holds its weight, and you cannot specify an extension point without knowing
what an extension is attached to. **The profile is the engine's configuration
object, so it gets designed first.**

## 3. What the shipped page already commits to

Nine tabs of prose, four demo values and fifteen verdict templates are already
on screen making claims about a profile. None of them is backed by anything.
They are not negotiable in the cheap sense — each one is either satisfied or
deliberately retracted, and a retraction is a change to shipped copy.

### 3.1 A profile is already named on screen, in two places

`default-v3` is a literal string in seven places in the fixture
(`ValueProfileFixture.php:958, 1122, 2181, 2222, 3325, 11370, 11452`) and
reaches the page through two render sites:

| Site | What it draws |
|---|---|
| `value_verdict_meta.ctp:40` | `Weighting profile default-v3` in the hero's meta line |
| `value_verdict_card.ctp:56` | `Weighting profile default-v3` as the Overview rail card's sub-line |

Both are plain text today. Both are the obvious entry point to a profile
viewer once one exists, and neither is a link.

`value_verdict_meta.ctp:38` carries a rule worth keeping: **a verdict reached
from no signal at all does not name a profile**, because *"naming the profile
that would have weighted it claims a computation that did not happen."* The
UNKNOWN value therefore renders no profile name. Whatever the profile becomes,
this conditional stays.

### 3.2 The profile already owns five distinct kinds of decision

Read across the fixture's four verdicts, the page attributes five separable
things to the profile. This is the strongest signal in the corpus about what a
profile *is*, because it was written without a profile design in view:

**(a) Weights.** `composition_note` on every scored value:

> *"Weights come from the default-v3 profile. An instance admin can edit the
> profile; the tab always names the one in force."*

**(b) Which evidence is set aside.** `not_counted` is documented in the fixture
at `2261` as *"evidence the profile deliberately set aside"*, distinct from
`ambiguities` (evidence that is genuinely split). Its entries are rules, not
observations:

- *"Feeds that merely mirror CIRCL OSINT are not independent corroboration and
  score once, not three times."* — a de-duplication rule over sources.
- *"Self-sightings: 3 sightings from the same org that created the attribute,
  within an hour of creation."* — an exclusion rule with a time window.
- *"4 occurrences outside your ACL — excluded, not hidden."* — **not** a profile
  decision; this one is the viewer's permissions and must never become
  configurable.

So `not_counted` is already two things wearing one coat: profile policy and
ACL truth. It renders through one template
(`value_verdict_not_counted.ctp`, reached from `value_verdict_aside.ctp:49,58`).

**(c) Escalation rules that override the score.** The conflicted value carries
a named rule (`ValueProfileFixture.php:2229`):

```
name: conflict:known-infrastructure-vs-reporting
text: A known-category warninglist hit together with three or more
      independent malicious reports emits CONFLICTED. Neither signal is
      discounted, because both are true at once.
```

It renders as `Conflict rule: <text>` in the meta line
(`value_verdict_meta.ctp:49`). A **named, addressable, human-readable rule that
overrides arithmetic** is already on screen. The name has a namespace prefix
(`conflict:`), which implies siblings.

**(d) Thresholds, stated as falsifiability.** `changers` — *"what it would take
to move the disposition. A verdict that cannot say what would falsify it is an
opinion"* (`ValueProfileFixture.php:1175`):

- *"A warninglist hit of category known → CONFLICTED immediately, whatever the
  score."*
- *"3 or more false-positive sightings from 2+ orgs → drops to SUSPICIOUS."*
- *"No sighting for 45 days → decay takes the score under 50."*
- *"7 days → SUSPICIOUS, whatever the listing"* (benign value, `3589`).

Every one of those is a profile parameter with a number in it: a category
trigger, a count, an org count, a day count, a score floor.

**(e) The profile's own knowledge, which can change without the evidence
changing.** The benign value's `curves_note` (`3542`):

> *"The step on 2025-06-24 is the address being added to the public-resolver
> warninglist. Before that the page called it SUSPICIOUS on the strength of the
> same four reports — the evidence did not change, the profile's knowledge of
> it did."*

This is the most consequential sentence in the corpus for this design. It says
the profile carries **reference data**, not only parameters, and that a
disposition can flip because the profile learned something. It also means a
profile is versioned in a way that has to be legible after the fact, or that
sentence is unexplainable to the reader it is written for.

### 3.3 The score is its own explanation, exactly

Verified in the corpus and re-stated here because it constrains every option
below: 84, 91 and 93 are the **arithmetic sum** of their ledger contributions,
to the unit (`value-profile-verdict-engine.md` §3.2, verified by
`value-profile-page.md` §6 step 4).

An engine that computes a score some other way and narrates it afterwards
breaks a property the page currently guarantees. Therefore **any weighting
scheme the profile expresses has to be summable and per-signal** — no
normalisation step, no post-hoc calibration, no model with a hidden layer. That
rules out a lot and is a gift, not a constraint.

The corollary for the profile: a weight is not a multiplier on an opaque
sub-score. A signal's `contribution` is the number the profile produces, and
the ledger row is the audit trail.

### 3.4 The weight band and the contribution do not agree

Carried from `value-profile-verdict-engine.md` §3.7 because it is now this
document's problem: `strong` / `moderate` / `weak` are rendered as bands and
`contribution` is a number, and nothing relates them. They **overlap** in the
fixture — a `strong` of `+17` sits below a `moderate` of `+16` elsewhere.

Either the band is derived from the contribution, or it is an independent
editorial label. If a profile holds weights, this is the first thing it has to
say, because "weight" is currently two things.

### 3.5 SUSPICIOUS is promised three times and does not exist

`ValueDisposition::TREATMENTS` has exactly four entries: `MALICIOUS`, `BENIGN`,
`CONFLICTED`, `UNKNOWN`. `SUSPICIOUS` appears nowhere in it, and nowhere in the
codebase outside three prose strings in the fixture
(`ValueProfileFixture.php:1190, 3545, 3589`).

A verdict that scored into a SUSPICIOUS band today would fall through to
`ValueDisposition::NEUTRAL` and render as a grey question mark labelled with a
disposition the table does not know. That degrades gracefully by design
(`ValueDisposition.php:17`) but it degrades to *unknown*, which is the opposite
of what a suspicious value means.

If the profile owns disposition bands, adding SUSPICIOUS is one entry in that
table plus a colour token — and `isDefinite()` has to be decided for it. Note
`isDefinite()` is **still unused**: the quiet treatment its docblock describes
was never wired to a style (`value-profile-page.md` §6.1). A fifth disposition
is the first caller that would want it.

### 3.6 The shipped copy says "instance admin"; the ask says org and user

The page says *"An instance admin can edit the profile"*, in the
`composition_note` of every scored value. The ask says each org and user can
fork it.

These are reconcilable — an instance admin edits the *default* profile, which
is exactly the decaying-model rule (§4.1) — but the sentence as written will be
wrong the day an analyst forks one, because it will name a profile the reader
edited themselves. **Shipped copy in scope for change.**

It compounds `00-contract.md` §14.6, which established that the verdict is
never *"the community's view"*, only *"the view available to you"*, because
occurrence visibility is per viewer. Per-user profiles would make two readers
differ for a **second, independent** reason. Today's caveat explains one of
them. See question Q9.

## 4. What MISP already has to build on

### 4.1 Decaying models are this feature, one domain over

This is the find of the discovery pass. `decaying_models` implements the ask's
whole lifecycle, in production, since 2.4.

| Ask | `decaying_models` |
|---|---|
| shipped default, enabled by default | loaded from `app/files/misp-decaying-models/models/*.json`, `default = 1` set on first import (`DecayingModel.php:164`) |
| identity across upstream updates | `uuid` column is the match key; re-import updates in place when `version` increases (`DecayingModel.php:178`) |
| org can fork and modify | rows carry `org_id`; `all_orgs` decides whether other orgs see it |
| the default cannot be edited in place | `isEditableByCurrentUser()` requires `!default` **and** `org_id == user's org` — site admin excepted (`DecayingModel.php:192`) |
| gated by permission | `perm_decaying` on the role |
| enabled/disabled independently of ownership | `enabled` column, with `enable`/`disable`/`massEnable`/`massDisable` actions |
| parameters are open-ended | `parameters` is a `text` column holding JSON, adjusted through `__adjustParameters()` |
| the model names its algorithm | `formula` is a class name resolved against `app/Lib/Tools/DecayingModel/Formula/*.php` |
| a set of things it applies to | `attribute_types` JSON, plus a `decaying_model_mappings` join table for per-instance overrides |

Schema, for reference (`INSTALL/MYSQL.sql:356`): `id, uuid, name, parameters,
attribute_types, description, org_id, enabled, all_orgs, ref, formula, version,
default`.

**What this buys the design.** A profile modelled on this row inherits a
lifecycle MISP administrators already understand, a migration story, an export
/ import path (`DecayingModelController::export`/`import`), and a REST surface.
It also inherits the pattern's known weaknesses — see §9.1 and §9.2.

**What it does not answer.** `decaying_models` has **no per-user scope**. Its
finest grain is the organisation, and `all_orgs` is a boolean, not a
distribution. The ask wants per-user. See Q3.

### 4.2 A per-user knob that changes a computed score already ships

`user_settings.tag_numerical_value_override` (`UserSetting.php:97`) lets a user
override the numerical value of any tag — for instance
`false-positive:risk="medium" => 99`. It is read by `Tag.php:453`, and tag
numerical values are the **base score** every decaying model starts from.

So MISP already ships a per-user setting that changes a per-attribute computed
score, and has done for years. The precedent matters for two reasons:

- It settles the *legitimacy* question in §3.6. Per-user influence on a score
  is not a novel hazard here; it exists.
- It is a **bag**, not a row: `(user_id, setting)` unique key, JSON value,
  validated by `validate_json`, never shared, never synced. That is a second
  and much lighter shape than §4.1's, and it is the right shape for
  preferences. See Q4 on whether the feature needs both.

Also in the registry and relevant: `dashboard`, `dashboard_access`, `homepage`,
`default_restsearch_parameters`, `event_index_hide_columns`. Every one of them
is *presentation or preference* — none changes an assertion. That is a clean
line and the design should probably keep it.

### 4.3 The decaying tool's simulator is the profile editor's preview

`DecayingModelController` ships `decayingTool`, `decayingToolBasescore`,
`decayingToolSimulation`, `decayingToolRestSearch` and
`decayingToolComputeSimulation` (`app/Controller/DecayingModelController.php`),
with views under `app/View/DecayingModel/`.

It answers *"what would this model do to this attribute, right now, before I
save it"*. A profile editor wants the same affordance against a value: **pick a
value, show the verdict under the candidate profile beside the verdict under
the one in force.** The fixture's four demo values are a ready-made regression
set for exactly this, and §3.3's summability is what makes the diff legible —
every changed row shows its old and new contribution and the totals still add
up.

Worth stating plainly because it is a design opportunity, not a requirement:
the sum-is-the-explanation property makes a profile diff *renderable*, which a
model-based score would not be.

### 4.4 Enrichment module enablement is instance-wide, and narrows only

`Module::getEnabledModules()` (`app/Model/Module.php:111`) filters on:

1. `Plugin.Enrichment_<name>_enabled` — an **instance** setting.
2. the requested type against `module['meta']['module-type']`.
3. `canUse()` (`Module.php:412`) — site admin always; otherwise
   `Plugin.Enrichment_<name>_restrict` must be empty or equal the user's
   `org_id`.

Three consequences for "a profile contains default enrichment modules":

- **A profile can only narrow, never widen.** The instance decides what exists;
  a profile picks from that set. A profile naming a module the instance has
  disabled must render as an honest state, not silently drop the row — the page
  has form for this (`value-profile-page.md` §1.3, *"honest states"*).
- **`_restrict` is one org, not a list.** A profile shared to `all_orgs` can
  therefore name a module that only one org can run. Second honest state.
- **Cortex is a separate family** with its own service and timeout, merged into
  one rail by a decision `04-enrichment.md` §11 flags as not free.

There is **no per-user or per-org module *selection*** in MISP today. A profile
would be the first.

### 4.5 The permission shape

`roles` carries 30+ `perm_*` flags (`INSTALL/MYSQL.sql:1135`). The relevant
ones: `perm_decaying` (the direct analogue — gates owning a scoring model),
`perm_analyst_data` (gates notes/opinions/relationships),
`perm_view_feed_correlations`, `perm_admin` (org admin), `perm_site_admin`.

Adding a flag is a schema change plus an ACL entry, and MISP has a self-check
for the latter — `queryACL/findMissingFunctionNames` reports an action with no
ACL entry. Whether this feature needs a new flag or rides `perm_decaying` is
Q7.

## 5. What MISP does not have

Four stores that do not exist. Each is a thing the profile could **become** —
which is how a configuration object turns into a schema sprawl if nobody
decides. These are listed as findings, not proposals.

### 5.1 There is no org-level settings store, anywhere

Verified: `organisations` has `id, name, date_created, date_modified,
description, type, nationality, sector, created_by, uuid, contacts, local,
restricted_to_domain, landingpage` (`INSTALL/MYSQL.sql:1018`) — no settings
column, no JSON bag. A grep for `OrgSetting` / `org_setting` across `app/`
returns nothing.

MISP has per-user settings (`user_settings`) and per-instance settings
(`system_settings`, `admin_settings`). **Org-scoped configuration in MISP is
always done by owning a row** — a decaying model with an `org_id`, a sharing
group, a collection, a dashboard with `restrict_to_org_id`.

So an org-scoped profile has no bag to hang off and must be a row. That is not
a hardship; it is a constraint that removes an option, and it points the design
at §4.1 rather than §4.2 for anything org-scoped.

### 5.2 Nothing records per-organisation source reliability

The Verdict tab's *"Who says what"* panel renders a `reliability` column with
admiralty-scale grades — `CIRCL: B`, `Team-CIRCL: C`, `ORGNAME: D`
(`ValueProfileFixture.php:1057–1084`, and again at `2318`, `3444`, `10391`).

The fixture is explicit that this is invented: *"Opinion, `to_ids` stance and
reliability are editorial and are not derivable from a row count, so they are
given for the four organisations the summary names and defaulted for the
nineteen it does not"* (`ValueProfileFixture.php:10386`).

There is no store for it. `admiralty-scale` is a taxonomy, so it exists as
*tags* — and tags attach to events, attributes and galaxy clusters. **Nothing
in MISP attaches a tag, or anything else, to an organisation.**

This is the single most profile-shaped gap in the corpus. "How much do I trust
CIRCL" is a per-analyst editorial judgement, it has nowhere to live, and it is
a direct multiplier on the reporting-breadth signal that is the largest
positive contribution on two of the four demo values (+28, +31). See Q6 — and
note it is also the fastest way for this feature to become enormous.

### 5.3 Nothing records that an enrichment module ran

`04-enrichment.md` §11 calls this *"the tab's gating decision"*. `Module` is
`useTable = false`; there is no per-value, per-module last-run timestamp
anywhere, so every staleness chip, every group header and the whole delta band
in the shipped Enrichment tab depend on persistence that does not exist. A
dismissal is not remembered either.

`value-profile-writes.md` §6.4 proposes the answer — *"a plain cache table,
keyed by value and module: last run, by whom, status, and the dismissed
elements"*, deliberately kept out of the assertion stores because it is
evictable and instance-owned.

The ask's *"default enrichment modules to be run when opening a value profile
page"* needs that table to exist, or it re-runs every module on every page load.
It also needs the **queued** path: `Event::enrichmentRouter()` returns before
its own `MISP.background_jobs` branch — it returns at `Event.php:7997` and
strands the branch at `7998` — so the interactive path is synchronous whatever
the setting says, and running nine
modules synchronously in one page render is not viable
(`04-enrichment.md` §11).

This is a dependency, not a component. See Q10.

### 5.4 No shipped warninglist sets a category

Inherited from `00-contract.md` §14.10 and **not re-verified here** — the
`warninglists` submodule is not checked out in this worktree or the parent, so
the list files could not be counted. What *was* verified: the column exists
(`warninglists.category varchar(20) NOT NULL DEFAULT 'false_positive'`,
`INSTALL/MYSQL.sql:1765`) and the validation enum is exactly
`['false_positive', 'known']` (`Warninglist.php:43`).

If the corpus's finding holds, then the `+38` benign signal, the conflicted
value's entire premise, and §3.2(d)'s *"a warninglist hit of category known →
CONFLICTED immediately"* all rest on a category nothing produces, and every
list defaults to `false_positive`.

A profile that supplies its own category map over warninglist ids would fix
that locally, and it is exactly the shape of §3.2(e)'s *"the profile's
knowledge of it"*. It is also a maintenance burden with an upstream that could
set the field properly instead. **Confirm the finding before designing on it.**

### 5.5 Nothing computes a verdict

Stated for completeness. `value-profile-page.md` §5 has said so since the
skeleton pass, and the four demo verdicts are hand-written arrays.

## 6. The naming problem — raised and settled

**Decided 2026-09-02: the object is the Analyst Profile.** Recorded because
this document raised it as a blocker and it is not one.

The concern was that the page would carry three things called a profile: the
**Value Profile** page itself, the **weighting profile `default-v3`** the hero
names, and the new object — with MISP's unrelated `user_login_profiles` a
fourth.

It is not worth a rename, because the profile name has a **two-site render
surface** and both sites are one string:

| Site | Tab | What it draws |
|---|---|---|
| `value_verdict_meta.ctp:40` | Verdict | `Weighting profile default-v3` in the hero meta line |
| `value_verdict_card.ctp:56` | **Overview** | `Weighting profile default-v3` as the rail card's sub-line |

The second one is worth noting rather than assuming: the verdict card is the
Overview's right-rail card, served by `ValuesController::viewVerdictCard()`
(`ValuesController.php:160`), so the profile name is on two tabs, not one. It
is still a sub-line on a card, not a page identity.

**`Analyst Posture` was considered and rejected.** The object declares which
enrichment modules run on page open (C12) and how much quota that may spend
(C13), and neither is a posture in any sense a reader would recognise —
"posture" describes a stance toward evidence, not a list of vendors to call.
An object that holds both wants the neutral word.

**Consequence for shipped copy.** The two sites above say *"Weighting
profile"*, and the object holds more than weighting. Either the copy becomes
*"Analyst profile"* on both, or it keeps saying *"Weighting profile"* and names
the analyst profile anyway — which reads as a mislabel the first time an
analyst forks one and sees their own profile's name under a word that only
describes part of it. Small, but it is shipped copy in scope for change, and
`value_verdict_meta.ctp:38`'s rule stays either way: a verdict reached from no
signal names no profile.

## 7. Candidate inventory

What a profile might hold. Each row states what it parameterises, what
supplies it today, and the question it forces. **Nothing here is chosen** — the
point of the table is to be argued down, and several rows should not survive.

| # | Candidate | Parameterises | Where it lives today | Forces |
|---|---|---|---|---|
| C1 | **Signal catalogue** — which signals are evaluated at all | the ledger's rows | 24 hand-written rows across 4 values | Q2, Q11 |
| C2 | **Per-signal weight** | `contribution` | fixture literals | Q2, Q5 |
| C3 | **Weight bands** — `strong`/`moderate`/`weak` | the band chip | fixture literals, inconsistent with C2 (§3.4) | Q5 |
| C4 | **Disposition bands** — score → disposition | the hero | nothing; `SUSPICIOUS` promised and absent (§3.5) | Q2 |
| C5 | **Escalation / conflict rules** — named overrides | CONFLICTED, and `changers` | one named rule, prose only (§3.2c) | Q2, Q11 |
| C6 | **Exclusion rules** — `not_counted` policy half | which evidence is set aside | prose (§3.2b) | Q2 |
| C7 | **Time horizons** — "recent", the silence threshold, the 90-day window | recency signals, `changers` | scattered literals: 30d, 45d, 7d, 90d | Q2 |
| C8 | **Decay model selection + aggregation** | two ledger rows | aggregation **decided** — per-day max (`23-sightings.md` §5); selection is not | Q8 |
| C9 | **Per-org trust / source reliability** | reporting-breadth, the largest positive signal | nothing, anywhere (§5.2) | Q6 |
| C10 | **Warninglist category map** | the benign case and the conflict rule | nothing sets it (§5.4) | Q6 |
| C11 | **Taxonomy / tag trust** | attribution signals | `tag_numerical_value_override`, per user, already ships (§4.2) | Q4, Q6 |
| C12 | **Default enrichment modules, per type** | the ask's badge | nothing; instance enablement only narrows (§4.4) | Q10 |
| C13 | **Enrichment cost posture** — will this profile spend quota / call out | whether a module auto-runs | nothing; no cost metadata exists (`04-enrichment.md` §11) | Q10 |
| C14 | **Which opinions count** — value-scoped, occurrence-scoped, or both | the analyst aggregate, and any opinion signal | open (`value-profile-writes.md` §10.1) | Q11 |
| C15 | **Presentation** — default tab, panel open/closed, hidden columns | nothing computed | `user_settings` precedent is exactly this (§4.2) | Q4 |

**C15 is the row most likely to be a mistake.** It is a preference, it changes
no assertion, and `user_settings` already holds four of its kind. Folding it
into an object that also holds C2 would put "which tab opens first" and "how
much I trust CIRCL" in one blob with one version number. §4.2's clean line
between preference and assertion is worth defending.

## 8. Three scopes, and the resolution order — decided, see Q3

The ask names instance, org and user. If all three can hold a profile, then
every read needs a resolution order, and the corpus has no such rule.

Live options, stated without preference:

- **One profile in force, selected.** The viewer resolves to exactly one
  profile (nearest owner wins: user → org → instance default). The hero names
  it, `default-v3` becomes `my-soc-v2`, and §3.1's single-name render site
  keeps working unchanged. A fork copies the whole thing.
- **Layered override.** Instance default, org delta, user delta, merged at read
  time. Cheaper to maintain upstream — an improved default reaches everyone who
  did not override that key — but the hero can no longer name *one* profile
  honestly, and §3.2(e)'s "the profile's knowledge changed" becomes three
  changelogs.
- **One scope only, for v1.** Org-owned, no per-user, exactly the decaying
  model rule. Smallest thing that satisfies most of the ask, and defers §3.6's
  compounding caveat entirely.

**Decided 2026-09-02: the first option — one profile in force, nearest owner
wins.** Q3 in §10 carries the reasoning and the three consequences, one of
which is that every fork is a frozen copy.

## 9. Hazards found writing this

### 9.1 The decaying-model pattern has a known sharp edge

`isEditableByCurrentUser()` (`DecayingModel.php:192`) makes a shipped default
uneditable by anyone except a site admin. That is right, and it means **fork is
the only path** for an ordinary analyst — so fork has to be a first-class,
one-click action with a good default name, not an export/import round trip.
`DecayingModelController` has no `fork` action today; it has `export` and
`import`, which is the round trip.

### 9.2 A forked profile does not track its parent — accepted, not fixed

`decaying_models` matches upstream updates by `uuid` and version. A fork gets a
new uuid and is then on its own forever: an improved default never reaches it,
and nothing records what it diverged from.

**Q12 decided this is fine.** Forking exists to get an analyst started, not to
carry provenance, so there are no parent columns and no diff-against-parent
view. It stays listed here because it is still the mechanism behind a real
failure mode rather than a solved problem: a fork's copy of anything that
*should* keep improving will not. That is tolerable for a weight the analyst
chose deliberately and much less so for reference data they never looked at,
which is why it is an input to Q6 rather than a closed item.

### 9.3 "Not stored, not synchronised" is an asset that is easy to spend

`value-profile-verdict-engine.md` §3.6 argues the render-time computation is a
feature: per-viewer weighting is cheap and user-defined signals have no sync
blast radius. The hero renders *"Not stored, not synchronised"* as given
(`value_verdict_meta.ctp:49`).

The moment a profile is cached, or a verdict is stored to sort an index by it,
that sentence becomes false and per-user weighting becomes expensive. Worth
naming as an invariant to defend rather than a property to notice breaking.

### 9.4 Opening a page would now have side effects

*"Default enrichment modules to be run when opening a value profile page"*
means a GET with outward-facing effects: third-party queries, quota spend, and
the analyst's interest disclosed to a vendor. Every other panel on the page is
a read of local data.

Three things follow. It needs the queued path (§5.3), so the badge has a
*pending* state and the page cannot promise a result on first paint. It needs a
cost posture (C13) or a profile shared to `all_orgs` will spend another org's
quota. And it needs a rule for whether a same-value revisit re-runs — which is
§5.3's missing table again.

### 9.5 The corpus will move underneath this

`value-profile-page.md` §1.4's table is the tab-level record and
`00-contract.md` §14.12 is the panel-level board, and both are updated as
phases land. This feature adds rows to the first. Re-read the section list
before appending — the main PRD went from §1–§9 to §1–§13 mid-session once
already.

## 10. The grilling agenda

Ordered by how much else depends on the answer. **Q1–Q4, Q6, Q8 and Q12 are
decided** and the reasoning is carried below; the main PRD
([`01-profile.md`](01-profile.md)) consolidates them. Q5, Q7, Q9, Q10 and Q11
remain open and are carried by the phase document each one lands in.

**~~Q1 — Naming.~~ Decided 2026-09-02** — the object is the Analyst Profile;
`Analyst Posture` rejected because the object also declares enrichment
behaviour. §6 has the reasoning and the shipped-copy consequence. Numbering
below is left as it was rather than shifted up, so cross-references from
`../value-profile-verdict-engine.md` keep pointing at the right questions.

**~~Q2 — What is a rule, concretely?~~ Decided 2026-09-02 — named sections,
and `signals` is a list.** A profile is a struct with five named sections:
`signals`, `thresholds`, `escalations`, `exclusions`, `reference`. Each
`signals` entry emits exactly one ledger row and contributes to the sum; the
other four do not.

Rejected: one flat rule list. Two reasons. An escalation does not contribute a
number, it *replaces* the answer (*"a known-category hit → CONFLICTED, whatever
the score"*), so a single list whose contract is "each entry contributes to the
score" would be false for half its entries — and §3.3's exact-sum property is
the one thing the page guarantees that a flat list would make unverifiable. And
a threshold like `silence_days: 45` would become a rule object with one
populated field.

**Consequence: `signals` is the only extensible section**, which answers half
of Q11 in advance. An analyst-authored signal appends to a list whose contract
is already "emit one ledger row, contribute one signed integer" — the existing
ledger renders it with no template change and §3.3's invariant still holds. An
analyst-authored *escalation* is a different and much larger question, because
it can override a disposition; not settled here.

**What would have changed it:** a rule that both contributes points and can
force a disposition. If one turns up, the section boundary is artificial and
this decision needs revisiting rather than working around.

**~~Q3 — How many scopes in v1, and what is the resolution order?~~ Decided
2026-09-02 — all three scopes, nearest owner wins, one profile in force.**
The viewer resolves to exactly one profile: their own if they have one, else
their organisation's, else the instance default. A fork is a full copy.

Rejected: layered deltas, on the strength of what the hero can honestly say.
It has one slot and it names one profile (§3.1, two render sites). Under
selection it names `sami-hunting` and the reader can open exactly the object
that produced the number. Under layering there is no single object to name, so
the hero either grows to name three or names one and misattributes the weights.

Rejected: org-only for v1, though it was the closest call — it is the decaying
model rule exactly, and fewer forks in existence means fewer stale copies.

**Three consequences, all of which raise the price of questions still open.**

- **Every fork is frozen at creation.** Selection means a fork carries its own
  full copy, so an improved default never reaches it — §9.2's hazard, now
  load-bearing rather than a footnote. This makes **Q12 a v1 question**: with
  per-user forks the population of stale copies is one per analyst, not one per
  org. It also raises the price of Q6 — reference data inside a frozen copy
  goes stale in a way a weight does not.
- **Q9 is live.** Per-user profiles exist, so two readers now differ for two
  independent reasons: their ACL and their profile. §14.6's standing caveat
  currently explains one.
- **§3.6's shipped copy is now definitely wrong.** *"An instance admin can edit
  the profile"* describes the default only, and most readers will be looking at
  a profile they or their org own.

**~~Q4 — One store or two?~~ Decided 2026-09-02 — a dedicated table holding
one JSON blob. Existing user settings are not touched.**

The question as first written (preference versus assertion, §4.2's line) was
the wrong question and was rephrased on the ask. The profile is a single
self-contained object describing scoring, enrichment defaults and whatever else
§7 admits; the only open point was where it is stored, and that has a factual
answer rather than a trade-off.

**`user_settings` cannot hold it.** `user_id` is `int(11) NOT NULL` with a
unique key on `(user_id, setting)`, so every row belongs to exactly one user,
and `checkAccess()` (`UserSetting.php:385`) reads `org_id` only to decide who
may *manage* a row, never to scope one. Q3 needs three scopes and
`user_settings` can express one.

```
analyst_profiles
  id                   int PK
  uuid                 varchar(40)       identity across instances; the
                                         export/import and upstream match key
  name                 varchar(191)      "circl-soc", "sami-hunting"
  description          text
  user_id              int NULL    ─┐
  org_id               int NULL     ├─   the ownership triple; exactly one set
  default              tinyint(1)  ─┘    shipped, site-admin-editable only
  enabled              tinyint(1)
  version              int
  parameters           longtext          the five sections of Q2, as JSON
  created, modified    datetime
```

Resolution is one query against the triple, in Q3's order: `user_id = me`, else
`org_id = my org`, else `default = 1`.

**`parameters` is one JSON blob, not child tables.** Same as
`decaying_models`, and for the same reason: the read pattern is always "fetch
one whole profile", and the five sections stay free to evolve without a
migration each. Accepted cost — no cross-profile querying (*"which profiles
weight galaxy attribution above 20"* is a PHP loop), and a malformed section
fails at read rather than at write, so validation on save is not optional.

**`all_orgs` is out.** `decaying_models` carries it, but under Q3's
nearest-owner-wins it plays no part in resolution — an org's profile already
applies to that org. Its only use would be letting one org *offer* a profile
for others to fork, and that is a sharing feature, not this one.

**Existing `user_settings` entries are untouched.** `homepage`, `dashboard`,
`event_index_hide_columns`, `default_restsearch_parameters` and
`tag_numerical_value_override` stay exactly as they are. This feature adds no
setting to that registry and changes no reader of it. **C15 is therefore out** —
presentation preferences are not in the profile and not in scope.

Note `tag_numerical_value_override` is the interesting survivor: it is a
per-user knob that already changes a computed score (§4.2), it stays in
`user_settings`, and it therefore sits *outside* the profile while affecting the
same numbers. Whether the profile has to name it as an input it does not own is
an engine question, and it is a real one.

**Q5 — Is a weight band derived or editorial?** §3.4. If derived, the fixture
is wrong in a way that has to be corrected in the same pass. If editorial, the
profile holds two numbers per signal and the design says how they can disagree.

**~~Q6 — Does the profile hold reference data, or only parameters?~~ Decided
2026-09-02 — both maps, held as overrides.** The profile carries per-org trust
(C9) and warninglist category overrides (C10).

A split was proposed and rejected: warninglist category is objectively true of
the list, so fixing it upstream in `misp-warninglists` would serve every
instance rather than each fork. The ask is that both live in the profile, and
they do.

**Held as overrides, not copies**, which removes the staleness objection Q12
created: an empty map means *"use whatever the warninglists table says"*, so an
upstream fix still reaches every profile that has not deliberately overridden
that list. Per-org trust has no underlying store to fall back to, so an empty
map there means *"all orgs weighted equally"* — today's behaviour exactly, and
nobody has to grade 200 orgs to get started.

**Keyed by `organisations.uuid`, never `org_id`**, which is local and would
break on export or against a peer that numbers the same org differently.

Full specification in [`07-reference.md`](07-reference.md).

**Q7 — Which permission gates ownership?** Ride `perm_decaying`, add a flag, or
gate on `perm_admin` for org-scoped and nothing for user-scoped?

**~~Q8 — Does the profile select decay models?~~ Decided 2026-09-02 — no. The
page stops reading `decaying_models` entirely.** The profile owns a per-type
**TTL** against **last independent corroboration**; the Value Profile page
reads that and nothing else for staleness.

**The finding that forced it: MISP's decay would double-count tags.**
`DecayingModelsFormulas/Base.php:102` computes `base_score` as
`Σ (taxonomy_ratio × tag.numerical_value)`, falling back to
`default_base_score` only when no tag matches. So a decay score is largely a
restatement of the value's tags, multiplied by a time factor — while the
verdict's Attribution group already scores those same tags directly (+14
APT28, +5 T1071.001). Same evidence, two paths, no visibility into the overlap.

Worse, it is *unattributable*: `tag_numerical_value_override` is per-user, stays
in `user_settings` outside the profile (Q4), and moves the decay base score. A
knob the profile does not own would silently change a number the profile appears
to explain.

**What resolved it.** MISP's decay answers two questions at once — *how bad is
this* (base score, from tags) and *how stale is this* (the time factor). The
ledger already answers the first, from more evidence and with a per-row audit
trail. So the profile takes a pure time statement and leaves the base score
behind, which is what a TTL is.

**The curve was a false choice.** `Polynomial.php:17` is
`base × (1 − (t/lifetime)^(1/decay_speed))`, clamped at zero. At
`decay_speed = 1` that *is* linear — the two are not alternatives, one contains
the other. Exponential was rejected outright: `e^(−λt)` asymptotes and never
expires, so it has no TTL.

**Direction recorded, work deferred: the verdict score is to be exposed to
restSearch**, eventually replacing `excludeDecayed` as the freshness gate. This
is why the page may stop explaining export gating without that being a
regression — it explains the thing that will *become* the gate. It is a named
later stage with real prerequisites (a cached score, bulk scoring), not a
promise this feature keeps.

Full specification in [`06-staleness.md`](06-staleness.md); the export stage is
[`11-restsearch.md`](11-restsearch.md).

**Q9 — Does the standing caveat need to say both reasons?** §3.6. Two readers
already differ because of ACL; per-user profiles add a second reason. One
sentence, two causes, on every verdict.

**Q10 — Enrichment: how much of §5.3 is in scope?** The badge needs the
last-run store and the queued path, neither of which exists. Is C12 a phase of
*this* feature, a phase that depends on `value-profile-writes.md` §6.4 landing
first, or explicitly deferred with the profile only *declaring* the module list?

**Q11 — Is there an extension point in v1?** `verdict-engine.md` §2.3 asks how
users add their own signals. C1 and C5 either accommodate that from the start
or the profile format has to change later to allow it.

**~~Q12 — Does a fork remember its parent?~~ Decided 2026-09-02 — no.**
*"The forking is just there to quickly get started, not support provenance."*
No `parent_uuid`, no `parent_version`, no diff-against-parent view. A fork is a
copy with a new uuid and no memory of where it came from.

This reframes §9.2 from a hazard to be solved into an **accepted property**,
and it has one real consequence that lands on Q6: a profile holding reference
data (warninglist categories, per-org trust) keeps whatever it was forked with,
permanently, and nothing anywhere can tell the analyst their copy is stale. If
Q6 says the profile holds knowledge, that knowledge has no update path into
existing forks — the analyst's only remedy is to fork the default again and
re-apply their edits by hand.

## 11. Out of scope for this document

- Any decision. §7 and §10 are inputs.
- Any schema. Even §4.1's table is a precedent, not a proposal.
- The engine's algorithm. That is
  [`../value-profile-verdict-engine.md`](../value-profile-verdict-engine.md)'s
  successor, and it depends on Q2 and Q3.
- Re-verifying §5.4. The submodule is not checked out; the finding is cited,
  not confirmed.
- Anything in `value-profile-writes.md`. It adds no read path and this document
  adds no write path; the two meet only at §5.3.
