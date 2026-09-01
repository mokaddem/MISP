<?php
/**
 * Section five: the object joins that carry a pair of dates.
 *
 * **A table and not a canvas, and that is the whole argument for it.**
 * The insight in a resolution history is entirely in the dates —
 * `draculax.myq-see.com.` resolves to four addresses in fourteen days
 * in April 2017, then to nothing for four years, then to one Brazilian
 * host the day before the report that named it. Dormant-then-
 * reactivated is a different story from continuously-live, and a
 * canvas has nowhere to put either. `24-relationships.md` §25.1 is the
 * worked case.
 *
 * Three things this section is careful about:
 *
 *   the span is real   an object recording one date recorded a moment,
 *                      not a span, and is not here. 40,098 objects on
 *                      the verification instance carry exactly one
 *                      `datetime` and 32,892 of those are a flood
 *                      capture saying when its row was generated.
 *   the word is theirs the columns are `First seen` and `Last seen`,
 *                      and under each date sits the object's own name
 *                      for it — `time_first`, `first-seen`. The header
 *                      is generic so the table can be read; the label
 *                      is not, so the reader is never told the object
 *                      said something it did not.
 *   the bound is said  roughly a fifth of attributes sit in objects at
 *                      all, and most objects record no date. An empty
 *                      panel here is the common case and it says which
 *                      case it is.
 *
 * Lazily loaded from ValuesController::viewRelationDated.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$relations = $valueProfile['relationships'];
$dated = $relations['dated'];
$siblings = $relations['siblings'];
$rows = $dated['rows'];

$icon = 'fas fa-clock-rotate-left';

/**
 * A neighbour's own Value Profile.
 *
 * The far end of a dated relation is the pivot — §25.2's whole reading
 * is the list of names on the address, and every one of them is a page
 * of its own. The encoding is `ValuesController::decodeValue`'s, which
 * the graph feed also builds; three characters of alphabet, duplicated
 * rather than shared for the same reason the model duplicates it.
 *
 * @param string $value
 * @return string
 */
$profileUrl = function ($value) use ($baseurl) {
    return $baseurl . '/values/view/' . rtrim(
        strtr(base64_encode($value), '+/', '-_'),
        '='
    );
};

/**
 * A stored `datetime` as a reader wants it, with the object's own
 * relation underneath.
 *
 * The stored form is ISO 8601 to the microsecond with an offset, which
 * is right for a machine and unreadable in a table cell beside four
 * more of them.
 *
 * @param array $stamp `at`, `raw`, `relation`
 * @return string
 */
$stamp = function ($stamp) {
    ob_start();
    ?>
    <span class="vp-dated-stamp" title="<?= h($stamp['raw']) ?>">
        <?= h(date('Y-m-d H:i', $stamp['at'])) ?>
    </span>
    <?php if ($stamp['relation'] !== ''): ?>
        <span class="vp-dated-relation"><?= h($stamp['relation']) ?></span>
    <?php endif; ?>
    <?php
    return ob_get_clean();
};

/*
 * Whether the two ends of the span are the same instant. A resolution
 * observed once has `time_first` equal to `time_last`, and printing
 * the same timestamp twice reads as a rendering fault rather than as
 * the fact it is.
 */
$moment = function ($row) {
    return $row['first']['at'] === $row['last']['at'];
};
?>
<div class="card shadow-sm mb-3 vp-panel vp-rel-k-object"
     style="--vp-panel-color: var(--vp-rel-object);"
     id="vp-relation-dated"
     data-vp-list
     data-vp-rel-summary="dated"
     data-vp-rel-count="<?= h(number_format($dated['total'])) ?>"
     <?php if (!empty($dated['cap']['applied'])): ?>
         data-vp-rel-note="<?= h(__('of the objects read')) ?>"
     <?php endif; ?>>

    <?php
    ob_start();
    ?>
        <span class="vp-rel-tag me-1">
            <i class="<?= h($icon) ?>"></i><?= h(__('Dated relations')) ?>
        </span>
        <?php if (!empty($rows)): ?>
            <?= h(sprintf(
                __('%1$s from %2$s'),
                __n('%d dated relation', '%d dated relations',
                    $dated['total'], $dated['total']),
                __n('%d object', '%d objects', $dated['objects'],
                    $dated['objects'])
            )) ?>
            <?php if (!empty($dated['templates'])): ?>
                &nbsp;·&nbsp;<?= h(implode(', ', $dated['templates'])) ?>
            <?php endif; ?>
            &nbsp;·&nbsp;
        <?php endif; ?>
        <span class="vp-rel-prov"><i class="fas fa-gauge"></i><?=
            h(__('Machine-derived')) ?></span>
        &nbsp;·&nbsp;<?= h(__('object join')) ?>
    <?php
    $headerSub = ob_get_clean();
    ?>

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Dated relations'),
        'panelIcon' => $icon,
        'panelColor' => 'var(--vp-rel-object)',
        'panelSub' => $headerSub,
    )) ?>

    <div class="p-3">

        <div class="vp-rel-cap">
            <i class="fas fa-circle-info"></i>
            <span>
                <?= __('A row is one object that records both a start and an end. The object names each date itself — a passive-dns object calls them time_first and time_last, a domain-ip object first-seen and last-seen — and those names are printed under the dates. An object that records a single date recorded a moment rather than a span, and is not counted here. Origin is the object\'s own word for where the observation came from, and most templates do not have one; the organisation beneath it is who reported the event.') ?>
            </span>
        </div>

        <?php if (empty($rows)): ?>

            <div class="vp-empty">
                <i class="<?= h($icon) ?>"></i>
                <span>
                    <?php if ($dated['in_objects'] === 0): ?>
                        <?= __('This value sits in no object, so there is no object relation to date. Around four attributes in five are like it.') ?>
                    <?php elseif ($dated['read_objects'] === 0): ?>
                        <?= h(sprintf(
                            __n(
                                'This value sits in one object, and it holds no other attribute to relate it to.',
                                'This value sits in %s, and none of them holds another attribute to relate it to.',
                                $dated['in_objects']
                            ),
                            sprintf(__('%s objects'),
                                number_format($dated['in_objects']))
                        )) ?>
                    <?php elseif (!empty($dated['cap']['applied'])): ?>
                        <?php
                        /*
                         * The capped case says both numbers. "None of
                         * the 500 objects" would be true of the read
                         * and misleading about the value, which sits in
                         * 32,922 of them.
                         */
                        ?>
                        <?= h(sprintf(
                            __('None of the %1$s objects read records both a start and an end date. This value sits in %2$s altogether, and the join reads the most recently touched.'),
                            number_format($dated['read_objects']),
                            sprintf(__('%s objects'),
                                number_format($dated['in_objects']))
                        )) ?>
                    <?php else: ?>
                        <?= h(sprintf(
                            __n(
                                'The one object this value sits in records no start and end date.',
                                'None of the %s objects this value sits in records both a start and an end date.',
                                $dated['in_objects']
                            ),
                            number_format($dated['in_objects'])
                        )) ?>
                    <?php endif; ?>
                </span>
            </div>

        <?php else: ?>

            <div class="table-responsive" data-vp-list-rows>
                <table class="table table-sm table-hover vp-table
                              align-middle mb-0">
                    <?php
                    /*
                     * Oldest first, which is the order the story runs
                     * in. The cut that produced these rows was taken
                     * off the other end — the most recent survive a cap
                     * — so a table that stops is missing its beginning
                     * rather than its present, and the pager says so.
                     */
                    ?>
                    <thead>
                        <tr>
                            <th scope="col"><?= __('Related value') ?></th>
                            <th scope="col"><?= __('As') ?></th>
                            <th scope="col"><?= __('First seen') ?></th>
                            <th scope="col"><?= __('Last seen') ?></th>
                            <th scope="col"><?= __('Origin') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr class="vp-rel-stripe vp-rel-k-object">
                                <td class="font-monospace">
                                    <a class="vp-rel-cell fw-semibold"
                                       href="<?= h($profileUrl(
                                           $row['value'])) ?>"
                                       title="<?= h($row['value']) ?>">
                                        <?= h($row['value']) ?>
                                    </a>
                                    <div class="vp-fact-line-sub">
                                        <?= h($row['type']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="vp-rel-tag">
                                        <i class="fas fa-cube"></i><?=
                                            h($row['object']) ?>
                                    </span>
                                    <?php if ($row['relation'] !== ''): ?>
                                        <div class="vp-fact-line-sub
                                                    font-monospace">
                                            <?= h($row['relation']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= $stamp($row['first']) ?></td>
                                <td>
                                    <?php if ($moment($row)): ?>
                                        <span class="vp-dated-span"
                                              title="<?= h(__('The object recorded one instant for both ends of the span.')) ?>">
                                            <?= h(__('same instant')) ?>
                                        </span>
                                    <?php else: ?>
                                        <?= $stamp($row['last']) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    /*
                                     * A dash and not a sentence. Most
                                     * templates have no `origin`
                                     * relation at all, so "the object
                                     * records no origin" is the same
                                     * true statement on every one of
                                     * forty-six rows, shouting over the
                                     * organisation underneath it. The
                                     * caption in the header says what
                                     * the column is; the title says why
                                     * a cell is empty for whoever asks.
                                     */
                                    ?>
                                    <?php if ($row['origin'] !== null
                                        && $row['origin'] !== ''
                                    ): ?>
                                        <div class="vp-rel-cell"
                                             title="<?= h($row['origin']) ?>">
                                            <?= h($row['origin']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="vp-dated-span"
                                              title="<?= h(__('This object template records no origin for the relation.')) ?>">—</span>
                                    <?php endif; ?>
                                    <div class="vp-fact-line-sub">
                                        <?= h($row['org']) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="px-1 py-2 border-top">
                <?= $this->element('Values/View/value_pager', array(
                    'size' => $dated['page_size'],
                    'shown' => count($rows),
                    'total' => $dated['total'],
                    'noun' => array(
                        'one' => __('dated relation'),
                        'many' => __('dated relations'),
                    ),
                )) ?>
            </div>

            <?php if (!empty($dated['cap']['applied'])): ?>
                <div class="vp-fact-line-sub mt-2">
                    <i class="fas fa-scissors"></i>
                    <?= h(sprintf(
                        __('This value sits in %1$s. The join read the %2$s most recently touched, so a dated relation in an older object is not on this list.'),
                        __n('%d object', '%d objects',
                            $dated['in_objects'],
                            number_format($dated['in_objects'])),
                        number_format($dated['cap']['limit'])
                    )) ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <?php if (!empty($relations['suppressed'])): ?>
            <div class="vp-fact-line-sub mt-2">
                <i class="fas fa-circle-info"></i>
                <?= __('Every event this value appears in was too large to read for co-occurrence. Object joins are read per object rather than per event, so this section is unaffected by that.') ?>
            </div>
        <?php endif; ?>

        <div class="vp-fact-line-sub mt-2">
            <i class="fas fa-clock"></i>
            <?= $this->element('Values/View/value_read_age', array(
                'readAt' => isset($relations['read_at'])
                    ? $relations['read_at'] : 0,
                'prefix' => __('Folded from the object join read %s.'),
            )) ?>
        </div>

    </div>

</div>
