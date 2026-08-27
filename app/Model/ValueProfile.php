<?php
App::uses('AppModel', 'Model');
App::uses('ValueStatsTool', 'Tools');
App::uses('ValueDecayTool', 'Tools');

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
     * @var array Lazily loaded models, by alias
     */
    private $models = array();

    /**
     * @var array|null The occurrence summary, once per request
     */
    private $summary = null;

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
}
