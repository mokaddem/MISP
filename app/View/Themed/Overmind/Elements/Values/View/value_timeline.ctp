<?php
/**
 * The Timeline tab — candidate `T-final`.
 *
 * One window, three depths. The spine says *when* the value was busy,
 * the source lanes say *which sources say so*, and the chronology says
 * *what exactly happened* — and all three are the same selection, which
 * is the whole design. Each of the four candidates the deck opened with
 * answered one of those three questions and lost the other two.
 *
 * The tab's placeholder note promised a merged chronology of seven
 * sources. Of those seven, one is fully dated and addressable by value,
 * one needs an assembled union, two are truncated to first-and-last,
 * one is a nullable per-occurrence span, and two carry no timestamp in
 * MISP on any instance. So the design problem was never how to draw a
 * chronology — it was how to draw one that admits its own holes without
 * becoming unreadable. Every hatched lane and every precision chip
 * below is that admission, put in the reading path rather than in a
 * margin a reader can skip.
 *
 * Three cards in one panel, and one endpoint behind them, because the
 * brush is a single control driving two regions that must already exist
 * when it fires (`06-timeline.md` §4).
 *
 * The spine is Chart.js, per `00-shared.md` §7. The lanes are inline
 * SVG and deliberately not: a lane has no axis of its own, no legend
 * and no scale, so seven Chart.js instances would be seven canvases for
 * a shape that needs marks and a `<title>`.
 *
 * Lazily loaded from ValuesController::viewTimeline.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$timeline = isset($valueProfile['timeline'])
    ? $valueProfile['timeline']
    : null;

/*
 * ------------------------------------------------------------------
 * The vocabularies
 * ------------------------------------------------------------------
 * One table per axis of meaning, so a source's colour, its glyph and
 * its label are decided once and read by all three cards. §11's rule is
 * that a lane mark and its stack segment must be the same colour, and
 * the only way to keep that true is for both to read the same token.
 */
$sourceMeta = array(
    'sighting' => array(
        'label' => __('Sighting'),
        'plural' => __('sightings'),
        'icon' => 'fas fa-eye',
        'token' => 'var(--vp-tl-sighting)',
    ),
    'false_positive' => array(
        'label' => __('False positive'),
        'plural' => __('false positives'),
        'icon' => 'fas fa-thumbs-down',
        'token' => 'var(--vp-tl-false_positive)',
    ),
    'expiration' => array(
        'label' => __('Expiration'),
        'plural' => __('expirations'),
        'icon' => 'fas fa-hourglass-end',
        'token' => 'var(--vp-tl-expiration)',
    ),
    'publication' => array(
        'label' => __('Published'),
        'plural' => __('publications'),
        'icon' => 'fas fa-paper-plane',
        'token' => 'var(--vp-tl-publication)',
    ),
    'note' => array(
        'label' => __('Note'),
        'plural' => __('notes'),
        'icon' => 'fas fa-note-sticky',
        'token' => 'var(--vp-tl-note)',
    ),
    'opinion' => array(
        'label' => __('Opinion'),
        'plural' => __('opinions'),
        'icon' => 'fas fa-comment-dots',
        'token' => 'var(--vp-tl-opinion)',
    ),
    'edit' => array(
        'label' => __('Edit'),
        'plural' => __('edits'),
        'icon' => 'fas fa-pencil',
        'token' => 'var(--vp-tl-edit)',
    ),
    'seen' => array(
        'label' => __('First seen'),
        'plural' => __('first-seen dates'),
        'icon' => 'far fa-clock',
        'token' => 'var(--vp-tl-seen)',
    ),
);

/*
 * How well a row is dated, and which bucket the chronology's tally
 * counts it in. Two buckets and not three: a `no date` bucket in a list
 * of dated entries would be a number describing nothing, and the facts
 * it would count are in the lanes and on the off-axis strip instead
 * (§8.3).
 */
$precisionMeta = array(
    'exact' => array(
        'label' => __('exact'),
        'bucket' => 'exact',
        'class' => 'vp-prec-exact',
    ),
    'first_last' => array(
        'label' => __('first & last only'),
        'bucket' => 'partial',
        'class' => 'vp-prec-part',
    ),
    'latest' => array(
        'label' => __('latest only'),
        'bucket' => 'partial',
        'class' => 'vp-prec-part',
    ),
);

$utc = new DateTimeZone('UTC');
$entries = $timeline === null ? array() : $timeline['entries'];
$undated = $timeline === null ? array() : $timeline['undated'];
$window = $timeline === null ? null : $timeline['window'];
$auditRecorded = $timeline !== null && $timeline['audit_recorded'];
$aclNote = $timeline === null ? null : $timeline['acl_note'];

$windowFrom = $window === null ? null : $window['from'] . ' 00:00:00';
$windowTo = $window === null ? null : $window['to'] . ' 23:59:59';

/*
 * ------------------------------------------------------------------
 * Everything below is derived from `entries` and `undated`
 * ------------------------------------------------------------------
 * The spine's monthly bars, the lanes' in-window counts and the
 * chronology's list are three aggregates over one array (§7). Nothing
 * on this tab is written into the fixture as a number, which is what
 * makes it impossible for the panel to state two counts that disagree.
 */
$windowed = array();
foreach ($entries as $entry) {
    if ($entry['at'] >= $windowFrom && $entry['at'] <= $windowTo) {
        $windowed[] = $entry;
    }
}

/*
 * ------------------------------------------------------------------
 * The spine's bins
 * ------------------------------------------------------------------
 * Twelve months ending at the window's own end, because the fixture's
 * range is a year. A value first seen last week needs daily bins, and
 * choosing the bin width from the range is live-data work the fixture
 * deliberately pins (§12).
 *
 * The last bin stops at the window's end rather than running to the end
 * of its month: there is no data after today, and a bar drawn over days
 * that have not happened invites the reader to read a dip in it.
 */
$bins = array();
$before = 0;
$earliest = null;
if ($window !== null) {
    $end = new DateTimeImmutable($window['to'] . ' 00:00:00', $utc);
    $first = $end->modify('first day of this month')
        ->modify('-11 months');
    for ($i = 0; $i < 12; $i++) {
        $start = $first->modify('+' . $i . ' months');
        $stop = $start->modify('last day of this month');
        if ($stop->format('Y-m-d') > $window['to']) {
            $stop = $end;
        }
        $bins[] = array(
            'key' => $start->format('Y-m'),
            'label' => $start->format('M'),
            'title' => $start->format('F Y'),
            'from' => $start->format('Y-m-d'),
            'to' => $stop->format('Y-m-d'),
            'counts' => array(),
            'total' => 0,
        );
    }

    /*
     * Anything older than the first bin is counted rather than
     * silently dropped. A chart that quietly discards entries is the
     * exact failure this tab exists to avoid, so if the spine cannot
     * hold the value's whole history it says so under itself.
     */
    $floor = $first->format('Y-m-d') . ' 00:00:00';
    $index = array();
    foreach ($bins as $i => $bin) {
        $index[$bin['key']] = $i;
    }
    foreach ($entries as $entry) {
        if ($entry['at'] < $floor) {
            $before++;
            if ($earliest === null || $entry['at'] < $earliest) {
                $earliest = $entry['at'];
            }
            continue;
        }
        $month = substr($entry['at'], 0, 7);
        if (!isset($index[$month])) {
            continue;
        }
        $at = $index[$month];
        $source = $entry['source'];
        if (!isset($bins[$at]['counts'][$source])) {
            $bins[$at]['counts'][$source] = 0;
        }
        $bins[$at]['counts'][$source]++;
        $bins[$at]['total']++;
    }
}

/*
 * Which sources the value actually has, in the vocabulary's order. A
 * stack segment for a source nobody ever filed is a legend entry
 * teaching the reader a colour they will never meet again.
 */
$present = array();
foreach ($sourceMeta as $key => $meta) {
    foreach ($entries as $entry) {
        if ($entry['source'] === $key) {
            $present[] = $key;
            break;
        }
    }
}

/*
 * The longest empty run *between* two months that do carry entries.
 * Named rather than left to be read as a quiet period: this is T3's own
 * objection answered rather than ignored — a density chart cannot tell
 * a quiet month from an unrecorded one, so the strip says which it is
 * and the lanes underneath say which sources could have recorded it.
 *
 * A leading run is not a gap; it is the time before the value existed.
 * A trailing one is not a gap either; it is the present.
 */
$gap = null;
$run = null;
$seen = false;
foreach ($bins as $bin) {
    if ($bin['total'] > 0) {
        if ($run !== null
            && ($gap === null || $run['len'] > $gap['len'])) {
            $gap = $run;
        }
        $run = null;
        $seen = true;
        continue;
    }
    if (!$seen) {
        continue;
    }
    if ($run === null) {
        $run = array('from' => $bin, 'to' => $bin, 'len' => 1);
    } else {
        $run['to'] = $bin;
        $run['len']++;
    }
}

/*
 * ------------------------------------------------------------------
 * Geometry
 * ------------------------------------------------------------------
 * The lane axis is one viewBox stretched to whatever width the panel
 * has, so every mark is placed in its coordinates and every word about
 * a mark is placed in HTML over it. `preserveAspectRatio="none"`
 * stretches glyphs with the box and turns an explanation into a smear,
 * which is what `.vp-lane-fill` and `.vp-lane-tag` exist to avoid
 * (§8.2).
 */
$LANE_W = 740;
$LANE_H = 38;
$MARK_W = 5;

$t0 = $window === null
    ? 0
    : (new DateTimeImmutable($windowFrom, $utc))->getTimestamp();
$t1 = $window === null
    ? 1
    : (new DateTimeImmutable($windowTo, $utc))->getTimestamp();
$span = max(1, $t1 - $t0);

/**
 * Where a moment sits on the lane axis, in viewBox units.
 *
 * @param string $at `Y-m-d H:i:s`
 * @return float
 */
$xFor = function ($at) use ($t0, $span, $utc, $LANE_W, $MARK_W) {
    $t = (new DateTimeImmutable($at, $utc))->getTimestamp();
    $fraction = ($t - $t0) / $span;
    $fraction = max(0, min(1, $fraction));
    return round($fraction * ($LANE_W - $MARK_W), 1);
};

/*
 * Five ticks across the window. Enough for a reader to place a mark
 * without turning the lane header into a ruler.
 */
$ticks = array();
if ($window !== null) {
    for ($i = 0; $i < 5; $i++) {
        $at = $t0 + (int)round(($span * $i) / 4);
        $tick = (new DateTimeImmutable('@' . $at))->setTimezone($utc);
        $ticks[] = $i === 0
            ? $tick->format('j M')
            : $tick->format('j');
    }
}

/*
 * ------------------------------------------------------------------
 * The lanes
 * ------------------------------------------------------------------
 * Seven, one per source the tab's own note promises, whether or not
 * MISP records it. Three will never carry a mark and keep a full-size
 * hatched lane anyway — a reader who scans the lanes must not be able
 * to miss what is missing, which is the reason this design was chosen
 * over the cheaper one that put the same facts in a rail.
 */
$spanCount = 0;
$spanTotal = 0;
foreach ($valueProfile['occurrences'] as $occurrence) {
    $spanTotal++;
    if (!empty($occurrence['Attribute']['first_seen'])) {
        $spanCount++;
    }
}

$undatedBy = array();
foreach ($undated as $row) {
    $undatedBy[$row['kind']] = $row;
}

$lanes = array(
    array(
        'key' => 'sightings',
        'label' => __('Sightings'),
        'sub' => __('date_sighting, exact'),
        'sources' => array('sighting', 'false_positive', 'expiration'),
        'draw' => 'marks',
    ),
    array(
        'key' => 'publications',
        'label' => __('Publications'),
        'sub' => __('first & last only'),
        'sources' => array('publication'),
        'draw' => 'marks',
    ),
    array(
        'key' => 'analyst',
        'label' => __('Notes / Opinions'),
        'sub' => __('created, exact'),
        'sources' => array('note', 'opinion'),
        'draw' => 'marks',
    ),
    array(
        'key' => 'edits',
        'label' => __('Edits'),
        'sub' => __('latest per occurrence'),
        'sources' => array('edit'),
        'draw' => 'marks',
        /*
         * A lane state, not a whole-tab state. One point per occurrence
         * is still a point, so the lane keeps its mark and the hatch
         * behind it names the setting that would fill in the rest.
         */
        'hatch' => $auditRecorded ? null : __(
            'Nothing before each occurrence\'s latest edit is recorded'
            . ' — MISP.log_new_audit is off.'
        ),
    ),
    array(
        'key' => 'seen',
        'label' => __('Seen spans'),
        'sub' => sprintf(
            __('%1$s of %2$s occurrences carry one'),
            $spanCount,
            $spanTotal
        ),
        'sources' => array('seen'),
        'draw' => 'spans',
    ),
    array(
        'key' => 'tags',
        'label' => __('Tags'),
        'sub' => __('no column exists, any instance'),
        'draw' => 'undated',
        'row' => isset($undatedBy[__('Tags')])
            ? $undatedBy[__('Tags')]
            : null,
        'absent' => __(
            'Nothing has tagged this value — and if something had, MISP'
            . ' would store no date for it.'
        ),
    ),
    array(
        'key' => 'feeds',
        'label' => __('Feed appearances'),
        'sub' => __('one date per feed, moves on refresh'),
        'draw' => 'undated',
        'row' => isset($undatedBy[__('Feed appearances')])
            ? $undatedBy[__('Feed appearances')]
            : null,
        'absent' => __(
            'No feed on this instance carries this value — and if one'
            . ' did, its only date would be the last fetch.'
        ),
    ),
);

/*
 * ------------------------------------------------------------------
 * The chronology
 * ------------------------------------------------------------------
 * Newest first, grouped by day. Runs of one source inside one day
 * collapse to a summary row: forty-seven sightings must not be
 * forty-seven rows. A day is wholly inside the window or wholly outside
 * it, so a run's shape never changes when the brush moves — which is
 * what lets the grouping be done once, here, rather than again in
 * JavaScript every time the window changes.
 */
$RUN_MIN = 3;
$SHOWN_MAX = 14;

$byDay = array();
foreach (array_reverse($entries) as $entry) {
    $day = substr($entry['at'], 0, 10);
    if (!isset($byDay[$day])) {
        $byDay[$day] = array();
    }
    $byDay[$day][] = $entry;
}

$days = array();
foreach ($byDay as $day => $rows) {
    $units = array();
    $current = array();
    foreach ($rows as $entry) {
        if (!empty($current)
            && $current[0]['source'] !== $entry['source']) {
            $units[] = $current;
            $current = array();
        }
        $current[] = $entry;
    }
    if (!empty($current)) {
        $units[] = $current;
    }
    $days[] = array(
        'day' => $day,
        'label' => (new DateTimeImmutable($day, $utc))
            ->format('l, j F Y'),
        'units' => $units,
    );
}

/*
 * The precision tally, over the window and only over the window. It
 * sums to the number of entries in the list beneath it, which is the
 * property that makes it worth printing at all.
 */
$tally = array('exact' => 0, 'partial' => 0);
foreach ($windowed as $entry) {
    $meta = $precisionMeta[$entry['precision']];
    $tally[$meta['bucket']]++;
}

/*
 * What the spine needs, and nothing else. The lanes and the chronology
 * re-scope from the rows already in the DOM rather than from a second
 * copy of them here — one array, three readings, and only one of the
 * three needs a canvas.
 */
$payload = null;
if ($window !== null) {
    $datasets = array();
    foreach ($present as $source) {
        $data = array();
        foreach ($bins as $bin) {
            $data[] = isset($bin['counts'][$source])
                ? $bin['counts'][$source]
                : 0;
        }
        $datasets[] = array(
            'source' => $source,
            'label' => $sourceMeta[$source]['label'],
            'colour' => $sourceMeta[$source]['token'],
            'data' => $data,
        );
    }
    $payload = array(
        'bins' => array_map(function ($bin) {
            return array(
                'key' => $bin['key'],
                'label' => $bin['label'],
                'title' => $bin['title'],
                'from' => $bin['from'],
                'to' => $bin['to'],
            );
        }, $bins),
        'datasets' => $datasets,
        'window' => array(
            'from' => $window['from'],
            'to' => $window['to'],
        ),
        'lane' => array(
            'width' => $LANE_W,
            'height' => $LANE_H,
            'mark' => $MARK_W,
        ),
        'labels' => array(
            'entries' => __('entries'),
            'axis' => __('Dated entries per month, stacked by source'),
        ),
    );
}

$subtitle = $timeline === null
    ? h(__('Nothing to place on an axis'))
    : h(sprintf(
        __('%1$s dated · %2$s named but undatable'),
        count($entries),
        array_sum(array_column($undated, 'count'))
    ));
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--bs-info);"
     <?= $timeline === null ? '' : 'data-vp-tl' ?>>

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Timeline'),
        'panelIcon' => 'fas fa-clock',
        'panelColor' => 'var(--bs-info)',
        'panelSub' => $subtitle,
    )) ?>

    <?php if ($timeline === null): ?>

        <?php
        /*
         * Not an empty timeline — no timeline. Twelve empty months and
         * seven lanes over a value MISP has never held would be
         * inventing a period of silence that never happened.
         */
        ?>
        <div class="p-3">
            <div class="vp-empty">
                <i class="fas fa-clock"></i>
                <span><?= __('Nothing has happened to this value,'
                    . ' because MISP has never held it.') ?></span>
            </div>
        </div>

    <?php else: ?>

        <script type="application/json" data-vp-tl-data><?=
            json_encode($payload) ?></script>

        <div class="p-3 vp-tl">

            <?php
            /*
             * ------------------------------------------------------
             * 1. The spine
             * ------------------------------------------------------
             * A control, not a second chart. Every count below it is
             * scoped to what is brushed here, which is what stops the
             * spine and the lanes from being two encodings of the same
             * thing sitting next to each other.
             */
            ?>
            <section class="vp-tl-card">
                <div class="vp-tl-head">
                    <div class="vp-min-w-0">
                        <div class="fw-semibold">
                            <?= __('Activity on this value') ?>
                        </div>
                        <div class="vp-tl-why">
                            <?= h(sprintf(
                                __(
                                    '%s by month, stacked by source.'
                                    . ' Drag to set the window — the'
                                    . ' lanes and the chronology below'
                                    . ' both follow it.'
                                ),
                                __n(
                                    '%s dated entry',
                                    '%s dated entries',
                                    count($entries),
                                    count($entries)
                                )
                            )) ?>
                        </div>
                    </div>
                    <div class="vp-tl-legend">
                        <?php foreach ($present as $source): ?>
                            <span class="vp-tl-key">
                                <span class="vp-tl-swatch"
                                      style="--vp-tl-hue: <?=
                                          h($sourceMeta[$source]['token'])
                                      ?>;"></span>
                                <?= h($sourceMeta[$source]['label']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="vp-tl-spine" data-vp-tl-spine>
                    <canvas id="vp-tl-spine" role="img"
                            aria-label="<?= h(__(
                                'Dated entries per month over the last'
                                . ' twelve months, stacked by source'
                            )) ?>"></canvas>
                    <?php
                    /*
                     * Hidden until the chart it controls exists. A
                     * brush framing an empty canvas offers a gesture
                     * that cannot do anything, which is worse than
                     * offering none.
                     */
                    ?>
                    <div class="vp-tl-brush" data-vp-tl-brush hidden>
                        <div class="vp-tl-mask" data-vp-tl-mask-left>
                        </div>
                        <div class="vp-tl-window" data-vp-tl-handle>
                        </div>
                        <div class="vp-tl-mask" data-vp-tl-mask-right>
                        </div>
                    </div>
                </div>

                <noscript>
                    <?php
                    /*
                     * The counts below are already right — they were
                     * computed for the default window before this page
                     * was sent. It is only the chart and the brush that
                     * need a script, so that is all this says.
                     */
                    ?>
                    <div class="vp-tl-why">
                        <?= h(sprintf(
                            __(
                                'The chart and its window control need'
                                . ' JavaScript. Everything below is the'
                                . ' window %1$s to %2$s, and its counts'
                                . ' are correct without it.'
                            ),
                            $window['from'],
                            $window['to']
                        )) ?>
                    </div>
                </noscript>

                <?php if ($before > 0): ?>
                    <?php
                    /*
                     * The spine holds twelve months and this value is
                     * older. Said out loud rather than left as a chart
                     * that silently begins after the beginning.
                     */
                    ?>
                    <div class="vp-tl-why pt-1">
                        <?= h(sprintf(
                            __(
                                '%1$s older than this chart, the'
                                . ' earliest on %2$s. They are in the'
                                . ' chronology and not on the axis.'
                            ),
                            __n(
                                '%s entry is',
                                '%s entries are',
                                $before,
                                $before
                            ),
                            substr($earliest, 0, 10)
                        )) ?>
                    </div>
                <?php endif; ?>

                <?php
                /*
                 * The off-axis strip. Not conditional on the brush, and
                 * that is the point: nothing on it is in any window,
                 * because nothing on it has a date to be in one with.
                 *
                 * These same facts also keep a full-size lane below, so
                 * neither a reader who scans the chart nor one who
                 * scans the lanes can miss them (§8.7).
                 */
                ?>
                <?php if (!empty($undated) || $gap !== null): ?>
                    <div class="vp-tl-offaxis">
                        <?php if (!empty($undated)): ?>
                            <i class="fas fa-circle-info"></i>
                            <span class="fw-semibold">
                                <?= __('Never on this axis:') ?>
                            </span>
                            <?php foreach ($undated as $row): ?>
                                <span class="vp-tl-undated-chip"
                                      title="<?= h($row['reason']) ?>">
                                    <?= h($row['kind']) ?>
                                    <strong><?= h($row['count']) ?></strong>
                                    <?php if ($row['as_of'] !== null): ?>
                                        — <?= h(sprintf(
                                            __('as of %s'),
                                            substr($row['as_of'], 0, 16)
                                        )) ?>
                                    <?php else: ?>
                                        — <?= __('no date column') ?>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if ($gap !== null): ?>
                            <span class="ms-auto vp-tl-why">
                                <?= h($gap['len'] === 1
                                    ? sprintf(
                                        __(
                                            '%s is empty here. Whether'
                                            . ' the value was quiet or'
                                            . ' the record was not kept'
                                            . ' is what the lanes below'
                                            . ' answer.'
                                        ),
                                        $gap['from']['title']
                                    )
                                    : sprintf(
                                        __(
                                            '%1$s to %2$s is empty here.'
                                            . ' Whether the value was'
                                            . ' quiet or the record was'
                                            . ' not kept is what the'
                                            . ' lanes below answer.'
                                        ),
                                        $gap['from']['title'],
                                        $gap['to']['title']
                                    )) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php
            /*
             * ------------------------------------------------------
             * 2. The lanes
             * ------------------------------------------------------
             */
            ?>
            <section class="vp-tl-card">
                <div class="vp-tl-head">
                    <div class="vp-min-w-0">
                        <div class="fw-semibold">
                            <?= __('Sources in this window') ?>
                        </div>
                        <div class="vp-tl-why">
                            <span data-vp-tl-window-label><?= h(sprintf(
                                __('%1$s to %2$s'),
                                $window['from'],
                                $window['to']
                            )) ?></span>
                            ·
                            <span data-vp-tl-window-count><?=
                                count($windowed) ?></span>
                            <?= __('entries') ?>
                            ·
                            <?= __('every source the tab promises gets a'
                                . ' lane, whether or not MISP records'
                                . ' it') ?>
                        </div>
                    </div>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-vp-tl-reset hidden>
                        <?= __('Reset window') ?>
                    </button>
                </div>

                <div class="vp-lanes">
                    <div class="vp-lane-head"><?= __('Source') ?></div>
                    <div class="vp-lane-head vp-lane-ticks"
                         data-vp-tl-ticks>
                        <?php foreach ($ticks as $tick): ?>
                            <span><?= h($tick) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="vp-lane-head text-end">
                        <?= __('In window') ?>
                    </div>

                    <?php foreach ($lanes as $lane): ?>
                        <?php
                        $undatedLane = $lane['draw'] === 'undated';
                        $sources = isset($lane['sources'])
                            ? $lane['sources']
                            : array();

                        // The lane's own share of the window, counted
                        // from the same array the chronology lists.
                        $mine = array();
                        foreach ($windowed as $entry) {
                            if (in_array($entry['source'], $sources)) {
                                $mine[] = $entry;
                            }
                        }
                        $breakdown = array();
                        foreach ($mine as $entry) {
                            $label = $sourceMeta[$entry['source']]['label'];
                            if (!isset($breakdown[$label])) {
                                $breakdown[$label] = 0;
                            }
                            $breakdown[$label]++;
                        }
                        $parts = array();
                        foreach ($breakdown as $label => $n) {
                            $parts[] = $n . ' ' . $label;
                        }
                        ?>

                        <div class="vp-lane-label">
                            <?php if ($undatedLane): ?>
                                <span class="vp-tl-src vp-tl-src-none">
                                    <?= h($lane['label']) ?>
                                </span>
                            <?php else: ?>
                                <button type="button" class="vp-tl-src-btn"
                                        data-vp-tl-lane="<?= h($lane['key'])
                                            ?>"
                                        data-vp-tl-sources="<?=
                                            h(implode(',', $sources)) ?>"
                                        aria-pressed="false"
                                        title="<?= h(sprintf(
                                            __('Show only %s in the'
                                                . ' chronology'),
                                            $lane['label']
                                        )) ?>">
                                    <span class="vp-tl-src vp-tl-src-<?=
                                        h($lane['key']) ?>">
                                        <?= h($lane['label']) ?>
                                    </span>
                                </button>
                            <?php endif; ?>
                            <div class="vp-tl-why"><?= h($lane['sub']) ?>
                            </div>
                        </div>

                        <?php if ($undatedLane): ?>
                            <?php
                            /*
                             * A hatch, never a colour. This is not a
                             * severity — it is a hole in the record,
                             * and the two must not look alike. Clicking
                             * it does nothing and its title says why:
                             * there is nothing to narrow to.
                             */
                            $row = $lane['row'];
                            ?>
                            <div class="vp-lane-axis vp-lane-undated"
                                 title="<?= h($row === null
                                     ? $lane['absent']
                                     : $row['reason']) ?>">
                                <div class="vp-lane-undated-body">
                                    <span class="vp-lane-undated-text">
                                        <?= h($row === null
                                            ? $lane['absent']
                                            : $row['reason']) ?>
                                    </span>
                                    <?php if ($row !== null): ?>
                                        <?php foreach (
                                            array_slice($row['chips'], 0, 2)
                                            as $chip
                                        ): ?>
                                            <?php if ($chip['colour']): ?>
                                                <?= $this->element(
                                                    'genericElementsBS5/'
                                                        . 'Badges/tag',
                                                    array(
                                                        'tag' => array(
                                                            'name' =>
                                                                $chip['label'],
                                                            'colour' =>
                                                                $chip['colour'],
                                                        ),
                                                        'local' => false,
                                                        'hiddenClass' => '',
                                                    )
                                                ) ?>
                                            <?php else: ?>
                                                <span class="badge
                                                             bg-secondary">
                                                    <?= h($chip['label']) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <?php if (count($row['chips']) > 2): ?>
                                            <span class="badge bg-secondary">
                                                +<?= h(
                                                    count($row['chips']) - 2
                                                ) ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="vp-lane-count">
                                <?= h($row === null ? 0 : $row['count']) ?>
                                <div class="vp-tl-why">
                                    <?= __('undated') ?>
                                </div>
                            </div>

                        <?php else: ?>
                            <div class="vp-lane-axis"
                                 data-vp-tl-axis="<?= h($lane['key']) ?>"
                                 data-vp-tl-draw="<?= h($lane['draw']) ?>"
                                 data-vp-tl-sources="<?=
                                     h(implode(',', $sources)) ?>">
                                <?php if (!empty($lane['hatch'])): ?>
                                    <div class="vp-lane-fill">
                                        <span class="vp-lane-fill-text">
                                            <?= h($lane['hatch']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <?php foreach ($mine as $entry): ?>
                                    <?php if ($lane['draw'] !== 'spans') {
                                        continue;
                                    } ?>
                                    <span class="vp-lane-tag"
                                          style="left: <?= h(round(
                                              100 * $xFor($entry['at'])
                                                  / $LANE_W,
                                              2
                                          )) ?>%;">
                                        <?= h($entry['ref']['attribute']) ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php
                                /*
                                 * `style="fill: var(…)"` and not the
                                 * `fill` attribute: a presentation
                                 * attribute does not resolve a custom
                                 * property, and the whole palette here
                                 * is custom properties so that a lane
                                 * mark and its stack segment cannot
                                 * drift apart.
                                 */
                                ?>
                                <svg viewBox="0 0 <?= (int)$LANE_W ?> <?=
                                         (int)$LANE_H ?>"
                                     preserveAspectRatio="none"
                                     class="vp-lane-svg"
                                     data-vp-tl-marks>
                                    <?php foreach ($mine as $entry): ?>
                                        <?php
                                        $x = $xFor($entry['at']);
                                        $hue =
                                            $sourceMeta[$entry['source']]
                                                ['token'];
                                        if ($lane['draw'] === 'spans') {
                                            $to = $entry['span_to'] === null
                                                ? $entry['at']
                                                : $entry['span_to'];
                                            $w = max(
                                                $MARK_W,
                                                $xFor($to) - $x
                                            );
                                            ?>
                                            <rect class="vp-lane-span"
                                                  x="<?= h($x) ?>" y="19"
                                                  width="<?= h($w) ?>"
                                                  height="7" rx="3"
                                                  style="--vp-tl-hue: <?=
                                                      h($hue) ?>;">
                                                <title><?= h(
                                                    $entry['title']
                                                ) ?></title>
                                            </rect>
                                            <?php
                                            continue;
                                        }
                                        ?>
                                        <?php if (!empty($lane['hatch'])): ?>
                                            <?php
                                            /*
                                             * A mark on a hatch needs a
                                             * ground. The one recorded
                                             * edit against an
                                             * unrecorded background is
                                             * this lane's whole point,
                                             * and a mark the colour of
                                             * the hatch behind it loses
                                             * it (§8.2).
                                             */
                                            ?>
                                            <rect class="vp-lane-ground"
                                                  x="<?= h($x - 3) ?>" y="9"
                                                  width="11" height="19"
                                                  rx="2"></rect>
                                        <?php endif; ?>
                                        <rect class="vp-lane-mark"
                                              x="<?= h($x) ?>" y="12"
                                              width="<?= (int)$MARK_W ?>"
                                              height="13" rx="1.5"
                                              style="--vp-tl-hue: <?=
                                                  h($hue) ?>;">
                                            <title><?= h($entry['title'])
                                                ?></title>
                                        </rect>
                                    <?php endforeach; ?>
                                </svg>
                            </div>
                            <div class="vp-lane-count"
                                 data-vp-tl-count="<?= h($lane['key']) ?>">
                                <span data-vp-tl-count-n><?=
                                    count($mine) ?></span>
                                <div class="vp-tl-why"
                                     data-vp-tl-count-why><?=
                                    h(implode(', ', $parts)) ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="vp-tl-why pt-2">
                    <?= h(__('Four lanes can carry marks · one is'
                        . ' truncated and says where · two are'
                        . ' structurally empty and always will be')) ?>
                </div>
            </section>

            <?php
            /*
             * ------------------------------------------------------
             * 3. The chronology
             * ------------------------------------------------------
             */
            ?>
            <section class="vp-tl-card" data-vp-tl-list>
                <div class="vp-tl-head">
                    <div class="vp-min-w-0">
                        <div class="fw-semibold">
                            <?= __('Chronology') ?>
                        </div>
                        <div class="vp-tl-why">
                            <?= __('Newest first. Click a source above to'
                                . ' narrow to it.') ?>
                            <span data-vp-tl-filter-note hidden>
                                <?= __('Showing') ?>
                                <b data-vp-tl-filter-name></b>
                                <button type="button"
                                        class="btn btn-link btn-sm p-0"
                                        data-vp-tl-filter-clear>
                                    <?= __('clear') ?>
                                </button>
                            </span>
                        </div>
                    </div>
                    <div class="d-flex gap-1 align-items-center">
                        <span class="vp-prec vp-prec-exact">
                            <?= __('exact') ?>
                            <b data-vp-tl-tally-exact><?=
                                h($tally['exact']) ?></b>
                        </span>
                        <span class="vp-prec vp-prec-part">
                            <?= __('partial') ?>
                            <b data-vp-tl-tally-part><?=
                                h($tally['partial']) ?></b>
                        </span>
                    </div>
                </div>

                <?php foreach ($days as $group): ?>
                    <?php
                    /*
                     * A day is wholly inside the window or wholly
                     * outside it, so the whole chronology can be
                     * rendered once and the days outside simply
                     * hidden. Without JavaScript that leaves exactly
                     * the default window on screen, with counts that
                     * were computed for it (§10).
                     */
                    $dayIn = $group['day'] >= $window['from']
                        && $group['day'] <= $window['to'];
                    ?>
                    <div class="vp-tl-day" data-vp-tl-day-head="<?=
                        h($group['day']) ?>"<?= $dayIn ? '' : ' hidden'
                        ?>><?= h($group['label']) ?></div>

                    <?php foreach ($group['units'] as $unit): ?>
                        <?php $collapse = count($unit) >= $RUN_MIN; ?>

                        <?php if ($collapse): ?>
                            <?php
                            $by = array();
                            foreach ($unit as $entry) {
                                $org = $entry['org'] === null
                                    ? __('unattributed')
                                    : $entry['org'];
                                if (!isset($by[$org])) {
                                    $by[$org] = 0;
                                }
                                $by[$org]++;
                            }
                            $summary = array();
                            foreach ($by as $org => $n) {
                                $summary[] = $org . ' ' . $n;
                            }
                            $meta = $sourceMeta[$unit[0]['source']];
                            ?>
                            <div class="vp-tl-collapsed"
                                 data-vp-tl-row
                                 data-vp-tl-day="<?= h($group['day']) ?>"
                                 data-vp-tl-source="<?=
                                     h($unit[0]['source']) ?>"
                                 data-vp-tl-run="<?= h($group['day'] . ':'
                                     . $unit[0]['source'] . ':'
                                     . $unit[0]['at']) ?>"
                                 <?= $dayIn ? '' : 'hidden' ?>>
                                <i class="<?= h($meta['icon']) ?> me-1"></i>
                                <?= h(sprintf(
                                    __('%1$s %2$s collapsed — %3$s'),
                                    count($unit),
                                    $meta['plural'],
                                    implode(', ', $summary)
                                )) ?>
                                <button type="button"
                                        class="btn btn-link btn-sm p-0 ms-1"
                                        data-vp-tl-expand>
                                    <?= __('expand') ?>
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($unit as $entry): ?>
                            <?php
                            $meta = $sourceMeta[$entry['source']];
                            $prec = $precisionMeta[$entry['precision']];
                            ?>
                            <div class="vp-audit-row"
                                 data-vp-tl-row
                                 data-vp-tl-day="<?= h($group['day']) ?>"
                                 data-vp-tl-at="<?= h($entry['at']) ?>"
                                 data-vp-tl-source="<?=
                                     h($entry['source']) ?>"
                                 data-vp-tl-precision="<?=
                                     h($prec['bucket']) ?>"
                                 data-vp-tl-ref="<?=
                                     h($entry['ref']['attribute']) ?>"
                                 <?php if ($collapse): ?>
                                     data-vp-tl-in-run="<?=
                                         h($group['day'] . ':'
                                             . $unit[0]['source'] . ':'
                                             . $unit[0]['at']) ?>"
                                 <?php endif; ?>
                                 <?php if ($entry['span_to'] !== null): ?>
                                     data-vp-tl-span-to="<?=
                                         h($entry['span_to']) ?>"
                                 <?php endif; ?>
                                 <?= $dayIn && !$collapse ? '' : 'hidden'
                                     ?>>
                                <div class="vp-tl-time"><?=
                                    h(substr($entry['at'], 11, 5)) ?></div>
                                <div class="vp-tl-dot">
                                    <i class="<?= h($meta['icon']) ?>"></i>
                                </div>
                                <div class="vp-min-w-0">
                                    <div class="vp-tl-main">
                                        <span class="vp-tl-src vp-tl-src-<?=
                                            h($entry['source']) ?>"><?=
                                            h($meta['label']) ?></span>
                                        <?= h($entry['title']) ?>
                                        <span class="vp-prec <?=
                                            h($prec['class']) ?>"><?=
                                            h($prec['label']) ?></span>
                                    </div>
                                    <?php if ($entry['note'] !== null): ?>
                                        <div class="vp-tl-why"><?=
                                            h($entry['note']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>

                <?php if (empty($entries)): ?>
                    <?php
                    /*
                     * The state that justifies the design. Nothing is
                     * dated, and the tab still says something true:
                     * the lanes above name every source that could
                     * have carried a date and did not, and the two
                     * that never could.
                     */
                    ?>
                    <div class="vp-empty">
                        <i class="fas fa-clock"></i>
                        <span><?= __('Nothing about this value carries a'
                            . ' date. The lanes above say which sources'
                            . ' could have supplied one.') ?></span>
                    </div>
                <?php endif; ?>

                <div class="vp-tl-foot" data-vp-tl-foot hidden>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-vp-tl-more>
                        <span data-vp-tl-more-n>0</span>
                        <?= __('more in this window') ?>
                    </button>
                </div>

                <div class="vp-empty vp-tl-blank" data-vp-tl-blank hidden>
                    <i class="fas fa-clock"></i>
                    <span><?= __('Nothing dated falls in this window.') ?>
                    </span>
                </div>

                <?php if ($aclNote !== null): ?>
                    <?php
                    /*
                     * The entry set is the viewer's, not the
                     * instance's, and a chronology that does not say so
                     * reads as complete. `Sightings_policy` and
                     * `Sightings_anonymise` move this line for two
                     * users looking at the same value.
                     */
                    ?>
                    <div class="vp-acl-note">
                        <i class="fas fa-eye-slash"></i>
                        <span><?= h($aclNote) ?></span>
                    </div>
                <?php endif; ?>
            </section>

        </div>

    <?php endif; ?>
</div>
