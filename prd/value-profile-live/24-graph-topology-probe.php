<?php

/**
 * Scratch shell: what shape is a value's neighbourhood actually in?
 *
 * §22 argues the graph should stop being a star and become two-mode —
 * `value -> event -> neighbour` — and then reads clusters, bridges,
 * hubs and isolates off the topology. Every word of that is reasoned;
 * none of it is measured. This measures it, against the same rows the
 * tab reads, so §22.8's three open questions get numbers:
 *
 *   - is the two-mode graph legible, or is it a different hairball
 *   - does a real neighbourhood have more than one component at all
 *   - does the top-12-by-shared-events cut keep the structure, or does
 *     ranking by shared events throw the bridge away
 *
 * The last one is the point. A cap that hides the only interesting
 * node makes the whole redesign pointless, so the same statistics are
 * computed at 12, 30, 60 and uncapped.
 *
 * Not part of the application. To run it:
 *
 *   cp prd/value-profile-live/24-graph-topology-probe.php \
 *      app/Console/Command/GraphTopologyProbeShell.php
 *   app/Console/cake GraphTopologyProbe run 1
 *   app/Console/cake GraphTopologyProbe sample 1 20
 *   rm app/Console/Command/GraphTopologyProbeShell.php
 */
class GraphTopologyProbeShell extends AppShell
{
    public $uses = array('User', 'MispAttribute');

    /** §12.1's six, and why each one is there. */
    private $values = array(
        '8.8.8.8' => 'the populated case',
        '443' => 'the heaviest value on the instance',
        '0.0.0.0' => '32,922 objects; the sibling cap bites',
        '185.92.180.100' => 'one event; exercises CIDR',
        '1.0.155.105' => 'the suppressed state',
        'github.com' => '21 occurrences in a single event',
    );

    /** The cuts to measure the same neighbourhood under. */
    private $caps = array(12, 30, 60, 0);

    public function run()
    {
        $user = $this->User->getAuthUser((int)$this->args[0]);
        Configure::write('CurrentUserId', $user['id']);
        foreach ($this->values as $value => $why) {
            $this->probe($user, $value, $why);
        }
    }

    /**
     * The same measurement over N random values, to learn whether the
     * six above are typical or hand-picked.
     */
    public function sample()
    {
        $user = $this->User->getAuthUser((int)$this->args[0]);
        Configure::write('CurrentUserId', $user['id']);
        $n = isset($this->args[1]) ? (int)$this->args[1] : 20;
        $rows = $this->MispAttribute->find('all', array(
            'recursive' => -1,
            'fields' => array('Attribute.value1', 'Attribute.type'),
            'conditions' => array(
                'Attribute.value1 !=' => '',
                'Attribute.type' => array('ip-dst', 'ip-src', 'domain',
                    'hostname', 'md5', 'sha256', 'url', 'email-src'),
            ),
            'order' => array('Attribute.id' => 'ASC'),
            'limit' => 4000,
        ));
        $seen = array();
        foreach ($rows as $row) {
            $seen[$row['Attribute']['value1']] =
                $row['Attribute']['type'];
        }
        $values = array_keys($seen);
        $step = max(1, (int)floor(count($values) / max(1, $n)));
        $picked = array();
        for ($i = 0; $i < count($values) && count($picked) < $n;
            $i += $step
        ) {
            $picked[] = $values[$i];
        }
        foreach ($picked as $value) {
            $this->probe($user, $value, $seen[$value]);
        }
    }

    /**
     * The best case the graph can be given: values that occur in
     * several events, and so *could* have a topology, without being
     * one of the instance's mega-values. A single-event value is a
     * star by construction and proves nothing either way.
     */
    public function spread()
    {
        $user = $this->User->getAuthUser((int)$this->args[0]);
        Configure::write('CurrentUserId', $user['id']);
        $n = isset($this->args[1]) ? (int)$this->args[1] : 12;
        $min = isset($this->args[2]) ? (int)$this->args[2] : 4;
        $max = isset($this->args[3]) ? (int)$this->args[3] : 40;
        $rows = $this->MispAttribute->find('all', array(
            'recursive' => -1,
            'fields' => array(
                'Attribute.value1',
                'COUNT(DISTINCT Attribute.event_id) AS events',
            ),
            'conditions' => array('Attribute.value1 !=' => ''),
            'group' => array('Attribute.value1'),
            'having' => array(
                'COUNT(DISTINCT Attribute.event_id) >=' => $min,
                'COUNT(DISTINCT Attribute.event_id) <=' => $max,
            ),
            'order' => array('events' => 'DESC'),
            'limit' => 400,
        ));
        $this->out(sprintf(
            '# %d values in %d-%d events; probing %d of them',
            count($rows), $min, $max, $n
        ));
        $step = max(1, (int)floor(count($rows) / max(1, $n)));
        $done = 0;
        for ($i = 0; $i < count($rows) && $done < $n; $i += $step) {
            $this->probe(
                $user,
                $rows[$i]['Attribute']['value1'],
                $rows[$i][0]['events'] . ' events'
            );
            $done++;
        }
    }

    private function probe(array $user, $value, $why)
    {
        $this->out('=== ' . $value . ' — ' . $why . ' ===');

        ClassRegistry::removeObject('ValueProfile');
        $profile = ClassRegistry::init('ValueProfile');
        $method = new ReflectionMethod($profile, 'relationScan');
        $method->setAccessible(true);
        $t = microtime(true);
        try {
            $scan = $method->invoke($profile, $user, $value,
                array(), true);
        } catch (Exception $e) {
            $this->out('  scan threw: ' . $e->getMessage());
            $this->out('');
            return;
        }
        $ms = (microtime(true) - $t) * 1000;

        $groups = array();
        foreach ($scan['rows'] as $row) {
            $v = isset($row['Attribute']['value'])
                ? $row['Attribute']['value']
                : '';
            if ($v === '') {
                continue;
            }
            if (!isset($groups[$v])) {
                $groups[$v] = array();
            }
            $groups[$v][(int)$row['Attribute']['event_id']] = true;
        }

        $this->out(sprintf(
            '  scan %.0f ms · events seen %d, read %d, oversized %d,'
                . ' skipped %d · neighbours %d',
            $ms,
            $scan['events_seen'],
            count($scan['picked']),
            $scan['events_oversized'],
            $scan['events_unread'],
            count($groups)
        ));

        if (empty($groups)) {
            $this->out('  nothing to draw');
            $this->out('');
            return;
        }

        // Rank the way the graph does today: by shared events desc.
        uasort($groups, function ($a, $b) {
            return count($b) - count($a);
        });

        foreach ($this->caps as $cap) {
            $this->report($groups, $cap);
        }
        $this->out('');
    }

    /**
     * The topology of the top-$cap neighbours ($cap 0 = all of them).
     */
    private function report(array $groups, $cap)
    {
        $subset = $cap > 0 ? array_slice($groups, 0, $cap, true) : $groups;
        $n = count($subset);
        if ($n === 0) {
            return;
        }

        // event -> neighbours in it
        $events = array();
        $twoModeEdges = 0;
        foreach ($subset as $v => $eventIds) {
            foreach (array_keys($eventIds) as $eventId) {
                $events[$eventId][] = $v;
                $twoModeEdges++;
            }
        }

        // The projection the two-mode model exists to avoid.
        $projected = 0;
        foreach ($events as $members) {
            $k = count($members);
            $projected += ($k * ($k - 1)) / 2;
        }

        $components = $this->components($subset, $events);
        $sizes = array_map('count', $components);
        rsort($sizes);
        $isolates = 0;
        foreach ($sizes as $size) {
            if ($size === 1) {
                $isolates++;
            }
        }

        // The glue: the event contributing the most neighbours.
        $glue = 0;
        $glueEvent = null;
        foreach ($events as $eventId => $members) {
            if (count($members) > $glue) {
                $glue = count($members);
                $glueEvent = $eventId;
            }
        }

        // Neighbours spanning more than one event, and of those, the
        // ones whose removal actually splits a component.
        $multi = array();
        foreach ($subset as $v => $eventIds) {
            if (count($eventIds) > 1) {
                $multi[] = $v;
            }
        }
        $bridges = $this->bridges($subset, $multi, count($components));

        $this->out(sprintf(
            '  cap %-5s nodes %d (1+%dE+%dN) · 2-mode edges %d vs'
                . ' projected %d · components %d%s · isolates %d ·'
                . ' glue %d/%d (%.0f%%, event %s) · multi-event %d ·'
                . ' bridges %s',
            $cap > 0 ? (string)$cap : 'all',
            1 + count($events) + $n,
            count($events),
            $n,
            $twoModeEdges,
            $projected,
            count($components),
            count($sizes) > 1
                ? ' (largest ' . $sizes[0] . ')'
                : '',
            $isolates,
            $glue,
            $n,
            $n > 0 ? ($glue / $n) * 100 : 0,
            $glueEvent === null ? '-' : $glueEvent,
            count($multi),
            $bridges === null ? 'skipped' : (string)$bridges
        ));
    }

    /**
     * Connected components over the neighbours alone.
     *
     * The centre is excluded on purpose: it touches every neighbour by
     * construction, so a graph including it is always one component and
     * the number would say nothing. What the rail's sentence wants to
     * report is whether the neighbours hang together once the value
     * they all share is taken out.
     */
    private function components(array $subset, array $events,
        array $without = array()
    ) {
        $parent = array();
        foreach (array_keys($subset) as $v) {
            if (!isset($without[$v])) {
                $parent[$v] = $v;
            }
        }
        foreach ($events as $members) {
            $prev = null;
            foreach ($members as $v) {
                if (isset($without[$v])) {
                    continue;
                }
                if ($prev !== null) {
                    $this->union($parent, $prev, $v);
                }
                $prev = $v;
            }
        }
        $out = array();
        foreach (array_keys($parent) as $v) {
            $out[$this->find($parent, $v)][] = $v;
        }
        return $out;
    }

    /**
     * How many of the multi-event neighbours are load-bearing: remove
     * one and the rest fall into more pieces than before.
     *
     * Bounded, because this is O(N) component passes and the whole
     * point of the uncapped row is that N can be five figures.
     */
    private function bridges(array $subset, array $multi, $baseline)
    {
        if (count($subset) > 400 || count($multi) > 120) {
            return null;
        }
        $found = 0;
        foreach ($multi as $v) {
            $events = array();
            foreach ($subset as $other => $eventIds) {
                if ($other === $v) {
                    continue;
                }
                foreach (array_keys($eventIds) as $eventId) {
                    $events[$eventId][] = $other;
                }
            }
            $after = $this->components($subset, $events,
                array($v => true));
            if (count($after) > $baseline) {
                $found++;
            }
        }
        return $found;
    }

    private function find(array &$parent, $v)
    {
        while ($parent[$v] !== $v) {
            $parent[$v] = $parent[$parent[$v]];
            $v = $parent[$v];
        }
        return $v;
    }

    private function union(array &$parent, $a, $b)
    {
        $ra = $this->find($parent, $a);
        $rb = $this->find($parent, $b);
        if ($ra !== $rb) {
            $parent[$rb] = $ra;
        }
    }
}
