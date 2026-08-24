<?php
/**
 * Type-aware next steps. Each chip will open the Value Profile of the
 * value it names, which is what makes this page a navigation surface
 * rather than a dead end.
 *
 * Inert in this pass: the pivots are fixture hints, not resolved values,
 * so a chip has nothing real to point at yet.
 *
 * @var array $pivots
 */
if (empty($pivots)) {
    return;
}
$inert = __('Pivot targets are not resolved in this pass.');
?>
<div class="container-fluid">
    <div class="vp-pivot-rail">
        <span class="vp-pivot-rail-label"><?= __('Pivot to') ?></span>
        <?php foreach ($pivots as $pivot): ?>
            <span class="vp-pivot" title="<?= h($inert) ?>">
                <span class="vp-pivot-label"><?= h($pivot['label']) ?></span>
                <span class="vp-pivot-hint"><?= h($pivot['hint']) ?></span>
            </span>
        <?php endforeach; ?>
    </div>
</div>
