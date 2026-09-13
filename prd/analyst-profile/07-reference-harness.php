<?php
/**
 * Phase 6's two maps, without a database.
 *
 * Both are override sets, so nearly every assertion here is *the same
 * value with the entry and without it* — a pair of profiles and no
 * instance at all. The one that matters most is the first: **an empty
 * `org_trust` map leaves every ledger row byte-identical to phase 2's
 * output**, which is `01-profile.md` §1.3's *"empty means as before"*
 * stated as a number rather than as a promise.
 *
 * Covers §5 items 1–5, 8, 11 and 12, plus the two cases the arithmetic
 * has to get right or a weighting quietly becomes something else: a
 * grade must never flip a signal's sign (item 4's reason, generalised
 * to the extra-organisation term), and the rounding must happen once at
 * the end rather than per organisation.
 *
 * What needs an instance is the uuid→id join, the shipped map against
 * real warninglist rows, and the day-one case —
 * `07-reference-live-probe.php` takes §5 items 6, 7, 9 and 10.
 *
 * Run: `php prd/analyst-profile/07-reference-harness.php`
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
require_once APP . 'Lib/Tools/ValueTrustTool.php';
require_once APP . 'Lib/Tools/WarninglistCategory.php';
require_once APP . 'Lib/Tools/ValueExclusionTool.php';
require_once APP . 'Lib/Tools/ValueLeanTool.php';
require_once APP . 'Lib/Tools/ValueChangersTool.php';
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

define('NOW', 1757000000);

/**
 * @return array
 */
function shippedProfile()
{
    return json_decode(
        file_get_contents(APP . 'files/analyst-profiles/default-v1.json'),
        true
    );
}

/**
 * The shipped profile with a `reference` section replaced.
 *
 * @param array $reference
 * @return array
 */
function trustProfile(array $reference)
{
    $profile = shippedProfile();
    $profile['parameters']['reference'] = array_merge(
        $profile['parameters']['reference'],
        $reference
    );
    return $profile;
}

/** Four organisations, with uuids as a real profile would key them. */
define('ORG_UUIDS', array(
    9 => '55f6ea5e-2c60-40e5-964f-47a8950d210f',
    30 => '55f6ea5f-fd34-43b8-ac1d-40cb950d210f',
    31 => '55f6ea5f-03c4-42c7-83bb-4984950d210f',
    5 => '0c3e884b-3746-4ef6-9082-ce8dec43215a',
));

define('ORG_NAMES', array(
    9 => 'CIRCL',
    30 => 'CthulhuSPRL.be',
    31 => 'FOXIT-CERT',
    5 => 'ORGNAME',
));

/**
 * The trust block `ValueProfile::verdictTrust` would build, with the
 * uuid→id join done here instead of in SQL.
 *
 * @param array $grades uuid => grade
 * @param array $scale Overrides for `org_trust_scale`
 * @param array $absent uuids to leave unresolved, as an organisation
 *                      this instance does not have
 * @return array
 */
function trustBlock(array $grades, array $scale = array(),
    array $absent = array()
) {
    $profile = trustProfile(array(
        'org_trust' => $grades,
        'org_trust_scale' => array_merge(
            shippedProfile()['parameters']['reference']
                ['org_trust_scale'],
            $scale
        ),
    ));
    $plan = ValueTrustTool::planFor($profile);
    $present = array();
    foreach (ORG_UUIDS as $id => $uuid) {
        if (in_array($uuid, $absent, true)) {
            continue;
        }
        $present[$uuid] = array(
            'id' => $id,
            'name' => ORG_NAMES[$id],
        );
    }
    return ValueTrustTool::contextFrom($plan, $present);
}

/**
 * A context in the shape `ValueProfile::verdictContextFor` builds it,
 * with four reporting organisations and a sighting history spread
 * across three of them.
 *
 * Four is not arbitrary: it is the fixture's malicious value, whose
 * `reporting.independent_orgs` row is `+28` at the cap, and §2.5's own
 * worked example grades exactly those four `B/B/C/D`.
 *
 * @param array $extra
 * @return array
 */
function context(array $extra = array())
{
    $orgs = array();
    foreach (ORG_UUIDS as $id => $uuid) {
        $orgs[] = array(
            'id' => $id,
            'uuid' => $uuid,
            'name' => ORG_NAMES[$id],
            'occurrences' => 3,
            'to_ids_yes' => 3,
            'to_ids_no' => 0,
            'newest' => NOW - 86400,
            'oldest' => NOW - 300 * 86400,
        );
    }
    $base = array(
        'value' => '185.234.219.24',
        'now' => NOW,
        'as_of' => date('Y-m-d', NOW),
        'types' => array(array('type' => 'ip-dst', 'count' => 12)),
        'occurrences' => array('total' => 12, 'events' => 12,
            'orgs' => 4, 'oldest' => NOW - 300 * 86400,
            'newest' => NOW - 86400),
        'publication' => array('events' => 12, 'published' => 10,
            'unpublished' => 2),
        'temporal' => array('occurrences' => 12, 'with_first_seen' => 9,
            'max_lag_days' => 4),
        'orgs' => $orgs,
        'trust' => trustBlock(array()),
        'activity' => array(
            'months' => array(date('Y-m', NOW) => 12),
            'active_months' => 6, 'span_months' => 10,
            'longest_run' => 4, 'gaps' => 1),
        'warninglist' => array('hits' => array(),
            'lists_checked' => 84, 'category' => null,
            'category_sources' => array()),
        'sightings' => array(
            'total' => 24,
            'fp' => 0,
            'expiration' => 0,
            'orgs' => 3,
            'fp_orgs' => 0,
            'fp_org_names' => array(),
            'fp_org_list' => array(),
            'by_org' => array(9 => 12, 30 => 8, 31 => 4),
            'by_org_fp' => array(),
            'anonymous' => 0,
            'anonymous_fp' => 0,
            'recent' => 6,
            'recent_days' => 30,
            'first_stamp' => NOW - 200 * 86400,
            'last_stamp' => NOW - 3 * 86400,
            'fp_last_stamp' => null,
        ),
        'galaxies' => array(
            'clusters' => array('APT28' => 2),
            'techniques' => array('T1071.001' => 3)),
        'feeds' => array('count' => 1, 'names' => array('CIRCL OSINT'),
            'checked' => 3),
        'corroboration' => ValueRelevanceTool::corroborationFrom(
            array(),
            array()
        ),
        'budget' => array('window_days' => null, 'hot' => false,
            'occurrences' => 12, 'threshold' => 10000),
        'excluded' => array(),
        'missing' => array(),
        'exclusions' => array(),
    );
    return array_merge($base, $extra);
}

/**
 * One row out of a scored ledger, by signal id.
 *
 * @param array $verdict
 * @param string $id
 * @return array|null
 */
function rowFor(array $verdict, $id)
{
    foreach ($verdict['ledger'] as $group) {
        foreach ($group['signals'] as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }
    }
    return null;
}

/**
 * Every ledger row's contribution, keyed by signal id.
 *
 * @param array $verdict
 * @return array
 */
function contributions(array $verdict)
{
    $out = array();
    foreach ($verdict['ledger'] as $group) {
        foreach ($group['signals'] as $row) {
            $out[$row['id']] = $row['contribution'];
        }
    }
    ksort($out);
    return $out;
}

$engine = new ValueVerdictTool();
$shipped = shippedProfile();

// -------------------------------------------------------------------
out('the shipped scale, and what it claims as its authority');

$scale = $shipped['parameters']['reference']['org_trust_scale'];
is_same(8, count($scale), 'seven grades and unrated ship in the'
    . ' profile, so an analyst can move any of them (§2.3)');
is_true(
    $scale['C'] == $scale['F'] && $scale['F'] == $scale['unrated'],
    'F equals C equals unrated — the taxonomy says f=50=c, so F is'
        . ' "reliability cannot be judged" and not the bottom of the'
        . ' scale (review A5)'
);
is_true(
    $scale['A'] > $scale['B'] && $scale['B'] > $scale['C']
        && $scale['C'] > $scale['D'] && $scale['D'] > $scale['E']
        && $scale['E'] >= $scale['G'],
    'and the ordering is A > B > C = F = unrated > D > E >= G'
);
is_same(0.0, (float)$scale['G'], 'G is a true zero: an accusation of'
    . ' deception, not a quality judgement');
is_true(
    (float)$scale['E'] > 0.0,
    'E is not, because unreliable still means sometimes right and a'
        . ' floor keeps that evidence discounted rather than erased'
);
is_same(
    array(),
    $shipped['parameters']['reference']['org_trust'],
    'and the shipped map is empty, so nothing is graded on day one'
);
is_same(
    array(),
    $shipped['parameters']['reference']['warninglist_category'],
    'as is the category map — under V1 the shipped knowledge is in'
        . ' code, so "empty means as before" holds without exception'
        . ' (§3.2)'
);

// -------------------------------------------------------------------
out('');
out('§5 item 1 — an empty map leaves the ledger byte-identical');

$plain = $engine->assess(context(), $shipped);
$baseline = contributions($plain);
is_true(!empty($baseline), 'the value scores something to compare');

/*
 * The strong form: the map being empty takes the mechanism out of the
 * path entirely, so an analyst who has edited the *scale* and graded
 * nobody has changed nothing. Without that branch, `unrated` at 0.5
 * would silently halve every score on the instance — and every one of
 * them would carry a weighting note explaining a weighting nobody
 * asked for.
 */
$editedScaleOnly = trustProfile(array(
    'org_trust' => array(),
    'org_trust_scale' => array('unrated' => 0.5),
));
$unmoved = $engine->assess(
    context(array('trust' => trustBlock(
        array(),
        array('unrated' => 0.5)
    ))),
    $editedScaleOnly
);
is_same(
    $baseline,
    contributions($unmoved),
    'the map is the switch: unrated moved to 0.5 with nobody graded'
        . ' changes not one contribution'
);
is_same(
    $plain['quality'],
    $unmoved['quality'],
    'and therefore not the quality either'
);
is_same(
    false,
    trustBlock(array())['in_force'],
    'an empty map reports itself as not in force'
);
is_same(
    false,
    trustBlock(array('not-a-uuid-at-all' => 'Z'))['in_force'],
    'and so does a map whose only entry is a grade that does not'
        . ' exist — otherwise a typo would put the mechanism in the'
        . ' path and leave every factor at 1.0, which reads on the'
        . ' page exactly like a working map'
);
is_same(
    array('not-a-uuid-at-all' => 'Z'),
    trustBlock(array('not-a-uuid-at-all' => 'Z'))['invalid'],
    'the typo is reported rather than dropped'
);

$emptyRow = rowFor($plain, 'reporting.independent_orgs');
is_same(28, $emptyRow['contribution'], 'four organisations at the cap,'
    . ' which is the fixture\'s own +28');
is_true(
    strpos($emptyRow['evidence'], 'reliability') === false,
    'and the row says nothing about weighting, because nothing'
        . ' happened (§2.5)'
);

// -------------------------------------------------------------------
out('');
out('§5 item 2 — the four organisations graded B/B/C/D');

$graded = array(
    ORG_UUIDS[9] => 'B',
    ORG_UUIDS[30] => 'B',
    ORG_UUIDS[31] => 'C',
    ORG_UUIDS[5] => 'D',
);
$bbcd = trustProfile(array('org_trust' => $graded));
$scored = $engine->assess(
    context(array('trust' => trustBlock($graded))),
    $bbcd
);
$row = rowFor($scored, 'reporting.independent_orgs');
/*
 * 7 × (1.10 + 1.10 + 1.00 + 0.75) = 27.65, under the cap of 28 and
 * rounded once at the end. Rounding per organisation would give
 * 8 + 8 + 7 + 5 = 28 — the same number as the unweighted row, so the
 * weighting would be invisible for exactly the reason §2.4 forbids it.
 */
is_same(28, (int)round(7 * 1.10) + (int)round(7 * 1.10)
    + (int)round(7 * 1.00) + (int)round(7 * 0.75),
    'rounding per organisation would reproduce the unweighted 28');
is_same(28, $emptyRow['contribution'], 'which the ungraded row is');
is_same(28, $row['contribution'], 'and the graded row is 28 too — the'
    . ' cap, because 27.65 rounds to 28 and the cap is 28');

/*
 * So the demonstration needs a value the cap is not sitting on. Three
 * organisations put the unweighted row at 21.
 */
$three = context();
array_pop($three['orgs']);
$three['occurrences']['orgs'] = 3;
$threeGraded = array(
    ORG_UUIDS[9] => 'B',
    ORG_UUIDS[30] => 'B',
    ORG_UUIDS[31] => 'D',
);
$threePlain = $engine->assess($three, $shipped);
$three['trust'] = trustBlock($threeGraded);
$threeScored = $engine->assess(
    $three,
    trustProfile(array('org_trust' => $threeGraded))
);
$before = rowFor($threePlain, 'reporting.independent_orgs');
$after = rowFor($threeScored, 'reporting.independent_orgs');
is_same(21, $before['contribution'], 'three ungraded organisations'
    . ' are worth 21');
is_same(21, (int)round(7 * (1.10 + 1.10 + 0.75)),
    '7 × (1.10 + 1.10 + 0.75) = 20.65, rounded once');
is_same(21, $after['contribution'], 'and the graded row is 21');

/*
 * 20.65 rounding to 21 is not the assertion this item wants either.
 * B/B/D is a shade under three unrated voices, and a shade is what a
 * grade *should* be — so the case that shows the mechanism is a grade
 * that moves the number by more than a rounding step.
 */
$harsh = array(
    ORG_UUIDS[9] => 'D',
    ORG_UUIDS[30] => 'D',
    ORG_UUIDS[31] => 'D',
);
$three['trust'] = trustBlock($harsh);
$harshScored = $engine->assess(
    $three,
    trustProfile(array('org_trust' => $harsh))
);
$harshRow = rowFor($harshScored, 'reporting.independent_orgs');
is_same(16, $harshRow['contribution'],
    'three D-graded organisations are worth 16, not 21 (7 × 2.25)');
is_true(
    strpos($harshRow['evidence'], 'CIRCL (D)') === 0,
    'and the evidence names the grade beside the organisation, which'
        . ' is §2.5\'s own worked example'
);
is_true(
    strpos(
        $harshRow['evidence'],
        'weighted by your reliability grades'
    ) !== false,
    'with the clause that says a weighting happened at all — the one'
        . ' part of this feature that moves a number for a reason'
        . ' invisible in the data'
);

$sum = 0;
foreach ($harshScored['ledger'] as $group) {
    foreach ($group['signals'] as $ledgerRow) {
        $sum += $ledgerRow['contribution'];
    }
}
is_same(
    $harshScored['quality'],
    $sum,
    'and the ledger still sums to the quality exactly — §5.1\'s'
        . ' invariant does not get an exemption for weighted rows'
);

// -------------------------------------------------------------------
out('');
out('§5 item 3 — every organisation graded A rises, and respects the cap');

$allA = array_fill_keys(array_values(ORG_UUIDS), 'A');
$three['trust'] = trustBlock(array_slice($allA, 0, 3, true));
$rising = $engine->assess(
    $three,
    trustProfile(array('org_trust' => $allA))
);
$risingRow = rowFor($rising, 'reporting.independent_orgs');
is_same(26, $risingRow['contribution'],
    'three A-graded organisations are worth 26, up from 21 (7 × 3.75)');

$capped = $engine->assess(
    context(array('trust' => trustBlock($allA))),
    trustProfile(array('org_trust' => $allA))
);
$cappedRow = rowFor($capped, 'reporting.independent_orgs');
is_same(28, $cappedRow['contribution'],
    'four of them would be 35 and the cap holds it at 28 — the cap is'
        . ' applied after the weighting, not before it');

// -------------------------------------------------------------------
out('');
out('§5 item 4 — a grade may not flip a sign, and G means nothing');

$oneE = array(ORG_UUIDS[9] => 'E');
$three['trust'] = trustBlock($oneE);
$fallen = $engine->assess(
    $three,
    trustProfile(array('org_trust' => $oneE))
);
$fallenRow = rowFor($fallen, 'reporting.independent_orgs');
is_same(16, $fallenRow['contribution'],
    'one E among three drops the row to 16 (7 × 2.25)');
is_true($fallenRow['contribution'] > 0, 'and it stays positive — a'
    . ' weighting that flipped a signal\'s sign would make `direction`'
    . ' unstable for a reason nobody can see');

$oneF = array(ORG_UUIDS[9] => 'F');
$three['trust'] = trustBlock($oneF);
$neutral = $engine->assess(
    $three,
    trustProfile(array('org_trust' => $oneF))
);
$neutralRow = rowFor($neutral, 'reporting.independent_orgs');
is_same(21, $neutralRow['contribution'],
    'one F among three changes the number not at all, identically to'
        . ' unrated — grading an organisation F records that you'
        . ' considered them');
is_true(
    strpos(
        $neutralRow['evidence'],
        'weighted by your reliability grades'
    ) !== false,
    'and the clause still appears, because a grade did touch the row'
        . ' even though it moved nothing'
);

$oneG = array(ORG_UUIDS[9] => 'G');
$three['trust'] = trustBlock($oneG);
$deceptive = $engine->assess(
    $three,
    trustProfile(array('org_trust' => $oneG))
);
$gRow = rowFor($deceptive, 'reporting.independent_orgs');
is_same(14, $gRow['contribution'],
    'a G-graded organisation is not a reporting voice at all: three'
        . ' organisations score as two');
is_true(
    strpos($gRow['evidence'], 'CIRCL (G) counts for nothing') !== false,
    'and the evidence names it, or the row is short by 7 with no'
        . ' explanation anywhere on the page'
);

/*
 * The other direction, which is the half a naive implementation gets
 * wrong: a deceptive organisation whitewashing a value it controls.
 * Its false-positive sightings have to count for as little as its
 * reports.
 */
$fpContext = context(array('sightings' => array(
    'total' => 24, 'fp' => 11, 'expiration' => 0, 'orgs' => 3,
    'fp_orgs' => 2, 'fp_org_names' => array('CIRCL', 'FOXIT-CERT'),
    'fp_org_list' => array(
        array('id' => 9, 'name' => 'CIRCL'),
        array('id' => 31, 'name' => 'FOXIT-CERT'),
    ),
    'by_org' => array(9 => 12, 30 => 8, 31 => 4),
    'by_org_fp' => array(9 => 9, 31 => 2),
    'anonymous' => 0, 'anonymous_fp' => 0,
    'recent' => 6, 'recent_days' => 30,
    'first_stamp' => NOW - 200 * 86400,
    'last_stamp' => NOW - 3 * 86400,
    'fp_last_stamp' => NOW - 9 * 86400,
)));
$fpPlain = $engine->assess($fpContext, $shipped);
$fpRow = rowFor($fpPlain, 'sightings.false_positive');
is_same(-26, $fpRow['contribution'],
    'eleven false positives from two organisations hit the -26 cap'
        . ' ungraded (-3 × 11 - 4 = -37, capped)');

$fpContext['trust'] = trustBlock($oneG);
$fpGraded = $engine->assess(
    $fpContext,
    trustProfile(array('org_trust' => $oneG))
);
$fpGradedRow = rowFor($fpGraded, 'sightings.false_positive');
is_same(-6, $fpGradedRow['contribution'],
    'with CIRCL graded G its nine filings count for nothing and it is'
        . ' not another voice either: -3 × 2 + -4 × max(0, 1 - 1)');
is_true(
    strpos($fpGradedRow['evidence'], 'counts for nothing') !== false,
    'and the row says which organisation it stopped counting'
);

/*
 * The extra-organisation term needed the clamp for the same reason the
 * headcount did. One E-graded filer sums to 0.25 voices, so `Σ − 1` is
 * −0.75, and `per_extra_org × −0.75` is **+3** — a false-positive
 * signal contributing *towards* a threat lean. Found by writing the
 * arithmetic out, not by the page.
 */
$soleE = context(array(
    'sightings' => array_merge($fpContext['sightings'], array(
        'fp' => 4, 'fp_orgs' => 1,
        'fp_org_names' => array('CIRCL'),
        'fp_org_list' => array(array('id' => 9, 'name' => 'CIRCL')),
        'by_org_fp' => array(9 => 4),
    )),
    'trust' => trustBlock(array(ORG_UUIDS[9] => 'E')),
));
$soleRow = rowFor(
    $engine->assess(
        $soleE,
        trustProfile(array('org_trust' => array(ORG_UUIDS[9] => 'E')))
    ),
    'sightings.false_positive'
);
is_true(
    $soleRow['contribution'] < 0,
    'a single E-graded filer still contributes against the value: the'
        . ' extra-organisation term is clamped at zero, or a fraction'
        . ' of a voice would turn a false positive into corroboration'
);
is_same(-3, $soleRow['contribution'],
    'four filings at 0.25 are worth one, and no extra-voice penalty');

// -------------------------------------------------------------------
out('');
out('§5 item 5 — the scale is data');

$dNudged = trustBlock($harsh, array('D' => 0.95));
$three['trust'] = $dNudged;
$nudged = $engine->assess(
    $three,
    trustProfile(array(
        'org_trust' => $harsh,
        'org_trust_scale' => array('D' => 0.95),
    ))
);
$nudgedRow = rowFor($nudged, 'reporting.independent_orgs');
is_same(20, $nudgedRow['contribution'],
    'the same three D grades with D at 0.95 are worth 20, not 16 —'
        . ' proving the scale is a value in the profile');
is_same(
    0.95,
    ValueTrustTool::factorForGrade($dNudged, 'D'),
    'and the plan reports the edited factor'
);
is_same(
    1.1,
    ValueTrustTool::factorForGrade($dNudged, 'B'),
    'while the grades nobody edited keep the shipped default'
);
is_same(
    0.0,
    ValueTrustTool::factorForGrade(
        trustBlock($harsh, array('D' => -4)),
        'D'
    ),
    'a negative factor is floored at zero rather than honoured — a'
        . ' negative multiplier is the sign flip item 4 forbids,'
        . ' arrived at through the scale instead of through a grade'
);

// -------------------------------------------------------------------
out('');
out('§5 item 8 — a grade for an organisation this instance has never'
    . ' heard of');

$stranger = 'ffffffff-0000-0000-0000-ffffffffffff';
$mixed = trustBlock(
    array($stranger => 'A', ORG_UUIDS[9] => 'D'),
    array(),
    array($stranger)
);
is_same(
    array($stranger => 'A'),
    $mixed['unknown'],
    'it is kept, ignored, and reported — never deleted, because the'
        . ' organisation may return or the profile may be shared (§4)'
);
is_same(
    array(9 => 'D'),
    $mixed['grades'],
    'while the grade that did resolve is in force'
);
$three['trust'] = $mixed;
$survives = $engine->assess(
    $three,
    trustProfile(array(
        'org_trust' => array($stranger => 'A', ORG_UUIDS[9] => 'D'),
    ))
);
is_true(
    rowFor($survives, 'reporting.independent_orgs') !== null,
    'and the assessment scores rather than erroring'
);
is_same(19, rowFor($survives, 'reporting.independent_orgs')
    ['contribution'],
    '7 × (0.75 + 1.00 + 1.00) = 18.25 — the absent A weights nobody');

/*
 * §4's second row, which is the case that must stay quiet: a grade for
 * an organisation this instance *does* have and this value does not.
 */
$elsewhere = trustBlock(array(ORG_UUIDS[5] => 'A'));
$three['trust'] = $elsewhere;
$quiet = $engine->assess(
    $three,
    trustProfile(array('org_trust' => array(ORG_UUIDS[5] => 'A')))
);
$quietRow = rowFor($quiet, 'reporting.independent_orgs');
is_same(21, $quietRow['contribution'],
    'a grade for an organisation with no occurrence of this value'
        . ' changes nothing');
is_true(
    strpos($quietRow['evidence'], 'reliability') === false,
    'and says nothing, because a note would appear on every value the'
        . ' analyst has ever graded anybody for (§4, row 2)'
);

// -------------------------------------------------------------------
out('');
out('the sightings volume curve, weighted before the logarithm');

$volumePlain = rowFor($plain, 'sightings.volume_recency');
is_same(20, $volumePlain['contribution'],
    '24 sightings against a saturation of 50 pay 20 of the 24:'
        . ' log(25)/log(51) × 24');

$volumeContext = context(array('trust' => trustBlock($oneG)));
$volumeGraded = rowFor(
    $engine->assess(
        $volumeContext,
        trustProfile(array('org_trust' => $oneG))
    ),
    'sightings.volume_recency'
);
is_true(
    $volumeGraded['contribution'] < 20,
    'CIRCL graded G removes its twelve sightings from the curve'
);
is_same(16, $volumeGraded['contribution'],
    'twelve weighted sightings instead of 24: log(13)/log(51) × 24');
is_true(
    strpos($volumeGraded['evidence'], 'weighted to 12') !== false,
    'and the evidence names the weighted count, because it is the one'
        . ' number the page shows nowhere else'
);
is_true(
    strpos($volumeGraded['signal'], '24 sightings') !== false,
    'while the claim still states what the rows say — the weighting'
        . ' is the analyst\'s judgement, not a correction to the facts'
);

/*
 * The reason the factor goes in before the curve rather than onto the
 * finished points: the whole judgement in this signal is that volume
 * saturates, so a discount applied after the logarithm would not be
 * discounting volume at all. Half the sightings is 80% of the points,
 * not half of them — which is the curve doing its job.
 */
is_true(
    $volumeGraded['contribution'] !== (int)round(20 * 0.5),
    'halving the sightings is not halving the points, which is what'
        . ' weighting after the curve would have done'
);

// -------------------------------------------------------------------
out('');
out('an organisation this viewer cannot name is unrated');

/*
 * Six anonymised sightings beside CIRCL's twelve. Graded `G`, CIRCL's
 * twelve go and the six stay, so the row scores what six sightings
 * score — not what none score, and not what eighteen do.
 */
$anonymised = array_merge(context()['sightings'], array(
    'by_org' => array(9 => 12),
    'anonymous' => 6,
    'total' => 18,
));
$anonPlain = rowFor(
    $engine->assess(
        context(array('sightings' => $anonymised)),
        $shipped
    ),
    'sightings.volume_recency'
);
$anon = context(array(
    'sightings' => $anonymised,
    'trust' => trustBlock(array(ORG_UUIDS[9] => 'G')),
));
$anonRow = rowFor(
    $engine->assess(
        $anon,
        trustProfile(array('org_trust' => array(ORG_UUIDS[9] => 'G')))
    ),
    'sightings.volume_recency'
);
is_same(18, $anonPlain['contribution'],
    'eighteen sightings ungraded are worth 18');
is_same(12, $anonRow['contribution'],
    'the six anonymised sightings survive a G grade on the named'
        . ' filer — anonymisation zeroes org_id, so there is nothing'
        . ' to grade and unrated is the honest answer');
is_true(
    strpos($anonRow['evidence'], 'weighted to 6') !== false,
    'and the row says six, which is the count that fed the curve'
);
is_same(
    1.0,
    ValueTrustTool::factor($anon, 0),
    'id 0 reads as unrated by construction'
);
is_same(
    null,
    ValueTrustTool::gradeFor($anon, 0),
    'and carries no grade to print'
);

// -------------------------------------------------------------------
out('');
out('§3.2 — the category resolution, and which step answered');

$aws = 'List of known Amazon AWS IP address ranges';
$resolved = WarninglistCategory::resolve($aws, 'false_positive');
is_same('known', $resolved['category'],
    'the shipped map calls an AWS range known infrastructure, over a'
        . ' database column that says false_positive');
is_same('shipped', $resolved['source'],
    'and names itself as the step that answered');
is_same(
    'known',
    WarninglistCategory::categoryFor($aws),
    'the map answers directly too'
);
is_same(
    null,
    WarninglistCategory::categoryFor('List of RFC 5735 CIDR blocks'),
    'and says nothing about a list it has never heard of — null rather'
        . ' than false_positive, or it would shadow the column, which'
        . ' for a custom list is the one deliberate statement (§3.2)'
);

$custom = WarninglistCategory::resolve(
    'BT Systems under berylia.org - UI',
    'known'
);
is_same('known', $custom['category'],
    'a custom list\'s own column is honoured');
is_same('list', $custom['source'], 'and named as the source');

$unset = WarninglistCategory::resolve('Test domain', null);
is_same('false_positive', $unset['category'],
    'a list with no column value falls through to the default');
is_same('default', $unset['source'], 'which names itself as well');

$overridden = WarninglistCategory::resolve(
    'List of known IPv4 public DNS resolvers',
    'false_positive',
    array('List of known IPv4 public DNS resolvers' => 'known')
);
is_same('known', $overridden['category'],
    'the profile\'s map wins over everything below it');
is_same('profile', $overridden['source'], 'and is named');

// §5 item 11: the profile takes a shipped `known` list back down.
$reversed = WarninglistCategory::resolve(
    $aws,
    'false_positive',
    array($aws => 'false_positive')
);
is_same('false_positive', $reversed['category'],
    '§5 item 11: a profile entry can take a shipped-map known list'
        . ' back to false_positive');
is_same('profile', $reversed['source'],
    'and the panel names the profile as the source, so the analyst'
        . ' can see it was their own decision');
is_same(
    'shipped',
    WarninglistCategory::resolve($aws, 'false_positive')['source'],
    'and with the entry removed the shipped map resumes, named'
);

$nonsense = WarninglistCategory::resolve(
    $aws,
    'false_positive',
    array($aws => 'definitely-not-a-category')
);
is_same('known', $nonsense['category'],
    'an override naming a category MISP does not admit falls through'
        . ' rather than being written into the page');
is_same('shipped', $nonsense['source'], 'and the next step is named');

// §5 item 12: all four origins, one each.
$origins = array();
foreach (array(
    WarninglistCategory::resolve($aws, 'false_positive',
        array($aws => 'known')),
    WarninglistCategory::resolve($aws, 'false_positive'),
    WarninglistCategory::resolve('Custom', 'known'),
    WarninglistCategory::resolve('Custom', ''),
) as $answer) {
    $origins[] = $answer['source'];
}
is_same(
    array('profile', 'shipped', 'list', 'default'),
    $origins,
    '§5 item 12: the four origins are distinguishable, one value each'
);

// -------------------------------------------------------------------
out('');
out('the roster, and the criterion for retiring it');

is_same(
    25,
    count(WarninglistCategory::KNOWN_LISTS),
    'the roster ships 25 lists — §3.3\'s shared infrastructure and'
        . ' research scanners'
);
is_same(
    25,
    count(array_unique(WarninglistCategory::KNOWN_LISTS)),
    'with no duplicate, which a name-keyed map would answer twice for'
);
foreach (array(
    'List of RFC 5735 CIDR blocks',
    'List of known IPv4 public DNS resolvers',
    'List of known hashes for empty files',
) as $refuting) {
    is_same(
        null,
        WarninglistCategory::categoryFor($refuting),
        sprintf(
            'and "%s" is not on it: a competent report naming that'
                . ' value cannot be simultaneously true',
            $refuting
        )
    );
}

$nothingImported = WarninglistCategory::retirable(array());
is_same(false, $nothingImported['retirable'],
    'V1 cannot retire on an instance where nothing carries a category');
is_same(25, count($nothingImported['outstanding']),
    'and all 25 entries are outstanding');
$allLanded = WarninglistCategory::retirable(array_fill_keys(
    WarninglistCategory::KNOWN_LISTS,
    'known'
));
is_same(true, $allLanded['retirable'],
    'and it can once every roster entry\'s row carries the same'
        . ' category the map hardcodes — the mechanical criterion §3.3'
        . ' promised, in code rather than in two PRs');
$halfLanded = WarninglistCategory::retirable(array(
    $aws => 'known',
));
is_same(false, $halfLanded['retirable'], 'one list is not the roster');
is_same(array($aws), $halfLanded['confirmed'],
    'though the one that landed is named');

// -------------------------------------------------------------------
out('');
out('§5 items 6 and 7 — the escalation phase 3 could not reach');

/*
 * The end-to-end test of D6 plus phase 3, and the sentence §1 quotes
 * from the page: *"the evidence did not change, the profile's
 * knowledge of it did."* The same context twice, one profile entry
 * apart.
 *
 * The value has to be the fixture's **benign** one to make items 6 and
 * 7 say what they mean, and that takes more than flipping the stances:
 * with organisations voting `to_ids no` over a ledger full of threat
 * evidence, phase 3's rule 7 makes the value contested before any
 * escalation is consulted — a record disputing its own assertion — and
 * `benign` is then unreachable. So this is `8.8.8.8`'s shape rather
 * than a stance edit: four organisations declining to export it,
 * eleven false-positive sightings, no galaxy, no feed, and a history
 * that stopped over a year ago.
 */
$dnsList = 'List of known IPv4 public DNS resolvers';
$benignOrgs = array();
foreach (ORG_UUIDS as $id => $uuid) {
    $benignOrgs[] = array(
        'id' => $id, 'uuid' => $uuid, 'name' => ORG_NAMES[$id],
        'occurrences' => 3, 'to_ids_yes' => 0, 'to_ids_no' => 3,
        'newest' => NOW - 400 * 86400,
        'oldest' => NOW - 800 * 86400,
    );
}
$listedHit = array(
    'hits' => array(array(
        'name' => $dnsList,
        'category' => 'false_positive',
        'category_source' => 'default',
        'matched' => '8.8.8.8/32',
    )),
    'lists_checked' => 84,
    'category' => 'false_positive',
    'category_sources' => array('default'),
);
$benign = context(array(
    'orgs' => $benignOrgs,
    'warninglist' => $listedHit,
    'occurrences' => array('total' => 12, 'events' => 12,
        'orgs' => 4, 'oldest' => NOW - 800 * 86400,
        'newest' => NOW - 400 * 86400),
    'temporal' => array('occurrences' => 12, 'with_first_seen' => 0,
        'max_lag_days' => 0),
    'galaxies' => array('clusters' => array(),
        'techniques' => array()),
    'feeds' => array('count' => 0, 'names' => array(), 'checked' => 3),
    'sightings' => array(
        'total' => 11, 'fp' => 11, 'expiration' => 0, 'orgs' => 2,
        'fp_orgs' => 2,
        'fp_org_names' => array('CIRCL', 'FOXIT-CERT'),
        'fp_org_list' => array(
            array('id' => 9, 'name' => 'CIRCL'),
            array('id' => 31, 'name' => 'FOXIT-CERT'),
        ),
        'by_org' => array(9 => 7, 31 => 4),
        'by_org_fp' => array(9 => 7, 31 => 4),
        'anonymous' => 0, 'anonymous_fp' => 0,
        'recent' => 0, 'recent_days' => 30,
        'first_stamp' => NOW - 700 * 86400,
        'last_stamp' => NOW - 400 * 86400,
        'fp_last_stamp' => NOW - 400 * 86400,
    ),
));
$asBenign = $engine->assess($benign, $shipped);
is_same('benign', $asBenign['lean'],
    'four organisations declining to export it and a false-positive'
        . ' listing: the lean is benign');
is_same(null, $asBenign['rule'],
    'and no conflict rule owns it');

$known = $benign;
$known['warninglist']['hits'][0]['category'] = 'known';
$known['warninglist']['hits'][0]['category_source'] = 'profile';
$known['warninglist']['category'] = 'known';
$known['warninglist']['category_sources'] = array('profile');
$asKnown = $engine->assess(
    $known,
    trustProfile(array(
        'warninglist_category' => array($dnsList => 'known'),
    ))
);
is_same('contested', $asKnown['lean'],
    '§5 item 6: one category override and the value is contested —'
        . ' the escalation phase 3 shipped could not fire without a'
        . ' category source at all');
is_same(
    'conflict:known-infrastructure-vs-reporting',
    $asKnown['rule']['id'] ?? null,
    'and the rule that did it is named, which is what the hero prints'
);
is_true(
    strpos($asKnown['rule']['evidence'] ?? '', $dnsList) !== false,
    'with the list in its evidence, so a reader can go and argue'
        . ' with the entry'
);
/*
 * The shim this line used to assert — `disposition`, written beside the
 * lean for templates that had not been renamed — went with D11's rename
 * in phase 9. What survives is the property it was really protecting:
 * the engine emits exactly one word for what the record asserts, and
 * `ValueLean` is the only place that turns it into English.
 */
is_true(
    !array_key_exists('disposition', $asKnown),
    'and the disposition shim is gone — the lean is the only word'
        . ' emitted for it'
);

$restored = $engine->assess($benign, $shipped);
is_same(
    'benign',
    $restored['lean'],
    '§5 item 7: the override removed and the value leans benign'
        . ' again — the evidence did not change, the profile\'s'
        . ' knowledge of it did'
);
is_same(
    $asBenign['quality'],
    $restored['quality'],
    'to the unit'
);

/*
 * And the case that is not in §5, found by writing item 6 against the
 * wrong stances first: on a value whose organisations *assert* it,
 * `conflict:listed-vs-asserted` has already made it contested, so the
 * override does not change the lean at all — it changes **which rule
 * owns the contradiction**, and the prose with it. Worth asserting,
 * because "the value goes contested" is the wrong thing to look for
 * on three-quarters of the values a category override will touch.
 */
$asserted = context(array('warninglist' => $listedHit));
$assertedBefore = $engine->assess($asserted, $shipped);
$assertedKnown = $asserted;
$assertedKnown['warninglist']['hits'][0]['category'] = 'known';
$assertedKnown['warninglist']['category'] = 'known';
$assertedAfter = $engine->assess(
    $assertedKnown,
    trustProfile(array(
        'warninglist_category' => array($dnsList => 'known'),
    ))
);
is_same('contested', $assertedBefore['lean'],
    'a listed value four organisations assert anyway is already'
        . ' contested, by conflict:listed-vs-asserted');
is_same(
    'conflict:listed-vs-asserted',
    $assertedBefore['rule']['id'] ?? null,
    'which is the rule that owns it'
);
is_same('contested', $assertedAfter['lean'],
    'and the override leaves the lean exactly where it was');
is_same(
    'conflict:known-infrastructure-vs-reporting',
    $assertedAfter['rule']['id'] ?? null,
    'while handing the contradiction to the other rule — the'
        . ' observable change is the rule and its prose, not the lean'
);
is_true(
    $assertedBefore['rule']['prose']
        !== $assertedAfter['rule']['prose'],
    'and the two rules say different things: "the page will not pick'
        . ' one" against "neither discounts the other"'
);

// -------------------------------------------------------------------
out('');
out('the signals that are not weighted, and stay that way');

$weightedIds = array();
foreach ($shipped['parameters']['signals'] as $entry) {
    if (!empty($entry['trust_weighted'])) {
        $weightedIds[] = $entry['id'];
    }
}
sort($weightedIds);
is_same(
    array('reporting.independent_orgs', 'sightings.false_positive',
        'sightings.volume_recency'),
    $weightedIds,
    'exactly the three §2.4 names carry trust_weighted — the ones'
        . ' whose contribution is derived from *which* organisations'
        . ' said something'
);

$allGraded = trustBlock(array_fill_keys(
    array_values(ORG_UUIDS),
    'G'
));
$zeroed = $engine->assess(
    context(array('trust' => $allGraded)),
    trustProfile(array('org_trust' => array_fill_keys(
        array_values(ORG_UUIDS),
        'G'
    )))
);
foreach (array('reporting.published_ratio', 'attribution.galaxy',
    'attribution.technique', 'lifecycle.warninglist',
    'lifecycle.feeds', 'lifecycle.continuity', 'lifecycle.recency',
    'record.temporal_precision') as $unweighted
) {
    $was = $baseline[$unweighted] ?? null;
    $now = contributions($zeroed)[$unweighted] ?? null;
    is_same($was, $now, sprintf(
        '%s is untouched by grading every organisation G',
        $unweighted
    ));
}
is_true(
    ValueTrustTool::inForce(
        context(array('trust' => $allGraded)),
        array('id' => 'x')
    ) === false,
    'and a signal entry that does not declare trust_weighted reports'
        . ' the mechanism as not in force, whatever the map says'
);

// -------------------------------------------------------------------
out('');
out('grade spelling, and a profile written somewhere else');

is_same('B', ValueTrustTool::normaliseGrade('b'),
    'the taxonomy writes grades lowercase and the page writes them'
        . ' upper, so both are the same grade');
is_same('B', ValueTrustTool::normaliseGrade(' B '),
    'and whitespace round an edited JSON value is not a new grade');
is_same('unrated', ValueTrustTool::normaliseGrade('UNRATED'),
    'unrated is spellable as a grade, because the scale carries it');
is_same(null, ValueTrustTool::normaliseGrade('H'),
    'H is not a grade — the scale stops at G');
is_same(null, ValueTrustTool::normaliseGrade(array('B')),
    'nor is an array, which a hand-edited profile can produce');

$upperKeys = trustBlock(array(
    strtoupper(ORG_UUIDS[9]) => 'b',
));
is_same(
    array(9 => 'B'),
    $upperKeys['grades'],
    'and a uuid written in upper case still finds its organisation —'
        . ' MISP stores them lower, an analyst pasting one may not'
);

out('');
out(sprintf(
    '%d checks, %d failures',
    $GLOBALS['checks'],
    $GLOBALS['failures']
));

exit($GLOBALS['failures'] === 0 ? 0 : 1);
