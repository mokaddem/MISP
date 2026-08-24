<?php
/**
 * Where this value appears outside this instance's own events.
 *
 * Will list the feeds holding it, the sync servers whose cache matches,
 * and the SightingDB hit count.
 *
 * Lazily loaded into `.ajax-card` from
 * ValuesController::viewExternal. Placeholder body until its pass lands.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
echo $this->element('Values/View/value_panel_placeholder', array(
    'panelTitle' => __('External presence'),
    'panelIcon' => 'fas fa-cloud-arrow-down',
    'panelColor' => 'var(--enrichment)',
    'panelNote' => __(
        'The feeds carrying this value, the sync servers whose'
        . ' cache holds it, and its SightingDB count.'
    ),
));
