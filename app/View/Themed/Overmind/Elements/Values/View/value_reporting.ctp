<?php
/**
 * Who put this value on the instance, and when.
 *
 * Two summaries side by side, neither of them new to the page and both
 * new to this tab:
 *
 *   - **When** — a bar a month over the value's whole life, which is
 *     the Timeline tab's *Activity on this value* with the axis set by
 *     the data's extent rather than by a brush. The silent months are
 *     drawn, because the run is the reading.
 *   - **Who** — occurrences per organisation with each one's `to_ids`
 *     stance, which is the occurrence half of the Assessment tab's
 *     *Who says what*. Same read, same stance word.
 *
 * Both replace hand-counting: a reader tallied the *Reported by* column
 * of the occurrence preview above to get the first and squinted at
 * *Last seen* for the second, over a capped sample that could not
 * support either. Phase 31.
 *
 * Lazily loaded into `.ajax-card` from ValuesController::viewReporting.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$reporting = $valueProfile['reporting'];
$months = $reporting['months'];
$orgs = $reporting['orgs'];
$peak = empty($months) ? 0 : max($months);
$topOrg = empty($orgs) ? 0 : $orgs[0]['occurrences'];
$hidden = $reporting['orgs_total'] - count($orgs);

/*
 * The months with something in them, out of the months in the span —
 * the same fraction `lifecycle.continuity` scores and the Assessment
 * tab prints in words. Stated here as the subtitle because it is what
 * the strip beneath it is *for*: a value reported in 16 of 51 months
 * and one reported in 16 consecutive months draw very differently and
 * hold the same occurrence count.
 */
$active = 0;
foreach ($months as $count) {
    if ($count > 0) {
        $active++;
    }
}

/*
 * Both halves' denominators, in the header, because both are read as
 * *out of what* and neither belongs under the thing it divides. The
 * second is the split's own sum and not the fact strip's total —
 * `forReporting` has the argument — so the word `live` is doing work:
 * a soft-deleted occurrence is in the strip's 26 and not in this 26.
 */
$clauses = array();
if (!empty($months)) {
    $clauses[] = sprintf(
        __n(
            'Reported in %1$s of %2$s month',
            'Reported in %1$s of %2$s months',
            count($months)
        ),
        number_format($active),
        number_format(count($months))
    );
}
if (!empty($orgs)) {
    $clauses[] = sprintf(
        __n(
            '%1$s live occurrence from %2$s',
            '%1$s live occurrences from %2$s',
            $reporting['occurrences']
        ),
        number_format($reporting['occurrences']),
        sprintf(
            __n(
                '%s organisation',
                '%s organisations',
                $reporting['orgs_total']
            ),
            number_format($reporting['orgs_total'])
        )
    );
}
$subtitle = empty($clauses)
    ? h(__('Nothing dated to report'))
    : implode(' &nbsp;·&nbsp; ', array_map('h', $clauses));

/*
 * The stance word is a fact about one organisation's rows and the
 * colour is the page's own reading of it, so the two are kept apart:
 * `mixed` is not a worse `yes`, it is an organisation that has not
 * decided, and it gets its own neutral rather than a shade between.
 */
$stanceTone = array(
    __('yes') => 'vp-stance-yes',
    __('no') => 'vp-stance-no',
    __('mixed') => 'vp-stance-mixed',
    __('none') => 'vp-stance-none',
);
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--object);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Reporting'),
        'panelIcon' => 'misp-icon misp-icon-organisation misp-simple',
        'panelColor' => 'var(--object)',
        'panelSub' => $subtitle,
    )) ?>

    <?php if (empty($orgs) && empty($months)): ?>
        <?php
        /*
         * The same distinction the occurrence card draws, for the same
         * reason: absent and hidden are different answers and only one
         * of them is knowable from here.
         */
        ?>
        <div class="vp-empty">
            <span class="misp-icon misp-icon-organisation misp-simple"></span>
            <span><?= __('No event you can see carries this value.') ?></span>
        </div>
    <?php else: ?>
        <div class="p-3 vp-reporting">

            <div class="vp-reporting-when">
                <div class="vp-subhead"><?= __('Reported in') ?></div>
                <?php if (empty($months)): ?>
                    <div class="vp-empty vp-empty-inline">
                        <i class="fas fa-clock"></i>
                        <span><?= __('No occurrence carries a date.') ?></span>
                    </div>
                <?php else: ?>
                    <div class="vp-spark" role="img"
                         aria-label="<?= h(__(
                             'Occurrences by month, oldest first'
                         )) ?>">
                        <?php foreach ($months as $month => $count): ?>
                            <span class="vp-spark-bar<?= $count === 0
                                ? ' vp-spark-bar-empty'
                                : '' ?>"
                                  style="--vp-spark-h: <?=
                                      $peak > 0
                                          ? round(100 * $count / $peak)
                                          : 0 ?>%;"
                                  title="<?= h(sprintf(
                                      __n(
                                          '%2$s — %1$s occurrence',
                                          '%2$s — %1$s occurrences',
                                          $count
                                      ),
                                      number_format($count),
                                      $month
                                  )) ?>"></span>
                        <?php endforeach; ?>
                    </div>
                    <?php
                    /*
                     * One label when the span is one month. Two ends
                     * reading `2017-01 … 2017-01` is a range that does
                     * not range, and the strip beside it already says
                     * the value has one bar.
                     */
                    ?>
                    <div class="vp-spark-axis">
                        <span><?= h(array_key_first($months)) ?></span>
                        <?php if (count($months) > 1): ?>
                            <span><?= h(array_key_last($months)) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="vp-reporting-who">
                <div class="vp-subhead">
                    <?= __('Occurrences by organisation') ?>
                </div>
                <?php if (empty($orgs)): ?>
                    <div class="vp-empty vp-empty-inline">
                        <span class="misp-icon misp-icon-organisation
                                     misp-simple"></span>
                        <span><?= __(
                            'Every occurrence is soft-deleted.'
                        ) ?></span>
                    </div>
                <?php else: ?>
                    <?php foreach ($orgs as $org): ?>
                        <div class="vp-reporter">
                            <span class="vp-reporter-name">
                                <?= h($org['name']) ?>
                            </span>
                            <span class="vp-stance <?= h(
                                $stanceTone[$org['stance']] ?? ''
                            ) ?>" title="<?= h(sprintf(
                                __('This organisation sets to_ids: %s'),
                                $org['stance']
                            )) ?>"><?= h($org['stance']) ?></span>
                            <span class="vp-reporter-track">
                                <span class="vp-reporter-fill" style="width: <?=
                                    $topOrg > 0
                                        ? round(100 * $org['occurrences']
                                            / $topOrg)
                                        : 0 ?>%;"></span>
                            </span>
                            <span class="vp-reporter-count">
                                <?= h(number_format($org['occurrences'])) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>

                    <?php
                    /*
                     * The cap is the drawing's and not the read's, so
                     * what it hides is a count this card holds rather
                     * than a number it would have to ask for.
                     */
                    ?>
                    <?php if ($hidden > 0): ?>
                        <div class="vp-reporting-rest">
                            <?= h(sprintf(
                                __n(
                                    '%s further organisation not shown',
                                    '%s further organisations not shown',
                                    $hidden
                                ),
                                number_format($hidden)
                            )) ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>

        </div>
    <?php endif; ?>

</div>
