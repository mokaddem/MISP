<?php
/**
 * The Enrichment tab: a rail of every module valid for this value's
 * type, and one module's results beside it.
 *
 * The module is the navigation. This tab has six states and most
 * visits find several at once — never run, staged, running, answered,
 * silent, timed out — and a rail row is the one object all six fit
 * into. "Nothing queried yet" is then a full column of dashed rows
 * rather than an empty page, and a module that timed out is one row
 * wearing a clock while the others are untouched: a timeout is
 * structurally incapable of reading as total failure.
 *
 * One endpoint for the whole tab. The rail's state chips and the
 * pane's contents are the same data read two ways, and every module's
 * pane is rendered up front so switching between them is a class
 * change rather than a request. Nothing here queries anything: not on
 * load, not on tab switch, not on picking a module.
 *
 * Lazily loaded from ValuesController::viewEnrichment.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$profile = $valueProfile;
$enrichment = $profile['enrichment'];
$modules = $enrichment['modules'];
$merged = $enrichment['merged'];

/*
 * The tab opens on the first module that has something to read, and
 * on the whole-rail brief when none has. Landing on a module nobody
 * has run would put a cost estimate where the reader expects results.
 */
$selected = '__all';
foreach ($modules as $module) {
    if ($module['elements'] > 0) {
        $selected = $module['name'];
        break;
    }
}

$noRun = __(
    'Disabled in this pass — running a module spends quota and'
    . ' queries a third party.'
);
$noWrite = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

/*
 * The sub-line names the type because module validity is scoped to
 * one: this value carries three, and a reader has to know which of
 * them the rail was matched against.
 */
$bits = array();
if ($enrichment['type'] === null) {
    $bits[] = h(__(
        'No module is valid — MISP cannot tell what this value is'
    ));
} else {
    $bits[] = sprintf(
        __n(
            '%1$s module valid for %2$s',
            '%1$s modules valid for %2$s',
            count($modules)
        ),
        h(count($modules)),
        '<span class="font-monospace">' . h($enrichment['type'])
            . '</span>'
    );
    if (!empty($enrichment['type_inferred'])) {
        $bits[] = '<span title="' . h(__(
            'This value has no occurrence, so there is no attribute row'
            . ' to read a type from. MISP classified the string itself.'
        )) . '">' . h(__('type inferred from the value')) . '</span>';
    }
}
$bits[] = $enrichment['last_run'] === null
    ? h(__('never run'))
    : h(sprintf(__('last run %s'), $enrichment['last_run']));
if ($enrichment['pending'] > 0) {
    $bits[] = h(sprintf(
        __n(
            '%d element awaiting review',
            '%d elements awaiting review',
            $enrichment['pending']
        ),
        $enrichment['pending']
    ));
}

ob_start();
?>
    <?php if ($enrichment['pending'] > 0): ?>
        <button type="button"
                class="btn btn-sm btn-outline-secondary disabled
                       d-inline-flex align-items-center gap-1"
                disabled title="<?= h($noWrite) ?>">
            <i class="fas fa-list-check"></i>
            <?= h(sprintf(
                __('Review all %d'),
                $enrichment['pending']
            )) ?>
        </button>
    <?php endif; ?>
<?php
$panelExtra = trim(ob_get_clean());
?>
<div class="card shadow-sm mb-3 vp-panel vp-e"
     style="--vp-panel-color: var(--vp-e-accent);"
     data-vp-enrich>

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Enrichment'),
        'panelIcon' => 'fas fa-wand-magic-sparkles',
        'panelColor' => 'var(--vp-e-accent)',
        'panelSub' => implode(' &middot; ', $bits),
        'panelExtra' => $panelExtra === '' ? null : $panelExtra,
    )) ?>

    <?php if (empty($modules)): ?>

        <?php
        /*
         * No rail at all, and it is a state rather than a failure:
         * modules are matched on a type, this value has none that MISP
         * can name, and so there is nothing to offer to run. Drawn as
         * one empty block instead of an empty rail beside an empty
         * pane, which would be the same nothing said twice.
         */
        ?>
        <div class="vp-empty p-4">
            <div class="fw-semibold mb-1">
                <?= h(__('No module is valid for this value.')) ?>
            </div>
            <div class="small text-muted">
                <?= h(__(
                    'Enrichment modules declare the attribute types'
                    . ' they accept, and MISP could not classify this'
                    . ' value as any of them. Nothing has been sent'
                    . ' anywhere.'
                )) ?>
            </div>
        </div>

    <?php else: ?>

        <div class="vp-e-split">

            <?= $this->element('Values/View/value_enrichment_rail', array(
                'enrichment' => $enrichment,
                'selected' => $selected,
                'noRun' => $noRun,
            )) ?>

            <div class="vp-e-pane">

                <?php
                /*
                 * Every pane, one shown. A request per module would be
                 * a request this tab must not make — and the reader
                 * comparing two modules should not pay a round trip
                 * for each glance.
                 */
                ?>
                <?= $this->element('Values/View/value_enrichment_pane', array(
                    'enrichment' => $enrichment,
                    'moduleName' => null,
                    'selected' => $selected === '__all',
                    'noRun' => $noRun,
                    'noWrite' => $noWrite,
                )) ?>

                <?php foreach ($modules as $module): ?>
                    <?= $this->element(
                        'Values/View/value_enrichment_pane',
                        array(
                            'enrichment' => $enrichment,
                            'moduleName' => $module['name'],
                            'selected' => $selected === $module['name'],
                            'noRun' => $noRun,
                            'noWrite' => $noWrite,
                        )
                    ) ?>
                <?php endforeach; ?>

            </div>

        </div>

    <?php endif; ?>

</div>
