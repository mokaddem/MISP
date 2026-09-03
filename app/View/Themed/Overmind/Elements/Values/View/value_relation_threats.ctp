<?php
/**
 * The rail's third card: which named threats this value sits next to.
 *
 * The only thing on this tab that answers *what does this mean* rather
 * than *what is related*. Every section beside it lists edges; this
 * names the actors, campaigns, malware and tooling reachable through
 * the value, which is the read every peer platform leads with.
 *
 * **A named threat is a galaxy cluster.** `GalaxyCategory` holds the
 * rule and the evidence — in short, freetext tags cannot carry the
 * claim (the two most-used on the verification instance are the word
 * `malware` and ` C2`, and one malware family appears under seven
 * spellings) and no installed taxonomy names an individual threat,
 * they classify one.
 *
 * **Three devices, one question each**, because the first cut was a
 * flat list of eight equally-weighted rows and answered none of them
 * without being read end to end:
 *
 *   composition   *what sort of thing is around this?* — one line of
 *                 kind counts. The page's own idiom: the count line
 *                 summarises, the list below enumerates, and both
 *                 read the same array so they cannot disagree.
 *   lead          *what is the strongest thing?* — the top row
 *                 promoted out of the list into a sentence. §22.3
 *                 item 16 calls this the single highest-value
 *                 sentence the page could produce, so it is set as a
 *                 sentence rather than as row one of eight.
 *   list          the remainder, in the ranked order.
 *
 * **The lead's opening words change with how the cluster arrived**, so
 * the sentence stays true: a cluster tagged on the value is not one
 * the value *sits next to*. That also absorbs the state chip for the
 * lead row — the eyebrow already says it.
 *
 * **Colour is not used to separate the kinds, and nor is a glyph.**
 * Seven notion hues on this tab each mean one notion and nothing else
 * — a machine row wears its notion on its left edge — so four more
 * hues for actor, campaign, malware and tool would overload a grammar
 * the tab carries four times over. A leading glyph per kind was built
 * and cut for a different reason: it said exactly what the text beside
 * it already said. The lead block is set apart by *tone* — a tertiary
 * ground, not a hue — which the rationing does not spend.
 *
 * **Numbers carry body weight and their units do not.** `1 org · 2
 * events` repeated down eight rows put the constant words in front of
 * the varying figures, which is backwards for a card ranked on those
 * figures.
 *
 * Lazily loaded from ValuesController::viewRelationThreats.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$profile = $valueProfile;
$relations = $profile['relationships'];
$threats = $relations['threats'];

$rows = isset($threats['rows']) ? $threats['rows'] : array();
$total = isset($threats['total']) ? (int)$threats['total'] : 0;
$cap = isset($threats['cap']) ? (int)$threats['cap'] : 8;
$eventsRead = isset($threats['events_read'])
    ? (int)$threats['events_read']
    : 0;
$eventCap = isset($threats['event_cap'])
    ? (int)$threats['event_cap']
    : 0;

$kindWords = array(
    'actor' => __('actor'),
    'campaign' => __('campaign'),
    'malware' => __('malware'),
    'tool' => __('tool'),
);
/* Plural forms for the composition line, which counts them. */
$kindPlurals = array(
    'actor' => __('actors'),
    'campaign' => __('campaigns'),
    'malware' => __('malware'),
    'tool' => __('tools'),
);
$marks = array(
    'value' => __('on the value'),
    'claim' => __('claimed by an analyst'),
);
/*
 * How the lead sentence opens, per arrival. Not decoration: "sits next
 * to" is false of a cluster tagged on the value itself.
 */
$leadIns = array(
    'event' => __('Sits next to'),
    'value' => __('Tagged on this value'),
    'claim' => __('An analyst attributes this to'),
);

/*
 * Names that more than one cluster carries, and they are real: MITRE
 * ships APT28 as an intrusion set in the enterprise, mobile and
 * pre-attack galaxies, so `Malicious` draws `APT28 - G0007` three
 * times with near-identical counts and it reads as a duplicated row
 * rather than as three records. Those rows name their galaxy; the
 * rest do not, because on every other row it would be a word that
 * never varies.
 *
 * Counted over the whole list rather than the shown eight, so a name
 * does not start disambiguating itself only once the reader expands.
 */
$nameCounts = array();
foreach ($rows as $threat) {
    $key = mb_strtolower($threat['name']);
    $nameCounts[$key] = isset($nameCounts[$key])
        ? $nameCounts[$key] + 1
        : 1;
}

/* The composition, in the order the kinds are worth reading. */
$composition = array();
foreach (array('actor', 'campaign', 'malware', 'tool') as $kind) {
    $n = 0;
    foreach ($rows as $threat) {
        if ($threat['kind'] === $kind) {
            $n++;
        }
    }
    if ($n > 0) {
        $composition[] = '<span class="vp-threat-n">' . (int)$n
            . '</span> ' . h($kindPlurals[$kind]);
    }
}

$lead = empty($rows) ? null : $rows[0];
$listed = array_slice($rows, 1, max(0, $cap - 1));
$rest = array_slice($rows, $cap);

/**
 * The corroboration figures, numbers ahead of their units.
 *
 * A closure because the lead and the rows both print them and a second
 * copy is the thing that drifts.
 */
$figures = function (array $threat) {
    $parts = array();
    if (!empty($threat['orgs'])) {
        $parts[] = '<span class="vp-threat-n">' . (int)$threat['orgs']
            . '</span> ' . h(__n('org', 'orgs', (int)$threat['orgs']));
    }
    if (!empty($threat['events'])) {
        $parts[] = '<span class="vp-threat-n">'
            . (int)$threat['events'] . '</span> '
            . h(__n('event', 'events', (int)$threat['events']));
    }
    return implode(' <span class="vp-threat-sep">·</span> ', $parts);
};

/** One cluster's row, for everything below the lead. */
$row = function (array $threat) use (
    $kindWords, $marks, $baseurl, $nameCounts, $figures
) {
    $kind = isset($threat['kind']) ? $threat['kind'] : '';
    $word = isset($kindWords[$kind]) ? $kindWords[$kind] : $kind;
    $attachment = isset($threat['attachment'])
        ? $threat['attachment']
        : 'event';
    $key = mb_strtolower($threat['name']);
    $galaxy = !empty($nameCounts[$key]) && $nameCounts[$key] > 1
        ? $threat['galaxy']
        : null;
    ?>
    <li class="vp-threat">
        <a class="vp-threat-name"
           href="<?= $baseurl ?>/galaxy_clusters/view/<?=
               h($threat['id']) ?>"
           title="<?= h(sprintf(
               __('%s in the %s galaxy'),
               $threat['name'],
               $threat['galaxy']
           )) ?>"><?= h($threat['name']) ?></a>
        <span class="vp-threat-figs"><?= $figures($threat) ?></span>
        <span class="vp-threat-meta">
            <span class="vp-threat-kind"><?= h($word) ?></span>
            <?php if (isset($marks[$attachment])): ?>
                <span class="vp-threat-mark"><?=
                    h($marks[$attachment]) ?></span>
            <?php endif; ?>
            <?php if ($galaxy !== null): ?>
                <span class="vp-threat-galaxy"><?= h($galaxy) ?></span>
            <?php endif; ?>
        </span>
    </li>
    <?php
};
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--bs-secondary-color);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Named threats in this neighbourhood'),
        'panelIcon' => 'misp-icon misp-icon-galaxy misp-simple',
        'panelColor' => 'var(--bs-secondary-color)',
        /*
         * Scope and age only. The first cut also stated the read at
         * the foot of the card, which said the same thing twice in one
         * 340px column; the foot now carries the definitions instead.
         */
        'panelSub' => h(sprintf(
            __n('%d event', '%d events', $eventsRead),
            $eventsRead
        )) . '&nbsp;·&nbsp;' . $this->element(
            'Values/View/value_read_age',
            array(
                'readAt' => isset($relations['read_at'])
                    ? $relations['read_at'] : 0,
                'prefix' => __('read %s'),
            )
        ),
    )) ?>

    <div class="p-3">
        <?php if ($lead === null): ?>
            <div class="vp-threat-none">
                <?php if ($eventsRead === 0): ?>
                    <?= h(__(
                        'This value is in no event you may read, so'
                        . ' there is no neighbourhood to name.'
                    )) ?>
                <?php else: ?>
                    <?= h(sprintf(
                        __n(
                            'Nothing in this value\'s %d event names an'
                            . ' actor, a campaign, malware or a tool.',
                            'Nothing in this value\'s %d events names an'
                            . ' actor, a campaign, malware or a tool.',
                            $eventsRead
                        ),
                        $eventsRead
                    )) ?>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <?php /*
             * Only where it says something the rows do not: with one
             * kind and nothing folded away, "2 actors" sits above two
             * visible actor rows and is just a number to skip.
             */ ?>
            <?php if (count($composition) > 1 || !empty($rest)): ?>
                <p class="vp-threat-comp">
                    <?= implode(
                        ' <span class="vp-threat-sep">·</span> ',
                        $composition
                    ) ?>
                </p>
            <?php endif; ?>

            <?php
            $leadKind = isset($kindWords[$lead['kind']])
                ? $kindWords[$lead['kind']]
                : $lead['kind'];
            $leadIn = isset($leadIns[$lead['attachment']])
                ? $leadIns[$lead['attachment']]
                : $leadIns['event'];
            /* The lead needs the same disambiguation the rows get. */
            $leadKey = mb_strtolower($lead['name']);
            $leadGalaxy = !empty($nameCounts[$leadKey])
                && $nameCounts[$leadKey] > 1
                ? $lead['galaxy']
                : null;
            ?>
            <div class="vp-threat-lead">
                <span class="vp-threat-lead-in"><?= h($leadIn) ?></span>
                <a class="vp-threat-lead-name"
                   href="<?= $baseurl ?>/galaxy_clusters/view/<?=
                       h($lead['id']) ?>"
                   title="<?= h(sprintf(
                       __('%s in the %s galaxy'),
                       $lead['name'],
                       $lead['galaxy']
                   )) ?>"><?= h($lead['name']) ?></a>
                <span class="vp-threat-lead-meta">
                    <span class="vp-threat-kind"><?= h($leadKind) ?></span>
                    <?php if ($leadGalaxy !== null): ?>
                        <span class="vp-threat-galaxy"><?=
                            h($leadGalaxy) ?></span>
                    <?php endif; ?>
                    <?= $figures($lead) ?>
                </span>
            </div>

            <?php if (!empty($listed)): ?>
                <ul class="vp-threat-list">
                    <?php foreach ($listed as $threat) {
                        $row($threat);
                    } ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($rest)): ?>
                <?php /*
                 * A `details` rather than a button and a script: the
                 * rest are already in this fragment, so revealing them
                 * is a browser's job — and it arrives keyboard
                 * operable and indifferent to reduced-motion for free.
                 * The revealed list is height-capped and scrolls
                 * inside itself, because one value on the verification
                 * instance reaches 154 clusters and a rail that tall
                 * would run far past the tables beside it.
                 */ ?>
                <details class="vp-threat-expand">
                    <summary>
                        <?= h(sprintf(
                            __('Show %d more'),
                            count($rest)
                        )) ?>
                    </summary>
                    <ul class="vp-threat-list vp-threat-rest">
                        <?php foreach ($rest as $threat) {
                            $row($threat);
                        } ?>
                    </ul>
                </details>
            <?php endif; ?>

            <p class="vp-threat-note">
                <?= h(__('Galaxy clusters only.')) ?>
                <?php if ($eventCap > 0 && $eventsRead >= $eventCap): ?>
                    <?= h(sprintf(
                        __(
                            'This value is in more than %d events, and'
                            . ' these are the most recent.'
                        ),
                        $eventCap
                    )) ?>
                <?php endif; ?>
                <?= h(__(
                    'Organisations are the event creators — MISP'
                    . ' records no author for a tag.'
                )) ?>
            </p>
        <?php endif; ?>
    </div>
</div>
