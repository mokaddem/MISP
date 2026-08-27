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
