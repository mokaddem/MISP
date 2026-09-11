<?php
/**
 * The right-hand pane: what the candidate does to a real value.
 *
 * This is the whole bet of the design. MISP already shipped a
 * simulator nobody used, and a simulator that is a destination is a
 * simulator that is not used — so the bench is half the editor and
 * cannot be skipped. It is the same computation the `simulate` page
 * gives the whole width to, which is why both read one payload.
 *
 * It computes and it writes nothing, and it says so.
 *
 * @var array $bench The simulation: base, in_force, detail, comparison
 * @var bool $full Every ledger row, or only the rows that moved
 * @var array $bands
 * @var string $profileId
 */
App::uses('ValueUrlTool', 'Tools');

$full = isset($full) ? $full : false;
$detail = $bench['detail'];
$focus = $bench['focus'];
$values = $bench['values'];
$pinned = $bench['comparison_set'];
$benched = !empty($values) ? $values[0] : null;
?>
<div class="wb-bench-inner">
    <?php if ($benched === null): ?>
        <div class="wb-empty">
            <div class="fw-semibold"><?= h(__('Nothing on the bench')) ?></div>
            <p class="mb-0 mt-1">
                <?= h(__('Pin a value and it appears here, scored under the'
                    . ' profile you are editing beside the one in force.'
                    . ' Arriving from a value page benches that value for'
                    . ' the visit — it does not pin it, which takes a'
                    . ' press.')) ?>
            </p>
        </div>
    <?php else: ?>
        <div class="bench-value">
            <span class="v"><?= h($benched) ?></span>
            <?php if ($focus !== null && $focus === $benched
                && !in_array($benched, $pinned, true)): ?>
                <span class="wb-sub"><?= h(__('benched, not pinned —'
                    . ' arrived with you from its value page')) ?></span>
                <form method="post" class="d-inline"
                      action="<?= h($this->Html->url(array(
                          'action' => 'pin',
                          ValueUrlTool::encode($benched),
                      ))) ?>">
                    <button type="submit"
                            class="btn btn-sm btn-outline-primary py-0 px-2">
                        <?= h(__('Pin')) ?>
                    </button>
                </form>
            <?php else: ?>
                <span class="wb-sub"><?= h(__('pinned')) ?></span>
            <?php endif; ?>
        </div>

        <?php if (count($values) > 1): ?>
            <div class="bench-pick">
                <div class="bench-quick">
                    <span class="wb-sub"><?= h(__('yours:')) ?></span>
                    <?php foreach ($values as $candidate): ?>
                        <a class="chip <?= $candidate === $benched
                                ? 'is-on' : '' ?>"
                           href="<?= h($this->Html->url(array(
                               'action' => $this->request->params['action'],
                               $profileId,
                               '?' => array(
                                   'value' => ValueUrlTool::encode($candidate),
                               ),
                           ))) ?>"><?= h($candidate) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="bench-live">
            <?= h(sprintf(__n(
                'recomputed · %s context build',
                'recomputed · %s context builds',
                $bench['context_builds']
            ), $bench['context_builds'])) ?>
        </div>

        <?php if ($detail !== null): ?>
            <div class="bench-sec">
                <span><?= h(__('Assessment')) ?></span>
                <span class="wb-sub"><?= h(__('three axes, read separately')) ?></span>
            </div>
            <?= $this->element('AnalystProfiles/assessment_head', array(
                'axes' => $detail['axes'],
                'moved' => $detail['changed'],
            )) ?>

            <div class="bench-sec">
                <span><?= h(__('The quality ledger')) ?></span>
                <span class="wb-sub"><?= h(__('the only axis that sums')) ?></span>
            </div>
            <div class="bench-q">
                <span class="now"><?= h($detail['totals']['after']) ?></span>
                <span class="from">
                    <?= h(__('quality')) ?><br>
                    <span class="num"><?= h($detail['totals']['before']) ?></span>
                    <?= h(__('under the profile in force')) ?>
                </span>
                <span class="num <?= $detail['totals']['delta'] > 0
                        ? 'd-up'
                        : ($detail['totals']['delta'] < 0 ? 'd-dn' : 'd-0') ?>"
                      style="font-size:1.1rem;font-weight:700;margin-left:auto">
                    <?= h($detail['totals']['delta'] > 0
                        ? '+' . $detail['totals']['delta']
                        : $detail['totals']['delta']) ?>
                </span>
            </div>

            <?= $this->element('AnalystProfiles/block_strip', array(
                'strip' => $bands,
                'marks' => array(
                    'was' => $detail['totals']['before'],
                    'now' => $detail['totals']['after'],
                ),
            )) ?>

            <div class="bench-axes bench-meta">
                <div>
                    <span><?= h(__('signals fired')) ?></span>
                    <b class="num"><?= h($detail['axes']['fired']['after']) ?></b>
                </div>
                <div>
                    <span><?= h(__('attainable bound')) ?></span>
                    <b class="num"><?= h($bands['bound']) ?></b>
                </div>
                <div>
                    <span><?= h(__('saved')) ?></span>
                    <b class="wb-sub"><?= h(__('nothing, ever')) ?></b>
                </div>
            </div>

            <?php $rule = $detail['axes']['rule']['after']; ?>
            <?php if (!empty($rule)): ?>
                <div class="wb-note warn mt-2">
                    <b><?= h($detail['axes']['lean']['after']) ?></b>
                    &mdash; <?= h($rule['prose']) ?>
                    <?php if (!empty($rule['evidence'])): ?>
                        <div class="wb-sub mt-1"><?= h($rule['evidence']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="bench-sec" style="margin-top:1.1rem">
                <span><?= $full
                    ? h(__('Every ledger row'))
                    : h(__('What moved')) ?></span>
                <span class="num"><?= h(sprintf(
                    __('%1$s of %2$s rows'),
                    count($detail['moved']),
                    count($detail['rows'])
                )) ?></span>
            </div>
            <?= $this->element('AnalystProfiles/diff_table', array(
                'detail' => $detail,
                'full' => $full,
            )) ?>
        <?php endif; ?>

        <?= $this->element('AnalystProfiles/comparison', array(
            'comparison' => $bench['comparison'],
            'pinned' => $pinned,
        )) ?>
    <?php endif; ?>

    <?php if (!$full): ?>
        <a class="btn btn-sm btn-outline-primary w-100 mt-3"
           href="<?= h($this->Html->url(array(
               'action' => 'simulate',
               $profileId,
               '?' => $focus === null
                   ? array()
                   : array('value' => ValueUrlTool::encode($focus)),
           ))) ?>">
            <?= h(__('Expand the bench — every row, both columns')) ?>
        </a>
    <?php endif; ?>
</div>
