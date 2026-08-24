<?php
/**
 * The signal ledger: every row that produced the score, and every row
 * that refused to.
 *
 * A real table rather than a stack of rows, so Signal, Evidence,
 * Contribution, Source panel and As of line up down the page and a
 * reader can scan one column at a time.
 *
 * Grouped by kind rather than sorted by weight: an analyst checking
 * whether the sightings were counted twice wants them next to each
 * other, not scattered through a ranking.
 *
 * The direction column reads against the stated disposition, not
 * against maliciousness — ▲ is a row that supports the verdict, ▼ one
 * that argues with it. That is the same thing on a MALICIOUS value and
 * the opposite on a BENIGN one, which is why the header says so on
 * hover rather than leaving the reader to infer it from the sign.
 *
 * Contradictions are a group inside this table rather than a card of
 * their own: they are ledger rows whose contribution is `unresolved`,
 * and lifting them out would imply they had been netted off somewhere.
 *
 * @var array $verdict
 * @var string $uid      Namespace for the collapse targets
 * @var string $noWrites Why the actions inside a conflict are disabled
 */
$ledger = $verdict['ledger'] ?? array();
$conflicts = $verdict['conflicts'] ?? array();

/*
 * Contribution is drawn as a bar against the heaviest signal as well as
 * printed: the ledger is read for shape — which signals carry the
 * verdict — and a column of signed integers alone hides that behind
 * arithmetic.
 */
$heaviest = 1;
foreach ($ledger as $group) {
    foreach ($group['signals'] as $signal) {
        $heaviest = max($heaviest, abs((int)$signal['contribution']));
    }
}
?>
<?php if (empty($ledger) && empty($conflicts)): ?>
    <div class="vp-empty">
        <i class="fas fa-list-check"></i>
        <span><?= __('No signal contributed to this verdict.') ?></span>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm mb-0 vp-ledger-table">
            <thead>
                <tr>
                    <th class="vp-ledger-dir"></th>
                    <th><?= __('Signal') ?></th>
                    <th><?= __('Evidence') ?></th>
                    <th class="vp-ledger-contrib-col"
                        title="<?= h(__(
                            'Points for and against the stated'
                            . ' disposition, not for and against'
                            . ' maliciousness.'
                        )) ?>">
                        <?= __('Contribution') ?>
                    </th>
                    <th class="vp-ledger-panel-col">
                        <?= __('Source panel') ?>
                    </th>
                    <th class="vp-ledger-asof-col">
                        <?= __('As of') ?>
                    </th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($ledger as $group): ?>
                    <tr class="vp-ledger-group">
                        <td colspan="6">
                            <?= h($group['kind']) ?>
                            <?php if (!empty($group['note'])): ?>
                                <span class="vp-ledger-group-note">
                                    <?= h($group['note']) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php foreach ($group['signals'] as $signal):
                        $up = $signal['direction'] === 'up';
                        $points = abs((int)$signal['contribution']);
                        ?>
                        <tr class="vp-ledger-line<?= $up
                            ? ' vp-ledger-line-up'
                            : ' vp-ledger-line-down' ?>">
                            <td class="vp-ledger-dir">
                                <?= $up ? '&#9650;' : '&#9660;' ?>
                            </td>
                            <td class="vp-ledger-signal-cell">
                                <?= h($signal['signal']) ?>
                            </td>
                            <td class="vp-ledger-evidence">
                                <?= h($signal['evidence']) ?>
                            </td>
                            <td>
                                <div class="vp-ledger-contrib-cell"
                                     title="<?= h(sprintf(
                                         __('%1$s points, %2$s'),
                                         $signal['contribution'],
                                         $signal['weight']
                                     )) ?>">
                                    <span class="vp-vc-bar">
                                        <span class="vp-vc-bar-fill"
                                              style="width: <?= round(
                                                  $points / $heaviest
                                                  * 100,
                                                  2
                                              ) ?>%;"></span>
                                    </span>
                                    <span class="vp-ledger-points">
                                        <?= h(
                                            ($signal['contribution'] > 0
                                                ? '+'
                                                : '')
                                            . $signal['contribution']
                                        ) ?>
                                    </span>
                                    <span class="vp-ledger-weight">
                                        <?= h($signal['weight']) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="vp-ledger-panel">
                                <?= h($signal['source']) ?>
                            </td>
                            <td class="vp-ledger-asof">
                                <?= h($signal['as_of']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>

                <?php
                /*
                 * Contradictions, in the same table and marked
                 * `unresolved`. Not netted off, and not moved
                 * somewhere that would imply they had been.
                 */
                ?>
                <?php if (!empty($conflicts)): ?>
                    <tr class="vp-ledger-group vp-ledger-group-conflict">
                        <td colspan="6">
                            <?= __('Contradictions &amp; conflicts') ?>
                            <span class="vp-ledger-group-note">
                                <?= __(
                                    'not netted off — shown as unresolved'
                                ) ?>
                            </span>
                        </td>
                    </tr>
                    <?php foreach ($conflicts as $c => $conflict):
                        $open = !empty($conflict['expanded'])
                            && !empty($conflict['rows']);
                        $rowId = $uid . '-conflict-' . $c;
                        ?>
                        <tr class="vp-ledger-conflict">
                            <td class="vp-ledger-dir">
                                <?php if (!empty($conflict['rows'])): ?>
                                    <button type="button"
                                            class="vp-ledger-disclose<?=
                                                $open
                                                    ? ''
                                                    : ' collapsed' ?>"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#<?=
                                                h($rowId) ?>"
                                            aria-expanded="<?= $open
                                                ? 'true'
                                                : 'false' ?>"
                                            aria-controls="<?=
                                                h($rowId) ?>"
                                            aria-label="<?= h(__(
                                                'Show the occurrences'
                                                . ' behind this'
                                            )) ?>">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="vp-ledger-mark">
                                        &#9670;
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="vp-ledger-signal-cell">
                                <?= h($conflict['title']) ?>
                            </td>
                            <td class="vp-ledger-evidence">
                                <?= h($conflict['evidence']
                                    ?? $conflict['note']) ?>
                            </td>
                            <td>
                                <span class="vp-ledger-unresolved">
                                    <?= __('unresolved') ?>
                                </span>
                            </td>
                            <td class="vp-ledger-panel">
                                <?= h(__('Occurrences')) ?>
                            </td>
                            <td class="vp-ledger-asof">
                                <?= h(__('now')) ?>
                            </td>
                        </tr>
                        <?php if (!empty($conflict['rows'])): ?>
                            <tr class="vp-ledger-conflict
                                       vp-ledger-detail-row">
                                <td></td>
                                <td colspan="5">
                                    <div class="collapse<?= $open
                                        ? ' show'
                                        : '' ?>"
                                         id="<?= h($rowId) ?>">
                                        <?= $this->element(
                                            'Values/View'
                                            . '/value_conflict_rows',
                                            array(
                                                'conflict' => $conflict,
                                                'noWrites' => $noWrites,
                                            )
                                        ) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

            </tbody>
        </table>
    </div>
<?php endif; ?>
