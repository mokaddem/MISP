<?php

App::uses('ValueVerdictTool', 'Tools');
App::uses('ValueSignalLoader', 'Tools');

/**
 * Phase 2's exit criterion, against real rows.
 *
 * `03-signals.md` §1: *"the shipped default profile, run against
 * `185.234.219.24` on the dev instance, produces a ledger whose
 * contributions sum to its score, and that score lands in the
 * MALICIOUS band"* — and §9 item 4 wants the same of every demo value.
 * The engine harness (`03-signals-engine-harness.php`) proves the
 * arithmetic with no database; this proves the seven queries behind it
 * return what the eleven signals think they return, which is where a
 * `GROUP BY` MariaDB rejects, an aggregate that comes back as a string,
 * or a tag name the galaxy parser cannot read would show up instead.
 *
 * Run:
 *   cp prd/analyst-profile/03-signals-live-probe.php \
 *      app/Console/Command/AnalystSignalProbeShell.php
 *   app/Console/cake AnalystSignalProbe run
 *   rm app/Console/Command/AnalystSignalProbeShell.php
 *
 * It writes nothing except through `AnalystProfile::update()`, which is
 * the shipped default the instance is supposed to have.
 */
class AnalystSignalProbeShell extends AppShell
{
    public $uses = array('AnalystProfile', 'ValueProfile', 'Value',
        'User', 'OverCorrelatingValue');

    /** The four demo values, plus the median shape's stand-in. */
    const DEMO_VALUES = array(
        '185.234.219.24',
        '8.8.8.8',
        '45.155.205.233',
        '104.21.19.200',
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

        $this->__discovery();
        $profile = $this->__profile($user);
        $this->__values($user, $profile);
        $this->__window($user, $profile);
        $this->__hotValue($user, $profile);

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
     * §9.14 and §9.18, against the real directories: the eleven load,
     * nothing errors, and the custom root's absence is silence.
     */
    private function __discovery()
    {
        $this->out('discovery — the shipped catalogue');
        $catalogue = ValueSignalLoader::catalogue();
        $this->__is(11, count($catalogue), 'eleven signals discovered');
        $this->__is(
            array(),
            ValueSignalLoader::errors(),
            'none of them failed to load'
        );
        foreach ($catalogue as $id => $config) {
            $this->out(sprintf(
                '    %-32s %-12s %-10s %s',
                $id,
                $config['group'],
                $config['evidence_class'],
                $config['default_band']
            ));
        }
    }

    /**
     * The shipped default applies, and resolves for a real user.
     *
     * @param array $user
     * @return array
     */
    private function __profile(array $user)
    {
        $this->out('');
        $this->out('the profile in force');
        /*
         * Forced, so the row this probe scores with is the file it is
         * scoring against. The version comparison is phase 1's and is
         * already probed there (`02-store-live-probe.php` §7.6); what
         * this needs is determinism — a probe that silently skipped
         * because a stored row carried the same version number would
         * report on last week's weights. On an instance where a site
         * admin has edited the default, this discards those edits.
         */
        $outcome = $this->AnalystProfile->update(true);
        $this->out(sprintf(
            '    updateDefaults: %s',
            json_encode($outcome)
        ));
        $profile = $this->AnalystProfile->resolveFor($user);
        $this->__is(
            'default-v1',
            empty($profile['name']) ? null : $profile['name'],
            'resolveFor names the shipped default'
        );
        $signals = $profile['parameters']['signals'] ?? array();
        $this->__is(
            11,
            count($signals),
            'and it carries the eleven-signal catalogue'
        );
        $unresolved = array();
        foreach ($signals as $entry) {
            if (ValueSignalLoader::get($entry['id']) === null) {
                $unresolved[] = $entry['id'];
            }
        }
        $this->__is(
            array(),
            $unresolved,
            'every id it names resolves to an implementation'
        );
        return $profile;
    }

    /**
     * The exit criterion: score real values and check the sum.
     *
     * @param array $user
     * @param array $profile
     */
    private function __values(array $user, array $profile)
    {
        $tool = new ValueVerdictTool($this->ValueProfile);
        foreach ($this->__subjects($user) as $value => $why) {
            /*
             * **Cast, and this is not defensive tidying.** PHP turns a
             * numeric array key into an integer, so the value `1`
             * arrives here as `int 1`; comparing an integer against a
             * `varchar` column makes MariaDB convert the column
             * instead of using its index, and the context build went
             * from 31 ms to 9.4 seconds on this instance's largest
             * value. The engine casts too (`verdictContextFor`), so
             * this is the belt to that brace — and it is the trap
             * `Value::prevalenceFor` already documents from the other
             * direction.
             */
            $value = (string)$value;
            $this->out('');
            $this->out(sprintf('%s — %s', $value, $why));
            $before = $this->__queryCount();
            $started = microtime(true);
            $context = $this->ValueProfile->verdictContextFor(
                $user,
                $value,
                $profile
            );
            $built = microtime(true);
            $verdict = $tool->assess(
                $context,
                $profile,
                array('lean' => 'threat')
            );
            $queries = $this->__queryCount() - $before;

            $sum = 0;
            foreach ($verdict['ledger'] as $group) {
                $this->out(sprintf(
                    '    %s — %s',
                    $group['kind'],
                    $group['note']
                ));
                foreach ($group['signals'] as $row) {
                    $sum += $row['contribution'];
                    $this->out(sprintf(
                        '      %s %+4d  %-9s %-46s %s',
                        $row['direction'] === 'up' ? '^' : 'v',
                        $row['contribution'],
                        $row['weight'],
                        $row['signal'],
                        $row['evidence']
                    ));
                }
            }
            foreach ($verdict['not_counted'] as $item) {
                $this->out(sprintf(
                    '    not counted: %s — %s',
                    $item['title'],
                    $item['note']
                ));
            }
            $this->out(sprintf(
                '    quality %d, band %s, %d of %d signals fired,'
                    . ' %d queries, %d ms context + %d ms scoring',
                $verdict['quality'],
                $verdict['band'],
                $verdict['signals']['fired'],
                $verdict['signals']['configured'],
                $queries,
                (int)round(($built - $started) * 1000),
                (int)round((microtime(true) - $built) * 1000)
            ));
            $this->__is(
                $verdict['quality'],
                $sum,
                sprintf('%s: the rows sum to the quality', $value)
            );
            $composition = 0;
            foreach ($verdict['composition'] as $segment) {
                $composition += $segment['points'];
            }
            $this->__is(
                $verdict['quality'],
                $composition,
                sprintf('%s: the composition card agrees', $value)
            );
            $this->__is(
                'default-v1',
                $verdict['profile'],
                sprintf('%s: the profile is named', $value)
            );
            /*
             * The one bound worth asserting on a live instance: the
             * context is a fixed number of queries however large the
             * value is, because every one of them is an aggregate or a
             * batched fetch. A query count that tracks the occurrence
             * count is the §14.4 tier-3 failure this probe exists to
             * catch.
             */
            $this->__is(
                true,
                $queries <= 30,
                sprintf('%s: %d queries, not per occurrence', $value,
                    $queries)
            );
        }
    }


    /**
     * §9.10 — the evidence window is deterministic, and it bounds the
     * row evidence only.
     *
     * The instance has no value that is both long-history and not
     * flagged over-correlating, so the window is brought down to the
     * value instead: the same profile with `min_occurrences` lowered
     * puts a mid-sized value inside the window's reach. What must hold
     * is that the aggregate-class rows are **identical** either way —
     * a window that moved the reporting breadth or the continuity
     * would be blinding the freshness clock, which is what §2.3
     * forbids — and that the `policy` note appears only with the
     * window in force.
     *
     * @param array $user
     * @param array $profile
     */
    private function __window(array $user, array $profile)
    {
        $subject = null;
        foreach ($this->__subjects($user) as $value => $why) {
            $value = (string)$value;
            if ($this->OverCorrelatingValue->isBlocked($value)) {
                continue;
            }
            $summary = $this->Value->recordSummaryFor($user, $value);
            if ($summary['occurrences'] >= 5) {
                $subject = $value;
                break;
            }
        }
        $this->out('');
        if ($subject === null) {
            $this->out('§9.10 — no unflagged value with five'
                . ' occurrences to window; unexercised');
            return;
        }
        $this->out(sprintf('§9.10 — the window, over %s', $subject));

        $tight = $profile;
        foreach ($tight['parameters']['exclusions'] as $i => $rule) {
            if ($rule['id'] === 'evidence.window') {
                $tight['parameters']['exclusions'][$i]['days'] = 30;
                $tight['parameters']['exclusions'][$i]['min_occurrences']
                    = 1;
            }
        }
        $tool = new ValueVerdictTool($this->ValueProfile);
        $wide = $tool->assess(
            $this->ValueProfile->verdictContextFor(
                $user,
                $subject,
                $profile
            ),
            $profile,
            array('lean' => 'threat')
        );
        $narrow = $tool->assess(
            $this->ValueProfile->verdictContextFor(
                $user,
                $subject,
                $tight
            ),
            $tight,
            array('lean' => 'threat')
        );
        $again = $tool->assess(
            $this->ValueProfile->verdictContextFor(
                $user,
                $subject,
                $tight
            ),
            $tight,
            array('lean' => 'threat')
        );
        $this->__is(
            $narrow['quality'],
            $again['quality'],
            'the same window twice gives the same quality'
        );
        $aggregate = array('reporting.independent_orgs',
            'reporting.published_ratio', 'record.temporal_precision',
            'lifecycle.warninglist', 'lifecycle.feeds',
            'lifecycle.continuity', 'lifecycle.recency');
        foreach ($aggregate as $id) {
            $this->__is(
                $this->__rowContribution($wide, $id),
                $this->__rowContribution($narrow, $id),
                sprintf('%s is identical with the window on', $id)
            );
        }
        $policies = array();
        foreach ($narrow['not_counted'] as $item) {
            if (($item['kind'] ?? null) === 'policy') {
                $policies[] = $item['id'];
            }
        }
        $this->__is(
            array('evidence.window'),
            $policies,
            'and the window states itself as policy'
        );
        $widePolicies = array();
        foreach ($wide['not_counted'] as $item) {
            if (($item['kind'] ?? null) === 'policy') {
                $widePolicies[] = $item['id'];
            }
        }
        $this->__is(
            array(),
            $widePolicies,
            'where an unwindowed value says nothing'
        );
        $this->out(sprintf(
            '    quality %d unwindowed, %d over 30 days',
            $wide['quality'],
            $narrow['quality']
        ));
    }

    /**
     * @param array $verdict
     * @param string $id
     * @return int|null
     */
    private function __rowContribution(array $verdict, $id)
    {
        foreach ($verdict['ledger'] as $group) {
            foreach ($group['signals'] as $row) {
                if ($row['id'] === $id) {
                    return $row['contribution'];
                }
            }
        }
        return null;
    }

    /**
     * §9.11 — a hot value renders rather than timing out.
     *
     * @param array $user
     * @param array $profile
     */
    private function __hotValue(array $user, array $profile)
    {
        $row = $this->OverCorrelatingValue->find('first', array(
            'recursive' => -1,
            'order' => array('OverCorrelatingValue.occurrence DESC'),
        ));
        if (empty($row)) {
            $this->out('');
            $this->out('no over-correlating value on this instance —'
                . ' §9.11 unexercised');
            return;
        }
        $this->out('');
        $this->out(sprintf(
            'the hot value: %s',
            $row['OverCorrelatingValue']['value']
        ));
        $this->out('    (the stored value is a hash of the original;'
            . ' the check below is that the flag is read at all)');
        $this->__is(
            true,
            $this->OverCorrelatingValue->isBlocked(
                $row['OverCorrelatingValue']['value']
            ),
            'isBlocked answers for a flagged value'
        );
    }

    /**
     * Which values to score: the demo values that exist here, plus the
     * three most widely reported values on the instance, plus a
     * single-org value for the median shape.
     *
     * @param array $user
     * @return array value => why it was picked
     */
    private function __subjects(array $user)
    {
        $subjects = array();
        foreach (self::DEMO_VALUES as $value) {
            $summary = $this->Value->recordSummaryFor($user, $value);
            if ($summary['occurrences'] > 0) {
                $subjects[$value] = sprintf(
                    'a demo value, %d occurrences in %d orgs',
                    $summary['occurrences'],
                    $summary['orgs']
                );
            }
        }
        $attributes = ClassRegistry::init('MispAttribute');
        $rows = $attributes->find('all', array(
            'fields' => array(
                'Attribute.value1',
                'COUNT(DISTINCT Event.orgc_id) AS orgs',
                'COUNT(DISTINCT Attribute.id) AS occurrences',
            ),
            'conditions' => array(
                'Attribute.deleted' => 0,
                'Attribute.value1 !=' => '',
            ),
            'recursive' => -1,
            'contain' => array('Event'),
            'group' => array('Attribute.value1'),
            // One field per element: two in one string is what CakePHP
            // 2 calls an invalid order clause.
            'order' => array('orgs DESC', 'occurrences DESC'),
            'limit' => 3,
        ));
        foreach ($rows as $row) {
            $value = $row['Attribute']['value1'];
            if (isset($subjects[$value])) {
                continue;
            }
            $subjects[$value] = sprintf(
                'the instance\'s most widely reported: %d orgs, %d'
                    . ' occurrences',
                (int)$row[0]['orgs'],
                (int)$row[0]['occurrences']
            );
        }
        $median = $attributes->find('first', array(
            'fields' => array('Attribute.value1'),
            'conditions' => array(
                'Attribute.deleted' => 0,
                'Attribute.value1 !=' => '',
                'Attribute.type' => array('ip-dst', 'ip-src', 'domain'),
            ),
            'recursive' => -1,
            'contain' => array('Event'),
            /*
             * `HAVING` rides inside `group`, which is CakePHP 2's only
             * way to express it — a `having` key is not part of the
             * find contract, and what the query builder says when it
             * gets one is *"Invalid order clause"*.
             */
            'group' => 'Attribute.value1 HAVING'
                . ' COUNT(DISTINCT Event.orgc_id) = 1 AND'
                . ' COUNT(DISTINCT Attribute.id) = 1',
        ));
        if (!empty($median['Attribute']['value1'])) {
            $subjects[$median['Attribute']['value1']] =
                'the median shape: one occurrence, one org (§7.4)';
        }
        return $subjects;
    }

    /**
     * @return array A site admin, as the ACL fetchers want one
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

    /**
     * @return int Queries issued so far on the default datasource
     */
    private function __queryCount()
    {
        $source = ConnectionManager::getDataSource('default');
        return count($source->getLog(false, false)['log']);
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
