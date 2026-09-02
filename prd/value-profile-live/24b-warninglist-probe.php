<?php

/**
 * Scratch shell: measure what a warninglist check over the co-occurrence
 * neighbourhood costs, so B5 can choose between a page-local check
 * (<= RELATION_ROW_CAP rows) and a fold-wide one (every distinct
 * neighbour), which `24b-relationships.md` §7 makes a precondition.
 *
 * One scenario per invocation, because the model's `entriesCache` and
 * the Redis value cache both warm on first use: two readings in one
 * process measure the second one against caches the first one built.
 *
 *   cake ValueWarninglistProbe lists
 *   cake ValueWarninglistProbe <value> carried|fold [redis|nocache]
 *   cake ValueWarninglistProbe <value> hits
 *
 * Not part of the application. Deleted after the run.
 */
class ValueWarninglistProbeShell extends AppShell
{
    public $uses = array('Warninglist', 'User', 'ValueProfile');

    public function main()
    {
        $mode = $this->args[0] ?? 'lists';
        if ($mode === 'lists') {
            $this->lists();
            return;
        }
        $value = $mode;
        $scope = $this->args[1] ?? 'carried';
        $how = $this->args[2] ?? 'redis';
        $user = $this->User->getAuthUser(1);
        $pairs = $scope === 'fold'
            ? $this->allCandidates($user, $value)
            : $this->carried($user, $value);
        if ($scope === 'hits' || $how === 'hits') {
            $this->hits($pairs);
            return;
        }
        $this->time($value, $scope, $how, $pairs);
    }

    private function lists()
    {
        $this->out('MISP.warning_for_all: '
            . var_export(Configure::read('MISP.warning_for_all'), true));
        $t = microtime(true);
        $enabled = $this->Warninglist->getEnabled();
        $this->out(sprintf('getEnabled: %d lists in %.1f ms',
            count($enabled), (microtime(true) - $t) * 1000));
        $byType = array();
        foreach ($enabled as $list) {
            $w = $list['Warninglist'];
            $byType[$w['type']][] = $w['name'];
        }
        foreach ($byType as $type => $names) {
            $this->out('  ' . $type . ': ' . count($names));
        }
        $this->out('installed: ' . $this->Warninglist->find('count'));
        /*
         * The entry sets themselves, because building them is the
         * one-off a first check pays and a `cidr` list pays most.
         */
        $t = microtime(true);
        $entries = 0;
        foreach ($enabled as $list) {
            $set = $this->Warninglist->getFilteredEntries($list);
            $entries += is_array($set) ? count($set) : 1;
        }
        $this->out(sprintf('getFilteredEntries (all lists): %.1f ms',
            (microtime(true) - $t) * 1000));
        $this->out('array entries held: ' . $entries);
    }

    private function carried(array $user, $value)
    {
        $co = $this->ValueProfile->forRelationCooccurrence($user, $value);
        $co = $co['relationships']['cooccurrence'];
        $this->out('distinct ' . $co['distinct_values']);
        return $this->pairs($co['rollups']['value']['rows']);
    }

    /**
     * Every distinct neighbour the scan saw, with the type the fold
     * would put on its badge — the row set a fold-wide check has to
     * cover, rebuilt because the fold only returns the top hundred.
     */
    private function allCandidates(array $user, $value)
    {
        $method = new ReflectionMethod('ValueProfile', 'relationScan');
        $method->setAccessible(true);
        $scan = $method->invoke($this->ValueProfile, $user, $value,
            array(), false);
        $types = array();
        foreach ($scan['rows'] as $row) {
            $v = $row['Attribute']['value'] ?? '';
            if ($v === '') {
                continue;
            }
            $type = $row['Attribute']['type'];
            $types[$v][$type] = ($types[$v][$type] ?? 0) + 1;
        }
        $out = array();
        foreach ($types as $v => $counts) {
            arsort($counts);
            $out[] = array(
                'value' => $v,
                'type' => key($counts),
                'to_ids' => 1,
            );
        }
        return $out;
    }

    private function pairs(array $rows)
    {
        $out = array();
        foreach ($rows as $row) {
            $out[] = array(
                'value' => $row['value'],
                'type' => $row['type'],
                'to_ids' => 1,
            );
        }
        return $out;
    }

    private function time($value, $scope, $how, array $pairs)
    {
        if (empty($pairs)) {
            $this->out($value . ' ' . $scope . ': no rows');
            return;
        }
        $t = microtime(true);
        if ($how === 'nocache') {
            $enabled = $this->Warninglist->getEnabled();
            $hits = 0;
            foreach ($pairs as $pair) {
                $checked = $this->Warninglist->checkForWarning($pair,
                    $enabled);
                if (!empty($checked['warnings'])) {
                    $hits++;
                }
            }
        } else {
            $this->Warninglist->attachWarninglistToAttributes($pairs);
            $hits = 0;
            foreach ($pairs as $pair) {
                if (!empty($pair['warnings'])) {
                    $hits++;
                }
            }
        }
        $ms = (microtime(true) - $t) * 1000;
        $this->out(sprintf('%s %s %s: n=%d hits=%d %.1f ms',
            $value, $scope, $how, count($pairs), $hits, $ms));
    }

    private function hits(array $pairs)
    {
        $this->Warninglist->attachWarninglistToAttributes($pairs);
        $listed = 0;
        foreach ($pairs as $index => $attribute) {
            if (empty($attribute['warnings'])) {
                continue;
            }
            $listed++;
            $names = array();
            foreach ($attribute['warnings'] as $warning) {
                $names[$warning['warninglist_name'] . ' ('
                    . $warning['warninglist_category'] . ' · '
                    . $warning['match'] . ')'] = true;
            }
            $this->out(sprintf('%3d %-14s %-46s %s',
                $index,
                $attribute['type'],
                mb_substr($attribute['value'], 0, 46),
                implode(' | ', array_keys($names))));
        }
        $this->out('listed ' . $listed . ' of ' . count($pairs));
    }
}
