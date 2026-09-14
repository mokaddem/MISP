<?php
/**
 * The lean band — how this reading was decided.
 *
 * The clock band's sibling, and the last of D11's three axes to get
 * its working onto the page. `10-wiring.md` §13.3 wired up the one
 * exit that had prose — a loaded conflict rule — and left six that had
 * none, so an ordinary value stated *Asserted threat* above a
 * provenance line reading *Computed at render · Analyst profile
 * default-v1* and nothing else. The organisations that decided it were
 * listed further down in *Who says what*; the arithmetic that turned
 * those rows into a lean was nowhere at all.
 *
 * **It costs no query.** `stances` is computed by
 * `ValueLeanTool::stancesFor()` on every value and, until this band,
 * was read by no template in the application — the same shape
 * `$verdict['relevance']` was in before §17: already built, discarded
 * at render.
 *
 * **The bar is the ledger's grammar, not a new one.** The clock band
 * draws a runway against a mark where the profile stops calling a
 * value current; this draws the threat share against the mark where
 * the profile starts calling the record decided. Two marks rather than
 * one, because a supermajority is a bar on *either* side — phase 3
 * §11.1's fix, where stating the rule as one comparison and its mirror
 * made a value of 34 in 100 miss by a floating-point hair.
 *
 * **Written, not extracted**, which is the difference from §17. The
 * relevance axis had a second surface already drawing its working, so
 * sharing one element made two panels unable to disagree; the lean has
 * no such surface — the editor's bench draws it as a bare word too. If
 * the bench ever wants the split, this is the element to take.
 *
 * @var array $verdict The assessment, carrying `stances`, `decided_by`
 *                     and `rule_errors`
 */
App::uses('ValueLeanReasonTool', 'Tools/ValueProfile');

$reason = ValueLeanReasonTool::reasonFor($verdict);
$stances = isset($verdict['stances']) && is_array($verdict['stances'])
    ? $verdict['stances']
    : array();
$errors = isset($verdict['rule_errors'])
    && is_array($verdict['rule_errors'])
    ? $verdict['rule_errors']
    : array();

/*
 * The lean ledger, which belongs here and used to be filed with the
 * quality's. `review-2026-09-13.md` §D1 took the two axes apart: the
 * warninglist's hits and false-positive sightings are the only rows
 * that read the *value*, so they are the only rows that anchor — and
 * that leaves them out of the table below, which now sums to the
 * quality alone.
 *
 * They cannot simply disappear with it. A `−38` listing is the
 * heaviest thing on a benign record and the band above says the
 * reading was decided; these rows are how much weight was behind that
 * decision, which is the one question the stance counts cannot answer.
 */
$leanRows = isset($verdict['lean_ledger'])
    && is_array($verdict['lean_ledger'])
    ? $verdict['lean_ledger']
    : array();
$leanWeight = (int)(isset($verdict['lean_weight'])
    ? $verdict['lean_weight']
    : 0);
$leanHeaviest = 1;
foreach ($leanRows as $leanRow) {
    $leanHeaviest = max(
        $leanHeaviest,
        abs((int)$leanRow['contribution'])
    );
}

/*
 * No sentence, no band. That is the empty record — whose hero already
 * says there is nothing to assess, and §17.4's argument applies
 * unchanged — and any exit a future rule adds without giving it words,
 * which should draw nothing rather than an empty frame.
 */
if ($reason === null) {
    return;
}

$total = (int)(isset($stances['orgs']) ? $stances['orgs'] : 0);
$threat = (int)(isset($stances['threat_orgs'])
    ? $stances['threat_orgs'] : 0);
$benign = (int)(isset($stances['benign_orgs'])
    ? $stances['benign_orgs'] : 0);
$share = $total === 0
    ? 0.0
    : (float)(isset($stances['threat_share'])
        ? $stances['threat_share'] : 0.0);
$supermajority = isset($stances['supermajority'])
    && is_numeric($stances['supermajority'])
    ? (float)$stances['supermajority']
    : null;
?>
<div class="vp-vc-lean">
    <div class="vp-vc-lean-head"
         title="<?= h(__('The lean is counted before any signal is'
             . ' scored: organisations are counted per side, and a'
             . ' supermajority on either side decides the reading. No'
             . ' points are involved.')) ?>">
        <i class="fas fa-scale-balanced vp-vc-lean-mark"></i>
        <?= h(__('How this reading was decided')) ?>
    </div>
    <div class="vp-vc-lean-body<?= empty($leanRows)
        ? ''
        : ' vp-vc-band-split' ?>">

        <div class="vp-vc-band-bar">
        <?php if ($total > 0): ?>
            <div class="vp-vc-lean-counts">
                <span class="vp-vc-lean-count vp-vc-lean-threat"
                      title="<?= h(__('Organisations with at least one'
                          . ' occurrence flagged for detection.')) ?>">
                    <?= h(sprintf(
                        __n(
                            '%s organisation asserts a threat',
                            '%s organisations assert a threat',
                            $threat
                        ),
                        $threat
                    )) ?>
                </span>
                <span class="vp-vc-lean-count vp-vc-lean-benign"
                      title="<?= h(__('Organisations whose occurrences'
                          . ' are all unflagged — the record says'
                          . ' harmless by not asserting.')) ?>">
                    <?= h(sprintf(
                        __n(
                            '%s reports it as harmless',
                            '%s report it as harmless',
                            $benign
                        ),
                        $benign
                    )) ?>
                </span>
            </div>

            <?php
            /*
             * Filled from the threat side, with the rest of the track
             * standing for the benign one — one bar rather than two,
             * because the quantity is a *share* and two bars invite a
             * reader to compare their lengths against nothing.
             */
            ?>
            <div class="vp-vc-lean-track"
                 title="<?= h(sprintf(
                     __('%1$s of %2$s organisations assert a threat'),
                     $threat,
                     $total
                 )) ?>">
                <span class="vp-vc-lean-fill"
                      style="width: <?= (int)round($share * 100) ?>%;"
                ></span>
                <?php if ($supermajority !== null): ?>
                    <?php
                    /*
                     * Both marks, always. The bar a threat reading has
                     * to clear and the one a benign reading has to
                     * clear are the same bar seen from two ends, and
                     * drawing only the near one would make a value
                     * sitting between them look as though it had
                     * missed a single threshold rather than both.
                     */
                    ?>
                    <span class="vp-vc-lean-bar"
                          title="<?= h(__('the threat supermajority')) ?>"
                          style="left: <?=
                              (int)round($supermajority * 100) ?>%;"
                    ></span>
                    <span class="vp-vc-lean-bar"
                          title="<?= h(__('the benign supermajority')) ?>"
                          style="left: <?=
                              (int)round((1 - $supermajority) * 100)
                          ?>%;"></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <p class="vp-vc-lean-note"><?= h($reason) ?></p>
        </div>

        <div class="vp-vc-band-list">
        <?php
        /*
         * And what else reads the value. Stance counts are one
         * organisation, one vote; these are the rows that carry a
         * weight, so both the count and the weight are on the page and
         * a reader can see which of the two decided.
         *
         * Signed against the lean, like the ledger's own rows: `+38`
         * is a row supporting the reading stated above, `−38` one
         * disputing it. On a contested lean the rows are threat-signed
         * — rule 7 put them back — so a negative row is the half of
         * the contradiction arguing benign.
         */
        ?>
        <?php if (!empty($leanRows)): ?>
            <div class="vp-vc-lean-rows">
                <div class="vp-vc-lean-rows-head">
                    <?= h(__('What else reads the value')) ?>
                    <span class="vp-vc-lean-rows-total"
                          title="<?= h(__(
                              'These rows sum to this. They are not'
                              . ' part of the quality below, which'
                              . ' weighs how much record there is'
                              . ' rather than what it says.'
                          )) ?>">
                        <?= h(($leanWeight > 0 ? '+' : '') . $leanWeight) ?>
                    </span>
                </div>
                <?php foreach ($leanRows as $leanRow):
                    $leanUp = (int)$leanRow['contribution'] >= 0;
                    $leanPoints = abs((int)$leanRow['contribution']);
                    ?>
                    <div class="vp-vc-lean-row<?= $leanUp
                        ? ' vp-vc-lean-row-up'
                        : ' vp-vc-lean-row-down' ?>">
                        <span class="vp-vc-lean-row-mark">
                            <?= $leanUp ? '&#9650;' : '&#9660;' ?>
                        </span>
                        <span class="vp-vc-lean-row-signal">
                            <?= h($leanRow['signal']) ?>
                            <span class="vp-vc-lean-row-evidence">
                                <?= h($leanRow['evidence']) ?>
                            </span>
                        </span>
                        <span class="vp-vc-lean-row-bar">
                            <span class="vp-vc-lean-row-fill"
                                  style="width: <?= round(
                                      $leanPoints / $leanHeaviest * 100,
                                      2
                                  ) ?>%;"></span>
                        </span>
                        <span class="vp-vc-lean-row-points">
                            <?= h(((int)$leanRow['contribution'] > 0
                                ? '+'
                                : '') . (int)$leanRow['contribution']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <?php
            /*
             * The other key nothing read. `ruleErrors()` exists so that
             * a conflict rule the profile enables but the loader
             * cannot run reaches the reader as well as the admin — and
             * it reached neither, because no template asked for it. It
             * belongs in this band rather than anywhere else: a rule
             * that did not run is a reason this reading might have
             * been a different one.
             */
            ?>
            <div class="vp-vc-lean-errors">
                <?= h(__n(
                    'A conflict rule this profile enables did not run:',
                    'Conflict rules this profile enables did not run:',
                    count($errors)
                )) ?>
                <?php foreach ($errors as $error): ?>
                    <div class="vp-vc-lean-error">
                        <span class="font-monospace">
                            <?= h($error['id']) ?>
                        </span>
                        <?= h($error['note']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        </div>

    </div>
</div>
