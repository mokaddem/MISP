#!/bin/bash
#
# The editor as a browser actually lays it out, in both themes.
#
# The render harness asserts the markup and the HTTP probe asserts the
# transport; neither can see a stylesheet that did not apply or a claim
# about rank that the grid does not keep. **A page whose CSS did not
# apply still renders** — as unstyled HTML, which passes a colour check
# for the wrong reason (phase 7 §6.1) — so `--vp-mal` is asserted to
# resolve before anything else is asserted at all.
#
# What it measures beyond that is 09b-decision.md §3's correction: the
# three axes are drawn at **one rank**, which is a claim about widths
# and is therefore measured rather than asserted.
#
#   bash prd/analyst-profile/09c-wiring-page-check.sh \
#        https://localhost admin@admin.test admin
#
set -u

BASE=${1:-https://localhost}
EMAIL=${2:-admin@admin.test}
PASS=${3:-admin}
JAR=$(mktemp)
WORK=$(mktemp -d)
PORT=$(python3 -c 'import socket;s=socket.socket();s.bind(("127.0.0.1",0));print(s.getsockname()[1]);s.close()')
trap 'rm -rf "$JAR" "$WORK"; kill ${SERVER:-0} 2>/dev/null' EXIT

CHROME=$(command -v google-chrome || command -v chromium \
    || command -v chromium-browser || true)
if [ -z "$CHROME" ]; then
    echo "!! no headless Chrome on PATH" >&2
    exit 2
fi

# ---------------------------------------------------------------- login
PAGE=$(curl -sk -c "$JAR" "$BASE/users/login")
grab() {
    printf '%s' "$PAGE" \
        | grep -o "name=\"data\[_Token\]\[$1\]\" value=\"[^\"]*\"" \
        | head -1 | sed 's/.*value="//;s/"$//'
}
CODE=$(curl -sk -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code}' \
    -X POST "$BASE/users/login" \
    --data-urlencode "data[_Token][key]=$(grab key)" \
    --data-urlencode "data[_Token][fields]=$(grab fields)" \
    --data-urlencode "data[_Token][unlocked]=$(grab unlocked)" \
    --data-urlencode "data[User][email]=$EMAIL" \
    --data-urlencode "data[User][password]=$PASS")
if [ "$CODE" != "302" ]; then
    echo "FAILED to log in as $EMAIL (HTTP $CODE)" >&2
    exit 2
fi

VALUE=$(printf '%s' '8.8.8.8' | base64 | tr '+/' '-_' | tr -d '=')
DEFAULT=$(curl -sk -b "$JAR" "$BASE/analystProfiles/index" \
    | grep -o '/analystProfiles/view/[0-9]*' | head -1 | grep -o '[0-9]*$')

fetch() {
    curl -sk -b "$JAR" "$BASE$1" -o "$WORK/raw.html"
    python3 - "$WORK/raw.html" "$BASE" "$WORK/$2" "$3" <<'PY'
import re
import sys

src, base, dest, theme = sys.argv[1:5]
page = open(src, encoding='utf-8', errors='replace').read()

# Absolute, so the assets load from the instance while the document is
# served from here — the session cookie is not needed for webroot.
page = re.sub(r'(href|src)="/(?!/)', r'\1="' + base + '/', page)
page = re.sub(r'data-bs-theme="[^"]*"', 'data-bs-theme="' + theme + '"',
              page, count=1)
if 'data-bs-theme' not in page.split('>', 2)[0] + page.split('>', 2)[1]:
    page = page.replace('<html', '<html data-bs-theme="' + theme + '"', 1)

probe = """
<pre id="ap-probe" style="position:fixed;left:-9999px"></pre>
<script>
window.addEventListener('load', function () {
  setTimeout(function () {
    var out = [];
    var cs = getComputedStyle(document.documentElement);
    var mal = cs.getPropertyValue('--vp-mal').trim();
    if (!mal) {
      out.push('FAIL  the stylesheet did not apply (--vp-mal empty).');
      out.push('      Every check below would pass for the wrong reason.');
    } else {
      out.push('ok    stylesheet applied   --vp-mal ' + mal
        + ' | --bs-body-bg ' + cs.getPropertyValue('--bs-body-bg').trim());
      var w = cs.getPropertyValue('--vp-dir-with').trim();
      var a = cs.getPropertyValue('--vp-dir-against').trim();
      out.push((w && a ? 'ok    ' : 'FAIL  ')
        + 'direction pair       with ' + (w || '(empty)')
        + ' | against ' + (a || '(empty)'));

      var wb = document.querySelector('.wb');
      out.push((wb ? 'ok    ' : 'FAIL  ') + 'the workbench        '
        + (wb ? Math.round(wb.offsetWidth) + 'px wide' : 'missing'));

      var bench = document.querySelector('.wb-bench');
      out.push((bench && bench.offsetHeight > 200 ? 'ok    ' : 'FAIL  ')
        + 'the bench            '
        + (bench ? Math.round(bench.offsetHeight) + 'px tall' : 'missing'));

      // 09b-decision.md section 3: the three axes are at one rank, and
      // rank here means width. Measured, because the correction was
      // made for a reader who saw two of them demoted.
      var ax = document.querySelectorAll('.bench-ax3 > .ax');
      if (ax.length !== 3) {
        out.push('FAIL  the assessment head has ' + ax.length
          + ' cells, not 3');
      } else {
        var widths = [];
        for (var i = 0; i < ax.length; i++) {
          widths.push(Math.round(ax[i].getBoundingClientRect().width));
        }
        var spread = Math.max.apply(null, widths)
          - Math.min.apply(null, widths);
        out.push((spread <= 1 ? 'ok    ' : 'FAIL  ')
          + 'three axes at one rank  ' + widths.join(' / ')
          + '  spread ' + spread + 'px');
      }

      var rail = document.querySelector('.wb-rail');
      var open_ = document.querySelectorAll('.wb-sec.is-open');
      out.push((open_.length === 1 ? 'ok    ' : 'FAIL  ')
        + 'one section open     ' + open_.length);

      // Nothing may push the page sideways.
      var over = document.documentElement.scrollWidth
        - document.documentElement.clientWidth;
      out.push((over <= 1 ? 'ok    ' : 'FAIL  ')
        + 'no sideways scroll   ' + over + 'px over');
    }
    document.getElementById('ap-probe').textContent = out.join('\\n');
  }, 900);
});
</script>
"""
page = page.replace('</body>', probe + '</body>', 1)
open(dest, 'w', encoding='utf-8').write(page)
PY
}

python3 -m http.server "$PORT" --bind 127.0.0.1 --directory "$WORK" \
    >/dev/null 2>&1 &
SERVER=$!
sleep 1

FAILED=0
for width in 1600 1280; do
    for theme in light dark; do
        echo "── ${width}px · $theme ─────────────────────────────────"
        fetch "/analystProfiles/edit/$DEFAULT?value=$VALUE" \
            "page-$width-$theme.html" "$theme"
        prof=$(mktemp -d)
        "$CHROME" --headless=new --disable-gpu --no-sandbox \
            --ignore-certificate-errors \
            --user-data-dir="$prof" --window-size="$width,1400" \
            --virtual-time-budget=20000 \
            --dump-dom "http://127.0.0.1:$PORT/page-$width-$theme.html" \
            2>/dev/null > "$WORK/dom-$width-$theme.html"
        rm -rf "$prof"
        python3 - "$WORK/dom-$width-$theme.html" <<'PY'
import html
import re
import sys

dom = open(sys.argv[1], encoding='utf-8', errors='replace').read()
found = re.search(r'id="ap-probe"[^>]*>(.*?)</pre>', dom, re.S)
text = html.unescape(found.group(1)).strip() if found \
    else 'FAIL  no probe output'
print(text)
sys.exit(1 if 'FAIL' in text else 0)
PY
        [ $? -ne 0 ] && FAILED=1
    done
done

echo
if [ $FAILED -eq 0 ]; then
    echo 'PASS in both themes, at both widths'
else
    echo 'FAILURES above'
fi
exit $FAILED
