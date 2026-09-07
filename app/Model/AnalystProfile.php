<?php

App::uses('AppModel', 'Model');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');

/**
 * The Analyst Profile — the configuration object the value assessment is
 * computed from.
 *
 * One row holds one JSON document of named sections: signal weights,
 * thresholds, escalations, exclusions, relevance TTLs, source trust and
 * enrichment defaults. Three scopes own them — a user, an organisation, or
 * the instance — and exactly one profile is in force for any viewer, which
 * is what lets the page name the profile that produced a number.
 *
 * prd/analyst-profile/02-store.md is the specification. This phase treats
 * `parameters` as opaque beyond "it parses"; phases 2-7 give each section
 * its contract.
 */
class AnalystProfile extends AppModel
{
    public $actsAs = array('Containable');

    /** Where a shipped default profile is read from. */
    const DEFAULT_PROFILE_PATH = APP . 'files/analyst-profiles/';

    /**
     * Resolution cache, keyed by "user_id:org_id".
     *
     * resolveFor() is called once per panel and the value page loads up to
     * twenty-seven of them, so the profile is resolved once per request.
     *
     * @var array
     */
    private $resolutionCache = array();

    public $validate = array(
        'name' => array(
            'notBlank' => array('rule' => 'notBlank'),
            'maxLength' => array('rule' => array('maxLength', 191)),
        ),
        'uuid' => array(
            'uuid' => array('rule' => 'uuid', 'allowEmpty' => true),
            'unique' => array('rule' => 'isUnique'),
        ),
    );

    public function afterFind($results, $primary = false)
    {
        foreach ($results as $k => $v) {
            if (!isset($v[$this->alias]['parameters'])) {
                continue;
            }
            $raw = $v[$this->alias]['parameters'];
            if ($raw === null || $raw === '') {
                $results[$k][$this->alias]['parameters'] = array();
                continue;
            }
            $decoded = json_decode($raw, true);
            if ($decoded === null || !is_array($decoded)) {
                /*
                 * Deliberately not substituted with an empty array, which is
                 * what DecayingModel::afterFind does. A profile whose
                 * parameters will not parse has to reach the engine as
                 * broken, because the hero names the profile it scored with
                 * and silently scoring under a different one — the empty one
                 * — would make that name a lie. 02-store.md §3.1.
                 */
                $results[$k][$this->alias]['parameters'] = array();
                $results[$k][$this->alias]['parameters_unparseable'] = true;
            } else {
                $results[$k][$this->alias]['parameters'] = $decoded;
            }
        }
        return $results;
    }

    public function beforeValidate($options = array())
    {
        parent::beforeValidate();
        $data = &$this->data[$this->alias];

        /*
         * Seed columns on create only. CakePHP saves the fields it is
         * handed, so defaulting these on an update writes them: a partial
         * save of the shipped default — a rename, or phase 8's edit form —
         * would set `default` to 0 and stop it being the default at all.
         * Found by the live probe (02-store.md §7.6), which the stubbed
         * harness could not see because its save() always succeeded.
         */
        if (empty($data['id'])) {
            if (empty($data['uuid'])) {
                $data['uuid'] = CakeText::uuid();
            }
            if (!isset($data['default'])) {
                $data['default'] = 0;
            }
            if (!isset($data['enabled'])) {
                $data['enabled'] = 1;
            }
            foreach (array('version', 'revision') as $counter) {
                if (!isset($data[$counter])) {
                    $data[$counter] = 1;
                }
            }
        }
        if (isset($data['parameters']) && is_array($data['parameters'])) {
            $data['parameters'] = json_encode($data['parameters']);
        }
        if (isset($data['parameters'])
            && !$this->validateParameters($data['parameters'])) {
            $this->invalidate(
                'parameters',
                __('The parameters are not a JSON object.')
            );
            return false;
        }
        if (!$this->__validateOwnership($data)) {
            return false;
        }
        return $this->__validateOneEnabledPerOwner($data);
    }

    /**
     * Exactly one of user_id, org_id and `default` is set.
     *
     * Enforced here rather than by a constraint because MySQL cannot express
     * "exactly one of three is non-null" portably. An ambiguous triple would
     * make resolveFor()'s ordering meaningless.
     *
     * @param array $data
     * @return bool
     */
    private function __validateOwnership(array $data)
    {
        $columns = array('user_id', 'org_id', 'default');
        $touched = array();
        foreach ($columns as $column) {
            if (array_key_exists($column, $data)) {
                $touched[$column] = $data[$column];
            }
        }

        /*
         * An update is validated against the row as it will be, not against
         * the fields this save happens to carry. A save that names none of
         * the three is not changing ownership and must pass; one that names
         * some is checked merged with what is stored. Without this, every
         * partial update of an existing profile fails — which is how the
         * shipped default's own update path was silently doing nothing.
         */
        if (!empty($data['id'])) {
            if (empty($touched)) {
                return true;
            }
            $stored = $this->find('first', array(
                'conditions' => array('AnalystProfile.id' => $data['id']),
                'fields' => array(
                    'AnalystProfile.user_id',
                    'AnalystProfile.org_id',
                    'AnalystProfile.default',
                ),
                'recursive' => -1,
            ));
            if (!empty($stored)) {
                $touched = array_merge($stored['AnalystProfile'], $touched);
            }
        }

        $scopes = 0;
        foreach ($columns as $column) {
            if (!empty($touched[$column])) {
                $scopes++;
            }
        }
        if ($scopes !== 1) {
            $this->invalidate(
                'user_id',
                __('A profile is owned by exactly one of a user, an'
                    . ' organisation, or the instance.')
            );
            return false;
        }
        return true;
    }

    /**
     * A user, and an organisation, holds at most one enabled profile.
     *
     * resolveFor() takes the nearest owner and would otherwise have two
     * equally near rows to choose between. A partial unique index would say
     * this in the schema but is not portable.
     *
     * @param array $data
     * @return bool
     */
    private function __validateOneEnabledPerOwner(array $data)
    {
        if (empty($data['enabled'])) {
            return true;
        }
        $owner = array();
        if (!empty($data['user_id'])) {
            $owner['AnalystProfile.user_id'] = $data['user_id'];
        } elseif (!empty($data['org_id'])) {
            $owner['AnalystProfile.org_id'] = $data['org_id'];
        } else {
            return true;
        }
        $owner['AnalystProfile.enabled'] = 1;
        if (!empty($data['id'])) {
            $owner['AnalystProfile.id !='] = $data['id'];
        }
        $existing = $this->find('first', array(
            'conditions' => $owner,
            'fields' => array('AnalystProfile.id'),
            'recursive' => -1,
        ));
        if (!empty($existing)) {
            $this->invalidate(
                'enabled',
                __(
                    'This owner already holds an enabled profile (#%s).',
                    $existing['AnalystProfile']['id']
                )
            );
            return false;
        }
        return true;
    }

    public function beforeSave($options = array())
    {
        $data = &$this->data[$this->alias];
        $now = date('Y-m-d H:i:s');
        if (empty($data['id'])) {
            $data['created'] = $now;
        }
        $data['modified'] = $now;
        return true;
    }

    /**
     * The one function every reader calls: the profile in force for a viewer.
     *
     * Nearest owner wins (D3) — the viewer's own, else their organisation's,
     * else the instance default. One statement rather than a fallback chain
     * of three.
     *
     * **Returns null when nothing matches**, which happens when a site admin
     * has disabled the instance default and the viewer owns no profile. That
     * is the honest reading of "verdict scoring is switched off here", not an
     * error: 01-profile.md §5.3 has the page render no score and name no
     * profile. Callers must handle null.
     *
     * @param array $user
     * @return array|null The AnalystProfile row, unwrapped
     */
    public function resolveFor(array $user)
    {
        $userId = isset($user['id']) ? $user['id'] : null;
        $orgId = isset($user['org_id']) ? $user['org_id'] : null;
        $cacheKey = $userId . ':' . $orgId;
        if (array_key_exists($cacheKey, $this->resolutionCache)) {
            return $this->resolutionCache[$cacheKey];
        }

        $ownership = array();
        if ($userId !== null) {
            $ownership[] = array('AnalystProfile.user_id' => $userId);
        }
        if ($orgId !== null) {
            $ownership[] = array('AnalystProfile.org_id' => $orgId);
        }
        $ownership[] = array('AnalystProfile.default' => 1);

        /*
         * At most three rows can match — one per scope, because
         * __validateOneEnabledPerOwner() caps each owner at one enabled
         * profile — so the nearest is picked from the result rather than in
         * the ORDER BY.
         *
         * The specification wrote this as one statement ordered by
         * `(user_id IS NOT NULL) DESC, (org_id IS NOT NULL) DESC`. It is
         * still one statement; only the tie-break moved, because CakePHP 2
         * quotes identifiers inside an `order` string and an expression
         * there is a quoting hazard rather than a readable one. What §3.1
         * actually forbids — three sequential queries walking the scopes —
         * is preserved.
         */
        $candidates = $this->find('all', array(
            'conditions' => array(
                'AnalystProfile.enabled' => 1,
                'OR' => $ownership,
            ),
            'recursive' => -1,
            'limit' => 3,
        ));

        $resolved = null;
        $bestRank = -1;
        foreach ($candidates as $candidate) {
            $row = $candidate['AnalystProfile'];
            if ($userId !== null && $row['user_id'] == $userId) {
                $rank = 2;
            } elseif ($orgId !== null && $row['org_id'] == $orgId) {
                $rank = 1;
            } elseif (!empty($row['default'])) {
                $rank = 0;
            } else {
                continue;
            }
            if ($rank > $bestRank) {
                $bestRank = $rank;
                $resolved = $row;
            }
        }
        $this->resolutionCache[$cacheKey] = $resolved;
        return $resolved;
    }

    /**
     * The profiles a viewer may see: their own, their organisation's, and the
     * instance default. A site admin sees every profile on the instance.
     *
     * @param array $user
     * @param array $filters
     * @return array
     */
    public function fetchProfiles(array $user, array $filters = array())
    {
        $conditions = array();
        if (empty($user['Role']['perm_site_admin'])) {
            $conditions['OR'] = array(
                array('AnalystProfile.user_id' => $user['id']),
                array('AnalystProfile.org_id' => $user['org_id']),
                array('AnalystProfile.default' => 1),
            );
        }
        if (isset($filters['enabled'])) {
            $conditions['AnalystProfile.enabled'] = $filters['enabled'];
        }
        return $this->find('all', array(
            'conditions' => $conditions,
            'recursive' => -1,
            'order' => array(
                'AnalystProfile.default DESC',
                'AnalystProfile.name ASC',
            ),
        ));
    }

    /**
     * @param array $user
     * @param int|string $id id or uuid
     * @return array|null
     */
    public function fetchProfile(array $user, $id)
    {
        $conditions = Validation::uuid($id)
            ? array('AnalystProfile.uuid' => $id)
            : array('AnalystProfile.id' => $id);
        $profile = $this->find('first', array(
            'conditions' => $conditions,
            'recursive' => -1,
        ));
        if (empty($profile)) {
            return null;
        }
        if (!$this->isReadableByCurrentUser($user, $profile)) {
            return null;
        }
        return $profile;
    }

    /**
     * @param array $user
     * @param array $profile
     * @return bool
     */
    public function isReadableByCurrentUser(array $user, array $profile)
    {
        if (!empty($user['Role']['perm_site_admin'])) {
            return true;
        }
        $row = isset($profile['AnalystProfile'])
            ? $profile['AnalystProfile']
            : $profile;
        if (!empty($row['default'])) {
            return true;
        }
        if (!empty($row['user_id']) && $row['user_id'] == $user['id']) {
            return true;
        }
        return !empty($row['org_id']) && $row['org_id'] == $user['org_id'];
    }

    /**
     * Who may edit a profile.
     *
     * Mirrors DecayingModel::isEditableByCurrentUser() and answers Q7 the way
     * 02-store.md §3.3 recommended: **no new permission flag.** A user
     * profile changes only its owner's own page, so it needs no grant — the
     * same reasoning that leaves user_settings ungated. An organisation
     * profile changes what colleagues read, so it is an org-admin action.
     * The shipped default stays site-admin only.
     *
     * Chosen partly because it is the reversible direction: adding a
     * perm_analyst_profile flag later is additive, whereas shipping one and
     * withdrawing it is a migration and a role-seed change.
     *
     * @param array $user
     * @param array $profile
     * @return bool
     */
    public function isEditableByCurrentUser(array $user, array $profile)
    {
        if (!empty($user['Role']['perm_site_admin'])) {
            return true;
        }
        $row = isset($profile['AnalystProfile'])
            ? $profile['AnalystProfile']
            : $profile;
        if (!empty($row['default'])) {
            return false;
        }
        if (!empty($row['user_id'])) {
            return $row['user_id'] == $user['id'];
        }
        if (!empty($row['org_id'])) {
            return $row['org_id'] == $user['org_id']
                && !empty($user['Role']['perm_admin']);
        }
        return false;
    }

    /**
     * Copy a profile into one the caller owns.
     *
     * A fork carries no lineage (D5): new uuid, counters reset, no
     * parent_uuid. Forking exists so an analyst can get started, and every
     * reference map in a profile is an override set — so a fork that
     * overrides nothing keeps tracking whatever the underlying source says.
     *
     * @param array $user
     * @param int|string $id The source profile
     * @param string|null $name
     * @param bool $forOrg Fork to the caller's organisation rather than to
     *                     the caller. Requires perm_admin.
     * @return array|null The saved fork, or null with validationErrors set
     */
    public function forkProfile(array $user, $id, $name = null, $forOrg = false)
    {
        $source = $this->fetchProfile($user, $id);
        if (empty($source)) {
            return null;
        }
        if ($forOrg && empty($user['Role']['perm_admin'])
            && empty($user['Role']['perm_site_admin'])) {
            $this->invalidate(
                'org_id',
                __('Forking to the organisation requires an org admin.')
            );
            return null;
        }
        $fork = array(
            'uuid' => CakeText::uuid(),
            'name' => $name !== null
                ? $name
                : sprintf('%s (copy)', $source['AnalystProfile']['name']),
            'description' => $source['AnalystProfile']['description'],
            'parameters' => $source['AnalystProfile']['parameters'],
            'user_id' => $forOrg ? null : $user['id'],
            'org_id' => $forOrg ? $user['org_id'] : null,
            'default' => 0,
            'enabled' => 1,
            'version' => 1,
            'revision' => 1,
        );
        $this->create();
        if (!$this->save(array('AnalystProfile' => $fork))) {
            return null;
        }
        $this->resolutionCache = array();
        return $this->find('first', array(
            'conditions' => array('AnalystProfile.id' => $this->id),
            'recursive' => -1,
        ));
    }

    /**
     * The name `Server::updateJSON()` calls on every model that loads
     * shipped JSON into a table — Galaxy, Noticelist, Warninglist, Taxonomy,
     * ObjectTemplate, ObjectRelationship, and now this one.
     *
     * @param bool $force
     * @return array
     */
    public function update($force = false)
    {
        return $this->updateDefaults($force);
    }

    /**
     * Load the shipped default profiles from app/files/analyst-profiles/.
     *
     * Follows DecayingModel::update() with its version-comparison bug fixed:
     * `version` is an int column here, so `10 > 9` rather than
     * `'1.10' > '1.9'` being false.
     *
     * Three things it must not do, each a rule rather than an omission: it
     * never touches a fork, because a fork has its own uuid and this loop
     * only knows the shipped ones; it never re-enables a disabled default,
     * because disabling it is how an admin switches assessment scoring off;
     * and it never deletes a default whose file has gone, because an org may
     * have forked from it and the page may still name it.
     *
     * @param bool $force Overwrite regardless of version
     * @return array Per-uuid outcome: created|updated|skipped|overwrote_edits
     */
    public function updateDefaults($force = false)
    {
        $shipped = $this->__loadShippedProfiles();
        if (empty($shipped)) {
            return array();
        }
        $existing = array();
        $rows = $this->find('all', array(
            'conditions' => array('AnalystProfile.default' => 1),
            'recursive' => -1,
        ));
        foreach ($rows as $row) {
            $existing[$row['AnalystProfile']['uuid']] = $row['AnalystProfile'];
        }

        $outcome = array();
        foreach ($shipped as $profile) {
            $uuid = $profile['uuid'];
            if (!isset($existing[$uuid])) {
                $this->create();
                $profile['default'] = 1;
                $profile['enabled'] = 1;
                $profile['revision'] = 1;
                $this->save(array('AnalystProfile' => $profile));
                $outcome[$uuid] = 'created';
                continue;
            }
            $current = $existing[$uuid];
            $shippedVersion = (int)$profile['version'];
            if (!$force && $shippedVersion <= (int)$current['version']) {
                $outcome[$uuid] = 'skipped';
                continue;
            }
            /*
             * A revision above 1 means the default was edited locally, and
             * this overwrite discards those edits. That is the rule — the
             * default tracks upstream and fork is the path to durable
             * divergence — but it is named rather than absorbed silently, so
             * the caller can log it.
             */
            $hadLocalEdits = (int)$current['revision'] > 1;
            /*
             * No create() here, deliberately. Model::create() seeds
             * $this->data from the column defaults, so `default` arrives as
             * 0 and, merged over the stored row, un-owns the profile that is
             * being updated. create() is for inserts; an update carries its
             * id and nothing else.
             */
            $this->id = $current['id'];
            $saved = $this->save(array('AnalystProfile' => array(
                'id' => $current['id'],
                'uuid' => $uuid,
                'name' => $profile['name'],
                'description' => $profile['description'],
                'parameters' => $profile['parameters'],
                'version' => $profile['version'],
                'revision' => (int)$current['revision'] + 1,
            )));
            if (empty($saved)) {
                /*
                 * Reported rather than swallowed. This branch returned
                 * 'updated' unconditionally until the live probe found the
                 * save failing validation and the caller believing it.
                 */
                $this->log(sprintf(
                    'AnalystProfile: could not update shipped default %s: %s',
                    $uuid,
                    json_encode($this->validationErrors)
                ));
                $outcome[$uuid] = 'failed';
                continue;
            }
            $outcome[$uuid] = $hadLocalEdits ? 'overwrote_edits' : 'updated';
        }
        $this->resolutionCache = array();
        return $outcome;
    }

    /**
     * @return array
     */
    private function __loadShippedProfiles()
    {
        if (!is_dir(self::DEFAULT_PROFILE_PATH)) {
            return array();
        }
        $dir = new Folder(self::DEFAULT_PROFILE_PATH);
        $files = $dir->find('.*\.json', true);
        $profiles = array();
        foreach ($files as $filename) {
            $file = new File(self::DEFAULT_PROFILE_PATH . $filename);
            $decoded = json_decode($file->read(), true);
            $file->close();
            if (empty($decoded['uuid']) || empty($decoded['name'])
                || !isset($decoded['parameters'])) {
                $this->log(sprintf(
                    'AnalystProfile: shipped profile %s is missing uuid,'
                        . ' name or parameters.',
                    $filename
                ));
                continue;
            }
            $profiles[] = array(
                'uuid' => $decoded['uuid'],
                'name' => $decoded['name'],
                'description' => isset($decoded['description'])
                    ? $decoded['description']
                    : null,
                'parameters' => json_encode($decoded['parameters']),
                'version' => isset($decoded['version'])
                    ? (int)$decoded['version']
                    : 1,
            );
        }
        return $profiles;
    }

    /**
     * Phase 1 asks only whether the document parses into an object. Each
     * section's contract belongs to the phase that owns it — signals to
     * phase 2, relevance to phase 5, and so on.
     *
     * @param string|array $parameters
     * @return bool
     */
    public function validateParameters($parameters)
    {
        if (is_array($parameters)) {
            return true;
        }
        if ($parameters === null || $parameters === '') {
            return true;
        }
        $decoded = json_decode($parameters, true);
        return is_array($decoded);
    }

    /**
     * `revision` is the local edit counter and `version` the upstream match
     * key, and the two are separate columns on purpose: a site admin editing
     * the default moves `revision` while `version` keeps tracking the shipped
     * file, so a shipped update still applies and the local edit is still
     * visible. Phase 10's materialised assessments key on `revision`.
     *
     * Renaming a profile does not move it. Only `parameters` does.
     *
     * @param int|string $id
     * @return bool
     */
    public function bumpRevision($id)
    {
        $profile = $this->find('first', array(
            'conditions' => array('AnalystProfile.id' => $id),
            'fields' => array('AnalystProfile.id', 'AnalystProfile.revision'),
            'recursive' => -1,
        ));
        if (empty($profile)) {
            return false;
        }
        $this->id = $id;
        return (bool)$this->saveField(
            'revision',
            (int)$profile['AnalystProfile']['revision'] + 1
        );
    }
}
