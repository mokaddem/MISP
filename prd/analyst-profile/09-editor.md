# PRD: Analyst Profile — phase 8, the editor

**Built. 8a 2026-09-07 (§7d), 8b 2026-09-08
([`09b-decision.md`](09b-decision.md)) with a revision round closing
2026-09-11 ([`09b-revisions.md`](09b-revisions.md)), 8c 2026-09-11
([`09c-wiring.md`](09c-wiring.md)).** Depends on phase 1
([`02-store.md`](02-store.md)) for the actions and phases 2–7 for the sections
it edits.

## 1. What ships

The UI for owning a profile: an index, a viewer, a per-section editor, a
one-click fork, and — the part that makes the rest worth building — a
**simulator** that shows what a candidate profile would do to a real value
before it is saved.

### 1.1 Three passes, and why the order is that way

**Split 2026-09-07 into 8a, 8b and 8c**, because this is the first phase of
the corpus whose deliverable is a *look* rather than a computation, and the
two halves fail in opposite directions when they are built together.

| Pass | What it is | What it produces |
|---|---|---|
| **8a — the contract** — *built* | The controller, the ACL, the mechanics, the validation, and every action's REST representation. **No templates.** | `AnalystProfilesController`, `AnalystProfileFormTool`, `ValueVerdictDiffTool`, the ACL block, the view-model for every page, real JSON fixtures dumped from the dev instance, and the mockup frame and checker 8b draws into |
| **8b — the prototypes** | Three deliberately different designs for the same pages, as standalone HTML against 8a's fixtures. Nothing wired. | `mockups/`, published for comparison; one of them is picked and refined |
| **8c — the wiring** — *built* | The picked design implemented as `.ctp` templates against 8a's view-model, plus the links in from the verdict | `View/…/AnalystProfiles/` (six pages, seventeen elements), `analyst-profile.css` and `.js`, `value-palette.css`, the two verdict links, and three checks: a render harness, an HTTP probe and a browser check |

Two reasons for that order, and each is a failure this project has already
paid for once:

- **A prototype drawn against invented data is a prototype that cannot be
  built.** Every mockup in `prd/phase7/mockups/` was drawn before its data
  existed, and `value-profile-live/` is the long record of what that cost —
  seven tabs converted one at a time, each one discovering that the page was
  asking for a number no query could produce. 8a exists so that 8b's three
  candidates are drawn against `assess()`'s actual output, an actual
  `points_schema`, and an actual ledger diff, and so that "did I just break
  something" is answered by arithmetic rather than by a plausible-looking
  table.
- **Templates written before the design is picked are thrown away.** Two of
  three, by construction. So 8a stops at the seam: it computes what each page
  needs and can prove it over HTTP, and it renders none of it.

**8a is therefore verifiable with no design decisions made at all** — the
REST path 02-store.md §6 already owed is what carries every action's
verification, and it is a deliverable rather than scaffolding: an analyst
profile is one JSON document, `export`/`import` are the sharing path (§8), and
a profile the API cannot read is a profile no automation can review.

**8b's brief is its own document**, [`09b-prototypes.md`](09b-prototypes.md):
which pages each candidate must cover, the three directions they must be
distinct along, and how one gets picked.

**8b is executed cold, by one agent per candidate**, which is what makes its
three answers independent rather than three passes of the same hand. That
pushes two things into 8a that would otherwise have been 8b's: the **fixtures
have to be sufficient on their own** — an agent who needs the dev instance
running has been handed an incomplete brief — and the **frame and the checker
have to exist**, because building a frame means dumping a live MISP page and
adapting a checker that currently asserts the value page's nine tabs. So 8a
delivers `mockups/frame.html` and `mockups/check-mockup.sh` alongside the
fixtures, and 8b copies rather than builds.

## 2. Why the simulator is not a nice-to-have

MISP already learned this once. `DecayingModelController` ships
`decayingTool`, `decayingToolBasescore`, `decayingToolSimulation`,
`decayingToolRestSearch` and `decayingToolComputeSimulation`, with views under
`app/View/DecayingModel/` — an entire subsystem for answering *"what would this
model do to this attribute, right now, before I commit"*.

And decaying models still went unused. `00-discovery.md` records the stated
diagnosis — too complex, and no defaults. This feature fixes the defaults
(phase 1 §4) and removes the complex part (D7 deletes `base_score_config`), but
an analyst editing a weight from 7 to 9 still has no way to know whether that
was a good idea.

**The exact-sum invariant is what makes the answer renderable.**
`01-profile.md` §5.1 means a profile diff is not an opaque before/after —
change one weight and every affected row shows its old and new contribution,
and both columns still add up to their own total. That is a genuinely legible
diff, and it is only possible because nothing is normalised.

### 2.1 The regression set is the fixture the simulator wants

`185.234.219.24` (MALICIOUS 84), `8.8.8.8` (BENIGN 91), `45.155.205.233`
(MALICIOUS 93) and the conflicted value exercise every layout, every
disposition and — after phase 3 — every band boundary. The fifth case is the
median value (`03-signals.md` §7.4) — one org, no sightings — whose row
answers the corpus-wide question the four outliers cannot: *did my edit
quietly inflate every single-report value on the instance*. A simulator that
runs a candidate profile against all five and shows a five-row summary
answers *"did I just break something"* in one screen.

Plus one value of the analyst's choosing, because the interesting question is
usually about a value they are looking at.

### 2.2 The five values are this corpus's fixture, not the product's

**Decided 2026-09-07, building 8a.** `185.234.219.24`, `8.8.8.8`,
`45.155.205.233` and the median value are values *on the dev instance*.
Shipping them as the simulator's regression set would put four addresses
chosen for one database into every MISP install, where three of them are
absent and the fourth is a public resolver whose row says nothing about the
reader's own corpus.

So the product ships a **comparison set the analyst owns**: up to eight values
they pin, stored per user, seeded from the value they arrived from and added
to from the simulator itself. An empty set is an honest state with an
instruction in it — *"pin a value and its two columns appear here"* — not a
blank table.

> **Spec/build gap, recorded 2026-09-11** (`09b-revisions.md` 3.5).
> *"Seeded from the value they arrived from"* is not what 8a built, and
> the difference is visible to a new analyst. Arriving from a value
> **benches** it — `focus` is merged into the scored list for that
> request only — and does **not** pin it: `__setPinned()` is the only
> writer, and only `pin`/`unpin` call it. So a new analyst's set stays
> empty until they press Pin, and the empty state is what they see on
> their first visit rather than a one-row set.
>
> Both readings are defensible and neither is built by accident, so this
> is a decision owed rather than a bug: seeding on arrival makes the
> first simulation non-empty, and not seeding keeps the set something the
> analyst chose rather than something a page load did to them. The
> mockup draws what is built — the arrived-from row is labelled
> *benched, not pinned* and carries the Pin button — so the drawing and
> the code agree while the spec sentence does not.
>
> **Decided 2026-09-11, building 8c: the built behaviour stands, and
> this sentence is the one that was wrong.** A pinned set is a
> shortlist the analyst curates; a page load is not a decision, and a
> set that grows by being visited is a set nobody trusts to mean
> anything. The cost is that a first visit shows the empty state, and
> the empty state is drawn and says what pinning is for
> (`09c-wiring.md` §4.4).

What §2.1 asked for survives that, because the property it wanted was never
the specific addresses: it was **more than one value, spanning more than one
shape, scored in one screen**. An analyst who pins the four values they
actually argue about gets a better regression set than four hardcoded ones,
and this corpus keeps its five as the *harness's* fixture, which is where a
value chosen for its shape belongs.

The cost is stated rather than hidden: **a new analyst's first simulation has
one row in it**, the value they came from, and nothing on the page can tell
them whether their edit moved every single-report value on the instance. That
is the question §2.1's median case answers, and answering it in the product
needs a corpus-wide sweep — which is phase 10's materialisation, not a render.
Named here as the gap rather than papered over with four addresses that would
not have answered it either.

## 3. Pages

| Action | What it renders | Built in |
|---|---|---|
| `index` | The profile in force (badged), the analyst's own, their org's, and the instance default. Each row: name, owner, enabled, version, and whether it is editable | 8a view-model, 8c page |
| `view/:id` | Read-only, section by section. Takes the simulator's optional `?value=` parameter (§5) to show each weight beside the contribution it produced on that value; without one, the contribution column stays empty rather than inventing a value | 8a view-model, 8c page |
| `edit/:id` | Per-section forms; see §4 | 8a accepts the POST, 8c draws the forms |
| `fork/:id` | One POST, no form. Lands on `edit` of the copy | 8a whole, 8c the confirm |
| `simulate/:id` | §5 | 8a the diff, 8c the page |
| `export/:id` | The JSON | 8a whole |
| `import` | §8 — the sharing path | 8a accepts it, 8c the form |
| `enable/:id`, `disable/:id`, `delete/:id` | 02-store.md §6 | 8a whole, 8c the confirms |
| `update` | `updateDefaults()`, site admin only | 8a whole |

**There is no `add`, decided 2026-09-07 while building 8a.** 02-store.md §6
lists one; D5 and §6 there make a fork *the* way in, on the argument that the
shipped default is uneditable by an ordinary analyst — so a blank-slate form
is a second entrance to a room with one door, and the document it would create
is an empty `parameters` that scores nothing and names no signal. A new
profile is a fork of one that already works, or an `import` of somebody
else's. This deletes a form from 8b's brief rather than adding one.

**`index` names the profile in force first and unmistakably.** Under D3 exactly
one applies, and the commonest confusion this feature can create is an analyst
editing a profile that is not the one weighting their pages — because they made
a fork, forgot, and their org profile still wins, or because their own is
disabled.

**So every row carries its own standing**, which is 8a's answer to that
confusion rather than 8c's: `in_force`, `disabled`, `overridden` — *naming the
profile that beat it* — `other_owner` for a row a site admin can see but which
could never apply to them, and `unresolved` for the shape resolution cannot
produce. A badge on one row cannot say *why* the fork you are editing is not
the one weighting your pages; a per-row standing can, and it is computed
rather than drawn.

## 4. Editing, section by section

**Under D12 the signal palette is a directory read, not a list in the view.**
Adding a signal to a profile means picking from what the loader discovered —
`app/Model/ValueSignals/` plus whatever the admin dropped in
`app/Lib/ValueSignals/` — the way the decaying-model form's formula dropdown
is already `DecayingModel::listAvailableFormulas()`. Three consequences for
this phase:

- **A custom signal is marked as one.** `Workflow` sets `is_custom` on a
  module loaded from `app/Lib/`; the palette shows the same badge, because
  "this instance computes something upstream does not" is exactly what a
  reader of a shared profile needs to know.
- **The form for a signal's points is generated**, from the `points_schema`
  the class declares (`03-signals.md` §8.2). Without it a dropped-in signal
  would have to be configured by hand-editing JSON, which makes the drop-in
  half a feature.
- **The loader's error list is on screen.** A file with a syntax error, a
  class that is not a `ValueSignalBase`, a colliding id — all are skipped
  silently by the engine (`03-signals.md` §8.5), so this is the page where an
  admin finds out. `Workflow` surfaces `$error_while_loading` the same way.

**Seven sections, not six.** This section was written before phase 5, which
added `relevance` — the clock, the curve, the aging fraction, the uncertainty
lag and the per-type TTL table (`06-staleness.md` §3). It is the section with
the largest number of editable numbers in it and the only one holding a
per-attribute-type map that an analyst genuinely re-tunes, so leaving it to
the raw JSON editor would have made the second axis the one axis nobody can
adjust. Counted 2026-09-07 against the shipped default: `format`, `signals`,
`thresholds`, `escalations`, `exclusions`, `relevance`, `reference`,
`enrichment` — seven editable sections plus the format marker.

Seven sections with genuinely different shapes (D2), so seven treatments rather
than one JSON textarea. Though **a raw JSON editor is also offered**, because
every profile is one document and an analyst who wants to paste one should be
able to — with validation on save (phase 1 §3).

**`signals`** — a table, one row per signal: enabled and its `points`
fields, under the heading of the ledger group the signal declares. The
group is a heading, not a control — `09c-wiring.md` §7.13 says why it has
no select. The points columns differ per signal, which the table has to tolerate;
`points` has no fixed schema by design (`03-signals.md` §3). Each row links to
its implementation's description of what it reads and what its keys mean,
because `per_org` and `cap` are not self-explanatory. There is no editorial
band column — D16 removed the field, so *band* on this page means the quality
band and nothing else, and what a signal is worth in principle is the `cap`
beside it, in points.

**`thresholds`** — the lean supermajority and the quality bands (D11), with
the band boundaries drawn as a strip against the attainable range under the
enabled catalogue, so `medium` above `high` — or a band beyond what the
signals can reach — is visibly wrong rather than silently saved.

**`escalations`** — a list with enable toggles. v1 ships one, so this is a
checkbox and its prose.

**`exclusions`** — a list with enable toggles and one parameter each. The four
ids are a closed set in code (`ValueExclusionTool` §"the set is closed"), so
unlike signals and escalations this palette is *not* a directory read, and the
editor must carry a declaration of the four and their one parameter each.
Built in 8a as `ValueExclusionTool::catalogue()` rather than in the editor, so
that the knowledge stays with the mechanism that applies it — the same
argument that put `points_schema` on the signal rather than in the form.

**`relevance`** — the clock (a select over the declared clocks), the decay
speed, the aging fraction, the uncertainty lag, and the per-type TTL table:
a `default` plus one row per MISP attribute type the analyst has an opinion
about, with a type picker to add more. Never a row per type MISP has — the
same posture `reference` takes over organisations, and for the same reason.

**`reference`** — two maps, and the only section that needs real UI work.
`org_trust` is an org picker plus a grade select, showing only graded orgs with
a search to add more — never a list of every organisation on the instance.
`warninglist_category` is the same shape over warninglists, defaulting to a
list of the ones this profile overrides.

**`enrichment`** — per-type module checkboxes, drawn from
`Module::getEnabledModules()` so the list is what the instance actually offers,
with `08-enrichment.md` §2.1's two honest states rendered inline.

### 4.1 Revision, and what bumps it

`revision` increments on any change to `parameters`. Not on renaming the
profile, not on enable/disable. `version` is a different counter — it tracks
the shipped file, and only `updateDefaults()` moves it (`02-store.md` §2,
decided 2026-09-03 per `review-2026-09-02.md` B1).

The reason is `01-profile.md` §5.5 and phase 10: the materialised instance
assessment is keyed by `(value, profile_uuid, revision)`, and a counter that
moves for a cosmetic reason invalidates every stored row for nothing — while
one that doubles as the upstream match key breaks the default's update path.
Worth getting right now, while it costs one condition.

## 5. The simulator

```
simulate/:id?value=<b64>
```

Renders, side by side:

- **The verdict under the profile in force** — the analyst's current reality.
- **The verdict under the candidate** — the profile being edited, including
  unsaved changes posted with the request.

And underneath, the diff that matters: **one row per ledger row, with both
contributions and the delta**, rows that appeared or vanished marked as such,
and both totals. Because of §2, the two columns each sum to their own score, so
the diff is arithmetic rather than impressionistic.

Then the analyst's comparison set as a compact summary (§2.2): lean, quality
and band under each profile, one row per pinned value, so a change that
quietly turned a known-benign value malicious is visible without one page load
per value.

**The candidate can change the context, not only the score**, which is the one
place the simulator is more than two calls to `assess()`. `exclusions` is
applied by the context builder — `orgs.own` is a query predicate in
`Value::conditionsFor()` (`05-exclusions.md` §7.1) — so two profiles whose
exclusion sections differ are two different sets of rows, and scoring both
from one context would show a diff that no saved profile could reproduce. The
context is therefore built per *exclusion plan* and reused when the two
profiles agree, which is the common case of editing a weight: one build, two
scorings. Measured in 8a rather than assumed.

**It computes, it does not save.** The candidate profile is posted as JSON and
scored in memory. Nothing is written, which means the simulator works on a
profile the analyst cannot even edit — including the instance default, which is
how someone decides whether they need a fork at all.

### 5.1 Reachable from the verdict itself

`01-profile.md` §6 lists two render sites that name the profile in plain text
today: `value_verdict_meta.ctp:40` and `value_verdict_card.ctp:56`. Both become
links — to `view/:id` for a profile the analyst cannot edit, to `edit/:id` for
one they can.

And every `policy` entry in `not_counted` links to the exclusion that produced
it (`05-exclusions.md` §2.1), which turns *"why doesn't this count?"* into a
click. That is the single best justification for having split `not_counted`.

## 6. Fork, in the UI

One button, on `view` of any profile the analyst cannot edit. No form: name
defaults to `<source> (copy)`, owner is the analyst, and it lands on `edit` of
the result.

**A user may hold one enabled profile** (phase 1 §5). Forking with one already
present offers *"replace your current profile"* or *"cancel"*, naming the
existing one. It does not silently create a second and disable the first.

**Replace disables; it never deletes** (decided 2026-09-03,
`review-2026-09-02.md` C-series). The existing profile is an analyst's tuned
judgement, and a one-click flow must not be able to destroy it. The old
profile stays in the index, disabled; the fork is created enabled. Enabling a
disabled profile later runs the same swap in reverse — the one-enabled
invariant means enabling one disables the other, and the confirm says which.
Deletion stays where it is: an explicit action on the index, never a side
effect.

An analyst with `perm_admin` also gets *"fork to my organisation"*, which under
D3 applies to every colleague who has not forked — so that button confirms,
naming the number of users it will affect.

## 7. Verification

Split across the three passes, because 8a can assert most of this list with
no page to look at and 8c should not be re-asserting what 8a already proved.

### 7a. The contract — no templates, no design decisions

1. `parallel-lint`, then `queryACL/findMissingFunctionNames` — every new action
   has an ACL entry, and no entry names an action that does not exist.
2. `index` as four users (own profile / org profile / neither / site admin):
   the profile in force is correct in each, and every other row carries the
   standing that says why it is not (§3).
3. Fork the default as a non-admin over REST, change one weight, save, then
   re-score a value: the assessment names the new profile and the quality has
   moved. **The whole of item 3 below except the page.**
4. Diff a profile against itself on every harness value: every row's delta is
   zero, no row is marked appeared or vanished, and both totals are equal.
5. Diff a candidate with one weight changed: exactly the rows that signal
   touches carry a non-zero delta, and **each column still sums to its own
   quality exactly** — the invariant §2 rests on, asserted rather than
   assumed.
6. A candidate that disables a signal: its row is marked vanished, not
   silently absent, and the totals differ by that row's old contribution.
7. Simulate a profile the analyst cannot edit, including the instance default:
   it computes and writes nothing — asserted by reading `revision` and
   `modified` back.
8. `quality_bands.medium` above `quality_bands.high`, or a band beyond the
   enabled catalogue's attainable sum: rejected on save, with the bound and
   how it was derived in the message.
9. A malformed JSON paste: rejected, with the parse error and its line, and
   the stored profile byte-identical afterwards.
10. Rename a profile: `revision` does not move, and neither does `version`.
    Change a weight: `revision` moves by one and `version` does not.
11. Fork while holding an enabled profile: refused with the existing one
    named, and the replace path disables rather than deletes it (§6).
12. Two profiles whose `exclusions` differ: the simulator builds two contexts,
    and the row counts they see differ. One build when they agree.

### 7b. The prototypes

Owned by [`09b-prototypes.md`](09b-prototypes.md) §6 — the kit assertions,
both themes, and the coverage each candidate has to demonstrate.

### 7c. The wiring

**Done 2026-09-11**, item by item, in
[`09c-wiring.md`](09c-wiring.md) §7.10. Item 5 is the exception and is
deferred to phase 9 with the reason in §4.3 there: it asks for a fork
made from the value page to move that page's score, and the value page
does not read the engine yet.

1. Every page renders in both themes, with `--vp-mal` asserted to resolve
   before anything else is asserted (`prd/phase7/README.md`'s first trap).
2. The diff table's delta column is the one place a red/green pair is doing
   real work and needs checking against `--vp-dir-with` / `--vp-dir-against`
   rather than raw Bootstrap colours.
3. An empty diff renders as *"no change"*, not as a blank table. An empty
   comparison set renders as its instruction, not as a blank table.
4. The generated form for a signal round-trips: render, save unchanged, and
   the stored `points` map is byte-identical — the property that stops a form
   silently rewriting a document it did not understand.
5. Fork the default from the value page, change one weight, reload the value
   page: the hero names the new profile and the score has moved. The item 3
   above that 7a could not reach.
6. The loader's error list is on screen where an admin will see it.
7. A `policy` entry in `not_counted` links to the exclusion that produced it
   (§5.1).

## 7d. What 8a found, built 2026-09-07

**Verified by 77 checks with no database
([`09-editor-harness.php`](09-editor-harness.php)), 44 against the dev
instance ([`09a-contract-live-probe.php`](09a-contract-live-probe.php),
run three times to prove it leaves the instance as it found it), 35 over
HTTP ([`09a-contract-http-probe.sh`](09a-contract-http-probe.sh), run
twice), and 27 over the fixtures
([`09a-fixtures-check.py`](09a-fixtures-check.py)).** All eight of the
corpus's harnesses still pass — 570 checks — which is phase 6 §7.7's
lesson held to: a change to shared code can kill an earlier harness
silently.

Eight findings. Four are defects in code that shipped in earlier phases
and were invisible until something read it from a new direction.

### 7d.1 `evidence.window` ignored its own `enabled` flag

Every other exclusion has honoured it since phase 4 —
`ValueExclusionTool::entries()` drops a disabled rule — and this one did
not, because `ValueProfile::verdictBudget()` reads the raw section rather
than going through the plan. So the window applied whatever the profile
said, and **the editor was about to draw a toggle with nothing behind
it**. Found by reading the two paths side by side while building the
exclusions form, which is the kind of thing only writing a form makes
you do.

### 7d.2 Every refusal answered HTTP 200

`refuse()` set 400 on `$this->response` and handed the body to
`RestResponse->viewData()` — which ignores it: `prepareResponse()` builds
a fresh `CakeResponse` with the code it was *passed*, and `viewData()`
always passes 200. So a rejected save answered *200 with `saved:
false`*, which tells an automated caller the save happened and leaves
the truth in the body. Now through MISP's own `saveFailResponse()`, which
answers **403** — semantically odd for a validation failure, and what
every failed save in MISP has answered for years, so a client that
already handles it needs no special case here.

### 7d.3 `resolveFor()`'s cache went stale across a swap

The resolution cache is memoised per request because the value page
calls it once per panel and there are twenty-seven of them. That is a
read-only assumption, and the editor breaks it: enabling one profile
disables another *in the same request*, and the response then has to say
which is in force. `forkProfile()` cleared the cache by hand;
`saveField('enabled', …)` did not, so **the swap's own answer came back
from before the swap**. Now cleared in `afterSave()` and `afterDelete()`,
because a cache whose correctness depends on every future caller
remembering is a cache that will be wrong.

### 7d.4 A fork of the default claimed to *be* the default

`forkProfile()` copied the description verbatim, and the shipped
default's description opens *"The instance default Analyst Profile…"* —
true of the source and false of the copy, on the one field a colleague
reads to decide whether to adopt it. It now leads with a dated line
saying where the document came from. **Not lineage**: D5 forbids a
tracked pointer, and a sentence is a changelog line — nothing resolves
through it, and it stays true if the source is renamed or deleted.

### 7d.5 The ACL check was reporting 39 false positives

`ACLComponent::findMissingFunctionNames()` reads controller files with a
regex and treats every method whose name does not begin with an
underscore as an action needing an entry. Seventy-eight of MISP's eighty
controllers are clean; the two that were not were **this feature's** —
`ValuesController` with 10 and the new one with 29 — so the tool that
02-store.md §6 deferred phase 1's ACL work *to* was useless the moment
it was needed. Both controllers now follow the convention, and the check
returns `[]`.

That also moved `encodeValue`/`decodeValue` out of `ValuesController`
into `ValueUrlTool`: a **public** method named `__decodeValue` reads as
private while being cross-class API, which is a wart the check's
convention creates rather than one anybody chose. Out of the controller
the question does not arise, and there is still one implementation of
the alphabet decision instead of two.

**And the check only reports one direction.** Nothing in MISP catches an
ACL entry naming an action that does not exist — the dead row phase 1
refused to ship — so the live probe asserts it, by reading the
controller's public methods and comparing both ways.

### 7d.6 The index board was unreachable except over HTTP

The per-row standing (§3) is the answer to the confusion this feature
can most easily create, and it started life in a private controller
method — so the one thing 8c most needs to get right could only be
checked by looking at a page. It moved to `AnalystProfile::indexFor()`,
which is where it belonged anyway: *"why is this not the one weighting
my pages"* is an ownership question, made out of exactly what
`resolveFor()` and `isEditableByCurrentUser()` already decide. The probe
now reads the board as all five of the instance's users.

### 7d.7 A weighting is invisible past a cap — again

The fixture dump's first attempt produced a diff **with no changed row
in it**. The candidate edit lowered `per_org` from 7 to 4 on a value
eight organisations report, and both products exceed the cap of 28, so
the row did not move. Phase 6 §7.1 recorded the same thing from the
other side; here it means the edit that demonstrates a *changed* row has
to move the cap, and — more usefully — that **a design promising "change
a weight and watch the row move" will be wrong for some signals some of
the time**. The honest promise is *change a number and the diff shows
what actually happened*.

The same run found two other gaps: nothing could *appear* in a candidate
while the profile in force runs every signal, so the fixture's in-force
side now has one switched off; and the `custom` badge had nowhere to come
from on an instance with an empty drop-in directory. Both are recorded
in the fixture's own `synthesised` block — dropping a PHP file into
`app/Lib/ValueSignals` to make a picture look right would be changing
the product for a mockup.

### 7d.8 The attainable bound needed a rule, and nearly got the wrong one

§7a item 8 refuses a band beyond what the enabled catalogue can reach,
which needs a number. The rule is *the largest positive value in a
signal's `points` map*, and it works only because a `cap` is always the
largest positive term where one is declared — checked against all eleven
shipped signals.

The first implementation treated any key beginning `per` **or `scale`**
as paid-per-unit and therefore unbounded. `reporting.published_ratio`
pays `scale × published / events`, where the multiplicand is at most 1,
so `scale` *is* its maximum — and flagging it made the bound
"unreliable" for the shipped default, which would have downgraded item
8's refusal to an advisory on **every instance**. A declared maximum on
the signal class was the alternative and was rejected for D14's reason
in a new place: a number an author maintains by hand duplicates their
own arithmetic and drifts from it silently, where a derived one cannot.

What remains stated rather than solved: a custom signal paying per unit
with no cap is not bounded by its own points, and the bound
under-reports it. So `attainable()` returns `unbounded` beside the total
and the refusal becomes a warning when it is non-empty — a derived
number that quietly rejects a legitimate edit is worse than one that
says where it stops being reliable.

### 7d.9 Smaller things worth the line

- **The spec said six sections and there are seven.** §4 was written
  before phase 5 added `relevance`, which holds the most editable
  numbers of any section and the only per-type map an analyst really
  re-tunes. Leaving it to the raw JSON editor would have made the second
  axis the one axis nobody can adjust.
- **`corroboration.*_days` are day-keyed maps of reports, not counts.**
  The harness's first fixture made them integers, on the strength of
  `ValueSignalBase`'s key list, and `ValueRelevanceTool` warned on a
  `foreach` over an int while **every assertion still passed** — because
  relevance emits no ledger row and the diff assertions are about the
  ledger. The docblock now names the shape.
- **The live probe's own cleanup was the last thing to fail.** Its
  second fork displaced the *first* fork, which cleanup then deleted, and
  it tried to re-enable a row that no longer existed — CakePHP reads that
  as an insert and it died on the missing uuid, after every other
  assertion had passed. Exactly the failure the *run it twice* convention
  exists to catch.
- **The fixtures cannot be written from inside the container.** Only
  parts of `app/` are mounted from the working tree, so the dump's file
  writes landed in the image and it reported five fixtures written to a
  directory that stayed empty. It prints to stdout now — and
  `app/webroot/` would have been the wrong fix twice over, since these
  documents carry indicator values and anything under webroot is served.
- **`relevance` read `uncertain · uncertain`.** The axis carries
  uncertainty as a flag *and* has `uncertain` as a state in its own
  right; appending the flag unconditionally handed a design the same
  word twice and left it to decide what that meant.

### 7d.10 What 8a deliberately did not do

- **No templates**, which is the whole point of the split. A browser
  gets JSON from every action today.
- **No side-menu entry.** `side_menu.ctp` is a template, so the
  navigation case belongs to 8c with the pages it navigates to.
- **No `add` action** (§3), which deletes a form from 8b's brief rather
  than adding one.
- **`enrichment.max_age_hours` stays inert** and the view-model says so
  with an `inert` flag, because there is still no store of module
  answers for a window to govern. Carried, drawn, and labelled — the
  posture phase 7 took, held here rather than quietly dropped.

## 7e. What reading the relevance section back found, 2026-09-11

Three findings, all of them on the one section whose settings are
shapes rather than quantities — and all three the same failure, which
is that the section explains its numbers everywhere except where they
take effect.

### 7e.1 The curve was the one thing an edit did not move

`refresh()` swaps `.wb-bench` and nothing else, and the TTL curve lives
in the relevance section, outside it. So moving **Decay speed** — the
one field on this page whose entire output is a shape — recomputed
every number around the figure and left the figure drawn from the
*saved* document. A straight line, while the box above it said `0.3`.

Measured rather than argued: `figure.ttl-curve.closest('.wb-bench')` is
null.

**The fix keeps the arithmetic on the server.** `simulate()` renders
`ttl_curve` from the posted document into a hidden carrier inside the
bench fragment, and `repaintCurve()` lifts it out and drops it where it
lives. No second endpoint — that would be a request per keystroke for a
figure this response regenerates anyway — and no polynomial in
JavaScript, which is the rule `analyst-profile.js` opens by stating and
the one this feature exists to keep. §4.2's *evidence against remaining
shelf life* is only worth drawing if the drawing answers the control.

`ValueRelevanceTool::agingElapsed()` / `agingDay()` are new, and the
template calls them rather than carrying its own copy of the inverse
curve: the mark on the figure and any sentence naming the day it falls
on have to agree, and two implementations is how they stop.

### 7e.2 `Aging from` read as a date and took a fraction

`Aging from` over a box holding `0.33`, helped by *the share of the TTL
left when a value stops reading as current* — which gives the units and
never says what the number does. The label is the sentence now:
**Aging starts with this much left**, with `of the shelf life` as the
unit the other numeric fields already carry.

**The day it works out to is on the figure, not in the help.** It is
what a reader actually wants, and it is not day 30 of 90 either — the
decay speed bends the curve between the fraction and the day, which is
exactly why it is worth printing. But the field's help renders once
with the section while the figure now redraws on every edit, so a day
printed under the box would be right until the first keystroke and then
argue with the mark it describes. The figure's own note carries it:
*Aging starts on day 60, with 0.33 of the shelf life left.*

### 7e.3 The lag was measured, compared, and never shown

The section's next field is **Encoding lag before uncertain**, a
threshold in days. The reading it is compared against sat in a `title`
on the bench's runway line, under the words *the timeline is uncertain,
so the elapsed time is a lower bound* — a verdict with its measurement
hidden, beside the box for choosing where the verdict falls. A
threshold cannot be set from a pane that only ever shows the number
once it has already been exceeded.

`runway.ctp` prints the measurement now — *timeline uncertain — no
first-seen date on any occurrence · added 302 days after its event's
date* — and, when a lag exists but did not trip, says so with the limit
for company: *added 18 days after its event's date, inside the 30-day
limit*. Both read live off `precision`, so moving the threshold
restates them without a save.

### 7e.4 `shelf life` was ours; `Lifetime` is MISP's

The docs call the quantity **shelf life** and it reads well, which is
why it reached the screen — but it is a metaphor this feature invented,
and a reader hitting it in the editor has nothing to check it against.
MISP already ships the same quantity under its own name: a decaying
model's parameters are `lifetime` and `decay_speed`, and the form at
`/decayingModels/add` labels the first **Lifetime (days)**.

This section had already borrowed `decay_speed` from that pair and then
renamed its twin. **Every user-facing string says `Lifetime` now** — the
editor's block titles, the value page's rail card and its panel
registration, the sightings chart's dataset and axis, the lifecycle
line. The prose in these documents keeps *shelf life*, because it reads
better in a paragraph and nobody is trying to look it up.

Two strings survived the first pass and are worth naming, because both
were the abbreviation rather than the metaphor: `TTL from ip-dst` on the
runway line and `N days elapsed of a 90 day TTL` in two track tooltips.
An acronym is not a standard term just because it is short.

### 7e.5 The pickers offered constants, and `Aging from` was a false claim

Two findings that are the same finding.

**The selects printed their stored keys.** `Measure from` offered
`last_independent_corroborat…` — a constant with its end cut off by the
column width — and `A value with several types` offered `shortest`,
which does not say shortest *what*. `field.ctp` had supported
`{value, label}` options since it was written and nothing had ever
passed one. They read as answers to their labels now (*Somebody else
confirming it*; *Take the shortest lifetime*), the read-only viewer
resolves the same labels rather than printing the key, and `type_rule`
gained the help explaining why `shortest` is the cautious default —
§3.4's reasoning, which existed only in this document.

`ValueRelevanceTool::CLOCKS` and `TYPE_RULES` are untouched: they are
validation lists, the document still stores the key, and the harness
now checks `array_column($options, 'value')` against them. That is what
*the editor speaks the engine's vocabulary* always meant — a picker that
cannot store something the engine will not read — and not that the
picker had to show the constant.

**`Aging from` was worse than unclear; it was untrue.** The first fix
made it *Aging starts with this much left*, which states that aging
begins at that point. It does not. A value loses relevance continuously
from the moment its clock last reset — that is what makes the curve a
curve — and nothing at all happens to the value at `0.33`. It is where
the *page* stops calling it `current` and starts calling it `aging`: a
labelling threshold, not an event.

**Call it aging below**, with `of the lifetime left` as its unit. The
figure says the rest, and says it in the order that answers the
question: *It loses relevance from day zero. Day 60 is only where it
stops counting as current, with 0.33 of the lifetime left.* The
`where aging begins` tooltip on both track marks was the same claim and
is now `where it stops counting as current`.

The lesson generalises past this field: **a threshold named for the
thing it bounds reads as the moment that thing begins.** The relevance
axis has three of them and this was the only one whose name made a
claim about the value rather than about the label.

### 7e.6 The figure drew three states and named one

The follow-up question to §7e.5 was *"by aging, do you mean expired?"* —
and the honest answer is that the figure gave no way to tell. It marked
day 60 `aging`, ran the plot to the lifetime, and put a bare `90` on the
tick. One labelled threshold, so `aging` read as the end of the line.

`stateFor()` has had three for as long as it has existed:

```
current   runway ≥ aging_fraction        day 0  → day 60
aging     0 < runway < aging_fraction    day 60 → day 90
expired   elapsed ≥ ttl                  day 90 →
```

`aging` is a **warning band inside the lifetime** — the stretch where
this profile would rather you re-checked, with the value still live —
and `expired` is the hard edge at the end of it. That distinction is
the reason the fraction is a setting at all; a profile that only had
`expired` would need no `aging_fraction`.

**The x-axis runs to 1.25× the lifetime now.** `expired` begins *at* the
lifetime, so an axis stopping there has nowhere to put the word, which
is precisely how the figure came to show two states and name one. The
curve stays flat on zero out there — the statement that nothing comes
back — and the three stretches are shaded and named: plain ground,
amber, grey. Both thresholds carry their day, where the old figure left
the second as an unexplained tick.

Two layout rules, because a fraction near 0 or 1 collapses a band:

- a band narrower than its own word goes unlabelled rather than
  printing over its neighbour (`room`, at 4.2px per character);
- the aging day is dropped when it would land on the y-axis or crowd
  the lifetime's — the shading and the sentence still carry it, and two
  strings in the same 24 pixels carry nothing.

Verified at `aging_fraction` 0.9 with `decay_speed` 2.5, which squeezes
`current` to one pixel: the remaining two stay legible and nothing
overlaps.

The help's first sentence is the only one the pane shows, so it is now
the answer to the question rather than the units: *Aging is the band
between current and expired, not the end of the lifetime.* Units come
second — a reader who has the states wrong is not helped by getting the
units right.

### 7e.7 Four numeric settings, no bounds, one with no validator at all

Reported as *"this lag input accepts negative numbers"*, and the box was
the smaller half.

**`undated_assumed_days` had no server validator** — and neither did
`lag_uncertain_days` before it, so the gap predates the rename by the
whole life of the setting. Every numeric neighbour has one:
`decay_speed` must be above zero, `aging_fraction` between 0 and 1,
`ttl_default` and each bucket a whole number of days above zero. This
one took `-5`, took a word, and saved. Nothing broke, because the engine
clamps the assumption at zero — the field simply displayed a number that
did nothing, which is the failure mode a form exists to prevent.

Now refused with a reason, and **zero is explicitly allowed**: assuming
nothing is an answer, so the bound is `>= 0` rather than `> 0`. The
retired key is validated too, since a fork still stores it and the
engine still reads it.

**`field.ctp` has supported `min`/`max` since it was written and no
field had ever declared one.** Dead code in a renderer is a fix nobody
has to build; four declarations turn it on:

| field | bound |
|---|---|
| `undated_assumed_days` | `min 0` — zero is *assume nothing*, negative would read a value as younger than its own record |
| `aging_fraction` | `min 0`, `max 1` |
| `ttl_default`, each bucket, each override | `min 1` |
| `decay_speed` | `min 0` — `min` is inclusive so it cannot say *above zero*; the server still refuses exactly 0 |

Verified in the browser: typing `-5` now yields *"Value must be greater
than or equal to 0."* before anything is posted.

### 7e.8 The buckets and their types were two blocks saying one thing

The pane printed every day count twice. **Lifetime** was five number
boxes in a column — short, medium, long, very long, every other type —
and **Which types go in which bucket** was a table underneath whose key
column read `Short — 90 days`, `Medium — 120 days`, four hundred pixels
from the box that decides the 90. Change a bucket's length and the
label repeating it only catches up on the next save.

A bucket is a **length and a membership**, and neither half is legible
without the other: the question an analyst is answering is *how long do
I keep an IP address*, which the old pane split across two headings.

**One table now, one row per bucket:**

| Bucket | Days | Attribute types |
|---|---|---|
| Short | `90` | `ip-dst ×` `ip-src ×` `+ add types` |
| … | | |
| Every other type | `180` | everything not named above |

The default is the last row rather than a field of its own — it is a
lifetime with no bucket, so the table becomes the whole answer to *how
long does this instance keep a type*. Nothing is assigned to it by
hand, so its types cell states rather than takes (`note` on the entry).

**No new block kind and no new field type.** A `map` entry may carry a
`key_field` — a setting on the key itself — and `block_map.ctp` draws a
column for it when the block names one in `key_field_label`. Every POST
name is what it was: the days still post to
`relevance.ttl_buckets.<bucket>` and `relevance.ttl_default`, the chips
still post type-first under `relevance.ttl_types` with `__present`
marking the map as drawn (§7.6 of `09c-wiring.md`). **A layout change,
not a schema change** — which is what the harness asserts, rather than
asserting the layout.

**The curve wraps on its own width, not the window's.** It sits beside
the table, and the pane here is the window minus a rail and, above some
widths, minus a 468px bench — so it is *narrower* at 1440 than at 1280.
A viewport media query would put the curve beside a 320px table on
exactly the screens with the least room for it. A flex basis is the
measurement that matters: below it the curve takes the line under the
table and the chips get the whole pane. Measured at eight widths from
1280 to 2560; every bucket is one 44px row at all of them but 1440,
where `medium`'s three chips wrap once.

Verified in the browser: editing `short` to 45 redraws the figure's
caption (the recompute already carried it, §7e.1), adding and dropping
chips moves the right types, and a save writes `ttl_buckets.short`,
`ttl_default` and both `ttl_types` edits — then the same pane put every
one of them back.

## 7f. What reading the reference section back found, 2026-09-12

One finding, in the one place §4 called *the only section that needs
real UI work*: **grading an organisation was free text at both ends.**

### 7f.1 *Grade an organisation* meant *type a uuid*

The `org_trust` block declared everything it needed. The entries carried
`type: select` with the seven grades, the `add` descriptor carried
`search: true` and `source: 'orgs'`, and §4 had asked for "an org picker
plus a grade select … with a search to add more". None of it reached the
page:

- **`block_map.ctp` drew the search box and nothing behind it.** Its add
  control branched on `add.options`, and `__gradedOrgs()` answers *only
  the organisations this profile already grades* — deliberately, so a
  response about four organisations does not carry nine hundred. So the
  unused-key list for `org_trust` is always empty, always took the
  fallback branch, and the fallback branch is an `<input type="search">`
  whose value becomes the map key. The key is `organisations.uuid`
  (§2.2). The control asked the analyst to type a uuid from memory.
- **`search: true` was dead metadata.** Nothing read it. Warninglists
  set it too and never noticed, because their catalogue is small enough
  to send whole, so they take the select branch and the flag is ignored.
- **The row the page added took a typed grade.** The add handler built
  an `<input>` for every map whose values were not `int` or `float`, so
  a row added on the page accepted `usually reliable`, `b`, or anything
  else, and learned on save that it is not an admiralty grade. The
  server has always drawn a `<select>` for the same row; only the row
  the page built was different.
- **On an empty map the control did nothing at all.** The page adds a
  row by appending to a `tbody`, and a map with no rows rendered the
  *no grades set* note **instead of** the table. So on the default
  profile — where nobody has graded anybody, which is every profile
  before the first grade — picking an organisation appended to nothing
  and the box simply cleared itself.

### 7f.2 A letter is not a grade anybody can read

`A B C D E F G` was the whole vocabulary the picker offered, and the
multiplier fields under it were labelled the same way. Two of the seven
read backwards without their wording: **F** looks like the bottom of an
A–G scale and is `Reliability cannot be judged` — neutral, worth 1.00,
the semantic twin of `unrated` — and **G** looks like the step below it
and is not a quality judgement at all but an accusation of deception.
The block's blurb already said both, in a tooltip, beside a picker that
made the reader hold seven letters in their head to use it.

The taxonomy's own `expanded` column is the wording, and it now lives on
`ValueTrustTool::GRADE_LABELS` beside `GRADES` and `DEFAULT_SCALE` —
copied rather than read out of `admiralty-scale` at render time, because
a profile must stay gradeable on an instance that never enabled the
taxonomy. Both the grade picker and *What a grade is worth* read it, so
the multipliers are now labelled `E — Unreliable 0.25` rather than
`E 0.25`.

### 7f.3 What was built

**The picker queries; it does not list.** The endpoint is
`/dashboards/searchOrganisations`, already ACL'd to every user and
already capped at fifty rows — the dashboard's org filter faced the same
catalogue and answered it the same way. No second endpoint, no ACL
entry. The suggestion list shows the name over the uuid, because the
uuid is what the document stores and what an exported profile is read
in. Organisations the map already carries are left out of the list;
`data-ap-key` on the row is what says which those are.

**A uuid typed in full is still offerable.** A profile written elsewhere
is what import exists for, and §4 already keeps a graded uuid this
instance cannot name. Offered only on a complete uuid and only when
nothing else matched, so it cannot be reached by a slip.

**The added row is the row the server would have drawn** — the name, the
uuid under it, a `<select>` of the same labelled grades, and the cross
that takes it back off. Nothing is preselected and the box is
`required`: a grade the analyst did not choose is an opinion the
document would record on their behalf, and `A` is the worst available
guess at one. The browser refuses the save and §7c's `invalid` handler
opens the pane holding the box.

**The empty map keeps its table, folded away.** `wb-empty` and the table
now trade places rather than excluding each other, in both directions —
removing the last row brings the note back, which matters because *an
empty map overrides nothing* is exactly what the analyst who just
removed it needs told.

**Two other maps were carrying the same defect** and are fixed by the
same mechanism: a block may now declare `value_options`, and
`warninglist_category` and `enrichment.locality` declare theirs, so a
row added on the page to either of them gets its vocabulary instead of a
text box.

**Enter never submits from the picker.** It is a lone text input in a
form, so a return pressed halfway through an organisation's name used to
save the profile.

Verified in the browser against the running instance: searching `ci`
offers two organisations by name, picking one writes
`reference.org_trust.<uuid>`, saving with no grade chosen is refused by
the box, grading `B` and saving stores
`{"581b5fea-…":"B"}` — and the reloaded pane puts the name, the uuid and
`B — Usually reliable` back. Arrow keys and Enter pick; Escape closes;
an organisation already graded is not offered twice; removing the row
restores the note. No JavaScript errors on the editor, the read-only
viewer or the simulator.

## 8. Out of scope

- Comparing two arbitrary profiles. The simulator compares the candidate with
  the one in force, which is the question an analyst has. Profile-to-profile
  diff is a later convenience.
- Fork lineage or merge (D5).
- Sharing a profile to other organisations (`all_orgs` is out, D4). Export and
  import is the path.
- A profile history or audit trail of edits. `AuditLogBehavior` on the model
  would give this nearly free and is worth considering, but it is not specified
  here.
