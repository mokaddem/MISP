<?php
/**
 * The disposition pill: a coloured dot, the word, and the score when
 * there is one.
 *
 * The single place that maps a disposition to a colour, so the verdict
 * card and the Verdict tab cannot drift apart. The tab bar in
 * `Values/view.ctp` keeps its own lookup because it needs a raw colour
 * for a CSS variable rather than markup.
 *
 * @var string $disposition MALICIOUS | CONFLICTED | UNKNOWN
 * @var int|null $score     0-100, omitted when nothing computed one
 * @var string $size        'lg' for the headline, otherwise inline
 */
$score = $score ?? null;
$size = $size ?? '';

$colours = array(
    'MALICIOUS' => 'var(--bs-danger)',
    'CONFLICTED' => 'var(--bs-warning)',
    'UNKNOWN' => 'var(--bs-secondary-color)',
);
$colour = $colours[$disposition] ?? 'var(--bs-secondary-color)';
?>
<span class="vp-disposition<?= $size === 'lg' ? ' vp-disposition-lg' : '' ?>"
      style="--vp-disposition-color: <?= h($colour) ?>;">
    <span class="vp-disposition-dot"></span>
    <span class="vp-disposition-label"><?= h($disposition) ?></span>
    <?php if ($score !== null): ?>
        <span class="vp-disposition-score"><?= h($score) ?></span>
    <?php endif; ?>
</span>
