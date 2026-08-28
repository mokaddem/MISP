<?php
/**
 * Who has seen this value, and when.
 *
 * MISP has three sighting types and they mean opposite things: a
 * sighting supports the value, a false positive contradicts it, an
 * expiration retires it. They are shown apart, never summed into one
 * "activity" number.
 *
 * Lazily loaded into `.ajax-card` from ValuesController::viewSightings.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$sightings = $valueProfile['sightings'];
$positive = $sightings['total'] - $sightings['fp'] - $sightings['expiration'];
$spark = $sightings['spark'];
$peak = empty($spark) ? 0 : max($spark);
$reporters = $sightings['reporters'];
$topReporter = empty($reporters) ? 0 : $reporters[0]['count'];

$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

$subtitle = $sightings['total'] === 0
    ? h(__('Never sighted'))
    : h(sprintf(__('Last sighting %s'), $sightings['last']));
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--sighting);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Sightings'),
        'panelIcon' => 'misp-icon misp-icon-sighting misp-simple',
        'panelColor' => 'var(--sighting)',
        'panelSub' => $subtitle,
    )) ?>

    <div class="p-3 d-flex flex-column gap-3">

        <div class="vp-stat-row">
            <div class="vp-stat vp-stat-primary">
                <div class="vp-stat-value"><?= h($positive) ?></div>
                <div class="vp-stat-label"><?= __('Sightings') ?></div>
            </div>
            <div class="vp-stat<?= $sightings['fp'] > 0
                ? ' vp-stat-fp'
                : '' ?>">
                <div class="vp-stat-value"><?= h($sightings['fp']) ?></div>
                <div class="vp-stat-label"><?= __('False positive') ?></div>
            </div>
            <div class="vp-stat<?= $sightings['expiration'] > 0
                ? ' vp-stat-exp'
                : '' ?>">
                <div class="vp-stat-value">
                    <?= h($sightings['expiration']) ?>
                </div>
                <div class="vp-stat-label"><?= __('Expiration') ?></div>
            </div>
        </div>

        <?php if (!empty($spark)): ?>
            <div>
                <div class="vp-spark" role="img"
                     aria-label="<?= h(__('Sightings over the last 90 days')) ?>">
                    <?php foreach ($spark as $bucket): ?>
                        <span class="vp-spark-bar<?= $bucket === 0
                            ? ' vp-spark-bar-empty'
                            : '' ?>"
                              style="--vp-spark-h: <?=
                                  $peak > 0
                                      ? round(100 * $bucket / $peak)
                                      : 0 ?>%;"
                              title="<?= h(sprintf(
                                  __('%s sightings'),
                                  $bucket
                              )) ?>"></span>
                    <?php endforeach; ?>
                </div>
                <div class="vp-spark-axis">
                    <span><?= __('90 days ago') ?></span>
                    <span><?= __('today') ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($reporters)): ?>
            <div class="vp-reporters">
                <div class="vp-subhead"><?= __('Reported by') ?></div>
                <?php foreach ($reporters as $reporter): ?>
                    <div class="vp-reporter">
                        <span class="vp-reporter-name">
                            <?= h($reporter['org']) ?>
                        </span>
                        <span class="vp-reporter-track">
                            <span class="vp-reporter-fill" style="width: <?=
                                $topReporter > 0
                                    ? round(100 * $reporter['count']
                                        / $topReporter)
                                    : 0 ?>%;"></span>
                        </span>
                        <span class="vp-reporter-count">
                            <?= h($reporter['count']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="vp-empty vp-empty-inline">
                <span class="misp-icon misp-icon-sighting misp-simple"></span>
                <span><?= __('Nobody has reported seeing this.') ?></span>
            </div>
        <?php endif; ?>

        <button type="button"
                class="btn btn-sm btn-outline-secondary w-100
                       d-flex align-items-center justify-content-center gap-1"
                disabled
                title="<?= h($noWrites) ?>">
            <span class="misp-icon misp-icon-sighting misp-simple"></span>
            <?= __('I saw this') ?>
        </button>

    </div>

</div>
