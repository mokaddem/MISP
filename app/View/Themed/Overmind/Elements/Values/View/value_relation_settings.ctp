<?php
/**
 * The rail's second card: what MISP is configured to count.
 *
 * Every number in the first section is conditional on settings the
 * reader cannot see from the page, and a count whose rules are
 * invisible is a count nobody should trust. This card is where those
 * rules are written down — which is also why it is the one panel on the
 * tab that still renders for a value with nothing at all: what MISP
 * counts is true whether or not this value has anything to count.
 *
 * The breakdown at the foot is the tab's arithmetic, stated rather than
 * left to be inferred: the correlation total is co-occurrence plus
 * near-match, and the analyst claims are counted apart and never added
 * to it. Three notions, never summed into one strength.
 *
 * Lazily loaded from ValuesController::viewRelationSettings.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$profile = $valueProfile;
$relations = $profile['relationships'];
$settings = $relations['settings'];
$summary = $relations['summary'];
$co = $relations['cooccurrence'];

/*
 * The value is past the limit when MISP recorded it in
 * `over_correlating_values` rather than correlating it. That is a
 * property of this value against this setting, not of the setting.
 */
$suppressed = !empty($co['suppressed']);

$split = array(
    array(
        'label' => __('Co-occurrence'),
        'colour' => 'var(--vp-rel-co)',
        'count' => $summary['cooccurrence'],
    ),
    array(
        'label' => __('Near-match'),
        'colour' => 'var(--vp-rel-near)',
        'count' => $summary['near'],
    ),
    array(
        'label' => __('Asserted'),
        'colour' => 'var(--vp-rel-human)',
        'count' => $summary['asserted'],
    ),
);
$maxSplit = 0;
foreach ($split as $part) {
    $maxSplit = max($maxSplit, (int)$part['count']);
}
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--bs-secondary-color);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('What is counted'),
        'panelIcon' => 'fas fa-sliders',
        'panelColor' => 'var(--bs-secondary-color)',
        'panelSub' => h(__('The engine settings this tab depends on')),
    )) ?>

    <div class="p-3 d-flex flex-column gap-2">

        <div class="vp-fact-line<?= $suppressed
            ? ' vp-fact-line-warn' : '' ?>">
            <i class="fas fa-triangle-exclamation"></i>
            <div>
                <span class="fw-semibold">
                    <?= h(sprintf(
                        __('Correlation limit %d.'),
                        $settings['correlation_limit']
                    )) ?>
                </span>
                <div class="vp-fact-line-sub">
                    <?= sprintf(
                        __(
                            'Above it MISP stores no correlations at all'
                            . ' and records the value in %s instead. That'
                            . ' is a fourth state for the first section,'
                            . ' not an empty one.'
                        ),
                        '<span class="font-monospace">'
                            . 'over_correlating_values</span>'
                    ) ?>
                    <?php if ($suppressed): ?>
                        <div class="mt-1 fw-semibold">
                            <?= h(__('This value is past it.')) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="vp-fact-line">
            <i class="fas fa-percent"></i>
            <div>
                <span class="fw-semibold">
                    <?= h(sprintf(
                        __('ssdeep threshold %d.'),
                        $settings['ssdeep_threshold']
                    )) ?>
                </span>
                <div class="vp-fact-line-sub">
                    <?= __('Pairs below it are never written, and the'
                        . ' score above it is not kept either — the'
                        . ' comparison is made to test the threshold and'
                        . ' then thrown away.') ?>
                </div>
            </div>
        </div>

        <div class="vp-fact-line">
            <i class="fas fa-ban"></i>
            <div>
                <span class="fw-semibold">
                    <?= h($settings['excluded']
                        ? __('Excluded from correlation.')
                        : __('No correlation exclusion.')) ?>
                </span>
                <div class="vp-fact-line-sub">
                    <?= sprintf(
                        $settings['excluded']
                            ? __('This value matches an entry in %s, so'
                                . ' nothing correlates it at all.')
                            : __('This value matches no entry in %s.'),
                        '<span class="font-monospace">'
                            . 'correlation_exclusions</span>'
                    ) ?>
                </div>
            </div>
        </div>

    </div>

    <?php if ($maxSplit > 0): ?>

        <div class="px-3 pb-3">
            <div class="vp-subhead">
                <?= h(sprintf(
                    __('The %s, split three ways'),
                    number_format($summary['correlations'])
                )) ?>
            </div>

            <?php foreach ($split as $part): ?>
                <div class="vp-reporter">
                    <span class="vp-reporter-name">
                        <?= h($part['label']) ?>
                    </span>
                    <span class="vp-reporter-track">
                        <span class="vp-reporter-fill"
                              style="width: <?= h(round(
                                  ($part['count'] / $maxSplit) * 100
                              )) ?>%; background: <?= h($part['colour'])
                              ?>;"></span>
                    </span>
                    <span class="vp-reporter-count">
                        <?= h(number_format($part['count'])) ?>
                    </span>
                </div>
            <?php endforeach; ?>

            <div class="small text-muted mt-2">
                <?php if ($suppressed): ?>
                    <?= h(sprintf(
                        __(
                            'The tab bar prints %1$s, which is the'
                            . ' occurrence count MISP recorded instead of'
                            . ' correlating. No correlation row was'
                            . ' stored, so the near-matches below it are'
                            . ' re-derived rather than read, and the'
                            . ' claims were never correlations at all.'
                        ),
                        number_format($summary['correlations'])
                    )) ?>
                <?php else: ?>
                    <?= h(sprintf(
                        __(
                            '%1$s and %2$s make the %3$s the tab bar'
                            . ' prints. The %4$s are counted apart and'
                            . ' never added to it — nothing here is'
                            . ' summed into one strength.'
                        ),
                        number_format($summary['cooccurrence']),
                        number_format($summary['near']),
                        number_format($summary['correlations']),
                        __n(
                            '%d claim',
                            '%d claims',
                            $summary['asserted'],
                            $summary['asserted']
                        )
                    )) ?>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>

</div>
