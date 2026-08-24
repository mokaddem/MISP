<?php
/**
 * A panel whose endpoint is live but whose body is not written yet.
 *
 * Distinct from `value_placeholder`, which stands in for a whole tab, and
 * distinct again from an empty state: the card chrome is real, so the
 * layout it will occupy is already visible, and the dashed body says the
 * contents are missing rather than absent.
 *
 * @var string $panelTitle
 * @var string $panelIcon
 * @var string $panelColor A CSS colour for the glyph tile
 * @var string $panelNote  What the panel will hold
 */
$panelNote = $panelNote ?? null;
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: <?= h($panelColor ?? 'var(--bs-secondary-color)') ?>;">
    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => $panelTitle,
        'panelIcon' => $panelIcon ?? null,
        'panelColor' => $panelColor ?? null,
        'panelSub' => h(__('Not yet implemented')),
    )) ?>
    <div class="vp-panel-stub">
        <?php if ($panelNote !== null): ?>
            <p class="vp-panel-stub-note"><?= h($panelNote) ?></p>
        <?php endif; ?>
        <span class="vp-panel-stub-badge">
            <i class="fas fa-helmet-safety"></i>
            <?= __('Skeleton pass') ?>
        </span>
    </div>
</div>
