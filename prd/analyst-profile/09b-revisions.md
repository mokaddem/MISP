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
| **P** | **Product bugs found by this review.** Real defects in built code, independent of the mockup. | 3.10, 3.13, 3.18 |
| **D** | Schema and/or engine change. **All four were decided on 2026-09-10 — see §7.** | 3.9 (cut the band), 3.12 (TTL buckets), 3.16 (enrichment states), 3.17 (locality rename) |

3.4 is closed: no blank-slate form (§7.1). 3.9 grew — it is no longer a
rename but a field deletion that supersedes a recorded decision (§7.5).

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

### 3.4 — An "add"/"create" button · **decided: no** (§7.1)

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

**Decided 2026-09-10: no blank-slate form.** D5 stands. The create path
is **one control on the index** — *"New profile ▾ → Fork the instance
default / Import JSON"* — which is create-shaped, reverses nothing, and
is built already. Build that; do not add an `add` action.

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

> **Closed 2026-09-11** (`09c-wiring.md` §7.19). One sentence visible,
> the rest behind the `i` — and rewritten plainly: the faithful wording
> below is exact and is also the version a reader has to already know
> the model to parse.


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

> **Superseded 2026-09-11 — the control is cut** (`09c-wiring.md`
> §7.13). Everything above is still true about the *field*; what it
> never argued is that the **control** earns a select on eleven rows.
> It cannot change an assessment, the per-implementation default is
> already right, and overriding it files a row under a heading whose
> note then describes something else. The field stays in the schema and
> the engine still resolves it — only the select is gone.

### 3.9 — `strong` / `moderate` is **cut** · **decided** (§7.5)

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

There were two defects here. The first is a **name collision** —
"band" names two unrelated things on one screen: the signal's editorial
band (declared) and the quality band (derived,
`ValueVerdictTool.php:98`). The editor calls the first one *"Weight
band"* (`AnalystProfileFormTool.php:334-345`), which is wrong twice
over: it is not the weight and it is not a band.

**The second is that the field earns nothing, and the decision was to
cut it.**

> **Decided 2026-09-10: delete `band` from the signal schema.** The
> thing it claims to express — *what this evidence is worth in
> principle* — is already stated by `points.cap` in the next column, in
> real units. D14 concluded the labels were editorial because they could
> not be derived from contribution; the same evidence (`7` in both
> `moderate` and `weak`; `17` strong above `16` moderate; `5` moderate
> below `6` weak) reads at least as well as *the labels are arbitrary
> and nothing depended on them*. A field that contradicts the numbers
> beside it adds noise, not judgement.

**Written up as D16** (`03-signals.md` §5.1, 2026-09-10), which
supersedes D14 and carries the evidence: measured against each signal's
**ceiling** rather than its per-value contribution, the labelling
overlaps (`attribution.technique` `weak` at 9 beside
`reporting.published_ratio` `moderate` at 9) and calls
`sightings.false_positive` `moderate` though it can only subtract. Those
ceilings sum to 129 — the attainable bound the page already shows — so
the number the band gestures at is already computed and already on
screen. D14's §5 heading now carries a supersession notice.

What to remove, in one **product** commit:
- the per-signal `band` entry in the profile schema, and
  `ValueSignalBase::$default_band` (`:137`);
- `AnalystProfileFormTool::BANDS` (`:93`), the select
  (`:334-345`), and its validation (`:1633-1640`);
- the ledger row's `weight` key (`ValueVerdictTool.php:806-808`) and the
  two templates that print it (`value_verdict_ledger.ctp:126`,
  `value_verdict_card.ctp:110`);
- the column in the mockup's signals table.

**Back-compat:** `parameters` is opaque JSON, so a stored `band` on an
existing fork is simply ignored once nothing reads it. Do not write a
migration; do confirm nothing else reads the key first.

**Free the word:** after this, *band* means the quality band and nothing
else, which was half the confusion.

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

> **Reorder closed 2026-09-11** (`09c-wiring.md` §7.21), along with a
> second reviewer pass on the same pane: the clamp's controls now name
> what you are typing rather than what the record is treated as, the
> ceiling can be set to *no cap* at all, and the pane's two `i` tooltips
> are gone because nothing on it needs hiding any more. **Still open:**
> binding each input visually to its segment.

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

### 3.12 — Relevance: TTL buckets · **decided** (§7.6)

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

**Decided 2026-09-10: four buckets, plus a per-type override.** Named
**short / medium / long / very long**, each showing its day count.

The shipped table maps onto them with **no behaviour change**:

| Bucket | Days | Types today |
|---|---|---|
| short | 90 | `ip-dst`, `ip-src` |
| medium | 120 | `domain`, `email-src`, `hostname` |
| long | 365 | `btc`, `filename` |
| very long | 730 | `md5`, `sha1`, `sha256` |
| *(default)* | 180 | every other type |
| *(override)* | 60 | `url` — the single exception in the shipped default |

That is the argument for four rather than three: three would have forced
`url` and the 120-day group onto values they do not have, changing real
shelf life on every instance. Four plus an override preserves every
shipped number while collapsing the editor from 194 possible rows to
four choices and one exception.

**Written up as D18** (`06-staleness.md` §3.7, 2026-09-10), including
the stored shape, the read-time shim for forks carrying the flat map
(item 7 above — the one that decides whether this is cheap), the
validation changes, and the ruling that `type_rule` still compares
**days**: buckets resolve to their day count *before* `chooseType()`
compares, so `shortest` keeps meaning shortest.

The remaining design problem is the assignment UI: 194 types into four
buckets, so think select-many-types-into-a-bucket, not one row per
type. The override list is a short second table, not a third mode.

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

### 3.16 — Enrichment: run states · **decided** (§7.2)

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

**Decided 2026-09-10: ship (a) and (c) now, defer (b).** Build the
tri-state schema anyway, so auto-run can be added later without a second
migration.

**Written up as D17** (`08-enrichment.md` §2.3), naming the three states
`ticked` / `never` / `auto`, recording all three deferral reasons, and
adding one requirement the plan had not: **`never` must be enforced in
`ValueProfile::enrichmentRun()`**, not merely by disabling the checkbox —
the run endpoint takes a module name from the request, so a view-only
guard is not a guard. D15 stands: nothing auto-runs.

### 3.17 — Enrichment: "cost posture" is a misnomer · **decided** (§7.3)

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

**Decided 2026-09-10:** rename to something locality-honest —
*"Modules that leave the instance"* — and **drop `ask`**, leaving the two
options that actually differ. **No upstream proposal**: real per-module
cost is not pursued, so the hardcoded roster stays hand-maintained and
`ModuleLocality::retirable()` (`:256-272`) stays unused. Do not describe
this control as being about cost anywhere in the UI.

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

### 3.19 — Enrichment: "Modules per type" does not scale · **done 2026-09-12**

> "given the amount of type and module, the current UI doesn't scale.
> Redo that section from scratch"

Confirmed, with numbers: **194 attribute types × 146 modules**, and the
widget was a 194-key map whose every value offered all 146. Every
enabled module was offered for every type, with **no check that the
module even accepts that type** — `enrichmentErrors()` only checked
list-ness.

**The data to fix it was already in hand and never read.** A module
declares the attribute types it answers about in
`mispattributes.input`, which `ValueProfile::enrichmentModuleFacts()`
reads at *resolution* time — that is what `C_TYPE_MISMATCH` is — while
the editor offered a free choice and let the value tab complain
afterwards.

Measured on the dev instance 2026-09-12, against the running
misp-modules:

| | |
|---|---|
| modules on the service | 146 |
| distinct types they accept between them | **71** |
| types with no enrichment module at all | **123 of 194** |
| median types a module accepts | **3** (80 of 112 accept ≤ 4) |
| worst type | `ip-src` / `ip-dst`, 48 modules each |
| modules *enabled* on the dev instance | 8–9 |

**Built: the block is keyed by module and the document is not.**
`auto_run` still stores `type => {module: state}` — the engine, the
tab, the shipped default and every hand-written profile speak it — and
`AnalystProfileFormTool::transposeModules()` is the single place that
knows the editor's axis is not the document's. The form posts
`auto_run_modules`; nothing else ever sees it.

What shipped, in the same two blocks there were before:

1. **Modules, and the types you want them asked about.** One row per
   module, offering only the types that module accepts, each with the
   three D17 states. On the dev instance: nine rows of one to six
   selects.
2. **Where a module answers from** — unchanged.

**A third block was built and then removed, on a false premise.** The
argument for *What each type resolves to* was that a type no row
mentions keeps every enabled module ticked, so naming one module for
`ip-dst` would silently untick the rest — a consequence visible keyed
by type and invisible keyed by module, needing a derived table to
disclose it.

The premise came from the old block's own blurb and **the tab does not
do that.** `value_enrichment_rail.ctp` builds `$picked` from
`profile.selected` and ticks a box only for a name in it, and
`selected` holds exactly what the profile declared for the types this
value has. Measured on `8.8.8.8` with the shipped default: **5
eligible modules, 3 ticked** — the three declared. A module the
profile says nothing about arrives **unticked**, always. So a
declaration adds ticks rather than removing them, there was no hidden
un-ticking to disclose, and the table restated the rows above it.
Removed 2026-09-12 at the reviewer's request; the blurb that carried
the false claim is corrected with it.

**A declared module is drawn whatever its state** (see 3.21's second
half), which is the half that only matters for an imported profile.

`value_type` was renamed `module_states` → `state_map` in the same
pass: the value holds types now, and a key that says otherwise is the
quiet lie §1.3 forbids in the one place nobody would look for it.

### 3.20 — "Reference data" naming · bucket M

> "I don't really like the name 'reference'. Also, make sure to include
> the work 'reputation' in the organisation trust description text."

The section is org-trust grades plus warninglist meanings, Admiralty-
shaped — source reliability in, information credibility out
(`AnalystProfileFormTool.php:1038-1044`).

**Decided 2026-09-10: "Sources & reputation".** Rename the section
everywhere it appears — the form tool's title, the rail, the mockup —
and rewrite the org-trust blurb to use the word **reputation**.

Two facts the copy should not contradict: a `0.00` grade is *"an
accusation of deception, not a quality judgement"* (`07-reference.md:114`),
and trust weighting is **inert until the map is non-empty**.
### 3.21 — Enrichment: the locality posture is withdrawn · **decided 2026-09-12**

> "That whole posture thing. I don't think I want that feature anymore.
> Get rid of it both frontend and backend."

**Gone**: `locality_posture`, its `cost_posture` predecessor (3.17's
rename), the retired `ask`, `DEFAULT_POSTURE`, `postures()`,
`postureNote()`, the `C_POSTURE` condition, the `withheld` bucket in
`resolve()`, the editor pane and the label on the tab.

**Why it was never worth its vocabulary.** Under D15 nothing runs
without a press. So a module arriving unticked and a module arriving
ticked both send exactly nothing until the reader acts, and the setting
bought a whole grammar of refusal — a bucket, a condition id, two
sentences, a pane, a legend that read the posture to say what a
locality *did* — in exchange for saving one click. 3.17 had already
found it was misnamed; the honest conclusion was one step further on.

**What is kept, and the distinction that matters.** `ModuleLocality`
stays, the `enrichment.locality` override map stays, and the tab still
says per module whether asking it leaves the building. **Locality is a
label, not a gate**: it is what a reader consults before pressing run.
Where the posture label sat on the strip there is now
`leavingCount()`'s number — *"3 of these would leave the instance"* —
which is the fact the posture was reached for, computed from the
selection in front of the reader rather than from a setting they set
once.

`never` survives untouched. It is the reader's own refusal rather than
a fact about a module, and it is enforced at the run endpoint, not
merely drawn unticked (D17).

**A stored key is ignored rather than migrated**: it selected nothing,
so there is nothing to carry. `legacyShapes()` names it once and the
next save of any section drops it.

### 3.22 — Enrichment: the shipped default declares a mapping · **decided 2026-09-12**

> "I think it would make a lot of sense to - by default - provide some
> mapping on the default profile. Like geo-lookup, passive-dns, dns
> resolution, ... prioritise modules using CIRCL's services"

`default-v1.json` shipped `"auto_run": {}` through version 8, which was
§1.3's *"empty means as before"* applied to this section. **Version 9
fills it**, for thirteen types and twelve modules, CIRCL-first:

| Types | Modules |
|---|---|
| `ip-src`, `ip-dst` | `mmdb_lookup`, `ipasn`, `circl_passivedns`, `circl_passivessl`, `reversedns` |
| `ip-src\|port`, `ip-dst\|port` | `mmdb_lookup`, `circl_passivedns`, `circl_passivessl` |
| `hostname` | `circl_passivedns`, `dns` |
| `domain` | `circl_passivedns`, `dns`, `whois` |
| `domain\|ip` | `dns`, `reversedns` |
| `md5`, `sha1`, `sha256` | `hashlookup` |
| `vulnerability` | `vulnerability_lookup`, `cve` |
| `onion-address` | `onion_lookup` |
| `ssh-fingerprint` | `passive_ssh` |

Nine of the twelve are CIRCL-operated. Five need no credentials at all
(`mmdb_lookup`, `hashlookup`, `vulnerability_lookup`, `cve`,
`onion_lookup`); `circl_passivedns`/`circl_passivessl` want a CIRCL
account and `passive_ssh` an API key, which is why the credential-free
one leads each row.

Two deliberate omissions. **`url` gets nothing** — no CIRCL URL service
is in the roster, and a gap is better than reaching for a vendor.
**`geoip_*` is not the geo default** despite being the only *local* geo
option, because it needs a Maxmind Geolite file MISP does not ship;
`mmdb_lookup` against `ip.circl.lu` is the answer that works out of the
box.

**Three things this mapping does not do**, and the third is the one to
read twice:

- It does not **run** anything. A run takes a press (D15).
- It does not **enable** anything. A module an administrator has not
  turned on stays off — `Enrichment_services_enable` itself ships
  `false` — and the editor draws the row saying so rather than hiding
  it.
- It **narrows**. A type listed here arrives with these modules ticked
  and every other module for that type unticked. That is the point of a
  curated default and it is a real behaviour change: on an instance
  with 48 modules accepting `ip-dst`, 43 of them stop arriving ticked.
  3.19's derived table is where a reader sees it.

**The reviewer waived the version-bump cost** ("I don't care. We're
developping things right now"): `updateDefaults()` overwrites local
edits to the default profile when the shipped version rises, so v10
discards whatever an admin changed since v8.


### 3.23 — Enrichment: the module row earns its place · **done 2026-09-12**

Six follow-ons to 3.19, once the table was module-keyed and could be
looked at properly.

**One control per row, not one per type.** The usual declaration is
*this module, for everything it accepts*, and `circl_passivedns` alone
was six identical choices — 36 selects across the shipped default's
twelve rows. A row-wide setter writes them all and dispatches a single
synthetic `change`, so the existing handler marks, re-prices and
debounces the bench exactly as it would have one select at a time. It
posts nothing itself and is hidden until the page can drive it: a
control that writes fields rather than being one.

**The module says what it does.** `meta.description` was in the
introspection payload all along and thrown away, leaving a column of
identifiers — `ipasn`, `mmdb_lookup`, `circl_passivedns` are not
self-describing to anybody who has not read misp-modules. Capped rather
than rewritten: median 53 characters, maximum **379**, seven carrying a
project URL. The first-sentence cut that looked tidier was withdrawn
when it turned `onion_lookup` into *"MISP module using the MISP
standard."* — the boilerplate opener, with the meaning in the sentence
after. The filter box reads it too, so *geolocation* finds
`mmdb_lookup` without knowing the name.

**Enabled and unconfigured is now visible.** The quietest failure the
page had: the declaration saves, the row looks healthy, the box arrives
ticked, and the analyst learns by pressing Run. `ModuleCredentials`
ships the roster, because introspection carries `meta.config` and
**nothing that says which keys are required** — and the obvious guess
would warn about `mmdb_lookup`, which declares three settings and works
unconfigured against `ip.circl.lu`. Membership cites the module's own
`requirements` text; where there is nothing to cite, the file is
silent. `passive_ssh` is the entry that documents the rule by being
absent.

**Locality moved onto the row** — *does asking this tell somebody
outside* is the question being answered at the moment of choosing, and
it lived two blocks down. **Only where it is known.** Drawn for all
three states it read `may leave the instance` on all twelve rows of the
shipped default, because `ModuleLocality` names the local modules and a
value page rarely has a type for one. A badge that says the same thing
on every row is wallpaper, including on the row where it differs — so
the presumption is stated once in the blurb and the pill marks only
what somebody established.

**`expansion, hover` is gone** from the row. It is misp-modules
vocabulary in a place an analyst reads for meaning.

**Long rows fold and the table filters.** `farsight_passivedns` accepts
20 types; past six a row is a wall. Folded rows are rendered and hidden
rather than dropped, so they still post their stored state and a reader
without JavaScript sees all of it — and **a type the reader has an
opinion about is never folded**, since hiding a declared type would
hide the declaration. The filter appears at twelve rows, which is where
the shipped default lands.

Verified in a browser rather than asserted: filter 12 → 1 on a name and
12 → 1 on a description, a bulk setter writing six selects and lighting
the section's unsaved-changes mark, and the fold moving 6 ↔ 20 with the
button reading *show all 20 types* / *show fewer*. No page errors.

**Not done, and deliberately:** sorting unavailable rows last, and a
picker that shows what an administrator has not enabled.


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

**Three tracks. All six decisions are made (§7), so nothing is blocked
on the reviewer any more — Track D now means "write the decision down,
then build it", not "wait".**

**Track P — product bugs (no mockup dependency, do first, commit
separately).**
1. 3.13 — `TYPE_RULES` and clock divergence, `decay_speed` int→float +
   validator. *This one refuses a valid profile today.*
2. 3.18 — a `unit`/`suffix` affordance in the form field spec.

**Track M — mockup work (parallelisable).**
- Group A (index/edit chrome): 3.1, 3.2, 3.3, 3.5, 3.6
- Group B (panes): 3.8, 3.10, 3.11, 3.15, 3.20
- Group C (cross-cutting, do **last** so it catches everything): 4.1, 4.2

Groups A and B touch different regions of one file. If two agents run in
parallel, **give each an exclusive region list and have them apply
patches by unique-anchor replacement with assertions**, not by line
number — the file is ~3900 lines and shifts under edits. Rebuild and run
the checker after *each* merge, not once at the end.

**Track D — decided and written up. Build to the PRD entries.**
D16, D17 and D18 were written on 2026-09-10 (`03-signals.md` §5.1,
`08-enrichment.md` §2.3, `06-staleness.md` §3.7) and are in the register
in `README.md`. Nothing here needs a decision or a write-up first — go
straight to the schema, then the drawing. In order of value:

1. **3.9 — cut the signal `band`.** Supersede D14 in `03-signals.md`,
   then remove the field, its validation, the ledger's `weight` key and
   the two templates. Do this early: it *deletes* a column the other
   panes would otherwise be redrawn around.
2. **3.16 + 3.19** — enrichment run states into `08-enrichment.md`
   (ship ticked + cannot-run, defer auto-run with its reasons), then the
   per-type redesign that depends on the settled shape.
3. **3.12** — four buckets + override and the fork read-shim into
   `06-staleness.md`, then the schema, then the assignment UI.
4. **3.17** — the locality rename and dropping `ask`. Small, no PRD
   entry needed beyond a line.
5. ~~3.4 (add form)~~ — closed, see §7.1.

3.9 was a copy fix when this document was written and is now a schema
change; it moved from Track M to here.

**Publish once per track**, to the same artifact URL, not once per item.

## 7. Decisions — taken 2026-09-10

All six open questions were put to the reviewer and answered. **These
are settled. Do not re-open them; build to them.**

| # | Question | Decision |
|---|---|---|
| 1 | blank-slate create form | **No** — fork + import only |
| 2 | enrichment auto-run | **Defer** — ship ticked + cannot-run |
| 3 | module cost | **Rename only** — drop `ask`, nothing upstream |
| 4 | "Reference data" | **"Sources & reputation"** |
| 5 | the `strong`/`moderate` label | **Cut the field entirely** |
| 6 | TTL buckets | **Four + per-type override** |

**7.1 — No blank-slate create form.** D5 stands. The create path is one
control on the index: *"New profile ▾ → Fork the instance default /
Import JSON"*. Both actions are already built. → 3.4, 3.1, 3.2.

**7.2 — Enrichment: ship two states, defer the third.** *Ticked by
default* and *cannot be run* land this round; *run automatically* is
deferred until a last-run store or a working queue exists. Build the
tri-state schema now so adding it later needs no second migration.
→ 3.16.

**7.3 — Locality, not cost.** Rename the setting, drop the `ask` option
that does nothing, and make **no** misp-modules proposal. Real
per-module cost is out of scope indefinitely; the 22-name roster stays
hand-maintained. → 3.17.

**7.4 — "Sources & reputation"** replaces "Reference data", and the
org-trust blurb uses the word *reputation*. → 3.20.

**7.5 — Cut `strong`/`moderate`/`weak` entirely.** Not renamed — removed.
It changes no score, and the signal's **ceiling** already says what it
claims to say, in real units — and that number is already summed into
the attainable bound. Written up as **D16**, superseding D14. → 3.9.

**7.6 — Four TTL buckets plus a per-type override**, named *short /
medium / long / very long* (90 / 120 / 365 / 730), with `url` at 60 as
the single shipped override. Chosen over three because three could not
express the shipped table without changing real shelf life on every
instance. → 3.12.

**What changed in this document as a result:** 3.4 closed; 3.9 grew from
a rename into a field deletion that supersedes a recorded decision; 3.12,
3.16, 3.17 and 3.20 have their shapes fixed. §2's triage table is
updated to match.

## 8. Verification

Per merged change: `check-mockup.sh` **PASS in both themes**; both
themes eyeballed; 1280px re-checked (it is a container query, so it
degrades on the *content column*, not the window); no figure that is not
in a fixture; `--vp-dir-*` still used only for the ledger delta.

At the end of the round: re-read §3 and confirm every item is either
done, explicitly deferred with a reason, or listed in §7 as waiting on
the reviewer. **An item silently dropped is the one failure mode this
document exists to prevent.**

### 8.1 The round, closed 2026-09-11

Every §3 item, and what happened to it.

| Item | State |
|---|---|
| 3.1 fork | **done** — both confirms drawn, the org one naming a measured count |
| 3.2 import / export | **done** — the create menu, and Export on every row that can |
| 3.3 index actions | **done** — the default row loses Edit and gains View; the disabled one gains Simulate and Export |
| 3.4 add / create | **closed** by §7.1; the create control is drawn |
| 3.5 pinned values | **done** — kept, explained at the point of use, empty state drawn, pin put in the simulator. The spec/build gap is recorded in `09-editor.md` §2.2 |
| 3.6 value picker | **done** — a search plus the pinned set as quick-switches |
| 3.7 the three axes | **done** — the relation paragraph is one sentence and a tooltip |
| 3.8 group column | **done** — the repetition gone, the control kept as a row action |
| 3.9 cut the band | **done** — product, shipped default, fixtures, mockup |
| 3.10 thresholds pane | **done** — order matches the strip, each input under its segment, and the IA error fixed in both the rail and `09b-decision.md` §4 |
| 3.11 conflict rules | **done** — what it moves, and the ledger's anchoring as the second-order effect |
| 3.12 TTL buckets | **done** — D18 built, with the read shim and its own harness section |
| 3.13 `type_rule` + two bugs | **done** — both divergences fixed in a separate commit, `decay_speed` made a float and given a validator, both mockup selects corrected |
| 3.14 curve chart | **done** — inline SVG, no client JS, with the aging tick and this value's position |
| 3.15 where the lean is configured | **done** — both panes carry an axis tag, and the supermajority states its inputs |
| 3.16 enrichment run states | **done** — D17 built; `auto` declared and inert, `never` enforced in the run path |
| 3.17 cost posture | **done** — D19: renamed, `ask` retired, both read as shims |
| 3.18 reuse window | **done** — a general `unit` on the field spec, and the setting drawn as inert |
| 3.19 modules per type | **done** — rebuilt module-first against real `mispattributes` |
| 3.20 reference naming | **done** — *Sources & reputation*, in the form tool and the mockup |
| 4.1 strip scaffolding | **done** — and `check-1280.sh` replaces the inset it removed |
| 4.2 one-sentence copy | **done** — nine blurbs cut, the rest behind `i` |

**Three defects this round found that it had not predicted**, all fixed:

1. **The shipped default still carried eleven `band` keys** after D16
   removed the field. Nothing read them, so nothing broke — the
   instance default was simply asserting a value the engine had stopped
   carrying. Version 5 → 6.
2. **D18's first implementation let the two TTL shapes blend.** A
   legacy `ttl_days` entry became an override, and overrides beat
   bucket assignments, so a fork the editor had upgraded — with a stale
   flat map still beside its new buckets — would have had the stale
   entry silently shadow its own assignment. The shapes no longer
   blend, and `merge()` drops the legacy key on save.
3. **D16's own survey undercounted.** It named two templates printing
   the ledger's `weight`; there were three, plus a second writer in
   `ValueVerdictDiffTool`. `03-signals.md` §5.1 records both.

**Harness totals at the close**, all green: store 34, signals 98, lean
and bands 100, exclusions 42, relevance 133, reference 114, enrichment
123, editor 137. Fixtures 28. `check-mockup.sh` PASS in both themes and
`check-1280.sh` OK in both.

**Not done, and deliberately:** `auto` is declared and not implemented
(D17 §7.2 — it needs a last-run store or a working queue, both bigger
than this round), and the `max_age_hours` reuse window is still inert
because no cache table exists. Both are drawn as inert rather than
hidden.

## 9. Corrections this round owes the corpus

Two things already written down are wrong and should be fixed as part of
this work:

1. **`09b-decision.md` §4** — the section-to-axis map lists Thresholds
   as `quality — the bands`. It configures **the lean too**
   (`lean_supermajority`). Fix the map and the rail tag (3.10, 3.15).
   **Done 2026-09-11**, and the table carries the correction and its
   reason.
2. **`mockups/workbench.html:2519-2522` and `:2542-2544`** — the clock
   and `type_rule` selects copied the form tool's wrong vocabularies,
   so the mockup offers two options the engine does not implement
   (`newest_occurrence`, `first`) and hides three it does
   (`last_sighting`, `last_occurrence`, `most_common`) (3.13).
   **Done 2026-09-11.** The form tool no longer has its own copies of
   either list — both use sites read `ValueRelevanceTool`'s constants,
   the way `max_band` already read `ValueVerdictTool::BANDS` — so the
   two lists cannot drift again.
