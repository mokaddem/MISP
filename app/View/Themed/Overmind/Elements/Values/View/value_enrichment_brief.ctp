<?php
/**
 * What one module would be asked, before anybody asks it.
 *
 * The pane a module shows until it is run, and the argument for the
 * press: what leaves the instance, what it is asked about, and what
 * happens to the answer. Phase 12 drew this as a cost estimate in
 * quota and credits; **no such metadata exists** — module
 * introspection carries a name, its accepted types, a description, its
 * kinds and its config keys, and nothing about rate limits or money —
 * so this states what is actually knowable instead of pricing a run
 * it would have to invent a number for.
 *
 * A plain partial, not an endpoint.
 *
 * @var array $module   One catalogue row
 * @var array $service
 * @var bool $canRun
 * @var string $noRun
 */
$kinds = implode(', ', $module['kinds']);
?>
<div class="vp-e-cold">

    <?php
    /*
     * Two children and only two: `.vp-e-cold` is a main column and an
     * aside, and a grid handed five children flows them alternately
     * into both — which put the description opposite the title and
     * the ledger opposite the note.
     */
    ?>
    <div class="vp-e-cold-main">

    <div class="vp-e-cold-title">
        <?= h(sprintf(
            __('%s has not been asked.'),
            $module['name']
        )) ?>
    </div>

    <?php if (!empty($module['description'])): ?>
        <div class="vp-e-cold-prose">
            <?= h($module['description']) ?>
        </div>
    <?php endif; ?>

    <div class="vp-e-cold-prose mt-3">
        <?= h(__(
            'Nothing is written to MISP. The answer is rendered here'
            . ' and not stored, so leaving the page loses it.'
        )) ?>
    </div>

    <div class="mt-3">
        <?= $this->element('Values/View/value_enrichment_button', array(
            'module' => $module,
            'canRun' => $canRun,
            'noRun' => $noRun,
            'label' => sprintf(__('Run %s'), $module['name']),
        )) ?>
    </div>

    </div>

    <div class="vp-e-cold-ledger">

        <div class="vp-e-ledger-row">
            <span class="vp-e-ledger-n"><?= h(__('Sends')) ?></span>
            <span>
                <?php
                /*
                 * The two formats differ in what leaves, and the
                 * difference is worth stating plainly: one sends the
                 * value, the other sends the attribute row it sits in
                 * — which carries its category, its comment and its
                 * IDS flag along with it.
                 */
                ?>
                <?= $module['format'] === 'misp_standard'
                    ? h(__(
                        'the attribute row this value sits in, as'
                        . ' MISP stores it'
                    ))
                    : h(__('this value and its type')) ?>
                <span class="text-muted">
                    (<span class="font-monospace"><?= h(
                        $module['format']
                    ) ?></span>)
                </span>
            </span>
        </div>

        <div class="vp-e-ledger-row">
            <span class="vp-e-ledger-n"><?= h(__('As')) ?></span>
            <span class="font-monospace"><?= h($module['type']) ?></span>
        </div>

        <div class="vp-e-ledger-row">
            <span class="vp-e-ledger-n"><?= h(__('Kind')) ?></span>
            <span><?= h($kinds) ?></span>
        </div>

        <?php if (!empty($module['config'])): ?>
            <div class="vp-e-ledger-row">
                <span class="vp-e-ledger-n">
                    <?= h(__('Settings')) ?>
                </span>
                <span>
                    <?= h(sprintf(
                        __n(
                            'declares %d setting',
                            'declares %d settings',
                            count($module['config'])
                        ),
                        count($module['config'])
                    )) ?>
                    <span class="text-muted">
                        <?php
                        /*
                         * Stated, never judged. Nothing distinguishes
                         * a key a module needs from one it merely
                         * accepts: `whois` fails without two of its
                         * three, `mmdb_lookup` works without any of
                         * its four. So the row reports what is
                         * declared and the run reports what happened.
                         */
                        ?>
                        &mdash; <?= h(__(
                            'whether any are required is something'
                            . ' only the run can say'
                        )) ?>
                    </span>
                </span>
            </div>
        <?php endif; ?>

        <div class="vp-e-ledger-row">
            <span class="vp-e-ledger-n"><?= h(__('Gives up after')) ?></span>
            <span><?= h(sprintf(
                __('%d s'),
                $service['timeout']
            )) ?></span>
        </div>

    </div>

</div>
