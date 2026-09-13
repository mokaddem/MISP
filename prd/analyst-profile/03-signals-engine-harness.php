<?php
/**
 * Phase 2's invariants, without a database.
 *
 * The exact-sum invariant (`01-profile.md` §5.1) is the property the
 * whole design rests on, and it is checkable with no instance at all:
 * hand the accumulator a context and a profile, and the ledger it
 * returns either sums to the quality or it does not. So is every
 * failure mode a directory that executes its contents invites —
 * `03-signals.md` §9's items 12 to 18 are files dropped in a
 * directory, and a harness can drop them.
 *
 * Covers §9 items 2, 3, 5, 6, 7, 9 (the median's calibration rule),
 * 13, 14, 15, 16 and 18, plus the catalogue-against-profile agreement
 * that nothing else would catch until a ledger row silently went
 * missing. Items 4, 8, 10, 11, 12 and 17 need a live instance or a
 * rendered page; `03-signals-live-probe.php` takes the first three and
 * §9 says which are phase 9's.
 *
 * Run: `php prd/analyst-profile/03-signals-engine-harness.php`
 *
 * It writes its stub signals under `app/tmp/value-signal-harness/` and
 * deletes them on the way out, which is also how it can assert what a
 * *missing* custom directory does.
 */

define('APP', __DIR__ . '/../../app/');

class App
{
    public static function uses($class, $path)
    {
    }
}

/**
 * The loader logs its failures where an admin will find them; here the
 * lines are the assertion.
 */
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

require_once APP . 'Lib/Tools/ValueSignalLoader.php';
require_once APP . 'Lib/Tools/ValueLeanTool.php';
require_once APP . 'Lib/Tools/ValueChangersTool.php';
/*
 * Loaded because `ValueVerdictTool::verdict()` assembles the relevance
 * axis (phase 5), which every earlier phase's engine call now reaches.
 */
require_once APP . 'Lib/Tools/ValueRelevanceTool.php';
require_once APP . 'Lib/Tools/ValueVerdictTool.php';

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
 * The two catalogues: the real one, and the harness's stubs.
 * ----------------------------------------------------------------------
 */

const HARNESS_SHIPPED = 'tmp/value-signal-harness/shipped/';
const HARNESS_CUSTOM = 'tmp/value-signal-harness/custom/';
const HARNESS_ABSENT = 'tmp/value-signal-harness/nothing-here/';

function useRealCatalogue()
{
    ValueSignalLoader::register('signal', array(
        'shipped' => 'Model/ValueSignals/',
        'custom' => 'Lib/ValueSignals/',
        'base' => 'ValueSignalBase',
        'skip' => array('ValueSignalBase.php'),
    ));
}

function useHarnessCatalogue($custom = HARNESS_CUSTOM)
{
    ValueSignalLoader::register('signal', array(
        'shipped' => HARNESS_SHIPPED,
        'custom' => $custom,
        'base' => 'ValueSignalBase',
        'skip' => array(),
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
    $parent = APP . 'tmp/value-signal-harness/';
    if (is_dir($parent)) {
        rmdir($parent);
    }
}

/** A stub returning whatever row its profile entry carries. */
function stubRowClass($class, $id)
{
    return '<?php class ' . $class . ' extends ValueSignalBase {'
        . ' public $id = \'' . $id . '\';'
        . ' public $group = \'Reporting\';'
        . ' public $description = \'harness\';'
        . ' public function evaluate(array $context, array $config) {'
        . '   $row = $config[\'config\'][\'row\'] ?? null;'
        . '   return $row === null ? null : $row; } }';
}

function shippedProfile()
{
    $raw = file_get_contents(
        APP . 'files/analyst-profiles/default-v1.json'
    );
    return json_decode($raw, true);
}

/*
 * ----------------------------------------------------------------------
 * Contexts. The median value is the one that matters most — most real
 * values are this shape, and §7.4 made it a regression case for
 * exactly that reason.
 * ----------------------------------------------------------------------
 */

/**
 * A value that exists and carries no evidence of its own.
 *
 * One occurrence, deliberately: the accumulator refuses to assess a
 * value with none (§2 — a `none` lean has no ledger, and neither has an
 * empty context), so a context for the stub-row tests has to be of a
 * value that exists. `absentContext()` is the other case.
 */
function emptyContext($now = 1757232000)
{
    return array(
        'value' => '198.51.100.24',
        'now' => $now,
        'as_of' => date('Y-m-d', $now),
        'types' => array(),
        'occurrences' => array('total' => 1, 'events' => 1, 'orgs' => 1,
            'oldest' => $now, 'newest' => $now),
        'publication' => array('events' => 0, 'published' => 0,
            'unpublished' => 0),
        'temporal' => array('occurrences' => 0, 'with_first_seen' => 0,
            'max_lag_days' => null),
        'orgs' => array(),
        'activity' => array('months' => array(), 'active_months' => 0,
            'span_months' => 0, 'longest_run' => 0),
        'warninglist' => array('hits' => array(),
            'lists_checked' => 84, 'category' => null),
        'feeds' => array('count' => 0, 'names' => array(),
            'checked' => 3),
        'sightings' => array('total' => 0, 'fp' => 0),
        'galaxies' => array('clusters' => array(),
            'techniques' => array()),
        'budget' => array('window_days' => null, 'hot' => false,
            'occurrences' => 1, 'threshold' => 10000),
        'excluded' => array(),
        'missing' => array(),
    );
}

/** No occurrence this viewer can see. */
function absentContext($now = 1757232000)
{
    $context = emptyContext($now);
    $context['occurrences'] = array('total' => 0, 'events' => 0,
        'orgs' => 0, 'oldest' => null, 'newest' => null);
    $context['budget']['occurrences'] = 0;
    return $context;
}

/**
 * One occurrence, one organisation, no sightings, no galaxy, no
 * warninglist hit — the production median (§7.4).
 */
function medianContext($now = 1757232000)
{
    $context = emptyContext($now);
    $context['types'] = array(
        array('type' => 'ip-dst', 'count' => 1),
    );
    $context['occurrences'] = array(
        'total' => 1,
        'events' => 1,
        'orgs' => 1,
        'oldest' => $now - 5 * 86400,
        'newest' => $now - 5 * 86400,
    );
    $context['publication'] = array('events' => 1, 'published' => 1,
        'unpublished' => 0);
    $context['temporal'] = array('occurrences' => 1,
        'with_first_seen' => 1, 'max_lag_days' => 1);
    $context['orgs'] = array(
        array('id' => 1, 'name' => 'CIRCL', 'occurrences' => 1,
            'to_ids_yes' => 1, 'to_ids_no' => 0,
            'newest' => $now - 5 * 86400),
    );
    $context['activity'] = array(
        'months' => array(date('Y-m', $now) => 1),
        'active_months' => 1,
        'span_months' => 1,
        'longest_run' => 1,
    );
    $context['budget']['occurrences'] = 1;
    return $context;
}

/**
 * The malicious demo value's facts, as the rest of the page states
 * them: four organisations, five of seven events published, 47
 * sightings from four orgs with the last two days ago, one false
 * positive, APT28, `T1071.001`, no warninglist hit, eleven unbroken
 * months.
 */
function maliciousContext($now = 1757232000)
{
    $context = emptyContext($now);
    $context['value'] = '185.234.219.24';
    $context['types'] = array(
        array('type' => 'ip-dst', 'count' => 7),
        array('type' => 'ip-src', 'count' => 3),
    );
    $context['occurrences'] = array(
        'total' => 10,
        'events' => 7,
        'orgs' => 4,
        'oldest' => $now - 340 * 86400,
        'newest' => $now - 2 * 86400,
    );
    $context['publication'] = array('events' => 7, 'published' => 5,
        'unpublished' => 2);
    $context['temporal'] = array('occurrences' => 10,
        'with_first_seen' => 6, 'max_lag_days' => 4);
    $context['orgs'] = array(
        array('id' => 1, 'name' => 'CIRCL', 'occurrences' => 4,
            'to_ids_yes' => 4, 'to_ids_no' => 0, 'newest' => $now),
        array('id' => 2, 'name' => 'CthulhuSPRL.be',
            'occurrences' => 3, 'to_ids_yes' => 3, 'to_ids_no' => 0,
            'newest' => $now),
        array('id' => 3, 'name' => 'Team-CIRCL', 'occurrences' => 2,
            'to_ids_yes' => 1, 'to_ids_no' => 1, 'newest' => $now),
        array('id' => 4, 'name' => 'ORGNAME', 'occurrences' => 1,
            'to_ids_yes' => 0, 'to_ids_no' => 1, 'newest' => $now),
    );
    $context['sightings'] = array(
        'total' => 47,
        'fp' => 1,
        'expiration' => 0,
        'last_stamp' => $now - 2 * 86400,
        'fp_last_stamp' => $now - 130 * 86400,
        'orgs' => 4,
        'fp_orgs' => 1,
        'fp_org_names' => array('ORGNAME'),
        'recent' => 12,
        'recent_days' => 30,
        'first_stamp' => $now - 330 * 86400,
    );
    $context['galaxies'] = array(
        'clusters' => array('APT28' => 2),
        'techniques' => array('T1071.001' => 3),
    );
    $context['feeds'] = array('count' => 1,
        'names' => array('CIRCL OSINT Feed'), 'checked' => 3);
    $months = array();
    for ($i = 10; $i >= 0; $i--) {
        $months[date('Y-m', strtotime(sprintf('-%d months', $i), $now))]
            = 3;
    }
    $context['activity'] = array('months' => $months,
        'active_months' => 11, 'span_months' => 11,
        'longest_run' => 11);
    $context['budget']['occurrences'] = 10;
    return $context;
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
    $sum = 0;
    foreach (ledgerRows($verdict) as $row) {
        $sum += $row['contribution'];
    }
    return $sum;
}

/**
 * A row by signal id, from either axis.
 *
 * The lean rows left the ledger with `review-2026-09-13.md` §D1 — the
 * table sums to the quality alone now, and what reads the value is in
 * `lean_ledger` — so a lookup that only walked the ledger would answer
 * null for the two signals whose anchoring this file is most
 * interested in.
 */
function rowById(array $verdict, $id)
{
    $rows = array_merge(
        ledgerRows($verdict),
        isset($verdict['lean_ledger']) ? $verdict['lean_ledger'] : array()
    );
    foreach ($rows as $row) {
        if ($row['id'] === $id) {
            return $row;
        }
    }
    return null;
}

/*
 * ----------------------------------------------------------------------
 * §9.2 — the accumulator against the three fixture ledgers.
 *
 * The fixture's own rows, fed in as literal signal output, must sum to
 * the numbers the page already shows: +84 and +93 on the threat lean,
 * +91 on the benign one with every row flipped. The benign rows are
 * authored verdict-relative, so the harness converts them to the
 * threat-signed declaration a profile would carry — which is exactly
 * what §2's anchoring undoes.
 * ----------------------------------------------------------------------
 */

/**
 * @param array $rows [group, band, contribution, signal] as the fixture
 *                    authored them, verdict-relative
 * @param int $polarity The lean's polarity; -1 flips the declaration
 * @return array Profile entries for the stub signal
 */
/**
 * The fixture's authored rows as profile entries.
 *
 * **It used to pre-multiply by the lean's polarity** so that the
 * engine's anchoring would undo it and arrive back at the authored
 * number. That is gone with the anchoring it was compensating for:
 * `review-2026-09-13.md` §D1 anchors only the rows that read the
 * value, and a fixture ledger replayed as a catalogue of quality
 * signals keeps the sign it was authored with on either lean. Which is
 * the property this block now asserts, rather than the one it used to
 * arrange for.
 *
 * @param array $rows
 * @param string $axis Which of D11's axes to declare the rows on
 */
function fixtureEntries(array $rows,
    $axis = ValueVerdictTool::AXIS_QUALITY
) {
    $entries = array();
    foreach ($rows as $row) {
        $entries[] = array(
            'id' => 'harness.row',
            'group' => $row[0],
            'band' => $row[1],
            'points' => array(),
            'config' => array('row' => array(
                'signal' => $row[3],
                'evidence' => 'harness',
                'contribution' => (int)$row[2],
                'source' => 'Occurrences',
                'as_of' => '2025-08-19',
                'axis' => $axis,
            )),
        );
    }
    return $entries;
}

function maliciousFixtureRows()
{
    return array(
        array('Reporting', 'strong', 28, '4 independent orgs'),
        array('Reporting', 'moderate', 9, '5 of 7 published'),
        array('Sightings', 'strong', 24, '47 sightings'),
        array('Sightings', 'moderate', -6, '1 false positive'),
        array('Attribution', 'moderate', 14, 'APT28'),
        array('Attribution', 'weak', 5, 'T1071.001'),
        array('Lifecycle', 'moderate', 12, 'NIDS above threshold'),
        array('Lifecycle', 'moderate', -8, 'decayed under Phishing'),
        array('Lifecycle', 'weak', 6, 'no warninglist hit'),
    );
}

function benignFixtureRows()
{
    return array(
        array('Lifecycle', 'strong', 38, 'public-resolver list'),
        array('Sightings', 'strong', 26, '11 false positives'),
        array('Reporting', 'moderate', 13, '8 of 9 to_ids = no'),
        array('Reporting', 'moderate', -11, '4 orgs report it'),
        array('Attribution', 'weak', 7, 'no galaxy, no technique'),
        array('Sightings', 'moderate', 12, 'no sighting in 5 months'),
        array('Lifecycle', 'moderate', 11, 'on 3 feeds as benign'),
        array('Reporting', 'weak', -5, '2 events call it C2'),
    );
}

function fluxFixtureRows()
{
    return array(
        array('Reporting', 'strong', 31, '23 independent orgs'),
        array('Sightings', 'strong', 24, '418 sightings'),
        array('Attribution', 'strong', 17, 'QakBot'),
        array('Lifecycle', 'moderate', 12, 'fourteen months'),
        array('Lifecycle', 'moderate', 16, 'NIDS 91/100'),
        array('Reporting', 'moderate', -6, 'one org dominates'),
        array('Sightings', 'weak', -1, 'one false positive'),
    );
}

out('');
out('the axis constants, which exist in two places');

/*
 * `ValueVerdictTool` mirrors `ValueSignalBase`'s two axis constants
 * rather than naming the class, because the path it takes for a value
 * with nothing to assess never loads the signal base — the loader
 * `include_once`s it at first scan, and that path never scans. A
 * mirror is the cheap fix and this is the cheap check on it.
 */
is_same(
    ValueSignalBase::AXIS_LEAN,
    ValueVerdictTool::AXIS_LEAN,
    'the engine and the signal base agree on AXIS_LEAN'
);
is_same(
    ValueSignalBase::AXIS_QUALITY,
    ValueVerdictTool::AXIS_QUALITY,
    'and on AXIS_QUALITY'
);
/*
 * And which of the shipped catalogue is on which axis — the list D11
 * §2.1 names, asserted rather than assumed, because a signal quietly
 * joining the lean axis would put the polarity back on a row that
 * weighs the record.
 */
useRealCatalogue();
ValueSignalLoader::forget();
$leanSignals = array();
foreach (ValueSignalLoader::catalogue() as $id => $config) {
    if (isset($config['axis'])
        && $config['axis'] === ValueSignalBase::AXIS_LEAN
    ) {
        $leanSignals[] = $id;
    }
}
sort($leanSignals);
is_same(
    array('lifecycle.warninglist', 'sightings.false_positive'),
    $leanSignals,
    'exactly two shipped signals read the value; the other nine weigh'
        . ' the record'
);

out('');
out('§9.2 — the fixture ledgers sum to the numbers on the page');

useHarnessCatalogue();
writeStub(
    HARNESS_SHIPPED,
    'HarnessLedgerRow.php',
    stubRowClass('HarnessLedgerRow', 'harness.row')
);
ValueSignalLoader::forget();

$tool = new ValueVerdictTool();

$cases = array(
    array('185.234.219.24', maliciousFixtureRows(), 'threat', 84),
    array('45.155.205.233', fluxFixtureRows(), 'threat', 93),
    array('8.8.8.8', benignFixtureRows(), 'benign', 91),
);
foreach ($cases as $case) {
    list($value, $rows, $lean, $expected) = $case;
    $profile = array(
        'name' => 'harness',
        'parameters' => array(
            'signals' => fixtureEntries($rows),
            'thresholds' => array(
                'quality_bands' => array('high' => 60, 'medium' => 30),
                'quality_high_min_signals' => 4,
            ),
        ),
    );
    $verdict = $tool->assess(
        emptyContext(),
        $profile,
        array('lean' => $lean)
    );
    is_same(
        $expected,
        $verdict['quality'],
        sprintf('%s: the ledger sums to %d', $value, $expected)
    );
    /*
     * And sums to the same against the opposite lean, which is the
     * property `review-2026-09-13.md` §A1 was about. The benign
     * fixture is the case that used to need the polarity trick to
     * reach 91: its rows weigh a record, and a record does not get
     * thinner because the reading above it changed.
     */
    $opposite = $tool->assess(
        emptyContext(),
        $profile,
        array('lean' => $lean === 'benign' ? 'threat' : 'benign')
    );
    is_same(
        $expected,
        $opposite['quality'],
        sprintf(
            '%s: and sums to the same under the opposite lean',
            $value
        )
    );
    is_same(
        $verdict['quality'],
        ledgerSum($verdict),
        sprintf('%s: quality is the sum of the rendered rows', $value)
    );
    $directions = array();
    foreach (ledgerRows($verdict) as $row) {
        $directions[] = $row['direction'];
    }
    $authored = array();
    foreach ($rows as $row) {
        $authored[] = $row[2] < 0 ? 'down' : 'up';
    }
    /*
     * Order differs — the engine groups, the fixture lists — so the
     * tally is what has to match: the same number of rows arguing each
     * way as the fixture authored.
     */
    sort($directions);
    sort($authored);
    is_same(
        $authored,
        $directions,
        sprintf('%s: every row keeps its authored direction', $value)
    );
    is_same(
        count($rows),
        count(ledgerRows($verdict)),
        sprintf('%s: every authored row is rendered', $value)
    );
    $composition = 0;
    foreach ($verdict['composition'] as $segment) {
        $composition += $segment['points'];
    }
    is_same(
        $verdict['quality'],
        $composition,
        sprintf('%s: the composition card sums to the same', $value)
    );
}

/*
 * ----------------------------------------------------------------------
 * §9.3 — a negative sum, and a sum of exactly zero.
 * ----------------------------------------------------------------------
 */

out('');
out('§9.3 — a negative sum and a zero sum are both stated outcomes');

$negative = $tool->assess(
    emptyContext(),
    array('name' => 'harness', 'parameters' => array(
        'signals' => fixtureEntries(array(
            array('Reporting', 'strong', 12, 'thin support'),
            array('Sightings', 'strong', -30, 'contradicted'),
        )),
    )),
    array('lean' => 'threat')
);
is_same(-18, $negative['quality'], 'a negative quality is returned as is');
is_same(
    'low',
    $negative['band'],
    'and bands as low rather than as an absent ledger'
);
/*
 * Phase 3's rule 7 turns this into a contested lean; what phase 2 owes
 * is the number it reads, unclamped and unhidden.
 */
is_true(
    $negative['quality'] < 0,
    'the sign phase 3 reads for rule 7 survives'
);

$zero = $tool->assess(
    emptyContext(),
    array('name' => 'harness', 'parameters' => array(
        'signals' => fixtureEntries(array(
            array('Reporting', 'strong', 12, 'support'),
            array('Sightings', 'strong', -12, 'dispute'),
        )),
    )),
    array('lean' => 'threat')
);
is_same(0, $zero['quality'], 'a sum of exactly zero is zero');
is_same('low', $zero['band'], 'zero is the low band, not none');
is_same(2, $zero['signals']['fired'], 'and both rows are on the page');

/*
 * ----------------------------------------------------------------------
 * §9.5 — an unknown signal id. §9.6 and §9.7 — nothing to score.
 * ----------------------------------------------------------------------
 */

out('');
out('§9.5 — an unknown id is an honest state, never a fatal');

$unknown = $tool->assess(
    emptyContext(),
    array('name' => 'default-v1', 'parameters' => array(
        'signals' => array_merge(
            fixtureEntries(array(
                array('Reporting', 'strong', 20, 'a signal that ran'),
            )),
            array(array('id' => 'reporting.from_a_plugin',
                'points' => array('per' => 5)))
        ),
    )),
    array('lean' => 'threat')
);
is_same(20, $unknown['quality'], 'the assessment still computes');
is_same(1, count($unknown['not_counted']), 'the id is not counted');
is_same(
    'reporting.from_a_plugin',
    $unknown['not_counted'][0]['title'],
    'and it is named on the page'
);
is_same(
    'unavailable',
    $unknown['not_counted'][0]['kind'],
    'as unavailable rather than as broken'
);
is_same(
    'default-v1',
    $unknown['profile'],
    'the hero still names the profile that weighted it'
);

out('');
out('§9.6, §9.7 — every signal disabled, and no profile at all');

$disabled = $tool->assess(
    emptyContext(),
    array('name' => 'default-v1', 'parameters' => array(
        'signals' => array(
            array('id' => 'harness.row', 'enabled' => false,
                'config' => array('row' => array(
                    'signal' => 'never runs',
                    'contribution' => 40,
                ))),
        ),
    )),
    array('lean' => 'threat')
);
is_same(array(), $disabled['ledger'], 'a disabled signal emits no row');
is_same(0, $disabled['quality'], 'and no quality');
is_same('none', $disabled['band'], 'the band is none, not low');
is_same(
    array(),
    $disabled['not_counted'],
    'and not a note either — a disabled signal is silent by design'
);

$none = $tool->assess(emptyContext(), null, array('lean' => 'threat'));
is_same(array(), $none['ledger'], 'no profile: an empty ledger');
is_same('none', $none['band'], 'no band');
is_same(null, $none['profile'], 'and nothing named as the profile');
is_same('threat', $none['lean'], 'the lean still arrives from phase 3');

/*
 * ----------------------------------------------------------------------
 * §9.16 — third-party code cannot break the exact sum.
 * ----------------------------------------------------------------------
 */

out('');
out('§9.16 — a signal that throws, and three malformed rows');

writeStub(HARNESS_SHIPPED, 'HarnessThrower.php', '<?php'
    . ' class HarnessThrower extends ValueSignalBase {'
    . ' public $id = \'harness.throws\';'
    . ' public $description = \'harness\';'
    . ' public function evaluate(array $c, array $g) {'
    . ' throw new Exception(\'a dropped file exploded\'); } }');
writeStub(HARNESS_SHIPPED, 'HarnessMalformed.php', '<?php'
    . ' class HarnessMalformed extends ValueSignalBase {'
    . ' public $id = \'harness.malformed\';'
    . ' public $description = \'harness\';'
    . ' public function evaluate(array $c, array $g) {'
    . ' return array(\'signal\' => \'bad\','
    . ' \'contribution\' => $g[\'config\'][\'shape\']); } }');
ValueSignalLoader::forget();

foreach (array(
    array(1.5, 'a float'),
    array('7', 'a string'),
    array(array(7), 'an array'),
) as $shape) {
    $verdict = $tool->assess(
        emptyContext(),
        array('name' => 'harness', 'parameters' => array(
            'signals' => array_merge(
                fixtureEntries(array(
                    array('Reporting', 'strong', 20, 'a real row'),
                    array('Sightings', 'moderate', -5, 'another'),
                )),
                array(
                    array('id' => 'harness.throws'),
                    array('id' => 'harness.malformed',
                        'config' => array('shape' => $shape[0])),
                )
            ),
        )),
        array('lean' => 'threat')
    );
    is_same(
        15,
        $verdict['quality'],
        sprintf(
            'with %s contribution, the remaining rows still sum exactly',
            $shape[1]
        )
    );
    is_same(
        2,
        count($verdict['not_counted']),
        sprintf('%s: both failures are on the page', $shape[1])
    );
    $kinds = array();
    foreach ($verdict['not_counted'] as $item) {
        $kinds[] = $item['kind'];
    }
    is_same(
        array('broken', 'broken'),
        $kinds,
        sprintf('%s: named as broken rather than as absent', $shape[1])
    );
}

/*
 * ----------------------------------------------------------------------
 * §9.14, §9.15, §9.18, §9.13 — the loader's own failure modes.
 * ----------------------------------------------------------------------
 */

out('');
out('§9.14 — three broken files, none of which may take the page down');

writeStub(HARNESS_SHIPPED, 'HarnessUnparseable.php',
    '<?php class HarnessUnparseable extends { function }');
writeStub(HARNESS_SHIPPED, 'HarnessNoClass.php',
    '<?php $notAClass = true;');
writeStub(HARNESS_SHIPPED, 'HarnessNotASignal.php',
    '<?php class HarnessNotASignal { public $id = \'harness.alien\'; }');
ValueSignalLoader::forget();
CakeLog::$lines = array();

$catalogue = ValueSignalLoader::catalogue();
is_true(
    isset($catalogue['harness.row']),
    'the well-formed signals still load'
);
is_true(
    !isset($catalogue['harness.alien']),
    'a class that is not a signal is unavailable'
);
$errors = ValueSignalLoader::errors();
is_same(
    3,
    count($errors),
    'each broken file is recorded, keyed by filename'
);
is_same(
    3,
    count(CakeLog::$lines),
    'and logged where an admin will find it'
);

out('');
out('§9.15 — a colliding id is refused and the shipped one keeps it');

writeStub(
    HARNESS_CUSTOM,
    'HarnessCollider.php',
    stubRowClass('HarnessCollider', 'harness.row')
);
ValueSignalLoader::forget();
$catalogue = ValueSignalLoader::catalogue();
is_same(
    'HarnessLedgerRow',
    get_class(ValueSignalLoader::get('harness.row')),
    'the shipped implementation keeps the id'
);
is_true(
    isset(ValueSignalLoader::errors()['HarnessCollider.php']),
    'and the collision is named'
);

$collided = $tool->assess(
    emptyContext(),
    array('name' => 'harness', 'parameters' => array(
        'signals' => fixtureEntries(array(
            array('Reporting', 'strong', 20, 'the shipped row'),
        )),
    )),
    array('lean' => 'threat')
);
is_same(20, $collided['quality'], 'the shipped signal still fires');

out('');
out('§9.13 — a dropped file that no profile enables moves nothing');

writeStub(
    HARNESS_CUSTOM,
    'HarnessDropped.php',
    stubRowClass('HarnessDropped', 'harness.dropped')
);
ValueSignalLoader::forget();
is_true(
    isset(ValueSignalLoader::catalogue()['harness.dropped']),
    'the dropped signal is discovered'
);
is_true(
    !empty(ValueSignalLoader::catalogue()['harness.dropped']['is_custom']),
    'and marked custom'
);
$after = $tool->assess(
    emptyContext(),
    array('name' => 'harness', 'parameters' => array(
        'signals' => fixtureEntries(array(
            array('Reporting', 'strong', 20, 'the shipped row'),
        )),
    )),
    array('lean' => 'threat')
);
is_same(
    $collided,
    $after,
    'and every number is byte-identical to before it was dropped'
);

out('');
out('§9.18 — both custom directories absent');

useHarnessCatalogue(HARNESS_ABSENT);
ValueSignalLoader::forget();
is_true(
    isset(ValueSignalLoader::catalogue()['harness.row']),
    'discovery finds the shipped catalogue'
);
is_same(
    3,
    count(ValueSignalLoader::errors()),
    'and says nothing about the directory that is not there'
);

/*
 * ----------------------------------------------------------------------
 * The real catalogue, the shipped profile, and §9.9's calibration rule.
 * ----------------------------------------------------------------------
 */

out('');
out('the shipped catalogue and the shipped profile agree');

useRealCatalogue();
ValueSignalLoader::forget();
$catalogue = ValueSignalLoader::catalogue();
is_same(
    11,
    count($catalogue),
    'eleven signals are discovered, one file each'
);
is_same(
    array(),
    ValueSignalLoader::errors(),
    'and none of them fails to load'
);

$profile = shippedProfile();
$configured = array();
foreach ($profile['parameters']['signals'] as $entry) {
    $configured[] = $entry['id'];
}
$discovered = array_keys($catalogue);
sort($configured);
sort($discovered);
is_same(
    $discovered,
    $configured,
    'the default profile enables exactly the eleven, by id'
);

$groups = array();
$banded = array();
foreach ($catalogue as $id => $config) {
    if (!in_array($config['group'], ValueSignalBase::GROUPS, true)) {
        $groups[] = $id;
    }
    if (array_key_exists('default_band', $config)) {
        $banded[] = $id;
    }
}
is_same(array(), $groups, 'every signal declares one of the four groups');
is_same(array(), $banded,
    'and none of them declares an editorial band — D16 removed it, and'
        . " what a signal is worth in principle is its cap's own"
        . ' contribution to the attainable bound');

$schemaless = array();
foreach ($catalogue as $id => $config) {
    if (empty($config['points_schema'])) {
        $schemaless[] = $id;
    }
}
is_same(
    array(),
    $schemaless,
    'and a points schema, so the editor can build its form'
);

$absenceUnbacked = array();
foreach ($catalogue as $id => $config) {
    if ($config['absence_key'] === null) {
        continue;
    }
    if (!isset($config['points_schema'][$config['absence_key']])) {
        $absenceUnbacked[] = $id;
    }
}
is_same(
    array(),
    $absenceUnbacked,
    'an absence key is a points key, or absence could never fire'
);

$badPoints = array();
foreach ($profile['parameters']['signals'] as $entry) {
    $signal = ValueSignalLoader::get($entry['id']);
    $errors = $signal->validateEntry($entry);
    if (!empty($errors)) {
        $badPoints = array_merge($badPoints, $errors);
    }
}
is_same(
    array(),
    $badPoints,
    'and the shipped weights validate against their own schemas'
);

out('');
out('§9.9 — the median value never leaves the low band');

$median = $tool->assess(
    medianContext(),
    $profile,
    array('lean' => 'threat')
);
is_same(
    $median['quality'],
    ledgerSum($median),
    'the median value\'s ledger sums to its quality'
);
is_same(
    'low',
    $median['band'],
    sprintf(
        'and lands in the low band (quality %d)',
        $median['quality']
    )
);
is_true(
    count(ledgerRows($median)) >= 5,
    'with a full ledger rather than an error state'
);
is_same(
    'default-v1',
    $median['profile'],
    'named with the profile that produced it'
);
is_same(
    array(),
    $median['not_counted'],
    'and nothing set aside — the median value is fully readable'
);
/*
 * The two negatives this shape cannot avoid, asserted rather than
 * assumed: nobody has sighted it and nobody has attributed it, and
 * both are what keep the median record's quality where §7.4 wants it.
 */
$absence = array();
foreach (ledgerRows($median) as $row) {
    if ($row['contribution'] < 0) {
        $absence[] = $row['id'];
    }
}
sort($absence);
is_same(
    array('attribution.galaxy', 'lifecycle.feeds',
        'sightings.volume_recency'),
    $absence,
    'the absences argue against it, which is why 21 and not 34'
);

/*
 * §7.4's rule stated universally — *no* single-org sighting-free
 * record leaves `low` — is not what the shipped weights deliver, and
 * this is the measurement that says so rather than an assertion
 * dressed to pass. One organisation reporting the same value every
 * month for fourteen months, carried by three feeds, reaches the
 * `medium` band on the strength of continuity and external presence
 * alone.
 *
 * That is not obviously wrong — a fourteen-month record on three
 * feeds is not the thin median record the rule was written about — but
 * it is not what the rule says, and weights cannot close the gap: the
 * positives a record of that shape can attain sum past `medium`
 * whatever they are individually, unless every one of them is
 * shrunk to the point of saying nothing on the values that do have
 * corroboration. It needs a **clamp in the banding**, which is phase
 * 3's section (`04-dispositions.md` §6), and this is the number it has
 * to clamp — so the check below now prints both bands, the one the
 * weights reach and the one the clamp holds it at.
 */
$persistent = medianContext();
$persistent['publication'] = array('events' => 14, 'published' => 14,
    'unpublished' => 0);
$persistent['occurrences']['total'] = 14;
$persistent['occurrences']['events'] = 14;
$persistent['temporal'] = array('occurrences' => 14,
    'with_first_seen' => 14, 'max_lag_days' => 1);
$months = array();
for ($i = 13; $i >= 0; $i--) {
    $months[date('Y-m', strtotime(sprintf('-%d months', $i), 1757232000))]
        = 1;
}
$persistent['activity'] = array('months' => $months,
    'active_months' => 14, 'span_months' => 14, 'longest_run' => 14);
$persistent['feeds'] = array('count' => 3,
    'names' => array('a', 'b', 'c'), 'checked' => 3);
$persistentVerdict = $tool->assess(
    $persistent,
    $profile,
    array('lean' => 'threat')
);
out(sprintf(
    '  note  one org, no sightings, fourteen unbroken months, three'
        . ' feeds: quality %d — band %s on the weights alone, %s'
        . ' once the thin-record clamp applies',
    $persistentVerdict['quality'],
    ValueVerdictTool::qualityBand(
        $persistentVerdict['quality'],
        $persistentVerdict['signals']['fired'],
        $profile
    ),
    $persistentVerdict['band']
));
is_same(
    $persistentVerdict['quality'],
    ledgerSum($persistentVerdict),
    'that case sums exactly too, whichever band it belongs in'
);
is_same(
    'low',
    $persistentVerdict['band'],
    'and it is the clamp, not the weights, that keeps it in low'
);

out('');
out('the malicious demo value, scored by the shipped default');

$malicious = $tool->assess(
    maliciousContext(),
    $profile,
    array('lean' => 'threat')
);
is_same(
    $malicious['quality'],
    ledgerSum($malicious),
    'its ledger sums to its quality'
);
is_same(
    'high',
    $malicious['band'],
    sprintf(
        'and reaches the high band (quality %d against the fixture\'s'
            . ' 84)',
        $malicious['quality']
    )
);
is_same(
    11,
    $malicious['signals']['evaluated'],
    'all eleven signals were evaluated'
);
is_true(
    $malicious['signals']['fired'] >= 9,
    sprintf(
        '%d of them fired',
        $malicious['signals']['fired']
    )
);
$fp = rowById($malicious, 'sightings.false_positive');
is_true(
    $fp !== null && $fp['direction'] === 'down',
    'the false positive argues against the threat lean'
);
$orgs = rowById($malicious, 'reporting.independent_orgs');
is_same(
    28,
    $orgs === null ? null : $orgs['contribution'],
    'four organisations are worth the fixture\'s own +28'
);

out('');
out('the same evidence forced to a benign lean keeps its record');

/*
 * **This block asserted the opposite until 2026-09-13**, and the
 * premise it rested on was the defect `review-2026-09-13.md` §A1
 * found. It forced a benign lean on to the malicious demo context and
 * expected `contested`, on the reasoning that *a record whose every
 * row points at a threat cannot be benign*. But those rows do not
 * point at a threat: five organisations reporting it, seven of eight
 * events published and a galaxy cluster are statements about how much
 * record there is, and a forced lean is precisely the caller saying
 * *score this against a reading I am supplying*.
 *
 * So the quality is unchanged — the record did not get thinner — and
 * rule 7 does not fire, because the only evidence on this context that
 * actually reads the value is one false-positive sighting, which
 * argues *for* the benign lean it was forced to. The contested case is
 * asserted below on a context where lean evidence really does dispute
 * the reading.
 */
$flipped = $tool->assess(
    maliciousContext(),
    $profile,
    array('lean' => 'benign')
);
is_same(
    'benign',
    $flipped['lean'],
    'a forced lean its lean evidence does not dispute survives scoring'
);
is_same(
    $malicious['quality'],
    $flipped['quality'],
    'and the record weighs the same against either reading'
);
$flippedFp = rowById($flipped, 'sightings.false_positive');
is_true(
    $flippedFp !== null && $flippedFp['direction'] === 'up',
    'the one row that reads the value flips, and supports the benign'
        . ' lean it was scored against'
);

out('');
out('rule 7 — a lean its own lean evidence disputes');

/*
 * A false-positive listing under a forced threat lean: `−38` anchored
 * at `+1` is the record saying *this is not an indicator* while the
 * reading above it says it is. That is the contradiction rule 7 was
 * written for, and it is now the only kind that reaches it.
 */
$listed = maliciousContext();
$listed['warninglist'] = array(
    'hits' => array(array('name' => 'A false-positive list',
        'category' => 'false_positive')),
    'lists_checked' => 84,
    'category' => 'false_positive',
);
$disputed = $tool->assess(
    $listed,
    $profile,
    array('lean' => 'threat')
);
is_same(
    'contested',
    $disputed['lean'],
    'a listing disputing a threat assertion lands contested'
);
is_same(
    'lean_disputed',
    $disputed['decided_by'],
    'and names the exit, so the band stops citing the lean it replaced'
);
is_true(
    $disputed['lean_weight'] < 0,
    sprintf(
        'the lean evidence is what went negative (%d), not the record'
            . ' (%d)',
        $disputed['lean_weight'],
        $disputed['quality']
    )
);
is_true(
    $disputed['quality'] > 0,
    'which leaves a well-documented contested value saying so'
);

out('');
out('§2.3 — the budget, and what a hot value still scores');

$hot = maliciousContext();
$hot['budget']['hot'] = true;
$hotVerdict = $tool->assess($hot, $profile, array('lean' => 'threat'));
$rowClass = array('sightings.volume_recency',
    'sightings.false_positive', 'attribution.galaxy',
    'attribution.technique');
$blocked = array();
foreach ($hotVerdict['not_counted'] as $item) {
    if (isset($item['id'])) {
        $blocked[] = $item['id'];
    }
}
foreach ($rowClass as $id) {
    is_true(
        in_array($id, $blocked, true),
        sprintf('%s bows out on a hot value', $id)
    );
}
is_true(
    rowById($hotVerdict, 'reporting.independent_orgs') !== null,
    'the aggregate-class signals still fire'
);
is_same(
    $hotVerdict['quality'],
    ledgerSum($hotVerdict),
    'and what is left still sums exactly'
);
is_true(
    in_array('over_correlating_values', $blocked, true),
    'with the budget itself named on the page'
);

$windowed = maliciousContext();
$windowed['budget']['window_days'] = 90;
$windowedVerdict = $tool->assess(
    $windowed,
    $profile,
    array('lean' => 'threat')
);
$policies = array();
foreach ($windowedVerdict['not_counted'] as $item) {
    if (($item['kind'] ?? null) === 'policy') {
        $policies[] = $item['id'];
    }
}
is_same(
    array('evidence.window'),
    $policies,
    'the window appears as profile policy, not as a data failure'
);

out('');
out('§4.2 — absent because excluded is not absent');

$emptied = medianContext();
$emptied['excluded']['sightings'] = 12;
$emptiedVerdict = $tool->assess(
    $emptied,
    $profile,
    array('lean' => 'threat')
);
is_same(
    null,
    rowById($emptiedVerdict, 'sightings.volume_recency'),
    'a sighting set an exclusion emptied fires no absence row'
);
is_true(
    rowById($median, 'sightings.volume_recency') !== null,
    'where the same shape with no exclusion does'
);

out('');
out('§4.3 — a fact that could not be read is not a fact');

$blind = medianContext();
$blind['missing']['feeds'] = 'No feed cache has been populated.';
$blindVerdict = $tool->assess(
    $blind,
    $profile,
    array('lean' => 'threat')
);
is_same(
    null,
    rowById($blindVerdict, 'lifecycle.feeds'),
    'the feed signal does not score its own blindness'
);
$ids = array();
foreach ($blindVerdict['not_counted'] as $item) {
    $ids[] = $item['id'];
}
is_same(
    array('lifecycle.feeds'),
    $ids,
    'it lands in not_counted with the reason'
);

out('');
out('§2 — a value with nothing to assess gets no ledger');

$absent = $tool->assess(
    absentContext(),
    $profile,
    array('lean' => 'threat')
);
is_same(array(), $absent['ledger'], 'no occurrence: no ledger');
is_same(0, $absent['quality'], 'no quality');
is_same('none', $absent['band'], 'and the band is none');
is_same(
    array(),
    $absent['not_counted'],
    'nothing set aside either — nothing was evaluated'
);
is_same(
    'default-v1',
    $absent['profile'],
    'the profile in force is still named'
);
/*
 * The bug this guards, stated as the assertion: the absence keys are
 * all true of a value nobody holds, and firing them would have scored
 * the engine's own blindness.
 */
$leaning = $tool->assess(
    absentContext(),
    $profile,
    array('lean' => 'none')
);
is_same(array(), $leaning['ledger'], 'and a none lean has none either');

cleanStubs();

out('');
out(sprintf(
    '%d checks, %d failures',
    $GLOBALS['checks'],
    $GLOBALS['failures']
));
out('');
exit($GLOBALS['failures'] > 0 ? 1 : 0);
