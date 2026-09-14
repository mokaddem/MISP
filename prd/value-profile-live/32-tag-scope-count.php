<?php

/**
 * Scratch shell: what the event-tag scope costs.
 *
 * Three endpoints gained a read on 2026-09-14 and `00-contract.md`
 * §14.12 wants the numbers before their rows move:
 *
 *   - `viewContext` — two `eventTagsFor` calls beside its two
 *     `topTagsFor` ones
 *   - `viewOccurrences` and `viewOccurrenceTable` — one `EventTag`
 *     fetch for every event on the page, which is the attach the Tags
 *     column needs
 *
 * The model is re-initialised per measurement so a memoised read from a
 * previous value cannot be counted as a query this one did not run.
 *
 * Run:
 *   cp prd/value-profile-live/32-tag-scope-count.php \
 *      app/Console/Command/ValueTagScopeCountShell.php
 *   app/Console/cake ValueTagScopeCount run
 *   rm app/Console/Command/ValueTagScopeCountShell.php
 */
class ValueTagScopeCountShell extends AppShell
{
    public $uses = array('User');

    const CASES = array(
        // The flagship: 7 attribute tags, 48 event tags, 20 events.
        '8.8.8.8',
        // The heaviest: 1,844 events, 3,999 event-tag rows, 246
        // distinct event tags against 3,860 distinct attribute tags.
        '443',
        '0.0.0.0',
        'sage.png',
        '1.162.239.42',
    );

    public function run()
    {
        $user = $this->User->getAuthUser(
            $this->User->field('id',
                array('User.email' => 'admin@admin.test'))
        );
        foreach (array('forContext', 'forOccurrences',
                 'forOccurrenceTable') as $method) {
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

        if ($method === 'forContext') {
            $note = sprintf(
                'tax=%d gal=%d  eventTax=%d eventGal=%d',
                count($out['tags']),
                count($out['galaxies']),
                count($out['event_tags']),
                count($out['event_galaxies'])
            );
        } else {
            $withEventTags = 0;
            $eventTagRows = 0;
            foreach ($out['occurrences'] as $row) {
                if (!empty($row['EventTag'])) {
                    $withEventTags++;
                    $eventTagRows += count($row['EventTag']);
                }
            }
            $note = sprintf(
                'rows=%d  rows with event tags=%d  attached=%d',
                count($out['occurrences']),
                $withEventTags,
                $eventTagRows
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
