<?php
/**
 * Phase 7's declaration, without a database and without a modules
 * service.
 *
 * The phase's whole risk is that a *declaration* and an *instance*
 * disagree quietly, so most of what follows is the same value resolved
 * twice — once where the instance offers what the profile names, once
 * where it does not — asserting that the second produces a **stated
 * condition** rather than a shorter list. The one assertion that
 * matters most is the first: **a profile that declares nothing
 * produces no selection, no condition and no extra call**, which is
 * `01-profile.md` §1.3's *"empty means as before"* for a section that
 * could otherwise change what a tab does on every page load.
 *
 * Covers §5 items 1 and 4 in full, the resolvable half of items 2 and
 * 3 — a disabled module and a restricted one are settings, and the
 * tool sees them as facts either way — plus the mechanism the items do
 * not name: the union over a value's types, which type a run would
 * use, the precedence between four reasons a module is missing, and
 * the posture's treatment of a module nobody has classified.
 *
 * What needs an instance is every fact this file hands over as a
 * literal: that `Module::getEnabledModules()` really does filter the
 * way `explain()` assumes, that the second `GET /modules` is paid on
 * exactly the path that needs it, and §5 item 5 — nothing runs —
 * which is a claim about outbound HTTP that only outbound HTTP can
 * refute. `08-enrichment-live-probe.php` takes those.
 *
 * Run: `php prd/analyst-profile/08-enrichment-harness.php`
 */

define('APP', __DIR__ . '/../../app/');

class App
{
    public static function uses($class, $path)
    {
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

require_once APP . 'Lib/Tools/ModuleLocality.php';
require_once APP . 'Lib/Tools/ValueEnrichmentTool.php';

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

function section($title)
{
    out('');
    out($title);
    out(str_repeat('-', strlen($title)));
}

/**
 * The shipped default's section, as `default-v1.json` carries it.
 */
function shippedProfile()
{
    return array(
        'name' => 'default-v1',
        'parameters' => array(
            'enrichment' => array(
                'auto_run' => array(),
                'cost_posture' => 'local_only',
                'max_age_hours' => 24,
            ),
        ),
    );
}

/**
 * A profile an analyst has actually edited.
 */
function declaredProfile($autoRun, $posture = 'allow_external',
    $locality = array()
) {
    return array(
        'name' => 'analyst fork',
        'parameters' => array(
            'enrichment' => array(
                'auto_run' => $autoRun,
                'cost_posture' => $posture,
                'max_age_hours' => 24,
                'locality' => $locality,
            ),
        ),
    );
}

/**
 * `typesFor` output for a value that is three types, ordered by
 * occurrence count descending as the real one is.
 */
function valueTypes()
{
    return array(
        array('type' => 'ip-dst', 'count' => 17),
        array('type' => 'ip-src', 'count' => 5),
        array('type' => 'text', 'count' => 2),
    );
}

/**
 * `enrichmentEligible()` output: what the instance offers for those
 * types. `circl_passivedns` accepts two of them, `dns` one, and the
 * locality is the shipped map's answer.
 */
function eligibleRows()
{
    return array(
        array(
            'name' => 'circl_passivedns',
            'kinds' => array('expansion', 'hover'),
            'types' => array('ip-dst' => 17, 'ip-src' => 5),
            'type' => 'ip-dst',
            'locality' => 'external',
            'locality_source' => 'unknown',
        ),
        array(
            'name' => 'dns',
            'kinds' => array('expansion', 'hover'),
            'types' => array('ip-src' => 5),
            'type' => 'ip-src',
            'locality' => 'unknown',
            'locality_source' => 'unknown',
        ),
        array(
            'name' => 'extract_url_components',
            'kinds' => array('expansion'),
            'types' => array('ip-dst' => 17),
            'type' => 'ip-dst',
            'locality' => 'local',
            'locality_source' => 'shipped',
        ),
    );
}

function facts($extra = array())
{
    return array_merge(
        array(
            'service' => array('reachable' => true),
            'types' => valueTypes(),
            'eligible' => eligibleRows(),
        ),
        $extra
    );
}

function conditionIds(array $resolved)
{
    $ids = array();
    foreach ($resolved['conditions'] as $condition) {
        $ids[] = $condition['id'];
    }
    return $ids;
}

function selectedNames(array $resolved)
{
    $names = array();
    foreach ($resolved['selected'] as $entry) {
        $names[] = $entry['name'];
    }
    return $names;
}

function conditionFor(array $resolved, $id)
{
    foreach ($resolved['conditions'] as $condition) {
        if ($condition['id'] === $id) {
            return $condition;
        }
    }
    return null;
}

out('');
out('Phase 7 — the enrichment declaration, without an instance');
out('========================================================');

/* ==================================================================
 * 1. The shipped default changes nothing
 * ================================================================== */

section('1. A profile that declares nothing');

$plan = ValueEnrichmentTool::planFor(shippedProfile());
is_same(false, $plan['in_force'], 'the shipped default is not in force');
is_same('local_only', $plan['posture'], 'and its posture is local only');

$resolved = ValueEnrichmentTool::resolve($plan, facts());
is_same(array(), $resolved['selected'], 'nothing is selected');
is_same(array(), $resolved['conditions'], 'nothing is stated');
is_same(0, $resolved['declared'], 'nothing is declared');
is_same(false, $resolved['in_force'], 'and the block says so');

/*
 * The load-bearing one: `needsFacts()` is what decides whether the
 * page pays a second `GET /modules`, and on the shipped default it
 * must never say yes — otherwise every value page on every instance
 * gains an outbound call for a feature nobody has configured.
 */
is_same(
    array(),
    ValueEnrichmentTool::needsFacts($plan, valueTypes(), eligibleRows()),
    'and no second call to the modules service is needed'
);

/* A profile with no `enrichment` section at all is the same case. */
$bare = ValueEnrichmentTool::planFor(array('parameters' => array()));
is_same(false, $bare['in_force'], 'a profile with no section is inert');
is_same(
    'local_only',
    $bare['posture'],
    'and still carries the default posture'
);
is_same(24, $bare['max_age_hours'], 'and the default reuse window');
is_same(true, $bare['reuse_inert'], 'which is carried as inert');

/* And no profile at all — the run path passes exactly this. */
$none = ValueEnrichmentTool::planFor(null);
is_same(false, $none['in_force'], 'a null profile is inert');
is_same(
    array(),
    ValueEnrichmentTool::resolve($none, facts())['conditions'],
    'and states nothing'
);

/* ==================================================================
 * 2. What a hand-edited document can get wrong
 * ================================================================== */

section('2. Normalisation, and the document is not rewritten');

$messy = declaredProfile(array(
    'ip-dst' => 'circl_passivedns',
    ' domain ' => array('dns', 'dns', ' dns ', ''),
    '' => array('whatever'),
    'md5' => null,
), 'nonsense');
$before = json_encode($messy);
$plan = ValueEnrichmentTool::planFor($messy);
is_same(
    $before,
    json_encode($messy),
    'planFor does not touch the profile it was handed'
);
is_same(
    array('circl_passivedns'),
    $plan['auto_run']['ip-dst'],
    'a bare string becomes a one-module list'
);
is_same(
    array('dns'),
    $plan['auto_run']['domain'],
    'the type key is trimmed, and the names deduplicated'
);
is_same(
    false,
    isset($plan['auto_run']['']),
    'an empty type key is dropped'
);
is_same(
    false,
    isset($plan['auto_run']['md5']),
    'and so is a null list'
);
is_same(
    'local_only',
    $plan['posture'],
    'an unknown posture falls back to the safe one'
);
is_same(
    array('circl_passivedns', 'dns'),
    array_keys($plan['declared']),
    'the declared set is the union over types'
);
is_same(
    array('domain'),
    $plan['declared']['dns'],
    'and each name remembers what declared it'
);

$hours = ValueEnrichmentTool::planFor(declaredProfile(array()));
is_same(24, $hours['max_age_hours'], 'a missing window is 24 hours');
$zero = array('parameters' => array('enrichment' => array(
    'auto_run' => array('ip-dst' => array('dns')),
    'max_age_hours' => 0,
)));
is_same(
    24,
    ValueEnrichmentTool::planFor($zero)['max_age_hours'],
    'and a zero window is not a window'
);

/* ==================================================================
 * 3. The union over a value's types
 * ================================================================== */

section('3. Several types, one selection');

$plan = ValueEnrichmentTool::planFor(declaredProfile(array(
    'ip-dst' => array('circl_passivedns'),
    'ip-src' => array('circl_passivedns', 'dns'),
    'md5' => array('virustotal'),
)));
$declared = ValueEnrichmentTool::declaredFor($plan, valueTypes());
is_same(
    array('circl_passivedns', 'dns'),
    array_keys($declared),
    'a module declared under two of the value types is one entry'
);
is_same(
    array('ip-dst', 'ip-src'),
    $declared['circl_passivedns'],
    'carrying both, in the value order rather than the profile order'
);
is_same(
    false,
    isset($declared['virustotal']),
    'and a module declared for a type this value is not is not in it'
);

$resolved = ValueEnrichmentTool::resolve($plan, facts());
is_same(
    array('circl_passivedns', 'dns'),
    selectedNames($resolved),
    'both are selected under allow_external'
);
is_same(3, $resolved['declared'], 'three modules declared in all');
is_same(2, $resolved['applicable'], 'two of them apply to this value');

/*
 * The declared type wins where the module accepts it. `ip-dst` is the
 * value's most common type and `dns` was declared under `ip-src`,
 * which is also the only type this row accepts — the interesting case
 * is `circl_passivedns`, declared under both and accepting both.
 */
$byName = array();
foreach ($resolved['selected'] as $entry) {
    $byName[$entry['name']] = $entry;
}
is_same(
    'ip-dst',
    $byName['circl_passivedns']['type'],
    'the run type is the first declared type the module accepts'
);
is_same(
    'ip-src',
    $byName['dns']['type'],
    'and the only accepted one where there is only one'
);

/* The fall-back: declared under a type the module does not accept,
 * but eligible through another. */
$odd = ValueEnrichmentTool::planFor(declaredProfile(array(
    'ip-dst' => array('dns'),
)));
$oddResolved = ValueEnrichmentTool::resolve($odd, facts());
is_same(
    'ip-src',
    $oddResolved['selected'][0]['type'],
    'an unhonourable declared type falls back to the row default'
);

/* ==================================================================
 * 4. The type a value does not have
 * ================================================================== */

section('4. A declaration this value cannot use');

$plan = ValueEnrichmentTool::planFor(declaredProfile(array(
    'md5' => array('virustotal'),
    'sha256' => array('virustotal'),
    'ip-dst' => array('circl_passivedns'),
)));
$resolved = ValueEnrichmentTool::resolve($plan, facts());
is_same(
    array('type.unused'),
    conditionIds($resolved),
    'the unused types are one condition, not one each'
);
is_same(
    array('md5', 'sha256'),
    conditionFor($resolved, 'type.unused')['subjects'],
    'and it names them'
);
is_same(
    array('circl_passivedns'),
    selectedNames($resolved),
    'while the type the value does have still resolves'
);

$allUsed = ValueEnrichmentTool::planFor(declaredProfile(array(
    'ip-dst' => array('circl_passivedns'),
)));
is_same(
    array(),
    conditionIds(ValueEnrichmentTool::resolve($allUsed, facts())),
    'and a declaration with nothing spare states nothing'
);

/* ==================================================================
 * 5. Four reasons a declared module is not on the rail
 * ================================================================== */

section('5. Why a module is missing, in the instance order');

$plan = ValueEnrichmentTool::planFor(declaredProfile(array(
    'ip-dst' => array('virustotal'),
)));
is_same(
    array('virustotal'),
    ValueEnrichmentTool::needsFacts($plan, valueTypes(), eligibleRows()),
    'a declared module missing from the rail needs explaining'
);

$cases = array(
    'module.not_offered' => array(
        'present' => false, 'enabled' => false,
        'restricted' => false, 'accepts' => array(),
    ),
    'module.disabled' => array(
        'present' => true, 'enabled' => false,
        'restricted' => false, 'accepts' => array('ip-dst'),
    ),
    'module.restricted' => array(
        'present' => true, 'enabled' => true,
        'restricted' => true, 'accepts' => array('ip-dst'),
    ),
    'module.type_mismatch' => array(
        'present' => true, 'enabled' => true,
        'restricted' => false, 'accepts' => array('md5', 'sha256'),
    ),
);
foreach ($cases as $id => $moduleFacts) {
    $resolved = ValueEnrichmentTool::resolve($plan, facts(array(
        'offered' => array('virustotal', 'dns'),
        'modules' => array('virustotal' => $moduleFacts),
    )));
    is_same(array($id), conditionIds($resolved), $id . ' is stated');
    is_same(
        array(),
        selectedNames($resolved),
        '  and nothing is selected for it'
    );
    is_same(
        'virustotal',
        conditionFor($resolved, $id)['module'],
        '  and the condition names the module'
    );
}

/*
 * Precedence. A module that is absent *and* would have been disabled
 * *and* would not have accepted the type gets the first reason, which
 * is the one that actually stopped it — the same order
 * `getEnabledModules()` applies.
 */
$resolved = ValueEnrichmentTool::resolve($plan, facts(array(
    'offered' => array('dns'),
    'modules' => array('virustotal' => array(
        'present' => false, 'enabled' => false,
        'restricted' => true, 'accepts' => array('md5'),
    )),
)));
is_same(
    array('module.not_offered'),
    conditionIds($resolved),
    'absent outranks every later reason'
);

/* The type mismatch names both sides, because one of them is a typo
 * the reader can fix and the other is what the module wanted. */
$resolved = ValueEnrichmentTool::resolve($plan, facts(array(
    'offered' => array('virustotal'),
    'modules' => array('virustotal' => array(
        'present' => true, 'enabled' => true,
        'restricted' => false, 'accepts' => array('md5', 'sha256'),
    )),
)));
$note = conditionFor($resolved, 'module.type_mismatch')['note'];
is_true(
    strpos($note, 'ip-dst') !== false,
    'the mismatch names the type the profile filed it under'
);
is_true(
    strpos($note, 'md5, sha256') !== false,
    'and the types the module does accept'
);

/* A caller that skips needsFacts gets an honest non-answer. */
$resolved = ValueEnrichmentTool::resolve($plan, facts());
is_same(
    array('module.unresolved'),
    conditionIds($resolved),
    'without facts the reason is "not established", not a guess'
);

/* A near miss is named and never substituted. */
$cased = ValueEnrichmentTool::planFor(declaredProfile(array(
    'ip-dst' => array('VirusTotal'),
)));
$resolved = ValueEnrichmentTool::resolve($cased, facts(array(
    'offered' => array('virustotal', 'dns'),
    'modules' => array('VirusTotal' => array(
        'present' => false, 'enabled' => false,
        'restricted' => false, 'accepts' => array(),
    )),
)));
is_same(
    array('module.not_offered'),
    conditionIds($resolved),
    'a mis-cased name is not offered'
);
is_true(
    strpos(
        conditionFor($resolved, 'module.not_offered')['note'],
        'virustotal'
    ) !== false,
    'and the sentence names the module that is'
);
is_same(
    array(),
    selectedNames($resolved),
    'and nothing is quietly corrected into a selection'
);

/* ==================================================================
 * 6. The service, not the modules
 * ================================================================== */

section('6. Nobody answered the door');

$plan = ValueEnrichmentTool::planFor(declaredProfile(array(
    'ip-dst' => array('circl_passivedns', 'virustotal'),
)));
$resolved = ValueEnrichmentTool::resolve($plan, array(
    'service' => array('reachable' => false),
    'types' => valueTypes(),
    'eligible' => array(),
));
is_same(
    array('service.unreachable'),
    conditionIds($resolved),
    'one condition for the service, not one per module'
);
is_same(
    array('circl_passivedns', 'virustotal'),
    conditionFor($resolved, 'service.unreachable')['subjects'],
    'naming what could not be checked'
);
is_same(array(), selectedNames($resolved), 'and nothing is selected');
is_same(
    2,
    $resolved['applicable'],
    'the declaration is still reported in full'
);
/*
 * The distinction that matters: an unreachable service must never
 * produce the sentences that blame the modules. A reader told their
 * profile names a module this instance does not offer, when the truth
 * is that the list could not be read, will go and edit a profile that
 * was right.
 */
is_true(
    !in_array('module.not_offered', conditionIds($resolved), true)
        && !in_array('module.disabled', conditionIds($resolved), true),
    'and no module is blamed for the service being down'
);

/* ==================================================================
 * 7. The posture
 * ================================================================== */

section('7. What local_only withholds');

$declaration = array(
    'ip-dst' => array('circl_passivedns', 'extract_url_components'),
    'ip-src' => array('dns'),
);
$local = ValueEnrichmentTool::resolve(
    ValueEnrichmentTool::planFor(
        declaredProfile($declaration, 'local_only')
    ),
    facts()
);
is_same(
    array('extract_url_components'),
    selectedNames($local),
    'local_only selects only what answers from inside'
);
is_same(
    array('circl_passivedns', 'dns'),
    array_map(
        function ($entry) {
            return $entry['name'];
        },
        $local['withheld']
    ),
    'and withholds the external one and the unclassified one alike'
);
is_same(
    array('posture.external', 'posture.external'),
    conditionIds($local),
    'each withholding is stated'
);
/*
 * The two sentences differ, and they have to: *asking it tells
 * somebody* and *nobody has established whether asking it tells
 * somebody* are different facts, and only one of them is a reason to
 * go and classify the module.
 */
$notes = array();
foreach ($local['conditions'] as $condition) {
    $notes[$condition['module']] = $condition['note'];
}
is_true(
    $notes['circl_passivedns'] !== $notes['dns'],
    'and an unclassified module is not told it leaks'
);
is_true(
    strpos($notes['dns'], 'Nothing records') !== false,
    'it is told the classification is missing'
);
is_same(0, ValueEnrichmentTool::leavingCount($local), 'nothing leaves');

/*
 * **One fact, one producer.** The two sentences above are only
 * different because the catalogue row said `external` for one module
 * and `unknown` for the other — the shipped map names neither, so a
 * tool that resolved the map itself would have called both unknown
 * and told a reader their passive DNS lookup was merely unclassified.
 * The rail chips the row's answer and the tray counts it; the
 * withholding decision has to be the same value, not a second opinion
 * that happens to agree.
 */
$rows = eligibleRows();
$rows[0]['locality'] = 'local';
$rows[0]['locality_source'] = 'profile';
$rowWins = ValueEnrichmentTool::resolve(
    ValueEnrichmentTool::planFor(
        declaredProfile($declaration, 'local_only')
    ),
    facts(array('eligible' => $rows))
);
is_true(
    in_array('circl_passivedns', selectedNames($rowWins), true),
    'the catalogue row is where locality comes from'
);
is_same(
    'profile',
    $rowWins['selected'][0]['locality_source'],
    'and it carries the row source through'
);

/* With no row to read — phase 8's editor, resolving against no value
 * — the map answers. */
$noRow = ValueEnrichmentTool::resolve(
    ValueEnrichmentTool::planFor(
        declaredProfile(array('ip-dst' => array('geoip_city')),
            'local_only')
    ),
    facts(array('eligible' => array(array(
        'name' => 'geoip_city',
        'types' => array('ip-dst' => 17),
        'type' => 'ip-dst',
    ))))
);
is_same(
    array('geoip_city'),
    selectedNames($noRow),
    'and a row with no locality falls back to the shipped map'
);
is_same(
    'shipped',
    $noRow['selected'][0]['locality_source'],
    'saying so'
);

$external = ValueEnrichmentTool::resolve(
    ValueEnrichmentTool::planFor(
        declaredProfile($declaration, 'allow_external')
    ),
    facts()
);
is_same(
    array('circl_passivedns', 'dns', 'extract_url_components'),
    selectedNames($external),
    'allow_external selects all three'
);
is_same(
    array(),
    conditionIds($external),
    'and has nothing to state about them'
);
is_same(
    2,
    ValueEnrichmentTool::leavingCount($external),
    'two of the three leave the instance'
);

/*
 * **D15's honest statement, as an identity.** While every run takes a
 * press, `ask` cannot mean anything `allow_external` does not: the
 * page is already asking. Asserted as byte-equality rather than as
 * two similar-looking lists, so that the day something runs without a
 * press this check fails and the difference has to be designed.
 */
$ask = ValueEnrichmentTool::resolve(
    ValueEnrichmentTool::planFor(declaredProfile($declaration, 'ask')),
    facts()
);
$askCopy = $ask;
$askCopy['posture'] = 'allow_external';
is_same(
    json_encode($external),
    json_encode($askCopy),
    'ask resolves identically to allow_external, posture aside'
);

/*
 * The profile's own locality override, which is the only way an
 * operator who repointed their resolver can say so — **applied where
 * the rows are built**, which is `ValueProfile::enrichmentEligible()`
 * and is modelled here rather than assumed. Applying it in the tool as
 * well is precisely the second opinion `localityOf()` exists to
 * refuse, so the two checks below are the same invariant from both
 * sides: the override reaches the decision through the row, and only
 * through the row.
 */
function stampLocality(array $rows, array $overrides)
{
    foreach ($rows as $i => $row) {
        $resolved = ModuleLocality::resolve($row['name'], $overrides);
        $rows[$i]['locality'] = $resolved['locality'];
        $rows[$i]['locality_source'] = $resolved['source'];
    }
    return $rows;
}

$overrides = array('dns' => 'local');
$overridden = ValueEnrichmentTool::resolve(
    ValueEnrichmentTool::planFor(declaredProfile(
        array('ip-src' => array('dns')),
        'local_only',
        $overrides
    )),
    facts(array(
        'eligible' => stampLocality(eligibleRows(), $overrides),
    ))
);
is_same(
    array('dns'),
    selectedNames($overridden),
    'an override makes a module local for this profile'
);
is_same(
    'profile',
    $overridden['selected'][0]['locality_source'],
    'and the selection says where that came from'
);

$ignored = ValueEnrichmentTool::resolve(
    ValueEnrichmentTool::planFor(declaredProfile(
        array('ip-src' => array('dns')),
        'local_only',
        $overrides
    )),
    facts()
);
is_same(
    array(),
    selectedNames($ignored),
    'and an override the rows did not carry does not sneak in later'
);

/* ==================================================================
 * 8. The locality map itself
 * ================================================================== */

section('8. ModuleLocality');

is_same(
    'local',
    ModuleLocality::resolve('extract_url_components')['locality'],
    'a module in the shipped map is local'
);
is_same(
    'shipped',
    ModuleLocality::resolve('geoip_city')['source'],
    'and says the map answered'
);
is_same(
    'unknown',
    ModuleLocality::resolve('virustotal')['locality'],
    'a module the map does not name is unknown, not external'
);
is_same(
    'unknown',
    ModuleLocality::resolve('virustotal')['source'],
    'and says nothing answered'
);
is_true(
    ModuleLocality::leavesInstance('virustotal'),
    'but unknown counts as leaving the building'
);
is_true(
    !ModuleLocality::leavesInstance('clamav'),
    'and local does not'
);
is_same(
    'external',
    ModuleLocality::resolve('geoip_city', array(
        'geoip_city' => 'external',
    ))['locality'],
    'an override can contradict the shipped map in both directions'
);
is_same(
    'local',
    ModuleLocality::resolve('geoip_city', array(
        'geoip_city' => 'nonsense',
    ))['locality'],
    'and an override that says nothing falls through to it'
);
is_same(
    'unknown',
    ModuleLocality::resolve('dns', array('dns' => 'unknown'))['locality'],
    'including an override that says "unknown" out loud'
);
is_same(null, ModuleLocality::shippedFor(null), 'a null name is null');
is_same(
    false,
    in_array('countrycode', ModuleLocality::LOCAL_MODULES, true),
    'countrycode is not local: it fetches geognos.com to expand a TLD'
);
is_same(
    false,
    in_array('html_to_markdown', ModuleLocality::LOCAL_MODULES, true),
    'nor html_to_markdown, which fetches the URL itself'
);
is_same(
    false,
    in_array('dns', ModuleLocality::LOCAL_MODULES, true),
    'nor dns, which resolves through 8.8.8.8 unless configured'
);
is_same(
    false,
    in_array('mmdb_lookup', ModuleLocality::LOCAL_MODULES, true),
    'nor mmdb_lookup, whose default server is ip.circl.lu'
);

$retire = ModuleLocality::retirable(array(
    'dns' => 'external',
    'clamav' => 'local',
));
is_true($retire['retirable'], 'V1 retires when every module declares');
$retire = ModuleLocality::retirable(array(
    'dns' => 'external',
    'clamav' => null,
));
is_same(
    false,
    $retire['retirable'],
    'and does not while one of them does not'
);
is_same(
    array('clamav'),
    $retire['outstanding'],
    'naming which'
);
is_same(
    false,
    ModuleLocality::retirable(array())['retirable'],
    'an empty reading confirms nothing'
);

/* ==================================================================
 * 9. The whole thing, on the case the tab will actually meet
 * ================================================================== */

section('9. One realistic profile, end to end');

$plan = ValueEnrichmentTool::planFor(declaredProfile(array(
    'ip-dst' => array('circl_passivedns', 'virustotal'),
    'ip-src' => array('dns'),
    'md5' => array('virustotal'),
), 'local_only'));
$resolved = ValueEnrichmentTool::resolve($plan, facts(array(
    'offered' => array('circl_passivedns', 'dns',
        'extract_url_components'),
    'modules' => array('virustotal' => array(
        'present' => false, 'enabled' => false,
        'restricted' => false, 'accepts' => array(),
    )),
)));
is_same(3, $resolved['declared'], 'three modules named');
is_same(3, $resolved['applicable'], 'all three apply to some type here');
is_same(array(), selectedNames($resolved), 'and none is selected');
is_same(
    array('type.unused', 'posture.external', 'posture.external',
        'module.not_offered'),
    conditionIds($resolved),
    'with four conditions saying exactly why'
);
is_same(
    'local_only',
    $resolved['posture'],
    'the posture is carried for the strip to name'
);
is_same(
    true,
    $resolved['reuse_inert'],
    'and the reuse window is carried as inert'
);

out('');
out(sprintf(
    '%d checks, %d failures',
    $GLOBALS['checks'],
    $GLOBALS['failures']
));
out('');
exit($GLOBALS['failures'] === 0 ? 0 : 1);
