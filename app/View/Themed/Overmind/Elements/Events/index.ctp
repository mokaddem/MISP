<?php
/**
 * ==============================================================
 * Definition of fields displayed in the scaffold
 * ==============================================================
 *
 * Possible fields for each entry:
 *
 * - name           : Label displayed in the table
 * - header_html    : Label as markup, in place of name
 * - sort           : Database field used for sorting
 * - data_path      : Path to the data in the $events array
 * - element        : Template used for rendering
 * - url            : Associated link (supports %id%)
 * - card_section   : Display section in card mode
 * - display_in     : ['table', 'card']
 * - mode           : Specific option for certain elements (ex: timestamp)
 * - actions        : Available actions (for element = selector)
 *
 * Fields specific to actions:
 *
 * - type           : link | ajax | toggle | divider
 * - label          : Displayed text
 * - label_on/off   : Text for toggle
 * - icon           : FontAwesome icon
 * - icon_on/off    : Toggle icon
 * - url            : URL (supports %id% and %action%)
 * - class          : CSS class
 * - requirement    : Permission check function
 * - publish_path     : Path to the published value (toggle)
 *
 * The table's columns are the event_* field elements; the card draws
 * itself (Events/index_card) and shares only the checkbox and the actions.
 */

$possible = $possibleColumns ?? [];
$offered = function ($column) use ($possible) {
    return in_array($column, $possible, true);
};
// Columns the instance offers but the reader has hidden: rendered, and
// hidden by the style below, so the chooser can show them without a reload.
$hiddenColumns = array_values(array_diff($possible, $columns ?? []));

/*
 * The chooser, in the order it lists them: column key => setting name in
 * `event_index_hide_columns`, label, icon. Sightings, proposals and
 * discussions are parts of the extras column.
 */
$chooser = [
    'owner' => ['owner_org', __('Owner org'), ''],
    'user' => ['creator_user', __('Creator user'), ''],
    'ext' => ['is_extension', __('Extension'), '<i class="fas fa-code-branch"></i>'],
    'ctx' => ['clusters', __('Tags & galaxies'), '<i class="misp-icon misp-icon-galaxy misp-simple"></i>'],
    'attrs' => ['attribute_count', __('Attributes'), '<i class="misp-icon misp-icon-attribute misp-simple text-attribute"></i>'],
    'corr' => ['correlations', __('Correlations'), '<i class="fas fa-link text-correlation"></i>'],
    'reps' => ['report_count', __('Reports'), '<i class="misp-icon misp-icon-report misp-simple text-report"></i>'],
    'sight' => ['sightings', __('Sightings'), '<i class="misp-icon misp-icon-sighting misp-simple text-sighting"></i>'],
    'prop' => ['proposals', __('Proposals'), '<i class="fas fa-comment-medical"></i>'],
    'disc' => ['discussion', __('Discussions'), '<i class="fas fa-comments"></i>'],
    'changed' => ['timestamp', __('Last change'), '<i class="fas fa-pen"></i>'],
    'pub' => ['publish_timestamp', __('Published at'), '<i class="fas fa-upload"></i>'],
];
$chooserItems = '';
foreach ($chooser as [$setting, $label, $chooserIcon]) {
    if (!$offered($setting)) {
        continue;
    }
    $chooserItems .= sprintf(
        '<label data-te-opt="%s"><input class="form-check-input" type="checkbox" value="%s"%s><span class="te-cols-ic">%s</span>%s<small hidden></small></label>',
        h($setting),
        h($setting),
        in_array($setting, $hiddenColumns, true) ? '' : ' checked',
        $chooserIcon,
        h($label)
    );
}
$chooserHtml = sprintf(
    '<div class="dropdown te-chooser"><button type="button" class="te-gear" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="%s" aria-label="%s"><i class="fas fa-table-columns"></i></button>'
        . '<div class="dropdown-menu dropdown-menu-end shadow te-cols" role="group" aria-label="%s"><h6>%s</h6>%s'
        . '<div class="te-cols-foot"><span class="te-cols-note"></span><button type="button" data-te-reset>%s</button></div></div></div>',
    h(__('Choose columns')),
    h(__('Choose columns')),
    h(__('Columns')),
    h(__('Columns')),
    $chooserItems,
    h(__('Reset'))
);

$col = function ($key, array $field, $class = '') {
    $class = trim('te-c-' . $key . ' ' . $class);
    return $field + [
        'class' => $class,
        'header_class' => $class,
        'display_in' => ['table'],
    ];
};
$icon = function ($html) {
    return '<span class="te-hicon">' . $html . '</span>';
};
$lane = function ($key, $lane, $title = null) use ($col, $offered) {
    return $col($key, [
        'element' => 'event_lane',
        'lane' => $lane,
        'requirement' => $offered('clusters'),
        'header_html' => '<span class="te-lanecap"' . ($title ? ' title="' . h($title) . '"' : '') . '>'
            . $this->EventIndex->laneIcon($lane) . '<span>' . h($this->EventIndex->laneLabel($lane)) . '</span></span>',
    ], 'te-lane');
};
$count = function ($key, $count, $setting, $iconHtml, $title) use ($col, $icon, $offered) {
    return $col($key, [
        'element' => 'event_count',
        'count' => $count,
        'header_html' => $icon($iconHtml),
        'header_title' => $title,
        'requirement' => $offered($setting),
    ], 'te-num');
};

$fields = [
    [
        'element' => 'checkbox',
        'data_path' => 'Event.id',
        'publish_path' => 'Event.published',
        'card_section' => 'selector',
        'class' => 'te-c-sel',
        'header_class' => 'te-c-sel',
    ],
    $col('state', [
        'element' => 'event_state',
        'header_html' => $icon('<i class="fas fa-upload"></i>'),
        'header_title' => __('Publish state'),
    ]),
    $col('id', [
        'name' => __('ID'),
        'sort' => 'Event.id',
        'data_path' => 'Event.id',
        'element' => 'id',
        'url' => $baseurl . '/events/view2/%id%',
    ]),
    $col('title', [
        'name' => __('Title'),
        'sort' => 'Event.info',
        'element' => 'event_title',
    ]),
    $col('orgc', [
        'header_html' => '<span class="te-long">' . h(__('Creator org')) . '</span><span class="te-short">' . h(__('Creator')) . '</span>',
        'sort' => 'Orgc.name',
        'element' => 'event_orgc',
    ]),
    $col('owner', [
        'name' => __('Owner org'),
        'element' => 'event_owner',
        'requirement' => $offered('owner_org'),
    ]),
    $col('user', [
        'name' => __('Creator user'),
        'element' => 'event_creator_user',
        'requirement' => $offered('creator_user'),
    ]),
    $col('mk', [
        'name' => __('Markings'),
        'header_title' => __('Markings your analyst profile pins'),
        'element' => 'event_markings',
    ]),
    $lane('attrib', 'attribution'),
    $lane('behav', 'behaviour', __('ATT&CK techniques (count, then ids), then mitigations')),
    $lane('classif', 'classification', __('Other clusters, then tags')),
    $count('attrs', 'attributes', 'attribute_count', '<i class="misp-icon misp-icon-attribute misp-simple text-attribute"></i>', __('Attributes')),
    $count('objs', 'objects', 'attribute_count', '<i class="misp-icon misp-icon-object misp-simple text-object"></i>', __('Objects')),
    $count('reps', 'reports', 'report_count', '<i class="misp-icon misp-icon-report misp-simple text-report"></i>', __('Reports')),
    $count('corr', 'correlations', 'correlations', '<i class="fas fa-link text-correlation"></i>', __('Correlations')),
    $col('extras', [
        'element' => 'event_extras',
        'header_html' => '<span class="te-hicon te-hx"><i class="misp-icon misp-icon-analyst-graph misp-simple text-analystGraph"></i><i class="misp-icon misp-icon-sighting misp-simple text-sighting"></i><i class="fas fa-comments"></i></span>',
        'header_title' => __('Analyst graphs, sightings, proposals and discussions, when there are any'),
    ]),
    $col('ext', [
        'name' => __('Extension'),
        'element' => 'event_extension',
        'requirement' => $offered('is_extension'),
    ]),
    $col('dist', [
        'element' => 'event_distribution',
        'sort' => 'Event.distribution',
        'header_html' => $icon('<i class="fas fa-share-nodes"></i>'),
        'header_title' => __('Distribution'),
    ]),
    $col('date', [
        'name' => __('Date'),
        'header_title' => __('Event date'),
        'sort' => 'Event.date',
        'element' => 'event_date',
    ]),
    $col('pub', [
        'name' => __('Published'),
        'header_title' => __('Published at'),
        'sort' => 'Event.publish_timestamp',
        'element' => 'event_published',
        'requirement' => $offered('publish_timestamp'),
    ]),
    $col('changed', [
        'name' => __('Changed'),
        'header_title' => __('Last change'),
        'sort' => 'Event.timestamp',
        'element' => 'event_changed',
        'requirement' => $offered('timestamp'),
    ]),
    [
        'header_html' => $chooserHtml,
        'element' => 'row_actions',
        'data_path' => 'Event.id',
        'publish_path' => 'Event.published',
        'card_section' => 'extra',
        'display_in' => ['table', 'card'],
        'class' => 'te-c-act',
        'header_class' => 'te-c-act',
        'actions' => [
            [
                'type' => 'navigate',
                'label' => __('View'),
                'icon' => 'eye',
                'url' => $baseurl . '/events/view2/%id%'
            ],
            [
                'type' => 'modal',
                'label' => __('Edit'),
                'icon' => 'pen-to-square',
                'url' => $baseurl . '/events/edit/%id%',
                'requirement' => 'check_edit_rights'
            ],
            [
                'type' => 'modal',
                'label' => __('Delete'),
                'icon' => 'trash',
                'url' => $baseurl . '/events/delete/%id%',
                'class' => 'text-danger',
                'requirement' => 'check_edit_rights'
            ],
            [
                'type' => 'divider',
                'url' => '#',
                'requirement' => 'check_publish_rights'
            ],
            [
                'type' => 'toggle',
                'label_on' => __('Unpublish'),
                'label_off' => __('Publish'),
                'icon_on' => 'eye-slash',
                'icon_off' => 'upload',
                'url' => $baseurl . '/events/%action%/%id%',
                'publish_path' => 'Event.published',
                'requirement' => 'check_publish_rights'
            ]
        ]
    ],
];

// The hidden columns, hidden before the table script runs.
$hiddenRules = [];
foreach ($chooser as $key => [$setting]) {
    if (!in_array($setting, $hiddenColumns, true)) {
        continue;
    }
    if (in_array($key, ['sight', 'prop', 'disc'], true)) {
        $hiddenRules[] = '.te-index .te-table .dk-x-' . $setting;
        continue;
    }
    $keys = ['ctx' => ['attrib', 'behav', 'classif'], 'attrs' => ['attrs', 'objs']][$key] ?? [$key];
    foreach ($keys as $k) {
        $hiddenRules[] = '.te-index .te-table .te-c-' . $k;
    }
}
$tableConfig = [
    'hidden' => $hiddenColumns,
    'saved' => $savedHiddenColumns ?? null,
    'defaults' => array_values(array_intersect(['is_extension', 'publish_timestamp', 'owner_org', 'creator_user'], $possible)),
    'graded' => !empty(array_filter(array_map(function ($event) {
        return $event['EventCard']['grade'] ?? null;
    }, $events))),
];

$children = [];

$children[] = [
    'type' => 'search',
    'button' => 'Search',
    'placeholder' => 'Search by info, ID or UUID',
    'name'        => 'eventinfo',
    'mode'        => 'event',
    'id_field'    => 'eventid',
];

if(!empty($show_user_button)) {
    $children[] = [
        'type' => 'button',
        'label' => __('My events'),
        'icon' => 'misp-icon misp-icon-user1 misp-simple',
        'class' => 'btn btn-primary',
        'url' => $baseurl . '/events/index/searchemail:' . urlencode($me['email'])
    ];
}

if(!empty($show_org_button)) {
    $children[] = [
        'type' => 'button',
        'label' => __('Org events'),
        'icon' => 'misp-icon misp-icon-organisation misp-simple',
        'class' => 'btn btn-primary',
        'url' => $baseurl . '/events/index/searchorg:' . urlencode($me['org_id'])
    ];
}


$children[] = [
    'type' => 'more_filters',
    'label' => __('More filters'),
    'children' => [
        [
            'type' => 'dropdown',
            'label' => __('Distribution'),
            'name' => 'distribution',
            'options' => [
                '' => '',
                '0' => 'Your organisation only',
                '1' => 'Community',
                '2' => 'Connected communities',
                '3' => 'All communities'
            ]
        ],
        [
            'type' => 'dropdown',
            'label' => __('Published'),
            'name' => 'published',
            'options' => [
                '' => '',
                '1' => 'Published',
                '0' => 'Not published'
            ]
        ],
        [
            'type' => 'dropdown',
            'label' => __('Creator Org'),
            'name' => 'org',
            'options' => $orgOptions
        ],
        [
            'type' => 'dropdown',
            'label' => __('Tags'),
            'name' => 'tag',
            'options' => $tagOptions
        ],
        [
            'type' => 'dropdown',
            'label' => __('Galaxy'),
            'name' => 'galaxy',
            'options' => $galaxyOptions
        ]
    ]
];

/**
 * ==============================================================
 * Call the generic scaffold
 * ==============================================================
 *
 * Main parameters:
 *
 * - scaffold_data.data.data       : Main dataset
 * - scaffold_data.data.filter_bar    : Filter bar configuration
 * - scaffold_data.data.fields     : Column definitions
 * - index_url                     : Base URL for pagination / filters
 */

echo $this->element('genericElements/assetLoader', [
    'css' => ['events-index-cards', 'events-index-table'],
    'js' => ['events-index-cards', 'events-index-table'],
]);

printf(
    '<style class="te-colstyle">%s</style><div class="dk-index te-index" data-te="%s">',
    $hiddenRules ? implode(',', $hiddenRules) . '{display:none}' : '',
    h(json_encode($tableConfig))
);
echo $this->element('genericElementsBS5/IndexTable/scaffold', [
    'scaffold_data' => [
        'data' => [
            'data' => $events,
            // The grid itself is events-index-cards.css: auto-fill, 330px minimum.
            'cards_per_row' => 1,
            'card_element' => 'Events/index_card',
            'filter_bar' => [
                'pull' => 'right',
                'children' => $children,
                'export' => 1,
                'delete' => '/delete'
            ],
            'fields' => $fields,
            'primary_id_path' => 'Event.id',
            'row_dblclick_url' => $baseurl . '/events/view2/%id%',
            'table_class' => 'te-table',
            'row_class_callable' => function ($row) {
                return trim('te-row ' . ($row['EventCard']['markings']['rail']['class'] ?? ''));
            },
            'row_style_callable' => function ($row) {
                $colour = $row['EventCard']['markings']['rail']['colour'] ?? null;
                return preg_match('/^#[0-9a-f]{3,8}$/i', (string)$colour) ? '--te-rail:' . $colour : '';
            },
            'row_data_callable' => function ($row) {
                return ['event-id' => $row['Event']['id']];
            },
        ]
    ],
    'item_url' => '/events'
]);
echo '</div>';
