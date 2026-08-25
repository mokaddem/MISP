<?php
/**
 * The Occurrences tab: every attribute row carrying this value, with the
 * counted rail that narrows it.
 *
 * The Overview already previews this table. What this one adds is
 * filtering, selection and the three columns the preview drops — so the
 * rail is the first thing on screen, and the panel lays out its own row
 * rather than taking `view_layout`'s split: the counts and the rows they
 * count come out of one fetch and cannot be allowed to disagree.
 *
 * An `index_table` over `$valueProfile['occurrences']`, shaped like a
 * `fetchAttributes` result, so the field renderers are the ones every
 * other MISP index uses.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewOccurrenceTable.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$profile = $valueProfile;
$rows = $profile['occurrences'];
$stats = $profile['occurrence_stats'];
$facets = $profile['occurrence_facets'];

$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

/*
 * Rows are drawn from one page of the panel's own rows, never fetched
 * again: `00-shared.md` §6. Ten is the point past which tallying facet
 * counts in PHP over the fetched set stops being honest, so it is also
 * the point past which this control has to start telling the truth
 * about which regime the reader is in.
 */
$pageSize = 10;

$view = $this;

/**
 * Tokens are matched by the rail, so they are derived from the row's own
 * domain values by the same rule the fixture used to write the facet
 * `value` down. A MISP type can hold characters an attribute value
 * should not — `domain|ip` — so everything is slugged.
 *
 * @param string $text
 * @return string
 */
$slug = function ($text) {
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
};

/**
 * @param array $row
 * @return string Space-separated `key:value` facet tokens
 */
$tokens = function ($row) use ($slug) {
    $attribute = $row['Attribute'];
    $tokens = array(
        'organisation:' . $row['Event']['Orgc']['id'],
        'type:' . $slug($attribute['type']),
        'category:' . $slug($attribute['category']),
        'ids:' . (!empty($attribute['to_ids']) ? 'set' : 'unset'),
        'distribution:' . (int)$attribute['distribution'],
    );
    if ((int)$attribute['distribution'] === 4
        && !empty($row['SharingGroup']['id'])
    ) {
        $tokens[] = 'sharing_group:' . $row['SharingGroup']['id'];
    }
    foreach ($row['AttributeTag'] as $attributeTag) {
        // Galaxy tags are not drawn in the Tags column either, and a
        // filter on something invisible is not a filter.
        if (!empty($attributeTag['Tag']['is_galaxy'])) {
            continue;
        }
        $tokens[] = 'tag:' . $slug($attributeTag['Tag']['name']);
    }
    if (!empty($row['proposal_count'])) {
        $tokens[] = 'state:proposal';
    }
    return implode(' ', $tokens);
};

/*
 * What the rail matches on, and what the bulk bar's scope line counts.
 * `row_class_callable` could not carry this: a row matched on type and
 * organisation and tag at once cannot say so as a class string without
 * the reader parsing class names back into fields.
 */
$rowData = function ($row) use ($tokens) {
    $data = array(
        'vp-facet' => $tokens($row),
        'vp-event' => $row['Event']['id'],
        'vp-org' => $row['Event']['Orgc']['name'],
    );
    if (!empty($row['Attribute']['deleted'])) {
        // A reveal, not a facet value: filtering *to* deleted rows and
        // including them alongside the rest are different questions.
        $data['vp-hidden'] = 'deleted';
    }
    return $data;
};

$rowClass = function ($row) {
    return empty($row['Attribute']['deleted']) ? '' : 'vp-occ-deleted';
};

/**
 * The row's exception column, and empty for an ordinary row. It carries
 * the two states that change what a row *means* rather than what it
 * says: a pending shadow attribute proposing a change to it, and a
 * soft-delete that makes it history rather than current state.
 *
 * @param array $row
 * @return string
 */
$stateCell = function ($row) {
    $badges = array();
    if (!empty($row['proposal_count'])) {
        $badges[] = '<span class="badge d-inline-flex align-items-center'
            . ' gap-1 bg-warning-subtle text-warning-emphasis'
            . ' border border-warning-subtle" title="'
            . h(__(
                'A pending shadow attribute proposes a change to this'
                . ' occurrence'
            )) . '">'
            . '<i class="fas fa-code-pull-request"></i>'
            . h($row['proposal_count']) . '</span>';
    }
    if (!empty($row['Attribute']['deleted'])) {
        $badges[] = '<span class="badge d-inline-flex align-items-center'
            . ' gap-1 bg-secondary-subtle text-secondary-emphasis'
            . ' border border-secondary-subtle" title="'
            . h(__('Soft-deleted — history, not current state')) . '">'
            . '<i class="fas fa-trash"></i>' . h(__('Del')) . '</span>';
    }
    if (empty($badges)) {
        return '<span class="text-muted">&mdash;</span>';
    }
    return implode(' ', $badges);
};

/**
 * A one-letter disc before the organisation name, so four organisations
 * are distinguishable down the column without reading them.
 *
 * It stands in for a logo rather than joining one: MISP's organisation
 * renderer already draws a logo where the organisation has uploaded
 * one, and two glyphs before a name is one more than the column needs.
 * The hue is derived from the name rather than taken from the theme's
 * palette — there is no fixed set of organisations to assign variables
 * to, and the same organisation has to land on the same colour on every
 * page that draws it.
 *
 * @param array $row
 * @return string
 */
$organisationCell = function ($row) use ($view) {
    $org = $row['Event']['Orgc'];
    $rendered = $view->element(
        'genericElementsBS5/IndexTable/Fields/organisation',
        array(
            'field' => array('data_path' => 'Event.Orgc'),
            'row' => $row,
            'data_path' => 'Event.Orgc',
            'viewMode' => 'table',
        )
    );
    if (strpos($rendered, '<img') !== false) {
        return $rendered;
    }
    $initial = mb_strtoupper(mb_substr($org['name'], 0, 1));
    return '<div class="d-inline-flex align-items-center gap-2">'
        . '<span class="vp-occ-orgdot" style="--vp-orgdot-hue: '
        . h(crc32($org['name']) % 360) . '" aria-hidden="true">'
        . h($initial) . '</span>'
        . $rendered
        . '</div>';
};

/*
 * ------------------------------------------------------------------
 * Twelve columns, nine of them shown
 * ------------------------------------------------------------------
 * Stated once, so the Columns menu, the header's ratio and the table
 * itself cannot come to disagree about which columns exist. `shown` is
 * only the default: the menu reveals the rest in place.
 *
 * Category, Comment and First seen are the three folded away. Category
 * mostly follows from the type; Comment is already summarised under the
 * Context column; and of the two seen dates only one can be read at a
 * glance in a column this narrow.
 */
$columns = array(
    array(
        'key' => 'state',
        'label' => __('State'),
        'shown' => true,
        'field' => array(
            'name' => __('State'),
            'element' => 'custom',
            'function' => $stateCell,
            'class' => 'text-nowrap',
        ),
    ),
    array(
        'key' => 'event',
        'label' => __('Event'),
        'shown' => true,
        'field' => array(
            'name' => __('Event'),
            'element' => 'event',
            'data_path' => 'Event.id, Event.info',
            'url' => $baseurl . '/events/view2/%id%',
        ),
    ),
    array(
        'key' => 'org',
        'label' => __('Reported by'),
        'shown' => true,
        'field' => array(
            'name' => __('Reported by'),
            'element' => 'custom',
            'function' => $organisationCell,
            // The shared column rule the organisation renderer expects,
            // named here because the cell arrives through `custom`.
            'class' => 'idx-col-organisation',
        ),
    ),
    array(
        'key' => 'type',
        'label' => __('Type'),
        'shown' => true,
        'field' => array(
            'name' => __('Type'),
            'element' => 'type',
            'data_path' => 'Attribute.type',
        ),
    ),
    array(
        'key' => 'category',
        'label' => __('Category'),
        'shown' => false,
        'field' => array(
            'name' => __('Category'),
            'element' => 'category',
            'data_path' => 'Attribute.category',
        ),
    ),
    array(
        'key' => 'ids',
        'label' => __('IDS'),
        'shown' => true,
        'field' => array(
            'name' => __('IDS'),
            'element' => 'ids',
            'data_path' => 'Attribute.to_ids',
            // This page reports the flag; the event that owns it sets it.
            'readonly' => true,
        ),
    ),
    array(
        'key' => 'distribution',
        'label' => __('Distribution'),
        'shown' => true,
        'field' => array(
            'name' => __('Distribution'),
            'element' => 'distribution',
            'data_path' => 'Attribute.distribution',
            'sharing_group_path' => 'SharingGroup.name',
        ),
    ),
    array(
        'key' => 'context',
        'label' => __('Context'),
        'shown' => true,
        'field' => array(
            'name' => __('Context'),
            'element' => 'value_object_context',
            'object_name_path' => 'Object.name',
            'object_id_path' => 'Object.id',
            'relation_path' => 'Attribute.object_relation',
            'comment_path' => 'Attribute.comment',
        ),
    ),
    array(
        'key' => 'comment',
        'label' => __('Comment'),
        'shown' => false,
        'field' => array(
            'name' => __('Comment'),
            'data_path' => 'Attribute.comment',
            'empty' => '—',
        ),
    ),
    array(
        'key' => 'first-seen',
        'label' => __('First seen'),
        'shown' => false,
        'field' => array(
            'name' => __('First seen'),
            'element' => 'datetime',
            'data_path' => 'Attribute.first_seen',
            'format' => 'Y-m-d H:i',
            // The words, never a dash: "unknown" and "no value" are
            // different claims about an optional column.
            'empty' => __('Not set'),
        ),
    ),
    array(
        'key' => 'last-seen',
        'label' => __('Last seen'),
        'shown' => true,
        'field' => array(
            'name' => __('Last seen'),
            'element' => 'datetime',
            'data_path' => 'Attribute.last_seen',
            'format' => 'Y-m-d H:i',
            'empty' => __('Not set'),
        ),
    ),
    array(
        'key' => 'tags',
        'label' => __('Tags'),
        'shown' => true,
        'field' => array(
            'name' => __('Tags'),
            'element' => 'tag_list',
            'data_path' => 'AttributeTag',
        ),
    ),
);

$fields = array(
    array(
        'element' => 'checkbox',
        'data_path' => 'Attribute.id',
    ),
);
$shownColumns = 0;
foreach ($columns as $column) {
    $field = $column['field'];
    $classes = array('vp-occ-col-' . $column['key']);
    if (!$column['shown']) {
        $classes[] = 'd-none';
    } else {
        $shownColumns++;
    }
    if (!empty($field['class'])) {
        $classes[] = $field['class'];
    }
    // The same classes on the heading and on the cells, so hiding a
    // column takes its heading with it.
    $field['class'] = implode(' ', $classes);
    $field['header_class'] = implode(' ', $classes);
    $fields[] = $field;
}

$subtitle = implode(' &nbsp;·&nbsp; ', array(
    sprintf(
        __('Showing %1$s of %2$s occurrences'),
        '<span data-vp-list-shown>' . h($stats['shown']) . '</span>',
        h($stats['total'])
    ),
    h(sprintf(__('%s events'), $stats['events'])),
    h(sprintf(__('%s organisations'), $stats['orgs'])),
));

ob_start();
?>
    <div class="dropdown">
        <?php
        /*
         * `auto-close: outside` so ticking a column does not shut the
         * menu the reader is working through.
         */
        ?>
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                type="button" data-bs-toggle="dropdown"
                data-bs-auto-close="outside" aria-expanded="false">
            <i class="fas fa-table-columns me-1"></i>
            <?= sprintf(
                __('Columns (%1$s of %2$s)'),
                '<span data-vp-col-shown>' . h($shownColumns) . '</span>',
                h(count($columns))
            ) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <?php foreach ($columns as $column): ?>
                <li>
                    <label class="dropdown-item d-flex align-items-center
                                  gap-2 mb-0">
                        <input type="checkbox" class="form-check-input mt-0"
                               data-vp-col="vp-occ-col-<?= h($column['key']) ?>"
                               <?= $column['shown'] ? 'checked' : '' ?>>
                        <?= h($column['label']) ?>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?= $this->element('Values/View/value_pager', array(
        'size' => $pageSize,
        'shown' => count($rows),
        'total' => $stats['total'],
    )) ?>
<?php
$headerExtra = ob_get_clean();
?>
<div class="row"<?= empty($rows) ? '' : ' data-vp-list' ?>>

    <?php if (!empty($facets)): ?>
        <div class="col-lg-3">
            <?= $this->element('Values/View/value_occurrence_facets', array(
                'facets' => $facets,
            )) ?>
        </div>
    <?php endif; ?>

    <div class="<?= empty($facets) ? 'col-12' : 'col-lg-9' ?>">
        <div class="card shadow-sm mb-3 vp-panel"
             style="--vp-panel-color: var(--attribute);">

            <?= $this->element('Values/View/value_panel_header', array(
                'panelTitle' => __('Occurrences'),
                'panelIcon' => 'misp-icon misp-icon-attribute misp-simple',
                'panelColor' => 'var(--attribute)',
                'panelSub' => empty($rows) ? null : $subtitle,
                'panelExtra' => empty($rows) ? null : $headerExtra,
            )) ?>

            <?php if (empty($rows)): ?>
                <?php
                /*
                 * One empty state rather than an empty rail beside an
                 * empty table — and "no event you can see" rather than
                 * "no occurrences", because for a value-centric page the
                 * difference between absent and hidden is the point and
                 * only one of the two is knowable here.
                 */
                ?>
                <div class="vp-empty">
                    <span class="misp-icon misp-icon-attribute misp-simple">
                    </span>
                    <span><?= __(
                        'This value has no occurrences on this instance.'
                    ) ?></span>
                </div>
            <?php else: ?>

                <?php
                /*
                 * Top-docked: the reader is looking at rows, and a bar
                 * that appears under them shoves the table down at the
                 * moment they are reading it.
                 *
                 * The whole bar is disabled rather than half of it. One
                 * selection can mix rows the user may edit with rows
                 * they may only propose against, and no endpoint or
                 * confirmation dialogue expresses that today.
                 */
                ?>
                <div class="px-3 pt-2 pb-0" data-vp-bulk
                     data-vp-scope-template="<?= h(__(
                         '%1$s rows · %2$s events · %3$s organisations'
                     )) ?>">
                    <?= $this->element(
                        'genericElementsBS5/IndexTable/multi_select_toolbar',
                        array(
                            'item_url' => '/values',
                            'filter_bar' => array(
                                'disabled' => $noWrites,
                                'scope_note' => __('No rows selected'),
                                'export' => true,
                                'mass_edit' => true,
                                'mass_tag' => true,
                                'mass_local_tag' => true,
                                'mass_cluster' => true,
                                'mass_sighting' => true,
                                'custom_actions' => array(
                                    array(
                                        'id' => 'vp-occ-mass-ids',
                                        'label' => __('Set to_ids'),
                                        'icon' => 'shield-halved',
                                        'class' => 'btn-outline-warning',
                                        'onclick' => '',
                                    ),
                                    array(
                                        'id' => 'vp-occ-mass-distribution',
                                        'label' => __('Set distribution'),
                                        'icon' => 'globe',
                                        'class' => 'btn-outline-secondary',
                                        'onclick' => '',
                                    ),
                                    array(
                                        'id' => 'vp-occ-mass-propose',
                                        'label' => __('Propose edit'),
                                        'icon' => 'code-pull-request',
                                        'class' => 'btn-outline-warning',
                                        'onclick' => '',
                                    ),
                                    array(
                                        'id' => 'vp-occ-mass-collection',
                                        'label' => __('Add to collection'),
                                        'icon' => 'folder-plus',
                                        'class' => 'btn-outline-dark',
                                        'onclick' => '',
                                    ),
                                ),
                            ),
                        )
                    ) ?>
                </div>

                <div class="card-body p-0" data-vp-list-rows>
                    <?= $this->element(
                        'genericElementsBS5/IndexTable/index_table',
                        array(
                            'scaffold_data' => array(
                                'data' => array(
                                    'data' => $rows,
                                    'fields' => $fields,
                                    'primary_id_path' => 'Attribute.id',
                                    'row_class_callable' => $rowClass,
                                    'row_data_callable' => $rowData,
                                ),
                            ),
                        )
                    ) ?>
                </div>

                <?php
                /*
                 * Only a filter can produce this. A value with no
                 * occurrences keeps the empty state above: "no rows
                 * match your filter" over it would be a different and
                 * false claim.
                 */
                ?>
                <div class="vp-empty d-none" data-vp-list-empty>
                    <i class="fas fa-filter"></i>
                    <span><?= __(
                        'No occurrence you can see matches these filters.'
                    ) ?></span>
                </div>

            <?php endif; ?>

            <?php if (!empty($profile['occurrence_acl_note'])): ?>
                <?php
                /*
                 * The count that exists and is not shown. A different
                 * sentence from the empty state above, and the two must
                 * not merge: one says nothing is here, the other says
                 * something is and you cannot have it.
                 */
                ?>
                <div class="vp-acl-note">
                    <i class="fas fa-eye-slash"></i>
                    <span><?= h($profile['occurrence_acl_note']) ?></span>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
