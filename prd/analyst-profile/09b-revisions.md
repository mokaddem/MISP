# PRD: Analyst Profile — phase 8b-r, the revision round

**An implementation plan, written to be executed by a session that has
not seen this conversation.** It carries one reviewer's feedback on the
picked prototype, the research that checked each item against the built
code, and what to do about it. Read §1, then §2, then work §3 in the
order §6 gives.

Depends on: [`09b-decision.md`](09b-decision.md) (why B was picked, and
the hybrid it is), [`09b-prototypes.md`](09b-prototypes.md) (the brief
and its §9 findings), [`09-editor.md`](09-editor.md) (what the pages do).

**The reviewer's feedback is quoted verbatim in each item.** Where the
research contradicts it, both are stated — the reviewer is describing
what the prototype communicated, which is evidence even when the
underlying claim is wrong.

---

## 1. Orientation

### 1.1 What is true right now

- **Candidate B, the workbench, is the design.** `mockups/workbench.html`
  → built to `build/workbench.html` → published. **A (`ledger-sheet`) and
  C (`stated-judgement`) are abandoned** (reviewer, 2026-09-10) — do not
  read them, do not keep them in step, do not delete them either.
- **8a is built**: the view-model (`AnalystProfileFormTool`), the
  controller (`AnalystProfilesController`), the engine
  (`ValueVerdictTool`, `ValueLeanTool`, `ValueRelevanceTool`,
  `ValueEnrichmentTool`, `ValueTrustTool`).
- **8c is not built.** There is **no `app/View/AnalystProfiles/`
  directory**. Every controller action returns JSON today. So the
  mockup is still the only design surface, and this round happens in the
  mockup.
- The fixtures in `09a-fixtures/` are the only numbers a mockup may
  draw.

### 1.2 The loop

```bash
# edit prd/analyst-profile/mockups/workbench.html, keeping <!-- vp-kit -->
python3 prd/phase7/kit/inline-kit.py \
    prd/analyst-profile/mockups/workbench.html
bash prd/analyst-profile/mockups/check-mockup.sh \
    prd/analyst-profile/build/workbench.html
# then republish to the SAME artifact url (Artifact tool, action publish,
# url = the existing artifact), never a new one
```

The checker asserts eleven things in both themes and must print
`PASS — ready to publish`. It was itself broken until 2026-09-07 and is
now trustworthy (`09b-prototypes.md` §9.1).

### 1.3 Non-negotiables

1. **Invent no number.** Every figure comes from `09a-fixtures/`. If a
   change needs a figure the fixtures do not have, get it from the
   engine (see §5) or mark it `synthesised` the way
   `09a-fixtures-dump.php` already does — never compose a plausible one.
2. **Both themes**, MISP tokens only, no hardcoded colours.
3. **1280px still works.** B's degradation is a container query; check it.
4. **`--vp-dir-with` / `--vp-dir-against` are the ledger's delta pair
   only.** They mean *supports / disputes*. Do not reuse them for time,
   locality, or state.
5. **No product code in this round** unless an item says so explicitly
   (§3 bucket **P**), and those are separable commits.

---

## 2. The triage

The feedback splits four ways, and getting this split right is most of
the value of this document. **Do not start at the top of the reviewer's
list and work down** — a third of it cannot be drawn honestly until a
decision is made, and a quarter of it is not a drawing task at all.

| Bucket | Meaning | Items |
|---|---|---|
| **A** | Already designed and/or built, never drawn. Pure drawing. | 3.1 fork, 3.2 import/export, 3.5 pinned-values copy |
| **M** | Mockup-only. Draw it, no open question. | 3.3, 3.6, 3.7, 3.8, 3.11, 3.14, 3.15, §4 |
| **P** | **Product bugs found by this review.** Real defects in built code, independent of the mockup. | 3.9, 3.10, 3.13 |
| **D** | **Needs a design decision before it can be drawn.** Schema and/or engine change. | 3.4 (add form), 3.12 (TTL buckets), 3.16 (enrichment states), 3.17 (cost posture) |

**Bucket P is the surprise of this round.** Reviewing a mockup turned up
three defects in shipped code that no mockup change can fix, and one of
them silently refuses a valid profile. They are worth more than the UI
work and should be committed separately so they can be reviewed on their
own.

---

## 3. The items

### 3.1 — Fork · bucket A

> "A user should also be able to fork any other model they can see."

**It exists.** `AnalystProfilesController::fork()` (`:250`) and
`AnalystProfile::forkProfile()` (`app/Model/AnalystProfile.php:486-522`)
are built, and `09-editor.md` §6 (`:299-320`) specifies the whole
interaction. It was simply never drawn.

Draw it exactly as specified — do not redesign:

- One button, on `view` of any profile the analyst cannot edit. **No
  form.** Name defaults to `<source> (copy)`, owner is the analyst,
  lands on `edit` of the result.
- **The one-enabled invariant**: forking while you already hold an
  enabled profile offers *"replace your current profile"* (naming the
  existing one) or *"cancel"*. **Replace disables, never deletes.**
- `perm_admin` additionally gets *"fork to my organisation"*, whose
  confirm names the number of users affected.
- A fork has **no lineage** — new uuid, counters reset, no
  `parent_uuid`. Its description is rewritten to *"Forked from “X” on
  DATE."* because a verbatim copy made a fork claim to *be* the default
  (`09-editor.md:435-443`). Draw that description, it is the only
  provenance a fork has.

**Acceptance:** the index and view boards show a fork affordance on a
profile the reader does not own; the replace-confirm is drawn as a
state, including the name of the profile it would disable.

### 3.2 — Import / export · bucket A

Not in the feedback, but it is the other half of 3.4's answer and it is
also built and undrawn: `export/:id` (`:482`) and `import` (`:509`).
Draw both on the index. Import is the second of the two creation paths.

### 3.3 — Index actions · bucket M

> "Right now, action on this page are fine"

The full built action set is: `index`, `view`, `edit`, `fork`, `enable`,
`disable`, `delete`, `export`, `import`, `simulate`, `pin`, `unpin`,
plus `update` (site-admin only). Check the index draws all of the ones
that belong to a row, and nothing that does not exist.

### 3.4 — An "add"/"create" button · bucket D — **ask before building**

> "also add a 'add' or 'create' button so that a user can add their own model"

**This was decided against deliberately**, `09-editor.md:141-147`:

> "There is no `add`, decided 2026-09-07 while building 8a. […] a fork
> is *the* way in, on the argument that the shipped default is
> uneditable by an ordinary analyst — so a blank-slate form is a second
> entrance to a room with one door, and the document it would create is
> an empty `parameters` that scores nothing and names no signal. A new
> profile is a fork of one that already works, or an `import` of
> somebody else's."

There is no `add` action and no ACL entry for one.

**Plan:** the reviewer's intent — *"a user can make their own"* — is
already served by fork (3.1) and import (3.2), neither of which was
drawn, which is very likely why the affordance felt missing. **Do 3.1
and 3.2 first, then ask the reviewer whether the gap is closed.** Only
if they still want a blank-slate form is D5 reopened, and that is a PRD
decision with an ACL entry, a controller action and a validation story —
not a button.

### 3.5 — "Pinned values" · bucket A (copy, not removal)

> "I'm not sure what 'Pinned values' [is]. if that's just for the purpose
> of the demo, make sure to remove it before implementing"

**Do not remove it. It is a real feature and its backend is built.**
`09-editor.md:97-124` — the analyst pins up to eight values, stored per
user, and the simulator scores each under both profiles so a change can
be judged against values the analyst cares about rather than one.
Storage is a `UserSetting` (`analyst_profile_comparison_set`, cap 8),
`pin`/`unpin` are POST actions (`AnalystProfilesController:751`, `:760`).

That the reviewer could not tell means **the drawing failed, not the
feature**. Fix by drawing:
- what a pinned value *is*, in one sentence, at the point of use;
- the **empty state**, which is what a new analyst actually sees and
  which `09-editor.md:106-110` already writes: *"pin a value and its two
  columns appear here"*;
- the pin affordance in the simulator, where pinning actually happens.

**Also record a spec/build gap** (do not fix here): `09-editor.md` says
the set is *"seeded from the value they arrived from"*, but arriving
from a value does not pin it — `focus` is merged into the scored list
for that request only, and `__setPinned()` is the only writer.

### 3.6 — Value picker on the edit bench · bucket M

> "I supposed this rail will allow a user to pick their own value rather
> than having a fixed set of value? My guess is right now it's a select
> for the purpose of the prototype"

Correct. The four values are fixture. In the product the bench's value
comes from `?value=<b64>` and from the pinned set. Redraw the control as
a **value search/picker** plus the pinned set as quick-switches, and
make it obvious the list is the analyst's own.

### 3.7 — Say what the three axes are · bucket M

> "It would be really nice to have a very short sentence about what the
> 3 mains axis are used for and what they mean (lean, relevance & quality)"

Partly done on 2026-09-08 (`09b-decision.md` §3) — the assessment head
now carries all three at equal rank with a gloss each and a relation
sentence. The remaining work is **length**: the current relation
paragraph is five lines. Cut to one sentence visible, the rest behind an
`i` tooltip, per §4.2. Keep the wording faithful:

- **lean** — what the record asserts (`threat`/`benign`/`contested`/`none`); categorical, no ordering.
- **relevance** — whether it still holds; a clock and a shelf life.
- **quality** — how well evidenced it is; **the only axis that sums**.
- The relation: the lean anchors the ledger (`row = points × polarity`),
  quality is that anchored sum, the band is cut from quality, and
  relevance touches neither.

### 3.8 — Signals table: the `Group` column · bucket M

> "there's the group column. Each rows have badges with the signal that
> row is part of, it's a duplicate since the rows are already grouped by
> signal type."

The duplication is real: the table has a `Group` column *and*
`wb-grp` header rows.

**But the column is not a label — it is the only control that moves a
signal between groups.** `group` is declared per implementation
(`ValueSignalBase.php:134`), **overridable per signal in the profile**,
and resolved `$entry['group'] ?: $signal->group`
(`ValueVerdictTool.php:802-804`). It is *not* derived from the id prefix
— the shipped default proves it: `record.temporal_precision` sits in
group `Lifecycle`.

**Do not delete the column.** Remove the *repetition* while keeping the
control: drop the per-row group text while the row sits under its own
group heading, and expose "move to group" as a row action or an inline
control that only shows the value when it differs from the
implementation's default.

### 3.9 — `strong` / `moderate`: the word "band" means two things · buckets M + P

> "There's also a level 'strong'/'moderate', I don't know where this is
> coming from and what that means. Is it the weight? If not, where is
> the weight? Is there a weight or is it based on the cap"

**Answers, all sourced:**

- `strong`/`moderate`/`weak` is an **editorial label with zero
  arithmetic effect** — decision D14, `03-signals.md:307-345`, taken
  because the fixture's own labelling was proven underivable (`7`
  appears in both `moderate` and `weak`; `17` strong > `16` moderate).
  Default `moderate` (`ValueSignalBase.php:137`). Its only consumer sets
  the ledger row's `weight` key to **the string**
  (`ValueVerdictTool.php:806-808`).
- **There is no numeric weight anywhere.** The weight *is* the `points`
  map — the per-unit values (`per_org`, `scale`, …) — and `cap` bounds
  the accumulated product. Accumulation is a plain signed sum with no
  coefficients (`ValueVerdictTool.php:370-377`); the only multiplier on
  a row is the lean's ±1 polarity (`:800`).
- Formula: `raw = f(evidence, points)` → `capped(raw, points.cap)`
  (sign-aware, `ValueSignalBase.php:493-501`) → `× polarity` → summed.
  Worked example: `reporting.independent_orgs` with `{per_org: 7,
  cap: 28}` and 4 orgs → `min(7×4, 28) = 28`.

**The actual defect is a name collision**, and it is why this was
confusing: **"band" is two unrelated concepts on the same screen** — the
signal's editorial band (`strong`/`moderate`/`weak`, declared) and the
quality band (`none`/`low`/`medium`/`high`, derived from the total,
`ValueVerdictTool.php:98`).

- **M:** in the mockup, stop calling the signal one a "band". The form
  tool already labels it *"Weight band"*
  (`AnalystProfileFormTool.php:334-345`) which makes it worse, because it
  is not the weight. Propose **"emphasis"** or **"editorial weight"**,
  with a tooltip saying plainly: *this does not affect the score; it
  tells a reader how much to weigh the row*. Reserve the word *band*
  for the quality bands.
- **P:** the same rename belongs in
  `AnalystProfileFormTool.php:334-345` and in the value-page ledger
  templates that print it (`value_verdict_ledger.ctp:126`,
  `value_verdict_card.ctp:110`). Separate commit; ask first, because it
  is user-visible copy on a shipped page.

Also worth drawing while here: `trust_weighted` is **inert by default**
(it needs a non-empty `org_trust` map, `ValueTrustTool.php:373-377`), so
a design that shows it as active is lying about the shipped state.

### 3.10 — Thresholds pane · buckets M + P

> "Below the quality band, there're input to set medium and high from.
> The order is not intuitive, also that UI could be polished a bit in
> term of UX."

**M:** the inputs are drawn **"High from" then "Medium from"** while the
strip above reads low → medium → high left to right. Reorder to match
the strip, and bind each input visually to its segment (a mark on the
strip that moves, or the input sitting under its own segment). Keep the
existing live refusal — the pane already answers *what a save would
refuse* while you type, against the attainable bound, and that is the
best thing in the pane.

**Also fix an IA error this pane exposes** (and correct
`09b-decision.md` §4, which is wrong): **Thresholds configures two
axes, not one.** It holds *"The lean → Supermajority share"*
(`lean_supermajority`, `AnalystProfileFormTool.php:449-473`) *and* the
quality bands. The rail's axis tag currently says "quality — the bands".
Either split the pane, or tag it as both. See 3.15.

### 3.11 — Conflict rules · bucket M

> "This seems interesting, but it's not clear how this affects the score
> or lean."

It affects **the lean only, and in exactly one direction**: an enabled
escalation fires ⇒ the lean becomes `contested`, named by the rule. A
rule may only ever say *contested*; it can never pick a side
(`AnalystProfileFormTool.php:594-600`, `04-dispositions.md` §4).

It affects the **score not at all directly** — but note the second-order
effect, which is the interesting part and worth drawing: on a
`contested` lean **there is no polarity**, so the ledger renders
threat-signed rather than anchored (`04-dispositions.md:70-72`); and on
a `none` lean **there is no ledger at all**.

Draw one sentence to that effect in the pane, and make the fixture's own
fired rule (`conflict:listed-vs-asserted`) show *which* axis it moved.

### 3.12 — Relevance: TTL buckets · bucket D — **decide before drawing**

> "'Time to live, per type': The table is nice, but I think it's too
> granular. Maybe, let's create 3 configurable (+ the default one)
> buckets fast, medium, long […] and each attribute type can be assigned
> to one bucket."

A good idea — the shipped default names 11 types out of **194**
(`MispAttribute::generateTypeDefinitions()`), and the "add a type"
picker offers all 194.

**It is a schema change, so it does not belong in a mockup first.** Full
blast radius, all verified:

1. `ValueRelevanceTool::section()` (`:927-974`) splits `ttl_days.default`
   into `ttl_default`; `ttlFor()` (`:267-315`) does the per-type lookup.
2. `chooseType()` (`:317-345`) compares on **days** — buckets must still
   resolve to days before the comparison, or `type_rule` changes meaning.
3. `AnalystProfileFormTool::sectionRelevance()` (`:818-955`) — the
   `kind => 'map'` block is a *string→int* editor. Buckets need a **new
   block kind** (type→enum) plus a second block (bucket→days).
4. POST merge semantics: `merge()` (`:2031-2057`) / `replaceMap()` —
   "rows not posted were removed" must be re-checked for a two-level
   shape.
5. Validation `relevanceErrors()` (`:1733-1751`) — "whole number of days
   above zero" and "needs a `default`" become bucket-name checks.
6. A new option source beside `attribute_types`
   (`AnalystProfilesController:1034`).
7. **The sharp edge: `parameters` is opaque JSON so there is no DB
   migration — but user forks are never touched by `updateDefaults()`
   (`AnalystProfile.php:790-799`), so every existing fork carries the
   flat map. Without a read-time shim accepting both shapes, forks
   silently lose their TTLs.** This is the item that decides whether the
   change is cheap.
8. Fixtures and harnesses asserting the flat shape:
   `09a-fixtures/profile.json`, `simulate.json`,
   `06-relevance-harness.php:367-382` ("eleven types plus the default"),
   `06-relevance-live-probe.php`, `09-editor-harness.php`.

**Plan:** write the decision into `06-staleness.md` as a dated decision
(shape, migration shim, what `type_rule` compares) **before** drawing
it. Then the mockup draws the agreed shape, with a bucket→types
assignment UI whose "assign many types quickly" is the actual design
problem (194 types, 4 buckets — think multi-select-into-bucket, not one
row per type).

### 3.13 — Relevance: `type_rule`, and two divergence bugs · buckets M + P

> "The 'a value with several types', this one needs to be clarified as
> it's not clear what it does."

**What it does:** a value can occur under several attribute types
(`8.8.8.8` as `ip-dst`, `ip-src`, `ip-dst|port`, `text`), each with its
own TTL. `type_rule` picks which type's TTL governs the value.
Per-attribute decay never had this problem; a value-centric page does
(`06-staleness.md` §3.4). Draw the resolution the way the shipped page
already does — *"TTL 90 days from ip-dst · shortest rule, over ip-src
90, ip-dst|port 180, text 180"* — which shows the rule **and** what it
chose between.

**P — two real bugs, both in built code:**

1. **`TYPE_RULES` diverge.** Form tool offers
   `('shortest','longest','first')`
   (`AnalystProfileFormTool.php:102`); the engine implements
   `('shortest','longest','most_common')`
   (`ValueRelevanceTool.php:84`). So the editor offers `first`, which
   the engine does not implement and silently treats as `shortest`, and
   `relevanceErrors()` (`:1713-1722`) **rejects a valid `most_common`
   profile**.
2. **Clock lists diverge.** Form:
   `('last_independent_corroboration','newest_occurrence')`
   (`:95-99`); engine:
   `('last_independent_corroboration','last_sighting','last_occurrence')`
   (`ValueRelevanceTool.php:77-81`). The form invents one option and
   hides two. **The mockup copied both wrong lists** — the clock select at
   `workbench.html:2519-2522` offers `newest_occurrence`, and the
   `type_rule` select at `:2542-2544` offers `first`. Both must be
   corrected to the engine's vocabulary.

3. **`decay_speed` is declared `int`** in the form
   (`AnalystProfileFormTool.php:874-888`) but is a **float** in the
   engine, and has **no validator at all** (`relevanceErrors()` checks
   `clock`, `type_rule`, `aging_fraction`, `ttl_days` — never
   `decay_speed`). An int field cannot express the sub-1 half of the
   curve family, which is the half the design's own verification uses
   (0.5).

Fix all three in one **product** commit, separate from mockup work.

### 3.14 — Relevance: a curve chart · bucket M

> "having a chart graph displaying the different thresholds (lag, decay,
> ...) would also help users understand what each numbers means"

Worth doing, and there is a **ready-made precedent to copy**:
`app/View/Themed/Overmind/Elements/DecayingModel/View/decaying_model_curve.ctp`
renders this exact formula as **server-side inline SVG with no client
JS**. The relevance axis deliberately does not read `decaying_models`,
so copy the drawing, not the data path.

The formula, confirmed: `runway = max(0, min(1, 1 − (elapsed/ttl)^(1/decay_speed)))`
(`ValueRelevanceTool.php:216`). `decay_speed = 1` is linear;
`< 1` holds then falls off a cliff; `> 1` drops fast then lingers.

The knobs the chart should make legible, all per-profile and global to
the axis unless noted:

| Knob | Default | What it does |
|---|---|---|
| `ttl_days.<type>` | 11 types, 60–730 | shelf life, **per attribute type** |
| `ttl_days.default` | 180 | TTL for unnamed types |
| `decay_speed` | 1.0 | curve exponent |
| `aging_fraction` | 0.33 | where `current` becomes `aging` — already drawn as the tick |
| `lag_uncertain_days` | 30 | **the "lag" knob** — created-to-published lag above this ⇒ `timeline uncertain` |
| `clock` | `last_independent_corroboration` | what resets the shelf |

Draw the curve with the aging tick, the expiry point, and the current
value's position on it. **Note a duplicated knob** while here:
`lag_uncertain_days` (relevance) and the `record.temporal_precision`
signal's own `config.lag_days`, both 30 — same measurement, two
settings, two sections.

### 3.15 — Where the lean is configured · bucket M

> "I did not find anything about the `to_ids` flag. I was expecting to
> find it under the lean (like conflict rules) pane."

**`to_ids` is not a profile setting and should not become one.** It is
an input read from the attribute rows: per-org stances aggregated in SQL
(`Value.php:487-491`, `SUM(CASE WHEN Attribute.to_ids = 1 …)`) and
consumed by `ValueLeanTool::stancesFor()` (`:184-190`). Stances are
counted **per organisation, not per occurrence** — one org spamming
forty `to_ids = 1` events is one vote (`04-dispositions.md:80-81`).
`threat_share = threat_orgs / (threat_orgs + benign_orgs)`.

The only lean knob is `lean_supermajority` (default 0.66, rejected if
≤ 0.5 or > 1).

**The real finding is that the lean has no home.** It is configured in
*two* panes — supermajority inside **Thresholds**, escalations inside
**Conflict rules** — which is exactly why a reader hunting for "how the
lean is decided" found neither. **Recommended:** gather the lean into
one place, or at minimum make each pane say which axis it serves (the
rail tags do this now; the panes do not). Then state the lean's inputs
where the supermajority lives: *derived from per-org `to_ids` stances;
one organisation is one vote; the share must pass this threshold.*

This also corrects `09b-decision.md` §4's map (Thresholds is not
quality-only).

### 3.16 — Enrichment: three run states · bucket D — **decide before drawing**

> "Modules are ticked by default / Modules are run automatically /
> Modules cannot be run"

**Today there are two outcomes and neither is "runs":** `selected` (the
checkbox arrives ticked, a human still presses) and `withheld` (blocked
by the locality posture). Nothing auto-runs, by decision D15
(`ValueEnrichmentTool.php:9-19`). Enrichment has **no effect on the
score** — it only decides what the Enrichment tab pre-ticks.

Cost of each state:

- **(a) ticked by default** — this is today's `selected`. Free.
- **(c) cannot be run** — cheapest of the two new ones, and
  *compatible* with the stated invariant that a profile may only ever
  narrow, never widen (`01-profile.md:420`). Today only the *instance*
  can forbid a module. Must be **enforced server-side** in
  `ValueProfile::enrichmentRun()`, not merely disabled in the view, or a
  reader can still POST the module name.
- **(b) run automatically** — **blocked by two pre-existing defects**,
  and this is the one to raise before designing it:
  1. there is **no per-value/per-module last-run store** anywhere
     (`Module` is `useTable = false`), so "run on open" means "run on
     *every* open";
  2. the queued path is **dead code** — `Event::enrichmentRouter()`
     returns at `app/Model/Event.php:7997` and strands its
     `MISP.background_jobs` branch at `:7999`.
  Auto-run needs a cache table or a working queue first. Both are
  bigger than this round.

**Schema change either way:** `enrichment.auto_run` must stop being
`type => [names]` and become `type => {name: state}`, touching
`planFor()` (`:173-235`), `declaredFor()` (`:293-312`), `resolve()`'s
`selected`/`withheld` split (`:431-468`), `leavingCount()` (`:835-844`),
the form's `multiselect` block (which **cannot express three states**,
`:1256-1270`), `enrichmentErrors()` (`:1851-1861`), and the rail view
(`value_enrichment_rail.ctp:151-163`). Keep a back-compat read: a plain
list must keep meaning "ticked".

**Plan:** write the decision into `08-enrichment.md` first. Recommend
shipping (a) + (c) now and deferring (b) until a last-run store exists.

### 3.17 — Enrichment: "cost posture" is a misnomer · bucket D + M

> "The 'cost posture' is unclear. Also, I guess to determine what cost a
> module has, misp-modules's modules must declare it"

**The reviewer is right, and the setting is misnamed.** It does exactly
one thing: under `local_only`, a module whose resolved **locality** is
not local is withheld (`ValueEnrichmentTool.php:455-466`). It is a
locality gate, not a cost gate.

- **Locality is a hardcoded roster**, not a declaration:
  `ModuleLocality::LOCAL_MODULES` is a static 22-name PHP array
  (`ModuleLocality.php:104-136`), overridable per profile, with
  `unknown` treated as external.
- **No cost field exists in module introspection**, confirmed upstream:
  MISP reads only `name`, `type`, `mispattributes`, `meta[module-type]`,
  `meta[description]`, `meta[config]` (`Module.php:111-143`), and a
  misp-modules `moduleinfo` is `version, author, description,
  module-type, name, logo, requirements, features, references, input,
  output`. The PRD already measured this: *"across the dev instance's
  146 modules: not one field says anything about money, rate limits"*
  (`08-enrichment.md:50-58`). The obvious heuristic (has API key ⇒
  external) is wrong in both directions — `countrycode` has empty config
  and fetches an external host; `clamav` has a config key and stays
  local.
- **`allow_external` and `ask` are byte-identical today** — asserted as
  such (`08-enrichment.md:398-405`). A three-option select where two
  options do nothing different is its own defect.

**Plan (M):** rename to something locality-honest — *"Modules that leave
the instance"* — and either implement `ask` or drop it to two options.
**Plan (D):** real cost is **not implementable** without a new upstream
`meta` field; if the reviewer wants it, that is a misp-modules proposal
(`~/git/misp-modules`), and `ModuleLocality::retirable()` (`:256-272`)
already records the criterion for when a declared field could replace
the roster. Raise it, do not build it.

### 3.18 — Enrichment: "Reuse an answer for" · buckets M + P

> "'Reuse an answer for' has no unit"

**Unit is hours, default 24** (`ValueEnrichmentTool.php:105`). The field
is `'type' => 'int'` with no unit anywhere in the spec
(`AnalystProfileFormTool.php:1237-1253`); the word "hours" appears only
in a validation error string. There is no `unit`/`suffix` concept in the
form tool at all — other settings smuggle the unit into the key name
(`ttl_days`, `lag_uncertain_days`).

**Worse, and this must be drawn honestly: the setting governs nothing.**
It is copied into the plan and the resolution beside
`'reuse_inert' => true` and consumed nowhere, because **no cache table
exists** (`08-enrichment.md:23-36`).

**Plan:** add a general `unit`/`suffix` affordance to the form field
spec (**P**, small), draw the unit, and either mark the setting plainly
as inert or hide it until the cache exists — **do not draw an inert
setting as though it works.**

### 3.19 — Enrichment: "Modules per type" does not scale · bucket M (after 3.16)

> "given the amount of type and module, the current UI doesn't scale.
> Redo that section from scratch"

Confirmed, with numbers: **194 attribute types × ~146 enabled modules**,
and the current widget is a 194-key map whose every value is a
146-option multiselect. Every enabled module is offered for every type,
with **no validation that the module even accepts that type**
(`enrichmentErrors()` only checks list-ness).

Redesign from the module's side rather than the type's — a module is
picked once and the types it applies to are chosen against
`mispattributes`, which is data MISP already has and which would also
make the "does this module accept this type" check possible. Do this
**after** 3.16 settles the state shape, or it will be drawn twice.

### 3.20 — "Reference data" naming · bucket M

> "I don't really like the name 'reference'. Also, make sure to include
> the work 'reputation' in the organisation trust description text."

The section is org-trust grades plus warninglist meanings, Admiralty-
shaped — source reliability in, information credibility out
(`AnalystProfileFormTool.php:1038-1044`). Propose **"Sources &
reputation"** or **"Who you trust"**; the reviewer picks. Rewrite the
org-trust blurb to use the word **reputation**.

Two facts the copy should not contradict: a `0.00` grade is *"an
accusation of deception, not a quality judgement"* (`07-reference.md:114`),
and trust weighting is **inert until the map is non-empty**.

---

## 4. Two cross-cutting passes

### 4.1 Strip the prototype scaffolding

> "Make sure to remove any text linked to explaining what's going in the
> UI for the purpose of the prototype"

Inventory in `mockups/workbench.html` — everything here is scaffolding,
not product:

| What | Where |
|---|---|
| `<h1>Analyst Profile — candidate B, the workbench</h1>` | `:1629` |
| The three `.vp-board-tag` strips (URL + "the question it answers") | `:1647`, `:1843`, `:3182` |
| "What this candidate gives up" section | `:3593` |
| The `.wb-1280` inset (a live demo of the breakpoint) | `:3622` |
| Prose about the simulator's candidate column | `:3770` |
| The dimmed `.vp-doc-chrome` navbar | frame |
| Any `wb-note` explaining *the mockup* rather than *the product* | grep `mockup`, `candidate`, `fixture`, `prototype` |

**Keep** the board frames themselves while it is still a mockup — they
are how three pages are compared in one file. Strip them at 8c, when
each board becomes a real page. **Note this explicitly in the commit**
so 8c does not inherit them by accident.

### 4.2 One-sentence copy, tooltips for the rest

> "Make sure to include **short** description of what is what and how
> it's used. If the text is longer than one sentence, you can use an 'i'
> icon and add a tooltip"

Apply to every pane blurb, and to the assessment head's relation
sentence (3.7). Rule: **one sentence visible; everything else behind
`i`.** MISP ships Bootstrap 5, so use its tooltip; do not invent a
mechanism. Audit `wb-blurb` and `wb-note` — several are three to six
lines today.

Where a sentence must be cut, keep the half that says *what it does to
the reader's page*, not the half that justifies the design.

---

## 5. Fixtures this round needs

Nothing may be drawn from an invented number. Get these **before**
drawing the items that need them:

| Item | Needs | How |
|---|---|---|
| 3.12 buckets | a bucket→days + type→bucket example | after the decision; synthesise with a disclosure note, as `09a-fixtures-dump.php` already does for the missing signal |
| 3.14 curve | the runway series | `ValueRelevanceTool::runwaySeries()` already emits one float per day; read it via `/values/viewSightingChart` or the relevance card |
| 3.16 states | a three-state `auto_run` example | after the decision; synthesised |
| 3.19 modules | the real module roster + `mispattributes` | `AnalystProfilesController::__enabledModules()`, or the dev instance |
| 3.1 fork | the replace-confirm payload | `fork()` returns `{confirm, existing, affects}` — dump one |

**The established honest route** (used on 2026-09-08 for the relevance
runway) is: fetch from the dev instance over HTTP as the logged-in
admin, record the provenance in the fixture beside the numbers, and say
in the README where they came from. Do **not** write a Console shell
into `app/Console/Command/` — that path is bind-mounted from the user's
`misp-src` on the dev container.

---

## 6. Sequencing

**Three tracks. The first two are independent and can run in parallel;
the third must wait on decisions.**

**Track P — product bugs (no mockup dependency, do first, commit
separately).**
1. 3.13 — `TYPE_RULES` and clock divergence, `decay_speed` int→float +
   validator. *This one refuses a valid profile today.*
2. 3.18 — a `unit`/`suffix` affordance in the form field spec.
3. 3.9 (P half) — the band/weight rename in the form tool and the two
   ledger templates. **Ask first** — user-visible copy on a shipped page.

**Track M — mockup work that needs no decision (parallelisable).**
- Group A (index/edit chrome): 3.1, 3.2, 3.3, 3.5, 3.6
- Group B (panes): 3.8, 3.9 (M half), 3.10, 3.11, 3.15, 3.20
- Group C (cross-cutting, do **last** so it catches everything): 4.1, 4.2

Groups A and B touch different regions of one file. If two agents run in
parallel, **give each an exclusive region list and have them apply
patches by unique-anchor replacement with assertions**, not by line
number — the file is ~3900 lines and shifts under edits. Rebuild and run
the checker after *each* merge, not once at the end.

**Track D — blocked on decisions.** In order of value:
1. 3.16 + 3.19 (enrichment states, then the redesign that depends on the
   shape) — write into `08-enrichment.md`.
2. 3.12 (TTL buckets, and the fork shim) — write into `06-staleness.md`.
3. 3.17 (cost posture rename now; real cost is a misp-modules proposal).
4. 3.4 (add form) — **only if fork+import does not satisfy the reviewer.**

**Publish once per track**, to the same artifact URL, not once per item.

## 7. Ask the reviewer before building

Do not guess these:

1. **3.4** — after fork and import are drawn, is a blank-slate "create"
   still wanted? (It reverses a recorded decision.)
2. **3.16(b)** — auto-run needs a cache table or a working queue.
   Defer, or fund it?
3. **3.17** — real per-module cost needs a new misp-modules field.
   Raise upstream, or settle for a locality rename?
4. **3.20** — which name replaces "Reference data"?
5. **3.9(P)** — rename the band on the shipped value page too, or only
   in the editor?
6. **3.12** — bucket names (`fast`/`medium`/`long`?) and how many
   besides the default.

## 8. Verification

Per merged change: `check-mockup.sh` **PASS in both themes**; both
themes eyeballed; 1280px re-checked (it is a container query, so it
degrades on the *content column*, not the window); no figure that is not
in a fixture; `--vp-dir-*` still used only for the ledger delta.

At the end of the round: re-read §3 and confirm every item is either
done, explicitly deferred with a reason, or listed in §7 as waiting on
the reviewer. **An item silently dropped is the one failure mode this
document exists to prevent.**

## 9. Corrections this round owes the corpus

Two things already written down are wrong and should be fixed as part of
this work:

1. **`09b-decision.md` §4** — the section-to-axis map lists Thresholds
   as `quality — the bands`. It configures **the lean too**
   (`lean_supermajority`). Fix the map and the rail tag (3.10, 3.15).
2. **`mockups/workbench.html:2519-2522` and `:2542-2544`** — the clock
   and `type_rule` selects copied the form tool's wrong vocabularies,
   so the mockup offers two options the engine does not implement
   (`newest_occurrence`, `first`) and hides three it does
   (`last_sighting`, `last_occurrence`, `most_common`) (3.13).
