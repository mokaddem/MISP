<?php

App::uses('ValueRelevanceTool', 'Tools');
App::uses('ValueVerdictTool', 'Tools');

/**
 * Phase 5's relevance axis, against real rows.
 *
 * The harness proves the arithmetic. What needs an instance is
 * everything the arithmetic is fed from, and phase 4 §7.3 is why this
 * file leads with the clock's **inputs** rather than its answer: a
 * clock that cannot resolve its own sources falls back, and a fallback
 * is a legitimate state — so a clock reading the wrong column produces
 * *"never independently corroborated"* on every value and looks exactly
 * like an instance where nothing has been corroborated. Zero
 * corroborations and zero *resolvable* corroborations are the same
 * number.
 *
 * So the first section asserts that every organisation row carries the
 * `oldest` column this phase added, and that the sighting fold decided
 * rather than skipped. Only then does the state mean anything.
 *
 * It also takes §6's three items a harness cannot reach:
 *
 *   - **item 5**, the three clocks, where the two halves come from two
 *     different queries and only SQL can disagree about them;
 *   - **item 7**, the majority case, on whatever this instance's
 *     majority case actually is rather than on an invented one;
 *   - **item 8**, the retirement being total — asserted from the
 *     datasource log rather than from a grep, because a query is the
 *     only thing that can prove a table is not read.
 *
 * Run:
 *   cp prd/analyst-profile/06-relevance-live-probe.php \
 *      app/Console/Command/AnalystRelevanceProbeShell.php
 *   app/Console/cake AnalystRelevanceProbe run
 *   rm app/Console/Command/AnalystRelevanceProbeShell.php
 */
class AnalystRelevanceProbeShell extends AppShell
{
    public $uses = array('AnalystProfile', 'ValueProfile', 'Value',
        'User', 'Sighting');

    const DEMO_VALUES = array(
        '185.234.219.24',
        '8.8.8.8',
        '45.155.205.233',
        '2.2.2.2',
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
        $this->__inputs($user, $subjects);
        $this->__values($user, $profile, $subjects);
        $this->__clocks($user, $profile, $subjects);
        $this->__invariant($user, $profile, $subjects);
        $this->__lonely($user, $profile);
        $this->__retired($user, $subjects);

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
     * The shipped `relevance` section, as the store hands it back.
     *
     * Worth its own section because the file the model imports is not
     * the file in this checkout: `app/files` is not mounted into the
     * dev container, so a section edited here and not copied in reads
     * as a profile that never changed.
     *
     * @param array $profile
     */
    private function __section(array $profile)
    {
        $this->out('the shipped relevance section');
        $raw = $profile['parameters']['relevance'] ?? array();
        $this->__is(
            true,
            !empty($raw),
            'the profile in force carries a relevance section'
        );
        $section = ValueRelevanceTool::section($profile);
        $this->__is(
            'last_independent_corroboration',
            $section['clock'],
            'the clock ships as last_independent_corroboration'
        );
        $this->__is(1.0, $section['decay_speed'], 'the curve is linear');
        $this->__is('shortest', $section['type_rule'], 'shortest TTL wins');
        $this->__is(0.33, $section['aging_fraction'], 'aging at a third');
        $this->__is(
            11,
            count($section['ttl_days']),
            'eleven types carry a named TTL'
        );
        $this->__is(
            10,
            count($section['ttl_types']),
            'ten of them through a bucket (D18)'
        );
        $this->__is(
            array('url' => 60),
            $section['ttl_overrides'],
            'and `url` as the one override'
        );
        $this->out(sprintf(
            '    default %d · buckets %s',
            $section['ttl_default'],
            json_encode($section['ttl_buckets'])
        ));
        $this->out(sprintf(
            '    ip-src %d · url %d · sha256 %d',
            $section['ttl_days']['ip-src'] ?? 0,
            $section['ttl_days']['url'] ?? 0,
            $section['ttl_days']['sha256'] ?? 0
        ));
    }

    /**
     * **The section that would have caught phase 4's bug.**
     *
     * Two inputs, both new to this phase and both invisible in the
     * output when wrong. `oldest` is one more aggregate on a grouped
     * query, so a missing alias hands back `0` for every organisation
     * and the clock silently reads every value as corroborated in 1970
     * — which is *older* than the fallback, so the fallback wins and
     * the page says *"never independently corroborated"* about a value
     * eight organisations hold.
     *
     * The sighting fold is the same shape from the other side: it
     * compares a sighting's organisation against the reporter of the
     * occurrence it hangs off, and an occurrence it cannot resolve
     * counts as undecidable. All-undecidable and nothing-to-decide are
     * the same zero.
     *
     * @param array $user
     * @param array $subjects
     */
    private function __inputs(array $user, array $subjects)
    {
        $this->out('');
        $this->out('the clock\'s inputs (phase 4 §7.3\'s rule)');
        foreach (array_keys($subjects) as $value) {
            $orgs = $this->Value->orgStanceFor($user, $value);
            $dated = 0;
            foreach ($orgs as $row) {
                if (!empty($row[0]['oldest'])) {
                    $dated++;
                }
            }
            $this->__is(
                count($orgs),
                $dated,
                sprintf(
                    '%s: all %d organisation rows name when they joined',
                    $value,
                    count($orgs)
                )
            );
            $sighted = $this->Value->sightedOccurrenceIdsFor($user, $value);
            $rows = empty($sighted)
                ? array()
                : $this->Sighting->listSightings(
                    $user,
                    array_keys($sighted),
                    'attribute',
                    false,
                    false,
                    false
                );
            $fold = ValueRelevanceTool::corroborationFrom($rows, $sighted);
            $positive = 0;
            foreach ($rows as $row) {
                if ((int)$row['Sighting']['type'] === 0) {
                    $positive++;
                }
            }
            $this->out(sprintf(
                '    %s: %d reports, %d type-0, %d days, %d'
                    . ' independent days, %d undecidable',
                $value,
                count($rows),
                $positive,
                count($fold['sightings_days']),
                count($fold['foreign_days']),
                $fold['undecidable']
            ));
            if ($positive > 0) {
                $this->__is(
                    true,
                    $fold['undecidable'] < $positive,
                    sprintf(
                        '%s: the fold decided at least one report'
                            . ' rather than skipping them all',
                        $value
                    )
                );
            }
        }
    }

    /**
     * The axis on each value the instance actually holds.
     *
     * @param array $user
     * @param array $profile
     * @param array $subjects
     */
    private function __values(array $user, array $profile, array $subjects)
    {
        $this->out('');
        $this->out('the axis, per value');
        foreach ($subjects as $value => $shape) {
            $panel = $this->ValueProfile->forRelevance($user, $value);
            $relevance = $panel['relevance'];
            $this->out(sprintf('  %s — %s', $value, $shape));
            $this->out(sprintf(
                '    %-10s runway %3d%%  %4d of %3d days  clock %s'
                    . ' (%s%s)',
                $relevance['state'],
                (int)round($relevance['runway'] * 100),
                $relevance['elapsed_days'],
                $relevance['ttl']['days'],
                date('Y-m-d', $relevance['clock']['at']),
                $relevance['clock']['kind'],
                $relevance['clock']['by'] === null
                    ? ''
                    : ', ' . $relevance['clock']['by']
            ));
            if (!empty($relevance['uncertain'])) {
                $this->out(sprintf(
                    '    uncertain: %s',
                    $relevance['uncertain_note']
                ));
            }
            if (!empty($relevance['ttl']['spread'])) {
                $spread = array();
                foreach ($relevance['ttl']['candidates'] as $candidate) {
                    $spread[] = $candidate['type'] . ' '
                        . $candidate['days'];
                }
                $this->out(sprintf(
                    '    types: %s — %s rule chose %s',
                    implode(', ', $spread),
                    $relevance['ttl']['rule'],
                    $relevance['ttl']['type']
                ));
            }
            $this->__is(
                true,
                in_array(
                    $relevance['state'],
                    array('current', 'aging', 'expired', 'uncertain'),
                    true
                ),
                sprintf('%s reaches one of the four states', $value)
            );
            $this->__is(
                true,
                $relevance['clock']['at'] !== null,
                sprintf('%s has a clock', $value)
            );
            $this->__is(
                true,
                $relevance['clock']['by'] !== null,
                sprintf(
                    '%s names what supplied it (§4.1) rather than'
                        . ' printing a bare date',
                    $value
                )
            );
            $this->__is(
                true,
                $relevance['expires_at'] === $relevance['clock']['at']
                    + $relevance['ttl']['days'] * 86400,
                sprintf('%s: the expiry date is the clock plus the TTL',
                    $value)
            );

            /*
             * The claim the rail card closes with, asserted rather than
             * assumed: the number in the rail is the last point of the
             * line in the chart. The two panels compute the axis
             * independently — two requests, no cache — so this is the
             * only thing making them agree.
             */
            $chart = $this->ValueProfile->forSightingChart($user, $value);
            $curves = $chart['sighting_series']['curves'] ?? array();
            if (!empty($curves[0]['points'])) {
                $points = $curves[0]['points'];
                $last = null;
                foreach ($points as $point) {
                    if ($point !== null) {
                        $last = $point;
                    }
                }
                $this->__is(
                    (int)round($chart['relevance']['runway'] * 100),
                    $last,
                    sprintf(
                        '%s: the chart\'s last drawn point is the'
                            . ' runway the rail prints',
                        $value
                    )
                );
                $this->__is(
                    1,
                    count($curves),
                    sprintf(
                        '%s: one overlay line, not one per model',
                        $value
                    )
                );
            }
        }
    }

    /**
     * §6 item 5 — all three clock settings on one real value.
     *
     * The two halves of the default clock come from two different
     * queries: the organisation joins from a grouped aggregate, the
     * independent sightings from a row fetch folded per day. A harness
     * hands both to the tool in one array; only an instance can have
     * them disagree.
     *
     * @param array $user
     * @param array $profile
     * @param array $subjects
     */
    private function __clocks(array $user, array $profile, array $subjects)
    {
        $this->out('');
        $this->out('the three clocks on one value (§6 item 5)');
        $value = $this->__busiest($user, $subjects);
        if ($value === null) {
            $this->out('  (no value on this instance to try them on)');
            return;
        }
        $answers = array();
        foreach (ValueRelevanceTool::CLOCKS as $clock) {
            $tuned = $profile;
            $tuned['parameters']['relevance']['clock'] = $clock;
            $panel = $this->ValueProfile->forRelevance(
                $user,
                $value,
                array('profile' => $tuned)
            );
            $answers[$clock] = $panel['relevance'];
        }
        foreach ($answers as $clock => $answer) {
            $this->out(sprintf(
                '    %-32s %s  %-18s %s',
                $clock,
                date('Y-m-d', $answer['clock']['at']),
                $answer['clock']['kind'],
                $answer['state']
            ));
            $this->__is(
                true,
                $answer['clock']['by'] !== null,
                sprintf('%s names its source', $clock)
            );
        }
        $independent = $answers['last_independent_corroboration'];
        $sighting = $answers['last_sighting'];
        $occurrence = $answers['last_occurrence'];
        $this->__is(
            true,
            $independent['clock']['at'] <= max(
                $sighting['clock']['at'],
                $occurrence['clock']['at']
            ),
            'the independent clock is never newer than the newest'
                . ' report or encoding — it is a subset of both'
        );
    }

    /**
     * **D11, against real rows.**
     *
     * The invariant is directional: nothing downstream reads the
     * relevance block. Proved off a relevance-only knob — the TTL — so
     * that no input to any other axis moves, which is the only way to
     * make a byte-for-byte diff mean anything.
     *
     * @param array $user
     * @param array $profile
     * @param array $subjects
     */
    private function __invariant(array $user, array $profile,
        array $subjects
    ) {
        $this->out('');
        $this->out('no ledger row, at any point on the curve (D11)');
        $engine = new ValueVerdictTool($this->ValueProfile);
        foreach (array_keys($subjects) as $value) {
            $long = $engine->verdictFor(
                $user,
                $value,
                array('profile' => $profile)
            );
            /*
             * Which way to move the TTL depends on where the value
             * already is. `45.155.205.233` is 1,728 days past a 180-day
             * TTL, so shortening it cannot move anything — the first
             * version of this probe asserted a change that was
             * arithmetically unavailable and failed on the one value
             * whose state was already the extreme.
             */
            $tuned = $profile;
            /*
             * Written in the current shape, and the assignments and
             * overrides are cleared with it: leaving them would leave
             * every named type on its own shelf life and the default
             * would move nothing (D18).
             */
            $tuned['parameters']['relevance']['ttl_default'] =
                $long['relevance']['state'] === 'expired'
                    ? $long['relevance']['elapsed_days'] + 3650
                    : 1;
            $tuned['parameters']['relevance']['ttl_types'] = array();
            $tuned['parameters']['relevance']['ttl_overrides'] = array();
            unset($tuned['parameters']['relevance']['ttl_days']);
            $short = $engine->verdictFor(
                $user,
                $value,
                array('profile' => $tuned)
            );
            $this->__is(
                true,
                $long['relevance']['state']
                    !== $short['relevance']['state'],
                sprintf(
                    '%s: moving the TTL alone moves the axis (%s → %s)',
                    $value,
                    $long['relevance']['state'],
                    $short['relevance']['state']
                )
            );
            $this->__is(
                $long['quality'],
                $short['quality'],
                sprintf(
                    '%s: and the quality is the same number (%d)',
                    $value,
                    $long['quality']
                )
            );
            $this->__is(
                $long['lean'],
                $short['lean'],
                sprintf('%s: and the lean is the same word', $value)
            );
            $this->__is(
                $long['band'],
                $short['band'],
                sprintf('%s: and the band is the same band', $value)
            );
            $this->__is(
                json_encode($long['ledger']),
                json_encode($short['ledger']),
                sprintf('%s: and the ledger is byte-identical', $value)
            );
            $axes = array();
            foreach ($long['changers'] as $row) {
                $axes[] = $row['axis'];
            }
            $this->__is(
                true,
                in_array('relevance', $axes, true),
                sprintf('%s: the changers block carries a relevance'
                    . ' line', $value)
            );
        }
    }

    /**
     * §6 item 7 — the majority case, found rather than invented.
     *
     * One organisation, no sightings. `01-profile.md` §1.3 calls this
     * the median value in production, and the requirement is that it
     * renders as a stated condition rather than as an error or a blank.
     *
     * @param array $user
     * @param array $profile
     */
    private function __lonely(array $user, array $profile)
    {
        $this->out('');
        $this->out('a single-organisation value (§6 item 7)');
        $attributes = ClassRegistry::init('MispAttribute');
        $row = $attributes->find('first', array(
            'fields' => array(
                'Attribute.value1',
                'COUNT(DISTINCT Event.orgc_id) AS orgs',
            ),
            'conditions' => array(
                'Attribute.deleted' => 0,
                'Attribute.value1 !=' => '',
            ),
            'recursive' => -1,
            'contain' => array('Event'),
            'group' => array('Attribute.value1'),
            'having' => array('COUNT(DISTINCT Event.orgc_id)' => 1),
            'order' => array('Attribute.id ASC'),
        ));
        $value = $row['Attribute']['value1'] ?? null;
        if ($value === null) {
            $this->out('  (every value here is held by two or more orgs)');
            return;
        }
        $relevance = $this->ValueProfile
            ->forRelevance($user, (string)$value)['relevance'];
        $this->out(sprintf(
            '    %s — %s, clock %s (%s)',
            $value,
            $relevance['state'],
            date('Y-m-d', $relevance['clock']['at']),
            $relevance['clock']['kind']
        ));
        $this->__is(
            true,
            $relevance['clock']['fallback'],
            'one organisation is nobody corroborating, so the clock'
                . ' falls back and says so'
        );
        $this->__is(
            true,
            $relevance['state'] !== null,
            'and the panel still has a state to render'
        );
        $this->__is(
            true,
            $relevance['clock']['by'] !== null,
            'and a name for the date it fell back to'
        );
    }

    /**
     * §6 item 8 — the retirement is total, proved from the query log.
     *
     * A grep says no source line names `DecayingModel`. It cannot say
     * that no query reaches `decaying_models`, which is the claim
     * §1's exit criterion actually makes — and the two panels that used
     * to read that table are exactly the two rebuilt here. So the log
     * is the evidence, and the query counts the board's `Q` column
     * wants come out of the same measurement.
     *
     * @param array $user
     * @param array $subjects
     */
    private function __retired(array $user, array $subjects)
    {
        $this->out('');
        $this->out('nothing reads decaying_models any more (§6 item 8)');
        $value = $this->__busiest($user, $subjects);
        if ($value === null) {
            $this->out('  (no value to measure against)');
            return;
        }
        $db = ConnectionManager::getDataSource('default');
        $db->fullDebug = true;
        /*
         * **The cap has to come off first, and the first run of this
         * probe is why the sentence is here.** `DboSource` keeps 200
         * log entries and this page has already spent them by the time
         * the section runs, so `getLog` returned a list that did not
         * grow — the measurement read *0 queries, 0 touching
         * decaying_models* and passed. Zero found and zero looked at
         * are the same number, which is phase 4 §7.3's rule reproduced
         * inside the probe written to honour it.
         */
        $max = new ReflectionProperty($db, '_queriesLogMax');
        $max->setAccessible(true);
        $max->setValue($db, 1000000);
        foreach (array('forRelevance', 'forSightingChart') as $method) {
            $before = count($db->getLog(false, false)['log']);
            $this->ValueProfile->$method($user, $value);
            $log = $db->getLog(false, false)['log'];
            $queries = array_slice($log, $before);
            $decay = 0;
            foreach ($queries as $entry) {
                if (stripos($entry['query'], 'decaying_model') !== false) {
                    $decay++;
                }
            }
            $this->out(sprintf(
                '    %-18s %d queries, %d touching decaying_models',
                $method,
                count($queries),
                $decay
            ));
            $this->__is(
                0,
                $decay,
                sprintf('%s issues no decaying-model query', $method)
            );
            $this->__is(
                true,
                count($queries) > 0,
                sprintf(
                    '%s: and the log recorded queries, so the line'
                        . ' above is a measurement rather than an'
                        . ' empty list',
                    $method
                )
            );
        }
    }

    /**
     * Whichever demo value this instance holds most of.
     *
     * @param array $user
     * @param array $subjects
     * @return string|null
     */
    private function __busiest(array $user, array $subjects)
    {
        $best = null;
        $most = -1;
        foreach (array_keys($subjects) as $value) {
            $summary = $this->Value->recordSummaryFor($user, $value);
            if ($summary['occurrences'] > $most) {
                $most = $summary['occurrences'];
                $best = $value;
            }
        }
        return $best;
    }

    /**
     * The demo values this instance actually holds.
     *
     * @param array $user
     * @return array value => a one-line shape
     */
    private function __subjects(array $user)
    {
        $subjects = array();
        foreach (self::DEMO_VALUES as $value) {
            $summary = $this->Value->recordSummaryFor($user, $value);
            if ($summary['occurrences'] > 0) {
                $subjects[$value] = sprintf(
                    '%d occurrences in %d orgs, %d dated',
                    $summary['occurrences'],
                    $summary['orgs'],
                    $summary['dated']
                );
            }
        }
        if (empty($subjects)) {
            $this->error('None of the demo values exist here.');
        }
        return $subjects;
    }

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
