<?php
/**
 * The counted rail beside the occurrence table.
 *
 * Every filter carries its own count, so the filter set and the summary
 * of the value are the same object — which is the whole addition over
 * the Overview's preview of this table.
 *
 * A count here is what this viewer may see, never what the instance
 * holds. Where that differs from a number already on the page, the note
 * under the header says so: it is the one place where the gap between
 * the banner's total and the rail's is written as a number.
 *
 * Rendered inside `value_occurrence_table`'s row, not as its own ajax
 * panel — the counts and the rows they count come from one fetch.
 *
 * @var array $facets `occurrence_facets` for this value
 */
$groups = $facets['groups'];

/*
 * Order, heading and glyph are the same for every value, so they live
 * here rather than in the fixture; only the counts vary. A key with no
 * values is a group that renders nothing at all — a facet rail of
 * zeroes claims there are rows to narrow.
 */
$defined = array(
    array(
        'key' => 'organisation',
        'title' => __('Organisation'),
        'icon' => 'fas fa-building',
    ),
    array(
        'key' => 'type',
        'title' => __('Type'),
        'icon' => 'misp-icon misp-icon-attribute misp-simple',
    ),
    /*
     * Beside Type, because they answer the same shape of question — what
     * kind of thing is this row — and differ in a way the type alone
     * hides: one `ip-dst` standalone, one inside a `domain-ip` and one
     * inside a `network-socket` are three different findings.
     */
    array(
        'key' => 'object',
        'title' => __('Object'),
        'icon' => 'misp-icon misp-icon-object misp-simple',
    ),
    array(
        'key' => 'category',
        'title' => __('Category'),
        'icon' => 'fas fa-folder',
    ),
    array(
        'key' => 'ids',
        'title' => __('IDS flag'),
        'icon' => 'fas fa-shield-halved',
    ),
    array(
        'key' => 'distribution',
        'title' => __('Distribution'),
        'icon' => 'fas fa-globe',
    ),
    array(
        'key' => 'sharing_group',
        'title' => __('Sharing group'),
        'icon' => 'misp-icon misp-icon-sharing-group misp-simple',
    ),
    /*
     * One group over both scopes, matching the Tags column: a label on
     * the row's event covers the row, so *this occurrence is labelled
     * X* and *this occurrence arrived in a report labelled X* are one
     * question for a reader narrowing a table. A row carrying a tag on
     * both sides is counted once.
     *
     * Most of what it lists comes from the events: `8.8.8.8` carries 7
     * distinct attribute tags and 48 distinct event tags.
     */
    array(
        'key' => 'tag',
        'title' => __('Tag'),
        'icon' => 'misp-icon misp-icon-tag misp-simple',
    ),
    /*
     * Below Tag and keyed apart from it, because a cluster is not a
     * label: *what is this occurrence labelled* and *what is it
     * attributed to* are two questions, where the two scopes of one tag
     * were one question asked of two rows.
     *
     * It arrived with the Galaxies column and could not have arrived
     * before it — the rail's standing rule is that a filter on
     * something invisible is not a filter, and until that column
     * existed the pane drew no cluster at all.
     */
    array(
        'key' => 'galaxy',
        'title' => __('Galaxy cluster'),
        'icon' => 'misp-icon misp-icon-galaxy misp-simple',
    ),
);

/*
 * The label is the component wherever MISP has one: a distribution row
 * carries the real badge, a tag row the real chip. Rendering the level
 * as the word "3" — or the tag as its name in plain text — would make
 * the rail the one place on the page where these look like something
 * else.
 */
foreach ($groups['distribution'] as &$facet) {
    $facet['html'] = $this->element(
        'genericElementsBS5/Badges/distribution',
        array('distribution' => $facet['level'], 'full' => true)
    );
}
unset($facet);

/*
 * The tag group draws the real chip, so a rail row and the cell it
 * narrows to are the same object.
 */
foreach ($groups['tag'] as &$facet) {
    $facet['html'] = $this->element(
        'genericElementsBS5/Badges/tag',
        array(
            'tag' => $facet['tag'],
            'local' => !empty($facet['local']),
            'hiddenClass' => '',
            'showFavourite' => false,
        )
    );
}
unset($facet);

/*
 * A cluster row is the chip the Galaxies column draws, with its galaxy
 * beside it — the one place in the pane the galaxy is named in words.
 * The column puts it in the chip's title instead, because there it
 * would repeat down every row; here each cluster appears once.
 *
 * **Named on every row rather than heading runs of them**, which is
 * how the card groups them and is wrong here: a group's search box and
 * its `N more` fold both hide rows, so a heading row would strand or
 * vanish from the clusters it was heading. A row that carries its own
 * galaxy survives both. The qualifier takes the ellipsis when the
 * cluster's name is long — the name is what the reader is picking —
 * and the row's title carries both in full.
 */
foreach ($groups['galaxy'] as &$facet) {
    $facet['html'] = '<span title="'
        . h(empty($facet['galaxy'])
            ? $facet['cluster']
            : sprintf(
                __('%1$s — in %2$s'),
                $facet['cluster'],
                $facet['galaxy']
            ))
        . '"><span class="vp-galaxy">'
        . '<span class="misp-icon misp-icon-galaxy misp-simple"></span>'
        . '<span class="vp-galaxy-name">' . h($facet['cluster'])
        . '</span></span>'
        . (empty($facet['galaxy'])
            ? ''
            : '<span class="vp-facet-galaxy">' . h($facet['galaxy'])
                . '</span>')
        . '</span>';
}
unset($facet);

/*
 * Empty when no occurrence carries either seen date. Forty zero bars and
 * two date inputs pre-filled from nothing would claim there was nothing
 * to see; what is true is that nobody recorded when, and the line under
 * the group is what says so.
 */
$spark = $facets['seen_spark'];
$sparkMax = empty($spark) ? 1 : max(1, max($spark));

/*
 * Still disabled, and now it has to say why rather than only that: two
 * working date ranges sit directly above it, so "not wired yet" would
 * read as an oversight. It is a different question. `timestamp` and
 * `publish_timestamp` are instants, and cutting them is a point-in-range
 * test; first/last seen is an *interval*, and the question a reader asks
 * of it — "was this live during my window" — is an overlap test, which
 * the range filter above does not do.
 */
$seenDisabled = __(
    'Not a date cut like the two above: first and last seen are an'
    . ' interval, so filtering them means asking which occurrences'
    . ' overlap a window rather than which fall inside one. Not wired'
    . ' in this pass.'
);

$hasState = !empty($groups['state']) || !empty($facets['deleted']);
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--attribute);">

    <?php
    ob_start();
    ?>
        <button type="button" class="btn btn-sm btn-outline-danger"
                data-vp-facet-clear disabled>
            <?= __('Clear all') ?>
        </button>
    <?php
    $headerExtra = ob_get_clean();

    /*
     * "No filter applied" and "2 filters" are the same line in two
     * states rather than two lines, so the reader's eye does not have
     * to move when the first box is ticked.
     */
    ob_start();
    ?>
        <span data-vp-facet-summary>
            <span class="vp-facet-summary-none"><?=
                __('No filter applied') ?></span>
            <span class="vp-facet-summary-some"><span
                data-vp-facet-count-active>0</span> <?= __('filters') ?></span>
            &nbsp;&middot;&nbsp;
            <span data-vp-facet-rows><?= h($facets['visible']) ?></span>
            <?= __('rows') ?>
        </span>
    <?php
    $headerSub = ob_get_clean();
    ?>

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Filters'),
        'panelIcon' => 'fas fa-filter',
        'panelColor' => 'var(--attribute)',
        'panelSub' => $headerSub,
        'panelExtra' => $headerExtra,
    )) ?>

    <?php
    /*
     * There used to be a `.vp-facet-note` here spelling out the gap
     * between the banner's instance-wide chip count and this rail's
     * viewer-scoped one. §14.6 made every count on the page the
     * viewer's, so banner and rail now agree by construction and there
     * is no gap left to explain. The sentence is gone rather than
     * reworded: a note that exists only to reconcile two numbers has
     * nothing to say once they cannot differ.
     */
    ?>
    <div class="card-body py-0 px-3">

        <?php
        /*
         * **First in the rail, and one control rather than three.**
         *
         * First because a date is the cut a reader reaches for before
         * any of the vocabularies below it — *what has moved lately* is
         * asked of a value long before *which organisation reported it*
         * — and the group used to sit ninth, under eight lists that a
         * long tag or galaxy tail can push a screen apart.
         *
         * One control because the three dates are alternatives, not
         * conjuncts. `timestamp` is when somebody last touched the
         * attribute, `publish_timestamp` when its event was last
         * released, `Event.timestamp` when the event itself last moved;
         * an occurrence edited yesterday on an event published last
         * year is a different thing from the reverse, and neither is
         * answerable from the other. But a reader cutting on one of
         * them is not also cutting on the next, and three stacked
         * strips said otherwise — 96px of chart before the first facet
         * list, two of them describing a question nobody had asked.
         *
         * So the dropdown names the date and one pane draws it, and
         * **switching scope clears the bound the old one held**. Left
         * behind, that bound would go on filtering the table from a
         * pane the reader can no longer see, which is the one thing a
         * rail whose whole claim is *the filter set is the summary*
         * cannot do.
         *
         * Ranges rather than facet checkboxes because a date has no
         * vocabulary to tick, and all three are wired — unlike the
         * first/last-seen control further down, which needs an overlap
         * test rather than a point test and stays disabled.
         *
         * The inputs start empty and carry the span as `min`/`max`. A
         * control pre-filled with the whole span looks like a filter
         * already applied, and "no bound" must not render the same as
         * "the widest bound".
         */
        /*
         * The same words `value_zoom` uses for a grain, so the two
         * captions on this page name a bucket the same way.
         */
        $grainWords = array(
            'day' => __('one bar a day'),
            'week' => __('one bar a week'),
            'month' => __('one bar a month'),
        );
        /*
         * The date axis under a strip: which bars carry a tick, and
         * which of those carry a label.
         *
         * **The next unit up from the grain**, so the axis is always
         * something the bars are not already saying. Monthly bars get
         * year marks; daily and weekly bars get month marks. The
         * caption underneath states the grain and both ends, so the
         * axis only has to let a reader place a bar between them —
         * which is what the strip could not do at all before: a bar
         * four fifths of the way along nine years read as *recent* and
         * no more precisely than that.
         *
         * **Keyed by bar index, and the value may be null** — a tick
         * without a label. The marks are the reading and they stay;
         * `8.8.8.8`'s weekly panes cross twelve months in 342px and
         * twelve three-letter labels would overlap into a smear.
         *
         * **The opening bucket is never a tick.** A span that starts in
         * June is not a boundary of the year it starts in, and marking
         * it would put a `2022` under a bar that is not January.
         *
         * A label in the last few bars is dropped rather than drawn:
         * `.vp-spark-tick` is left-aligned on its slot, so one at the
         * end hangs off the edge of the rail. 8% of the bars is about
         * 27px at every bar count this strip draws.
         */
        $timeScale = function (array $histogram) {
            $bars = $histogram['bars'];
            $annual = $histogram['unit'] === 'month';
            $marks = array();
            $seen = null;
            foreach ($bars as $index => $bar) {
                $period = substr($bar['from'], 0, $annual ? 4 : 7);
                $opened = $period !== $seen;
                $seen = $period;
                if (!$opened || $index === 0) {
                    continue;
                }
                $marks[$index] = $annual
                    ? $period
                    : date('M', strtotime($bar['from']));
            }
            $step = max(1, (int)ceil(count($marks) / 6));
            $tail = count($bars)
                - max(1, (int)ceil(count($bars) * 0.08));
            $nth = 0;
            $scale = array();
            foreach ($marks as $index => $label) {
                $scale[$index] = ($nth % $step === 0 && $index < $tail)
                    ? $label
                    : null;
                $nth++;
            }
            return $scale;
        };
        $scopes = array(
            array(
                'key' => 'timestamp',
                'label' => __('Attribute last modified'),
                'absent' => __('No occurrence here carries a'
                    . ' modification time.'),
                'note' => null,
            ),
            array(
                'key' => 'published',
                'label' => __('Event published'),
                'absent' => __('None of these occurrences sits on a'
                    . ' published event.'),
                /*
                 * An unpublished event has no publication date, so a cut
                 * on this drops those rows entirely. How many belongs
                 * beside the control rather than in the reader's head.
                 */
                'note' => empty($facets['published_unset'])
                    ? null
                    : sprintf(
                        __(
                            '%d occurrences sit on events that were'
                            . ' never published, and a date cut here'
                            . ' removes them.'
                        ),
                        $facets['published_unset']
                    ),
            ),
            /*
             * The event's own stamp, which is neither of the two above:
             * it moves when *any* attribute on the report changes, so it
             * answers "when was this report last worked on" where
             * `timestamp` answers "when was this occurrence last
             * touched". A row can be years old on a report edited this
             * morning.
             */
            array(
                'key' => 'event',
                'label' => __('Event last modified'),
                'absent' => __('No event behind these occurrences'
                    . ' carries a modification time.'),
                'note' => null,
            ),
        );
        /*
         * The first scope that has any dates at all, so the pane the
         * rail opens on is never the empty one while a working date sits
         * one entry down the list.
         */
        $timeScope = $scopes[0]['key'];
        foreach ($scopes as $scope) {
            if ($facets['time_spans'][$scope['key']] !== null) {
                $timeScope = $scope['key'];
                break;
            }
        }
        ?>
        <div class="vp-facetgrp">
            <div class="vp-subhead">
                <i class="fas fa-clock me-1"></i><?= __('Time') ?>
            </div>
            <?php
            /*
             * Not a `[data-vp-filter-key]`: this select picks which
             * question is being asked, and the shared list filter would
             * read its value as an answer and drop every row that does
             * not carry it as a facet token.
             */
            ?>
            <select class="form-select form-select-sm mb-2"
                    data-vp-time-scope
                    aria-label="<?= __('Filter rows on which date') ?>">
                <?php foreach ($scopes as $scope): ?>
                    <option value="<?= h($scope['key']) ?>"<?=
                        $scope['key'] === $timeScope ? ' selected' : '' ?>>
                        <?= h($scope['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php foreach ($scopes as $scope): ?>
                <?php
                $span = $facets['time_spans'][$scope['key']];
                $histogram = $facets['time_buckets'][$scope['key']];
                ?>
                <div data-vp-time-pane="<?= h($scope['key']) ?>"<?=
                    $scope['key'] === $timeScope ? '' : ' class="d-none"' ?>>
                    <?php if ($span === null): ?>
                        <?php
                        /*
                         * No row carries this date, so there is nothing
                         * to bound. A live-looking control over a column
                         * that is empty for every row is the one thing
                         * this page's rules rule out.
                         */
                        ?>
                        <div class="small text-muted">
                            <?= h($scope['absent']) ?>
                        </div>
                    <?php else: ?>
                        <?php
                        $caption = $histogram === null
                            ? sprintf(
                                __('%1$s to %2$s'),
                                $span['from'],
                                $span['to']
                            )
                            : sprintf(
                                __('%1$s · %2$s to %3$s'),
                                $grainWords[$histogram['unit']],
                                $span['from'],
                                $span['to']
                            );
                        ?>
                        <?php if ($histogram !== null): ?>
                            <?php
                            /*
                             * The same brush the History chart and the
                             * Sightings navigator use, over a strip of
                             * CSS bars rather than a canvas:
                             * `00-shared.md` §7 keeps bars as the
                             * standing exception to the Chart.js rule,
                             * and this needs to be a third the height of
                             * History's chart to sit in a `col-lg-3`
                             * rail above nine facet groups.
                             *
                             * Drag to pick a range, click to clear. The
                             * gesture writes the two date inputs below
                             * and fires their own `change`, so the
                             * window stays statable as two dates and one
                             * filter path runs whether the reader
                             * brushed or typed — which is the same
                             * reason the History chart sits directly
                             * above its own inputs.
                             */
                            ?>
                            <div class="vp-timebrush"
                                 data-vp-timebrush="<?=
                                     h($scope['key']) ?>">
                                <div class="vp-spark vp-spark-attribute
                                            vp-spark-flush"
                                     role="img"
                                     aria-label="<?= h(sprintf(
                                         __(
                                             'Occurrences by %1$s, %2$s.'
                                             . ' Drag to pick a range.'
                                         ),
                                         mb_strtolower($scope['label']),
                                         $grainWords[$histogram['unit']]
                                     )) ?>">
                                    <?php foreach (
                                        $histogram['bars'] as $bar
                                    ): ?>
                                        <span class="vp-spark-bar<?=
                                            $bar['count'] === 0
                                                ? ' vp-spark-bar-empty'
                                                : '' ?>"
                                              style="--vp-spark-h: <?=
                                                  h($histogram['max'] > 0
                                                      ? round(
                                                          $bar['count']
                                                          / $histogram['max']
                                                          * 100
                                                      )
                                                      : 0) ?>%"
                                              data-vp-bucket-from="<?=
                                                  h($bar['from']) ?>"
                                              data-vp-bucket-to="<?=
                                                  h($bar['to']) ?>"
                                              data-vp-bucket-label="<?=
                                                  h($bar['label']) ?>"
                                              data-vp-bucket-count="<?=
                                                  h($bar['count']) ?>">
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="vp-brush" data-vp-brush>
                                    <div class="vp-brush-mask"
                                         data-vp-brush-mask-left></div>
                                    <div class="vp-brush-window"
                                         data-vp-brush-handle></div>
                                    <div class="vp-brush-mask"
                                         data-vp-brush-mask-right></div>
                                </div>
                            </div>
                            <?php $scale = $timeScale($histogram); ?>
                            <?php if (!empty($scale)): ?>
                                <?php
                                /*
                                 * `aria-hidden`: the strip's own
                                 * accessible name says what the axis
                                 * is and the caption below states both
                                 * ends, so a screen reader walking
                                 * fifty-two bare slots between them is
                                 * reading the gridlines rather than
                                 * the chart.
                                 *
                                 * Outside `.vp-timebrush` rather than
                                 * in it: the brush layer covers that
                                 * box edge to edge, and an axis under
                                 * it would be both dimmed by the mask
                                 * and unreadable through the window.
                                 */
                                ?>
                                <div class="vp-spark-scale
                                            vp-timebrush-scale"
                                     aria-hidden="true">
                                    <?php foreach (
                                        $histogram['bars'] as $at => $bar
                                    ): ?>
                                        <span class="vp-spark-slot<?=
                                            array_key_exists($at, $scale)
                                                ? ' vp-spark-slot-tick'
                                                : '' ?>"><?php
                                            if (!empty($scale[$at])):
                                        ?><span class="vp-spark-tick"><?=
                                            h($scale[$at])
                                        ?></span><?php endif; ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="input-group input-group-sm">
                            <input type="date" class="form-control"
                                   data-vp-range-from="<?=
                                       h($scope['key']) ?>"
                                   min="<?= h($span['from']) ?>"
                                   max="<?= h($span['to']) ?>"
                                   aria-label="<?= h(sprintf(
                                       __('%s from'),
                                       $scope['label']
                                   )) ?>">
                            <span class="input-group-text">
                                <?= __('to') ?>
                            </span>
                            <input type="date" class="form-control"
                                   data-vp-range-to="<?=
                                       h($scope['key']) ?>"
                                   min="<?= h($span['from']) ?>"
                                   max="<?= h($span['to']) ?>"
                                   aria-label="<?= h(sprintf(
                                       __('%s to'),
                                       $scope['label']
                                   )) ?>">
                        </div>
                        <?php
                        /*
                         * States the grain, and names the bucket under
                         * the pointer while the reader is over the
                         * strip. A bar three pixels wide is not
                         * self-describing, and the brush layer sits on
                         * top of the bars so their own `title` never
                         * reaches the reader.
                         */
                        ?>
                        <div class="small text-muted mt-1"
                             data-vp-timebrush-caption="<?=
                                 h($scope['key']) ?>"
                             data-vp-caption-default="<?= h($caption) ?>">
                            <?= h($caption) ?>
                        </div>
                        <?php if ($scope['note'] !== null): ?>
                            <div class="small text-muted mt-1">
                                <?= h($scope['note']) ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($defined as $group): ?>
            <?= $this->element('Values/View/value_facet_group', array(
                'key' => $group['key'],
                'title' => $group['title'],
                'icon' => $group['icon'],
                'values' => $groups[$group['key']],
            )) ?>
        <?php endforeach; ?>

        <?php
        /*
         * Not a facet list: first_seen and last_seen are a span, and the
         * question a reader asks of them is "when was this live", which
         * a set of checkboxes cannot express. The bars are the density
         * of those spans over the value's lifetime.
         */
        ?>
        <div class="vp-facetgrp">
            <div class="vp-subhead"><?= __('First / last seen') ?></div>
            <div class="d-flex flex-column gap-2">
                <?php if (!empty($spark)): ?>
                    <div class="vp-spark vp-spark-attribute"
                         role="img"
                         aria-label="<?= h(sprintf(
                             __('Occurrences seen between %1$s and %2$s'),
                             $facets['seen_from'],
                             $facets['seen_to']
                         )) ?>">
                        <?php foreach ($spark as $bucket): ?>
                            <span class="vp-spark-bar<?=
                                $bucket === 0 ? ' vp-spark-bar-empty' : '' ?>"
                                  style="--vp-spark-h: <?=
                                      h(round(($bucket / $sparkMax) * 100))
                                  ?>%">
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="input-group input-group-sm"
                         title="<?= h($seenDisabled) ?>">
                        <input type="date" class="form-control"
                               value="<?= h($facets['seen_from']) ?>"
                               aria-label="<?= __('Seen from') ?>" disabled>
                        <span class="input-group-text"><?= __('to') ?></span>
                        <input type="date" class="form-control"
                               value="<?= h($facets['seen_to']) ?>"
                               aria-label="<?= __('Seen to') ?>" disabled>
                    </div>
                <?php endif; ?>
                <?php if (!empty($facets['seen_unset'])): ?>
                    <?php
                    /*
                     * `first_seen` and `last_seen` are optional, so a
                     * date cut silently drops whatever never had one.
                     * How many that is belongs beside the control.
                     */
                    ?>
                    <div class="small text-muted">
                        <?= h(sprintf(
                            __(
                                '%d occurrences carry no first/last'
                                . ' seen at all.'
                            ),
                            $facets['seen_unset']
                        )) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($hasState): ?>
            <?php
            /*
             * Written out rather than driven by `value_facet_group`,
             * because this group is the one that mixes a facet with a
             * reveal: filtering *to* soft-deleted rows and including
             * them alongside the rest are different questions, and the
             * design only ever asks the second.
             */
            ?>
            <div class="vp-facetgrp">
                <div class="vp-subhead"><?= __('Row state') ?></div>

                <?php foreach ($groups['state'] as $index => $facet): ?>
                    <label class="vp-facet">
                        <input type="checkbox" class="form-check-input"
                               data-vp-facet-key="state"
                               value="<?= h($facet['value']) ?>"
                               id="vp-facet-state-<?= h($index) ?>">
                        <span class="vp-facet-label">
                            <?= h($facet['label']) ?>
                        </span>
                        <span class="vp-facet-count">
                            <?= h($facet['count']) ?>
                        </span>
                        <span class="vp-facet-bar"
                              style="--vp-facet-share: 100%"></span>
                    </label>
                <?php endforeach; ?>

                <?php if (!empty($facets['deleted'])): ?>
                    <?php
                    /*
                     * Included by default, unlike the Overview preview:
                     * that panel shows the value's current state, this
                     * tab is the whole table. The header's "showing n"
                     * counts these rows, so hiding them by default
                     * would make the header disagree with the tbody.
                     */
                    ?>
                    <div class="form-check form-switch mt-2 mb-0">
                        <input class="form-check-input" type="checkbox"
                               role="switch" data-vp-reveal="deleted"
                               id="vp-occ-reveal-deleted" checked>
                        <label class="form-check-label small text-muted"
                               for="vp-occ-reveal-deleted">
                            <?= h(sprintf(
                                __('Include %d soft-deleted'),
                                $facets['deleted']
                            )) ?>
                        </label>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>
