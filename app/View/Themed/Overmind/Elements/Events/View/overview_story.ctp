<?php
$event = $data['Event'] ?? [];
$eventId = (int)($event['id'] ?? 0);
$report = $data['EventReport'] ?? null;
$others = $data['OtherEventReports'] ?? [];
$reportCount = (int)($report_count ?? (empty($report) ? 0 : 1 + count($others)));
$canAddReport = $this->Acl->canModifyEvent($data);
$addReportUrl = $baseurl . '/event_reports/add/' . $eventId;
?>
<div class="row g-3 mb-3 eo-row">

    <div class="col-12 col-xl-6 d-flex">
        <div class="card shadow-sm eo-card w-100" id="eo-report-card"
             <?php if (!empty($report)): ?>
             data-eo-report-id="<?= (int)$report['id'] ?>"
             data-eo-event-id="<?= $eventId ?>"
             data-eo-report-content="<?= h($report['content'] ?? '') ?>"
             <?php endif; ?>>
            <div class="eo-card-head">
                <div class="misp-icon-tile eo-tile" style="--tile:var(--bs-report);--tile-bg:color-mix(in srgb, var(--bs-report) 12%, transparent);">
                    <i class="misp-icon misp-icon-report misp-simple"></i>
                </div>
                <div class="min-w-0 me-auto">
                    <?php if (!empty($report)): ?>
                        <div class="eo-card-title text-truncate"><?= h($report['name']) ?></div>
                        <div class="eo-card-sub text-truncate">
                            <?= h($data['Orgc']['name'] ?? '') ?> · <?= __('modified %s', $this->Time->time($report['timestamp'])) ?>
                        </div>
                    <?php else: ?>
                        <div class="eo-card-title"><?= __('Report') ?></div>
                    <?php endif; ?>
                </div>
                <div class="eo-figure"><b><?= $reportCount ?></b> <?= h(__n('report', 'reports', $reportCount)) ?></div>
            </div>

            <?php if (!empty($report)): ?>
                <div class="eo-report-body">
                    <div class="eo-report-clamp">
                        <div class="markdown-preview-body" data-eo-report-preview>
                            <div class="text-center text-muted py-4"><div class="misp-loader misp-loader-sm" role="status"></div></div>
                        </div>
                    </div>
                    <div class="eo-report-fade" aria-hidden="true"></div>
                </div>
                <?php if (!empty($others)): ?>
                    <ul class="eo-other-reports">
                        <?php foreach ($others as $other): ?>
                            <li>
                                <a href="<?= h($baseurl . '/eventReports/view/' . (int)$other['id']) ?>" class="text-truncate">
                                    <i class="misp-icon misp-icon-report misp-simple"></i><?= h($other['name']) ?>
                                </a>
                                <span class="eo-muted"><?= $this->Time->time($other['timestamp']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="eo-card-foot">
                    <button type="button" class="btn btn-sm btn-report" data-eo-report-open>
                        <i class="fas fa-book-open me-1"></i><?= __('Read in full') ?>
                    </button>
                </div>
            <?php else: ?>
                <div class="eo-empty">
                    <span><?= __('No report yet.') ?></span>
                    <?php if ($canAddReport): ?>
                        <a href="<?= h($addReportUrl) ?>" class="btn btn-sm btn-outline-report"
                           onclick="event.preventDefault(); openModal('<?= h($addReportUrl) ?>');">
                            <i class="fas fa-plus me-1"></i><?= __('Write a report') ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-xl-6 d-flex">
        <div class="card shadow-sm eo-card w-100" id="eo-graph-card"
             data-eo-graph-url="<?= h($baseurl . '/events/viewEventOverviewGraph/' . $eventId . '.json') ?>"
             data-eo-event-id="<?= $eventId ?>"
             data-eo-event-uuid="<?= h($event['uuid'] ?? '') ?>"
             data-eo-text-summary="<?= h(__('%s objects reference each other — too many to draw here.')) ?>"
             data-eo-text-empty="<?= h(__('No object references another one in this event.')) ?>"
             data-eo-text-failed="<?= h(__('The graph could not be drawn.')) ?>"
             data-eo-text-counts="<?= h(__('%1$s objects, %2$s references')) ?>"
             data-eo-text-saved="<?= h(__('Saved graph: %s')) ?>"
             data-eo-text-saved-count="<?= h(__('%s saved graphs')) ?>">
            <div class="eo-card-head">
                <div class="misp-icon-tile eo-tile" style="--tile:var(--bs-correlation);--tile-bg:color-mix(in srgb, var(--bs-correlation) 12%, transparent);">
                    <i class="fas fa-circle-nodes"></i>
                </div>
                <div class="min-w-0 me-auto">
                    <div class="eo-card-title"><?= __('Event graph') ?></div>
                    <div class="eo-card-sub text-truncate" data-eo-graph-sub>&nbsp;</div>
                </div>
                <div class="eo-switch" role="group" aria-label="<?= h(__('Graph source')) ?>">
                    <button type="button" data-eo-graph-mode="saved" disabled
                            title="<?= h(__('No saved graph on this event')) ?>"
                            data-eo-title-ready="<?= h(__('Show the saved graph')) ?>">
                        <i class="fas fa-bookmark"></i><?= __('Saved graph') ?>
                    </button>
                    <button type="button" class="active" data-eo-graph-mode="structure"
                            title="<?= h(__('Show how the objects reference each other')) ?>">
                        <i class="fas fa-sitemap"></i><?= __('Structure') ?>
                    </button>
                </div>
            </div>
            <div class="eo-graph-stage">
                <div class="eo-graph-loader" data-eo-graph-loader>
                    <div class="misp-loader misp-loader-sm" role="status"></div>
                </div>
                <div class="eo-graph-canvas" data-eo-graph-canvas="structure"></div>
                <div class="eo-graph-canvas d-none" data-eo-graph-canvas="saved"></div>
                <div class="eo-graph-message d-none" data-eo-graph-message></div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($report)): ?>
<div class="modal fade" id="eo-report-modal" tabindex="-1" aria-labelledby="eo-report-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="min-w-0">
                    <h5 class="modal-title text-truncate" id="eo-report-modal-title"><?= h($report['name']) ?></h5>
                    <div class="eo-card-sub"><?= h($data['Orgc']['name'] ?? '') ?> · <?= __('modified %s', $this->Time->time($report['timestamp'])) ?></div>
                </div>
                <a class="btn btn-sm btn-outline-secondary ms-auto me-2" href="<?= h($baseurl . '/eventReports/view/' . (int)$report['id']) ?>"><?= __('Open report page') ?></a>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(__('Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <div class="markdown-preview-body eo-report-full" data-eo-report-full></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
