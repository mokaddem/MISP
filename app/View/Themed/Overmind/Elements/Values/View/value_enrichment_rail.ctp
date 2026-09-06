<?php
/**
 * The rail: one row per module eligible for this value, plus the
 * merged row above them and the tray below.
 *
 * **A module is one row however many of the value's types put it
 * there.** `8.8.8.8` is four types and `circl_passivedns` accepts
 * three of them; that is one module, and the row names the type a run
 * would use rather than repeating itself three times.
 *
 * **No grouping, because grouping needs memory.** Phase 12 sorted the
 * rail into *ran / not in the last run / never run*, which are three
 * claims about history, and history is what a stateless tab does not
 * have. The rows sort by name — a stable order a reader can learn,
 * rather than one that reshuffles as they work.
 *
 * The state a row *does* carry is this visit's: not asked, asking,
 * answered, answered with nothing, timed out, errored, refused. Set by
 * the run, on the client, against markup this element sent.
 *
 * A plain partial, not an endpoint.
 *
 * @var array $enrichment
 * @var bool $canRun
 * @var string $noRun
 */
$modules = $enrichment['modules'];
$service = $enrichment['service'];
?>
<div class="vp-e-rail">

    <?php
    /*
     * Select all, and the count beside it. Phase 12 put this here to
     * price a batch before committing to it; the price it quoted was
     * quota and third-party exposure, and neither has a source. What
     * a selection can still be honestly told is **how many modules it
     * is**, which is also how many separate queries the press makes —
     * a run is one module, so `Run 3 selected` is three requests, one
     * after another.
     */
    ?>
    <div class="vp-e-railhead">
        <label class="vp-e-selall">
            <input type="checkbox" class="form-check-input"
                   data-vp-e-select-all
                   <?= $canRun ? '' : 'disabled' ?>>
            <span><?= h(__('Select all')) ?></span>
        </label>
        <span class="vp-e-railhead-n">
            <span data-vp-e-picked>0</span>
            <?= h(sprintf(__('of %d selected'), count($modules))) ?>
        </span>
    </div>

    <div class="vp-e-railscroll">

        <?php
        /*
         * `All results` — the one addition `E2` makes to the direction
         * it came from. The rail costs the reader cross-module
         * reading, and this row buys it back by putting every answer
         * in one pane. It is filled on the client from the runs made
         * this visit, because that is the only span a page with no
         * memory can merge over.
         */
        ?>
        <div class="vp-e-railrow vp-e-railrow-all"
             data-vp-e-row="__all">
            <button type="button"
                    class="vp-e-railbody"
                    data-vp-e-pick="__all"
                    aria-pressed="false">
                <span class="vp-e-railrow-name">
                    <?= h(__('All results')) ?>
                </span>
                <span class="vp-e-railrow-sub" data-vp-e-allsub>
                    <?= h(__('Nothing run yet')) ?>
                </span>
            </button>
        </div>

        <div class="vp-e-railgroup">
            <?= h(sprintf(
                __n('%d module', '%d modules', count($modules)),
                count($modules)
            )) ?>
        </div>

        <?php foreach ($modules as $module): ?>
            <?php
            /*
             * The sub-line is the type the run would use and the
             * kinds the module answers as. Not a cost: no cost
             * metadata exists, and a chip that implied one would be
             * inventing it.
             */
            $types = array_keys($module['types']);
            $extra = count($types) - 1;
            ?>
            <div class="vp-e-railrow" data-vp-e-row="<?= h($module['name']) ?>">

                <label class="vp-e-selbox">
                    <input type="checkbox" class="form-check-input"
                           data-vp-e-select="<?= h($module['name']) ?>"
                           data-vp-e-seltype="<?= h($module['type']) ?>"
                           <?= $canRun ? '' : 'disabled' ?>
                           aria-label="<?= h(sprintf(
                                __('Select %s'),
                                $module['name']
                           )) ?>">
                </label>

                <button type="button"
                        class="vp-e-railbody"
                        data-vp-e-pick="<?= h($module['name']) ?>"
                        aria-pressed="false">

                    <span class="vp-e-railrow-name">
                        <span class="vp-e-dot vp-e-dot-none"
                              data-vp-e-dot></span>
                        <?= h($module['name']) ?>
                    </span>

                    <span class="vp-e-railrow-sub">
                        <span class="font-monospace"><?= h(
                            $module['type']
                        ) ?></span>
                        <?php if ($extra > 0): ?>
                            <span class="text-muted"
                                  title="<?= h(sprintf(
                                    __('Also accepts %s'),
                                    implode(', ', array_slice($types, 1))
                                  )) ?>">
                                <?= h(sprintf(__('+%d'), $extra)) ?>
                            </span>
                        <?php endif; ?>
                        <span class="vp-e-kind"><?= h(implode(
                            '+',
                            $module['kinds']
                        )) ?></span>
                    </span>

                    <span class="vp-e-status vp-e-status-none"
                          data-vp-e-state><?= h(__('Not asked')) ?></span>

                </button>

            </div>
        <?php endforeach; ?>

    </div>

    <div class="vp-e-tray">

        <?php
        /*
         * The run button for the selection. Phase 12 put two cost
         * chips beside it — quota and third-party — and neither has
         * any source in module introspection, so what stands here
         * instead is the one cost that is knowable and is the same
         * thing the reader is agreeing to: how many separate queries
         * leave the building.
         */
        ?>
        <div class="vp-e-tray-cost" data-vp-e-cost-none>
            <?= h(__('Nothing selected.')) ?>
        </div>
        <div class="vp-e-tray-cost d-none" data-vp-e-cost-out>
            <i class="fas fa-arrow-up-right-from-square"></i>
            <span data-vp-e-ext-n>0</span>
            <?= h(__('queries leave this instance, one at a time')) ?>
        </div>

<?php
        /*
         * Disabled on arrival whoever is reading, because nothing is
         * selected yet. The client re-enables it when a selection
         * exists *and* the reader may run — a reader without
         * `perm_add` finds it disabled with the reason, which is this
         * page's standing treatment for a control they may not press.
         */
        ?>
        <button type="button"
                class="btn btn-sm mt-2 d-inline-flex align-items-center
                       gap-1 <?= $canRun
                    ? 'btn-outline-primary'
                    : 'disabled btn-outline-secondary' ?>"
                disabled
                <?= $canRun ? '' : 'title="' . h($noRun) . '"' ?>
                data-vp-e-run-selected>
            <i class="fas fa-play" data-vp-e-icon></i>
            <span data-vp-e-label>
                <?= h(__('Run')) ?>
                <span data-vp-e-runcount>0</span>
                <?= h(__('selected')) ?>
            </span>
        </button>

        <div class="vp-e-svc mt-2">
            <i class="fas fa-plug"></i>
            <?= h(sprintf(
                __('Service answered in %d ms'),
                $service['took']
            )) ?>
            &middot;
            <?= h(sprintf(
                __('%d s limit per module'),
                $service['timeout']
            )) ?>
        </div>
        <div class="small text-muted mt-1">
            <?= h(__('Nothing runs until you press Run.')) ?>
        </div>

    </div>

</div>
