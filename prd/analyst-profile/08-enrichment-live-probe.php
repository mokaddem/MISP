<?php

App::uses('ValueEnrichmentTool', 'Tools');
App::uses('ModuleLocality', 'Tools');

/**
 * Phase 7's declaration, against a real modules service.
 *
 * The harness proves the resolution. What needs an instance is every
 * fact the harness hands over as a literal — and one of them is the
 * whole phase: **`Module::getEnabledModules()` has already thrown away
 * the difference** between a module that is turned off, one reserved
 * for another organisation, one missing from the build and one that
 * never accepted the type, and `08-enrichment.md` §2.1 says all four
 * must be stated rather than dropped. Whether the second `GET
 * /modules` really recovers them is not a claim a fixture can settle.
 *
 * It also takes §5's items a harness cannot reach:
 *
 *   - **item 1**'s live half — the section survives the store and
 *     comes back out of `resolveFor()` intact;
 *   - **items 2 and 3** on this instance's own settings rather than on
 *     invented facts, with item 3 probed as a member of neither
 *     organisation, since `canUse()` passes a site admin through every
 *     restriction and the condition would be false for them;
 *   - **item 4**, the posture conflict, on real modules whose locality
 *     is the shipped map's answer rather than a fixture's;
 *   - **item 5**, *nothing runs* — asserted three ways: the row counts
 *     either side of every call, the modules service's own request log
 *     (read from outside this process, since a page that queried a
 *     module would not report it), and a run with the service
 *     unreachable, which must be indistinguishable from one where it
 *     is reachable and nothing is declared.
 *
 * Nothing here writes a profile. Every declaration is passed through
 * `forEnrichment()`'s `profile` option — the seam phase 8's editor
 * needs — so the instance's own stored profiles are untouched and the
 * probe leaves no rows behind.
 *
 * Run:
 *   cp prd/analyst-profile/08-enrichment-live-probe.php \
 *      app/Console/Command/AnalystEnrichmentProbeShell.php
 *   app/Console/cake AnalystEnrichmentProbe run
 *   app/Console/cake AnalystEnrichmentProbe unreachable
 *   rm app/Console/Command/AnalystEnrichmentProbeShell.php
 */
class AnalystEnrichmentProbeShell extends AppShell
{
    public $uses = array('AnalystProfile', 'ValueProfile', 'Value',
        'User', 'Module', 'MispAttribute', 'MispObject', 'Event');

    /** Four types on this instance, and three modules take some. */
    const VALUE = '8.8.8.8';

    /** Enabled here, local, and accepts `text` — which this value is. */
    const LOCAL_MODULE = 'convert_markdown_to_pdf';

    /** Enabled here, and every query it makes leaves the building. */
    const EXTERNAL_MODULE = 'circl_passivedns';

    /** Present in the build, not enabled on this instance. */
    const DISABLED_MODULE = 'virustotal';

    /** Enabled, usable, and takes hashes rather than addresses. */
    const MISMATCHED_MODULE = 'hashlookup';

    /** No module of this name exists anywhere. */
    const ABSENT_MODULE = 'not_a_real_module';

    /** A user in an organisation of their own, and not a site admin. */
    const OTHER_ORG_USER = 'orgadmin@circl.lu';

    /** Where misp-modules listens, and its own default. */
    const MODULES_PORT = 6666;

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

        $before = $this->__rowCounts();

        $this->__preflight();
        $this->__section($profile);
        $this->__inert($user, $profile);
        $this->__ground($user);
        $this->__posture($user);
        $this->__missing($user);
        $this->__restricted($user);
        $this->__unused($user);
        $this->__override($user);
        $this->__cost($user);
        $this->__wrote($before);

        $this->__report();
    }

    /**
     * §5 item 5's other half: the same page load with nobody
     * answering the door.
     *
     * Its own command because `Configure::write` on the services URL
     * is process-wide, and every assertion above it depends on the
     * service being up. What must hold is that the declaration
     * produces **one** condition about the service and **none** about
     * the modules: a reader told their profile names a module this
     * instance does not offer, when the truth is that the list could
     * not be read, goes and edits a profile that was right.
     */
    public function unreachable()
    {
        $this->out('');
        $user = $this->__user();
        if (empty($user)) {
            $this->error('No site admin on this instance to probe as.');
        }
        $before = $this->__rowCounts();
        Configure::write(
            'Plugin.Enrichment_services_url',
            'http://127.0.0.1'
        );
        Configure::write('Plugin.Enrichment_services_port', 1);

        $this->out('the service is unreachable');
        $catalogue = $this->__catalogue($user, array(
            'ip-dst' => array(self::EXTERNAL_MODULE),
            'text' => array(self::LOCAL_MODULE),
        ), 'allow_external');
        $this->__is(
            false,
            $catalogue['service']['reachable'],
            'the catalogue says the service did not answer'
        );
        $declaration = $catalogue['profile'];
        $this->__is(
            array('service.unreachable'),
            $this->__ids($declaration),
            'and the declaration states exactly that, once'
        );
        $this->__is(
            array(),
            $this->__names($declaration),
            'nothing is selected'
        );
        $this->__is(
            2,
            $declaration['applicable'],
            'while the declaration itself is still reported in full'
        );
        $this->__is(
            0,
            count($catalogue['modules']),
            'and the rail is empty, which is the tab\'s own state'
        );

        /*
         * The comparison §5 item 5 actually asks for. With nothing
         * declared, an unreachable service and a reachable one differ
         * only in the service block — the profile block is the same
         * empty object either way, because a declaration nobody made
         * has nothing to say about a service nobody could reach.
         */
        $empty = $this->__catalogue($user, array(), 'local_only');
        $this->__is(
            array(),
            $empty['profile']['conditions'],
            'and with nothing declared, an unreachable service is'
                . ' indistinguishable from a reachable one'
        );
        $this->__wrote($before);
        $this->__report();
    }

    /**
     * Is anybody home?
     *
     * **Found by this probe rather than assumed:** on 2026-09-07 this
     * instance had `Plugin.Enrichment_services_port` set to `6677`
     * while misp-modules listens on `6666`, so *every* enrichment
     * surface on it — this page's tab included — was reporting a dead
     * service. Phase 28 measured the same tab at 9 ms a day earlier,
     * so the setting moved in between.
     *
     * That is a fact about the deployment and not about this phase,
     * and the probe must not silently absorb it: a run where nothing
     * is reachable passes half these assertions for the wrong reason,
     * which is exactly what the first attempt did. So the port is
     * corrected **for this process only** — `Configure::write` touches
     * no file and nothing else on the instance sees it — and the
     * correction is announced.
     */
    private function __preflight()
    {
        $this->out('the modules service');
        $url = Configure::read('Plugin.Enrichment_services_url');
        $port = Configure::read('Plugin.Enrichment_services_port');
        if (is_array($this->Module->getModules('Enrichment'))) {
            $this->__is(
                true,
                true,
                sprintf('answers at %s:%s as configured', $url, $port)
            );
            return;
        }
        Configure::write(
            'Plugin.Enrichment_services_port',
            self::MODULES_PORT
        );
        $recovered = is_array($this->Module->getModules('Enrichment'));
        $this->out(sprintf(
            '  NOTE  this instance has the service on %s:%s, where'
                . ' nothing answers; %s:%d %s',
            $url,
            $port,
            $url,
            self::MODULES_PORT,
            $recovered ? 'does' : 'does not either'
        ));
        $this->__is(
            true,
            $recovered,
            'the service answers once the port is corrected for this'
                . ' process — an instance setting, not a phase 7 fault'
        );
        if (!$recovered) {
            $this->error(
                'No modules service to probe against; every assertion'
                . ' below would pass for the wrong reason.'
            );
        }
    }

    /**
     * The section, as it comes back out of the store.
     */
    private function __section(array $profile)
    {
        $this->out('the shipped enrichment section');
        $section = ValueEnrichmentTool::section($profile);
        $this->__is(
            true,
            isset($section['locality_posture']),
            'the profile in force carries a locality posture — not a'
                . ' cost one, which is what it was called and never was'
        );
        $this->__is(
            'local_only',
            $section['locality_posture'],
            'and it is local only, which is the only defensible'
                . ' default for a setting one person can apply to a'
                . ' whole organisation'
        );
        $this->__is(
            array(),
            isset($section['auto_run']) ? $section['auto_run'] : null,
            'the module list ships empty'
        );
        $this->__is(
            24,
            isset($section['max_age_hours'])
                ? $section['max_age_hours']
                : null,
            'and the reuse window survives the store as an integer'
                . ' (§5 item 1)'
        );
        $plan = ValueEnrichmentTool::planFor($profile);
        $this->__is(
            false,
            $plan['in_force'],
            'so the declaration is not in force on day one — the'
                . ' module list is the switch'
        );
        $this->__is(
            true,
            $plan['reuse_inert'],
            'and the window is carried as inert, because the store it'
                . ' would govern does not exist'
        );
    }

    /**
     * **The strong form of "empty means as before".** With the shipped
     * default in force, the tab is the one phase 28 shipped: same
     * rows, same order, same everything, and an empty profile block
     * beside it.
     */
    private function __inert(array $user, array $profile)
    {
        $this->out('');
        $this->out('the shipped default changes nothing');
        $with = $this->ValueProfile->forEnrichment($user, self::VALUE);
        $without = $this->ValueProfile->forEnrichment(
            $user,
            self::VALUE,
            array('profile' => null)
        );
        $this->__is(
            json_encode($without['enrichment']['modules']),
            json_encode($with['enrichment']['modules']),
            'the rail is byte-identical with the profile and without'
                . ' one'
        );
        $declaration = $with['enrichment']['profile'];
        $this->__is(
            false,
            $declaration['in_force'],
            'the profile block is not in force'
        );
        $this->__is(
            array(),
            $declaration['conditions'],
            'states nothing'
        );
        $this->__is(
            array(),
            $declaration['selected'],
            'and selects nothing'
        );
        $this->__is(
            0,
            $declaration['leaving'],
            'so nothing in the selection leaves the instance'
        );
        $this->__is(
            $profile['name'],
            $declaration['name'],
            'while still naming the profile that produced it'
        );
    }

    /**
     * What this value actually is, and what the instance offers for
     * it. Every later section reads off these, so they are asserted
     * rather than assumed — phase 4 §7.3's lesson, where 44 checks
     * passed against a context shape that did not exist.
     */
    private function __ground(array $user)
    {
        $this->out('');
        $this->out('the ground truth');
        $catalogue = $this->__catalogue($user, array(), 'local_only');
        $types = array();
        foreach ($catalogue['types'] as $row) {
            $types[$row['type']] = $row['count'];
        }
        $this->out(sprintf(
            '  %s is %d types: %s',
            self::VALUE,
            count($types),
            json_encode($types)
        ));
        $this->__is(
            true,
            isset($types['text']),
            sprintf(
                'this value carries a `text` occurrence, which is what'
                    . ' puts a local module on the rail at all'
            )
        );
        $names = array();
        $localities = array();
        foreach ($catalogue['modules'] as $row) {
            $names[] = $row['name'];
            $localities[$row['name']] = $row['locality'];
        }
        $this->out(sprintf(
            '  %d eligible: %s',
            count($names),
            json_encode($localities)
        ));
        $this->__is(
            true,
            in_array(self::LOCAL_MODULE, $names, true),
            sprintf('%s is eligible', self::LOCAL_MODULE)
        );
        $this->__is(
            'local',
            $localities[self::LOCAL_MODULE],
            'and the shipped map calls it local'
        );
        $this->__is(
            true,
            in_array(self::EXTERNAL_MODULE, $names, true),
            sprintf('%s is eligible', self::EXTERNAL_MODULE)
        );
        $this->__is(
            'unknown',
            $localities[self::EXTERNAL_MODULE],
            'and the shipped map says nothing about it, which the'
                . ' posture treats as leaving the building'
        );

        /*
         * The asymmetry worth recording rather than hiding: the local
         * roster is mostly file and syntax modules, so on an ordinary
         * value the shipped posture will select little or nothing.
         */
        $local = 0;
        foreach ($localities as $locality) {
            if ($locality === 'local') {
                $local++;
            }
        }
        $this->out(sprintf(
            '  %d of %d eligible modules answer from inside',
            $local,
            count($names)
        ));
    }

    /**
     * §5 item 4 — the posture, on real modules.
     */
    private function __posture(array $user)
    {
        $this->out('');
        $this->out('the locality posture');
        $declaration = array(
            'text' => array(self::LOCAL_MODULE),
            'ip-dst' => array(self::EXTERNAL_MODULE),
        );

        $local = $this->__catalogue($user, $declaration, 'local_only');
        $this->__is(
            array(self::LOCAL_MODULE),
            $this->__names($local['profile']),
            'local_only selects the module that answers from inside'
        );
        $this->__is(
            array('posture.external'),
            $this->__ids($local['profile']),
            'and states the withholding of the one that does not'
        );
        $this->__is(
            0,
            $local['profile']['leaving'],
            'nothing in the selection leaves the instance'
        );

        $external = $this->__catalogue(
            $user,
            $declaration,
            'allow_external'
        );
        $this->__is(
            array(self::EXTERNAL_MODULE, self::LOCAL_MODULE),
            $this->__names($external['profile']),
            'allow_external selects both'
        );
        $this->__is(
            array(),
            $this->__ids($external['profile']),
            'with nothing to state'
        );
        $this->__is(
            1,
            $external['profile']['leaving'],
            'and one of the two leaves the instance'
        );

        /*
         * D15's honest statement, on real rows: while every run takes
         * a press, `ask` cannot mean anything `allow_external` does
         * not.
         */
        $ask = $this->__catalogue($user, $declaration, 'ask');
        $askBlock = $ask['profile'];
        $askBlock['posture'] = 'allow_external';
        $this->__is(
            json_encode($external['profile']),
            json_encode($askBlock),
            'and `ask` resolves identically, posture aside'
        );

        /*
         * The run type is the declared one. `circl_passivedns` accepts
         * four of this value's types and the profile filed it under
         * one.
         */
        $selected = array();
        foreach ($external['profile']['selected'] as $entry) {
            $selected[$entry['name']] = $entry['type'];
        }
        $this->__is(
            'ip-dst',
            $selected[self::EXTERNAL_MODULE],
            'a run would use the type the profile filed it under'
        );
    }

    /**
     * §5 items 2 and 3's first half, plus the two the specification
     * did not name: absent from the build, and filed under a type the
     * module does not take.
     */
    private function __missing(array $user)
    {
        $this->out('');
        $this->out('why a declared module is not on the rail');

        $disabled = $this->__catalogue($user, array(
            'ip-dst' => array(self::DISABLED_MODULE),
        ), 'allow_external');
        $this->__is(
            array('module.disabled'),
            $this->__ids($disabled['profile']),
            sprintf(
                '%s is in the build and not enabled here: stated as'
                    . ' disabled',
                self::DISABLED_MODULE
            )
        );
        $this->__is(
            array(),
            $this->__names($disabled['profile']),
            'and not selected'
        );

        $absent = $this->__catalogue($user, array(
            'ip-dst' => array(self::ABSENT_MODULE),
        ), 'allow_external');
        $this->__is(
            array('module.not_offered'),
            $this->__ids($absent['profile']),
            'a name no module answers to is stated as not offered'
        );

        $mismatch = $this->__catalogue($user, array(
            'ip-dst' => array(self::MISMATCHED_MODULE),
        ), 'allow_external');
        $this->__is(
            array('module.type_mismatch'),
            $this->__ids($mismatch['profile']),
            sprintf(
                '%s is enabled and takes hashes: stated as a type'
                    . ' mismatch',
                self::MISMATCHED_MODULE
            )
        );
        $note = $mismatch['profile']['conditions'][0]['note'];
        $this->__is(
            true,
            strpos($note, 'md5') !== false,
            'and the sentence names what it does take'
        );
    }

    /**
     * §5 item 3 — a module this instance reserves for one
     * organisation, read as a member of another.
     *
     * `Configure::write` rather than a saved setting: the restriction
     * exists for this process and nothing else on the instance changes.
     * The site admin's reading is asserted beside it, because
     * `canUse()` passes them through every restriction — telling them
     * the module was reserved away would be false, and the two
     * readings differing is the point rather than a wrinkle.
     */
    private function __restricted(array $user)
    {
        $this->out('');
        $this->out('a module reserved for one organisation');
        $other = $this->User->find('first', array(
            'recursive' => -1,
            'conditions' => array(
                'User.email' => self::OTHER_ORG_USER,
                'User.disabled' => 0,
            ),
        ));
        if (empty($other['User']['id'])) {
            $this->out('  skipped — no second-organisation user here');
            return;
        }
        $member = $this->User->getAuthUser($other['User']['id']);
        $key = 'Plugin.Enrichment_' . self::EXTERNAL_MODULE
            . '_restrict';
        $was = Configure::read($key);
        Configure::write($key, (int)$user['org_id']);
        try {
            $declaration = array(
                'ip-dst' => array(self::EXTERNAL_MODULE),
            );
            $theirs = $this->__catalogue(
                $member,
                $declaration,
                'allow_external'
            );
            $this->__is(
                array('module.restricted'),
                $this->__ids($theirs['profile']),
                sprintf(
                    'a member of %s is told the module is reserved'
                        . ' elsewhere',
                    $member['Organisation']['name']
                )
            );
            $this->__is(
                array(),
                $this->__names($theirs['profile']),
                'and nothing is selected for them'
            );
            $mine = $this->__catalogue(
                $user,
                $declaration,
                'allow_external'
            );
            $this->__is(
                array(),
                $this->__ids($mine['profile']),
                'while the site admin, whom canUse() passes through'
                    . ' every restriction, is told nothing'
            );
            $this->__is(
                array(self::EXTERNAL_MODULE),
                $this->__names($mine['profile']),
                'and gets the selection'
            );
        } catch (Exception $e) {
            Configure::write($key, $was);
            throw $e;
        }
        Configure::write($key, $was);
    }

    /**
     * A declaration for types this value is not.
     */
    private function __unused(array $user)
    {
        $this->out('');
        $this->out('a declaration this value cannot use');
        $catalogue = $this->__catalogue($user, array(
            'md5' => array(self::MISMATCHED_MODULE),
            'sha256' => array(self::MISMATCHED_MODULE),
            'text' => array(self::LOCAL_MODULE),
        ), 'local_only');
        $this->__is(
            array('type.unused'),
            $this->__ids($catalogue['profile']),
            'the types this value does not have are one condition'
        );
        $this->__is(
            array('md5', 'sha256'),
            $catalogue['profile']['conditions'][0]['subjects'],
            'naming both'
        );
        $this->__is(
            array(self::LOCAL_MODULE),
            $this->__names($catalogue['profile']),
            'while the type it does have still resolves'
        );
    }

    /**
     * The profile's locality override, end to end — the only way an
     * operator who repointed a module at their own service can say so.
     *
     * It has to travel through `enrichmentEligible()`, because that is
     * where the rows are stamped and the row is what the decision
     * reads. An override that reached the tool but not the row would
     * put a `profile` chip on the rail and a different answer in the
     * posture.
     */
    private function __override(array $user)
    {
        $this->out('');
        $this->out('the locality override');
        $catalogue = $this->__catalogue(
            $user,
            array('ip-dst' => array(self::EXTERNAL_MODULE)),
            'local_only',
            array(self::EXTERNAL_MODULE => 'local')
        );
        $this->__is(
            array(self::EXTERNAL_MODULE),
            $this->__names($catalogue['profile']),
            'an override makes the module local for this profile'
        );
        $this->__is(
            'profile',
            $catalogue['profile']['selected'][0]['locality_source'],
            'and the selection says where that came from'
        );
        $row = null;
        foreach ($catalogue['modules'] as $candidate) {
            if ($candidate['name'] === self::EXTERNAL_MODULE) {
                $row = $candidate;
            }
        }
        $this->__is(
            'local',
            $row['locality'],
            'the rail row carries the same answer, from the same'
                . ' producer'
        );
    }

    /**
     * What the declaration costs, in outbound calls to the modules
     * service.
     *
     * There is no query log for HTTP, so this measures the only thing
     * a process can measure about its own calls: elapsed time and the
     * conditions that could only exist if the second call was made.
     * The **count** is asserted from outside, against the modules
     * container's own request log.
     */
    private function __cost(array $user)
    {
        $this->out('');
        $this->out('what it costs');

        $started = microtime(true);
        $this->__catalogue($user, array(), 'local_only');
        $bare = (int)round((microtime(true) - $started) * 1000);

        $started = microtime(true);
        $clean = $this->__catalogue($user, array(
            'text' => array(self::LOCAL_MODULE),
        ), 'local_only');
        $resolves = (int)round((microtime(true) - $started) * 1000);

        $started = microtime(true);
        $dirty = $this->__catalogue($user, array(
            'ip-dst' => array(self::DISABLED_MODULE),
        ), 'allow_external');
        $explains = (int)round((microtime(true) - $started) * 1000);

        $this->out(sprintf(
            '  nothing declared %d ms · declaration resolves %d ms ·'
                . ' declaration needs explaining %d ms',
            $bare,
            $resolves,
            $explains
        ));
        $this->__is(
            array(),
            $this->__ids($clean['profile']),
            'a declaration that resolves cleanly states nothing, so'
                . ' the second call was never needed'
        );
        $this->__is(
            array('module.disabled'),
            $this->__ids($dirty['profile']),
            'and a declaration that does not could only have been'
                . ' explained by making it'
        );
    }

    /**
     * §5 item 5 — nothing ran, and nothing was written.
     *
     * The row counts are phase 28's own check, kept because it is the
     * one that would catch an enrichment *result* becoming a record.
     * The outbound half cannot be seen from in here at all, which is
     * why the modules container's request log is read from outside.
     */
    private function __wrote(array $before)
    {
        $this->out('');
        $this->out('nothing was written');
        $after = $this->__rowCounts();
        foreach ($before as $table => $count) {
            $this->__is(
                $count,
                $after[$table],
                sprintf('%s unchanged at %d', $table, $count)
            );
        }
    }

    /**
     * @param array $user
     * @param array $autoRun
     * @param string $posture
     * @param array $locality
     * @return array The `enrichment` block
     */
    private function __catalogue(array $user, array $autoRun, $posture,
        array $locality = array()
    ) {
        $result = $this->ValueProfile->forEnrichment(
            $user,
            self::VALUE,
            array('profile' => array(
                'name' => 'probe',
                'parameters' => array('enrichment' => array(
                    'auto_run' => $autoRun,
                    'locality_posture' => $posture,
                    'max_age_hours' => 24,
                    'locality' => $locality,
                )),
            ))
        );
        return $result['enrichment'];
    }

    /**
     * @param array $declaration
     * @return array
     */
    private function __ids(array $declaration)
    {
        $ids = array();
        foreach ($declaration['conditions'] as $condition) {
            $ids[] = $condition['id'];
        }
        return $ids;
    }

    /**
     * @param array $declaration
     * @return array
     */
    private function __names(array $declaration)
    {
        $names = array();
        foreach ($declaration['selected'] as $entry) {
            $names[] = $entry['name'];
        }
        sort($names);
        return $names;
    }

    /**
     * @return array
     */
    private function __rowCounts()
    {
        return array(
            'attributes' => $this->MispAttribute->find('count', array(
                'recursive' => -1,
            )),
            'objects' => $this->MispObject->find('count', array(
                'recursive' => -1,
            )),
            'events' => $this->Event->find('count', array(
                'recursive' => -1,
            )),
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
