<?php

/**
 * Scratch shell: the query count behind `forExternal` and
 * `forRelationExternal`, for `00-contract.md` §14.12's board.
 *
 * The board's `viewExternal` row has carried a blank `Q` since phase 24
 * built the card, with a note that whoever next touches `forExternal`
 * records it. B3 touched it.
 *
 * Counts SQL through the datasource's own log, so a query issued deep
 * inside `Feed::searchCaches` is counted the same as one this model
 * issues itself. Redis work is not SQL and is reported separately by
 * count of calls, because it is what this endpoint actually spends its
 * time on.
 *
 * **Not part of the application.** Copy it in for the duration:
 *
 *   cp prd/value-profile-live/24b-external-count.php \
 *      app/Console/Command/ValueExtCountShell.php
 *   app/Console/cake ValueExtCount run 1 24576
 *   rm app/Console/Command/ValueExtCountShell.php
 */
class ValueExtCountShell extends AppShell
{
    public $uses = array('User', 'ValueProfile');

    /**
     * cake ValueExtCount run <userId> <value>
     */
    public function run()
    {
        $user = $this->User->getAuthUser((int)$this->args[0]);
        $hit = $this->args[1];
        $miss = isset($this->args[2]) ? $this->args[2] : 'dns.google';

        foreach (array('forExternal', 'forRelationExternal') as $method) {
            foreach (array('hit' => $hit, 'miss' => $miss) as $kind => $v) {
                $this->measure($method, $kind, $user, $v);
            }
        }
    }

    private function measure($method, $kind, array $user, $value)
    {
        $db = ConnectionManager::getDataSource('default');
        $db->fullDebug = true;
        $before = count($db->getLog(false, false)['log']);

        ClassRegistry::removeObject('ValueProfile');
        $profile = ClassRegistry::init('ValueProfile');
        $t = microtime(true);
        $out = $profile->$method($user, $value);
        $ms = (microtime(true) - $t) * 1000;

        $log = $db->getLog(false, false)['log'];
        $queries = array_slice($log, $before);
        $ext = $out['external'];
        $this->out(sprintf(
            '%-22s %-5s %-12s Q=%-3d %6.1f ms   sources=%d events=%d',
            $method, $kind, $value, count($queries), $ms,
            count($ext['sources']), $ext['events']
        ));
        foreach ($queries as $q) {
            $this->out('        ' . substr(
                preg_replace('/\s+/', ' ', $q['query']), 0, 120));
        }
    }
}
