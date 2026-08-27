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

### 1.4 Where this stands

**Read this first.** §1.2 describes the original skeleton pass; the document has
since grown to twenty-one phases and this subsection is the current state.

Phases 1–21 were **fixture-first**: real routing, real templates, real ajax
endpoints, real interactions — all reading `ValueProfileFixture`. All nine tabs
render their own content; nothing on the page is a placeholder.

**Phase 22 started the live campaign.** The Occurrences tab now reads the
database; the other eight tabs still read the fixture, so the two regimes sit
side by side until the campaign finishes. **Nothing writes**, on either side.
`live/00-contract.md` §14.12 is the panel-level record — one of twenty-seven
endpoints has moved.

**Phase numbers and section numbers are not aligned.** Phase 10 is the Sightings
tab, written up in `value-profile-tabs/02-sightings.md`; §10 is phase 18. Always
follow the "Written up in" column rather than assuming §N is phase N.
In that column `tabs/` is `prd/value-profile-tabs/`, `phases/` is
`prd/value-profile-phases/` and `live/` is `prd/value-profile-live/`.

| Phase | What | Written up in | Status |
|---|---|---|---|
| 1–5 | Skeleton: routing and chrome, lazy loading, Overview, Verdict, states and interactions | §3 | built |
| 6 | The verification pass | §6 | ran; §6.1 has the outcome |
| 7 | Candidate decks for the five content tabs | §7 | done — 5 artifacts, 20 candidates, all picked |
| 8 | Shared groundwork for the tab phases | `tabs/00-shared.md` | built |
| 9 | Occurrences tab | `tabs/01-occurrences.md` | built |
| 10 | Sightings tab | `tabs/02-sightings.md` | built |
| 11 | Relationships tab | `tabs/03-relationships.md` | built |
| 12 | Enrichment tab | `tabs/04-enrichment.md` | built |
| 13 | Analyst data tab | `tabs/05-analyst.md` | built |
| 14 | Candidate decks for Timeline and History | §8 | done — 2 artifacts, both picked |
| 15 | Timeline tab | `tabs/06-timeline.md` | built |
| 16 | History tab | `tabs/07-history.md` | built |
| 17 | Bounding the object-siblings section | `phases/17-object-siblings.md` (§9) | **specification only** — built by phase 18 |
| 18 | Phase 17 built, plus `45.155.205.233` as a fourth demo value | `phases/18-siblings-built.md` (§10) | built |
| 19 | History at occurrence scale | `phases/19-history-scale.md` (§11) | built |
| 20 | One brush primitive, three callers | `phases/20-one-brush.md` (§12) | built |
| 21 | Zooming the activity chart | `phases/21-chart-zoom.md` (§13) | built |
| — | Going live: the wiring contract | `live/00-contract.md` (§14) | the contract; §14.12's board says which panels have moved |
| 22 | Occurrences goes live — the first live phase | `live/22-occurrences.md` | built |
| — | Analyst writes on a value | [`value-profile-writes.md`](value-profile-writes.md) | **design only — nothing built, no schema** |
| — | The verdict engine | [`value-profile-verdict-engine.md`](value-profile-verdict-engine.md) | **not designed — a scope note only.** Blocks the Verdict tab; needs its own PRD and grilling session |

Then the live campaign, one phase per tab. **This is the table to update as each
lands** — it is the only tab-level record, and `live/00-contract.md` §14.12
carries the panel-level board beneath it. The order is not fixed; whichever tab
goes first argues why in its own document.

| Phase | Converts | Document | Status |
|---|---|---|---|
| 22+ | Overview | — | not started — **partially blocked**, its verdict card needs the engine |
| — | Verdict | [`value-profile-verdict-engine.md`](value-profile-verdict-engine.md) | **BLOCKED** — no engine computes a verdict; needs its own PRD and grilling session first |
| **22** | **Occurrences** | [`live/22-occurrences.md`](value-profile-live/22-occurrences.md) | **built** — capped at 300 rows (§6); review added the resolved distribution chain and two working time ranges (§13), sortable columns and a reader-chosen page size (§14), then a brush on each time range (§15) |
| 22+ | Sightings | — | not started |
| 22+ | Relationships | — | not started |
| 22+ | Enrichment | — | not started — blocked on the persistence §7.9 found missing |
| 22+ | Analyst data | — | not started |
| 22+ | Timeline | — | not started |
| 22+ | History | — | not started |

#### What a fresh session must not break

- **Nothing writes.** Every control that would write renders visibly disabled.
- **Fixture-first, except where a live phase says otherwise.** Panels read
  `ValueProfileFixture` unless §14.12's board records them converted. §14 is the
  contract for changing that, and a change is a phase with a document.
- **Four demo values, all four states — on the fixture-backed panels.**
  `185.234.219.24` malicious, `104.21.34.198` conflicted, `8.8.8.8` benign,
  `45.155.205.233` the occurrence-scale case, and anything else the sparse
  unknown page. A tab that supplies only one of them is not done
  (`tabs/00-shared.md` §10). **A converted panel shows those values nothing**,
  because a real instance holds no such attributes and no seeder is built
  (§14.8) — so a live phase names the real values it verified against instead,
  and `live/22-occurrences.md` §10.1 is the pattern.
- **Additive changes to shared code only**, guarded, with existing callers
  re-verified — now a three-part test in §14.7.
- **Both themes**, and the §6.1 trap: assert `--vp-mal` resolves before
  asserting any colour, because an unstyled page passes a colour check for the
  wrong reason.

#### The open backlog

Nothing here is a defect; each is recorded where it belongs and listed once here
so it need not be reassembled from fourteen sections.

| Item | Where | Note |
|---|---|---|
| §14.6's nine required changes | §14.6 | **four applied by phase 22** (the Occurrences tab's ACL band, its facet-vs-banner sentence, its ACL-hidden state, its counts). Five remain, each owned by the phase that converts its panel |
| The page control cannot draw a large table | `live/22-occurrences.md` §12.1, §14.2 | one page button per page, inline: past ~20 the panel header collapses and overflows horizontally. Predates phase 22 — `45.155.205.233` shows it today. A larger page size buys room rather than fixing it, so it still bounds both the 300-row cap and which page sizes may be offered |
| The decay aggregation rule | §14.5, `tabs/02-sightings.md` §11/§16 | ten per-attribute curves into one per-value score. Undecided; owner is `ValueDecayTool` |
| Opinion colour contradiction | `tabs/05-analyst.md` §11/§15 | the Overview preview paints "Agree" green, the Verdict histogram paints >50 red. The Overview card is the one that should change |
| Markdown in notes | `tabs/05-analyst.md` §11 | stored, never rendered; no per-note flag, so enabling it is instance-wide |
| A `known`-category warninglist | §14.10 | §2.6's shared-infrastructure argument may have no shipped list behind it |
| Shared-code defects | §7.9, `live/22-occurrences.md` §13.3 | the `bg-light` bulk bar, the `border-dark` type badge, and `DistributionLevel`'s level-1 tint at 4.09:1 — reported and deliberately unfixed |
| First/last seen has no date filter | `live/22-occurrences.md` §13.2 | the Time ranges cut on instants; an interval needs an overlap test, which is one more matcher |
| Per-phase deferrals | each phase's Deferred subsection | §9.12, §10.6, §11.7, §12.7, §13.7 |

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

Phases added once the skeleton landed, written up where they belong:
**phase 6**, the verification pass, is §6; **phase 7**, candidate mockups for
the five stubbed content tabs, is §7. The picks from phase 7 become
**phases 8–13**, one document each in `prd/value-profile-tabs/` — shared
groundwork first, then one tab per phase. §7.8 has the table.

**Phase 14**, candidate mockups for the last two stubbed tabs — Timeline and
History — is §8, together with the feasibility pass those two needed first.
Its picks become **phases 15** and **16**. §8.7 has the table.

**Phase 17** is `phases/17-object-siblings.md` (§9), and is the first phase
that comes out of reviewing what shipped rather than out of a queue: the
Relationships tab's object-siblings section is unbounded, and no demo value
has enough occurrences for that to show. It shipped as a specification and
nothing else.


**Phase 18** is `phases/18-siblings-built.md` (§10): §9 built. The aggregated
siblings section, the nesting fix the shared list machinery needed to carry
two paged sections in one panel, and `45.155.205.233` — a fourth demo value
with 812 occurrences, over-correlating, so the section §9 bounds is the only
content left on its tab.


**Phase 19** is `phases/19-history-scale.md` (§11), and takes the finding
phase 18 measured rather than predicted: the History tab renders one section
per occurrence, so the fourth value renders 748 of them. It is the first phase
whose design came out of an interview rather than a candidate deck, and §11.2
records the ten decisions.


**Phase 20** is `phases/20-one-brush.md` (§12): collapsing the three brushable
activity charts into one, with the bucket unit as a parameter — the three
callers bucket by three different rules, so the parameter is what makes one
primitive possible rather than an extra. No new behaviour for the reader, and
blocked on phase 19, which writes the third of the three.


**Phase 21** is `phases/21-chart-zoom.md` (§13): zooming that chart, so a
month holding a thousand entries can be opened. Blocked on phase 20, and it
inherited one decision phase 19 made awkward — the drag gesture was already
spent on selecting a period, so zoom got its own: four buttons on the visible
span, with the bucket unit following from it. The measurement §13.1 asked for
came back saying bars are cheap enough to ship at every grain, so no zoom
level fetches. History and Sightings converted; the Timeline declines, because
twelve monthly bins is not a span that needs opening.


Every phase to here has been fixture-first. The contract for taking the nine
tabs live is `live/00-contract.md`, still numbered §14 — the model layer, the
seam the coming `attributes.value` table moves behind, the fetcher and
aggregation rules, viewer-scoped counts, and the UI reuse test. **It is not a
phase**: unlike `value-profile-tabs/00-shared.md`, which was phase 8 because it
built primitives, it builds nothing. Live phases inherit it and start at 22;
§14 deliberately does not sequence them, and §1.4 is where their progress is
recorded. Writes are a separate document,
[`value-profile-writes.md`](value-profile-writes.md).

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
  artboard are rendered as given. This is why the Verdict tab's live phase is
  **blocked** rather than merely unstarted: it has no data source to swap in,
  because the fixture there stands in for an algorithm rather than for a query.
  Scope is recorded in
  [`value-profile-verdict-engine.md`](value-profile-verdict-engine.md).

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

---

## 7. Phase 7 — candidate mockups for the five content tabs

### 7.1 What this phase produces

Two of the nine tabs were built from artboards. The other seven were stubbed,
and there are no artboards for them. Designing them directly in `.ctp` is the
expensive way to find out a direction is wrong: a rejected layout costs a day
of template work and a rewrite of the fixture shape underneath it.

Phase 7 designs them as standalone mockups instead. Five tabs, four candidate
designs each, published as five artifacts — one per tab, its four candidates on
one page. The picks come back as a decision table in §7.8, and every
implementation phase after this one starts from a chosen design rather than a
paragraph of prose.

No PHP is written in this phase.

### 7.2 Scope — five tabs, twenty candidates

| Registry # | Tab | Brief section | Candidates |
|---|---|---|---|
| 3 | Occurrences | §6.3 Tab 2 | `O1`–`O4` |
| 4 | Sightings | §6.3 Tab 3 | `S1`–`S4` |
| 5 | Relationships | §6.3 Tab 4 | `R1`–`R4` |
| 6 | Enrichment | §6.3 Tab 5 | `E1`–`E4` |
| 7 | Analyst data | §6.3 Tab 6 | `A1`–`A4` |

Brief sections are in `prd/attribute-value-page.md`.

**Timeline (#8) and History (#9) are excluded.** A merged chronology and an
audit log are the two most conventional surfaces on the page — the ones where
the design question is thinnest and the data question is thickest. They follow
the same mockup-then-implement path once these five have proven the loop.

Candidates are drawn against the malicious value, and must state how they
behave at the other three:

| Value | Disposition | Occurrences | Sightings | Relationships | Enrichment | Analyst |
|---|---|---|---|---|---|---|
| `185.234.219.24` | MALICIOUS | 10 | 47 | 31 | 9 | 6 |
| `104.21.34.198` | CONFLICTED | 9 | 63 | 1,847 | 4 | 7 |
| `8.8.8.8` | BENIGN | 9 | 17 | 21,904 | 3 | 5 |
| any other | UNKNOWN | 0 | 0 | 0 | 0 | 0 |

A design that only works at 31 rows is not a candidate. Neither is one with no
answer for zero.

### 7.3 Step 0 — the mockup kit

Built once, before any agent runs. It is the reason twenty mockups drawn by
five agents can be compared at all: without it each agent invents its own
approximation of MISP, and the comparison is between house styles rather than
between designs.

**`prd/phase7/kit/mockup-kit.css`** — a concatenation of exactly the
stylesheets the real page loads, in the order `Layouts/default.ctp` loads them:
`bootstrap5-custom.min.css`, `tom-select.bootstrap5.min.css`,
`mainOvermind.css`, `fontawesome7.min.css`, `misp-iconify.css`, then the
page's own `value-profile.css`. `print.css` is skipped — it loads at
`media="print"` and says nothing about the screen. 812KB with the fonts
embedded, inlined into each artifact and far inside the 16MB ceiling.
`prd/phase7/kit/build-kit.sh` builds it and stamps the source commit into a
header comment, so a stale kit is visible rather than silently wrong.

**Fonts and icons.** The artifact CSP blocks every external host, so the build
script embeds `webfonts-fa7/fa-solid-900.woff2` (112KB) and
`fa-regular-400.woff2` (18KB) as `data:` URIs. Font Awesome declares each face
three times — once for `Font Awesome 7 Free` and twice more as the
`Font Awesome 5 Free` and `FontAwesome` aliases — so embedding on every
occurrence would carry the same font three times over; only the live family is
embedded and the eight alias and brand faces are dropped. `misp-iconify.css`
already carries its glyphs as inline `data:` SVG masks, so MISP's own icon set
needs no work.

**A BOM will eat Bootstrap's variables.** `bootstrap5-custom.min.css` opens
with one. Harmless at the head of its own file; concatenated into the middle of
another it is an invalid token that takes the *following* rule with it — which
there is the whole `:root,[data-bs-theme=light]` block, every Bootstrap
variable the page has. The stylesheet still parses, `--bs-danger` and
`--bs-body-bg` just quietly resolve to nothing. The builder reads with
`utf-8-sig` and refuses to emit a kit containing a BOM.

**The theme bridge.** Artifacts stamp `data-theme` on the root element and
otherwise fall back to `prefers-color-scheme`; MISP switches on
`data-bs-theme`. The kit ships a short script that mirrors the artifact's
resolved theme onto `data-bs-theme` at load and on change, so a mockup follows
the reader's theme using MISP's own dark palette rather than a second one.

**`prd/phase7/kit/frame.html`** — the page around the tab body, lifted from a
live render rather than approximated: the banner with `185.234.219.24` and its
type chips, the disabled action buttons, the fact strip, the pivot rail and the
nine-tab bar. `prd/phase7/kit/build-frame.py` regenerates it from a page dump
when the chrome changes. Each frame carries `data-vp-tab`, and the deck script
activates that tab in the bar. The chrome renders at reduced emphasis — it is
context for judging the tab body, not part of what is being judged — except the
tab bar, which is what tells the reader which tab they are looking at. The bar
wraps to two rows at 1600px and that is left exactly as §6.1 records it.

**The pinned width.** Not the body width: the page is `container-fluid`, so the
content column is a share of the window rather than a fixed size — `col-lg-9`
measures 1080px at a 1440px window, 1200px at 1600px and 1440px at 1920px.
Pinning the *page* at 1600px and keeping MISP's real grid inside it gives every
candidate the geometry it would really have, and lets one that wants the full
width take `col-12` (1576px) instead of the 9/3 split without leaving the
frame. Candidates are drawn at that pin, so density is honest and none of them
wins by being drawn narrower than the column it would live in.

**Publishing.** A source file under `prd/phase7/mockups/` keeps a
`<!-- vp-kit -->` marker and stays readable in a diff;
`prd/phase7/kit/inline-kit.py` swaps the marker for the kit and writes the
publishable copy to `prd/phase7/build/`, which is not committed — five
committed copies of the same 812KB is not a diff anyone can read.

**Step 0's own check.** `prd/phase7/kit/check-mockup.sh` renders a built file
in headless Chrome in both themes and asserts that `--vp-mal` resolves before
it asserts anything else, then that there are nine tabs with exactly one
active, four candidates with real body height, no off-host reference, and no
page-level horizontal scroll. The first assertion is the one that matters: a
mockup whose CSS failed to apply still renders, as unstyled HTML, and unstyled
HTML passes a colour check for the wrong reason — the trap that made a whole
sweep vacuous in §6.1.

### 7.4 What a candidate is

**A layout and an information architecture, not a colour scheme.** Four
candidates for a tab must differ in what is on screen and where it sits. Four
takes on one arrangement is one candidate and three variants, and the phase has
bought nothing.

**MISP-styled.** Real classes from the kit — `card`, `nav`, `badge`, `table`,
`form-control`, `misp-icon`, and the `vp-*` primitives the page already owns. A
candidate invents a class only where MISP has no equivalent, which is the rule
§1.3 already sets for the page itself.

**Placeholder content, not fixture data.** Structure is real: panel headers,
column headers, control labels, filter names, empty-state copy, and the counts
from §7.2. Row content is skeleton blocks at varying widths — except where the
*kind* of thing is the design point, which is where density comes from:
distribution badges, `to_ids` pills, tag chips, org names, dates and typed
relationship labels render as real components carrying generic text (`Org A`,
`tlp:amber`, `2025-03-14`). Nothing invents intelligence data — no fabricated
IPs, hashes, actor names or CVEs beyond the four demo values this page already
uses. A mockup that reads as a real threat report is a mockup that will
eventually be screenshotted as one.

**Charts are static SVG or CSS.** Chart.js is not in the kit. What is being
judged is the shape and placement of a histogram or a decay curve, not its
rendering library.

**Interactivity is demonstrated, not implemented.** Controls are inert. Where a
candidate's whole argument *is* an interaction — a brush, a split-pane
selection — it shows one worked example, and never in a way that makes the
mockup unjudgeable without clicking.

**Every candidate carries its own reckoning**, in a card above it:

- a one-sentence thesis;
- what it optimises for, and what it gives up to do that;
- how it behaves at the stress counts in §7.2, and at zero;
- which MISP factory, model or endpoint would supply each region — and
  anything the brief asks for that MISP cannot supply today;
- a size estimate (S/M/L) for implementing it as one set of ajax panels.

English only; `__()` comes with the implementation. The honesty rule from §1.3
carries over: a control that would write renders visibly disabled, and "not
implemented", "nothing to show" and "hidden by ACL" stay three different
things.

### 7.5 Per-tab briefs

Each subsection names what the tab must cover, the tension its design has to
resolve, and four starting directions. An agent may beat a direction with a
better one — it just has to say what it replaced and why.

#### 7.5.1 Occurrences — `O1`–`O4`

**Must cover.** One row per occurrence: event id and info, creating org, type,
category, `to_ids`, distribution with sharing-group name, object context,
comment, first/last seen, tags. Filters for org, type, category, `to_ids`,
distribution, sharing group, tag, date range and include-deleted. Multi-select
with a bulk bar — tag, set `to_ids`, set distribution, propose edit, add
sighting, add to collection, export selection. A pending-proposal indicator on
rows that carry a shadow attribute. Soft-deleted rows struck through behind a
toggle. The ACL truncation note.

**The tension.** The Overview tab already shows a ten-row preview of this
table, so a full-width copy of it is not a tab. What this one adds is
filtering, selection and the columns the preview drops — and it has to make
that addition visible on arrival.

**Directions.** Dense single table with a filter bar above and a sticky bulk
bar · faceted, with a filter rail carrying counts beside the table · grouped
into collapsible sections by event, org or object with per-group actions ·
split-pane, list on the left and the selected occurrence's full detail on the
right.

#### 7.5.2 Sightings — `S1`–`S4`

**Must cover.** A time histogram stacked by organisation, with a type toggle
(sighting / false positive / expiration) and a brush-selectable range. The
decay-score curve, one per decaying model, over the same axis. A table of
individual sightings: org, source string, date, type. A disabled, value-scoped
"Add sighting".

**The tension.** The overlay is the whole point: MISP computes the decay curve
and has never had a place to show it against the sightings that move it. A
candidate that puts the curve in its own separate card has lost the argument
before it starts.

**Directions.** Chart above, table below, one shared brush · chart-first and
full-bleed with the table as a linked drawer · small multiples, one lane per
reporting org over a shared axis · chronology-first, the sighting stream
vertical with the decay curve as a rail beside it.

#### 7.5.3 Relationships — `R1`–`R4`

**Must cover.** Three notions, kept visibly apart: co-occurrence including
object siblings, near-matches with their similarity scores (CIDR containment,
ssdeep, domain/TLD tree), and asserted analyst relationships. Plus a graph view
centred on the value.

**The tension.** Conflating the three is the one way this tab fails outright —
a machine-derived correlation and a human claim must never look alike. And the
counts are brutal: 8.8.8.8 carries 21,904 relationships, so "list them" is not
an answer. The design must degrade into ranking, grouping or thresholds, and
say so on its face rather than in a scrollbar.

**Directions.** Three stacked sections, each with its own affordances ·
graph-first with the three notions as toggleable layers · a segmented control
swapping one notion at a time into a full-width pane · one ranked list with a
provenance column and notion filters.

#### 7.5.4 Enrichment — `E1`–`E4`

**Must cover.** A module picker — one card per enabled module valid for this
type, with last-run timestamp, staleness indicator, select-all and
run-selected. Per-module progress where one timeout does not read as total
failure. Results as structured cards of returned MISP attributes and objects,
each element with its own add-to-event / add-as-new-event / dismiss. A
since-last-run delta. An explicit "nothing queried yet" state, and no auto-run
on load.

**The tension.** Running a module costs money and quota, and querying an
adversary's infrastructure announces your interest — so "not yet run" is a
first-class state to design, not an empty one to apologise for. Write-back is
per returned element, never per module.

**Directions.** Picker grid then a results feed · two-pane, module rail
carrying state on the left and results on the right · a job-queue framing where
runs are rows with progress and a history · results-first, last run as the
landing state with the picker in a drawer.

#### 7.5.5 Analyst data — `A1`–`A4`

**Must cover.** The opinion aggregate — mean, 0–100 distribution histogram,
per-organisation breakdown — above the individual opinions and their comments.
Markdown-rendered notes, threaded two levels deep, with author org,
distribution and timestamp. An inline composer for a note or an opinion,
disabled.

**The tension.** Disagreement between organisations is the signal, and a mean
hides it. The Verdict tab already makes this argument for the conflicted value
with a bimodal histogram and a note that its mean is meaningless (§2.6); this
tab must not undo it by leading with an average.

**Directions.** Aggregate header over a threaded feed · two columns, analytics
rail beside the thread · grouped by organisation, each org's stance as a block ·
one interleaved activity thread with the aggregate as a sticky summary.

### 7.6 The artifact

One per tab, holding all four of that tab's candidates. Nothing lives outside
it.

- **Title** a short noun phrase (`Occurrences Candidates`), a one-sentence
  `description`, and one emoji `favicon` kept stable across redeploys.
- **The landing view is the comparison.** All four scaled to fit the viewport
  in a single row — `transform: scale()` with `transform-origin: top left` — so
  the structural differences read at a glance. A toggle switches to the stacked
  view, each candidate at the pinned width from §7.3, which is where density
  and legibility are actually judged.
- **Each candidate is anchored and preceded by its reckoning card** (§7.4), so
  a decision can cite `O2` and land on it.
- **The page closes with a comparison table** across the four and the agent's
  own recommendation, with its reason. The agent that drew them has an opinion;
  hiding it throws away the most informed read in the room.
- **Both themes must render.** The kit supplies MISP's palette; a candidate
  that hardcodes a light-only colour is a defect, the same one §6's check 7
  found.
- **Source is committed** at `prd/phase7/mockups/<tab>.html` and published from
  there, so a revision edits the file and republishes the same URL.

Artifacts are private on publish. The URLs are the user's to share or not.

### 7.7 The fan-out

Five subagents, one per tab, launched in a single message so they run
concurrently.

**One agent per tab, owning all four candidates.** Divergence is the hard part.
An agent drawing all four can spread them deliberately across the axis; four
independent agents each converge on the obvious table-with-filters, and the
spread has to be manufactured afterwards by whoever reviews them.

Each agent receives: this section; §1.3, §2.4, §2.5 and §2.6 of this PRD; its
tab's subsection of the brief; the kit path and how to inline it; the counts
table; and `value_occurrences.ctp`, `value_verdict_ledger.ctp` and
`value_panel_header.ctp` as the tone to match. It loads the `artifact-design`
skill before writing anything.

Each agent returns: the artifact URL, the committed file path, its four
candidate ids and theses, its recommendation, and every place the brief asks
for something MISP cannot supply — that last one is a finding to record here,
not something to design around quietly.

**Agents write only under `prd/phase7/`.** Nothing under `app/` is touched in
this phase, `value-profile.css` included.

### 7.8 Review and selection

Each artifact is reviewed before it reaches the user: kit compliance, genuine
divergence, honest feasibility notes, both themes, the stress counts addressed.
A tab that comes back with four restyles goes back to its agent, named with the
axis it failed to spread on.

All five passed. Twenty candidates, drawn 2026-08-25:

| Tab | Artifact | Candidates | **Chosen** | Grafts | Spec |
|---|---|---|---|---|---|
| Occurrences | [b9ab9ec9](https://claude.ai/code/artifact/b9ab9ec9-e3cf-42e4-86b7-d53242c9447f) | `O1`–`O4` | **`O2`** | none | [phase 9](value-profile-tabs/01-occurrences.md) |
| Sightings | [715b4822](https://claude.ai/code/artifact/715b4822-2644-409e-beb2-3ac09474c4d2) | `S1`–`S4` | **`S1`** | none | [phase 10](value-profile-tabs/02-sightings.md) |
| Relationships | [0eaa5580](https://claude.ai/code/artifact/0eaa5580-c273-451a-b7ba-6444dd58296e) | `R1`–`R4` | **`R1`** | `R3`'s faceted pane | [phase 11](value-profile-tabs/03-relationships.md) |
| Enrichment | [ee197bd7](https://claude.ai/code/artifact/ee197bd7-e9ec-46f3-9b51-c3797236a4ee) | `E1`–`E4` | **`E2`** | none | [phase 12](value-profile-tabs/04-enrichment.md) |
| Analyst data | [09f056ee](https://claude.ai/code/artifact/09f056ee-be74-4560-90e1-b14cda8f832c) | `A1`–`A4` | **`A1`** | none | [phase 13](value-profile-tabs/05-analyst.md) |

Each deck names the axis its four candidates spread along, so a pick is a
choice between organising units rather than between skins: the row, the facet,
the event or the occurrence (Occurrences); what the tab is besides the chart
(Sightings); the notion, the graph, the pane or the row (Relationships); the
spend decision, the module, the run or the returned element (Enrichment); time,
analytics, organisation or position (Analyst data).

Decided 2026-08-25. Four picks were taken bare and one with a graft:
Relationships takes `R1`'s three-ledger structure with `R3`'s faceted pane on
its co-occurrence section, for faster narrowing and an exact count per
dimension. The grafts each deck recommended for the other four — `O1`'s
bottom-docked bulk bar, `O4`'s proposal diff, `S4`'s gap rows, `S3`'s baseline
split, `E1`'s staging tray, `A2`'s element reuse — are recorded as deferred in
the sub-PRD that owns them, so the first implementation of each tab is the
design that was actually reviewed.

Each pick is specified as its own phase in `prd/value-profile-tabs/`, preceded
by **phase 8**, [shared groundwork](value-profile-tabs/00-shared.md): the
registry mechanics, an inventory of the 257 `vp-*` primitives that already
exist, the one new shared primitive (the counted facet control), the
static-SVG-to-Chart.js translation, a fourth honest state, and two shared-code
dark-theme fixes. Those five phases are **fixture-first** — real templates and
real ajax endpoints against an extended `ValueProfileFixture`, nothing writes,
no model queries — which is the pattern phases 3–5 used and the one §7.11
anticipates. §7.9 below is the list of what a later live phase has to solve.

### 7.9 What the mockups found MISP cannot supply

Each agent was asked to name what its tab's brief assumes and MISP does not
have. These are the phase's second deliverable, and several of them decide
what a phase 8 can honestly promise.

**Sightings.** The decay curve is computed **per attribute, not per value** —
`DecayingModel::getScoreOvertime()` takes one attribute id and derives its base
score from that attribute's own type and numerically-tagged taxonomies, so ten
occurrences can carry ten curves per model. There is no value-scoped endpoint
and no aggregation rule; the deck proposes *max across occurrences, labelled
with its occurrence*, and someone has to decide it before this tab is real. The
curve's axis is not the histogram's either: hourly samples from the attribute's
first timestamp to *last sighting + lifetime*, so it starts arbitrarily and
runs into the future. False positives and expirations move the score by
nothing (`$sightingsType = 0`), which the chart states rather than hides. And
the count in the tab title is the viewer's: `Sightings_policy` hides whole
sightings, `Sightings_anonymise` files foreign orgs as *Others*. The org-stacked
histogram, by contrast, needs no new query — `attributesStatistics()` already
groups org × attribute × type × date in SQL.

**Relationships.** Domain/TLD-tree relations, which the brief asks for, **do
not exist in MISP at all** — no public-suffix list, no tree, no code path — so
the candidates render a distinct "no engine" state rather than an empty one.
Correlations carry no provenance: exact matches, CIDR containment and ssdeep
rows are written into the same table by
`Correlation::__addAdvancedCorrelations()` with nothing to tell them apart, so
splitting co-occurrence from near-match means re-deriving per render or adding
a column. The ssdeep score is computed
and then thrown away — `ssdeepCorrelation()` only tests it against
`MISP.ssdeep_correlation_threshold`. And **21,904 correlations cannot exist**:
`MISP.correlation_limit` defaults to 20 (`OnDemandCorrelationBehavior:185`,
`OverCorrelatingValue:86`, `Event:5553`), past which the value is recorded as
over-correlating and *no* correlations are stored — which the fixture already
reflects with `over_correlating => true` on both large values. The honest render
of `8.8.8.8` is a suppressed state, a fourth alongside empty, hidden-by-ACL and
not-implemented.

**Enrichment.** Nothing anywhere records that a module ran: `Module` is
`useTable = false`, so every staleness chip and the entire since-last-run delta
needs new persistence. A dismissal is not remembered either — the per-element
checkboxes are DOM state in one modal, so a re-run re-proposes everything the
analyst rejected. Module introspection carries no cost, quota or
leaves-the-building metadata, and there is no progress inside a module: one
`POST /query` under `Plugin.Enrichment_timeout`, so progress can only ever be
*n of m modules*. "Add to event" has no target when the value sits in seven of
them. Also `Event::enrichmentRouter()` returns before its own
`MISP.background_jobs` branch (`Event.php:7995`), so the interactive path is
synchronous whatever the setting says.

**Analyst data.** Notes and opinions hang off `object_uuid` + `object_type`,
and **a value is not a valid target** — the tab is a controller-assembled union
over the value's occurrences and events, with no single query and no pagination
across it, and the composer needs an explicit "attach to" picker. Nothing
computes the aggregate today; the Verdict tab's histogram is fixture. Markdown
is stored and never rendered (`Analyst_data/thread.ctp` prints `pre-line`), and
there is no per-note markup flag to turn it on selectively. Depth 2 is a fetch
limit, so `_max_depth_reached` has to be rendered or threads truncate in
silence. And MISP currently colours an opinion two contradictory ways — the
Overview preview paints "Agree" green while the Verdict histogram paints
everything above 50 red; the mockups unify on the Verdict reading and the
contradiction needs a decision.

**Occurrences.** No endpoint returns per-facet counts over an ACL-scoped
attribute set — tallying the fetched page works at ten rows and stops being
honest the moment the table paginates. A cross-event bulk write does not exist
either: every bulk action fans out per attribute, and one selection can mix
rows the user may edit with rows they may only propose against. The event ids
behind ACL-hidden occurrences are not obtainable, so a "ghost group" can name a
number and never a row. And no demo value carries a pending proposal, so the
indicator every candidate must show has nothing to render until the fixture
grows one.

**Shared-code defects surfaced along the way**, all outside this phase's remit
and none of them touched:

- `IndexTable/multi_select_toolbar.ctp:18` paints the bulk bar `bg-light`.
  Bootstrap does not redefine `--bs-light-rgb` for `[data-bs-theme=dark]` while
  it does redefine `--bs-body-color`, so in dark the real bulk bar is near-white
  text on a near-white bar — on every index table that uses it, not only this
  page.
- `genericElementsBS5/Badges/type.ctp:12` uses `border border-dark`, which all
  but vanishes against the dark ground for the same reason.
- `Event::enrichmentRouter()` has unreachable code after its `return`
  (`Event.php:7995`).

### 7.10 Exit criteria

Five artifacts published, twenty candidates, each rendering at the pinned width
in both themes with its reckoning card, and every row of the decision table
carrying either a pick or an explicit re-run.

### 7.11 What phase 7 leaves to phase 8

No PHP, no CSS under `app/`, no fixture changes, no live data, no writes — the
whole phase lives under `prd/phase7/`.

Each chosen candidate then becomes its own implementation phase, in the shape
phases 3 to 5 established: one ajax endpoint per panel on `ValuesController`,
elements under `Elements/Values/View/`, the fixture extended with that tab's
shape, `index_table` and the existing field renderers wherever they fit, and
the panel's empty state built alongside the populated one rather than
discovered later. Timeline and History join the queue once this loop has run
once.

---

## 8. Phase 14 — Timeline and History

The loop §7.11 describes has now run once: phase 8 laid the shared groundwork
and phases 9–13 shipped the five content tabs. The two tabs still rendering
`value_placeholder` are the ones §7.11 left in the queue, and this section is
their brief.

### 8.1 What this phase covers

Tabs 8 and 9 of the registry in §2.5. Their placeholder notes in `view.ctp`
state the intent as it was written in phase 1:

- **Timeline** — "One merged chronology: publications, first and last seen,
  sightings, tags, opinions, feed appearances and edits."
- **History** — "The audit log across every occurrence, with actor and
  organisation."

Both notes were written before anyone checked what MISP records. §8.2 is that
check, and it does not leave the Timeline note standing.

### 8.2 What MISP cannot supply for these two tabs

This is the §7.9 pass for the last two tabs, run before the design rather than
alongside it, because for these two the answer changes what the tab can be.

#### Nothing is recorded at all on a default instance

`MISP.log_new_audit` defaults to **`false`** (`Server.php:6649`), and
`AuditLogBehavior::isEnabled()` reads exactly that setting
(`AuditLogBehavior.php:296`). On a default instance `audit_logs` is empty —
not sparse, empty. So:

- The **History tab has no rows whatsoever**, and its own tab count is `0` for
  a reason that has nothing to do with the value.
- The Timeline's **edits** lane collapses to one point per occurrence
  (`attributes.timestamp`, which is the latest edit and not a history).

Both tabs therefore need a distinct **not-recorded** state, and it is not the
empty state. Empty means nothing happened to this value; this means nobody was
writing it down. Conflating them tells the analyst the value is quiet when the
truth is that the instance is deaf. That is a fifth honest state alongside
phase 8 §8's four — empty, hidden-by-ACL, not-implemented, suppressed.

#### Tags have no timestamp anywhere

`attribute_tags` is `(id, attribute_id, event_id, tag_id, local,
relationship_type)` and `event_tags` is `(id, event_id, tag_id, local,
relationship_type)`. Neither carries `created` or `modified`. A tag's date
exists *only* as an `audit_logs` row (`ACTION_TAG`, `ACTION_TAG_LOCAL`,
`ACTION_REMOVE_TAG`, `ACTION_REMOVE_TAG_LOCAL`), so it inherits the gate above.
Galaxy clusters are the same — `ACTION_GALAXY` and friends, no column.

On a default instance the Timeline can say **which** tags the value carries and
never **when** any of them was applied. The tab's own note promises tags in a
chronology, and that promise cannot be kept.

#### Feed appearances cannot be dated at all, on any instance

The feed cache is a Redis set of md5s per feed — `misp:feed_cache:<feedId>`,
written by `sAddArray`/`sAdd` (`Feed.php:1605-1611`) — with no per-member time.
The only timestamp is `misp:feed_cache_timestamp:<feedId>`, one integer per
feed, `set` to `time()` on every re-cache (`Feed.php:1573`).

So every value in a feed shares one date, that date means "when we last
fetched", and it moves each time the feed refreshes. A feed appearance can
honestly be drawn as **as of the last cache** and never as **appeared on**.
This one is not gated by a setting: no configuration makes it available.

#### Publications are two points per event, not a history

`events.publish_timestamp` is the most recent publication and
`events.first_publication` is the first; nothing records the ones in between.
Both are already in `Event::fetchEvent`'s field list (`Event.php:3187`), so
they need no new query. A value in seven events contributes at most fourteen
publication marks, and an event published three times still contributes two of
them. A full publication history exists only as `ACTION_PUBLISH` audit rows,
which is gated.

#### First and last seen are per occurrence, and nullable

`attributes.first_seen` / `last_seen`. Ten occurrences can carry ten different
spans for one value, and any of them can be null. These are also a different
kind of thing from the rest of the list: a claim about when the value was
*active*, not a record of something that *happened on the page's own
chronology*. A merged stream has to decide whether they are points, spans, or a
lane of their own — and it cannot merge ten spans into one without inventing an
aggregation rule, the same problem §7.9 found for the decay curve.

#### Sightings are the one source that fully works

`sightings.date_sighting` (epoch), with `type` ∈ `{0 sighting, 1
false-positive, 2 expiration}` (`Sighting::TYPE`, `Sighting.php:60`), `org_id`
and `source`. Per value, per event, properly dated. It carries the caveat
§7.9 already recorded: `Sightings_policy` can hide whole sightings and
`Sightings_anonymise` files foreign orgs as *Others*, so the chronology is the
viewer's rather than the instance's.

#### Notes and opinions are dated but not addressable by value

`notes` and `opinions` both carry `created` and `modified`. But §7.9 already
established that a value is not a valid `object_uuid` target, so the Timeline
inherits phase 13's controller-assembled union over the value's occurrences
and events rather than getting a query of its own.

#### The scoreboard

Of the seven sources the Timeline's note promises: **one** is fully dated and
addressable by value (sightings), **one** is dated but needs an assembled
union (analyst data), **two** are truncated to first-and-last (publications,
edits), **one** is a nullable per-occurrence span needing an aggregation rule
(first/last seen), and **two** have no usable timestamp at all (tags, feed
appearances) — one of those two gated by a setting, the other unavailable on
any instance. The note in `view.ctp` should be rewritten as part of this phase
rather than left describing a tab nobody can build.

#### A plain user sees only their own history

`AuditLogsController::__applyAuditAcl` (`:356`) restricts a non-admin to
`AuditLog.user_id = $user['id']` and an org admin to `AuditLog.org_id =
$user['org_id']`; only a site admin sees everything. The per-event index
escapes that with a different path entirely: `__createEventIndexConditions`
(`:488`) returns every row for an event when the viewer is a site admin or sits
in the event's creating org, and otherwise runs a full `fetchEvent()` to
enumerate the attribute, object, proposal and object-reference ids the viewer
may see, then restricts to those.

A value-scoped history has to pick one of the two models, and only the
per-event one shows a plain analyst anything at all. Note the shape of the
trap: verified as a site admin, either model looks identical.

#### The per-event ACL model costs a `fetchEvent()` per event

A value in seven events, viewed by someone outside all seven creating orgs, is
seven `fetchEvent()` calls before a single audit row is read — just to build
the `WHERE` clause. There is no value-scoped equivalent and no cheaper path.

Scoping instead by `model = 'Attribute' AND model_id IN (<occurrence ids>)` is
one query and needs no `fetchEvent()` at all, but it drops every event-level
action — publications, event tags, the event's own edits — which is a real part
of the value's story. The choice between them is a design decision this phase
has to make explicitly, not a detail to settle in the controller.

#### `model_title` carries the value, and it carries the *new* one

The behaviour's Attribute closure builds `"$category/$type $value"` preferring
`$new` over `$old` (`AuditLogBehavior.php:65-72`). So an edit that rewrote
`1.2.3.4` into `185.234.219.24` is filed under the new value. Two consequences:

1. **Scope by id, never by title.** Matching history on `model_title` would
   hand this value someone else's deletion and lose its own — and `model_title`
   is an unindexed `text` column, so the query is a scan as well as wrong.
2. **A correct row can name a different value.** The occurrence that *became*
   this value has audit rows describing what it was before. Showing them is
   right; showing them without saying so reads as a bug.

### 8.3 What already exists to reuse

`Themed/Overmind/Elements/Logs/timeline.ctp` is a day-grouped,
action-coloured timeline card with a documented entry contract — `created`,
`action`, `action_label`, `title`, `model`, `model_link`, `user`, `user_link`,
`org`, `request_badge`, `change_html` — and an action map covering all sixteen
`AuditLog::ACTION_*` constants plus the application-log actions. It is already
called by `Overmind/AuditLogs/admin_index.ctp:168` and
`Overmind/Logs/index.ctp:92`.

That is the History tab's body and very likely the Timeline tab's spine, and it
means **neither tab needs a new rendering primitive** — the phase 8 §4 posture,
applied to the only two tabs that get it for free.

One caveat, to be measured rather than assumed: the element's `$meta` colours
are hardcoded hex with light pastel fills (`#d1e7dd`, `#cfe2ff`, …) and its
header chip is `background:#e0e7ff` with `#4f46e5` on it. Those are
theme-blind. If they need `vp-*` tokens, that is a change to a shared element
with two existing callers, and it belongs in this phase as a stated decision
rather than a drive-by.

Also reusable: `Elements/AuditLog/change.ctp` for a row's diff and
`AuditLogsController::fullChange` for fetching one on demand —
`audit_logs.change` is a brotli-compressed blob above 256 bytes and capped at
64KB (`AuditLog::BROTLI_HEADER`, `COMPRESS_MIN_LENGTH`, `CHANGE_MAX_SIZE`), so
decompressing inline is the wrong instinct. And phase 8 §5's counted facet
control, for filtering History by action, model, org and actor.

### 8.4 Timeline — the brief

**Must cover.** One merged, reverse-chronological stream of everything the
value has that is genuinely dated: sightings by type and org, publications
(first and last per event), notes and opinions, edits where the audit log has
them. Per-source filtering. First/last seen, under whatever treatment the
design settles on. And an explicit, visible account of the undated sources
rather than their silent omission.

**The tension.** Five of the seven sources the tab promises are truncated,
unaddressable, or undated. The design problem is therefore not "how to draw a
chronology" — it is **how to draw a chronology that admits its own holes**. A
candidate that quietly drops tags and feed appearances looks complete and lies
about the value; one that renders every gap inline is honest and unreadable.
That trade is the axis.

**Directions.** A single stream with the undated facts in a footer band that
says why they are there · a lane per source, with the undated lanes rendered as
static chip rows against the same axis · a density spine (calendar or
histogram) with the stream beside it, so gaps in the record are visible as gaps
· a two-track split — "what happened", dated, above "what is true but undated",
below.

### 8.5 History — the brief

**Must cover.** One row per audit entry across the value's occurrences and, if
the event-scoped model wins, their events: actor, organisation, action, model,
title, and an expandable diff. Filters on action, model, org, actor and date
range. The not-recorded state from §8.2. A note on whose rows the viewer is
actually seeing, since §8.2 shows that differs by role.

**The tension.** This is the one tab whose content is *literally* an existing
MISP view, rendered by an element that already exists. So its design question
is only what a value-scoped history adds over seven per-event ones — and that
addition has to be visible on arrival, the same test §7.5.1 set for
Occurrences. The candidate that is a per-event audit log with a different
`WHERE` clause has not earned a tab.

**Directions.** The shared timeline element, value-scoped, with a facet rail ·
grouped by occurrence, so the value's own edit history separates from its
events' · grouped by actor and org, answering "who has been touching this" ·
diff-first, one changed field per row rather than one audit entry per row.

### 8.6 How the phase runs

Phases 9–13 were each preceded by a candidate deck (§7), and these two tabs get
the same treatment: a deck of four candidates each, reviewed and picked, then a
sub-PRD each, then implementation as **phases 15** (Timeline) and **16**
(History). Decided 2026-08-25.

The argument against decks was real and was rejected: §8.3 hands both tabs an
existing rendering primitive, which is most of what the other five decks were
choosing between. But §8.4's axis — how much of the record's absence to show —
is a design choice that primitive does not make, and it is the choice this
page's whole provenance argument rests on.

Both decks are fixture-first, in the shape phases 9–13 used: no live query, no
write, and every number consistent with what the rest of the page already
claims about the demo value.

Whatever is picked, §8.2 stands and the Timeline's placeholder note in
`view.ctp` gets rewritten — it currently promises a chronology of seven sources
where two of them have no timestamp in MISP at all.

### 8.7 The decks

Built on phase 7's kit unchanged — `prd/phase7/kit/frame.html`, MISP's own
stylesheets, the real page chrome, the pinned 1600px geometry, both themes
through the kit's theme bridge. Sources are `prd/phase7/mockups/timeline.html`
and `.../history.html`; `inline-kit.py` produces the published copies and
`check-mockup.sh` passes on both in light and dark.

The frame and its script needed nothing: `#tab-timeline` and `#tab-history`
already exist in the chrome's nine-tab bar, so `data-vp-tab` activates them as
they stand.

`check-mockup.sh` needed one correction, found by the fifth candidate. It
asserted a fixed pixel floor on `.vp-cand-view`, which is the scaler times
`--vp-scale` — and `fit` divides the lane by the candidate count, so the same
candidate measures smaller in a deck of five than in a deck of four. `T2`
started failing for the size of the deck it was in rather than for anything
about `T2`. The assertion now measures the scaler's own unscaled height, which
is what it was always about: whether the candidate has a body. All five earlier
decks pass unchanged under it, and the candidate-count check now accepts four or
more, since a deck may legitimately carry a composite drawn after review.

| Tab | Artifact | Candidates | Axis | **Chosen** | Spec |
|---|---|---|---|---|---|
| Timeline | [7c2bbad2](https://claude.ai/code/artifact/7c2bbad2-fcf2-49c8-8bf3-b60f122cadbb) | `T1`–`T4`, `T-final` | How much of the record's absence the reader has to look at | **`T-final`** | [phase 15](value-profile-tabs/06-timeline.md) |
| History | [41280107](https://claude.ai/code/artifact/41280107-5222-435c-8d48-5b51b8acd1d3) | `H1`–`H4` | The organising unit: the stream, the occurrence, the actor, the field | **`H2`** + `H1`'s facet rail | [phase 16](value-profile-tabs/07-history.md) |

Decided 2026-08-26. The Timeline pick is not one of the four: the review asked
for the composite instead, and `T-final` was drawn and added to the deck as a
fifth frame so the four it is assembled from stay readable beside it.

**Timeline.** T1 is one uninterrupted stream with the undated facts as
explained rail cards; T2 gives all seven promised sources a lane and hatches
the three that have no record, at full size; T3 makes a twelve-month density
spine the tab and puts the undated on one strip beneath it; T4 abandons the
timeline shape for a ledger where how well a fact is dated is a column.
Chosen: **`T-final`**, the composite. Each of the four solves one third of the
tab and loses the other two — T3 knows *when* and cannot say *from whom*, T2
knows *from whom* and shows no entry, T1 lists the entries with no way to find a
period, T4 is honest about every fact and has no shape. `T-final` stacks all
four against **one** selection: the spine sets a window, the seven source lanes
report what that window holds, the chronology lists it, and every row carries
its own precision chip. Nothing is duplicated because each level answers a
different question about the same window.

It also settles §8.2 in three places rather than one, which is the point of
assembling it this way. The two sources MISP can never date keep a full-size
hatched lane **and** a chip on the off-axis strip, so neither a reader who scans
the chart nor one who scans the lanes can miss them; the truncated edit lane
carries its own hatch naming the setting that would fill it; and "latest only"
sits on the row it qualifies rather than in a caption three panels away. T3's
own objection — that a density spine cannot tell a quiet month from an
unrecorded one — is answered rather than ignored, because the spine no longer
stands alone and the lanes beneath it say which sources could have recorded the
months it draws.

The cost is real and recorded: it is the tallest of the five and the most
expensive to build — a lane primitive, a brush driving two panels, and three
aggregates over one set where T1 needs one list and an existing element. If
phase 15 has to be small, `T1` with `T4`'s precision chip is the fallback and
remains a defensible tab.

**History.** H1 is the shared audit-timeline element value-scoped with a
counted facet rail; H2 groups by occurrence with a per-occurrence action mix;
H3 groups by organisation and actor; H4 unpacks each entry into one row per
changed field. Chosen: **`H2`** with `H1`'s facet rail, because §8.5's test is what a
value-scoped history adds over the seven per-event ones an analyst already has,
and grouping by occurrence is that addition — it can say 4831022 has nine
entries and 4828810 has two, which no per-event log can. H1 is the right answer
to "ship it this week" and the wrong one to that test. H3 asks the best question
and cannot answer it for most readers: `__applyAuditAcl` collapses three of its
four cards to *unnamed users* for anyone who is not a site admin. H4 is
rejected on cost — it needs `audit_logs.change` decoded for every row at render
time, which is what `fullChange` exists to avoid.

One thing the pick costs that §8.3 above assumed it would not: the shared
`Logs/timeline.ctp` groups by calendar day, so `H2` does **not** get it as a
free body — only its action vocabulary, extracted into a new
`AuditActionMeta`. [Phase 16](value-profile-tabs/07-history.md) §2 records that
and why the shared element is left alone in a fixture-first phase.

Two grafts are recommended into whichever History candidate wins, because both
are §8.2 findings made visible rather than design preferences: H1's and H4's
**rename callout** — the `2025-06-14` row where the value was rewritten, marked
as the reason scoping is by attribute id and never by `model_title` — and H2's
**footer note** that four of the ten occurrences are ACL-hidden and their entry
counts are not obtainable at all.

The **not-recorded state** is drawn once, in the History deck's foot, outside
the candidate lane. It is a shared requirement and not a differentiator: with
`MISP.log_new_audit` off, every candidate renders the same page, and the
treatment argues that this is neither the empty state nor an error — it says
what is still knowable without the audit log, and that enabling it records
forward and never reconstructs the past.

---

## Phases 17–21 — moved to `value-profile-phases/`

§9 through §13 were the five phase briefs that came out of reviewing what
shipped rather than out of a queue. They are a chain — 18 builds 17, 19 acts on
a finding of 18's, 20 is blocked on 19 and 21 on 20 — and they cite each other
68 times, so they moved together into one directory rather than being folded
into this document's front matter.

| Was | Phase | Now |
|---|---|---|
| §9 | 17 | [`value-profile-phases/17-object-siblings.md`](value-profile-phases/17-object-siblings.md) |
| §10 | 18 | [`value-profile-phases/18-siblings-built.md`](value-profile-phases/18-siblings-built.md) |
| §11 | 19 | [`value-profile-phases/19-history-scale.md`](value-profile-phases/19-history-scale.md) |
| §12 | 20 | [`value-profile-phases/20-one-brush.md`](value-profile-phases/20-one-brush.md) |
| §13 | 21 | [`value-profile-phases/21-chart-zoom.md`](value-profile-phases/21-chart-zoom.md) |

**Their section numbers are unchanged.** A reference of the form `§9.6`,
`§11.5.3` or `§13.1` — and the corpus carries over a hundred of them — still
names a real heading. It is now in the file above rather than in this document,
and no reference needed rewriting.

Still here rather than moved: §7 and §8, which are also phase briefs (7 and 14).
They hold **§7.9** and **§8.2**, the two "what MISP cannot supply" ledgers, which
are the most-cited subsections in the whole corpus at 27 and 35 references. They
are worth promoting out of their deck sections one day; that is a separate move
from this one.

---

## Going live — moved to `value-profile-live/`

§14 was the contract for taking the nine tabs live: the model layer, the seam
the coming `attributes.value` table moves behind, the fetcher and aggregation
rules, viewer-scoped counts, and the UI reuse test. It now opens its own
directory, because the campaign it governs will run for several phases and each
one needs somewhere to record its decisions beside the rules it followed —
which is what `value-profile-tabs/` did for the fixture campaign.

| Was | Now |
|---|---|
| §14 | [`value-profile-live/00-contract.md`](value-profile-live/00-contract.md) |

**Its section numbers are unchanged**, so `§14.3`, `§14.6` and `§14.9` still
name real headings — 29 references, none rewritten.

It gained two tables in the move. **§14.12**, a board of all twenty-seven
endpoints and the element each renders, is the fine-grained record of which
panels are still fixture-backed. **§14.13** is the phase index. Tab-level status
stays in §1.4 of this document: **§1.4 says whether, the contract says what.**

Unlike `value-profile-tabs/00-shared.md`, which was phase 8, the contract
carries no phase number — it builds nothing. The first live phase is 22.
