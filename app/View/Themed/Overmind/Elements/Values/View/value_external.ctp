<?php
/**
 * Where this value appears outside this instance's own events.
 *
 * "Is anyone outside our instance seeing this?" — the feeds carrying it,
 * the sync servers whose cache matches, and the SightingDB count. All
 * three are corroboration from somewhere this instance does not control,
 * which is why they sit apart from the sightings card.
 *
 * Lazily loaded into `.ajax-card` from ValuesController::viewExternal.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$external = $valueProfile['external'];
$feeds = $external['feeds'];
$anything = !empty($feeds)
    || !empty($external['servers'])
    || !empty($external['sightingdb']);

$subtitle = $anything
    ? h(sprintf(__('%s feeds carry it'), count($feeds)))
    : h(__('Not seen outside this instance'));
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--enrichment);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('External presence'),
        'panelIcon' => 'fas fa-cloud-arrow-down',
        'panelColor' => 'var(--enrichment)',
        'panelSub' => $subtitle,
    )) ?>

    <?php if (!$anything): ?>
        <div class="vp-empty">
            <i class="fas fa-cloud"></i>
            <span>
                <?= __('No feed, sync server or SightingDB carries this.') ?>
            </span>
        </div>
    <?php else: ?>
        <div class="p-3 d-flex flex-column gap-3">

            <?php if (!empty($feeds)): ?>
                <div>
                    <div class="vp-subhead"><?= __('Feeds') ?></div>
                    <?php foreach ($feeds as $feed): ?>
                        <div class="vp-external-row">
                            <i class="fas fa-rss"></i>
                            <div class="vp-min-w-0">
                                <div class="fw-semibold text-truncate"
                                     title="<?= h($feed['name']) ?>">
                                    <?= h($feed['name']) ?>
                                </div>
                                <div class="vp-fact-line-sub">
                                    <?= h($feed['provider']) ?>
                                </div>
                            </div>
                            <span class="vp-external-count"
                                  title="<?= h(__('Events in this feed')) ?>">
                                <?= h($feed['events']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="vp-external-row">
                <i class="fas fa-server"></i>
                <div class="vp-min-w-0">
                    <div class="fw-semibold">
                        <?= h(sprintf(
                            __('%s sync servers'),
                            $external['servers']
                        )) ?>
                    </div>
                    <div class="vp-fact-line-sub">
                        <?= __('Hold this value in their cache') ?>
                    </div>
                </div>
            </div>

            <div class="vp-external-row">
                <i class="fas fa-database"></i>
                <div class="vp-min-w-0">
                    <div class="fw-semibold">
                        <?= h(sprintf(
                            __('%s SightingDB hits'),
                            $external['sightingdb']
                        )) ?>
                    </div>
                    <div class="vp-fact-line-sub">
                        <?= __('Counted outside MISP, by anyone querying') ?>
                    </div>
                </div>
            </div>

        </div>
    <?php endif; ?>

</div>
