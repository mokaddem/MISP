<?php
/**
 * The History tab: the audit log for this value, one collapsible section
 * per occurrence.
 *
 * Grouping by occurrence is the whole addition over the per-event audit
 * logs the analyst already has. Those can each see one copy of this
 * value; only this page can say that occurrence 4831022 carries nine
 * entries while 4828810 carries two.
 *
 * Three things follow from the grouping rather than decorate it. Event
 * publications and event tags get their own section, because repeating
 * ten of them inside six sections would turn ten entries into sixty.
 * The occurrences hidden by ACL are named and not listed, because six
 * sections where there should be ten is the one place that absence has
 * a visible shape. And a section's counts move with the rail, because a
 * header reading `9 entries` over three visible rows is two numbers
 * disagreeing.
 *
 * The panel owns its own row — rail at col-lg-3, sections at col-lg-9 —
 * rather than taking `view_layout`'s split: the facet control binds a
 * checkbox to rows through the nearest `data-vp-list` ancestor, so the
 * two cannot be separate cards.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewHistory.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
App::uses('AuditActionMeta', 'Tools');

$profile = $valueProfile;
$history = isset($profile['history']) ? $profile['history'] : null;

$panelColour = 'var(--bs-secondary-color)';
$panelIcon = 'fas fa-history';

/*
 * Rows page inside their own section and never across the union: a
 * server-side Paginator over N event-scoped queries has no stable
 * ordering key, and this is where that lands. Twenty-five is the point
 * past which a section stops being something a reader scrolls.
 */
$pageSize = 25;

/**
 * @param string $stamp `Y-m-d H:i:s`
 * @param string $format
 * @return string
 */
$fmt = function ($stamp, $format) {
    $at = strtotime((string)$stamp);
    return $at ? date($format, $at) : (string)$stamp;
};

/**
 * The `Del` badge the Occurrences tab already uses, so a soft-deleted
 * occurrence looks the same on both tabs.
 *
 * @return string
 */
$deletedBadge = function () {
    return '<span class="badge d-inline-flex align-items-center gap-1'
        . ' bg-secondary-subtle text-secondary-emphasis border'
        . ' border-secondary-subtle" title="'
        . h(__('Soft-deleted — history, not current state')) . '">'
        . '<i class="fas fa-trash"></i>' . h(__('Del')) . '</span>';
};
?>

<?php if ($history === null): ?>
    <?php
    /*
     * No `history` key at all. Not "recorded and empty" and not "not
     * recorded" — MISP has never held this value, so there is nothing
     * for an audit log to have caught or missed, and offering the
     * reader a setting to change would be answering a question they
     * did not ask.
     */
    ?>
    <div class="card shadow-sm mb-3 vp-panel"
         style="--vp-panel-color: <?= h($panelColour) ?>;">
        <?= $this->element('Values/View/value_panel_header', array(
            'panelTitle' => __('History'),
            'panelIcon' => $panelIcon,
            'panelColor' => $panelColour,
        )) ?>
        <div class="vp-empty">
            <i class="<?= h($panelIcon) ?>"></i>
            <span><?= __(
                'This value has never been stored on this instance, so'
                . ' nothing has ever happened to it here.'
            ) ?></span>
        </div>
    </div>

<?php elseif (!$history['recorded']): ?>
    <?php
    /*
     * State 2, and the common case rather than the edge one:
     * `MISP.log_new_audit` defaults to false (`Server.php:6649`), so
     * this is what a default instance renders. It replaces the panel
     * instead of sitting inside it, because every section, count and
     * facet below would otherwise be a claim about a log that is not
     * running.
     *
     * The rail card is the point of the state. "No history" invites the
     * reader to conclude nothing happened; the answer is the list of
     * what this page still knows without the audit log — and it is not
     * a short list.
     */
    $occurrences = $profile['occurrences'];
    $edited = null;
    foreach ($occurrences as $occurrence) {
        $stamp = (int)$occurrence['Attribute']['timestamp'];
        if ($edited === null || $stamp > $edited) {
            $edited = $stamp;
        }
    }
    $publications = array();
    if (!empty($profile['timeline']['entries'])) {
        foreach ($profile['timeline']['entries'] as $entry) {
            if ($entry['source'] === 'publication') {
                $publications[] = $entry['at'];
            }
        }
    }
    sort($publications);
    ?>
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3 vp-panel"
                 style="--vp-panel-color: <?= h($panelColour) ?>;">
                <?= $this->element('Values/View/value_panel_header', array(
                    'panelTitle' => __('History'),
                    'panelIcon' => $panelIcon,
                    'panelColor' => $panelColour,
                )) ?>
                <div class="p-3">
                    <div class="vp-panel-stub">
                        <span class="vp-panel-stub-badge">
                            <?= __('Not recorded') ?>
                        </span>
                        <div class="fw-bold mt-2">
                            <?= __(
                                'This instance is not recording an audit'
                                . ' log.'
                            ) ?>
                        </div>
                        <div class="vp-panel-stub-note">
                            <?= __(
                                'Which is not the same as nothing having'
                                . ' happened to this value. Everything'
                                . ' below happened; none of it was'
                                . ' written down.'
                            ) ?>
                        </div>
                    </div>

                    <div class="vp-fact-line mt-3">
                        <i class="fas fa-sliders"></i>
                        <span>
                            <?= sprintf(
                                __('Turn it on at %s.'),
                                '<code>' . h(__(
                                    'Administration → Server settings →'
                                    . ' MISP → MISP.log_new_audit'
                                )) . '</code>'
                            ) ?>
                        </span>
                    </div>
                    <div class="vp-fact-line vp-fact-line-warn">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span>
                            <?= __(
                                'Enabling it records forward. It never'
                                . ' reconstructs the past — an analyst'
                                . ' who turns it on today and comes'
                                . ' back in a year has a year of'
                                . ' history, not this value\'s.'
                            ) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="vp-aside">
                <div class="vp-aside-head">
                    <span class="vp-aside-title">
                        <?= __('Knowable without it') ?>
                    </span>
                </div>
                <div class="vp-aside-note">
                    <?= __(
                        'None of these come from the audit log, so none'
                        . ' of them are affected by the setting.'
                    ) ?>
                </div>
                <div class="vp-fact-line">
                    <i class="fas fa-pencil"></i>
                    <span>
                        <?= h(sprintf(
                            __n(
                                'The latest edit to each of %d'
                                . ' occurrence',
                                'The latest edit to each of %d'
                                . ' occurrences',
                                count($occurrences)
                            ),
                            count($occurrences)
                        )) ?>
                        <?php if ($edited !== null): ?>
                            <span class="vp-fact-line-sub">
                                <?= h(sprintf(
                                    __('most recent %s'),
                                    date('j M Y', $edited)
                                )) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if (!empty($publications)): ?>
                    <div class="vp-fact-line">
                        <i class="fas fa-paper-plane"></i>
                        <span>
                            <?= __('First and last publication') ?>
                            <span class="vp-fact-line-sub">
                                <?= h(sprintf(
                                    '%1$s → %2$s',
                                    $fmt($publications[0], 'j M Y'),
                                    $fmt(
                                        $publications[
                                            count($publications) - 1
                                        ],
                                        'j M Y'
                                    )
                                )) ?>
                            </span>
                        </span>
                    </div>
                <?php endif; ?>
                <div class="vp-fact-line">
                    <i class="misp-icon misp-icon-sighting misp-simple">
                    </i>
                    <span>
                        <?= h(sprintf(
                            __('%d sightings, each to the minute'),
                            $profile['counts']['sightings']
                        )) ?>
                    </span>
                </div>
                <div class="vp-aside-note">
                    <?= sprintf(
                        __('All three are on the %s tab.'),
                        '<a href="#tab-timeline">'
                        . h(__('Timeline')) . '</a>'
                    ) ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($history['occurrences'] === 0): ?>
    <?php
    /*
     * State 4 taken to its limit — every occurrence hidden. The
     * suppressed band rather than the empty state, because the panel
     * knows the number it cannot show, and "nothing here" would be the
     * one reading that is false.
     */
    ?>
    <div class="card shadow-sm mb-3 vp-panel"
         style="--vp-panel-color: <?= h($panelColour) ?>;">
        <?= $this->element('Values/View/value_panel_header', array(
            'panelTitle' => __('History'),
            'panelIcon' => $panelIcon,
            'panelColor' => $panelColour,
        )) ?>
        <div class="vp-suppressed">
            <i class="fas fa-eye-slash"></i>
            <span>
                <span class="vp-suppressed-badge">
                    <?= __('Hidden from you') ?>
                </span>
                <?= h(sprintf(
                    __n(
                        'All %d occurrence of this value is on an event'
                        . ' you cannot see, so there is no audit entry'
                        . ' here you may read. How many entries they'
                        . ' carry is not obtainable either — the count'
                        . ' is itself a fact about those events.',
                        'All %d occurrences of this value are on events'
                        . ' you cannot see, so there is no audit entry'
                        . ' here you may read. How many entries they'
                        . ' carry is not obtainable either — the count'
                        . ' is itself a fact about those events.',
                        $history['total_occurrences']
                    ),
                    $history['total_occurrences']
                )) ?>
            </span>
        </div>
    </div>

<?php elseif ($history['entries'] === 0): ?>
    <?php
    /*
     * State 3. Recorded, and nothing about this value — which is a
     * claim about the value, where state 2 is a claim about the
     * instance. The wording has to make that the first thing the
     * reader takes away, because the two look identical otherwise.
     */
    ?>
    <div class="card shadow-sm mb-3 vp-panel"
         style="--vp-panel-color: <?= h($panelColour) ?>;">
        <?= $this->element('Values/View/value_panel_header', array(
            'panelTitle' => __('History'),
            'panelIcon' => $panelIcon,
            'panelColor' => $panelColour,
            'panelSub' => h(sprintf(
                __n(
                    '%d occurrence, nothing logged',
                    '%d occurrences, nothing logged',
                    $history['occurrences']
                ),
                $history['occurrences']
            )),
        )) ?>
        <div class="vp-empty">
            <i class="<?= h($panelIcon) ?>"></i>
            <span>
                <?= h(sprintf(
                    __n(
                        'The audit log is running on this instance and'
                        . ' has no entry for this value. Its %d visible'
                        . ' occurrence has not been touched since'
                        . ' recording began.',
                        'The audit log is running on this instance and'
                        . ' has no entry for this value. None of its %d'
                        . ' visible occurrences has been touched since'
                        . ' recording began.',
                        $history['occurrences']
                    ),
                    $history['occurrences']
                )) ?>
            </span>
        </div>
        <?php if ($history['hidden'] > 0): ?>
            <div class="vp-acl-note vp-acl-note-band">
                <i class="fas fa-eye-slash"></i>
                <span>
                    <?= h(sprintf(
                        __n(
                            '%d further occurrence is on an event you'
                            . ' cannot see. Whether it has entries is'
                            . ' not obtainable from here.',
                            '%d further occurrences are on events you'
                            . ' cannot see. Whether they have entries'
                            . ' is not obtainable from here.',
                            $history['hidden']
                        ),
                        $history['hidden']
                    )) ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
<?php
/*
 * State 1. Everything below is derived from `groups` and
 * `event_entries` — the header, the section counts, the mixes and the
 * four facet groups are four readings of one pair of lists, so adding
 * an entry moves all four by one and no two of them can drift.
 */
$facets = $history['facets'];
$vocab = $history['vocab']['action'];

/**
 * One row. Kept as a closure rather than a second element file: the
 * occurrence sections and the event-level section draw the same row,
 * and the only difference between them is what the row is filed under.
 *
 * @param array $row
 * @return string
 */
$renderRow = function ($row) use ($baseurl, $fmt) {
    $meta = AuditActionMeta::forAction($row['action']);
    $fields = array();
    if (!empty($row['change'])) {
        foreach ($row['change'] as $change) {
            $fields[] = $change['field'];
        }
    }
    /*
     * The title is composed from what the row already carries rather
     * than written out: an edit names the fields in its own diff, a tag
     * or galaxy names its subject, and everything else is its action
     * and nothing more. A title stored beside the diff is a title that
     * eventually contradicts it.
     */
    $title = $meta['label'];
    if (!empty($fields)) {
        $title .= ' ' . implode(', ', $fields);
    } elseif ($row['subject'] !== null) {
        $title .= ' ' . $row['subject'];
    }

    $actor = $row['actor'] !== null
        ? $row['actor']
        : sprintf(__('%s (unnamed)'), $row['org']);
    $tokens = array(
        'action:' . preg_replace('/[^a-z0-9]+/', '-', $row['action']),
        'model:' . strtolower($row['model']),
        'org:' . trim(preg_replace(
            '/[^a-z0-9]+/',
            '-',
            strtolower($row['org'])
        ), '-'),
        'actor:' . trim(preg_replace(
            '/[^a-z0-9]+/',
            '-',
            strtolower($actor)
        ), '-'),
    );
    $blob = strtolower(trim(implode(' ', array(
        $title,
        (string)$row['model_title'],
        $actor,
        $row['org'],
        (string)$row['note'],
        implode(' ', $fields),
    ))));

    ob_start();
    ?>
    <?php
    /*
     * Phase 15's `.vp-audit-row` grid, not a second one: time, glyph,
     * everything else. The Timeline's chronology draws the same three
     * tracks, which is why that phase built the class here rather than
     * in its own family.
     */
    ?>
    <div class="vp-audit-row"
         data-vp-list-row
         data-vp-facet="<?= h(implode(' ', $tokens)) ?>"
         data-vp-time="<?= h($fmt($row['created'], 'YmdHi')) ?>"
         data-vp-text="<?= h($blob) ?>"
         <?= $row['renamed'] ? 'data-vp-audit-renamed' : '' ?>>

        <div class="vp-tl-time" style="line-height: 1.3;">
            <div style="font-size: 0.7rem;">
                <?= h($fmt($row['created'], 'j M Y')) ?>
            </div>
            <div style="font-size: 0.7rem;">
                <?= h($fmt($row['created'], 'H:i')) ?>
            </div>
        </div>

        <span class="vp-audit-act"
              style="<?= AuditActionMeta::style($row['action']) ?>"
              title="<?= h($meta['label']) ?>">
            <i class="<?= h($meta['icon']) ?>"></i>
        </span>

        <div class="vp-min-w-0">

            <div class="d-flex align-items-start gap-2">

            <div class="vp-min-w-0 flex-grow-1">
                <div class="small">
                    <?= h($title) ?>
                    <?php if ((int)$row['request_type'] === 1): ?>
                        <span class="badge bg-secondary-subtle
                                     text-secondary-emphasis border
                                     border-secondary-subtle ms-1"
                              title="<?= h(__(
                                  'Made through the API, not the web'
                                  . ' interface'
                              )) ?>"><?= __('API') ?></span>
                    <?php elseif ((int)$row['request_type'] === 2): ?>
                        <span class="badge bg-secondary-subtle
                                     text-secondary-emphasis border
                                     border-secondary-subtle ms-1"
                              title="<?= h(__(
                                  'Made from the command line'
                              )) ?>"><?= __('CLI') ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($row['model_title'] !== null): ?>
                    <div class="text-muted font-monospace"
                         style="font-size: 0.7rem;">
                        <?= h($row['model']) ?>
                        <?php if ($row['model_id'] !== null): ?>
                            <?= h($row['model_id']) ?>
                        <?php endif; ?>
                        · <?= h($row['model_title']) ?>
                    </div>
                <?php endif; ?>
                <?php if ($row['note'] !== null): ?>
                    <div class="vp-fact-line-sub">
                        <?= h($row['note']) ?>
                    </div>
                <?php endif; ?>
                <?php if ($row['renamed']): ?>
                    <?php
                    /*
                     * The one row that explains why this tab is scoped
                     * by attribute id. `AuditLogBehavior` files an
                     * edit under the title it produced, so this row
                     * sits in this value's history while naming
                     * another value on its left-hand side — and the
                     * three entries below it are filed under the old
                     * value entirely. A `model_title` match would have
                     * kept the wrong ones and lost these.
                     */
                    ?>
                    <div class="vp-fact-line vp-fact-line-warn mt-1">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span>
                            <?= __(
                                'This occurrence used to hold a'
                                . ' different address. MISP files an'
                                . ' edit under the value it produced,'
                                . ' so this row — and only the rows'
                                . ' above it — belong to this value;'
                                . ' the entries below it are filed'
                                . ' under the old one. The tab finds'
                                . ' them because it is scoped by'
                                . ' attribute id, not by value.'
                            ) ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="text-end text-muted flex-shrink-0 vp-min-w-0"
                 style="font-size: 0.7rem; line-height: 1.3;
                        max-width: 11rem;">
                <div class="text-truncate<?= $row['actor'] === null
                    ? ' fst-italic'
                    : '' ?>"
                     <?= $row['actor'] === null
                         ? 'title="' . h(__(
                             'MISP does not hand you the user on an'
                             . ' entry from outside your organisation'
                         )) . '"'
                         : '' ?>>
                    <?= h($row['actor'] !== null
                        ? $row['actor']
                        : __('unnamed user')) ?>
                </div>
                <div class="text-truncate"><?= h($row['org']) ?></div>
            </div>

            <?php if (!empty($row['change'])): ?>
                <button type="button"
                        class="btn btn-sm btn-link p-0 flex-shrink-0
                               text-muted"
                        data-vp-audit-diff
                        aria-expanded="false"
                        title="<?= h(__('Show what changed')) ?>">
                    <i class="fas fa-chevron-down"></i>
                </button>
            <?php else: ?>
                <span class="flex-shrink-0" style="width: 1rem;"></span>
            <?php endif; ?>

        </div>

        <?php if (!empty($row['change'])): ?>
            <?php
            /*
             * From the fixture in this pass. Live, this is where
             * `AuditLogsController::fullChange` is called:
             * `audit_logs.change` is brotli-compressed above
             * `AuditLog::COMPRESS_MIN_LENGTH` and capped at 64KB, so
             * decoding every row at render time is exactly what that
             * method exists to avoid.
             */
            ?>
            <table class="vp-audit-diff d-none">
                <?php foreach ($row['change'] as $change): ?>
                    <tr>
                        <th><?= h($change['field']) ?></th>
                        <td>
                            <?php if ($change['was'] === ''): ?>
                                <em class="text-muted">
                                    <?= __('not set') ?>
                                </em>
                            <?php else: ?>
                                <s><?= h($change['was']) ?></s>
                            <?php endif; ?>
                        </td>
                        <td><i class="fas fa-arrow-right"></i></td>
                        <td>
                            <?php if ($change['is'] === ''): ?>
                                <em class="text-muted">
                                    <?= __('cleared') ?>
                                </em>
                            <?php else: ?>
                                <?= h($change['is']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        </div>

    </div>
    <?php
    return ob_get_clean();
};

/**
 * The action-mix bar for one occurrence.
 *
 * Segments follow the vocabulary order and not the counts, so two bars
 * a reader is comparing put the same action in the same place. It is
 * never re-proportioned under a filter: it describes the occurrence,
 * and redrawing it would make the filter look like history.
 *
 * @param array $mix
 * @param int $total
 * @return string
 */
$renderMix = function ($mix, $total) use ($vocab) {
    if ($total < 1) {
        return '';
    }
    $segments = '';
    $legend = array();
    foreach ($vocab as $action) {
        if (empty($mix[$action])) {
            continue;
        }
        $share = round(($mix[$action] / $total) * 100, 2);
        $segments .= '<span style="'
            . AuditActionMeta::style($action)
            . ' --vp-audit-share: ' . $share . '%;"></span>';
        $legend[] = AuditActionMeta::label($action)
            . ' ' . $mix[$action];
    }
    return '<span class="vp-audit-mix" title="'
        . h(implode(' · ', $legend)) . '">' . $segments . '</span>';
};

/*
 * The rail's four groups. Order, heading and glyph belong here rather
 * than in the fixture — only the counts vary by value — and the notes
 * are where the group says what its number does not cover.
 */
$railGroups = array(
    array(
        'key' => 'action',
        'title' => __('Action'),
        'icon' => 'fas fa-bolt',
        'rows' => $facets['action'],
        'note' => h(__(
            'Counted against every action that can reach an attribute'
            . ' or its event, so a zero is a statement: nothing here'
            . ' was ever hard-deleted or restored.'
        )),
    ),
    array(
        'key' => 'model',
        'title' => __('Model'),
        'icon' => 'fas fa-cube',
        'rows' => $facets['model'],
        'note' => null,
    ),
    array(
        'key' => 'org',
        'title' => __('Organisation'),
        'icon' => 'fas fa-building',
        'rows' => $facets['org'],
        'note' => null,
    ),
    array(
        'key' => 'actor',
        'title' => __('Actor'),
        'icon' => 'fas fa-user',
        'rows' => $facets['actor'],
        'note' => h(__(
            'An entry from outside your organisation is filed under the'
            . ' organisation, because MISP does not hand you the user.'
            . ' A site admin sees names where you see these.'
        )),
    ),
);

$headerLine = implode(' &nbsp;·&nbsp; ', array(
    sprintf(
        __('Showing %1$s of %2$s entries'),
        '<span data-vp-list-shown>' . h($history['entries']) . '</span>',
        h($history['entries'])
    ),
    h(sprintf(
        __n(
            '%1$d occurrence, %2$d event',
            '%1$d occurrences, %2$d events',
            $history['occurrences']
        ),
        $history['occurrences'],
        $history['events']
    )),
    h(sprintf(
        '%1$s → %2$s',
        $fmt($history['first'], 'j M Y'),
        $fmt($history['last'], 'j M Y')
    )),
));

ob_start();
?>
    <div class="input-group input-group-sm" style="width: 13rem">
        <span class="input-group-text">
            <i class="fas fa-magnifying-glass"></i>
        </span>
        <input type="text" class="form-control"
               data-vp-filter-text
               aria-label="<?= __('Search the listed entries') ?>"
               placeholder="<?= h(__('Search entries')) ?>">
    </div>

    <?php
    /*
     * The grouping control, with one of its three enabled. Grouping by
     * organisation is unanswerable for anyone who is not a site admin
     * — `__applyAuditAcl` collapses most of its cards to unnamed users
     * — and grouping by field needs `audit_logs.change` decoded for
     * every row at render time, which is what `fullChange` exists to
     * avoid. A disabled control is how this page has said "designed,
     * not built" since phase 5.
     */
    ?>
    <div class="btn-group btn-group-sm" role="group"
         aria-label="<?= __('Grouping') ?>">
        <button type="button" class="btn btn-outline-secondary active"
                aria-pressed="true">
            <?= __('By occurrence') ?>
        </button>
        <button type="button" class="btn btn-outline-secondary" disabled
                title="<?= h(__(
                    'Unanswerable outside a site-admin account: MISP'
                    . ' strips the user on entries from other'
                    . ' organisations, so most cards would read'
                    . ' "unnamed users".'
                )) ?>">
            <?= __('By organisation') ?>
        </button>
        <button type="button" class="btn btn-outline-secondary" disabled
                title="<?= h(__(
                    'Needs every row\'s compressed change blob decoded'
                    . ' at render time, which is the cost fullChange'
                    . ' exists to avoid.'
                )) ?>">
            <?= __('By field') ?>
        </button>
    </div>

    <button type="button" class="btn btn-sm btn-outline-secondary"
            data-vp-audit-expand-all
            data-vp-audit-label-expand="<?= h(__('Expand all')) ?>"
            data-vp-audit-label-collapse="<?= h(__('Collapse all')) ?>">
        <span data-vp-audit-expand-label><?= __('Expand all') ?></span>
    </button>
<?php
$headerExtra = ob_get_clean();

/*
 * The period control's bounds, taken from the rows rather than written
 * down. An input offering a month the log cannot hold invites a reader
 * to filter to an empty panel and read it as a quiet month.
 */
$stamps = array();
foreach ($history['groups'] as $group) {
    foreach ($group['entries'] as $entry) {
        $stamps[] = $entry['created'];
    }
}
foreach (($history['event_entries'] ?? array()) as $entry) {
    $stamps[] = $entry['created'];
}
sort($stamps);
$periodFirst = empty($stamps) ? null : $stamps[0];
$periodLast = empty($stamps) ? null : $stamps[count($stamps) - 1];
?>

<div class="row" data-vp-list data-vp-audit>

    <div class="col-lg-3">
        <div class="card shadow-sm mb-3 vp-panel"
             style="--vp-panel-color: <?= h($panelColour) ?>;">

            <?php
            ob_start();
            ?>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        data-vp-facet-clear disabled>
                    <?= __('Clear all') ?>
                </button>
            <?php
            $railExtra = ob_get_clean();
            ?>

            <?= $this->element('Values/View/value_panel_header', array(
                'panelTitle' => __('Filters'),
                'panelIcon' => 'fas fa-filter',
                'panelColor' => $panelColour,
                'panelSub' => sprintf(
                    __('%1$s entries · %2$s filters set'),
                    '<span data-vp-facet-rows>'
                        . h($history['entries']) . '</span>',
                    '<span data-vp-facet-count-active>0</span>'
                ),
                'panelExtra' => $railExtra,
            )) ?>

            <div class="p-3">
                <div class="vp-facet-note">
                    <?= __(
                        'Every count is what you may read, never what'
                        . ' the instance holds. A site admin sees more'
                        . ' rows here than you do.'
                    ) ?>
                </div>
                <?php if ($periodFirst !== null): ?>
                    <div class="vp-facetgrp">
                        <div class="vp-subhead">
                            <i class="fas fa-clock me-1"></i>
                            <?= __('Period') ?>
                        </div>
                        <div class="vp-facet-note">
                            <?= h(sprintf(
                                __('The log runs %1$s to %2$s. Both'
                                    . ' bounds are inclusive, and either'
                                    . ' one alone is a filter.'),
                                $fmt($periodFirst, 'j M Y'),
                                $fmt($periodLast, 'j M Y')
                            )) ?>
                        </div>
                        <div class="vp-audit-period">
                            <label class="form-label"
                                   for="vp-audit-from"><?= __('From') ?></label>
                            <input type="datetime-local"
                                   class="form-control form-control-sm"
                                   id="vp-audit-from" data-vp-filter-from
                                   min="<?= h($fmt($periodFirst, 'Y-m-d\TH:i')) ?>"
                                   max="<?= h($fmt($periodLast, 'Y-m-d\TH:i')) ?>">
                            <label class="form-label"
                                   for="vp-audit-to"><?= __('To') ?></label>
                            <input type="datetime-local"
                                   class="form-control form-control-sm"
                                   id="vp-audit-to" data-vp-filter-to
                                   min="<?= h($fmt($periodFirst, 'Y-m-d\TH:i')) ?>"
                                   max="<?= h($fmt($periodLast, 'Y-m-d\TH:i')) ?>">
                        </div>
                    </div>
                <?php endif; ?>
                <?php foreach ($railGroups as $group): ?>
                    <?= $this->element(
                        'Values/View/value_facet_group',
                        array(
                            'key' => $group['key'],
                            'title' => $group['title'],
                            'icon' => $group['icon'],
                            'values' => $group['rows'],
                            'note' => $group['note'],
                        )
                    ) ?>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

    <div class="col-lg-9">
        <div class="card shadow-sm mb-3 vp-panel"
             style="--vp-panel-color: <?= h($panelColour) ?>;">

            <?= $this->element('Values/View/value_panel_header', array(
                'panelTitle' => __('History'),
                'panelIcon' => $panelIcon,
                'panelColor' => $panelColour,
                'panelSub' => $headerLine,
                'panelExtra' => $headerExtra,
            )) ?>

            <?php
            /*
             * Directly under the header and not at the foot. This is
             * the one page in MISP where a reader can be misled into
             * thinking they are looking at the whole record, and the
             * sentence costs one line.
             *
             * The two numbers count the events holding a visible
             * occurrence, which is what
             * `AuditLogsController::__createEventIndexConditions`
             * actually branches on: every row for an event your
             * organisation created, and otherwise the event-level rows
             * plus the parts of it you may fetch.
             */
            ?>
            <div class="vp-acl-note vp-acl-note-band">
                <i class="fas fa-user-shield"></i>
                <span>
                    <?= h(sprintf(
                        __(
                            'You see every entry on the %1$d events'
                            . ' your organisation created. On the other'
                            . ' %2$d you see the event-level entries'
                            . ' and the entries on occurrences you may'
                            . ' read, and nothing else. A site admin'
                            . ' sees more rows here than you do.'
                        ),
                        $history['viewer_events'],
                        $history['other_events']
                    )) ?>
                </span>
            </div>

            <div data-vp-list-rows>

                <?php foreach ($history['groups'] as $index => $group): ?>
                    <?php
                    $sectionId = 'vp-audit-' . (int)$group['attribute_id'];
                    // The first section is open, the rest closed: a tab
                    // that opens six of these opens with nine hundred
                    // pixels of rows and no shape.
                    $open = $index === 0;
                    ?>
                    <div class="border-bottom"
                         data-vp-audit-section
                         data-vp-audit-total="<?= h($group['count']) ?>">

                        <div class="d-flex align-items-center gap-2 gap-lg-3
                                    p-2 px-3 flex-wrap">

                            <button type="button"
                                    class="btn btn-sm btn-link
                                           text-decoration-none p-0
                                           text-body d-flex
                                           align-items-center gap-2"
                                    data-vp-audit-toggle
                                    aria-expanded="<?= $open
                                        ? 'true'
                                        : 'false' ?>"
                                    aria-controls="<?= h($sectionId) ?>">
                                <i class="fas fa-chevron-<?= $open
                                    ? 'down'
                                    : 'right' ?>"
                                   data-vp-audit-chevron></i>
                                <span class="font-monospace small">
                                    <?= h($group['attribute_id']) ?>
                                </span>
                            </button>

                            <div class="vp-min-w-0 flex-grow-1">
                                <div class="small text-truncate">
                                    <?= h($group['event_info']) ?>
                                    <?php if ($group['deleted']): ?>
                                        <?= $deletedBadge() ?>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted"
                                     style="font-size: 0.7rem;">
                                    <?= h(sprintf(
                                        __('event %1$s · %2$s'),
                                        $group['event_id'],
                                        $group['org']
                                    )) ?>
                                </div>
                            </div>

                            <div class="flex-shrink-0"
                                 style="width: 8.5rem;">
                                <?= $renderMix(
                                    $group['mix'],
                                    $group['count']
                                ) ?>
                                <div class="text-muted"
                                     style="font-size: 0.7rem;"
                                     data-vp-audit-count
                                     data-vp-audit-plain="<?= h(sprintf(
                                         __n(
                                             '%d entry',
                                             '%d entries',
                                             $group['count']
                                         ),
                                         $group['count']
                                     )) ?>"
                                     data-vp-audit-tpl="<?= h(__n(
                                         '%1$s of %2$s entry',
                                         '%1$s of %2$s entries',
                                         $group['count']
                                     )) ?>"><?= h(sprintf(
                                         __n(
                                             '%d entry',
                                             '%d entries',
                                             $group['count']
                                         ),
                                         $group['count']
                                     )) ?></div>
                            </div>

                            <div class="text-muted text-end flex-shrink-0"
                                 style="width: 6rem; font-size: 0.7rem;">
                                <?php if ($group['last'] !== null): ?>
                                    <?= h($fmt($group['last'], 'j M Y')) ?>
                                <?php else: ?>
                                    <span class="fst-italic">
                                        <?= __('nothing logged') ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <a href="<?= $baseurl ?>/events/view2/<?=
                                h($group['event_id']) ?>"
                               class="flex-shrink-0 small"
                               title="<?= h(__(
                                   'Open the event holding this'
                                   . ' occurrence'
                               )) ?>">
                                <i class="fas fa-arrow-up-right-from-square">
                                </i>
                            </a>

                        </div>

                        <div id="<?= h($sectionId) ?>"
                             data-vp-audit-body
                             class="<?= $open ? '' : 'd-none' ?>">
                            <?php foreach ($group['entries'] as $row): ?>
                                <?= $renderRow($row) ?>
                            <?php endforeach; ?>

                            <?php if ($group['count'] > $pageSize): ?>
                                <div class="px-3 py-2 border-top">
                                    <?= $this->element(
                                        'Values/View/value_pager',
                                        array(
                                            'size' => $pageSize,
                                            'shown' => $group['count'],
                                            'total' => $group['count'],
                                            'noun' => __('entries'),
                                        )
                                    ) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endforeach; ?>

                <?php if (!empty($history['event_entries'])): ?>
                    <?php
                    /*
                     * Below a rule and outside the groups, because a
                     * publication belongs to the value's story and to
                     * none of its copies. Repeating these ten inside
                     * six sections would read as sixty things having
                     * happened; dropping them would lose every
                     * publication this value has.
                     */
                    $eventTotal = count($history['event_entries']);
                    ?>
                    <div class="border-top border-2"
                         data-vp-audit-section
                         data-vp-audit-total="<?= h($eventTotal) ?>">

                        <div class="d-flex align-items-center gap-3
                                    p-2 px-3 flex-wrap">
                            <button type="button"
                                    class="btn btn-sm btn-link
                                           text-decoration-none p-0
                                           text-body d-flex
                                           align-items-center gap-2"
                                    data-vp-audit-toggle
                                    aria-expanded="false"
                                    aria-controls="vp-audit-events">
                                <i class="fas fa-chevron-right"
                                   data-vp-audit-chevron></i>
                                <span class="small fw-bold">
                                    <?= __('Event-level actions') ?>
                                </span>
                            </button>
                            <div class="vp-min-w-0 flex-grow-1
                                        vp-fact-line-sub">
                                <?= __(
                                    'Publications and event tags. They'
                                    . ' belong to this value and to no'
                                    . ' single occurrence of it, so'
                                    . ' they are counted once here'
                                    . ' rather than repeated in every'
                                    . ' section above.'
                                ) ?>
                            </div>
                            <div class="text-muted flex-shrink-0"
                                 style="font-size: 0.7rem;"
                                 data-vp-audit-count
                                 data-vp-audit-plain="<?= h(sprintf(
                                     __n(
                                         '%d entry',
                                         '%d entries',
                                         $eventTotal
                                     ),
                                     $eventTotal
                                 )) ?>"
                                 data-vp-audit-tpl="<?= h(__n(
                                     '%1$s of %2$s entry',
                                     '%1$s of %2$s entries',
                                     $eventTotal
                                 )) ?>"><?= h(sprintf(
                                     __n(
                                         '%d entry',
                                         '%d entries',
                                         $eventTotal
                                     ),
                                     $eventTotal
                                 )) ?></div>
                        </div>

                        <div id="vp-audit-events" data-vp-audit-body
                             class="d-none">
                            <?php foreach (
                                $history['event_entries'] as $row
                            ): ?>
                                <?= $renderRow($row) ?>
                            <?php endforeach; ?>

                            <?php if ($eventTotal > $pageSize): ?>
                                <div class="px-3 py-2 border-top">
                                    <?= $this->element(
                                        'Values/View/value_pager',
                                        array(
                                            'size' => $pageSize,
                                            'shown' => $eventTotal,
                                            'total' => $eventTotal,
                                            'noun' => __('entries'),
                                        )
                                    ) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endif; ?>

            </div>

            <?php
            /*
             * Fires only where a filter produced the emptiness
             * (`00-shared.md` §5.1). A panel that was empty to begin
             * with has its own state above, and "no entry matches your
             * filter" over a value with no entries is a different and
             * false claim.
             */
            ?>
            <div class="vp-empty d-none" data-vp-list-empty>
                <i class="fas fa-filter"></i>
                <span>
                    <?= __(
                        'No entry matches these filters. The sections'
                        . ' above are still listed, because how many'
                        . ' occurrences this value has does not depend'
                        . ' on what you filtered.'
                    ) ?>
                </span>
            </div>

            <?php if ($history['hidden'] > 0): ?>
                <div class="vp-acl-note vp-acl-note-band border-bottom-0
                            border-top">
                    <i class="fas fa-eye-slash"></i>
                    <span>
                        <?= h(sprintf(
                            __n(
                                '%1$d of this value\'s %2$d occurrences'
                                . ' is on an event you cannot see, so'
                                . ' there is no section for it above.'
                                . ' How many entries it carries is not'
                                . ' obtainable either — the count is'
                                . ' itself a fact about that event.',
                                '%1$d of this value\'s %2$d occurrences'
                                . ' are on events you cannot see, so'
                                . ' there are no sections for them'
                                . ' above. How many entries they carry'
                                . ' is not obtainable either — the'
                                . ' count is itself a fact about those'
                                . ' events.',
                                $history['hidden']
                            ),
                            $history['hidden'],
                            $history['total_occurrences']
                        )) ?>
                    </span>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>
<?php endif; ?>
