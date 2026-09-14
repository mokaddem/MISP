<?php

/**
 * Scratch shell: what phase 31's panels cost.
 *
 * `00-contract.md` §14.12 sets the rule this exists to satisfy — a row
 * moves off `—` only when its phase document records the same numbers —
 * and this phase touches two rows: **`viewReporting` is new** and
 * **`viewOccurrences` had its cap cut from 25 to 8**, which changes
 * nothing about its query count and everything about what the four
 * attachments behind it are attaching to. Both are measured, so the
 * board can carry the pair rather than one measured row beside one
 * assumed one.
 *
 * The model is re-initialised per measurement so a memoised read from a
 * previous value cannot be counted as a query this one did not run.
 *
 * Run:
 *   cp prd/value-profile-live/31-query-count.php \
 *      app/Console/Command/ValuePhase31CountShell.php
 *   app/Console/cake ValuePhase31Count run
 *   rm app/Console/Command/ValuePhase31CountShell.php
 */
class ValuePhase31CountShell extends AppShell
{
    public $uses = array('User');

    const CASES = array(
        // The flagship: 26 occurrences, 8 organisations, 52 months.
        '8.8.8.8',
        // The heaviest on the instance: 48,255 occurrences, 12
        // organisations, 112 months. Both reads here are grouped
        // aggregates, so this is where that claim is tested.
        '443',
        // Second-heaviest, and a different shape — one organisation
        // holds 33,099 of its 33,110.
        '0.0.0.0',
        // One occurrence, one organisation, one month: the short-span
        // branch the strip had to be capped for.
        'sage.png',
        // One occurrence, and nothing else at all.
        '1.162.239.42',
    );

    public function run()
    {
        $user = $this->User->getAuthUser(
            $this->User->field('id',
                array('User.email' => 'admin@admin.test'))
        );
        foreach (array('forReporting', 'forOccurrences') as $method) {
            $this->out(sprintf('== %s ==', $method));
            foreach (self::CASES as $value) {
                $this->__measure($method, $user, $value);
            }
            $this->out('');
        }
    }

    private function __measure($method, array $user, $value)
    {
        $db = ConnectionManager::getDataSource('default');
        $db->fullDebug = true;
        $before = count($db->getLog(false, false)['log']);

        ClassRegistry::removeObject('ValueProfile');
        $profile = ClassRegistry::init('ValueProfile');
        Configure::write('CurrentUserId', $user['id']);

        $t = microtime(true);
        $out = $profile->$method($user, $value);
        $ms = (microtime(true) - $t) * 1000;
        $queries = count($db->getLog(false, false)['log']) - $before;

        if ($method === 'forReporting') {
            $r = $out['reporting'];
            $note = sprintf(
                'orgs=%d/%d months=%d live=%d',
                count($r['orgs']),
                $r['orgs_total'],
                count($r['months']),
                $r['occurrences']
            );
        } else {
            $note = sprintf(
                'rows=%d of %d',
                count($out['occurrences']),
                $out['occurrence_stats']['total']
            );
        }
        $this->out(sprintf(
            '  %-16s Q=%-4d %8.1f ms   %s',
            $value,
            $queries,
            $ms,
            $note
        ));
    }
}
