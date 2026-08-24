<?php
/**
 * The occurrences behind one contradiction, revealed under its ledger
 * row.
 *
 * A `to_ids` split is a claim about rows, so the rows are what settles
 * it: which event, whose, what type, and which way each was set. The
 * two bulk actions name what they would touch, and the line under them
 * says how much — an action that would edit six rows in six events
 * across four organisations should say so before it is pressed, not
 * after.
 *
 * @var array $conflict  rows, actions, confirm_note
 * @var string $noWrites Why the actions are disabled
 */
$rows = $conflict['rows'];
$actions = $conflict['actions'] ?? array();
$confirmNote = $conflict['confirm_note'] ?? null;
?>
<div class="vp-conflict-rows">
    <table class="vp-conflict-table">
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="vp-cr-event">
                        <a href="<?= $baseurl ?>/events/view/<?=
                            h($row['event_id']) ?>">
                            #<?= h($row['event_id']) ?>
                        </a>
                    </td>
                    <td class="vp-cr-info">
                        <?= h($row['event_info'] ?? '') ?>
                    </td>
                    <td class="vp-cr-org"><?= h($row['org']) ?></td>
                    <td class="vp-cr-type">
                        <?= h($row['type'] ?? '') ?>
                    </td>
                    <td class="vp-cr-ids">
                        <span class="vp-cr-ids-badge<?= empty($row['to_ids'])
                            ? ' vp-cr-ids-no'
                            : ' vp-cr-ids-yes' ?>">
                            <?= empty($row['to_ids'])
                                ? h(__('no'))
                                : h(__('yes')) ?>
                        </span>
                    </td>
                    <td class="vp-cr-who"><?= h($row['comment']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($actions)): ?>
        <div class="vp-conflict-actions">
            <?php foreach ($actions as $action): ?>
                <button type="button" class="vp-conflict-action disabled"
                        disabled title="<?= h($noWrites) ?>">
                    <i class="<?= h($action['icon']) ?>"
                       style="color: <?= h($action['colour']) ?>;"></i>
                    <?= h($action['label']) ?>
                </button>
            <?php endforeach; ?>
            <?php if ($confirmNote !== null): ?>
                <span class="vp-conflict-confirm">
                    <?= h($confirmNote) ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
