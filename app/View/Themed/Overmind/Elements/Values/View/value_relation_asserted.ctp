<?php
/**
 * Section three: relationships somebody wrote down on purpose.
 *
 * The rule the whole tab lives or dies by is enforced here by form and
 * not by hue: **a machine relation is a table row, a human claim never
 * is**. Each claim is a `.vp-analyst` block — the same primitive the
 * Overview's analyst preview and the Analyst data tab use — so a claim
 * looks like a claim everywhere on the page, and cannot be mistaken for
 * a row even in greyscale.
 *
 * This section is never ranked and never truncated. Nobody generated
 * these; they were written one at a time, and the only thing that can
 * remove one from the list is a distribution the reader is outside of.
 * That is the inverse of section one's cap, and it is stated as such.
 *
 * Lazily loaded from ValuesController::viewRelationAsserted.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$profile = $valueProfile;
$asserted = $profile['relationships']['asserted'];
$claims = $asserted['claims'];
$view = $this;

$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

/**
 * @param string $text
 * @return string
 */
$slug = function ($text) {
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
};

/*
 * One glyph per target kind, so the reader can tell an event from a
 * galaxy cluster without reading the word — and the word is there too.
 */
$targetIcons = array(
    'Event' => 'misp-icon misp-icon-event misp-simple',
    'GalaxyCluster' => 'misp-icon misp-icon-galaxy misp-simple',
    'Object' => 'misp-icon misp-icon-object misp-simple',
    'Attribute' => 'misp-icon misp-icon-attribute misp-simple',
);

/*
 * The relationship types actually present, for the filter. Offering a
 * type nobody used would be a control that can only empty the list.
 */
$types = array();
foreach ($claims as $claim) {
    $types[$claim['relationship_type']] = true;
}
$types = array_keys($types);
sort($types);

ob_start();
?>
    <select class="form-select form-select-sm w-auto"
            data-vp-filter-key="rel"
            aria-label="<?= __('Relationship type') ?>">
        <option value=""><?= __('All relationship types') ?></option>
        <?php foreach ($types as $type): ?>
            <option value="<?= h($slug($type)) ?>"><?= h($type) ?></option>
        <?php endforeach; ?>
    </select>
<?php
$headerExtra = ob_get_clean();
if (empty($claims)) {
    $headerExtra = null;
}
?>
<div class="card shadow-sm mb-3 vp-panel vp-rel-k-human"
     style="--vp-panel-color: var(--vp-rel-human);"
     data-vp-list>

    <?php
    ob_start();
    ?>
        <span class="vp-rel-tag me-1">
            <span class="misp-icon misp-icon-analyst-note misp-simple"></span>
            <?= h(__('Asserted')) ?>
        </span>
        <?php if (empty($claims)): ?>
            <?= h(__('No claim')) ?>
        <?php else: ?>
            <?= h(sprintf(
                __('%1$s from %2$s'),
                __n('%d claim', '%d claims', $asserted['total'],
                    $asserted['total']),
                __n('%d organisation', '%d organisations',
                    $asserted['orgs'], $asserted['orgs'])
            )) ?>
        <?php endif; ?>
        &nbsp;·&nbsp;<?= h(__('analyst-data relationships')) ?>&nbsp;·&nbsp;
        <span class="vp-rel-prov vp-rel-prov-human">
            <i class="fas fa-user-pen"></i><?= h(__('Human claim')) ?>
        </span>
    <?php
    $headerSub = ob_get_clean();
    ?>

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Asserted by analysts'),
        'panelIcon' => 'misp-icon misp-icon-analyst-note misp-simple',
        'panelColor' => 'var(--vp-rel-human)',
        'panelSub' => $headerSub,
        'panelExtra' => $headerExtra,
    )) ?>

    <?php if (empty($claims)): ?>

        <div class="p-3">
            <div class="vp-empty">
                <span class="misp-icon misp-icon-analyst-note
                             misp-simple"></span>
                <span>
                    <?= __('No analyst has asserted a relationship for'
                        . ' this value.') ?>
                </span>
            </div>
        </div>

    <?php else: ?>

        <div class="vp-rel-cap vp-rel-cap-complete">
            <i class="fas fa-user-check"></i>
            <span>
                <?= sprintf(
                    __(
                        '%1$s Asserted relationships are written one at a'
                        . ' time by people, so this section is never'
                        . ' ranked and never truncated — the only cut is'
                        . ' ACL. It stays complete on a value whose'
                        . ' events the section above could not even'
                        . ' read.'
                    ),
                    '<strong>' . h(__n(
                        'The single claim is shown.',
                        'All %d claims are shown.',
                        $asserted['total'],
                        $asserted['total']
                    )) . '</strong>'
                ) ?>
            </span>
        </div>

        <div class="p-3 d-flex flex-column gap-2" data-vp-list-rows>
            <?php foreach ($claims as $claim): ?>
                <?php
                $target = $claim['target'];
                $icon = isset($targetIcons[$target['kind']])
                    ? $targetIcons[$target['kind']]
                    : 'fas fa-cube';
                $outbound = $claim['direction'] === 'outbound';
                ?>
                <div class="vp-analyst vp-rel-claim"
                     data-vp-list-row
                     data-vp-facet="rel:<?=
                         h($slug($claim['relationship_type'])) ?>">
                    <div class="vp-analyst-kind">
                        <i class="fas fa-arrow-right-long"></i>
                        <?= h($claim['relationship_type']) ?>
                    </div>
                    <div class="vp-analyst-body">
                        <div class="d-flex align-items-center gap-2
                                    flex-wrap">
                            <span class="vp-rel-dir"
                                  title="<?= h($outbound
                                      ? __('This value is the source of'
                                          . ' the claim')
                                      : __('Something else claims a'
                                          . ' relationship to this value')
                                  ) ?>">
                                <i class="fas fa-arrow-<?=
                                    $outbound ? 'right' : 'left' ?>"></i>
                                <?= h($outbound
                                    ? __('outbound')
                                    : __('inbound')) ?>
                            </span>
                            <span class="vp-rel-target">
                                <?php if (strpos($icon, 'misp-icon') === 0): ?>
                                    <span class="<?= h($icon) ?>"></span>
                                <?php else: ?>
                                    <i class="<?= h($icon) ?>"></i>
                                <?php endif; ?>
                                <span class="fw-semibold">
                                    <?= h($target['kind']) ?>
                                </span>
                                <span class="text-muted">
                                    <?= h($target['label']) ?>
                                </span>
                            </span>
                        </div>
                        <?php if ($claim['text'] !== ''): ?>
                            <div class="vp-analyst-text mt-1">
                                <?= h($claim['text']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="vp-analyst-meta d-flex align-items-center
                                    gap-2 flex-wrap">
                            <span><?= h($claim['org']) ?></span>
                            <span>·</span>
                            <span class="font-monospace">
                                <?= h($claim['date']) ?>
                            </span>
                            <?= $this->element(
                                'genericElementsBS5/Badges/distribution',
                                array(
                                    'distribution' =>
                                        (int)$claim['distribution'],
                                    'full' => false,
                                )
                            ) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="px-3 pb-3 d-none" data-vp-list-empty>
            <div class="vp-empty vp-empty-inline">
                <i class="fas fa-filter"></i>
                <span>
                    <?= __('Nobody has asserted a relationship of that'
                        . ' type for this value.') ?>
                </span>
            </div>
        </div>

        <div class="px-3 pb-3 d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-outline-primary
                                         disabled"
                    title="<?= h($noWrites) ?>">
                <i class="fas fa-plus me-1"></i>
                <?= __('Add a relationship') ?>
            </button>
            <span class="small text-muted vp-min-w-0">
                <?= h(sprintf(
                    __(
                        'Claims are stored against an occurrence, not'
                        . ' against the value — this list is the union'
                        . ' over the %d occurrences, in both directions,'
                        . ' de-duplicated by relationship UUID.'
                    ),
                    $asserted['occurrences']
                )) ?>
                <?php if (!empty($asserted['capped'])): ?>
                    <?php
                    /*
                     * The cap is on the *lookup*, not on the list. A
                     * claim written against an occurrence outside the
                     * most recent N would not be found, and a section
                     * whose whole argument is *never truncated* has to
                     * name the one place it can still miss something.
                     */
                    ?>
                    <?= h(sprintf(
                        __(
                            'Those are the %d most recent; a claim'
                            . ' written against an older occurrence of'
                            . ' this value is not looked up.'
                        ),
                        $asserted['occurrences']
                    )) ?>
                <?php endif; ?>
                <?php if (!empty($asserted['prose_absent'])): ?>
                    <?php
                    /*
                     * Said once here rather than as a placeholder on
                     * every block. `relationships` has no free-text
                     * column at all — `notes.note` and
                     * `opinions.comment` beside it do — so a claim's
                     * prose is not missing data, it is data MISP does
                     * not model.
                     */
                    ?>
                    <?= sprintf(
                        __(
                            'A relationship carries no text of its own:'
                            . ' %s has the type, the two ends, an author'
                            . ' and a distribution, and no prose column.'
                            . ' Anything written *about* one is a Note'
                            . ' attached to it, which this pass does not'
                            . ' fetch.'
                        ),
                        '<span class="font-monospace">relationships</span>'
                    ) ?>
                <?php endif; ?>
            </span>
        </div>

    <?php endif; ?>

    <?php
    /*
     * §14.6, applied here by phase 24. This carried a `.vp-acl-note`
     * counting the claims held at a distribution the reader is outside
     * of — *"their existence is counted; their text and their target
     * are not shown"*. Counting the existence is exactly the disclosure
     * §14.6 forbids, on a page whose URL takes any value the reader
     * types, so the band is gone and the list simply is what the reader
     * may see.
     */
    ?>

</div>
