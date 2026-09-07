# Phase 8b — candidate prototypes

**Your brief is [`../09b-prototypes.md`](../09b-prototypes.md).** Read
that, and [`../09a-fixtures/README.md`](../09a-fixtures/README.md) before
it. This file only says how to drive the tooling.

```
frame.html          the page frame — copy this, do not edit it
build-frame.py      regenerates frame.html from a live MISP page dump
check-mockup.sh     renders a built candidate in both themes and asserts
../09a-fixtures/    every number you are allowed to draw
../build/           publishable copies; not committed
```

The CSS kit and the inliner are phase 7's and are shared:
`prd/phase7/kit/mockup-kit.css` is MISP's own stylesheets concatenated
with the fonts inlined, and `prd/phase7/kit/inline-kit.py` swaps the
`<!-- vp-kit -->` marker for it.

## Building a candidate

```bash
cp prd/analyst-profile/mockups/frame.html \
   prd/analyst-profile/mockups/<your-candidate>.html
# draw your three boards into it, keeping the <!-- vp-kit --> marker
python3 prd/phase7/kit/inline-kit.py \
    prd/analyst-profile/mockups/<your-candidate>.html
bash prd/analyst-profile/mockups/check-mockup.sh \
    prd/analyst-profile/build/<your-candidate>.html
# then publish the built file with the Artifact tool
```

The source file stays small enough to read in a diff; the built copy
carries 812KB of MISP CSS and is what gets published. **Never paste the
kit into a source file.**

## What the frame gives you

- **MISP's real navbar**, lifted from a live page, dimmed because it is
  context rather than the thing being judged. Once for the document, not
  once per board — a reader moving between three pages in the real
  product sees one navbar.
- **MISP's real page header** per board: breadcrumb, title, count badge,
  subtitle and action buttons, in the product's own classes. This part
  *is* yours to design — each board's is different, which is why the
  frame writes it out rather than lifting the decaying-model page's.
- **The real geometry.** The page is `container-fluid`, so the content
  column is a share of the window rather than a fixed width. The frame
  pins the page at 1600px; §7 of the brief asks whether your candidate
  still works at 1280, so switch `--vp-page` and look before publishing.
- **A theme bridge**, mirroring the artifact's `data-theme` onto MISP's
  `data-bs-theme`, so both themes render in MISP's own palette.
- **Skeleton primitives** — `.sk` with `--sk-w` / `--sk-h`, plus
  `.sk-line`, `.sk-block`, `.sk-dim` — for the parts a board legitimately
  elides. Not for the parts §5 of the brief requires.

## Three traps

**A mockup whose CSS did not apply still renders.** It renders as
unstyled HTML, and unstyled HTML passes a colour check for the wrong
reason — the trap that made a whole verification sweep vacuous in phase
7 §6.1. `check-mockup.sh` asserts `--vp-mal` resolves before it asserts
anything else, and aborts if it does not.

**`bootstrap5-custom.min.css` starts with a BOM.** Concatenated into the
middle of a file it becomes an invalid token that takes the following
rule down with it — and there, that is the entire `:root` block of
Bootstrap variables. Everything still parses and every colour silently
stops resolving. `build-kit.sh` reads with `utf-8-sig` and refuses to
emit a kit containing one.

**The delta column is the one place a red/green pair does real work.**
Use `--vp-dir-with` and `--vp-dir-against`, never raw Bootstrap colours;
`check-mockup.sh` asserts both resolve. They are `--vp-mal` and
`--vp-ben` underneath, which is the same pair the value page's ledger
uses — so a reader who has seen one recognises the other.

## Rebuilding the frame

Only after MISP's page chrome changes.

```bash
B=prd/analyst-profile/mockups
curl -sk -b cookies.txt https://localhost/decayingModel/index -o $B/dump.html
python3 $B/build-frame.py $B/dump.html
rm $B/dump.html
```

Any ordinary MISP configuration page works; the builder wants a
`<header>` with a navbar in it and refuses anything that still names a
host afterwards.
