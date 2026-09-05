#!/usr/bin/env python3
"""Regression check: the four panels phase 27 touched shared code for."""
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

panels = ["viewTimeline", "viewOccurrenceTable", "viewSightingChart",
          "viewRelationCooccurrence", "viewAnalystThread",
          "viewAnalystPreview", "viewHistory"]
for value in ("8.8.8.8", "193.161.193.99"):
    b64 = base64.urlsafe_b64encode(value.encode()).decode().rstrip("=")
    for p in panels:
        url = f"{BASE}/values/{p}/{b64}"
        try:
            r = s.get(url, headers={"X-Requested-With": "XMLHttpRequest"},
                      timeout=240)
        except Exception as e:
            print(f"{value:16} {p:26} EXCEPTION {e}"); continue
        bad = [w for w in ("Notice (", "Warning (", "Fatal error",
                           "Undefined", "Internal Error", "SQLSTATE")
               if w in r.text]
        flag = "ok" if r.status_code == 200 and not bad else f"!! {bad}"
        print(f"{value:16} {p:26} {r.status_code} {len(r.text):>9,}  {flag}")
