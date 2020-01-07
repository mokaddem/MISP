<?php
App::uses('AppController', 'Controller');

class GalaxiesController extends AppController
{
    public $components = array('Session', 'RequestHandler');

    public $paginate = array(
            'limit' => 60,
            'maxLimit' => 9999, // LATER we will bump here on a problem once we have more than 9999 events <- no we won't, this is the max a user van view/page.
            'contain' => array(

            ),
            'order' => array(
                'Galaxy.id' => 'DESC'
            ),
    );

    public function index()
    {
        $filters = $this->IndexFilter->harvestParameters(array('context', 'value'));
        if (empty($filters['context'])) {
            $filters['context'] = 'all';
        }
        if (empty($filters['value'])) {
            $filters['value'] = '';
        } else {
            $searchall = '%' . strtolower($filters['value']) . '%';
            $this->paginate['conditions']['AND'][] = array(
                'OR' => array(
                    'LOWER(Galaxy.name) LIKE' => $searchall,
                    'LOWER(Galaxy.namespace) LIKE' => $searchall,
                    'LOWER(Galaxy.description) LIKE' => $searchall,
                    'LOWER(Galaxy.kill_chain_order) LIKE' => $searchall,
                    'Galaxy.uuid LIKE' => $searchall
                )
            );
        }
        $galaxies = $this->Galaxy->find('all', array('recursive' => -1));
        if ($this->_isRest()) {
            return $this->RestResponse->viewData($galaxies, $this->response->type());
        } else {
            $galaxies = $this->paginate();
            $this->set('galaxyList', $galaxies);
            $this->set('list', $galaxies);
            $this->set('context', $filters['context']);
            $this->set('searchall', $filters['value']);
        }
    }

    public function update()
    {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException('This action is only accessible via POST requests.');
        }
        if (!empty($this->params['named']['force'])) {
            $force = 1;
        } else {
            $force = 0;
        }
        $result = $this->Galaxy->update($force);
        $message = 'Galaxies updated.';
        if ($this->_isRest()) {
            return $this->RestResponse->saveSuccessResponse('Galaxy', 'update', false, $this->response->type(), $message);
        } else {
            $this->Flash->success($message);
            $this->redirect(array('controller' => 'galaxies', 'action' => 'index'));
        }
    }

    public function add()
    {
        $this->loadModel('Attribute');
        $distributionLevels = $this->Attribute->distributionLevels;
        unset($distributionLevels[5]);
        $initialDistribution = 3;
        $configuredDistribution = Configure::check('MISP.default_attribute_distribution');
        if ($configuredDistribution != null && $configuredDistribution != 'event') {
            $initialDistribution = $configuredDistribution;
        }
        $this->loadModel('SharingGroup');
        $sgs = $this->SharingGroup->fetchAllAuthorised($this->Auth->user(), 'name', 1);

        if (isset($this->params['named']['forkId'])) {
            $origGalaxy = $this->Galaxy->find('first', array(
                'recursive' => -1,
                'conditions' => array('id' => $this->params['named']['forkId']),
                'contain' => array('GalaxyCluster' => array('GalaxyElement')),
            ));
            if (!empty($origGalaxy)) {
                $this->set('forkId', $this->params['named']['forkId']);
                $origGalaxyMeta = $origGalaxy['Galaxy'];
                if (empty($this->request->data)) {
                    $this->request->data = $origGalaxy;
                    unset($this->request->data['Galaxy']['id']);
                    unset($this->request->data['Galaxy']['uuid']);
                    unset($this->request->data['Galaxy']['type']);
                    unset($this->request->data['Galaxy']['version']);
                    unset($this->request->data['Galaxy']['org_id']);
                    unset($this->request->data['Galaxy']['orgc_id']);
                    $this->request->data['Galaxy']['kill_chain_order'] = isset($origGalaxy['Galaxy']['kill_chain_order']) ? json_encode($origGalaxy['Galaxy']['kill_chain_order']) : '{}';
                    $this->request->data['Galaxy']['values'] = json_encode($origGalaxy['GalaxyCluster']);
                }
                $this->set('origGalaxy', $origGalaxy);
                $this->set('origGalaxyMeta', $origGalaxyMeta);
            }
        }
        if ($this->request->is('post') || $this->request->is('put')) {
            // $galaxy = array('Galaxy' => $this->request->data['Galaxy']);
            $galaxy = $this->request->data;
            $errors = array();
            if (empty($galaxy['Galaxy']['values'])) {
                $galaxy['Galaxy']['values'] = array();
            } else {
                $galaxy['Galaxy']['values'] = $this->decodeJson($galaxy['Galaxy']['values']);
            }
            $galaxy['Galaxy']['uuid'] = CakeText::uuid();
            $type = $galaxy['Galaxy']['uuid'];
            $galaxy['Galaxy']['type'] = $type;
            $date = new DateTime();
            $galaxy['Galaxy']['version'] = $date->getTimestamp();
            $galaxy['Galaxy']['org_id'] = $this->Auth->user('org_id');
            $galaxy['Galaxy']['orgc_id'] = $this->Auth->user('org_id');
            $this->Galaxy->create();
            $saveSuccess = $this->Galaxy->save($galaxy);
            if (!$saveSuccess) {
                foreach($this->Galaxy->validationErrors as $validationError) {
                    $errors[] = $validationError;
                }
            }
            if (!empty($errors)) {
                $flashErrorMessage = implode(', ', $errors);
                $this->Flash->error($flashErrorMessage);
            } else {
                $this->redirect(array('controller' => 'galaxies', 'action' => 'view', $this->id));
            }
        }
        $this->set('distributionLevels', $distributionLevels);
        $this->set('initialDistribution', $initialDistribution);
        $this->set('sharingGroups', $sgs);
        $this->set('action', 'add');
    }

    public function import()
    {
        if ($this->request->is('post') || $this->request->is('put')) {
            $data = $this->request->data['Galaxy'];
            if ($data['submittedjson']['name'] != '' && $data['json'] != '') {
                throw new MethodNotAllowedException(__('Only one import field can be used at a time'));
            }
            if ($data['submittedjson']['size'] > 0) {
                $filename = basename($data['submittedjson']['name']);
                $file_content = file_get_contents($data['submittedjson']['tmp_name']);
                if ((isset($data['submittedjson']['error']) && $data['submittedjson']['error'] == 0) ||
                    (!empty($data['submittedjson']['tmp_name']) && $data['submittedjson']['tmp_name'] != '')
                ) {
                    if (!$file_content) {
                        throw new InternalErrorException(__('PHP says file was not uploaded. Are you attacking me?'));
                    }
                }
                $text = $file_content;
            } else {
                $text = $data['json'];
            }
            $json = json_decode($text, true);
            if ($json === null) {
                throw new MethodNotAllowedException(__('Error while decoding JSON'));
            }
            
            // perfom clean up here

            // $saveResult = $this->Galaxy->save($json);
            $saveResult = true;
            if ($saveResult) {
                $message = __('Galaxy imported');
                if ($this->_isRest()) {
                    return $this->RestResponse->saveSuccessResponse('Galaxy', 'import', false, $this->response->type(), $message);
                } else {
                    $this->Flash->success($message);
                    $this->redirect(array('controller' => 'galaxies', 'action' => 'index'));
                }
            } else {
                $message = __('Could not import Galaxy');
                if ($this->_isRest()) {
                    return $this->RestResponse->saveFailResponse('Galaxy', 'import', false, $message);
                } else {
                    $this->Flash->error($message);
                    $this->redirect($this->referer());
                }
            }
        }
    }

    public function decodeJson($text)
    {
        $decoded = json_decode($text, true);
        if ($decoded === null) {
            $decoded = array();
        }
        return $decoded;
    }

    public function edit($id = null)
    {
        if (Validation::uuid($id)) {
            $temp = $this->Galaxy->find('first', array(
                'recursive' => -1,
                'fields' => array('Galaxy.id', 'Galaxy.uuid'),
                'conditions' => array('Galaxy.uuid' => $id)
            ));
            if ($temp == null) {
                throw new NotFoundException('Invalid galaxy');
            }
            $id = $temp['Galaxy']['id'];
        } elseif (!is_numeric($id)) {
            throw new NotFoundException(__('Invalid galaxy'));
        }
        $conditions = array('conditions' => array('Galaxy.id' => $id));
        $galaxy = $this->Galaxy->fetchGalaxies($this->Auth->user(), $conditions);
        if (empty($galaxy)) {
            throw new MethodNotAllowedException('Invalid galaxy');
        }
        $this->Galaxy->data = $galaxy[0];

        $this->loadModel('Attribute');
        $distributionLevels = $this->Attribute->distributionLevels;
        unset($distributionLevels[5]);
        $initialDistribution = 3;
        $configuredDistribution = Configure::check('MISP.default_attribute_distribution');
        if ($configuredDistribution != null && $configuredDistribution != 'event') {
            $initialDistribution = $configuredDistribution;
        }
        $this->loadModel('SharingGroup');
        $sgs = $this->SharingGroup->fetchAllAuthorised($this->Auth->user(), 'name', 1);

        if ($this->request->is('post') || $this->request->is('put')) {
            $galaxy = $this->request->data;
            $errors = array();
            if (!isset($galaxy['Galaxy']['uuid'])) { 
                $galaxy['Galaxy']['uuid'] = $this->Galaxy->data['Galaxy']['uuid'];
            }
            if (!isset($galaxy['Galaxy']['id'])) { 
                $galaxy['Galaxy']['id'] = $id;
            }
            if (
                $galaxy['Galaxy']['id'] !== $this->Galaxy->data['Galaxy']['id'] || 
                $galaxy['Galaxy']['uuid'] !== $this->Galaxy->data['Galaxy']['uuid']
            ) {
                $errors[] = sprintf(__('Galaxy id/uuid missmatch'));
            }
            if (empty($galaxy['Galaxy']['values'])) {
                $galaxy['Galaxy']['values'] = array();
            } else {
                $decoded = json_decode($galaxy['Galaxy']['values'], true);
                if ($decoded === null) {
                    $this->Galaxy->validationErrors['values'][] = __('Invalid JSON');
                    $errors[] = sprintf(__('Invalid JSON'));
                }
                $galaxy['Galaxy']['values'] = $decoded;
            }
            $date = new DateTime();
            $galaxy['Galaxy']['version'] = $date->getTimestamp();
            $fieldList = array('name', 'namespace', 'description', 'version', 'icon', 'kill_chain_order', 'distribution', 'sharing_group_id');
            if (empty($errors)) {
                $saveSuccess = $this->Galaxy->save($galaxy, array('fieldList' => $fieldList));
                if (!$saveSuccess) {
                    foreach($this->Galaxy->validationErrors as $validationError) {
                        $errors[] = $validationError[0];
                    }
                } else {
                    $savedGalaxy = $this->Galaxy->find('first', array(
                        'conditions' => array('id' => $id),
                        'recursive' => -1
                    ));
                    $savedGalaxy['Galaxy']['values'] = $galaxy['Galaxy']['values'];
                    $saveSuccess = $this->Galaxy->editClusters($savedGalaxy['Galaxy']);
                    if(!$saveSuccess) {
                        $errors[] = __('Error while saving clusters');
                    }
                }
            }
            if (!empty($errors)) {
                $flashErrorMessage = implode(', ', $errors);
                $this->Flash->error($flashErrorMessage);
            } else {
                $this->redirect(array('controller' => 'galaxies', 'action' => 'view', $id));
            }
        } else {
            $this->request->data = $this->Galaxy->data;
            if (isset($this->request->data['Galaxy']['kill_chain_order'])) {
                $this->request->data['Galaxy']['kill_chain_order'] = json_encode($this->request->data['Galaxy']['kill_chain_order']);
            }
        }
        $this->set('distributionLevels', $distributionLevels);
        $this->set('initialDistribution', $initialDistribution);
        $this->set('sharingGroups', $sgs);
        $this->set('galaxyId', $id);
        $this->set('action', 'edit');
        $this->render('add');
    }

    public function view($id)
    {
        $id = $this->Toolbox->findIdByUuid($this->Galaxy, $id);
        if (isset($this->params['named']['searchall']) && strlen($this->params['named']['searchall']) > 0) {
            $this->set('passedArgsArray', array('all' => $this->params['named']['searchall']));
        }
        if ($this->_isRest()) {
            $galaxy = $this->Galaxy->find('first', array(
                    'contain' => array('GalaxyCluster' => array('GalaxyElement'/*, 'GalaxyReference'*/)),
                    'recursive' => -1,
                    'conditions' => array('Galaxy.id' => $id)
            ));
            if (empty($galaxy)) {
                throw new NotFoundException('Galaxy not found.');
            }
            return $this->RestResponse->viewData($galaxy, $this->response->type());
        } else {
            $galaxy = $this->Galaxy->find('first', array(
                    'recursive' => -1,
                    'conditions' => array('Galaxy.id' => $id)
            ));
            if (empty($galaxy)) {
                throw new NotFoundException('Galaxy not found.');
            }
            $this->set('galaxy', $galaxy);
        }
    }

    public function delete($id)
    {
        if (!is_numeric($id)) {
            throw new NotFoundException('Invalid galaxy.');
        }
        $galaxy = $this->Galaxy->find('first', array(
                'recursive' => -1,
                'conditions' => array('Galaxy.id' => $id)
        ));
        if (empty($galaxy)) {
            throw new NotFoundException('Invalid galaxy.');
        }
        $result = $this->Galaxy->delete($id);
        if ($result) {
            $message = __('Galaxy deleted');
            if ($this->_isRest()) {
                return $this->RestResponse->saveSuccessResponse('Galaxy', 'delete', false, $this->response->type(), $message);
            } else {
                $this->Flash->success($message);
                $this->redirect(array('controller' => 'galaxies', 'action' => 'index'));
            }
        } else {
            $message = __('Could not delete Galaxy.');
            if ($this->_isRest()) {
                return $this->RestResponse->saveFailResponse('Galaxy', 'delete', false, $message);
            } else {
                $this->Flash->error($message);
                $this->redirect($this->referer());
            }
        }
    }

    public function selectGalaxy($target_id, $target_type='event', $namespace='misp')
    {
        $mitreAttackGalaxyId = $this->Galaxy->getMitreAttackGalaxyId();
        $local = !empty($this->params['named']['local']) ? $this->params['named']['local'] : '0';
        $conditions = $namespace === '0' ? array() : array('namespace' => $namespace);
        $galaxies = $this->Galaxy->find('all', array(
            'recursive' => -1,
            'fields' => array('MAX(Galaxy.version) as latest_version', 'id', 'kill_chain_order', 'name', 'icon', 'description'),
            'conditions' => $conditions,
            'group' => array('name', 'id', 'kill_chain_order', 'icon', 'description'),
            'order' => array('name asc')
        ));
        $items = array(
            array(
                'name' => __('All clusters'),
                'value' => "/galaxies/selectCluster/" . h($target_id) . '/' . h($target_type) . '/0'. '/local:' . $local
            )
        );
        foreach ($galaxies as $galaxy) {
            if (!isset($galaxy['Galaxy']['kill_chain_order'])) {
                $items[] = array(
                    'name' => h($galaxy['Galaxy']['name']),
                    'value' => "/galaxies/selectCluster/" . $target_id . '/' . $target_type . '/' . $galaxy['Galaxy']['id'] . '/local:' . $local,
                    'template' => array(
                        'preIcon' => 'fa-' . $galaxy['Galaxy']['icon'],
                        'name' => $galaxy['Galaxy']['name'],
                        'infoExtra' => $galaxy['Galaxy']['description'],
                    )
                );
            } else { // should use matrix instead
                $param = array(
                    'name' => $galaxy['Galaxy']['name'],
                    'functionName' => sprintf(
                        "getMatrixPopup('%s', '%s', '%s/local:%s')",
                        $target_type,
                        $target_id,
                        $galaxy['Galaxy']['id'],
                        $local
                    ),
                    'isPill' => true,
                    'isMatrix' => true
                );
                if ($galaxy['Galaxy']['id'] == $mitreAttackGalaxyId) {
                    $param['img'] = "/img/mitre-attack-icon.ico";
                }
                $items[] = $param;
            }
        }

        $this->set('items', $items);
        $this->render('/Elements/generic_picker');
    }

    public function selectGalaxyNamespace($target_id, $target_type='event')
    {
        $namespaces = $this->Galaxy->find('list', array(
            'recursive' => -1,
            'fields' => array('namespace', 'namespace'),
            'group' => array('namespace'),
            'order' => array('namespace asc')
        ));
        $local = !empty($this->params['named']['local']) ? '1' : '0';
        $items = array();
        $items[] = array(
            'name' => __('All namespaces'),
            'value' => "/galaxies/selectGalaxy/" . $target_id . '/' . $target_type . '/0' . '/local:' . $local
        );
        foreach ($namespaces as $namespace) {
            $items[] = array(
                'name' => $namespace,
                'value' => "/galaxies/selectGalaxy/" . $target_id . '/' . $target_type . '/' . $namespace . '/local:' . $local
            );
        }

        $this->set('items', $items);
        $this->set('options', array( // set chosen (select picker) options
            'multiple' => 0,
        ));
        $this->render('/Elements/generic_picker');
    }

    public function selectCluster($target_id, $target_type = 'event', $selectGalaxy = false)
    {
        $conditions = array();
        if ($selectGalaxy) {
            $conditions = array('GalaxyCluster.galaxy_id' => $selectGalaxy);
        }
        $local = !empty($this->params['named']['local']) ? $this->params['named']['local'] : '0';
        $data = $this->Galaxy->GalaxyCluster->find('all', array(
                'conditions' => $conditions,
                'fields' => array('value', 'description', 'source', 'type', 'id'),
                'order' => array('value asc'),
                'recursive' => -1
        ));
        $clusters = array();
        $cluster_ids = array();
        foreach ($data as $k => $cluster) {
            $cluster_ids[] = $cluster['GalaxyCluster']['id'];
        }
        $synonyms = $this->Galaxy->GalaxyCluster->GalaxyElement->find('all', array(
            'conditions' => array(
                'GalaxyElement.galaxy_cluster_id' => $cluster_ids,
                'GalaxyElement.key' => 'synonyms'
            ),
            'recursive' => -1
        ));
        $sorted_synonyms = array();
        foreach ($synonyms as $synonym) {
            $sorted_synonyms[$synonym['GalaxyElement']['galaxy_cluster_id']][] = $synonym;
        }
        foreach ($data as $k => $cluster) {
            $cluster['GalaxyCluster']['synonyms_string'] = array();
            if (!empty($sorted_synonyms[$cluster['GalaxyCluster']['id']])) {
                foreach ($sorted_synonyms[$cluster['GalaxyCluster']['id']] as $element) {
                    $cluster['GalaxyCluster']['synonyms_string'][] = $element['GalaxyElement']['value'];
                    $cluster['GalaxyElement'][] = $element['GalaxyElement'];
                }
                unset($sorted_synonyms[$cluster['GalaxyCluster']['id']]);
            }
            $cluster['GalaxyCluster']['synonyms_string'] = implode(', ', $cluster['GalaxyCluster']['synonyms_string']);
            unset($cluster['GalaxyElement']);
            $clusters[$cluster['GalaxyCluster']['type']][$cluster['GalaxyCluster']['value']] = $cluster['GalaxyCluster'];
        }
        ksort($clusters);
        $this->set('target_id', $target_id);
        $this->set('target_type', $target_type);

        $items = array();
        foreach ($clusters as $namespace => $cluster_data) {
            foreach ($cluster_data as $k => $cluster) {
                $name = $cluster['value'];
                $optionName = $cluster['value'];
                if ($cluster['synonyms_string'] !== '') {
                    $synom = __('Synonyms: ') . $cluster['synonyms_string'];
                    $optionName .= $cluster['synonyms_string'] !== '' ? ' (' . $cluster['synonyms_string'] . ')' : '';
                } else {
                    $synom = '';
                }
                $itemParam = array(
                    'name' => $optionName,
                    'value' => $cluster['id'],
                    'template' => array(
                        'name' => $name,
                        'infoExtra' => $cluster['description'],
                    )
                );
                if ($cluster['synonyms_string'] !== '') {
                    $itemParam['template']['infoContextual'] = $synom;
                }
                $items[] = $itemParam;
                unset($cluster_data[$k]);
            }
        }
        $onClickForm = 'quickSubmitGalaxyForm';
        if ($this->_isRest()) {
            return $this->RestResponse->viewData($items, $this->response->type());
        } else {
            $this->set('items', $items);
            $this->set('options', array( // set chosen (select picker) options
                'functionName' => $onClickForm,
                'multiple' => '-1',
                'select_options' => array(
                    'additionalData' => array(
                        'target_id' => $target_id,
                        'target_type' => $target_type,
                        'local' => $local
                    )
                ),
            ));
            $this->render('ajax/cluster_choice');
        }
    }

    public function attachCluster($target_id, $target_type = 'event')
    {
        $cluster_id = $this->request->data['Galaxy']['target_id'];
        $result = $this->Galaxy->attachCluster($this->Auth->user(), $target_type, $target_id, $cluster_id);
        return new CakeResponse(array('body'=> json_encode(array('saved' => true, 'success' => $result, 'check_publish' => true)), 'status'=>200, 'type' => 'json'));
    }

    public function attachMultipleClusters($target_id, $target_type = 'event')
    {
        $local = !empty($this->params['named']['local']);
        $this->set('local', $local);
        if ($this->request->is('post')) {
            if ($target_id === 'selected') {
                $target_id_list = json_decode($this->request->data['Galaxy']['attribute_ids']);
            } else {
                $target_id_list = array($target_id);
            }
            $cluster_ids = $this->request->data['Galaxy']['target_ids'];
            if (strlen($cluster_ids) > 0) {
                $cluster_ids = json_decode($cluster_ids, true);
                if ($cluster_ids === null || empty($cluster_ids)) {
                    return new CakeResponse(array('body'=> json_encode(array('saved' => false, 'errors' => __('Failed to parse request or no clusters picked.'))), 'status'=>200, 'type' => 'json'));
                }
            } else {
                return new CakeResponse(array('body'=> json_encode(array('saved' => false, 'errors' => __('Failed to parse request.'))), 'status'=>200, 'type' => 'json'));
            }
            $result = "";
            if (!is_array($cluster_ids)) { // in case we only want to attach 1
                $cluster_ids = array($cluster_ids);
            }
            foreach ($cluster_ids as $cluster_id) {
                foreach ($target_id_list as $target_id) {
                    $result = $this->Galaxy->attachCluster($this->Auth->user(), $target_type, $target_id, $cluster_id, $local);
                }
            }
            if ($this->request->is('ajax')) {
                return new CakeResponse(array('body'=> json_encode(array('saved' => true, 'success' => $result, 'check_publish' => true)), 'status'=>200, 'type' => 'json'));
            } else {
                $this->Flash->info($result);
                $this->redirect($this->referer());
            }
        } else {
            $this->set('target_id', $target_id);
            $this->set('target_type', $target_type);
            $this->layout = false;
            $this->autoRender = false;
            $this->render('/Galaxies/ajax/attach_multiple_clusters');
        }
    }

    public function viewGraph($id)
    {
        $cluster = $this->Galaxy->GalaxyCluster->find('first', array(
            'conditions' => array('GalaxyCluster.id' => $id),
            'contain' => array('Galaxy'),
            'recursive' => -1
        ));
        if (empty($cluster)) {
            throw new MethodNotAllowedException('Invalid Galaxy.');
        }
        $this->set('cluster', $cluster);
        $this->set('scope', 'galaxy');
        $this->set('id', $id);
        $this->set('galaxy_id', $cluster['Galaxy']['id']);
        $this->render('/Events/view_graph');
    }

    public function showGalaxies($id, $scope = 'event')
    {
        $this->layout = 'ajax';
        $this->set('scope', $scope);
        if ($scope == 'event') {
            $this->loadModel('Event');
            $object = $this->Event->fetchEvent($this->Auth->user(), array('eventid' => $id, 'metadata' => 1));
            if (empty($object)) {
                throw new MethodNotAllowedException('Invalid event.');
            }
            $this->set('object', $object[0]);

        } elseif ($scope == 'attribute') {
            $this->loadModel('Attribute');
            $object = $this->Attribute->fetchAttributes($this->Auth->user(), array('conditions' => array('Attribute.id' => $id), 'flatten' => 1));
            if (empty($object)) {
                throw new MethodNotAllowedException('Invalid attribute.');
            }
            $object[0] = $this->Attribute->Event->massageTags($object[0], 'Attribute');
        } elseif ($scope == 'tag_collection') {
            $this->loadModel('TagCollection');
            $object = $this->TagCollection->fetchTagCollection($this->Auth->user(), array('conditions' => array('TagCollection.id' => $id)));
            if (empty($object)) {
                throw new MethodNotAllowedException('Invalid Tag Collection.');
            }
        }
        $this->set('object', $object[0]);
        $this->render('/Events/ajax/ajaxGalaxies');
    }
}
