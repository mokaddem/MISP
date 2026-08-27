# Value Profile — bounding the object-siblings section

**Phase 17.** Extracted from [`value-profile-page.md`](../value-profile-page.md), where this
was §9. **The section numbering is deliberately kept:** the corpus carries
over a hundred references of the form `§9.x`, and they resolve to the
headings below rather than to anything in the main document.

---

## 9. Phase 17 — bounding the object-siblings section

Phases 15 and 16 emptied the queue §7.11 opened: all nine tabs render their own
content and nothing on the page is a placeholder any more. This is the first
phase that exists because of a review of what shipped rather than because an
earlier phase left it in a queue, and it is narrow — one sub-section of one tab,
the fixture underneath it, and one live-data decision that sub-section cannot be
built without.

### 9.1 What this phase covers

The **object siblings** sub-section of the Relationships tab —
[phase 11](value-profile-tabs/03-relationships.md) §6.4, rendered by
`value_relation_cooccurrence.ctp:539-614`. Nothing else on that tab moves: the
ranked values table, the near-match and asserted sections, the facet bar and the
three engine states are all as phase 11 shipped them.

The section is unbounded, and phase 11's own §12 is why nobody looked. It
recorded the join as *"Cheapest thing on the tab, and the highest signal."* —
which is true of the query and says nothing about the result set. The claim was
made on query cost and read as a claim about size.

### 9.2 Siblings scale on an axis no demo value exercises

A sibling row is not a correlation, so `MISP.correlation_limit` does not bound
it — that is the section's whole argument for surviving the suppressed band. It
is bounded instead by **how many objects the value sits in**, which is a
function of the occurrence count. Those are two different axes, and the fixture
only ever stressed the first:

| Demo value | Disposition | Occurrences | Correlations | Sibling rows |
|---|---|---|---|---|
| `185.234.219.24` | MALICIOUS | 10 | 31 | 3 |
| `104.21.34.198` | CONFLICTED | 9 | 1,847 | 3 |
| `8.8.8.8` | BENIGN | **9** | 21,904 | **1** |

`8.8.8.8` was built to be the pathological value: 21,904 correlations, the
suppressed band, the reason section one has three states at all. It carries
**nine occurrences** (`ValueProfileFixture.php:2596`), so the section that
scales on the other axis returned one row and looked settled. The same holds for
the other two: 10 and 9 occurrences (`:131`, `:1378`).

So no demo value has ever had more than ten occurrences, and every panel on the
page that scales on that count was designed and verified against a value that
does not stress it. §9.8 is what else that reaches.

### 9.3 The arithmetic, and why the suppressed value is the worst case

For a value `v`:

```
sibling rows = Σ  ( filled slots of that object − 1 )
               over each occurrence of v that sits in an object
```

The fixture's `domain-ip` objects contribute two rows each (`domain` and
`first-seen` beside our `ip`); `file` and `network-connection` templates have
more slots to fill. So a value with 500 occurrences inside `domain-ip` objects
produces on the order of 1,000 rows, and richer templates push it further.

The worst case is the one the section is proudest of. When a value is
over-correlating, section one collapses to `.vp-suppressed` and lists nothing —
so those thousand-odd unbounded rows become the **only** content on the
Relationships tab. Phase 11 §16 wrote *"object siblings survive the suppressed
band, and that is the point"* as a virtue, and at scale it means the one section
with no brake is the one left standing.

### 9.4 What the shipped template does

Nothing. `value_relation_cooccurrence.ctp:580` is a bare
`foreach ($siblings as $sibling)` with no cap, no pager and no `n of m`; the
badge at `:556` is `count($siblings)`. The ranked values table immediately
below it pages at eight rows through `value_pager` (`00-shared.md` §6), and the
conflicted value's co-occurrence pane is careful enough to print `1–8 of 24`
with `(1,462 in total)` beside it. The section above it, with no bound at all,
renders whatever it is handed and states that number as if it were a total.

### 9.5 Two shapes of "too many", needing opposite treatments

Volume is not one problem here, and this is what makes a pager the wrong first
move:

- **The same sibling value, many times.** 500 objects each pairing the value
  with `dns.google` is 500 rows carrying **one** fact. Paged at eight, it is 63
  pages of the same sentence. This case wants **collapsing**:
  `dns.google · domain · in 487 objects across 12 events`.
- **A different sibling value each time.** 500 objects each naming a distinct
  domain is 500 genuine facts, and no analyst reads 500 rows. This case wants
  **ranking and a stated total** — the idiom section one already owns for 1,847
  correlations.

One aggregation serves both, which is why the phase is small: group on
`(object template, relation, sibling value)`, carry a distinct-object count,
rank by it, page the remainder, state the total. The identical case collapses to
one row by construction; the distinct case ranks and pages.
`attributes.object_id` is indexed (`INSTALL/MYSQL.sql:119`), so the `GROUP BY`
runs on the same index-backed join the section already claims.

### 9.6 The live-data question this phase has to settle

Phase 11 §12 says the join runs over *"occurrences the page has already
fetched"*. That is only true if the page fetched all of them, and the
Occurrences tab pages. The two readings are not both available:

- **Over the fetched page.** One cheap join, and the section shows the siblings
  of occurrence page one while its badge reads like a total. That fails the
  brief's §7 rule — *never let a partial view read as complete* — silently,
  which is the worst way to fail it.
- **Over every occurrence.** Correct, and *"the cheapest thing on the tab"*
  stops being true for a value with thousands of them.

The phase resolves it as the second, bounded and declared: the aggregate is
computed over all of the viewer's occurrences, the join is capped at a stated
number of object ids, and the cap is named on the page when it bites rather than
inferred from a short list. An aggregate over a truncated set is the one thing
this section must not produce, because a count is exactly what a reader will not
double-check.

**ACL.** The occurrence set is already the viewer's — phase 16 established that
four of `185.234.219.24`'s ten occurrences are ACL-hidden — and attributes
inside a visible object still carry their own distribution. So the aggregate is
over visible siblings only and gets the same `.vp-acl-note` treatment section
one has: the hidden rows are counted where they can be and named where they
cannot.

### 9.7 What ships

1. **An aggregated sibling row** — `object template · relation · sibling value ·
   type · objects · events · reported by`, replacing the per-attribute row. The
   `objects` column is the collapse from §9.5 and carries the count as a number,
   not a bar.
2. **Ranking, a pager and a total**, reusing `value_pager` at the tab's existing
   page size, so the section reads the way the table below it does.
3. **The `.vp-acl-note`** and the cap notice from §9.6, both stated rather than
   implied.
4. **The suppressed-value case checked deliberately** — the tab where this
   section is the only content is the one that has to be looked at, not the one
   that gets inferred from the others.
5. **A fourth demo value** whose occurrence count is in the hundreds. This is
   the phase's real cost and it is unavoidable: a value with nine occurrences
   sits in at most nine objects, so no fixture edit to the existing three can
   produce this state without contradicting numbers that nine tabs already
   agree on. Raising `8.8.8.8`'s occurrence count was rejected for exactly that
   — it would rewrite its Occurrences, Sightings, Timeline, History and Verdict
   fixtures to fix a table in one section.
6. **The caption gets rewritten.** *"A join on the object id over occurrences
   this page has already fetched — not a correlation, and not the engine's to
   suppress"* is accurate about provenance and, as §9.1 found, was read as a
   claim about size. It keeps the provenance sentence and drops the implication.

The fourth value renders all nine tabs, as the other three do. Where a tab has
nothing new to say about occurrence scale, it may render at *list capped, total
stated* fidelity rather than being re-authored row by row — recorded here as a
scoping decision so that a later reader does not mistake it for an oversight.

### 9.8 What this phase does not fix, and must not hide

The same blind spot reaches further than this section, and the fourth demo value
is what will make it visible.

**History groups by occurrence.** Phase 16's pick — `H2`, chosen precisely
because *"grouping by occurrence is that addition"* — renders one collapsible
section per occurrence, six for `185.234.219.24`. At 500 occurrences it renders
500 sections. It is the same defect from the same cause, and it is **not in this
phase's scope**: it is a design question about what a per-occurrence history
does when occurrences are the numerous thing, and phase 16's own reasoning is
what has to be revisited to answer it.

It is recorded here rather than fixed because bounding one section and leaving
the other to be found by a user is worse than either. The Occurrences tab pages
already and is fine; the Verdict tab's per-occurrence ledger should be checked
against the fourth value before it is assumed to be.

### 9.9 How the phase runs

**No candidate deck.** §8.6's test is whether the axis is a design choice the
existing primitive does not make, and here it is not: §9.5's two cases prescribe
the treatment rather than leaving a choice between treatments, and the tab
already owns the ranking-plus-pager-plus-ACL-note idiom this should reuse rather
than reinvent one section above it. A deck would be choosing between ways to
render a `GROUP BY`.

**No sub-PRD.** This section is the specification; phases 15 and 16 needed their
own documents because each was a whole tab. Fixture-first as every phase since
8 has been: no live query, no write, every number consistent with what the rest
of the page already claims about the value.

### 9.10 Verification

1. `parallel-lint` over the changed `.ctp` and PHP.
2. The three existing values' Relationships tabs render unchanged in row content
   — the aggregation of three rows is three rows — and their badges still match.
3. The fourth value's siblings section: ranked, paged, the total stated, and the
   sum of the `objects` column reconciling with the header count.
4. The identical-sibling case collapses to one row with its object count, and
   the distinct-sibling case pages — both on the fourth value, since that is the
   only value that can hold either.
5. The fourth value's tab with the correlation section suppressed: siblings
   bounded, the band still saying nothing was stored, and the two not
   contradicting each other.
6. `Object siblings only` on the filter row still narrows the values table, and
   still agrees with the aggregated section above it.
7. The ACL note and the cap notice both appear when they apply and neither when
   it does not.
8. The fourth value renders all nine tabs without a panel erroring, and §9.8's
   History reading is confirmed and written down rather than left as a
   prediction.
9. Both themes.

### 9.11 Exit criterion

A value with hundreds of occurrences renders a siblings section that fits on
the screen, states how many objects each row stands for, states its own total,
and says when it has been capped or ACL-narrowed — and the same value's
Relationships tab under a suppressed correlation section is bounded rather than
becoming the longest thing on the page. The three existing values render as
they did.

### 9.12 Deferred

- **History's per-occurrence grouping at scale**, per §9.8. The finding is this
  phase's; the fix is not.
- **A sibling row's own correlations.** Aggregating to `(template, relation,
  value)` loses the individual `object_id`s, so "which object" becomes a
  drill-down rather than a column. Whether that drill-down is a link to the
  object or an expansion in place is a live-data decision, not a fixture one.
- **Cross-value sibling ranking.** Nothing here says which sibling value matters
  most beyond how many objects hold it; a weighting would need the verdict
  engine §5 puts out of scope.

