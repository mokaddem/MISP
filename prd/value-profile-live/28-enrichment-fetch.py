#!/usr/bin/env python3
"""Phase 28's verification: fetch the Enrichment tab, then run a module.

Two things the probe shell cannot check, because both are about the
page rather than about the model:

  1. The panel renders for every shape — a value with several types, a
     value with one, a value the reader holds nothing of.
  2. **A run works end to end over HTTP**, which means the POST, the
     CSRF token, `validatePost` being off, the ACL entry, and the
     result fragment. Any one of those wrong is a run that fails in the
     browser and nowhere else.

It also asserts the two invariants that matter more than the markup:
the catalogue GET must *not* have run anything, and the answer must
carry a fresh token for the next run.

Not part of the application. Run it against a dev instance:

    python3 prd/value-profile-live/28-enrichment-fetch.py
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
print("logged in ->", r.url)

PROBLEMS = ("Notice (", "Warning (", "Fatal error", "Undefined",
            "Internal Error", "SQLSTATE")

# A value per shape: four types, one type, one hash, a composite, and
# one the instance has never heard of.
VALUES = ["8.8.8.8", "github.com", "f1d3ff8443297732862df21dc4e57262",
          "45.155.205.233", "no-such-value-anywhere.invalid"]


def b64(value):
    return base64.urlsafe_b64encode(
        value.encode()).decode().rstrip("=")


def problems(body):
    return [w for w in PROBLEMS if w in body] or "none"


print("")
print("=== the catalogue ===")
panels = {}
for value in VALUES:
    url = "%s/values/viewEnrichment/%s" % (BASE, b64(value))
    r = s.get(url, headers={"X-Requested-With": "XMLHttpRequest"},
              timeout=60)
    body = r.text
    panels[value] = body
    rows = body.count('data-vp-e-row=')
    panes = body.count('data-vp-e-pane=')
    # The one invariant of a GET on this tab: it asks nothing.
    ran = 'data-vp-e-result=' in body
    print("%-34s %s %8s bytes  rows=%-3d panes=%-3d ran=%-5s %s"
          % (value, r.status_code, format(len(body), ","), rows, panes,
             ran, problems(body)))
    if ran:
        print("    *** the catalogue ran a module — it must not ***")

print("")
print("=== a run ===")
for value in ["8.8.8.8", "github.com",
              "f1d3ff8443297732862df21dc4e57262"]:
    body = panels[value]
    token = re.search(r'data-vp-e-token="([^"]*)"', body)
    modules = re.findall(r'data-vp-e-run="([^"]+)"', body)
    types = re.findall(r'data-vp-e-type="([^"]+)"', body)
    if not token or not modules:
        print("%-34s no token or no module to run" % value)
        continue
    for module, mtype in zip(modules, types):
        url = "%s/values/viewEnrichmentRun/%s" % (BASE, b64(value))
        r = s.post(
            url,
            headers={"X-Requested-With": "XMLHttpRequest"},
            data={
                "data[_Token][key]": token.group(1),
                "data[module]": module,
                "data[type]": mtype,
            },
            timeout=180,
        )
        out = r.text
        state = re.search(r'data-vp-e-state-is="([^"]*)"', out)
        fresh = re.search(r'data-vp-e-token="([^"]*)"', out)
        shown = out.count('class="vp-e-el"') + out.count('vp-e-obj-head')
        print("%-34s %-22s %s %-12s elements=%-4d capped=%-5s "
              "fresh_token=%-5s %s"
              % (value, module, r.status_code,
                 state.group(1) if state else "?",
                 shown, "vp-e-partial" in out,
                 bool(fresh and fresh.group(1)), problems(out)))
        # Each run spends the token; the answer carries the next one.
        if fresh and fresh.group(1):
            token = fresh

print("")
print("=== refusals ===")
value = "8.8.8.8"
url = "%s/values/viewEnrichmentRun/%s" % (BASE, b64(value))
r = s.get(url, headers={"X-Requested-With": "XMLHttpRequest"},
          timeout=30)
print("GET instead of POST            -> %s (want 405)" % r.status_code)

r = s.post(url, headers={"X-Requested-With": "XMLHttpRequest"},
           data={"data[module]": "circl_passivedns"}, timeout=30,
           allow_redirects=False)
print("POST with no CSRF token        -> %s (want not 200)"
      % r.status_code)

# A fresh panel, because the runs above spent every token this one
# was holding — a spent token blackholes at 400 and would look like
# the ineligible path passing when it never ran.
fresh_panel = s.get(
    "%s/values/viewEnrichment/%s" % (BASE, b64(value)),
    headers={"X-Requested-With": "XMLHttpRequest"}, timeout=60).text
token = re.search(r'data-vp-e-token="([^"]*)"', fresh_panel)
r = s.post(url, headers={"X-Requested-With": "XMLHttpRequest"},
           data={"data[_Token][key]": token.group(1),
                 "data[module]": "hashlookup",
                 "data[type]": "md5"},
           timeout=60)
state = re.search(r'data-vp-e-state-is="([^"]*)"', r.text)
print("POST naming an unoffered module-> %s %s (want 200 ineligible)"
      % (r.status_code, state.group(1) if state else "?"))
