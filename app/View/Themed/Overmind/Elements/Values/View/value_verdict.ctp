<?php
/**
 * The Verdict tab for a value whose signals agree.
 *
 * Agreeing, not malicious: a value everything points away from is the
 * same kind of argument as one everything points at, made in the same
 * shape, and it earns the same layout. What changes between MALICIOUS
 * and BENIGN is the colour, the glyph, and whether there is a
 * warninglist band — none of which is a reason for a second template.
 * Only CONFLICTED gets one, because two irreconcilable cases genuinely
 * do not fit a single ledger.
 *
 * One card, not four. The disposition, its provenance, the listing that
 * drives it where there is one, the signals behind it and the
 * contradictions that survived are a single argument, and separate
 * cards made the reader reassemble it. They are bands of one card here,
 * in the order the argument is made.
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
App::uses('ValueDisposition', 'Tools');

$verdict = $valueProfile['verdict'];

$uid = 'vp' . substr(md5($valueProfile['value'] . '-verdict'), 0, 8);

$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

$disposition = $verdict['disposition'];
$treatment = ValueDisposition::treatment($disposition);
$score = $verdict['score'];
$warninglist = $verdict['warninglist'] ?? null;

/*
 * The score reads as support for the disposition the card states, not
 * as a malice reading — a full bar on a BENIGN value means the benign
 * call is well evidenced, the same way it means the malicious call is
 * on a MALICIOUS one. One ruler, so the two are comparable; the
 * alternative is a near-empty bar under the word BENIGN, which reads
 * as a weak verdict rather than a confident one.
 */
$scoreLabel = sprintf(
    __('How strongly the evidence supports %s'),
    $disposition
);

/*
 * `Reads the value as` is carried only where the organisations differ
 * about what the value is rather than about what to do with it, so the
 * column follows the data instead of the layout: a benign value with
 * one organisation still treating it as an indicator needs it, and a
 * malicious value where everyone agrees on the reading does not.
 */
$orgColumns = array('to_ids', 'reliability');
foreach ($verdict['orgs'] as $org) {
    if (!empty($org['reads'])) {
        $orgColumns[] = 'reads';
        break;
    }
}
?>

<div class="card shadow-sm mb-3 vp-panel vp-vc vp-vc-agreeing
            vp-vc-<?= h($treatment['slug']) ?>"
     style="--vp-vc-color: <?= h($treatment['colour']) ?>;
            <?= h(ValueDisposition::directionStyle($disposition)) ?>">

    <?php
    /*
     * ----------------------------------------------------------
     * 1. Hero — the state, the score, and the reason
     * ----------------------------------------------------------
     */
    ?>
    <div class="vp-vc-hero">
        <span class="vp-vc-badge">
            <i class="<?= h($treatment['icon']) ?>"></i>
            <?= h($disposition) ?>
        </span>

        <?php if ($score !== null): ?>
            <div class="vp-vc-score" title="<?= h($scoreLabel) ?>">
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
     * 3. The listing, where one drives the verdict
     * ----------------------------------------------------------
     * A malicious value that hits no warninglist has no band here and
     * says so in the ledger instead, as one weak signal among many. A
     * benign one usually has the band, and it is usually the heaviest
     * row in the ledger below.
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
     * 4. The ledger
     * ----------------------------------------------------------
     */
    ?>
    <?= $this->element('Values/View/value_verdict_ledger', array(
        'verdict' => $verdict,
        'uid' => $uid,
        'noWrites' => $noWrites,
    )) ?>

</div>

<?php
/*
 * ------------------------------------------------------------------
 * Who says what
 * ------------------------------------------------------------------
 * Consensus is itself a signal, so it is shown per source. The
 * trailing columns are the ones that matter when the signals agree:
 * whether each organisation would have the value fire a rule, and how
 * much its say is worth.
 */
?>
<?= $this->element('Values/View/value_verdict_orgs', array(
    'verdict' => $verdict,
    'orgColumns' => $orgColumns,
    'orgsSub' => __(
        'One row per organisation — consensus is a signal, so it is'
        . ' shown per source'
    ),
)) ?>
