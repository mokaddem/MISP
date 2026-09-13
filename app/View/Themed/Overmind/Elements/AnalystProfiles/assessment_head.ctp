<?php
/**
 * The assessment: lean, relevance and quality, at one rank.
 *
 * Quality is the only axis with an additive ledger, so it is the only
 * one a design can show its work for — and every prototype in 8b put
 * its visual mass there and listed the other two as fields. Two of the
 * three axes ended up rendered as peers of *"saved: nothing, ever"*.
 * Three cells of equal width is the correction, and the relation
 * between them is stated once underneath rather than left to be
 * inferred from the layout.
 *
 * @var array $axes From the diff — each axis as before/after/changed
 * @var bool $moved Whether anything moved at all
 */
App::uses('ValueRelevanceTool', 'Tools');
$lean = $axes['lean'];
$relevance = $axes['relevance'];
$quality = $axes['quality'];
$band = $axes['band'];
$runway = isset($relevance['runway']) ? $relevance['runway'] : null;
?>
<div class="bench-ax3">
    <div class="ax">
        <span class="ax-n"><?= h(__('lean')) ?></span>
        <b class="ax-v"><?= h($lean['after'] === null
            ? __('none') : $lean['after']) ?></b>
        <?php if (!empty($lean['changed'])): ?>
            <span class="ax-was"><?= h(sprintf(
                __('was %s'), $lean['before'] === null
                    ? __('none') : $lean['before']
            )) ?></span>
        <?php endif; ?>
        <span class="ax-g"><?= h(__('what the record asserts')) ?></span>
    </div>
    <div class="ax">
        <span class="ax-n"><?= h(__('relevance')) ?></span>
        <b class="ax-v"><?= $relevance['after'] === null
            ? h(__('no clock'))
            : h(isset($relevance['label']) && $relevance['label'] !== ''
                ? $relevance['label']
                : $relevance['after']) ?></b>
        <?php if (!empty($relevance['changed'])): ?>
            <span class="ax-was"><?= h(sprintf(
                __('was %s'), $relevance['before'] === null
                    ? __('no clock') : $relevance['before']
            )) ?></span>
        <?php endif; ?>
        <span class="ax-g"><?= h(__('whether it still holds')) ?></span>
        <?= $this->element('AnalystProfiles/runway', array(
            'runway' => $runway,
        )) ?>

    </div>
    <div class="ax ax-led">
        <span class="ax-n"><?= h(__('quality')) ?></span>
        <b class="ax-v num">
            <?= h($quality['after']) ?>
            <?php if (!empty($quality['changed'])): ?>
                <span class="ax-was"><?= h(sprintf(
                    __('was %s'), $quality['before']
                )) ?></span>
            <?php endif; ?>
        </b>
        <span class="ax-g">
            <?= h(__('how well evidenced')) ?> &middot;
            <?= h(__('band')) ?> <b><?= h($band['after']) ?></b>
            <?php if (!empty($band['changed'])): ?>
                <span class="ax-was"><?= h(sprintf(
                    __('was %s'), $band['before']
                )) ?></span>
            <?php endif; ?>
        </span>
    </div>
</div>
<p class="bench-rel">
    <?php
    /*
     * One line on screen, the rest behind the `i` — 09b-revisions §3.7
     * asked for that length and this is it. The wording is the plain
     * one: *points × polarity* and *anchored sum* are exact and they
     * are also the two phrases a reader has to already know the model
     * to parse, which is the wrong way round for the sentence whose
     * job is teaching the model.
     */
    ?>
    <?= sprintf(
        h(__('Three readings, not one score: %1$slean%2$s is what the'
            . ' record says this value is, %1$squality%2$s is how well'
            . ' backed up that is, and %1$srelevance%2$s is whether it'
            . ' still holds.')),
        '<b>',
        '</b>'
    ) ?>
    <a href="#" class="wb-i" onclick="return false;"
       title="<?= h(__("The lean is decided first, by counting how many"
           . " organisations reported this value as a threat and how many"
           . " called it harmless. No points are involved.\n\n"
           . "Then the quality is counted. Every signal scores the value"
           . " on one scale, where a plus means it looks dangerous and a"
           . " minus means it looks harmless. If the lean came out"
           . " benign, that whole scale is flipped — so a plus always"
           . " means the evidence supports the lean, and a minus always"
           . " means it argues with it.\n\n"
           . "The quality is those numbers added up, and the band is cut"
           . " from the total. Relevance is a clock and touches"
           . " neither.")) ?>">i</a>
    <?php if ($lean['after'] === 'contested'): ?>
        <?= h(__('This value is contested, so there is no polarity and'
            . ' the ledger renders threat-signed.')) ?>
    <?php elseif ($lean['after'] === 'none'): ?>
        <?= h(__('This value has a lean of none, so there is no ledger'
            . ' at all.')) ?>
    <?php endif; ?>
</p>
<?php
/*
 * The dates the relevance axis is built on — the same element the value
 * page's Lifetime card draws, because a reader tuning
 * `undated_assumed_days` three fields away is exactly the reader who
 * needs to know whether this value declares a `first_seen` at all.
 *
 * **Below the strip, not inside it.** The first attempt put it in the
 * relevance column, which is one third of the bench: 125px for three
 * columns, and `Attribute.first_seen` overlapped the cell beside it.
 * The strip is three readings side by side; this is one table about
 * one of them, and it needs the width.
 *
 * Folded shut. The bench is an assessment, not a reference table.
 */
?>
<?php if (!empty($dates) && !empty($runway['clock'])): ?>
    <div class="bench-dates">
        <?= $this->element('Values/View/value_date_sources', array(
            'facts' => $dates,
            'clockKind' => $runway['clock']['kind'] ?? null,
            'clockLabel' => ValueRelevanceTool::clockLabel(
                $runway['clock']['setting'] ?? null
            ),
            'lastSighting' => $lastSighting ?? null,
            'sightingTotal' => $sightingTotal ?? 0,
        )) ?>
    </div>
<?php endif; ?>
