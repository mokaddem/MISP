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
 * Soft-deleted occurrences are part of the value's history but not of its
 * current state, so they render struck through and hidden until asked
 * for. The class is put on the <tr> by the table itself.
 */
$rowClass = function ($row) {
    return empty($row['Attribute']['deleted'])
        ? ''
        : 'vp-occ-deleted d-none';
};

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
        'name' => __('Category'),
        'element' => 'category',
        'data_path' => 'Attribute.category',
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
    <a href="#tab-occurrences"
       class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
       title="<?= __('The full, filterable occurrence table') ?>">
        <?= __('Open full table') ?>
        <i class="fas fa-arrow-right"></i>
    </a>
<?php
$headerExtra = ob_get_clean();
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--attribute);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Occurrences'),
        'panelIcon' => 'misp-icon misp-icon-attribute misp-simple',
        'panelColor' => 'var(--attribute)',
        'panelSub' => $subtitle,
        'panelExtra' => $headerExtra,
    )) ?>

    <div class="card-body p-0">
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

<?php if (!empty($stats['deleted'])): ?>
<script>
(function () {
    var toggle = document.getElementById('vp-occ-deleted-toggle');
    if (!toggle) return;
    // Scoped to this panel's own card: the toggle speaks for one table.
    var scope = toggle.closest('.vp-panel') || document;
    toggle.addEventListener('change', function () {
        scope.querySelectorAll('tr.vp-occ-deleted').forEach(function (tr) {
            tr.classList.toggle('d-none', !toggle.checked);
        });
    });
}());
</script>
<?php endif; ?>
