<?php
/**
 * What the community has labelled this value.
 *
 * Tags are grouped by taxonomy rather than listed flat, because a
 * taxonomy is the unit that can disagree with itself: two events putting
 * `tlp:amber` and `tlp:green` on the same value is a fact about the
 * value, and a flat list hides it. Where a taxonomy is ordinal —
 * `admiralty-scale` and its kin — the group renders as a position on a
 * scale instead of a string nobody reads.
 *
 * Galaxy clusters follow, with the number of occurrences each was
 * attributed to.
 *
 * Lazily loaded into `.ajax-tab-content` from
 * ValuesController::viewContext.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$profile = $valueProfile;
$taxonomies = $profile['tags'];
$galaxies = $profile['galaxies'];

$conflicts = 0;
foreach ($taxonomies as $taxonomy) {
    if (!empty($taxonomy['conflict'])) {
        $conflicts++;
    }
}

$subtitle = implode(' &nbsp;·&nbsp; ', array(
    h(sprintf(__('%s taxonomies'), count($taxonomies))),
    h(sprintf(__('%s galaxy clusters'), count($galaxies))),
));

$headerExtra = null;
if ($conflicts > 0) {
    $headerExtra = '<span class="vp-conflict" title="'
        . h(__(
            'Occurrences of this value carry tags from the same taxonomy'
            . ' that contradict each other. Shown as they are, not'
            . ' resolved to one.'
        ))
        . '"><i class="fas fa-code-branch"></i>'
        . h(sprintf(__('%s in conflict'), $conflicts))
        . '</span>';
}
?>
<div class="card shadow-sm mb-3 vp-panel"
     style="--vp-panel-color: var(--tag);">

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Tags and galaxies'),
        'panelIcon' => 'misp-icon misp-icon-tag misp-simple',
        'panelColor' => 'var(--tag)',
        'panelSub' => $subtitle,
        'panelExtra' => $headerExtra,
    )) ?>

    <?php if (empty($taxonomies) && empty($galaxies)): ?>
        <div class="vp-empty">
            <i class="fas fa-tag"></i>
            <span><?= __('Nobody has tagged this value.') ?></span>
        </div>
    <?php else: ?>

        <?php foreach ($taxonomies as $taxonomy): ?>
            <div class="vp-tax">
                <div class="vp-tax-name">
                    <?= h($taxonomy['taxonomy']) ?>
                    <?php if (!empty($taxonomy['conflict'])): ?>
                        <span class="vp-conflict-dot"
                              title="<?= h(__('Occurrences disagree')) ?>">
                        </span>
                    <?php endif; ?>
                </div>
                <div class="vp-tax-body">

                    <?php if (!empty($taxonomy['scale'])):
                        $scale = $taxonomy['scale']; ?>
                        <div class="vp-scale">
                            <div class="vp-scale-track">
                                <?php for ($i = 1; $i <= $scale['of']; $i++): ?>
                                    <span class="vp-scale-seg<?=
                                        $i <= $scale['position']
                                            ? ' vp-scale-seg-on'
                                            : '' ?>"></span>
                                <?php endfor; ?>
                            </div>
                            <div class="vp-scale-reading">
                                <span class="fw-semibold">
                                    <?= h($scale['reading']) ?>
                                </span>
                                <span class="text-muted">
                                    <?= h($scale['label']) ?>
                                    <?= h(sprintf(
                                        __('(%1$s of %2$s)'),
                                        $scale['position'],
                                        $scale['of']
                                    )) ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="vp-tax-tags">
                        <?php foreach ($taxonomy['tags'] as $tag):
                            $orgs = implode(', ', $tag['orgs']);
                            ?>
                            <span class="vp-tag" title="<?= h(sprintf(
                                __('On %1$s occurrences, from %2$s'),
                                $tag['count'],
                                $orgs
                            )) ?>">
                                <?= $this->element(
                                    'genericElementsBS5/Badges/tag',
                                    array(
                                        'tag' => $tag,
                                        'local' => !empty($tag['local']),
                                        'hiddenClass' => '',
                                    )
                                ) ?>
                                <span class="vp-tag-count">
                                    &times;<?= h($tag['count']) ?>
                                </span>
                            </span>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!empty($galaxies)): ?>
            <div class="vp-tax vp-tax-galaxies">
                <div class="vp-tax-name"><?= __('Galaxies') ?></div>
                <div class="vp-tax-body">
                    <div class="vp-tax-tags">
                        <?php foreach ($galaxies as $galaxy): ?>
                            <span class="vp-galaxy" title="<?= h(sprintf(
                                __('Attributed on %s occurrences'),
                                $galaxy['n']
                            )) ?>">
                                <span class="misp-icon misp-icon-galaxy
                                             misp-simple"></span>
                                <span class="vp-galaxy-name">
                                    <?= h($galaxy['name']) ?>
                                </span>
                                <span class="vp-galaxy-kind">
                                    <?= h($galaxy['kind']) ?>
                                </span>
                                <span class="vp-galaxy-count">
                                    <?= h($galaxy['n']) ?>
                                </span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>
