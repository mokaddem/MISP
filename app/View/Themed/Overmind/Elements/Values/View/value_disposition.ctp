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
 * @var int|null $score     0-100, omitted when nothing computed one
 * @var string $size        'lg' for the headline, otherwise inline
 */
App::uses('ValueDisposition', 'Tools');

$score = $score ?? null;
$size = $size ?? '';

$colour = ValueDisposition::colour($disposition);
?>
<span class="vp-disposition<?= $size === 'lg' ? ' vp-disposition-lg' : '' ?>"
      style="--vp-disposition-color: <?= h($colour) ?>;">
    <span class="vp-disposition-dot"></span>
    <span class="vp-disposition-label"><?= h($disposition) ?></span>
    <?php if ($score !== null): ?>
        <span class="vp-disposition-score"><?= h($score) ?></span>
    <?php endif; ?>
</span>
