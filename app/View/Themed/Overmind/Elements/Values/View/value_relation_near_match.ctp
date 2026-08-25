<?php
/**
 * Section two: values that are close to this one without being it.
 *
 * The sentence this whole section exists to prevent a reader from
 * assuming is *these two values are the same*. They are not, and the
 * panel says so above the rows rather than trusting a dashed border to
 * carry the argument.
 *
 * Three engine states appear here and all three are different:
 *
 *   active           the engine runs for this type and found rows.
 *   not applicable   the engine exists and runs, but not on a value of
 *                    this type. Nothing is missing, nothing is hidden.
 *   no engine        MISP has no such engine at all. Not empty, not
 *                    inapplicable — absent, and drawn as a stub so the
 *                    gap is visible rather than quietly dropped.
 *
 * Nothing here reads a provenance column, because there is none: the
 * correlation table stores `value`, two event ids, two attribute ids,
 * org and distribution, and exact matches and CIDR containment are
 * written into it together. Every row below is re-derived at render
 * time, and says so.
 *
 * Lazily loaded from ValuesController::viewRelationNearMatch.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$profile = $valueProfile;
$near = $profile['relationships']['near'];
$view = $this;

/*
 * The type the engines are asked about. Every demo value is an address,
 * but an unknown value has no attribute anywhere on the instance and so
 * has no type for an engine to decline — which is a fourth sentence
 * again, and not the same as "the engine does not apply".
 */
$primaryType = null;
if (!empty($profile['types'])) {
    $primaryType = $profile['types'][0]['type'];
}

$engines = array();
foreach ($near['engines'] as $engine) {
    $engines[$engine['id']] = $engine;
}

$maxPrefix = 32;

/**
 * @param array $row
 * @return string
 */
$distributionBadge = function ($row) use ($view) {
    return $view->element(
        'genericElementsBS5/Badges/distribution',
        array('distribution' => (int)$row['distribution'], 'full' => false)
    );
};

ob_start();
?>
    <?php if (!empty($near['matches'])): ?>
        <label class="small text-muted" for="vp-rel-similarity">
            <?= __('Similarity') ?> &ge;
        </label>
        <div class="input-group input-group-sm" style="width: 6.5rem">
            <input type="number" class="form-control" id="vp-rel-similarity"
                   min="0" max="100" step="1" value="0"
                   data-vp-filter-min="closeness">
            <span class="input-group-text">%</span>
        </div>
    <?php endif; ?>
<?php
$headerExtra = ob_get_clean();
if (empty($near['matches'])) {
    $headerExtra = null;
}
?>
<div class="card shadow-sm mb-3 vp-panel vp-rel-k-near"
     style="--vp-panel-color: var(--vp-rel-near);"
     data-vp-list>

    <?php
    ob_start();
    ?>
        <span class="vp-rel-tag me-1">
            <i class="fas fa-code-compare"></i><?= h(__('Near-match')) ?>
        </span>
        <?php if (empty($near['engines'])): ?>
            <?= h(__('No engine has been asked')) ?>
        <?php else: ?>
            <?= h(sprintf(
                __('%1$s from %2$s'),
                __n('%d match', '%d matches', $near['matches'],
                    $near['matches']),
                __n('%d engine', '%d engines', $near['engines_active'],
                    $near['engines_active'])
            )) ?>
            &nbsp;·&nbsp;
            <?= h(__n(
                '%d engine does not apply here',
                '%d engines do not apply here',
                $near['engines_idle'],
                $near['engines_idle']
            )) ?>
        <?php endif; ?>
        &nbsp;·&nbsp;
        <span class="vp-rel-prov"><i class="fas fa-gauge"></i><?=
            h(__('Machine-derived')) ?></span>
    <?php
    $headerSub = ob_get_clean();
    ?>

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Near-matches'),
        'panelIcon' => 'fas fa-code-compare',
        'panelColor' => 'var(--vp-rel-near)',
        'panelSub' => $headerSub,
        'panelExtra' => $headerExtra,
    )) ?>

    <?php if (empty($near['engines'])): ?>

        <div class="p-3">
            <div class="vp-empty">
                <i class="fas fa-code-compare"></i>
                <span>
                    <?php if ($primaryType === null): ?>
                        <?= __('This value has no attribute on the'
                            . ' instance, so there is nothing for a'
                            . ' near-match engine to compare.') ?>
                    <?php else: ?>
                        <?= h(sprintf(
                            __('No near-match engine applies to %s.'),
                            $primaryType
                        )) ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

    <?php else: ?>

        <div class="vp-rel-cap">
            <i class="fas fa-triangle-exclamation"></i>
            <span>
                <?= sprintf(
                    __(
                        'A near-match is %s. Every row names the engine'
                        . ' that produced it and how close the match is;'
                        . ' a row here never means the two values are'
                        . ' the same.'
                    ),
                    '<strong>' . h(__('not equality')) . '</strong>'
                ) ?>
            </span>
        </div>

        <?php if (isset($engines['cidr'])): ?>

            <div class="vp-rel-engine vp-rel-engine-on">
                <span class="vp-rel-engine-state"><?= __('Active') ?></span>
                <div class="vp-min-w-0 flex-grow-1">
                    <div class="fw-semibold small vp-rel-engine-name">
                        <?= __('CIDR containment') ?>
                    </div>
                    <div class="small text-muted mt-1">
                        <?= h(__n(
                            '%d network-block attribute contains this'
                            . ' address.',
                            '%d network-block attributes contain this'
                            . ' address.',
                            count($engines['cidr']['rows']),
                            count($engines['cidr']['rows'])
                        )) ?>
                        <?= __('Re-derived at render time from the CIDR'
                            . ' list — the stored correlation row does'
                            . ' not record which engine wrote it.') ?>
                    </div>
                </div>
            </div>

            <div class="table-responsive" data-vp-list-rows>
                <table class="table table-sm table-hover vp-table
                              align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= __('Containing block') ?></th>
                            <th class="vp-rel-num"><?= __('Closeness') ?></th>
                            <th><?= __('Addresses') ?></th>
                            <th><?= __('Event') ?></th>
                            <th><?= __('Reported by') ?></th>
                            <th><?= __('Distribution') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($engines['cidr']['rows'] as $row): ?>
                            <?php
                            /*
                             * Closeness is the prefix as a share of the
                             * address width, and the address count is
                             * printed beside it so the bar is grounded
                             * in something a reader can check. A /16 is
                             * "50%" only in the sense that half the bits
                             * are fixed; 65,536 addresses is the fact.
                             */
                            $share = round(
                                ($row['prefix'] / $maxPrefix) * 100
                            );
                            ?>
                            <tr class="vp-rel-stripe vp-rel-k-near"
                                data-vp-num="closeness:<?= h($share) ?>">
                                <td class="font-monospace text-nowrap">
                                    <?= h($row['block']) ?>
                                </td>
                                <td>
                                    <span class="vp-rel-bar"
                                          style="--vp-seg-color:
                                                 var(--vp-rel-near);">
                                        <span class="vp-weight-track">
                                            <span class="vp-weight-fill"
                                                  style="width: <?=
                                                      h($share) ?>%;"></span>
                                        </span>
                                        <span class="vp-rel-bar-read">
                                            /<?= h($row['prefix']) ?>
                                        </span>
                                    </span>
                                </td>
                                <td class="font-monospace small text-nowrap">
                                    <?= h(number_format($row['addresses'])) ?>
                                </td>
                                <td class="text-nowrap">
                                    <a href="<?= $baseurl ?>/events/view2/<?=
                                        h($row['event']) ?>"
                                       class="font-monospace small">
                                        #<?= h($row['event']) ?>
                                    </a>
                                </td>
                                <td class="text-nowrap">
                                    <?= h($row['org']) ?>
                                </td>
                                <td><?= $distributionBadge($row) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-3 d-none" data-vp-list-empty>
                <div class="vp-empty vp-empty-inline">
                    <i class="fas fa-filter"></i>
                    <span>
                        <?= __('No containing block is that close. The'
                            . ' widest block here is the least useful'
                            . ' and the first to go.') ?>
                    </span>
                </div>
            </div>

        <?php endif; ?>

        <?php if (isset($engines['ssdeep'])): ?>

            <div class="vp-rel-engine vp-rel-engine-off">
                <span class="vp-rel-engine-state">
                    <?= __('Not applicable') ?>
                </span>
                <div class="vp-min-w-0 flex-grow-1">
                    <div class="fw-semibold small vp-rel-engine-name">
                        <?= __('ssdeep fuzzy similarity') ?>
                    </div>
                    <div class="small text-muted mt-1">
                        <?= sprintf(
                            __(
                                'ssdeep compares %1$s attributes against'
                                . ' each other. This value is %2$s, so'
                                . ' the engine never runs for it. Where'
                                . ' it does apply the score renders as'
                                . ' %3$s, recomputed per row — MISP'
                                . ' stores the threshold test, not the'
                                . ' number.'
                            ),
                            '<span class="font-monospace">ssdeep</span>',
                            '<span class="font-monospace">'
                                . h($primaryType) . '</span>',
                            '<span class="badge vp-rel-score">'
                                . h(__('ssdeep 92%')) . '</span>'
                        ) ?>
                    </div>
                </div>
            </div>

        <?php endif; ?>

        <?php if (isset($engines['tld'])): ?>

            <div class="p-3">
                <div class="vp-panel-stub">
                    <span class="vp-panel-stub-badge">
                        <i class="fas fa-code"></i>
                        <?= __('No engine in MISP') ?>
                    </span>
                    <div class="fw-semibold small mt-2">
                        <?= __('Domain / TLD tree') ?>
                    </div>
                    <p class="vp-panel-stub-note">
                        <?= __('Nothing in MISP computes a parent-domain,'
                            . ' registrable-domain or public-suffix'
                            . ' relation — there is no public-suffix'
                            . ' list, no table and no code path. Drawn so'
                            . ' the gap in the brief is visible rather'
                            . ' than quietly dropped, and deliberately'
                            . ' unlike the two states above it: this one'
                            . ' is not empty and not inapplicable, it is'
                            . ' absent.') ?>
                    </p>
                </div>
            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>
