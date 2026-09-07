# PRD: Analyst Profile — phase 8, the editor

**Specification. Nothing built.** Depends on phase 1
([`02-store.md`](02-store.md)) for the actions and phases 2–6 for the sections
it edits. Can be built incrementally, one section at a time.

## 1. What ships

The UI for owning a profile: an index, a viewer, a per-section editor, a
one-click fork, and — the part that makes the rest worth building — a
**simulator** that shows what a candidate profile would do to a real value
before it is saved.

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

## 3. Pages

| Action | What it renders |
|---|---|
| `index` | The profile in force (badged), the analyst's own, their org's, and the instance default. Each row: name, owner, enabled, version, and whether it is editable |
| `view/:id` | Read-only, section by section. Takes the simulator's optional `?value=` parameter (§5) to show each weight beside the contribution it produced on that value; without one, the contribution column stays empty rather than inventing a value |
| `edit/:id` | Per-section forms; see §4 |
| `fork/:id` | One POST, no form. Lands on `edit` of the copy |
| `simulate/:id` | §5 |
| `export/:id` | The JSON |

**`index` names the profile in force first and unmistakably.** Under D3 exactly
one applies, and the commonest confusion this feature can create is an analyst
editing a profile that is not the one weighting their pages — because they made
a fork, forgot, and their org profile still wins, or because their own is
disabled.

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

Six sections with genuinely different shapes (D2), so six treatments rather
than one JSON textarea. Though **a raw JSON editor is also offered**, because
every profile is one document and an analyst who wants to paste one should be
able to — with validation on save (phase 1 §3).

**`signals`** — a table, one row per signal: enabled, group, band, and its
`points` fields. The points columns differ per signal, which the table has to
tolerate; `points` has no fixed schema by design (`03-signals.md` §3). Each row
links to its implementation's description of what it reads and what its keys
mean, because `per_org` and `cap` are not self-explanatory.

**`thresholds`** — the lean supermajority and the quality bands (D11), with
the band boundaries drawn as a strip against the attainable range under the
enabled catalogue, so `medium` above `high` — or a band beyond what the
signals can reach — is visibly wrong rather than silently saved.

**`escalations`** — a list with enable toggles. v1 ships one, so this is a
checkbox and its prose.

**`exclusions`** — a list with enable toggles and one parameter each.

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

Then the regression set as a compact summary: disposition and score under
each profile, five rows (§2.1), so a change that quietly turned `8.8.8.8`
malicious — or every single-report value out of the `low` band — is visible
without five page loads.

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

1. `parallel-lint`, then `queryACL/findMissingFunctionNames` — every new action
   has an ACL entry.
2. `index` as four users (own profile / org profile / neither / site admin):
   the profile in force is correct and unambiguous in each.
3. Fork the default as a non-admin, land on `edit`, change one weight, save,
   reload a value page: the hero names the new profile and the score has moved.
4. Simulate the default against all five regression cases with no changes: the
   diff is empty and both columns are identical. An empty diff must render as
   *"no change"*, not as a blank table.
5. Simulate a candidate that flips `8.8.8.8` to MALICIOUS: the five-value
   summary shows it, in a colour that reads as a warning without asserting the
   change is wrong. Same for one that lifts the median case out of `low`.
6. Simulate a profile the analyst cannot edit: works, saves nothing.
7. `quality_bands.medium` above `quality_bands.high`, or a band beyond the
   enabled catalogue's attainable sum: rejected on save with the band strip
   showing why.
8. A malformed JSON paste: rejected, with the parse error and the line, and the
   stored profile untouched.
9. Rename a profile: `version` does not move. Change a weight: it does.
10. Both themes; the diff table's delta column is the one place a red/green
    pair is doing real work and needs checking against `--vp-dir-with` /
    `--vp-dir-against` rather than raw Bootstrap colours.

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
