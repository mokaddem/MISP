<?php
/**
 * What the box resolved to, when it did not land on a profile.
 *
 * Three answers, none of them an error page. A value nothing here
 * records is the answer to the question the reader asked
 * (`value-index.md` §7.1, V3), so it is stated plainly and the profile
 * — which says the same thing with the tabs to prove it — is offered
 * anyway. A hit never reaches this element: it is a `303` to the
 * profile, and the profile is the page that says everything else.
 *
 * The third is the refusal over the cap, which says the count rather
 * than truncating to it: a page that assessed the first hundred of
 * three hundred and forty would have dropped the two hundred and
 * forty the reader would never think to check.
 *
 * It carries its own padding because `triage()` renders it as the
 * whole response when a paste is refused, and a fragment that relies
 * on a wrapper the page draws arrives without one.
 *
 * **Nothing here distinguishes a value the reader may not see from a
 * value nobody recorded** (§4.2, §8 G3). There is one *absent* answer,
 * one wording, and it is drawn from `recorded === false` alone — the
 * probe behind it is viewer-scoped, so this element is never told
 * which of the two it is looking at and cannot leak what it does not
 * know.
 *
 * @var array $resolution `kind`, and what that kind carries
 */
App::uses('ValueUrlTool', 'Tools/ValueProfile');
App::uses('ValueInputTool', 'Tools/ValueProfile');

$kind = $resolution['kind'];
$profileUrl = function ($value) {
    return $this->Html->url(array(
        'controller' => 'values',
        'action' => 'view',
        ValueUrlTool::encode($value),
    ));
};
?>
<div class="vi-answerwrap">
<div class="vi-answer">
<?php if ($kind === 'absent'): ?>
    <p class="vi-said"><?= h($resolution['value']) ?></p>
<?php   $changed = $resolution['changed']; ?>
<?php   if (in_array(ValueInputTool::REFANGED, $changed, true)): ?>
    <p class="vi-note"><?= h(__(
        'Values are stored refanged, so that is what your paste'
        . ' resolves to. The box above still holds what you pasted.'
    )) ?></p>
<?php   elseif (in_array(ValueInputTool::UNQUOTED, $changed, true)): ?>
    <p class="vi-note"><?= h(__(
        'The quotes came off: they are not part of the value.'
    )) ?></p>
<?php   endif; ?>
    <p><?= h(__(
        'Nothing you can see records this value, so there is nothing'
        . ' to assess.'
    )) ?></p>
<?php   if ($resolution['suggestion'] === null): ?>
    <p class="vi-quiet"><?= h(__(
        'That is the answer to the question you asked. Nobody has'
        . ' filed it here.'
    )) ?></p>
    <div class="vi-offer">
        <a class="vi-btn" href="<?= h($profileUrl($resolution['value'])) ?>">
            <?= h(__('Open its profile anyway')) ?>
        </a>
        <span class="vi-mark"><?= h(__(
            'The profile will say the same thing, with the tabs to'
            . ' prove it.'
        )) ?></span>
    </div>
<?php   else: ?>
    <div class="vi-offer">
        <a class="vi-btn vi-btn--lead"
           href="<?= h($profileUrl($resolution['suggestion'])) ?>">
            <?= h(__('Did you mean')) ?>
            <b class="vi-said-inline"><?= h($resolution['suggestion']) ?></b>?
        </a>
        <a class="vi-btn vi-btn--quiet"
           href="<?= h($profileUrl($resolution['value'])) ?>">
            <?= h(sprintf(__('Open %s anyway'), $resolution['value'])) ?>
        </a>
    </div>
    <p class="vi-quiet"><?= h(sprintf(
        __('This instance stores %1$s and %2$s as two values, so the'
            . ' offer is an offer: only you can say which one the'
            . ' report meant.'),
        $resolution['value'],
        $resolution['suggestion']
    )) ?></p>
<?php   endif; ?>
<?php elseif ($kind === 'over'): ?>
    <p class="vi-refusal"><?= h(sprintf(
        __('That is %1$d values. This page takes %2$d at a time.'),
        $resolution['count'],
        $resolution['cap']
    )) ?></p>
    <p class="vi-quiet"><?= h(sprintf(
        __('Nothing was cut and nothing was read — your paste is'
            . ' still in the box exactly as you left it. Trim it to'
            . ' %1$d and press again, or work it in %2$d goes.'),
        $resolution['cap'],
        $resolution['goes']
    )) ?></p>
<?php else: ?>
    <p><?= h(__('There was nothing in the box to resolve.')) ?></p>
<?php endif; ?>
</div>
</div>
