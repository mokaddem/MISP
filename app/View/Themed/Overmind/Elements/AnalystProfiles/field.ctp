<?php
/**
 * One setting, as whatever control its type asks for.
 *
 * Seven types and no per-section code: every field in the view-model
 * carries its own `path`, which is both the POST name and the address
 * `AnalystProfileFormTool::merge()` writes back to. A section that
 * needed its own renderer would be a section whose fields the merge
 * could not find.
 *
 * `editable` false draws the same field as text. Not a disabled input:
 * a disabled input still looks like something you could have changed,
 * and the read-only page is for a profile that is somebody else's.
 *
 * @var array $field
 * @var bool $editable
 * @var string|null $datalist The id of the picker's option list
 * @var string|null $datalist The id of the picker's option list
 * @var array|null $extra Segments appended to the path, for a field
 *                        inside a map row
 */
App::uses('AnalystProfileFormTool', 'Tools');

$extra = isset($extra) ? $extra : array();
$path = $field['path'];
$name = AnalystProfileFormTool::fieldName($path, $extra);
$id = AnalystProfileFormTool::fieldId($path, $extra);
$type = isset($field['type']) ? $field['type'] : 'string';
$value = isset($field['value']) ? $field['value'] : null;
$default = array_key_exists('default', $field) ? $field['default'] : null;

/*
 * A number that is stored is shown; one that is not shows nothing and
 * the placeholder says what the engine will use instead. Printing the
 * default into the box would store it on the next save and turn a
 * document that follows the shipped default into one that has frozen a
 * copy of it.
 */
$shown = $value === null ? '' : $value;
if (is_bool($shown)) {
    $shown = $shown ? 'true' : 'false';
}
$placeholder = $value === null && $default !== null
    ? (is_bool($default) ? ($default ? 'true' : 'false') : (string)$default)
    : '';

$numeric = $type === 'float' || $type === 'int';
$width = $numeric ? '4.6rem' : '';
$classes = 'form-control form-control-sm';
if ($numeric) {
    $classes .= ' num text-end';
}

/*
 * A box that refuses a word as it is typed. `ValueSignalBase::checkMap()`
 * and `checkScalar()` already refuse one on save, but a setting that
 * looks like a number and takes `soon` is a round trip to learn what
 * the control could have said at the keystroke.
 *
 * **Unless what is stored is not a number.** A `number` input shows
 * nothing for a value it cannot parse, and a box that silently empties
 * itself posts the key away on the next save. Such a value is drawn as
 * text, where it stays visible and correctable.
 */
$asNumber = $numeric && ($shown === '' || is_numeric($shown));
$step = $type === 'int' ? '1' : 'any';

/*
 * How many characters a text box holds, which is the only thing that
 * can size one. `supermajority` in a box built for a weight reads
 * `supermaj`, and a setting you cannot finish reading is a setting you
 * have to click into to check.
 *
 * Only where a width is not already decided: `.form-control` is 100% of
 * its cell in the section panes, so this changes the chips and nothing
 * else. Bounded both ways — a one-character box is not a target, and an
 * over-long stored value must not push the table out.
 *
 * `types` and `module_states` hold arrays and draw their own controls,
 * so they never reach the box this sizes and must not be measured as
 * though they did.
 */
$size = is_scalar($shown)
    ? max(4, min(28, max(
        mb_strlen((string)$shown),
        mb_strlen($placeholder)
    )))
    : 4;
?>
<?php if ($type === 'bool'): ?>
    <?php if ($editable): ?>
        <input type="hidden" name="<?= h($name) ?>" value="false">
        <input class="form-check-input" type="checkbox"
               id="<?= h($id) ?>"
               name="<?= h($name) ?>" value="true"
               data-ap-field="1"
               data-ap-was="<?= !empty($value) ? 'true' : 'false' ?>"
               <?= !empty($value) ? 'checked' : '' ?>>
    <?php else: ?>
        <span class="wb-sub"><?= !empty($value)
            ? h(__('yes'))
            : h(__('no')) ?></span>
    <?php endif; ?>
<?php elseif ($type === 'select'): ?>
    <?php if ($editable): ?>
        <select class="form-select form-select-sm" id="<?= h($id) ?>"
                name="<?= h($name) ?>" data-ap-field="1"
                data-ap-was="<?= h((string)$shown) ?>"
                <?= empty($field['options']) ? 'disabled' : '' ?>>
            <?php foreach ($field['options'] as $option): ?>
                <?php
                $label = is_array($option)
                    ? (isset($option['label']) ? $option['label'] : $option['value'])
                    : $option;
                $optionValue = is_array($option) ? $option['value'] : $option;
                ?>
                <option value="<?= h($optionValue) ?>"
                    <?= (string)$optionValue === (string)$shown
                        ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <span class="wb-id"><?= h((string)$shown) ?></span>
    <?php endif; ?>
<?php elseif ($type === 'types'): ?>
    <?php
    /*
     * The types assigned to this bucket, and nothing else. MISP has
     * 194 attribute types and four buckets: drawing a control per type
     * per bucket is 776 of them, which is both the reason §4 says
     * *never a row per type MISP has* and, measured, 400KB of one
     * page. The picker beside them offers the types no bucket holds.
     *
     * **The row is read bucket-first and written type-first.** The
     * document stores `ttl_types` as type => bucket, and reading it
     * that way would put 194 rows on the page; the pane groups it the
     * other way round, so each chip posts itself back under its own
     * type with this bucket as the value. The block's `__present`
     * marker is what makes a chip taken off the page a key removed
     * from the map.
     */
    $held = is_array($value) ? $value : array();
    $taken = isset($field['taken']) ? $field['taken'] : array();
    $bucket = $field['key'];
    $mapPath = $field['path'];
    $free = array();
    foreach ($field['options'] as $option) {
        if (!isset($taken[$option])) {
            $free[] = $option;
        }
    }
    ?>
    <div class="ttl-chips" data-ap-chips="1">
        <?php foreach ($held as $option): ?>
            <span class="chip is-on">
                <?= h($option) ?>
                <?php if ($editable): ?>
                    <input type="hidden" data-ap-field="1"
                           data-ap-was="<?= h($bucket) ?>"
                           name="<?= h(AnalystProfileFormTool::fieldName(
                               $mapPath, array($option))) ?>"
                           value="<?= h($bucket) ?>">
                    <a href="#" class="ap-chip-drop"
                       aria-label="<?= h(sprintf(__('Remove %s'), $option)) ?>"
                       >&times;</a>
                <?php endif; ?>
            </span>
        <?php endforeach; ?>
        <?php if (empty($held)): ?>
            <span class="wb-sub"><?= h(__('nothing assigned')) ?></span>
        <?php endif; ?>
        <?php if ($editable): ?>
            <input class="chip-add" type="text"
                   list="<?= h(isset($datalist) ? $datalist : '') ?>"
                   data-ap-type-add="<?= h(
                       AnalystProfileFormTool::fieldName($mapPath)) ?>"
                   data-ap-type-value="<?= h($bucket) ?>"
                   placeholder="<?= h(sprintf(__n(
                       '+ add a type (%s free)',
                       '+ add types (%s free)',
                       count($free)
                   ), count($free))) ?>">
        <?php endif; ?>
    </div>
<?php elseif ($type === 'module_states'): ?>
    <?php
    /*
     * Three states per module, so a checklist will not do (D17): a
     * module can be ticked, declared never, or left to the default.
     * `auto` is offered only where it is built — the schema carries it
     * and the run path does not, and an editor that offered it anyway
     * would be promising a behaviour nothing implements.
     */
    $states = is_array($value) ? $value : array();
    $built = isset($field['states_built'])
        ? $field['states_built']
        : $field['state_options'];
    $unavailable = isset($field['unavailable']) ? $field['unavailable'] : array();
    ?>
    <div class="mod-states">
        <?php if ($editable): ?>
            <input type="hidden"
                   name="<?= h(AnalystProfileFormTool::fieldName($path,
                       array_merge($extra, array('__present')))) ?>"
                   value="1">
        <?php endif; ?>
        <?php foreach ($field['options'] as $module): ?>
            <?php
            $state = isset($states[$module]) ? $states[$module] : '';
            $gone = in_array($module, $unavailable, true);
            ?>
            <div class="mod-row <?= $gone ? 'is-off' : '' ?>">
                <span class="wb-id"><?= h($module) ?></span>
                <?php if ($gone): ?>
                    <span class="pill t-missing"><?= h(__('not enabled here')) ?></span>
                <?php endif; ?>
                <?php if ($editable): ?>
                    <select class="form-select form-select-sm"
                            data-ap-field="1"
                            data-ap-was="<?= h($state) ?>"
                            name="<?= h(AnalystProfileFormTool::fieldName(
                                $path,
                                array_merge($extra, array($module))
                            )) ?>">
                        <option value=""><?= h(__('not declared')) ?></option>
                        <?php foreach ($field['state_options'] as $option): ?>
                            <option value="<?= h($option) ?>"
                                <?= $state === $option ? 'selected' : '' ?>
                                <?= in_array($option, $built, true)
                                    ? '' : 'data-ap-inert="1"' ?>>
                                <?= h($option) ?><?= in_array($option, $built, true)
                                    ? '' : ' — ' . h(__('declared, not run')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <span class="wb-sub"><?= $state === ''
                        ? h(__('not declared'))
                        : h($state) ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <?php if ($editable): ?>
        <input class="<?= h($classes) ?>"
               type="<?= $asNumber ? 'number' : 'text' ?>"
               <?= $asNumber ? 'step="' . h($step) . '"'
                   : 'size="' . (int)$size . '"' ?>
               id="<?= h($id) ?>"
               name="<?= h($name) ?>"
               value="<?= h((string)$shown) ?>"
               data-ap-field="1"
               data-ap-was="<?= h((string)$shown) ?>"
               <?= $placeholder === ''
                   ? ''
                   : 'placeholder="' . h($placeholder) . '"' ?>
               <?= $width === ''
                   ? ''
                   : 'style="max-width:' . h($width) . '"' ?>>
    <?php else: ?>
        <span class="num"><?= $shown === ''
            ? '<span class="wb-sub">' . h(__('not set')) . '</span>'
            : h((string)$shown) ?></span>
    <?php endif; ?>
<?php endif; ?>
