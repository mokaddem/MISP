<?php
/**
 * The Enrichment tab: every module this value could be sent to, and
 * one module's answer beside it.
 *
 * **Live since phase 28, and the tab has no memory.** Nothing records
 * that a module ran, so there is no staleness, no delta against a
 * previous run and no dismissal — those were phase 12's, they needed a
 * store MISP does not have, and `28-enrichment.md` §5 is the list of
 * what came out rather than a diff to reconstruct.
 *
 * What survives is the reason `E2` was chosen: the module is the
 * navigation, and every state the tab can be in is the same object — a
 * rail row. Not asked, asking, answered, answered with nothing, refused
 * and errored are six rows and one pane.
 *
 * **Nothing runs on arrival.** Not on load, not on tab switch, not on
 * selecting a row. Running a module spends the instance's quota and
 * tells whoever operates it that somebody is looking at this value, so
 * it takes a press. Every module's pane is rendered up front — that is
 * what makes picking one to read incapable of querying anything.
 *
 * Lazily loaded from ValuesController::viewEnrichment.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$profile = $valueProfile;
$enrichment = $profile['enrichment'];
$service = $enrichment['service'];
$modules = $enrichment['modules'];
$types = $enrichment['types'];
$canRun = !empty($enrichment['can_run']);

/*
 * The CSRF token the run posts back. A fresh one per fragment: tokens
 * are use-once, and the reader may run several modules.
 */
$token = isset($this->request->params['_Token']['key'])
    ? $this->request->params['_Token']['key']
    : '';

$noRun = __(
    'Running a module requires the add permission — the same one MISP'
    . ' asks for on every other enrichment surface.'
);

/*
 * The sub-line names the types because eligibility is matched on one:
 * a value is several, and a reader has to know which of them put a
 * module on the rail.
 */
$bits = array();
if (empty($types)) {
    $bits[] = h(__('No occurrence you can see'));
} else {
    $names = array();
    foreach ($types as $row) {
        $names[] = '<span class="font-monospace">'
            . h($row['type']) . '</span>';
    }
    $bits[] = sprintf(
        __n('%1$s type: %2$s', '%1$s types: %2$s', count($types)),
        h(count($types)),
        implode(', ', $names)
    );
}
if ($service['reachable']) {
    $bits[] = h(sprintf(
        __n(
            '%d module eligible',
            '%d modules eligible',
            count($modules)
        ),
        count($modules)
    ));
    $bits[] = h(sprintf(
        __('%d enabled on this instance'),
        $enrichment['enabled']
    ));
}
?>
<div class="card shadow-sm mb-3 vp-panel vp-e"
     style="--vp-panel-color: var(--vp-e-accent);"
     data-vp-enrich
     data-vp-e-value="<?= h($valueB64) ?>"
     data-vp-e-token="<?= h($token) ?>"
     data-vp-e-canrun="<?= $canRun ? '1' : '0' ?>"
     data-vp-e-n-one="<?= h(__('%s element')) ?>"
     data-vp-e-n-many="<?= h(__('%s elements')) ?>"
     data-vp-e-n-capped="<?= h(__('%1$s of %2$s')) ?>"
     data-vp-e-of="<?= h(__('%1$s of %2$s')) ?>"
     data-vp-e-url="<?= h($baseurl . '/values/viewEnrichmentRun/'
        . $valueB64) ?>">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Enrichment'),
        'panelIcon' => 'fas fa-wand-magic-sparkles',
        'panelColor' => 'var(--vp-e-accent)',
        'panelSub' => implode(' &middot; ', $bits),
    )) ?>

    <?php if (!$service['reachable']): ?>

        <?php
        /*
         * The service, not the modules. A reader who is told "no
         * modules" when the truth is "nobody answered the door" will
         * conclude this instance enriches nothing, which is a
         * different and wrong fact about their own deployment.
         */
        ?>
        <div class="vp-empty p-4">
            <div class="fw-semibold mb-1">
                <?= h(__('The enrichment service did not answer.')) ?>
            </div>
            <div class="small text-muted">
                <?= h(__(
                    'This says nothing about which modules exist or'
                    . ' whether they would have anything to report —'
                    . ' the list itself is what could not be read.'
                    . ' Nothing has been sent anywhere.'
                )) ?>
            </div>
            <?php if (!empty($service['error'])): ?>
                <div class="small text-muted mt-2 font-monospace">
                    <?= h($service['error']) ?>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif (empty($types)): ?>

        <?php
        /*
         * No type, and the reason is this reader's rather than the
         * value's: modules are matched on the type an occurrence
         * carries, and a reader who may see none has nothing to match
         * against. Deliberately not phrased as "MISP cannot classify
         * this value" — that was the fixture's answer and it is a
         * claim about the string, which is not what is missing.
         */
        ?>
        <div class="vp-empty p-4">
            <div class="fw-semibold mb-1">
                <?= h(__('Nothing to enrich here.')) ?>
            </div>
            <div class="small text-muted">
                <?= h(__(
                    'Enrichment modules accept attribute types, and'
                    . ' you hold no occurrence of this value to take a'
                    . ' type from. Nothing has been sent anywhere.'
                )) ?>
            </div>
        </div>

    <?php elseif (empty($modules)): ?>

        <div class="vp-empty p-4">
            <div class="fw-semibold mb-1">
                <?= h(__(
                    'No enabled module accepts any of this value\'s'
                    . ' types.'
                )) ?>
            </div>
            <div class="small text-muted">
                <?= h(sprintf(
                    __n(
                        'One module is enabled on this instance and it'
                        . ' does not take these types.',
                        '%d modules are enabled on this instance and'
                        . ' none of them takes these types.',
                        $enrichment['enabled']
                    ),
                    $enrichment['enabled']
                )) ?>
            </div>
        </div>

    <?php else: ?>

        <div class="vp-e-split">

            <?= $this->element('Values/View/value_enrichment_rail', array(
                'enrichment' => $enrichment,
                'canRun' => $canRun,
                'noRun' => $noRun,
            )) ?>

            <div class="vp-e-pane">

                <?php
                /*
                 * The resting pane, shown until a row is picked. It is
                 * the tab's opening claim and the reason nothing has
                 * been queried is stated here rather than implied by
                 * an empty column.
                 */
                ?>
                <div data-vp-e-pane="__none">
                    <div class="vp-e-cold vp-e-cold-solo">
                        <div class="vp-e-cold-title">
                            <?= h(__('Nothing has been queried.')) ?>
                        </div>
                        <div class="vp-e-cold-prose">
                            <?= h(__(
                                'This page has sent nothing to any'
                                . ' module. Picking one below shows'
                                . ' what it would be asked; running it'
                                . ' sends this value to whoever'
                                . ' operates it, which spends the'
                                . ' instance\'s quota and tells them'
                                . ' somebody is looking.'
                            )) ?>
                        </div>
                        <div class="vp-e-cold-prose mt-2">
                            <?= h(__(
                                'Nothing a module returns is stored.'
                                . ' The answer lives on this page'
                                . ' until you leave it.'
                            )) ?>
                        </div>
                    </div>
                </div>

                <?php
                /*
                 * The merged pane — `E2`'s one addition to the
                 * direction it came from. The rail costs the reader
                 * cross-module reading and this buys it back.
                 *
                 * Filled on the client by cloning what the answered
                 * panes already hold, so it costs no request and
                 * cannot disagree with them. It merges **this
                 * visit's** runs, which is the only span a page with
                 * no memory can merge over — and it says so, rather
                 * than implying it has read everything.
                 */
                ?>
                <div class="d-none" data-vp-e-pane="__all">
                    <?php
                    /*
                     * The merged pane wears a result's head, because
                     * that is what it is: the same rows under one
                     * claim. Anything else would have the reader
                     * learning a second layout for the same content.
                     */
                    ?>
                    <div class="vp-e-res-head">
                        <i class="fas fa-layer-group vp-e-mark
                                  vp-e-mark-quiet" aria-hidden="true"></i>
                        <div class="vp-e-res-headtext">
                            <div class="vp-e-cold-title" data-vp-e-allhead>
                                <?= h(__(
                                    'Nothing has been run this visit.'
                                )) ?>
                            </div>
                            <div class="vp-e-cold-prose mb-0">
                                <?= h(__(
                                    'This merges the answers from'
                                    . ' modules run on this page, in'
                                    . ' this visit. Nothing is stored,'
                                    . ' so there is nothing here from'
                                    . ' last time.'
                                )) ?>
                            </div>
                        </div>
                    </div>
                    <div data-vp-e-allbody></div>
                </div>

                <?php
                /*
                 * One pane per module, rendered up front and empty.
                 * The run fills it in place; picking between them is a
                 * class change and never a request, which is the
                 * promise the tab is built to keep.
                 */
                ?>
                <?php foreach ($modules as $module): ?>
                    <div class="d-none"
                         data-vp-e-pane="<?= h($module['name']) ?>">
                        <div data-vp-e-slot>
                            <?= $this->element(
                                'Values/View/value_enrichment_brief',
                                array(
                                    'module' => $module,
                                    'service' => $service,
                                    'canRun' => $canRun,
                                    'noRun' => $noRun,
                                )
                            ) ?>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>

        </div>

    <?php endif; ?>

</div>
