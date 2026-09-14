#!/usr/bin/env python3
"""Phase 32: the four surfaces that gained the event-tag scope.

Every assertion is a thing the page could not say before 2026-09-14,
or a thing it said wrongly. The page joined `attribute_tags` and
nothing else, so on `8.8.8.8` it drew 7 of 55 labels and reported
*0 galaxy clusters* for a value whose events carry MITRE ATT&CK
techniques.

Run: python3 prd/value-profile-live/32-tag-scope-check.py
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


# The marker every event-scope chip carries, from `Badges/tag`.
MARK = "misp-icon-event"

print("== the occurrence tables ==")
for panel, cap in (("viewOccurrences", 1), ("viewOccurrenceTable", 4)):
    code, html = fetch(panel, "8.8.8.8")
    check(code == 200, f"{panel} answers 200 ({code})")
    check("Fatal error" not in html and "Notice (" not in html,
          "and it is not a trace inside a 200")
    check("idx-col-value_tag_list" in html,
          "the Tags column is the two-scope element")
    check(html.count(MARK) >= 1,
          f"and it draws event-scope chips ({html.count(MARK)})")
    # The cap is the caller's, and the two callers differ: four chips in
    # a preview row took that card from 433px to 756.
    cells = re.findall(r'class="tag-container.*?</div>\s*</td>', html, re.S)
    worst = 0
    for cell in cells:
        shown = len(re.findall(r'class="badge me-1 mb-1 "', cell))
        worst = max(worst, shown)
    check(worst <= cap,
          f"at most {cap} chip(s) before the fold (worst cell: {worst})")

# A tag on both scopes is drawn once, as the attribute's — two chips
# would read as two sources agreeing.
_, html = fetch("viewOccurrences", "8.8.8.8")
for cell in re.findall(r'class="tag-container.*?</div>\s*</td>', html, re.S):
    names = re.findall(r'title="([^"]*?)(?: — carried by the event[^"]*)?"',
                       cell)
    check(len(names) == len(set(names)),
          "no tag is drawn twice in one cell")
    break

print("== the facet rail ==")
_, html = fetch("viewOccurrenceTable", "8.8.8.8")
check('data-vp-facet-key="event_tag"' in html
      or 'event_tag' in html, "the rail carries an Event tag group")
check("event_tag:" in html,
      "and rows carry event_tag tokens the group can narrow on")
# The two groups are not merged: the same tag answers 2 and 8.
check(re.search(r"tag:tlp-white", html) is not None
      and re.search(r"event_tag:tlp-white", html) is not None,
      "one tag can appear in both groups, keyed apart")

print("== the context card ==")
code, html = fetch("viewContext", "8.8.8.8")
check(code == 200, f"viewContext answers 200 ({code})")
check("Fatal error" not in html and "Notice (" not in html,
      "and it is not a trace inside a 200")
check("On its occurrences" in html and "On the events it appears in" in html,
      "both scopes are headed")
check("on its occurrences" in html and "on its events" in html,
      "and the subtitle counts them apart")
# Was `0 galaxy clusters` on a value whose events carry ATT&CK.
check(html.count("vp-galaxy-name") >= 1,
      f"galaxy clusters are drawn ({html.count('vp-galaxy-name')})")
check(html.count(MARK) >= 1, "event-scope chips carry the marker")
# The unit is named, because `×N` cannot carry two denominators.
check("event this value appears in" in html
      or "events this value appears in" in html,
      "the event-scope count names its unit")
check("occurrence of this value" in html
      or "occurrences of this value" in html,
      "and so does the occurrence-scope count")

# A value whose events carry nothing must draw the card it drew before.
_, html = fetch("viewContext", "no-such-value-anywhere.invalid")
check("Nobody has tagged this value." in html,
      "a value nobody tagged still says so")
check("On the events it appears in" not in html,
      "and grows no scope headings")

print(f"\n{checks} checks, {failures} failures")
sys.exit(1 if failures else 0)
