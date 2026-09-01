<?php
App::uses('ValueStatsTool', 'Tools');

/**
 * The Relationships tab's aggregates.
 *
 * Pure and static, and it takes no `$user` — the shape
 * prd/value-profile-live/00-contract.md §14.5 requires. Every method
 * here folds rows the owning model has already scoped, so nothing in
 * this file can widen what a viewer sees.
 *
 * It exists rather than growing `ValueStatsTool` for the same reason
 * `ValueDecayTool` did in phase 23: one tab's notion of *related* is a
 * self-contained argument, and the folding rules below — how a value
 * with two types gets one badge, which audiences a group of
 * occurrences reports, how a sibling seen five hundred times becomes
 * one row — are decisions this tab owns and nothing else reads.
 *
 * **What is not here.** The correlation table. Section one of this tab
 * is not correlation-engine output and cannot be: a
 * `default_correlations` row links two attributes that carry *the same*
 * value, so for one value the engine returns other occurrences of it —
 * which is the Occurrences tab — and its CIDR/ssdeep partners, which
 * are section two. Nothing in it ever returns a *different* value.
 * `24-relationships.md` §3 has the argument and the measurement.
 */
class ValueRelationTool
{
    /**
     * Entries kept in one facet group.
     *
     * Each entry's *count* stays exact — this cuts the list, not the
     * arithmetic. `8.8.8.8` produces 128 distinct tags across its
     * neighbourhood, and a dropdown that long is not a control anybody
     * uses: `value_facet_group` folds everything past the tenth behind
     * a *"n more"* button and grows a search box past fifty, so the
     * tail beyond this is markup nobody reads. It was 178 KB of the
     * fragment when it was uncapped.
     */
    const FACET_CAP = 40;

    /**
     * MISP's one "this is a point in time" attribute type, and the only
     * marker the dated fold needs. `date-of-birth` and
     * `whois-creation-date` are dates about a subject rather than about
     * when a relation held, and neither appears in a pair.
     */
    const DATE_TYPE = 'datetime';

    /** What `passive-dns` calls the source of a resolution. */
    const ORIGIN_RELATION = 'origin';

    /**
     * Fold the value's neighbourhood into the three roll-ups, the six
     * facet groups and the numbers the panel header prints.
     *
     * One pass over rows that have already been bounded by the caller.
     * The counts are therefore **exact over the scope**, and the scope
     * is what the panel states — which is the distinction §14.4 draws
     * between folding a complete set and tallying a page and calling it
     * a total.
     *
     * @param array $rows Neighbour rows, `Value::neighbourRowsFor`
     * @param array $context `orgs`, `events`, `sharing_groups`,
     *                       `our_objects`, `row_cap`, `page_size`
     * @return array The `cooccurrence` shape the template reads
     */
    public static function cooccurrence(array $rows, array $context)
    {
        $orgs = isset($context['orgs']) ? $context['orgs'] : array();
        $eventMeta = isset($context['events'])
            ? $context['events']
            : array();
        $sgNames = isset($context['sharing_groups'])
            ? $context['sharing_groups']
            : array();
        /*
         * The object templates this value is itself in, so a row can
         * say whether it is a sibling. It arrives from the caller
         * because the sibling join is the caller's query, not this
         * fold's.
         */
        $ourObjects = isset($context['our_objects'])
            ? $context['our_objects']
            : array();
        /*
         * The narrowing the reader asked for, applied here rather than
         * in the browser. The table carries the top `row_cap` values by
         * shared events, so a filter applied after that cut can only
         * ever narrow the hundred that survived it — which is why
         * `abuse.ch`, 9,791 values none of which rank that high, used
         * to empty the table it had just been counted in.
         */
        $narrowing = isset($context['filters'])
            ? (array)$context['filters']
            : array();
        $filters = self::cleanFilters($narrowing);
        /*
         * The ranking travels with the narrowing because it decides the
         * same thing: which values reach the cut. Ordering the hundred
         * that were already chosen by shared events cannot answer
         * *most recent* — the most recent value in a neighbourhood this
         * size is usually one that shares a single event and never
         * ranked near the top.
         */
        $rank = isset($narrowing['rank'])
            && $narrowing['rank'] === 'recent'
            ? 'recent'
            : 'shared';
        $rowCap = isset($context['row_cap']) ? $context['row_cap'] : 200;
        $pageSize = isset($context['page_size'])
            ? $context['page_size']
            : 8;

        $groups = array();
        $objects = array();
        $categories = array();

        foreach ($rows as $row) {
            $attribute = $row['Attribute'];
            $value = isset($attribute['value']) ? $attribute['value'] : '';
            if ($value === '') {
                continue;
            }
            if (!isset($groups[$value])) {
                $groups[$value] = self::emptyGroup($value);
            }
            $group = &$groups[$value];
            $group['occurrences']++;
            self::tally($group['types'], $attribute['type']);
            self::tally($group['categories'], $attribute['category']);
            $categories[$attribute['category']] = true;
            $eventId = (int)$attribute['event_id'];
            $group['events'][$eventId] = true;
            $group['orgs'][(int)$row['Event']['orgc_id']] = true;
            $group['last'] = max(
                $group['last'],
                (int)$attribute['timestamp']
            );

            if (!empty($row['Object']['id'])) {
                $objectId = (int)$row['Object']['id'];
                self::tally($group['objects'], $row['Object']['name']);
                $group['object_ids'][$objectId] = true;
                if (!isset($objects[$objectId])) {
                    $objects[$objectId] = array(
                        'object' => array(
                            'id' => $objectId,
                            'name' => $row['Object']['name'],
                        ),
                        'event' => $eventId,
                        'org' => (int)$row['Event']['orgc_id'],
                        'values' => array(),
                        'relations' => array(),
                    );
                }
                $objects[$objectId]['values'][$value] = true;
                if (!empty($attribute['object_relation'])) {
                    $objects[$objectId]['relations']
                        [$attribute['object_relation']] = true;
                }
            }

            /*
             * Every audience the group's occurrences state, and not one
             * of them standing in for the rest. `effectiveDistribution`
             * is still what answers it per occurrence — the conjunction
             * of the attribute, its object and its event, tightest
             * wins — but a row folds many occurrences and a set of
             * records spread over events has no single audience. What
             * used to happen here was a second fold on top of that one,
             * keeping whichever occurrence was widest, which let a row
             * read `All communities` while one of the records behind it
             * was org-only.
             *
             * Keyed on the pair, so two occurrences of one sharing
             * group are one entry and two different sharing groups
             * stay two — the distinction the level alone cannot make.
             */
            $effective = ValueStatsTool::effectiveDistribution(
                $row,
                $sgNames
            );
            if ($effective['level'] !== null) {
                $sharingGroup = (int)$effective['sharing_group_id'];
                $audience = $sharingGroup === 0
                    ? $effective['level']
                    : $effective['level'] . '.' . $sharingGroup;
                if (!isset($group['distributions'][$audience])) {
                    $group['distributions'][$audience] = array(
                        'level' => $effective['level'],
                        'rank' => $effective['rank'],
                        'sharing_group' => array(
                            'id' => $effective['sharing_group_id'],
                            'name' => $effective['sharing_group_name'],
                        ),
                    );
                }
            }

            foreach (self::tagsOf($row) as $tag) {
                $group['tags'][$tag['name']] = $tag;
            }
            unset($group);
        }

        $facets = self::facets($groups, $eventMeta, $orgs);
        $distinct = count($groups);

        $ranked = array_values($groups);
        usort($ranked, function ($a, $b) use ($rank) {
            $events = count($b['events']) - count($a['events']);
            $last = $a['last'] === $b['last']
                ? 0
                : $b['last'] - $a['last'];
            $first = $rank === 'recent' ? $last : $events;
            if ($first !== 0) {
                return $first;
            }
            $second = $rank === 'recent' ? $events : $last;
            if ($second !== 0) {
                return $second;
            }
            return strcmp($a['value'], $b['value']);
        });
        $matched = $ranked;
        if (!empty($filters)) {
            $matched = array();
            foreach ($ranked as $group) {
                if (self::groupMatches($group, $filters, $orgs,
                    $ourObjects)
                ) {
                    $matched[] = $group;
                }
            }
        }
        $listed = array_slice($matched, 0, $rowCap);
        $rows = self::valueRows($listed, $orgs, $ourObjects);
        /*
         * Two numbers per facet entry, because they answer different
         * questions and the control was answering with the wrong one.
         * `count` is the value's whole neighbourhood; `listed` is how
         * much of it the table below actually carries. A narrowing
         * control that can only reach the listed rows has to know when
         * those are all of them.
         */
        $facets = self::markListed($facets, $rows);

        $eventRows = self::eventRollup($groups, $eventMeta, $orgs);
        $objectRows = self::objectRollup($objects, $orgs, $rowCap);

        ksort($categories);

        return array(
            'suppressed' => false,
            /*
             * §14.6: every number here is the viewer's own, and the
             * panel says nothing about what it cannot see — so `stored`
             * and `visible` are the same number and `hidden` is zero.
             * The keys survive because the template reads them; what
             * does not survive is any sentence subtracting one from the
             * other, which would have named a hidden count.
             */
            'stored' => count($rows),
            'visible' => count($rows),
            'hidden' => 0,
            'distinct_values' => $distinct,
            /*
             * What the filter left, before the cut. The pager prints it
             * so a narrowed table says `1–8 of 100 (9,791 match)`
             * rather than implying the hundred are all there were.
             */
            'matched' => count($matched),
            'filters' => $filters,
            'rank' => $rank,
            'events' => count($eventRows),
            'page_size' => $pageSize,
            'rollups' => array(
                'value' => array(
                    'total' => $distinct,
                    'rows' => $rows,
                ),
                'event' => array(
                    'total' => count($eventRows),
                    'rows' => $eventRows,
                ),
                'object' => array(
                    'total' => count($objects),
                    'rows' => $objectRows,
                ),
            ),
            'facets' => $facets,
            'categories' => array_keys($categories),
        );
    }

    /**
     * @param string $value
     * @return array
     */
    private static function emptyGroup($value)
    {
        return array(
            'value' => $value,
            'types' => array(),
            'categories' => array(),
            'events' => array(),
            'orgs' => array(),
            'objects' => array(),
            'object_ids' => array(),
            'tags' => array(),
            'last' => 0,
            'distributions' => array(),
            'occurrences' => 0,
        );
    }

    /**
     * The listed rows, in the shape the table reads.
     *
     * **A value with more than one type gets one badge**, the type it
     * most often carries. The alternative — one row per `(value, type)`
     * pair — would make the row count exceed the distinct-value count
     * the header prints, and a table whose length contradicts its own
     * badge is worse than a badge that rounds. The type facet still
     * counts the value under *every* type it appeared as, so the
     * narrowing control does not lose the second one.
     *
     * @param array $groups
     * @param array $orgs
     * @return array
     */
    private static function valueRows(array $groups, array $orgs,
        array $ourObjects = array()
    ) {
        $rows = array();
        foreach ($groups as $group) {
            $audiences = self::audiences($group['distributions']);
            $names = array();
            foreach (array_keys($group['orgs']) as $orgId) {
                $names[] = self::orgName($orgs, $orgId);
            }
            sort($names);
            $rows[] = array(
                'value' => $group['value'],
                'type' => self::dominant($group['types']),
                'category' => self::dominant($group['categories']),
                'shared_events' => count($group['events']),
                'orgs' => $names,
                'last_together' => $group['last'] > 0
                    ? date('Y-m-d', $group['last'])
                    : '',
                /*
                 * The widest of the set, kept for the column sort and
                 * for a caller that wants one number. The cell reads
                 * `distributions`.
                 */
                'distribution' => empty($audiences)
                    ? 5
                    : $audiences[0]['level'],
                'distributions' => $audiences,
                'object' => empty($group['objects'])
                    ? null
                    : self::dominant($group['objects']),
                'tags' => array_values($group['tags']),
                'events' => array_keys($group['events']),
                'tokens' => self::groupTokens($group, $orgs, $ourObjects),
            );
        }
        return $rows;
    }

    /**
     * The same neighbourhood counted by event: how many distinct values
     * this value shares with each event it sits in.
     *
     * @param array $groups
     * @param array $eventMeta
     * @param array $orgs
     * @return array
     */
    private static function eventRollup(array $groups, array $eventMeta,
        array $orgs
    ) {
        $counts = array();
        foreach ($groups as $group) {
            foreach (array_keys($group['events']) as $eventId) {
                if (!isset($counts[$eventId])) {
                    $counts[$eventId] = 0;
                }
                $counts[$eventId]++;
            }
        }
        arsort($counts);
        $rows = array();
        foreach ($counts as $eventId => $shared) {
            $meta = isset($eventMeta[$eventId])
                ? $eventMeta[$eventId]
                : array();
            $rows[] = array(
                'event' => array(
                    'id' => $eventId,
                    'info' => isset($meta['info']) ? $meta['info'] : '',
                    'date' => isset($meta['date']) ? $meta['date'] : '',
                ),
                'org' => self::orgName(
                    $orgs,
                    isset($meta['orgc_id']) ? $meta['orgc_id'] : 0
                ),
                'shared_values' => $shared,
                /*
                 * One row is one event here, so its audience is the
                 * event's own column and there is nothing to fold. The
                 * list shape is the value roll-up's, so one cell
                 * renders both.
                 */
                'distribution' => isset($meta['distribution'])
                    ? (int)$meta['distribution']
                    : 0,
                'distributions' => array(array(
                    'level' => isset($meta['distribution'])
                        ? (int)$meta['distribution']
                        : 0,
                    'sharing_group' => array(
                        'id' => isset($meta['sharing_group_id'])
                            ? (int)$meta['sharing_group_id']
                            : null,
                        'name' => isset($meta['sharing_group_name'])
                            ? $meta['sharing_group_name']
                            : null,
                    ),
                )),
                'tags' => isset($meta['tags']) ? $meta['tags'] : array(),
            );
        }
        return $rows;
    }

    /**
     * And by the object the neighbouring attribute sits in.
     *
     * Capped, because this is the roll-up that can be longer than the
     * other two rather than shorter: on the verification instance a
     * single event holds 32,921 objects, one per row of a flood
     * capture, and every one of them would otherwise be a row here.
     *
     * @param array $objects
     * @param array $orgs
     * @param int $cap
     * @return array
     */
    private static function objectRollup(array $objects, array $orgs, $cap)
    {
        $rows = array();
        foreach ($objects as $object) {
            $rows[] = array(
                'object' => $object['object'],
                'event' => $object['event'],
                'org' => self::orgName($orgs, $object['org']),
                'values' => count($object['values']),
                'relations' => array_keys($object['relations']),
            );
        }
        usort($rows, function ($a, $b) {
            return $b['values'] - $a['values'];
        });
        return array_slice($rows, 0, $cap);
    }

    /**
     * The six counted groups.
     *
     * **A facet counts distinct values, not rows** — `Type · domain 5`
     * means five of the listed neighbours are domains, which is the
     * number a reader about to tick it wants. The per-event counts
     * therefore sum past the value total, because one neighbour can
     * share more than one event; that is the correct reading and the
     * panel already says so.
     *
     * Counted from the folded groups rather than from the rows, so a
     * value seen forty times in one event counts once.
     *
     * @param array $groups
     * @param array $eventMeta
     * @param array $orgs
     * @return array
     */
    private static function facets(array $groups, array $eventMeta,
        array $orgs
    ) {
        $facets = array(
            'event' => array(),
            'organisation' => array(),
            'type' => array(),
            'object' => array(),
            'tag' => array(),
            'distribution' => array(),
            'sharing_group' => array(),
        );
        foreach ($groups as $group) {
            foreach (array_keys($group['events']) as $eventId) {
                $meta = isset($eventMeta[$eventId])
                    ? $eventMeta[$eventId]
                    : array();
                self::bump(
                    $facets['event'],
                    (string)$eventId,
                    sprintf(
                        '#%d %s',
                        $eventId,
                        isset($meta['info']) ? $meta['info'] : ''
                    )
                );
            }
            foreach (array_keys($group['orgs']) as $orgId) {
                $name = self::orgName($orgs, $orgId);
                self::bump(
                    $facets['organisation'],
                    ValueStatsTool::facetToken($name),
                    $name
                );
            }
            foreach (array_keys($group['types']) as $type) {
                self::bump(
                    $facets['type'],
                    ValueStatsTool::facetToken($type),
                    $type
                );
            }
            foreach (array_keys($group['objects']) as $name) {
                self::bump(
                    $facets['object'],
                    ValueStatsTool::facetToken($name),
                    $name
                );
            }
            foreach ($group['tags'] as $tag) {
                self::bump(
                    $facets['tag'],
                    ValueStatsTool::facetToken($tag['name']),
                    $tag['name'],
                    array('tag' => $tag, 'local' => 0)
                );
            }
            /*
             * Counted under every audience it has, the way the value is
             * already counted under every type, org, event and tag it
             * has. A facet is a membership test, so folding the set to
             * one would hide a row from a filter it genuinely matches.
             */
            foreach ($group['distributions'] as $audience) {
                self::bump(
                    $facets['distribution'],
                    (string)$audience['level'],
                    null,
                    array('level' => $audience['level'])
                );
                if ($audience['level'] !== 4
                    || empty($audience['sharing_group']['id'])
                ) {
                    continue;
                }
                self::bump(
                    $facets['sharing_group'],
                    (string)$audience['sharing_group']['id'],
                    $audience['sharing_group']['name'] !== null
                        ? $audience['sharing_group']['name']
                        : sprintf(
                            __('Sharing group #%s'),
                            $audience['sharing_group']['id']
                        )
                );
            }
        }
        foreach ($facets as $key => $group) {
            $facets[$key] = array_slice(
                self::rank($group),
                0,
                self::FACET_CAP
            );
        }
        return $facets;
    }

    /**
     * The object siblings, aggregated to one row per `(template,
     * relation, sibling value)` triple.
     *
     * Phase 18's rule, applied to real rows: the same sibling seen five
     * hundred times is one row that says five hundred. The event link
     * survives wherever the fold left exactly one event to point at,
     * which is every single-object row and any row whose objects all
     * sit in the same event. Past that the row can only give a count.
     *
     * @param array $rows Sibling rows, `Value::neighbourRowsFor`
     * @param array $context `orgs`, `objects`, `in_objects`, `cap`,
     *                       `page_size`
     * @return array The `siblings` shape the template reads
     */
    public static function siblings(array $rows, array $context)
    {
        $orgs = isset($context['orgs']) ? $context['orgs'] : array();
        /*
         * Which relation each object files *this* value under. It has
         * to arrive from the caller: the rows here are by definition
         * the attributes that are not ours, so our own end of the join
         * appears nowhere in them. Without it an edge can say
         * `passive-dns · rdata` but not `passive-dns · rrname → rdata`,
         * and the second is the one that tells a reader which end they
         * are standing on.
         */
        $ours = isset($context['relations'])
            ? $context['relations']
            : array();
        $triples = array();
        $objects = array();
        $templates = array();
        foreach ($rows as $row) {
            $attribute = $row['Attribute'];
            if (empty($row['Object']['id'])) {
                continue;
            }
            $objectId = (int)$row['Object']['id'];
            $eventId = (int)$attribute['event_id'];
            $template = $row['Object']['name'];
            $relation = empty($attribute['object_relation'])
                ? ''
                : $attribute['object_relation'];
            $value = isset($attribute['value']) ? $attribute['value'] : '';
            $ourRelation = isset($ours[$objectId]) ? $ours[$objectId] : '';
            $key = $template . "\0" . $relation . "\0" . $value;
            if (!isset($triples[$key])) {
                $triples[$key] = array(
                    'object' => $template,
                    'relation' => $relation,
                    'value' => $value,
                    'type' => $attribute['type'],
                    'objects' => array(),
                    'events' => array(),
                    'orgs' => array(),
                    'ours' => array(),
                );
            }
            $triples[$key]['objects'][$objectId] = true;
            $triples[$key]['events'][$eventId] = true;
            $triples[$key]['orgs'][(int)$row['Event']['orgc_id']] = true;
            if ($ourRelation !== '') {
                self::tally($triples[$key]['ours'], $ourRelation);
            }
            $objects[$objectId] = true;

            if (!isset($templates[$template])) {
                $templates[$template] = array(
                    'objects' => array(),
                    'values' => array(),
                    'events' => array(),
                    'ours' => array(),
                    'relations' => array(),
                    'linking' => array(),
                );
            }
            $templates[$template]['objects'][$objectId] = true;
            $templates[$template]['values'][$value] = true;
            $templates[$template]['events'][$eventId] = true;
            if ($relation !== '') {
                self::tally($templates[$template]['relations'], $relation);
                /*
                 * Ranked apart, because the edge label is the template's
                 * *claim* and its bookkeeping is not part of it. Every
                 * `passive-dns` object carries `count`, `origin` and
                 * two timestamps as well as `rdata`, and by row count
                 * the bookkeeping wins — so a label ranked over all of
                 * them reads `rrname → count, origin, time_first`,
                 * which names everything the object says except the
                 * thing it exists to say. `disable_correlation` is
                 * MISP's own record of which is which.
                 */
                if (empty($attribute['disable_correlation'])) {
                    self::tally($templates[$template]['linking'],
                        $relation);
                }
            }
            if ($ourRelation !== '') {
                self::tally($templates[$template]['ours'], $ourRelation);
            }
        }

        $inObjects = (int)$context['in_objects'];
        $limit = (int)$context['cap'];
        /*
         * Whether the fold saw every object this value sits in, which
         * is what decides if `one event` is a fact about the value or
         * only about the objects the cap let through.
         */
        $exact = $inObjects <= $limit;

        $out = array();
        foreach ($triples as $triple) {
            $events = array_keys($triple['events']);
            $held = count($triple['objects']);
            /*
             * A single-object row keeps the link it has always had:
             * whatever the cap left out, that object is in that event.
             * A row standing for several only claims to name their one
             * event where the fold was over all of them.
             */
            $oneEvent = $held === 1
                || ($exact && count($events) === 1);
            $names = array();
            foreach (array_keys($triple['orgs']) as $orgId) {
                $names[] = self::orgName($orgs, $orgId);
            }
            sort($names);
            $out[] = array(
                'object' => $triple['object'],
                'relation' => $triple['relation'],
                'our_relation' => self::dominant($triple['ours']),
                'value' => $triple['value'],
                'type' => $triple['type'],
                'objects' => $held,
                'events' => count($events),
                'event' => $oneEvent ? $events[0] : null,
                'orgs' => $names,
                'org_total' => count($names),
            );
        }
        usort($out, function ($a, $b) {
            if ($a['objects'] !== $b['objects']) {
                return $b['objects'] - $a['objects'];
            }
            return strcmp($a['value'], $b['value']);
        });

        $triples = count($out);
        /*
         * **The rows are capped and the total is not.** `443` sits in
         * 394 objects whose 2,691 sibling attributes fold to some two
         * thousand triples, and listing all of them made this one
         * fragment 2.4 MB — 2.8 MB for a reader who can see more of
         * them. The badge and the pager print `total`, so the cut shows
         * up as *1–8 of 100 (2,041 in total)* rather than as a table
         * that quietly stops.
         */
        $rowCap = isset($context['row_cap'])
            ? (int)$context['row_cap']
            : 100;
        /*
         * Folded before the cut, so the counts describe the value's
         * whole sibling set rather than the hundred rows that fit. That
         * is the same bargain the ranked table's bar strikes, and the
         * bar carries the same sentence about it: a count larger than
         * the table can show names something outside the 100 carried.
         */
        $facets = self::siblingFacets($out);
        $out = array_slice($out, 0, $rowCap);
        return array(
            'rows' => $out,
            'facets' => $facets,
            'templates' => self::templateRollup($templates, $context),
            'total' => $triples,
            'raw' => count($rows),
            'objects' => count($objects),
            'in_objects' => $inObjects,
            'cap' => array(
                'limit' => $limit,
                'applied' => $inObjects > $limit,
            ),
            // §14.6: no count of what the reader cannot see.
            'hidden' => 0,
            'page_size' => isset($context['page_size'])
                ? (int)$context['page_size']
                : 8,
        );
    }

    /**
     * One row per object template, which is what the graph draws when
     * the sibling set is past reading.

     * **This is the roll-up that lets nothing be truncated.** A ranked
     * cap answers `0.0.0.0` with twelve of 35,102 siblings and no way
     * to reach the rest; two template rows carrying 32,922 and 1 answer
     * it completely, and the larger number is the finding — 32,922
     * near-identical `paloalto-threat-event` objects read as
     * flood-capture noise at a glance.

     * **The object count is the value's own, not the fold's.** The
     * caller caps the objects it reads at `SIBLING_OBJECT_CAP`, so
     * counting the folded ones would print 500 where the truth is
     * 32,922 — a roll-up quietly lying at the one number it exists to
     * carry. `template_totals` is the census the caller runs when its
     * cap bit; `folded` stays beside it so a reader of this array can
     * still tell how much of the template the values came from.
     *
     * @param array $templates Per-template sets, from the fold above
     * @param array $context `template_totals` where the cap bit
     * @return array
     */
    private static function templateRollup(array $templates,
        array $context
    ) {
        $totals = isset($context['template_totals'])
            ? $context['template_totals']
            : array();
        /*
         * Every template in the census, not only the ones the fold
         * reached. `0.0.0.0` sits in 32,921 `paloalto-threat-event`
         * objects and one `pe`, and the read stops at 500 — all of them
         * paloalto — so a roll-up built from the fold alone would draw
         * one node and silently lose the template that is actually
         * unusual. A template with no folded row draws its count and no
         * values, which is exactly what is known about it.
         */
        foreach ($totals as $name => $count) {
            if (!isset($templates[$name])) {
                $templates[$name] = array(
                    'objects' => array(),
                    'values' => array(),
                    'events' => array(),
                    'ours' => array(),
                    'relations' => array(),
                    'linking' => array(),
                );
            }
        }
        $rows = array();
        foreach ($templates as $name => $group) {
            $folded = count($group['objects']);
            $known = isset($totals[$name])
                ? (int)$totals[$name]
                : $folded;
            $relations = empty($group['linking'])
                ? $group['relations']
                : $group['linking'];
            arsort($relations);
            $rows[] = array(
                'object' => $name,
                'objects' => max($folded, $known),
                'folded' => $folded,
                'values' => count($group['values']),
                'events' => count($group['events']),
                'our_relation' => self::dominant($group['ours']),
                'relations' => array_slice(array_keys($relations), 0, 4),
            );
        }
        usort($rows, function ($a, $b) {
            if ($a['objects'] !== $b['objects']) {
                return $b['objects'] - $a['objects'];
            }
            return strcmp($a['object'], $b['object']);
        });
        return $rows;
    }

    /**
     * Section five: the object joins that carry a pair of dates.
     *
     * Folded from **the rows the sibling section already read**, so it
     * costs no query of its own. Grouped by object rather than by
     * triple, because the dates are a property of the object and the
     * whole point of the panel is to put them on the edge.

     * **A dated relation is an object recording two or more dates.**
     * One date is a moment, not a span, and the instance says why the
     * distinction has to be drawn: 40,098 objects carry exactly one
     * `datetime`, and 32,892 of those are `paloalto-threat-event`
     * saying when the row was generated, with another 6,740 saying when
     * a sample was last submitted. Neither is a claim about when the
     * relation held. Requiring a pair keeps `passive-dns`
     * (`time_first`/`time_last`) and `url-honeypot-detection`
     * (`first-seen`/`last-seen`) and drops the bookkeeping, without a
     * per-template list to maintain.

     * **First and last are the earliest and the latest, and each cell
     * carries the object's own word for it.** The column header is
     * generic and the label under the date is not, which is §23.2's
     * rule applied to a timestamp: a label is true where a
     * classification would be arguing.

     * **The far value is one MISP itself marks as linking.**
     * `disable_correlation` is 0 on `rrname` and `rdata` and 1 on
     * `rrtype`, `count`, `origin` and both timestamps — the template's
     * own record of which attributes are there to join and which are
     * there to describe. Nothing here classifies templates; it reads a
     * column that is already written.
     *
     * @param array $rows fetchAttributesSimple rows, object-scoped
     * @param array $context `orgs`, `relations`, `in_objects`, `cap`,
     *                       `row_cap`, `page_size`
     * @return array
     */
    public static function dated(array $rows, array $context)
    {
        $orgs = isset($context['orgs']) ? $context['orgs'] : array();
        $objects = array();
        foreach ($rows as $row) {
            $attribute = $row['Attribute'];
            if (empty($row['Object']['id'])) {
                continue;
            }
            $objectId = (int)$row['Object']['id'];
            if (!isset($objects[$objectId])) {
                $objects[$objectId] = array(
                    'id' => $objectId,
                    'object' => $row['Object']['name'],
                    'event' => (int)$attribute['event_id'],
                    'org' => (int)$row['Event']['orgc_id'],
                    'dates' => array(),
                    'values' => array(),
                    'origin' => null,
                );
            }
            $relation = empty($attribute['object_relation'])
                ? ''
                : $attribute['object_relation'];
            $value = isset($attribute['value']) ? $attribute['value'] : '';
            if ($attribute['type'] === self::DATE_TYPE) {
                $at = strtotime($value);
                if ($at !== false) {
                    $objects[$objectId]['dates'][] = array(
                        'at' => $at,
                        'raw' => $value,
                        'relation' => $relation,
                    );
                }
                continue;
            }
            /*
             * Named, because the object names it. `passive-dns` records
             * where a resolution was observed on 646 of its 673 rows
             * here, and a resolution history without its source is a
             * list of claims with no provenance.
             */
            if ($relation === self::ORIGIN_RELATION) {
                $objects[$objectId]['origin'] = $value;
                continue;
            }
            if (!empty($attribute['disable_correlation']) || $value === '') {
                continue;
            }
            $objects[$objectId]['values'][$value] = array(
                'value' => $value,
                'relation' => $relation,
                'type' => $attribute['type'],
            );
        }

        $out = array();
        $templates = array();
        $dated = 0;
        foreach ($objects as $object) {
            if (count($object['dates']) < 2 || empty($object['values'])) {
                continue;
            }
            usort($object['dates'], function ($a, $b) {
                return $a['at'] === $b['at'] ? 0 : ($a['at'] - $b['at']);
            });
            $first = reset($object['dates']);
            $last = end($object['dates']);
            $dated++;
            self::tally($templates, $object['object']);
            foreach ($object['values'] as $far) {
                $out[] = array(
                    'value' => $far['value'],
                    'relation' => $far['relation'],
                    'type' => $far['type'],
                    'object' => $object['object'],
                    'object_id' => $object['id'],
                    'event' => $object['event'],
                    'org' => self::orgName($orgs, $object['org']),
                    'origin' => $object['origin'],
                    'first' => $first,
                    'last' => $last,
                );
            }
        }

        /*
         * Newest first for the cut, oldest first for the eye. A
         * resolution history reads forwards — four addresses in
         * fourteen days, four years of nothing, then one more — but a
         * cap taken off the front of that would keep 2017 and drop
         * last week. So the cut keeps the most recent rows and the
         * table then reads them in the order the story runs.
         */
        usort($out, function ($a, $b) {
            if ($a['last']['at'] !== $b['last']['at']) {
                return $b['last']['at'] - $a['last']['at'];
            }
            return strcmp($a['value'], $b['value']);
        });
        $total = count($out);
        $rowCap = isset($context['row_cap'])
            ? (int)$context['row_cap']
            : 100;
        $out = array_slice($out, 0, $rowCap);
        usort($out, function ($a, $b) {
            if ($a['first']['at'] !== $b['first']['at']) {
                return $a['first']['at'] - $b['first']['at'];
            }
            return strcmp($a['value'], $b['value']);
        });
        arsort($templates);

        $inObjects = isset($context['in_objects'])
            ? (int)$context['in_objects']
            : count($objects);
        $limit = isset($context['cap']) ? (int)$context['cap'] : 0;
        return array(
            'rows' => $out,
            'total' => $total,
            'objects' => $dated,
            'read_objects' => count($objects),
            'in_objects' => $inObjects,
            'templates' => array_keys($templates),
            'cap' => array(
                'limit' => $limit,
                'applied' => $limit > 0 && $inObjects > $limit,
            ),
            // §14.6: no count of what the reader cannot see.
            'hidden' => 0,
            'page_size' => isset($context['page_size'])
                ? (int)$context['page_size']
                : 8,
        );
    }

    /**
     * Section six: what MISP itself records as related to this value.
     *
     * The rows arrive already resolved and already ACL-filtered — this
     * only folds them, ranks them and counts the templates, which is
     * the division of labour every other fold here keeps.
     *
     * Ranked by relationship type and then by the far object's
     * template, so ten `hosted-by` references read as one block rather
     * than as ten unrelated rows.
     *
     * @param array $rows From ValueProfile::objectReferences
     * @param array $context `row_cap`, `page_size`, `in_objects`
     * @return array
     */
    public static function references(array $rows, array $context)
    {
        $types = array();
        $templates = array();
        foreach ($rows as $row) {
            self::tally($types, $row['relationship']);
            if (!empty($row['far']['object'])) {
                self::tally($templates, $row['far']['object']);
            }
        }
        usort($rows, function ($a, $b) {
            $type = strcmp($a['relationship'], $b['relationship']);
            if ($type !== 0) {
                return $type;
            }
            $far = strcmp($a['far']['object'], $b['far']['object']);
            return $far === 0
                ? strcmp($a['far']['label'], $b['far']['label'])
                : $far;
        });
        $total = count($rows);
        $rowCap = isset($context['row_cap'])
            ? (int)$context['row_cap']
            : 100;
        arsort($types);
        arsort($templates);
        $read = isset($context['read_objects'])
            ? (int)$context['read_objects']
            : 0;
        $limit = isset($context['object_cap'])
            ? (int)$context['object_cap']
            : 0;
        return array(
            'rows' => array_slice($rows, 0, $rowCap),
            'total' => $total,
            'types' => $types,
            'templates' => array_keys($templates),
            'read_objects' => $read,
            'occurrences' => isset($context['occurrences'])
                ? (int)$context['occurrences']
                : 0,
            /*
             * How many of this value's own objects carry a reference at
             * all — counted before the both-ends-ours rows are dropped,
             * because the question is whether the value's objects are
             * referenced, not whether any survived the fold.
             */
            'with_references' => isset($context['with_references'])
                ? (int)$context['with_references']
                : 0,
            'cap' => array(
                'limit' => $limit,
                'applied' => $limit > 0 && $read >= $limit,
            ),
            // §14.6: no count of what the reader cannot see.
            'hidden' => 0,
            'page_size' => isset($context['page_size'])
                ? (int)$context['page_size']
                : 8,
        );
    }

    /**
     * The sibling section's own counted facets.
     *
     * Its own rather than the ranked table's, because the two sections
     * fold different row sets: the bar above is folded from the events
     * the scan read, and these from the objects the value sits in. An
     * object can survive an event the scan skipped for being oversized
     * — that is the whole reason the sibling table renders under a
     * suppressed band — so one bar over both would print a count that
     * is exact for neither.
     *
     * The keys are prefixed. `value_facet_group` derives a DOM id from
     * the key, and two groups called `type` in one panel would collide.
     *
     * @param array $rows Aggregated triples, pre-cap
     * @return array Facet groups, ranked and capped
     */
    private static function siblingFacets(array $rows)
    {
        $facets = array(
            'sibobject' => array(),
            'sibrelation' => array(),
            'sibtype' => array(),
            'siborg' => array(),
        );
        foreach ($rows as $row) {
            self::bump(
                $facets['sibobject'],
                ValueStatsTool::facetToken($row['object']),
                $row['object']
            );
            if ($row['relation'] !== '') {
                self::bump(
                    $facets['sibrelation'],
                    ValueStatsTool::facetToken($row['relation']),
                    $row['relation']
                );
            }
            self::bump(
                $facets['sibtype'],
                ValueStatsTool::facetToken($row['type']),
                $row['type']
            );
            foreach ($row['orgs'] as $name) {
                self::bump(
                    $facets['siborg'],
                    ValueStatsTool::facetToken($name),
                    $name
                );
            }
        }
        foreach ($facets as $key => $group) {
            $facets[$key] = array_slice(
                self::rank($group),
                0,
                self::FACET_CAP
            );
        }
        return $facets;
    }

    /**
     * Which network blocks on the instance contain this address.
     *
     * The near-match section's only live engine, and it is **re-derived
     * rather than read**: the correlation table records no provenance,
     * so nothing in it says a row came from CIDR containment. What it
     * does record — `Correlation::cidrCorrelation` — is the same list
     * this walks, so the answer is the engine's own and not an
     * approximation of it.
     *
     * IPv6 is tested by prefix comparison on the packed address, which
     * is what `Correlation::__ipv6InCidr` does; the two must agree or
     * the panel would name a containment the engine did not write.
     *
     * @param string $value An IPv4/IPv6 address, or a block
     * @param array $blocks CIDR strings, `Correlation::getCidrList()`
     * @return array Blocks containing it, tightest prefix first
     */
    public static function containingBlocks($value, array $blocks)
    {
        $address = strpos($value, '/') === false
            ? $value
            : substr($value, 0, strpos($value, '/'));
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return array();
        }
        $matches = array();
        foreach ($blocks as $block) {
            if ($block === $value || strpos($block, '/') === false) {
                continue;
            }
            $prefix = self::containment($address, $block);
            if ($prefix === null) {
                continue;
            }
            $matches[$block] = $prefix;
        }
        arsort($matches);
        $rows = array();
        foreach ($matches as $block => $prefix) {
            $rows[] = array('block' => $block, 'prefix' => $prefix);
        }
        return $rows;
    }

    /**
     * @param string $address
     * @param string $block
     * @return int|null The block's prefix length, or null if it does
     *                  not contain the address
     */
    private static function containment($address, $block)
    {
        list($network, $bits) = array_pad(explode('/', $block, 2), 2, null);
        $bits = (int)$bits;
        $packedAddress = @inet_pton($address);
        $packedNetwork = @inet_pton($network);
        if ($packedAddress === false || $packedNetwork === false) {
            return null;
        }
        $width = strlen($packedAddress) * 8;
        if (strlen($packedAddress) !== strlen($packedNetwork)
            || $bits < 0
            || $bits > $width
        ) {
            return null;
        }
        $wholeBytes = intdiv($bits, 8);
        if ($wholeBytes > 0
            && strncmp($packedAddress, $packedNetwork, $wholeBytes) !== 0
        ) {
            return null;
        }
        $spare = $bits % 8;
        if ($spare > 0) {
            $mask = 0xff << (8 - $spare) & 0xff;
            if ((ord($packedAddress[$wholeBytes]) & $mask)
                !== (ord($packedNetwork[$wholeBytes]) & $mask)
            ) {
                return null;
            }
        }
        return $bits;
    }

    /**
     * How many addresses a prefix covers, as a string.
     *
     * A `/8` of IPv6 is 2^120 addresses, which no integer here holds,
     * so the count is formatted from a power of two rather than
     * computed into one. The v4 case stays exact and printable, which
     * is the case the panel's argument rests on — `/22` reads 1,024 and
     * `/16` reads 65,536, so *closeness* is grounded in a number rather
     * than in a bar's width.
     *
     * @param int $prefix
     * @param int $width 32 or 128
     * @return string
     */
    public static function addressSpace($prefix, $width)
    {
        $free = max(0, (int)$width - (int)$prefix);
        if ($free <= 62) {
            return number_format(pow(2, $free));
        }
        return sprintf('2^%d', $free);
    }

    /**
     * @param array $group
     * @param string $token
     * @param string|null $label
     * @param array $extra
     * @return void
     */
    private static function bump(array &$group, $token, $label,
        array $extra = array()
    ) {
        if (!isset($group[$token])) {
            $group[$token] = array_merge(
                array('value' => $token, 'count' => 0),
                $label === null ? array() : array('label' => $label),
                $extra
            );
        }
        $group[$token]['count']++;
    }

    /**
     * The narrowing keys, in the order a row prints them.
     *
     * A key absent from here is a control the fold cannot apply, so it
     * is also a control the panel must not offer.
     */
    private static $tokenKeys = array('type', 'category', 'organisation',
        'event', 'tag', 'distribution', 'sharing_group', 'object',
        'sibling');

    /**
     * One key's tokens for one group.
     *
     * Read by both the row builder and the filter, so the string a
     * facet counts and the string a filter matches cannot drift.
     *
     * @param array $group
     * @param string $key
     * @param array $orgs
     * @param array $ourObjects
     * @return array
     */
    private static function tokensFor(array $group, $key, array $orgs,
        array $ourObjects
    ) {
        $tokens = array();
        switch ($key) {
            case 'type':
                foreach (array_keys($group['types']) as $type) {
                    $tokens[] = 'type:'
                        . ValueStatsTool::facetToken($type);
                }
                break;
            case 'category':
                foreach (array_keys($group['categories']) as $category) {
                    $tokens[] = 'category:'
                        . ValueStatsTool::facetToken($category);
                }
                break;
            case 'organisation':
                foreach (array_keys($group['orgs']) as $orgId) {
                    $tokens[] = 'organisation:'
                        . ValueStatsTool::facetToken(
                            self::orgName($orgs, $orgId)
                        );
                }
                break;
            case 'event':
                foreach (array_keys($group['events']) as $eventId) {
                    $tokens[] = 'event:' . (int)$eventId;
                }
                break;
            case 'tag':
                foreach ($group['tags'] as $tag) {
                    $tokens[] = 'tag:'
                        . ValueStatsTool::facetToken($tag['name']);
                }
                break;
            case 'distribution':
                foreach ($group['distributions'] as $audience) {
                    $tokens[] = 'distribution:' . (int)$audience['level'];
                }
                break;
            case 'sharing_group':
                foreach ($group['distributions'] as $audience) {
                    if (empty($audience['sharing_group']['id'])) {
                        continue;
                    }
                    $tokens[] = 'sharing_group:'
                        . (int)$audience['sharing_group']['id'];
                }
                break;
            case 'object':
                foreach (array_keys($group['objects']) as $name) {
                    $tokens[] = 'object:'
                        . ValueStatsTool::facetToken($name);
                }
                break;
            case 'sibling':
                foreach (array_keys($group['objects']) as $name) {
                    if (isset($ourObjects[$name])) {
                        $tokens[] = 'sibling:yes';
                        break;
                    }
                }
                break;
        }
        return $tokens;
    }

    /**
     * Whether one group survives the narrowing.
     *
     * Disjunctive inside a key and conjunctive across them, which is
     * what the facet bar means by ticking two boxes in one dropdown
     * and one box in two.
     *
     * @param array $group
     * @param array $filters
     * @param array $orgs
     * @param array $ourObjects
     * @return bool
     */
    private static function groupMatches(array $group, array $filters,
        array $orgs, array $ourObjects
    ) {
        if (isset($filters['text'])
            && mb_strpos(
                mb_strtolower($group['value']),
                $filters['text']
            ) === false
        ) {
            return false;
        }
        if (isset($filters['min_shared'])
            && count($group['events']) < $filters['min_shared']
        ) {
            return false;
        }
        foreach (self::$tokenKeys as $key) {
            if (empty($filters[$key])) {
                continue;
            }
            $tokens = self::tokensFor($group, $key, $orgs, $ourObjects);
            $hit = false;
            foreach ($filters[$key] as $wanted) {
                if (in_array($key . ':' . $wanted, $tokens, true)) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                return false;
            }
        }
        /*
         * A select is a second constraint on a dimension the dropdown
         * may already constrain, and the panel means them differently:
         * two ticks in the `Type` dropdown are *either*, a type picked
         * in the select on top of them is *and also*. Merging the two
         * would quietly turn the select into a third tick.
         */
        if (!empty($filters['select'])) {
            foreach ($filters['select'] as $key => $wanted) {
                $tokens = self::tokensFor($group, $key, $orgs,
                    $ourObjects);
                if (!in_array($key . ':' . $wanted, $tokens, true)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * The narrowing, reduced to what this fold can apply.
     *
     * Everything here arrives from a query string, so a key nobody
     * declared is dropped rather than trusted, and the two scalar
     * controls are cast to what they are.
     *
     * @param array $filters
     * @return array
     */
    private static function cleanFilters(array $filters)
    {
        $out = array();
        foreach (self::$tokenKeys as $key) {
            if (empty($filters[$key])) {
                continue;
            }
            $wanted = array();
            foreach ((array)$filters[$key] as $token) {
                if (!is_scalar($token) || (string)$token === '') {
                    continue;
                }
                $wanted[] = (string)$token;
            }
            if (!empty($wanted)) {
                $out[$key] = array_values(array_unique($wanted));
            }
        }
        if (!empty($filters['select'])
            && is_array($filters['select'])
        ) {
            $selects = array();
            foreach ($filters['select'] as $key => $token) {
                if (!in_array($key, self::$tokenKeys, true)
                    || !is_scalar($token)
                    || (string)$token === ''
                ) {
                    continue;
                }
                $selects[$key] = (string)$token;
            }
            if (!empty($selects)) {
                $out['select'] = $selects;
            }
        }
        if (!empty($filters['text']) && is_scalar($filters['text'])) {
            $out['text'] = mb_strtolower(trim((string)$filters['text']));
            if ($out['text'] === '') {
                unset($out['text']);
            }
        }
        if (isset($filters['min_shared'])
            && (int)$filters['min_shared'] > 1
        ) {
            $out['min_shared'] = (int)$filters['min_shared'];
        }
        return $out;
    }

    /**
     * What a row is matched on, by the facet bar and by the fold.
     *
     * **Every value the group carried, not the one it carries best.**
     * The facets count a value under each type, category and object it
     * appeared as — the `dominant()` badge is a display choice — so
     * emitting only the dominant one left the counted facet unable to
     * find rows it had just counted. `type` and `object` were the two
     * worst dropdowns on the tab for exactly this reason, before the
     * row cap is even considered.
     *
     * The slug is `ValueStatsTool::facetToken`, which is what built the
     * facet entries, so the two cannot drift apart.
     *
     * @param array $group
     * @param array $orgs
     * @param array $ourObjects Object templates this value sits in
     * @return array
     */
    private static function groupTokens(array $group, array $orgs,
        array $ourObjects
    ) {
        $tokens = array();
        foreach (self::$tokenKeys as $key) {
            foreach (self::tokensFor($group, $key, $orgs, $ourObjects)
                as $token
            ) {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }

    /**
     * How much of each facet the listed rows actually hold.
     *
     * @param array $facets
     * @param array $rows Listed rows, already built
     * @return array
     */
    private static function markListed(array $facets, array $rows)
    {
        $held = array();
        foreach ($rows as $row) {
            foreach ($row['tokens'] as $token) {
                if (!isset($held[$token])) {
                    $held[$token] = 0;
                }
                $held[$token]++;
            }
        }
        foreach ($facets as $key => $entries) {
            foreach ($entries as $index => $entry) {
                $token = $key . ':' . $entry['value'];
                $facets[$key][$index]['listed'] = isset($held[$token])
                    ? $held[$token]
                    : 0;
            }
        }
        return $facets;
    }

    /**
     * One group's distinct audiences, widest first.
     *
     * Widest first so the badge a reader saw when the cell held only
     * one is still the badge it leads with, and `rank` — the position
     * `ValueStatsTool` assigns, which is not the level's own number —
     * is dropped once it has done the ordering.
     *
     * @param array $set
     * @return array
     */
    private static function audiences(array $set)
    {
        $out = array_values($set);
        if (count($out) > 1) {
            usort($out, function ($a, $b) {
                if ($a['rank'] !== $b['rank']) {
                    return $b['rank'] - $a['rank'];
                }
                return strcmp(
                    (string)$a['sharing_group']['name'],
                    (string)$b['sharing_group']['name']
                );
            });
        }
        foreach ($out as &$audience) {
            unset($audience['rank']);
        }
        unset($audience);
        return $out;
    }

    /**
     * @param array $group
     * @return array Ranked, densest first
     */
    private static function rank(array $group)
    {
        $facets = array_values($group);
        usort($facets, function ($a, $b) {
            if ($a['count'] !== $b['count']) {
                return $b['count'] - $a['count'];
            }
            $left = isset($a['label']) ? $a['label'] : $a['value'];
            $right = isset($b['label']) ? $b['label'] : $b['value'];
            return strcmp((string)$left, (string)$right);
        });
        return $facets;
    }

    /**
     * @param array $counts
     * @param string $key
     * @return void
     */
    private static function tally(array &$counts, $key)
    {
        if ($key === null || $key === '') {
            return;
        }
        if (!isset($counts[$key])) {
            $counts[$key] = 0;
        }
        $counts[$key]++;
    }

    /**
     * @param array $counts
     * @return string|null The most frequent key, ties broken by name
     */
    private static function dominant(array $counts)
    {
        if (empty($counts)) {
            return null;
        }
        arsort($counts);
        $top = null;
        $best = -1;
        foreach ($counts as $key => $count) {
            if ($count > $best
                || ($count === $best && strcmp($key, $top) < 0)
            ) {
                $top = $key;
                $best = $count;
            }
        }
        return $top;
    }

    /**
     * Galaxy tags are dropped, as they are on the Occurrences tab: the
     * Tags column does not draw them, and a filter on something the
     * reader cannot see in the table is not a filter.
     *
     * @param array $row
     * @return array
     */
    private static function tagsOf(array $row)
    {
        if (empty($row['AttributeTag'])) {
            return array();
        }
        $tags = array();
        foreach ($row['AttributeTag'] as $attributeTag) {
            if (empty($attributeTag['Tag'])
                || !empty($attributeTag['Tag']['is_galaxy'])
            ) {
                continue;
            }
            $tags[] = $attributeTag['Tag'];
        }
        return $tags;
    }

    /**
     * @param array $orgs
     * @param int $id
     * @return string
     */
    private static function orgName(array $orgs, $id)
    {
        return isset($orgs[$id]) ? $orgs[$id] : __('Unknown organisation');
    }
}
