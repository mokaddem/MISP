# PRD: MISP Value Profile page (`/values/view`)

## 1. Overview

### 1.1 Purpose

MISP is event-centric: every screen answers "what is in this event?". This adds
a page whose subject is a single **value** — `185.234.219.24`, a hash, a domain
— aggregating every occurrence of it across events, organisations, sightings,
opinions, correlations, decay models and feeds into one view.

The design source is the Claude Design canvas *Value Profile — MISP-native*
(project `aa30814d-d6e9-4cc1-a54a-cea51a463bc7`), which supplies three
artboards: **A** the Overview tab, **B** the Verdict tab for a value that
resolves to malicious, **C** the Verdict tab for a value that resolves to
conflicted. The written brief behind it is `prd/attribute-value-page.md`.

A fourth state, **BENIGN**, was added after the artboards. It has no artboard
because it needs no new layout: a value everything points away from is the same
argument as one everything points at, and it shares artboard B's template.

### 1.2 Scope of this PRD

This PRD covers the **skeleton pass only**: the real page, real routing, real
chrome, real interactions — with hardcoded data. No model queries, no writes.
Each panel's live implementation is a separate follow-up, and the whole design
here exists to make each of those a local change.

### 1.3 Design principles

- **Reuse MISP's factories.** The page is assembled from `headerSection`,
  `view_layout`, `index_table`, the `IndexTable/Fields/*` renderers and the
  `Badges/*` renderers. New markup is written only for primitives MISP has no
  equivalent for.
- **Fixture shaped like the real thing.** Templates are written against MISP's
  actual array shapes and field names, so going live swaps a data source, not a
  template.
- **Additive changes to shared code.** Every edit to a file outside this feature
  is guarded so existing callers render byte-identically.
- **Honest states.** "Not implemented", "nothing to show" and "hidden from you
  by ACL" are three visually distinct things.
- **Nothing writes.** Controls that would write to the database render visibly
  disabled, not silently dead.

---

## 2. Architecture

### 2.1 Files

New:

```
app/Controller/ValuesController.php
app/Lib/Tools/ValueProfileFixture.php
app/View/Themed/Overmind/Values/view.ctp
app/View/Themed/Overmind/Elements/Values/View/
    value_fact_strip.ctp
    value_pivot_rail.ctp
    value_occurrences.ctp          left,  Overview
    value_context.ctp              left,  Overview
    value_analyst_preview.ctp      left,  Overview
    value_verdict_card.ctp         right, Overview
    value_sightings.ctp            right, Overview
    value_lifecycle.ctp            right, Overview
    value_external.ctp             right, Overview
    value_verdict.ctp              left,  Verdict (signals agree)
    value_verdict_conflicted.ctp   left,  Verdict (conflicted)
    value_verdict_ledger.ctp       the signal table
    value_verdict_orgs.ctp         Who says what, both layouts
    value_verdict_warninglist.ctp  the warninglist band, both layouts
    value_placeholder.ctp          the seven stubbed tabs
app/View/Themed/Overmind/Elements/genericElementsBS5/IndexTable/Fields/
    value_object_context.ctp
app/webroot/css/value-profile.css
```

Modified, all additive and guarded:

```
app/webroot/js/mispOvermind.js                     .ajax-card lazy loading
.../genericElementsBS5/Layout/view_layout.ctp      optional tab 'badge'
.../Overmind/Elements/headerSection.ctp            optional $headerBreadcrumb
app/Lib/Tools/OvermindPages.php                    register values actions
app/Controller/Component/ACLComponent.php          values ACL entries
app/View/Helper/NavbarHelper.php                   menu entry + menu map
```

### 2.2 Controller

`ValuesController extends AppController`. Default CakePHP routing supplies the
URLs; no `routes.php` change.

| Action | URL | Renders |
|---|---|---|
| `view($b64value)` | `/values/view/<b64>` | full page |
| `viewOccurrences($b64value)` | ajax | `value_occurrences` |
| `viewContext($b64value)` | ajax | `value_context` |
| `viewAnalystPreview($b64value)` | ajax | `value_analyst_preview` |
| `viewVerdictCard($b64value)` | ajax | `value_verdict_card` |
| `viewSightings($b64value)` | ajax | `value_sightings` |
| `viewLifecycle($b64value)` | ajax | `value_lifecycle` |
| `viewExternal($b64value)` | ajax | `value_external` |
| `viewVerdict($b64value)` | ajax | `value_verdict` or `value_verdict_conflicted` |

`app/Lib/Tools/ValueDisposition.php` holds the one mapping from a disposition to
its colour, glyph, modifier slug and whether it names a state at all — read by
the tab badge, the disposition pill and the Verdict tab, each of which had grown
its own copy.

Every action decodes the value with `base64_decode($v, true)` and throws
`NotFoundException` on invalid encoding — the same guard the existing
`AttributesController::getAttributeByB64Value` uses. Ajax actions set
`$this->layout = false` and render their element only.

`viewVerdict` picks its template from the fixture's disposition, so the
conflicted layout is a property of the value rather than a display mode.
MALICIOUS and BENIGN both take `value_verdict`; only CONFLICTED branches.

### 2.3 Fixture contract

`ValueProfileFixture::forValue(string $value): array` returns one structure per
value, in MISP's own array shapes:

```php
[
    'value'        => '185.234.219.24',
    'types'        => [['type'=>'ip-dst','count'=>7], ...],
    'value2_note'  => '1 occurrence has it as value2 of domain|ip',
    'facts'        => [['label'=>..,'value'=>..,'sub'=>..], ... x6],
    'pivots'       => [['label'=>'Containing CIDR','hint'=>'185.234.216.0/22'], ...],
    'occurrences'  => [
        [
            'Attribute' => [
                'id'=>.., 'uuid'=>.., 'type'=>'ip-dst',
                'category'=>'Network activity', 'to_ids'=>1,
                'distribution'=>3, 'comment'=>..,
                'first_seen'=>.., 'last_seen'=>.., 'deleted'=>0,
                'object_relation'=>'ip-dst',
            ],
            'Event'        => ['id'=>1284,'info'=>..,'Orgc'=>['name'=>'CIRCL']],
            'Object'       => ['name'=>'network-connection'],
            'SharingGroup' => ['name'=>null],
            'AttributeTag' => [['Tag'=>['name'=>'tlp:amber','colour'=>..]], ...],
        ], ...
    ],
    'occurrence_acl_note' => 'Showing 6 of 10 ... 4 are hidden by distribution
                              rules on events owned by other organisations.',
    'tags'         => grouped by taxonomy, with counts and local flags,
    'galaxies'     => [['name'=>'APT28','kind'=>'Threat actor · Sofacy','n'=>2], ...],
    'sightings'    => ['total'=>47,'fp'=>1,'expiration'=>0,
                       'spark'=>[40 ints],'reporters'=>[...],'last'=>'2 days ago'],
    'decay'        => [['model'=>'NIDS Simple Decaying Model','score'=>78,
                        'threshold'=>60,'decayed'=>false,'curve'=>[40 ints]], ...],
    'warninglists' => [],   // or hits, with name / version / category
    'correlations' => ['count'=>31,'over_correlating'=>false],
    'external'     => ['feeds'=>[...],'servers'=>2,'sightingdb'=>1204],
    'verdict'      => [
        'disposition' => 'MALICIOUS',   // or BENIGN / CONFLICTED / UNKNOWN
        'score'       => 84,
        'confidence'  => 'high',
        'summary'     => prose,
        'profile'     => 'default-v3',
        'computed_at' => timestamp,
        'acl_note'    => '4 occurrences you cannot see were excluded',
        'ledger'      => grouped signal rows,
        'conflicts'   => to_ids disagreement + its occurrence rows,
        'orgs'        => per-organisation consensus rows,
        'composition' => weighted contribution segments,
    ],
]
```

Presentational values from the artboard (`idsBg`, `idsFg`, `w:"78%"`) are **not**
carried. Colour and badge styling are derived by the factories from domain
values — `distribution.ctp` resolves an int through the `DistributionLevel`
helper, `ids.ctp` renders the `to_ids` flag, `tag_list.ctp` takes tag arrays.

Values are stored **refanged**, as MISP stores them: `185.234.219.24`, not
`185.234.219[.]24`. `ComplexTypeTool::refangValue()` normalises on ingestion, so
there is no stored defanged form and the artboard's defang toggle is dropped —
along with its "Defanged" chip.

### 2.4 Page composition

`Values/view.ctp` sets the shared header variables, then renders two bespoke
strips, then the tab layout:

```php
$this->set('headerTitleHtml', value + type chips markup);
$this->set('headerBreadcrumb', 'Data points > Value Profile');
$this->set('headerActions', [...]);
echo $this->element('Values/View/value_fact_strip', [...]);
echo $this->element('Values/View/value_pivot_rail', [...]);
echo $this->element('genericElementsBS5/Layout/view_layout', ['tabs' => [...]]);
```

`headerActions` carries Add sighting, Enrich, Add to collection, an Export
dropdown (STIX 2.1 / Suricata / Zeek / RPZ / CSV / copy restSearch) and the
Watch toggle, using the existing action types and per-tab `'tab'` scoping. Every
one renders disabled in this pass.

Artboards B and C draw a condensed banner (a single fact line, two buttons).
The header renders once per page, not per tab, so artboard A's fuller banner is
the one implemented. Artboard C's "Warninglist hit" chip beside the value is
kept, driven by `warninglists`.

### 2.5 Tab registry

`view_layout` gives the `col-lg-9` / `col-lg-3` split, hash-based activation and
`data-header-tab` syncing.

| # | id | Title | Icon | Count | State |
|---|---|---|---|---|---|
| 1 | `general` | Overview | `fa-info-circle` | — | built |
| 2 | `verdict` | Verdict | `fa-gavel` | badge | built |
| 3 | `occurrences` | Occurrences | `misp-icon-attribute` | 10 | stub |
| 4 | `sightings` | Sightings | `misp-icon-sighting` | 47 | stub |
| 5 | `relationships` | Relationships | `fa-link` | 31 | stub |
| 6 | `enrichment` | Enrichment | `fa-wand-magic-sparkles` | 9 | stub |
| 7 | `analyst` | Analyst data | `misp-icon-analyst-note` | 6 | stub |
| 8 | `timeline` | Timeline | `fa-clock` | — | stub |
| 9 | `history` | History | `fa-history` | — | stub |

MISP glyphs are written in full as `misp-icon misp-icon-<name> misp-simple`, the
form `view2.ctp` already uses. The available names are `attribute`, `event`,
`object`, `sighting`, `report`, `galaxy`, `tag`, `taxonomy`, `organisation`,
`sharing-group`, `user`, `misp`, `analyst-note`, `analyst-opinion` — there is no
generic analyst glyph, so the Analyst data tab uses `analyst-note`.

The Verdict tab carries a state pill (`MALICIOUS 84`, `CONFLICTED`) with a
colour dot, via the new optional `badge` key.

### 2.6 Panel inventory

**Overview, left column**

- `value_occurrences` — `index_table` with fields `checkbox`, `event_info`,
  `organisation`, `type`, `category`, `ids`, `distribution`,
  `value_object_context` (new), `datetime`, `tag_list`. No `sort` keys, so no
  Paginator is involved. Header shows "10 attribute rows across 7 events · 4
  organisations", an include-soft-deleted toggle, and "Open full table →". Footer
  carries the ACL truncation note. `multi_select_toolbar` supplies the bulk bar.
- `value_context` — tags grouped by taxonomy with occurrence counts, local-tag
  marking, TLP/PAP in canonical colours, `admiralty-scale` as a labelled scale,
  a `conflict` marker where TLP disagrees; then galaxy cluster chips with counts.
- `value_analyst_preview` — most recent notes and opinions.

**Overview, right rail**

- `value_verdict_card` — disposition, confidence bar, the top three signals with
  direction glyph and weight, "3 further signals not shown", "Full assessment →"
  jumping to the Verdict tab.
- `value_sightings` — total split by MISP's three sighting types, a 90-day CSS
  bar sparkline, top reporters, a disabled "I saw this".
- `value_lifecycle` — per-model decay score as CSS bars with the `decayed` flag,
  warninglist result ("No warninglist hit · 84 lists checked"), correlation count
  against the over-correlation threshold.
- `value_external` — feeds holding the value, sync servers with a cache hit,
  SightingDB hit count.

**Verdict tab, signals agree** (`value_verdict`) — MALICIOUS and BENIGN

1. Hero: disposition, confidence, score, prose summary, Recompute / view-as-JSON
   (disabled), "Computed at render", weighting profile name, the ACL exclusion
   note, and "Not stored, not synchronised".
2. Signal ledger grouped by kind — Signal | Evidence | Contribution | Source
   panel | As of — with expandable rows.
3. Contradictions & conflicts, explicitly not netted off: the `to_ids`
   disagreement with its own occurrence table and two disabled actions.
4. Who says what — one row per organisation: occurrences, sightings, false
   positives, opinion, `to_ids` stance, source reliability.
5. How the score was reached — composition segments and total.
6. Verdict over time — 90-day decay curves.

**Verdict tab, conflicted** (`value_verdict_conflicted`)

1. Hero: CONFLICTED, prose, a malicious-vs-benign tug-of-war bar, signal counts,
   the conflict rule that produced the state.
2. Warninglist callout: list name, version, category, matching CIDR, and the
   explanation that a `known`-category hit means shared infrastructure rather
   than benign.
3. Two opposed cases side by side, each row with weight, evidence and source.
4. Unresolved signals, counted for neither side.
5. Who says what — per organisation, with how each reads the value.
6. Resolve it — four resolution cards, each naming exactly what it would write.
7. Opinion distribution — histogram plus the note that a bimodal mean is
   meaningless.

On a BENIGN value the same bands carry the opposite argument: the disposition's
colour and glyph come from `ValueDisposition`, the warninglist band from the
conflicted layout appears between provenance and the ledger (a benign call
usually rests on a listing), and `Who says what` gains a `Reads the value as`
column wherever the fixture supplies one.

The `score` is **support for the stated disposition**, not a malice reading —
MALICIOUS 84 and BENIGN 91 both mean "well evidenced", and a ▲ ledger row is one
that supports the verdict rather than one that argues malicious. The direction
colours follow from that: red stays the malicious reading in both layouts, so ▲
is red on a malicious value and green on a benign one, via `--vp-dir-with` /
`--vp-dir-against`.

**Stubs** — `value_placeholder` renders one card reading "<Tab> — not yet
implemented", visually distinct from an empty state.

### 2.7 Shared-element changes

- **`mispOvermind.js`** — `loadAjaxContainer` is currently only called for
  `.ajax-tab-content`; the `.ajax-card` container that `view_layout` emits for
  right-rail cards has no loader and would spin forever. Add `.ajax-card` to
  both call sites. This fixes dead code and lets `event_sightings.ctp` /
  `event_related.ctp` drop their hand-rolled `fetch()` later.
- **`view_layout.ctp`** — optional `$tab['badge']` (`label`, `color`, `dot`),
  guarded by `!empty()`, so the ten existing callers are unaffected.
- **`headerSection.ctp`** — optional `$headerBreadcrumb` overriding the
  controller/action-derived crumb, so this page reads "Data points > Value
  Profile" rather than "Values > View".

### 2.8 Registration

- `ACLComponent::ACL_LIST` (a class constant, not a property) — `'values' =>
  [...]`, every action `['AND' => ['*', 'theming_enabled']]`. The file's own
  comment is explicit that anything absent from this list is site-admin-only, so
  registration is mandatory, not optional.
  Read-only, so per-row ACL belongs in the
  model calls of the follow-up passes; `theming_enabled` (which resolves to
  `MISP.enable_themes`) turns a themes-disabled instance into a clean 403 rather
  than a missing-view 500, matching how `editAttributeTags` and
  `analyst_data/viewForObject` are already gated.
- `OvermindPages::$pages` — `'values' => ['view', 'viewOccurrences', ...]` so the
  BS5 chrome is applied.
- `NavbarHelper` — `ACTIVE_MENU_MAP += 'values' => 'datapoints'`, plus a "Value
  Profile" entry under the existing Data points menu pointing at the demo value.
  That entry is a skeleton affordance, to be replaced by a real value lookup.

No unthemed fallback view, the same posture as `Events/view2.ctp`.

### 2.9 CSS

`app/webroot/css/value-profile.css`, loaded through
`genericElements/assetLoader`. Covers only the primitives MISP has no class for:

```
.vp-fact      fact-strip cell
.vp-pivot     pivot chip
.vp-signal    verdict signal row
.vp-weight    contribution weight bar
.vp-spark     sighting sparkline bars
.vp-decay     decay score bars
.vp-tug       conflicted tug-of-war bar
.vp-case      opposed case column
```

Every colour comes from an existing variable (`--sighting`, `--correlation`,
`--galaxy`, `--warninglist`, …), never raw hex, and each block has a
`[data-bs-theme="dark"]` counterpart — Overmind ships a dark theme that the
light-only artboards give no guidance on. Spacing uses the artboard's literal
pixel values rather than Bootstrap's scale.

### 2.10 Charts

Chart.js — already shipped and used by `view2` and the dashboard widgets — for
the two decay-over-time curves, the conflicted tab's two competing curves, and
the opinion histogram. `Chart.min` is loaded once at page level via
`assetLoader`, not per fragment, and canvas ids are namespaced per panel so two
cards cannot collide. Init inside a lazily-injected fragment is safe because
`loadAjaxContainer` re-creates every `<script>` *after* setting `innerHTML`, so
the canvas is in the DOM when init runs.

CSS bars for the sparkline, decay bars, composition segments, weight bars and
tug-of-war.

### 2.11 Interactivity

Working, all client-side against data already in the DOM: type-chip filtering,
include-soft-deleted toggle, row selection revealing the bulk bar, expandable
verdict signal and conflict rows, fact-strip and "Full assessment →" tab jumps,
the Export dropdown opening.

Disabled with an explanatory `title`: Add sighting, Enrich, Add to collection,
Watch, "I saw this", every bulk operation, every export target, Recompute, the
`to_ids` mass actions and every resolution card.

### 2.12 Unknown values

`forValue()` knows `185.234.219.24` (malicious), `104.21.34.198` (conflicted)
and `8.8.8.8` (benign — Google Public DNS, hit by a `false_positive`-category
warninglist). Any other value renders the **full page** — banner, tab bar, all
nine tabs — with the value shown, zero counts, an `UNKNOWN` verdict and every
panel in its own empty state. This is the majority case once the page takes live
data, so the empty states get designed now rather than discovered later.

---

## 3. Implementation phases

**Phase 1 — routing and chrome.** Controller with `view` only, fixture returning
the malicious value, `Values/view.ctp`, ACL / OvermindPages / Navbar
registration, `headerBreadcrumb`, the fact strip and pivot rail, the nine-tab
bar with all tabs stubbed. Exit criterion: the page loads, the breadcrumb and
navbar highlight are right, all nine tabs switch.

**Phase 2 — lazy loading.** The `.ajax-card` fix, `view_layout`'s `badge` key,
and the ajax actions returning placeholder bodies. Exit criterion: right-rail
cards resolve from spinner to content.

**Phase 3 — Overview.** The three left panels and four rail cards, `index_table`
wiring, the new `value_object_context` field renderer, `value-profile.css`, the
CSS-bar visualisations. Exit criterion: artboard A is recognisable in the
browser, light and dark.

**Phase 4 — Verdict.** Both templates, the Chart.js curves and histogram, the
disposition-driven template choice. Exit criterion: both demo values render
their own artboard.

**Phase 5 — states and interactions.** Client-side interactions, disabled-control
treatment, the unknown-value sparse page, per-panel empty states. Exit
criterion: all three states reachable and distinguishable.

---

## 4. Key design decisions

**A dedicated controller, not an Attributes action.** The subject is a value,
not an attribute row, and `AttributesController` is already ~3,800 lines across
40+ actions. A separate controller also gives clean per-panel ajax endpoints.

**Reuse the shared header rather than a bespoke banner.** `headerSection`
already implements action grouping, per-tab scoping, modal wiring and i18n —
the fiddly parts. Only the fact strip and pivot rail, which it has no concept
of, are new. Extending the shared header with generic slots was rejected: it is
rendered by every Overmind page, and committing to a slot design before a second
caller exists is premature.

**MISP-shaped fixture, not artboard-shaped.** Artboard-shaped data would be
faster to render but would mean rewriting every template when the data goes
live — the opposite of what a skeleton is for.

**Demo values rather than a display switch.** Verdict state is a property of
the value. Keying the fixture by value also exercises the lookup the real
implementation needs, and avoids a debug affordance to unwind.

**Two verdict layouts, not one per disposition.** The split is between signals
that agree and signals that do not, which is a difference in shape; MALICIOUS
and BENIGN differ only in colour, glyph and whether a warninglist band applies,
none of which needs a second template to keep in step.

**Fix `.ajax-card` rather than copy the workaround.** The declarative form is
already emitted by shared code and already broken; making it work removes
per-card `fetch()` boilerplate here and unblocks it for every future page.

**Sparse page for unknown values, not a 404.** For a value-centric page, "nobody
has reported this" is a legitimate and useful answer, not a missing resource.

**No defang toggle.** MISP refangs on ingestion, so there is no stored defanged
form for a toggle to switch to.

**Chart.js for curves, CSS for bars.** Consistent with the rest of MISP's
new UI, and gives tooltips and axes for free when the data becomes real.

---

## 5. Out of scope

- Any live model query. Every number on the page is fixture data.
- Any database write. All write controls are disabled.
- The seven stubbed tab bodies.
- Linking attribute values from the event view's attribute table — deferred
  until real data exists, since with two demo values almost every such link
  would land on the sparse state.
- A verdict scoring engine. The page displays a verdict; it does not compute one.
  The weighting-profile name and "not stored, not synchronised" note in the
  artboard are rendered as given.

---

## 6. Verification

The Docker dev stack serves this worktree directly (mounts repointed to
`/home/sami/git/MISP/.claude/worktrees/attribute-value-page-brief`), so
verification is a real page load, not a lint pass:

1. `parallel-lint` over the new PHP and `.ctp` files.
2. Load `/values/view/<b64 185.234.219.24>` and confirm the banner, all nine
   tabs, the seven stubs, and that every rail card resolves off its spinner.
3. Load the conflicted value and confirm it renders artboard C's layout.
4. Load the benign value and confirm the agreeing layout renders in green, with
   the warninglist band, and that its ledger rows sum to the score the hero and
   the rail's composition card both state.
5. Load an unknown value and confirm the sparse page.
6. Check the nine-tab bar for wrapping at common widths — `nav-tabs` at `fs-5`
   with nine items is a real risk and is reported, not silently restyled.
7. Toggle the dark theme and confirm no hardcoded light-only colour survives.

### 6.1 Outcome

All seven ran against the live stack. Two things are worth carrying forward.

**The nine-tab bar wraps.** Measured on the malicious value: one row at 1920px,
two rows at 1600px and every width below it down to 992px. The bar never
overflows horizontally and the page never scrolls sideways — it wraps cleanly,
and the second row is legible. It is reported here rather than restyled,
because the fix is a design decision (scroll, overflow menu, drop the counts,
or accept two rows) and not one this pass should make on its own.

**`--bs-secondary-color` is a text colour.** The UNKNOWN disposition used it as
a chip fill. It inverts between the themes, so in dark the hero badge became a
light grey block under the white label — about 1.4:1. Dispositions now resolve
through `--vp-unknown`, which is theme-stable and reaches 4.69:1 in both.
`ValueDisposition::isDefinite()` is still unused: the quiet treatment its
docblock describes for CONFLICTED and UNKNOWN was never wired to a style.

A caveat for whoever verifies the next phase. Panels are checked in headless
Chrome against saved fragments, and the page pulls its CSS from the instance
cross-origin — that fetch fails intermittently. An unstyled page passes a
colour check for the wrong reason, so the harness now asserts `--vp-mal`
resolves before it asserts anything else, and aborts when it does not.
