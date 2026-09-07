"""
Build prd/analyst-profile/mockups/frame.html from a dump of an ordinary
MISP configuration page.

Phase 8b's boards are not the Value Profile page, so `prd/phase7/kit`'s
frame is the wrong chrome: it carries that page's banner, type chips and
nine-tab bar, and its deck is built to compare four candidates for *one*
tab. These boards live where the decaying-model index lives — global
navbar, MISP's page header, plain content column — and the comparison is
between three whole candidates in three files, judged side by side by a
person. So this frame is its own, and thinner: real chrome, three
boards, a theme bridge, nothing else. Phase 7's kit still supplies the
CSS and the inliner.

**The navbar is lifted from the dump; the page header is a template
here.** Two different decisions for two different reasons. The navbar is
fixed furniture and its markup is 47KB of dropdowns nobody should
retype, so it is extracted — once, not once per board, because a reader
moving between three pages in the real product sees one navbar. The page
header is *different on every board* — its breadcrumb, title, count and
action buttons are part of what a candidate designs — so what the frame
hands over is MISP's real classes with each board's own words in them,
rather than the decaying-model page's.

    B=prd/analyst-profile/mockups
    curl -sk -b cookies.txt https://localhost/decayingModel/index -o $B/dump.html
    python3 $B/build-frame.py $B/dump.html
    rm $B/dump.html

Run it again whenever MISP's page chrome changes.
"""
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'frame.html')

HEAD = '''<title>Analyst Profile — candidate frame</title>

<!--
    Phase 8b candidate frame (prd/analyst-profile/09b-prototypes.md).

    Copy this file to prd/analyst-profile/mockups/<your-candidate>.html
    and draw your three boards into the three .vp-board slots below.
    Do not paste the kit into this file: the marker is replaced at build
    time, which is what keeps 812KB of MISP CSS out of committed files.

        python3 prd/phase7/kit/inline-kit.py \\
            prd/analyst-profile/mockups/<your-candidate>.html
        bash prd/analyst-profile/mockups/check-mockup.sh \\
            prd/analyst-profile/build/<your-candidate>.html

    Every figure comes from prd/analyst-profile/09a-fixtures/. Read that
    directory's README.md first, and invent nothing.
-->
<!-- vp-kit -->

<style>
/* ==================================================================
 * The frame. One navbar, three boards, at a pinned page width so a
 * candidate is judged at the size it would really have.
 * ================================================================== */

:root {
    /*
     * MISP's content is `container-fluid`, so the column is a share of
     * the window rather than a fixed width. 1600px is where phase 7
     * measured its geometry; 1280px is the width §7 of the brief asks a
     * candidate to still work at — switch this and re-read before
     * publishing.
     */
    --vp-page: 1600px;
    --vp-board-gap: 3.5rem;
}

body {
    margin: 0;
    background: var(--bs-body-bg, #f8f9fa);
    color: var(--bs-body-color, #212529);
}

.vp-doc {
    max-width: var(--vp-page);
    margin: 0 auto;
    padding: 0 0 4rem;
}

/*
 * The navbar sits back: it is context, not the thing being judged.
 * Real markup rather than an approximation, so a candidate is read
 * inside the navigation it would really have.
 */
.vp-doc-chrome {
    opacity: .5;
    pointer-events: none;
    filter: saturate(.55);
}

.vp-doc-chrome .navbar {
    position: static !important;
}

.vp-doc-head {
    padding: 1.5rem 1rem 1rem;
    border-bottom: 1px solid var(--bs-border-color, #dee2e6);
    margin-bottom: var(--vp-board-gap);
}

.vp-doc-head h1 {
    font-size: 1.5rem;
    margin: 0 0 .35rem;
}

.vp-doc-sub {
    color: var(--bs-secondary-color, #6c757d);
    margin: 0;
    max-width: 62rem;
}

.vp-doc-jump {
    margin-top: .75rem;
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
}

.vp-doc-jump a {
    font-size: .8125rem;
    padding: .15rem .6rem;
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 999px;
    text-decoration: none;
    color: inherit;
}

/* One board: a labelled slab holding one page of the candidate. */
.vp-board {
    margin: 0 1rem var(--vp-board-gap);
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: .5rem;
    overflow: hidden;
    background: var(--bs-body-bg, #fff);
}

.vp-board-tag {
    font: 600 .75rem/1.4 var(--bs-font-monospace, ui-monospace, monospace);
    letter-spacing: .03em;
    padding: .5rem .85rem;
    background: var(--bs-tertiary-bg, #f1f3f5);
    border-bottom: 1px solid var(--bs-border-color, #dee2e6);
    color: var(--bs-secondary-color, #6c757d);
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.vp-board-tag b {
    color: var(--bs-body-color, #212529);
}

.vp-board-tag .vp-board-q {
    font-family: var(--bs-font-sans-serif, system-ui);
    font-weight: 400;
    font-style: italic;
}

/* Skeleton primitives, for the parts a board legitimately elides. */
.sk {
    display: inline-block;
    width: var(--sk-w, 100%);
    height: var(--sk-h, .85rem);
    border-radius: .25rem;
    background: var(--bs-secondary-bg, #e9ecef);
}

.sk-dim { opacity: .55; }
.sk-line { display: block; margin: .3rem 0; }
.sk-block { --sk-h: 6rem; display: block; }
</style>
'''

DOC_OPEN = '''
<div class="vp-doc">

  <div class="vp-doc-chrome">
%(chrome)s
  </div>

  <header class="vp-doc-head">
    <h1>Analyst Profile — candidate <em>&lt;name&gt;</em></h1>
    <p class="vp-doc-sub">
      One design for three boards. Name the direction this candidate
      commits to (09b-prototypes.md §4) and the bet it makes, in two
      sentences, here. Every number below comes from
      <code>prd/analyst-profile/09a-fixtures/</code>.
    </p>
    <div class="vp-doc-jump">
      <a href="#board-index">Index</a>
      <a href="#board-edit">Edit</a>
      <a href="#board-simulate">Simulate</a>
    </div>
  </header>
'''

# MISP's own page header, by hand, because each board's is different.
PAGE_HEADER = '''      <div class="container-fluid py-3">
        <div class="d-flex justify-content-between align-items-center">
          <div class="d-flex flex-column align-items-start">
            <span class="text-muted text-uppercase fw-semibold mb-1"
                  style="font-size:0.68rem; letter-spacing:0.07em;">
              <a href="#" class="text-muted text-decoration-none">AnalystProfiles</a> &gt; %(crumb)s
            </span>
            <div class="d-flex align-items-center gap-2">
              <h1 class="mb-0 fw-bold lh-1 d-flex"
                  style="font-size:2rem; word-break:break-word; max-width:100%%;">
                %(title)s
              </h1>
              %(badge)s
            </div>
            <p class="text-muted mt-1" style="font-size:0.85rem;">
              %(blurb)s
            </p>
          </div>
          <div class="d-flex gap-2 align-items-center flex-wrap">
            <!-- This board's actions. MISP's own button classes. -->
%(actions)s
          </div>
        </div>
      </div>
'''

BOARDS = (
    {
        'slug': 'index',
        'route': '/analystProfiles/index',
        'crumb': 'Index',
        'title': 'Analyst Profiles',
        'badge': '<span class="badge rounded-pill bg-primary'
                 ' fw-semibold px-3">3</span>',
        'blurb': 'The judgements the value assessment is computed from.'
                 ' Exactly one is in force for you.',
        'question': 'Which profile is weighting my pages, and why is it '
                    'not the one I just forked?',
        'actions': '            <a href="#" class="btn btn-outline-primary'
                   ' fw-semibold d-flex align-items-center gap-2">'
                   '<i class="fas fa-file-import"></i>Import</a>\n',
    },
    {
        'slug': 'edit',
        'route': '/analystProfiles/edit/29',
        'crumb': 'Weights I actually use',
        'title': 'Weights I actually use',
        'badge': '<span class="badge rounded-pill bg-secondary'
                 ' fw-semibold px-3">rev 4</span>',
        'blurb': 'Yours, in force. Seven sections; nothing is'
                 ' normalised, so the ledger is the number.',
        'question': 'What is this profile asserting, and how do I change '
                    'one number without reading JSON?',
        'actions': '            <a href="#" class="btn btn-outline-primary'
                   ' fw-semibold d-flex align-items-center gap-2">'
                   '<i class="fas fa-flask"></i>Simulate</a>\n'
                   '            <a href="#" class="btn btn-primary'
                   ' fw-semibold d-flex align-items-center gap-2">'
                   '<i class="fas fa-save"></i>Save</a>\n',
    },
    {
        'slug': 'simulate',
        'route': '/analystProfiles/simulate/29?value=OC44LjguOA==',
        'crumb': 'Simulate',
        'title': 'Simulate',
        'badge': '',
        'blurb': 'What this candidate would do, before it is saved.'
                 ' It computes; it writes nothing.',
        'question': 'What would my change do — to this value, and to the '
                    'values I care about?',
        'actions': '            <a href="#" class="btn btn-outline-secondary'
                   ' fw-semibold d-flex align-items-center gap-2">'
                   '<i class="fas fa-rotate-left"></i>Discard</a>\n'
                   '            <a href="#" class="btn btn-primary'
                   ' fw-semibold d-flex align-items-center gap-2">'
                   '<i class="fas fa-save"></i>Save these weights</a>\n',
    },
)

BODY_HINT = '''        <div class="container-fluid">
          <!--
              Your candidate for this board goes here. MISP's content
              column is `container-fluid`; use ordinary Bootstrap rows.
              Keep a 9/3 split if this board wants a rail, or col-12 for
              the width. At the pinned 1600px page, col-lg-9 is 1200px.
          -->
          <p class="text-muted">Candidate body for the %s board.</p>
        </div>
'''

DOC_CLOSE = '''
  <section class="vp-doc-head" style="border:0;margin-top:1rem">
    <h2 class="h5">What this candidate gives up</h2>
    <p class="vp-doc-sub">
      Every direction in §4 has a risk it has to survive. Say which one
      bit and what you did about it. A candidate with no trade-off has
      not been designed yet.
    </p>
  </section>

</div>
'''

TAIL = '''
<script>
(function () {
    var root = document.documentElement;

    /*
     * Theme bridge. Artifacts stamp data-theme on the root element and
     * otherwise leave it to prefers-color-scheme; MISP switches on
     * data-bs-theme. Mirror one onto the other so a candidate follows
     * the reader's theme in MISP's own palette rather than a second one.
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
})();
</script>
'''


def scrub(markup):
    """Nothing may reach off-host from a published artifact, and an
    artifact naming a private dev instance leaks it to whoever it is
    shared with. The asset tags are meaningless here anyway: the kit
    carries the CSS, and Font Awesome's faces are embedded in it.
    """
    markup = re.sub(r'<script[^>]*>.*?</script>', '', markup, flags=re.S)
    markup = re.sub(r'<img[^>]*>', '', markup)
    markup = re.sub(r'"https?://[^"]*"', '"#"', markup)
    markup = re.sub(r"'https?://[^']*'", "'#'", markup)
    markup = re.sub(r'(src|href)="/[^"]*"', r'\1="#"', markup)
    leaked = re.findall(r'https?://[^\s"\'<>]+', markup)
    if leaked:
        raise SystemExit('!! chrome still names hosts: %s' % leaked[:3])
    return markup


def main():
    if len(sys.argv) < 2:
        raise SystemExit(__doc__)
    dump = open(sys.argv[1], encoding='utf-8', errors='replace').read()

    start = dump.find('<header>')
    if start == -1:
        raise SystemExit('!! no <header> in %s — is it a MISP page?'
                         % sys.argv[1])
    end = dump.find('</header>', start)
    if end == -1:
        raise SystemExit('!! unterminated <header> in %s' % sys.argv[1])
    chrome = scrub(dump[start:end + len('</header>')])
    if 'navbar' not in chrome:
        raise SystemExit('!! the extracted header has no navbar in it')

    parts = [HEAD, DOC_OPEN % {'chrome': chrome}]
    for board in BOARDS:
        parts.append(
            '\n  <div class="vp-board" id="board-%(slug)s">\n'
            '    <div class="vp-board-tag">'
            '<span><b>%(route)s</b></span>'
            '<span class="vp-board-q">%(question)s</span></div>\n'
            % board
        )
        parts.append(PAGE_HEADER % board)
        parts.append(BODY_HINT % board['slug'])
        parts.append('  </div>\n')
    parts.append(DOC_CLOSE)
    parts.append(TAIL)

    with open(OUT, 'w', encoding='utf-8') as handle:
        handle.write(''.join(parts))

    print('wrote %s (%.1f KB, %d boards)'
          % (os.path.relpath(OUT), os.path.getsize(OUT) / 1024.0,
             len(BOARDS)))


main()
