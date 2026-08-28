# Scratch harness: driving a Value Profile panel in a real browser

Phase 24's graph is JavaScript, so it had to be looked at rather than
asserted about (`23-sightings.md` §10.6 makes the same point about a
curve, and phase 23 built the first version of this). This is the
harness, written down because it needs no session cookie and takes about
ten minutes to set up from cold.

**Not part of the application.** Nothing here ships; it lives beside the
phase document for the reason §14.8 of the contract gives.

---

## What it does

Renders one panel fragment through
[`24-relationships-render.php`](24-relationships-render.php), serves
`app/webroot` statically, and loads the fragment into a page **the way
`mispOvermind.js` does** — `innerHTML`, then every `<script>` rebuilt and
appended to `<head>` **without copying its `type`**. That last detail is
the point of doing it this way rather than opening the fragment
directly: it is what forces a panel to poll for an asynchronously
fetched library, and it is what turns a `<script type="application/json">`
data block into a syntax error. Both were found here.

## Setup

```sh
# 1. Render the fragments (see 24-relationships-render.php's header).
app/Console/cake ValueRender panels 1 8.8.8.8 /tmp/vp

# 2. Copy one out and fix the console's asset prefix.
sed -e 's#/var/www/MISP/app/Console/#/#g' \
    /tmp/vp/value_relation_graph.html > app/webroot/__frag.html

# 3. Drop the page below at app/webroot/__harness.html and serve the
#    webroot, so /js and /css resolve as they do in the real page.
python3 -m http.server 8917 --directory app/webroot

# 4. Drive it. Playwright comes from a sibling checkout; any Chromium
#    with CDP will do.
node harness.mjs http://127.0.0.1:8917 /__frag.html dark out.png

# 5. Remove the two files from app/webroot when finished.
```

## The page

```html
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<link rel="stylesheet" href="/css/bootstrap5-custom.min.css">
<link rel="stylesheet" href="/css/value-profile.css">
<style>
  body { padding: 12px; }
  /* The rail, at the width col-lg-3 gives it on a 1500px viewport. */
  #rail { width: 340px; }
</style>
</head>
<body>
<div id="rail"><div class="ajax-card" id="target"></div></div>
<script>
// A faithful copy of loadAjaxContainer's script handling in
// mispOvermind.js. `type` is deliberately not copied, because the real
// loader does not copy it either.
(function () {
  var params = new URLSearchParams(location.search);
  document.documentElement.setAttribute(
    'data-bs-theme', params.get('theme') || 'light'
  );
  var container = document.getElementById('target');
  fetch(params.get('frag'))
    .then(function (r) { return r.text(); })
    .then(function (html) {
      container.innerHTML = html;
      container.querySelectorAll('script').forEach(function (oldScript) {
        var newScript = document.createElement('script');
        if (oldScript.src) {
          newScript.src = oldScript.src;
        } else {
          newScript.textContent = oldScript.textContent;
        }
        document.head.appendChild(newScript);
        document.head.removeChild(newScript);
      });
    });
})();
</script>
</body>
</html>
```

## What to assert, and what not to

**Assert computed values, not attributes.** Pivotick writes
`stroke="var(--vp-rel-co)"`, so reading the attribute proves only that
the caller passed a variable; reading `getComputedStyle(el).stroke`
proves the variable *resolves*, and to what. Phase 24's colour check is a
tally of computed strokes against the theme's own token values, in both
themes — which is §6.1's standing rule (assert the token resolves before
asserting any colour) applied to a canvas.

**Dashes are a class here, not an attribute.** Pivotick adds `.dashed`
and lets CSS do the rest, so `stroke-dasharray` on the element is
`none` and the computed value is what carries it.

**Wait for the layout, not for the DOM.** A force simulation shows an
*"Optimizing node positions"* panel while it settles and the nodes are
not where they will be. Poll until that text is gone, then wait once
more.

**Screenshot and look at it.** Every graph problem phase 24 found — the
white canvas on a dark page, the edit affordance in the overlay, the
truncated labels, the label clutter that decided the rail draws none —
was visible in a screenshot and invisible in an assertion. The assertions
were all passing.
