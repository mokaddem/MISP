<?php
/**
 * What this name resolved to, as a shape over time or as a set.
 *
 * **Two widgets in one file, and the split is the data's.** A dated
 * answer gets a sparkline of how many resolutions were current per
 * month; an undated one gets the addresses themselves with a count.
 * Drawing a flat line for an undated answer would assert a history
 * nobody observed, and refusing to draw it at all would throw away the
 * only resolution answer a stock instance has.
 *
 * The sparkline is built from `months`, which `prepare()` computed —
 * this template does no arithmetic beyond scaling the points into the
 * viewBox, which is drawing rather than deciding.
 *
 * @var array $data
 */
$months = $data['months'];
$max = empty($months) ? 0 : max($months);
?>
<div class="vp-rw-in vp-rw-res">
    <div class="vp-rw-metric">
        <span class="vp-rw-metric-n"><?=
            h(number_format($data['distinct'])) ?></span>
        <span class="vp-rw-metric-l"><?= h(__n(
            'resolution', 'resolutions', $data['distinct']
        )) ?></span>
    </div>

    <?php if ($data['dated'] && $max > 0 && count($months) > 1): ?>
        <?php
        $width = 180;
        $height = 44;
        $step = $width / max(1, count($months) - 1);
        $x = 0;
        $line = array();
        foreach ($months as $count) {
            $line[] = round($x, 1) . ','
                . round($height - ($count / $max) * ($height - 4), 1);
            $x += $step;
        }
        $keys = array_keys($months);
        ?>
        <svg class="vp-rw-spark" viewBox="0 0 <?= h($width) ?> <?=
             h($height) ?>" preserveAspectRatio="none"
             aria-hidden="true">
            <polyline points="<?= h(implode(' ', $line)) ?>"
                      class="vp-rw-spark-line"/>
        </svg>
        <div class="vp-rw-sub"><?= h(sprintf(
            '%s — %s',
            reset($keys),
            end($keys)
        )) ?></div>
    <?php else: ?>
        <?php
        /*
         * The set, capped at what the height allows. Which three is
         * not an accident — `prepare()` sorted by how recently each
         * was current — and the count above already says how many
         * there are, so a `+N` here would say it twice.
         */
        $shown = array_slice($data['resolutions'], 0, 3);
        ?>
        <ul class="vp-rw-set">
            <?php foreach ($shown as $row): ?>
                <li class="font-monospace"><?= h($row['to']) ?><?php
                    if ($row['type'] !== ''): ?>
                    <span class="vp-rw-code"><?= h($row['type']) ?></span>
                <?php endif; ?></li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$data['dated']): ?>
            <div class="vp-rw-note"><?= h(__('now, undated')) ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
