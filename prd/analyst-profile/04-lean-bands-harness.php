<?php
/**
 * Phase 3's invariants, without a database.
 *
 * The lean is the one part of an assessment that is decided by rules
 * rather than by arithmetic, and rules are exactly what a harness can
 * corner. Every boundary in `04-dispositions.md` §3 is a pair of
 * contexts differing by one organisation, and every one of them is
 * checked here at the boundary rather than near it.
 *
 * Covers §9 items 1, 2, 3, 4, 5, 6 and the lean half of 8, plus the
 * conflict-rule loader's own failure modes — which are the same five
 * rules the signal loader has, against a directory phase 3 adds — and
 * the derived `changers` of §8, which nothing else would catch until a
 * falsifiability line quietly claimed the wrong number.
 *
 * Item 7 is a rendered page in two themes and item 8's band half needs
 * real rows on a real instance; `04-lean-bands-live-probe.php` takes
 * the second and §9 says the first is a before/after.
 *
 * Run: `php prd/analyst-profile/04-lean-bands-harness.php`
 *
 * Stub rules and stub signals go under `app/tmp/value-lean-harness/`
 * and are deleted on the way out, which is also how the *missing
 * directory* case gets checked.
 */

define('APP', __DIR__ . '/../../app/');

class App
{
    public static function uses($class, $path)
    {
    }
}

class CakeLog
{
    public static $lines = array();

    public static function write($level, $message)
    {
        self::$lines[] = $message;
        return true;
    }
}

function __($string)
{
    $args = func_get_args();
    array_shift($args);
    return $args ? vsprintf($string, $args) : $string;
}

function __n($singular, $plural, $count)
{
    return $count === 1 ? $singular : $plural;
}

require_once APP . 'Model/ValueSignals/ValueSignalBase.php';
require_once APP . 'Model/ValueEscalations/ValueEscalationBase.php';
require_once APP . 'Lib/Tools/ValueStatsTool.php';
/*
 * Loaded because the three trust-weighted signals read it
 * (phase 6). Without it they throw and land in `not_counted`,
 * which is the engine's guard working and every number in this
 * file moving.
 */
require_once APP . 'Lib/Tools/ValueTrustTool.php';
/*
 * Loaded because both conflict rules name their category vocabulary
 * from it. `App::uses` is a no-op here, so without this they fail to
 * construct and every escalation check below goes quiet rather than
 * red.
 */
require_once APP . 'Lib/Tools/WarninglistCategory.php';

require_once APP . 'Lib/Tools/ValueSignalLoader.php';
require_once APP . 'Lib/Tools/ValueLeanTool.php';
require_once APP . 'Lib/Tools/ValueChangersTool.php';
/*
 * Loaded because `ValueVerdictTool::verdict()` assembles the relevance
 * axis (phase 5), which every earlier phase's engine call now reaches.
 */
require_once APP . 'Lib/Tools/ValueRelevanceTool.php';
require_once APP . 'Lib/Tools/ValueVerdictTool.php';
require_once APP . 'Lib/Tools/ValueSummaryTool.php';
require_once APP . 'Lib/Tools/ValueLeanReasonTool.php';
require_once APP . 'Lib/Tools/ValueContestedTool.php';

$GLOBALS['checks'] = 0;
$GLOBALS['failures'] = 0;

function out($line = '')
{
    echo $line . PHP_EOL;
}

function is_same($expected, $actual, $label)
{
    $GLOBALS['checks']++;
    if ($expected === $actual) {
        out(sprintf('  ok    %s', $label));
        return true;
    }
    $GLOBALS['failures']++;
    out(sprintf(
        '  FAIL  %s' . PHP_EOL . '        expected %s' . PHP_EOL
            . '        got      %s',
        $label,
        json_encode($expected),
        json_encode($actual)
    ));
    return false;
}

function is_true($actual, $label)
{
    return is_same(true, (bool)$actual, $label);
}

/*
 * ----------------------------------------------------------------------
 * Contexts.
 *
 * The lean reads three things and nothing else: whether there is an
 * occurrence, the per-organisation stance table, and which categories
 * of warninglist matched. So the contexts here are deliberately thin —
 * a context carrying a sighting table would be claiming the derivation
 * looks at one.
 * ----------------------------------------------------------------------
 */

const NOW = 1757232000;

/**
 * A stance table of the requested shape.
 *
 * @param int $threat Organisations holding a `to_ids = 1` occurrence
 * @param int $benign Organisations whose every occurrence is `to_ids = 0`
 * @return array
 */
function orgs($threat, $benign)
{
    $rows = array();
    for ($i = 0; $i < $threat; $i++) {
        $rows[] = array('id' => $i + 1, 'name' => 'threat-' . $i,
            'occurrences' => 1, 'to_ids_yes' => 1, 'to_ids_no' => 0,
            'newest' => NOW);
    }
    for ($i = 0; $i < $benign; $i++) {
        $rows[] = array('id' => 100 + $i, 'name' => 'benign-' . $i,
            'occurrences' => 1, 'to_ids_yes' => 0, 'to_ids_no' => 1,
            'newest' => NOW);
    }
    return $rows;
}

/**
 * A context the lean derivation can read, and nothing more.
 *
 * @param int $threat
 * @param int $benign
 * @param array $categories Warninglist categories that matched
 * @return array
 */
function leanContext($threat, $benign, array $categories = array())
{
    $hits = array();
    foreach ($categories as $index => $category) {
        $hits[] = array('name' => 'list-' . $index,
            'category' => $category, 'matched' => null);
    }
    $orgs = orgs($threat, $benign);
    return array(
        'value' => '198.51.100.24',
        'now' => NOW,
        'as_of' => date('Y-m-d', NOW),
        'types' => array(array('type' => 'ip-dst', 'count' => 1)),
        'occurrences' => array(
            'total' => count($orgs),
            'events' => count($orgs),
            'orgs' => count($orgs),
            'oldest' => NOW,
            'newest' => NOW,
        ),
        'publication' => array('events' => count($orgs),
            'published' => count($orgs), 'unpublished' => 0),
        'temporal' => array('occurrences' => count($orgs),
            'with_first_seen' => count($orgs), 'max_lag_days' => 0),
        'orgs' => $orgs,
        'activity' => array('months' => array(date('Y-m', NOW) => 1),
            'active_months' => 1, 'span_months' => 1,
            'longest_run' => 1),
        'warninglist' => array('hits' => $hits, 'lists_checked' => 84,
            'category' => empty($categories)
                ? null
                : (in_array('false_positive', $categories, true)
                    ? 'false_positive'
                    : $categories[0])),
        'sightings' => array('total' => 0, 'fp' => 0),
        'galaxies' => array('clusters' => array(),
            'techniques' => array()),
        'feeds' => array('count' => 0, 'names' => array(),
            'checked' => 3),
        'budget' => array('window_days' => null, 'hot' => false,
            'occurrences' => count($orgs), 'threshold' => null),
        'excluded' => array(),
        'missing' => array(),
    );
}

function shippedProfile()
{
    $raw = file_get_contents(
        APP . 'files/analyst-profiles/default-v1.json'
    );
    return json_decode($raw, true);
}

/**
 * A profile carrying nothing but the sections one test needs.
 *
 * @param array $sections
 * @return array
 */
function profileOf(array $sections)
{
    return array(
        'id' => 9,
        'name' => 'harness',
        'revision' => 1,
        'parameters' => $sections + array('format' => 1),
    );
}

/** The thresholds the shipped default carries. */
function shippedThresholds()
{
    $profile = shippedProfile();
    return $profile['parameters']['thresholds'];
}

/** The escalation list the shipped default carries. */
function shippedEscalations()
{
    $profile = shippedProfile();
    return $profile['parameters']['escalations'];
}

const HARNESS_SHIPPED = 'tmp/value-lean-harness/shipped/';
const HARNESS_CUSTOM = 'tmp/value-lean-harness/custom/';
const HARNESS_ABSENT = 'tmp/value-lean-harness/nothing-here/';

function useRealRules()
{
    ValueSignalLoader::register('escalation', array(
        'shipped' => 'Model/ValueEscalations/',
        'custom' => 'Lib/ValueEscalations/',
        'base' => 'ValueEscalationBase',
        'skip' => array('ValueEscalationBase.php'),
    ));
}

function useHarnessRules($custom = HARNESS_CUSTOM)
{
    ValueSignalLoader::register('escalation', array(
        'shipped' => HARNESS_SHIPPED,
        'custom' => $custom,
        'base' => 'ValueEscalationBase',
        'skip' => array(),
    ));
}

function useHarnessSignals()
{
    ValueSignalLoader::register('signal', array(
        'shipped' => HARNESS_SHIPPED,
        'custom' => HARNESS_ABSENT,
        'base' => 'ValueSignalBase',
        'skip' => array(),
    ));
}

function useRealSignals()
{
    ValueSignalLoader::register('signal', array(
        'shipped' => 'Model/ValueSignals/',
        'custom' => 'Lib/ValueSignals/',
        'base' => 'ValueSignalBase',
        'skip' => array('ValueSignalBase.php'),
    ));
}

function writeStub($root, $filename, $body)
{
    $dir = APP . $root;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir . $filename, $body);
}

function cleanStubs()
{
    foreach (array(HARNESS_SHIPPED, HARNESS_CUSTOM) as $root) {
        $dir = APP . $root;
        if (!is_dir($dir)) {
            continue;
        }
        foreach (glob($dir . '*') as $file) {
            unlink($file);
        }
        rmdir($dir);
    }
    $parent = APP . 'tmp/value-lean-harness/';
    if (is_dir($parent)) {
        rmdir($parent);
    }
}

/** A rule that always fires, naming itself. */
function stubRuleClass($class, $id, $body = null)
{
    $fires = $body === null
        ? 'return array(\'prose\' => \'' . $id . ' fired\');'
        : $body;
    return '<?php class ' . $class
        . ' extends ValueEscalationBase {'
        . ' public $id = \'' . $id . '\';'
        . ' public $description = \'harness\';'
        . ' public function fires(array $c, array $e) { ' . $fires
        . ' } }';
}

/** A signal returning whatever row its profile entry carries. */
function stubSignalClass($class, $id)
{
    return '<?php class ' . $class . ' extends ValueSignalBase {'
        . ' public $id = \'' . $id . '\';'
        . ' public $group = \'Reporting\';'
        . ' public $description = \'harness\';'
        . ' public function evaluate(array $context, array $config) {'
        . '   $row = $config[\'config\'][\'row\'] ?? null;'
        . '   return $row === null ? null : $row; } }';
}

function ledgerRows(array $verdict)
{
    $rows = array();
    foreach ($verdict['ledger'] as $group) {
        foreach ($group['signals'] as $row) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function ledgerSum(array $verdict)
{
    $total = 0;
    foreach (ledgerRows($verdict) as $row) {
        $total += $row['contribution'];
    }
    return $total;
}

function changerFor(array $verdict, $axis)
{
    foreach ($verdict['changers'] as $changer) {
        if ($changer['axis'] === $axis) {
            return $changer;
        }
    }
    return null;
}

register_shutdown_function('cleanStubs');

/*
 * ======================================================================
 * §9.1 — the lean derivation, at every rule boundary
 * ======================================================================
 */

out('§9.1 — the lean derivation at every rule boundary');
useRealRules();

$lean = new ValueLeanTool();
$rules = profileOf(array(
    'thresholds' => shippedThresholds(),
    'escalations' => shippedEscalations(),
));

// Rule 1, both ways into it.
$nothing = leanContext(0, 0);
is_same(
    'none',
    $lean->leanFor($nothing, $rules)['lean'],
    'rule 1: no occurrence, no lean'
);
$unreadable = leanContext(0, 0);
$unreadable['occurrences']['total'] = 4;
is_same(
    'none',
    $lean->leanFor($unreadable, $rules)['lean'],
    'and occurrences with no readable stance is the same answer'
);

/*
 * Rules 4 and 5, at the boundary rather than near it. 66 of 100 is
 * exactly `lean_supermajority` and the comparison is inclusive; 65 is
 * one organisation short and lands in the split.
 */
is_same(
    'threat',
    $lean->leanFor(leanContext(66, 34), $rules)['lean'],
    'rule 4: a share of exactly 0.66 is decisive'
);
is_same(
    'contested',
    $lean->leanFor(leanContext(65, 35), $rules)['lean'],
    'and 0.65 is not — it is a stance split'
);
is_same(
    'benign',
    $lean->leanFor(leanContext(34, 66), $rules)['lean'],
    'rule 5: the mirror of it, at 0.34'
);
is_same(
    'contested',
    $lean->leanFor(leanContext(35, 65), $rules)['lean'],
    'and 0.35 is the split again'
);
is_same(
    'threat',
    $lean->leanFor(leanContext(1, 0), $rules)['lean'],
    'one organisation asserting, nobody disagreeing: threat'
);
is_same(
    'benign',
    $lean->leanFor(leanContext(0, 1), $rules)['lean'],
    'one organisation holding it without the flag: benign'
);

/*
 * Rule 3, and rule 2 guarding it. A false-positive list beats a
 * minority of threat stances on purpose; once the stances are a
 * supermajority the other way, the conflict rule gets there first.
 */
$listedMinority = leanContext(1, 1, array('false_positive'));
is_same(
    'benign',
    $lean->leanFor($listedMinority, $rules)['lean'],
    'rule 3: a false-positive list beats a 50% threat share'
);
$listedMajority = leanContext(4, 0, array('false_positive'));
$decided = $lean->leanFor($listedMajority, $rules);
is_same(
    'contested',
    $decided['lean'],
    'rule 2 first: the same list against a supermajority is'
        . ' contested'
);
is_same(
    'conflict:listed-vs-asserted',
    $decided['rule']['id'],
    'and the rule that decided it is named'
);
is_true(
    strpos($decided['rule']['prose'], '4 of 4') !== false,
    'with prose a reader can check against the stance table'
);

/*
 * Rule 6 is the fall-through, and a `known` hit alone does not change
 * it — the category means shared infrastructure, not a false positive.
 */
$known = leanContext(1, 1, array('known'));
is_same(
    'contested',
    $lean->leanFor($known, $rules)['lean'],
    'rule 6: a known hit does not rescue a stance split'
);
is_same(
    null,
    $lean->leanFor($known, $rules)['rule'],
    'and it is a split, not a named contradiction'
);

out('');
out('§9.4 — the two shipped conflict rules, at their own boundaries');

/*
 * The known-infrastructure rule fires at exactly three reporting
 * organisations and not at two, which is the difference between a
 * pattern and one organisation making a mistake in one event.
 */
$threeReports = leanContext(3, 0, array('known'));
$decided = $lean->leanFor($threeReports, $rules);
is_same(
    'contested',
    $decided['lean'],
    'the known rule fires at exactly 3 reporting organisations'
);
is_same(
    'conflict:known-infrastructure-vs-reporting',
    $decided['rule']['id'],
    'and names itself'
);
$twoReports = leanContext(2, 0, array('known'));
$decided = $lean->leanFor($twoReports, $rules);
is_same(
    'threat',
    $decided['lean'],
    'at 2 it does not fire, and the stances decide instead'
);

/*
 * Both rules true of one value. List order decides, and the meta line
 * names the one that did — so reversing the profile's own list changes
 * the answer's provenance and nothing else.
 */
$both = leanContext(4, 0, array('known', 'false_positive'));
$shippedOrder = $lean->leanFor($both, $rules);
is_same(
    'conflict:known-infrastructure-vs-reporting',
    $shippedOrder['rule']['id'],
    'both rules firing: the first in the profile\'s list decides'
);
$reversed = shippedEscalations();
$reversed = array_reverse($reversed);
$reversedProfile = profileOf(array(
    'thresholds' => shippedThresholds(),
    'escalations' => $reversed,
));
is_same(
    'conflict:listed-vs-asserted',
    $lean->leanFor($both, $reversedProfile)['rule']['id'],
    'and reversing the list changes which one is named'
);
is_same(
    'contested',
    $lean->leanFor($both, $reversedProfile)['lean'],
    'the lean itself being the same either way'
);

/*
 * A rule turned off is not a rule that could not run. It emits
 * nothing, reports nothing, and lets the counting rules decide — which
 * is the whole point of the section being editable.
 */
$off = shippedEscalations();
$off[0]['enabled'] = false;
$off[1]['enabled'] = false;
$disabled = $lean->leanFor(
    $threeReports,
    profileOf(array('thresholds' => shippedThresholds(),
        'escalations' => $off))
);
is_same(
    'threat',
    $disabled['lean'],
    'both rules disabled: the stances decide'
);
is_same(
    array(),
    $disabled['rule_errors'],
    'and a disabled rule is not reported as a problem'
);

/*
 * A rule whose evidence could not be read does not fire on the gap.
 * The warninglist check failing is not the same as nothing matching.
 */
$blind = $threeReports;
$blind['missing']['warninglist'] = 'The warninglist cache is empty.';
is_same(
    'threat',
    $lean->leanFor($blind, $rules)['lean'],
    'a rule whose evidence is unreadable stays quiet'
);

out('');
out('§4 — a conflict rule that could not run is reported, not hidden');

$ghost = profileOf(array(
    'thresholds' => shippedThresholds(),
    'escalations' => array(
        array('id' => 'conflict:not-on-this-instance',
            'enabled' => true, 'emits' => 'contested'),
    ),
));
$answer = $lean->leanFor($threeReports, $ghost);
is_same(
    'threat',
    $answer['lean'],
    'a rule this instance does not have leaves the lean unescalated'
);
is_same(
    1,
    count($answer['rule_errors']),
    'and says so, because nothing else on the page would'
);
is_same(
    'conflict:not-on-this-instance',
    $answer['rule_errors'][0]['id'],
    'naming the id the profile weighted'
);

$illegal = profileOf(array(
    'thresholds' => shippedThresholds(),
    'escalations' => array(
        array('id' => 'conflict:known-infrastructure-vs-reporting',
            'enabled' => true, 'emits' => 'benign'),
    ),
));
$answer = $lean->leanFor($threeReports, $illegal);
is_same(
    'threat',
    $answer['lean'],
    'a rule asked to emit a side does not run'
);
is_same(
    1,
    count($answer['rule_errors']),
    'and is reported rather than silently emitting contested'
);

/*
 * ======================================================================
 * The loader's five rules, against the directory phase 3 adds
 * ======================================================================
 */

out('');
out('§4 — the conflict-rule directory obeys the loader\'s five rules');

ValueSignalLoader::forget();
useHarnessRules(HARNESS_ABSENT);
writeStub(HARNESS_SHIPPED, 'AlwaysFires.php',
    stubRuleClass('AlwaysFires', 'conflict:always'));
ValueSignalLoader::forget('escalation');

is_same(
    array(),
    ValueSignalLoader::errors('escalation'),
    'a missing custom directory is silence, not a failure'
);
is_true(
    ValueSignalLoader::get('conflict:always', 'escalation') !== null,
    'and the shipped root still loads'
);

$stubProfile = profileOf(array(
    'thresholds' => shippedThresholds(),
    'escalations' => array(),
));
is_same(
    'threat',
    $lean->leanFor(leanContext(3, 0), $stubProfile)['lean'],
    'discovery is not activation: a rule no profile lists changes'
        . ' nothing'
);
$listed = profileOf(array(
    'thresholds' => shippedThresholds(),
    'escalations' => array(
        array('id' => 'conflict:always', 'enabled' => true),
    ),
));
is_same(
    'contested',
    $lean->leanFor(leanContext(3, 0), $listed)['lean'],
    'and listing it in a profile is what makes it run'
);

// Rule 2 — a colliding id is refused, and the shipped file keeps it.
useHarnessRules();
writeStub(HARNESS_CUSTOM, 'Collides.php',
    stubRuleClass('Collides', 'conflict:always',
        'return array(\'prose\' => \'the custom one\');'));
ValueSignalLoader::forget('escalation');
$errors = ValueSignalLoader::errors('escalation');
is_same(
    1,
    count($errors),
    'a custom file claiming a shipped id is refused'
);
is_true(
    isset($errors['Collides.php']),
    'and the file that lost is the one named'
);
$decided = $lean->leanFor(leanContext(3, 0), $listed);
is_same(
    'conflict:always fired',
    $decided['rule']['prose'],
    'the shipped implementation keeps the id'
);

// Rule 3 — a broken file is an honest state, never a fatal.
writeStub(HARNESS_CUSTOM, 'Broken.php', '<?php this is not php');
writeStub(HARNESS_CUSTOM, 'Throws.php',
    stubRuleClass('Throws', 'conflict:throws',
        'throw new RuntimeException(\'boom\');'));
ValueSignalLoader::forget('escalation');
$throwing = profileOf(array(
    'thresholds' => shippedThresholds(),
    'escalations' => array(
        array('id' => 'conflict:throws', 'enabled' => true),
        array('id' => 'conflict:always', 'enabled' => true),
    ),
));
$decided = $lean->leanFor(leanContext(3, 0), $throwing);
is_same(
    'contested',
    $decided['lean'],
    'a rule that throws does not take the page down'
);
is_same(
    'conflict:always',
    $decided['rule']['id'],
    'and the next rule in the list still gets to decide'
);
is_true(
    count(ValueSignalLoader::errors('escalation')) >= 2,
    'the file that would not parse is kept for the admin'
);
is_true(
    !empty(CakeLog::$lines),
    'and logged where an admin will find it'
);

/*
 * ======================================================================
 * §9.2 — re-anchoring, against the fixture's ledgers as literal input
 * ======================================================================
 */

out('');
out('§9.2 — re-anchoring against the fixture ledgers');

/*
 * The fixture's rows are what the page already renders, so they are
 * the ground truth for the anchoring and need no database. The
 * malicious value's rows were authored under a threat lean, which is
 * polarity +1, so they are already threat-signed. The benign value's
 * were authored under a benign lean, so their threat-signed form is
 * their negation — and feeding *that* back under a benign lean has to
 * reproduce the fixture row for row.
 */
$maliciousRows = array(28, 9, 24, -6, 14, 5, 12, -8, 6);
$benignRows = array(-11, 13, 26, -4, 7, 38, 16, 6);

/**
 * A profile whose stub signals emit exactly the contributions given.
 *
 * @param array $contributions Threat-signed
 * @return array
 */
function ledgerProfile(array $contributions)
{
    $signals = array();
    foreach ($contributions as $index => $points) {
        $signals[] = array(
            'id' => 'harness.row' . $index,
            'group' => 'Reporting',
            'enabled' => true,
            'config' => array('row' => array(
                'signal' => 'row ' . $index,
                'contribution' => $points,
            )),
        );
    }
    return profileOf(array(
        'thresholds' => shippedThresholds(),
        'escalations' => array(),
        'signals' => $signals,
    ));
}

useHarnessSignals();
foreach (array_keys($maliciousRows) as $index) {
    writeStub(HARNESS_SHIPPED, 'HarnessRow' . $index . '.php',
        stubSignalClass('HarnessRow' . $index, 'harness.row' . $index));
}
ValueSignalLoader::forget('signal');

$tool = new ValueVerdictTool();
$context = leanContext(3, 0);

$scored = $tool->assess($context, ledgerProfile($maliciousRows),
    array('lean' => 'threat'));
is_same(
    84,
    $scored['quality'],
    'the malicious ledger is unchanged under a threat lean: +84'
);
is_same(
    $scored['quality'],
    ledgerSum($scored),
    'and the rows still sum to it exactly'
);
$rows = ledgerRows($scored);
is_same(
    $maliciousRows,
    array_map(function ($row) {
        return $row['contribution'];
    }, $rows),
    'every row as the fixture authored it, in order'
);
is_same(
    array('up', 'up', 'up', 'down', 'up', 'up', 'up', 'down', 'up'),
    array_map(function ($row) {
        return $row['direction'];
    }, $rows),
    'and every direction, which is the sign and nothing else'
);

/*
 * The benign case. Threat-signed input is the fixture's negation;
 * anchoring to a benign lean flips it back, and the sum is the +91 the
 * page already shows.
 */
$threatSigned = array_map(function ($points) {
    return -$points;
}, $benignRows);
$scored = $tool->assess($context, ledgerProfile($threatSigned),
    array('lean' => 'benign'));
is_same(
    91,
    $scored['quality'],
    'the benign ledger anchors to +91, the number on the page'
);
is_same(
    $scored['quality'],
    ledgerSum($scored),
    'summing exactly there too'
);
$rows = ledgerRows($scored);
is_same(
    $benignRows,
    array_map(function ($row) {
        return $row['contribution'];
    }, $rows),
    'every row back to the fixture\'s own value'
);
is_same(
    'down',
    $rows[0]['direction'],
    'wide reporting disputes a benign lean'
);
is_same(
    'up',
    $rows[5]['direction'],
    'and the warninglist hit supports it'
);

/*
 * ======================================================================
 * §9.3 and §9.5 — rule 7, and the tug it produces
 * ======================================================================
 */

out('');
out('§9.3 — a ledger that disputes its own lean');

$disputing = $tool->assess(
    $context,
    ledgerProfile(array(-20, 6, -9)),
    array('lean' => 'threat')
);
is_same(
    'contested',
    $disputing['lean'],
    'rule 7: a threat lean summing below zero is contested'
);
is_same(
    'threat',
    $disputing['derived_lean'],
    'with the lean it was scored against still on the record'
);
is_same(
    -23,
    $disputing['quality'],
    'and the quality stays negative — a contested ledger is'
        . ' threat-signed, so there is nothing to flip'
);
is_same(
    $disputing['quality'],
    ledgerSum($disputing),
    'the exact-sum invariant holding through the re-anchoring'
);

out('');
out('§9.5 — the tug, both sides derivable from the rendered ledger');

is_same(
    6,
    $disputing['tug']['support'],
    'the support is the sum of the positive rows'
);
is_same(
    29,
    $disputing['tug']['dispute'],
    'the dispute is the sum of the negative ones'
);
is_same(
    $disputing['quality'],
    $disputing['tug']['support'] - $disputing['tug']['dispute'],
    'and the two differ by exactly the quality'
);
is_same(
    array('support', 'dispute'),
    array_keys($disputing['tug']),
    'two keys and no more — the fixture\'s third wedge is gone rather'
        . ' than zeroed, and so are the two aliases D11 renamed'
);

/*
 * A benign lean disputed by its own ledger flips the other way: the
 * threat-signed sum was positive, so re-anchoring makes the quality
 * positive and the state contested.
 */
$flipped = $tool->assess(
    $context,
    ledgerProfile(array(20, -6, 9)),
    array('lean' => 'benign')
);
is_same(
    'contested',
    $flipped['lean'],
    'a benign lean over a threat-leaning ledger is contested too'
);
is_same(
    23,
    $flipped['quality'],
    'and its quality re-anchors threat-signed, so positive'
);
is_same(
    1,
    $flipped['polarity'],
    'with the polarity reset, because contested has no side'
);

/*
 * A lean the ledger disputed needs a falsifier about the ledger, and
 * both directions of it have to be found by the sign of nothing — a
 * benign lean disputed by its own rows re-anchors positive, so a
 * negative-quality test would miss it and the stance probe would then
 * offer a change into the state the value is already in.
 */
$changer = changerFor($disputing, 'lean');
is_true(
    $changer !== null
        && strpos($changer['text'], '24 more points') === 0,
    'a disputed threat lean asks for the points that would settle it'
);
$changer = changerFor($flipped, 'lean');
is_true(
    $changer !== null
        && strpos($changer['text'], '24 more points') === 0,
    'and so does a disputed benign one, off a positive quality'
);
is_true(
    $changer !== null
        && strpos($changer['text'], 'asserted benign') !== false,
    'naming the lean it would firm back to'
);

/*
 * ======================================================================
 * §9.6 — the quality bands, at their boundaries
 * ======================================================================
 */

out('');
out('§9.6 — the bands at every boundary');

$thresholds = profileOf(array('thresholds' => shippedThresholds()));

is_same(
    'none',
    ValueVerdictTool::qualityBand(84, 0, $thresholds),
    'nothing fired: no band, whatever the number says'
);
is_same(
    'high',
    ValueVerdictTool::qualityBand(60, 4, $thresholds),
    'exactly 60 with 4 signals: high'
);
is_same(
    'medium',
    ValueVerdictTool::qualityBand(59, 4, $thresholds),
    'one point short: medium'
);
is_same(
    'medium',
    ValueVerdictTool::qualityBand(60, 3, $thresholds),
    'and 60 on three signals stays medium — a high band means'
        . ' several readings agree'
);
is_same(
    'medium',
    ValueVerdictTool::qualityBand(30, 2, $thresholds),
    'exactly 30: medium'
);
is_same(
    'low',
    ValueVerdictTool::qualityBand(29, 2, $thresholds),
    'one point short of that: low'
);
is_same(
    'low',
    ValueVerdictTool::qualityBand(-23, 3, $thresholds),
    'and a negative quality bands low rather than failing'
);

out('');
out('§6 — the thin-record clamp, which is what the median value needs');

/*
 * The measured case from phase 2: one organisation reporting the same
 * value every month for over a year, carried by three feeds, reaches
 * 43 points — `medium` on the weights alone. The clamp is what says
 * that a record with one source and nothing corroborating it is not a
 * medium-quality record however long it has been going on.
 */
$thin = leanContext(1, 0);
$thin['sightings'] = array('total' => 0, 'fp' => 0);
is_same(
    'medium',
    ValueVerdictTool::qualityBand(43, 5, $thresholds),
    'the weights alone put the measured case in medium'
);
is_same(
    'low',
    ValueVerdictTool::qualityBand(43, 5, $thresholds, $thin),
    'and the clamp holds it at low'
);

$sighted = $thin;
$sighted['sightings']['total'] = 1;
is_same(
    'medium',
    ValueVerdictTool::qualityBand(43, 5, $thresholds, $sighted),
    'one sighting from anyone and the clamp lets go'
);
$second = leanContext(2, 0);
$second['sightings'] = array('total' => 0, 'fp' => 0);
is_same(
    'medium',
    ValueVerdictTool::qualityBand(43, 5, $thresholds, $second),
    'so does a second organisation'
);
is_same(
    'high',
    ValueVerdictTool::qualityBand(70, 5, $thresholds, $second),
    'and the clamp never touches a record it does not describe'
);
$noClamp = profileOf(array('thresholds' => array(
    'quality_bands' => array('high' => 60, 'medium' => 30),
    'quality_high_min_signals' => 4,
)));
is_same(
    'medium',
    ValueVerdictTool::qualityBand(43, 5, $noClamp, $thin),
    'a profile with no clamp section clamps nothing'
);

/*
 * ======================================================================
 * §9.8 — the regression set reaches its stated lean
 * ======================================================================
 */

out('');
out('§9.8 — the five regression cases reach their stated lean');

/*
 * The stance shapes are the fixture's own: each demo verdict states
 * its `to_ids` split and how many organisations reported it, and those
 * two numbers are all the lean reads. The band half of this item needs
 * real rows and is the live probe's.
 */
useRealRules();
ValueSignalLoader::forget('escalation');

is_same(
    'threat',
    $lean->leanFor(leanContext(3, 1), $rules)['lean'],
    'the malicious demo value: 3 of 4 organisations assert it'
);
is_same(
    'benign',
    $lean->leanFor(
        leanContext(1, 3, array('false_positive')),
        $rules
    )['lean'],
    'the benign demo value: one asserter against a resolver list'
);
$conflicted = $lean->leanFor(leanContext(3, 1, array('known')), $rules);
is_same(
    'contested',
    $conflicted['lean'],
    'the conflicted demo value: three reports inside a known range'
);
is_same(
    'conflict:known-infrastructure-vs-reporting',
    $conflicted['rule']['id'],
    'reached through the rule the fixture already names'
);
is_same(
    'threat',
    $lean->leanFor(leanContext(24, 6), $rules)['lean'],
    'the flux demo value: hundreds of reports, a clear majority'
);
is_same(
    'threat',
    $lean->leanFor(leanContext(1, 0), $rules)['lean'],
    'the median value: one organisation asserted it, and that is'
        . ' the honest reading'
);

/*
 * ======================================================================
 * §8 — the changers, derived per axis
 * ======================================================================
 */

out('');
out('§8 — the falsifiability lines are derived, and arithmetic');

useRealSignals();
ValueSignalLoader::forget('signal');
$shipped = shippedProfile();

/*
 * The median value. Its band is held by the clamp, so the line has to
 * be about a second source rather than about points — saying *"18 more
 * points"* to a reader whose record is capped would be false.
 */
$median = leanContext(1, 0);
$median['occurrences'] = array('total' => 1, 'events' => 1,
    'orgs' => 1, 'oldest' => NOW - 5 * 86400,
    'newest' => NOW - 5 * 86400);
$median['temporal'] = array('occurrences' => 1, 'with_first_seen' => 1,
    'max_lag_days' => 1);
$scored = $tool->assess($median, $shipped);
is_same(
    'threat',
    $scored['lean'],
    'the median value leans threat, derived rather than passed'
);
is_same(
    'low',
    $scored['band'],
    'and bands low'
);
$leanChanger = changerFor($scored, 'lean');
is_true(
    $leanChanger !== null,
    'it carries a lean changer'
);
is_true(
    strpos($leanChanger['text'], 'One more organisation') === 0,
    'and the cheapest one is a single organisation'
);
is_same(
    'down',
    $leanChanger['direction'],
    'pointing away from the assertion, because that is what one'
        . ' more organisation would do here'
);

/*
 * And its quality line has to be the clamp's condition, not a points
 * gap. Nine points would take this record over the `medium` floor and
 * the clamp would still hold it at `low`, so a line promising the
 * medium band is a falsifier that fails when a reader acts on it.
 */
$qualityChanger = changerFor($scored, 'quality');
is_true(
    $qualityChanger !== null
        && strpos($qualityChanger['text'], 'A second source') === 0,
    'and its quality line names the clamp rather than a points gap'
);
is_true(
    $qualityChanger !== null
        && strpos($qualityChanger['text'], 'medium band') === false,
    'promising no band the clamp would refuse'
);

/*
 * A lean a conflict rule decided has a falsifier the stance probe
 * cannot see: no number of organisations undoes a category hit, so
 * the line is about the listing.
 */
$listedRange = leanContext(3, 0, array('known'));
$scored = $tool->assess($listedRange, $shipped);
is_same(
    'contested',
    $scored['lean'],
    'the known-range case reaches contested through its rule'
);
$leanChanger = changerFor($scored, 'lean');
is_true(
    $leanChanger !== null
        && strpos($leanChanger['text'], 'Removal from every list') === 0,
    'and its lean changer is the delisting, not an org count'
);
is_same(
    'up',
    $leanChanger['direction'],
    'firming to threat, so it points up'
);

/*
 * The malicious shape: several organisations, so the lean takes more
 * than one to move, and the quality line names a unit rather than a
 * points gap.
 */
$wide = leanContext(4, 0);
$wide['sightings'] = array('total' => 12, 'fp' => 0, 'orgs' => 2,
    'fp_orgs' => 0, 'recent' => 12, 'recent_days' => 30,
    'first_stamp' => NOW - 60 * 86400, 'last_stamp' => NOW - 86400);
$scored = $tool->assess($wide, $shipped);
$leanChanger = changerFor($scored, 'lean');
is_same(
    'threat',
    $scored['lean'],
    'four asserters, nobody disagreeing: threat'
);
is_true(
    $leanChanger !== null
        && strpos($leanChanger['text'], '3 more organisations') === 0,
    'and it takes three organisations to break a 4/0 share'
);
$qualityChanger = changerFor($scored, 'quality');
is_true(
    $qualityChanger !== null,
    'the quality axis has a line of its own'
);
is_same(
    'quality',
    $qualityChanger['axis'],
    'named by axis, so the card can put one row per axis'
);

/*
 * A record already at the top band is asked what it would lose,
 * because *nothing will change this* is not a falsifier.
 */
$strong = ledgerProfile(array(40, 30, 20, 10));
useHarnessSignals();
foreach (array(0, 1, 2, 3) as $index) {
    writeStub(HARNESS_SHIPPED, 'HarnessRow' . $index . '.php',
        stubSignalClass('HarnessRow' . $index, 'harness.row' . $index));
}
ValueSignalLoader::forget('signal');
$scored = $tool->assess($context, $strong, array('lean' => 'threat'));
is_same(
    'high',
    $scored['band'],
    'four rows worth 100 points reach the high band'
);
$qualityChanger = changerFor($scored, 'quality');
is_true(
    $qualityChanger !== null
        && strpos($qualityChanger['text'], 'Withdraw') === 0,
    'and its quality line is what would lose it'
);
is_same(
    'down',
    $qualityChanger['direction'],
    'pointing down, because it is a loss'
);

/*
 * A record with the points for the top band but not the readings.
 * Saying *"more points"* would be wrong twice: it has enough, and
 * more would not help.
 */
$narrow = ledgerProfile(array(70));
$scored = $tool->assess($context, $narrow, array('lean' => 'threat'));
is_same(
    'medium',
    $scored['band'],
    'one row worth 70 does not reach high'
);
$qualityChanger = changerFor($scored, 'quality');
is_true(
    $qualityChanger !== null
        && strpos($qualityChanger['text'], 'The points are already'
            . ' there') === 0,
    'and the line says what is actually missing'
);

/*
 * ------------------------------------------------------------------
 * The hero's sentence (D11 §7, `10-wiring.md` §13)
 * ------------------------------------------------------------------
 * `ValueSummaryTool` is a pure function of a finished assessment, so
 * every branch is reachable here and only three of them are reachable
 * on the verification instance — which has no `benign` lean, no `high`
 * band and no `aging` value at all. A sentence whose rarest readings
 * are never rendered until a real analyst meets one is exactly the
 * shape of copy that ships wrong.
 */
/*
 * ------------------------------------------------------------------
 * The two cases (D11 §4, `10-wiring.md` §14)
 * ------------------------------------------------------------------
 * `value_verdict_conflicted` reads `$cases[0]` and `$cases[1]`
 * positionally and its tug has two feet, so a producer returning one
 * case or three does not degrade — it throws. A pair or nothing is the
 * contract, and it is asserted here rather than trusted.
 */
out('');
out('the two cases');

/*
 * §9.3's own value, reused: rule 7 fired on it, so its ledger is
 * re-anchored threat-signed and carries both signs — which is exactly
 * the record the two-column layout exists for.
 */
$contested = $disputing;
$cases = ValueContestedTool::casesFor($contested);
is_same(2, count($cases), 'a contested record gets exactly two cases');
is_same(
    array('threat', 'benign'),
    array_column($cases, 'side'),
    'threat first, because the layout draws the left column first and'
        . ' paints it in the threat colour'
);
is_same(
    $contested['tug']['support'],
    (int)$cases[0]['weight'],
    'the threat case weighs what the tug says it does'
);
is_same(
    $contested['tug']['dispute'],
    (int)$cases[1]['weight'],
    'and so does the benign one — one fold of one ledger, which is why'
        . ' a reader can add either column up and arrive at the bar'
);
is_same(
    $contested['quality'],
    (int)$cases[0]['weight'] - (int)$cases[1]['weight'],
    'and the two differ by the quality, which is the exact-sum'
        . ' invariant in its contested shape'
);
$ordered = array_column($cases[0]['rows'], 'points');
$sorted = $ordered;
rsort($sorted);
is_same($sorted, $ordered, 'rows come heaviest first');
is_true(
    !in_array(0, array_column($cases[0]['rows'], 'points'), true)
        && !in_array(0, array_column($cases[1]['rows'], 'points'), true),
    'and a row worth nothing is in neither column, because a zero'
        . ' argues for no side'
);

/*
 * The two ways there is no pair to draw. Both fall back to the agreeing
 * layout, which carries a ledger and says the same thing in the shape
 * that fits it.
 */
$agreeing = $tool->assess($context, $shipped, array('lean' => 'threat'));
is_same('threat', $agreeing['lean'], 'the agreeing control really does'
    . ' lean threat, so the next check is about the cases and not about'
    . ' the lean');
is_same(
    array(),
    ValueContestedTool::casesFor($agreeing),
    'a record whose ledger agrees on balance gets no cases — a couple'
        . ' of negative rows is not an opposed case'
);
$oneSided = $agreeing;
$oneSided['lean'] = 'contested';
foreach ($oneSided['ledger'] as $g => $group) {
    foreach ($group['signals'] as $r => $row) {
        $oneSided['ledger'][$g]['signals'][$r]['contribution'] =
            abs((int)$row['contribution']);
    }
}
is_same(
    array(),
    ValueContestedTool::casesFor($oneSided),
    'and neither does a contested lean whose every row falls one way,'
        . ' which rule 7 can produce'
);

/*
 * What neither case could take. Two facts survive scoring without being
 * scored, and both are resolutions by rule rather than by evidence —
 * anything the ledger netted off is already a row and naming it here
 * again would be double-counting in prose.
 */
out('');
out('and what neither case could take');

is_same(
    array(),
    ValueContestedTool::unresolvedFor($context),
    'a record nothing is split about has nothing unresolved'
);

$split = $context;
$split['orgs'][0]['to_ids_no'] = 2;
$items = ValueContestedTool::unresolvedFor($split);
is_same(1, count($items), 'an organisation holding it both ways is one'
    . ' item');
is_true(
    strpos($items[0]['title'], 'One organisation') === 0,
    'named in the singular when there is one of them'
);
is_true(
    strpos($items[0]['note'], 'Counted with the asserters') === 0,
    'and the note says where it went, because it did go somewhere —'
        . ' the heading this card used to carry said *counted for'
        . ' neither side*, which was the one thing it is not'
);

$split['orgs'][1]['to_ids_no'] = 1;
$items = ValueContestedTool::unresolvedFor($split);
is_true(
    strpos($items[0]['title'], '2 organisations') === 0,
    'and in the plural when there are two'
);

$twoLists = $context;
$twoLists['warninglist']['hits'] = array(
    array('name' => 'A resolver list', 'category' => 'false_positive'),
    array('name' => 'A CDN list', 'category' => 'known'),
);
$items = ValueContestedTool::unresolvedFor($twoLists);
is_same(1, count($items), 'lists disagreeing about the kind of listing'
    . ' is the other one');
is_true(
    strpos($items[0]['note'], 'A false-positive listing outranks') === 0,
    'and it says which reading won and that the other is carried by no'
        . ' signal'
);
$oneList = $context;
$oneList['warninglist']['hits'] = array(
    array('name' => 'A resolver list', 'category' => 'false_positive'),
    array('name' => 'Another resolver list', 'category' => 'false_positive'),
);
is_same(
    array(),
    ValueContestedTool::unresolvedFor($oneList),
    'two lists that agree are not a disagreement'
);

out('');
out('the hero sentence, every branch');

/**
 * An assessment array with just the keys the sentence reads.
 *
 * @param string $lean
 * @param string $band
 * @param array|null $relevance
 * @return array
 */
function heroFor($lean, $band, $relevance)
{
    return array(
        'lean' => $lean,
        'band' => $band,
        'relevance' => $relevance === null
            ? array('state' => null, 'runway_days' => null,
                'uncertain' => false)
            : $relevance,
    );
}

/**
 * @param string $state
 * @param int $days
 * @param bool $uncertain
 * @return array
 */
function shelf($state, $days, $uncertain = false)
{
    return array('state' => $state, 'runway_days' => $days,
        'uncertain' => $uncertain);
}

is_same(
    'What is recorded here reads as a threat. The record behind that'
        . ' is well evidenced, and its shelf life ran out 400 days ago.',
    ValueSummaryTool::summaryFor(
        heroFor('threat', 'high', shelf('expired', -400))
    ),
    'D11 §3\'s old malware hash: a well-documented historic threat,'
        . ' which the one-number design could not say'
);
is_same(
    'What is recorded here reads as a threat. The record behind that'
        . ' is thin, and its shelf life ran out 1 day ago — on a'
        . ' timeline nothing records.',
    ValueSummaryTool::summaryFor(
        heroFor('threat', 'low', shelf('expired', -1, true))
    ),
    'and §3\'s late-encoded phishing URL: asserted threat, thin'
        . ' record, likely over'
);
is_same(
    'What is recorded here reads as benign. The record behind that is'
        . ' well evidenced, and it has 40 days of shelf life left.',
    ValueSummaryTool::summaryFor(
        heroFor('benign', 'high', shelf('current', 40))
    ),
    'a benign lean reads as an assertion too, not as an absence'
);
is_same(
    'What is recorded here contradicts itself. The record behind that'
        . ' is moderately evidenced, and it is most of the way through'
        . ' its shelf life, with 9 days left.',
    ValueSummaryTool::summaryFor(
        heroFor('contested', 'medium', shelf('aging', 9))
    ),
    'aging says the same number differently, because the hero draws no'
        . ' state label to separate it from current'
);
is_same(
    'What is recorded here reads as a threat. The record behind that'
        . ' is thin, and it expires today.',
    ValueSummaryTool::summaryFor(
        heroFor('threat', 'low', shelf('aging', 0))
    ),
    'and the day it runs out is a day, not "0 days left"'
);
is_same(
    'What is recorded here reads as a threat. The record behind that'
        . ' is thin, and it has 1 day of shelf life left.',
    ValueSummaryTool::summaryFor(
        heroFor('threat', 'low', shelf('current', 1))
    ),
    'one day is singular on the way down as well as on the way past'
);
is_same(
    'Nothing you can see records this value, so there is nothing to'
        . ' assess.',
    ValueSummaryTool::summaryFor(heroFor('none', 'none', null)),
    'and the value with nothing to assess gets one clause — the branch'
        . ' the card reaches first, because it prints prose only where'
        . ' there are no rows to list'
);

/*
 * The two halves that must not be assembled: a lean with no band has
 * nothing to say about a record, and a band with no clock has nothing
 * to say about time. Both used to end the sentence mid-phrase.
 */
is_same(
    'What is recorded here reads as a threat.',
    ValueSummaryTool::summaryFor(
        heroFor('threat', 'none', shelf('current', 40))
    ),
    'a lean with nothing weighed behind it stops after one clause'
        . ' rather than naming a band it does not have'
);
is_same(
    'What is recorded here reads as a threat. The record behind that'
        . ' is thin.',
    ValueSummaryTool::summaryFor(heroFor('threat', 'low', null)),
    'and a record with no clock to run ends after the band'
);

/*
 * The sentence is a reading of the assessment and never a second
 * opinion, so the same assessment has to produce the same sentence —
 * and a *different* band has to change it. Both directions, because a
 * builder that ignored its input would pass the first alone.
 */
$sameTwice = ValueSummaryTool::summaryFor(
    heroFor('contested', 'low', shelf('current', 69))
);
is_same(
    $sameTwice,
    ValueSummaryTool::summaryFor(
        heroFor('contested', 'low', shelf('current', 69))
    ),
    'the same assessment writes the same sentence'
);
is_true(
    $sameTwice !== ValueSummaryTool::summaryFor(
        heroFor('contested', 'high', shelf('current', 69))
    ),
    'and a different band writes a different one'
);

/*
 * ------------------------------------------------------------------
 * How the lean was decided (`10-wiring.md` §18)
 * ------------------------------------------------------------------
 * `leanFor()` has seven exits and, until this pass, exactly one of
 * them said anything a reader could see. Two things are asserted here
 * and they are different claims: that the engine **names** the exit it
 * took, and that `ValueLeanReasonTool` writes the right sentence for
 * that name.
 *
 * Every case below runs end to end — the engine's own output is handed
 * straight to the writer — so a rule that changed which exit it takes
 * would break the sentence too, rather than leaving a tool agreeing
 * with a fixture about a branch the engine no longer reaches. It is
 * also the only place several of these can be read at all: the
 * verification instance has no `benign` lean and nothing on a
 * false-positive list under a minority of asserters.
 */
out('');
out('the exit each rule takes');

is_same(
    'nothing_visible',
    $lean->leanFor($nothing, $rules)['decided_by'],
    'rule 1 names itself'
);
is_same(
    'threat_supermajority',
    $lean->leanFor(leanContext(66, 34), $rules)['decided_by'],
    'rule 4 names itself'
);
is_same(
    'benign_supermajority',
    $lean->leanFor(leanContext(34, 66), $rules)['decided_by'],
    'rule 5 names itself'
);
is_same(
    'no_supermajority',
    $lean->leanFor(leanContext(65, 35), $rules)['decided_by'],
    'rule 6 names itself — the split'
);
is_same(
    'false_positive_listed',
    $lean->leanFor($listedMinority, $rules)['decided_by'],
    'rule 3 names itself'
);
is_same(
    'escalation',
    $decided['decided_by'],
    'and a conflict rule firing is its own exit'
);

/*
 * Rules 3 and 5 both answer `benign`, which is why the name is carried
 * rather than re-derived: this pair is indistinguishable from the
 * outside, and they are two different sentences.
 */
is_same(
    'benign',
    $lean->leanFor($listedMinority, $rules)['lean'],
    'rules 3 and 5 answer the same lean'
);
is_true(
    $lean->leanFor($listedMinority, $rules)['decided_by']
        !== $lean->leanFor(leanContext(34, 66), $rules)['decided_by'],
    'and are still told apart by the exit they name'
);

out('');
out('and the sentence written for it');

$reasonThreat = ValueLeanReasonTool::reasonFor(
    $lean->leanFor(leanContext(66, 34), $rules)
);
is_true(
    strpos($reasonThreat, '66 of 100 organisations assert this is a'
        . ' threat') !== false,
    'rule 4 says who asserted and out of how many'
);
is_true(
    strpos($reasonThreat, '66%') !== false,
    'and names the threshold it cleared'
);
is_true(
    strpos($reasonThreat, 'this is a threat') !== false
        && strpos($reasonThreat, 'organisations assert') !== false,
    'as something the organisations assert, never as what the value is'
);

$reasonBenign = ValueLeanReasonTool::reasonFor(
    $lean->leanFor(leanContext(34, 66), $rules)
);
is_true(
    strpos($reasonBenign, '66 of 100 organisations report this as'
        . ' harmless') !== false,
    'rule 5 counts the benign side, not the threat one'
);

$reasonSplit = ValueLeanReasonTool::reasonFor(
    $lean->leanFor(leanContext(65, 35), $rules)
);
is_true(
    strpos($reasonSplit, 'Neither side reaches') === 0,
    'rule 6 leads with the reason there is no reading'
);
is_true(
    strpos($reasonSplit, 'the split is 65 to 35') !== false,
    'and carries both counts, because the split is the point'
);
/*
 * Said as a ratio rather than as two clauses with verbs in them. The
 * first wording read *1 of 2 assert a threat and 1 report it as
 * harmless* on the instance's own split values, and `__n()` cannot fix
 * it: one plural form has to serve both counts, and here they disagree
 * about which one they want.
 */
is_true(
    strpos(
        ValueLeanReasonTool::reasonFor(
            $lean->leanFor(leanContext(1, 1), $rules)
        ),
        'the split is 1 to 1'
    ) !== false,
    'and reads grammatically where one organisation holds each side'
);

$reasonListed = ValueLeanReasonTool::reasonFor(
    $lean->leanFor($listedMinority, $rules)
);
is_true(
    strpos($reasonListed, 'warninglist marks this a false positive')
        !== false,
    'rule 3 names the list as what decided it'
);
is_true(
    strpos($reasonListed, 'no supermajority of organisations disputes')
        !== false,
    'and says the organisations were given the chance to override it'
);
is_true(
    strpos($reasonListed, 'the threat stances run 1 of 2') !== false,
    'with the minority it beat, as a ratio rather than a verb'
);

/*
 * The escalation exit is the one that must *not* restate its own
 * evidence: the rule's prose is already on the page, one line above
 * this band, and §13.3 was careful about exactly this.
 */
$reasonRule = ValueLeanReasonTool::reasonFor($decided);
is_true(
    strpos($reasonRule, 'quoted in the line above') !== false,
    'the escalation exit points at the rule rather than competing'
);
is_true(
    strpos($reasonRule, '4 of 4') === false,
    'and does not restate the counts the rule itself prints'
);

is_same(
    null,
    ValueLeanReasonTool::reasonFor($lean->leanFor($nothing, $rules)),
    'the empty record gets no sentence, so the band draws nothing'
);
is_same(
    null,
    ValueLeanReasonTool::reasonFor(array()),
    'and neither does an assessment with no exit named'
);

/*
 * Plurals, on the median value in production: one organisation, no
 * sightings. `1 of 1 organisations assert` is the shape that ships
 * when a writer treats the count as decoration.
 */
$reasonOne = ValueLeanReasonTool::reasonFor(
    $lean->leanFor(leanContext(1, 0), $rules)
);
is_true(
    strpos($reasonOne, '1 of 1 organisation asserts') !== false,
    'one organisation asserts, in the singular'
);
is_true(
    strpos($reasonOne, 'organisations') === false,
    'and nothing on that sentence is plural'
);

/*
 * The threshold is printed, never spelled. `0.66` is not two thirds —
 * §11.1 needed a tolerance for that gap — so a sentence saying *two
 * thirds* would name a threshold the engine does not use.
 */
$halved = $lean->leanFor(leanContext(66, 34), $rules);
$halved['stances']['supermajority'] = 0.5;
is_true(
    strpos(ValueLeanReasonTool::reasonFor($halved), '50%') !== false,
    'the threshold is read from the profile rather than hardcoded'
);
unset($halved['stances']['supermajority']);
is_true(
    strpos(ValueLeanReasonTool::reasonFor($halved), 'supermajority')
        !== false,
    'and a profile with none still writes a sentence'
);

/*
 * Same assessment, same sentence; a different exit, a different one.
 * A writer ignoring its input passes the first of these alone.
 */
is_same(
    $reasonThreat,
    ValueLeanReasonTool::reasonFor(
        $lean->leanFor(leanContext(66, 34), $rules)
    ),
    'the same assessment writes the same sentence'
);
is_true(
    $reasonThreat !== $reasonSplit,
    'and a different exit writes a different one'
);

out('');
out(sprintf(
    '%d checks, %d failures',
    $GLOBALS['checks'],
    $GLOBALS['failures']
));

exit($GLOBALS['failures'] === 0 ? 0 : 1);
