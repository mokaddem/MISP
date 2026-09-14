<?php
/**
 * The Enrichment tab: every module this value could be sent to, and
 * one module's answer beside it.
 *
 * **The tab has a memory since phase 11.** `value_enrichment_runs`
 * records what a module last said about a value, per organisation, so
 * a row can read *Asked 2 hours ago* and its pane can show that answer
 * without asking anybody anything. The reuse window a profile always
 * declared finally governs something (`13-auto-run.md` §4–5, D25).
 *
 * It says when, never who (D27).
 *
 * The delta against a previous run and the dismissal are still gone —
 * those were phase 12's and `28-enrichment.md` §5 is the list of what
 * came out.
 *
 * What survives is the reason `E2` was chosen: the module is the
 * navigation, and every state the tab can be in is the same object — a
 * rail row. Not asked, asked before, being asked, asking, answered,
 * answered with nothing, refused and errored are eight rows and one
 * pane.
 *
 * **Selecting a row still runs nothing**, and every module's pane is
 * still rendered up front, which is what makes picking one to read
 * incapable of querying anything.
 *
 * **What can now happen on arrival is the profile's and the
 * administrator's, together.** A module a profile marked `auto` fires
 * when this panel loads, but only where
 * `Plugin.ValueProfile_enrichment_auto_run` allows it — off by
 * default, so this panel behaves exactly as it did until somebody
 * turns it on. The plan rides in on `data-vp-e-auto` and is empty
 * otherwise.
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
     <?php
     /*
      * **The plan travels with the panel that acts on it** (phase 11
      * §9), so the browser can act without going back for a list it
      * was about to be handed anyway.
      *
      * **`fresh` is in it as well as `fire`**, because §9 defines
      * `fresh` as *render it, ask nothing* and a pane cannot render
      * what was never fetched. Both go out as `mode=auto` on the same
      * endpoint, which is the whole point of that mode: it serves a
      * kept answer when there is one and asks the module when there is
      * not, so the page never has to decide which of those it is
      * about to get. A `fresh` request sends nothing outside the
      * instance and comes back in about a tenth of a second.
      *
      * Without this the tab that the Overview has already filled would
      * open on *Nothing has been queried* while the store held every
      * answer — which is exactly the state the intended flow produces.
      *
      * `in_flight` is deliberately not here: somebody else's request
      * is about to write that row, and asking for it now would start a
      * second query rather than wait for the first.
      *
      * Empty on any instance that has not turned auto-run on, which is
      * every instance until an administrator does — and empty is how
      * the fan-out stays off, rather than by a second flag.
      *
      * The dispositions are advisory: `enrichmentRun()` decides again
      * when a request lands, because a row can turn fresh in between.
      */
     $autoFire = array();
     foreach ($enrichment['profile']['auto'] as $one) {
         if ($one['disposition'] !== 'fire'
             && $one['disposition'] !== 'fresh'
         ) {
             continue;
         }
         $autoFire[] = array(
             'module' => $one['module'],
             'type' => $one['type'],
         );
     }
     ?>
     data-vp-e-auto="<?= h(json_encode($autoFire)) ?>"
     data-vp-e-auto-max="5"
     data-vp-e-url="<?= h($baseurl . '/values/viewEnrichmentRun/'
        . $valueB64) ?>">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Enrichment'),
        'panelIcon' => 'fas fa-wand-magic-sparkles',
        'panelColor' => 'var(--vp-e-accent)',
        'panelSub' => implode(' &middot; ', $bits),
    )) ?>

    <?php
    /*
     * Above the branch on purpose. A profile naming a module this
     * instance has turned off is precisely the case with no rail to
     * hang the condition on, and a reader told "no enabled module
     * accepts this value's types" while their own profile names one
     * has been quietly lied to.
     */
    ?>
    <?= $this->element('Values/View/value_enrichment_profile', array(
        'enrichment' => $enrichment,
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
                                    'Nothing has been opened here yet.'
                                )) ?>
                            </div>
                            <div class="vp-e-cold-prose mb-0">
                                <?php
                                /*
                                 * Both sentences this carried stopped
                                 * being true in phase 11: answers are
                                 * stored now, and an answer from last
                                 * time is exactly what a module row
                                 * may already be holding.
                                 */
                                ?>
                                <?= h(__(
                                    'This merges the answers open on'
                                    . ' this page. Open a module that'
                                    . ' was asked before and its answer'
                                    . ' joins them, without asking'
                                    . ' anybody anything.'
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
