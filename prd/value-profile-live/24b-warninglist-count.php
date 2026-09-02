<?php

/**
 * Scratch shell: what B5's warninglist read costs the co-occurrence
 * endpoint in queries, for `00-contract.md` §14.12's board.
 *
 * The row says `Q=16`, measured by phase 24 before the panel read a
 * warninglist. `Warninglist::attachWarninglistToAttributes` is
 * Redis-backed but falls back to SQL for a list whose entry set is not
 * cached, and `assignComments` issues one find of its own wherever
 * anything matched — so the number has to be re-measured warm *and*
 * cold rather than assumed unchanged.
 *
 * Counts SQL through the datasource's own log, the way
 * `24b-external-count.php` did, so a query issued inside the
 * Warninglist model counts the same as one this model issues itself.
 *
 * **Not part of the application.** Copy it in for the duration:
 *
 *   cp prd/value-profile-live/24b-warninglist-count.php \
 *      app/Console/Command/ValueWlCountShell.php
 *   app/Console/cake ValueWlCount run 1 8.8.8.8
 *   rm app/Console/Command/ValueWlCountShell.php
 */
class ValueWlCountShell extends AppShell
{
    public $uses = array('User', 'ValueProfile');

    /**
     * cake ValueWlCount run <userId> <value>
     */
    public function run()
    {
        $user = $this->User->getAuthUser((int)$this->args[0]);
        $value = $this->args[1];
        // Cold: the scan is re-read, so the warninglist check runs.
        $this->measure('scan re-read', $user, $value,
            array('fresh' => true));
        // Warm: the scan comes back from Redis with the check in it.
        $this->measure('scan from redis', $user, $value, array());
        $this->lookupOnly($user, $value);
        $this->dated($user, $value);
    }

    /**
     * `viewRelationDated`, which §14.12's board has no row for at all.
     * It folds the scan the co-occurrence endpoint reads, so cold means
     * *this endpoint missed the cache first*, not *it costs this on top
     * of the other*.
     */
    private function dated(array $user, $value)
    {
        foreach (array('fresh' => true, 'cached' => false) as $kind => $f) {
            $db = ConnectionManager::getDataSource('default');
            $db->fullDebug = true;
            $before = count($db->getLog(false, false)['log']);
            ClassRegistry::removeObject('ValueProfile');
            $profile = ClassRegistry::init('ValueProfile');
            $t = microtime(true);
            $out = $profile->forRelationDated($user, $value,
                $f ? array('fresh' => true) : array());
            $ms = (microtime(true) - $t) * 1000;
            $log = $db->getLog(false, false)['log'];
            $d = $out['relationships']['dated'];
            $this->out(sprintf(
                'dated %-8s Q=%-3d %7.1f ms   rows %d listed %d',
                $kind, count($log) - $before, $ms, count($d['rows']),
                $d['warninglists_listed']));
        }
    }

    /**
     * The lookup's own query cost, isolated — so the board's delta does
     * not have to be inferred from a total measured against different
     * data.
     */
    private function lookupOnly(array $user, $value)
    {
        App::uses('ValueWarninglistTool', 'Tools');
        $method = new ReflectionMethod('ValueProfile', 'relationScan');
        $method->setAccessible(true);
        $scan = $method->invoke($this->ValueProfile, $user, $value,
            array(), false);
        $probes = array();
        foreach ($scan['rows'] as $row) {
            $probes[] = array(
                'value' => $row['Attribute']['value'],
                'type' => $row['Attribute']['type'],
            );
        }
        $db = ConnectionManager::getDataSource('default');
        $db->fullDebug = true;
        foreach (array('first call', 'same process') as $kind) {
            $model = ClassRegistry::init('Warninglist');
            if ($kind === 'first call') {
                ClassRegistry::removeObject('Warninglist');
                $model = ClassRegistry::init('Warninglist');
            }
            $before = count($db->getLog(false, false)['log']);
            $t = microtime(true);
            $hits = ValueWarninglistTool::hitsFor($model, $probes);
            $ms = (microtime(true) - $t) * 1000;
            $log = $db->getLog(false, false)['log'];
            $queries = array_slice($log, $before);
            $this->out(sprintf(
                'lookup %-13s Q=%-3d %7.1f ms   %d of %d listed',
                $kind, count($queries), $ms, count($hits),
                count($probes)));
            foreach ($queries as $q) {
                $this->out('        ' . substr(
                    preg_replace('/\s+/', ' ', $q['query']), 0, 100));
            }
        }
    }

    private function measure($kind, array $user, $value, array $options)
    {
        $db = ConnectionManager::getDataSource('default');
        $db->fullDebug = true;
        $before = count($db->getLog(false, false)['log']);

        ClassRegistry::removeObject('ValueProfile');
        $profile = ClassRegistry::init('ValueProfile');
        $t = microtime(true);
        $out = $profile->forRelationCooccurrence($user, $value, $options);
        $ms = (microtime(true) - $t) * 1000;

        $log = $db->getLog(false, false)['log'];
        $queries = array_slice($log, $before);
        $co = $out['relationships']['cooccurrence'];
        $wl = 0;
        foreach ($queries as $q) {
            if (stripos($q['query'], 'warninglist') !== false) {
                $wl++;
            }
        }
        $this->out(sprintf(
            '%-16s Q=%-3d (warninglist %d) %7.1f ms   listed %d of %d',
            $kind, count($queries), $wl, $ms,
            $co['warninglists_listed'], $co['distinct_values']
        ));
        foreach ($queries as $index => $q) {
            $this->out(sprintf('   %2d  ', $index + 1) . substr(
                preg_replace('/\s+/', ' ', $q['query']), 0, 105));
        }
    }
}
