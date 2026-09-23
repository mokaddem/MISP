<?php
/**
 * The prompt — one box, and the verb for what pressing it does.
 *
 * Direction A's, taken into C at the pick (`02a-contract.md` §12.3):
 * the live `n/100` counter, the verb that says what pressing will do,
 * and the `Enter` hint.
 *
 * The counter and the verb are rendered at their empty-box values and
 * moved by `value-index.js` from there. A box that comes back filled
 * after a submission is the case that makes this matter: the server
 * renders `0/100` and *Open profile*, and the script corrects both
 * before the reader can read either, because it runs on
 * `DOMContentLoaded` against the value already in the field.
 *
 * The form is built through `FormHelper` rather than by hand because
 * `SecurityComponent` hashes the fields it emits: a raw `<textarea>`
 * with the right name posts to a blackhole.
 *
 * The textarea refills itself from `$this->request->data`, which is
 * the whole of *the box keeps its content* (§8 G11) — a reader fixing
 * one character does not re-type the value.
 */
App::uses('ValueInputTool', 'Tools/ValueProfile');

echo $this->Form->create('Value', array(
    'class' => 'vi-prompt',
    'id' => 'vi-prompt',
    'url' => $this->Html->url(array(
        'controller' => 'values',
        'action' => 'resolve',
    )),
    /*
     * The second destination, on the form rather than in the script:
     * a URL built by `Html->url()` is right under a subdirectory
     * install and a string in a `.js` file is not.
     */
    'data-vi-triage' => $this->Html->url(array(
        'controller' => 'values',
        'action' => 'triage',
    )),
));
?>
    <div class="vi-prompt__field">
        <?= $this->Form->textarea('value', array(
            'class' => 'form-control vi-ta',
            'rows' => 3,
            'autofocus' => true,
            'spellcheck' => 'false',
            'autocomplete' => 'off',
            'autocapitalize' => 'off',
            'data-vi-box' => '1',
            'placeholder' => __('Paste a value, or a list of values'),
            'aria-label' => __('Values to look up'),
        )) ?>
    </div>
    <div class="vi-prompt__side">
        <span class="vi-count" data-vi-count
              data-vi-cap="<?= h(ValueInputTool::CAP) ?>"
              aria-live="polite"><b>0</b>/<?= h(ValueInputTool::CAP) ?></span>
        <button type="submit" class="btn btn-primary vi-run" data-vi-go="1">
            <?php
            /*
             * Both wordings travel with the markup rather than living
             * in the script, so the verb is translated by the same
             * catalogue as everything else on the page.
             */
            ?>
            <span data-vi-verb
                  data-vi-one="<?= h(__('Open profile')) ?>"
                  data-vi-many="<?= h(__('Look up %d values')) ?>"
                  ><?= h(__('Open profile')) ?></span>
            <span class="vi-kbd"><?= h(__('Enter')) ?></span>
        </button>
    </div>
<?= $this->Form->end() ?>
