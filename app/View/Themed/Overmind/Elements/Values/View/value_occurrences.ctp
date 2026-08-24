<?php
/**
 * Every attribute row carrying this value, across every event the
 * viewing user can see.
 *
 * An `index_table` over `$valueProfile['occurrences']`, which is shaped
 * like a `fetchAttributes` result, so the field renderers below are the
 * same ones every other MISP index uses. No `sort` keys and no
 * `paginatorOptions`: this is the Overview summary, and the full,
 * filterable, paginated table is the Occurrences tab.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewOccurrences.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$profile = $valueProfile;
$rows = $profile['occurrences'];
$stats = $profile['occurrence_stats'];

$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

/*
 * Two things the page filters rows by, both stated on the <tr> because
 * `row_class_callable` is the only per-row hook `index_table` offers.
 *
 * Soft-deleted occurrences are part of the value's history but not of
 * its current state, so they start hidden until asked for. The type is
 * carried as a slug: a MISP type can hold characters a class name
 * cannot — `domain|ip`. The banner's type chips are keyed by the same
 * slug, and the two forms have to agree for a chip to select anything.
 */
$typeSlug = function ($type) {
    return preg_replace('/[^a-z0-9]+/', '-', strtolower($type));
};

$rowClass = function ($row) use ($typeSlug) {
    $classes = array('vp-occ-type-' . $typeSlug($row['Attribute']['type']));
    if (!empty($row['Attribute']['deleted'])) {
        $classes[] = 'vp-occ-deleted';
        $classes[] = 'd-none';
    }
    return implode(' ', $classes);
};

/*
 * Nine columns, not the full table's ten: `category` is dropped here
 * because it is the least discriminating of them — MISP's category
 * mostly follows from the type — and ten columns overflow a col-lg-9 far
 * enough to push the tags off the edge. The Occurrences tab carries the
 * complete field set.
 */
$fields = array(
    array(
        'element' => 'checkbox',
        'data_path' => 'Attribute.id',
    ),
    array(
        'name' => __('Event'),
        'element' => 'event',
        'data_path' => 'Event.id, Event.info',
        'url' => $baseurl . '/events/view2/%id%',
    ),
    array(
        'name' => __('Reported by'),
        'element' => 'organisation',
        'data_path' => 'Event.Orgc',
    ),
    array(
        'name' => __('Type'),
        'element' => 'type',
        'data_path' => 'Attribute.type',
    ),
    array(
        'name' => __('IDS'),
        'element' => 'ids',
        'data_path' => 'Attribute.to_ids',
        // This page reports the flag; the event that owns it sets it.
        'readonly' => true,
    ),
    array(
        'name' => __('Distribution'),
        'element' => 'distribution',
        'data_path' => 'Attribute.distribution',
        'sharing_group_path' => 'SharingGroup.name',
    ),
    array(
        'name' => __('Context'),
        'element' => 'value_object_context',
        'object_name_path' => 'Object.name',
        'object_id_path' => 'Object.id',
        'relation_path' => 'Attribute.object_relation',
        'comment_path' => 'Attribute.comment',
    ),
    array(
        'name' => __('Last seen'),
        'element' => 'datetime',
        'data_path' => 'Attribute.last_seen',
        'format' => 'Y-m-d H:i',
        'empty' => __('Not set'),
    ),
    array(
        'name' => __('Tags'),
        'element' => 'tag_list',
        'data_path' => 'AttributeTag',
    ),
);

$subtitle = implode(' &nbsp;·&nbsp; ', array(
    h(sprintf(
        __('Showing %1$s of %2$s occurrences'),
        $stats['shown'],
        $stats['total']
    )),
    h(sprintf(__('%s events'), $stats['events'])),
    h(sprintf(__('%s organisations'), $stats['orgs'])),
));

ob_start();
?>
    <?php if (!empty($stats['deleted'])): ?>
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch"
                   id="vp-occ-deleted-toggle">
            <label class="form-check-label small text-muted"
                   for="vp-occ-deleted-toggle">
                <?= h(sprintf(
                    __('Include %s soft-deleted'),
                    $stats['deleted']
                )) ?>
            </label>
        </div>
    <?php endif; ?>
    <?php if (!empty($rows)): ?>
        <a href="#tab-occurrences"
           class="btn btn-sm btn-outline-secondary d-flex gap-1
                  align-items-center"
           title="<?= __('The full, filterable occurrence table') ?>">
            <?= __('Open full table') ?>
            <i class="fas fa-arrow-right"></i>
        </a>
    <?php endif; ?>
<?php
$headerExtra = ob_get_clean();
?>
<div class="card shadow-sm mb-3 vp-panel" data-vp-occurrences
     style="--vp-panel-color: var(--attribute);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Occurrences'),
        'panelIcon' => 'misp-icon misp-icon-attribute misp-simple',
        'panelColor' => 'var(--attribute)',
        'panelSub' => $subtitle,
        'panelExtra' => $headerExtra,
    )) ?>

    <?php if (empty($rows)): ?>
        <?php
        /*
         * "No event you can see" rather than "no occurrences": for a
         * value-centric page the distinction between absent and hidden
         * is the whole point, and only one of them is knowable here.
         */
        ?>
        <div class="vp-empty">
            <span class="misp-icon misp-icon-attribute misp-simple"></span>
            <span><?= __('No event you can see carries this value.') ?></span>
        </div>
    <?php else: ?>
        <?php
        /*
         * What a type chip in the banner did, said in the panel it acted
         * on: a narrowed table with no note reads as a value with fewer
         * occurrences than the header claims.
         *
         * The denominator is the rows the filter chose from, not the
         * value's occurrence count — rows hidden by ACL or by the
         * soft-deleted toggle were never candidates.
         */
        ?>
        <div class="vp-filter-note d-none" data-vp-filter-note>
            <i class="fas fa-filter"></i>
            <span><?= sprintf(
                __('Type %1$s only &nbsp;·&nbsp; %2$s of %3$s rows'),
                '<span class="font-monospace fw-semibold"'
                    . ' data-vp-filter-type></span>',
                '<span data-vp-filter-shown></span>',
                '<span data-vp-filter-total></span>'
            ) ?></span>
            <button type="button" class="vp-filter-clear ms-auto"
                    data-vp-filter-clear>
                <?= __('Clear') ?>
            </button>
        </div>

        <div class="vp-empty d-none" data-vp-filter-empty>
            <span class="misp-icon misp-icon-attribute misp-simple"></span>
            <span><?= sprintf(
                __('No occurrence you can see has type %s.'),
                '<span class="font-monospace" data-vp-filter-type></span>'
            ) ?></span>
        </div>

        <div class="card-body p-0" data-vp-occ-table>
            <?= $this->element(
                'genericElementsBS5/IndexTable/index_table',
                array(
                    'scaffold_data' => array(
                        'data' => array(
                            'data' => $rows,
                            'fields' => $fields,
                            'primary_id_path' => 'Attribute.id',
                            'row_class_callable' => $rowClass,
                        ),
                    ),
                )
            ) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($rows)): ?>
        <div class="px-3 pb-3 pt-0">
            <?= $this->element(
                'genericElementsBS5/IndexTable/multi_select_toolbar',
                array(
                    'item_url' => '/values',
                    'filter_bar' => array(
                        'disabled' => $noWrites,
                        'export' => true,
                        'mass_edit' => true,
                        'mass_tag' => true,
                        'mass_local_tag' => true,
                        'mass_cluster' => true,
                        'mass_sighting' => true,
                    ),
                )
            ) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($profile['occurrence_acl_note'])): ?>
        <div class="vp-acl-note">
            <i class="fas fa-eye-slash"></i>
            <span><?= h($profile['occurrence_acl_note']) ?></span>
        </div>
    <?php endif; ?>

</div>
