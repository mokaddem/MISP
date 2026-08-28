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
 * with two types gets one badge, which distribution a group of
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
             * The widest audience any of the group's occurrences has,
             * not the tightest. The column answers *how widely may this
             * pairing be discussed*, and one restricted sighting of a
             * value that is otherwise public does not make the pairing
             * restricted.
             */
            $effective = ValueStatsTool::effectiveDistribution(
                $row,
                $sgNames
            );
            if ($effective['level'] !== null
                && ($group['distribution'] === null
                    || $effective['rank'] > $group['distribution']['rank'])
            ) {
                $group['distribution'] = $effective;
            }

            foreach (self::tagsOf($row) as $tag) {
                $group['tags'][$tag['name']] = $tag;
            }
            unset($group);
        }

        $facets = self::facets($groups, $eventMeta, $orgs);
        $distinct = count($groups);

        $ranked = array_values($groups);
        usort($ranked, function ($a, $b) {
            $events = count($b['events']) - count($a['events']);
            if ($events !== 0) {
                return $events;
            }
            if ($a['last'] !== $b['last']) {
                return $b['last'] - $a['last'];
            }
            return strcmp($a['value'], $b['value']);
        });
        $listed = array_slice($ranked, 0, $rowCap);

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
            'events' => count($eventRows),
            'page_size' => $pageSize,
            'rollups' => array(
                'value' => array(
                    'total' => $distinct,
                    'rows' => self::valueRows($listed, $orgs),
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
            'distribution' => null,
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
    private static function valueRows(array $groups, array $orgs)
    {
        $rows = array();
        foreach ($groups as $group) {
            $distribution = $group['distribution'];
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
                'distribution' => $distribution === null
                    ? 5
                    : $distribution['level'],
                'sharing_group' => array(
                    'id' => $distribution === null
                        ? null
                        : $distribution['sharing_group_id'],
                    'name' => $distribution === null
                        ? null
                        : $distribution['sharing_group_name'],
                ),
                'object' => empty($group['objects'])
                    ? null
                    : self::dominant($group['objects']),
                'tags' => array_values($group['tags']),
                'events' => array_keys($group['events']),
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
                'distribution' => isset($meta['distribution'])
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
            $level = $group['distribution'] === null
                ? null
                : $group['distribution']['level'];
            if ($level !== null) {
                self::bump(
                    $facets['distribution'],
                    (string)$level,
                    null,
                    array('level' => $level)
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
        $triples = array();
        $objects = array();
        foreach ($rows as $row) {
            $attribute = $row['Attribute'];
            if (empty($row['Object']['id'])) {
                continue;
            }
            $template = $row['Object']['name'];
            $relation = empty($attribute['object_relation'])
                ? ''
                : $attribute['object_relation'];
            $value = isset($attribute['value']) ? $attribute['value'] : '';
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
                );
            }
            $triples[$key]['objects'][(int)$row['Object']['id']] = true;
            $triples[$key]['events'][(int)$attribute['event_id']] = true;
            $triples[$key]['orgs'][(int)$row['Event']['orgc_id']] = true;
            $objects[(int)$row['Object']['id']] = true;
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
