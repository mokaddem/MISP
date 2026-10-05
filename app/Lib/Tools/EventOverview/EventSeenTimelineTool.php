<?php
App::uses('EventOverviewTool', 'Tools/EventOverview');
App::uses('ValueProfileBuckets', 'Tools/ValueProfile');

/**
 * The data behind the Overmind event Timeline tab: loose attributes and
 * objects placed by first_seen / last_seen, an object spanning its own dates
 * and its attributes'.
 *
 * A row is dated when it has either end. An undated row is counted, never
 * placed. Seen dates are microseconds; days are UTC days since the epoch.
 *
 * Every query is scoped to the events asked for and the ACL is part of the
 * SQL, because the row queries have to be cut with a LIMIT.
 */
class EventSeenTimelineTool
{
    /** Rows returned for one window, past which the response says it is capped. */
    const ITEM_CAP = 2000;

    /** Dated attributes of the returned objects, past which they are fetched per object. */
    const CHILD_INLINE_CAP = 2000;

    /** Dated attributes returned for a single object. */
    const CHILD_CAP = 500;

    const LABEL_MAX = 200;

    const DAY_US = 86400000000;

    /** How far past today the histogram reaches before calling dates outside. */
    const SPAN_AHEAD_DAYS = 3653;

    /** The widest span the histogram draws, back from its end. */
    const SPAN_MAX_DAYS = 18263;

    /** @var EventOverviewTool */
    private $overview;

    /** @var Model */
    private $Attribute;

    public function __construct(EventOverviewTool $overview = null)
    {
        $this->overview = $overview ?? new EventOverviewTool();
        $this->Attribute = ClassRegistry::init('MispAttribute');
    }

    /**
     * @param array $user
     * @param array $events Event rows (id, org_id, timestamp), the viewed one first
     * @param array $options from, to (`Y-m-d`, inclusive), q, kind, types,
     *        objects, categories, events
     * @param string $today `Y-m-d`
     * @return array span, histogram, window, items, capped, in_window, counts, facets
     */
    public function timeline(array $user, array $events, array $options, $today)
    {
        $scopes = $this->scopes($user, $events);
        $tallies = [];
        foreach ($events as $event) {
            $tallies[(int)$event['id']] = $this->tally($scopes[(int)$event['id']], $event);
        }
        $merged = self::mergeTallies($tallies);
        $histogram = self::histogram($merged['starts'], $merged['ends'], $today);

        $window = self::window($options, $histogram);
        $items = [];
        $capped = false;
        $inWindow = 0;
        if ($window !== null) {
            list($items, $capped, $inWindow) = $this->items($scopes, $window, $options);
        }

        return [
            'span' => $histogram === null ? null : [
                'from' => $histogram['from'],
                'to' => $histogram['to'],
            ],
            'histogram' => $histogram,
            'window' => $window === null ? null : [
                'from' => gmdate('Y-m-d', $window[0] * 86400),
                'to' => gmdate('Y-m-d', $window[1] * 86400),
            ],
            'items' => $items,
            'capped' => $capped,
            'in_window' => $inWindow,
            'counts' => [
                'attributes' => $merged['attributes'],
                'objects' => $merged['objects'],
                'dated' => $merged['attributes'] + $merged['objects'],
                'undated' => $merged['undated'],
                'outside' => $histogram['outside'] ?? 0,
            ],
            'facets' => array_map(function ($counts) {
                return (object)$counts;
            }, $merged['facets']),
        ];
    }

    /**
     * The dated attributes of one object, for an object whose children were
     * not inlined.
     *
     * @param array $user
     * @param array $events Event rows the object may belong to
     * @param int $objectId
     * @return array|null children, capped; null when the object is not visible
     */
    public function objectChildren(array $user, array $events, $objectId)
    {
        $scopes = $this->scopes($user, $events);
        $objects = $this->Attribute->query(
            'SELECT o.id FROM objects o WHERE o.id = ? AND o.deleted = 0 AND '
            . $this->eventSql($scopes, 'o'),
            [(int)$objectId],
            false
        );
        if (empty($objects)) {
            return null;
        }
        $children = $this->children($scopes, [(int)$objectId], self::CHILD_CAP + 1);
        $rows = $children[(int)$objectId] ?? [];
        return [
            'children' => array_slice($rows, 0, self::CHILD_CAP),
            'capped' => count($rows) > self::CHILD_CAP,
        ];
    }

    private function scopes(array $user, array $events)
    {
        $scopes = [];
        foreach ($events as $event) {
            $scopes[(int)$event['id']] = $this->overview->scope($user, $event);
        }
        return $scopes;
    }

    /**
     * Start and end days, undated count and facet counts for one event,
     * cached on the event's timestamp and the reader's scope.
     */
    private function tally(array $scope, array $event)
    {
        return $this->overview->cached('timeline-v1', $event, $scope, function () use ($scope, $event) {
            return $this->computeTally([(int)$event['id'] => $scope]);
        });
    }

    private function computeTally(array $scopes)
    {
        $tally = [
            'starts' => [], 'ends' => [], 'attributes' => 0, 'objects' => 0, 'undated' => 0,
            'facets' => ['types' => [], 'objects' => [], 'categories' => []],
        ];
        $loose = $this->looseFrom($scopes);
        $objects = '(' . $this->envelopeSql($scopes) . ') env';
        foreach (['starts' => 's', 'ends' => 'e'] as $key => $column) {
            $source = [
                'attributes' => 'SELECT FLOOR(' . self::seenSql('a', $column) . ' / ' . self::DAY_US . ') AS d,'
                    . ' COUNT(*) AS n ' . $loose . ' GROUP BY d',
                'objects' => 'SELECT FLOOR(env.' . $column . ' / ' . self::DAY_US . ') AS d, COUNT(*) AS n'
                    . ' FROM ' . $objects . ' GROUP BY d',
            ];
            foreach ($source as $kind => $sql) {
                foreach ($this->rows($sql) as $r) {
                    if ($r['d'] === null) {
                        if ($key === 'starts') {
                            $tally['undated'] += (int)$r['n'];
                        }
                        continue;
                    }
                    $day = (int)$r['d'];
                    $tally[$key][$day] = ($tally[$key][$day] ?? 0) + (int)$r['n'];
                    if ($key === 'starts') {
                        $tally[$kind] += (int)$r['n'];
                    }
                }
            }
        }
        $facets = [
            ['types', 'type', 'SELECT a.type AS f, a.category AS c, COUNT(*) AS n ' . $loose
                . ' AND ' . self::datedSql('a') . ' GROUP BY a.type, a.category'],
            ['objects', 'name', 'SELECT env.name AS f, env.category AS c, COUNT(*) AS n FROM ' . $objects
                . ' WHERE env.s IS NOT NULL GROUP BY env.name, env.category'],
        ];
        foreach ($facets as list($facet, , $sql)) {
            foreach ($this->rows($sql) as $r) {
                $n = (int)$r['n'];
                $tally['facets'][$facet][$r['f']] = ($tally['facets'][$facet][$r['f']] ?? 0) + $n;
                $category = (string)$r['c'];
                $tally['facets']['categories'][$category] = ($tally['facets']['categories'][$category] ?? 0) + $n;
            }
        }
        return $tally;
    }

    /**
     * Several events' tallies as one, with the dated rows per event as a facet.
     *
     * @param array $tallies event id => tally
     * @return array
     */
    public static function mergeTallies(array $tallies)
    {
        $merged = [
            'starts' => [], 'ends' => [], 'attributes' => 0, 'objects' => 0, 'undated' => 0,
            'facets' => ['types' => [], 'objects' => [], 'categories' => [], 'events' => []],
        ];
        foreach ($tallies as $eventId => $tally) {
            foreach (['starts', 'ends'] as $key) {
                foreach ($tally[$key] as $day => $n) {
                    $merged[$key][$day] = ($merged[$key][$day] ?? 0) + $n;
                }
            }
            foreach (['attributes', 'objects', 'undated'] as $key) {
                $merged[$key] += $tally[$key];
            }
            foreach ($tally['facets'] as $facet => $counts) {
                foreach ($counts as $name => $n) {
                    $merged['facets'][$facet][$name] = ($merged['facets'][$facet][$name] ?? 0) + $n;
                }
            }
            $merged['facets']['events'][$eventId] = $tally['attributes'] + $tally['objects'];
        }
        foreach (['types', 'objects', 'categories'] as $facet) {
            arsort($merged['facets'][$facet]);
        }
        return $merged;
    }

    /**
     * How many rows are seen in each bucket of the span: a row counts in every
     * bucket between its first and last day, a row with one end only in that
     * end's bucket.
     *
     * The span runs from the earliest day to the latest, but stops
     * SPAN_AHEAD_DAYS past today and reaches back at most SPAN_MAX_DAYS; rows
     * wholly beyond it are counted in `outside`.
     *
     * @param array $starts day => rows starting that day
     * @param array $ends day => rows ending that day
     * @param string $today `Y-m-d`
     * @return array|null from, to, unit, max, bars (from, to, label, count), outside
     */
    public static function histogram(array $starts, array $ends, $today)
    {
        $days = array_merge(array_keys($starts), array_keys($ends));
        if (empty($days)) {
            return null;
        }
        $todayDay = intdiv((int)(new DateTimeImmutable($today . ' 00:00:00', new DateTimeZone('UTC')))
            ->getTimestamp(), 86400);
        $to = min(max($days), $todayDay + self::SPAN_AHEAD_DAYS);
        $from = max(min($days), $to - self::SPAN_MAX_DAYS + 1);
        if ($from > $to) {
            return null;
        }
        $fromDate = gmdate('Y-m-d', $from * 86400);
        $toDate = gmdate('Y-m-d', $to * 86400);
        $unit = ValueProfileBuckets::unitForSpan($to - $from + 1, [
            ['days' => 45, 'unit' => ValueProfileBuckets::DAY],
            ['days' => 370, 'unit' => ValueProfileBuckets::WEEK],
            ['days' => null, 'unit' => ValueProfileBuckets::MONTH],
        ]);
        $series = ValueProfileBuckets::series($fromDate, $toDate, $unit);
        $index = ValueProfileBuckets::locate($series);
        $count = count($series);
        $bucketOf = function ($day) use ($from, $to, $index, $count) {
            if ($day < $from) {
                return -1;
            }
            return $day > $to ? $count : $index[gmdate('Y-m-d', $day * 86400)];
        };

        $delta = array_fill(0, $count + 1, 0);
        $outside = 0;
        foreach ($starts as $day => $n) {
            $bucket = max(0, $bucketOf((int)$day));
            if ($bucket >= $count) {
                $outside += $n;
                continue;
            }
            $delta[$bucket] += $n;
        }
        foreach ($ends as $day => $n) {
            $bucket = $bucketOf((int)$day);
            if ($bucket < 0) {
                $outside += $n;
            }
            if ($bucket < $count) {
                $delta[$bucket + 1] -= $n;
            }
        }

        $bars = [];
        $running = 0;
        $max = 0;
        foreach ($series as $position => $bucket) {
            $running += $delta[$position];
            $max = max($max, $running);
            $bars[] = [
                'from' => $bucket['from'],
                'to' => $bucket['to'],
                'label' => $bucket['title'],
                'count' => $running,
            ];
        }
        return [
            'from' => $fromDate,
            'to' => $toDate,
            'unit' => $unit,
            'max' => $max,
            'bars' => $bars,
            'outside' => $outside,
        ];
    }

    /**
     * The window asked for, clamped to whole days, or the histogram's span.
     *
     * @param array $options from, to (`Y-m-d`)
     * @param array|null $histogram
     * @return array|null [first day, last day]
     */
    public static function window(array $options, $histogram)
    {
        $day = function ($date) {
            if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return null;
            }
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
            return $parsed === false ? null : intdiv($parsed->getTimestamp(), 86400);
        };
        $from = $day($options['from'] ?? null);
        $to = $day($options['to'] ?? null);
        if ($histogram !== null) {
            $from = $from ?? $day($histogram['from']);
            $to = $to ?? $day($histogram['to']);
        }
        if ($from === null || $to === null) {
            return null;
        }
        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    /**
     * The rows overlapping the window, earliest first, at most ITEM_CAP.
     *
     * @return array [items, capped, rows in window]
     */
    private function items(array $scopes, array $window, array $options)
    {
        $scopes = self::filterEvents($scopes, $options['events'] ?? []);
        if (empty($scopes)) {
            return [[], false, 0];
        }
        $lower = $window[0] * self::DAY_US;
        $upper = ($window[1] + 1) * self::DAY_US - 1;
        $want = self::wanted($options);

        $parts = [];
        if ($want['attributes']) {
            $parts['attributes'] = $this->attributeFilter($scopes, $options, $lower, $upper);
        }
        if ($want['objects']) {
            $parts['objects'] = $this->objectFilter($scopes, $options, $lower, $upper);
        }

        $attributes = $objects = [];
        if (isset($parts['attributes'])) {
            list($sql, $params) = $parts['attributes'];
            $attributes = $this->rows(
                'SELECT a.id, a.uuid, a.event_id, a.type, a.category, a.to_ids,'
                . ' LEFT(a.value1, ' . (self::LABEL_MAX + 1) . ') AS value1,'
                . ' LEFT(a.value2, ' . (self::LABEL_MAX + 1) . ') AS value2,'
                . ' a.first_seen, a.last_seen, ' . self::seenSql('a', 's') . ' AS s, '
                . self::seenSql('a', 'e') . ' AS e ' . $sql
                . ' ORDER BY s, a.id LIMIT ' . (self::ITEM_CAP + 1),
                $params
            );
        }
        if (isset($parts['objects'])) {
            list($sql, $params) = $parts['objects'];
            $objects = $this->rows(
                'SELECT env.* ' . $sql . ' ORDER BY env.s, env.id LIMIT ' . (self::ITEM_CAP + 1),
                $params
            );
        }

        $merged = self::mergeRows($attributes, $objects, self::ITEM_CAP);
        $capped = count($attributes) + count($objects) > self::ITEM_CAP;
        $inWindow = count($merged);
        if ($capped) {
            $inWindow = 0;
            foreach ($parts as $part) {
                list($sql, $params) = $part;
                $inWindow += (int)($this->rows('SELECT COUNT(*) AS n ' . $sql, $params)[0]['n'] ?? 0);
            }
        }

        $objectIds = [];
        $childTotal = 0;
        foreach ($merged as $row) {
            if ($row['kind'] === 'object') {
                $objectIds[] = (int)$row['id'];
                $childTotal += (int)$row['dated_children'];
            }
        }
        $children = [];
        if (!empty($objectIds) && $childTotal <= self::CHILD_INLINE_CAP) {
            $children = $this->children($scopes, $objectIds, self::CHILD_INLINE_CAP + 1);
        }
        $inline = !empty($objectIds) && $childTotal <= self::CHILD_INLINE_CAP;

        $items = [];
        foreach ($merged as $row) {
            if ($row['kind'] === 'attribute') {
                $items[] = self::attributeItem($row);
                continue;
            }
            $item = self::objectItem($row);
            $item['children'] = $inline ? ($children[(int)$row['id']] ?? []) : null;
            $items[] = $item;
        }
        return [$items, $capped, $inWindow];
    }

    /**
     * Two lists already sorted by start, as one, the first $cap of them.
     *
     * @return array rows, each tagged with its kind
     */
    public static function mergeRows(array $attributes, array $objects, $cap)
    {
        $rows = [];
        foreach ($attributes as $row) {
            $rows[] = ['kind' => 'attribute'] + $row;
        }
        foreach ($objects as $row) {
            $rows[] = ['kind' => 'object'] + $row;
        }
        usort($rows, function ($a, $b) {
            return [(int)$a['s'], $a['kind'], (int)$a['id']] <=> [(int)$b['s'], $b['kind'], (int)$b['id']];
        });
        return array_slice($rows, 0, $cap);
    }

    /**
     * Which kinds of row the filter can match: a type picks attributes, an
     * object name picks objects, both pick both.
     */
    public static function wanted(array $options)
    {
        $kind = $options['kind'] ?? null;
        $types = !empty($options['types']);
        $objects = !empty($options['objects']);
        return [
            'attributes' => $kind !== 'object' && ($types || !$objects),
            'objects' => $kind !== 'attribute' && ($objects || !$types),
        ];
    }

    private static function filterEvents(array $scopes, array $eventIds)
    {
        if (empty($eventIds)) {
            return $scopes;
        }
        return array_intersect_key($scopes, array_flip(array_map('intval', $eventIds)));
    }

    private function attributeFilter(array $scopes, array $options, $lower, $upper)
    {
        $sql = $this->looseFrom($scopes) . ' AND ' . self::datedSql('a')
            . ' AND ' . self::seenSql('a', 's') . ' <= ? AND ' . self::seenSql('a', 'e') . ' >= ?';
        $params = [$upper, $lower];
        if (!empty($options['types'])) {
            $sql .= ' AND a.type IN (' . self::placeholders($options['types']) . ')';
            $params = array_merge($params, array_values($options['types']));
        }
        if (!empty($options['categories'])) {
            $sql .= ' AND a.category IN (' . self::placeholders($options['categories']) . ')';
            $params = array_merge($params, array_values($options['categories']));
        }
        if (isset($options['q']) && trim($options['q']) !== '') {
            $like = '%' . self::escapeLike(trim($options['q'])) . '%';
            $sql .= ' AND (a.value1 LIKE ? OR a.value2 LIKE ?)';
            array_push($params, $like, $like);
        }
        return [$sql, $params];
    }

    private function objectFilter(array $scopes, array $options, $lower, $upper)
    {
        $sql = 'FROM (' . $this->envelopeSql($scopes) . ') env'
            . ' WHERE env.s IS NOT NULL AND env.s <= ? AND env.e >= ?';
        $params = [$upper, $lower];
        if (!empty($options['objects'])) {
            $sql .= ' AND env.name IN (' . self::placeholders($options['objects']) . ')';
            $params = array_merge($params, array_values($options['objects']));
        }
        if (!empty($options['categories'])) {
            $sql .= ' AND env.category IN (' . self::placeholders($options['categories']) . ')';
            $params = array_merge($params, array_values($options['categories']));
        }
        if (isset($options['q']) && trim($options['q']) !== '') {
            $like = '%' . self::escapeLike(mb_strtolower(trim($options['q']))) . '%';
            $sql .= ' AND (LOWER(env.name) LIKE ? OR EXISTS (SELECT 1 FROM attributes c'
                . ' WHERE c.object_id = env.id AND c.deleted = 0 AND '
                . $this->eventSql($scopes, 'c') . ' AND (c.value1 LIKE ? OR c.value2 LIKE ?)))';
            array_push($params, $like, $like, $like);
        }
        return [$sql, $params];
    }

    /**
     * Dated attributes of the given objects, by object id, earliest first.
     */
    private function children(array $scopes, array $objectIds, $limit)
    {
        $rows = $this->rows(
            'SELECT a.id, a.uuid, a.event_id, a.object_id, a.object_relation, a.type, a.category,'
            . ' a.to_ids, LEFT(a.value1, ' . (self::LABEL_MAX + 1) . ') AS value1,'
            . ' LEFT(a.value2, ' . (self::LABEL_MAX + 1) . ') AS value2, a.first_seen, a.last_seen,'
            . ' ' . self::seenSql('a', 's') . ' AS s, ' . self::seenSql('a', 'e') . ' AS e'
            . ' FROM attributes a WHERE a.object_id IN (' . self::placeholders($objectIds) . ')'
            . ' AND a.deleted = 0 AND ' . self::datedSql('a') . ' AND ' . $this->eventSql($scopes, 'a')
            . ' ORDER BY a.object_id, s, a.id LIMIT ' . (int)$limit,
            array_map('intval', $objectIds)
        );
        $children = [];
        foreach ($rows as $row) {
            $children[(int)$row['object_id']][] = self::attributeItem($row);
        }
        return $children;
    }

    public static function attributeItem(array $row)
    {
        $item = [
            'kind' => 'attribute',
            'id' => (int)$row['id'],
            'uuid' => $row['uuid'],
            'event_id' => (int)$row['event_id'],
            'label' => self::label($row['value1'], $row['value2'] ?? ''),
            'type' => $row['type'],
            'category' => $row['category'],
            'to_ids' => !empty($row['to_ids']),
            'first_seen' => self::micro($row['first_seen']),
            'last_seen' => self::micro($row['last_seen']),
            'start' => self::micro($row['s']),
            'end' => self::micro($row['e']),
        ];
        if (isset($row['object_relation'])) {
            $item['relation'] = $row['object_relation'];
        }
        return $item;
    }

    public static function objectItem(array $row)
    {
        return [
            'kind' => 'object',
            'id' => (int)$row['id'],
            'uuid' => $row['uuid'],
            'event_id' => (int)$row['event_id'],
            'label' => $row['name'],
            'category' => $row['category'],
            'first_seen' => self::micro($row['ofs']),
            'last_seen' => self::micro($row['ols']),
            'start' => self::micro($row['s']),
            'end' => self::micro($row['e']),
            'children_count' => (int)$row['dated_children'],
        ];
    }

    /**
     * An attribute's value as MISP shows it, a composite joined by a pipe,
     * cut to LABEL_MAX characters.
     */
    public static function label($value1, $value2)
    {
        $value = (string)$value1;
        if ((string)$value2 !== '') {
            $value .= '|' . $value2;
        }
        if (mb_strlen($value) > self::LABEL_MAX) {
            $value = mb_substr($value, 0, self::LABEL_MAX - 1) . '…';
        }
        return $value;
    }

    private static function micro($value)
    {
        return $value === null ? null : (int)$value;
    }

    /**
     * One row per visible object of the events, with its own dates (ofs, ols),
     * the span of its visible dated attributes folded in (s, e) and how many
     * of those there are.
     */
    private function envelopeSql(array $scopes)
    {
        $inner = 'SELECT o.id, o.uuid, o.event_id, o.name, o.`meta-category` AS category,'
            . ' o.first_seen AS ofs, o.last_seen AS ols,'
            . ' ' . self::seenSql('o', 's') . ' AS os, ' . self::seenSql('o', 'e') . ' AS oe,'
            . ' MIN(' . self::seenSql('a', 's') . ') AS amin,'
            . ' MAX(' . self::seenSql('a', 'e') . ') AS amax,'
            . ' COUNT(a.id) AS dated_children'
            . ' FROM objects o LEFT JOIN attributes a ON a.object_id = o.id AND a.deleted = 0'
            . ' AND ' . self::datedSql('a') . ' AND ' . $this->eventSql($scopes, 'a')
            . ' WHERE o.deleted = 0 AND ' . $this->eventSql($scopes, 'o')
            . ' GROUP BY o.id, o.uuid, o.event_id, o.name, o.`meta-category`, o.first_seen, o.last_seen';
        return 'SELECT t.id, t.uuid, t.event_id, t.name, t.category, t.ofs, t.ols, t.dated_children,'
            . ' LEAST(COALESCE(t.os, t.amin), COALESCE(t.amin, t.os)) AS s,'
            . ' GREATEST(COALESCE(t.oe, t.amax), COALESCE(t.amax, t.oe)) AS e'
            . ' FROM (' . $inner . ') t';
    }

    /**
     * FROM … WHERE for the events' visible loose attributes.
     *
     * Pinned to event_id: left alone, the optimiser intersects it with
     * object_id, whose 0 range holds every loose attribute of the instance.
     */
    private function looseFrom(array $scopes)
    {
        return 'FROM attributes a FORCE INDEX (event_id) WHERE a.object_id = 0 AND a.deleted = 0 AND '
            . $this->eventSql($scopes, 'a');
    }

    /**
     * The events' rows the reader may see: everything of an event they hold
     * in full, the shareable rest of the others.
     */
    private function eventSql(array $scopes, $alias)
    {
        $full = $partial = [];
        $sgids = [];
        foreach ($scopes as $eventId => $scope) {
            if ($scope['full']) {
                $full[] = (int)$eventId;
            } else {
                $partial[] = (int)$eventId;
                $sgids = array_keys($scope['sgids']);
            }
        }
        $clauses = [];
        if (!empty($full)) {
            $clauses[] = "$alias.event_id IN (" . implode(', ', $full) . ')';
        }
        if (!empty($partial)) {
            $visible = "$alias.distribution IN (1, 2, 3, 5)";
            if (!empty($sgids)) {
                $visible = "($visible OR ($alias.distribution = 4 AND $alias.sharing_group_id IN ("
                    . implode(', ', array_map('intval', $sgids)) . ')))';
            }
            $clauses[] = "($alias.event_id IN (" . implode(', ', $partial) . ") AND $visible)";
        }
        return empty($clauses) ? '1 = 0' : '(' . implode(' OR ', $clauses) . ')';
    }

    /**
     * The earlier (s) or later (e) seen date of a row, whichever ends it has.
     */
    private static function seenSql($alias, $end)
    {
        $first = "COALESCE($alias.first_seen, $alias.last_seen)";
        $last = "COALESCE($alias.last_seen, $alias.first_seen)";
        return ($end === 's' ? 'LEAST' : 'GREATEST') . "($first, $last)";
    }

    private static function datedSql($alias)
    {
        return "($alias.first_seen IS NOT NULL OR $alias.last_seen IS NOT NULL)";
    }

    private static function placeholders(array $values)
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    private static function escapeLike($value)
    {
        return addcslashes($value, '%_\\');
    }

    private function rows($sql, array $params = [])
    {
        $flat = [];
        foreach ($this->Attribute->query($sql, $params, false) as $row) {
            $r = [];
            foreach ($row as $part) {
                if (is_array($part)) {
                    $r += $part;
                }
            }
            $flat[] = $r;
        }
        return $flat;
    }
}
