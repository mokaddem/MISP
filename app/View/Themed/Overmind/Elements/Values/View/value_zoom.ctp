<?php
/**
 * The zoom control over a brushable activity chart.
 *
 * Four buttons and a caption, shared by the three callers phase 20
 * gave one brush. Buttons rather than a gesture, because phase 19
 * already spent the drag on selecting a period and §13.3 refuses to
 * conflate the two: a reader who wants to look closer at March must
 * not be made to filter the tab to March to do it. The wheel was
 * rejected for trapping the page under the reader's pointer, and the
 * double-click for colliding with the click that clears the brush.
 *
 * Rendered hidden, for the reason the brush is (§11.2): without the
 * script these buttons frame a chart they cannot move.
 *
 * `reset` is not a fifth step so much as the way back — it is the one
 * button whose target does not depend on where the reader is.
 *
 * @var array $grain Per-unit wording for what a bar is worth
 * @var string $zoomLabel Names the group for a screen reader
 * @var string $zoomAway What to say when the selection is off screen
 * @var string $zoomNote Optional sentence under the buttons
 */
$grain = isset($grain) ? $grain : array();
$zoomAway = isset($zoomAway) ? $zoomAway : null;
$zoomNote = isset($zoomNote) ? $zoomNote : null;
$steps = array(
    array(
        'step' => 'out',
        'icon' => 'fas fa-magnifying-glass-minus',
        'title' => __('Show a wider span'),
    ),
    array(
        'step' => 'in',
        'icon' => 'fas fa-magnifying-glass-plus',
        'title' => __('Look inside the span on screen'),
    ),
    array(
        'step' => 'left',
        'icon' => 'fas fa-chevron-left',
        'title' => __('Move back'),
    ),
    array(
        'step' => 'right',
        'icon' => 'fas fa-chevron-right',
        'title' => __('Move forward'),
    ),
    array(
        'step' => 'reset',
        'icon' => 'fas fa-arrows-left-right-to-line',
        'title' => __('Show the whole span'),
    ),
);
?>
<div class="vp-zoom" data-vp-zoom hidden role="group"
     aria-label="<?= h($zoomLabel) ?>">
    <div class="vp-zoom-where">
        <span class="vp-zoom-range" data-vp-zoom-range></span>
        <span class="vp-zoom-grain" data-vp-zoom-grain></span>
        <?php
        /*
         * Whether what the reader has selected is still on screen. It
         * has to be said in words: the brush paints a selection that
         * has gone out of view as a fully dimmed strip, which is the
         * truthful painting and is also indistinguishable from an
         * undimmed one, because a uniform dim has nothing to contrast
         * against.
         */
        ?>
        <span class="vp-zoom-note" data-vp-zoom-note hidden></span>
    </div>
    <div class="vp-zoom-steps">
        <?php foreach ($steps as $step): ?>
            <button type="button" class="vp-zoom-step"
                    data-vp-zoom-step="<?= h($step['step']) ?>"
                    title="<?= h($step['title']) ?>"
                    aria-label="<?= h($step['title']) ?>">
                <i class="<?= h($step['icon']) ?>" aria-hidden="true"></i>
            </button>
        <?php endforeach; ?>
    </div>
    <?php
    /*
     * The per-unit wording is data rather than markup because §12.2's
     * argument survives the zoom: a bar means a different thing on each
     * caller, so only the caller can say what one is worth.
     */
    ?>
    <script type="application/json" data-vp-zoom-labels><?= json_encode(
        array('grain' => $grain, 'away' => $zoomAway)
    ) ?></script>
</div>
<?php if ($zoomNote !== null): ?>
    <div class="vp-facet-note"><?= h($zoomNote) ?></div>
<?php endif; ?>
