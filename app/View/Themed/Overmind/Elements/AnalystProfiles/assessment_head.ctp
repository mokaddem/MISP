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
    <?= h(__('Three readings, not one score.')) ?>
    <?= sprintf(
        h(__('The %1$slean%2$s anchors the ledger — every row is points ×'
            . ' polarity and %1$squality%2$s is that anchored sum, with the'
            . ' band cut from it. %1$sRelevance%2$s is the clock, and'
            . ' touches neither.')),
        '<b>',
        '</b>'
    ) ?>
    <?php if ($lean['after'] === 'contested'): ?>
        <?= h(__('This value is contested, so there is no polarity and'
            . ' the ledger renders threat-signed.')) ?>
    <?php elseif ($lean['after'] === 'none'): ?>
        <?= h(__('This value has a lean of none, so there is no ledger'
            . ' at all.')) ?>
    <?php endif; ?>
</p>
