<?php
/**
 * The relevance axis's magnitude: the shelf, as the value page draws
 * it.
 *
 * A track, a fill and a mark where aging begins. Coloured
 * `--bs-correlation`, which is what the shipped relevance card uses,
 * and deliberately not `--vp-dir-with` / `--vp-dir-against`: that pair
 * means *supports* and *disputes*, and a clock says neither.
 *
 * The whole thing is absent when there is no clock, which is a state
 * and not a gap — a value nothing is recorded for has no shelf life to
 * run down, and drawing an empty track would assert one.
 *
 * @var array|null $runway `ValueRelevanceTool`'s relevance array
 */
if (empty($runway) || !isset($runway['runway'])
    || $runway['runway'] === null
) {
    if (!empty($runway['note'])) {
        echo '<span class="ax-g">' . h($runway['note']) . '</span>';
    }
    return;
}
$ttlDays = isset($runway['ttl']['days']) ? (int)$runway['ttl']['days'] : null;
$ttlFrom = isset($runway['ttl']['type']) ? $runway['ttl']['type'] : null;
$left = (int)round($runway['runway'] * 100);
$aging = (int)round(
    (isset($runway['aging_fraction']) ? $runway['aging_fraction'] : 0.33) * 100
);
$clockAt = isset($runway['clock']['at']) && $runway['clock']['at']
    ? date('Y-m-d', $runway['clock']['at'])
    : null;
$precision = isset($runway['precision']) ? $runway['precision'] : array();
$lagDays = isset($precision['max_lag_days'])
    && $precision['max_lag_days'] !== null
    ? (int)$precision['max_lag_days']
    : null;
$lagLimit = isset($precision['lag_limit'])
    ? (int)$precision['lag_limit']
    : null;
?>
<div class="ax-runway">
    <div class="ax-track" title="<?= h(sprintf(
        __('%1$s days elapsed of a %2$s day TTL'),
        $runway['elapsed_days'],
        $ttlDays
    )) ?>">
        <span class="ax-fill" style="width:<?= max(0, min(100, $left)) ?>%"></span>
        <span class="ax-agemark" style="left:<?= max(0, min(100, $aging)) ?>%"
              title="<?= h(__('where aging begins')) ?>"></span>
    </div>
    <span class="ax-runway-t">
        <?php if ($runway['runway_days'] < 0): ?>
            <b><?= h(-$runway['runway_days']) ?></b>
            <?= h(__('days over')) ?>
        <?php else: ?>
            <b><?= h($runway['runway_days']) ?></b>
            <?= h(sprintf(__('of %s days left'), $ttlDays)) ?>
        <?php endif; ?>
        <?php if ($ttlFrom !== null): ?>
            &middot; <?= h(__('TTL from')) ?>
            <span class="wb-id"><?= h($ttlFrom) ?></span>
        <?php endif; ?>
        <?php if ($clockAt !== null): ?>
            &middot; <?= h(sprintf(__('clock last reset %s'), $clockAt)) ?>
        <?php endif; ?>
    </span>
    <?php if (!empty($runway['uncertain'])): ?>
        <?php
        /*
         * The measurement, not the verdict. This line said *the
         * timeline is uncertain, so the elapsed time is a lower bound*
         * and kept the number that tripped it in a `title` — beside an
         * editor whose next field is the threshold that number is
         * compared against. Judging `30` with the reading hidden is the
         * one thing this pane exists to prevent.
         */
        ?>
        <span class="ax-runway-t wb-sub"
              title="<?= h(__('Counted from when the value was added to'
                  . ' MISP, not from when it was seen, so the elapsed'
                  . ' time above is a minimum.')) ?>">
            <?= h(sprintf(
                __('timeline uncertain — %s'),
                $runway['uncertain_note']
            )) ?>
        </span>
    <?php elseif ($lagDays !== null && $lagDays > 0): ?>
        <?php
        /*
         * A lag that did not trip is still the reading the threshold is
         * set against, and a pane that only ever shows the number when
         * it has already failed cannot be used to choose the number.
         */
        ?>
        <span class="ax-runway-t wb-sub">
            <?= h(sprintf(
                __n(
                    'added %1$s day after its event\'s date, inside the'
                        . ' %2$s-day limit',
                    'added %1$s days after its event\'s date, inside the'
                        . ' %2$s-day limit',
                    $lagDays
                ),
                $lagDays,
                $lagLimit
            )) ?>
        </span>
    <?php endif; ?>
</div>
