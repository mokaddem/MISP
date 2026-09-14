#!/usr/bin/env python3
"""The Overview card's report count against the tab panel's headline.

`29-overview.md` §14.8's deferred amendment, verified the way every
cross-panel count on this page is: the two surfaces are fetched in
separate requests and the numbers they print are compared, rather than
one method being called once and trusted twice.
"""
import sys
import re
import base64
import urllib3
import requests

urllib3.disable_warnings()
BASE = "https://localhost"
s = requests.Session()
s.verify = False

r = s.get(BASE + "/users/login", timeout=30)
fields = dict(re.findall(
    r'<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"', r.text))
fields["data[User][email]"] = "admin@admin.test"
fields["data[User][password]"] = "admin"
r = s.post(BASE + "/users/login", data=fields, timeout=30,
           allow_redirects=True)
if "/users/login" in r.url:
    print("LOGIN FAILED", r.status_code, r.url)
    sys.exit(1)

VALUES = ["8.8.8.8", "2.2.2.2", "443", "1.162.239.42", "0.0.0.0",
          "sage.png"]

PROBLEM_WORDS = ("Notice (", "Warning (", "Fatal error", "Undefined",
                 "Internal Error", "SQLSTATE")


def get(path):
    resp = s.get(BASE + path,
                 headers={"X-Requested-With": "XMLHttpRequest"},
                 timeout=300)
    return resp.status_code, resp.text


def problems(body):
    return [w for w in PROBLEM_WORDS if w in body]


def check(value):
    b64 = base64.urlsafe_b64encode(value.encode()).decode().rstrip("=")
    pc, preview = get("/values/viewAnalystPreview/" + b64)
    rc, reports = get("/values/viewAnalystReports/" + b64)

    chip = re.search(r"(\d[\d,]*) reports? on its events", preview)
    head = re.search(r"(\d[\d,]*) reports? on (\d[\d,]*) events?", reports)
    empty = "No event report on any event this value appears in" in reports

    card_n = int(chip.group(1).replace(",", "")) if chip else 0
    if empty:
        tab_n = 0
    elif head:
        tab_n = int(head.group(1).replace(",", ""))
    else:
        tab_n = None

    found = problems(preview) + problems(reports)
    agree = card_n == tab_n
    print("%-22s card=%-5s tab=%-5s %s  http=%s/%s  problems=%s" % (
        value, card_n, tab_n, "agree" if agree else "DISAGREE",
        pc, rc, found or "none"))
    return agree and pc == 200 and rc == 200 and not found


results = list(map(check, VALUES))
print("\nOK" if all(results) else "\nFAILED")
sys.exit(0 if all(results) else 1)
