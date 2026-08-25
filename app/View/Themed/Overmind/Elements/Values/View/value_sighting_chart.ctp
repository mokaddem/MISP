<?php
/**
 * The overlay: what was reported, and what it did to the score.
 *
 * MISP has computed a decay curve per attribute for years and has never
 * had anywhere to draw it against the sightings that move it. That is
 * the whole argument for this panel, and it is why the curve is not in
 * a card of its own: on one axis pair the reader can see a burst of
 * reports lift a line back over its threshold, and — the harder thing —
 * see a false positive land and lift nothing.
 *
 * Both axes are captioned in words above the chart. An overlay with two
 * scales that labels neither is a trick, and the left one is a count
 * while the right one is a score out of a hundred.
 *
 * Chart.js rather than the mockup's inline SVG (`00-shared.md` §7): the
 * artifact could not fetch a script, the page already loads one. Every
 * colour goes in as `var(--…)` and is resolved against the canvas at
 * init, so the chart follows the theme it cannot inherit — there is not
 * a hex value in this file.
 *
 * Lazily loaded from ValuesController::viewSightingChart.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$series = $valueProfile['sighting_series'];
$sightings = $valueProfile['sightings'];
$decay = $valueProfile['decay'];
$notes = $valueProfile['sighting_notes'];
$positive = $sightings['total'] - $sightings['fp'] - $sightings['expiration'];

/*
 * The three types, with the reason each one is dead when it is. A count
 * of zero is rendered disabled rather than hidden: "nobody has ever
 * filed an expiration for this value" is a fact about the value, and
 * dropping the control drops the fact with it.
 */
$toggles = array(
    array(
        'key' => 'sighting',
        'label' => __('Sightings'),
        'count' => $positive,
        'why' => __('No sighting has been reported for this value'),
    ),
    array(
        'key' => 'fp',
        'label' => __('False positives'),
        'count' => $sightings['fp'],
        'why' => __('No false positive has been reported for this value'),
    ),
    array(
        'key' => 'expiration',
        'label' => __('Expirations'),
        'count' => $sightings['expiration'],
        'why' => __('No expiration sighting has been reported for this'
            . ' value'),
    ),
);

$controls = '';
foreach ($toggles as $toggle) {
    $dead = $toggle['count'] === 0;
    $controls .= '<button type="button" class="vp-sight-toggle"'
        . ' data-vp-sight-type="' . h($toggle['key']) . '"'
        . ' aria-pressed="' . ($dead ? 'false' : 'true') . '"'
        . ($dead ? ' disabled title="' . h($toggle['why']) . '"' : '')
        . '><span class="vp-sight-toggle-swatch'
        . ' vp-sight-swatch-' . h($toggle['key']) . '"></span>'
        . h($toggle['label'])
        . '<span class="vp-sight-toggle-count">'
        . h($toggle['count']) . '</span></button>';
}

if ($series !== null && count($series['ranges']) > 1) {
    $controls .= '<select class="form-select form-select-sm'
        . ' vp-sight-range" data-vp-sight-range'
        . ' aria-label="' . h(__('Time range')) . '">';
    foreach ($series['ranges'] as $range) {
        $controls .= '<option value="' . h($range['key']) . '"'
            . ($range['key'] === $series['default_range']
                ? ' selected'
                : '')
            . '>' . h($range['label']) . '</option>';
    }
    $controls .= '</select>';
}

$subtitle = $sightings['total'] === 0
    ? h(__('Never sighted'))
    : h(sprintf(
        __('%1$s · %2$s, %3$s, %4$s · last one %5$s'),
        __n(
            '%s report',
            '%s reports',
            $sightings['total'],
            $sightings['total']
        ),
        __n('%s sighting', '%s sightings', $positive, $positive),
        __n(
            '%s false positive',
            '%s false positives',
            $sightings['fp'],
            $sightings['fp']
        ),
        __n(
            '%s expiration',
            '%s expirations',
            $sightings['expiration'],
            $sightings['expiration']
        ),
        $sightings['last']
    ));

/*
 * Chart.js wants one array per dataset, and the fixture stores one
 * bucket per column, so the transpose happens here rather than in
 * JavaScript: the template is where the shape of the data is already
 * known, and a range switch should not re-derive it every time.
 */
$payload = null;
if ($series !== null) {
    $ranges = array();
    foreach ($series['ranges'] as $range) {
        $orgSeries = array();
        $orgCounts = array();
        foreach ($series['orgs'] as $i => $org) {
            $orgSeries[$i] = array();
            $orgCounts[$i] = 0;
        }
        $fp = array();
        $expiration = array();
        foreach ($range['buckets'] as $bucket) {
            foreach ($series['orgs'] as $i => $org) {
                $orgSeries[$i][] = $bucket['by_org'][$i];
                $orgCounts[$i] += $bucket['by_org'][$i];
            }
            $fp[] = $bucket['fp'];
            $expiration[] = $bucket['expiration'];
        }
        $ranges[] = array(
            'key' => $range['key'],
            'from' => $range['from'],
            'to' => $range['to'],
            'step' => $range['step'],
            'stepLabel' => $range['step_label'],
            'labels' => array_column($range['buckets'], 'label'),
            'starts' => array_column($range['buckets'], 'from'),
            'ends' => array_column($range['buckets'], 'to'),
            'org' => array_values($orgSeries),
            'orgCounts' => array_values($orgCounts),
            'fp' => $fp,
            'fpCount' => array_sum($fp),
            'expiration' => $expiration,
            'expirationCount' => array_sum($expiration),
            'curves' => $range['curves'],
            'inRange' => $range['in_range'],
        );
    }
    $payload = array(
        'orgs' => $series['orgs'],
        'ranges' => $ranges,
        'default' => $series['default_range'],
        'models' => $decay,
        'labels' => array(
            'threshold' => __('threshold %s'),
            'perDay' => __('Sightings per day, stacked by organisation'),
            'perWeek' => __('Sightings per week, stacked by organisation'),
            'falsePositive' => __('False positive'),
            'expiration' => __('Expiration'),
        ),
    );
}
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--sighting);"
     <?= $payload === null ? '' : 'data-vp-sight' ?>>

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Sightings over time'),
        'panelIcon' => 'misp-icon misp-icon-sighting misp-simple',
        'panelColor' => 'var(--sighting)',
        'panelSub' => $subtitle,
        'panelExtra' => $series === null ? null : $controls,
    )) ?>

    <?php if ($series === null): ?>

        <?php
        /*
         * No attribute, so no axis. A chart drawn over the last 90 days
         * for a value that has never existed would be inventing the
         * window as well as the emptiness.
         */
        ?>
        <div class="p-3">
            <div class="vp-empty">
                <span class="misp-icon misp-icon-sighting misp-simple"></span>
                <span><?= __('Nobody has reported seeing this.') ?></span>
            </div>
        </div>

    <?php else: ?>

        <div class="p-3">

            <div class="vp-sight-axes">
                <span class="vp-subhead" data-vp-sight-axis-left>
                    <?= __('Sightings per day, stacked by organisation') ?>
                </span>
                <span class="vp-subhead vp-sight-axis-right">
                    <?= __('Decay score · 0–100') ?>
                </span>
            </div>

            <div class="vp-chart vp-sight-main">
                <canvas id="vp-sight-main" role="img"
                        aria-label="<?= h(__(
                            'Sightings per organisation over time, with each'
                            . ' decaying model\'s score overlaid'
                        )) ?>"></canvas>
                <?php if ($sightings['total'] === 0): ?>
                    <?php
                    /*
                     * The axes stay. "No sightings" and "no score" are
                     * different claims, and the rail beside this still
                     * carries a score — MISP decays an un-sighted
                     * attribute from its own first-seen date.
                     */
                    ?>
                    <div class="vp-sight-overlay">
                        <?= __('Nobody has reported seeing this.') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="vp-sight-nav" data-vp-sight-nav>
                <canvas id="vp-sight-nav" role="img"
                        aria-label="<?= h(__(
                            'All sightings in the selected range, as a'
                            . ' navigator'
                        )) ?>"></canvas>
                <div class="vp-sight-brush" data-vp-sight-brush>
                    <div class="vp-sight-mask" data-vp-sight-mask-left></div>
                    <div class="vp-sight-window" data-vp-sight-handle></div>
                    <div class="vp-sight-mask" data-vp-sight-mask-right></div>
                </div>
            </div>

            <div class="vp-sight-nav-caption">
                <span>
                    <?= __('Drag the rail to change the range — the table'
                        . ' below follows it') ?>
                </span>
                <span class="vp-sight-nav-window" data-vp-sight-window>
                    <?= h($series['ranges'][0]['from']) ?>
                    →
                    <?= h($series['today']) ?>
                </span>
            </div>

            <div class="vp-sight-legend" data-vp-sight-legend>
                <?php foreach ($series['orgs'] as $i => $org): ?>
                    <span class="vp-sight-key"
                          data-vp-sight-key-org="<?= (int)$i ?>">
                        <span class="vp-sight-swatch"
                              style="--vp-sight-hue: var(--vp-sight-org-<?=
                                  (int)($i % 6) + 1 ?>);"></span>
                        <?= h($org) ?>
                        <b data-vp-sight-key-count></b>
                    </span>
                <?php endforeach; ?>
                <?php if ($sightings['fp'] > 0): ?>
                    <span class="vp-sight-key">
                        <span class="vp-sight-swatch vp-sight-swatch-fp"></span>
                        <?= __('False positive') ?>
                        <b data-vp-sight-key-fp></b>
                    </span>
                <?php endif; ?>
                <?php if ($sightings['expiration'] > 0): ?>
                    <span class="vp-sight-key">
                        <span class="vp-sight-swatch
                                     vp-sight-swatch-exp"></span>
                        <?= __('Expiration') ?>
                        <b data-vp-sight-key-exp></b>
                    </span>
                <?php endif; ?>
                <?php foreach ($decay as $i => $model): ?>
                    <span class="vp-sight-key">
                        <span class="vp-sight-swatch vp-sight-swatch-line"
                              style="--vp-sight-hue: var(--vp-sight-curve-<?=
                                  (int)($i % 2) + 1 ?>);"></span>
                        <?= h($model['model']) ?>
                        <b><?= h($model['score']) ?></b>
                    </span>
                    <span class="vp-sight-key vp-sight-key-quiet">
                        <span class="vp-sight-swatch vp-sight-swatch-dash"
                              style="--vp-sight-hue: var(--vp-sight-curve-<?=
                                  (int)($i % 2) + 1 ?>);"></span>
                        <?= h(sprintf(
                            __('threshold %s'),
                            $model['threshold']
                        )) ?>
                    </span>
                <?php endforeach; ?>
            </div>

            <?php
            /*
             * Three counts with three scopes are on screen at once —
             * the toggles count the whole value, the legend counts the
             * selected range, and the Reporters card counts every
             * report an organisation filed of any type. An organisation
             * therefore legitimately carries two different numbers in
             * two places, and the reader is owed the reason rather than
             * left to find it.
             */
            ?>
            <p class="vp-sight-legend-note">
                <?= h(__(
                    'Organisation counts are sightings in the selected'
                    . ' range. False positives and expirations are'
                    . ' counted apart from them, and the toggles above'
                    . ' count the whole value rather than the range.'
                )) ?>
            </p>

            <?php
            /*
             * The sentence the whole overlay exists to earn. A curve in
             * a card of its own could assert it; only a curve drawn
             * through the bars can show it.
             */
            ?>
            <p class="vp-sight-note">
                <i class="fas fa-circle-info"></i>
                <span><?= h($notes['fp_moves_nothing']) ?></span>
            </p>

        </div>

        <script type="application/json" data-vp-sight-data>
            <?= json_encode($payload) ?>
        </script>

    <?php endif; ?>

</div>
