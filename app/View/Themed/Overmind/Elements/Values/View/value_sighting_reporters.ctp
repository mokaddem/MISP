<?php
/**
 * Who reports this value, as a rail card.
 *
 * The same bars the Overview's sightings card draws, on a page where
 * the chart beside them has just stacked those organisations by day —
 * the rail is where the totals live, so the reader can rank the
 * organisations without counting stack segments.
 *
 * The count is every report the organisation filed, of any type. A
 * contradiction is participation too, and hiding a false positive here
 * would make the most sceptical organisation look like the quietest.
 *
 * Lazily loaded from ValuesController::viewSightingReporters.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$sightings = $valueProfile['sightings'];
$reporters = $sightings['reporters'];
$top = empty($reporters) ? 0 : $reporters[0]['count'];
?>
<div class="card shadow-sm mb-3 vp-panel vp-aside"
     style="--vp-panel-color: var(--sighting);">

    <div class="vp-aside-head">
        <span class="misp-icon misp-icon-organisation misp-simple"
              style="color: var(--sighting);"></span>
        <span class="vp-aside-title"><?= __('Reporters') ?></span>
        <span class="vp-aside-meta">
            <?= h(sprintf(
                __('%1$s · %2$s'),
                __n(
                    '%s organisation',
                    '%s organisations',
                    count($reporters),
                    count($reporters)
                ),
                __n(
                    '%s report',
                    '%s reports',
                    $sightings['total'],
                    $sightings['total']
                )
            )) ?>
        </span>
    </div>

    <div class="p-3">

        <?php if (empty($reporters)): ?>
            <div class="vp-empty vp-empty-inline">
                <span class="misp-icon misp-icon-sighting misp-simple"></span>
                <span><?= __('Nobody has reported seeing this.') ?></span>
            </div>
        <?php else: ?>
            <div class="vp-reporters">
                <?php foreach ($reporters as $reporter): ?>
                    <div class="vp-reporter">
                        <span class="vp-reporter-name"
                              title="<?= h($reporter['org']) ?>">
                            <?= h($reporter['org']) ?>
                        </span>
                        <span class="vp-reporter-track">
                            <span class="vp-reporter-fill" style="width: <?=
                                $top > 0
                                    ? round(100 * $reporter['count'] / $top)
                                    : 0 ?>%;"></span>
                        </span>
                        <span class="vp-reporter-count">
                            <?= h($reporter['count']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="vp-aside-note">
                <?= h(__(
                    'Every report the organisation filed, whatever it said.'
                    . ' The chart splits them by type; this ranks them by'
                    . ' who spoke.'
                )) ?>
            </p>
        <?php endif; ?>

    </div>

</div>
