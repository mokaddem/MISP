<?php
/**
 * The provenance band under a verdict hero: where the number came from,
 * how long it lasts, what it did not get to see, and — where a rule
 * rather than a threshold decided the state — which rule fired.
 *
 * One quiet line rather than a row of chips. It is the small print of
 * the card above it and should read that way.
 *
 * Shared by both verdict layouts, because the caveats do not depend on
 * which way the evidence fell. A verdict that stated a disposition
 * without saying it was computed from the viewing user's own
 * visibility would be claiming more than it knows.
 *
 * @var array $verdict
 * @var string $metaRule Optional — the rule that produced the state,
 *                       shown in place of the storage note
 */
$metaRule = $metaRule ?? null;
$aclNote = $verdict['acl_note'] ?? null;

/*
 * Literally true: there is no stored verdict, so the timestamp is this
 * render. The honest form of the artboard's fixed clock.
 */
$computedAt = $verdict['computed_at'] ?? date('Y-m-d H:i:s');

$parts = array(
    h(__('Computed at render,')) . ' <span class="font-monospace">'
        . h($computedAt) . '</span>',
    h(__('Weighting profile')) . ' <span class="font-monospace'
        . ' vp-meta-strong">' . h($verdict['profile']) . '</span>',
);

if (!empty($aclNote)) {
    $parts[] = '<i class="fas fa-eye-slash me-1"></i>' . h($aclNote);
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
