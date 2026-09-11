<?php
/**
 * A labelled group of settings.
 *
 * One sentence visible per field and the rest behind an `i`: the pane
 * is where somebody changes a number, and a paragraph above every box
 * is a paragraph nobody reads twice.
 *
 * @var array $block
 * @var bool $editable
 */
?>
<?php if (!empty($block['title'])): ?>
    <p class="wb-h mt-3">
        <?= h($block['title']) ?>
        <?php if (!empty($block['axis'])): ?>
            <span class="wb-ax"><?= h($block['axis']) ?></span>
        <?php endif; ?>
    </p>
<?php endif; ?>
<?php if (!empty($block['blurb'])): ?>
    <p class="wb-blurb"><?= h($block['blurb']) ?></p>
<?php endif; ?>

<div class="wb-fields">
    <?php foreach ($block['fields'] as $field): ?>
        <?php
        /*
         * `help` is split at the first sentence: the rest is a tooltip.
         * Splitting here rather than in the tool keeps the whole
         * sentence in the API's answer, where a reader has room for it.
         */
        $help = isset($field['help']) ? trim($field['help']) : '';
        $rest = '';
        if ($help !== '' && preg_match('/^(.+?[.!?])\s+(.*)$/s', $help, $m)) {
            $help = $m[1];
            $rest = $m[2];
        }
        ?>
        <div class="wb-field">
            <label for="<?= h(AnalystProfileFormTool::fieldId($field['path'])) ?>">
                <?= h($field['label']) ?>
            </label>
            <div class="wb-inline">
                <?= $this->element('AnalystProfiles/field', array(
                    'field' => $field,
                    'editable' => $editable,
                )) ?>
                <?php if (!empty($field['unit'])): ?>
                    <span class="wb-unit"><?= h($field['unit']) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($help !== ''): ?>
                <p class="help">
                    <?= h($help) ?>
                    <?php if ($rest !== ''): ?>
                        <a href="#" class="wb-i" onclick="return false;"
                           title="<?= h($rest) ?>">i</a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
