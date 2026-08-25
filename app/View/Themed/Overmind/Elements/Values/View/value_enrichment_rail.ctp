<?php
/**
 * The rail: one row per module valid for this value's type, whatever
 * state it is in, grouped by whether the last run included it.
 *
 * The row is the state. Every wording here is a different claim and
 * the four are deliberately not interchangeable: a module that timed
 * out was asked and did not finish; one that was queried and answered
 * with nothing did finish; one the last run left out has an older
 * answer still standing; and one nobody has ever run has no answer at
 * all and is not stale, it is unused.
 *
 * A plain partial, not an endpoint.
 *
 * @var array $enrichment
 * @var string $selected  Pane key the tab opens on
 * @var string $noRun     Title for every control that would query
 */
$modules = $enrichment['modules'];
$merged = $enrichment['merged'];
$service = $enrichment['service'];

/*
 * Three groups, and a module is in exactly one of them. The header
 * carries the group's size because that is the number a reader wants
 * before deciding whether the rail is worth scrolling.
 */
$groups = array(
    'ran' => array(
        'label' => $enrichment['last_run'] === null
            ? __('Last run')
            : sprintf(__('Ran %s'), $enrichment['last_run']),
        'rows' => array(),
    ),
    'before' => array('label' => __('Not in the last run'),
        'rows' => array()),
    'never' => array('label' => __('Never run'), 'rows' => array()),
);
foreach ($modules as $module) {
    if ($module['ran_at'] !== null) {
        $groups['ran']['rows'][] = $module;
    } elseif ($module['last_ran_at'] !== null) {
        $groups['before']['rows'][] = $module;
    } else {
        $groups['never']['rows'][] = $module;
    }
}

/**
 * The state, in words. This is the design.
 *
 * @param array $module
 * @param array $enrichment
 * @return string Markup
 */
$subLine = function (array $module) use ($enrichment) {
    if ($module['state'] === 'ok') {
        $elements = h(sprintf(
            __n('%d element', '%d elements', $module['elements']),
            $module['elements']
        ));
        if ($module['new'] < 1) {
            return $elements;
        }
        return $elements . ' &middot; ' . h(sprintf(
            __('%d new'),
            $module['new']
        ));
    }
    if ($module['state'] === 'timeout') {
        $limit = $module['kind'] === 'cortex'
            ? $enrichment['cortex_timeout']
            : $enrichment['timeout'];
        return h(sprintf(__('Gave up at %d s'), $limit));
    }
    if ($module['state'] === 'none') {
        return h(__('Queried, nothing back'));
    }
    if ($module['last_ran_at'] !== null) {
        return h(sprintf(__('Last run %s'), $module['last_ran_at']));
    }
    /*
     * Never run, and never run before either. The sub-line is then the
     * only argument the row has to make, so it is the cost of making
     * it: quota is money, a third party is disclosure, and they are
     * two different prices.
     */
    $chips = '';
    if (!empty($module['cost']['quota'])) {
        $chips .= '<span class="vp-e-cost vp-e-cost-quota">'
            . '<i class="fas fa-coins"></i>'
            . h(__('Spends quota')) . '</span>';
    }
    if (!empty($module['cost']['external'])) {
        $chips .= '<span class="vp-e-cost vp-e-cost-out">'
            . '<i class="fas fa-satellite-dish"></i>'
            . h(__('Third party')) . '</span>';
    }
    return $chips;
};

/**
 * @param array $module
 * @return string Markup, or '' where a dot would be a claim the row
 *                cannot make
 */
$dot = function (array $module) {
    $states = array(
        'ok' => 'vp-e-dot-ok',
        'timeout' => 'vp-e-dot-timeout',
        'none' => 'vp-e-dot-none',
    );
    if (!isset($states[$module['state']])) {
        // Never run has no outcome to colour. Its staleness chip says
        // so instead, which is a different sentence from the hollow
        // dot a silent module wears.
        return '';
    }
    $titles = array(
        'ok' => __('Answered'),
        'timeout' => __('Timed out'),
        'none' => __('Answered with nothing'),
    );
    return '<span class="vp-e-dot ' . $states[$module['state']]
        . '" title="' . h($titles[$module['state']]) . '"></span>';
};

/**
 * @param array $module
 * @return string Markup
 */
$stale = function (array $module) {
    if ($module['last_ran_at'] === null) {
        return '<span class="vp-e-stale vp-e-stale-never" title="'
            . h(__(
                'Never run against this value. That is not staleness —'
                . ' there is no answer to have gone out of date.'
            )) . '">' . h(__('Never')) . '</span>';
    }
    $days = (int)$module['stale_days'];
    $class = $days > 7 ? 'vp-e-stale-stale' : 'vp-e-stale-fresh';
    $label = $days < 1
        ? __('Today')
        : sprintf(__('%d d'), $days);
    return '<span class="vp-e-stale ' . $class . '" title="'
        . h(sprintf(
            __('Last answered %s'),
            $module['last_ran_at']
        )) . '">' . h($label) . '</span>';
};
?>
<div class="vp-e-rail">

    <div class="d-flex align-items-center gap-2 p-2 border-bottom">
        <input class="form-check-input mt-0" type="checkbox"
               data-vp-e-select-all
               id="vp-e-all-<?= h(md5($enrichment['type'] . 'all')) ?>"
               aria-label="<?= h(__('Select every module')) ?>">
        <label class="small fw-semibold mb-0"
               for="vp-e-all-<?= h(md5($enrichment['type'] . 'all')) ?>">
            <?= h(__('Select all')) ?>
        </label>
        <span class="ms-auto small text-muted vp-e-num">
            <?= sprintf(
                __('%1$s of %2$s selected'),
                '<span data-vp-e-picked>0</span>',
                h(count($modules))
            ) ?>
        </span>
    </div>

    <div class="vp-e-railscroll">

        <?php
        /*
         * The one addition E2 makes to the direction it came from. A
         * rail costs the reader cross-module reading — three modules
         * returning the same value is the thing you most want to see
         * and the thing one-module-at-a-time hides — and this row is
         * what buys it back.
         */
        ?>
        <div class="vp-e-railrow vp-e-railrow-all
                    <?= $selected === '__all' ? 'vp-e-railrow-on' : '' ?>"
             data-vp-e-row="__all">
            <button type="button" class="vp-e-railbody"
                    data-vp-e-pick="__all"
                    aria-pressed="<?= $selected === '__all'
                        ? 'true' : 'false' ?>">
                <span class="vp-e-railrow-name">
                    <?= h($merged['elements'] > 0
                        ? __('All results')
                        : __('Nothing queried yet')) ?>
                </span>
                <span class="vp-e-railrow-sub">
                    <?php if ($merged['elements'] > 0): ?>
                        <span>
                            <?= h(sprintf(
                                __n(
                                    '%1$d element across %2$d module',
                                    '%1$d elements across %2$d modules',
                                    $merged['elements']
                                ),
                                $merged['elements'],
                                $merged['modules']
                            )) ?>
                            <?php if ($merged['new'] > 0): ?>
                                &middot;
                                <?= h(sprintf(
                                    __('%d new'),
                                    $merged['new']
                                )) ?>
                            <?php endif; ?>
                        </span>
                    <?php else: ?>
                        <span>
                            <?= h(sprintf(
                                __n(
                                    '%d module valid, none has been run',
                                    '%d modules valid, none has been run',
                                    count($modules)
                                ),
                                count($modules)
                            )) ?>
                        </span>
                    <?php endif; ?>
                </span>
            </button>
            <i class="fas fa-chevron-right text-muted"></i>
        </div>

        <?php foreach ($groups as $key => $group): ?>
            <?php if (empty($group['rows'])) {
                // A group of nothing is not a heading. The rail says
                // which states this value is in, not which states
                // exist.
                continue;
            } ?>

            <div class="vp-e-railgroup">
                <?= h(sprintf(
                    '%s (%d)',
                    $group['label'],
                    count($group['rows'])
                )) ?>
            </div>

            <?php foreach ($group['rows'] as $module): ?>
                <?php
                $boxId = 'vp-e-m-' . md5($module['name']);
                $on = $selected === $module['name'];
                ?>
                <div class="vp-e-railrow<?= $on
                        ? ' vp-e-railrow-on' : '' ?>"
                     data-vp-e-row="<?= h($module['name']) ?>">
                    <input class="form-check-input mt-0 flex-shrink-0"
                           type="checkbox"
                           id="<?= h($boxId) ?>"
                           data-vp-e-select
                           value="<?= h($module['name']) ?>"
                           data-vp-e-quota="<?= empty($module['cost']['quota'])
                               ? '0' : '1' ?>"
                           data-vp-e-external="<?=
                               empty($module['cost']['external'])
                                   ? '0' : '1' ?>"
                           aria-label="<?= h(sprintf(
                               __('Select %s to run'),
                               $module['name']
                           )) ?>">
                    <?php if ($module['kind'] === 'cortex'): ?>
                        <?php
                        /*
                         * Cortex is a second list, its own service on
                         * its own port with its own timeout. Merging
                         * it into one rail is a decision this design
                         * makes, and the chip is where it says so.
                         */
                        ?>
                        <span class="vp-e-kind vp-e-kind-cortex"
                              title="<?= h(sprintf(__(
                                  'A Cortex analyser, not a MISP'
                                  . ' module: a separate service with'
                                  . ' its own %d s timeout.'
                              ), $enrichment['cortex_timeout'])) ?>">
                            <?= h(__('Cortex')) ?>
                        </span>
                    <?php endif; ?>
                    <button type="button" class="vp-e-railbody"
                            data-vp-e-pick="<?= h($module['name']) ?>"
                            aria-pressed="<?= $on ? 'true' : 'false' ?>">
                        <span class="vp-e-railrow-name">
                            <?= h($module['name']) ?>
                        </span>
                        <span class="vp-e-railrow-sub">
                            <?= $subLine($module) ?>
                        </span>
                    </button>
                    <?= $stale($module) ?>
                    <?= $dot($module) ?>
                </div>
            <?php endforeach; ?>

        <?php endforeach; ?>

    </div>

    <?php
    /*
     * The tray. E1's full staging panel — the one that prices a whole
     * run in quota and third-party exposure before you commit — is
     * deferred, but the argument it made belongs beside the button
     * that would spend the money, so the compact form of it lives
     * here: what this selection costs, and what it would cost to
     * press the button.
     *
     * Nothing is selected on arrival. A run spends money and tells an
     * adversary you are looking; staging three modules on the
     * reader's behalf is the one thing this tab should not do for
     * them.
     */
    ?>
    <div class="vp-e-tray">

        <div class="vp-e-tray-cost">
            <span class="vp-e-cost" data-vp-e-cost-none>
                <i class="fas fa-circle-minus"></i>
                <?= h(__('Nothing selected — nothing will be sent')) ?>
            </span>
            <span class="vp-e-cost vp-e-cost-quota d-none"
                  data-vp-e-cost-quota
                  title="<?= h(__(
                      'These modules consume a paid or rate-limited API'
                      . ' allowance.'
                  )) ?>">
                <i class="fas fa-coins"></i>
                <?= sprintf(
                    __('%s spend quota'),
                    '<span data-vp-e-quota-n>0</span>'
                ) ?>
            </span>
            <span class="vp-e-cost vp-e-cost-out d-none"
                  data-vp-e-cost-out
                  title="<?= h(__(
                      'These modules send this value outside your'
                      . ' instance, which tells whoever runs them that'
                      . ' you are looking at it.'
                  )) ?>">
                <i class="fas fa-satellite-dish"></i>
                <?= sprintf(
                    __('%s query a third party'),
                    '<span data-vp-e-ext-n>0</span>'
                ) ?>
            </span>
        </div>

        <button type="button"
                class="btn btn-sm btn-primary disabled
                       d-inline-flex align-items-center gap-2 fw-semibold"
                disabled
                title="<?= h(empty($service['reachable'])
                    ? __(
                        'Disabled in this pass — and the module service'
                        . ' is not answering, so there is nothing to'
                        . ' run against.'
                    )
                    : $noRun) ?>">
            <i class="fas fa-wand-magic-sparkles"></i>
            <?= sprintf(
                __('Run %s selected'),
                '<span data-vp-e-runcount class="vp-e-num">0</span>'
            ) ?>
        </button>

        <div>
            <?php if (!empty($service['reachable'])): ?>
                <span class="vp-e-svc"
                      title="<?= h(sprintf(
                          __('Checked %s'),
                          $service['checked']
                      )) ?>">
                    <span class="vp-e-dot vp-e-dot-ok"></span>
                    <?= h(__('Module service reachable')) ?>
                </span>
            <?php else: ?>
                <span class="vp-e-svc vp-e-svc-down"
                      title="<?= h(sprintf(
                          __('Checked %s'),
                          $service['checked']
                      )) ?>">
                    <span class="vp-e-dot vp-e-dot-err"></span>
                    <?= h(__('Module service unreachable')) ?>
                </span>
                <?php if (!empty($service['note'])): ?>
                    <div class="small text-muted mt-1">
                        <?= h($service['note']) ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>

</div>
