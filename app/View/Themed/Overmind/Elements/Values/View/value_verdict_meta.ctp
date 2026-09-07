<?php
/**
 * The provenance band under a verdict hero: where the number came from,
 * how long it lasts, what it did not get to see, and — where a rule
 * rather than a threshold decided the state — which rule fired.
 *
 * One quiet line rather than a row of chips. It is the small print of
 * the card above it and should read that way.
 *
 * Shared by both verdict layouts, because the provenance does not
 * depend on which way the evidence fell.
 *
 * **It says nothing about permissions, and that is deliberate.** MISP
 * shows a reader what they are allowed to see; that is how the platform
 * works and the people using it know it. A line volunteering that
 * something might be missing tells a reader nothing they did not
 * already assume, and on a page that accepts any value typed into the
 * URL it hints at the presence of records the reader has no business
 * knowing about.
 *
 * @var array $verdict
 * @var string $metaRule Optional — the rule that produced the state,
 *                       shown in place of the storage note
 */
$metaRule = $metaRule ?? null;

/*
 * Literally true: there is no stored verdict, so the timestamp is this
 * render. The honest form of the artboard's fixed clock.
 */
$computedAt = $verdict['computed_at'] ?? date('Y-m-d H:i:s');

$parts = array(
    h(__('Computed at render,')) . ' <span class="font-monospace">'
        . h($computedAt) . '</span>',
);

/*
 * A verdict reached from no signal at all was not weighted by anything,
 * so naming the profile that would have weighted it claims a
 * computation that did not happen.
 */
if (!empty($verdict['ledger'])) {
    $parts[] = h(__('Weighting profile')) . ' <span class="font-monospace'
        . ' vp-meta-strong">' . h($verdict['profile']) . '</span>';
}

$parts[] = $metaRule === null
    ? h(__('Not stored, not synchronised'))
    : h(__('Conflict rule:')) . ' <em>' . h($metaRule) . '</em>';
?>
<div class="vp-verdict-meta">
    <?php foreach ($parts as $i => $part): ?>
        <?php if ($i > 0): ?>
            <span class="vp-meta-sep">|</span>
        <?php endif; ?>
        <span><?= $part ?></span>
    <?php endforeach; ?>
</div>
