<?php

/**
 * Scratch shell: what the two amended endpoints now cost.
 *
 * `00-contract.md` §14.12 sets the rule this exists to satisfy — *a row
 * moves off `—` only when its phase document records the same numbers*
 * — and §14.12's closing paragraph names this exact situation: two of
 * the three concepts extend `viewOccurrenceTable`, a row phase 22 has
 * already filled, so "whoever gets there first: amend the row, and
 * record the new numbers in your own document".
 *
 * Two methods, because this session amended two endpoints:
 * `forOccurrenceTable` gains the standalone-proposal fetch, and
 * `forAnalystPreview` gains the event-report count.
 *
 * The model is re-initialised per measurement so a memoised read from a
 * previous value cannot be counted as a query this one did not run.
 *
 * Run:
 *   cp prd/value-profile-live/22b-query-count.php \
 *      app/Console/Command/ValueQueryCountShell.php
 *   app/Console/cake ValueQueryCount run
 *   rm app/Console/Command/ValueQueryCountShell.php
 */
class ValueQueryCountShell extends AppShell
{
    public $uses = array('User');

    const CASES = array(
        // No occurrence, one standalone proposal: the fetch runs and
        // the table's own reads find nothing.
        '123.123.123.1',
        // An occurrence and a standalone proposal.
        '123.43.32.21',
        // An occurrence and two withdrawn standalone proposals.
        'tinyurl.com',
        // Proposals, but none standalone: the fetch runs and returns
        // nothing, which is the cost paid on every ordinary value.
        '8.8.8.8',
        // The heaviest.
        '443',
    );

    public function run()
    {
        $user = $this->User->getAuthUser(
            $this->User->field('id',
                array('User.email' => 'admin@admin.test'))
        );
        foreach (array('forOccurrenceTable', 'forAnalystPreview')
                 as $method) {
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

        $note = '';
        if ($method === 'forOccurrenceTable') {
            $block = $out['standalone_proposals'];
            $note = sprintf(
                'rows=%d proposals=%s',
                count($out['occurrences']),
                $block === null ? 'none' : $block['total']
            );
        } else {
            $note = sprintf(
                'reports=%d',
                (int)$out['analyst']['counts']['reports']
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
