<?php
/**
 * What would change this — the falsification card.
 *
 * A verdict that cannot say what would move it is an opinion. Each line
 * names a condition and the disposition it would produce, so the reader
 * can check the value against it themselves rather than wait for the
 * score to drift.
 *
 * The actions under them are the three ways a reader can supply exactly
 * that evidence.
 *
 * @var array $valueProfile
 * @var string $noWrites Why every action here is disabled
 */
$verdict = $valueProfile['verdict'];
$changers = $verdict['changers'] ?? array();
$actions = $verdict['changer_actions'] ?? array();
?>
<?php if (!empty($changers)): ?>
    <div class="card shadow-sm mb-3 vp-panel vp-aside">

        <div class="vp-aside-head">
            <i class="fas fa-arrows-turn-to-dots"
               style="color: var(--primary);"></i>
            <span class="vp-aside-title">
                <?= __('What would change this') ?>
            </span>
        </div>

        <div class="p-3 vp-changers">

            <?php foreach ($changers as $changer): ?>
                <div class="vp-changer">
                    <span class="vp-changer-arrow vp-changer-arrow-<?=
                        h($changer['direction']) ?>">
                        <?= $changer['direction'] === 'up'
                            ? '&#9650;'
                            : '&#9660;' ?>
                    </span>
                    <span><?= h($changer['text']) ?></span>
                </div>
            <?php endforeach; ?>

            <?php if (!empty($actions)): ?>
                <div class="vp-changer-actions">
                    <?php foreach ($actions as $action): ?>
                        <button type="button"
                                class="vp-changer-action<?=
                                    empty($action['emphasis'])
                                        ? ''
                                        : ' vp-changer-action-strong' ?>
                                       disabled"
                                disabled
                                title="<?= h($noWrites) ?>"
                                style="--vp-action-color: <?=
                                    h($action['colour']) ?>;">
                            <i class="<?= h($action['icon']) ?>"></i>
                            <?= h($action['label']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

    </div>
<?php endif; ?>
