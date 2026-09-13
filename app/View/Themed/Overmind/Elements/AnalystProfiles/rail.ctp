<?php
/**
 * The outline: the whole document on the left, one section of it open
 * on the right.
 *
 * Every section carries the axis it configures, which is the fastest
 * available proof that a profile is not a set of quality weights —
 * six of the seven reach exactly one of the three. The counts are
 * settings, not bytes: what a reader wants to know before clicking is
 * how much is in there.
 *
 * @var array $sections
 * @var string $open
 * @var string $raw The document, for the Raw JSON row's size
 * @var bool $editable
 */
$count = function (array $section) {
    $n = 0;
    foreach ($section['blocks'] as $block) {
        if ($block['kind'] === 'items') {
            $n += count($block['items']);
        } elseif ($block['kind'] === 'fields') {
            $n += count($block['fields']);
        } elseif ($block['kind'] === 'map') {
            foreach ($block['entries'] as $entry) {
                /* A row whose key is a setting too counts as both. */
                $n += empty($entry['key_field']) ? 1 : 2;
            }
        }
    }
    return $n;
};
?>
<nav class="wb-rail" id="ap-rail">
    <div class="wb-rail-title"><?= h(__('Sections')) ?></div>
    <?php foreach ($sections as $id => $section): ?>
        <button type="button"
                class="wb-rail-item <?= $open === $id ? 'is-open' : '' ?>"
                data-sec="<?= h($id) ?>">
            <span>
                <span class="mk" data-ap-dirty-mark="<?= h($id) ?>" hidden
                      title="<?= h(__('this section holds an edit this'
                          . ' session has made and not saved')) ?>">&#8961;</span>
                <?= h($section['title']) ?>
                <span class="wb-rail-ax"><?= h($section['axis']) ?></span>
            </span>
            <span class="c"><?= h($count($section)) ?></span>
        </button>
    <?php endforeach; ?>
    <button type="button" class="wb-rail-item <?= $open === 'raw' ? 'is-open' : '' ?>"
            data-sec="raw">
        <span>
            <?= h(__('Raw JSON')) ?>
            <span class="wb-rail-ax"><?= h(__('all three')) ?></span>
        </span>
        <span class="c"><?= h(sprintf('%.1f KB', strlen($raw) / 1024)) ?></span>
    </button>
    <p class="wb-rail-note">
        <?= h(__('The rail is the whole document; the pane beside it is one'
            . ' section of it. Under each section is the axis it'
            . ' configures.')) ?>
    </p>
</nav>
