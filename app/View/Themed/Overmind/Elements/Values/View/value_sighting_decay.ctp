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

            <?php
            /*
             * The colour is the chart's, not this card's. `%s`'s line
             * and `%s`'s bar are the same series drawn twice, and until
             * this phase the bar was `--correlation` for every model
             * while the line cycled two hues — so a reader with two
             * models had no way to tell which line the number under
             * each name belonged to. The cycle is the one
             * `value-profile.js` uses, over the same two variables, and
             * the index matches because `ValueProfile::decayFor` fills
             * `models` and `curves` in one loop.
             */
            ?>
            <?php foreach ($decay as $i => $model): ?>
                <?php $hue = 'var(--vp-sight-curve-' . ($i % 2 + 1) . ')'; ?>
                <div class="vp-decay vp-decay-keyed<?= !empty($model['decayed'])
                    ? ' vp-decay-expired'
                    : '' ?>"
                     style="--vp-decay-colour: <?= $hue ?>;">

                    <div class="vp-decay-head">
                        <span class="vp-decay-key"></span>
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

                    <?php if (!empty($model['held_by']['event_id'])): ?>
                        <?php
                        /*
                         * A value is a set of occurrences and a decay
                         * score is one attribute's, so a value-scoped
                         * score needs an aggregation rule. The rule is
                         * the highest of them, and the second half of
                         * the rule is saying which one — a reader who
                         * disagrees with the number has somewhere to go
                         * and argue with it.
                         * prd/value-profile-live/23-sightings.md §5.
                         */
                        ?>
                        <div class="vp-decay-prov">
                            <?= h(sprintf(
                                __('Highest of %1$s · held by the one in'
                                    . ' event %2$s'),
                                __n(
                                    '%s scored occurrence',
                                    '%s scored occurrences',
                                    $model['over'],
                                    $model['over']
                                ),
                                $model['held_by']['event_id']
                            )) ?>
                            <?php if ($model['over'] < $model['of']): ?>
                                ·
                                <?= h(sprintf(
                                    __('%1$s of %2$s occurrences scored'),
                                    $model['over'],
                                    $model['of']
                                )) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>

            <p class="vp-aside-note">
                <?= h(__(
                    'Each line on the chart is one of these models, drawn'
                    . ' in the colour beside its name here. The bar under'
                    . ' each name is that line\'s last point, and the tick'
                    . ' across it is the threshold the chart no longer'
                    . ' draws.'
                )) ?>
                <?= h(__(
                    'MISP scores an attribute, not a value, so each of'
                    . ' these is the highest score any one occurrence of'
                    . ' the value carries.'
                )) ?>
            </p>

            <?php
            /*
             * §14.6's one exception, which this phase found has two
             * members rather than one.
             *
             * A decay score is computed from the reports the reader can
             * see, and `Plugin.Sightings_policy` hides whole reports.
             * Measured on `2.2.2.2`: a site admin reads NIDS 73 and a
             * CIRCL org admin reads 59, from the same value on the same
             * day, because the report that last reset the clock is on an
             * event CIRCL does not own. That is the Verdict tab's
             * situation exactly — a computed judgement two readers can
             * honestly differ on — so it gets the Verdict tab's
             * treatment: a line that is always shown, identical for
             * every reader, and therefore carrying no information about
             * what any particular reader cannot see.
             *
             * prd/value-profile-live/23-sightings.md §7.
             */
            ?>
            <p class="vp-acl-note">
                <i class="fas fa-user-shield"></i>
                <span><?= h(__(
                    'A decay score counts the reports you can see. Two'
                    . ' readers whose sighting visibility differs can'
                    . ' honestly read different scores for this value on'
                    . ' the same day.'
                )) ?></span>
            </p>

            <p class="vp-aside-note">
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
