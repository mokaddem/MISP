<?php
/**
 * The Assessment tab for a value whose signals agree.
 *
 * Agreeing, not threatening: a value everything points away from is the
 * same kind of argument as one everything points at, made in the same
 * shape, and it earns the same layout. What changes between a `threat`
 * lean and a `benign` one is the colour, the glyph, and whether there
 * is a warninglist band — none of which is a reason for a second
 * template. Only `contested` gets one, because two irreconcilable cases
 * genuinely do not fit a single ledger.
 *
 * One card, not four. The lean, its provenance, the listing that drives
 * it where there is one, the signals behind it and the contradictions
 * that survived are a single argument, and separate cards made the
 * reader reassemble it. They are bands of one card here, in the order
 * the argument is made.
 *
 * `Who says what` stays its own card — the same argument counted a
 * different way, by organisation rather than by signal.
 *
 * The arithmetic, the trend, the exclusions and what would falsify the
 * assessment live in the rail, from `value_verdict_aside`.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewVerdict.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
App::uses('ValueLean', 'Tools');

$verdict = $valueProfile['verdict'];

$uid = 'vp' . substr(md5($valueProfile['value'] . '-verdict'), 0, 8);

$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

$lean = $verdict['lean'];
$treatment = ValueLean::treatment($lean);
$quality = $verdict['quality'];
$warninglist = $verdict['warninglist'] ?? null;

/*
 * Quality reads as support for the lean the card states, not as a
 * malice reading — a full bar on a `benign` record means the benign
 * assertion is well evidenced, the same way it means the threat
 * assertion is on a `threat` one. One ruler, so the two are comparable;
 * the alternative is a near-empty bar under *Asserted benign*, which
 * reads as a weak assessment rather than a confident one.
 */
$qualityLabel = sprintf(
    __('How strongly the record supports %s'),
    ValueLean::label($lean)
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
            <?= h(ValueLean::directionStyle($lean)) ?>">

    <?php
    /*
     * ----------------------------------------------------------
     * 1. Hero — the lean, the quality, and the reason
     * ----------------------------------------------------------
     */
    ?>
    <div class="vp-vc-hero">
        <?php
        /*
         * A `none` lean arrives here too — this layout carries every
         * value whose signals do not contradict each other, including
         * the ones with no signals at all. A solid badge in the same
         * weight as *Asserted threat* would state a conclusion where
         * there is none, so a lean that refuses to name a state is
         * drawn quietly.
         */
        ?>
        <span class="vp-vc-badge<?= $treatment['definite']
            ? ''
            : ' vp-vc-badge-quiet' ?>">
            <i class="<?= h($treatment['icon']) ?>"></i>
            <?= h(ValueLean::label($lean)) ?>
        </span>

        <?php if ($quality !== null): ?>
            <div class="vp-vc-score" title="<?= h($qualityLabel) ?>">
                <div class="vp-vc-score-heads">
                    <span><?= h(sprintf(
                        __('Quality %s'),
                        $verdict['band']
                    )) ?></span>
                    <span class="vp-vc-score-value">
                        <?= h($quality) ?> / 100
                    </span>
                </div>
                <?php /*
                 * Clamped, because a quality can be negative and the
                 * fixture's could not: the ledger sums to it exactly,
                 * so a record whose evidence disputes its own lean nets
                 * below zero — `8.8.8.8` closes at −1 on the dev
                 * instance. A negative width is an invalid declaration
                 * the browser drops, which leaves the fill at whatever
                 * width it inherits rather than at empty. The number
                 * beside it is printed unclamped, because that one is
                 * the assessment.
                 */ ?>
                <div class="vp-vc-score-track">
                    <span class="vp-vc-score-fill"
                          style="width: <?= max(0, min(100, (int)$quality)) ?>%;"
                    ></span>
                </div>
            </div>
        <?php endif; ?>

        <?php /*
         * The hero's paragraph, drawn only where there is one to draw.
         * `summary` is the key D11 left open — the composition of lean,
         * relevance and quality into a sentence — and nothing produces
         * it yet (`prd/analyst-profile/10-wiring.md` §2.2). The bands
         * below state the same argument in rows, so the card is
         * complete without it; an empty paragraph would only add a gap
         * where a reader expects a summary.
         */ ?>
        <?php if (!empty($verdict['summary'])): ?>
            <p class="vp-vc-prose vp-vc-prose-wide">
                <?= h($verdict['summary']) ?>
            </p>
        <?php endif; ?>

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
     * 3. The listing, where one drives the lean
     * ----------------------------------------------------------
     * A threat-leaning value that hits no warninglist has no band here
     * and says so in the ledger instead, as one weak signal among many.
     * A benign-leaning one usually has the band, and it is usually the
     * heaviest row in the ledger below.
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
