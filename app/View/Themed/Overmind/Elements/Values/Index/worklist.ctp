<?php
/**
 * A pasted list, as the rows to work.
 *
 * `02b-proposal-c.html`'s worklist, at the point phase 4 leaves it:
 * **the rows and what the parse did to get to them, and no
 * assessment** (`value-index.md` §7.2). It is a useful tool at that
 * size on its own — it cleans an IOC list, splits composites, drops
 * duplicates, canonicalises spellings and links every value to its
 * profile — and it is the block phase 5 fills.
 *
 * **What C draws around these rows is deliberately not here.** The
 * progress tape, the rail marks, the *left to do* filters, the worst-
 * first sort, the cards-and-table toggle and the line saying marks
 * live in this tab are one machine for working a batch of decisions,
 * and this phase produces no decisions to work: every row is in the
 * same state, so a tape would be one colour, a filter would never
 * subtract a row and a second view would draw the same thing at a
 * different size. Chrome a reader has to learn before it can do
 * anything is worse than chrome that arrives with its subject.
 *
 * **The rows are the reader's own strings** — a table whose rows are
 * not the strings that were pasted is a table they cannot check
 * (§5) — and the links are the instance's. Those are two different
 * spellings for a value MISP lowercased on the way in, and both
 * matter: the reader checks the row against their report, and the
 * link has to land on a value somebody holds. What the read did is
 * counted in the provenance line rather than marked per row, because
 * a hundred uppercase hashes would otherwise carry a hundred marks
 * all saying the same thing.
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
<div class="vi-work">
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
    <ol class="vi-rows" role="list">
<?php foreach ($rows as $i => $row): ?>
        <li class="vi-row">
            <span class="vi-n"><?= h($i + 1) ?></span>
            <span class="vi-val"><?= h($row['value']) ?></span>
            <span class="vi-rowact">
                <?php
                /*
                 * A new tab, because the list is the work and a
                 * profile is one item of it: a reader who opened a
                 * value in place would come back to a page that had
                 * forgotten their paste.
                 */
                ?>
                <a class="vi-btn" target="_blank" rel="noopener"
                   href="<?= h($this->Html->url(array(
                       'controller' => 'values',
                       'action' => 'view',
                       ValueUrlTool::encode(
                           $row['stored'] === null
                               ? $row['value']
                               : $row['stored']
                       ),
                   ))) ?>"><?= h(__('Open profile')) ?></a>
            </span>
        </li>
<?php endforeach; ?>
    </ol>
</div>
