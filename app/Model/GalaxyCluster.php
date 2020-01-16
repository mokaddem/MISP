<?php
App::uses('AppModel', 'Model');
class GalaxyCluster extends AppModel
{
    public $useTable = 'galaxy_clusters';

    public $recursive = -1;

    public $actsAs = array(
            'Containable',
    );

    public $validate = array(
    );

    public $belongsTo = array(
        'Galaxy' => array(
            'className' => 'Galaxy',
            'foreignKey' => 'galaxy_id',
        ),
        'Tag' => array(
            'foreignKey' => false,
            'conditions' => array('GalaxyCluster.tag_name = Tag.name')
        )
    );

    private $__clusterCache = array();

    public $hasMany = array(
        'GalaxyElement' => array('dependent' => true),
    //  'GalaxyReference'
    );

    public function beforeValidate($options = array())
    {
        parent::beforeValidate();
        if (!isset($this->data['GalaxyCluster']['description'])) {
            $this->data['GalaxyCluster']['description'] = '';
        }
        return true;
    }

    public function afterFind($results, $primary = false)
    {
        foreach ($results as $k => $result) {
            if (isset($results[$k]['GalaxyCluster']['authors'])) {
                $results[$k]['GalaxyCluster']['authors'] = json_decode($results[$k]['GalaxyCluster']['authors'], true);
            }
        }
        return $results;
    }

    public function beforeDelete($cascade = true)
    {
        $this->GalaxyElement->deleteAll(array('GalaxyElement.galaxy_cluster_id' => $this->id));
    }


    // receive a full galaxy and add all new clusters, update existing ones contained in the new galaxy, cull old clusters that are removed from the galaxy
    public function update($galaxy_id, $cluster_package, $force, $default = true)
    {
        $template = array(
            'source' => isset($cluster_package['source']) ? $cluster_package['source'] : '',
            'authors' => json_encode(isset($cluster_package['authors']) ? $cluster_package['authors'] : array(), true),
            'collection_uuid' => isset($cluster_package['uuid']) ? $cluster_package['uuid'] : '',
            'galaxy_id' => $galaxy_id,
            'type' => $cluster_package['type'],
            'tag_name' => 'misp-galaxy:' . $cluster_package['type'] . '="'
        );
        $elements = array();
        $temp = $this->find('all', array(
            'conditions' => array(
                'GalaxyCluster.galaxy_id' => $galaxy_id
            ),
            'recursive' => -1,
            'fields' => array('version', 'id', 'value', 'uuid')
        ));
        $existingClusters = array();
        foreach ($temp as $k => $v) {
            $existingClusters[$v['GalaxyCluster']['uuid']] = $v;
        }
        $clusterPackageProxy = array();
        foreach ($cluster_package['values'] as $k => $cluster) {
            if (!isset($cluster['uuid'])) {
                $cluster_package['values'][$k]['uuid'] = CakeText::uuid();
            }
            $clusterPackageProxy[$cluster_package['values'][$k]['uuid']] = $cluster;
        }

        $clustersToDelete = array();
        $obsoleteClusters = array_diff_key($existingClusters, $clusterPackageProxy);
        // Delete all clusters that do not exists anymore
        foreach($obsoleteClusters as $k => $obsoleteCluster) {
            $clustersToDelete[] = $obsoleteCluster['GalaxyCluster']['id'];
        }
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
            if (!empty($existingClusters[$cluster['uuid']])) {
                if ($force || $existingClusters[$cluster['uuid']]['GalaxyCluster']['version'] < $cluster_package['values'][$k]['version']) {
                    $clustersToDelete[] = $existingClusters[$cluster['uuid']]['GalaxyCluster']['id'];
                } else {
                    unset($cluster_package['values'][$k]);
                }
            }
        }
        if (!empty($clustersToDelete)) {
            $this->GalaxyElement->deleteAll(array('GalaxyElement.galaxy_cluster_id' => $clustersToDelete), false, false);
            $this->delete($clustersToDelete, false, false);
        }

        // create all clusters
        $saveSuccess = true;
        foreach ($cluster_package['values'] as $cluster) {
            if (empty($cluster['version'])) {
                $cluster['version'] = 1;
            }
            $template['version'] = $cluster['version'];
            $this->create();
            $cluster_to_save = $template;
            if (isset($cluster['description'])) {
                $cluster_to_save['description'] = $cluster['description'];
                unset($cluster['description']);
            }
            $cluster_to_save['value'] = $cluster['value'];
            if (!empty($cluster['uuid'])) {
                $cluster_to_save['uuid'] = $cluster['uuid'];
            }
            if ($default) {
                $cluster_to_save['tag_name'] = $cluster_to_save['tag_name'] . $cluster['value'] . '"';
            } else { // user-made galaxy cannot expose the cluster value
                $cluster_to_save['tag_name'] = $cluster_to_save['tag_name'] . $cluster['uuid'] . '"';
            }
            unset($cluster['value']);
            if (empty($cluster_to_save['description'])) {
                $cluster_to_save['description'] = '';
            }
            debug($cluster_to_save);
            $saveSuccess = $saveSuccess && $this->save($cluster_to_save, false);
            $galaxyClusterId = $this->id;
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
                            $this->id,
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
        return $saveSuccess;
    }

    /* Return a list of all tags associated with the cluster specific cluster within the galaxy (or all clusters if $clusterValue is false)
     * The counts are restricted to the event IDs that the user is allowed to see.
    */
    public function getTags($galaxyType, $clusterValue = false, $user)
    {
        $this->Event = ClassRegistry::init('Event');
        $event_ids = $this->Event->fetchEventIds($user, false, false, false, true);
        $tags = $this->Event->EventTag->Tag->find('list', array(
                'conditions' => array('name LIKE' => 'misp-galaxy:' . $galaxyType . '="' . ($clusterValue ? $clusterValue : '%') .'"'),
                'fields' => array('name', 'id'),
        ));
        $this->Event->EventTag->virtualFields['tag_count'] = 'COUNT(id)';
        $tagCounts = $this->Event->EventTag->find('list', array(
                'conditions' => array('EventTag.tag_id' => array_values($tags), 'EventTag.event_id' => $event_ids),
                'fields' => array('EventTag.tag_id', 'EventTag.tag_count'),
                'group' => array('EventTag.tag_id')
        ));
        foreach ($tags as $k => $v) {
            if (isset($tagCounts[$v])) {
                $tags[$k] = array('count' => $tagCounts[$v], 'tag_id' => $v);
            } else {
                unset($tags[$k]);
            }
        }
        return $tags;
    }

    /* Fetch a cluster along with all elements and the galaxy it belongs to
     *   - In the future, once we move to galaxy 2.0, pass a user along for access control
     *   - maybe in the future remove the galaxy itself once we have logos with each galaxy
    */
    public function getCluster($name, $user)
    {
        $conditions = $this->Galaxy->buildConditions($user);
        $conditions['AND'][] = array('LOWER(GalaxyCluster.tag_name)' => strtolower($name));
        if (is_numeric($name)) {
            $conditions['AND'][] = array('GalaxyCluster.id' => $name);
        }
        if (isset($this->__clusterCache[$name])) {
            return $this->__clusterCache[$name];
        }
        $objects = array('Galaxy', 'GalaxyElement');
        $cluster = $this->find('first', array(
            'conditions' => $conditions,
            'contain' => array('Galaxy', 'GalaxyElement')
        ));
        if (!empty($cluster)) {
            if (isset($cluster['Galaxy'])) {
                $cluster['GalaxyCluster']['Galaxy'] = $cluster['Galaxy'];
                unset($cluster['Galaxy']);
            }
            $elements = array();
            foreach ($cluster['GalaxyElement'] as $element) {
                if (!isset($elements[$element['key']])) {
                    $elements[$element['key']] = array($element['value']);
                } else {
                    $elements[$element['key']][] = $element['value'];
                }
            }
            unset($cluster['GalaxyElement']);
            $this->Tag = ClassRegistry::init('Tag');
            $tag_id = $this->Tag->find(
                'first',
                array(
                    'conditions' => array(
                        'LOWER(Tag.name)' => strtolower($cluster['GalaxyCluster']['tag_name'])
                    ),
                    'recursive' => -1,
                    'fields' => array('Tag.id')
                )
            );
            if (!empty($tag_id)) {
                $cluster['GalaxyCluster']['tag_id'] = $tag_id['Tag']['id'];
            }
            $cluster['GalaxyCluster']['meta'] = $elements;
        }
        $this->__clusterCache[$name] = $cluster;
        return $cluster;
    }

    public function attachClustersToEventIndex($user, $events, $replace = false)
    {
        foreach ($events as $k => $event) {
            foreach ($event['EventTag'] as $k2 => $eventTag) {
                if (substr($eventTag['Tag']['name'], 0, strlen('misp-galaxy:')) === 'misp-galaxy:') {
                    $cluster = $this->getCluster($eventTag['Tag']['name'], $user);
                    if ($cluster) {
                        $cluster['GalaxyCluster']['tag_id'] = $eventTag['Tag']['id'];
                        $cluster['GalaxyCluster']['local'] = $eventTag['local'];
                        $events[$k]['GalaxyCluster'][] = $cluster['GalaxyCluster'];
                        if ($replace) {
                            unset($events[$k]['EventTag'][$k2]);
                        }
                    }
                }
            }
        }
        return $events;
    }

    public function getClusterTagsFromMeta($galaxyElements, $user)
    {
        // AND operator between cluster metas
        $tmpResults = array();
        foreach ($galaxyElements as $galaxyElementKey => $galaxyElementValue) {
            $tmpResults[] = array_values($this->GalaxyElement->find('list', array(
                'conditions' => array(
                    'key' => $galaxyElementKey,
                    'value' => $galaxyElementValue,
                ),
                'fields' => array('galaxy_cluster_id'),
                'recursive' => -1
            )));
        }
        $clusterTags = array();
        if (!empty($tmpResults)) {
            // Get all Clusters matching all conditions
            $matchingClusters = $tmpResults[0];
            array_shift($tmpResults);
            foreach ($tmpResults as $tmpResult) {
                $matchingClusters = array_intersect($matchingClusters, $tmpResult);
            }
    
            $clusterTags = $this->find('list', array(
                'conditions' => array('id' => $matchingClusters),
                'fields' => array('GalaxyCluster.tag_name'),
                'recursive' => -1
            ));
            // TODO: Apply ACL
        }
        return array_values($clusterTags);
    }
}
