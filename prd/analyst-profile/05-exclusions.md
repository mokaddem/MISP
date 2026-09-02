# PRD: Analyst Profile — phase 4, exclusions

**Specification. Nothing built.** Depends on phase 2
([`03-signals.md`](03-signals.md)). Small phase, one structural fix.

Covers the `exclusions` section, and the split of `not_counted` into the two
different things it currently carries.

## 1. What ships

Filters that run over evidence **before** any signal sees it, and the change to
`not_counted` that stops the page attributing the viewer's ACL to the analyst's
profile.

## 2. `not_counted` is two things wearing one coat

The fixture documents `not_counted` at `ValueProfileFixture.php:2261` as
*"evidence the profile deliberately set aside"*. Its actual entries on the
malicious value are:

| Entry | What it really is |
|---|---|
| *"4 occurrences — outside your ACL. Excluded, not hidden. The score you see is the score for your permissions."* | **The viewer's permissions.** Not a profile decision, and must never become one |
| *"Feed presence alone — feeds that merely mirror CIRCL OSINT are not independent corroboration and score once, not three times"* | **Profile policy.** A de-duplication rule |
| *"Self-sightings — 3 sightings from the same org that created the attribute, within an hour of creation"* | **Profile policy.** An exclusion with a time window |

And on the flux value, a fourth kind: *"21,904 correlations"* — the correlation
engine gave up, so a signal had no input. Neither ACL nor policy; a fact about
the data.

**Three kinds, one block, no visual distinction.** A reader cannot tell which
of those they could change by editing their profile, which is precisely the
question the block exists to answer once profiles are real.

### 2.1 The split

`not_counted` keeps its template (`value_verdict_not_counted.ctp`) and gains a
`reason` on each entry:

```php
'not_counted' => array(
    array('reason' => 'acl',      'title' => '4 occurrences',   'note' => '…'),
    array('reason' => 'policy',   'title' => 'Self-sightings',  'note' => '…',
          'exclusion_id' => 'sightings.self'),
    array('reason' => 'nodata',   'title' => '21,904 correlations', 'note' => '…'),
)
```

- **`acl`** — the viewer's permissions. Leads the block, as it does today: *"a
  score computed from a subset should say which subset."* Not configurable, and
  the profile is not named next to it.
- **`policy`** — an exclusion the profile applied. Carries `exclusion_id`, which
  makes it **linkable to the profile's own editor** (phase 8). This is the
  payoff: *"why doesn't this count?"* becomes a click.
- **`nodata`** — a signal had no input. Distinct from silent
  (`03-signals.md` §4.2), because silent means *evaluated and nothing to say*
  while this means *could not evaluate*.

The visual treatment stays one list; the difference is that a `policy` entry is
actionable and the other two are statements. Minimum viable version: `policy`
rows carry a link, the others do not.

## 3. The `exclusions` contract

```json
"exclusions": [
  { "id": "sightings.self",    "enabled": true, "within_hours": 1 },
  { "id": "feeds.mirrored",    "enabled": true, "dedupe_by": "upstream_source" },
  { "id": "orgs.own",          "enabled": false }
]
```

Each id resolves to an implementation that filters `$context` before signals
run — the same class-per-id pattern as signals and escalations.

**Applied to `$context`, once, not per signal.** Two signals reading sightings
must see the same filtered set or the ledger's rows disagree about how many
sightings exist, and a reader summing them by hand would be right to complain.

### 3.1 The three in v1

**`sightings.self`** — sightings from the org that created the occurrence,
within `within_hours` of its creation. The argument is on screen already: an
org confirming its own fresh report is not corroboration. Note the window
matters — a self-sighting a year later *is* information ("we still see this"),
which is why this is a window and not a blanket rule.

**`feeds.mirrored`** — feeds whose content derives from another source counted
once, not per feed. Needs a notion of a feed's upstream, which MISP does not
store — `feeds` has `provider`, `url` and `source_format`, and nothing that
says "this mirrors CIRCL OSINT". So the dedupe key is either `provider` (cheap,
wrong when one provider runs unrelated feeds) or a profile-supplied map (honest,
and more reference data). **Recommendation: `provider` in v1, with the map as a
phase 6 extension if anyone asks.** State the imprecision on the page rather
than implying an exactness that is not there.

**`orgs.own`** — exclude the viewer's own organisation's occurrences and
sightings, so the verdict reflects *what others say*. Off by default and
included because it is the one exclusion an analyst will genuinely want and
cannot get any other way: an org that reported a value cannot currently tell
whether the community agrees with it.

### 3.2 What is deliberately not an exclusion

**ACL.** Filtering by permission is not a rule the profile applies; it is the
boundary of what the profile can see. Making it an `exclusion` id — even a
locked one — would put it in the same list as configurable policy and invite
somebody to add an `enabled: false`.

**Blocklists.** `org_blocklists`, `event_blocklists` and
`sighting_blocklists` already filter at the query layer, instance-wide. A
profile re-litigating them would either duplicate or contradict an
administrator's decision.

## 4. Interaction with the exact-sum invariant

Exclusions change *inputs*, not contributions, so §5.1 is untouched: fewer
sightings means `sightings.volume_recency` produces a smaller number, and the
ledger still sums to the score.

The one thing to get right is **ordering with trust weighting** (phase 6). An
org excluded by `orgs.own` must not also be counted at its trust grade — so
exclusions run first, unconditionally, and trust weighting applies to what
survives. Stated here because the opposite order produces plausible-looking
numbers that are wrong.

## 5. Verification

1. Each exclusion toggled on and off on the same value: the ledger row's number
   changes, the sum still equals the score, and a `policy` entry appears and
   disappears from `not_counted`.
2. `sightings.self` with `within_hours` at 1 and at 8760 — the same value
   produces different sighting counts, and the note states the window.
3. `orgs.own` as a member of a reporting org: the reporting-breadth row drops
   by one org and the note says so.
4. An `acl` entry and a `policy` entry rendered together: the ACL one leads,
   only the policy one is a link.
5. A value where every sighting is excluded: `sightings.volume_recency` must be
   *silent* (`03-signals.md` §4.2), not fire with zero, and the exclusion
   appears in `not_counted` — otherwise the page shows a `0` row and a note
   saying the sightings were excluded, which reads as a contradiction.

## 6. Out of scope

- The feed-upstream map (§3.1, deferred to phase 6 if requested).
- Any change to blocklists or ACL.
- Exclusion rules an analyst writes themselves (Q11).
