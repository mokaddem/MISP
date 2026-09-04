<?php
/**
 * Value Profile — the page whose subject is one value rather than one
 * event. Skeleton pass: real routing, real chrome, hardcoded data.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
App::uses('ValueDisposition', 'Tools');

/*
 * Chart.js is loaded once here rather than per fragment: several panels
 * draw curves, and a lazily-injected fragment cannot be trusted to be
 * the first one to need it. The panels poll for the global instead of
 * assuming it has arrived.
 */
echo $this->element('genericElements/assetLoader', array(
    'css' => array('value-profile'),
    'js' => array('Chart.min', 'value-profile'),
));

$profile = $valueProfile;

/*
 * ------------------------------------------------------------------
 * Banner
 * ------------------------------------------------------------------
 * The value itself, monospace and selectable, followed by one chip per
 * MISP type it appears as. Values are stored refanged, so there is no
 * defanged form to toggle to.
 */
$titleHtml = '<span class="vp-value font-monospace user-select-all">'
    . h($profile['value']) . '</span>';

/*
 * A button, not a label: pressing one narrows the occurrence table to
 * that type, and pressing it again lets it go. The slug is what the
 * table's rows carry, since a MISP type can hold characters a class
 * name cannot — `domain|ip`.
 */
foreach ($profile['types'] as $type) {
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($type['type']));
    $titleHtml .= '<button type="button" class="vp-type-chip"'
        . ' data-vp-type="' . h($type['type']) . '"'
        . ' data-vp-type-slug="' . h($slug) . '"'
        . ' aria-pressed="false" title="'
        . h(sprintf(
            __('%s — show only these rows in the occurrence table'),
            __n(
                '%1$s occurrence of type %2$s',
                '%1$s occurrences of type %2$s',
                $type['count'],
                $type['count'],
                $type['type']
            )
        )) . '">'
        . '<span class="vp-type-chip-name">' . h($type['type']) . '</span>'
        . '<span class="vp-type-chip-count">' . h($type['count'])
        . '</span></button>';
}

foreach ($profile['warninglists'] as $warninglist) {
    $titleHtml .= '<span class="vp-warninglist-chip" title="'
        . h($warninglist['name']) . '">'
        . '<i class="fas fa-exclamation-triangle"></i>'
        . h(__('Warninglist hit')) . '</span>';
}

$description = null;
if (!empty($profile['value2_note'])) {
    $description = h($profile['value2_note']);
}

/*
 * ------------------------------------------------------------------
 * Header actions
 * ------------------------------------------------------------------
 * Everything on this page that would write is rendered visibly disabled
 * rather than silently dead. Grouping is switched off so each control
 * keeps its own button: with five of them, folding the writes behind a
 * caret would hide what the page deliberately shows as unavailable.
 */
$noWrites = __(
    'Disabled in this pass — the Value Profile page does not write to'
    . ' the database yet.'
);

$exportTargets = array(
    array('label' => __('STIX 2.1'), 'icon' => 'shield-halved'),
    array('label' => __('Suricata'), 'icon' => 'shield'),
    array('label' => __('Zeek'), 'icon' => 'network-wired'),
    array('label' => __('RPZ'), 'icon' => 'ban'),
    array('label' => __('CSV'), 'icon' => 'file-csv'),
    array('type' => 'divider'),
    array('label' => __('Copy the restSearch query'), 'icon' => 'code'),
);
foreach ($exportTargets as &$target) {
    if (!empty($target['type'])) {
        continue;
    }
    $target['class'] = 'disabled';
    $target['title'] = $noWrites;
}
unset($target);

$headerActions = array(
    array(
        'type' => 'navigate',
        'label' => __('Add sighting'),
        'icon' => 'eye',
        'class' => 'btn btn-primary disabled',
        'title' => $noWrites,
    ),
    array(
        'type' => 'navigate',
        'label' => __('Enrich'),
        'icon' => 'wand-magic-sparkles',
        'class' => 'btn btn-outline-primary disabled',
        'title' => $noWrites,
    ),
    array(
        'type' => 'navigate',
        'label' => __('Add to collection'),
        'icon' => 'folder-plus',
        'class' => 'btn btn-outline-dark disabled',
        'title' => $noWrites,
    ),
    array(
        'type' => 'navigate',
        'label' => __('Watch'),
        'icon' => 'bell',
        'class' => 'btn btn-outline-dark disabled',
        'title' => $noWrites,
    ),
    array(
        'type' => 'dropdown',
        'label' => __('Export'),
        'icon' => 'file-export',
        'class' => 'btn btn-outline-dark',
        'children' => $exportTargets,
    ),
);

$this->set('headerTitleHtml', $titleHtml);
$this->set('headerBreadcrumb', __('Data points') . ' > ' . __('Value Profile'));
$this->set('headerDescription', $description);
$this->set('headerActions', $headerActions);
$this->set('headerActionGroups', array('navigate' => array('mode' => 'none')));

echo $this->element('Values/View/value_fact_strip', array(
    'facts' => $profile['facts'],
));

echo $this->element('Values/View/value_pivot_rail', array(
    'pivots' => $profile['pivots'],
));

/*
 * ------------------------------------------------------------------
 * Tabs
 * ------------------------------------------------------------------
 * Every tab is assembled from lazily-loaded panels, one endpoint each,
 * so a slow panel never holds up the rest of the page and each one's
 * live implementation stays a local change. None is stubbed any more;
 * the whole-tab placeholder below stays because a tab that names no
 * panel should still say so rather than render an empty column.
 */
$counts = $profile['counts'];

/*
 * ------------------------------------------------------------------
 * What each endpoint's panels look like before they arrive
 * ------------------------------------------------------------------
 * Every panel below is fetched after the page paints, and the layout
 * used to hold nothing for it but a centred spinner — so a tab opened
 * as a column of 60px spinners and grew to its real height one fetch at
 * a time. A reader could not tell which section was which, could not
 * reach the one they came for, and watched the page move under the
 * cursor until the slowest endpoint answered.
 *
 * This table is the shape of the answer, stated before it comes:
 * `value_panel_loading` draws the real card chrome from it, so the
 * structure and the section names are there from the first paint.
 *
 * **It repeats each panel's title, and that is the cost of the move.**
 * The titles live in the panel templates, which is where they have to
 * live — a panel composes its own subtitle out of its own numbers — so
 * a name changed there and not here shows the old one for as long as
 * the fetch takes. The alternative is a spinner that says nothing at
 * all, and a wrong name for 400ms is cheaper than no name at all.
 *
 * `lines` is how many body bars to shimmer, and it is a guess at the
 * panel's height rather than a claim about its contents. Guessed low on
 * purpose: the panel that lands is then taller than its skeleton, so
 * the page grows rather than shrinking, which is the direction that
 * does not pull a section out from under a reader who has scrolled to
 * it.
 */
$icoAttr = 'misp-icon misp-icon-attribute misp-simple';
$icoNote = 'misp-icon misp-icon-analyst-note misp-simple';
$icoSight = 'misp-icon misp-icon-sighting misp-simple';
$icoTag = 'misp-icon misp-icon-tag misp-simple';
$icoObj = 'misp-icon misp-icon-object misp-simple';
$icoGalaxy = 'misp-icon misp-icon-galaxy misp-simple';
$icoOrg = 'misp-icon misp-icon-organisation misp-simple';

/**
 * One card of chrome.
 *
 * @param string|null $title Null only on an `aside`, whose title this
 *                           page cannot know — see the element
 * @param string|null $icon
 * @param string $color
 * @param int $lines
 * @param array $extra `shape` and `col`, for the two endpoints that
 *                     own their own split and the rail's lighter card
 * @return array
 */
$await = function ($title, $icon, $color, $lines = 3,
    array $extra = array()
) {
    return $extra + array(
        'title' => $title,
        'icon' => $icon,
        'color' => $color,
        'lines' => $lines,
    );
};

$aside = array('shape' => 'aside');

$panelChrome = array(
    'viewOccurrences' => array(
        $await(__('Occurrences'), $icoAttr, 'var(--attribute)', 6),
    ),
    'viewContext' => array(
        $await(__('Tags and galaxies'), $icoTag, 'var(--tag)', 4),
    ),
    'viewAnalystPreview' => array(
        $await(__('Analyst data'), $icoNote, 'var(--analystData)'),
    ),
    'viewVerdictCard' => array(
        $await(__('Verdict'), 'fas fa-gavel', 'var(--primary)', 4),
    ),
    'viewSightings' => array(
        $await(__('Sightings'), $icoSight, 'var(--sighting)'),
    ),
    'viewLifecycle' => array(
        $await(__('Lifecycle'), 'fas fa-hourglass-half',
            'var(--correlation)'),
    ),
    'viewExternal' => array(
        $await(__('External presence'), 'fas fa-cloud-arrow-down',
            'var(--enrichment)'),
    ),
    'viewVerdict' => array(
        $await(__('Verdict'), 'fas fa-gavel', 'var(--primary)', 10),
    ),
    /*
     * The rail is four cards or five and each one's title with it, so
     * the skeleton holds three unnamed ones: the shape is honest, the
     * names would be guesses.
     */
    'viewVerdictAside' => array(
        $await(null, null, 'var(--primary)', 4, $aside),
        $await(null, null, 'var(--primary)', 4, $aside),
        $await(null, null, 'var(--primary)', 3, $aside),
    ),
    'viewOccurrenceTable' => array(
        $await(__('Filters'), 'fas fa-filter', 'var(--attribute)', 5,
            array('col' => 3)),
        $await(__('Occurrences'), $icoAttr, 'var(--attribute)', 8,
            array('col' => 9)),
    ),
    'viewSightingChart' => array(
        $await(__('Sightings over time'), $icoSight, 'var(--sighting)',
            6),
    ),
    'viewSightingList' => array(
        $await(__('Individual sightings'), $icoSight, 'var(--sighting)',
            6),
    ),
    'viewSightingDecay' => array(
        $await(__('Decay models'), 'fas fa-hourglass-half',
            'var(--correlation)', 3, $aside),
    ),
    'viewSightingReporters' => array(
        $await(__('Reporters'), $icoOrg, 'var(--sighting)', 3, $aside),
    ),
    'viewSightingAdd' => array(
        $await(__('Report a sighting'), $icoSight, 'var(--sighting)', 3,
            $aside),
    ),
    /*
     * Three panels out of one endpoint, in the order it draws them.
     * Two of the three are conditional on the value having such rows —
     * but the contents strip above already lists all three whatever the
     * value turns out to hold, so a skeleton that listed fewer would
     * disagree with the page's own table of contents.
     */
    'viewRelationCooccurrence' => array(
        $await(__('In the same object'), $icoObj, 'var(--vp-rel-co)', 6),
        $await(__('In the same events'), 'fas fa-link',
            'var(--vp-rel-co)', 8),
        $await(__('Labels in this neighbourhood'), $icoGalaxy,
            'var(--vp-rel-co)', 5),
    ),
    'viewRelationDated' => array(
        $await(__('Dated relations'), 'fas fa-clock-rotate-left',
            'var(--vp-rel-object)', 6),
    ),
    'viewRelationNearMatch' => array(
        $await(__('Near-matches'), 'fas fa-code-compare',
            'var(--vp-rel-near)', 5),
    ),
    'viewRelationExternal' => array(
        $await(__('Outside this instance'), 'fas fa-cloud-arrow-down',
            'var(--vp-rel-external)', 4),
    ),
    'viewRelationReferences' => array(
        $await(__('Object relationships'), 'fas fa-diagram-project',
            'var(--vp-rel-reference)', 5),
    ),
    'viewRelationAsserted' => array(
        $await(__('Asserted by analysts'), $icoNote,
            'var(--vp-rel-human)', 4),
    ),
    'viewRelationGraph' => array(
        $await(__('Neighbourhood'), 'fas fa-circle-nodes',
            'var(--bs-secondary-color)', 5),
    ),
    'viewRelationThreats' => array(
        $await(__('Named threats in this neighbourhood'), $icoGalaxy,
            'var(--bs-secondary-color)', 4),
    ),
    'viewRelationSettings' => array(
        $await(__('What is counted'), 'fas fa-sliders',
            'var(--bs-secondary-color)', 4),
    ),
    'viewEnrichment' => array(
        $await(__('Enrichment'), 'fas fa-wand-magic-sparkles',
            'var(--vp-e-accent)', 8),
    ),
    'viewAnalystStanding' => array(
        $await(__('Where the organisations stand'),
            'fas fa-arrows-left-right-to-line', 'var(--analystData)', 6),
    ),
    'viewAnalystThread' => array(
        $await(__('Notes and opinions'), $icoNote, 'var(--analystData)',
            6),
    ),
    'viewTimeline' => array(
        $await(__('Timeline'), 'fas fa-clock', 'var(--bs-info)', 8),
    ),
    'viewHistory' => array(
        $await(__('Filters'), 'fas fa-filter',
            'var(--bs-secondary-color)', 5, array('col' => 3)),
        $await(__('History'), 'fas fa-history',
            'var(--bs-secondary-color)', 8, array('col' => 9)),
    ),
);

/**
 * The optional anchor is the panel's *container*, not its card: it is
 * put on the empty div that holds the spinner, so a link can reach the
 * section before the fetch that fills it has come back. Only the
 * Relationships tab asks for one, because only it has a contents strip
 * pointing at its own sections.
 *
 * @param string $action
 * @param string $anchor
 * @return array A view_layout card pointing at one panel endpoint
 */
$panel = function ($action, $anchor = null) use ($baseurl, $valueB64,
    $panelChrome
) {
    $card = array(
        'ajax' => $baseurl . '/values/' . $action . '/' . h($valueB64),
    );
    if ($anchor !== null) {
        $card['id'] = $anchor;
    }
    if (isset($panelChrome[$action])) {
        $card['placeholder'] = array(
            'element' => 'Values/View/value_panel_loading',
            'params' => array('panels' => $panelChrome[$action]),
        );
    }
    return $card;
};

/*
 * The Verdict tab's state pill. A verdict is a state, not a count, so it
 * gets a badge rather than the parenthesised number the other tabs use.
 * The colour names the disposition; the label carries the score when
 * there is one to carry.
 */
$verdict = $profile['verdict'];
$verdictBadge = array(
    'label' => $verdict['score'] === null
        ? $verdict['disposition']
        : $verdict['disposition'] . ' ' . $verdict['score'],
    'color' => ValueDisposition::colour($verdict['disposition']),
    'dot' => true,
);

/*
 * An UNKNOWN value has nothing for the Verdict rail — no score to
 * compose, no model to decay, no warninglist hit to explain — so that
 * tab keeps the full width rather than reserving a column for cards
 * that would each render their own nothing. `value_verdict_aside`
 * holds the matching decision about which cards apply.
 */
$hasVerdictAside = $verdict['disposition'] !== 'UNKNOWN';

/*
 * The Relationships pill: one notion, named, and only when it is there.
 *
 * A pill rather than the parenthesised count because `(15)` on this tab
 * would read as fifteen *relationships* — the claim that got the
 * fixture's correlation badge removed in phase 24. The label carries
 * the unit, so the number says what it counts, and it counts the notion
 * the tab is founded on rather than a total across seven of them.
 *
 * Null at zero: a value in no object can still hold a claim, a
 * near-match or a remote hit, so *0 objects* would answer *is this
 * worth opening* wrongly. No pill means what it means on Sightings and
 * Timeline — no number can be told truly — and never *nothing here*.
 * `ValueProfile::forTabCounts` has the cost and the rest of the case.
 */
$relationshipObjects = (int)($counts['relationship_objects'] ?? 0);
$relationshipBadge = $relationshipObjects === 0 ? null : array(
    'label' => sprintf(
        __n('%s object', '%s objects', $relationshipObjects),
        number_format($relationshipObjects)
    ),
    'color' => 'var(--vp-rel-object)',
);

$tabRegistry = array(
    array(
        'id' => 'general',
        'title' => __('Overview'),
        'icon' => 'fas fa-info-circle',
        'left' => array(
            $panel('viewOccurrences'),
            $panel('viewContext'),
            $panel('viewAnalystPreview'),
        ),
        'right' => array(
            $panel('viewVerdictCard'),
            $panel('viewSightings'),
            $panel('viewLifecycle'),
            $panel('viewExternal'),
        ),
    ),
    array(
        'id' => 'verdict',
        'title' => __('Verdict'),
        'icon' => 'fas fa-gavel',
        'badge' => $verdictBadge,
        /*
         * The same 9/3 split as the Overview tab: the evidence — the
         * ledger grid, the occurrence tables, the two opposed cases —
         * needs the width, while the summaries and reference facts read
         * better beside it than under it.
         */
        'left' => array(
            $panel('viewVerdict'),
        ),
        'right' => $hasVerdictAside
            ? array($panel('viewVerdictAside'))
            : null,
    ),
    array(
        'id' => 'occurrences',
        'title' => __('Occurrences'),
        'icon' => 'misp-icon misp-icon-attribute misp-simple',
        /*
         * The viewer's own count, off the same aggregate the tab's
         * header uses, so the badge and the panel cannot disagree —
         * `ValueProfile::forTabCounts`. Every other badge on this bar
         * is still the fixture's.
         */
        'count' => $counts['occurrences'],
        /*
         * One full-width slot, and the panel lays out its own internal
         * row: the rail on the left at col-lg-3, the table at col-lg-9,
         * the reverse of this page's usual split. Both come out of one
         * fetch, which is why they are one panel — a facet count and
         * the rows it counts must not be able to disagree.
         */
        'left' => array(
            $panel('viewOccurrenceTable'),
        ),
        'right' => null,
    ),
    array(
        'id' => 'sightings',
        'title' => __('Sightings'),
        'icon' => 'misp-icon misp-icon-sighting misp-simple',
        /*
         * No count, for the Timeline tab's reason and one of its own.
         * A sighting count is the *viewer's* — `Sightings_policy` hides
         * whole reports — and getting it costs the panel's own thirteen
         * queries, on every page load, for a tab most readers never
         * open. It carried a fixture literal until 2026-08-28, which
         * read 17 beside a panel reporting 53.
         *
         * `ValueProfile::forTabCounts` holds the reasoning and the
         * condition for putting a number back.
         *
         * The page's usual 9/3 split. The overlay is the tab, and it
         * needs the width: bars stacked by organisation under two decay
         * curves on their own axis is not a chart that survives being
         * put in a card beside something else.
         */
        'left' => array(
            $panel('viewSightingChart'),
            $panel('viewSightingList'),
        ),
        'right' => array(
            $panel('viewSightingDecay'),
            $panel('viewSightingReporters'),
            $panel('viewSightingAdd'),
        ),
    ),
    array(
        'id' => 'relationships',
        'title' => __('Relationships'),
        'icon' => 'fas fa-link',
        /*
         * A pill naming objects, not the parenthesised count. This tab
         * read the fixture's correlation total until phase 24, which is
         * a number nothing on the live tab computes: co-occurrence here
         * is an event join, not correlation output
         * (`24-relationships.md` §3). That join's own total is still
         * refused — it means running the panel's whole scan, up to
         * 20,000 attribute rows and a second on the heaviest value, on
         * every page load for a tab most readers never open.
         *
         * What the pill carries instead is the notion §26 re-founded
         * the tab on, off one indexed aggregate, and it is the same
         * number the sibling panel and the graph's object layer print.
         * `$relationshipBadge` above and `ValueProfile::forTabCounts`
         * hold the case, the cost and why zero shows nothing.
         */
        'badge' => $relationshipBadge,
        /*
         * Six panels rather than one, top to bottom in the order the
         * six notions should be read: what shares an event and an
         * object, when an object says that join held, what is merely
         * close, what somebody outside this instance holds it
         * alongside, and then the two a person wrote — MISP's own typed
         * reference and an analyst's claim. Machine-derived before
         * human, which is what the separation rule encodes.
         *
         * **Dated relations sits second**, directly under the section
         * whose rows it dates: it is the object join again, with the
         * one thing that join records and the table above has no column
         * for. Splitting it out rather than adding two columns is
         * `03-relationships.md` §23.5 — most object joins carry no date
         * at all, and two empty columns on every row would be a worse
         * answer than a panel that says so once.
         *
         * They are separate endpoints because they cost wildly
         * different amounts — the first reads up to 20,000 rows and the
         * references a handful — and one slow scan must not hold up the
         * two sections a person wrote by hand.
         */
        'left' => array(
            /*
             * A contents strip over the six endpoints, and the only
             * thing on this tab that renders before them: seven cards
             * — the co-occurrence endpoint draws two sections — each
             * holding one section's headline number and jumping to it.
             * It is markup only. The numbers arrive from the panels
             * themselves as they land, because computing any of them
             * here would mean running the scan the tab is lazily
             * loaded to avoid.
             */
            array('element' => 'Values/View/value_relation_summary'),
            $panel('viewRelationCooccurrence', 'vp-rel-sec-cooccurrence'),
            $panel('viewRelationDated', 'vp-rel-sec-dated'),
            $panel('viewRelationNearMatch', 'vp-rel-sec-near'),
            $panel('viewRelationExternal', 'vp-rel-sec-external'),
            $panel('viewRelationReferences', 'vp-rel-sec-references'),
            $panel('viewRelationAsserted', 'vp-rel-sec-asserted'),
        ),
        /*
         * Shape, then names, then bookkeeping. The graph says how many
         * blobs the neighbourhood has and how big they are; the card
         * under it says who is in them; the settings card is the
         * bookkeeping and stays last.
         *
         * The named-threat card is the only thing on this tab that
         * answers *what does this mean* rather than *what is related*,
         * and it is in the rail because that is where the headroom is
         * — the tab measured ~5,700px against a rail of ~1,700px on
         * `8.8.8.8`. Not an eighth card on the contents strip: each of
         * those carries one section's headline number and jumps to it,
         * and this has no left-column section to jump to.
         */
        'right' => array(
            $panel('viewRelationGraph'),
            $panel('viewRelationThreats'),
            $panel('viewRelationSettings'),
        ),
    ),
    array(
        'id' => 'enrichment',
        'title' => __('Enrichment'),
        'icon' => 'fas fa-wand-magic-sparkles',
        'count' => $counts['enrichment'],
        /*
         * One full-width slot, and the panel owns its own split: the
         * module rail at ~40% and one module's results beside it. Not
         * this page's 9/3, because the rail scrolls independently of
         * the pane once there are more modules than fit — thirty is
         * as ordinary a number here as three.
         */
        'left' => array(
            $panel('viewEnrichment'),
        ),
        'right' => null,
    ),
    array(
        'id' => 'analyst',
        'title' => __('Analyst data'),
        'icon' => 'misp-icon misp-icon-analyst-note misp-simple',
        'count' => $counts['analyst'],
        /*
         * One full-width slot, and the standing panel lays out its own
         * internal row: the histogram at col-lg-4 beside the
         * per-organisation table at col-lg-8. Not this page's 9/3,
         * because the position strip spans the whole panel above both
         * of them — it is the tab's opening claim and a rail would cut
         * a hundred-point scale down to a quarter of the page.
         */
        'left' => array(
            $panel('viewAnalystStanding'),
            $panel('viewAnalystThread'),
        ),
        'right' => null,
    ),
    array(
        'id' => 'timeline',
        'title' => __('Timeline'),
        'icon' => 'fas fa-clock',
        /*
         * No count. "67 dated entries" is the *viewer's* count —
         * `Sightings_policy` can hide whole sightings and
         * `Sightings_anonymise` refiles foreign orgs — so two users
         * would read two numbers off one value's tab bar.
         *
         * One full-width slot, and the panel owns its own three cards.
         * Not this page's 9/3 and not three panels: the brush is one
         * control driving two regions that must already exist when it
         * fires, and three `.ajax-card`s resolve independently.
         */
        'left' => array(
            $panel('viewTimeline'),
        ),
        'right' => null,
    ),
    array(
        'id' => 'history',
        'title' => __('History'),
        'icon' => 'fas fa-history',
        /*
         * No count, and for a stronger reason than the Timeline tab's.
         * An audit-entry count is the *viewer's*: a plain analyst, an
         * org admin and a site admin get three different numbers for
         * one value. And on a default instance `MISP.log_new_audit` is
         * off, so it is 0 for a reason that has nothing to do with the
         * value. A number meaning three things and usually zero is
         * worse than no number.
         *
         * One full-width slot, and the panel owns its rail: the facet
         * control binds checkboxes to rows through the nearest
         * `data-vp-list` ancestor, so the two cannot be separate cards.
         */
        'left' => array(
            $panel('viewHistory'),
        ),
        'right' => null,
    ),
);

/*
 * A tab that names no panels is one nobody has written yet, and says so
 * with the whole-tab placeholder rather than an empty column.
 */
$tabs = array();
foreach ($tabRegistry as $tab) {
    $tabs[] = array(
        'id' => $tab['id'],
        'title' => $tab['title'],
        'icon' => $tab['icon'],
        'count' => $tab['count'] ?? null,
        'badge' => $tab['badge'] ?? null,
        'right' => $tab['right'] ?? null,
        'left' => $tab['left'] ?? array(
            array(
                'element' => 'Values/View/value_placeholder',
                'params' => array(
                    'tabTitle' => $tab['title'],
                    'tabIcon' => $tab['icon'],
                    'tabNote' => $tab['note'],
                ),
            ),
        ),
    );
}

echo $this->element('genericElementsBS5/Layout/view_layout', array(
    'data' => $profile,
    'tabs' => $tabs,
));
