<?php
/**
 * The individual sightings, newest first, following the chart's brush.
 *
 * `Reported against` is the column a value-scoped list cannot do
 * without. Ten occurrences of one value can each be sighted separately,
 * and a flat list of forty-seven reports with no occurrence column
 * silently merges them into one thing that was seen forty-seven times.
 *
 * `Source` prints an em dash far more often than it prints a string,
 * which is worth showing rather than hiding: it is how a reader learns
 * that MISP's sighting source is optional and mostly unused, instead of
 * assuming this instance has lost it.
 *
 * The count in the header is the viewer's. `Plugin.Sightings_policy` can
 * hide whole sightings on events another organisation owns, so this list
 * is what you may see and not what the instance holds — said in words at
 * the foot of the panel rather than left for the reader to discover by
 * comparing two pages.
 *
 * Lazily loaded from ValuesController::viewSightingList.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$rows = array_reverse($valueProfile['sighting_rows']);
$series = $valueProfile['sighting_series'];
$total = count($rows);
$pageSize = 10;

/*
 * Keyed by `Sighting::TYPE`'s own ints. The labels are spelt out rather
 * than taken from that constant, which stores `false-positive` for the
 * API's benefit and reads as a slug in a table cell.
 */
$types = array(
    0 => array(
        'label' => __('sighting'),
        'class' => 'vp-sight-badge-yes',
    ),
    1 => array(
        'label' => __('false positive'),
        'class' => 'vp-sight-badge-no',
    ),
    2 => array(
        'label' => __('expiration'),
        'class' => 'vp-sight-badge-exp',
    ),
);

$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

$exportButton = '<button type="button"'
    . ' class="btn btn-sm btn-outline-dark" disabled title="'
    . h($noWrites) . '">'
    . '<i class="fas fa-file-export me-1"></i>'
    . h(__('Export selection')) . '</button>';

$subtitle = $total === 0
    ? h(__('No individual sighting to list'))
    : '<span data-vp-sight-in-range>' . h($total) . '</span> '
        . h(__('in the selected range')) . ' · '
        . h(sprintf(__('%s in total'), $total));
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--sighting);"
     data-vp-sight-list
     data-vp-sight-page-size="<?= (int)$pageSize ?>">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Individual sightings'),
        'panelIcon' => 'misp-icon misp-icon-sighting misp-simple',
        'panelColor' => 'var(--sighting)',
        'panelSub' => $subtitle,
        'panelExtra' => $exportButton,
    )) ?>

    <?php if ($total === 0): ?>

        <div class="p-3">
            <div class="vp-empty">
                <span class="misp-icon misp-icon-sighting misp-simple"></span>
                <span><?= __('Nobody has reported seeing this.') ?></span>
            </div>
        </div>

    <?php else: ?>

        <?php
        /*
         * The same primitive the Overview's type filter uses, driven
         * here by the navigator rather than by a chip. It stays hidden
         * until the brush actually narrows something: a note that
         * always says "showing everything" trains the reader to stop
         * reading it.
         */
        ?>
        <div class="vp-filter-note d-none" data-vp-sight-range-note>
            <i class="fas fa-filter"></i>
            <span>
                <?= h(__('Range')) ?>
                <strong data-vp-sight-range-from></strong>
                →
                <strong data-vp-sight-range-to></strong>
                ·
                <strong data-vp-sight-range-shown></strong>
                <?= h(sprintf(__('of %s sightings'), $total)) ?>
            </span>
            <button type="button" class="vp-filter-clear ms-auto"
                    data-vp-sight-clear>
                <?= __('Clear') ?>
            </button>
        </div>

        <div class="table-responsive" data-vp-sight-rows>
            <table class="table table-sm align-middle vp-table mb-0">
                <thead>
                    <tr>
                        <th><?= __('Organisation') ?></th>
                        <th><?= __('Source') ?></th>
                        <th><?= __('Date') ?></th>
                        <th><?= __('Type') ?></th>
                        <th><?= __('Reported against') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                        <?php $type = $types[$row['type']]; ?>
                        <tr data-vp-sight-date="<?=
                                h(substr($row['date'], 0, 10)) ?>"
                            class="<?= $index >= $pageSize ? 'd-none' : '' ?>">
                            <td class="fw-semibold">
                                <?= h($row['org']) ?>
                            </td>
                            <td>
                                <?php if ($row['source'] === null): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <?= h($row['source']) ?>
                                <?php endif; ?>
                            </td>
                            <td class="font-monospace text-nowrap">
                                <?= h($row['date']) ?>
                            </td>
                            <td>
                                <span class="vp-sight-badge <?=
                                    h($type['class']) ?>">
                                    <?= h($type['label']) ?>
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <span class="vp-sight-against">
                                    <?= h(sprintf(
                                        __('Event %s'),
                                        $row['against']['event']
                                    )) ?>
                                </span>
                                <span class="vp-sight-against-type">
                                    <?= h($row['against']['type']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        /*
         * Only a brush can produce this. A value with no sightings at
         * all has its own empty state above, and "no sightings in this
         * range" over it would be a different and false claim.
         */
        ?>
        <div class="p-3 d-none" data-vp-sight-empty>
            <div class="vp-empty vp-empty-inline">
                <span class="misp-icon misp-icon-sighting misp-simple"></span>
                <span>
                    <?= __('No sighting was reported in the range you'
                        . ' selected.') ?>
                </span>
            </div>
        </div>

        <div class="vp-sight-foot">
            <span>
                <?= h(__('Showing')) ?>
                <strong data-vp-sight-shown><?=
                    h(min($pageSize, $total)) ?></strong>
                <?= h(__('of')) ?>
                <strong data-vp-sight-of><?= h($total) ?></strong>
            </span>
            <button type="button" class="vp-filter-clear"
                    data-vp-sight-more
                    <?= $total <= $pageSize ? 'hidden' : '' ?>>
                <?= __('load the rest') ?>
            </button>
        </div>

        <div class="vp-acl-note vp-acl-note-band">
            <i class="fas fa-user-shield"></i>
            <span><?= h($valueProfile['sighting_notes']['policy']) ?></span>
        </div>

    <?php endif; ?>

</div>
