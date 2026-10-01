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
