<?php
/**
 * Who says what — the same argument counted by organisation rather
 * than by signal.
 *
 * Every verdict layout ends with this card, because consensus is itself
 * a signal and a reader who distrusts one source needs to see the
 * verdict with that source taken out.
 *
 * The first five columns are the same question everywhere: how much
 * each organisation contributed and what it thinks. What differs is
 * what the layout needs after them, so the trailing columns are named
 * by the caller — `to_ids` and `reliability` where the disagreement is
 * about whether to act, `reads` where it is about what the value even
 * is.
 *
 * @var array $verdict
 * @var array $orgColumns  Any of `to_ids`, `reliability`, `reads`
 * @var string $orgsSub    The subtitle: why this table is here for
 *                         this particular value
 */
$orgs = $verdict['orgs'] ?? array();
$orgColumns = $orgColumns ?? array('to_ids', 'reliability');

$headings = array(
    'to_ids' => __('to_ids stance'),
    'reliability' => __('Source reliability'),
    'reads' => __('Reads the value as'),
);
?>
<?php if (!empty($orgs)): ?>
    <div class="card shadow-sm mb-3 vp-panel"
         style="--vp-panel-color: var(--object);">

        <?= $this->element('Values/View/value_panel_header', array(
            'panelTitle' => __('Who says what'),
            'panelIcon' => 'misp-icon misp-icon-organisation misp-simple',
            'panelColor' => 'var(--object)',
            'panelSub' => h($orgsSub),
        )) ?>

        <div class="table-responsive">
            <table class="table table-sm align-middle vp-table mb-0">
                <thead>
                    <tr>
                        <th><?= __('Organisation') ?></th>
                        <th class="text-end"><?= __('Occurrences') ?></th>
                        <th class="text-end"><?= __('Sightings') ?></th>
                        <th class="text-end"><?= __('False positives') ?></th>
                        <th><?= __('Opinion') ?></th>
                        <?php foreach ($orgColumns as $column): ?>
                            <th><?= h($headings[$column] ?? $column) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orgs as $org): ?>
                        <tr>
                            <td class="fw-semibold"><?= h($org['org']) ?></td>
                            <td class="text-end">
                                <?= h($org['occurrences']) ?>
                            </td>
                            <td class="text-end">
                                <?= h($org['sightings']) ?>
                            </td>
                            <td class="text-end<?= $org['fp'] > 0
                                ? ' text-danger fw-semibold'
                                : ' text-muted' ?>">
                                <?= h($org['fp']) ?>
                            </td>
                            <td>
                                <?php if ($org['opinion'] === null): ?>
                                    <span class="text-muted">
                                        <?= __('none stated') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="vp-opinion"
                                          title="<?= h(sprintf(
                                              __('Opinion %s of 100'),
                                              $org['opinion']
                                          )) ?>">
                                        <span class="vp-opinion-track">
                                            <span class="vp-opinion-fill"
                                                  style="width: <?=
                                                      (int)$org['opinion']
                                                      ?>%;"></span>
                                        </span>
                                        <span class="vp-opinion-value">
                                            <?= h($org['opinion']) ?>
                                        </span>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <?php foreach ($orgColumns as $column): ?>
                                <?php if ($column === 'reliability'): ?>
                                    <td>
                                        <span class="vp-reliability">
                                            <?= h($org['reliability']) ?>
                                        </span>
                                    </td>
                                <?php elseif ($column === 'reads'): ?>
                                    <td>
                                        <span class="vp-side vp-side-<?=
                                            h($org['side'] ?? 'none') ?>">
                                            <?= h($org['reads']) ?>
                                        </span>
                                    </td>
                                <?php else: ?>
                                    <td><?= h($org[$column]) ?></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
<?php endif; ?>
