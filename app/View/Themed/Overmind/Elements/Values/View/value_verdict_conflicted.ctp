<?php
/**
 * The Verdict tab for a value whose signals contradict each other.
 *
 * A different layout rather than a different colour: two opposed cases
 * side by side, each row carrying its weight, evidence and source. Then
 * the warninglist callout, the signals counted for neither side, the
 * per-organisation reading, the resolution options - each naming
 * exactly what it would write - and the opinion distribution.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewVerdict. Placeholder body until its pass lands.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
echo $this->element('Values/View/value_panel_placeholder', array(
    'panelTitle' => __('Verdict'),
    'panelIcon' => 'fas fa-scale-unbalanced',
    'panelColor' => 'var(--bs-warning)',
    'panelNote' => __(
        'Two opposed cases side by side, the warninglist'
        . ' callout, the unresolved signals, and what each way of'
        . ' resolving it would write.'
    ),
));
