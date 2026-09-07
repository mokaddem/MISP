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
