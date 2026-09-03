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
 * The breakdown at the foot is the tab's arithmetic, stated rather than
 * left to be inferred. **Seven notions and five units** — values,
 * relations, remote events, references, claims — so each row names its
 * own unit and nothing is summed into one strength. It listed four
 * until the object-mediated sections became panels of their own, which
 * left this card quietly short of three of the numbers the tab prints.
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

/*
 * One entry per section on the tab, in the order they are read, each
 * carrying the unit it counts.
 *
 * **All seven of them.** It listed four until the object-mediated
 * sections were split out into panels of their own — leaving a card
 * whose whole claim is *this is the tab's arithmetic* silently missing
 * three of the seven numbers the tab prints. The section names are the
 * panels' own, so this card, the contents strip at the top of the tab
 * and the panel headers all say the same thing in the same words.
 *
 * The units differ — values, relations, remote events, references,
 * claims — so each row names its own rather than letting seven bars in
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
 * `0` and carries the bound in its tooltip instead; the row is already
 * marked as empty, and the rule above it says which sections the scan
 * bounds.
 */
$split = array(
    array(
        'label' => __('In the same object'),
        'unit' => __('siblings'),
        'colour' => 'var(--vp-rel-co)',
        'count' => $summary['siblings'],
        'capped' => !empty($summary['siblings_capped']),
    ),
    array(
        'label' => __('In the same events'),
        'unit' => __('values'),
        'colour' => 'var(--vp-rel-co)',
        'count' => $summary['cooccurrence'],
        'capped' => false,
    ),
    array(
        'label' => __('Dated relations'),
        'unit' => __('relations'),
        'colour' => 'var(--vp-rel-object)',
        'count' => $summary['dated'],
        'capped' => !empty($summary['dated_capped']),
    ),
    array(
        'label' => __('Near-matches'),
        'unit' => __('values'),
        'colour' => 'var(--vp-rel-near)',
        'count' => $summary['near'],
        'capped' => false,
    ),
    array(
        'label' => __('Outside this instance'),
        'unit' => __('remote events'),
        'colour' => 'var(--vp-rel-external)',
        'count' => $summary['external'],
        'capped' => false,
    ),
    array(
        'label' => __('Object relationships'),
        'unit' => __('references'),
        'colour' => 'var(--vp-rel-reference)',
        'count' => $summary['references'],
        'capped' => !empty($summary['references_capped']),
    ),
    array(
        'label' => __('Asserted by analysts'),
        'unit' => __('claims'),
        'colour' => 'var(--vp-rel-human)',
        'count' => $summary['asserted'],
        'capped' => false,
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
 * renders the five rows it always did, rather than a sixth reading
 * zero — the same rule the warninglist facet follows one panel over.
 */
if (!empty($summary['labels'])) {
    $split[] = array(
        'label' => __('Labels on those events'),
        'unit' => __('clusters and tags'),
        'colour' => 'var(--vp-rel-co)',
        'count' => $summary['labels'],
        'capped' => false,
    );
}
$maxSplit = 0;
foreach ($split as $part) {
    $maxSplit = max($maxSplit, (int)$part['count']);
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

    <div class="p-3 d-flex flex-column gap-2">

        <div class="vp-fact-line<?= $suppressed
            ? ' vp-fact-line-warn' : '' ?>">
            <i class="fas fa-triangle-exclamation"></i>
            <div>
                <span class="fw-semibold">
                    <?= h(sprintf(
                        __('Correlation limit %d.'),
                        $settings['correlation_limit']
                    )) ?>
                </span>
                <div class="vp-fact-line-sub">
                    <?= sprintf(
                        __(
                            'Above it MISP stores no correlations at all'
                            . ' and records the value in %s instead.'
                        ),
                        '<span class="font-monospace">'
                            . 'over_correlating_values</span>'
                    ) ?>
                    <?php if ($suppressed): ?>
                        <div class="mt-1 fw-semibold">
                            <?= h(__('This value is past it.')) ?>
                        </div>
                        <div class="mt-1">
                            <?= h(__(
                                'Nothing on this tab is drawn from the'
                                . ' correlation table, so the sections'
                                . ' beside this card are unaffected —'
                                . ' but every other page in MISP that'
                                . ' offers to show you what this value'
                                . ' relates to will come back empty, and'
                                . ' that is why the setting is worth'
                                . ' stating here.'
                            )) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="vp-fact-line">
            <i class="fas fa-percent"></i>
            <div>
                <span class="fw-semibold">
                    <?= h(sprintf(
                        __('ssdeep threshold %d.'),
                        $settings['ssdeep_threshold']
                    )) ?>
                </span>
                <div class="vp-fact-line-sub">
                    <?= __('Pairs below it are never written, and the'
                        . ' score above it is not kept either — the'
                        . ' comparison is made to test the threshold and'
                        . ' then thrown away.') ?>
                </div>
            </div>
        </div>

        <div class="vp-fact-line<?= empty($external['cached']['feeds'])
            && empty($external['cached']['servers'])
            ? ' vp-fact-line-warn' : '' ?>">
            <i class="fas fa-cloud-arrow-down"></i>
            <div>
                <span class="fw-semibold">
                    <?php if (empty($external['cached']['feeds'])
                        && empty($external['cached']['servers'])): ?>
                        <?= h(__('Nothing is cached for lookup.')) ?>
                    <?php else: ?>
                        <?= h(sprintf(
                            __('%1$s and %2$s cached.'),
                            __n('%d feed', '%d feeds',
                                $external['cached']['feeds'],
                                $external['cached']['feeds']),
                            __n('%d sync server', '%d sync servers',
                                $external['cached']['servers'],
                                $external['cached']['servers'])
                        )) ?>
                    <?php endif; ?>
                </span>
                <div class="vp-fact-line-sub">
                    <?php if (empty($external['cached']['feeds'])
                        && empty($external['cached']['servers'])): ?>
                        <?= __('A feed or server needs caching switched on'
                            . ' and a completed caching job before anything'
                            . ' outside this instance can be looked up, so'
                            . ' the fourth section is empty for every'
                            . ' value.') ?>
                    <?php else: ?>
                        <?= h(sprintf(
                            __('You may read %1$s and %2$s of them.'),
                            __n('%d feed', '%d feeds',
                                $external['visible']['feeds'],
                                $external['visible']['feeds']),
                            __n('%d server', '%d servers',
                                $external['visible']['servers'],
                                $external['visible']['servers'])
                        )) ?>
                        <?php if ($external['restricted']['feeds']
                            || $external['restricted']['servers']): ?>
                            <div class="mt-1">
                                <?= h(__('The rest are withheld from your'
                                    . ' role, on every value alike.')) ?>
                            </div>
                        <?php endif; ?>
                        <div class="mt-1">
                            <?= h(sprintf(__(
                                'A hit is set membership on an md5, so it'
                                . ' carries no date and no near-match, and'
                                . ' at most %d events are listed per'
                                . ' source.'
                            ), $external['event_cap'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="vp-fact-line">
            <i class="fas fa-ban"></i>
            <div>
                <span class="fw-semibold">
                    <?= h($settings['excluded']
                        ? __('Excluded from correlation.')
                        : __('No correlation exclusion.')) ?>
                </span>
                <div class="vp-fact-line-sub">
                    <?= sprintf(
                        $settings['excluded']
                            ? __('This value matches an entry in %s, so'
                                . ' nothing correlates it at all.')
                            : __('This value matches no entry in %s.'),
                        '<span class="font-monospace">'
                            . 'correlation_exclusions</span>'
                    ) ?>
                </div>
            </div>
        </div>

        <?php
        /*
         * The fourth rule is that three sections have none.
         *
         * Every setting above governs some part of this tab, and a
         * reader who takes the card at its word would read seven bars
         * at the foot as seven counts those settings shaped. Three of
         * them are not: the object join reads attributes and the
         * reference section reads `object_references`, so neither the
         * correlation limit nor the ssdeep threshold touches them.
         * Saying so is the same disclosure as the three rules above —
         * what bounds a number, stated where the number is.
         */
        ?>
        <div class="vp-fact-line">
            <i class="fas fa-cube"></i>
            <div>
                <span class="fw-semibold">
                    <?= h(__('Three sections answer to no setting.')) ?>
                </span>
                <div class="vp-fact-line-sub">
                    <?= sprintf(
                        __(
                            'In the same object, Dated relations and'
                            . ' Object relationships read attributes and'
                            . ' %s directly, so neither the correlation'
                            . ' limit nor the ssdeep threshold applies to'
                            . ' them. They survive a value past the limit,'
                            . ' and the only cut is what your role may'
                            . ' see — the two object joins additionally'
                            . ' bounded by how many of this value\'s'
                            . ' objects the scan reached, which is what a'
                            . ' %s on their counts below means.'
                        ),
                        '<span class="font-monospace">object_references'
                            . '</span>',
                        '<span class="font-monospace">&#8805;</span>'
                    ) ?>
                </div>
            </div>
        </div>

    </div>

    <?php if ($maxSplit > 0): ?>

        <div class="px-3 pb-3">
            <div class="vp-subhead">
                <?= h(__('Seven notions, counted apart')) ?>
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
                 * seven notions do not share a unit, so the scale is
                 * a rough sense of size and nothing more — but 3
                 * remote events beside 1,214 co-occurring values
                 * rounds to 0% and then the bar contradicts the
                 * number printed next to it. Anything present gets
                 * a minimum; only a real zero is empty.
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
                ?>
                <div class="vp-split-row<?= $part['count'] > 0
                    ? '' : ' vp-split-none' ?>"
                     style="--vp-split-colour: <?= h($part['colour']) ?>;">
                    <span class="vp-split-dot"></span>
                    <span class="vp-split-label">
                        <?= h($part['label']) ?>
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

            <div class="small text-muted mt-2">
                <?= h(sprintf(
                    __('%1$s %2$s'),
                    __n(
                        '%s value shares an event with this one,',
                        '%s values share an event with this one,',
                        $summary['cooccurrence'],
                        number_format($summary['cooccurrence'])
                    ),
                    __n(
                        'and %s is near it without being it.',
                        'and %s are near it without being it.',
                        $summary['near'],
                        number_format($summary['near'])
                    )
                )) ?>
                <?php
                /*
                 * The three object-mediated notions, in one sentence
                 * and in their own units. A sibling is a value and
                 * could have been added to the first sentence's count;
                 * it is not, for the reason the whole card exists —
                 * sharing an object and sharing an event are different
                 * claims and are never summed into one strength.
                 *
                 * The sibling clause carries the floor the bar above it
                 * carries. It said `795 sit in an object beside it` on
                 * a value whose own row read `≥ 795`, which is the
                 * card contradicting itself inside one panel.
                 */
                $siblingClause = __n(
                    '%s value sits in an object beside it',
                    '%s values sit in an object beside it',
                    $summary['siblings'],
                    number_format($summary['siblings'])
                );
                if (!empty($summary['siblings_capped'])
                    && $summary['siblings'] > 0
                ) {
                    // Capitalised: this clause opens the sentence.
                    $siblingClause = sprintf(
                        __('At least %s'),
                        $siblingClause
                    );
                }
                ?>
                <?= h(sprintf(
                    __('%1$s, %2$s and %3$s.'),
                    $siblingClause,
                    __n('%s of those joins records a span',
                        '%s of them record a span',
                        $summary['dated'],
                        number_format($summary['dated'])),
                    __n('%s typed reference names it',
                        '%s typed references name it',
                        $summary['references'],
                        number_format($summary['references']))
                )) ?>
                <?= h(sprintf(
                    __('%1$s hold it, in %2$s.'),
                    __n('%d source outside this instance',
                        '%d sources outside this instance',
                        $summary['external_sources'],
                        $summary['external_sources']),
                    __n('%d remote event', '%d remote events',
                        $summary['external'], $summary['external'])
                )) ?>
                <?php
                /*
                 * A zero needs its own sentence rather than `__n`'s
                 * plural: "The 0 claims are counted apart" is what the
                 * plural form produces, and it is not English.
                 */
                ?>
                <?php if ($summary['asserted'] === 0): ?>
                    <?= h(__('No analyst has claimed anything about it —'
                        . ' nothing here is summed into one strength, and'
                        . ' none of the seven is a correlation row.')) ?>
                <?php else: ?>
                    <?= h(__n(
                        'The single claim is counted apart from all of'
                            . ' them — nothing here is summed into one'
                            . ' strength, and none of the seven is a'
                            . ' correlation row.',
                        'The %d claims are counted apart from all of'
                            . ' them — nothing here is summed into one'
                            . ' strength, and none of the seven is a'
                            . ' correlation row.',
                        $summary['asserted'],
                        $summary['asserted']
                    )) ?>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>

</div>
