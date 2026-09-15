<?php
/**
 * `/values/index` — the way in to the Value Profile.
 *
 * **Not an index of values, and it cannot be one.** The subject of
 * this feature is a value string rather than a row, so *all values* is
 * a `GROUP BY value1` over 3.9M attribute rows — 2M distinct — that no
 * `LIMIT` bounds (`value-index.md` §1.1). Every block on this page
 * therefore either takes its values from the reader or reads a table
 * that is genuinely value-keyed.
 *
 * The page's shape is `value-index/02b-proposal-c.html` as picked in
 * `02a-contract.md` §12 — the worklist, with A's prompt. Phase 3 wires
 * the prompt and the resolver's three answers; the worklist, the
 * recently-viewed line, the conditions strip and the method note are
 * phases 4 to 9, and the blocks that have nothing to say are absent
 * rather than drawn empty.
 *
 * @var array|null $resolution What `ValuesController::resolve()` made
 *                             of the box, or null on a plain load
 */
echo $this->element('genericElements/assetLoader', array(
    'css' => array('value-palette', 'value-index'),
    'js' => array('value-index'),
));

$this->set('headerTitle', __('Values'));
$this->set('headerBreadcrumb', __('Data points') . ' > ' . __('Values'));
$this->set('headerDescription', __(
    'Resolve an indicator to its Value Profile.'
));
?>
<div class="vi-page">
    <div class="vi-app">
        <?= $this->element('Values/Index/prompt') ?>
<?php if ($resolution === null): ?>
        <div class="vi-invite">
            <h2><?= h(__('Open a value\'s profile.')) ?></h2>
            <p><?= h(__(
                'Type or paste one value and press Enter. Defanged'
                . ' input is refanged for you, because that is how'
                . ' values are stored. A value nothing here records is'
                . ' an answer rather than an error, and nothing you'
                . ' paste reaches the address bar.'
            )) ?></p>
        </div>
<?php else: ?>
        <div class="vi-answerwrap">
            <?= $this->element('Values/Index/answer', array(
                'resolution' => $resolution,
            )) ?>
        </div>
<?php endif; ?>
    </div>
</div>
