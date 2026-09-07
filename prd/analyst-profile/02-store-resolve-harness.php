<?php
/**
 * Phase 1's exit criterion, without a database.
 *
 * `resolveFor()` must return exactly one profile for every user on the
 * instance — including a user whose org has no profile and who has none
 * themselves — and `null` only when a site admin has switched assessment
 * scoring off by disabling the default.
 *
 * The verification in `02-store.md` §7 items 3, 4 and 8 wants a live
 * instance; this covers the same decisions against stubbed rows so the
 * ranking and the ownership rule can be checked before the table exists
 * anywhere. Run: `php prd/analyst-profile/02-store-resolve-harness.php`
 */

define('APP', __DIR__ . '/../../app/');

class App
{
    public static function uses($class, $path)
    {
    }
}

class CakeText
{
    public static function uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

class Validation
{
    public static function uuid($value)
    {
        return (bool)preg_match('/^[0-9a-f]{8}-([0-9a-f]{4}-){3}[0-9a-f]{12}$/i', $value);
    }
}

function __($string)
{
    $args = func_get_args();
    array_shift($args);
    return $args ? vsprintf($string, $args) : $string;
}

class Model
{
    public $alias = 'AnalystProfile';
    public $data = array();
    public $id = null;
    public $validationErrors = array();
    public $queryCount = 0;

    public function find($type, $options = array())
    {
        return array();
    }

    public function invalidate($field, $message = true)
    {
        $this->validationErrors[$field] = $message;
    }

    public function create($data = array())
    {
        $this->data = array();
        $this->id = null;
    }

    public function save($data = null, $validate = true, $fields = array())
    {
        return true;
    }

    public function saveField($name, $value)
    {
        return true;
    }

    public function log($message, $type = LOG_ERR)
    {
    }
}

class AppModel extends Model
{
    public function beforeValidate($options = array())
    {
        return true;
    }
}

require_once APP . 'Model/AnalystProfile.php';

/**
 * Serves fixture rows in place of the database, and counts the statements
 * resolveFor() issues — §3.1 forbids a fallback chain of three, so the count
 * is part of what is being asserted.
 */
class StubbedAnalystProfile extends AnalystProfile
{
    public $rows = array();

    public function find($type, $options = array())
    {
        $this->queryCount++;
        $conditions = isset($options['conditions']) ? $options['conditions'] : array();
        $matched = array();
        foreach ($this->rows as $row) {
            if (isset($conditions['AnalystProfile.enabled'])
                && $row['enabled'] != $conditions['AnalystProfile.enabled']) {
                continue;
            }
            if (isset($conditions['OR'])) {
                $hit = false;
                foreach ($conditions['OR'] as $clause) {
                    foreach ($clause as $field => $value) {
                        $column = substr($field, strlen('AnalystProfile.'));
                        if (isset($row[$column]) && $row[$column] == $value) {
                            $hit = true;
                        }
                    }
                }
                if (!$hit) {
                    continue;
                }
            }
            $matched[] = array('AnalystProfile' => $row);
        }
        if ($type === 'first') {
            return $matched ? $matched[0] : array();
        }
        return $matched;
    }
}

function row($id, $name, $userId, $orgId, $isDefault, $enabled = 1)
{
    return array(
        'id' => $id,
        'name' => $name,
        'user_id' => $userId,
        'org_id' => $orgId,
        'default' => $isDefault,
        'enabled' => $enabled,
        'parameters' => array(),
        'version' => 1,
        'revision' => 1,
    );
}

$failures = 0;
$checks = 0;

function assertSame($expected, $actual, $label)
{
    global $failures, $checks;
    $checks++;
    if ($expected === $actual) {
        printf("  ok   %s\n", $label);
        return;
    }
    $failures++;
    printf(
        "  FAIL %s\n         expected %s, got %s\n",
        $label,
        var_export($expected, true),
        var_export($actual, true)
    );
}

// The instance: a default, one org profile, one user profile.
$populated = array(
    row(1, 'default-v1', null, null, 1),
    row(2, 'CIRCL house style', null, 4, 0),
    row(3, 'sami tuned', 9, null, 0),
);

echo "resolveFor() — nearest owner wins (D3)\n";

// 1. A user with their own profile takes it, over their org's and the default.
$m = new StubbedAnalystProfile();
$m->rows = $populated;
$resolved = $m->resolveFor(array('id' => 9, 'org_id' => 4));
assertSame('sami tuned', $resolved['name'], 'own profile beats org and default');
assertSame(1, $m->queryCount, 'one statement, not a fallback chain');

// 2. A user whose org has one, but who has none.
$m = new StubbedAnalystProfile();
$m->rows = $populated;
$resolved = $m->resolveFor(array('id' => 11, 'org_id' => 4));
assertSame('CIRCL house style', $resolved['name'], "org profile when the user has none");

// 3. A user with neither — the case the exit criterion names.
$m = new StubbedAnalystProfile();
$m->rows = $populated;
$resolved = $m->resolveFor(array('id' => 12, 'org_id' => 7));
assertSame('default-v1', $resolved['name'], 'the default when neither exists');

// 4. Every user on the instance resolves to exactly one profile.
$m = new StubbedAnalystProfile();
$m->rows = $populated;
$everyone = array(
    array('id' => 9, 'org_id' => 4),
    array('id' => 11, 'org_id' => 4),
    array('id' => 12, 'org_id' => 7),
    array('id' => 1, 'org_id' => 1),
);
$resolvedAll = true;
foreach ($everyone as $user) {
    if (empty($m->resolveFor($user)['name'])) {
        $resolvedAll = false;
    }
}
assertSame(true, $resolvedAll, 'no user resolves to nothing while a default is enabled');

// 5. A disabled profile is not in force — resolution falls to the next owner.
$m = new StubbedAnalystProfile();
$m->rows = array(
    row(1, 'default-v1', null, null, 1),
    row(3, 'sami tuned', 9, null, 0, 0),
);
$resolved = $m->resolveFor(array('id' => 9, 'org_id' => 4));
assertSame('default-v1', $resolved['name'], 'a disabled own profile falls through');

// 6. The default disabled and nothing else: null, and not an error (§3.1).
$m = new StubbedAnalystProfile();
$m->rows = array(row(1, 'default-v1', null, null, 1, 0));
assertSame(null, $m->resolveFor(array('id' => 9, 'org_id' => 4)), 'null when scoring is switched off');

// 7. Resolution is cached per request — twenty-seven panels, one statement.
$m = new StubbedAnalystProfile();
$m->rows = $populated;
for ($i = 0; $i < 27; $i++) {
    $m->resolveFor(array('id' => 9, 'org_id' => 4));
}
assertSame(1, $m->queryCount, '27 panel calls cost one statement');

// 8. Two viewers, two resolutions — the cache is keyed, not global.
$m = new StubbedAnalystProfile();
$m->rows = $populated;
$a = $m->resolveFor(array('id' => 9, 'org_id' => 4));
$b = $m->resolveFor(array('id' => 12, 'org_id' => 7));
assertSame('sami tuned', $a['name'], 'viewer A');
assertSame('default-v1', $b['name'], 'viewer B is not served A cached row');

echo "\nOwnership triple — exactly one of three (§2.1)\n";

function ownershipAccepted($fields)
{
    $m = new StubbedAnalystProfile();
    $m->data = array('AnalystProfile' => array_merge(
        array('name' => 'p', 'parameters' => array()),
        $fields
    ));
    return $m->beforeValidate() !== false;
}

assertSame(true, ownershipAccepted(array('user_id' => 9)), 'a user profile');
assertSame(true, ownershipAccepted(array('org_id' => 4)), 'an org profile');
assertSame(true, ownershipAccepted(array('default' => 1)), 'the instance default');
assertSame(false, ownershipAccepted(array('user_id' => 9, 'org_id' => 4)), 'user + org rejected');
assertSame(false, ownershipAccepted(array('org_id' => 4, 'default' => 1)), 'org + default rejected');
assertSame(false, ownershipAccepted(array()), 'no owner rejected');

echo "\nisEditableByCurrentUser() — Q7 as decided (§3.3)\n";

$m = new StubbedAnalystProfile();
$analyst = array('id' => 9, 'org_id' => 4, 'Role' => array(
    'perm_site_admin' => 0, 'perm_admin' => 0));
$orgAdmin = array('id' => 10, 'org_id' => 4, 'Role' => array(
    'perm_site_admin' => 0, 'perm_admin' => 1));
$siteAdmin = array('id' => 1, 'org_id' => 1, 'Role' => array(
    'perm_site_admin' => 1, 'perm_admin' => 1));

$default = row(1, 'default-v1', null, null, 1);
$orgProfile = row(2, 'CIRCL house style', null, 4, 0);
$ownProfile = row(3, 'sami tuned', 9, null, 0);
$othersProfile = row(4, 'someone else', 11, null, 0);

assertSame(true, $m->isEditableByCurrentUser($analyst, $ownProfile), 'own profile needs no grant');
assertSame(false, $m->isEditableByCurrentUser($analyst, $othersProfile), "not another user's");
assertSame(false, $m->isEditableByCurrentUser($analyst, $orgProfile), "not the org's without perm_admin");
assertSame(true, $m->isEditableByCurrentUser($orgAdmin, $orgProfile), "the org's with perm_admin");
assertSame(false, $m->isEditableByCurrentUser($analyst, $default), 'the default is not an analyst\'s');
assertSame(false, $m->isEditableByCurrentUser($orgAdmin, $default), 'nor an org admin\'s');
assertSame(true, $m->isEditableByCurrentUser($siteAdmin, $default), 'site admin edits the default');

echo "\nUnparseable parameters reach the engine as broken (§3.1)\n";

$m = new StubbedAnalystProfile();
$found = $m->afterFind(array(
    array('AnalystProfile' => array('id' => 1, 'parameters' => '{"signals": []}')),
    array('AnalystProfile' => array('id' => 2, 'parameters' => '{not json')),
    array('AnalystProfile' => array('id' => 3, 'parameters' => null)),
));
assertSame(array('signals' => array()), $found[0]['AnalystProfile']['parameters'], 'valid JSON decodes');
assertSame(true, isset($found[1]['AnalystProfile']['parameters_unparseable']), 'broken JSON is flagged');
assertSame(false, isset($found[2]['AnalystProfile']['parameters_unparseable']), 'an empty column is not broken');

echo "\nThe shipped default parses and is inert (§4.1, 08-enrichment.md §2)\n";

$shipped = json_decode(file_get_contents(APP . 'files/analyst-profiles/default-v1.json'), true);
assertSame(true, is_array($shipped), 'default-v1.json parses');
assertSame('default-v1', $shipped['name'], 'named default-v1, not default-v3');
assertSame(true, Validation::uuid($shipped['uuid']), 'carries a uuid');
assertSame(array(), $shipped['parameters']['enrichment']['auto_run'], 'auto_run is empty');
assertSame('local_only', $shipped['parameters']['enrichment']['cost_posture'], 'cost_posture is local_only');
assertSame(true, is_array($shipped['parameters']['signals']), 'has a signals list');
assertSame(array(), $shipped['parameters']['reference']['org_trust'], 'reference maps are empty overrides');

printf("\n%d checks, %d failures\n", $checks, $failures);
exit($failures === 0 ? 0 : 1);
