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

$spark = $facets['seen_spark'];
$sparkMax = max(1, max($spark));

/*
 * A date range over a column that is frequently empty needs to say so,
 * and the filter itself is not wired in this pass — §9 lists what does
 * work, and this is not on it. Inert-but-live-looking is the one thing
 * a page of honest states cannot afford.
 */
$seenDisabled = __(
    'Date filtering is not wired in this pass — the sparkline and the'
    . ' counts above describe the same set of rows.'
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

    <div class="p-3 pb-0">
        <div class="vp-facet-note">
            <?= sprintf(
                __(
                    'Counts cover the %1$s. The banner counts all %2$s — it'
                    . ' says %3$s, this rail says %4$s.'
                ),
                '<strong>' . h(sprintf(
                    __('%d occurrences you can see'),
                    $facets['visible']
                )) . '</strong>',
                h($facets['total']),
                '<strong>' . h(sprintf(
                    '%s %d',
                    $facets['banner_note']['chip'],
                    $facets['banner_note']['banner']
                )) . '</strong>',
                '<strong>' . h($facets['banner_note']['rail']) . '</strong>'
            ) ?>
        </div>
    </div>

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
         * Not a facet list: first_seen and last_seen are a span, and the
         * question a reader asks of them is "when was this live", which
         * a set of checkboxes cannot express. The bars are the density
         * of those spans over the value's lifetime.
         */
        ?>
        <div class="vp-facetgrp">
            <div class="vp-subhead"><?= __('First / last seen') ?></div>
            <div class="d-flex flex-column gap-2">
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
                                  h(round(($bucket / $sparkMax) * 100)) ?>%">
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
