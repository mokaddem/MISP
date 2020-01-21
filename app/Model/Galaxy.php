<?php
App::uses('AppModel', 'Model');
class Galaxy extends AppModel
{
    public $useTable = 'galaxies';

    public $recursive = -1;

    public $actsAs = array(
            'Containable',
    );

    public $validate = array(
        'description' => array(
            'stringNotEmpty' => array(
                'rule' => array('stringNotEmpty')
            )
        ),
        'name' => array(
            'stringNotEmpty' => array(
                'rule' => array('stringNotEmpty')
            )
        ),
        'namespace' => array(
            'stringNotEmpty' => array(
                'rule' => array('stringNotEmpty')
            )
        ),
        'to_ids' => array(
            'boolean' => array(
                'rule' => array('boolean'),
                'required' => false
            )
        ),
        'uuid' => array(
            'uuid' => array(
                'rule' => array('custom', '/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$/'),
                'message' => 'Please provide a valid UUID'
            ),
            'unique' => array(
                'rule' => 'isUnique',
                'message' => 'The UUID provided is not unique',
                'required' => 'create'
            )
        ),
        'distribution' => array(
                'rule' => array('inList', array('0', '1', '2', '3', '4', '5')),
                'message' => 'Options: Your organisation only, This community only, Connected communities, All communities, Sharing group, Inherit event',
                'required' => true
        ),
        'kill_chain_order' => array(
            'valueIsJson' => array(
                'rule' => array('valueIsJsonOrNull')
            )
        )
    );
    public $hasMany = array(
        'GalaxyCluster' => array('dependent' => true)
    );

    public $belongsTo = array(
        'Org' => array(
            'className' => 'Organisation',
            'foreignKey' => 'org_id'
        ),
        'Orgc' => array(
            'className' => 'Organisation',
            'foreignKey' => 'orgc_id'
        )
    );

    public function beforeValidate($options = array())
    {
        parent::beforeValidate();
        if (isset($this->data['Galaxy']['kill_chain_order'])) {
            if (empty($this->data['Galaxy']['kill_chain_order'])) {
                $this->data['Galaxy']['kill_chain_order'] = NULL;
            } else {
                if (is_array($this->data['Galaxy']['kill_chain_order'])) {
                    $json = json_encode($this->data['Galaxy']['kill_chain_order']);
                    if ($json !== null) {
                        $this->data['Galaxy']['kill_chain_order'] = $json;
                    } else {
                        $this->data['Galaxy']['kill_chain_order'] = NULL;
                    }
                } else {
                    $json = json_decode($this->data['Galaxy']['kill_chain_order'], true);
                    if ($json !== null && !empty($json)) {
                        $this->data['Galaxy']['kill_chain_order'] = $json;
                    } else {
                        $this->data['Galaxy']['kill_chain_order'] = NULL;
                    }
                }
            }
        }
        return true;
    }

    public function beforeDelete($cascade = true)
    {
        $this->GalaxyCluster->deleteAll(array('GalaxyCluster.galaxy_id' => $this->id));
    }

    public function afterFind($results, $primary = false)
    {
        foreach ($results as $k => $v) {
            if (isset($v['Galaxy']['kill_chain_order']) && $v['Galaxy']['kill_chain_order'] !== '') {
                $results[$k]['Galaxy']['kill_chain_order'] = json_decode($v['Galaxy']['kill_chain_order'], true);
            } else {
                unset($results[$k]['Galaxy']['kill_chain_order']);
            }
        }
        return $results;
    }

    private function __load_galaxies($force = false)
    {
        $dir = new Folder(APP . 'files' . DS . 'misp-galaxy' . DS . 'galaxies');
        $files = $dir->find('.*\.json');
        $galaxies = array();
        foreach ($files as $file) {
            $file = new File($dir->pwd() . DS . $file);
            $galaxies[] = json_decode($file->read(), true);
            $file->close();
        }
        $galaxyTypes = array();
        foreach ($galaxies as $i => $galaxy) {
            $galaxyTypes[$galaxy['type']] = $galaxy['type'];
        }
        $temp = $this->find('all', array(
            'fields' => array('uuid', 'version', 'id', 'icon'),
            'recursive' => -1
        ));
        $existingGalaxies = array();
        foreach ($temp as $k => $v) {
            $existingGalaxies[$v['Galaxy']['uuid']] = $v['Galaxy'];
        }
        foreach ($galaxies as $k => $galaxy) {
            $galaxy['default'] = true;
            $galaxy['distribution'] = 3;
            if (isset($existingGalaxies[$galaxy['uuid']])) {
                if (
                    $force ||
                    $existingGalaxies[$galaxy['uuid']]['version'] < $galaxy['version'] ||
                    (!empty($galaxy['icon']) && ($existingGalaxies[$galaxy['uuid']]['icon'] != $galaxy['icon']))
                ) {
                    $galaxy['id'] = $existingGalaxies[$galaxy['uuid']]['id'];
                    $this->save($galaxy);
                }
            } else {
                $this->create();
                $this->save($galaxy);
            }
        }
        return $this->find('list', array('recursive' => -1, 'fields' => array('type', 'id')));
    }

    public function update($force = false)
    {
        $galaxies = $this->__load_galaxies($force);
        $dir = new Folder(APP . 'files' . DS . 'misp-galaxy' . DS . 'clusters');
        $files = $dir->find('.*\.json');
        $cluster_packages = array();
        foreach ($files as $file) {
            $file = new File($dir->pwd() . DS . $file);
            $cluster_package = json_decode($file->read(), true);
            $file->close();
            if (!isset($galaxies[$cluster_package['type']])) {
                continue;
            }
            $this->GalaxyCluster->update($galaxies[$cluster_package['type']], $cluster_package, $force);
        }
        return true;
    }

    public function attachCluster($user, $target_type, $target_id, $cluster_id, $local = false)
    {
        $connectorModel = Inflector::camelize($target_type) . 'Tag';
        if ($local == 1 || $local === true) {
            $local = 1;
        } else {
            $local = 0;
        }
        $cluster = $this->GalaxyCluster->find('first', array('recursive' => -1, 'conditions' => array('id' => $cluster_id), 'fields' => array('tag_name', 'id', 'value')));
        $this->Tag = ClassRegistry::init('Tag');
        if ($target_type === 'event') {
            $target = $this->Tag->EventTag->Event->fetchEvent($user, array('eventid' => $target_id, 'metadata' => 1));
        } elseif ($target_type === 'attribute') {
            $target = $this->Tag->AttributeTag->Attribute->fetchAttributes($user, array('conditions' => array('Attribute.id' => $target_id), 'flatten' => 1));
        } elseif ($target_type === 'tag_collection') {
            $target = $this->Tag->TagCollectionTag->TagCollection->fetchTagCollection($user, array('conditions' => array('TagCollection.id' => $target_id)));
        }
        if (empty($target)) {
            throw new NotFoundException(__('Invalid %s.', $target_type));
        }
        $target = $target[0];
        $tag_id = $this->Tag->captureTag(array('name' => $cluster['GalaxyCluster']['tag_name'], 'colour' => '#0088cc', 'exportable' => 1), $user);
        $existingTag = $this->Tag->$connectorModel->find('first', array('conditions' => array($target_type . '_id' => $target_id, 'tag_id' => $tag_id)));
        if (!empty($existingTag)) {
            return 'Cluster already attached.';
        }
        $this->Tag->$connectorModel->create();
        $toSave = array($target_type . '_id' => $target_id, 'tag_id' => $tag_id, 'local' => $local);
        if ($target_type === 'attribute') {
            $event = $this->Tag->EventTag->Event->find('first', array(
                'conditions' => array(
                    'Event.id' => $target['Attribute']['event_id']
                ),
                'recursive' => -1
            ));
            $toSave['event_id'] = $target['Attribute']['event_id'];
        }
        $result = $this->Tag->$connectorModel->save($toSave);
        if ($result) {
            if ($target_type !== 'tag_collection') {
                if ($target_type === 'event') {
                    $event = $target;
                }
                $this->Tag->EventTag->Event->insertLock($user, $event['Event']['id']);
                $event['Event']['published'] = 0;
                $date = new DateTime();
                $event['Event']['timestamp'] = $date->getTimestamp();
                $this->Tag->EventTag->Event->save($event);
            }
            $this->Log = ClassRegistry::init('Log');
            $this->Log->create();
            $this->Log->save(array(
                'org' => $user['Organisation']['name'],
                'model' => ucfirst($target_type),
                'model_id' => $target_id,
                'email' => $user['email'],
                'action' => 'galaxy',
                'title' => 'Attached ' . $cluster['GalaxyCluster']['value'] . ' (' . $cluster['GalaxyCluster']['id'] . ') to ' . $target_type . ' (' . $target_id . ')',
                'change' => ''
            ));
            return 'Cluster attached.';
        }
        return 'Could not attach the cluster';
    }

    public function detachCluster($user, $target_type, $target_id, $cluster_id) {
        $cluster = $this->GalaxyCluster->find('first', array(
            'recursive' => -1,
            'conditions' => array('id' => $cluster_id),
            'fields' => array('tag_name', 'id', 'value')
        ));
        $this->Tag = ClassRegistry::init('Tag');
        if ($target_type === 'event') {
            $target = $this->Tag->EventTag->Event->fetchEvent($user, array('eventid' => $target_id, 'metadata' => 1));
            if (empty($target)) {
                throw new NotFoundException(__('Invalid %s.', $target_type));
            }
            $target = $target[0];
            $event = $target;
            $event_id = $target['Event']['id'];
            $org_id = $event['Event']['org_id'];
            $orgc_id = $event['Event']['orgc_id'];
        } elseif ($target_type === 'attribute') {
            $target = $this->Tag->AttributeTag->Attribute->fetchAttributes($user, array('conditions' => array('Attribute.id' => $target_id), 'flatten' => 1));
            if (empty($target)) {
                throw new NotFoundException(__('Invalid %s.', $target_type));
            }
            $target = $target[0];
            $event_id = $target['Attribute']['event_id'];
            $event = $this->Tag->EventTag->Event->fetchEvent($user, array('eventid' => $event_id, 'metadata' => 1));
            if (empty($event)) {
                throw new NotFoundException(__('Invalid event'));
            }
            $event = $event[0];
            $org_id = $event['Event']['org_id'];
            $orgc_id = $event['Event']['org_id'];
        } elseif ($target_type === 'tag_collection') {
            $target = $this->Tag->TagCollectionTag->TagCollection->fetchTagCollection($user, array('conditions' => array('TagCollection.id' => $target_id)));
            if (empty($target)) {
                throw new NotFoundException(__('Invalid %s.', $target_type));
            }
            $target = $target[0];
            $org_id = $target['org_id'];
            $orgc_id = $org_id;
        }

        if (!$user['Role']['perm_site_admin'] && !$user['Role']['perm_sync']) {
            if (
                ($scope === 'tag_collection' && !$user['Role']['perm_tag_editor']) ||
                ($scope !== 'tag_collection' && !$user['Role']['perm_tagger']) ||
                ($user['org_id'] !== $org_id && $user['org_id'] !== $orgc_id)
            ) {
                throw new MethodNotAllowedException('Invalid ' . Inflector::humanize($targe_type) . '.');
            }
        }

        $tag_id = $this->Tag->captureTag(array('name' => $cluster['GalaxyCluster']['tag_name'], 'colour' => '#0088cc', 'exportable' => 1), $user);

        if ($target_type == 'attribute') {
            $existingTargetTag = $this->Tag->AttributeTag->find('first', array(
                'conditions' => array('AttributeTag.tag_id' => $tag_id, 'AttributeTag.attribute_id' => $target_id),
                'recursive' => -1,
                'contain' => array('Tag')
            ));
        } elseif ($target_type == 'event') {
            $existingTargetTag = $this->Tag->EventTag->find('first', array(
                'conditions' => array('EventTag.tag_id' => $tag_id, 'EventTag.event_id' => $target_id),
                'recursive' => -1,
                'contain' => array('Tag')
            ));
        } elseif ($target_type == 'tag_collection') {
            $existingTargetTag = $this->Tag->TagCollectionTag->TagCollection->find('first', array(
                'conditions' => array('tag_id' => $tag_id, 'tag_collection_id' => $target_id),
                'recursive' => -1,
                'contain' => array('Tag')
            ));
        }

        if (empty($existingTargetTag)) {
            return 'Cluster not attached.';
        } else {
            if ($target_type == 'event') {
                $result = $this->Tag->EventTag->delete($existingTargetTag['EventTag']['id']);
            } elseif ($target_type == 'attribute') {
                $result = $this->Tag->AttributeTag->delete($existingTargetTag['AttributeTag']['id']);
            } elseif ($target_type == 'tag_collection') {
                $result = $this->Tag->TagCollectionTag->delete($existingTargetTag['TagCollectionTag']['id']);
            }

            if ($result) {
                if ($target_type !== 'tag_collection') {
                    $this->Tag->EventTag->Event->insertLock($user, $event['Event']['id']);
                    $event['Event']['published'] = 0;
                    $date = new DateTime();
                    $event['Event']['timestamp'] = $date->getTimestamp();
                    $this->Tag->EventTag->Event->save($event);
                }
                $this->Log = ClassRegistry::init('Log');
                $this->Log->create();
                $this->Log->save(array(
                    'org' => $user['Organisation']['name'],
                    'model' => ucfirst($target_type),
                    'model_id' => $target_id,
                    'email' => $user['email'],
                    'action' => 'galaxy',
                    'title' => 'Detached ' . $cluster['GalaxyCluster']['value'] . ' (' . $cluster['GalaxyCluster']['id'] . ') to ' . $target_type . ' (' . $target_id . ')',
                    'change' => ''
                ));
                return 'Cluster detached';
            } else {
                return 'Could not detach cluster';
            }
        }
    }

    public function saveGalaxy($user, $galaxy, $fromPull = false)
    {
        if (!$user['Role']['perm_galaxy_editor'] && !$user['Role']['perm_site_admin']) {
            return false;
        }
        unset($galaxy['Galaxy']['id']);
        if (isset($galaxy['Galaxy']['uuid'])) {
            // check if the uuid already exists
            $existingGalaxy = $this->find('first', array('conditions' => array('Galaxy.uuid' => $galaxy['Galaxy']['uuid'])));
            if ($existingGalaxy) {
                if ($fromPull && !$existingGalaxy['Galaxy']['default']) {
                    $errors = $this->editGalaxy($user, $galaxy, $fromPull);
                    return empty($errors);
                } else {
                    // Maybe redirect to the correct URL?
                }
                return false;
            }
        } else {
            $galaxy['Galaxy']['uuid'] = CakeText::uuid();
        }
        $type = $galaxy['Galaxy']['uuid'];
        $galaxy['Galaxy']['type'] = $type;
        if (!$fromPull) {
            $date = new DateTime();
            $galaxy['Galaxy']['version'] = $date->getTimestamp();
        }
        $galaxy['Galaxy']['org_id'] = $user['org_id'];
        if (!isset($galaxy['Galaxy']['orgc_id'])) {
            if (isset($galaxy['Orgc']['uuid'])) {
                $orgc_id = $this->Orgc->find('first', array('conditions' => array('Orgc.uuid' => $data['Orgc']['uuid']), 'fields' => array('Orgc.id'), 'recursive' => -1));
            } else {
                $orgc_id = $user['org_id'];
            }
            $galaxy['Galaxy']['orgc_id'] = $orgc_id;
        }
        $this->create();
        $saveSuccess = $this->save($galaxy);
        return $saveSuccess;
    }

    // Gets a galaxy then save it.
    public function captureGalaxy($user, $galaxy, $orgId, $fromPull = false)
    {
        $errors = array();
        if ($fromPull) {
            $galaxy['Galaxy']['org_id'] = $orgId;
        } else {
            $galaxy['Galaxy']['org_id'] = $user['Organisation']['id'];
        }
        if (!isset($galaxy['Galaxy']['orgc_id']) && !isset($galaxy['Orgc'])) {
            $galaxy['Galaxy']['orgc_id'] = $galaxy['Galaxy']['org_id'];
        } else {
            if (!isset($galaxy['Galaxy']['Orgc'])) {
                if (isset($galaxy['Galaxy']['orgc_id']) && $galaxy['Galaxy']['orgc_id'] != $user['org_id'] && !$user['Role']['perm_sync'] && !$user['Role']['perm_site_admin']) {
                    return array(__('Only sync user can create galaxy on behalf of other users'));
                }
            } else {
                if ($galaxy['Galaxy']['Orgc']['uuid'] != $user['Organisation']['uuid'] && !$user['Role']['perm_sync'] && !$user['Role']['perm_site_admin']) {
                    return array(__('Only sync user can create galaxy on behalf of other users'));
                }
            }
            if (isset($galaxy['Galaxy']['orgc_id']) && $galaxy['Galaxy']['orgc_id'] != $user['org_id'] && !$user['Role']['perm_sync'] && !$user['Role']['perm_site_admin']) {
                return array(__('Only sync user can create galaxy on behalf of other users'));
            }
        }
        if (!Configure::check('MISP.enableOrgBlacklisting') || Configure::read('MISP.enableOrgBlacklisting') !== false) {
            $this->OrgBlacklist = ClassRegistry::init('OrgBlacklist');
            if (!isset($galaxy['Galaxy']['Orgc']['uuid'])) {
                $orgc = $this->Orgc->find('first', array('conditions' => array('Orgc.id' => $galaxy['Galaxy']['orgc_id']), 'fields' => array('Orgc.uuid'), 'recursive' => -1));
            } else {
                $orgc = array('Orgc' => array('uuid' => $galaxy['Galaxy']['Orgc']['uuid']));
            }
            if ($this->OrgBlacklist->hasAny(array('OrgBlacklist.org_uuid' => $orgc['Orgc']['uuid']))) {
                return array(__('Organisation blacklisted'));
            }
        }
        $galaxy = $this->captureOrganisationAndSG($galaxy, $user);
        $saveSuccess = $this->saveGalaxy($user, $galaxy, true);
        if ($saveSuccess) {
            $savedGalaxy = $this->find('first', array(
                'conditions' => array('id' =>  $this->id),
                'recursive' => -1
            ));
            $savedGalaxy['Galaxy']['values'] = $galaxy['GalaxyCluster'];
            $saveSuccess = $this->GalaxyCluster->update($savedGalaxy['Galaxy']['id'], $savedGalaxy['Galaxy'], false, false);
            if(!$saveSuccess) {
                $errors[] = array(__('Error while saving clusters'));
            }
        } else {
            foreach($this->Galaxy->validationErrors as $validationError) {
                $errors[] = $validationError[0];
            }
        }
        return $errors;
    }

    public function editGalaxy($user, $galaxy, $fromPull = false)
    {
        $errors = array();
        if (!$user['Role']['perm_galaxy_editor'] && !$user['Role']['perm_site_admin']) {
            $errors[] = __('Incorrect permission');
        }
        if (isset($galaxy['Galaxy']['uuid'])) {
            $existingGalaxy = $this->find('first', array('conditions' => array('Galaxy.uuid' => $galaxy['Galaxy']['uuid'])));
        } else {
            $errors[] = __('UUID not provided');
        }
        if (empty($existingGalaxy)) {
            $errors[] = __('Unkown UUID');
        } else {
            // For users that are of the creating org of the galaxy, always allow the edit
            // For users that are sync users, only allow the edit if the event is locked
            if ($existingGalaxy['Galaxy']['orgc_id'] === $user['org_id']
            || ($user['Role']['perm_sync'] && $existingGalaxy['Galaxy']['locked']) || $user['Role']['perm_site_admin']) {
                if ($user['Role']['perm_sync']) {
                    if (isset($galaxy['Galaxy']['distribution']) && $galaxy['Galaxy']['distribution'] == 4 && !$this->SharingGroup->checkIfAuthorised($user, $galaxy['Galaxy']['sharing_group_id'])) {
                        $errors[] = array(__('Galaxy could not be saved: The sync user has to have access to the sharing group in order to be able to edit it.'));
                    }
                }
            } else {
                $errors[] = array(__('Galaxy could not be saved: The user used to edit the galaxy is not authorised to do so. This can be caused by the user not being of the same organisation as the original creator of the galaxy whilst also not being a site administrator.'));
            }
            $galaxy['Galaxy']['id'] = $existingGalaxy['Galaxy']['id'];

            if (empty($errors)) {
                $date = new DateTime();
                if (!$fromPull) {
                    $galaxy['Galaxy']['version'] = $date->getTimestamp();
                }
                $galaxy['Galaxy']['default'] = false;
                $fieldList = array('name', 'namespace', 'description', 'version', 'icon', 'kill_chain_order', 'distribution', 'sharing_group_id', 'default');
                $saveSuccess = $this->save($galaxy, array('fieldList' => $fieldList));
                // if (!$saveSuccess) {
                //     foreach($this->validationErrors as $validationError) {
                //         $errors[] = $validationError[0];
                //     }
                // } else {
                //     $savedGalaxy = $this->find('first', array(
                //         'conditions' => array('id' => $galaxy['Galaxy']['id']),
                //         'recursive' => -1
                //     ));
        
                //     $savedGalaxy['Galaxy']['values'] = $galaxy['Galaxy']['values'];
                //     $saveSuccess = $this->GalaxyCluster->update($savedGalaxy['Galaxy']['id'], $savedGalaxy['Galaxy'], true, false);
                //     if(!$saveSuccess) {
                //         $errors[] = __('Error while saving clusters');
                //     }
                // }
            }
        }
        return $errors;
    }

    private function captureOrganisationAndSG($galaxy, $user)
    {
        if (isset($galaxy['Galaxy']['distribution']) && $galaxy['Galaxy']['distribution'] == 4) {
            $galaxy['Galaxy'] = $this->__captureSGForElement($galaxy['Galaxy'], $user);
        }
        // first we want to see how the creator organisation is encoded
        // The options here are either by passing an organisation object along or simply passing a string along
        if (isset($galaxy['Orgc'])) {
            $galaxy['Galaxy']['orgc_id'] = $this->Orgc->captureOrg($galaxy['Orgc'], $user);
            unset($galaxy['Orgc']);
        } else {
            // Can't capture the Orgc, default to the current user
            $galaxy['Galaxy']['orgc_id'] = $user['org_id'];
        }
        return $galaxy;
    }

    public function buildConditions($user)
    {
        $this->Event = ClassRegistry::init('Event');
        $conditions = array();
        if (!$user['Role']['perm_site_admin']) {
            $sgids = $this->Event->cacheSgids($user, true);
            $conditions['AND']['OR'] = array(
                'Galaxy.org_id' => $user['org_id'],
                array(
                    'AND' => array(
                        'Galaxy.distribution >' => 0,
                        'Galaxy.distribution <' => 4
                    ),
                ),
                array(
                    'AND' => array(
                        'Galaxy.sharing_group_id' => $sgids,
                        'Galaxy.distribution' => 4
                    )
                )
            );
        }
        return $conditions;
    }

    // very flexible, it's basically a replacement for find, with the addition that it restricts access based on user
    // options:
    //     fields
    //     contain
    //     conditions
    //     order
    //     group
    public function fetchGalaxies($user, $options, $full=false)
    {
        $params = array(
            'conditions' => $this->buildConditions($user),
            'recursive' => -1
        );
        if ($full) {
            $params['contain'] = array('GalaxyCluster' => array('GalaxyElement'));
        }
        if (isset($options['fields'])) {
            $params['fields'] = $options['fields'];
        }
        if (isset($options['conditions'])) {
            $params['conditions']['AND'][] = $options['conditions'];
        }
        if (isset($options['group'])) {
            $params['group'] = empty($options['group']) ? $options['group'] : false;
        }
        $galaxies = $this->find('all', $params);
        foreach($galaxies as $k => $galaxy) {
            $galaxies[$k] = $this->Org->attachOrgs($galaxy, array('id', 'name', 'uuid', 'local'), 'Galaxy');
        }
        return $galaxies;
    }

    public function attachExtendByInfo($user, $galaxy)
    {
        $extensions = $this->fetchGalaxies($user, array('conditions' => array('extends_uuid' => $galaxy['Galaxy']['uuid'])));
        $galaxy['Galaxy']['extends_by'] = $extensions;
        return $galaxy;
    }

    public function attachExtendFromInfo($user, $galaxy)
    {
        $extensions = $this->fetchGalaxies($user, array('conditions' => array('uuid' => $galaxy['Galaxy']['extends_uuid'])));
        if (!empty($extensions)) {
            $galaxy['Galaxy']['extends_from'] = $extensions[0];
        } else {
            $galaxy['Galaxy']['extends_from'] = array();
        }
        return $galaxy;
    }

    public function __setDefaultGalaxies()
    {
        // TODO: read all files on disk an set the default flag to 1 for those
    }

    public function getMitreAttackGalaxyId($type="mitre-attack-pattern", $namespace="mitre-attack")
    {
        $galaxy = $this->find('first', array(
                'recursive' => -1,
                'fields' => array('MAX(Galaxy.version) as latest_version', 'id'),
                'conditions' => array(
                    'Galaxy.type' => $type,
                    'Galaxy.namespace' => $namespace
                ),
                'group' => array('name', 'id')
        ));
        return empty($galaxy) ? 0 : $galaxy['Galaxy']['id'];
    }

    public function getAllowedMatrixGalaxies()
    {
        $conditions = array(
            'NOT' => array(
                'kill_chain_order' => ''
            )
        );
        $galaxies = $this->find('all', array(
            'recursive' => -1,
            'conditions' => $conditions,
        ));
        return $galaxies;
    }

    public function getMatrix($galaxy_id, $scores=array())
    {
        $conditions = array('Galaxy.id' => $galaxy_id);
        $contains = array(
            'GalaxyCluster' => array('GalaxyElement'),
        );

        $galaxy = $this->find('first', array(
                'recursive' => -1,
                'contain' => $contains,
                'conditions' => $conditions,
        ));

        $mispUUID = Configure::read('MISP')['uuid'];

        if (!isset($galaxy['Galaxy']['kill_chain_order'])) {
            throw new Exception(__("Galaxy cannot be represented as a matrix"));

        }
        $matrixData = array(
            'killChain' => $galaxy['Galaxy']['kill_chain_order'],
            'tabs' => array(),
            'matrixTags' => array(),
            'instance-uuid' => $mispUUID,
            'galaxy' => $galaxy['Galaxy']
        );

        $clusters = $galaxy['GalaxyCluster'];
        $cols = array();

        foreach ($clusters as $cluster) {
            if (empty($cluster['GalaxyElement'])) {
                continue;
            }

            $toBeAdded = false;
            $clusterType = $cluster['type'];
            $galaxyElements = $cluster['GalaxyElement'];
            foreach ($galaxyElements as $element) {
                // add cluster if kill_chain is present
                if ($element['key'] == 'kill_chain') {
                    $kc = explode(":", $element['value']);
                    $galaxyType = $kc[0];
                    $kc = $kc[1];
                    $cols[$galaxyType][$kc][] = $cluster;
                    $toBeAdded = true;
                }
                if ($element['key'] == 'external_id') {
                    $cluster['external_id'] = $element['value'];
                }
                if ($toBeAdded) {
                    $matrixData['matrixTags'][$cluster['tag_name']] = 1;
                }
            }
        }
        $matrixData['tabs'] = $cols;

        $this->sortMatrixByScore($matrixData['tabs'], $scores);
        // #FIXME temporary fix: retrieve tag name of deprecated mitre galaxies (for the stats)
        if ($galaxy['Galaxy']['id'] == $this->getMitreAttackGalaxyId()) {
            $names = array('Enterprise Attack - Attack Pattern', 'Pre Attack - Attack Pattern', 'Mobile Attack - Attack Pattern');
            $tag_names = array();
            $gals = $this->find('all', array(
                    'recursive' => -1,
                    'contain' => array('GalaxyCluster.tag_name'),
                    'conditions' => array('Galaxy.name' => $names)
            ));
            foreach ($gals as $gal => $temp) {
                foreach ($temp['GalaxyCluster'] as $value) {
                    $matrixData['matrixTags'][$value['tag_name']] = 1;
                }
            }
        }
        // end FIXME

        $matrixData['matrixTags'] = array_keys($matrixData['matrixTags']);
        return $matrixData;
    }

    public function sortMatrixByScore(&$tabs, $scores)
    {
        foreach (array_keys($tabs) as $i) {
            foreach (array_keys($tabs[$i]) as $j) {
                // major ordering based on score, minor based on alphabetical
                usort($tabs[$i][$j], function ($a, $b) use($scores) {
                    if ($a['tag_name'] == $b['tag_name']) {
                        return 0;
                    }
                    if (isset($scores[$a['tag_name']]) && isset($scores[$b['tag_name']])) {
                        if ($scores[$a['tag_name']] < $scores[$b['tag_name']]) {
                            $ret = 1;
                        } else if ($scores[$a['tag_name']] == $scores[$b['tag_name']]) {
                            $ret = strcmp($a['value'], $b['value']);
                        } else {
                            $ret = -1;
                        }
                    } else if (isset($scores[$a['tag_name']])) {
                        $ret = -1;
                    } else if (isset($scores[$b['tag_name']])) {
                        $ret = 1;
                    } else { // none are set
                        $ret = strcmp($a['value'], $b['value']);
                    }
                    return $ret;
                });
            }
        }
    }
}
