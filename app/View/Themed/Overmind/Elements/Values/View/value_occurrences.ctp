<?php
/**
 * Every attribute row carrying this value, across every event the
 * viewing user can see.
 *
 * Will be an `index_table` over `$valueProfile['occurrences']` with the
 * fields `checkbox`, `event_info`, `organisation`, `type`, `category`,
 * `ids`, `distribution`, `value_object_context`, `datetime` and
 * `tag_list`, a bulk-action bar from `multi_select_toolbar`, and the
 * ACL truncation note in the footer.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewOccurrences. Placeholder body until its pass lands.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
echo $this->element('Values/View/value_panel_placeholder', array(
    'panelTitle' => __('Occurrences'),
    'panelIcon' => 'misp-icon misp-icon-attribute misp-simple',
    'panelColor' => 'var(--attribute)',
    'panelNote' => __(
        'The attribute rows carrying this value, with their'
        . ' event, organisation, IDS flag, distribution and tags,'
        . ' plus the note saying how many rows distribution rules'
        . ' keep from you.'
    ),
));
