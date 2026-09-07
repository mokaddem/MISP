# PRD: Analyst Profile — phase 10, the verdict in restSearch

**Specification. Nothing built. Deliberately last.** Depends on phases 1–5 and
9, plus one amendment to phase 1 that this document makes load-bearing (§4.2).

**Rewritten 2026-09-03.** From its creation this file was a direction note
with one job: phase 5 removes the page's ability to explain why MISP stopped
exporting an occurrence, and that removal is only acceptable with the
replacement on the record. That job is kept (§2). What changed is that the
design question blocking a specification — render-time computation cannot
serve a bulk API, and every mitigation considered was a cache with an ACL
problem — was settled by an industry survey (§3.1) and the decision it
produced (§3.2): **the export gate is a materialised instance verdict, set by
a background worker.** The page's own verdict stays render-time, untouched.

**D11 (2026-09-03) renames the object**: the instance verdict becomes the
instance assessment — `value_verdicts` → `value_assessments`,
`minVerdictScore` → `minQuality`, `includeVerdict` → `includeAssessment`
(attaching lean, relevance and quality); the map is
[`12-assessment.md`](12-assessment.md) §5. The mechanics below are unchanged
and are read through the map until this document's own rework.

## 1. What ships

A `value_verdicts` table holding one materialised verdict per value under one
designated profile; the worker that fills it and keeps it fresh; four
`restSearch` parameters that filter on it; and the one line the Value Profile
page gains back.

Exit criterion: **a `restSearch` call with `excludeStale` returns in the same
order of time as one without it, makes zero engine calls, and culls exactly
the values whose instance verdict has passed its TTL — including ones the
sweep has not visited yet (§5.2).**

## 2. What phase 5 gives up, and this phase restores

`excludeDecayed` is a real `restSearch` filter — accepted at
`RestSearchComponent.php:50`, passed through at `MispAttribute.php:3729` and
`MispObject.php:1707`, and applied at `MispAttribute.php:2208` and `2421` —
with `includeDecayScore` alongside it (`RestSearchComponent.php:48`,
`Event.php:164`). It is what makes a decaying model operationally meaningful:
a decayed attribute stops reaching a NIDS.

After phase 5 the Value Profile page does not read `decaying_models`, so when
an analyst asks *"why did this IOC stop firing"* the page cannot answer. This
phase restores the answer, and better than the decay bars it replaced: the
instance verdict is a stored row with a profile name, a revision and a date,
so the page can say *when* an occurrence stopped exporting and *under whose
judgement* — §8.

## 3. The decision: materialise, do not compute at request time

**Decided 2026-09-03 — D10.** A background worker computes the verdict for
every value under the **instance default profile**, stores disposition, score
and expiry, and `restSearch` filters on the stored row. Per-request
computation is rejected; so is per-analyst gating. The Value Profile page
keeps computing the viewer's verdict at render time, exactly as phases 2 and
9 specify.

### 3.1 The industry precedent

Surveyed 2026-09-02 — from memory as of early 2026; EclecticIQ deliberately
omitted for low confidence in its aging specifics, and OpenCTI's decay
feature was evolving quickly, so exact behaviour may have moved:

| TIP | Aging mechanism | Export gate |
|---|---|---|
| ThreatQuotient | per-source and per-type TTLs; a background process flips indicator status to Expired | SIEM-facing exports filter `status=Active` by default |
| Anomali ThreatStream | `expiration_ts` + status (active/expired/falsepos) per observable; per-source/per-type TTLs, plus an ML confidence score | intelligence API and SIEM integrations filter `status=active` |
| Cortex XSOAR TIM | indicator expiration — per-source/per-type TTL or manual | EDL exports consumed by firewalls/SIEMs exclude expired automatically |
| OpenCTI (~5.12+) | decay rules — curves per observable type; the score decays and a threshold crossing revokes | live streams, TAXII collections and feeds filter on `revoked` and score |
| ThreatConnect | confidence deprecation — rules decrement confidence over time, optionally deleting at zero | TQL filters on confidence / ThreatAssess score |
| Recorded Future | vendor-computed risk scores that age with evidence | risk lists include only above-threshold indicators; culling is inherent |
| STIX/TAXII | `valid_from` / `valid_until` / `revoked` on the Indicator SDO — a static TTL, no curve | consumers expected to honour it |
| MineMeld (dead, influential) | per-miner age-out | indicators dropped from the output feed after their TTL |

Two families — *static TTL → status flip → filter* and *score decay →
threshold → filter* — and two unanimities across both: **nobody computes the
aging per request at export time**, and **nobody returns different feed
contents depending on which analyst's key pulled them, beyond ACL**.

MISP is more unusual here than `excludeDecayed`'s query-time computation
makes it look: the decay is also per-*caller* —
`DecayingModelsFormulas/Base.php:139` reads the last sighting through
`getLastSightingForAttribute($user, …)`, which is ACL-scoped — so today's
gate is viewer-dependent too. The precedent supports moving both properties
to the industry shape, not just the first.

### 3.2 What materialisation settles by construction

**The old §2.4 — whose profile gates the export — closes itself.** A worker
must pick its profile before any caller exists, so the gate cannot be the
caller's profile. It is the instance default's — the one profile every user
resolves to when nothing nearer exists, and the only one a feed consumer can
reasonably be told about. Per-analyst profiles keep their entire value on the
page. Stated as the invariant it is: **profiles personalise the view; one
designated profile governs the gate.**

**The cache-key contradiction dissolves** (`review-2026-09-02.md` B6). The
old sketch cached the *viewer's* verdict by `(value, profile)`, which either
served one viewer's number to another or was wrong. The materialised row is
not anyone's viewer verdict — it is a new object, computed once from
instance-wide visibility, like ThreatQuotient's `Expired` flag. §5.4 of the
main PRD does not bind its computation; it binds its *exposure*, which is Q13
(§7).

**"Not stored, not synchronised" stays true for what it describes.** The
hero's sentence is about the viewer's verdict, and the viewer's verdict is
never stored. `01-profile.md` §5.5 is rewritten in the same pass to say so.

### 3.3 Two verdicts, named

From this phase on there are two verdicts and they can disagree — correctly:

| | The viewer's verdict | The instance verdict |
|---|---|---|
| Computed | at render time, per page view | by the worker, stored |
| Profile | the one in force for the viewer (D3) | the instance default |
| Evidence scope | the viewer's ACL | instance-wide |
| Lives | nowhere | `value_verdicts` |
| Gates | nothing | `restSearch` (§6) |

*"Your page says MALICIOUS 84 and the feed dropped it"* is a legitimate state
— the viewer's fork disagrees with the instance's judgement, or sees evidence
the expiry postdates. It must be legible, not discovered: wherever the page
mentions export gating it names the **instance** verdict as a distinct
object, with its profile and date (§8).

## 4. The store

```sql
CREATE TABLE IF NOT EXISTS `value_verdicts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `value_hash` binary(32) NOT NULL,
  `value` mediumtext NOT NULL,
  `profile_uuid` varchar(40) CHARACTER SET ascii NOT NULL,
  `profile_revision` int(11) NOT NULL,
  `disposition` varchar(20) NOT NULL,
  `score` int(11) DEFAULT NULL,
  `fully_public` tinyint(1) NOT NULL DEFAULT 0,
  `computed_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `recompute_at` datetime DEFAULT NULL,
  `dirty` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `value_profile` (`value_hash`, `profile_uuid`),
  KEY `expires_at` (`expires_at`),
  KEY `recompute_at` (`recompute_at`),
  KEY `dirty` (`dirty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.1 Column notes

- **`value_hash`** is SHA-256 over the normalised value, and the
  normalisation **must be the page's own** — two normalisations would
  disagree about which value a verdict belongs to.
- **`score` is `NULL` on CONFLICTED**, matching `04-dispositions.md` §5: the
  conflicted layout has no score slot and the exact-sum invariant does not
  bind where there is no score.
- **`fully_public`** — set by the worker, conservatively, while it holds the
  evidence: `1` only when every occurrence and sighting the verdict counted
  is visible to any authenticated user on the instance. When in doubt, `0`.
  This is Q13's cheapest gate (§7) and costs nothing to record here.
- **`expires_at`** is the TTL boundary — the wall-clock moment
  `lifecycle.staleness` flips to `expired` given the evidence as of
  `computed_at`. `NULL` when already expired or when the signal is disabled.
- **`recompute_at`** is the next moment the stored score changes for any
  time-driven reason (§5.1). `NULL` when nothing further is scheduled.
- **One row per (value, profile).** v1 materialises exactly one profile — the
  instance default — so cardinality is one row per distinct value with an
  occurrence: the same order as the correlation value space.

### 4.2 `profile_revision` — the amendment this phase forces on phase 1

`02-store.md` gives the profile a single `version` used as the upstream match
key (`shipped.version > existing.version` → overwrite), while `09-editor.md`
§4.1 bumps the same counter on any `parameters` change — and the default *is*
site-admin-editable, so both uses collide: a locally edited default either
never receives a shipped update or has its edits silently clobbered by one
(`review-2026-09-02.md` B1).

This phase is where the collision stops being theoretical, because the
materialisation trigger needs the *local* counter: **split the column** into
`version` (matches the shipped file, moved only by `updateDefaults()`) and
`revision` (increments on any `parameters` change, from any editor). Rows
here key on `revision`; a revision bump marks every row for that profile
dirty (§5.3). The split landed in phase 1's schema on 2026-09-03
(`02-store.md` §2); recorded here because this is the phase that made it
load-bearing.

## 5. The worker

A console shell (`cake ValueVerdict sweep`, scheduled the way feed caching
is) plus dirty-marking at the write sites. It is the engine's second caller,
and the reason `03-signals.md` §2.1 required no view dependency: it runs
`ValueVerdictTool` with a synthetic instance-scope `$user`, over a **batch
context builder** — one pass gathering the facts for a set of values, then
scoring. The per-value context build of the page is the classic N+1 at this
scale and stays on the page.

### 5.1 Only staleness moves on its own — so the schedule is computable

Every signal except `lifecycle.staleness` changes only when evidence is
written. Staleness changes with wall-clock time, but **predictably**: its
contribution is `round(fraction × points.fresh)` — an integer that steps at
most `points.fresh` times across the TTL, then the expiry cliff. At
materialisation time the worker therefore knows every future date the stored
score will change, and writes the nearest into `recompute_at` (the expiry
boundary separately into `expires_at`).

The sweep is then two cheap queries:

```
rows WHERE dirty = 1                → evidence changed; recompute
rows WHERE recompute_at <= NOW()    → the score's next step arrived; recompute
```

Recompute, not flip a precomputed value — one code path, and the row's next
`recompute_at` falls out of the same computation. With `points.fresh = 12`
and a 90-day TTL a row recomputes roughly weekly; the step count is bounded
by `points.fresh` and can be coarsened (recompute only on multi-point steps)
if an instance's value population makes weekly too hot.

### 5.2 The cull does not wait for the sweep

`excludeStale` compares `expires_at` to `NOW()` **at query time** (§6), so a
value stops exporting the second its TTL passes even if the sweep has not
visited the row. The sweep's job is only to move `score` and `disposition` —
which is what `minVerdictScore` reads, and that part does lag by design;
`computed_at` is in the row and exposed, so the lag is visible rather than
denied.

### 5.3 Dirty marking

- **Evidence writes** — attribute/object save, sighting add, tag or galaxy
  change — mark the touched value's rows dirty at the write site.
- **A warninglist update** marks everything dirty: list membership can move
  any value's largest signal, and matching a delta is more machinery than a
  full sweep costs. This matches how the warninglist caches already rebuild.
- **A profile `revision` bump** (§4.2) marks all rows for that profile dirty.
- **v1 pragmatism**: dirty flags plus a periodic sweep, not perfect triggers.
  A missed trigger means a stale row until the next scheduled full pass, and
  `computed_at` says so — an honest state, not a silent one.

## 6. The restSearch surface

```
/attributes/restSearch
  includeVerdict:   bool   # attach disposition, score, profile name+revision,
                           # computed_at — gated per Q13 (§7)
  excludeStale:     bool   # cull values whose expires_at has passed
  minVerdictScore:  int    # cull values whose stored score is below N
  verdictProfile:   uuid   # select among materialised profiles
```

Implementation is a join against `value_verdicts` on the value hash — **zero
engine calls in the request path**. The old sketch's names are kept.

- **Filters only remove.** No parameter here returns an attribute the caller
  could not get without it; the caller's ACL is applied exactly as today, and
  the join happens inside it.
- **A value with no row fails open for `excludeStale` and closed for
  `minVerdictScore`**, and the asymmetry is deliberate. `excludeStale` culls
  positively-expired indicators, and only an actual expiry may cull —
  dropping data because a computation has not run is the quiet-lie class
  `01-profile.md` §1.3 forbids. `minVerdictScore` is an opt-in quality floor,
  and a missing score does not meet a floor. Both behaviours are stated in
  the parameter docs so neither is a surprise.
- **`verdictProfile` must name a materialised profile**; anything else is an
  error listing the profiles that are materialised, never a silent fallback
  to the default. In v1 exactly one is, so the parameter is trivially
  satisfied — it exists so the API shape does not change when org-profile
  materialisation lands (§10).
- **`excludeDecayed` and `includeDecayScore` stay**, unchanged and
  undeprecated. `01-profile.md` §7 is explicit that this feature does not
  deprecate MISP's decaying models anywhere but one page, and a filter
  thousands of integrations depend on is not something to retire on the
  strength of one page's redesign.

## 7. Q13 — what `includeVerdict` may attach, and to whom

**The leak, stated.** The instance verdict is computed from instance-wide
evidence. Attaching `score: 84` to an attribute returned to a caller who can
see two occurrences discloses that corroboration exists beyond their ACL —
an aggregate of rows they may not read. Every surveyed TIP ships exactly this
(a vendor or platform score is never the viewer's), so it is defensible; but
MISP's ACL culture is stricter, so it is a decision, not an inheritance.

**Ruled out 2026-09-03: computing the attachment per caller.** That is the
per-request path this whole phase exists to avoid, and it would make the
attached number disagree with the number the gate used.

**Open — the access gate.** Three candidate shapes, not mutually exclusive:

- **A role.** A permission flag (existing or new — Q7's territory) grants
  seeing the instance verdict wholesale.
- **The host organisation.** Users of `MISP.host_org_id` see it; the
  instance's own operators are the natural owners of the instance's
  judgement.
- **Fully public evidence.** Ungated when the row's `fully_public` flag is
  set (§4.1): over evidence everyone can read, the instance verdict equals
  what any caller could compute themselves, so there is nothing to leak.

The composable shape, recorded to give the decision its frame: *attach the
full verdict when `fully_public` is set **or** the caller passes the
role/host-org gate; otherwise attach nothing for that attribute.* Whether
"nothing" needs an honest marker — and whether it is per attribute or one
note on the response — is part of the question; a per-row disclaimer in a
ten-thousand-row response is noise.

**The filters themselves are a smaller instance of the same leak** and should
be resolved with it: an attribute the caller can see, absent from an
`excludeStale` result, reveals one bit derived from hidden evidence.
Recommendation: the filters stay ungated — one bit, opt-in, and exactly the
bit `excludeDecayed` already reveals today — and only the *attachment* is
gated. Recorded as part of Q13 rather than decided.

## 8. What the page gains back

`06-staleness.md` §4.3 accepted, on the strength of this file, that the page
would interim-lose the answer to *"why did this IOC stop firing"*. This phase
repairs it with one line, drawn from the row, rendered where the staleness
panel states the TTL in force:

> Instance verdict: expired since 2026-08-01 (default-v1, r3) — culled from
> `excludeStale` exports since then.

The line names the instance verdict as the distinct object it is (§3.3) — it
does not restyle the viewer's verdict, and on an instance where the caller's
profile *is* the default and their ACL sees everything, the two verdicts
agree and the line is confirmation rather than news. When there is no row
yet, the line is absent, not a placeholder.

## 9. Verification

1. `parallel-lint` over the shell, the tool changes and the migration; then
   `Admin runUpdates` + `Admin schemaDiagnostics` — no diff.
2. Materialise the four demo values on the dev instance; as a **site admin**
   (whose ACL is instance-wide and whose profile is the default), the page's
   verdict and the stored row agree on disposition and score for all four.
   This is the two-verdicts table (§3.3) collapsing when its two axes are
   equal, asserted.
3. The step schedule: a value with a 12-day TTL and `points.fresh = 12`
   recomputes on consecutive days, dropping one point each time, and
   `recompute_at` always names the next step. At the boundary the sweep
   produces the `expired` contribution and clears `recompute_at`.
4. §5.2's query-time cull: set `expires_at` in the past on a row the sweep
   has not touched; `excludeStale` culls it while `minVerdictScore` still
   reads the stale score, and `computed_at` exposes the lag.
5. A sighting write marks the row dirty and the next sweep moves the score;
   a profile `revision` bump marks every row and a full pass rebuilds them.
6. Fail-open/fail-closed: a value with no row survives `excludeStale` and is
   dropped by `minVerdictScore`, both asserted, both documented.
7. `verdictProfile` with an unmaterialised uuid: the honest error, naming
   what is materialised.
8. `excludeDecayed` regression: a query using it returns identical results
   before and after this phase.
9. ACL: for a user with restricted visibility, every attribute returned with
   these filters active is returned without them too — the filters only
   remove. Asserted against a sharing-group fixture.
10. Q13's chosen gate, both branches: a `fully_public` row attaches for an
    ordinary user; a non-public row attaches only through the gate.
11. Load: a ten-thousand-attribute `restSearch` with all four parameters
    performs zero engine calls — asserted via query log — and its runtime is
    the same order as the unfiltered call.

## 10. Out of scope

- **Materialising org profiles.** v2: an opt-in flag on an org-scoped
  profile adds one row per value per opted-in profile; `verdictProfile` is
  already shaped for it. Bounded, deliberate growth — never automatic.
- **Q13's final gate.** The frame and the ruled-out option are recorded
  (§7); the choice among role / host org / fully-public is not made here.
- **Deprecating `excludeDecayed`.** It stays, with `includeDecayScore`,
  undeprecated (§6).
- **Syncing verdicts.** The instance verdict is instance-local, like the
  profile that produced it.
- **Any change to the page's render-time verdict.** Phases 2 and 9 own it;
  this phase adds one line (§8) that reads a row, not the engine.
