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
 * **The name is the largest type in the rail**, and that is the one
 * liberty this card takes with the tab's scale: nothing else here
 * exceeds 0.82rem. A card whose whole purpose is to produce a name
 * should not print that name at the size of a caption.
 *
 * **Colour is not used to separate the kinds, and nor is a glyph.**
 * Seven notion hues on this tab each mean one notion and nothing else
 * — a machine row wears its notion on its left edge — so four more
 * hues for actor, campaign, malware and tool would overload a grammar
 * the tab carries four times over. A leading glyph per kind was built
 * and cut for a different reason: it said exactly what the chip beside
 * it already said. The kind is a word, and the two chip weights carry
 * the rest — hairline for a *category* the cluster belongs to, filled
 * for a *state* of how it reached this value.
 *
 * **Only the tighter attachments are marked.** A cluster that arrived
 * on an event needs no word, because the row's own event count already
 * says so; `on the value` and `claimed by an analyst` are marked
 * because they are more specific than the default. `on the object`
 * joins them when objects become taggable — see
 * `ValueProfile::neighbourhoodThreats`.
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
$marks = array(
    'value' => __('on the value'),
    'claim' => __('claimed by an analyst'),
);

$shown = array_slice($rows, 0, $cap);
$rest = array_slice($rows, $cap);

/*
 * Names that more than one cluster carries, and they are real: MITRE
 * ships APT28 as an intrusion set in the enterprise, mobile and
 * pre-attack galaxies, so `Malicious` draws `APT28 - G0007` twice with
 * the same counts and it reads as a duplicated row rather than as two
 * records. Those rows name their galaxy; the rest do not, because on
 * every other row it would be a word that never varies.
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

/**
 * One cluster's row.
 *
 * A closure rather than a second element, because the folded rows
 * below use exactly the same markup and a copy of it would be the
 * thing that drifts.
 */
$row = function (array $threat) use (
    $kindWords, $marks, $baseurl, $nameCounts
) {
    $kind = isset($threat['kind']) ? $threat['kind'] : '';
    $word = isset($kindWords[$kind]) ? $kindWords[$kind] : $kind;
    $attachment = isset($threat['attachment'])
        ? $threat['attachment']
        : 'event';
    $counts = array();
    $key = mb_strtolower($threat['name']);
    if (!empty($nameCounts[$key]) && $nameCounts[$key] > 1) {
        $counts[] = $threat['galaxy'];
    }
    if (!empty($threat['orgs'])) {
        $counts[] = sprintf(
            __n('%d org', '%d orgs', (int)$threat['orgs']),
            (int)$threat['orgs']
        );
    }
    if (!empty($threat['events'])) {
        $counts[] = sprintf(
            __n('%d event', '%d events', (int)$threat['events']),
            (int)$threat['events']
        );
    }
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
        <span class="vp-threat-kind"><?= h($word) ?></span>
        <span class="vp-threat-meta">
            <?php if (isset($marks[$attachment])): ?>
                <span class="vp-threat-mark"><?=
                    h($marks[$attachment]) ?></span>
            <?php endif; ?>
            <?= h(implode(' · ', $counts)) ?>
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
        'panelSub' => h(sprintf(
            __n(
                'Galaxy clusters reaching this value through %d event',
                'Galaxy clusters reaching this value through %d events',
                $eventsRead
            ),
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
        <?php if (empty($rows)): ?>
            <div class="vp-empty vp-empty-inline">
                <i class="misp-icon misp-icon-galaxy misp-simple"
                   aria-hidden="true"></i>
                <span>
                    <?php if ($eventsRead === 0): ?>
                        <?= h(__(
                            'This value is in no event you may read, so'
                            . ' there is no neighbourhood to name.'
                        )) ?>
                    <?php else: ?>
                        <?= h(sprintf(
                            __n(
                                'Nothing in this value\'s %d event names'
                                . ' an actor, a campaign, malware or a'
                                . ' tool.',
                                'Nothing in this value\'s %d events names'
                                . ' an actor, a campaign, malware or a'
                                . ' tool.',
                                $eventsRead
                            ),
                            $eventsRead
                        )) ?>
                    <?php endif; ?>
                </span>
            </div>
        <?php else: ?>
            <ul class="vp-threat-list">
                <?php foreach ($shown as $threat) {
                    $row($threat);
                } ?>
            </ul>

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
                <?= h(sprintf(
                    __n(
                        'Read from this value\'s %d event.',
                        'Read from this value\'s %d events.',
                        $eventsRead
                    ),
                    $eventsRead
                )) ?>
                <?php if ($eventCap > 0 && $eventsRead >= $eventCap): ?>
                    <?= h(sprintf(
                        __(
                            'This value is in more than %d, and these are'
                            . ' the most recent.'
                        ),
                        $eventCap
                    )) ?>
                <?php endif; ?>
                <?= h(__(
                    'Organisation counts are the creators of those'
                    . ' events: MISP records no author for a tag.'
                )) ?>
            </p>
        <?php endif; ?>
    </div>
</div>
