#!/bin/bash
#
# Check a built mockup before publishing it (PRD §7.3, §7.6).
#
#   python3 prd/phase7/kit/inline-kit.py prd/phase7/mockups/<tab>.html
#   bash prd/phase7/kit/check-mockup.sh prd/phase7/build/<tab>.html
#
# Renders the file in headless Chrome in both themes and asserts the things
# that are invisible until they are wrong: that the kit resolved at all, that
# exactly one tab is active, that nothing reaches off-host, and that every
# candidate has a body with height.
#
# The first assertion is the important one. A mockup whose CSS failed to apply
# renders as unstyled HTML, and unstyled HTML passes a colour check for the
# wrong reason — the trap that made a whole verification sweep vacuous in §6.1.
#
set -u

FILE=${1:-}
if [ -z "$FILE" ] || [ ! -f "$FILE" ]; then
    echo "usage: check-mockup.sh prd/phase7/build/<tab>.html" >&2
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
      out.push('FAIL  kit did not apply (--vp-mal empty) — every other check');
      out.push('      below would pass for the wrong reason. Aborting.');
    } else {
      out.push('ok    kit applied            --vp-mal ' + mal
               + ' · --bs-body-bg ' + cs.getPropertyValue('--bs-body-bg').trim());

      var frames = document.querySelectorAll('.vp-frame');
      out.push((frames.length ? 'ok   ' : 'FAIL ') + ' frames               '
               + frames.length);

      frames.forEach(function (f, i) {
        var tabs = f.querySelectorAll('.nav-tabs .nav-item').length;
        var act = f.querySelectorAll('.nav-tabs .nav-link.active');
        var name = act.length === 1 ? act[0].getAttribute('href') : '?';
        var good = tabs === 9 && act.length === 1;
        out.push((good ? 'ok    ' : 'FAIL  ') + 'frame ' + (i + 1)
                 + '              ' + tabs + ' tabs, active ' + name
                 + (act.length === 1 ? '' : ' (' + act.length + ' active)'));
      });

      // Four is the brief; more than four is a deck that also carries a
      // composite drawn after review, which is legitimate.
      var cands = document.querySelectorAll('.vp-cand');
      out.push((cands.length >= 4 ? 'ok    ' : 'WARN  ') + 'candidates          '
               + cands.length + (cands.length >= 4 ? '' : ' (expected 4+)'));

      // Measure the scaler, not the viewport box. The box is the scaler
      // times --vp-scale, and 'fit' divides the lane by the candidate
      // count -- so the same candidate measures smaller in a deck of five
      // than in a deck of four, and a fixed pixel floor on the box starts
      // failing candidates for the size of the deck they are in. The
      // scaler's own height is what the assertion is actually about:
      // whether the candidate has a body at all.
      cands.forEach(function (c) {
        var inner = c.querySelector('.vp-cand-scaler');
        var h = inner ? Math.round(inner.offsetHeight) : 0;
        var id = c.id || '?';
        out.push((h > 500 ? 'ok    ' : 'FAIL  ') + 'candidate ' + id
                 + '        body height ' + h + 'px (unscaled)');
      });

      var off = document.querySelectorAll(
        '[href^="http"]:not([href^="#"]), [src^="http"]');
      out.push((off.length === 0 ? 'ok    ' : 'FAIL  ')
               + 'no off-host refs     ' + off.length);

      var wide = document.documentElement.scrollWidth
               - document.documentElement.clientWidth;
      out.push((wide <= 2 ? 'ok    ' : 'WARN  ') + 'no page-level x-scroll  '
               + wide + 'px');
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

FAILED=0
for theme in light dark; do
    echo "── $theme ──────────────────────────────────────────────"
    prof=$(mktemp -d)
    google-chrome --headless=new --disable-gpu --no-sandbox \
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
