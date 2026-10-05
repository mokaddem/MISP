<?php
$eventId = (int)$event['Event']['id'];
$mark = function (array $item) {
    $source = $item['kind'] === 'cluster' ? $item['cluster'] : $item['tag'];
    $source['_eo_level'] = $item['level'];
    $source['_eo_count'] = (int)$item['count'];
    return $source;
};
$chipOptions = [
    'searchUrl' => '',
    'unitClass' => function ($source) {
        return ($source['_eo_level'] ?? '') === 'indicators' ? 'eo-ind' : '';
    },
    'suffix' => function ($source) {
        $count = (int)($source['_eo_count'] ?? 0);
        if ($count === 0) {
            return '';
        }
        $only = ($source['_eo_level'] ?? '') === 'indicators';
        return sprintf(
            '<span class="eo-on%s" title="%s"><i class="misp-icon misp-icon-attribute misp-simple"></i>%s</span>',
            $only ? ' is-only' : '',
            h($only
                ? __n('Only on %s indicator', 'Only on %s indicators', $count, $count)
                : __n('Also on %s indicator', 'Also on %s indicators', $count, $count)),
            h(__('on %s', $count))
        );
    },
];
$tags = function (array $items) use ($mark) {
    $out = [];
    foreach ($items as $item) {
        if ($item['kind'] === 'tag') {
            $out[] = $mark($item);
        }
    }
    return $out;
};
$clusters = function (array $items) use ($mark) {
    $out = [];
    foreach ($items as $item) {
        if ($item['kind'] === 'cluster') {
            $out[] = $mark($item);
        }
    }
    return $out;
};
$techniques = function (array $items) use ($baseurl) {
    ob_start();
    echo '<ul class="eo-techniques">';
    foreach ($items as $item) {
        $cluster = $item['cluster'];
        $only = $item['level'] === 'indicators';
        $href = !empty($cluster['id']) ? $baseurl . '/galaxy_clusters/view/' . (int)$cluster['id'] : null;
        printf(
            '<li class="%s" title="%s">%s<span class="eo-tech-id">%s</span><span class="eo-tech-name">%s</span>%s%s</li>',
            $only ? 'eo-ind' : '',
            h($cluster['value']),
            $href ? '<a href="' . h($href) . '">' : '<span>',
            h($item['technique'] ?? ''),
            h(EventContextTool::techniqueName($cluster)),
            $href ? '</a>' : '</span>',
            $item['count'] > 0
                ? sprintf(
                    '<span class="eo-on%s" title="%s"><i class="misp-icon misp-icon-attribute misp-simple"></i>%s</span>',
                    $only ? ' is-only' : '',
                    h($only
                        ? __n('Only on %s indicator', 'Only on %s indicators', $item['count'], $item['count'])
                        : __n('Also on %s indicator', 'Also on %s indicators', $item['count'], $item['count'])),
                    h(__('on %s', $item['count']))
                )
                : ''
        );
    }
    echo '</ul>';
    return ob_get_clean();
};

$labelCount = 0;
foreach (['attribution', 'behaviour', 'classification', 'clusters', 'mitigation'] as $row) {
    foreach ($rows[$row] as $item) {
        $labelCount += $item['kind'] === 'absent' ? 0 : 1;
    }
}
$editTags = $baseurl . '/events/editEventTags/' . $eventId;
$editClusters = $baseurl . '/events/editEventGalaxies/' . $eventId;
$hasIndicatorOnly = false;
foreach ($rows as $items) {
    foreach ((array)$items as $item) {
        if (($item['level'] ?? '') === 'indicators') {
            $hasIndicatorOnly = true;
        }
    }
}
?>
<div class="eo-context">
    <div class="eo-card-head">
        <div class="misp-icon-tile eo-tile" style="--tile:var(--bs-galaxy);--tile-bg:color-mix(in srgb, var(--bs-galaxy) 12%, transparent);">
            <i class="misp-icon misp-icon-galaxy misp-simple"></i>
        </div>
        <div class="min-w-0 me-auto">
            <div class="eo-card-title"><?= __('Context') ?></div>
            <div class="eo-card-sub text-truncate">
                <?= $profileName ? h(__('Ordered by your %s profile', $profileName)) : __('Tags and galaxy clusters') ?>
            </div>
        </div>
        <div class="eo-figure"><b><?= $labelCount ?></b> <?= h(__n('label', 'labels', $labelCount)) ?></div>
    </div>

    <dl class="eo-rows">
        <dt><?= __('Attribution') ?></dt>
        <dd>
            <?php if (empty($rows['attribution'])): ?>
                <span class="eo-none"><i class="fas fa-user-secret"></i><?= __('None stated') ?></span>
            <?php else: ?>
                <?= $this->TagChip->clusters($clusters($rows['attribution']), $chipOptions) ?>
            <?php endif; ?>
        </dd>

        <?php if (!empty($rows['behaviour'])): ?>
            <dt><?= __('Behaviour') ?><small><?= h(__n('%s technique', '%s techniques', count($rows['behaviour']), count($rows['behaviour']))) ?></small></dt>
            <dd><?= $techniques($rows['behaviour']) ?></dd>
        <?php endif; ?>

        <?php if (!empty($rows['classification']) || !empty($rows['clusters'])): ?>
            <dt><?= __('Classification') ?></dt>
            <dd>
                <div class="eo-chips">
                    <?php foreach ($rows['classification'] as $item): ?>
                        <?php if ($item['kind'] === 'absent'): ?>
                            <span class="eo-absent" title="<?= h(__('Pinned by your analyst profile, not on this event')) ?>">
                                <i class="fas fa-thumbtack"></i><?= h($item['key']) ?> <em><?= __('not set') ?></em>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?= $this->TagChip->collection($tags($rows['classification']), $chipOptions) ?>
                    <?php if (!empty($rows['clusters'])): ?>
                        <?= $this->TagChip->clusters($clusters($rows['clusters']), $chipOptions) ?>
                    <?php endif; ?>
                </div>
            </dd>
        <?php endif; ?>

        <?php if (!empty($rows['mitigation'])): ?>
            <dt><?= __('Mitigation') ?></dt>
            <dd><?= $techniques($rows['mitigation']) ?></dd>
        <?php endif; ?>
    </dl>

    <?php if (!$rolledUp): ?>
        <div class="eo-rollup">
            <span class="eo-muted small"><?= __('Labels found on the indicators are not included yet — this event is large.') ?></span>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-eo-rollup="<?= h($baseurl . '/events/viewEventContext/' . $eventId . '/rollup:1') ?>">
                <?= __('Include them') ?>
            </button>
        </div>
    <?php endif; ?>

    <div class="eo-card-foot">
        <?php if ($hasIndicatorOnly): ?>
            <span class="eo-legend"><span class="eo-legend-swatch"></span><?= __('only on indicators') ?></span>
        <?php endif; ?>
        <?php if ($mayModify): ?>
            <button type="button" class="btn btn-sm btn-link px-0 ms-auto" onclick="openModal('<?= h($editTags) ?>', 'xl')">
                <i class="fas fa-pen-to-square me-1"></i><?= __('Edit tags') ?>
            </button>
            <button type="button" class="btn btn-sm btn-link px-0" onclick="openModal('<?= h($editClusters) ?>', 'xl')">
                <i class="fas fa-pen-to-square me-1"></i><?= __('Edit clusters') ?>
            </button>
        <?php endif; ?>
    </div>
</div>
