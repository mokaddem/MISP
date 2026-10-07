<?php
/*
 * One event in the Overmind events index card view, wired in through
 * `card_element` (see genericElementsBS5/IndexTable/index_card).
 *
 * $row['EventCard'] is built by EventsController::__attachCardsToEvents().
 * The selection checkbox and the action menu are the scaffold's own
 * ($sections['selector'] / ['extra']), placed and restyled here.
 */
App::uses('EventCardTool', 'Tools/EventOverview');

$event = $row['Event'] ?? [];
$card = $row['EventCard'] ?? null;
if (empty($event['id']) || $card === null) {
    return;
}

$id = (int)$event['id'];
$orgc = $row['Orgc'] ?? [];
$org = $row['Org'] ?? [];
$now = time();
$utc = function ($timestamp) {
    return gmdate('Y-m-d H:i', (int)$timestamp) . ' UTC';
};
$plural = function ($n, $one, $many) {
    return __n($one, $many, $n, $n);
};

$state = $card['state'];
if ($state === 'pending') {
    $stateHtml = sprintf(
        '<span class="dk-state is-pending" title="%s">%s</span>',
        h(__('Published %s, changed since', $utc($event['publish_timestamp']))),
        __('Changes pending')
    );
} elseif ($state === 'published') {
    $stateHtml = empty($event['publish_timestamp'])
        ? sprintf('<span class="dk-state" title="%s">%s</span>', h(__('Published, no publish time recorded')), __('Published'))
        : sprintf(
            '<span class="dk-state" title="%s">%s</span>',
            h(__('Published %s', $utc($event['publish_timestamp']))),
            h(__('Published %s', gmdate('j M Y', (int)$event['publish_timestamp'])))
        );
} else {
    $stateHtml = sprintf('<span class="dk-state is-unpub" title="%s">%s</span>', h(__('Never published')), __('Unpublished'));
}

$rail = $card['markings']['rail'];
$railStyle = '';
if (!empty($rail['colour']) && preg_match('/^#[0-9a-f]{3,8}$/i', $rail['colour'])) {
    $railStyle = ' style="--dk-rail:' . h($rail['colour']) . '"';
}

$logo = empty($orgc) ? '' : $this->OrgImg->getOrgLogoV2($orgc, 22, false);
if ($logo !== '') {
    $orgTile = '<span class="dk-logo">' . $logo . '</span>';
} else {
    $mono = EventCardTool::monogram($orgc);
    $orgTile = sprintf('<span class="dk-logo is-mono m%d" aria-hidden="true">%s</span>', $mono['index'], h($mono['letters']));
}
$synced = !empty($org['id']) && !empty($orgc['id']) && (int)$org['id'] !== (int)$orgc['id'];
$orgTitle = __('Created by %s', $orgc['name'] ?? '')
    . ($synced ? ' · ' . __('held here by %s', $org['name'] ?? '') : '');

$markings = '';
foreach ($card['markings']['slots'] as $slot) {
    if (!$slot['present']) {
        $markings .= sprintf(
            '<span class="dk-mk is-none" title="%s"><small>%s</small></span>',
            h(__('No %s marking', $slot['key'])),
            h($slot['key'])
        );
        continue;
    }
    $colour = !$slot['neutral'] && preg_match('/^#[0-9a-f]{3,8}$/i', (string)$slot['colour']) ? $slot['colour'] : null;
    $markings .= sprintf(
        '<span class="dk-mk%s"%s title="%s"><small>%s</small><b>%s</b></span>',
        $colour ? '' : ' is-clear',
        $colour ? ' style="--c:' . h($colour) . '"' : '',
        h($slot['name']),
        h($slot['key']),
        h($slot['value'])
    );
}

$ext = [];
if (!empty($card['extends'])) {
    $parent = $card['extends'];
    $ext[] = $parent['id']
        ? sprintf(
            '<a href="%s" title="%s"><i class="fas fa-turn-up fa-rotate-90"></i>%s</a>',
            h($baseurl . '/events/view2/' . $parent['id']),
            h(__('Extends #%s %s', $parent['id'], $parent['info'])),
            h(__('extends #%s', $parent['id']))
        )
        : sprintf(
            '<span class="is-unheld" title="%s"><i class="fas fa-turn-up fa-rotate-90"></i>%s</span>',
            h(__('Extends %s, which is not available here', $parent['uuid'])),
            __('extends an unknown event')
        );
}
if (!empty($card['extended_by'])) {
    $n = (int)$card['extended_by'];
    $ext[] = sprintf(
        '<span title="%s"><i class="fas fa-code-branch"></i>%s</span>',
        h($plural($n, 'Extended by %s event', 'Extended by %s events')),
        h(__('extended by %s', $n))
    );
}

$chip = function (array $c) {
    switch ($c['kind']) {
        case 'cluster':
            $title = ($c['relationship'] ? $c['relationship'] . ': ' : '') . $c['galaxy'] . ' › ' . $c['label'];
            $icon = $c['icon'] ? $this->FontAwesome->getClass($c['icon']) : 'fas fa-circle-dot';
            return sprintf(
                '<span class="dk-chip is-gx%s" title="%s"><i class="%s"></i><span>%s</span></span>',
                $c['attribution'] ? ' is-attr' : '',
                h($title),
                $icon,
                h($c['label'])
            );
        case 'technique':
            $title = $c['label'] . '  ' . $c['name'] . ($c['unheld'] ? ' ' . __('(cluster not available here)') : '');
            return sprintf(
                '<span class="dk-chip is-gx is-tid%s" title="%s">%s</span>',
                $c['unheld'] ? ' is-unheld' : '',
                h($title),
                h($c['label'])
            );
        case 'mitigation':
            return sprintf(
                '<span class="dk-chip is-gx is-mit" title="%s"><i class="fas fa-shield-halved"></i>%s</span>',
                h(__('Mitigation %s', $c['label'] . '  ' . $c['name'])),
                h($c['label'])
            );
        case 'unheld':
            return sprintf(
                '<span class="dk-chip is-gx is-unheld" title="%s"><i class="fas fa-circle-dot"></i><span>%s</span></span>',
                h($c['galaxy'] . ' › ' . $c['label'] . ' ' . __('(cluster not available here)')),
                h($c['label'])
            );
        case 'tag':
            $colour = preg_match('/^#[0-9a-f]{3,8}$/i', (string)$c['colour']) ? ' style="--tc:' . h($c['colour']) . '"' : '';
            return sprintf(
                '<span class="dk-chip is-tag"%s title="%s"><span>%s%s</span></span>',
                $colour,
                h($c['name']),
                $c['namespace'] !== null ? '<small>' . h($c['namespace']) . '</small> ' : '',
                h($c['label'])
            );
        case 'fold':
            return sprintf(
                '<span class="dk-chip is-fold" tabindex="0" title="%s">%s</span>',
                h(__n(
                    '%s galaxy tag whose cluster is not available here',
                    '%s galaxy tags whose cluster is not available here',
                    $c['count'],
                    $c['count']
                )),
                h(__('%s unresolved', $c['count']))
            );
    }
    return '';
};

$line = function ($label, $icon, $count, array $chips, $empty) use ($chip) {
    $html = '<div class="dk-rl">' . $icon . h($label) . ($count ? ' <small>' . (int)$count . '</small>' : '') . '</div>';
    if (empty($chips)) {
        return $html . '<div class="dk-ctx"><span class="dk-none">' . h($empty) . '</span></div>';
    }
    return $html . '<div class="dk-ctx">' . implode('', array_map($chip, $chips)) . '<span class="dk-more" tabindex="0" hidden></span></div>';
};
$rows = $card['rows'];

$count = function ($n, $icon, $title) {
    $n = (int)$n;
    return sprintf(
        '<span class="dk-n%s" title="%s">%s<b>%s</b></span>',
        $n ? '' : ' is-zero',
        h($title),
        $icon,
        EventCardTool::compactCount($n)
    );
};
$counts = $count(
    $event['attribute_count'] ?? 0,
    '<i class="misp-icon misp-icon-attribute misp-simple text-attribute"></i>',
    $plural((int)($event['attribute_count'] ?? 0), '%s attribute', '%s attributes')
);
if (isset($event['object_count'])) {
    $counts .= $count(
        $event['object_count'],
        '<i class="misp-icon misp-icon-object misp-simple text-object"></i>',
        $plural((int)$event['object_count'], '%s object', '%s objects')
    );
}
if (isset($event['report_count'])) {
    $counts .= $count(
        $event['report_count'],
        '<i class="misp-icon misp-icon-report misp-simple text-report"></i>',
        $plural((int)$event['report_count'], '%s report', '%s reports')
    );
}
if (isset($event['correlation_count'])) {
    $counts .= $count(
        $event['correlation_count'],
        '<i class="fas fa-link text-correlation"></i>',
        $plural((int)$event['correlation_count'], '%s correlation', '%s correlations')
    );
}

$extras = [];
$graphs = $card['graphs'];
if ($graphs) {
    $n = count($graphs);
    $title = $plural($n, '%s analyst graph', '%s analyst graphs') . ":\n" . implode("\n", array_column($graphs, 'name'));
    $extras[] = sprintf(
        '<a class="dk-graph" href="%s" title="%s" aria-label="%s"><i class="misp-icon misp-icon-analyst-graph misp-simple text-analystGraph"></i> %d</a>',
        h($baseurl . ($n === 1 ? '/analyst_graphs/view/' . $graphs[0]['uuid'] : '/events/view2/' . $id)),
        h($title),
        h($title),
        $n
    );
}
foreach ([
    ['sightings_count', '<i class="misp-icon misp-icon-sighting misp-simple text-sighting"></i>', '%s sighting', '%s sightings'],
    ['proposals_count', '<i class="fas fa-comment-medical"></i>', '%s proposal', '%s proposals'],
    ['post_count', '<i class="fas fa-comments"></i>', '%s discussion post', '%s discussion posts'],
] as [$key, $icon, $one, $many]) {
    $n = (int)($event[$key] ?? 0);
    if ($n) {
        $extras[] = sprintf('<span title="%s">%s %s</span>', h($plural($n, $one, $many)), $icon, EventCardTool::compactCount($n));
    }
}

$dist = $card['distribution'];
?>
<article class="dk-card <?= h($rail['class']) ?>" data-event-id="<?= $id ?>"<?= $railStyle ?>>
    <div class="dk-kick">
        <span class="dk-lead">
            <i class="misp-icon misp-icon-event misp-simple" aria-hidden="true"></i>
            <?php if (!empty($sections['selector'])): ?>
                <span class="dk-sel"><?= implode('', $sections['selector']) ?></span>
            <?php endif; ?>
        </span>
        <?= $stateHtml ?>
        <span class="dk-id">#<?= $id ?></span>
        <?php if (!empty($sections['extra'])): ?>
            <div class="dk-menu"><?= implode('', $sections['extra']) ?></div>
        <?php endif; ?>
    </div>
    <h3 class="dk-title">
        <a href="<?= h($baseurl . '/events/view2/' . $id) ?>" title="<?= h($event['info']) ?>"><?= h($event['info']) ?></a>
    </h3>
    <div class="dk-org">
        <?= $orgTile ?>
        <a class="dk-orgname" href="<?= h($baseurl . '/organisations/view/' . ($orgc['id'] ?? '')) ?>" title="<?= h($orgTitle) ?>"><?= h($orgc['name'] ?? '') ?></a>
        <span class="dk-marks"><?= $markings ?></span>
    </div>
    <div class="dk-meta">
        <span title="<?= h(__('Event date')) ?>"><i class="fa-regular fa-calendar"></i><?= h(date('j M Y', strtotime($event['date']))) ?></span>
        <span class="dk-when" title="<?= h(__('Last change %s', $utc($event['timestamp']))) ?>"><i class="fas fa-pen"></i><?= h(EventCardTool::ago($event['timestamp'], $now)) ?></span>
        <?php if ($ext): ?>
            <span class="dk-ext"><?= implode('', $ext) ?></span>
        <?php endif; ?>
    </div>
    <div class="dk-rows">
        <?= $line(__('Attribution'), '<i class="fas fa-user-secret"></i>', $rows['attribution_count'], $rows['attribution'], __('None stated')) ?>
        <?= $line(__('Behaviour'), '<i class="misp-icon misp-icon-galaxy misp-simple"></i>', $rows['technique_count'], $rows['behaviour'], '—') ?>
        <?= $line(__('Classification'), '<i class="misp-icon misp-icon-taxonomy misp-simple"></i>', 0, $rows['classification'], '—') ?>
    </div>
    <div class="dk-foot">
        <?= $counts ?>
        <?php if ($extras): ?>
            <span class="dk-xtra"><?= implode('', $extras) ?></span>
        <?php endif; ?>
        <span class="dk-dist" style="<?= h($dist['style']) ?>" title="<?= h($dist['label']) ?>" aria-label="<?= h($dist['label']) ?>"><i class="<?= h($dist['icon']) ?>"></i></span>
    </div>
</article>
