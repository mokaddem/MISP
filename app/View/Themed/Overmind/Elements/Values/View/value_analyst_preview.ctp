<?php
/**
 * The most recent analyst notes and opinions on this value.
 *
 * A preview only: the threaded view and the full opinion distribution
 * belong to the Analyst data tab.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewAnalystPreview. Placeholder body until its pass lands.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
echo $this->element('Values/View/value_panel_placeholder', array(
    'panelTitle' => __('Analyst data'),
    'panelIcon' => 'misp-icon misp-icon-analyst-note misp-simple',
    'panelColor' => 'var(--analystData)',
    'panelNote' => __(
        'The latest notes and opinions, with a link into the'
        . ' threaded view.'
    ),
));
