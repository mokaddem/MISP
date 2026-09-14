<?php

/**
 * Scratch shell: what the Overview's borrowed split costs.
 *
 * `00-contract.md` §14.12's rule is that a board row moves only when
 * its phase document records the same numbers. This phase touches one
 * row — `viewAnalystPreview` — and the claim it has to support is that
 * the row does **not** move: the split is drawn off `standing`, which
 * `analystContext` was already building for every caller and
 * `forAnalystPreview` was deleting on the way out.
 *
 * So this measures the endpoint's facade method and prints, per value,
 * the query count, the wall clock, and the two counts that must be
 * read together — how many opinions the card's *subtitle* names
 * (`analystCounts`, top-level items of any anchor) against how many the
 * *bar* is over (`analystStanding`, opinions at any depth rating the
 * value). They are not the same set, which is why the lead states its
 * own denominator, and a value where they differ is worth knowing
 * about.
 *
 * The model is re-initialised per measurement so a memoised read from a
 * previous value cannot be counted as a query this one did not run.
 *
 * Run:
 *   cp prd/value-profile-live/35-query-count.php \
 *      app/Console/Command/ValuePhase35CountShell.php
 *   app/Console/cake ValuePhase35Count run
 *   rm app/Console/Command/ValuePhase35CountShell.php
 */
class ValuePhase35CountShell extends AppShell
{
    public $uses = array('User');

    const CASES = array(
        // The flagship, and the only one of phase 31's five that
        // carries an opinion at all: four of them, all ADMIN's, 100 /
        // 100 / 80 / 10 — which is the split the bar draws.
        '8.8.8.8',
        // One opinion, written on the attribute itself rather than
        // reaching the value through an event. Every opinion disputes.
        '127.0.0.1',
        // The heaviest on the instance: 48,255 occurrences, and no
        // opinion anywhere near it. The bar is not drawn and the union
        // is still read, so this is where "no opinion" is priced.
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
        $this->out('== forAnalystPreview ==');
        foreach (self::CASES as $value) {
            $this->__measure($user, $value);
        }
        $this->out('');
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
        $out = $profile->forAnalystPreview($user, $value);
        $ms = (microtime(true) - $t) * 1000;
        $queries = count($db->getLog(false, false)['log']) - $before;

        $analyst = $out['analyst'];
        $standing = isset($analyst['standing'])
            ? $analyst['standing']
            : array('orgs' => array());
        $this->out(sprintf(
            '  %-16s Q=%-4d %8.1f ms   subtitle=%d opinions'
                . '   bar=%d opinions%s   preview=%d',
            $value,
            $queries,
            $ms,
            (int)$analyst['counts']['opinions'],
            count($standing['orgs']),
            (int)$analyst['counts']['opinions']
                === count($standing['orgs']) ? '' : '  <-- DIFFER',
            count($analyst['preview'])
        ));
    }
}
