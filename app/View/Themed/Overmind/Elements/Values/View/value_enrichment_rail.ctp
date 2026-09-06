<?php
/**
 * The rail: one row per module eligible for this value.
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
 * answered, answered with nothing, refused, errored. All six are set
 * by the run, on the client, against markup this element sent.
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

    <div class="vp-e-railscroll">

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
         * The tray states the service rather than a selection's price.
         * Phase 12 put two cost chips and a *Run n selected* button
         * here; there is no cost metadata to fill the chips, and a
         * multi-module run is a request per module — the queued path
         * that would make one press safe is `POST /attributes/enrich`,
         * which writes. So a run is one module, from its own row.
         */
        ?>
        <div class="vp-e-svc">
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
