# PRD: Analyst Profile — phase 8c, the wiring

**The picked design becomes the product.** 8a built the contract and
rendered nothing; 8b drew three candidates and
[`09b-decision.md`](09b-decision.md) picked the workbench; 8b-r refined
it against a reviewer ([`09b-revisions.md`](09b-revisions.md)). This
phase turns `mockups/workbench.html` into templates the controller
serves, and links the value page's verdict to the profile that weighted
it.

Specification: [`09-editor.md`](09-editor.md) §3 (the pages), §4 (the
sections), §5 (the simulator), §6 (fork), §7c (verification). What 8c
inherits is listed in [`09b-decision.md`](09b-decision.md) §6.

## 1. Orientation

### 1.1 What is true right now

- **Every action answers JSON.** `AnalystProfilesController::__payload()`
  hands its array to `RestResponse->viewData()` and there are no
  templates at all. A browser gets JSON.
- **The view-model is complete and generic.** `AnalystProfileFormTool`
  emits seven sections, each a list of *blocks*; there are exactly four
  block kinds (`items`, `fields`, `strip`, `map`) and seven field types
  (`bool`, `select`, `int`, `float`, `string`, `types`,
  `module_states`). Every field carries its own `path`, which is the
  POST name `merge()` expects. So the editor is one generic renderer
  plus per-section garnish, not seven bespoke forms.
- **The mockup is a reading, not a form.** It draws a signal's points as
  `.kv` chips — `<k>cap</k><v>16</v>` — which cannot be edited. §4.1.
- **The value page's verdict is still the fixture.**
  `ValuesController::__profileFor()` is `ValueProfileFixture::forValue()`
  and nothing on that page calls `ValueVerdictTool`. Phase 9 converts
  it. §4.3 is what that costs 8c.

### 1.2 The seam

One rule, and it is what keeps 8a's work from being unpicked: **no
action grows a second shape.** `__payload()` gains a view name; with
one, an HTML request gets the template and a REST request gets exactly
the JSON it got yesterday. Every array key a template reads is a key
`09a-fixtures/` already carries, so the fixtures stay the contract the
templates are checked against.

Write actions have no view. In HTML they flash and redirect, except
where 8a already answers `confirm` — fork into an occupied slot, and
the enable swap — which renders one confirm page.

## 2. What ships

| | File | What |
|---|---|---|
| pages | `View/Themed/Overmind/AnalystProfiles/index.ctp` | the board of profiles, each with its standing |
| | `.../view.ctp` | read-only, the same rail and panes, no inputs |
| | `.../edit.ctp` | the workbench: rail, pane, bench |
| | `.../simulate.ctp` | the bench given the whole width |
| | `.../import.ctp` | paste or upload somebody else's document |
| | `.../confirm.ctp` | the one-enabled swap, named |
| elements | `Elements/AnalystProfiles/workbench.ctp` | the two panes, shared by `edit` and `view` |
| | `.../rail.ctp` | sections, counts, axis tags |
| | `.../section.ctp` | one section: blurb, then its blocks |
| | `.../block_items.ctp` | signals, escalations, exclusions |
| | `.../block_fields.ctp` | a labelled group of fields |
| | `.../block_map.ctp` | key→value maps with an add control |
| | `.../block_strip.ctp` | the band strip against the attainable bound |
| | `.../field.ctp` | one input, dispatching on `type` |
| | `.../chip.ctp` | a key/value setting, editable in place |
| | `.../ttl_curve.ctp` | the shelf life its numbers describe, drawn |
| | `.../bench.ctp` | the assessment head, the ledger, the pinned set |
| | `.../assessment_head.ctp` | lean · relevance · quality, at one rank |
| | `.../runway.ctp` | the relevance shelf |
| | `.../diff_table.ctp` | both contributions and the delta |
| | `.../comparison.ctp` | one row per pinned value |
| | `.../standing.ctp` | the per-row standing pill and its sentence |
| | `.../loader_errors.ctp` | what the signal loader skipped |
| assets | `webroot/css/analyst-profile.css` | candidate B's stylesheet, minus the frame |
| | `webroot/js/analyst-profile.js` | rail switching, dirty tracking, bench refresh |
| engine | `ValueRelevanceTool::stateLabel()` | the state labels, one writer (§4.2) |
| | `ValueVerdictDiffTool::axes()` | the relevance axis carries its runway (§4.2) |
| links | `value_verdict_meta.ctp`, `value_verdict_card.ctp` | the profile becomes a link (§4.3) |
| | `value_verdict_not_counted.ctp` | a `policy` entry links to its exclusion |

## 3. The pages

**`index`** — the header names the count; the rail is the resolution
order with a count per scope, and the note says why resolution stopped
where it did. The table is one row per profile carrying name, owner,
standing, signals enabled over available, both counters, last change,
and the actions that row can take. The bench holds what is in force and
the pinned set.

**`edit`** — the rail is the seven sections plus Raw JSON, each with the
axis it configures ([`09b-decision.md`](09b-decision.md) §4) and a count
of the settings inside it. One section is open at a time. The bench is
the right-hand pane: the assessment head, then the ledger for the value
under assessment, then the pinned set.

**`view`** — the same two panes with no inputs and no bench controls.
With `?value=` each weight shows the contribution it produced; without
one the column is empty rather than invented.

**`simulate`** — the same bench with the pane widths swapped: what is
being proposed on the left, every ledger row on the right, both columns
summing to their own quality, and the comparison set underneath.

**`confirm`** — one page, two buttons, and the consequence named: which
profile is disabled, or how many colleagues an organisation fork
changes.

**`import`** — a textarea and a file field. The refusal path is 8a's,
and the parse error and its line are what the page shows.

## 4. The five things the mockup could not decide

### 4.1 A points chip becomes an input

The mockup draws `points` as read-only chips because a prototype is a
reading. The editor's whole purpose is changing one of those numbers, so
in 8c the chip **holds the input**: the key stays a `<k>`, the value
becomes a small right-aligned field named by the field's own `path`.
Nothing else about the chip changes — the density that made the signals
table legible at 1280px is the reason to keep the chip rather than fall
back to a form row per key.

The `is-undeclared` chip — a key the profile carries and this instance's
schema does not describe — stays an input too. Dropping it on save is
exactly the silent rewrite §7c item 4 exists to catch.

### 4.2 The relevance label has one writer, and the runway reaches the bench

Two things were left open by 8b and both are the same defect: the
relevance axis is rendered in more than one place and each place knows
its own vocabulary.

- `value_relevance.ctp` holds the state→label map as a local array,
  and so does `value_lifecycle.ctp` — **three** copies of four words
  once the editor needed them. They move to
  **`ValueRelevanceTool::stateLabel()`**, beside a `STATES` constant
  naming the four, the way `max_band` already reads
  `ValueVerdictTool::BANDS` and the clock lists were fixed in 8b-r.
  The uncertainty is deliberately not composed in: it is a second
  thing that is true at once, and a caller that wants both says so
  rather than receiving a sentence it cannot take apart.
- `ValueVerdictDiffTool::axes()` reduces relevance to a comparable word.
  It keeps that word — a diff needs something comparable — and gains
  **`label`** and **`runway`**, so the bench can draw the shelf the
  shipped relevance card draws rather than printing a state string.

And the composed form: the fixture says `expired · uncertain`, the
instance says `expired`. **8c prints the label and marks the
uncertainty separately**, which is what the shipped page does. The
editor follows the value page; it does not invent a second reading of
the same axis.

### 4.3 The verdict links degrade honestly

§5.1 turns two plain-text profile names into links. Both render inside
the fixture today, and the fixture has no `profile_id` — so the link
appears when the verdict carries one and the text stays plain when it
does not. That is phase 9's switch, thrown once, rather than a link to a
profile the page did not use.

The consequence for verification: **§7c item 5 cannot run in 8c.** It
asks for a fork made from the value page to move the value page's score,
and the value page does not read the engine yet. It is restated in §6 as
phase 9's.

### 4.4 Pinning keeps what is built

[`09-editor.md`](09-editor.md) §2.2 records a spec/build gap: the spec
says the comparison set is seeded from the value the analyst arrived
from, and 8a benches that value without pinning it. **8c keeps the built
behaviour** — the set stays something the analyst chose, the arrived-from
row is labelled *benched, not pinned* and carries the Pin button, and
the empty state is drawn rather than avoided. The mockup already draws
this, so the drawing, the code and now the spec agree.

### 4.5 What the scaffolding leaves behind

The three `.vp-board` frames, their URL tags and the dimmed navbar are
how three pages were compared in one file. They do not come across.
Neither does `--vp-page`: the real page is `container-fluid`, so the
container query that degrades the workbench fires on a width the window
actually produces.

## 5. CSS and JS

**The stylesheet is candidate B's**, lifted with the frame's rules
removed, and shipped as `analyst-profile.css` rather than appended to
`value-profile.css`: the two pages share a palette and nothing else, and
the value page already carries 267KB it loads on every tab.

**The JS is small and does three things**: switch the open section from
the rail, mark a field the session has edited and keep a count of them,
and refresh the bench by posting the dirty document to `simulate` and
swapping in the fragment it answers. The third is what makes the bench
*"recomputed on every change"* rather than a reading of the saved
document, and it is the only network call the editor makes.

No client-side arithmetic. The bench's numbers come from the engine or
they do not appear: a JavaScript re-implementation of the ledger is a
second scoring engine, and the whole feature exists because there should
be exactly one.

## 6. Verification

[`09-editor.md`](09-editor.md) §7c is the list. Restated with what 8c
can actually reach:

1. Every page renders in both themes, `--vp-mal` asserted to resolve
   first.
2. The diff's delta column uses `--vp-dir-with` / `--vp-dir-against`.
3. An empty diff renders as *"no change"*; an empty comparison set
   renders its instruction.
4. A signal's generated form round-trips: render, save unchanged, the
   stored `points` map is byte-identical — and so is the rest of the
   document, so `revision` does not move.
5. **Deferred to phase 9** (§4.3): fork from the value page and watch
   the hero move. What 8c asserts instead is that the link points at
   the profile the verdict names — computed from the engine, so the
   link is live the moment phase 9 hands the page a real assessment —
   and that it stays plain text, still naming the profile, when the
   verdict names no id.
6. The loader's error list is on screen.
7. A `policy` entry in `not_counted` links to its exclusion — and only
   a `policy` entry does, because a row with no decision behind it has
   nowhere to send anybody.

Plus the corpus's standing checks: `parallel-lint`,
`queryACL/findMissingFunctionNames`, a harness that renders every
template against the fixtures, and a live probe over HTTP as four users.

## 7. What 8c found, built 2026-09-11

Ten defects, four of them in code earlier phases shipped. Every one
was found by building the page or by the checks below, and every one
is fixed.

### 7.1 The value page 500s for every reader whose theme is not Overmind

`ValuesController::beforeRender()` set the theme only when
`empty($this->theme)` — a floor, deliberately, so a reader's own theme
would be left alone. But a reader whose `ui_theme` is `Default` **has**
a theme, so the floor never fired, the render looked for
`Themed/Default/Values/view.ctp`, and the page answered 500. Phase 7
has shipped that since its first page.

Found because the new editor did exactly the same thing on its first
request, and the value page turned out to be doing it too under the
same login. The floor now asks the right question —
**`MispTheme::carries($theme, $controller)`**, *does this theme have
these views* — and both controllers ask it. A reader whose own theme
carries the directory still keeps it.

### 7.2 The editor rendered under Bootstrap 4

`OvermindPages::$pages` is the list of controller/action pairs that get
the BS5 chrome, and `analystProfiles` was not in it. So the page loaded
`bootstrap.css` and `main.css` while its whole stylesheet is written
against BS5 custom properties and `data-bs-theme` — `--bs-border-color`,
`--bs-body-bg`, `--bs-secondary-color`, none of which existed. Nothing
errored; it simply rendered as a page nobody designed.

Registered with the six actions that render a page. The check that
catches it is the first trap restated: assert the palette **resolves**
before asserting anything about it (§7.9).

### 7.3 A points chip could not be edited

§4.1, by design in 8b and a gap in the product. The chip now holds the
input.

### 7.4 The exclusions pane offered to edit an exclusion's id

`exclusionItems()` handed the whole stored entry to
`generatedFields()`, which reports every key the schema does not
describe as an *undeclared* setting. The entry's own structural keys
are two of those: `id` came back as an editable string, and `enabled`
came back a second time under the name the row's own checkbox already
posts. The entry is now passed without them.

### 7.5 The TTL buckets drew a control per attribute type, per bucket

MISP has 194 attribute types and there are four buckets: 776 checkboxes
and **400KB of one page**, measured. §4 of the spec says *never a row
per type MISP has*, and this was that rule broken at one level down —
the row was per bucket and the *control* was per type.

The pane now draws the types a bucket holds, plus a picker over the
types no bucket has taken, with one option list per map rather than one
per row. The page went from 560KB to 221KB.

### 7.6 The bucket map posted itself back inside out

`ttl_types` is stored **type => bucket** and the pane groups it
**bucket => types**, which is the whole reason the pane is four rows
instead of 194. The first version posted the grouping it drew: four
buckets' chips all under one name, arriving as a flat list of ten types
with no bucket on any of them. Every TTL assignment on the profile
would have been destroyed by saving the pane that displays them.

Each chip now posts itself under its own type with its bucket as the
value. Caught by the round-trip check, and only after the instance's
default was brought up to the current shape — against the legacy
document the pane had no bucket assignments to lose.

### 7.7 The curve lost the point that says where the value is

`ttl_curve.ctp` samples the polyline in a loop that used `$runway` as
its own counter — the same name as the parameter carrying the value on
the bench. By the time the marker was computed the parameter was the
last sample, `0`, and the guard dropped the marker silently. The curve
drew correctly and the one thing it was drawn *for* was missing.

Renamed, and asserted: the harness now requires `ttl-here` and the
sentence that says the same thing to a reader who cannot see it.

### 7.8 A save upgrades a legacy document, and now says so first

Not a defect — the design of D18 and D19 — but a surprise, and the
corpus's rule is that a surprise gets said out loud. Both sections are
read through a **shim**: the stored document keeps its old keys until
something writes it, and the editor is the first thing that ever does.
So posting any section back rewrites `relevance` into buckets and
renames `cost_posture`, and `revision` moves on a save the analyst
believes changed nothing — invalidating every row phase 10 will
materialise against that counter.

`AnalystProfileFormTool::legacyShapes()` names what is in an older
shape and the editor prints it above the panes. The upgrade is asserted
**lossless** — the effective type-to-days map, the clock, the speed and
the fractions are unchanged — and asserted to **settle**: the upgraded
document round-trips byte-identical.

One thing was missing from the pair. D18 already had `merge()` drop
`ttl_days` once the bucket keys are present; D19's rename had no
equivalent, so a saved document carried `locality_posture` *and*
`cost_posture` — not ambiguous, because the reader prefers the new key,
but a setting nothing reads sitting in the raw document under a name
that says it is about cost. `merge()` now drops it the same way.

### 7.9 Smaller things worth the line

- **`Form->create` underscored its own controller.** Given the URL as
  an array it wrote `/analyst_profiles/edit/23`, which routes and is a
  second spelling of the URL every link on the page writes as
  `analystProfiles`. All three hand-built forms now pass the string
  `Html->url()` produces.
- **The four shelf-life buckets had a delete button.** A map's rows are
  removable only where the key set is open; the buckets are a closed
  vocabulary and a cross beside `Short` offers something that cannot
  happen. The presence of an add control is what distinguishes the two
  kinds.
- **"Your pinned values: 0" sat above a row.** A reader arriving from a
  value page has one value on the bench and nothing pinned. The heading
  counts the rows and a line underneath says how many are pinned.
- **The band marks hung off the strip.** A mark is centred on its
  position, so one at 0 or at the bound widened the pane by 6px. Kept
  just inside; the number it carries still says where it is.
- **The index hid Edit from a site admin on the instance default.** The
  mockup's *"No Edit: it is the instance's, not yours"* is the reading
  for an analyst, and the row now shows Edit whenever the reader may
  edit and the note only when they may not.
- **`--vp-mal` and its five siblings had one declaration inside
  `value-profile.css`**, a 267KB file the editor has no other reason to
  load. Split into `value-palette.css`, loaded by both pages. The
  harness asserts the value page no longer declares its own copy.

### 7.10 What the checks are

| Check | What it covers | Result |
|---|---|---|
| `09c-wiring-harness.php` | the markup, with no session: the round-trip, the legacy upgrade, the diff, the axes, the empty states, the palette, the two verdict links, the read-only page | **70 checks, 0 failures** |
| `09c-wiring-http-probe.sh` | the seam: HTML for a browser and the identical JSON for REST, the confirm, the save, the refused paste, the bench fragment, enable/disable/delete | **45 checks, 0 failures** |
| `09c-wiring-page-check.sh` | a real browser, both themes, 1600px and 1280px: the stylesheet resolves, the three axes are at one rank (spread 0px), nothing scrolls sideways | **PASS, 4 combinations** |
| every earlier phase | store 34, signals 98, lean and bands 100, exclusions 42, relevance 133, reference 114, enrichment 123, editor 137 | **all green** |
| the live probes | store 36, signals 36, relevance 60, reference 74, enrichment 46, editor 44, 8a over HTTP 35 | **all green but one, below** |

**The one:** `02-store-live-probe.php` asserts the instance default
starts at `version 1, revision 1`, and this instance's has been through
forty updates. It is an assertion about a pristine instance rather than
about the code, and it failed the same way before this phase.

### 7.11 The dev instance was two versions behind

`app/files/` is not mounted into the dev container, so the shipped
`default-v1.json` it holds was the image's copy at version 4 — before
D18 and D19 — and `updateDefaults()` skipped every run because the
version it read was not newer than the one stored. The instance's
default was therefore in the pre-bucket shape, which is why §7.6 could
hide: the pane had no bucket assignments to lose. Copied in and loaded;
the default is version 8.

## 8. What 8c deliberately did not do

- **`?value=` search on the bench.** The mockup draws a search box for
  benching a value the analyst is not pinned to. The quick-switch chips
  are built; the search is not, because the endpoint that would answer
  it is the value page's own and pointing the editor at it is a wiring
  decision phase 9 should take.
- **A drag to move a signal between ledger groups.** The mockup's
  `wb-grp-drop` is drawn; the control that ships is the select on the
  row, which does the same thing and needs no pointer.
- **Anything about `auto` or `max_age_hours`.** Both are declared and
  inert (D17, D19), and the editor draws them as what they are.
