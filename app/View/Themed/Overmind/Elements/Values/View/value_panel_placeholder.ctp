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
$panelIcon = $panelIcon ?? 'fas fa-cube';
$panelColor = $panelColor ?? 'var(--bs-secondary-color)';
$panelNote = $panelNote ?? null;
$isMispGlyph = strpos($panelIcon, 'misp-icon') === 0;
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: <?= h($panelColor) ?>;">
    <div class="p-3 border-bottom d-flex align-items-center gap-2">
        <span class="vp-panel-glyph">
            <?php if ($isMispGlyph): ?>
                <span class="<?= h($panelIcon) ?>"></span>
            <?php else: ?>
                <i class="<?= h($panelIcon) ?>"></i>
            <?php endif; ?>
        </span>
        <div class="me-auto">
            <div class="fw-bold lh-1"><?= h($panelTitle) ?></div>
            <div class="small text-muted mt-1">
                <?= __('Not yet implemented') ?>
            </div>
        </div>
    </div>
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
