<?php
/**
 * Phase 8a's invariants, without a database.
 *
 * Two things are asserted here and nowhere else:
 *
 * - **The diff is arithmetic.** §7a items 4 to 6 — an unchanged
 *   candidate produces a diff with no movement in it, a changed weight
 *   moves exactly the rows that signal touches, a disabled signal's row
 *   is marked *vanished* rather than quietly absent, and **each column
 *   still sums to its own quality**. That last one is the property the
 *   whole simulator rests on (`01-profile.md` §5.1), and it is checked
 *   rather than trusted.
 * - **The form does not rewrite what it did not understand.** A section
 *   the form never posted survives a save; a signal this instance does
 *   not have keeps its configuration; a points map round-trips
 *   byte-identically; and a posted string becomes the integer the
 *   exact-sum invariant requires.
 *
 * What needs an instance is everything about *ownership* — resolution,
 * the fork swap, the ACL — and `09a-contract-live-probe.php` takes
 * those.
 *
 * Run: `php prd/analyst-profile/09-editor-harness.php`
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
require_once APP . 'Lib/Tools/ValueTrustTool.php';
require_once APP . 'Lib/Tools/WarninglistCategory.php';
require_once APP . 'Lib/Tools/ModuleLocality.php';
require_once APP . 'Lib/Tools/ValueEnrichmentTool.php';
require_once APP . 'Lib/Tools/ValueSignalLoader.php';
require_once APP . 'Lib/Tools/ValueExclusionTool.php';
require_once APP . 'Lib/Tools/ValueLeanTool.php';
require_once APP . 'Lib/Tools/ValueChangersTool.php';
require_once APP . 'Lib/Tools/ValueRelevanceTool.php';
require_once APP . 'Lib/Tools/ValueVerdictTool.php';
require_once APP . 'Lib/Tools/ValueVerdictDiffTool.php';
require_once APP . 'Lib/Tools/ValueUrlTool.php';
require_once APP . 'Lib/Tools/AnalystProfileFormTool.php';

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
    return json_decode(
        file_get_contents(APP . 'files/analyst-profiles/default-v1.json'),
        true
    );
}

/**
 * A profile row as the model hands one over: `parameters` decoded.
 *
 * @param array $parameters
 * @return array
 */
function profileRow(array $parameters, $id = 9, $revision = 1)
{
    return array(
        'id' => $id,
        'uuid' => '00000000-0000-4000-8000-00000000000' . $id,
        'name' => 'harness-' . $id,
        'enabled' => 1,
        'default' => 0,
        'version' => 1,
        'revision' => $revision,
        'parameters' => $parameters,
    );
}

/**
 * A value with enough of everything that most signals fire — the shape
 * a diff needs, rather than the median value, which fires two.
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
        'types' => array(array('type' => 'ip-dst', 'count' => 6)),
        'occurrences' => array('total' => 6, 'events' => 6, 'orgs' => 4,
            'oldest' => NOW - (86400 * 200), 'newest' => NOW - 86400),
        'publication' => array('events' => 6, 'published' => 5,
            'unpublished' => 1),
        'temporal' => array('occurrences' => 6, 'with_first_seen' => 6,
            'max_lag_days' => 2),
        'orgs' => array(
            array('id' => 1, 'name' => 'A', 'occurrences' => 2,
                'to_ids_yes' => 2, 'to_ids_no' => 0, 'newest' => NOW - 86400,
                'oldest' => NOW - (86400 * 200)),
            array('id' => 2, 'name' => 'B', 'occurrences' => 2,
                'to_ids_yes' => 2, 'to_ids_no' => 0, 'newest' => NOW - 86400,
                'oldest' => NOW - (86400 * 100)),
            array('id' => 3, 'name' => 'C', 'occurrences' => 1,
                'to_ids_yes' => 1, 'to_ids_no' => 0, 'newest' => NOW - 86400,
                'oldest' => NOW - (86400 * 50)),
            array('id' => 4, 'name' => 'D', 'occurrences' => 1,
                'to_ids_yes' => 1, 'to_ids_no' => 0, 'newest' => NOW - 86400,
                'oldest' => NOW - (86400 * 20)),
        ),
        'activity' => array(
            'months' => array(
                date('Y-m', NOW - (86400 * 120)) => 2,
                date('Y-m', NOW - (86400 * 60)) => 2,
                date('Y-m', NOW - (86400 * 30)) => 1,
                date('Y-m', NOW) => 1,
            ),
            'active_months' => 4,
            'span_months' => 7,
            'longest_run' => 2,
            'gaps' => 2,
        ),
        'warninglist' => array('hits' => array(),
            'lists_checked' => 84, 'category' => null),
        'sightings' => array('total' => 12, 'fp' => 0, 'expiration' => 0,
            'orgs' => 3, 'fp_orgs' => 0, 'fp_org_names' => array(),
            'first_stamp' => NOW - (86400 * 90),
            'last_stamp' => NOW - 43200, 'recent' => 4,
            'recent_days' => 30),
        'galaxies' => array(
            'clusters' => array('APT28' => 2),
            'techniques' => array('T1071.001' => 1),
        ),
        'feeds' => array('count' => 2, 'names' => array('F1', 'F2'),
            'checked' => 3),
        /*
         * `*_days` are **day-keyed maps of whole reports**, not counts.
         * The first version of this fixture made them integers, on the
         * strength of `ValueSignalBase`'s key list, and
         * `ValueRelevanceTool::sightingEvents()` warned on a `foreach`
         * over an int — every assertion still passed, because relevance
         * emits no ledger row and the harness's diff assertions are
         * about the ledger. A fixture that produces a warning is a
         * fixture that has stopped describing the platform.
         */
        'corroboration' => array(
            'sightings_days' => array(
                strtotime(date('Y-m-d', NOW - 43200)) => array(
                    'at' => NOW - 43200,
                    'name' => 'C',
                    'attribute_id' => 11,
                    'event_id' => 5,
                ),
            ),
            'foreign_days' => array(
                strtotime(date('Y-m-d', NOW - 86400)) => array(
                    'at' => NOW - 86400,
                    'name' => 'D',
                    'attribute_id' => 12,
                    'event_id' => 6,
                ),
            ),
            'last_sightings' => NOW - 43200,
            'last_foreign' => NOW - 86400,
            'undecidable' => 0,
        ),
        'budget' => array('window_days' => null, 'hot' => false,
            'occurrences' => 6, 'threshold' => null),
        'excluded' => array(),
        'missing' => array(),
        'exclusions' => array(),
    );
    return array_merge($base, $extra);
}

$form = new AnalystProfileFormTool();
$engine = new ValueVerdictTool();
$shipped = shippedProfile();
$parameters = $shipped['parameters'];

/*
 * ------------------------------------------------------------------
 * 1. The attainable bound
 * ------------------------------------------------------------------
 * The rule is *the largest positive value in a signal's `points` map*,
 * and the reason it needs asserting rather than explaining is that it
 * only works because a cap, where one is declared, is always the
 * largest positive term. If a future signal breaks that, the bound
 * silently under-reports and §7a item 8's refusal starts rejecting
 * legitimate edits.
 */
out('== the attainable bound ==');
$attainable = $form->attainable($parameters);
is_same(129, $attainable['bound'],
    'the eleven shipped signals cannot sum past 129');
is_same(11, $attainable['signals'],
    'and all eleven are counted, because all eleven are enabled');
is_same(array(), $attainable['unbounded'],
    'none of them is paid per unit without a cap');
is_true($attainable['reliable'],
    'so the bound is reliable and a band above it may be refused');
is_same(28, $attainable['per_signal']['reporting.independent_orgs'],
    'a capped signal bounds at its cap');
is_same(9, $attainable['per_signal']['reporting.published_ratio'],
    'and an uncapped ratio bounds at its scale — the multiplicand is at'
        . ' most 1, which is why `scale` is not read as per-unit');
is_same(0, $attainable['per_signal']['sightings.false_positive'],
    'a signal with no positive term contributes nothing to the bound');

/*
 * The other half of the rule: disabling signals lowers the bound, so a
 * band that was reachable can stop being reachable without the band
 * moving. That is the case the strip has to draw.
 */
$thin = $parameters;
foreach ($thin['signals'] as $index => $signal) {
    if (!in_array($signal['id'], array('reporting.independent_orgs',
        'sightings.volume_recency'), true)
    ) {
        $thin['signals'][$index]['enabled'] = false;
    }
}
$thinBound = $form->attainable($thin);
is_same(52, $thinBound['bound'],
    'with only the two heaviest signals enabled the bound is 52');
$strip = $form->bandStrip($thin);
is_true(!$strip['ok'],
    'and the shipped `high` at 60 is then beyond it');
is_same('high', $strip['problems'][0]['band'],
    'with the offending band named');

/*
 * ------------------------------------------------------------------
 * 2. Band ordering
 * ------------------------------------------------------------------
 */
out('');
out('== band ordering ==');
$inverted = $parameters;
$inverted['thresholds']['quality_bands'] = array('high' => 30,
    'medium' => 60);
$invertedCheck = $form->validate($inverted);
is_same(1, count($invertedCheck['errors']),
    'medium at or above high is one error');
is_true(
    strpos($invertedCheck['errors'][0], 'Nothing could ever be high')
        !== false,
    'and it says what the consequence is, not just that it is invalid'
);
$ok = $form->validate($parameters);
is_same(array(), $ok['errors'],
    'the shipped default validates with no errors');
is_same(array(), $ok['warnings'],
    'and no warnings');

/*
 * ------------------------------------------------------------------
 * 2b. The editor's vocabulary is the engine's
 * ------------------------------------------------------------------
 * `09b-revisions.md` 3.13. The form tool carried its own `CLOCKS` and
 * `TYPE_RULES` lists and both had drifted: it offered `first`, which
 * the engine treats as `shortest`, and refused `most_common`, which the
 * engine implements. A profile a reader could not have written was
 * being refused, so the lists are asserted equal rather than reviewed.
 */
out('');
out('== the editor speaks the engine\'s vocabulary ==');
$relevanceSection = $form->sections($parameters, array(
    'attribute_types' => array('ip-src', 'ip-dst', 'domain'),
))['relevance'];
$clockField = null;
$ruleField = null;
$speedField = null;
foreach ($relevanceSection['blocks'][0]['fields'] as $field) {
    if ($field['key'] === 'clock') {
        $clockField = $field;
    } elseif ($field['key'] === 'type_rule') {
        $ruleField = $field;
    } elseif ($field['key'] === 'decay_speed') {
        $speedField = $field;
    }
}
is_same(ValueRelevanceTool::CLOCKS, $clockField['options'],
    'the clock select offers exactly the clocks the engine reads');
is_same(ValueRelevanceTool::TYPE_RULES, $ruleField['options'],
    'and the type rule select exactly the rules it applies');
$mostCommon = $parameters;
$mostCommon['relevance']['type_rule'] = 'most_common';
is_same(array(), $form->validate($mostCommon)['errors'],
    '`most_common` is accepted — it was refused, and it is the rule the'
        . ' engine implements');
$invented = $parameters;
$invented['relevance']['type_rule'] = 'first';
is_same(1, count($form->validate($invented)['errors']),
    'and `first` is refused — the editor used to offer it and the'
        . ' engine silently read it as `shortest`');

/*
 * `decay_speed` was declared `int`, which cannot express the sub-1 half
 * of the curve family, and had no validator at all.
 */
is_same('float', $speedField['type'],
    'decay speed is a float, so the holds-then-cliff half of the curve'
        . ' family is reachable from the editor');
$half = $parameters;
$half['relevance']['decay_speed'] = 0.5;
is_same(array(), $form->validate($half)['errors'],
    'and 0.5 validates');
$zero = $parameters;
$zero['relevance']['decay_speed'] = 0;
is_same(1, count($form->validate($zero)['errors']),
    'while zero is refused rather than silently swapped for the default');

/*
 * ------------------------------------------------------------------
 * 2c. A numeric field says what it counts
 * ------------------------------------------------------------------
 * `09b-revisions.md` 3.18. "Reuse an answer for: 24" was read by a
 * reviewer as having no unit, and it did not: hours appeared only
 * inside a validation error string. The settings that read correctly
 * were smuggling the unit into the key name (`ttl_days`,
 * `lag_uncertain_days`), which the label does not show either.
 */
out('');
out('== a numeric field says what it counts ==');
$unitSections = $form->sections($parameters, array(
    'attribute_types' => array('ip-src', 'ip-dst', 'domain'),
    'modules' => array(),
));
$units = array();
foreach ($unitSections as $sectionId => $section) {
    foreach ($section['blocks'] as $block) {
        if ($block['kind'] !== 'fields') {
            continue;
        }
        foreach ($block['fields'] as $field) {
            if (isset($field['unit'])) {
                $units[$field['key']] = $field['unit'];
            }
        }
    }
}
is_same('hours', isset($units['max_age_hours'])
    ? $units['max_age_hours'] : null,
    'the reuse window is in hours, and now says so outside a validation'
        . ' message');
is_same('days', isset($units['lag_uncertain_days'])
    ? $units['lag_uncertain_days'] : null,
    'the encoding lag is in days without the key name having to carry'
        . ' it');
is_same('points', isset($units['high']) ? $units['high'] : null,
    'and a quality band threshold is in points — the same units the'
        . ' attainable bound is quoted in');

/*
 * The affordance is general rather than five hand-written cases: a
 * generated points field gets the unit from the map it belongs to, so a
 * dropped-in signal needs to declare nothing, and `config` — whose
 * entries are days and ratios — gets no unit it has not earned.
 */
$unitPalette = $form->signalPalette($parameters);
$pointsUnits = array();
$configUnits = array();
foreach ($unitPalette as $item) {
    foreach ($item['fields'] as $field) {
        if (!isset($field['map'])) {
            continue;
        }
        $seen = isset($field['unit']) ? $field['unit'] : null;
        if ($field['map'] === 'points') {
            $pointsUnits[$seen === null ? 'none' : $seen] = true;
        } else {
            $configUnits[$seen === null ? 'none' : $seen] = true;
        }
    }
}
is_same(array('points' => true), $pointsUnits,
    'every generated points field across all eleven signals is in'
        . ' points, from the map rather than from eleven signal files');
is_same(array('none' => true), $configUnits,
    'and no `config` field is silently called points — its entries are'
        . ' days and ratios');

/*
 * ------------------------------------------------------------------
 * 2d. The posture is about locality, and the editor refuses neither
 *     name
 * ------------------------------------------------------------------
 * `09b-revisions.md` 3.17. `cost_posture` never gated cost, and `ask`
 * was byte-identical to `allow_external`. The setting is renamed and
 * `ask` retired — but a pasted document may carry either, and the
 * engine reads both, so validation that refused them would refuse a
 * profile that works.
 */
out('');
out('== the posture is locality, under either name ==');
$postureSection = $form->sections($parameters, array(
    'attribute_types' => array('ip-dst'),
    'modules' => array(),
))['enrichment'];
$postureBlock = $postureSection['blocks'][0];
is_same('Modules that leave the instance', $postureBlock['title'],
    'the pane is named for what it does, not for the cost it never'
        . ' read');
is_same('locality_posture', $postureBlock['fields'][0]['key'],
    'and so is the setting');
is_same(array('local_only', 'allow_external'),
    $postureBlock['fields'][0]['options'],
    'two options, both of which do something different');

$legacy = $parameters;
$legacy['enrichment']['cost_posture'] =
    $legacy['enrichment']['locality_posture'];
unset($legacy['enrichment']['locality_posture']);
is_same(array(), $form->validate($legacy)['errors'],
    'a document carrying the old key validates — the engine reads it,'
        . ' so refusing it would refuse a working profile');
$asked = $parameters;
$asked['enrichment']['locality_posture'] = 'ask';
is_same(array(), $form->validate($asked)['errors'],
    'and so does a retired `ask`, which the engine reads as'
        . ' allow_external');
$nonsense = $parameters;
$nonsense['enrichment']['locality_posture'] = 'whenever';
is_same(1, count($form->validate($nonsense)['errors']),
    'while a posture that never existed is still refused');
$legacyNonsense = $parameters;
$legacyNonsense['enrichment']['cost_posture'] = 'whenever';
is_true(
    strpos($form->validate($legacyNonsense)['errors'][0],
        'cost_posture') !== false,
    'and a bad value under the old key is reported under the name the'
        . ' reader actually typed'
);

/*
 * ------------------------------------------------------------------
 * 2e. A checklist cannot hold three states
 * ------------------------------------------------------------------
 * `09b-revisions.md` 3.16, D17. `enrichment.auto_run` was a
 * multiselect per type, which has two states — in the list or not —
 * and the declaration now has three. The block stays a `map`; its
 * row value becomes a map of its own.
 */
out('');
out('== the declaration has three states, so it is not a checklist ==');
/*
 * The shipped default declares nothing — that is its whole point
 * (D15) — so the states need a profile that has been edited.
 */
$declaring = $parameters;
$declaring['enrichment']['auto_run'] = array(
    'ip-src' => array('dns' => 'ticked', 'circl_passivedns' => 'never'),
);
$stateSections = $form->sections($declaring, array(
    'attribute_types' => array('ip-src', 'domain', 'md5'),
    'modules' => array('dns' => array(), 'circl_passivedns' => array()),
));
$autoBlock = null;
foreach ($stateSections['enrichment']['blocks'] as $candidate) {
    if (isset($candidate['id']) && $candidate['id'] === 'auto_run') {
        $autoBlock = $candidate;
    }
}
is_same('map', $autoBlock['kind'],
    'still one of the four block kinds — no fifth was invented for'
        . ' this');
is_same('module_states', $autoBlock['value_type'],
    'but its row value is a module-to-state map, not a checklist');
$row = $autoBlock['entries'][0];
is_same(array('ticked', 'never', 'auto'), $row['state_options'],
    'three states are offered');
is_same(array('ticked', 'never'), $row['states_built'],
    'and the row says which two are implemented, so a design cannot'
        . ' draw `auto` as though it worked');
is_true(is_array($row['value']) && !isset($row['value'][0]),
    'the value is a map rather than a list');
is_same(array('dns' => 'ticked', 'circl_passivedns' => 'never'),
    $row['value'],
    'each module carries its own state, which is the thing a'
        . ' multiselect could not say');

/*
 * A pre-D17 profile is the common case, not the exotic one: every
 * existing fork carries a bare list.
 */
$listed = $parameters;
$listed['enrichment']['auto_run'] = array('ip-src' => array('dns'));
$listedSections = $form->sections($listed, array(
    'attribute_types' => array('ip-src'),
    'modules' => array('dns' => array()),
));
foreach ($listedSections['enrichment']['blocks'] as $candidate) {
    if (isset($candidate['id']) && $candidate['id'] === 'auto_run') {
        is_same(array('dns' => 'ticked'),
            $candidate['entries'][0]['value'],
            'a fork carrying the pre-D17 list renders as ticked, so the'
                . ' editor does not need a migration to open it');
    }
}

/*
 * The POST semantics for the nested shape. `__present` on the inner
 * map means *these are all the modules for this type*, so a module the
 * analyst removed goes rather than lingering — the same rule the flat
 * maps already follow, checked here because the shape is new.
 */
$storedStates = array('enrichment' => array('auto_run' => array(
    'ip-src' => array('dns' => 'ticked', 'circl_passivedns' => 'never'),
    'domain' => array('dns' => 'ticked'),
)));
$postedStates = array('enrichment' => array('auto_run' => array(
    '__present' => 1,
    'ip-src' => array('__present' => 1, 'dns' => 'never'),
)));
$mergedStates = $form->merge($storedStates, $postedStates);
is_same(
    array('ip-src' => array('dns' => 'never')),
    $mergedStates['enrichment']['auto_run'],
    'a posted declaration replaces: the module dropped from the type'
        . ' goes, the type not posted at all goes, and the state that'
        . ' changed is the one stored'
);
$keepOthers = $form->merge($storedStates, array(
    'enrichment' => array('auto_run' => array(
        'ip-src' => array('__present' => 1, 'dns' => 'auto'),
    )),
));
is_same(
    array(
        'ip-src' => array('dns' => 'auto'),
        'domain' => array('dns' => 'ticked'),
    ),
    $keepOthers['enrichment']['auto_run'],
    'while without `__present` on the outer map the types that were'
        . ' not posted survive — a section the form never showed must'
        . ' not be rewritten'
);

$refusing = $parameters;
$refusing['enrichment']['auto_run'] = array(
    'ip-src' => array('dns' => 'never'),
);
is_same(array(), $form->validate($refusing)['errors'],
    '`never` validates');
$autoing = $parameters;
$autoing['enrichment']['auto_run'] = array(
    'ip-src' => array('dns' => 'auto'),
);
is_same(array(), $form->validate($autoing)['errors'],
    'and so does the unimplemented `auto` — it is a valid document,'
        . ' which is the whole point of landing the schema early');
$bogus = $parameters;
$bogus['enrichment']['auto_run'] = array(
    'ip-src' => array('dns' => 'whenever'),
);
$bogusErrors = $form->validate($bogus)['errors'];
is_same(1, count($bogusErrors), 'a state that is not one is refused');
is_true(strpos($bogusErrors[0], 'ticked, never, auto') !== false,
    'and the message lists the three, because a reader hand-editing'
        . ' JSON has no select to look at');

/*
 * ------------------------------------------------------------------
 * 3. The parse error and its line
 * ------------------------------------------------------------------
 * §7a item 9. `json_decode` reports what went wrong and never where,
 * and a syntax error with no position on a three-hundred-line document
 * sends the reader back to a text editor to bisect it by hand.
 */
out('');
out('== the parse error ==');
$mismatched = "{\n  \"signals\": [\n    {\"id\": \"a\"}\n  }\n}";
$parsed = $form->parse($mismatched);
is_same(false, $parsed['ok'], 'a mismatched closer does not parse');
is_same(4, $parsed['line'],
    'and the line named is the one holding the closer, which a'
        . ' depth-only counter would have missed — the depth never goes'
        . ' negative here');
$unterminated = "{\n  \"a\": 1,\n  \"b\": [1,2,\n}";
is_same(4, $form->parse($unterminated)['line'],
    'an unterminated array points at where the document stops');
is_same(false, $form->parse('[1,2,3]')['ok'],
    'a list is not a profile');
is_same(false, $form->parse('')['ok'],
    'and neither is nothing');
is_true($form->parse(json_encode($parameters))['ok'],
    'the shipped document round-trips through the parser');

/*
 * ------------------------------------------------------------------
 * 4. The merge does not rewrite what it did not understand
 * ------------------------------------------------------------------
 */
out('');
out('== the merge ==');
$merged = $form->merge($parameters, array(
    'signals' => array(
        'reporting.independent_orgs' => array(
            'points' => array('per_org' => '9'),
        ),
    ),
));
is_same(
    array_keys($parameters),
    array_keys($merged),
    'a posted `signals` section leaves the other six sections in place'
);
is_same(count($parameters['signals']), count($merged['signals']),
    'and every signal the form did not mention survives');
$edited = null;
foreach ($merged['signals'] as $signal) {
    if ($signal['id'] === 'reporting.independent_orgs') {
        $edited = $signal;
    }
}
is_same(9, $edited['points']['per_org'],
    'the posted string became an integer, because a string fails the'
        . ' exact-sum invariant and correctly so');
is_same(28, $edited['points']['cap'],
    'and the key the form did not post is untouched');
is_same(4, $edited['config']['named'],
    'as is the whole `config` map');

/*
 * The case §4.4 exists for: a profile configuring a signal this
 * instance does not have. The editor must keep it, because a redeploy
 * brings the implementation back and a dropped entry is a lost
 * judgement.
 */
$withStranger = $parameters;
$withStranger['signals'][] = array(
    'id' => 'custom.not_here',
    'enabled' => true,
    'band' => 'strong',
    'points' => array('per_thing' => 5),
);
$strangerMerged = $form->merge($withStranger, array(
    'signals' => array(
        'lifecycle.feeds' => array('enabled' => false),
    ),
));
$kept = false;
foreach ($strangerMerged['signals'] as $signal) {
    if ($signal['id'] === 'custom.not_here') {
        $kept = $signal;
    }
}
is_true($kept !== false,
    'a signal this instance does not have survives a save of another'
        . ' section');
is_same(array('per_thing' => 5), $kept['points'],
    'with its points intact');
$strangerCheck = $form->validate($withStranger);
is_same(array(), $strangerCheck['errors'],
    'and it is not an error — a profile written for another instance'
        . ' has to be storable, or import is useless');
is_same(1, count($strangerCheck['warnings']),
    'it is a warning');

/*
 * A round-trip: render the form, post back every field unchanged, and
 * the document must come out byte-identical. This is the property that
 * stops a form silently rewriting a section it merely displayed.
 */
$posted = array('signals' => array());
foreach ($form->signalPalette($parameters) as $item) {
    if (!$item['in_profile']) {
        continue;
    }
    $entry = array();
    foreach ($item['fields'] as $field) {
        if ($field['value'] === null) {
            continue;
        }
        $leaf = end($field['path']);
        if (isset($field['map'])) {
            $entry[$field['map']][$leaf] = $field['value'];
        } else {
            $entry[$leaf] = $field['value'];
        }
    }
    $posted['signals'][$item['id']] = $entry;
}
$roundTripped = $form->merge($parameters, $posted);
is_same(
    canonicalise($parameters['signals']),
    canonicalise($roundTripped['signals']),
    'the whole signals section round-trips through the form unchanged'
);

/*
 * A map replaces rather than merges, because a removed row posts
 * nothing and a merge cannot tell that from a row the form never drew.
 */
$graded = $parameters;
$graded['reference']['org_trust'] = array(
    'aaaaaaaa-0000-4000-8000-000000000001' => 'B',
    'aaaaaaaa-0000-4000-8000-000000000002' => 'E',
);
$pruned = $form->merge($graded, array(
    'reference' => array(
        'org_trust' => array(
            '__present' => 1,
            'aaaaaaaa-0000-4000-8000-000000000001' => 'B',
        ),
    ),
));
is_same(
    array('aaaaaaaa-0000-4000-8000-000000000001' => 'B'),
    $pruned['reference']['org_trust'],
    'a map that declares itself present drops the rows it did not post'
);
is_same(
    $graded['reference']['org_trust_scale'],
    $pruned['reference']['org_trust_scale'],
    'and the sibling block in the same section is untouched'
);
$notPresent = $form->merge($graded, array(
    'reference' => array(
        'org_trust' => array(
            'aaaaaaaa-0000-4000-8000-000000000001' => 'A',
        ),
    ),
));
is_same(
    array(
        'aaaaaaaa-0000-4000-8000-000000000001' => 'A',
        'aaaaaaaa-0000-4000-8000-000000000002' => 'E',
    ),
    $notPresent['reference']['org_trust'],
    'without the marker it merges, which is what a partial post means'
);

/*
 * ------------------------------------------------------------------
 * 5. The diff
 * ------------------------------------------------------------------
 */
out('');
out('== the diff ==');
$base = $engine->assess(context(), profileRow($parameters));
is_true(count($base['ledger']) > 0,
    'the harness value produces a ledger to diff');

$identical = ValueVerdictDiffTool::diff($base, $base);
is_same(false, $identical['changed'],
    'a profile against itself has not changed');
is_same(array(), $identical['moved'],
    'with no row moved');
is_same(0, $identical['totals']['delta'],
    'and no delta');
$states = array();
foreach ($identical['rows'] as $row) {
    $states[$row['state']] = true;
}
is_same(array('same' => true), $states,
    'every row is `same` — an empty diff is a table of unchanged rows,'
        . ' which is what lets a design say "no change" instead of'
        . ' drawing a blank');
is_true($identical['sums']['ok'],
    'and both columns sum to their own quality');

/*
 * One weight changed. Exactly the rows that signal touches move, and
 * both columns still add up — which is the whole of §2's argument
 * turned into an assertion.
 */
$heavier = $parameters;
foreach ($heavier['signals'] as $index => $signal) {
    if ($signal['id'] === 'attribution.galaxy') {
        $heavier['signals'][$index]['points']['per_cluster'] = 3;
    }
}
$after = $engine->assess(context(), profileRow($heavier));
$oneWeight = ValueVerdictDiffTool::diff($base, $after);
is_true($oneWeight['changed'], 'a changed weight is a changed diff');
is_same(array('attribution.galaxy'), $oneWeight['moved'],
    'and exactly one row moved');
is_true($oneWeight['sums']['before']['ok'],
    'the before column still sums to its own quality');
is_true($oneWeight['sums']['after']['ok'],
    'and so does the after column');
$sumOfDeltas = 0;
foreach ($oneWeight['rows'] as $row) {
    $sumOfDeltas += $row['delta'];
}
is_same($oneWeight['totals']['delta'], $sumOfDeltas,
    'the deltas sum to the difference between the totals, which is the'
        . ' diff being arithmetic rather than impressionistic');

/*
 * A disabled signal. Its row is *vanished* and not merely absent,
 * because absent looks identical to a signal that had nothing to say.
 */
$without = $parameters;
foreach ($without['signals'] as $index => $signal) {
    if ($signal['id'] === 'lifecycle.feeds') {
        $without['signals'][$index]['enabled'] = false;
    }
}
$disabled = $engine->assess(context(), profileRow($without));
$vanishedDiff = ValueVerdictDiffTool::diff($base, $disabled);
$vanished = null;
foreach ($vanishedDiff['rows'] as $row) {
    if ($row['id'] === 'lifecycle.feeds') {
        $vanished = $row;
    }
}
is_true($vanished !== null,
    'a disabled signal still has a row in the diff');
is_same(ValueVerdictDiffTool::VANISHED, $vanished['state'],
    'marked vanished');
is_same(null, $vanished['after'],
    'with no contribution under the candidate');
is_same(
    -$vanished['before'],
    $vanishedDiff['totals']['delta'],
    'and the totals differ by exactly what it used to contribute'
);
is_true($vanishedDiff['sums']['ok'],
    'both columns still sum exactly');

/*
 * And the mirror: a signal the base profile did not carry appears.
 */
$reversedDiff = ValueVerdictDiffTool::diff($disabled, $base);
$appeared = null;
foreach ($reversedDiff['rows'] as $row) {
    if ($row['id'] === 'lifecycle.feeds') {
        $appeared = $row;
    }
}
is_same(ValueVerdictDiffTool::APPEARED, $appeared['state'],
    'the same edit read the other way round is an appeared row');
is_same(null, $appeared['before'], 'with nothing before it');

/*
 * The relevance axis moves without a single ledger row moving, which
 * is D11 held mechanically and the one case a ledger-only diff would
 * have reported as *no change*.
 */
$shortTtl = $parameters;
$shortTtl['relevance']['ttl_days'] = array('default' => 1, 'ip-dst' => 1);
$expired = $engine->assess(context(), profileRow($shortTtl));
$relevanceDiff = ValueVerdictDiffTool::diff($base, $expired);
is_same(array(), $relevanceDiff['moved'],
    'a TTL edit moves no ledger row');
is_same(0, $relevanceDiff['totals']['delta'],
    'and no quality');
is_true($relevanceDiff['axes']['relevance']['changed'],
    'but the relevance axis moved');
is_true($relevanceDiff['changed'],
    'so the diff reports a change — a ledger-only diff would have said'
        . ' nothing happened');

/*
 * The headline the comparison set renders per value.
 */
out('');
out('== the comparison headline ==');
$headline = ValueVerdictDiffTool::headline('198.51.100.24', $base, $after);
is_same('198.51.100.24', $headline['value'], 'the value is named');
is_true($headline['moved'], 'a moved assessment is flagged as moved');
is_true(
    in_array($headline['direction'], array('up', 'down'), true),
    'with a direction, so a design can colour it without asserting the'
        . ' change is wrong'
);
$still = ValueVerdictDiffTool::headline('198.51.100.24', $base, $base);
is_same('none', $still['direction'],
    'an unchanged value has no direction at all');
is_same(false, $still['moved'], 'and has not moved');

/*
 * ------------------------------------------------------------------
 * 6. The palette
 * ------------------------------------------------------------------
 */
out('');
out('== the palette ==');
$palette = $form->signalPalette($parameters);
is_same(11, count($palette), 'eleven shipped signals, eleven items');
$byId = array();
foreach ($palette as $item) {
    $byId[$item['id']] = $item;
}
is_same('active', $byId['reporting.independent_orgs']['state'],
    'a signal the profile carries is active');
$pointsFields = array();
foreach ($byId['reporting.independent_orgs']['fields'] as $field) {
    if (isset($field['map']) && $field['map'] === 'points') {
        $pointsFields[] = $field['key'];
    }
}
is_same(array('per_org', 'cap'), $pointsFields,
    'its points form is generated from `points_schema`');
$ratioFields = array();
foreach ($byId['reporting.published_ratio']['fields'] as $field) {
    if (isset($field['map']) && $field['map'] === 'points') {
        $ratioFields[] = $field['key'];
    }
}
is_same(array('scale', 'none'), $ratioFields,
    'and the next row\'s columns are different ones, which is the thing'
        . ' a uniform three-column table would have got wrong');

$strangerPalette = $form->signalPalette($withStranger);
$strangerItem = null;
foreach ($strangerPalette as $item) {
    if ($item['id'] === 'custom.not_here') {
        $strangerItem = $item;
    }
}
is_true($strangerItem !== null,
    'a signal this instance does not have is still in the palette');
is_same('missing', $strangerItem['state'], 'flagged missing');
is_same('missing', $strangerItem['badges'][0]['id'],
    'with a badge saying so, because the engine already lists it as not'
        . ' counted and the editor must agree');

$groups = array();
foreach ($palette as $item) {
    $groups[] = $item['group'];
}
is_same(
    array('Reporting', 'Reporting', 'Sightings', 'Sightings',
        'Attribution', 'Attribution', 'Lifecycle', 'Lifecycle',
        'Lifecycle', 'Lifecycle', 'Lifecycle'),
    $groups,
    'the palette reads in the ledger\'s own group order, not the'
        . ' filesystem\'s alphabet'
);

/*
 * ------------------------------------------------------------------
 * 7. The seven sections
 * ------------------------------------------------------------------
 * §4 of the spec says six. It was written before phase 5 added
 * `relevance`, and a section left to the raw JSON editor is a section
 * nobody adjusts.
 */
out('');
out('== the sections ==');
$sections = $form->sections($parameters, array(
    'attribute_types' => array('ip-src', 'ip-dst', 'domain'),
));
is_same(7, count($sections), 'seven editable sections, not six');
is_same(
    array('signals', 'thresholds', 'escalations', 'exclusions',
        'relevance', 'reference', 'enrichment'),
    array_keys($sections),
    'and `format` is not one of them — it is the document\'s version'
        . ' marker, not a judgement'
);
$kinds = array();
foreach ($sections as $section) {
    foreach ($section['blocks'] as $block) {
        $kinds[$block['kind']] = true;
    }
}
ksort($kinds);
is_same(array('fields' => true, 'items' => true, 'map' => true,
    'strip' => true), $kinds,
    'four block kinds cover all seven, so a design that renders four'
        . ' shapes renders the whole document');

$ttl = null;
foreach ($sections['relevance']['blocks'] as $block) {
    if (isset($block['id']) && $block['id'] === 'ttl_days') {
        $ttl = $block;
    }
}
is_true($ttl !== null, 'relevance carries its per-type TTL map');
is_same('map', $ttl['kind'], 'as a map');
is_true(count($ttl['entries']) > 1,
    'with a row per type the profile has an opinion about');
is_same(
    array(),
    array_diff($ttl['add']['options'], array('ip-src', 'domain')),
    'and the picker offers only types not already in it — never a row'
        . ' per type MISP has'
);

$exclusions = $sections['exclusions']['blocks'][0]['items'];
is_same(4, count($exclusions), 'four exclusions, the closed set');
$layers = array();
foreach ($exclusions as $item) {
    $layers[$item['layer']] = true;
}
ksort($layers);
is_same(array('budget' => true, 'condition' => true,
    'list_fold' => true, 'row_filter' => true), $layers,
    'each declaring its layer, because a condition-class exclusion'
        . ' changes what a signal sees rather than filtering its output');

out('');
out(sprintf(
    '%d checks, %d failures',
    $GLOBALS['checks'],
    $GLOBALS['failures']
));

exit($GLOBALS['failures'] === 0 ? 0 : 1);

/**
 * A structure in a comparable form, so two documents differing only in
 * key order compare equal — the same rule the controller uses to decide
 * whether `revision` moves.
 *
 * @param mixed $value
 * @return string
 */
function canonicalise($value)
{
    return json_encode(sortKeysDeep($value));
}

/**
 * @param mixed $value
 * @return mixed
 */
function sortKeysDeep($value)
{
    if (!is_array($value)) {
        return $value;
    }
    $out = array();
    foreach ($value as $key => $member) {
        $out[$key] = sortKeysDeep($member);
    }
    ksort($out);
    return $out;
}
