<?php
/**
 * Whether this value is still worth acting on.
 *
 * Will show each decaying model's current score against its threshold
 * with the `decayed` flag, the warninglist result - a miss being as
 * informative as a hit, so the number of lists checked is stated - and
 * the correlation count against the over-correlation threshold.
 *
 * Lazily loaded into `.ajax-card` from
 * ValuesController::viewLifecycle. Placeholder body until its pass lands.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
echo $this->element('Values/View/value_panel_placeholder', array(
    'panelTitle' => __('Lifecycle'),
    'panelIcon' => 'fas fa-hourglass-half',
    'panelColor' => 'var(--correlation)',
    'panelNote' => __(
        'Decay score per model, the warninglist result, and how'
        . ' many correlations this value carries.'
    ),
));
