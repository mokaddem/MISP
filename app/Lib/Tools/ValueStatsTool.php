<?php
App::uses('ValueProfileBuckets', 'Tools');
App::uses('ValueDecayTool', 'Tools');

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
            'object' => array(),
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
            /*
             * The object template the occurrence sits in — `domain-ip`,
             * `network-socket` — which is a different question from its
             * type and one the type facet cannot answer: the same
             * `ip-dst` turns up standalone, inside a `domain-ip` and
             * inside a `network-socket`, and those are three different
             * things to have found.
             *
             * The template's name and not the object's id: a value's
             * occurrences are almost never in the *same* object, so
             * per-instance counts would all be one.
             *
             * Standalone rows get a value of their own rather than no
             * token, so the group partitions the rows and the reader can
             * ask for the complement — on `8.8.8.8` that is eleven of
             * twenty-three, which a group summing to twelve could not
             * have offered. `standalone` cannot collide with a slugged
             * template name unless somebody ships an object template
             * called "standalone".
             */
            if (empty($row['Object']['name'])) {
                self::bump(
                    $groups['object'],
                    'standalone',
                    __('Standalone attribute')
                );
            } else {
                self::bump(
                    $groups['object'],
                    self::facetToken($row['Object']['name']),
                    $row['Object']['name']
                );
            }
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
        $days = array('timestamp' => array(), 'published' => array());
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
                if (!isset($days[$key][$day])) {
                    $days[$key][$day] = 0;
                }
                $days[$key][$day]++;
                if ($spans[$key] === null) {
                    $spans[$key] = array('from' => $day, 'to' => $day);
                    continue;
                }
                $spans[$key]['from'] = min($spans[$key]['from'], $day);
                $spans[$key]['to'] = max($spans[$key]['to'], $day);
            }
        }
        $buckets = array();
        foreach ($spans as $key => $span) {
            $buckets[$key] = $span === null
                ? null
                : self::timeHistogram($span, $days[$key]);
        }
        return array(
            'time_spans' => $spans,
            'time_buckets' => $buckets,
            'published_unset' => $unpublished,
        );
    }

    /**
     * How many occurrences fall in each calendar bucket of a span, so
     * the rail can draw the shape of a date before the reader picks a
     * range out of it.
     *
     * **`ValueProfileBuckets` is reused here**, unlike the forty-slice
     * sparkline beside it (see `seenDensity()`). The difference is the
     * data: a `timestamp` is an instant, so this is a `Y-m-d` count map
     * tallied into calendar buckets, which is exactly the shape
     * `series()` and `locate()` were built for. The sparkline's input is
     * a set of *intervals*, which is what did not fit.
     *
     * The unit follows the span by a rule this caller owns, per the
     * tool's own contract. The default rule stops at weeks, which draws
     * a two-year span as a hundred-odd bars — legible in a full-width
     * chart and not in a `col-lg-3` rail, so months are added past a
     * year. Bars still thin out on a span of many years; the caption
     * states the grain so a thin bar is readable as a month rather than
     * as a gap.
     *
     * @param array $span `from` and `to`, `Y-m-d`
     * @param array $days `Y-m-d` => count
     * @return array `unit`, `max`, and `bars` of from/to/label/count
     */
    private static function timeHistogram(array $span, array $days)
    {
        $spanDays = 1 + (int)round(
            (strtotime($span['to']) - strtotime($span['from'])) / 86400
        );
        $unit = ValueProfileBuckets::unitForSpan($spanDays, array(
            array('days' => 45, 'unit' => ValueProfileBuckets::DAY),
            array('days' => 370, 'unit' => ValueProfileBuckets::WEEK),
            array('days' => null, 'unit' => ValueProfileBuckets::MONTH),
        ));
        $series = ValueProfileBuckets::series(
            $span['from'],
            $span['to'],
            $unit
        );
        $index = ValueProfileBuckets::locate($series);
        $counts = array_fill(0, count($series), 0);
        foreach ($days as $day => $count) {
            if (isset($index[$day])) {
                $counts[$index[$day]] += $count;
            }
        }
        $bars = array();
        foreach ($series as $position => $bucket) {
            $bars[] = array(
                'from' => $bucket['from'],
                'to' => $bucket['to'],
                'label' => $bucket['title'],
                'count' => $counts[$position],
            );
        }
        return array(
            'unit' => $unit,
            'max' => empty($counts) ? 0 : max($counts),
            'bars' => $bars,
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
     * The Overview card's sparkline: 90 days in 40 columns, counting
     * sightings and nothing else.
     *
     * Off the same rows the Sightings tab charts, which is the point of
     * it living here rather than being a fifth query of its own. The two
     * panels sit on the same page and a reader can see both at once, so
     * they must not be able to disagree about how busy the last 90 days
     * were — and while this card was on the fixture and the tab was not,
     * they could.
     *
     * Type 0 only, matching the card, whose three figures are already
     * split: a false positive is not a quiet week and drawing it as one
     * would put a contradiction into the same bar as the support.
     *
     * The columns are folded from a dense per-day tally rather than
     * bucketed straight off the rows, so the day arithmetic is
     * `ValueProfileBuckets`' and not a third copy of it.
     *
     * @param array $rows Rows as `Sighting::listSightings` returns
     * @param string $today `Y-m-d`
     * @return array 40 counts, oldest first
     */
    public static function sightingSpark(array $rows, $today)
    {
        $columns = 40;
        $days = 90;
        $from = date(
            'Y-m-d',
            strtotime($today) - ($days - 1) * 86400
        );
        $perDay = array();
        foreach ($rows as $row) {
            if ((int)$row['Sighting']['type'] !== 0) {
                continue;
            }
            $day = date(
                'Y-m-d',
                (int)$row['Sighting']['date_sighting']
            );
            $perDay[$day] = ($perDay[$day] ?? 0) + 1;
        }
        $tally = ValueProfileBuckets::tally($from, $today, $perDay);
        $spark = array_fill(0, $columns, 0);
        foreach ($tally as $offset => $count) {
            $column = (int)floor(($offset * $columns) / $days);
            $spark[min($column, $columns - 1)] += $count;
        }
        return $spark;
    }

    /**
     * The three numbers the tab's headers count, plus who filed them.
     *
     * Every count here is the viewer's by construction: the rows arrive
     * from `Sighting::listSightings`, which has already applied
     * `Plugin.Sightings_policy` and `Plugin.Sightings_anonymise`. This
     * class never sees a row the reader may not see, which is what
     * §14.5's no-`$user` rule buys.
     *
     * An anonymised sighting comes back with an empty organisation name
     * and `org_id` 0, and is filed under one *Others* key rather than
     * one key per hidden organisation — otherwise the org stack would
     * leak the number of foreign reporters it is hiding.
     *
     * @param array $rows Rows as `Sighting::listSightings` returns
     * @return array
     */
    public static function sightingTotals(array $rows)
    {
        $counts = array('total' => 0, 'sighting' => 0, 'fp' => 0,
            'expiration' => 0);
        $orgs = array();
        $last = null;
        $lastFp = null;
        foreach ($rows as $row) {
            $type = (int)$row['Sighting']['type'];
            $counts['total']++;
            if ($type === 1) {
                $counts['fp']++;
            } elseif ($type === 2) {
                $counts['expiration']++;
            } else {
                $counts['sighting']++;
            }
            $org = self::sightingOrg($row);
            $orgs[$org] = ($orgs[$org] ?? 0) + 1;
            $at = (int)$row['Sighting']['date_sighting'];
            if ($last === null || $at > $last) {
                $last = $at;
            }
            if ($type === 1 && ($lastFp === null || $at > $lastFp)) {
                $lastFp = $at;
            }
        }
        arsort($orgs);
        $reporters = array();
        foreach ($orgs as $name => $count) {
            $reporters[] = array('org' => $name, 'count' => $count);
        }
        return array(
            'total' => $counts['total'],
            'sighting' => $counts['sighting'],
            'fp' => $counts['fp'],
            'expiration' => $counts['expiration'],
            'reporters' => $reporters,
            'org_counts' => $orgs,
            'last_stamp' => $last,
            'last_fp_stamp' => $lastFp,
        );
    }

    /**
     * The individual-sightings table, oldest first.
     *
     * Oldest first because `value_sighting_list` reverses what it is
     * given: the brush indexes rows by date and the chart's series runs
     * forward, so one direction is chosen here and the panel's display
     * order is the panel's business.
     *
     * `against` is the occurrence the report was filed on, which is the
     * column a value-scoped list cannot do without — forty-seven
     * reports with no occurrence column silently merge ten occurrences
     * into one thing that was seen forty-seven times. It comes from the
     * id set rather than from a second lookup, so the table and the
     * chart cannot disagree about which occurrence a report belongs to.
     *
     * `org_id` and `against.attribute` are what the table links on, and
     * they are null exactly when there is nowhere to send the reader:
     * an anonymised report names no organisation, and an occurrence the
     * id set does not hold is one this reader may not open.
     *
     * @param array $rows Rows as `Sighting::listSightings` returns
     * @param array $occurrences As
     *              `Value::sightedOccurrenceIdsFor` returns
     * @return array
     */
    public static function sightingList(array $rows, array $occurrences)
    {
        $list = array();
        foreach ($rows as $row) {
            $id = (int)$row['Sighting']['attribute_id'];
            $source = $row['Sighting']['source'];
            $known = isset($occurrences[$id]);
            $list[] = array(
                'org' => self::sightingOrg($row),
                'org_id' => self::sightingHasOrg($row)
                    ? (int)$row['Sighting']['org_id']
                    : null,
                'source' => ($source === null || $source === '')
                    ? null
                    : $source,
                'date' => date(
                    'Y-m-d H:i',
                    (int)$row['Sighting']['date_sighting']
                ),
                'stamp' => (int)$row['Sighting']['date_sighting'],
                'type' => (int)$row['Sighting']['type'],
                'against' => array(
                    'attribute' => $known ? $id : null,
                    'event' => $known
                        ? $occurrences[$id]['event_id']
                        : (int)$row['Sighting']['event_id'],
                    'type' => $known
                        ? $occurrences[$id]['type']
                        : __('unknown type'),
                ),
            );
        }
        usort($list, function ($a, $b) {
            return $a['stamp'] - $b['stamp'];
        });
        return $list;
    }

    /**
     * The span the chart draws, and whether it was clipped.
     *
     * From the value's oldest evidence — the earliest occurrence date or
     * the earliest report, whichever is older, since a report can
     * predate the attribute row that now carries the value — to today.
     * Bounded by `ValueDecayTool::SPAN_CAP_DAYS`, and `clipped` says so
     * when it was, because a cap is not a permission (§14.6).
     *
     * The oldest occurrence date arrives as one number from
     * `Value::occurrenceSummaryFor` rather than being scanned out of a
     * row set. It has to be the oldest of *every* occurrence — the
     * chart's span is a claim about the value, not about the hundred
     * occurrences the decay envelope happened to score.
     *
     * @param array $summary From `Value::occurrenceSummaryFor`
     * @param array $rows Rows as `Sighting::listSightings` returns
     * @param string $today `Y-m-d`
     * @return array|null `from`, `to`, `first`, `clipped`
     */
    public static function sightingSpan(array $summary, array $rows,
        $today
    ) {
        $oldest = empty($summary['oldest'])
            ? null
            : date('Y-m-d', $summary['oldest']);
        foreach ($rows as $row) {
            $day = date('Y-m-d', (int)$row['Sighting']['date_sighting']);
            if ($oldest === null || $day < $oldest) {
                $oldest = $day;
            }
        }
        if ($oldest === null) {
            return null;
        }
        if ($oldest > $today) {
            // An occurrence whose timestamp is in the future of the
            // clock this page is drawn against. One day of span rather
            // than a negative one.
            $oldest = $today;
        }
        $floor = date(
            'Y-m-d',
            strtotime($today) - (ValueDecayTool::SPAN_CAP_DAYS - 1) * 86400
        );
        return array(
            'from' => max($oldest, $floor),
            'to' => $today,
            'first' => $oldest,
            'clipped' => $oldest < $floor,
        );
    }

    /**
     * The chart's payload: the reports as daily tallies per series, the
     * grain plan the browser zooms through, and the presets.
     *
     * The shape is phase 21's and unchanged — parallel sparse day
     * tallies plus a `plan` of grains, so a zoom step and a preset
     * switch are the same arithmetic in the browser rather than a
     * re-fetch. §13.1 of `22-occurrences.md` measured why: three
     * precomputed ranges cost 39.8 KB where the whole span as daily
     * counts costs 21.6 KB.
     *
     * Every series is positional, aligned with `orgs`, because Chart.js
     * wants one dataset per organisation and a stack order that does
     * not change between buckets.
     *
     * @param array $rows Rows as `Sighting::listSightings` returns
     * @param array $span From sightingSpan
     * @param array $totals From sightingTotals
     * @param array $curves One per model: `model`, `threshold`, `points`
     * @return array
     */
    public static function sightingSeries(array $rows, array $span,
        array $totals, array $curves
    ) {
        /*
         * One organisation list for all three kinds of report, ordered
         * as the Reporters card orders it: by every report the
         * organisation filed, of any type.
         *
         * It used to be the type-0 list, with false positives and
         * expirations pooled into two series of their own — so a
         * sighting was `CIRCL saw this` and a false positive was
         * nobody's. The rail beside the chart has always counted a
         * contradiction as participation ("hiding a false positive here
         * would make the most sceptical organisation look like the
         * quietest"), and the chart now says the same thing: three
         * series per organisation, and an organisation that has only
         * ever contradicted the value has a slot like any other.
         */
        $orgKeys = array_keys($totals['org_counts']);
        $at = array_flip($orgKeys);
        $slots = count($orgKeys);
        $perDay = array(
            'sighting' => array_fill(0, $slots, array()),
            'fp' => array_fill(0, $slots, array()),
            'expiration' => array_fill(0, $slots, array()),
        );
        foreach ($rows as $row) {
            $day = date('Y-m-d', (int)$row['Sighting']['date_sighting']);
            $type = (int)$row['Sighting']['type'];
            $kind = $type === 1
                ? 'fp'
                : ($type === 2 ? 'expiration' : 'sighting');
            $i = $at[self::sightingOrg($row)];
            $perDay[$kind][$i][$day] =
                ($perDay[$kind][$i][$day] ?? 0) + 1;
        }

        $plan = ValueProfileBuckets::plan(
            $span['from'],
            $span['to'],
            ValueProfileBuckets::$spanRule,
            ValueProfileBuckets::END
        );
        /*
         * The last bucket of every grain is today, whichever grain is
         * drawn. A bucket that is not the last one keeps its own date,
         * which makes this a relabelling of the end rather than of now.
         *
         * One word rather than a write into every grain's label array,
         * because the day grain no longer has one — its labels are
         * derived in the browser (`ValueProfileBuckets::plan`), and a
         * translated string is the one thing that cannot be. So the
         * substitution moves to the side that holds the labels.
         */
        $plan['last_label'] = __('today');

        /*
         * Sparse rather than dense: these are three series per
         * organisation over a span that can be three years, and each is
         * nearly all zero — two of the three usually entirely so.
         * `ValueProfileBuckets::sparse` documents the measurement
         * behind the choice, and it is what keeps three series per
         * organisation from costing three times two.
         */
        $daily = array(
            'sighting' => array(),
            'fp' => array(),
            'expiration' => array(),
        );
        foreach ($perDay as $kind => $series) {
            foreach ($series as $i => $byDay) {
                $daily[$kind][$i] = ValueProfileBuckets::sparse(
                    $span['from'],
                    $span['to'],
                    $byDay
                );
            }
        }

        return array(
            'today' => $span['to'],
            // Where the chart starts, and where the value does. They
            // differ exactly when the span cap bit, which is the only
            // thing that makes the clip notice sayable.
            'from' => $span['from'],
            'first' => $span['first'],
            'clipped' => $span['clipped'],
            'orgs' => $orgKeys,
            // Name => every report it filed, of any type. The same map
            // the Reporters card ranks, and now the same order the
            // stack is drawn in.
            'org_counts' => $totals['org_counts'],
            'totals' => array(
                'total' => $totals['total'],
                'sighting' => $totals['sighting'],
                'fp' => $totals['fp'],
                'expiration' => $totals['expiration'],
            ),
            'plan' => $plan,
            'daily' => $daily,
            'curves' => $curves,
            'spans' => self::sightingSpans($span, $daily, $rows),
            'default_span' => self::defaultSpan($span, $rows),
        );
    }

    /**
     * The presets, derived rather than listed.
     *
     * 90 always; 365 only for a span wider than it, because a control
     * that draws the same chart as the one beside it behind a different
     * label is worse than one that is absent; all time always. The
     * fixture reached the same rule and `02-sightings.md` §15 records
     * why.
     *
     * @param array $span From sightingSpan
     * @param array $daily From sightingSeries
     * @param array $rows Rows as `Sighting::listSightings` returns
     * @return array
     */
    private static function sightingSpans(array $span, array $daily,
        array $rows
    ) {
        $days = 1 + (int)round(
            (strtotime($span['to']) - strtotime($span['from'])) / 86400
        );
        $windows = array(90);
        if ($days > 365) {
            $windows[] = 365;
        }
        $windows[] = null;
        $spans = array();
        foreach ($windows as $window) {
            $from = $window === null
                ? $span['from']
                : date(
                    'Y-m-d',
                    strtotime($span['to']) - ($window - 1) * 86400
                );
            if ($from < $span['from']) {
                $from = $span['from'];
            }
            $spans[] = array(
                'key' => $window === null ? 'all' : (string)$window,
                'label' => $window === null
                    ? ($span['clipped']
                        ? sprintf(
                            __('All charted · from %s'),
                            $span['from']
                        )
                        : sprintf(__('All time · from %s'), $span['from']))
                    : sprintf(__('Last %s days'), $window),
                'days' => $window,
                'from' => $from,
                'to' => $span['to'],
            );
        }
        return $spans;
    }

    /**
     * The narrowest preset that holds every report.
     *
     * A sparse value opening on 90 days is a nearly empty chart, and the
     * reader would have to discover the control to learn that it is not
     * the whole truth.
     *
     * @param array $span From sightingSpan
     * @param array $rows Rows as `Sighting::listSightings` returns
     * @return string
     */
    private static function defaultSpan(array $span, array $rows)
    {
        $oldest = null;
        foreach ($rows as $row) {
            $day = date('Y-m-d', (int)$row['Sighting']['date_sighting']);
            if ($oldest === null || $day < $oldest) {
                $oldest = $day;
            }
        }
        if ($oldest === null) {
            return 'all';
        }
        $days = 1 + (int)round(
            (strtotime($span['to']) - strtotime($span['from'])) / 86400
        );
        foreach (array(90, 365) as $window) {
            if ($window === 365 && $days <= 365) {
                continue;
            }
            $from = date(
                'Y-m-d',
                strtotime($span['to']) - ($window - 1) * 86400
            );
            if ($oldest >= $from) {
                return (string)$window;
            }
        }
        return 'all';
    }

    /**
     * The two sentences the tab must not omit, derived per value.
     *
     * The first is the whole argument for the overlay: a contradiction
     * is drawn on the axis and moves no line. The fixture wrote it by
     * hand per value; here it names the value's own last false positive,
     * so a reader can find the bar it is talking about.
     *
     * @param array $totals From sightingTotals
     * @return array
     */
    public static function sightingNotes(array $totals)
    {
        if ($totals['fp'] > 0) {
            $fp = sprintf(
                __n(
                    'The false positive on %1$s leaves every curve flat.'
                        . ' MISP resets the decay clock on sightings'
                        . ' alone, so a contradiction is visible on the'
                        . ' axis but moves no score.',
                    'The %2$s false positives, the last of them on %1$s,'
                        . ' leave every curve flat. MISP resets the decay'
                        . ' clock on sightings alone, so a contradiction'
                        . ' is visible on the axis but moves no score.',
                    $totals['fp']
                ),
                date('Y-m-d', $totals['last_fp_stamp']),
                $totals['fp']
            );
        } else {
            $fp = __(
                'Nobody has contradicted this value. A false positive'
                . ' would be drawn on this axis and would move no'
                . ' curve — MISP resets the decay clock on sightings'
                . ' alone.'
            );
        }
        return array(
            'fp_moves_nothing' => $fp,
            'policy' => __(
                'Sightings you can see. This instance\'s sighting policy'
                . ' hides sightings reported by other organisations on'
                . ' events your organisation does not own, so this count'
                . ' is yours, not the instance\'s.'
            ),
        );
    }

    /**
     * How long ago, in the words the panel sub-line uses.
     *
     * @param int|null $stamp
     * @param int $now
     * @return string
     */
    public static function agoPhrase($stamp, $now)
    {
        if ($stamp === null) {
            return __('never');
        }
        $days = (int)floor(
            (strtotime(date('Y-m-d', $now)) - strtotime(date('Y-m-d', $stamp)))
            / 86400
        );
        if ($days <= 0) {
            return __('today');
        }
        if ($days === 1) {
            return __('yesterday');
        }
        if ($days < 31) {
            return sprintf(__('%s days ago'), $days);
        }
        return date('Y-m-d', $stamp);
    }

    /**
     * The organisation a report is filed under.
     *
     * `Sighting::listSightings` blanks the name and zeroes `org_id` for
     * a foreign report when `Plugin.Sightings_anonymise` is on. All of
     * them collapse to one key, because one key per hidden organisation
     * would put the number of them in the legend.
     *
     * @param array $row One `Sighting::listSightings` row
     * @return string
     */
    private static function sightingOrg(array $row)
    {
        return self::sightingHasOrg($row)
            ? $row['Organisation']['name']
            : __('Others');
    }

    /**
     * Whether the row names an organisation at all.
     *
     * One predicate rather than the same two-part guard written twice,
     * because the label and the link have to agree: a report pooled
     * under `Others` must not carry a link to whichever organisation
     * `Sighting.org_id` still happens to hold.
     *
     * @param array $row One `Sighting::listSightings` row
     * @return bool
     */
    private static function sightingHasOrg(array $row)
    {
        return ($row['Organisation']['name'] ?? '') !== ''
            && !empty($row['Sighting']['org_id']);
    }

    /**
     * The date MISP decays an occurrence from when nobody has reported
     * it: `last_seen` if it has one, else the attribute's own timestamp.
     * `DecayingModelBase::computeCurrentScore` picks the same pair in
     * the same order.
     *
     * @param array $occurrence One `Value::occurrenceIdsFor` entry
     * @return int Unix timestamp
     */
    public static function anchorStamp(array $occurrence)
    {
        $seen = self::stamp($occurrence['last_seen'] ?? null);
        return $seen === null ? (int)$occurrence['timestamp'] : $seen;
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
