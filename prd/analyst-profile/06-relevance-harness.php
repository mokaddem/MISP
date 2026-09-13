<?php
/**
 * Phase 5's invariants, without a database.
 *
 * The relevance axis is arithmetic over a handful of dates, so almost
 * all of it is checkable with no instance at all: the four states and
 * the discontinuity between them, the curve at three `decay_speed`
 * settings, the three clocks, the three `type_rule` settings, and the
 * two facts that make a timeline uncertain.
 *
 * The load-bearing assertion is the one that is *not* about relevance:
 * **the lean and the quality are byte-identical with the axis at
 * `current` and at `expired`.** D11 removed staleness from the ledger,
 * and the only mechanical proof of that is scoring the same value twice
 * with nothing but its dates moved and diffing everything else.
 *
 * Covers §6 items 3, 3b, 4, 5, 6 and 7, plus the forward-walk property
 * of the runway series — which is the phase's one repeat-offender risk,
 * because the retired decay code got exactly that wrong and no
 * assertion caught it (`ValueDecayTool::elapsed`'s own note).
 * `06-relevance-live-probe.php` takes items 5, 7 and 8 against real
 * rows, where the clock's halves come from two different queries.
 *
 * Run: `php prd/analyst-profile/06-relevance-harness.php`
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
require_once APP . 'Lib/Tools/ValueSignalLoader.php';
require_once APP . 'Lib/Tools/ValueExclusionTool.php';
require_once APP . 'Lib/Tools/ValueRelevanceTool.php';
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

function is_close($expected, $actual, $label, $epsilon = 0.0005)
{
    $GLOBALS['checks']++;
    if (abs($expected - $actual) <= $epsilon) {
        out(sprintf('  ok    %s', $label));
        return true;
    }
    $GLOBALS['failures']++;
    out(sprintf(
        '  FAIL  %s' . PHP_EOL . '        expected %s' . PHP_EOL
            . '        got      %s',
        $label,
        $expected,
        $actual
    ));
    return false;
}

/** Midnight, so a day count is not a function of the hour. */
const NOW = 1757203200;
const DAY = 86400;

function shippedProfile()
{
    return json_decode(
        file_get_contents(APP . 'files/analyst-profiles/default-v1.json'),
        true
    );
}

/**
 * The shipped profile with one `relevance` key overridden.
 *
 * @param array $overrides
 * @return array
 */
function relevanceProfile(array $overrides = array())
{
    $profile = shippedProfile();
    $profile['parameters']['relevance'] = array_merge(
        $profile['parameters']['relevance'],
        $overrides
    );
    return $profile;
}

/**
 * A context in the shape `ValueProfile::verdictContextFor` builds.
 *
 * Two organisations by default, so the clock's occurrence half has
 * something to find: the second organisation's arrival is the
 * corroboration, the first one's is the report being corroborated.
 *
 * @param array $overrides
 * @return array
 */
function context(array $overrides = array())
{
    $base = array(
        'value' => '185.234.219.24',
        'now' => NOW,
        'as_of' => date('Y-m-d', NOW),
        'types' => array(array('type' => 'ip-src', 'count' => 4)),
        'occurrences' => array(
            'total' => 4,
            'events' => 3,
            'orgs' => 2,
            'oldest' => NOW - 400 * DAY,
            'newest' => NOW,
        ),
        'publication' => array(
            'events' => 3,
            'published' => 3,
            'unpublished' => 0,
        ),
        'temporal' => array(
            'occurrences' => 4,
            'with_first_seen' => 4,
            'max_lag_days' => 1,
        ),
        'orgs' => array(
            org(1, 'CIRCL', 2, NOW - 400 * DAY, NOW - 300 * DAY, 2, 0),
            org(2, 'CERT-EU', 2, NOW - 30 * DAY, NOW, 2, 0),
        ),
        'activity' => array(
            'months' => array('2026-08' => 2, '2026-09' => 2),
            'active_months' => 2,
            'span_months' => 2,
            'longest_run' => 2,
        ),
        'warninglist' => array(
            'hits' => array(),
            'lists_checked' => 89,
            'category' => null,
        ),
        'feeds' => array('count' => 0, 'names' => array()),
        'sightings' => array('total' => 0, 'fp' => 0, 'expiration' => 0,
            'orgs' => 0, 'fp_orgs' => 0, 'fp_org_names' => array(),
            'recent' => 0, 'recent_days' => 90, 'first_stamp' => null,
            'last_stamp' => null),
        'galaxies' => array('clusters' => array(), 'techniques' => array()),
        'corroboration' => ValueRelevanceTool::corroborationFrom(
            array(),
            array()
        ),
        'budget' => array('window_days' => null, 'hot' => false,
            'occurrences' => 4, 'threshold' => null),
        'excluded' => array(),
        'missing' => array(),
        'exclusions' => array(),
    );
    return array_merge($base, $overrides);
}

/**
 * One `orgs` row, in the shape `ValueProfile::verdictOrgs` builds it —
 * `oldest` added by this phase.
 */
function org($id, $name, $count, $oldest, $newest, $yes = 1, $no = 0)
{
    return array(
        'id' => $id,
        'name' => $name,
        'occurrences' => $count,
        'to_ids_yes' => $yes,
        'to_ids_no' => $no,
        'newest' => $newest,
        'oldest' => $oldest,
    );
}

/**
 * One sighting row as `Sighting::listSightings` returns it.
 */
function sighting($orgId, $attributeId, $at, $type = 0, $eventId = 11)
{
    return array(
        'Sighting' => array(
            'id' => $attributeId * 100 + $orgId,
            'attribute_id' => (string)$attributeId,
            'event_id' => (string)$eventId,
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
 * The flat occurrence map `Value::sightedOccurrenceIdsFor` returns —
 * flat, because phase 4's §7.3 is what a nested fixture here cost.
 */
function occurrences(array $rows)
{
    $map = array();
    foreach ($rows as $id => $row) {
        $map[(int)$id] = array(
            'event_id' => 11,
            'type' => 'ip-src',
            'timestamp' => $row['at'],
            'deleted' => false,
            'object_id' => 0,
            'orgc_id' => $row['org'],
        );
    }
    return $map;
}

/**
 * The same context with the clock moved to a given elapsed time.
 *
 * **Only the corroborating organisation's join date moves**, and that
 * is the whole reason this function exists rather than a one-liner. The
 * first version moved `occurrences.newest` with it, which changed the
 * quality by 2 points and failed the D11 invariant below — correctly,
 * because `lifecycle.recency` reads that key as evidence about the
 * record. The axes share inputs; what D11 forbids is the *direction*
 * (nothing reads the relevance block), not two axes reading one date.
 *
 * An organisation whose first occurrence is old and whose latest is
 * recent is an ordinary value, so the two dates really are independent
 * here: `oldest` is when it joined, `newest` is what it has said since.
 */
function atElapsed($days, array $overrides = array())
{
    $context = context($overrides);
    $context['orgs'][1]['oldest'] = NOW - $days * DAY;
    return $context;
}

/*
 * ======================================================================
 * §6 item 3 — the four states, and the discontinuity at the boundary
 * ======================================================================
 */

out('the states, walked across the boundary (ttl 90, aging 0.33)');

$profile = shippedProfile();
$expected = array(
    0 => 'current',
    45 => 'current',
    72 => 'aging',
    90 => 'expired',
    91 => 'expired',
);
$states = array();
foreach (array_keys($expected) as $elapsed) {
    $states[$elapsed] = ValueRelevanceTool::relevanceFor(
        atElapsed($elapsed),
        $profile
    );
}
foreach ($expected as $elapsed => $state) {
    is_same(
        $state,
        $states[$elapsed]['state'],
        sprintf('elapsed %d days of 90 reads %s', $elapsed, $state)
    );
}
is_same(
    90,
    $states[0]['ttl']['days'],
    'the TTL in force is ip-src\'s 90 rather than the 180 default'
);
is_same(
    'ip-src',
    $states[0]['ttl']['type'],
    'and the type that supplied it is named'
);
is_close(
    1.0,
    $states[0]['runway'],
    'the runway is full at zero elapsed'
);
is_close(
    0.5,
    $states[45]['runway'],
    'and half at half the TTL, which is the curve being linear at'
        . ' decay_speed 1'
);
is_close(
    0.0,
    $states[90]['runway'],
    'and nothing at the TTL'
);
is_same(
    0,
    $states[90]['runway_days'],
    'the runway in days closes at exactly zero on the boundary'
);
is_same(
    -1,
    $states[91]['runway_days'],
    'and goes negative past it — expiry is an event, not a gradient'
);

out('');
out('and none of it reaches the ledger (D11)');

$engine = new ValueVerdictTool();

/*
 * **The invariant, proved off a profile knob and not off the data.**
 * `ttl_days` is a relevance-only setting, so scoring one unchanged
 * context under a 90-day TTL and under a 5-day TTL moves the axis from
 * `current` to `expired` with *no input to any other axis touched* —
 * which makes a diff of everything else a clean proof that nothing
 * downstream reads the block.
 *
 * The first version of this check moved the value's dates instead and
 * failed by 2 points, because `lifecycle.recency` legitimately reads
 * `occurrences.newest`. That failure is recorded in §7: the axes share
 * inputs, and the invariant D11 asserts is directional.
 */
$sameValue = atElapsed(30);
$current = $engine->assess($sameValue, $profile);
$expired = $engine->assess(
    $sameValue,
    relevanceProfile(array(
        'ttl_default' => 5,
        'ttl_types' => array(),
        'ttl_overrides' => array(),
    ))
);

is_same(
    'current',
    $current['relevance']['state'],
    'one context under a 90-day TTL is current'
);
is_same(
    'expired',
    $expired['relevance']['state'],
    'and under a 5-day TTL is expired'
);

/*
 * `changers` is compared separately: its relevance line is the one row
 * allowed to move, and the assertions below check that it is the only
 * one that does.
 */
function withoutRelevance(array $verdict)
{
    unset($verdict['relevance'], $verdict['changers']);
    return $verdict;
}
is_same(
    withoutRelevance($current),
    withoutRelevance($expired),
    'and every other key of the assessment is byte-identical, ledger'
        . ' rows and their evidence prose included'
);

out('');
out('and the same holds when the axis moves off the data');

$fresh = $engine->assess(atElapsed(0), $profile);
$stale = $engine->assess(atElapsed(91), $profile);
is_same(
    array('current', 'expired'),
    array($fresh['relevance']['state'], $stale['relevance']['state']),
    'moving only the corroborating organisation\'s join date moves the'
        . ' axis across the boundary'
);
is_same(
    $fresh['quality'],
    $stale['quality'],
    'and the quality is the same number'
);
is_same(
    $fresh['lean'],
    $stale['lean'],
    'the lean is the same word'
);
is_same(
    $fresh['band'],
    $stale['band'],
    'and the band is the same band'
);
is_same(
    withoutRelevance($fresh),
    withoutRelevance($stale),
    'with every other key identical too — no ledger row is emitted at'
        . ' any point on the curve'
);

$axes = array();
foreach ($current['changers'] as $row) {
    $axes[] = $row['axis'];
}
is_true(
    in_array('relevance', $axes, true),
    'the changers block gains a relevance row'
);
$relevanceRows = 0;
foreach ($current['changers'] as $row) {
    if ($row['axis'] === 'relevance') {
        $relevanceRows++;
    }
}
is_same(1, $relevanceRows, 'exactly one, which is one per axis');

$movedLines = 0;
foreach ($current['changers'] as $i => $row) {
    if ($row['text'] !== $expired['changers'][$i]['text']) {
        $movedLines++;
        is_same(
            'relevance',
            $row['axis'],
            'the only falsifiability line that moved is the relevance one'
        );
    }
}
is_same(1, $movedLines, 'and only one of the three moved at all');

$expiredLine = null;
foreach ($stale['changers'] as $row) {
    if ($row['axis'] === 'relevance') {
        $expiredLine = $row;
    }
}
is_true(
    strpos($expiredLine['text'], '90') !== false,
    'an expired value\'s line offers the TTL back rather than counting'
        . ' down from a negative runway'
);
is_true(
    strpos($expiredLine['text'], '-') === false,
    'and carries no negative number at all'
);
is_true(
    strpos(
        $states[45]['state'] === 'current'
            ? ValueRelevanceTool::changerFor($states[45])['text']
            : '',
        '45'
    ) !== false,
    'and a live value\'s line is the runway solved for the boundary'
);

/*
 * `aging` fell through to `current`'s line until 2026-09-11, so the one
 * state that exists to prompt a re-check was the one state that never
 * said so. Asserted per branch, because the bug was invisible to every
 * check above: the line was well-formed, carried the right number, and
 * answered a question the reader had already stopped asking.
 */
$currentLine = ValueRelevanceTool::changerFor($states[45]);
$agingLine = ValueRelevanceTool::changerFor($states[72]);
is_same('aging', $states[72]['state'],
    'the fixture at 72 of 90 days is the aging state');
is_true(
    $agingLine['text'] !== $currentLine['text'],
    'and an aging value no longer borrows the current one\'s line');
is_same('down', $currentLine['direction'],
    'a current value is told what happens if nobody acts');
is_same('up', $agingLine['direction'],
    'and an aging value is told what to do, like an expired one');
is_true(
    strpos($agingLine['text'], '18') !== false,
    'the aging line still carries the days it has left');
is_true(
    !preg_match('/-\d/', $agingLine['text']),
    'and no negative number — the bare hyphen check the expired line'
        . ' uses would have failed on a hyphenated word');
is_true(
    strpos($agingLine['text'], 'current') !== false,
    'and names the state one corroboration would put it back to');

/*
 * ======================================================================
 * §6 item 3b — temporal precision, the case that forced D11
 * ======================================================================
 */

out('');
out('the late-encoded phishing URL (§3.6)');

$phish = context(array(
    'types' => array(array('type' => 'url', 'count' => 1)),
    'occurrences' => array('total' => 1, 'events' => 1, 'orgs' => 1,
        'oldest' => NOW - 20 * DAY, 'newest' => NOW - 20 * DAY),
    'orgs' => array(org(9, 'ACME', 1, NOW - 20 * DAY, NOW - 20 * DAY)),
    'temporal' => array('occurrences' => 1, 'with_first_seen' => 0,
        'max_lag_days' => 61),
    'publication' => array('events' => 1, 'published' => 1,
        'unpublished' => 0),
));
$relevance = ValueRelevanceTool::relevanceFor($phish, $profile);
is_same(
    'uncertain',
    $relevance['state'],
    'inside its TTL but undated, the state is timeline uncertain rather'
        . ' than a silently wrong current'
);
is_same(
    60,
    $relevance['ttl']['days'],
    'on url\'s 60-day TTL'
);
is_true(
    strpos($relevance['uncertain_note'], 'first seen') !== false,
    'and the note names the fact the rows can answer, in the words'
        . ' MISP\'s own attribute form uses (§7.8)'
);
/*
 * One reason, not two. The second was the event-date lag, and it is
 * gone with the columns that could not support it (§7.11) — the
 * fixture still carries `max_lag_days: 61` and nothing reads it, which
 * is the point.
 */
is_same(
    1,
    count($relevance['precision']['reasons']),
    'one fact trips it, and a stale max_lag_days in the context moves'
        . ' nothing'
);
is_same(
    30,
    $relevance['assumed_days'],
    'the undated value is aged by the profile\'s stated assumption'
);
is_same(
    20,
    $relevance['recorded_days'],
    'while what the rows actually say is kept beside it'
);
is_same(
    50,
    $relevance['elapsed_days'],
    'and the runway is drawn from the sum'
);
is_true(
    !$relevance['assumed_capped'],
    'no cap needed here — 50 of 60 days leaves the lifetime intact'
);
is_true(
    $relevance['clock']['fallback'],
    'the clock fell back, because one organisation is nobody'
        . ' corroborating'
);

/*
 * ----------------------------------------------------------------------
 * A sighting is an observation date (§3.6, revised 2026-09-12)
 * ----------------------------------------------------------------------
 *
 * The rule read `first_seen` on occurrences and nothing else, so a
 * value whose clock had just been reset by a sighting was told its
 * timeline could not be trusted — while the elapsed time the warning
 * qualified was measured from that sighting's own `date_sighting`. The
 * same undated occurrences, with one foreign sighting on them.
 */
out('');
out('a sighting dates the observation the clock measures from');

$sighted = context(array(
    'types' => array(array('type' => 'url', 'count' => 1)),
    'occurrences' => array('total' => 1, 'events' => 1, 'orgs' => 1,
        'oldest' => NOW - 20 * DAY, 'newest' => NOW - 20 * DAY),
    'orgs' => array(org(9, 'ACME', 1, NOW - 20 * DAY, NOW - 20 * DAY)),
    'temporal' => array('occurrences' => 1, 'with_first_seen' => 0),
    'publication' => array('events' => 1, 'published' => 1,
        'unpublished' => 0),
    'corroboration' => ValueRelevanceTool::corroborationFrom(
        array(sighting(4, 71, NOW - 3 * DAY)),
        occurrences(array(71 => array('at' => NOW - 20 * DAY, 'org' => 9)))
    ),
));
$sightedRelevance = ValueRelevanceTool::relevanceFor($sighted, $profile);
is_same(
    'foreign_sighting',
    $sightedRelevance['clock']['kind'],
    'the clock is the sighting, reported by an organisation other than'
        . ' the one holding the occurrence'
);
is_true(
    !$sightedRelevance['uncertain'],
    'so the timeline is not uncertain, even with first_seen on none of'
        . ' the occurrences — the date it measures from is somebody'
        . ' saying when they saw it'
);
is_same(
    'current',
    $sightedRelevance['state'],
    'and the state is the one the runway earns');
is_same(
    0,
    $sightedRelevance['assumed_days'],
    'with nothing assumed, because nothing had to be');
is_true(
    $sightedRelevance['precision']['clock_is_dated'],
    'and the reading says which of the two answered it');

/*
 * The contrast that makes the rule a rule rather than a special case:
 * the same undated occurrences with the sighting removed fall back to
 * `Attribute.timestamp`, which is a row write.
 */
is_true(
    $relevance['uncertain'],
    'while the fallback clock on the same undated occurrences stays'
        . ' uncertain — a row-write date is not an observation'
);

/*
 * The other half of item 3b: the same two facts deduct in the quality
 * ledger. Two readings, two homes, one pair of measurements.
 */
$scored = $engine->assess($phish, $profile);
$precision = null;
foreach ($scored['ledger'] as $group) {
    foreach ($group['signals'] as $row) {
        if ($row['id'] === 'record.temporal_precision') {
            $precision = $row;
        }
    }
}
is_true(
    $precision !== null,
    'record.temporal_precision fired on the same value'
);
is_true(
    $precision['contribution'] < 0,
    'and deducted, which is the quality reading of an undated record'
);

out('');
out('past its TTL, uncertainty no longer decides the word');

$old = $phish;
$old['occurrences'] = array('total' => 1, 'events' => 1, 'orgs' => 1,
    'oldest' => NOW - 70 * DAY, 'newest' => NOW - 70 * DAY);
$old['orgs'] = array(org(9, 'ACME', 1, NOW - 70 * DAY, NOW - 70 * DAY));
$expiredPhish = ValueRelevanceTool::relevanceFor($old, $profile);
is_same(
    'expired',
    $expiredPhish['state'],
    'expiry survives an untrustworthy clock — an encoding date is later'
        . ' than what it stands for, so elapsed measured from it is a'
        . ' lower bound and a bound already past the TTL is past it on'
        . ' any reading'
);
is_true(
    $expiredPhish['uncertain'],
    'and the uncertainty travels beside the state rather than being'
        . ' swallowed by it — the page reads "expired · timeline'
        . ' uncertain", which is 12-assessment.md §3\'s own sentence'
);

/*
 * ======================================================================
 * §6 item 4 — three decay speeds, three curves, one TTL
 * ======================================================================
 */

out('');
out('the curve at three decay_speed settings');

$curves = array();
foreach (array(0.5, 1, 2) as $speed) {
    $points = array();
    foreach (array(0, 22, 45, 67, 90) as $elapsed) {
        $points[] = ValueRelevanceTool::runway($elapsed, 90, $speed);
    }
    $curves[(string)$speed] = $points;
}
is_true(
    $curves['0.5'] !== $curves['1'] && $curves['1'] !== $curves['2']
        && $curves['0.5'] !== $curves['2'],
    'three distinct curves'
);
foreach ($curves as $speed => $points) {
    is_close(
        1.0,
        $points[0],
        sprintf('speed %s starts at a full runway', $speed)
    );
    is_close(
        0.0,
        $points[4],
        sprintf('speed %s reaches zero at the TTL', $speed)
    );
}
is_true(
    $curves['0.5'][1] > $curves['1'][1],
    'below 1 the value is held and then falls off a cliff'
);
is_true(
    $curves['2'][1] < $curves['1'][1],
    'above 1 it drops fast and lingers'
);
is_close(
    0.5,
    ValueRelevanceTool::runway(45, 90, 1),
    'and at 1 the polynomial is linear, which is why "polynomial or'
        . ' linear" was a false choice (D8)'
);
is_close(
    0.0,
    ValueRelevanceTool::runway(5, 0, 1),
    'a TTL of nothing expires on arrival rather than dividing by zero'
);
is_close(
    1.0,
    ValueRelevanceTool::runway(-3, 90, 1),
    'and a clock in the future is a full runway, not a runway over one'
);

/*
 * ======================================================================
 * §6 item 5 — the three clocks
 * ======================================================================
 */

out('');
out('the three clock settings on one value');

$rows = array(
    // org 1 sighting its own occurrence: activity, not corroboration.
    sighting(1, 501, NOW - 3 * DAY),
    // org 3 sighting org 1's occurrence: independent.
    sighting(3, 501, NOW - 40 * DAY),
    // an anonymised report: independence cannot be decided.
    sighting(0, 501, NOW - 2 * DAY),
    // a false positive, which corroborates nothing whoever filed it.
    sighting(3, 501, NOW - 1 * DAY, 1),
);
$map = occurrences(array(501 => array('org' => 1, 'at' => NOW - 300 * DAY)));
$corroboration = ValueRelevanceTool::corroborationFrom($rows, $map);

is_same(
    NOW - 2 * DAY,
    $corroboration['last_sightings']['at'],
    'the newest sighting of any kind but a contradiction is 2 days old,'
        . ' anonymised included — `last_sighting` exists for parity with'
        . ' MISP, which counts reports rather than their authorship'
);
is_same(
    'Others',
    $corroboration['last_sightings']['name'],
    'and an anonymised report is labelled rather than left nameless'
);
is_same(
    NOW - 40 * DAY,
    $corroboration['last_foreign']['at'],
    'and the newest independent one is 40 days old'
);
is_same(
    'org-3',
    $corroboration['last_foreign']['name'],
    'named by the organisation that filed it'
);
is_same(
    501,
    $corroboration['last_foreign']['attribute_id'],
    'and by the occurrence it is attached to'
);
is_same(
    1,
    $corroboration['undecidable'],
    'the anonymised row is counted as undecidable rather than folded in'
        . ' either direction — phase 4 §7.3\'s rule, that a fold'
        . ' reporting nothing looks exactly like a fold with nothing to do'
);
is_same(
    3,
    count($corroboration['sightings_days']),
    'the false positive is in neither list: it argues against the value,'
        . ' so counting it would let a disputed value be kept current by'
        . ' the dispute'
);
$named = 0;
foreach ($corroboration['sightings_days'] as $entry) {
    if (!empty($entry['name'])) {
        $named++;
    }
}
is_same(
    count($corroboration['sightings_days']),
    $named,
    'every folded day keeps the name of the report behind it, not just'
        . ' its date — the fold that kept bare stamps rendered'
        . ' "unnamed" on five of six timeline rows, and naming what'
        . ' supplied the clock is the half of the aggregation rule §4.1'
        . ' calls load-bearing'
);

$clocked = context(array('corroboration' => $corroboration));
$answers = array();
foreach (ValueRelevanceTool::CLOCKS as $clock) {
    $answers[$clock] = ValueRelevanceTool::relevanceFor(
        $clocked,
        relevanceProfile(array('clock' => $clock))
    );
}
is_same(
    3,
    count(array_unique(array_map(
        function ($answer) {
            return $answer['clock']['at'];
        },
        $answers
    ))),
    'three settings, three different corroboration dates'
);
is_same(
    NOW - 2 * DAY,
    $answers['last_sighting']['clock']['at'],
    'last_sighting takes the newest report, which is MISP\'s own answer'
        . ' and the reason a heavily-sighted value never decays there'
);
is_same(
    NOW,
    $answers['last_occurrence']['clock']['at'],
    'last_occurrence takes the newest encoding'
);
is_same(
    NOW - 30 * DAY,
    $answers['last_independent_corroboration']['clock']['at'],
    'and the default takes the more recent of the last organisation to'
        . ' join and the last independent sighting — here the join, at 30'
        . ' days, over the sighting at 40'
);
is_same(
    'org_joined',
    $answers['last_independent_corroboration']['clock']['kind'],
    'and says which of its two halves supplied it'
);
is_same(
    'CERT-EU',
    $answers['last_independent_corroboration']['clock']['by'],
    'with a name on it (§4.1)'
);
foreach ($answers as $clock => $answer) {
    is_true(
        $answer['clock']['by'] !== null,
        sprintf('%s names what supplied the clock', $clock)
    );
}

out('');
out('the halves the clock could not read');

$hot = context(array(
    'budget' => array('window_days' => 90, 'hot' => true,
        'occurrences' => 90000, 'threshold' => 500),
));
$hotAnswer = ValueRelevanceTool::relevanceFor($hot, $profile);
is_same(
    false,
    $hotAnswer['clock']['rows_read'],
    'on an over-correlating value the sighting half was never fetched,'
        . ' and the clock says so rather than presenting half an answer'
        . ' as a whole one'
);
is_same(
    'org_joined',
    $hotAnswer['clock']['kind'],
    'while the organisation half, being an aggregate, still answers'
);

/*
 * And the axis stands down rather than running on the half it has.
 *
 * A clock missing its sighting half can only run **slow**, so the state
 * it produces can only be too stale — which is not a caveat, it is an
 * answer that is wrong in one direction. `github.com` on the
 * verification instance is the case that found it: the Assessment tab
 * read `expired, 33 days over` from a fallback date while the Sightings
 * tab, which reads the same value with no budget, drew `64 days left`
 * from an independent sighting 56 days old. Two panels, one axis,
 * opposite answers (`10-wiring.md` §14.3).
 */
is_same(
    null,
    $hotAnswer['state'],
    'so an over-correlating value gets no relevance state at all —'
        . ' a clock that can only run slow would only ever say expired'
);
is_same(
    'rows_not_read',
    $hotAnswer['reason'],
    'and the reason separates it from a value with no record, because'
        . ' this one has a record nobody read'
);
is_same(
    null,
    $hotAnswer['runway_days'],
    'with no day count to print, which is what makes the hero sentence'
        . ' end after the band and the rail draw no chart'
);
/*
 * Deliberately narrower than the clock's own `rows_read`, which is also
 * false when a sighting policy hides rows. That case keeps a state and
 * a caveat: the rows exist, the reader may not see them, and the
 * relevance card says the date may be older than the truth.
 */
$hidden = context(array('missing' => array('sightings' => true)));
$hiddenAnswer = ValueRelevanceTool::relevanceFor($hidden, $profile);
is_same(
    false,
    $hiddenAnswer['clock']['rows_read'],
    'a sighting policy also leaves the clock short of its sighting half'
);
is_true(
    $hiddenAnswer['state'] !== null,
    'but that one keeps its state and its caveat — rows the reader may'
        . ' not see are a caveat, rows nobody read are an absence'
);

/*
 * ======================================================================
 * §6 item 6 — a value with two types and two TTLs
 * ======================================================================
 */

out('');
out('two types, three type_rule settings (§3.4)');

$twoTypes = context(array('types' => array(
    array('type' => 'ip-src', 'count' => 3),
    array('type' => 'url', 'count' => 9),
)));
$expectedRules = array(
    'shortest' => array(60, 'url'),
    'longest' => array(90, 'ip-src'),
    'most_common' => array(60, 'url'),
);
foreach ($expectedRules as $rule => $answer) {
    $out = ValueRelevanceTool::relevanceFor(
        $twoTypes,
        relevanceProfile(array('type_rule' => $rule))
    );
    is_same(
        $answer[0],
        $out['ttl']['days'],
        sprintf('%s picks %d days', $rule, $answer[0])
    );
    is_same(
        $answer[1],
        $out['ttl']['type'],
        sprintf('and names %s as the type in force', $answer[1])
    );
    is_true(
        $out['ttl']['spread'],
        sprintf('%s states that the candidates differ', $rule)
    );
    is_same(
        2,
        count($out['ttl']['candidates']),
        sprintf('%s carries both candidates whatever it chose', $rule)
    );
}

$tied = context(array('types' => array(
    array('type' => 'ip-src', 'count' => 5),
    array('type' => 'url', 'count' => 5),
)));
is_same(
    'url',
    ValueRelevanceTool::relevanceFor(
        $tied,
        relevanceProfile(array('type_rule' => 'most_common'))
    )['ttl']['type'],
    'most_common breaks a tie on the shorter TTL rather than on row'
        . ' order — a rule whose answer depends on which row came back'
        . ' first changes its mind between two page loads'
);

$unlisted = context(array('types' => array(
    array('type' => 'phone-number', 'count' => 2),
)));
$fallbackTtl = ValueRelevanceTool::relevanceFor($unlisted, $profile);
is_same(
    180,
    $fallbackTtl['ttl']['days'],
    'a type the table does not name takes the default'
);
is_true(
    $fallbackTtl['ttl']['from_default'],
    'and the panel can say the number is the default rather than a'
        . ' judgement about phone numbers'
);

/*
 * ======================================================================
 * §6 item 7 — the majority case: one occurrence, nobody else
 * ======================================================================
 */

out('');
out('one occurrence, no sightings — the median value in production');

$lonely = context(array(
    'occurrences' => array('total' => 1, 'events' => 1, 'orgs' => 1,
        'oldest' => NOW - 70 * DAY, 'newest' => NOW - 70 * DAY),
    'orgs' => array(org(1, 'CIRCL', 1, NOW - 70 * DAY, NOW - 70 * DAY)),
    'temporal' => array('occurrences' => 1, 'with_first_seen' => 1,
        'max_lag_days' => 0),
    'publication' => array('events' => 1, 'published' => 1,
        'unpublished' => 0),
));
$alone = ValueRelevanceTool::relevanceFor($lonely, $profile);
is_true(
    $alone['clock']['fallback'],
    'nothing has independently corroborated it, ever, and the clock'
        . ' says fallback rather than inventing a corroboration'
);
is_same(
    NOW - 70 * DAY,
    $alone['clock']['at'],
    'so it runs from the occurrence\'s own date'
);
is_same(
    'CIRCL',
    $alone['clock']['by'],
    'still named — the panel must not render this as a blank'
);
is_same(
    'aging',
    $alone['state'],
    'and the state is a real one: 70 of 90 days leaves less than a'
        . ' third of the runway'
);
is_same(
    array(),
    $alone['clock']['events'],
    'with no corroboration events behind it'
);

out('');
out('nothing recorded at all');

$empty = context(array(
    'types' => array(),
    'occurrences' => array('total' => 0, 'events' => 0, 'orgs' => 0,
        'oldest' => null, 'newest' => null),
    'orgs' => array(),
    'temporal' => array('occurrences' => 0, 'with_first_seen' => 0,
        'max_lag_days' => null),
));
$none = ValueRelevanceTool::relevanceFor($empty, $profile);
is_same(
    null,
    $none['state'],
    'a value this viewer holds nothing about has no relevance state —'
        . ' printing "expired" would be inventing an assertion in order'
        . ' to age it'
);
is_same(
    'no_record',
    $none['reason'],
    'and says why, so the panel has something to render'
);
is_same(
    false,
    $none['uncertain'],
    'and claims no uncertainty about a timeline that does not exist'
);
$noneScored = $engine->assess($empty, $profile);
is_same(
    null,
    $noneScored['relevance']['state'],
    'the assessment of a value with no occurrences carries the same'
        . ' empty axis rather than omitting the key'
);

/*
 * ======================================================================
 * The runway series — the forward walk, which is where the retired
 * decay code went wrong and no assertion noticed
 * ======================================================================
 */

out('');
out('the runway over history walks forward (§4.2)');

$series = ValueRelevanceTool::relevanceFor($clocked, $profile);
$grid = array();
for ($i = 120; $i >= 0; $i--) {
    $grid[] = NOW - $i * DAY;
}
$points = ValueRelevanceTool::runwaySeries(
    $series,
    $grid,
    NOW - 400 * DAY
);
is_same(
    count($grid),
    count($points),
    'one point per grid day'
);
$last = $points[count($points) - 1];
is_close(
    $series['runway'],
    $last,
    'and the last point is the runway the panel prints, by construction'
        . ' rather than by coincidence'
);
/*
 * The bug this exists to prevent: a value corroborated 30 days ago must
 * not read as freshly corroborated 100 days ago. The retired decay code
 * applied a reset on days that preceded it and drew a four-month
 * plateau at full score — visible in a browser and invisible to every
 * assertion before it.
 */
$at100 = $points[20];
$at30 = $points[90];
is_true(
    $at100 < $at30,
    'a corroboration 30 days ago does not lift the curve 100 days ago'
);
$rises = 0;
for ($i = 1; $i < count($points); $i++) {
    if ($points[$i] > $points[$i - 1] + 0.0001) {
        $rises++;
    }
}
is_same(
    2,
    $rises,
    'the curve rises exactly where a corroboration lands — twice here,'
        . ' at the independent sighting and at the second organisation'
);
$before = ValueRelevanceTool::runwaySeries(
    $series,
    array(NOW - 500 * DAY, NOW - 450 * DAY),
    NOW - 400 * DAY
);
is_same(
    array(null, null),
    $before,
    'and draws nothing before the record starts — a gap is the honest'
        . ' mark, where zero is a value that has expired'
);

/*
 * ======================================================================
 * The section reader — defaults that work unedited (§1.3)
 * ======================================================================
 */

out('');
out('a profile that names none of this');

$bare = ValueRelevanceTool::section(null);
is_same(
    'last_independent_corroboration',
    $bare['clock'],
    'no profile at all still resolves the shipped clock'
);
is_same(180, $bare['ttl_default'], 'and the shipped default TTL');
is_same(1.0, $bare['decay_speed'], 'and a linear curve');

$typo = ValueRelevanceTool::section(array('parameters' => array(
    'relevance' => array(
        'clock' => 'last_indepndent_corroboration',
        'type_rule' => 'shortst',
        'decay_speed' => -4,
        'aging_fraction' => 'soon',
    ),
)));
is_same(
    'last_independent_corroboration',
    $typo['clock'],
    'a misspelled clock falls back rather than throwing — a profile is'
        . ' a hand-edited JSON document and a typo in it must not take a'
        . ' page down'
);
is_same('shortest', $typo['type_rule'], 'the same for the type rule');
is_same(1.0, $typo['decay_speed'], 'a negative speed for the curve');
is_same(0.33, $typo['aging_fraction'], 'and a word where a fraction goes');

$shippedSection = ValueRelevanceTool::section(shippedProfile());
is_same(
    12,
    count($shippedSection['ttl_days']) + 1,
    'the shipped shelf life still covers eleven types plus the default'
        . ' (§3) — ten in buckets, one override, and `ttl_days` is now'
        . ' what they resolve to'
);
is_true(
    $shippedSection['ttl_days']['ip-src'] > 60,
    'and errs generous against MISP\'s own 30-60 day decay models,'
        . ' because a short TTL drops indicators silently where a long'
        . ' one merely fails to flag a stale one (§3.5)'
);

/*
 * ==================================================================
 * D18: four buckets, one override, and an upgrade that changes nothing
 * ==================================================================
 * The load-bearing property of the whole change. The shipped table
 * uses five distinct values, four buckets plus one override reproduce
 * every one of them, and a fork still carrying the flat map resolves
 * to the same days it always did — because `updateDefaults()` never
 * touches a fork, so without the read shim every value in one would
 * silently change shelf life with nobody having edited anything.
 */
out('');
out('== shelf life: four buckets and an override (D18) ==');

$bucketed = ValueRelevanceTool::section(shippedProfile());
is_same(
    array('short' => 90, 'medium' => 120, 'long' => 365,
        'very_long' => 730),
    $bucketed['ttl_buckets'],
    'the shipped default names four buckets'
);
is_same(10, count($bucketed['ttl_types']),
    'ten of its eleven types are assigned to one');
is_same(array('url' => 60), $bucketed['ttl_overrides'],
    'and `url` is the single override — the value no bucket can hold');
is_same(
    5,
    count(array_unique(array_values($bucketed['ttl_days']))),
    'so all five distinct shipped values survive, which is why four'
        . ' buckets and not three: three cannot hold five values'
        . ' without moving real shelf life on every instance'
);

/*
 * The shape every existing fork carries, read verbatim.
 */
$flat = shippedProfile();
$flat['parameters']['relevance'] = array_diff_key(
    $flat['parameters']['relevance'],
    array('ttl_default' => 1, 'ttl_buckets' => 1, 'ttl_types' => 1,
        'ttl_overrides' => 1)
);
$flat['parameters']['relevance']['ttl_days'] = array(
    'default' => 180,
    'ip-src' => 90, 'ip-dst' => 90,
    'domain' => 120, 'hostname' => 120, 'url' => 60, 'email-src' => 120,
    'md5' => 730, 'sha1' => 730, 'sha256' => 730,
    'btc' => 365, 'filename' => 365,
);
$forked = ValueRelevanceTool::section($flat);

$a = $bucketed['ttl_days'];
$b = $forked['ttl_days'];
ksort($a);
ksort($b);
is_same($a, $b,
    'a fork carrying the pre-D18 flat map resolves to byte-identical'
        . ' per-type days — the upgrade changes nothing');
is_same($bucketed['ttl_default'], $forked['ttl_default'],
    'and the same default');
is_same(array(), $forked['ttl_types'],
    'the flat map assigns nothing to a bucket, which is exactly what'
        . ' "no buckets" means');
is_same(11, count($forked['ttl_overrides']),
    'every type it named is its own override — the reading is exact,'
        . ' not approximate');

/*
 * The half-edited fork: a document carrying both shapes. The current
 * keys win, because the editor is what wrote them — a fork the editor
 * upgraded while a stale `ttl_days` block sat below it must not keep
 * resolving the stale default.
 */
$mixed = shippedProfile();
$mixed['parameters']['relevance']['ttl_days'] = array(
    'default' => 5,
    'sha256' => 10,
    'zzz-not-a-real-type' => 42,
);
$mixedSection = ValueRelevanceTool::section($mixed);
is_same(180, $mixedSection['ttl_default'],
    'where a document carries both shapes the current keys win and the'
        . ' legacy map is ignored outright — the shapes do not blend');
is_same(730, $mixedSection['ttl_days']['sha256'],
    'so a stale flat entry cannot shadow a bucket assignment, which is'
        . ' what a half-edited fork would otherwise do');
is_same(false, isset($mixedSection['ttl_days']['zzz-not-a-real-type']),
    'and nothing from the ignored map leaks through');
is_same(60, $mixedSection['ttl_days']['url'],
    'while the current override list is untouched');


$nonsense = ValueRelevanceTool::section(array('parameters' => array(
    'relevance' => array(
        'ttl_buckets' => array('short' => 0, 'made_up' => 7,
            'long' => 'soon'),
        'ttl_types' => array('ip-src' => 'made_up', 'md5' => 'long'),
        'ttl_overrides' => array('url' => -1, 'domain' => 30),
        'ttl_default' => 'whenever',
    ),
)));
is_same(90, $nonsense['ttl_buckets']['short'],
    'a bucket set to zero keeps its default — a shelf life of no days'
        . ' is not a shelf life');
is_same(false, isset($nonsense['ttl_buckets']['made_up']),
    'a bucket that is not one of the four is not invented');
is_same(365, $nonsense['ttl_buckets']['long'],
    'and a word where days go falls back');
is_same(false, isset($nonsense['ttl_types']['ip-src']),
    'a type assigned to a bucket that does not exist is unassigned'
        . ' rather than throwing — a profile is a hand-edited document');
is_same(365, $nonsense['ttl_days']['md5'],
    'while the assignment beside it still resolves, to its own'
        . " bucket's days");
is_same(false, isset($nonsense['ttl_days']['url']),
    'a negative override is dropped');
is_same(30, $nonsense['ttl_days']['domain'], 'and a good one kept');
is_same(180, $nonsense['ttl_default'], 'a nonsense default falls back');

/*
 * `type_rule` still compares days. Comparing bucket ordinals would be
 * a different rule wearing the same name.
 */
$spread = ValueRelevanceTool::relevanceFor(
    context(array(
        'types' => array(
            array('type' => 'md5', 'count' => 1),
            array('type' => 'url', 'count' => 9),
        ),
    )),
    shippedProfile()
);
is_same(60, $spread['ttl']['days'],
    '`shortest` over a very-long bucket and a 60-day override picks'
        . ' the override — the comparison is on days, so shortest'
        . ' still means shortest');
is_same('url', $spread['ttl']['type'], 'and names the type it came from');
is_same('override', $spread['ttl']['from'],
    'saying the number came from an override');
$bucketPick = ValueRelevanceTool::relevanceFor(
    context(array(
        'types' => array(array('type' => 'md5', 'count' => 1)),
    )),
    shippedProfile()
);
is_same('bucket', $bucketPick['ttl']['from'],
    'while a bucketed type says so');
is_same('very_long', $bucketPick['ttl']['bucket'],
    'and names its bucket, so a page can say 730 days, very long'
        . ' rather than quoting a bare number');
$unnamed = ValueRelevanceTool::relevanceFor(
    context(array(
        'types' => array(array('type' => 'text', 'count' => 3)),
    )),
    shippedProfile()
);
is_same('default', $unnamed['ttl']['from'],
    'and a type nobody named falls to the default');
is_same(180, $unnamed['ttl']['days'], 'at 180 days');

out('');
out(sprintf(
    '%d checks, %d failures',
    $GLOBALS['checks'],
    $GLOBALS['failures']
));

exit($GLOBALS['failures'] === 0 ? 0 : 1);
