# PRD: Analyst Profile — phase 10, the verdict in restSearch

**Direction only. Deliberately later, and nothing here is a specification.**

This file exists for one reason: phase 5 removes the page's ability to explain
why MISP stopped exporting an occurrence, and that is only acceptable if the
replacement is on the record. Without this document the removal is an
unexplained regression. With it, it is an interim state with a named
successor.

**Recorded as D9** (`00-discovery.md` Q8): *the verdict score is to be exposed
to `restSearch`, eventually replacing `excludeDecayed` as the freshness gate.*

## 1. What the page gives up in phase 5, precisely

`excludeDecayed` is a real `restSearch` filter — accepted at
`RestSearchComponent.php:50`, passed through at `MispAttribute.php:3729` and
`MispObject.php:1707`, and applied at `MispAttribute.php:2208` and `2421` —
with `includeDecayScore` alongside it (`RestSearchComponent.php:48`,
`Event.php:164`). It is what makes a decaying model operationally meaningful:
a decayed attribute stops reaching a NIDS.

After phase 5 the Value Profile page does not read `decaying_models`, so when
an analyst asks *"why did this IOC stop firing"* the page cannot answer. Every
other question about a value it can answer; this one it cannot.

The argument for accepting that is not that the question does not matter. It is
that the answer is about to change: if the **verdict** becomes the gate, then
the page explaining the verdict *is* the page explaining the gate, and it
explains it far better than a stack of per-model decay bars ever did — because
the verdict comes with a ledger.

## 2. Why it cannot be done now

### 2.1 The invariant it spends

`01-profile.md` §5.5 — the hero renders *"Not stored, not synchronised"*, and
`../value-profile-verdict-engine.md` §3.6 argues render-time computation is an
**asset**: it makes per-viewer weighting cheap and gives analyst-authored
signals no sync blast radius.

A `restSearch` call returns thousands of attributes. You cannot render-time
compute ten thousand verdicts inside one API request, so this phase needs a
**cached score** — and the hero's sentence becomes false and has to change.

That is a deliberate trade, not an oversight, and it is the reason this is a
separate phase rather than a follow-up to phase 5.

### 2.2 The cache is cheaper than the scope decision suggests

D3 put profiles at user scope, which sounds like it multiplies the cache by the
user count. It does not, if the key is right:

**Cache by `(value, profile_uuid, profile_version)`, never by `(value,
user)`.** Profiles are shared — under D3 a viewer with no profile of their own
resolves to their org's, and most will. A two-hundred-analyst instance
realistically runs one or two profiles per organisation, so cardinality is
distinct-profiles × values.

`09-editor.md` §4.1 is what makes `profile_version` usable as part of the key:
the version moves on any `parameters` change and **not** on a rename or an
enable/disable, so a cosmetic edit does not invalidate every cached verdict.
That condition was written into phase 8 for this reason.

### 2.3 Bulk scoring is a different shape from page scoring

`03-signals.md` §2.2 builds one `$context` per value, from the same aggregates
the live panels use. That is right for one value and wrong for ten thousand —
a per-value context build inside a result loop is the classic N+1 and would
make the filter unusable at the sizes that matter.

So this phase needs a batch path: one pass gathering the facts for every value
in the result set, then scoring. That is real work and it is why "expose the
verdict to restSearch" is not a small change.

### 2.4 Per-analyst API results are a genuine operational property

Under D3, two analysts in one organisation with different profiles would get
**different `restSearch` results for the same query**. An automated pipeline's
output would depend on whose auth key it used.

That may be acceptable — arguably it is the honest consequence of per-analyst
judgement, and MISP already returns different results per key because of ACL.
But it is a property somebody has to decide on deliberately, and options exist:
gate on the org's profile rather than the caller's, or require the filter to
name a profile explicitly.

**Not decided. It is the first question this phase has to answer.**

## 3. Shape, sketched

Not a specification. Enough to show the direction is coherent.

```
/attributes/restSearch
  includeVerdict:   bool        # attach disposition, score, profile name
  excludeStale:     bool        # the excludeDecayed analogue
  minVerdictScore:  int
  verdictProfile:   uuid        # §2.4's explicit form
```

`excludeDecayed` and `includeDecayScore` **stay**, unchanged and undeprecated.
`01-profile.md` §7 is explicit that this feature does not deprecate MISP's
decaying models anywhere but one page, and a filter thousands of integrations
depend on is not something to retire on the strength of one page's redesign.

## 4. What has to be true before this starts

A checklist rather than a plan:

- Phases 1–5 and 9 landed, so there is an engine and a page that uses it.
- The engine has **no view dependency** — required from phase 2
  (`03-signals.md` §2.1) precisely so this phase is a new caller rather than a
  rewrite. If that slipped, fix it before anything here.
- §2.4 decided.
- A batch context builder (§2.3).
- A cache with an eviction story, keyed per §2.2.
- The hero's *"Not stored, not synchronised"* copy rewritten, and
  `../value-profile-verdict-engine.md` §3.6's argument revisited rather than
  quietly contradicted.

## 5. Out of scope

Everything. This file records a direction and its prerequisites so that phase
5's removal is accountable. It chooses no API surface, no cache technology, no
eviction policy and no answer to §2.4.
