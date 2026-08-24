<?php
/**
 * Value Profile — the page whose subject is one value rather than one
 * event. Skeleton pass: real routing, real chrome, hardcoded data.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */

echo $this->element('genericElements/assetLoader', array(
    'css' => array('value-profile'),
));

$profile = $valueProfile;

/*
 * ------------------------------------------------------------------
 * Banner
 * ------------------------------------------------------------------
 * The value itself, monospace and selectable, followed by one chip per
 * MISP type it appears as. Values are stored refanged, so there is no
 * defanged form to toggle to.
 */
$titleHtml = '<span class="vp-value font-monospace user-select-all">'
    . h($profile['value']) . '</span>';

foreach ($profile['types'] as $type) {
    $titleHtml .= '<span class="vp-type-chip" title="'
        . h(__n(
            '%1$s occurrence of type %2$s',
            '%1$s occurrences of type %2$s',
            $type['count'],
            $type['count'],
            $type['type']
        )) . '">'
        . '<span class="vp-type-chip-name">' . h($type['type']) . '</span>'
        . '<span class="vp-type-chip-count">' . h($type['count'])
        . '</span></span>';
}

foreach ($profile['warninglists'] as $warninglist) {
    $titleHtml .= '<span class="vp-warninglist-chip" title="'
        . h($warninglist['name']) . '">'
        . '<i class="fas fa-exclamation-triangle"></i>'
        . h(__('Warninglist hit')) . '</span>';
}

$description = null;
if (!empty($profile['value2_note'])) {
    $description = h($profile['value2_note']);
}

/*
 * ------------------------------------------------------------------
 * Header actions
 * ------------------------------------------------------------------
 * Everything on this page that would write is rendered visibly disabled
 * rather than silently dead. Grouping is switched off so each control
 * keeps its own button: with five of them, folding the writes behind a
 * caret would hide what the page deliberately shows as unavailable.
 */
$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

$exportTargets = array(
    array('label' => __('STIX 2.1'), 'icon' => 'shield-halved'),
    array('label' => __('Suricata'), 'icon' => 'shield'),
    array('label' => __('Zeek'), 'icon' => 'network-wired'),
    array('label' => __('RPZ'), 'icon' => 'ban'),
    array('label' => __('CSV'), 'icon' => 'file-csv'),
    array('type' => 'divider'),
    array('label' => __('Copy the restSearch query'), 'icon' => 'code'),
);
foreach ($exportTargets as &$target) {
    if (!empty($target['type'])) {
        continue;
    }
    $target['class'] = 'disabled';
    $target['title'] = $noWrites;
}
unset($target);

$headerActions = array(
    array(
        'type' => 'navigate',
        'label' => __('Add sighting'),
        'icon' => 'eye',
        'class' => 'btn btn-primary disabled',
        'title' => $noWrites,
    ),
    array(
        'type' => 'navigate',
        'label' => __('Enrich'),
        'icon' => 'wand-magic-sparkles',
        'class' => 'btn btn-outline-primary disabled',
        'title' => $noWrites,
    ),
    array(
        'type' => 'navigate',
        'label' => __('Add to collection'),
        'icon' => 'folder-plus',
        'class' => 'btn btn-outline-dark disabled',
        'title' => $noWrites,
    ),
    array(
        'type' => 'navigate',
        'label' => __('Watch'),
        'icon' => 'bell',
        'class' => 'btn btn-outline-dark disabled',
        'title' => $noWrites,
    ),
    array(
        'type' => 'dropdown',
        'label' => __('Export'),
        'icon' => 'file-export',
        'class' => 'btn btn-outline-dark',
        'children' => $exportTargets,
    ),
);

$this->set('headerTitleHtml', $titleHtml);
$this->set('headerBreadcrumb', __('Data points') . ' > ' . __('Value Profile'));
$this->set('headerDescription', $description);
$this->set('headerActions', $headerActions);
$this->set('headerActionGroups', array('navigate' => array('mode' => 'none')));

echo $this->element('Values/View/value_fact_strip', array(
    'facts' => $profile['facts'],
));

echo $this->element('Values/View/value_pivot_rail', array(
    'pivots' => $profile['pivots'],
));

/*
 * ------------------------------------------------------------------
 * Tabs
 * ------------------------------------------------------------------
 * Every tab is stubbed in this pass. Going live is a local change: swap
 * one registry entry's placeholder params for the panels it should hold.
 */
$counts = $profile['counts'];
$tabRegistry = array(
    array(
        'id' => 'general',
        'title' => __('Overview'),
        'icon' => 'fas fa-info-circle',
        'note' => __(
            'Occurrence summary, tags and galaxies, analyst data, and a'
            . ' rail carrying the verdict, sightings, lifecycle and'
            . ' external presence.'
        ),
    ),
    array(
        'id' => 'verdict',
        'title' => __('Verdict'),
        'icon' => 'fas fa-gavel',
        'note' => __(
            'The glass-box assessment: every signal, contradiction and'
            . ' per-organisation stance behind the disposition.'
        ),
    ),
    array(
        'id' => 'occurrences',
        'title' => __('Occurrences'),
        'icon' => 'misp-icon misp-icon-attribute misp-simple',
        'count' => $counts['occurrences'],
        'note' => __(
            'The full, filterable attribute table with a bulk-action bar.'
        ),
    ),
    array(
        'id' => 'sightings',
        'title' => __('Sightings'),
        'icon' => 'misp-icon misp-icon-sighting misp-simple',
        'count' => $counts['sightings'],
        'note' => __(
            'A histogram stacked by organisation, with the decay-score'
            . ' curve overlaid.'
        ),
    ),
    array(
        'id' => 'relationships',
        'title' => __('Relationships'),
        'icon' => 'fas fa-link',
        'count' => $counts['relationships'],
        'note' => __(
            'Co-occurrence, near-matches and analyst-asserted'
            . ' relationships, kept apart rather than conflated.'
        ),
    ),
    array(
        'id' => 'enrichment',
        'title' => __('Enrichment'),
        'icon' => 'fas fa-wand-magic-sparkles',
        'count' => $counts['enrichment'],
        'note' => __(
            'A module picker and per-module results. Never auto-run:'
            . ' querying an adversary\'s infrastructure announces your'
            . ' interest.'
        ),
    ),
    array(
        'id' => 'analyst',
        'title' => __('Analyst data'),
        'icon' => 'misp-icon misp-icon-analyst-note misp-simple',
        'count' => $counts['analyst'],
        'note' => __(
            'Threaded notes and the opinion distribution across'
            . ' organisations.'
        ),
    ),
    array(
        'id' => 'timeline',
        'title' => __('Timeline'),
        'icon' => 'fas fa-clock',
        'note' => __(
            'One merged chronology: publications, first and last seen,'
            . ' sightings, tags, opinions, feed appearances and edits.'
        ),
    ),
    array(
        'id' => 'history',
        'title' => __('History'),
        'icon' => 'fas fa-history',
        'note' => __(
            'The audit log across every occurrence, with actor and'
            . ' organisation.'
        ),
    ),
);

$tabs = array();
foreach ($tabRegistry as $tab) {
    $tabs[] = array(
        'id' => $tab['id'],
        'title' => $tab['title'],
        'icon' => $tab['icon'],
        'count' => $tab['count'] ?? null,
        'left' => array(
            array(
                'element' => 'Values/View/value_placeholder',
                'params' => array(
                    'tabTitle' => $tab['title'],
                    'tabIcon' => $tab['icon'],
                    'tabNote' => $tab['note'],
                ),
            ),
        ),
    );
}

echo $this->element('genericElementsBS5/Layout/view_layout', array(
    'data' => $profile,
    'tabs' => $tabs,
));
