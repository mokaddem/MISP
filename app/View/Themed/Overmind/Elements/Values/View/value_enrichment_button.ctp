<?php
/**
 * The one control on this page that causes anything to leave the
 * instance.
 *
 * **Enabled, unlike every other control here** — and that is the
 * distinction it exists to draw. The rest of the page's write controls
 * render visibly disabled because the Value Profile writes nothing;
 * this one is live because running a module writes nothing either. It
 * is disabled only for a reader without `perm_add`, which is the bar
 * MISP sets on `attributes/hoverEnrichment` and
 * `events/queryEnrichment` and which this page does not undercut.
 *
 * A plain partial, not an endpoint.
 *
 * @var array $module
 * @var bool $canRun
 * @var string $noRun
 * @var string $label
 */
?>
<button type="button"
        class="btn btn-sm d-inline-flex align-items-center gap-1
               <?= $canRun ? 'btn-outline-primary' : 'disabled
               btn-outline-secondary' ?>"
        <?= $canRun ? '' : 'disabled title="' . h($noRun) . '"' ?>
        data-vp-e-run="<?= h($module['name']) ?>"
        data-vp-e-type="<?= h($module['type']) ?>">
    <i class="fas fa-play" data-vp-e-icon></i>
    <span data-vp-e-label><?= h($label) ?></span>
</button>
