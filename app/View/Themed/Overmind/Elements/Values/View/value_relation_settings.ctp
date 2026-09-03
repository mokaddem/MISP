<?php
/**
 * The rail's second card: what MISP is configured to count.
 *
 * Every number on this tab is conditional on rules the reader cannot see
 * from the page, and a count whose rules are invisible is a count nobody
 * should trust. This card is where those rules are written down — which
 * is also why it is the one panel on the tab that still renders for a
 * value with nothing at all: what MISP counts is true whether or not
 * this value has anything to count.
 *
 * **Three kinds of rule, because the tab has three kinds of source.**
 * The co-occurrence and near-match sections answer to the correlation
 * engine's settings; the outside-this-instance section answers to the
 * feed and server caches — which sources have caching switched on,
 * which of them this reader's role may be told about, and how many
 * events are listed per source. Neither set is visible anywhere else on
 * the page. **And three sections answer to nothing at all**: the two
 * object joins and the typed references read attributes and
 * `object_references` directly, so they survive a value the correlation
 * limit suppressed. A card that lists what governs this tab has to say
 * where nothing does, or the settings above read as governing all of
 * it.
 *
 * **A settings value is not a finding.** Each rule is one row — its
 * name and where this instance stands — and the paragraph explaining
 * the mechanism sits in a fold under them. The card printed all five
 * mechanisms as bordered boxes and measured 1,278px against a rail
 * whose other two panels are 380px and 427px: 613px of that was five
 * paragraphs restating constants, on a value where four of the five
 * reported nothing unusual. Prose is spent on the exceptions instead —
 * a value past the correlation limit, a value in
 * `correlation_exclusions`, an instance with nothing cached — because
 * those are the readings that change what the reader does. The rest is
 * a number in a row, and the mechanism is one press away.
 *
 * The breakdown at the foot is the tab's arithmetic, stated rather than
 * left to be inferred. **Eight notions and five units** — values,
 * relations, remote events, references, claims — so each row names its
 * own unit and nothing is summed into one strength.
 *
 * **This is still the only panel on the tab that reads the correlation
 * engine's state at all.** Section one is an event join, section two
 * re-derives its own matches and section four reads a cache, so what the
 * engine did or refused to do for this value is reported here or
 * nowhere. prd/value-profile-live/24-relationships.md §3.
 *
 * Lazily loaded from ValuesController::viewRelationSettings.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$profile = $valueProfile;
$relations = $profile['relationships'];
$settings = $relations['settings'];
$summary = $relations['summary'];
$co = $relations['cooccurrence'];

/*
 * The value is past the limit when MISP recorded it in
 * `over_correlating_values` rather than correlating it. That is a
 * property of this value against this setting, not of the setting —
 * and it is a different question from whether the pane beside this one
 * could read the value's events, which is what `suppressed` means
 * since phase 24.
 */
$overCorrelating = isset($settings['over_correlating'])
    ? !empty($settings['over_correlating'])
    : !empty($co['suppressed']);
$suppressed = $overCorrelating;

$external = $settings['external'];
$nothingCached = empty($external['cached']['feeds'])
    && empty($external['cached']['servers']);
$restricted = !empty($external['restricted']['feeds'])
    || !empty($external['restricted']['servers']);

/*
 * The three readings worth a paragraph on the face of the card.
 *
 * A rule row states where the instance stands; a warn line states that
 * the reader is on the wrong side of it. Only these three are ever the
 * wrong side — every other rule on this card is a constant that reads
 * the same on every value — so only these three cost the space.
 */
$alerts = array();
if ($suppressed) {
    $alerts[] = array(
        'icon' => 'fas fa-triangle-exclamation',
        'title' => __('This value is past the correlation limit.'),
        'body' => __(
            'Nothing on this tab is drawn from the correlation table, so'
            . ' the sections beside this card are unaffected — but every'
            . ' other page in MISP that offers to show you what this'
            . ' value relates to will come back empty.'
        ),
    );
}
if (!empty($settings['excluded'])) {
    $alerts[] = array(
        'icon' => 'fas fa-ban',
        'title' => __('This value is excluded from correlation.'),
        'body' => sprintf(
            __('It matches an entry in %s, so nothing correlates it at'
                . ' all.'),
            '<span class="font-monospace">correlation_exclusions</span>'
        ),
    );
}
if ($nothingCached) {
    $alerts[] = array(
        'icon' => 'fas fa-cloud-arrow-down',
        'title' => __('Nothing is cached for lookup.'),
        'body' => __(
            'A feed or server needs caching switched on and a completed'
            . ' caching job before anything outside this instance can be'
            . ' looked up, so Outside this instance is empty for every'
            . ' value.'
        ),
    );
}

/*
 * One row per rule: its name, and where this instance stands. The
 * mechanism behind each is in the fold below, keyed by the same name.
 *
 * `state` is the reading a warn line above has already spelled out, so
 * the row can mark itself without repeating the sentence.
 */
$rules = array(
    array(
        'label' => __('Correlation limit'),
        'value' => number_format((int)$settings['correlation_limit']),
        'state' => $suppressed ? __('past it') : '',
    ),
    array(
        'label' => __('ssdeep threshold'),
        'value' => (string)(int)$settings['ssdeep_threshold'],
        'state' => '',
    ),
    array(
        'label' => __('Correlation exclusion'),
        'value' => $settings['excluded']
            ? __('this value')
            : __('no match'),
        'state' => $settings['excluded'] ? __('excluded') : '',
    ),
);
/*
 * The cached-source rules only when there is a cache. With nothing
 * cached both rows read zero and the warn line above already says so
 * once; two rows restating it is the density this card was losing.
 */
if (!$nothingCached) {
    /*
     * §21.6's role disclosure, in the row rather than a sentence under
     * it: `5 of 6 feeds` names the difference the reader is on the
     * wrong side of, and collapses to `5 feeds` when there is none.
     * It is a statement about the instance and their own role, not
     * about any value — vary the value and it does not move.
     */
    $feedText = $external['visible']['feeds'] < $external['cached']['feeds']
        ? sprintf(
            __n('%1$d of %2$d feed', '%1$d of %2$d feeds',
                $external['cached']['feeds']),
            $external['visible']['feeds'],
            $external['cached']['feeds']
        )
        : sprintf(
            __n('%d feed', '%d feeds', $external['cached']['feeds']),
            $external['cached']['feeds']
        );
    $serverText = $external['visible']['servers']
            < $external['cached']['servers']
        ? sprintf(
            __n('%1$d of %2$d server', '%1$d of %2$d servers',
                $external['cached']['servers']),
            $external['visible']['servers'],
            $external['cached']['servers']
        )
        : sprintf(
            __n('%d server', '%d servers', $external['cached']['servers']),
            $external['cached']['servers']
        );
    $rules[] = array(
        'label' => __('Cached for lookup'),
        'value' => sprintf(__('%1$s · %2$s'), $feedText, $serverText),
        'state' => $restricted ? __('some withheld') : '',
    );
    $rules[] = array(
        'label' => __('Listed per source'),
        'value' => sprintf(
            __n('%d event', '%d events', $external['event_cap']),
            $external['event_cap']
        ),
        'state' => '',
    );
}

/*
 * One entry per section on the tab, in the order they are read, each
 * carrying the unit it counts.
 *
 * **All of them.** It listed four until the object-mediated sections
 * were split out into panels of their own — leaving a card whose whole
 * claim is *this is the tab's arithmetic* silently missing three of
 * the numbers the tab prints. The section names are the panels' own,
 * so this card, the contents strip at the top of the tab and the panel
 * headers all say the same thing in the same words.
 *
 * The units differ — values, relations, remote events, references,
 * claims — so each row names its own rather than letting eight bars in
 * one group imply they are the same thing. Nothing here is summed.
 *
 * `capped` marks a count the section itself prints with a `≥`: a join
 * bounded by how many of this value's objects the scan read is a floor,
 * and a rail stating a bare number beside a panel qualifying it would
 * be the two disagreeing about how much they saw.
 *
 * **The `≥` is dropped on a zero.** `0.0.0.0`'s reference read is
 * capped and finds nothing, and `≥ 0` is both vacuous — *at least none*
 * — and indistinguishable from a rendering fault. A capped zero says
 * `0` and carries the bound in its tooltip instead.
 *
 * `ungoverned` is the fourth rule — that three sections have none —
 * carried by the rows it applies to rather than by a paragraph above
 * them. The footnote under the list spells the marker out once.
 */
$split = array(
    array(
        'label' => __('In the same object'),
        'unit' => __('siblings'),
        'colour' => 'var(--vp-rel-co)',
        'count' => $summary['siblings'],
        'capped' => !empty($summary['siblings_capped']),
        'ungoverned' => true,
    ),
    array(
        'label' => __('In the same events'),
        'unit' => __('values'),
        'colour' => 'var(--vp-rel-co)',
        'count' => $summary['cooccurrence'],
        'capped' => false,
        'ungoverned' => false,
    ),
    array(
        'label' => __('Dated relations'),
        'unit' => __('relations'),
        'colour' => 'var(--vp-rel-object)',
        'count' => $summary['dated'],
        'capped' => !empty($summary['dated_capped']),
        'ungoverned' => true,
    ),
    array(
        'label' => __('Near-matches'),
        'unit' => __('values'),
        'colour' => 'var(--vp-rel-near)',
        'count' => $summary['near'],
        'capped' => false,
        'ungoverned' => false,
    ),
    array(
        'label' => __('Outside this instance'),
        'unit' => __('remote events'),
        'colour' => 'var(--vp-rel-external)',
        'count' => $summary['external'],
        'capped' => false,
        'ungoverned' => false,
        /*
         * The one fact the deleted prose foot carried that no bar did.
         * A tooltip rather than a row of its own: sources and events
         * are two units, and this card's rule is that two units are
         * never one bar.
         */
        'note' => sprintf(
            __n('Held by %d source outside this instance.',
                'Held by %d sources outside this instance.',
                $summary['external_sources']),
            $summary['external_sources']
        ),
    ),
    array(
        'label' => __('Object relationships'),
        'unit' => __('references'),
        'colour' => 'var(--vp-rel-reference)',
        'count' => $summary['references'],
        'capped' => !empty($summary['references_capped']),
        'ungoverned' => true,
    ),
    array(
        'label' => __('Asserted by analysts'),
        'unit' => __('claims'),
        'colour' => 'var(--vp-rel-human)',
        'count' => $summary['asserted'],
        'capped' => false,
        'ungoverned' => false,
    ),
);
/*
 * §10.2's label neighbours, in their own unit and appended rather than
 * declared with the rest.
 *
 * **Its own row because it is its own unit**, which is this card's one
 * rule: a galaxy cluster is not a value, so it cannot be added to the
 * row above it any more than a remote event or a reference can. The
 * co-occurrence table carries both and `relationSummary` splits them
 * for exactly this reason.
 *
 * Appended so that a value whose events carry no clusters and no tags
 * renders the rows it always did, rather than one more reading zero —
 * the same rule the warninglist facet follows one panel over.
 */
if (!empty($summary['labels'])) {
    $split[] = array(
        'label' => __('Labels on those events'),
        'unit' => __('clusters and tags'),
        'colour' => 'var(--vp-rel-co)',
        'count' => $summary['labels'],
        'capped' => false,
        'ungoverned' => false,
    );
}
$maxSplit = 0;
$anyCapped = false;
foreach ($split as $part) {
    $maxSplit = max($maxSplit, (int)$part['count']);
    $anyCapped = $anyCapped || !empty($part['capped']);
}
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--bs-secondary-color);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('What is counted'),
        'panelIcon' => 'fas fa-sliders',
        'panelColor' => 'var(--bs-secondary-color)',
        /*
         * The settings are read live; the counts at the foot come from
         * the held digest, so the age is disclosed here for §16.7's
         * reason rather than left to be assumed current.
         */
        'panelSub' => h(__('The settings and caches these sections depend on'))
            . '&nbsp;·&nbsp;' . $this->element(
                'Values/View/value_read_age',
                array(
                    'readAt' => isset($relations['read_at'])
                        ? $relations['read_at'] : 0,
                    'prefix' => __('counts read %s'),
                )
            ),
    )) ?>

    <div class="p-3">

        <?php foreach ($alerts as $alert): ?>
            <div class="vp-fact-line vp-fact-line-warn mb-2">
                <i class="<?= h($alert['icon']) ?>"></i>
                <div>
                    <span class="fw-semibold">
                        <?= h($alert['title']) ?>
                    </span>
                    <div class="vp-fact-line-sub">
                        <?= $alert['body'] ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="vp-rules">
            <?php foreach ($rules as $rule): ?>
                <div class="vp-rule-row">
                    <span class="vp-rule-name">
                        <?= h($rule['label']) ?>
                    </span>
                    <span class="vp-rule-value">
                        <?= h($rule['value']) ?>
                        <?php if ($rule['state'] !== ''): ?>
                            <span class="vp-rule-state">
                                <?= h($rule['state']) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
        /*
         * The mechanisms, folded.
         *
         * A native `<details>` for the reason the external panel's
         * fold gives: the panel is injected lazily and binds no JS of
         * its own. Shut by default — a reader who wants to know what
         * `over_correlating_values` is asks once, and the answer
         * should not be levied on every load of the tab.
         *
         * The fourth rule, that three sections answer to none of
         * these, is in here with the three that do — it is the same
         * disclosure and belongs in the same place, and the marker on
         * the rows below carries the pointer.
         */
        ?>
        <details class="vp-fold vp-rules-fold mt-2">
            <summary>
                <i class="fas fa-chevron-down"></i>
                <span><?= h(__('What these rules do')) ?></span>
            </summary>
            <div class="vp-fold-body">
                <p>
                    <b><?= h(__('Correlation limit.')) ?></b>
                    <?= sprintf(
                        __('Above it MISP stores no correlations at all'
                            . ' and records the value in %s instead.'),
                        '<span class="font-monospace">'
                            . 'over_correlating_values</span>'
                    ) ?>
                </p>
                <p>
                    <b><?= h(__('ssdeep threshold.')) ?></b>
                    <?= h(__('Pairs below it are never written, and the'
                        . ' score above it is not kept either — the'
                        . ' comparison is made to test the threshold and'
                        . ' then thrown away.')) ?>
                </p>
                <p>
                    <b><?= h(__('Correlation exclusion.')) ?></b>
                    <?= sprintf(
                        __('A value matching an entry in %s is not'
                            . ' correlated at all.'),
                        '<span class="font-monospace">'
                            . 'correlation_exclusions</span>'
                    ) ?>
                </p>
                <?php if (!$nothingCached): ?>
                    <p>
                        <b><?= h(__('Cached for lookup.')) ?></b>
                        <?= h(__('A hit is set membership on an md5, so'
                            . ' it carries no date and no near-match.')) ?>
                        <?php if ($restricted): ?>
                            <?= h(__('The sources you may not read are'
                                . ' withheld from your role, on every'
                                . ' value alike.')) ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                <p>
                    <b><?= h(__('Three sections answer to none of'
                        . ' these.')) ?></b>
                    <?= sprintf(
                        __('In the same object, Dated relations and'
                            . ' Object relationships read attributes and'
                            . ' %s directly, so neither the correlation'
                            . ' limit nor the ssdeep threshold applies to'
                            . ' them. They survive a value past the'
                            . ' limit, and the only cut is what your role'
                            . ' may see.'),
                        '<span class="font-monospace">object_references'
                            . '</span>'
                    ) ?>
                </p>
            </div>
        </details>

    </div>

    <?php if ($maxSplit > 0): ?>

        <div class="px-3 pb-3">
            <div class="vp-subhead">
                <?= h(sprintf(
                    __n('%d notion, counted apart',
                        '%d notions, counted apart', count($split)),
                    count($split)
                )) ?>
            </div>

            <?php
            /*
             * Two lines per notion rather than the rail's usual
             * name-bar-count row. `.vp-reporter` gives its label 40% of
             * a card that is a quarter of the page, which is about
             * twenty characters — enough for an organisation's acronym
             * in the Sightings rail, and not enough for `Outside this
             * instance` beside the unit it counts. Here the name and
             * its figure take a line and the bar takes the next.
             */
            ?>
            <?php foreach ($split as $part): ?>
                <?php
                /*
                 * A non-zero count never draws as an empty bar. The
                 * notions do not share a unit, so the scale is a rough
                 * sense of size and nothing more — but 3 remote events
                 * beside 1,214 co-occurring values rounds to 0% and
                 * then the bar contradicts the number printed next to
                 * it. Anything present gets a minimum; only a real
                 * zero is empty.
                 */
                $width = $part['count'] > 0
                    ? max(4, round(($part['count'] / $maxSplit) * 100))
                    : 0;
                // A floor is only a floor when there is something to be
                // at least; see the `≥ 0` note in the header above.
                $showFloor = $part['capped'] && $part['count'] > 0;
                $bound = $part['capped']
                    ? ($part['count'] > 0
                        ? __('A floor: the join was bounded by how many of'
                            . ' this value\'s objects the scan reached.')
                        : __('None in the objects the scan reached, and it'
                            . ' did not reach all of them.'))
                    : '';
                $note = isset($part['note']) ? $part['note'] : '';
                ?>
                <div class="vp-split-row<?= $part['count'] > 0
                    ? '' : ' vp-split-none' ?>"
                     style="--vp-split-colour: <?= h($part['colour']) ?>;">
                    <span class="vp-split-dot"></span>
                    <span class="vp-split-label"<?php
                        if ($note !== ''): ?> title="<?= h($note) ?>"<?php
                        endif; ?>>
                        <?= h($part['label']) ?><?php
                        if (!empty($part['ungoverned'])): ?><span
                            class="vp-split-mark"
                            title="<?= h(__('Answers to none of the rules'
                                . ' above.')) ?>">&#8224;</span><?php
                        endif; ?>
                    </span>
                    <span class="vp-split-figure"<?php
                        if ($bound !== ''): ?> title="<?= h($bound) ?>"<?php
                        endif; ?>>
                        <span class="vp-split-count"><?php
                            if ($showFloor) {
                                echo "\u{2265}\u{00A0}";
                            }
                            echo h(number_format($part['count']));
                        ?></span>
                        <span class="vp-split-unit">
                            <?= h($part['unit']) ?>
                        </span>
                    </span>
                    <span class="vp-split-track">
                        <span class="vp-split-fill"
                              style="width: <?= h($width) ?>%;"></span>
                    </span>
                </div>
            <?php endforeach; ?>

            <?php
            /*
             * The two markers the rows use, spelled out once. This is
             * what the two paragraphs at the foot cost 189px to say
             * less clearly — the sentences restated every figure the
             * bars had just printed, so the card said each number
             * three times and the tab's contents strip said it a
             * fourth.
             */
            ?>
            <div class="vp-split-footnote">
                <span class="vp-split-mark">&#8224;</span>
                <?= sprintf(
                    __('Read straight from attributes and %s — none of'
                        . ' the rules above governs it.'),
                    '<span class="font-monospace">object_references'
                        . '</span>'
                ) ?>
                <?php if ($anyCapped): ?>
                    <?= sprintf(
                        __('%s marks a floor: the scan did not reach'
                            . ' every object this value sits in.'),
                        '<span class="font-monospace">&#8805;</span>'
                    ) ?>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>

</div>
