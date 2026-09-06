<?php
/**
 * One module's answer.
 *
 * The fragment `viewEnrichmentRun` returns, injected into the pane the
 * run came from. **Nothing here is stored** — this markup is the whole
 * of the result's existence, and leaving the page loses it.
 *
 * Six outcomes, deliberately not interchangeable. Phase 12 named four
 * and the live path adds two: *refused*, which is MISP declining to
 * ask on the instance's behalf, and *ineligible*, which is this page
 * declining a module the reader was never offered. The distinction
 * that matters most is the old one — **silent is not failure.** A
 * module that answered with nothing has done its job and reported no
 * knowledge of this value, which is a finding.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$run = $valueProfile['run'];
$state = $run['state'];

/*
 * The heading is the claim. Each of these is a different thing to have
 * happened and a reader acts differently on each, so none of them is
 * softened into "no results".
 */
$headings = array(
    'ok' => __('%s answered.'),
    'silent' => __('%s answered with nothing.'),
    'error' => __('%s could not do the job.'),
    'refused' => __('MISP did not send the query.'),
    'unreachable' => __('The enrichment service did not answer.'),
    'ineligible' => __('That module was not offered for this value.'),
);
$heading = isset($headings[$state])
    ? $headings[$state]
    : __('%s answered.');
$heading = strpos($heading, '%s') === false
    ? $heading
    : sprintf($heading, $run['module']);

$prose = array(
    'silent' => __(
        'The module was asked and returned no elements. That is a'
        . ' report of no knowledge about this value, not a failure —'
        . ' it is different from never having asked, and different'
        . ' again from an error.'
    ),
    'error' => __(
        'The module itself reported a problem. A missing or wrong'
        . ' setting looks like this, and so does a third-party service'
        . ' refusing the request.'
    ),
    'refused' => __(
        'The query was stopped before it left the instance. An'
        . ' `enrichment-before-query` workflow can decline a query,'
        . ' and nothing was sent to anybody.'
    ),
    'unreachable' => __(
        'Nothing answered at the configured address. Nothing was sent'
        . ' to any module.'
    ),
    'ineligible' => __(
        'A run may only name a module offered for a type you hold an'
        . ' occurrence of. Nothing was sent anywhere.'
    ),
);
?>
<?php
/*
 * A fresh CSRF token travels with the answer. They are use-once and a
 * reader runs several modules, so the fragment that arrives carries
 * the one the next run will spend — `Security::startup()` mints it on
 * this very request, after validating the one that got here.
 */
$token = isset($this->request->params['_Token']['key'])
    ? $this->request->params['_Token']['key']
    : '';
?>
<div class="vp-e-res"
     data-vp-e-result="<?= h($run['module']) ?>"
     data-vp-e-state-is="<?= h($state) ?>"
     data-vp-e-token="<?= h($token) ?>">

    <div class="vp-e-cold-title">
        <?= h($heading) ?>
    </div>

    <div class="vp-e-meta mb-2">
        <?php if ($run['type'] !== null): ?>
            <?= h(__('asked as')) ?>
            <span class="font-monospace"><?= h($run['type']) ?></span>
            &middot;
        <?php endif; ?>
        <?= h(sprintf(__('%d ms'), $run['took'])) ?>
        <?php if ($state === 'ok'): ?>
            &middot;
            <span class="vp-e-num"><?= h($run['total']) ?></span>
            <?= h(__n(
                'element returned',
                'elements returned',
                $run['total']
            )) ?>
        <?php endif; ?>
        &middot;
        <?= h(__('nothing stored')) ?>
    </div>

    <?php if (isset($prose[$state])): ?>
        <div class="vp-e-cold-prose">
            <?= h($prose[$state]) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($run['message'])): ?>
        <div class="vp-e-why mt-2">
            <div class="font-monospace small"><?= h(
                $run['message']
            ) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($run['capped']): ?>
        <?php
        /*
         * §14.6 keeps cap notices, and this one is stated against the
         * total rather than instead of it: a reader who cannot see
         * 1,174 of 1,374 rows must not be left thinking there were
         * 200. The pressure here comes from outside MISP — the module
         * decides how much to say — which makes the total the only
         * honest thing on the line.
         */
        ?>
        <div class="vp-e-partial mt-2">
            <i class="fas fa-scissors"></i>
            <?= h(sprintf(
                __(
                    'Showing %1$s of %2$s elements. The rest are not'
                    . ' hidden from you — they are more than this'
                    . ' panel renders.'
                ),
                $run['shown'],
                $run['total']
            )) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($run['attributes'])): ?>
        <div class="vp-e-railgroup mt-3">
            <?= h(sprintf(
                __n('%d attribute', '%d attributes', count($run['attributes'])),
                count($run['attributes'])
            )) ?>
        </div>
        <?php foreach ($run['attributes'] as $attribute): ?>
            <div class="vp-e-el">
                <div class="vp-e-el-body">
                    <span class="vp-e-type"><?= h(
                        $attribute['type']
                    ) ?></span>
                    <span class="vp-e-val"><?= h(
                        $attribute['value']
                    ) ?></span>
                    <?php if (!empty($attribute['category'])): ?>
                        <span class="vp-e-meta"><?= h(
                            $attribute['category']
                        ) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($attribute['to_ids'])): ?>
                        <span class="vp-e-meta"><?= h(
                            __('to_ids')
                        ) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($attribute['comment'])): ?>
                    <div class="vp-e-prov-note"><?= h(
                        $attribute['comment']
                    ) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($run['objects'])): ?>
        <div class="vp-e-railgroup mt-3">
            <?= h(sprintf(
                __n('%d object', '%d objects', count($run['objects'])),
                count($run['objects'])
            )) ?>
        </div>
        <?php foreach ($run['objects'] as $object): ?>
            <div class="vp-e-obj">
                <div class="vp-e-obj-head">
                    <span class="vp-e-obj-name"><?= h(
                        $object['name']
                    ) ?></span>
                    <span class="vp-e-obj-count"><?= h(sprintf(
                        __n(
                            '%d attribute',
                            '%d attributes',
                            count($object['attributes'])
                        ),
                        count($object['attributes'])
                    )) ?></span>
                </div>
                <?php foreach ($object['attributes'] as $attribute): ?>
                    <div class="vp-e-rel">
                        <span class="vp-e-rel-name"><?= h(
                            $attribute['relation']
                        ) ?></span>
                        <span class="vp-e-val"><?= h(
                            $attribute['value']
                        ) ?></span>
                        <span class="vp-e-meta"><?= h(
                            $attribute['type']
                        ) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($run['elements'])): ?>
        <div class="vp-e-railgroup mt-3">
            <?= h(sprintf(
                __n('%d element', '%d elements', count($run['elements'])),
                count($run['elements'])
            )) ?>
        </div>
        <?php foreach ($run['elements'] as $element): ?>
            <div class="vp-e-el">
                <div class="vp-e-el-body">
                    <?php foreach ($element['types'] as $type): ?>
                        <span class="vp-e-type"><?= h($type) ?></span>
                    <?php endforeach; ?>
                    <span class="vp-e-val"><?= h(
                        $element['value']
                    ) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php
    /*
     * Offered only where a run actually happened. `ineligible` and
     * `unreachable` did not ask anything, and a *Run again* under
     * either would be inviting a press that is not the one that would
     * help. Reaching this element at all means `perm_add`, since the
     * ACL gates the action — so the control is never the disabled
     * variant here.
     */
    ?>
    <?php if (in_array($state, array('ok', 'silent', 'error'), true)): ?>
        <div class="mt-3 d-flex align-items-center gap-2 flex-wrap">
            <?= $this->element(
                'Values/View/value_enrichment_button',
                array(
                    'module' => array(
                        'name' => $run['module'],
                        'type' => $run['type'],
                    ),
                    'canRun' => true,
                    'noRun' => '',
                    'label' => __('Run again'),
                )
            ) ?>
            <span class="small text-muted">
                <?= h(__(
                    'Re-running asks the module again — this page'
                    . ' remembers no previous answer to compare'
                    . ' against.'
                )) ?>
            </span>
        </div>
    <?php endif; ?>

</div>
