<?php

App::uses('ValueTrustTool', 'Tools');
App::uses('WarninglistCategory', 'Tools');
App::uses('ValueVerdictTool', 'Tools');

/**
 * Phase 6's two maps, against real rows.
 *
 * The harness proves the arithmetic. What needs an instance is
 * everything the arithmetic is keyed on, and the load-bearing half of
 * this phase is a **join** the harness fakes: the profile grades
 * `organisations.uuid`, every row downstream carries the local id, and
 * nothing but SQL can say whether the two ends meet. A trust map that
 * resolved nothing would weight nothing and look exactly like a map
 * nobody has filled in — which is phase 4 §7.3's trap in a new place,
 * so the first section asserts the join's *output* before any score is
 * read.
 *
 * It also takes §5's items a harness cannot reach:
 *
 *   - **items 6 and 7** on this instance's own listed value rather
 *     than on an invented one, which is where the harness's finding
 *     gets confirmed or refuted: a category override does not always
 *     change the lean;
 *   - **item 9**, a profile carrying a grade for an organisation that
 *     is here and one that is not — the export/import case, without a
 *     second instance;
 *   - **item 10**, the day-one case, which needs a shipped list
 *     enabled and real rows inside it. It runs as its own command,
 *     because `Warninglist::getEnabled()` memoises per process and a
 *     list enabled after that read stays invisible for the rest of the
 *     run;
 *   - **item 12**'s live half, the category source on a real hit;
 *   - and §3.3's retirement criterion measured against this instance's
 *     89 rows rather than asserted from two PRs.
 *
 * Run:
 *   cp prd/analyst-profile/07-reference-live-probe.php \
 *      app/Console/Command/AnalystReferenceProbeShell.php
 *   app/Console/cake AnalystReferenceProbe run
 *   app/Console/cake AnalystReferenceProbe dayone
 *   rm app/Console/Command/AnalystReferenceProbeShell.php
 */
class AnalystReferenceProbeShell extends AppShell
{
    public $uses = array('AnalystProfile', 'ValueProfile', 'Value',
        'User', 'Organisation', 'Warninglist');

    const DEMO_VALUES = array(
        '8.8.8.8',
        '2.2.2.2',
        '1.1.1.1',
        '45.155.205.233',
    );

    /** The list every roster entry on this instance is checked for. */
    const SHORTENERS = 'List of known URL Shorteners domains';

    /** A value that really is on it, and really is multi-org here. */
    const SHORTENED = 'bit.ly';

    /** The enabled false-positive list the demo values sit on. */
    const RESOLVERS = 'List of known IPv4 public DNS resolvers';

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
        $this->__join($user, $profile, $subjects);
        $this->__unchanged($user, $profile, $subjects);
        $this->__graded($user, $profile, $subjects);
        $this->__absent($user, $profile, $subjects);
        $this->__categories($user, $profile, $subjects);
        $this->__override($user, $profile, $subjects);
        $this->__retirement();
        $this->__cost($user, $profile, $subjects);

        $this->__report();
    }

    /**
     * §5 item 10 — the day-one case, in its own process.
     *
     * The shipped map supplies `known` for a list whose database row
     * says `false_positive`, with **both profile maps empty**: nobody
     * has edited anything and the category is still right. That is the
     * whole of V1's contribution, and it is the case the fixture's
     * conflicted value has been illustrating with no mechanism behind
     * it.
     *
     * The list is enabled for the duration and put back afterwards,
     * whatever happens in between.
     */
    public function dayone()
    {
        $this->out('');
        $user = $this->__user();
        if (empty($user)) {
            $this->error('No site admin on this instance to probe as.');
        }
        $this->AnalystProfile->update(true);
        $profile = $this->AnalystProfile->resolveFor($user);
        $list = $this->Warninglist->find('first', array(
            'recursive' => -1,
            'conditions' => array('Warninglist.name' => self::SHORTENERS),
        ));
        if (empty($list)) {
            $this->error(sprintf(
                'The list "%s" is not on this instance.',
                self::SHORTENERS
            ));
        }
        $id = (int)$list['Warninglist']['id'];
        $was = (int)$list['Warninglist']['enabled'];
        $this->out(sprintf(
            'the day-one case — "%s" (#%d), column category "%s",'
                . ' enabled %d',
            self::SHORTENERS,
            $id,
            $list['Warninglist']['category'],
            $was
        ));
        $this->__is(
            'false_positive',
            $list['Warninglist']['category'],
            'the database row says false_positive, because nothing'
                . ' upstream sets the field and __updateList() would'
                . ' drop it anyway (§3.1)'
        );
        $this->__is(
            'known',
            WarninglistCategory::categoryFor(self::SHORTENERS),
            'and the shipped map says known: a link a competent report'
                . ' names as malicious really is a shortener, and'
                . ' acting on the shortener has collateral'
        );

        try {
            $this->__enable($id, 1);
            $context = $this->ValueProfile->verdictContextFor(
                $user,
                self::SHORTENED,
                $profile
            );
            $hit = null;
            foreach ($context['warninglist']['hits'] as $row) {
                if ($row['name'] === self::SHORTENERS) {
                    $hit = $row;
                }
            }
            $this->__is(
                true,
                $hit !== null,
                sprintf('"%s" hits the list on real rows',
                    self::SHORTENED)
            );
            if ($hit !== null) {
                $this->__is('known', $hit['category'],
                    'and the hit resolves to known');
                $this->__is('shipped', $hit['category_source'],
                    'from the shipped map, with the profile map empty'
                        . ' — nobody edited anything (§5 item 10)');
            }
            $this->__is(
                array(),
                $profile['parameters']['reference']
                    ['warninglist_category'],
                'which the profile in force confirms: its category map'
                    . ' is empty'
            );

            /*
             * The escalation's own precondition is phase 3's threshold,
             * not this phase's: `min_independent_reports` ships at 3
             * and this instance's only roster-listed multi-org value
             * has 2. So the chain is closed with the threshold moved
             * and the move stated, rather than left unasserted — and
             * the shipped threshold is checked separately so the
             * change cannot pass unnoticed.
             */
            $orgs = count($context['orgs']);
            $this->out(sprintf(
                '  ..    %s is reported by %d organisations',
                self::SHORTENED,
                $orgs
            ));
            $shippedMin = null;
            foreach ($profile['parameters']['escalations'] as $rule) {
                if ($rule['id']
                    === 'conflict:known-infrastructure-vs-reporting'
                ) {
                    $shippedMin = (int)$rule['when']
                        ['min_independent_reports'];
                }
            }
            $this->__is(3, $shippedMin,
                'the shipped rule wants 3 independent reports');
            $engine = new ValueVerdictTool($this->ValueProfile);
            $lowered = $profile;
            foreach ($lowered['parameters']['escalations'] as $k => $r) {
                if ($r['id']
                    === 'conflict:known-infrastructure-vs-reporting'
                ) {
                    $lowered['parameters']['escalations'][$k]['when']
                        ['min_independent_reports'] = max(1, $orgs);
                }
            }
            $fired = $engine->assess($context, $lowered);
            $this->__is(
                'conflict:known-infrastructure-vs-reporting',
                $fired['rule']['id'] ?? null,
                sprintf(
                    'and with the threshold at %d the escalation fires'
                        . ' on the shipped map alone — the rule phase 3'
                        . ' shipped could not reach its own'
                        . ' precondition until now',
                    max(1, $orgs)
                )
            );
            $this->__is('contested', $fired['lean'],
                'the lean is contested');
            $this->__is('CONFLICTED', $fired['disposition'],
                'and the word the templates read is CONFLICTED');
        } finally {
            $this->__enable($id, $was);
            $this->out(sprintf(
                '  ..    "%s" put back to enabled %d',
                self::SHORTENERS,
                $was
            ));
        }

        $this->__report();
    }

    /**
     * The shipped `reference` section, as the store hands it back.
     *
     * Its own section for the reason phase 5's probe gives: the file
     * the model imports is not the file in this checkout, because
     * `app/files` is not mounted into the dev container — so a section
     * edited here and not copied in reads as a profile that never
     * changed.
     *
     * @param array $profile
     */
    private function __section(array $profile)
    {
        $this->out('the shipped reference section');
        $section = ValueTrustTool::section($profile);
        $this->__is(
            true,
            isset($section['org_trust_scale']),
            'the profile in force carries the grade scale, so an'
                . ' analyst can move a grade without a release'
        );
        $this->__is(
            array(),
            isset($section['org_trust']) ? $section['org_trust'] : null,
            'and the grade map ships empty'
        );
        $plan = ValueTrustTool::planFor($profile);
        $this->__is(false, $plan['in_force'],
            'so trust weighting is not in force on day one — the map'
                . ' is the switch, which is what makes §5 item 1'
                . ' structural rather than arithmetic');
        $this->__is(
            1.25,
            ValueTrustTool::factorForGrade($plan, 'A'),
            'the scale that came out of the store is the shipped one');
        $this->__is(
            ValueTrustTool::factorForGrade($plan, 'C'),
            ValueTrustTool::factorForGrade($plan, 'F'),
            'with F equal to C, because the taxonomy says f = 50 = c');
        $this->__is(
            0.0,
            ValueTrustTool::factorForGrade($plan, 'G'),
            'and G a true zero');
    }

    /**
     * The uuid→id join, asserted on its output before any score reads
     * it.
     *
     * @param array $user
     * @param array $profile
     * @param array $subjects
     */
    private function __join(array $user, array $profile, array $subjects)
    {
        $this->out('');
        $this->out('the join a harness cannot do');
        $value = array_keys($subjects)[0];
        $plain = $this->ValueProfile->verdictContextFor(
            $user,
            $value,
            $profile
        );
        $this->__is(
            true,
            isset($plain['trust']),
            'the context carries a trust block even with no grade'
        );
        $this->__is(false, $plain['trust']['in_force'],
            'reporting itself as not in force');

        $uuids = array();
        foreach ($plain['orgs'] as $org) {
            $this->__is(
                true,
                !empty($org['uuid']),
                sprintf('%s carries a uuid on its org row', $org['name'])
            );
            if (!empty($org['uuid'])) {
                $uuids[$org['uuid']] = (int)$org['id'];
            }
        }
        $this->__is(
            true,
            !empty($uuids),
            sprintf('%s has organisations to grade', $value)
        );
        if (empty($uuids)) {
            return;
        }
        $first = array_keys($uuids)[0];
        $graded = $this->__profileWith(
            $profile,
            array('org_trust' => array($first => 'D'))
        );
        $resolved = $this->ValueProfile->verdictContextFor(
            $user,
            $value,
            $graded
        );
        $this->__is(true, $resolved['trust']['in_force'],
            'one grade puts the mechanism in force');
        $this->__is(
            array($uuids[$first] => 'D'),
            $resolved['trust']['grades'],
            sprintf(
                'and the uuid %s resolves to local id %d — the join'
                    . ' meets, which is the one thing a map keyed by'
                    . ' uuid can silently fail at',
                substr($first, 0, 8),
                $uuids[$first]
            )
        );
        $this->__is(
            0.75,
            $resolved['trust']['factors'][$uuids[$first]] ?? null,
            'carrying the grade\'s factor from the scale in force'
        );
        $this->__is(
            true,
            !empty($resolved['trust']['names'][$uuids[$first]]),
            'and the organisation\'s name, for the evidence line'
        );
        $this->__is(array(), $resolved['trust']['unknown'],
            'with nothing unresolved');
    }

    /**
     * §5 item 1, on every demo value: the empty map leaves the ledger
     * where it was, to the unit.
     *
     * The scale is moved at the same time, because that is the case the
     * strong form of the rule is *for*: `unrated` at 0.5 with nobody
     * graded must change nothing, or an analyst who edited a number
     * they were entitled to edit has silently halved their instance.
     *
     * @param array $user
     * @param array $profile
     * @param array $subjects
     */
    private function __unchanged(array $user, array $profile,
        array $subjects
    ) {
        $this->out('');
        $this->out('§5 item 1 — an empty map changes nothing');
        $engine = new ValueVerdictTool($this->ValueProfile);
        $moved = $this->__profileWith($profile, array(
            'org_trust' => array(),
            'org_trust_scale' => array('unrated' => 0.5, 'B' => 0.1),
        ));
        foreach (array_keys($subjects) as $value) {
            $before = $engine->verdictFor($user, $value, array(
                'profile' => $profile,
            ));
            $after = $engine->verdictFor($user, $value, array(
                'profile' => $moved,
            ));
            $this->__is(
                $this->__rows($before),
                $this->__rows($after),
                sprintf('%s: every ledger row identical', $value)
            );
            $this->__is($before['quality'], $after['quality'],
                sprintf('%s: and the quality with them', $value));
            $this->__is($before['lean'], $after['lean'],
                sprintf('%s: and the lean', $value));
        }
    }

    /**
     * §5 items 2 to 5, on whatever this instance's organisations
     * actually are.
     *
     * Asserted as *relationships* rather than as numbers: the demo
     * values' rows move as the instance's data does, and a probe
     * hardcoding `+28` would be asserting the fixture rather than the
     * mechanism.
     *
     * @param array $user
     * @param array $profile
     * @param array $subjects
     */
    private function __graded(array $user, array $profile,
        array $subjects
    ) {
        $this->out('');
        $this->out('§5 items 2 to 5 — the row moves, and the ledger'
            . ' still sums');
        $engine = new ValueVerdictTool($this->ValueProfile);
        $value = $this->__widest($user, $profile, $subjects);
        if ($value === null) {
            $this->out('  ..    no value here has enough reporters');
            return;
        }
        $context = $this->ValueProfile->verdictContextFor(
            $user,
            $value,
            $profile
        );
        $uuids = array();
        foreach ($context['orgs'] as $org) {
            if (!empty($org['uuid'])) {
                $uuids[] = $org['uuid'];
            }
        }
        $this->out(sprintf(
            '  ..    %s, %d reporting organisations',
            $value,
            count($uuids)
        ));
        $base = $engine->assess($context, $profile);
        $baseRow = $this->__row($base, 'reporting.independent_orgs');
        $this->__is(true, $baseRow !== null,
            'the reporting row fires ungraded');
        if ($baseRow === null) {
            return;
        }
        /*
         * **The cap is what this section has to reason about**, and the
         * first run of this probe is why. Eight organisations at 7
         * points each is 56 against a cap of 28, so grading every one
         * of them `D` still leaves 42 and the row does not move — *"D
         * falls"* failed, correctly. The row leaves the cap only once
         * the weighted headcount drops below `cap / per_org`, which is
         * four under the shipped weights. So the assertion is the
         * mechanism itself — the row is exactly
         * `min(per_org × Σ factor, cap)` — and the ladder is checked
         * against that rather than against a hoped-for direction.
         */
        $perOrg = (int)$this->__points($profile, 'per_org');
        $cap = (int)$this->__cap($profile);
        $capVoices = $perOrg > 0 ? $cap / $perOrg : 0;
        $this->out(sprintf(
            '  ..    %d points an organisation, cap %d, so the cap'
                . ' binds above %.1f weighted voices',
            $perOrg,
            $cap,
            $capVoices
        ));
        $this->__is(
            true,
            count($uuids) > $capVoices,
            sprintf(
                '%s is at the cap ungraded (%d voices), which is where'
                    . ' §5 items 2 and 3 are unobservable',
                $value,
                count($uuids)
            )
        );

        $ladder = array();
        foreach (array('A', 'F', 'D', 'E', 'G') as $grade) {
            $map = array_fill_keys($uuids, $grade);
            $with = $this->__profileWith($profile,
                array('org_trust' => $map));
            $scored = $engine->assess(
                $this->ValueProfile->verdictContextFor(
                    $user,
                    $value,
                    $with
                ),
                $with
            );
            $row = $this->__row($scored, 'reporting.independent_orgs');
            $points = $row === null ? null : $row['contribution'];
            $ladder[$grade] = $points;
            $factor = ValueTrustTool::factorForGrade(
                ValueTrustTool::planFor($with),
                $grade
            );
            $voices = $factor * count($uuids);
            $expected = (int)round(min($perOrg * $voices, $cap));
            $this->out(sprintf(
                '  ..    every organisation %s: %d -> %s (%.2f voices)',
                $grade,
                $baseRow['contribution'],
                $points === null ? 'no row' : $points,
                $voices
            ));
            $this->__is(
                $expected,
                $points,
                sprintf(
                    'grade %s scores min(%d × %.2f, %d) exactly — the'
                        . ' cap applied after the weighting, rounded'
                        . ' once',
                    $grade,
                    $perOrg,
                    $voices,
                    $cap
                )
            );
            if ($grade === 'F') {
                $this->__is(
                    $baseRow['contribution'],
                    $points,
                    'F changes the number not at all, identically to'
                        . ' unrated — grading an organisation F records'
                        . ' that you considered them (§5 item 4)'
                );
                $this->__is(
                    true,
                    strpos(
                        $row['evidence'],
                        'weighted by your reliability grades'
                    ) !== false,
                    'and the clause still appears, because a grade did'
                        . ' touch the row'
                );
            }
            if ($grade === 'E') {
                $this->__is(true, $points > 0,
                    'E stays positive — a weighting may not flip a'
                        . ' signal\'s sign (§5 item 4)');
                $this->__is(
                    true,
                    strpos($row['evidence'], '(E)') !== false,
                    'and the evidence names the grade beside the'
                        . ' organisation (§2.5)'
                );
            }
            if ($grade === 'G') {
                /*
                 * A zero row rather than silence, and deliberately: the
                 * reports exist, the reader's own profile discounted
                 * them to nothing, and a silent signal would hide the
                 * discount instead of showing it. The evidence line is
                 * what makes the zero readable.
                 */
                $this->__is(0, $points,
                    'every organisation graded G leaves a row worth'
                        . ' zero rather than no row: the reports exist'
                        . ' and the profile discounted them, and'
                        . ' silence would hide that');
                $this->__is(
                    true,
                    strpos($row['evidence'], 'counts for nothing')
                        !== false,
                    'with the organisations it stopped counting named'
                );
            }
            $sum = 0;
            foreach ($scored['ledger'] as $group) {
                foreach ($group['signals'] as $ledgerRow) {
                    $sum += $ledgerRow['contribution'];
                }
            }
            $this->__is($scored['quality'], $sum,
                sprintf('grade %s: the ledger still sums to the'
                    . ' quality exactly', $grade));
        }
        $this->__is(
            true,
            $ladder['A'] >= $ladder['F'] && $ladder['F'] >= $ladder['D']
                && $ladder['D'] >= $ladder['E']
                && $ladder['E'] >= $ladder['G'],
            'and the ladder is monotone: A >= F >= D >= E >= G'
        );
        $this->__is(
            true,
            $ladder['E'] < $ladder['F'],
            'with at least one strict drop below the cap, or the'
                . ' section would be asserting a mechanism it never saw'
                . ' move'
        );

        /*
         * §5 item 5 — the same map, a different scale. `E` rather than
         * the item's own `D`, for the cap reason above: eight
         * organisations at `D` are still over the cap, so `D` at 0.95
         * and `D` at 0.75 produce the same 28 and prove nothing.
         */
        $map = array_fill_keys($uuids, 'E');
        $shippedE = $this->__profileWith($profile,
            array('org_trust' => $map));
        $nudged = $this->__profileWith($profile, array(
            'org_trust' => $map,
            'org_trust_scale' => array('E' => 0.30),
        ));
        $a = $this->__row(
            $engine->assess(
                $this->ValueProfile->verdictContextFor($user, $value,
                    $shippedE),
                $shippedE
            ),
            'reporting.independent_orgs'
        );
        $b = $this->__row(
            $engine->assess(
                $this->ValueProfile->verdictContextFor($user, $value,
                    $nudged),
                $nudged
            ),
            'reporting.independent_orgs'
        );
        $this->__is(true, $b['contribution'] > $a['contribution'],
            sprintf(
                '§5 item 5: the same E grades with E at 0.30 score %d'
                    . ' rather than %d — the scale is data',
                $b['contribution'],
                $a['contribution']
            )
        );
    }

    /**
     * §5 items 8 and 9 — a grade for an organisation that is not here.
     *
     * The export/import case without a second instance: a profile
     * carrying one uuid this instance has and one it does not.
     *
     * @param array $user
     * @param array $profile
     * @param array $subjects
     */
    private function __absent(array $user, array $profile,
        array $subjects
    ) {
        $this->out('');
        $this->out('§5 items 8 and 9 — a grade for an organisation'
            . ' this instance has never had');
        $value = $this->__widest($user, $profile, $subjects);
        if ($value === null) {
            return;
        }
        $context = $this->ValueProfile->verdictContextFor(
            $user,
            $value,
            $profile
        );
        $here = null;
        foreach ($context['orgs'] as $org) {
            if (!empty($org['uuid'])) {
                $here = $org;
                break;
            }
        }
        $stranger = '00000000-dead-4000-8000-000000000000';
        $this->__is(
            0,
            $this->Organisation->find('count', array(
                'recursive' => -1,
                'conditions' => array('Organisation.uuid' => $stranger),
            )),
            'the stranger uuid really is on no organisation here'
        );
        $mixed = $this->__profileWith($profile, array(
            'org_trust' => array(
                $stranger => 'A',
                $here['uuid'] => 'D',
            ),
        ));
        $resolved = $this->ValueProfile->verdictContextFor(
            $user,
            $value,
            $mixed
        );
        $this->__is(
            array($stranger => 'A'),
            $resolved['trust']['unknown'],
            'it is kept, ignored and reported — never deleted, because'
                . ' the organisation may return or the profile may be'
                . ' shared (§4)'
        );
        $this->__is(
            array((int)$here['id'] => 'D'),
            $resolved['trust']['grades'],
            sprintf(
                'while %s, which is here, weights at its grade (§5'
                    . ' item 9)',
                $here['name']
            )
        );
        $engine = new ValueVerdictTool($this->ValueProfile);
        $scored = $engine->assess($resolved, $mixed);
        $this->__is(
            true,
            $this->__row($scored, 'reporting.independent_orgs') !== null,
            'and the assessment scores rather than erroring (§5 item 8)'
        );
    }

    /**
     * §5 item 12's live half — the source that answered, on real hits.
     *
     * Every origin this instance can reach is asserted; the ones it
     * cannot are named rather than skipped, because a probe that says
     * nothing about an unreachable case reads exactly like one where
     * the case passed.
     *
     * @param array $user
     * @param array $profile
     * @param array $subjects
     */
    private function __categories(array $user, array $profile,
        array $subjects
    ) {
        $this->out('');
        $this->out('§5 item 12 — which step answered, on real hits');
        $seen = array();
        foreach (array_keys($subjects) as $value) {
            $context = $this->ValueProfile->verdictContextFor(
                $user,
                $value,
                $profile
            );
            foreach ($context['warninglist']['hits'] as $hit) {
                $seen[$hit['category_source']][] = sprintf(
                    '%s on %s -> %s',
                    $value,
                    $hit['name'],
                    $hit['category']
                );
                $this->__is(
                    true,
                    in_array($hit['category_source'], array('profile',
                        'shipped', 'list', 'default'), true),
                    sprintf(
                        '%s / %s names one of the four steps: %s',
                        $value,
                        $hit['name'],
                        $hit['category_source']
                    )
                );
            }
        }
        foreach ($seen as $source => $examples) {
            $this->out(sprintf(
                '  ..    %-8s %s',
                $source,
                $examples[0]
            ));
        }
        $this->__is(
            true,
            isset($seen['list']),
            'the database column answers for at least one hit — which'
                . ' on this instance means a custom list, the one place'
                . ' the column is a deliberate statement (§3.2)'
        );
        foreach (array('profile', 'shipped', 'default') as $origin) {
            if (!isset($seen[$origin])) {
                $this->out(sprintf(
                    '  ..    %s is not reachable on the demo values'
                        . ' here; the harness takes it, and dayone'
                        . ' takes "shipped" on real rows',
                    $origin
                ));
            }
        }
    }

    /**
     * §5 items 6 and 7 — a category override on this instance's own
     * listed value, and what it actually changes.
     *
     * The harness found that *"the value goes CONFLICTED"* is the wrong
     * thing to look for whenever the value's organisations assert it:
     * `conflict:listed-vs-asserted` has already made it contested, so
     * the override hands the contradiction to the other rule rather
     * than creating one. This is where that gets confirmed on rows
     * nobody wrote for it.
     *
     * @param array $user
     * @param array $profile
     * @param array $subjects
     */
    private function __override(array $user, array $profile,
        array $subjects
    ) {
        $this->out('');
        $this->out('§5 items 6 and 7 — the override, and what it moves');
        $engine = new ValueVerdictTool($this->ValueProfile);
        $subject = null;
        foreach (array_keys($subjects) as $value) {
            $context = $this->ValueProfile->verdictContextFor(
                $user,
                $value,
                $profile
            );
            foreach ($context['warninglist']['hits'] as $hit) {
                if ($hit['name'] === self::RESOLVERS) {
                    $subject = array($value, $context);
                }
            }
            if ($subject !== null) {
                break;
            }
        }
        if ($subject === null) {
            $this->out(sprintf(
                '  ..    no demo value hits "%s" here',
                self::RESOLVERS
            ));
            return;
        }
        list($value, $context) = $subject;
        $before = $engine->assess($context, $profile);
        $this->out(sprintf(
            '  ..    %s: lean %s, rule %s',
            $value,
            $before['lean'],
            $before['rule']['id'] ?? 'none'
        ));

        $with = $this->__profileWith($profile, array(
            'warninglist_category' => array(self::RESOLVERS => 'known'),
        ));
        $overridden = $this->ValueProfile->verdictContextFor(
            $user,
            $value,
            $with
        );
        $hit = null;
        foreach ($overridden['warninglist']['hits'] as $row) {
            if ($row['name'] === self::RESOLVERS) {
                $hit = $row;
            }
        }
        $this->__is('known', $hit['category'] ?? null,
            'the override reaches the hit');
        $this->__is('profile', $hit['category_source'] ?? null,
            'and the panel names the profile as the source, so the'
                . ' analyst can see it was their own decision (§5'
                . ' item 11)');
        $after = $engine->assess($overridden, $with);
        $this->out(sprintf(
            '  ..    with the override: lean %s, rule %s',
            $after['lean'],
            $after['rule']['id'] ?? 'none'
        ));
        $this->__is(
            'conflict:known-infrastructure-vs-reporting',
            $after['rule']['id'] ?? null,
            '§5 item 6: the escalation phase 3 shipped fires — on this'
                . ' instance\'s rows, for the first time'
        );
        $this->__is('contested', $after['lean'],
            'and the lean is contested');

        $restored = $engine->assess(
            $this->ValueProfile->verdictContextFor(
                $user,
                $value,
                $profile
            ),
            $profile
        );
        $this->__is($before['lean'], $restored['lean'],
            '§5 item 7: the override removed and the value returns to'
                . ' where it was — the evidence did not change, the'
                . ' profile\'s knowledge of it did');
        $this->__is($before['quality'], $restored['quality'],
            'to the unit');
        $this->__is(
            $before['rule']['id'] ?? null,
            $restored['rule']['id'] ?? null,
            'rule and all'
        );
        if (($before['lean'] ?? null) === 'contested') {
            $this->__is(
                true,
                ($before['rule']['id'] ?? null)
                    !== ($after['rule']['id'] ?? null),
                'and the harness\'s finding holds on real rows: the'
                    . ' value was already contested, so what the'
                    . ' override changed is which rule owns the'
                    . ' contradiction'
            );
        }
    }

    /**
     * §3.3's retirement criterion, measured.
     *
     * The question *"has V2 landed?"* answered by an instance rather
     * than by reading two PRs — and the same read re-confirms §3.1 on
     * this instance's own rows.
     */
    private function __retirement()
    {
        $this->out('');
        $this->out('§3.3 — whether V1 can retire yet');
        $rows = $this->Warninglist->find('all', array(
            'recursive' => -1,
            'fields' => array('Warninglist.name', 'Warninglist.category'),
        ));
        $categories = array();
        $known = 0;
        foreach ($rows as $row) {
            $categories[$row['Warninglist']['name']] =
                $row['Warninglist']['category'];
            if ($row['Warninglist']['category'] === 'known') {
                $known++;
            }
        }
        $this->out(sprintf(
            '  ..    %d lists on this instance, %d carrying "known"',
            count($rows),
            $known
        ));
        $verdict = WarninglistCategory::retirable($categories);
        $this->__is(false, $verdict['retirable'],
            'V1 cannot retire: no roster entry\'s row carries the'
                . ' category the map hardcodes');
        $this->__is(
            0,
            count($verdict['confirmed']),
            'not one of the 25 has landed, which re-confirms §3.1 from'
                . ' the other end — the field is not imported, so an'
                . ' upstream fix alone would change nothing'
        );
        $present = 0;
        foreach (WarninglistCategory::KNOWN_LISTS as $name) {
            if (isset($categories[$name])) {
                $present++;
            }
        }
        $this->__is(
            25,
            $present,
            'while all 25 roster names match a list that is actually'
                . ' here — the names, not the directory names, are the'
                . ' load-bearing part of the map'
        );
    }

    /**
     * What the join costs, and that an empty map costs nothing.
     *
     * @param array $user
     * @param array $profile
     * @param array $subjects
     */
    private function __cost(array $user, array $profile,
        array $subjects
    ) {
        $this->out('');
        $this->out('what the section costs');
        $value = array_keys($subjects)[0];
        $plain = $this->__queries($user, $value, $profile);
        $graded = null;
        $context = $this->ValueProfile->verdictContextFor(
            $user,
            $value,
            $profile
        );
        foreach ($context['orgs'] as $org) {
            if (!empty($org['uuid'])) {
                $graded = $this->__profileWith($profile, array(
                    'org_trust' => array($org['uuid'] => 'B'),
                ));
                break;
            }
        }
        if ($graded === null) {
            return;
        }
        $weighted = $this->__queries($user, $value, $graded);
        $this->out(sprintf(
            '  ..    %d queries with an empty map, %d with one grade',
            $plain,
            $weighted
        ));
        $this->__is(
            1,
            $weighted - $plain,
            'the join is exactly one query, and only when the map has'
                . ' something in it'
        );
    }

    /**
     * How many queries one context build costs.
     *
     * The cap comes off the datasource log first, and phase 5 §7.5 is
     * why the line is here rather than assumed: `DboSource` keeps 200
     * entries, this probe has already spent them by the time the
     * section runs, and a log that does not grow reads as *0 queries*
     * — which passed once already, in the phase written to stop
     * exactly that.
     *
     * @param array $user
     * @param string $value
     * @param array $profile
     * @return int
     */
    private function __queries(array $user, $value, array $profile)
    {
        $db = ConnectionManager::getDataSource('default');
        $db->fullDebug = true;
        $max = new ReflectionProperty($db, '_queriesLogMax');
        $max->setAccessible(true);
        $max->setValue($db, 1000000);
        $before = count($db->getLog(false, false)['log']);
        $this->ValueProfile->verdictContextFor($user, $value, $profile);
        return count($db->getLog(false, false)['log']) - $before;
    }

    /**
     * The value with the most reporting organisations, which is where a
     * weighting has the most to move.
     *
     * @param array $user
     * @param array $profile
     * @param array $subjects
     * @return string|null
     */
    private function __widest(array $user, array $profile,
        array $subjects
    ) {
        $best = null;
        $most = 0;
        foreach (array_keys($subjects) as $value) {
            $summary = $this->Value->recordSummaryFor($user, $value);
            if ((int)$summary['orgs'] > $most) {
                $most = (int)$summary['orgs'];
                $best = $value;
            }
        }
        return $most >= 2 ? $best : null;
    }

    /**
     * The `reporting.independent_orgs` cap in the profile in force.
     *
     * @param array $profile
     * @return int
     */
    private function __cap(array $profile)
    {
        return $this->__points($profile, 'cap');
    }

    /**
     * One `reporting.independent_orgs` points value from the profile in
     * force, so the section reasons about the cap the analyst has
     * rather than the one this file remembers.
     *
     * @param array $profile
     * @param string $key
     * @return int
     */
    private function __points(array $profile, $key)
    {
        foreach ($profile['parameters']['signals'] as $entry) {
            if ($entry['id'] === 'reporting.independent_orgs') {
                return (int)$entry['points'][$key];
            }
        }
        return 0;
    }

    /**
     * One ledger row by signal id.
     *
     * @param array $verdict
     * @param string $id
     * @return array|null
     */
    private function __row(array $verdict, $id)
    {
        foreach ($verdict['ledger'] as $group) {
            foreach ($group['signals'] as $row) {
                if (($row['id'] ?? null) === $id) {
                    return $row;
                }
            }
        }
        return null;
    }

    /**
     * Every contribution, keyed by signal id.
     *
     * @param array $verdict
     * @return array
     */
    private function __rows(array $verdict)
    {
        $out = array();
        foreach ($verdict['ledger'] as $group) {
            foreach ($group['signals'] as $row) {
                $out[$row['id']] = $row['contribution'];
            }
        }
        ksort($out);
        return $out;
    }

    /**
     * The profile in force with its `reference` section overridden.
     *
     * @param array $profile
     * @param array $reference
     * @return array
     */
    private function __profileWith(array $profile, array $reference)
    {
        $profile['parameters']['reference'] = array_merge(
            isset($profile['parameters']['reference'])
                ? $profile['parameters']['reference']
                : array(),
            $reference
        );
        return $profile;
    }

    /**
     * Flip one list's `enabled` and rebuild the caches core rebuilds.
     *
     * @param int $id
     * @param int $enabled
     */
    private function __enable($id, $enabled)
    {
        $this->Warninglist->id = $id;
        $this->Warninglist->saveField('enabled', (int)$enabled);
        $this->Warninglist->regenerateWarninglistCaches($id);
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
        if (empty($subjects)) {
            $this->error('None of the demo values exist here.');
        }
        foreach ($subjects as $value => $shape) {
            $this->out(sprintf('  ..    %-16s %s', $value, $shape));
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

    private function __report()
    {
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
