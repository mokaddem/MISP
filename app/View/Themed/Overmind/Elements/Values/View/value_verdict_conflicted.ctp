<?php
/**
 * The Verdict tab for a value whose signals contradict each other.
 *
 * A different layout rather than a different colour. A conflicted value
 * is not a malicious one with a warning on it: the evidence divides
 * into two coherent readings, and the page's job is to keep them apart
 * so an analyst can pick one, rather than average them into a number
 * that describes neither.
 *
 * There is deliberately no score. The tug-of-war bar shows the two
 * weights beside each other and refuses to subtract them.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewVerdict.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$verdict = $valueProfile['verdict'];

$uid = 'vp' . substr(md5($valueProfile['value'] . '-conflict'), 0, 8);

$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

$confidenceLevels = array('none' => 0, 'low' => 1, 'medium' => 2,
    'high' => 3);
$confidence = $confidenceLevels[$verdict['confidence']] ?? 0;

$tug = $verdict['tug'];
$tugTotal = max(
    array_sum(array($tug['malicious'], $tug['benign'], $tug['unresolved'])),
    1
);

$cases = $verdict['cases'];
$caseSignals = 0;
foreach ($cases as $case) {
    $caseSignals += count($case['rows']);
}
?>

<?php
/*
 * ------------------------------------------------------------------
 * 1. Hero
 * ------------------------------------------------------------------
 */
?>
<div class="card shadow-sm mb-3 vp-panel vp-verdict-hero"
     style="--vp-panel-color: var(--bs-warning);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Assessment'),
        'panelIcon' => 'fas fa-scale-unbalanced',
        'panelColor' => 'var(--bs-warning)',
        'panelSub' => h(sprintf(
            __('%1$s signals, split across %2$s readings'),
            $caseSignals,
            count($cases)
        )),
        'panelExtra' => '<button type="button"'
            . ' class="btn btn-sm btn-outline-dark disabled"'
            . ' title="' . h($noWrites) . '">'
            . '<i class="fas fa-rotate me-1"></i>'
            . h(__('Recompute')) . '</button>'
            . '<button type="button"'
            . ' class="btn btn-sm btn-outline-dark disabled"'
            . ' title="' . h($noWrites) . '">'
            . '<i class="fas fa-code me-1"></i>'
            . h(__('View as JSON')) . '</button>',
    )) ?>

    <div class="p-3 d-flex flex-column gap-3">

        <div class="vp-verdict-headline">
            <?= $this->element('Values/View/value_disposition', array(
                'disposition' => $verdict['disposition'],
                'score' => null,
                'size' => 'lg',
            )) ?>
            <div class="vp-verdict-noscore"
                 title="<?= h(__(
                     'A single number here would be the mean of two'
                     . ' incompatible readings, and would read as'
                     . ' certainty the evidence does not support.'
                 )) ?>">
                <?= __('No score — the readings do not average') ?>
            </div>
            <div class="vp-confidence">
                <span class="vp-confidence-label">
                    <?= h(__('Confidence')) ?>
                </span>
                <span class="vp-confidence-track">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <span class="vp-confidence-seg<?=
                            $i <= $confidence
                                ? ' vp-confidence-seg-on'
                                : '' ?>"></span>
                    <?php endfor; ?>
                </span>
                <span class="vp-confidence-reading">
                    <?= h($verdict['confidence']) ?>
                </span>
            </div>
        </div>

        <p class="vp-verdict-prose mb-0">
            <?= h($verdict['summary']) ?>
        </p>

        <div>
            <div class="vp-tug"
                 title="<?= h(sprintf(
                     __('%1$s weight for malicious, %2$s for benign,'
                         . ' %3$s counted for neither'),
                     $tug['malicious'],
                     $tug['benign'],
                     $tug['unresolved']
                 )) ?>">
                <span class="vp-tug-mal"
                      style="width: <?= round(
                          $tug['malicious'] / $tugTotal * 100,
                          2
                      ) ?>%;">
                    <?= h($tug['malicious']) ?>
                </span>
                <span class="vp-tug-none"
                      style="width: <?= round(
                          $tug['unresolved'] / $tugTotal * 100,
                          2
                      ) ?>%;"></span>
                <span class="vp-tug-ben"
                      style="width: <?= round(
                          $tug['benign'] / $tugTotal * 100,
                          2
                      ) ?>%;">
                    <?= h($tug['benign']) ?>
                </span>
            </div>
            <div class="vp-tug-legend">
                <span class="vp-tug-legend-item vp-tug-legend-mal">
                    <?= h(sprintf(
                        __('Malicious · %s signals'),
                        count($cases[0]['rows'])
                    )) ?>
                </span>
                <span class="vp-tug-legend-item vp-tug-legend-none">
                    <?= h(sprintf(
                        __('Neither · %s signals'),
                        count($verdict['unresolved'])
                    )) ?>
                </span>
                <span class="vp-tug-legend-item vp-tug-legend-ben">
                    <?= h(sprintf(
                        __('Benign · %s signals'),
                        count($cases[1]['rows'])
                    )) ?>
                </span>
            </div>
        </div>

        <div class="vp-rule">
            <i class="fas fa-code-branch"></i>
            <div>
                <div class="vp-rule-name font-monospace">
                    <?= h($verdict['rule']['name']) ?>
                </div>
                <div class="vp-rule-text">
                    <?= h($verdict['rule']['text']) ?>
                </div>
            </div>
        </div>

        <?= $this->element('Values/View/value_verdict_meta', array(
            'verdict' => $verdict,
        )) ?>

    </div>

</div>

<?php
/*
 * ------------------------------------------------------------------
 * 2. Warninglist callout
 * ------------------------------------------------------------------
 * The single most misread signal on the page, so it gets its own block
 * rather than a row in a table: a `known`-category hit says the address
 * is shared infrastructure, not that the reports against it are wrong.
 */
?>
<?php if (!empty($verdict['warninglist'])):
    $warninglist = $verdict['warninglist'];
    ?>
    <div class="card shadow-sm mb-3 vp-panel"
         style="--vp-panel-color: var(--warninglist);">

        <?= $this->element('Values/View/value_panel_header', array(
            'panelTitle' => __('Warninglist hit'),
            'panelIcon' => 'fas fa-triangle-exclamation',
            'panelColor' => 'var(--warninglist)',
            'panelSub' => h(sprintf(
                __('Category %s'),
                $warninglist['category']
            )),
        )) ?>

        <div class="p-3 d-flex flex-column gap-3">

            <div class="vp-warninglist-facts">
                <div class="vp-warninglist-fact">
                    <span class="vp-warninglist-fact-label">
                        <?= __('List') ?>
                    </span>
                    <span class="vp-warninglist-fact-value">
                        <?= h($warninglist['name']) ?>
                    </span>
                </div>
                <div class="vp-warninglist-fact">
                    <span class="vp-warninglist-fact-label">
                        <?= __('Version') ?>
                    </span>
                    <span class="vp-warninglist-fact-value font-monospace">
                        <?= h($warninglist['version']) ?>
                    </span>
                </div>
                <div class="vp-warninglist-fact">
                    <span class="vp-warninglist-fact-label">
                        <?= __('Category') ?>
                    </span>
                    <span class="vp-warninglist-fact-value font-monospace">
                        <?= h($warninglist['category']) ?>
                    </span>
                </div>
                <div class="vp-warninglist-fact">
                    <span class="vp-warninglist-fact-label">
                        <?= __('Matched') ?>
                    </span>
                    <span class="vp-warninglist-fact-value font-monospace">
                        <?= h($warninglist['matched']) ?>
                    </span>
                </div>
            </div>

            <div class="vp-fact-line vp-fact-line-warn">
                <i class="fas fa-circle-info"></i>
                <div><?= h($warninglist['note']) ?></div>
            </div>

        </div>

    </div>
<?php endif; ?>

<?php
/*
 * ------------------------------------------------------------------
 * 3. The two cases
 * ------------------------------------------------------------------
 * Side by side and the same shape, so the comparison is between the
 * evidence rather than between two presentations of it.
 */
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--bs-warning);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('The two cases'),
        'panelIcon' => 'fas fa-scale-balanced',
        'panelColor' => 'var(--bs-warning)',
        'panelSub' => h(__('Neither is subtracted from the other')),
    )) ?>

    <div class="p-3">
        <div class="vp-cases">
            <?php foreach ($cases as $case): ?>
                <div class="vp-case vp-case-<?= h($case['side']) ?>">

                    <div class="vp-case-head">
                        <span class="vp-case-title">
                            <?= h($case['title']) ?>
                        </span>
                        <span class="vp-case-weight">
                            <?= h($case['weight']) ?>
                        </span>
                    </div>

                    <?php foreach ($case['rows'] as $row): ?>
                        <div class="vp-case-row">
                            <div class="vp-case-row-head">
                                <span class="vp-case-signal">
                                    <?= h($row['signal']) ?>
                                </span>
                                <span class="vp-case-points">
                                    <?= h($row['points'] > 0
                                        ? '+' . $row['points']
                                        : $row['points']) ?>
                                </span>
                            </div>
                            <div class="vp-case-evidence">
                                <?= h($row['evidence']) ?>
                            </div>
                            <div class="vp-case-meta">
                                <span class="vp-case-weight-word">
                                    <?= h($row['weight']) ?>
                                </span>
                                <span class="vp-case-source">
                                    <?= h($row['source']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<?php
/*
 * ------------------------------------------------------------------
 * 4. Unresolved signals
 * ------------------------------------------------------------------
 * Counted for neither side, and listed anyway. A signal silently
 * dropped looks the same as a signal nobody found.
 */
?>
<?php if (!empty($verdict['unresolved'])): ?>
    <div class="card shadow-sm mb-3 vp-panel"
         style="--vp-panel-color: var(--bs-secondary-color);">

        <?= $this->element('Values/View/value_panel_header', array(
            'panelTitle' => __('Counted for neither side'),
            'panelIcon' => 'fas fa-circle-minus',
            'panelColor' => 'var(--bs-secondary-color)',
            'panelSub' => h(sprintf(
                __('%s signals that separate nothing'),
                count($verdict['unresolved'])
            )),
        )) ?>

        <div class="table-responsive">
            <table class="table table-sm align-middle vp-table mb-0">
                <thead>
                    <tr>
                        <th><?= __('Signal') ?></th>
                        <th><?= __('Evidence') ?></th>
                        <th><?= __('Source panel') ?></th>
                        <th><?= __('Why it does not count') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($verdict['unresolved'] as $row): ?>
                        <tr>
                            <td class="fw-semibold">
                                <?= h($row['signal']) ?>
                            </td>
                            <td class="text-muted">
                                <?= h($row['evidence']) ?>
                            </td>
                            <td><?= h($row['source']) ?></td>
                            <td class="text-muted"><?= h($row['why']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
<?php endif; ?>

<?php
/*
 * ------------------------------------------------------------------
 * 5. Who says what
 * ------------------------------------------------------------------
 * The same table the agreeing layout uses, plus the column that only
 * matters here: which reading each organisation holds.
 */
?>
<?php if (!empty($verdict['orgs'])): ?>
    <div class="card shadow-sm mb-3 vp-panel"
         style="--vp-panel-color: var(--primary);">

        <?= $this->element('Values/View/value_panel_header', array(
            'panelTitle' => __('Who says what'),
            'panelIcon' => 'misp-icon misp-icon-organisation misp-simple',
            'panelColor' => 'var(--primary)',
            'panelSub' => h(sprintf(
                __('%s organisations you can see'),
                count($verdict['orgs'])
            )),
        )) ?>

        <div class="table-responsive">
            <table class="table table-sm align-middle vp-table mb-0">
                <thead>
                    <tr>
                        <th><?= __('Organisation') ?></th>
                        <th><?= __('Reads it as') ?></th>
                        <th class="text-end"><?= __('Occurrences') ?></th>
                        <th class="text-end"><?= __('Sightings') ?></th>
                        <th class="text-end"><?= __('False positives') ?></th>
                        <th><?= __('Opinion') ?></th>
                        <th><?= __('to_ids stance') ?></th>
                        <th><?= __('Source reliability') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($verdict['orgs'] as $org): ?>
                        <tr>
                            <td class="fw-semibold"><?= h($org['org']) ?></td>
                            <td>
                                <span class="vp-side vp-side-<?=
                                    h($org['side'] ?? 'none') ?>">
                                    <?= h($org['reads']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?= h($org['occurrences']) ?>
                            </td>
                            <td class="text-end">
                                <?= h($org['sightings']) ?>
                            </td>
                            <td class="text-end<?= $org['fp'] > 0
                                ? ' text-danger fw-semibold'
                                : ' text-muted' ?>">
                                <?= h($org['fp']) ?>
                            </td>
                            <td>
                                <?php if ($org['opinion'] === null): ?>
                                    <span class="text-muted">
                                        <?= __('none stated') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="vp-opinion"
                                          title="<?= h(sprintf(
                                              __('Opinion %s of 100'),
                                              $org['opinion']
                                          )) ?>">
                                        <span class="vp-opinion-track">
                                            <span class="vp-opinion-fill"
                                                  style="width: <?=
                                                      (int)$org['opinion']
                                                      ?>%;"></span>
                                        </span>
                                        <span class="vp-opinion-value">
                                            <?= h($org['opinion']) ?>
                                        </span>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($org['to_ids']) ?></td>
                            <td>
                                <span class="vp-reliability">
                                    <?= h($org['reliability']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
<?php endif; ?>

<?php
/*
 * ------------------------------------------------------------------
 * 6. Resolve it
 * ------------------------------------------------------------------
 * Each card names the write it would perform rather than the verdict it
 * would assert. Resolving a conflict is an edit somebody has to own,
 * and a card that hid that would be asking for a signature on a blank
 * form.
 */
?>
<?php if (!empty($verdict['resolutions'])): ?>
    <div class="card shadow-sm mb-3 vp-panel"
         style="--vp-panel-color: var(--attribute);">

        <?= $this->element('Values/View/value_panel_header', array(
            'panelTitle' => __('Resolve it'),
            'panelIcon' => 'fas fa-gavel',
            'panelColor' => 'var(--attribute)',
            'panelSub' => h(__(
                'Each option states exactly what it would write'
            )),
        )) ?>

        <div class="p-3">
            <div class="vp-resolutions">
                <?php foreach ($verdict['resolutions'] as $resolution): ?>
                    <div class="vp-resolution">
                        <div class="vp-resolution-head">
                            <i class="<?= h($resolution['icon']) ?>"></i>
                            <span class="vp-resolution-title">
                                <?= h($resolution['title']) ?>
                            </span>
                        </div>
                        <p class="vp-resolution-note">
                            <?= h($resolution['note']) ?>
                        </p>
                        <div class="vp-resolution-writes">
                            <span class="vp-resolution-writes-label">
                                <?= __('Writes') ?>
                            </span>
                            <?= h($resolution['writes']) ?>
                        </div>
                        <button type="button"
                                class="btn btn-sm btn-outline-dark disabled
                                       w-100"
                                title="<?= h($noWrites) ?>">
                            <?= h($resolution['title']) ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
<?php endif; ?>

<?php
/*
 * ------------------------------------------------------------------
 * 7. Opinion distribution
 * ------------------------------------------------------------------
 * A histogram rather than a mean, and the note saying why. The mean of
 * a bimodal distribution is a value nobody holds.
 */
?>
<?php if (!empty($verdict['opinions']['buckets'])):
    $opinions = $verdict['opinions'];

    $labels = array();
    $counts = array();
    foreach ($opinions['buckets'] as $bucket) {
        $labels[] = $bucket['label'];
        $counts[] = (int)$bucket['count'];
    }

    $opinionChart = array(
        'type' => 'bar',
        'data' => array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => __('Opinions'),
                    'data' => $counts,
                    'backgroundColor' => 'var(--analystData)',
                    'borderColor' => 'var(--analystData)',
                    'borderWidth' => 0,
                    'borderRadius' => 2,
                    'barPercentage' => 0.85,
                    'categoryPercentage' => 0.9,
                ),
            ),
        ),
        'options' => array(
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => array(
                'x' => array(
                    'grid' => array('display' => false),
                    'ticks' => array(
                        'color' => 'var(--bs-secondary-color)',
                        'font' => array('size' => 10),
                    ),
                ),
                'y' => array(
                    'beginAtZero' => true,
                    'grid' => array('color' => 'var(--bs-border-color)'),
                    'ticks' => array(
                        'color' => 'var(--bs-secondary-color)',
                        'precision' => 0,
                        'font' => array('size' => 10),
                    ),
                ),
            ),
            'plugins' => array(
                'legend' => array('display' => false),
            ),
        ),
    );
    ?>
    <div class="card shadow-sm mb-3 vp-panel"
         style="--vp-panel-color: var(--analystData);">

        <?= $this->element('Values/View/value_panel_header', array(
            'panelTitle' => __('Opinion distribution'),
            'panelIcon' => 'misp-icon misp-icon-analyst-opinion misp-simple',
            'panelColor' => 'var(--analystData)',
            'panelSub' => h(sprintf(
                __('%s opinions you can see'),
                $opinions['n']
            )),
        )) ?>

        <div class="p-3 d-flex flex-column gap-3">

            <?= $this->element('Values/View/value_chart', array(
                'chartId' => $uid . '-opinions',
                'chartConfig' => $opinionChart,
                'chartHeight' => 200,
                'chartLabel' => __(
                    'How many opinions fall into each ten-point band'
                ),
            )) ?>

            <div class="vp-fact-line vp-fact-line-warn">
                <i class="fas fa-circle-info"></i>
                <div>
                    <div class="fw-semibold">
                        <?= h(sprintf(
                            __('Mean %s — an artefact'),
                            $opinions['mean']
                        )) ?>
                    </div>
                    <div class="vp-fact-line-sub">
                        <?= h($opinions['note']) ?>
                    </div>
                </div>
            </div>

        </div>

    </div>
<?php endif; ?>

<?php
/*
 * ------------------------------------------------------------------
 * 8. The cases over time
 * ------------------------------------------------------------------
 * The two weights on one axis, because when they crossed is the fact
 * that a snapshot cannot carry.
 */
?>
<?php if (!empty($verdict['curves'])):
    $buckets = count($verdict['curves'][0]['data']);
    $curveLabels = array();
    for ($i = 0; $i < $buckets; $i++) {
        $daysAgo = $buckets > 1
            ? (int)round((($buckets - 1 - $i) * 90) / ($buckets - 1))
            : 0;
        $curveLabels[] = $daysAgo === 0
            ? __('today')
            : '-' . $daysAgo . 'd';
    }

    $curveSets = array();
    foreach ($verdict['curves'] as $curve) {
        $curveSets[] = array(
            'label' => $curve['label'],
            'data' => array_values($curve['data']),
            'borderColor' => $curve['colour'],
            'backgroundColor' => $curve['colour'],
            'borderWidth' => 2,
            'pointRadius' => 0,
            'pointHoverRadius' => 3,
            'tension' => 0.3,
            'fill' => false,
        );
    }

    $curveChart = array(
        'type' => 'line',
        'data' => array(
            'labels' => $curveLabels,
            'datasets' => $curveSets,
        ),
        'options' => array(
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => array(
                'mode' => 'index',
                'intersect' => false,
            ),
            'scales' => array(
                'x' => array(
                    'grid' => array('display' => false),
                    'ticks' => array(
                        'color' => 'var(--bs-secondary-color)',
                        'maxTicksLimit' => 7,
                        'maxRotation' => 0,
                        'autoSkip' => true,
                        'font' => array('size' => 10),
                    ),
                ),
                'y' => array(
                    'beginAtZero' => true,
                    'grid' => array('color' => 'var(--bs-border-color)'),
                    'ticks' => array(
                        'color' => 'var(--bs-secondary-color)',
                        'font' => array('size' => 10),
                    ),
                ),
            ),
            'plugins' => array(
                'legend' => array(
                    'position' => 'bottom',
                    'labels' => array(
                        'color' => 'var(--bs-body-color)',
                        'boxWidth' => 14,
                        'boxHeight' => 2,
                        'font' => array('size' => 10),
                    ),
                ),
            ),
        ),
    );
    ?>
    <div class="card shadow-sm mb-3 vp-panel"
         style="--vp-panel-color: var(--correlation);">

        <?= $this->element('Values/View/value_panel_header', array(
            'panelTitle' => __('The cases over time'),
            'panelIcon' => 'fas fa-chart-line',
            'panelColor' => 'var(--correlation)',
            'panelSub' => h(__(
                '90 days — the weight each reading carried'
            )),
        )) ?>

        <div class="p-3">
            <?= $this->element('Values/View/value_chart', array(
                'chartId' => $uid . '-cases',
                'chartConfig' => $curveChart,
                'chartHeight' => 260,
                'chartLabel' => __(
                    'The weight of the malicious and benign readings over'
                    . ' the last 90 days'
                ),
            )) ?>
        </div>

    </div>
<?php endif; ?>
