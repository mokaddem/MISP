<?php
/**
 * What the profile deliberately set aside.
 *
 * A signal silently dropped looks the same as a signal nobody found, so
 * the exclusions are listed with the reason for each. Distinct from the
 * unresolved band in the argument card: those are splits that could
 * still fall either way, these were never going to count.
 *
 * @var array $valueProfile
 */
$verdict = $valueProfile['verdict'];
$notCounted = $verdict['not_counted'] ?? array();
?>
<?php if (!empty($notCounted)): ?>
    <div class="card shadow-sm mb-3 vp-panel vp-aside">

        <div class="vp-aside-head">
            <i class="fas fa-eye-slash"
               style="color: var(--bs-secondary-color);"></i>
            <span class="vp-aside-title"><?= __('Not counted') ?></span>
        </div>

        <div class="p-3 d-flex flex-column vp-notcounted">
            <?php foreach ($notCounted as $item): ?>
                <div class="vp-notcounted-item">
                    <strong><?= h($item['title']) ?></strong>
                    — <?= h($item['note']) ?>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
<?php endif; ?>
