<?php
/**
 * The verdict in one card: what this value resolves to, and why, in
 * three lines.
 *
 * Will show the disposition, a confidence bar, the top three signals
 * with a direction glyph and a weight, a count of the signals it does
 * not have room for, and a jump to the Verdict tab. The card is a
 * summary of that tab, never a second opinion.
 *
 * Lazily loaded into `.ajax-card` from
 * ValuesController::viewVerdictCard. Placeholder body until its pass lands.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
echo $this->element('Values/View/value_panel_placeholder', array(
    'panelTitle' => __('Verdict'),
    'panelIcon' => 'fas fa-gavel',
    'panelColor' => 'var(--primary)',
    'panelNote' => __(
        'The disposition, its confidence, and the three signals'
        . ' that carried it, with a jump to the full assessment.'
    ),
));
