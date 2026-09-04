<?php
/**
 * The chrome of the panels an endpoint is about to deliver, drawn
 * before it answers.
 *
 * **Why the page draws them at all.** Every panel here is lazily
 * loaded, and the container each one lands in used to hold nothing but
 * a centred spinner. So a tab opened as one row of spinners, roughly
 * 60px each, and then grew to its real height panel by panel as the
 * fetches came back — a reader could not tell which section was which,
 * could not reach the one they came for, and watched the page move
 * under the cursor for as long as the slowest endpoint took.
 *
 * The card chrome is real markup, so the structure is there from the
 * first paint: named panels, in their order, each saying that its own
 * contents are on the way. `value_panel_placeholder` makes the same
 * move for a panel that is not written yet; this one is for a panel
 * that is merely not here yet.
 *
 * **A skeleton names only what the endpoint will certainly render.**
 * The Verdict rail is four cards or five depending on the disposition,
 * and each one's title with it — a title guessed wrong is worse than no
 * title, so those entries pass `title => null` and get a shimmer where
 * the words will be. Only the rail's lighter chrome takes a null:
 * `value_panel_header` escapes and prints the title it is given, and
 * teaching it to hold markup for one caller would change the element
 * twenty-odd panels share.
 *
 * @var array $panels One descriptor per card:
 *                    `title`  string, or null on an `aside`
 *                    `icon`   Font Awesome class or misp-icon triplet
 *                    `color`  CSS colour for the glyph tile
 *                    `lines`  how many body lines to shimmer, default 3
 *                    `shape`  `panel` (default) or `aside`, the lighter
 *                             chrome the rail cards wear
 *                    `col`    Bootstrap lg width; when any entry
 *                             carries one the set is laid out as a row,
 *                             which is what the endpoints that own
 *                             their own split need
 */
$panels = $panels ?? array();

/*
 * Ragged on purpose, and deterministic. A stack of equal bars reads as
 * a table someone drew; uneven lengths read as text that has not
 * arrived. Cycled from a fixed list rather than randomised so two
 * renders of one page are the same page.
 */
$widths = array(88, 71, 94, 62, 83, 76);

$split = false;
foreach ($panels as $panel) {
    if (!empty($panel['col'])) {
        $split = true;
        break;
    }
}

/**
 * One card's worth of chrome.
 *
 * @param array $panel
 * @param array $widths
 * @return void
 */
$card = function (array $panel) use ($widths) {
    $title = isset($panel['title']) ? $panel['title'] : null;
    $icon = isset($panel['icon']) ? $panel['icon'] : null;
    $color = isset($panel['color'])
        ? $panel['color']
        : 'var(--bs-secondary-color)';
    $lines = isset($panel['lines']) ? (int)$panel['lines'] : 3;
    $aside = isset($panel['shape']) && $panel['shape'] === 'aside';
    $isMispGlyph = $icon !== null && strpos($icon, 'misp-icon') === 0;
    ?>
    <div class="card shadow-sm mb-3 vp-panel vp-panel-await<?=
             $aside ? ' vp-aside' : '' ?>"
         style="--vp-panel-color: <?= h($color) ?>;"
         aria-busy="true">

        <?php if ($aside): ?>
            <div class="vp-aside-head">
                <?php if ($icon === null): ?>
                    <span class="vp-await-bar vp-await-glyph"></span>
                <?php elseif ($isMispGlyph): ?>
                    <span class="<?= h($icon) ?>"
                          style="color: <?= h($color) ?>;"></span>
                <?php else: ?>
                    <i class="<?= h($icon) ?>"
                       style="color: <?= h($color) ?>;"></i>
                <?php endif; ?>
                <?php if ($title === null): ?>
                    <span class="vp-aside-title">
                        <span class="vp-await-bar"
                              style="width: 7rem;"></span>
                    </span>
                <?php else: ?>
                    <span class="vp-aside-title"><?= h($title) ?></span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php
            /*
             * The same header element the real panel wears, so the
             * glyph tile, the title baseline and the 36px it all sits
             * on do not move when the fetch lands. The subtitle carries
             * the panel's headline number, which nothing here knows —
             * a shimmer holds the line instead of a word repeated down
             * the page.
             */
            $sub = '<span class="vp-await-bar" style="width: 11rem;">'
                . '</span>';
            ?>
            <?= $this->element('Values/View/value_panel_header', array(
                'panelTitle' => (string)$title,
                'panelIcon' => $icon,
                'panelColor' => $color,
                'panelSub' => $sub,
            )) ?>
        <?php endif; ?>

        <div class="vp-await-body">
            <span class="visually-hidden"><?= __('Loading') ?></span>
            <?php for ($i = 0; $i < $lines; $i++): ?>
                <span class="vp-await-bar"
                      style="width: <?= (int)$widths[$i % count($widths)]
                          ?>%;"></span>
            <?php endfor; ?>
        </div>
    </div>
    <?php
};
?>
<?php if ($split): ?>
    <div class="row">
        <?php foreach ($panels as $panel): ?>
            <div class="col-lg-<?= (int)($panel['col'] ?? 12) ?>">
                <?php $card($panel); ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <?php foreach ($panels as $panel): ?>
        <?php $card($panel); ?>
    <?php endforeach; ?>
<?php endif; ?>
