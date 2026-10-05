<?php
$event = $data['Event'] ?? [];
$analysisStops = [
    ['value' => 0, 'title' => __('Initial'), 'tone' => 'var(--misp-tone-blue-solid, #0d6efd)', 'sub' => __('Raw intelligence')],
    ['value' => 1, 'title' => __('Ongoing'), 'tone' => 'var(--misp-tone-orange-solid, #fd7e14)', 'sub' => __('Under investigation')],
    ['value' => 2, 'title' => __('Completed'), 'tone' => 'var(--misp-tone-green-solid, #198754)', 'sub' => __('Verified & closed')],
];
/* Lowest risk first — the ids themselves run the other way (1 is High). */
$threatStops = [
    ['value' => 4, 'title' => __('Undefined'), 'tone' => 'var(--misp-tone-gray-solid, #41464b)', 'sub' => __('No risk')],
    ['value' => 3, 'title' => __('Low'), 'tone' => 'var(--misp-tone-yellow-solid, #ffc107)', 'sub' => __('Opportunistic')],
    ['value' => 2, 'title' => __('Medium'), 'tone' => 'var(--misp-tone-orange-solid, #fd7e14)', 'sub' => __('Targeted campaign')],
    ['value' => 1, 'title' => __('High'), 'tone' => 'var(--misp-tone-red-solid, #dc3545)', 'sub' => __('Active exploitation')],
];
?>
<div class="card shadow-sm mb-3 eo-card eo-details">
    <button type="button" class="eo-details-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#eo-details" aria-expanded="false" aria-controls="eo-details">
        <i class="fas fa-chevron-right eo-chevron"></i><?= __('Details') ?>
        <span class="eo-muted small ms-2"><?= __('Analysis, threat level, correlation, protection') ?></span>
    </button>
    <div class="collapse" id="eo-details">
        <div class="eo-details-body">
            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <div class="eo-band-label"><?= __('Analysis') ?></div>
                    <?= $this->element('genericElementsBS5/Forms/choice_slider', [
                        'field' => 'analysis',
                        'value' => (int)($event['analysis'] ?? 0),
                        'options' => $analysisStops,
                        'readonly' => true,
                    ]) ?>
                </div>
                <div class="col-12 col-md-6">
                    <div class="eo-band-label"><?= __('Threat Level') ?></div>
                    <?= $this->element('genericElementsBS5/Forms/choice_slider', [
                        'field' => 'threat_level_id',
                        'value' => (int)($event['threat_level_id'] ?? 4),
                        'options' => $threatStops,
                        'readonly' => true,
                    ]) ?>
                </div>
                <div class="col-12 col-md-6">
                    <div class="eo-band-label"><?= __('Correlation') ?></div>
                    <?= $this->element('genericElementsBS5/Badges/boolean', [
                        'boolean' => empty($event['disable_correlation']),
                        'full' => true,
                        'true' => __('Enabled'),
                        'false' => __('Disabled'),
                        'trueColor' => 'success',
                        'falseColor' => 'danger',
                        'trueIcon' => 'fa-link',
                        'falseIcon' => 'fa-unlink',
                    ]) ?>
                </div>
                <div class="col-12 col-md-6">
                    <div class="eo-band-label"><?= __('Protection') ?></div>
                    <?= $this->element('genericElementsBS5/Badges/boolean', [
                        'boolean' => !empty($event['protected']),
                        'full' => true,
                        'true' => __('Protected'),
                        'false' => __('Unprotected'),
                        'trueColor' => 'warning',
                        'falseColor' => 'secondary',
                        'trueIcon' => 'fa-shield-alt',
                        'falseIcon' => 'fa-shield-alt',
                    ]) ?>
                </div>
                <?= $this->element('Events/View/event_extensions', ['data' => $data]) ?>
            </div>
        </div>
    </div>
</div>
