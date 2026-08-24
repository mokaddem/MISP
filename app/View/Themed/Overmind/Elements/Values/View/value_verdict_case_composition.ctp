<?php
/**
 * How the two weights were reached, as a rail card.
 *
 * The same arithmetic the agreeing layout shows for its score, except
 * there are two of them and they are never added together. Each case
 * gets its own strip and its own total, so the tug-of-war bar in the
 * hero can be checked against the signals that produced it.
 *
 * Grouped by source panel rather than listed per signal: at rail width
 * a signal's own wording does not fit, and "which panel is carrying
 * this case" is the question the breakdown answers anyway.
 *
 * Derived from the cases rather than carried separately, so this card
 * and the two columns beside it cannot disagree.
 *
 * @var array $valueProfile
 */
$verdict = $valueProfile['verdict'];
$cases = $verdict['cases'] ?? array();

/*
 * One colour per panel, shared across both cases, so the same source
 * is the same colour on both sides and the two strips can be compared.
 */
$panelColours = array(
    'var(--event)',
    'var(--sighting)',
    'var(--galaxy)',
    'var(--correlation)',
    'var(--type)',
    'var(--enrichment)',
);
$colourFor = array();
$next = 0;

$sides = array();
foreach ($cases as $case) {
    $bySource = array();
    foreach ($case['rows'] as $row) {
        $source = $row['source'];
        if (!isset($colourFor[$source])) {
            $colourFor[$source] = $panelColours[
                $next++ % count($panelColours)
            ];
        }
        if (!isset($bySource[$source])) {
            $bySource[$source] = 0;
        }
        $bySource[$source] += (int)$row['points'];
    }
    arsort($bySource);
    $sides[] = array(
        'side' => $case['side'],
        'title' => $case['title'],
        'total' => (int)$case['weight'],
        'segments' => $bySource,
    );
}
?>
<?php if (!empty($sides)): ?>
    <div class="card shadow-sm mb-3 vp-panel vp-aside">

        <div class="vp-aside-head">
            <i class="fas fa-calculator"
               style="color: var(--enrichment);"></i>
            <span class="vp-aside-title">
                <?= h(sprintf(
                    __('How %1$s and %2$s were reached'),
                    $sides[0]['total'],
                    $sides[1]['total']
                )) ?>
            </span>
        </div>

        <div class="p-3 d-flex flex-column gap-3">

            <?php foreach ($sides as $s => $side):
                $span = max($side['total'], 1);
                ?>
                <div class="vp-case-comp vp-case-comp-<?=
                    h($side['side']) ?>">

                    <div class="vp-case-comp-head">
                        <span class="vp-case-comp-title">
                            <?= h($side['title']) ?>
                        </span>
                        <span class="vp-case-comp-total">
                            <?= h($side['total']) ?>
                        </span>
                    </div>

                    <div class="vp-composition">
                        <?php foreach ($side['segments']
                            as $source => $points): ?>
                            <span class="vp-composition-seg"
                                  style="width: <?= round(
                                      $points / $span * 100,
                                      2
                                  ) ?>%; --vp-seg-color: <?=
                                      h($colourFor[$source]) ?>;"
                                  title="<?= h(sprintf(
                                      '%1$s +%2$s',
                                      $source,
                                      $points
                                  )) ?>"></span>
                        <?php endforeach; ?>
                    </div>

                    <div class="vp-comp-legend">
                        <?php foreach ($side['segments']
                            as $source => $points): ?>
                            <div class="vp-comp-row">
                                <span class="vp-comp-swatch"
                                      style="--vp-seg-color: <?=
                                          h($colourFor[$source]) ?>;">
                                </span>
                                <span class="vp-comp-name">
                                    <?= h($source) ?>
                                </span>
                                <span class="vp-comp-pts">
                                    +<?= h($points) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            <?php endforeach; ?>

            <p class="vp-comp-note">
                <?= __(
                    'The two totals are never added or subtracted. A'
                    . ' single number would be the mean of two'
                    . ' incompatible readings.'
                ) ?>
            </p>

        </div>

    </div>
<?php endif; ?>
