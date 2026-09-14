#!/usr/bin/env python3
"""Phase 31: the two Overview panels it changed, over HTTP.

`viewOccurrences` lost three columns and two thirds of its cap;
`viewReporting` is new. This asserts what the fragments must contain
rather than only that they answered 200 — a panel that renders an
exception trace inside a 200 is the failure mode a status check misses.

The values are `31-query-count.php`'s, plus one nothing holds: the
empty state is a branch, and it is the one that says *no event you can
see* rather than *no occurrences*.

Run: python3 prd/value-profile-live/31-panel-check.py
"""
import re, base64, urllib3, requests, sys

urllib3.disable_warnings()
BASE = "https://localhost"
s = requests.Session(); s.verify = False
r = s.get(BASE + "/users/login", timeout=30)
f = dict(re.findall(r'<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"', r.text))
f["data[User][email]"] = "admin@admin.test"
f["data[User][password]"] = "admin"
r = s.post(BASE + "/users/login", data=f, timeout=30)
if "/users/login" in r.url:
    print("LOGIN FAILED"); sys.exit(1)

VALUES = ["8.8.8.8", "443", "0.0.0.0", "sage.png", "1.162.239.42",
          "no-such-value-anywhere.invalid"]

checks = failures = 0


def check(ok, label):
    global checks, failures
    checks += 1
    if not ok:
        failures += 1
    print(("  ok    " if ok else "  FAIL  ") + label)


def fetch(panel, value):
    b64 = base64.urlsafe_b64encode(value.encode()).decode().rstrip("=")
    r = s.get(f"{BASE}/values/{panel}/{b64}",
              headers={"X-Requested-With": "XMLHttpRequest"}, timeout=240)
    return r.status_code, r.text


for value in VALUES:
    print(f"== {value} ==")

    code, html = fetch("viewOccurrences", value)
    check(code == 200, f"viewOccurrences answers 200 ({code})")
    check("Fatal error" not in html and "Notice (" not in html,
          "and it is not a trace inside a 200")
    rows = html.count("<tr", 1) and len(re.findall(r"vp-occ-type-", html))
    check(rows <= 8, f"at most 8 rows drawn ({rows})")
    # The three things phase 31 took off this card.
    check("multi_select_toolbar" not in html and "mass_sighting" not in html,
          "no mass-action toolbar")
    check('idx-col-checkbox' not in html, "no selection column")
    check('idx-col-datetime' not in html, "no Last seen column")
    # And the one it put on.
    if rows:
        check("vp-event-ref" in html, "the compact event reference is used")
        check("Badges/event" not in html and 'class="rounded border"'
              not in html, "and the two-line badge is not")
    else:
        check("No event you can see carries this value." in html,
              "the empty state distinguishes absent from hidden")

    code, html = fetch("viewReporting", value)
    check(code == 200, f"viewReporting answers 200 ({code})")
    check("Fatal error" not in html and "Notice (" not in html,
          "and it is not a trace inside a 200")
    if "vp-reporting" in html:
        bars = len(re.findall(r"vp-spark-bar", html))
        # `vp-reporter\b` also matches -name, -track, -fill and -count,
        # which counts one organisation five times.
        orgs = len(re.findall(r'class="vp-reporter"', html))
        check(bars >= 1, f"the month strip draws bars ({bars})")
        check(1 <= orgs <= 6, f"the split is capped at 6 organisations ({orgs})")
        check("Occurrences by organisation" in html, "and names what it counts")
        check(len(re.findall(r"vp-stance-", html)) == orgs,
              "every organisation carries a to_ids stance")
        # Both denominators live in the header.
        check("Reported in" in html and "live occurrence" in html,
              "the header carries both denominators")
        # A one-month span prints one axis label, not a range that does
        # not range.
        axis = re.search(r'vp-spark-axis"?>(.*?)</div>', html, re.S)
        labels = re.findall(r"\d{4}-\d{2}", axis.group(1)) if axis else []
        check(len(set(labels)) == len(labels),
              f"the axis does not print one month twice ({labels})")
    else:
        check("No event you can see carries this value." in html,
              "the empty state distinguishes absent from hidden")

print(f"\n{checks} checks, {failures} failures")
sys.exit(1 if failures else 0)
