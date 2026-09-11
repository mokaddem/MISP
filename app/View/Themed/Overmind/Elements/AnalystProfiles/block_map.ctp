<?php
/**
 * A key→value map: graded organisations, warninglist categories, the
 * TTL buckets and their types, the modules declared per attribute type.
 *
 * **Only the keys the map already carries**, plus a control to add one.
 * §4 of the spec is explicit that an instance with nine hundred
 * organisations must not render nine hundred rows to say something
 * about four of them, and the same restraint applies to every other
 * map here.
 *
 * A map **replaces** on save rather than merging, because a row the
 * analyst removed posts nothing and a merge cannot tell that from a row
 * the form never drew. `__present` is what says the block was on
 * screen; without it an empty map would read as *unchanged*.
 *
 * @var array $block
 * @var bool $editable
 */
App::uses('AnalystProfileFormTool', 'Tools');

/*
 * One picker list per map rather than one per row: the four TTL
 * buckets offer the same 194 attribute types, and four copies of that
 * list is three copies too many.
 */
$datalist = null;
if (($block['value_type'] ?? null) === 'types') {
    $datalist = AnalystProfileFormTool::fieldId($block['path'], array('list'));
}
?>
<div class="ap-map">
<?php if ($datalist !== null): ?>
    <datalist id="<?= h($datalist) ?>">
        <?php
        $offered = array();
        foreach ($block['entries'] as $entry) {
            foreach ($entry['options'] as $option) {
                if (!isset($entry['taken'][$option])) {
                    $offered[$option] = true;
                }
            }
        }
        ?>
        <?php foreach (array_keys($offered) as $option): ?>
            <option value="<?= h($option) ?>"></option>
        <?php endforeach; ?>
    </datalist>
<?php endif; ?>
<?php if (!empty($block['title'])): ?>
    <p class="wb-h mt-3">
        <?= h($block['title']) ?>
        <?php if (!empty($block['axis'])): ?>
            <span class="wb-ax"><?= h($block['axis']) ?></span>
        <?php endif; ?>
    </p>
<?php endif; ?>
<?php if (!empty($block['blurb'])): ?>
    <?php
    $blurb = trim($block['blurb']);
    $rest = '';
    if (preg_match('/^(.+?[.!?])\s+(.*)$/s', $blurb, $m)) {
        $blurb = $m[1];
        $rest = $m[2];
    }
    ?>
    <p class="wb-blurb">
        <?= h($blurb) ?>
        <?php if ($rest !== ''): ?>
            <a href="#" class="wb-i" onclick="return false;"
               title="<?= h($rest) ?>">i</a>
        <?php endif; ?>
    </p>
<?php endif; ?>

<?php if ($editable): ?>
    <input type="hidden"
           name="<?= h(AnalystProfileFormTool::fieldName($block['path'],
               array('__present'))) ?>"
           value="1">
<?php endif; ?>

<?php if (empty($block['entries'])): ?>
    <div class="wb-empty">
        <div class="fw-semibold"><?= h(sprintf(
            __('No %s set'), strtolower($block['value_label'])
        )) ?></div>
        <p class="mb-0 mt-1"><?= h(__('An empty map is not a gap. It means'
            . ' this profile overrides nothing here, and everything'
            . ' takes the behaviour it would have had without the'
            . ' section.')) ?></p>
    </div>
<?php else: ?>
    <table class="wb-tbl">
        <thead>
            <tr>
                <th style="width:34%"><?= h($block['key_label']) ?></th>
                <th><?= h($block['value_label']) ?></th>
                <?php if ($editable && !empty($block['add'])): ?>
                    <th style="width:3rem"></th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($block['entries'] as $entry): ?>
                <?php
                /*
                 * An entry is one field with a key beside it, so the
                 * same renderer draws a grade, a category, a bucket of
                 * types and a module state map.
                 */
                $field = $entry + array('label' => $entry['key']);
                ?>
                <tr class="<?= empty($entry['missing']) ? '' : 'is-off' ?>">
                    <td>
                        <div class="fw-semibold"><?= h($entry['label']) ?></div>
                        <?php if (!empty($entry['sub_label'])): ?>
                            <div class="wb-sub"><?= h($entry['sub_label']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($entry['missing'])): ?>
                            <div class="wb-sub"><?= h(__('Not an'
                                . ' identifier this instance knows. The'
                                . ' entry is kept — it may be somebody'
                                . " else's, and importing a profile is"
                                . ' how it got here.')) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $this->element('AnalystProfiles/field', array(
                            'field' => $field,
                            'editable' => $editable,
                            'datalist' => $datalist,
                        )) ?>
                    </td>
                    <?php
                    /*
                     * A row can be removed only where the key set is
                     * open. The four shelf-life buckets are the
                     * closed one — they are a fixed vocabulary, and a
                     * cross beside `Short` offers to delete something
                     * that cannot be deleted. The presence of an add
                     * control is what says which kind a map is.
                     */
                    ?>
                    <?php if ($editable && !empty($block['add'])): ?>
                        <td class="r">
                            <button type="button" class="wb-drop"
                                    data-ap-drop="<?= h(
                                        AnalystProfileFormTool::fieldId(
                                            $entry['path']
                                        )) ?>"
                                    title="<?= h(__('Remove this row. The'
                                        . ' map is written whole on'
                                        . ' save, so a removed row is'
                                        . ' a removed key.')) ?>">
                                &times;
                            </button>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($editable && !empty($block['add'])): ?>
    <div class="wb-inline mt-2">
        <label class="wb-sub" for="<?= h(AnalystProfileFormTool::fieldId(
            $block['path'], array('add'))) ?>">
            <?= h($block['add']['label']) ?>
        </label>
        <?php if (!empty($block['add']['options'])): ?>
            <select class="form-select form-select-sm" style="max-width:16rem"
                    id="<?= h(AnalystProfileFormTool::fieldId($block['path'],
                        array('add'))) ?>"
                    data-ap-add="<?= h(implode('.', $block['path'])) ?>"
                    data-ap-add-name="<?= h(AnalystProfileFormTool::fieldName(
                        $block['path'])) ?>">
                <option value=""><?= h(__('pick one…')) ?></option>
                <?php foreach ($block['add']['options'] as $option): ?>
                    <option value="<?= h($option) ?>"><?= h($option) ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <input class="form-control form-control-sm" type="search"
                   style="max-width:16rem"
                   id="<?= h(AnalystProfileFormTool::fieldId($block['path'],
                       array('add'))) ?>"
                   data-ap-add="<?= h(implode('.', $block['path'])) ?>"
                   data-ap-add-name="<?= h(AnalystProfileFormTool::fieldName(
                       $block['path'])) ?>"
                   data-ap-add-source="<?= h($block['add']['source']) ?>"
                   placeholder="<?= h(__('search…')) ?>">
        <?php endif; ?>
        <span class="wb-sub"><?= h(__('added rows are saved with the'
            . ' section')) ?></span>
    </div>
<?php endif; ?>
</div>
