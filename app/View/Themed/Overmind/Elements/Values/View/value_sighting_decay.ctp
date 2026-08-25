<?php
/**
 * The decaying models that score this value, as a rail card.
 *
 * Every line on the chart beside it is one of these models, and the bar
 * under each name here is that line's last point. Saying so is the
 * point of the closing note: a reader who has just watched a curve
 * cross a dotted threshold should not have to guess whether the number
 * in the rail is the same quantity.
 *
 * Two ways of being below a threshold are shown apart, because they are
 * different facts. A model that has decayed under its threshold can be
 * pushed back over it by the next sighting. A model whose base score is
 * already below its own threshold for this value never can, however
 * often the value is reported — and that is worth a sentence rather
 * than the same amber flag.
 *
 * Lazily loaded from ValuesController::viewSightingDecay.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$decay = $valueProfile['decay'];
$sightings = $valueProfile['sightings'];
?>
<div class="card shadow-sm mb-3 vp-panel vp-aside"
     style="--vp-panel-color: var(--correlation);">

    <div class="vp-aside-head">
        <i class="fas fa-hourglass-half"
           style="color: var(--correlation);"></i>
        <span class="vp-aside-title"><?= __('Decay models') ?></span>
        <span class="vp-aside-meta">
            <?= h(__n(
                '%s applies',
                '%s apply',
                count($decay),
                count($decay)
            )) ?>
        </span>
    </div>

    <div class="p-3 d-flex flex-column gap-3">

        <?php if (empty($decay)): ?>
            <?php
            /*
             * No model rather than a zero score. A value nobody has
             * recorded has no attribute for a model to score, which is
             * not the same claim as a score of nothing.
             */
            ?>
            <div class="vp-empty vp-empty-inline">
                <i class="fas fa-hourglass-half"></i>
                <span><?= __('No decaying model scores this value.') ?></span>
            </div>
        <?php else: ?>

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

                    <?php if (!empty($model['permanently_under'])): ?>
                        <div class="vp-decay-why">
                            <?= h(sprintf(
                                __('Base score %1$s — permanently under its'
                                    . ' own threshold of %2$s for this'
                                    . ' value. No sighting can carry it'
                                    . ' over.'),
                                $model['base'],
                                $model['threshold']
                            )) ?>
                        </div>
                    <?php endif; ?>

                    <div class="vp-decay-prov">
                        <?php if (!empty($model['reset_by'])): ?>
                            <?= h(sprintf(
                                __('Last reset by %1$s on %2$s'),
                                $model['reset_by'],
                                $model['reset_on']
                            )) ?>
                        <?php else: ?>
                            <?php
                            /*
                             * Never sighted, so the clock has been
                             * running from the attribute's own date.
                             * That is a score with no report behind it,
                             * and the card says which it is looking at.
                             */
                            ?>
                            <?= h(sprintf(
                                __('Never sighted — decaying from %s, the'
                                    . ' date the attribute was first'
                                    . ' seen'),
                                $model['reset_on']
                            )) ?>
                        <?php endif; ?>
                        ·
                        <?= h(sprintf(
                            __('lifetime %s days'),
                            $model['lifetime']
                        )) ?>
                    </div>

                </div>
            <?php endforeach; ?>

            <p class="vp-aside-note">
                <?= h(__(
                    'Each line on the chart is this model\'s score. The bar'
                    . ' under each name is the same number now.'
                )) ?>
                <?php
                $contradictions = $sightings['fp']
                    + $sightings['expiration'];
                ?>
                <?php if ($contradictions > 0): ?>
                    <?= h(__n(
                        'The %s report that contradicts the value is in'
                            . ' neither — MISP resets a decay clock on'
                            . ' sightings alone.',
                        'The %s reports that contradict the value are in'
                            . ' neither — MISP resets a decay clock on'
                            . ' sightings alone.',
                        $contradictions,
                        $contradictions
                    )) ?>
                <?php endif; ?>
            </p>

        <?php endif; ?>

    </div>

</div>
