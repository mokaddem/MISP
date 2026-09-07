<?php

/**
 * Phase 1's verification items that need the table: §7's 5, 6 and 9, plus
 * 3, 4 and 8 re-run against real rows rather than the stubs in
 * `02-store-resolve-harness.php`.
 *
 * The harness answers "does the logic decide correctly"; this answers "does
 * it decide correctly through CakePHP, against MySQL, with the instance's
 * own users" — which is where a validation rule that never fires, an
 * `afterFind` that mangles a column, or a save that silently drops a field
 * would show up instead.
 *
 * Run:
 *   cp prd/analyst-profile/02-store-live-probe.php \
 *      app/Console/Command/AnalystProfileProbeShell.php
 *   app/Console/cake AnalystProfileProbe run
 *   rm app/Console/Command/AnalystProfileProbeShell.php
 *
 * It leaves the shipped default in place — that is a row the instance is
 * supposed to have — and deletes every profile it creates itself.
 */
class AnalystProfileProbeShell extends AppShell
{
    public $uses = array('AnalystProfile', 'User');

    private $checks = 0;
    private $failures = 0;
    private $created = array();

    public function run()
    {
        $this->out('');
        $this->__cleanup(true);

        $this->__item6ShippedDefault();
        $this->__item3And4Resolution();
        $this->__item5Fork();
        $this->__item9Revision();
        $this->__item8Ownership();

        $this->__cleanup(false);

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
     * §7 item 6 — updateDefaults() is idempotent, a version bump applies,
     * and `enabled` is the admin's to keep.
     */
    private function __item6ShippedDefault()
    {
        $this->out('§7.6 — the shipped default loads, once');

        $first = $this->AnalystProfile->update();
        $this->__is(
            array('created'),
            array_values($first),
            'a first run creates it'
        );

        $second = $this->AnalystProfile->update();
        $this->__is(
            array('skipped'),
            array_values($second),
            'a second run is a no-op'
        );

        $profile = $this->__default();
        if (empty($profile['id'])) {
            /*
             * Everything below reads that row, and saveField() on an absent
             * id makes CakePHP insert instead of update — which fails on
             * `uuid` having no column default and buries the real cause
             * under a stack trace. Stop here and say what is actually wrong.
             */
            $this->__is(
                true,
                false,
                'no default profile was loaded — is default-v1.json readable'
                    . ' at ' . APP . 'files/analyst-profiles/ ?'
            );
            return;
        }
        $this->__is(
            'default-v1',
            $profile['name'],
            'named default-v1'
        );
        $this->__is(
            true,
            is_array($profile['parameters'])
                && isset($profile['parameters']['signals']),
            'parameters round-tripped through MySQL as a decoded array'
        );
        $this->__is(
            'local_only',
            $profile['parameters']['enrichment']['cost_posture'],
            'the stored default still contacts nobody'
        );
        $this->__is(
            array(1, 1),
            array((int)$profile['version'], (int)$profile['revision']),
            'version and revision both start at 1'
        );

        // A version bump applies, and an admin's `enabled` choice survives it.
        $this->AnalystProfile->id = $profile['id'];
        $this->AnalystProfile->saveField('enabled', 0);
        $this->AnalystProfile->id = $profile['id'];
        $this->AnalystProfile->saveField('version', 0);

        $third = $this->AnalystProfile->update();
        $this->__is(
            array('updated'),
            array_values($third),
            'a newer shipped version applies'
        );
        $after = $this->__default();
        $this->__is(
            0,
            (int)$after['enabled'],
            'a disabled default is not re-enabled by an update'
        );
        $this->__is(
            2,
            (int)$after['revision'],
            'the overwrite bumped revision'
        );

        // Put it back the way an instance expects to find it, all three
        // columns this section moved — so an aborted run still leaves a
        // usable default behind.
        foreach (array('enabled' => 1, 'revision' => 1, 'version' => 1) as $field => $value) {
            $this->AnalystProfile->id = $after['id'];
            $this->AnalystProfile->saveField($field, $value);
        }
    }

    /**
     * §7 items 3 and 4 — resolution for real users, and the null.
     */
    private function __item3And4Resolution()
    {
        $this->out('');
        $this->out('§7.3/§7.4 — resolveFor() against the instance');

        $plainUser = $this->__user(2);
        $circlAdmin = $this->__user(4);

        $resolved = $this->AnalystProfile->resolveFor($plainUser);
        $this->__is(
            'default-v1',
            $resolved['name'],
            'a user with nothing gets the default'
        );

        // An org profile for org 9, which user 4 is in and user 2 is not.
        $orgProfile = $this->__make(array(
            'name' => 'probe org profile',
            'org_id' => 9,
            'parameters' => array('format' => 1, 'signals' => array()),
        ));
        $this->__resetCache();
        $this->__is(
            'probe org profile',
            $this->AnalystProfile->resolveFor($circlAdmin)['name'],
            "an org member gets their org's"
        );
        $this->__is(
            'default-v1',
            $this->AnalystProfile->resolveFor($plainUser)['name'],
            'a member of another org does not'
        );

        // A user profile for user 4 outranks their org's.
        $this->__make(array(
            'name' => 'probe user profile',
            'user_id' => 4,
            'parameters' => array('format' => 1, 'signals' => array()),
        ));
        $this->__resetCache();
        $this->__is(
            'probe user profile',
            $this->AnalystProfile->resolveFor($circlAdmin)['name'],
            'their own outranks their org\'s'
        );

        // Every user on the instance resolves to exactly one profile.
        $everyone = $this->User->find('all', array(
            'fields' => array('User.id', 'User.org_id'),
            'recursive' => -1,
        ));
        $unresolved = 0;
        foreach ($everyone as $row) {
            $this->__resetCache();
            if (empty($this->AnalystProfile->resolveFor($row['User'])['name'])) {
                $unresolved++;
            }
        }
        $this->__is(
            0,
            $unresolved,
            sprintf('all %d users resolve to a profile', count($everyone))
        );

        // §7.4: the default disabled, and this viewer owns nothing.
        $default = $this->__default();
        $this->AnalystProfile->id = $default['id'];
        $this->AnalystProfile->saveField('enabled', 0);
        $this->__resetCache();
        $this->__is(
            null,
            $this->AnalystProfile->resolveFor($plainUser),
            'null when the default is off and the viewer owns nothing'
        );
        $this->AnalystProfile->id = $default['id'];
        $this->AnalystProfile->saveField('enabled', 1);
        $this->__resetCache();

        $this->orgProfileId = $orgProfile;
    }

    /**
     * §7 item 5 — fork the default as a non-admin; the copy is editable and
     * the original is not.
     */
    private function __item5Fork()
    {
        $this->out('');
        $this->out('§7.5 — fork, as a non-admin');

        $plainUser = $this->__user(2);
        $default = $this->__default();

        $this->__is(
            false,
            $this->AnalystProfile->isEditableByCurrentUser($plainUser, $default),
            'the default is not editable by an ordinary user'
        );

        // A user profile for user 2 must not already exist.
        $this->__resetCache();
        $fork = $this->AnalystProfile->forkProfile(
            $plainUser,
            $default['id'],
            'probe fork'
        );
        $this->__is(
            true,
            !empty($fork),
            'fork succeeds for an ordinary user'
        );
        if (empty($fork)) {
            return;
        }
        $this->created[] = $fork['AnalystProfile']['id'];
        $row = $fork['AnalystProfile'];

        $this->__is(
            true,
            $this->AnalystProfile->isEditableByCurrentUser($plainUser, $row),
            'the copy is editable by its owner'
        );
        $this->__is(
            (int)$plainUser['id'],
            (int)$row['user_id'],
            'the copy is owned by the forker'
        );
        $this->__is(
            0,
            (int)$row['default'],
            'the copy is not a default'
        );
        $this->__is(
            true,
            $row['uuid'] !== $default['uuid'],
            'the copy carries a fresh uuid, and no lineage'
        );
        $this->__is(
            $default['parameters'],
            $row['parameters'],
            'parameters copied verbatim'
        );
        $this->__is(
            array(1, 1),
            array((int)$row['version'], (int)$row['revision']),
            'counters reset'
        );
        $this->__is(
            'probe fork',
            $this->AnalystProfile->resolveFor($plainUser)['name'],
            'the fork is immediately what the forker resolves to'
        );

        // A user holds one enabled profile: a second fork is refused.
        $this->AnalystProfile->create();
        $second = $this->AnalystProfile->forkProfile(
            $plainUser,
            $default['id'],
            'probe second fork'
        );
        $this->__is(
            null,
            $second,
            'a second enabled fork for the same user is refused'
        );

        // Forking to the org needs perm_admin.
        $this->AnalystProfile->create();
        $refused = $this->AnalystProfile->forkProfile(
            $plainUser,
            $default['id'],
            'probe org fork',
            true
        );
        $this->__is(
            null,
            $refused,
            'forking to the org is refused without perm_admin'
        );
    }

    /**
     * §7 item 9 — a rename leaves `revision`; editing `parameters` moves it.
     */
    private function __item9Revision()
    {
        $this->out('');
        $this->out('§7.9 — revision moves on parameters, not on a rename');

        $id = $this->__make(array(
            'name' => 'probe revision',
            'user_id' => 3,
            'parameters' => array('format' => 1, 'signals' => array()),
        ));
        $before = $this->__byId($id);

        $this->AnalystProfile->id = $id;
        $this->AnalystProfile->saveField('name', 'probe revision renamed');
        $renamed = $this->__byId($id);
        $this->__is(
            (int)$before['revision'],
            (int)$renamed['revision'],
            'a rename does not move revision'
        );

        $this->AnalystProfile->bumpRevision($id);
        $bumped = $this->__byId($id);
        $this->__is(
            (int)$before['revision'] + 1,
            (int)$bumped['revision'],
            'bumpRevision() moves it by one'
        );
        $this->__is(
            (int)$before['version'],
            (int)$bumped['version'],
            'and leaves version alone'
        );
    }

    /**
     * §7 item 8 — the ownership triple, through CakePHP's validation rather
     * than through the stub.
     */
    private function __item8Ownership()
    {
        $this->out('');
        $this->out('§7.8 — the ownership triple, saved for real');

        $cases = array(
            array('user + org', array('user_id' => 2, 'org_id' => 1), false),
            array('org + default', array('org_id' => 1, 'default' => 1), false),
            array('all three', array('user_id' => 2, 'org_id' => 1, 'default' => 1), false),
            array('no owner', array(), false),
            array('user only', array('user_id' => 8), true),
        );
        foreach ($cases as $case) {
            list($label, $fields, $shouldSave) = $case;
            $this->AnalystProfile->create();
            $saved = $this->AnalystProfile->save(array(
                'AnalystProfile' => array_merge(
                    array(
                        'name' => 'probe ownership ' . $label,
                        'parameters' => array('format' => 1),
                    ),
                    $fields
                )
            ));
            if (!empty($saved)) {
                $this->created[] = $this->AnalystProfile->id;
            }
            $this->__is(
                $shouldSave,
                !empty($saved),
                sprintf('%s -> %s', $label, $shouldSave ? 'saved' : 'rejected')
            );
        }

        // And unparseable parameters survive the round trip as a flag.
        $id = $this->created ? end($this->created) : null;
        if ($id) {
            $this->AnalystProfile->query(sprintf(
                'UPDATE analyst_profiles SET parameters = %s WHERE id = %d',
                "'{not json'",
                $id
            ));
            $broken = $this->__byId($id);
            $this->__is(
                true,
                !empty($broken['parameters_unparseable']),
                'unparseable parameters reach the reader flagged, not empty'
            );
        }
    }

    // ---------------------------------------------------------------- helpers

    private function __resetCache()
    {
        // resolveFor() memoises per request, and a probe is one long request.
        $property = new ReflectionProperty('AnalystProfile', 'resolutionCache');
        $property->setAccessible(true);
        $property->setValue($this->AnalystProfile, array());
    }

    private function __user($id)
    {
        $user = $this->User->getAuthUser($id);
        if (empty($user)) {
            $this->error(sprintf('user %s not found on this instance', $id));
        }
        return $user;
    }

    private function __default()
    {
        $row = $this->AnalystProfile->find('first', array(
            'conditions' => array('AnalystProfile.default' => 1),
            'recursive' => -1,
        ));
        return empty($row) ? array() : $row['AnalystProfile'];
    }

    private function __byId($id)
    {
        $row = $this->AnalystProfile->find('first', array(
            'conditions' => array('AnalystProfile.id' => $id),
            'recursive' => -1,
        ));
        return empty($row) ? array() : $row['AnalystProfile'];
    }

    private function __make(array $fields)
    {
        $this->AnalystProfile->create();
        $saved = $this->AnalystProfile->save(array('AnalystProfile' => $fields));
        if (empty($saved)) {
            $this->out(sprintf(
                '  !! could not create %s: %s',
                isset($fields['name']) ? $fields['name'] : '?',
                json_encode($this->AnalystProfile->validationErrors)
            ));
            return null;
        }
        $this->created[] = $this->AnalystProfile->id;
        return $this->AnalystProfile->id;
    }

    /**
     * @param bool $initial Before the run, clear anything a previous run
     *                      left behind; after it, clear only this run's.
     */
    private function __cleanup($initial)
    {
        if ($initial) {
            $this->AnalystProfile->deleteAll(
                array('AnalystProfile.name LIKE' => 'probe %'),
                false
            );
            /*
             * The shipped default goes too, and is reloaded by the first
             * assertion. §7.6 moves its `version` and `revision` on purpose,
             * so a run that inherited the previous run's counters would be
             * asserting 'updated' where it means to assert 'created' — the
             * probe has to own the row's whole life or it tests drift.
             */
            $this->AnalystProfile->deleteAll(
                array('AnalystProfile.default' => 1),
                false
            );
            return;
        }
        if (!empty($this->created)) {
            $this->AnalystProfile->deleteAll(
                array('AnalystProfile.id' => $this->created),
                false
            );
        }
        $this->AnalystProfile->deleteAll(
            array('AnalystProfile.name LIKE' => 'probe %'),
            false
        );
        $remaining = $this->AnalystProfile->find('count', array(
            'conditions' => array('AnalystProfile.default' => 0),
        ));
        $this->out('');
        $this->__is(
            0,
            (int)$remaining,
            'the probe left nothing behind but the shipped default'
        );
    }

    private function __is($expected, $actual, $label)
    {
        $this->checks++;
        if ($expected === $actual) {
            $this->out(sprintf('  ok   %s', $label));
            return;
        }
        $this->failures++;
        $this->out(sprintf('  FAIL %s', $label));
        $this->out(sprintf('         expected %s', var_export($expected, true)));
        $this->out(sprintf('         got      %s', var_export($actual, true)));
    }
}
