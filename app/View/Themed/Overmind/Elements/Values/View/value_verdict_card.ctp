<?php
/**
 * The verdict in one card: what this value resolves to, and why.
 *
 * A summary of the Verdict tab, never a second opinion — the signals
 * here are the highest-weighted rows of the same ledger, and the count
 * of the ones that did not fit is stated rather than left implied. A
 * glass box with three sides showing is still a glass box; a black box
 * with a number in it is not.
 *
 * Lazily loaded into `.ajax-card` from
 * ValuesController::viewVerdictCard.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$verdict = $valueProfile['verdict'];

/*
 * The ledger is grouped by kind for the Verdict tab's benefit; the card
 * wants the heaviest signals whatever kind they came from.
 */
$signals = array();
foreach ($verdict['ledger'] as $group) {
    foreach ($group['signals'] as $signal) {
        $signal['kind'] = $group['kind'];
        $signals[] = $signal;
    }
}
usort($signals, function ($a, $b) {
    return abs($b['contribution']) <=> abs($a['contribution']);
});
$top = array_slice($signals, 0, 3);
$rest = count($signals) - count($top);

$confidenceLevels = array('none' => 0, 'low' => 1, 'medium' => 2,
    'high' => 3);
$confidence = $confidenceLevels[$verdict['confidence']] ?? 0;
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--primary);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Verdict'),
        'panelIcon' => 'fas fa-gavel',
        'panelColor' => 'var(--primary)',
        'panelSub' => h(sprintf(
            __('Weighting profile %s'),
            $verdict['profile']
        )),
    )) ?>

    <div class="p-3 d-flex flex-column gap-3">

        <div>
            <?= $this->element('Values/View/value_disposition', array(
                'disposition' => $verdict['disposition'],
                'score' => $verdict['score'],
                'size' => 'lg',
            )) ?>
            <div class="vp-confidence" title="<?= h(sprintf(
                __('Confidence: %s'),
                $verdict['confidence']
            )) ?>">
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

        <?php if (empty($top)): ?>
            <p class="vp-verdict-summary mb-0">
                <?= h($verdict['summary']) ?>
            </p>
        <?php else: ?>
            <div class="vp-signals">
                <?php foreach ($top as $signal):
                    $up = $signal['direction'] === 'up';
                    ?>
                    <div class="vp-signal<?= $up
                        ? ' vp-signal-up'
                        : ' vp-signal-down' ?>"
                         title="<?= h($signal['evidence']) ?>">
                        <span class="vp-signal-arrow">
                            <i class="fas fa-caret-<?=
                                $up ? 'up' : 'down' ?>"></i>
                        </span>
                        <span class="vp-signal-text">
                            <?= h($signal['signal']) ?>
                        </span>
                        <span class="vp-signal-weight">
                            <?= h($signal['weight']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($rest > 0): ?>
                <div class="vp-signals-rest">
                    <?= h(sprintf(
                        __('%s further signals not shown'),
                        $rest
                    )) ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <a href="#tab-verdict"
           class="btn btn-sm btn-outline-primary w-100
                  d-flex align-items-center justify-content-center gap-1">
            <?= __('Full assessment') ?>
            <i class="fas fa-arrow-right"></i>
        </a>

    </div>

</div>
