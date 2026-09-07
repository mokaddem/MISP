<?php

App::uses('AnalystProfileFormTool', 'Tools');
App::uses('ValueVerdictTool', 'Tools');
App::uses('ValueVerdictDiffTool', 'Tools');
App::uses('ValueUrlTool', 'Tools');
App::uses('ACLComponent', 'Controller/Component');

/**
 * Phase 8a's contract, against real rows.
 *
 * The harness proves the diff is arithmetic and the merge is
 * conservative. What needs an instance is everything about **ownership
 * and cost**:
 *
 *   - **§7a item 1, both directions.** `findMissingFunctionNames()`
 *     reports actions with no ACL entry. Nothing in MISP reports the
 *     opposite — an ACL entry naming an action that does not exist —
 *     which is the dead row 02-store.md §6 refused to ship in phase 1,
 *     so this asserts it here.
 *   - **§7a item 2**, the index board for every user on this instance
 *     rather than for four invented ones. It became checkable at all
 *     because the board moved out of the controller (`indexFor()`).
 *   - **§7a items 3, 7, 10 and 11** — the fork swap, the revision
 *     rules, and a simulation of a profile the caller cannot edit
 *     writing nothing, asserted by reading `revision` and `modified`
 *     back.
 *   - **§7a item 12 and its cost**, which is the finding this phase
 *     could not have reached from a harness: a candidate whose
 *     `exclusions` differ is a *different context*, and the number of
 *     context builds is measured rather than assumed.
 *
 * It leaves the instance as it found it, and says so — every profile it
 * creates is deleted and the enabled one it displaced is restored. Run
 * it twice.
 *
 * The HTTP layer is `09a-contract-http-probe.sh`: status codes, the
 * refusal shape and the JSON payloads are the one thing a shell cannot
 * see.
 *
 * Run:
 *   cp prd/analyst-profile/09a-contract-live-probe.php \
 *      app/Console/Command/AnalystEditorProbeShell.php
 *   app/Console/cake AnalystEditorProbe run
 *   rm app/Console/Command/AnalystEditorProbeShell.php
 */
class AnalystEditorProbeShell extends AppShell
{
    public $uses = array('AnalystProfile', 'ValueProfile', 'Value',
        'User', 'Organisation');

    /** A value with enough evidence that most signals fire here. */
    const VALUE = '8.8.8.8';

    /** Every action the ACL block is expected to name. */
    const ACTIONS = array('index', 'view', 'edit', 'fork', 'delete',
        'enable', 'disable', 'export', 'import', 'simulate', 'pin',
        'unpin', 'update');

    private $checks = 0;
    private $failures = 0;

    /** Profile ids this run created, deleted on the way out. */
    private $created = array();

    /** Profiles this run disabled, re-enabled on the way out. */
    private $disabled = array();

    public function run()
    {
        $this->out('');
        $this->out('== the ACL block, both directions ==');
        $this->__acl();

        $this->out('');
        $this->out('== the index board, per user ==');
        $this->__board();

        $this->out('');
        $this->out('== revision and version ==');
        $this->__counters();

        $this->out('');
        $this->out('== the fork swap ==');
        $this->__fork();

        $this->out('');
        $this->out('== the simulator writes nothing ==');
        $this->__simulate();

        $this->out('');
        $this->out('== what a simulation costs ==');
        $this->__cost();

        $this->__cleanup();
        $this->__report();
    }

    /**
     * Every action has an entry, and every entry names an action.
     *
     * The second half is what MISP has no check for. A dead entry is
     * harmless today and a trap later: it names a permission for an
     * action nobody wrote, so whoever writes that action inherits an
     * access decision made by someone who had a different action in
     * mind.
     *
     * @return void
     */
    private function __acl()
    {
        $list = ACLComponent::ACL_LIST;
        $this->__is(true, isset($list['analystProfiles']),
            'the ACL block exists, keyed as the controller inflects');
        $entries = array_keys($list['analystProfiles']);
        sort($entries);
        $expected = self::ACTIONS;
        sort($expected);
        $this->__is($expected, $entries,
            'and names exactly the thirteen actions, no more and no'
                . ' fewer');

        $file = APP . 'Controller/AnalystProfilesController.php';
        $source = file_get_contents($file);
        preg_match_all('/\n    public function ([a-zA-Z]+)\s*\(/', $source,
            $found);
        $public = array_values(array_diff($found[1],
            array('beforeFilter', 'beforeRender', 'afterFilter')));
        sort($public);
        $this->__is($expected, $public,
            'the controller has exactly those public methods, so no'
                . ' entry is dead and no action is unguarded');

        /*
         * D13 in one assertion: nothing here names a permission except
         * `update`. Every other action is `*` and the model decides,
         * because the ACL cannot express *"your own, or your
         * organisation's if you are an org admin"* and two opinions
         * about that is how the looser one wins.
         */
        $gated = array();
        foreach ($list['analystProfiles'] as $action => $rule) {
            if ($rule !== array('*')) {
                $gated[] = $action;
            }
        }
        $this->__is(array('update'), $gated,
            'only `update` is gated in the ACL; the rest is the'
                . ' model\'s decision (D13)');
    }

    /**
     * The board for every enabled user on this instance.
     *
     * @return void
     */
    private function __board()
    {
        $users = $this->User->find('all', array(
            'recursive' => -1,
            'contain' => array('Role', 'Organisation'),
            'conditions' => array('User.disabled' => 0),
            'order' => array('User.id ASC'),
            'limit' => 12,
        ));
        $this->__is(true, count($users) > 0,
            sprintf('this instance has %d enabled users to read as',
                count($users)));
        foreach ($users as $row) {
            $user = $this->User->getAuthUser($row['User']['id']);
            if (empty($user)) {
                continue;
            }
            $board = $this->AnalystProfile->indexFor($user);
            $inForce = 0;
            $states = array();
            foreach ($board['profiles'] as $profile) {
                if ($profile['in_force']) {
                    $inForce++;
                }
                $states[] = $profile['standing']['state'];
            }
            $expected = $board['in_force'] === null ? 0 : 1;
            $this->__is($expected, $inForce, sprintf(
                'user %d resolves to exactly %d profile in force, and'
                    . ' the board agrees',
                $row['User']['id'],
                $expected
            ));
            /*
             * The point of the standing: every row that is *not* in
             * force carries a reason, so no reader is left wondering
             * why the fork they are editing is not the one weighting
             * their pages.
             */
            $unexplained = 0;
            foreach ($states as $state) {
                if (!in_array($state, array('in_force', 'disabled',
                    'overridden', 'other_owner', 'unresolved'), true)
                ) {
                    $unexplained++;
                }
            }
            $this->__is(0, $unexplained, sprintf(
                'and every one of user %d\'s %d rows carries a standing',
                $row['User']['id'],
                count($states)
            ));
        }
    }

    /**
     * A rename moves neither counter; a weight moves `revision` only.
     *
     * @return void
     */
    private function __counters()
    {
        $user = $this->__user();
        $fork = $this->__fresh($user, 'probe-counters');
        if ($fork === null) {
            return;
        }
        $before = $this->__reread($fork['id']);

        $this->AnalystProfile->id = $fork['id'];
        $this->AnalystProfile->save(array('AnalystProfile' => array(
            'id' => $fork['id'],
            'name' => 'probe-counters-renamed',
        )));
        $renamed = $this->__reread($fork['id']);
        /*
         * Cast, because the counters come back from the datasource as
         * strings and the API's own `summarise()` casts them for the
         * same reason. Asserting `2 === "2"` failed on the first run,
         * which is the assertion being right about the type rather than
         * the code being wrong about the number.
         */
        $this->__is((int)$before['revision'], (int)$renamed['revision'],
            'a rename leaves `revision` where it was');
        $this->__is((int)$before['version'], (int)$renamed['version'],
            'and `version`, which tracks the shipped file, not the edit');
        $this->__is('probe-counters-renamed', $renamed['name'],
            'while the name did change');

        $parameters = $renamed['parameters'];
        foreach ($parameters['signals'] as $index => $signal) {
            if ($signal['id'] === 'lifecycle.feeds') {
                $parameters['signals'][$index]['points']['per_feed'] = 6;
            }
        }
        $this->AnalystProfile->id = $fork['id'];
        $this->AnalystProfile->save(array('AnalystProfile' => array(
            'id' => $fork['id'],
            'parameters' => $parameters,
        )));
        $this->AnalystProfile->bumpRevision($fork['id']);
        $edited = $this->__reread($fork['id']);
        $this->__is((int)$before['revision'] + 1, (int)$edited['revision'],
            'editing a weight moves `revision` by one');
        $this->__is((int)$before['version'], (int)$edited['version'],
            'and still leaves `version` alone — phase 10 keys its'
                . ' materialised rows on the one that moves');
        $this->__is(6,
            $this->__points($edited['parameters'], 'lifecycle.feeds',
                'per_feed'),
            'and the weight really is stored');
    }

    /**
     * Forking with an enabled profile already held: refused as a
     * decision, and the replace path disables rather than deletes.
     *
     * @return void
     */
    private function __fork()
    {
        $user = $this->__user();
        $first = $this->__fresh($user, 'probe-fork-one');
        if ($first === null) {
            return;
        }
        $this->__is(true, !empty($first['enabled']),
            'the first fork of the default lands enabled');
        $this->__is(true,
            (int)$this->AnalystProfile->resolveFor($user)['id']
                === (int)$first['id'],
            'and is immediately what its owner resolves to');

        /*
         * A second enabled fork is refused by the model, which is what
         * makes the controller's confirm a *decision* rather than a
         * courtesy: there is no path that silently creates two.
         */
        $second = $this->AnalystProfile->forkProfile($user,
            $this->__defaultId(), 'probe-fork-two');
        $this->__is(null, $second,
            'a second enabled fork is refused outright');
        $errors = $this->AnalystProfile->validationErrors;
        $this->__is(true, isset($errors['enabled']),
            'and the refusal names the invariant it protects');

        /*
         * The replace path. §6: it disables, and it never deletes —
         * an analyst's tuned judgement is not something a one-click
         * flow may destroy.
         */
        $this->AnalystProfile->id = $first['id'];
        $this->AnalystProfile->saveField('enabled', 0);
        $replacement = $this->AnalystProfile->forkProfile($user,
            $this->__defaultId(), 'probe-fork-replacement');
        $this->__is(true, !empty($replacement),
            'with the first disabled, the fork succeeds');
        if (!empty($replacement)) {
            $this->created[] = $replacement['AnalystProfile']['id'];
        }
        $survivor = $this->__reread($first['id']);
        $this->__is(true, !empty($survivor),
            'and the replaced profile still exists');
        $this->__is(false, !empty($survivor['enabled']),
            'disabled rather than deleted');

        /*
         * The fork's own description says what it is. It used to be a
         * verbatim copy, so a fork of the shipped default introduced
         * itself as *"The instance default Analyst Profile"* — true of
         * the source and false of the copy, on the one field a
         * colleague reads to decide whether to adopt it.
         */
        $this->__is(true,
            strpos($replacement['AnalystProfile']['description'],
                'Forked from') === 0,
            'and its description leads with where it came from, rather'
                . ' than claiming to be its source');
    }

    /**
     * Scoring a candidate writes nothing — including a candidate built
     * from a profile the caller could not edit if they tried.
     *
     * @return void
     */
    private function __simulate()
    {
        $user = $this->__user();
        $default = $this->__reread($this->__defaultId());
        $before = array(
            'revision' => $default['revision'],
            'modified' => $default['modified'],
            'parameters' => json_encode($default['parameters']),
        );

        $candidate = $default;
        $parameters = $candidate['parameters'];
        foreach ($parameters['signals'] as $index => $signal) {
            if ($signal['id'] === 'attribution.galaxy') {
                $parameters['signals'][$index]['points']['cap'] = 40;
            }
        }
        $candidate['parameters'] = $parameters;

        $engine = new ValueVerdictTool($this->ValueProfile);
        $context = $this->ValueProfile->verdictContextFor($user,
            self::VALUE, $default);
        $baseline = $engine->assess($context, $default);
        $scored = $engine->assess($context, $candidate);
        $diff = ValueVerdictDiffTool::diff($baseline, $scored);

        $this->__is(true, $diff['sums']['before']['ok'],
            'the in-force column sums to its own quality on real rows');
        $this->__is(true, $diff['sums']['after']['ok'],
            'and so does the candidate column — the invariant the whole'
                . ' diff rests on, measured rather than assumed');

        $after = $this->__reread($this->__defaultId());
        $this->__is($before['revision'], $after['revision'],
            'the profile scored against is untouched: `revision`');
        $this->__is($before['modified'], $after['modified'],
            'untouched: `modified`');
        $this->__is($before['parameters'],
            json_encode($after['parameters']),
            'untouched: the document itself');
    }

    /**
     * What a simulation costs, and the property that decides it.
     *
     * A candidate that differs only in weights shares its context with
     * the profile in force; one whose `exclusions` differ does not,
     * because `orgs.own` is a predicate in `Value::conditionsFor()` and
     * the two profiles are then looking at different rows.
     *
     * @return void
     */
    private function __cost()
    {
        $user = $this->__user();
        $default = $this->__reread($this->__defaultId());
        $engine = new ValueVerdictTool($this->ValueProfile);

        $weightsOnly = $default;
        $parameters = $weightsOnly['parameters'];
        foreach ($parameters['signals'] as $index => $signal) {
            if ($signal['id'] === 'lifecycle.recency') {
                $parameters['signals'][$index]['points']['recent'] = 14;
            }
        }
        $weightsOnly['parameters'] = $parameters;
        $sharedContext = $this->ValueProfile->verdictContextFor($user,
            self::VALUE, $default);
        $weightDiff = ValueVerdictDiffTool::diff(
            $engine->assess($sharedContext, $default),
            $engine->assess($sharedContext, $weightsOnly)
        );
        $this->__is(true, $weightDiff['changed'],
            'a weight-only candidate changes the assessment');
        $this->__is(true, count($weightDiff['moved']) > 0,
            'moving at least one ledger row');

        /*
         * And the exclusions case, scored against its own context. The
         * assertion is not that the number moved — a value with one
         * organisation would not — but that the *inputs* did, which is
         * phase 4 §7.3's lesson: a filter that reports nothing looks
         * exactly like a filter with nothing to do.
         */
        $excluding = $default;
        $parameters = $excluding['parameters'];
        foreach ($parameters['exclusions'] as $index => $rule) {
            if ($rule['id'] === 'orgs.own') {
                $parameters['exclusions'][$index]['enabled'] = true;
            }
        }
        $excluding['parameters'] = $parameters;
        $ownContext = $this->ValueProfile->verdictContextFor($user,
            self::VALUE, $excluding);
        $this->__is(true,
            (int)$ownContext['occurrences']['total']
                <= (int)$sharedContext['occurrences']['total'],
            'a context built under `orgs.own` sees no more occurrences'
                . ' than one built without it');
        $this->__is(true,
            $this->__signature($default) !== $this->__signature($excluding),
            'and the two exclusion sections do not match, which is what'
                . ' tells the simulator it may not share one context');
        $this->__is(true,
            $this->__signature($default) === $this->__signature($weightsOnly),
            'while a weight-only candidate does match, so one build'
                . ' serves both');

        $excludedDiff = ValueVerdictDiffTool::diff(
            $engine->assess($sharedContext, $default),
            $engine->assess($ownContext, $excluding)
        );
        $this->__is(true, $excludedDiff['sums']['ok'],
            'both columns still sum exactly across two contexts');
        $policy = 0;
        foreach ($excludedDiff['not_counted'] as $entry) {
            if ($entry['state'] === ValueVerdictDiffTool::APPEARED) {
                $policy++;
            }
        }
        $this->__is(true, $policy > 0,
            'and the exclusion appears in `not_counted`, so a quality'
                . ' that dropped has something to attribute it to');
    }

    /**
     * A fork the run owns, or null with the reason printed.
     *
     * @param array $user
     * @param string $name
     * @return array|null
     */
    private function __fresh(array $user, $name)
    {
        $existing = $this->AnalystProfile->find('first', array(
            'conditions' => array(
                'AnalystProfile.user_id' => $user['id'],
                'AnalystProfile.enabled' => 1,
            ),
            'recursive' => -1,
        ));
        if (!empty($existing)) {
            /*
             * Somebody's real profile is in the way. Disabled, not
             * deleted, and re-enabled on the way out — the same posture
             * the feature itself takes.
             */
            $displacedId = (int)$existing['AnalystProfile']['id'];
            $this->AnalystProfile->id = $displacedId;
            $this->AnalystProfile->saveField('enabled', 0);
            /*
             * Only somebody else's profile is worth restoring. On the
             * second call this displaces the *first fork*, which
             * cleanup then deletes — and the first version of this
             * probe recorded it anyway, so cleanup tried to
             * `saveField` a row that no longer existed. CakePHP reads
             * that as an insert and it died on the missing uuid, after
             * every other assertion had passed. Exactly the failure
             * the "run it twice" convention exists to catch.
             */
            if (!in_array($displacedId, $this->created, true)) {
                $this->disabled[] = $displacedId;
            }
        }
        $fork = $this->AnalystProfile->forkProfile($user,
            $this->__defaultId(), $name);
        if (empty($fork)) {
            $this->__is(true, false, sprintf(
                'could not fork for %s: %s',
                $name,
                json_encode($this->AnalystProfile->validationErrors)
            ));
            return null;
        }
        $this->created[] = $fork['AnalystProfile']['id'];
        return $fork['AnalystProfile'];
    }

    /**
     * @return void
     */
    private function __cleanup()
    {
        $this->out('');
        $this->out('== leaving the instance as it was ==');
        foreach ($this->created as $id) {
            $this->AnalystProfile->delete($id);
        }
        $survivors = 0;
        foreach ($this->created as $id) {
            if (!empty($this->__reread($id))) {
                $survivors++;
            }
        }
        $this->__is(0, $survivors, sprintf(
            'the %d profiles this run created are gone',
            count($this->created)
        ));
        $toRestore = array();
        foreach (array_unique($this->disabled) as $id) {
            if (empty($this->__reread($id))) {
                continue;
            }
            $toRestore[] = $id;
            $this->AnalystProfile->id = $id;
            $this->AnalystProfile->saveField('enabled', 1);
        }
        $restored = 0;
        foreach ($toRestore as $id) {
            $row = $this->__reread($id);
            if (!empty($row['enabled'])) {
                $restored++;
            }
        }
        $this->__is(count($toRestore), $restored,
            'and every profile it displaced is enabled again');
        $this->__is(true,
            !empty($this->__reread($this->__defaultId())['enabled']),
            'the instance default is still enabled');
    }

    /**
     * @param array $row
     * @return string
     */
    private function __signature(array $row)
    {
        return json_encode(
            isset($row['parameters']['exclusions'])
                ? $row['parameters']['exclusions']
                : array()
        );
    }

    /**
     * @param array $parameters
     * @param string $id
     * @param string $key
     * @return mixed
     */
    private function __points(array $parameters, $id, $key)
    {
        foreach ($parameters['signals'] as $signal) {
            if ($signal['id'] === $id) {
                return isset($signal['points'][$key])
                    ? $signal['points'][$key]
                    : null;
            }
        }
        return null;
    }

    /**
     * @param int $id
     * @return array
     */
    private function __reread($id)
    {
        $row = $this->AnalystProfile->find('first', array(
            'conditions' => array('AnalystProfile.id' => $id),
            'recursive' => -1,
        ));
        return empty($row) ? array() : $row['AnalystProfile'];
    }

    /**
     * @return int
     */
    private function __defaultId()
    {
        $row = $this->AnalystProfile->find('first', array(
            'conditions' => array('AnalystProfile.default' => 1),
            'fields' => array('AnalystProfile.id'),
            'recursive' => -1,
        ));
        return empty($row) ? 0 : (int)$row['AnalystProfile']['id'];
    }

    /**
     * A user with no profile of their own, so a fork of the default is
     * the interesting case rather than a second one.
     *
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
