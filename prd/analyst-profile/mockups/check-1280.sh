#!/bin/bash
#
# The 1280px re-check (09b-revisions.md section 8).
#
#   bash prd/analyst-profile/mockups/check-1280.sh
#
# The workbench's degradation is a container query on the *content
# column*, not a media query on the window, so it has to be measured
# rather than trusted. The mockup used to carry a live 1280px inset to
# show it; that inset explained the drawing rather than the product
# (section 4.1), so it is gone and this asserts the same thing instead.
#
# At a 1280px window the content column is about 1231px, which is under
# the 1400px breakpoint: the outline stops being a column and becomes a
# row, the wide-only cells go, and what must never go -- the bench, the
# value, the quality, the delta, the band, every row that moved --
# stays.
#
set -u
cd "$(dirname "$0")/../../.." || exit 1

FILE=$(readlink -f prd/analyst-profile/build/workbench.html)
WORK=$(mktemp -d)
PORT=$(python3 -c 'import socket;s=socket.socket();s.bind(("127.0.0.1",0));print(s.getsockname()[1]);s.close()')

python3 - "$FILE" "$WORK" <<'PY'
import os, sys
src, work = sys.argv[1], sys.argv[2]
body = open(src, encoding='utf-8').read()
PROBE = """
<script>
window.addEventListener('load', function () {
  setTimeout(function () {
    var out = [];
    var col = document.querySelector('#board-edit .container-fluid');
    var wb = document.querySelector('#board-edit .wb');
    out.push('content column   ' + Math.round(col.getBoundingClientRect().width) + 'px');
    var cols = getComputedStyle(wb).gridTemplateColumns.split(' ').length;
    out.push((cols === 2 ? 'ok    ' : 'FAIL  ')
             + 'degraded to 2 cols  ' + cols);
    var rail = document.querySelector('#board-edit .wb-rail');
    var flow = getComputedStyle(rail).flexDirection || '(none)';
    out.push('rail flex-direction ' + flow);
    var hidden = document.querySelector('#board-edit .wb-wide-only')
      || document.querySelector('.wb-wide-only');
    out.push((hidden && getComputedStyle(hidden).display === 'none'
              ? 'ok    ' : 'WARN  ')
             + 'wide-only hidden    '
             + (hidden ? getComputedStyle(hidden).display : 'none found'));
    var wide = document.documentElement.scrollWidth
             - document.documentElement.clientWidth;
    out.push((wide <= 2 ? 'ok    ' : 'FAIL  ')
             + 'no page x-scroll    ' + wide + 'px');
    var bench = document.querySelector('#board-edit .wb-bench');
    out.push((bench && bench.offsetHeight > 200 ? 'ok    ' : 'FAIL  ')
             + 'bench still drawn   ' + (bench ? bench.offsetHeight : 0) + 'px');
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
    open(os.path.join(work, '%s.html' % theme), 'w',
         encoding='utf-8').write(page)
PY

python3 -m http.server "$PORT" --bind 127.0.0.1 --directory "$WORK" \
    >/dev/null 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null; rm -rf "$WORK"' EXIT
sleep 1

CHROME=$(command -v google-chrome || command -v chromium \
    || command -v chromium-browser || true)
[ -z "$CHROME" ] && { echo "no headless chrome" >&2; exit 2; }

FAILED=0
for theme in light dark; do
    echo "-- $theme at 1280x1000 --"
    prof=$(mktemp -d)
    "$CHROME" --headless=new --disable-gpu --no-sandbox \
        --user-data-dir="$prof" --window-size=1280,1000 \
        --virtual-time-budget=20000 \
        --dump-dom "http://127.0.0.1:$PORT/$theme.html" 2>/dev/null \
        > "$WORK/dom_$theme.html"
    rm -rf "$prof"
    python3 - "$WORK/dom_$theme.html" <<'PY'
import html, re, sys
d = open(sys.argv[1], encoding='utf-8', errors='replace').read()
m = re.search(r'id="vp-probe"[^>]*>(.*?)</pre>', d, re.S)
text = html.unescape(m.group(1)).strip() if m else 'FAIL  no probe output'
print(text)
sys.exit(1 if 'FAIL' in text else 0)
PY
    [ $? -ne 0 ] && FAILED=1
done
echo
[ $FAILED -eq 0 ] && echo "1280px OK" || echo "1280px FAILURES"
exit $FAILED
