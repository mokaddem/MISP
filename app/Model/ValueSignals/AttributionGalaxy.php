<?php

/**
 * What this value has been attributed to.
 *
 * A galaxy cluster on an occurrence is a person's judgement that this
 * indicator belongs to a named actor, family or campaign — the
 * strongest editorial statement MISP's data model carries — so it is
 * worth more than another count of the same rows.
 *
 * **Absence fires here, and this is the case §4.2 was written for.**
 * On the benign value the fixture scores *"No galaxy and no technique
 * on any occurrence"* toward benign: nine occurrences, four
 * organisations, and nobody willing to attribute it to anything is
 * evidence rather than a gap. It fires only where the profile carries
 * an `absent` key and only on genuine absence — a cluster set an
 * exclusion emptied leaves the row silent.
 *
 * **One point per cluster, not per occurrence.** The same actor tag on
 * a hundred occurrences is one attribution repeated; the fixture's
 * flux value shows the trap plainly with *"QakBot, on 107
 * occurrences"*, where a per-occurrence weight would have paid 107
 * times for one judgement. The occurrence count belongs in the prose,
 * where it is context rather than arithmetic.
 */
class AttributionGalaxy extends ValueSignalBase
{
    public $id = 'attribution.galaxy';
    public $group = 'Attribution';
    public $evidence_class = self::EVIDENCE_ROW;
    public $reads = array('galaxies');
    public $absence_key = 'absent';
    public $source = 'Context';

    public function __construct()
    {
        $this->description = __(
            'Galaxy clusters attached to this value\'s own'
            . ' occurrences.'
        );
        $this->points_schema = array(
            'per_cluster' => array(
                'type' => 'int',
                'default' => 7,
                'label' => __('Points per distinct cluster'),
            ),
            'cap' => array(
                'type' => 'int',
                'default' => 21,
                'label' => __('Most this signal may contribute'),
            ),
            'absent' => array(
                'type' => 'int',
                'default' => -7,
                'label' => __('Points when nobody has attributed it'),
            ),
        );
        $this->unit = array(
            'points' => 'per_cluster',
            'cap' => 'cap',
            'one' => __('A galaxy cluster on any occurrence'),
            'many' => __('%d galaxy clusters across the occurrences'),
        );
    }

    public function evaluate(array $context, array $config)
    {
        $galaxies = isset($context['galaxies'])
            ? $context['galaxies']
            : array();
        $clusters = isset($galaxies['clusters'])
            ? $galaxies['clusters']
            : array();
        if (empty($clusters)) {
            if (!$this->absenceFires($config, $context, 'galaxies')) {
                return null;
            }
            return $this->row(
                $this->points($config, 'absent'),
                __('No galaxy on any occurrence'),
                __('Nobody has attributed this value to an actor,'
                    . ' family or campaign'),
                $context
            );
        }
        arsort($clusters);
        $points = $this->capped(
            $this->points($config, 'per_cluster') * count($clusters),
            $this->points($config, 'cap')
        );
        $names = array_keys($clusters);
        $lead = $names[0];
        $occurrences = (int)$clusters[$lead];
        $signal = count($names) === 1
            ? sprintf(
                __('Linked to galaxy: %1$s (%2$d %3$s)'),
                $lead,
                $occurrences,
                $occurrences === 1
                    ? __('occurrence')
                    : __('occurrences')
            )
            : sprintf(
                __('Linked to %1$d galaxy clusters, led by %2$s'),
                count($names),
                $lead
            );
        return $this->row(
            $points,
            $signal,
            implode(', ', array_slice($names, 0, 4)),
            $context
        );
    }
}
