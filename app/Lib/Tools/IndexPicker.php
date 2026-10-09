<?php

/**
 * Search-as-you-type for index filter pickers: one row shape
 * `{value, label, style}` for organisations, tags and galaxy clusters.
 */
class IndexPicker
{
    const LIMIT = 20;
    const MIN_TERM = 2;
    const CONTAINS_FROM = 3;

    /**
     * Prefix matches first; when they leave room and the term is long
     * enough, unordered contains matches fill the rest.
     *
     * @param Model $model
     * @param array $query find('all') options without the match condition
     * @param string $field the matched column, e.g. 'LOWER(Tag.name)'
     * @param string $term
     * @param callable $row model row => picker row
     * @return array
     */
    public static function search(Model $model, array $query, $field, $term, callable $row)
    {
        $term = mb_strtolower(trim((string)$term));
        if (mb_strlen($term) < self::MIN_TERM) {
            return [];
        }
        $like = addcslashes($term, '%_\\');
        $found = [];
        $collect = function (array $rows) use (&$found, $row) {
            foreach ($rows as $r) {
                $out = $row($r);
                if ($out !== null && !isset($found[$out['value']]) && count($found) < self::LIMIT) {
                    $found[$out['value']] = $out;
                }
            }
        };
        $prefix = $query;
        $prefix['conditions']['AND'][] = [$field . ' LIKE' => $like . '%'];
        $prefix['limit'] = self::LIMIT;
        $collect($model->find('all', $prefix));

        if (count($found) < self::LIMIT && mb_strlen($term) >= self::CONTAINS_FROM) {
            $contains = $query;
            $contains['conditions']['AND'][] = [$field . ' LIKE' => '%' . $like . '%'];
            $contains['limit'] = self::LIMIT;
            $contains['order'] = false;
            $collect($model->find('all', $contains));
        }
        return array_values($found);
    }

    /**
     * @param array $user
     * @return bool whether this reader may pick creator organisations
     */
    public static function canPickOrgs(array $user)
    {
        if (!Configure::read('MISP.showorg')) {
            return false;
        }
        return !Configure::read('Security.hide_organisation_index_from_users')
            || !empty($user['Role']['perm_sharing_group']);
    }

    /**
     * @param array $user
     * @param string $term
     * @return array
     */
    public static function organisations(array $user, $term)
    {
        if (!self::canPickOrgs($user)) {
            return [];
        }
        $Organisation = ClassRegistry::init('Organisation');
        return self::search($Organisation, [
            'conditions' => ['AND' => [$Organisation->createConditions($user)]],
            'fields' => ['Organisation.id', 'Organisation.name', 'Organisation.uuid'],
            'order' => ['Organisation.name' => 'ASC'],
            'recursive' => -1,
        ], 'LOWER(Organisation.name)', $term, [self::class, 'orgRow']);
    }

    /**
     * The org picker's rows before anything is typed: the reader's own org,
     * then the creator orgs of the rows on the page, most frequent first.
     *
     * @param array $user
     * @param array $orgs one Organisation (id, name, uuid) per row on the page
     * @param int $limit
     * @return array groups of {label, rows, more}
     */
    public static function orgSuggestions(array $user, array $orgs, $limit = 10)
    {
        if (!self::canPickOrgs($user)) {
            return [];
        }
        $groups = [];
        $mine = (string)($user['Organisation']['id'] ?? '');
        if ($mine !== '') {
            $row = self::orgRow(['Organisation' => $user['Organisation']]);
            $row['note'] = __('your organisation');
            $groups[] = ['label' => null, 'rows' => [$row], 'more' => 0];
        }
        $counts = [];
        $seen = [];
        foreach ($orgs as $org) {
            $id = (string)($org['id'] ?? '');
            if ($id === '' || $id === $mine) {
                continue;
            }
            $counts[$id] = ($counts[$id] ?? 0) + 1;
            $seen[$id] = $org;
        }
        uksort($counts, function ($a, $b) use ($counts, $seen) {
            return $counts[$b] <=> $counts[$a] ?: strcasecmp($seen[$a]['name'] ?? '', $seen[$b]['name'] ?? '');
        });
        $ids = array_slice(array_keys($counts), 0, $limit);
        if ($ids) {
            $groups[] = [
                'label' => __('On this page'),
                'rows' => array_map(function ($id) use ($seen) {
                    return self::orgRow(['Organisation' => $seen[$id]]);
                }, $ids),
                'more' => count($counts) - count($ids),
            ];
        }
        return $groups;
    }

    /**
     * @param array $user
     * @param string $term
     * @return array
     */
    public static function tags(array $user, $term)
    {
        $Tag = ClassRegistry::init('Tag');
        return self::search($Tag, [
            'conditions' => ['AND' => [
                $Tag->createConditions($user),
                ['Tag.hide_tag' => 0, 'Tag.is_galaxy' => 0],
            ]],
            'fields' => ['Tag.name', 'Tag.colour'],
            'order' => ['Tag.name' => 'ASC'],
            'recursive' => -1,
        ], 'Tag.name', $term, [self::class, 'tagRow']);
    }

    /**
     * @param array $user
     * @param string $term
     * @param string $valueField 'tag_name', or 'uuid' to name one cluster among same-named ones
     * @return array
     */
    public static function clusters(array $user, $term, $valueField = 'tag_name')
    {
        $GalaxyCluster = ClassRegistry::init('GalaxyCluster');
        return self::search($GalaxyCluster, [
            'conditions' => ['AND' => [
                $GalaxyCluster->buildConditions($user),
                ['GalaxyCluster.deleted' => 0],
            ]],
            'fields' => ['GalaxyCluster.value', 'GalaxyCluster.tag_name', 'GalaxyCluster.uuid', 'Galaxy.name', 'Galaxy.icon'],
            'contain' => ['Galaxy'],
            'order' => ['GalaxyCluster.value' => 'ASC'],
        ], 'LOWER(GalaxyCluster.value)', $term, function (array $r) use ($valueField) {
            $row = self::clusterRow($r);
            $row['value'] = $r['GalaxyCluster'][$valueField];
            return $row;
        });
    }

    /**
     * The sharing groups the reader may see, by name; the picker narrows the
     * whole list itself.
     *
     * @param array $user
     * @param array|null $ids only these
     * @return array
     */
    public static function sharingGroups(array $user, $ids = null)
    {
        $SharingGroup = ClassRegistry::init('SharingGroup');
        $authorised = array_diff($SharingGroup->authorizedIds($user), [-1]);
        if ($ids !== null) {
            $authorised = array_intersect($authorised, array_map('intval', $ids));
        }
        if (empty($authorised)) {
            return [];
        }
        $rows = $SharingGroup->find('all', [
            'conditions' => ['SharingGroup.id' => array_values($authorised)],
            'fields' => ['SharingGroup.id', 'SharingGroup.name', 'SharingGroup.active'],
            'order' => ['SharingGroup.name' => 'ASC'],
            'recursive' => -1,
        ]);
        return array_map(function (array $r) {
            return [
                'value' => (string)$r['SharingGroup']['id'],
                'label' => $r['SharingGroup']['name'],
                'note' => $r['SharingGroup']['active'] ? null : __('inactive'),
            ];
        }, $rows);
    }

    /**
     * The chips' labels for the values a URL carries, under the same ACL as
     * the searches; a value the reader may not resolve stays as given.
     *
     * @param array $user
     * @param array $values kind (org, tag, galaxy, sharinggroup) => raw values, `!` stripped
     * @return array kind => value => {value, label, style}
     */
    public static function resolve(array $user, array $values)
    {
        $out = ['org' => [], 'tag' => [], 'galaxy' => [], 'sharinggroup' => []];
        $sharingGroups = array_values(array_filter($values['sharinggroup'] ?? [], 'is_numeric'));
        if ($sharingGroups) {
            $out['sharinggroup'] = array_column(self::sharingGroups($user, $sharingGroups), null, 'value');
        }
        $orgs = array_values(array_filter($values['org'] ?? [], 'is_numeric'));
        if ($orgs && self::canPickOrgs($user)) {
            $Organisation = ClassRegistry::init('Organisation');
            $rows = $Organisation->find('all', [
                'conditions' => ['AND' => [$Organisation->createConditions($user), ['Organisation.id' => $orgs]]],
                'fields' => ['Organisation.id', 'Organisation.name', 'Organisation.uuid'],
                'recursive' => -1,
            ]);
            foreach ($rows as $r) {
                $out['org'][(string)$r['Organisation']['id']] = self::orgRow($r);
            }
        }
        if (!empty($values['tag'])) {
            $Tag = ClassRegistry::init('Tag');
            $rows = $Tag->find('all', [
                'conditions' => ['AND' => [$Tag->createConditions($user), ['Tag.name' => $values['tag']]]],
                'fields' => ['Tag.name', 'Tag.colour'],
                'recursive' => -1,
            ]);
            foreach ($rows as $r) {
                $out['tag'][$r['Tag']['name']] = self::tagRow($r);
            }
        }
        if (!empty($values['galaxy'])) {
            $GalaxyCluster = ClassRegistry::init('GalaxyCluster');
            $rows = $GalaxyCluster->find('all', [
                'conditions' => ['AND' => [
                    $GalaxyCluster->buildConditions($user),
                    ['GalaxyCluster.tag_name' => $values['galaxy'], 'GalaxyCluster.deleted' => 0],
                ]],
                'fields' => ['GalaxyCluster.value', 'GalaxyCluster.tag_name', 'Galaxy.name', 'Galaxy.icon'],
                'contain' => ['Galaxy'],
            ]);
            foreach ($rows as $r) {
                $out['galaxy'][$r['GalaxyCluster']['tag_name']] = self::clusterRow($r);
            }
        }
        return $out;
    }

    /**
     * The org's logo when it has one, else the monogram the events index
     * draws for it.
     */
    public static function orgRow(array $r)
    {
        App::uses('OrgImgHelper', 'View/Helper');
        App::uses('EventCardTool', 'Tools/EventOverview');
        $org = $r['Organisation'];
        if (OrgImgHelper::imageFile($org) !== null) {
            $style = ['logo' => Configure::read('MISP.baseurl') . '/organisations/getOrgLogo/' . (int)$org['id']];
        } else {
            $mono = EventCardTool::monogram($org);
            $style = ['mono' => $mono['letters'], 'tint' => $mono['index']];
        }
        return [
            'value' => (string)$org['id'],
            'label' => $org['name'],
            'style' => $style,
        ];
    }

    public static function tagRow(array $r)
    {
        return [
            'value' => $r['Tag']['name'],
            'label' => $r['Tag']['name'],
            'style' => ['colour' => $r['Tag']['colour'] ?: null],
        ];
    }

    public static function clusterRow(array $r)
    {
        $icon = $r['Galaxy']['icon'] ?? null;
        if ($icon && preg_match('/^[a-z0-9-]+$/', $icon)) {
            App::uses('FontAwesomeHelper', 'View/Helper');
            $icon = FontAwesomeHelper::findNamespace($icon) . ' fa-' . $icon;
        } else {
            $icon = null;
        }
        return [
            'value' => $r['GalaxyCluster']['tag_name'],
            'label' => $r['GalaxyCluster']['value'],
            'style' => [
                'galaxy' => $r['Galaxy']['name'] ?? null,
                'icon' => $icon,
            ],
        ];
    }
}
