<?php
App::uses('RailCard', 'Tools/RailCards');
App::uses('GalaxyMatrixLayout', 'Tools');

/**
 * Rail cards for a galaxy. $galaxy is ['Galaxy' => [...]] with
 * kill_chain_order decoded, as GalaxiesController::view loads it.
 */
class GalaxyRailCards
{
    const USAGE_LIMIT = 10;
    const CONTRIBUTOR_LIMIT = 3;
    const MATRIX_TABS = 3;

    /**
     * Shape, title and icon of the cards loaded after first paint.
     *
     * @return array
     */
    private function heads()
    {
        return [
            'galaxy-usage' => ['list', __('Most used'), 'fas fa-ranking-star'],
            'galaxy-matrix' => ['inventory', __('Matrix'), 'fas fa-table-cells'],
        ];
    }

    /**
     * @param string $cardId
     * @param int $galaxyId
     * @return array
     */
    public function slot($cardId, $galaxyId)
    {
        list($shape, $title, $icon) = $this->heads()[$cardId];
        return RailCard::slot($shape, $cardId, $title, $icon,
            '/galaxies/railCard/' . (int)$galaxyId . '/' . $cardId);
    }

    /**
     * @param string $cardId
     * @param array $user
     * @param array $galaxy
     * @return array
     * @throws NotFoundException
     */
    public function lazy($cardId, array $user, array $galaxy)
    {
        switch ($cardId) {
            case 'galaxy-usage':
                return $this->usage($user, $galaxy);
            case 'galaxy-matrix':
                return $this->matrix($user, $galaxy);
        }
        throw new NotFoundException(__('Invalid rail card.'));
    }

    /**
     * @param array $user
     * @param int $galaxyId
     * @return array conditions on GalaxyCluster for the galaxy's visible clusters
     */
    private function clusterConditions(array $user, $galaxyId)
    {
        $conditions = ClassRegistry::init('GalaxyCluster')->buildConditions($user);
        $conditions['AND'][] = ['GalaxyCluster.galaxy_id' => $galaxyId];
        return $conditions;
    }

    /**
     * Where the clusters come from: shipped or made here, published, deleted,
     * and the organisations that made the most.
     *
     * @param array $user
     * @param array $galaxy
     * @return array
     */
    public function composition(array $user, array $galaxy)
    {
        $GalaxyCluster = ClassRegistry::init('GalaxyCluster');
        $conditions = $this->clusterConditions($user, $galaxy['Galaxy']['id']);
        $rows = $GalaxyCluster->find('all', [
            'recursive' => -1,
            'conditions' => $conditions,
            'fields' => ['GalaxyCluster.default', 'GalaxyCluster.published', 'GalaxyCluster.deleted', 'COUNT(GalaxyCluster.id) AS n'],
            'group' => ['GalaxyCluster.default', 'GalaxyCluster.published', 'GalaxyCluster.deleted'],
        ]);
        $shipped = $local = $published = $deleted = 0;
        foreach ($rows as $row) {
            $c = $row['GalaxyCluster'];
            $n = (int)$row[0]['n'];
            if ($c['deleted']) {
                $deleted += $n;
                continue;
            }
            if ($c['default']) {
                $shipped += $n;
            } else {
                $local += $n;
                $published += $c['published'] ? $n : 0;
            }
        }
        $total = $shipped + $local;
        $groups = [];
        if ($total) {
            $groups[] = [
                'key' => 'origin',
                'label' => __('Origin'),
                'icon' => 'fas fa-box-open',
                'count' => $total,
                'partition' => true,
                'facets' => array_values(array_filter([
                    $shipped ? ['label' => __('Shipped'), 'count' => $shipped] : null,
                    $local ? ['label' => __('Made here'), 'count' => $local] : null,
                ])),
            ];
        }
        if ($local) {
            $groups[] = [
                'key' => 'published',
                'label' => __('Publication'),
                'icon' => 'fas fa-pen-ruler',
                'count' => $local,
                'partition' => true,
                'facets' => array_values(array_filter([
                    $published ? ['label' => __('Published'), 'count' => $published, 'tone' => 'ok'] : null,
                    $local - $published ? ['label' => __('Unpublished'), 'count' => $local - $published, 'tone' => 'muted'] : null,
                ])),
            ];
            $conditions['AND'][] = ['GalaxyCluster.default' => 0, 'GalaxyCluster.deleted' => 0];
            $byOrg = $GalaxyCluster->find('all', [
                'recursive' => -1,
                'conditions' => $conditions,
                'fields' => ['GalaxyCluster.orgc_id', 'COUNT(GalaxyCluster.id) AS n'],
                'group' => ['GalaxyCluster.orgc_id'],
            ]);
            usort($byOrg, function ($a, $b) {
                return (int)$b[0]['n'] <=> (int)$a[0]['n'];
            });
            $names = ClassRegistry::init('Organisation')->find('list', [
                'conditions' => ['Organisation.id' => array_column(array_column($byOrg, 'GalaxyCluster'), 'orgc_id')],
                'fields' => ['Organisation.id', 'Organisation.name'],
            ]);
            $facets = [];
            foreach (array_slice($byOrg, 0, self::CONTRIBUTOR_LIMIT) as $row) {
                $orgId = $row['GalaxyCluster']['orgc_id'];
                $facets[] = [
                    'label' => $names[$orgId] ?? __('Unknown'),
                    'count' => (int)$row[0]['n'],
                    'href' => '/organisations/view/' . $orgId,
                ];
            }
            $groups[] = [
                'key' => 'contributors',
                'label' => __n('%s contributor', '%s contributors', count($byOrg), count($byOrg)),
                'icon' => 'misp-icon misp-icon-organisation misp-simple',
                'count' => $local,
                'partition' => true,
                'facets' => $facets,
                'more' => max(0, count($byOrg) - self::CONTRIBUTOR_LIMIT),
            ];
        }
        return RailCard::inventory(
            'galaxy-composition',
            __('Composition'),
            'fas fa-layer-group',
            ['count' => $total, 'label' => __n('cluster', 'clusters', $total)],
            $groups,
            false,
            [
                'link' => ['label' => __('Clusters'), 'href' => '#tab-clusters'],
                'empty' => __('This galaxy has no cluster.'),
                'note' => $deleted ? __n('%s deleted cluster is not counted.', '%s deleted clusters are not counted.', $deleted, number_format($deleted)) : null,
            ]
        );
    }

    /**
     * The galaxy's clusters carried by the most events the user can see.
     *
     * @param array $user
     * @param array $galaxy
     * @return array
     */
    public function usage(array $user, array $galaxy)
    {
        $clusters = ClassRegistry::init('GalaxyCluster')->find('all', [
            'recursive' => -1,
            'conditions' => $this->clusterConditions($user, $galaxy['Galaxy']['id']) + ['GalaxyCluster.deleted' => 0],
            'fields' => ['GalaxyCluster.id', 'GalaxyCluster.value', 'GalaxyCluster.tag_name'],
        ]);
        $byTagName = [];
        foreach ($clusters as $cluster) {
            $byTagName[$cluster['GalaxyCluster']['tag_name']] = $cluster['GalaxyCluster'];
        }
        $tags = empty($byTagName) ? [] : ClassRegistry::init('Tag')->find('list', [
            'conditions' => ['Tag.name' => array_keys($byTagName)],
            'fields' => ['Tag.id', 'Tag.name'],
        ]);
        $counts = ClassRegistry::init('EventTag')->countForTags(array_keys($tags), $user);
        $counts = array_filter(array_map('intval', $counts));
        arsort($counts);
        $rows = [];
        foreach (array_slice($counts, 0, self::USAGE_LIMIT, true) as $tagId => $count) {
            $cluster = $byTagName[$tags[$tagId]];
            $rows[] = [
                'label' => $cluster['value'],
                'href' => '/galaxy_clusters/view/' . $cluster['id'],
                'count' => $count,
            ];
        }
        list(, $title, $icon) = $this->heads()['galaxy-usage'];
        return RailCard::rows(
            'galaxy-usage',
            $title,
            $icon,
            $rows,
            count($counts) > count($rows)
                ? ['label' => __('%s clusters in use', number_format(count($counts))), 'href' => '#tab-clusters']
                : null,
            ['empty' => __('No event carries a cluster of this galaxy.'), 'lazy' => true]
        );
    }

    /**
     * The kill chain as a thumbnail: per tab, how many clusters sit under
     * each tactic, in the tab's own order.
     *
     * @param array $user
     * @param array $galaxy
     * @return array
     */
    public function matrix(array $user, array $galaxy)
    {
        $order = (array)($galaxy['Galaxy']['kill_chain_order'] ?? []);
        $GalaxyElement = ClassRegistry::init('GalaxyElement');
        $conditions = $this->clusterConditions($user, $galaxy['Galaxy']['id']);
        $conditions['AND'][] = ['GalaxyElement.key' => 'kill_chain', 'GalaxyCluster.deleted' => 0];
        $rows = $GalaxyElement->find('all', [
            'recursive' => -1,
            'conditions' => $conditions,
            'contain' => ['GalaxyCluster' => ['fields' => ['GalaxyCluster.id']]],
            'fields' => ['GalaxyElement.value', 'GalaxyElement.galaxy_cluster_id'],
        ]);
        $cells = [];
        $clusters = [];
        foreach ($rows as $row) {
            $parts = explode(':', $row['GalaxyElement']['value'], 2);
            if (count($parts) !== 2) {
                continue;
            }
            list($tab, $tactic) = $parts;
            $clusterId = $row['GalaxyElement']['galaxy_cluster_id'];
            $cells[$tab][$tactic][$clusterId] = true;
            $clusters[$tab][$clusterId] = true;
            $clusters[''][$clusterId] = true;
        }
        uksort($cells, function ($a, $b) use ($clusters) {
            return count($clusters[$b]) <=> count($clusters[$a]);
        });
        $groups = [];
        foreach (array_slice($cells, 0, self::MATRIX_TABS, true) as $tab => $tactics) {
            $declared = array_map('strval', (array)($order[$tab] ?? []));
            $columns = array_merge($declared, array_diff(array_map('strval', array_keys($tactics)), $declared));
            $facets = [];
            foreach ($columns as $tactic) {
                if (!empty($tactics[$tactic])) {
                    $facets[] = [
                        'label' => GalaxyMatrixLayout::formatTactic($tactic),
                        'count' => count($tactics[$tactic]),
                    ];
                }
            }
            $groups[] = [
                'key' => 'tab-' . $tab,
                'label' => GalaxyMatrixLayout::formatTactic($tab),
                'icon' => 'fas fa-table-columns',
                'count' => count($clusters[$tab]),
                'facets' => $facets,
            ];
        }
        $total = count($clusters[''] ?? []);
        $hidden = count($cells) - count($groups);
        list(, $title, $icon) = $this->heads()['galaxy-matrix'];
        return RailCard::inventory(
            'galaxy-matrix',
            $title,
            $icon,
            ['count' => $total, 'label' => __n('cluster on the kill chain', 'clusters on the kill chain', $total)],
            $groups,
            false,
            [
                'empty' => __('No cluster is placed on the kill chain.'),
                'note' => $hidden > 0 ? __n('%s smaller tab is not shown.', '%s smaller tabs are not shown.', $hidden, $hidden) : null,
                'lazy' => true,
            ]
        );
    }
}
