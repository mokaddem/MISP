#!/usr/bin/env python3
"""Fetch a MISP fragment over HTTP by posting the login form."""
import sys, re, base64, urllib3, requests

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
print("logged in ->", r.url)

for value, extra in [("8.8.8.8", ""), ("8.8.8.8", "/all"),
                     ("2.2.2.2", ""), ("443", "")]:
    b64 = base64.urlsafe_b64encode(value.encode()).decode().rstrip("=")
    url = f"{BASE}/values/viewHistory/{b64}{extra}"
    r = s.get(url, headers={"X-Requested-With": "XMLHttpRequest"},
              timeout=180)
    body = r.text
    bad = [w for w in ("Notice (", "Warning (", "Fatal error",
                       "Undefined", "Internal Error", "SQLSTATE")
           if w in body]
    print(f"{value:16} {extra or '(default)':10} {r.status_code} "
          f"{len(body):>9,} bytes  sections="
          f"{body.count('data-vp-audit-section')}  "
          f"diffs={body.count('vp-audit-diff')}  "
          f"problems={bad or 'none'}")
