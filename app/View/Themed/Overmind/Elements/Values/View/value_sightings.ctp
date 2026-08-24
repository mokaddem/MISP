<?php
/**
 * Who has seen this value, and when.
 *
 * Will split the total across MISP's three sighting types - sighting,
 * false positive and expiration - draw 90 days as a CSS bar sparkline,
 * list the top reporting organisations, and offer a disabled
 * "I saw this".
 *
 * Lazily loaded into `.ajax-card` from
 * ValuesController::viewSightings. Placeholder body until its pass lands.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
echo $this->element('Values/View/value_panel_placeholder', array(
    'panelTitle' => __('Sightings'),
    'panelIcon' => 'misp-icon misp-icon-sighting misp-simple',
    'panelColor' => 'var(--sighting)',
    'panelNote' => __(
        'The sighting total split by type, ninety days as a'
        . ' sparkline, and the organisations reporting them.'
    ),
));
