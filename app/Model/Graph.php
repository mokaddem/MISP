<?php
App::uses('AppModel', 'Model');
App::uses('AnalystData', 'Model');
App::uses('AnalystGraphDocumentTool', 'Tools');

/**
 * An analyst graph: analyst data attached to one record, whose content is a
 * document of the records it shows and their layout.
 */
class Graph extends AnalystData
{
    public $useTable = 'analyst_graphs';

    public $recursive = -1;

    public $actsAs = array(
        'AuditLog',
        'Containable',
        'AnalystData',
    );

    public $current_type = 'Graph';
    public $current_type_id = 3;

    const VALID_TARGETS = [
        'Collection',
        'Event',
        'GalaxyCluster',
    ];

    public const EDITABLE_FIELDS = [
        'name',
        'description',
    ];

    public const SEARCHABLE_FIELDS = [
        'name',
        'description',
    ];

    public $childValidate = [
        'name' => [
            'notBlank' => [
                'rule' => 'notBlank',
                'message' => 'A graph needs a name.',
                'required' => 'create',
            ],
            'maxLength' => [
                'rule' => ['maxLength', 191],
                'message' => 'The name is at most 191 characters.',
            ],
        ],
        'object_type' => [
            'validTarget' => [
                'rule' => ['inList', self::VALID_TARGETS],
                'message' => 'A graph attaches to a collection, an event or a galaxy cluster.',
                'required' => 'create',
            ],
        ],
        'content' => [
            'validDocument' => [
                'rule' => 'validDocument',
            ],
        ],
    ];

    public function getEditableFields(): array
    {
        return array_values(array_diff(parent::getEditableFields(), ['language']));
    }

    public function beforeValidate($options = array())
    {
        parent::beforeValidate($options);
        if (empty($this->id) && empty($this->data[$this->alias]['id']) && !isset($this->data[$this->alias]['content'])) {
            $this->data[$this->alias]['content'] = AnalystGraphDocumentTool::emptyDocument();
        }
        return true;
    }

    /**
     * Validates the document and stores it in its normalised, encoded form.
     *
     * @param array $check
     * @return bool|string
     */
    public function validDocument(array $check)
    {
        list($document, $errors) = AnalystGraphDocumentTool::normalise($check['content']);
        if (!empty($errors)) {
            return implode(' ', array_slice($errors, 0, 10));
        }
        $this->data[$this->alias]['content'] = AnalystGraphDocumentTool::encode($document);
        return true;
    }

    public function beforeSave($options = [])
    {
        parent::beforeSave($options);
        $data = &$this->data[$this->alias];
        if (isset($data['content'])) {
            $data = AnalystGraphDocumentTool::measure($data['content']) + $data;
        }
        $id = $data['id'] ?? $this->id;
        if (empty($id)) {
            $data['revision'] = 1;
        } else {
            $stored = $this->find('first', [
                'conditions' => [$this->alias . '.id' => $id],
                'fields' => [$this->alias . '.revision'],
                'recursive' => -1,
                'callbacks' => false,
            ]);
            $data['revision'] = (int)($stored[$this->alias]['revision'] ?? 0) + 1;
        }
        // A save restricted to a field list still writes the derived columns
        if (!empty($this->whitelist)) {
            $this->whitelist = array_merge($this->whitelist, ['revision', 'content_size', 'node_count']);
        }
        return true;
    }

    /**
     * Replace a graph's document under a row lock. With $baseRevision, a graph
     * saved since that revision is left untouched and the result is a conflict.
     *
     * @param int $id
     * @param callable $change Given the stored document, returns the new one
     * @param int|null $baseRevision
     * @return array status (saved, conflict, invalid or missing), revision,
     *               errors; once saved also node_count, content_size, modified
     * @throws Exception
     */
    public function writeContent($id, callable $change, $baseRevision = null)
    {
        $db = $this->getDataSource();
        $db->begin();
        try {
            $rows = $db->fetchAll(
                sprintf(
                    'SELECT %s, %s FROM %s WHERE %s = ? FOR UPDATE',
                    $db->name('revision'),
                    $db->name('content'),
                    $db->fullTableName($this),
                    $db->name('id')
                ),
                [(int)$id],
                ['cache' => false]
            );
            if (empty($rows)) {
                $db->rollback();
                return ['status' => 'missing', 'revision' => null, 'errors' => []];
            }
            $stored = array_merge(...array_values($rows[0]));
            $revision = (int)$stored['revision'];
            if ($baseRevision !== null && (int)$baseRevision !== $revision) {
                $db->rollback();
                return ['status' => 'conflict', 'revision' => $revision, 'errors' => []];
            }
            $document = json_decode($stored['content'], true);
            $content = $change(is_array($document) ? $document : AnalystGraphDocumentTool::emptyDocument());
            $this->create(false);
            $saved = $this->save(
                [$this->alias => [
                    'id' => (int)$id,
                    'content' => $content,
                    'modified' => date('Y-m-d H:i:s'),
                ]],
                ['fieldList' => ['content', 'modified']]
            );
            if (!$saved) {
                $db->rollback();
                return ['status' => 'invalid', 'revision' => $revision, 'errors' => $this->validationErrors];
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        $saved = $saved[$this->alias];
        return [
            'status' => 'saved',
            'revision' => (int)$saved['revision'],
            'node_count' => (int)$saved['node_count'],
            'content_size' => (int)$saved['content_size'],
            'modified' => $saved['modified'],
            'errors' => [],
        ];
    }

    /**
     * Editing also needs the analyst-data permission, so `_canEdit` tells a
     * client whether to offer saving or forking.
     */
    public function canEditAnalystData(array $user, array $analystData, $modelType): bool
    {
        if (!parent::canEditAnalystData($user, $analystData, $modelType)) {
            return false;
        }
        return !empty($user['Role']['perm_site_admin']) || !empty($user['Role']['perm_analyst_data']);
    }

    /**
     * Read by AuditLogBehavior: the document is logged as a summary.
     *
     * @param string $field
     * @param mixed $old
     * @param mixed $new
     * @return array|null
     */
    public function auditLogSummary($field, $old, $new)
    {
        if ($field !== 'content') {
            return null;
        }
        $summary = AnalystGraphDocumentTool::summariseChange($old, $new);
        if (isset($this->data[$this->alias]['revision'])) {
            $summary = ['revision' => (int)$this->data[$this->alias]['revision']] + $summary;
        }
        return $summary;
    }
}
