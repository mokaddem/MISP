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
     * How many values one prevalence lookup asks about per round trip.
     *
     * The candidate set is a whole co-occurrence fold — 10,040 values
     * on `8.8.8.8` — and each one is now its own bounded probe, so this
     * is how many of those probes share a statement rather than how
     * long an `IN` list may get. Measured flat from 200 to 400.
     */
    const PREVALENCE_CHUNK = 250;

    /**
     * How many occurrences a single value's probe may read.
     *
     * **The number that stops this lookup scaling with the instance.**
     * Prevalence used to be one `IN` pass per column that grouped every
     * matching row, so its cost tracked the rows the candidates matched
     * rather than the candidates themselves. `8.8.8.8`'s 34 sibling
     * values matched **277,987** rows — they are object-template enum
     * values like `flood` (65,717), `not-applicable` (32,945) and
     * `client-to-server` (32,869) — which grouped to 239,076 triples,
     * every one of them hydrated into PHP to be counted. That was
     * **3,631 ms** for 34 numbers, and linear: 7.3 µs per matched row,
     * measured from 55 rows to 267,474.
     *
     * A value that has already been seen this many times is not going
     * to be told apart from one seen twice as often by a denominator,
     * so the probe stops here and the caller is told it stopped. The
     * bound has to be on **rows read**, not on grouped output: a
     * `GROUP BY … LIMIT` has to group before it can limit, so it still
     * reads every matching row — measured at ~60 ms for one hot value
     * whether the limit was 50 or 1,000, against 23 ms for the same
     * value once the grouping came out.
     */
    const PREVALENCE_ROW_CAP = 500;

    /**
     * How many values get a prevalence probe at all.
     *
     * Each probe is bounded, so this bounds the lookup: at ~0.3 ms for
     * a value nothing much holds, 1,500 of them is ~420 ms whatever the
     * instance holds. Above it the tail goes unprobed and those rows
     * carry no spread, which is a state the fold and both tables
     * already render — `ValueRelationTool::emptyGroup` has why an
     * unknown spread is not a spread of zero.
     *
     * The tail is the right thing to drop because the caller hands the
     * values over in local-frequency order, so what goes unprobed is
     * what the fewest of the panel's own rows rest on. A value with
     * more than 1,500 distinct neighbours is not a value anyone reads
     * to a conclusion; it is `8.8.8.8`, and it still answers.
     */
    const PREVALENCE_PROBE_CAP = 1500;

    /**
     * MISP's attribute type for a date an object template records in a
     * field of its own — `time_first`, `first-seen`, `send-date`.
     *
     * The same string `ValueRelationTool::DATE_TYPE` folds the
     * Relationships tab's dated relations on; stated again here rather
     * than reached across because this file is the seam and a tool is
     * not, and a value table landing later has to answer *which rows
     * are dates* from whichever table holds them.
     */
    const DATE_TYPE = 'datetime';

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
     * MISP's proposal model. Its `$alias` is `ShadowAttribute`, which
     * is the alias `conditionsFor` is asked for by name.
     *
     * @var ShadowAttribute
     */
    private $proposalModel = null;

    /**
     * @return ShadowAttribute
     */
    private function proposals()
    {
        if ($this->proposalModel === null) {
            $this->proposalModel = ClassRegistry::init('ShadowAttribute');
        }
        return $this->proposalModel;
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
     * **`$options['alias']` is the same bargain, for a second table.**
     * `shadow_attributes` carries its own `value1`/`value2` pair with
     * the same semantics, so the Timeline's proposals lane needs this
     * predicate spelled `ShadowAttribute.value1` — and §14.3's rule is
     * that no file but this one may spell it at all. Defaulting to
     * `Attribute` keeps every existing caller byte-identical; none of
     * the fourteen passes the key.
     *
     * The parameter was named by `value-profile-coverage.md` §2.4 as
     * the one item in that survey with a cost per live phase deferred,
     * because a value table landing later has to answer the proposals
     * question too and `shadow_attributes.value1` is a column that
     * migration must either move or leave behind.
     *
     * @param string $value
     * @param array $options `types` narrows to a set of MISP types;
     *                       `alias` names the model the columns are on
     * @return array
     */
    public function conditionsFor($value, array $options = array())
    {
        $alias = empty($options['alias']) ? 'Attribute' : $options['alias'];
        $conditions = array(
            'OR' => array(
                $alias . '.value1' => $value,
                $alias . '.value2' => $value,
            ),
        );
        if (!empty($options['types'])) {
            return array(
                $conditions,
                $alias . '.type' => $options['types'],
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
     * How many objects this value sits in, for this viewer.
     *
     * The uncapped count of exactly what `occurrenceObjectIdsFor`
     * returns — the same three conditions, in the same order — so the
     * Relationships tab badge and the sibling panel's own census cannot
     * state different numbers for one value. That is the property
     * `ValueProfile::forTabCounts` exists to protect, and here it is
     * held by the conditions rather than by one shared call: the census
     * gets its total free from the rows it already fetched whenever the
     * value sits under `SIBLING_OBJECT_CAP` objects, and paying for
     * this aggregate there would be a query for a number already in
     * hand.
     *
     * One indexed aggregate over the `value1` prefix index: 0.3 ms on
     * `8.8.8.8` (15 objects) and 84 ms on `0.0.0.0`, the instance's
     * 32,922-object outlier. `ValueProfile::forTabCounts` has why the
     * badge is worth that where the co-occurrence total is not.
     *
     * @param array $user
     * @param string $value
     * @param array $options As conditionsFor
     * @return int
     */
    public function objectCountFor(array $user, $value,
        array $options = array()
    ) {
        $attributes = $this->attributes();
        $conditions = $attributes->buildConditions($user);
        $conditions['AND'][] = $this->conditionsFor($value, $options);
        $conditions['AND'][] = array('Attribute.object_id >' => 0);
        $row = $attributes->find('first', array(
            'fields' => array(
                'COUNT(DISTINCT Attribute.object_id) AS objects',
            ),
            'conditions' => $conditions,
            'recursive' => -1,
            // As occurrenceCountFor: buildConditions() names Event.* and
            // Object.* directly, so both have to be joined.
            'contain' => array('Event', 'Object'),
        ));
        return empty($row[0]['objects']) ? 0 : (int)$row[0]['objects'];
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
     * Everything the assessment's aggregate class needs about the
     * record, in one query.
     *
     * `occurrenceSummaryFor`'s five numbers plus the three the quality
     * ledger reads — how much of the reporting is published, how many
     * occurrences date their own observation, and the worst
     * encoding-against-event lag — because they are all single-row
     * aggregates over the same join and a second query for them would
     * buy nothing. The summary keeps its own contract; five panels
     * already read it and none of them wants these three.
     *
     * **Soft-deleted occurrences are excluded, and that is a
     * judgement rather than tidying.** A withdrawn attribute is a
     * claim its organisation took back; scoring it would let a
     * retracted report keep arguing. `occurrenceSummaryFor` counts
     * them because the Occurrences tab *shows* them — a row a reader
     * can see has to be in the total above it (§14.6) — and an
     * assessment has the opposite obligation.
     *
     * The lag is `Event.date` against the attribute's own timestamp:
     * the encoding date against the date the event says the thing
     * happened. `06-staleness.md` §3.6 makes it one of the two inputs
     * that put the relevance axis into *timeline uncertain*, and
     * `record.temporal_precision` reads the same fact as a quality
     * signal.
     *
     * @param array $user
     * @param string $value
     * @param array $options As conditionsFor
     * @return array
     */
    public function recordSummaryFor(array $user, $value,
        array $options = array()
    ) {
        $attributes = $this->attributes();
        $conditions = $attributes->buildConditions($user);
        $conditions['AND'][] = $this->conditionsFor($value, $options);
        $conditions['AND'][] = array('Attribute.deleted' => 0);
        $row = $attributes->find('first', array(
            'fields' => array(
                'COUNT(DISTINCT Attribute.id) AS occurrences',
                'COUNT(DISTINCT Event.id) AS events',
                'COUNT(DISTINCT Event.orgc_id) AS orgs',
                'MIN(Attribute.timestamp) AS oldest',
                'MAX(Attribute.timestamp) AS newest',
                'COUNT(DISTINCT CASE WHEN Event.published = 1'
                    . ' THEN Event.id END) AS published',
                'SUM(CASE WHEN Attribute.first_seen IS NOT NULL'
                    . ' THEN 1 ELSE 0 END) AS dated',
                'MAX(TIMESTAMPDIFF(DAY, Event.date,'
                    . ' FROM_UNIXTIME(Attribute.timestamp)))'
                    . ' AS max_lag_days',
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
            'published' => (int)($found['published'] ?? 0),
            'dated' => (int)($found['dated'] ?? 0),
            'oldest' => empty($found['oldest'])
                ? null
                : (int)$found['oldest'],
            'newest' => empty($found['newest'])
                ? null
                : (int)$found['newest'],
            /*
             * Null rather than zero when there is nothing to measure:
             * a value with no occurrence has no lag, and a lag of zero
             * is a real and different answer.
             */
            'max_lag_days' => isset($found['max_lag_days'])
                    && $found['max_lag_days'] !== null
                ? (int)$found['max_lag_days']
                : null,
        );
    }

    /**
     * Each organisation's stake in this value: how many occurrences it
     * holds, and how its occurrences set `to_ids`.
     *
     * Two readers, one query. `reporting.independent_orgs` counts the
     * rows; the lean derivation (`04-dispositions.md` §3) reads the
     * stances, and counts them **per organisation rather than per
     * occurrence** — one org putting forty `to_ids = 1` events on a
     * value is one voice, which is the same independence argument the
     * reporting signal makes.
     *
     * Grouped in SQL rather than folded in PHP for the reason
     * `ownTagsFor` gives: a value can occur 48,255 times and the answer
     * is a handful of rows either way.
     *
     * Organisation *names* are not here. This class owns the value's
     * identity and its occurrence set; who an `orgc_id` belongs to is
     * the Organisation model's, and the caller resolves it in one
     * lookup.
     *
     * @param array $user
     * @param string $value
     * @param array $options As conditionsFor
     * @return array Rows of `Event.orgc_id` and, under `0`,
     *               `occurrences`, `to_ids_yes`, `to_ids_no`, `newest`
     */
    public function orgStanceFor(array $user, $value,
        array $options = array()
    ) {
        $attributes = $this->attributes();
        $conditions = $attributes->buildConditions($user);
        $conditions['AND'][] = $this->conditionsFor($value, $options);
        $conditions['AND'][] = array('Attribute.deleted' => 0);
        return $attributes->find('all', array(
            'fields' => array(
                'Event.orgc_id',
                'COUNT(DISTINCT Attribute.id) AS occurrences',
                'SUM(CASE WHEN Attribute.to_ids = 1 THEN 1 ELSE 0 END)'
                    . ' AS to_ids_yes',
                'SUM(CASE WHEN Attribute.to_ids = 0 THEN 1 ELSE 0 END)'
                    . ' AS to_ids_no',
                'MAX(Attribute.timestamp) AS newest',
            ),
            'conditions' => $conditions,
            'recursive' => -1,
            'contain' => array('Event', 'Object'),
            'group' => array('Event.orgc_id'),
            'order' => array('occurrences DESC'),
        ));
    }

    /**
     * Which months this value was reported in, and how often.
     *
     * `lifecycle.continuity` asks whether the reporting is continuous
     * or one burst, and the answer is the longest unbroken run of
     * months — which needs the months, not the span.
     *
     * **An index aggregate, so it is never windowed** (`03-signals.md`
     * §2.3): the row count is the value's age in months, whatever its
     * occurrence count, and bounding it to 90 days would cap every
     * long-lived value's continuity at three months — turning the
     * signal's own subject into an artefact of the budget.
     *
     * @param array $user
     * @param string $value
     * @param array $options As conditionsFor
     * @return array `YYYY-MM` => occurrences, oldest month first
     */
    public function activityMonthsFor(array $user, $value,
        array $options = array()
    ) {
        $attributes = $this->attributes();
        $conditions = $attributes->buildConditions($user);
        $conditions['AND'][] = $this->conditionsFor($value, $options);
        $conditions['AND'][] = array('Attribute.deleted' => 0);
        $rows = $attributes->find('all', array(
            'fields' => array(
                "DATE_FORMAT(FROM_UNIXTIME(Attribute.timestamp),"
                    . " '%Y-%m') AS month",
                'COUNT(DISTINCT Attribute.id) AS occurrences',
            ),
            'conditions' => $conditions,
            'recursive' => -1,
            'contain' => array('Event', 'Object'),
            'group' => array('month'),
            'order' => array('month ASC'),
        ));
        $months = array();
        foreach ($rows as $row) {
            if (empty($row[0]['month'])) {
                continue;
            }
            $months[$row[0]['month']] = (int)$row[0]['occurrences'];
        }
        return $months;
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
     * **One bounded probe per value, which is what stops this scaling
     * with the instance.** Identity and storage disagree on composite
     * attributes: a value is an identity when it is `value1` **or**
     * `value2` (`conditionsFor`, and the reason is in this class's own
     * docblock), while the co-occurrence fold keys its rows on
     * `Attribute.value`, which composes to `value1|value2`. 16% of this
     * instance's attributes are composite and 2,231 of `8.8.8.8`'s
     * 10,040 neighbour keys carry a pipe, so neither reading can be
     * skipped. A key's count is the union of the two: the composed
     * value equals `A|B` exactly when `value1` is `A` and `value2` is
     * `B`, or when `value1` is the literal `A|B` and `value2` is empty.
     *
     * This used to be two `IN` passes that grouped every matching row
     * and rebuilt the union in PHP, and its cost tracked the rows the
     * candidates matched rather than the candidates themselves —
     * `PREVALENCE_ROW_CAP` carries the measurements. Each key now asks
     * its own question, capped, and MariaDB answers it off the two
     * value indexes: `EXPLAIN` reports
     * `index_merge … sort_union(value1,value2)` for the plain shape.
     *
     * **The arms are separate statements, not one condition.** A
     * thousand `(value1 = A AND value2 = B)` arms OR'd into a single
     * `WHERE` took **311 seconds** over this fold and matched far more
     * than it was asked about. Chunking them as `UNION ALL` keeps every
     * arm its own indexed lookup with its own `LIMIT`, which is the
     * only way the cap can bind per value.
     *
     * **Equality, so an absent value costs nothing.** The cap would be
     * no protection if a miss had to scan: 200 values that match
     * nothing cost 98 ms, against the 4.5 s a `LIKE '%…'` shape spends
     * discovering the same thing — the trap recorded at
     * `nearMatches`. Every arm here is an equality on an indexed
     * column, so a value nobody holds is a range lookup that finds
     * nothing.
     *
     * @param array $user
     * @param array $values Value strings, as the caller keys them, most
     *                      locally frequent first — the tail past
     *                      `PREVALENCE_PROBE_CAP` goes unprobed
     * @param array $options `unit` counts `event` (default) or
     *                       `object`; otherwise as conditionsFor
     * @return array `counts` (key => exact count, absent when zero),
     *               `capped` (key => true, too common to count),
     *               `row_cap`, `probed`, `unprobed`
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
        /*
         * **Back to strings, and this is not defensive tidying.** PHP
         * turns an array key that looks like an integer into one, so
         * `array_keys` hands back `443` and `1204` — real neighbours,
         * a port and a passive-DNS record count — as ints. CakePHP
         * binds them as integers, MariaDB then compares a varchar
         * column against a number, converts the whole column to do it
         * and abandons the `value1` index.
         */
        $keys = array_map('strval', array_keys($wanted));
        $out = array(
            'counts' => array(),
            'capped' => array(),
            'row_cap' => self::PREVALENCE_ROW_CAP,
            'probed' => 0,
            'unprobed' => 0,
        );
        if (empty($keys)) {
            return $out;
        }
        if (count($keys) > self::PREVALENCE_PROBE_CAP) {
            $out['unprobed'] = count($keys) - self::PREVALENCE_PROBE_CAP;
            $keys = array_slice($keys, 0, self::PREVALENCE_PROBE_CAP);
        }
        $out['probed'] = count($keys);
        $probed = $this->prevalenceProbe($user, $keys, $options);
        $out['counts'] = $probed['counts'];
        $out['capped'] = $probed['capped'];
        return $out;
    }

    /**
     * The probes themselves, chunked into `UNION ALL` statements.
     *
     * `Event` and `Object` are joined because
     * `MispAttribute::buildConditions` names their columns directly,
     * and only the id being counted is selected: the ACL filters on
     * columns it does not have to read back.
     *
     * Each arm reads at most `PREVALENCE_ROW_CAP + 1` occurrences and
     * reports two numbers about them — how many it read, and how many
     * distinct events or objects they were. The first says whether the
     * second can be trusted: an arm that came back short of its limit
     * saw every occurrence there is, so its distinct count is exact;
     * one that filled its limit saw a prefix, and the honest answer
     * about such a value is that it is too common to count rather than
     * whatever the prefix happened to hold. `flood` reads 501 rows
     * spread over one event — the distinct count off a truncated read
     * is not a small number, it is a meaningless one.
     *
     * @param array $user
     * @param array $keys Value strings, already capped and stringified
     * @param array $options As prevalenceFor
     * @return array `counts` and `capped`
     */
    private function prevalenceProbe(array $user, array $keys,
        array $options
    ) {
        $attributes = $this->attributes();
        $db = $attributes->getDataSource();
        $acl = $attributes->buildConditions($user);
        $object = isset($options['unit'])
            && $options['unit'] === 'object';
        $idKey = $object ? 'object_id' : 'event_id';
        $joins = array(
            array(
                'table' => $db->fullTableName(ClassRegistry::init('Event')),
                'alias' => 'Event',
                'type' => 'LEFT',
                'conditions' => array('Event.id = Attribute.event_id'),
            ),
            array(
                'table' => $db->fullTableName(
                    ClassRegistry::init('MispObject')
                ),
                'alias' => 'Object',
                'type' => 'LEFT',
                'conditions' => array('Object.id = Attribute.object_id'),
            ),
        );

        $counts = array();
        $capped = array();
        foreach (array_chunk($keys, self::PREVALENCE_CHUNK) as $chunk) {
            $arms = array();
            foreach ($chunk as $key) {
                $conditions = $acl;
                $conditions['AND'][] = $this->prevalencePredicate($key);
                if (!empty($options['types'])) {
                    $conditions['AND'][] = array(
                        'Attribute.type' => $options['types'],
                    );
                }
                if ($object) {
                    $conditions['AND'][] = array(
                        'Attribute.object_id !=' => 0,
                    );
                }
                /*
                 * `order` empty on purpose: `MispAttribute` carries a
                 * default `Attribute.event_id DESC` that a `find()`
                 * would append here, and an ordering nobody reads would
                 * turn a bounded index lookup into a sort of everything
                 * the arm matched.
                 */
                $inner = $db->buildStatement(array(
                    'fields' => array(
                        'Attribute.' . $idKey . ' AS probe_id',
                    ),
                    'table' => $db->fullTableName($attributes),
                    'alias' => 'Attribute',
                    'joins' => $joins,
                    'conditions' => $conditions,
                    'order' => null,
                    'group' => null,
                    'having' => null,
                    'limit' => self::PREVALENCE_ROW_CAP + 1,
                    'offset' => null,
                ), $attributes);
                $arms[] = 'SELECT ' . $db->value($key, 'string')
                    . ' AS probe_key, COUNT(*) AS probe_rows,'
                    . ' COUNT(DISTINCT probe_id) AS probe_ids'
                    . ' FROM (' . $inner . ') AS probe';
            }
            $rows = $db->fetchAll(implode(' UNION ALL ', $arms));
            if (empty($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                /*
                 * Every column here is computed, so CakePHP files them
                 * under the no-table key rather than under an alias.
                 */
                $cells = isset($row[0]) ? $row[0] : reset($row);
                if (!is_array($cells)
                    || !array_key_exists('probe_key', $cells)
                ) {
                    continue;
                }
                $key = (string)$cells['probe_key'];
                if ((int)$cells['probe_rows'] > self::PREVALENCE_ROW_CAP) {
                    $capped[$key] = true;
                    continue;
                }
                $ids = (int)$cells['probe_ids'];
                if ($ids > 0) {
                    $counts[$key] = $ids;
                }
            }
        }
        return array('counts' => $counts, 'capped' => $capped);
    }

    /**
     * Every way one fold key can be stored, as one indexed predicate.
     *
     * A plain key is an identity on either column. A composed key is
     * additionally the pair that spells it — and still the literal, for
     * the attribute whose `value1` carries a pipe of its own with an
     * empty `value2`. All arms are equalities, so the whole predicate
     * stays a set of range lookups over `value1` and `value2`.
     *
     * @param string $key A key as the co-occurrence fold spells it
     * @return array
     */
    private function prevalencePredicate($key)
    {
        $predicate = array(
            'OR' => array(
                array('Attribute.value1' => $key),
                array('Attribute.value2' => $key),
            ),
        );
        $cut = strpos($key, '|');
        if ($cut !== false && $cut > 0) {
            $predicate['OR'][] = array(
                'Attribute.value1' => substr($key, 0, $cut),
                'Attribute.value2' => substr($key, $cut + 1),
            );
        }
        return $predicate;
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
                // Both ends of the span, because the Timeline's seen
                // lane is the one consumer that needs the low end and
                // it needs it for the same rows this already reads.
                'Attribute.first_seen',
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
     *               last_seen, deleted, and the containing object's
     *               id and span
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
                // Both ends of the span, because the Timeline's seen
                // lane is the one consumer that needs the low end and
                // it needs it for the same rows this already reads.
                'Attribute.first_seen',
                'Attribute.last_seen',
                'Attribute.deleted',
                // Which object the row sits in, so the seen lane can
                // tell an occurrence that has no span of its own from
                // one whose object has none either.
                'Attribute.object_id',
            ),
            /*
             * buildConditions() names Event.* and Object.* directly, so
             * both have to be joined for the ACL to be expressible —
             * and the join was all this asked for until the seen lane
             * needed the object's own span.
             *
             * **Named fields, for the reason `occurrencesFor` records
             * one screen down**: with an explicit `fields` list on the
             * attribute, Containable selects nothing of its own from a
             * `belongsTo` unless it is told what to take. A bare
             * `contain` joins both tables, satisfies the ACL, and hands
             * back rows carrying no `Object` columns at all — which is
             * why the object's `first_seen` was unreachable here for
             * four phases while the table was already in the query.
             */
            'contain' => array(
                'Event' => array('fields' => array('Event.id')),
                'Object' => array('fields' => array(
                    'Object.id',
                    'Object.first_seen',
                    'Object.last_seen',
                )),
            ),
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
     * The shape both id-set accessors return, keyed by
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
                'first_seen' => $row['Attribute']['first_seen'] ?? null,
                'last_seen' => $row['Attribute']['last_seen'] ?? null,
                'deleted' => !empty($row['Attribute']['deleted']),
                /*
                 * The object's id and its own span, null on a loose
                 * attribute and on every caller that did not ask the
                 * `Object` association for its columns. Both accessors
                 * share this shape, and only one of them names those
                 * fields — so absent has to mean *not asked for* rather
                 * than *not set*, which is why every read of these is
                 * guarded rather than assumed.
                 */
                'object_id' => isset($row['Attribute']['object_id'])
                    ? (int)$row['Attribute']['object_id']
                    : 0,
                'object_first_seen' => $row['Object']['first_seen'] ?? null,
                'object_last_seen' => $row['Object']['last_seen'] ?? null,
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
     * be org-only — and applying it is what obliges the `Object` join
     * below, since those conditions name `Object.distribution` directly
     * for anybody who is not a site admin.
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
            // Both, as everywhere else here: the ACL is not expressible
            // without them. `Event` alone threw for every reader who was
            // not a site admin, and took the whole panel down with it.
            'contain' => array('Event', 'Object'),
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
     * @return array object id => ['last' =>, 'relation' =>, 'name' =>]
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
                /*
                 * The template's name, so a mark drawn from an object
                 * can say which kind of object it came from — `MIN()`
                 * for the same `ONLY_FULL_GROUP_BY` reason as the
                 * relation above, and free here: `Object` is already
                 * joined for the ACL.
                 */
                'MIN(Object.name) AS object_name',
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
                'name' => $row[0]['object_name'] === null
                    ? ''
                    : $row[0]['object_name'],
            );
        }
        return $objects;
    }

    /**
     * The `datetime` attributes of the objects this value sits in —
     * the dates an object template records in its own fields, as
     * opposed to the `first_seen`/`last_seen` columns MISP keeps beside
     * every attribute and object.
     *
     * **A third place a date about this value can live**, and the one
     * the page reached only sideways: `ValueRelationTool::dated` folds
     * these into the Relationships tab's *Dated relations*, but it
     * needs two of them plus a linking value in the same object,
     * because what it dates is a *relation between two values*. What
     * this returns dates the object itself, so one row is enough and no
     * far value is required.
     *
     * The vocabulary is the template's, not MISP's, and it is not all
     * one notion: on the verification instance the `datetime` rows are
     * `time_generated` (32,892), `first-seen` (11,318), `last-seen`
     * (11,193), `last-submission` (6,744), `time_first`/`time_last`
     * (665 each) and a long tail down through `compilation-timestamp`,
     * `creation-date` and `send-date`. So a caller may not merge these
     * into a *seen* claim — the relation has to travel with the row and
     * be shown, which is why `object_relation` is in the field list.
     *
     * ACL'd through `fetchAttributesSimple` and not by the object's
     * permission: a sibling attribute carries its own distribution, so
     * an object a reader may open can still hold a row they may not.
     *
     * **Newest first, and the ordering is this file's rather than the
     * caller's.** A caller asking for `Attribute.value1 DESC` would be
     * spelling the column name outside this seam, which §14.3 forbids —
     * and the sort is not incidental: the date lives in `value1` as a
     * string, and ISO 8601 sorts lexically the way it sorts
     * chronologically, which is the property that lets a `varchar` date
     * column be ordered at all. A value table landing later has to keep
     * that property or give this method a real column.
     *
     * @param array $user
     * @param array $objectIds The objects this value sits in
     * @param array $options `limit` reaches the fetcher
     * @return array fetchAttributesSimple rows
     */
    public function objectDatesFor(array $user, array $objectIds,
        array $options = array()
    ) {
        if (empty($objectIds)) {
            return array();
        }
        $params = array(
            'conditions' => array(
                'Attribute.object_id' => array_values($objectIds),
                'Attribute.type' => self::DATE_TYPE,
                'Attribute.deleted' => 0,
            ),
            'fields' => array(
                'Attribute.id',
                'Attribute.event_id',
                'Attribute.object_id',
                'Attribute.object_relation',
                'Attribute.value1',
                'Attribute.timestamp',
            ),
            'contain' => self::CONTEXT_FIELDS,
            'order' => array('Attribute.value1' => 'DESC'),
        );
        if (isset($options['limit'])) {
            $params['limit'] = $options['limit'];
        }
        $out = array();
        foreach ($this->attributes()->fetchAttributesSimple($user, $params)
            as $row
        ) {
            $out[] = array(
                'id' => (int)$row['Attribute']['id'],
                'event_id' => (int)$row['Attribute']['event_id'],
                'object_id' => (int)$row['Attribute']['object_id'],
                'relation' => $row['Attribute']['object_relation'] === null
                    ? ''
                    : $row['Attribute']['object_relation'],
                'at' => $row['Attribute']['value1'],
                'object' => isset($row['Object']['name'])
                    ? $row['Object']['name']
                    : '',
            );
        }
        return $out;
    }

    /**
     * The same rows as a per-day tally, so the lane's spine can be
     * binned over all of them while the chronology carries only the
     * newest cap-many.
     *
     * §16.1's rule, and this is the reader that needs it most: a value
     * in 32,922 objects reaches **32,893** `datetime` rows on the
     * verification instance, so a lane tallying what it drew would draw
     * eleven years of dates as whichever fortnight the cap left it.
     *
     * The day comes from the left ten characters of the stored value
     * rather than from a date function, because the column is a string:
     * MISP validates a `datetime` attribute to ISO 8601, so the first
     * ten characters are its calendar day in every row and the grouping
     * needs no parse. A row whose value is not a date groups under
     * whatever it starts with and is dropped by the caller's own parse,
     * which is the same rule the rows take.
     *
     * **Grouped by relation as well as by day**, and the second half is
     * what lets the lane name its own vocabulary honestly. The rows it
     * draws are the newest cap-many, so tallying relations from those
     * would report `0.0.0.0`'s eleven kinds of date as whichever one
     * happens to be newest — and it is not a close call there:
     * `time_generated` is 32,892 of its 32,893 rows.
     *
     * @param array $user
     * @param array $objectIds The objects this value sits in
     * @return array ['by_day' => day => n, 'by_relation' => rel => n,
     *               'total' =>, 'first' =>, 'last' =>]
     */
    public function objectDateCountsFor(array $user, array $objectIds)
    {
        $empty = array(
            'by_day' => array(),
            'by_relation' => array(),
            'total' => 0,
            'first' => null,
            'last' => null,
        );
        if (empty($objectIds)) {
            return $empty;
        }
        $attributes = $this->attributes();
        $conditions = $attributes->buildConditions($user);
        $conditions['AND'][] = array(
            'Attribute.object_id' => array_values($objectIds),
            'Attribute.type' => self::DATE_TYPE,
            'Attribute.deleted' => 0,
        );
        $rows = $attributes->find('all', array(
            'conditions' => $conditions,
            'fields' => array(
                'LEFT(Attribute.value1, 10) AS day',
                'Attribute.object_relation',
                'COUNT(*) AS n',
            ),
            'group' => array('day', 'Attribute.object_relation'),
            'order' => array('day' => 'ASC'),
            'recursive' => -1,
            // Named for the ACL, exactly as the aggregate one screen up
            // does: `buildConditions` spells both tables.
            'contain' => array('Event', 'Object'),
        ));
        $out = $empty;
        foreach ($rows as $row) {
            $day = $row[0]['day'];
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$day)) {
                continue;
            }
            $n = (int)$row[0]['n'];
            $relation = $row['Attribute']['object_relation'] === null
                ? ''
                : $row['Attribute']['object_relation'];
            $out['by_day'][$day] = $n + (isset($out['by_day'][$day])
                ? $out['by_day'][$day]
                : 0);
            $out['by_relation'][$relation] = $n + (
                isset($out['by_relation'][$relation])
                    ? $out['by_relation'][$relation]
                    : 0
            );
            $out['total'] += $n;
            if ($out['first'] === null || $day < $out['first']) {
                $out['first'] = $day;
            }
            if ($out['last'] === null || $day > $out['last']) {
                $out['last'] = $day;
            }
        }
        // Commonest first, because the lane names only its head.
        arsort($out['by_relation']);
        return $out;
    }

    /**
     * The value's occurrences as UUIDs, which is how analyst data
     * addresses them.
     *
     * `Relationship` hangs off an `object_uuid`, never off an id, so
     * the asserted section's lookup is a UUID set and not the integer
     * set every other panel on this page uses. Keeping the two
     * accessors apart rather than adding `uuid` to `occurrenceIdsFor`
     * keeps those panels' cost visible: both callers cap their own set,
     * and every other panel on the page reads the integer accessor and
     * pays nothing for a column it has no use for. The Timeline's
     * analyst lane is the second caller — same shape, same reason, and
     * its cap is `ValueProfile::TIMELINE_ANALYST_CAP`.
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
                /*
                 * The object's name, for the one caller that has to
                 * print what the occurrence sits in rather than only
                 * match against it: the Analyst tab's attachment chip
                 * says *network-connection in #1284*, and a uuid is
                 * not a thing to show a reader. Free — the join is
                 * already here for the uuid beside it.
                 */
                'Object.name',
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
                'object_name' => empty($row['Attribute']['object_id'])
                    || empty($row['Object']['name'])
                        ? null
                        : $row['Object']['name'],
            );
        }
        return $set;
    }

    /**
     * Every proposal that is about this value, from either direction.
     *
     * **Two scopes, and the instance holds exactly one row that proves
     * both are needed.** Proposal 12 proposes `2.2.2.3` against
     * attribute 1495259, which holds `2.2.2.2`:
     *
     * - `2.2.2.2` reaches it only through the *target*, and what it
     *   says there is *someone proposed replacing this*.
     * - `2.2.2.3` reaches it only through the proposal's own columns,
     *   and what it says there is *someone proposed this as a
     *   replacement*.
     *
     * Sixteen of the other seventeen attribute-targeted proposals carry
     * the same value as their target and are reached both ways, which
     * is why the union is deduplicated by id rather than concatenated.
     *
     * **Two statements and not one `OR`.** The two scopes sit on
     * different columns of different tables — `ShadowAttribute.value1`
     * and `Attribute.value1` — and an `OR` spanning them is the shape
     * that cost the co-occurrence panel a full table scan. Each half
     * here matches a prefix index of its own: `shadow_attributes` has
     * `value1(255)` and `value2(255)`, the same shape `attributes`
     * carries.
     *
     * **The target scope is a join and not an id list.** `old_id` is a
     * `belongsTo` to `MispAttribute`, so `ShadowAttribute::buildConditions`
     * has already joined `Attribute` in order to express its own ACL —
     * which means the value predicate can be written against that join
     * for nothing. The alternative was `old_id IN (occurrence ids)`,
     * and on `443` that is an `IN` list of 48,255 integers.
     *
     * **The gate is MISP's own**, `ShadowAttribute::buildConditions`,
     * which is what `ShadowAttributesController::index` uses. It is not
     * the attribute ACL with an extra clause: `value-profile-coverage.md`
     * §2.2 found that a standalone proposal (`old_id = 0`) is OR'd past
     * the whole attribute-and-object distribution test, because there is
     * no attribute to test, and is therefore gated by **event visibility
     * alone**. That is looser than the occurrence fetcher, and it is
     * MISP's rule rather than this page's — so it is applied as MISP
     * wrote it and stated here rather than tightened silently.
     *
     * Soft-deleted proposals are kept. A withdrawn proposal is still a
     * dated thing that happened to this value, and the row says so —
     * unlike the co-occurrence panel, whose subject is what the value
     * currently sits beside.
     *
     * @param array $user
     * @param string $value
     * @param array $options As conditionsFor, minus `alias`
     * @return array id => the proposal row, its event and its org
     */
    public function proposalsFor(array $user, $value,
        array $options = array()
    ) {
        $model = $this->proposals();
        $fields = array(
            'ShadowAttribute.id',
            'ShadowAttribute.old_id',
            'ShadowAttribute.event_id',
            'ShadowAttribute.type',
            'ShadowAttribute.category',
            'ShadowAttribute.value1',
            'ShadowAttribute.value2',
            'ShadowAttribute.comment',
            'ShadowAttribute.deleted',
            'ShadowAttribute.proposal_to_delete',
            'ShadowAttribute.timestamp',
            'Org.id',
            'Org.name',
            'Event.id',
            'Event.info',
            // What the target holds today, so a row that changes the
            // value can name both ends of the change.
            'Attribute.id',
            'Attribute.value1',
            'Attribute.value2',
        );
        // `alias` is passed on a fresh array and never on the caller's,
        // so a page-wide options array cannot pick it up and re-point
        // some other fetcher at a table it does not read.
        $scopes = array(
            'proposed' => $this->conditionsFor($value,
                array('alias' => 'ShadowAttribute')),
            'target' => $this->conditionsFor($value),
        );
        $found = array();
        foreach ($scopes as $reach => $scope) {
            $conditions = $model->buildConditions($user);
            $conditions['AND'][] = $scope;
            $rows = $model->find('all', array(
                'fields' => $fields,
                'conditions' => $conditions,
                'recursive' => -1,
                /*
                 * All three, and each for a different job: `Event` and
                 * `Attribute` are what `buildConditions` names, so the
                 * ACL is not expressible without them; `Org` is the
                 * proposing organisation, which every row on the
                 * Timeline is attributed to.
                 */
                'contain' => array('Event', 'Attribute', 'Org'),
            ));
            foreach ($rows as $row) {
                $id = (int)$row['ShadowAttribute']['id'];
                if (isset($found[$id])) {
                    // Reached both ways. The first reach wins, which is
                    // `proposed`, because a proposal naming this value
                    // is about it more directly than one that merely
                    // targets a row holding it.
                    continue;
                }
                $found[$id] = self::proposalRow($row, $reach);
            }
        }
        return $found;
    }

    /**
     * One proposal row, flattened, with the reach that found it.
     *
     * @param array $row One `ShadowAttribute` find record
     * @param string $reach `proposed` or `target`
     * @return array
     */
    private static function proposalRow(array $row, $reach)
    {
        $proposal = $row['ShadowAttribute'];
        $target = isset($row['Attribute']['id'])
            ? $row['Attribute']
            : null;
        return array(
            'id' => (int)$proposal['id'],
            'old_id' => (int)$proposal['old_id'],
            'event_id' => (int)$proposal['event_id'],
            'type' => $proposal['type'],
            'category' => $proposal['category'],
            'value' => self::composite($proposal),
            'comment' => $proposal['comment'],
            'deleted' => !empty($proposal['deleted']),
            'to_delete' => !empty($proposal['proposal_to_delete']),
            'timestamp' => (int)$proposal['timestamp'],
            'reach' => $reach,
            'org' => isset($row['Org']['name']) && $row['Org']['name'] !== null
                ? $row['Org']['name']
                : __('Unknown organisation'),
            // For callers that link the organisation rather than only
            // naming it; null where the row no longer resolves to one.
            'org_id' => empty($row['Org']['id'])
                ? null
                : (int)$row['Org']['id'],
            'event' => array(
                'id' => (int)$proposal['event_id'],
                'info' => isset($row['Event']['info'])
                    ? $row['Event']['info']
                    : null,
            ),
            /*
             * Null for a standalone proposal, which is the state
             * `old_id = 0` names and the one the whole page is blind to
             * everywhere else (`value-profile-coverage.md` §2.2).
             */
            'target' => $target === null ? null : array(
                'id' => (int)$target['id'],
                'value' => self::composite($target),
            ),
        );
    }

    /**
     * `value1|value2`, the way MISP spells a composite everywhere else.
     *
     * `ShadowAttribute` and `MispAttribute` both define this as a
     * virtual field, and neither is selected here: both fetches list
     * their columns explicitly, and a virtual field is not one.
     *
     * @param array $row A record holding `value1` and `value2`
     * @return string
     */
    private static function composite(array $row)
    {
        $second = isset($row['value2']) ? $row['value2'] : '';
        if ($second === '' || $second === null) {
            return (string)$row['value1'];
        }
        return $row['value1'] . '|' . $second;
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
