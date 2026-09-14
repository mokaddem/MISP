<?php
/**
 * What the community has labelled this value — and what it labelled the
 * reports the value arrived in.
 *
 * Tags are grouped by taxonomy rather than listed flat, because a
 * taxonomy is the unit that can disagree with itself: two events putting
 * `tlp:amber` and `tlp:green` on the same value is a fact about the
 * value, and a flat list hides it. Where a taxonomy is ordinal —
 * `admiralty-scale` and its kin — the group renders as a position on a
 * scale instead of a string nobody reads.
 *
 * **Two scopes since 2026-09-14, drawn apart.** Every tag read on this
 * page joined `attribute_tags` and nothing else, which showed a
 * minority of the labelling: an analyst tags the report far more often
 * than the indicator inside it, and `8.8.8.8` carries **7 distinct
 * attribute tags** against **48 distinct event tags** across its twenty
 * events. This card's own subtitle was the evidence — it read *0 galaxy
 * clusters* on a value whose events are attributed to three MITRE
 * ATT&CK techniques.
 *
 * They are two sections and not one list because **the count means two
 * different things**: the first counts the occurrences carrying a tag,
 * the second the events carrying it, so `tlp:white` is ×2 above and ×8
 * below. Merging them would put one `×N` column over two denominators.
 *
 * `value_context_scope` draws either one; only the unit differs.
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
$tagCap = isset($profile['tag_cap']) ? $profile['tag_cap'] : null;
$eventTaxonomies = $profile['event_tags'] ?? array();
$eventGalaxies = $profile['event_galaxies'] ?? array();
$eventTagCap = $profile['event_tag_cap'] ?? null;

$conflicts = 0;
foreach (array_merge($taxonomies, $eventTaxonomies) as $taxonomy) {
    if (!empty($taxonomy['conflict'])) {
        $conflicts++;
    }
}

$hasOccurrence = !empty($taxonomies) || !empty($galaxies);
$hasEvent = !empty($eventTaxonomies) || !empty($eventGalaxies);

/*
 * **Both scopes counted, and the galaxy cell is why it matters.** The
 * subtitle read *3 taxonomies · 0 galaxy clusters* on `8.8.8.8`, which
 * was true of its occurrences and false of the value as anybody reading
 * the page would understand it. Splitting the cells makes *0* a fact
 * about a scope rather than about the value, which also settles the
 * apparent argument with the Assessment rail: that panel scores *no
 * galaxy on any occurrence*, and it still means exactly that.
 */
$subtitle = implode(' &nbsp;·&nbsp; ', array(
    h(sprintf(
        __n(
            '%s taxonomy on its occurrences',
            '%s taxonomies on its occurrences',
            count($taxonomies)
        ),
        count($taxonomies)
    )),
    h(sprintf(
        __n(
            '%s on its events',
            '%s on its events',
            count($eventTaxonomies)
        ),
        count($eventTaxonomies)
    )),
    h(sprintf(
        __n(
            '%s galaxy cluster',
            '%s galaxy clusters',
            count($galaxies) + count($eventGalaxies)
        ),
        count($galaxies) + count($eventGalaxies)
    )),
));

$headerExtra = null;
if ($conflicts > 0) {
    $headerExtra = '<span class="vp-conflict" title="'
        . h(__(
            'Tags from the same taxonomy contradict each other here.'
            . ' Shown as they are, not resolved to one.'
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

    <?php if (!$hasOccurrence && !$hasEvent): ?>
        <div class="vp-empty">
            <i class="fas fa-tag"></i>
            <span><?= __('Nobody has tagged this value.') ?></span>
        </div>
    <?php else: ?>

        <?php
        /*
         * The heading only appears once there is a second section to
         * tell this one apart from. A value whose events carry nothing
         * draws exactly what it drew before this card had scopes.
         */
        ?>
        <?php if ($hasEvent): ?>
            <div class="vp-tax-scope">
                <span class="misp-icon misp-icon-attribute misp-simple">
                </span>
                <?= __('On its occurrences') ?>
            </div>
        <?php endif; ?>

        <?php if ($hasOccurrence): ?>
            <?= $this->element('Values/View/value_context_scope', array(
                'taxonomies' => $taxonomies,
                'galaxies' => $galaxies,
                'tagCap' => $tagCap,
                'unit' => 'occurrence',
            )) ?>
        <?php else: ?>
            <?php
            /*
             * Said rather than left blank. A value labelled only through
             * its reports is an ordinary case — most values are — and an
             * empty half under a heading reads as a panel that failed.
             */
            ?>
            <div class="vp-empty vp-empty-inline">
                <span class="misp-icon misp-icon-attribute misp-simple">
                </span>
                <span><?= __(
                    'No occurrence of this value carries a tag of its'
                    . ' own.'
                ) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($hasEvent): ?>
            <div class="vp-tax-scope">
                <span class="misp-icon misp-icon-event misp-simple"></span>
                <?= __('On the events it appears in') ?>
                <span class="vp-tax-scope-note">
                    <?= __('about the report, not only about this value') ?>
                </span>
            </div>
            <?= $this->element('Values/View/value_context_scope', array(
                'taxonomies' => $eventTaxonomies,
                'galaxies' => $eventGalaxies,
                'tagCap' => $eventTagCap,
                'unit' => 'event',
                // One cloud, not ten taxonomy rows — the partial
                // carries the measurement that forced it.
                'flat' => true,
            )) ?>
        <?php endif; ?>

    <?php endif; ?>

</div>
