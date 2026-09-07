<?php
/**
 * Whether this value is still worth acting on.
 *
 * Three questions that all bear on the same thing and answer it
 * differently: is the record still current, does the value hit a
 * warninglist, and does it correlate with so much that the
 * correlations mean nothing.
 *
 * A warninglist miss is as informative as a hit, so the number of lists
 * checked is stated: "no hit" and "not checked" are not the same claim.
 *
 * **The first question changed in phase 5.** It used to be *has the
 * score decayed past its model's threshold*, drawn as one bar per
 * decaying model — a score MISP computes largely from the value's own
 * tags multiplied by a time factor, which the assessment's quality
 * ledger now scores directly and without the double counting
 * (`prd/analyst-profile/06-staleness.md` §4). What is here instead is
 * the relevance axis at card scale: the state, the runway, and the date
 * the clock last moved. The rail card on the Sightings tab
 * (`value_relevance`) is the same statement at panel scale, with the
 * provenance and the corroboration timeline this card has no room for.
 *
 * **The relevance block is live and the other two lines are not.** The
 * card is not indivisible either — see `ValuesController::viewLifecycle`
 * for why this phase converted its own third of it and left the rest.
 *
 * Lazily loaded into `.ajax-card` from ValuesController::viewLifecycle.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$relevance = $valueProfile['relevance'];
$warninglists = $valueProfile['warninglists'];
$checked = $valueProfile['warninglists_checked'];
$correlations = $valueProfile['correlations'];

$stateLabels = array(
    'current' => __('current'),
    'aging' => __('aging'),
    'expired' => __('expired'),
    'uncertain' => __('timeline uncertain'),
);
$state = $relevance['state'];

$subtitle = $state === null
    ? h(__('Nothing recorded to age'))
    : h(sprintf(
        __('%1$s of %2$s days of shelf life left'),
        max(0, $relevance['runway_days']),
        $relevance['ttl']['days']
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

        <?php if ($state !== null): ?>
            <div class="vp-shelf vp-shelf-<?= h($state) ?>">
                <div class="vp-shelf-head">
                    <span class="vp-shelf-state">
                        <?= h($stateLabels[$state]) ?>
                    </span>
                    <span class="vp-shelf-days">
                        <?= h(sprintf(
                            __('%1$s of %2$s days elapsed'),
                            $relevance['elapsed_days'],
                            $relevance['ttl']['days']
                        )) ?>
                    </span>
                </div>
                <div class="vp-shelf-track"
                     title="<?= h(sprintf(
                         __('%1$s days elapsed of a %2$s day TTL'),
                         $relevance['elapsed_days'],
                         $relevance['ttl']['days']
                     )) ?>">
                    <span class="vp-shelf-fill"
                          style="width: <?= (int)round(
                              $relevance['runway'] * 100
                          ) ?>%;"></span>
                    <span class="vp-shelf-mark"
                          style="left: <?= (int)round(
                              $relevance['aging_fraction'] * 100
                          ) ?>%;"></span>
                </div>
                <div class="vp-shelf-prov">
                    <?php if (!empty($relevance['uncertain'])): ?>
                        <?php
                        /*
                         * The measurement, at card scale. §3.6 requires
                         * the number wherever the state is shown — a
                         * bare `timeline uncertain` is the same silent
                         * guess it exists to replace.
                         */
                        ?>
                        <?= h($relevance['uncertain_note']) ?>
                    <?php elseif (!empty($relevance['clock']['fallback'])): ?>
                        <?= h(sprintf(
                            __('Never independently corroborated —'
                                . ' encoded %s'),
                            date('Y-m-d', $relevance['clock']['at'])
                        )) ?>
                    <?php else: ?>
                        <?= h(sprintf(
                            __('Last corroborated %1$s by %2$s'),
                            date('Y-m-d', $relevance['clock']['at']),
                            $relevance['clock']['by'] === null
                                ? __('an unnamed organisation')
                                : $relevance['clock']['by']
                        )) ?>
                    <?php endif; ?>
                    ·
                    <?= h($relevance['ttl']['type'] === null
                        ? __('default TTL')
                        : sprintf(
                            __('TTL from %s'),
                            $relevance['ttl']['type']
                        )) ?>
                </div>
            </div>
        <?php else: ?>
            <?php
            /*
             * The other two lines below answer even when the answer is
             * "nothing", so a silently absent freshness section would
             * read as a rendering gap rather than as a value this
             * reader holds nothing about.
             */
            ?>
            <div class="vp-empty vp-empty-inline">
                <i class="fas fa-hourglass-half"></i>
                <span><?= __('Nothing is recorded for this value, so'
                    . ' there is no clock to run.') ?></span>
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
