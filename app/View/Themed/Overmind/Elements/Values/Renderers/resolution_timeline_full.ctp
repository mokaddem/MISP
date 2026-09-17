<?php
/**
 * Every resolution, with its window and how often it was seen.
 *
 * Sorted by how recently each was current, because *what does this
 * resolve to now* is the question and *what did it resolve to in 2014*
 * is the context. The observation count is drawn as a number rather
 * than as a bar: it spans four orders of magnitude on a single value
 * and a bar chart of it is one bar and a row of slivers.
 *
 * @var array $data
 */
?>
<div class="vp-rf vp-rf-res">
    <?php if (!$data['dated']): ?>
        <p class="vp-rf-note"><?= h(
            __('These are current resolutions. The source that'
                . ' returned them carries no first or last seen, so'
                . ' there is no history to draw.')
        ) ?></p>
    <?php elseif ($data['first'] !== null): ?>
        <p class="vp-rf-note"><?= h(sprintf(
            __('%1$s resolutions, first seen %2$s, last %3$s.'),
            number_format($data['distinct']),
            date('Y-m-d', $data['first']),
            $data['last'] === null
                ? '?' : date('Y-m-d', $data['last'])
        )) ?></p>
    <?php endif; ?>

    <table class="table table-sm vp-rf-table">
        <thead>
            <tr>
                <th scope="col"><?= h(__('Name')) ?></th>
                <th scope="col"><?= h(__('Type')) ?></th>
                <th scope="col"><?= h(__('Resolves to')) ?></th>
                <th scope="col"><?= h(__('First')) ?></th>
                <th scope="col"><?= h(__('Last')) ?></th>
                <th scope="col" class="text-end"><?=
                    h(__('Seen')) ?></th>
                <th scope="col"><?= h(__('Source')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['resolutions'] as $row): ?>
                <tr>
                    <td class="font-monospace"><?= h($row['from'] === ''
                        ? '—' : $row['from']) ?></td>
                    <td><?= h($row['type'] === ''
                        ? '—' : $row['type']) ?></td>
                    <td class="font-monospace"><?= h($row['to']) ?></td>
                    <td><?= h($row['first'] === null
                        ? '—' : date('Y-m-d', $row['first'])) ?></td>
                    <td><?= h($row['last'] === null
                        ? '—' : date('Y-m-d', $row['last'])) ?></td>
                    <td class="text-end"><?= h($row['count'] > 1
                        ? number_format($row['count']) : '') ?></td>
                    <td class="font-monospace vp-rf-dim"><?= h(implode(
                        ', ',
                        array_filter($row['sources'])
                    )) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
