<?php

/**
 * Scratch shell: which of the Assessment tab's drawn objects are
 * already sitting in the Overview's hands.
 *
 * §5 of `35-overview-borrowed.md` is a list of candidates for the
 * treatment this phase gave the tug-bar, and the criterion that sorts
 * it is not taste: a candidate is *free* when the panel that would
 * draw it is already fetching the array it needs and throwing it away.
 * That is exactly what the split turned out to be, and the question is
 * what else is.
 *
 * `viewVerdictCard` calls `forVerdict` **without** `with_opinions`, so
 * this does too — measuring what the Overview's own rail card holds,
 * not what the Assessment tab's does. Every key printed here is one
 * `value_verdict_card.ctp` never reads.
 *
 * The count beside each key is what the key holds, so a key that is
 * technically present and always empty cannot pass for a candidate.
 *
 * Run:
 *   cp prd/value-profile-live/35-free-keys.php \
 *      app/Console/Command/ValuePhase35KeysShell.php
 *   app/Console/cake ValuePhase35Keys run
 *   rm app/Console/Command/ValuePhase35KeysShell.php
 */
class ValuePhase35KeysShell extends AppShell
{
    public $uses = array('User');

    const CASES = array(
        '8.8.8.8',
        '127.0.0.1',
        '443',
        '0.0.0.0',
        'sage.png',
        '1.162.239.42',
    );

    /*
     * What `value_verdict_card.ctp` reads, so the report can say what
     * is left over rather than what exists.
     */
    const READ_BY_THE_CARD = array(
        'ledger', 'band', 'lean', 'quality', 'profile', 'profile_id',
        'summary',
    );

    const CANDIDATES = array(
        'stances', 'lean_ledger', 'lean_weight', 'composition', 'tug',
        'curves', 'cases', 'changer_actions', 'orgs', 'warninglist',
        'conflicts', 'ambiguities', 'resolutions', 'opinions',
        'changers', 'curves_span', 'curves_note', 'composition_note',
    );

    public function run()
    {
        $user = $this->User->getAuthUser(
            $this->User->field('id',
                array('User.email' => 'admin@admin.test'))
        );
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
        $out = $profile->forVerdict($user, $value);
        $ms = (microtime(true) - $t) * 1000;
        $queries = count($db->getLog(false, false)['log']) - $before;

        $verdict = $out['verdict'];
        $bits = array();
        foreach (self::CANDIDATES as $key) {
            if (!array_key_exists($key, $verdict)) {
                $bits[] = $key . '=absent';
                continue;
            }
            $held = $verdict[$key];
            if ($held === null) {
                $bits[] = $key . '=null';
            } elseif (is_array($held)) {
                $bits[] = $key . '=' . count($held);
            } else {
                $bits[] = $key . '=' . (is_scalar($held)
                    ? (string)$held : 'set');
            }
        }
        $this->out(sprintf('== %s  Q=%d  %.1f ms  lean=%s ==',
            $value, $queries, $ms, $verdict['lean']));
        $this->out('   ' . implode('  ', $bits));
    }
}
