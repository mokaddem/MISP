<?php
/**
 * The Verdict tab for a value whose signals contradict each other.
 *
 * One card, not five. The disposition, what it rests on, the
 * warninglist hit that causes half the trouble, the two cases and the
 * ambiguities between them are a single argument, and splitting them
 * into separate cards made the reader reassemble it. They are bands of
 * one card here, in the order the argument is made.
 *
 * There is deliberately no score. The tug-of-war bar puts the two
 * weights against each other with the unresolved wedge between them,
 * striped because it belongs to neither side.
 *
 * `Who says what` stays its own card: it is the same argument counted
 * a different way, by organisation rather than by signal.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewVerdict.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$verdict = $valueProfile['verdict'];

$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

$tug = $verdict['tug'];
$tugTotal = max(
    array_sum(array($tug['malicious'], $tug['benign'], $tug['unresolved'])),
    1
);

$cases = $verdict['cases'];
$ambiguities = $verdict['ambiguities'] ?? array();
$warninglist = $verdict['warninglist'] ?? null;

/*
 * The row bars are read against the heaviest signal on either side, so
 * a strong signal looks strong in both columns. Scaling each column to
 * its own maximum would make the weaker case look just as emphatic.
 */
$heaviest = 1;
foreach ($cases as $case) {
    foreach ($case['rows'] as $row) {
        $heaviest = max($heaviest, (int)$row['points']);
    }
}
?>

<div class="card shadow-sm mb-3 vp-panel vp-vc">

    <?php
    /*
     * ----------------------------------------------------------
     * 1. Hero — the state, the reason, and the balance
     * ----------------------------------------------------------
     */
    ?>
    <div class="vp-vc-hero">
        <span class="vp-vc-state">
            <i class="fas fa-circle-exclamation"></i>
            <span class="vp-vc-state-word">
                <?= h($verdict['disposition']) ?>
            </span>
        </span>

        <p class="vp-vc-prose"><?= h($verdict['summary']) ?></p>

        <div class="vp-tug-block">
            <div class="vp-tug-heads">
                <span class="vp-tug-head-mal">
                    <?= h(sprintf(
                        __('Malicious case %s'),
                        $tug['malicious']
                    )) ?>
                </span>
                <span class="vp-tug-head-ben">
                    <?= h(sprintf(
                        __('%s benign case'),
                        $tug['benign']
                    )) ?>
                </span>
            </div>
            <div class="vp-tug">
                <span class="vp-tug-mal" style="width: <?= round(
                    $tug['malicious'] / $tugTotal * 100,
                    2
                ) ?>%;"></span>
                <span class="vp-tug-none" style="width: <?= round(
                    $tug['unresolved'] / $tugTotal * 100,
                    2
                ) ?>%;"></span>
                <span class="vp-tug-ben" style="width: <?= round(
                    $tug['benign'] / $tugTotal * 100,
                    2
                ) ?>%;"></span>
            </div>
            <div class="vp-tug-feet">
                <span><?= h(sprintf(
                    __('%s signals'),
                    count($cases[0]['rows'])
                )) ?></span>
                <span><?= h(sprintf(
                    __('%s unresolved'),
                    count($ambiguities)
                )) ?></span>
                <span><?= h(sprintf(
                    __('%s signals'),
                    count($cases[1]['rows'])
                )) ?></span>
            </div>
        </div>
    </div>

    <?php
    /*
     * ----------------------------------------------------------
     * 2. Provenance and the rule that fired
     * ----------------------------------------------------------
     */
    ?>
    <?= $this->element('Values/View/value_verdict_meta', array(
        'verdict' => $verdict,
        'metaRule' => $verdict['rule']['text'] ?? null,
    )) ?>

    <?php
    /*
     * ----------------------------------------------------------
     * 3. The warninglist hit
     * ----------------------------------------------------------
     * A band rather than a card, because on this value it is one of the
     * two cases talking — not a separate finding.
     */
    ?>
    <?php if ($warninglist !== null): ?>
        <?= $this->element('Values/View/value_verdict_warninglist', array(
            'warninglist' => $warninglist,
        )) ?>
    <?php endif; ?>

    <?php
    /*
     * ----------------------------------------------------------
     * 4. The two cases
     * ----------------------------------------------------------
     * Side by side and the same shape, so the comparison is between the
     * evidence rather than between two presentations of it.
     */
    ?>
    <div class="vp-vc-cases">
        <?php foreach ($cases as $case):
            $mal = $case['side'] === 'malicious';
            ?>
            <div class="vp-vc-case vp-vc-case-<?= h($case['side']) ?>">
                <div class="vp-vc-case-head">
                    <span class="vp-vc-case-arrow">
                        <?= $mal ? '&#9650;' : '&#9660;' ?>
                    </span>
                    <span class="vp-vc-case-title">
                        <?= h(sprintf(
                            __('%1$s — %2$s'),
                            $case['title'],
                            $case['weight']
                        )) ?>
                    </span>
                    <span class="vp-vc-case-count">
                        <?= h(sprintf(
                            __('%s signals'),
                            count($case['rows'])
                        )) ?>
                    </span>
                </div>
                <?php foreach ($case['rows'] as $row): ?>
                    <div class="vp-vc-row">
                        <div class="vp-vc-row-top">
                            <span class="vp-vc-signal">
                                <?= h($row['signal']) ?>
                            </span>
                            <span class="vp-vc-bar"
                                  title="<?= h(sprintf(
                                      __('%s points'),
                                      $row['points']
                                  )) ?>">
                                <span class="vp-vc-bar-fill"
                                      style="width: <?= round(
                                          $row['points'] / $heaviest * 100,
                                          2
                                      ) ?>%;"></span>
                            </span>
                            <span class="vp-vc-weight">
                                <?= h($row['weight']) ?>
                            </span>
                        </div>
                        <div class="vp-vc-row-bottom">
                            <span class="vp-vc-evidence">
                                <?= h($row['evidence']) ?>
                            </span>
                            <span class="vp-vc-panel">
                                <?= h($row['source']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    /*
     * ----------------------------------------------------------
     * 5. Unresolved
     * ----------------------------------------------------------
     * Splits that could fall either way, named rather than assigned.
     */
    ?>
    <?php if (!empty($ambiguities)): ?>
        <div class="vp-vc-unresolved">
            <div class="vp-vc-unresolved-head">
                <span class="vp-vc-unresolved-mark">&#9670;</span>
                <?= __('Unresolved — counted for neither side') ?>
            </div>
            <div class="vp-vc-unresolved-body">
                <?php foreach ($ambiguities as $item): ?>
                    <div class="vp-vc-unresolved-item">
                        <div class="vp-vc-unresolved-title">
                            <?= h($item['title']) ?>
                        </div>
                        <div class="vp-vc-unresolved-note">
                            <?= h($item['note']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
/*
 * ------------------------------------------------------------------
 * Who says what
 * ------------------------------------------------------------------
 * The disagreement is between organisations, so it is also shown per
 * organisation — and here the trailing column is the reading itself,
 * because on this value the organisations do not disagree about what
 * to do so much as about what the address is.
 */
?>
<?= $this->element('Values/View/value_verdict_orgs', array(
    'verdict' => $verdict,
    'orgColumns' => array('reads'),
    'orgsSub' => __(
        'The disagreement is between organisations, so it is shown per'
        . ' organisation'
    ),
)) ?>
