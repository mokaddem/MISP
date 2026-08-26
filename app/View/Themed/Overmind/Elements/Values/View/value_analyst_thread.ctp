<?php
/**
 * The notes and opinions on this value, in the order they were written.
 *
 * One chronological thread rather than two lists, because a reply has
 * to sit next to what it replies to: MISP lets a note carry a note and
 * an opinion carry an opinion two levels deep, and a feed grouped by
 * kind turns that conversation into unrelated fragments.
 *
 * Three things this panel refuses to fake:
 *
 * - **What an item hangs off.** Analyst data attaches to an
 *   `object_uuid` and an `object_type`, never to a value. Every item
 *   carries a chip naming its real target, and an event-level item says
 *   that it is inherited by every occurrence in that event rather than
 *   said about this value.
 * - **What an opinion rates.** An opinion written on a note rates the
 *   note. It takes no side on the value and is excluded from the
 *   standing panel's aggregate, and it says so inline.
 * - **Where MISP stops.** `fetchChildNotesAndOpinions` is called at
 *   depth 2. The second level renders the limit rather than truncating
 *   silently.
 *
 * Markdown is rendered here. MISP stores it and renders none of it
 * today — `Analyst_data/thread.ctp` prints notes `pre-line` with no
 * parser — so this is a decision the tab is making, and one that would
 * apply to every note on the instance because there is no per-note
 * markup flag (`05-analyst.md` §11).
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewAnalystThread.
 *
 * @var array $valueProfile
 */
$analyst = $valueProfile['analyst'];
$thread = $analyst['thread'];
$counts = $analyst['counts'];
$stats = $valueProfile['occurrence_stats'];

$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

/**
 * MISP's distribution levels, as the words the rest of the product
 * uses for them.
 *
 * @param int $level
 * @param string|null $sharingGroup
 * @return array label, icon
 */
$distribution = function ($level, $sharingGroup) {
    $levels = array(
        0 => array(__('Your organisation only'), 'fas fa-lock'),
        1 => array(__('This community only'), 'fas fa-share-nodes'),
        2 => array(__('Connected communities'), 'fas fa-share-nodes'),
        3 => array(__('All communities'), 'fas fa-share-nodes'),
    );
    if ((int)$level === 4) {
        return array(
            $sharingGroup === null ? __('Sharing group') : $sharingGroup,
            'misp-icon misp-icon-sharing-group misp-simple',
        );
    }
    return isset($levels[(int)$level])
        ? $levels[(int)$level]
        : array(__('Inherit event'), 'fas fa-share-nodes');
};

/**
 * A very small markdown subset: headings, bullets, block quotes,
 * paragraphs, inline code and bold.
 *
 * Escaped first and marked up afterwards, so a note that contains
 * `<script>` is a note that contains the characters `<script>`. That
 * ordering is the whole safety argument — there is no sanitiser here
 * and none is needed, because no author-supplied byte ever reaches the
 * output unescaped.
 *
 * @param string $text
 * @return string
 */
$markdown = function ($text) {
    $inline = function ($line) {
        $line = h($line);
        $line = preg_replace('/`([^`]+)`/', '<code>$1</code>', $line);
        $line = preg_replace(
            '/\*\*([^*]+)\*\*/',
            '<strong>$1</strong>',
            $line
        );
        return $line;
    };

    $out = '';
    $para = array();
    $list = array();
    $quote = array();

    $flush = function () use (&$out, &$para, &$list, &$quote, $inline) {
        if (!empty($list)) {
            $out .= '<ul>';
            foreach ($list as $item) {
                $out .= '<li>' . $inline($item) . '</li>';
            }
            $out .= '</ul>';
            $list = array();
        }
        if (!empty($quote)) {
            $out .= '<blockquote>' . $inline(implode(' ', $quote))
                . '</blockquote>';
            $quote = array();
        }
        if (!empty($para)) {
            $out .= '<p class="mb-0">' . $inline(implode(' ', $para))
                . '</p>';
            $para = array();
        }
    };

    foreach (preg_split('/\R/', trim($text)) as $line) {
        $line = rtrim($line);
        if ($line === '') {
            $flush();
            continue;
        }
        if (preg_match('/^#{1,6}\s+(.*)$/', $line, $m)) {
            $flush();
            $out .= '<h4>' . $inline($m[1]) . '</h4>';
            continue;
        }
        if (preg_match('/^[-*]\s+(.*)$/', $line, $m)) {
            if (!empty($para) || !empty($quote)) {
                $flush();
            }
            $list[] = $m[1];
            continue;
        }
        if (preg_match('/^>\s?(.*)$/', $line, $m)) {
            if (!empty($para) || !empty($list)) {
                $flush();
            }
            $quote[] = $m[1];
            continue;
        }
        if (!empty($list) || !empty($quote)) {
            $flush();
        }
        $para[] = $line;
    }
    $flush();

    return $out;
};

/**
 * Whether a body actually uses any of the markup the renderer knows
 * about. The chip is a statement about this note, so a plain sentence
 * does not get to claim it was rendered from markdown.
 *
 * @param string $text
 * @return bool
 */
$isMarkdown = function ($text) {
    return (bool)preg_match('/(^|\R)\s*(#{1,6}\s|[-*]\s|>\s)|`|\*\*/', $text);
};

/**
 * The chip naming what an item hangs off.
 *
 * @param array $target
 * @return string
 */
$attachChip = function ($target) {
    $event = isset($target['event'])
        ? '#' . $target['event']
        : __('an event');

    if ($target['kind'] === 'event') {
        return '<span class="vpa-chip" title="'
            . h(__(
                'Attached to the event, so it is inherited by every'
                . ' occurrence in it'
            )) . '">'
            . '<span class="misp-icon misp-icon-event misp-simple"></span>'
            . h($event)
            . '<span class="vpa-chip-sep">' . h(__('inherited'))
            . '</span></span>';
    }

    if ($target['kind'] === 'object') {
        return '<span class="vpa-chip" title="'
            . h(__('Attached to the object the occurrence sits in'))
            . '">'
            . '<span class="misp-icon misp-icon-object misp-simple"></span>'
            . h($target['name'])
            . '<span class="vpa-chip-sep">' . h(__('in')) . '</span>'
            . '<span class="misp-icon misp-icon-event misp-simple"></span>'
            . h($event)
            . '</span>';
    }

    if ($target['kind'] === 'note') {
        return '<span class="vpa-chip" title="'
            . h(__(
                'Attached to the note above, which is what makes it an'
                . ' opinion about that note rather than about the value'
            )) . '">'
            . '<span class="misp-icon misp-icon-analyst-note'
            . ' misp-simple"></span>'
            . h(__('the note above'))
            . '</span>';
    }

    return '<span class="vpa-chip" title="'
        . h(__(
            'Analyst data attaches to an object_uuid, never to a value —'
            . ' this item hangs off one attribute occurrence'
        )) . '">'
        . '<span class="misp-icon misp-icon-attribute misp-simple"></span>'
        . h($target['type'])
        . '<span class="vpa-chip-sep">' . h(__('in')) . '</span>'
        . '<span class="misp-icon misp-icon-event misp-simple"></span>'
        . h($event)
        . '</span>';
};

$readsBadge = function ($reads) {
    if ($reads === 'malicious') {
        return 'success';
    }
    if ($reads === 'benign') {
        return 'danger';
    }
    return 'secondary';
};

/*
 * One item and everything written under it. Recursive, because the
 * shape is: MISP returns two levels and the template renders exactly
 * what it was given rather than flattening it into a list with an
 * indent column.
 */
$renderItem = function ($item, $depth) use (
    &$renderItem,
    $markdown,
    $isMarkdown,
    $attachChip,
    $distribution,
    $readsBadge
) {
    $isOpinion = $item['kind'] === 'opinion';
    $ratesNote = $item['rates'] !== 'value';
    list($distLabel, $distIcon) = $distribution(
        $item['distribution'],
        $item['sharing_group']
    );
    $side = 'vpa-side-none';
    if ($item['reads'] === 'malicious') {
        $side = 'vpa-side-agree';
    } elseif ($item['reads'] === 'benign') {
        $side = 'vpa-side-disagree';
    }

    $out = '<div class="vp-analyst vp-analyst-'
        . ($isOpinion ? 'opinion' : 'note') . ' ' . $side . '">';

    $out .= '<div class="vp-analyst-kind">'
        . '<span class="misp-icon misp-icon-analyst-'
        . ($isOpinion ? 'opinion' : 'note') . ' misp-simple"></span>'
        . h($isOpinion ? __('Opinion') : __('Note'))
        . '</div>';

    $out .= '<div class="vp-analyst-body">';

    $out .= '<div class="d-flex align-items-center gap-2 mb-1 flex-wrap">';
    if ($isOpinion) {
        $colour = $ratesNote ? 'secondary' : $readsBadge($item['reads']);
        $out .= '<span class="badge bg-' . $colour . '-subtle text-'
            . $colour . '-emphasis border border-' . $colour
            . '-subtle fw-semibold">' . h($item['label'])
            . ' &middot; ' . h($item['score']) . '/100</span>';
    } elseif ($isMarkdown($item['body'])) {
        $out .= '<span class="vpa-chip" title="'
            . h(__(
                'MISP stores this body as written and renders none of'
                . ' it today. This page renders it.'
            )) . '"><i class="fas fa-align-left me-1"></i>'
            . h(__('rendered from markdown')) . '</span>';
    }
    if ($ratesNote) {
        $out .= '<span class="vpa-notcounted">'
            . '<i class="fas fa-circle-info"></i>'
            . h(__(
                'about the note above, not about the value — not in the'
                . ' aggregate'
            )) . '</span>';
    }
    $out .= '<span class="ms-auto">' . $attachChip($item['attached_to'])
        . '</span>';
    $out .= '</div>';

    $out .= '<div class="vp-analyst-text vpa-md">'
        . $markdown($item['body']) . '</div>';

    $out .= '<div class="vp-analyst-meta d-flex align-items-center'
        . ' flex-wrap gap-2"><span>'
        . '<span class="misp-icon misp-icon-organisation misp-simple'
        . ' me-1"></span>' . h($item['org'])
        . ' &nbsp;&middot;&nbsp; <i class="fas fa-user me-1"></i>'
        . h($item['author'])
        . ' &nbsp;&middot;&nbsp; <i class="fas fa-clock me-1"></i>'
        . h($item['date']) . '</span>'
        . '<span class="badge bg-body-tertiary text-body-secondary border'
        . ' fw-normal" title="'
        . h(sprintf(__('distribution %s'), $item['distribution'])) . '">'
        . ($distIcon === 'fas fa-lock' || $distIcon === 'fas fa-share-nodes'
            ? '<i class="' . $distIcon . ' me-1"></i>'
            : '<span class="' . $distIcon . ' me-1"></span>')
        . h($distLabel) . '</span>';
    if (!empty($item['language'])) {
        $out .= '<span class="badge bg-body-tertiary text-body-secondary'
            . ' border fw-normal" title="'
            . h(__(
                'the language column, a natural-language code — not a'
                . ' markup flag'
            )) . '">' . h($item['language']) . '</span>';
    }
    if (!empty($item['children'])) {
        $out .= '<button type="button" class="vpa-replybtn"'
            . ' data-vp-a-replies aria-expanded="true">'
            . '<i class="fas fa-caret-down"></i>'
            . h(__n(
                '%s reply',
                '%s replies',
                count($item['children']),
                count($item['children'])
            )) . '</button>';
    }
    $out .= '</div>';

    $out .= '</div></div>';

    if (!empty($item['children'])) {
        $out .= '<div class="vpa-replies">';
        foreach ($item['children'] as $child) {
            $out .= '<div class="vpa-reply'
                . ($depth >= 1 ? ' vpa-reply-2' : '') . '">'
                . $renderItem($child, $depth + 1) . '</div>';
        }
        $out .= '</div>';
    }

    if (!empty($item['max_depth_reached'])) {
        $out .= '<div class="vpa-depth"><i class="fas fa-ellipsis"></i>'
            . h(__(
                'Two levels is what MISP returns. Anything written below'
                . ' this one is flagged but not fetched.'
            )) . '</div>';
    }

    return $out;
};

/*
 * Nothing written is a state, not four zeroes. A sub-line reading
 * `0 items · 0 opinions, 0 notes · 0 replies · newest first` states
 * the ordering of an empty list, which is the kind of sentence that
 * makes a working page look broken.
 */
$subtitle = empty($thread) ? h(__('Nothing written on this value')) : implode(' &nbsp;·&nbsp; ', array(
    h(sprintf(
        __('%s on this value'),
        __n('%s item', '%s items', $counts['items'], $counts['items'])
    )),
    h(sprintf(
        __('%1$s, %2$s'),
        __n(
            '%s opinion',
            '%s opinions',
            $counts['opinions'],
            $counts['opinions']
        ),
        __n('%s note', '%s notes', $counts['notes'], $counts['notes'])
    )),
    h(sprintf(
        __('%s written on them'),
        __n(
            '%s reply',
            '%s replies',
            $counts['replies'],
            $counts['replies']
        )
    )),
    h(__('newest first')),
));

$sorts = array(
    'newest' => __('Newest'),
    'oldest' => __('Oldest'),
    'org' => __('By organisation'),
);
$pills = array(
    'all' => sprintf(__('All %s'), $counts['items']),
    'note' => sprintf(__('Notes %s'), $counts['notes']),
    'opinion' => sprintf(__('Opinions %s'), $counts['opinions']),
);

$headerExtra = '';
if (!empty($thread)) {
    $headerExtra .= '<div class="btn-group btn-group-sm" role="group"'
        . ' aria-label="' . h(__('Order')) . '">';
    foreach ($sorts as $key => $label) {
        $headerExtra .= '<button type="button" class="btn'
            . ' btn-outline-secondary text-nowrap'
            . ($key === 'newest' ? ' active' : '') . '"'
            . ' data-vp-a-sort="' . h($key) . '"'
            . ' aria-pressed="' . ($key === 'newest' ? 'true' : 'false')
            . '">' . h($label) . '</button>';
    }
    $headerExtra .= '</div>';

    $headerExtra .= '<div class="btn-group btn-group-sm" role="group"'
        . ' aria-label="' . h(__('Kind')) . '">';
    foreach ($pills as $key => $label) {
        $headerExtra .= '<button type="button" class="btn'
            . ' btn-outline-secondary text-nowrap'
            . ($key === 'all' ? ' active' : '') . '"'
            . ' data-vp-a-kind-filter="' . h($key) . '"'
            . ' aria-pressed="' . ($key === 'all' ? 'true' : 'false')
            . '">' . h($label) . '</button>';
    }
    $headerExtra .= '</div>';
}
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--analystData);"
     data-vp-analyst-thread>

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Notes and opinions'),
        'panelIcon' => 'misp-icon misp-icon-analyst-note misp-simple',
        'panelColor' => 'var(--analystData)',
        'panelSub' => $subtitle,
        'panelExtra' => $headerExtra === '' ? null : $headerExtra,
    )) ?>

    <?php if (empty($thread)): ?>
        <div class="vp-empty">
            <span class="misp-icon misp-icon-analyst-note misp-simple"></span>
            <span><?=
                __('Nobody has written a note or an opinion on this value.')
            ?></span>
        </div>
    <?php else: ?>
        <div class="p-3">
            <div class="vpa-thread">
                <?php foreach ($thread as $i => $item): ?>
                    <div class="vpa-item"
                         data-vp-a-item
                         data-vp-a-kind="<?= h($item['kind']) ?>"
                         data-vp-a-org="<?= h($item['org']) ?>"
                         data-vp-a-date="<?= h($item['date']) ?>"
                         data-vp-a-order="<?= $i ?>">
                        <?= $renderItem($item, 0) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="vp-empty-inline d-none" data-vp-a-empty>
                <i class="fas fa-filter"></i>
                <span><?= __('No item of that kind on this value.') ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php
    /*
     * The composer, drawn and disabled.
     *
     * The `Attach to` picker is drawn rather than implied because it is
     * the one control this tab cannot ship without: analyst data has no
     * value-level target, so writing a note from a value page means
     * naming which occurrence, object or event it hangs off. A composer
     * with nowhere to put what it writes is the thing that would have
     * to be redesigned rather than enabled.
     *
     * The picker offers what the viewer can see, not what exists. An
     * occurrence hidden by distribution is not a target this user can
     * attach anything to.
     */
    ?>
    <div class="p-3 border-top">
        <div class="vpa-composer">
            <div class="vpa-composer-head">
                <ul class="nav nav-pills vpa-kindswitch" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active disabled" href="#"
                           aria-disabled="true" title="<?= h($noWrites) ?>">
                            <span class="misp-icon misp-icon-analyst-note
                                         misp-simple me-1"></span>
                            <?= __('Note') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" href="#"
                           aria-disabled="true" title="<?= h($noWrites) ?>">
                            <span class="misp-icon misp-icon-analyst-opinion
                                         misp-simple me-1"></span>
                            <?= __('Opinion') ?>
                        </a>
                    </li>
                </ul>
                <span class="badge bg-warning-subtle text-warning-emphasis
                             border border-warning-subtle fw-semibold ms-auto">
                    <i class="fas fa-ban me-1"></i>
                    <?= __('Disabled in this pass') ?>
                </span>
            </div>
            <div class="row g-3">
                <div class="col-lg-7">
                    <textarea class="form-control" rows="3" disabled
                              title="<?= h($noWrites) ?>"
                              placeholder="<?= h(__(
                                  'Write a note. Markdown is rendered.'
                              )) ?>"></textarea>
                    <div class="form-text"><?= __(
                        'Markdown is rendered on save. MISP stores no'
                        . ' per-note markup flag, so this would apply to'
                        . ' every note the tab shows.'
                    ) ?></div>
                </div>
                <div class="col-lg-5">
                    <label class="form-label small fw-semibold mb-1"><?=
                        __('Attach to')
                    ?></label>
                    <select class="form-select form-select-sm" disabled
                            title="<?= h($noWrites) ?>">
                        <option><?= h($stats['shown'] > 0
                            ? sprintf(
                                __('Choose one of the %s of this value'),
                                __n(
                                    '%s occurrence you can see',
                                    '%s occurrences you can see',
                                    $stats['shown'],
                                    $stats['shown']
                                )
                            )
                            : __('No occurrence of this value to attach to')
                        ) ?></option>
                    </select>
                    <div class="form-text mb-2"><?= __(
                        'Analyst data attaches to an attribute, event or'
                        . ' object UUID. There is no value-level target,'
                        . ' so the composer has to name one.'
                    ) ?></div>
                    <div class="d-flex gap-2 align-items-end">
                        <div class="flex-grow-1">
                            <label class="form-label small fw-semibold
                                          mb-1"><?=
                                __('Distribution')
                            ?></label>
                            <select class="form-select form-select-sm"
                                    disabled title="<?= h($noWrites) ?>">
                                <option><?= __('All communities') ?></option>
                            </select>
                        </div>
                        <a href="#" class="btn btn-primary btn-sm disabled
                                           fw-semibold"
                           aria-disabled="true" title="<?= h($noWrites) ?>"><?=
                            __('Add note')
                        ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($analyst['acl_note'])): ?>
        <div class="vp-acl-note">
            <i class="fas fa-eye-slash"></i>
            <span><?= h($analyst['acl_note']) ?></span>
        </div>
    <?php endif; ?>

</div>
