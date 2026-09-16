<?php
/**
 * Where the bands sit.
 *
 * A reader who disagrees with a quality band needs the cut-off before
 * the disagreement is about anything, and the conditions strip already
 * links to the profile that holds it. This is the number that link
 * would take them to.
 *
 * **`min_signals` is on the tile because it is the surprise.** A value
 * scoring 80 still bands `medium` if only two signals fired — quality
 * is high when several independent readings agree, not when one is
 * generous (`ValueVerdictTool::qualityBand()`). That is the one rule
 * here a reader cannot infer from a band they are looking at, and
 * leaving it off would make the tile's two numbers a promise the
 * engine does not keep.
 *
 * **The high cut-off takes the value slot.** It is the threshold that
 * decides whether a record is worth acting on; `medium` is the floor
 * under it and belongs in the qualifying line.
 *
 * Free: the thresholds come from the profile phase 7 already resolved.
 * Where a profile declares none, the figures shown are the engine's
 * own fallbacks rather than a blank, because those are the numbers it
 * would actually band with.
 *
 * @var array{high: int, medium: int, min_signals: int} $bands
 */
?>
<div class="vi-tile" data-vi-tile="bands">
    <div class="vi-tile__label"><?= h(__('Where the bands sit')) ?></div>
    <div class="vi-tile__value">
        <?= h(number_format($bands['high'])) ?>
    </div>
    <div class="vi-tile__sub"><?= sprintf(
        h(__(
            'and above bands high, %s and above medium — and high also'
            . ' needs %s to agree.'
        )),
        '<b>' . h(number_format($bands['medium'])) . '</b>',
        '<b>' . h(number_format($bands['min_signals'])) . '</b> ' . h(__n(
            'signal',
            'signals',
            $bands['min_signals']
        ))
    ) ?></div>
</div>
