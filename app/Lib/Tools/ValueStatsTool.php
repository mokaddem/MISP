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
            $level = (int)$attribute['distribution'];
            self::bump(
                $groups['distribution'],
                (string)$level,
                null,
                array('level' => $level)
            );
            // A sharing group is only what the row is distributed by
            // when the row says so; `sharing_group_id` outlives a
            // distribution change and the badge would be a claim the
            // row does not make.
            if ($level === 4 && !empty($row['SharingGroup']['id'])) {
                self::bump(
                    $groups['sharing_group'],
                    (string)$row['SharingGroup']['id'],
                    $row['SharingGroup']['name']
                );
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
            self::seenDensity($rows)
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
