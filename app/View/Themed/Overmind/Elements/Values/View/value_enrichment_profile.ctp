<?php
/**
 * What the reader's Analyst Profile said about this tab, and every
 * place the instance disagreed with it.
 *
 * **Phase 7 of prd/analyst-profile/, and it runs nothing.** The
 * profile names modules per attribute type; this strip says which of
 * them the rail arrived with ticked, which the reader refused outright,
 * and which could not be honoured at all. The press is still theirs — a
 * declaration is not a trigger, because nothing in MISP records that a
 * module ran and *"run the defaults on page open"* therefore means
 * *"run them on every page open"* (`08-enrichment.md` §1.1).
 *
 * **Rendered above the branch, so it survives every empty state.** A
 * profile naming a module the instance has turned off is exactly the
 * case where the tab has no rail to hang the condition on: the reader
 * would otherwise be told *"no enabled module accepts this value's
 * types"* while their own profile names one, which is the quiet lie
 * `01-profile.md` §1.3 forbids.
 *
 * Silent when the profile declares nothing, which is the shipped
 * default: the tab then renders exactly as phase 28 shipped it.
 *
 * A plain partial, not an endpoint.
 *
 * @var array $enrichment
 */
$declaration = isset($enrichment['profile'])
    ? $enrichment['profile']
    : array();
if (empty($declaration['in_force'])) {
    return;
}
$selected = count($declaration['selected']);
$applicable = (int)$declaration['applicable'];
$declared = (int)$declaration['declared'];

/*
 * What the posture label used to occupy, doing the job the posture was
 * reached for: telling the reader, before they press anything, how much
 * of what arrived ticked would leave the building. A fact about this
 * selection, not a setting — the number is `leavingCount()`'s, so it
 * cannot drift from the chips on the rail.
 */
$leaving = isset($declaration['leaving'])
    ? (int)$declaration['leaving']
    : 0;

if ($applicable === 0) {
    $headline = sprintf(
        __n(
            'Your profile names %d module, and none of them for what'
            . ' this value is.',
            'Your profile names %d modules, and none of them for what'
            . ' this value is.',
            $declared
        ),
        $declared
    );
} elseif ($selected === 0) {
    $headline = sprintf(
        __n(
            'None of the %d module your profile names for this'
            . ' value\'s types could be selected.',
            'None of the %d modules your profile names for this'
            . ' value\'s types could be selected.',
            $applicable
        ),
        $applicable
    );
} elseif ($selected === $applicable) {
    $headline = sprintf(
        __n(
            'Selected the %d module your profile names for this'
            . ' value\'s types.',
            'Selected all %d modules your profile names for this'
            . ' value\'s types.',
            $applicable
        ),
        $applicable
    );
} else {
    $headline = sprintf(
        __('Selected %1$d of the %2$d modules your profile names for'
            . ' this value\'s types.'),
        $selected,
        $applicable
    );
}

$icons = array(
    'service.unreachable' => 'fa-plug-circle-xmark',
    'module.not_offered' => 'fa-circle-question',
    'module.disabled' => 'fa-toggle-off',
    'module.restricted' => 'fa-building-lock',
    'module.type_mismatch' => 'fa-shuffle',
    'module.unresolved' => 'fa-circle-question',
    'type.unused' => 'fa-filter-circle-xmark',
    'state.never' => 'fa-ban',
    /*
     * `state.auto_inert` is gone with phase 11: *nothing runs on its
     * own on this version* stopped being true. What replaced it is
     * two conditions, kept apart because only one of them is a
     * conversation the reader can have — an instance with the gate
     * shut may open it, where a gate set to site administrators only
     * is a decision that has already been made about them.
     */
    'state.auto_disabled' => 'fa-hand-pointer',
    'state.auto_site_admin' => 'fa-user-shield',
);
?>
<div class="vp-e-profile">

    <div class="vp-e-profile-head">
        <i class="fas fa-user-gear" aria-hidden="true"></i>
        <span class="vp-e-profile-name">
            <?php if (!empty($declaration['name'])): ?>
                <?= h(sprintf(
                    __('Profile: %s'),
                    $declaration['name']
                )) ?>
            <?php else: ?>
                <?= h(__('Your Analyst Profile')) ?>
            <?php endif; ?>
        </span>
        <?php if ($leaving > 0): ?>
            <span class="vp-e-profile-leaving"><?= h(sprintf(
                __n(
                    '%d of these would leave the instance',
                    '%d of these would leave the instance',
                    $leaving
                ),
                $leaving
            )) ?></span>
        <?php endif; ?>
    </div>

    <div class="vp-e-profile-line"><?= h($headline) ?></div>

    <?php
    /*
     * The sentence that keeps a declaration from reading as a
     * schedule. It is the tab's standing promise, restated here
     * because this strip is the one part of the page that could be
     * mistaken for having done something.
     */
    ?>
    <div class="vp-e-profile-note">
        <?= h(__(
            'Nothing has been run. Your profile ticks the boxes and'
            . ' nothing else; sending anything anywhere still takes a'
            . ' press.'
        )) ?>
    </div>

    <?php if (!empty($declaration['conditions'])): ?>
        <ul class="vp-e-profile-conds">
            <?php foreach ($declaration['conditions'] as $condition): ?>
                <?php
                $icon = isset($icons[$condition['id']])
                    ? $icons[$condition['id']]
                    : 'fa-circle-info';
                ?>
                <li>
                    <i class="fas <?= h($icon) ?>" aria-hidden="true"></i>
                    <span>
                        <?php if (!empty($condition['module'])): ?>
                            <span class="font-monospace fw-semibold"><?=
                                h($condition['module'])
                            ?></span>
                            &mdash;
                        <?php endif; ?>
                        <?= h($condition['note']) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</div>
