<?php

/**
 * What this value has been attributed to.
 *
 * A galaxy cluster on an occurrence is a person's judgement that this
 * indicator belongs to a named actor, family or campaign — the
 * strongest editorial statement MISP's data model carries — so it is
 * worth more than another count of the same rows.
 *
 * **Absence fires here, and this is the case absence keys exist for.**
 * *"No galaxy and no technique on any occurrence"* scores toward
 * benign: nine occurrences, four organisations, and nobody willing to
 * attribute it to anything is evidence rather than a gap. It fires
 * only where the profile carries an `absent` key and only on genuine
 * absence — a cluster set an exclusion emptied leaves the row silent.
 *
 * **An absence says where it looked.** *Occurrence* is the whole of
 * the distinction this signal rests on, and a row that leans on the
 * word silently reads as a contradiction of the context card: the
 * Overview heads a *Threat Actor* group with *APT29*, this says nobody
 * attributed the value, and a reader is left to reconcile two true
 * sentences. So where the carrying events name a threat the count is
 * put in the row and the reason under it. **Nothing about the score
 * changes** — the event labels are not an attribution of the value and
 * are not paid for, which is the point the wording makes out loud
 * rather than by omission.
 *
 * **One point per cluster, not per occurrence.** The same actor tag on
 * a hundred occurrences is one attribution repeated; on *"QakBot, on
 * 107 occurrences"* a per-occurrence weight would pay 107 times for
 * one judgement. The occurrence count belongs in the prose,
 * where it is context rather than arithmetic.
 *
 * **Not every cluster is an attribution, and the profile says which
 * are.** The context splits a value's galaxy tags into techniques and
 * clusters on *is this ATT&CK-shaped*, so `clusters` is every galaxy
 * that is not — sectors, countries, countermeasures and typologies
 * included. Unfiltered, a value tagged `sector:banking`, `country:lu`
 * and a `preventive-measure` would be paid three times over and called
 * attribution. `galaxies.attribution` is the eligibility filter; a
 * profile declaring none counts every cluster, which is what a profile
 * written before the section existed needs.
 */
class AttributionGalaxy extends ValueSignalBase
{
    public $id = 'attribution.galaxy';
    public $group = 'Attribution';
    public $evidence_class = self::EVIDENCE_ROW;
    public $reads = array('galaxies');
    public $absence_key = 'absent';
    public $tab = 'general';

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
             * Three absences, and they are not the same finding.
             * Nobody labelled this at all, somebody labelled it with
             * galaxies that name no threat, or the labels naming one
             * are on the events rather than on this value — and a
             * signal that said *no galaxy on any occurrence* over a
             * value carrying `sector:banking` would be contradicted by
             * the context card two panels away.
             *
             * **The third is the same objection at event scope**, and
             * it is the one a reader actually meets: the context card
             * heads a *Threat Actor* group with *APT29*, this row says
             * nobody attributed it, and both are true. The word doing
             * the work is *occurrence*, and it was doing it silently.
             * So the count is said out loud and the evidence line says
             * why it is not an attribution, which is the distinction
             * `ValueProfile::verdictEventGalaxies` exists to keep
             * rather than to soften.
             *
             * `eligible()` runs over the event set too, so the two
             * halves of the sentence count the same kind of thing: a
             * value whose events carry only `producer` and a typology
             * says *no galaxy* with nothing in brackets, rather than
             * sending its reader to look for an attribution that is
             * not there.
             */
            $ruled = count($carried);
            $evidence = __('Nobody has attributed this value to an'
                . ' actor, family or campaign');
            if ($ruled === 0) {
                $onEvents = $this->eligible(
                    $galaxies,
                    isset($galaxies['on_events'])
                        && is_array($galaxies['on_events'])
                        ? $galaxies['on_events']
                        : array(),
                    isset($galaxies['event_types'])
                        && is_array($galaxies['event_types'])
                        ? $galaxies['event_types']
                        : array()
                );
                if (empty($onEvents)) {
                    $signal = __('No galaxy on any occurrence');
                } else {
                    $signal = sprintf(
                        __('No galaxy on any occurrence (%d on the'
                            . ' events it appears in)'),
                        count($onEvents)
                    );
                    $evidence = __('Its events are attributed to a'
                        . ' threat; the value itself is not');
                }
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
                $evidence,
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
     * The types map is a parameter rather than a lookup because the
     * absence wording asks the same question of a second set — the
     * clusters on the carrying events — and one rule applied to both
     * is what keeps the ledger from calling a galaxy an attribution in
     * brackets and not in the row above.
     *
     * @param array $galaxies The context's galaxy half
     * @param array $clusters Cluster name => occurrences
     * @param array|null $types Cluster name => galaxy types; the
     *                          occurrence set's own map when omitted
     * @return array The subset that attributes
     */
    private function eligible(array $galaxies, array $clusters,
        array $types = null
    ) {
        if (empty($galaxies['attribution'])
            || !is_array($galaxies['attribution'])
            || empty($clusters)
        ) {
            return $clusters;
        }
        $allowed = array_flip($galaxies['attribution']);
        if ($types === null) {
            $types = isset($galaxies['types'])
                && is_array($galaxies['types'])
                ? $galaxies['types']
                : array();
        }
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
