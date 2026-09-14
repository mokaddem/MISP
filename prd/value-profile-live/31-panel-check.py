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

# ------------------------------------------------------------------
# The three cards the 2026-09-14 reading pass changed. Each assertion
# is a defect that was live, not a feature that merely exists.
# ------------------------------------------------------------------
print("== the reading pass ==")

_, html = fetch("viewReporting", "8.8.8.8")
check(html.count("fa-shield-halved") == len(re.findall(r"vp-stance-", html)),
      "every stance chip carries the to_ids shield")
check("vp-stance-yes" in html and "vp-stance-mixed" in html,
      "and the three states are distinguishable classes")
years = re.findall(r'vp-spark-tick">(\d{4})<', html)
check(len(years) >= 2, f"the month strip carries year ticks ({years})")
check(years == sorted(years), "in order")
# Anchored on `class="`, because a bare `vp-spark-bar` also matches
# `vp-spark-bar-empty` in the same attribute and counts a silent month
# twice — as does `vp-spark-slot` against `vp-spark-slot-tick`.
slots = len(re.findall(r'class="vp-spark-slot', html))
bars = len(re.findall(r'class="vp-spark-bar', html))
check(slots == bars,
      f"one scale slot per bar, so a tick lands on its own month"
      f" ({slots} vs {bars})")

_, html = fetch("viewSightings", "8.8.8.8")
# Was type-0 only: 47 reports drawn, 6 dropped, under two tiles
# counting exactly those 6.
check("vp-spark-seg-fp" in html and "vp-spark-seg-exp" in html,
      "the sparkline draws false positives and expirations")
check("vp-spark-down" in html, "below the line, as the tab's chart does")
check(re.search(r"53\s+reports", html) is not None,
      "the reporter subhead names its unit and its total")
# Was one purple bar per organisation, summing to 53 under a tile
# reading 47.
check(html.count("vp-reporter-seg-fp") >= 1,
      "and each organisation's bar is split by what it reported")

# A value nobody has contradicted keeps the unsigned strip it had.
_, html = fetch("viewSightings", "google.com")
check("vp-spark-down" not in html,
      "a value with no contradiction grows no second region")

# Was: one events total under two lines, belonging to neither.
_, html = fetch("viewExternal", "8.8.8.8")
check("remote events name it" in html,
      "the external line carries its own remote-event count")
check("remote events name this value" not in html,
      "and the unattributed total under both lines is gone")

# ------------------------------------------------------------------
# The Distribution column: the card drew `Attribute.distribution` while
# the tab two clicks away drew the chain it resolves to, so one row read
# `Inherited` on the Overview and `This community only` on Occurrences.
# ------------------------------------------------------------------
print("== the distribution column ==")


def dist_by_attr(html):
    """attribute id -> the level label its badge carries.

    Every cell opens with the chain on the wrapper and the level on the
    badge inside it, so the second `title` is the label on both
    surfaces — the tab adds a third for the sharing-group link.
    """
    out = {}
    for part in re.split(r"<tr\b", html)[1:]:
        pid = re.search(r'data-primary-id="(\d+)"', part)
        cell = re.search(r"idx-col-value_distribution.*?</td>", part, re.S)
        if not pid or not cell:
            continue
        titles = re.findall(r'title="([^"]*)"', cell.group(0))
        out[pid.group(1)] = titles[1] if len(titles) > 1 else None
    return out


_, card = fetch("viewOccurrences", "8.8.8.8")
_, tab = fetch("viewOccurrenceTable", "8.8.8.8")
check("idx-col-distribution" not in card,
      "the card is off the shared attribute-column renderer")
cardLevels, tabLevels = dist_by_attr(card), dist_by_attr(tab)
check(len(cardLevels) == len(re.findall(r"vp-occ-type-", card)),
      f"every card row draws a distribution ({len(cardLevels)})")
check(all(v for v in cardLevels.values()),
      "and none of them draws an empty cell")
shared = [k for k in cardLevels if k in tabLevels]
check(len(shared) == len(cardLevels),
      f"the card's rows are all in the tab ({len(shared)})")
check(all(cardLevels[k] == tabLevels[k] for k in shared),
      "and the two surfaces name the same level for each")
check(any("Inherited" in (t or "") for t in
          re.findall(r'title="(Attribute: [^"]*)"', card)),
      "the chain is stated, inherited links included")

# The tightest level on the page, and the one the attribute's own
# column never carries: all four of these rows are level 5 there.
_, card = fetch("viewOccurrences", "23.94.99.61")
levels = dist_by_attr(card)
check(all(v == "Your organisation only" for v in levels.values()),
      f"an org-only event makes its rows org-only ({levels})")

# A sharing group is named on both surfaces: under the badge where there
# is width for a line, in the title where the card holds one line a row.
_, card = fetch("viewOccurrences", "7.7.7.7")
_, tab = fetch("viewOccurrenceTable", "7.7.7.7")
check("Sharing group ·" in card and "sharing_groups/view" not in card,
      "the card names the sharing group in the title, on one line")
check("sharing_groups/view" in tab,
      "and the tab still links it under the badge")

print(f"\n{checks} checks, {failures} failures")
sys.exit(1 if failures else 0)
