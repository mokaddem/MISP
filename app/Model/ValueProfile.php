<?php
App::uses('AppModel', 'Model');
App::uses('ValueStatsTool', 'Tools');

/**
 * The Value Profile page's per-panel facade.
 *
 * One public method per panel, each returning the array shape that
 * panel's template already reads. `useTable = false`: this model owns no
 * data of its own, it assembles other models' answers into a panel.
 *
 * **Why this is per panel and not per page.**
 * `ValuesController::profileFor()` builds every tab's data and hands the
 * whole array to whichever endpoint asked for it. With a fixture that is
 * one array literal and costs nothing. Live it would be nine tabs of
 * queries per panel request and twenty-odd panel requests per tab visit,
 * so the whole-profile shape does not survive going live — see
 * prd/value-profile-live/00-contract.md §14.1.
 *
 * **Why this is not one big `Value` model.** §4 of the page's PRD
 * refused to build this feature inside `AttributesController` because
 * ~3,800 lines across 40+ actions is not somewhere to add a feature. A
 * single model carrying nine tabs of panel assembly reaches the same
 * size by the same route. `Value` owns the value's identity and its
 * ACL'd occurrence set and is deliberately kept small; this owns
 * assembly and knows what a panel looks like.
 *
 * **What it must not do**, per §14.2: issue its own SQL against
 * attribute value storage. `Value` is the only file in this feature that
 * knows how a value resolves to rows.
 */
class ValueProfile extends AppModel
{
    public $useTable = false;

    /**
     * The most occurrence rows one panel request will load.
     *
     * A cap on the *result size*, not on the query count — the six
     * queries behind the occurrence table are constant in the number of
     * occurrences. On a real instance a value like `443` resolves to
     * tens of thousands of occurrences as the port half of
     * `ip-dst|port`, and fetching those with their events, objects,
     * sharing groups and tags is not a slow page but a page that does
     * not arrive.
     *
     * **300.** Two things bound it, and neither is the query — nothing
     * here scales with occurrence count.
     *
     * The page control renders one button per page inline, so the button
     * count is rows ÷ page size and it shares the panel header with the
     * subtitle. Measured at a 1500px viewport: twelve buttons leave the
     * subtitle readable on two lines, twenty squeeze it to a 16px column,
     * twenty-five push the panel into horizontal overflow. At the default
     * page size of 60 this cap is five pages, which is seven buttons; at
     * the smallest the reader can pick, 25, it is twelve.
     *
     * The other bound is the fragment. A row costs roughly 5.7 KB of
     * markup, so 300 rows is about 1.7 MB — against the 5.9 MB that
     * 1,000 rows of `443` produced when this was first written, which is
     * a fragment that does not arrive. Raising it further is now a
     * question about weight rather than about the pager, which is the
     * more honest place for the limit to sit.
     *
     * When the cap bites, the panel says so — §14.6 keeps cap notices,
     * because a cap is not a permission.
     */
    const OCCURRENCE_CAP = 300;

    /**
     * Most recent first. The table's order was never stated while the
     * rows were fixture data listed in a literal; a value's newest
     * occurrence is the one a reader opening this tab is looking for,
     * and it is also what makes the cap's own wording true.
     */
    const OCCURRENCE_ORDER = 'Attribute.timestamp DESC';

    /**
     * @var array Lazily loaded models, by alias
     */
    private $models = array();

    /**
     * @param string $alias
     * @return Model
     */
    private function model($alias)
    {
        if (!isset($this->models[$alias])) {
            $this->models[$alias] = ClassRegistry::init($alias);
        }
        return $this->models[$alias];
    }

    /**
     * The Occurrences tab: the facet rail and the table it counts.
     *
     * One method for both, because a facet count and the rows it counts
     * have to come out of one assembly or they can disagree with each
     * other — which is the same reason `viewOccurrenceTable` is one
     * endpoint rather than two.
     *
     * Six queries, none of them per-occurrence or per-event:
     *
     *   1. the viewer's occurrence count            `Value`
     *   2. the occurrence rows, capped              `Value`
     *   3. their attribute tags (the hasMany half of 2's contain)
     *   4. the tag records behind those             `MispAttribute`
     *   5. `Event.Orgc` for all N events at once    `Event`
     *   6. pending proposals per row                `ShadowAttribute`
     *
     * @param array $user
     * @param string $value
     * @param array $options Reserved; `types` reaches `Value`
     * @return array
     */
    public function forOccurrenceTable(array $user, $value,
        array $options = array()
    ) {
        $valueModel = $this->model('Value');
        $total = $valueModel->occurrenceCountFor($user, $value, $options);
        $rows = $valueModel->occurrencesFor(
            $user,
            $value,
            array_merge($options, array(
                'limit' => self::OCCURRENCE_CAP,
                'order' => self::OCCURRENCE_ORDER,
            ))
        );

        $this->attachTags($rows);
        $rows = $this->attachCreatorOrgs($user, $rows);
        $this->attachProposalCounts($rows);
        $this->attachEffectiveDistribution($user, $rows);

        $stats = ValueStatsTool::occurrenceStats($rows, $total);

        return array(
            'value' => $value,
            'occurrences' => $rows,
            'occurrence_stats' => $stats,
            /*
             * Null rather than a set of zero groups on a value with no
             * occurrence the viewer may see: a rail of zeroes is a lie
             * about the value (tabs/00-shared.md §5), and the table
             * renders one honest empty state at full width instead.
             */
            'occurrence_facets' => empty($rows)
                ? null
                : ValueStatsTool::occurrenceFacets($rows, $total),
            'occurrence_cap' => $total > $stats['shown']
                ? array('shown' => $stats['shown'], 'total' => $total)
                : null,
        );
    }

    /**
     * The tag records behind each row's attribute tags.
     *
     * `contain => ['AttributeTag']` gives only `tag_id`; this turns
     * those into the records the chips and the rail's facets are drawn
     * from. `includeAllTags` because `Tag.exportable = 0` is a statement
     * about exports, not about visibility — this page is an inspection
     * view, and a tag the reader can see on the event page should not
     * disappear here.
     *
     * @param array $rows
     * @return void
     */
    private function attachTags(array &$rows)
    {
        if (empty($rows)) {
            return;
        }
        $this->model('MispAttribute')->attachTagsToAttributes(
            $rows,
            array('includeAllTags' => true)
        );
    }

    /**
     * `Event.Orgc` for every event the rows sit on, in one call.
     *
     * §14.4's one performance commitment: never one call per event. A
     * value in seven events is one `fetchSimpleEvents`, not seven
     * `fetchEvent`s — the cheap path §14.10 found that §8.2 had costed
     * as a full event graph per event.
     *
     * A nested `contain => ['Event' => ['Orgc']]` would have folded this
     * into the row fetch for nothing, since both are `belongsTo` and
     * become joins. It is a separate call so that the event's ACL is
     * checked by the model that owns events:
     * `Event::createEventConditions` is the same event predicate
     * `MispAttribute::buildConditions` embeds, so it can never be
     * stricter and the drop below can never fire — which is the point of
     * having it, since a divergence would then cost a row rather than
     * leak one.
     *
     * The organisation is rebuilt from `Event.orgc_id` rather than read
     * out of the contained record, because `fetchSimpleEvents` fixes its
     * own `contain` at `Orgc.name` and the facet token is the id.
     *
     * @param array $user
     * @param array $rows
     * @return array Rows whose event this viewer may open
     */
    private function attachCreatorOrgs(array $user, array $rows)
    {
        if (empty($rows)) {
            return $rows;
        }
        $eventIds = array();
        foreach ($rows as $row) {
            $eventIds[$row['Event']['id']] = true;
        }
        $events = $this->model('Event')->fetchSimpleEvents(
            $user,
            array('conditions' => array(
                'Event.id' => array_keys($eventIds),
            )),
            true
        );
        $orgs = array();
        foreach ($events as $event) {
            $orgs[$event['Event']['id']] = array(
                'id' => $event['Event']['orgc_id'],
                'name' => isset($event['Orgc']['name'])
                    ? $event['Orgc']['name']
                    : __('Unknown organisation'),
            );
        }
        $kept = array();
        foreach ($rows as $row) {
            $eventId = $row['Event']['id'];
            if (!isset($orgs[$eventId])) {
                continue;
            }
            $row['Event']['Orgc'] = $orgs[$eventId];
            $kept[] = $row;
        }
        return $kept;
    }

    /**
     * Who can actually see each occurrence.
     *
     * An attribute's own `distribution` column is level 5 — *inherit* —
     * for almost every row on a real instance, so reporting it tells the
     * reader nothing: the level that matters is the conjunction of the
     * attribute's, its object's and its event's, which is the same rule
     * `MispAttribute::buildConditions` enforces to decide whether the row
     * is visible at all. `ValueStatsTool::effectiveDistribution()` owns
     * the resolution; this stamps the answer on the row so the table's
     * badge and the rail's facet cannot resolve it differently.
     *
     * Every level it needs is already on the row — the attribute's, the
     * object's from the `contain`, the event's from the same — so this
     * costs no query. Sharing group *names* do cost one, and only when
     * some row resolves to level 4.
     *
     * `SharingGroup::fetchAllAuthorised($user, 'name')` and not a plain
     * find: it returns only the groups this viewer is authorised for, so
     * a group name cannot be read off a row whose event the reader
     * happens to own. A level-4 row whose group does not resolve keeps
     * its badge and loses only the name.
     *
     * @param array $user
     * @param array $rows
     * @return void
     */
    private function attachEffectiveDistribution(array $user, array &$rows)
    {
        if (empty($rows)) {
            return;
        }
        $names = array();
        foreach ($rows as $row) {
            $levels = array(
                (int)$row['Attribute']['distribution'],
                (int)($row['Event']['distribution'] ?? -1),
                empty($row['Object']['id'])
                    ? -1
                    : (int)$row['Object']['distribution'],
            );
            if (in_array(4, $levels, true)) {
                $names = $this->model('SharingGroup')
                    ->fetchAllAuthorised($user, 'name');
                break;
            }
        }
        foreach ($rows as &$row) {
            $row['effective_distribution'] =
                ValueStatsTool::effectiveDistribution($row, $names);
        }
        unset($row);
    }

    /**
     * How many pending shadow attributes propose a change to each row.
     *
     * A tier-2 aggregate over an already-ACL'd id set, and the written
     * reason §14.4 asks for: the answer is a count per row, and a
     * proposal row carries a value, a comment, a type and a category
     * this panel never renders.
     *
     * The id set comes from the row fetch rather than from a second
     * resolution of the value, so permissions were settled before the
     * aggregate ran and the two cannot drift.
     * `ShadowAttribute::buildConditions()` mirrors the attribute
     * visibility model — a proposal is visible to whoever may see the
     * attribute it proposes against — so it is already satisfied by the
     * set this receives and re-applying it would only re-join `events`.
     *
     * @param array $rows
     * @return void
     */
    private function attachProposalCounts(array &$rows)
    {
        if (empty($rows)) {
            return;
        }
        $ids = array();
        foreach ($rows as $row) {
            $ids[] = $row['Attribute']['id'];
        }
        $counts = $this->model('ShadowAttribute')->find('all', array(
            'fields' => array(
                'ShadowAttribute.old_id',
                'COUNT(*) AS proposal_count',
            ),
            'conditions' => array(
                'ShadowAttribute.old_id' => $ids,
                // A soft-deleted proposal is a withdrawn one, and the
                // badge means "somebody is waiting on you".
                'ShadowAttribute.deleted' => 0,
            ),
            'group' => array('ShadowAttribute.old_id'),
            'recursive' => -1,
        ));
        $byAttribute = array();
        foreach ($counts as $count) {
            $byAttribute[$count['ShadowAttribute']['old_id']] =
                (int)$count[0]['proposal_count'];
        }
        foreach ($rows as &$row) {
            $row['proposal_count'] = isset(
                $byAttribute[$row['Attribute']['id']]
            )
                ? $byAttribute[$row['Attribute']['id']]
                : 0;
        }
        unset($row);
    }
}
