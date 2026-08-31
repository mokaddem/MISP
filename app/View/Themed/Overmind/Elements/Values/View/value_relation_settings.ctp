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
 * **Two kinds of rule, because the tab has two kinds of source.**
 * Sections one and two answer to the correlation engine's settings;
 * section four answers to the feed and server caches — which sources
 * have caching switched on, which of them this reader's role may be told
 * about, and how many events are listed per source. Neither set is
 * visible anywhere else on the page.
 *
 * The breakdown at the foot is the tab's arithmetic, stated rather than
 * left to be inferred. **Four notions and four units** — values, values,
 * remote events, claims — so each bar names its unit and nothing is
 * summed into one strength.
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
 * carrying the unit it counts. The units differ — values, values, events,
 * claims — so the label says which rather than letting four bars in one
 * group imply they are the same thing. Nothing here is summed.
 */
$split = array(
    array(
        'label' => __('Co-occurrence (values)'),
        'colour' => 'var(--vp-rel-co)',
        'count' => $summary['cooccurrence'],
    ),
    array(
        'label' => __('Near-match (values)'),
        'colour' => 'var(--vp-rel-near)',
        'count' => $summary['near'],
    ),
    array(
        'label' => __('Outside (events)'),
        'colour' => 'var(--vp-rel-external)',
        'count' => $summary['external'],
    ),
    array(
        'label' => __('Asserted (claims)'),
        'colour' => 'var(--vp-rel-human)',
        'count' => $summary['asserted'],
    ),
);
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
        'panelSub' => h(__('The settings and caches these sections depend on')),
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

    </div>

    <?php if ($maxSplit > 0): ?>

        <div class="px-3 pb-3">
            <div class="vp-subhead">
                <?= h(__('Four notions, counted apart')) ?>
            </div>

            <?php foreach ($split as $part): ?>
                <div class="vp-reporter">
                    <span class="vp-reporter-name">
                        <?= h($part['label']) ?>
                    </span>
                    <?php
                    /*
                     * A non-zero count never draws as an empty bar. The
                     * four notions do not share a unit, so the scale is
                     * a rough sense of size and nothing more — but 3
                     * remote events beside 1,214 co-occurring values
                     * rounds to 0% and then the bar contradicts the
                     * number printed next to it. Anything present gets
                     * a floor; only a real zero is empty.
                     */
                    $width = $part['count'] > 0
                        ? max(4, round(($part['count'] / $maxSplit) * 100))
                        : 0;
                    ?>
                    <span class="vp-reporter-track">
                        <span class="vp-reporter-fill"
                              style="width: <?= h($width) ?>%;
                                     background: <?= h($part['colour'])
                              ?>;"></span>
                    </span>
                    <span class="vp-reporter-count">
                        <?= h(number_format($part['count'])) ?>
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
                <?= h(sprintf(
                    __('%1$s hold it, in %2$s.'),
                    __n('%d source outside this instance',
                        '%d sources outside this instance',
                        $summary['external_sources'],
                        $summary['external_sources']),
                    __n('%d remote event', '%d remote events',
                        $summary['external'], $summary['external'])
                )) ?>
                <?= h(__n(
                    'The single claim is counted apart from all of them'
                        . ' — nothing here is summed into one strength,'
                        . ' and none of the four is a correlation row.',
                    'The %d claims are counted apart from all of them'
                        . ' — nothing here is summed into one strength,'
                        . ' and none of the four is a correlation row.',
                    $summary['asserted'],
                    $summary['asserted']
                )) ?>
            </div>
        </div>

    <?php endif; ?>

</div>
