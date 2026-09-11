<?php
/**
 * A palette: every signal, escalation or exclusion this instance has
 * or this profile names, one row each.
 *
 * The three are one table because they are one shape — an id, a state,
 * a switch and a handful of settings whose keys differ per row. What
 * differs is the last column: only signals have a contribution, because
 * only signals emit a ledger row.
 *
 * **A row's settings are chips, and the chip holds the input.** The
 * points map has no fixed schema by design, so a column per key is not
 * available; a form row per key would make the table four screens long.
 * The chip keeps the density and still edits.
 *
 * @var array $block
 * @var bool $editable
 * @var string $sectionId
 * @var array|null $ledger Signal id => contribution, when a value is
 *                         on the bench
 * @var string|null $benchValue
 */
App::uses('AnalystProfileFormTool', 'Tools');

$ledger = isset($ledger) ? $ledger : array();
$benchValue = isset($benchValue) ? $benchValue : null;
$isSignals = $sectionId === 'signals';
$groups = isset($block['groups']) ? $block['groups'] : array();

/*
 * Grouped only where the document groups: a signal declares its ledger
 * group and the ledger is read group by group, so the palette that
 * edits it reads the same way. Escalations and exclusions have no
 * grouping to honour.
 */
$rows = array();
if ($isSignals) {
    foreach ($groups as $group) {
        $rows[$group] = array();
    }
    foreach ($block['items'] as $item) {
        $group = isset($item['group']) && $item['group'] !== null
            ? $item['group']
            : __('Ungrouped');
        if (!isset($rows[$group])) {
            $rows[$group] = array();
        }
        $rows[$group][] = $item;
    }
} else {
    $rows[''] = $block['items'];
}

/*
 * Two chip columns, named for what they actually hold.
 *
 * *Settings* and *What it reads* were each one word too broad: both
 * columns held settings, and the second mixed the `config` map with the
 * data source being read — which is a property of the implementation,
 * not something on the form. The source moved to the name column with
 * the rest of the signal's identity, and each header now names its own
 * map: `points` is what a reading is worth, `config` is every other
 * knob on it.
 *
 * **Not "thresholds"**, though most of them are: `named` sets how many
 * organisations the evidence line lists, and `stale_factor` is a
 * multiplier — so a header promising thresholds would be wrong about
 * the two keys a reader is most likely to be surprised by. *Tuning*
 * is true of all nine. It also keeps the word *thresholds* meaning the
 * section in the rail, which is a different thing at a different scope.
 *
 * Only signals have a `points` map; an escalation's settings are its
 * `when` conditions and an exclusion's are its own schema, so for those
 * the points column was an em-dash on every row and is not drawn.
 */
$pointsHead = __('Points');
if ($sectionId === 'escalations') {
    $settingsHead = __('When it fires');
} elseif ($sectionId === 'exclusions') {
    $settingsHead = __('Settings');
} else {
    $settingsHead = __('Tuning');
}
$columns = $isSignals ? 5 : 3;
?>
<table class="wb-tbl">
    <thead>
        <tr>
            <th style="width:2.2rem"></th>
            <th style="width:<?= $isSignals ? '31%' : '46%' ?>">
                <?= $isSignals ? h(__('Signal')) : h(__('Rule')) ?>
            </th>
            <?php if ($isSignals): ?>
                <th style="width:26%"><?= h($pointsHead) ?></th>
            <?php endif; ?>
            <th style="width:<?= $isSignals ? '21%' : '48%' ?>">
                <?= h($settingsHead) ?>
            </th>
            <?php if ($isSignals): ?>
                <th class="r" style="width:12%">
                    <?= $benchValue === null
                        ? h(__('Contribution'))
                        : h(sprintf(__('On %s'), $benchValue)) ?>
                </th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $group => $items): ?>
        <?php if ($group !== ''): ?>
            <tr class="wb-grp"><td colspan="<?= $columns ?>"><?= h($group) ?></td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <?php
            $state = isset($item['state']) ? $item['state'] : 'active';
            $rowClass = '';
            if ($state === 'missing') {
                $rowClass = 'is-off';
            } elseif (!$item['enabled']) {
                $rowClass = 'is-off';
            }
            $contribution = $isSignals && isset($ledger[$item['id']])
                ? $ledger[$item['id']]
                : null;

            /*
             * The row's own control comes out of the field list, so the
             * checkbox is not drawn twice as a chip. Everything else is
             * a chip, grouped by the map it writes to.
             */
            $switch = null;
            $chips = array();
            $seen = array();
            foreach ($item['fields'] as $field) {
                $address = implode('.', $field['path']);
                if (isset($seen[$address])) {
                    continue;
                }
                $seen[$address] = true;
                if ($field['key'] === 'enabled' && $field['type'] === 'bool') {
                    $switch = $field;
                    continue;
                }
                $map = isset($field['map']) ? $field['map'] : '';
                $chips[$map === 'points' ? 'points' : 'config'][] = $field;
            }
            ?>
            <tr class="<?= h($rowClass) ?>" data-ap-item="<?= h($item['id']) ?>">
                <td>
                    <?php if ($switch !== null): ?>
                        <?= $this->element('AnalystProfiles/field', array(
                            'field' => $switch,
                            'editable' => $editable,
                        )) ?>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="wb-id fw-semibold"><?= h($item['title']) ?></div>
                    <?php foreach ($item['badges'] as $badge): ?>
                        <div class="mt-1">
                            <span class="pill t-missing"
                                  title="<?= h($badge['title']) ?>">
                                <?= h($badge['label']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($state === 'available'): ?>
                        <div class="mt-1">
                            <span class="pill t-plain"
                                  title="<?= h(__('This instance implements it'
                                      . ' and this profile does not name it.'
                                      . ' Enabling it adds it to the'
                                      . ' document.')) ?>">
                                <?= h(__('available')) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($item['emits'])): ?>
                        <div class="wb-sub"><?= h(sprintf(
                            __('Forces the lean to %s when it fires.'),
                            $item['emits']
                        )) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($item['description'])): ?>
                        <div class="wb-sub"><?= h($item['description']) ?></div>
                    <?php endif; ?>
                    <?php
                    /*
                     * What the row *is*, as against what it is set to:
                     * the multiplier its contribution passes through,
                     * and the data it reads. Neither is on the form, so
                     * neither is a chip — tags are square where the
                     * state pills are round, which is the whole reason
                     * they can sit in the same cell without being read
                     * as the same kind of thing.
                     */
                    ?>
                    <?php if (!empty($item['trust_weighted'])
                        || !empty($item['source'])
                        || !empty($item['layer'])): ?>
                        <div class="sig-meta">
                            <?php if (!empty($item['trust_weighted'])): ?>
                                <?php
                                /*
                                 * No `×`. It is the remove glyph three
                                 * times over on this page — the chip
                                 * drop, the map row's button, and the
                                 * one the JS writes for a new chip —
                                 * so a lone leading one beside those
                                 * chips reads as a control that turns
                                 * the weighting off. It works in
                                 * `assessment_head` because *points ×
                                 * polarity* has a second operand;
                                 * here there is none, and *weighted*
                                 * already says the points are scaled.
                                 */
                                ?>
                                <span class="sig-tag is-mult"
                                      title="<?= h(__('Every point this signal'
                                          . ' contributes is multiplied by how'
                                          . ' much the reporting organisation'
                                          . ' is trusted.')) ?>">
                                    <?= h(__('trust weighted')) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($item['source'])): ?>
                                <span class="sig-tag">
                                    <span class="k"><?= h(__('reads')) ?></span>
                                    <b><?= h($item['source']) ?></b>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($item['layer'])): ?>
                                <span class="sig-tag">
                                    <span class="k"><?= h(__('applies at')) ?></span>
                                    <b><?= h($item['layer']) ?></b>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <?php if ($isSignals): ?>
                    <td>
                        <?php if (empty($chips['points'])): ?>
                            <span class="wb-sub">&mdash;</span>
                        <?php endif; ?>
                        <?php foreach ((array)(isset($chips['points'])
                            ? $chips['points'] : array()) as $field): ?>
                            <?= $this->element('AnalystProfiles/chip', array(
                                'field' => $field,
                                'editable' => $editable,
                            )) ?>
                        <?php endforeach; ?>
                    </td>
                <?php endif; ?>
                <td>
                    <?php if (empty($chips['config'])): ?>
                        <span class="wb-sub">&mdash;</span>
                    <?php endif; ?>
                    <?php foreach ((array)(isset($chips['config'])
                        ? $chips['config'] : array()) as $field): ?>
                        <?= $this->element('AnalystProfiles/chip', array(
                            'field' => $field,
                            'editable' => $editable,
                        )) ?>
                    <?php endforeach; ?>
                </td>
                <?php if ($isSignals): ?>
                    <td class="r">
                        <?php if ($state === 'missing'): ?>
                            <span class="wb-sub"><?= h(__('not counted')) ?></span>
                        <?php elseif ($benchValue === null): ?>
                            <span class="wb-sub">&mdash;</span>
                        <?php elseif ($contribution === null): ?>
                            <div class="wb-sub"><?= h(__('no row')) ?></div>
                            <div class="wb-sub"><?= h(__('on this value')) ?></div>
                        <?php else: ?>
                            <div class="num fw-bold"><?= h($contribution) ?></div>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>
