# Proposal to misp-modules: query options belong in `userConfig`

**Repository:** `MISP/misp-modules`
**Measured against:** `misp-modules` `main` @ `99a6ab25`, and the module
service of a MISP 2.5 dev instance reporting **146 modules**, 2026-09-06.
**Raised from:** `value-profile-live/28-enrichment.md` §9, building the
Value Profile's Enrichment tab.

---

## 1. The ask, in one sentence

An expansion or hover module that accepts an option shaping **what is
asked** — a date range, a result limit, a threshold — should declare it
in `mispattributes["userConfig"]`, not in `moduleconfig`, because those
two lists mean different things to MISP and today the difference is not
being made.

## 2. Why the two lists are not interchangeable

MISP turns them into the same wire format and nothing else about them is
the same.

`moduleconfig` becomes a set of **instance settings** —
`Plugin.Enrichment_<module>_<key>` — writable only by a site admin, one
value for everybody on the instance, and read straight into the payload:

```php
// MISP, app/Model/ValueProfile.php (and every other enrichment caller)
foreach ($row['config'] as $key) {
    $config[$key] = Configure::read(
        'Plugin.Enrichment_' . $row['name'] . '_' . $key
    );
}
$postData['config'] = $config;
```

`userConfig` becomes a **form shown to the person running the module**,
typed and validated by MISP before anything leaves the instance
(`Module::CONFIG_TYPES` — `String`, `Integer`, `Boolean`, `IP`,
`Select`), then merged into the same `config` dict:

```python
# misp-modules, import_mod/csvimport.py
userConfig = {
    "has_header": {"type": "Boolean", "message": "Tick this box ONLY …"},
}
mispattributes = {"userConfig": userConfig, "format": "misp_standard"}
```

So from a module's point of view the two are indistinguishable — both
arrive as `request["config"][key]`. **The declaration is the only thing
that carries the difference**, and the difference is *who may set this,
and for whom*.

### 2.1 Why this is a security property, not a UX preference

This is the part that motivated the proposal. A MISP surface that wanted
to offer per-query options today has only `moduleconfig` to work from,
and `moduleconfig` is a flat list of key names with no type and no
description. Offering all of them to a reader would mean offering these,
which are on the current catalogue:

| Key | Modules |
|---|---|
| `custom_API`, `custom_API_URL` | `mmdb_lookup`, `cpe` |
| `server` | `farsight_passivedns`, `whois` |
| `mwdb_url` | `mwdb` |
| `api_url`, `token_url` | `cytomic_orion` |
| `proxy_host`, `proxy_port`, `proxy_username`, `proxy_password` | `virustotal`, `google_threat_intelligence`, `vysion` |

MISP sends the **whole merged dict**. A reader allowed to override
`server` while `apikey` keeps its stored value produces
`{apikey: <the instance's secret>, server: <a host they chose>}`, and the
modules container posts the instance's credential to that host. The
reader never sees the key and exfiltrates it regardless — plus it is
SSRF from the module container, and the bar for running an enrichment
module in MISP is `perm_add`, an ordinary user permission, where plugin
settings are site-admin-only.

**Hiding the stored value does not help**, because the attack never
needs to read it. Nor does inferring intent from the stored value's
shape: on the instance measured, **nine** `Enrichment_*` settings are set
at all, and not one of them is a per-module query key. Unset is the
normal state — it is why `mmdb_lookup` works with none of its four keys
and `hashlookup` with a missing `custom_API`.

`userConfig` is therefore the only declaration a MISP surface can safely
act on: it is the module saying *this one is a question, not a
credential.* That is what makes it worth moving keys into.

## 3. What the catalogue looks like today

Measured over the 146 modules the service reports.

| | Count |
|---|---|
| Modules declaring a non-empty `userConfig` | 11 |
| …of which `expansion` or `hover` | **0** |
| `expansion` modules | 98 |
| `hover` modules | 76 |
| `expansion`/`hover` with query-shaping keys in `moduleconfig` | 13 flagged, **9** after reading |

All 11 `userConfig` declarations are import modules: `taxii21`,
`csvimport`, `cuckooimport`, `vmray_import`, `vmray_summary_json_import`,
`joe_import`, `openiocimport`, `lastline_import`, `url_import`,
`import_blueprint`, `testimport`.

`taxii21` is the shape this proposal wants, on the wrong side of the
line — its `added_after` is declared as *"Lower bound on time the object
was uploaded to the TAXII server"*, typed, described, per-run. That is
exactly a date range, and no expansion module has one.

## 4. The candidates

Keys that shape the question rather than the connection. Sorted by how
little work the move is.

### 4.1 Declaration-only — the module already tolerates absence

These read their key with a default already, so moving it changes no
handler code at all:

| Module | Key | Type | Current read |
|---|---|---|---|
| `virustotal` | `event_limit` | Integer | `request["config"].get("event_limit")` |
| `google_threat_intelligence` | `event_limit` | Integer | same shape |
| `vysion` | `event_limit` | Integer | same shape |
| `cpe` | `limit` | Integer | `int(config["limit"]) if config.get("limit") else DEFAULT_LIMIT` |
| `farsight_passivedns` | `limit` | Integer | `config["limit"] if config.get("limit") else DEFAULT_LIMIT` |
| `mmdb_lookup` | `max_country_info_qt` | Integer | `request["config"].get("max_country_info_qt", 0)` |
| `mmdb_lookup` | `db_source_filter` | String / Select | filter, defaults to none |
| `mwdb` | `include_tags_event`, `include_tags_attribute` | Boolean | `request["config"].get(…)`, truthy-tested |

### 4.2 Needs a handler change as well

| Module | Key | Type | Why |
|---|---|---|---|
| `abuseipdb` | `max_age_in_days`, `abuse_threshold` | Integer | guards on `not in request["config"]`, and no default for `max_age_in_days` |
| `vulndb` | `discard_dates` and the `discard_*` family | Boolean | compares `request["config"].get(k).lower() == "true"` — a real boolean has no `.lower()` |

`vulndb` is the one worth reading carefully. Its six `discard_*` keys
are read as **strings** and compared against the literal `"true"`:

```python
if request["config"].get("discard_dates") is not None \
        and request["config"].get("discard_dates").lower() == "true":
```

Declared as `Boolean`, MISP's checkbox does not send that string, and
`.lower()` on what it does send raises `AttributeError`. The move needs
a reader that accepts both — or the keys declared as `String`, which
would be the wrong type for what they are. Worth fixing in either case:
today a site admin has to know to type the word `true`.

### 4.3 Deliberately not proposed

`rbl`'s `timeout`, `apiosintds`'s `cache_timeout_h` and `cache_directory`,
`vmray_submit`'s `do_not_include_vmrayjobids`, and `cytomic_orion`'s
`upload_*` family. These are operational or write-back settings, not
questions a reader is asking, and an instance-wide value is the right
home for them. Nothing about credentials, URLs, hosts or proxies is
being asked for here.

## 5. The change, concretely

`abuseipdb` is the fullest example because it needs both halves.

**Today:**

```python
mispattributes = {
    "input": ["ip-src", "ip-dst", "hostname", "domain", "domain|ip"],
    "format": "misp_standard",
}
moduleconfig = ["api_key", "max_age_in_days", "abuse_threshold"]

def handler(q=False):
    ...
    if "max_age_in_days" not in request["config"]:
        return {"error": "AbuseIPDB max age in days is missing"}
    if "abuse_threshold" not in request["config"]:
        return {"error": "AbuseIPDB abuse threshold is missing"}
    ...
    max_age_in_days = request["config"]["max_age_in_days"]
```

**Proposed:**

```python
userConfig = {
    "max_age_in_days": {
        "type": "Integer",
        "message": "How far back to look, in days. Leave empty for 30.",
    },
    "abuse_threshold": {
        "type": "Integer",
        "message": "Confidence score at or above which an IP is"
                   " reported as abusive. Leave empty for 70.",
    },
}
mispattributes = {
    "input": ["ip-src", "ip-dst", "hostname", "domain", "domain|ip"],
    "format": "misp_standard",
    "userConfig": userConfig,
}
moduleconfig = ["api_key"]

def handler(q=False):
    ...
    max_age_in_days = request["config"].get("max_age_in_days") or 30
    abuse_threshold = request["config"].get("abuse_threshold") or 70
```

### 5.1 The one behavioural requirement

**A module moving a key to `userConfig` must tolerate that key being
absent.** MISP omits an empty optional field rather than sending a null:

```php
// MISP, app/Controller/EventsController.php
if (empty($requestData['config'][$configName])
    && empty($config['required'])
) {
    continue;
}
```

This differs from `moduleconfig`, where MISP always sets the key and may
set it to `null`. Every module in §4.1 already satisfies this. The
`not in request["config"]` guards in `abuseipdb` are the pattern that
does not, and they are dead code on the MISP path today — MISP always
sets the key, so the guard never fires and a `null` reaches the query
instead, where `requests` silently drops it. In other words
`max_age_in_days` currently does nothing at all unless a site admin has
set it, which is a second reason to move it.

### 5.2 Keep a default in the module, not only in the message

`Leave empty for 30` in the `message` is for the reader; the `or 30` in
the handler is what makes it true. A module that documents a default in
prose and then fails without one is the state `abuseipdb` is in.

## 6. What MISP needs on its side

This proposal is not self-sufficient, and a reviewer will rightly ask.
MISP reads `userConfig` **only on the import path** — `EventsController::
importModule` and `View/Events/import_module.ctp`. Nothing on the
enrichment path looks at it. So MISP would need, separately:

1. `Module::getEnabledModules` output to carry `userConfig` through to
   the enrichment callers (it already carries `mispattributes`).
2. The enrichment surfaces — `events/queryEnrichment`,
   `attributes/hoverEnrichment`, and the Value Profile's own
   `values/viewEnrichmentRun` — to render the typed fields, validate
   through `Module::CONFIG_TYPES`, and merge into `config` alongside the
   instance settings, with the module's own value winning where the
   reader left the field empty.

That is a small, well-bounded change on the MISP side and it is the same
code the import path already runs. **It is worth doing only once
something declares a `userConfig` to render**, which is why this
proposal goes first.

## 7. How the numbers here were obtained

So a reviewer can re-run rather than trust:

- **Catalogue shape:** `GET /modules` on the module service, then a
  tally of `mispattributes` keys, `meta.config` keys and `module-type`
  per module. `userConfig` presence was counted directly.
- **Query-shaping candidates:** `meta.config` keys matched against a
  name heuristic (limit / max / days / threshold / include / discard /
  …) with credential-ish names (api / key / token / user / pass / url /
  host / port / proxy / …) excluded, then read by hand against each
  module's handler. §4.3 is what the heuristic caught and the reading
  rejected — the heuristic is a starting point, not the finding.
- **Settings actually set:** the instance's `Plugin.Enrichment_*` keys,
  classified by value shape only — number, bool, URL, host, string of
  length *n* — so no stored secret was read out.
- **Module reads:** `misp-modules` `main` @ `99a6ab25`, per file.
