<?php
App::uses('AppModel', 'Model');
App::uses('ValueStatsTool', 'Tools');
App::uses('ValueDecayTool', 'Tools');
App::uses('ValueRelationTool', 'Tools');

/**
 * The Value Profile page's per-panel facade.
 *
 * One public method per panel, each returning the array shape that
 * panel's template already reads. `useTable = false`: this model owns no
 * data of its own, it assembles other models' answers into a panel.
 *
 * **Why this is per panel and not per page.**
 * `ValuesController::profileFor()` builds every tab's data and hands the
 * whole array to whichever endpoint asked for it. With a fixture that is
 * one array literal and costs nothing. Live it would be nine tabs of
 * queries per panel request and twenty-odd panel requests per tab visit,
 * so the whole-profile shape does not survive going live — see
 * prd/value-profile-live/00-contract.md §14.1.
 *
 * **Why this is not one big `Value` model.** §4 of the page's PRD
 * refused to build this feature inside `AttributesController` because
 * ~3,800 lines across 40+ actions is not somewhere to add a feature. A
 * single model carrying nine tabs of panel assembly reaches the same
 * size by the same route. `Value` owns the value's identity and its
 * ACL'd occurrence set and is deliberately kept small; this owns
 * assembly and knows what a panel looks like.
 *
 * **What it must not do**, per §14.2: issue its own SQL against
 * attribute value storage. `Value` is the only file in this feature that
 * knows how a value resolves to rows.
 */
class ValueProfile extends AppModel
{
    public $useTable = false;

    /**
     * The most occurrence rows one panel request will load.
     *
     * A cap on the *result size*, not on the query count — the six
     * queries behind the occurrence table are constant in the number of
     * occurrences. On a real instance a value like `443` resolves to
     * tens of thousands of occurrences as the port half of
     * `ip-dst|port`, and fetching those with their events, objects,
     * sharing groups and tags is not a slow page but a page that does
     * not arrive.
     *
     * **300.** Two things bound it, and neither is the query — nothing
     * here scales with occurrence count.
     *
     * The page control renders one button per page inline, so the button
     * count is rows ÷ page size and it shares the panel header with the
     * subtitle. Measured at a 1500px viewport: twelve buttons leave the
     * subtitle readable on two lines, twenty squeeze it to a 16px column,
     * twenty-five push the panel into horizontal overflow. At the default
     * page size of 60 this cap is five pages, which is seven buttons; at
     * the smallest the reader can pick, 25, it is twelve.
     *
     * The other bound is the fragment. A row costs roughly 5.7 KB of
     * markup, so 300 rows is about 1.7 MB — against the 5.9 MB that
     * 1,000 rows of `443` produced when this was first written, which is
     * a fragment that does not arrive. Raising it further is now a
     * question about weight rather than about the pager, which is the
     * more honest place for the limit to sit.
     *
     * When the cap bites, the panel says so — §14.6 keeps cap notices,
     * because a cap is not a permission.
     */
    const OCCURRENCE_CAP = 300;

    /**
     * Most recent first. The table's order was never stated while the
     * rows were fixture data listed in a literal; a value's newest
     * occurrence is the one a reader opening this tab is looking for,
     * and it is also what makes the cap's own wording true.
     */
    const OCCURRENCE_ORDER = 'Attribute.timestamp DESC';

    /**
     * How many of the value's events the co-occurrence section will
     * even look at before it starts choosing.
     *
     * A first bound, and the cheap one. `443` sits in 1,844 events on
     * the verification instance; asking the database how big each of
     * them is took 519 ms over all 1,844 and 44 ms over the most recent
     * 200. Nothing below this line is affected by how many events were
     * discarded here, because the budget below discards far more.
     */
    const RELATION_EVENT_CAP = 200;

    /**
     * An event this size has no co-occurrence signal in it.
     *
     * The largest event on the verification instance holds 843,976
     * attributes and the largest one `8.8.8.8` appears in holds
     * 369,822. In an event that size *every* value co-occurs with every
     * other, so a neighbour list drawn from it says nothing about this
     * value — and reading it costs 4.8 seconds against 0.19 for the
     * other eighteen events put together.
     *
     * This is an editorial line and not only a performance one, which
     * is why the panel states it in words rather than quietly applying
     * it. `24-relationships.md` §4.2.
     */
    const RELATION_EVENT_SIZE_CAP = 10000;

    /**
     * The most attribute rows one co-occurrence request will read.
     *
     * The one number that bounds this section's cost on any instance,
     * whatever shape its events are. Events are taken newest-first
     * until adding the next one would exceed it. Measured: 20,000 rows
     * is 914 ms of fetch on `443` including its tag join, and 379 ms on
     * `8.8.8.8`'s 10,168.
     */
    const RELATION_SCAN_BUDGET = 20000;

    /**
     * Listed neighbour values, and listed object roll-up rows.
     *
     * The counts above them stay exact at any cardinality because they
     * are folded from the whole scope; this bounds only what is drawn.
     *
     * **100, and the pager is what sets it.** `value_pager` pages
     * client-side at `RELATION_PAGE_SIZE`, and phase 22 measured where
     * that control breaks: twelve buttons leave the panel subtitle
     * readable, twenty squeeze it to a 16px column, twenty-five push
     * the header into horizontal overflow. 100 rows at 8 a page is
     * thirteen. 200 was tried first and is 25 buttons — and, on
     * `8.8.8.8`, a 1.05 MB fragment, because this panel ships all
     * three roll-ups at once so the reader can switch between them
     * without a request.
     */
    const RELATION_ROW_CAP = 100;

    /** Rows per page in the co-occurrence and sibling lists. */
    const RELATION_PAGE_SIZE = 8;

    /**
     * Nodes per notion in the rail's neighbourhood graph.
     *
     * The graph is a neighbourhood, not the table with springs on it.
     * Twelve per notion is 37 nodes at most, which a force layout
     * settles in a 300px rail without the labels colliding; the table
     * beside it is where a reader goes for the hundredth neighbour.
     * The sub-line says how many of the total are drawn.
     */
    const GRAPH_NODE_CAP = 12;

    /**
     * Objects the sibling join will read.
     *
     * `0.0.0.0` sits in 32,921 distinct objects here, one per row of a
     * flood capture. The fixture named this bound 500 and the live
     * shape keeps it: 500 objects is 5,500 sibling rows and 70 ms.
     */
    const SIBLING_OBJECT_CAP = 500;

    /**
     * Occurrence UUIDs the asserted section resolves claims against.
     *
     * The only cap on that section, and it is on the *lookup* rather
     * than on the claims: analyst relationships are written one at a
     * time by people, so the list itself is never truncated. A value
     * with more occurrences than this can carry a claim on one the
     * lookup did not reach, and the panel says so.
     */
    const CLAIM_OCCURRENCE_CAP = 300;

    /**
     * @var array Lazily loaded models, by alias
     */
    private $models = array();

    /**
     * @var array|null The occurrence summary, once per request
     */
    private $summary = null;

    /**
     * @var array|null The co-occurrence fold, once per request
     */
    private $cooccurrence = null;

    /**
     * @param string $alias
     * @return Model
     */
    private function model($alias)
    {
        if (!isset($this->models[$alias])) {
            $this->models[$alias] = ClassRegistry::init($alias);
        }
        return $this->models[$alias];
    }

    /**
     * The numbers on the tab bar, corrected where the page frame and a
     * converted tab would otherwise contradict each other.
     *
     * Not a panel, and the only method here that is not. The frame —
     * tab badges, fact strip, banner chips — is built in one call to
     * `ValueProfileFixture` and belongs to the Overview's phase, which
     * has not run. That was harmless while every tab was fixture-backed
     * and both halves agreed; it stopped being harmless the moment a
     * tab went live, because a badge and the panel two inches under it
     * then state different numbers for one value. On `8.8.8.8` the
     * badges read 9 and 17 against 23 occurrences and 53 reports.
     *
     * **Occurrences gets a real number.** One `COUNT`, and pointedly
     * the same call `forOccurrenceTable` makes for the total its own
     * header prints — not another aggregate that ought to agree with
     * it. Two counts that should match are two counts that can drift;
     * one call cannot disagree with itself.
     *
     * **Sightings gets no number at all**, and the key is removed
     * rather than zeroed. A sighting badge has to be the viewer's:
     * `Sightings_policy` hides whole reports and two readers would read
     * two numbers off one tab bar. Getting the viewer's count means
     * running the policy over the rows, which is the panel's own 13
     * queries — paid on every page load, for a tab most readers never
     * open. No number is better than a wrong one, and the Timeline and
     * History tabs already carry no badge for the same reason.
     *
     * **Revisit when a cheap viewer-scoped count exists.** It would
     * take `Sighting` growing a counting method that applies the policy
     * in SQL instead of in PHP over fetched rows — worth doing when the
     * Overview's phase converts the frame, since the fact strip's
     * `%d sightings` line needs exactly the same number.
     *
     * **Relationships gets no number either, and for a sharper reason.**
     * The fixture's badge was the *correlation* total, and nothing on
     * the live tab computes one: co-occurrence there is an event join
     * rather than correlation output (`24-relationships.md` §3), so the
     * old number is not merely stale, it counts something the tab no
     * longer claims. The join's own total is available — it is
     * `distinct_values` — but only by running the panel's whole scan,
     * up to 20,000 attribute rows and about a second on the heaviest
     * value on the instance, on every page load. Same conclusion as
     * sightings by the same route: no number beats a wrong one.
     *
     * **Revisit if the scan ever becomes a shared per-request context**
     * — `24-relationships.md` §15.1 item 1 — since the badge would then
     * cost nothing beyond a tab visit that was going to happen anyway.
     *
     * @param array $user
     * @param string $value
     * @param array $counts The frame's counts, from the fixture
     * @return array The same, with the badges that can be told truly
     */
    public function forTabCounts(array $user, $value, array $counts)
    {
        $counts['occurrences'] = $this->model('Value')
            ->occurrenceCountFor($user, $value);
        unset($counts['sightings'], $counts['relationships']);
        return $counts;
    }

    /**
     * The Occurrences tab: the facet rail and the table it counts.
     *
     * One method for both, because a facet count and the rows it counts
     * have to come out of one assembly or they can disagree with each
     * other — which is the same reason `viewOccurrenceTable` is one
     * endpoint rather than two.
     *
     * Six queries, none of them per-occurrence or per-event:
     *
     *   1. the viewer's occurrence count            `Value`
     *   2. the occurrence rows, capped              `Value`
     *   3. their attribute tags (the hasMany half of 2's contain)
     *   4. the tag records behind those             `MispAttribute`
     *   5. `Event.Orgc` for all N events at once    `Event`
     *   6. pending proposals per row                `ShadowAttribute`
     *
     * @param array $user
     * @param string $value
     * @param array $options Reserved; `types` reaches `Value`
     * @return array
     */
    public function forOccurrenceTable(array $user, $value,
        array $options = array()
    ) {
        $valueModel = $this->model('Value');
        $total = $valueModel->occurrenceCountFor($user, $value, $options);
        $rows = $valueModel->occurrencesFor(
            $user,
            $value,
            array_merge($options, array(
                'limit' => self::OCCURRENCE_CAP,
                'order' => self::OCCURRENCE_ORDER,
            ))
        );

        $this->attachTags($rows);
        $rows = $this->attachCreatorOrgs($user, $rows);
        $this->attachProposalCounts($rows);
        $this->attachEffectiveDistribution($user, $rows);

        $stats = ValueStatsTool::occurrenceStats($rows, $total);

        return array(
            'value' => $value,
            'occurrences' => $rows,
            'occurrence_stats' => $stats,
            /*
             * Null rather than a set of zero groups on a value with no
             * occurrence the viewer may see: a rail of zeroes is a lie
             * about the value (tabs/00-shared.md §5), and the table
             * renders one honest empty state at full width instead.
             */
            'occurrence_facets' => empty($rows)
                ? null
                : ValueStatsTool::occurrenceFacets($rows, $total),
            'occurrence_cap' => $total > $stats['shown']
                ? array('shown' => $stats['shown'], 'total' => $total)
                : null,
        );
    }

    /**
     * The Sightings tab's chart: the reports as a stacked histogram and
     * every applicable model's score drawn through it.
     *
     * The slow panel of the five, and the reason the tab is split into
     * five endpoints rather than one — see `ValuesController`. The decay
     * envelope is what costs: it is the only thing on this page that
     * evaluates a formula per occurrence per day.
     *
     * @param array $user
     * @param string $value
     * @param array $options Reserved; `types` reaches `Value`
     * @return array
     */
    public function forSightingChart(array $user, $value,
        array $options = array()
    ) {
        $context = $this->sightingContext($user, $value, $options);
        $span = $this->spanFor($user, $value, $context, $options);
        $decay = $this->decayFor($user, $value, $context, $span, $options);
        return array(
            'value' => $value,
            'sightings' => $this->sightingHeader($context),
            'sighting_series' => $span === null
                ? null
                : ValueStatsTool::sightingSeries(
                    $context['sightings'],
                    $span,
                    $context['totals'],
                    $decay['curves']
                ),
            'sighting_notes' => ValueStatsTool::sightingNotes(
                $context['totals']
            ),
            'decay' => $decay['models'],
        );
    }

    /**
     * The Overview's sightings card: the three counts, who filed them,
     * and a 90-day sparkline.
     *
     * On the Overview and not the Sightings tab, and the only panel of
     * either that this method serves. It is here rather than in the
     * Overview's own live phase because of what it is made of: the same
     * `sightingContext` the tab's four endpoints share, so converting it
     * is wiring rather than new work, and leaving it on the fixture
     * meant a card and a tab on one page that could disagree about the
     * same value — the tab counting what the database holds and the card
     * counting what a literal said.
     *
     * **The Overview's other panels stay on the fixture**, including
     * `value_verdict_card`, which `00-contract.md` §14.12 blocks until a
     * verdict engine exists. This converts one card of that tab and
     * claims nothing about the rest, which is what §14.12's note about
     * not treating a tab as indivisible asks for.
     *
     * No decay work, like the list: a card above the fold should not
     * wait for a curve.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array
     */
    public function forSightings(array $user, $value,
        array $options = array()
    ) {
        $context = $this->sightingContext($user, $value, $options);
        /*
         * No spark at all for a value nobody has reported, rather than
         * forty empty columns. The card already says `Nobody has
         * reported seeing this` underneath, and a flat strip over that
         * sentence is a chart of nothing.
         *
         * Quiet is not the same as absent, so a value with reports but
         * none in the last ninety days keeps its strip: there, the
         * empty columns are the answer to the question the strip asks.
         */
        $reported = $context['totals']['total'] > 0;
        return array(
            'value' => $value,
            'sightings' => $this->sightingHeader($context) + array(
                'spark' => $reported
                    ? ValueStatsTool::sightingSpark(
                        $context['sightings'],
                        date('Y-m-d')
                    )
                    : array(),
            ),
        );
    }

    /**
     * The individual sightings, and the range note the brush drives.
     *
     * No decay work at all, which is the point of it being its own
     * endpoint: the table is the part of the tab a reader can act on and
     * it should not wait for a curve.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array
     */
    public function forSightingList(array $user, $value,
        array $options = array()
    ) {
        $context = $this->sightingContext($user, $value, $options);
        return array(
            'value' => $value,
            'sighting_rows' => ValueStatsTool::sightingList(
                $context['sightings'],
                $context['sighted']
            ),
            'sighting_notes' => ValueStatsTool::sightingNotes(
                $context['totals']
            ),
        );
    }

    /**
     * The rail's decay card.
     *
     * It pays the envelope's cost a second time rather than sharing the
     * chart's, because the two panels are two requests and this page has
     * no cache — §14.4 leaves caching out deliberately. What it buys is
     * that the number in the rail is the last point of the curve in the
     * chart by construction rather than by coincidence, which is the
     * sentence the card closes with.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array
     */
    public function forSightingDecay(array $user, $value,
        array $options = array()
    ) {
        $context = $this->sightingContext($user, $value, $options);
        $decay = $this->decayFor(
            $user,
            $value,
            $context,
            $this->spanFor($user, $value, $context, $options),
            $options
        );
        return array(
            'value' => $value,
            'sightings' => $this->sightingHeader($context),
            'decay' => $decay['models'],
        );
    }

    /**
     * The rail's reporters card: one bar per organisation, every report
     * it filed of any type.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array
     */
    public function forSightingReporters(array $user, $value,
        array $options = array()
    ) {
        $context = $this->sightingContext($user, $value, $options);
        return array(
            'value' => $value,
            'sightings' => $this->sightingHeader($context),
        );
    }

    /**
     * The rail's write card, still disabled, and the fan-out sentence
     * that has to be true before it ever is enabled.
     *
     * The count is the occurrence set `Sighting::saveSightings` would
     * actually write to: it resolves the value through
     * `fetchAttributesSimple`, which is the same fetcher behind
     * `Value::occurrenceIdsFor` and neither of them drops a
     * soft-deleted row. So the number here is larger than the number of
     * occurrences the sighting *list* beside it can show, and it is the
     * write's number that this card owes the reader.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array
     */
    public function forSightingAdd(array $user, $value,
        array $options = array()
    ) {
        $summary = $this->summaryFor($user, $value, $options);
        return array(
            'value' => $value,
            'sighting_fanout' => array(
                'occurrences' => $summary['occurrences'],
                'events' => $summary['events'],
                'orgs' => $summary['orgs'],
            ),
            /*
             * The element counts these when no `sighting_fanout` is
             * supplied, which is how `ValueProfileFixture` still drives
             * it. Live it is empty and the counts above are used: three
             * `COUNT(DISTINCT …)` beat materialising 48,255 rows to
             * count them, by 617 ms on `443`.
             */
            'occurrences' => array(),
        );
    }

    /**
     * The reports on a value, and the summary of the occurrence set they
     * were filed against. Every sightings panel starts here.
     *
     * One query of ours plus the two `Sighting::listSightings` makes,
     * and **none of them touches every occurrence the value has**:
     *
     *   1. the occurrences carrying a report          `Value`
     *   2. `listSightings`' own attribute re-fetch    `MispAttribute`
     *   3. the sighting rows                          `Sighting`
     *
     * Query 1 is the one this panel could not do without, and the first
     * version of this phase did not have it: it scoped `listSightings`
     * by the value's whole occurrence set. On `443` that is 48,255 ids
     * handed to a fetcher that re-resolves every one of them, measured
     * at 1.6 to 3.4 seconds per panel — for three sightings. Narrowing
     * to the occurrences that have actually been reported is the whole
     * difference between this tab working on a real instance and not.
     *
     * **`listSightings` re-resolves the ids it is given**, through
     * `fetchAttributes` with `Attribute.deleted = 0` forced. So a report
     * filed against a soft-deleted occurrence is invisible to this tab
     * while the occurrence itself is visible on the Occurrences tab —
     * a defect in shared code, reported rather than fixed here per
     * §14.7. It is also why the ids are pre-filtered: handed nothing but
     * soft-deleted ids the fetcher returns nothing and `listSightings`
     * throws `MethodNotAllowedException` rather than an empty list.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array `summary`, `sighted`, `sightings`, `totals`, `span`
     */
    private function sightingContext(array $user, $value,
        array $options = array()
    ) {
        $sighted = $this->model('Value')->sightedOccurrenceIdsFor(
            $user,
            $value,
            $options
        );
        $sightable = array();
        foreach ($sighted as $id => $occurrence) {
            if (empty($occurrence['deleted'])) {
                $sightable[] = $id;
            }
        }
        $sightings = array();
        if (!empty($sightable)) {
            $sightings = $this->model('Sighting')->listSightings(
                $user,
                $sightable,
                'attribute',
                false,
                false,
                false
            );
        }
        return array(
            'sighted' => $sighted,
            'sightings' => $sightings,
            'totals' => ValueStatsTool::sightingTotals($sightings),
        );
    }

    /**
     * The occurrence summary, fetched at most once per request.
     *
     * Lazy because two of the five panels never need it and it is the
     * one query on this tab whose cost tracks the value's size: 413 ms
     * on `443`, whose 48,255 occurrences it has to scan to count them.
     * The reporters card and the sightings table are the tab's fast
     * panels and asking them to wait for it made them four times
     * slower for nothing.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array From `Value::occurrenceSummaryFor`
     */
    private function summaryFor(array $user, $value, array $options)
    {
        if ($this->summary === null) {
            $this->summary = $this->model('Value')
                ->occurrenceSummaryFor($user, $value, $options);
        }
        return $this->summary;
    }

    /**
     * The chart's span, or null when the value has no occurrence this
     * viewer can see — there is then no axis to draw and no window to
     * invent one over.
     *
     * @param array $user
     * @param string $value
     * @param array $context From sightingContext
     * @param array $options
     * @return array|null
     */
    private function spanFor(array $user, $value, array $context,
        array $options
    ) {
        $summary = $this->summaryFor($user, $value, $options);
        return $summary['occurrences'] === 0
            ? null
            : ValueStatsTool::sightingSpan(
                $summary,
                $context['sightings'],
                date('Y-m-d')
            );
    }

    /**
     * The `sightings` key three of the five panels read: the header
     * counts, the reporter bars and the phrase for the last report.
     *
     * @param array $context From sightingContext
     * @return array
     */
    private function sightingHeader(array $context)
    {
        $totals = $context['totals'];
        return array(
            'total' => $totals['total'],
            'fp' => $totals['fp'],
            'expiration' => $totals['expiration'],
            'reporters' => $totals['reporters'],
            'last' => ValueStatsTool::agoPhrase(
                $totals['last_stamp'],
                time()
            ),
        );
    }

    /**
     * The models that score this value, their current scores, and one
     * curve each.
     *
     * The aggregation rule is `ValueDecayTool`'s and is stated there:
     * the per-day maximum across occurrences, labelled with the
     * occurrence holding it. What lives here is everything that needs a
     * model or a query — which models apply, each occurrence's base
     * score, and MISP's own formula object — because a tool under
     * `app/Lib/Tools/Value*` may not fetch anything (§14.5).
     *
     * Queries, none of them per occurrence:
     *
     *   4. the occurrence rows with their attribute tags
     *   5. the tag records behind them
     *   6. the event tags over every event the occurrences sit on
     *   7. every enabled model this viewer may use, and their
     *      attribute-type mappings — two queries, flat
     *
     * @param array $user
     * @param array $context From sightingContext
     * @return array `models` and `curves`
     */
    private function decayFor(array $user, $value, array $context,
        $span, array $options = array()
    ) {
        if ($span === null) {
            return array('models' => array(), 'curves' => array());
        }
        $resets = ValueDecayTool::resetStamps($context['sightings']);
        $capped = $this->decaySet($user, $value, $context, $options);
        $models = $this->modelsFor($user, $capped);
        if (empty($models)) {
            return array('models' => array(), 'curves' => array());
        }
        $tagged = $this->taggedOccurrences($user, $capped);
        $grid = $this->dayGrid($span);
        $decayModel = $this->model('DecayingModel');

        /*
         * Elapsed time is a property of the occurrence and the day, not
         * of the model, so it is computed once and read by every model.
         * Inside the loop below it was the same walk twice — measured at
         * 208 ms for two models over 23 occurrences and 1,095 days,
         * against 120 ms hoisted.
         */
        $elapsedFor = array();
        foreach ($capped as $id => $occurrence) {
            $elapsedFor[$id] = ValueDecayTool::elapsed(
                isset($resets[$id]) ? $resets[$id] : array(),
                ValueStatsTool::anchorStamp($occurrence),
                $grid
            );
        }

        $out = array('models' => array(), 'curves' => array());
        foreach ($models as $model) {
            $formula = $decayModel->getModelClass($model);
            $parameters = $model['DecayingModel']['parameters'];
            $threshold = (int)$parameters['threshold'];
            /*
             * `Polynomial`'s score depends on the base and the elapsed
             * time and on nothing else about the attribute, so
             * occurrences sharing a base can be collapsed to the one
             * with the least elapsed time — exactly, not approximately.
             * `PolynomialExtended` reads the attribute's `retention`
             * tags and `Sightings` ignores elapsed time, so both take
             * the per-occurrence path. `ValueDecayTool::groupByBase`
             * carries the argument.
             */
            $groupable = get_class($formula) === 'Polynomial';
            $bases = array();
            $bestBase = null;
            foreach ($capped as $id => $occurrence) {
                if (!isset($tagged[$id])
                    || !in_array(
                        $occurrence['type'],
                        $model['DecayingModel']['attribute_types'],
                        true
                    )
                ) {
                    continue;
                }
                $base = (float)$formula->computeBasescore(
                    $model,
                    $tagged[$id]
                )['base_score'];
                if ($bestBase === null || $base > $bestBase) {
                    $bestBase = $base;
                }
                $bases[$id] = array(
                    'base' => $base,
                    'event_id' => $occurrence['event_id'],
                );
            }
            if (empty($bases)) {
                continue;
            }
            $scored = count($bases);
            $candidates = array();
            if ($groupable) {
                $groups = ValueDecayTool::groupByBase($bases, $elapsedFor);
                foreach ($groups as $group) {
                    $points = array();
                    foreach ($group['elapsed'] as $seconds) {
                        $points[] = $seconds === null
                            ? null
                            : (int)round($formula->computeScore(
                                $model,
                                array(),
                                $group['base'],
                                $seconds
                            ));
                    }
                    $candidates[] = array(
                        'owner' => $group['owner'],
                        'event_id' => $group['event'],
                        'points' => $points,
                    );
                }
            } else {
                foreach ($bases as $id => $meta) {
                    $points = array();
                    foreach ($elapsedFor[$id] as $seconds) {
                        $points[] = $seconds === null
                            ? null
                            : (int)round($formula->computeScore(
                                $model,
                                $tagged[$id],
                                $meta['base'],
                                $seconds
                            ));
                    }
                    $candidates[] = array(
                        'owner' => $id,
                        'event_id' => $meta['event_id'],
                        'points' => $points,
                    );
                }
            }
            $envelope = ValueDecayTool::envelope($candidates);
            $last = count($envelope['points']) - 1;
            $score = $envelope['points'][$last];
            if ($score === null) {
                continue;
            }
            $reset = ValueDecayTool::lastReset($resets, $capped);
            $out['models'][] = array(
                'model' => $model['DecayingModel']['name'],
                'score' => (int)$score,
                'threshold' => $threshold,
                'base' => (int)round($bestBase),
                'lifetime' => (int)$parameters['lifetime'],
                'decayed' => $score < $threshold,
                'permanently_under' => $bestBase < $threshold,
                'reset_on' => $reset === null
                    ? date('Y-m-d', ValueStatsTool::anchorStamp(
                        $capped[$envelope['owner'][$last]]
                    ))
                    : date('Y-m-d', $reset['at']),
                'reset_by' => $reset === null
                    ? null
                    : $this->reporterAt($context['sightings'], $reset),
                /*
                 * Which occurrence holds the number, which is half of
                 * the aggregation rule rather than a decoration on it.
                 */
                'held_by' => array(
                    'attribute_id' => $envelope['owner'][$last],
                    'event_id' => $envelope['event'][$last],
                ),
                'over' => $scored,
                'of' => $this->summaryFor(
                    $user,
                    $value,
                    $options
                )['occurrences'],
            );
            $out['curves'][] = array(
                'model' => $model['DecayingModel']['name'],
                'threshold' => $threshold,
                'points' => $envelope['points'],
            );
        }
        return $out;
    }

    /**
     * The occurrences the envelope is computed over: every occurrence
     * that has been reported, plus the most recently updated of the
     * rest, up to the cap.
     *
     * **The cap's ordering is the answer rather than a guess about it.**
     * A score falls as elapsed time grows, and for an occurrence nobody
     * has reported the clock runs from its own date — so among
     * un-reported occurrences the newest is the highest-scoring, for any
     * two that share a base score. `ORDER BY Attribute.timestamp DESC`
     * therefore puts the candidates that could hold the maximum first,
     * and the reported ones are added unconditionally because a report
     * can lift an old occurrence above a new one.
     *
     * The envelope over a subset is a lower bound on the envelope over
     * the whole set, and the rail card prints `N of M occurrences
     * scored` when the two differ. `ValueDecayTool::OCCURRENCE_CAP`
     * carries the number and the reason.
     *
     * @param array $user
     * @param string $value
     * @param array $context From sightingContext
     * @param array $options
     * @return array As `Value::occurrenceIdsFor` returns
     */
    private function decaySet(array $user, $value, array $context,
        array $options
    ) {
        $newest = $this->model('Value')->occurrenceIdsFor(
            $user,
            $value,
            array_merge($options, array(
                'limit' => ValueDecayTool::OCCURRENCE_CAP,
                'order' => self::OCCURRENCE_ORDER,
            ))
        );
        return $context['sighted'] + $newest;
    }

    /**
     * Who filed the report that last reset the clock.
     *
     * Read off the rows already fetched rather than queried again: the
     * sighting set is in hand and a second lookup could disagree with
     * it.
     *
     * @param array $sightings Rows as `Sighting::listSightings` returns
     * @param array $reset From `ValueDecayTool::lastReset`
     * @return string|null
     */
    private function reporterAt(array $sightings, array $reset)
    {
        foreach ($sightings as $sighting) {
            if ((int)$sighting['Sighting']['attribute_id']
                    === $reset['attribute_id']
                && (int)$sighting['Sighting']['date_sighting']
                    === $reset['at']
            ) {
                $name = $sighting['Organisation']['name'] ?? '';
                return $name === '' ? __('Others') : $name;
            }
        }
        return null;
    }

    /**
     * The decaying models that apply to any of this value's types.
     *
     * `fetchAllAllowedModels` is MISP's own ACL'd fetcher — a model is
     * visible to its owning organisation or to everyone — and with
     * `$full` it resolves each model's applicable types as the union of
     * the list a default model ships with and the mappings an
     * administrator added. So it answers *which models may this viewer
     * use and what do they cover* in one call, and the value's types are
     * matched against that in PHP.
     *
     * `DecayingModelMapping::getAssociatedModels($user, $type)` is the
     * route `attachScoresToAttribute` takes and it was tried first. It
     * asks per type and re-reads every default model each time, which
     * measured at thirteen queries for a value with three types where
     * this is two. The two answer the same question — the union
     * `getAssociatedModels` builds with `array_merge_recursive` is the
     * union `$full` builds with `Hash::extract` — and this one does not
     * grow with the number of types a value has.
     *
     * @param array $user
     * @param array $occurrences As `Value::occurrenceIdsFor` returns
     * @return array
     */
    private function modelsFor(array $user, array $occurrences)
    {
        $types = array();
        foreach ($occurrences as $occurrence) {
            $types[$occurrence['type']] = true;
        }
        $models = $this->model('DecayingModel')->fetchAllAllowedModels(
            $user,
            true,
            array(),
            array('DecayingModel.enabled' => true)
        );
        $applicable = array();
        foreach ($models as $model) {
            $covered = array_intersect_key(
                $types,
                array_flip($model['DecayingModel']['attribute_types'])
            );
            if (!empty($covered)) {
                $applicable[] = $model;
            }
        }
        return $applicable;
    }

    /**
     * The occurrence rows a base score is computed from, carrying the
     * attribute's own tags and its event's.
     *
     * `computeBasescore` reads `AttributeTag` and `EventTag` and
     * prioritises the former over the latter, so both are needed or
     * every score is the model's `default_base_score`.
     * `getScoreOvertime` fetches the event tags one attribute at a
     * time; this is one query for every event the value sits in.
     *
     * @param array $user
     * @param array $occurrences As `Value::occurrenceIdsFor` returns
     * @return array attribute id => an array shaped for computeBasescore
     */
    private function taggedOccurrences(array $user, array $occurrences)
    {
        if (empty($occurrences)) {
            return array();
        }
        $rows = $this->model('MispAttribute')->fetchAttributesSimple(
            $user,
            array(
                'conditions' => array(
                    'Attribute.id' => array_keys($occurrences),
                ),
                'contain' => array('Event', 'Object', 'AttributeTag'),
            )
        );
        if (empty($rows)) {
            return array();
        }
        $this->model('MispAttribute')->attachTagsToAttributes(
            $rows,
            array('includeAllTags' => true)
        );
        $eventIds = array();
        foreach ($rows as $row) {
            $eventIds[$row['Event']['id']] = true;
        }
        $eventTags = $this->model('Event')->EventTag->find('all', array(
            'recursive' => -1,
            'contain' => array('Tag'),
            'conditions' => array(
                'EventTag.event_id' => array_keys($eventIds),
            ),
        ));
        $byEvent = array();
        foreach ($eventTags as $eventTag) {
            $eventTag['EventTag']['Tag'] = $eventTag['Tag'];
            $byEvent[$eventTag['EventTag']['event_id']][] =
                $eventTag['EventTag'];
        }
        $tagged = array();
        foreach ($rows as $row) {
            $id = (int)$row['Attribute']['id'];
            $attribute = $row['Attribute'];
            $attribute['AttributeTag'] = isset($row['AttributeTag'])
                ? $row['AttributeTag']
                : array();
            $attribute['EventTag'] = isset($byEvent[$row['Event']['id']])
                ? $byEvent[$row['Event']['id']]
                : array();
            $tagged[$id] = $attribute;
        }
        return $tagged;
    }

    /**
     * The day grid the curves are sampled on: one point per day of the
     * span, at the end of each day, and `now` for the last one.
     *
     * End of day rather than start, so a report filed this morning has
     * already moved today's point. And `now` rather than the end of
     * today, so the curve's last point is the number
     * `DecayingModelBase::computeCurrentScore` would compute — which is
     * what makes the rail card's closing sentence true rather than
     * approximately true.
     *
     * @param array $span From `ValueStatsTool::sightingSpan`
     * @return array Ascending unix timestamps
     */
    private function dayGrid(array $span)
    {
        $now = time();
        $grid = array();
        $day = strtotime($span['from'] . ' 00:00:00');
        $stop = strtotime($span['to'] . ' 00:00:00');
        while ($day <= $stop) {
            $end = $day + 86399;
            $grid[] = min($end, $now);
            $day += 86400;
        }
        if (empty($grid)) {
            $grid[] = $now;
        }
        return $grid;
    }

    /**
     * The tag records behind each row's attribute tags.
     *
     * `contain => ['AttributeTag']` gives only `tag_id`; this turns
     * those into the records the chips and the rail's facets are drawn
     * from. `includeAllTags` because `Tag.exportable = 0` is a statement
     * about exports, not about visibility — this page is an inspection
     * view, and a tag the reader can see on the event page should not
     * disappear here.
     *
     * @param array $rows
     * @return void
     */
    private function attachTags(array &$rows)
    {
        if (empty($rows)) {
            return;
        }
        $this->model('MispAttribute')->attachTagsToAttributes(
            $rows,
            array('includeAllTags' => true)
        );
    }

    /**
     * `Event.Orgc` for every event the rows sit on, in one call.
     *
     * §14.4's one performance commitment: never one call per event. A
     * value in seven events is one `fetchSimpleEvents`, not seven
     * `fetchEvent`s — the cheap path §14.10 found that §8.2 had costed
     * as a full event graph per event.
     *
     * A nested `contain => ['Event' => ['Orgc']]` would have folded this
     * into the row fetch for nothing, since both are `belongsTo` and
     * become joins. It is a separate call so that the event's ACL is
     * checked by the model that owns events:
     * `Event::createEventConditions` is the same event predicate
     * `MispAttribute::buildConditions` embeds, so it can never be
     * stricter and the drop below can never fire — which is the point of
     * having it, since a divergence would then cost a row rather than
     * leak one.
     *
     * The organisation is rebuilt from `Event.orgc_id` rather than read
     * out of the contained record, because `fetchSimpleEvents` fixes its
     * own `contain` at `Orgc.name` and the facet token is the id.
     *
     * @param array $user
     * @param array $rows
     * @return array Rows whose event this viewer may open
     */
    private function attachCreatorOrgs(array $user, array $rows)
    {
        if (empty($rows)) {
            return $rows;
        }
        $eventIds = array();
        foreach ($rows as $row) {
            $eventIds[$row['Event']['id']] = true;
        }
        $events = $this->model('Event')->fetchSimpleEvents(
            $user,
            array('conditions' => array(
                'Event.id' => array_keys($eventIds),
            )),
            true
        );
        $orgs = array();
        foreach ($events as $event) {
            $orgs[$event['Event']['id']] = array(
                'id' => $event['Event']['orgc_id'],
                'name' => isset($event['Orgc']['name'])
                    ? $event['Orgc']['name']
                    : __('Unknown organisation'),
            );
        }
        $kept = array();
        foreach ($rows as $row) {
            $eventId = $row['Event']['id'];
            if (!isset($orgs[$eventId])) {
                continue;
            }
            $row['Event']['Orgc'] = $orgs[$eventId];
            $kept[] = $row;
        }
        return $kept;
    }

    /**
     * Who can actually see each occurrence.
     *
     * An attribute's own `distribution` column is level 5 — *inherit* —
     * for almost every row on a real instance, so reporting it tells the
     * reader nothing: the level that matters is the conjunction of the
     * attribute's, its object's and its event's, which is the same rule
     * `MispAttribute::buildConditions` enforces to decide whether the row
     * is visible at all. `ValueStatsTool::effectiveDistribution()` owns
     * the resolution; this stamps the answer on the row so the table's
     * badge and the rail's facet cannot resolve it differently.
     *
     * Every level it needs is already on the row — the attribute's, the
     * object's from the `contain`, the event's from the same — so this
     * costs no query. Sharing group *names* do cost one, and only when
     * some row resolves to level 4.
     *
     * `SharingGroup::fetchAllAuthorised($user, 'name')` and not a plain
     * find: it returns only the groups this viewer is authorised for, so
     * a group name cannot be read off a row whose event the reader
     * happens to own. A level-4 row whose group does not resolve keeps
     * its badge and loses only the name.
     *
     * @param array $user
     * @param array $rows
     * @return void
     */
    private function attachEffectiveDistribution(array $user, array &$rows)
    {
        if (empty($rows)) {
            return;
        }
        $names = array();
        foreach ($rows as $row) {
            $levels = array(
                (int)$row['Attribute']['distribution'],
                (int)($row['Event']['distribution'] ?? -1),
                empty($row['Object']['id'])
                    ? -1
                    : (int)$row['Object']['distribution'],
            );
            if (in_array(4, $levels, true)) {
                $names = $this->model('SharingGroup')
                    ->fetchAllAuthorised($user, 'name');
                break;
            }
        }
        foreach ($rows as &$row) {
            $row['effective_distribution'] =
                ValueStatsTool::effectiveDistribution($row, $names);
        }
        unset($row);
    }

    /**
     * How many pending shadow attributes propose a change to each row.
     *
     * A tier-2 aggregate over an already-ACL'd id set, and the written
     * reason §14.4 asks for: the answer is a count per row, and a
     * proposal row carries a value, a comment, a type and a category
     * this panel never renders.
     *
     * The id set comes from the row fetch rather than from a second
     * resolution of the value, so permissions were settled before the
     * aggregate ran and the two cannot drift.
     * `ShadowAttribute::buildConditions()` mirrors the attribute
     * visibility model — a proposal is visible to whoever may see the
     * attribute it proposes against — so it is already satisfied by the
     * set this receives and re-applying it would only re-join `events`.
     *
     * @param array $rows
     * @return void
     */
    private function attachProposalCounts(array &$rows)
    {
        if (empty($rows)) {
            return;
        }
        $ids = array();
        foreach ($rows as $row) {
            $ids[] = $row['Attribute']['id'];
        }
        $counts = $this->model('ShadowAttribute')->find('all', array(
            'fields' => array(
                'ShadowAttribute.old_id',
                'COUNT(*) AS proposal_count',
            ),
            'conditions' => array(
                'ShadowAttribute.old_id' => $ids,
                // A soft-deleted proposal is a withdrawn one, and the
                // badge means "somebody is waiting on you".
                'ShadowAttribute.deleted' => 0,
            ),
            'group' => array('ShadowAttribute.old_id'),
            'recursive' => -1,
        ));
        $byAttribute = array();
        foreach ($counts as $count) {
            $byAttribute[$count['ShadowAttribute']['old_id']] =
                (int)$count[0]['proposal_count'];
        }
        foreach ($rows as &$row) {
            $row['proposal_count'] = isset(
                $byAttribute[$row['Attribute']['id']]
            )
                ? $byAttribute[$row['Attribute']['id']]
                : 0;
        }
        unset($row);
    }

    /*
     * ==================================================================
     * The Relationships tab.
     *
     * Three notions of *related*, three sources, and none of them is
     * the correlation engine — which is this phase's headline finding
     * and is argued in `24-relationships.md` §3. In short: a
     * `default_correlations` row links two attributes carrying the
     * *same* value, so for one value the engine returns other
     * occurrences of it (the Occurrences tab) and its CIDR/ssdeep
     * partners (section two). It never returns a different value, so
     * section one — *values that appear in the same events* — has to be
     * an event join, and the settings card is where the engine's own
     * state is reported instead.
     * ==================================================================
     */

    /**
     * Section one: everything else in the events this value sits in,
     * plus the object siblings above it.
     *
     * Nine queries, none of them per-event or per-occurrence:
     *
     *   1. the value's events, newest first, capped     `Value`
     *   2. how big each of those events is              `MispAttribute`
     *   3. the neighbour rows inside the budget         `Value`
     *   4. their attribute tags (the hasMany of 3)
     *   5. the tag records behind those                 `MispAttribute`
     *   6. the objects this value sits in, capped       `Value`
     *   7. the sibling rows inside those objects        `Value`
     *   8. creator organisation names                   `Organisation`
     *   9. event metadata for the event roll-up         `Event`
     *
     * plus one `SharingGroup::authorizedIds` inside `buildConditions`
     * for a non-site-admin, and one more for sharing-group names when
     * some neighbour resolves to distribution 4.
     *
     * @param array $user
     * @param string $value
     * @param array $options Reserved; `types` reaches `Value`
     * @return array
     */
    public function forRelationCooccurrence(array $user, $value,
        array $options = array()
    ) {
        $context = $this->cooccurrenceContext($user, $value, $options);
        return array(
            'value' => $value,
            'relationships' => array(
                'summary' => $this->relationSummary(
                    $user,
                    $value,
                    $options,
                    array('cooccurrence' => $context['co'])
                ),
                'cooccurrence' => $context['co'],
                'settings' => $this->relationSettings($user, $value),
            ),
        );
    }

    /**
     * Section two: values that are close to this one without being it.
     *
     * Two queries at most, and neither of them reads the correlation
     * table — there is nothing in it to read. `Correlation` records no
     * provenance, so a CIDR containment row is indistinguishable from
     * an exact match once written; both engines are therefore
     * re-derived here from the same inputs the engine itself uses.
     *
     * @param array $user
     * @param string $value
     * @param array $options Reserved; `types` reaches `Value`
     * @return array
     */
    public function forRelationNearMatch(array $user, $value,
        array $options = array()
    ) {
        $types = $this->model('Value')->typesFor($user, $value, $options);
        return array(
            'value' => $value,
            'types' => $types,
            'relationships' => array(
                'near' => $this->nearMatches($user, $value, $types),
            ),
        );
    }

    /**
     * Section three: relationships somebody wrote down on purpose.
     *
     * @param array $user
     * @param string $value
     * @param array $options Reserved; `types` reaches `Value`
     * @return array
     */
    public function forRelationAsserted(array $user, $value,
        array $options = array()
    ) {
        return array(
            'value' => $value,
            'relationships' => array(
                'asserted' => $this->assertedClaims($user, $value,
                    $options),
            ),
        );
    }

    /**
     * The rail's neighbourhood sketch.
     *
     * **The expensive rail card**, and knowingly so: the sketch draws
     * one region per notion and its sub-line states the tab's own
     * arithmetic, so it needs all three sections' numbers and pays for
     * all three. A tab-level context shared across the five requests
     * would remove the repeat; §14.11 puts caching out of scope and
     * `24-relationships.md` §11.1 records the cost.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array
     */
    public function forRelationGraph(array $user, $value,
        array $options = array()
    ) {
        $context = $this->cooccurrenceContext($user, $value, $options);
        $types = $this->model('Value')->typesFor($user, $value, $options);
        $near = $this->nearMatches($user, $value, $types);
        $asserted = $this->assertedClaims($user, $value, $options);
        $summary = $this->relationSummary($user, $value, $options, array(
            'cooccurrence' => $context['co'],
            'near' => $near,
            'asserted' => $asserted,
        ));
        return array(
            'value' => $value,
            'relationships' => array(
                'summary' => $summary,
                'graph' => $this->graphFor($context['co'], $near,
                    $asserted, $value),
            ),
        );
    }

    /**
     * The rail's second card: what MISP is configured to count, and
     * where this value stands against it.
     *
     * The one panel on the tab that still says something true about a
     * value with nothing at all — and, now that section one is not
     * engine output, the only panel that reads the correlation engine's
     * state at all.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array
     */
    public function forRelationSettings(array $user, $value,
        array $options = array()
    ) {
        $context = $this->cooccurrenceContext($user, $value, $options);
        $types = $this->model('Value')->typesFor($user, $value, $options);
        $near = $this->nearMatches($user, $value, $types);
        $asserted = $this->assertedClaims($user, $value, $options);
        return array(
            'value' => $value,
            'relationships' => array(
                'summary' => $this->relationSummary($user, $value,
                    $options, array(
                        'cooccurrence' => $context['co'],
                        'near' => $near,
                        'asserted' => $asserted,
                    )),
                'cooccurrence' => $context['co'],
                'settings' => $this->relationSettings($user, $value),
            ),
        );
    }

    /**
     * The neighbourhood, folded — at most once per request.
     *
     * **The scope is chosen before anything is read**, which is the
     * whole design of this section. Events come newest-first; an event
     * larger than `RELATION_EVENT_SIZE_CAP` is dropped outright,
     * because co-occurrence inside an event that size is a statement
     * about the event and not about this value; then events are taken
     * until `RELATION_SCAN_BUDGET` rows would be exceeded. Whatever
     * survives is read *completely*, so every count the panel prints is
     * exact over a scope the panel also prints.
     *
     * Reading the events first and choosing second is what makes this
     * affordable: the size query is index-only and cost 61 ms over
     * `8.8.8.8`'s 19 events, against 4.8 seconds for the neighbour scan
     * it then avoided.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array `co` and the scan's own numbers
     */
    private function cooccurrenceContext(array $user, $value,
        array $options = array()
    ) {
        if ($this->cooccurrence !== null) {
            return $this->cooccurrence;
        }
        $valueModel = $this->model('Value');
        $events = $valueModel->occurrenceEventsFor(
            $user,
            $value,
            array_merge($options, array(
                'limit' => self::RELATION_EVENT_CAP,
            ))
        );
        $sizes = $this->eventSizes(array_keys($events));

        $picked = array();
        $spent = 0;
        $oversized = 0;
        $unread = 0;
        foreach ($events as $eventId => $meta) {
            $size = isset($sizes[$eventId]) ? $sizes[$eventId] : 0;
            if ($size > self::RELATION_EVENT_SIZE_CAP) {
                $oversized++;
                continue;
            }
            if ($spent + $size > self::RELATION_SCAN_BUDGET) {
                $unread++;
                continue;
            }
            $picked[] = $eventId;
            $spent += $size;
        }

        $rows = $valueModel->neighbourRowsFor(
            $user,
            $value,
            array('events' => $picked),
            array_merge($options, array('tags' => true))
        );
        $this->attachTags($rows);
        $sharingGroups = $this->sharingGroupNames($user, $rows);
        $orgs = $this->organisationNames($rows);

        $co = ValueRelationTool::cooccurrence($rows, array(
            'orgs' => $orgs,
            'events' => $this->eventMetadata($user, $picked),
            'sharing_groups' => $sharingGroups,
            'row_cap' => self::RELATION_ROW_CAP,
            'page_size' => self::RELATION_PAGE_SIZE,
        ));
        $co['siblings'] = $this->siblingSection($user, $value, $options,
            $orgs);
        $co['scan'] = array(
            'events_read' => count($picked),
            'events_seen' => count($events),
            'events_oversized' => $oversized,
            'events_unread' => $unread,
            'event_cap' => self::RELATION_EVENT_CAP,
            'size_cap' => self::RELATION_EVENT_SIZE_CAP,
            'budget' => self::RELATION_SCAN_BUDGET,
            'rows_read' => count($rows),
            'row_cap' => self::RELATION_ROW_CAP,
        );
        /*
         * Not "the engine stored nothing" — the opposite claim. Every
         * event this value appears in is too large to read for
         * co-occurrence, so there is a neighbourhood and it is not
         * being shown, which is what `.vp-suppressed` says and what an
         * empty state would get exactly backwards.
         */
        $co['suppressed'] = empty($picked) && $oversized > 0;

        $this->cooccurrence = array('co' => $co);
        return $this->cooccurrence;
    }

    /**
     * How many attributes each candidate event holds.
     *
     * Tier 2, and the written reason is that this is the query that
     * makes the rest of the section affordable: the answer is one
     * number per event, it is index-only on `attributes.event_id`, and
     * every row it counts is a row the neighbour scan then does not
     * have to read. No ACL is applied and none is needed — the number
     * never reaches the page. It decides which events are worth
     * reading, and the reading itself goes through
     * `buildConditions($user)` like everything else here.
     *
     * @param array $eventIds
     * @return array event id => attribute count
     */
    private function eventSizes(array $eventIds)
    {
        if (empty($eventIds)) {
            return array();
        }
        $rows = $this->model('MispAttribute')->find('all', array(
            'fields' => array(
                'Attribute.event_id',
                'COUNT(*) AS attributes',
            ),
            'conditions' => array('Attribute.event_id' => $eventIds),
            'recursive' => -1,
            'group' => array('Attribute.event_id'),
        ));
        $sizes = array();
        foreach ($rows as $row) {
            $sizes[(int)$row['Attribute']['event_id']] =
                (int)$row[0]['attributes'];
        }
        return $sizes;
    }

    /**
     * The event roll-up's own columns — info, date, creator, tags.
     *
     * One `fetchSimpleEvents` for all N, per §14.4's batching rule, and
     * one tag fetch beside it. The neighbour rows carry `Event.info`
     * already; this exists for the events that survived the scan but
     * whose only neighbour rows were dropped, so the roll-up cannot
     * name an event it has no title for.
     *
     * @param array $user
     * @param array $eventIds
     * @return array event id => info, date, orgc_id, distribution, tags
     */
    private function eventMetadata(array $user, array $eventIds)
    {
        if (empty($eventIds)) {
            return array();
        }
        $events = $this->model('Event')->fetchSimpleEvents(
            $user,
            array('conditions' => array('Event.id' => $eventIds)),
            true
        );
        $meta = array();
        foreach ($events as $event) {
            $meta[(int)$event['Event']['id']] = array(
                'info' => $event['Event']['info'],
                'date' => $event['Event']['date'],
                'orgc_id' => (int)$event['Event']['orgc_id'],
                'distribution' => (int)$event['Event']['distribution'],
                'sharing_group_id' =>
                    (int)$event['Event']['sharing_group_id'],
                'tags' => array(),
            );
        }
        $tags = $this->model('EventTag')->find('all', array(
            'conditions' => array(
                'EventTag.event_id' => array_keys($meta),
            ),
            'recursive' => -1,
            'contain' => array('Tag' => array('fields' => array(
                'Tag.id', 'Tag.name', 'Tag.colour', 'Tag.is_galaxy',
            ))),
        ));
        foreach ($tags as $tag) {
            $eventId = (int)$tag['EventTag']['event_id'];
            if (!isset($meta[$eventId]) || empty($tag['Tag'])
                || !empty($tag['Tag']['is_galaxy'])
            ) {
                continue;
            }
            $meta[$eventId]['tags'][] = $tag['Tag'];
        }
        return $meta;
    }

    /**
     * Creator organisation names for the rows' events.
     *
     * A plain `Organisation` list rather than an ACL'd fetcher, and it
     * is not a tier-3 bypass: `buildConditions($user)` already decided
     * which rows this viewer may see, and every one of those rows
     * carries its creator organisation on the event page and in every
     * attribute index in MISP. The only thing resolved here is an id
     * into the name beside it.
     *
     * @param array $rows
     * @return array org id => name
     */
    private function organisationNames(array $rows)
    {
        $ids = array();
        foreach ($rows as $row) {
            if (!empty($row['Event']['orgc_id'])) {
                $ids[(int)$row['Event']['orgc_id']] = true;
            }
        }
        if (empty($ids)) {
            return array();
        }
        return $this->model('Organisation')->find('list', array(
            'recursive' => -1,
            'fields' => array('Organisation.id', 'Organisation.name'),
            'conditions' => array(
                'Organisation.id' => array_keys($ids),
            ),
        ));
    }

    /**
     * Sharing-group names, and only when a row could need one.
     *
     * `fetchAllAuthorised` rather than a plain find, for the reason
     * `attachEffectiveDistribution` gives: a group name must not be
     * readable off a row whose event the reader happens to own.
     *
     * @param array $user
     * @param array $rows
     * @return array
     */
    private function sharingGroupNames(array $user, array $rows)
    {
        foreach ($rows as $row) {
            $levels = array(
                (int)$row['Attribute']['distribution'],
                (int)($row['Event']['distribution'] ?? -1),
                empty($row['Object']['id'])
                    ? -1
                    : (int)$row['Object']['distribution'],
            );
            if (in_array(4, $levels, true)) {
                return $this->model('SharingGroup')
                    ->fetchAllAuthorised($user, 'name');
            }
        }
        return array();
    }

    /**
     * The object siblings — the same object, other relations.
     *
     * Not a correlation and not the engine's to suppress: a join on
     * `Attribute.object_id` over occurrences this viewer can already
     * see. It is the reason a value whose events are all too large to
     * scan still has something on this tab, and the reason the
     * suppressed band above it does not short-circuit the panel.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @param array $orgs Names already resolved for the neighbour rows
     * @return array
     */
    private function siblingSection(array $user, $value, array $options,
        array $orgs
    ) {
        $valueModel = $this->model('Value');
        $objects = $valueModel->occurrenceObjectIdsFor(
            $user,
            $value,
            array_merge($options, array(
                'limit' => self::SIBLING_OBJECT_CAP,
            ))
        );
        $rows = $valueModel->neighbourRowsFor(
            $user,
            $value,
            array('objects' => array_keys($objects)),
            $options
        );
        $missing = $this->organisationNames($rows);
        return ValueRelationTool::siblings($rows, array(
            'orgs' => $orgs + $missing,
            'in_objects' => $this->objectFootprint($user, $value,
                $options, count($objects)),
            'cap' => self::SIBLING_OBJECT_CAP,
            'row_cap' => self::RELATION_ROW_CAP,
            'page_size' => self::RELATION_PAGE_SIZE,
        ));
    }

    /**
     * How many objects this value sits in altogether, so the sibling
     * section can say what its cap left out.
     *
     * Asked only when the cap actually bit. Below it the answer is the
     * number of objects already fetched, and a `COUNT(DISTINCT …)` over
     * 32,921 rows to learn a number we are holding would be the exact
     * mistake `occurrenceSummaryFor` was written to stop.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @param int $fetched
     * @return int
     */
    private function objectFootprint(array $user, $value, array $options,
        $fetched
    ) {
        if ($fetched < self::SIBLING_OBJECT_CAP) {
            return $fetched;
        }
        $attributes = $this->model('MispAttribute');
        $conditions = $attributes->buildConditions($user);
        $conditions['AND'][] = $this->model('Value')
            ->conditionsFor($value, $options);
        $conditions['AND'][] = array('Attribute.object_id >' => 0);
        $row = $attributes->find('first', array(
            'fields' => array(
                'COUNT(DISTINCT Attribute.object_id) AS objects',
            ),
            'conditions' => $conditions,
            'recursive' => -1,
            'contain' => array('Event', 'Object'),
        ));
        return empty($row[0]['objects'])
            ? $fetched
            : (int)$row[0]['objects'];
    }

    /**
     * The near-match engines and what each of them has to say.
     *
     * Three engines and, live, **four states rather than three**. The
     * brief has *active*, *not applicable* and *no engine in MISP*;
     * real data adds a fourth that is none of them — an engine MISP
     * ships, that applies to this value, and that cannot run because
     * the PHP extension behind it is not loaded. Saying *not
     * applicable* there would be a lie about the value; saying *no
     * engine* would be a lie about MISP.
     *
     * @param array $user
     * @param string $value
     * @param array $types From `Value::typesFor`
     * @return array
     */
    private function nearMatches(array $user, $value, array $types)
    {
        if (empty($types)) {
            // No occurrence this viewer can see, so no type for an
            // engine to accept or decline. The panel says exactly that
            // rather than declining on the reader's behalf.
            return array(
                'matches' => 0,
                'engines_active' => 0,
                'engines_idle' => 0,
                'threshold' => $this->ssdeepThreshold(),
                'engines' => array(),
            );
        }
        $names = array();
        foreach ($types as $type) {
            $names[$type['type']] = true;
        }
        $engines = array(
            $this->cidrEngine($user, $value, $names),
            $this->ssdeepEngine($user, $value, $names),
            array('id' => 'tld', 'state' => 'absent', 'rows' => array()),
        );
        $matches = 0;
        $active = 0;
        foreach ($engines as $engine) {
            $matches += count($engine['rows']);
            if ($engine['state'] === 'active') {
                $active++;
            }
        }
        return array(
            'matches' => $matches,
            'engines_active' => $active,
            'engines_idle' => count($engines) - $active,
            'threshold' => $this->ssdeepThreshold(),
            'engines' => $engines,
        );
    }

    /**
     * CIDR containment, re-derived from the same list the engine walks.
     *
     * `Correlation::getCidrList()` is the network-block set MISP itself
     * tests against — Redis-cached, 44 entries on the verification
     * instance — so the containment answer here is the engine's own
     * rather than an approximation of it. The blocks are then fetched
     * as attributes so the rows carry an event, a reporter and a
     * distribution the viewer may actually see.
     *
     * Two queries at most: the list (usually Redis, one query on a
     * cold cache) and one fetch for whichever blocks contained us.
     *
     * @param array $user
     * @param string $value
     * @param array $types Type name => true
     * @return array
     */
    private function cidrEngine(array $user, $value, array $types)
    {
        $ipTypes = array('ip-src', 'ip-dst', 'ip-src|port', 'ip-dst|port');
        if (empty(array_intersect($ipTypes, array_keys($types)))) {
            return array(
                'id' => 'cidr',
                'state' => 'not_applicable',
                'rows' => array(),
            );
        }
        $blocks = ValueRelationTool::containingBlocks(
            $value,
            $this->model('Correlation')->getCidrList()
        );
        if (empty($blocks)) {
            return array(
                'id' => 'cidr',
                'state' => 'active',
                'rows' => array(),
            );
        }
        $width = strpos($value, ':') === false ? 32 : 128;
        $byBlock = array();
        foreach ($blocks as $block) {
            $byBlock[$block['block']] = $block['prefix'];
        }
        $rows = $this->model('Value')->occurrencesForAny(
            $user,
            array_keys($byBlock),
            array('types' => array('ip-src', 'ip-dst'))
        );
        $out = array();
        $seen = array();
        foreach ($this->decorate($user, $rows) as $row) {
            $block = $row['Attribute']['value'];
            // One row per block. A `/8` is an attribute in dozens of
            // events and the panel names a containment, not a report.
            if (!isset($byBlock[$block]) || isset($seen[$block])) {
                continue;
            }
            $seen[$block] = true;
            $out[] = $this->nearRow(
                $row,
                $block,
                $byBlock[$block],
                ValueRelationTool::addressSpace($byBlock[$block], $width),
                $width
            );
        }
        usort($out, function ($a, $b) {
            return $b['prefix'] - $a['prefix'];
        });
        return array('id' => 'cidr', 'state' => 'active', 'rows' => $out);
    }

    /**
     * A near-match row, carrying the reporter and the audience of the
     * *other* value rather than of ours.
     *
     * @param array $row
     * @param string $label
     * @param int $closeness Prefix length, or an ssdeep score
     * @param string|null $addresses
     * @param int $width Address width, or 100 for a percentage
     * @return array
     */
    private function nearRow(array $row, $label, $closeness, $addresses,
        $width
    ) {
        return array(
            'block' => $label,
            'prefix' => (int)$closeness,
            'addresses' => $addresses,
            'width' => $width,
            'event' => (int)$row['Attribute']['event_id'],
            'org' => $row['org'],
            'distribution' => $row['effective_distribution']['level'] === null
                ? 5
                : $row['effective_distribution']['level'],
        );
    }

    /**
     * Creator organisation and effective audience for a small row set.
     *
     * Two queries for the whole set rather than two per row, which is
     * §14.4's batching rule applied to the near-match section — a
     * section that can name a `/8` block held in a hundred events.
     *
     * @param array $user
     * @param array $rows
     * @return array
     */
    private function decorate(array $user, array $rows)
    {
        if (empty($rows)) {
            return $rows;
        }
        $orgs = $this->organisationNames($rows);
        $sharingGroups = $this->sharingGroupNames($user, $rows);
        foreach ($rows as &$row) {
            $orgId = (int)($row['Event']['orgc_id'] ?? 0);
            $row['org'] = isset($orgs[$orgId])
                ? $orgs[$orgId]
                : __('Unknown organisation');
            $row['effective_distribution'] =
                ValueStatsTool::effectiveDistribution($row, $sharingGroups);
        }
        unset($row);
        return $rows;
    }

    /**
     * ssdeep fuzzy similarity.
     *
     * Not applicable unless the value is itself an `ssdeep` attribute,
     * which is the state the brief designs the block around. When it
     * *is* one, the state depends on something the brief could not know
     * about: `ssdeep_fuzzy_compare()` is a PHP extension MISP does not
     * require, and without it the engine is present and inert.
     *
     * The comparison is made here rather than read, because MISP keeps
     * neither the score nor which engine wrote a correlation row —
     * `Correlation::ssdeepCorrelation` computes the number only to test
     * the threshold and then throws it away.
     *
     * @param array $user
     * @param string $value
     * @param array $types
     * @return array
     */
    private function ssdeepEngine(array $user, $value, array $types)
    {
        if (empty($types['ssdeep'])) {
            return array(
                'id' => 'ssdeep',
                'state' => 'not_applicable',
                'rows' => array(),
            );
        }
        if (!function_exists('ssdeep_fuzzy_compare')) {
            return array(
                'id' => 'ssdeep',
                'state' => 'unavailable',
                'rows' => array(),
            );
        }
        $threshold = $this->ssdeepThreshold();
        $candidates = $this->model('Value')->occurrencesOfType(
            $user,
            'ssdeep',
            $value,
            self::RELATION_ROW_CAP
        );
        $rows = array();
        foreach ($this->decorate($user, $candidates) as $candidate) {
            $score = @ssdeep_fuzzy_compare(
                $value,
                $candidate['Attribute']['value']
            );
            if ($score === false || $score < $threshold) {
                continue;
            }
            $rows[] = $this->nearRow(
                $candidate,
                $candidate['Attribute']['value'],
                $score,
                null,
                100
            );
        }
        usort($rows, function ($a, $b) {
            return $b['prefix'] - $a['prefix'];
        });
        return array(
            'id' => 'ssdeep',
            'state' => 'active',
            'rows' => $rows,
        );
    }

    /**
     * Analyst-asserted relationships on this value's occurrences.
     *
     * `Relationship` hangs off an `object_uuid`, so the list is the
     * union over the value's occurrence UUIDs in both directions,
     * de-duplicated by relationship UUID. `AnalystData::buildConditions`
     * is the ACL, which is the same predicate every other analyst-data
     * reader in MISP uses.
     *
     * **A claim has no text**, and that is a schema fact rather than a
     * decision taken here: `relationships` carries `relationship_type`,
     * the two endpoints, an author, a date and a distribution, and no
     * prose column at all — unlike `notes.note` and `opinions.comment`
     * beside it. Prose about a relationship is a Note attached to the
     * relationship, which is another fetch per claim and is deferred.
     * `24-relationships.md` §7.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array
     */
    private function assertedClaims(array $user, $value,
        array $options = array()
    ) {
        $occurrences = $this->model('Value')->occurrenceUuidsFor(
            $user,
            $value,
            array_merge($options, array(
                'limit' => self::CLAIM_OCCURRENCE_CAP,
                'order' => self::OCCURRENCE_ORDER,
            ))
        );
        $claims = array();
        $orgs = array();
        if (!empty($occurrences)) {
            $uuids = array_keys($occurrences);
            $relationships = $this->model('Relationship');
            $conditions = $relationships->buildConditions($user);
            $conditions['AND'][] = array('OR' => array(
                array(
                    'Relationship.object_type' => 'Attribute',
                    'Relationship.object_uuid' => $uuids,
                ),
                array(
                    'Relationship.related_object_type' => 'Attribute',
                    'Relationship.related_object_uuid' => $uuids,
                ),
            ));
            /*
             * `Org` and `Orgc` are contained rather than left out, and
             * it is not decoration: `AnalystData::rearrangeOrganisation`
             * runs in `afterFind` and issues **one `Organisation` find
             * per row for each of them** when they are absent. Six
             * claims is twelve queries nobody asked for. Contained,
             * it is two joins.
             */
            $rows = $relationships->find('all', array(
                'conditions' => $conditions,
                'recursive' => -1,
                'contain' => array('Org', 'Orgc'),
                'order' => array('Relationship.modified DESC'),
            ));
            foreach ($rows as $row) {
                $claim = $this->claimFrom($user, $row, $occurrences);
                if ($claim === null) {
                    continue;
                }
                $claims[$row['Relationship']['uuid']] = $claim;
                $orgs[$claim['org']] = true;
            }
        }
        return array(
            'total' => count($claims),
            'orgs' => count($orgs),
            // §14.6: no count of claims the reader may not see.
            'hidden' => 0,
            'occurrences' => count($occurrences),
            'capped' => count($occurrences) >= self::CLAIM_OCCURRENCE_CAP,
            /*
             * Not "these claims happen to have no text" — no claim on
             * any MISP instance has any, because the column does not
             * exist. The panel says so once, at the foot.
             */
            'prose_absent' => !empty($claims),
            'claims' => array_values($claims),
        );
    }

    /**
     * One `Relationship` row, read from whichever end this value is on.
     *
     * The direction is not a column: a row is *outbound* when one of
     * this value's occurrences is its source and *inbound* when the
     * occurrence is what somebody else pointed at. Reading it off the
     * two endpoint columns is the only way to know, and it is also what
     * decides which end is the interesting one to name.
     *
     * @param array $user
     * @param array $row
     * @param array $occurrences uuid => id, event_id, type
     * @return array|null
     */
    private function claimFrom(array $user, array $row,
        array $occurrences
    ) {
        $relationship = $row['Relationship'];
        $outbound = $relationship['object_type'] === 'Attribute'
            && isset($occurrences[$relationship['object_uuid']]);
        if ($outbound) {
            $kind = $relationship['related_object_type'];
            $uuid = $relationship['related_object_uuid'];
            /*
             * `Relationship::afterFind` has already resolved the far
             * end — one ACL'd fetch per row, whether anybody wanted it
             * or not — so reading it here costs nothing extra. The near
             * end of an inbound claim is not resolved by anything, and
             * is asked for below.
             */
            $target = isset($relationship['related_object'])
                ? $relationship['related_object']
                : array();
        } elseif (isset($occurrences[$relationship['related_object_uuid']])) {
            $kind = $relationship['object_type'];
            $uuid = $relationship['object_uuid'];
            $target = $this->model('Relationship')->getRelatedElement(
                $user,
                $kind,
                $uuid
            );
        } else {
            return null;
        }
        return array(
            'relationship_type' => empty($relationship['relationship_type'])
                ? __('related-to')
                : $relationship['relationship_type'],
            'direction' => $outbound ? 'outbound' : 'inbound',
            'target' => array(
                'kind' => $kind,
                'id' => $uuid,
                'label' => self::claimLabel($kind, $uuid, $target),
            ),
            /*
             * There is no prose to render. Left as an empty string
             * rather than a placeholder sentence, so the block draws
             * nothing where the fixture drew a paragraph and the panel
             * explains the absence once, at the foot, instead of on
             * every claim.
             */
            'text' => '',
            /*
             * Nested inside the row, not beside it.
             * `AnalystData::rearrangeOrganisation` moves a contained
             * `Orgc` under the record and unsets the top-level key, so
             * the obvious read finds nothing and every claim silently
             * reports *Unknown organisation*.
             */
            'org' => empty($relationship['Orgc']['name'])
                ? __('Unknown organisation')
                : $relationship['Orgc']['name'],
            'date' => substr($relationship['modified'], 0, 10),
            'distribution' => (int)$relationship['distribution'],
        );
    }

    /**
     * What to call the other end of a claim.
     *
     * **A target that does not resolve keeps its UUID**, and that is
     * not a fallback nobody will hit: two of the seven attribute-facing
     * relationships on the verification instance point at attribute
     * UUIDs that no longer exist, so `getRelatedElement` returns an
     * empty array for them. A claim about something deleted is still a
     * claim somebody made, and dropping the row would hide it; naming
     * it by UUID says what is known.
     *
     * `GalaxyCluster` never resolves either, and for a different
     * reason: `Relationship::getRelatedElement` handles Event,
     * Attribute, Object, Note, Opinion and Relationship, and stops
     * there — while `AnalystData::valid_targets` allows six more.
     *
     * @param string $kind
     * @param string $uuid
     * @param array $target As getRelatedElement returns it
     * @return string
     */
    private static function claimLabel($kind, $uuid, array $target)
    {
        if (!empty($target['Event']['info'])) {
            return sprintf(
                '#%s %s',
                $target['Event']['id'],
                $target['Event']['info']
            );
        }
        if (!empty($target['Attribute']['value'])) {
            return sprintf(
                '%s · %s',
                $target['Attribute']['type'],
                $target['Attribute']['value']
            );
        }
        if (!empty($target['Object']['name'])) {
            return sprintf(
                '%s · #%s',
                $target['Object']['name'],
                $target['Object']['id']
            );
        }
        return $uuid;
    }

    /**
     * The engine settings this tab depends on, and where this value
     * stands against each of them.
     *
     * @param array $user
     * @param string $value
     * @return array
     */
    private function relationSettings(array $user, $value)
    {
        return array(
            'correlation_limit' => (int)$this
                ->model('OverCorrelatingValue')->getLimit(),
            'ssdeep_threshold' => $this->ssdeepThreshold(),
            'excluded' => $this->model('Correlation')
                ->isValueExcluded($value),
            'over_correlating' => $this
                ->model('OverCorrelatingValue')->isBlocked($value),
        );
    }

    /**
     * The tab's arithmetic, over whichever sections the caller has
     * already built.
     *
     * `correlations` is co-occurrence plus near-match and never
     * includes the claims — an analyst assertion is a `Relationship`
     * row and not a correlation, which is the distinction the rail
     * card exists to state.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @param array $parts Any of `cooccurrence`, `near`, `asserted`
     * @return array
     */
    private function relationSummary(array $user, $value, array $options,
        array $parts
    ) {
        $cooccurrence = isset($parts['cooccurrence'])
            ? (int)$parts['cooccurrence']['distinct_values']
            : 0;
        $near = isset($parts['near'])
            ? (int)$parts['near']['matches']
            : 0;
        $asserted = isset($parts['asserted'])
            ? (int)$parts['asserted']['total']
            : 0;
        return array(
            'correlations' => $cooccurrence + $near,
            'cooccurrence' => $cooccurrence,
            'near' => $near,
            'asserted' => $asserted,
            /*
             * The viewer's own occurrence count and not
             * `over_correlating_values.occurrence`. That column is
             * instance-wide, so printing it would state a number about
             * rows the reader may not see — §14.6 — and it is 0 on
             * every one of the 1,622 rows the verification instance
             * holds anyway, because it is filled by a separate job.
             */
            'recorded' => $this->summaryFor($user, $value,
                $options)['occurrences'],
        );
    }

    /**
     * The neighbourhood as a real node/edge feed.
     *
     * `03-relationships.md` §12 recorded that **no value-centred graph
     * feed exists** — `CorrelationGraphTool` expands events, not values
     * — and shipped a static SVG sketch with a disabled button rather
     * than a canvas that looked live and was not. This is that feed.
     * It is not the correlation engine's either; it is the three
     * sections of this tab, each contributing its own edge kind, which
     * is exactly the separation §5 of that document says the tab lives
     * or dies by:
     *
     *     co      solid, `--vp-rel-co`      shares an event
     *     near    dashed, `--vp-rel-near`   close without being equal
     *     human   arrowed, `--vp-rel-human` an analyst said so
     *
     * Every neighbour node carries the URL of *its own* Value Profile,
     * so the graph is a pivot rather than a picture — which is the one
     * thing a value-centred graph can do that an event-centred one
     * cannot.
     *
     * The three legacy `nodes` lists survive beside the feed. They are
     * what the static sketch draws, and the sketch is still the
     * fallback for a browser where the graph library did not load.
     *
     * @param array $co
     * @param array $near
     * @param array $asserted
     * @param string $value
     * @return array
     */
    private function graphFor(array $co, array $near, array $asserted,
        $value
    ) {
        $sketch = array('co' => array(), 'near' => array(),
            'human' => array());
        $nodes = array(array(
            'id' => 'value',
            'data' => array(
                'label' => $value,
                'kind' => 'value',
                'sub' => __('this value'),
            ),
        ));
        $edges = array();

        $rows = $co['rollups']['value']['rows'];
        foreach (array_slice($rows, 0, self::GRAPH_NODE_CAP) as $i => $row) {
            $id = 'co:' . $i;
            $nodes[] = array('id' => $id, 'data' => array(
                'label' => $row['value'],
                'kind' => 'co',
                'type' => $row['type'],
                'sub' => sprintf(
                    __n('%d shared event', '%d shared events',
                        $row['shared_events'], $row['shared_events']),
                    $row['shared_events']
                ),
                'href' => '/values/view/' . self::b64($row['value']),
            ));
            $edges[] = self::graphEdge('value', $id, 'co',
                (string)$row['shared_events']);
            if (count($sketch['co']) < 3) {
                $sketch['co'][] = $row['type'] === null
                    ? __('value')
                    : $row['type'];
            }
        }

        $index = 0;
        foreach ($near['engines'] as $engine) {
            foreach ($engine['rows'] as $row) {
                if ($index >= self::GRAPH_NODE_CAP) {
                    break 2;
                }
                $id = 'near:' . $index++;
                $nodes[] = array('id' => $id, 'data' => array(
                    'label' => $row['block'],
                    'kind' => 'near',
                    'type' => $engine['id'] === 'cidr'
                        ? 'network-block'
                        : $engine['id'],
                    'sub' => $engine['id'] === 'cidr'
                        ? '/' . $row['prefix']
                        : $row['prefix'] . '%',
                    'href' => '/values/view/' . self::b64($row['block']),
                ));
                $edges[] = self::graphEdge('value', $id, 'near',
                    $engine['id']);
                if (count($sketch['near']) < 3) {
                    $sketch['near'][] = $engine['id'] === 'cidr'
                        ? 'network-block'
                        : $engine['id'];
                }
            }
        }

        foreach (array_slice($asserted['claims'], 0, self::GRAPH_NODE_CAP)
            as $i => $claim
        ) {
            $id = 'human:' . $i;
            $nodes[] = array('id' => $id, 'data' => array(
                'label' => $claim['target']['label'],
                'kind' => 'human',
                'type' => $claim['target']['kind'],
                'sub' => $claim['org'],
            ));
            /*
             * The arrow points the way the claim was written. An
             * inbound claim is something else pointing at this value,
             * and drawing both the same way would erase the one thing
             * the direction chip in the panel exists to say.
             */
            $outbound = $claim['direction'] === 'outbound';
            $edges[] = self::graphEdge(
                $outbound ? 'value' : $id,
                $outbound ? $id : 'value',
                'human',
                $claim['relationship_type']
            );
            if (count($sketch['human']) < 3) {
                $sketch['human'][] = $claim['target']['kind'];
            }
        }

        return array(
            'edges' => count($edges),
            'nodes' => $sketch,
            'feed' => array('nodes' => $nodes, 'edges' => $edges),
        );
    }

    /**
     * One graph edge, styled where the shipped library actually reads
     * it: **on the edge itself**.
     *
     * `render.defaultEdgeStyle` with `dashed`, `markerEnd` and
     * `styleCb` callbacks is the documented way to do this and is what
     * pivotick 1.6 supports; the build in `app/webroot/js` predates it
     * and ignores the callback forms, drawing every edge in one colour
     * with one arrowhead.
     *
     * **The style is nested under `edge`.** The bundle reads
     * `this.style?.edge`, so a flat `style` object is silently ignored
     * — no error, no colour, and nothing to say which of the two it
     * was. Found by reading the bundle rather than the documentation,
     * which describes the newer shape.
     *
     * @param string $from
     * @param string $to
     * @param string $kind `co`, `near` or `human`
     * @param string $label
     * @return array
     */
    private static function graphEdge($from, $to, $kind, $label)
    {
        $ink = array(
            'co' => 'var(--vp-rel-co)',
            'near' => 'var(--vp-rel-near)',
            'human' => 'var(--vp-rel-human)',
        );
        return array(
            'from' => $from,
            'to' => $to,
            'data' => array('kind' => $kind, 'label' => $label),
            'style' => array('edge' => array(
                'strokeColor' => $ink[$kind],
                'strokeWidth' => $kind === 'human' ? 2.25 : 2,
                // The separation has to survive greyscale, so it is
                // carried by the stroke as well as by the hue.
                'dashed' => $kind === 'near',
                'animateDash' => false,
                'markerEnd' => $kind === 'human' ? 'arrow' : 'none',
            )),
        );
    }

    /**
     * The URL-safe encoding `ValuesController` decodes.
     *
     * Duplicated from the controller's private `encodeValue` rather
     * than shared, because sharing it would mean the model importing
     * the controller. Three characters of alphabet; if a third caller
     * appears it moves to `Value`.
     *
     * @param string $value
     * @return string
     */
    private static function b64($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @return int
     */
    private function ssdeepThreshold()
    {
        $threshold = Configure::read(
            'MISP.ssdeep_correlation_threshold'
        );
        return empty($threshold) ? 40 : (int)$threshold;
    }
}
