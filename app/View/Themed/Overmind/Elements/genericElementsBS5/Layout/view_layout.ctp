<?php
/**
 * What a lazily-loaded card shows before its fetch answers.
 *
 * The default is a centred spinner, which says *something is coming*
 * and nothing else: the container is ~84px tall whatever will land in
 * it, so a tab of them opens as a row of spinners and then grows panel
 * by panel as the fetches return. A caller that knows the shape of what
 * it asked for can pass `placeholder` — an element and its params — and
 * draw that shape instead, so the page has its structure and its
 * section names from the first paint.
 *
 * `Layout/ajax_card_skeleton` beside this file is the ready-made one,
 * and its docblock has the call:
 *
 * ```php
 * $card['placeholder'] = array(
 *     'element' => 'genericElementsBS5/Layout/ajax_card_skeleton',
 *     'params' => array('cards' => array(
 *         array('title' => __('Attributes'), 'icon' => 'fas fa-tag',
 *               'lines' => 8),
 *     )),
 * );
 * ```
 *
 * @param array $card
 * @return void
 */
$ajaxPlaceholder = function (array $card) {
    if (!empty($card['placeholder']['element'])) {
        echo $this->element(
            $card['placeholder']['element'],
            $card['placeholder']['params'] ?? array()
        );
        return;
    }
    echo '<div class="text-center p-4">';
    echo '<div class="spinner-border"></div>';
    echo '</div>';
};

/**
 * One card in a column, in whichever of the three shapes a caller uses.
 *
 * Extracted because both columns had the same body, and because the row
 * group below needs a third caller for it. `$containerClass` is the only
 * thing the two columns ever differed by: the left emits
 * `.ajax-tab-content` and the right rail `.ajax-card`, and
 * `AJAX_CONTAINER_SELECTOR` in mispOvermind.js matches both.
 *
 * @param mixed $card An array with `ajax`, with `element`, or a plain
 *                    element name
 * @param string $containerClass
 * @return void
 */
$renderCard = function ($card, $containerClass) use ($ajaxPlaceholder, $data) {
    if (!is_array($card)) {
        echo $this->element($card, array('data' => $data));
        return;
    }
    if (!empty($card['ajax'])) {
        // Optional `id` gives a lazily-loaded panel an anchor a link can
        // reach before its content has arrived. Absent for every
        // existing caller.
        echo '<div class="' . h($containerClass) . '"'
            . (empty($card['id']) ? '' : ' id="' . h($card['id']) . '"')
            . ' data-url="' . h($card['ajax']) . '">';
        $ajaxPlaceholder($card);
        echo '</div>';
        return;
    }
    if (!empty($card['element'])) {
        // Optional `params` lets one element serve several cards. Absent
        // for every existing caller, so their render is unchanged.
        echo $this->element(
            $card['element'],
            array('data' => $data) + ($card['params'] ?? array())
        );
    }
};

/**
 * A group of cards that sit beside each other instead of stacking.
 *
 * `array('row' => array($cardA, $cardB))` in a column's list, where the
 * default is one card per row at full width. Absent for every existing
 * caller, so nothing that does not ask for it changes.
 *
 * **It is a `col-lg-*` split, so the row is a large-screen arrangement
 * only.** Below the breakpoint the columns stack and the panels are
 * exactly what they were — which is the point: two panels side by side
 * is a claim about horizontal room, and a narrow window does not have
 * any. Bootstrap's own `.row` does the stacking; nothing here is
 * conditional.
 *
 * Cards divide the twelve columns evenly unless one names its own
 * `col`. Two panels of unequal height leave space under the shorter,
 * which is the arrangement's cost and always less than stacking them:
 * a row is as tall as its tallest card rather than as tall as the sum.
 *
 * @param array $group The `row` value
 * @param string $containerClass As $renderCard
 * @return void
 */
$renderRow = function (array $group, $containerClass) use ($renderCard) {
    $cards = array_values(array_filter($group));
    if (empty($cards)) {
        return;
    }
    $span = max(1, (int)floor(12 / count($cards)));
    echo '<div class="row">';
    foreach ($cards as $card) {
        $col = is_array($card) && !empty($card['col'])
            ? $card['col']
            : 'col-lg-' . $span;
        echo '<div class="' . h($col) . '">';
        $renderCard($card, $containerClass);
        echo '</div>';
    }
    echo '</div>';
};

/**
 * A column's whole list, stacking cards and laying out row groups.
 *
 * @param array $cards
 * @param string $containerClass As $renderCard
 * @return void
 */
$renderColumn = function ($cards, $containerClass) use (
    $renderCard, $renderRow
) {
    foreach ((array)$cards as $card) {
        if (is_array($card) && !empty($card['row'])) {
            $renderRow($card['row'], $containerClass);
            continue;
        }
        $renderCard($card, $containerClass);
    }
};

$activeTabIndex = 0;
foreach ($tabs as $i => $tab) {
    if (!empty($tab['active'])) {
        $activeTabIndex = $i;
        break;
    }
}
?>
<div class="container-fluid">
    <ul class="nav nav-tabs mb-3 fs-5" role="tablist">
        <?php foreach ($tabs as $i => $tab): ?>
            <?php $isActive = $i === $activeTabIndex; ?>
            <li class="nav-item"  role="presentation">
                <a class="nav-view nav-link d-flex align-items-center gap-2 bg-light text-dark <?= $isActive ? 'active' : '' ?>"
                    data-bs-toggle="tab"
                    href="#tab-<?= h($tab['id']) ?>"
                    role="tab"
                    aria-selected="<?= $isActive ? 'true' : 'false' ?>">

                    <?php if (!empty($tab['icon'])): ?>
                        <i class="<?= h($tab['icon']) ?>"></i>
                    <?php endif; ?>

                    <?php if (!empty($tab['title'])): ?>
                        <?= h($tab['title']) ?>
                    <?php endif; ?>

                    <?php if (!empty($tab['count'])): ?>
                        <span> (<?= h($tab['count']) ?>) </span>
                    <?php endif; ?>

                    <?php
                    /*
                     * Optional state pill, for a tab whose content resolves to
                     * a state rather than to a count — a verdict, a status, a
                     * severity. `label` is required; `color` is any CSS colour
                     * (a variable is the intent) and `dot` prefixes a filled
                     * circle in it. Absent for every existing caller.
                     */
                    ?>
                    <?php if (!empty($tab['badge']['label'])): ?>
                        <span class="nav-view-badge"<?=
                            empty($tab['badge']['color'])
                                ? ''
                                : ' style="--nav-view-badge-color: '
                                    . h($tab['badge']['color']) . ';"'
                        ?>>
                            <?php if (!empty($tab['badge']['dot'])): ?>
                                <span class="nav-view-badge-dot"></span>
                            <?php endif; ?>
                            <?= h($tab['badge']['label']) ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php foreach ($tabs as $i => $tab): ?>
            <div class="tab-pane fade <?= $i === $activeTabIndex ? 'show active' : '' ?>"
                id="tab-<?= h($tab['id']) ?>"
                role="tabpanel">
                <div class="row">
                    <!-- LEFT COLUMN -->
                    <div class="<?= !empty($tab['right']) ? 'col-lg-9' : 'col-12' ?>">
                        <?php
                            if (!empty($tab['left'])) {
                                $renderColumn($tab['left'], 'ajax-tab-content');
                            }
                        ?>
                    </div>
                    <?php if (!empty($tab['right'])): ?>
                        <!-- RIGHT COLUMN -->
                        <div class="col-lg-3">
                            <?php $renderColumn($tab['right'], 'ajax-card'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function activateTabFromHash() {
    var hash = window.location.hash;
    if (!hash) return;
    var target = document.querySelector('.nav-link[href="' + hash + '"]');
    if (target) bootstrap.Tab.getOrCreateInstance(target).show();
}

// The header strip is rendered once by the page and never re-rendered on tab
// switch, so header actions tagged with data-header-tab are toggled here to
// match the active tab. Actions without the attribute are left untouched.
function syncHeaderActions(tabId) {
    document.querySelectorAll('[data-header-tab]').forEach(function (el) {
        el.classList.toggle('d-none', el.getAttribute('data-header-tab') !== tabId);
    });
}

function currentTabId() {
    var active = document.querySelector('.nav-view.active[href^="#tab-"]');
    return active ? active.getAttribute('href').replace('#tab-', '') : null;
}

// A link to #tab-<id> from inside a panel has to switch tabs, not just move
// the hash. Bootstrap does not watch the hash, so neither did this.
window.addEventListener('hashchange', activateTabFromHash);

document.addEventListener('DOMContentLoaded', function () {
    // Restore active tab from URL hash on load
    activateTabFromHash();

    // Reveal the header actions belonging to the initially active tab
    syncHeaderActions(currentTabId());

    // Keep URL hash + header actions in sync when switching tabs
    document.querySelectorAll('.nav-link[data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (e) {
            var href = e.target.getAttribute('href');
            if (href) {
                history.replaceState(null, '', href);
                syncHeaderActions(href.replace('#tab-', ''));
            }
        });
    });
});

// Activate tab when hash changes without page reload (same-page anchor links)
window.addEventListener('hashchange', function () {
    activateTabFromHash();
    syncHeaderActions(currentTabId());
});
</script>