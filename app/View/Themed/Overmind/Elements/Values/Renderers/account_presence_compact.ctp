<?php
/**
 * How many platforms, and which.
 *
 * The count leads and the platform names follow, which is the inverse
 * of most shapes here: the answer *is* the set, and no one platform in
 * it is more the answer than another.
 *
 * @var array $data
 */
$platforms = $data['platforms'];
?>
<div class="vp-rw-in vp-rw-acct">
    <div class="vp-rw-metric">
        <span class="vp-rw-metric-n"><?=
            h(number_format($data['count'])) ?></span>
        <span class="vp-rw-metric-l"><?= h(__n(
            'account', 'accounts', $data['count']
        )) ?></span>
    </div>
    <?php if (!empty($platforms)): ?>
        <ul class="vp-rw-set">
            <?php foreach (array_slice($platforms, 0, 4) as $one): ?>
                <li><?= h($one) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php if (count($platforms) > 4): ?>
            <div class="vp-rw-note">+<?=
                h(count($platforms) - 4) ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
