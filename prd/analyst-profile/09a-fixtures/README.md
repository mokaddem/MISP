# The 8a fixtures — what each file holds

**Start here if you are building a phase 8b prototype.** Your brief is
[`../09b-prototypes.md`](../09b-prototypes.md); this file is the data
dictionary for the five JSON documents beside it.

Everything here was dumped from a real MISP instance by
[`../09a-fixtures-dump.php`](../09a-fixtures-dump.php), scored by the
real engine, against real attribute rows. **Every figure you draw comes
from these files.** Not one number is yours to invent — a design drawn
against a plausible-looking figure is a design that has to be redrawn
the first time it meets real data, and `prd/value-profile-live/` is the
long record of what that costs.

Check your coverage with:

```bash
python3 prd/analyst-profile/09a-fixtures-check.py
```

Twenty-seven assertions, one per state your candidate has to be able to
draw. If it passes, the states are all in the fixtures; whether your
candidate renders them is §6 of the brief.

## The five files

| File | The board it feeds |
|---|---|
| `index.json` | The **index** board |
| `profile.json` | The **edit** and **view** boards — the whole seven-section view-model |
| `palette.json` | The signals table's three states, the conflict rules, the exclusions, and the loader's error list |
| `bands.json` | The band strip, in four states including the two that are wrong |
| `simulate.json` | The **simulate** board — the ledger diff and the comparison set |

## `index.json`

```
profiles[]            one per row, ordered as the page should show them
  .name .owner        who owns it: "You", an org name, "Instance default"
  .enabled .editable  booleans
  .in_force           exactly one row is true, or none
  .standing.state     in_force | disabled | overridden | other_owner
                      | unresolved
  .standing.winner    only on `overridden`: the profile that beat it
  .signals            {enabled, configured} — how much is switched on
  .version .revision  two counters; see profile.json below
in_force              the winning profile, or null
scoring_off           true when nothing is in force at all
comparison_set[]      the values this reader has pinned
scoring_off_variant   the *other* index state: no profile in force, so
                      no value on this instance is scored. Draw it.
```

**`standing` is the point of this board.** Under the design exactly one
profile applies to a reader, and the commonest confusion the feature can
create is an analyst editing a profile that is not the one weighting
their pages — they forked, forgot, and their organisation's profile
still wins, or their own is disabled. A badge on one row cannot say
*why*; a per-row standing can, and `overridden` names the winner.

## `profile.json`

```
profile               name, uuid, enabled, default, version, revision
value                 the value the contributions below were produced on
sections{}            seven sections, in presentation order
  .title .blurb
  .blocks[]           each block is one of four kinds
bands                 the same shape as bands.json's `ok`
raw                   the whole document as pretty JSON, for the raw editor
warnings[]            saveable complaints, as sentences
```

### The four block kinds — render these and you render everything

| `kind` | Shape | Where |
|---|---|---|
| `fields` | a flat list of labelled scalars | thresholds, relevance, reference scale, enrichment posture |
| `map` | key→value rows plus an *add* affordance | relevance TTLs, org trust, warninglist categories, enrichment |
| `items` | togglable entries, each with its own fields | signals, conflict rules, exclusions |
| `strip` | the quality bands against the attainable bound | thresholds only |

Every **field** carries:

```
key label type       type is int | float | string | bool | select
                     | multiselect
value                what the profile says, or null for "unset"
default              what the code falls back to when unset
options[]            for select / multiselect
help                 one sentence; worth showing somewhere
path[]               the segments a form posts it under, e.g.
                     ["signals","reporting.independent_orgs",
                      "points","per_org"]
generated            true when the field came from a signal's own
                     declared schema rather than from a hand-written form
undeclared           true when the profile carries a key this version
                     cannot label — kept, not dropped
inert                true when the setting is stored and governs nothing
                     yet (enrichment's reuse window). Say so.
```

A **map** block carries `entries[]` with `key`, `label`, `value`, and
sometimes `missing` (the key is not on this instance — a graded
organisation from somebody else's profile) or `shipped` (what the
instance's own roster says, so an override can be shown as an override).
Its `add.options[]` is what a picker may offer: **only keys the map does
not already hold**, never every organisation or every attribute type on
the instance.

### The two counters, which are not the same thing

`revision` is the local edit counter; `version` tracks the shipped file.
A rename moves neither. Changing any weight moves `revision` only. If
your design shows one number, show `revision`.

## `palette.json`

```
items[]               one per signal
  .id .description
  .state              active | available | missing
  .enabled .in_profile
  .badges[]           {id, label, title} — `custom` and `missing`
  .group              Reporting | Sightings | Attribution | Lifecycle.
                      There is no editorial band: D16 removed it, so
                      what a signal is worth in principle is the `cap`
                      in its own points map, in points.
  .contribution       null here — see profile.json for a scored one
  .fields[]           as above, including the generated points/config maps
escalations[]         the conflict rules, same shape
exclusions[]          the four, each with a `layer`
loader_errors[]       {subject, file, reason}
synthesised{}         what the dump had to construct, and why
```

**The three states are the whole reason this is a palette and not a
list.** `active` is in the profile and implemented; `available` is
implemented and not yet in the profile; `missing` is *in the profile and
not implemented on this instance* — it contributes nothing, the
assessment already lists it as not counted, and its configuration is
kept because a redeploy brings the implementation back.

**The points columns differ per row and that is by design.** There are
twelve distinct shapes in this fixture. `{per_org, cap}` sits next to
`{scale, none}` next to `{dated, undated, lagged}`. A design that
assumed three uniform numeric columns is wrong here, and this is the
cheapest possible place to find that out.

**`loader_errors` is the only place an admin ever finds out.** A dropped-in
file that will not parse, a class that is not a signal, an id that
collides — the engine skips all three in silence. Three entries here,
one per refusal reason.

**`synthesised` is the honesty note.** Two states could not be dumped
from this instance because it has no drop-in signals and no broken
files, so the dump wrote them: the `missing` signal and the `custom`
badge. Which signal carries the badge is arbitrary; that a design must
render it is not.

## `bands.json`

```
ok                    the shipped default: high 60, medium 30, bound 129
inverted              medium 60 above high 30 — refused on save
beyond_bound          high 180, past a bound of 129 — refused on save
narrow_catalogue      nine signals disabled, so the bound falls to 52
                      and the shipped high at 60 stops being reachable
                      without anybody touching a band
*_errors[]            the sentence the save answered with
```

Each strip carries `bound`, `bands[]` (`{id, from, to}` for high, medium
and low), `problems[]` and `ok`. **`bound` is the most the enabled
signals could contribute** — the largest positive value in each
`points` map, summed — so a boundary above it can never be reached by
anything, and the save is refused with the arithmetic in the message.
`attainable.per_signal` breaks the bound down if you want to show where
it came from.

## `simulate.json`

```
in_force              the profile weighting this reader's pages
candidate             the profile being edited
candidate_edits{}     the three edits, in words
detail                the ledger diff for `value`
  .rows[]             id, signal, group, before, after, delta, state
  .totals             {before, after, delta}
  .sums               per column: {ledger, quality, ok}
  .axes               lean, quality, band, relevance, fired, rule
  .axes.relevance     the state, plus `.runway` (below)
  .not_counted[]      what each side could not count, and what moved
  .moved[]            the ids that are not `same`
  .changed            false when nothing moved at all
detail_unchanged      the empty diff
comparison[]          one row per pinned value
comparison_empty      []
context_builds        1
bands                 the candidate's strip
```

### The relevance runway

The relevance axis is not just a word. It has a magnitude — a shelf life
— and the shipped value page already draws it, so a design that renders
relevance as a bare state is dropping something the product has.
`axes.relevance.runway` carries it, on the assessed value and on every
comparison row:

```
state / label         `uncertain` and its shipped label "timeline uncertain"
ttl_days              90, and `ttl_from` / `ttl_rule` say where it came from
elapsed_days          16
runway_days           74 left — negative when the value is over its TTL
runway / runway_pct   0.8222, drawn as 82%
aging_fraction        0.33 — where `current` becomes `aging`, a mark on the bar
clock / clock_at      what resets the shelf, and when it last did
expires_at            the date the state flips
uncertain(_note)      why the elapsed count is a lower bound
```

**These are the engine's numbers, not composed ones.** They were read
from `/values/viewRelevance/<b64>` on the dev instance on 2026-09-08 —
`ValueRelevanceTool` through `value_relevance.ctp` — and each row records
its own `runway_source`. The instance's TTL table for these types is
identical to profile 23's own (`ip-dst` 90, `ip-src` 90, `text` 180,
`ip-dst|port` 180), which is what makes them attachable to this
profile's fixture.

The four pinned values cover the four shapes a runway takes:

| Value | State | Runway |
|---|---|---|
| `8.8.8.8` | uncertain | 74 of 90 days left, 82% — a clock running, but a timeline that cannot be trusted |
| `185.234.219.24` | *none* | **no clock at all** — nothing is recorded, so there is no shelf to draw. The same value whose lean is `none` and whose ledger does not exist |
| `45.155.205.233` | expired | 1729 days elapsed of 180 — **1549 days over**, 0% |
| `1.1.1.1` | expired | 201 of 90 — 111 days over, 0%, and quality 34 in the medium band |

That last row is the one worth drawing carefully: **well-evidenced and
long expired at the same time**. It is the clearest proof on the page
that quality and relevance are separate axes, and a design that shows
only quality cannot say it.

The runway is identical before and after in this fixture, because none
of the candidate's three edits touches the TTL table — which is itself
the point: signal weights cannot move this axis.

### The invariant — check it, do not trust it

Each column sums to its own quality **exactly**. In this fixture:
`4` before and `23` after, and the ten row deltas sum to `19`. Nothing
is normalised anywhere in this feature, which is the only reason a diff
of two ledgers is arithmetic a reader can check by hand rather than an
impression. **A ledger column in your candidate that does not add up to
the quality printed under it disqualifies it** (§6 item 4 of the brief).

### The four row states, all present here

| State | Row in this fixture | Reading |
|---|---|---|
| `changed` | `reporting.independent_orgs` 28 → 16 | its cap moved |
| `vanished` | `lifecycle.warninglist` −38 → — | switched off in the candidate |
| `appeared` | `attribution.galaxy` — → −7 | switched on in the candidate |
| `same` | six others | untouched |

Getting all four into one fixture took two attempts, and the reason is
worth knowing before you design the table: **a lowered per-unit weight
is invisible where a signal is saturated at its cap.** The first version
of this fixture dropped `per_org` from 7 to 4 and the row did not move —
eight organisations report this value and both products exceed the cap.
So a design that promises *"change a weight and watch the row move"*
will be wrong for some signals some of the time; the honest promise is
*change a number and the diff shows you what actually happened*.

### The comparison set — four real values, four different answers

| Value | Quality | Band | Direction |
|---|---|---|---|
| `8.8.8.8` | 4 → 23 | low → low | `up` |
| `185.234.219.24` | 0 → 0 | none → none | `none` |
| `45.155.205.233` | 11 → −2 | low → low | `down` |
| `1.1.1.1` | 15 → 34 | low → medium | `up` |

Four states in four rows, which is why they are all worth drawing:
a quality that rose without changing band, **a value with nothing to
assess at all** (band `none`, lean `none` — the honest reading of a
value this reader's permissions and this instance's rows give nothing
for), a value that went *down*, and one whose **band** moved. The
direction is there so a row can be coloured without asserting that the
change is wrong — the analyst is the one deciding that.

`comparison_empty` is what a new analyst actually sees, and it is the
state most likely to be skipped: an instruction — *pin a value and its
two columns appear here* — not a blank table.

### `context_builds`

`1`, because the candidate and the profile in force have the same
`exclusions`. An edit to that section costs two, because exclusions
change *which rows the engine sees* rather than what they are worth —
so both columns are rebuilt and the row counts themselves differ. If
your design has anywhere to say what a simulation cost, this is the
number.
