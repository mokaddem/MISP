<?php
$total = $positive + $negative;
?>

<div data-sighting-total="<?= $total ?>">

<?php if ($total === 0): ?>

    <div class="d-flex flex-column align-items-center justify-content-center
                text-muted py-4">
        <span class="misp-icon misp-icon-sighting misp-hexagone mb-2 opacity-50" style="font-size:2em;"></span>
        <p class="mb-0 small fw-semibold">
            <?= __('No sightings recorded yet.') ?>
        </p>
    </div>

<?php else: ?>

    <div class="d-flex gap-3 p-3">

        <!-- Positive -->
        <div class="flex-fill rounded-3 p-3 d-flex flex-column gap-1"
             style="background:var(--misp-tone-green-bg, #f0fdf4);
                    border:1px solid var(--misp-tone-green-border, #bbf7d0);">
            <div class="d-flex align-items-center gap-2">
                <span class="rounded-circle d-flex align-items-center
                             justify-content-center"
                      style="width:32px;height:32px;
                             background:var(--misp-tone-green-solid, #16a34a);">
                    <i class="fas fa-thumbs-up"
                       style="font-size:.8rem;
                              color:var(--misp-surface, #fff);"></i>
                </span>
                <span class="small fw-semibold"
                      style="color:var(--misp-tone-green-fg,
                                       var(--bs-secondary-color));">
                    <?= __('Positive') ?>
                </span>
            </div>
            <div class="fw-bold ms-1" style="font-size:1.6rem;
                        color:var(--misp-tone-green-fg, #15803d);
                        line-height:1;">
                <?= (int)$positive ?>
            </div>
        </div>

        <!-- Negative / False-positive -->
        <div class="flex-fill rounded-3 p-3 d-flex flex-column gap-1"
             style="background:var(--misp-tone-red-bg, #fff1f2);
                    border:1px solid var(--misp-tone-red-border, #fecdd3);">
            <div class="d-flex align-items-center gap-2">
                <span class="rounded-circle d-flex align-items-center
                             justify-content-center"
                      style="width:32px;height:32px;
                             background:var(--misp-tone-red-solid, #dc2626);">
                    <i class="fas fa-thumbs-down"
                       style="font-size:.8rem;
                              color:var(--misp-surface, #fff);"></i>
                </span>
                <span class="small fw-semibold"
                      style="color:var(--misp-tone-red-fg,
                                       var(--bs-secondary-color));">
                    <?= __('False positive') ?>
                </span>
            </div>
            <div class="fw-bold ms-1" style="font-size:1.6rem;
                        color:var(--misp-tone-red-fg, #b91c1c);
                        line-height:1;">
                <?= (int)$negative ?>
            </div>
        </div>

    </div>

<?php endif; ?>

</div>
