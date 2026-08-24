<?php
/**
 * The Verdict tab for a value whose signals agree.
 *
 * A glass box, not a score: the hero states the disposition and the
 * prose behind it, then the signal ledger grouped by kind, the
 * contradictions kept explicitly un-netted, the per-organisation
 * stances, the score composition, and the decay curves over time.
 *
 * Nothing here is stored or synchronised - the verdict is computed at
 * render, from what the viewing user is allowed to see.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewVerdict. Placeholder body until its pass lands.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
echo $this->element('Values/View/value_panel_placeholder', array(
    'panelTitle' => __('Verdict'),
    'panelIcon' => 'fas fa-gavel',
    'panelColor' => 'var(--primary)',
    'panelNote' => __(
        'The full assessment: signal ledger, contradictions,'
        . ' who says what, how the score was reached, and the'
        . ' verdict over time.'
    ),
));
