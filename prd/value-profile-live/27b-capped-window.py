#!/usr/bin/env python3
"""A windowed request that hits the row cap: the elided line must not
blame the period, because some of those occurrences do have entries in
it and were cut."""
import re, base64, urllib3, requests, html

urllib3.disable_warnings()
BASE = "https://localhost"
s = requests.Session(); s.verify = False
r = s.get(BASE + "/users/login", timeout=30)
f = dict(re.findall(r'<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"', r.text))
f["data[User][email]"] = "admin@admin.test"
f["data[User][password]"] = "admin"
s.post(BASE + "/users/login", data=f, timeout=30)

cases = [
    ("443", "/2026-06-01/2026-06-30", "burst month, expect capped"),
    ("443", "/all", "all time, expect capped"),
    ("8.8.8.8", "/2024-11-01/2026-09-05", "wide, under the cap"),
]
for value, extra, why in cases:
    b64 = base64.urlsafe_b64encode(value.encode()).decode().rstrip("=")
    r = s.get(f"{BASE}/values/viewHistory/{b64}{extra}",
              headers={"X-Requested-With": "XMLHttpRequest"}, timeout=300)
    body = r.text
    shown = re.search(r'data-vp-list-shown>(\d+)<', body)
    m = re.search(r'data-vp-audit-elided.*?<span>(.*?)</span>', body, re.S)
    line = ""
    if m:
        line = html.unescape(re.sub(r"\s+", " ",
                                    re.sub(r"<[^>]+>", " ", m.group(1)))).strip()
    bad = [w for w in ("Notice (", "Warning (", "Undefined", "Fatal") if w in body]
    print(f"\n{value} {extra}   ({why})")
    print(f"   status {r.status_code}  shown={shown.group(1) if shown else '?'}"
          f"  problems={bad or 'none'}")
    print(f"   elided: {line[:230]}")
