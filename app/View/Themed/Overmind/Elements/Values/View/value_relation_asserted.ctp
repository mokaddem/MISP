<?php
/**
 * Section three: relationships somebody wrote down on purpose.
 *
 * The rule the whole tab lives or dies by is enforced here by form and
 * not by hue: **a machine relation is a table row, a human claim never
 * is**. Each claim is a `.vp-analyst` block — the same primitive the
 * Overview's analyst preview and the Analyst data tab use — so a claim
 * looks like a claim everywhere on the page, and cannot be mistaken for
 * a row even in greyscale.
 *
 * This section is never ranked and never truncated. Nobody generated
 * these; they were written one at a time, and the only thing that can
 * remove one from the list is a distribution the reader is outside of.
 * That is the inverse of section one's cap, and it is stated as such.
 *
 * Lazily loaded from ValuesController::viewRelationAsserted.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
$profile = $valueProfile;
$asserted = $profile['relationships']['asserted'];
$claims = $asserted['claims'];
$view = $this;

$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

/**
 * @param string $text
 * @return string
 */
$slug = function ($text) {
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
};

/*
 * One glyph per target kind, so the reader can tell an event from a
 * galaxy cluster without reading the word — and the word is there too.
 */
$targetIcons = array(
    'Event' => 'misp-icon misp-icon-event misp-simple',
    'GalaxyCluster' => 'misp-icon misp-icon-galaxy misp-simple',
    'Object' => 'misp-icon misp-icon-object misp-simple',
    'Attribute' => 'misp-icon misp-icon-attribute misp-simple',
);

/*
 * The same four kinds in a sentence. `strtolower` on the model name
 * would say *this galaxycluster*.
 */
$kindWords = array(
    'Event' => __('event'),
    'GalaxyCluster' => __('galaxy cluster'),
    'Object' => __('object'),
    'Attribute' => __('attribute'),
);

/**
 * Where a claim's far end opens.
 *
 * **To the event's own tab and not to `/attributes/view` or
 * `/objects/view`**, which is the choice the sightings table already
 * made for the same reason: this theme's event view takes no `focus:`
 * parameter, and the two flat views redirect to the event and lose
 * which record they were asked about. The link's title carries the
 * record, so what the cell cannot show the hover does.
 *
 * @param array $target
 * @return string|null Null when there is nowhere to send the reader
 */
$targetUrl = function ($target) use ($baseurl) {
    if (empty($target['resolved'])) {
        return null;
    }
    if ($target['kind'] === 'Event') {
        return $baseurl . '/events/view2/' . $target['id'];
    }
    if ($target['kind'] === 'GalaxyCluster') {
        return $baseurl . '/galaxy_clusters/view/' . $target['id'];
    }
    if (empty($target['event'])) {
        return null;
    }
    return $baseurl . '/events/view2/' . $target['event']['id']
        . ($target['kind'] === 'Object'
            ? '#tab-objects'
            : '#tab-attributes');
};

/**
 * What that link promises, which is not always the target itself.
 *
 * @param array $target
 * @return string
 */
$targetTitle = function ($target) {
    if ($target['kind'] === 'Event') {
        return sprintf(__('Open event #%s'), $target['id']);
    }
    if ($target['kind'] === 'GalaxyCluster') {
        return __('Open this galaxy cluster');
    }
    return sprintf(
        $target['kind'] === 'Object'
            ? __('Object #%1$s, in the Objects tab of event #%2$s')
            : __('Attribute #%1$s, in the Attributes tab of event #%2$s'),
        $target['id'],
        empty($target['event']) ? '?' : $target['event']['id']
    );
};

/*
 * The hover card's three parts. Rows rather than prose: everything in
 * it is a stored column, and a reader who opens it is checking one.
 */

/**
 * @param string $text
 * @return string
 */
$tipHead = function ($text) {
    return '<div class="vp-claim-tiphead">' . h($text) . '</div>';
};

/**
 * @param string $label
 * @param string $html Already escaped, or built from escaped parts
 * @param string $class
 * @return string Empty when there is no value, so the row does not draw
 */
$tipRaw = function ($label, $html, $class = '') {
    if ($html === '' || $html === null) {
        return '';
    }
    return '<div class="vp-claim-tiprow"><b>' . h($label) . '</b>'
        . '<span class="' . h($class) . '">' . $html . '</span></div>';
};

/**
 * @param string $label
 * @param string|null $value
 * @param string $class
 * @return string
 */
$tipRow = function ($label, $value, $class = '') use ($tipRaw) {
    return $tipRaw($label, $value === null ? '' : h($value), $class);
};

/**
 * An organisation, linked wherever there is an id to link it by.
 *
 * @param array|null $org
 * @param string $title
 * @return string
 */
$orgChip = function ($org, $title) use ($baseurl) {
    if (empty($org['name'])) {
        return '';
    }
    $glyph = '<i class="fas fa-building me-1"></i>';
    if (empty($org['id'])) {
        return '<span title="' . h($title) . '">' . $glyph
            . h($org['name']) . '</span>';
    }
    return '<a class="vp-claim-link" title="' . h($title) . '" href="'
        . h($baseurl . '/organisations/view/' . $org['id']) . '">'
        . $glyph . h($org['name']) . '</a>';
};

/*
 * The relationship types actually present, for the filter. Offering a
 * type nobody used would be a control that can only empty the list.
 */
$types = array();
foreach ($claims as $claim) {
    $types[$claim['relationship_type']] = true;
}
$types = array_keys($types);
sort($types);

ob_start();
?>
    <select class="form-select form-select-sm w-auto"
            data-vp-filter-key="rel"
            aria-label="<?= __('Relationship type') ?>">
        <option value=""><?= __('All relationship types') ?></option>
        <?php foreach ($types as $type): ?>
            <option value="<?= h($slug($type)) ?>"><?= h($type) ?></option>
        <?php endforeach; ?>
    </select>
<?php
$headerExtra = ob_get_clean();
if (empty($claims)) {
    $headerExtra = null;
}
?>
<div class="card shadow-sm mb-3 vp-panel vp-rel-k-human"
     style="--vp-panel-color: var(--vp-rel-human);"
     data-vp-list>

    <?php
    ob_start();
    ?>
        <span class="vp-rel-tag me-1">
            <span class="misp-icon misp-icon-analyst-note misp-simple"></span>
            <?= h(__('Asserted')) ?>
        </span>
        <?php if (empty($claims)): ?>
            <?= h(__('No claim')) ?>
        <?php else: ?>
            <?= h(sprintf(
                __('%1$s from %2$s'),
                __n('%d claim', '%d claims', $asserted['total'],
                    $asserted['total']),
                __n('%d organisation', '%d organisations',
                    $asserted['orgs'], $asserted['orgs'])
            )) ?>
        <?php endif; ?>
        &nbsp;·&nbsp;<?= h(__('analyst-data relationships')) ?>&nbsp;·&nbsp;
        <span class="vp-rel-prov vp-rel-prov-human">
            <i class="fas fa-user-pen"></i><?= h(__('Human claim')) ?>
        </span>
    <?php
    $headerSub = ob_get_clean();
    ?>

    <?= $this->element('Values/View/value_panel_header', array(
        'panelTitle' => __('Asserted by analysts'),
        'panelIcon' => 'misp-icon misp-icon-analyst-note misp-simple',
        'panelColor' => 'var(--vp-rel-human)',
        'panelSub' => $headerSub,
        'panelExtra' => $headerExtra,
    )) ?>

    <?php if (empty($claims)): ?>

        <div class="p-3">
            <div class="vp-empty">
                <span class="misp-icon misp-icon-analyst-note
                             misp-simple"></span>
                <span>
                    <?= __('No analyst has asserted a relationship for'
                        . ' this value.') ?>
                </span>
            </div>
        </div>

    <?php else: ?>

        <div class="vp-rel-cap vp-rel-cap-complete">
            <i class="fas fa-user-check"></i>
            <span>
                <?= sprintf(
                    __(
                        '%1$s Asserted relationships are written one at a'
                        . ' time by people, so this section is never'
                        . ' ranked and never truncated — the only cut is'
                        . ' ACL. It stays complete on a value whose'
                        . ' events the section above could not even'
                        . ' read.'
                    ),
                    '<strong>' . h(__n(
                        'The single claim is shown.',
                        'All %d claims are shown.',
                        $asserted['total'],
                        $asserted['total']
                    )) . '</strong>'
                ) ?>
            </span>
        </div>

        <div class="p-3 d-flex flex-column gap-2" data-vp-list-rows>
            <?php foreach ($claims as $claim): ?>
                <?php
                $target = $claim['target'];
                $icon = isset($targetIcons[$target['kind']])
                    ? $targetIcons[$target['kind']]
                    : 'fas fa-cube';
                $outbound = $claim['direction'] === 'outbound';
                $url = $targetUrl($target);
                ?>
                <div class="vp-analyst vp-rel-claim"
                     data-vp-list-row
                     data-vp-facet="rel:<?=
                         h($slug($claim['relationship_type'])) ?>">
                    <div class="vp-analyst-kind">
                        <i class="fas fa-arrow-right-long"></i>
                        <?= h($claim['relationship_type']) ?>
                    </div>
                    <div class="vp-analyst-body">
                        <div class="d-flex align-items-center gap-2
                                    flex-wrap">
                            <span class="vp-rel-dir"
                                  title="<?= h($outbound
                                      ? __('This value is the source of'
                                          . ' the claim')
                                      : __('Something else claims a'
                                          . ' relationship to this value')
                                  ) ?>">
                                <i class="fas fa-arrow-<?=
                                    $outbound ? 'right' : 'left' ?>"></i>
                                <?= h($outbound
                                    ? __('outbound')
                                    : __('inbound')) ?>
                            </span>
                            <span class="vp-rel-target">
                                <?php if (strpos($icon, 'misp-icon') === 0): ?>
                                    <span class="<?= h($icon) ?>"></span>
                                <?php else: ?>
                                    <i class="<?= h($icon) ?>"></i>
                                <?php endif; ?>
                                <span class="fw-semibold">
                                    <?= h($target['kind']) ?>
                                </span>
                                <?php if ($url === null): ?>
                                    <?php
                                    /*
                                     * A UUID and not a name, so it is
                                     * set in the face that says so.
                                     */
                                    ?>
                                    <span class="text-muted <?= $target[
                                        'resolved'] ? '' : 'font-monospace' ?>">
                                        <?= h($target['label']) ?>
                                    </span>
                                <?php else: ?>
                                    <a class="vp-claim-link vp-claim-target"
                                       href="<?= h($url) ?>"
                                       title="<?= h($targetTitle($target)) ?>">
                                        <?= h($target['label']) ?>
                                    </a>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($claim['text'] !== ''): ?>
                            <div class="vp-analyst-text mt-1">
                                <?= h($claim['text']) ?>
                            </div>
                        <?php endif; ?>
                        <?php
                        /*
                         * What the far end *is*, under what it is
                         * called. Ordered the same way for every kind —
                         * where it lives, what it is, who made it — so
                         * six claims pointing at four kinds of thing
                         * still read down one column.
                         */
                        $facts = array();
                        if (!empty($target['event'])) {
                            $facts[] = '<a class="vp-claim-link" href="'
                                . h($baseurl . '/events/view2/'
                                    . $target['event']['id'])
                                . '" title="' . h(__('Open the event'))
                                . '">' . h(sprintf(
                                    '#%s %s',
                                    $target['event']['id'],
                                    $target['event']['info']
                                )) . '</a>';
                        }
                        foreach ($target['facts'] as $fact) {
                            $facts[] = h($fact);
                        }
                        $facts[] = $orgChip(
                            $target['org'],
                            sprintf(
                                __('The organisation that created this %s'),
                                isset($kindWords[$target['kind']])
                                    ? $kindWords[$target['kind']]
                                    : $target['kind']
                            )
                        );
                        $facts = array_filter($facts);
                        ?>
                        <?php if (!$target['resolved']): ?>
                            <?php
                            /*
                             * Two reasons, one line, and the panel does
                             * not know which: `getRelatedElement` and
                             * `fetchGalaxyClusters` are both ACL'd, so
                             * an empty answer means gone *or* not
                             * yours. Naming both is what §14.6 allows —
                             * the claim itself is already visible, and
                             * this says nothing about whether the thing
                             * it names exists.
                             */
                            ?>
                            <div class="vp-claim-facts vp-claim-unresolved">
                                <i class="fas fa-circle-question"></i>
                                <span>
                                    <?= __('Not held on this instance, or'
                                        . ' not visible to you — the claim'
                                        . ' is shown by the UUID it names.')
                                    ?>
                                </span>
                            </div>
                        <?php elseif (!empty($facts)): ?>
                            <div class="vp-claim-facts">
                                <i class="fas fa-turn-up fa-rotate-90"></i>
                                <span>
                                    <?= implode(
                                        ' <span class="vp-claim-sep">·</span> ',
                                        $facts
                                    ) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <div class="vp-analyst-meta d-flex align-items-center
                                    gap-2 flex-wrap">
                            <?php
                            /*
                             * Named, now that a second organisation can
                             * appear two lines above it. `ADMIN` beside
                             * a date said nothing about which of the
                             * two it was.
                             */
                            ?>
                            <span>
                                <?= sprintf(
                                    __('Asserted by %s'),
                                    $orgChip(
                                        array(
                                            'id' => $claim['org_id'],
                                            'name' => $claim['org'],
                                        ),
                                        __('The organisation credited with'
                                            . ' writing this claim')
                                    )
                                ) ?>
                            </span>
                            <?php if ($claim['owner'] !== null): ?>
                                <?php
                                /*
                                 * Only when the two columns differ,
                                 * which on an instance nothing has
                                 * synced into is never. A claim that
                                 * arrived over a sync is owned by the
                                 * organisation that passed it on and
                                 * credited to the one that wrote it.
                                 */
                                ?>
                                <span>·</span>
                                <span>
                                    <?= sprintf(
                                        __('held by %s'),
                                        $orgChip(
                                            array(
                                                'id' => $claim['owner_id'],
                                                'name' => $claim['owner'],
                                            ),
                                            __('The organisation this'
                                                . ' instance holds the'
                                                . ' claim for')
                                        )
                                    ) ?>
                                </span>
                            <?php endif; ?>
                            <span>·</span>
                            <span class="font-monospace"
                                  title="<?= h(__('Last modified')) ?>">
                                <?= h($claim['date']) ?>
                            </span>
                            <?= $this->element(
                                'genericElementsBS5/Badges/distribution',
                                array(
                                    'distribution' =>
                                        (int)$claim['distribution'],
                                    'full' => false,
                                )
                            ) ?>
                            <?php
                            /*
                             * The rest of the record, on hover and on
                             * keyboard focus. Not `data-bs-toggle` and
                             * not a `title`: this panel arrives through
                             * `loadAjaxContainer`, and the only
                             * Bootstrap tooltip initialiser MISP has
                             * runs once at `DOMContentLoaded` — a
                             * tooltip declared here would silently
                             * never bind. CSS on `:hover` and
                             * `:focus-within` has no such lifecycle,
                             * needs no JS on a page that already runs
                             * plenty, and reaches the keyboard, which a
                             * native `title` does not.
                             */
                            $against = $claim['against'];
                            $againstUrl = $baseurl . '/events/view2/'
                                . $against['event_id'] . '#tab-attributes';
                            $fullDist = $this->element(
                                'genericElementsBS5/Badges/distribution',
                                array(
                                    'distribution' =>
                                        (int)$claim['distribution'],
                                    'full' => true,
                                )
                            );
                            /*
                             * The group named beside the badge rather
                             * than under it. At level 4 the badge reads
                             * *Sharing group*, so a `Sharing group` row
                             * below it spent a whole line repeating the
                             * label to deliver one word.
                             */
                            if ($claim['sharing_group'] !== null) {
                                $fullDist .= ' ' . h($claim['sharing_group']);
                            }
                            ?>
                            <span class="vp-claim-tipwrap">
                                <button type="button" class="vp-claim-more"
                                        aria-label="<?= h(__('Everything'
                                            . ' recorded about this claim'))
                                        ?>">
                                    <i class="fas fa-circle-info"></i>
                                </button>
                                <div class="vp-claim-tip" role="tooltip">

                                    <?php
                                    /*
                                     * The card lists what the row
                                     * *stores*. `direction` is derived
                                     * from which endpoint column holds
                                     * one of our occurrences, and the
                                     * chip two lines above already says
                                     * it, so it is the one thing here
                                     * that is not a column and is not
                                     * listed.
                                     */
                                    ?>
                                    <?= $tipHead(__('This claim')) ?>
                                    <?= $tipRow(__('Type'),
                                        $claim['relationship_type']) ?>
                                    <?php
                                    /*
                                     * One row while the two timestamps
                                     * agree. Printing `Created` and
                                     * `Modified` with the same value
                                     * under each other reads as a
                                     * defect in the card rather than as
                                     * a claim nobody has edited.
                                     */
                                    ?>
                                    <?php if ($claim['modified']
                                        === $claim['created']): ?>
                                        <?= $tipRow(__('Written'),
                                            $claim['created']) ?>
                                    <?php else: ?>
                                        <?= $tipRow(__('Created'),
                                            $claim['created']) ?>
                                        <?= $tipRow(__('Modified'),
                                            $claim['modified']) ?>
                                    <?php endif; ?>
                                    <?= $tipRow(__('Authors'),
                                        $claim['authors']) ?>
                                    <?= $tipRaw(__('Audience'),
                                        $fullDist) ?>
                                    <?= $tipRow(__('UUID'), $claim['uuid'],
                                        'font-monospace') ?>

                                    <?php
                                    /*
                                     * The near end, which the block has
                                     * never named. The panel's footer
                                     * has said since it shipped that a
                                     * claim is stored against an
                                     * occurrence and not against the
                                     * value; on a value with 23 of them
                                     * this is the only place that says
                                     * which one.
                                     */
                                    ?>
                                    <?= $tipHead(__('Written against')) ?>
                                    <?= $tipRaw(
                                        __('Occurrence'),
                                        '<a class="vp-claim-link" href="'
                                        . h($againstUrl) . '">'
                                        . h(sprintf(
                                            __('%1$s · #%2$s in event #%3$s'),
                                            $against['type'],
                                            $against['id'],
                                            $against['event_id']
                                        )) . '</a>'
                                    ) ?>
                                    <?= $tipRow(__('UUID'),
                                        $against['uuid'], 'font-monospace') ?>

                                    <?= $tipHead(sprintf(
                                        __('Points at · %s'),
                                        isset($kindWords[$target['kind']])
                                            ? $kindWords[$target['kind']]
                                            : $target['kind']
                                    )) ?>
                                    <?php
                                    /*
                                     * An unresolved target's label *is*
                                     * its UUID, so naming it twice
                                     * would fill two rows with one
                                     * fact. It gets the UUID row and
                                     * the reason, and nothing else here
                                     * is known.
                                     */
                                    ?>
                                    <?php if ($target['resolved']): ?>
                                        <?= $tipRaw(
                                            __('Target'),
                                            $url === null
                                                ? h($target['label'])
                                                : '<a class="vp-claim-link"'
                                                    . ' href="' . h($url)
                                                    . '">'
                                                    . h($target['label'])
                                                    . '</a>'
                                        ) ?>
                                        <?php foreach ($target['detail']
                                            as $label => $value): ?>
                                            <?= $tipRow($label, $value) ?>
                                        <?php endforeach; ?>
                                        <?php if ($target['distribution']
                                            !== null): ?>
                                            <?= $tipRaw(
                                                __('Audience'),
                                                $this->element(
                                                    'genericElementsBS5'
                                                        . '/Badges'
                                                        . '/distribution',
                                                    array(
                                                        'distribution' =>
                                                            $target[
                                                            'distribution'],
                                                        'full' => true,
                                                    )
                                                )
                                            ) ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?= $tipRow(__('Status'),
                                            __('not held here, or not'
                                                . ' visible to you')) ?>
                                    <?php endif; ?>
                                    <?= $tipRow(__('UUID'),
                                        $target['uuid'], 'font-monospace') ?>

                                </div>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="px-3 pb-3 d-none" data-vp-list-empty>
            <div class="vp-empty vp-empty-inline">
                <i class="fas fa-filter"></i>
                <span>
                    <?= __('Nobody has asserted a relationship of that'
                        . ' type for this value.') ?>
                </span>
            </div>
        </div>

        <div class="px-3 pb-3 d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-outline-primary
                                         disabled"
                    title="<?= h($noWrites) ?>">
                <i class="fas fa-plus me-1"></i>
                <?= __('Add a relationship') ?>
            </button>
            <span class="small text-muted vp-min-w-0">
                <?= h(sprintf(
                    __(
                        'Claims are stored against an occurrence, not'
                        . ' against the value — this list is the union'
                        . ' over the %d occurrences, in both directions,'
                        . ' de-duplicated by relationship UUID.'
                    ),
                    $asserted['occurrences']
                )) ?>
                <?php if (!empty($asserted['capped'])): ?>
                    <?php
                    /*
                     * The cap is on the *lookup*, not on the list. A
                     * claim written against an occurrence outside the
                     * most recent N would not be found, and a section
                     * whose whole argument is *never truncated* has to
                     * name the one place it can still miss something.
                     */
                    ?>
                    <?= h(sprintf(
                        __(
                            'Those are the %d most recent; a claim'
                            . ' written against an older occurrence of'
                            . ' this value is not looked up.'
                        ),
                        $asserted['occurrences']
                    )) ?>
                <?php endif; ?>
                <?php if (!empty($asserted['prose_absent'])): ?>
                    <?php
                    /*
                     * Said once here rather than as a placeholder on
                     * every block. `relationships` has no free-text
                     * column at all — `notes.note` and
                     * `opinions.comment` beside it do — so a claim's
                     * prose is not missing data, it is data MISP does
                     * not model.
                     */
                    ?>
                    <?= sprintf(
                        __(
                            'A relationship carries no text of its own:'
                            . ' %s has the type, the two ends, an author'
                            . ' and a distribution, and no prose column.'
                            . ' Anything written *about* one is a Note'
                            . ' attached to it, which this pass does not'
                            . ' fetch.'
                        ),
                        '<span class="font-monospace">relationships</span>'
                    ) ?>
                <?php endif; ?>
            </span>
        </div>

    <?php endif; ?>

    <?php
    /*
     * §14.6, applied here by phase 24. This carried a `.vp-acl-note`
     * counting the claims held at a distribution the reader is outside
     * of — *"their existence is counted; their text and their target
     * are not shown"*. Counting the existence is exactly the disclosure
     * §14.6 forbids, on a page whose URL takes any value the reader
     * types, so the band is gone and the list simply is what the reader
     * may see.
     */
    ?>

</div>
