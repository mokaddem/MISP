<?php

App::uses('AnalystProfileFormTool', 'Tools');
App::uses('ValueVerdictTool', 'Tools');
App::uses('ValueVerdictDiffTool', 'Tools');
App::uses('ValueSignalLoader', 'Tools');

/**
 * Dump phase 8a's view-model as the fixtures phase 8b draws against.
 *
 * **Every number in every prototype comes from here.** 8b is executed
 * cold, by one agent per candidate, and the brief forbids inventing a
 * figure — so these files have to be *sufficient on their own*. An
 * agent who needs the dev instance running has been handed an
 * incomplete brief, and a mockup drawn against a plausible-looking
 * number is a mockup that cannot be built. `value-profile-live/` is the
 * long record of what that costs.
 *
 * Which means the awkward states are not optional extras. A design that
 * only ever sees the happy path will be redrawn in 8c the first time it
 * meets a signal this instance does not have, a loader that skipped a
 * file, a band nothing can reach, or an empty comparison set. So the
 * dump *constructs* those states rather than waiting for the instance
 * to have one — from real rows, on top of the real document, but
 * deliberately rather than by luck.
 *
 * **It prints to stdout and writes nothing.** The first version wrote
 * files under `prd/`, which the dev container cannot see: only parts of
 * `app/` are mounted from the working tree, so every write landed in
 * the image and the run reported five fixtures written to a directory
 * that stayed empty. Printing removes the assumption altogether — and
 * `app/webroot/` would have been the wrong fix twice over, since these
 * documents carry indicator values and anything under webroot is served.
 *
 * Run:
 *   cp prd/analyst-profile/09a-fixtures-dump.php \
 *      app/Console/Command/AnalystFixturesShell.php
 *   app/Console/cake AnalystFixtures dump > /tmp/fixtures.json
 *   rm app/Console/Command/AnalystFixturesShell.php
 *   python3 -c "import json,os,sys; \
 *     d=json.load(open('/tmp/fixtures.json')); \
 *     out='prd/analyst-profile/09a-fixtures/'; os.makedirs(out,exist_ok=True); \
 *     [open(out+k,'w').write(json.dumps(v,indent=2)+chr(10)) for k,v in d.items()]"
 */
class AnalystFixturesShell extends AppShell
{
    public $uses = array('AnalystProfile', 'ValueProfile', 'Value',
        'User', 'Organisation', 'Warninglist');

    /** Where the caller is expected to put them. */
    const OUT = 'prd/analyst-profile/09a-fixtures/';

    /**
     * Values to score, in the order the comparison set shows them.
     *
     * Chosen for their *shapes* on this instance and named as this
     * corpus's own fixture rather than as the product's regression set
     * — 09-editor.md §2.2 is the argument, and it is why the product
     * ships an analyst-owned pinned set instead of four addresses.
     */
    const VALUES = array(
        '8.8.8.8',
        '185.234.219.24',
        '45.155.205.233',
        '1.1.1.1',
    );

    public function dump()
    {
        $user = $this->__user();
        $default = $this->__default();
        $form = new AnalystProfileFormTool();
        $engine = new ValueVerdictTool($this->ValueProfile);

        $written = array();

        /*
         * 1. The index board, for a reader who owns a fork and whose
         *    organisation owns another — the three-row shape §5 item 7
         *    asks for, built rather than found, because this instance
         *    has one profile and the state that confuses people needs
         *    at least three.
         */
        $written['index.json'] = $this->__index($user, $default);

        /*
         * 2. The seven sections, with the maps filled in. §5 item 8:
         *    `reference.org_trust` and `enrichment.locality` are empty
         *    in the shipped default — correctly, since an empty trust
         *    map switches weighting off — so the fixture grades real
         *    organisations off this instance and overrides real
         *    modules.
         */
        $furnished = $this->__furnish($default, $user);
        $sources = $this->__sources($furnished['parameters']);
        $contributions = $this->__contributions($engine, $user,
            self::VALUES[0], $furnished);
        $written['profile.json'] = array(
            'note' => 'The edit and view boards. `sections` is the whole'
                . ' view-model: seven sections, four block kinds. Every'
                . ' field carries the `path` a form posts it under.',
            'profile' => $this->AnalystProfile->summarise($furnished),
            'value' => self::VALUES[0],
            'editable' => true,
            'sections' => $form->sections($furnished['parameters'],
                $sources + $contributions),
            'bands' => $form->bandStrip($furnished['parameters']),
            'raw' => json_encode($furnished['parameters'],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'errors' => array(),
            'warnings' => $form->validate(
                $furnished['parameters'])['warnings'],
        );

        /*
         * 3. The palette on its own, plus the two states a happy path
         *    never shows: a signal the profile names that this instance
         *    does not implement, and a custom one. Both are synthesised,
         *    and the fixture says so — an admin's drop-in directory is
         *    empty here and inventing a file on disk to make a mockup
         *    look right would be a change to the product for the sake
         *    of a picture.
         */
        $written['palette.json'] = $this->__palette($form, $default);

        /*
         * 4. The band strip in the state it exists to draw: `medium`
         *    above `high`, and a boundary beyond what the enabled
         *    catalogue can reach (§5 item 4).
         */
        $written['bands.json'] = $this->__bands($form, $default);

        /*
         * 5. The simulator: an empty diff, a diff with a changed row, a
         *    vanished row, an appeared row, and the comparison set —
         *    with its empty state (§5 items 5 and 6).
         */
        $written['simulate.json'] = $this->__simulate($engine, $form,
            $user, $default);

        /*
         * One JSON object keyed by filename, and nothing else on
         * stdout — a progress line here would end up inside the
         * document the caller redirects to a file.
         */
        echo json_encode($written,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /**
     * A three-row index board. The extra two rows are real saved
     * profiles, deleted before this returns — the board is what is
     * kept, not the rows.
     *
     * @param array $user
     * @param array $default
     * @return array
     */
    private function __index(array $user, array $default)
    {
        $created = array();
        $displaced = null;
        $existing = $this->AnalystProfile->find('first', array(
            'conditions' => array(
                'AnalystProfile.user_id' => $user['id'],
                'AnalystProfile.enabled' => 1,
            ),
            'recursive' => -1,
        ));
        if (!empty($existing)) {
            $displaced = (int)$existing['AnalystProfile']['id'];
            $this->AnalystProfile->id = $displaced;
            $this->AnalystProfile->saveField('enabled', 0);
        }

        $mine = $this->AnalystProfile->forkProfile($user,
            $default['id'], 'My tuned weights');
        if (!empty($mine)) {
            $created[] = (int)$mine['AnalystProfile']['id'];
        }
        /*
         * A second one of the reader's own, disabled — the row that
         * makes an analyst ask why their edits changed nothing.
         */
        $this->AnalystProfile->id = $created[0];
        $this->AnalystProfile->saveField('enabled', 0);
        $second = $this->AnalystProfile->forkProfile($user,
            $default['id'], 'Sightings-heavy (experiment)');
        if (!empty($second)) {
            $created[] = (int)$second['AnalystProfile']['id'];
        }

        $board = $this->AnalystProfile->indexFor($user);
        $fixture = array(
            'note' => 'The index board. Every row that is not in force'
                . ' carries the reason it is not — `disabled`,'
                . ' `overridden` naming the winner, `other_owner`,'
                . ' `unresolved`. The rows here were saved and deleted'
                . ' by the dump; the board is the fixture.',
            'profiles' => $board['profiles'],
            'in_force' => $board['in_force'] === null
                ? null
                : $this->AnalystProfile->summarise($board['in_force']),
            'scoring_off' => false,
            'loader_errors' => array(),
            'comparison_set' => self::VALUES,
            /*
             * The other state the index has to be able to draw: a site
             * admin has disabled the instance default and this reader
             * owns nothing, so no profile is in force and scoring is
             * simply off here. It is not an error and not a zero.
             */
            'scoring_off_variant' => array(
                'profiles' => array(),
                'in_force' => null,
                'scoring_off' => true,
                'comparison_set' => array(),
            ),
        );

        foreach ($created as $id) {
            $this->AnalystProfile->delete($id);
        }
        if ($displaced !== null) {
            $this->AnalystProfile->id = $displaced;
            $this->AnalystProfile->saveField('enabled', 1);
        }
        return $fixture;
    }

    /**
     * The shipped default with its two empty override maps filled in
     * from this instance's own rows.
     *
     * @param array $default
     * @param array $user
     * @return array
     */
    private function __furnish(array $default, array $user)
    {
        $row = $default;
        $parameters = $row['parameters'];

        $orgs = $this->Organisation->find('all', array(
            'fields' => array('Organisation.uuid', 'Organisation.name'),
            'recursive' => -1,
            'order' => array('Organisation.id ASC'),
            'limit' => 3,
        ));
        $grades = array('A', 'D', 'E');
        $trust = array();
        foreach ($orgs as $index => $organisation) {
            $trust[$organisation['Organisation']['uuid']] =
                $grades[$index % count($grades)];
        }
        /*
         * And one graded organisation that is not on this instance —
         * the export/import case, where a profile carries a judgement
         * about somebody the reader has never synchronised with. The
         * row must keep its grade and say it cannot be named.
         */
        $trust['9d8f1c60-0000-4000-8000-abcdefabcdef'] = 'B';
        $parameters['reference']['org_trust'] = $trust;

        $list = $this->Warninglist->find('first', array(
            'fields' => array('Warninglist.name'),
            'recursive' => -1,
            'conditions' => array('Warninglist.name LIKE' => '%DNS%'),
        ));
        if (!empty($list)) {
            $parameters['reference']['warninglist_category'] = array(
                $list['Warninglist']['name'] => 'known',
            );
        }

        $parameters['enrichment']['auto_run'] = array(
            'ip-src' => array('circl_passivedns', 'virustotal_public'),
            'domain' => array('dns'),
        );
        $parameters['enrichment']['locality'] = array(
            'circl_passivedns' => 'external',
            'dns' => 'local',
        );
        $row['parameters'] = $parameters;
        $row['name'] = 'Weights I actually use';
        return $row;
    }

    /**
     * @param array $parameters
     * @return array
     */
    private function __sources(array $parameters)
    {
        $uuids = array_keys($parameters['reference']['org_trust']);
        $rows = $this->Organisation->find('all', array(
            'conditions' => array('Organisation.uuid' => $uuids),
            'fields' => array('Organisation.uuid', 'Organisation.name'),
            'recursive' => -1,
        ));
        $orgs = array();
        foreach ($rows as $organisation) {
            $orgs[$organisation['Organisation']['uuid']] =
                $organisation['Organisation']['name'];
        }
        $lists = array();
        foreach ($this->Warninglist->find('all', array(
            'fields' => array('Warninglist.id', 'Warninglist.name'),
            'recursive' => -1,
            'limit' => 40,
            'order' => array('Warninglist.name ASC'),
        )) as $list) {
            $lists[$list['Warninglist']['name']] =
                (int)$list['Warninglist']['id'];
        }
        return array(
            'attribute_types' => array('ip-src', 'ip-dst', 'domain',
                'hostname', 'url', 'md5', 'sha1', 'sha256', 'email-src',
                'btc', 'filename'),
            'orgs' => $orgs,
            'warninglists' => $lists,
            'modules' => array(
                'circl_passivedns' => 'circl_passivedns',
                'dns' => 'dns',
                'virustotal_public' => 'virustotal_public',
                'countrycode' => 'countrycode',
            ),
        );
    }

    /**
     * @param ValueVerdictTool $engine
     * @param array $user
     * @param string $value
     * @param array $profile
     * @return array
     */
    private function __contributions(ValueVerdictTool $engine, array $user,
        $value, array $profile
    ) {
        $verdict = $engine->verdictFor($user, $value,
            array('profile' => $profile));
        $ledger = array();
        foreach ($verdict['ledger'] as $group) {
            foreach ($group['signals'] as $row) {
                if (!empty($row['id'])) {
                    $ledger[$row['id']] = $row['contribution'];
                }
            }
        }
        $notCounted = array();
        foreach ($verdict['not_counted'] as $entry) {
            if (!empty($entry['id'])) {
                $notCounted[$entry['id']] = isset($entry['note'])
                    ? $entry['note']
                    : '';
            }
        }
        return array('ledger' => $ledger, 'not_counted' => $notCounted);
    }

    /**
     * @param AnalystProfileFormTool $form
     * @param array $default
     * @return array
     */
    private function __palette(AnalystProfileFormTool $form,
        array $default
    ) {
        $parameters = $default['parameters'];
        /*
         * A signal the profile configures that this instance cannot
         * compute. Synthesised, because the drop-in directory is empty
         * here and creating a file on disk to make a mockup look right
         * would be changing the product for a picture.
         */
        $parameters['signals'][] = array(
            'id' => 'reporting.partner_feed_agreement',
            'group' => 'Reporting',
            'enabled' => true,
            'points' => array('per_partner' => 6, 'cap' => 18),
        );
        return array(
            'note' => 'The signal palette. Three states: `active` (in'
                . ' the profile and implemented), `available`'
                . ' (implemented, not in the profile) and `missing`'
                . ' (in the profile, not implemented here). The points'
                . ' columns differ per row by design — `points` has no'
                . ' fixed schema.',
            'synthesised' => array(
                'reporting.partner_feed_agreement' => 'Not implemented'
                    . ' on this instance. Added to the fixture so the'
                    . ' `missing` state can be drawn.',
                'lifecycle.feeds is_custom' => 'This instance has no'
                    . ' drop-in signals, so the `custom` badge is'
                    . ' written onto one shipped row rather than'
                    . ' dropping a PHP file into app/Lib/ValueSignals'
                    . ' to make a picture look right. The badge is what'
                    . ' a design has to render; which signal carries it'
                    . ' is not.',
                'loader_errors' => 'This instance has no drop-in'
                    . ' directory and no broken files, so the three'
                    . ' entries below are written by hand from the'
                    . ' loader\'s own three refusal reasons.',
            ),
            'items' => $this->__withCustomBadge(
                $form->signalPalette($parameters)),
            'escalations' => $form->escalationPalette($parameters),
            'exclusions' => $form->exclusionItems($parameters),
            /*
             * §5 item 3. The three ways a file is refused, in the
             * loader's own words: it does not parse, it is not a
             * `ValueSignalBase`, or its id collides with one already
             * loaded. The engine skips all three in silence, so the
             * editor is the only place an admin finds out.
             */
            'loader_errors' => array(
                array(
                    'subject' => 'Signal',
                    'file' => 'PartnerAgreement.php',
                    'reason' => 'syntax error, unexpected token ";",'
                        . ' expecting ")" on line 61',
                ),
                array(
                    'subject' => 'Signal',
                    'file' => 'HelperFunctions.php',
                    'reason' => 'the class does not extend'
                        . ' ValueSignalBase',
                ),
                array(
                    'subject' => 'Escalation',
                    'file' => 'ConflictListed.php',
                    'reason' => 'id `conflict:listed-vs-asserted`'
                        . ' already loaded from Model/ValueEscalations',
                ),
            ),
        );
    }


    /**
     * One palette row carrying the `custom` badge.
     *
     * `is_custom` is set by the loader on anything found under
     * `app/Lib/ValueSignals`, and this instance's drop-in directory is
     * empty. Dropping a file in to make the badge appear would be
     * changing the product for the sake of a mockup; leaving the state
     * undrawn would let a candidate ship without it, and *"this
     * instance computes something upstream does not"* is exactly what a
     * reader of a shared profile needs to see. So it is written on, and
     * `synthesised` says so.
     *
     * @param array $items
     * @return array
     */
    private function __withCustomBadge(array $items)
    {
        foreach ($items as $index => $item) {
            if ($item['id'] !== 'lifecycle.feeds') {
                continue;
            }
            array_unshift($items[$index]['badges'], array(
                'id' => 'custom',
                'label' => 'custom',
                'title' => 'Dropped into app/Lib/ValueSignals on this'
                    . ' instance. Nothing upstream computes it, so a'
                    . ' colleague reading this profile elsewhere cannot'
                    . ' reproduce the number it contributes.',
            ));
        }
        return $items;
    }

    /**
     * @param AnalystProfileFormTool $form
     * @param array $default
     * @return array
     */
    private function __bands(AnalystProfileFormTool $form, array $default)
    {
        $ok = $default['parameters'];

        $inverted = $ok;
        $inverted['thresholds']['quality_bands'] = array('high' => 30,
            'medium' => 60);

        $unreachable = $ok;
        $unreachable['thresholds']['quality_bands'] = array('high' => 180,
            'medium' => 30);

        $narrow = $ok;
        foreach ($narrow['signals'] as $index => $signal) {
            if (!in_array($signal['id'], array(
                'reporting.independent_orgs',
                'sightings.volume_recency'), true)
            ) {
                $narrow['signals'][$index]['enabled'] = false;
            }
        }

        return array(
            'note' => 'The band strip. `bound` is the most the enabled'
                . ' signals could contribute — the largest positive'
                . ' value in each `points` map, summed. A boundary above'
                . ' it can never be reached, and a save is refused.',
            'ok' => $form->bandStrip($ok),
            'inverted' => $form->bandStrip($inverted),
            'inverted_errors' => $form->validate($inverted)['errors'],
            'beyond_bound' => $form->bandStrip($unreachable),
            'beyond_bound_errors' => $form->validate($unreachable)['errors'],
            'narrow_catalogue' => $form->bandStrip($narrow),
            'narrow_catalogue_note' => 'Nine signals disabled, so the'
                . ' bound falls to 52 and the shipped `high` at 60 stops'
                . ' being reachable without anybody moving a band.',
        );
    }

    /**
     * @param ValueVerdictTool $engine
     * @param AnalystProfileFormTool $form
     * @param array $user
     * @param array $default
     * @return array
     */
    private function __simulate(ValueVerdictTool $engine,
        AnalystProfileFormTool $form, array $user, array $default
    ) {
        /*
         * The diff has to show all three row states, and getting there
         * needed two goes.
         *
         * **A lowered weight is invisible where a signal is
         * saturated.** The first version of this fixture dropped
         * `per_org` from 7 to 4 and the row did not move: eight
         * organisations report this value, and both 8 x 7 and 8 x 4
         * exceed the cap of 28. That is phase 6 §7.1's finding in a
         * new place — a weighting cannot be observed past a cap — so
         * the edit that produces a *changed* row has to move the cap.
         *
         * And **nothing can appear in a candidate if the profile in
         * force already runs everything**, which the shipped default
         * does. So the in-force side of this fixture has one signal
         * switched off and the candidate switches it back on, which is
         * also the more realistic pair: an analyst deciding whether to
         * start counting something.
         */
        $inForce = $default;
        $inForceParameters = $inForce['parameters'];
        foreach ($inForceParameters['signals'] as $index => $signal) {
            if ($signal['id'] === 'attribution.galaxy') {
                $inForceParameters['signals'][$index]['enabled'] = false;
            }
        }
        $inForce['parameters'] = $inForceParameters;
        $inForce['name'] = 'Instance default, galaxies off';

        $candidate = $default;
        $parameters = $candidate['parameters'];
        foreach ($parameters['signals'] as $index => $signal) {
            // changed: the cap moves, so the row moves with it
            if ($signal['id'] === 'reporting.independent_orgs') {
                $parameters['signals'][$index]['points']['cap'] = 16;
            }
            // vanished: switched off in the candidate
            if ($signal['id'] === 'lifecycle.warninglist') {
                $parameters['signals'][$index]['enabled'] = false;
            }
            // appeared: switched on again in the candidate
            if ($signal['id'] === 'attribution.galaxy') {
                $parameters['signals'][$index]['enabled'] = true;
            }
        }
        $candidate['parameters'] = $parameters;
        $candidate['name'] = 'Weights I actually use';

        $comparison = array();
        $detail = null;
        $unchangedDetail = null;
        foreach (self::VALUES as $index => $value) {
            $context = $this->ValueProfile->verdictContextFor($user,
                $value, $inForce);
            $before = $engine->assess($context, $inForce);
            $after = $engine->assess($context, $candidate);
            $comparison[] = ValueVerdictDiffTool::headline($value,
                $before, $after);
            if ($index === 0) {
                $detail = ValueVerdictDiffTool::diff($before, $after);
                $unchangedDetail = ValueVerdictDiffTool::diff($before,
                    $before);
            }
        }

        return array(
            'note' => 'The simulator. `detail` is the ledger diff for'
                . ' the first value; `comparison` is one row per pinned'
                . ' value. Each column of the diff sums to its own'
                . ' quality exactly — check it, because a column that'
                . ' does not add up disqualifies a candidate.',
            'in_force' => $this->AnalystProfile->summarise($inForce),
            'candidate' => $this->AnalystProfile->summarise($candidate),
            'candidate_edits' => array(
                'reporting.independent_orgs.points.cap' => '28 -> 16,'
                    . ' which moves the row (a lowered per-unit weight'
                    . ' would not: the signal is saturated at its cap)',
                'lifecycle.warninglist.enabled' => 'true -> false,'
                    . ' so its row vanishes',
                'attribution.galaxy.enabled' => 'false -> true, so its'
                    . ' row appears',
            ),
            'value' => self::VALUES[0],
            'detail' => $detail,
            'detail_unchanged' => $unchangedDetail,
            'detail_unchanged_note' => 'The empty diff. Every row is'
                . ' `same` and `changed` is false — this must read as'
                . ' "no change", never as a blank table.',
            'comparison' => $comparison,
            'comparison_empty' => array(),
            'comparison_empty_note' => 'What a new analyst sees. The'
                . ' honest state is an instruction — pin a value and its'
                . ' two columns appear here — not an empty table.',
            'context_builds' => 1,
            'context_builds_note' => 'One build, because the candidate'
                . ' and the profile in force have the same `exclusions`.'
                . ' A candidate that changed them would cost two, and'
                . ' the rows themselves would differ.',
            'bands' => $form->bandStrip($candidate['parameters']),
        );
    }

    /**
     * @return array
     */
    private function __default()
    {
        $row = $this->AnalystProfile->find('first', array(
            'conditions' => array('AnalystProfile.default' => 1),
            'recursive' => -1,
        ));
        return empty($row) ? array() : $row['AnalystProfile'];
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
        return empty($row['User']['id'])
            ? array()
            : $this->User->getAuthUser($row['User']['id']);
    }
}
