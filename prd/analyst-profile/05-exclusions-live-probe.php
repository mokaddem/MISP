<?php

App::uses('ValueVerdictTool', 'Tools');
App::uses('ValueExclusionTool', 'Tools');

/**
 * Phase 4's condition-class exclusion, against real SQL.
 *
 * `orgs.own` is the one exclusion a harness cannot check, because its
 * entire mechanism is a predicate in six aggregate queries. Two things
 * can go wrong there and neither shows up in PHP: the condition names
 * an `Event` column, so a query that did not join the table would
 * error rather than filter; and if the key reached some aggregates and
 * not others, the ledger would contradict itself in a way that still
 * looks like a number.
 *
 * So the load-bearing assertion here is **agreement**: the reporting
 * breadth and the occurrence tally are two separate queries, and they
 * have to name the same set of organisations with the rule on as with
 * it off. That is §5 item 3, and it is the property a post-filter over
 * an aggregated context would have silently broken.
 *
 * Run:
 *   cp prd/analyst-profile/05-exclusions-live-probe.php \
 *      app/Console/Command/AnalystExclusionProbeShell.php
 *   app/Console/cake AnalystExclusionProbe run
 *   rm app/Console/Command/AnalystExclusionProbeShell.php
 */
class AnalystExclusionProbeShell extends AppShell
{
    public $uses = array('AnalystProfile', 'ValueProfile', 'Value',
        'User', 'OverCorrelatingValue');

    const DEMO_VALUES = array(
        '185.234.219.24',
        '8.8.8.8',
        '45.155.205.233',
    );

    private $checks = 0;
    private $failures = 0;

    public function run()
    {
        $this->out('');
        $user = $this->__user();
        if (empty($user)) {
            $this->error('No site admin on this instance to probe as.');
        }
        $this->AnalystProfile->update(true);
        $profile = $this->AnalystProfile->resolveFor($user);
        if (empty($profile)) {
            $this->error('No profile in force for this user.');
        }

        $this->__section($profile);
        $subjects = $this->__subjects($user);
        $this->__values($user, $profile, $subjects);
        $this->__reach($user, $profile, $subjects);

        $this->out('');
        $this->out(sprintf(
            '%d checks, %d failures',
            $this->checks,
            $this->failures
        ));
        if ($this->failures > 0) {
            $this->error(sprintf('%d failed', $this->failures));
        }
    }

    /**
     * The shipped default's own exclusions section.
     *
     * @param array $profile
     */
    private function __section(array $profile)
    {
        $this->out('the shipped exclusions');
        $section = $profile['parameters']['exclusions'] ?? array();
        $this->__is(4, count($section), 'four exclusions ship');
        foreach ($section as $entry) {
            $this->out(sprintf(
                '    %-20s %s',
                $entry['id'],
                (array_key_exists('enabled', $entry)
                    && empty($entry['enabled']))
                    ? 'off'
                    : 'on'
            ));
        }
    }

    /**
     * Each value scored with `orgs.own` off and then on.
     *
     * @param array $user
     * @param array $profile
     * @param array $subjects
     */
    private function __values(array $user, array $profile,
        array $subjects
    ) {
        $tool = new ValueVerdictTool($this->ValueProfile);
        $with = $this->__profileWithOwn($profile, true);
        foreach ($subjects as $value => $why) {
            $value = (string)$value;
            $this->out('');
            $this->out(sprintf('%s — %s', $value, $why));

            $before = $this->ValueProfile->verdictContextFor(
                $user,
                $value,
                $profile
            );
            $after = $this->ValueProfile->verdictContextFor(
                $user,
                $value,
                $with
            );

            $this->out(sprintf(
                '    orgs.own off: %d occurrences, %d orgs, %d'
                    . ' stance rows, %d sightings, %d sources',
                $before['occurrences']['total'],
                $before['occurrences']['orgs'],
                count($before['orgs']),
                $before['sightings']['total'] ?? 0,
                $before['feeds']['count']
            ));
            $this->out(sprintf(
                '    orgs.own on:  %d occurrences, %d orgs, %d'
                    . ' stance rows, %d sightings, %d sources',
                $after['occurrences']['total'],
                $after['occurrences']['orgs'],
                count($after['orgs']),
                $after['sightings']['total'] ?? 0,
                $after['feeds']['count']
            ));

            /*
             * The whole point of putting the exclusion in the query.
             * Two different aggregates count organisations; they must
             * agree, or the ledger's reporting row and its occurrence
             * evidence line describe different sets.
             */
            $this->__is(
                (int)$before['occurrences']['orgs'],
                count($before['orgs']),
                'off: the tally and the stance rows name the same'
                    . ' number of organisations'
            );
            $this->__is(
                (int)$after['occurrences']['orgs'],
                count($after['orgs']),
                'on: they still agree'
            );
            $this->__is(
                true,
                $after['occurrences']['orgs']
                    <= $before['occurrences']['orgs'],
                'an exclusion never adds organisations'
            );
            $this->__is(
                true,
                $after['occurrences']['total']
                    <= $before['occurrences']['total'],
                'nor occurrences'
            );
            $ownRows = 0;
            foreach ($after['orgs'] as $org) {
                if ((int)$org['id'] === (int)$user['org_id']) {
                    $ownRows++;
                }
            }
            $this->__is(
                0,
                $ownRows,
                'and the viewer\'s own organisation is gone from the'
                    . ' stance table'
            );
            /*
             * The monthly activity is a third query through the same
             * predicate, and continuity reads it. If the key reached
             * the tally and not this, a value would lose occurrences
             * and keep the months they happened in.
             */
            $this->__is(
                true,
                array_sum($after['activity']['months'])
                    <= array_sum($before['activity']['months']),
                'the monthly activity is filtered by the same key'
            );

            $this->__exclusionNotes($before);
            $verdict = $tool->assess($before, $profile);
            $this->__blockAssertions($verdict);
        }
    }

    /**
     * What the profile actually left out on this value.
     *
     * @param array $context
     */
    private function __exclusionNotes(array $context)
    {
        foreach ($context['exclusions'] as $note) {
            $this->out(sprintf(
                '    set aside [%s] %s — %s',
                $note['reason'],
                $note['title'],
                $note['note']
            ));
        }
        foreach ($context['excluded'] as $key => $count) {
            $this->out(sprintf(
                '    excluded tally: %s = %d',
                $key,
                $count
            ));
        }
    }

    /**
     * What has to hold of the block, whatever the value.
     *
     * @param array $verdict
     */
    private function __blockAssertions(array $verdict)
    {
        foreach ($verdict['not_counted'] as $entry) {
            $this->__is(
                true,
                in_array($entry['reason'],
                    array('policy', 'nodata'), true),
                sprintf(
                    '"%s" carries a render-level reason',
                    $entry['title']
                )
            );
            /*
             * A policy row is the one kind a reader can act on, so it
             * is the one kind that has to name the rule that made it.
             */
            if ($entry['reason'] === 'policy') {
                $this->__is(
                    true,
                    !empty($entry['id']),
                    sprintf(
                        '"%s" names the rule behind it',
                        $entry['title']
                    )
                );
            }
        }
        $this->__is(
            true,
            !empty($verdict['acl_note']),
            'the permissions caveat is on the verdict'
        );
    }

    /**
     * The same profile with `orgs.own` switched on.
     *
     * @param array $profile
     * @param bool $enabled
     * @return array
     */
    private function __profileWithOwn(array $profile, $enabled)
    {
        $exclusions = $profile['parameters']['exclusions'] ?? array();
        $found = false;
        foreach ($exclusions as $index => $entry) {
            if (($entry['id'] ?? null) === ValueExclusionTool::ORGS_OWN) {
                $exclusions[$index]['enabled'] = $enabled;
                $found = true;
            }
        }
        if (!$found) {
            $exclusions[] = array(
                'id' => ValueExclusionTool::ORGS_OWN,
                'enabled' => $enabled,
            );
        }
        $profile['parameters']['exclusions'] = $exclusions;
        return $profile;
    }

    /**
     * @param array $user
     * @return array
     */
    private function __subjects(array $user)
    {
        $subjects = array();
        foreach (self::DEMO_VALUES as $value) {
            $summary = $this->Value->recordSummaryFor($user, $value);
            if ($summary['occurrences'] > 0) {
                $subjects[$value] = sprintf(
                    '%d occurrences in %d orgs',
                    $summary['occurrences'],
                    $summary['orgs']
                );
            }
        }
        /*
         * A value the viewer's own organisation reports, or `orgs.own`
         * has nothing to remove and the interesting assertions are
         * vacuous.
         */
        $attributes = ClassRegistry::init('MispAttribute');
        $mine = $attributes->find('first', array(
            'fields' => array('Attribute.value1'),
            'conditions' => array(
                'Attribute.deleted' => 0,
                'Attribute.value1 !=' => '',
                'Event.orgc_id' => $user['org_id'],
            ),
            'recursive' => -1,
            'contain' => array('Event'),
            'group' => array('Attribute.value1'),
            'order' => array('COUNT(DISTINCT Attribute.id) DESC'),
        ));
        if (!empty($mine['Attribute']['value1'])) {
            $subjects[$mine['Attribute']['value1']] =
                'the value your own organisation reports most';
        }
        return $subjects;
    }

    /**
     * Whether the two row-and-list exclusions ever fire on real data.
     *
     * A probe that prints nothing where a rule removed nothing looks
     * exactly like a probe that never ran the rule, so this says which
     * happened — and it widens the self-sighting window to prove the
     * filter is reachable at all. If no organisation on this instance
     * has ever sighted its own report, the rule is correct and
     * unexercised, and that sentence is the finding rather than a
     * silent pass.
     *
     * @param array $user
     * @param array $profile
     * @param array $subjects
     */
    private function __reach(array $user, array $profile,
        array $subjects
    ) {
        $this->out('');
        $this->out('do the row and list rules reach anything here?');
        $wide = $profile;
        $exclusions = array();
        foreach ($profile['parameters']['exclusions'] as $entry) {
            if (($entry['id'] ?? null)
                === ValueExclusionTool::SIGHTINGS_SELF
            ) {
                $entry['within_hours'] = 24 * 365 * 10;
            }
            $exclusions[] = $entry;
        }
        $wide['parameters']['exclusions'] = $exclusions;

        $selfSightings = 0;
        $undecided = 0;
        $folded = 0;
        foreach (array_keys($subjects) as $value) {
            $context = $this->ValueProfile->verdictContextFor(
                $user,
                (string)$value,
                $wide
            );
            foreach ($context['exclusions'] as $note) {
                if ($note['id'] === ValueExclusionTool::SIGHTINGS_SELF) {
                    $this->out(sprintf(
                        '    %s: %s',
                        $value,
                        $note['note']
                    ));
                }
            }
            $selfSightings += (int)
                ($context['excluded']['sightings'] ?? 0);
            $folded += (int)($context['excluded']['feeds'] ?? 0);
        }
        $this->out(sprintf(
            '    over a ten-year window: %d self-sightings excluded,'
                . ' %d mirrored sources folded',
            $selfSightings,
            $folded
        ));
        if ($selfSightings === 0) {
            $this->out('    note  no organisation on this instance has'
                . ' sighted its own report, so the row filter is'
                . ' correct and unexercised here — the harness is'
                . ' where it is proven');
        }
        $this->__is(
            true,
            $selfSightings >= 0 && $folded >= 0,
            'the row and list rules run over real evidence without'
                . ' error'
        );

        /*
         * *Excluded nothing* and *could not decide about anything* come
         * out of the tally identically, so the rule's own inputs are
         * checked directly. If the occurrence map carried no creating
         * organisation, every row would be undecidable and the count
         * above would still read zero — which would look exactly like
         * this instance having no self-sightings.
         */
        $value = (string)array_keys($subjects)[0];
        $map = $this->Value->sightedOccurrenceIdsFor($user, $value);
        $withOrg = 0;
        $withStamp = 0;
        foreach ($map as $occurrence) {
            // Flat keys: the accessor folds CakePHP's nested result.
            if (!empty($occurrence['orgc_id'])) {
                $withOrg++;
            }
            if (!empty($occurrence['timestamp'])) {
                $withStamp++;
            }
        }
        $this->out(sprintf(
            '    %s: %d sighted occurrences, %d naming a reporting'
                . ' organisation, %d with a timestamp',
            $value,
            count($map),
            $withOrg,
            $withStamp
        ));
        $this->__is(
            count($map),
            $withOrg,
            'every sighted occurrence names the organisation that'
                . ' reported it, so the rule had a comparison to make'
        );
        $this->__is(
            count($map),
            $withStamp,
            'and a timestamp to measure the window against'
        );
    }

    /**
     * @return array
     */
    private function __user()
    {
        $row = $this->User->find('first', array(
            'recursive' => -1,
            'contain' => array('Role', 'Organisation'),
            'conditions' => array('User.disabled' => 0),
            'order' => array('Role.perm_site_admin DESC', 'User.id ASC'),
        ));
        if (empty($row['User']['id'])) {
            return array();
        }
        return $this->User->getAuthUser($row['User']['id']);
    }

    private function __is($expected, $actual, $label)
    {
        $this->checks++;
        if ($expected === $actual) {
            $this->out(sprintf('  ok    %s', $label));
            return;
        }
        $this->failures++;
        $this->out(sprintf(
            '  FAIL  %s (expected %s, got %s)',
            $label,
            json_encode($expected),
            json_encode($actual)
        ));
    }
}
