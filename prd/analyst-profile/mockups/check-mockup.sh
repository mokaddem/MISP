#!/bin/bash
#
# Check a built phase 8b candidate before publishing it.
#
#   python3 prd/phase7/kit/inline-kit.py \
#       prd/analyst-profile/mockups/<name>.html
#   bash prd/analyst-profile/mockups/check-mockup.sh \
#       prd/analyst-profile/build/<name>.html
#
# Renders the file in headless Chrome in both themes and asserts the
# things that are invisible until they are wrong.
#
# It is `prd/phase7/kit/check-mockup.sh` with the value page's
# assertions swapped for these boards': that one requires nine tabs and
# four candidates per file, and here there are three boards and one
# candidate. What is kept unchanged is the assertion that matters most.
#
# **A mockup whose CSS did not apply still renders.** It renders as
# unstyled HTML, and unstyled HTML passes a colour check for the wrong
# reason — the trap that made a whole verification sweep vacuous in
# phase 7 §6.1. So `--vp-mal` is asserted to resolve before anything
# else is asserted at all, and the run aborts if it does not.
#
set -u

FILE=${1:-}
if [ -z "$FILE" ] || [ ! -f "$FILE" ]; then
    echo "usage: check-mockup.sh prd/analyst-profile/build/<name>.html" >&2
    exit 2
fi

FILE=$(readlink -f "$FILE")
WORK=$(mktemp -d)
PORT=$(python3 -c 'import socket;s=socket.socket();s.bind(("127.0.0.1",0));print(s.getsockname()[1]);s.close()')

python3 - "$FILE" "$WORK" <<'PY'
import os
import sys

src, work = sys.argv[1], sys.argv[2]
body = open(src, encoding='utf-8').read()

PROBE = """
<script>
window.addEventListener('load', function () {
  setTimeout(function () {
    var out = [];
    var cs = getComputedStyle(document.documentElement);
    var mal = cs.getPropertyValue('--vp-mal').trim();
    if (!mal) {
      out.push('FAIL  kit did not apply (--vp-mal empty) — every other');
      out.push('      check below would pass for the wrong reason.');
      out.push('      Aborting.');
    } else {
      out.push('ok    kit applied            --vp-mal ' + mal
               + ' · --bs-body-bg ' + cs.getPropertyValue('--bs-body-bg').trim());

      // The two tokens the diff column needs. A red/green pair is doing
      // real work there and must come from these rather than from raw
      // Bootstrap colours (09-editor.md §7c item 2).
      var withC = cs.getPropertyValue('--vp-dir-with').trim();
      var against = cs.getPropertyValue('--vp-dir-against').trim();
      out.push((withC && against ? 'ok    ' : 'FAIL  ')
               + 'direction tokens     with ' + (withC || '(empty)')
               + ' · against ' + (against || '(empty)'));

      var boards = document.querySelectorAll('.vp-board');
      out.push((boards.length === 3 ? 'ok    ' : 'FAIL  ')
               + 'boards               ' + boards.length
               + (boards.length === 3 ? '' : ' (expected 3)'));

      var want = ['board-index', 'board-edit', 'board-simulate'];
      want.forEach(function (id) {
        var el = document.getElementById(id);
        var h = el ? Math.round(el.offsetHeight) : 0;
        // A board that is only its frame is about 200px. A drawn one is
        // taller; the floor catches the board somebody forgot to fill.
        out.push((h > 420 ? 'ok    ' : 'FAIL  ') + 'height ' + id
                 + (id.length < 14 ? '   ' : '')
                 + '   ' + h + 'px');
      });

      // The chrome belongs to the document, once, not to each board.
      var navs = document.querySelectorAll('.vp-doc-chrome .navbar');
      out.push((navs.length === 1 ? 'ok    ' : 'WARN  ')
               + 'one navbar           ' + navs.length);

      var off = document.querySelectorAll(
        '[href^="http"]:not([href^="#"]), [src^="http"]');
      out.push((off.length === 0 ? 'ok    ' : 'FAIL  ')
               + 'no off-host refs     ' + off.length
               + (off.length ? ' (' + off[0].outerHTML.slice(0, 70) + ')' : ''));

      // A published artifact must not name the dev instance.
      var leaked = document.documentElement.innerHTML
        .match(/https?:\\/\\/localhost/g);
      out.push((leaked ? 'FAIL  ' : 'ok    ') + 'no dev host named    '
               + (leaked ? leaked.length + ' mentions' : '0'));

      var wide = document.documentElement.scrollWidth
               - document.documentElement.clientWidth;
      out.push((wide <= 2 ? 'ok    ' : 'WARN  ') + 'no page x-scroll     '
               + wide + 'px');

      // Every figure should come from the fixtures, so a candidate that
      // still carries the frame's own placeholder text is unfinished.
      var stub = (document.body.textContent || '')
        .indexOf('Candidate body for the');
      out.push((stub === -1 ? 'ok    ' : 'FAIL  ')
               + 'no frame placeholder ' + (stub === -1 ? 'clean'
               : 'a board is still the frame default'));
    }
    var pre = document.createElement('pre');
    pre.id = 'vp-probe';
    pre.textContent = out.join('\\n');
    document.body.appendChild(pre);
  }, 400);
});
</script>
"""

for theme in ('light', 'dark'):
    page = ('<!doctype html><html data-theme="%s"><head><meta charset="utf-8">'
            '</head><body>' % theme) + body + PROBE + '</body></html>'
    with open(os.path.join(work, '%s.html' % theme), 'w',
              encoding='utf-8') as handle:
        handle.write(page)
PY

python3 -m http.server "$PORT" --bind 127.0.0.1 --directory "$WORK" \
    >/dev/null 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null; rm -rf "$WORK"' EXIT
sleep 1

CHROME=$(command -v google-chrome || command -v chromium \
    || command -v chromium-browser || true)
if [ -z "$CHROME" ]; then
    echo "!! no headless Chrome on PATH (google-chrome / chromium)" >&2
    exit 2
fi

FAILED=0
for theme in light dark; do
    echo "── $theme ──────────────────────────────────────────────"
    prof=$(mktemp -d)
    "$CHROME" --headless=new --disable-gpu --no-sandbox \
        --user-data-dir="$prof" --window-size=1700,1400 \
        --virtual-time-budget=20000 \
        --dump-dom "http://127.0.0.1:$PORT/$theme.html" 2>/dev/null \
        > "$WORK/dom_$theme.html"
    rm -rf "$prof"
    python3 - "$WORK/dom_$theme.html" <<'PY'
import html
import re
import sys
d = open(sys.argv[1], encoding='utf-8', errors='replace').read()
m = re.search(r'id="vp-probe"[^>]*>(.*?)</pre>', d, re.S)
text = html.unescape(m.group(1)).strip() if m else 'FAIL  no probe output'
print(text)
sys.exit(1 if 'FAIL' in text else 0)
PY
    [ $? -ne 0 ] && FAILED=1
done

echo
if [ $FAILED -eq 0 ]; then
    echo "PASS — ready to publish"
else
    echo "FAILURES above — fix before publishing"
fi
exit $FAILED
