<?php
/**
 * The most recent analyst notes and opinions on this value.
 *
 * A preview: the thread, the replies, the proposals, the event reports
 * and the full opinion distribution belong to the Collaboration tab.
 * `AnalystData/thread` is not reused here because it carries the add /
 * edit / delete controls, and nothing on this page writes.
 *
 * **Live since 2026-09-05**, off `ValueProfile::forAnalystPreview` and
 * therefore off the same union the tab reads. It carried the fixture's
 * notes for three phases while the tab beside it went live, which is
 * the state `26-analyst.md` §14.13 predicted would start lying — a
 * reader met one set of counts here and another one tab across.
 *
 * **Newest first across both kinds.** The fixture handed over a `Note`
 * array and an `Opinion` array and this card drew every note above
 * every opinion; that was the fixture's shape and not a decision, and
 * it let a card headed *the most recent* put a two-year-old note above
 * yesterday's opinion.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewAnalystPreview.
 *
 * @var array $valueProfile
 */
$analyst = $valueProfile['analyst'];
$counts = $analyst['counts'];
$items = $analyst['preview'];
$written = (int)$counts['notes'] + (int)$counts['opinions'];
$shown = count($items);

$subtitle = implode(' &nbsp;·&nbsp; ', array_filter(array(
    h(sprintf(
        __n('%s note', '%s notes', (int)$counts['notes']),
        (int)$counts['notes']
    )),
    h(sprintf(
        __n('%s opinion', '%s opinions', (int)$counts['opinions']),
        (int)$counts['opinions']
    )),
    /*
     * Proposals are counted here and drawn on the tab. The card says
     * they exist because the alternative is a subtitle that reads
     * `0 notes · 0 opinions` over a value three organisations have
     * proposed edits to.
     */
    (int)$counts['proposals'] > 0
        ? h(sprintf(
            __n('%s proposal', '%s proposals', (int)$counts['proposals']),
            (int)$counts['proposals']
        ))
        : null,
    $shown > 0 && $shown < $written
        ? h(sprintf(__('showing the %s most recent'), $shown))
        : null,
)));

/**
 * One item's organisation, author and date.
 *
 * The organisation opens, under §18.1's rule that a chip naming a
 * record is a link to that record — the thread's meta line, the report
 * rows and the ledger already follow it, and this card could not while
 * its organisations were fixture strings with no id behind them.
 *
 * @param array $item
 * @return string
 */
$meta = function ($item) use ($baseurl) {
    $bits = array();
    $bits[] = '<span class="misp-icon misp-icon-organisation'
        . ' misp-simple me-1"></span>'
        . (empty($item['org_id'])
            ? h($item['org'])
            : '<a class="vpa-orglink" href="' . h($baseurl)
                . '/organisations/view/' . (int)$item['org_id'] . '">'
                . h($item['org']) . '</a>');
    // Free text on the row and not a user reference, so it is printed
    // and never linked.
    if (!empty($item['author'])) {
        $bits[] = '<i class="fas fa-user me-1"></i>' . h($item['author']);
    }
    if (!empty($item['date'])) {
        $bits[] = '<i class="fas fa-clock me-1"></i>' . h($item['date']);
    }
    return implode(' &nbsp;·&nbsp; ', $bits);
};

/*
 * Nothing written is a state, and which state depends on what else the
 * union found. A value nobody has written about and a value three
 * organisations have proposed edits to are not the same emptiness, and
 * this card previews a tab that holds both.
 */
$emptyText = (int)$counts['proposals'] > 0
    ? __(
        'Nobody has written a note or an opinion about this value, but'
        . ' there are proposals on it.'
    )
    : __('No analyst has written about this value.');

// Nothing on the tab means nothing to open, so the affordance goes too.
$hasTab = $written > 0 || (int)$counts['proposals'] > 0;
$headerExtra = !$hasTab ? null : '<a href="#tab-analyst"'
    . ' class="btn btn-sm btn-outline-secondary d-flex align-items-center'
    . ' gap-1" title="' . h(__('The full thread')) . '">'
    . h(__('Open thread')) . '<i class="fas fa-arrow-right"></i></a>';
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--analystData);"
     data-vp-analyst-preview>

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Analyst data'),
        'panelIcon' => 'misp-icon misp-icon-analyst-note misp-simple',
        'panelColor' => 'var(--analystData)',
        'panelSub' => $subtitle,
        'panelExtra' => $headerExtra,
    )) ?>

    <?php if ($shown === 0): ?>
        <div class="vp-empty">
            <span class="misp-icon misp-icon-analyst-note misp-simple"></span>
            <span><?= h($emptyText) ?></span>
        </div>
    <?php else: ?>
        <div class="p-3 d-flex flex-column gap-2">

            <?php foreach ($items as $item):
                $isOpinion = $item['kind'] === 'opinion';
                ?>
                <div class="vp-analyst vp-analyst-<?=
                    $isOpinion ? 'opinion' : 'note' ?>">
                    <div class="vp-analyst-kind">
                        <span class="misp-icon misp-icon-analyst-<?=
                            $isOpinion ? 'opinion' : 'note'
                        ?> misp-simple"></span>
                        <?= $isOpinion ? __('Opinion') : __('Note') ?>
                    </div>
                    <div class="vp-analyst-body">
                        <?php if ($isOpinion): ?>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <?php
                                /*
                                 * **Coloured by the score against 50,
                                 * never by the band word.** That is
                                 * MISP's own rule, not this page's:
                                 * `Analyst_data/opinion_scale.ctp`
                                 * computes `$opinion > 50 ? green :
                                 * red`, greys exactly 50, and paints
                                 * the band word and the numeral with
                                 * it. The five words split at
                                 * 20/40/60/80 and agreement splits at
                                 * 50, so the word is not what carries
                                 * the side — the score is, and a
                                 * `Neutral` at 45 is red where a
                                 * `Neutral` at 55 is green.
                                 *
                                 * The card used to colour by *band*:
                                 * 61+ green, 41-60 grey, 40 and below
                                 * red. That is what made it half of
                                 * `05-analyst.md` §11's contradiction —
                                 * it painted a disagreeing 45 the same
                                 * grey as an agreeing 60. The fix is
                                 * the pivot, not the absence of colour.
                                 *
                                 * `reads` is resolved in the facade off
                                 * the same 50, so this badge and the
                                 * tab's ledger take one side per score.
                                 */
                                $side = array(
                                    'malicious' => 'agree',
                                    'benign' => 'dispute',
                                );
                                $side = isset($side[$item['reads']])
                                    ? $side[$item['reads']]
                                    : 'neither';
                                ?>
                                <span class="vpa-reading vpa-s-<?=
                                          h($side) ?>"
                                      title="<?= h(__(
                                          'MISP colours an opinion by'
                                          . ' its score against 50 —'
                                          . ' above it agrees with what'
                                          . ' the record asserts, below'
                                          . ' it disagrees, and exactly'
                                          . ' 50 takes no side.'
                                      )) ?>">
                                    <i></i><?= h($item['label']) ?>
                                    &middot;
                                    <?= (int)$item['score'] ?>/100
                                </span>
                                <?php
                                /*
                                 * An opinion the ledger leaves out, in
                                 * the tab's own words rather than in
                                 * this card's. Only one of the two
                                 * cases can reach a preview — these are
                                 * roots, so nothing here hangs off the
                                 * item above it — but a root whose
                                 * anchor no longer resolves is D5's row
                                 * and is drawn because somebody wrote
                                 * it. Saying *rates a note* over one of
                                 * those would be this card inventing a
                                 * target the row does not have.
                                 */
                                ?>
                                <?php if ($item['rates'] !== 'value'): ?>
                                    <span class="vpa-chip"><?= h(
                                        $item['attached_to']['kind']
                                            === 'unresolved'
                                            ? __(
                                                'the row this hangs off'
                                                . ' does not resolve —'
                                                . ' not in the aggregate'
                                            )
                                            : __(
                                                'about the item above,'
                                                . ' not about the value'
                                                . ' — not in the'
                                                . ' aggregate'
                                            )
                                    ) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($item['body'] !== ''): ?>
                            <div class="vp-analyst-text"><?=
                                h($item['body'])
                            ?></div>
                        <?php endif; ?>
                        <div class="vp-analyst-meta"><?= $meta($item) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    <?php endif; ?>

</div>
