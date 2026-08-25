# Phase 7 — candidate mockups

Working directory for PRD §7 of `prd/value-profile-page.md`. Read that section
first: it says what a candidate is and what each tab must cover. This file only
says how to drive the tooling.

```
kit/
    build-kit.sh        rebuilds mockup-kit.css from app/webroot/css
    mockup-kit.css      MISP's own stylesheets, concatenated, fonts inlined
    build-frame.py      regenerates frame.html from a live page dump
    frame.html          the page chrome + deck furniture — start here
    inline-kit.py       source + kit -> a publishable file
    check-mockup.sh     renders the built file in both themes and asserts
mockups/                one committed source file per tab
build/                  publishable copies, not committed
```

## Making a mockup

```bash
cp prd/phase7/kit/frame.html prd/phase7/mockups/occurrences.html
# write four candidates into it, then:
python3 prd/phase7/kit/inline-kit.py prd/phase7/mockups/occurrences.html
bash prd/phase7/kit/check-mockup.sh prd/phase7/build/occurrences.html
# publish prd/phase7/build/occurrences.html with the Artifact tool
```

The source file keeps the `<!-- vp-kit -->` marker and stays small enough to
read in a diff. `inline-kit.py` swaps that marker for 812KB of MISP CSS and
writes the result to `build/`, which is what gets published. Never paste the
kit into a source file.

Set `data-vp-tab` on each `.vp-frame` (`occurrences`, `sightings`,
`relationships`, `enrichment`, `analyst`) and the deck script activates the
matching tab in the bar.

## What the kit gives you

- **MISP's real stylesheets**, in the order the page loads them, so `card`,
  `table`, `badge`, `nav`, `form-control`, `fas fa-*` and
  `misp-icon misp-icon-* misp-simple` all behave exactly as they do in the
  product. Font Awesome's faces and MISP's icon set are embedded as data URIs —
  a published artifact may not fetch anything off-host.
- **The real page chrome**, lifted from a live render: banner, type chips,
  disabled action buttons, fact strip, pivot rail, nine-tab bar.
- **The real geometry.** The page is `container-fluid`, so the content column
  is a share of the window, not a fixed width. The frame pins the page at
  1600px, where `col-lg-9` measures 1200px and the full row 1576px. Write
  ordinary Bootstrap columns inside the frame's `.row`: keep the 9/3 split for
  a candidate with a right rail, or use `col-12` for one that wants the width.
- **A theme bridge**, mirroring the artifact's `data-theme` onto MISP's
  `data-bs-theme`, so both themes render in MISP's own palette.
- **Deck furniture** — side-by-side and stacked views, a scale control, jump
  links, the comparison table and the recommendation section.
- **Skeleton primitives** — `.sk` with `--sk-w` / `--sk-h`, plus `.sk-line`,
  `.sk-circle`, `.sk-block`, `.sk-chart`, `.sk-dim`.

## Two traps

**A mockup whose CSS did not apply still renders.** It renders as unstyled
HTML, and unstyled HTML passes a colour check for the wrong reason — this cost
a whole verification sweep in §6.1. `check-mockup.sh` asserts `--vp-mal`
resolves before it asserts anything else, and so should any harness you write.

**`bootstrap5-custom.min.css` starts with a BOM.** Concatenated into the middle
of a file it becomes an invalid token that takes the following rule down with
it — which there is the entire `:root` block of Bootstrap variables. Everything
still parses and every colour silently stops resolving. `build-kit.sh` reads
with `utf-8-sig` and refuses to emit a kit containing one.

## Rebuilding

`build-kit.sh` after any change under `app/webroot/css`. `build-frame.py`
after any change to the page chrome, against a fresh dump:

```bash
B64=$(printf %s 185.234.219.24 | base64 -w0)
curl -sk -b cookies.txt "https://localhost/values/view/$B64" -o page.html
python3 prd/phase7/kit/build-frame.py page.html
```
