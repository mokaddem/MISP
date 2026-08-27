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
}
