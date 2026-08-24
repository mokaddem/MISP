<?php
/**
 * A tab that has not been built yet.
 *
 * Deliberately unlike an empty state: a dashed frame and a construction
 * glyph say "nothing here yet because nobody wrote it", where an empty
 * state says "nothing here because there is nothing to show". Confusing
 * the two is how a skeleton reads as a broken page.
 *
 * @var string $tabTitle
 * @var string $tabIcon
 * @var string $tabNote
 */
$tabTitle = $tabTitle ?? __('This section');
$tabIcon = $tabIcon ?? 'fas fa-cube';
$tabNote = $tabNote ?? null;
?>
<div class="vp-placeholder">
    <div class="vp-placeholder-glyph">
        <i class="<?= h($tabIcon) ?>"></i>
    </div>
    <div class="vp-placeholder-title">
        <?= h(sprintf(__('%s — not yet implemented'), $tabTitle)) ?>
    </div>
    <?php if ($tabNote !== null): ?>
        <p class="vp-placeholder-note"><?= h($tabNote) ?></p>
    <?php endif; ?>
    <span class="vp-placeholder-badge">
        <i class="fas fa-helmet-safety"></i>
        <?= __('Skeleton pass') ?>
    </span>
</div>
