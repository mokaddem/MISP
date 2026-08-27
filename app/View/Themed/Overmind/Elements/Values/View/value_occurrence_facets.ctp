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
    array(
        'key' => 'tag',
        'title' => __('Tag'),
        'icon' => 'misp-icon misp-icon-tag misp-simple',
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
         * Two dates a reader genuinely cuts on, and they are different
         * questions: `timestamp` is when somebody last touched the
         * attribute, `publish_timestamp` is when its event was last
         * released. An occurrence edited yesterday on an event published
         * last year is a different thing from the reverse, and neither
         * is answerable from the other.
         *
         * Ranges rather than facet checkboxes because a date has no
         * vocabulary to tick, and both are wired — unlike the
         * first/last-seen control below, which needs an overlap test
         * rather than a point test and stays disabled.
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
        $ranges = array(
            array(
                'key' => 'timestamp',
                'label' => __('Attribute last modified'),
                'span' => $facets['time_spans']['timestamp'],
                'buckets' => $facets['time_buckets']['timestamp'],
                'absent' => __('No occurrence here carries a'
                    . ' modification time.'),
                'note' => null,
            ),
            array(
                'key' => 'published',
                'label' => __('Event published'),
                'span' => $facets['time_spans']['published'],
                'buckets' => $facets['time_buckets']['published'],
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
        );
        ?>
        <div class="vp-facetgrp">
            <div class="vp-subhead">
                <i class="fas fa-clock me-1"></i><?= __('Time') ?>
            </div>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($ranges as $range): ?>
                    <?php if ($range['span'] === null): ?>
                        <?php
                        /*
                         * No row carries this date, so there is nothing
                         * to bound. A live-looking control over a column
                         * that is empty for every row is the one thing
                         * this page's rules rule out.
                         */
                        ?>
                        <div>
                            <div class="small text-muted mb-1">
                                <?= h($range['label']) ?>
                            </div>
                            <div class="small text-muted">
                                <?= h($range['absent']) ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php
                        $histogram = $range['buckets'];
                        $caption = $histogram === null
                            ? sprintf(
                                __('%1$s to %2$s'),
                                $range['span']['from'],
                                $range['span']['to']
                            )
                            : sprintf(
                                __('%1$s · %2$s to %3$s'),
                                $grainWords[$histogram['unit']],
                                $range['span']['from'],
                                $range['span']['to']
                            );
                        ?>
                        <div>
                            <div class="small text-muted mb-1">
                                <?= h($range['label']) ?>
                            </div>

                            <?php if ($histogram !== null): ?>
                                <?php
                                /*
                                 * The same brush the History chart and
                                 * the Sightings navigator use, over a
                                 * strip of CSS bars rather than a
                                 * canvas: `00-shared.md` §7 keeps bars
                                 * as the standing exception to the
                                 * Chart.js rule, and this needs to be a
                                 * third the height of History's chart to
                                 * sit in a `col-lg-3` rail beside eight
                                 * other groups.
                                 *
                                 * Drag to pick a range, click to clear.
                                 * The gesture writes the two date inputs
                                 * below and fires their own `change`, so
                                 * the window stays statable as two dates
                                 * and one filter path runs whether the
                                 * reader brushed or typed — which is the
                                 * same reason the History chart sits
                                 * directly above its own inputs.
                                 */
                                ?>
                                <div class="vp-timebrush"
                                     data-vp-timebrush="<?=
                                         h($range['key']) ?>">
                                    <div class="vp-spark vp-spark-attribute
                                                vp-spark-flush"
                                         role="img"
                                         aria-label="<?= h(sprintf(
                                             __(
                                                 'Occurrences by %1$s,'
                                                 . ' %2$s. Drag to pick a'
                                                 . ' range.'
                                             ),
                                             mb_strtolower($range['label']),
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
                            <?php endif; ?>

                            <div class="input-group input-group-sm">
                                <input type="date" class="form-control"
                                       data-vp-range-from="<?=
                                           h($range['key']) ?>"
                                       min="<?= h($range['span']['from']) ?>"
                                       max="<?= h($range['span']['to']) ?>"
                                       aria-label="<?= h(sprintf(
                                           __('%s from'),
                                           $range['label']
                                       )) ?>">
                                <span class="input-group-text">
                                    <?= __('to') ?>
                                </span>
                                <input type="date" class="form-control"
                                       data-vp-range-to="<?=
                                           h($range['key']) ?>"
                                       min="<?= h($range['span']['from']) ?>"
                                       max="<?= h($range['span']['to']) ?>"
                                       aria-label="<?= h(sprintf(
                                           __('%s to'),
                                           $range['label']
                                       )) ?>">
                            </div>
                            <?php
                            /*
                             * States the grain, and names the bucket
                             * under the pointer while the reader is over
                             * the strip. A bar three pixels wide is not
                             * self-describing, and the brush layer sits
                             * on top of the bars so their own `title`
                             * never reaches the reader.
                             */
                            ?>
                            <div class="small text-muted mt-1"
                                 data-vp-timebrush-caption="<?=
                                     h($range['key']) ?>"
                                 data-vp-caption-default="<?=
                                     h($caption) ?>">
                                <?= h($caption) ?>
                            </div>
                            <?php if ($range['note'] !== null): ?>
                                <div class="small text-muted mt-1">
                                    <?= h($range['note']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

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
