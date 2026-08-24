# Design brief — MISP "Value Profile" page

*A prompt for Claude Design. Everything below describes a **new page in MISP** that
does not exist yet. Produce a high-fidelity UI mockup, not code.*

---

## 1. The problem

MISP is event-centric. Every screen answers "what is in this event?". There is no
screen that answers **"what do we collectively know about this one value?"**

An analyst who receives `185.234.219.24` from a SIEM alert today has to run a
search, get a flat list of attributes, and open each event one by one to
reconstruct the picture. Everything MISP already knows about that value —
sightings, opinions, correlations, decay, feed hits, galaxy attribution — is
scattered across a dozen event pages.

The new page inverts the hierarchy: **the value is the subject, and events become
just one of many kinds of evidence about it.**

## 2. Naming

The subject of the page is a *value string*, not a single attribute row (the same
value exists as many attribute rows). Recommended name: **"Value Profile"**
(page title: the value itself; nav / breadcrumb label: "Value Profile").

Alternatives, in order of preference: "Observable Profile", "Value Dossier",
"Value Lens". Avoid "Indicator" — in MISP an attribute is not necessarily an
indicator (`to_ids` is a per-occurrence flag, and one of the things this page
must resolve is *disagreement* about it).

Do **not** call it "Attribute view" — MISP already has `/attributes/view/<id>`
for a single row, and the distinction matters.

## 3. Who it is for

A CTI analyst mid-investigation, on a triage clock. Three jobs, in order:

1. **Verdict** — "is this bad, and how sure are we?" Answerable in ~5 seconds
   from the top of the page, without scrolling.
2. **Evidence** — "why do we believe that, and who says so?" Every element of
   the verdict must be traceable to the panel that produced it.
3. **Pivot & act** — "what do I do next?" Enrich, tag, sight, export, pivot to
   the next value, add to a case.

Design for density. This audience reads tables and prefers information per pixel
over whitespace. The verdict strip is the one place where restraint wins.

## 4. Page identity and entry points

- **Subject:** a normalised value, e.g. `185.234.219.24`, `evil-cdn[.]com`,
  `d41d8cd98f00b204e9800998ecf8427e`.
- **URL shape:** `/values/view/<base64-value>` (MISP already has a REST-only
  `/attributes/getAttributeByB64Value/<b64>` endpoint — this is its UI).
- **Composite values matter:** MISP stores `domain|ip` and `filename|md5` as two
  halves (`value1`/`value2`). The profile for `1.2.3.4` must include composite
  attributes where `1.2.3.4` is the *second* half. Show these clearly as
  "appears as part of `domain|ip`".
- **Multi-type:** the same value can exist as `ip-src`, `ip-dst` and
  `domain|ip`. The page covers all of them, with a type filter chip row.
- **Entry points to show in at least one artboard:** clicking a value in the
  event attribute table, the global search bar, a correlation-graph node, and a
  sighting notification.

## 5. Layout skeleton — must match MISP's current "Overmind" chrome

You're free to design your own page. As long as it works for these constraints:

- MISP already has a topbar that should be kept.
- Cards load lazily and show a small centred spinner before content.

**Design tokens (use these exact values — they are MISP's real CSS variables):**

```
--primary      #1892B1    --event        #1892B1    --object      #524948
--attribute    #97CC04    --tag          #DB6A47    --galaxy      #8B5CF6
--report       #4DA167    --sighting     #890096    --correlation #E67F0D
--category     #EF476F    --type         #06D6A0    --analystData #8F2D56
--enrichment   #48435C    --warninglist  #9d174d
```

Bootstrap 5 semantic colours for state (`danger #dc3545`, `warning #f39a1f`,
`success #6fbe80`, info, secondary). Icon set: MISP's own `misp-iconify` glyphs
for MISP entities (attribute, object, sighting, report, galaxy) and Font Awesome
for generic UI. **Produce just light theme artboards**.

---

## 6. Region-by-region specification

### 6.1 Value banner (top of page, above the tabs)

The most important 120px of the page.

- The **value itself**, large, monospace, selectable. A **defang toggle** next to
  it (`evil.com` ⇄ `evil[.]com`, `http` ⇄ `hxxp`) and a copy button. Defanged is
  the default for `url`, `domain`, `hostname`, `ip-*`.
- Type chips: every MISP type this value appears as (`ip-dst ×7`, `ip-src ×2`,
  `domain|ip ×1`) — clickable, and they scope the whole page.
- A compact **fact strip**: first seen · last seen · N attributes · N events ·
  N organisations · N sightings. Each jumps to the relevant tab.
- Right side: primary actions — *Add sighting*, *Enrich*, *Add to collection*, a
  *Watch* toggle (bell), and an overflow menu.

### 6.2 Verdict card (right rail, first card — the headline feature)

This does not exist in MISP today and is the single most valuable addition.

A synthesized disposition: **Malicious / Suspicious / Benign / Unknown /
⚠ Conflicted**, with a confidence bar. Critically, it must be **glass-box**: a
list of contributing signals with direction and weight, each row clicking through
to the panel that produced it. For example:

```
▲  3 independent organisations reported it        +strong
▲  47 sightings from 5 orgs, last 2 days ago      +strong
▲  Linked to galaxy: APT28 (2 events)             +moderate
▼  Warninglist hit: "Known Cloudflare IP ranges"  −strong
▼  2 false-positive sightings (CIRCL, 2024-11)    −moderate
▼  Decayed under "Phishing Model" (score 12/100)  −moderate
◆  to_ids disagreement: 5 yes / 4 no              conflict
```

The **Conflicted** state is the interesting one and deserves its own artboard: a
value that is heavily sighted *and* hits a warninglist is a real, common,
hard-to-resolve situation, and the page's job is to surface the tension rather
than average it away.

### 6.3 Tabs
Even though, we use "Tab" as name, feel free to consider them as UI tabs, panels or section.

**Tab 1 — Overview** (default)

*Left column:*

- **Occurrence summary** — a compact table, one row per attribute occurrence:
  event (id + info, truncated), creating org, type, category, `to_ids` pill,
  distribution badge (+ sharing group name), object context ("inside object
  `file` as relation `md5`"), comment, first/last seen, tags. Soft-deleted
  occurrences shown struck-through behind a toggle. Rows selectable → reveals a
  sticky bulk-action bar.
- **Context: tags & galaxies** — aggregated attribute tags *and* inherited event
  tags, each with an occurrence count and which orgs applied them. Render
  taxonomy-aware: TLP and PAP in their canonical colours, `admiralty-scale` and
  `estimative-language` as labelled scales rather than raw strings. Local tags
  visually distinct from global. Galaxy clusters (threat actor, malware, MITRE
  ATT&CK technique, sector, country) as rich chips with counts.
- **Analyst data preview** — most recent notes and opinions.

*Right rail:*

- Verdict card (6.2)
- **Sightings** — total, split by MISP's three sighting types (sighting /
  false-positive / expiration), a 90-day sparkline, top reporting orgs, and an
  inline "I saw this" button.
- **Reputation & lifecycle** — per-decaying-model score with a `decayed` flag,
  warninglist hits (list name + version + category: *known* vs *false positive*),
  noticelist triggers, and an over-correlation / correlation-exclusion warning if
  the value is flagged.
- **External presence** — which **feeds** and which **sync servers** hold this
  value in their cache, plus **SightingDB** hits. The "is anyone outside our
  instance seeing this?" panel.
- **Exposure** — the most permissive distribution across all occurrences, the
  sharing groups involved, a count of organisations that can see it, and a
  conflict flag if occurrences carry contradictory TLP tags.

**Tab 2 — Occurrences** `(count)`

The full, dense, filterable table. Filters: org, type, category, `to_ids`,
distribution, sharing group, tag, date range, include-deleted. Multi-select with
a **bulk action bar**: tag / untag, set `to_ids`, set distribution, propose edit,
add sighting, add to collection, export selection. Show a pending-proposal
indicator (MISP shadow attributes) on rows that have one.

**Tab 3 — Sightings** `(count)`

- A time histogram, stacked by organisation, with a type toggle
  (sighting / false-positive / expiration) and a brush-selectable range.
- A **decay-score-over-time curve** overlaid per decaying model, so the analyst
  sees the score decaying and each sighting bumping it back up. MISP already
  computes this curve; it has never had a good home.
- A table of individual sightings: org, source string, date, type.
- "Add sighting" writes against the *value*, fanning out to every occurrence —
  MISP's sighting API already supports value-scoped sightings, and this page is
  the natural UI for it.

**Tab 4 — Relationships & pivots** `(count)`

Three distinct notions of "related", clearly separated — conflating them is the
main way this page could fail:

1. **Co-occurrence** — other values appearing in the same events/objects as this
   one, ranked by frequency. Object siblings are the highest-signal case: if this
   md5 sits in a `file` object, its filename, size, sha256 and ssdeep are one
   click away.
2. **Near-matches (not equality)** — MISP has these engines but no UI worth the
   name: CIDR containment (which network-block attributes contain this IP),
   ssdeep fuzzy similarity for hashes, and domain/TLD tree relations. Show
   similarity scores.
3. **Asserted relationships** — analyst-created typed relationships (MISP's
   analyst-data Relationship objects) pointing to attributes, events, objects,
   galaxy clusters, orgs. These are human claims and must look different from
   machine-derived correlations.

Plus a **graph view** (reuse the visual language of MISP's existing Pivot
Explorer / correlation graph) with this value at the centre.

**Tab 5 — Enrichment** `(module count)`

The "mass enrichment" ask. Design:

- A **module picker**: cards for each enabled enrichment / expansion / Cortex
  module valid for this attribute type, with a checkbox, a last-run timestamp and
  a staleness indicator. "Select all", "Run selected".
- Running state: per-module progress with individual success/failure — one module
  timing out must not look like total failure.
- Results as **structured cards**, one per module, because MISP enrichment
  returns MISP attributes and objects, not free text. Each returned element gets
  its own row with *add to event X* / *add as new event* / *dismiss*. Nothing is
  written to the database without an explicit action.
- A results-diff affordance: "this module returned 3 new values since the last
  run" — analysts re-run enrichment constantly and need the delta.
- **Never auto-run on page load.** Enrichment costs money and quota, and can be
  an operational-security leak (querying VirusTotal for an adversary's C2
  announces your interest). Show a clear "nothing queried yet" state.

**Tab 6 — Analyst data** `(count)`

MISP's notes / opinions / relationships, currently hard to find.

- **Opinions:** an aggregate — mean score, a 0–100 distribution histogram, and a
  per-organisation breakdown — above the individual opinions with their comments.
  Disagreement between orgs is the interesting signal; make it visible.
- **Notes:** markdown-rendered, threaded (notes and opinions can themselves carry
  notes and opinions, two levels deep), with author org, distribution, timestamp.
- Inline composer for a new note or opinion without leaving the page.

**Tab 7 — Timeline**

A single merged chronology, which no MISP screen currently offers: event
publication dates, per-attribute `first_seen`/`last_seen`, sightings, tag and
opinion additions, feed cache appearances, enrichment observations, audit-log
edits. Filterable by lane. This is what an analyst screenshots into a report.

**Tab 8 — History**

Audit log across every occurrence: created / edited / tagged / soft-deleted /
proposal accepted, with actor org and user. Answers "who changed the `to_ids`
flag and when".

### 6.4 Action rail

- Add sighting · Mark false positive · Mark expired
- Bulk tag / set `to_ids` / set distribution across occurrences
- Add note · Add opinion · Add relationship
- Add to collection · Create new event from this value
- Add to warninglist · Add correlation exclusion · Block
- Run workflow (ad-hoc)
- Export: STIX 2.1, JSON, CSV, Suricata, Snort, Zeek, RPZ, plain text — plus
  "copy the REST search query for this value", which is how analysts actually
  automate follow-up.
- Watch / subscribe: notify me when a new event, sighting or opinion touches this
  value.

### 6.5 Type-aware pivot rail

A small contextual strip of next-step pivots that changes with the value type:

- IP → containing CIDR block, ASN, geolocation, other ports seen, passive DNS
- Domain → registrar/whois, subdomains, historical resolutions, parent domain
- Hash → filenames it was seen under, the other hashes of the same file, size
- URL → its domain, host and path components as separate profiles
- Email → sender domain profile

Each pivot links to *another Value Profile page*, which is what makes this a
navigation surface rather than a dead end.

---

## 7. Rules the design must respect

- **ACL honesty.** Everything shown is scoped to the viewing user's permissions.
  Where results are truncated by ACL or by a limit, say so explicitly ("showing
  12 occurrences you have access to"). Never let a partial view read as complete.
- **"No data" ≠ "no permission" ≠ "not yet queried."** Three visually distinct
  empty states. The enrichment tab in particular must never look broken just
  because nobody has run it.
- **Defanged by default** for network and URL types.
- **Traceability.** Every aggregate number links to the rows behind it.
- **Nothing writes silently.** Bulk actions span multiple events and multiple
  orgs' data; every one gets a confirmation showing exactly how many rows in how
  many events will change.
- **Responsive:** the right rail stacks beneath the left column below ~992px;
  dense tables scroll horizontally inside their own container, never the page.

---

## 8. Artboards to produce

1. **Overview — rich malicious value** (a C2 IP: many sightings, APT galaxy,
   4 orgs, high decay score). There should be 4 variant for this one, exploring tabs, panels and section
2. **Overview — Conflicted verdict** (heavily sighted but hits a Cloudflare
   warninglist, with false-positive sightings). The verdict card's hardest job.
3. **Overview — sparse value** (a single occurrence, one org, no sightings, no
   enrichment run). The honest empty state, and the majority case.
4. **Occurrences tab** with 4 rows selected and the bulk-action bar active.
5. **Sightings tab** — histogram stacked by org + decay curve overlay.
6. **Enrichment tab** — three states on one board: module picker, mid-run, and
   results with add/dismiss controls.
7. **Analyst data tab** — opinion distribution histogram plus a threaded note.
8. **Relationships tab** — graph view with the near-match / co-occurrence /
   asserted-relationship legend.
9. **Entry point** — a small board showing how a value in the event attribute
    table becomes a link into this page.

## 9. Sample data to use

Make it look like real MISP. Organisations: `CIRCL`, `CthulhuSPRL.be`,
`ORGNAME`, `Team-CIRCL`. Event titles in MISP's house style
(`OSINT - Emotet malspam campaign targeting .lu`, `Phishing kit hosted on
compromised WordPress`). Galaxy chips like `APT28`, `Emotet`,
`T1071.001 - Application Layer Protocol: Web Protocols`. Tags like `tlp:amber`,
`pap:amber`, `misp-galaxy:threat-actor="Sofacy"`,
`admiralty-scale:source-reliability="b"`, `type:OSINT`. Warninglist names like
`List of known Cloudflare IP ranges`, `Top 1000 websites from Alexa`. Attribute
types drawn from MISP's real 194-type vocabulary. Decaying model names like
`NIDS Simple Decaying Model`, `Phishing Model`.
