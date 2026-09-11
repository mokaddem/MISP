<?php
App::uses('AppController', 'Controller');
App::uses('MispTheme', 'MispTheme');
App::uses('ValueUrlTool', 'Tools');
App::uses('AnalystProfileFormTool', 'Tools');
App::uses('ValueSignalLoader', 'Tools');
App::uses('ValueVerdictTool', 'Tools');
App::uses('ValueVerdictDiffTool', 'Tools');
App::uses('ValueExclusionTool', 'Tools');

/**
 * The UI for owning an Analyst Profile: an index that names the profile
 * in force, a read-only viewer, a per-section editor, a one-click fork,
 * and a simulator that scores a candidate profile against a real value
 * before anything is saved.
 *
 * prd/analyst-profile/09-editor.md is the specification. Phase 1 built
 * the store and deliberately shipped no controller, so this file is also
 * where `queryACL/findMissingFunctionNames` finally has something to
 * check (02-store.md §6, amended).
 *
 * ## This is phase 8a, and it renders nothing
 *
 * §1.1 splits the editor into a contract, three prototypes and a
 * wiring pass, and **every action here answers with its view-model as
 * JSON**. Not as scaffolding: an analyst profile is one JSON document,
 * export and import are the sharing path, and a profile the API cannot
 * read is a profile no automation can review. What 8c adds is the HTML
 * branch — the same arrays, handed to the templates of whichever design
 * gets picked — so nothing here has to be unpicked to get there.
 *
 * The consequence worth stating: **a browser gets JSON today.** That is
 * the honest state of a phase whose whole point is that no design
 * decision has been made yet.
 *
 * ## There is no `add`
 *
 * D5 makes a fork a frozen copy and 02-store.md §5 makes it the only
 * way in — the shipped default is uneditable by an ordinary analyst, so
 * a blank-slate form would be a second entrance to a room with one
 * door, and an empty `parameters` document scores nothing and names no
 * signal. A new profile is a fork of one that already works, or an
 * `import` of somebody else's.
 *
 * ## Nothing here decides who may edit what
 *
 * `AnalystProfile::isEditableByCurrentUser()` does, and it is asked on
 * every write. D13 put the rule in the model on purpose: the ACL is a
 * per-action whitelist and cannot express *"your own, or your
 * organisation's if you are an org admin"*, so an ACL entry that tried
 * would be a second opinion about the same question — and two of those
 * is how the looser one becomes the answer.
 */
class AnalystProfilesController extends AppController
{
    public $components = array('Session', 'RequestHandler');

    public $uses = array('AnalystProfile');

    /** Where these views and elements live. */
    const THEME = 'Overmind';

    /** The most values an analyst may pin to the simulator (§2.2). */
    const COMPARISON_LIMIT = 8;

    /** Where the pinned set lives, per user. */
    const COMPARISON_SETTING = 'analyst_profile_comparison_set';

    /**
     * The editor posts field names the form helper cannot produce.
     *
     * A signal id contains dots, an attribute type a pipe, an
     * organisation key is a uuid — so the form writes its own bracket
     * names, and `SecurityComponent`'s field hash, which is built from
     * the names the helper emitted, can only ever fail against them.
     * **CSRF stays on**: it is the check that matters here, and it is
     * checked separately from the field hash.
     *
     * `pin` and `unpin` are on the list for a second reason. The
     * editor pins without leaving the page, so its press posts the
     * editor's own form — that is where the CSRF token is, and a
     * separate form for it inside the editor's form is markup a
     * browser discards. The hash that arrives is therefore the one
     * `edit` minted, over a different URL, and it cannot match.
     *
     * @return void
     */
    public function beforeFilter()
    {
        parent::beforeFilter();
        if (in_array($this->request->params['action'],
            array('edit', 'import', 'simulate', 'pin', 'unpin'), true)
        ) {
            $this->Security->validatePost = false;
        }
        /*
         * The editor posts more than once per page: every field change
         * recomputes the bench, and a pin posts beside it. A single-use
         * CSRF key makes the *first* of those spend the token the rest
         * need, so the second press is a blackhole — which is exactly
         * what the Value Profile page hit
         * (`ValuesController::beforeFilter()`), and this is the same
         * fix. A stable per-session key is the synchroniser-token
         * pattern; CSRF turns on an attacker being unable to read the
         * token cross-origin, never on its being fresh.
         */
        $this->Security->csrfUseOnce = false;
    }

    /**
     * Render under the theme these pages live in, whoever is asking.
     *
     * They have one implementation and it is under `Overmind`, so
     * naming it here says where the files are rather than overriding
     * anybody's preference. A reader whose own theme carries the
     * directory keeps it.
     *
     * @return void
     */
    public function beforeRender()
    {
        parent::beforeRender();
        if (!MispTheme::carries($this->theme, 'AnalystProfiles')) {
            $this->theme = self::THEME;
            $this->viewClass = 'Theme';
        }
    }

    /**
     * Every profile this reader may see, with the one in force named
     * first and unmistakably.
     *
     * The commonest confusion this feature can create is an analyst
     * editing a profile that is not weighting their pages — they forked,
     * forgot, and their organisation's profile still wins, or their own
     * is disabled. So each row carries its *standing*: in force, or the
     * reason it is not, naming the profile that beat it.
     *
     * @return CakeResponse
     */
    public function index()
    {
        $user = $this->Auth->user();
        $board = $this->AnalystProfile->indexFor($user);
        $inForce = $board['in_force'];
        return $this->__payload(array(
            'profiles' => $board['profiles'],
            /*
             * Null is a state and not an absence: a site admin who has
             * disabled the instance default leaves every reader who owns
             * no profile with no scoring at all, and 01-profile.md §5.3
             * has the page say so rather than showing a zero.
             */
            'in_force' => $inForce === null
                ? null
                : array(
                    'id' => (int)$inForce['id'],
                    'name' => $inForce['name'],
                ),
            'scoring_off' => $inForce === null,
            'loader_errors' => $this->__loaderErrors(),
            'comparison_set' => $this->__comparisonSet($user),
            'comparison_limit' => self::COMPARISON_LIMIT,
        ), 'index');
    }

    /**
     * One profile, read-only, section by section.
     *
     * Takes the simulator's optional `?value=` so each weight can be
     * shown beside the contribution it produced on that value. Without
     * one the contribution column stays empty rather than inventing a
     * number.
     *
     * @param int|string|null $id
     * @return CakeResponse
     */
    public function view($id = null)
    {
        $user = $this->Auth->user();
        $profile = $this->__profileOr404($user, $id);
        return $this->__payload(
            $this->__board($user, $profile, false),
            'view'
        );
    }

    /**
     * The per-section editor. GET describes the form; POST applies it.
     *
     * Three ways in, and they are deliberately different shapes:
     *
     * - `data[AnalystProfile][parameters]` — a section's fields, nested
     *   the way `AnalystProfileFormTool::merge()` expects. A section the
     *   form did not post is untouched.
     * - `data[AnalystProfile][parameters_json]` — the whole document,
     *   pasted. Replaces rather than merges, because that is what
     *   pasting a document means.
     * - `data[AnalystProfile][name]` / `[description]` — the label. Moves
     *   no counter (§4.1).
     *
     * @param int|string|null $id
     * @return CakeResponse
     */
    public function edit($id = null)
    {
        $user = $this->Auth->user();
        $profile = $this->__profileOr404($user, $id);
        $row = $profile['AnalystProfile'];
        if (!$this->AnalystProfile->isEditableByCurrentUser($user, $row)) {
            throw new ForbiddenException(__(
                'This profile is not yours to edit. Fork it and edit'
                . ' the copy.'
            ));
        }
        if (!$this->request->is(array('post', 'put'))) {
            return $this->__payload(
                $this->__board($user, $profile, true),
                'edit'
            );
        }

        $posted = isset($this->request->data['AnalystProfile'])
            ? $this->request->data['AnalystProfile']
            : $this->request->data;
        $benched = $this->__requestedValue();
        $form = new AnalystProfileFormTool();
        $stored = is_array($row['parameters']) ? $row['parameters'] : array();

        if (array_key_exists('parameters_json', $posted)) {
            $parsed = $form->parse($posted['parameters_json']);
            if (empty($parsed['ok'])) {
                $board = $this->__board($user, $profile, true);
                $board['raw'] = $posted['parameters_json'];
                $board['parse'] = array(
                    'error' => $parsed['error'],
                    'line' => isset($parsed['line'])
                        ? $parsed['line']
                        : null,
                );
                $board['open_section'] = 'raw';
                return $this->__refuse(array($parsed['error']), array(
                    'parse' => $board['parse'],
                ), array('view' => 'edit', 'vars' => $board));
            }
            $parameters = $parsed['parameters'];
        } elseif (isset($posted['parameters'])
            && is_array($posted['parameters'])
        ) {
            $parameters = $form->merge($stored, $posted['parameters']);
        } else {
            $parameters = $stored;
        }

        $checked = $form->validate($parameters);
        if (!empty($checked['errors'])) {
            $candidate = $profile;
            $candidate['AnalystProfile']['parameters'] = $parameters;
            return $this->__refuse($checked['errors'], array(
                'warnings' => $checked['warnings'],
                'bands' => $form->bandStrip($parameters),
            ), array(
                'view' => 'edit',
                'vars' => $this->__board($user, $candidate, true),
            ));
        }

        $save = array('id' => $row['id']);
        $changedParameters = $this->__canonical($parameters)
            !== $this->__canonical($stored);
        if ($changedParameters) {
            $save['parameters'] = $parameters;
        }
        foreach (array('name', 'description') as $label) {
            if (array_key_exists($label, $posted)) {
                $save[$label] = $posted[$label];
            }
        }
        $this->AnalystProfile->id = $row['id'];
        if (!$this->AnalystProfile->save(array('AnalystProfile' => $save))) {
            return $this->__refuse(
                $this->__flatten($this->AnalystProfile->validationErrors)
            );
        }
        /*
         * `revision` is the local edit counter and moves only when
         * `parameters` did (§4.1). Phase 10 keys its materialised
         * assessments on it, so a counter that moved for a rename would
         * invalidate every stored row for nothing.
         */
        if ($changedParameters) {
            $this->AnalystProfile->bumpRevision($row['id']);
        }
        $saved = $this->AnalystProfile->fetchProfile($user, $row['id']);
        return $this->__wrote(
            array(
                'saved' => true,
                'parameters_changed' => $changedParameters,
                'warnings' => $checked['warnings'],
                'profile' => $this->AnalystProfile->summarise(
                    $saved['AnalystProfile']
                ),
            ),
            $changedParameters
                ? sprintf(
                    __('Saved. %s is now at revision %s.'),
                    $saved['AnalystProfile']['name'],
                    $saved['AnalystProfile']['revision']
                )
                : __('Saved. Nothing in the document changed, so the'
                    . ' revision did not move.'),
            /*
             * The bench never leaves, and a save is the moment it
             * would: the redirect carries the value back so the pane
             * the edit was judged against is still under it.
             */
            array('action' => 'edit', $row['id'],
                '?' => $benched === null
                    ? array()
                    : array('value' => ValueUrlTool::encode($benched)))
        );
    }

    /**
     * Copy a profile into one the caller owns.
     *
     * **A user may hold one enabled profile** (phase 1 §5), so forking
     * with one already present is a decision rather than a silent
     * second row. Without `replace` this answers with the existing
     * profile named and nothing written; with it, the existing one is
     * **disabled and never deleted** — an analyst's tuned judgement is
     * not something a one-click flow may destroy (§6).
     *
     * @param int|string|null $id
     * @return CakeResponse
     */
    public function fork($id = null)
    {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException(__(
                'Forking creates a profile, so it is a POST.'
            ));
        }
        $user = $this->Auth->user();
        $source = $this->__profileOr404($user, $id);
        $posted = isset($this->request->data['AnalystProfile'])
            ? $this->request->data['AnalystProfile']
            : $this->request->data;
        $forOrg = !empty($posted['for_org']);
        $replace = !empty($posted['replace']);
        $name = isset($posted['name']) && trim((string)$posted['name']) !== ''
            ? trim((string)$posted['name'])
            : null;

        if ($forOrg && empty($user['Role']['perm_admin'])
            && empty($user['Role']['perm_site_admin'])
        ) {
            throw new ForbiddenException(__(
                'Forking to the organisation is an org-admin action,'
                . ' because it changes what colleagues read.'
            ));
        }

        $existing = $this->__enabledFor($user, $forOrg);
        if ($existing !== null && !$replace) {
            return $this->__confirm(array(
                'confirm' => 'replace',
                'existing' => $this->AnalystProfile->summarise($existing),
                'affects' => $forOrg ? $this->__orgReaders($user) : null,
                'message' => $forOrg
                    ? sprintf(
                        __('Your organisation already has an enabled'
                            . ' profile, %s. Replacing it disables it —'
                            . ' it is not deleted — and the fork becomes'
                            . ' the one in force.'),
                        $existing['name']
                    )
                    : sprintf(
                        __('You already have an enabled profile, %s.'
                            . ' Replacing it disables it — it is not'
                            . ' deleted — and the fork becomes the one'
                            . ' in force.'),
                        $existing['name']
                    ),
            ), array(
                'title' => $forOrg
                    ? __('Fork to my organisation')
                    : __('Fork to me'),
                'url' => array('action' => 'fork', $id),
                'fields' => array(
                    'replace' => 1,
                    'for_org' => $forOrg ? 1 : 0,
                ),
                'confirm_label' => $forOrg
                    ? __('Replace my organisation\'s profile')
                    : __('Replace my current profile'),
                'cancel' => array('action' => 'index'),
            ));
        }
        if ($existing !== null) {
            $this->AnalystProfile->id = $existing['id'];
            if (!$this->AnalystProfile->saveField('enabled', 0)) {
                return $this->__refuse(array(__(
                    'The profile in force could not be disabled, so'
                    . ' nothing was forked.'
                )));
            }
        }
        $fork = $this->AnalystProfile->forkProfile($user, $id, $name,
            $forOrg);
        if (empty($fork)) {
            /*
             * The source profile is still enabled or not, exactly as the
             * caller left it — except in the one case where it was just
             * disabled to make room. Re-enabling it is the honest
             * rollback: a failed fork must not leave a reader with no
             * profile at all.
             */
            if ($existing !== null) {
                $this->AnalystProfile->id = $existing['id'];
                $this->AnalystProfile->saveField('enabled', 1);
            }
            return $this->__refuse($this->__flatten(
                $this->AnalystProfile->validationErrors
            ) ?: array(__('The fork could not be saved.')));
        }
        return $this->__wrote(array(
            'forked' => true,
            /*
             * With `enabled` corrected, because `$existing` was read
             * before it was disabled and reporting a replaced profile
             * as still enabled is the same quiet lie this feature keeps
             * refusing to tell elsewhere.
             */
            'replaced' => $existing === null
                ? null
                : array('enabled' => false)
                    + $this->AnalystProfile->summarise($existing),
            'profile' => $this->AnalystProfile->summarise($fork['AnalystProfile']),
            'source' => $this->AnalystProfile->summarise($source['AnalystProfile']),
        ),
            $existing === null
                ? sprintf(
                    __('Forked. %s is yours and is the one in force.'),
                    $fork['AnalystProfile']['name']
                )
                : sprintf(
                    __('Forked. %1$s is now in force and %2$s is'
                        . ' disabled — not deleted.'),
                    $fork['AnalystProfile']['name'],
                    $existing['name']
                ),
            array('action' => 'edit', $fork['AnalystProfile']['id'])
        );
    }

    /**
     * Enable a profile, running the one-enabled swap in reverse.
     *
     * The invariant means enabling one disables the other, so the
     * response says which — a swap the reader did not ask for and
     * cannot see is the whole failure mode §3's standing exists to
     * prevent.
     *
     * @param int|string|null $id
     * @return CakeResponse
     */
    public function enable($id = null)
    {
        return $this->__setEnabled($id, true);
    }

    /**
     * @param int|string|null $id
     * @return CakeResponse
     */
    public function disable($id = null)
    {
        return $this->__setEnabled($id, false);
    }

    /**
     * @param int|string|null $id
     * @param bool $enabled
     * @return CakeResponse
     */
    private function __setEnabled($id, $enabled)
    {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException(__(
                'Changing what is in force is a POST.'
            ));
        }
        $user = $this->Auth->user();
        $profile = $this->__profileOr404($user, $id);
        $row = $profile['AnalystProfile'];
        if (!$this->AnalystProfile->isEditableByCurrentUser($user, $row)) {
            throw new ForbiddenException(__(
                'This profile is not yours to enable or disable.'
            ));
        }
        $displaced = null;
        if ($enabled && empty($row['default'])) {
            $displaced = $this->__enabledFor($user, !empty($row['org_id']));
            if ($displaced !== null
                && (int)$displaced['id'] === (int)$row['id']
            ) {
                $displaced = null;
            } elseif ($displaced !== null) {
                $this->AnalystProfile->id = $displaced['id'];
                $this->AnalystProfile->saveField('enabled', 0);
            }
        }
        $this->AnalystProfile->id = $row['id'];
        if (!$this->AnalystProfile->saveField('enabled', $enabled ? 1 : 0)) {
            if ($displaced !== null) {
                $this->AnalystProfile->id = $displaced['id'];
                $this->AnalystProfile->saveField('enabled', 1);
            }
            return $this->__refuse($this->__flatten(
                $this->AnalystProfile->validationErrors
            ) ?: array(__('The profile could not be saved.')));
        }
        /*
         * Re-resolved rather than reasoned about: the swap has just
         * moved which profile wins, and the caller's next question is
         * *so what is in force now*. `$row` and `$displaced` were both
         * read before the writes, so their `enabled` is corrected here
         * rather than reported stale.
         */
        $inForce = $this->AnalystProfile->resolveFor($user);
        return $this->__wrote(array(
            'enabled' => $enabled,
            'profile' => array('enabled' => $enabled)
                + $this->AnalystProfile->summarise($row),
            'displaced' => $displaced === null
                ? null
                : array('enabled' => false) + $this->AnalystProfile->summarise($displaced),
            'in_force' => $inForce === null
                ? null
                : $this->AnalystProfile->summarise($inForce),
        ),
            $displaced === null
                ? sprintf(
                    $enabled
                        ? __('%s is enabled.')
                        : __('%s is disabled.'),
                    $row['name']
                )
                : sprintf(
                    __('%1$s is enabled, so %2$s was disabled — you may'
                        . ' hold one at a time, and nothing was'
                        . ' deleted.'),
                    $row['name'],
                    $displaced['name']
                ),
            array('action' => 'index')
        );
    }

    /**
     * Delete a profile. Never the default, and never as a side effect —
     * §6 keeps deletion an explicit action for the same reason replace
     * only disables.
     *
     * @param int|string|null $id
     * @return CakeResponse
     */
    public function delete($id = null)
    {
        if (!$this->request->is(array('post', 'delete'))) {
            throw new MethodNotAllowedException(__(
                'Deleting a profile is a POST.'
            ));
        }
        $user = $this->Auth->user();
        $profile = $this->__profileOr404($user, $id);
        $row = $profile['AnalystProfile'];
        if (!empty($row['default'])) {
            throw new ForbiddenException(__(
                'The instance default is the fall-back every reader who'
                . ' owns no profile resolves to. It can be disabled;'
                . ' it cannot be deleted.'
            ));
        }
        if (!$this->AnalystProfile->isEditableByCurrentUser($user, $row)) {
            throw new ForbiddenException(__(
                'This profile is not yours to delete.'
            ));
        }
        if (!$this->AnalystProfile->delete($row['id'])) {
            return $this->__refuse(array(__(
                'The profile could not be deleted.'
            )));
        }
        return $this->__wrote(
            array(
                'deleted' => true,
                'profile' => $this->AnalystProfile->summarise($row),
            ),
            sprintf(__('%s is deleted.'), $row['name']),
            array('action' => 'index')
        );
    }

    /**
     * The JSON, as a download.
     *
     * `uuid` and `version` ride along, because an import decides what to
     * do about a collision by reading them (02-store.md §6).
     *
     * @param int|string|null $id
     * @return CakeResponse
     */
    public function export($id = null)
    {
        $user = $this->Auth->user();
        $profile = $this->__profileOr404($user, $id);
        $row = $profile['AnalystProfile'];
        $json = json_encode($this->__document($row),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $filename = sprintf(
            'analyst-profile-%s.json',
            trim(preg_replace('/[^a-z0-9]+/', '-',
                strtolower($row['name'])), '-')
        );
        return $this->RestResponse->viewData($json, 'application/json',
            false, true, $filename);
    }

    /**
     * Somebody else's profile, as a copy of their judgement.
     *
     * **A uuid that already exists locally is re-minted, never
     * overwritten** (02-store.md §6). An imported profile is a copy of
     * somebody else's judgement, and overwriting a local row of the same
     * uuid would silently rewrite the profile the assessment has been
     * naming.
     *
     * @return CakeResponse
     */
    public function import()
    {
        $user = $this->Auth->user();
        if (!$this->request->is('post')) {
            /*
             * A browser has to be given the form before it can post
             * one. REST keeps the refusal: there is nothing to GET
             * here for a caller that already holds a document.
             */
            if ($this->_isRest()) {
                throw new MethodNotAllowedException(__(
                    'Importing a profile is a POST.'
                ));
            }
            return $this->__payload(array(
                'can_import_for_org' => !empty($user['Role']['perm_admin'])
                    || !empty($user['Role']['perm_site_admin']),
                'holds_enabled' => $this->__enabledFor($user, false) !== null,
            ), 'import');
        }
        $posted = isset($this->request->data['AnalystProfile'])
            ? $this->request->data['AnalystProfile']
            : $this->request->data;
        $raw = null;
        foreach (array('json', 'parameters_json', 'document') as $key) {
            if (isset($posted[$key]) && is_string($posted[$key])) {
                $raw = $posted[$key];
                break;
            }
        }
        $document = $raw === null ? $posted : json_decode($raw, true);
        if (!is_array($document)) {
            return $this->__refuse(array(__(
                'The import must be a JSON object with `name` and'
                . ' `parameters`.'
            )));
        }
        if (empty($document['parameters'])
            || !is_array($document['parameters'])
        ) {
            return $this->__refuse(array(__(
                '`parameters` is missing, so there would be nothing to'
                . ' score with.'
            )));
        }
        $form = new AnalystProfileFormTool();
        $checked = $form->validate($document['parameters']);
        if (!empty($checked['errors'])) {
            return $this->__refuse($checked['errors'],
                array('warnings' => $checked['warnings']));
        }
        $forOrg = !empty($posted['for_org']);
        if ($forOrg && empty($user['Role']['perm_admin'])
            && empty($user['Role']['perm_site_admin'])
        ) {
            throw new ForbiddenException(__(
                'Importing to the organisation is an org-admin action.'
            ));
        }
        $existing = $this->__enabledFor($user, $forOrg);
        $reuuid = false;
        $uuid = isset($document['uuid']) ? $document['uuid'] : null;
        if ($uuid !== null && Validation::uuid($uuid)) {
            $clash = $this->AnalystProfile->find('first', array(
                'conditions' => array('AnalystProfile.uuid' => $uuid),
                'fields' => array('AnalystProfile.id'),
                'recursive' => -1,
            ));
            if (!empty($clash)) {
                $reuuid = true;
            }
        } else {
            $reuuid = true;
        }
        $save = array(
            'uuid' => $reuuid ? CakeText::uuid() : $uuid,
            'name' => isset($document['name']) && $document['name'] !== ''
                ? $document['name']
                : __('Imported profile'),
            'description' => isset($document['description'])
                ? $document['description']
                : null,
            'parameters' => $document['parameters'],
            'user_id' => $forOrg ? null : $user['id'],
            'org_id' => $forOrg ? $user['org_id'] : null,
            'default' => 0,
            /*
             * An import lands disabled when the owner already holds an
             * enabled profile, rather than refusing or displacing one.
             * Import is how a profile is *reviewed* before it is
             * adopted — §8 makes it the sharing path — and a reviewer
             * should be able to simulate it without their own pages
             * changing underneath them first.
             */
            'enabled' => $existing === null ? 1 : 0,
            'version' => isset($document['version'])
                ? (int)$document['version']
                : 1,
            'revision' => 1,
        );
        $this->AnalystProfile->create();
        if (!$this->AnalystProfile->save(array('AnalystProfile' => $save))) {
            return $this->__refuse($this->__flatten(
                $this->AnalystProfile->validationErrors
            ) ?: array(__('The profile could not be imported.')));
        }
        $imported = $this->AnalystProfile->fetchProfile($user,
            $this->AnalystProfile->id);
        return $this->__wrote(array(
            'imported' => true,
            're_uuided' => $reuuid,
            'enabled' => $existing === null,
            'blocked_by' => $existing === null
                ? null
                : $this->AnalystProfile->summarise($existing),
            'warnings' => $checked['warnings'],
            'profile' => $this->AnalystProfile->summarise($imported['AnalystProfile']),
        ),
            $existing === null
                ? sprintf(
                    __('Imported. %s is yours and is the one in force.'),
                    $imported['AnalystProfile']['name']
                )
                : sprintf(
                    __('Imported. %1$s is disabled, because %2$s is'
                        . ' still in force — simulate it before you'
                        . ' adopt it.'),
                    $imported['AnalystProfile']['name'],
                    $existing['name']
                ),
            array('action' => 'edit', $imported['AnalystProfile']['id'])
        );
    }

    /**
     * Load the shipped defaults, the way every other model that reads
     * shipped JSON is driven.
     *
     * @param bool|string $force
     * @return CakeResponse
     */
    public function update($force = false)
    {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException(__(
                'Updating the shipped defaults is a POST.'
            ));
        }
        $outcome = $this->AnalystProfile->updateDefaults(
            $force === true || $force === 'true' || $force === '1'
        );
        return $this->__wrote(
            array('outcomes' => $outcome),
            __('The shipped defaults were loaded.'),
            array('action' => 'index')
        );
    }

    /**
     * What a candidate profile would do to a value, beside what the
     * profile in force does to it.
     *
     * **It computes and it does not save.** The candidate arrives as
     * `parameters_json` or as a section form merged over the stored
     * document, is scored in memory, and nothing is written — which is
     * why the simulator works on a profile the analyst cannot edit,
     * including the instance default. That is how somebody decides
     * whether they need a fork at all.
     *
     * @param int|string|null $id The candidate's base profile
     * @return CakeResponse
     */
    public function simulate($id = null)
    {
        $user = $this->Auth->user();
        $base = $this->__profileOr404($user, $id);
        $row = $base['AnalystProfile'];
        $posted = isset($this->request->data['AnalystProfile'])
            ? $this->request->data['AnalystProfile']
            : array();
        $form = new AnalystProfileFormTool();
        $stored = is_array($row['parameters']) ? $row['parameters'] : array();

        $candidateParameters = $stored;
        $parseError = null;
        if (isset($posted['parameters_json'])) {
            $parsed = $form->parse($posted['parameters_json']);
            if (empty($parsed['ok'])) {
                $parseError = $parsed;
            } else {
                $candidateParameters = $parsed['parameters'];
            }
        } elseif (isset($posted['parameters'])
            && is_array($posted['parameters'])
        ) {
            $candidateParameters = $form->merge($stored,
                $posted['parameters']);
        }
        if ($parseError !== null) {
            return $this->__refuse(array($parseError['error']), array(
                'parse' => array(
                    'error' => $parseError['error'],
                    'line' => isset($parseError['line'])
                        ? $parseError['line']
                        : null,
                ),
            ), array('redirect' => array('action' => 'edit', $id)));
        }

        $checked = $form->validate($candidateParameters);
        $simulation = $this->__simulation($user, $row, $candidateParameters);
        /*
         * The editor's bench is this computation, so the editor asks
         * for it again whenever a field changes — as the fragment
         * alone, since the rest of the page is already on screen.
         * One producer: the pane and the page it expands into cannot
         * disagree, and neither can the pane and itself after an edit.
         */
        if (!$this->_isRest() && $this->request->is('ajax')) {
            $this->set('bench', $simulation);
            $this->set('bands', $form->bandStrip($candidateParameters));
            $this->set('full', false);
            $this->set('profileId', $row['id']);
            $this->layout = false;
            return $this->render('/Elements/AnalystProfiles/bench');
        }
        return $this->__payload(
            $simulation + array(
                'candidate_valid' => empty($checked['errors']),
                'errors' => $checked['errors'],
                'warnings' => $checked['warnings'],
                'bands' => $form->bandStrip($candidateParameters),
            ),
            'simulate'
        );
    }

    /**
     * One candidate document, scored against the profile in force over
     * every value on the bench.
     *
     * **Shared with the editor**, which is not an optimisation but the
     * design: §2 exists because MISP already shipped a simulator that
     * was a destination, and the bench in the right-hand pane of
     * `edit` is this same computation. One producer, so the pane and
     * the page it expands into cannot disagree.
     *
     * @param array $user
     * @param array $row The base profile, unwrapped
     * @param array $candidateParameters The document to score
     * @return array
     */
    private function __simulation(array $user, array $row,
        array $candidateParameters
    ) {
        $inForce = $this->AnalystProfile->resolveFor($user);
        $candidate = $row;
        $candidate['parameters'] = $candidateParameters;

        $values = $this->__simulationValues($user);
        $focus = $this->__requestedValue();
        if ($focus !== null) {
            $values = array_values(array_unique(
                array_merge(array($focus), $values)
            ));
        }
        $values = array_slice($values, 0, self::COMPARISON_LIMIT + 1);

        $this->loadModel('ValueProfile');
        $engine = new ValueVerdictTool($this->ValueProfile);
        $builds = 0;
        $rows = array();
        $detail = null;
        foreach ($values as $index => $value) {
            $scored = $this->__scorePair($engine, $user, $value, $inForce,
                $candidate, $builds);
            $rows[] = ValueVerdictDiffTool::headline(
                $value,
                $scored['before'],
                $scored['after']
            );
            if ($index === 0) {
                $detail = ValueVerdictDiffTool::diff(
                    $scored['before'],
                    $scored['after']
                );
            }
        }
        return array(
            'base' => $this->AnalystProfile->summarise($row),
            'in_force' => $inForce === null
                ? null
                : $this->AnalystProfile->summarise($inForce),
            /*
             * A simulation against nothing is not a simulation. When the
             * reader has pinned no values and arrived with none, the
             * honest answer is the instruction, not an empty table.
             */
            'values' => $values,
            'focus' => $focus,
            'detail' => $detail,
            'comparison' => $rows,
            'comparison_set' => $this->__comparisonSet($user),
            'context_builds' => $builds,
        );
    }

    /**
     * Add a value to this reader's comparison set.
     *
     * @param string|null $b64value
     * @return CakeResponse
     */
    public function pin($b64value = null)
    {
        return $this->__setPinned($b64value, true);
    }

    /**
     * @param string|null $b64value
     * @return CakeResponse
     */
    public function unpin($b64value = null)
    {
        return $this->__setPinned($b64value, false);
    }

    /**
     * @param string|null $b64value
     * @param bool $pinned
     * @return CakeResponse
     */
    private function __setPinned($b64value, $pinned)
    {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException(__(
                'Changing the comparison set is a POST.'
            ));
        }
        $user = $this->Auth->user();
        $value = $this->__decodeValue($b64value);
        $set = $this->__comparisonSet($user);
        $set = array_values(array_filter($set, function ($pinnedValue) use ($value) {
            return $pinnedValue !== $value;
        }));
        if ($pinned) {
            /*
             * Newest first and capped, so pinning a ninth value drops
             * the oldest rather than refusing — the set is a working
             * shortlist, not a registry, and a refusal would make the
             * analyst prune before they could compare.
             */
            array_unshift($set, $value);
            $set = array_slice($set, 0, self::COMPARISON_LIMIT);
        }
        $this->loadModel('UserSetting');
        $this->UserSetting->setSettingInternal($user['id'],
            self::COMPARISON_SETTING, $set);
        $written = array(
            'pinned' => $pinned,
            'value' => $value,
            'comparison_set' => $set,
            'limit' => self::COMPARISON_LIMIT,
        );
        /*
         * The editor pins from beside the document it is editing and
         * redraws the bench itself, so following a redirect back would
         * re-render the whole page to throw it away — and throw the
         * unsaved edits away with it.
         */
        if (!$this->_isRest() && $this->request->is('ajax')) {
            return $this->RestResponse->viewData($written, 'application/json');
        }
        return $this->__wrote(
            $written,
            $pinned
                ? sprintf(
                    __('%s is pinned, and every profile change is now'
                        . ' judged against it too.'),
                    $value
                )
                : sprintf(__('%s is unpinned.'), $value),
            $this->referer(array('action' => 'index'), true)
        );
    }

    /**
     * Score one value under two profiles, building the context once per
     * distinct exclusion plan.
     *
     * **The candidate can change the context, not only the score.**
     * `exclusions` is applied by the context builder — `orgs.own` is a
     * query predicate in `Value::conditionsFor()` — so two profiles
     * whose exclusion sections differ are two different sets of rows,
     * and scoring both from one context would show a diff no saved
     * profile could reproduce. When the sections agree, which is the
     * common case of editing a weight, one build serves both.
     *
     * @param ValueVerdictTool $engine
     * @param array $user
     * @param string $value
     * @param array|null $inForce
     * @param array $candidate
     * @param int $builds Incremented per context built, so the response
     *                    can report what the run actually cost
     * @return array `before` and `after`
     */
    private function __scorePair(ValueVerdictTool $engine, array $user, $value,
        $inForce, array $candidate, &$builds
    ) {
        $shared = $this->__exclusionSignature($inForce)
            === $this->__exclusionSignature($candidate);
        $context = $this->ValueProfile->verdictContextFor($user, $value,
            $inForce);
        $builds++;
        $before = $engine->assess($context, $inForce);
        if ($shared) {
            $after = $engine->assess($context, $candidate);
        } else {
            $candidateContext = $this->ValueProfile->verdictContextFor(
                $user, $value, $candidate);
            $builds++;
            $after = $engine->assess($candidateContext, $candidate);
        }
        return array('before' => $before, 'after' => $after);
    }

    /**
     * Two profiles' exclusion sections, canonically, so they can be
     * compared for equality.
     *
     * @param array|null $profile
     * @return string
     */
    private function __exclusionSignature($profile)
    {
        if (!is_array($profile)
            || !isset($profile['parameters']['exclusions'])
        ) {
            return '[]';
        }
        return $this->__canonical($profile['parameters']['exclusions']);
    }

    /**
     * The values a simulation runs over: the reader's pinned set.
     *
     * @param array $user
     * @return array
     */
    private function __simulationValues(array $user)
    {
        return $this->__comparisonSet($user);
    }

    /**
     * @param array $user
     * @return array
     */
    private function __comparisonSet(array $user)
    {
        $this->loadModel('UserSetting');
        $set = $this->UserSetting->getValueForUser($user['id'],
            self::COMPARISON_SETTING);
        if (!is_array($set)) {
            return array();
        }
        $out = array();
        foreach ($set as $value) {
            if (is_string($value) && $value !== '') {
                $out[] = $value;
            }
        }
        return array_slice($out, 0, self::COMPARISON_LIMIT);
    }

    /**
     * The section the caller wants open, when the link that brought
     * them here knew which one answers their question.
     *
     * @return string|null
     */
    private function __requestedSection()
    {
        if (empty($this->request->query['section'])) {
            return null;
        }
        $section = (string)$this->request->query['section'];
        if ($section === 'raw'
            || in_array($section, AnalystProfileFormTool::SECTION_ORDER, true)
        ) {
            return $section;
        }
        return null;
    }

    /**
     * The value the caller asked about, from `?value=` or a posted one.
     *
     * @return string|null
     */
    private function __requestedValue()
    {
        foreach (array('value', 'b64value') as $key) {
            if (!empty($this->request->query[$key])) {
                return $this->__decodeValue(
                    $this->request->query[$key]
                );
            }
        }
        $posted = isset($this->request->data['AnalystProfile'])
            ? $this->request->data['AnalystProfile']
            : array();
        if (!empty($posted['value'])) {
            return $this->__decodeValue($posted['value']);
        }
        return null;
    }

    /**
     * The value a request names, or a 404.
     *
     * `ValueUrlTool` owns the encoding — the Value Profile page mints
     * the same `?value=` and there is one implementation of the
     * alphabet question — and the refusal stays here, so this
     * endpoint's wording is its own.
     *
     * @param string|null $b64value
     * @return string
     * @throws NotFoundException
     */
    private function __decodeValue($b64value)
    {
        $value = ValueUrlTool::decode($b64value);
        if ($value === null) {
            throw new NotFoundException(__(
                'That is not a value this page can read. Values travel'
                . ' base64-encoded, because they are arbitrary strings'
                . ' in a URL.'
            ));
        }
        return $value;
    }

    /**
     * Everything `view` and `edit` describe about one profile.
     *
     * @param array $user
     * @param array $profile
     * @param bool $editable
     * @return array
     */
    private function __board(array $user, array $profile, $editable)
    {
        $row = $profile['AnalystProfile'];
        $parameters = is_array($row['parameters'])
            ? $row['parameters']
            : array();
        $form = new AnalystProfileFormTool();
        $sources = $this->__sources($user, $parameters);
        $focus = $this->__requestedValue();
        if ($focus !== null) {
            $sources = array_merge($sources,
                $this->__contributionsFor($user, $focus, $row));
        }
        $checked = $form->validate($parameters);
        return array(
            'profile' => $this->AnalystProfile->summarise($row),
            'editable' => $editable
                && $this->AnalystProfile->isEditableByCurrentUser($user, $row),
            'sections' => $form->sections($parameters, $sources),
            'bands' => $form->bandStrip($parameters),
            'errors' => $checked['errors'],
            'warnings' => $checked['warnings'],
            'legacy' => $form->legacyShapes($parameters),
            'loader_errors' => $this->__loaderErrors(),
            'raw' => json_encode($parameters,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'value' => $focus,
            'value_b64' => $focus === null
                ? null
                : ValueUrlTool::encode($focus),
            /*
             * Which section the page opens on. A `not_counted` entry on
             * a value page links straight to the exclusion that
             * produced it, and landing on the signals table with the
             * answer three clicks away would make that link a gesture.
             */
            'open_section' => $this->__requestedSection(),
            'comparison_limit' => self::COMPARISON_LIMIT,
            'comparison_set' => $this->__comparisonSet($user),
            /*
             * The right-hand pane, and it is the simulator rather than
             * a reading of the stored document: the whole bet of this
             * design is that the consequence of an edit is unavoidable
             * rather than merely available, which it is not if the
             * pane shows what the profile already does.
             *
             * It costs nothing when there is nothing to score — no
             * pinned values and no value arrived with means no
             * context is built at all.
             */
            'bench' => $this->__simulation($user, $row, $parameters),
        );
    }

    /**
     * What each weight produced on one value, so `view` can show the
     * contribution beside the number that caused it.
     *
     * @param array $user
     * @param string $value
     * @param array $row
     * @return array `ledger` and `not_counted`, keyed by signal id
     */
    private function __contributionsFor(array $user, $value, array $row)
    {
        $this->loadModel('ValueProfile');
        $engine = new ValueVerdictTool($this->ValueProfile);
        $verdict = $engine->verdictFor($user, $value,
            array('profile' => $row));
        $ledger = array();
        foreach ($verdict['ledger'] as $group) {
            foreach ($group['signals'] as $signalRow) {
                if (!empty($signalRow['id'])) {
                    $ledger[$signalRow['id']] = $signalRow['contribution'];
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
        return array(
            'ledger' => $ledger,
            'not_counted' => $notCounted,
            'verdict' => $verdict,
        );
    }

    /**
     * The option lists the form tool may not fetch for itself.
     *
     * **Only the keys a map already carries**, plus a picker source for
     * adding one. §4 is explicit that `org_trust` must never render a
     * row per organisation on the instance, and the same restraint
     * applies to what is *sent*: an instance with 900 organisations
     * would put 900 names into a response about four of them.
     *
     * @param array $user
     * @param array $parameters
     * @return array
     */
    private function __sources(array $user, array $parameters)
    {
        return array(
            'attribute_types' => $this->__attributeTypes(),
            'orgs' => $this->__gradedOrgs($parameters),
            'warninglists' => $this->__warninglistNames(),
            'modules' => $this->__enabledModules($user),
        );
    }

    /**
     * @return array
     */
    private function __attributeTypes()
    {
        $this->loadModel('MispAttribute');
        return array_keys($this->MispAttribute->typeDefinitions);
    }

    /**
     * The organisations this profile grades, by uuid, with their names.
     *
     * A graded organisation that is not on this instance keeps its uuid
     * as its label rather than disappearing — a profile written
     * elsewhere is the case import exists for, and dropping the row
     * would lose the grade.
     *
     * @param array $parameters
     * @return array uuid => name
     */
    private function __gradedOrgs(array $parameters)
    {
        $uuids = isset($parameters['reference']['org_trust'])
            && is_array($parameters['reference']['org_trust'])
            ? array_keys($parameters['reference']['org_trust'])
            : array();
        if (empty($uuids)) {
            return array();
        }
        $this->loadModel('Organisation');
        $rows = $this->Organisation->find('all', array(
            'conditions' => array('Organisation.uuid' => $uuids),
            'fields' => array('Organisation.uuid', 'Organisation.name'),
            'recursive' => -1,
        ));
        $out = array();
        foreach ($rows as $organisation) {
            $out[$organisation['Organisation']['uuid']] =
                $organisation['Organisation']['name'];
        }
        return $out;
    }

    /**
     * @return array name => id
     */
    private function __warninglistNames()
    {
        $this->loadModel('Warninglist');
        $rows = $this->Warninglist->find('all', array(
            'fields' => array('Warninglist.id', 'Warninglist.name'),
            'recursive' => -1,
            'order' => array('Warninglist.name ASC'),
        ));
        $out = array();
        foreach ($rows as $list) {
            $out[$list['Warninglist']['name']] =
                (int)$list['Warninglist']['id'];
        }
        return $out;
    }

    /**
     * What this instance actually offers, or nothing at all.
     *
     * The modules service is a third party and may be down. An empty
     * list is the honest answer for that, and the enrichment section
     * renders what the profile declares rather than pretending the
     * declaration is unknown — which is phase 7 §7.1's lesson stated in
     * a new place: *"nothing is selected"* is also true when the service
     * is unreachable, so the two must not look the same.
     *
     * @param array $user
     * @return array name => name
     */
    private function __enabledModules(array $user)
    {
        $this->loadModel('Module');
        $enabled = $this->Module->getEnabledModules($user);
        if (!is_array($enabled) || empty($enabled['modules'])) {
            return array();
        }
        $out = array();
        foreach ($enabled['modules'] as $module) {
            if (!empty($module['name'])) {
                $out[$module['name']] = $module['name'];
            }
        }
        ksort($out);
        return $out;
    }

    /**
     * The enabled profile of the owner a fork would land on, or null.
     *
     * @param array $user
     * @param bool $forOrg
     * @return array|null
     */
    private function __enabledFor(array $user, $forOrg)
    {
        $conditions = array('AnalystProfile.enabled' => 1);
        if ($forOrg) {
            $conditions['AnalystProfile.org_id'] = $user['org_id'];
        } else {
            $conditions['AnalystProfile.user_id'] = $user['id'];
        }
        $profile = $this->AnalystProfile->find('first', array(
            'conditions' => $conditions,
            'recursive' => -1,
        ));
        return empty($profile) ? null : $profile['AnalystProfile'];
    }

    /**
     * How many colleagues an organisation profile would apply to.
     *
     * Under D3 an org profile is what every colleague who has not
     * forked resolves to, so this counts the users in the organisation
     * who hold no enabled profile of their own — which is the number the
     * confirm has to name (§6). Counting the whole organisation would
     * overstate it and counting nobody would understate it.
     *
     * @param array $user
     * @return array
     */
    private function __orgReaders(array $user)
    {
        $this->loadModel('User');
        $total = $this->User->find('count', array(
            'conditions' => array(
                'User.org_id' => $user['org_id'],
                'User.disabled' => 0,
            ),
            'recursive' => -1,
        ));
        $owners = $this->AnalystProfile->find('all', array(
            'conditions' => array(
                'AnalystProfile.enabled' => 1,
                'AnalystProfile.user_id !=' => null,
            ),
            'fields' => array('AnalystProfile.user_id'),
            'recursive' => -1,
        ));
        $ownerIds = array();
        foreach ($owners as $owner) {
            $ownerIds[] = (int)$owner['AnalystProfile']['user_id'];
        }
        $exempt = 0;
        if (!empty($ownerIds)) {
            $exempt = $this->User->find('count', array(
                'conditions' => array(
                    'User.org_id' => $user['org_id'],
                    'User.disabled' => 0,
                    'User.id' => $ownerIds,
                ),
                'recursive' => -1,
            ));
        }
        return array(
            'users' => (int)$total,
            'own_profile' => (int)$exempt,
            'affected' => max(0, (int)$total - (int)$exempt),
        );
    }

    /**
     * The profile or a 404 — never "you may not see this", which would
     * confirm that a profile with that id exists.
     *
     * @param array $user
     * @param int|string|null $id
     * @return array
     * @throws NotFoundException
     */
    private function __profileOr404(array $user, $id)
    {
        if ($id === null || $id === '') {
            throw new NotFoundException(__('No profile named.'));
        }
        $profile = $this->AnalystProfile->fetchProfile($user, $id);
        if (empty($profile)) {
            throw new NotFoundException(__('Invalid analyst profile.'));
        }
        return $profile;
    }

    /**
     * The exportable document: what an import expects to be handed.
     *
     * @param array $row
     * @return array
     */
    private function __document(array $row)
    {
        return array(
            'uuid' => $row['uuid'],
            'name' => $row['name'],
            'description' => $row['description'],
            'version' => (int)$row['version'],
            'parameters' => is_array($row['parameters'])
                ? $row['parameters']
                : array(),
        );
    }

    /**
     * Files the loader skipped, both subjects at once.
     *
     * The engine skips them silently (03-signals.md §8.5), so this is
     * the page where an admin finds out — `Workflow` surfaces
     * `$error_while_loading` in the same place for the same reason.
     *
     * @return array
     */
    private function __loaderErrors()
    {
        $errors = array();
        foreach (array(
            ValueSignalLoader::SUBJECT_SIGNAL => __('Signal'),
            ValueSignalLoader::SUBJECT_ESCALATION => __('Escalation'),
        ) as $subject => $label) {
            foreach (ValueSignalLoader::errors($subject) as $file => $why) {
                $errors[] = array(
                    'subject' => $label,
                    'file' => $file,
                    'reason' => $why,
                );
            }
        }
        return $errors;
    }

    /**
     * A document in a comparable form — recursively key-sorted, so two
     * documents differing only in key order compare equal.
     *
     * This is what decides whether `revision` moves. A form that
     * round-trips a section can reorder its keys without changing a
     * single value, and a revision bumped for that would invalidate
     * every materialised assessment phase 10 stores for nothing.
     *
     * @param mixed $value
     * @return string
     */
    private function __canonical($value)
    {
        return json_encode($this->__sortKeys($value));
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function __sortKeys($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        $out = array();
        foreach ($value as $key => $member) {
            $out[$key] = $this->__sortKeys($member);
        }
        ksort($out);
        return $out;
    }

    /**
     * CakePHP's per-field error map as a flat list of sentences.
     *
     * @param array $errors
     * @return array
     */
    private function __flatten(array $errors)
    {
        $out = array();
        foreach ($errors as $field => $messages) {
            foreach ((array)$messages as $message) {
                $out[] = is_string($field) && !is_numeric($field)
                    ? sprintf('%s: %s', $field, $message)
                    : $message;
            }
        }
        return $out;
    }

    /**
     * A refusal, with everything the reader needs to fix it.
     *
     * **Through MISP's own failure helper, and it answers 403.** The
     * first implementation set 400 on `$this->response` and handed the
     * body to `viewData()` — which ignores it: `prepareResponse()`
     * builds a fresh `CakeResponse` with the code it was passed, and
     * `viewData()` always passes 200. So every refusal answered *200
     * with `saved: false`*, which tells an automated caller the save
     * happened and leaves the truth in the body.
     *
     * 403 is semantically odd for a validation failure, and it is what
     * `saveFailResponse()` has answered for every failed save in MISP
     * for years. Consistency with the platform wins over REST purity
     * here: a client that already treats 403-with-`saved: false` as a
     * refused save handles this endpoint with no special case.
     *
     * **A browser is refused on the page it posted from**, with the
     * document it posted still in the form. Redirecting to the index
     * would answer *"no"* and throw away the paste that caused it,
     * which is the one thing an analyst cannot get back.
     *
     * @param array $errors
     * @param array $extra Carried under `data`, which is where the
     *                     helper puts a caller's own payload
     * @param array|null $html `view` and `vars` — the page to re-render
     *                         for a browser, with what it should show
     * @return CakeResponse
     */
    private function __refuse(array $errors, array $extra = array(),
        array $html = null
    ) {
        if (!$this->_isRest()) {
            foreach ($errors as $error) {
                $this->Flash->error($error);
            }
            if ($html === null) {
                return $this->redirect(array('action' => 'index'));
            }
            if (isset($html['redirect'])) {
                return $this->redirect($html['redirect']);
            }
            $vars = $html['vars'];
            $vars['errors'] = array_merge(
                isset($vars['errors']) ? $vars['errors'] : array(),
                $errors
            );
            $this->set($vars);
            $this->set('payload', $vars);
            return $this->render($html['view']);
        }
        return $this->RestResponse->saveFailResponse(
            'AnalystProfiles',
            isset($this->request->params['action'])
                ? $this->request->params['action']
                : 'edit',
            isset($this->request->params['pass'][0])
                ? $this->request->params['pass'][0]
                : false,
            $errors,
            'json',
            empty($extra) ? null : $extra
        );
    }

    /**
     * Every action's answer, in whichever of the two shapes the caller
     * asked for.
     *
     * **The same array, both ways.** A REST caller gets exactly the
     * JSON it got before there were templates; a browser gets the
     * template, handed the identical keys. An action that grew a
     * second view-model for its page would put the fixtures and the
     * page out of step the first time one of them changed, and the
     * fixtures are what the templates are checked against.
     *
     * An action with no view — every write — is JSON either way, and
     * the HTML path is `__wrote()` below.
     *
     * @param array $data
     * @param string|null $view
     * @return CakeResponse
     */
    private function __payload(array $data, $view = null)
    {
        if ($view === null || $this->_isRest()) {
            return $this->RestResponse->viewData($data, 'application/json');
        }
        $this->set($data);
        $this->set('payload', $data);
        return $this->render($view);
    }

    /**
     * A write's answer: the payload over REST, a flash and a redirect
     * in a browser.
     *
     * A write has nothing to render — the page a reader wants next is
     * the one showing the result — so the outcome travels as a flash
     * and the destination is named by the caller rather than guessed
     * from the referer, which lies as soon as a confirm sat in between.
     *
     * @param array $data
     * @param string $message
     * @param array|string $url
     * @return CakeResponse
     */
    private function __wrote(array $data, $message, $url)
    {
        if ($this->_isRest()) {
            return $this->RestResponse->viewData($data, 'application/json');
        }
        $this->Flash->success($message);
        return $this->redirect($url);
    }

    /**
     * A write that stopped to ask.
     *
     * The two that do are the one-enabled swap and a fork into an
     * occupied slot, and both are refusals to act silently rather than
     * failures: the consequence is named, and the same POST carrying
     * `replace` goes through. REST callers get the same payload and
     * decide for themselves.
     *
     * @param array $data
     * @param array $form `url`, `fields` and `confirm_label`
     * @return CakeResponse
     */
    private function __confirm(array $data, array $form)
    {
        if ($this->_isRest()) {
            return $this->RestResponse->viewData($data, 'application/json');
        }
        $this->set($data);
        $this->set('form', $form);
        return $this->render('confirm');
    }
}
