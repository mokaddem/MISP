<?php
/**
 * A page control over rows the fragment already holds.
 *
 * No second request, no Paginator, no `paginate()` in the controller:
 * this pass pages client-side over the rows the panel was rendered with.
 * The markup is nonetheless the real Bootstrap control, so when these
 * panels go live the change is the data source and not the chrome.
 *
 * The first page is rendered here rather than left to the script, so a
 * load that never runs JavaScript still shows a truthful range instead
 * of an empty control.
 *
 * Expected:
 * @var int    $size  Rows per page
 * @var int    $shown Rows this panel was given
 * @var int    $total The value's own count, which filtering never changes
 * @var string $noun  Optional label for the range line
 */
$size = max(1, (int)($size ?? 10));
$shown = (int)($shown ?? 0);
$total = (int)($total ?? $shown);
$noun = $noun ?? null;

$pages = max(1, (int)ceil($shown / $size));
$to = min($size, $shown);
$from = $shown > 0 ? 1 : 0;
?>
<div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">

    <?php
    /*
     * `total` is the value's count and not this panel's, so a filtered
     * table cannot end up claiming the value has fewer rows than it has.
     */
    ?>
    <span class="small text-muted">
        <span data-vp-page-from><?= h($from) ?></span>–<span
            data-vp-page-to><?= h($to) ?></span>
        <?= __('of') ?>
        <span data-vp-page-of><?= h($shown) ?></span>
        <?php if ($noun !== null): ?>
            <?= h($noun) ?>
        <?php endif; ?>
        <?php if ($total !== $shown): ?>
            <?= h(sprintf(__('(%d in total)'), $total)) ?>
        <?php endif; ?>
    </span>

    <nav data-vp-pager
         data-vp-page-size="<?= h($size) ?>"
         class="<?= $pages < 2 ? 'd-none' : '' ?>"
         aria-label="<?= __('Page navigation') ?>">
        <ul class="pagination pagination-sm mb-0">
            <?php
            // Arrows are dead at the ends rather than present and inert.
            ?>
            <li class="page-item disabled">
                <button type="button" class="page-link"
                        data-vp-page="0" disabled>&laquo;</button>
            </li>
            <?php for ($n = 1; $n <= $pages; $n++): ?>
                <li class="page-item<?= $n === 1 ? ' active' : '' ?>">
                    <button type="button" class="page-link"
                            data-vp-page="<?= h($n) ?>"
                            <?= $n === 1 ? 'aria-current="page"' : '' ?>>
                        <?= h($n) ?>
                    </button>
                </li>
            <?php endfor; ?>
            <li class="page-item<?= $pages < 2 ? ' disabled' : '' ?>">
                <button type="button" class="page-link"
                        data-vp-page="2"
                        <?= $pages < 2 ? 'disabled' : '' ?>>&raquo;</button>
            </li>
        </ul>
    </nav>

</div>
