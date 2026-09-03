<?php

/**
 * Scratch shell: what does the ssdeep engine's candidate cap cost, and
 * what does it hide?
 *
 * `24b-relationships.md` §4.2 found `ValueProfile::ssdeepEngine`
 * fetching `RELATION_ROW_CAP` candidates — 100, newest first — and then
 * comparing against those, while the panel says it compared *every*
 * `ssdeep` attribute the viewer can see. B11 has to choose between
 * raising the set to the type's whole population and keeping a bound
 * that says so, and that is a cost question before it is a wording one.
 *
 *   cake ValueSsdeepCapProbe truth <value>        full population
 *   cake ValueSsdeepCapProbe capped <value> <n>   as the engine runs it
 *   cake ValueSsdeepCapProbe sweep <n>            n real ssdeep values
 *   cake ValueSsdeepCapProbe population
 *
 * Not part of the application. Deleted after the run.
 */
class ValueSsdeepCapProbeShell extends AppShell
{
    public $uses = array('User', 'Value', 'MispAttribute', 'ValueProfile');

    public function main()
    {
        $mode = $this->args[0] ?? 'population';
        $user = $this->User->getAuthUser(1);
        if ($mode === 'population') {
            $this->population($user);
        } elseif ($mode === 'truth') {
            $this->compare($user, $this->args[1], 0);
        } elseif ($mode === 'capped') {
            $this->compare($user, $this->args[1],
                (int)($this->args[2] ?? 100));
        } elseif ($mode === 'lean') {
            $this->lean($user, $this->args[1] ?? 'x');
        } elseif ($mode === 'matrix') {
            $this->matrix($user);
        } elseif ($mode === 'sweep') {
            $this->sweep($user, (int)($this->args[1] ?? 10));
        } else {
            $this->out('unknown mode');
        }
    }

    private function population(array $user)
    {
        $t = microtime(true);
        $rows = $this->Value->occurrencesOfType($user, 'ssdeep', '', 100000);
        $ms = (microtime(true) - $t) * 1000;
        $this->out(sprintf('visible ssdeep attributes: %d', count($rows)));
        $this->out(sprintf('fetch (uncapped):           %.1f ms', $ms));
        $distinct = array();
        foreach ($rows as $row) {
            $distinct[$row['Attribute']['value']] = true;
        }
        $this->out(sprintf('distinct values:            %d',
            count($distinct)));
        $this->out(sprintf('ssdeep_fuzzy_compare loaded: %s',
            function_exists('ssdeep_fuzzy_compare') ? 'yes' : 'no'));
    }

    /**
     * @param array $user
     * @param string $value
     * @param int $cap 0 for the whole population
     */
    private function compare(array $user, $value, $cap)
    {
        $threshold = 40;
        $limit = $cap === 0 ? 100000 : $cap;
        $t0 = microtime(true);
        $rows = $this->Value->occurrencesOfType(
            $user, 'ssdeep', $value, $limit
        );
        $t1 = microtime(true);
        $hits = array();
        foreach ($rows as $row) {
            $score = @ssdeep_fuzzy_compare(
                $value, $row['Attribute']['value']
            );
            if ($score === false || $score < $threshold) {
                continue;
            }
            $hits[] = $score;
        }
        $t2 = microtime(true);
        rsort($hits);
        $this->out(sprintf('%s%s',
            $cap === 0 ? 'FULL  ' : 'CAP ' . $cap . ' ',
            substr($value, 0, 40)));
        $this->out(sprintf('  candidates: %d', count($rows)));
        $this->out(sprintf('  fetch:      %8.1f ms', ($t1 - $t0) * 1000));
        $this->out(sprintf('  compare:    %8.1f ms', ($t2 - $t1) * 1000));
        $this->out(sprintf('  over %d:    %d  %s', $threshold, count($hits),
            $hits ? '(best ' . $hits[0] . ')' : ''));
    }

    /**
     * The same candidate set fetched two ways: as the engine does it
     * (eight fields plus the Event/Object context every row needs) and
     * as a comparison actually needs it (the value, and nothing else).
     * Only the survivors need context, and there are never many.
     */
    private function lean(array $user, $value)
    {
        $t0 = microtime(true);
        $rich = $this->Value->occurrencesOfType(
            $user, 'ssdeep', $value, 100000
        );
        $t1 = microtime(true);
        $lean = $this->MispAttribute->fetchAttributesSimple($user, array(
            'conditions' => array(
                'Attribute.type' => 'ssdeep',
                'Attribute.deleted' => 0,
            ),
            'fields' => array('Attribute.id', 'Attribute.value'),
            'order' => false,
            'limit' => 100000,
        ));
        $t2 = microtime(true);
        $this->out(sprintf('full context : %6.1f ms, %d rows',
            ($t1 - $t0) * 1000, count($rich)));
        $this->out(sprintf('values only  : %6.1f ms, %d rows',
            ($t2 - $t1) * 1000, count($lean)));
    }

    /**
     * Every distinct visible ssdeep value against every other, once,
     * so the question *what would raising the cap actually surface?*
     * has an answer for the whole instance rather than for a sample.
     */
    private function matrix(array $user)
    {
        $rows = $this->Value->occurrencesOfType(
            $user, 'ssdeep', 'x', 100000
        );
        $values = array();
        foreach ($rows as $row) {
            $values[$row['Attribute']['value']] = true;
        }
        $values = array_keys($values);
        $n = count($values);
        $this->out(sprintf('%d distinct visible ssdeep values', $n));
        $t = microtime(true);
        $pairs = array();
        $partners = array_fill(0, $n, 0);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $score = @ssdeep_fuzzy_compare($values[$i], $values[$j]);
                if ($score !== false && $score >= 40) {
                    $partners[$i]++;
                    $partners[$j]++;
                    $pairs[] = array($score, $values[$i], $values[$j]);
                }
            }
        }
        $ms = (microtime(true) - $t) * 1000;
        $this->out(sprintf('all-pairs compare: %.0f ms for %d comparisons',
            $ms, $n * ($n - 1) / 2));
        $this->out(sprintf('pairs over 40: %d', count($pairs)));
        rsort($partners);
        $withAny = 0;
        foreach ($partners as $p) {
            if ($p > 0) {
                $withAny++;
            }
        }
        $this->out(sprintf('values with at least one partner: %d (%.1f%%)',
            $withAny, 100 * $withAny / max($n, 1)));
        $this->out(sprintf('most partners on one value: %d', $partners[0]));
        usort($pairs, function ($a, $b) {
            return $b[0] - $a[0];
        });
        foreach (array_slice($pairs, 0, 8) as $p) {
            $this->out(sprintf('  %3d  %s  ~  %s', $p[0],
                substr($p[1], 0, 28), substr($p[2], 0, 28)));
        }
    }

    private function sweep(array $user, $n)
    {
        $rows = $this->MispAttribute->find('all', array(
            'conditions' => array(
                'Attribute.type' => 'ssdeep',
                'Attribute.deleted' => 0,
            ),
            'fields' => array('DISTINCT Attribute.value1'),
            'order' => false,
            'limit' => $n,
            'recursive' => -1,
        ));
        $this->out(sprintf('%-42s %7s %7s %6s %6s',
            'value', 'cap100', 'full', 'hid', 'ms'));
        foreach ($rows as $row) {
            $v = $row['Attribute']['value1'];
            $capped = $this->countOver($user, $v, 100);
            $t = microtime(true);
            $full = $this->countOver($user, $v, 100000);
            $ms = (microtime(true) - $t) * 1000;
            $this->out(sprintf('%-42s %7d %7d %6d %6.0f',
                substr($v, 0, 42), $capped, $full, $full - $capped, $ms));
        }
    }

    /**
     * @return int Pairs over the threshold within a candidate limit
     */
    private function countOver(array $user, $value, $limit)
    {
        $rows = $this->Value->occurrencesOfType(
            $user, 'ssdeep', $value, $limit
        );
        $n = 0;
        foreach ($rows as $row) {
            $score = @ssdeep_fuzzy_compare(
                $value, $row['Attribute']['value']
            );
            if ($score !== false && $score >= 40) {
                $n++;
            }
        }
        return $n;
    }
}
