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

A set the analyst chooses needs somewhere to choose from, which is why
the empty state carries the value box rather than only the sentence
describing it (§7.12). Arriving from a value page is one way in, not
the only one.

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

**The JS is small and does four things**: switch the open section from
the rail, mark a field the session has edited and keep a count of them,
put a value on the bench (and pin or unpin it), and refresh the bench by
posting the dirty document to `simulate` and swapping in the fragment it
answers. The last is what makes the bench *"recomputed on every change"*
rather than a reading of the saved document.

**It waits for the document before reading it.** The script tag is
echoed above the markup it drives, so every lookup at load time answers
`null` — §7.12 is what that costs, and it costs it silently.

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

> **Overtaken 2026-09-12 (`09b-revisions.md` 3.21).** The posture was
> withdrawn entirely, so there is no rename left to shim. `merge()`
> drops *both* names now, `legacyShapes()` says the key is ignored
> rather than renamed, and nothing reads either one. The finding above
> stands for `relevance`, which still upgrades through a shim.

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

### 7.12 The editor's JavaScript never ran, and pinning had no door

Four defects in one report, all on the first page a reader who did not
arrive from a value page actually sees. The first hid the rest.

**The script executed above the markup it drives.** `assetLoader`
echoes `<script src>` where the view runs, which is before the
workbench — so `document.getElementById('ap-rail')` answered `null` on
the first line and the file returned. Nothing in §5 worked: the rail
did not switch panes, the dirty marks never appeared, the bench never
recomputed, and the header's Save called a function that was never
defined. It is the worst shape a bug can take here, because the page
still draws perfectly. Fixed by waiting for `DOMContentLoaded`.

**The CSRF key is spent by the first POST.** `csrfUseOnce` defaults on,
and the editor posts repeatedly by design — every field change
recomputes the bench. So the *second* post of any page was a blackhole.
`ValuesController::beforeFilter()` had already met and documented this
and the fix is the same one: a stable per-session key, which is the
synchroniser-token pattern rather than a weakening. Worth stating that
this was only reachable once the JS ran at all.

**The Pin button was markup a browser discards.** `bench.ctp` drew a
`<form method="post">` for it, and the bench sits *inside* the editor's
form — the parser drops the inner one, so the press submitted the
editor instead. On the simulator, where the nesting does not arise, the
same form carried no token and tripped CSRF. Neither page could pin.
The editor now posts its own form to `pin`/`unpin` over XHR and redraws
the bench, which is also what keeps the unsaved document on screen; the
simulator uses `postLink`, which mints a token. `pin`/`unpin` join the
`validatePost` exemption for the reason `edit` is on it — the hash that
arrives was minted for a different URL.

**And there was no way to put a value on the bench.** The set is a
`UserSetting` written only by `pin`, `pin` was reachable only from a
value already benched, and a value was benched only by arriving with
`?value=`. A reader opening the editor from the index therefore read
*"pin a value and it appears here"* beside no control that pins one —
§8's deferral of the search had quietly taken the only door with it.
The bench now opens with a box: type a value, press **Bench it**, and
it is scored. Benching does not navigate — the value rides in a hidden
field the recompute already posts, so swapping it does not throw away
unsaved edits — and `history.replaceState` corrects the address bar so
a reload lands on the same value. Unpinning keeps the value benched
rather than emptying the pane the press was made from, and a save now
carries `?value=` through the redirect, because *the bench never
leaves* is a claim the save was breaking.

### 7.13 The ledger-group select is cut, the field is kept

Asked what changing a signal's **Ledger group** does, the honest answer
turned out to be *almost nothing a reader can see*, and that is the
argument against it rather than a defect to fix.

`group` decides which heading a row is read under —
`ValueVerdictTool::anchor()` resolves `$entry['group'] ?: $signal->group`
into `kind`, and `group()` buckets the ledger by it. It touches no
arithmetic, so it is **the one control in the editor that cannot change
an assessment**. The page's premise is *change a number, look at what it
did*; this control could never participate, which is exactly why it read
as dead.

Three more, in order of weight:

- **The default is already right.** Every signal declares its own group
  in its implementation (`ValueSignalBase::$group`), and the shipped set
  is deliberate rather than derived — §3.8 of `09b-revisions.md` makes
  the point with `record.temporal_precision`, which is filed under
  `Lifecycle`. There is no wrong default here for an analyst to correct.
- **Using it makes the ledger worse.** `groupNote()` is a switch over
  the four shipped names, so a reporting signal moved to `Sightings`
  lands under *"who has seen it, and how recently"* — a caption that is
  now false for that row. A fifth, custom group gets no caption at all.
  The reachable outcomes were *invisible* and *mislabelled*.
- **It cost the densest pane eleven selects**, one per signal row, for
  a cosmetic result.

**The schema keeps the key.** `anchor()` is unchanged, so a profile that
arrives by `import` or through the Raw JSON pane with a `group` set is
still honoured and the signals pane still files it under that heading.
What went away is the control, not the field — which is what lets a site
running custom signal drop-ins re-shelve them without the editor
offering the same move to every analyst.

That split only holds while a section save **merges** rather than
replaces: a key with no field would otherwise leave the document the
first time anybody pressed Save. `mergeAssoc()` merges, and a signal
entry posts no `__present`, so it survives — asserted now in the
round-trip section, which reads the shipped default's own `group` keys
back out after a merge, and confirmed in the browser by setting a group
through Raw JSON and then saving a different section.

### 7.14 The palette's two columns were named for neither of their maps

> "the column 'What it reads' is fine for the actual data source but it
> mixes the config part"

Correct, and *Settings* beside it had the same fault from the other
end. The table held **four** different things in two columns: the
`points` map, the `config` map, the data source the implementation
reads, and — for exclusions — the layer it applies at. *Settings* named
one of two maps that are both settings; *What it reads* named the
source but sat on top of the config chips.

The source and the layer are declared by the implementation and are not
on the form, so they moved to the name column with the rest of the
row's identity. Each chip column now names its own map: **Points** and
**Tuning**.

**Not *Thresholds***, though seven of the nine `config` keys are one.
`named` sets how many organisations the evidence line lists and
`stale_factor` is a multiplier, so the header would be wrong about
exactly the two keys a reader is most likely to be surprised by — and
*thresholds* already names a section in the rail, at a different scope.
*Tuning* is true of all nine.

**Escalations and exclusions lost a column.** Only signals have a
`points` map; the other two put everything in the second column, so the
first was an em-dash on every row. Their headers are their own now —
*When it fires* for a conflict rule's `when` conditions, *Settings* for
an exclusion — and the freed width goes to the conditions, which were
the most cramped cells on the page.

### 7.15 `× trust weighted` was styled as prose

It sat in `wb-sub`, the same grey the description below it uses, so the
multiplier that scales **every point the row contributes** read as the
tail of a sentence. It is now a tag: square where the state pills
(`available`, `missing`) are round, because those say what a row's
relationship to this profile is and this says what the arithmetic does
to it — two different questions that were sharing one voice in one
cell. The multiplier carries the primary tint; `reads <source>` is the
same tag shape in neutral grey.

**The `×` is gone, and the first pass was wrong to keep it.** It is the
remove glyph three times over on this page — `field.ctp`'s chip drop,
`block_map.ctp`'s row button, and the one `analyst-profile.js` writes
for a chip it builds — so a lone leading `×` sat beside chips whose own
removal affordance is the same character, and the accent it had been
given is exactly the treatment that reads as *clickable*. `×` is right
in `assessment_head` (*"every row is points × polarity"*) because it
has two operands; here it had none. *Weighted* already says the points
are scaled, and it keeps the term the rail uses for the section that
sets it.

Both tints are `color-mix` over a token rather than a fixed grey, so
they hold their weight against either theme's ground — checked in both.

### 7.16 The contribution column says which way it pushed

The signals pane's last column is the one number on the page that is
about the value rather than the document — the points this row put into
the quality of the value on the bench — and it was rendering as an
undifferentiated bold figure. It now carries `d-up` / `d-dn` **and
prints the sign**: colour is the fastest way to read a direction and
the only way to miss one, and this is a column people read while
deciding whether a weight did what they meant.

**And the header says what a `+` is toward.** The sign of a row is its
agreement with the lean, and the lean is decided *before* any of this
arithmetic — by `ValueLeanTool`, from the stance count across
organisations, a false-positive listing and the conflict rules. Nothing
in the ledger produces it; the ledger is then *anchored* to it, `row =
points × polarity`, with polarity `−1` on a benign lean. So a column of
signed, coloured numbers whose meaning lives in a word rendered in the
other pane is a column a reader has to be told how to read. It now says
`+ toward benign` under `ON 127.0.0.1`, and updates as the lean does.

### 7.17 The direction pair now follows the lean everywhere

`--vp-dir-with` and `--vp-dir-against` mean *with* and *against the
lean*, not red and green: on a benign value the row agreeing with the
verdict is the green one. The value page had this right — every verdict
card carries `ValueDisposition::directionStyle()` and every consumer in
`value-profile.css` reads the pair. **The editor had it wrong
everywhere**, taking the `:root` default, which is the malicious
reading. On `127.0.0.1` — benign, quality 20 — the warninglist row that
*supports* the benign verdict was painted red.

Three changes, because the fix is not only a swap:

- **The editor emits the pair.** The lean travels `workbench.ctp` →
  `section.ctp` → `block_items.ctp` for the signals table, and
  `bench.ctp` re-emits it on the fragment itself. The fragment has to
  carry its own, because editing a weight can move the lean: a ledger
  summing against the lean it was anchored to comes back `contested`.
  One helper for both surfaces, so they cannot drift.
- **An ink pair, `--vp-dir-with-ink` / `--vp-dir-against-ink`.** The
  dark-theme rules read `--vp-mal-ink` directly, and a rule naming an
  ink cannot be swapped — so dark mode went on showing the malicious
  reading however carefully the hue was flipped. The pair is declared in
  the shared palette and swapped by the same helper.
- **`directionStyle()` emits all four**, which is why the value page
  gets the ink pair for free when something there needs it.

Checked on a threat value and a benign one, in both themes, on the
signals table and the bench: `+` is red on the first and green on the
second, and the inks follow. Asserted in the harness — the helper's
contract both ways, the ink pair's default, that no dark rule reaches
past the pair, and that a rendered editor carries one.

**What the value page could not be shown doing.** Its own verdict path
does not read this engine yet (§4.3 is phase 9's switch), so on this
instance it answers `UNKNOWN` for every value the editor scores and its
ledger draws no rows to colour. The helper and its consumers are
verified; the rendering is not, and cannot be until phase 9.

### 7.18 The contribution column catches up with the recompute

The bench recomputed on every change; the signals pane beside it did
not. Setting `per_org` from 7 to 40 moved the bench's quality from 27 to
48 and left the column reading `+7`, because `simulate` answers the
bench fragment alone and the editor swapped only `.wb-bench`. True since
the column existed, and colour made it worse rather than caused it: a
confidently red `+7` next to a quality that had just moved reads as a
number that means something.

The fragment now carries the new ledger back — the contributions, the
direction pair, the anchor line and the column's own labels — and the
editor writes them into cells addressed by signal id. **The labels
travel with it** because the column's wording is translated and a script
holding its own copy is a second place for it to be wrong. **No
arithmetic travels**, which is the rule this feature is built on: the
numbers arrive computed by the one engine that computes them.

Re-applying the direction pair is part of it rather than an extra.
Editing a weight can move the lean itself — drive one signal to `-40`
and the ledger sums against the lean it was anchored to, so the verdict
comes back `contested` — and the column has to repaint its swap and its
`+ toward …` line when that happens. Checked by doing exactly that.

A signal the instance does not implement is not addressed and not
repainted: *not counted* is a fact about the instance, and editing a
weight cannot change it.

### 7.19 The page says what a plus means, in plain words

The model this page rests on is genuinely surprising the first time —
**the lean is decided before any of these numbers exist**, by counting
how many organisations called the value a threat and how many called it
harmless, and only then is the ledger anchored to it. So on a benign
verdict the whole column is flipped, and a warninglist hit worth `-38`
on the *is it dangerous* scale arrives as `+38` of support for *benign*.

Two places said so and neither said it plainly. The signals blurb had
the exact-sum invariant and not the scale; the bench had *"the lean
anchors the ledger — every row is points × polarity and quality is that
anchored sum"*, which is precise and is also two phrases you have to
already know the model to parse. That is the wrong way round for the one
sentence whose job is teaching it.

Both are rewritten in the words a reader would ask the question in — *a
plus means it looks dangerous, a minus means it looks harmless; the
verdict is decided separately, and when it comes out benign the whole
column is flipped* — with one line on screen and the rest behind the
`i`, which is the length [`09b-revisions.md`](09b-revisions.md) §3.7
asked for and never got.

### 7.20 The numeric settings took a word

Every setting on the signals pane is a number — what a signal pays, the
days its config counts in — and every one of them was a `type="text"`
box. Typing `soon` into `recent_days` was accepted at the keystroke,
accepted by the recompute, and refused only by
`ValueSignalBase::checkMap()` on save, as a line at the top of a page
whose thirty-six other boxes look exactly the same. The refusal was
correct and arrived in the wrong place.

`field.ctp` draws `int` and `float` as `number` — stepping by one and by
anything — so the browser refuses the word as it is typed. Four things
came with it:

- **A stored value that is not a number is still drawn as text.** A
  `number` input shows nothing for a value it cannot parse, and a box
  that silently empties itself posts the key away on the next save.
- **The stepper is suppressed.** The chip gives a weight 3.4rem of
  monospace, and two arrows inside that leave room for one digit. The
  keyboard still steps.
- **A refusal in a closed pane opens it.** One section is on screen and
  the rest are `display: none`; a browser cannot report on a control it
  cannot show, so it blocks the save and says nothing at all. The first
  refusal of a validation pass now opens its own pane — the first,
  because the events arrive in tree order and the browser reports on
  that one.
- **The header's Save runs the checks at all.** `analystProfileSave()`
  called `form.submit()`, which skips constraint validation outright —
  so the page's main button would have posted a half-typed number that
  the Save inside a pane refuses. It calls `requestSubmit()`, which is
  the same post through the door the checks are behind.

The row the page builds when a map gains a key was the last text box.
It now reads the map's `value_type` and builds the control the server
would have drawn, which for `ttl_overrides` — a map of days — is a
`number`.

Three boxes stay text and are right to: `warninglist_category` on both
conflict rules is a category name, and `threat_share_at_least` takes a
fraction *or* the word `supermajority`. Both are `string` in their
`when_schema` and both draw as prose fields, without the monospace
right-aligned styling the numbers carry.

**Both of those were wrong, and §7.22 and §7.24 fix them.**
`warninglist_category` is a category name out of a set of two — a
select, not prose. And `threat_share_at_least` is not *a fraction or a
word* either: the word was never a value.

**Still open, same shape, different pane.** A map whose values are a
`select` — `org_trust`'s grades, `warninglist_category`'s two meanings
— has its existing rows drawn as selects and the row the page adds
drawn as a free-text box. The fix is the option list carried to the add
control the way `value_type` now is; it is the reference pane, not the
signals one, and it is not in this change.

### 7.21 The thresholds pane said what it does in its own language

Three complaints about one pane, and they share a cause: the copy was
written by somebody who already knew the model.

**The band inputs were drawn high first.** `09b-revisions.md` §3.10
asked for this and it had not landed: the strip above reads low →
medium → high and the boxes under it read *High from*, *Medium from*.
They are now in the strip's order, under a line that says what a band
even is — the quality score is a running total of points, and these two
numbers cut it into three.

**The thin-record clamp explained its own justification and not its
controls.** *Sources that still count as one* and *Sightings that still
count as none* are riddles: both name the threshold by what the record
is treated as rather than by what you are typing. They now read
**Reported by at most `1` organisations**, **Sighted at most `0` times**,
**Cap the band at `low`**, each with one line saying what falling above
it means. The blurb leads with the shape the clamp exists for — a value
piling up points while resting on a single reporter — gives the outcome
under the shipped numbers, and says the thing the reviewer asked for
outright: *the clamp only lowers the quality band; it leaves the lean
alone.* The old paragraph about weightings being unable to express this
is true, is the reason the clamp exists, and belongs in
`ValueVerdictTool::clamped()`'s docblock, where it already is.

**The ceiling could not be set to nothing.** The blurb ended *Delete the
three numbers to remove the clamp*, which the editor could not do:
`max_band` is a select over `BANDS` with no empty option, so a profile
that has no clamp showed `none` selected — the emptiest band, chosen by
nobody — and the next save wrote it. `clamped()` has always read an
absent `max_band` as no clamp; the select now offers **no cap** as a
blank option, `mergeAssoc()` drops the key on an empty post, and the
document goes back to having no clamp. Checked both ways: posting the
blank option removes `max_band`, and a thin record at 80 points then
reads `high` where the ceiling had held it at `low`.

**Two `i` tooltips are gone from the pane, not moved.** `block_fields`
and `section` hide everything after the first sentence behind an `i`,
which is the right default and was hiding two things that did not earn
it — a floating-point aside about 34-of-100, and half of the section
blurb. Both sentences are now one sentence each, so the pane has no `i`
left to hover. The mechanism is untouched; the other panes keep theirs.

### 7.22 `warninglist_category` is a set of two, drawn as a text box

*When it fires* on the conflict rules pane offered `warninglist_category`
as free text on both rules — a box you could type `banana` into, next to
a rule whose entire precondition is that the category resolves. §7.20
looked at the same two boxes and classed them with
`threat_share_at_least` as *prose fields, right to stay text*. Neither
deserved it, for different reasons — the second is §7.24 — and a
warninglist category is exactly two values and always has been — `warninglists.category` validates against
`['false_positive', 'known']` and nothing else can reach the column.

The schema already had the extension point. `generatedFields()` turns a
spec's `options` key into a select, so the fix is the `when_schema`
declaring what it accepts, and the vocabulary moves to one place:
`WarninglistCategory::CATEGORIES`, which the reference pane's override
map now reads too instead of rebuilding the pair from the constants.

**A select the server did not enforce would have been half a fix.**
`AnalystProfileFormTool::checkScalar()` has honoured `options` since it
was written, but that path validates *exclusions*; an escalation's
`when` goes through `ValueEscalationBase::checkValue()`, which checked
type and nothing else. So a document arriving by import — the path that
has no form in front of it — could still set a category nothing
resolves to, and the rule would be accepted and then never fire, which
is the quietest way for a conflict rule to be wrong. `checkValue()` now
refuses a value outside a declared `options`, naming the set.

**Two smaller things came with it.**

- **A chip's select sizes to its options.** The chip caps its control at
  `5rem` because free text is unbounded, and that truncated
  `false_positive` to `false_po`. A select's options are known, so it is
  as wide as its longest one.
- **And keeps its caret.** The chip flattens its control into the chip
  by zeroing the background, which took Bootstrap's arrow with it — a
  picker drawn exactly like the text boxes beside it. It is drawn again
  from `--bs-form-select-bg-img`, so the dark theme's lighter arrow
  follows for free. No chip rendered a select before this change, so
  neither was a regression; both were waiting for the first one.

**`04-lean-bands-harness.php` needed a `require_once`.** It stubs
`App::uses` to a no-op, so a rule naming `WarninglistCategory` in its
constructor failed to construct, and 13 escalation checks went quiet
rather than red. Worth recording as a property of the harness pattern:
a rule that cannot construct disappears from the catalogue rather than
raising, so a missing `require` reads as a behaviour change.

### 7.23 A text chip is as wide as what it holds

The chip pins its control to `3.4rem`, which is right for a weight and
wrong for anything you have to finish reading — `supermajority` read
`supermaj`, and a setting you cannot finish reading is one you have to
click into to check. §7.22 fixed the neighbouring box by making it a
select; this one cannot be a set, so it is sized instead. §7.24 then
emptied it, and the sizing is what lets the placeholder that replaced
it say something worth reading.

`size` is the attribute for this, and the box now carries the character
count of what it holds, bounded at both ends — 4, so a short value is
still a target, and 28, so a long stored string cannot push the table
out, with a `12rem` CSS backstop behind that. Numbers keep the fixed
width, which is what makes a column of them line up; only `type="text"`
is sized.

**It changes the chips and nothing else.** `.form-control` is 100% of
its cell in the section panes, so the attribute is inert there —
checked on relevance and enrichment, which are the two panes with text
fields in them.

**One thing the round-trip harness caught that a screenshot did not.**
`$shown` is an array for the `types` and `module_states` field kinds,
which draw their own controls and never reach the box being sized;
measuring it as a string raised *Array to string conversion* four times
per render. Rendering the page looked perfect throughout — the warnings
only surface with `debug` on, which the harness sets and the browser
does not.

### 7.24 The word `supermajority` was never a value

`threat_share_at_least` was a text box, and the question it invites is
*what else may I type in here*. The answer was worse than the label
said. `ValueEscalationBase::shareThreshold()` is

```php
$given = $this->when($config, $key, 'supermajority');
return is_numeric($given) ? (float)$given : <the profile's supermajority>;
```

— so **every** non-number means *follow the profile*. `supermajority`
was not a value the resolution recognised; it was the documented
spelling of *not a number*. `banana` did the same thing, silently, and
so did a typo of the word itself. An absent key did the same thing
again.

So the box is now a **number**, and the state that used to be spelled
with a word is the state a number box already has: empty. What an empty
one falls back to is written in the placeholder, named and resolved —
`supermajority · 0.66` — and the number comes from
`ValueLeanTool::supermajority()`, made public for this, rather than from
a second copy of its validity rule that would read `0.4` back to an
analyst the day somebody stored it.

**Declared, not special-cased.** The `when_schema` spec gains `follows`,
beside the `options` that §7.22 used, and the generic builder does the
rest: a stored non-number is drawn as empty, and the placeholder is
resolved from the document. One setting is followed and one resolver
answers for it, so the spec names the word rather than a path — the slot
for a second resolver is a guess until there is a second one.

**What it accepts now, and what it refuses.** A number, in `[0, 1]`,
checked as `is_numeric` rather than by PHP type because that is the test
the resolution applies — a document writing `"0.9"` is not wrong about
anything the engine reads. Or the word, still, so documents carrying it
validate. Everything else is refused and named. `75` for *75%* is
refused too, by `min`/`max` in the spec and `min`/`max` on the box, and
that one matters: in range it is a threshold no record can reach, so the
rule would simply never fire and never say why.

**A save drops the word, and the page says so first.** `legacyShapes()`
already exists for exactly this — a shim read that a save turns into a
write — and it gains a third note, driven off the schemas rather than a
list, so a second rule declaring `follows` is covered the day it is
dropped in. The shipped `default-v1.json` stops carrying the word for
the same reason.

**The round-trip check had to grow up.** It rendered the in-force
profile, posted it back and demanded byte-identity — which is the right
question only for a document already in the current shape. An instance
holding a legacy one now fails it for a reason that has nothing to do
with the round trip, so the section upgrades once, asserts the notice
was empty afterwards, and then asks about identity. The legacy section
builds the word deliberately, like it already builds the flat TTL map
and the old posture name. (Both posture names were withdrawn on
2026-09-12 — `09b-revisions.md` 3.21 — so what `legacyShapes()` says
about them now is that they are ignored and will be dropped, rather
than that they will be renamed.)

### 7.25 The exclusions pane printed the name of a hook

The same pass as §7.21–7.24, over the one pane they had not touched.
Most of it came back clean: all four exclusions already declare proper
types, and `dedupe_by` already declared `options`, so it was already a
select — it simply inherited §7.22's caret and sizing. There was no
free-text box and nothing to convert.

**What was wrong was the layer tag.** Every rule printed `applies at
row_filter`, `list_fold`, `condition` or `budget` — the names the code
calls the hooks it hangs on, and no answer at all to the question the
tag is there to answer: *does this move one list, or every count?* That
distinction is the surprising one. `orgs.own` contributes SQL to
`Value::conditionsFor()`, so it reaches every value-scoped aggregate at
once, where `feeds.mirrored` deduplicates one already-fetched list. The
tag now reads **applies to every count** / **rows** / **one list** /
**what is fetched**, with the mechanism behind a `title` — the pattern
the *trust weighted* tag beside it already uses, rather than a bare
`i`. The vocabulary is declared in `ValueExclusionTool::layers()`,
beside the layer each rule is assigned, for the same reason the schemas
are there: one kept in the view drifts the first time a rule moves.

**The numbers had no bounds.** `AnalystProfileFormTool::checkScalar()`
is the exclusions' validator and it honoured `options` and nothing else,
so `days: -5` was accepted — stored, read as a window nothing falls
inside, and silent about why. It honours `min`/`max` now, as
`ValueEscalationBase::checkValue()` already did, and the three numbers
declare them: hours and occurrences from zero, and a row-evidence window
from one day, because a window of no days is not a window.

**And one `i` went.** The section blurb hid *"every row an exclusion
removes is listed in the assessment as not counted, naming the rule"* —
the more useful of its two sentences — behind a hover. It is one
sentence now and the pane has no `i` left.

### 7.26 A row stayed greyed out after you switched it on

The palette dims a row whose switch is off — `.wb-tbl tr.is-off > td`
at `.62` — and the class was rendered from the **stored** value. So
ticking a disabled rule left it greyed out until a save, and
`orgs.own`, the one exclusion the shipped profile ships disabled, is
where anybody meets it.

Same shape as §7.18: the page showing a state the form no longer has.
`mark()` already runs on every change and already repaints the chips and
the rail's dirty glyphs; the row's own class was the one it did not
touch. It does now.

**A dimmed row is not always a switched-off row**, which is why the
state travels with it. `missing` — a rule this profile names and this
instance does not implement — is dimmed for a different reason, and it
is a fact about the instance that ticking a box cannot change, exactly
as §7.18's contribution column leaves `not counted` rows alone. The
`<tr>` carries `data-ap-state` and the script undims nothing else.

Checked by driving it rather than by reading it: the row goes
`opacity .62` → `1` on the tick and back on the untick with no reload, a
signal row dims when switched off and returns, and a row stamped
`missing` stays dimmed with its box ticked.

## 8. What 8c deliberately did not do

- **Suggestions under the bench's value box.** The box itself ships
  (§7.12): what it does not do is *complete* what is typed, because the
  endpoint that would answer that is the value page's own and pointing
  the editor at it is a wiring decision phase 9 should take. A value is
  benched by name; nothing offers to find it for you.
- **A drag to move a signal between ledger groups.** The mockup's
  `wb-grp-drop` is drawn; the control that ships is the select on the
  row, which does the same thing and needs no pointer.
- **Anything about `auto` or `max_age_hours`.** Both are declared and
  inert (D17, D19), and the editor draws them as what they are.
