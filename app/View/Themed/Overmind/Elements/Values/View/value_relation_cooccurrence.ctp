<?php
/**
 * Section one of the Relationships tab: what the correlation engine
 * stored about this value.
 *
 * Machine-derived, statistical, and the only section that grows without
 * bound — so it is the only one that carries `R3`'s narrowing bar and
 * pages rather than caps. The cut is always stated in words above the
 * rows: a table that silently shows the top eight of 1,462 is a table
 * that lies by omission.
 *
 * Three things share this panel and are not the same thing:
 *
 *   object siblings   a join on `Attribute.object_id` over occurrences
 *                     the page already holds. Structural, not
 *                     statistical, and bounded by how many objects the
 *                     value sits in rather than by how large its events
 *                     are — which is why it still renders under a
 *                     suppressed band.
 *   the roll-ups      the neighbourhood, counted three ways.
 *   the facet bar     folded from every row read, not from the page.
 *                     Its counts do not move when the list pages, and
 *                     saying so is the whole reason the bar is here.
 *
 * **Not the correlation engine.** A `default_correlations` row links
 * two attributes carrying the *same* value, so for one value the engine
 * returns other occurrences of it — the Occurrences tab — and its
 * CIDR/ssdeep partners, which are the section below. It never returns a
 * different value. What is counted here is an event join, and the
 * engine's own state is reported on the rail instead.
 * prd/value-profile-live/24-relationships.md §3.
 *
 * Lazily loaded from ValuesController::viewRelationCooccurrence.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$profile = $valueProfile;
$relations = $profile['relationships'];
$co = $relations['cooccurrence'];
$summary = $relations['summary'];
$siblings = $co['siblings'];
$facets = $co['facets'];
/*
 * What the panel read, so it can say so. The fixture carries no scan —
 * it never had to choose which events to read — so the keys are
 * defaulted and the scan line is skipped on a fixture-driven render
 * rather than printing zeroes at the reader.
 */
$scanned = isset($co['scan']);
$scan = $co['scan'] ?? array(
    'events_read' => 0,
    'events_seen' => 0,
    'events_oversized' => 0,
    'events_unread' => 0,
    'size_cap' => 0,
    'budget' => 0,
    'rows_read' => 0,
);

$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

$view = $this;

/**
 * @param string $text
 * @return string
 */
$slug = function ($text) {
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
};

/*
 * The objects this value is itself part of, taken from the sibling
 * join rather than guessed: a correlated value sitting in a `file`
 * object is not a sibling of ours unless we are in that same object.
 */
$siblingObjects = array();
foreach ($siblings['rows'] as $sibling) {
    $siblingObjects[$sibling['object']] = true;
}

/**
 * The tokens the facet bar and the filter row match on.
 *
 * @param array $row
 * @return string
 */
$valueTokens = function ($row) use ($slug, $siblingObjects) {
    $tokens = array(
        'type:' . $slug($row['type']),
        'category:' . $slug($row['category']),
        'distribution:' . (int)$row['distribution'],
    );
    foreach ($row['orgs'] as $org) {
        $tokens[] = 'organisation:' . $slug($org);
    }
    foreach ($row['events'] as $event) {
        $tokens[] = 'event:' . $event;
    }
    foreach ($row['tags'] as $tag) {
        $tokens[] = 'tag:' . $slug($tag['name']);
    }
    if ($row['object'] !== null) {
        $tokens[] = 'object:' . $slug($row['object']);
        if (isset($siblingObjects[$row['object']])) {
            $tokens[] = 'sibling:yes';
        }
    }
    return implode(' ', $tokens);
};

/**
 * The same, for the sibling table, whose keys are prefixed so the two
 * bars in this panel cannot read each other's ticks — `type` there and
 * `type` here narrow different row sets.
 *
 * @param array $sibling
 * @return string
 */
$siblingTokens = function ($sibling) use ($slug) {
    $tokens = array(
        'sibobject:' . $slug($sibling['object']),
        'sibtype:' . $slug($sibling['type']),
    );
    if ($sibling['relation'] !== '') {
        $tokens[] = 'sibrelation:' . $slug($sibling['relation']);
    }
    foreach ($sibling['orgs'] as $org) {
        $tokens[] = 'siborg:' . $slug($org);
    }
    return implode(' ', $tokens);
};

/**
 * A row's two orderings, as numbers the sort can read without parsing
 * a date out of a cell.
 *
 * @param int $weight
 * @param string $date
 * @return string
 */
$numbers = function ($weight, $date) {
    return 'shared:' . (int)$weight
        . ' recent:' . str_replace('-', '', substr($date, 0, 10));
};

/**
 * MISP's own distribution badge, so the level is never printed as the
 * bare integer it is stored as.
 *
 * @param array $row
 * @return string
 */
$distributionBadge = function ($row) use ($view) {
    return $view->element(
        'genericElementsBS5/Badges/distribution',
        array('distribution' => (int)$row['distribution'], 'full' => false)
    );
};

/**
 * @param array $tags
 * @return string
 */
$tagChips = function ($tags) use ($view) {
    if (empty($tags)) {
        return '<span class="text-muted">&mdash;</span>';
    }
    $out = '';
    foreach ($tags as $tag) {
        $out .= $view->element(
            'genericElementsBS5/Badges/tag',
            array(
                'tag' => $tag,
                'local' => false,
                'hiddenClass' => '',
                'showFavourite' => false,
            )
        );
    }
    return $out;
};

/**
 * The type, through MISP's own badge, re-flowed for a dense row by
 * `.vp-rel-type` rather than by a second rendering of the same fact.
 *
 * @param string $type
 * @return string
 */
$typeBadge = function ($type) use ($view) {
    return '<span class="vp-rel-type">'
        . $view->element(
            'genericElementsBS5/Badges/type',
            array('type' => $type, 'full' => true)
        )
        . '</span>';
};

/**
 * A weight, on the page's own bar. Strengths are only ever compared
 * inside one roll-up, never across the three notions.
 *
 * @param int $weight
 * @param int $max
 * @return string
 */
$weightBar = function ($weight, $max) {
    $share = $max > 0 ? round(($weight / $max) * 100) : 0;
    return '<span class="vp-rel-bar"'
        . ' style="--vp-seg-color: var(--vp-rel-co);">'
        . '<span class="vp-weight-track"><span class="vp-weight-fill"'
        . ' style="width: ' . h($share) . '%;"></span></span>'
        . '<span class="vp-rel-bar-read">' . h(number_format($weight))
        . '</span></span>';
};

/*
 * The six groups, in the order the bar prints them. A key the fixture
 * left out renders nothing at all, which is what `value_facet_group`
 * already enforces for a group of zeroes.
 */
$facetGroups = array(
    array('key' => 'event', 'title' => __('Event'),
        'icon' => 'misp-icon misp-icon-event misp-simple'),
    array('key' => 'organisation', 'title' => __('Organisation'),
        'icon' => 'fas fa-building'),
    array('key' => 'type', 'title' => __('Type'),
        'icon' => 'misp-icon misp-icon-attribute misp-simple'),
    array('key' => 'object', 'title' => __('Object'),
        'icon' => 'misp-icon misp-icon-object misp-simple'),
    array('key' => 'tag', 'title' => __('Tag'),
        'icon' => 'misp-icon misp-icon-tag misp-simple'),
    array('key' => 'distribution', 'title' => __('Distribution'),
        'icon' => 'fas fa-globe'),
);

/*
 * The sibling table's own bar. Four keys and not six: a sibling row
 * has no tag column and no single distribution to name, and Relation
 * is the dimension that only exists here — it is what separates the
 * `domain` in a `domain-ip` object from the timestamps beside it.
 */
$sibFacets = isset($siblings['facets']) ? $siblings['facets'] : array();
$sibFacetGroups = array(
    array('key' => 'sibobject', 'title' => __('Object'),
        'icon' => 'misp-icon misp-icon-object misp-simple'),
    array('key' => 'sibrelation', 'title' => __('Relation'),
        'icon' => 'fas fa-diagram-project'),
    array('key' => 'sibtype', 'title' => __('Type'),
        'icon' => 'misp-icon misp-icon-attribute misp-simple'),
    array('key' => 'siborg', 'title' => __('Reported by'),
        'icon' => 'fas fa-building'),
);

/*
 * A distribution row carries MISP's own badge and a tag row its own
 * chip, because `value_facet_group` renders `html` where a caller
 * supplies one and the bare `label` otherwise — and neither of those
 * two facets has a label to fall back on. The Occurrences rail has
 * built these since phase 22; this pane never did, so its Distribution
 * and Tag dropdowns have been rendering rows with an empty name and a
 * count beside it since phase 11.
 */
foreach ($facets['distribution'] as &$facet) {
    $facet['html'] = $this->element(
        'genericElementsBS5/Badges/distribution',
        array('distribution' => (int)$facet['level'], 'full' => true)
    );
}
unset($facet);
foreach ($facets['tag'] as &$facet) {
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

$valueRows = $co['rollups']['value']['rows'];
$eventRows = $co['rollups']['event']['rows'];
$objectRows = $co['rollups']['object']['rows'];
$hasRows = !empty($valueRows);

$maxShared = 0;
foreach ($valueRows as $row) {
    $maxShared = max($maxShared, (int)$row['shared_events']);
}
$maxEventShared = 0;
foreach ($eventRows as $row) {
    $maxEventShared = max($maxEventShared, (int)$row['shared_values']);
}
$maxObjectValues = 0;
foreach ($objectRows as $row) {
    $maxObjectValues = max($maxObjectValues, (int)$row['values']);
}

ob_start();
?>
    <select class="form-select form-select-sm w-auto" data-vp-sort
            aria-label="<?= __('Rank the rows') ?>">
        <option value="shared"><?= __('Most shared first') ?></option>
        <option value="recent"><?= __('Most recent first') ?></option>
    </select>
    <select class="form-select form-select-sm w-auto" data-vp-group
            aria-label="<?= __('Roll the neighbourhood up by') ?>">
        <option value="value"><?= __('Group by value') ?></option>
        <option value="event"><?= __('Group by event') ?></option>
        <option value="object"><?= __('Group by object') ?></option>
    </select>
<?php
$headerExtra = ob_get_clean();
if (!$hasRows) {
    // Ranking and rolling up nothing is a control that cannot do
    // anything, which is worse than one that is absent.
    $headerExtra = null;
}

ob_start();
?>
    <span class="vp-rel-tag me-1">
        <i class="fas fa-link"></i><?= h(__('Co-occurrence')) ?>
    </span>
    <?php if ($co['suppressed'] && $scanned): ?>
        <?= h(__n(
            'the one event this value is in is too large to read',
            'all %d events this value is in are too large to read',
            $scan['events_seen'],
            $scan['events_seen']
        )) ?>
    <?php elseif ($co['suppressed']): ?>
        <?= h(sprintf(
            __('%s recorded occurrences · no correlation stored'),
            number_format($summary['recorded'])
        )) ?>
    <?php elseif ($hasRows): ?>
        <span data-vp-list-shown><?= h(count($valueRows)) ?></span>
        <?= h(sprintf(
            __n(
                'of %1$s distinct values in %2$d event',
                'of %1$s distinct values across %2$d events',
                $co['events'],
                number_format($co['distinct_values']),
                $co['events']
            )
        )) ?>
    <?php else: ?>
        <?= h(__('Nothing else in the events this value is in')) ?>
    <?php endif; ?>
    &nbsp;·&nbsp;<?= h(__('shared events')) ?>&nbsp;·&nbsp;
    <span class="vp-rel-prov"><i class="fas fa-gauge"></i><?=
        h(__('Machine-derived')) ?></span>
<?php
$headerSub = ob_get_clean();
?>
<div class="card shadow-sm mb-3 vp-panel vp-rel-k-co"
     style="--vp-panel-color: var(--vp-rel-co);"
     data-vp-list
     data-vp-group-active="value">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Co-occurrence'),
        'panelIcon' => 'fas fa-link',
        'panelColor' => 'var(--vp-rel-co)',
        'panelSub' => $headerSub,
        'panelExtra' => $headerExtra,
    )) ?>

    <?php if ($co['suppressed']): ?>

        <?php
        /*
         * Not an empty state — the opposite claim, and one a reader who
         * saw an empty table would get exactly backwards. There is a
         * neighbourhood; it is in events so large that reading one
         * would say nothing about this value in particular.
         */
        ?>
        <div class="vp-suppressed">
            <i class="fas fa-circle-exclamation"></i>
            <div>
                <span class="vp-suppressed-badge">
                    <?= $scanned
                        ? __('Too large to read')
                        : __('Suppressed by MISP') ?>
                </span>
                <?php if ($scanned): ?>
                    <div class="mt-2">
                        <?= sprintf(
                            __(
                                '%1$s holds more than %2$s attributes. In'
                                . ' an event that size every value'
                                . ' co-occurs with every other, so a'
                                . ' neighbour list drawn from one would'
                                . ' describe the event rather than this'
                                . ' value — and this panel does not draw'
                                . ' one.'
                            ),
                            '<strong>' . h(__n(
                                'The one event this value appears in',
                                'Every one of the %d events this value'
                                    . ' appears in',
                                $scan['events_seen'],
                                $scan['events_seen']
                            )) . '</strong>',
                            '<strong>' . h(number_format(
                                $scan['size_cap']
                            )) . '</strong>'
                        ) ?>
                    </div>
                    <div class="mt-2">
                        <?= h(__(
                            'Nothing is hidden from you here and nothing'
                            . ' is missing. The object siblings below sit'
                            . ' in those same events and are listed in'
                            . ' full, because an object is a statement'
                            . ' somebody made about which attributes'
                            . ' belong together — it does not get larger'
                            . ' because the event around it did.'
                        )) ?>
                    </div>
                <?php else: ?>
                    <div class="mt-2">
                        <?= sprintf(
                            __(
                                'This value occurs %1$s times — past'
                                . ' %2$s, which is %3$d. MISP stored'
                                . ' %4$s and recorded the value in'
                                . ' %5$s instead.'
                            ),
                            '<strong>' . h(number_format(
                                $summary['recorded']
                            )) . '</strong>',
                            '<code>MISP.correlation_limit</code>',
                            $relations['settings']['correlation_limit'],
                            '<strong>' . h(__('no correlation at all'))
                                . '</strong>',
                            '<code>over_correlating_values</code>'
                        ) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif (!$hasRows): ?>

        <div class="p-3">
            <div class="vp-empty">
                <i class="fas fa-link"></i>
                <span>
                    <?= __('No correlation the engine has stored for'
                        . ' this value.') ?>
                </span>
            </div>
        </div>

    <?php endif; ?>

    <?php if (!empty($siblings['rows'])): ?>

        <?php
        /*
         * Listed above the ranked table because it is the only
         * co-occurrence here that is structural rather than
         * statistical, and because it survives everything: the object
         * join reads attributes rather than correlations, so it is
         * unaffected by the correlation limit that suppressed the
         * section above it.
         *
         * Its own `data-vp-list` rather than a second control inside
         * the panel's: it pages on a different set of rows than the
         * ranked table, and one pager over the union of the two would
         * print a range belonging to neither.
         *
         * The section scales on how many objects the value sits in,
         * which is a function of the occurrence count and not of the
         * correlation count — so it can be the longest thing on the
         * tab on exactly the value whose correlations were suppressed.
         * Everything below states its own bound for that reason.
         */
        $sibCapped = !empty($siblings['cap']['applied']);
        // A count taken over a capped join is a floor, and says so.
        $sibFloor = $sibCapped ? '≥&nbsp;' : '';
        ?>
        <div data-vp-list>

            <div class="px-3 pt-3">
                <div class="vp-subhead d-flex align-items-center gap-2">
                    <span class="misp-icon misp-icon-object
                                 misp-simple"></span>
                    <?= __('Object siblings — the same object, other'
                        . ' relations') ?>
                    <?php
                    /*
                     * The value's own number of distinct siblings, not
                     * the number of rows this fragment carries. The
                     * badge used to print the row count and read as a
                     * total, which is the defect this section is here
                     * to fix.
                     */
                    ?>
                    <span class="badge text-bg-secondary">
                        <?= $sibFloor ?><?=
                            h(number_format($siblings['total'])) ?>
                    </span>
                </div>
                <?php
                /*
                 * Two lines, and the order is the point. A reader who
                 * has never met a MISP object needs to be told what one
                 * is and why its contents are worth their attention
                 * before being told how the rows were folded; the
                 * provenance sentence that used to open this said
                 * neither. It keeps its place underneath, because
                 * *not a correlation* is what stops the section being
                 * read as engine output.
                 */
                ?>
                <div class="small mb-1">
                    <?= __('A MISP object groups the attributes that'
                        . ' describe one thing — a file with its hashes'
                        . ' and filename, a domain with the address it'
                        . ' resolved to, one network connection. This is'
                        . ' what else was recorded beside this value'
                        . ' inside those objects: the same subject,'
                        . ' described further, and usually the first'
                        . ' place to pivot.') ?>
                </div>
                <div class="small text-muted mb-2">
                    <?= __('One row per object template, relation and'
                        . ' value — a sibling seen in many objects is a'
                        . ' single row, and Objects says how many. Read'
                        . ' from the objects themselves rather than from'
                        . ' the correlation engine, so it still answers'
                        . ' when correlation is suppressed.') ?>
                </div>
            </div>

            <?php if ($sibCapped): ?>
                <?php
                /*
                 * §9.6's resolution, stated rather than inferred. The
                 * aggregate is over the viewer's whole occurrence set
                 * and not over the Occurrences tab's current page, so
                 * it needs a ceiling — and an aggregate over a
                 * truncated set that does not say so is the one thing
                 * this section must not produce.
                 */
                ?>
                <div class="vp-rel-cap">
                    <i class="fas fa-circle-info"></i>
                    <span>
                        <?= sprintf(
                            __(
                                'Aggregated over %1$s of the %2$s objects'
                                . ' this value sits in, most recent'
                                . ' first. Every count in this section'
                                . ' is a floor rather than a total, and'
                                . ' is written %3$s to say so.'
                            ),
                            '<strong>' . h(number_format(
                                $siblings['objects']
                            )) . '</strong>',
                            '<strong>' . h(number_format(
                                $siblings['in_objects']
                            )) . '</strong>',
                            '<code>&ge;</code>'
                        ) ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php
            /*
             * The section's own narrowing bar, inside its own
             * `[data-vp-list]`. Phase 18 left this out and said what it
             * would cost: the facet lookups had to be scoped the way
             * paging already was, which `ownNodes` in `value-profile.js`
             * now does for every control a list reads.
             *
             * It is a separate bar and not a seventh dropdown on the one
             * above because the two fold different row sets — that one
             * from the events the scan read, this one from the objects
             * the value sits in — and a single bar would print counts
             * that are exact for neither.
             */
            ?>
            <?php $sibHasFacets = false; ?>
            <?php foreach ($sibFacetGroups as $group) {
                if (!empty($sibFacets[$group['key']])) {
                    $sibHasFacets = true;
                }
            } ?>
            <?php if ($sibHasFacets): ?>
                <div class="px-3 pb-2 d-flex flex-wrap gap-2
                            align-items-center">
                    <span class="vp-subhead mb-0 me-1"><?=
                        __('Narrow by') ?></span>

                    <?php foreach ($sibFacetGroups as $group): ?>
                        <?php if (empty($sibFacets[$group['key']])) {
                            continue;
                        } ?>
                        <div class="dropdown">
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary
                                           dropdown-toggle vp-rel-facet"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="outside"
                                    aria-expanded="false">
                                <?= h($group['title']) ?>
                                <span class="badge text-bg-secondary ms-1">
                                    <?= h(count($sibFacets[$group['key']])) ?>
                                </span>
                            </button>
                            <div class="dropdown-menu vp-rel-facetmenu p-2">
                                <?= $this->element(
                                    'Values/View/value_facet_group',
                                    array(
                                        'key' => $group['key'],
                                        'title' => $group['title'],
                                        'icon' => $group['icon'],
                                        'values' => $sibFacets[$group['key']],
                                    )
                                ) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <span class="small text-muted ms-2 vp-min-w-0">
                        <?= sprintf(
                            __(
                                'Counts are folded from all %1$s siblings,'
                                . ' not from the page. A count larger than'
                                . ' the table can show means the value it'
                                . ' names is outside the %2$s carried.'
                            ),
                            '<span class="font-monospace">'
                                . h(number_format($siblings['total']))
                                . '</span>',
                            '<span class="font-monospace">'
                                . h(number_format(
                                    count($siblings['rows'])
                                )) . '</span>'
                        ) ?>
                    </span>

                    <span class="small text-muted ms-auto"
                          data-vp-facet-summary>
                        <span class="vp-facet-summary-none">
                            <?= __('No filter applied') ?>
                        </span>
                        <span class="vp-facet-summary-some">
                            <span data-vp-facet-count-active>0</span>
                            <?= __('filters') ?>
                            &middot;
                            <span data-vp-facet-rows><?=
                                h(count($siblings['rows'])) ?></span>
                            <?= __('rows') ?>
                        </span>
                    </span>

                    <button type="button" class="btn btn-sm btn-link"
                            data-vp-facet-clear disabled>
                        <?= __('Reset') ?>
                    </button>
                </div>
            <?php endif; ?>

            <div class="table-responsive" data-vp-list-rows>
                <table class="table table-sm table-hover vp-table
                              align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= __('Object') ?></th>
                            <th><?= __('Relation') ?></th>
                            <th><?= __('Sibling value') ?></th>
                            <th><?= __('Type') ?></th>
                            <th class="text-end"><?= __('Objects') ?></th>
                            <?php
                            /*
                             * A right-aligned number immediately left of
                             * a left-aligned name reads as one field, so
                             * the count keeps its own gutter.
                             */
                            ?>
                            <th class="text-end pe-4"><?= __('Events') ?></th>
                            <th><?= __('Reported by') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($siblings['rows'] as $sibling): ?>
                            <tr class="vp-rel-stripe vp-rel-k-co"
                                data-vp-facet="<?=
                                    h($siblingTokens($sibling)) ?>">
                                <td class="text-nowrap">
                                    <span class="misp-icon misp-icon-object
                                                 misp-simple me-1"></span>
                                    <span class="font-monospace small">
                                        <?= h($sibling['object']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="vp-relation">
                                        <?= h($sibling['relation']) ?>
                                    </span>
                                </td>
                                <td class="font-monospace vp-rel-cell">
                                    <?= h($sibling['value']) ?>
                                </td>
                                <td><?= $typeBadge($sibling['type']) ?></td>
                                <?php
                                /*
                                 * The collapse, as a number and not a
                                 * bar: it is how many objects this one
                                 * row stands for, which a reader has to
                                 * be able to read exactly.
                                 */
                                ?>
                                <td class="text-end font-monospace small
                                           text-nowrap">
                                    <?= $sibling['objects'] > 1
                                        ? $sibFloor
                                        : '' ?><?=
                                        h(number_format(
                                            $sibling['objects']
                                        )) ?>
                                </td>
                                <td class="text-end pe-4 text-nowrap">
                                    <?php
                                    /*
                                     * Aggregating to a triple loses the
                                     * object ids, so a row standing for
                                     * many objects can only give the
                                     * count. A row standing for one is
                                     * unambiguous and keeps the link it
                                     * had before the aggregation.
                                     */
                                    ?>
                                    <?php if ($sibling['event'] !== null): ?>
                                        <a href="<?= $baseurl
                                            ?>/events/view2/<?=
                                            h($sibling['event']) ?>"
                                           class="font-monospace small">
                                            #<?= h($sibling['event']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="font-monospace small">
                                            <?= $sibFloor ?><?=
                                                h(number_format(
                                                    $sibling['events']
                                                )) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?= h(implode(', ', array_slice(
                                        $sibling['orgs'],
                                        0,
                                        2
                                    ))) ?>
                                    <?php
                                    $sibMore = $sibling['org_total']
                                        - min(2, count($sibling['orgs']));
                                    ?>
                                    <?php if ($sibMore > 0): ?>
                                        <span class="text-muted small">
                                            <?= h(sprintf(
                                                __('+%d more'),
                                                $sibMore
                                            )) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-3 d-none" data-vp-list-empty>
                <div class="vp-empty vp-empty-inline">
                    <i class="fas fa-filter"></i>
                    <span>
                        <?= __('No sibling matches the filter you set.') ?>
                    </span>
                </div>
            </div>

            <div class="px-3 py-2 border-top">
                <?= $this->element('Values/View/value_pager', array(
                    'size' => $siblings['page_size'],
                    'shown' => count($siblings['rows']),
                    'total' => $siblings['total'],
                    'noun' => __('siblings'),
                )) ?>
            </div>

            <?php
            /*
             * §14.6, applied here by phase 24. This carried a
             * `.vp-acl-note` counting the sibling attributes held at a
             * distribution the reader is outside of. A count of what is
             * hidden is a membership oracle over any value the reader
             * cares to type, and the bare fact that something *was*
             * hidden is the same disclosure at one bit — so the band is
             * gone and the aggregate above simply describes what the
             * reader can see. The cap notice stays: a cap is not a
             * permission.
             */
            ?>

        </div>

    <?php endif; ?>

    <?php if ($hasRows): ?>

        <?php
        /*
         * The ranked table's heading, hoisted out of its roll-up pane so
         * that everything governing this table — the two cap notices,
         * the filter row and the counted bar — sits between this line
         * and the rows, and nothing governing it sits above the sibling
         * section. All four used to open the panel, which put them
         * above a table they say nothing about; a reader could only
         * learn which of the three narrowing controls reached which of
         * the two tables by trying them.
         *
         * Outside `[data-vp-list-rows]`, and that is load-bearing: the
         * row host is hidden when a filter empties the table, and a
         * control inside it would take the reader's only way back out
         * with it.
         */
        ?>
        <div data-vp-group-only="value">
            <div class="px-3 pt-3 mt-2 border-top">
                <div class="vp-subhead d-flex align-items-center gap-2">
                    <i class="fas fa-link"></i>
                    <?= __('Values that appear in the same events') ?>
                    <span class="badge text-bg-secondary">
                        <?= h(number_format($co['distinct_values'])) ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if (!$co['suppressed']): ?>

        <?php
        /*
         * Counts distinct *values* and promises the facet counts below
         * are exact, so it goes away with the roll-up those two belong
         * to. It always showed, which was survivable while it sat at
         * the top of the panel and only wrong once it moved down to
         * where the event table can be the thing underneath it.
         *
         * The scan line below is not gated: what the scan read is the
         * same however the rows are rolled up.
         */
        ?>
        <div class="vp-rel-cap" data-vp-group-only="value">
            <i class="fas fa-filter"></i>
            <span>
                <?php if ($co['distinct_values'] > count($valueRows)): ?>
                    <?= sprintf(
                        __(
                            '%1$s, ranked by shared events. The facet'
                            . ' counts below stay exact at %2$s: they'
                            . ' are folded from every row read, not'
                            . ' tallied from the page.'
                        ),
                        '<strong>' . h(sprintf(
                            __('%1$s of %2$s distinct values are carried'),
                            number_format(count($valueRows)),
                            number_format($co['distinct_values'])
                        )) . '</strong>',
                        h(number_format($co['distinct_values']))
                    ) ?>
                <?php else: ?>
                    <?= sprintf(
                        __(
                            '%1$s. Nothing here is ranked away — the'
                            . ' cut below is on which events were read,'
                            . ' not on which values survived.'
                        ),
                        '<strong>' . h(sprintf(
                            __('All %d distinct values are listed'),
                            $co['distinct_values']
                        )) . '</strong>'
                    ) ?>
                <?php endif; ?>
            </span>
        </div>

        <?php if ($scanned): ?>
            <?php
            /*
             * The cut this section is made of, in words. Every count
             * above is exact over the events named here and over no
             * others, and a reader who is not told which events were
             * read has no way to judge a neighbour list at all.
             *
             * §14.6 keeps cap notices: a cap is not a permission. None
             * of these numbers says anything about rows the reader may
             * not see — an oversized event is oversized for everybody.
             */
            ?>
            <div class="vp-rel-cap">
                <i class="fas fa-circle-info"></i>
                <span>
                    <?= sprintf(
                        __(
                            'Read from %1$s, newest first, within a'
                            . ' budget of %2$s attribute rows — %3$s'
                            . ' read.'
                        ),
                        '<strong>' . h(sprintf(
                            __n(
                                'the one event this value is in',
                                '%1$d of this value\'s %2$d events',
                                $scan['events_seen'],
                                $scan['events_read'],
                                $scan['events_seen']
                            )
                        )) . '</strong>',
                        h(number_format($scan['budget'])),
                        h(__n(
                            '%s row',
                            '%s rows',
                            $scan['rows_read'],
                            number_format($scan['rows_read'])
                        ))
                    ) ?>
                    <?php if (!empty($scan['events_oversized'])): ?>
                        <?= h(sprintf(
                            __n(
                                '%1$d event was left out for holding'
                                    . ' more than %2$s attributes,'
                                    . ' where co-occurrence describes'
                                    . ' the event rather than the value.',
                                '%1$d events were left out for holding'
                                    . ' more than %2$s attributes each,'
                                    . ' where co-occurrence describes'
                                    . ' the event rather than the value.',
                                $scan['events_oversized'],
                                $scan['events_oversized'],
                                number_format($scan['size_cap'])
                            )
                        )) ?>
                    <?php endif; ?>
                    <?php if (!empty($scan['events_unread'])): ?>
                        <?= h(sprintf(
                            __n(
                                '%d further event fell outside the'
                                    . ' budget.',
                                '%d further events fell outside the'
                                    . ' budget.',
                                $scan['events_unread'],
                                $scan['events_unread']
                            )
                        )) ?>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <?php
        /*
         * The narrowing block belongs to the value roll-up and says so
         * when it is not showing. A facet on `type` is a property of a
         * correlated value; an event row is not a value, and narrowing
         * it by the type of one would be a filter that means nothing.
         */
        ?>
        <div data-vp-group-only="value">

            <div class="p-3 border-bottom d-flex flex-wrap gap-2
                        align-items-center">
                <div class="input-group input-group-sm" style="width: 15rem">
                    <span class="input-group-text">
                        <i class="fas fa-magnifying-glass"></i>
                    </span>
                    <input type="text" class="form-control"
                           data-vp-filter-text
                           aria-label="<?= __('Search the listed values') ?>"
                           placeholder="<?= h(__('Search value')) ?>">
                </div>

                <?php
                $selects = array(
                    array('key' => 'type', 'any' => __('Any type'),
                        'rows' => $facets['type']),
                    array('key' => 'organisation',
                        'any' => __('Any organisation'),
                        'rows' => $facets['organisation']),
                    array('key' => 'event', 'any' => __('Any event'),
                        'rows' => $facets['event']),
                );
                ?>
                <select class="form-select form-select-sm w-auto"
                        data-vp-filter-key="category"
                        aria-label="<?= __('Category') ?>">
                    <option value=""><?= __('Any category') ?></option>
                    <?php foreach ($co['categories'] as $category): ?>
                        <option value="<?= h($slug($category)) ?>">
                            <?= h($category) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php foreach ($selects as $select): ?>
                    <select class="form-select form-select-sm w-auto"
                            data-vp-filter-key="<?= h($select['key']) ?>"
                            aria-label="<?= h($select['any']) ?>">
                        <option value=""><?= h($select['any']) ?></option>
                        <?php foreach ($select['rows'] as $facet): ?>
                            <option value="<?= h($facet['value']) ?>">
                                <?= h($facet['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endforeach; ?>

                <div class="input-group input-group-sm" style="width: 12rem">
                    <span class="input-group-text">
                        <?= __('Shared events') ?> &ge;
                    </span>
                    <input type="number" class="form-control" min="1"
                           value="1" data-vp-filter-min="shared"
                           aria-label="<?= __('Minimum shared events') ?>">
                </div>

                <div class="form-check form-switch mb-0 ms-1">
                    <input class="form-check-input" type="checkbox"
                           role="switch" id="vp-rel-siblings-only"
                           data-vp-facet-key="sibling" value="yes">
                    <label class="form-check-label small text-muted"
                           for="vp-rel-siblings-only">
                        <?= __('Object siblings only') ?>
                    </label>
                </div>

                <?php
                /*
                 * "No filter applied" and "3 filters" are one line in
                 * two states rather than two lines, so the reader's eye
                 * does not have to move when the first control is set.
                 * It counts every narrowing control — a select and a
                 * threshold narrow the table exactly as a ticked facet
                 * does, and a count that ignored them would let the
                 * panel claim nothing was applied while three were.
                 */
                ?>
                <span class="small text-muted ms-auto"
                      data-vp-facet-summary>
                    <span class="vp-facet-summary-none">
                        <?= __('No filter applied') ?>
                    </span>
                    <span class="vp-facet-summary-some">
                        <span data-vp-facet-count-active>0</span>
                        <?= __('filters') ?>
                        &middot;
                        <span data-vp-facet-rows><?=
                            h(count($valueRows)) ?></span>
                        <?= __('rows') ?>
                    </span>
                </span>

                <button type="button" class="btn btn-sm btn-link"
                        data-vp-facet-clear disabled>
                    <?= __('Reset') ?>
                </button>
            </div>

            <div class="p-3 border-bottom d-flex flex-wrap gap-2
                        align-items-center">
                <span class="vp-subhead mb-0 me-1"><?= __('Narrow by') ?></span>

                <?php foreach ($facetGroups as $group): ?>
                    <?php if (empty($facets[$group['key']])) {
                        continue;
                    } ?>
                    <div class="dropdown">
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary
                                       dropdown-toggle vp-rel-facet"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                aria-expanded="false">
                            <?= h($group['title']) ?>
                            <span class="badge text-bg-secondary ms-1">
                                <?= h(count($facets[$group['key']])) ?>
                            </span>
                        </button>
                        <div class="dropdown-menu vp-rel-facetmenu p-2">
                            <?= $this->element(
                                'Values/View/value_facet_group',
                                array(
                                    'key' => $group['key'],
                                    'title' => $group['title'],
                                    'icon' => $group['icon'],
                                    'values' => $facets[$group['key']],
                                )
                            ) ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <span class="small text-muted ms-2 vp-min-w-0">
                    <?= $scanned ? sprintf(
                        __(
                            'Facet counts are exact at every count —'
                            . ' they are folded from %s, not from the'
                            . ' page. A count larger than the table can'
                            . ' show means the value it names is outside'
                            . ' the %s carried.'
                        ),
                        '<span class="font-monospace">'
                            . h(sprintf(
                                __('all %s rows read'),
                                number_format($scan['rows_read'])
                            )) . '</span>',
                        '<span class="font-monospace">'
                            . h(number_format(count($valueRows)))
                            . '</span>'
                    ) : sprintf(
                        __(
                            'Facet counts are exact at every count —'
                            . ' they are a %s over the whole scope, not'
                            . ' a count of the page.'
                        ),
                        '<span class="font-monospace">GROUP BY</span>'
                    ) ?>
                </span>
            </div>

        </div>

        <div class="vp-rel-cap d-none" data-vp-group-not="value">
            <i class="fas fa-circle-info"></i>
            <span>
                <?= __('Narrowing applies to the value roll-up. A facet'
                    . ' like Type is a property of a correlated value;'
                    . ' an event row is not a value, and filtering one'
                    . ' by the type of the other would be a control that'
                    . ' means nothing.') ?>
            </span>
        </div>


        <?php endif; ?>

        <div data-vp-list-rows>

            <div data-vp-group-pane="value">
                <div class="table-responsive">
                    <table class="table table-sm table-hover vp-table
                                  align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="vp-rel-pick">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           data-vp-rel-select-all
                                           aria-label="<?=
                                               __('Select every listed row')
                                           ?>">
                                </th>
                                <th><?= __('Value') ?></th>
                                <th><?= __('Type') ?></th>
                                <th class="vp-rel-num">
                                    <?= __('Shared events') ?>
                                </th>
                                <th><?= __('Organisations') ?></th>
                                <th><?= __('Last together') ?></th>
                                <th><?= __('Distribution') ?></th>
                                <th><?= __('Tags') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($valueRows as $row): ?>
                                <tr class="vp-rel-stripe vp-rel-k-co"
                                    data-vp-group="value"
                                    data-vp-facet="<?= h($valueTokens($row)) ?>"
                                    data-vp-num="<?= h($numbers(
                                        $row['shared_events'],
                                        $row['last_together']
                                    )) ?>"
                                    data-vp-text="<?= h(strtolower(
                                        $row['value']
                                    )) ?>">
                                    <td class="vp-rel-pick">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               data-vp-rel-select
                                               aria-label="<?= h(sprintf(
                                                   __('Select %s'),
                                                   $row['value']
                                               )) ?>">
                                    </td>
                                    <td class="font-monospace vp-rel-cell">
                                        <?= h($row['value']) ?>
                                    </td>
                                    <td><?= $typeBadge($row['type']) ?></td>
                                    <td><?= $weightBar(
                                        $row['shared_events'],
                                        $maxShared
                                    ) ?></td>
                                    <td class="small">
                                        <?= h(implode(', ', $row['orgs'])) ?>
                                    </td>
                                    <td class="font-monospace text-nowrap
                                               small">
                                        <?= h($row['last_together']) ?>
                                    </td>
                                    <td><?= $distributionBadge($row) ?></td>
                                    <td><?= $tagChips($row['tags']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div data-vp-group-pane="event" class="d-none">
                <div class="px-3 pt-3 mt-2 border-top">
                    <div class="vp-subhead d-flex align-items-center gap-2">
                        <span class="misp-icon misp-icon-event
                                     misp-simple"></span>
                        <?= __('Events this value shares with something'
                            . ' else') ?>
                        <span class="badge text-bg-secondary">
                            <?= h(number_format(
                                $co['rollups']['event']['total']
                            )) ?>
                        </span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover vp-table
                                  align-middle mb-0">
                        <thead>
                            <tr>
                                <th><?= __('Event') ?></th>
                                <th><?= __('Date') ?></th>
                                <th><?= __('Reported by') ?></th>
                                <th class="vp-rel-num">
                                    <?= __('Shared values') ?>
                                </th>
                                <th><?= __('Distribution') ?></th>
                                <th><?= __('Tags') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eventRows as $row): ?>
                                <tr class="vp-rel-stripe vp-rel-k-co"
                                    data-vp-group="event"
                                    data-vp-num="<?= h($numbers(
                                        $row['shared_values'],
                                        $row['event']['date']
                                    )) ?>">
                                    <td class="vp-min-w-0">
                                        <a href="<?= $baseurl
                                            ?>/events/view2/<?=
                                            h($row['event']['id']) ?>"
                                           class="font-monospace small">
                                            #<?= h($row['event']['id']) ?>
                                        </a>
                                        <div class="small text-muted
                                                    vp-rel-cell">
                                            <?= h($row['event']['info']) ?>
                                        </div>
                                    </td>
                                    <td class="font-monospace text-nowrap
                                               small">
                                        <?= h($row['event']['date']) ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <?= h($row['org']) ?>
                                    </td>
                                    <td><?= $weightBar(
                                        $row['shared_values'],
                                        $maxEventShared
                                    ) ?></td>
                                    <td><?= $distributionBadge($row) ?></td>
                                    <td><?= $tagChips($row['tags']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div data-vp-group-pane="object" class="d-none">
                <div class="px-3 pt-3 mt-2 border-top">
                    <div class="vp-subhead d-flex align-items-center gap-2">
                        <span class="misp-icon misp-icon-object
                                     misp-simple"></span>
                        <?= __('Objects the correlated attributes sit in') ?>
                        <span class="badge text-bg-secondary">
                            <?= h(number_format(
                                $co['rollups']['object']['total']
                            )) ?>
                        </span>
                    </div>
                    <div class="small text-muted mb-2">
                        <?= __('Shorter than the other two roll-ups because'
                            . ' most correlated attributes are not in an'
                            . ' object at all.') ?>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover vp-table
                                  align-middle mb-0">
                        <thead>
                            <tr>
                                <th><?= __('Object') ?></th>
                                <th><?= __('Event') ?></th>
                                <th><?= __('Reported by') ?></th>
                                <th class="vp-rel-num">
                                    <?= __('Related values') ?>
                                </th>
                                <th><?= __('Relations') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($objectRows as $row): ?>
                                <tr class="vp-rel-stripe vp-rel-k-co"
                                    data-vp-group="object"
                                    data-vp-num="shared:<?=
                                        h((int)$row['values']) ?> recent:0">
                                    <td class="text-nowrap">
                                        <span class="misp-icon
                                                     misp-icon-object
                                                     misp-simple me-1"></span>
                                        <span class="font-monospace small">
                                            <?= h($row['object']['name']) ?>
                                        </span>
                                        <span class="text-muted small">
                                            #<?= h($row['object']['id']) ?>
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="<?= $baseurl
                                            ?>/events/view2/<?=
                                            h($row['event']) ?>"
                                           class="font-monospace small">
                                            #<?= h($row['event']) ?>
                                        </a>
                                    </td>
                                    <td class="text-nowrap">
                                        <?= h($row['org']) ?>
                                    </td>
                                    <td><?= $weightBar(
                                        $row['values'],
                                        $maxObjectValues
                                    ) ?></td>
                                    <td>
                                        <?php foreach (
                                            $row['relations'] as $relation
                                        ): ?>
                                            <span class="vp-relation me-1">
                                                <?= h($relation) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <?php
        /*
         * Only a filter can produce this. The value with no correlation
         * at all has its own empty state above, and "no row matches
         * your filter" over it would be a different and false claim.
         */
        ?>
        <div class="p-3 d-none" data-vp-list-empty>
            <div class="vp-empty vp-empty-inline">
                <i class="fas fa-filter"></i>
                <span>
                    <?= __('No correlation matches the filter you set.') ?>
                </span>
            </div>
        </div>

        <div class="px-3 py-2 border-top">
            <?= $this->element('Values/View/value_pager', array(
                'size' => $co['page_size'],
                'shown' => count($valueRows),
                'total' => count($valueRows),
                'noun' => __('rows'),
            )) ?>
        </div>

        <div class="p-3 pt-0 d-flex align-items-center gap-2 flex-wrap">
            <span class="small text-muted me-1">
                <strong data-vp-rel-selected>0</strong>
                <?= h(__('selected')) ?>
            </span>
            <button type="button" class="btn btn-sm btn-outline-secondary
                                         disabled"
                    title="<?= h($noWrites) ?>">
                <i class="fas fa-hashtag me-1"></i>
                <?= __('Tag the selection') ?>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary
                                         disabled"
                    title="<?= h($noWrites) ?>">
                <i class="fas fa-folder-plus me-1"></i>
                <?= __('Add selection to a collection') ?>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary
                                         ms-auto disabled"
                    title="<?= h(__(
                        'Disabled in this pass — the search this would'
                        . ' open is a restSearch the page does not run'
                        . ' yet.'
                    )) ?>">
                <?= h(sprintf(
                    __('Open all %s as a search'),
                    number_format($co['distinct_values'])
                )) ?>
                <i class="fas fa-arrow-right ms-1"></i>
            </button>
        </div>

    <?php endif; ?>

    <?php
    /*
     * §14.6, applied here by phase 24. This carried a `.vp-acl-note`
     * reading *"4 further correlations point into events you cannot
     * see. They are counted in the 31 and are not listed."* — a count
     * of the invisible, stated on a page whose URL takes any value the
     * reader types. Removed, along with the *"N of the M stored rows
     * are visible to you"* sentence above, which named the same number
     * by subtraction.
     */
    ?>

</div>
