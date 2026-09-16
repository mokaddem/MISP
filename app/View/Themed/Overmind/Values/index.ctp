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
 * the prompt and the resolver's answers, phase 4 the rows a paste
 * becomes, phase 6 the carried-over line and phase 7 the conditions
 * strip; the method note and the enrichment count are phases 8 and 9,
 * and the blocks that have nothing to say are absent rather than drawn
 * empty.
 *
 * **The strip is above the box and the carried-over line below it.**
 * What the strip says — whose thresholds decide the assessments —
 * is true of every answer the box will give, so a reader meets it
 * before the first one rather than after.
 *
 * **One region below the prompt, and it has one occupant.** The
 * invitation, an answer and the worklist are three things to say about
 * the same box and never two at once, so they share a container the
 * page's script can refill from `ValuesController::triage()` without
 * the paste above it moving.
 *
 * **The carried-over line sits outside that region**, at the foot of
 * the card, because it is the one block on the page that is not about
 * the box: a reader who pastes a list has not stopped being the reader
 * who opened something an hour ago, and a strip the script wiped on
 * every submission would be a way back that disappears the moment it
 * is wanted.
 *
 * @var array|null $resolution What `ValuesController::resolve()` made
 *                             of the box, or null on a plain load
 * @var array|null $triage The rows a pasted list became, or null
 * @var array $recent The values this reader last opened, newest first
 * @var array|null $inForce The Analyst Profile deciding this reader's
 *                          assessments, or null when none is
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
        <?= $this->element('Values/Index/conditions', array(
            'inForce' => $inForce,
        )) ?>
        <?= $this->element('Values/Index/prompt') ?>
        <div class="vi-out" data-vi-out>
<?php if ($triage !== null): ?>
            <?= $this->element('Values/Index/worklist', array(
                'triage' => $triage,
            )) ?>
<?php elseif ($resolution === null): ?>
            <div class="vi-invite">
                <h2><?= h(__('Open a value\'s profile.')) ?></h2>
                <p><?= h(__(
                    'Type or paste one value and press Enter, or paste'
                    . ' a list and get a row for each. Defanged input'
                    . ' is refanged for you, because that is how'
                    . ' values are stored. A value nothing here'
                    . ' records is an answer rather than an error, and'
                    . ' nothing you paste reaches the address bar.'
                )) ?></p>
            </div>
<?php else: ?>
            <?= $this->element('Values/Index/answer', array(
                'resolution' => $resolution,
            )) ?>
<?php endif; ?>
        </div>
        <?= $this->element('Values/Index/recent', array(
            'recent' => $recent,
        )) ?>
    </div>
</div>
