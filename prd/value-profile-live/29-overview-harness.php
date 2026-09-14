<?php
/**
 * Phase 29's two folding tools, without a database.
 *
 * `ValueFactsTool` and `ValueContextTool` take no `$user` and issue no
 * query — everything reaching them has been scoped by the model
 * already — so every rule they carry is assertable with arrays and no
 * instance at all. What needs an instance is the SQL behind them: the
 * three columns added to `Value::occurrenceSummaryFor`, the `value2`
 * predicate, and whether `Taxonomy::getTagConflicts` says what this
 * page expects it to on real tags. Those are §9's verification, not
 * this file's.
 *
 * The rules asserted here are the ones that would otherwise be checked
 * by reading: the strip's dead anchor, the date that is a row write,
 * the scale that must not be drawn, and the galaxy cluster that must
 * not be named.
 *
 * Run: `php prd/value-profile-live/29-overview-harness.php`
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

/*
 * MISP requires `mbstring` and `Taxonomy::getTaxonomyForTag` calls
 * `mb_strtolower` directly, so the application always has it. A bare
 * CLI need not, and a harness that cannot run without one is a harness
 * nobody runs — the tag namespaces this folds are ASCII in every case
 * the page has met, so the fallback changes no answer here.
 */
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string)
    {
        return strtolower($string);
    }
}

require_once APP . 'Lib/Tools/GalaxyCategory.php';
require_once APP . 'Lib/Tools/ValueFactsTool.php';
require_once APP . 'Lib/Tools/ValueContextTool.php';

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

function is_null_value($actual, $label)
{
    return is_same(true, $actual === null, $label);
}

/** 2026-09-14, so every age below is arithmetic a reader can redo. */
const NOW = 1789344000;

function summary(array $over = array())
{
    return $over + array(
        'occurrences' => 10,
        'events' => 7,
        'orgs' => 4,
        'oldest' => NOW - (86400 * 340),
        'newest' => NOW - (86400 * 5),
        'dated_from' => 3,
        'dated_at' => 6,
        'published' => 5,
    );
}

function types()
{
    return array(
        array('type' => 'ip-dst', 'count' => 7),
        array('type' => 'ip-src', 'count' => 2),
        array('type' => 'domain|ip', 'count' => 1),
    );
}

/**
 * `8.8.8.8`'s own shape on the verification instance, which is the case
 * that matters: three kinds of row, summing to a total the page must
 * not print under the word *Sightings*.
 */
function sightings(array $over = array())
{
    return $over + array(
        'total' => 53,
        'sighting' => 47,
        'fp' => 4,
        'expiration' => 2,
    );
}

function cell(array $facts, $label)
{
    foreach ($facts as $fact) {
        if ($fact['label'] === $label) {
            return $fact;
        }
    }
    return null;
}

out();
out('== ValueFactsTool::strip — the five cells ==');

$facts = ValueFactsTool::strip(summary(), types(), sightings(), NOW);

is_same(6, count($facts), 'six cells');
is_same(
    array('First seen', 'Last seen', 'Occurrences', 'Events',
        'Organisations', 'Sightings'),
    array_column($facts, 'label'),
    'the labels, in order'
);
is_same(
    '47',
    cell($facts, 'Sightings')['value'],
    'the cell prints sightings proper, as the card beneath it does'
);
is_true(
    cell($facts, 'Sightings')['value'] !== '53',
    'and not the total of all three kinds, which the card calls something else'
);
is_same(
    '4 false positives',
    cell($facts, 'Sightings')['sub'],
    'and names the false positives among them'
);
is_same(
    'sightings',
    cell($facts, 'Sightings')['tab'],
    'and links to the panel that lists them'
);
is_null_value(
    ValueFactsTool::strip(
        summary(),
        types(),
        sightings(array('fp' => 0)),
        NOW
    )[5]['sub'],
    'no false positives, no sub — rather than a nought nobody needs'
);

out();
out('== The anchor D11 renamed and the fixture did not ==');

is_same(
    'assessment',
    cell($facts, 'Organisations')['tab'],
    'organisations links to #tab-assessment'
);
is_true(
    cell($facts, 'Organisations')['tab'] !== 'verdict',
    'and not to the fixture\'s #tab-verdict, which addresses nothing'
);
is_same(
    'occurrences',
    cell($facts, 'Occurrences')['tab'],
    'occurrences links to its own tab'
);
is_same(
    'timeline',
    cell($facts, 'First seen')['tab'],
    'a date links to the timeline'
);

out();
out('== The numbers and their subs ==');

is_same('10', cell($facts, 'Occurrences')['value'], 'occurrence count');
is_same('3 types', cell($facts, 'Occurrences')['sub'], 'the types sub');
is_same('7', cell($facts, 'Events')['value'], 'event count');
is_same('5 published', cell($facts, 'Events')['sub'], 'the published sub');
is_same('4', cell($facts, 'Organisations')['value'], 'organisation count');
is_null_value(
    cell($facts, 'Organisations')['sub'],
    'and no sub — naming the organisations costs a query'
);

$none = ValueFactsTool::strip(summary(), array(), sightings(), NOW);
is_null_value(
    cell($none, 'Occurrences')['sub'],
    'no types, no types sub'
);

out();
out('== D4: a date that is a row write says so ==');

is_same(
    '11 months ago',
    cell($facts, 'First seen')['sub'],
    'a declared first_seen gets an age — 340 days reads as 11 months'
);
is_same('5 days ago', cell($facts, 'Last seen')['sub'], 'and so does last_seen');

$undated = ValueFactsTool::strip(
    summary(array('dated_from' => 0, 'dated_at' => 0)),
    types(),
    sightings(),
    NOW
);
is_same(
    'record date, not observed',
    cell($undated, 'First seen')['sub'],
    'nothing declares first_seen — the cell refuses to call it an observation'
);
is_same(
    'record date, not observed',
    cell($undated, 'Last seen')['sub'],
    'and the same for last_seen'
);
is_true(
    cell($undated, 'First seen')['value'] !== '',
    'the date is still printed — it is the best answer there is'
);

$half = ValueFactsTool::strip(
    summary(array('dated_from' => 0)),
    types(),
    sightings(),
    NOW
);
is_same(
    'record date, not observed',
    cell($half, 'First seen')['sub'],
    'the two cells are judged separately: first_seen undeclared'
);
is_same(
    '5 days ago',
    cell($half, 'Last seen')['sub'],
    'while last_seen, declared on the same rows, keeps its age'
);

$empty = ValueFactsTool::strip(
    summary(array('oldest' => null, 'newest' => null)),
    array(),
    sightings(),
    NOW
);
is_same(
    'Not recorded',
    cell($empty, 'First seen')['value'],
    'no occurrence at all reads as not recorded'
);
is_null_value(
    cell($empty, 'First seen')['tab'],
    'and links nowhere, because there is nothing to open'
);

out();
out('== agePhrase over the range a value can span ==');

is_same('today', ValueFactsTool::agePhrase(NOW, NOW), 'today');
is_same(
    'yesterday',
    ValueFactsTool::agePhrase(NOW - 86400, NOW),
    'yesterday'
);
is_same(
    '9 days ago',
    ValueFactsTool::agePhrase(NOW - (86400 * 9), NOW),
    'inside a month, in days'
);
is_same(
    '2 months ago',
    ValueFactsTool::agePhrase(NOW - (86400 * 70), NOW),
    'beyond a month, in months'
);
is_same(
    '3 years ago',
    ValueFactsTool::agePhrase(NOW - (86400 * 1200), NOW),
    'beyond a year, in years'
);
is_same(
    'dated in the future',
    ValueFactsTool::agePhrase(NOW + (86400 * 3), NOW),
    'a declared last_seen ahead of the clock is not "today"'
);

out();
out('== The value2 note ==');

is_null_value(
    ValueFactsTool::value2Note(array()),
    'nothing matched that way — no note at all, rather than a zero'
);
is_same(
    '1 occurrence has it as the second half of a domain|ip',
    ValueFactsTool::value2Note(
        array(array('type' => 'domain|ip', 'count' => 1))
    ),
    'one row, named by its type'
);
is_same(
    '5 occurrences have it as the second half of a ip-dst|port',
    ValueFactsTool::value2Note(
        array(
            array('type' => 'ip-dst|port', 'count' => 4),
            array('type' => 'domain|ip', 'count' => 1),
        )
    ),
    'the total across types, named by the commonest'
);

out();
out('== ValueContextTool::taxonomies ==');

function tag($name, array $over = array())
{
    return $over + array(
        'tag' => array(
            'id' => 1,
            'name' => $name,
            'colour' => '#FFC000',
            'is_galaxy' => false,
            'local' => false,
        ),
        'count' => 4,
    );
}

$taxonomies = array(
    'tlp' => array('predicates' => array(
        'red' => array('expanded' => 'Red', 'numerical' => 0, 'entries' => array()),
        'amber' => array('expanded' => 'Amber', 'numerical' => 25, 'entries' => array()),
        'green' => array('expanded' => 'Green', 'numerical' => 50, 'entries' => array()),
        'clear' => array('expanded' => 'Clear', 'numerical' => 100, 'entries' => array()),
    )),
    'admiralty-scale' => array('predicates' => array(
        'source-reliability' => array(
            'expanded' => 'Source reliability',
            'numerical' => null,
            'entries' => array(
                'a' => array('expanded' => 'Completely reliable', 'numerical' => 100),
                'b' => array('expanded' => 'Usually reliable', 'numerical' => 75),
                'c' => array('expanded' => 'Fairly reliable', 'numerical' => 50),
                'd' => array('expanded' => 'Not usually reliable', 'numerical' => 25),
                'e' => array('expanded' => 'Unreliable', 'numerical' => 0),
            ),
        ),
    )),
    'type' => array('predicates' => array(
        'OSINT' => array('expanded' => null, 'numerical' => null, 'entries' => array()),
        'internal' => array('expanded' => null, 'numerical' => null, 'entries' => array()),
    )),
);

$groups = ValueContextTool::taxonomies(
    array(
        'tlp:amber' => tag('tlp:amber'),
        'tlp:green' => tag('tlp:green'),
        'type:OSINT' => tag('type:OSINT'),
        'admiralty-scale:source-reliability="b"'
            => tag('admiralty-scale:source-reliability="b"'),
    ),
    $taxonomies,
    array('tlp:amber' => true, 'tlp:green' => true)
);

is_same(3, count($groups), 'three namespaces from four tags');
is_same('tlp', $groups[0]['taxonomy'], 'the busiest namespace leads');
is_true($groups[0]['conflict'], 'tlp is flagged, because MISP flagged it');

$admiralty = null;
$type = null;
foreach ($groups as $group) {
    if ($group['taxonomy'] === 'admiralty-scale') {
        $admiralty = $group;
    }
    if ($group['taxonomy'] === 'type') {
        $type = $group;
    }
}
is_true(!$admiralty['conflict'], 'a single-tag namespace is not in conflict');
is_same(4, $groups[0]['tags'][0]['count'], 'a tag carries its occurrence count');
is_true(
    !isset($groups[0]['tags'][0]['orgs']),
    'and names no organisations — the tag tables do not record who applied one'
);

out();
out('== The scale is the taxonomy\'s own numbers ==');

is_same(
    array(
        'label' => 'Source reliability',
        'position' => 2,
        'of' => 5,
        'reading' => 'b — Usually reliable',
    ),
    $admiralty['scale'],
    'b is 2nd — the scale runs best-first, not by the raw numbers'
);
is_true(
    !isset($groups[0]['scale']),
    'two tlp tags draw no scale — a contradiction is not a position'
);
is_true(
    !isset($type['scale']),
    'an unnumbered taxonomy draws none either, rather than inventing a rank'
);

/*
 * The numerically-keyed taxonomy, which is the one that broke.
 * `admiralty-scale:information-credibility` is keyed `1` to `6`, PHP
 * casts those keys to integers, and a strict `array_search` for the
 * string `'2'` then finds nothing and falls through to position 1 —
 * so every credibility tag drew *1 of 6* whatever it said. Caught on
 * `sage.png`, live.
 */
$credibility = array('admiralty-scale' => array('predicates' => array(
    'information-credibility' => array(
        'expanded' => 'Information Credibility',
        'numerical' => null,
        'entries' => array(
            '1' => array('expanded' => 'Confirmed by other sources', 'numerical' => 100),
            '2' => array('expanded' => 'Probably true', 'numerical' => 75),
            '3' => array('expanded' => 'Possibly true', 'numerical' => 50),
            '6' => array('expanded' => 'Truth cannot be judged', 'numerical' => 50),
            '4' => array('expanded' => 'Doubtful', 'numerical' => 25),
            '5' => array('expanded' => 'Improbable', 'numerical' => 0),
        ),
    ),
)));
$numeric = ValueContextTool::taxonomies(
    array('admiralty-scale:information-credibility="2"'
        => tag('admiralty-scale:information-credibility="2"')),
    $credibility,
    array()
);
is_same(
    2,
    $numeric[0]['scale']['position'],
    'a numerically-keyed entry finds its own position, not position 1'
);
is_same(6, $numeric[0]['scale']['of'], 'out of all six');
is_same(
    '2 — Probably true',
    $numeric[0]['scale']['reading'],
    'and reads as the taxonomy words it'
);

$worst = ValueContextTool::taxonomies(
    array('admiralty-scale:information-credibility="5"'
        => tag('admiralty-scale:information-credibility="5"')),
    $credibility,
    array()
);
is_same(
    6,
    $worst[0]['scale']['position'],
    'the bottom of the scale is last, not first'
);

$single = ValueContextTool::taxonomies(
    array('tlp:amber' => tag('tlp:amber')),
    $taxonomies,
    array()
);
is_same(
    array(
        'label' => 'tlp',
        'position' => 3,
        'of' => 4,
        'reading' => 'amber — Amber',
    ),
    $single[0]['scale'],
    'a predicate-level tag scales over the namespace\'s predicates'
);

out();
out('== What the instance carries, and the two shapes it broke ==');

/*
 * Both of these came off `8.8.8.8` on the verification instance rather
 * than out of a design: it carries five freetext tags and a `PAP:RED`,
 * and the first cut of this tool headed six groups for them and found
 * no taxonomy for any.
 */
$real = ValueContextTool::taxonomies(
    array(
        'tlp:white' => tag('tlp:white'),
        'PAP:RED' => tag('PAP:RED'),
        'asyncrat' => tag('asyncrat'),
        'c2' => tag('c2'),
        'Gh0stRAT' => tag('Gh0stRAT'),
        'historicalandnew' => tag('historicalandnew'),
        'mightcontainvariantsofasyncrat' => tag('mightcontainvariantsofasyncrat'),
    ),
    $taxonomies + array('pap' => array('predicates' => array(
        'red' => array('expanded' => 'Red', 'numerical' => null, 'entries' => array()),
        'amber' => array('expanded' => 'Amber', 'numerical' => null, 'entries' => array()),
    ))),
    array()
);

is_same(3, count($real), 'three groups from seven tags, not seven');
$labels = array_column($real, 'taxonomy');
is_true(
    in_array('Not in a taxonomy', $labels, true),
    'the five freetext tags share one group'
);
is_true(
    in_array('pap', $labels, true),
    'PAP:RED is folded under the taxonomy MISP stores as pap'
);
is_true(
    !in_array('PAP', $labels, true),
    'and not under its own spelling, which would find no taxonomy at all'
);
foreach ($real as $group) {
    if ($group['taxonomy'] === 'Not in a taxonomy') {
        is_same(5, count($group['tags']), 'all five of them, in that one group');
        is_true(!isset($group['scale']), 'and no scale over tags with no order');
    }
}

$mixedCase = ValueContextTool::taxonomies(
    array('ADMIRALTY-SCALE:Source-Reliability="B"'
        => tag('ADMIRALTY-SCALE:Source-Reliability="B"')),
    $taxonomies,
    array()
);
is_same(
    2,
    $mixedCase[0]['scale']['position'],
    'a tag shouted in capitals still finds its place on the scale'
);

out();
out('== Local tags, and galaxies kept out of the taxonomy list ==');

$mixed = ValueContextTool::taxonomies(
    array(
        'workflow:state="reviewed"' => tag(
            'workflow:state="reviewed"',
            array('tag' => array(
                'id' => 9,
                'name' => 'workflow:state="reviewed"',
                'colour' => '#3F51B5',
                'is_galaxy' => false,
                'local' => true,
            ))
        ),
        'misp-galaxy:mitre-attack-pattern="Phishing"' => tag(
            'misp-galaxy:mitre-attack-pattern="Phishing"',
            array('tag' => array(
                'id' => 8,
                'name' => 'misp-galaxy:mitre-attack-pattern="Phishing"',
                'colour' => '#0088CC',
                'is_galaxy' => true,
                'local' => false,
            ))
        ),
    ),
    $taxonomies,
    array()
);
is_same(1, count($mixed), 'a galaxy tag is not a taxonomy group');
is_true($mixed[0]['tags'][0]['local'], 'a local tag is marked local');

out();
out('== ValueContextTool::galaxies — the cluster ACL is the caller\'s ==');

$galaxyTags = array(
    'misp-galaxy:mitre-attack-pattern="Phishing"' => tag(
        'misp-galaxy:mitre-attack-pattern="Phishing"',
        array('tag' => array(
            'id' => 8,
            'name' => 'misp-galaxy:mitre-attack-pattern="Phishing"',
            'colour' => '#0088CC',
            'is_galaxy' => true,
            'local' => false,
        ))
    ),
    'misp-galaxy:threat-actor="Withheld"' => tag(
        'misp-galaxy:threat-actor="Withheld"',
        array('tag' => array(
            'id' => 7,
            'name' => 'misp-galaxy:threat-actor="Withheld"',
            'colour' => '#CC0000',
            'is_galaxy' => true,
            'local' => false,
        ))
    ),
);

$galaxies = ValueContextTool::galaxies($galaxyTags, array(
    'misp-galaxy:mitre-attack-pattern="Phishing"' => array(
        'GalaxyCluster' => array(
            'value' => 'Phishing',
            'type' => 'mitre-attack-pattern',
            'tag_name' => 'misp-galaxy:mitre-attack-pattern="Phishing"',
        ),
    ),
));

is_same(1, count($galaxies), 'the cluster that came back is drawn');
is_same('Phishing', $galaxies[0]['name'], 'named by the cluster, not the tag');
is_same(4, $galaxies[0]['n'], 'counted across its events');
is_true(
    $galaxies[0]['kind'] !== null && $galaxies[0]['kind'] !== '',
    'and carries a kind'
);
foreach ($galaxies as $galaxy) {
    is_true(
        strpos($galaxy['name'], 'Withheld') === false,
        'a cluster the viewer may not know exists is absent, and uncounted'
    );
}
is_same(
    array(),
    ValueContextTool::galaxies($galaxyTags, array()),
    'no cluster resolves — the card draws nothing, and says nothing about why'
);

out();
out(sprintf(
    '%d checks, %d failures',
    $GLOBALS['checks'],
    $GLOBALS['failures']
));
exit($GLOBALS['failures'] === 0 ? 0 : 1);
