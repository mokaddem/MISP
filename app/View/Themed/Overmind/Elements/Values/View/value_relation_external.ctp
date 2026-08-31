<?php
/**
 * Section four: where this value sits in somebody else's events.
 *
 * The fourth notion of "related" on this tab, and the only one whose
 * rows are not on this instance. A MISP-format feed or a cached sync
 * server holds the value inside a remote event, and that event can be
 * named and opened — co-occurrence outside the instance boundary, which
 * nothing else on this page can see past.
 *
 * Three things this section is careful about:
 *
 *   the count matches   a source that holds the value but publishes no
 *                       event still gets a row, marked as such. Drop
 *                       those and the Overview card's number stops
 *                       matching the rows here, which makes a reader
 *                       distrust both.
 *   the notice is role  "your role cannot see this" is keyed on the
 *                       reader and shown on every value alike, never on
 *                       whether this value hit something withheld. A
 *                       notice that appeared only when something was
 *                       hidden would be an existence oracle at one bit
 *                       (`live/00-contract.md` §14.6).
 *   the link leaves     opening a remote event previews it from the
 *                       feed or server at request time. It is the only
 *                       affordance on this page that is not a local
 *                       read, so it says so — and it says *preview*
 *                       rather than *fetch*, because a MISP reader hears
 *                       "fetch" as pulling the event into this instance,
 *                       which is precisely what previewEvent does not
 *                       do.
 *
 * Lazily loaded from ValuesController::viewRelationExternal.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$external = $valueProfile['external'];
$sources = $external['sources'];
$counts = $external['counts'];
$restricted = $external['restricted'];
$cached = $external['cached'];

$nothingCached = empty($cached['feeds']) && empty($cached['servers']);
/*
 * Shown whenever this reader's role cannot reach a kind of source the
 * instance holds — on every value alike, and whether or not this one hit
 * anything. Gating it on "something was withheld for this value" would
 * make its presence the disclosure §14.6 forbids; gating it on "no hits
 * at all" would leave a reader who can see feeds never told that servers
 * are withheld from them.
 */
$roleRestricted = $restricted['feeds'] || $restricted['servers'];

$icon = 'fas fa-cloud-arrow-down';
?>
<div class="card shadow-sm mb-3 vp-panel vp-rel-k-external"
     style="--vp-panel-color: var(--vp-rel-external);"
     id="vp-external-presence"
     data-vp-list>

    <?php
    ob_start();
    ?>
        <span class="vp-rel-tag me-1">
            <i class="<?= h($icon) ?>"></i><?= h(__('Outside this instance')) ?>
        </span>
        <?php if (!empty($sources)): ?>
            <?= h(sprintf(
                __('%1$s across %2$s'),
                __n('%d remote event', '%d remote events',
                    $external['events'], $external['events']),
                implode(', ', array_filter(array(
                    $counts['feeds']
                        ? sprintf(__n('%d feed', '%d feeds',
                            $counts['feeds']), $counts['feeds'])
                        : null,
                    $counts['servers']
                        ? sprintf(__n('%d sync server', '%d sync servers',
                            $counts['servers']), $counts['servers'])
                        : null,
                )))
            )) ?>
            &nbsp;·&nbsp;
        <?php endif; ?>
        <span class="vp-rel-prov"><i class="fas fa-gauge"></i><?=
            h(__('Machine-derived')) ?></span>
        &nbsp;·&nbsp;<?= h(__('feed cache')) ?>
    <?php
    $headerSub = ob_get_clean();
    ?>

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Outside this instance'),
        'panelIcon' => $icon,
        'panelColor' => 'var(--vp-rel-external)',
        'panelSub' => $headerSub,
    )) ?>

    <div class="p-3">

        <div class="vp-rel-cap">
            <i class="fas fa-circle-info"></i>
            <span>
                <?= __('A hit here means a feed or sync server cache holds this exact value. It is set membership on a hash — no CIDR, no substring and no near-match — so it is never a statement that two values are alike.') ?>
            </span>
        </div>

        <?php if ($roleRestricted): ?>
            <div class="vp-acl-note-band">
                <i class="fas fa-lock"></i>
                <span>
                    <?php if ($restricted['feeds'] && $restricted['servers']): ?>
                        <?= __('Your role cannot view feed correlations, and sync server hits require site admin. Neither is counted here, on any value.') ?>
                    <?php elseif ($restricted['feeds']): ?>
                        <?= __('Your role cannot view feed correlations, so feeds an administrator has not published for lookup are not counted here, on any value.') ?>
                    <?php else: ?>
                        <?= __('Sync server hits require site admin, so they are not counted here, on any value.') ?>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if ($nothingCached): ?>

            <div class="vp-empty">
                <i class="fas fa-cloud"></i>
                <span>
                    <?= __('No feed or sync server on this instance has caching enabled, so there is nothing to look this value up in.') ?>
                </span>
            </div>

        <?php elseif (empty($sources)): ?>

            <div class="vp-empty">
                <i class="<?= h($icon) ?>"></i>
                <span>
                    <?= __('No feed or sync server you can see holds this value.') ?>
                </span>
            </div>

        <?php else: ?>

            <div class="table-responsive" data-vp-list-rows>
                <table class="table table-sm table-hover vp-table
                              align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?= __('Source') ?></th>
                            <th scope="col"><?= __('Kind') ?></th>
                            <th scope="col"><?= __('Remote events') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sources as $source): ?>
                            <tr class="vp-rel-stripe vp-rel-k-external">
                                <td>
                                    <div class="vp-rel-cell fw-semibold"
                                         title="<?= h($source['name']) ?>">
                                        <?= h($source['name']) ?>
                                    </div>
                                    <?php if (!empty($source['url'])): ?>
                                        <div class="vp-fact-line-sub vp-rel-cell"
                                             title="<?= h($source['url']) ?>">
                                            <?= h($source['url']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="vp-rel-tag">
                                        <i class="fas <?= $source['scope'] === 'server'
                                            ? 'fa-server' : 'fa-rss' ?>"></i><?=
                                            h($source['kind']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (empty($source['events'])): ?>
                                        <span class="vp-empty-inline">
                                            <?= __('Holds the value; publishes no event to open') ?>
                                        </span>
                                    <?php else: ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($source['events'] as $event): ?>
                                                <a class="vp-external-event"
                                                   href="<?= h($event['url']) ?>"
                                                   title="<?= h(sprintf(
                                                       __('Preview this event from %s'),
                                                       $source['name']
                                                   )) ?>">
                                                    <i class="fas fa-arrow-up-right-from-square"></i>
                                                    <?= h($event['name']) ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if ($source['events_total'] > count($source['events'])): ?>
                                            <div class="vp-fact-line-sub">
                                                <?= h(sprintf(
                                                    __('Showing %1$s of %2$s.'),
                                                    count($source['events']),
                                                    $source['events_total']
                                                )) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="vp-fact-line-sub mt-2">
                <i class="fas fa-arrow-up-right-from-square"></i>
                <?= __('Opening a remote event previews it from the feed or server as it is right now. Every other link on this page reads local data.') ?>
            </div>

        <?php endif; ?>

    </div>

</div>
