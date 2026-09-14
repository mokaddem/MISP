# PRD: Occurrences — the rows no occurrence read can reach

**Status: built, 2026-09-14.** An amendment to phase 22's filled board
row rather than a phase of its own, which is what
[`value-profile-page.md`](../value-profile-page.md) said it would be.

It closes the first of the two concepts
[`value-profile-coverage.md`](../value-profile-coverage.md) §5.1 records
as outstanding on the Occurrences tab. The second, the feed column, is
untouched and still owed.

---

## 1. The defect, on real rows

`value-profile-coverage.md` §2.2:

> a proposed addition (`old_id = 0`) is invisible, so a value held only
> as a proposal renders as §2.12's unknown page.

That is not a hypothetical. The verification instance holds **six
standalone proposals across three values**, and before this change:

| Value | What it is | What the tab said |
|---|---|---|
| `123.123.123.1` | proposed on event 195 by ADMIN, pending | *This value has no occurrences on this instance.* |
| `123.43.32.22` | proposed on event 195 by CIRCL, pending | the same |
| `123.43.32.21` | one occurrence **and** a pending proposal | the occurrence, and no sign of the proposal |
| `tinyurl.com` | one occurrence and two withdrawn proposals | the occurrence only |
| `5.2.3.4` | one occurrence and a withdrawn proposal on an org-only event | the occurrence only |

The first two are the whole of §2.2: three organisations can see that
somebody proposed `123.123.123.1` on event 195, and the page built to
answer *what do we know about this value* answered *nothing*.

---

## 2. What was already there

Two of §5.1's six items were built by later phases and cost nothing
here.

- **The seam parameter (§5.1.6), which §2.4 called the gating item and
  the one that gets more expensive with every phase that ships against
  the current signature.** `Value::conditionsFor` grew its `alias`
  option for phase 25's Timeline lane, so the predicate can be spelled
  `ShadowAttribute.value1` inside `Value` and nowhere else. It was the
  blocker and it is gone.
- **The reader.** `Value::proposalsFor` was written for the same lane.
  It already reaches standalone proposals — its `proposalRow` even
  documents `target => null` as "the state `old_id = 0` names and the
  one the whole page is blind to everywhere else" — already keeps
  withdrawn proposals, and already applies `ShadowAttribute::
  buildConditions` rather than an ACL of this page's invention.

So the model layer needed **one option**: `reach`, which narrows to one
of the two scopes. The Timeline wants both. A caller after *standalone*
proposals wants only `proposed`, because the `target` scope joins
`Attribute` through `old_id` and a standalone proposal has no target —
so that half is a second statement whose empty result is guaranteed.

---

## 3. The counting question, answered

§5.1.3 asked three things and offered its own answer:

> whether a standalone proposal consumes cap, whether the header's *"N
> attribute rows across M events · K organisations"* counts it, and
> whether the footer band's two numbers stay comparable if it does. The
> safest reading is that a proposal is not an attribute row and the
> header should not say it is.

**Taken, and taken structurally rather than by arithmetic.** The rows do
not enter the occurrence row set at all, so there is no count to adjust
and no place a later change could reintroduce one. The table's header,
its rail's facet counts, its cap band, its sort keys and its client-side
paging all operate on exactly the rows they operated on before.

That is what decided the placement. §5.1.1 points at
`Event::__attachProposals`, whose pattern is *edits inline on their
target row, standalone proposals as rows of their own* — and on the
event view that works because the table has no rail counting its rows
and no header stating a total. This one has both. A standalone row in
this `tbody` would be counted by the header as an attribute row, by
eight facet groups as a row with an organisation and a type and a
distribution, and by the pager as a row of a page.

So: **a panel of its own, directly under the table, in the same
column.** `Proposed additions`, with the note that carries the whole
point once rather than once per row —

> These are pending attributes, not occurrences. Nothing has been added
> to the events below — an organisation has asked for it.

**§5.1.2's second `state` token is therefore not needed.** The item
existed because one token, `proposal`, would have had to mean both *this
row has a pending proposal against it* and *this row is a proposal*, and
a facet cannot mean two things. With the rows outside the table the
question dissolves: `state:proposal` keeps its one meaning, and the
block beside the rail is not something the rail counts.

**Its own cap, at 50**, the number phase 26's report list settled on and
for the same reason — a list of exceptions with no filter to narrow
itself owes the reader a stated remainder. The instance's largest count
is two.

---

## 4. The ACL, which is looser here than anywhere else on this page

§5.1.4, applied as MISP wrote it rather than tightened silently.
`ShadowAttribute::buildConditions` ORs `old_id = '0'` past the whole
attribute-and-object distribution test — there is no attribute to test —
so a standalone proposal is gated on **event visibility alone**. The
occurrence table's ACL reasoning does not carry over to the block
beneath it, and `22-occurrences.md`'s summary of that model describes
only the `old_id != 0` branch.

Nothing in the UI says so, under the page's standing rule that it does
not narrate per-value visibility. It is recorded here and asserted in
§6.

---

## 5. What a row shows, and what it does not

Value, type, category, the proposing organisation, the event, the date,
the comment where there is one, and `IDS` where the proposal asks for
the flag. `to_ids` was added to `proposalRow` for this: the table
directly above carries an IDS column, and its absence here would read as
the proposal not having said.

**No distribution badge, and that is not an omission.**
`shadow_attributes` has no distribution column at all — a proposal's
audience is exactly its event's, and the event is named and linked on
every row. A badge would restate what the link already leads to.

**Withdrawn proposals stay, struck through and badged**, the way phase
26's report list keeps withdrawn reports. Somebody proposed it and
somebody discarded it, and both are part of what happened to this value.

**No *Proposed* badge per row**, though the occurrence table's State
column carries one. There a badge marks the rows that are not ordinary
among rows that are; here every row is a proposed addition, the heading
says so, and a badge on each would be the panel title repeated once per
row — directly beside *Withdrawn*, which is the one word on the row that
distinguishes anything.

**It takes `--vp-tl-proposal`**, not `--attribute`. The Timeline's
proposals lane and the Collaboration thread's proposal item already wear
that colour; this is the same kind of record on a third surface, and a
reader who has met one should recognise it without reading the heading.

---

## 6. Verification

### 6.1 Three readers, and the third is the one that proves it

[`22b-standalone-proposals-probe.php`](22b-standalone-proposals-probe.php)
— **77 checks, 0 failures.**

The pair every other probe on this page uses could not settle this one.
All six standalone proposals sit on events 194, 195 and 1595; two of
those are *all communities*, so the CIRCL org admin sees everything the
site admin sees and a working gate is indistinguishable from a dropped
one. Event 194 is CIRCL's own at distribution 0, so the **ADMIN org
admin** is the reader it must close against — and does:

| Value | Site admin | CIRCL org admin | ADMIN org admin |
|---|---|---|---|
| `123.123.123.1` | 1 | 1 | 1 |
| `123.43.32.22` | 1 | 1 | 1 |
| `123.43.32.21` | 1 | 1 | 1 |
| `tinyurl.com` | 2 | 2 | 2 |
| `5.2.3.4` | 1 | 1 | **no block at all** |
| `8.8.8.8` | — | — | — |

The probe asserts that the narrowing happens *somewhere*, and fails if
it does not. It failed on the first run with two readers, which is how
the third was found — the check earning its place rather than decorating
the run.

It also asserts, on every value including the ones with no block, that
`occurrence_stats['total']` equals `Value::occurrenceCountFor` for the
same reader: §3's answer stated as a test rather than as a paragraph. If
a standalone row ever reaches the occurrence row set, that is where it
surfaces.

And that the block is **absent rather than empty** where there is
nothing — `8.8.8.8` carries proposals, but attribute-targeted ones, and
draws no block.

### 6.2 Drawn, in both themes, and measured

[`22b-standalone-render.mjs`](22b-standalone-render.mjs) drives a real
browser, because a block that exists in the DOM and renders 0px tall
looks exactly like a working one to a string search. It reports the
card's box, every row's box, whether the page overflows horizontally,
and whether the accent custom property actually resolved:

- light: card 288×1026, rows 64px and 63px, accent `#1d4ed8`
- dark: the same geometry, accent `#6ea8fe`
- `overflowsX: false` in both; no console errors

Both accents are `--vp-tl-proposal`'s own values, resolved from
`:root` — the check that catches an unset custom property, which
degrades to an invisible border rather than to an error.

The withdrawn rows report `text-decoration-line: line-through` from the
browser rather than from the stylesheet, so the strike is confirmed as
painted.

### 6.3 The reading order

Screenshotted whole for `123.123.123.1`: the table states *This value
has no occurrences on this instance* — which is true, and is the answer
to the question the tab asks — and the block beneath then says what does
exist. Reversing them would open the tab on an exception before stating
the rule.

For `tinyurl.com`, the table reads *Showing 1 of 1 occurrences · 1 event
· 1 organisation* and the rail reads *1 rows*, both unchanged, with two
struck-through proposals in the block below.

### 6.4 Nothing else moved

[`27-panel-regression.py`](27-panel-regression.py) over seven panels ×
two values: all 200, no warnings. The Timeline still draws its proposal
lane — two marks on `2.2.2.2`, three on `tinyurl.com` — so `reach` and
the new `to_ids` field did not disturb the caller `proposalsFor` was
written for.

### 6.5 What the two amended endpoints now cost

[`22b-query-count.php`](22b-query-count.php), which exists because
`00-contract.md` §14.12 says a row moves off its recorded number only
when a phase document records the new one — and names this exact case,
a later phase amending a row phase 22 already filled.

| Value | `forOccurrenceTable` | `forAnalystPreview` |
|---|---|---|
| `123.123.123.1` | 3 | 3 |
| `123.43.32.21` | 6 | 10 |
| `tinyurl.com` | 6 | 8 |
| `8.8.8.8` | **10** | **27** |
| `443` | 7 | 9 |

**`viewOccurrenceTable` goes from 9 to 10**, and the delta was confirmed
by dumping the statements rather than by subtracting: the ten on
`8.8.8.8` are the summary aggregate, the occurrence fetch, `AttributeTag`,
`Tag`, the user setting, the creator-org `Event` read, phase 22's
per-row `proposal_count`, `authorizedIds`, the sharing-group names — the
nine the row already recorded — and then the `shadow_attributes` fetch.
It costs that one statement on every value, including the ones with no
standalone proposal at all, which is the price of the panel being able
to say *there are none* rather than *there are none that I looked for*.

**The tier table did not have to change**, which §14.12 warned it might.
The fetch is a bounded row read with a cap and a stated remainder, and
it never enters the occurrence row set, so §14.4's tiers apply to it
unchanged.

**`viewAnalystPreview` is measured for the first time, at 3 to 27**, and
the board's blank is filled with it. The endpoint was converted by phase
26 §20 after that phase's board pass had already concluded the
Overview's row was untouched, so it has carried a `—` since; the
event-report count (`29-overview.md` §17) is one of those 27, and is a
`SELECT COUNT(*)` rather than a row fetch, which the same dump confirms.

---

## 7. What this leaves

1. **The feed column**, §5.1's other concept for this tab. Untouched.
2. **`O4`'s proposal diff** — what an attribute-targeted proposal
   actually changes, as against the fact that it changed something.
   Already a deliberate deferral in §1.4 and `tabs/01-occurrences.md`
   §10, and unaffected by any of this.
3. **The rest of the page is still blind to a proposal-only value, and
   now visibly so.** Found while verifying this: `/values/view/` for
   `123.123.123.1` draws a fact strip reading *Not recorded · no
   occurrence you can see* on every cell, and the Timeline prints *There
   is no occurrence of this value here to place on an axis* over a value
   carrying a proposal dated 2025-06-04 that its own proposals lane
   would otherwise place.

   Both sentences are **true as written** — they are about occurrences,
   and there are none — so this is a gap rather than a lie, which is why
   it is recorded rather than patched in passing. The Timeline's is
   structural: its axis is derived from the occurrence read, and placing
   a proposal-only value on one is a change to phase 25's panel rather
   than an amendment to phase 22's. The fact strip's is phase 29's.
