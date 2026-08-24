<?php
/**
 * One disagreement, shown whole.
 *
 * A 6/4 split and a unanimous 10 are different situations, and a net
 * figure describes neither, so the two counts sit beside each other and
 * the occurrences behind them are listed.
 *
 * Its own element because the block nests a table three levels inside a
 * card, and inline it left no column width for the markup to breathe.
 *
 * @var array $conflict  kind, title, note, yes, no, rows, actions
 * @var string $noWrites Why every action here is disabled
 */
$yes = (int)$conflict['yes'];
$no = (int)$conflict['no'];
$sum = max($yes + $no, 1);

/*
 * The badge factory directly rather than the `ids` field renderer:
 * these rows are stances, not attribute rows, and carry no id for the
 * interactive shield to toggle.
 */
$idsBadge = array(
    'full' => true,
    'true' => __('IDS'),
    'false' => __('No IDS'),
    'trueColor' => 'warning',
    'falseColor' => 'secondary',
    'trueIcon' => 'fa-shield-halved',
    'falseIcon' => 'fa-shield-halved',
);
?>
<div class="vp-conflict-block">

    <div class="vp-conflict-head">
        <span class="vp-conflict-title">
            <?= h($conflict['title']) ?>
        </span>
        <span class="vp-conflict-kind">
            <?= h($conflict['kind']) ?>
        </span>
    </div>

    <div class="vp-split"
         title="<?= h(sprintf(__('%1$s for, %2$s against'), $yes, $no)) ?>">
        <span class="vp-split-yes"
              style="width: <?= round($yes / $sum * 100, 2) ?>%;">
            <?= h($yes) ?>
        </span>
        <span class="vp-split-no"
              style="width: <?= round($no / $sum * 100, 2) ?>%;">
            <?= h($no) ?>
        </span>
    </div>

    <p class="vp-conflict-note mb-0">
        <?= h($conflict['note']) ?>
    </p>

    <?php if (!empty($conflict['rows'])): ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle vp-table mb-0">
                <thead>
                    <tr>
                        <th><?= __('Event') ?></th>
                        <th><?= __('Organisation') ?></th>
                        <th><?= __('to_ids') ?></th>
                        <th><?= __('Category') ?></th>
                        <th><?= __('Comment') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conflict['rows'] as $row): ?>
                        <tr>
                            <td>
                                <a href="<?= $baseurl ?>/events/view/<?=
                                    h($row['event_id']) ?>">
                                    #<?= h($row['event_id']) ?>
                                </a>
                            </td>
                            <td><?= h($row['org']) ?></td>
                            <td>
                                <?= $this->element(
                                    'genericElementsBS5/Badges/boolean',
                                    $idsBadge + array(
                                        'boolean' => !empty($row['to_ids']),
                                    )
                                ) ?>
                            </td>
                            <td><?= h($row['category']) ?></td>
                            <td class="text-muted">
                                <?= h($row['comment']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($conflict['actions'])): ?>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($conflict['actions'] as $action): ?>
                <button type="button"
                        class="btn btn-sm btn-outline-dark disabled"
                        title="<?= h($noWrites) ?>">
                    <?= h($action) ?>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
