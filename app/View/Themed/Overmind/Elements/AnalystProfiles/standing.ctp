<?php
/**
 * Why a profile is, or is not, the one weighting this reader's pages.
 *
 * A badge on one row cannot say *why* the fork you are editing is not
 * the one scoring you. A per-row standing can, and the model computes
 * it rather than the page inferring it.
 *
 * @var array $profile A row from the index board
 */
$standing = $profile['standing'];
$state = $standing['state'];
$tone = array(
    'in_force' => 't-force',
    'disabled' => 't-off',
    'overridden' => 't-over',
    'not_selected' => 't-plain',
    'other_owner' => 't-plain',
    'unresolved' => 't-missing',
);
$label = array(
    'in_force' => __('in force'),
    'disabled' => __('disabled'),
    'overridden' => __('overridden'),
    'not_selected' => __('not chosen'),
    'other_owner' => __('another owner'),
    'unresolved' => __('unresolved'),
);
/*
 * Which scopes point at this row (D45). A shipped profile that nobody
 * has chosen is switched on and doing nothing, and *not chosen* is a
 * different sentence from *overridden* — one of them names something a
 * reader can do about it.
 */
$selectedBy = isset($profile['selected_by'])
    ? $profile['selected_by']
    : array();
?>
<span class="pill <?= h(isset($tone[$state]) ? $tone[$state] : 't-plain') ?>">
    <?= h(isset($label[$state]) ? $label[$state] : $state) ?>
</span>
<div class="wb-sub mt-1">
    <?php if ($state === 'in_force'): ?>
        <?= h(__('Every value page you open is scored by this one.')) ?>
        <?php if (in_array('user', $selectedBy, true)): ?>
            <?= h(__('You chose it; you did not fork it, so it stays'
                . ' corrected as MISP corrects it.')) ?>
        <?php elseif (in_array('org', $selectedBy, true)): ?>
            <?= h(__('Your organisation chose it. Choosing one of your'
                . ' own, or forking, overrides that for you alone.')) ?>
        <?php elseif (in_array('instance', $selectedBy, true)): ?>
            <?= h(__('The instance runs this one, for everybody whose'
                . ' organisation and account have chosen none.')) ?>
        <?php endif; ?>
    <?php elseif ($state === 'not_selected'): ?>
        <?= h(__('One of the profiles MISP ships, switched on and chosen'
            . ' by nobody. Choose it and it scores your pages — no fork,'
            . ' so corrections to it reach you.')) ?>
    <?php elseif ($state === 'disabled' && $profile['editable']): ?>
        <?= h(__('Yours, and weighting nothing. You may hold one enabled'
            . ' profile, so enabling this one disables the other — the'
            . ' confirm says which, and nothing is deleted.')) ?>
    <?php elseif ($state === 'disabled'): ?>
        <?= h(__('Enabled by nobody, so it weighs nothing.')) ?>
    <?php elseif ($state === 'overridden'): ?>
        <?= h(__('Enabled, and beaten for you by')) ?>
        <?php if (!empty($standing['winner'])): ?>
            <a class="fw-semibold"
               href="<?= h($this->Html->url(array(
                   'action' => 'view', $standing['winner']['id']))) ?>">
                <?= h($standing['winner']['name']) ?></a>.
        <?php else: ?>
            <?= h(__('a nearer profile.')) ?>
        <?php endif; ?>
        <?php if (in_array('instance', $selectedBy, true)): ?>
            <?= h(__('It is still what a colleague who has chosen nothing'
                . ' is scored by.')) ?>
        <?php elseif (in_array('org', $selectedBy, true)): ?>
            <?= h(__('It is still what a colleague in your organisation'
                . ' who has chosen nothing is scored by.')) ?>
        <?php endif; ?>
    <?php elseif ($state === 'other_owner'): ?>
        <?= h(__('Somebody else\'s. You can see it because you administer'
            . ' this instance; it could never apply to you.')) ?>
    <?php else: ?>
        <?= h(__('Enabled, applies to you, and yet nothing is in force —'
            . ' which resolution cannot produce. Reported rather than'
            . ' explained away.')) ?>
    <?php endif; ?>
</div>
