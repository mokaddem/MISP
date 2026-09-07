<?php

App::uses('ValueVerdictTool', 'Tools');
App::uses('ValueLeanTool', 'Tools');
App::uses('ValueChangersTool', 'Tools');
App::uses('ValueSignalLoader', 'Tools');

/**
 * Phase 3's exit criterion, against real rows.
 *
 * `04-dispositions.md` §1: *"all five regression cases reach their
 * stated lean and quality band, and the contested one reaches it
 * through a named rule that renders in the meta line."* The harness
 * (`04-lean-bands-harness.php`) proves the rules at every boundary with
 * synthetic stance tables; this proves that the stance tables the
 * instance actually returns are the shape the rules expect — which is
 * where a `to_ids` column that comes back as a string, an
 * organisation with no readable name, or a warninglist hit with no
 * category would show up instead.
 *
 * It is also where §9's item 8 gets its band half. The harness asserts
 * the lean on all five cases from the fixture's own stated stance
 * splits; only real rows can say what the bands come out as.
 *
 * Run:
 *   cp prd/analyst-profile/04-lean-bands-live-probe.php \
 *      app/Console/Command/AnalystLeanProbeShell.php
 *   app/Console/cake AnalystLeanProbe run
 *   rm app/Console/Command/AnalystLeanProbeShell.php
 *
 * It writes nothing except through `AnalystProfile::update()`, which is
 * the shipped default the instance is supposed to have.
 */
class AnalystLeanProbeShell extends AppShell
{
    public $uses = array('AnalystProfile', 'ValueProfile', 'Value',
        'User', 'OverCorrelatingValue');

    /** The four demo values. */
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
        $this->__determinism($user, $profile);

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
     * The conflict-rule directory, against the real filesystem.
     *
     * The one thing a harness pointing at its own stub directories
     * cannot check: that the shipped root is where the loader thinks it
     * is, and that both rules in it construct.
     */
    private function __discovery()
    {
        $this->out('discovery — the shipped conflict rules');
        $catalogue = ValueSignalLoader::catalogue('escalation');
        $this->__is(2, count($catalogue), 'two conflict rules discovered');
        $this->__is(
            array(),
            ValueSignalLoader::errors('escalation'),
            'neither failed to load'
        );
        foreach ($catalogue as $id => $config) {
            $this->out(sprintf(
                '    %-44s %-10s %s',
                $id,
                $config['emits'],
                implode(', ', array_keys($config['when_schema']))
            ));
        }
        /*
         * The falsifiability card reads a signal's own `unit`
         * declaration, so a shipped signal that declares one the
         * arithmetic cannot use would produce a line that is quietly
         * wrong rather than absent.
         */
        $units = 0;
        foreach (ValueSignalLoader::catalogue() as $id => $config) {
            if (empty($config['unit'])) {
                continue;
            }
            $units++;
            $this->__is(
                true,
                isset($config['unit']['points'],
                    $config['unit']['cap'],
                    $config['unit']['one'],
                    $config['unit']['many']),
                sprintf('%s declares a complete unit', $id)
            );
        }
        $this->out(sprintf(
            '    %d of the shipped signals declare a unit a reader'
                . ' could supply',
            $units
        ));
    }

    /**
     * The shipped default applies, and carries phase 3's sections.
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
         * scoring against. On an instance where a site admin has
         * edited the default, this discards those edits.
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
        $escalations = $profile['parameters']['escalations'] ?? array();
        $this->__is(
            2,
            count($escalations),
            'and it lists both conflict rules'
        );
        $unresolved = array();
        foreach ($escalations as $entry) {
            if (ValueSignalLoader::get($entry['id'], 'escalation')
                === null
            ) {
                $unresolved[] = $entry['id'];
            }
        }
        $this->__is(
            array(),
            $unresolved,
            'every rule id it names resolves to an implementation'
        );
        $thresholds = $profile['parameters']['thresholds'] ?? array();
        $this->__is(
            true,
            isset($thresholds['thin_record_clamp']['max_band']),
            'and the thresholds carry the thin-record clamp'
        );
        return $profile;
    }

    /**
     * The whole assessment, on real values, with nothing forced.
     *
     * Phase 2's probe had to pass a lean because there was nothing to
     * derive one from. This passes none — which is the difference the
     * phase makes, and the reason `8.8.8.8` is expected to come out
     * somewhere other than where phase 2 left it.
     *
     * @param array $user
     * @param array $profile
     */
    private function __values(array $user, array $profile)
    {
        $tool = new ValueVerdictTool($this->ValueProfile);
        foreach ($this->__subjects($user) as $value => $why) {
            // A string, whatever the array key made of it.
            $value = (string)$value;
            $this->out('');
            $this->out(sprintf('%s — %s', $value, $why));
            $context = $this->ValueProfile->verdictContextFor(
                $user,
                $value,
                $profile
            );
            $verdict = $tool->assess($context, $profile);

            $this->out(sprintf(
                '    stances: %d assert / %d do not, share %.2f'
                    . ' against %.2f',
                $verdict['stances']['threat_orgs'],
                $verdict['stances']['benign_orgs'],
                $verdict['stances']['threat_share'],
                $verdict['stances']['supermajority']
            ));
            $this->out(sprintf(
                '    lean %s (rules reached %s), quality %+d, band %s',
                strtoupper($verdict['lean']),
                $verdict['derived_lean'],
                $verdict['quality'],
                $verdict['band']
            ));
            if (!empty($verdict['rule'])) {
                $this->out(sprintf(
                    '    rule %s',
                    $verdict['rule']['id']
                ));
                $this->out(sprintf(
                    '         %s',
                    $verdict['rule']['prose']
                ));
                $this->out(sprintf(
                    '         %s',
                    $verdict['rule']['evidence']
                ));
            }
            $this->out(sprintf(
                '    tug: %d supporting / %d disputing',
                $verdict['tug']['support'],
                $verdict['tug']['dispute']
            ));
            foreach ($verdict['changers'] as $changer) {
                $this->out(sprintf(
                    '    %-8s %-5s %s',
                    $changer['axis'],
                    $changer['direction'],
                    $changer['text']
                ));
            }
            foreach ($verdict['rule_errors'] as $error) {
                $this->out(sprintf(
                    '    rule not run: %s — %s',
                    $error['id'],
                    $error['note']
                ));
            }
            $this->__assertions($verdict);
        }
    }

    /**
     * What has to hold of every assessment, whatever the value.
     *
     * @param array $verdict
     */
    private function __assertions(array $verdict)
    {
        $sum = 0;
        $support = 0;
        $dispute = 0;
        foreach ($verdict['ledger'] as $group) {
            foreach ($group['signals'] as $row) {
                $sum += $row['contribution'];
                $threatSigned = $row['contribution']
                    * $verdict['polarity'];
                if ($threatSigned >= 0) {
                    $support += $threatSigned;
                } else {
                    $dispute -= $threatSigned;
                }
            }
        }
        $this->__is(
            $verdict['quality'],
            $sum,
            'the ledger sums to the quality'
        );
        $this->__is(
            $support,
            $verdict['tug']['support'],
            'the tug\'s supporting side is the rows that support it'
        );
        $this->__is(
            $dispute,
            $verdict['tug']['dispute'],
            'and its disputing side is the rest'
        );
        $this->__is(
            true,
            in_array($verdict['lean'], ValueLeanTool::LEANS, true),
            'the lean is one of the four states'
        );
        /*
         * The one thing the two axes must never disagree about: a lean
         * the ledger came out against has to be contested, or the page
         * is printing a negative number under a confident word.
         */
        $this->__is(
            true,
            $verdict['quality'] >= 0
                || $verdict['lean'] === 'contested',
            'a negative quality only ever appears under a contested'
                . ' lean'
        );
        $this->__is(
            true,
            in_array($verdict['band'],
                ValueVerdictTool::BANDS, true),
            'and the band is one of the four'
        );
        /*
         * Every falsifiability line has to name an axis, because the
         * card renders one row per axis and an unnamed line would land
         * in whichever row it happened to be listed in.
         */
        foreach ($verdict['changers'] as $changer) {
            $this->__is(
                true,
                in_array($changer['axis'],
                    array('lean', 'relevance', 'quality'), true)
                    && in_array($changer['direction'],
                        array('up', 'down'), true)
                    && $changer['text'] !== '',
                sprintf('the %s changer is renderable', $changer['axis'])
            );
        }
    }

    /**
     * The same value twice, and the answers agree.
     *
     * The lean derivation runs conflict rules and the falsifiability
     * card re-runs the derivation inside a probe loop, so there are now
     * three places an ordering accident could make one page disagree
     * with the next. Phase 10's worker and the page have to reach the
     * same answer or a materialised assessment means nothing.
     *
     * @param array $user
     * @param array $profile
     */
    private function __determinism(array $user, array $profile)
    {
        $this->out('');
        $this->out('determinism — the same rows twice');
        $tool = new ValueVerdictTool($this->ValueProfile);
        $subjects = array_keys($this->__subjects($user));
        if (empty($subjects)) {
            return;
        }
        $value = (string)$subjects[0];
        $now = time();
        $first = $tool->verdictFor($user, $value,
            array('profile' => $profile, 'now' => $now));
        $second = $tool->verdictFor($user, $value,
            array('profile' => $profile, 'now' => $now));
        $this->__is(
            json_encode($first),
            json_encode($second),
            sprintf('%s assesses identically twice over', $value)
        );
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
            'group' => 'Attribute.value1 HAVING'
                . ' COUNT(DISTINCT Event.orgc_id) = 1 AND'
                . ' COUNT(DISTINCT Attribute.id) = 1',
        ));
        if (!empty($median['Attribute']['value1'])) {
            $subjects[$median['Attribute']['value1']] =
                'the median shape: one occurrence, one org';
        }
        return $subjects;
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
