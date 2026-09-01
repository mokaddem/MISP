<?php
App::uses('AppModel', 'Model');

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
                'Attribute.type',
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
            $branches[] = array(
                'Attribute.object_id' => array_values($objectIds),
                'Attribute.disable_correlation' => 0,
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
     * Every occurrence of a given type except this value's own.
     *
     * The ssdeep engine's candidate set. MISP's own path narrows it
     * first through `fuzzy_correlate_ssdeep`, an index built at save
     * time — which holds **zero rows** on the verification instance
     * against 1,387 `ssdeep` attributes, so narrowing through it would
     * return nothing and report it as *no match* rather than as *no
     * index*. Comparing against the type directly is what
     * `ssdeep_fuzzy_compare` is for, is bounded by `limit`, and cannot
     * silently inherit an empty index.
     *
     * @param array $user
     * @param string $type
     * @param string $value The value to exclude
     * @param int $limit
     * @return array fetchAttributesSimple rows
     */
    public function occurrencesOfType(array $user, $type, $value, $limit)
    {
        return $this->attributes()->fetchAttributesSimple($user, array(
            'conditions' => array(
                'Attribute.type' => $type,
                'Attribute.deleted' => 0,
                'NOT' => $this->conditionsFor($value),
            ),
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
            'limit' => (int)$limit,
        ));
    }
}
