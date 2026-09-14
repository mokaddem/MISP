<?php

/**
 * Does the sighting count agree with MISP, under every policy?
 *
 * `Value::sightingCountsFor` re-expresses `Plugin.Sightings_policy` as
 * SQL so that a value's sightings can be counted without materialising
 * its attribute ids. That is a rewrite of an **access rule**, and the
 * failure mode of getting it wrong is showing one organisation another
 * organisation's reports — the worst defect this page could carry. So
 * it is not verified by reading it; it is verified against the answer
 * MISP itself gives.
 *
 * The reference is `Sighting::listSightings`, which applies the policy
 * the way MISP has always applied it: in PHP, over fetched attribute
 * rows, through `createConditionsByAttributes`. For every value and
 * every reader below, the two must return **the same number**.
 *
 * **All four policies are exercised**, and nothing on the instance is
 * changed to do it: `sightingsPolicy()` reads
 * `Configure::read('Plugin.Sightings_policy')`, so the probe overrides
 * it in its own process and puts it back. No setting is written, no
 * config file is touched, and the running server never sees a different
 * policy.
 *
 * Run:
 *   cp prd/value-profile-live/29-sightings-count-probe.php \
 *      app/Console/Command/ValueSightingCountProbeShell.php
 *   app/Console/cake ValueSightingCountProbe run
 *   rm app/Console/Command/ValueSightingCountProbeShell.php
 */
class ValueSightingCountProbeShell extends AppShell
{
    public $uses = array('Value', 'Sighting', 'User', 'MispAttribute');

    private $checks = 0;
    private $failures = 0;

    const POLICIES = array(
        'EVENT_OWNER' => 0,
        'SIGHTING_REPORTER' => 1,
        'EVERYONE' => 2,
        'HOST_ORG' => 3,
    );

    public function run()
    {
        $this->out('');
        $readers = array(
            'site admin' => $this->__reader('admin@admin.test'),
            'CIRCL org admin' => $this->__reader('orgadmin@circl.lu'),
            'ADMIN plain user' => $this->__reader('user@admin.test'),
        );
        $values = $this->__valuesWithSightings();
        if (empty($values)) {
            $this->out('<error>no sighted value to test against</error>');
            return;
        }
        $this->out(sprintf(
            'values under test: %s',
            implode(', ', $values)
        ));
        $original = Configure::read('Plugin.Sightings_policy');

        foreach (self::POLICIES as $name => $policy) {
            Configure::write('Plugin.Sightings_policy', $policy);
            $this->out('');
            $this->out(sprintf('== policy %s (%d) ==', $name, $policy));
            foreach ($readers as $who => $user) {
                if (empty($user)) {
                    continue;
                }
                foreach ($values as $value) {
                    $this->__agrees($user, $who, $value);
                }
            }
        }

        Configure::write('Plugin.Sightings_policy', $original);
        $this->out('');
        $this->out(sprintf(
            '%d checks, %d failures',
            $this->checks,
            $this->failures
        ));
    }

    /**
     * The aggregate against MISP's own answer, for one reader and one
     * value.
     *
     * `listSightings` is asked for the *attribute* scope over the
     * value's occurrence ids — the same rows `sightingCountsFor`
     * predicates on — so the only difference between the two answers
     * can be how the policy was applied.
     */
    private function __agrees(array $user, $who, $value)
    {
        $mine = $this->Value->sightingCountsFor($user, $value);
        $theirs = $this->__reference($user, $value);
        $this->__same(
            $theirs['total'],
            $mine['total'],
            sprintf('%s / %s: total', $who, $value)
        );
        $this->__same(
            $theirs['fp'],
            $mine['fp'],
            sprintf('%s / %s: false positives', $who, $value)
        );
        $this->__same(
            $theirs['expiration'],
            $mine['expiration'],
            sprintf('%s / %s: expirations', $who, $value)
        );
        /*
         * The number the fact strip and the tab badge actually print,
         * and the reason it is not `total`: MISP counts the three kinds
         * of row apart and the Overview's card heads the first of them
         * *Sightings*.
         */
        $this->__same(
            $theirs['sighting'],
            $mine['sighting'],
            sprintf('%s / %s: sightings proper', $who, $value)
        );
        $this->__same(
            $mine['total'],
            $mine['sighting'] + $mine['fp'] + $mine['expiration'],
            sprintf('%s / %s: the three kinds sum to the total', $who, $value)
        );
    }

    /**
     * What MISP says, through the path the Sightings tab uses.
     */
    private function __reference(array $user, $value)
    {
        // `occurrenceIdsFor` is keyed by id; `listSightings` wants the
        // keys, not the rows hanging off them.
        $ids = array_keys($this->Value->occurrenceIdsFor($user, $value));
        if (empty($ids)) {
            return array(
                'total' => 0,
                'sighting' => 0,
                'fp' => 0,
                'expiration' => 0,
            );
        }
        $rows = $this->Sighting->listSightings(
            $user,
            $ids,
            'attribute'
        );
        $total = 0;
        $fp = 0;
        $expiration = 0;
        foreach ($rows as $row) {
            $total++;
            $type = (int)$row['Sighting']['type'];
            if ($type === 1) {
                $fp++;
            } elseif ($type === 2) {
                $expiration++;
            }
        }
        return array(
            'total' => $total,
            'sighting' => $total - $fp - $expiration,
            'fp' => $fp,
            'expiration' => $expiration,
        );
    }

    /**
     * Values that actually carry sightings, since a probe that only
     * ever compares zero to zero proves nothing.
     *
     * Read straight off the sightings table and mapped back to values,
     * so the set is whatever this instance happens to hold.
     */
    private function __valuesWithSightings()
    {
        $rows = $this->Sighting->find('all', array(
            'fields' => array(
                'Sighting.attribute_id',
                'COUNT(*) AS n',
            ),
            'recursive' => -1,
            'group' => array('Sighting.attribute_id'),
            'order' => array('n DESC'),
            'limit' => 12,
        ));
        $ids = array();
        foreach ($rows as $row) {
            $ids[] = $row['Sighting']['attribute_id'];
        }
        if (empty($ids)) {
            return array();
        }
        $attributes = $this->MispAttribute->find('all', array(
            'conditions' => array('Attribute.id' => $ids),
            'fields' => array('Attribute.value1', 'Attribute.value2'),
            'recursive' => -1,
        ));
        $values = array();
        foreach ($attributes as $attribute) {
            $row = $attribute['Attribute'];
            if ($row['value1'] !== '') {
                $values[$row['value1']] = true;
            }
            if ($row['value2'] !== '') {
                $values[$row['value2']] = true;
            }
        }
        return array_slice(array_keys($values), 0, 6);
    }

    private function __reader($email)
    {
        $user = $this->User->find('first', array(
            'conditions' => array('User.email' => $email),
            'recursive' => -1,
            'fields' => array('User.id'),
        ));
        if (empty($user)) {
            $this->out(sprintf('  (no %s on this instance)', $email));
            return array();
        }
        return $this->User->getAuthUser($user['User']['id']);
    }

    private function __same($expected, $actual, $label)
    {
        $this->checks++;
        if ($expected === $actual) {
            $this->out(sprintf('  ok    %s = %s', $label, json_encode($actual)));
            return;
        }
        $this->failures++;
        $this->out(sprintf(
            '  FAIL  %s (MISP says %s, the aggregate says %s)',
            $label,
            json_encode($expected),
            json_encode($actual)
        ));
    }
}
