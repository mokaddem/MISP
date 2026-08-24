<?php
/**
 * Whether this value is still worth acting on.
 *
 * Three questions that all bear on the same thing and answer it
 * differently: has the score decayed past its model's threshold, does
 * the value hit a warninglist, and does it correlate with so much that
 * the correlations mean nothing.
 *
 * A warninglist miss is as informative as a hit, so the number of lists
 * checked is stated: "no hit" and "not checked" are not the same claim.
 *
 * Lazily loaded into `.ajax-card` from ValuesController::viewLifecycle.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$decay = $valueProfile['decay'];
$warninglists = $valueProfile['warninglists'];
$checked = $valueProfile['warninglists_checked'];
$correlations = $valueProfile['correlations'];

$decayed = 0;
foreach ($decay as $model) {
    if (!empty($model['decayed'])) {
        $decayed++;
    }
}

$subtitle = empty($decay)
    ? h(__('No decaying model applies'))
    : h(sprintf(
        __('%1$s of %2$s models still above threshold'),
        count($decay) - $decayed,
        count($decay)
    ));
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--correlation);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Lifecycle'),
        'panelIcon' => 'fas fa-hourglass-half',
        'panelColor' => 'var(--correlation)',
        'panelSub' => $subtitle,
    )) ?>

    <div class="p-3 d-flex flex-column gap-3">

        <?php if (!empty($decay)): ?>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($decay as $model): ?>
                    <div class="vp-decay<?= !empty($model['decayed'])
                        ? ' vp-decay-expired'
                        : '' ?>">
                        <div class="vp-decay-head">
                            <span class="vp-decay-model"
                                  title="<?= h($model['model']) ?>">
                                <?= h($model['model']) ?>
                            </span>
                            <?php if (!empty($model['decayed'])): ?>
                                <span class="vp-decay-flag">
                                    <?= __('decayed') ?>
                                </span>
                            <?php endif; ?>
                            <span class="vp-decay-score">
                                <?= h($model['score']) ?>
                            </span>
                        </div>
                        <div class="vp-decay-track"
                             title="<?= h(sprintf(
                                 __('Score %1$s, threshold %2$s'),
                                 $model['score'],
                                 $model['threshold']
                             )) ?>">
                            <span class="vp-decay-fill"
                                  style="width: <?=
                                      (int)$model['score'] ?>%;"></span>
                            <span class="vp-decay-threshold"
                                  style="left: <?=
                                      (int)$model['threshold'] ?>%;"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="vp-fact-line<?= empty($warninglists)
            ? ''
            : ' vp-fact-line-warn' ?>">
            <i class="fas fa-<?= empty($warninglists)
                ? 'circle-check'
                : 'triangle-exclamation' ?>"></i>
            <div>
                <?php if (empty($warninglists)): ?>
                    <div class="fw-semibold">
                        <?= __('No warninglist hit') ?>
                    </div>
                    <div class="vp-fact-line-sub">
                        <?= h(sprintf(__('%s lists checked'), $checked)) ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($warninglists as $warninglist): ?>
                        <div class="fw-semibold">
                            <?= h($warninglist['name']) ?>
                        </div>
                        <div class="vp-fact-line-sub">
                            <?= h(sprintf(
                                __('version %1$s · category %2$s'),
                                $warninglist['version'],
                                $warninglist['category']
                            )) ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="vp-fact-line<?= empty($correlations['over_correlating'])
            ? ''
            : ' vp-fact-line-warn' ?>">
            <i class="fas fa-diagram-project"></i>
            <div>
                <div class="fw-semibold">
                    <?= h(sprintf(
                        __('%s correlations'),
                        $correlations['count']
                    )) ?>
                </div>
                <div class="vp-fact-line-sub">
                    <?php if (!empty($correlations['over_correlating'])): ?>
                        <?= h(sprintf(
                            __('Over the %s threshold — correlations on this'
                                . ' value carry little meaning'),
                            $correlations['threshold']
                        )) ?>
                    <?php else: ?>
                        <?= h(sprintf(
                            __('Under the over-correlation threshold of %s'),
                            $correlations['threshold']
                        )) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

</div>
