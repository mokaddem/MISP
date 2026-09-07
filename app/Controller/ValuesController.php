<?php
App::uses('AppController', 'Controller');
App::uses('ValueProfileFixture', 'Tools');

/**
 * Value Profile controller, mounted at /values/* via CakePHP's default
 * routing.
 *
 * The subject of these pages is a value string — `185.234.219.24`, a hash,
 * a domain — not a single attribute row. The same value exists as many
 * attribute rows across many events, and this controller aggregates them.
 *
 * Read-only: nothing here writes. Every number is fixture data except on
 * the tabs the live campaign has converted — Occurrences (phase 22),
 * Sightings (23), Relationships (24), Timeline (25), Collaboration (26),
 * History (27) and Enrichment (28) — which it does one panel at a time,
 * so the two regimes sit side by side until it finishes.
 * `prd/value-profile-live/00-contract.md` §14.12 is the record of which
 * panels have moved.
 *
 * **One action leaves the building**, and it is the only one:
 * `viewEnrichmentRun` queries a third-party module. It still writes
 * nothing to MISP — see its docblock for what that costs instead, and
 * why it is the page's only POST.
 */
class ValuesController extends AppController
{
    public $components = array('Session', 'RequestHandler');

    // The subject is a value, not a row of one table, so there is no
    // default model to bind. Panels load their own models as they land.
    public $uses = array();

    /**
     * Where every element this controller renders lives, and the only
     * place any of them lives: `View/Themed/Overmind/Elements/Values`.
     * There is no unthemed fallback to fall back to.
     */
    const THEME = 'Overmind';

    /**
     * A panel is an HTML fragment and has no other representation.
     *
     * `Router::parseExtensions` accepts `json`, `xml` and `csv` on every
     * route, so any of them could be appended to a panel URL — and
     * `RequestHandler` also infers one from an `Accept` header. What
     * came back was a 500: the extension makes `AppController` treat
     * the request as REST, REST skips the block that sets the theme,
     * and a themeless render cannot find an element that exists only
     * under a theme. Every panel threw `MissingViewException`.
     *
     * Refusing is the honest answer rather than serving something. A
     * panel is markup built for one page to inject; there is no JSON
     * shape of it to define, and `live/00-contract.md` §14.11 puts an
     * API for this page out of scope.
     *
     * @return void
     * @throws NotFoundException
     */
    public function beforeFilter()
    {
        parent::beforeFilter();
        $this->rejectNonHtmlExtension(
            $this->request->params['ext'] ?? null
        );
        /*
         * The run endpoint posts two scalars and no form, so the
         * form-tampering hash has nothing to check and its absence
         * would blackhole every run. The CSRF check stays on —
         * `viewEnrichmentRun` says why that half is worth keeping
         * where MISP's usual ajax treatment drops both.
         */
        if (($this->request->params['action'] ?? null)
            === 'viewEnrichmentRun'
        ) {
            $this->Security->validatePost = false;
        }

        /*
         * **A use-once CSRF token cannot survive this page.**
         *
         * `SecurityComponent::generateToken()` is a read-modify-write
         * on one session key: it reads `_Token.csrfTokens`, adds the
         * nonce it just minted, and writes the map back. This page
         * loads its panels lazily and in parallel — around twenty
         * requests land together — so two that overlap both read the
         * same map and the later write drops the earlier one's nonce.
         * Whichever request the Enrichment panel was is as likely as
         * any other to be the one dropped, and the run it embedded a
         * token for then blackholes at 400.
         *
         * Measured before this line existed: **two of five** browser
         * runs failed that way, against six of six over plain HTTP,
         * where nothing is concurrent. That gap is the whole tell —
         * the defect is in the page's concurrency, not the endpoint.
         *
         * A stable per-session token is the fix and not a weakening:
         * it is the synchroniser-token pattern every later CakePHP
         * uses, and CSRF turns on an attacker being unable to *read*
         * the token cross-origin, never on its being fresh. Every
         * concurrent write now writes the same key, so the lost
         * update stops mattering, and a reader can run several
         * modules without each press spending the next one's token.
         *
         * Scoped to this controller, which posts exactly one thing.
         */
        $this->Security->csrfUseOnce = false;
    }

    /**
     * The guard above, named so it can be exercised on its own — the
     * filter it runs from needs a session and this does not.
     *
     * @param string|null $extension The route's parsed extension
     * @return void
     * @throws NotFoundException
     */
    protected function rejectNonHtmlExtension($extension)
    {
        if ($extension === null || $extension === 'html') {
            return;
        }
        throw new NotFoundException(__(
            'The Value Profile panels are HTML fragments and have no'
            . ' %s representation.',
            strtoupper($extension)
        ));
    }

    /**
     * Render under the theme the panels live in, whoever is asking.
     *
     * `AppController` sets a theme only for a non-REST request, and
     * only when `MISP.enable_themes` is on and a theme resolves — so an
     * instance with themes disabled reaches `render()` with none, and
     * every panel throws `MissingViewException` exactly as the REST
     * path did. This page has one implementation and it is under
     * `Overmind`, so naming it here is a statement of where the files
     * are rather than a preference overriding the reader's.
     *
     * A theme the user did choose is left alone: this is a floor, not a
     * ceiling.
     *
     * @return void
     */
    public function beforeRender()
    {
        parent::beforeRender();
        if (empty($this->theme)) {
            $this->theme = self::THEME;
            $this->viewClass = 'Theme';
        }
    }

    /**
     * The full profile page for one value.
     *
     * @param string $b64value
     * @return void
     */
    public function view($b64value = null)
    {
        $profile = $this->profileFor($b64value);
        /*
         * The frame is still the fixture's — see §14.12, where the tab
         * counts and banner chips are the Overview's phase to convert.
         * The two badges that name a converted tab are corrected here,
         * because those are the two that can be caught contradicting
         * the panel underneath them. `forTabCounts` says which and why.
         */
        $this->loadModel('ValueProfile');
        $profile['counts'] = $this->ValueProfile->forTabCounts(
            $this->Auth->user(),
            $profile['value'],
            $profile['counts']
        );
        $this->set('valueProfile', $profile);
        // Re-encoded rather than passed through, so the panel URLs the page
        // builds are well-formed whichever alphabet the caller arrived with.
        $this->set('valueB64', self::encodeValue($profile['value']));
    }

    /**
     * The panels, one lazily-loaded fragment each.
     *
     * A panel per endpoint rather than one endpoint per page: each is a
     * different question of a different model, they answer at different
     * speeds, and a slow one should not hold up the rest. It also means
     * each panel's live implementation is one action and one element.
     *
     * @param string $b64value
     * @return void
     */
    public function viewOccurrences($b64value = null)
    {
        $this->renderPanel($this->profileFor($b64value), 'value_occurrences');
    }

    public function viewContext($b64value = null)
    {
        $this->renderPanel($this->profileFor($b64value), 'value_context');
    }

    /**
     * The Overview's preview of the Collaboration tab.
     *
     * **Live since 2026-09-05**, and it reads the tab's own union
     * rather than a cheaper one of its own — `ValueProfile::
     * forAnalystPreview` has the argument. It was the last panel on
     * this page still answering from the fixture beside panels reading
     * the database, which `26-analyst.md` §11 call 2 recorded as the
     * price of leaving the Overview's row to the Overview's phase.
     *
     * @param string $b64value
     * @return void
     */
    public function viewAnalystPreview($b64value = null)
    {
        $this->renderLivePanel(
            $b64value,
            'forAnalystPreview',
            'value_analyst_preview'
        );
    }

    public function viewVerdictCard($b64value = null)
    {
        $this->renderPanel($this->profileFor($b64value), 'value_verdict_card');
    }

    /**
     * The Overview's sightings card, and the one panel of that tab that
     * reads the database — see `ValueProfile::forSightings` for why it
     * was converted here rather than with the rest of the Overview.
     *
     * @param string $b64value
     * @return void
     */
    public function viewSightings($b64value = null)
    {
        $this->loadModel('ValueProfile');
        $this->renderPanel(
            $this->ValueProfile->forSightings(
                $this->Auth->user(),
                $this->decodeValue($b64value)
            ),
            'value_sightings'
        );
    }

    /**
     * The Overview rail's Lifecycle card — three questions that all
     * bear on *is this still worth acting on*.
     *
     * **One of the three went live in phase 5 and the other two did
     * not**, which is deliberate and the narrower reading of
     * `00-contract.md` §14.12's note about a tab not being
     * indivisible. This phase owns the freshness question and retires
     * the decay bars that used to answer it, so leaving the card
     * rendering a fixture literal in their place would ship a panel
     * saying something no query supports. The warninglist and
     * correlation lines are the Overview's own phase to convert and
     * are untouched.
     *
     * @param string $b64value
     * @return void
     */
    public function viewLifecycle($b64value = null)
    {
        $this->loadModel('ValueProfile');
        $profile = $this->profileFor($b64value);
        $profile['relevance'] = $this->ValueProfile->forRelevance(
            $this->Auth->user(),
            $profile['value']
        )['relevance'];
        $this->renderPanel($profile, 'value_lifecycle');
    }

    /**
     * The Overview's external presence card.
     *
     * Live for feeds and sync servers, and a count rather than a list:
     * the detail is the Relationships tab's fourth section, reading the
     * same filtered method so the two cannot disagree
     * (`tabs/03-relationships.md` §20.1).
     *
     * @param string $b64value
     * @return void
     */
    public function viewExternal($b64value = null)
    {
        $this->renderLivePanel(
            $b64value,
            'forExternal',
            'value_external'
        );
    }

    /**
     * The Occurrences tab: the facet rail and the table it counts.
     *
     * One endpoint rather than two, unlike the panels above. A facet
     * count and the rows it counts have to be computed from the same
     * fetch or they can disagree with each other, and two endpoints
     * against a moving attribute set is exactly how that happens.
     *
     * **Live since phase 22** — the first panel on this page to read the
     * database rather than `ValueProfileFixture`. It is also why the
     * whole-profile shape below no longer serves every endpoint: the
     * live facade answers per panel, per
     * prd/value-profile-live/22-occurrences.md.
     *
     * @param string $b64value
     * @return void
     */
    public function viewOccurrenceTable($b64value = null)
    {
        $this->loadModel('ValueProfile');
        $this->renderPanel(
            $this->ValueProfile->forOccurrenceTable(
                $this->Auth->user(),
                $this->decodeValue($b64value)
            ),
            'value_occurrence_table'
        );
    }

    /**
     * The Sightings tab: five panels, one endpoint each.
     *
     * Split the other way from the Occurrences tab, and for the
     * opposite reason. There the rail counts the rows beside it, so one
     * fetch is the only honest shape; here the chart, the list and the
     * three rail cards are five readings of the same rows that resolve
     * at their own speed, and the overlay is the widest.
     *
     * **Live since phase 23.** The split earned its keep on the decay
     * envelope — the two panels that drew a curve each spent around
     * 200,000 formula evaluations on it — and phase 5 retired the
     * envelope without collapsing the split: five readings that resolve
     * at their own speed is still the right shape for a tab whose table
     * is the part a reader acts on.
     * prd/value-profile-live/23-sightings.md,
     * prd/analyst-profile/06-staleness.md §4.
     *
     * @param string $b64value
     * @return void
     */
    public function viewSightingChart($b64value = null)
    {
        $this->renderSightingPanel(
            $b64value,
            'forSightingChart',
            'value_sighting_chart'
        );
    }

    public function viewSightingList($b64value = null)
    {
        $this->renderSightingPanel(
            $b64value,
            'forSightingList',
            'value_sighting_list'
        );
    }

    /**
     * The rail's relevance card, which until phase 5 was the decay card.
     *
     * `prd/analyst-profile/06-staleness.md` §4 is the retirement and its
     * reason: MISP's decay score is largely a restatement of the value's
     * tags multiplied by a time factor, and the assessment's quality
     * ledger already scores those tags directly with a per-row audit
     * trail. So the page takes the time factor and leaves the base
     * score. `decaying_models` itself is untouched — the decaying tool,
     * `excludeDecayed`, `includeDecayScore` and every non-Value-Profile
     * caller keep working exactly as they do.
     *
     * @param string $b64value
     * @return void
     */
    public function viewRelevance($b64value = null)
    {
        $this->renderSightingPanel(
            $b64value,
            'forRelevance',
            'value_relevance'
        );
    }

    public function viewSightingReporters($b64value = null)
    {
        $this->renderSightingPanel(
            $b64value,
            'forSightingReporters',
            'value_sighting_reporters'
        );
    }

    public function viewSightingAdd($b64value = null)
    {
        $this->renderSightingPanel(
            $b64value,
            'forSightingAdd',
            'value_sighting_add'
        );
    }

    /**
     * One line per Sightings endpoint rather than five copies of the
     * same four, which is what §14.2 promised the swap would cost.
     *
     * @param string $b64value
     * @param string $method A public ValueProfile facade method
     * @param string $element Name under Elements/Values/View
     * @return void
     */
    private function renderSightingPanel($b64value, $method, $element)
    {
        $this->loadModel('ValueProfile');
        $this->renderPanel(
            $this->ValueProfile->$method(
                $this->Auth->user(),
                $this->decodeValue($b64value)
            ),
            $element
        );
    }

    /**
     * The Relationships tab: three notions of "related", one endpoint
     * each, plus the two rail cards.
     *
     * Three and not one, because the three cost different amounts the
     * moment this goes live. Co-occurrence is a query against the
     * correlation table that can return 1,847 rows; near-matches are
     * re-derived per render and need the CIDR list and an ssdeep
     * recompute; asserted claims are a cheap, complete read of
     * `Relationship` over the value's occurrence UUIDs. One slow
     * correlation query must not hold up the claims, which are the part
     * of this tab a person actually wrote.
     *
     * **Live since phase 24**, and the split turned out to matter more
     * than the fixture could show: the co-occurrence scan reads up to
     * 20,000 attribute rows and the asserted claims read a handful of
     * `relationships` rows, so a shared endpoint would have made the
     * cheap, human part of this tab wait on the statistical one.
     * prd/value-profile-live/24-relationships.md.
     *
     * @param string $b64value
     * @return void
     */
    public function viewRelationCooccurrence($b64value = null)
    {
        $this->renderLivePanel(
            $b64value,
            'forRelationCooccurrence',
            'value_relation_cooccurrence',
            array(
                'filters' => $this->relationFilters(),
                // The panel's own refresh, and the only thing on this
                // page that asks for a read rather than accepting one.
                'fresh' => !empty($this->request->query['fresh']),
            )
        );
    }

    public function viewRelationNearMatch($b64value = null)
    {
        $this->renderLivePanel(
            $b64value,
            'forRelationNearMatch',
            'value_relation_near_match'
        );
    }

    public function viewRelationAsserted($b64value = null)
    {
        $this->renderLivePanel(
            $b64value,
            'forRelationAsserted',
            'value_relation_asserted'
        );
    }

    /**
     * Section five. Folded with the co-occurrence scan, so this is
     * usually a Redis read — see `ValueProfile::forRelationDated`.
     *
     * @param string $b64value
     * @return void
     */
    public function viewRelationDated($b64value = null)
    {
        $this->renderLivePanel(
            $b64value,
            'forRelationDated',
            'value_relation_dated'
        );
    }

    /**
     * Section six, and the cheapest endpoint on the tab: three indexed
     * lookups against `object_references` and one resolve. Its own
     * action for exactly that reason — it must not queue behind the
     * 20,000-row scan section one can run.
     *
     * @param string $b64value
     * @return void
     */
    public function viewRelationReferences($b64value = null)
    {
        $this->renderLivePanel(
            $b64value,
            'forRelationReferences',
            'value_relation_references'
        );
    }

    public function viewRelationExternal($b64value = null)
    {
        $this->renderLivePanel(
            $b64value,
            'forRelationExternal',
            'value_relation_external'
        );
    }

    public function viewRelationGraph($b64value = null)
    {
        $this->renderLivePanel(
            $b64value,
            'forRelationGraph',
            'value_relation_graph'
        );
    }

    public function viewRelationSettings($b64value = null)
    {
        $this->renderLivePanel(
            $b64value,
            'forRelationSettings',
            'value_relation_settings'
        );
    }

    /**
     * The rail's third card: which named threats this value sits next
     * to.
     *
     * Its own endpoint rather than a group on the graph card, for the
     * reason the split above gives: it reads events the co-occurrence
     * scan may have skipped as too large, so it answers on values
     * whose neighbourhood table is suppressed entirely and must not
     * queue behind the scan that suppressed it.
     *
     * @param string $b64value
     * @return void
     */
    public function viewRelationThreats($b64value = null)
    {
        $this->renderLivePanel(
            $b64value,
            'forRelationThreats',
            'value_relation_threats'
        );
    }

    /**
     * One line per live endpoint, as the Sightings tab's five already
     * have. Named for the Relationships tab until phase 26, which is
     * the third tab to reach for it.
     *
     * @param string $b64value
     * @param string $method A public ValueProfile facade method
     * @param string $element Name under Elements/Values/View
     * @return void
     */
    private function renderLivePanel($b64value, $method, $element,
        array $options = array()
    ) {
        $this->loadModel('ValueProfile');
        $this->renderPanel(
            $this->ValueProfile->$method(
                $this->Auth->user(),
                $this->decodeValue($b64value),
                $options
            ),
            $element
        );
    }

    /**
     * The co-occurrence panel's narrowing, as it arrives on the wire.
     *
     * The panel re-requests itself when the reader ticks something its
     * own markup cannot answer — a facet naming thousands of values
     * that rank below the hundred it carries. Nothing here is trusted:
     * `ValueRelationTool` drops every key it did not declare, and the
     * values are only ever compared against tokens it generated
     * itself, so they reach no query and no output.
     *
     * @return array
     */
    private function relationFilters()
    {
        $query = $this->request->query;
        if (empty($query['f']) || !is_array($query['f'])) {
            return array();
        }
        return $query['f'];
    }

    /**
     * The Enrichment tab: the modules this value could be sent to.
     *
     * **Live since phase 28, and stateless.** Nothing records that a
     * module ran, because there is nowhere for that to live — `Module`
     * is `useTable = false` and no per-value per-module store exists.
     * So this tab has no memory: it lists what could be asked, a press
     * asks one thing, and the answer lives in the response.
     * `28-enrichment.md` §1 is why that is the phase rather than the
     * store it would have needed.
     *
     * **Two endpoints now, where phase 12 had one.** The rail and the
     * pane were one fixture read and are no longer: the rail is cheap
     * and local, a run costs an outbound query and up to five seconds.
     * So they resolve separately, which is the per-panel ajax pattern
     * this page already uses, one level further down.
     *
     * Nothing here runs a module. Not on load, not on tab switch, not
     * on selecting one — a run spends quota and tells a third party
     * you are looking, so it needs a press nobody made by arriving.
     *
     * @param string $b64value
     * @return void
     */
    public function viewEnrichment($b64value = null)
    {
        $this->renderLivePanel(
            $b64value,
            'forEnrichment',
            'value_enrichment'
        );
    }

    /**
     * Run one module against this value and render what came back.
     *
     * **The only action on this page that causes anything to leave the
     * instance**, and the only one that is a POST. It writes nothing
     * to MISP — `Module::queryModuleServer()` is the non-writing call,
     * and `Event::enrichment()`, which turns a response into
     * attributes, is never reached from here — but it spends the
     * instance's quota and announces interest in the value to a third
     * party. A GET carrying that is prefetchable, replayable and
     * crawlable, so it is a POST with a CSRF token.
     *
     * **`validatePost` is off and the CSRF check is not.** Form
     * tampering is what `validatePost` defends and there is no form:
     * two scalars arrive, and neither is trusted anyway — the module
     * name and type are checked against the catalogue this reader
     * would have been shown, in `ValueProfile::forEnrichmentRun`.
     * MISP's usual ajax treatment is `unlockedActions`, which would
     * drop both; this endpoint keeps the half that matters.
     *
     * @param string $b64value
     * @return void
     * @throws MethodNotAllowedException
     */
    public function viewEnrichmentRun($b64value = null)
    {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException(__(
                'Running a module queries a third party, so it is a'
                . ' POST.'
            ));
        }
        $this->renderLivePanel(
            $b64value,
            'forEnrichmentRun',
            'value_enrichment_result',
            array(
                'module' => $this->runParam('module'),
                'type' => $this->runParam('type'),
            )
        );
    }

    /**
     * One posted scalar, or null.
     *
     * Nothing here validates: the catalogue does, because a check
     * written beside the request would be a second opinion about what
     * this reader may ask, and two of those is how the looser one
     * becomes the answer.
     *
     * @param string $key
     * @return string|null
     */
    private function runParam($key)
    {
        $data = $this->request->data;
        if (!isset($data[$key]) || !is_string($data[$key])) {
            return null;
        }
        return $data[$key];
    }

    /**
     * The Analyst data tab: where the organisations stand, and the
     * thread underneath it.
     *
     * Two endpoints for what is one fetch, which is the opposite of
     * the Occurrences tab's reasoning and for a compatible one. There
     * the rail counts the rows beside it, so a split could let the two
     * disagree. Here both panels are two readings of one union — the
     * numbers cannot drift because neither is authoritative for the
     * other's rows — while the thread is the part that grows without
     * limit and the part that has no single query behind it: analyst
     * data hangs off an object UUID, so the union over a value's
     * occurrences, their events and their objects is assembled here.
     *
     * **Live since phase 26**, and the split stands for a different
     * reason than it was made for. The standing panel was expected to
     * be the cheap one — four numbers over a set bounded by the
     * organisations on the instance — and it is not: two of its
     * columns count the thread. Both endpoints now read the same
     * union, and the split survives because the two panels still
     * resolve independently in the page.
     *
     * @param string $b64value
     * @return void
     */
    public function viewAnalystStanding($b64value = null)
    {
        $this->renderLivePanel(
            $b64value,
            'forAnalystStanding',
            'value_analyst_standing'
        );
    }

    public function viewAnalystThread($b64value = null)
    {
        $this->renderLivePanel(
            $b64value,
            'forAnalystThread',
            'value_analyst_thread'
        );
    }

    /**
     * The tab's third panel: the event reports written about the events
     * this value sits in.
     *
     * Its own endpoint rather than a third region of the thread's,
     * because it is a different question of a different model and
     * answers at a different speed — the reports read is one
     * `fetchReports` over the value's events and never waits for the
     * thread's five-anchor union.
     *
     * @param string $b64value
     * @return void
     */
    public function viewAnalystReports($b64value = null)
    {
        $this->renderLivePanel(
            $b64value,
            'forAnalystReports',
            'value_analyst_reports'
        );
    }

    /**
     * The Timeline tab: the spine, the source lanes and the
     * chronology, in one panel.
     *
     * One endpoint for all three, against every other multi-card tab
     * on this page, because the brush is a single control driving two
     * regions that must already exist when it fires. Three `.ajax-card`
     * requests resolve independently, so a spine that arrived first
     * would be a brush wired to nothing.
     *
     * **Live since phase 25**, and it stays one endpoint: the argument
     * above is about the brush and nothing about reading the database
     * touches it.
     *
     * **The window is in the path, and it is a filter rather than an
     * identity.** `viewHistory`'s period is the panel's subject — what
     * that endpoint returns for one window is a different fragment, top
     * to bottom. This one returns the same spine over the value's whole
     * range whatever window it is given; only the chronology's rows
     * change. It takes the path shape anyway, and `self::period` with
     * it, because a reader of these two actions should not have to
     * learn that one page states a window two ways.
     *
     * Absent or malformed dates mean no window, which is the panel a
     * page load asks for.
     *
     * @param string $b64value
     * @param string $from `Y-m-d`
     * @param string $to `Y-m-d`
     * @return void
     */
    public function viewTimeline($b64value = null, $from = null, $to = null)
    {
        $window = self::period($from, $to);
        $this->loadModel('ValueProfile');
        $this->renderPanel(
            $this->ValueProfile->forTimeline(
                $this->Auth->user(),
                $this->decodeValue($b64value),
                // `period` also answers `all`, which this panel has no
                // use for: its spine is already the whole range.
                array('window' => is_array($window) ? $window : null)
            ),
            'value_timeline'
        );
    }

    /**
     * The History tab: the counted rail and the occurrence sections it
     * narrows, in one panel.
     *
     * One endpoint, for the Occurrences tab's reason rather than the
     * Timeline tab's. The facet control wires its checkboxes to rows by
     * walking up to the nearest `data-vp-list` region, so the rail and
     * the rows have to arrive inside one container: split across two
     * `.ajax-card`s they resolve independently, and a rail whose rows
     * have not landed yet is a rail wired to nothing.
     *
     * The only panel on this page that takes a period, and it takes it
     * in the path because it is the panel's identity rather than a
     * filter over it: what the endpoint returns for one window is a
     * different fragment, and `reloadAjaxTabIndex` re-fetches a
     * container by URL. Everything else on the tab narrows client-side
     * over rows this already sent.
     *
     * @param string $b64value
     * @param string $from `Y-m-d`, or the literal `all`
     * @param string $to `Y-m-d`
     * @return void
     */
    public function viewHistory($b64value = null, $from = null, $to = null)
    {
        $this->renderLivePanel(
            $b64value,
            'forHistory',
            'value_history',
            array('window' => self::period($from, $to))
        );
    }

    /**
     * A period out of two path segments.
     *
     * Anything that is not a well-formed pair falls back to the default
     * window rather than to the whole log: a typo'd URL should land the
     * reader on the bounded page, which is the one that always renders.
     *
     * @param string $from
     * @param string $to
     * @return mixed `all`, a from/to pair, or null for the default
     */
    private static function period($from, $to)
    {
        if ($from === 'all') {
            return 'all';
        }
        $shape = '/^\d{4}-\d{2}-\d{2}$/';
        if (!preg_match($shape, (string)$from)
            || !preg_match($shape, (string)$to)
        ) {
            return null;
        }
        return $from <= $to
            ? array('from' => $from, 'to' => $to)
            : array('from' => $to, 'to' => $from);
    }

    /**
     * The Verdict tab body.
     *
     * A value whose signals contradict each other needs a different
     * layout, not a different colour: two opposed cases side by side
     * rather than one ledger. Which one is a property of the value, so
     * the disposition picks the template.
     *
     * @param string $b64value
     * @return void
     */
    public function viewVerdict($b64value = null)
    {
        $profile = $this->profileFor($b64value);
        $conflicted = ($profile['verdict']['disposition'] ?? null)
            === 'CONFLICTED';
        $this->renderPanel(
            $profile,
            $conflicted ? 'value_verdict_conflicted' : 'value_verdict'
        );
    }

    /**
     * The Verdict tab's right rail.
     *
     * One endpoint for the whole rail rather than one per card, unlike
     * the Overview rail: those cards are different questions of
     * different models, while every card here is a reading of the same
     * verdict computation. The element picks which cards apply.
     *
     * @param string $b64value
     * @return void
     */
    public function viewVerdictAside($b64value = null)
    {
        $this->renderPanel(
            $this->profileFor($b64value),
            'value_verdict_aside'
        );
    }

    /**
     * @param string $b64value
     * @param array $options Per-panel options; see
     *                       ValueProfileFixture::forValue
     * @return array
     */
    private function profileFor($b64value, array $options = array())
    {
        return ValueProfileFixture::forValue(
            $this->decodeValue($b64value),
            $options
        );
    }

    /**
     * Serve one panel: the fragment only, no layout and no chrome, since
     * it is injected into a page that already has both.
     *
     * @param array $profile
     * @param string $element Name under Elements/Values/View
     * @return void
     */
    private function renderPanel(array $profile, $element)
    {
        $this->set('valueProfile', $profile);
        $this->set('valueB64', self::encodeValue($profile['value']));
        $this->layout = false;
        $this->render('/Elements/Values/View/' . $element);
    }

    /**
     * @param string $value
     * @return string URL-safe base64, so a value containing `/` survives
     *                a path segment.
     */
    private static function encodeValue($value)
    {
        return strtr(base64_encode($value), '+/', '-_');
    }

    /**
     * Values reach this controller base64-encoded because they are
     * arbitrary strings in a URL segment. Both the standard and the
     * URL-safe alphabet are accepted — a raw `/` cannot survive a path
     * segment, so callers legitimately encode with `-_`.
     *
     * @param string $b64value
     * @return string
     * @throws NotFoundException
     */
    private function decodeValue($b64value)
    {
        if ($b64value === null || $b64value === '') {
            throw new NotFoundException(__('No value supplied.'));
        }
        $normalised = strtr($b64value, '-_', '+/');
        $value = base64_decode($normalised, true);
        if ($value === false || $value === '') {
            throw new NotFoundException(__('Invalid base64 encoding.'));
        }
        return $value;
    }
}
