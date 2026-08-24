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

Two phases were added once the skeleton landed and are written up where they
belong: **phase 6**, the verification pass, is §6; **phase 7**, candidate
mockups for the five stubbed content tabs, is §7.

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
`media="print"` and says nothing about the screen. About 760KB once the font
below is embedded, inlined into each artifact and far inside the 16MB ceiling.
`prd/phase7/kit/build-kit.sh` builds it and stamps the source commit into a
header comment, so a stale kit is visible rather than silently wrong.

**Fonts and icons.** The artifact CSP blocks every external host, so the build
script embeds `webfonts/fa-solid-900.woff2` (80KB) as a `data:` URI and
rewrites its `@font-face` src. `fa-brands` and `fa-regular` are dropped — the
page uses solid. `misp-iconify.css` already carries its glyphs as inline
`data:` SVG masks, so MISP's own icon set needs no work.

**The theme bridge.** Artifacts stamp `data-theme` on the root element and
otherwise fall back to `prefers-color-scheme`; MISP switches on
`data-bs-theme`. The kit ships a short script that mirrors the artifact's
resolved theme onto `data-bs-theme` at load and on change, so a mockup follows
the reader's theme using MISP's own dark palette rather than a second one.

**`prd/phase7/kit/frame.html`** — the page around the tab body: the banner with
`185.234.219.24` and its type chips, the fact strip, the pivot rail, the
nine-tab bar with the target tab active, and the `col-lg-9` / `col-lg-3` split.
The frame renders at reduced emphasis: it is context for judging the tab body,
not part of what is being judged. The tab bar wraps to two rows at ≤1600px and
that is left exactly as §6.1 records it.

**The pinned width.** Step 0 measures the real content column on the live stack
and pins it in the kit as `--vp-mock-body` (expect ~975px at a 1600px window;
the measured number is what ships). Every candidate renders at that width, so
density is honest and no candidate wins by being drawn narrower than the column
it would actually live in.

**Step 0's own check.** The frame renders in both themes in headless Chrome,
asserting that `--vp-mal` resolves before asserting anything else. That was a
harness rule in §6.1 because the page pulled its CSS cross-origin; with the kit
inlined there is no fetch left to fail, and the assertion stays only as a guard
against a malformed kit.

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

The user then picks one candidate per tab, or a graft of two — "`O2`'s filter
rail with `O4`'s row". Picks are recorded here:

| Tab | Chosen | Grafts | Date |
|---|---|---|---|
| Occurrences | | | |
| Sightings | | | |
| Relationships | | | |
| Enrichment | | | |
| Analyst data | | | |

"None of these" is a legitimate entry. If the four missed the axis, one more
round for that tab costs a fraction of implementing a design nobody wanted.

### 7.9 Exit criteria

Five artifacts published, twenty candidates, each rendering at the pinned width
in both themes with its reckoning card, and every row of the decision table
carrying either a pick or an explicit re-run.

### 7.10 What phase 7 leaves to phase 8

No PHP, no CSS under `app/`, no fixture changes, no live data, no writes — the
whole phase lives under `prd/phase7/`.

Each chosen candidate then becomes its own implementation phase, in the shape
phases 3 to 5 established: one ajax endpoint per panel on `ValuesController`,
elements under `Elements/Values/View/`, the fixture extended with that tab's
shape, `index_table` and the existing field renderers wherever they fit, and
the panel's empty state built alongside the populated one rather than
discovered later. Timeline and History join the queue once this loop has run
once.
