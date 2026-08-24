<?php
/**
 * The Verdict tab for a value whose signals agree.
 *
 * A glass box, not a score: the hero states the disposition and the
 * prose behind it, then the signal ledger grouped by kind, the
 * contradictions kept explicitly un-netted, the per-organisation
 * stances, the score composition, and the decay curves over time.
 *
 * Every row traces back to the panel that produced it. A disposition
 * an analyst cannot take apart is worth less than no disposition at
 * all, because it cannot be argued with.
 *
 * Nothing here is stored or synchronised — the verdict is computed at
 * render, from what the viewing user is allowed to see.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewVerdict.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$verdict = $valueProfile['verdict'];
$decay = $valueProfile['decay'];

$uid = 'vp' . substr(md5($valueProfile['value'] . '-verdict'), 0, 8);

$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

$confidenceLevels = array('none' => 0, 'low' => 1, 'medium' => 2,
    'high' => 3);
$confidence = $confidenceLevels[$verdict['confidence']] ?? 0;

$signalCount = 0;
foreach ($verdict['ledger'] as $group) {
    $signalCount += count($group['signals']);
}

/*
 * Earned and deducted points share one scale, so the strip and the row
 * bars are read against the same ruler. Netting them into a single bar
 * first would produce one length nobody could account for.
 */
$positive = 0;
$negative = 0;
foreach ($verdict['composition'] as $segment) {
    if ($segment['points'] >= 0) {
        $positive += $segment['points'];
    } else {
        $negative += abs($segment['points']);
    }
}
$span = max($positive + $negative, 1);
?>

<?php
/*
 * ------------------------------------------------------------------
 * 1. Hero
 * ------------------------------------------------------------------
 */
?>
<div class="card shadow-sm mb-3 vp-panel vp-verdict-hero"
     style="--vp-panel-color: var(--bs-danger);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Assessment'),
        'panelIcon' => 'fas fa-gavel',
        'panelColor' => 'var(--bs-danger)',
        'panelSub' => h(sprintf(
            __('%1$s signals across %2$s kinds'),
            $signalCount,
            count($verdict['ledger'])
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
            <?php if ($verdict['score'] !== null): ?>
                <div class="vp-verdict-score">
                    <span class="vp-verdict-score-value">
                        <?= h($verdict['score']) ?>
                    </span>
                    <span class="vp-verdict-score-of">/ 100</span>
                </div>
            <?php endif; ?>
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

        <?= $this->element('Values/View/value_verdict_meta', array(
            'verdict' => $verdict,
        )) ?>

    </div>

</div>

<?php
/*
 * ------------------------------------------------------------------
 * 2. Signal ledger
 * ------------------------------------------------------------------
 * Grouped by kind rather than sorted by weight: an analyst checking
 * whether the sightings were counted twice wants them next to each
 * other, not scattered through a ranking.
 */
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--attribute);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Signal ledger'),
        'panelIcon' => 'fas fa-list-check',
        'panelColor' => 'var(--attribute)',
        'panelSub' => h(__('Every row expands to its evidence')),
    )) ?>

    <?php if (empty($verdict['ledger'])): ?>
        <div class="vp-empty">
            <i class="fas fa-list-check"></i>
            <span><?= __('No signal contributed to this verdict.') ?></span>
        </div>
    <?php else: ?>
        <div class="vp-ledger">

            <div class="vp-ledger-head">
                <span></span>
                <span><?= __('Signal') ?></span>
                <span class="text-end"><?= __('Contribution') ?></span>
                <span><?= __('Source panel') ?></span>
                <span><?= __('As of') ?></span>
                <span></span>
            </div>

            <?php foreach ($verdict['ledger'] as $g => $group): ?>
                <div class="vp-ledger-kind">
                    <?= h($group['kind']) ?>
                </div>
                <?php foreach ($group['signals'] as $s => $signal):
                    $up = $signal['direction'] === 'up';
                    $rowId = $uid . '-sig-' . $g . '-' . $s;
                    ?>
                    <div class="vp-ledger-row<?= $up
                        ? ' vp-ledger-row-up'
                        : ' vp-ledger-row-down' ?>">
                        <button type="button"
                                class="vp-ledger-toggle collapsed"
                                data-bs-toggle="collapse"
                                data-bs-target="#<?= h($rowId) ?>"
                                aria-expanded="false"
                                aria-controls="<?= h($rowId) ?>">
                            <span class="vp-signal-arrow">
                                <i class="fas fa-caret-<?=
                                    $up ? 'up' : 'down' ?>"></i>
                            </span>
                            <span class="vp-ledger-signal">
                                <?= h($signal['signal']) ?>
                            </span>
                            <span class="vp-ledger-contrib">
                                <?= h(($signal['contribution'] > 0 ? '+' : '')
                                    . $signal['contribution']) ?>
                            </span>
                            <span class="vp-ledger-source">
                                <?= h($signal['source']) ?>
                            </span>
                            <span class="vp-ledger-asof">
                                <?= h($signal['as_of']) ?>
                            </span>
                            <i class="fas fa-chevron-down
                                      vp-ledger-chevron"></i>
                        </button>
                        <div class="collapse" id="<?= h($rowId) ?>">
                            <div class="vp-ledger-detail">
                                <div>
                                    <span class="vp-ledger-detail-label">
                                        <?= __('Evidence') ?>
                                    </span>
                                    <?= h($signal['evidence']) ?>
                                </div>
                                <div>
                                    <span class="vp-ledger-detail-label">
                                        <?= __('Weight') ?>
                                    </span>
                                    <?= h($signal['weight']) ?>
                                </div>
                                <div>
                                    <span class="vp-ledger-detail-label">
                                        <?= __('Read from') ?>
                                    </span>
                                    <?= h(sprintf(
                                        __('the %s panel, as of %s'),
                                        $signal['source'],
                                        $signal['as_of']
                                    )) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>

        </div>
    <?php endif; ?>

</div>

<?php
/*
 * ------------------------------------------------------------------
 * 3. Contradictions and conflicts
 * ------------------------------------------------------------------
 * Shown whole rather than reduced to a net figure. A 6/4 split and a
 * unanimous 10 are different situations, and averaging them produces
 * a number that describes neither.
 */
?>
<?php if (!empty($verdict['conflicts'])): ?>
    <div class="card shadow-sm mb-3 vp-panel"
         style="--vp-panel-color: var(--bs-warning);">

        <?= $this->element('Values/View/value_panel_header', array(
            'panelTitle' => __('Contradictions and conflicts'),
            'panelIcon' => 'fas fa-code-branch',
            'panelColor' => 'var(--bs-warning)',
            'panelSub' => h(sprintf(
                __('%s disagreements, none netted off'),
                count($verdict['conflicts'])
            )),
        )) ?>

        <div class="p-3 d-flex flex-column gap-3">
            <?php foreach ($verdict['conflicts'] as $conflict): ?>
                <?= $this->element(
                    'Values/View/value_conflict_block',
                    array('conflict' => $conflict, 'noWrites' => $noWrites)
                ) ?>
            <?php endforeach; ?>
        </div>

    </div>
<?php endif; ?>

<?php
/*
 * ------------------------------------------------------------------
 * 4. Who says what
 * ------------------------------------------------------------------
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
 * 5. How the score was reached
 * ------------------------------------------------------------------
 */
?>
<?php if (!empty($verdict['composition'])): ?>
    <div class="card shadow-sm mb-3 vp-panel"
         style="--vp-panel-color: var(--type);">

        <?= $this->element('Values/View/value_panel_header', array(
            'panelTitle' => __('How the score was reached'),
            'panelIcon' => 'fas fa-calculator',
            'panelColor' => 'var(--type)',
            'panelSub' => h(sprintf(
                __('%1$s earned, %2$s deducted'),
                $positive,
                $negative
            )),
        )) ?>

        <div class="p-3 d-flex flex-column gap-3">

            <div class="vp-composition"
                 title="<?= h(sprintf(
                     __('%1$s points earned, %2$s deducted, %3$s net'),
                     $positive,
                     $negative,
                     $positive - $negative
                 )) ?>">
                <?php foreach ($verdict['composition'] as $segment):
                    if ($segment['points'] < 0) {
                        continue;
                    }
                    ?>
                    <span class="vp-composition-seg"
                          style="width: <?= round(
                              $segment['points'] / $span * 100,
                              2
                          ) ?>%; --vp-seg-color: <?=
                              h($segment['colour']) ?>;"
                          title="<?= h(sprintf(
                              '%1$s +%2$s',
                              $segment['label'],
                              $segment['points']
                          )) ?>"></span>
                <?php endforeach; ?>
                <?php if ($negative > 0): ?>
                    <span class="vp-composition-cut"
                          style="width: <?= round(
                              $negative / $span * 100,
                              2
                          ) ?>%;"
                          title="<?= h(sprintf(
                              __('%s points deducted'),
                              $negative
                          )) ?>"></span>
                <?php endif; ?>
            </div>

            <div class="d-flex flex-column gap-1">
                <?php foreach ($verdict['composition'] as $segment):
                    $points = (int)$segment['points'];
                    $down = $points < 0;
                    ?>
                    <div class="vp-weight">
                        <span class="vp-weight-swatch"
                              style="--vp-seg-color: <?=
                                  h($segment['colour']) ?>;"></span>
                        <span class="vp-weight-label">
                            <?= h($segment['label']) ?>
                        </span>
                        <span class="vp-weight-track">
                            <span class="vp-weight-fill<?= $down
                                ? ' vp-weight-fill-down'
                                : '' ?>"
                                  style="width: <?= round(
                                      abs($points) / $span * 100,
                                      2
                                  ) ?>%; --vp-seg-color: <?=
                                      h($segment['colour']) ?>;"></span>
                        </span>
                        <span class="vp-weight-points<?= $down
                            ? ' vp-weight-points-down'
                            : '' ?>">
                            <?= h(($points > 0 ? '+' : '') . $points) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                <div class="vp-weight vp-weight-total">
                    <span class="vp-weight-swatch
                                 vp-weight-swatch-empty"></span>
                    <span class="vp-weight-label">
                        <?= __('Total') ?>
                    </span>
                    <span class="vp-weight-track"></span>
                    <span class="vp-weight-points">
                        <?= h($positive - $negative) ?>
                    </span>
                </div>
            </div>

        </div>

    </div>
<?php endif; ?>

<?php
/*
 * ------------------------------------------------------------------
 * 6. Verdict over time
 * ------------------------------------------------------------------
 * The decay curves, with each model's threshold drawn as a dashed line
 * in the same colour. A score without the line it is above or below is
 * a number nobody can act on.
 */
?>
<?php if (!empty($decay)):
    $buckets = count($decay[0]['curve']);
    $labels = array();
    for ($i = 0; $i < $buckets; $i++) {
        $daysAgo = $buckets > 1
            ? (int)round((($buckets - 1 - $i) * 90) / ($buckets - 1))
            : 0;
        $labels[] = $daysAgo === 0 ? __('today') : '-' . $daysAgo . 'd';
    }

    $palette = array('var(--correlation)', 'var(--sighting)');
    $datasets = array();
    foreach ($decay as $m => $model) {
        $colour = $palette[$m % count($palette)];
        $datasets[] = array(
            'label' => $model['model'],
            'data' => array_values($model['curve']),
            'borderColor' => $colour,
            'backgroundColor' => $colour,
            'borderWidth' => 2,
            'pointRadius' => 0,
            'pointHoverRadius' => 3,
            'tension' => 0.3,
            'fill' => false,
        );
        $datasets[] = array(
            'label' => sprintf(__('%s threshold'), $model['model']),
            'data' => array_fill(0, $buckets, (int)$model['threshold']),
            'borderColor' => $colour,
            'backgroundColor' => $colour,
            'borderWidth' => 1,
            'borderDash' => array(4, 4),
            'pointRadius' => 0,
            'pointHoverRadius' => 0,
            'fill' => false,
        );
    }

    $chartConfig = array(
        'type' => 'line',
        'data' => array('labels' => $labels, 'datasets' => $datasets),
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
                    'min' => 0,
                    'max' => 100,
                    'grid' => array('color' => 'var(--bs-border-color)'),
                    'ticks' => array(
                        'color' => 'var(--bs-secondary-color)',
                        'stepSize' => 25,
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
            'panelTitle' => __('Verdict over time'),
            'panelIcon' => 'fas fa-chart-line',
            'panelColor' => 'var(--correlation)',
            'panelSub' => h(__('90 days, one line per decaying model')),
        )) ?>

        <div class="p-3">
            <?= $this->element('Values/View/value_chart', array(
                'chartId' => $uid . '-decay',
                'chartConfig' => $chartConfig,
                'chartHeight' => 260,
                'chartLabel' => __(
                    'Decay score over the last 90 days, one line per'
                    . ' decaying model, with each model threshold drawn'
                    . ' as a dashed line'
                ),
            )) ?>
        </div>

    </div>
<?php endif; ?>
