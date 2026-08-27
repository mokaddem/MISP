<?php

/**
 * The cross-cutting aggregates the Value Profile page's panels share:
 * facet counts, organisation and type rollups, and the seen-density
 * histogram behind the occurrence rail's sparkline.
 *
 * Pure and static, which is the shape
 * prd/value-profile-live/00-contract.md §14.5 prefers and the shape
 * `ValueProfileBuckets` already takes. **No method here accepts a
 * `$user`**, and none of them queries anything: the owning model
 * pre-scopes and hands over a set that is already filtered, so this
 * class cannot leak data the viewer may not see. That is checkable by
 * reading the signatures rather than by tracing the callers.
 */
class ValueStatsTool
{
    /**
     * Buckets in the occurrence rail's sparkline. Forty is what the
     * design draws and what the shipped `.vp-spark` renders.
     */
    const SPARK_BUCKETS = 40;

    /** MISP's "inherit from the parent" distribution level. */
    const INHERIT = 5;

    /**
     * Restrictiveness, tightest first — the order this class resolves a
     * distribution chain by, and the only place it is decided.
     *
     * `0` is one organisation and is strictly tightest. `4` is a named
     * list of organisations, so it sits next: bounded and explicit,
     * where `1`–`3` widen by community. **`4` is not truly comparable
     * with `1`–`3`** — a sharing group can carry an `all_orgs` server
     * entry and so be wider than "this community only" — which is why a
     * chain mixing the two is reported as an intersection rather than
     * silently flattened. See `effectiveDistribution()`.
     */
    private static $restrictiveness = array(0 => 0, 4 => 1, 1 => 2,
        2 => 3, 3 => 4);

    /**
     * Who can actually see one occurrence, as a single level.
     *
     * An attribute carries a distribution, so does the object holding it
     * and so does the event holding that, and MISP's own visibility rule
     * is the conjunction of all three — `MispAttribute::buildConditions`
     * requires the event to allow the viewer *and* the attribute *and*
     * the object, with level 5 passing through. Reporting the attribute's
     * own column instead says `Inherited` for almost every row on a real
     * instance and tells the reader nothing.
     *
     * Two steps. **Inheritance is resolved**: a link at level 5 states
     * nothing of its own and defers outward, and an event can never be 5,
     * so at least one link always states a level. **Then the tightest
     * stated level wins**, by `$restrictiveness`.
     *
     * `intersects` is the honest part. When a sharing group is one of
     * several stated constraints, the real audience is an intersection —
     * that group's members *and* whatever the other constraint allows —
     * and no single level says that. It is true only where the ambiguity
     * is real: a level 0 anywhere dominates every other constraint, and
     * the same sharing group stated twice is one constraint, so neither
     * of those sets it.
     *
     * A row with no object must not be read as an object at level 0. The
     * `Object` key is always present after a `contain`, holding nulls
     * where the LEFT JOIN found nothing, so the object link counts only
     * when it has an id.
     *
     * @param array $row fetchAttributes-shaped, with Event contained
     * @param array $sharingGroupNames id => name, for the groups this
     *                                 viewer may see
     * @return array level, sharing_group_id, sharing_group_name, source,
     *               stated, intersects, inherited
     */
    public static function effectiveDistribution(array $row,
        array $sharingGroupNames = array()
    ) {
        $links = array(
            array('scope' => 'attribute', 'label' => __('Attribute'),
                'from' => $row['Attribute']),
        );
        if (!empty($row['Object']['id'])) {
            $links[] = array('scope' => 'object', 'label' => __('Object'),
                'from' => $row['Object']);
        }
        $links[] = array('scope' => 'event', 'label' => __('Event'),
            'from' => $row['Event']);

        $stated = array();
        foreach ($links as $link) {
            $level = (int)$link['from']['distribution'];
            if ($level === self::INHERIT) {
                continue;
            }
            $stated[] = array(
                'scope' => $link['scope'],
                'label' => $link['label'],
                'level' => $level,
                'sharing_group_id' => $level === 4
                    ? (int)$link['from']['sharing_group_id']
                    : null,
            );
        }

        if (empty($stated)) {
            // Only reachable if an event were itself at level 5, which
            // MISP does not allow. Reported rather than guessed at.
            return array(
                'level' => null,
                'rank' => PHP_INT_MAX,
                'sharing_group_id' => null,
                'sharing_group_name' => null,
                'source' => null,
                'stated' => array(),
                'intersects' => false,
                'inherited' => true,
            );
        }

        $winner = $stated[0];
        foreach ($stated as $candidate) {
            if (self::restrictionRank($candidate['level'])
                < self::restrictionRank($winner['level'])
            ) {
                $winner = $candidate;
            }
        }

        return array(
            'level' => $winner['level'],
            // Position in `$restrictiveness`, so a caller ordering rows
            // by audience does not have to know the order — the one
            // place it is decided stays the one place.
            'rank' => self::restrictionRank($winner['level']),
            'sharing_group_id' => $winner['sharing_group_id'],
            'sharing_group_name' => $winner['sharing_group_id'] !== null
                    && isset($sharingGroupNames[$winner['sharing_group_id']])
                ? $sharingGroupNames[$winner['sharing_group_id']]
                : null,
            'source' => $winner['scope'],
            'stated' => $stated,
            'intersects' => self::intersects($stated, $winner),
            // The attribute deferred, so this level is somebody else's
            // decision — worth saying, because it is not editable here.
            'inherited' => $winner['scope'] !== 'attribute',
        );
    }

    /**
     * @param int $level
     * @return int
     */
    private static function restrictionRank($level)
    {
        return isset(self::$restrictiveness[$level])
            ? self::$restrictiveness[$level]
            : PHP_INT_MAX;
    }

    /**
     * Whether the stated constraints narrow each other in a way one
     * level cannot express. See `effectiveDistribution()`.
     *
     * @param array $stated
     * @param array $winner
     * @return bool
     */
    private static function intersects(array $stated, array $winner)
    {
        if ($winner['level'] === 0) {
            return false;
        }
        $groups = array();
        $others = 0;
        foreach ($stated as $constraint) {
            if ($constraint['level'] === 4) {
                $groups[$constraint['sharing_group_id']] = true;
            } elseif ($constraint['level'] !== 0) {
                $others++;
            }
        }
        if (empty($groups)) {
            return false;
        }
        return count($groups) > 1 || $others > 0;
    }

    /**
     * The token a facet checkbox carries and a row is matched on.
     *
     * One rule with two callers, which is why it is here rather than in
     * either of them. The rail counts by it and
     * `value_occurrence_table.ctp` stamps it on each row; while the
     * counts were fixture data the slug was written down by hand in one
     * place and derived by a regex in the other, and nothing would have
     * noticed the two drifting apart. A MISP type can hold characters
     * an attribute value should not — `domain|ip` — so everything is
     * slugged rather than only the values that look like they need it.
     *
     * @param string $text
     * @return string
     */
    public static function facetToken($text)
    {
        return trim(
            preg_replace('/[^a-z0-9]+/', '-', strtolower((string)$text)),
            '-'
        );
    }

    /**
     * The numbers in the occurrence table's panel header.
     *
     * `$total` is the viewer's own count of the value's occurrences and
     * arrives from the model; everything else is counted from the rows
     * the panel was actually given, so a capped fetch cannot claim to
     * describe events and organisations it never loaded.
     *
     * @param array $rows fetchAttributes-shaped occurrence rows
     * @param int $total The viewer's occurrence count for the value
     * @return array
     */
    public static function occurrenceStats(array $rows, $total)
    {
        $events = array();
        $orgs = array();
        $deleted = 0;
        foreach ($rows as $row) {
            $events[$row['Event']['id']] = true;
            if (isset($row['Event']['Orgc']['id'])) {
                $orgs[$row['Event']['Orgc']['id']] = true;
            }
            if (!empty($row['Attribute']['deleted'])) {
                $deleted++;
            }
        }
        return array(
            'total' => (int)$total,
            'shown' => count($rows),
            'events' => count($events),
            'orgs' => count($orgs),
            'deleted' => $deleted,
        );
    }

    /**
     * The counted rail beside the occurrence table.
     *
     * Order, heading and glyph are not here: `value_occurrence_facets`
     * owns those because they are the same for every value, and phase 9
     * §13 moved them out of the fixture for that reason. What this
     * returns is only what varies — counts, and the domain values behind
     * them.
     *
     * All eight groups are always present, empty where the rows offer
     * nothing. The rail iterates a fixed list of keys and dereferences
     * two of them directly, so a missing key is a warning rather than an
     * absent group; `value_facet_group` is what decides that a group of
     * zeroes renders nothing at all.
     *
     * @param array $rows fetchAttributes-shaped occurrence rows,
     *                    already carrying Event.Orgc, SharingGroup,
     *                    AttributeTag.Tag and proposal_count
     * @param int $total The viewer's occurrence count for the value
     * @return array
     */
    public static function occurrenceFacets(array $rows, $total)
    {
        $groups = array(
            'organisation' => array(),
            'type' => array(),
            'category' => array(),
            'ids' => array(),
            'distribution' => array(),
            'sharing_group' => array(),
            'tag' => array(),
            'state' => array(),
        );
        $deleted = 0;
        $proposals = 0;

        foreach ($rows as $row) {
            $attribute = $row['Attribute'];

            if (isset($row['Event']['Orgc']['id'])) {
                self::bump(
                    $groups['organisation'],
                    (string)$row['Event']['Orgc']['id'],
                    $row['Event']['Orgc']['name']
                );
            }
            self::bump(
                $groups['type'],
                self::facetToken($attribute['type']),
                $attribute['type']
            );
            self::bump(
                $groups['category'],
                self::facetToken($attribute['category']),
                $attribute['category']
            );
            self::bump(
                $groups['ids'],
                empty($attribute['to_ids']) ? 'unset' : 'set',
                empty($attribute['to_ids'])
                    ? __('to_ids unset')
                    : __('to_ids set')
            );
            /*
             * The **effective** level, not `Attribute.distribution`.
             * Almost every attribute on a real instance is at level 5,
             * so a rail counting the column says `Inherited: 100` and
             * answers nobody's question; the resolved chain says who can
             * actually see each row. `effectiveDistribution()` has the
             * rule, and the model stamps it on the row so the rail and
             * the table cannot resolve it differently.
             */
            $effective = isset($row['effective_distribution'])
                ? $row['effective_distribution']
                : null;
            $level = $effective === null ? null : $effective['level'];
            if ($level !== null) {
                self::bump(
                    $groups['distribution'],
                    (string)$level,
                    null,
                    array('level' => $level)
                );
                // Named by whichever link in the chain won, so a row
                // distributed through its event's sharing group is
                // counted under that group rather than not at all.
                if ($level === 4
                    && !empty($effective['sharing_group_id'])
                ) {
                    self::bump(
                        $groups['sharing_group'],
                        (string)$effective['sharing_group_id'],
                        $effective['sharing_group_name'] !== null
                            ? $effective['sharing_group_name']
                            : sprintf(
                                __('Sharing group #%s'),
                                $effective['sharing_group_id']
                            )
                    );
                }
            }
            foreach ($row['AttributeTag'] as $attributeTag) {
                if (empty($attributeTag['Tag'])) {
                    continue;
                }
                $tag = $attributeTag['Tag'];
                // The Tags column does not draw galaxy tags either, and
                // a filter on something invisible is not a filter.
                if (!empty($tag['is_galaxy'])) {
                    continue;
                }
                self::bump(
                    $groups['tag'],
                    self::facetToken($tag['name']),
                    $tag['name'],
                    array(
                        'tag' => $tag,
                        'local' => !empty($tag['local']) ? 1 : 0,
                    )
                );
            }
            if (!empty($row['proposal_count'])) {
                $proposals++;
            }
            if (!empty($attribute['deleted'])) {
                $deleted++;
            }
        }

        /*
         * A tag attached locally on one occurrence and globally on
         * another is one facet, because the row token is the tag's name
         * and carries no local/global distinction. `local` therefore
         * survives only when every attachment was local, so the chip
         * never marks a globally-attached tag as local.
         */
        foreach ($groups['tag'] as $token => $facet) {
            $groups['tag'][$token]['local'] = empty($facet['local_all'])
                ? 0
                : 1;
            unset($groups['tag'][$token]['local_all']);
        }

        if ($proposals > 0) {
            self::bump(
                $groups['state'],
                'proposal',
                __('With a pending proposal'),
                array(),
                $proposals
            );
        }

        foreach ($groups as $key => $facets) {
            $groups[$key] = self::rank($facets);
        }

        return array_merge(
            array(
                // What the rail's own counts cover, which is the rows it
                // was given rather than the value's total.
                'visible' => count($rows),
                'total' => (int)$total,
                'groups' => $groups,
                'deleted' => $deleted,
            ),
            self::seenDensity($rows),
            self::timeSpans($rows)
        );
    }

    /**
     * Add one to a facet, creating it on first sight.
     *
     * @param array $group
     * @param string $token
     * @param string|null $label
     * @param array $extra Keys the rail renders a component from
     * @param int $by
     * @return void
     */
    private static function bump(array &$group, $token, $label,
        array $extra = array(), $by = 1
    ) {
        if (!isset($group[$token])) {
            $group[$token] = array_merge(
                array('value' => $token, 'count' => 0),
                $label === null ? array() : array('label' => $label),
                $extra
            );
            if (isset($extra['local'])) {
                $group[$token]['local_all'] = true;
            }
        }
        $group[$token]['count'] += $by;
        if (array_key_exists('local_all', $group[$token])
            && empty($extra['local'])
        ) {
            $group[$token]['local_all'] = false;
        }
    }

    /**
     * Count descending, then label, so redrawing the same rows cannot
     * reorder the rail.
     *
     * @param array $group
     * @return array
     */
    private static function rank(array $group)
    {
        $facets = array_values($group);
        usort($facets, function ($a, $b) {
            if ($a['count'] !== $b['count']) {
                return $b['count'] - $a['count'];
            }
            return strcmp(
                isset($a['label']) ? $a['label'] : $a['value'],
                isset($b['label']) ? $b['label'] : $b['value']
            );
        });
        return $facets;
    }

    /**
     * The two dates the rail can cut on, and what they span.
     *
     * `timestamp` is when the attribute was last modified and every
     * attribute has one. `published` is when its event was last
     * published, and an unpublished event has none — so those rows are
     * counted rather than quietly dropped, because a date cut over a
     * column that is sometimes absent has to say how much it removes
     * (the same rule `seen_unset` follows).
     *
     * The spans bound the date inputs. The inputs themselves start empty:
     * a control pre-filled with the full span looks like a filter that is
     * already applied, and "no bound" and "the widest bound" should not
     * render identically.
     *
     * @param array $rows
     * @return array `time_spans`, `published_unset`
     */
    private static function timeSpans(array $rows)
    {
        $spans = array('timestamp' => null, 'published' => null);
        $unpublished = 0;
        foreach ($rows as $row) {
            $stamps = array(
                'timestamp' => $row['Attribute']['timestamp'] ?? null,
                'published' => empty($row['Event']['publish_timestamp'])
                    ? null
                    : $row['Event']['publish_timestamp'],
            );
            if ($stamps['published'] === null) {
                $unpublished++;
            }
            foreach ($stamps as $key => $stamp) {
                if (empty($stamp)) {
                    continue;
                }
                $day = date('Y-m-d', (int)$stamp);
                if ($spans[$key] === null) {
                    $spans[$key] = array('from' => $day, 'to' => $day);
                    continue;
                }
                $spans[$key]['from'] = min($spans[$key]['from'], $day);
                $spans[$key]['to'] = max($spans[$key]['to'], $day);
            }
        }
        return array(
            'time_spans' => $spans,
            'published_unset' => $unpublished,
        );
    }

    /**
     * The sparkline under the rail's `First / last seen` heading.
     *
     * Forty buckets across the span the value was seen over, each
     * counting the occurrences whose seen interval covers it — an
     * interval overlap rather than a point count, because an occurrence
     * seen for three months is present in all of them.
     *
     * `ValueProfileBuckets` is the page's bucket primitive and is not
     * used here. It divides a span into calendar units keyed `Y-m-d` and
     * tallies a day-keyed count map; forty equal slices of an arbitrary
     * span are not a calendar unit, and converting intervals to days
     * first is both the expensive part and the part it does not do.
     *
     * `first_seen` and `last_seen` are optional, and either may be set
     * without the other — a row with only one of them is a point, and
     * covers the single bucket it falls in. A row with neither is
     * counted by `seen_unset` and placed nowhere, because a date filter
     * over a frequently-empty column has to say how much it would
     * silently drop.
     *
     * @param array $rows
     * @return array `seen_spark`, `seen_from`, `seen_to`, `seen_unset`
     */
    private static function seenDensity(array $rows)
    {
        $spans = array();
        $unset = 0;
        foreach ($rows as $row) {
            $first = self::stamp($row['Attribute']['first_seen'] ?? null);
            $last = self::stamp($row['Attribute']['last_seen'] ?? null);
            if ($first === null && $last === null) {
                $unset++;
                continue;
            }
            $from = $first === null ? $last : $first;
            $to = $last === null ? $first : $last;
            $spans[] = $from <= $to
                ? array($from, $to)
                : array($to, $from);
        }

        if (empty($spans)) {
            /*
             * No sparkline and no pre-filled dates rather than forty
             * zeroes and two empty inputs: a chart of nothing is a
             * claim that there was nothing to see, and what is true is
             * that nobody recorded when.
             */
            return array(
                'seen_spark' => array(),
                'seen_from' => null,
                'seen_to' => null,
                'seen_unset' => $unset,
            );
        }

        $min = null;
        $max = null;
        foreach ($spans as $span) {
            $min = $min === null ? $span[0] : min($min, $span[0]);
            $max = $max === null ? $span[1] : max($max, $span[1]);
        }

        $buckets = array_fill(0, self::SPARK_BUCKETS, 0);
        $width = ($max - $min) / self::SPARK_BUCKETS;
        foreach ($spans as $span) {
            if ($width <= 0) {
                // Every occurrence seen at the same instant. One bucket
                // is the honest picture; forty identical bars would
                // draw a duration nothing has.
                $buckets[0]++;
                continue;
            }
            $fromBucket = (int)floor(($span[0] - $min) / $width);
            $toBucket = (int)floor(($span[1] - $min) / $width);
            $fromBucket = max(0, min(self::SPARK_BUCKETS - 1, $fromBucket));
            $toBucket = max(0, min(self::SPARK_BUCKETS - 1, $toBucket));
            for ($i = $fromBucket; $i <= $toBucket; $i++) {
                $buckets[$i]++;
            }
        }

        return array(
            'seen_spark' => $buckets,
            'seen_from' => date('Y-m-d', $min),
            'seen_to' => date('Y-m-d', $max),
            'seen_unset' => $unset,
        );
    }

    /**
     * `MispAttribute::afterFind` hands these back as ISO strings, and a
     * value that predates the seen columns hands back nothing.
     *
     * @param string|null $value
     * @return int|null Unix timestamp
     */
    private static function stamp($value)
    {
        if (empty($value)) {
            return null;
        }
        $stamp = strtotime($value);
        return $stamp === false ? null : $stamp;
    }
}
