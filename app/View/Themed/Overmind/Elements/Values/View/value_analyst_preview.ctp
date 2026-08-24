<?php
/**
 * The most recent analyst notes and opinions on this value.
 *
 * A preview: the threaded view, the replies and the full opinion
 * distribution belong to the Analyst data tab. `AnalystData/thread` is
 * not reused here because it carries the add / edit / delete controls,
 * and nothing on this page writes.
 *
 * The data is shaped like `AnalystData::fetchForObject` returns it, so
 * the swap is a data source rather than a template.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewAnalystPreview.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$analyst = $valueProfile['analyst'];
$notes = $analyst['Note'];
$opinions = $analyst['Opinion'];
$shown = count($notes) + count($opinions);

$subtitle = implode(' &nbsp;·&nbsp; ', array(
    h(sprintf(__('%s notes'), $analyst['notes'])),
    h(sprintf(__('%s opinions'), $analyst['opinions'])),
));
if ($shown > 0 && $shown < $analyst['total']) {
    $subtitle .= ' &nbsp;·&nbsp; ' . h(sprintf(
        __('showing the %s most recent'),
        $shown
    ));
}

/*
 * The same banding AnalystData/thread uses, so an opinion reads the same
 * on both pages.
 */
$opinionBand = function ($o) {
    $o = max(0, min(100, (int)$o));
    if ($o >= 81) {
        return array(__('Strongly agree'), 'success');
    }
    if ($o >= 61) {
        return array(__('Agree'), 'success');
    }
    if ($o >= 41) {
        return array(__('Neutral'), 'secondary');
    }
    if ($o >= 21) {
        return array(__('Disagree'), 'danger');
    }
    return array(__('Strongly disagree'), 'danger');
};

$meta = function ($item) {
    $bits = array();
    if (!empty($item['Org']['name'])) {
        $bits[] = '<i class="fas fa-building me-1"></i>'
            . h($item['Org']['name']);
    }
    if (!empty($item['authors'])) {
        $bits[] = '<i class="fas fa-user me-1"></i>' . h($item['authors']);
    }
    if (!empty($item['created'])) {
        $bits[] = '<i class="fas fa-clock me-1"></i>' . h($item['created']);
    }
    return implode(' &nbsp;·&nbsp; ', $bits);
};

$headerExtra = '<a href="#tab-analyst"'
    . ' class="btn btn-sm btn-outline-secondary d-flex align-items-center'
    . ' gap-1" title="' . h(__('The full thread')) . '">'
    . h(__('Open thread')) . '<i class="fas fa-arrow-right"></i></a>';
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--analystData);">

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
            <span><?= __('No analyst has written about this value.') ?></span>
        </div>
    <?php else: ?>
        <div class="p-3 d-flex flex-column gap-2">

            <?php foreach ($notes as $note): ?>
                <div class="vp-analyst vp-analyst-note">
                    <div class="vp-analyst-kind">
                        <span class="misp-icon misp-icon-analyst-note
                                     misp-simple"></span>
                        <?= __('Note') ?>
                    </div>
                    <div class="vp-analyst-body">
                        <div class="vp-analyst-text">
                            <?= h($note['note']) ?>
                        </div>
                        <div class="vp-analyst-meta"><?= $meta($note) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php foreach ($opinions as $opinion):
                list($label, $colour) = $opinionBand($opinion['opinion']);
                ?>
                <div class="vp-analyst vp-analyst-opinion">
                    <div class="vp-analyst-kind">
                        <span class="misp-icon misp-icon-analyst-opinion
                                     misp-simple"></span>
                        <?= __('Opinion') ?>
                    </div>
                    <div class="vp-analyst-body">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-<?= h($colour) ?>-subtle
                                         text-<?= h($colour) ?>-emphasis
                                         border border-<?= h($colour) ?>-subtle
                                         fw-semibold">
                                <?= h($label) ?>
                                &middot;
                                <?= h($opinion['opinion']) ?>/100
                            </span>
                        </div>
                        <?php if (!empty($opinion['comment'])): ?>
                            <div class="vp-analyst-text">
                                <?= h($opinion['comment']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="vp-analyst-meta">
                            <?= $meta($opinion) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    <?php endif; ?>

</div>
