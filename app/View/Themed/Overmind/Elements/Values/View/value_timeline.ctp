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
App::uses('ValueProfileBuckets', 'Tools');

/*
 * The spine's grain, chosen from the value's own range.
 *
 * The fixture pinned twelve monthly bins because its range was a year.
 * A value first seen last week needs finer bins and one held since 2020
 * needs coarser, so the rule is the range's — and it is this tab's own
 * rule rather than `ValueProfileBuckets`' default, because a bar here
 * means something different from a bar on the Sightings navigator. A
 * sighting has a timestamp to the second; a bar here is a density over
 * sources MISP dates unevenly, so the finest grain it offers is a day
 * and it reaches for it only when the whole range is short enough that
 * a day is what the reader is asking about.
 *
 * Deliberately not `plan()`. That ships every grain and lets the
 * browser re-aggregate, which is right for a chart with one series and
 * wrong here: this spine is stacked per source, so a grain is a matrix
 * rather than a row, and the counts it stacks are already server-side.
 */
$spineRule = array(
    array('days' => 45, 'unit' => ValueProfileBuckets::DAY),
    array('days' => 400, 'unit' => ValueProfileBuckets::WEEK),
    array('days' => null, 'unit' => ValueProfileBuckets::MONTH),
);

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

/*
 * ------------------------------------------------------------------
 * The counts arrive; they are no longer all derived here
 * ------------------------------------------------------------------
 * The tab's rule was that every number in the panel is an aggregate
 * over `entries`, so two of them could not disagree. That holds only
 * while `entries` is all of them, and on a value like `443` — 174,299
 * dated things, 172,426 of them audit rows — it is not: the array is
 * the newest few hundred and the panel would be charting a fortnight
 * under a year's axis.
 *
 * So the counts come from aggregates over the whole set the viewer may
 * see, and `entries` is what the chronology lists. The invariant is
 * kept, in the stronger form: **no number below is tallied from the
 * rows unless the rows are all of them.** `by_day` bins the spine,
 * `in_window` fills the lane grid, `total` and `shown` are what the
 * panel says out loud.
 */
$counts = $timeline === null
    ? array(
        'total' => 0,
        'shown' => 0,
        'by_source' => array(),
        'by_day' => array(),
        'in_window' => array('total' => 0),
        'first' => null,
        'last' => null,
        'capped' => false,
        'cap' => 0,
    )
    : $timeline['counts'];
$countsByDay = $counts['by_day'];
$inWindow = $counts['in_window'];
$spans = $timeline === null
    ? array('occurrences' => 0, 'with' => 0, 'shown' => 0, 'cap' => 0)
    : $timeline['spans'];

$windowFrom = $window === null ? null : $window['from'] . ' 00:00:00';
$windowTo = $window === null ? null : $window['to'] . ' 23:59:59';

/*
 * Which of the *listed* rows fall in the window. This is the
 * chronology's own business — which rows to show — and never a count
 * the panel prints: the numbers come from `$inWindow`, which was summed
 * over every day the viewer may see rather than over the rows that fit.
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
 * Over the value's whole dated range, at whatever grain the range
 * asks for, and binned from `by_day` rather than from the rows — so a
 * value whose chronology is capped still gets its whole history
 * charted. That is the difference between this chart and a chart of
 * the last fortnight labelled as a year.
 *
 * `$before` therefore stays at zero and the notice under the chart no
 * longer fires: the spine now begins where the value does, so there is
 * nothing older than it to count. The branch is kept because a future
 * ceiling on the axis would need it back, and removing it would take
 * the wording with it.
 */
$bins = array();
$before = 0;
$earliest = null;
$spineUnit = ValueProfileBuckets::MONTH;
$rangeFrom = $counts['first'] === null
    ? null
    : substr($counts['first'], 0, 10);
$rangeTo = $counts['last'] === null
    ? null
    : substr($counts['last'], 0, 10);
if ($window !== null && $rangeFrom !== null) {
    $rangeDays = 1 + (int)(new DateTimeImmutable($rangeFrom, $utc))
        ->diff(new DateTimeImmutable($rangeTo, $utc))
        ->days;
    $spineUnit = ValueProfileBuckets::unitForSpan($rangeDays, $spineRule);
    $bins = ValueProfileBuckets::series($rangeFrom, $rangeTo, $spineUnit);
    foreach ($bins as $i => $bin) {
        $bins[$i]['counts'] = array();
        $bins[$i]['total'] = 0;
    }
    $index = ValueProfileBuckets::locate($bins);
    foreach ($countsByDay as $day => $bySource) {
        if (!isset($index[$day])) {
            continue;
        }
        $at = $index[$day];
        foreach ($bySource as $source => $n) {
            if (!isset($bins[$at]['counts'][$source])) {
                $bins[$at]['counts'][$source] = 0;
            }
            $bins[$at]['counts'][$source] += $n;
            $bins[$at]['total'] += $n;
        }
    }
}

/*
 * Which sources the value actually has, in the vocabulary's order. A
 * stack segment for a source nobody ever filed is a legend entry
 * teaching the reader a colour they will never meet again.
 *
 * From the counts and not from the rows, which is the same correction
 * as everywhere else on this page: a source whose every row was capped
 * away still has entries, and a legend that dropped it would be
 * hiding a stack segment the chart above is drawing.
 */
$present = array();
foreach ($sourceMeta as $key => $meta) {
    if (!empty($counts['by_source'][$key])) {
        $present[] = $key;
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
 * The month names the ruler and the spine's axis both read. Formatted
 * once here and shipped, because the ruler is redrawn client-side for
 * every brush and a second formatter in JavaScript would be a second
 * vocabulary — `toLocaleString` follows the browser's locale, `M`
 * follows PHP's, and the two disagree the moment they differ.
 */
$months = array();
for ($m = 1; $m <= 12; $m++) {
    $months[] = (new DateTimeImmutable(sprintf('2001-%02d-01', $m), $utc))
        ->format('M');
}

/**
 * One ruler label: the day, plus whatever the tick before it has not
 * already said.
 *
 * @param DateTimeImmutable $at
 * @param DateTimeImmutable|null $prev The tick to its left, if any
 * @return string
 */
$rulerLabel = function ($at, $prev) use ($months) {
    $day = $at->format('j');
    if ($prev !== null && $prev->format('Y-m') === $at->format('Y-m')) {
        return $day;
    }
    $label = $day . ' ' . $months[(int)$at->format('n') - 1];
    if ($prev === null || $prev->format('Y') !== $at->format('Y')) {
        $label .= ' ' . $at->format('Y');
    }
    return $label;
};

/*
 * Five ticks across the window. Enough for a reader to place a mark
 * without turning the lane header into a ruler.
 *
 * Each one names its month where the month changed and its year where
 * the year did, which the first four never did before: the window is
 * the reader's to set and a brush over the whole spine is years wide,
 * so a ruler of bare day numbers put four of its five ticks in the
 * first tick's month and never named a year at all.
 */
$ticks = array();
if ($window !== null) {
    $prev = null;
    for ($i = 0; $i < 5; $i++) {
        $at = $t0 + (int)round(($span * $i) / 4);
        $tick = (new DateTimeImmutable('@' . $at))->setTimezone($utc);
        $ticks[] = $rulerLabel($tick, $prev);
        $prev = $tick;
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
/*
 * Matched on the stable key and never on the translated `kind`. Both
 * used to come from the same `__()` call, so they agreed in English and
 * in any locale that translated the two strings identically — and
 * stopped agreeing otherwise, the lane rendering its *absent* text
 * while the strip below it listed the chips.
 */
$undatedBy = array();
foreach ($undated as $row) {
    $undatedBy[$row['key']] = $row;
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
        /*
         * Three numbers and not two, when the cap bites: how many
         * occurrences carry a span, of how many the viewer can see, and
         * how many of those this lane drew. A cap is not a permission,
         * so the third is said out loud for every reader.
         */
        'sub' => $spans['shown'] < $spans['with']
            ? sprintf(
                __('%1$s of %2$s occurrences carry one · drawing %3$s'),
                $spans['with'],
                $spans['occurrences'],
                $spans['shown']
            )
            : sprintf(
                __('%1$s of %2$s occurrences carry one'),
                $spans['with'],
                $spans['occurrences']
            ),
        'sources' => array('seen'),
        'draw' => 'spans',
    ),
    array(
        'key' => 'tags',
        'label' => __('Tags'),
        'sub' => __('no column exists, any instance'),
        'draw' => 'undated',
        'row' => isset($undatedBy['tags'])
            ? $undatedBy['tags']
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
        'row' => isset($undatedBy['feeds'])
            ? $undatedBy['feeds']
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

$rowsByDay = array();
foreach (array_reverse($entries) as $entry) {
    $day = substr($entry['at'], 0, 10);
    if (!isset($rowsByDay[$day])) {
        $rowsByDay[$day] = array();
    }
    $rowsByDay[$day][] = $entry;
}

$days = array();
foreach ($rowsByDay as $day => $rows) {
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
 * property that makes it worth printing at all — and it is therefore
 * a tally over the *listed* rows rather than over the value, which is
 * the one place on this panel where that is the right choice.
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
        /*
         * The day map travels with the chart, so a brushed window is
         * counted the same way the default one was: summed over every
         * day the viewer may see. Without it the script would fall back
         * to tallying the rows in the DOM, which are capped — and the
         * lane counts would drop the moment a reader brushed a value
         * whose chronology did not fit, which is the disagreement this
         * panel exists to make impossible. A day is a date and a small
         * object; `443`'s whole history is 49 of them.
         */
        'by_day' => $countsByDay,
        'window' => array(
            'from' => $window['from'],
            'to' => $window['to'],
        ),
        'lane' => array(
            'width' => $LANE_W,
            'height' => $LANE_H,
            'mark' => $MARK_W,
        ),
        // For the ruler over the lanes, which moves with the brush.
        'months' => $months,
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
        $counts['total'],
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
         * Not an empty timeline — no timeline. An axis, empty bins and
         * seven lanes over a value with no occurrence to date would be
         * inventing a period of silence that never happened.
         *
         * **The sentence says nothing about why**, and that is the
         * fixture-era wording corrected rather than carried over. It
         * read *because MISP has never held it*, which is one of the two
         * cases: the other is a value held only in events this reader
         * cannot open. Naming the first would be false for the second,
         * and naming either would make the panel answer *does this exist
         * on the instance* — on a page whose URL takes any value the
         * reader types. So one sentence, true both ways, identical for
         * every reader.
         */
        ?>
        <div class="p-3">
            <div class="vp-empty">
                <i class="fas fa-clock"></i>
                <span><?= __('There is no occurrence of this value here'
                    . ' to place on an axis.') ?></span>
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
                            <?php
                            /*
                             * The grain is named rather than assumed:
                             * it is chosen from the value's range, so
                             * two values on this page can carry two
                             * different bar widths and a reader who is
                             * not told will read one as the other.
                             */
                            $grainWord = array(
                                ValueProfileBuckets::DAY => __('by day'),
                                ValueProfileBuckets::WEEK => __('by week'),
                                ValueProfileBuckets::MONTH => __('by month'),
                            );
                            ?>
                            <?= h(sprintf(
                                __(
                                    '%1$s %2$s, stacked by source.'
                                    . ' Drag to set the window — the'
                                    . ' lanes and the chronology below'
                                    . ' both follow it.'
                                ),
                                __n(
                                    '%s dated entry',
                                    '%s dated entries',
                                    $counts['total'],
                                    $counts['total']
                                ),
                                isset($grainWord[$spineUnit])
                                    ? $grainWord[$spineUnit]
                                    : __('by month')
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
                    <?php
                    /*
                     * Named from the grain and the range, not from the
                     * twelve months the fixture drew: the spine now
                     * covers the value's whole history at whichever of
                     * three grains the range asks for, so the fixture's
                     * wording was false for every value but one.
                     */
                    ?>
                    <canvas id="vp-tl-spine" role="img"
                            aria-label="<?= h(sprintf(
                                __('Dated entries %1$s from %2$s to'
                                    . ' %3$s, stacked by source'),
                                isset($grainWord[$spineUnit])
                                    ? $grainWord[$spineUnit]
                                    : __('by month'),
                                $rangeFrom === null ? '-' : $rangeFrom,
                                $rangeTo === null ? '-' : $rangeTo
                            )) ?>"></canvas>
                    <?php
                    /*
                     * Hidden until the chart it controls exists. A
                     * brush framing an empty canvas offers a gesture
                     * that cannot do anything, which is worse than
                     * offering none.
                     */
                    ?>
                    <div class="vp-brush" data-vp-brush hidden>
                        <div class="vp-brush-mask" data-vp-brush-mask-left>
                        </div>
                        <div class="vp-brush-window" data-vp-brush-handle>
                        </div>
                        <div class="vp-brush-mask" data-vp-brush-mask-right>
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
                                (int)$inWindow['total'] ?></span>
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

                        /*
                         * The lane's own share of the window, from the
                         * summed day map rather than from the rows the
                         * chronology happens to be carrying. On a value
                         * whose chronology is capped these differ, and
                         * the row tally is the one that is wrong: `443`
                         * ships 300 rows inside three days, so tallying
                         * them would report a window of 11 entries as
                         * 300 and every quiet lane as busy.
                         */
                        $laneCount = 0;
                        $parts = array();
                        foreach ($sources as $source) {
                            $n = isset($inWindow[$source])
                                ? (int)$inWindow[$source]
                                : 0;
                            $laneCount += $n;
                            if ($n > 0) {
                                $parts[] = $n . ' '
                                    . $sourceMeta[$source]['label'];
                            }
                        }
                        // What the lane draws, which is still the rows:
                        // marks come from entries, counts do not.
                        $mine = array();
                        foreach ($windowed as $entry) {
                            if (in_array($entry['source'], $sources)) {
                                $mine[] = $entry;
                            }
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
                                    (int)$laneCount ?></span>
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
                            <?php if ($counts['capped']): ?>
                                <?php
                                /*
                                 * Two numbers about the same query at
                                 * two grains, and not a disagreement:
                                 * the aggregates above describe every
                                 * dated thing the viewer may see, and
                                 * this list carries the newest of them
                                 * that fit. A cap is not a permission,
                                 * so it reads the same for every
                                 * reader.
                                 */
                                ?>
                                <b><?= h(sprintf(
                                    __('Showing the newest %1$s of'
                                        . ' %2$s entries.'),
                                    number_format($counts['shown']),
                                    number_format($counts['total'])
                                )) ?></b>
                            <?php endif; ?>
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

                <?php
                /*
                 * The other reason this list can be empty, and the one
                 * it used to deny: the window *does* hold entries and
                 * none of them are rows the fragment carries.
                 *
                 * A reader who brushes the first active bar of a value
                 * with years of history hits it every time — the spine
                 * is binned from the aggregate and covers the whole
                 * range, while the rows are the newest cap-many. Saying
                 * *nothing dated falls in this window* there is not a
                 * softer answer than this one, it is the opposite of
                 * what the number beside the window label says, so a
                 * reader can only conclude the panel is broken.
                 *
                 * Two caps can put a day in the aggregate with no row
                 * to show for it, and the sentence names whichever
                 * applies: `TIMELINE_ROW_CAP` over the merged
                 * chronology, whose numbers are worth printing, and
                 * `TIMELINE_SPAN_CAP` inside the seen lane, which bites
                 * on values whose chronology fits whole.
                 */
                ?>
                <div class="vp-empty vp-tl-blank"
                     data-vp-tl-blank-capped hidden>
                    <i class="fas fa-clock"></i>
                    <span>
                        <?= sprintf(
                            __('%s dated entries fall in this window,'
                                . ' and none of them are among the'
                                . ' rows this list carries.'),
                            '<b data-vp-tl-blank-n>0</b>'
                        ) ?>
                        <?php if ($counts['capped']): ?>
                            <?= h(sprintf(
                                __('It holds the newest %1$s of %2$s —'
                                    . ' brush a more recent period to'
                                    . ' read them.'),
                                number_format($counts['shown']),
                                number_format($counts['total'])
                            )) ?>
                        <?php else: ?>
                            <?= h(__('A lane caps the rows it draws;'
                                . ' the counts above are over all of'
                                . ' them.')) ?>
                        <?php endif; ?>
                    </span>
                </div>

                <?php
                /*
                 * **No ACL band here, deliberately.** The fixture drew
                 * one — *four of this value's nine occurrences are on
                 * events you cannot see* — and a note whose presence is
                 * itself the disclosure cannot ship on a page whose URL
                 * takes any value the reader types: it turns the page
                 * into an oracle for what exists on the instance. The
                 * panel where everything is hidden therefore renders as
                 * the panel where nothing is dated, which is a real
                 * loss and the cheaper of the two.
                 *
                 * Cap notices are a different thing and stay: a cap is
                 * a property of the fragment, not of the reader, so it
                 * reads identically for everyone and discloses nothing.
                 */
                ?>
            </section>

        </div>

    <?php endif; ?>
</div>
