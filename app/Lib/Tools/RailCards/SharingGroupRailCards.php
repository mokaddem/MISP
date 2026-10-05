<?php
App::uses('RailCard', 'Tools/RailCards');

/**
 * Rail cards for a sharing group. $sg is SharingGroupsController::view's
 * find, with SharingGroupOrg and SharingGroupServer only when the viewer
 * may see the members.
 */
class SharingGroupRailCards
{
    const COUNT_CAP = 100000;
    const FACET_LIMIT = 4;

    /**
     * Shape, title and icon of the cards loaded after first paint.
     *
     * @return array
     */
    private function heads()
    {
        return [
            'sg-inventory' => ['inventory', __('What travels through it'), 'fas fa-boxes-stacked'],
        ];
    }

    /**
     * @param string $cardId
     * @param int $sgId
     * @return array
     */
    public function slot($cardId, $sgId)
    {
        list($shape, $title, $icon) = $this->heads()[$cardId];
        return RailCard::slot($shape, $cardId, $title, $icon,
            '/sharing_groups/railCard/' . (int)$sgId . '/' . $cardId);
    }

    /**
     * @param string $cardId
     * @param array $user
     * @param int $sgId
     * @return array
     * @throws NotFoundException
     */
    public function lazy($cardId, array $user, $sgId)
    {
        if ($cardId === 'sg-inventory') {
            return $this->inventory($user, (int)$sgId);
        }
        throw new NotFoundException(__('Invalid rail card.'));
    }

    /**
     * The events, attributes, objects, reports and galaxy clusters the user
     * can see that are distributed to this sharing group.
     *
     * @param array $user
     * @param int $sgId
     * @return array
     */
    public function inventory(array $user, $sgId)
    {
        $Event = ClassRegistry::init('Event');
        $Attribute = ClassRegistry::init('MispAttribute');
        $Object = ClassRegistry::init('MispObject');
        $Report = ClassRegistry::init('EventReport');
        $Cluster = ClassRegistry::init('GalaxyCluster');
        $inEvent = ['Event' => ['fields' => ['Event.id']]];
        $kinds = [
            'events' => [$Event, __('Events'), 'event', 'misp-icon misp-icon-event misp-simple',
                $Event->createEventConditions($user), [], []],
            'attributes' => [$Attribute, __('Attributes'), 'attribute', 'misp-icon misp-icon-attribute misp-simple',
                $Attribute->buildConditions($user), $inEvent + ['Object' => ['fields' => ['Object.id']]],
                ['Attribute.deleted' => 0]],
            'objects' => [$Object, __('Objects'), 'object', 'misp-icon misp-icon-object misp-simple',
                $Object->buildConditions($user), $inEvent, ['Object.deleted' => 0]],
            'reports' => [$Report, __('Reports'), 'report', 'misp-icon misp-icon-report misp-simple',
                $Report->buildACLConditions($user), $inEvent, ['EventReport.deleted' => 0]],
            'clusters' => [$Cluster, __('Galaxy clusters'), 'galaxy', 'misp-icon misp-icon-galaxy misp-simple',
                $Cluster->buildConditions($user), [], ['GalaxyCluster.deleted' => 0]],
        ];
        $groups = [];
        $capped = false;
        foreach ($kinds as $key => list($model, $label, $colour, $icon, $acl, $contain, $live)) {
            $conditions = $live + [
                $model->alias . '.distribution' => 4,
                $model->alias . '.sharing_group_id' => $sgId,
            ];
            if (!empty($acl)) {
                $conditions['AND'][] = $acl;
            }
            list($count, $hitCap) = RailCard::countUpTo($model, [
                'recursive' => -1,
                'conditions' => $conditions,
                'contain' => empty($acl) ? [] : $contain,
            ], self::COUNT_CAP);
            $capped = $capped || $hitCap;
            if ($count) {
                $groups[] = [
                    'key' => $key,
                    'label' => $label,
                    'icon' => $icon,
                    'color' => $colour,
                    'count' => $count,
                ];
            }
        }
        $total = array_sum(array_column($groups, 'count'));
        list(, $title, $icon) = $this->heads()['sg-inventory'];
        return RailCard::inventory(
            'sg-inventory',
            $title,
            $icon,
            ['count' => $total, 'label' => __n('record', 'records', $total)],
            $groups,
            true,
            [
                'link' => ['label' => __('Events'), 'href' => '/events/index/searchsharinggroup:' . $sgId],
                'empty' => __('Nothing is distributed to this sharing group yet.'),
                'note' => $capped ? __('Counted up to %s per kind.', number_format(self::COUNT_CAP)) : null,
                'lazy' => true,
            ]
        );
    }

    /**
     * Who the group reaches: its member organisations by locality, country
     * and sector, and the instances it is pushed to. When the viewer may not
     * see the members, only how many there are.
     *
     * @param array $sg
     * @return array
     */
    public function reach(array $sg)
    {
        if (!isset($sg['SharingGroupOrg'])) {
            $count = (int)($sg['SharingGroup']['org_count'] ?? 0);
            return RailCard::usage(
                'sg-reach',
                __('Reach'),
                'misp-icon misp-icon-organisation misp-simple',
                ['count' => $count, 'label' => __n('member organisation', 'member organisations', $count)],
                [],
                null,
                ['empty' => __('No organisation is a member yet.')]
            );
        }
        $orgIds = array_values(array_unique(array_column($sg['SharingGroupOrg'], 'org_id')));
        $orgs = [];
        if (!empty($orgIds)) {
            $orgs = ClassRegistry::init('Organisation')->find('all', [
                'recursive' => -1,
                'conditions' => ['Organisation.id' => $orgIds],
                'fields' => ['Organisation.id', 'Organisation.local', 'Organisation.nationality', 'Organisation.sector'],
            ]);
        }
        $local = 0;
        $countries = $sectors = [];
        foreach ($orgs as $org) {
            $o = $org['Organisation'];
            $local += $o['local'] ? 1 : 0;
            $countries[trim((string)$o['nationality'])][] = $o['id'];
            $sectors[trim((string)$o['sector'])][] = $o['id'];
        }
        $total = count($orgs);
        $groups = [];
        if ($total) {
            $groups[] = [
                'key' => 'locality',
                'label' => __('Locality'),
                'icon' => 'fas fa-location-dot',
                'count' => $total,
                'partition' => true,
                'facets' => array_values(array_filter([
                    $local ? ['label' => __('Local'), 'count' => $local] : null,
                    $total - $local ? ['label' => __('Remote'), 'count' => $total - $local] : null,
                ])),
            ];
            $groups[] = $this->spread('countries', __('Countries'), 'fas fa-earth-europe', $countries, $total);
            $groups[] = $this->spread('sectors', __('Sectors'), 'fas fa-industry', $sectors, $total);
        }

        $note = null;
        if ($total && !empty($sg['SharingGroup']['roaming'])) {
            $note = __('Roaming: it travels to any instance its members connect to.');
        } else if ($total && isset($sg['SharingGroupServer'])) {
            $servers = count(array_filter(array_column($sg['SharingGroupServer'], 'server_id')));
            $note = $servers
                ? __n('Pushed to %s other instance.', 'Pushed to %s other instances.', $servers, $servers)
                : __('Stays on this instance.');
        }
        return RailCard::inventory(
            'sg-reach',
            __('Reach'),
            'misp-icon misp-icon-organisation misp-simple',
            ['count' => $total, 'label' => __n('member organisation', 'member organisations', $total)],
            $groups,
            false,
            [
                'empty' => __('No organisation is a member yet.'),
                'note' => $note,
            ]
        );
    }

    /**
     * The largest buckets of $buckets as facets; organisations that leave the
     * field blank are a muted "Not set".
     *
     * @param string $key
     * @param string $label
     * @param string $icon
     * @param array $buckets value => [org id]
     * @param int $total
     * @return array
     */
    private function spread($key, $label, $icon, array $buckets, $total)
    {
        $unset = count($buckets[''] ?? []);
        unset($buckets['']);
        $counts = array_map('count', $buckets);
        arsort($counts);
        $facets = [];
        foreach (array_slice($counts, 0, self::FACET_LIMIT, true) as $value => $count) {
            $facets[] = ['label' => (string)$value, 'count' => $count];
        }
        $more = max(0, count($counts) - self::FACET_LIMIT);
        if ($unset) {
            $facets[] = ['label' => __('Not set'), 'count' => $unset, 'tone' => 'muted'];
        }
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'count' => $total,
            'partition' => true,
            'facets' => $facets,
            'more' => $more,
        ];
    }

    /**
     * The blueprint that regenerates this group, for the people who could
     * edit the group by hand. Null when no blueprint owns it.
     *
     * @param array $user
     * @param array $sg
     * @param bool $mayView whether the viewer may open the blueprint
     * @return array|null
     */
    public function blueprint(array $user, array $sg, $mayView)
    {
        $blueprint = ClassRegistry::init('SharingGroupBlueprint')->find('first', [
            'recursive' => -1,
            'conditions' => ['SharingGroupBlueprint.sharing_group_id' => $sg['SharingGroup']['id']],
            'fields' => ['id', 'name', 'org_id', 'timestamp'],
            'contain' => ['Organisation' => ['fields' => ['Organisation.name']]],
        ]);
        if (empty($blueprint)) {
            return null;
        }
        $b = $blueprint['SharingGroupBlueprint'];
        $mayView = $mayView && ($user['Role']['perm_site_admin'] || (int)$b['org_id'] === (int)$user['org_id']);
        $items = [
            ['label' => __('Owner'), 'value' => $blueprint['Organisation']['name'] ?? __('Unknown')],
        ];
        if (!empty($b['timestamp'])) {
            $items[] = ['label' => __('Rules saved'), 'value' => RailCard::ago((int)$b['timestamp'])];
        }
        return RailCard::status(
            'sg-blueprint',
            __('Managed by a blueprint'),
            'fas fa-wand-magic-sparkles',
            'info',
            __('Blueprint "%s" sets the members. Running it overwrites any change made by hand.', $b['name']),
            $items,
            $mayView ? ['label' => __('Open blueprint'), 'href' => '/sharing_group_blueprints/view/' . $b['id'], 'method' => 'get'] : null
        );
    }
}
