<?php
/**
 * What the community has labelled this value.
 *
 * Will show tags grouped by taxonomy with per-tag occurrence counts,
 * local tags marked as local, TLP and PAP in their canonical colours,
 * `admiralty-scale` as a labelled scale rather than a raw string, a
 * conflict marker where two events disagree on TLP, and then the galaxy
 * clusters with their own counts.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewContext. Placeholder body until its pass lands.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
echo $this->element('Values/View/value_panel_placeholder', array(
    'panelTitle' => __('Tags and galaxies'),
    'panelIcon' => 'misp-icon misp-icon-tag misp-simple',
    'panelColor' => 'var(--tag)',
    'panelNote' => __(
        'Tags grouped by taxonomy with occurrence counts, and'
        . ' the galaxy clusters this value has been attributed to.'
    ),
));
