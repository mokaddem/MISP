<?php
/**
 * The provenance line under a verdict hero: where the number came from,
 * how long it lasts, and what it did not get to see.
 *
 * Shared by both verdict layouts, because the caveats do not depend on
 * which way the evidence fell. A verdict that stated a disposition
 * without saying it was computed from the viewer's own visibility would
 * be claiming more than it knows.
 *
 * @var array $verdict
 */
$aclNote = $verdict['acl_note'] ?? null;
?>
<div class="vp-verdict-meta">
    <span class="vp-meta-chip"
          title="<?= h(__(
              'Recomputed on every page load. There is no stored'
              . ' verdict to go stale.'
          )) ?>">
        <i class="fas fa-bolt"></i>
        <?= __('Computed at render') ?>
    </span>
    <span class="vp-meta-chip"
          title="<?= h(__(
              'The weighting profile decides what each signal is worth.'
          )) ?>">
        <i class="fas fa-sliders"></i>
        <?= h(sprintf(__('Profile %s'), $verdict['profile'])) ?>
    </span>
    <span class="vp-meta-chip"
          title="<?= h(__(
              'The verdict lives in this page only. It is not written to'
              . ' the database and never leaves this instance.'
          )) ?>">
        <i class="fas fa-database"></i>
        <?= __('Not stored, not synchronised') ?>
    </span>
</div>

<?php if (!empty($aclNote)): ?>
    <div class="vp-acl-note">
        <i class="fas fa-eye-slash"></i>
        <span><?= h($aclNote) ?></span>
    </div>
<?php endif; ?>
