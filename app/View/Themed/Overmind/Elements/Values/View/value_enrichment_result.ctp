<?php
/**
 * One module's answer.
 *
 * The fragment `viewEnrichmentRun` returns, injected into the pane the
 * run came from. **Nothing here is stored** — this markup is the whole
 * of the result's existence, and leaving the page loses it.
 *
 * Seven outcomes, deliberately not interchangeable. Phase 12 named
 * four and the live path adds three: *refused*, which is MISP
 * declining to ask on the instance's behalf, *ineligible*, which is
 * this page declining a module the reader was never offered, and
 * *unreachable*, which is nothing answering at all — distinct from
 * *timeout*, where the module was asked and ran out of time. The
 * distinction that matters most is still the old one: **silent is not
 * failure.** A module that answered with nothing has done its job and
 * reported no knowledge of this value, which is a finding.
 *
 * **`Already in MISP` is the one piece of §8.3's provenance that
 * survives having no store**, and the one that does the most work: it
 * is what stops an analyst adding a duplicate. `New since <date>` was
 * a delta against a previous run and is gone.
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
    'timeout' => __('%s ran out of time.'),
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
    'timeout' => __(
        'The module was asked and did not finish in time. It may'
        . ' answer on a second press — this is not the service being'
        . ' down, and the other modules are untouched.'
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

/*
 * §10's disabled set. These render rather than vanish because this
 * page's rule is that a control which would write is *visibly*
 * disabled — "not implemented", "nothing to show" and "you may not"
 * are three different things and a missing button says none of them.
 * The two reasons are different and the titles say which:
 * `value-profile-writes.md` owns the writes, and the persistence phase
 * owns the dismissal store.
 */
$noWrite = __(
    'Disabled — the Value Profile page does not write to the database'
    . ' yet.'
);
$noDismiss = __(
    'Disabled — a dismissal has nowhere to be remembered. Nothing'
    . ' records that a module ran, so nothing can record that you'
    . ' rejected part of what it returned.'
);

/**
 * The per-element actions, drawn once and reused on every row.
 *
 * §8.4: never per module. MISP enrichment returns attributes and
 * objects and the decision to keep one is per element — a
 * module-level *accept* would write things nobody looked at.
 */
$actions = function () use ($noWrite, $noDismiss) {
    $out = '<span class="vp-e-el-acts">';
    $buttons = array(
        array(__('Add to event'), 'fa-plus', $noWrite),
        array(__('New event'), 'fa-file-circle-plus', $noWrite),
        array(__('Dismiss'), 'fa-xmark', $noDismiss),
    );
    foreach ($buttons as $button) {
        $out .= '<button type="button" disabled'
            . ' class="btn btn-sm btn-outline-secondary disabled"'
            . ' title="' . h($button[2]) . '">'
            . '<i class="fas ' . h($button[1]) . '"></i>'
            . '<span class="visually-hidden">' . h($button[0])
            . '</span></button>';
    }
    return $out . '</span>';
};

/**
 * `Already in MISP`, where it is.
 *
 * @param array $element
 * @return string
 */
$knownChip = function (array $element) {
    if (empty($element['known'])) {
        return '';
    }
    /*
     * The claim is about the **value string**, not about the value
     * under this type — which is the claim §8.3 makes too. One probe
     * for the whole result is what keeps this to a single query, and
     * a per-type probe would be one query per distinct type for a
     * chip whose job is to make somebody look before they add.
     * Verified against the instance: `mmdb_lookup`'s `United States`
     * matches `text` rows and its `38` matches `float` rows, so the
     * untyped probe is not, in practice, matching across types.
     */
    return '<span class="vp-e-known" title="' . h(__(
        'MISP already holds this value somewhere you can see it.'
        . ' Check before adding it again.'
    )) . '">' . h(__('Already in MISP')) . '</span>';
};

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

    <div class="d-flex justify-content-between align-items-start
                flex-wrap gap-2">
        <div class="vp-e-cold-title">
            <?= h($heading) ?>
        </div>
        <?php if ($state === 'ok'): ?>
            <?php
            /*
             * §8's `Add all 6`, disabled. It is the one module-level
             * write the mockup allows, and only because it is a
             * shorthand for the per-element ones rather than a
             * different decision.
             */
            ?>
            <button type="button" disabled
                    class="btn btn-sm btn-outline-secondary disabled
                           d-inline-flex align-items-center gap-1"
                    title="<?= h($noWrite) ?>">
                <i class="fas fa-plus"></i>
                <?= h(sprintf(__('Add all %d'), $run['shown'])) ?>
            </button>
        <?php endif; ?>
    </div>

    <?php
    /*
     * §8's provenance line. Live it says what the module *is* and what
     * this press cost, where the fixture also said when it last ran.
     */
    ?>
    <div class="vp-e-meta mb-2">
        <?php if (!empty($run['kinds'])): ?>
            <?= h(implode('+', $run['kinds'])) ?>
            &middot;
        <?php endif; ?>
        <?php if ($run['format'] !== null): ?>
            <span class="font-monospace"><?= h($run['format']) ?></span>
            &middot;
        <?php endif; ?>
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
                __n(
                    '%d attribute',
                    '%d attributes',
                    count($run['attributes'])
                ),
                count($run['attributes'])
            )) ?>
        </div>
        <?php foreach ($run['attributes'] as $attribute): ?>
            <div class="vp-e-el" data-vp-e-item>
                <div class="vp-e-el-body">
                    <span class="vp-e-type"><?= h(
                        $attribute['type']
                    ) ?></span>
                    <span class="vp-e-val"><?= h(
                        $attribute['value']
                    ) ?></span>
                    <?php if (!empty($attribute['to_ids'])): ?>
                        <span class="vp-e-meta"><?= h(
                            __('to_ids')
                        ) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($attribute['category'])): ?>
                        <span class="vp-e-meta"><?= h(
                            $attribute['category']
                        ) ?></span>
                    <?php endif; ?>
                    <?= $knownChip($attribute) ?>
                    <?= $actions() ?>
                </div>
                <?php if (!empty($attribute['comment'])): ?>
                    <div class="vp-e-meta"><?= h(
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
        <?php foreach ($run['objects'] as $index => $object): ?>
            <?php
            /*
             * §10's per-object expansion. Open by default for a small
             * answer and folded for a long one: `circl_passivedns`
             * returns two hundred of these, and a pane that opens
             * with two hundred seven-row tables is not a pane a
             * reader can find anything in.
             */
            $open = count($run['objects']) <= 5;
            ?>
            <div class="vp-e-obj" data-vp-e-item>
                <div class="vp-e-obj-head">
                    <button type="button"
                            class="vp-e-disc"
                            data-vp-e-disc
                            aria-expanded="<?= $open ? 'true' : 'false' ?>">
                        <i class="fas fa-chevron-right"></i>
                        <span class="visually-hidden"><?= h(
                            __('Show the attributes')
                        ) ?></span>
                    </button>
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
                <div class="<?= $open ? '' : 'd-none' ?>" data-vp-e-fold>
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
                            <?= $knownChip($attribute) ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="vp-e-rel">
                        <?= $actions() ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($run['elements'])): ?>
        <div class="vp-e-railgroup mt-3">
            <?= h(sprintf(
                __n(
                    '%d element',
                    '%d elements',
                    count($run['elements'])
                ),
                count($run['elements'])
            )) ?>
        </div>
        <?php foreach ($run['elements'] as $element): ?>
            <div class="vp-e-el" data-vp-e-item>
                <div class="vp-e-el-body">
                    <?php foreach ($element['types'] as $type): ?>
                        <span class="vp-e-type"><?= h($type) ?></span>
                    <?php endforeach; ?>
                    <span class="vp-e-val"><?= h(
                        $element['value']
                    ) ?></span>
                    <?= $knownChip($element) ?>
                    <?= $actions() ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php
    /*
     * Offered only where a run actually happened. `ineligible` and
     * `unreachable` did not ask anything, and a *Run again* under
     * either would be inviting a press that is not the one that would
     * help. A timeout gets one, because pressing again is exactly what
     * might work. Reaching this element at all means `perm_add`, since
     * the ACL gates the action — so the control is never the disabled
     * variant here.
     */
    ?>
    <?php if (in_array(
        $state,
        array('ok', 'silent', 'error', 'timeout'),
        true
    )): ?>
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
