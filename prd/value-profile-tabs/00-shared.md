# PRD: Value Profile tabs — shared groundwork

**Phase 8.** Prerequisite for the five tab phases that follow. Small on
purpose: it exists so the same primitive is not specified three times and then
built twice differently.

## 1. What this phase is for

Phase 7 produced twenty candidate mockups and a pick per tab
(`prd/value-profile-page.md` §7.8). Five of those designs are now specified in
`01-occurrences.md` … `05-analyst.md`. Five things are common to all five, and
one of them is a genuinely new primitive:

1. the mechanics of turning a stubbed tab into a real one, including the ACL
   entry every new endpoint needs (§3.1),
2. an inventory of the `vp-*` primitives that **already exist**, so no tab
   re-invents one,
3. the facet control, which Occurrences and Relationships both need,
4. the rules that let a mockup be translated into a template without lying:
   charts, pagination, and a fourth honest state,
5. the handful of hooks on genuinely shared MISP elements that the designs
   depend on (§5.2), so a tab phase is a controller and its own elements and
   nothing else.

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

### 3.1 Every action needs an ACL entry, and this phase adds all fourteen

`ACLComponent::checkAccess` throws `ForbiddenException` for any action absent
from its controller's list — with one escape hatch: `perm_site_admin` is checked
last and returns true regardless. So a missing entry is invisible to whoever is
most likely to be testing, and shows up for everyone else as a panel whose
spinner never resolves.

That already happened. **`viewVerdictAside` was never added to the `values`
block**, so the Verdict tab's rail has been 403 for every non-site-admin user
since phase 5, and phase 5's verification passed because it ran as the admin.
Fixed here alongside the new ones.

All fourteen tab endpoints are registered in this phase rather than each in its
own — one entry per action, `theming_enabled`, in `ACLComponent.php`'s `values`
block. A tab phase then lands as one controller-plus-element change, and the
five phases do not queue on the same array.

**When adding an endpoint, verify it as a non-site-admin user, or verify the ACL
entry exists by reading it.** A 200 as admin proves nothing about this.

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

### 5.1 The contract, as built

`value-profile.js` carries the behaviour and `value_facet_group.ctp` the
markup. A panel opts in with `data-vp-list` on the region that owns the rows;
everything else hangs off that.

| Attribute | On | Means |
|---|---|---|
| `data-vp-list` | the panel region | this region is a faceted list |
| `data-vp-list-rows` | the row host | rows are its `tbody > tr`, or its `[data-vp-list-row]` children |
| `data-vp-facet="k:v k:v"` | a row | the row's facet tokens; several values for one key is fine |
| `data-vp-hidden="token"` | a row | keep this row out until something reveals `token` |
| `data-vp-facet-key="k"` | a checkbox | a facet on key `k`, its `value` the token |
| `data-vp-reveal="token"` | a checkbox | reveals rows hidden by `token` |
| `data-vp-facet-clear` | a button | clears every facet in this list; disabled while none are set |
| `data-vp-facet-more` | a button | reveals its group's folded tail |
| `data-vp-facet-search` | an input | narrows its own group's rows, never the table's |
| `data-vp-pager` + `data-vp-page-size` | the control | page the surviving rows client-side |
| `data-vp-list-shown`, `data-vp-facet-rows`, `data-vp-facet-count-active` | spans | rows left · rows left · facets set |
| `data-vp-page-from`, `-to`, `-of` | spans | the current page window |
| `data-vp-list-empty` | a block | shown only when a *filter* emptied the list |

Two semantics the mockups implied and the implementation had to settle:

- **Within one key the checked values are alternatives; across keys they all
  have to hold.** Ticking `ip-dst` and `ip-src` under Type and `Org A` under
  Organisation means *(ip-dst or ip-src) and Org A* — what a reader means by
  it, and what every faceted search does.
- **Soft-deleted rows are a reveal, not a facet value.** Filtering *to* deleted
  rows and including them alongside the rest are different questions, and the
  design only ever asks the second. Hence `data-vp-reveal` rather than a
  `state:deleted` token.

Changing a facet or a reveal returns the reader to page one: narrowing changes
how many pages there are, and leaving them on a page that no longer exists is
how a filtered table starts looking empty when it is not.

`data-vp-list-empty` fires only when a filter produced the emptiness. A list
that was empty to begin with keeps the template's own empty state — "no rows
match your filter" over a value with no occurrences is a different and false
claim.

### 5.2 Three shared-code additions this needed

Additive and guarded, per §1.3. All three exist so no tab phase has to touch
shared code:

1. **`index_table.ctp` gains `row_data_callable`.** It already had
   `row_class_callable`, but a row matched on several independent keys at once
   cannot express that as a class string without the reader parsing class names
   back into fields. The callable returns name => value pairs hung on the `<tr>`
   as `data-` attributes; names are restricted to what may follow `data-`.
   Occurrences' table is `index_table` (`01` §4), so without this the facet rail
   has nothing to match on.
2. **`multi_select_toolbar.ctp` gains `scope_note`.** The optional slot `01` §4
   asks for — `3 rows · 3 events · 2 organisations` beside the count.
3. **`value_facet_group.ctp` and `value_pager.ctp`**, under
   `Elements/Values/View/`. The honesty rules above are behavioural, and a rule
   restated in two templates is a rule built two ways: the group element is
   where "a group of zeroes renders nothing", "the tail is folded, not dropped"
   and "past ~50 it is a search box" actually live. The pager element is where
   §6's markup lives, first page rendered server-side so a load without
   JavaScript still shows a truthful range.

Both elements are plain partials a tab template includes — not endpoints — and
neither touches `$this`, which is what let them be unit-tested outside CakePHP.

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

## 9. Two shared-code dark-theme fixes — withdrawn, the bug does not exist

Phase 7 review claimed two dark-theme defects here and said both were verified
in source. **Neither is real.** The review reasoned from Bootstrap's tokens
alone and missed that the Overmind theme carries its own inversion layer:
`mainOvermind.css` has a *Dark mode: switch colors between light/dark* section
that redefines exactly these utilities, and the layout preloads that sheet on
every Overmind page (`Layouts/default.ctp:72`), so it is never absent.

```css
/* mainOvermind.css:821 */  [data-bs-theme="dark"] .bg-light   { background-color: var(--bs-dark); color: var(--bs-light); }
/* mainOvermind.css:864 */  [data-bs-theme="dark"] .border-dark { border-color: var(--bs-light); }
```

Measured in a real browser on `/values/view`, both themes, computed styles:

| Element | Light | Dark | Reading |
|---|---|---|---|
| bulk bar (`multi_select_toolbar.ctp:18`) | ground `#f8f9fa`, ink `#212529` | ground `#212529`, ink `#f8f9fa` | inverted correctly |
| type badge (`Badges/type.ctp:12`) | border `#212529` | border `#f8f9fa` | inverted correctly |

Making the proposed changes would have been a regression, not a fix:
`--bs-tertiary-bg` resolves to `#2b3035` in dark, so swapping `bg-light` for it
would have *changed* a bar that already renders correctly.

**The rule this leaves behind, which matters more than the two fixes did:** a
claim about how a Bootstrap utility renders in dark is only settled by reading
`mainOvermind.css`'s inversion section as well as Bootstrap's tokens, and by
measuring in the browser. Neither tab PRD's verification list should carry a
"the §9 fix" item; `01`, `03` and `04` each do, and each should read simply
*the bulk bar / type badge is legible in both themes* — which it already is.

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

Items 2 and 3 of the original list belonged to the tab phases, not to this one:
this phase deliberately renders no new tab, so it has no endpoint to call and no
facet rail on the page to point at. They move to the phase that builds one.

What this phase can and did verify, against the Docker stack serving this
worktree:

1. **`php -l`** over every changed and new file, plus `node --check` on
   `value-profile.js`. Clean.
2. **The two new elements, rendered standalone** — neither uses `$this`, so
   `h()` and `__()` are the whole dependency surface and they can be driven
   directly. Eight cases, all passing: a group of zeroes renders nothing; bar
   shares are relative to the group's own maximum (4/1/1 → 100/25/25); a label
   like `domain|ip` slugs to something safe for an attribute; a caller's badge
   survives as the label; fourteen values fold to ten plus a `4 more`; sixty
   values get a search box; the pager renders `« 1 2 3 »` with page one active
   and the left arrow dead; a single page hides the control but keeps the range
   line; an empty list reads `0–0`.
3. **The facet control driven in the real page, both themes** — markup injected
   into a live `/values/view` load so the shipped CSS and JS are what runs,
   which is the only honest way to test a primitive no tab renders yet. 48
   assertions, all passing: initial paging over 22 eligible rows of 25; a facet
   narrowing to 15; two values on one key widening back to 22 (alternatives);
   a second key cutting to 11 (conjunction); page two showing the eleventh row
   alone; the reveal switch adding the three soft-deleted rows and resetting to
   page one; `Clear all` restoring 25 and going inert; a filter that matches
   nothing showing the empty block instead of a bare table; the bar painting
   its gradient; a checked row tinted differently from an unchecked one; the
   suppressed band carrying a ground in both themes.
4. **Light and dark, measured not eyeballed.** See §9 — the two fixes that
   section asked for were withdrawn because the computed values prove the
   utilities already invert.
5. **`multi_select_toolbar` and `index_table` callers render byte-identically.**
   `events/index`, `attributes/index` and `feeds/index` fetched with and without
   the changes: after normalising the per-request ids CakePHP mints, the only
   remaining difference on all three pages is the page's own render timestamp.
   The `<tr>` attribute census is identical across 140 rows, and
   `multiSelectScopeNote` appears zero times — both new hooks are dormant until
   a caller passes them.

## 12. Exit criteria

Met. The five tab PRDs can each be implemented without editing shared code: the
registry mechanics are known, all fourteen endpoints are registered in the ACL
list, the primitive inventory is written down, the facet control exists with its
honesty rules and a documented markup contract (§5.1), the three shared-code
hooks it needed are in (§5.2), pagination and charts have a stated translation,
and `suppressed` exists as a state.

One correction carried out of this phase rather than into it: §9's two fixes
were withdrawn as unfounded, and the verification lines that reference them in
`01`, `03` and `04` should be reworded when those phases run.
