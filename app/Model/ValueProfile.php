<?php
App::uses('AppModel', 'Model');
App::uses('ValueStatsTool', 'Tools');
App::uses('ValueDecayTool', 'Tools');
App::uses('ValueRelationTool', 'Tools');
App::uses('RedisTool', 'Tools');
App::uses('ValueWarninglistTool', 'Tools');
App::uses('GalaxyCategory', 'Tools');
App::uses('DomainPermutationTool', 'Tools');

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

    /**
     * Candidate rows the ssdeep engine fetches before de-duplicating.
     *
     * A ceiling rather than a working limit. This instance holds 1,399
     * `ssdeep` attributes and 1,260 distinct values, so nothing here
     * binds and the engine compares the whole visible population; the
     * number exists so an instance with a hundred thousand hashes
     * fetches a bounded set rather than all of them. **When it does
     * bind the panel says so** — which is the half §4.2 found missing,
     * and the reason the old `RELATION_ROW_CAP` was the wrong constant
     * as well as the wrong size: a row cap bounds what is shown, and
     * this bounds what is compared, so it changes the verdict.
     */
    const SSDEEP_CANDIDATE_CAP = 10000;

    /**
     * Occurrences the typosquat engine fetches for its candidate set.
     *
     * A *fetch* cap, not a compare cap — the distinction §4.2 found
     * the ssdeep engine getting wrong. Every candidate is always
     * checked, because the check is one `IN` over the whole set; this
     * bounds how many occurrences of the hits come back, and the panel
     * reports it when it binds rather than quietly showing fewer
     * look-alikes than exist.
     */
    const TYPOSQUAT_FETCH_CAP = 200;

    /** Rows per page in the co-occurrence and sibling lists. */
    const RELATION_PAGE_SIZE = 8;

    /**
     * Named threats the neighbourhood card shows before folding.
     *
     * Measured over every value on the development instance: of the
     * ~93,000 that have any named threat nearby, 93% have one, two or
     * three, and the cap binds on 1.7% of them. It binds hard when it
     * does — one value reaches 154 distinct clusters and another 102 —
     * so the rest are held rather than dropped, and the card expands
     * in place instead of sending the reader anywhere.
     */
    const THREAT_ROW_CAP = 8;

    /**
     * Seconds the Relationships scan's reads are held in Redis.
     *
     * Narrowing the co-occurrence table re-requests the panel, and each
     * request would otherwise repeat the whole scan to fold the same
     * rows differently. Cached, a session of narrowing costs one scan.
     *
     * **Five minutes, because the panel says how old the read is and
     * offers to redo it.** Nothing invalidates this entry — no
     * event-change hook reaches here — so without those two the only
     * bound on a stale neighbourhood would be the clock, and this would
     * have to stay at a minute. It still does not go higher: the reader
     * has a cue for *I* just added something, and none at all for
     * *somebody else* did.
     */
    const RELATION_SCAN_TTL = 300;

    /**
     * The shape of what the caches above hold. **Bump it in the same
     * commit as any change to the arrays they store.**
     *
     * A cached payload outlives the code that wrote it, so for one TTL
     * after a deploy the templates are new and the arrays they read are
     * old. A panel that reads a key the old fold did not write then
     * fatals for five minutes on every value someone had opened —
     * observed while building B4, which added `tokens` to the sibling
     * rows. Versioning the key retires those payloads at the deploy
     * rather than at the clock, and costs one cold read.
     *
     * `00-contract.md` §14.4 carries the rule; this is the second thing
     * a key here must capture, after the permission scope.
     */
    const CACHE_SHAPE = 10;

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
     * Above this many sibling values the neighbourhood graph draws one
     * node per object template instead of one per value.
     *
     * **A legibility bound, not a transport one** (§23.3). The wire
     * would allow roughly 2,500 nodes — phase 22 measured 5.9 MB as a
     * fragment that does not arrive, and this tab's heaviest today is
     * 1.18 MB — but 2,500 nodes is an unreadable hairball that arrived
     * intact. Bounding here keeps the payload so far from the wire that
     * pivotick's eventual graph-coarsening is an enhancement rather
     * than something this design leans on.
     *
     * The expansion also requires the fold to have carried every
     * sibling it counted: `ValueRelationTool::siblings` caps its rows
     * at `RELATION_ROW_CAP`, and drawing a hundred of a hundred and
     * twenty would be the fraction §23.3 exists to remove. So the
     * effective bound today is the lower of the two, and raising this
     * one alone changes nothing.
     */
    const GRAPH_SIBLING_BOUND = 150;

    /**
     * Event nodes drawn before the rest roll into one.
     *
     * The event layer draws the events themselves and stops — it does
     * not expand into their attributes (§23.1), which is what keeps it
     * affordable on a value in two hundred events.
     */
    const GRAPH_EVENT_CAP = 40;

    /**
     * How many of this value's own occurrences the reference reader
     * offers as reference targets.
     *
     * `object_references.referenced_id` is indexed, so the cost here is
     * the `IN` list rather than the scan. The claims section caps its
     * own UUID set at `CLAIM_OCCURRENCE_CAP` for the same reason.
     */
    const REFERENCE_OCCURRENCE_CAP = 300;

    /** Reference rows read before the panel says the cut bit. */
    const REFERENCE_ROW_CAP = 200;

    /** Identifying values carried per far object in a reference row. */
    const REFERENCE_FACE_CAP = 4;

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
     * Characters of a target's own prose a claim's hover card carries.
     *
     * A galaxy cluster's description is the only free text that reaches
     * this section, and it runs to paragraphs. The card is a hover, not
     * a page — what it cannot hold is one click away on the target
     * itself, which the same card links to.
     */
    const CLAIM_PROSE_CAP = 180;

    /**
     * Remote events listed per external source.
     *
     * Measured over 4,798 cached values on a live instance: a median of
     * 2 remote events per hitting value, 4 at p95, 52 at the most
     * (`live/24-relationships.md` §17.4). Twenty-five covers everything
     * seen with room to spare, and a source past it says so — a cap is
     * not a permission, so that notice reads the same for every reader.
     */
    const EXTERNAL_EVENT_CAP = 25;

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

    /** The narrowing `$cooccurrence` was folded under. */
    private $cooccurrenceFilters = array();

    /**
     * The value the two memos above and below hold.
     *
     * A request serves one value, so this never changes inside the
     * application — but a memo that cannot say which value it holds
     * hands the wrong neighbourhood to the second caller in any loop,
     * silently and with no key to notice it by. A console shell walking
     * eight verification values is exactly that loop.
     *
     * @var string|null
     */
    private $memoValue = null;

    /**
     * @var array|null The object-reference read, once per request
     */
    private $references = null;

    /**
     * Drop every per-value memo the moment a different value is asked
     * for.
     *
     * @param string $value
     * @return void
     */
    private function forget($value)
    {
        if ($this->memoValue === $value) {
            return;
        }
        $this->memoValue = $value;
        $this->cooccurrence = null;
        $this->cooccurrenceFilters = array();
        $this->references = null;
        $this->summary = null;
    }

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
     * **Relationships gets a number that names its own unit.** The
     * fixture's badge was the *correlation* total, and nothing on the
     * live tab computes one: co-occurrence there is an event join
     * rather than correlation output (`24-relationships.md` §3), so the
     * old number was not merely stale, it counted something the tab no
     * longer claims. The join's own total — `distinct_values` — is
     * still refused here for the sightings reason: it needs the panel's
     * whole scan, up to 20,000 attribute rows and about a second on the
     * heaviest value on the instance, on every page load.
     *
     * What is affordable is the notion the tab was re-founded on
     * (`24-relationships.md` §26). **How many objects this value sits
     * in** is one indexed aggregate — 0.3 ms on `8.8.8.8`, 84 ms on
     * `0.0.0.0` — and it is the same number the sibling panel's census
     * and the graph's object layer already print, because
     * `Value::objectCountFor` carries the conditions of the call that
     * fetches those rows.
     *
     * **It rides the badge pill, not the parenthesised count**, and the
     * distinction is the whole of why this one can be told. `(15)` on a
     * tab called Relationships reads as *fifteen relationships*, which
     * is the claim that got the correlation badge removed; the pill
     * takes a label, so the unit travels with the number. The tab bar
     * inherits the contents strip's rule that seven notions have seven
     * units and none of them is a total.
     *
     * **Zero shows nothing at all**, which is what keeps this honest
     * rather than merely cheap. A value can sit in no object and still
     * have an analyst claim, a near-match or a remote hit — near-matches
     * alone cannot be priced at page-load cost — so a badge reading
     * *0 objects* would answer *is this tab worth opening* wrongly, in
     * the one direction that costs a reader something. An absent pill
     * therefore keeps the meaning it has today, *no number can be told
     * truly*, and adding a probe can only turn silence into a true
     * badge, never into a false one.
     *
     * **The warm-digest peek was the other candidate, and is refused.**
     * `relationDigest` is held in Redis per user and value, so a `GET`
     * here would hand over the exact join total for free once the tab
     * had been opened — `24-relationships.md` §15.1 item 1 built that
     * context. But a badge that appears only after the visit it exists
     * to inform has missed its one job, and an intermittent number
     * conflates *not read yet* with *nothing there*, which is the
     * conflation the contents strip's placeholder was built to prevent.
     *
     * @param array $user
     * @param string $value
     * @param array $counts The frame's counts, from the fixture
     * @return array The same, with the badges that can be told truly
     */
    public function forTabCounts(array $user, $value, array $counts)
    {
        $valueModel = $this->model('Value');
        $counts['occurrences'] = $valueModel
            ->occurrenceCountFor($user, $value);
        $counts['relationship_objects'] = $valueModel
            ->objectCountFor($user, $value);
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
        $this->forget($value);
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
        /*
         * Lifted out before anything else sees `$options`: the
         * narrowing describes rows the fold has already fetched, and
         * the query builders below take their keys from the same
         * array.
         */
        $filters = isset($options['filters'])
            ? (array)$options['filters']
            : array();
        unset($options['filters']);
        $context = $this->cooccurrenceContext($user, $value, $options,
            $filters);
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
     * Section five: the object joins that carry a pair of dates.
     *
     * Folded with the co-occurrence scan rather than queried again, so
     * this endpoint's cost is the scan's — usually a Redis read — and
     * never a second object join. `03-relationships.md` §23.5.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array
     */
    public function forRelationDated(array $user, $value,
        array $options = array()
    ) {
        unset($options['filters']);
        $context = $this->cooccurrenceContext($user, $value, $options);
        return array(
            'value' => $value,
            'relationships' => array(
                'dated' => $context['co']['dated'],
                'siblings' => array(
                    'in_objects' => $context['co']['siblings']['in_objects'],
                    'objects' => $context['co']['siblings']['objects'],
                    'cap' => $context['co']['siblings']['cap'],
                ),
                'suppressed' => !empty($context['co']['suppressed']),
                'read_at' => isset($context['co']['scan']['read_at'])
                    ? (int)$context['co']['scan']['read_at']
                    : time(),
                'ttl' => self::RELATION_SCAN_TTL,
            ),
        );
    }

    /**
     * Section six: MISP's own typed relation between two objects.
     *
     * Its own read and not the scan's, deliberately: three indexed
     * lookups and a resolve, against a scan that can read 20,000 rows.
     * A reader whose co-occurrence panel is still working should not be
     * waiting on it to learn that this address is `hosted-by` something.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array
     */
    public function forRelationReferences(array $user, $value,
        array $options = array()
    ) {
        unset($options['filters']);
        return array(
            'value' => $value,
            'relationships' => array(
                'references' => $this->referenceSection($user, $value,
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
        $digest = $this->relationDigest($user, $value, $options);
        return array(
            'value' => $value,
            'relationships' => array(
                'summary' => $digest['summary'],
                'graph' => $digest['graph'],
                'read_at' => $digest['read_at'],
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
        $digest = $this->relationDigest($user, $value, $options);
        return array(
            'value' => $value,
            'relationships' => array(
                'summary' => $digest['summary'],
                /*
                 * Only the flag, not the fold. This card reads
                 * `suppressed` and nothing else off the neighbourhood.
                 */
                'cooccurrence' => array(
                    'suppressed' => $digest['suppressed'],
                ),
                // config, so read live rather than held with the digest
                'settings' => $this->relationSettings($user, $value),
                'read_at' => $digest['read_at'],
            ),
        );
    }

    /**
     * The rail's third card: which named threats this value sits next
     * to.
     *
     * The one thing on the tab that answers *what does this mean*
     * rather than *what is related*. Everything else here lists edges;
     * this names the campaigns, actors, malware and tooling reachable
     * through the value, which is the read every peer platform leads
     * with and the one this tab had no answer for.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array
     */
    public function forRelationThreats(array $user, $value,
        array $options = array()
    ) {
        $digest = $this->relationDigest($user, $value, $options);
        return array(
            'value' => $value,
            'relationships' => array(
                'summary' => $digest['summary'],
                'threats' => $digest['threats'],
                'tactics' => $digest['tactics'],
                'read_at' => $digest['read_at'],
            ),
        );
    }

    /**
     * The Overview card: how many feeds and sync servers hold this value,
     * counting only the ones this reader may be told about.
     *
     * @param array $user
     * @param string $value
     * @param array $options Reserved
     * @return array
     */
    public function forExternal(array $user, $value,
        array $options = array()
    ) {
        return array(
            'value' => $value,
            'external' => $this->externalPresence($user, $value),
        );
    }

    /**
     * Section four: the remote events a feed or sync server holds this
     * value in.
     *
     * The same method the Overview card counts. Two panels filtering
     * independently is how the looser one becomes a disclosure and the
     * stricter one's accuracy becomes decorative
     * (`tabs/03-relationships.md` §20.1).
     *
     * @param array $user
     * @param string $value
     * @param array $options Reserved
     * @return array
     */
    public function forRelationExternal(array $user, $value,
        array $options = array()
    ) {
        return array(
            'value' => $value,
            'external' => $this->externalPresence($user, $value),
        );
    }

    /**
     * Which cached feeds and sync servers hold this value, filtered to
     * what this reader may see.
     *
     * **The visibility rule is per source, not per reader**, and it is
     * chosen so the page is never looser than any surface MISP already
     * ships:
     *
     *   lookup_visible = 1   every role — `/feeds/searchCaches` is
     *                        reachable by everyone and returns these by
     *                        name, so gating them here would hide what
     *                        the same reader gets one click away.
     *   lookup_visible = 0   site admin. The column defaults to 0, so on
     *                        a stock instance this is every feed.
     *   sync servers         site admin only. One notch stricter than
     *                        the event view, which admits the host org,
     *                        because `servers/previewEvent` is site
     *                        admin only and the link is the row's whole
     *                        value.
     *
     * **The feed rule read `perm_view_feed_correlations` until B3, and
     * that was a disclosure.** All three surfaces MISP ships withhold a
     * non-lookup-visible feed's *identity* from everyone but a site
     * admin: `Feed::getCachedFeedsOrServers` conditions on
     * `lookup_visible = 1` for `!perm_site_admin`, and `/feeds/index`
     * and `/feeds/searchCaches` add a host-org branch that cannot fire
     * — they compare a session `org_id` (string `'1'`) against
     * `MISP.host_org_id` (int `1`) with `!==`, so every non-site-admin
     * takes the limited path. `perm_view_feed_correlations` gates
     * *whether feed correlations are shown at all*, never *which feeds
     * may be named*, and `AppModel`'s own migration sets it to 1 for
     * every existing role — so on any upgraded instance the old rule
     * handed each of them the name, URL and remote events of feeds
     * `/feeds/index` refuses to list for them. Measured on the dev
     * instance 2026-09-01: a non-host-org Org Admin carrying the perm
     * was handed `CIRCL OSINT Feed`, its URL and two event links for a
     * value, while `searchCaches` returned that reader one feed and
     * `/feeds/index` did not list it at all.
     *
     * The host-org branch is deliberately not reproduced here. Copying
     * it would mean copying a comparison that does not do what it reads
     * as doing, and fixing it belongs to those surfaces, not to a page
     * that only reads them.
     *
     * `Feed::searchCaches` applies no role check at all, so nothing here
     * may render its output directly. It is called for the whole
     * instance and its hits are then intersected with the ids this
     * reader is allowed to be told about.
     *
     * **`restricted` is keyed on the role and never on the value.** It
     * is true whenever the instance holds cached sources of that kind
     * that this reader's role cannot reach, whether or not this
     * particular value hits any of them — which is what lets it exist
     * at all under `live/00-contract.md` §14.6. A notice that appeared
     * only when something was hidden would be the same disclosure at
     * one bit.
     *
     * @param array $user
     * @param string $value
     * @return array
     */
    /**
     * Which cached sources exist, and which of them this reader may be
     * told about — the config half of §20.2's rule, with no value in it.
     *
     * Split out from `externalPresence()` because the rail's "What is
     * counted" card states these rules for a value with nothing to
     * count, so it needs them without paying for a cache lookup.
     * Everything it returns is a property of the instance and the
     * reader's role, never of a value.
     *
     * @param array $user
     * @return array
     */
    private function externalVisibility(array $user)
    {
        $isSiteAdmin = !empty($user['Role']['perm_site_admin']);

        $cachedFeeds = $this->model('Feed')->find('all', array(
            'conditions' => array('Feed.caching_enabled' => 1),
            'recursive' => -1,
            'fields' => array('Feed.id', 'Feed.lookup_visible'),
        ));
        $cachedServerCount = $this->model('Server')->find('count', array(
            'conditions' => array('Server.caching_enabled' => 1),
            'recursive' => -1,
        ));

        $visibleFeedIds = array();
        $withheldFeeds = 0;
        foreach ($cachedFeeds as $feed) {
            if ($isSiteAdmin || !empty($feed['Feed']['lookup_visible'])) {
                $visibleFeedIds[(string)$feed['Feed']['id']] = true;
            } else {
                $withheldFeeds++;
            }
        }

        return array(
            'site_admin' => $isSiteAdmin,
            'visible_feed_ids' => $visibleFeedIds,
            'cached' => array(
                'feeds' => count($cachedFeeds),
                'servers' => $cachedServerCount,
            ),
            'visible' => array(
                'feeds' => count($visibleFeedIds),
                'servers' => $isSiteAdmin ? $cachedServerCount : 0,
            ),
            // role and instance config only — never a value
            'restricted' => array(
                'feeds' => $withheldFeeds > 0,
                'servers' => !$isSiteAdmin && $cachedServerCount > 0,
            ),
            'event_cap' => self::EXTERNAL_EVENT_CAP,
        );
    }

    private function externalPresence(array $user, $value)
    {
        $feedModel = $this->model('Feed');
        $visibility = $this->externalVisibility($user);
        $visibleFeedIds = $visibility['visible_feed_ids'];
        $isSiteAdmin = $visibility['site_admin'];

        $presence = array(
            'sources' => array(),
            'counts' => array('feeds' => 0, 'servers' => 0),
            'events' => 0,
            'restricted' => $visibility['restricted'],
            'cached' => $visibility['cached'],
            'visible' => $visibility['visible'],
            'event_cap' => self::EXTERNAL_EVENT_CAP,
        );

        if (empty($visibleFeedIds) && !$isSiteAdmin) {
            return $presence;
        }
        if (empty($visibility['cached']['feeds'])
            && empty($visibility['cached']['servers'])
        ) {
            return $presence;
        }

        foreach ($feedModel->searchCaches($value, false) as $hit) {
            $source = $hit['Feed'];
            $isServer = ($source['type'] === 'MISP Server');
            if ($isServer) {
                if (!$isSiteAdmin) {
                    continue;
                }
            } elseif (!isset($visibleFeedIds[(string)$source['id']])) {
                continue;
            }

            $events = array();
            if (!empty($source['direct_urls']) && !empty($source['uuid'])) {
                foreach ($source['direct_urls'] as $link) {
                    $events[] = array(
                        'name' => $link['name'],
                        'url' => $link['url'],
                    );
                }
            }
            $presence['sources'][] = array(
                'id' => $source['id'],
                'name' => $source['name'],
                'url' => isset($source['url']) ? $source['url'] : null,
                'kind' => $source['type'],
                'scope' => $isServer ? 'server' : 'feed',
                'events' => array_slice($events, 0, self::EXTERNAL_EVENT_CAP),
                'events_total' => count($events),
            );
            $presence['events'] += count($events);
            $presence['counts'][$isServer ? 'servers' : 'feeds']++;
        }

        return $presence;
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
        array $options = array(), array $filters = array()
    ) {
        /*
         * Keyed on the narrowing as well, so the once-per-request memo
         * cannot hand a filtered fold to a caller that asked for the
         * whole neighbourhood.
         */
        if ($this->cooccurrence !== null
            && $this->cooccurrenceFilters === $filters
            && $this->memoValue === $value
        ) {
            return $this->cooccurrence;
        }
        $this->forget($value);
        $this->cooccurrenceFilters = $filters;
        $fresh = !empty($options['fresh']);
        unset($options['fresh']);
        $scan = $this->relationScan($user, $value, $options, $fresh);

        $co = ValueRelationTool::cooccurrence($scan['rows'], array(
            'orgs' => $scan['orgs'],
            'events' => $scan['event_meta'],
            'sharing_groups' => $scan['sharing_groups'],
            'our_objects' => $scan['our_objects'],
            'filters' => $filters,
            'row_cap' => self::RELATION_ROW_CAP,
            'page_size' => self::RELATION_PAGE_SIZE,
            /*
             * Read over the whole scan, so the facet the fold builds
             * from it counts the neighbourhood and not the page — the
             * same promise every other facet in that bar makes.
             */
            'warninglists' => isset($scan['warninglists'])
                ? $scan['warninglists']
                : array(),
            'warninglists_checked' => isset($scan['warninglists_checked'])
                ? $scan['warninglists_checked']
                : 0,
            /*
             * Also read over the whole scan, and for a stronger reason
             * than the facet's: this is what the **Most specific** rank
             * divides by, and the rank decides which neighbours reach
             * the cut. A denominator covering only the carried hundred
             * would rank a page instead of a fold.
             */
            'prevalence' => isset($scan['prevalence'])
                ? $scan['prevalence']
                : array(),
            /*
             * §10.2's label side. `own_tags` and the events' own tags
             * are what a label neighbour is folded from; `clusters` is
             * the ACL ruling on the galaxy ones, without which a tag
             * name is not a cluster this viewer may be shown.
             *
             * All three are lookups, which is why they arrive as
             * context: `ValueRelationTool` issues no queries.
             */
            'own_tags' => isset($scan['own_tags'])
                ? $scan['own_tags']
                : array(),
            'clusters' => isset($scan['clusters'])
                ? $scan['clusters']
                : array('by_tag' => array(), 'by_uuid' => array()),
        ));
        $co['siblings'] = $scan['siblings'];
        $co['dated'] = $scan['dated'];
        $co['scan'] = array(
            'events_read' => count($scan['picked']),
            'events_seen' => $scan['events_seen'],
            'events_oversized' => $scan['events_oversized'],
            'events_unread' => $scan['events_unread'],
            'event_cap' => self::RELATION_EVENT_CAP,
            'size_cap' => self::RELATION_EVENT_SIZE_CAP,
            'budget' => self::RELATION_SCAN_BUDGET,
            'rows_read' => count($scan['rows']),
            'row_cap' => self::RELATION_ROW_CAP,
            /*
             * When the rows under this panel were read. Zero seconds on
             * a scan that just ran, up to `RELATION_SCAN_TTL` on one
             * served from Redis — and the panel prints it, because a
             * cached read that does not say how old it is is the reason
             * a cache this long would otherwise be a trap.
             */
            'read_at' => $scan['read_at'],
            'ttl' => self::RELATION_SCAN_TTL,
        );
        /*
         * Not "the engine stored nothing" — the opposite claim. Every
         * event this value appears in is too large to read for
         * co-occurrence, so there is a neighbourhood and it is not
         * being shown, which is what `.vp-suppressed` says and what an
         * empty state would get exactly backwards.
         */
        $co['suppressed'] = empty($scan['picked'])
            && $scan['events_oversized'] > 0;

        $this->cooccurrence = array('co' => $co);
        return $this->cooccurrence;
    }

    /**
     * Everything the fold reads, from Redis where it is still warm.
     *
     * The narrowing re-requests the panel, so the same scan would
     * otherwise run again to fold the same rows against a different
     * filter. Cached, the first request pays for it and every narrowing
     * after it is a fold over rows already in hand.
     *
     * Keyed on the viewer, because every row in here went through
     * `buildConditions($user)` and two readers of one value do not see
     * the same neighbourhood. Redis being unavailable is not an error:
     * the scan runs, and the page is merely as slow as it was before.
     *
     * `$fresh` is the reader pressing the panel's own refresh: the
     * entry is skipped on the way in and rewritten on the way out, so
     * one press lands on new rows rather than on an empty cache.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @param bool $fresh
     * @return array
     */
    /**
     * The tab's numbers and its graph feed, held as one small entry.
     *
     * **This is §15.1 item 1.** The tab fires six requests, and two of
     * them are rail cards that describe the other four. They used to
     * re-assemble every section to do it: on `8.8.8.8` the graph card
     * cost 245 ms and the settings card 210 ms against a warm cache,
     * more than the 190 ms section they were summarising, because both
     * inflated an 11.6 MB scan out of Redis and re-folded 21,904 rows to
     * print a handful of counts.
     *
     * What they actually need is small — four integers, a suppressed
     * flag, and a graph capped at `GRAPH_NODE_CAP` per notion, so at
     * most 37 nodes. That is what is stored here, under its own key, so
     * a rail card reads kilobytes instead of megabytes.
     *
     * **Held on the same terms as the scan** (§16.7): the same TTL, the
     * same per-viewer key because every number in it went through
     * `buildConditions($user)`, and Redis being unavailable falls
     * through to computing it. `read_at` is the *scan's* stamp rather
     * than this entry's, because the co-occurrence counts are the oldest
     * thing in here and the honest age to disclose is the oldest one.
     * Both rail cards print it, for the reason §16.7 gives: a cached
     * read that does not say how old it is is a trap.
     *
     * Cold costs what it always did — whichever request misses first
     * assembles everything, and on a cold tab several miss at once.
     * What this removes is the repeat.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array `summary`, `graph`, `suppressed`, `read_at`
     */
    private function relationDigest(array $user, $value,
        array $options = array()
    ) {
        $fresh = !empty($options['fresh']);
        $keyOptions = $options;
        unset($keyOptions['fresh']);
        $key = 'misp:value_profile:relation_digest:v'
            . self::CACHE_SHAPE . ':' . (int)$user['id']
            . ':' . hash('sha256', $value . '|' . json_encode($keyOptions));

        $redis = null;
        try {
            $redis = RedisTool::init();
        } catch (Exception $e) {
            $redis = null;
        }
        if ($redis !== null && !$fresh) {
            $cached = RedisTool::deserialize(
                RedisTool::decompress($redis->get($key))
            );
            if (!empty($cached)) {
                return $cached;
            }
        }

        $context = $this->cooccurrenceContext($user, $value, $options);
        $types = $this->model('Value')->typesFor($user, $value, $options);
        $near = $this->nearMatches($user, $value, $types);
        $asserted = $this->assertedClaims($user, $value, $options);
        $external = $this->externalPresence($user, $value);
        $references = $this->referenceSection($user, $value, $options);

        $digest = array(
            'summary' => $this->relationSummary($user, $value, $options,
                array(
                    'cooccurrence' => $context['co'],
                    'near' => $near,
                    'asserted' => $asserted,
                    'external' => $external,
                    'references' => $references,
                )),
            'graph' => $this->graphFor(array(
                'siblings' => $context['co']['siblings'],
                /*
                 * The events the scan read, which is the set the tab
                 * already discloses the bounds of. An event too large
                 * to fold has no roll-up row and therefore no node —
                 * the same suppression the co-occurrence panel states
                 * in words, rather than a second, quieter cut.
                 */
                'events' => $context['co']['rollups']['event']['rows'],
                'near' => $near,
                'asserted' => $asserted,
                'references' => $references,
            ), $value),
            'suppressed' => !empty($context['co']['suppressed']),
            /*
             * **Read off the scan now, and the property that made that
             * safe is the scan's, not this card's.** The scan skips an
             * event too large to fold for co-occurrence, but an event's
             * tags cost the same whatever its size — so `readRelationScan`
             * reads the label side over every event the value is in.
             * Tying the named threats to the attribute budget would
             * drop them for an unrelated reason, and this card still
             * answers on values whose neighbourhood table is suppressed
             * entirely. §10.2.
             */
            /*
             * `$asserted['claims']`, not `$asserted` — that array is a
             * section, and its other keys are counts. Passing the whole
             * thing made the fold iterate `total` and `orgs` as though
             * they were claims, which skips every one of them without
             * erroring: `$claim['target']` on an integer is null, and
             * null is not `GalaxyCluster`.
             */
            'threats' => $this->neighbourhoodThreats(
                $user,
                $context['co'],
                isset($asserted['claims'])
                    ? $asserted['claims']
                    : array()
            ),
            /*
             * The same card's second group, and a second reader of the
             * same label rows rather than a second read of anything:
             * the `attack-pattern` clusters the card above filters out
             * are the ones this folds. The chain it orders them on is
             * the one lookup, over a 130-row table, held here with the
             * rest of the digest.
             */
            'tactics' => $this->neighbourhoodTactics(
                $context['co'],
                $this->tacticChain()
            ),
            'read_at' => isset($context['co']['scan']['read_at'])
                ? (int)$context['co']['scan']['read_at']
                : time(),
        );

        if ($redis !== null) {
            $redis->setex(
                $key,
                self::RELATION_SCAN_TTL,
                RedisTool::compress(RedisTool::serialize($digest))
            );
        }
        return $digest;
    }

    /**
     * The named threats reachable through this value, as a slice of the
     * co-occurrence fold.
     *
     * **A named threat is a galaxy cluster and nothing else**, and
     * `GalaxyCategory` holds both that rule and the evidence for it.
     * The short version: freetext tags cannot carry the claim — the two
     * most-used on this instance are the word `malware` and ` C2`, and
     * one malware family appears under seven spellings — and no
     * installed taxonomy names an individual threat, they classify one.
     *
     * **This used to be six queries and is now one.** §10.2's change of
     * definition is what made that possible: a galaxy cluster is a
     * neighbour in its own right now, so the events, their metadata,
     * their tags, the tags on this value's own occurrences and the
     * `fetchGalaxyClusters` ruling over all of them are read once by
     * `readRelationScan` and folded once by `ValueRelationTool`. This
     * card reads that fold, filters it to `named-threat`, and ranks
     * what is left for a 340px rail.
     *
     * The one query left is the one the fold cannot answer: a claim
     * points at a cluster by UUID and may name one that appears on no
     * event here, so `claimClusters` still resolves those under the
     * viewer's ACL. It sends nothing when no claim names a cluster.
     *
     * **The scope property that had to survive.** The card answers on
     * values whose neighbourhood table is suppressed entirely, because
     * an event's tags cost the same whatever its size while its
     * attributes do not. That is why `readRelationScan` reads the label
     * side over every event the value is in rather than over the events
     * the attribute budget afforded — the note is there too, and the
     * two have to stay in step.
     *
     * **What `orgs` counts, and what it cannot.** Neither `event_tags`
     * nor `attribute_tags` records who applied a tag — there is no org
     * and no user on either table — so this counts the *creator
     * organisations of the events carrying the cluster*, and the card
     * says so in words. A claim is the one source that does record an
     * author, and contributes that org instead.
     *
     * **Four ways in, one word out.** A cluster can arrive on the
     * value's own occurrence, on a neighbouring attribute, on an event
     * the value appears in, or as the far end of an analyst's claim
     * about it. Where more than one applies the most specific wins,
     * which is the order in `threatRank` — and it is a decision rather
     * than a derivation: a claim outranks a tag on the value because it
     * carries an author and a relationship type, so it is the more
     * informative thing to print. The first three are the fold's own
     * `attachment`, decided in `ValueRelationTool::$attachments` so
     * that the word on this row and the word on the table's row are
     * the same word from the same place.
     *
     * **A fifth is coming.** Objects cannot be tagged yet: the join
     * tables are `attribute_tags`, `event_tags` and
     * `event_report_tags`, there is no `object_tags`, and `MispObject`
     * reaches a tag only through its attributes. When `ObjectTag`
     * lands, a cluster on the object a value sits in belongs between
     * `value` and `neighbour` in both rank tables — grep either name to
     * find them.
     *
     * @param array $user
     * @param array $co The co-occurrence fold, `$context['co']`
     * @param array $claims `assertedClaims()['claims']` — the list,
     *     not the section that wraps it
     * @return array rows, total, cap, events_read
     */
    private function neighbourhoodThreats(array $user, array $co,
        array $claims
    ) {
        $found = array(
            'rows' => array(),
            'total' => 0,
            'cap' => self::THREAT_ROW_CAP,
            'events_read' => isset($co['scan']['events_seen'])
                ? (int)$co['scan']['events_seen']
                : 0,
            'event_cap' => self::RELATION_EVENT_CAP,
        );

        /*
         * Event titles for the figures hover, off the fold's own event
         * roll-up rather than a second `eventMetadata` read. Every
         * event carrying a label produces a group, and the roll-up is
         * built from the groups after the labels are folded in — so an
         * event on a row here is an event with a row there.
         */
        $eventTitles = array();
        if (isset($co['rollups']['event']['rows'])) {
            foreach ($co['rollups']['event']['rows'] as $eventRow) {
                $eventTitles[(int)$eventRow['event']['id']] = array(
                    'id' => (int)$eventRow['event']['id'],
                    'info' => $eventRow['event']['info'],
                    'date' => $eventRow['event']['date'],
                );
            }
        }

        $rows = array();
        /*
         * The label section's rows, which are held uncapped for exactly
         * this reader — the table beside them renders the first `cap`,
         * and a cluster reaching this value through one event sits
         * nowhere near the top of a list ranked by shared events.
         */
        $labels = isset($co['labels']['rows'])
            ? $co['labels']['rows']
            : array();
        foreach ($labels as $label) {
            if ($label['kind'] !== ValueRelationTool::KIND_CLUSTER
                || empty($label['cluster'])
            ) {
                continue;
            }
            $this->addThreat(
                $rows,
                $label['cluster'],
                $label['attachment'],
                $label,
                $eventTitles
            );
        }

        /*
         * The claims, which are not in the fold: a `Relationship` row
         * is not a label on an event, and the asserted section is what
         * reads them. A claim naming a cluster the fold already holds
         * lands on that row; one naming a cluster nothing here is
         * tagged with brings its own.
         */
        $clusters = $this->claimClusters($user, $claims);
        foreach ($claims as $claim) {
            if ($claim['target']['kind'] !== 'GalaxyCluster') {
                continue;
            }
            $uuid = $claim['target']['uuid'];
            if (!isset($clusters['by_uuid'][$uuid])) {
                continue;
            }
            $this->addThreat(
                $rows,
                $clusters['by_uuid'][$uuid],
                'claim',
                null,
                $eventTitles,
                $claim
            );
        }

        /*
         * Both sets were accumulated keyed, so a cluster reached
         * through three sources on one event counts that event once.
         * The counts are what the row prints and the lists are what
         * the hover names, which is why both survive.
         */
        foreach ($rows as $id => $row) {
            $rows[$id]['events'] = count($row['event_list']);
            $rows[$id]['orgs'] = count($row['org_names']);
            $rows[$id]['event_list'] = array_values($row['event_list']);
            $rows[$id]['org_names'] = array_values($row['org_names']);
            sort($rows[$id]['org_names']);
        }
        $rows = array_values($rows);
        /*
         * Organisations, then events, then the name — and attachment
         * is not in the sort on purpose. The word on the row already
         * says how a cluster got there; ranking by it as well would
         * bury a threat four organisations reported under one somebody
         * tagged on the value once.
         */
        usort($rows, function ($a, $b) {
            if ($a['orgs'] !== $b['orgs']) {
                return $b['orgs'] - $a['orgs'];
            }
            if ($a['events'] !== $b['events']) {
                return $b['events'] - $a['events'];
            }
            return strcasecmp($a['name'], $b['name']);
        });
        $found['rows'] = $rows;
        $found['total'] = count($rows);
        return $found;
    }

    /**
     * One cluster onto the card, or nothing if it names no threat.
     *
     * **This is where §10.2's filter is applied**, and it stays here
     * rather than moving into the fold: the table carries every
     * cluster it found, and *named threat* is the rail card's own
     * question about them. A `TECHNIQUE` cluster is a perfectly good
     * neighbour and a bad answer to *who is this*.
     *
     * @param array $rows Accumulator, keyed by cluster id
     * @param array $cluster A `fetchGalaxyClusters` row
     * @param string $attachment `event`, `neighbour`, `value` or
     *     `claim`
     * @param array|null $label The fold's row for this cluster, whose
     *     events and organisations are the card's figures. Null for a
     *     cluster a claim brought in, which the fold never saw.
     * @param array $eventTitles Event id => id, info, date
     * @param array|null $claim The claim, where one is what brought
     *     the cluster in. Kept so the card's own badge can say who
     *     asserted it, how, and when — three words on the row is the
     *     right size for the rail, and the rest belongs on hover.
     * @return void
     */
    private function addThreat(array &$rows, array $cluster,
        $attachment, $label, array $eventTitles, array $claim = null
    ) {
        $row = $cluster['GalaxyCluster'];
        if (!GalaxyCategory::isNamedThreat($row['type'])) {
            return;
        }
        $id = (int)$row['id'];
        if (!isset($rows[$id])) {
            $rows[$id] = array(
                'id' => $id,
                'name' => $row['value'],
                'galaxy' => empty($row['Galaxy']['name'])
                    ? $row['type']
                    : $row['Galaxy']['name'],
                'kind' => GalaxyCategory::kindOf($row['type']),
                'attachment' => $attachment,
                /*
                 * Names, not ids, and that is the second query this
                 * card stopped issuing: the fold resolved the
                 * organisations behind its rows already, over the same
                 * ids and under the same `Organisation` read the value
                 * rows needed anyway.
                 */
                'org_names' => array(),
                'event_list' => array(),
                'claims' => array(),
                /*
                 * The same display shape the asserted section builds
                 * for a claim's far end, so the card renders the very
                 * same hover element rather than a second one that
                 * would drift from it. Free: the GalaxyCluster branch
                 * of `claimTarget` reads only the row already fetched,
                 * and `claimTargetOrg` returns null rather than
                 * insisting on an organisation lookup — so the card
                 * omits that row instead of costing a query for it.
                 */
                'target' => $this->claimTarget(
                    'GalaxyCluster',
                    $row['uuid'],
                    $cluster,
                    array(
                        'orgs' => array(),
                        'tags' => array(),
                        'galaxy_by_event' => array(),
                        'clusters' => array(),
                    )
                ),
            );
        }
        if ($claim !== null) {
            $rows[$id]['claims'][] = array(
                'type' => $claim['relationship_type'],
                'anchor' => isset($claim['anchor'])
                    ? $claim['anchor']
                    : null,
                'org' => $claim['org'],
                'date' => $claim['date'],
            );
        }
        if (self::threatRank($attachment)
            > self::threatRank($rows[$id]['attachment'])
        ) {
            $rows[$id]['attachment'] = $attachment;
        }
        if ($label === null) {
            return;
        }
        foreach ($label['orgs'] as $name) {
            $rows[$id]['org_names'][$name] = $name;
        }
        foreach ($label['events'] as $eventId) {
            $eventId = (int)$eventId;
            $rows[$id]['event_list'][$eventId] =
                isset($eventTitles[$eventId])
                    ? $eventTitles[$eventId]
                    : array(
                        'id' => $eventId,
                        'info' => '',
                        'date' => '',
                    );
        }
    }

    /**
     * How specific a way of reaching the value is.
     *
     * The fold's own `$attachments` plus `claim` on top, because a
     * claim is not a label on an event and reaches this card by
     * another road — see `neighbourhoodThreats`. `object` belongs
     * between `value` and `neighbour` once objects can be tagged, in
     * both tables.
     *
     * @param string $attachment
     * @return int
     */
    private static function threatRank($attachment)
    {
        $ranks = array(
            'claim' => 4,
            'value' => 3,
            'neighbour' => 2,
            'event' => 1,
        );
        return isset($ranks[$attachment]) ? $ranks[$attachment] : 0;
    }

    /**
     * Where in the intrusion this value's neighbourhood sits: the
     * technique clusters around it, collapsed to their tactics.
     *
     * A second group on the named-threat card, answering *what stage*
     * where that one answers *who* — which is why it is a group and not
     * more rows in the same list. It is a fold over the labels the
     * co-occurrence scan already holds: the `attack-pattern` clusters
     * that card filters out are sitting in `$co['labels']['rows']` with
     * their events and organisations counted, so this issues no query
     * and reads nothing the tab has not already read.
     *
     * **A technique counts in every tactic it belongs to**, because
     * ATT&CK genuinely files several that way — *Registry Run Keys /
     * Startup Folder* is persistence and privilege escalation both, and
     * picking one of the two would be this page inventing a fact. The
     * consequence is that the counts sum to more than the techniques
     * they were folded from, so the group states the technique total
     * beside them rather than leaving a reader to add the chips up and
     * get a bigger number than the neighbourhood holds.
     *
     * **A technique with no `kill_chain` element cannot be placed**, and
     * is counted rather than dropped: 7 of the clusters tagged on events
     * here are in that state — 2 attack patterns and 5 ICS techniques
     * whose galaxy ships no kill chain at all. The group says how many
     * it could not place, on the same rule every cap on this tab
     * follows.
     *
     * @param array $co The co-occurrence fold, `$context['co']`
     * @param array $chain Tactic token => position, `tacticChain()`
     * @return array rows, total, techniques, placed, unplaced, multi
     */
    private function neighbourhoodTactics(array $co, array $chain)
    {
        $found = array(
            'rows' => array(),
            'total' => 0,
            'techniques' => 0,
            'placed' => 0,
            'unplaced' => 0,
            'multi' => 0,
        );
        $labels = isset($co['labels']['rows'])
            ? $co['labels']['rows']
            : array();

        $byTactic = array();
        foreach ($labels as $label) {
            if ($label['kind'] !== ValueRelationTool::KIND_CLUSTER
                || empty($label['cluster'])
            ) {
                continue;
            }
            $record = $label['cluster']['GalaxyCluster'];
            if (!GalaxyCategory::isAttackPattern($record['type'])) {
                continue;
            }
            $found['techniques']++;
            $tactics = empty($record['tactics'])
                ? array()
                : $record['tactics'];
            if (empty($tactics)) {
                $found['unplaced']++;
                continue;
            }
            $found['placed']++;
            if (count($tactics) > 1) {
                $found['multi']++;
            }
            foreach ($tactics as $token) {
                if (!isset($byTactic[$token])) {
                    $byTactic[$token] = array(
                        'tactic' => $token,
                        'name' => self::tacticName($token),
                        'techniques' => array(),
                        'events' => array(),
                        'orgs' => array(),
                        'galaxies' => array(),
                    );
                }
                /*
                 * Whose kill chain this tactic is on, and the chip's
                 * hover is where it has to be said. The strip is one
                 * ordered run of chips, so a value whose techniques
                 * span two frameworks — `attck4fraud`'s tactics after
                 * ATT&CK's, which is what the fraud events here
                 * produce — reads as a single chain running past
                 * `Impact`, and no framework claims that. Naming the
                 * galaxy is the same answer the rows above give for a
                 * cluster's family, from the same field.
                 */
                $galaxy = empty($record['Galaxy']['name'])
                    ? $record['type']
                    : $record['Galaxy']['name'];
                $byTactic[$token]['galaxies'][$galaxy] = $galaxy;
                /*
                 * The cluster's own name, which is what the fold prints
                 * in place of a value — `Masquerading - T1036` rather
                 * than the tag string storing it.
                 */
                $byTactic[$token]['techniques'][$label['label']] =
                    $label['label'];
                foreach ($label['events'] as $eventId) {
                    $byTactic[$token]['events'][(int)$eventId] = true;
                }
                foreach ($label['orgs'] as $name) {
                    $byTactic[$token]['orgs'][$name] = $name;
                }
            }
        }

        $rows = array();
        $frameworks = array();
        foreach ($byTactic as $token => $tactic) {
            $names = array_values($tactic['techniques']);
            sort($names);
            $galaxies = array_values($tactic['galaxies']);
            sort($galaxies);
            foreach ($galaxies as $galaxy) {
                $frameworks[$galaxy] = $galaxy;
            }
            $rows[] = array(
                'tactic' => $token,
                'name' => $tactic['name'],
                'techniques' => count($names),
                'technique_names' => $names,
                'events' => count($tactic['events']),
                'orgs' => count($tactic['orgs']),
                'galaxies' => $galaxies,
                /*
                 * A tactic the chain cannot place still gets a chip —
                 * it is a real reading of a real cluster — and sorts
                 * after the ones it can, rather than at a position the
                 * data never stated.
                 */
                'position' => isset($chain[$token])
                    ? $chain[$token]
                    : null,
            );
        }
        usort($rows, function ($a, $b) {
            if (($a['position'] === null) !== ($b['position'] === null)) {
                return $a['position'] === null ? 1 : -1;
            }
            if ($a['position'] !== null
                && $a['position'] !== $b['position']
            ) {
                return $a['position'] - $b['position'];
            }
            return strcasecmp($a['name'], $b['name']);
        });
        $found['rows'] = $rows;
        $found['total'] = count($rows);
        /*
         * How many kill chains the strip is showing at once. One is the
         * ordinary case and needs no words; more than one is the case
         * where the strip's single ordered run stops being a single
         * chain, and the group says so.
         */
        $found['frameworks'] = count($frameworks);
        return $found;
    }

    /**
     * A tactic token as a reader should see it.
     *
     * The token is whatever the galaxy wrote after the last colon of a
     * `kill_chain` element, normalised — so `defense-evasion` and
     * `Defense Evasion` are one tactic rather than two chips saying the
     * same thing. Sentence case rather than ATT&CK's own Title Case:
     * the chips sit under a card whose rows are proper names, and
     * title-casing a phrase competes with them for the same glance.
     *
     * @param string $token
     * @return string
     */
    private static function tacticName($token)
    {
        $words = str_replace('-', ' ', $token);
        return mb_strtoupper(mb_substr($words, 0, 1)) . mb_substr($words, 1);
    }

    /**
     * One tactic token, as the two callers have to agree on it.
     *
     * A `kill_chain` element is `<tab>:<tactic>` — `attack-Windows:
     * defense-evasion` — and occasionally `<galaxy>:<tab>:<tactic>`,
     * which the deprecated MITRE galaxies write. The tactic is the last
     * segment either way, and the tab in front of it is the *platform*
     * rather than a second dimension: one technique carries up to 40 of
     * these elements, the same handful of tactics repeated once per
     * operating system it applies to. Reading the last segment and
     * de-duplicating is what collapses that back to the tactics.
     *
     * Lower-cased and dashed, which merges the three spellings the
     * shipped galaxies use for one tactic — ATT&CK's `defense-evasion`,
     * ATRM's `Initial Access`, MoTIF's `Initial-Access`. It does not
     * merge `defence-evasion` with `defense-evasion`: that is a real
     * spelling difference between two frameworks and papering over it
     * would need a dictionary this page has no business holding.
     *
     * @param string $value A `kill_chain` element's value
     * @return string
     */
    private static function tacticToken($value)
    {
        $parts = explode(':', (string)$value);
        $tactic = trim(array_pop($parts));
        return str_replace(' ', '-', mb_strtolower($tactic));
    }

    /**
     * Which tactics each of these clusters names, keyed by cluster id.
     *
     * One indexed read over `galaxy_elements`, and it is the query B9
     * was scoped believing it would not need: the tactic is not on the
     * cluster row. `fetchGalaxyClusters` contains `Galaxy` and nothing
     * else unless asked for everything, so the `kill_chain` elements —
     * 5,964 rows of the table's 304,132 — have to be read. Scoped to
     * the `attack-pattern` clusters actually in play, which is tens of
     * ids against an index on `galaxy_cluster_id`.
     *
     * It runs in the scan rather than in the digest so that the card's
     * fold stays query-free and the answer ages at the rate the panel
     * already discloses.
     *
     * @param array $clusters `claimClusters`' by_uuid / by_tag maps
     * @return array Cluster id => tactic tokens, in first-seen order
     */
    private function clusterTactics(array $clusters)
    {
        $ids = array();
        foreach (array('by_tag', 'by_uuid') as $side) {
            if (empty($clusters[$side])) {
                continue;
            }
            foreach ($clusters[$side] as $row) {
                $record = $row['GalaxyCluster'];
                if (GalaxyCategory::isAttackPattern($record['type'])) {
                    $ids[(int)$record['id']] = true;
                }
            }
        }
        if (empty($ids)) {
            return array();
        }
        $elements = $this->model('GalaxyElement')->find('all', array(
            'recursive' => -1,
            'fields' => array(
                'GalaxyElement.galaxy_cluster_id',
                'GalaxyElement.value',
            ),
            'conditions' => array(
                'GalaxyElement.key' => 'kill_chain',
                'GalaxyElement.galaxy_cluster_id' => array_keys($ids),
            ),
        ));
        $tactics = array();
        foreach ($elements as $element) {
            $id = (int)$element['GalaxyElement']['galaxy_cluster_id'];
            $token = self::tacticToken($element['GalaxyElement']['value']);
            if ($token === '') {
                continue;
            }
            if (!isset($tactics[$id])) {
                $tactics[$id] = array();
            }
            $tactics[$id][$token] = $token;
        }
        foreach ($tactics as $id => $tokens) {
            $tactics[$id] = array_values($tokens);
        }
        return $tactics;
    }

    /**
     * The kill chain these tactics sit on, as token => position.
     *
     * **Ordered by the galaxies' own `kill_chain_order`, pooled across
     * the ATT&CK-shaped families rather than read off one of them**, and
     * pooling is what makes the order come out right. `mitre-attack-
     * pattern`'s order is a map of *platform* tabs — one list per
     * operating system — and no tab holds both `reconnaissance` and
     * `initial-access`, so that galaxy alone cannot say which of them
     * comes first. It matters: `attack-PRE`'s two tactics reach 36
     * events on the verification instance, and a strip putting
     * reconnaissance after impact reads as a bug. Two galaxies ship the
     * whole chain as a single list — `cmtmf-attack-pattern` states
     * ATT&CK's fourteen tactics in order and `mitre-atlas-attack-
     * pattern` states them with its two ML stages inserted — and
     * reading those alongside the platform tabs supplies exactly the
     * relation the tabs omit. So the answer stays derived from shipped
     * data instead of from a tactic list hardcoded here, which is the
     * whole reason `GalaxyCategory` exists.
     *
     * **A single-list galaxy is read before a multi-tab one**, longest
     * list first, because one list is an unambiguous statement about
     * the whole chain while many tabs state an order per platform and
     * nothing between the platforms. Read the other way round, the
     * platform tabs place `initial-access` first and the complete chain
     * then contradicts them.
     *
     * **The merge inserts and never moves.** Each list walks the chain
     * built so far, and a tactic it names that is already placed only
     * advances the insertion point — so where two frameworks genuinely
     * disagree the first one read wins, deterministically, rather than
     * the merge having to detect a cycle and pick a loser anyway. A
     * list sharing no tactic with the chain is a separate framework's
     * kill chain — `attck4fraud`'s seven, the deprecated `pre-attack`'s
     * seventeen — and follows the chain rather than being interleaved
     * into it.
     *
     * **A new tactic lands as late as its list allows**, immediately
     * before the next tactic that list names and the chain has already
     * placed, rather than immediately after the previous one. Both
     * satisfy the list; the late reading puts a tactic beside the ones
     * it belongs with. MoTIF's `defence-evasion` is the case that shows
     * it: its list says only *after persistence, before credential
     * access*, and inserting early left the British spelling sitting
     * two places ahead of ATT&CK's `defense-evasion` instead of next
     * to it, which reads as a sort fault rather than as two frameworks
     * spelling one tactic differently.
     *
     * @return array Tactic token => position
     */
    private function tacticChain()
    {
        $rows = $this->model('Galaxy')->find('all', array(
            'recursive' => -1,
            'fields' => array('Galaxy.type', 'Galaxy.kill_chain_order'),
            'conditions' => array(
                'Galaxy.type' => GalaxyCategory::typesOfKind(
                    GalaxyCategory::ATTACK_PATTERN
                ),
            ),
        ));

        /*
         * `Galaxy::afterFind` decodes the column, and leaves null both
         * where it is empty and where it holds the literal `null` a
         * hand-made galaxy can carry.
         */
        $orders = array();
        foreach ($rows as $row) {
            $order = $row['Galaxy']['kill_chain_order'];
            if (empty($order) || !is_array($order)) {
                continue;
            }
            $tokens = 0;
            foreach ($order as $columns) {
                $tokens += count($columns);
            }
            $orders[] = array(
                'type' => $row['Galaxy']['type'],
                'tabs' => count($order),
                'tokens' => $tokens,
                'order' => $order,
            );
        }
        usort($orders, function ($a, $b) {
            if ($a['tabs'] !== $b['tabs']) {
                return $a['tabs'] - $b['tabs'];
            }
            if ($a['tokens'] !== $b['tokens']) {
                return $b['tokens'] - $a['tokens'];
            }
            return strcmp($a['type'], $b['type']);
        });

        $chain = array();
        foreach ($orders as $galaxy) {
            foreach ($galaxy['order'] as $columns) {
                $sequence = array();
                foreach ($columns as $column) {
                    $token = self::tacticToken($column);
                    if ($token !== '') {
                        $sequence[$token] = $token;
                    }
                }
                self::mergeTactics($chain, array_values($sequence));
            }
        }
        return array_flip($chain);
    }

    /**
     * One kill chain merged into the chain built so far.
     *
     * See `tacticChain` for why it inserts and never moves. A sequence
     * with nothing in common with the chain is appended whole, because
     * a framework whose tactics relate to none of the placed ones has
     * stated no position among them.
     *
     * @param array $chain Ordered tokens, modified in place
     * @param array $sequence One tab's tactics, in its own order
     * @return void
     */
    private static function mergeTactics(array &$chain, array $sequence)
    {
        if (empty($sequence)) {
            return;
        }
        if (empty(array_intersect($sequence, $chain))) {
            foreach ($sequence as $token) {
                $chain[] = $token;
            }
            return;
        }
        $at = -1;
        $pending = array();
        foreach ($sequence as $token) {
            $found = array_search($token, $chain, true);
            if ($found === false) {
                $pending[] = $token;
                continue;
            }
            if (!empty($pending)) {
                $insert = max($at + 1, $found);
                array_splice($chain, $insert, 0, $pending);
                $found += count($pending);
                $pending = array();
            }
            $at = max($at, $found);
        }
        if (!empty($pending)) {
            array_splice($chain, $at + 1, 0, $pending);
        }
    }

    private function relationScan(array $user, $value, array $options,
        $fresh = false
    ) {
        $key = 'misp:value_profile:relation_scan:v'
            . self::CACHE_SHAPE . ':' . (int)$user['id']
            . ':' . hash('sha256', $value . '|' . json_encode($options));
        $redis = null;
        try {
            $redis = RedisTool::init();
        } catch (Exception $e) {
            $redis = null;
        }
        if ($redis !== null && !$fresh) {
            $cached = RedisTool::deserialize(
                RedisTool::decompress($redis->get($key))
            );
            if (!empty($cached)) {
                return $cached;
            }
        }
        $scan = $this->readRelationScan($user, $value, $options);
        $scan['read_at'] = time();
        if ($redis !== null) {
            $redis->setex(
                $key,
                self::RELATION_SCAN_TTL,
                RedisTool::compress(RedisTool::serialize($scan))
            );
        }
        return $scan;
    }

    /**
     * The scan itself: choose the scope, then read it completely.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array
     */
    private function readRelationScan(array $user, $value, array $options)
    {
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

        /*
         * **Read over every event the value is in, not over the events
         * the attribute budget could afford.** §10.2 makes a label a
         * neighbour in its own right, and a label costs the same
         * whatever the size of the event carrying it: one indexed
         * `EventTag` read over at most `RELATION_EVENT_CAP` ids either
         * way. Scoping it to `$picked` would drop a cluster for a
         * reason that has nothing to do with clusters — the exact
         * argument `relationDigest` already made for keeping the threat
         * card off the scan, and the reason that card can now be a
         * slice of this fold instead of a second read.
         *
         * So the two halves of the table have two scopes, and the panel
         * states both rather than averaging them: values over the
         * events that were read, labels over the events that exist.
         */
        $eventMeta = $this->eventMetadata($user, array_keys($events));
        $ownTags = $valueModel->ownTagsFor(
            $user,
            $value,
            array_keys($events),
            $options
        );

        /*
         * Which of the galaxy tags in play name a cluster this viewer
         * may know exists. One `fetchGalaxyClusters` over every galaxy
         * name the three sources between them mention — the value's own
         * occurrences, the events it appears in, and the neighbouring
         * attributes the scan read.
         *
         * Unresolved is dropped, not printed, for the reason spelled
         * out at `claimTarget`: the tag string would disclose the
         * cluster the instance is withholding.
         */
        $galaxyNames = array();
        foreach ($eventMeta as $meta) {
            foreach ($meta['galaxy_tags'] as $name) {
                $galaxyNames[$name] = true;
            }
        }
        foreach ($ownTags as $name => $entry) {
            if ($entry['tag']['is_galaxy']) {
                $galaxyNames[$name] = true;
            }
        }
        foreach ($rows as $row) {
            if (empty($row['AttributeTag'])) {
                continue;
            }
            foreach ($row['AttributeTag'] as $attributeTag) {
                if (empty($attributeTag['Tag'])
                    || empty($attributeTag['Tag']['is_galaxy'])
                ) {
                    continue;
                }
                $galaxyNames[$attributeTag['Tag']['name']] = true;
            }
        }
        $clusters = $this->claimClusters(
            $user,
            array(),
            array_keys($galaxyNames)
        );

        /*
         * Which tactic each technique cluster names, written onto the
         * cluster record so the fold carries it into the label rows and
         * the tactic group needs no second lookup — the same decoration
         * `Galaxy::getMatrix` does with `external_id`, and for the same
         * reason: the element is what the caller wants and the row is
         * where it has to arrive.
         */
        $clusterTactics = $this->clusterTactics($clusters);
        foreach (array('by_tag', 'by_uuid') as $side) {
            foreach ($clusters[$side] as $key => $row) {
                $id = (int)$row['GalaxyCluster']['id'];
                $clusters[$side][$key]['GalaxyCluster']['tactics'] =
                    isset($clusterTactics[$id])
                        ? $clusterTactics[$id]
                        : array();
            }
        }

        /*
         * The organisations credited on a label row are the creator
         * organisations of the events carrying it — which reaches
         * further than the rows' own events now that labels are read
         * over all of them, so the ids come from both.
         */
        $eventOrgIds = array();
        $eventLevels = array();
        foreach ($eventMeta as $meta) {
            if (!empty($meta['orgc_id'])) {
                $eventOrgIds[(int)$meta['orgc_id']] = true;
            }
            $eventLevels[] = (int)$meta['distribution'];
        }
        $orgs = $this->organisationNames($rows, array_keys($eventOrgIds));

        /*
         * Held with the rows it describes rather than recomputed per
         * request, and that is the honest place for it: the panel
         * already prints how old this scan is, so the listing verdicts
         * age at the rate the panel discloses instead of at a rate it
         * does not mention. It costs 65 ms over `8.8.8.8`'s 10,040
         * neighbours (`ValueWarninglistTool`), which is worth paying
         * once a scan rather than on every narrowing of it.
         *
         * The consequence is bounded and stated: enabling a list shows
         * up here within `RELATION_SCAN_TTL`, or at once on the panel's
         * own refresh, which re-reads the scan.
         */
        $warninglistModel = $this->model('Warninglist');
        $probes = array();
        foreach ($rows as $row) {
            $probes[] = array(
                'value' => $row['Attribute']['value'],
                'type' => $row['Attribute']['type'],
            );
        }

        /*
         * The sibling join runs before the fold, because the fold needs
         * the object templates this value sits in: `sibling` is one of
         * the tokens a row is matched on, and a token the fold cannot
         * build is a filter the fold cannot apply.
         */
        $sections = $this->objectSections($user, $value, $options, $orgs);
        $siblings = $sections['siblings'];
        $ourObjects = array();
        foreach ($siblings['rows'] as $sibling) {
            $ourObjects[$sibling['object']] = true;
        }

        return array(
            'rows' => $rows,
            'picked' => $picked,
            'events_seen' => count($events),
            'events_oversized' => $oversized,
            'events_unread' => $unread,
            'orgs' => $orgs,
            'sharing_groups' => $this->sharingGroupNames(
                $user,
                $rows,
                $eventLevels
            ),
            'event_meta' => $eventMeta,
            /*
             * The label side of the fold, and the reason the threat
             * card no longer runs its own five queries: the tags on
             * this value's own occurrences, and the clusters those and
             * the event tags resolve to under this viewer's ACL.
             */
            'own_tags' => $ownTags,
            'clusters' => $clusters,
            'siblings' => $siblings,
            /*
             * Folded here rather than in its own endpoint because it
             * reads the rows this scan has already fetched. A second
             * endpoint would mean a second object join for a panel
             * whose whole input is sitting in this array.
             */
            'dated' => $sections['dated'],
            'our_objects' => $ourObjects,
            'warninglists' => ValueWarninglistTool::hitsFor(
                $warninglistModel,
                $probes
            ),
            'warninglists_checked' => ValueWarninglistTool::enabledCount(
                $warninglistModel
            ),
            /*
             * The **Most specific** rank's denominator, held with the
             * rows it describes for the same reason the warninglist
             * verdicts are: it is a lookup over the whole fold, it does
             * not change between narrowings of that fold, and the panel
             * already prints how old this scan is. 775 ms over
             * `8.8.8.8`'s 9,520 neighbours and 361 ms over `443`'s 758
             * sibling values — worth paying once a scan rather than on
             * every re-rank of it.
             *
             * Two maps because the two tables count different things.
             * The values table's rows are events, so a neighbour's
             * spread is events; the sibling table's rows are objects
             * and its visible count is objects, so a fraction in events
             * would not match the column beside it.
             */
            'prevalence' => $valueModel->prevalenceFor(
                $user,
                $this->neighbourValues($rows),
                $options
            ),
        );
    }

    /**
     * The neighbour value strings a prevalence lookup needs, deduped.
     *
     * Keyed exactly as `ValueRelationTool::cooccurrence` keys its
     * groups — on `Attribute.value` — so the map it hands back can be
     * read straight off the row without a second interpretation of
     * what a value is.
     *
     * @param array $rows Neighbour rows
     * @return array Value strings
     */
    private function neighbourValues(array $rows)
    {
        $values = array();
        foreach ($rows as $row) {
            if (!empty($row['Attribute']['value'])) {
                $values[$row['Attribute']['value']] = true;
            }
        }
        return array_keys($values);
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
                /*
                 * The event's own modification stamp, which is what a
                 * label row's **Last together** reads. A tag carries no
                 * date of its own on either join table, and the event
                 * date is the day the incident is filed under rather
                 * than the day the record last moved — so the honest
                 * pairing with a value row's `Attribute.timestamp` is
                 * this, the same clock and the same meaning.
                 */
                'timestamp' => (int)$event['Event']['timestamp'],
                'orgc_id' => (int)$event['Event']['orgc_id'],
                'distribution' => (int)$event['Event']['distribution'],
                'sharing_group_id' =>
                    (int)$event['Event']['sharing_group_id'],
                'tags' => array(),
                'galaxy_tags' => array(),
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
            if (!isset($meta[$eventId]) || empty($tag['Tag'])) {
                continue;
            }
            /*
             * Kept rather than dropped, for `claimEventTags`' reason
             * one level up: the Tags column does not draw a galaxy tag,
             * so every other reader here filters them out — but the
             * neighbourhood card is made of them, and they are already
             * in this result set. Only the name is kept, because a
             * cluster still has to go through `fetchGalaxyClusters`
             * before it may be shown at all.
             */
            if (!empty($tag['Tag']['is_galaxy'])) {
                $meta[$eventId]['galaxy_tags'][] = $tag['Tag']['name'];
                continue;
            }
            /*
             * Whether the tag left the instance. A local tag is real to
             * this viewer and false to everybody else, and §10.2's
             * table now gives one a row of its own — so the row has to
             * be able to say so rather than reading as shared context.
             */
            $tag['Tag']['local'] = !empty($tag['EventTag']['local']);
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
     * @param array $extra Further ids the caller holds — the events a
     *     label was read on reach past the events the rows came from
     * @return array org id => name
     */
    private function organisationNames(array $rows, array $extra = array())
    {
        $ids = array();
        foreach ($rows as $row) {
            if (!empty($row['Event']['orgc_id'])) {
                $ids[(int)$row['Event']['orgc_id']] = true;
            }
        }
        foreach ($extra as $orgId) {
            if (!empty($orgId)) {
                $ids[(int)$orgId] = true;
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
     * @param array $extra Further distribution levels the caller holds
     *     — a label row's audience is its event's, and labels are read
     *     over events no row came from
     * @return array
     */
    private function sharingGroupNames(array $user, array $rows,
        array $extra = array()
    ) {
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
        if (in_array(4, array_map('intval', $extra), true)) {
            return $this->model('SharingGroup')
                ->fetchAllAuthorised($user, 'name');
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
    private function objectSections(array $user, $value, array $options,
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
        $relations = array();
        foreach ($objects as $objectId => $meta) {
            $relations[$objectId] = $meta['relation'];
        }
        $rows = $valueModel->neighbourRowsFor(
            $user,
            $value,
            array('objects' => array_keys($objects)),
            $options
        );
        $missing = $this->organisationNames($rows);
        $census = $this->objectCensus($user, $value, $options,
            count($objects));
        /*
         * A second warninglist read, over this join's own rows.
         *
         * It cannot reuse the co-occurrence scan's: that one probes the
         * attributes of the *events* the scan read, and these are the
         * attributes of the *objects* the value sits in. An object can
         * survive an event the scan skipped for being oversized — which
         * is the whole reason the sibling table renders under a
         * suppressed band — so a sibling value need not appear in the
         * other probe set at all.
         *
         * What it costs is one query, `assignComments`, and only where
         * something matched. The per-value work is nearly free on the
         * overlap: `attachWarninglistToAttributes` keys its Redis cache
         * on `(type, value)`, so every value both reads share is a
         * cache hit the second time.
         */
        $warninglistModel = $this->model('Warninglist');
        $probes = array();
        foreach ($rows as $row) {
            $probes[] = array(
                'value' => $row['Attribute']['value'],
                'type' => $row['Attribute']['type'],
            );
        }
        /*
         * One context for both folds, because they read the same rows
         * against the same bounds and a second copy would be a second
         * place for the caps to drift apart.
         */
        $context = array(
            'orgs' => $orgs + $missing,
            'relations' => $relations,
            'in_objects' => $census['total'],
            'template_totals' => $census['templates'],
            'cap' => self::SIBLING_OBJECT_CAP,
            'row_cap' => self::RELATION_ROW_CAP,
            'page_size' => self::RELATION_PAGE_SIZE,
            'warninglists' => ValueWarninglistTool::hitsFor(
                $warninglistModel,
                $probes
            ),
            'warninglists_checked' => ValueWarninglistTool::enabledCount(
                $warninglistModel
            ),
            /*
             * Counted in objects, and read from these raw rows rather
             * than from the fold's output: `siblings` ranks and then
             * cuts to `row_cap`, so a denominator arriving after the
             * fold could only re-order the hundred rows that already
             * won — which is the re-rank-a-page contract the tab
             * refuses to take on.
             *
             * Objects rather than events because the sibling row's own
             * count is objects. `paloalto-threat-event · dst ·
             * 0.0.0.0` sits in 5 of `8.8.8.8`'s objects and in 32,922
             * across the instance; that ratio is what moves it off
             * page one, and it has to be the same unit as the number
             * printed beside it.
             */
            'prevalence' => $valueModel->prevalenceFor(
                $user,
                $this->neighbourValues($rows),
                array_merge($options, array('unit' => 'object'))
            ),
        );
        return array(
            'siblings' => ValueRelationTool::siblings($rows, $context),
            'dated' => ValueRelationTool::dated($rows, $context),
            'object_ids' => array_keys($objects),
        );
    }

    /**
     * How many objects this value sits in altogether, and how they
     * divide by template.
     *
     * Asked only when the cap actually bit. Below it the answer is the
     * number of objects already fetched, and a `COUNT(DISTINCT …)` over
     * 32,921 rows to learn a number we are holding would be the exact
     * mistake `occurrenceSummaryFor` was written to stop.
     *
     * **The per-template split is what the roll-up node prints**, and
     * it costs nothing extra: one `GROUP BY Object.name` gives both the
     * breakdown and the total, where the old query gave only the total.
     * Without it `0.0.0.0` would draw `paloalto-threat-event · 500
     * objects` — the cap's number, not the value's — which is the one
     * number a roll-up exists to carry.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @param int $fetched
     * @return array `total`, and `templates` as name => objects
     */
    private function objectCensus(array $user, $value, array $options,
        $fetched
    ) {
        if ($fetched < self::SIBLING_OBJECT_CAP) {
            return array('total' => $fetched, 'templates' => array());
        }
        $attributes = $this->model('MispAttribute');
        $conditions = $attributes->buildConditions($user);
        $conditions['AND'][] = $this->model('Value')
            ->conditionsFor($value, $options);
        $conditions['AND'][] = array('Attribute.object_id >' => 0);
        $rows = $attributes->find('all', array(
            'fields' => array(
                'Object.name',
                'COUNT(DISTINCT Attribute.object_id) AS objects',
            ),
            'conditions' => $conditions,
            'recursive' => -1,
            'contain' => array('Event', 'Object'),
            'group' => array('Object.name'),
        ));
        $templates = array();
        $total = 0;
        foreach ($rows as $row) {
            if (empty($row['Object']['name'])) {
                continue;
            }
            $count = (int)$row[0]['objects'];
            $templates[$row['Object']['name']] = $count;
            $total += $count;
        }
        return array(
            'total' => $total === 0 ? $fetched : $total,
            'templates' => $templates,
        );
    }

    /**
     * Section six: what MISP itself records as related to this value.
     *
     * `ObjectReference` is the only typed, directional relation in MISP
     * that a person wrote and that is not an analyst claim — a
     * `hosted-by`, a `communicates-with`, and on this instance a
     * `Crush` where somebody typed that. It is read here in **both
     * directions and at both depths**:
     *
     *     direct     the reference points at this value's own
     *                attribute (`referenced_type = 0`, 1,142 rows on
     *                the verification instance)
     *     by parent  the reference is between the object this value
     *                sits in and another one (`referenced_type = 1`,
     *                10,191 rows), in either direction
     *
     * **A reference with both ends in this value's own set is
     * dropped.** `18.117.184.102` sits in four `passive-dns` objects
     * and every one of them carries a `hosted-by` pointing back at the
     * bare attribute — the object holding the value saying the value
     * hosts it. That is a re-telling, not a relation to something else,
     * and it would have been eight of the twelve rows.
     *
     * **Its own read, not the co-occurrence scan's.** The scan reads up
     * to 20,000 attribute rows; this is three indexed lookups and a
     * resolve. Hanging it off the scan would make the cheapest section
     * on the tab wait for the most expensive one, which is the same
     * argument `ValuesController` makes for splitting the endpoints.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array As ValueRelationTool::references returns
     */
    private function referenceSection(array $user, $value,
        array $options = array()
    ) {
        if ($this->references !== null && $this->memoValue === $value) {
            return $this->references;
        }
        $this->forget($value);
        $fresh = !empty($options['fresh']);
        $keyOptions = $options;
        unset($keyOptions['fresh']);
        $key = 'misp:value_profile:relation_references:v'
            . self::CACHE_SHAPE . ':' . (int)$user['id']
            . ':' . hash('sha256', $value . '|' . json_encode($keyOptions));
        $redis = null;
        try {
            $redis = RedisTool::init();
        } catch (Exception $e) {
            $redis = null;
        }
        if ($redis !== null && !$fresh) {
            $cached = RedisTool::deserialize(
                RedisTool::decompress($redis->get($key))
            );
            if (!empty($cached)) {
                $this->references = $cached;
                return $cached;
            }
        }
        $section = $this->readReferences($user, $value, $keyOptions);
        if ($redis !== null) {
            $redis->setex(
                $key,
                self::RELATION_SCAN_TTL,
                RedisTool::compress(RedisTool::serialize($section))
            );
        }
        $this->references = $section;
        return $section;
    }

    /**
     * The reference read itself: our two id sets, the reference rows
     * that touch them, and the far ends resolved through the ACL.
     *
     * @param array $user
     * @param string $value
     * @param array $options
     * @return array
     */
    private function readReferences(array $user, $value, array $options)
    {
        $valueModel = $this->model('Value');
        $ourAttributes = $valueModel->occurrenceIdsFor(
            $user,
            $value,
            array_merge($options, array(
                'limit' => self::REFERENCE_OCCURRENCE_CAP,
            ))
        );
        $ourObjects = $valueModel->occurrenceObjectIdsFor(
            $user,
            $value,
            array_merge($options, array(
                'limit' => self::SIBLING_OBJECT_CAP,
            ))
        );
        /*
         * The objects *read*, not the objects the value sits in.
         * `occurrenceObjectIdsFor` is capped at `SIBLING_OBJECT_CAP`
         * and the census that corrects it belongs to the co-occurrence
         * scan, which this section deliberately does not wait for. So
         * the panel says "of the 500 objects read" rather than quoting
         * a total it did not measure.
         */
        $context = array(
            'row_cap' => self::RELATION_ROW_CAP,
            'page_size' => self::RELATION_PAGE_SIZE,
            'read_objects' => count($ourObjects),
            'object_cap' => self::SIBLING_OBJECT_CAP,
            'occurrences' => count($ourAttributes),
        );
        if (empty($ourAttributes) && empty($ourObjects)) {
            return ValueRelationTool::references(array(), $context);
        }

        $branches = array();
        if (!empty($ourAttributes)) {
            $branches[] = array(
                'ObjectReference.referenced_type' => 0,
                'ObjectReference.referenced_id' =>
                    array_keys($ourAttributes),
            );
        }
        if (!empty($ourObjects)) {
            $branches[] = array(
                'ObjectReference.object_id' => array_keys($ourObjects),
            );
            $branches[] = array(
                'ObjectReference.referenced_type' => 1,
                'ObjectReference.referenced_id' => array_keys($ourObjects),
            );
        }
        $refs = $this->model('ObjectReference')->find('all', array(
            'conditions' => array(
                'ObjectReference.deleted' => 0,
                'OR' => $branches,
            ),
            'recursive' => -1,
            'order' => array('ObjectReference.id DESC'),
            'limit' => self::REFERENCE_ROW_CAP,
        ));

        $edges = array();
        $wantObjects = array();
        $wantAttributes = array();
        $carrying = array();
        foreach ($refs as $ref) {
            $row = $ref['ObjectReference'];
            $source = (int)$row['object_id'];
            $target = (int)$row['referenced_id'];
            $targetIsObject = (int)$row['referenced_type'] === 1;
            $sourceIsOurs = isset($ourObjects[$source]);
            $targetIsOurs = $targetIsObject
                ? isset($ourObjects[$target])
                : isset($ourAttributes[$target]);
            if ($sourceIsOurs) {
                $carrying[$source] = true;
            }
            if ($targetIsOurs && $targetIsObject) {
                $carrying[$target] = true;
            }
            if ($sourceIsOurs && $targetIsOurs) {
                // Both ends are this value's own. See the docblock.
                continue;
            }
            if ($sourceIsOurs) {
                $edges[] = array(
                    'row' => $row,
                    'direction' => 'outbound',
                    'near' => array('kind' => 'object', 'id' => $source),
                    'far' => array(
                        'kind' => $targetIsObject ? 'object' : 'attribute',
                        'id' => $target,
                    ),
                );
            } elseif ($targetIsOurs) {
                $edges[] = array(
                    'row' => $row,
                    'direction' => 'inbound',
                    'near' => array(
                        'kind' => $targetIsObject ? 'object' : 'attribute',
                        'id' => $target,
                    ),
                    'far' => array('kind' => 'object', 'id' => $source),
                );
            } else {
                continue;
            }
            $end = end($edges);
            if ($end['far']['kind'] === 'object') {
                $wantObjects[$end['far']['id']] = true;
            } else {
                $wantAttributes[$end['far']['id']] = true;
            }
            /*
             * The near object too, for its template. A row that says
             * *through the `passive-dns` object this value sits in* is
             * telling the reader which of several parents carried the
             * reference, which they cannot work out from the far end.
             */
            if ($end['near']['kind'] === 'object') {
                $wantObjects[$end['near']['id']] = true;
            }
        }
        $context['with_references'] = count($carrying);
        if (empty($edges)) {
            return ValueRelationTool::references(array(), $context);
        }

        $faces = $this->referenceFaces(
            $user,
            array_keys($wantObjects),
            array_keys($wantAttributes)
        );
        $rows = array();
        foreach ($edges as $edge) {
            $far = $this->referenceFace($faces, $edge['far']);
            if ($far === null) {
                // Nothing this reader may see at the far end, so there
                // is no row — not a row saying one was withheld.
                continue;
            }
            $near = $edge['near']['kind'] === 'object'
                && isset($ourObjects[$edge['near']['id']])
                ? array(
                    'kind' => 'object',
                    'object' => isset($faces['objects']
                        [$edge['near']['id']]['object'])
                        ? $faces['objects'][$edge['near']['id']]['object']
                        : null,
                    'relation' => $ourObjects[$edge['near']['id']]
                        ['relation'],
                )
                : array('kind' => 'attribute', 'object' => null,
                    'relation' => '');
            $rows[] = array(
                'relationship' => empty($edge['row']['relationship_type'])
                    ? __('unnamed')
                    : $edge['row']['relationship_type'],
                'named' => !empty($edge['row']['relationship_type']),
                'direction' => $edge['direction'],
                'comment' => empty($edge['row']['comment'])
                    ? ''
                    : $edge['row']['comment'],
                'near' => $near,
                'far' => $far,
                'event' => (int)$edge['row']['event_id'],
            );
        }
        return ValueRelationTool::references($rows, $context);
    }

    /**
     * Both far-end kinds resolved in one ACL-filtered read, keyed the
     * two ways the caller looks them up.
     *
     * The near end is resolved by the same query where it happens to be
     * an object we already asked about — a reference between two of
     * this value's own objects is dropped, so the only near objects
     * that survive are ones whose template we still want to print.
     *
     * @param array $user
     * @param array $objectIds
     * @param array $attributeIds
     * @return array `objects` and `attributes`, keyed by id
     */
    private function referenceFaces(array $user, array $objectIds,
        array $attributeIds
    ) {
        $rows = $this->model('Value')->referenceFacesFor(
            $user,
            $objectIds,
            $attributeIds,
            self::RELATION_ROW_CAP * self::REFERENCE_FACE_CAP
        );
        $objects = array();
        $attributes = array();
        foreach ($rows as $row) {
            $attribute = $row['Attribute'];
            $id = (int)$attribute['id'];
            $value = isset($attribute['value']) ? $attribute['value'] : '';
            if (in_array($id, $attributeIds, true)) {
                $attributes[$id] = array(
                    'kind' => 'attribute',
                    'object' => empty($row['Object']['name'])
                        ? null
                        : $row['Object']['name'],
                    'label' => $value,
                    'values' => array(array(
                        'value' => $value,
                        'relation' => empty($attribute['object_relation'])
                            ? ''
                            : $attribute['object_relation'],
                        'type' => $attribute['type'],
                    )),
                    'event' => (int)$attribute['event_id'],
                    'id' => $id,
                );
            }
            $objectId = (int)$attribute['object_id'];
            if ($objectId === 0 || !in_array($objectId, $objectIds, true)) {
                continue;
            }
            if (!isset($objects[$objectId])) {
                $objects[$objectId] = array(
                    'kind' => 'object',
                    'object' => empty($row['Object']['name'])
                        ? null
                        : $row['Object']['name'],
                    'label' => '',
                    'values' => array(),
                    'event' => (int)$attribute['event_id'],
                    'id' => $objectId,
                );
            }
            if (count($objects[$objectId]['values'])
                < self::REFERENCE_FACE_CAP
            ) {
                $objects[$objectId]['values'][] = array(
                    'value' => $value,
                    'relation' => empty($attribute['object_relation'])
                        ? ''
                        : $attribute['object_relation'],
                    'type' => $attribute['type'],
                );
            }
        }
        foreach ($objects as $objectId => $object) {
            $labels = array();
            foreach ($object['values'] as $entry) {
                $labels[] = $entry['value'];
            }
            $objects[$objectId]['label'] = implode(' · ', $labels);
        }
        return array('objects' => $objects, 'attributes' => $attributes);
    }

    /**
     * @param array $faces
     * @param array $end `kind` and `id`
     * @return array|null
     */
    private function referenceFace(array $faces, array $end)
    {
        $bucket = $end['kind'] === 'object' ? 'objects' : 'attributes';
        return isset($faces[$bucket][$end['id']])
            ? $faces[$bucket][$end['id']]
            : null;
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
        /*
         * Fixed order, whatever each engine's state is. §4.1 weighed
         * reordering by state — with ssdeep active, CIDR's idle line
         * sits above the block that ran — and kept the stable position
         * of each engine, which is worth more now that there are four.
         *
         * The typosquat engine does **not** replace the absent tree
         * line, which is what §12 planned. §12.1 priced the tree and
         * found it is two engines rather than one: a parent lookup at
         * 10–51 ms that MISP genuinely has no code path for, and a
         * child lookup at 4,533 ms that only a schema change makes
         * affordable. A permutation engine does not make a
         * parent-domain relation exist, so dropping the line would
         * quietly close a gap this section draws on purpose.
         */
        $engines = array(
            $this->cidrEngine($user, $value, $names),
            $this->ssdeepEngine($user, $value, $names),
            $this->typosquatEngine($user, $value, $names),
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
     * tests against — Redis-cached, 53 entries on the verification
     * instance — so the containment answer here is the engine's own
     * rather than an approximation of it. The blocks are then fetched
     * as attributes so the rows carry an event, a reporter and a
     * distribution the viewer may actually see.
     *
     * Two queries at most: the list (usually Redis, one query on a
     * cold cache) and one fetch for whichever blocks contained us.
     *
     * That Redis set is rebuilt only by
     * `Correlation::advancedCorrelationsUpdate` on an attribute save, so
     * a block inserted outside the save path is invisible to the engine
     * and to this panel alike until `updateCidrList()` runs. Reporting
     * *found nothing* there is the engine's answer, not a wrong one.
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
     * **The record the matched value sits in**, too, and it costs no
     * query: `Value::CONTEXT_FIELDS` already contains `Object.id` and
     * `Object.name`, and both near-match queries already select
     * `Attribute.id` and `Attribute.object_id`. Without them the row
     * named an event and left the reader to find the attribute in it,
     * which on a `/8` in a 300-attribute event is a search.
     *
     * For CIDR this is *an* occurrence of the block rather than the only
     * one — the engine folds to one row per block on purpose — and it is
     * the same occurrence whose event, reporter and audience the rest of
     * the row already reports.
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
        ) + $this->matchContext($row);
    }

    /**
     * Where a matched occurrence sits, who reported it and who may see
     * it — the half of a near-match row that has nothing to do with
     * how the match was made.
     *
     * Split out because the typosquat engine's closeness is a
     * *permutation class* and not a number: it cannot fill `prefix`,
     * `addresses` or `width`, and filling them with zeroes to reuse
     * `nearRow` would hand the template a row whose bar renders 0% and
     * whose `Similarity` filter silently drops it. Two row shapes over
     * one context, rather than one row shape carrying three fields
     * that mean nothing on a third of its rows.
     *
     * @param array $row
     * @return array
     */
    private function matchContext(array $row)
    {
        $object = empty($row['Object']['id'])
            ? null
            : (int)$row['Object']['id'];
        return array(
            'event' => (int)$row['Attribute']['event_id'],
            'attribute' => (int)$row['Attribute']['id'],
            'object' => $object,
            'object_name' => $object === null
                ? null
                : $row['Object']['name'],
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
        /*
         * **Compare first, decorate second.** The engine used to fetch
         * a hundred decorated rows and compare against those, so the
         * candidate set was chosen by `timestamp DESC` before a single
         * comparison had happened — a value whose partner was reported
         * last year was told, in the panel's own words, that it had
         * been compared against every `ssdeep` attribute and matched
         * none of them. Comparing is the cheap half: 793,170
         * comparisons across this whole instance take 398 ms, while
         * fetching the same rows decorated takes 34.6 ms for 1,399 of
         * them. So the whole population is compared, and only the
         * survivors are fetched with the context a row needs.
         */
        $candidates = $this->model('Value')->valuesOfType(
            $user,
            'ssdeep',
            $value,
            self::SSDEEP_CANDIDATE_CAP
        );
        $scores = array();
        foreach ($candidates['values'] as $candidate) {
            $score = @ssdeep_fuzzy_compare($value, $candidate);
            if ($score === false || $score < $threshold) {
                continue;
            }
            $scores[$candidate] = (int)$score;
        }
        $rows = array();
        if (!empty($scores)) {
            /*
             * Sized to the match set, not left on the default 200.
             * This fetch exists to put an event, a reporter and an
             * audience beside each matched hash, and it orders by
             * timestamp — so a hash held in three hundred events could
             * fill the window on its own and push another matched hash
             * out of it entirely. That would drop a row the engine had
             * already decided to show, which is the failure this task
             * is about. Twenty occurrences per match is generous
             * against a measured worst case of 45 matches; whatever
             * still fails to place is counted and reported below
             * rather than quietly missing.
             */
            $matches = $this->model('Value')->occurrencesForAny(
                $user,
                array_map('strval', array_keys($scores)),
                array(
                    'types' => array('ssdeep'),
                    'limit' => min(
                        self::SSDEEP_CANDIDATE_CAP,
                        max(200, count($scores) * 20)
                    ),
                )
            );
            /*
             * **One row per matched hash, not per occurrence of it.**
             * A pair is two values, so a hash held in three events is
             * one pair and not three — and the block above the table
             * counts pairs. Folding here is what makes that sentence
             * true, and it is the same fold `cidrEngine` performs for
             * the same reason: what the row names is the match, and
             * the event beside it is *an* occurrence of the far end
             * rather than the only one.
             */
            $seen = array();
            foreach ($this->decorate($user, $matches) as $match) {
                $matched = $match['Attribute']['value'];
                if (!isset($scores[$matched]) || isset($seen[$matched])) {
                    continue;
                }
                $seen[$matched] = true;
                $rows[] = $this->nearRow(
                    $match,
                    $matched,
                    $scores[$matched],
                    null,
                    100
                );
            }
        }
        usort($rows, function ($a, $b) {
            return $b['prefix'] - $a['prefix'];
        });
        return array(
            'id' => 'ssdeep',
            'state' => 'active',
            'rows' => $rows,
            'compared' => count($candidates['values']),
            'matched' => count($scores),
            'unplaced' => count($scores) - count($rows),
            'saturated' => $candidates['saturated'],
            'cap' => self::SSDEEP_CANDIDATE_CAP,
        );
    }

    /**
     * Spellings of this domain that somebody could mistake for it, and
     * which of them already exist on this instance.
     *
     * The fourth engine, and the only one that makes a claim MISP's
     * correlation engine never makes. That is deliberate and it is the
     * section's own contract rather than a stretch of it: a near-match
     * is *not equality*, every row names the engine that produced it,
     * and the panel says so above the rows. `careflrst.com` and
     * `caref1rst.com` are two unrelated attributes as far as
     * `default_correlations` is concerned, and one is a homoglyph of
     * the other; the section exists to say the second thing.
     *
     * **Generation is `DomainPermutationTool`'s and the check is
     * ours** — the split the tool's docblock argues for. Generation is
     * pure string work over the label, 0.2 ms for a typical name; the
     * check is one indexed `IN` over `value1`/`value2`, 7.3 ms mean
     * over 25 real values, which is a fifth of what this section
     * already costs. `24b-relationships.md` §12.1 measured both, and
     * measured the alternatives that are not here: matching inside
     * `url` values is a substring scan at 1,090 ms a candidate, so
     * `url` is not in the type list below.
     *
     * **The fetch is capped and the panel says so.** `occurrencesForAny`
     * orders by `Attribute.timestamp DESC`, so a saturated fetch means
     * *the most recent* occurrences, and a look-alike seen only in
     * older events would be missing without a word — which is §4.2's
     * finding about the ssdeep candidate cap, and the reason this one
     * is reported rather than merely bounded.
     *
     * @param array $user
     * @param string $value
     * @param array $types Type name => true
     * @return array
     */
    private function typosquatEngine(array $user, $value, array $types)
    {
        $domainTypes = array('domain', 'hostname', 'domain|ip');
        if (empty(array_intersect($domainTypes, array_keys($types)))) {
            return array(
                'id' => 'typosquat',
                'state' => 'not_applicable',
                'rows' => array(),
                'candidates' => 0,
            );
        }
        $candidates = DomainPermutationTool::candidates($value);
        if (empty($candidates)) {
            /*
             * The type says domain and the value cannot carry a
             * spelling — no dot, or already at the length limit. The
             * engine applies and generated nothing, which is a
             * different sentence from *found nothing* and the panel
             * draws it as one.
             */
            return array(
                'id' => 'typosquat',
                'state' => 'active',
                'rows' => array(),
                'candidates' => 0,
                'classes' => DomainPermutationTool::CLASSES,
                'saturated' => false,
                'cap' => self::TYPOSQUAT_FETCH_CAP,
            );
        }
        /*
         * `array_map('strval', ...)` for the reason §8.1 records: an
         * array key that looks like an integer comes back from
         * `array_keys()` as one, reaches the database bound as an
         * integer, and makes MariaDB convert the column and abandon
         * the `value1` index. A domain rarely looks numeric, `123.com`
         * does not — but `4.com` is a hostname somebody registered and
         * this generator emits its neighbours.
         */
        $rows = $this->model('Value')->occurrencesForAny(
            $user,
            array_map('strval', array_keys($candidates)),
            array(
                'types' => $domainTypes,
                'limit' => self::TYPOSQUAT_FETCH_CAP,
            )
        );
        $saturated = count($rows) >= self::TYPOSQUAT_FETCH_CAP;
        $order = array_flip(DomainPermutationTool::CLASSES);
        $out = array();
        $seen = array();
        foreach ($this->decorate($user, $rows) as $row) {
            /*
             * `domain|ip` stores the domain in `value1` and the address
             * in `value2`, and the row's `value` is the composite. The
             * look-alike is the domain half.
             */
            $matched = $row['Attribute']['value'];
            if (strpos($matched, '|') !== false) {
                $matched = explode('|', $matched)[0];
            }
            $matched = strtolower($matched);
            if (!isset($candidates[$matched]) || isset($seen[$matched])) {
                continue;
            }
            $seen[$matched] = true;
            $out[] = array(
                'block' => $matched,
                'class' => $candidates[$matched],
            ) + $this->matchContext($row);
        }
        usort($out, function ($a, $b) use ($order) {
            $byClass = $order[$a['class']] - $order[$b['class']];
            return $byClass !== 0
                ? $byClass
                : strcmp($a['block'], $b['block']);
        });
        return array(
            'id' => 'typosquat',
            'state' => 'active',
            'rows' => $out,
            'candidates' => count($candidates),
            'classes' => DomainPermutationTool::CLASSES,
            'saturated' => $saturated,
            'cap' => self::TYPOSQUAT_FETCH_CAP,
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
        /*
         * The three things a claim about this value can be anchored
         * on, by uuid: the occurrence itself, the event it is in, or
         * the object it sits in.
         *
         * **Attribute alone was too narrow, and the omission showed.**
         * A claim written on an event — *event 4074 is linked-to
         * APT1* — is a claim about a neighbourhood this value is part
         * of, and the tab already counts a plain galaxy *tag* on that
         * same event. Counting the tag and dropping the claim ranked a
         * label above a deliberate, authored, typed statement. The
         * parent object is the same argument one level tighter: a
         * claim about the `domain-ip` object this address sits in is
         * about the thing the address is part of.
         *
         * The containers cost no query — `occurrenceUuidsFor` already
         * joins `Event` and `Object` to read them.
         */
        $near = array(
            'Attribute' => $occurrences,
            'Event' => array(),
            'Object' => array(),
        );
        foreach ($occurrences as $occurrence) {
            if (!empty($occurrence['event_uuid'])) {
                $near['Event'][$occurrence['event_uuid']] = true;
            }
            if (!empty($occurrence['object_uuid'])) {
                $near['Object'][$occurrence['object_uuid']] = true;
            }
        }

        $claims = array();
        $orgs = array();
        if (!empty($occurrences)) {
            $relationships = $this->model('Relationship');
            $conditions = $relationships->buildConditions($user);
            /*
             * Six branches rather than two, and still one query: a
             * claim names its ends by uuid and type, so each kind of
             * anchor is one equality pair per direction.
             */
            $anchors = array();
            foreach ($near as $kind => $set) {
                if (empty($set)) {
                    continue;
                }
                $uuids = array_keys($set);
                $anchors[] = array(
                    'Relationship.object_type' => $kind,
                    'Relationship.object_uuid' => $uuids,
                );
                $anchors[] = array(
                    'Relationship.related_object_type' => $kind,
                    'Relationship.related_object_uuid' => $uuids,
                );
            }
            $conditions['AND'][] = array('OR' => $anchors);
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
                $claim = $this->claimFrom($user, $row, $near);
                if ($claim === null) {
                    continue;
                }
                $claims[$row['Relationship']['uuid']] = $claim;
                $orgs[$claim['org']] = true;
            }
            $claims = $this->resolveClaimTargets($user, $claims);
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
     * **Either end may be an occurrence, its event or its object.** The
     * near end is whichever of the two the caller nominated, and it is
     * matched on the pair — type *and* uuid — so an `Event` uuid can
     * never be mistaken for an `Attribute` one.
     *
     * @param array $user
     * @param array $row
     * @param array $near kind => uuid => anything, from `assertedClaims`
     * @return array|null
     */
    private function claimFrom(array $user, array $row, array $near)
    {
        $relationship = $row['Relationship'];
        $isNear = function ($kind, $uuid) use ($near) {
            return isset($near[$kind]) && isset($near[$kind][$uuid]);
        };
        $outbound = $isNear(
            $relationship['object_type'],
            $relationship['object_uuid']
        );
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
        } elseif ($isNear(
            $relationship['related_object_type'],
            $relationship['related_object_uuid']
        )) {
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
        $author = self::claimOrg($relationship, 'Orgc');
        /*
         * Owner and creator are separate columns and are the same row
         * on an instance nothing has synced into. Compared on the
         * uuids rather than on the two contained names, because that
         * is the comparison the schema actually stores; the second
         * name is dropped when it says nothing, so the meta line grows
         * only where the two really differ.
         */
        $owner = $relationship['org_uuid'] === $relationship['orgc_uuid']
            ? null
            : self::claimOrg($relationship, 'Org');
        return array(
            'relationship_type' => empty($relationship['relationship_type'])
                ? __('related-to')
                : $relationship['relationship_type'],
            'direction' => $outbound ? 'outbound' : 'inbound',
            /*
             * Which of this value's three anchors the claim was
             * written on — the occurrence, its event, or its object.
             * The near end by construction, so it is read off
             * whichever column the direction says.
             */
            'anchor' => $outbound
                ? $relationship['object_type']
                : $relationship['related_object_type'],
            /*
             * The far end as fetched, not as rendered.
             * `resolveClaimTargets` turns it into the display shape
             * once the whole list is known, so the two lookups it
             * still needs are one query each rather than one per
             * claim.
             */
            'target' => array(
                'kind' => $kind,
                'uuid' => $uuid,
                'element' => $target,
            ),
            /*
             * There is no prose to render. Left as an empty string
             * rather than a placeholder sentence, so the block draws
             * nothing where the fixture drew a paragraph and the panel
             * explains the absence once, at the foot, instead of on
             * every claim.
             */
            'text' => '',
            'org' => $author === null
                ? __('Unknown organisation')
                : $author['name'],
            // Null is *no link*, not *no name*: a claim whose author
            // organisation did not resolve still says who it credits.
            'org_id' => $author === null ? null : $author['id'],
            'owner' => $owner === null ? null : $owner['name'],
            'owner_id' => $owner === null ? null : $owner['id'],
            'date' => substr($relationship['modified'], 0, 10),
            'distribution' => (int)$relationship['distribution'],
        );
    }

    /**
     * One organisation off a claim, as a name and something to link.
     *
     * **Nested inside the row, not beside it.**
     * `AnalystData::rearrangeOrganisation` moves a contained `Orgc`
     * under the record and unsets the top-level key, so the obvious
     * read finds nothing and every claim silently reports *Unknown
     * organisation*.
     *
     * @param array $relationship
     * @param string $key `Org` or `Orgc`
     * @return array|null id and name, or null when it did not resolve
     */
    private static function claimOrg(array $relationship, $key)
    {
        if (empty($relationship[$key]['name'])) {
            return null;
        }
        return array(
            'id' => empty($relationship[$key]['id'])
                ? null
                : (int)$relationship[$key]['id'],
            'name' => $relationship[$key]['name'],
        );
    }

    /**
     * Fill in what each claim's far end actually is.
     *
     * `claimFrom` stops at whatever `getRelatedElement` handed back,
     * because two of the things a target line wants are not on it and
     * both are cheaper asked once for the whole list than once per
     * claim: a galaxy cluster, which `getRelatedElement` does not
     * resolve at all, and the creator organisation of an event target,
     * which arrives as a bare `orgc_id` and no name.
     *
     * Three queries for the section, each skipped when nothing needs it
     * — the same trade `assertedClaims` already makes by containing the
     * authors rather than letting `afterFind` fetch them per row. The
     * tags come first because the galaxy ones among them name clusters,
     * and those resolve in the same fetch as the clusters a claim
     * points *at* rather than in a second one.
     *
     * @param array $user
     * @param array $claims Keyed by relationship UUID
     * @return array The same claims, targets in display shape
     */
    private function resolveClaimTargets(array $user, array $claims)
    {
        $tags = $this->claimEventTags($claims);
        $clusters = $this->claimClusters($user, $claims,
            array_keys($tags['galaxy']));
        foreach ($claims as $key => $claim) {
            $target = $claim['target'];
            if ($target['kind'] === 'GalaxyCluster'
                && isset($clusters['by_uuid'][$target['uuid']])
            ) {
                $claims[$key]['target']['element']
                    = $clusters['by_uuid'][$target['uuid']];
            }
        }
        $lookups = array(
            'orgs' => $this->claimTargetOrgs($claims),
            'tags' => $tags['plain'],
            'galaxy_by_event' => $tags['galaxy_by_event'],
            'clusters' => $clusters['by_tag'],
        );
        foreach ($claims as $key => $claim) {
            $claims[$key]['target'] = $this->claimTarget(
                $claim['target']['kind'],
                $claim['target']['uuid'],
                $claim['target']['element'],
                $lookups
            );
        }
        return $claims;
    }

    /**
     * Every event a claim's far end sits in, tagged, in one fetch.
     *
     * An event target *is* the event; an attribute or object target
     * carries its own. Both are wanted, because a reader deciding
     * whether a claim matters wants what the event was labelled with —
     * and on this page the label is the tag.
     *
     * **Galaxy tags come back separately rather than being dropped.**
     * Everywhere else on this page they are filtered out, because the
     * Tags column does not draw them and a facet on something invisible
     * is not a facet. Here they are the point: a cluster is what an
     * analyst reaching for context is looking for, and the tag name is
     * how the event stores it.
     *
     * @param array $claims
     * @return array plain => event id => tag rows, galaxy => name => true
     */
    private function claimEventTags(array $claims)
    {
        $eventIds = array();
        foreach ($claims as $claim) {
            $id = self::claimEventId($claim['target']);
            if ($id !== null) {
                $eventIds[$id] = true;
            }
        }
        $found = array(
            'plain' => array(),
            'galaxy' => array(),
            'galaxy_by_event' => array(),
        );
        if (empty($eventIds)) {
            return $found;
        }
        $rows = $this->model('EventTag')->find('all', array(
            'conditions' => array(
                'EventTag.event_id' => array_keys($eventIds),
            ),
            'recursive' => -1,
            'contain' => array('Tag' => array('fields' => array(
                'Tag.id', 'Tag.name', 'Tag.colour', 'Tag.is_galaxy',
            ))),
        ));
        foreach ($rows as $row) {
            if (empty($row['Tag']['name'])) {
                continue;
            }
            $eventId = (int)$row['EventTag']['event_id'];
            if (empty($row['Tag']['is_galaxy'])) {
                $found['plain'][$eventId][] = $row['Tag'];
                continue;
            }
            $found['galaxy'][$row['Tag']['name']] = true;
            $found['plain'][$eventId] = isset($found['plain'][$eventId])
                ? $found['plain'][$eventId]
                : array();
        }
        /*
         * Which event each galaxy tag was on, kept beside the names so
         * the fetch above stays one query and the card can still say
         * *this* event's clusters rather than the section's.
         */
        foreach ($rows as $row) {
            if (empty($row['Tag']['is_galaxy'])) {
                continue;
            }
            $eventId = (int)$row['EventTag']['event_id'];
            $found['galaxy_by_event'][$eventId][] = $row['Tag']['name'];
        }
        return $found;
    }

    /**
     * The event id a target sits in, or is.
     *
     * @param array $target As claimFrom left it, element and all
     * @return int|null
     */
    private static function claimEventId(array $target)
    {
        $element = $target['element'];
        $kind = $target['kind'];
        if ($kind === 'Event' && !empty($element['Event']['id'])) {
            return (int)$element['Event']['id'];
        }
        if (!empty($element[$kind]['Event']['id'])) {
            return (int)$element[$kind]['Event']['id'];
        }
        return null;
    }

    /**
     * The galaxy clusters this section's claims point at, in one fetch.
     *
     * `Relationship::getRelatedElement` handles Event, Attribute,
     * Object, Note, Opinion and Relationship and stops there, while
     * `AnalystData::valid_targets` allows six more — so a cluster
     * target used to render as a bare UUID with nowhere to go, and the
     * one such claim on the verification instance still does, because
     * the cluster it names is not stored here.
     *
     * `fetchGalaxyClusters` is the reader the galaxy pages themselves
     * use, so a cluster the viewer may not see stays unresolved rather
     * than appearing.
     *
     * **Two callers, one query.** A cluster a claim points at is looked
     * up by UUID; a cluster an event is tagged with is looked up by the
     * tag name that stores it. Both are `GalaxyCluster` rows under the
     * same ACL, so they are one `OR` rather than two round trips.
     *
     * @param array $user
     * @param array $claims
     * @param array $tagNames Galaxy tag names found on the events
     * @return array by_uuid => uuid => row, by_tag => tag name => row
     */
    private function claimClusters(array $user, array $claims,
        array $tagNames = array()
    ) {
        $uuids = array();
        foreach ($claims as $claim) {
            if ($claim['target']['kind'] === 'GalaxyCluster') {
                $uuids[$claim['target']['uuid']] = true;
            }
        }
        $found = array('by_uuid' => array(), 'by_tag' => array());
        $or = array();
        if (!empty($uuids)) {
            $or['GalaxyCluster.uuid'] = array_keys($uuids);
        }
        if (!empty($tagNames)) {
            $or['GalaxyCluster.tag_name'] = $tagNames;
        }
        // An empty `IN ()` is not a query worth sending.
        if (empty($or)) {
            return $found;
        }
        $rows = $this->model('GalaxyCluster')->fetchGalaxyClusters(
            $user,
            array('conditions' => array('OR' => $or))
        );
        foreach ($rows as $row) {
            $found['by_uuid'][$row['GalaxyCluster']['uuid']] = $row;
            if (!empty($row['GalaxyCluster']['tag_name'])) {
                $found['by_tag'][$row['GalaxyCluster']['tag_name']] = $row;
            }
        }
        return $found;
    }

    /**
     * Names for the organisation ids a target carries but cannot name.
     *
     * An `Attribute` or `Object` target arrives with its event's
     * creator organisation already nested — `Relationship::rearrangeData`
     * puts it there — while an `Event` target is fetched with no
     * contain at all and a cluster with only its galaxy. Those two hold
     * an id and nothing to print, and the id is what this fills in.
     *
     * @param array $claims
     * @return array id => name
     */
    private function claimTargetOrgs(array $claims)
    {
        $ids = array();
        foreach ($claims as $claim) {
            $kind = $claim['target']['kind'];
            $element = $claim['target']['element'];
            if (empty($element[$kind]['orgc_id'])) {
                // Also the galaxy clusters MISP ships, which are
                // nobody's: `orgc_id` 0 is not an organisation.
                continue;
            }
            $ids[(int)$element[$kind]['orgc_id']] = true;
        }
        if (empty($ids)) {
            return array();
        }
        return $this->model('Organisation')->find('list', array(
            'conditions' => array('Organisation.id' => array_keys($ids)),
            'fields' => array('Organisation.id', 'Organisation.name'),
            'recursive' => -1,
        ));
    }

    /**
     * The far end of a claim, as the panel draws it.
     *
     * Four kinds, four different things worth knowing, one shape: a
     * label, an id the view turns into a link, the event the target
     * lives in when it lives in one, the organisation behind it, and a
     * short list of facts that are only true of that kind.
     *
     * **A target that does not resolve keeps its UUID**, and that is
     * not a fallback nobody will hit — the verification instance has a
     * claim naming a galaxy cluster this instance does not hold. Then
     * `resolved` is false, the view gives it no link and says why: a
     * claim about something that cannot be shown is still a claim
     * somebody made, and dropping the row would hide it.
     *
     * @param string $kind
     * @param string $uuid
     * @param array $element As getRelatedElement returns it
     * @param array $lookups orgs, tags and clusters for the whole list
     * @return array
     */
    private function claimTarget($kind, $uuid, array $element,
        array $lookups
    ) {
        $target = array(
            'kind' => $kind,
            'uuid' => $uuid,
            'id' => null,
            'label' => $uuid,
            'event' => null,
            'org' => null,
            'facts' => array(),
            /*
             * Kind-specific rows for the hover, as label/value pairs.
             * Every one of them is a column already on the row that was
             * fetched — nothing here is worth a second query, and a
             * detail that would need one belongs on the page it lives
             * on rather than in a tooltip.
             */
            'detail' => array(),
            'distribution' => null,
            'tags' => array(),
            'clusters' => array(),
            'resolved' => false,
        );
        if (empty($element[$kind]['id'])) {
            return $target;
        }
        $row = $element[$kind];
        $target['id'] = (int)$row['id'];
        $target['org'] = self::claimTargetOrg($row, $lookups['orgs']);
        $target['resolved'] = true;
        if (isset($row['distribution'])) {
            $target['distribution'] = (int)$row['distribution'];
        }
        if ($kind === 'Event') {
            $target['label'] = sprintf('#%s %s', $row['id'], $row['info']);
            $target['facts'] = array(
                $row['date'],
                /*
                 * Said only when it is true. An unpublished event has
                 * not left this instance, so a claim pointing at one
                 * points somewhere nobody else can follow — and the
                 * silence in the other direction is what *published*
                 * means, which needs no word.
                 */
                empty($row['published']) ? __('unpublished') : '',
            );
            /*
             * The target *is* the event, so the event's own card rows
             * are the target's. An attribute or object target gets the
             * same shape one section below it, built by the same call —
             * what this panel says about an event should not depend on
             * how the reader arrived at it.
             */
            $event = $this->claimEventFacts($row, $lookups);
            $target['detail'] = $event['detail'];
            $target['tags'] = $event['tags'];
            $target['clusters'] = $event['clusters'];
        } elseif ($kind === 'Attribute') {
            $target['label'] = sprintf('%s · %s', $row['type'],
                $row['value']);
            $target['event'] = $this->claimTargetEvent($row, $lookups);
            $target['facts'] = array($row['category']);
            if (!empty($row['Object']['name'])) {
                // Where in the object, not just which object: an
                // attribute's meaning inside one is its relation.
                $target['facts'][] = sprintf('%s ↦ %s',
                    $row['Object']['name'], $row['object_relation']);
            }
            // No `Type` row: the label above the card is `type · value`,
            // so it would print the first half of it back.
            $target['detail'] = array(
                __('Category') => $row['category'],
                __('IDS flag') => empty($row['to_ids'])
                    ? __('not set')
                    : __('set'),
                __('Comment') => $row['comment'],
                __('First seen') => self::claimSeen($row, 'first_seen'),
                __('Last seen') => self::claimSeen($row, 'last_seen'),
            );
        } elseif ($kind === 'Object') {
            $target['label'] = sprintf('%s · #%s', $row['name'],
                $row['id']);
            $target['event'] = $this->claimTargetEvent($row, $lookups);
            $target['facts'] = array(
                empty($row['meta-category']) ? '' : $row['meta-category'],
            );
            $target['detail'] = array(
                __('Template') => sprintf('%s v%s', $row['name'],
                    $row['template_version']),
                __('Category') => $row['meta-category'],
                __('Comment') => $row['comment'],
                __('First seen') => self::claimSeen($row, 'first_seen'),
                __('Last seen') => self::claimSeen($row, 'last_seen'),
            );
        } elseif ($kind === 'GalaxyCluster') {
            $target['label'] = $row['value'];
            $target['facts'] = array(
                empty($row['Galaxy']['name'])
                    ? $row['type']
                    : $row['Galaxy']['name'],
                empty($row['source']) ? '' : $row['source'],
            );
            $target['detail'] = array(
                __('Tag') => $row['tag_name'],
                __('Description') => self::claimClip($row['description']),
            );
        } else {
            /*
             * A kind this panel has no link and no facts for. It keeps
             * its UUID rather than being drawn as something it is not.
             */
            $target['id'] = null;
            $target['org'] = null;
            $target['distribution'] = null;
            $target['resolved'] = false;
        }
        $target['facts'] = array_values(array_filter($target['facts']));
        // An empty column is not a row. A blank comment would otherwise
        // draw a label with nothing beside it on most attributes.
        $target['detail'] = array_filter($target['detail'], 'strlen');
        return $target;
    }

    /**
     * Prose cut to something a hover card can hold.
     *
     * @param string|null $text
     * @return string
     */
    private static function claimClip($text)
    {
        if (empty($text)) {
            return '';
        }
        if (mb_strlen($text) <= self::CLAIM_PROSE_CAP) {
            return $text;
        }
        return mb_substr($text, 0, self::CLAIM_PROSE_CAP - 1) . '…';
    }

    /**
     * The event a target lives in, when it lives in one.
     *
     * Free apart from its tags: `Relationship::getRelatedElement`
     * contains `Event` for both an Attribute and an Object target, so
     * the whole row is already here.
     *
     * @param array $row An Attribute or Object, as rearranged
     * @param array $lookups
     * @return array|null
     */
    private function claimTargetEvent(array $row, array $lookups)
    {
        if (empty($row['Event']['id'])) {
            return null;
        }
        $event = $this->claimEventFacts($row['Event'], $lookups);
        return array_merge($event, array(
            'id' => (int)$row['Event']['id'],
            'info' => $row['Event']['info'],
        ));
    }

    /**
     * What this panel says about an event, wherever it is drawn.
     *
     * One definition for the two places an event reaches the card — as
     * a claim's target, and as the event an attribute or object target
     * sits in. Two definitions would drift, and the reader would be
     * told different things about the same event depending on which
     * claim they hovered.
     *
     * @param array $row An `Event` row
     * @param array $lookups tags and clusters, keyed for the section
     * @return array detail, distribution, uuid, tags, clusters
     */
    private function claimEventFacts(array $row, array $lookups)
    {
        $id = (int)$row['id'];
        $analysis = $this->model('Event')->analysisLevels;
        $level = (int)$row['analysis'];
        /*
         * `attribute_count` is denormalised and can lag, and it is
         * still the right number to print: it is what MISP's own event
         * index shows for the same event, so a reader who follows the
         * link and counts something else has found a discrepancy in the
         * instance rather than in this card.
         */
        $facts = array(
            'detail' => array(
                __('Date') => $row['date'],
                __('Analysis') => isset($analysis[$level])
                    ? __($analysis[$level])
                    : '',
                __('Attributes') => $row['attribute_count'],
            ),
            'distribution' => isset($row['distribution'])
                ? (int)$row['distribution']
                : null,
            'uuid' => isset($row['uuid']) ? $row['uuid'] : null,
            'tags' => isset($lookups['tags'][$id])
                ? $lookups['tags'][$id]
                : array(),
            'clusters' => array(),
        );
        $facts['detail'] = array_filter($facts['detail'], 'strlen');
        /*
         * A galaxy tag names a cluster the reader may not be allowed to
         * read. `fetchGalaxyClusters` is the ACL, so a name with no row
         * behind it is dropped rather than printed raw — the tag string
         * would disclose the cluster this instance is withholding.
         */
        if (empty($lookups['galaxy_by_event'][$id])) {
            return $facts;
        }
        foreach ($lookups['galaxy_by_event'][$id] as $name) {
            if (!isset($lookups['clusters'][$name])) {
                continue;
            }
            $cluster = $lookups['clusters'][$name]['GalaxyCluster'];
            $facts['clusters'][] = array(
                'id' => (int)$cluster['id'],
                'value' => $cluster['value'],
                'galaxy' => empty($cluster['Galaxy']['name'])
                    ? $cluster['type']
                    : $cluster['Galaxy']['name'],
            );
        }
        return $facts;
    }

    /**
     * A first/last seen stamp, only where one was actually recorded.
     *
     * @param array $row
     * @param string $key
     * @return string
     */
    private static function claimSeen(array $row, $key)
    {
        if (empty($row[$key])) {
            return '';
        }
        return substr(str_replace('T', ' ', $row[$key]), 0, 19);
    }

    /**
     * The organisation behind a target, from whichever end carries it.
     *
     * `Relationship::rearrangeData` nests the event's creator
     * organisation under an Attribute or Object target, so those two
     * arrive named. An Event target and a cluster arrive with an id,
     * and `claimTargetOrgs` is what turned that into a name.
     *
     * @param array $row
     * @param array $orgs id => name
     * @return array|null
     */
    private static function claimTargetOrg(array $row, array $orgs)
    {
        if (!empty($row['Organisation']['name'])) {
            return array(
                'id' => (int)$row['Organisation']['id'],
                'name' => $row['Organisation']['name'],
            );
        }
        $id = empty($row['orgc_id']) ? 0 : (int)$row['orgc_id'];
        return isset($orgs[$id])
            ? array('id' => $id, 'name' => $orgs[$id])
            : null;
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
            /*
             * Section four is governed by the feed cache rather than by
             * the correlation engine, and its rules are just as
             * invisible from the page: which sources are cached at all,
             * which of them this reader may be told about, and the
             * per-source event cap. Config and role only — no value.
             */
            'external' => $this->externalVisibility($user),
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
        /*
         * §10.2's labels, counted beside `correlations` and never into
         * it — the same rule the `external` note below states rather
         * than a new one. `correlations` is *values related to this
         * one*, and a galaxy cluster is no more a value than a remote
         * event is; summing them would invent a strength out of two
         * units, and inflate the one number on the rail that is meant
         * to be comparable between values.
         */
        $labels = isset($parts['cooccurrence']['labels']['total'])
            ? (int)$parts['cooccurrence']['labels']['total']
            : 0;
        $near = isset($parts['near'])
            ? (int)$parts['near']['matches']
            : 0;
        $asserted = isset($parts['asserted'])
            ? (int)$parts['asserted']['total']
            : 0;
        /*
         * Remote events, which is what section four's own header counts,
         * and deliberately not added to `correlations`: that total is
         * *values* related to this one, and an event is not a value.
         * Summing them would invent a strength out of two units, which
         * is the blend §5 of the tab brief exists to prevent.
         */
        $external = isset($parts['external'])
            ? (int)$parts['external']['events']
            : 0;
        /*
         * Object joins and typed references, counted beside the other
         * notions and — like `external` — deliberately not added into
         * `correlations`. A sibling is a value and could be summed; a
         * reference points at an object, which is not one. Keeping the
         * two apart is the same rule that keeps a remote event out of
         * the total.
         */
        $siblings = isset($parts['cooccurrence']['siblings'])
            ? (int)$parts['cooccurrence']['siblings']['total']
            : 0;
        $dated = isset($parts['cooccurrence']['dated'])
            ? (int)$parts['cooccurrence']['dated']['total']
            : 0;
        $references = isset($parts['references'])
            ? (int)$parts['references']['total']
            : 0;
        /*
         * Whether each of the three is a floor rather than a total. The
         * panels themselves print `≥` on a capped join, and the rail
         * card stating a bare number beside a panel qualifying it would
         * be the two disagreeing about how much they saw — which is the
         * one thing this card exists to prevent.
         */
        $siblingsCapped = !empty(
            $parts['cooccurrence']['siblings']['cap']['applied']
        );
        $datedCapped = !empty(
            $parts['cooccurrence']['dated']['cap']['applied']
        );
        $referencesCapped = !empty($parts['references']['cap']['applied']);
        $externalSources = isset($parts['external'])
            ? (int)$parts['external']['counts']['feeds']
                + (int)$parts['external']['counts']['servers']
            : 0;
        return array(
            'correlations' => $cooccurrence + $near,
            'cooccurrence' => $cooccurrence,
            /*
             * §10.2's label neighbours, counted apart from the values
             * for the reason given where they are split.
             */
            'labels' => $labels,
            'near' => $near,
            'external' => $external,
            'external_sources' => $externalSources,
            'asserted' => $asserted,
            'siblings' => $siblings,
            'dated' => $dated,
            'references' => $references,
            'siblings_capped' => $siblingsCapped,
            'dated_capped' => $datedCapped,
            'references_capped' => $referencesCapped,
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
     * The neighbourhood as a real node/edge feed, re-founded on the
     * object rather than on the event.
     *
     * **What this replaced and why** (`03-relationships.md` §23). The
     * first version of this feed drew one edge per value sharing an
     * *event* with this one. That is a star, it carries nothing the
     * three panels beneath it do not already print, and on live data it
     * drew 36 of `8.8.8.8`'s 10,024 neighbours. §24 of
     * `24-relationships.md` measured the topology behind it: components
     * equal event count, no neighbour spans two, a bridge fires once in
     * sixteen values. Sharing a container is not a relation.
     *
     * Sharing an *object* is. A `passive-dns` object says *this name
     * resolved to this address between these dates*; a `domain-ip` says
     * *this domain is on this address*. `8.8.8.8` has 22
     * object-mediated neighbours against 10,024 event-mediated ones,
     * and 95.6 % of values that sit in objects have fifty or fewer.
     *
     * **Five layers, each with its own edge kind:**
     *
     *     object      shares an object with this value
     *     event       this value appears in this event
     *     near        CIDR containment, ssdeep proximity
     *     human       an analyst wrote this claim
     *     reference   MISP's own typed relation between two objects
     *
     * **The event layer draws events and stops.** An event node is the
     * event; it does not expand into the ten thousand attributes inside
     * it. That is what keeps the layer affordable and what makes it
     * worth having — *which events is this value in* is a real
     * question, and an event node answers it.
     *
     * **Nothing is truncated anywhere.** Above the legibility bound the
     * object layer collapses to one node per template carrying its
     * object count, and every other layer rolls its tail into a single
     * counted node rather than dropping it. No caption on this canvas
     * states a fraction of a whole the reader cannot reach, which is
     * the defect §22.1 identified.
     *
     * **Two feeds, because there are two surfaces.** `peek` is the
     * rail — rolled hard, one node per template, a single node for the
     * events — and `feed` is the overlay, which expands the object
     * layer into values where the bound allows. That is what gives
     * `Open the full graph` a specific meaning rather than making it a
     * second copy of the same picture.
     *
     * @param array $parts `siblings`, `events`, `near`, `asserted`,
     *                     `references`
     * @param string $value
     * @return array
     */
    private function graphFor(array $parts, $value)
    {
        $siblings = $parts['siblings'];
        $events = $parts['events'];
        $near = $parts['near'];
        $asserted = $parts['asserted'];
        $references = $parts['references'];

        $centre = array('id' => 'value', 'data' => array(
            'label' => $value,
            'kind' => 'value',
            'sub' => __('this value'),
        ));
        $feed = array('nodes' => array($centre), 'edges' => array());
        $peek = array('nodes' => array($centre), 'edges' => array());
        $sketch = array('object' => array(), 'event' => array(),
            'near' => array(), 'human' => array(), 'reference' => array());
        $layers = array();

        /*
         * Layer one — object siblings.
         *
         * The rail always draws templates. The overlay draws values
         * where the fold both counted few enough of them to read and
         * carried every one it counted: `RELATION_ROW_CAP` bounds the
         * rows, so expanding past it would draw a hundred of a hundred
         * and twenty and put a fraction back on the canvas.
         */
        $expand = $siblings['total'] <= self::GRAPH_SIBLING_BOUND
            && $siblings['total'] <= count($siblings['rows']);
        foreach ($siblings['templates'] as $index => $template) {
            $id = 'tpl:' . $index;
            $node = array('id' => $id, 'data' => array(
                'label' => $template['object'],
                'kind' => 'template',
                'type' => $template['object'],
                'count' => (int)$template['objects'],
                'sub' => sprintf(
                    __n('%s object', '%s objects', $template['objects'],
                        number_format($template['objects'])),
                    number_format($template['objects'])
                ),
            ));
            $edge = self::graphEdge('value', $id, 'object',
                self::joinLabel($template['our_relation'],
                    $template['relations']));
            $peek['nodes'][] = $node;
            $peek['edges'][] = $edge;
            if (!$expand) {
                $feed['nodes'][] = $node;
                $feed['edges'][] = $edge;
            }
            if (count($sketch['object']) < 3) {
                $sketch['object'][] = $template['object'];
            }
        }
        if ($expand) {
            foreach ($siblings['rows'] as $index => $row) {
                $id = 'sib:' . $index;
                $feed['nodes'][] = array('id' => $id, 'data' => array(
                    'label' => $row['value'],
                    'kind' => 'sibling',
                    'type' => $row['type'],
                    'sub' => $row['relation'] === ''
                        ? $row['object']
                        : $row['object'] . ' · ' . $row['relation'],
                    'href' => '/values/view/' . self::b64($row['value']),
                ));
                $feed['edges'][] = self::graphEdge('value', $id, 'object',
                    self::joinLabel($row['our_relation'],
                        array($row['relation'])));
            }
        }
        $layers['object'] = array(
            'templates' => count($siblings['templates']),
            'values' => (int)$siblings['total'],
            'objects' => (int)$siblings['in_objects'],
            'rolled' => !$expand,
        );

        /*
         * Layer two — the events themselves.
         *
         * The rail carries one node for the lot unless there is exactly
         * one, because *which* events is a question for the overlay and
         * *how many* is the whole of the rail's answer.
         */
        $drawnEvents = array_slice($events, 0, self::GRAPH_EVENT_CAP);
        foreach ($drawnEvents as $index => $row) {
            $id = 'evt:' . $index;
            $eventId = (int)$row['event']['id'];
            $feed['nodes'][] = array('id' => $id, 'data' => array(
                'label' => $row['event']['info'] === ''
                    ? sprintf(__('Event %s'), $eventId)
                    : $row['event']['info'],
                'kind' => 'event',
                'type' => 'event',
                'sub' => trim($row['event']['date'] . ' · ' . $row['org'],
                    ' ·'),
                'href' => '/events/view/' . $eventId,
            ));
            $feed['edges'][] = self::graphEdge('value', $id, 'event',
                sprintf(__n('%s value here', '%s values here',
                    $row['shared_values'],
                    number_format($row['shared_values'])),
                    number_format($row['shared_values'])));
            if (count($sketch['event']) < 3) {
                $sketch['event'][] = $row['event']['date'] === ''
                    ? __('event')
                    : $row['event']['date'];
            }
        }
        $restEvents = count($events) - count($drawnEvents);
        if ($restEvents > 0) {
            $feed['nodes'][] = self::rollNode('evt:rest', 'event',
                $restEvents, sprintf(
                    __n('%s further event', '%s further events',
                        $restEvents, number_format($restEvents)),
                    number_format($restEvents)));
            $feed['edges'][] = self::graphEdge('value', 'evt:rest',
                'event', '');
        }
        if (!empty($events)) {
            /*
             * One node whatever the count, and it names the event where
             * there is only one. The rail's answer to *which events* is
             * *how many*; the overlay answers the question itself.
             */
            $peek['nodes'][] = count($events) === 1
                ? self::rollNode('evt:all', 'event', 1,
                    $events[0]['event']['info'] === ''
                        ? sprintf(__('Event %s'),
                            (int)$events[0]['event']['id'])
                        : $events[0]['event']['info'])
                : self::rollNode('evt:all', 'event', count($events),
                    sprintf(__n('%s event', '%s events', count($events),
                        number_format(count($events))),
                        number_format(count($events))));
            $peek['edges'][] = self::graphEdge('value', 'evt:all', 'event',
                '');
        }
        $layers['event'] = array(
            'drawn' => count($drawnEvents),
            'total' => count($events),
            'rolled' => $restEvents > 0,
        );

        /*
         * Layers three and four — near-match and asserted. Both are
         * small, both are semantically distinct from an object join,
         * and an analyst claim is the only edge on this canvas a human
         * wrote — which is why neither was dropped when the tab was
         * re-founded on objects.
         *
         * The overlay draws each one. The rail carries one counted node
         * per layer, for the reason §10.3 of `24-relationships.md`
         * measured: `8.8.8.8` has six claims and three templates, and
         * fourteen labels at 340px overlap into the illegibility that
         * finding is about. Rolling every layer but the object one
         * keeps the rail at three to eight nodes on every value, and
         * what it loses is one click away in the panel beneath it.
         */
        $index = 0;
        $nearTotal = 0;
        foreach ($near['engines'] as $engine) {
            foreach ($engine['rows'] as $row) {
                $nearTotal++;
                if ($index >= self::GRAPH_NODE_CAP) {
                    continue;
                }
                $id = 'near:' . $index++;
                $node = array('id' => $id, 'data' => array(
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
                $feed['nodes'][] = $node;
                $feed['edges'][] = self::graphEdge('value', $id, 'near',
                    $engine['id']);
                if (count($sketch['near']) < 3) {
                    $sketch['near'][] = $engine['id'] === 'cidr'
                        ? 'network-block'
                        : $engine['id'];
                }
            }
        }
        if ($nearTotal > $index) {
            $restNear = $nearTotal - $index;
            $feed['nodes'][] = self::rollNode('near:rest', 'near',
                $restNear, sprintf(__n('%s further near-match',
                    '%s further near-matches', $restNear,
                    number_format($restNear)), number_format($restNear)));
            $feed['edges'][] = self::graphEdge('value', 'near:rest',
                'near', '');
        }
        if ($nearTotal > 0) {
            $peek['nodes'][] = self::rollNode('near:all', 'near',
                $nearTotal, sprintf(__n('%s near-match', '%s near-matches',
                    $nearTotal, number_format($nearTotal)),
                    number_format($nearTotal)));
            $peek['edges'][] = self::graphEdge('value', 'near:all', 'near',
                '');
        }
        $layers['near'] = array(
            'drawn' => $index,
            'total' => $nearTotal,
            'rolled' => $nearTotal > $index,
        );

        $claims = $asserted['claims'];
        foreach (array_slice($claims, 0, self::GRAPH_NODE_CAP)
            as $i => $claim
        ) {
            $id = 'human:' . $i;
            $node = array('id' => $id, 'data' => array(
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
            $feed['nodes'][] = $node;
            $feed['edges'][] = self::graphEdge(
                $outbound ? 'value' : $id,
                $outbound ? $id : 'value',
                'human',
                $claim['relationship_type']
            );
            if (count($sketch['human']) < 3) {
                $sketch['human'][] = $claim['target']['kind'];
            }
        }
        $drawnClaims = min(count($claims), self::GRAPH_NODE_CAP);
        if (count($claims) > $drawnClaims) {
            $restClaims = count($claims) - $drawnClaims;
            $feed['nodes'][] = self::rollNode('human:rest', 'human',
                $restClaims, sprintf(
                    __n('%s further claim', '%s further claims',
                        $restClaims, number_format($restClaims)),
                    number_format($restClaims)));
            $feed['edges'][] = self::graphEdge('human:rest', 'value',
                'human', '');
        }
        if (!empty($claims)) {
            $peek['nodes'][] = self::rollNode('human:all', 'human',
                count($claims), sprintf(
                    __n('%s analyst claim', '%s analyst claims',
                        count($claims), number_format(count($claims))),
                    number_format(count($claims))));
            $peek['edges'][] = self::graphEdge('human:all', 'value',
                'human', '');
        }
        $layers['human'] = array(
            'drawn' => $drawnClaims,
            'total' => count($claims),
            'rolled' => count($claims) > $drawnClaims,
        );

        /*
         * Layer five — object references, and the only layer whose
         * nodes are objects. A reference is recorded between two
         * objects, so drawing it between values would be a re-telling
         * and would make *which object* unanswerable. The rail rolls
         * them per far template, the overlay draws each one.
         */
        $refRows = $references['rows'];
        $drawnRefs = array_slice($refRows, 0, self::GRAPH_NODE_CAP);
        foreach ($drawnRefs as $i => $row) {
            $id = 'ref:' . $i;
            $far = $row['far'];
            $feed['nodes'][] = array('id' => $id, 'data' => array(
                'label' => $far['label'],
                'kind' => $far['kind'] === 'object' ? 'object' : 'sibling',
                'type' => $far['object'] === null
                    ? $far['kind']
                    : $far['object'],
                'sub' => $far['object'] === null
                    ? __('attribute')
                    : $far['object'],
                'href' => $far['kind'] === 'object'
                    ? '/objects/view/' . (int)$far['id']
                    : '/values/view/' . self::b64($far['label']),
            ));
            $outbound = $row['direction'] === 'outbound';
            $feed['edges'][] = self::graphEdge(
                $outbound ? 'value' : $id,
                $outbound ? $id : 'value',
                'reference',
                $row['relationship']
            );
            if (count($sketch['reference']) < 3) {
                $sketch['reference'][] = $row['relationship'];
            }
        }
        $restRefs = count($refRows) - count($drawnRefs);
        if ($restRefs > 0) {
            $feed['nodes'][] = self::rollNode('ref:rest', 'reference',
                $restRefs, sprintf(__n('%s further reference',
                    '%s further references', $restRefs,
                    number_format($restRefs)), number_format($restRefs)));
            $feed['edges'][] = self::graphEdge('value', 'ref:rest',
                'reference', '');
        }
        if (!empty($refRows)) {
            $peek['nodes'][] = self::rollNode('ref:all', 'reference',
                count($refRows), sprintf(
                    __n('%s reference', '%s references', count($refRows),
                        number_format(count($refRows))),
                    number_format(count($refRows))));
            $peek['edges'][] = self::graphEdge('value', 'ref:all',
                'reference', '');
        }
        $layers['reference'] = array(
            'drawn' => count($drawnRefs),
            'total' => count($refRows),
            'rolled' => $restRefs > 0,
        );

        return array(
            'edges' => count($feed['edges']),
            'nodes' => $sketch,
            'layers' => $layers,
            'feed' => $feed,
            'peek' => $peek,
        );
    }

    /**
     * `passive-dns · rrname → rdata`, or as much of it as the object
     * actually said.
     *
     * The arrow is the reader's own position: it says which end of the
     * join they are standing on, which is the thing a boolean
     * `relational / descriptive` flag could never have told them
     * (§23.2). Where the object files one end anonymously the label
     * degrades to the half it knows rather than inventing the other.
     *
     * @param string|null $ours This value's relation in the object
     * @param array $theirs The far end's relations, ranked
     * @return string
     */
    private static function joinLabel($ours, array $theirs)
    {
        $far = array();
        foreach ($theirs as $relation) {
            if ($relation !== '' && $relation !== null) {
                $far[] = $relation;
            }
        }
        $far = implode(', ', array_slice($far, 0, 3));
        if ($ours === null || $ours === '') {
            return $far;
        }
        return $far === '' ? $ours : $ours . ' → ' . $far;
    }

    /**
     * A node standing for everything a layer did not draw one by one.
     *
     * The count is the point. A tail that is dropped makes the caption
     * a fraction; a tail that is counted makes it an answer, and the
     * number is often the finding — 32,922 near-identical objects reads
     * as flood capture at a glance.
     *
     * @param string $id
     * @param string $kind
     * @param int $count
     * @param string $label
     * @return array
     */
    private static function rollNode($id, $kind, $count, $label)
    {
        return array('id' => $id, 'data' => array(
            'label' => $label,
            'kind' => $kind,
            'type' => $kind,
            'count' => (int)$count,
            'rolled' => true,
        ));
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
            'object' => 'var(--vp-rel-object)',
            'event' => 'var(--vp-rel-event)',
            'near' => 'var(--vp-rel-near)',
            'human' => 'var(--vp-rel-human)',
            'reference' => 'var(--vp-rel-reference)',
        );
        /*
         * Five kinds and five strokes, because the separation has to
         * survive greyscale as well as colour-blindness: an object join
         * is solid and heavy, an event membership solid and thin, a
         * near-match dashed, and the two that carry a direction — an
         * analyst's claim and MISP's own reference — are the only ones
         * with an arrowhead. The distinction that matters most is the
         * last: those two were written by a person.
         */
        $directed = $kind === 'human' || $kind === 'reference';
        $weight = array(
            'object' => 2.25,
            'event' => 1.5,
            'near' => 2,
            'human' => 2.25,
            'reference' => 2,
        );
        return array(
            'from' => $from,
            'to' => $to,
            'data' => array('kind' => $kind, 'label' => $label),
            'style' => array('edge' => array(
                'strokeColor' => isset($ink[$kind])
                    ? $ink[$kind]
                    : 'var(--vp-rel-object)',
                'strokeWidth' => isset($weight[$kind])
                    ? $weight[$kind]
                    : 2,
                'dashed' => $kind === 'near' || $kind === 'event',
                'animateDash' => false,
                'markerEnd' => $directed ? 'arrow' : 'none',
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
