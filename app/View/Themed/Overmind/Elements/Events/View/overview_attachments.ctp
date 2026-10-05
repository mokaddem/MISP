<?php
$eventId = (int)($data['Event']['id'] ?? 0);
$uploadUrl = $baseurl . '/attributes/add_attachment/' . $eventId;
$mayUpload = $this->Acl->canModifyEvent($data) || !empty($isSiteAdmin);
?>
<div class="card shadow-sm mb-3 eo-card" id="attachment-card">
    <div class="eo-card-head">
        <div class="misp-icon-tile eo-tile" style="--tile:#F59E0B;--tile-bg:#F59E0B18;">
            <i class="fas fa-paperclip"></i>
        </div>
        <div class="min-w-0 me-auto">
            <div class="eo-card-title"><?= __('Attachments') ?></div>
            <div class="eo-card-sub" data-eo-attachment-figure>&nbsp;</div>
        </div>
        <?php if ($mayUpload): ?>
            <a href="<?= h($uploadUrl) ?>" class="btn btn-sm btn-outline-secondary"
               onclick="event.preventDefault(); openModal('<?= h($uploadUrl) ?>')">
                <i class="fas fa-upload me-1"></i><?= __('Upload') ?>
            </a>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-primary" data-eo-download-all disabled>
            <i class="fas fa-download me-1"></i><?= __('Download All') ?>
        </button>
    </div>
    <div data-eo-fragment="<?= h($baseurl . '/events/viewAttachments/' . $eventId . '/compact:1') ?>">
        <div class="text-center text-muted py-3"><div class="misp-loader misp-loader-sm" role="status"></div></div>
    </div>
</div>
