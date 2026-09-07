<?php
/**
 * Phase 4's invariants, without a database.
 *
 * Every one of these rules is *"the same value, with the rule on and
 * with it off"*, which is a pair of contexts and no instance at all.
 * What needs an instance is the condition-class exclusion, because its
 * whole mechanism is SQL — `05-exclusions-live-probe.php` takes that
 * and §5 item 3 with it.
 *
 * Covers §5 items 1, 2, 4 and 5, plus the two imprecisions
 * `sightings.self` cannot avoid — an anonymised sighting and an
 * occurrence whose timestamp is not its creation — which are the cases
 * a rule that filters evidence must get right or it filters a subset it
 * never admitted to.
 *
 * Run: `php prd/analyst-profile/05-exclusions-harness.php`
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

require_once APP . 'Model/ValueSignals/ValueSignalBase.php';
require_once APP . 'Model/ValueEscalations/ValueEscalationBase.php';
require_once APP . 'Lib/Tools/ValueStatsTool.php';
require_once APP . 'Lib/Tools/ValueSignalLoader.php';
require_once APP . 'Lib/Tools/ValueExclusionTool.php';
require_once APP . 'Lib/Tools/ValueLeanTool.php';
require_once APP . 'Lib/Tools/ValueChangersTool.php';
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

const NOW = 1757232000;

function shippedProfile()
{
    $raw = file_get_contents(
        APP . 'files/analyst-profiles/default-v1.json'
    );
    return json_decode($raw, true);
}

/**
 * A profile carrying one `exclusions` list and the shipped thresholds.
 *
 * @param array $exclusions
 * @return array
 */
function exclusionProfile(array $exclusions)
{
    $shipped = shippedProfile();
    return array(
        'id' => 9,
        'name' => 'harness',
        'revision' => 1,
        'parameters' => array(
            'format' => 1,
            'thresholds' => $shipped['parameters']['thresholds'],
            'escalations' => array(),
            'exclusions' => $exclusions,
        ),
    );
}

/** A viewer belonging to org 7. */
function viewer($orgId = 7)
{
    return array(
        'id' => 1,
        'org_id' => $orgId,
        'Organisation' => array('name' => 'ORGNAME'),
        'Role' => array('perm_site_admin' => 0),
    );
}

/**
 * One sighting row, in the shape `Sighting::listSightings` returns.
 *
 * @param int $orgId 0 for a row the instance's policy anonymised
 * @param int $attributeId
 * @param int $at
 * @param int $type
 * @return array
 */
function sighting($orgId, $attributeId, $at, $type = 0)
{
    return array(
        'Sighting' => array(
            'id' => $attributeId * 100 + $orgId,
            'attribute_id' => (string)$attributeId,
            'org_id' => $orgId === 0 ? null : $orgId,
            'date_sighting' => $at,
            'type' => $type,
        ),
        'Organisation' => array(
            'name' => $orgId === 0 ? '' : 'org-' . $orgId,
        ),
    );
}

/**
 * The occurrence map `Value::sightedOccurrenceIdsFor` returns.
 *
 * **Flat, and keyed by an integer**, which is what that accessor
 * actually produces — it folds CakePHP's nested result into one array
 * per occurrence. The first version of this helper built the nested
 * shape instead, and every assertion below passed against it while the
 * real thing found nothing: a rule that cannot resolve an occurrence
 * declares its sightings undecidable and excludes none of them, which
 * is indistinguishable from a rule with nothing to do. The live probe
 * is what caught it, by checking the rule's inputs rather than its
 * output.
 *
 * @param array $rows attribute id => array(org, at)
 * @return array
 */
function occurrences(array $rows)
{
    $map = array();
    foreach ($rows as $id => $row) {
        $map[(int)$id] = array(
            'event_id' => 1,
            'type' => 'ip-dst',
            'timestamp' => $row['at'],
            'deleted' => false,
            'object_id' => 0,
            'orgc_id' => $row['org'],
        );
    }
    return $map;
}

/*
 * ======================================================================
 * The plan — what a profile asks for, resolved against this viewer
 * ======================================================================
 */

out('the plan a profile resolves to');

$tool = new ValueExclusionTool();

$shipped = shippedProfile();
$plan = $tool->planFor($shipped, viewer());
is_true(
    isset($plan['rules']['sightings.self']),
    'the shipped default enables the self-sighting rule'
);
is_true(
    isset($plan['rules']['feeds.mirrored']),
    'and the mirrored-feed rule'
);
is_same(
    false,
    isset($plan['rules']['orgs.own']),
    'orgs.own ships disabled, so it is not in the plan'
);
is_same(
    array(),
    $plan['exclude_orgs'],
    'and contributes no query condition'
);
is_same(
    false,
    isset($plan['rules']['evidence.window']),
    'the window is not in the plan — the context builder applies it,'
        . ' and a plan carrying it would invite a second application'
);

$own = $tool->planFor(
    exclusionProfile(array(array('id' => 'orgs.own', 'enabled' => true))),
    viewer(7)
);
is_same(
    array(7),
    $own['exclude_orgs'],
    'enabling orgs.own excludes the viewer\'s own organisation'
);
is_same(
    array('exclude_orgs' => array(7)),
    $tool->conditionOptions($own),
    'and it reaches the query layer as one options key'
);
$orgless = $tool->planFor(
    exclusionProfile(array(array('id' => 'orgs.own', 'enabled' => true))),
    array('id' => 1)
);
is_same(
    array(),
    $orgless['exclude_orgs'],
    'a viewer with no organisation excludes nothing rather than'
        . ' excluding org 0'
);
is_same(
    array(),
    $tool->conditionOptions($orgless),
    'and adds no options key at all'
);

/*
 * ======================================================================
 * §5.2 — sightings.self, and the window that is the whole rule
 * ======================================================================
 */

out('');
out('§5.2 — the self-sighting window');

/*
 * Four sightings against one occurrence reported by org 3: the author
 * inside the hour, the author eleven months later, another org inside
 * the hour, and a row this instance would not name an organisation for.
 */
$reported = NOW - 340 * 86400;
$rows = array(
    sighting(3, 11, $reported + 600),
    sighting(3, 11, $reported + 330 * 86400),
    sighting(4, 11, $reported + 900),
    sighting(0, 11, $reported + 1200),
);
$map = occurrences(array(11 => array('org' => 3, 'at' => $reported)));

$hour = $tool->planFor(
    exclusionProfile(array(
        array('id' => 'sightings.self', 'enabled' => true,
            'within_hours' => 1),
    )),
    viewer()
);
$filtered = $tool->applyToSightings($rows, $map, $hour);
is_same(
    1,
    $filtered['excluded'],
    'at one hour, only the author\'s own fresh sighting goes'
);
is_same(
    3,
    count($filtered['rows']),
    'the other three survive'
);
is_same(
    1,
    $filtered['undecidable'],
    'and the anonymised row is counted as undecided, not excluded'
);

$year = $tool->planFor(
    exclusionProfile(array(
        array('id' => 'sightings.self', 'enabled' => true,
            'within_hours' => 8760),
    )),
    viewer()
);
$widened = $tool->applyToSightings($rows, $map, $year);
is_same(
    2,
    $widened['excluded'],
    'at a year, the author\'s later sighting goes too'
);
is_true(
    $widened['excluded'] > $filtered['excluded'],
    'so the same value produces different counts under different'
        . ' windows, which is the rule being a window'
);

$off = $tool->planFor(exclusionProfile(array()), viewer());
$untouched = $tool->applyToSightings($rows, $map, $off);
is_same(
    4,
    count($untouched['rows']),
    'with the rule off, nothing is filtered'
);
is_same(
    0,
    $untouched['excluded'],
    'and nothing is reported as excluded'
);

/*
 * The safe direction of the imprecision. An occurrence edited after the
 * fact has a timestamp later than the report it describes, so a
 * self-sighting filed in between stops looking self-adjacent — it is
 * *counted*, which is the error worth having.
 */
$edited = occurrences(array(
    11 => array('org' => 3, 'at' => $reported + 10 * 86400),
));
$afterEdit = $tool->applyToSightings($rows, $map = $edited, $hour);
is_same(
    0,
    $afterEdit['excluded'],
    'an occurrence edited after its sightings excludes none of them'
        . ' — the imprecision counts a sighting rather than'
        . ' discarding one'
);

// A sighting against an occurrence this viewer cannot resolve.
$orphan = $tool->applyToSightings(
    array(sighting(3, 99, $reported + 600)),
    occurrences(array(11 => array('org' => 3, 'at' => $reported))),
    $hour
);
is_same(
    0,
    $orphan['excluded'],
    'a sighting whose occurrence is not in the map is kept'
);
is_same(
    1,
    $orphan['undecidable'],
    'and reported as undecided'
);

/*
 * ======================================================================
 * feeds.mirrored — one upstream, counted once
 * ======================================================================
 */

out('');
out('feeds.mirrored — the provider fold, and what it must not fold');

$sources = array(
    array('id' => 1, 'name' => 'CIRCL OSINT', 'provider' => 'CIRCL',
        'scope' => 'feed'),
    array('id' => 2, 'name' => 'CIRCL mirror A', 'provider' => 'CIRCL',
        'scope' => 'feed'),
    array('id' => 3, 'name' => 'CIRCL mirror B', 'provider' => 'CIRCL',
        'scope' => 'feed'),
    array('id' => 4, 'name' => 'Botvrij', 'provider' => 'Botvrij',
        'scope' => 'feed'),
    array('id' => 5, 'name' => 'No provider', 'provider' => null,
        'scope' => 'feed'),
    array('id' => 6, 'name' => 'Another', 'provider' => null,
        'scope' => 'feed'),
    array('id' => 7, 'name' => 'Partner MISP', 'provider' => 'CIRCL',
        'scope' => 'server'),
);
$mirrored = $tool->planFor(
    exclusionProfile(array(
        array('id' => 'feeds.mirrored', 'enabled' => true,
            'dedupe_by' => 'provider'),
    )),
    viewer()
);
$folded = $tool->applyToSources($sources, $mirrored);
is_same(
    2,
    $folded['excluded'],
    'three feeds from one provider count once, so two are folded away'
);
is_same(
    5,
    count($folded['sources']),
    'leaving one per provider, both provider-less feeds, and the'
        . ' server'
);
$kept = array();
foreach ($folded['sources'] as $source) {
    $kept[] = $source['id'];
}
is_same(
    array(1, 4, 5, 6, 7),
    $kept,
    'the first of each provider survives, in order'
);
is_true(
    in_array(7, $kept, true),
    'a MISP server is never folded into a feed: it is another'
        . ' instance\'s own holding, not a mirror of one'
);
is_same(
    7,
    count($tool->applyToSources($sources, $off)['sources']),
    'with the rule off, every source counts'
);

/*
 * ======================================================================
 * §5.1 and §5.4 — the notes, and what a reader can act on
 * ======================================================================
 */

out('');
out('§5.1 — a rule that removed something says so, and only then');

$notes = $tool->notes($hour, array('sightings' => 1,
    'sightings_undecidable' => 1, 'sources' => 0));
is_same(
    1,
    count($notes),
    'one rule removed something, so there is one note'
);
is_same(
    'sightings.self',
    $notes[0]['id'],
    'carrying the id, which is what makes the row a link to the'
        . ' profile that decided it'
);
is_same(
    'policy',
    $notes[0]['reason'],
    'and the reason that says a reader could change it'
);
is_true(
    strpos($notes[0]['note'], 'within an hour of it') !== false,
    'the note states the window rather than a number of hours'
);
is_true(
    strpos($notes[0]['note'], 'could not be checked') !== false,
    'and admits the rows it could not decide about'
);

$quiet = $tool->notes($hour, array('sightings' => 0,
    'sightings_undecidable' => 0, 'sources' => 0));
is_same(
    array(),
    $quiet,
    'a rule that is on and removed nothing says nothing — the block'
        . ' answers "why doesn\'t this count", and rules that did not'
        . ' apply are noise in front of the ones that did'
);

$noUndecided = $tool->notes($hour, array('sightings' => 2,
    'sightings_undecidable' => 0, 'sources' => 0));
is_true(
    strpos($noUndecided[0]['note'], 'could not be checked') === false,
    'with nothing undecided, the note does not raise it'
);

$ownNotes = $tool->notes($own, array());
is_same(
    1,
    count($ownNotes),
    'orgs.own is reported whenever it is on, because it removes'
        . ' evidence from every count rather than from a tally this'
        . ' can measure'
);
is_true(
    strpos($ownNotes[0]['note'], 'ORGNAME') !== false,
    'and names the organisation being left out'
);

$feedNotes = $tool->notes($mirrored, array('sources' => 2));
is_true(
    strpos($feedNotes[0]['note'], 'provider') !== false,
    'the mirrored-feed note names the key it folded by, because'
        . ' MISP does not record what a feed mirrors'
);

/*
 * ======================================================================
 * End to end — the accumulator's block, and the ACL caveat
 * ======================================================================
 */

out('');
out('§5.4 — every block row is actionable or a fact, and the ACL'
    . ' caveat is neither');

/**
 * A context of a value that exists, with whatever the test needs.
 *
 * @param array $extra
 * @return array
 */
function context(array $extra = array())
{
    $base = array(
        'value' => '198.51.100.24',
        'now' => NOW,
        'as_of' => date('Y-m-d', NOW),
        'types' => array(array('type' => 'ip-dst', 'count' => 1)),
        'occurrences' => array('total' => 2, 'events' => 2, 'orgs' => 2,
            'oldest' => NOW - 86400, 'newest' => NOW),
        'publication' => array('events' => 2, 'published' => 2,
            'unpublished' => 0),
        'temporal' => array('occurrences' => 2, 'with_first_seen' => 2,
            'max_lag_days' => 0),
        'orgs' => array(
            array('id' => 1, 'name' => 'A', 'occurrences' => 1,
                'to_ids_yes' => 1, 'to_ids_no' => 0, 'newest' => NOW),
            array('id' => 2, 'name' => 'B', 'occurrences' => 1,
                'to_ids_yes' => 1, 'to_ids_no' => 0, 'newest' => NOW),
        ),
        'activity' => array('months' => array(date('Y-m', NOW) => 2),
            'active_months' => 1, 'span_months' => 1,
            'longest_run' => 1),
        'warninglist' => array('hits' => array(),
            'lists_checked' => 84, 'category' => null),
        'sightings' => array('total' => 0, 'fp' => 0),
        'galaxies' => array('clusters' => array(),
            'techniques' => array()),
        'feeds' => array('count' => 0, 'names' => array(),
            'checked' => 3),
        'budget' => array('window_days' => null, 'hot' => false,
            'occurrences' => 2, 'threshold' => null),
        'excluded' => array(),
        'missing' => array(),
        'exclusions' => array(),
    );
    return array_merge($base, $extra);
}

$engine = new ValueVerdictTool();
$profile = shippedProfile();

$excludedContext = context(array(
    'exclusions' => $tool->notes($hour, array('sightings' => 3,
        'sightings_undecidable' => 0, 'sources' => 0)),
    'excluded' => array('sightings' => 3),
));
$verdict = $engine->assess($excludedContext, $profile);

$reasons = array();
foreach ($verdict['not_counted'] as $entry) {
    $reasons[$entry['reason']] = true;
    is_true(
        in_array($entry['reason'], array('policy', 'nodata'), true),
        sprintf('"%s" carries a render-level reason', $entry['title'])
    );
}
is_true(
    isset($reasons['policy']),
    'the profile\'s own exclusion is in the block as policy'
);
/*
 * And the assessment says nothing about permissions at all — no row,
 * no key, no caveat. MISP discloses what a reader may see; that is how
 * the platform works and the people using it know it, so a page
 * repeating it per value is telling them nothing while hinting at
 * records they have no business knowing exist.
 */
$aclRows = 0;
foreach ($verdict['not_counted'] as $entry) {
    if ($entry['reason'] === 'acl') {
        $aclRows++;
    }
}
is_same(
    0,
    $aclRows,
    'no row in the block is about the ACL'
);
is_same(
    false,
    array_key_exists('acl_note', $verdict),
    'and the assessment carries no permissions caveat either'
);

/*
 * §5.5, and it is the item most likely to be got wrong: a value whose
 * every sighting the profile removed must leave the sightings signals
 * *silent*. A zero row next to a note saying the sightings were
 * excluded is the page contradicting itself, and scoring the absence
 * is the profile's own decision being counted as evidence about the
 * value.
 */
out('');
out('§5.5 — absent because excluded is not absent');

$found = null;
foreach ($verdict['ledger'] as $group) {
    foreach ($group['signals'] as $row) {
        if ($row['id'] === 'sightings.volume_recency') {
            $found = $row;
        }
    }
}
is_same(
    null,
    $found,
    'the sightings signal is silent, not a zero row'
);
is_same(
    $verdict['quality'],
    array_sum(array_map(
        function ($row) {
            return $row['contribution'];
        },
        call_user_func(function () use ($verdict) {
            $rows = array();
            foreach ($verdict['ledger'] as $group) {
                foreach ($group['signals'] as $row) {
                    $rows[] = $row;
                }
            }
            return $rows;
        })
    )),
    'and the ledger still sums to the quality exactly'
);

/*
 * §5.1's other half: the same value with the rule off scores
 * differently and the policy row disappears.
 */
$plain = $engine->assess(context(), $profile);
is_same(
    0,
    count($plain['not_counted']),
    'with nothing excluded, the block is empty'
);
is_true(
    $plain['quality'] !== $verdict['quality'],
    'and the two assessments differ, which is the exclusion changing'
        . ' inputs rather than contributions'
);

out('');
out(sprintf(
    '%d checks, %d failures',
    $GLOBALS['checks'],
    $GLOBALS['failures']
));

exit($GLOBALS['failures'] === 0 ? 0 : 1);
