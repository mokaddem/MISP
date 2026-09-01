<?php
/**
 * Where the organisations stand on this value.
 *
 * The panel opens on the split rather than on a summary of it. A mean
 * over a divided set is a reading nobody holds, and the Verdict tab
 * already says so about the same numbers; leading with an average here
 * would undo that one tab later.
 *
 * Two objects, in the order a card is scanned (`05-analyst.md` §16.3):
 *
 * 1. The tug-bar — one stacked bar sized by *headcount*, with the
 *    split stated beside it in words. It answers "is this set divided,
 *    and how lopsided" before the reader looks at a single row.
 * 2. The ledger — one row per organisation on a shared 0-100 lane. A
 *    bar grows from the 50 pivot to that organisation's score, so
 *    direction is the side and length is the conviction, and the empty
 *    middle is a shaded column crossing every lane.
 *
 * The two are deliberately not the same width. The tug-bar spans the
 * panel's whole padded width while the ruler and lanes stay inset in
 * their own grid column, because the bar is sized by headcount and the
 * lane axis by score: stack them flush and a one-against-three split
 * puts a segment boundary at 25% of the axis, which reads as "the set
 * divides at 25". The name column and the three trailing columns inset
 * the lane asymmetrically, so the two centres cannot coincide. The
 * caption under the bar states the units as the second guard.
 *
 * There is no histogram. Ten bands over three or four opinions is a
 * chart of almost nothing, and it was the one place on this panel that
 * painted the axis the other way round — below 50 green — against the
 * badges beside it. Each score is now drawn once, as a position, and
 * said once, as a numeral.
 *
 * Every number here is computed at render — MISP stores no mean, no
 * buckets and no per-organisation rollup anywhere (`05-analyst.md`
 * §11) — which is what the header chip says out loud.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewAnalystStanding.
 *
 * @var array $valueProfile
 */
$analyst = $valueProfile['analyst'];
$standing = $analyst['standing'];
$orgs = $standing['orgs'];
$aggregate = $standing['aggregate'];

/**
 * Which way an opinion argues, named for what the reader is told
 * rather than for the fixture's own `malicious` / `benign`.
 *
 * The indirection is worth removing rather than carrying: MISP's scale
 * runs from disagreement to agreement with what the value asserts, so
 * an opinion above 50 is the *agreeing* one and takes the green the
 * Overview card uses for agreement. Mapping `malicious` to a green
 * token two hops away is how the deleted histogram came to disagree
 * with the table beside it.
 *
 * @param string $reads malicious | benign | none
 * @return string agree | dispute | neither
 */
$sideOf = function ($reads) {
    if ($reads === 'malicious') {
        return 'agree';
    }
    if ($reads === 'benign') {
        return 'dispute';
    }
    return 'neither';
};

$sideWord = function ($side) {
    if ($side === 'agree') {
        return __('agrees');
    }
    if ($side === 'dispute') {
        return __('disputes');
    }
    return __('takes no side');
};

/*
 * How long ago, in the coarsest unit that is still true. An opinion
 * held for three months and one held yesterday are different evidence,
 * and the built panel rendered both as the same plain date.
 */
$staleAfter = 60;

$ageLabel = function ($days) {
    if ($days === null) {
        return null;
    }
    if ($days <= 0) {
        return __('today');
    }
    if ($days === 1) {
        return __('yesterday');
    }
    if ($days < 31) {
        return sprintf(__n('%d day ago', '%d days ago', $days), $days);
    }
    $months = (int)round($days / 30.44);
    $months = max(1, $months);
    return sprintf(
        __n('%d month ago', '%d months ago', $months),
        $months
    );
};

/*
 * The five band words, and where each one sits. MISP splits them at
 * 20/40/60/80 while the reading splits at 50, which is why a band word
 * is never coloured on this panel: `Neutral` covers 41-60 and so lands
 * on both sides of the pivot. The footnote says so.
 */
$bands = array(
    array(0, 20, __('Strongly disagree')),
    array(20, 40, __('Disagree')),
    array(40, 60, __('Neutral')),
    array(60, 80, __('Agree')),
    array(80, 100, __('Strongly agree')),
);

$subtitle = $aggregate === null
    ? h(__('No opinion on this value from any organisation'))
    : h($aggregate['note']);

/*
 * The chip is a statement about numbers on the panel, so a panel with
 * no numbers on it does not get to make it.
 */
$headerExtra = $aggregate === null ? null
    : '<span class="badge bg-body-tertiary text-body-secondary'
        . ' border fw-normal" title="'
        . h(__(
            'MISP computes no mean, no distribution and no'
            . ' per-organisation rollup over analyst data. Every number'
            . ' on this panel is derived from the opinions themselves'
            . ' when the page is built.'
        )) . '">' . h(__('computed at render')) . '</span>';
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--analystData);"
     data-vp-analyst-standing>

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Where the organisations stand'),
        'panelIcon' => 'fas fa-arrows-left-right-to-line',
        'panelColor' => 'var(--analystData)',
        'panelSub' => $subtitle,
        'panelExtra' => $headerExtra,
    )) ?>

    <?php if ($aggregate === null): ?>
        <div class="vp-empty">
            <i class="fas fa-arrows-left-right-to-line"></i>
            <span><?=
                __('No organisation has recorded an opinion on this value.')
            ?></span>
        </div>
    <?php else: ?>
        <?php
        /*
         * The split, counted off the rows this panel is about to
         * render. Nothing else computes it, so nothing else can
         * disagree with it.
         */
        $bySide = array('agree' => 0, 'dispute' => 0, 'neither' => 0);
        foreach ($orgs as $org) {
            $bySide[$sideOf($org['reads'])]++;
        }
        $total = count($orgs);

        /*
         * What the bar amounts to, in one clause, so a reader who has
         * taken in neither the bar nor the lanes still has the answer.
         */
        if ($bySide['dispute'] === 0) {
            $verdict = __('every organisation agrees');
        } elseif ($bySide['agree'] === 0) {
            $verdict = __('every organisation disputes');
        } elseif ($bySide['agree'] === $bySide['dispute']) {
            $verdict = sprintf(
                __('an even split, %s each way'),
                $bySide['agree']
            );
        } else {
            $minor = min($bySide['agree'], $bySide['dispute']);
            $verdict = sprintf(
                $bySide['agree'] > $bySide['dispute']
                    ? __n(
                        'most agree; %d of %d does not',
                        'most agree; %d of %d do not',
                        $minor
                    )
                    : __n(
                        'most dispute; %d of %d does not',
                        'most dispute; %d of %d do not',
                        $minor
                    ),
                $minor,
                $total
            );
        }

        $gap = $aggregate['gap'];
        $showGap = $gap !== null && $gap['points'] >= 20;
        ?>
        <div class="p-3">

            <?php
            /*
             * --------------------------------------------------------
             * The tug-bar
             * --------------------------------------------------------
             * Full panel width, and never aligned to the lane axis
             * below it — see the note at the head of this file.
             */
            $segments = array(
                array('dispute', $bySide['dispute'], __('dispute')),
                array('neither', $bySide['neither'], __('neither')),
                array('agree', $bySide['agree'], __('agree')),
            );
            ?>
            <div class="vpa-tugblock">
                <div class="vpa-tuglead">
                    <span class="vp-subhead mb-0"><?= __('The split') ?></span>
                    <span class="vpa-verdict"><?= h($verdict) ?></span>
                </div>

                <div class="vpa-tug">
                    <?php foreach ($segments as $segment):
                        if ($segment[1] === 0) {
                            continue;
                        }
                        $width = round($segment[1] / $total * 100, 3);
                        ?>
                        <div class="vpa-tug-seg vpa-s-<?= $segment[0] ?><?=
                                  $segment[0] === 'agree' ? ' vpa-tug-end' : ''
                              ?>"
                             style="width: <?= $width ?>%;"
                             title="<?= h(sprintf(
                                 __n(
                                     '%1$d organisation %2$s',
                                     '%1$d organisations %2$s',
                                     $segment[1]
                                 ),
                                 $segment[1],
                                 $sideWord($segment[0])
                             )) ?>">
                            <?php if ($segment[0] === 'agree'): ?>
                                <span><?= h($segment[2]) ?></span>
                                <span class="vpa-tug-n"><?=
                                    (int)$segment[1]
                                ?></span>
                            <?php else: ?>
                                <span class="vpa-tug-n"><?=
                                    (int)$segment[1]
                                ?></span>
                                <span><?= h($segment[2]) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="vpa-tug-cap">
                    <span><?= __('disputes') ?></span>
                    <span><?= __('sized by headcount, not by score') ?></span>
                    <span><?= __('agrees') ?></span>
                </div>
            </div>

            <?php
            /*
             * --------------------------------------------------------
             * The ledger
             * --------------------------------------------------------
             * A flat grid: five cells in the header row and five per
             * organisation, with the void, the pivot and the mean
             * drawn inside each lane. They are per-lane rather than
             * one element spanning the rows because an explicitly
             * placed child in an otherwise auto-placed grid displaces
             * every cell after it; adjacent lanes stack the slices
             * into one continuous column anyway.
             */
            $aria = array();
            foreach ($orgs as $org) {
                $aria[] = sprintf(
                    __('%1$s at %2$s'),
                    $org['org'],
                    $org['score']
                );
            }
            ?>
            <div class="vpa-ledger-scroll">
                <div class="vpa-ledger"
                     role="group"
                     aria-label="<?= h(sprintf(
                         __('Each organisation\'s opinion on the 0 to 100'
                             . ' scale: %s.'),
                         implode(__(', '), $aria)
                     )) ?>">

                    <div class="vpa-h"><?= __('Organisation') ?></div>

                    <div class="vpa-ruler">
                        <?php foreach ($bands as $b => $band):
                            $edge = '';
                            $at = ($band[0] + $band[1]) / 2;
                            if ($b === 0) {
                                $edge = ' vpa-edge-l';
                                $at = 0;
                            } elseif ($b === count($bands) - 1) {
                                $edge = ' vpa-edge-r';
                                $at = 100;
                            }
                            ?>
                            <span class="vpa-ruler-band<?= $edge ?>"
                                  style="left: <?= $at ?>%;"><?=
                                h($band[2])
                            ?></span>
                        <?php endforeach; ?>

                        <?php foreach (array(0, 25, 50, 75, 100) as $tick):
                            $edge = '';
                            if ($tick === 0) {
                                $edge = ' vpa-edge-l';
                            } elseif ($tick === 100) {
                                $edge = ' vpa-edge-r';
                            }
                            ?>
                            <span class="vpa-ruler-tick<?= $edge ?>"
                                  style="left: <?= $tick ?>%;"><?=
                                $tick
                            ?></span>
                        <?php endforeach; ?>

                        <?php if ($showGap): ?>
                            <span class="vpa-ruler-void"
                                  style="left: <?=
                                      ($gap['from'] + $gap['to']) / 2
                                  ?>%;"><?= h(sprintf(
                                __n(
                                    'no opinion falls in this %d point',
                                    'no opinion falls in these %d points',
                                    $gap['points']
                                ),
                                $gap['points']
                            )) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="vpa-h"><?= __('Reads it as') ?></div>
                    <div class="vpa-h vpa-r"><?= __('Notes') ?></div>
                    <div class="vpa-h vpa-r"><?= __('Last activity') ?></div>

                    <?php foreach ($orgs as $org):
                        $side = $sideOf($org['reads']);
                        $score = (int)$org['score'];
                        $from = min(50, $score);
                        $to = max(50, $score);
                        $days = isset($org['days']) ? $org['days'] : null;
                        $stale = $days !== null && $days >= $staleAfter;
                        ?>
                        <div class="vpa-row"
                             data-vp-a-org="<?= h($org['org']) ?>">

                            <div class="vpa-cell vpa-org">
                                <span class="misp-icon
                                             misp-icon-organisation
                                             misp-simple"></span>
                                <span class="vp-min-w-0 text-truncate"><?=
                                    h($org['org'])
                                ?></span>
                            </div>

                            <div class="vpa-lane vpa-s-<?= $side ?>"
                                 title="<?= h(sprintf(
                                     __('%1$s · %2$s/100 · %3$s'),
                                     $org['org'],
                                     $score,
                                     $org['label']
                                 )) ?>">
                                <?php if ($showGap): ?>
                                    <span class="vpa-lane-void"
                                          style="left: <?= $gap['from'] ?>%;
                                                 right: <?=
                                                     100 - $gap['to']
                                                 ?>%;"></span>
                                <?php endif; ?>
                                <span class="vpa-lane-pivot"
                                      style="left: 50%;"></span>
                                <span class="vpa-lane-mean"
                                      style="left: <?=
                                          $aggregate['mean']
                                      ?>%;"></span>
                                <span class="vpa-lane-track"></span>
                                <span class="vpa-lane-bar"
                                      style="left: <?= $from ?>%;
                                             right: <?= 100 - $to ?>%;"></span>
                                <span class="vpa-lane-dot"
                                      style="left: <?= $score ?>%;"></span>
                                <span class="vpa-lane-val"
                                      style="<?= $score >= 50
                                          ? 'left: calc(' . $score
                                              . '% + 13px);'
                                          : 'right: calc(' . (100 - $score)
                                              . '% + 13px);'
                                      ?>"><?= $score ?></span>
                            </div>

                            <div class="vpa-cell">
                                <span class="vpa-reading vpa-s-<?= $side ?>">
                                    <i></i><?= h($sideWord($side)) ?>
                                </span>
                                <span class="vpa-band"><?=
                                    h($org['label'])
                                ?></span>
                            </div>

                            <div class="vpa-cell vpa-r font-monospace"><?=
                                $org['notes'] > 0
                                    ? (int)$org['notes']
                                    : '<span class="text-body-secondary">'
                                        . '&mdash;</span>'
                            ?></div>

                            <div class="vpa-cell vpa-r">
                                <span class="vpa-age<?=
                                          $stale ? ' vpa-age-stale' : ''
                                      ?>"
                                      title="<?= h($org['last']) ?>">
                                    <?php if ($stale): ?>
                                        <i class="vpa-age-dot"></i>
                                    <?php endif; ?>
                                    <?= h($days === null
                                        ? $org['last']
                                        : $ageLabel($days)) ?>
                                </span>
                            </div>

                        </div>
                    <?php endforeach; ?>

                </div>
            </div>

            <?php
            /*
             * The aggregate, as a row of chips rather than a headline.
             * The mean keeps `.vpa-mean`, so it is struck through here
             * on exactly the values where it describes nobody.
             */
            ?>
            <div class="vpa-stats">
                <span class="vpa-stat">
                    <b><?= count($aggregate['clusters']) ?></b>
                    <?= h(__n(
                        'position',
                        'positions',
                        count($aggregate['clusters'])
                    )) ?>
                </span>

                <?php if ($showGap): ?>
                    <span class="vpa-stat vpa-stat-void">
                        <b><?= (int)$gap['points'] ?></b>
                        <?= __('points of empty middle') ?>
                    </span>
                <?php endif; ?>

                <span class="vpa-stat">
                    <b><?= (int)$aggregate['empty_bands'] ?></b>
                    <?= __('of ten bands unoccupied') ?>
                </span>

                <span class="vpa-mean<?= $aggregate['mean_orphan']
                          ? ' vpa-mean-orphan' : '' ?>"
                      title="<?= h($aggregate['mean_orphan']
                          ? __(
                              'Shown because the aggregate is specified,'
                              . ' struck through because it describes'
                              . ' nobody: the nearest opinion is more'
                              . ' than half a band away.'
                          )
                          : __(
                              'Shown because the aggregate is specified.'
                              . ' On this value an organisation does hold'
                              . ' a position within half a band of it.'
                          )) ?>">
                    <span class="vpa-mean-value"><?=
                        h($aggregate['mean_label'])
                    ?></span>
                    <span><?= h($aggregate['mean_orphan']
                        ? __('mean — no organisation holds it')
                        : __('mean')) ?></span>
                </span>
            </div>

            <p class="vp-aside-note"><?= __(
                'Above 50 agrees with what the value asserts; below 50'
                . ' disputes it — the same hues the Overview card uses'
                . ' for the bands it names. The band word is MISP\'s own'
                . ' five-step scale and splits at 20/40/60/80, so a'
                . ' Neutral opinion can sit on either side of the pivot:'
                . ' 45 disputes, 60 agrees. An opinion written on a note'
                . ' rates the note, not the value, and is not counted'
                . ' here.'
            ) ?></p>

        </div>
    <?php endif; ?>

</div>
