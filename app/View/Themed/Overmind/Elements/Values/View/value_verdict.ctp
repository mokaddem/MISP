<?php
/**
 * The Verdict tab for a value whose signals agree.
 *
 * One card, not four. The disposition, its provenance, the signals
 * behind it and the contradictions that survived are a single argument,
 * and separate cards made the reader reassemble it. They are bands of
 * one card here, and the ledger is a real table so that Signal,
 * Evidence, Contribution, Source panel and As of line up down the page.
 *
 * Contradictions are a group inside that table rather than a card of
 * their own: they are ledger rows whose contribution is `unresolved`,
 * and lifting them out would imply they were netted off somewhere.
 *
 * `Who says what` stays its own card — the same argument counted a
 * different way, by organisation rather than by signal.
 *
 * The arithmetic, the trend, the exclusions and what would falsify the
 * verdict live in the rail, from `value_verdict_aside`.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewVerdict.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$verdict = $valueProfile['verdict'];

$uid = 'vp' . substr(md5($valueProfile['value'] . '-verdict'), 0, 8);

$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

$score = $verdict['score'];
$conflicts = $verdict['conflicts'] ?? array();

/*
 * Contribution is drawn as a bar against the heaviest signal rather
 * than printed as a number: the ledger is read for shape — which
 * signals carry the verdict — and a column of signed integers hides
 * that behind arithmetic.
 */
$heaviest = 1;
foreach ($verdict['ledger'] as $group) {
    foreach ($group['signals'] as $signal) {
        $heaviest = max($heaviest, abs((int)$signal['contribution']));
    }
}
?>

<div class="card shadow-sm mb-3 vp-panel vp-vc vp-vc-malicious">

    <?php
    /*
     * ----------------------------------------------------------
     * 1. Hero — the state, the score, and the reason
     * ----------------------------------------------------------
     */
    ?>
    <div class="vp-vc-hero">
        <span class="vp-vc-badge">
            <i class="fas fa-triangle-exclamation"></i>
            <?= h($verdict['disposition']) ?>
        </span>

        <?php if ($score !== null): ?>
            <div class="vp-vc-score">
                <div class="vp-vc-score-heads">
                    <span><?= h(sprintf(
                        __('Confidence %s'),
                        $verdict['confidence']
                    )) ?></span>
                    <span class="vp-vc-score-value">
                        <?= h($score) ?> / 100
                    </span>
                </div>
                <div class="vp-vc-score-track">
                    <span class="vp-vc-score-fill"
                          style="width: <?= (int)$score ?>%;"></span>
                </div>
            </div>
        <?php endif; ?>

        <p class="vp-vc-prose vp-vc-prose-wide">
            <?= h($verdict['summary']) ?>
        </p>

        <div class="vp-vc-hero-actions">
            <button type="button" class="vp-vc-hero-action disabled"
                    disabled title="<?= h($noWrites) ?>">
                <i class="fas fa-rotate"></i>
                <?= __('Recompute') ?>
            </button>
            <button type="button"
                    class="vp-vc-hero-action vp-vc-hero-action-mono
                           disabled"
                    disabled title="<?= h($noWrites) ?>">
                <?= __('view as JSON') ?>
            </button>
        </div>
    </div>

    <?php
    /*
     * ----------------------------------------------------------
     * 2. Provenance
     * ----------------------------------------------------------
     */
    ?>
    <?= $this->element('Values/View/value_verdict_meta', array(
        'verdict' => $verdict,
    )) ?>

    <?php
    /*
     * ----------------------------------------------------------
     * 3. The ledger
     * ----------------------------------------------------------
     * Grouped by kind rather than sorted by weight: an analyst checking
     * whether the sightings were counted twice wants them next to each
     * other, not scattered through a ranking.
     */
    ?>
    <?php if (empty($verdict['ledger']) && empty($conflicts)): ?>
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
                        <th class="vp-ledger-contrib-col">
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

                    <?php foreach ($verdict['ledger'] as $group): ?>
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
                                             __('%s points'),
                                             $signal['contribution']
                                         )) ?>">
                                        <span class="vp-vc-bar">
                                            <span class="vp-vc-bar-fill"
                                                  style="width: <?= round(
                                                      $points / $heaviest
                                                      * 100,
                                                      2
                                                  ) ?>%;"></span>
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
                                        'not netted off — shown as'
                                        . ' unresolved'
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
                                            <i class="fas fa-chevron-down">
                                            </i>
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

</div>

<?php
/*
 * ------------------------------------------------------------------
 * Who says what
 * ------------------------------------------------------------------
 * Consensus is itself a signal, so it is shown per source.
 */
?>
<?php if (!empty($verdict['orgs'])): ?>
    <div class="card shadow-sm mb-3 vp-panel"
         style="--vp-panel-color: var(--object);">

        <?= $this->element('Values/View/value_panel_header', array(
            'panelTitle' => __('Who says what'),
            'panelIcon' => 'misp-icon misp-icon-organisation misp-simple',
            'panelColor' => 'var(--object)',
            'panelSub' => h(__(
                'One row per organisation — consensus is a signal, so it'
                . ' is shown per source'
            )),
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
                        <th><?= __('to_ids stance') ?></th>
                        <th><?= __('Source reliability') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($verdict['orgs'] as $org): ?>
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
                            <td><?= h($org['to_ids']) ?></td>
                            <td>
                                <span class="vp-reliability">
                                    <?= h($org['reliability']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
<?php endif; ?>
