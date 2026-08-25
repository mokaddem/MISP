<?php
/**
 * One module's results, or the merged set behind the rail's
 * `All results` row.
 *
 * Every pane the rail can reach is rendered, and one of them shown.
 * Switching modules is then a class change rather than a request,
 * which is the behaviour this tab has to be able to promise: nothing
 * queries anything, and picking a module cannot be the thing that
 * spends quota.
 *
 * Decisions are per element and never per module. MISP enrichment
 * comes back as attributes and objects, and whether to keep one of
 * them is a judgement about that one — a module-level "accept" would
 * write things nobody looked at.
 *
 * A plain partial, not an endpoint.
 *
 * @var array $enrichment
 * @var string|null $moduleName  null for the merged pane
 * @var bool $selected
 * @var string $noRun
 * @var string $noWrite
 */
$merged = $enrichment['merged'];
$isMerged = $moduleName === null;

$module = null;
$result = null;
if (!$isMerged) {
    foreach ($enrichment['modules'] as $candidate) {
        if ($candidate['name'] === $moduleName) {
            $module = $candidate;
            break;
        }
    }
    if (isset($enrichment['results'][$moduleName])) {
        $result = $enrichment['results'][$moduleName];
    }
}

$paneKey = $isMerged ? '__all' : $moduleName;
$kindLabels = array(
    'expansion' => __('Expansion'),
    'hover' => __('Hover'),
    'cortex' => __('Cortex analyser'),
);

/**
 * The withheld value bar.
 *
 * No third party was queried to build this page, so a hostname
 * printed here would be an invention. The row still carries the type
 * and the shape, which is what a reader decides on anyway.
 *
 * @param int $width In rem
 * @return string Markup
 */
$bar = function ($width) {
    return '<span class="vp-e-val" style="--vp-e-val-w: '
        . h((float)$width) . 'rem" title="'
        . h(__(
            'Value withheld: no module was queried to build this page.'
            . ' The type and the shape are real; the value is not'
            . ' invented to fill the gap.'
        )) . '"></span>';
};

/**
 * The three actions every returned element carries.
 *
 * @return string Markup
 */
$actions = function () use ($noWrite) {
    $buttons = array(
        array('label' => __('Add to event'), 'icon' => 'fas fa-plus',
            'class' => 'btn-outline-primary'),
        array('label' => __('New event'), 'icon' => 'fas fa-folder-plus',
            'class' => 'btn-outline-secondary'),
        array('label' => __('Dismiss'), 'icon' => 'fas fa-xmark',
            'class' => 'btn-outline-secondary'),
    );
    $out = '<div class="vp-e-el-acts">';
    foreach ($buttons as $button) {
        $out .= '<button type="button" class="btn btn-sm '
            . $button['class'] . ' disabled d-inline-flex'
            . ' align-items-center gap-1" disabled title="'
            . h($noWrite) . '"><i class="' . $button['icon'] . '"></i>'
            . h($button['label']) . '</button>';
    }
    return $out . '</div>';
};

/**
 * Where an element came from, and who else returned the same value.
 *
 * Two modules returning the same *type* is not corroboration, so the
 * note is only drawn where the fixture records the same *value*.
 *
 * @param array $item
 * @param bool $withModule Name the returning module too
 * @return string Markup
 */
$provenance = function (array $item, $withModule) {
    $out = '';
    if ($withModule && !empty($item['module'])) {
        $out .= '<span class="vp-e-prov">' . h($item['module'])
            . '</span>';
    }
    if (!empty($item['also'])) {
        $count = count($item['also']) + 1;
        $out .= '<span class="vp-e-prov-note" title="'
            . h(sprintf(
                __('Also returned by %s'),
                implode(', ', $item['also'])
            )) . '">' . h(sprintf(
                __('%d modules agree'),
                $count
            )) . '</span>';
    }
    return $out === ''
        ? ''
        : '<span class="vp-e-provs">' . $out . '</span>';
};

/**
 * One loose attribute.
 *
 * @param array $attribute
 * @param bool $withModule
 * @return string Markup
 */
$renderAttr = function (array $attribute, $withModule) use (
    $bar,
    $actions,
    $provenance
) {
    $isNew = !empty($attribute['is_new']);
    $out = '<div class="vp-e-el' . ($isNew ? ' vp-e-el-new' : '')
        . '" data-vp-e-item' . ($isNew ? ' data-vp-e-new' : '') . '>';

    $out .= '<button type="button" class="vp-e-disc" data-vp-e-disc'
        . ' aria-expanded="false" title="'
        . h(__('Where this element came from')) . '">'
        . '<i class="fas fa-chevron-right"></i></button>';

    $out .= '<div class="vp-e-el-body">';
    $out .= '<span class="vp-e-type">' . h($attribute['type'])
        . '</span>';
    $out .= $bar($attribute['width']);
    if (!empty($attribute['to_ids'])) {
        $out .= '<span class="vp-e-meta" title="'
            . h(__('The module proposes this as an IDS signature'))
            . '"><i class="fas fa-shield-halved text-warning"></i>'
            . 'to_ids</span>';
    }
    $out .= '<span class="vp-e-meta"><i class="far fa-calendar"></i>'
        . h($attribute['date']) . '</span>';

    if ($isNew) {
        $out .= '<span class="vp-e-new" title="'
            . h(__('Not present in the previous run')) . '">'
            . h(__('New')) . '</span>';
    } elseif (!empty($attribute['known'])) {
        $out .= '<span class="vp-e-known" title="'
            . h(__('This value already exists in MISP')) . '">'
            . h(__('Already in MISP')) . '</span>';
    }
    $out .= $provenance($attribute, $withModule);

    /*
     * The disclosure. Everything in it is a property of the element
     * rather than a claim about the value, which is the only kind of
     * statement this tab is in a position to make.
     */
    $rows = array();
    if (!empty($attribute['module'])) {
        $rows[__('Returned by')] = h($attribute['module']);
    }
    $rows[__('to_ids')] = h(empty($attribute['to_ids'])
        ? __('not set by the module')
        : __('set by the module'));
    if (!empty($attribute['known'])) {
        $rows[__('In MISP')] = h(__(
            'This value already exists, so adding it would duplicate'
            . ' an attribute you already have.'
        ));
    } elseif ($isNew) {
        $rows[__('In MISP')] = h(__(
            'Not returned by the previous run of this module.'
        ));
    }
    if (!empty($attribute['also'])) {
        $rows[__('Agreement')] = h(sprintf(
            __('The same value came back from %s.'),
            implode(', ', $attribute['also'])
        ));
    }
    $out .= '<div class="vp-e-why d-none" data-vp-e-fold><dl>';
    foreach ($rows as $term => $definition) {
        $out .= '<dt>' . h($term) . '</dt><dd>' . $definition . '</dd>';
    }
    $out .= '</dl></div>';

    $out .= '</div>';
    $out .= $actions();
    return $out . '</div>';
};

/**
 * One returned object and its relations.
 *
 * @param array $object
 * @param bool $withModule
 * @return string Markup
 */
$renderObject = function (array $object, $withModule) use (
    $bar,
    $actions,
    $provenance
) {
    $isNew = !empty($object['is_new']);
    $out = '<div class="vp-e-obj' . ($isNew ? ' vp-e-obj-new' : '')
        . '" data-vp-e-item' . ($isNew ? ' data-vp-e-new' : '') . '>';

    $out .= '<div class="vp-e-obj-head">';
    $out .= '<button type="button" class="vp-e-disc" data-vp-e-disc'
        . ' aria-expanded="true" title="'
        . h(__('Fold this object away')) . '">'
        . '<i class="fas fa-chevron-right"></i></button>';
    $out .= '<span class="misp-icon misp-icon-object misp-simple">'
        . '</span>';
    $out .= '<span class="vp-e-obj-name">' . h($object['name'])
        . '</span>';
    $out .= '<span class="vp-e-obj-count">' . h(sprintf(
        __n('%d attribute', '%d attributes', $object['attributes']),
        $object['attributes']
    )) . '</span>';
    if ($isNew) {
        $out .= '<span class="vp-e-new" title="'
            . h(__('This object was not in the previous run'))
            . '">' . h(__('New')) . '</span>';
    }
    $out .= $provenance($object, $withModule);
    $out .= '<div class="ms-auto d-flex align-items-center gap-2">'
        . $actions() . '</div>';
    $out .= '</div>';

    $out .= '<div data-vp-e-fold>';
    foreach ($object['elements'] as $element) {
        $out .= '<div class="vp-e-rel">'
            . '<span class="vp-e-rel-name">' . h($element['relation'])
            . '</span><span class="vp-e-type">' . h($element['type'])
            . '</span>' . $bar($element['width']) . '</div>';
    }
    $out .= '</div>';

    return $out . '</div>';
};

/*
 * ------------------------------------------------------------------
 * The header, which is the state stated once
 * ------------------------------------------------------------------
 */
if ($isMerged) {
    $title = $merged['elements'] > 0
        ? __('All results')
        : __('Nothing queried yet');
    $titleMono = false;
    $sub = $merged['elements'] > 0
        ? sprintf(
            __n(
                '%1$d module &middot; merged from the run of %2$s',
                '%1$d modules &middot; merged from the run of %2$s',
                $merged['modules']
            ),
            $merged['modules'],
            h($enrichment['last_run'])
        )
        : h(sprintf(
            __n(
                '%d module valid for this value',
                '%d modules valid for this value',
                count($enrichment['modules'])
            ),
            count($enrichment['modules'])
        ));
    $statusClass = $merged['elements'] > 0
        ? 'vp-e-status-ok'
        : 'vp-e-status-none';
    $statusIcon = $merged['elements'] > 0
        ? 'fas fa-check'
        : 'fas fa-circle-minus';
    $statusText = $merged['elements'] > 0
        ? sprintf(
            __n('%d element', '%d elements', $merged['elements']),
            $merged['elements']
        )
        : __('Nothing sent');
    $addCount = $merged['elements'];
} else {
    $title = $module['name'];
    $titleMono = true;
    $kind = isset($kindLabels[$module['kind']])
        ? $kindLabels[$module['kind']]
        : $module['kind'];
    $parts = array(h($kind));
    if ($module['shape'] !== null) {
        $parts[] = '<span class="font-monospace">'
            . h($module['shape']) . '</span>';
    }
    if ($module['state'] === 'timeout') {
        $limit = $module['kind'] === 'cortex'
            ? $enrichment['cortex_timeout']
            : $enrichment['timeout'];
        $parts[] = h(sprintf(
            __('gave up at %1$d s on %2$s'),
            $limit,
            $module['ran_at']
        ));
    } elseif ($module['ran_at'] !== null) {
        $parts[] = h(sprintf(
            __('ran %1$s in %2$s s'),
            $module['ran_at'],
            $module['took']
        ));
    } elseif ($module['last_ran_at'] !== null) {
        $parts[] = h(sprintf(
            __('last ran %s'),
            $module['last_ran_at']
        ));
    } else {
        $parts[] = h(__('never run against this value'));
    }
    $sub = implode(' &middot; ', $parts);

    $statusMap = array(
        'ok' => array('vp-e-status-ok', 'fas fa-check'),
        'timeout' => array('vp-e-status-timeout', 'fas fa-hourglass-half'),
        'none' => array('vp-e-status-none', 'fas fa-circle-minus'),
        'never' => array('vp-e-status-none', 'far fa-circle'),
    );
    $statusClass = $statusMap[$module['state']][0];
    $statusIcon = $statusMap[$module['state']][1];
    if ($module['state'] === 'ok') {
        $statusText = sprintf(
            __n('%d element', '%d elements', $module['elements']),
            $module['elements']
        );
    } elseif ($module['state'] === 'timeout') {
        $statusText = __('Timed out');
    } elseif ($module['state'] === 'none') {
        $statusText = __('No result');
    } else {
        $statusText = __('Never run');
    }
    $addCount = $module['elements'];
}

$dismissed = 0;
if ($isMerged) {
    foreach ($enrichment['results'] as $entry) {
        $dismissed += (int)$entry['dismissed'];
    }
} elseif ($result !== null) {
    $dismissed = (int)$result['dismissed'];
}

$hasElements = $isMerged
    ? $merged['elements'] > 0
    : ($result !== null && $module['elements'] > 0);
?>
<div data-vp-e-pane="<?= h($paneKey) ?>"
     class="<?= $selected ? '' : 'd-none' ?>">

    <div class="p-3 border-bottom d-flex align-items-center gap-2
                flex-wrap">
        <span class="vp-panel-glyph">
            <i class="fas fa-cube"></i>
        </span>
        <div class="me-auto vp-min-w-0">
            <div class="fw-bold lh-1<?= $titleMono
                ? ' font-monospace' : '' ?>">
                <?= h($title) ?>
            </div>
            <div class="small text-muted mt-1"><?= $sub ?></div>
        </div>
        <span class="vp-e-status <?= h($statusClass) ?>">
            <i class="<?= h($statusIcon) ?>"></i>
            <?= h($statusText) ?>
        </span>
        <?php if (!$isMerged): ?>
            <button type="button"
                    class="btn btn-sm btn-outline-secondary disabled
                           d-inline-flex align-items-center gap-1"
                    disabled title="<?= h($noRun) ?>">
                <i class="fas fa-rotate"></i>
                <?= h($module['ran_at'] === null
                    && $module['last_ran_at'] === null
                        ? __('Run')
                        : __('Re-run')) ?>
            </button>
        <?php endif; ?>
        <?php if ($addCount > 0): ?>
            <button type="button"
                    class="btn btn-sm btn-outline-primary disabled
                           d-inline-flex align-items-center gap-1"
                    disabled title="<?= h($noWrite) ?>">
                <i class="fas fa-plus"></i>
                <?= h(sprintf(__('Add all %d'), $addCount)) ?>
            </button>
        <?php endif; ?>
    </div>

    <?php if ($hasElements): ?>

        <?php
        /*
         * The delta band. Analysts re-run enrichment constantly and
         * the question they arrive with is what changed, so it goes
         * above the results rather than beside them. A run that
         * returned nothing new is still an answer, so the band stays
         * and goes quiet rather than disappearing.
         */
        $newCount = $isMerged ? $merged['new'] : $module['new'];
        $previous = null;
        $unchanged = 0;
        if ($isMerged) {
            foreach ($enrichment['results'] as $entry) {
                $unchanged += (int)$entry['delta']['unchanged'];
                $previous = $entry['delta']['previous_run'];
            }
        } else {
            $previous = $result['delta']['previous_run'];
            $unchanged = (int)$result['delta']['unchanged'];
        }
        ?>
        <div class="p-3 pb-0">
            <div class="vp-e-delta<?= $newCount > 0
                ? '' : ' vp-e-delta-quiet' ?>">
                <i class="<?= $newCount > 0
                    ? 'fas fa-arrow-trend-up'
                    : 'fas fa-equals' ?>"></i>
                <span class="vp-e-delta-text">
                    <?php if ($newCount > 0): ?>
                        <strong><?= h(sprintf(
                            __n(
                                '%1$d new value since the run on %2$s.',
                                '%1$d new values since the run on %2$s.',
                                $newCount
                            ),
                            $newCount,
                            $previous
                        )) ?></strong>
                        <?= h(sprintf(
                            __n(
                                'The other %d element was already'
                                    . ' returned last time.',
                                'The other %d elements were already'
                                    . ' returned last time.',
                                $unchanged
                            ),
                            $unchanged
                        )) ?>
                    <?php else: ?>
                        <strong><?= h(sprintf(
                            __('Nothing new since the run on %s.'),
                            $previous
                        )) ?></strong>
                        <?= h(sprintf(
                            __n(
                                'The %d element here was already'
                                    . ' returned last time.',
                                'All %d elements here were already'
                                    . ' returned last time.',
                                $unchanged
                            ),
                            $unchanged
                        )) ?>
                    <?php endif; ?>
                </span>
                <?php if ($newCount > 0): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary
                                   d-inline-flex align-items-center gap-1"
                            data-vp-e-only-new aria-pressed="false">
                        <i class="fas fa-filter"></i>
                        <?= h(__('Show only new')) ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex flex-column gap-2 p-3">
            <?php
            $objects = $isMerged
                ? $merged['objects']
                : $result['objects'];
            $attributes = $isMerged
                ? $merged['attributes']
                : $result['attributes'];
            foreach ($objects as $object) {
                echo $renderObject($object, $isMerged);
            }
            ?>
            <?php if (!empty($attributes)): ?>
                <div>
                    <?php foreach ($attributes as $attribute): ?>
                        <?= $renderAttr($attribute, $isMerged) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php
            /*
             * Fires only when the filter emptied the list. A pane that
             * had nothing to begin with keeps its own wording — "no
             * new elements" over a module that returned nothing is a
             * different and false claim.
             */
            ?>
            <div class="vp-empty-inline d-none" data-vp-e-empty>
                <?= h(__(
                    'Every element in this pane was already returned by'
                    . ' the previous run.'
                )) ?>
            </div>
        </div>

        <?php if ($dismissed > 0): ?>
            <?php
            /*
             * A dismissal is a decision, so it stays visible as one.
             * Dropping the row would make the pane's count disagree
             * with what the analyst remembers doing.
             */
            ?>
            <div class="px-3 pb-2">
                <div class="vp-e-el" style="border-top: 0">
                    <div class="vp-e-el-body">
                        <span class="text-muted small">
                            <i class="fas fa-eye-slash me-1"></i>
                            <?= h(sprintf(
                                __n(
                                    '%d element dismissed in this run',
                                    '%d elements dismissed in this run',
                                    $dismissed
                                ),
                                $dismissed
                            )) ?>
                        </span>
                    </div>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary
                                   disabled d-inline-flex
                                   align-items-center gap-1"
                            disabled title="<?= h($noWrite) ?>">
                        <i class="fas fa-rotate-left"></i>
                        <?= h(__('Restore')) ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <div class="vp-e-withheld">
            <i class="fas fa-circle-info me-1"></i>
            <?= h(__(
                'Every value above is drawn as a bar. No module was'
                . ' queried to build this page, and a hostname invented'
                . ' to fill the gap would be the one thing this tab'
                . ' must not produce. The types, the shapes and the'
                . ' counts are real.'
            )) ?>
        </div>

    <?php elseif ($isMerged): ?>

        <?php
        /*
         * Nothing queried yet — a briefing, not an apology. The reader
         * is being asked to spend money and to reveal an interest, so
         * the state before the spend gets the room to say what the
         * spend buys and what it costs.
         */
        $quota = 0;
        $external = 0;
        $total = count($enrichment['modules']);
        foreach ($enrichment['modules'] as $entry) {
            $quota += empty($entry['cost']['quota']) ? 0 : 1;
            $external += empty($entry['cost']['external']) ? 0 : 1;
        }
        ?>
        <div class="vp-e-cold">
            <div>
                <p class="vp-e-cold-title">
                    <?= h(__('Nothing queried yet.')) ?>
                </p>
                <p class="vp-e-cold-prose">
                    <?= h(__(
                        'The rail already knows what each module would'
                        . ' cost. Pick the ones worth spending on —'
                        . ' nothing runs until you do.'
                    )) ?>
                </p>
                <p class="vp-e-cold-prose">
                    <?= h(__(
                        'Nothing has been sent. Loading this page made'
                        . ' no request to any module, switching to this'
                        . ' tab made none, and selecting a module makes'
                        . ' none either. Running one queries somebody'
                        . ' else\'s service, which tells whoever runs'
                        . ' it that you are looking at this value.'
                    )) ?>
                </p>
            </div>
            <div class="vp-e-cold-ledger">
                <div class="vp-subhead">
                    <?= h(__('What running everything would cost')) ?>
                </div>
                <div class="vp-e-ledger-row">
                    <span><?= h(__('Spend API quota')) ?></span>
                    <span class="vp-e-ledger-n"><?= h(sprintf(
                        __('%1$d of %2$d'),
                        $quota,
                        $total
                    )) ?></span>
                </div>
                <div class="vp-e-ledger-row">
                    <span><?= h(__('Query a third party')) ?></span>
                    <span class="vp-e-ledger-n"><?= h(sprintf(
                        __('%1$d of %2$d'),
                        $external,
                        $total
                    )) ?></span>
                </div>
            </div>
        </div>

    <?php elseif ($module['state'] === 'timeout'): ?>

        <?php
        $limit = $module['kind'] === 'cortex'
            ? $enrichment['cortex_timeout']
            : $enrichment['timeout'];
        ?>
        <div class="p-3 d-flex flex-column gap-3">
            <div class="vp-e-partial">
                <i class="fas fa-hourglass-half"></i>
                <span>
                    <strong><?= h(sprintf(
                        __('%1$s gave up at %2$d s.'),
                        $module['name'],
                        $limit
                    )) ?></strong>
                    <?= sprintf(
                        __(
                            'That limit is %s, not a property of the'
                            . ' module — it stopped waiting, which is'
                            . ' not the same as the module failing.'
                        ),
                        '<span class="font-monospace">'
                            . ($module['kind'] === 'cortex'
                                ? 'Plugin.Cortex_timeout'
                                : 'Plugin.Enrichment_timeout')
                            . '</span>'
                    ) ?>
                </span>
            </div>
            <p class="small text-muted mb-0">
                <?= h(sprintf(
                    __n(
                        'Every other row is untouched: %1$d module'
                            . ' answered and %2$d elements are waiting'
                            . ' for review.',
                        'Every other row is untouched: %1$d modules'
                            . ' answered and %2$d elements are waiting'
                            . ' for review.',
                        $merged['modules']
                    ),
                    $merged['modules'],
                    $enrichment['pending']
                )) ?>
            </p>
        </div>

    <?php elseif ($module['state'] === 'none'): ?>

        <div class="p-3 d-flex flex-column gap-3">
            <div class="vp-e-partial vp-e-quiet">
                <i class="fas fa-circle-minus"></i>
                <span>
                    <strong><?= h(__(
                        'This module answered with nothing.'
                    )) ?></strong>
                    <?= h(sprintf(
                        __(
                            'It was queried on %1$s and came back in'
                            . ' %2$s s with no elements. That is an'
                            . ' answer.'
                        ),
                        $module['ran_at'],
                        $module['took']
                    )) ?>
                </span>
            </div>
            <p class="small text-muted mb-0">
                <?= h(__(
                    'Different from a module nobody has run, which has'
                    . ' no answer at all, and different from one that'
                    . ' timed out, which was never allowed to finish.'
                    . ' All three are in the rail, and none of them is'
                    . ' the same claim.'
                )) ?>
            </p>
        </div>

    <?php else: ?>

        <?php
        /*
         * Never run — either never at all, or not in the last run. The
         * pane is the per-module version of the brief: what this one
         * would cost, and what it has said before if anything.
         */
        ?>
        <div class="p-3 d-flex flex-column gap-3">
            <?php if ($module['last_ran_at'] !== null): ?>
                <div class="vp-e-partial vp-e-quiet">
                    <i class="fas fa-clock-rotate-left"></i>
                    <span>
                        <strong><?= h(__(
                            'The last run did not include this module.'
                        )) ?></strong>
                        <?= h(sprintf(
                            __n(
                                'It last answered on %1$s, %2$d day'
                                    . ' ago.',
                                'It last answered on %1$s, %2$d days'
                                    . ' ago.',
                                (int)$module['stale_days']
                            ),
                            $module['last_ran_at'],
                            (int)$module['stale_days']
                        )) ?>
                    </span>
                </div>
            <?php else: ?>
                <div class="vp-e-partial vp-e-quiet">
                    <i class="far fa-circle"></i>
                    <span>
                        <strong><?= h(sprintf(
                            __('%s has never been run against this'
                                . ' value.'),
                            $module['name']
                        )) ?></strong>
                        <?= h(__(
                            'Nothing has been sent to it, so there is'
                            . ' nothing here to be out of date.'
                        )) ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="vp-e-cold-ledger">
                <div class="vp-subhead">
                    <?= h(__('What running it would cost')) ?>
                </div>
                <div class="vp-e-ledger-row">
                    <span><?= h(__('Spend API quota')) ?></span>
                    <span class="vp-e-ledger-n">
                        <?= h(empty($module['cost']['quota'])
                            ? __('No') : __('Yes')) ?>
                    </span>
                </div>
                <div class="vp-e-ledger-row">
                    <span><?= h(__('Query a third party')) ?></span>
                    <span class="vp-e-ledger-n">
                        <?= h(empty($module['cost']['external'])
                            ? __('No') : __('Yes')) ?>
                    </span>
                </div>
                <div class="vp-e-ledger-row">
                    <span><?= h(__('Waits at most')) ?></span>
                    <span class="vp-e-ledger-n">
                        <?= h(sprintf(
                            __('%d s'),
                            $module['kind'] === 'cortex'
                                ? $enrichment['cortex_timeout']
                                : $enrichment['timeout']
                        )) ?>
                    </span>
                </div>
            </div>

            <?php if (empty($enrichment['service']['reachable'])): ?>
                <p class="small text-muted mb-0">
                    <?= h(__(
                        'The module service is not answering, so this'
                        . ' module could not have run even if it had'
                        . ' been asked to.'
                    )) ?>
                </p>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>
