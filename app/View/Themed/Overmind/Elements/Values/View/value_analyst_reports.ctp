<?php
/**
 * The event reports written about the events this value sits in.
 *
 * The third panel on the Analyst tab, and the one that is a list rather
 * than an argument. `value-profile-coverage.md` §4.5 places reports
 * here — narrative analyst content about the value's context, beside
 * the notes and opinions — and they are a panel of their own rather
 * than more thread items because a report is a document, not a turn in
 * a conversation: dropped between two one-line notes it buries both.
 *
 * **Every row is about an event and says so.** Analyst data attaches to
 * a uuid and a report attaches to an event; nothing attaches to a
 * value. What this panel can state honestly is *a report was written
 * about an event this value is in*, which is the same sentence the
 * thread's event-level items carry.
 *
 * **The extract is the head of the report, not a summary.** MISP has no
 * summary of a report and this page will not write one — an abstract
 * the page invented would be the page's claim about somebody's
 * document. The first few hundred characters are the document's own
 * opening, marked as truncated, with the full length beside it.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewAnalystReports.
 *
 * @var array $valueProfile
 */
$reports = $valueProfile['analyst_reports'];
$rows = $reports['rows'];

/**
 * A report's opening, as text.
 *
 * The body is markdown and this is a four-line extract, so the markup
 * is stripped rather than rendered: half a rendered heading and an
 * orphaned list bullet read as a broken document, where a paragraph of
 * plain prose reads as the start of one. Escaped on the way out like
 * everything else.
 *
 * @param string $head
 * @return string
 */
$extract = function ($head) {
    $text = preg_replace('/```.*?```/s', ' ', $head);
    /*
     * MISP's own element references first — `@![attribute](uuid)` and
     * its object and tag siblings. They render as a card in the report
     * and as a uuid in an extract, which is the one thing an opening
     * paragraph must not be made of.
     */
    $text = preg_replace('/@!?\[[^\]]*\]\([^)]*\)/', '', $text);
    // Then images, which are a filename and a path, and then links,
    // which keep the words and drop the URL.
    $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $text);
    $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text);
    $text = preg_replace('/^\s*[#>]+\s*/m', '', $text);
    $text = preg_replace('/^\s*[-*]\s+/m', '', $text);
    // `*` and backticks, but never `_`: a report full of snake_case
    // identifiers is more common than one using underscore emphasis.
    $text = preg_replace('/[`*]+/', '', $text);
    // The editor escapes punctuation on save, so the stored bytes read
    // `RFC 7231\)` and an extract that kept the backslash would be
    // showing the reader the storage format.
    $text = preg_replace('/\\\\([\\\\`*_{}\[\]()#+\-.!])/', '$1', $text);
    $text = trim(preg_replace('/\s+/', ' ', $text));
    return $text;
};

$distribution = function ($level, $sharingGroup) {
    $levels = array(
        0 => array(__('Your organisation only'), 'fas fa-lock'),
        1 => array(__('This community only'), 'fas fa-share-nodes'),
        2 => array(__('Connected communities'), 'fas fa-share-nodes'),
        3 => array(__('All communities'), 'fas fa-share-nodes'),
    );
    if ((int)$level === 4) {
        return array(
            $sharingGroup === null ? __('Sharing group') : $sharingGroup,
            'misp-icon misp-icon-sharing-group misp-simple',
        );
    }
    return isset($levels[(int)$level])
        ? $levels[(int)$level]
        : array(__('Inherit event'), 'fas fa-share-nodes');
};

/*
 * Nothing to count is a state. `0 reports across 20 events` is a
 * sentence about a panel with no rows, and it is the kind of sentence
 * that makes a working page look broken.
 */
$subtitle = empty($rows)
    ? h(__('No event report on any event this value appears in'))
    : implode(' &nbsp;·&nbsp; ', array_filter(array(
        h(sprintf(
            __('%1$s on %2$s'),
            __n('%s report', '%s reports', $reports['total'],
                $reports['total']),
            __n('%s event', '%s events', $reports['events'],
                $reports['events'])
        )),
        $reports['withdrawn'] > 0
            ? h(sprintf(
                __n(
                    '%s withdrawn',
                    '%s withdrawn',
                    $reports['withdrawn']
                ),
                $reports['withdrawn']
            ))
            : null,
        h(__('newest change first')),
    )));
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--analystData);"
     data-vp-analyst-reports>

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Event reports'),
        'panelIcon' => 'fas fa-file-lines',
        'panelColor' => 'var(--analystData)',
        'panelSub' => $subtitle,
    )) ?>

    <?php if (empty($rows)): ?>
        <div class="vp-empty">
            <i class="fas fa-file-lines"></i>
            <span><?= __(
                'Nobody has written an event report on an event this'
                . ' value appears in.'
            ) ?></span>
        </div>
    <?php else: ?>
        <div class="p-3">
            <?php foreach ($rows as $row):
                list($distLabel, $distIcon) = $distribution(
                    $row['distribution'],
                    $row['sharing_group']
                );
                $body = $extract($row['head']);
                $truncated = $row['length'] > mb_strlen($row['head']);
                ?>
                <div class="vpa-report<?=
                    $row['withdrawn'] ? ' vpa-report-gone' : '' ?>">

                    <div class="d-flex align-items-center gap-2 flex-wrap
                                mb-1">
                        <a class="vpa-report-name"
                           href="<?= h($baseurl) ?>/eventReports/view/<?=
                               (int)$row['id'] ?>"
                           title="<?= h(__('Open the report')) ?>"><?=
                            h($row['name'] === '' || $row['name'] === null
                                ? __('(untitled report)')
                                : $row['name'])
                        ?></a>

                        <?php if ($row['withdrawn']): ?>
                            <span class="badge bg-secondary-subtle
                                         text-secondary-emphasis border
                                         border-secondary-subtle
                                         fw-semibold"
                                  title="<?= h(__(
                                      'Soft-deleted. A withdrawn report'
                                      . ' saves only its deleted column,'
                                      . ' so its date is still the day'
                                      . ' its content last changed.'
                                  )) ?>"><?= __('Withdrawn') ?></span>
                        <?php endif; ?>

                        <span class="ms-auto vpa-chip"
                              title="<?= h(__(
                                  'A report is written about an event,'
                                  . ' never about a value — this one is'
                                  . ' on an event this value appears in'
                              )) ?>">
                            <span class="misp-icon misp-icon-event
                                         misp-simple"></span>
                            <?= h('#' . $row['event']['id']) ?>
                            <?php if (!empty($row['event']['info'])): ?>
                                <span class="vpa-chip-sep vp-min-w-0
                                             text-truncate"><?=
                                    h($row['event']['info'])
                                ?></span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php if ($body !== ''): ?>
                        <div class="vpa-report-extract"><?= h($body)
                            ?><?php if ($truncated): ?><span
                                class="vpa-report-more"
                                title="<?= h(sprintf(
                                    __('The report is %s characters long;'
                                        . ' this is its opening.'),
                                    $row['length']
                                )) ?>">&nbsp;<?= __('…') ?></span>
                            <?php endif; ?></div>
                    <?php endif; ?>

                    <div class="vp-analyst-meta d-flex align-items-center
                                flex-wrap gap-2">
                        <span>
                            <span class="misp-icon
                                         misp-icon-organisation
                                         misp-simple me-1"></span>
                            <?= h($row['org']) ?>
                            <?php if ($row['at'] !== null): ?>
                                &nbsp;&middot;&nbsp;
                                <i class="fas fa-clock me-1"></i>
                                <span title="<?= h(__(
                                    'When the report last changed.'
                                    . ' event_reports has no creation'
                                    . ' date — its one timestamp is'
                                    . ' rewritten on every edit.'
                                )) ?>"><?= h($row['at']) ?>
                                    <?= __('(last changed)') ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="badge bg-body-tertiary
                                     text-body-secondary border fw-normal"
                              title="<?= h(sprintf(
                                  __('distribution %s'),
                                  $row['distribution']
                              )) ?>">
                            <?php if ($distIcon === 'fas fa-lock'
                                || $distIcon === 'fas fa-share-nodes'): ?>
                                <i class="<?= $distIcon ?> me-1"></i>
                            <?php else: ?>
                                <span class="<?= $distIcon ?> me-1"></span>
                            <?php endif; ?>
                            <?= h($distLabel) ?>
                        </span>
                        <span class="vpa-chip"><?= h(sprintf(
                            __n(
                                '%s character',
                                '%s characters',
                                $row['length']
                            ),
                            $row['length']
                        )) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!empty($reports['capped'])): ?>
                <p class="vp-aside-note"><?= h(sprintf(
                    __('Showing the %1$s most recently changed of %2$s.'
                        . ' There is no page control here: the rows come'
                        . ' from every event this value appears in, and'
                        . ' a value in thousands of them would be'
                        . ' paginating a list nobody asked for.'),
                    count($rows),
                    $reports['total']
                )) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($reports['occurrence_capped'])): ?>
        <?php
        /*
         * Outside the empty branch on purpose. A value past the
         * occurrence cap with no report on the events this read reached
         * renders *nobody has written one* over a slice of its events,
         * which is the one arrangement where the empty state is a
         * false statement rather than a true one.
         */
        ?>
        <p class="vp-aside-note"><?= h(sprintf(
            __('This value has more occurrences than one read takes, so'
                . ' this list covers the %s events its most recent'
                . ' occurrences sit in. A report on an older event is'
                . ' not listed.'),
            $reports['events']
        )) ?></p>
    <?php endif; ?>

</div>
