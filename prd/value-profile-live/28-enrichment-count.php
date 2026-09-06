<?php

/**
 * Scratch shell: the query count behind `forEnrichment` and
 * `forEnrichmentRun`, for `00-contract.md` §14.12's board.
 *
 * The board's rule is that a row moves off `—` only when its phase
 * document records the same numbers, so these are measured rather
 * than reasoned about.
 *
 * **SQL is the smaller half of what this tab spends**, and the board's
 * `Q` column cannot say so. Both endpoints make an outbound HTTP call
 * that no datasource log sees — one `GET /modules` for the catalogue,
 * and the run's `POST /query` on top of it. So this reports both:
 * `Q` for the board, and wall time for the truth. `viewExternal` set
 * the precedent for a row whose cost is not its query count.
 *
 * **Not part of the application.** Copy it in for the duration:
 *
 *   cp prd/value-profile-live/28-enrichment-count.php \
 *      app/Console/Command/ValueEnrichCountShell.php
 *   app/Console/cake ValueEnrichCount run 1
 *   rm app/Console/Command/ValueEnrichCountShell.php
 */
class ValueEnrichCountShell extends AppShell
{
    public $uses = array('User', 'ValueProfile');

    /**
     * Values spanning the shapes the board cares about: several types,
     * one type, and a value the reader holds nothing of.
     */
    const CASES = array(
        array('8.8.8.8', 'mmdb_lookup', 'ip-dst'),
        array('github.com', 'whois', 'domain'),
        array('f1d3ff8443297732862df21dc4e57262', 'hashlookup', 'md5'),
        array('45.155.205.233', 'mmdb_lookup', 'ip-dst|port'),
        array('no-such-value-anywhere.invalid', 'mmdb_lookup', 'ip-dst'),
    );

    /**
     * cake ValueEnrichCount run <userId>
     */
    public function run()
    {
        $user = $this->User->getAuthUser((int)$this->args[0]);
        if (empty($user)) {
            $this->out('no such user');
            return;
        }
        foreach (self::CASES as $case) {
            $this->measure('forEnrichment', $user, $case[0], array());
        }
        $this->out('');
        foreach (self::CASES as $case) {
            $this->measure('forEnrichmentRun', $user, $case[0], array(
                'module' => $case[1],
                'type' => $case[2],
            ));
        }
    }

    private function measure($method, array $user, $value,
        array $options
    ) {
        $db = ConnectionManager::getDataSource('default');
        $db->fullDebug = true;
        $before = count($db->getLog(false, false)['log']);

        ClassRegistry::removeObject('ValueProfile');
        $profile = ClassRegistry::init('ValueProfile');
        $t = microtime(true);
        $out = $profile->$method($user, $value, $options);
        $ms = (microtime(true) - $t) * 1000;

        $log = $db->getLog(false, false)['log'];
        $queries = array_slice($log, $before);

        if ($method === 'forEnrichment') {
            $tail = sprintf(
                'types=%d eligible=%d enabled=%d',
                count($out['enrichment']['types']),
                count($out['enrichment']['modules']),
                $out['enrichment']['enabled']
            );
        } else {
            $tail = sprintf(
                'state=%-11s shown=%d of %d',
                $out['run']['state'],
                $out['run']['shown'],
                $out['run']['total']
            );
        }

        $this->out(sprintf(
            '%-17s %-34s Q=%-3d %8.1f ms   %s',
            $method,
            $value,
            count($queries),
            $ms,
            $tail
        ));
        foreach ($queries as $q) {
            $this->out('        ' . substr(
                preg_replace('/\s+/', ' ', $q['query']), 0, 110));
        }
    }
}
