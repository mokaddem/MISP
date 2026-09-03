<?php

/**
 * Scratch shell: what B9's tactic fold actually produces.
 *
 * Two things it answers that no amount of reading the code does. The
 * merged kill chain — whether pooling the ATT&CK-shaped galaxies' own
 * `kill_chain_order` really lands `reconnaissance` before
 * `initial-access`, which one galaxy's platform tabs cannot state — and
 * the fold over a real value's label rows.
 *
 * **Not part of the application**, same as the other probes here:
 * `00-contract.md` §14.8 declines to build test scaffolding into MISP.
 * To run it, copy it in for the duration:
 *
 *   cp prd/value-profile-live/24b-tactics-probe.php \
 *      app/Console/Command/ValueTacticsShell.php
 *   app/Console/cake ValueTactics chain
 *   app/Console/cake ValueTactics fold 8.8.8.8
 *   rm app/Console/Command/ValueTacticsShell.php
 */
class ValueTacticsShell extends AppShell
{
    public $uses = array('User', 'ValueProfile');

    /**
     * The merged chain, in order.
     *
     * @return void
     */
    public function chain()
    {
        $chain = $this->reflect('tacticChain', array());
        $this->out(sprintf('%d tactics in the chain', count($chain)));
        foreach ($chain as $token => $position) {
            $this->out(sprintf('%3d  %s', $position, $token));
        }
    }

    /**
     * The fold over one value.
     *
     * @return void
     */
    public function fold()
    {
        $value = $this->args[0];
        $user = $this->admin();
        $profile = $this->ValueProfile->forRelationThreats($user, $value,
            array('fresh' => true));
        $tactics = $profile['relationships']['tactics'];
        $this->out(sprintf(
            '%s: %d tactics over %d techniques'
                . ' (%d placed, %d unplaced, %d in more than one)',
            $value,
            $tactics['total'],
            $tactics['techniques'],
            $tactics['placed'],
            $tactics['unplaced'],
            $tactics['multi']
        ));
        foreach ($tactics['rows'] as $row) {
            $this->out(sprintf(
                '  %-26s %2d techniques · %2d events · %d orgs  [%s]',
                $row['name'],
                $row['techniques'],
                $row['events'],
                $row['orgs'],
                $row['position'] === null ? 'unplaced' : $row['position']
            ));
            $this->out('        ' . implode('; ', array_slice(
                $row['technique_names'], 0, 4
            )));
        }
        $threats = $profile['relationships']['threats'];
        $this->out(sprintf(
            '  (named threats beside it: %d)',
            $threats['total']
        ));
    }

    /**
     * What the two reads B9 added actually cost.
     *
     * The task was scoped believing the tactic was on the cluster row
     * and that this group would issue nothing. It is not, so the cost
     * is worth stating rather than assuming.
     *
     * @return void
     */
    public function cost()
    {
        $value = $this->args[0];
        $user = $this->admin();
        // Warm every lazy model load so the figures are the queries.
        $this->ValueProfile->forRelationThreats($user, $value);

        $started = microtime(true);
        $chain = $this->reflect('tacticChain', array());
        $chainMs = (microtime(true) - $started) * 1000;

        $clusters = $this->reflect('claimClusters', array(
            $user, array(), $this->tagNames($user, $value),
        ));
        $started = microtime(true);
        $tactics = $this->reflect('clusterTactics', array($clusters));
        $tacticsMs = (microtime(true) - $started) * 1000;

        $started = microtime(true);
        $this->ValueProfile->forRelationThreats($user, $value,
            array('fresh' => true));
        $digestMs = (microtime(true) - $started) * 1000;

        $this->out(sprintf(
            '%s: tacticChain %.1f ms (%d tactics),'
                . ' clusterTactics %.1f ms (%d of %d clusters),'
                . ' whole digest %.0f ms',
            $value,
            $chainMs,
            count($chain),
            $tacticsMs,
            count($tactics),
            count($clusters['by_tag']),
            $digestMs
        ));
    }

    /**
     * The galaxy tag names on a value's events, so `cost` can resolve
     * the same cluster set the scan does.
     *
     * @param array $user
     * @param string $value
     * @return array
     */
    private function tagNames(array $user, $value)
    {
        $rows = ClassRegistry::init('GalaxyCluster')->find('all', array(
            'recursive' => -1,
            'fields' => array('GalaxyCluster.tag_name'),
            'conditions' => array(
                'GalaxyCluster.tag_name IN (
                     SELECT t.name FROM tags t
                     JOIN event_tags et ON et.tag_id = t.id
                     JOIN attributes a ON a.event_id = et.event_id
                     WHERE a.value1 = ? AND a.deleted = 0
                       AND t.is_galaxy = 1)' => $value,
            ),
        ));
        $names = array();
        foreach ($rows as $row) {
            $names[] = $row['GalaxyCluster']['tag_name'];
        }
        return $names;
    }

    /**
     * @param string $method
     * @param array $args
     * @return mixed
     */
    private function reflect($method, array $args)
    {
        $reflection = new ReflectionMethod('ValueProfile', $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->ValueProfile, $args);
    }

    /**
     * @return array
     */
    private function admin()
    {
        $user = $this->User->getAuthUser(1);
        if (empty($user)) {
            $this->error('No user 1 on this instance.');
        }
        /*
         * `AnalystData::afterFind` reaches for the current user to rule
         * on a claim's sharing group, and there is no session here to
         * hold one. Without this the asserted read fatals before the
         * fold under test ever runs.
         */
        Configure::write('CurrentUserId', $user['id']);
        return $user;
    }
}
