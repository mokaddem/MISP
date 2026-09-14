<?php

/**
 * Scratch shell: what the Collaboration tab's fourth panel costs.
 *
 * `00-contract.md` §14.12 sets the rule this exists to satisfy — a row
 * moves off `—` only when its phase document records the same numbers —
 * and this phase adds one: **`viewAnalystComments` is new**.
 *
 * Two aggregates, and the point of measuring is that neither returns
 * rows in proportion to the occurrences it reads. `94.98.224.81` holds
 * one sentence on 1,459 occurrences; `193.161.193.99` holds 33 on 336;
 * `flood` holds none on 65,717. All three are here because the grouped
 * read's whole argument is that the table's length is not the value's.
 *
 * The model is re-initialised per measurement so a memoised read from a
 * previous value cannot be counted as a query this one did not run.
 *
 * Run:
 *   cp prd/value-profile-live/34-comments-count.php \
 *      app/Console/Command/ValuePhase34CountShell.php
 *   app/Console/cake ValuePhase34Count run
 *   rm app/Console/Command/ValuePhase34CountShell.php
 */
class ValuePhase34CountShell extends AppShell
{
    public $uses = array('User');

    const CASES = array(
        // The flagship: 26 occurrences, 3 of them commented.
        '8.8.8.8',
        // 39 commented occurrences carrying 11 sentences — the shape
        // the grouping exists for, small enough to read by eye.
        '147.185.221.29',
        // The most sentences on the instance: 33 over 336 occurrences
        // in 203 events.
        '193.161.193.99',
        // The most repetition: one sentence on 1,459 occurrences across
        // five events. One row, and the count in it is the fact.
        '94.98.224.81',
        // 65,717 occurrences and not one comment — the scan with
        // nothing to group and nothing to draw.
        'flood',
        // A 995-character comment on a single occurrence.
        'a2c9afd6adac242827adb00d76c20c491b2d2247',
    );

    public function run()
    {
        $user = $this->User->getAuthUser(
            $this->User->field('id',
                array('User.email' => 'admin@admin.test'))
        );
        $this->out('== forAnalystComments ==');
        foreach (self::CASES as $value) {
            $this->__measure($user, $value);
        }
    }

    private function __measure(array $user, $value)
    {
        $db = ConnectionManager::getDataSource('default');
        $db->fullDebug = true;
        $before = count($db->getLog(false, false)['log']);

        ClassRegistry::removeObject('ValueProfile');
        $profile = ClassRegistry::init('ValueProfile');
        Configure::write('CurrentUserId', $user['id']);

        $t = microtime(true);
        $out = $profile->forAnalystComments($user, $value);
        $ms = (microtime(true) - $t) * 1000;
        $queries = count($db->getLog(false, false)['log']) - $before;

        $c = $out['analyst_comments'];
        $longest = 0;
        $widest = 0;
        foreach ($c['rows'] as $row) {
            $longest = max($longest, mb_strlen($row['comment']));
            $widest = max($widest, $row['occurrences']);
        }
        $this->out(sprintf(
            '  %-42s Q=%-3d %8.1f ms   rows=%-3d of %-3d  on %d'
                . ' occurrences in %d events  widest=%d  longest=%d'
                . '%s',
            mb_substr($value, 0, 42),
            $queries,
            $ms,
            count($c['rows']),
            $c['total'],
            $c['occurrences'],
            $c['events'],
            $widest,
            $longest,
            $c['capped'] ? '  CAPPED' : ''
        ));
    }
}
