<?php
App::uses('RailCard', 'Tools/RailCards');
App::uses('Validation', 'Utility');

/**
 * Rail cards for an event report. $report is EventReport::simpleFetchById(),
 * with its content and its Event.
 */
class EventReportRailCards
{
    const OUTLINE_LIMIT = 12;
    const FACET_LIMIT = 5;
    const SIBLING_LIMIT = 6;

    /**
     * The report's content outside fenced code blocks, line by line.
     *
     * @param string $content
     * @return array
     */
    private function proseLines($content)
    {
        $lines = [];
        $fence = null;
        foreach (preg_split('/\r\n|\r|\n/', (string)$content) as $line) {
            if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $m)) {
                if ($fence === null) {
                    $fence = $m[1][0];
                } else if ($m[1][0] === $fence) {
                    $fence = null;
                }
                continue;
            }
            if ($fence === null) {
                $lines[] = $line;
            }
        }
        return $lines;
    }

    /**
     * The report's sections: its top-level headings, each with the number of
     * headings under it.
     *
     * @param array $report
     * @return array
     */
    public function outline(array $report)
    {
        $headings = [];
        foreach ($this->proseLines($report['EventReport']['content'] ?? '') as $line) {
            if (preg_match('/^ {0,3}(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $m)) {
                $text = preg_replace('/@!?\[[a-z]+\]\(([^)\n]*)\)/', '$1', $m[2]);
                $text = preg_replace('/!?\[([^\]]*)\]\([^)]*\)/', '$1', $text);
                $text = trim(preg_replace('/[*_`]+/', '', $text));
                if ($text !== '') {
                    $headings[] = [strlen($m[1]), $text];
                }
            }
        }
        $rows = [];
        if (!empty($headings)) {
            $top = min(array_column($headings, 0));
            foreach ($headings as list($level, $text)) {
                if ($level === $top) {
                    $rows[] = ['label' => $text, 'count' => 0];
                } else if (!empty($rows)) {
                    $rows[count($rows) - 1]['count']++;
                } else {
                    $rows[] = ['label' => $text, 'count' => 0];
                }
            }
            foreach ($rows as $i => $row) {
                $rows[$i]['meta'] = $row['count'] ? [__n('%s subsection', '%s subsections', $row['count'], $row['count'])] : [];
                $rows[$i]['count'] = null;
            }
        }
        $count = count($rows);
        return RailCard::rows(
            'report-outline',
            __('Outline'),
            'fas fa-list-ol',
            array_slice($rows, 0, self::OUTLINE_LIMIT),
            $count > self::OUTLINE_LIMIT ? ['label' => __('%s more sections', $count - self::OUTLINE_LIMIT), 'href' => null] : null,
            ['empty' => __('The report has no heading.')]
        );
    }

    /**
     * The MISP elements the report mentions, by kind, counting each element
     * once. Attributes, objects and galaxies count only when the user can
     * see them.
     *
     * @param array $user
     * @param array $report
     * @return array
     */
    public function mentions(array $user, array $report)
    {
        $byScope = [];
        foreach ($this->proseLines($report['EventReport']['content'] ?? '') as $line) {
            if (preg_match_all('/@!?\[(attribute|object|tag|galaxymatrix)\]\(((?:(?!@!?\[)[^)\n])+)\)/', $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $byScope[$m[1]][trim($m[2])] = true;
                }
            }
        }
        $groups = [];
        $uuids = function ($scope) use ($byScope) {
            return array_values(array_filter(array_keys($byScope[$scope] ?? []), 'Validation::uuid'));
        };
        if ($attributeUuids = $uuids('attribute')) {
            $rows = ClassRegistry::init('MispAttribute')->fetchAttributesSimple($user, [
                'conditions' => ['Attribute.uuid' => $attributeUuids],
                'fields' => ['Attribute.uuid', 'Attribute.type'],
            ]);
            $groups[] = $this->group('attributes', __('Attributes'), 'misp-icon misp-icon-attribute misp-simple', 'attribute',
                array_count_values(array_column(array_column($rows, 'Attribute'), 'type')));
        }
        if ($objectUuids = $uuids('object')) {
            $rows = ClassRegistry::init('MispObject')->fetchObjectSimple($user, [
                'conditions' => ['Object.uuid' => $objectUuids],
                'fields' => ['Object.uuid', 'Object.name'],
            ]);
            $groups[] = $this->group('objects', __('Objects'), 'misp-icon misp-icon-object misp-simple', 'object',
                array_count_values(array_column(array_column($rows, 'Object'), 'name')));
        }
        $tags = $clusters = [];
        foreach (array_keys($byScope['tag'] ?? []) as $name) {
            if (preg_match('/^misp-galaxy:[^=]+="(.+)"$/', $name, $m)) {
                $clusters[$m[1]] = 1;
            } else {
                $tags[$name] = 1;
            }
        }
        if (!empty($clusters)) {
            $groups[] = $this->group('clusters', __('Galaxy clusters'), 'misp-icon misp-icon-galaxy misp-simple', 'galaxy', $clusters, false);
        }
        if (!empty($tags)) {
            $groups[] = $this->group('tags', __('Tags'), 'fas fa-tag', 'tag', $tags, false);
        }
        if ($galaxyUuids = $uuids('galaxymatrix')) {
            $Galaxy = ClassRegistry::init('Galaxy');
            $names = $Galaxy->find('column', [
                'conditions' => ['AND' => [['Galaxy.uuid' => $galaxyUuids], $Galaxy->buildConditions($user)]],
                'fields' => ['Galaxy.name'],
            ]);
            $groups[] = $this->group('galaxies', __('Galaxy matrices'), 'fas fa-table-cells', 'galaxy', array_fill_keys($names, 1), false);
        }
        $groups = array_values(array_filter($groups, function ($group) {
            return $group['count'] > 0;
        }));
        $total = array_sum(array_column($groups, 'count'));
        return RailCard::inventory(
            'report-mentions',
            __('Mentions'),
            'fas fa-at',
            ['count' => $total, 'label' => __n('element mentioned', 'elements mentioned', $total)],
            $groups,
            true,
            ['empty' => __('The report mentions no MISP element.')]
        );
    }

    /**
     * @param string $key
     * @param string $label
     * @param string $icon
     * @param string $colour
     * @param array $counts facet label => elements
     * @param bool $counted whether facets show their count
     * @return array
     */
    private function group($key, $label, $icon, $colour, array $counts, $counted = true)
    {
        arsort($counts);
        $facets = [];
        foreach (array_slice($counts, 0, self::FACET_LIMIT, true) as $facet => $count) {
            $facets[] = ['label' => (string)$facet, 'count' => $counted ? $count : null];
        }
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'color' => $colour,
            'count' => array_sum($counts),
            'partition' => $counted,
            'facets' => $facets,
            'more' => max(0, count($counts) - self::FACET_LIMIT),
        ];
    }

    /**
     * The event the report belongs to, then its other reports.
     *
     * @param array $user
     * @param array $report
     * @return array
     */
    public function siblings(array $user, array $report)
    {
        $event = $report['Event'] ?? [];
        $rows = [];
        if (!empty($event['id'])) {
            $rows[] = [
                'label' => $event['info'],
                'href' => '/events/view/' . $event['id'],
                'icon' => 'misp-icon misp-icon-event misp-simple',
                'meta' => array_values(array_filter([$event['Orgc']['name'] ?? '', $event['date'] ?? ''])),
                'badge' => ['label' => __('Event'), 'tone' => 'muted'],
            ];
        }
        $EventReport = ClassRegistry::init('EventReport');
        $conditions = $EventReport->buildACLConditions($user);
        $conditions['AND'][] = [
            'EventReport.event_id' => $report['EventReport']['event_id'],
            'EventReport.id !=' => $report['EventReport']['id'],
            'EventReport.deleted' => 0,
        ];
        $others = $EventReport->find('all', [
            'recursive' => -1,
            'conditions' => $conditions,
            'contain' => ['Event' => ['fields' => ['Event.id']]],
            'fields' => ['EventReport.id', 'EventReport.name', 'EventReport.timestamp'],
            'order' => ['EventReport.timestamp' => 'DESC'],
        ]);
        foreach (array_slice($others, 0, self::SIBLING_LIMIT) as $other) {
            $rows[] = [
                'label' => $other['EventReport']['name'],
                'href' => '/eventReports/view/' . $other['EventReport']['id'],
                'icon' => 'misp-icon misp-icon-report misp-simple',
                'meta' => [RailCard::ago((int)$other['EventReport']['timestamp'])],
            ];
        }
        return RailCard::rows(
            'report-siblings',
            __('In this event'),
            'misp-icon misp-icon-report misp-simple',
            $rows,
            count($others) > self::SIBLING_LIMIT && !empty($event['id'])
                ? ['label' => __('All %s reports', count($others) + 1), 'href' => '/events/view/' . $event['id']]
                : null,
            ['empty' => __('No other report in this event.')]
        );
    }
}
