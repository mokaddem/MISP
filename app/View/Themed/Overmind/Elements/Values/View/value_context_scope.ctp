<?php
/**
 * One scope of the context card: taxonomies, then galaxy clusters.
 *
 * Extracted when the card gained the event scope, so the two are the
 * same markup reading two arrays rather than two copies drifting apart.
 * The only thing the caller varies is **the unit**, and that is the
 * whole reason the scopes are drawn apart rather than merged:
 * `topTagsFor` counts the occurrences carrying a tag and `eventTagsFor`
 * counts the events carrying it, so `tlp:white` reads ×2 here and ×8
 * there on `8.8.8.8`. A single list under one `×N` column would mean
 * neither.
 *
 * **`flat` is a height decision, and it was forced by measurement.**
 * Grouped, each taxonomy is a row with a name column and a body, which
 * is right for the occurrence scope — `8.8.8.8` has three of them — and
 * ruinous for the event scope, which has **ten**: the card rendered at
 * **1,060px** against the 223 it had before, and took the Overview pane
 * from 1,515 to 2,320. Flat, the same tags are one cloud of chips
 * ordered by how many events carry them.
 *
 * What flat gives up is the per-taxonomy scale and the inline conflict
 * dot. Neither is lost: the scales were already withheld on a capped
 * read for a stated reason, every event-scope read here is capped, and
 * the conflict count still reaches the card header, which counts both
 * scopes.
 *
 * @var array $taxonomies From `ValueContextTool::taxonomies`
 * @var array $galaxies   From `ValueContextTool::galaxies`
 * @var int|null $tagCap  The cap, when it bit
 * @var string $unit      `occurrence` or `event`
 * @var bool $flat        One cloud instead of a row per taxonomy
 */
$isEvent = $unit === 'event';
$flat = !empty($flat);

$flatTags = array();
if ($flat) {
    foreach ($taxonomies as $taxonomy) {
        foreach ($taxonomy['tags'] as $tag) {
            /*
             * The conflict travels with the tag, because flat has no
             * taxonomy row to hang the dot on — and the card header
             * counts conflicts across both scopes, so a reader told
             * *1 in conflict* has to be able to find it.
             */
            $tag['conflict'] = !empty($taxonomy['conflict']);
            $tag['taxonomy'] = $taxonomy['taxonomy'];
            $flatTags[] = $tag;
        }
    }
    usort($flatTags, function ($a, $b) {
        if ($a['count'] !== $b['count']) {
            return $b['count'] - $a['count'];
        }
        return strcasecmp($a['name'], $b['name']);
    });
}
?>
<?php if ($tagCap !== null): ?>
    <?php
    /*
     * A cap is not a permission (§14.6), so it is stated on the panel
     * rather than left to be inferred from a list that stops. It also
     * explains the scales' absence: none is drawn on a capped read,
     * because a position means *one tag of this dimension* and a
     * truncated list cannot tell that from *one that was read*.
     */
    ?>
    <div class="vp-filter-note">
        <i class="fas fa-filter"></i>
        <span><?= h($isEvent
            ? sprintf(
                __('The %s most-carried labels on its events. There are'
                    . ' more, so no taxonomy is drawn as a scale here.'),
                number_format($tagCap)
            )
            : sprintf(
                __('The %s most-carried labels. This value has more, so'
                    . ' no taxonomy is drawn as a scale here.'),
                number_format($tagCap)
            )) ?></span>
    </div>
<?php endif; ?>

<?php if ($flat): ?>
    <div class="vp-tax vp-tax-flat">
        <div class="vp-tax-body">
            <div class="vp-tax-tags">
                <?php foreach ($flatTags as $tag): ?>
                    <span class="vp-tag" title="<?= h(sprintf(
                        __n(
                            'On %s event this value appears in',
                            'On %s events this value appears in',
                            $tag['count']
                        ),
                        number_format($tag['count'])
                    )) ?>">
                        <?= $this->element(
                            'genericElementsBS5/Badges/tag',
                            array(
                                'tag' => $tag,
                                'local' => !empty($tag['local']),
                                'scope' => 'event',
                                'hiddenClass' => '',
                            )
                        ) ?>
                        <span class="vp-tag-count">
                            &times;<?= h($tag['count']) ?>
                        </span>
                        <?php if (!empty($tag['conflict'])): ?>
                            <span class="vp-conflict-dot"
                                  title="<?= h(sprintf(
                                      __('Its events disagree about %s'),
                                      $tag['taxonomy']
                                  )) ?>"></span>
                        <?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php foreach ($flat ? array() : $taxonomies as $taxonomy): ?>
    <div class="vp-tax">
        <div class="vp-tax-name">
            <?= h($taxonomy['taxonomy']) ?>
            <?php if (!empty($taxonomy['conflict'])): ?>
                <span class="vp-conflict-dot"
                      title="<?= h($isEvent
                          ? __('Its events disagree')
                          : __('Occurrences disagree')) ?>"></span>
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
                <?php foreach ($taxonomy['tags'] as $tag): ?>
                    <?php
                    /*
                     * The count names its own unit in the tooltip,
                     * because the `×N` beside the chip cannot: the two
                     * scopes print the same glyph against two different
                     * denominators.
                     *
                     * Neither `attribute_tags` nor `event_tags` records
                     * who applied a tag, so no scope here can say *who
                     * said it* — the tooltip named the carrying events'
                     * creator organisations once and was withdrawn for
                     * claiming more than it knew.
                     */
                    ?>
                    <span class="vp-tag" title="<?= h($isEvent
                        ? sprintf(
                            __n(
                                'On %s event this value appears in',
                                'On %s events this value appears in',
                                $tag['count']
                            ),
                            number_format($tag['count'])
                        )
                        : sprintf(
                            __n(
                                'On %s occurrence of this value',
                                'On %s occurrences of this value',
                                $tag['count']
                            ),
                            number_format($tag['count'])
                        )) ?>">
                        <?= $this->element(
                            'genericElementsBS5/Badges/tag',
                            array(
                                'tag' => $tag,
                                'local' => !empty($tag['local']),
                                'scope' => $isEvent ? 'event' : null,
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
                    <span class="vp-galaxy" title="<?= h($isEvent
                        ? sprintf(
                            __n(
                                'Attributed on %s event this value'
                                    . ' appears in',
                                'Attributed on %s events this value'
                                    . ' appears in',
                                $galaxy['n']
                            ),
                            number_format($galaxy['n'])
                        )
                        : sprintf(
                            __n(
                                'Attributed on %s occurrence',
                                'Attributed on %s occurrences',
                                $galaxy['n']
                            ),
                            number_format($galaxy['n'])
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
