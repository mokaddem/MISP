<?php
/**
 * The rail's neighbourhood graph.
 *
 * **Re-founded on the object in phase 26** (`03-relationships.md` §23).
 * Until then this drew one edge per value sharing an *event* with the
 * centre — a star, carrying nothing the panels beneath it did not
 * already print, and on live data drawing 36 of `8.8.8.8`'s 10,024
 * neighbours. §24 of `24-relationships.md` measured why: sharing a
 * container is not a relation.
 *
 * Sharing an object is. The canvas now draws five layers:
 *
 *     object      shares an object — `passive-dns · rrname → rdata`
 *     event       this value appears in this event, and stops there
 *     near        CIDR containment, ssdeep proximity
 *     human       an analyst wrote this claim
 *     reference   MISP's own typed relation between two objects
 *
 * **Two surfaces, two roll-ups.** The rail is a peek: the object layer
 * is one node per template and every other layer is one counted node,
 * which is what keeps a 340px column legible — §10.3 of
 * `24-relationships.md` measured 37 labels there as unreadable, and
 * this draws three to eight *with* their labels. The overlay expands
 * the object layer into values where the legibility bound allows.
 *
 * **Nothing is truncated on either.** A layer that does not draw its
 * tail one node at a time draws it as one node carrying the count, so
 * no caption here states a fraction of a whole the reader cannot reach.
 *
 * The graph is drawn by **pivotick 1.6.0**, already shipped in
 * `app/webroot/js`. What is still to come from that version — the
 * `light` overlay with pivotick's own legend and edge-facet filters —
 * is the client-side pass; this one is the feed and the two roll-ups.
 *
 * **The fallback is a composition strip, not a sketch.** The old
 * fallback drew one labelled SVG region per notion and there were
 * three; there are five, and five regions in a 300px box is a diagram
 * about its own layout. What a reader needs when a 776 KB script did
 * not arrive is which notions this value has and how much of each,
 * which is a list.
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

$feed = isset($graph['feed']) ? $graph['feed'] : null;
$peek = isset($graph['peek']) ? $graph['peek'] : null;
$layers = isset($graph['layers']) ? $graph['layers'] : array();
$graphId = 'vp-relgraph-' . substr(md5($profile['value']), 0, 8);

if ($feed !== null) {
    echo $this->element('genericElements/assetLoader', array(
        'js' => array('pivotick.iife'),
        'css' => array('pivotick'),
    ));
}

/*
 * One row per layer, in the order the tab reads them: the machine-
 * derived joins, then the two a person wrote. Each carries its own
 * count and says when it is rolled, so the strip is a truthful summary
 * of the canvas rather than a decoration beside it.
 */
$strip = array(
    array(
        'kind' => 'object',
        'label' => __('Object siblings'),
        'icon' => 'fas fa-cube',
        'count' => isset($layers['object']['values'])
            ? (int)$layers['object']['values']
            : 0,
        'noun' => array(__('value'), __('values')),
        'note' => isset($layers['object']['rolled'])
            && $layers['object']['rolled']
            ? sprintf(
                __n('rolled into %d template',
                    'rolled into %d templates',
                    (int)$layers['object']['templates'],
                    (int)$layers['object']['templates']),
                (int)$layers['object']['templates']
            )
            : null,
        'empty' => __('this value sits in no object'),
    ),
    array(
        'kind' => 'event',
        'label' => __('Events'),
        'icon' => 'fas fa-calendar',
        'count' => isset($layers['event']['total'])
            ? (int)$layers['event']['total']
            : 0,
        'noun' => array(__('event'), __('events')),
        'note' => null,
        'empty' => __('no event small enough to read'),
    ),
    array(
        'kind' => 'near',
        'label' => __('Near-matches'),
        'icon' => 'fas fa-arrows-left-right-to-line',
        'count' => isset($layers['near']['total'])
            ? (int)$layers['near']['total']
            : 0,
        'noun' => array(__('block'), __('blocks')),
        'note' => null,
        'empty' => __('nothing close without being equal'),
    ),
    array(
        'kind' => 'reference',
        'label' => __('Object references'),
        'icon' => 'fas fa-diagram-project',
        'count' => isset($layers['reference']['total'])
            ? (int)$layers['reference']['total']
            : 0,
        'noun' => array(__('reference'), __('references')),
        'note' => null,
        'empty' => __('no object reference reaches it'),
    ),
    array(
        'kind' => 'human',
        'label' => __('Asserted'),
        'icon' => 'fas fa-user-pen',
        'count' => isset($layers['human']['total'])
            ? (int)$layers['human']['total']
            : 0,
        'noun' => array(__('claim'), __('claims')),
        'note' => null,
        'empty' => __('nobody has written one'),
    ),
);

/*
 * The key teaches the stroke as well as the colour, because the
 * separation has to survive greyscale. Two of the five carry an
 * arrowhead and they are the two a person wrote — which is the
 * distinction this tab lives or dies by, drawn rather than captioned.
 */
$key = array(
    array(
        'kind' => 'object',
        'class' => '',
        'colour' => 'var(--vp-rel-object)',
        'label' => __('Object join'),
        'source' => __('— a shared object'),
    ),
    array(
        'kind' => 'event',
        'class' => ' vp-rel-swatch-dash',
        'colour' => 'var(--vp-rel-event)',
        'label' => __('Event'),
        'source' => __('— it appears there'),
    ),
    array(
        'kind' => 'near',
        'class' => ' vp-rel-swatch-dash',
        'colour' => 'var(--vp-rel-near)',
        'label' => __('Near-match'),
        'source' => __('— CIDR / ssdeep'),
    ),
    array(
        'kind' => 'reference',
        'class' => ' vp-rel-swatch-arrow',
        'colour' => 'var(--vp-rel-reference)',
        'label' => __('Reference'),
        'source' => __('— MISP\'s own typed link'),
    ),
    array(
        'kind' => 'human',
        'class' => ' vp-rel-swatch-arrow',
        'colour' => 'var(--vp-rel-human)',
        'label' => __('Asserted'),
        'source' => __('— an analyst said so'),
    ),
);

$rolled = false;
foreach ($layers as $layer) {
    if (!empty($layer['rolled'])) {
        $rolled = true;
    }
}
?>
<div class="card shadow-sm mb-3 vp-panel" data-vp-relgraph
     style="--vp-panel-color: var(--bs-secondary-color);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Neighbourhood'),
        'panelIcon' => 'fas fa-circle-nodes',
        'panelColor' => 'var(--bs-secondary-color)',
        /*
         * **No fraction.** The old sub-line read `36 of 10,024 edges
         * drawn`, which was accurate and was the argument against the
         * graph it captioned. Every edge is now either drawn or counted
         * in a roll-up, so the honest sub-line is what is on the canvas
         * plus whether anything is folded — never a ratio of a whole
         * the reader has no way to reach.
         */
        'panelSub' => h(sprintf(
            __('The value at the centre · %s'),
            sprintf(
                __n('%d edge', '%d edges', $graph['edges'],
                    number_format($graph['edges'])),
                number_format($graph['edges'])
            )
        )) . ($rolled
            ? '&nbsp;·&nbsp;' . h(__('tail rolled up, nothing dropped'))
            : '')
        . '&nbsp;·&nbsp;' . $this->element(
            'Values/View/value_read_age',
            array(
                'readAt' => isset($relations['read_at'])
                    ? $relations['read_at'] : 0,
                'prefix' => __('read %s'),
            )
        ),
    )) ?>

    <?php if ($peek !== null): ?>
        <?php
        /*
         * Empty until the library reports for duty. The strip below
         * stays visible until then, so the card never shows a blank
         * box — and stays visible for good if the script never
         * arrives.
         */
        ?>
        <div class="p-2 d-none" data-vp-relgraph-stage>
            <div class="vp-relgraph" id="<?= h($graphId) ?>"></div>
        </div>
    <?php endif; ?>

    <div class="px-3 pt-2" data-vp-relgraph-sketch>
        <div class="vp-rel-strip">
            <?php foreach ($strip as $row): ?>
                <div class="vp-rel-strip-row vp-rel-k-<?= h($row['kind']) ?><?=
                    $row['count'] === 0 ? ' vp-rel-strip-none' : '' ?>">
                    <span class="vp-rel-strip-dot"></span>
                    <span>
                        <i class="<?= h($row['icon']) ?> me-1"></i><?=
                            h($row['label']) ?>
                        <?php if ($row['count'] === 0): ?>
                            <span class="vp-fact-line-sub">
                                — <?= h($row['empty']) ?>
                            </span>
                        <?php elseif ($row['note'] !== null): ?>
                            <span class="vp-fact-line-sub">
                                — <?= h($row['note']) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                    <span class="vp-rel-strip-count">
                        <?php if ($row['count'] === 0): ?>
                            <?= h(__('none')) ?>
                        <?php else: ?>
                            <?= h(number_format($row['count'])) ?>
                            <span class="vp-fact-line-sub"><?= h(
                                $row['count'] === 1
                                    ? $row['noun'][0]
                                    : $row['noun'][1]
                            ) ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="px-3 pb-3 pt-2">
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
                class="btn btn-sm btn-outline-secondary w-100 mt-3
                       d-none"
                data-vp-relgraph-expand
                title="<?= h(__(
                    'The same five layers, full width, with the'
                    . ' object templates expanded into the values'
                    . ' behind them.'
                )) ?>">
            <i class="fas fa-maximize me-1"></i>
            <?= __('Open the full graph') ?>
        </button>
    </div>

    <?php if ($peek !== null): ?>
        <?php
        /*
         * Both feeds are literals inside this script and not a
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

            /*
             * Two feeds, built server-side. The rail's is rolled to one
             * node per object template and one counted node per other
             * layer; the overlay's expands the object layer into values
             * where the legibility bound allows. Rolling in the browser
             * was the alternative and it is the wrong place for it —
             * `0.0.0.0`'s 35,102 siblings are roughly 7 MB of nodes,
             * and no client-side algorithm helps a payload that never
             * lands.
             */
            var peek = <?= json_encode($peek) ?>;
            var data = <?= json_encode($feed) ?>;
            if (!peek || !peek.nodes || peek.nodes.length < 2) {
                // One node is not a neighbourhood. The strip says
                // "none" against every empty layer, which is a better
                // answer than a lone dot with springs on it.
                return;
            }

            var LEGEND = {
                object: <?= json_encode(h(__('shares an object'))) ?>,
                event: <?= json_encode(h(__('appears in this event'))) ?>,
                near: <?= json_encode(h(__(
                    'close without being equal'
                ))) ?>,
                reference: <?= json_encode(h(__(
                    'MISP records this relation'
                ))) ?>,
                human: <?= json_encode(h(__('an analyst said so'))) ?>
            };

            function kindOf(item) {
                var d = item && item.getData ? item.getData() : null;
                return (d && d.kind) ? d.kind : 'object';
            }

            function theme() {
                return document.documentElement
                    .getAttribute('data-bs-theme') === 'dark'
                    ? 'dark'
                    : 'light';
            }

            /*
             * Seven node kinds and four shapes, because the bundle
             * knows circle, square, hexagon and triangle and an unknown
             * shape draws no shape element at all — pivotick then
             * measures a node that is not there and throws `getBBox is
             * not a function` on every render tick. Colour separates
             * the pairs that share a shape, and the key beneath the
             * canvas names all five edge kinds in words.
             */
            var SHAPES = {
                value: { shape: 'hexagon', color: 'var(--bs-body-color)' },
                template: {
                    shape: 'square',
                    color: 'var(--vp-rel-object)'
                },
                sibling: {
                    shape: 'circle',
                    color: 'var(--vp-rel-object)'
                },
                event: { shape: 'triangle', color: 'var(--vp-rel-event)' },
                near: { shape: 'square', color: 'var(--vp-rel-near)' },
                object: {
                    shape: 'circle',
                    color: 'var(--vp-rel-reference)'
                },
                /*
                 * A rolled node takes its layer's name, and for this
                 * layer that is `reference` where the far-end node kind
                 * is `object`. Without the alias the rail's
                 * `6 references` node matches nothing in the map and
                 * pivotick draws its own default — a blue circle in a
                 * card where blue means nothing.
                 */
                reference: {
                    shape: 'circle',
                    color: 'var(--vp-rel-reference)'
                },
                human: { shape: 'triangle', color: 'var(--vp-rel-human)' }
            };

            function styleMap(size) {
                var map = {};
                Object.keys(SHAPES).forEach(function (kind) {
                    map[kind] = {
                        shape: SHAPES[kind].shape,
                        color: SHAPES[kind].color,
                        size: kind === 'value' ? size + 8 : size
                    };
                });
                return map;
            }

            function options(size, rail) {
                return {
                    isDirected: true,
                    render: {
                        /*
                         * Required, not cosmetic: pivotick applies
                         * `defaultEdgeStyle` and the style maps **only**
                         * under the SVG renderer. Left at the default
                         * the five notions all draw in one colour and
                         * the dashes never appear, which is the whole
                         * separation this tab rests on.
                         */
                        type: 'svg',
                        nodeTypeAccessor: function (node) {
                            return kindOf(node);
                        },
                        nodeStyleMap: styleMap(size),
                        defaultNodeStyle: {
                            /*
                             * Above the node rather than inside it.
                             * Pivotick's character budget is 2.5×
                             * larger for a label it draws outside, and
                             * a non-zero shift is the only way to ask
                             * for that, there being no `outside` flag.
                             */
                            textVerticalShift: 1,
                            /*
                             * 1.6.0, **and only on the rail**. The
                             * roll-up depends on it there: three to
                             * eight nodes labelled with template names,
                             * and truncated to pivotick's
                             * radius-derived budget
                             * `paloalto-threat-event` becomes `pa…nt` —
                             * exactly the name the roll-up exists to
                             * show.
                             *
                             * On the overlay it is the wrong setting
                             * and the screenshot says so. Fifty-two
                             * nodes there include event titles like
                             * *Kunai Analysis Report - Malware Sample
                             * Abusing Open Recursive DNS for
                             * Exfiltration*, and untruncated they
                             * overlap into a wall of text with the
                             * graph somewhere underneath it. The
                             * overlay keeps pivotick's own truncation
                             * and puts the full title in the tooltip,
                             * which is what a tooltip is for.
                             */
                            textTruncate: rail ? false : undefined,
                            text: function (node) {
                                var d = node.getData();
                                if (!d) return '';
                                if (d.count && d.rolled) {
                                    return d.label;
                                }
                                return d.label || '';
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
                                var d = edge.getData
                                    ? edge.getData()
                                    : null;
                                return d ? (d.label || '') : '';
                            }
                        }
                    },
                    /*
                     * `viewer` in both places. `light` and `full` add
                     * pivotick's main header, and that header carries
                     * **Edit Graph** and **Notes** — two affordances
                     * that mutate the canvas. They write nothing to
                     * MISP, which is exactly why they would be the
                     * wrong thing here: a reader dragging out an edge
                     * would think they had asserted a relationship.
                     * §14 keeps every write control on this page
                     * disabled, and the honest way to keep one disabled
                     * is not to offer it. §23.4's `light` overlay waits
                     * on the upstream flag that switches Notes off.
                     *
                     * Pivotick paints its own canvas ground and
                     * defaults to the light one, so a dark page gets a
                     * white rectangle in the middle of the rail unless
                     * it is told. Read from the page rather than
                     * hardcoded, because this panel has to be right in
                     * both themes and the reader chooses.
                     */
                    UI: {
                        mode: 'viewer',
                        theme: theme(),
                        /*
                         * **Off on the rail, and it has to be said.**
                         * §26.9 settled `viewer` on the reading that
                         * leaving `navigation` unconfigured keeps its
                         * viewport rail unmounted, because
                         * `UIManager.ts:241` gates the rail on
                         * `o.navigation?.enabled`. That was true of the
                         * build this tab shipped against; **1.6.0
                         * defaults the flag to `true`**, so the gate now
                         * opens rather than closes on silence, and a
                         * 340px card grew four buttons over its own
                         * canvas. The overlay keeps them: pan and zoom
                         * are worth a toolbar at 1400px, and the whole
                         * reason for opening it is to move around.
                         */
                        navigation: { enabled: !rail },
                        /*
                         * **Off on the rail, for the same reason and
                         * by the same trap as `navigation` above.**
                         * `DEFAULT_UI_OPTIONS.tooltip.enabled` is
                         * `true` and the options are deep-merged, so
                         * naming the header maps without naming the
                         * flag inherits it — silence opens the
                         * element rather than closing it.
                         *
                         * A 340px peek draws three to eight nodes
                         * whose labels are already on the canvas, and
                         * every one of them is counted again in the
                         * composition strip directly beneath. The
                         * tooltip there covers a third of the canvas
                         * to repeat what it covers. The overlay keeps
                         * it: that surface truncates its labels on
                         * purpose (`textTruncate` above), so the
                         * tooltip is the only place the full one is
                         * readable.
                         */
                        tooltip: {
                            enabled: !rail,
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
                     * so its nodes need room to hold them; the rail
                     * keeps pivotick's own tuning, which is what packs
                     * eight nodes into 320px.
                     */
                    simulation: rail ? {} : { d3LinkDistance: 320 },
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
                        options(24, false)
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
                     * polls, then the strip keeps the card.
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
                    new window.Pivotick(host, peek, options(16, true));
                } catch (e) {
                    console.error('[value-profile] neighbourhood graph'
                        + ' failed, keeping the strip:', e);
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
