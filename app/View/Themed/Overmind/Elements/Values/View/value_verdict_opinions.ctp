<?php
/**
 * The opinion distribution, as a rail card.
 *
 * A histogram rather than a mean, and the note saying why: the mean of
 * a bimodal distribution is a value nobody holds. Beside the two cases,
 * because the two clusters in this chart *are* the two cases.
 *
 * CSS bars rather than a chart library. Ten bars with no axis beyond
 * 0 / 50 / 100 is a shape, not a plot — the reader needs to see two
 * clusters and a hole, and nothing here rewards a tooltip.
 *
 * @var array $valueProfile
 */
$verdict = $valueProfile['verdict'];
$opinions = $verdict['opinions'] ?? null;

if (!empty($opinions['buckets'])) {
    $tallest = 1;
    foreach ($opinions['buckets'] as $bucket) {
        $tallest = max($tallest, (int)$bucket['count']);
    }
}
?>
<?php if (!empty($opinions['buckets'])): ?>
    <div class="card shadow-sm mb-3 vp-panel vp-aside">

        <div class="vp-aside-head">
            <i class="fas fa-chart-column"
               style="color: var(--analystData);"></i>
            <span class="vp-aside-title">
                <?= __('Opinion distribution') ?>
            </span>
            <span class="vp-aside-meta">
                <?= h(sprintf(
                    __('%1$s opinions · mean %2$s'),
                    $opinions['n'],
                    $opinions['mean']
                )) ?>
            </span>
        </div>

        <div class="p-3">

            <div class="vp-hist">
                <?php foreach ($opinions['buckets'] as $b => $bucket):
                    $count = (int)$bucket['count'];
                    /*
                     * An opinion below the midpoint argues the value is
                     * benign and one above it argues malicious, so the
                     * bars carry the same two colours the cases do.
                     * That is what makes the split legible as a split
                     * rather than as a lumpy distribution.
                     */
                    $side = $b < 5 ? 'ben' : 'mal';
                    ?>
                    <span class="vp-hist-bar vp-hist-bar-<?= $side ?><?=
                        $count === 0 ? ' vp-hist-bar-empty' : '' ?>"
                          style="height: <?= $count === 0
                              ? 2
                              : round($count / $tallest * 100, 2) ?>%;"
                          title="<?= h(sprintf(
                              __('%1$s opinions in %2$s'),
                              $count,
                              $bucket['label']
                          )) ?>"></span>
                <?php endforeach; ?>
            </div>

            <div class="vp-hist-axis">
                <span>0</span><span>50</span><span>100</span>
            </div>

            <p class="vp-aside-note"><?= h($opinions['note']) ?></p>

        </div>

    </div>
<?php endif; ?>
