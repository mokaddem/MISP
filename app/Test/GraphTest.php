<?php
/**
 * Analyst graphs: the document validator and its caps, edit rights, the
 * audit summary, and graphs staying out of embedded analyst data.
 *
 * Pure PHPUnit, no CakePHP bootstrap and no database, as the other tests
 * under app/Test/. The framework classes touched are stubbed below, each
 * guarded so that a full-suite run sharing one process keeps whichever stub
 * loaded first.
 */

require_once __DIR__ . '/../Vendor/autoload.php';

use PHPUnit\Framework\TestCase;

if (!class_exists('App', false)) {
    class App
    {
        public static function uses($class, $package)
        {
        }
    }
}

if (!class_exists('Configure', false)) {
    class Configure
    {
        private static $values = array();

        public static function read($key)
        {
            return isset(self::$values[$key]) ? self::$values[$key] : null;
        }

        public static function check($key)
        {
            return isset(self::$values[$key]);
        }

        public static function write($key, $value)
        {
            self::$values[$key] = $value;
        }
    }
}

if (!class_exists('ClassRegistry', false)) {
    class ClassRegistry
    {
        public static $instances = array();

        public static function init($name)
        {
            if (!isset(self::$instances[$name])) {
                throw new RuntimeException("No fake registered for $name.");
            }
            return self::$instances[$name];
        }
    }
}

if (!function_exists('__')) {
    function __($string)
    {
        $args = func_get_args();
        $format = array_shift($args);
        return empty($args) ? $format : vsprintf($format, $args);
    }
}

if (!class_exists('AppModel', false)) {
    class AppModel
    {
        public $id = false;
        public $data = array();
        public $validationErrors = array();
    }
}

if (!class_exists('Model', false)) {
    class Model
    {
    }
}

if (!class_exists('ModelBehavior', false)) {
    class ModelBehavior
    {
        public function __construct()
        {
        }
    }
}

if (!class_exists('AuditLog', false)) {
    class AuditLog
    {
        const ACTION_ADD = 'add';
        const ACTION_EDIT = 'edit';
        const ACTION_SOFT_DELETE = 'soft_delete';
        const ACTION_UNDELETE = 'undelete';
        const ACTION_DELETE = 'delete';
    }
}

require_once __DIR__ . '/../Model/Value.php';
require_once __DIR__ . '/../Lib/Tools/AnalystGraphDocumentTool.php';
require_once __DIR__ . '/../Model/AnalystData.php';
require_once __DIR__ . '/../Model/Graph.php';
require_once __DIR__ . '/../Model/Behavior/AnalystDataParentBehavior.php';
require_once __DIR__ . '/../Model/Behavior/AuditLogBehavior.php';

class GraphTestGraph extends Graph
{
    public $alias = 'Graph';
    public $name = 'Graph';

    public function __construct()
    {
    }
}

class GraphTestModel extends Model
{
    public $name = 'Graph';
    public $alias = 'Graph';
    public $primaryKey = 'id';
    public $id = 1;
    public $data = array();
    public $includeAnalystDataRecursive = false;
    public $graph;

    public function __construct()
    {
        $this->graph = new GraphTestGraph();
    }

    public function schema($field = false)
    {
        $fields = array();
        foreach (array('id', 'content_size', 'node_count', 'revision') as $name) {
            $fields[$name] = array('type' => 'integer');
        }
        foreach (array('uuid', 'name', 'content') as $name) {
            $fields[$name] = array('type' => 'string');
        }
        return $fields;
    }

    public function auditLogSummary($field, $old, $new)
    {
        $this->graph->data = $this->data;
        return $this->graph->auditLogSummary($field, $old, $new);
    }
}

class GraphTestRecorder
{
    public $calls = array();
    public $fetchRecursive;

    public function fetchForUuids($uuids, $user = null)
    {
        $this->calls[] = $uuids;
        return array();
    }

    public function insert(array $row)
    {
        $this->calls[] = $row;
    }
}

class GraphTest extends TestCase
{
    const ATTRIBUTE = '5D34E7F4-0000-4000-8000-000000000001';
    const EVENT = '5d34e7f4-0000-4000-8000-000000000002';

    private static function node($type, $uuid, array $extra = array())
    {
        return array('type' => $type, 'uuid' => $uuid) + $extra;
    }

    private static function valueNode($value)
    {
        return array('type' => 'Value', 'value' => $value);
    }

    private static function uuid($i)
    {
        return sprintf('00000000-0000-4000-8000-%012d', $i);
    }

    private static function documentWithNodes($count)
    {
        $nodes = array();
        for ($i = 1; $i <= $count; $i++) {
            $nodes[] = self::node('Attribute', self::uuid($i));
        }
        return array('version' => 1, 'nodes' => $nodes);
    }

    private static function stored(array $nodes)
    {
        list($document) = AnalystGraphDocumentTool::normalise(array('nodes' => $nodes));
        return AnalystGraphDocumentTool::encode($document);
    }

    // ----------------------------------------------------------- validation

    public function testDocumentIsNormalised()
    {
        list($document, $errors) = AnalystGraphDocumentTool::normalise(array(
            'nodes' => array(
                self::node('Attribute', self::ATTRIBUTE, array('x' => 120, 'y' => -40.5, 'pinned' => true, 'label' => 'dropped')),
                array('type' => 'Value', 'value' => ' 8.8.8.8 ', 'uuid' => self::EVENT),
            ),
            'hidden_edges' => array('relationship:a', 'relationship:a'),
            'view' => array('layout' => 'force', 'zoom' => 1.5, 'center' => array(0, 0), 'theme' => 'dropped'),
            'extra' => 'dropped',
        ));

        $this->assertSame(array(), $errors);
        $this->assertSame(array(
            'version' => 1,
            'nodes' => array(
                array('type' => 'Attribute', 'uuid' => strtolower(self::ATTRIBUTE), 'x' => 120, 'y' => -40.5, 'pinned' => true),
                array('type' => 'Value', 'uuid' => Value::uuidFor('8.8.8.8'), 'value' => '8.8.8.8'),
            ),
            'hidden_edges' => array('relationship:a'),
            'view' => array('layout' => 'force', 'zoom' => 1.5, 'center' => array(0, 0)),
        ), $document);
    }

    public function testJsonStringIsAccepted()
    {
        list($document, $errors) = AnalystGraphDocumentTool::normalise('{"version":1,"nodes":[{"type":"Event","uuid":"' . self::EVENT . '"}]}');
        $this->assertSame(array(), $errors);
        $this->assertSame(self::EVENT, $document['nodes'][0]['uuid']);
    }

    public function testEmptyDocumentEncodesViewAsAnObject()
    {
        $this->assertSame(
            '{"version":1,"nodes":[],"hidden_edges":[],"view":{}}',
            AnalystGraphDocumentTool::encode(AnalystGraphDocumentTool::emptyDocument())
        );
    }

    /**
     * @dataProvider invalidDocuments
     */
    public function testInvalidDocumentIsRefused($content)
    {
        list($document, $errors) = AnalystGraphDocumentTool::normalise($content);
        $this->assertNull($document);
        $this->assertNotEmpty($errors);
    }

    public static function invalidDocuments()
    {
        return array(
            'not JSON' => array('{"nodes": ['),
            'not an object' => array(42),
            'unknown version' => array(array('version' => 2)),
            'nodes not a list' => array(array('nodes' => array('a' => self::node('Event', self::EVENT)))),
            'unknown node type' => array(array('nodes' => array(self::node('Tag', self::EVENT)))),
            'bad uuid' => array(array('nodes' => array(self::node('Event', 'not-a-uuid')))),
            'duplicate node' => array(array('nodes' => array(self::node('Event', self::EVENT), self::node('Event', strtoupper(self::EVENT))))),
            'duplicate value' => array(array('nodes' => array(self::valueNode('8.8.8.8'), self::valueNode(' 8.8.8.8')))),
            'empty value' => array(array('nodes' => array(self::valueNode('  ')))),
            'oversized value' => array(array('nodes' => array(self::valueNode(str_repeat('a', 1025))))),
            'non-numeric position' => array(array('nodes' => array(self::node('Event', self::EVENT, array('x' => '12'))))),
            'hidden edge not a string' => array(array('hidden_edges' => array(array('id' => 1)))),
            'view as a list' => array(array('view' => array(1, 2))),
            'bad zoom' => array(array('view' => array('zoom' => 0))),
        );
    }

    public function testSameUuidUnderTwoTypesIsNotADuplicate()
    {
        list(, $errors) = AnalystGraphDocumentTool::normalise(array(
            'nodes' => array(self::node('Event', self::EVENT), self::node('Attribute', self::EVENT)),
        ));
        $this->assertSame(array(), $errors);
    }

    public function testValueAtTheByteCapIsAccepted()
    {
        list(, $errors) = AnalystGraphDocumentTool::normalise(array('nodes' => array(self::valueNode(str_repeat('a', 1024)))));
        $this->assertSame(array(), $errors);
    }

    public function testErrorsNameTheNode()
    {
        list(, $errors) = AnalystGraphDocumentTool::normalise(array(
            'nodes' => array(self::node('Event', self::EVENT), self::node('Tag', self::EVENT)),
        ));
        $this->assertSame(array('nodes[1]: unknown node type.'), $errors);
    }

    // ----------------------------------------------------------------- caps

    public function testNodeCap()
    {
        list(, $errors) = AnalystGraphDocumentTool::normalise(self::documentWithNodes(2000));
        $this->assertSame(array(), $errors);

        list($document, $errors) = AnalystGraphDocumentTool::normalise(self::documentWithNodes(2001));
        $this->assertNull($document);
        $this->assertSame(array('A graph holds at most 2000 nodes.'), $errors);
    }

    public function testByteCap()
    {
        $edges = array();
        for ($i = 0; $i < 66000; $i++) {
            $edges[] = str_pad('relationship:' . $i, 255, 'x');
        }
        list($document, $errors) = AnalystGraphDocumentTool::normalise(array('hidden_edges' => $edges));
        $this->assertNull($document);
        $this->assertSame(array('A graph document is at most 16777216 bytes.'), $errors);
    }

    public function testMeasure()
    {
        $content = self::stored(array(self::node('Event', self::EVENT), self::valueNode('8.8.8.8')));
        $this->assertSame(
            array('content_size' => strlen($content), 'node_count' => 2),
            AnalystGraphDocumentTool::measure($content)
        );
    }

    // ---------------------------------------------------------- model glue

    public function testModelStoresTheNormalisedDocument()
    {
        $graph = new GraphTestGraph();
        $graph->data = array('Graph' => array('content' => array('nodes' => array(self::node('Attribute', self::ATTRIBUTE)))));

        $this->assertTrue($graph->validDocument(array('content' => $graph->data['Graph']['content'])));
        $this->assertSame(
            '{"version":1,"nodes":[{"type":"Attribute","uuid":"' . strtolower(self::ATTRIBUTE) . '"}],"hidden_edges":[],"view":{}}',
            $graph->data['Graph']['content']
        );
    }

    public function testModelReportsDocumentErrors()
    {
        $graph = new GraphTestGraph();
        $result = $graph->validDocument(array('content' => array('nodes' => array(self::node('Tag', self::EVENT)))));
        $this->assertSame('nodes[0]: unknown node type.', $result);
    }

    public function testGraphIsAnAnalystDataTypeButNotEmbedded()
    {
        $this->assertContains('Graph', AnalystData::TYPES);
        $this->assertNotContains('Graph', AnalystData::ANALYST_DATA_TYPES);
        $this->assertContains('Graph', AnalystData::valid_targets);
        $this->assertContains('Value', AnalystData::valid_targets);
        $this->assertSame(AnalystData::GRAPH, (new GraphTestGraph())->current_type_id);
    }

    public function testGraphsHaveNoLanguage()
    {
        $this->assertNotContains('language', (new GraphTestGraph())->getEditableFields());
    }

    public function testDistributionIsRequiredOnCreateOnly()
    {
        $this->assertSame('create', (new GraphTestGraph())->validate['distribution']['required']);
    }

    // ---------------------------------------------------------- edit rights

    private static function user($orgUuid, $siteAdmin = false, $permAnalystData = true)
    {
        return array(
            'Role' => array('perm_site_admin' => $siteAdmin, 'perm_analyst_data' => $permAnalystData),
            'Organisation' => array('uuid' => $orgUuid),
        );
    }

    /**
     * @dataProvider editRights
     */
    public function testEditRights(array $user, $expected)
    {
        $graph = new GraphTestGraph();
        $record = array('Graph' => array('orgc_uuid' => self::uuid(1)));
        $this->assertSame($expected, $graph->canEditAnalystData($user, $record, 'Graph'));
    }

    public static function editRights()
    {
        return array(
            'creator org' => array(self::user(self::uuid(1)), true),
            'creator org without analyst-data permission' => array(self::user(self::uuid(1), false, false), false),
            'other org' => array(self::user(self::uuid(2)), false),
            'site admin of another org' => array(self::user(self::uuid(2), true, false), true),
        );
    }

    // -------------------------------------------------------- audit summary

    public function testAuditSummaryOfAnEdit()
    {
        $old = self::stored(array(self::node('Event', self::EVENT), self::node('Attribute', self::uuid(1))));
        $new = self::stored(array(self::node('Event', self::EVENT), self::node('Attribute', self::uuid(2)), self::valueNode('8.8.8.8')));

        $this->assertSame(array(
            'node_count' => array(2, 3),
            'content_size' => array(strlen($old), strlen($new)),
            'nodes_added_count' => 2,
            'nodes_added' => array('Attribute:' . self::uuid(2), 'Value:' . Value::uuidFor('8.8.8.8')),
            'nodes_removed_count' => 1,
            'nodes_removed' => array('Attribute:' . self::uuid(1)),
        ), AnalystGraphDocumentTool::summariseChange($old, $new));
    }

    public function testAuditSummaryWithoutAPreviousDocument()
    {
        $content = self::stored(array(self::node('Event', self::EVENT)));
        $this->assertSame(
            array('node_count' => 1, 'content_size' => strlen($content)),
            AnalystGraphDocumentTool::summariseChange(null, $content)
        );
    }

    public function testAuditSummaryListsAreCapped()
    {
        $new = AnalystGraphDocumentTool::encode(self::documentWithNodes(150) + array('hidden_edges' => array(), 'view' => array()));
        $summary = AnalystGraphDocumentTool::summariseChange(self::stored(array()), $new);
        $this->assertSame(150, $summary['nodes_added_count']);
        $this->assertCount(100, $summary['nodes_added']);
    }

    public function testModelSummarisesOnlyTheContentAndAddsTheRevision()
    {
        $graph = new GraphTestGraph();
        $graph->data = array('Graph' => array('revision' => 4));
        $content = self::stored(array(self::node('Event', self::EVENT)));

        $this->assertNull($graph->auditLogSummary('name', 'old', 'new'));
        $this->assertSame(
            array('revision' => 4, 'node_count' => 1, 'content_size' => strlen($content)),
            $graph->auditLogSummary('content', null, $content)
        );
    }

    public function testAuditLogRecordsTheSummaryInPlaceOfTheDocument()
    {
        Configure::write('MISP.log_new_audit', true);
        $recorder = new GraphTestRecorder();
        ClassRegistry::$instances['AuditLog'] = $recorder;

        $old = self::stored(array(self::node('Event', self::EVENT)));
        $new = self::stored(array(self::node('Event', self::EVENT), self::valueNode('8.8.8.8')));
        $model = new GraphTestModel();
        $model->data = array('Graph' => array('id' => 1, 'name' => 'Campaign', 'content' => $new, 'revision' => 3));

        $behavior = new AuditLogBehavior();
        $behavior->setup($model);
        $beforeSave = new ReflectionProperty(AuditLogBehavior::class, 'beforeSave');
        $beforeSave->setAccessible(true);
        $beforeSave->setValue($behavior, array('Graph' => array('id' => 1, 'name' => 'Campaign', 'content' => $old, 'revision' => 2)));

        $behavior->afterSave($model, false, array('fieldList' => array()));

        $this->assertCount(1, $recorder->calls);
        $row = $recorder->calls[0];
        $this->assertSame('Graph', $row['model']);
        $this->assertSame('Campaign', $row['model_title']);
        $this->assertSame(array('content'), array_keys($row['change']));
        $this->assertSame(3, $row['change']['content']['revision']);
        $this->assertSame(array(1, 2), $row['change']['content']['node_count']);
        $this->assertSame(array('Value:' . Value::uuidFor('8.8.8.8')), $row['change']['content']['nodes_added']);
    }

    // ----------------------------------------------------------- embed skip

    private function registerEmbedFakes()
    {
        $fakes = array();
        foreach (array('Note', 'Opinion', 'Relationship', 'Graph', 'User') as $type) {
            $fakes[$type] = new GraphTestRecorder();
            ClassRegistry::$instances[$type] = $fakes[$type];
        }
        return $fakes;
    }

    public function testBulkFetchNeverLoadsGraphs()
    {
        $fakes = $this->registerEmbedFakes();
        $behavior = new AnalystDataParentBehavior();

        $behavior->fetchAnalystDataBulk(new GraphTestModel(), array(self::EVENT), array('Note', 'Graph'));

        $this->assertCount(1, $fakes['Note']->calls);
        $this->assertSame(array(), $fakes['Graph']->calls);
    }

    public function testBulkAttachNeverLoadsGraphs()
    {
        $fakes = $this->registerEmbedFakes();
        $behavior = new AnalystDataParentBehavior();

        $behavior->attachAnalystDataBulk(new GraphTestModel(), array(array('uuid' => self::EVENT)), array('Graph', 'Opinion'));

        $this->assertCount(1, $fakes['Opinion']->calls);
        $this->assertSame(array(), $fakes['Graph']->calls);
    }
}
