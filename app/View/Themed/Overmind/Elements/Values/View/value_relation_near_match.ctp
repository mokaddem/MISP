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

/**
 * Closeness as a share of whatever the row's scale is.
 *
 * A prefix over the address width for a network block — 32 bits for
 * IPv4 and 128 for IPv6, which the fixture could hardcode as 32 and
 * live data cannot — and the score itself for ssdeep, which is already
 * a percentage. `Similarity ≥` filters on this number, so the control
 * and the bar cannot disagree whichever engine wrote the row.
 *
 * @param array $row
 * @return int
 */
$closeness = function ($row) {
    $width = empty($row['width']) ? 32 : (int)$row['width'];
    return (int)round(((int)$row['prefix'] / $width) * 100);
};

/**
 * A matched value short enough for the column and still readable as
 * itself.
 *
 * `.vp-rel-cell` clips at 18rem and puts the ellipsis at the end, which
 * is right for a network block and useless for an ssdeep hash: the
 * hashes in a family share a prefix — sharing one is what makes them a
 * family — so every row clipped to the same visible string. The middle
 * goes instead, and the whole value is on the cell's title either way.
 *
 * @param string $label A CIDR block, or an ssdeep hash
 * @return string
 */
$shorten = function ($label) {
    if (strlen($label) <= 40) {
        return $label;
    }
    return substr($label, 0, 24) . '…' . substr($label, -12);
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
     data-vp-list
     data-vp-rel-summary="near"
     data-vp-rel-count="<?= h(number_format($near['matches'])) ?>"
     <?php if (empty($near['engines'])): ?>
         data-vp-rel-note="<?= h(__('no engine applies')) ?>"
     <?php endif; ?>>

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

        <?php
        /**
         * One engine's rows. Two engines produce them and they differ
         * only in what the first two columns are called, so the table
         * is written once — a second copy is a second place for the
         * `Similarity ≥` control to stop agreeing with the bar.
         *
         * @param array $engine
         * @param string $subject Heading for the matched value
         * @param string $scale Heading for the closeness column
         * @param string|null $extra Heading for the grounding column
         * @return void
         */
        $engineTable = function ($engine, $subject, $scale, $extra)
            use ($view, $baseurl, $closeness, $shorten, $distributionBadge)
        {
            ?>
            <div class="table-responsive" data-vp-list-rows>
                <table class="table table-sm table-hover vp-table
                              align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= h($subject) ?></th>
                            <th class="vp-rel-num"><?= h($scale) ?></th>
                            <?php if ($extra !== null): ?>
                                <th><?= h($extra) ?></th>
                            <?php endif; ?>
                            <th><?= __('Event') ?></th>
                            <th><?= __('Reported by') ?></th>
                            <th><?= __('Distribution') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($engine['rows'] as $row): ?>
                            <?php
                            /*
                             * Closeness is grounded twice: the bar is a
                             * defensible share and the column beside it
                             * is the fact. A /16 is "50%" only in the
                             * sense that half the bits are fixed;
                             * 65,536 addresses is what that means.
                             */
                            $share = $closeness($row);
                            ?>
                            <tr class="vp-rel-stripe vp-rel-k-near"
                                data-vp-num="closeness:<?= h($share) ?>">
                                <td class="font-monospace">
                                    <span class="vp-rel-cell"
                                          title="<?= h($row['block']) ?>"><?=
                                        h($shorten($row['block'])) ?></span>
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
                                            <?= $row['addresses'] === null
                                                ? h($row['prefix'] . '%')
                                                : h('/' . $row['prefix']) ?>
                                        </span>
                                    </span>
                                </td>
                                <?php if ($extra !== null): ?>
                                    <td class="font-monospace small
                                               text-nowrap">
                                        <?php
                                        /*
                                         * A live row arrives formatted,
                                         * because a /8 of IPv6 is 2^120
                                         * and no integer here holds it.
                                         * The fixture's rows are plain
                                         * integers and still need the
                                         * separators.
                                         */
                                        ?>
                                        <?= h(is_int($row['addresses'])
                                            ? number_format($row['addresses'])
                                            : $row['addresses']) ?>
                                    </td>
                                <?php endif; ?>
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
            <?php
        };
        ?>

        <?php if (isset($engines['cidr'])): ?>

            <?php $cidr = $engines['cidr']; ?>
            <div class="vp-rel-engine vp-rel-engine-<?= $cidr['state']
                === 'active' ? 'on' : 'off' ?>">
                <span class="vp-rel-engine-state">
                    <?= $cidr['state'] === 'active'
                        ? __('Active')
                        : __('Not applicable') ?>
                </span>
                <div class="vp-min-w-0 flex-grow-1">
                    <div class="fw-semibold small vp-rel-engine-name">
                        <?= __('CIDR containment') ?>
                    </div>
                    <div class="small text-muted mt-1">
                        <?php if ($cidr['state'] !== 'active'): ?>
                            <?= sprintf(
                                __(
                                    'CIDR containment runs for %1$s'
                                    . ' attributes. This value is %2$s,'
                                    . ' so the engine never runs for it.'
                                ),
                                '<span class="font-monospace">ip-src /'
                                    . ' ip-dst</span>',
                                '<span class="font-monospace">'
                                    . h($primaryType) . '</span>'
                            ) ?>
                        <?php else: ?>
                            <?= h(__n(
                                '%d network-block attribute contains this'
                                . ' address.',
                                '%d network-block attributes contain this'
                                . ' address.',
                                count($cidr['rows']),
                                count($cidr['rows'])
                            )) ?>
                            <?= __('Re-derived at render time from the'
                                . ' same CIDR list the engine walks —'
                                . ' the stored correlation row does not'
                                . ' record which engine wrote it.') ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($cidr['state'] === 'active'
                && !empty($cidr['rows'])
            ): ?>
                <?php $engineTable(
                    $cidr,
                    __('Containing block'),
                    __('Closeness'),
                    __('Addresses')
                ) ?>

                <div class="p-3 d-none" data-vp-list-empty>
                    <div class="vp-empty vp-empty-inline">
                        <i class="fas fa-filter"></i>
                        <span>
                            <?= __('No containing block is that close.'
                                . ' The widest block here is the least'
                                . ' useful and the first to go.') ?>
                        </span>
                    </div>
                </div>
            <?php elseif ($cidr['state'] === 'active'): ?>
                <div class="px-3 pb-3">
                    <div class="vp-empty vp-empty-inline">
                        <i class="fas fa-code-compare"></i>
                        <span>
                            <?= __('No network block on this instance'
                                . ' contains this address. The engine'
                                . ' applies; it found nothing.') ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <?php if (isset($engines['ssdeep'])): ?>

            <?php $ssdeep = $engines['ssdeep']; ?>
            <div class="vp-rel-engine vp-rel-engine-<?= $ssdeep['state']
                === 'active' ? 'on' : 'off' ?>">
                <span class="vp-rel-engine-state">
                    <?php if ($ssdeep['state'] === 'active'): ?>
                        <?= __('Active') ?>
                    <?php elseif ($ssdeep['state'] === 'unavailable'): ?>
                        <?= __('Cannot run') ?>
                    <?php else: ?>
                        <?= __('Not applicable') ?>
                    <?php endif; ?>
                </span>
                <div class="vp-min-w-0 flex-grow-1">
                    <div class="fw-semibold small vp-rel-engine-name">
                        <?= __('ssdeep fuzzy similarity') ?>
                    </div>
                    <div class="small text-muted mt-1">
                        <?php if ($ssdeep['state'] === 'not_applicable'): ?>
                            <?= sprintf(
                                __(
                                    'ssdeep compares %1$s attributes'
                                    . ' against each other. This value is'
                                    . ' %2$s, so the engine never runs'
                                    . ' for it. Where it does apply the'
                                    . ' score renders as %3$s, recomputed'
                                    . ' per row — MISP stores the'
                                    . ' threshold test, not the number.'
                                ),
                                '<span class="font-monospace">ssdeep</span>',
                                '<span class="font-monospace">'
                                    . h($primaryType) . '</span>',
                                '<span class="badge vp-rel-score">'
                                    . h(__('ssdeep 92%')) . '</span>'
                            ) ?>
                        <?php elseif ($ssdeep['state'] === 'unavailable'): ?>
                            <?php
                            /*
                             * A fourth state the brief does not have,
                             * and neither *not applicable* nor *no
                             * engine* would be true of it. MISP ships
                             * this engine and it applies to this value;
                             * the PHP extension it is written against
                             * is simply not loaded here.
                             */
                            ?>
                            <?= sprintf(
                                __(
                                    'This value %1$s an %2$s hash, so the'
                                    . ' engine applies — but %3$s is not'
                                    . ' loaded on this instance, so'
                                    . ' nothing can compare it. The'
                                    . ' engine is present and inert,'
                                    . ' which is neither *not applicable*'
                                    . ' nor *missing from MISP*.'
                                ),
                                '<strong>' . h(__('is')) . '</strong>',
                                '<span class="font-monospace">ssdeep</span>',
                                '<span class="font-monospace">'
                                    . 'ssdeep_fuzzy_compare()</span>'
                            ) ?>
                        <?php else: ?>
                            <?= sprintf(
                                __(
                                    'Compared against every other %1$s'
                                    . ' attribute you can see, %2$s'
                                    . ' cleared the threshold of %3$d.'
                                    . ' The score is recomputed here —'
                                    . ' MISP keeps the threshold test,'
                                    . ' not the number.'
                                ),
                                '<span class="font-monospace">ssdeep</span>',
                                '<strong>' . h(__n(
                                    '%d pair',
                                    '%d pairs',
                                    count($ssdeep['rows']),
                                    count($ssdeep['rows'])
                                )) . '</strong>',
                                $near['threshold']
                            ) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($ssdeep['state'] === 'active'
                && !empty($ssdeep['rows'])
            ): ?>
                <?php $engineTable(
                    $ssdeep,
                    __('Matched hash'),
                    __('Similarity'),
                    null
                ) ?>
            <?php endif; ?>

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
