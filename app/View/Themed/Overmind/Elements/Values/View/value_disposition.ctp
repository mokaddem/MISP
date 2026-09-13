<?php
/**
 * The disposition pill: a coloured dot, the word, and the score when
 * there is one.
 *
 * The markup form of a disposition. The colour itself comes from
 * `ValueDisposition`, which the tab bar and the Verdict tab read too —
 * they each need it in a different form, and three copies of the same
 * table is three chances for the page to contradict itself about what
 * a value is.
 *
 * @var string $disposition MALICIOUS | BENIGN | CONFLICTED | UNKNOWN
 * @var int|null $score     The quality. Null where nothing computed
 *                          one; **capped at 100 and not floored at 0**,
 *                          because the ledger sums to it exactly and a
 *                          record whose evidence disputes its own lean
 *                          nets negative. This element prints it; a
 *                          caller drawing a bar off it clamps its own
 *                          width (`value_verdict.ctp`)
 * @var string $size        'lg' for the headline, otherwise inline
 */
App::uses('ValueDisposition', 'Tools');

$score = $score ?? null;
$size = $size ?? '';

$colour = ValueDisposition::colour($disposition);

/*
 * A disposition that refuses to name a state is drawn quietly. CONFLICTED
 * and UNKNOWN are the absence of an answer, and a solid chip in the same
 * weight as MALICIOUS claims a certainty the value does not have — which
 * is what `isDefinite()` has always been for.
 */
$quiet = ValueDisposition::isDefinite($disposition) ? '' : ' vp-disposition-quiet';
?>
<span class="vp-disposition<?= $size === 'lg' ? ' vp-disposition-lg' : '' ?><?= $quiet ?>"
      style="--vp-disposition-color: <?= h($colour) ?>;">
    <span class="vp-disposition-dot"></span>
    <span class="vp-disposition-label"><?= h($disposition) ?></span>
    <?php if ($score !== null): ?>
        <span class="vp-disposition-score"><?= h($score) ?></span>
    <?php endif; ?>
</span>
