#!/usr/bin/env python3
"""Every row that names a record should now open it."""
import re, base64, urllib3, requests

urllib3.disable_warnings()
BASE = "https://localhost"
s = requests.Session(); s.verify = False
r = s.get(BASE + "/users/login", timeout=30)
f = dict(re.findall(r'<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"', r.text))
f["data[User][email]"] = "admin@admin.test"
f["data[User][password]"] = "admin"
s.post(BASE + "/users/login", data=f, timeout=30)

for value, extra in (("8.8.8.8", ""), ("8.8.8.8", "/all"), ("443", "")):
    b64 = base64.urlsafe_b64encode(value.encode()).decode().rstrip("=")
    r = s.get(f"{BASE}/values/viewHistory/{b64}{extra}",
              headers={"X-Requested-With": "XMLHttpRequest"}, timeout=240)
    body = r.text
    rows = re.split(r'(?=<div class="vp-audit-row")', body)[1:]
    named, linked, targets = 0, 0, {}
    for row in rows:
        m = re.search(r'font-monospace"[^>]*>(.*?)</div>', row, re.S)
        if not m:
            continue
        named += 1
        a = re.search(r'href="([^"]+)"', m.group(1))
        if a:
            linked += 1
            targets[re.sub(r"/\d+$", "/N", a.group(1))] = \
                targets.get(re.sub(r"/\d+$", "/N", a.group(1)), 0) + 1
    print(f"{value:10}{extra or ' (default)':10} rows={len(rows):>4} "
          f"naming a record={named:>4}  linked={linked:>4}  "
          f"{'ALL LINKED' if named == linked else 'GAP'}")
    for t, n in sorted(targets.items(), key=lambda kv: -kv[1]):
        print(f"      {n:>4}x {t}")
