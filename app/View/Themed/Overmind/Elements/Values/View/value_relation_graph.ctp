<?php
/**
 * The rail's neighbourhood sketch.
 *
 * A static SVG and not a chart, because there is nothing to drive a
 * real one: `CorrelationGraphTool` expands events, not values, so no
 * value-centred node/edge feed exists (`00-shared.md` §7). A canvas
 * that looked live and was not would be the one dishonest thing on a
 * page built to avoid exactly that, so the button that would open a
 * real graph is present and visibly disabled instead.
 *
 * The layout is computed from the node lists rather than hardcoded:
 * three labelled regions, one per notion, each laying its own nodes out
 * evenly. A value with nothing in a region says so in that region — the
 * benign value has no co-occurrence to draw and its left half is
 * explicitly empty rather than quietly absent.
 *
 * The key draws line samples rather than squares, so it teaches the
 * edge styles as well as the colours: solid for the correlation engine,
 * dashed for a near-match, arrowed for something an analyst said.
 *
 * Lazily loaded from ValuesController::viewRelationGraph.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$profile = $valueProfile;
$relations = $profile['relationships'];
$graph = $relations['graph'];
$summary = $relations['summary'];
$nodes = $graph['nodes'];

/*
 * A namespaced id so two fragments carrying an arrow marker cannot
 * collide — the tab loads five of them and nothing guarantees an order.
 */
$markerId = 'vp-rel-arrow-' . substr(md5($profile['value']), 0, 8);

$width = 300;
$height = 260;
$centreX = 150;
$centreY = 130;

/**
 * Evenly spaced y positions for n nodes inside a band.
 *
 * @param int $count
 * @param int $top
 * @param int $bottom
 * @return array
 */
$slots = function ($count, $top, $bottom) {
    if ($count < 1) {
        return array();
    }
    if ($count === 1) {
        return array((int)(($top + $bottom) / 2));
    }
    $step = ($bottom - $top) / ($count - 1);
    $out = array();
    for ($i = 0; $i < $count; $i++) {
        $out[] = (int)round($top + ($step * $i));
    }
    return $out;
};

$coSlots = $slots(count($nodes['co']), 36, 224);
$nearSlots = $slots(count($nodes['near']), 44, 100);
$humanSlots = $slots(count($nodes['human']), 162, 222);

/**
 * A node label is a MISP type, and `network-block` is longer than the
 * node is wide. SVG clips at its viewport, so an over-long label would
 * lose its tail against the card edge rather than wrapping — the small
 * class buys the four characters that difference costs.
 *
 * @param string $label
 * @return string
 */
$typeClass = function ($label) {
    return mb_strlen($label) > 10 ? 'rg-type-xs' : 'rg-type-sm';
};

$key = array(
    array(
        'class' => '',
        'colour' => 'var(--vp-rel-co)',
        'label' => __('Co-occurrence'),
        'source' => __('— correlation engine'),
    ),
    array(
        'class' => ' vp-rel-swatch-dash',
        'colour' => 'var(--vp-rel-near)',
        'label' => __('Near-match'),
        'source' => __('— CIDR / ssdeep'),
    ),
    array(
        'class' => ' vp-rel-swatch-arrow',
        'colour' => 'var(--vp-rel-human)',
        'label' => __('Asserted'),
        'source' => __('— an analyst said so'),
    ),
);
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--bs-secondary-color);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Neighbourhood'),
        'panelIcon' => 'fas fa-circle-nodes',
        'panelColor' => 'var(--bs-secondary-color)',
        'panelSub' => h(sprintf(
            __('The value at the centre · %1$d of %2$s edges drawn'),
            $graph['edges'],
            number_format($summary['correlations'])
        )),
    )) ?>

    <div class="p-2">
        <svg class="rg-canvas" viewBox="0 0 <?= $width ?> <?= $height ?>"
             role="img"
             aria-label="<?= h(sprintf(
                 __(
                     'Sketch of the value at the centre with %1$d'
                     . ' co-occurrence, %2$d near-match and %3$d asserted'
                     . ' neighbours in three labelled regions'
                 ),
                 count($nodes['co']),
                 count($nodes['near']),
                 count($nodes['human'])
             )) ?>">

            <defs>
                <marker id="<?= h($markerId) ?>" viewBox="0 0 10 10"
                        refX="9" refY="5" markerWidth="6" markerHeight="6"
                        orient="auto-start-reverse">
                    <path d="M0,0 L10,5 L0,10 z" class="rg-accent-human"/>
                </marker>
            </defs>

            <?php
            /*
             * Each notion owns a labelled region, so the separation
             * survives the colours being unavailable — printed, in
             * greyscale, or read by somebody who cannot tell the three
             * hues apart.
             */
            ?>
            <line x1="150" y1="6" x2="150" y2="254" class="rg-div"/>
            <line x1="208" y1="130" x2="294" y2="130" class="rg-div"/>
            <text x="4" y="12" class="rg-sector">
                <?= h(strtoupper(__('Co-occurrence'))) ?>
            </text>
            <text x="212" y="18" class="rg-sector">
                <?= h(strtoupper(__('Near-match'))) ?>
            </text>
            <?php
            /*
             * Under the divider rather than at the foot: a label on the
             * last baseline loses its descenders to the viewBox edge,
             * and a region label that is itself cut off is a poor
             * advertisement for a panel about honest truncation.
             */
            ?>
            <text x="212" y="146" class="rg-sector">
                <?= h(strtoupper(__('Asserted'))) ?>
            </text>

            <?php foreach ($coSlots as $index => $y): ?>
                <line x1="84" y1="<?= h($y + 14) ?>" x2="96"
                      y2="<?= $centreY ?>" class="rg-edge-co"/>
            <?php endforeach; ?>
            <?php foreach ($coSlots as $index => $y): ?>
                <g>
                    <rect x="4" y="<?= h($y) ?>" width="80" height="28"
                          rx="6" class="rg-node"/>
                    <rect x="5" y="<?= h($y + 4) ?>" width="3" height="20"
                          class="rg-accent-co"/>
                    <text x="14" y="<?= h($y + 15) ?>"
                          class="<?= h($typeClass($nodes['co'][$index]))
                              ?> rg-type-co">
                        <?= h($nodes['co'][$index]) ?>
                    </text>
                    <rect x="14" y="<?= h($y + 21) ?>" width="56" height="6"
                          rx="3" class="rg-sk"/>
                </g>
            <?php endforeach; ?>

            <?php foreach ($nearSlots as $index => $y): ?>
                <line x1="204" y1="120" x2="212" y2="<?= h($y + 14) ?>"
                      class="rg-edge-near"/>
                <g>
                    <rect x="212" y="<?= h($y) ?>" width="84" height="28"
                          rx="6" class="rg-node"/>
                    <rect x="213" y="<?= h($y + 4) ?>" width="3" height="20"
                          class="rg-accent-near"/>
                    <text x="222" y="<?= h($y + 15) ?>"
                          class="<?= h($typeClass($nodes['near'][$index]))
                              ?> rg-type-near">
                        <?= h($nodes['near'][$index]) ?>
                    </text>
                    <rect x="222" y="<?= h($y + 21) ?>" width="60" height="6"
                          rx="3" class="rg-sk"/>
                </g>
            <?php endforeach; ?>

            <?php foreach ($humanSlots as $index => $y): ?>
                <line x1="204" y1="140" x2="212" y2="<?= h($y + 14) ?>"
                      class="rg-edge-human"
                      marker-end="url(#<?= h($markerId) ?>)"/>
                <g>
                    <rect x="212" y="<?= h($y) ?>" width="84" height="28"
                          rx="6" class="rg-node"/>
                    <rect x="213" y="<?= h($y + 4) ?>" width="3" height="20"
                          class="rg-accent-human"/>
                    <text x="222" y="<?= h($y + 15) ?>"
                          class="<?= h($typeClass($nodes['human'][$index]))
                              ?> rg-type-human">
                        <?= h($nodes['human'][$index]) ?>
                    </text>
                    <rect x="222" y="<?= h($y + 21) ?>" width="60" height="6"
                          rx="3" class="rg-sk"/>
                </g>
            <?php endforeach; ?>

            <?php if (empty($nodes['co'])): ?>
                <text x="44" y="134" class="rg-muted" text-anchor="middle">
                    <?= h(__('none')) ?>
                </text>
            <?php endif; ?>
            <?php if (empty($nodes['near'])): ?>
                <text x="254" y="76" class="rg-muted" text-anchor="middle">
                    <?= h(__('none')) ?>
                </text>
            <?php endif; ?>
            <?php if (empty($nodes['human'])): ?>
                <text x="254" y="196" class="rg-muted" text-anchor="middle">
                    <?= h(__('none')) ?>
                </text>
            <?php endif; ?>

            <g>
                <circle cx="<?= $centreX ?>" cy="<?= $centreY ?>" r="76"
                        class="rg-halo"/>
                <rect x="97" y="111" width="106" height="38" rx="10"
                      class="rg-centre"/>
                <text x="<?= $centreX ?>" y="136" class="rg-value"
                      text-anchor="middle">
                    <?= h(mb_strimwidth($profile['value'], 0, 18, '…')) ?>
                </text>
            </g>

        </svg>
    </div>

    <div class="px-3 pb-3">
        <div class="vp-rel-key">
            <?php foreach ($key as $entry): ?>
                <span class="vp-rel-key-item">
                    <span class="vp-rel-swatch<?= h($entry['class']) ?>"
                          style="--vp-rel-color: <?= h($entry['colour']) ?>;"
                    ></span>
                    <?= h($entry['label']) ?>
                    <span class="text-muted"><?= h($entry['source']) ?></span>
                </span>
            <?php endforeach; ?>
        </div>
        <button type="button"
                class="btn btn-sm btn-outline-secondary w-100 mt-3 disabled"
                title="<?= h(__(
                    'Disabled in this pass — MISP has no value-centred'
                    . ' graph feed. CorrelationGraphTool expands events,'
                    . ' not values, so a real graph needs an endpoint'
                    . ' that does not exist yet.'
                )) ?>">
            <i class="fas fa-maximize me-1"></i>
            <?= __('Open the full graph') ?>
        </button>
    </div>

</div>
