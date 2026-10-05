<?php
App::uses('ValueLabelPriority', 'Tools/ValueProfile');

$eventUuid = $data['Event']['uuid'] ?? '';
$confidence = [];
foreach ($data['EventTag'] ?? [] as $eventTag) {
    $namespace = ValueLabelPriority::namespaceOf($eventTag['Tag']['name'] ?? '');
    if (in_array($namespace, ['admiralty-scale', 'estimative-language'], true)) {
        $confidence[] = $eventTag;
    }
}
$opinions = $data['Opinion'] ?? [];
$notes = $data['Note'] ?? [];
$relationships = $data['Relationship'] ?? [];
$hasAny = !empty($confidence) || !empty($opinions) || !empty($notes) || !empty($relationships);
$canAdd = !empty($me['Role']['perm_analyst_data']) && $eventUuid !== '';
$addUrl = function ($type) use ($baseurl, $eventUuid) {
    return $baseurl . '/analystData/add/' . $type . '/' . rawurlencode($eventUuid) . '/Event';
};
$scores = array_filter(array_map(function ($opinion) {
    return isset($opinion['opinion']) ? (int)$opinion['opinion'] : null;
}, $opinions), 'is_int');
?>
<div class="card shadow-sm mb-3 eo-card eo-assessment" id="analyst-data-card">
    <div class="eo-assessment-line">
        <div class="misp-icon-tile eo-tile eo-tile-sm" style="--tile:var(--bs-analystData);--tile-bg:color-mix(in srgb, var(--bs-analystData) 12%, transparent);">
            <i class="fas fa-scale-balanced"></i>
        </div>
        <b><?= __('Assessment') ?></b>
        <?php if (!$hasAny): ?>
            <span class="eo-muted"><?= __('No assessment yet') ?></span>
        <?php else: ?>
            <span class="eo-assessment-facts">
                <?php foreach ($confidence as $eventTag): ?>
                    <?= $this->TagChip->chip($eventTag, ['searchUrl' => '']) ?>
                <?php endforeach; ?>
                <?php if (!empty($opinions)): ?>
                    <span class="eo-fact">
                        <i class="misp-icon misp-icon-analyst-opinion misp-simple"></i>
                        <?= h(__n('%s opinion', '%s opinions', count($opinions), count($opinions))) ?>
                        <?php if (!empty($scores)): ?>
                            <span class="eo-muted"><?= h(__('avg. %s/100', (int)round(array_sum($scores) / count($scores)))) ?></span>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($notes)): ?>
                    <span class="eo-fact"><i class="misp-icon misp-icon-analyst-note misp-simple"></i><?= h(__n('%s note', '%s notes', count($notes), count($notes))) ?></span>
                <?php endif; ?>
                <?php if (!empty($relationships)): ?>
                    <span class="eo-fact"><i class="fas fa-diagram-project"></i><?= h(__n('%s relationship', '%s relationships', count($relationships), count($relationships))) ?></span>
                <?php endif; ?>
            </span>
        <?php endif; ?>
        <span class="ms-auto d-inline-flex gap-1 flex-shrink-0">
            <?php if ($hasAny && (!empty($opinions) || !empty($notes) || !empty($relationships))): ?>
                <button type="button" class="btn btn-sm btn-link px-1" data-bs-toggle="collapse" data-bs-target="#eo-analyst-data" aria-expanded="false">
                    <?= __('Show') ?>
                </button>
            <?php endif; ?>
            <?php if ($canAdd): ?>
                <button type="button" class="btn btn-sm btn-outline-success" onclick="openModal('<?= h($addUrl('Opinion')) ?>')">
                    <i class="misp-icon misp-icon-analyst-opinion misp-simple me-1"></i><?= __('Add opinion') ?>
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="openModal('<?= h($addUrl('Note')) ?>')">
                    <i class="misp-icon misp-icon-analyst-note misp-simple me-1"></i><?= __('Add note') ?>
                </button>
            <?php endif; ?>
        </span>
    </div>
    <?php if ($hasAny): ?>
        <div class="collapse" id="eo-analyst-data">
            <div class="border-top" data-eo-fragment-lazy="<?= h($baseurl . '/analystData/viewForObject/Event/' . rawurlencode($eventUuid) . '?embedded=1') ?>">
                <div class="text-center text-muted py-3"><div class="misp-loader misp-loader-sm" role="status"></div></div>
            </div>
        </div>
    <?php endif; ?>
</div>
