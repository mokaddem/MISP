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

### 2.1 The four demo values are the fixture the simulator wants

`185.234.219.24` (MALICIOUS 84), `8.8.8.8` (BENIGN 91), `45.155.205.233`
(MALICIOUS 93) and the conflicted value exercise every layout, every
disposition and — after phase 3 — every band boundary. A simulator that runs a
candidate profile against all four and shows a four-row summary answers *"did I
just break something"* in one screen.

Plus one value of the analyst's choosing, because the interesting question is
usually about a value they are looking at.

## 3. Pages

| Action | What it renders |
|---|---|
| `index` | The profile in force (badged), the analyst's own, their org's, and the instance default. Each row: name, owner, enabled, version, and whether it is editable |
| `view/:id` | Read-only, section by section, with every value shown resolved — a weight of 7 next to the contribution it produced on the last value viewed |
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

Six sections with genuinely different shapes (D2), so six treatments rather
than one JSON textarea. Though **a raw JSON editor is also offered**, because
every profile is one document and an analyst who wants to paste one should be
able to — with validation on save (phase 1 §3).

**`signals`** — a table, one row per signal: enabled, group, band, and its
`points` fields. The points columns differ per signal, which the table has to
tolerate; `points` has no fixed schema by design (`03-signals.md` §3). Each row
links to its implementation's description of what it reads and what its keys
mean, because `per_org` and `cap` are not self-explanatory.

**`thresholds`** — five numbers, with the band boundaries drawn as a strip so
`suspicious_floor` above `malicious_floor` is visibly wrong rather than
silently saved.

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

### 4.1 Version, and what bumps it

`version` increments on any change to `parameters`. Not on renaming the
profile, not on enable/disable.

The reason is `01-profile.md` §5.5 and phase 10: once verdicts are cached by
`(value, profile)`, the version is the cache key's discriminator, and a version
that moves for a cosmetic reason invalidates every cached verdict for nothing.
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

Then the four demo values as a compact summary: disposition and score under
each profile, four rows, so a change that quietly turned `8.8.8.8` malicious is
visible without four page loads.

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
4. Simulate the default against all four demo values with no changes: the diff
   is empty and both columns are identical. An empty diff must render as
   *"no change"*, not as a blank table.
5. Simulate a candidate that flips `8.8.8.8` to MALICIOUS: the four-value
   summary shows it, in a colour that reads as a warning without asserting the
   change is wrong.
6. Simulate a profile the analyst cannot edit: works, saves nothing.
7. `suspicious_floor` above `malicious_floor`: rejected on save with the band
   strip showing why.
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
