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
    );

    public $hasMany = array(
        'GalaxyCluster' => array('dependent' => true)
    );

    public function beforeValidate($options = array())
    {
        parent::beforeValidate();
        if (isset($this->data['Galaxy']['kill_chain_order'])) {
            $json = json_encode($this->data['Galaxy']['kill_chain_order']);
            if ($json !== null) {
                $this->data['Galaxy']['kill_chain_order'] = $json;
            } else {
                unset($this->data['Galaxy']['kill_chain_order']);
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
            $template = array(
                'source' => isset($cluster_package['source']) ? $cluster_package['source'] : '',
                'authors' => json_encode(isset($cluster_package['authors']) ? $cluster_package['authors'] : array(), true),
                'collection_uuid' => isset($cluster_package['uuid']) ? $cluster_package['uuid'] : '',
                'galaxy_id' => $galaxies[$cluster_package['type']],
                'type' => $cluster_package['type'],
                'tag_name' => 'misp-galaxy:' . $cluster_package['type'] . '="'
            );
            $elements = array();
            $temp = $this->GalaxyCluster->find('all', array(
                'conditions' => array(
                    'GalaxyCluster.galaxy_id' => $galaxies[$cluster_package['type']]
                ),
                'recursive' => -1,
                'fields' => array('version', 'id', 'value', 'uuid')
            ));
            $existingClusters = array();
            foreach ($temp as $k => $v) {
                $existingClusters[$v['GalaxyCluster']['value']] = $v;
            }
            $clusters_to_delete = array();

            // Delete all existing outdated clusters
            foreach ($cluster_package['values'] as $k => $cluster) {
                if (empty($cluster['value'])) {
                    continue;
                }
                if (isset($cluster['version'])) {
                } elseif (!empty($cluster_package['version'])) {
                    $cluster_package['values'][$k]['version'] = $cluster_package['version'];
                } else {
                    $cluster_package['values'][$k]['version'] = 0;
                }
                if (!empty($existingClusters[$cluster['value']])) {
                    if ($force || $existingClusters[$cluster['value']]['GalaxyCluster']['version'] < $cluster_package['values'][$k]['version']) {
                        $clusters_to_delete[] = $existingClusters[$cluster['value']]['GalaxyCluster']['id'];
                    } else {
                        unset($cluster_package['values'][$k]);
                    }
                }
            }
            if (!empty($clusters_to_delete)) {
                $this->GalaxyCluster->GalaxyElement->deleteAll(array('GalaxyElement.galaxy_cluster_id' => $clusters_to_delete), false, false);
                $this->GalaxyCluster->delete($clusters_to_delete, false, false);
            }

            // create all clusters
            foreach ($cluster_package['values'] as $cluster) {
                if (empty($cluster['version'])) {
                    $cluster['version'] = 1;
                }
                $template['version'] = $cluster['version'];
                $this->GalaxyCluster->create();
                $cluster_to_save = $template;
                if (isset($cluster['description'])) {
                    $cluster_to_save['description'] = $cluster['description'];
                    unset($cluster['description']);
                }
                $cluster_to_save['value'] = $cluster['value'];
                $cluster_to_save['tag_name'] = $cluster_to_save['tag_name'] . $cluster['value'] . '"';
                if (!empty($cluster['uuid'])) {
                    $cluster_to_save['uuid'] = $cluster['uuid'];
                }
                unset($cluster['value']);
                if (empty($cluster_to_save['description'])) {
                    $cluster_to_save['description'] = '';
                }
                $result = $this->GalaxyCluster->save($cluster_to_save, false);
                $galaxyClusterId = $this->GalaxyCluster->id;
                if (isset($cluster['meta'])) {
                    foreach ($cluster['meta'] as $key => $value) {
                        if (is_array($value)) {
                            foreach ($value as $v) {
                                $elements[] = array(
                                    $galaxyClusterId,
                                    $key,
                                    strval($v)
                                );
                            }
                        } else {
                            $elements[] = array(
                                $this->GalaxyCluster->id,
                                $key,
                                strval($value)
                            );
                        }
                    }
                }
            }
            $db = $this->getDataSource();
            $fields = array('galaxy_cluster_id', 'key', 'value');
            if (!empty($elements)) {
                $db->insertMulti('galaxy_elements', $fields, $elements);
            }
        }
        return true;
    }

    public function verifyDatabaseAndJSONSynchronisation()
    {
        $diagnostic = array();
        $dir = new Folder(APP . 'files' . DS . 'misp-galaxy' . DS . 'galaxies');
        $files = $dir->find('.*\.json');
        $diskGalaxies = array();
        foreach ($files as $file) {
            $file = new File($dir->pwd() . DS . $file);
            $galaxy = json_decode($file->read(), true);
            $file->close();
            if (!is_null($galaxy)) {
                $connector = $galaxy['type'];
                // $connector = $galaxy['uuid'];
                $diskGalaxies[$connector] = $galaxy;
            }
        }
        $dir = new Folder(APP . 'files' . DS . 'misp-galaxy' . DS . 'clusters');
        $files = $dir->find('.*\.json');
        foreach ($files as $file) {
            $file = new File($dir->pwd() . DS . $file);
            $cluster = json_decode($file->read(), true);
            $file->close();
            $connector = $cluster['type'];
            // $connector = $cluster['uuid'];
            $diskGalaxies[$connector]['clusters'] = array();
            if (!is_null($cluster)) {
                $diskGalaxies[$connector]['clusters'] = $cluster;
            }
        }
        foreach ($diskGalaxies as $galaxyType => $galaxy) {
            // if ($galaxyType != 'threat-actor') {
            //     continue;
            // }
            $databaseGalaxy = $this->find('first', array(
            'recursive' => 2,
            'conditions' => array(
                'type' => $galaxyType
                )
            ));
            $diagnostic[$galaxyType] = $this->compareDiskAndDatabaseGalaxy($galaxy, $databaseGalaxy);
        }
        return $diagnostic;
    }

    private function compareDiskAndDatabaseGalaxy($diskGalaxy, $databaseGalaxy)
    {
        $comparisonResult = array();
        if (empty($databaseGalaxy)) {
            $comparisonResult['Galaxy']['_exist_'] = array(
                'disk' => $diskGalaxy['type'],
                'db' => ''
            );
            $comparisonResult['same'] = false;
            $comparisonResult['diagnosticMessage'] = sprintf(__('The Galaxy `%s` does not exists in the database despite the fact that it exists on the disk.'), $diskGalaxies['type']);
            return $comparisonResult;
        }

        $galaxyFields = array(
            'description' => array('default' => null, 'required' => true),
            'icon' => array('default' => null, 'required' => true),
            'name' => array('default' => false, 'required' => true),
            'namespace' => array('default' => null, 'required' => true),
            'type' => array('default' => null, 'required' => true),
            'uuid' => array('default' => null, 'required' => true),
            'version' => array('default' => null, 'required' => true),
        );
        $galaxyAtClusterFields = array(
            'description' => array('default' => null, 'required' => true),
            'type' => array('default' => null, 'required' => true),
            'version' => array('default' => null, 'required' => true),
            'name' => array('default' => false, 'required' => true),
            'uuid' => array('default' => null, 'required' => true),
            'source' => array('default' => null, 'required' => true),
            'category' => array('default' => null, 'required' => true),
            'authors' => array('default' => array(), 'required' => true),
        );
        $clusterFields = array(
            'description' => array('default' => null, 'required' => true),
            'value' => array('default' => null, 'required' => true),
            'uuid' => array('default' => '', 'required' => true),
            'related' => array('default' => null, 'required' => false),
        );
        $harmonizedDiskGalaxy = array(
            'Galaxy' => array(),
            'Clusters' => array()
        );
        $harmonizedDatabaseGalaxy = array(
            'Galaxy' => array(),
            'Clusters' => array()
        );
        // construct same format for disk taxonomy
        foreach ($galaxyFields as $field => $fieldConfig) {
            if (isset($diskGalaxy[$field])) {
                $diskValue = $diskGalaxy[$field];
            } else {
                $diskValue = $fieldConfig['default'];
            }
            $harmonizedDiskGalaxy['Galaxy'][$field] = $diskValue;
        }
        $galaxyAtCluster = $diskGalaxy['clusters'];
        $galaxyValue = $galaxyAtCluster['type'];
        foreach ($galaxyAtClusterFields as $field => $fieldConfig) {
            if (isset($galaxyAtCluster[$field])) {
                $diskValue = $galaxyAtCluster[$field];
            } else {
                $diskValue = $fieldConfig['default'];
            }
            $harmonizedDiskGalaxy['Galaxy']['GalaxyAtCluster'][$field] = $diskValue;
        }

        $clusters = $diskGalaxy['clusters']['values'];
        foreach($clusters as $cluster) {
            $clusterValue = $cluster['value'];
            foreach ($clusterFields as $field => $fieldConfig) {
                if (isset($cluster[$field])) {
                    $diskValue = $cluster[$field];
                } else {
                    $diskValue = $fieldConfig['default'];
                }
                $harmonizedDiskGalaxy['Clusters'][$clusterValue][$field] = $diskValue;
            }
            $harmonizedDiskGalaxy['Clusters'][$clusterValue]['GalaxyElements'] = array();
            if (isset($cluster['meta'])) {
                $elements = $cluster['meta'];
                $harmonizedDiskGalaxy['Clusters'][$clusterValue]['GalaxyElements'] = $elements;
            }
        }

        // construct same format for database taxonomy
        foreach ($galaxyFields as $field => $fieldConfig) {
            if (isset($databaseGalaxy['Galaxy'][$field])) {
                $databaseValue = $databaseGalaxy['Galaxy'][$field];
            } else {
                $databaseValue = $fieldConfig['default'];
            }
            $harmonizedDatabaseGalaxy['Galaxy'][$field] = $databaseValue;
        }
        $harmonizedDatabaseGalaxy['Galaxy']['GalaxyAtCluster'] = array();

        $clusters = $databaseGalaxy['GalaxyCluster'];
        foreach($clusters as $cluster) {
            $clusterValue = $cluster['value'];
            foreach ($clusterFields as $field => $fieldConfig) {
                if (isset($cluster[$field])) {
                    $databaseValue = $cluster[$field];
                } else {
                    $databaseValue = $fieldConfig['default'];
                }
                $harmonizedDatabaseGalaxy['Clusters'][$clusterValue][$field] = $databaseValue;
            }
            $harmonizedDatabaseGalaxy['Clusters'][$clusterValue]['GalaxyElements'] = array();
            if (isset($cluster['GalaxyElement'])) {
                foreach($cluster['GalaxyElement'] as $element) {
                    $harmonizedDatabaseGalaxy['Clusters'][$clusterValue]['GalaxyElements'][$element['key']][] = $element['value'];
                }
            }
        }
        
        // compare database and disk
        foreach ($galaxyFields as $field => $fieldConfig) {
            $diskValue = $harmonizedDiskGalaxy['Galaxy'][$field];
            $databaseValue = $harmonizedDatabaseGalaxy['Galaxy'][$field];
            if ($diskValue != $databaseValue) {
                $comparisonResult['Galaxy'][$field] = array(
                    'disk' => $diskValue,
                    'db' => $databaseValue
                );
                $comparisonResult['diagnosticMessage'][] = sprintf(__('Galaxy Value for field `%s` is different'), $field);
            }
        }
        foreach($harmonizedDiskGalaxy['Clusters'] as $clusterValue => $diskCluster) {
            if (!isset($harmonizedDatabaseGalaxy['Clusters'][$clusterValue])) {
                $comparisonResult['Clusters'][$clusterValue]['_exist_'] = array(
                    'disk' => $clusterValue,
                    'db' => null
                );
                $comparisonResult['diagnosticMessage'][] = sprintf(__('Cluster `%s` does not exists in the database despite that it exists on the disk'), $clusterValue);
            } else {
                foreach ($clusterFields as $field => $fieldConfig) {
                    $diskValue = $harmonizedDiskGalaxy['Clusters'][$clusterValue][$field];
                    $databaseValue = $harmonizedDatabaseGalaxy['Clusters'][$clusterValue][$field];
                    if ($diskValue != $databaseValue) {
                        if (!(!is_null($databaseValue) && is_null($diskValue))) {
                            if ($fieldConfig['required']) {
                                $comparisonResult['Clusters'][$clusterValue][$field]['_exist_'] = array(
                                    'disk' => count($diskValue) < 2 ? $diskValue : array(
                                        $diskValue[0],
                                        sprintf(__('%s more values'), count($diskValue)-1)
                                    ),
                                    'db' => $databaseValue
                                );
                                $comparisonResult['diagnosticMessage'][] = sprintf(__('Cluster\'s `%s`\'s value field `%s` does not exist'), $clusterValue, $field);
                            }
                        } else {
                            $comparisonResult['Clusters'][$clusterValue][$field] = array(
                                'disk' => $diskValue,
                                'db' => $databaseValue
                            );
                            $comparisonResult['diagnosticMessage'][] = sprintf(__('Cluster\'s `%s`\'s value field `%s` is different'), $clusterValue, $field);
                        }
                    }
                }
            }

            foreach($diskCluster['GalaxyElements'] as $elementValue => $diskElement) {
                if (!isset($harmonizedDatabaseGalaxy['Clusters'][$clusterValue]['GalaxyElements'][$elementValue])) {
                    if (!empty($diskElement)) {
                        $comparisonResult['Clusters'][$clusterValue]['GalaxyElement'][$elementValue]['_exist_'] = array(
                            'disk' => $diskElement,
                            'db' => null
                        );
                        $comparisonResult['diagnosticMessage'][] = sprintf(__('[%s] GalaxyElement `%s` does not exists in the database despite that it exists on the disk'), $clusterValue, $elementValue);
                    }
                } else {
                    $diskValue = $harmonizedDiskGalaxy['Clusters'][$clusterValue]['GalaxyElements'][$elementValue];
                    $databaseValue = $harmonizedDatabaseGalaxy['Clusters'][$clusterValue]['GalaxyElements'][$elementValue];
                    if (
                        $diskValue != $databaseValue &&
                        !(count($databaseValue) == 1 && $databaseValue[0] == $diskValue)
                    ) {
                        $comparisonResult['Clusters'][$clusterValue]['GalaxyElement'][$elementValue] = array(
                            'disk' => $diskValue,
                            'db' => $databaseValue
                        );
                        $comparisonResult['diagnosticMessage'][] = sprintf(__('Clusters `%s`\'s GalaxyElement value `%s` is different'), $clusterValue, $elementValue);
                    }
                }
            }
        }
        $comparisonResult['same'] = empty($comparisonResult['Galaxy']) && empty($comparisonResult['Clusters']);
        return $comparisonResult;
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

    public function getMitreAttackGalaxyId($type="mitre-attack-pattern", $namespace="mitre-attack")
    {
        $galaxy = $this->find('first', array(
                'recursive' => -1,
                'fields' => 'id',
                'conditions' => array('Galaxy.type' => $type, 'Galaxy.namespace' => $namespace),
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
