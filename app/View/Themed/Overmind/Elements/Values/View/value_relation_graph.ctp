<?php
/**
 * The rail's neighbourhood graph.
 *
 * **Live since phase 24.** Until then this was a static SVG sketch with
 * a disabled button beside it, because there was nothing to drive a real
 * graph: `CorrelationGraphTool` expands events, not values, so no
 * value-centred node/edge feed existed (`00-shared.md` §7), and a canvas
 * that looked live and was not would have been the one dishonest thing
 * on a page built to avoid exactly that. `ValueProfile::graphFor` is now
 * that feed — assembled from the tab's own three sections rather than
 * from the correlation table, which has nothing to say about a value's
 * neighbours (`24-relationships.md` §3).
 *
 * The graph is drawn by **pivotick**, already shipped in
 * `app/webroot/js` and already used by the event Pivot Explorer. One
 * edge kind per notion, carrying §5's separation into the picture:
 * solid for a shared event, dashed for a near-match, arrowed for
 * something an analyst said. Every neighbour node links to *its own*
 * Value Profile, which is the one thing a value-centred graph can do
 * that an event-centred one cannot.
 *
 * **The sketch is still here**, as the fallback. A rail that renders
 * nothing when a 560 KB script fails to arrive is worse than a rail
 * that renders a drawing, and the drawing was already written.
 *
 * The key draws line samples rather than squares, so it teaches the
 * edge styles as well as the colours.
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
 * Live data brings a feed; the fixture brings only the three type
 * lists the sketch draws. A fixture-driven render therefore keeps the
 * sketch and the disabled button it always had, and loads no library.
 */
$feed = isset($graph['feed']) ? $graph['feed'] : null;
$graphId = 'vp-relgraph-' . substr(md5($profile['value']), 0, 8);

if ($feed !== null) {
    echo $this->element('genericElements/assetLoader', array(
        'js' => array('pivotick.iife'),
        'css' => array('pivotick'),
    ));
}

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
        // Not the correlation engine — §3 of the phase document.
        'source' => __('— a shared event'),
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
<div class="card shadow-sm mb-3 vp-panel" data-vp-relgraph
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

    <?php if ($feed !== null): ?>
        <?php
        /*
         * Empty until the library reports for duty. The sketch below
         * stays visible until then, so the card never shows a blank
         * box — and stays visible for good if the script never
         * arrives.
         */
        ?>
        <div class="p-2 d-none" data-vp-relgraph-stage>
            <div class="vp-relgraph" id="<?= h($graphId) ?>"></div>
        </div>
    <?php endif; ?>

    <div class="p-2" data-vp-relgraph-sketch>
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
        <?php if ($feed === null): ?>
            <button type="button"
                    class="btn btn-sm btn-outline-secondary w-100 mt-3
                           disabled"
                    title="<?= h(__(
                        'Disabled in this pass — MISP has no'
                        . ' value-centred graph feed.'
                        . ' CorrelationGraphTool expands events, not'
                        . ' values, so a real graph needs an endpoint'
                        . ' that does not exist yet.'
                    )) ?>">
                <i class="fas fa-maximize me-1"></i>
                <?= __('Open the full graph') ?>
            </button>
        <?php else: ?>
            <?php
            /*
             * No longer disabled, and this is the one write-free
             * control on the tab whose *reason* for being disabled has
             * gone away rather than being deferred again: the missing
             * thing was a value-centred feed, and there is one now.
             * The overlay draws the same nodes at a size where the
             * labels are readable and the physics panel is worth
             * having.
             */
            ?>
            <button type="button"
                    class="btn btn-sm btn-outline-secondary w-100 mt-3
                           d-none"
                    data-vp-relgraph-expand
                    title="<?= h(__(
                        'The same neighbourhood, full width, with'
                        . ' pivotick\'s own controls.'
                    )) ?>">
                <i class="fas fa-maximize me-1"></i>
                <?= __('Open the full graph') ?>
            </button>
        <?php endif; ?>
    </div>

    <?php if ($feed !== null): ?>
        <?php
        /*
         * The feed is a literal inside this script and not a
         * `<script type="application/json">` beside it, because
         * `loadAjaxContainer` re-creates every script the fragment
         * brings **without copying its `type`** — a JSON block would
         * be appended to `<head>` as executable JavaScript and throw.
         */
        ?>
        <script>
        (function () {
            var card = document.currentScript
                ? document.currentScript.closest('[data-vp-relgraph]')
                : null;
            if (!card) {
                /*
                 * `mispOvermind.js` re-creates every script the
                 * fragment brings and appends it to `<head>`, so
                 * `currentScript.closest()` finds nothing. The card is
                 * addressed by its own id instead.
                 */
                card = document.getElementById(
                    <?= json_encode($graphId) ?>
                );
                card = card ? card.closest('[data-vp-relgraph]') : null;
            }
            if (!card) return;
            var stage = card.querySelector('[data-vp-relgraph-stage]');
            var sketch = card.querySelector('[data-vp-relgraph-sketch]');
            var expand = card.querySelector('[data-vp-relgraph-expand]');
            var host = document.getElementById(
                <?= json_encode($graphId) ?>
            );
            if (!stage || !host) return;

            var data = <?= json_encode($feed) ?>;
            if (!data || !data.nodes || data.nodes.length < 2) {
                // One node is not a neighbourhood. The sketch says
                // "none" in each empty region, which is the better
                // answer than a lone dot with springs on it.
                return;
            }

            var LEGEND = {
                co: <?= json_encode(h(__('shares an event'))) ?>,
                near: <?= json_encode(h(__(
                    'close without being equal'
                ))) ?>,
                human: <?= json_encode(h(__('an analyst said so'))) ?>
            };

            function kindOf(item) {
                var d = item && item.getData ? item.getData() : null;
                return (d && d.kind) ? d.kind : 'co';
            }

            function theme() {
                return document.documentElement
                    .getAttribute('data-bs-theme') === 'dark'
                    ? 'dark'
                    : 'light';
            }

            function options(mode, labels) {
                return {
                    isDirected: true,
                    render: {
                        /*
                         * Required, not cosmetic: pivotick applies
                         * `defaultEdgeStyle` and the style maps **only**
                         * under the SVG renderer. Left at the default
                         * the three notions all draw in one colour and
                         * the dashes never appear, which is the whole
                         * separation this tab rests on.
                         */
                        type: 'svg',
                        nodeTypeAccessor: function (node) {
                            return kindOf(node);
                        },
                        nodeStyleMap: {
                            value: {
                                shape: 'hexagon',
                                color: 'var(--bs-body-color)',
                                size: labels ? 30 : 24
                            },
                            co: {
                                shape: 'circle',
                                color: 'var(--vp-rel-co)',
                                size: labels ? 22 : 13
                            },
                            near: {
                                shape: 'square',
                                color: 'var(--vp-rel-near)',
                                size: labels ? 22 : 13
                            },
                            /*
                             * `triangle` and not `diamond`: the shipped
                             * bundle knows circle, square, hexagon and
                             * triangle, and an unknown shape draws no
                             * shape element at all — pivotick then
                             * measures a node that is not there and
                             * throws `getBBox is not a function` on
                             * every render tick.
                             */
                            human: {
                                shape: 'triangle',
                                color: 'var(--vp-rel-human)',
                                size: labels ? 24 : 15
                            }
                        },
                        defaultNodeStyle: {
                            /*
                             * Above the node rather than inside it, in
                             * the overlay. Pivotick's character budget
                             * is 2.5× larger for a label it draws
                             * outside — and a non-zero shift is the
                             * only way to ask for that, there being no
                             * `outside` flag. With the larger nodes
                             * above it takes a value from six readable
                             * characters to about sixteen.
                             */
                            textVerticalShift: labels ? 1 : 0,
                            /*
                             * The rail draws no labels. Thirty-seven
                             * of them in a 340px column overlap into
                             * illegibility and the card's job there is
                             * to show the *shape* of a neighbourhood —
                             * which is what the sketch it replaces did.
                             * The overlay is where the names are read.
                             */
                            text: function (node) {
                                if (!labels) {
                                    return kindOf(node) === 'value'
                                        ? '★'
                                        : '';
                                }
                                var d = node.getData();
                                return d ? d.label : '';
                            }
                        },
                        /*
                         * Only the width and the marker default live
                         * here. Colour and dash are on each edge, in
                         * the feed — the shipped build ignores the
                         * callback forms of `dashed`, `markerEnd` and
                         * `styleCb` that the current library documents.
                         */
                        defaultEdgeStyle: {
                            strokeWidth: 2,
                            markerEnd: 'none'
                        },
                        defaultLabelStyle: {
                            labelAccessor: function (edge) {
                                if (!labels) return '';
                                var d = edge.getData
                                    ? edge.getData()
                                    : null;
                                return d ? (d.label || '') : '';
                            }
                        }
                    },
                    /*
                     * `viewer` in both places, and the overlay could
                     * have had more. `light` and `full` add pivotick's
                     * main header, and that header carries **Edit
                     * Graph** and **Notes** — two affordances that
                     * mutate the canvas. They write nothing to MISP,
                     * which is exactly why they would be the wrong
                     * thing here: a reader dragging out an edge would
                     * think they had asserted a relationship. §14 keeps
                     * every write control on this page disabled, and
                     * the honest way to keep one disabled is not to
                     * offer it.
                     *
                     * Pivotick paints its own canvas ground and
                     * defaults to the light one, so a dark page gets a
                     * white rectangle in the middle of the rail unless
                     * it is told. Read from the page rather than
                     * hardcoded, because this panel has to be right in
                     * both themes and the reader chooses.
                     */
                    UI: {
                        mode: mode,
                        theme: theme(),
                        /*
                         * The node label is drawn *inside* the node and
                         * pivotick shortens it to fit — the character
                         * budget is derived from the radius and both
                         * the budget and the font scale together, so a
                         * bigger node does not buy more letters. Six is
                         * the ceiling, which turns `update.example.com`
                         * into `up…com`. The tooltip is where the value
                         * is legible, and it is the only place it can
                         * be without rendering nodes by hand.
                         */
                        tooltip: {
                            nodeHeaderMap: {
                                title: function (node) {
                                    var d = node.getData();
                                    return d ? d.label : '';
                                },
                                subtitle: function (node) {
                                    var d = node.getData();
                                    if (!d) return '';
                                    return [d.type, d.sub]
                                        .filter(Boolean).join(' · ');
                                }
                            },
                            edgeHeaderMap: {
                                title: function (edge) {
                                    var d = edge.getData
                                        ? edge.getData()
                                        : null;
                                    return d ? (d.label || '') : '';
                                },
                                subtitle: function (edge) {
                                    return LEGEND[kindOf(edge)] || '';
                                }
                            }
                        }
                    },
                    /*
                     * The overlay's labels are the point of opening it,
                     * so the nodes need room to hold them. The rail's
                     * own layout keeps pivotick's own tuning, which is
                     * what packs 19 nodes into 320px.
                     */
                    simulation: labels
                        ? { d3LinkDistance: 220 }
                        : {},
                    /*
                     * A double-click, not a click. A single click is
                     * how pivotick selects and how a reader drags, and
                     * navigating away from either would make the graph
                     * unusable — which is the trap a pivot control
                     * falls into if it takes the same gesture as
                     * selection.
                     */
                    callbacks: {
                        onNodeDbclick: function (event, node) {
                            pivot(node);
                        }
                    }
                };
            }

            function pivot(node) {
                var d = node && node.getData ? node.getData() : null;
                if (!d || !d.href) return;
                window.location = <?= json_encode($baseurl) ?> + d.href;
            }

            /*
             * The rail's copy, with every label stripped out of the
             * data. Returning an empty string from `labelAccessor` is
             * not enough — the shipped build reads `data.label` when
             * the accessor gives it nothing — so the rail is handed a
             * graph that has no labels to draw.
             */
            function unlabelled(source) {
                return {
                    nodes: source.nodes.map(function (node) {
                        var d = {};
                        Object.keys(node.data || {}).forEach(function (k) {
                            if (k !== 'label') d[k] = node.data[k];
                        });
                        return { id: node.id, data: d, style: node.style };
                    }),
                    edges: source.edges.map(function (edge) {
                        return {
                            from: edge.from,
                            to: edge.to,
                            data: { kind: edge.data.kind },
                            style: edge.style
                        };
                    })
                };
            }

            var overlay = null;
            var big = null;

            function open() {
                if (typeof window.Pivotick !== 'function') return;
                if (overlay) {
                    overlay.classList.remove('d-none');
                    return;
                }
                overlay = document.createElement('div');
                overlay.className = 'vp-relgraph-overlay';
                overlay.innerHTML =
                    '<div class="vp-relgraph-overlay-bar">'
                    + '<span class="vp-relgraph-overlay-title"></span>'
                    + '<button type="button" class="btn btn-sm'
                    + ' btn-outline-light" data-close>'
                    + <?= json_encode(h(__('Close'))) ?>
                    + '</button></div>'
                    + '<div class="vp-relgraph-overlay-stage"></div>';
                overlay.querySelector('.vp-relgraph-overlay-title')
                    .textContent = <?= json_encode(sprintf(
                        __('Neighbourhood of %s'),
                        $profile['value']
                    )) ?>;
                document.body.appendChild(overlay);
                overlay.querySelector('[data-close]')
                    .addEventListener('click', function () {
                        overlay.classList.add('d-none');
                    });
                try {
                    big = new window.Pivotick(
                        overlay.querySelector('.vp-relgraph-overlay-stage'),
                        data,
                        options('viewer', true)
                    );
                } catch (e) {
                    console.error('[value-profile] full graph failed:', e);
                    overlay.classList.add('d-none');
                }
            }

            var tries = 0;
            (function start() {
                if (typeof window.Pivotick !== 'function') {
                    /*
                     * The loader re-creates `<script src>` and lets it
                     * fetch asynchronously, then runs this one
                     * immediately — so the library is reliably *not*
                     * there on the first tick. Six seconds of 50ms
                     * polls, then the sketch keeps the card.
                     */
                    if (++tries > 120) return;
                    window.setTimeout(start, 50);
                    return;
                }
                /*
                 * Reveal before constructing. A `d-none` container is
                 * 0×0, and pivotick sizes its viewport from the
                 * element it is handed — built hidden it produces an
                 * empty `<svg>` and no shapes, with nothing thrown to
                 * say so.
                 */
                stage.classList.remove('d-none');
                if (sketch) sketch.classList.add('d-none');
                try {
                    new window.Pivotick(host, unlabelled(data),
                        options('viewer', false));
                } catch (e) {
                    console.error('[value-profile] neighbourhood graph'
                        + ' failed, keeping the sketch:', e);
                    stage.classList.add('d-none');
                    if (sketch) sketch.classList.remove('d-none');
                    return;
                }
                if (expand) {
                    expand.classList.remove('d-none');
                    expand.addEventListener('click', open);
                }
            })();
        })();
        </script>
    <?php endif; ?>

</div>
