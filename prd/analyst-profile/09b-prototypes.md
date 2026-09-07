# PRD: Analyst Profile — phase 8b, the three prototypes

**The brief for the design pass, written to be executed cold.** Three agents
each build one candidate from this file and the fixtures beside it, with no
prior knowledge of the corpus. [`09-editor.md`](09-editor.md) is still the
specification of *what* the pages do; this file says what to draw, what to be
different about, and how one gets picked.

Depends on 8a, which is built: the view-model, the fixtures in
`09a-fixtures/`, the page frame in `mockups/frame.html`, and the checker in
`mockups/check-mockup.sh`. **8b builds none of that** — it copies the frame
and writes a candidate into it.

## 1. Executing this cold

### 1.1 Read exactly this, in this order

Nothing else in the corpus. It is fifteen documents and reading them is a day.

| Read | For |
|---|---|
| `09a-fixtures/README.md` | What every fixture holds, field by field. **Start here.** |
| This file, §2 to §7 | The boards, the direction you were assigned, the coverage list |
| `09-editor.md` §3, §4, §5, §6 | What each page does and why — the only sections you need |
| `mockups/README.md` | The tooling: the build loop, what the frame gives you, three traps |
| `09a-fixtures/*.json` | The numbers. Every figure you draw comes from these |

If a fixture and a document disagree, **the fixture is right** and the
disagreement is worth reporting: it means 8a and the spec have drifted.

### 1.2 Do not

- **Do not touch anything under `app/`.** 8b changes no product code. A
  candidate is one HTML file under `prd/analyst-profile/mockups/`.
- **Do not run or need the MISP dev instance.** Everything is in the
  fixtures. If you find yourself wanting a live page, the fixture is missing
  something — say so rather than working around it.
- **Do not invent a number.** Not one. Every figure, name, count and
  contribution comes from a fixture. Inventing a plausible one is how a design
  gets drawn that cannot be built (§4 of `09-editor.md` and the whole of
  `value-profile-live/`).
- **Do not read the other candidates.** Three convergent designs answer
  nothing. Build yours from the direction you were assigned.
- **Do not write the kit, the frame or the checker.** They exist.

### 1.3 The loop

```bash
cp prd/analyst-profile/mockups/frame.html \
   prd/analyst-profile/mockups/<your-candidate>.html
# write your candidate into it, keeping the <!-- vp-kit --> marker, then:
python3 prd/phase7/kit/inline-kit.py \
    prd/analyst-profile/mockups/<your-candidate>.html
bash prd/analyst-profile/mockups/check-mockup.sh \
    prd/analyst-profile/build/<your-candidate>.html
```

The source file keeps the `<!-- vp-kit -->` marker and stays small enough to
read in a diff; `inline-kit.py` swaps it for 812KB of MISP's own CSS and
writes to `build/`, which is what gets published and is not committed. **Never
paste the kit into a source file.**

Run the checker before publishing and again after any edit. **It fails on an
unfilled frame by design** — one of its assertions is that no board still
carries the frame's own placeholder text — so a passing run means all three
boards have something in them.

Publish `build/<your-candidate>.html` with the Artifact tool when it passes,
and report the URL. Name the file after your direction — `ledger-sheet`,
`workbench`, `stated-judgement` — so the three are distinguishable in a
gallery.

## 2. What 8b is, and what it is not

Three standalone HTML files, each a complete design for the same three boards,
drawn against 8a's real fixtures and wired to nothing. They are published for
comparison, one is picked, that one is refined, and 8c implements it.

It is **not** a UI review of one design with variants. Three candidates that
differ in colour and border radius answer no question worth a phase. §4 names
the axis each one is different along, and a candidate that could be turned
into another by editing a stylesheet has failed the brief.

It is also **not** a coverage exercise where the hard parts are grey boxes.
§5 lists nine pieces of content each candidate must actually render, because
every one of them is a place where a plausible-looking design turns out not to
fit real data — a `points` map with different keys per row, a signal this
instance does not have, a diff row that vanished. `prd/phase7`'s mockups
skipped several of those and `value-profile-live/` is the record of the cost.

## 3. The boards

Three per candidate. `view/:id` is `edit/:id` with the inputs replaced by their
values, so it is a state of the edit board rather than a fourth one.

| Board | The question it has to answer |
|---|---|
| **index** | *Which profile is weighting my pages, and why is it not the one I just forked?* |
| **edit** | *What is this profile asserting, and how do I change one number without reading JSON?* |
| **simulate** | *What would my change do — to this value, and to the values I care about?* |

Put each on its own `.vp-frame` in the one file, in that order, so the three
can be scrolled in sequence and compared against the other candidates board
for board.

## 4. The three directions — one per agent

Take the one you were assigned and commit to it. They are ordered by how much
they ask of the reader, not by preference.

### A — The ledger sheet

**Everything is one table and the numbers are the design.** One row per
signal; the weight, the band and the points map are columns; the contribution
this profile produced on the fixture's value is another column, and that column
adds up to the quality at the foot. Editing happens in the cells. Monospace
figures, ruled alignment, no cards. The simulator is not a separate place: it
is two more columns and a delta on the same table.

The bet: the exact-sum invariant is the whole reason this feature can show its
work — the ledger adds up to the score with nothing normalised — and a column
that visibly adds up is the most direct rendering of it anyone will draw. The
reader who wants this already thinks in the ledger.

The risk it must survive: seven sections do not all fit a table, and
`relevance`'s TTL map and `reference`'s two override maps are the proof.

### B — The workbench

**Two panes, and the effect is never off screen.** The left pane is the
profile — sections as a navigable outline, one open at a time. The right pane
is the value under assessment, permanently, updating as the left changes. The
simulator stops being a page an analyst has to remember to visit; it is the
right-hand half of the editor.

The bet: MISP already shipped a simulator for decaying models — five actions
and a whole view directory — and nobody used it (`09-editor.md` §2). A
simulator you have to navigate to is a simulator you do not use, and the fix
is to stop making it a destination.

The risk it must survive: the right pane is a whole second page's worth of
content in half the width, and a 1280px window has to degrade to something
still usable.

### C — The stated judgement

**The profile reads as a document, not a form.** Each signal is a sentence
asserting what a piece of evidence is worth — *"a report from an independent
organisation is worth 7, up to 28"* — with the numbers editable in place.
Sections are chapters. The simulator is a deliberate step with a
before-and-after of its own.

The bet: a profile is *somebody's stated judgement*, and an org profile has
readers who did not write it — a colleague deciding whether to fork it, an
auditor asking why a value scored 84. This is the only one of the three a
reader can understand without already knowing the schema.

The risk it must survive: eleven signals as eleven sentences is a lot of prose
to scan, and an analyst who wants to change one number should not have to read
a paragraph to find it.

## 5. Coverage — nine things a candidate must render

Not as placeholders. Each is in the fixtures; `09a-fixtures/README.md` says
which file.

1. **A signals table whose points columns differ per row.** `points` has no
   fixed schema by design: `{per_org, cap}` next to `{scale, none}` next to
   `{dated, undated, lagged}`. Any design that assumed three uniform numeric
   columns is wrong here, and this is the cheapest place to find that out.
2. **A custom signal, badged as one**, and **a signal the profile names that
   this instance does not have** — *not implemented here*, which the engine
   already renders in its `not_counted` list and the palette must too.
3. **The loader's error list**: a file that would not parse, a class that is
   not a `ValueSignalBase`, a colliding id. This is the only page an admin
   finds out on.
4. **The band strip**, drawn against the attainable bound, in the state where
   `medium` sits above `high` — so the design shows how it says *this is
   wrong* before a save is attempted.
5. **The ledger diff**: rows with a delta, a row that **appeared**, a row that
   **vanished**, and both totals, each still summing to its own quality.
   Including the *empty* diff, which must read as "no change" and not as a
   blank table.
6. **The comparison set**: several pinned values with lean, quality and band
   under each profile — **and its empty state**, which is what a new analyst
   actually sees.
7. **The index's per-row standing**: `in_force`, `disabled`, and `overridden`
   *naming the profile that beat it*. Plus the state where nothing is in force
   at all, which means scoring is switched off on this instance.
8. **Two sections that are maps, not lists**: `relevance`'s per-type TTL table
   and `reference`'s org-trust grades — both with an "add one" affordance and
   neither listing every attribute type or every organisation on the instance.
9. **Both themes.** Light and dark, in MISP's own palette, via the frame's
   theme bridge. Never a hardcoded colour where a `--bs-*` or `--vp-*` token
   exists.

## 6. Verification

1. `check-mockup.sh` on the built candidate, in both themes. Its first
   assertion is that **`--vp-mal` resolves**, and it aborts the rest if it
   does not: a mockup whose CSS did not apply still renders, as unstyled HTML
   that passes a colour check for the wrong reason. `mockups/README.md`'s
   *Three traps* is the full list, and all three have cost this project a
   verification sweep already.
2. Each of §5's nine items is present and rendered rather than stubbed. A
   candidate missing one is not a candidate.
3. Both themes, no unstyled fallback, no colour resolving to nothing.
4. Every number agrees with the fixtures — **including the arithmetic**. A
   ledger column that does not add up to the quality printed under it is the
   one defect that disqualifies a candidate outright, because it is the
   invariant the whole feature is built on.
5. The candidate is recognisable as its §4 direction from the screenshots
   alone.

## 7. How one gets picked

Not by vote. The three are published together and the decision is the user's,
made against these questions:

- Which one would an analyst who has never seen the schema **read correctly**?
- Which one makes changing one number **fastest**?
- Which one makes the consequence of a change **unavoidable** rather than
  merely available?
- Which one still works at 1280px, and on the org profile a colleague did not
  write?

The picked candidate is refined in place — it stays a mockup while the copy
and the layout settle — and 8c implements it against 8a's view-model. A hybrid
is an allowed outcome, and if it is chosen it is written down as one before 8c
starts, because *"the table from A with the split from B"* is a design decision
and not an implementation detail.

## 8. Out of scope

- Wiring. Nothing in 8b posts, saves or fetches.
- JavaScript beyond what a candidate needs to *show* an interaction — a tab
  switch, a section open, a theme toggle. No validation logic and no scoring:
  the numbers are fixtures.
- The pages 8a decided against. There is no `add` form (`09-editor.md` §3),
  so no candidate draws one.

## 9. What 8b found in 8a

The three candidates are built and published:

| Direction | Source | Artifact |
|---|---|---|
| A — the ledger sheet | `mockups/ledger-sheet.html` | https://claude.ai/code/artifact/d1655add-c6da-420f-ab6c-a25bc05f4720 |
| B — the workbench | `mockups/workbench.html` | https://claude.ai/code/artifact/be9f9d78-ca7a-4523-80a4-9dd3faa2d8b1 |
| C — the stated judgement | `mockups/stated-judgement.html` | https://claude.ai/code/artifact/11fd32c3-1e69-4c27-b6c5-c22c00b8377a |

Three agents built them cold and in parallel, none reading another's file.
Where all three report the same defect they found it separately, which is the
strongest evidence this corpus produces. Everything below was re-verified
against the fixtures and the tooling before being written down.

### 9.1 Scaffolding defects, fixed in this pass

1. **The checker's placeholder assertion could never pass.** It read
   `document.body.textContent` for `Candidate body for the`, and `textContent`
   includes `<script>` source — so the probe matched its own `indexOf`
   argument on every candidate, filled or not. A page whose entire content is
   `<p>hello world</p>` fails it at index 151. Fixed by reading the
   `.vp-board`s with `script`/`style` stripped, and checked both ways: `clean`
   on a filled candidate, `a board is still the frame default` on
   `build/frame.html`.

   §6 of this file and `mockups/README.md` both advertise this as the
   assertion that catches an unfilled board. It never has. Phase 7's checker
   has no such assertion — it was introduced in the 8a adaptation, so no
   candidate has ever been checked for the thing it was written to catch.

2. **The frame reached its own `--vp-page` only by accident.** MISP's own
   CSS sets `body { display: flex }` and `.vp-doc` set only `max-width`, so
   the page shrink-to-fit to its content instead of filling the pinned width.
   Measured at a 1700px window on the three built candidates:

   | Candidate | `.vp-doc` | `board-edit` |
   |---|---|---|
   | ledger sheet | 1600px | 1568px |
   | workbench | 1600px | 1568px |
   | stated judgement | **1157px** | **1125px** |

   A and B reach the cap because their wide tables push the flex item into it;
   B had also found and fixed this in its own copy. C's fixed 68rem centred
   paper never pushes it, so C alone rendered at 1157px — the defect is
   invisible in the two candidates whose content happens to be wide, and
   silently rescales the one whose design cannot self-rescue. That is the
   worst shape for a defect in a phase whose entire output is a side-by-side
   comparison. Fixed with `width: 100%` in the frame, and C rebuilt against
   it so the three are judged at one width.

3. **`.vp-board { overflow: hidden }` disabled `position: sticky`** inside a
   board, because it makes a scroll container. Fixed with `overflow: clip`,
   which still clips to the radius. Not a neutral fix: a permanently visible
   pane is exactly what direction B is, so the frame was quietly hostile to
   one of the three directions it was built to host.

4. **The frame's edit header named a profile no fixture has** — `/edit/29`,
   `/simulate/29`, and `rev 4`, where `4` is the *version* and the revision is
   37. Corrected to 23 and revision 37. Two candidates corrected it locally
   and reported it; the third drew the fixture's numbers without comment.

### 9.2 Fixture drift 8a should settle before 8c

None of these is a drawing problem. Each is a place where the view-model, the
fixtures and the spec disagree, and the cost lands on 8c.

1. **`palette.json` has no `available` signal.** Eleven `active` and one
   `missing`, all `in_profile: true`, against a README documenting three
   states. The add-a-signal affordance has nothing real to offer, so all three
   candidates drew it empty, disabled, or as a stated absence.

2. **`profile.json` omits the signal §5.2 requires.** Its `sections.signals`
   holds eleven items and the string `partner_feed` does not occur anywhere in
   the file; `reporting.partner_feed_agreement` exists only in `palette.json`,
   marked `missing`. All three candidates reached into the palette to draw it.
   **If 8c builds the edit page from the profile view-model alone, the
   not-implemented row cannot appear on any page** — and that row is the one
   §5.2 exists to force.

3. **One profile, four names, and a diff against itself.** id 23 / uuid
   `6e2679bc…` / version 4 / revision 37 is *default-v1* in `index.json`,
   *Weights I actually use* in `profile.json` and in `simulate.json`'s
   `candidate`, and *Instance default, galaxies off* in `simulate.json`'s
   `in_force`. Meanwhile `index.json` says the profile in force is id **52**
   and that 23 is *overridden by* it — so the simulate board's before column
   draws a profile the index says is not in force. Same id on both sides is
   defensible if the candidate is "23 with unsaved edits"; one record under
   two names is not.

4. **`profile.json` records no quality for its own value** — only per-signal
   contributions, which sum to −6 and match neither side of simulate's 4 → 23.
   The exact-sum invariant is therefore only provable on `simulate.json`, the
   one fixture carrying `sums.ok`. Recording the quality on `profile.json`
   would make the edit board's own foot checkable.

5. **`sightings.false_positive` is −23 in `profile.json` and −20 in
   `simulate.json`** for the same value. −20 is what the points map yields
   directly; the difference is trust weighting, applied in one fixture and not
   the other. Possibly correct, but undocumented — two candidates stopped to
   derive it, and one of them still calls it suspect.

6. **`simulate.json`'s `detail.not_counted` is `[]` on both sides**, though
   the fixtures README says a `missing` signal is listed in the assessment as
   not counted.

7. **`bands.json` carries `narrow_catalogue_note` where its two siblings carry
   `_errors`** (`inverted_errors`, `beyond_bound_errors`), so a candidate
   drawing the third band problem has no error sentence to print and has to
   fall back to `problems[0].message`.

8. **`09-editor.md` §3 names `other_owner` and `unresolved` standings** that
   `index.json` does not contain. All three left them undrawn rather than
   inventing them, which is the right call and also means those two states go
   into 8c never having been designed.

9. **Nothing scores index profiles 52 and 51**, and no fixture gives the
   assessed value's attribute type — so an index that wants to show what each
   profile would make of a value, and any board that wants a type chip, have
   no data to draw.

### 9.3 What this says about the fixtures

Item 2 and item 3 are the same shape of problem: the fixtures were dumped per
board rather than from one coherent instance state, so they agree
board-by-board and contradict each other across boards. That is survivable for
a mockup — each candidate drew each board from one fixture and said so — but
8c builds one page from one view-model, and there the contradiction has to
resolve to a single answer.
