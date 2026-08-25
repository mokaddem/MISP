<?php
/**
 * Where the organisations stand on this value.
 *
 * The panel opens on the split rather than on a summary of it. A mean
 * over a divided set is a reading nobody holds, and the Verdict tab
 * already says so about the same numbers; leading with an average here
 * would undo that one tab later. So the strip comes first, the mean is
 * a chip beside a sub-head, and the empty middle is drawn as a bracket
 * and again as a row in the table.
 *
 * Every number on this panel is computed at render — MISP stores no
 * mean, no buckets and no per-organisation rollup anywhere
 * (`05-analyst.md` §11) — which is what the header chip says out loud.
 *
 * The strip is a static inline SVG scale, not a chart. There is no
 * axis to zoom, no series to hover and nothing to fetch; Chart.js
 * would be a library standing between a reader and eight numbers.
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

/*
 * Geometry, in the SVG's own units. Named rather than inlined because
 * the marker radius is what decides when two organisations collide,
 * and a magic 17 in four places is how that stops being true.
 */
$stripW = 1180;
$stripH = 176;
$padX = 44;
$trackW = $stripW - ($padX * 2);
$markR = 17;
$markY = 56;
$trackY = 104;
$trackH = 20;

/**
 * @param int|float $score
 * @return float The x coordinate of a position on the 0-100 scale
 */
$x = function ($score) use ($padX, $trackW) {
    return round($padX + ($score / 100) * $trackW, 1);
};

/*
 * Merge markers that would overlap. Two organisations two points apart
 * are 22 units apart on a 1092-unit scale and their 34-unit discs sit
 * on top of each other, so the strip would show one marker and lose
 * the other — which is the one failure mode a panel about who holds
 * what position cannot have. They become one badged marker instead.
 */
$collide = ($markR * 2) + 4;
$groups = array();
foreach ($orgs as $org) {
    $at = $x($org['score']);
    $last = empty($groups) ? null : count($groups) - 1;
    if ($last !== null && abs($at - $groups[$last]['x']) < $collide) {
        $groups[$last]['members'][] = $org;
        // The group sits at the mean of its members, so a pair reads
        // as one position rather than as the first one that arrived.
        $sum = 0;
        foreach ($groups[$last]['members'] as $member) {
            $sum += $member['score'];
        }
        $groups[$last]['x'] = $x($sum / count($groups[$last]['members']));
        continue;
    }
    $groups[] = array('x' => $at, 'members' => array($org));
}

$readsWord = function ($reads) {
    if ($reads === 'malicious') {
        return __('supports the value');
    }
    if ($reads === 'benign') {
        return __('disputes the value');
    }
    return __('argues neither way');
};

$readsInk = function ($reads) {
    if ($reads === 'malicious') {
        return 'var(--vp-mal)';
    }
    if ($reads === 'benign') {
        return 'var(--vp-ben)';
    }
    return 'var(--bs-secondary-color)';
};

$readsBadge = function ($reads) {
    if ($reads === 'malicious') {
        return 'danger';
    }
    if ($reads === 'benign') {
        return 'success';
    }
    return 'secondary';
};

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
        <div class="p-3">

            <?php
            /*
             * ------------------------------------------------------
             * The position strip
             * ------------------------------------------------------
             * One marker per organisation on a 0-100 scale, a bracket
             * over the widest empty run, and the mean drawn inside
             * that bracket where it belongs: as a number sitting in a
             * space nobody occupies.
             */
            $gap = $aggregate['gap'];
            $showGap = $gap !== null && $gap['points'] >= 20;
            $meanX = $x($aggregate['mean']);

            $aria = array();
            foreach ($orgs as $org) {
                $aria[] = sprintf(
                    __('%1$s at %2$s'),
                    $org['org'],
                    $org['score']
                );
            }
            $ariaLabel = sprintf(
                __('Each organisation\'s opinion on the 0 to 100 scale:'
                    . ' %s.'),
                implode(__(', '), $aria)
            );

            $bands = array(
                array(0, 20, __('Strongly disagree')),
                array(21, 40, __('Disagree')),
                array(41, 60, __('Neutral')),
                array(61, 80, __('Agree')),
                array(81, 100, __('Strongly agree')),
            );
            ?>
            <svg class="vpa-strip"
                 viewBox="0 0 <?= $stripW ?> <?= $stripH ?>"
                 role="img" aria-label="<?= h($ariaLabel) ?>">

                <rect x="<?= $padX ?>" y="<?= $trackY ?>"
                      width="<?= $trackW ?>" height="<?= $trackH ?>"
                      rx="3" style="fill: var(--bs-secondary-bg);"/>

                <?php foreach ($bands as $band): ?>
                    <text x="<?= $x(($band[0] + $band[1]) / 2) ?>"
                          y="<?= $trackY + 14 ?>" text-anchor="middle"
                          font-size="10.5"
                          style="fill: var(--bs-secondary-color);"><?=
                        h($band[2])
                    ?></text>
                <?php endforeach; ?>

                <?php foreach (array(20, 40, 60, 80) as $edge): ?>
                    <line x1="<?= $x($edge) ?>" y1="<?= $trackY ?>"
                          x2="<?= $x($edge) ?>"
                          y2="<?= $trackY + $trackH ?>"
                          style="stroke: var(--bs-body-bg);
                                 stroke-width: 2;"/>
                <?php endforeach; ?>

                <?php foreach (array(0, 25, 50, 75, 100) as $mark): ?>
                    <text x="<?= $x($mark) ?>" y="<?= $trackY + 38 ?>"
                          text-anchor="middle" font-size="10.5"
                          style="fill: var(--bs-secondary-color);
                                 font-family:
                                     var(--bs-font-monospace);"><?=
                        $mark
                    ?></text>
                <?php endforeach; ?>

                <?php if ($showGap): ?>
                    <?php
                    $gapFrom = $x($gap['from']);
                    $gapTo = $x($gap['to']);
                    $gapMid = ($gapFrom + $gapTo) / 2;
                    ?>
                    <line x1="<?= $gapFrom ?>" y1="82"
                          x2="<?= $gapTo ?>" y2="82"
                          style="stroke: var(--bs-secondary-color);
                                 stroke-width: 1; stroke-dasharray: 4 3;
                                 opacity: .8;"/>
                    <line x1="<?= $gapFrom ?>" y1="78"
                          x2="<?= $gapFrom ?>" y2="86"
                          style="stroke: var(--bs-secondary-color);
                                 stroke-width: 1;"/>
                    <line x1="<?= $gapTo ?>" y1="78"
                          x2="<?= $gapTo ?>" y2="86"
                          style="stroke: var(--bs-secondary-color);
                                 stroke-width: 1;"/>
                    <text x="<?= $gapMid ?>" y="72" text-anchor="middle"
                          font-size="11.5" font-weight="600"
                          style="fill: var(--bs-secondary-color);"><?=
                        h(sprintf(
                            __('%s gap'),
                            __n(
                                '%s-point',
                                '%s-point',
                                $gap['points'],
                                $gap['points']
                            )
                        ))
                    ?></text>
                <?php endif; ?>

                <?php
                /*
                 * The mean, as a marker rather than as a headline. It
                 * is hollow and struck through when no organisation is
                 * within half a band of it, which is the only case
                 * where "a reading nobody holds" is a claim about this
                 * value rather than a slogan about averages.
                 */
                ?>
                <path d="M <?= $meanX ?> 84 l 7 6 l -7 6 l -7 -6 z"
                      style="fill: var(--bs-body-bg);
                             stroke: var(--bs-secondary-color);
                             stroke-width: 1.5;"/>
                <text x="<?= $meanX ?>" y="<?= $stripH - 4 ?>"
                      text-anchor="middle" font-size="11.5"
                      style="fill: var(--bs-secondary-color);"><?=
                    h($aggregate['mean_orphan']
                        ? sprintf(
                            __('mean %s — a reading no organisation holds'),
                            $aggregate['mean_label']
                        )
                        : sprintf(
                            __('mean %s'),
                            $aggregate['mean_label']
                        ))
                ?></text>

                <?php foreach ($groups as $group):
                    $members = $group['members'];
                    $merged = count($members) > 1;
                    $at = $group['x'];

                    $sides = array();
                    $names = array();
                    $scores = array();
                    $titleBits = array();
                    foreach ($members as $member) {
                        $sides[$member['reads']] = true;
                        $names[] = $member['org'];
                        $scores[] = $member['score'];
                        $titleBits[] = sprintf(
                            __('%1$s · %2$s/100 · %3$s'),
                            $member['org'],
                            $member['score'],
                            $member['last']
                        );
                    }
                    $ink = count($sides) === 1
                        ? $readsInk(key($sides))
                        : 'var(--bs-secondary-color)';
                    $fill = 'color-mix(in srgb, ' . $ink
                        . ' 20%, var(--bs-body-bg))';
                    ?>
                    <g class="vpa-mark" data-vp-a-mark="<?=
                           h(implode('|', $names)) ?>">
                        <title><?= h(implode(' · ', $titleBits)) ?></title>
                        <line x1="<?= $at ?>"
                              y1="<?= $markY + $markR + 4 ?>"
                              x2="<?= $at ?>" y2="<?= $trackY ?>"
                              style="stroke: <?= $ink ?>;
                                     stroke-width: 1.5; opacity: .55;"/>
                        <circle cx="<?= $at ?>" cy="<?= $markY ?>"
                                r="<?= $markR ?>"
                                style="fill: <?= $fill ?>;
                                       stroke: <?= $ink ?>;
                                       stroke-width: 2.5;"/>
                        <text x="<?= $at ?>" y="<?= $markY + 5 ?>"
                              text-anchor="middle" font-size="13"
                              font-weight="700"
                              style="fill: var(--bs-body-color);"><?=
                            $merged
                                ? '&#215;' . count($members)
                                : (int)$members[0]['score']
                        ?></text>
                        <text x="<?= $at ?>" y="22" text-anchor="middle"
                              font-size="13" font-weight="600"
                              style="fill: var(--bs-body-color);"><?=
                            h($merged
                                ? sprintf(
                                    __n(
                                        '%s organisation',
                                        '%s organisations',
                                        count($members),
                                        count($members)
                                    )
                                )
                                : $members[0]['org'])
                        ?></text>
                        <text x="<?= $at ?>" y="35" text-anchor="middle"
                              font-size="10.5"
                              style="fill: var(--bs-secondary-color);"><?=
                            h($merged
                                ? min($scores) . '–' . max($scores)
                                : $members[0]['last'])
                        ?></text>
                    </g>
                <?php endforeach; ?>

            </svg>

            <div class="row g-4 mt-0">

                <?php
                /*
                 * ------------------------------------------------
                 * The histogram
                 * ------------------------------------------------
                 * The same ten buckets and the same classes the
                 * Verdict tab draws, so a reader who has seen one
                 * recognises the other. Empty bands are a 2% stub
                 * rather than nothing, because the gaps are the
                 * point and a missing bar reads as a missing band.
                 */
                $tallest = 1;
                foreach ($aggregate['buckets'] as $bucket) {
                    $tallest = max($tallest, (int)$bucket['count']);
                }
                ?>
                <div class="col-lg-4">
                    <div class="vp-subhead"><?=
                        __('Distribution, 0–100 in ten bands')
                    ?></div>

                    <div class="vpa-hist-counts">
                        <?php foreach ($aggregate['buckets'] as $bucket): ?>
                            <span><?= (int)$bucket['count'] === 0
                                ? '&middot;'
                                : (int)$bucket['count'] ?></span>
                        <?php endforeach; ?>
                    </div>

                    <div class="vp-hist" style="height: 64px;">
                        <?php foreach ($aggregate['buckets'] as $b => $bucket):
                            $count = (int)$bucket['count'];
                            $side = $b < 5 ? 'ben' : 'mal';
                            ?>
                            <span class="vp-hist-bar vp-hist-bar-<?= $side ?><?=
                                $count === 0 ? ' vp-hist-bar-empty' : '' ?>"
                                  style="height: <?= $count === 0
                                      ? 2
                                      : round($count / $tallest * 100, 2)
                                  ?>%;"
                                  title="<?= h(sprintf(
                                      __n(
                                          '%1$s opinion in %2$s',
                                          '%1$s opinions in %2$s',
                                          $count
                                      ),
                                      $count,
                                      $bucket['label']
                                  )) ?>"></span>
                        <?php endforeach; ?>
                    </div>

                    <div class="vp-hist-axis">
                        <span>0</span><span>50</span><span>100</span>
                    </div>

                    <p class="vp-aside-note"><?= h(sprintf(
                        __(
                            '%1$s. The same ten buckets the Verdict tab'
                            . ' draws, so a reader who has seen one'
                            . ' recognises the other.'
                        ),
                        sprintf(
                            __('%1$s, %2$s'),
                            __n(
                                '%s position',
                                '%s positions',
                                count($aggregate['clusters']),
                                count($aggregate['clusters'])
                            ),
                            __n(
                                '%s empty band',
                                '%s empty bands',
                                $aggregate['empty_bands'],
                                $aggregate['empty_bands']
                            )
                        )
                    )) ?></p>
                </div>

                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-2 mb-2
                                flex-wrap">
                        <span class="vp-subhead mb-0"><?=
                            __('Per organisation')
                        ?></span>
                        <span class="vpa-mean<?= $aggregate['mean_orphan']
                                  ? ' vpa-mean-orphan' : '' ?>"
                              title="<?= h($aggregate['mean_orphan']
                                  ? __(
                                      'Shown because the aggregate is'
                                      . ' specified, struck through'
                                      . ' because it describes nobody:'
                                      . ' the nearest opinion is more'
                                      . ' than half a band away.'
                                  )
                                  : __(
                                      'Shown because the aggregate is'
                                      . ' specified. On this value an'
                                      . ' organisation does hold a'
                                      . ' position within half a band'
                                      . ' of it.'
                                  )) ?>">
                            <?= h(__('mean')) ?>
                            <span class="vpa-mean-value"><?=
                                h($aggregate['mean_label'])
                            ?></span>
                            <span><?= h(sprintf(
                                __('over %s'),
                                __n(
                                    '%s opinion',
                                    '%s opinions',
                                    $aggregate['n'],
                                    $aggregate['n']
                                )
                            )) ?></span>
                        </span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0
                                      vp-table">
                            <thead>
                                <tr>
                                    <th><?= __('Organisation') ?></th>
                                    <th><?= __('Opinion') ?></th>
                                    <th><?= __('Reads the value as') ?></th>
                                    <th><?= __('Position') ?></th>
                                    <th class="text-center"><?=
                                        __('Notes')
                                    ?></th>
                                    <th><?= __('Last activity') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $previous = null;
                                foreach ($orgs as $org):
                                    /*
                                     * The empty middle, rendered as a
                                     * row. It is the most important
                                     * thing on the panel, and a table
                                     * that simply runs 60 into 30 is
                                     * where it would disappear.
                                     */
                                    if (
                                        $previous !== null
                                        && $previous['reads'] !== 'benign'
                                        && $org['reads'] === 'benign'
                                    ):
                                        ?>
                                        <tr class="vpa-gaprow">
                                            <td colspan="6"
                                                class="text-center small
                                                       text-body-secondary
                                                       py-1"><?=
                                                h(sprintf(
                                                    __(
                                                        'no organisation'
                                                        . ' between %1$s'
                                                        . ' and %2$s'
                                                    ),
                                                    $org['score'],
                                                    $previous['score']
                                                ))
                                            ?></td>
                                        </tr>
                                    <?php endif;
                                    $previous = $org;
                                    ?>
                                    <tr data-vp-a-org="<?= h($org['org']) ?>">
                                        <td>
                                            <span class="misp-icon
                                                         misp-icon-organisation
                                                         misp-simple me-1">
                                            </span><?= h($org['org']) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?=
                                                $readsBadge($org['reads'])
                                            ?>-subtle text-<?=
                                                $readsBadge($org['reads'])
                                            ?>-emphasis border border-<?=
                                                $readsBadge($org['reads'])
                                            ?>-subtle fw-semibold">
                                                <?= h($org['label']) ?>
                                                &middot;
                                                <?= h($org['score']) ?>/100
                                            </span>
                                        </td>
                                        <td class="text-body-secondary"><?=
                                            h($readsWord($org['reads']))
                                        ?></td>
                                        <td>
                                            <span class="vp-opinion">
                                                <span class="vp-opinion-track">
                                                    <span
                                                        class="vp-opinion-fill"
                                                        style="width: <?=
                                                            (int)$org['score']
                                                        ?>%;"></span>
                                                </span>
                                                <span class="vp-opinion-value">
                                                    <?= h($org['score']) ?>/100
                                                </span>
                                            </span>
                                        </td>
                                        <td class="text-center"><?=
                                            h($org['notes'])
                                        ?></td>
                                        <td class="font-monospace
                                                   text-body-secondary"><?=
                                            h($org['last'])
                                        ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p class="vp-aside-note"><?= __(
                        'Colour is the reading, not the agreement: above'
                        . ' 50 argues the value is hostile, below 50'
                        . ' argues it is not — the two hues the Verdict'
                        . ' tab already uses. An opinion written on a'
                        . ' note takes neither.'
                    ) ?></p>
                </div>

            </div>

        </div>
    <?php endif; ?>

</div>
