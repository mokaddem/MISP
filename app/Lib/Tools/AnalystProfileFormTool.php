<?php

App::uses('ValueSignalLoader', 'Tools');
App::uses('ValueExclusionTool', 'Tools');
App::uses('ValueEnrichmentTool', 'Tools');
App::uses('ValueRelevanceTool', 'Tools');
App::uses('ValueTrustTool', 'Tools');
App::uses('ValueVerdictTool', 'Tools');
App::uses('ModuleLocality', 'Tools');
App::uses('WarninglistCategory', 'Tools');

/**
 * The editor's mechanics: what each section of a profile looks like as a
 * form, what a posted form does to the document, and which edits are
 * refusable before they are saved.
 *
 * prd/analyst-profile/09-editor.md, phase 8a. **This file renders
 * nothing.** It produces a *view-model* — a description of the document
 * in blocks and fields — and three deliberately different designs
 * (phase 8b) draw the same view-model three ways. The seam exists
 * because two of those three designs get thrown away and the mechanics
 * should not go with them.
 *
 * ## Four block kinds, and no fifth
 *
 * A section is a list of blocks; a block is one of four shapes. D2 says
 * the sections have genuinely different shapes and deserve different
 * treatments, and this is that statement made mechanical — a template
 * that can render four block kinds can render all seven sections,
 * including a section a later phase adds.
 *
 * - **`fields`** — labelled scalars. `thresholds`' supermajority,
 *   `relevance`'s clock, `enrichment`'s posture.
 * - **`map`** — key→value pairs with an *add* affordance and a named
 *   source for the keys. `relevance.ttl_days`, `reference.org_trust`,
 *   `enrichment.locality`. Never a row per candidate key: the source is
 *   what the picker searches, not what the table lists (§4).
 * - **`items`** — a list of togglable entries, each with its own fields.
 *   `signals`, `escalations`, `exclusions`. An item carries its state as
 *   well as its values, because a profile may name a signal this
 *   instance does not have and the palette has to say so rather than
 *   drop it (`03-signals.md` §4.4).
 * - **`strip`** — the quality bands against the attainable bound. One
 *   block, one section, and it exists because a boundary is only wrong
 *   *relative to* something and the something has to be drawn.
 *
 * ## A field addresses itself by path, and the form posts nested names
 *
 * Every field carries `path` — its segments from the root of
 * `parameters`. A template turns that into
 * `data[AnalystProfile][parameters][signals][reporting.independent_orgs][points][per_org]`,
 * which PHP parses back into the same nested array, and `merge()` needs
 * no per-section code to apply it.
 *
 * Nested bracket names rather than a dot-joined string, because the keys
 * are not safe for a separator: a signal id contains dots
 * (`reporting.independent_orgs`), an attribute type contains a pipe
 * (`domain|ip`), an organisation key is a uuid. PHP replaces `.` only in
 * the part of a parameter name before the first bracket, so keys inside
 * brackets survive verbatim — checked rather than assumed.
 *
 * ## The lists are addressed by id and stored as lists
 *
 * `signals`, `escalations` and `exclusions` are JSON *arrays* in the
 * document and *maps keyed by id* in the form, because a position is
 * not a stable address — a save that renumbered would rewrite the entry
 * the analyst was not looking at. `merge()` is where the two
 * representations meet, and it is the one place that knows the document
 * is a list.
 */
class AnalystProfileFormTool
{
    /**
     * The sections, in the order the editor presents them: what the
     * assessment is computed *from* first, then what it is computed
     * *against*, then the two that feed the other axes.
     *
     * `format` is not here. It is the document's version marker, not a
     * judgement, and an editor offering to change it offers to make the
     * document unreadable.
     */
    const SECTION_ORDER = array(
        'signals',
        'thresholds',
        'escalations',
        'exclusions',
        'relevance',
        'reference',
        'enrichment',
    );

    /** The editorial bands a profile may put a signal in (D14). */
    const BANDS = array('strong', 'moderate', 'weak');

    /** The clocks `relevance` may be told to read (`06-staleness.md`). */
    const CLOCKS = array(
        'last_independent_corroboration',
        'newest_occurrence',
    );

    /** How a per-type TTL table resolves for a multi-type value. */
    const TYPE_RULES = array('shortest', 'longest', 'first');

    /**
     * The whole view-model for `view` and `edit`.
     *
     * @param array $parameters The profile's decoded `parameters`
     * @param array $sources Option lists the tool may not fetch for
     *                       itself: `attribute_types`, `orgs`,
     *                       `warninglists`, `modules`, plus `ledger`
     *                       and `not_counted` when a value is in play
     * @return array Section id => section
     */
    public function sections(array $parameters, array $sources = array())
    {
        $sections = array();
        foreach (self::SECTION_ORDER as $id) {
            $method = 'section' . ucfirst($id);
            $sections[$id] = $this->$method($parameters, $sources);
        }
        return $sections;
    }

    /**
     * `signals` — one item per signal, and the points columns differ per
     * row because `points` has no fixed schema by design.
     *
     * The palette is the union of two sets, which is the whole of D12
     * made visible: everything the loader discovered, plus everything
     * the profile names. A signal in the first set and not the second is
     * *available* — droppable in, not yet active. One in the second and
     * not the first is *missing* — this instance cannot compute it, the
     * engine already says so in `not_counted`, and an editor that
     * quietly dropped the entry would lose an analyst's configuration
     * for a signal a redeploy would bring back.
     *
     * @param array $parameters
     * @param array $sources
     * @return array
     */
    private function sectionSignals(array $parameters, array $sources)
    {
        return array(
            'id' => 'signals',
            'title' => __('Signals'),
            'blurb' => __(
                'What each kind of evidence is worth. The contributions'
                . ' sum to the quality exactly — nothing is normalised,'
                . ' so the ledger is the number.'
            ),
            'blocks' => array(
                array(
                    'kind' => 'items',
                    'id' => 'signals',
                    'path' => array('signals'),
                    'items' => $this->signalPalette($parameters, $sources),
                    'groups' => $this->signalGroups($parameters),
                ),
            ),
        );
    }

    /**
     * Every signal this instance has or this profile names.
     *
     * @param array $parameters
     * @param array $sources
     * @return array
     */
    public function signalPalette(array $parameters, array $sources = array())
    {
        $catalogue = ValueSignalLoader::catalogue(
            ValueSignalLoader::SUBJECT_SIGNAL
        );
        $entries = $this->entriesById($parameters, 'signals');
        $ledger = isset($sources['ledger']) ? $sources['ledger'] : array();
        $notCounted = isset($sources['not_counted'])
            ? $sources['not_counted']
            : array();

        $items = array();
        foreach ($catalogue as $id => $config) {
            $items[$id] = $this->signalItem($id, $config,
                isset($entries[$id]) ? $entries[$id] : null,
                $ledger, $notCounted);
        }
        foreach ($entries as $id => $entry) {
            if (isset($items[$id])) {
                continue;
            }
            $items[$id] = $this->signalItem($id, null, $entry,
                $ledger, $notCounted);
        }
        return $this->orderByGroup($items);
    }

    /**
     * The palette in the ledger's own order — the four groups as
     * `ValueVerdictTool` renders them, then any group a custom signal
     * named, and inside a group by id.
     *
     * The loader's order is the filesystem's, which is alphabetical by
     * filename and puts `attribution.galaxy` above
     * `reporting.independent_orgs` for no reason a reader can see. An
     * analyst checking whether the sightings were counted twice wants
     * them next to each other, which is the same argument the ledger's
     * own grouping already makes.
     *
     * @param array $items id => item
     * @return array
     */
    private function orderByGroup(array $items)
    {
        $groups = array();
        foreach ($this->baseGroups() as $group) {
            $groups[$group] = array();
        }
        foreach ($items as $id => $item) {
            $group = $item['group'] === null ? '' : $item['group'];
            if (!isset($groups[$group])) {
                $groups[$group] = array();
            }
            $groups[$group][$id] = $item;
        }
        $out = array();
        foreach ($groups as $group => $members) {
            ksort($members);
            foreach ($members as $item) {
                $out[] = $item;
            }
        }
        return $out;
    }

    /**
     * @param string $id
     * @param array|null $config From the loader; null when the instance
     *                           does not have this signal
     * @param array|null $entry From the profile; null when the profile
     *                          does not carry it
     * @param array $ledger id => contribution
     * @param array $notCounted id => note
     * @return array
     */
    private function signalItem($id, $config, $entry, array $ledger,
        array $notCounted
    ) {
        $inProfile = $entry !== null;
        $enabled = $inProfile
            && (!array_key_exists('enabled', $entry)
                || !empty($entry['enabled']));
        if ($config === null) {
            $state = 'missing';
        } elseif (!$inProfile) {
            $state = 'available';
        } else {
            $state = 'active';
        }
        $badges = array();
        if ($config !== null && !empty($config['is_custom'])) {
            $badges[] = array(
                'id' => 'custom',
                'label' => __('custom'),
                'title' => __(
                    'Dropped into app/Lib/ValueSignals on this instance.'
                    . ' Nothing upstream computes it, so a colleague'
                    . ' reading this profile elsewhere cannot reproduce'
                    . ' the number it contributes.'
                ),
            );
        }
        if ($state === 'missing') {
            $badges[] = array(
                'id' => 'missing',
                'label' => __('not implemented here'),
                'title' => __(
                    'This profile configures a signal this instance does'
                    . ' not have. It contributes nothing and is listed'
                    . ' in the assessment as not counted. The'
                    . ' configuration is kept, so deploying the'
                    . ' implementation brings it back.'
                ),
            );
        }
        $item = array(
            'id' => $id,
            'title' => $id,
            'description' => $config === null
                ? null
                : $config['description'],
            'state' => $state,
            'enabled' => $enabled,
            'in_profile' => $inProfile,
            'badges' => $badges,
            'group' => $entry !== null && !empty($entry['group'])
                ? $entry['group']
                : ($config === null ? null : $config['group']),
            'band' => $entry !== null && !empty($entry['band'])
                ? $entry['band']
                : ($config === null ? null : $config['default_band']),
            'evidence_class' => $config === null
                ? null
                : $config['evidence_class'],
            'source' => $config === null ? null : $config['source'],
            'trust_weighted' => $entry !== null
                && !empty($entry['trust_weighted']),
            'absence_key' => $config === null
                ? null
                : $config['absence_key'],
            'path' => array('signals', $id),
            'contribution' => array_key_exists($id, $ledger)
                ? (int)$ledger[$id]
                : null,
            'not_counted' => array_key_exists($id, $notCounted)
                ? $notCounted[$id]
                : null,
            'fields' => array(),
        );
        /*
         * The toggle and the two editorial choices come first, then the
         * generated maps. `band` is editorial and not derived (D14), so
         * it is a select over three words rather than a number the form
         * computes from the points.
         */
        $item['fields'][] = array(
            'key' => 'enabled',
            'label' => __('Enabled'),
            'type' => 'bool',
            'value' => $enabled,
            'default' => true,
            'path' => array('signals', $id, 'enabled'),
        );
        $item['fields'][] = array(
            'key' => 'band',
            'label' => __('Weight band'),
            'type' => 'select',
            'options' => self::BANDS,
            'value' => $item['band'],
            'default' => $config === null ? null : $config['default_band'],
            'help' => __(
                'What this kind of evidence is worth in principle. The'
                . ' contribution says what it produced here; the band'
                . ' says how much a reader should weigh that.'
            ),
            'path' => array('signals', $id, 'band'),
        );
        $item['fields'][] = array(
            'key' => 'group',
            'label' => __('Ledger group'),
            'type' => 'select',
            'options' => $this->groupOptions($config),
            'value' => $item['group'],
            'default' => $config === null ? null : $config['group'],
            'path' => array('signals', $id, 'group'),
        );
        foreach (array('points', 'config') as $map) {
            $schema = $config === null
                ? array()
                : $config[$map . '_schema'];
            $stored = $entry !== null && isset($entry[$map])
                && is_array($entry[$map])
                ? $entry[$map]
                : array();
            foreach ($this->generatedFields($schema, $stored,
                array('signals', $id, $map)) as $field) {
                $field['map'] = $map;
                $item['fields'][] = $field;
            }
        }
        return $item;
    }

    /**
     * The four shipped groups plus whichever fifth a custom signal named
     * — because `GROUPS` is the shipped set and a custom signal is
     * explicitly allowed to name its own.
     *
     * @param array|null $config
     * @return array
     */
    private function groupOptions($config)
    {
        $options = $this->baseGroups();
        if ($config !== null && !empty($config['group'])
            && !in_array($config['group'], $options, true)
        ) {
            $options[] = $config['group'];
        }
        return $options;
    }

    /**
     * Which groups this profile's signals actually land in, in the
     * ledger's own order, so a design can section the table the way the
     * assessment sections its ledger.
     *
     * @param array $parameters
     * @return array
     */
    private function signalGroups(array $parameters)
    {
        $catalogue = ValueSignalLoader::catalogue();
        $seen = array();
        foreach ($this->entriesById($parameters, 'signals') as $id => $entry) {
            if (!empty($entry['group'])) {
                $seen[$entry['group']] = true;
            } elseif (isset($catalogue[$id])) {
                $seen[$catalogue[$id]['group']] = true;
            }
        }
        $ordered = array();
        foreach ($this->baseGroups() as $group) {
            if (isset($seen[$group])) {
                $ordered[] = $group;
                unset($seen[$group]);
            }
        }
        return array_merge($ordered, array_keys($seen));
    }

    /**
     * `thresholds` — the lean supermajority, the band boundaries, and
     * the thin-record clamp, with the band strip beside them.
     *
     * @param array $parameters
     * @param array $sources
     * @return array
     */
    private function sectionThresholds(array $parameters, array $sources)
    {
        $section = $this->section($parameters, 'thresholds');
        $bands = isset($section['quality_bands'])
            && is_array($section['quality_bands'])
            ? $section['quality_bands']
            : array();
        $clamp = isset($section['thin_record_clamp'])
            && is_array($section['thin_record_clamp'])
            ? $section['thin_record_clamp']
            : array();
        return array(
            'id' => 'thresholds',
            'title' => __('Thresholds'),
            'blurb' => __(
                'Where the counted evidence turns into a word. The lean'
                . ' asks how one-sided the record is; the bands ask how'
                . ' much of it there is.'
            ),
            'blocks' => array(
                array(
                    'kind' => 'fields',
                    'id' => 'lean',
                    'title' => __('The lean'),
                    'fields' => array(
                        array(
                            'key' => 'lean_supermajority',
                            'label' => __('Supermajority share'),
                            'type' => 'float',
                            'value' => isset($section['lean_supermajority'])
                                ? $section['lean_supermajority']
                                : null,
                            'default' => 0.66,
                            'help' => __(
                                'The share of organisations that must'
                                . ' agree before the record is read as'
                                . ' asserting one thing. A supermajority'
                                . ' on either side, with a tolerance —'
                                . ' 34 of 100 against misses the mirror'
                                . ' of 0.66 by a floating-point hair.'
                            ),
                            'path' => array('thresholds',
                                'lean_supermajority'),
                        ),
                    ),
                ),
                array(
                    'kind' => 'strip',
                    'id' => 'quality_bands',
                ) + $this->bandStrip($parameters),
                array(
                    'kind' => 'fields',
                    'id' => 'quality',
                    'title' => __('The quality bands'),
                    'fields' => array(
                        array(
                            'key' => 'high',
                            'label' => __('High from'),
                            'type' => 'int',
                            'value' => isset($bands['high'])
                                ? $bands['high']
                                : null,
                            'default' => 60,
                            'path' => array('thresholds', 'quality_bands',
                                'high'),
                        ),
                        array(
                            'key' => 'medium',
                            'label' => __('Medium from'),
                            'type' => 'int',
                            'value' => isset($bands['medium'])
                                ? $bands['medium']
                                : null,
                            'default' => 30,
                            'path' => array('thresholds', 'quality_bands',
                                'medium'),
                        ),
                        array(
                            'key' => 'quality_high_min_signals',
                            'label' => __('Signals needed for high'),
                            'type' => 'int',
                            'value' => isset(
                                $section['quality_high_min_signals'])
                                ? $section['quality_high_min_signals']
                                : null,
                            'default' => 4,
                            'help' => __(
                                'What stops one heavy row buying a high'
                                . ' band on its own.'
                            ),
                            'path' => array('thresholds',
                                'quality_high_min_signals'),
                        ),
                    ),
                ),
                array(
                    'kind' => 'fields',
                    'id' => 'thin_record_clamp',
                    'title' => __('The thin-record clamp'),
                    'blurb' => __(
                        'A ceiling on the band for a record with one'
                        . ' source and nothing corroborating it. The'
                        . ' weights alone cannot express this: one'
                        . ' organisation reporting the same value for'
                        . ' fourteen months sums past any floor that'
                        . ' still says something useful about the values'
                        . ' that do have corroboration. Delete the three'
                        . ' numbers to remove the clamp.'
                    ),
                    'fields' => array(
                        array(
                            'key' => 'max_orgs',
                            'label' => __('Sources that still count as one'),
                            'type' => 'int',
                            'value' => isset($clamp['max_orgs'])
                                ? $clamp['max_orgs']
                                : null,
                            'default' => 1,
                            'path' => array('thresholds',
                                'thin_record_clamp', 'max_orgs'),
                        ),
                        array(
                            'key' => 'max_sightings',
                            'label' => __('Sightings that still count as none'),
                            'type' => 'int',
                            'value' => isset($clamp['max_sightings'])
                                ? $clamp['max_sightings']
                                : null,
                            'default' => 0,
                            'path' => array('thresholds',
                                'thin_record_clamp', 'max_sightings'),
                        ),
                        array(
                            'key' => 'max_band',
                            'label' => __('Ceiling'),
                            'type' => 'select',
                            'options' => ValueVerdictTool::BANDS,
                            'value' => isset($clamp['max_band'])
                                ? $clamp['max_band']
                                : null,
                            'default' => 'low',
                            'path' => array('thresholds',
                                'thin_record_clamp', 'max_band'),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * `escalations` — the conflict rules, discovered the same way
     * signals are and with the same three states.
     *
     * v1 ships two, so this is two checkboxes and their prose. It is
     * still `items` rather than `fields`, because the set is a directory
     * read and the count is not the design's business.
     *
     * @param array $parameters
     * @param array $sources
     * @return array
     */
    private function sectionEscalations(array $parameters, array $sources)
    {
        return array(
            'id' => 'escalations',
            'title' => __('Conflict rules'),
            'blurb' => __(
                'When the record contradicts itself loudly enough that'
                . ' no lean is honest. A rule may only ever say'
                . ' contested — one that could name a side would be a'
                . ' score override wearing a different hat.'
            ),
            'blocks' => array(
                array(
                    'kind' => 'items',
                    'id' => 'escalations',
                    'path' => array('escalations'),
                    'items' => $this->escalationPalette($parameters),
                ),
            ),
        );
    }

    /**
     * @param array $parameters
     * @return array
     */
    public function escalationPalette(array $parameters)
    {
        $catalogue = ValueSignalLoader::catalogue(
            ValueSignalLoader::SUBJECT_ESCALATION
        );
        $entries = $this->entriesById($parameters, 'escalations');
        $items = array();
        foreach ($catalogue as $id => $config) {
            $items[$id] = $this->escalationItem($id, $config,
                isset($entries[$id]) ? $entries[$id] : null);
        }
        foreach ($entries as $id => $entry) {
            if (!isset($items[$id])) {
                $items[$id] = $this->escalationItem($id, null, $entry);
            }
        }
        return array_values($items);
    }

    /**
     * @param string $id
     * @param array|null $config
     * @param array|null $entry
     * @return array
     */
    private function escalationItem($id, $config, $entry)
    {
        $inProfile = $entry !== null;
        $enabled = $inProfile
            && (!array_key_exists('enabled', $entry)
                || !empty($entry['enabled']));
        $badges = array();
        if ($config !== null && !empty($config['is_custom'])) {
            $badges[] = array(
                'id' => 'custom',
                'label' => __('custom'),
                'title' => __('Dropped into app/Lib/ValueEscalations.'),
            );
        }
        if ($config === null) {
            $badges[] = array(
                'id' => 'missing',
                'label' => __('not implemented here'),
                'title' => __(
                    'This profile configures a rule this instance does'
                    . ' not have. It cannot fire; the configuration is'
                    . ' kept.'
                ),
            );
        }
        $item = array(
            'id' => $id,
            'title' => $id,
            'description' => $config === null
                ? null
                : $config['description'],
            'state' => $config === null
                ? 'missing'
                : ($inProfile ? 'active' : 'available'),
            'enabled' => $enabled,
            'in_profile' => $inProfile,
            'badges' => $badges,
            'emits' => $config === null
                ? (isset($entry['emits']) ? $entry['emits'] : null)
                : $config['emits'],
            'source' => $config === null ? null : $config['source'],
            'path' => array('escalations', $id),
            'fields' => array(
                array(
                    'key' => 'enabled',
                    'label' => __('Enabled'),
                    'type' => 'bool',
                    'value' => $enabled,
                    'default' => true,
                    'path' => array('escalations', $id, 'enabled'),
                ),
            ),
        );
        $when = $entry !== null && isset($entry['when'])
            && is_array($entry['when'])
            ? $entry['when']
            : array();
        $schema = $config === null ? array() : $config['when_schema'];
        foreach ($this->generatedFields($schema, $when,
            array('escalations', $id, 'when')) as $field) {
            $field['map'] = 'when';
            $item['fields'][] = $field;
        }
        return $item;
    }

    /**
     * `exclusions` — the closed set of four, declared beside the code
     * that applies them.
     *
     * @param array $parameters
     * @param array $sources
     * @return array
     */
    private function sectionExclusions(array $parameters, array $sources)
    {
        return array(
            'id' => 'exclusions',
            'title' => __('Exclusions'),
            'blurb' => __(
                'Evidence you have decided not to count, filtered before'
                . ' any signal sees it — so two signals reading the same'
                . ' fact read the same filtered set. Every row an'
                . ' exclusion removes is listed in the assessment as not'
                . ' counted, naming the rule.'
            ),
            'blocks' => array(
                array(
                    'kind' => 'items',
                    'id' => 'exclusions',
                    'path' => array('exclusions'),
                    'items' => $this->exclusionItems($parameters),
                ),
            ),
        );
    }

    /**
     * @param array $parameters
     * @return array
     */
    public function exclusionItems(array $parameters)
    {
        $entries = $this->entriesById($parameters, 'exclusions');
        $items = array();
        foreach (ValueExclusionTool::catalogue() as $id => $declaration) {
            $entry = isset($entries[$id]) ? $entries[$id] : null;
            $enabled = $entry !== null
                && (!array_key_exists('enabled', $entry)
                    || !empty($entry['enabled']));
            $item = array(
                'id' => $id,
                'title' => $declaration['title'],
                'description' => $declaration['description'],
                'state' => $entry === null ? 'available' : 'active',
                'enabled' => $enabled,
                'in_profile' => $entry !== null,
                'layer' => $declaration['layer'],
                'badges' => array(),
                'path' => array('exclusions', $id),
                'fields' => array(
                    array(
                        'key' => 'enabled',
                        'label' => __('Enabled'),
                        'type' => 'bool',
                        'value' => $enabled,
                        'default' => false,
                        'path' => array('exclusions', $id, 'enabled'),
                    ),
                ),
            );
            foreach ($this->generatedFields(
                $declaration['schema'],
                $entry === null ? array() : $entry,
                array('exclusions', $id)
            ) as $field) {
                $item['fields'][] = $field;
            }
            $items[] = $item;
        }
        foreach ($entries as $id => $entry) {
            if (isset(ValueExclusionTool::catalogue()[$id])) {
                continue;
            }
            $items[] = array(
                'id' => $id,
                'title' => $id,
                'description' => null,
                'state' => 'missing',
                'enabled' => false,
                'in_profile' => true,
                'layer' => null,
                'badges' => array(array(
                    'id' => 'missing',
                    'label' => __('not an exclusion this version has'),
                    'title' => __(
                        'The exclusion set is closed in code. This entry'
                        . ' does nothing.'
                    ),
                )),
                'path' => array('exclusions', $id),
                'fields' => array(),
            );
        }
        return $items;
    }

    /**
     * `relevance` — the second axis. Added by phase 5, after §4 of the
     * spec was written, which is why the spec says six sections and
     * there are seven.
     *
     * @param array $parameters
     * @param array $sources
     * @return array
     */
    private function sectionRelevance(array $parameters, array $sources)
    {
        $section = $this->section($parameters, 'relevance');
        $ttl = isset($section['ttl_days']) && is_array($section['ttl_days'])
            ? $section['ttl_days']
            : array();
        $types = isset($sources['attribute_types'])
            ? $sources['attribute_types']
            : array();
        $entries = array();
        foreach ($ttl as $type => $days) {
            $entries[] = array(
                'key' => (string)$type,
                'label' => $type === 'default'
                    ? __('Every other type')
                    : (string)$type,
                'value' => $days,
                'type' => 'int',
                'fixed' => $type === 'default',
                'path' => array('relevance', 'ttl_days', (string)$type),
            );
        }
        return array(
            'id' => 'relevance',
            'title' => __('Relevance'),
            'blurb' => __(
                'Whether what the record asserts still matters today.'
                . ' Its own axis, and nothing in the quality reads it:'
                . ' silence must never be able to promote a value to a'
                . ' definite answer.'
            ),
            'blocks' => array(
                array(
                    'kind' => 'fields',
                    'id' => 'clock',
                    'title' => __('The clock'),
                    'fields' => array(
                        array(
                            'key' => 'clock',
                            'label' => __('Measure from'),
                            'type' => 'select',
                            'options' => self::CLOCKS,
                            'value' => isset($section['clock'])
                                ? $section['clock']
                                : null,
                            'default' => 'last_independent_corroboration',
                            'help' => __(
                                'Last independent corroboration is'
                                . ' somebody other than the original'
                                . ' reporter saying it again. Newest'
                                . ' occurrence is the fallback where'
                                . ' nothing else can be dated.'
                            ),
                            'path' => array('relevance', 'clock'),
                        ),
                        array(
                            'key' => 'decay_speed',
                            'label' => __('Decay speed'),
                            'type' => 'int',
                            'value' => isset($section['decay_speed'])
                                ? $section['decay_speed']
                                : null,
                            'default' => 1,
                            'help' => __(
                                "MISP's polynomial. 1 is linear;"
                                . ' exponential has no TTL to measure'
                                . ' against, which is why the curve is'
                                . ' pinned here.'
                            ),
                            'path' => array('relevance', 'decay_speed'),
                        ),
                        array(
                            'key' => 'aging_fraction',
                            'label' => __('Aging from'),
                            'type' => 'float',
                            'value' => isset($section['aging_fraction'])
                                ? $section['aging_fraction']
                                : null,
                            'default' => 0.33,
                            'help' => __(
                                'The share of the TTL left when a value'
                                . ' stops reading as current.'
                            ),
                            'path' => array('relevance', 'aging_fraction'),
                        ),
                        array(
                            'key' => 'lag_uncertain_days',
                            'label' => __('Encoding lag before uncertain'),
                            'type' => 'int',
                            'value' => isset($section['lag_uncertain_days'])
                                ? $section['lag_uncertain_days']
                                : null,
                            'default' => 30,
                            'help' => __(
                                'An encoding date is later than the'
                                . ' observation it stands for, so time'
                                . ' measured from it is a lower bound.'
                                . ' Past this lag the timeline is'
                                . ' flagged uncertain — which is the'
                                . ' common state on real data, not the'
                                . ' exotic one.'
                            ),
                            'path' => array('relevance',
                                'lag_uncertain_days'),
                        ),
                        array(
                            'key' => 'type_rule',
                            'label' => __('A value with several types'),
                            'type' => 'select',
                            'options' => self::TYPE_RULES,
                            'value' => isset($section['type_rule'])
                                ? $section['type_rule']
                                : null,
                            'default' => 'shortest',
                            'path' => array('relevance', 'type_rule'),
                        ),
                    ),
                ),
                array(
                    'kind' => 'map',
                    'id' => 'ttl_days',
                    'title' => __('Time to live, per type'),
                    'blurb' => __(
                        'How long a report of this type stays current'
                        . ' without corroboration. Only the types you'
                        . ' have an opinion about; everything else'
                        . ' follows the default.'
                    ),
                    'key_label' => __('Attribute type'),
                    'value_label' => __('Days'),
                    'value_type' => 'int',
                    'path' => array('relevance', 'ttl_days'),
                    'entries' => $entries,
                    'add' => array(
                        'label' => __('Add a type'),
                        'source' => 'attribute_types',
                        'options' => $this->unusedKeys($types, $ttl),
                    ),
                ),
            ),
        );
    }

    /**
     * `reference` — what the analyst believes about their sources. Two
     * override maps and the scale they resolve through.
     *
     * Both maps default to *only what this profile overrides*, never a
     * row per organisation or per warninglist on the instance. An
     * instance has hundreds of organisations and the profile has an
     * opinion about four.
     *
     * @param array $parameters
     * @param array $sources
     * @return array
     */
    private function sectionReference(array $parameters, array $sources)
    {
        $section = $this->section($parameters, 'reference');
        $trust = isset($section['org_trust'])
            && is_array($section['org_trust'])
            ? $section['org_trust']
            : array();
        $scale = isset($section['org_trust_scale'])
            && is_array($section['org_trust_scale'])
            ? $section['org_trust_scale']
            : array();
        $categories = isset($section['warninglist_category'])
            && is_array($section['warninglist_category'])
            ? $section['warninglist_category']
            : array();
        $orgs = isset($sources['orgs']) ? $sources['orgs'] : array();
        $lists = isset($sources['warninglists'])
            ? $sources['warninglists']
            : array();

        $trustEntries = array();
        foreach ($trust as $uuid => $grade) {
            $trustEntries[] = array(
                'key' => (string)$uuid,
                'label' => isset($orgs[$uuid])
                    ? $orgs[$uuid]
                    : (string)$uuid,
                'sub_label' => isset($orgs[$uuid]) ? (string)$uuid : null,
                'missing' => !isset($orgs[$uuid]),
                'value' => $grade,
                'type' => 'select',
                'options' => array_merge(
                    ValueTrustTool::GRADES,
                    array(ValueTrustTool::UNRATED)
                ),
                'path' => array('reference', 'org_trust', (string)$uuid),
            );
        }
        $scaleFields = array();
        foreach (ValueTrustTool::DEFAULT_SCALE as $grade => $factor) {
            $scaleFields[] = array(
                'key' => (string)$grade,
                'label' => (string)$grade,
                'type' => 'float',
                'value' => isset($scale[$grade]) ? $scale[$grade] : null,
                'default' => $factor,
                'path' => array('reference', 'org_trust_scale',
                    (string)$grade),
            );
        }
        $categoryEntries = array();
        foreach ($categories as $name => $category) {
            $categoryEntries[] = array(
                'key' => (string)$name,
                'label' => (string)$name,
                'missing' => !empty($lists) && !isset($lists[$name]),
                'value' => $category,
                'type' => 'select',
                'options' => $this->categoryOptions(),
                'path' => array('reference', 'warninglist_category',
                    (string)$name),
            );
        }
        return array(
            'id' => 'reference',
            'title' => __('Reference data'),
            'blurb' => __(
                'What you believe about your sources, and what the'
                . ' warninglists mean. Admiralty-shaped on purpose:'
                . ' source reliability goes in, information credibility'
                . ' comes out.'
            ),
            'blocks' => array(
                array(
                    'kind' => 'map',
                    'id' => 'org_trust',
                    'title' => __('Organisation trust'),
                    'blurb' => __(
                        'Admiralty grades. F is neutral, the same as C —'
                        . ' the shipped taxonomy says so — and G is'
                        . ' deliberately deceptive and worth nothing.'
                        . ' Only the organisations you have graded.'
                    ),
                    'key_label' => __('Organisation'),
                    'value_label' => __('Grade'),
                    'value_type' => 'select',
                    'path' => array('reference', 'org_trust'),
                    'entries' => $trustEntries,
                    'add' => array(
                        'label' => __('Grade an organisation'),
                        'source' => 'orgs',
                        'search' => true,
                        'options' => $this->unusedKeys(
                            array_keys($orgs), $trust),
                    ),
                ),
                array(
                    'kind' => 'fields',
                    'id' => 'org_trust_scale',
                    'title' => __('What a grade is worth'),
                    'blurb' => __(
                        'The multiplier each grade contributes. A voice'
                        . ' graded E counts as a quarter of one.'
                    ),
                    'fields' => $scaleFields,
                ),
                array(
                    'kind' => 'map',
                    'id' => 'warninglist_category',
                    'title' => __('Warninglist categories'),
                    'blurb' => __(
                        'What a list hit means: known infrastructure is'
                        . ' not a false positive. Nothing upstream sets'
                        . ' this column yet, so the instance ships a map'
                        . ' and this is where you disagree with it.'
                    ),
                    'key_label' => __('Warninglist'),
                    'value_label' => __('Means'),
                    'value_type' => 'select',
                    'path' => array('reference', 'warninglist_category'),
                    'entries' => $categoryEntries,
                    'add' => array(
                        'label' => __('Override a list'),
                        'source' => 'warninglists',
                        'search' => true,
                        'options' => $this->unusedKeys(
                            array_keys($lists), $categories),
                    ),
                ),
            ),
        );
    }

    /**
     * The warninglist categories a profile may assert.
     *
     * Taken from `WarninglistCategory` where it is available, so the
     * editor and the resolution agree; the fallback is the three the
     * upstream schema defines, because a class that has not loaded is
     * not a reason to offer no options.
     *
     * @return array
     */
    private function categoryOptions()
    {
        return array(
            WarninglistCategory::KNOWN,
            WarninglistCategory::FALSE_POSITIVE,
        );
    }

    /**
     * The four shipped ledger groups.
     *
     * `ValueSignalBase` is included by the loader's scan rather than by
     * `App::uses` — it lives under `Model/ValueSignals/`, which is not a
     * package path, and the loader requires it directly so that it stays
     * runnable with no CakePHP booted. So the constant is reached
     * through a scan rather than assumed to be loaded, and the coupling
     * is one line here instead of a second copy of the list.
     *
     * @return array
     */
    private function baseGroups()
    {
        ValueSignalLoader::classes();
        return ValueSignalBase::GROUPS;
    }

    /**
     * `enrichment` — which modules the profile declares, and the cost
     * posture that decides whether asking them is allowed.
     *
     * Nothing auto-runs (D15): the tab arrives with these ticked and a
     * run still takes a press, because MISP records nowhere that a
     * module has run and "run the defaults on page open" therefore
     * means running them on every page open.
     *
     * @param array $parameters
     * @param array $sources
     * @return array
     */
    private function sectionEnrichment(array $parameters, array $sources)
    {
        $section = $this->section($parameters, 'enrichment');
        $autoRun = isset($section['auto_run'])
            && is_array($section['auto_run'])
            ? $section['auto_run']
            : array();
        $locality = isset($section['locality'])
            && is_array($section['locality'])
            ? $section['locality']
            : array();
        $modules = isset($sources['modules']) ? $sources['modules'] : array();
        $types = isset($sources['attribute_types'])
            ? $sources['attribute_types']
            : array();

        $autoEntries = array();
        foreach ($autoRun as $type => $names) {
            $names = is_array($names) ? $names : array($names);
            $autoEntries[] = array(
                'key' => (string)$type,
                'label' => (string)$type,
                'value' => $names,
                'type' => 'multiselect',
                'options' => empty($modules)
                    ? $names
                    : array_keys($modules),
                'unavailable' => empty($modules)
                    ? array()
                    : array_values(array_diff($names,
                        array_keys($modules))),
                'path' => array('enrichment', 'auto_run', (string)$type),
            );
        }
        $localityEntries = array();
        foreach ($locality as $name => $where) {
            $localityEntries[] = array(
                'key' => (string)$name,
                'label' => (string)$name,
                'value' => $where,
                'type' => 'select',
                'options' => array(
                    ModuleLocality::LOCAL,
                    ModuleLocality::EXTERNAL,
                ),
                'shipped' => ModuleLocality::shippedFor((string)$name),
                'path' => array('enrichment', 'locality', (string)$name),
            );
        }
        return array(
            'id' => 'enrichment',
            'title' => __('Enrichment'),
            'blurb' => __(
                'Which modules you would want asked about a value of'
                . ' each type. Nothing runs on its own: the tab arrives'
                . ' with these ticked and a run still takes a press.'
            ),
            'blocks' => array(
                array(
                    'kind' => 'fields',
                    'id' => 'posture',
                    'title' => __('Cost posture'),
                    'fields' => array(
                        array(
                            'key' => 'cost_posture',
                            'label' => __('Modules that may be offered'),
                            'type' => 'select',
                            'options' => ValueEnrichmentTool::postures(),
                            'value' => isset($section['cost_posture'])
                                ? $section['cost_posture']
                                : null,
                            'default' => ValueEnrichmentTool::DEFAULT_POSTURE,
                            'help' => __(
                                'Local only offers nothing that leaves'
                                . ' the instance — which on a platform'
                                . ' where enrichment means asking'
                                . ' somebody else selects very little,'
                                . ' and says so.'
                            ),
                            'path' => array('enrichment', 'cost_posture'),
                        ),
                        array(
                            'key' => 'max_age_hours',
                            'label' => __('Reuse an answer for'),
                            'type' => 'int',
                            'value' => isset($section['max_age_hours'])
                                ? $section['max_age_hours']
                                : null,
                            'default' =>
                                ValueEnrichmentTool::DEFAULT_MAX_AGE_HOURS,
                            'help' => __(
                                'Inert: there is no store of module'
                                . ' answers, so nothing is reused and'
                                . ' this window governs nothing yet.'
                            ),
                            'inert' => true,
                            'path' => array('enrichment', 'max_age_hours'),
                        ),
                    ),
                ),
                array(
                    'kind' => 'map',
                    'id' => 'auto_run',
                    'title' => __('Modules per type'),
                    'key_label' => __('Attribute type'),
                    'value_label' => __('Modules'),
                    'value_type' => 'multiselect',
                    'path' => array('enrichment', 'auto_run'),
                    'entries' => $autoEntries,
                    'add' => array(
                        'label' => __('Declare modules for a type'),
                        'source' => 'attribute_types',
                        'options' => $this->unusedKeys($types, $autoRun),
                    ),
                ),
                array(
                    'kind' => 'map',
                    'id' => 'locality',
                    'title' => __('Where a module answers from'),
                    'blurb' => __(
                        'Overrides the shipped roster. Locality cannot'
                        . ' be derived — a module that declares no'
                        . ' config and no requirements may still fetch'
                        . ' a third-party site — so it ships as'
                        . ' knowledge and this is where you correct it.'
                    ),
                    'key_label' => __('Module'),
                    'value_label' => __('Answers from'),
                    'value_type' => 'select',
                    'path' => array('enrichment', 'locality'),
                    'entries' => $localityEntries,
                    'add' => array(
                        'label' => __('Override a module'),
                        'source' => 'modules',
                        'search' => true,
                        'options' => $this->unusedKeys(
                            array_keys($modules), $locality),
                    ),
                ),
            ),
        );
    }

    /**
     * A form for a schema the tool has never seen, which is what makes a
     * dropped-in signal configurable without hand-edited JSON.
     *
     * @param array $schema key => spec
     * @param array $stored The map as the profile carries it
     * @param array $prefix The path segments this map lives under
     * @return array
     */
    private function generatedFields(array $schema, array $stored,
        array $prefix
    ) {
        $fields = array();
        foreach ($schema as $key => $spec) {
            $field = array(
                'key' => $key,
                'label' => isset($spec['label']) ? $spec['label'] : $key,
                'type' => isset($spec['type']) ? $spec['type'] : 'int',
                'value' => array_key_exists($key, $stored)
                    ? $stored[$key]
                    : null,
                'default' => isset($spec['default'])
                    ? $spec['default']
                    : null,
                'path' => array_merge($prefix, array($key)),
                'generated' => true,
            );
            if (isset($spec['options'])) {
                $field['type'] = 'select';
                $field['options'] = $spec['options'];
            }
            if (isset($spec['help'])) {
                $field['help'] = $spec['help'];
            }
            $fields[] = $field;
        }
        /*
         * A key the profile carries that the schema does not describe.
         * Not an error — a profile written against a later version of a
         * signal has to survive a downgrade (`ValueSignalBase`) — so it
         * is rendered as what it is: a value this version cannot label.
         */
        foreach ($stored as $key => $value) {
            if (isset($schema[$key]) || is_array($value)) {
                continue;
            }
            $fields[] = array(
                'key' => $key,
                'label' => $key,
                'type' => is_float($value) ? 'float'
                    : (is_int($value) ? 'int' : 'string'),
                'value' => $value,
                'default' => null,
                'path' => array_merge($prefix, array($key)),
                'generated' => true,
                'undeclared' => true,
            );
        }
        return $fields;
    }

    /**
     * The most this profile's enabled signals could contribute, and how
     * that number was arrived at.
     *
     * **The rule: the largest positive value in a signal's `points`
     * map.** A `cap` is always the largest positive value where one is
     * declared, so the rule needs no knowledge of which key is the cap
     * — checked against all eleven shipped signals, where it gives the
     * cap for the six that have one and the single positive term for the
     * five that do not.
     *
     * **What it is: an upper bound, and only for a signal whose points
     * bound it.** A custom signal paying `per_x` with no cap is not
     * bounded by its own points, and this would under-report it. That is
     * why `unbounded` is returned alongside the total rather than
     * folded into it — the caller refuses a band above the bound, and
     * a bound with an unbounded signal under it is a bound the caller
     * should not refuse on. Stated rather than papered over: a derived
     * number that quietly rejects a legitimate edit is worse than one
     * that says where it stops being reliable.
     *
     * A declared maximum on the signal class was the alternative and
     * was rejected for D14's reason in a new place: a number an author
     * maintains by hand duplicates their own arithmetic and drifts from
     * it silently, where a derived one cannot.
     *
     * @param array $parameters
     * @return array `bound`, `per_signal`, `unbounded`, `signals`
     */
    public function attainable(array $parameters)
    {
        $catalogue = ValueSignalLoader::catalogue();
        $perSignal = array();
        $unbounded = array();
        $bound = 0;
        $counted = 0;
        foreach ($this->entriesById($parameters, 'signals') as $id => $entry) {
            if (array_key_exists('enabled', $entry)
                && empty($entry['enabled'])
            ) {
                continue;
            }
            if (!isset($catalogue[$id])) {
                // Cannot run here, so it can contribute nothing.
                continue;
            }
            $counted++;
            $points = isset($entry['points']) && is_array($entry['points'])
                ? $entry['points']
                : array();
            $best = 0;
            $positive = false;
            foreach ($points as $value) {
                if (!is_int($value) && !is_float($value)) {
                    continue;
                }
                if ($value > $best) {
                    $best = $value;
                    $positive = true;
                }
            }
            $perSignal[$id] = (int)$best;
            $bound += (int)$best;
            /*
             * A signal with a positive term and no key at least as
             * large as its own product cannot be bounded from here. The
             * cheap and honest test is whether it declares a cap at
             * all: the shipped six do, under the key `cap`, and a
             * custom signal that pays per unit without one is the case
             * this cannot see.
             */
            if ($positive && !array_key_exists('cap', $points)
                && count($points) > 0
                && $this->looksPerUnit($points)
            ) {
                $unbounded[] = $id;
            }
        }
        return array(
            'bound' => $bound,
            'per_signal' => $perSignal,
            'unbounded' => $unbounded,
            'signals' => $counted,
            'reliable' => empty($unbounded),
        );
    }

    /**
     * Whether a points map reads as *paid per something* — a key named
     * `per…`, which is the convention every shipped signal that
     * multiplies by a count follows, and every one of those declares a
     * `cap` beside it.
     *
     * `scale` is deliberately not here, and the distinction is the whole
     * point of the test rather than a detail: `reporting.published_ratio`
     * pays `scale × published / events`, and the multiplicand is a ratio
     * at most 1 — so `scale` *is* its maximum and the signal is bounded
     * by its own points with no cap. Treating it as per-unit made
     * `reliable` false for the shipped default, which would have turned
     * §7a item 8's refusal into an advisory on every instance. Measured
     * against all eleven shipped signals: with `scale` excluded, six
     * declare a `per…` key and all six declare a `cap`, so the shipped
     * catalogue is fully bounded.
     *
     * @param array $points
     * @return bool
     */
    private function looksPerUnit(array $points)
    {
        foreach (array_keys($points) as $key) {
            if (strpos((string)$key, 'per') === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * The band boundaries drawn against the attainable range, so a band
     * above the one above it — or beyond anything the catalogue can
     * reach — is visibly wrong rather than silently saved.
     *
     * @param array $parameters
     * @return array
     */
    public function bandStrip(array $parameters)
    {
        $thresholds = $this->section($parameters, 'thresholds');
        $bands = isset($thresholds['quality_bands'])
            && is_array($thresholds['quality_bands'])
            ? $thresholds['quality_bands']
            : array();
        $high = isset($bands['high']) ? (int)$bands['high'] : 60;
        $medium = isset($bands['medium']) ? (int)$bands['medium'] : 30;
        $attainable = $this->attainable($parameters);
        $bound = $attainable['bound'];
        $problems = array();
        if ($medium >= $high) {
            $problems[] = array(
                'band' => 'medium',
                'message' => sprintf(
                    __('Medium starts at %1$d, which is not below high'
                        . ' at %2$d. Nothing could ever be high.'),
                    $medium,
                    $high
                ),
            );
        }
        foreach (array('high' => $high, 'medium' => $medium) as $band => $at) {
            if ($at <= $bound) {
                continue;
            }
            $problems[] = array(
                'band' => $band,
                'message' => sprintf(
                    __('%1$s starts at %2$d, and the enabled signals'
                        . ' cannot sum past %3$d. Nothing could ever'
                        . ' reach %1$s.'),
                    $band,
                    $at,
                    $bound
                ),
                'advisory' => !$attainable['reliable'],
            );
        }
        return array(
            'bound' => $bound,
            'attainable' => $attainable,
            'bands' => array(
                array(
                    'id' => 'high',
                    'from' => $high,
                    'to' => max($high, $bound),
                ),
                array(
                    'id' => 'medium',
                    'from' => $medium,
                    'to' => $high,
                ),
                array(
                    'id' => 'low',
                    'from' => 0,
                    'to' => $medium,
                ),
            ),
            'problems' => $problems,
            'ok' => empty($problems),
        );
    }

    /**
     * Everything wrong with a document, as sentences.
     *
     * Phase 1 asked only whether `parameters` parses into an object and
     * left each section's contract to the phase that owns it. This is
     * where those contracts are finally checked together, because the
     * editor is the first caller that can refuse an edit before it is
     * stored.
     *
     * A **warning** is a statement about a document that is still
     * saveable — a signal this instance does not have, an organisation
     * key that is not a uuid — and it is returned separately, because
     * refusing to save a profile written for a different instance would
     * make export and import useless.
     *
     * @param array $parameters
     * @return array `errors` and `warnings`, both lists of strings
     */
    public function validate(array $parameters)
    {
        $errors = array();
        $warnings = array();

        foreach (array_keys($parameters) as $key) {
            if ($key === 'format'
                || in_array($key, self::SECTION_ORDER, true)
            ) {
                continue;
            }
            $warnings[] = sprintf(
                __('`%s` is not a section this version knows about. It'
                    . ' is kept and ignored.'),
                $key
            );
        }
        foreach (array('signals', 'escalations', 'exclusions') as $name) {
            if (isset($parameters[$name]) && !$this->isList($parameters[$name])) {
                $errors[] = sprintf(
                    __('`%s` must be a list of entries, each with an'
                        . ' `id`.'),
                    $name
                );
            }
        }
        $strip = $this->bandStrip($parameters);
        foreach ($strip['problems'] as $problem) {
            if (!empty($problem['advisory'])) {
                $warnings[] = $problem['message'] . ' ' . sprintf(
                    __('Not refused, because %s is paid per unit with'
                        . ' no cap and the bound cannot see it.'),
                    implode(', ', $strip['attainable']['unbounded'])
                );
                continue;
            }
            $errors[] = $problem['message'];
        }

        $thresholds = $this->section($parameters, 'thresholds');
        if (isset($thresholds['lean_supermajority'])) {
            $share = $thresholds['lean_supermajority'];
            if (!is_numeric($share) || $share <= 0.5 || $share > 1) {
                $errors[] = __(
                    'The lean supermajority must be above 0.5 and at'
                    . ' most 1. A share at or below half is not a'
                    . ' majority, and one above 1 can never be met.'
                );
            }
        }

        $catalogue = ValueSignalLoader::catalogue();
        foreach ($this->entriesById($parameters, 'signals') as $id => $entry) {
            $signal = ValueSignalLoader::get($id);
            if ($signal === null) {
                $warnings[] = sprintf(
                    __('`%s` is not implemented on this instance. It'
                        . ' contributes nothing and is kept.'),
                    $id
                );
                continue;
            }
            foreach ($signal->validateEntry($entry) as $error) {
                $errors[] = $error;
            }
            if (!empty($entry['band'])
                && !in_array($entry['band'], self::BANDS, true)
            ) {
                $errors[] = sprintf(
                    __('%1$s: `%2$s` is not a weight band.'),
                    $id,
                    $entry['band']
                );
            }
        }
        foreach ($this->entriesById($parameters, 'escalations')
            as $id => $entry
        ) {
            $rule = ValueSignalLoader::get($id,
                ValueSignalLoader::SUBJECT_ESCALATION);
            if ($rule === null) {
                $warnings[] = sprintf(
                    __('`%s` is not a conflict rule this instance has.'
                        . ' It cannot fire and is kept.'),
                    $id
                );
                continue;
            }
            foreach ($rule->validateEntry($entry) as $error) {
                $errors[] = $error;
            }
        }
        $exclusions = ValueExclusionTool::catalogue();
        foreach ($this->entriesById($parameters, 'exclusions')
            as $id => $entry
        ) {
            if (!isset($exclusions[$id])) {
                $warnings[] = sprintf(
                    __('`%s` is not an exclusion this version has. The'
                        . ' set is closed in code; the entry does'
                        . ' nothing.'),
                    $id
                );
                continue;
            }
            foreach ($exclusions[$id]['schema'] as $key => $spec) {
                if (!array_key_exists($key, $entry)) {
                    continue;
                }
                $error = $this->checkScalar($entry[$key], $spec,
                    $id . '.' . $key);
                if ($error !== null) {
                    $errors[] = $error;
                }
            }
        }

        foreach ($this->relevanceErrors($parameters) as $error) {
            $errors[] = $error;
        }
        foreach ($this->referenceErrors($parameters) as $error) {
            $errors[] = $error;
        }
        foreach ($this->enrichmentErrors($parameters) as $error) {
            $errors[] = $error;
        }
        return array('errors' => $errors, 'warnings' => $warnings);
    }

    /**
     * @param array $parameters
     * @return array
     */
    private function relevanceErrors(array $parameters)
    {
        $errors = array();
        $section = $this->section($parameters, 'relevance');
        if (isset($section['clock'])
            && !in_array($section['clock'], self::CLOCKS, true)
        ) {
            $errors[] = sprintf(
                __('`relevance.clock`: `%s` is not a clock this version'
                    . ' reads.'),
                $section['clock']
            );
        }
        if (isset($section['type_rule'])
            && !in_array($section['type_rule'], self::TYPE_RULES, true)
        ) {
            $errors[] = sprintf(
                __('`relevance.type_rule`: `%s` is not a rule this'
                    . ' version applies.'),
                $section['type_rule']
            );
        }
        if (isset($section['aging_fraction'])) {
            $fraction = $section['aging_fraction'];
            if (!is_numeric($fraction) || $fraction <= 0 || $fraction >= 1) {
                $errors[] = __(
                    '`relevance.aging_fraction` must be above 0 and'
                    . ' below 1 — it is the share of the TTL still'
                    . ' left when a value stops reading as current.'
                );
            }
        }
        $ttl = isset($section['ttl_days']) && is_array($section['ttl_days'])
            ? $section['ttl_days']
            : array();
        foreach ($ttl as $type => $days) {
            if (!is_int($days) || $days <= 0) {
                $errors[] = sprintf(
                    __('`relevance.ttl_days.%s` must be a whole number'
                        . ' of days above zero.'),
                    $type
                );
            }
        }
        if (!empty($ttl) && !isset($ttl['default'])) {
            $errors[] = __(
                '`relevance.ttl_days` needs a `default`, or every type'
                . ' it does not name has no time to live at all.'
            );
        }
        return $errors;
    }

    /**
     * @param array $parameters
     * @return array
     */
    private function referenceErrors(array $parameters)
    {
        $errors = array();
        $section = $this->section($parameters, 'reference');
        $grades = array_merge(ValueTrustTool::GRADES,
            array(ValueTrustTool::UNRATED));
        $trust = isset($section['org_trust'])
            && is_array($section['org_trust'])
            ? $section['org_trust']
            : array();
        foreach ($trust as $uuid => $grade) {
            if (!in_array(ValueTrustTool::normaliseGrade($grade), $grades,
                true)
            ) {
                $errors[] = sprintf(
                    __('`reference.org_trust.%1$s`: `%2$s` is not an'
                        . ' admiralty grade.'),
                    $uuid,
                    is_scalar($grade) ? $grade : gettype($grade)
                );
            }
        }
        $scale = isset($section['org_trust_scale'])
            && is_array($section['org_trust_scale'])
            ? $section['org_trust_scale']
            : array();
        foreach ($scale as $grade => $factor) {
            if (!is_numeric($factor) || $factor < 0) {
                $errors[] = sprintf(
                    __('`reference.org_trust_scale.%s` must be a number'
                        . ' at or above zero.'),
                    $grade
                );
            }
        }
        $categories = isset($section['warninglist_category'])
            && is_array($section['warninglist_category'])
            ? $section['warninglist_category']
            : array();
        $allowed = $this->categoryOptions();
        foreach ($categories as $name => $category) {
            if (!in_array($category, $allowed, true)) {
                $errors[] = sprintf(
                    __('`reference.warninglist_category.%1$s`: `%2$s` is'
                        . ' not a category.'),
                    $name,
                    is_scalar($category) ? $category : gettype($category)
                );
            }
        }
        return $errors;
    }

    /**
     * @param array $parameters
     * @return array
     */
    private function enrichmentErrors(array $parameters)
    {
        $errors = array();
        $section = $this->section($parameters, 'enrichment');
        if (isset($section['cost_posture'])
            && !in_array($section['cost_posture'],
                ValueEnrichmentTool::postures(), true)
        ) {
            $errors[] = sprintf(
                __('`enrichment.cost_posture`: `%s` is not a posture.'),
                $section['cost_posture']
            );
        }
        if (isset($section['max_age_hours'])
            && (!is_int($section['max_age_hours'])
                || $section['max_age_hours'] <= 0)
        ) {
            $errors[] = __(
                '`enrichment.max_age_hours` must be a whole number of'
                . ' hours above zero.'
            );
        }
        $locality = isset($section['locality'])
            && is_array($section['locality'])
            ? $section['locality']
            : array();
        foreach ($locality as $name => $where) {
            if (!ModuleLocality::valid($where)) {
                $errors[] = sprintf(
                    __('`enrichment.locality.%1$s`: `%2$s` is not a'
                        . ' locality.'),
                    $name,
                    is_scalar($where) ? $where : gettype($where)
                );
            }
        }
        $autoRun = isset($section['auto_run'])
            && is_array($section['auto_run'])
            ? $section['auto_run']
            : array();
        foreach ($autoRun as $type => $names) {
            if (!is_array($names) && !is_string($names)) {
                $errors[] = sprintf(
                    __('`enrichment.auto_run.%s` must be a list of'
                        . ' module names.'),
                    $type
                );
            }
        }
        return $errors;
    }

    /**
     * @param mixed $given
     * @param array $spec
     * @param string $label
     * @return string|null
     */
    private function checkScalar($given, array $spec, $label)
    {
        $type = isset($spec['type']) ? $spec['type'] : 'int';
        if ($type === 'int' && !is_int($given)) {
            return sprintf(__('`%s` must be a whole number.'), $label);
        }
        if ($type === 'float' && !is_int($given) && !is_float($given)) {
            return sprintf(__('`%s` must be a number.'), $label);
        }
        if ($type === 'string' && !is_string($given)) {
            return sprintf(__('`%s` must be text.'), $label);
        }
        if (isset($spec['options'])
            && !in_array($given, $spec['options'], true)
        ) {
            return sprintf(
                __('`%1$s` must be one of: %2$s.'),
                $label,
                implode(', ', $spec['options'])
            );
        }
        return null;
    }

    /**
     * A pasted document, with the parse error and its line where there
     * is one.
     *
     * `json_decode` reports an offset and not a line, so the line is
     * counted from the offset — because "syntax error" with no position
     * on a 300-line document is a message that sends the reader back to
     * a text editor to bisect it by hand.
     *
     * @param string $raw
     * @return array `ok`, and either `parameters` or `error` and `line`
     */
    public function parse($raw)
    {
        $raw = (string)$raw;
        if (trim($raw) === '') {
            return array(
                'ok' => false,
                'error' => __('The document is empty.'),
                'line' => 1,
            );
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $offset = function_exists('json_last_error_msg')
                ? $this->firstStructuralFault($raw)
                : null;
            return array(
                'ok' => false,
                'error' => json_last_error_msg(),
                'line' => $offset === null
                    ? null
                    : substr_count(substr($raw, 0, $offset), "\n") + 1,
            );
        }
        if (!is_array($decoded) || $this->isList($decoded)) {
            return array(
                'ok' => false,
                'error' => __(
                    'A profile is a JSON object of named sections, not'
                    . ' a list or a bare value.'
                ),
                'line' => 1,
            );
        }
        return array('ok' => true, 'parameters' => $decoded);
    }

    /**
     * The byte at which the document first stops being possible.
     *
     * PHP reports *what* went wrong and never *where*, and "syntax
     * error" with no position on a three-hundred-line document sends the
     * reader back to a text editor to bisect it by hand. Bisecting on
     * *does this prefix parse* finds nothing, because a prefix of valid
     * JSON is almost never valid JSON — so this walks the brackets
     * instead, tracking what each closer is supposed to be closing.
     *
     * It catches the two faults a hand-edited profile actually has: a
     * closer with no opener, and a closer that does not match its
     * opener — `"signals": [}`, where the depth never goes negative and
     * a depth-only counter would have pointed at the end of the file.
     * Anything it cannot place — a bad escape, a bare word, a trailing
     * comma — points at the last byte, which is where an unterminated
     * document does in fact stop.
     *
     * @param string $raw
     * @return int|null
     */
    private function firstStructuralFault($raw)
    {
        $length = strlen($raw);
        if ($length === 0) {
            return null;
        }
        $stack = array();
        $inString = false;
        $escaped = false;
        for ($i = 0; $i < $length; $i++) {
            $char = $raw[$i];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{' || $char === '[') {
                $stack[] = $char;
            } elseif ($char === '}' || $char === ']') {
                if (empty($stack)) {
                    return $i;
                }
                $opener = array_pop($stack);
                $wants = $char === '}' ? '{' : '[';
                if ($opener !== $wants) {
                    return $i;
                }
            }
        }
        return $length - 1;
    }

    /**
     * A posted form applied to the stored document.
     *
     * **A section the form did not post is untouched.** The editor edits
     * one section at a time, and a merge that treated absence as
     * deletion would let a form wipe the six sections it did not draw.
     *
     * Within a posted section:
     *
     * - **`signals`, `escalations`, `exclusions` merge by id.** A stored
     *   entry the form did not mention survives — which is the rule that
     *   keeps an analyst's configuration for a signal this instance does
     *   not currently have (`03-signals.md` §4.4). An entry is removed
     *   only by an explicit `__remove`.
     * - **A map replaces wholesale**, because a removed row posts
     *   nothing and a merge could not tell that from a row the form
     *   never drew. So a map block posts `__present`, and a present
     *   marker with no rows means *empty*, not *unchanged*.
     * - **A scalar field with an empty string is a deletion**, so the
     *   key falls back to its schema default rather than being stored as
     *   `""` — which is how a numeric field gets cleared at all.
     *
     * @param array $stored The decoded stored `parameters`
     * @param array $posted The `parameters` sub-array of the request
     * @return array The document to save
     */
    public function merge(array $stored, array $posted)
    {
        $merged = $stored;
        foreach ($posted as $name => $value) {
            if (!in_array($name, self::SECTION_ORDER, true)) {
                continue;
            }
            if (in_array($name, array('signals', 'escalations',
                'exclusions'), true)
            ) {
                $merged[$name] = $this->mergeEntries(
                    isset($stored[$name]) && is_array($stored[$name])
                        ? $stored[$name]
                        : array(),
                    is_array($value) ? $value : array()
                );
                continue;
            }
            $merged[$name] = $this->mergeAssoc(
                isset($stored[$name]) && is_array($stored[$name])
                    ? $stored[$name]
                    : array(),
                is_array($value) ? $value : array()
            );
        }
        return $merged;
    }

    /**
     * @param array $stored A list of entries with `id`
     * @param array $posted id => fields
     * @return array A list of entries
     */
    private function mergeEntries(array $stored, array $posted)
    {
        $byId = array();
        $order = array();
        foreach ($stored as $entry) {
            if (!is_array($entry) || empty($entry['id'])) {
                continue;
            }
            $byId[$entry['id']] = $entry;
            $order[] = $entry['id'];
        }
        foreach ($posted as $id => $fields) {
            if (!is_array($fields)) {
                continue;
            }
            if (!empty($fields['__remove'])) {
                unset($byId[$id]);
                continue;
            }
            unset($fields['__remove']);
            $entry = isset($byId[$id])
                ? $byId[$id]
                : array('id' => $id);
            $byId[$id] = $this->mergeAssoc($entry, $fields);
            $byId[$id]['id'] = $id;
            if (!in_array($id, $order, true)) {
                $order[] = $id;
            }
        }
        $out = array();
        foreach ($order as $id) {
            if (isset($byId[$id])) {
                $out[] = $byId[$id];
            }
        }
        return $out;
    }

    /**
     * @param array $stored
     * @param array $posted
     * @return array
     */
    private function mergeAssoc(array $stored, array $posted)
    {
        $merged = $stored;
        $present = array();
        foreach ($posted as $key => $value) {
            if ($key === '__present') {
                continue;
            }
            $present[] = $key;
            if (is_array($value)) {
                if (!empty($value['__present'])
                    || $this->isList($value)
                ) {
                    $merged[$key] = $this->replaceMap($value);
                    continue;
                }
                $merged[$key] = $this->mergeAssoc(
                    isset($stored[$key]) && is_array($stored[$key])
                        ? $stored[$key]
                        : array(),
                    $value
                );
                continue;
            }
            if ($value === '' || $value === null) {
                unset($merged[$key]);
                continue;
            }
            $merged[$key] = $this->cast($value);
        }
        /*
         * A block that declared itself present replaces rather than
         * merges: the rows it did not post are rows the analyst removed.
         */
        if (!empty($posted['__present'])) {
            foreach (array_keys($merged) as $key) {
                if (!in_array($key, $present, true)) {
                    unset($merged[$key]);
                }
            }
        }
        return $merged;
    }

    /**
     * @param array $posted
     * @return array
     */
    private function replaceMap(array $posted)
    {
        unset($posted['__present']);
        $out = array();
        foreach ($posted as $key => $value) {
            if (is_array($value)) {
                $out[$key] = $this->replaceMap($value);
                continue;
            }
            if ($value === '' || $value === null) {
                continue;
            }
            $out[$key] = $this->cast($value);
        }
        return $out;
    }

    /**
     * A posted scalar back to the type the document wants.
     *
     * Everything arrives as a string from a form, and a document whose
     * `per_org` is `"7"` fails `ValueSignalBase::validatePoints()` —
     * correctly, because the exact-sum invariant is not negotiable and a
     * string is not an integer. So the cast happens here, once, rather
     * than in every validator.
     *
     * @param mixed $value
     * @return mixed
     */
    private function cast($value)
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return $value;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        if (preg_match('/^-?\d+$/', $trimmed)) {
            return (int)$trimmed;
        }
        if (preg_match('/^-?\d*\.\d+$/', $trimmed)) {
            return (float)$trimmed;
        }
        if ($trimmed === 'true') {
            return true;
        }
        if ($trimmed === 'false') {
            return false;
        }
        return $trimmed;
    }

    /**
     * A section's entries keyed by id, from the list the document holds.
     *
     * @param array $parameters
     * @param string $name
     * @return array
     */
    private function entriesById(array $parameters, $name)
    {
        $entries = array();
        $section = isset($parameters[$name]) && is_array($parameters[$name])
            ? $parameters[$name]
            : array();
        foreach ($section as $entry) {
            if (!is_array($entry) || empty($entry['id'])) {
                continue;
            }
            $entries[(string)$entry['id']] = $entry;
        }
        return $entries;
    }

    /**
     * @param array $parameters
     * @param string $name
     * @return array
     */
    private function section(array $parameters, $name)
    {
        return isset($parameters[$name]) && is_array($parameters[$name])
            ? $parameters[$name]
            : array();
    }

    /**
     * Candidate keys a map does not already carry, so an "add one"
     * picker offers what is missing rather than everything.
     *
     * @param array $candidates
     * @param array $used
     * @return array
     */
    private function unusedKeys(array $candidates, array $used)
    {
        $out = array();
        foreach ($candidates as $candidate) {
            if (!array_key_exists($candidate, $used)) {
                $out[] = $candidate;
            }
        }
        return $out;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function isList($value)
    {
        if (!is_array($value)) {
            return false;
        }
        if ($value === array()) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }
}
