<?php

/**
 * Scratch shell: what do the object-founded sections actually return?
 *
 * Phase 26 re-founds the Relationships tab's graph on the object join
 * and adds two panels — Dated relations and Object relationships. This
 * drives the model facades that back them against the eight
 * verification values of `03-relationships.md` §23.8 and prints the
 * shapes, so the numbers in that document are measured rather than
 * asserted.
 *
 * It also weighs the graph feed, which is what §23.3 says the
 * legibility bound has to be measured against: the JSON of all five
 * layers at once, per surface, per value.
 *
 * **Not part of the application.** To run it:
 *
 *   cp prd/value-profile-live/26-object-graph-probe.php \
 *      app/Console/Command/ObjectGraphProbeShell.php
 *   app/Console/cake ObjectGraphProbe run 1
 *   rm app/Console/Command/ObjectGraphProbeShell.php
 */
class ObjectGraphProbeShell extends AppShell
{
    public $uses = array('User', 'ValueProfile');

    /** §23.8's eight, and why each one is here. */
    private $values = array(
        'draculax.myq-see.com.' => 'five dated passive-dns resolutions',
        '45.77.250.80' => '42 siblings across 23 domain-ip objects',
        '18.117.184.102' => 'the bridge, and the reference layer',
        '0.0.0.0' => 'the roll-up stress case, 35,102 siblings',
        '443' => 'the second roll-up case, 845 siblings',
        '8.8.8.8' => 'the populated case',
        '1.0.155.105' => 'the suppressed state',
        'github.com' => '21 occurrences in a single event',
    );

    public function run()
    {
        $user = $this->User->getAuthUser((int)$this->args[0]);
        Configure::write('CurrentUserId', $user['id']);
        foreach ($this->values as $value => $why) {
            $this->probe($user, $value, $why);
        }
    }

    private function probe(array $user, $value, $why)
    {
        $this->out('');
        $this->out('=== ' . $value . '  — ' . $why);
        $started = microtime(true);
        $dated = $this->ValueProfile->forRelationDated($user, $value);
        $datedMs = (microtime(true) - $started) * 1000;

        $started = microtime(true);
        $refs = $this->ValueProfile->forRelationReferences($user, $value);
        $refMs = (microtime(true) - $started) * 1000;

        $started = microtime(true);
        $graph = $this->ValueProfile->forRelationGraph($user, $value);
        $graphMs = (microtime(true) - $started) * 1000;

        $this->dated($dated['relationships']['dated'], $datedMs);
        $this->references($refs['relationships']['references'], $refMs);
        $this->graph($graph['relationships']['graph'], $graphMs);
    }

    private function dated(array $dated, $ms)
    {
        $this->out(sprintf(
            '  dated      %d rows of %d · %d dated objects of %d read'
            . ' · in %d objects · templates: %s · %.0f ms',
            count($dated['rows']),
            $dated['total'],
            $dated['objects'],
            $dated['read_objects'],
            $dated['in_objects'],
            implode(', ', $dated['templates']) ?: '—',
            $ms
        ));
        foreach (array_slice($dated['rows'], 0, 6) as $row) {
            $this->out(sprintf(
                '             %-22s %-10s %s → %s  (%s/%s)%s',
                substr($row['value'], 0, 22),
                $row['relation'],
                substr($row['first']['raw'], 0, 16),
                substr($row['last']['raw'], 0, 16),
                $row['first']['relation'],
                $row['last']['relation'],
                $row['origin'] === null ? '' : '  origin=' . $row['origin']
            ));
        }
    }

    private function references(array $refs, $ms)
    {
        $this->out(sprintf(
            '  references %d rows of %d · %d of %d objects carry one'
            . ' · %d occurrences · types: %s · %.0f ms',
            count($refs['rows']),
            $refs['total'],
            $refs['with_references'],
            $refs['read_objects'],
            $refs['occurrences'],
            implode(', ', array_keys($refs['types'])) ?: '—',
            $ms
        ));
        foreach (array_slice($refs['rows'], 0, 6) as $row) {
            $this->out(sprintf(
                '             %-16s %-8s %-16s %s',
                $row['relationship'],
                $row['direction'],
                $row['far']['object'] === null
                    ? '(attribute)'
                    : $row['far']['object'],
                substr($row['far']['label'], 0, 60)
            ));
        }
    }

    private function graph(array $graph, $ms)
    {
        $feed = strlen(json_encode($graph['feed']));
        $peek = strlen(json_encode($graph['peek']));
        $this->out(sprintf(
            '  graph      feed %d nodes / %d edges / %s'
            . ' · peek %d nodes / %d edges / %s · %.0f ms',
            count($graph['feed']['nodes']),
            count($graph['feed']['edges']),
            $this->weigh($feed),
            count($graph['peek']['nodes']),
            count($graph['peek']['edges']),
            $this->weigh($peek),
            $ms
        ));
        foreach ($graph['layers'] as $name => $layer) {
            $bits = array();
            foreach ($layer as $key => $number) {
                $bits[] = $key . '=' . (is_bool($number)
                    ? ($number ? 'yes' : 'no')
                    : $number);
            }
            $this->out(sprintf('             %-10s %s', $name,
                implode(' ', $bits)));
        }
        foreach (array_slice($graph['peek']['edges'], 0, 6) as $edge) {
            $this->out(sprintf('             peek edge  %-10s %s',
                $edge['data']['kind'], $edge['data']['label']));
        }
    }

    private function weigh($bytes)
    {
        return $bytes < 1024
            ? $bytes . ' B'
            : sprintf('%.1f KB', $bytes / 1024);
    }
}
