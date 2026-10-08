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
            'fields' => ['Organisation.id', 'Organisation.name'],
            'order' => ['Organisation.name' => 'ASC'],
            'recursive' => -1,
        ], 'LOWER(Organisation.name)', $term, function ($r) {
            return [
                'value' => (string)$r['Organisation']['id'],
                'label' => $r['Organisation']['name'],
                'style' => null,
            ];
        });
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
     * @return array
     */
    public static function clusters(array $user, $term)
    {
        $GalaxyCluster = ClassRegistry::init('GalaxyCluster');
        return self::search($GalaxyCluster, [
            'conditions' => ['AND' => [
                $GalaxyCluster->buildConditions($user),
                ['GalaxyCluster.deleted' => 0],
            ]],
            'fields' => ['GalaxyCluster.value', 'GalaxyCluster.tag_name', 'Galaxy.name', 'Galaxy.icon'],
            'contain' => ['Galaxy'],
            'order' => ['GalaxyCluster.value' => 'ASC'],
        ], 'LOWER(GalaxyCluster.value)', $term, [self::class, 'clusterRow']);
    }

    /**
     * The chips' labels for the values a URL carries, under the same ACL as
     * the searches; a value the reader may not resolve stays as given.
     *
     * @param array $user
     * @param array $values kind (org, tag, galaxy) => raw values, `!` stripped
     * @return array kind => value => {value, label, style}
     */
    public static function resolve(array $user, array $values)
    {
        $out = ['org' => [], 'tag' => [], 'galaxy' => []];
        $orgs = array_values(array_filter($values['org'] ?? [], 'is_numeric'));
        if ($orgs && self::canPickOrgs($user)) {
            $Organisation = ClassRegistry::init('Organisation');
            $rows = $Organisation->find('all', [
                'conditions' => ['AND' => [$Organisation->createConditions($user), ['Organisation.id' => $orgs]]],
                'fields' => ['Organisation.id', 'Organisation.name'],
                'recursive' => -1,
            ]);
            foreach ($rows as $r) {
                $out['org'][(string)$r['Organisation']['id']] = [
                    'value' => (string)$r['Organisation']['id'],
                    'label' => $r['Organisation']['name'],
                    'style' => null,
                ];
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
