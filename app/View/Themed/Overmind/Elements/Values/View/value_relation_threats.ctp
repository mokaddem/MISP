<?php
/**
 * The rail's third card: which named threats this value sits next to.
 *
 * The only thing on this tab that answers *what does this mean* rather
 * than *what is related*. Every section beside it lists edges; this
 * names the actors, campaigns, malware and tooling reachable through
 * the value, which is the read every peer platform leads with.
 *
 * **A named threat is a galaxy cluster**, and the card says so in the
 * subtitle by naming the four kinds rather than by using the phrase
 * and leaving it to be guessed. `GalaxyCategory` holds the rule and
 * the evidence — in short, freetext tags cannot carry the claim (the
 * two most-used on the verification instance are the word `malware`
 * and ` C2`, and one malware family appears under seven spellings)
 * and no installed taxonomy names an individual threat, they classify
 * one.
 *
 * **Clusters wear MISP's own cluster badge.** `GalaxyColour` derives a
 * hue from the galaxy's name and every galaxy view in MISP tints its
 * clusters with it, so a Threat Actor cluster is the same colour here
 * as on the event page. That is a galaxy hue, not one of this tab's
 * seven notion hues, so the notion grammar is untouched — and dark
 * mode is handled by `--galaxy-alpha`, which `mainOvermind.css` lifts
 * from 0.12 to 0.92 so the badge's own text colour still reads.
 *
 * **The counts at the top are the filter.** They were a static
 * composition line first, which told a reader the neighbourhood held
 * 63 malware and then gave them no way to see them. As pills they
 * answer the same question and act on it: picking a kind shows every
 * cluster of that kind rather than the eight the card opens with, and
 * the per-row kind label goes away while it is picked because the
 * pill already says it.
 *
 * Lazily loaded from ValuesController::viewRelationThreats.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
App::uses('GalaxyColour', 'Tools');

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
/* Pill labels, which count them. */
$kindLabels = array(
    'actor' => __('Actors'),
    'campaign' => __('Campaigns'),
    'malware' => __('Malware'),
    'tool' => __('Tools'),
);
$marks = array(
    'value' => __('on the value'),
);

/*
 * What a claim was written on. The card counts a claim about the
 * occurrence, about the event it is in, and about the object it sits
 * in — so the hover has to say which, or *Human claim* is two words
 * that hide the difference between a statement about this address and
 * one about a report containing it.
 */
$anchorWords = array(
    'Attribute' => __('on this value'),
    'Event' => __('on an event it appears in'),
    'Object' => __('on the object it sits in'),
);

/*
 * The hover card's heading word, and the same map the asserted section
 * passes, because it is the same element. Only the cluster entry is
 * ever reached from here — a row on this card is always a cluster —
 * but the element takes the map, not the word.
 */
$targetKindWords = array(
    'Event' => __('event'),
    'GalaxyCluster' => __('galaxy cluster'),
    'Object' => __('object'),
    'Attribute' => __('attribute'),
);

/* The hover card element renders itself through the view. */
$view = $this;

/**
 * Who asserted a cluster, how, and when — one line per claim.
 *
 * The row has room for two words, so this goes in the hover beside the
 * figures. The asserted section is where the whole claim, with its
 * author and its direction, is laid out properly; this is the peek.
 *
 * Returns the lines rather than a joined string, because a `\n` inside
 * markup is whitespace: joined, two claims render as one run-on
 * sentence.
 *
 * @param array $claims As `neighbourhoodThreats` recorded them
 * @return array
 */
$claimLines = function (array $claims) use ($anchorWords) {
    $lines = array();
    foreach ($claims as $claim) {
        $where = isset($anchorWords[$claim['anchor']])
            ? $anchorWords[$claim['anchor']]
            : '';
        $lines[] = trim(sprintf(
            __('%s claimed "%s" %s'),
            $claim['org'],
            $claim['type'],
            $where
        )) . ($claim['date'] === '' ? '' : ' · ' . $claim['date']);
    }
    return $lines;
};

/*
 * Names that more than one cluster carries, and they are real: MITRE
 * ships APT28 as an intrusion set in the enterprise, mobile and
 * pre-attack galaxies, so `Malicious` draws `APT28 - G0007` three
 * times with near-identical counts and it reads as a duplicated row
 * rather than as three records. Those rows name their galaxy; the
 * rest do not, because on every other row it would be a word that
 * never varies.
 */
$nameCounts = array();
foreach ($rows as $threat) {
    $key = mb_strtolower($threat['name']);
    $nameCounts[$key] = isset($nameCounts[$key])
        ? $nameCounts[$key] + 1
        : 1;
}

/* Kinds present, in the order they are worth reading. */
$kindCounts = array();
foreach (array('actor', 'campaign', 'malware', 'tool') as $kind) {
    $n = 0;
    foreach ($rows as $threat) {
        if ($threat['kind'] === $kind) {
            $n++;
        }
    }
    if ($n > 0) {
        $kindCounts[$kind] = $n;
    }
}

/**
 * One cluster's row.
 *
 * @param array $threat
 * @param bool $folded Beyond the opening cut, hidden until asked for
 */
$row = function (array $threat, $folded) use (
    $kindWords, $marks, $baseurl, $nameCounts, $claimLines, $view,
    $targetKindWords
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
    $claimed = $attachment === 'claim' && !empty($threat['claims']);
    $extra = isset($marks[$attachment]) || $claimed || $galaxy !== null;
    $figures = array();
    if (!empty($threat['orgs'])) {
        $figures[] = '<span class="vp-threat-n">'
            . (int)$threat['orgs'] . '</span> '
            . h(__n('org', 'orgs', (int)$threat['orgs']));
    }
    if (!empty($threat['events'])) {
        $figures[] = '<span class="vp-threat-n">'
            . (int)$threat['events'] . '</span> '
            . h(__n('event', 'events', (int)$threat['events']));
    }
    ?>
    <?php $url = $baseurl . '/galaxy_clusters/view/' . $threat['id']; ?>
    <li class="vp-threat<?= $folded ? ' vp-threat-folded' : '' ?>"
        data-vp-threat-kind="<?= h($kind) ?>">
        <span class="vp-threat-cell">
            <a class="vp-threat-badge"
               style="<?= GalaxyColour::badgeStyle($threat['galaxy']) ?>"
               href="<?= h($url) ?>"
               title="<?= h(sprintf(
                   __('%s in the %s galaxy'),
                   $threat['name'],
                   $threat['galaxy']
               )) ?>"><?= h($threat['name']) ?></a>
            <?php if (!empty($threat['target'])): ?>
                <?php /*
                 * The asserted section's own hover card, rendered
                 * from the same element and the same target shape —
                 * everything recorded about the cluster, its tag
                 * name, its description, its audience.
                 *
                 * It is a CSS card on `:hover`/`:focus-within` rather
                 * than a `title`, which is the element's own reason
                 * and applies here twice over: this fragment arrives
                 * through `loadAjaxContainer`, so a Bootstrap tooltip
                 * declared in it would never bind, and a native
                 * `title` is not reachable from the keyboard.
                 */ ?>
                <?= $view->element(
                    'Values/View/value_claim_target_card',
                    array(
                        'target' => $threat['target'],
                        'url' => $url,
                        'kindWords' => $targetKindWords,
                    )
                ) ?>
            <?php endif; ?>
        </span>
        <?php /*
         * The figures name their own sources on hover. `2 orgs · 3
         * events` is the right size for the rail and says nothing
         * about *which*, and which is the question a reader checking
         * corroboration actually has — one organisation reporting a
         * cluster three times is not three organisations agreeing.
         *
         * Built from the shared card's classes rather than from its
         * element: that element is documented as being about a claim's
         * target and nothing else, and this is about the row's
         * evidence. Same look, different subject.
         */ ?>
        <span class="vp-threat-right vp-claim-tipwrap">
            <span class="vp-threat-kind"><?= h($word) ?></span>
            <span class="vp-threat-figs"><?= implode(
                ' <span class="vp-threat-sep">·</span> ',
                $figures
            ) ?></span>
            <?php if (!empty($threat['org_names'])
                || !empty($threat['event_list'])
            ): ?>
                <span class="vp-claim-tip" role="tooltip">
                    <span class="vp-claim-tiphead"><?=
                        h(__('Where this comes from')) ?></span>
                    <?php if (!empty($threat['org_names'])): ?>
                        <span class="vp-claim-tiprow">
                            <b><?= h(__('Organisations')) ?></b>
                            <span><?= h(implode(', ',
                                $threat['org_names'])) ?></span>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($threat['event_list'])): ?>
                        <span class="vp-claim-tiprow">
                            <b><?= h(sprintf(
                                __('Events (%d)'),
                                count($threat['event_list'])
                            )) ?></b>
                            <span class="vp-threat-tipevents">
                                <?php foreach (array_slice(
                                    $threat['event_list'], 0, 6
                                ) as $event): ?>
                                    <span class="vp-threat-tipevent">
                                        <a class="vp-claim-link"
                                           href="<?= $baseurl
                                               ?>/events/view2/<?=
                                               h($event['id']) ?>">#<?=
                                               h($event['id']) ?></a>
                                        <?= h($event['info'] === ''
                                            ? $event['date']
                                            : $event['info']) ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php $spare = count(
                                    $threat['event_list']
                                ) - 6; ?>
                                <?php if ($spare > 0): ?>
                                    <span class="vp-threat-tipevent">
                                        <?= h(sprintf(
                                            __('and %d more'),
                                            $spare
                                        )) ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($threat['claims'])): ?>
                        <span class="vp-claim-tiprow">
                            <b><?= h(sprintf(
                                __('Claims (%d)'),
                                count($threat['claims'])
                            )) ?></b>
                            <span class="vp-threat-tiplines">
                                <?php foreach ($claimLines(
                                    $threat['claims']
                                ) as $line): ?>
                                    <span><?= h($line) ?></span>
                                <?php endforeach; ?>
                            </span>
                        </span>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </span>
        <?php if ($extra): ?>
            <span class="vp-threat-extra">
                <?php if ($claimed): ?>
                    <?php /*
                     * The tab's own human-claim mark, **word
                     * included**: `.vp-rel-prov-human`, `fa-user-pen`
                     * and the words *Human claim* are what the
                     * asserted and references panels already use to
                     * say *a person wrote this*, and `--vp-rel-human`
                     * is that notion's colour.
                     *
                     * This card first said *claimed by an analyst*,
                     * which is the same notion under a second name.
                     * The separation rule this tab carries four times
                     * over — colour, form, **word**, place — only
                     * works if the word is the same word everywhere,
                     * so a synonym weakens the thing the styling was
                     * borrowed to reinforce.
                     */ ?>
                    <?php /*
                     * No `title` on the mark any more: who claimed
                     * what is in the figures hover beside it, which
                     * is findable and keyboard-reachable where a
                     * native tooltip was neither.
                     */ ?>
                    <span class="vp-rel-prov vp-rel-prov-human">
                        <i class="fas fa-user-pen" aria-hidden="true"></i>
                        <?= h(__('Human claim')) ?>
                    </span>
                <?php endif; ?>
                <?php if (isset($marks[$attachment])): ?>
                    <span class="vp-threat-mark"><?=
                        h($marks[$attachment]) ?></span>
                <?php endif; ?>
                <?php if ($galaxy !== null): ?>
                    <span class="vp-threat-galaxy"><?=
                        h($galaxy) ?></span>
                <?php endif; ?>
            </span>
        <?php endif; ?>
    </li>
    <?php
};
?>
<div class="card shadow-sm mb-3 vp-panel" data-vp-threats
     data-vp-threat-view="top"
     style="--vp-panel-color: var(--bs-secondary-color);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Named threats in this neighbourhood'),
        'panelIcon' => 'misp-icon misp-icon-galaxy misp-simple',
        'panelColor' => 'var(--bs-secondary-color)',
        /*
         * The subtitle is where *named threat* gets defined, by naming
         * the four kinds. It also carries the scope and the age, so
         * the card needs no footer — the first cut had one that
         * repeated the scope already stated here.
         */
        'panelSub' => h(__('Actors, campaigns, malware or tools'))
            . '&nbsp;·&nbsp;' . h(sprintf(
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
        <?php if (empty($rows)): ?>
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

            <?php if (count($kindCounts) > 1 || $total > $cap): ?>
                <div class="vp-pillgroup vp-threat-filters" role="group"
                     aria-label="<?= h(__('Show one kind of threat')) ?>">
                    <button type="button" class="vp-pill active"
                            data-vp-threat-filter="top"
                            aria-pressed="true">
                        <?= h(__('All')) ?>
                        <span class="vp-pill-n"><?= (int)$total ?></span>
                    </button>
                    <?php foreach ($kindCounts as $kind => $n): ?>
                        <button type="button" class="vp-pill"
                                data-vp-threat-filter="<?= h($kind) ?>"
                                aria-pressed="false">
                            <?= h($kindLabels[$kind]) ?>
                            <span class="vp-pill-n"><?= (int)$n ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <ul class="vp-threat-list">
                <?php foreach ($rows as $i => $threat) {
                    $row($threat, $i >= $cap);
                } ?>
            </ul>

            <?php if ($total > $cap): ?>
                <button type="button" class="vp-threat-more"
                        data-vp-threat-expand>
                    <?= h(sprintf(
                        __('Show %d more'),
                        $total - $cap
                    )) ?>
                </button>
            <?php endif; ?>

            <?php if ($eventCap > 0 && $eventsRead >= $eventCap): ?>
                <p class="vp-threat-note">
                    <?= h(sprintf(
                        __(
                            'This value is in more than %d events, and'
                            . ' these are the most recent.'
                        ),
                        $eventCap
                    )) ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
