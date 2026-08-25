"""
Build prd/phase7/kit/frame.html from a dump of the live Value Profile page.

The frame is the page around a tab body — banner, fact strip, pivot rail and
the nine-tab bar — lifted from the real markup rather than approximated, so a
mockup is judged in the chrome it would actually live in.

    curl -sk -b cookies.txt \
        "https://localhost/values/view/$(printf %s 185.234.219.24 | base64 -w0)" \
        -o page.html
    python3 prd/phase7/kit/build-frame.py page.html

Run it again whenever the page chrome changes.
"""
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'frame.html')

TEMPLATE_HEAD = '''<title>Value Profile Mockup Frame</title>

<!--
    Phase 7 mockup frame (PRD §7.3).

    Copy this file to prd/phase7/mockups/<tab>.html and fill in four
    candidates. Do not paste the kit into this file: the marker below is
    replaced by prd/phase7/kit/inline-kit.py at build time, which is what
    keeps 812KB of CSS out of five committed files.

        python3 prd/phase7/kit/inline-kit.py prd/phase7/mockups/<tab>.html
        # -> prd/phase7/build/<tab>.html, which is the file you publish

    Set data-vp-tab on each .vp-frame to the tab you are designing
    (occurrences | sightings | relationships | enrichment | analyst) and the
    deck script activates the matching tab in the bar for you.
-->
<!-- vp-kit -->

<style>
/* ==================================================================
 * Deck — the page furniture around the candidates. Shared by all five
 * artifacts so a reader learns it once.
 * ================================================================== */

:root {
    /*
     * The reference viewport. The real page is `container-fluid`, so the
     * content column is a percentage of the window rather than a fixed
     * width: at 1600px the col-lg-9 measures 1200px and the full row
     * 1576px. Pinning the page width rather than the body width lets a
     * candidate choose its own split and still be drawn at the size it
     * would really have.
     */
    --vp-page: 1600px;
    --vp-scale: 0.4;
    --vp-deck-gap: 2rem;
}

body {
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
}

.vp-deck {
    padding: 1.5rem clamp(1rem, 3vw, 3rem) 4rem;
    max-width: 100%;
}

.vp-deck-head h1 {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0 0 .35rem;
}

.vp-deck-sub {
    color: var(--bs-secondary-color);
    max-width: 68ch;
    margin-bottom: 1rem;
}

.vp-deck-controls {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem 1.5rem;
    align-items: center;
    padding: .75rem 0 1.25rem;
    border-bottom: 1px solid var(--bs-border-color);
    margin-bottom: 1.5rem;
    position: sticky;
    top: 0;
    background: var(--bs-body-bg);
    z-index: 20;
}

.vp-deck-controls .btn-group .btn {
    --bs-btn-padding-y: .15rem;
    --bs-btn-padding-x: .6rem;
    --bs-btn-font-size: .85rem;
}

.vp-deck-jump {
    margin-left: auto;
    display: flex;
    gap: .35rem;
}

/* ------------------------------------------------------------------
 * The lane. Side by side scrolls horizontally at whatever scale is
 * chosen; stacked drops the transform and shows each candidate at true
 * size, which is the only view where density can be judged.
 * ------------------------------------------------------------------ */

.vp-lane {
    display: flex;
    gap: var(--vp-deck-gap);
    align-items: flex-start;
}

[data-mode="compare"] .vp-lane {
    overflow-x: auto;
    padding-bottom: 1rem;
}

[data-mode="stack"] .vp-lane {
    flex-direction: column;
    overflow-x: auto;
}

[data-mode="stack"] {
    --vp-scale: 1;
}

.vp-cand {
    flex: 0 0 auto;
    max-width: 100%;
}

.vp-cand-head {
    border: 1px solid var(--bs-border-color);
    border-radius: .5rem .5rem 0 0;
    border-bottom: 0;
    padding: .85rem 1rem;
    background: var(--bs-tertiary-bg);
    width: calc(var(--vp-page) * var(--vp-scale));
    max-width: 100%;
}

.vp-cand-id {
    font-weight: 700;
    font-family: var(--bs-font-monospace);
    margin-right: .5rem;
}

.vp-cand-thesis {
    font-weight: 600;
}

.vp-cand-reckoning {
    margin: .6rem 0 0;
    font-size: .85rem;
    color: var(--bs-secondary-color);
    display: grid;
    grid-template-columns: max-content 1fr;
    gap: .2rem .75rem;
}

.vp-cand-reckoning dt {
    font-weight: 600;
    color: var(--bs-body-color);
    white-space: nowrap;
}

.vp-cand-reckoning dd {
    margin: 0;
}

[data-mode="compare"] .vp-cand-reckoning {
    display: none;
}

.vp-cand-view {
    width: calc(var(--vp-page) * var(--vp-scale));
    max-width: 100%;
    overflow: hidden;
    border: 1px solid var(--bs-border-color);
    border-radius: 0 0 .5rem .5rem;
    background: var(--bs-body-bg);
}

.vp-cand-scaler {
    width: var(--vp-page);
    transform: scale(var(--vp-scale));
    transform-origin: top left;
}

/* ------------------------------------------------------------------
 * The frame itself. The chrome is context, not the subject, so it sits
 * back a little — except the tab bar, which is what tells the reader
 * which tab they are looking at.
 * ------------------------------------------------------------------ */

.vp-frame {
    background: var(--bs-body-bg);
    padding-bottom: 2rem;
}

.vp-frame-chrome {
    opacity: .72;
    pointer-events: none;
}

.vp-frame-tag {
    font-size: .7rem;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--bs-secondary-color);
    padding: .4rem 1.5rem 0;
}

/* ------------------------------------------------------------------
 * Skeleton primitives. Structure is real — headers, labels, controls,
 * counts — and the data inside a row is a block, except where the kind
 * of thing is itself the design point (badges, chips, dates), which is
 * where a table gets its density.
 * ------------------------------------------------------------------ */

.sk {
    display: inline-block;
    width: var(--sk-w, 5rem);
    height: var(--sk-h, .68em);
    border-radius: .2rem;
    background: var(--bs-secondary-bg);
    vertical-align: middle;
}

.sk-line {
    display: block;
    margin: .28rem 0;
}

.sk-dim {
    opacity: .55;
}

.sk-circle {
    border-radius: 50%;
    --sk-w: 1.4rem;
    --sk-h: 1.4rem;
}

.sk-block {
    display: block;
    --sk-h: 8rem;
    --sk-w: 100%;
    border-radius: .4rem;
}

/* A chart placeholder is a shape, not a grey box: candidates draw the
 * curve or the bars as static SVG inside this. */
.sk-chart {
    display: block;
    width: 100%;
    border-radius: .4rem;
    background: var(--bs-tertiary-bg);
}

.vp-deck-foot {
    margin-top: 3rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--bs-border-color);
}

.vp-deck-foot table {
    font-size: .9rem;
}
</style>
'''

TEMPLATE_TAIL = '''
<script>
(function () {
    var root = document.documentElement;

    /*
     * Theme bridge. Artifacts stamp data-theme on the root element and
     * otherwise leave it to prefers-color-scheme; MISP switches on
     * data-bs-theme. Mirror one onto the other so the mockups follow the
     * reader's theme in MISP's own palette rather than a second one.
     */
    function applyTheme() {
        var t = root.getAttribute('data-theme');
        if (!t) {
            t = window.matchMedia('(prefers-color-scheme: dark)').matches
                ? 'dark' : 'light';
        }
        root.setAttribute('data-bs-theme', t);
    }
    applyTheme();
    new MutationObserver(applyTheme).observe(root, {
        attributes: true, attributeFilter: ['data-theme']
    });
    try {
        window.matchMedia('(prefers-color-scheme: dark)')
            .addEventListener('change', applyTheme);
    } catch (e) { /* older engines: the stamped attribute still works */ }

    /* Activate the tab each frame says it is designing. */
    document.querySelectorAll('.vp-frame[data-vp-tab]').forEach(function (f) {
        var want = '#tab-' + f.getAttribute('data-vp-tab');
        f.querySelectorAll('.nav-tabs .nav-link').forEach(function (a) {
            var on = a.getAttribute('href') === want;
            a.classList.toggle('active', on);
            a.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    });

    var deck = document.querySelector('.vp-deck');
    if (!deck) { return; }

    /*
     * A scaled candidate keeps its unscaled height in layout, because a
     * transform does not affect it — so the viewport box is sized here and
     * resized whenever the scale changes.
     */
    function resize() {
        var scale = parseFloat(
            getComputedStyle(deck).getPropertyValue('--vp-scale')) || 1;
        deck.querySelectorAll('.vp-cand-view').forEach(function (view) {
            var inner = view.querySelector('.vp-cand-scaler');
            if (inner) { view.style.height = (inner.offsetHeight * scale) + 'px'; }
        });
    }

    function setScale(value) {
        if (value === 'fit') {
            var lane = deck.querySelector('.vp-lane');
            var count = deck.querySelectorAll('.vp-cand').length || 1;
            var gaps = 32 * (count - 1);
            value = Math.max(0.12, (lane.clientWidth - gaps) / (count * 1600));
        }
        deck.style.setProperty('--vp-scale', value);
        resize();
    }

    deck.querySelectorAll('[data-vp-mode]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            deck.setAttribute('data-mode', btn.getAttribute('data-vp-mode'));
            deck.querySelectorAll('[data-vp-mode]').forEach(function (b) {
                b.classList.toggle('active', b === btn);
            });
            if (btn.getAttribute('data-vp-mode') === 'stack') {
                deck.style.removeProperty('--vp-scale');
                resize();
            } else {
                setScale('fit');
            }
        });
    });

    deck.querySelectorAll('[data-vp-scale]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            deck.querySelectorAll('[data-vp-scale]').forEach(function (b) {
                b.classList.toggle('active', b === btn);
            });
            setScale(btn.getAttribute('data-vp-scale'));
        });
    });

    window.addEventListener('resize', resize);
    window.addEventListener('load', function () {
        if (deck.getAttribute('data-mode') === 'compare') { setScale('fit'); }
        else { resize(); }
    });
    setTimeout(resize, 0);
})();
</script>
'''

DECK_OPEN = '''
<div class="vp-deck" data-mode="compare">

  <header class="vp-deck-head">
    <h1>TAB — four candidates</h1>
    <p class="vp-deck-sub">
      One sentence on what this tab is for and what the four candidates
      disagree about.
    </p>
  </header>

  <div class="vp-deck-controls">
    <div class="btn-group" role="group" aria-label="View">
      <button type="button" class="btn btn-outline-secondary active"
              data-vp-mode="compare">Side by side</button>
      <button type="button" class="btn btn-outline-secondary"
              data-vp-mode="stack">Stacked, true size</button>
    </div>
    <div class="btn-group" role="group" aria-label="Scale">
      <button type="button" class="btn btn-outline-secondary active"
              data-vp-scale="fit">Fit</button>
      <button type="button" class="btn btn-outline-secondary"
              data-vp-scale="0.5">50%</button>
      <button type="button" class="btn btn-outline-secondary"
              data-vp-scale="0.75">75%</button>
      <button type="button" class="btn btn-outline-secondary"
              data-vp-scale="1">100%</button>
    </div>
    <div class="vp-deck-jump">
      <a class="btn btn-sm btn-outline-secondary" href="#C1">C1</a>
      <a class="btn btn-sm btn-outline-secondary" href="#C2">C2</a>
      <a class="btn btn-sm btn-outline-secondary" href="#C3">C3</a>
      <a class="btn btn-sm btn-outline-secondary" href="#C4">C4</a>
    </div>
  </div>

  <div class="vp-lane">

    <!-- ===================== CANDIDATE ===================== -->
    <article class="vp-cand" id="C1">
      <div class="vp-cand-head">
        <div>
          <span class="vp-cand-id">C1</span>
          <span class="vp-cand-thesis">The thesis, in one sentence.</span>
        </div>
        <dl class="vp-cand-reckoning">
          <dt>Optimises for</dt><dd>…</dd>
          <dt>Gives up</dt><dd>…</dd>
          <dt>At scale</dt><dd>… at 1,847 · … at 21,904 · … at zero</dd>
          <dt>Data</dt><dd>… (and what MISP cannot supply)</dd>
          <dt>Size</dt><dd>M</dd>
        </dl>
      </div>
      <div class="vp-cand-view">
        <div class="vp-cand-scaler">
'''

DECK_CLOSE = '''
        </div>
      </div>
    </article>
    <!-- =================== END CANDIDATE =================== -->

  </div>

  <section class="vp-deck-foot">
    <h2 class="h5">How they compare</h2>
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Candidate</th><th>Reads best when</th><th>Falls down when</th>
          <th>Size</th>
        </tr>
      </thead>
      <tbody>
        <tr><td>C1</td><td>…</td><td>…</td><td>M</td></tr>
      </tbody>
    </table>
    <h2 class="h5 mt-4">Recommendation</h2>
    <p>Which one, and why — from the agent that drew all four.</p>
  </section>

</div>
'''


def main():
    if len(sys.argv) < 2:
        raise SystemExit(__doc__)
    dump = open(sys.argv[1], encoding='utf-8', errors='replace').read()

    start = dump.find('<div class="container-fluid py-3">')
    end = dump.find('<div class="tab-content"')
    if start == -1 or end == -1 or start > end:
        raise SystemExit('!! could not find the chrome in %s' % sys.argv[1])
    chrome = dump[start:end]

    # Nothing may reach off-host from a published artifact, and the asset
    # tags are meaningless here anyway — the kit carries the CSS.
    chrome = re.sub(r'<script[^>]*></script>', '', chrome)
    chrome = re.sub(r'"https?://[^"]*"', '"#"', chrome)
    chrome = re.sub(r"'https?://[^']*'", "'#'", chrome)

    # The tab bar stays at full strength; everything above it sits back.
    split = chrome.find('<div class="container-fluid">')
    if split == -1:
        raise SystemExit('!! could not find the tab bar container')
    banner, tabbar = chrome[:split], chrome[split:]

    frame = (
        '<div class="vp-frame" data-vp-tab="occurrences">\n'
        '<div class="vp-frame-tag">Page chrome — context, not the design</div>\n'
        '<div class="vp-frame-chrome">\n' + banner + '\n</div>\n'
        + tabbar +
        '''
    <div class="tab-content">
      <div class="tab-pane fade show active" role="tabpanel">
        <div class="row">

          <!--
              The real grid. Keep the 9/3 split if the candidate wants a
              right rail; use a single col-12 if it wants the full width.
              At the pinned 1600px page, col-lg-9 is 1200px and the full
              row is 1576px.
          -->
          <div class="col-lg-9">
            <p class="text-muted">Candidate body goes here.</p>
          </div>
          <div class="col-lg-3">
            <p class="text-muted">Rail, if the candidate has one.</p>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>
'''
    )

    with open(OUT, 'w', encoding='utf-8') as handle:
        handle.write(TEMPLATE_HEAD)
        handle.write(DECK_OPEN)
        handle.write(frame)
        handle.write(DECK_CLOSE)
        handle.write(TEMPLATE_TAIL)

    print('wrote %s (%.1f KB)' % (OUT, os.path.getsize(OUT) / 1024.0))


main()
