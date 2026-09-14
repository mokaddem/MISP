<?php
/**
 * The one control on this page that causes anything to leave the
 * instance — and, since phase 11, its twin that does not.
 *
 * **Enabled, unlike every other control here** — and that is the
 * distinction it exists to draw. The rest of the page's write controls
 * render visibly disabled because the Value Profile writes nothing;
 * this one is live because running a module writes nothing either. It
 * is disabled only for a reader without `perm_add`, which is the bar
 * MISP sets on `attributes/hoverEnrichment` and
 * `events/queryEnrichment` and which this page does not undercut.
 *
 * `mode` is what separates the twins. Without it the press is a press:
 * it always asks the module, because `max_age_hours` governs automatic
 * reuse and a human who pressed a button has made a decision a cache
 * must not overrule. With `auto` it serves the stored answer when
 * there is a fresh one — the same request the tab's own fan-out makes,
 * so *Show what came back* and an auto-run go down one path and cannot
 * disagree.
 *
 * The same `perm_add` bar guards both, even though the stored path
 * sends nothing: the endpoint decides whether to query, and a control
 * that looked cheaper than it might be would be promising on the
 * server's behalf.
 *
 * A plain partial, not an endpoint.
 *
 * @var array $module
 * @var bool $canRun
 * @var string $noRun
 * @var string $label
 * @var string|null $mode `auto` to prefer a stored answer
 * @var string|null $variant `secondary` for the quieter of two
 * @var string|null $runType The type to ask under; the row's default
 *                           otherwise. A stored answer is keyed by the
 *                           type it was asked under, which need not be
 *                           the one this row leads with, and fetching
 *                           it under the wrong one would quietly ask
 *                           the module instead of serving the answer.
 */
$mode = isset($mode) ? $mode : null;
$variant = isset($variant) ? $variant : null;
$runType = isset($runType) && $runType !== null
    ? $runType
    : $module['type'];
$tone = $variant === 'secondary' ? 'btn-outline-secondary'
    : 'btn-outline-primary';
$icon = $mode === 'auto' ? 'fas fa-clock-rotate-left' : 'fas fa-play';
?>
<button type="button"
        class="btn btn-sm d-inline-flex align-items-center gap-1
               <?= $canRun ? $tone : 'disabled
               btn-outline-secondary' ?>"
        <?= $canRun ? '' : 'disabled title="' . h($noRun) . '"' ?>
        data-vp-e-run="<?= h($module['name']) ?>"
        <?= $mode === null ? '' : 'data-vp-e-mode="' . h($mode) . '"' ?>
        data-vp-e-type="<?= h($runType) ?>">
    <i class="<?= h($icon) ?>" data-vp-e-icon></i>
    <span data-vp-e-label><?= h($label) ?></span>
</button>
