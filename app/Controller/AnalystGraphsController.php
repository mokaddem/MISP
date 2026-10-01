<?php
App::uses('AppController', 'Controller');
App::uses('AnalystGraphDocumentTool', 'Tools');
App::uses('JsonTool', 'Tools');

/**
 * The actions only an analyst graph has. Creating, editing, viewing and
 * deleting one go through AnalystDataController with the type `Graph`.
 */
class AnalystGraphsController extends AppController
{
    public $components = ['Session', 'RequestHandler'];

    public $uses = ['Graph', 'AnalystGraphData'];

    public function beforeFilter()
    {
        parent::beforeFilter();
        // Posted as hand-built JSON by the graph host, with the CSRF token
        // in the X-CSRF-Token header.
        $this->_csrfTokenHeaderOnly(['save', 'fork']);
    }

    /**
     * The graph as the user sees it: its document, the records of the nodes
     * they may read and the edges between them.
     *
     * @param string $uuid
     */
    public function data($uuid)
    {
        $this->request->allowMethod(['get']);
        $graph = $this->__fetchGraph($uuid);
        $document = json_decode($graph['Graph']['content'], true) ?: AnalystGraphDocumentTool::emptyDocument();
        unset($graph['Graph']['content']);
        $payload = $this->AnalystGraphData->resolve($this->Auth->user(), $document);
        return $this->RestResponse->viewData(['Graph' => $graph['Graph']] + $payload, 'json');
    }

    /**
     * Replace the document, unless the graph was saved since the revision the
     * client started from.
     *
     * @param string $uuid
     */
    public function save($uuid)
    {
        $this->request->allowMethod(['post', 'put']);
        $graph = $this->__fetchEditableGraph($uuid);
        $input = $this->__input();
        $revision = $input['revision'] ?? null;
        if (!is_int($revision) && !(is_string($revision) && ctype_digit($revision))) {
            throw new BadRequestException(__('A save names the revision it started from.'));
        }
        if (!isset($input['content']) || (!is_array($input['content']) && !is_string($input['content']))) {
            throw new BadRequestException(__('A save carries the graph document as content.'));
        }
        $content = $input['content'];
        $result = $this->Graph->writeContent($graph['Graph']['id'], function () use ($content) {
            return $content;
        }, (int)$revision);
        return $this->__writeResponse($graph, $result);
    }

    /**
     * A copy of the graph owned by the user's organisation, holding the nodes
     * they can see.
     *
     * @param string $uuid
     */
    public function fork($uuid)
    {
        $this->request->allowMethod(['post']);
        $user = $this->Auth->user();
        $original = $this->__fetchGraph($uuid);
        $input = $this->__input();
        $document = json_decode($original['Graph']['content'], true) ?: AnalystGraphDocumentTool::emptyDocument();
        $document['nodes'] = $this->AnalystGraphData->visibleNodes($user, $document['nodes'] ?? []);
        $target = isset($input['target']) && is_array($input['target']) ? $input['target'] : [];
        $fork = ['Graph' => [
            'name' => isset($input['name']) && is_string($input['name']) ? $input['name'] : $original['Graph']['name'],
            'description' => $original['Graph']['description'],
            'object_uuid' => $target['uuid'] ?? $original['Graph']['object_uuid'],
            'object_type' => $target['type'] ?? $original['Graph']['object_type'],
            'distribution' => 0,
            'content' => $document,
            'forked_from_uuid' => $original['Graph']['uuid'],
        ]];
        $this->Graph->current_user = $user;
        $this->Graph->create();
        if (!$this->Graph->save($fork)) {
            return $this->RestResponse->saveFailResponse('AnalystGraphs', 'fork', false, $this->Graph->validationErrors, 'json');
        }
        $created = $this->Graph->find('first', [
            'conditions' => ['Graph.id' => $this->Graph->id],
            'contain' => ['Org', 'Orgc'],
        ]);
        $created['Graph']['content'] = AnalystGraphDocumentTool::decode($created['Graph']['content']);
        return $this->RestResponse->viewData($created, 'json');
    }

    /**
     * @param string $uuid
     * @return array
     * @throws NotFoundException
     */
    private function __fetchGraph($uuid)
    {
        if (!Validation::uuid($uuid)) {
            throw new NotFoundException(__('Invalid graph.'));
        }
        $user = $this->Auth->user();
        $this->Graph->current_user = $user;
        $graph = $this->Graph->find('first', [
            'conditions' => [
                'AND' => [
                    ['Graph.uuid' => $uuid],
                    $this->Graph->buildConditions($user),
                ],
            ],
            'contain' => ['Org', 'Orgc'],
        ]);
        if (empty($graph)) {
            throw new NotFoundException(__('Invalid graph.'));
        }
        return $graph;
    }

    /**
     * @param string $uuid
     * @return array
     * @throws ForbiddenException
     */
    private function __fetchEditableGraph($uuid)
    {
        $graph = $this->__fetchGraph($uuid);
        if (empty($graph['Graph']['_canEdit'])) {
            throw new ForbiddenException(__('Only the organisation that created this graph can change it. Fork it instead.'));
        }
        return $graph;
    }

    /**
     * @return array
     */
    private function __input()
    {
        $input = $this->request->data;
        if (!is_array($input)) {
            return [];
        }
        return isset($input['Graph']) && is_array($input['Graph']) ? $input['Graph'] : $input;
    }

    /**
     * @param array $graph
     * @param array $result Graph::writeContent()'s
     * @return CakeResponse
     */
    private function __writeResponse(array $graph, array $result)
    {
        switch ($result['status']) {
            case 'missing':
                throw new NotFoundException(__('Invalid graph.'));
            case 'conflict':
                $this->response->statusCode(409);
                $this->response->type('json');
                $this->response->body(JsonTool::encode([
                    'saved' => false,
                    'name' => __('The graph was saved since you loaded it.'),
                    'message' => __('The graph was saved since you loaded it.'),
                    'url' => $this->request->here,
                    'revision' => $result['revision'],
                ]));
                return $this->response;
            case 'invalid':
                return $this->RestResponse->saveFailResponse('AnalystGraphs', $this->request->params['action'], $graph['Graph']['id'], $result['errors'], 'json');
        }
        return $this->RestResponse->viewData([
            'saved' => true,
            'uuid' => $graph['Graph']['uuid'],
            'revision' => $result['revision'],
            'node_count' => $result['node_count'],
            'content_size' => $result['content_size'],
            'modified' => $result['modified'],
        ], 'json');
    }
}
