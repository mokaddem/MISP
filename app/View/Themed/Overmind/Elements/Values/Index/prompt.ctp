<?php
/**
 * The prompt — one box, and the verb for what pressing it does.
 *
 * Direction A's, taken into C at the pick (`02a-contract.md` §12.3):
 * the caret at the left edge, the verb, and the `Enter` hint. **No
 * `n/100` counter yet.** The counter is the triage cap mirrored
 * client-side, and until phase 4 there is no cap to mirror — a box
 * that reads *1/100* while the only thing it can do is open one
 * profile would be promising the worklist a phase early.
 *
 * The form is built through `FormHelper` rather than by hand because
 * `SecurityComponent` hashes the fields it emits: a raw `<textarea>`
 * with the right name posts to a blackhole.
 *
 * The textarea refills itself from `$this->request->data`, which is
 * the whole of *the box keeps its content* (§8 G11) — a reader fixing
 * one character does not re-type the value.
 */
echo $this->Form->create('Value', array(
    'class' => 'vi-prompt',
    'id' => 'vi-prompt',
    'url' => $this->Html->url(array(
        'controller' => 'values',
        'action' => 'resolve',
    )),
));
?>
    <div class="vi-prompt__caret" aria-hidden="true">&#9656;</div>
    <div class="vi-prompt__field">
        <?= $this->Form->textarea('value', array(
            'class' => 'vi-ta',
            'rows' => 2,
            'autofocus' => true,
            'spellcheck' => 'false',
            'autocomplete' => 'off',
            'autocapitalize' => 'off',
            'data-vi-box' => '1',
            'placeholder' => __('Paste a value, then press Enter'),
            'aria-label' => __('A value to resolve'),
        )) ?>
    </div>
    <div class="vi-prompt__side">
        <button type="submit" class="vi-run" data-vi-go="1">
            <?= h(__('Open profile')) ?>
            <span class="vi-kbd"><?= h(__('Enter')) ?></span>
        </button>
    </div>
<?= $this->Form->end() ?>
