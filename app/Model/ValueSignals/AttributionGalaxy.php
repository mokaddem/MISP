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
 *
 * **Not every cluster is an attribution, and the profile says which
 * are** (D43). The context splits a value's galaxy tags into techniques
 * and clusters on *is this ATT&CK-shaped*, so `clusters` is every
 * galaxy that is not — sectors, countries, countermeasures and
 * typologies included. This signal consulted no category table at all,
 * so on a value tagged `sector:banking`, `country:lu` and a
 * `preventive-measure` it paid three times over and called it
 * attribution. `galaxies.attribution` is the eligibility filter it
 * never had; a profile declaring none leaves it counting what it
 * counted before, which is what a document forked before the section
 * existed needs.
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
        $carried = isset($galaxies['clusters'])
            ? $galaxies['clusters']
            : array();
        $clusters = $this->eligible($galaxies, $carried);
        if (empty($clusters)) {
            if (!$this->absenceFires($config, $context, 'galaxies')) {
                return null;
            }
            /*
             * Two absences, and they are not the same finding. Nobody
             * labelled this at all, or somebody labelled it with
             * galaxies that name no threat — and a signal that said
             * *no galaxy on any occurrence* over a value carrying
             * `sector:banking` would be contradicted by the context
             * card two panels away.
             */
            $ruled = count($carried);
            if ($ruled === 0) {
                $signal = __('No galaxy on any occurrence');
            } elseif ($ruled === 1) {
                $signal = __('One galaxy on the occurrences, and it'
                    . ' names no threat');
            } else {
                $signal = sprintf(
                    __('%d galaxies on the occurrences, none of them'
                        . ' naming a threat'),
                    $ruled
                );
            }
            return $this->row(
                $this->points($config, 'absent'),
                $signal,
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

    /**
     * The clusters this profile counts as an attribution.
     *
     * The filter is off unless the profile declares a list, so an
     * instance whose profile predates the section scores exactly as it
     * did. A cluster whose galaxy the context could not record is kept
     * for the same reason: dropping it would silently lower a score on
     * a data shape this signal cannot see, and the wrong answer there
     * is the one that removes evidence rather than the one that keeps
     * it.
     *
     * A cluster name folds two galaxies into one entry where both name
     * it — *Lazarus Group* is a `threat-actor` and an
     * `mitre-intrusion-set` — so one eligible galaxy is enough.
     *
     * @param array $galaxies The context's galaxy half
     * @param array $clusters Cluster name => occurrences
     * @return array The subset that attributes
     */
    private function eligible(array $galaxies, array $clusters)
    {
        if (empty($galaxies['attribution'])
            || !is_array($galaxies['attribution'])
            || empty($clusters)
        ) {
            return $clusters;
        }
        $allowed = array_flip($galaxies['attribution']);
        $types = isset($galaxies['types']) && is_array($galaxies['types'])
            ? $galaxies['types']
            : array();
        $kept = array();
        foreach ($clusters as $name => $occurrences) {
            if (!isset($types[$name]) || !is_array($types[$name])) {
                $kept[$name] = $occurrences;
                continue;
            }
            foreach ($types[$name] as $type) {
                if (isset($allowed[$type])) {
                    $kept[$name] = $occurrences;
                    break;
                }
            }
        }
        return $kept;
    }
}
