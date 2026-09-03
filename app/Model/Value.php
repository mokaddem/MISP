<?php
App::uses('AppModel', 'Model');
App::uses('ValueFieldKind', 'Tools');

/**
 * The value-identity seam for the Value Profile page.
 *
 * The subject of that page is a value string — `185.234.219.24`, a hash,
 * a domain — which is not a row of any table. It is a set of attribute
 * rows that happen to carry the same string, so this model has no table
 * of its own and resolves the value against `attributes` instead.
 *
 * `useTable = false` is established practice here: `Community`, `Module`
 * and `EventLock` all do it.
 *
 * **This is the only file in the Value Profile feature that names
 * `value1` or `value2`** (prd/value-profile-live/00-contract.md §14.3).
 * A feature is coming that moves `attributes.value` into a table of its
 * own — possibly several, split by type. This page does not build it,
 * does not wait for it and does not assume its shape; what it does is
 * arrange that when it lands, one file changes. Verification is a grep:
 * those two column names must not appear in ValueProfile.php, in
 * app/Lib/Tools/Value*.php, in ValuesController.php or under
 * app/View/Themed/Overmind/Elements/Values/.
 *
 * Identity is the value, not the pair `(type, value)`. A composite
 * attribute therefore contributes two identities, which is the reading
 * `Sighting::saveSightings` already takes when it matches value1 OR
 * value2 to write a sighting by value.
 */
class Value extends AppModel
{
    public $useTable = false;

    /**
     * The event and object columns every row on the Relationships tab
     * is read through — the reporter, the audience and the object
     * template — stated once so the three fetchers cannot drift.
     *
     * They are also the columns `MispAttribute::buildConditions`
     * itself names, which is why a bare `contain` looks like it works:
     * the join is there and the ACL is enforced either way. What is
     * missing without this is the data, not the permission.
     */
    /**
     * How many values one prevalence lookup asks about at a time.
     *
     * The candidate set is a whole co-occurrence fold — 9,520 values on
     * `8.8.8.8` — and an `IN` list that long is a query nobody should
     * send. Measured flat between 1,000 and 2,000 per chunk, so the
     * smaller one stands.
     */
    const PREVALENCE_CHUNK = 1000;

    const CONTEXT_FIELDS = array(
        'Event' => array('fields' => array(
            'Event.id',
            'Event.info',
            'Event.date',
            'Event.orgc_id',
            'Event.org_id',
            'Event.distribution',
            'Event.sharing_group_id',
            'Event.published',
        )),
        'Object' => array('fields' => array(
            'Object.id',
            'Object.name',
            'Object.distribution',
            'Object.sharing_group_id',
        )),
    );

    /**
     * MISP's attribute model, whose `$alias` is `Attribute` — so the
     * rows this class returns are keyed the way the templates and every
     * other MISP index already read them.
     *
     * @var MispAttribute
     */
    private $attributeModel = null;

    /**
     * @return MispAttribute
     */
    private function attributes()
    {
        if ($this->attributeModel === null) {
            $this->attributeModel = ClassRegistry::init('MispAttribute');
        }
        return $this->attributeModel;
    }

    /**
     * The condition fragment that selects a value's occurrences.
     *
     * Composes with `MispAttribute::buildConditions($user)` untouched,
     * so every existing ACL fetcher keeps working exactly as it does
     * today. Tomorrow this becomes a subquery or a join against the
     * value table(s) and nothing above it changes.
     *
     * `$options['types']` exists for that split: a caller that knows
     * which types it wants passes them and a per-type table can be
     * selected; a caller that does not gets the union. It costs a
     * parameter nobody has to use now and saves revisiting every call
     * site later.
     *
     * @param string $value
     * @param array $options `types` narrows to a set of MISP types
     * @return array
     */
    public function conditionsFor($value, array $options = array())
    {
        $conditions = array(
            'OR' => array(
                'Attribute.value1' => $value,
                'Attribute.value2' => $value,
            ),
        );
        if (!empty($options['types'])) {
            return array(
                $conditions,
                'Attribute.type' => $options['types'],
            );
        }
        return $conditions;
    }

    /**
     * How many occurrences of this value the viewer may see.
     *
     * Every count on the Value Profile page is the viewer's (§14.6):
     * the URL takes any value the reader types, so a count that
     * included invisible occurrences would turn the page into a
     * membership oracle for any indicator on the instance.
     *
     * An aggregate rather than a row fetch because the answer is one
     * number, and because its whole job is to be the total that a
     * capped row set is not — it cannot be derived from the rows.
     *
     * @param array $user
     * @param string $value
     * @param array $options As conditionsFor
     * @return int
     */
    public function occurrenceCountFor(array $user, $value,
        array $options = array()
    ) {
        $attributes = $this->attributes();
        $conditions = $attributes->buildConditions($user);
        $conditions['AND'][] = $this->conditionsFor($value, $options);
        return (int)$attributes->find('count', array(
            'conditions' => $conditions,
            'recursive' => -1,
            // buildConditions() references Event.* and Object.* directly
            // rather than through subqueries, so both have to be joined
            // for the ACL to be expressible at all.
            'contain' => array('Event', 'Object'),
        ));
    }

    /**
     * Four numbers about the whole occurrence set, in one aggregate.
     *
     * Tier 2 (§14.4), and the written reason is a measurement. The
     * Sightings tab needs the value's occurrence, event and organisation
     * counts for the write card's fan-out sentence and its oldest
     * occurrence date for the chart's span, and it needs them over
     * *every* occurrence rather than over a page. Materialising rows to
     * count them cost 617 ms on `443` — 48,255 occurrences behind three
     * sightings — and 280 ms on `0.0.0.0`. This is 4 ms on both.
     *
     * `orgs` counts creator organisations, matching the fan-out
     * sentence's wording and the Occurrences rail's organisation facet.
     *
     * @param array $user
     * @param string $value
     * @param array $options As conditionsFor
     * @return array `occurrences`, `events`, `orgs`, `oldest`, `newest`
     */
    public function occurrenceSummaryFor(array $user, $value,
        array $options = array()
    ) {
        $attributes = $this->attributes();
        $conditions = $attributes->buildConditions($user);
        $conditions['AND'][] = $this->conditionsFor($value, $options);
        $row = $attributes->find('first', array(
            'fields' => array(
                'COUNT(DISTINCT Attribute.id) AS occurrences',
                'COUNT(DISTINCT Event.id) AS events',
                'COUNT(DISTINCT Event.orgc_id) AS orgs',
                'MIN(Attribute.timestamp) AS oldest',
                'MAX(Attribute.timestamp) AS newest',
            ),
            'conditions' => $conditions,
            'recursive' => -1,
            'contain' => array('Event', 'Object'),
        ));
        $found = empty($row[0]) ? array() : $row[0];
        return array(
            'occurrences' => (int)($found['occurrences'] ?? 0),
            'events' => (int)($found['events'] ?? 0),
            'orgs' => (int)($found['orgs'] ?? 0),
            'oldest' => empty($found['oldest'])
                ? null
                : (int)$found['oldest'],
            'newest' => empty($found['newest'])
                ? null
                : (int)$found['newest'],
        );
    }

    /**
     * How widely each of many values is spread, for this viewer.
     *
     * `occurrenceSummaryFor`'s `events` count, plural — the denominator
     * the Relationships tab's **Most specific** rank divides by. A
     * neighbour that shares five events with the value is worth
     * clicking if those are five of the six events it appears in
     * anywhere, and worth ignoring if they are five of two hundred;
     * ranking cannot tell those apart without this number.
     *
     * **Viewer-scoped, and that is not a detail.** §14.6 makes every
     * count on the page the reader's own, and the printed fraction is
     * read as *"in 8 of its 204 events"* beside a value the reader can
     * open. A denominator over events they cannot see would both leak
     * membership and disagree with the page they land on. The ACL join
     * costs almost nothing here: 775 ms against 626 ms unscoped over
     * `8.8.8.8`'s 9,520 neighbours, the worst fold on the instance.
     *
     * **Two `IN` passes and nothing else, which is the whole design.**
     * Identity and storage disagree on composite attributes: a value is
     * an identity when it is `value1` **or** `value2`
     * (`conditionsFor`, and the reason is in this class's own
     * docblock), while the co-occurrence fold keys its rows on
     * `Attribute.value`, which composes to `value1|value2`. 16% of this
     * instance's attributes are composite and 2,231 of `8.8.8.8`'s
     * 10,040 neighbour keys carry a pipe, so neither reading can be
     * skipped. A key's count is the union of the two: the composed
     * value equals `A|B` exactly when `value1` is `A` and `value2` is
     * `B`, or when `value1` is the literal `A|B` and `value2` is empty.
     *
     * The union is assembled from the rows rather than from the query,
     * because matching pairs *in SQL* is what made the first attempt
     * unusable — a thousand `(value1 = A AND value2 = B)` arms OR'd
     * into one condition took **311 seconds** over this fold and
     * matched far more than it was asked about. Here both passes are
     * plain `IN` lists on the two indexed value columns, and the
     * composed key is rebuilt in PHP from the `value1`/`value2` pair
     * each row already carries. The first parts of the composite keys
     * join the first pass's `IN` list so those rows come back at all.
     *
     * Ids come back as rows and the distinct-count is taken in PHP,
     * because `COUNT(DISTINCT)` grouped by `value1` cannot see the
     * `value2` matches and two grouped counts cannot be added without
     * double-counting an id that holds both.
     *
     * @param array $user
     * @param array $values Value strings, as the caller keys them
     * @param array $options `unit` counts `event` (default) or
     *                       `object`; otherwise as conditionsFor
     * @return array Value string => count, absent when zero
     */
    public function prevalenceFor(array $user, array $values,
        array $options = array()
    ) {
        $wanted = array();
        foreach ($values as $value) {
            if ($value !== '' && $value !== null) {
                $wanted[(string)$value] = true;
            }
        }
        if (empty($wanted)) {
            return array();
        }
        $object = isset($options['unit'])
            && $options['unit'] === 'object';
        $idKey = $object ? 'object_id' : 'event_id';

        /*
         * The left-hand halves of the composite keys, so the first pass
         * returns the rows a composed key is rebuilt from. A key whose
         * pipe is part of a plain value contributes a first part that
         * simply matches nothing.
         */
        $probes = $wanted;
        foreach (array_keys($wanted) as $key) {
            // Cast for the same reason as below: a numeric-looking key
            // comes back from array_keys() as an int.
            $key = (string)$key;
            $cut = strpos($key, '|');
            if ($cut !== false && $cut > 0) {
                $probes[substr($key, 0, $cut)] = true;
            }
        }

        /*
         * **Back to strings, and this is not defensive tidying.** PHP
         * turns an array key that looks like an integer into one, so
         * `array_keys` hands back `443` and `1204` — real neighbours,
         * a port and a passive-DNS record count — as ints. CakePHP
         * binds them as integers, MariaDB then compares a varchar
         * column against a number, converts the whole column to do it
         * and abandons the `value1` index: a full scan of 3.2M rows
         * joined twice. Only the chunks holding a numeric-looking
         * value were affected, which is why 4 of 16 queries took 40
         * seconds each while an 983-row one among them took 41 — the
         * cost tracked the cast, not the rows. 171 seconds over
         * `8.8.8.8`'s fold, all of it here.
         */
        $seen = array();
        $this->prevalencePass($user, array_map('strval',
            array_keys($probes)), 'value1', $idKey, $wanted, $seen,
            $options);
        $this->prevalencePass($user, array_map('strval',
            array_keys($wanted)), 'value2', $idKey, $wanted, $seen,
            $options);

        $out = array();
        foreach ($seen as $value => $ids) {
            $out[$value] = count($ids);
        }
        return $out;
    }

    /**
     * One `IN` pass, folded onto the id sets the caller is building.
     *
     * Chunked because the candidate set is a whole co-occurrence fold
     * and an `IN` list of twelve thousand strings is a query nobody
     * should send. Measured flat between 1,000 and 2,000 per chunk.
     *
     * `Event` and `Object` are joined because
     * `MispAttribute::buildConditions` names their columns directly,
     * and only their ids are selected: the ACL filters on columns it
     * does not have to read back, and a fold this wide should not
     * carry every event's `info` through PHP to reach a count.
     *
     * @param array $user
     * @param array $probes The values to match on this column
     * @param string $column `value1` or `value2`
     * @param string $idKey `event_id` or `object_id`
     * @param array $wanted The keys the caller actually asked about
     * @param array $seen Accumulator: key => array of id => true
     * @param array $options
     * @return void
     */
    private function prevalencePass(array $user, array $probes, $column,
        $idKey, array $wanted, array &$seen, array $options
    ) {
        $attributes = $this->attributes();
        $idField = 'Attribute.' . $idKey;
        foreach (array_chunk($probes, self::PREVALENCE_CHUNK) as $chunk) {
            $conditions = $attributes->buildConditions($user);
            $conditions['AND'][] = array('Attribute.' . $column => $chunk);
            if (!empty($options['types'])) {
                $conditions['AND'][]
                    = array('Attribute.type' => $options['types']);
            }
            if ($idKey === 'object_id') {
                $conditions['AND'][]
                    = array('Attribute.object_id !=' => 0);
            }
            $rows = $attributes->find('all', array(
                'fields' => array(
                    'Attribute.value1',
                    'Attribute.value2',
                    $idField,
                ),
                'conditions' => $conditions,
                'recursive' => -1,
                'contain' => array(
                    'Event' => array('fields' => array('Event.id')),
                    'Object' => array('fields' => array('Object.id')),
                ),
                /*
                 * **Grouped, and `order` explicitly off — it takes
                 * both.** The grouping is not for correctness; the
                 * accumulator below is a set keyed on the id and would
                 * dedupe anyway. It is there to stop the rows reaching
                 * PHP at all: one chunk of a thousand values matches
                 * 35,884 attribute rows and collapses to 1,197 distinct
                 * triples, and hydrating the difference is what cost
                 * **190 seconds** over `8.8.8.8`'s fold against 0.4
                 * seconds in the database.
                 *
                 * And `order` has to go with it, because
                 * `MispAttribute` carries a default
                 * `Attribute.event_id DESC` that CakePHP appends to
                 * every `find()`: grouping under that sort was **401
                 * seconds**, and dropping the group to escape the sort
                 * was **1,431**. Neither alone is the answer. An
                 * ordering nobody reads is not free.
                 */
                'group' => array(
                    'Attribute.value1',
                    'Attribute.value2',
                    $idField,
                ),
                'order' => false,
            ));
            foreach ($rows as $row) {
                $row = $row['Attribute'];
                if (!isset($row[$idKey])) {
                    continue;
                }
                $id = (int)$row[$idKey];
                $one = (string)$row['value1'];
                $two = isset($row['value2'])
                    ? (string)$row['value2']
                    : '';
                /*
                 * The identity this row carries on the column that
                 * matched, and the composed key it spells. Either may
                 * be a key the caller asked about; both, when a value
                 * is stored plain in one place and as half of a pair in
                 * another.
                 */
                $identity = $column === 'value2' ? $two : $one;
                if ($identity !== '' && isset($wanted[$identity])) {
                    $seen[$identity][$id] = true;
                }
                $composed = $two === '' ? $one : $one . '|' . $two;
                if ($composed !== '' && isset($wanted[$composed])) {
                    $seen[$composed][$id] = true;
                }
            }
        }
    }

    /**
     * Only the occurrences that carry at least one sighting.
     *
     * The query this tab could not do without, and the one place in this
     * feature that joins another model's table. **The alternative was
     * measured and it does not work**: scoping
     * `Sighting::listSightings` by the value's whole occurrence set
     * means handing it 48,255 ids on `443`, which it re-resolves through
     * `fetchAttributes` — 1.6 to 3.4 seconds per panel for three
     * sightings. Narrowing first turns that into a millisecond.
     *
     * Tier 2, and it is an aggregate rather than a fetch: one row per
     * occurrence that has been reported, and nothing about the reports
     * themselves. **The sighting policy is not applied here and does not
     * need to be** — `listSightings` applies it to the rows, and this
     * set never reaches the page: an occurrence whose only sighting the
     * reader may not see contributes no row, no count and no curve
     * distinguishable from an un-sighted one.
     *
     * The table name comes off the `Sighting` model rather than being
     * spelled here, so the model that owns the data still owns where it
     * lives.
     *
     * Soft-deleted occurrences are included, because
     * `buildConditions()` includes them; the caller filters them before
     * `listSightings`, which forces `deleted = 0` on its own re-fetch.
     *
     * @param array $user
     * @param string $value
     * @param array $options As conditionsFor
     * @return array The same shape as occurrenceIdsFor
     */
    public function sightedOccurrenceIdsFor(array $user, $value,
        array $options = array()
    ) {
        $attributes = $this->attributes();
        $conditions = $attributes->buildConditions($user);
        $conditions['AND'][] = $this->conditionsFor($value, $options);
        $sightings = ClassRegistry::init('Sighting');
        $rows = $attributes->find('all', array(
            'fields' => array(
                'Attribute.id',
                'Attribute.event_id',
                'Attribute.type',
                'Attribute.timestamp',
                'Attribute.last_seen',
                'Attribute.deleted',
            ),
            'conditions' => $conditions,
            'recursive' => -1,
            'contain' => array('Event', 'Object'),
            'joins' => array(array(
                'table' => $sightings->table,
                'alias' => 'ValueSighting',
                'type' => 'INNER',
                'conditions' => array(
                    'ValueSighting.attribute_id = Attribute.id',
                ),
            )),
            'group' => array('Attribute.id'),
        ));
        return self::keyById($rows);
    }

    /**
     * The value's occurrences as an id set, with the two columns any
     * value-scoped aggregate needs to label its own result.
     *
     * §14.3 of the contract sketches this as a bare list of ids, and
     * phase 22 declined to build it because its aggregate already had
     * the ids from its row fetch. Phase 23 is the caller it was waiting
     * for, and it needs slightly more than ids: a sighting is filed
     * against one occurrence, so the list panel's `Reported against`
     * column is that occurrence's event and type. Re-fetching those
     * would be a second resolution of the same value, and two
     * resolutions can disagree.
     *
     * `fields` keeps it to six columns rather than the whole row. It
     * takes `limit` and `order` because its callers cap it: reading
     * every occurrence a value has is what `occurrenceSummaryFor` is
     * for, and doing it by materialising rows cost seconds on the two
     * heaviest values on the instance.
     *
     * @param array $user
     * @param string $value
     * @param array $options As conditionsFor, plus `limit`/`order`
     * @return array attribute id => event_id, type, timestamp,
     *               last_seen, deleted
     */
    public function occurrenceIdsFor(array $user, $value,
        array $options = array()
    ) {
        $params = array(
            'conditions' => $this->conditionsFor($value, $options),
            'fields' => array(
                'Attribute.id',
                'Attribute.event_id',
                'Attribute.type',
                'Attribute.timestamp',
                'Attribute.last_seen',
                'Attribute.deleted',
            ),
            // buildConditions() names Event.* and Object.* directly, so
            // both have to be joined for the ACL to be expressible.
            'contain' => array('Event', 'Object'),
        );
        foreach (array('limit', 'page', 'order') as $key) {
            if (isset($options[$key])) {
                $params[$key] = $options[$key];
            }
        }
        return self::keyById(
            $this->attributes()->fetchAttributesSimple($user, $params)
        );
    }

    /**
     * The six-column shape both id-set accessors return, keyed by
     * attribute id.
     *
     * @param array $rows
     * @return array
     */
    private static function keyById(array $rows)
    {
        $set = array();
        foreach ($rows as $row) {
            $set[(int)$row['Attribute']['id']] = array(
                'event_id' => (int)$row['Attribute']['event_id'],
                'type' => $row['Attribute']['type'],
                'timestamp' => (int)$row['Attribute']['timestamp'],
                'last_seen' => $row['Attribute']['last_seen'] ?? null,
                'deleted' => !empty($row['Attribute']['deleted']),
            );
        }
        return $set;
    }

    /**
     * The value's occurrences, as `fetchAttributes` shapes them.
     *
     * `fetchAttributesSimple` rather than `fetchAttributes`, and the
     * difference matters twice. `fetchAttributes` forces
     * `Attribute.deleted = 0` for anyone without `perm_sync`, which
     * would make the soft-deleted occurrences this tab reveals
     * unreachable for every ordinary user; and it forces
     * `Attribute.object_id = 0` unless `flatten`, which would drop
     * every occurrence sitting inside an object — the rows the Context
     * column exists to describe.
     *
     * @param array $user
     * @param string $value
     * @param array $options `types`, plus `limit` and `order` passed to
     *                       the fetcher
     * @return array
     */
    public function occurrencesFor(array $user, $value,
        array $options = array()
    ) {
        $params = array(
            'conditions' => $this->conditionsFor($value, $options),
            'contain' => array(
                'Event',
                'Object',
                // The name behind a distribution-4 row's badge.
                'SharingGroup',
                // Carries tag_id only; attachTagsToAttributes() turns
                // those into the tag records the chips are drawn from.
                'AttributeTag',
            ),
        );
        foreach (array('limit', 'page', 'order') as $key) {
            if (isset($options[$key])) {
                $params[$key] = $options[$key];
            }
        }
        return $this->attributes()->fetchAttributesSimple($user, $params);
    }

    /*
     * ------------------------------------------------------------------
     * The Relationships tab's queries.
     *
     * These are here rather than in `ValueProfile` for one reason and it
     * is §14.4's tier 3: *any query that reaches attribute value storage
     * outside `Value`* is forbidden. Every method below either names
     * `value1`/`value2` or selects `Attribute.value`, which is the
     * virtual field over the same two columns
     * (`MispAttribute.php:55`) — so all of them are value storage and
     * all of them belong to the seam.
     *
     * That is a real cost: this file was to stay small, and the tab adds
     * five methods to it. The alternative was worse. A neighbour
     * aggregate written in `ValueProfile` would put the column names in
     * a second file, and the whole of §14.3 is the promise that when the
     * value table lands, **one** file changes.
     * ------------------------------------------------------------------
     */

    /**
     * The MISP types this value is stored under, most common first.
     *
     * The near-match section asks the engines about a type rather than
     * about a value — CIDR containment runs for `ip-dst` and not for
     * `sha256` — so it needs to know what this value *is* before it can
     * say which engine declines it. The page frame carries the same
     * array from the fixture; this is the viewer's own version of it,
     * and a value with no occurrence the reader may see correctly has
     * no type at all rather than a guessed one.
     *
     * @param array $user
     * @param string $value
     * @param array $options As conditionsFor
     * @return array [['type' => 'ip-dst', 'count' => 7], …]
     */
    public function typesFor(array $user, $value, array $options = array())
    {
        $attributes = $this->attributes();
        $conditions = $attributes->buildConditions($user);
        $conditions['AND'][] = $this->conditionsFor($value, $options);
        $rows = $attributes->find('all', array(
            'fields' => array(
                'Attribute.type',
                'COUNT(DISTINCT Attribute.id) AS occurrences',
            ),
            'conditions' => $conditions,
            'recursive' => -1,
            'contain' => array('Event', 'Object'),
            'group' => array('Attribute.type'),
            'order' => array('occurrences DESC'),
        ));
        $types = array();
        foreach ($rows as $row) {
            $types[] = array(
                'type' => $row['Attribute']['type'],
                'count' => (int)$row[0]['occurrences'],
            );
        }
        return $types;
    }

    /**
     * The events this value occurs in, newest occurrence first.
     *
     * One grouped aggregate rather than a row fetch, for the reason
     * `occurrenceSummaryFor` gives: the answer is one number per event
     * and materialising 48,255 rows to derive 1,844 of them is seconds
     * rather than milliseconds.
     *
     * The order is what makes the co-occurrence section's cut
     * defensible. A value in 1,844 events cannot have all of them read,
     * so *which* ones are read has to be a rule a reader would accept,
     * and "the events where this value was seen most recently" is the
     * one a person asking about an indicator today would pick.
     *
     * @param array $user
     * @param string $value
     * @param array $options As conditionsFor, plus `limit`
     * @return array event id => ['occurrences' => int, 'last' => int]
     */
    public function occurrenceEventsFor(array $user, $value,
        array $options = array()
    ) {
        $attributes = $this->attributes();
        $conditions = $attributes->buildConditions($user);
        $conditions['AND'][] = $this->conditionsFor($value, $options);
        $params = array(
            'fields' => array(
                'Attribute.event_id',
                'COUNT(DISTINCT Attribute.id) AS occurrences',
                'MAX(Attribute.timestamp) AS last_seen',
            ),
            'conditions' => $conditions,
            'recursive' => -1,
            'contain' => array('Event', 'Object'),
            'group' => array('Attribute.event_id'),
            'order' => array('last_seen DESC'),
        );
        if (isset($options['limit'])) {
            $params['limit'] = $options['limit'];
        }
        $events = array();
        foreach ($attributes->find('all', $params) as $row) {
            $events[(int)$row['Attribute']['event_id']] = array(
                'occurrences' => (int)$row[0]['occurrences'],
                'last' => (int)$row[0]['last_seen'],
            );
        }
        return $events;
    }

    /**
     * The labels this value's own occurrences carry.
     *
     * The tightest way a label reaches a value: not *an event
     * mentioning APT29 contained this address*, but *this address, in
     * this event, is marked APT29*. Both the neighbourhood card and the
     * co-occurrence fold print the difference on the row, so they have
     * to be able to tell them apart.
     *
     * **Galaxy and taxonomy tags together, and `is_galaxy` decides
     * which is which downstream.** They live in one table and one join,
     * so asking for one of them was costing the other a second query
     * once the co-occurrence fold started reading both — the reason
     * §10.2 gives for one table of neighbours rather than two reads.
     * A galaxy tag is still worthless until `fetchGalaxyClusters` says
     * the viewer may know its cluster exists; that ruling belongs to
     * the caller, and the flag is what lets it find the names to ask
     * about.
     *
     * **Grouped, not fetched.** A value can occur 48,255 times, and the
     * answer is a handful of names either way — so this aggregates in
     * SQL rather than materialising occurrences and folding them in
     * PHP. `attribute_tags` is joined explicitly because it is a
     * `hasMany` that would otherwise cost its own query per id, and the
     * answer needs no attribute rows at all.
     *
     * The colour and the flag join the grouping rather than riding
     * along outside it: they are constant per tag, but MySQL under
     * `ONLY_FULL_GROUP_BY` recognises that only through a key it can
     * see, and a selected column it cannot prove constant is an error
     * rather than a guess.
     *
     * The event scope is the caller's, and it is what makes the ACL
     * argument short: those ids came from `occurrenceEventsFor`, so the
     * events are ones this viewer may read. `buildConditions($user)` is
     * still applied, because an attribute inside a readable event can
     * be org-only.
     *
     * @param array $user
     * @param string $value
     * @param array $eventIds Events the caller has already resolved
     * @param array $options As conditionsFor
     * @return array tag name => `tag` (id, name, colour, is_galaxy) and
     *     `events` (event id => `occurrences`, `last`)
     */
    public function ownTagsFor(array $user, $value,
        array $eventIds, array $options = array()
    ) {
        if (empty($eventIds)) {
            return array();
        }
        $attributes = $this->attributes();
        $conditions = $attributes->buildConditions($user);
        $conditions['AND'][] = $this->conditionsFor($value, $options);
        $conditions['AND'][] = array(
            'Attribute.event_id' => array_values($eventIds),
            'Attribute.deleted' => 0,
        );
        $rows = $attributes->find('all', array(
            'fields' => array(
                'Tag.id',
                'Tag.name',
                'Tag.colour',
                'Tag.is_galaxy',
                'Attribute.event_id',
                'COUNT(DISTINCT Attribute.id) AS occurrences',
                /*
                 * So a label row's **Last together** reads the same
                 * clock as a value row's. Free — the group is already
                 * over these attributes, and the alternative was the
                 * carrying event's stamp, which is the day the report
                 * moved rather than the day this occurrence did.
                 */
                'MAX(Attribute.timestamp) AS last',
            ),
            'conditions' => $conditions,
            'recursive' => -1,
            'contain' => array('Event'),
            'joins' => array(
                array(
                    'table' => 'attribute_tags',
                    'alias' => 'AttributeTag',
                    'type' => 'INNER',
                    'conditions' => array(
                        'AttributeTag.attribute_id = Attribute.id',
                    ),
                ),
                array(
                    'table' => 'tags',
                    'alias' => 'Tag',
                    'type' => 'INNER',
                    'conditions' => array(
                        'Tag.id = AttributeTag.tag_id',
                    ),
                ),
            ),
            'group' => array(
                'Tag.id',
                'Tag.name',
                'Tag.colour',
                'Tag.is_galaxy',
                'Attribute.event_id',
            ),
        ));
        $found = array();
        foreach ($rows as $row) {
            $name = $row['Tag']['name'];
            $eventId = (int)$row['Attribute']['event_id'];
            if (!isset($found[$name])) {
                $found[$name] = array(
                    'tag' => array(
                        'id' => (int)$row['Tag']['id'],
                        'name' => $name,
                        'colour' => $row['Tag']['colour'],
                        'is_galaxy' => !empty($row['Tag']['is_galaxy']),
                    ),
                    'events' => array(),
                );
            }
            $found[$name]['events'][$eventId] = array(
                'occurrences' => (int)$row[0]['occurrences'],
                'last' => (int)$row[0]['last'],
            );
        }
        return $found;
    }

    /**
     * The objects this value sits in, newest occurrence first.
     *
     * The sibling section's input. Grouped for the same reason as
     * `occurrenceEventsFor`, and capped by the same argument: `0.0.0.0`
     * sits in 32,921 distinct objects on the verification instance, so
     * a section that read them all would be the slowest thing on the
     * page by two orders of magnitude.
     *
     * **And which relation the object files this value under**, which
     * is what turns a sibling into a labelled edge: `passive-dns ·
     * rrname → rdata` needs both ends, and only the far end is on the
     * sibling row. `MIN()` rather than the bare column, so the group is
     * legal under `ONLY_FULL_GROUP_BY` as well as without it; a value
     * filed twice in one object under two relations is rare enough that
     * naming the first alphabetically beats a second query.
     *
     * @param array $user
     * @param string $value
     * @param array $options As conditionsFor, plus `limit`
     * @return array object id => ['last' =>, 'relation' =>]
     */
    public function occurrenceObjectIdsFor(array $user, $value,
        array $options = array()
    ) {
        $attributes = $this->attributes();
        $conditions = $attributes->buildConditions($user);
        $conditions['AND'][] = $this->conditionsFor($value, $options);
        $conditions['AND'][] = array('Attribute.object_id >' => 0);
        $params = array(
            'fields' => array(
                'Attribute.object_id',
                'MAX(Attribute.timestamp) AS last_seen',
                'MIN(Attribute.object_relation) AS our_relation',
            ),
            'conditions' => $conditions,
            'recursive' => -1,
            'contain' => array('Event', 'Object'),
            'group' => array('Attribute.object_id'),
            'order' => array('last_seen DESC'),
        );
        if (isset($options['limit'])) {
            $params['limit'] = $options['limit'];
        }
        $objects = array();
        foreach ($attributes->find('all', $params) as $row) {
            $objects[(int)$row['Attribute']['object_id']] = array(
                'last' => (int)$row[0]['last_seen'],
                'relation' => $row[0]['our_relation'] === null
                    ? ''
                    : $row[0]['our_relation'],
            );
        }
        return $objects;
    }

    /**
     * The value's occurrences as UUIDs, which is how analyst data
     * addresses them.
     *
     * `Relationship` hangs off an `object_uuid`, never off an id, so
     * the asserted section's lookup is a UUID set and not the integer
     * set every other panel on this page uses. Keeping the two
     * accessors apart rather than adding `uuid` to `occurrenceIdsFor`
     * keeps that panel's cost visible: this is the only caller that
     * needs it, and it caps its own set.
     *
     * @param array $user
     * @param string $value
     * @param array $options As conditionsFor, plus `limit`/`order`
     * @return array uuid => ['id' =>, 'event_id' =>, 'type' =>]
     */
    public function occurrenceUuidsFor(array $user, $value,
        array $options = array()
    ) {
        $params = array(
            'conditions' => $this->conditionsFor($value, $options),
            'fields' => array(
                'Attribute.id',
                'Attribute.uuid',
                'Attribute.event_id',
                // Zero on a plain attribute, which is how a claim
                // about "the parent object" is told from no parent.
                'Attribute.object_id',
                'Attribute.type',
                /*
                 * Named explicitly, because `fields` and Containable
                 * together select exactly what is listed: the `Event`
                 * and `Object` joins are already in the query — the
                 * ACL needs them — but their columns are not fetched
                 * unless asked for. Every earlier caller read only
                 * `Attribute.*`, so the omission cost nothing until a
                 * container's uuid was wanted.
                 */
                'Event.uuid',
                'Object.uuid',
            ),
            'contain' => array('Event', 'Object'),
        );
        foreach (array('limit', 'order') as $key) {
            if (isset($options[$key])) {
                $params[$key] = $options[$key];
            }
        }
        $set = array();
        $rows = $this->attributes()->fetchAttributesSimple($user, $params);
        foreach ($rows as $row) {
            $set[$row['Attribute']['uuid']] = array(
                'id' => (int)$row['Attribute']['id'],
                'event_id' => (int)$row['Attribute']['event_id'],
                'type' => $row['Attribute']['type'],
                /*
                 * The containers this occurrence sits in, by uuid,
                 * because an analyst's claim can be written about any
                 * of the three and `relationships` names its ends by
                 * uuid alone. Taken from the records this fetch
                 * already contains rather than looked up again: the
                 * `Event` and `Object` joins above are what make the
                 * asserted section able to ask about a container
                 * without a second query per occurrence.
                 *
                 * `object_uuid` is null for a plain attribute —
                 * `object_id` is 0 there, and a claim can no more be
                 * written about object 0 than about a missing event.
                 */
                'event_uuid' => isset($row['Event']['uuid'])
                    ? $row['Event']['uuid']
                    : null,
                'object_uuid' => empty($row['Attribute']['object_id'])
                    || empty($row['Object']['uuid'])
                        ? null
                        : $row['Object']['uuid'],
            );
        }
        return $set;
    }

    /**
     * Everything else in a given set of events, or a given set of
     * objects — the co-occurrence section's rows and the sibling
     * section's rows, which are the same query against two scopes.
     *
     * **Rows and not an aggregate, deliberately, and this is the one
     * place on the page where that is the cheaper answer.** The panel
     * needs six things per neighbour — how many events it shares, when
     * it was last seen with us, which organisations reported it, which
     * events, which object template, what distribution — and five of
     * them are multi-valued per neighbour. As aggregates that is one
     * `GROUP BY` per column, each re-scanning the same rows;
     * `GROUP_CONCAT` would fold them into one query but appears nowhere
     * else in MISP and is not portable. Scanning the rows once and
     * folding in PHP costs one query and gives all six.
     *
     * It is only affordable because **the caller has already bounded
     * the scope**, which is the whole of the co-occurrence design: the
     * event set is chosen and capped before this runs, so the row count
     * is bounded by a number the panel prints rather than by how
     * popular the value is. §14.4's warning about counting in PHP is
     * about tallying *a page* and calling it a total; this folds the
     * complete scope and the panel states what the scope was.
     *
     * `Attribute.value` — the virtual field — rather than the two
     * columns, so a composite neighbour reads `example.com|1.2.3.4` the
     * way it does everywhere else in MISP.
     *
     * Soft-deleted rows are excluded. The Occurrences tab reveals a
     * soft-deleted occurrence *of this value* because the reader is
     * asking about that value's history; a withdrawn attribute in the
     * same event is not something this value co-occurs with.
     *
     * @param array $user
     * @param string $value The value to exclude — our own occurrences
     * @param array $scope `events` or `objects`, a list of ids
     * @param array $options As conditionsFor, plus `limit`
     * @return array fetchAttributesSimple rows
     */
    public function neighbourRowsFor(array $user, $value, array $scope,
        array $options = array()
    ) {
        $ids = isset($scope['events'])
            ? $scope['events']
            : (isset($scope['objects']) ? $scope['objects'] : array());
        if (empty($ids)) {
            return array();
        }
        $column = isset($scope['events'])
            ? 'Attribute.event_id'
            : 'Attribute.object_id';
        $params = array(
            'conditions' => array(
                $column => array_values($ids),
                'Attribute.deleted' => 0,
                'NOT' => $this->conditionsFor($value, $options),
            ),
            'fields' => array(
                'Attribute.id',
                'Attribute.event_id',
                'Attribute.object_id',
                'Attribute.object_relation',
                'Attribute.type',
                'Attribute.category',
                'Attribute.value',
                'Attribute.timestamp',
                'Attribute.distribution',
                'Attribute.sharing_group_id',
                /*
                 * MISP's own record of which attributes in an object
                 * are there to link and which are there to describe.
                 * The Dated relations fold reads it to tell a far value
                 * from a bookkeeping column: in `passive-dns` it is 0
                 * on `rrname` and `rdata` and 1 on `rrtype`, `count`,
                 * `origin` and both timestamps.
                 */
                'Attribute.disable_correlation',
            ),
            'contain' => self::CONTEXT_FIELDS,
        );
        /*
         * The tag join is the caller's to ask for. It is a `hasMany`
         * rather than a join, so it costs its own query over every id
         * in the scope, and the sibling scope renders no tag column.
         */
        if (!empty($options['tags'])) {
            $params['contain']['AttributeTag'] = array();
        }
        if (isset($options['limit'])) {
            $params['limit'] = $options['limit'];
        }
        return $this->attributes()->fetchAttributesSimple($user, $params);
    }

    /**
     * The far ends of a set of object references, as this viewer may
     * see them.
     *
     * A reference names an object or an attribute by id and says
     * nothing about who may read it, so the row is worthless until the
     * thing it points at has been through `buildConditions($user)`.
     * That is the whole job here: an end that resolves to nothing
     * contributes no row to the panel, which is §14.6 applied to
     * somebody else's object.
     *
     * **One query for both kinds**, because they are the same table
     * under two conditions. The object branch takes only the
     * identifying attributes — MISP's own `disable_correlation = 0` —
     * since a far object is named by what it links, not by its
     * bookkeeping columns. The attribute branch takes the row whatever
     * its flag says: a reference that points at one attribute is
     * pointing at that attribute, and hiding it because the template
     * marked it non-correlating would drop the reference entirely.
     *
     * @param array $user
     * @param array $objectIds Far objects to identify
     * @param array $attributeIds Far attributes to resolve
     * @param int $limit
     * @return array fetchAttributesSimple rows
     */
    public function referenceFacesFor(array $user, array $objectIds,
        array $attributeIds, $limit = 500
    ) {
        $branches = array();
        if (!empty($objectIds)) {
            $branches[] = array_merge(
                array('Attribute.object_id' => array_values($objectIds)),
                ValueFieldKind::linkingConditions()
            );
        }
        if (!empty($attributeIds)) {
            $branches[] = array(
                'Attribute.id' => array_values($attributeIds),
            );
        }
        if (empty($branches)) {
            return array();
        }
        return $this->attributes()->fetchAttributesSimple($user, array(
            'conditions' => array(
                'Attribute.deleted' => 0,
                'OR' => $branches,
            ),
            'fields' => array(
                'Attribute.id',
                'Attribute.event_id',
                'Attribute.object_id',
                'Attribute.object_relation',
                'Attribute.type',
                'Attribute.value',
                'Attribute.timestamp',
                'Attribute.distribution',
                'Attribute.sharing_group_id',
            ),
            'contain' => self::CONTEXT_FIELDS,
            'limit' => $limit,
        ));
    }

    /**
     * Occurrences of *other* values — a set of them, given by name.
     *
     * The near-match section's second query. Having decided which
     * network blocks contain this address, it needs each block as an
     * attribute the viewer may actually see, so the row can carry an
     * event, a reporter and a distribution rather than a bare string
     * out of a Redis set. A block nobody may see contributes no row,
     * which is §14.6 applied to somebody else's value.
     *
     * One query for all of them, newest first, and `limit` bounds it —
     * a `/8` block can be an attribute in a hundred events and the
     * panel names each block once.
     *
     * @param array $user
     * @param array $values
     * @param array $options `types` narrows, `limit` bounds
     * @return array fetchAttributesSimple rows
     */
    public function occurrencesForAny(array $user, array $values,
        array $options = array()
    ) {
        if (empty($values)) {
            return array();
        }
        $conditions = array(
            'OR' => array(
                'Attribute.value1' => $values,
                'Attribute.value2' => $values,
            ),
            'Attribute.deleted' => 0,
        );
        if (!empty($options['types'])) {
            $conditions['Attribute.type'] = $options['types'];
        }
        return $this->attributes()->fetchAttributesSimple($user, array(
            'conditions' => $conditions,
            'fields' => array(
                'Attribute.id',
                'Attribute.event_id',
                'Attribute.object_id',
                'Attribute.type',
                'Attribute.value',
                'Attribute.timestamp',
                'Attribute.distribution',
                'Attribute.sharing_group_id',
            ),
            /*
             * Named fields rather than a bare `contain`. With an
             * explicit `fields` list on the attribute, Containable
             * selects nothing of its own from a `belongsTo` unless it
             * is told what to take — so a bare contain joins the two
             * tables, satisfies `buildConditions`, and hands back rows
             * with no `Event.distribution` on them at all. The ACL is
             * right and every reader of the row is wrong.
             */
            'contain' => self::CONTEXT_FIELDS,
            'order' => array('Attribute.timestamp DESC'),
            'limit' => isset($options['limit'])
                ? $options['limit']
                : 200,
        ));
    }

    /**
     * The distinct values of a type, except this value's own, for an
     * engine that wants to compare rather than to display.
     *
     * The ssdeep engine's candidate set, and it is **not** MISP's.
     * `Correlation::ssdeepCorrelation` narrows through
     * `fuzzy_correlate_ssdeep`, a chunk index that
     * `query_ssdeep_chunks` populates as a side effect of being
     * queried — so it only ever holds attributes saved *since* the
     * extension started working. Measured here: **952 chunk rows
     * covering 13 of 1,399 `ssdeep` attributes**, the 13 being the
     * seeded ones. Narrowing through it would miss the other 1,386 and
     * report *no match* where it means *no index*. Comparing against
     * the type directly is what `ssdeep_fuzzy_compare` is for and
     * cannot inherit an index nobody backfilled.
     *
     * **Values, not occurrences, and that is what makes the whole
     * population affordable.** The engine used to take this set as
     * fully-decorated rows and compare against the hundred most recent
     * of them; a comparison needs the string and nothing else.
     * Dropping eight fields to two roughly halves the fetch — 34.6 ms
     * to 15.8 ms over this instance's 1,399 `ssdeep` attributes — and
     * de-duplicating here means a hash held in thirty events is
     * compared once instead of thirty times. The survivors are
     * re-fetched with their context by `occurrencesForAny`, and there
     * are never many: §14 measured 1,612 pairs over the threshold
     * across the entire instance, and 45 partners on its busiest
     * value.
     *
     * `contain` is deliberately not passed, so `fetchAttributesSimple`
     * keeps its own default `['Event', 'Object']` join — the ACL's
     * conditions are written against `Event`, and dropping that join
     * to save a few milliseconds would drop the predicate with it.
     * These rows must not be read for anything but the value, which is
     * the trap `occurrencesForAny` documents above.
     *
     * @param array $user
     * @param string $type
     * @param string $value The value to exclude
     * @param int $limit Rows fetched, before de-duplication
     * @return array {values: string[], fetched: int, saturated: bool}
     */
    public function valuesOfType(array $user, $type, $value, $limit)
    {
        $rows = $this->attributes()->fetchAttributesSimple($user, array(
            'conditions' => array(
                'Attribute.type' => $type,
                'Attribute.deleted' => 0,
                'NOT' => $this->conditionsFor($value),
            ),
            'fields' => array('Attribute.id', 'Attribute.value'),
            /*
             * Unordered on purpose. The old set was `timestamp DESC`
             * because it was about to be truncated to a hundred rows;
             * ordering a set you intend to keep whole is a sort nobody
             * reads, and on this table it is a filesort.
             */
            'order' => false,
            'limit' => (int)$limit,
        ));
        $values = array();
        foreach ($rows as $row) {
            $values[$row['Attribute']['value']] = true;
        }
        return array(
            'values' => array_keys($values),
            'fetched' => count($rows),
            'saturated' => count($rows) >= (int)$limit,
        );
    }
}
