# PRD: Value Profile tabs — shared groundwork

**Phase 8.** Prerequisite for the five tab phases that follow. Small on
purpose: it exists so the same primitive is not specified three times and then
built twice differently.

## 1. What this phase is for

Phase 7 produced twenty candidate mockups and a pick per tab
(`prd/value-profile-page.md` §7.8). Five of those designs are now specified in
`01-occurrences.md` … `05-analyst.md`. Four things are common to all five, and
one of them is a genuinely new primitive:

1. the mechanics of turning a stubbed tab into a real one,
2. an inventory of the `vp-*` primitives that **already exist**, so no tab
   re-invents one,
3. the facet control, which Occurrences and Relationships both need,
4. the rules that let a mockup be translated into a template without lying:
   charts, pagination, and a fourth honest state.

Nothing in this phase renders a new tab by itself. Its exit criterion is that
the five tab phases can each be implemented without touching shared code again.

## 2. What every tab PRD inherits

These are contracts, not suggestions, and the tab PRDs do not restate them:

| Source | What it governs |
|---|---|
| `value-profile-page.md` §1.3 | Reuse MISP's factories · fixture shaped like the real thing · additive changes to shared code · honest states · nothing writes |
| §2.1 | Where files live |
| §2.2 | Controller shape: `base64_decode($v, true)`, `NotFoundException` on bad encoding, `$this->layout = false` on ajax actions |
| §2.3 | The fixture contract and its array shapes |
| §2.4–§2.6 | Page composition, tab registry, panel conventions |
| §2.9 | CSS: colours from existing variables, never raw hex; every block gets a `[data-bs-theme="dark"]` counterpart |
| §2.10 | Charts are Chart.js, loaded once at page level |
| §2.11 | Interactivity: client-side only, against data already in the DOM; anything that would write renders disabled with an explanatory `title` |
| §2.12 | Unknown values render the full page with every panel in its own empty state |
| §7.9 | What MISP cannot supply, per tab |

**Fixture-first.** These five phases build real templates and real endpoints
against `ValueProfileFixture`. No model queries, no writes. Live wiring is a
later phase per tab, and §7.9 is the list of what it will have to solve first.

## 3. Turning a stub into a tab

A stubbed entry in `view.ctp`'s `$tabRegistry` carries `id`, `title`, `icon`,
`count` and a one-line `note` that `value_placeholder.ctp` renders. Promoting
it:

1. Drop `note`.
2. Add `left` — an array of `$panel('<action>')` entries, one per ajax panel,
   top to bottom.
3. Add `right` only if the design has a rail. `right => null` keeps the tab at
   full width, which is what `view_layout` already does for the UNKNOWN
   verdict's missing aside — that is the mechanism a `col-12` design uses, not a
   new one.
4. Leave `count` alone. It comes from `$profile['counts']` and the fixture
   already supplies all five.

Which shape each tab takes:

| Tab | `left` | `right` |
|---|---|---|
| Occurrences | `viewOccurrenceTable` | `null` — the panel lays out its own rail |
| Sightings | `viewSightingChart`, `viewSightingList` | `viewSightingDecay`, `viewSightingReporters`, `viewSightingAdd` |
| Relationships | `viewRelationCooccurrence`, `viewRelationNearMatch`, `viewRelationAsserted` | `viewRelationGraph`, `viewRelationSettings` |
| Enrichment | `viewEnrichment` | `null` |
| Analyst data | `viewAnalystStanding`, `viewAnalystThread` | `null` |

Endpoint names never collide with the Overview's (`viewOccurrences`,
`viewSightings`, `viewAnalystPreview` are the Overview's preview cards and stay
as they are). One ajax action per panel, each rendering one element, exactly as
phases 3–5 did.

**Where a design's rail is on the left** — Occurrences — it does *not* get
`view_layout`'s 9/3 reversed. The tab takes one full-width slot and its element
renders its own internal `row` with `col-lg-3` + `col-lg-9`. That is also the
truthful shape: facet counts and table rows must be computed from the same
fetch, or they can disagree with each other.

## 4. Primitive inventory — reuse, do not re-invent

`value-profile.css` already carries **257** `vp-*` primitives from phases 3–5.
The chosen candidates lean on them heavily, and several tabs would otherwise
each invent their own. Before adding a class, check this list:

| Family | Classes | Already used by |
|---|---|---|
| Panel chrome | `.vp-panel`, `.vp-panel-glyph`, `.vp-subhead`, `.vp-min-w-0` | every built panel |
| Rail card | `.vp-aside`, `.vp-aside-head`, `.vp-aside-title`, `.vp-aside-meta`, `.vp-aside-note` | Verdict aside |
| Honest states | `.vp-empty`, `.vp-empty-inline`, `.vp-acl-note`, `.vp-acl-note-band`, `.vp-panel-stub`, `.vp-panel-stub-badge`, `.vp-panel-stub-note` | Overview, Verdict |
| Human claim | `.vp-analyst`, `.vp-analyst-kind`, `.vp-analyst-body`, `.vp-analyst-text`, `.vp-analyst-meta` | Overview analyst preview |
| Decay | `.vp-decay`, `.vp-decay-head`, `.vp-decay-model`, `.vp-decay-score`, `.vp-decay-track`, `.vp-decay-fill`, `.vp-decay-threshold`, `.vp-decay-flag`, `.vp-decay-expired` | Overview lifecycle |
| Opinion | `.vp-opinion`, `.vp-opinion-track`, `.vp-opinion-fill`, `.vp-opinion-value`, `.vp-hist`, `.vp-hist-bar`, `.vp-hist-bar-mal`, `.vp-hist-bar-ben`, `.vp-hist-bar-empty`, `.vp-hist-axis` | Verdict opinions |
| Contribution bar | `.vp-weight`, `.vp-weight-track`, `.vp-weight-fill`, `.vp-weight-label`, `.vp-weight-points` | Verdict ledger |
| Reporters | `.vp-reporter`, `.vp-reporter-name`, `.vp-reporter-track`, `.vp-reporter-fill`, `.vp-reporter-count` | Overview sightings |
| Tables & filters | `.vp-table`, `.vp-filter-note`, `.vp-filter-clear`, `.vp-relation`, `.vp-fact-line`, `.vp-fact-line-sub`, `.vp-fact-line-warn` | Overview, Verdict |

Two consequences worth stating, because the mockups already assume them:

- **The claim block is `.vp-analyst`.** Relationships' asserted claims and
  Analyst data's notes and opinions are the same visual object, and it exists.
  A human claim is never a table row (see `03-relationships.md` §6).
- **The opinion histogram is `.vp-hist`.** Analyst data draws the same ten
  buckets the Verdict tab draws, through the same classes, so a reader who has
  seen one recognises the other.

## 5. The one new shared primitive — the facet control

Occurrences' rail (`O2`) and Relationships' pane (`R3`'s contribution to `R1`)
both narrow a list by counted facets. The mockups named them per tab
(`vp-o-facet*`, `vp-rel-facet`); they become one primitive.

```
.vp-facetgrp        one group: a .vp-subhead and its rows
.vp-facet           one row: label · count · bar (a <label> wrapping a checkbox)
.vp-facet-label     the label, truncating; may contain a badge or a tag chip
.vp-facet-count     the count, tabular figures, right-aligned
.vp-facet-bar       proportional bar, width relative to the largest in the group
.vp-facet-note      the honesty line a facet block carries (below)
```

Rules the control carries with it, because they are what make a count honest:

- **A facet counts what the viewer may see**, never what exists. Where that
  differs from a number already on the page, the block says so in
  `.vp-facet-note` — the banner counts `ip-dst 7` and the rail counts `4`
  because four occurrences are hidden by distribution.
- **Long tails are cut, visibly.** Past ten values a group shows its top ten
  and an "n more"; past ~50 it becomes a search box rather than a list.
- **A group of zeroes is not rendered.** A facet rail of zeroes is a lie about
  the value; the empty state belongs to the list, not to the filter.
- **Counts and rows come from one fetch** in this pass. Tallying the fetched
  set in PHP is honest at ten rows and stops being honest the moment the list
  paginates — that is the live-data problem recorded in §7.9, and the note the
  control renders is what tells the reader which regime they are in.

Interaction is client-side and real: checking a facet filters the rows already
in the DOM and updates the panel's "Showing n of m" line. Nothing re-queries.

## 6. Pagination inside an ajax panel

The Overview's preview table deliberately has no `sort` keys and no Paginator
(§2.6). Two of the tab designs do paginate — Occurrences' table and
Relationships' co-occurrence pane. In this pass:

- The page control renders as real Bootstrap `pagination` markup with the
  current page active and the arrows disabled at the ends.
- It operates on rows already in the DOM. No second request, no Paginator
  component, no `paginate()` in the controller.
- The panel header carries `1–n of m` beside it, and `m` is the count the
  fixture supplies — so a paginated panel and the tab-bar count cannot drift.

When these panels go live, this is where `Paginator` inside an ajax action
lands. The markup is shaped for it now so that change is local.

## 7. Static SVG in the mockups, Chart.js in the templates

The phase 7 mockups drew every chart as inline SVG because a published artifact
cannot fetch a script. **That is an artifact constraint, not a design
decision.** The implementation uses Chart.js, which the page already loads once
at page level (§2.10), following `value_verdict_curves.ctp`: namespaced canvas
ids per panel, init inside the fragment, and no assumption about which fragment
arrives first.

Three exceptions stay CSS or inline SVG, because Chart.js would be the wrong
tool and the mockups are already right:

- the decay bars, reporter bars, weight bars and opinion histogram — CSS, as
  they are today;
- Relationships' neighbourhood sketch — a small static SVG, since there is no
  value-centred graph feed to drive a real one (§7.9);
- Analyst data's position strip — a static SVG scale, not a chart.

Where a mockup's SVG becomes a Chart.js canvas, the tab PRD says so explicitly.

## 8. A fourth honest state — suppressed

§1.3 names three states that must look different: not implemented, nothing to
show, hidden from you by ACL. Relationships needs a fourth, and it is not a
corner case: past `MISP.correlation_limit` (default **20**) MISP records the
value in `over_correlating_values` and stores **no** correlations at all. "No
rows" then means "too many to store", which is the opposite of empty.

```
.vp-suppressed          the band: what was suppressed, by which setting, and
                        what the reader should do instead
.vp-suppressed-badge    the label, distinct from .vp-panel-stub-badge
```

Only Relationships uses it today. It lives in the shared sheet because
"suppressed" is a property of MISP's engines, not of one tab, and Sightings'
`Sightings_range` cap and Enrichment's service-unreachable state are the same
shape of claim.

## 9. Two shared-code dark-theme fixes

Both surfaced in phase 7 review, both verified in source, both needed by the
designs that follow. Additive and guarded, per §1.3.

1. **`IndexTable/multi_select_toolbar.ctp:18`** paints the bulk bar `bg-light`.
   Bootstrap does not redefine `--bs-light-rgb` under `[data-bs-theme=dark]`
   while it does redefine `--bs-body-color`, so in dark the bar is near-white
   text on a near-white ground — on every index table that uses it, not only
   this page. Occurrences' bulk bar is this element. Fix: a theme-aware surface
   (`--bs-tertiary-bg`) in place of `bg-light`.
2. **`genericElementsBS5/Badges/type.ctp:12`** uses `border border-dark`, which
   all but vanishes against the dark ground. Every tab that renders an
   attribute type badge hits it — Occurrences, Relationships and Enrichment all
   do.

Neither fix changes light-theme rendering; that is the acceptance test.

## 10. How the fixture grows

Each tab adds its own key to the array `forValue()` returns, and one private
builder per value per tab, mirroring `maliciousOccurrences()` /
`maliciousLedger()`. The four demo values are the four states, and every tab
supplies all four:

| Value | What the tab's data must show |
|---|---|
| `185.234.219.24` | the populated case the mockup drew |
| `104.21.34.198` | the conflicted/high-cardinality case — 1,847 relationships, a bimodal opinion set |
| `8.8.8.8` | the benign case — and for Relationships, the suppressed one (21,904, `over_correlating => true`) |
| any other | zero, every panel in its own empty state (§2.12) |

A tab PRD that only supplies the malicious value is not done: the unknown value
already renders all nine tabs, so a missing shape is a rendering error on a page
that already ships.

## 11. Verification

Per §6's method — a real page load against the Docker stack serving this
worktree, not a lint pass:

1. `php -l` over every new file.
2. All five tab endpoints return 200 for all four demo values (20 requests) and
   every panel resolves off its spinner.
3. The facet control renders, filters client-side, and its note appears wherever
   the viewer's count differs from the page's.
4. Light and dark, with the two shared-code fixes in place: the bulk bar and the
   type badge are legible in both.
5. Existing pages that use `multi_select_toolbar` and `Badges/type` render
   byte-identically in light — the guard on §1.3's "additive changes" rule.

## 12. Exit criteria

The five tab PRDs can each be implemented without editing shared code: the
registry mechanics are known, the primitive inventory is written down, the facet
control exists with its honesty rules, pagination and charts have a stated
translation, `suppressed` exists as a state, and the two dark-theme fixes are
in.
