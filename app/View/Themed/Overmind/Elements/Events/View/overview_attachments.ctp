<?php
$eventId = (int)($data['Event']['id'] ?? 0);
?>
<div class="card shadow-sm mb-3 eo-card" id="attachment-card">
    <div class="eo-card-head">
        <div class="misp-icon-tile eo-tile" style="--tile:#F59E0B;--tile-bg:#F59E0B18;">
            <i class="fas fa-paperclip"></i>
        </div>
        <div class="min-w-0 me-auto">
            <div class="eo-card-title"><?= __('Attachments') ?></div>
        </div>
        <div class="eo-figure" data-eo-attachment-figure></div>
    </div>
    <div data-eo-fragment="<?= h($baseurl . '/events/viewAttachments/' . $eventId . '/compact:1') ?>">
        <div class="text-center text-muted py-3"><div class="misp-loader misp-loader-sm" role="status"></div></div>
    </div>
</div>
