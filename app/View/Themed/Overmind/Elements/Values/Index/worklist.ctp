<?php
/**
 * A pasted list, as the rows to work.
 *
 * `02b-proposal-c.html`'s worklist. Phase 4 built the rows and what the
 * parse did to get to them; phase 5 gives them their assessments and,
 * with them, the machine C is for — the progress tape, a mark per row,
 * the *left to do* filters, the worst-first sort, the compact table
 * view and the line saying where the marks live.
 *
 * **That chrome arrives with its subject and not before.** Until there
 * were decisions to work, a tape would have been one colour, a filter
 * would never have subtracted a row and a second view would have drawn
 * the same thing at a different size — so phase 4 shipped none of it.
 * It is drawn now, and only for a reader whose browser can fill the
 * rows: with no script no assessment ever arrives, so `.is-live` gates
 * every control on the thing that makes them mean anything, and what
 * is left is exactly the list phase 4 shipped.
 *
 * **The rows are the reader's own strings** — a table whose rows are
 * not the strings that were pasted is a table they cannot check
 * (§5) — and the links, and the assessments, are the instance's. Those
 * are two different spellings for a value MISP lowercased on the way
 * in, and both matter: the reader checks the row against their report,
 * and the assessment has to be of a value somebody holds, or the row
 * would disagree with the profile page it links to. What the read did
 * is counted in the provenance line rather than marked per row.
 *
 * @var array $triage `rows`, `recased`, `collapsed` from
 *                    `ValueProfile::forTriage`, and the parser's
 *                    `report`
 */
App::uses('ValueUrlTool', 'Tools/ValueProfile');
App::uses('ValueInputTool', 'Tools/ValueProfile');

$report = $triage['report'];
$rows = $triage['rows'];

/*
 * The tape and its legend are a picture of a batch's progress, and
 * below eight rows the picture says less than the rows themselves do.
 * The prototype's threshold, kept.
 */
$batched = count($rows) >= 8;

/*
 * What the parse did, as a sentence a reader can check against their
 * own paste. Only the clauses that happened are said: a list nothing
 * was done to gets the count and a full stop, which is the common
 * case and the one that should read shortest.
 */
$did = array();
if ($report[ValueInputTool::REFANGED]) {
    $did[] = sprintf(__('%d refanged'),
        $report[ValueInputTool::REFANGED]);
}
if ($report[ValueInputTool::UNQUOTED]) {
    $did[] = sprintf(__('%d unquoted'),
        $report[ValueInputTool::UNQUOTED]);
}
if ($report['composites']) {
    $did[] = sprintf(__n('%d composite split in two',
        '%d composites split in two', $report['composites']),
        $report['composites']);
}
if ($report['duplicates']) {
    $did[] = sprintf(__n('%d duplicate dropped',
        '%d duplicates dropped', $report['duplicates']),
        $report['duplicates']);
}
if ($triage['collapsed']) {
    $did[] = sprintf(__('%d collapsed onto another spelling'),
        $triage['collapsed']);
}
if ($triage['recased']) {
    $did[] = sprintf(__('%d linked to the spelling this instance'
        . ' holds'), $triage['recased']);
}
if ($report['empty']) {
    /*
     * Not *blank lines*: a blank line never reaches this count, since
     * the field split drops it before anything is normalised. What is
     * counted here is a line that looked like a value and left
     * nothing — a stray pair of quotes, a lone zero-width space off a
     * web page — which is worth saying precisely because the reader
     * cannot see it in their own paste.
     */
    $did[] = sprintf(__('%d left nothing'), $report['empty']);
}

/*
 * The lead names the unit the paste chose, because that is the rule
 * a reader most needs to see having run: a line-per-value paste is
 * unsurprising, and a single line split on its commas is the one
 * reading this box makes that the reader might not have meant.
 */
$byCommas = $report['separator'] === ValueInputTool::BY_COMMAS;
$lead = $byCommas
    ? sprintf(__n('One line, split on its %1$d comma, became %2$d row.',
        'One line, split on its %1$d commas, became %2$d rows.',
        count($rows)), max(0, $report['fields'] - 1), count($rows))
    : sprintf(__n('%1$d line became %2$d row.',
        '%1$d lines became %2$d rows.', count($rows)),
        $report['lines'], count($rows));
?>
<div class="vi-work" data-vi-work
     data-vi-assess="<?= h($this->Html->url(array(
         'controller' => 'values',
         'action' => 'assess',
     ))) ?>">
    <p class="vi-prov">
        <b><?= h($lead) ?></b>
<?php if ($did): ?>
        <?= h(ucfirst(implode(', ', $did)) . '.') ?>
<?php else: ?>
        <?= h(__('Nothing was changed.')) ?>
<?php endif; ?>
        <?= h(__('Your paste is still in the box above, to check any'
            . ' of it against.')) ?>
    </p>
<?php if ($byCommas && count($rows) > 1): ?>
    <?php
    /*
     * The one paste whose reading is genuinely ambiguous, offered
     * rather than decided (§7.2). A value may legitimately contain a
     * comma — a `text` attribute does — and on one line the commas
     * are the only separator there is, so splitting is the reading
     * that serves nearly everybody and the offer serves the rest.
     * Its own form, because `FormHelper` hashes a field list together
     * with the action it posts to, and this one posts to `resolve`
     * from inside a fragment `triage` rendered.
     */
    echo $this->Form->create('Value', array(
        'class' => 'vi-whole',
        'url' => $this->Html->url(array(
            'controller' => 'values',
            'action' => 'resolve',
        )),
    ));
    echo $this->Form->hidden('value', array(
        'value' => isset($this->request->data['Value']['value'])
            ? $this->request->data['Value']['value']
            : '',
    ));
    echo $this->Form->hidden('whole', array('value' => 1));
    ?>
        <span class="vi-mark"><?= h(__(
            'If those commas are part of one value:'
        )) ?></span>
        <button type="submit" class="vi-btn"><?= h(__(
            'Open the whole line as one value'
        )) ?></button>
    <?= $this->Form->end() ?>
<?php endif; ?>
    <div class="vi-chrome">
        <div class="vi-tapewrap">
            <?php
            /*
             * The one sentence that says where the reader is. It moves
             * twice — once as the assessments land, once as decisions
             * are made — and it is the only thing on this page that is
             * announced, because a batch's progress is exactly what a
             * reader who has looked away needs told.
             */
            ?>
            <?php
            /*
             * Every wording travels with the markup, as the prompt's
             * verb does: a sentence built in a `.js` file is a
             * sentence no catalogue translates.
             */
            ?>
            <span class="vi-progress" data-vi-progress
                  aria-live="polite"
                  data-vi-filling="<?= h(__('%1$d of %2$d assessed,'
                      . ' %3$d decided')) ?>"
                  data-vi-worked="<?= h(__('All %d decided — the batch'
                      . ' is worked')) ?>"
                  data-vi-left="<?= h(__('%1$d of %2$d decided, %3$d'
                      . ' left')) ?>"
                  data-vi-lost="<?= h(__('%d did not come back')) ?>"
                  ></span>
<?php if ($batched): ?>
            <span class="vi-tape" data-vi-tape role="group"
                  aria-label="<?= h(__('Batch progress')) ?>"></span>
<?php endif; ?>
        </div>
<?php if ($batched): ?>
        <div class="vi-legend">
            <span><span class="vi-swatch" data-s="queued"></span><?= h(__(
                'waiting for a lane')) ?></span>
            <span><span class="vi-swatch" data-s="flight"></span><?= h(__(
                'assessment in flight')) ?></span>
            <span><span class="vi-swatch" data-s="todo"></span><?= h(__(
                'assessed, not yet decided')) ?></span>
            <span><span class="vi-swatch" data-s="opened"></span><?= h(__(
                'opened')) ?></span>
            <span><span class="vi-swatch" data-s="cleared"></span><?= h(__(
                'cleared')) ?></span>
        </div>
<?php endif; ?>
        <div class="vi-controls">
            <span class="vi-segs" data-vi-filters>
                <button class="vi-seg" type="button" data-vi-f="all"
                        aria-pressed="true"><?= h(__('All')) ?> <b></b></button>
                <button class="vi-seg" type="button" data-vi-f="todo"
                        aria-pressed="false"><?= h(__('Left to do')) ?> <b></b></button>
                <button class="vi-seg" type="button" data-vi-f="opened"
                        aria-pressed="false"><?= h(__('Opened')) ?> <b></b></button>
                <button class="vi-seg" type="button" data-vi-f="cleared"
                        aria-pressed="false"><?= h(__('Cleared')) ?> <b></b></button>
            </span>
            <span class="vi-segs">
                <button class="vi-seg" type="button" data-vi-sort
                        aria-pressed="false"><?= h(__('Worst first')) ?></button>
            </span>
            <span class="vi-segs" data-vi-views>
                <button class="vi-seg" type="button" data-vi-view="cards"
                        aria-pressed="true"><?= h(__('Cards')) ?></button>
                <button class="vi-seg" type="button" data-vi-view="table"
                        aria-pressed="false"><?= h(__('Table')) ?></button>
            </span>
            <span class="vi-keys">
                <span><span class="vi-kbd">j</span> <span class="vi-kbd">k</span> <?= h(__('move')) ?></span>
                <span><span class="vi-kbd">o</span> <?= h(__('open')) ?></span>
                <span><span class="vi-kbd">x</span> <?= h(__('clear')) ?></span>
                <span><span class="vi-kbd">u</span> <?= h(__('undo')) ?></span>
                <?php
                /*
                 * Printed beside the controls it qualifies rather than
                 * in a footnote. Nothing on this page writes (§0), so
                 * a progress model built on it lives in one browser
                 * tab — and a reader who worked forty rows and then
                 * lost them to a refresh was not told.
                 */
                ?>
                <span class="vi-tabonly"><?= h(__('Marks live in this'
                    . ' tab only. Nothing here writes to MISP.')) ?></span>
            </span>
        </div>
        <?php
        /*
         * The sort note says what the order now means, because a table
         * a reader can no longer check line by line against their own
         * source has stopped being the thing they pasted. Its two
         * wordings differ in one clause — whether anything is still
         * arriving — because a row cannot be ranked before it has an
         * answer, and a reader watching rows jump needs telling that
         * the ones below are not last, only unanswered.
         */
        ?>
        <div class="vi-sortnote" data-vi-sortnote hidden
             data-vi-mid="<?= h(__('Sorted by assessment — the %1$d that'
                 . ' have landed. The %2$d still arriving keep their'
                 . ' place below, because a row cannot be ranked before'
                 . ' it has an answer.')) ?>"
             data-vi-settled="<?= h(__('Sorted by assessment. Paste order'
                 . ' is the one you can check against your own'
                 . ' source.')) ?>"
             data-vi-back="<?= h(__('Back to paste order')) ?>"></div>
    </div>
    <div class="vi-listwrap">
        <div class="vi-thead" aria-hidden="true">
            <span></span>
            <span><?= h(__('value')) ?></span>
            <span class="vi-c-types"><?= h(__('types')) ?></span>
            <span class="vi-c-lean"><?= h(__('lean')) ?></span>
            <span class="vi-c-qual"><?= h(__('quality')) ?></span>
            <span class="vi-c-rel"><?= h(__('relevance')) ?></span>
            <span class="vi-c-num vi-c-orgs"><?= h(__('orgs')) ?></span>
            <span class="vi-c-num vi-c-sig"><?= h(__('sightings')) ?></span>
            <span></span>
        </div>
        <ol class="vi-rows<?= $batched ? '' : ' vi-rows--short' ?>"
            data-vi-rows tabindex="0" role="list"
            aria-label="<?= h(__('Values to work')) ?>">
<?php foreach ($rows as $i => $row): ?>
            <?php
            /*
             * **The assessment is asked about the instance's spelling
             * and the row shows the reader's.** Sent the reader's, the
             * row would carry a verdict on a string nobody filed, and
             * would then disagree with the profile the same row links
             * to. Where the instance holds no spelling there is
             * nothing to prefer, and the reader's own is what a value
             * nobody recorded is asked about.
             */
            $asked = $row['stored'] === null
                ? $row['value']
                : $row['stored'];
            ?>
            <li class="vi-row" data-vi-row="<?= h($i) ?>"
                data-vi-value="<?= h($asked) ?>">
                <?php
                /*
                 * The gutter carries the row's ordinal until the
                 * script takes it over for the state mark. Without a
                 * script there is no state to draw and the number is
                 * the more useful thing — it is also the list phase 4
                 * shipped, unchanged.
                 */
                ?>
                <span class="vi-rail" data-vi-rail><?= h($i + 1) ?></span>
                <span class="vi-fill" data-vi-fill>
                    <div class="vi-card">
                        <div class="vi-l1">
                            <span class="vi-val"><?= h($row['value']) ?></span>
                        </div>
                        <div class="vi-pend" aria-hidden="true">
                            <i class="vi-bar" style="width:86px"></i>
                            <i class="vi-bar" style="width:44px"></i>
                            <i class="vi-bar" style="width:120px"></i>
                            <span class="vi-pendtxt vi-pendtxt--queued"><?= h(__(
                                'waiting for a lane')) ?></span>
                            <span class="vi-pendtxt vi-pendtxt--flight"><?= h(__(
                                'assessing…')) ?></span>
                        </div>
                        <div class="vi-fail"><?= h(__('The assessment did'
                            . ' not come back. This row is unknown, not'
                            . ' empty.')) ?></div>
                    </div>
                    <div class="vi-cells">
                        <span class="vi-c-val">
                            <span class="vi-c-valtext"><?= h($row['value']) ?></span>
                        </span>
                        <span class="vi-c-types"></span>
                        <span class="vi-c-lean"><i class="vi-bar" style="width:72px"></i></span>
                        <span class="vi-c-qual"></span>
                        <?php
                        /*
                         * One cell holding both wordings, never two
                         * cells: a hidden grid item leaves its column
                         * to the next one along, and a row whose
                         * columns slide by one is the alignment this
                         * view exists for, gone.
                         */
                        ?>
                        <span class="vi-c-rel"><span
                            class="vi-pendtxt vi-pendtxt--queued"><?= h(__(
                            'waiting for a lane')) ?></span><span
                            class="vi-pendtxt vi-pendtxt--flight"><?= h(__(
                            'assessing…')) ?></span></span>
                        <span class="vi-c-num vi-c-orgs"></span>
                        <span class="vi-c-num vi-c-sig"></span>
                        <span class="vi-c-say"><?= h(__('The assessment did'
                            . ' not come back.')) ?></span>
                    </div>
                    <div class="vi-acts">
                        <button type="button" class="vi-btn vi-retry"
                                data-vi-act="retry"><?= h(__(
                            'Ask again')) ?></button>
                        <a class="vi-btn" target="_blank" rel="noopener"
                           href="<?= h($this->Html->url(array(
                               'controller' => 'values',
                               'action' => 'view',
                               ValueUrlTool::encode($asked),
                           ))) ?>"><?= h(__('Open')) ?><span
                           class="vi-cardonly"> <?= h(__('profile')) ?></span></a>
                    </div>
                </span>
            </li>
<?php endforeach; ?>
        </ol>
        <?php
        /*
         * A filter that subtracts every row is a working filter, not a
         * broken list, and an empty box with no explanation is
         * indistinguishable from one that failed to load.
         */
        ?>
        <p class="vi-empty" data-vi-empty hidden><?= h(__('No rows in'
            . ' this view. Switch back to All.')) ?></p>
    </div>
    <?php
    /*
     * The end of the batch, and the one thing a page that writes
     * nothing can still hand over: the shortlist. It is rendered with
     * the rows and shown when the last decision is made, so the two
     * numbers in it are filled by the script rather than built by it.
     */
    ?>
    <div class="vi-done" data-vi-done hidden>
        <h3 data-vi-done-lead
            data-vi-tpl="<?= h(__('You worked all %d.')) ?>"></h3>
        <p><span data-vi-done-counts
                 data-vi-tpl="<?= h(__('%1$d opened, %2$d cleared.')) ?>"></span>
            <?= h(__('None of that was saved anywhere — this page writes'
                . ' nothing, so the marks end when the tab does. Take'
                . ' the shortlist with you instead.')) ?></p>
        <div class="vi-offer">
            <button type="button" class="vi-btn vi-btn--lead"
                    data-vi-copy
                    data-vi-tpl="<?= h(__('Copy the %d you opened')) ?>"></button>
            <button type="button" class="vi-btn" data-vi-again><?= h(__(
                'Start the batch over')) ?></button>
            <span class="vi-mark"><?= h(__('Or paste a new batch above —'
                . ' the box still holds this one.')) ?></span>
        </div>
        <textarea class="vi-shortlist" data-vi-short hidden
                  aria-label="<?= h(__('The values you opened')) ?>"></textarea>
    </div>
</div>
