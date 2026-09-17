<?php
/**
 * Each account found, with a link where the source gave one.
 *
 * The creation date is the column worth having: an identifier with
 * accounts created across six years reads differently from one whose
 * accounts were all created in the same week.
 *
 * @var array $data
 */
?>
<div class="vp-rf vp-rf-acct">
    <table class="table table-sm vp-rf-table">
        <thead>
            <tr>
                <th scope="col"><?= h(__('Platform')) ?></th>
                <th scope="col"><?= h(__('Handle')) ?></th>
                <th scope="col"><?= h(__('Display name')) ?></th>
                <th scope="col"><?= h(__('Created')) ?></th>
                <th scope="col"><?= h(__('Last seen')) ?></th>
                <th scope="col"><?= h(__('Source')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['accounts'] as $account): ?>
                <tr>
                    <td><?= h($account['platform'] === null
                        ? '—' : $account['platform']) ?></td>
                    <td class="font-monospace"><?php
                        if ($account['link'] !== null): ?>
                        <a href="<?= h($account['link']) ?>"
                           rel="noreferrer noopener"
                           target="_blank"><?= h((string)
                            $account['handle']) ?></a>
                    <?php else: ?>
                        <?= h($account['handle'] === null
                            ? '—' : $account['handle']) ?>
                    <?php endif; ?></td>
                    <td><?= h($account['display'] === null
                        ? '—' : $account['display']) ?></td>
                    <td><?= h($account['created'] === null
                        ? '—' : date('Y-m-d', $account['created'])) ?></td>
                    <td><?= h($account['last_login'] === null
                        ? '—'
                        : date('Y-m-d', $account['last_login'])) ?></td>
                    <td class="font-monospace vp-rf-dim"><?= h(implode(
                        ', ',
                        array_filter($account['sources'])
                    )) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
