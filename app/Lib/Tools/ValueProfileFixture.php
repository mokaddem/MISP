<?php
App::uses('AuditActionMeta', 'Tools');

/**
 * Hardcoded Value Profile data, keyed by value.
 *
 * The Value Profile page (/values/view/<b64>) is built against MISP's own
 * array shapes — `Attribute`/`Event`/`AttributeTag` keys, distribution as
 * an int, `to_ids` as 0|1 — so that going live replaces this class with
 * model calls and leaves the templates untouched.
 *
 * Nothing here is presentational: no colours, no widths, no chip classes.
 * The view factories derive those from the domain values.
 */
class ValueProfileFixture
{
    /**
     * The values this fixture knows about, and the artboard each one
     * exercises. Anything else renders the sparse page.
     */
    const KNOWN_VALUES = array(
        '185.234.219.24' => 'MALICIOUS',
        '104.21.34.198' => 'CONFLICTED',
        '8.8.8.8' => 'BENIGN',
        /*
         * The same disposition as the first, and here for a different
         * axis: 812 occurrences rather than ten, which is the count
         * every panel on the page scales on and none of the first three
         * exercise.
         */
        '45.155.205.233' => 'MALICIOUS',
    );

    /**
     * The day the whole fixture is written from. Every relative phrase
     * on the page — "2 days ago", "yesterday" — is measured against it,
     * and the sightings series buckets up to it.
     */
    const TODAY = '2025-08-24';

    /**
     * MISP's sighting types, as ints. They are not degrees of one thing:
     * a sighting supports the value, a false positive contradicts it, an
     * expiration retires it, and only the first resets a decay clock.
     */
    const SIGHTING = 0;
    const FALSE_POSITIVE = 1;
    const EXPIRATION = 2;

    /**
     * How many object ids the object-sibling join is allowed to read.
     *
     * The aggregate is over every occurrence the viewer can see rather
     * than over the Occurrences tab's current page, so it needs a
     * ceiling. When the ceiling bites the section says so and every
     * count under it reads as a floor — an unlabelled aggregate over a
     * truncated set is the one thing it must not produce.
     */
    const SIBLING_JOIN_CAP = 500;

    /**
     * @param string $value A refanged value, as MISP stores it.
     * @return array
     */
    public static function forValue($value)
    {
        if ($value === '185.234.219.24') {
            return self::malicious();
        }
        if ($value === '104.21.34.198') {
            return self::conflicted();
        }
        if ($value === '8.8.8.8') {
            return self::benign();
        }
        if ($value === '45.155.205.233') {
            return self::flux();
        }
        return self::unknown($value);
    }

    /**
     * A C2 IP: many sightings, an APT galaxy, four organisations, a high
     * decay score and a `to_ids` disagreement.
     *
     * @return array
     */
    private static function malicious()
    {
        $created = '2024-09-14';
        $rows = self::maliciousSightingRows();
        $decay = self::decayModels(
            $rows,
            $created,
            self::maliciousModels()
        );
        $enrichment = self::maliciousEnrichment();
        $analyst = array_merge(
            self::maliciousAnalystData(),
            self::maliciousAnalystTab()
        );
        /*
         * Hoisted rather than built inside the return array: the
         * Timeline tab derives its entries and its undated facts from
         * exactly these, so a second call here would be a second copy
         * of the numbers the rest of the page already claims.
         */
        $occurrences = self::maliciousOccurrences();
        $tags = self::maliciousTags();
        $galaxies = array(
            array(
                'name' => 'APT28',
                'kind' => __('Threat actor') . ' · Sofacy',
                'n' => 2,
            ),
            array(
                'name' => 'Emotet',
                'kind' => __('Malware'),
                'n' => 1,
            ),
            array(
                'name' => 'T1071.001',
                'kind' => __('Attack pattern')
                    . ' · Application Layer Protocol: Web Protocols',
                'n' => 3,
            ),
        );
        $feeds = array(
            array(
                'name' => 'CIRCL OSINT Feed',
                'provider' => 'CIRCL',
                'events' => 3,
            ),
            array(
                'name' => 'Botvrij.eu Data',
                'provider' => 'Botvrij.eu',
                'events' => 1,
            ),
        );

        return array(
            'value' => '185.234.219.24',
            'types' => array(
                array('type' => 'ip-dst', 'count' => 7),
                array('type' => 'ip-src', 'count' => 2),
                array('type' => 'domain|ip', 'count' => 1),
            ),
            'value2_note' => __(
                '1 occurrence has it as the second half of a domain|ip'
            ),
            'counts' => array(
                'occurrences' => 10,
                'sightings' => 47,
                'relationships' => 31,
                /*
                 * Elements awaiting review, not modules. Every other
                 * tab's count is the thing the tab lists, and nine
                 * modules valid for a type is a capability rather
                 * than something to read — the number the reader is
                 * being sent to the tab for is what came back.
                 */
                'enrichment' => $enrichment['pending'],
                'analyst' => $analyst['counts']['items'],
            ),
            'facts' => array(
                array(
                    'label' => __('First seen'),
                    'value' => '2024-09-14',
                    'sub' => __('11 months ago'),
                    'tab' => 'timeline',
                ),
                array(
                    'label' => __('Last seen'),
                    'value' => '2025-08-19',
                    'sub' => __('5 days ago'),
                    'tab' => 'timeline',
                ),
                array(
                    'label' => __('Occurrences'),
                    'value' => '10',
                    'sub' => __('3 types'),
                    'tab' => 'occurrences',
                ),
                array(
                    'label' => __('Events'),
                    'value' => '7',
                    'sub' => __('5 published'),
                    'tab' => 'occurrences',
                ),
                array(
                    'label' => __('Organisations'),
                    'value' => '4',
                    'sub' => __('CIRCL + 3'),
                    'tab' => 'verdict',
                ),
                array(
                    'label' => __('Sightings'),
                    'value' => '47',
                    'sub' => __('1 false positive'),
                    'tab' => 'sightings',
                ),
            ),
            'pivots' => array(
                array(
                    'label' => __('Containing CIDR'),
                    'hint' => '185.234.216.0/22',
                ),
                array(
                    'label' => __('ASN'),
                    'hint' => 'AS204428 SS-Net',
                ),
                array(
                    'label' => __('Geolocation'),
                    'hint' => __('Sofia, BG'),
                ),
                array(
                    'label' => __('Ports seen'),
                    'hint' => '443, 8080, 8443',
                ),
                array(
                    'label' => __('Passive DNS'),
                    'hint' => __('6 hostnames'),
                ),
            ),
            'occurrences' => $occurrences,
            'occurrence_stats' => array(
                'total' => 10,
                'shown' => 6,
                'hidden' => 4,
                'events' => 7,
                'orgs' => 4,
                'deleted' => 1,
            ),
            'occurrence_acl_note' => __(
                'Showing 6 of 10 occurrences. 4 are hidden by distribution'
                . ' rules on events owned by other organisations.'
            ),
            'occurrence_facets' => self::maliciousOccurrenceFacets(),
            'tags' => $tags,
            'galaxies' => $galaxies,
            'analyst' => $analyst,
            'sightings' => array(
                'total' => 47,
                'fp' => 1,
                'expiration' => 0,
                'spark' => self::sightingSpark($rows),
                'reporters' => array(
                    array('org' => 'CIRCL', 'count' => 21),
                    array('org' => 'CthulhuSPRL.be', 'count' => 14),
                    array('org' => 'Team-CIRCL', 'count' => 8),
                    array('org' => 'ORGNAME', 'count' => 4),
                ),
                'last' => __('2 days ago'),
            ),
            'sighting_rows' => $rows,
            'sighting_series' => self::sightingSeries($rows, $decay, $created),
            'sighting_notes' => self::sightingNotes(__(
                'The false positive on 2025-08-01 leaves both curves'
                . ' flat. MISP resets the decay clock on type-0 sightings'
                . ' only, so a contradiction is visible on the axis but'
                . ' moves no score.'
            )),
            'decay' => $decay,
            'warninglists' => array(),
            'warninglists_checked' => 84,
            'correlations' => array(
                'count' => 31,
                'over_correlating' => false,
                'threshold' => 50,
            ),
            'relationships' => self::maliciousRelationships(),
            'enrichment' => $enrichment,
            'external' => array(
                'feeds' => $feeds,
                'servers' => 2,
                'sightingdb' => 1204,
            ),
            /*
             * Seven events hold this value and four of its ten
             * occurrences are hidden, so this is the publication
             * record of the six the viewer can see. Event 1265 is
             * unpublished and contributes nothing — which is the
             * honest reading of an event that has never been
             * published, not a gap.
             */
            'timeline' => self::timeline(array(
                'occurrences' => $occurrences,
                'sightings' => $rows,
                'analyst' => $analyst,
                'tags' => $tags,
                'galaxies' => $galaxies,
                'feeds' => $feeds,
                'publications' => self::maliciousPublications(),
                'window' => array(
                    'from' => '2025-08-01',
                    'to' => self::TODAY,
                ),
                'feeds_as_of' => '2025-08-24 05:30:00',
                'acl_note' => __(
                    'Four of this value\'s ten occurrences are on'
                    . ' events you cannot see. Nothing they contribute'
                    . ' is in this chronology, and how much that is'
                    . ' cannot be counted.'
                ),
            )),
            /*
             * Grouped by occurrence, which is the one thing the seven
             * per-event audit logs cannot do: each of them only ever
             * sees one copy of this value.
             */
            'history' => self::maliciousHistory($occurrences),
            /*
             * The rail card's second line is the NIDS decay score, and
             * it is handed in rather than drawn again here: the Verdict
             * tab and the Sightings tab plot one number two ways, and
             * deriving it twice is how they would stop agreeing.
             */
            'verdict' => self::maliciousVerdict(
                self::decaySpan($rows, $created, $decay[0])
            ),
        );
    }

    /**
     * The occurrence rows the viewing user is allowed to see. Shaped like
     * a `fetchAttributes` result: `Attribute` plus the contained models.
     *
     * @return array
     */
    private static function maliciousOccurrences()
    {
        $amber = array(
            'local' => 0,
            'Tag' => array(
                'name' => 'tlp:amber',
                'colour' => '#FFC000',
                'is_galaxy' => false,
            ),
        );
        $green = array(
            'local' => 0,
            'Tag' => array(
                'name' => 'tlp:green',
                'colour' => '#33FF00',
                'is_galaxy' => false,
            ),
        );
        $osint = array(
            'local' => 0,
            'Tag' => array(
                'name' => 'type:OSINT',
                'colour' => '#004646',
                'is_galaxy' => false,
            ),
        );
        $sofacy = array(
            'local' => 0,
            'Tag' => array(
                'name' => 'misp-galaxy:threat-actor="Sofacy"',
                'colour' => '#8B5CF6',
                'is_galaxy' => true,
            ),
        );
        $reviewed = array(
            'local' => 1,
            'Tag' => array(
                'name' => 'workflow:state="reviewed"',
                'colour' => '#3F51B5',
                'is_galaxy' => false,
            ),
        );

        return array(
            array(
                'Attribute' => array(
                    'id' => 4831022,
                    'uuid' => '5f3c1a8e-2b41-4c7d-9f0a-1e2d3c4b5a60',
                    'event_id' => 1284,
                    'object_id' => 0,
                    'type' => 'ip-dst',
                    'category' => 'Network activity',
                    'to_ids' => 1,
                    'distribution' => 3,
                    'sharing_group_id' => 0,
                    'comment' => 'Emotet C2, port 8080',
                    'first_seen' => '2025-08-12T09:14:00+00:00',
                    'last_seen' => '2025-08-19T21:02:00+00:00',
                    'timestamp' => 1755640920,
                    'deleted' => 0,
                    'object_relation' => null,
                ),
                'Event' => array(
                    'id' => 1284,
                    'info' => 'OSINT - Emotet malspam campaign targeting .lu',
                    'published' => 1,
                    'orgc_id' => 1,
                    'user_id' => 3,
                    'Orgc' => array('id' => 1, 'name' => 'CIRCL'),
                ),
                'Object' => array('id' => null, 'name' => null),
                'SharingGroup' => array('id' => null, 'name' => null),
                'AttributeTag' => array($amber, $osint, $sofacy),
            ),
            array(
                'Attribute' => array(
                    'id' => 4831577,
                    'uuid' => '61a2b3c4-d5e6-4f70-8192-a3b4c5d6e7f8',
                    'event_id' => 1291,
                    'object_id' => 90412,
                    'type' => 'ip-dst',
                    'category' => 'Network activity',
                    'to_ids' => 1,
                    'distribution' => 1,
                    'sharing_group_id' => 0,
                    'comment' => '',
                    'first_seen' => '2025-07-30T14:00:00+00:00',
                    'last_seen' => null,
                    'timestamp' => 1753884000,
                    'deleted' => 0,
                    'object_relation' => 'ip-dst',
                ),
                'Event' => array(
                    'id' => 1291,
                    'info' => 'Phishing kit hosted on compromised WordPress',
                    'published' => 1,
                    'orgc_id' => 2,
                    'user_id' => 41,
                    'Orgc' => array('id' => 2, 'name' => 'CthulhuSPRL.be'),
                ),
                'Object' => array(
                    'id' => 90412,
                    'name' => 'network-connection',
                ),
                'SharingGroup' => array('id' => null, 'name' => null),
                'AttributeTag' => array($green),
                /*
                 * A pending shadow attribute against this occurrence. No
                 * demo value carried one, and the Occurrences tab's State
                 * column has to render the indicator somewhere rather than
                 * describe a state that never appears.
                 */
                'proposal_count' => 1,
            ),
            array(
                'Attribute' => array(
                    'id' => 4829903,
                    'uuid' => '72b3c4d5-e6f7-4081-92a3-b4c5d6e7f809',
                    'event_id' => 1272,
                    'object_id' => 0,
                    'type' => 'ip-src',
                    'category' => 'Network activity',
                    'to_ids' => 0,
                    'distribution' => 4,
                    'sharing_group_id' => 7,
                    'comment' => 'Scanning source, low confidence',
                    'first_seen' => null,
                    'last_seen' => null,
                    'timestamp' => 1750329600,
                    'deleted' => 0,
                    'object_relation' => null,
                ),
                'Event' => array(
                    'id' => 1272,
                    'info' => 'Mass scanning activity against .lu netblocks',
                    'published' => 1,
                    'orgc_id' => 3,
                    'user_id' => 12,
                    'Orgc' => array('id' => 3, 'name' => 'Team-CIRCL'),
                ),
                'Object' => array('id' => null, 'name' => null),
                'SharingGroup' => array(
                    'id' => 7,
                    'name' => 'CIRCL private sector',
                ),
                'AttributeTag' => array($amber, $reviewed),
            ),
            array(
                'Attribute' => array(
                    'id' => 4830441,
                    'uuid' => '83c4d5e6-f708-4192-a3b4-c5d6e7f80912',
                    'event_id' => 1279,
                    'object_id' => 89771,
                    'type' => 'domain|ip',
                    'category' => 'Network activity',
                    'to_ids' => 1,
                    'distribution' => 3,
                    'sharing_group_id' => 0,
                    'comment' => 'cdn-status[.]top resolving to the C2',
                    'first_seen' => '2025-06-02T08:00:00+00:00',
                    'last_seen' => '2025-06-27T08:00:00+00:00',
                    'timestamp' => 1751011200,
                    'deleted' => 0,
                    'object_relation' => 'domain-ip',
                ),
                'Event' => array(
                    'id' => 1279,
                    'info' => 'OSINT - Emotet infrastructure, June 2025',
                    'published' => 1,
                    'orgc_id' => 1,
                    'user_id' => 3,
                    'Orgc' => array('id' => 1, 'name' => 'CIRCL'),
                ),
                'Object' => array('id' => 89771, 'name' => 'domain-ip'),
                'SharingGroup' => array('id' => null, 'name' => null),
                'AttributeTag' => array($amber, $osint),
            ),
            array(
                'Attribute' => array(
                    'id' => 4828810,
                    'uuid' => '94d5e6f7-0819-42a3-b4c5-d6e7f8091223',
                    'event_id' => 1265,
                    'object_id' => 0,
                    'type' => 'ip-dst',
                    'category' => 'Payload delivery',
                    'to_ids' => 0,
                    'distribution' => 0,
                    'sharing_group_id' => 0,
                    'comment' => 'Reported by a member, not verified',
                    'first_seen' => null,
                    'last_seen' => null,
                    'timestamp' => 1744243200,
                    'deleted' => 0,
                    'object_relation' => null,
                ),
                'Event' => array(
                    'id' => 1265,
                    'info' => 'Suspicious download hosts, April 2025',
                    'published' => 0,
                    'orgc_id' => 4,
                    'user_id' => 57,
                    'Orgc' => array('id' => 4, 'name' => 'ORGNAME'),
                ),
                'Object' => array('id' => null, 'name' => null),
                'SharingGroup' => array('id' => null, 'name' => null),
                'AttributeTag' => array(),
            ),
            array(
                'Attribute' => array(
                    'id' => 4827334,
                    'uuid' => 'a5e6f708-192a-43b4-c5d6-e7f809122334',
                    'event_id' => 1251,
                    'object_id' => 0,
                    'type' => 'ip-dst',
                    'category' => 'Network activity',
                    'to_ids' => 1,
                    'distribution' => 3,
                    'sharing_group_id' => 0,
                    'comment' => 'Superseded, kept for history',
                    'first_seen' => '2024-09-14T00:00:00+00:00',
                    'last_seen' => '2024-10-01T00:00:00+00:00',
                    'timestamp' => 1727740800,
                    'deleted' => 1,
                    'object_relation' => null,
                ),
                'Event' => array(
                    'id' => 1251,
                    'info' => 'OSINT - Emotet infrastructure, autumn 2024',
                    'published' => 1,
                    'orgc_id' => 1,
                    'user_id' => 3,
                    'Orgc' => array('id' => 1, 'name' => 'CIRCL'),
                ),
                'Object' => array('id' => null, 'name' => null),
                'SharingGroup' => array('id' => null, 'name' => null),
                'AttributeTag' => array($amber),
            ),
        );
    }

    /**
     * One counted facet row: what it is called, the token its rows
     * carry, and how many rows carry it.
     *
     * The three helpers below exist because these arrays are the same
     * five keys repeated eighty times, and spelling each one out costs
     * two hundred lines that say nothing the helper's name does not.
     *
     * @param string $label
     * @param string $value Token the matching rows carry
     * @param int $count
     * @return array
     */
    private static function facetRow($label, $value, $count)
    {
        return array('label' => $label, 'value' => $value, 'count' => $count);
    }

    /**
     * A distribution facet, named by its level rather than by a label:
     * the rail renders MISP's own badge, which already knows what each
     * level is called and what colour it is.
     *
     * @param int $level
     * @param int $count
     * @return array
     */
    private static function distributionFacet($level, $count)
    {
        return array(
            'level' => $level,
            'value' => (string)$level,
            'count' => $count,
        );
    }

    /**
     * A tag facet carrying the tag record, so the rail renders the real
     * chip rather than the tag's name as text.
     *
     * Galaxy tags are absent by construction: the Tags column does not
     * draw them either, and a filter on something the reader cannot see
     * in the table is not a filter.
     *
     * @param string $name
     * @param string $colour
     * @param int $local
     * @param string $value
     * @param int $count
     * @return array
     */
    private static function tagFacet($name, $colour, $local, $value, $count)
    {
        return array(
            'tag' => array(
                'name' => $name,
                'colour' => $colour,
                'is_galaxy' => false,
            ),
            'local' => $local,
            'value' => $value,
            'count' => $count,
        );
    }

    /**
     * The counted rail beside the occurrence table.
     *
     * Stated here rather than tallied in the template for two reasons
     * the design rests on. The numbers exist in one place, so the rail
     * and the table cannot come to disagree; and the gap between what
     * the instance holds and what this viewer may see is an explicit
     * number rather than an accident of which rows the fixture lists.
     *
     * Every count covers the six occurrences the viewer can open, never
     * the ten the value has. `banner_note` is where that is said out
     * loud, against the one chip where the difference is largest.
     *
     * @return array
     */
    private static function maliciousOccurrenceFacets()
    {
        return array(
            'visible' => 6,
            'total' => 10,
            /*
             * Keyed by facet key. The rail owns the order, the heading
             * and the glyph, because those are the same for every value;
             * a key with no values is a group the rail does not draw.
             */
            'groups' => array(
                'organisation' => array(
                    self::facetRow('CIRCL', '1', 3),
                    self::facetRow('CthulhuSPRL.be', '2', 1),
                    self::facetRow('Team-CIRCL', '3', 1),
                    self::facetRow('ORGNAME', '4', 1),
                ),
                'type' => array(
                    self::facetRow('ip-dst', 'ip-dst', 4),
                    self::facetRow('ip-src', 'ip-src', 1),
                    self::facetRow('domain|ip', 'domain-ip', 1),
                ),
                'category' => array(
                    self::facetRow('Network activity', 'network-activity', 5),
                    self::facetRow('Payload delivery', 'payload-delivery', 1),
                ),
                'ids' => array(
                    self::facetRow(__('to_ids set'), 'set', 4),
                    self::facetRow(__('to_ids unset'), 'unset', 2),
                ),
                'distribution' => array(
                    self::distributionFacet(3, 3),
                    self::distributionFacet(1, 1),
                    self::distributionFacet(4, 1),
                    self::distributionFacet(0, 1),
                ),
                'sharing_group' => array(
                    self::facetRow('CIRCL private sector', '7', 1),
                ),
                'tag' => array(
                    self::tagFacet('tlp:amber', '#FFC000', 0, 'tlp-amber', 4),
                    self::tagFacet('type:OSINT', '#004646', 0, 'type-osint', 2),
                    self::tagFacet('tlp:green', '#33FF00', 0, 'tlp-green', 1),
                    self::tagFacet(
                        'workflow:state="reviewed"',
                        '#3F51B5',
                        1,
                        'workflow-state-reviewed',
                        1
                    ),
                ),
                'state' => array(
                    self::facetRow(
                        __('With a pending proposal'),
                        'proposal',
                        1
                    ),
                ),
            ),
            /*
             * Forty buckets across the value's seen span, each counting
             * the occurrences whose first/last-seen interval covers it.
             * An empty bucket is drawn rather than skipped: a gap in the
             * middle of a run is information.
             */
            'seen_spark' => array(
                1, 1, 1, 0, 0, 0, 0, 0, 0, 0,
                0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
                0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
                1, 1, 1, 1, 0, 0, 0, 1, 0, 1,
            ),
            'seen_from' => '2024-09-14',
            'seen_to' => '2025-08-19',
            'seen_unset' => 2,
            'deleted' => 1,
            /*
             * The one place on the page where the ACL gap is a number:
             * the banner counts every ip-dst the instance holds, the
             * rail counts the ones this viewer may open.
             */
            'banner_note' => array(
                'chip' => 'ip-dst',
                'banner' => 7,
                'rail' => 4,
            ),
        );
    }

    /**
     * Tags aggregated across every occurrence, grouped by taxonomy so the
     * context panel can render TLP, PAP and the labelled scales in their
     * own idiom rather than as raw strings.
     *
     * @return array
     */
    private static function maliciousTags()
    {
        return array(
            array(
                'taxonomy' => 'tlp',
                'conflict' => true,
                'tags' => array(
                    array(
                        'name' => 'tlp:amber',
                        'colour' => '#FFC000',
                        'count' => 4,
                        'local' => false,
                        'orgs' => array('CIRCL', 'Team-CIRCL'),
                    ),
                    array(
                        'name' => 'tlp:green',
                        'colour' => '#33FF00',
                        'count' => 1,
                        'local' => false,
                        'orgs' => array('CthulhuSPRL.be'),
                    ),
                ),
            ),
            array(
                'taxonomy' => 'pap',
                'conflict' => false,
                'tags' => array(
                    array(
                        'name' => 'pap:amber',
                        'colour' => '#FFC000',
                        'count' => 3,
                        'local' => false,
                        'orgs' => array('CIRCL'),
                    ),
                ),
            ),
            array(
                'taxonomy' => 'admiralty-scale',
                'conflict' => false,
                'scale' => array(
                    'label' => __('Source reliability'),
                    'position' => 2,
                    'of' => 6,
                    'reading' => __('B — usually reliable'),
                ),
                'tags' => array(
                    array(
                        'name' => 'admiralty-scale:source-reliability="b"',
                        'colour' => '#00B050',
                        'count' => 3,
                        'local' => false,
                        'orgs' => array('CIRCL'),
                    ),
                ),
            ),
            array(
                'taxonomy' => 'type',
                'conflict' => false,
                'tags' => array(
                    array(
                        'name' => 'type:OSINT',
                        'colour' => '#004646',
                        'count' => 5,
                        'local' => false,
                        'orgs' => array('CIRCL', 'Botvrij.eu'),
                    ),
                ),
            ),
            array(
                'taxonomy' => 'workflow',
                'conflict' => false,
                'tags' => array(
                    array(
                        'name' => 'workflow:state="reviewed"',
                        'colour' => '#3F51B5',
                        'count' => 1,
                        'local' => true,
                        'orgs' => array('CIRCL'),
                    ),
                ),
            ),
        );
    }

    /**
     * The notes and opinions attached to this value, in the shape
     * `AnalystData::fetchForObject` returns and `AnalystData/thread`
     * already renders: one flat item per record, grouped by type.
     *
     * Only the most recent of each are carried — the preview panel shows
     * a handful and the Analyst data tab holds the thread.
     *
     * @return array
     */
    private static function maliciousAnalystData()
    {
        return array(
            'total' => 6,
            'notes' => 2,
            'opinions' => 4,
            'Note' => array(
                array(
                    'uuid' => 'c1d2e3f4-0516-4728-b93a-4c5d6e7f8091',
                    'note' => 'Still answering on 8080 as of this morning.'
                        . ' The TLS certificate is the same self-signed one'
                        . ' the June infrastructure used.',
                    'authors' => 'alice@circl.lu',
                    'created' => '2025-08-22 08:41:07',
                    'distribution' => 3,
                    'Org' => array('id' => 1, 'name' => 'CIRCL'),
                ),
                array(
                    'uuid' => 'd2e3f405-1627-4839-ba4b-5d6e7f809122',
                    'note' => 'Hosting provider notified 2025-08-05, no'
                        . ' response.',
                    'authors' => 'bob@cthulhu.example',
                    'created' => '2025-08-06 15:02:44',
                    'distribution' => 3,
                    'Org' => array('id' => 2, 'name' => 'CthulhuSPRL.be'),
                ),
            ),
            'Opinion' => array(
                array(
                    'uuid' => 'e3f40516-2738-494a-cb5c-6e7f80912233',
                    'opinion' => 85,
                    'comment' => 'Consistent with our own telemetry.',
                    'authors' => 'alice@circl.lu',
                    'created' => '2025-08-21 17:20:00',
                    'distribution' => 3,
                    'Org' => array('id' => 1, 'name' => 'CIRCL'),
                ),
                array(
                    'uuid' => 'f4051627-3849-4a5b-dc6d-7f8091223344',
                    'opinion' => 30,
                    'comment' => 'We saw one hit and it was a scanner.'
                        . ' Not convinced this is C2.',
                    'authors' => 'carol@orgname.example',
                    'created' => '2025-08-18 11:55:12',
                    'distribution' => 3,
                    'Org' => array('id' => 4, 'name' => 'ORGNAME'),
                ),
            ),
        );
    }

    /**
     * The glass-box verdict: a disposition plus every signal, conflict and
     * per-organisation stance that produced it.
     *
     * @param array $nidsCurve The NIDS decay score over the same 90
     *                         days, computed from the sighting rows
     * @return array
     */
    private static function maliciousVerdict(array $nidsCurve)
    {
        return array(
            'disposition' => 'MALICIOUS',
            'score' => 84,
            'confidence' => 'high',
            'summary' => __(
                'Four organisations report this address as Emotet'
                . ' command-and-control infrastructure, with 47 sightings'
                . ' across eleven months and the most recent two days ago.'
                . ' It hits no warninglist. One false-positive sighting and'
                . ' a to_ids disagreement across occurrences temper the'
                . ' confidence but do not change the disposition.'
            ),
            'profile' => 'default-v3',
            'computed_at' => null,
            'acl_note' => __(
                '4 occurrences you cannot see were excluded from this'
                . ' assessment.'
            ),
            'ledger' => self::maliciousLedger(),
            'conflicts' => array(
                array(
                    'kind' => 'to_ids',
                    'title' => __('to_ids disagreement: 6 yes / 4 no'),
                    'note' => __(
                        'Not netted off. Whether this address should fire'
                        . ' an IDS rule is a per-occurrence editorial call,'
                        . ' and the organisations disagree.'
                    ),
                    'yes' => 6,
                    'no' => 4,
                    'evidence' => __(
                        '6 occurrences set yes, 4 set no, across 4'
                        . ' organisations'
                    ),
                    'expanded' => true,
                    'rows' => array(
                        array(
                            'event_id' => 1284,
                            'event_info' => 'OSINT - Emotet malspam'
                                . ' campaign targeting .lu',
                            'org' => 'CIRCL',
                            'type' => 'ip-dst',
                            'to_ids' => 1,
                            'category' => 'Network activity',
                            'comment' => 'Emotet C2, port 8080',
                        ),
                        array(
                            'event_id' => 1272,
                            'event_info' => 'Scanning activity against'
                                . ' member infrastructure',
                            'org' => 'Team-CIRCL',
                            'type' => 'ip-src',
                            'to_ids' => 0,
                            'category' => 'Network activity',
                            'comment' => 'Scanning source, low confidence',
                        ),
                        array(
                            'event_id' => 1265,
                            'event_info' => 'Malware delivery infrastructure'
                                . ' round-up',
                            'org' => 'ORGNAME',
                            'type' => 'ip-dst',
                            'to_ids' => 0,
                            'category' => 'Payload delivery',
                            'comment' => 'Reported by a member',
                        ),
                    ),
                    'actions' => array(
                        array(
                            'label' => __('Set to_ids on all 6 …'),
                            'icon' => 'fas fa-code-compare',
                            'colour' => 'var(--vp-conflict)',
                        ),
                        array(
                            'label' => __('Propose change to other orgs'),
                            'icon' => 'fas fa-code-pull-request',
                            'colour' => 'var(--correlation)',
                        ),
                    ),
                    'confirm_note' => __(
                        'Both actions confirm first, listing 6 rows in 6'
                        . ' events across 4 organisations.'
                    ),
                ),
                array(
                    'kind' => 'tlp',
                    'title' => __('TLP disagreement: amber vs green'),
                    'note' => __(
                        'One organisation shares this address at tlp:green'
                        . ' while three restrict it to tlp:amber.'
                    ),
                    'yes' => 4,
                    'no' => 1,
                    'evidence' => __(
                        '4 occurrences tagged tlp:amber, 1 tlp:green,'
                        . ' across 4 organisations'
                    ),
                    'expanded' => false,
                    'rows' => array(),
                    'actions' => array(),
                    'confirm_note' => null,
                ),
            ),
            'orgs' => array(
                array(
                    'org' => 'CIRCL',
                    'occurrences' => 4,
                    'sightings' => 21,
                    'fp' => 0,
                    'opinion' => 85,
                    'to_ids' => __('yes'),
                    'reliability' => 'B',
                ),
                array(
                    'org' => 'CthulhuSPRL.be',
                    'occurrences' => 3,
                    'sightings' => 14,
                    'fp' => 0,
                    'opinion' => 75,
                    'to_ids' => __('yes'),
                    'reliability' => 'B',
                ),
                array(
                    'org' => 'Team-CIRCL',
                    'occurrences' => 2,
                    'sightings' => 8,
                    'fp' => 0,
                    'opinion' => 60,
                    'to_ids' => __('mixed'),
                    'reliability' => 'C',
                ),
                array(
                    'org' => 'ORGNAME',
                    'occurrences' => 1,
                    'sightings' => 4,
                    'fp' => 1,
                    'opinion' => 30,
                    'to_ids' => __('no'),
                    'reliability' => 'D',
                ),
            ),
            'composition' => array(
                array(
                    'label' => __('Reporting breadth'),
                    'points' => 37,
                    'colour' => 'var(--event)',
                ),
                array(
                    'label' => __('Sightings'),
                    'points' => 24,
                    'colour' => 'var(--sighting)',
                ),
                array(
                    'label' => __('Attribution'),
                    'points' => 19,
                    'colour' => 'var(--galaxy)',
                ),
                array(
                    'label' => __('Lifecycle'),
                    'points' => 18,
                    'colour' => 'var(--correlation)',
                ),
                /*
                 * The two downward signals, collected. Not the
                 * contradictions — those are marked `unresolved` in the
                 * ledger and contribute nothing, and labelling this line
                 * after them would send a reader tracing -14 to the
                 * wrong rows.
                 */
                array(
                    'label' => __('Signals against'),
                    'points' => -14,
                    'colour' => 'var(--bs-danger)',
                ),
            ),
            'composition_note' => __(
                'Weights come from the default-v3 profile. An instance'
                . ' admin can edit the profile; the tab always names the'
                . ' one in force.'
            ),
            'curves' => array(
                array(
                    'label' => __('Synthesised verdict'),
                    'colour' => 'var(--vp-mal)',
                    'data' => self::maliciousVerdictCurve(),
                ),
                array(
                    'label' => __('NIDS decay score'),
                    'colour' => 'var(--vp-conflict)',
                    'dashed' => true,
                    'data' => $nidsCurve,
                ),
            ),
            'curves_span' => __('90 days'),
            'curves_note' => __(
                'Three step-ups, each a sighting burst; the dips between'
                . ' are decay. The 2025-07-21 step is the APT28 galaxy'
                . ' being attached.'
            ),
            /*
             * Excluded on purpose, and said so. The ACL exclusion leads:
             * a score computed from a subset should say which subset.
             */
            'not_counted' => array(
                array(
                    'title' => __('4 occurrences'),
                    'note' => __(
                        'Outside your ACL — excluded, not hidden. The'
                        . ' score you see is the score for your'
                        . ' permissions.'
                    ),
                ),
                array(
                    'title' => __('Feed presence alone'),
                    'note' => __(
                        'Feeds that merely mirror CIRCL OSINT are not'
                        . ' independent corroboration and score once, not'
                        . ' three times.'
                    ),
                ),
                array(
                    'title' => __('Self-sightings'),
                    'note' => __(
                        '3 sightings from the same org that created the'
                        . ' attribute, within an hour of creation.'
                    ),
                ),
            ),
            /*
             * What it would take to move the disposition. A verdict that
             * cannot say what would falsify it is an opinion.
             */
            'changers' => array(
                array(
                    'direction' => 'down',
                    'text' => __(
                        'A warninglist hit of category known → CONFLICTED'
                        . ' immediately, whatever the score.'
                    ),
                ),
                array(
                    'direction' => 'down',
                    'text' => __(
                        '3 or more false-positive sightings from 2+ orgs'
                        . ' → drops to SUSPICIOUS.'
                    ),
                ),
                array(
                    'direction' => 'down',
                    'text' => __(
                        'No sighting for 45 days → decay takes the score'
                        . ' under 50.'
                    ),
                ),
            ),
            'changer_actions' => array(
                array(
                    'label' => __('Mark false positive'),
                    'icon' => 'fas fa-flag',
                    'colour' => 'var(--vp-mal)',
                    'emphasis' => true,
                ),
                array(
                    'label' => __('Record an opinion'),
                    'icon' => 'fas fa-scale-balanced',
                    'colour' => 'var(--analystData)',
                ),
                array(
                    'label' => __('Notify me if the verdict changes'),
                    'icon' => 'fas fa-bell',
                    'colour' => 'var(--correlation)',
                ),
            ),
        );
    }

    /**
     * The synthesised verdict over the same 90 days: sighting bursts
     * step it up, decay pulls it back between them.
     *
     * @return array
     */
    private static function maliciousVerdictCurve()
    {
        return array(
            46, 45, 44, 43, 58, 57, 56, 55, 54, 53,
            52, 51, 50, 49, 66, 65, 64, 63, 62, 61,
            60, 59, 58, 57, 56, 72, 71, 70, 69, 68,
            67, 66, 78, 84, 83, 82, 81, 83, 85, 84,
        );
    }

    /**
     * Signal rows grouped by kind, each traceable to the panel that
     * produced it.
     *
     * @return array
     */
    private static function maliciousLedger()
    {
        return array(
            array(
                'kind' => __('Reporting'),
                'note' => __('who reported it, and how widely'),
                'signals' => array(
                    array(
                        'direction' => 'up',
                        'weight' => 'strong',
                        'contribution' => 28,
                        'signal' => __(
                            '4 independent organisations reported it'
                        ),
                        'evidence' => 'CIRCL, CthulhuSPRL.be, Team-CIRCL,'
                            . ' ORGNAME',
                        'source' => __('Occurrences'),
                        'as_of' => '2025-08-19',
                    ),
                    array(
                        'direction' => 'up',
                        'weight' => 'moderate',
                        'contribution' => 9,
                        'signal' => __('5 of 7 events are published'),
                        'evidence' => __(
                            'Published events carry more weight than drafts'
                        ),
                        'source' => __('Occurrences'),
                        'as_of' => '2025-08-19',
                    ),
                ),
            ),
            array(
                'kind' => __('Sightings'),
                'note' => __('who has seen it, and how recently'),
                'signals' => array(
                    array(
                        'direction' => 'up',
                        'weight' => 'strong',
                        'contribution' => 24,
                        'signal' => __(
                            '47 sightings from 4 orgs, last 2 days ago'
                        ),
                        'evidence' => __(
                            'Recency dominates: 12 sightings in the last'
                            . ' 30 days'
                        ),
                        'source' => __('Sightings'),
                        'as_of' => '2025-08-22',
                    ),
                    array(
                        'direction' => 'down',
                        'weight' => 'moderate',
                        'contribution' => -6,
                        'signal' => __(
                            '1 false-positive sighting (ORGNAME)'
                        ),
                        'evidence' => '2025-04-11',
                        'source' => __('Sightings'),
                        'as_of' => '2025-04-11',
                    ),
                ),
            ),
            array(
                'kind' => __('Attribution'),
                'note' => __('what it has been linked to'),
                'signals' => array(
                    array(
                        'direction' => 'up',
                        'weight' => 'moderate',
                        'contribution' => 14,
                        'signal' => __('Linked to galaxy: APT28 (2 events)'),
                        'evidence' => 'misp-galaxy:threat-actor="Sofacy"',
                        'source' => __('Context'),
                        'as_of' => '2025-07-30',
                    ),
                    array(
                        'direction' => 'up',
                        'weight' => 'weak',
                        'contribution' => 5,
                        'signal' => __('T1071.001 on 3 occurrences'),
                        'evidence' => __(
                            'Application Layer Protocol: Web Protocols'
                        ),
                        'source' => __('Context'),
                        'as_of' => '2025-07-30',
                    ),
                ),
            ),
            array(
                'kind' => __('Lifecycle'),
                'note' => __('whether it is still worth acting on'),
                'signals' => array(
                    array(
                        'direction' => 'up',
                        'weight' => 'moderate',
                        'contribution' => 12,
                        'signal' => __(
                            'NIDS Simple Decaying Model: 78/100, above'
                            . ' threshold'
                        ),
                        'evidence' => __('Threshold 60'),
                        'source' => __('Lifecycle'),
                        'as_of' => '2025-08-24',
                    ),
                    array(
                        'direction' => 'down',
                        'weight' => 'moderate',
                        'contribution' => -8,
                        'signal' => __(
                            'Decayed under Phishing Model (34/100)'
                        ),
                        'evidence' => __('Threshold 50'),
                        'source' => __('Lifecycle'),
                        'as_of' => '2025-08-24',
                    ),
                    array(
                        'direction' => 'up',
                        'weight' => 'weak',
                        'contribution' => 6,
                        'signal' => __('No warninglist hit'),
                        'evidence' => __('84 lists checked'),
                        'source' => __('Lifecycle'),
                        'as_of' => '2025-08-24',
                    ),
                ),
            ),
        );
    }

    /**
     * A Cloudflare-fronted IP: three organisations report it as phishing
     * infrastructure, and it sits inside a `known`-category warninglist
     * range shared by millions of unrelated sites.
     *
     * The interesting case, and the reason the Verdict tab has a second
     * layout: the evidence is real on both sides, and averaging it away
     * would destroy the only useful thing the page can say.
     *
     * @return array
     */
    private static function conflicted()
    {
        $created = '2025-02-03';
        $rows = self::conflictedSightingRows();
        $decay = self::decayModels(
            $rows,
            $created,
            self::conflictedModels()
        );
        $enrichment = self::conflictedEnrichment();
        $analyst = array_merge(
            self::conflictedAnalystData(),
            self::conflictedAnalystTab()
        );
        /*
         * Hoisted rather than built inside the return array: the
         * Timeline tab derives its entries and its undated facts from
         * exactly these, so a second copy here would be a second set
         * of the numbers the rest of the page already claims.
         */
        $occurrences = self::conflictedOccurrences();
        $tags = self::conflictedTags();
        $galaxies = array(
            array(
                'name' => 'Gamaredon',
                'kind' => __('Threat actor') . ' · Primitive Bear',
                'n' => 1,
            ),
            array(
                'name' => 'T1583.003',
                'kind' => __('Attack pattern')
                    . ' · Acquire Infrastructure: Virtual Private'
                    . ' Server',
                'n' => 2,
            ),
        );
        $feeds = array(
            array(
                'name' => 'Phishtank OSINT Feed',
                'provider' => 'Phishtank',
                'events' => 6,
            ),
            array(
                'name' => 'Botvrij.eu Data',
                'provider' => 'Botvrij.eu',
                'events' => 2,
            ),
        );

        return array(
            'value' => '104.21.34.198',
            'types' => array(
                array('type' => 'ip-dst', 'count' => 5),
                array('type' => 'domain|ip', 'count' => 3),
                array('type' => 'ip-src', 'count' => 1),
            ),
            'value2_note' => __(
                '3 occurrences have it as the second half of a domain|ip'
            ),
            'counts' => array(
                'occurrences' => 9,
                'sightings' => 63,
                'relationships' => 1847,
                'enrichment' => $enrichment['pending'],
                'analyst' => $analyst['counts']['items'],
            ),
            'facts' => array(
                array(
                    'label' => __('First seen'),
                    'value' => '2025-02-03',
                    'sub' => __('6 months ago'),
                    'tab' => 'timeline',
                ),
                array(
                    'label' => __('Last seen'),
                    'value' => '2025-08-23',
                    'sub' => __('yesterday'),
                    'tab' => 'timeline',
                ),
                array(
                    'label' => __('Occurrences'),
                    'value' => '9',
                    'sub' => __('3 types'),
                    'tab' => 'occurrences',
                ),
                array(
                    'label' => __('Events'),
                    'value' => '6',
                    'sub' => __('6 published'),
                    'tab' => 'occurrences',
                ),
                array(
                    'label' => __('Organisations'),
                    'value' => '3',
                    'sub' => __('CIRCL + 2'),
                    'tab' => 'verdict',
                ),
                array(
                    'label' => __('Sightings'),
                    'value' => '63',
                    'sub' => __('9 false positives'),
                    'tab' => 'sightings',
                ),
            ),
            'pivots' => array(
                array(
                    'label' => __('Containing CIDR'),
                    'hint' => '104.21.32.0/20',
                ),
                array(
                    'label' => __('ASN'),
                    'hint' => 'AS13335 Cloudflare',
                ),
                array(
                    'label' => __('Geolocation'),
                    'hint' => __('Anycast'),
                ),
                array(
                    'label' => __('Ports seen'),
                    'hint' => '443',
                ),
                array(
                    'label' => __('Passive DNS'),
                    'hint' => __('2,411 hostnames'),
                ),
            ),
            'occurrences' => $occurrences,
            'occurrence_stats' => array(
                'total' => 9,
                'shown' => 5,
                'hidden' => 4,
                'events' => 6,
                'orgs' => 3,
                'deleted' => 0,
            ),
            'occurrence_acl_note' => __(
                'Showing 5 of 9 occurrences. 4 are hidden by distribution'
                . ' rules on events owned by other organisations.'
            ),
            'occurrence_facets' => self::conflictedOccurrenceFacets(),
            'tags' => $tags,
            'galaxies' => $galaxies,
            'analyst' => $analyst,
            'sightings' => array(
                'total' => 63,
                'fp' => 9,
                'expiration' => 2,
                'spark' => self::sightingSpark($rows),
                'reporters' => array(
                    array('org' => 'CIRCL', 'count' => 26),
                    array('org' => 'Botvrij.eu', 'count' => 19),
                    array('org' => 'CthulhuSPRL.be', 'count' => 13),
                    array('org' => 'ORGNAME', 'count' => 5),
                ),
                'last' => __('yesterday'),
            ),
            'sighting_rows' => $rows,
            'sighting_series' => self::sightingSeries($rows, $decay, $created),
            'sighting_notes' => self::sightingNotes(__(
                'Nine of these 63 reports are false positives and two are'
                . ' expirations, and neither kind touches either curve.'
                . ' MISP resets the decay clock on type-0 sightings only,'
                . ' so eleven contradictions are visible on the axis and'
                . ' none of them is in the score.'
            )),
            'decay' => $decay,
            'warninglists' => array(
                array(
                    'name' => 'List of known Cloudflare IP ranges',
                    'version' => '20250714',
                    'category' => 'known',
                    'matched' => '104.21.0.0/20',
                    'type' => 'cidr',
                ),
            ),
            'warninglists_checked' => 84,
            'correlations' => array(
                'count' => 1847,
                'over_correlating' => true,
                'threshold' => 50,
            ),
            'relationships' => self::conflictedRelationships(),
            'enrichment' => $enrichment,
            'external' => array(
                'feeds' => $feeds,
                'servers' => 3,
                'sightingdb' => 84109,
            ),
            /*
             * The busiest timeline of the three, and the one where the
             * spine earns its place: nine occurrences over six months
             * on shared infrastructure, so the question a reader
             * arrives with is *when*, not *whether*.
             */
            'timeline' => self::timeline(array(
                'occurrences' => $occurrences,
                'sightings' => $rows,
                'analyst' => $analyst,
                'tags' => $tags,
                'galaxies' => $galaxies,
                'feeds' => $feeds,
                'publications' => self::conflictedPublications(),
                'window' => array(
                    'from' => '2025-08-01',
                    'to' => self::TODAY,
                ),
                'feeds_as_of' => '2025-08-24 04:15:00',
                'acl_note' => __(
                    'Four of this value\'s nine occurrences are on'
                    . ' events you cannot see, and nothing they'
                    . ' contribute is in this chronology.'
                ),
            )),
            /*
             * One of the five sections carries twenty-eight entries,
             * which is what the conflict looks like as a record rather
             * than as a score — and the only section on this page that
             * pages.
             */
            'history' => self::conflictedHistory($occurrences),
            /*
             * The opinion distribution is handed in rather than
             * written again: the Verdict tab's histogram and the
             * Analyst tab's are the same ten buckets over the same
             * opinions, and deriving them twice is how they would
             * stop agreeing.
             */
            'verdict' => self::conflictedVerdict(
                $analyst['standing']['aggregate']
            ),
        );
    }

    /**
     * Five visible occurrences across six events: phishing landing pages
     * behind a CDN, plus one organisation that reported the front-end
     * address out of its own web logs.
     *
     * @return array
     */
    private static function conflictedOccurrences()
    {
        $clear = array(
            'local' => 0,
            'Tag' => array(
                'name' => 'tlp:clear',
                'colour' => '#FFFFFF',
                'is_galaxy' => false,
            ),
        );
        $green = array(
            'local' => 0,
            'Tag' => array(
                'name' => 'tlp:green',
                'colour' => '#33FF00',
                'is_galaxy' => false,
            ),
        );
        $osint = array(
            'local' => 0,
            'Tag' => array(
                'name' => 'type:OSINT',
                'colour' => '#004646',
                'is_galaxy' => false,
            ),
        );
        $phishing = array(
            'local' => 0,
            'Tag' => array(
                'name' => 'phishing:distribution-mechanism="hosting"',
                'colour' => '#EF476F',
                'is_galaxy' => false,
            ),
        );
        $falsePositive = array(
            'local' => 1,
            'Tag' => array(
                'name' => 'false-positive:risk="high"',
                'colour' => '#9d174d',
                'is_galaxy' => false,
            ),
        );

        return array(
            array(
                'Attribute' => array(
                    'id' => 5102884,
                    'uuid' => '7a1b2c3d-4e5f-4061-9273-8495a6b7c8d9',
                    'event_id' => 1402,
                    'object_id' => 0,
                    'type' => 'ip-dst',
                    'category' => 'Network activity',
                    'to_ids' => 1,
                    'distribution' => 3,
                    'sharing_group_id' => 0,
                    'comment' => 'Phishing landing page, .lu bank brand',
                    'first_seen' => '2025-08-18T06:30:00+00:00',
                    'last_seen' => '2025-08-23T19:45:00+00:00',
                    'timestamp' => 1755978300,
                    'deleted' => 0,
                    'object_relation' => null,
                ),
                'Event' => array(
                    'id' => 1402,
                    'info' => 'Phishing campaign impersonating a .lu bank',
                    'published' => 1,
                    'orgc_id' => 1,
                    'user_id' => 3,
                    'Orgc' => array('id' => 1, 'name' => 'CIRCL'),
                ),
                'Object' => array('id' => null, 'name' => null),
                'SharingGroup' => array('id' => null, 'name' => null),
                'AttributeTag' => array($green, $osint, $phishing),
            ),
            array(
                'Attribute' => array(
                    'id' => 5103011,
                    'uuid' => '8b2c3d4e-5f60-4172-a384-95a6b7c8d9e0',
                    'event_id' => 1402,
                    'object_id' => 96331,
                    'type' => 'domain|ip',
                    'category' => 'Network activity',
                    'to_ids' => 0,
                    'distribution' => 3,
                    'sharing_group_id' => 0,
                    'comment' => 'secure-login-lu.example resolves here',
                    'first_seen' => '2025-08-18T06:31:00+00:00',
                    'last_seen' => '2025-08-23T19:45:00+00:00',
                    'timestamp' => 1755978360,
                    'deleted' => 0,
                    'object_relation' => 'domain-ip',
                ),
                'Event' => array(
                    'id' => 1402,
                    'info' => 'Phishing campaign impersonating a .lu bank',
                    'published' => 1,
                    'orgc_id' => 1,
                    'user_id' => 3,
                    'Orgc' => array('id' => 1, 'name' => 'CIRCL'),
                ),
                'Object' => array('id' => 96331, 'name' => 'domain-ip'),
                'SharingGroup' => array('id' => null, 'name' => null),
                'AttributeTag' => array($green, $osint),
            ),
            array(
                'Attribute' => array(
                    'id' => 5098720,
                    'uuid' => '9c3d4e5f-6071-4283-b495-a6b7c8d9e0f1',
                    'event_id' => 1388,
                    'object_id' => 0,
                    'type' => 'ip-dst',
                    'category' => 'Payload delivery',
                    'to_ids' => 1,
                    'distribution' => 3,
                    'sharing_group_id' => 0,
                    'comment' => 'Second-stage download host',
                    'first_seen' => '2025-07-02T11:00:00+00:00',
                    'last_seen' => '2025-07-29T08:12:00+00:00',
                    'timestamp' => 1753776720,
                    'deleted' => 0,
                    'object_relation' => null,
                ),
                'Event' => array(
                    'id' => 1388,
                    'info' => 'Gamaredon infrastructure, July batch',
                    'published' => 1,
                    'orgc_id' => 2,
                    'user_id' => 9,
                    'Orgc' => array('id' => 2, 'name' => 'CthulhuSPRL.be'),
                ),
                'Object' => array('id' => null, 'name' => null),
                'SharingGroup' => array('id' => null, 'name' => null),
                'AttributeTag' => array($clear, $osint),
            ),
            array(
                'Attribute' => array(
                    'id' => 5076193,
                    'uuid' => 'ad4e5f60-7182-4394-c5a6-b7c8d9e0f102',
                    'event_id' => 1341,
                    'object_id' => 0,
                    'type' => 'ip-src',
                    'category' => 'Network activity',
                    'to_ids' => 0,
                    'distribution' => 1,
                    'sharing_group_id' => 0,
                    'comment' => 'Seen in web logs — likely just the CDN',
                    'first_seen' => '2025-05-14T09:20:00+00:00',
                    'last_seen' => '2025-05-14T09:20:00+00:00',
                    'timestamp' => 1747214400,
                    'deleted' => 0,
                    'object_relation' => null,
                ),
                'Event' => array(
                    'id' => 1341,
                    'info' => 'Suspicious traffic against a member portal',
                    'published' => 1,
                    'orgc_id' => 4,
                    'user_id' => 21,
                    'Orgc' => array('id' => 4, 'name' => 'ORGNAME'),
                ),
                'Object' => array('id' => null, 'name' => null),
                'SharingGroup' => array('id' => null, 'name' => null),
                'AttributeTag' => array($falsePositive),
            ),
            array(
                'Attribute' => array(
                    'id' => 5061455,
                    'uuid' => 'be5f6071-8293-44a5-d6b7-c8d9e0f10213',
                    'event_id' => 1309,
                    'object_id' => 94018,
                    'type' => 'domain|ip',
                    'category' => 'Network activity',
                    'to_ids' => 1,
                    'distribution' => 3,
                    'sharing_group_id' => 0,
                    'comment' => 'account-verify-lu.example',
                    'first_seen' => '2025-02-03T14:05:00+00:00',
                    'last_seen' => '2025-03-11T10:40:00+00:00',
                    'timestamp' => 1741689600,
                    'deleted' => 0,
                    'object_relation' => 'domain-ip',
                ),
                'Event' => array(
                    'id' => 1309,
                    'info' => 'Phishing kit reuse across .lu targets',
                    'published' => 1,
                    'orgc_id' => 1,
                    'user_id' => 3,
                    'Orgc' => array('id' => 1, 'name' => 'CIRCL'),
                ),
                'Object' => array('id' => 94018, 'name' => 'domain-ip'),
                'SharingGroup' => array('id' => null, 'name' => null),
                'AttributeTag' => array($green, $osint, $phishing),
            ),
        );
    }

    /**
     * The high-cardinality case's rail: same eight groups, no soft-
     * deleted rows and no pending proposal, so the row-state group is
     * empty and the rail draws nothing where it would have been.
     *
     * @return array
     */
    private static function conflictedOccurrenceFacets()
    {
        return array(
            'visible' => 5,
            'total' => 9,
            'groups' => array(
                'organisation' => array(
                    self::facetRow('CIRCL', '1', 3),
                    self::facetRow('CthulhuSPRL.be', '2', 1),
                    self::facetRow('ORGNAME', '4', 1),
                ),
                'type' => array(
                    self::facetRow('ip-dst', 'ip-dst', 2),
                    self::facetRow('domain|ip', 'domain-ip', 2),
                    self::facetRow('ip-src', 'ip-src', 1),
                ),
                'category' => array(
                    self::facetRow('Network activity', 'network-activity', 4),
                    self::facetRow('Payload delivery', 'payload-delivery', 1),
                ),
                'ids' => array(
                    self::facetRow(__('to_ids set'), 'set', 3),
                    self::facetRow(__('to_ids unset'), 'unset', 2),
                ),
                'distribution' => array(
                    self::distributionFacet(3, 4),
                    self::distributionFacet(1, 1),
                ),
                'sharing_group' => array(),
                'tag' => array(
                    self::tagFacet('type:OSINT', '#004646', 0, 'type-osint', 4),
                    self::tagFacet('tlp:green', '#33FF00', 0, 'tlp-green', 3),
                    self::tagFacet(
                        'phishing:distribution-mechanism="hosting"',
                        '#EF476F',
                        0,
                        'phishing-distribution-mechanism-hosting',
                        2
                    ),
                    self::tagFacet('tlp:clear', '#FFFFFF', 0, 'tlp-clear', 1),
                    self::tagFacet(
                        'false-positive:risk="high"',
                        '#9d174d',
                        1,
                        'false-positive-risk-high',
                        1
                    ),
                ),
                'state' => array(),
            ),
            'seen_spark' => array(
                1, 1, 1, 1, 1, 1, 1, 1, 0, 0,
                0, 0, 0, 0, 0, 0, 0, 0, 0, 1,
                0, 0, 0, 0, 0, 0, 0, 0, 0, 1,
                1, 1, 1, 1, 1, 0, 0, 0, 2, 2,
            ),
            'seen_from' => '2025-02-03',
            'seen_to' => '2025-08-23',
            'seen_unset' => 0,
            'deleted' => 0,
            'banner_note' => array(
                'chip' => 'ip-dst',
                'banner' => 5,
                'rail' => 2,
            ),
        );
    }

    /**
     * The taxonomies that disagree here are the point: one organisation
     * has locally marked the value a high false-positive risk while the
     * others tag it as phishing infrastructure.
     *
     * @return array
     */
    private static function conflictedTags()
    {
        return array(
            array(
                'taxonomy' => 'tlp',
                'conflict' => true,
                'tags' => array(
                    array(
                        'name' => 'tlp:green',
                        'colour' => '#33FF00',
                        'count' => 3,
                        'local' => false,
                        'orgs' => array('CIRCL'),
                    ),
                    array(
                        'name' => 'tlp:clear',
                        'colour' => '#FFFFFF',
                        'count' => 1,
                        'local' => false,
                        'orgs' => array('CthulhuSPRL.be'),
                    ),
                ),
            ),
            array(
                'taxonomy' => 'false-positive',
                'conflict' => false,
                'tags' => array(
                    array(
                        'name' => 'false-positive:risk="high"',
                        'colour' => '#9d174d',
                        'count' => 1,
                        'local' => true,
                        'orgs' => array('ORGNAME'),
                    ),
                ),
            ),
            array(
                'taxonomy' => 'phishing',
                'conflict' => false,
                'tags' => array(
                    array(
                        'name' => 'phishing:distribution-mechanism="hosting"',
                        'colour' => '#EF476F',
                        'count' => 2,
                        'local' => false,
                        'orgs' => array('CIRCL'),
                    ),
                ),
            ),
            array(
                'taxonomy' => 'admiralty-scale',
                'conflict' => true,
                'scale' => array(
                    'label' => __('Source reliability'),
                    'position' => 3,
                    'of' => 6,
                    'reading' => __('B to D — organisations disagree'),
                ),
                'tags' => array(
                    array(
                        'name' => 'admiralty-scale:source-reliability="b"',
                        'colour' => '#00B050',
                        'count' => 2,
                        'local' => false,
                        'orgs' => array('CIRCL'),
                    ),
                    array(
                        'name' => 'admiralty-scale:source-reliability="d"',
                        'colour' => '#FFC000',
                        'count' => 1,
                        'local' => false,
                        'orgs' => array('ORGNAME'),
                    ),
                ),
            ),
            array(
                'taxonomy' => 'type',
                'conflict' => false,
                'tags' => array(
                    array(
                        'name' => 'type:OSINT',
                        'colour' => '#004646',
                        'count' => 4,
                        'local' => false,
                        'orgs' => array('CIRCL', 'CthulhuSPRL.be'),
                    ),
                ),
            ),
        );
    }

    /**
     * @return array
     */
    private static function conflictedAnalystData()
    {
        return array(
            'total' => 7,
            'notes' => 3,
            'opinions' => 4,
            'Note' => array(
                array(
                    'uuid' => 'aa112233-4455-4667-8899-aabbccddeeff',
                    'note' => 'This is a Cloudflare edge address. The'
                        . ' phishing sites are real, but blocking the IP'
                        . ' takes out every other site behind the same'
                        . ' edge. Block the hostname, not the address.',
                    'authors' => 'dave@orgname.example',
                    'created' => '2025-08-20 09:12:31',
                    'distribution' => 3,
                    'Org' => array('id' => 4, 'name' => 'ORGNAME'),
                ),
                array(
                    'uuid' => 'bb223344-5566-4778-99aa-bbccddeeff00',
                    'note' => 'Six distinct phishing hostnames resolved'
                        . ' here between February and August. The reuse is'
                        . ' the signal; the address is only where it'
                        . ' surfaced.',
                    'authors' => 'alice@circl.lu',
                    'created' => '2025-08-19 16:04:55',
                    'distribution' => 3,
                    'Org' => array('id' => 1, 'name' => 'CIRCL'),
                ),
            ),
            'Opinion' => array(
                array(
                    'uuid' => 'cc334455-6677-4889-aabb-ccddeeff0011',
                    'opinion' => 80,
                    'comment' => 'Confirmed phishing, twice this month.',
                    'authors' => 'alice@circl.lu',
                    'created' => '2025-08-23 10:31:00',
                    'distribution' => 3,
                    'Org' => array('id' => 1, 'name' => 'CIRCL'),
                ),
                array(
                    'uuid' => 'dd445566-7788-499a-bbcc-ddeeff001122',
                    'opinion' => 10,
                    'comment' => 'Shared CDN address. Actioning it would'
                        . ' cause an outage for unrelated services.',
                    'authors' => 'dave@orgname.example',
                    'created' => '2025-08-20 09:15:00',
                    'distribution' => 3,
                    'Org' => array('id' => 4, 'name' => 'ORGNAME'),
                ),
            ),
        );
    }

    /**
     * A value nobody has reported. The page still renders in full: for a
     * value-centric view, "we know nothing about this" is an answer.
     *
     * @param string $value
     * @return array
     */
    private static function unknown($value)
    {
        return array(
            'value' => $value,
            'types' => array(),
            'value2_note' => null,
            'counts' => array(
                'occurrences' => 0,
                'sightings' => 0,
                'relationships' => 0,
                'enrichment' => 0,
                'analyst' => 0,
            ),
            'facts' => array(
                array('label' => __('First seen'), 'value' => '—'),
                array('label' => __('Last seen'), 'value' => '—'),
                array('label' => __('Occurrences'), 'value' => '0'),
                array('label' => __('Events'), 'value' => '0'),
                array('label' => __('Organisations'), 'value' => '0'),
                array('label' => __('Sightings'), 'value' => '0'),
            ),
            'pivots' => array(),
            'occurrences' => array(),
            'occurrence_stats' => array(
                'total' => 0,
                'shown' => 0,
                'hidden' => 0,
                'events' => 0,
                'orgs' => 0,
                'deleted' => 0,
            ),
            'occurrence_acl_note' => null,
            /*
             * Null rather than a set of empty groups: a facet rail of
             * zeroes claims there are rows to narrow. The tab renders no
             * rail at all and lets the table carry the one empty state.
             */
            'occurrence_facets' => null,
            'tags' => array(),
            'galaxies' => array(),
            /*
             * Nothing written, and the tab still renders: the standing
             * panel's strip is replaced by one empty line and the
             * thread keeps its composer, so a value nobody has argued
             * about reads as usable rather than broken.
             */
            'analyst' => array_merge(
                array(
                    'total' => 0,
                    'notes' => 0,
                    'opinions' => 0,
                    'Note' => array(),
                    'Opinion' => array(),
                ),
                self::analystTab(array(), array())
            ),
            'sightings' => array(
                'total' => 0,
                'fp' => 0,
                'expiration' => 0,
                'spark' => array(),
                'reporters' => array(),
                'last' => null,
            ),
            /*
             * Null, not an empty series. There is no attribute here to
             * date an axis from, so a chart drawn over the last 90 days
             * would be inventing a window for a value that has none —
             * the panel renders its empty state instead. That is not the
             * same as the value with occurrences and no sightings, which
             * does get axes, and does get a score.
             */
            'sighting_rows' => array(),
            'sighting_series' => null,
            'sighting_notes' => null,
            'decay' => array(),
            'warninglists' => array(),
            'warninglists_checked' => 84,
            'correlations' => array(
                'count' => 0,
                'over_correlating' => false,
                'threshold' => 50,
            ),
            'relationships' => self::emptyRelationships(),
            /*
             * Null, and deliberately not an empty timeline. An empty
             * one is a value MISP watched and recorded nothing about;
             * this is a value MISP has never held. Drawing twelve
             * empty months and seven lanes over it would be inventing
             * a period of silence that never happened.
             */
            'timeline' => null,
            /*
             * No `history` key at all, where the Timeline gets an
             * explicit null. A value MISP has never held has nothing
             * for an audit log to have recorded or failed to record,
             * so the panel has no state to choose between: the tab's
             * own placeholder is the answer, and a `recorded` flag
             * here would make the question look answerable.
             */
            /*
             * A value with no occurrence still gets the full rail.
             * There is no attribute row to read a type from, so the
             * type is inferred from the value's own shape and the
             * panel says so — which is what lets the untouched state
             * be a page rather than an empty one.
             */
            'enrichment' => self::unknownEnrichment($value),
            'external' => array(
                'feeds' => array(),
                'servers' => 0,
                'sightingdb' => 0,
            ),
            'verdict' => array(
                'disposition' => 'UNKNOWN',
                'score' => null,
                'confidence' => 'none',
                'summary' => __(
                    'No organisation on this instance has reported this'
                    . ' value, and nothing you can see references it.'
                ),
                'profile' => 'default-v3',
                'computed_at' => null,
                'acl_note' => null,
                'ledger' => array(),
                'conflicts' => array(),
                'orgs' => array(),
                'composition' => array(),
            ),
        );
    }

    /**
     * The verdict that refuses to resolve.
     *
     * Carries everything the malicious layout does, plus the keys only
     * the conflicted layout uses: the rule that produced the state, the
     * two opposed cases, the signals that count for neither, and the
     * resolutions — each naming exactly what it would write.
     *
     * There is deliberately no score. A number here would be an average
     * of two incompatible readings, and would read as certainty the
     * evidence does not support.
     *
     * @return array
     */
    private static function conflictedVerdict(array $opinions)
    {
        return array(
            'disposition' => 'CONFLICTED',
            'score' => null,
            'confidence' => 'low',
            'summary' => __(
                'Three organisations report this address as phishing'
                . ' infrastructure, with 63 sightings and the most recent'
                . ' yesterday. It also sits inside a known-category'
                . ' warninglist range — a Cloudflare edge shared by'
                . ' millions of unrelated sites — and correlates with'
                . ' 1,847 other attributes. Both readings are supported.'
                . ' Averaging them would produce a number that describes'
                . ' neither.'
            ),
            'profile' => 'default-v3',
            'computed_at' => null,
            'acl_note' => __(
                '4 occurrences you cannot see were excluded from this'
                . ' assessment.'
            ),
            'rule' => array(
                'name' => 'conflict:known-infrastructure-vs-reporting',
                'text' => __(
                    'A known-category warninglist hit together with three'
                    . ' or more independent malicious reports emits'
                    . ' CONFLICTED. Neither signal is discounted, because'
                    . ' both are true at once: the address is shared'
                    . ' infrastructure and it is being abused.'
                ),
            ),
            'tug' => array(
                'malicious' => 71,
                'benign' => 66,
                'unresolved' => 12,
            ),
            'warninglist' => array(
                'name' => 'List of known Cloudflare IP ranges',
                'version' => '20250714',
                'category' => 'known',
                'matched' => '104.21.0.0/20',
                'type' => 'cidr',
                'note' => __(
                    'Category `known` means widely-used infrastructure,'
                    . ' not a false positive. The hit says an action'
                    . ' against this address will hit unrelated services'
                    . ' too — it does not say the reports are wrong.'
                ),
            ),
            'ledger' => self::conflictedLedger(),
            'cases' => self::conflictedCases(),
            /*
             * Ambiguities in the evidence itself — a split that could
             * fall either way. Distinct from `not_counted`, which is
             * evidence the profile deliberately set aside.
             */
            'ambiguities' => array(
                array(
                    'title' => __(
                        'to_ids disagreement — 5 yes / 4 no'
                    ),
                    'note' => __(
                        'The split follows the type, not the'
                        . ' organisation: every domain|ip occurrence is'
                        . ' yes, every bare ip-dst is no. That is'
                        . ' agreement, expressed badly.'
                    ),
                ),
                array(
                    'title' => __(
                        'CIRCL both sighted it most and filed most false'
                        . ' positives'
                    ),
                    'note' => __(
                        '26 sightings and 7 of the 9 false positives,'
                        . ' from two different teams in the same'
                        . ' organisation. Neither side can claim CIRCL'
                        . ' as support.'
                    ),
                ),
            ),
            /*
             * Set aside on purpose, and said so. A signal silently
             * dropped looks the same as a signal nobody found.
             */
            'not_counted' => array(
                array(
                    'title' => __('1,847 correlations'),
                    'note' => __(
                        'Above the over-correlation threshold, so'
                        . ' correlation is not treated as evidence here.'
                        . ' It is itself a hint that the value is shared'
                        . ' infrastructure.'
                    ),
                ),
                array(
                    'title' => __('Feed presence'),
                    'note' => __(
                        '2 feeds list it, and both ingest the same CIRCL'
                        . ' OSINT source.'
                    ),
                ),
            ),
            'orgs' => array(
                array(
                    'org' => 'CIRCL',
                    'occurrences' => 4,
                    'sightings' => 26,
                    'fp' => 0,
                    'opinion' => 80,
                    'to_ids' => __('yes'),
                    'reliability' => 'B',
                    'reads' => __('Phishing infrastructure'),
                    'side' => 'malicious',
                ),
                array(
                    'org' => 'CthulhuSPRL.be',
                    'occurrences' => 3,
                    'sightings' => 13,
                    'fp' => 2,
                    'opinion' => 60,
                    'to_ids' => __('mixed'),
                    'reliability' => 'B',
                    'reads' => __('Abused, but block the hostname'),
                    'side' => 'malicious',
                ),
                array(
                    'org' => 'Botvrij.eu',
                    'occurrences' => 1,
                    'sightings' => 19,
                    'fp' => 0,
                    'opinion' => null,
                    'to_ids' => __('yes'),
                    'reliability' => 'C',
                    'reads' => __('Feed relay, no stated position'),
                    'side' => null,
                ),
                array(
                    'org' => 'ORGNAME',
                    'occurrences' => 1,
                    'sightings' => 5,
                    'fp' => 7,
                    'opinion' => 10,
                    'to_ids' => __('no'),
                    'reliability' => 'D',
                    'reads' => __('Shared CDN, not actionable'),
                    'side' => 'benign',
                ),
            ),
            'resolutions' => self::conflictedResolutions(),
            /*
             * The Analyst tab's aggregate, unchanged, plus the reading
             * of it this card is for. Both tabs then draw one
             * distribution rather than two versions of it.
             */
            'opinions' => array_merge($opinions, array(
                'note' => sprintf(
                    __(
                        'Bimodal, not uncertain. The mean of %1$s is the'
                        . ' one number on this page that means nothing —'
                        . ' two positions, nothing between %2$s and'
                        . ' %3$s.'
                    ),
                    $opinions['mean_label'],
                    $opinions['gap']['from'],
                    $opinions['gap']['to']
                ),
            )),
            'curves' => array(
                array(
                    'label' => __('Malicious case'),
                    'colour' => 'var(--vp-mal)',
                    'data' => self::conflictedWeightMalicious(),
                ),
                array(
                    'label' => __('Benign case'),
                    'colour' => 'var(--vp-ben)',
                    'data' => self::conflictedWeightBenign(),
                ),
            ),
            'curves_span' => __('90 days'),
            'curves_note' => __(
                'The two cases crossed on 2025-07-09, when the Cloudflare'
                . ' list was updated to cover this range. The value has'
                . ' been conflicted for 46 days.'
            ),
        );
    }

    /**
     * The same signals the two cases are built from, in the ledger shape
     * the Overview verdict card reads: grouped by the panel that
     * produced them, with the side expressed as a direction.
     *
     * Derived rather than written out again, so the card and the tab
     * cannot disagree about what the evidence is.
     *
     * @return array
     */
    private static function conflictedLedger()
    {
        $groups = array();
        foreach (self::conflictedCases() as $case) {
            $up = $case['side'] === 'malicious';
            foreach ($case['rows'] as $row) {
                $groups[$row['source']][] = array(
                    'direction' => $up ? 'up' : 'down',
                    'weight' => $row['weight'],
                    'contribution' => $up
                        ? $row['points']
                        : -$row['points'],
                    'signal' => $row['signal'],
                    'evidence' => $row['evidence'],
                    'source' => $row['source'],
                    'as_of' => '2025-08-23',
                );
            }
        }

        $ledger = array();
        foreach ($groups as $kind => $signals) {
            $ledger[] = array('kind' => $kind, 'signals' => $signals);
        }
        return $ledger;
    }

    /**
     * The two cases, kept side by side rather than summed. Each row is a
     * signal with its weight, the evidence behind it and the panel that
     * produced it.
     *
     * @return array
     */
    private static function conflictedCases()
    {
        return array(
            array(
                'side' => 'malicious',
                'title' => __('Argues malicious'),
                'weight' => 71,
                'rows' => array(
                    array(
                        'weight' => 'strong',
                        'points' => 24,
                        'signal' => __(
                            '3 independent organisations reported it'
                        ),
                        'evidence' => 'CIRCL, CthulhuSPRL.be, Botvrij.eu',
                        'source' => __('Occurrences'),
                    ),
                    array(
                        'weight' => 'strong',
                        'points' => 18,
                        'signal' => __(
                            '6 phishing hostnames resolved here since'
                            . ' February'
                        ),
                        'evidence' => __(
                            'Reuse across unrelated .lu targets'
                        ),
                        'source' => __('Relationships'),
                    ),
                    array(
                        'weight' => 'moderate',
                        'points' => 11,
                        'signal' => __(
                            '54 non-false-positive sightings, last'
                            . ' yesterday'
                        ),
                        'evidence' => __('26 of them from CIRCL'),
                        'source' => __('Sightings'),
                    ),
                    array(
                        'weight' => 'moderate',
                        'points' => 10,
                        'signal' => __(
                            'Above threshold under the Phishing Model'
                            . ' (66/100)'
                        ),
                        'evidence' => __('Threshold 50'),
                        'source' => __('Lifecycle'),
                    ),
                    array(
                        'weight' => 'weak',
                        'points' => 8,
                        'signal' => __(
                            'Tagged phishing on 2 occurrences'
                        ),
                        'evidence' => 'phishing:distribution-mechanism'
                            . '="hosting"',
                        'source' => __('Context'),
                    ),
                ),
            ),
            array(
                'side' => 'benign',
                'title' => __('Argues benign or false positive'),
                'weight' => 66,
                'rows' => array(
                    array(
                        'weight' => 'strong',
                        'points' => 28,
                        'signal' => __(
                            'Known-category warninglist hit'
                        ),
                        'evidence' => __(
                            'Cloudflare edge range 104.21.0.0/20'
                        ),
                        'source' => __('Lifecycle'),
                    ),
                    array(
                        'weight' => 'moderate',
                        'points' => 19,
                        'signal' => __(
                            '2,411 unrelated hostnames share this address'
                        ),
                        'evidence' => __('Passive DNS'),
                        'source' => __('Relationships'),
                    ),
                    array(
                        'weight' => 'moderate',
                        'points' => 15,
                        'signal' => __(
                            '9 false-positive sightings from 2'
                            . ' organisations'
                        ),
                        'evidence' => __('ORGNAME 7, CthulhuSPRL.be 2'),
                        'source' => __('Sightings'),
                    ),
                    array(
                        'weight' => 'weak',
                        'points' => 4,
                        'signal' => __(
                            'Decayed under the NIDS Simple Decaying Model'
                            . ' (41/100)'
                        ),
                        'evidence' => __(
                            'Threshold 60 — already counted against the'
                            . ' Phishing Model score'
                        ),
                        'source' => __('Lifecycle'),
                    ),
                ),
            ),
        );
    }

    /**
     * The four ways out, each stated as the write it would perform. A
     * resolution card that only named a verdict would hide the fact that
     * resolving a conflict is an edit somebody has to own.
     *
     * @return array
     */
    private static function conflictedResolutions()
    {
        return array(
            array(
                'title' => __('Accept malicious'),
                'icon' => 'fas fa-triangle-exclamation',
                'colour' => 'var(--vp-mal)',
                'note' => __(
                    'Treat the reports as decisive and the warninglist as'
                    . ' context.'
                ),
                'writes' => __(
                    'Tags every visible occurrence'
                    . ' `false-positive:risk="low"` and leaves `to_ids`'
                    . ' as each organisation set it.'
                ),
            ),
            array(
                'title' => __('Accept shared infrastructure'),
                'icon' => 'fas fa-cloud',
                'colour' => 'var(--vp-ben)',
                'note' => __(
                    'Keep the intelligence, stop it firing detections.'
                ),
                'writes' => __(
                    'Clears `to_ids` on all 5 visible occurrences and'
                    . ' tags them `false-positive:risk="high"`.'
                ),
            ),
            array(
                'title' => __('Narrow to the hostnames'),
                'icon' => 'fas fa-scissors',
                'colour' => 'var(--primary)',
                'note' => __(
                    'The reuse is real; the address is only where it'
                    . ' surfaced.'
                ),
                'writes' => __(
                    'Clears `to_ids` on the 3 ip-dst / ip-src'
                    . ' occurrences and leaves the domain|ip pairs'
                    . ' untouched.'
                ),
            ),
            array(
                'title' => __('Leave it conflicted'),
                'icon' => 'fas fa-code-branch',
                'colour' => 'var(--vp-conflict)',
                'note' => __(
                    'A recorded disagreement is more useful than a'
                    . ' premature answer.'
                ),
                'writes' => __(
                    'Writes nothing. Adds an analyst note stating why the'
                    . ' conflict was left standing.'
                ),
            ),
        );
    }

    /**
     * Google Public DNS: nine occurrences across six events, every one
     * of them describing infrastructure a sample used rather than an
     * indicator to act on, and a `false_positive`-category warninglist
     * hit that says so outright.
     *
     * The third artboard, and the one that shows the ledger reads in
     * both directions. A value everything points away from is the same
     * kind of argument as one everything points at — same bands, same
     * table, same arithmetic — which is why it shares the layout rather
     * than getting a third one.
     *
     * @return array
     */
    private static function benign()
    {
        $created = '2024-11-02';
        $rows = self::benignSightingRows();
        $decay = self::decayModels($rows, $created, self::benignModels());
        $enrichment = self::benignEnrichment();
        $analyst = array_merge(
            self::benignAnalystData(),
            self::benignAnalystTab()
        );
        /*
         * Hoisted rather than built inside the return array: the
         * Timeline tab derives its entries and its undated facts from
         * exactly these, so a second copy here would be a second set
         * of the numbers the rest of the page already claims.
         */
        $occurrences = self::benignOccurrences();
        $tags = self::benignTags();

        return array(
            'value' => '8.8.8.8',
            'types' => array(
                array('type' => 'ip-dst', 'count' => 6),
                array('type' => 'domain|ip', 'count' => 2),
                array('type' => 'ip-src', 'count' => 1),
            ),
            'value2_note' => __(
                '2 occurrences have it as the second half of a domain|ip'
            ),
            'counts' => array(
                'occurrences' => 9,
                'sightings' => 17,
                'relationships' => 21904,
                'enrichment' => $enrichment['pending'],
                'analyst' => $analyst['counts']['items'],
            ),
            'facts' => array(
                array(
                    'label' => __('First seen'),
                    'value' => '2024-11-02',
                    'sub' => __('10 months ago'),
                    'tab' => 'timeline',
                ),
                array(
                    'label' => __('Last seen'),
                    'value' => '2025-08-21',
                    'sub' => __('3 days ago'),
                    'tab' => 'timeline',
                ),
                array(
                    'label' => __('Occurrences'),
                    'value' => '9',
                    'sub' => __('3 types'),
                    'tab' => 'occurrences',
                ),
                array(
                    'label' => __('Events'),
                    'value' => '6',
                    'sub' => __('6 published'),
                    'tab' => 'occurrences',
                ),
                array(
                    'label' => __('Organisations'),
                    'value' => '5',
                    'sub' => __('CIRCL + 4'),
                    'tab' => 'verdict',
                ),
                array(
                    'label' => __('Sightings'),
                    'value' => '17',
                    'sub' => __('11 false positives'),
                    'tab' => 'sightings',
                ),
            ),
            'pivots' => array(
                array(
                    'label' => __('Containing CIDR'),
                    'hint' => '8.8.8.0/24',
                ),
                array(
                    'label' => __('ASN'),
                    'hint' => 'AS15169 Google LLC',
                ),
                array(
                    'label' => __('Geolocation'),
                    'hint' => __('Mountain View, US'),
                ),
                array(
                    'label' => __('Reverse DNS'),
                    'hint' => 'dns.google',
                ),
                array(
                    'label' => __('Ports seen'),
                    'hint' => '53, 853',
                ),
                array(
                    'label' => __('Passive DNS'),
                    'hint' => __('1 hostname'),
                ),
            ),
            'occurrences' => $occurrences,
            'occurrence_stats' => array(
                'total' => 9,
                'shown' => 5,
                'hidden' => 4,
                'events' => 6,
                // Five, not four: the five occurrences the viewer can
                // see already carry five distinct owners, so a total of
                // four would be smaller than its own visible subset.
                'orgs' => 5,
                'deleted' => 1,
            ),
            'occurrence_acl_note' => __(
                'Showing 5 of 9 occurrences. 4 are hidden by distribution'
                . ' rules on events owned by other organisations.'
            ),
            'occurrence_facets' => self::benignOccurrenceFacets(),
            'tags' => $tags,
            /*
             * Deliberately empty, and load-bearing: "nothing has ever
             * been attributed to this address" is one of the signals in
             * the ledger, and the Context panel has to be able to say
             * so rather than render a gap.
             */
            'galaxies' => array(),
            'analyst' => $analyst,
            'sightings' => array(
                'total' => 17,
                'fp' => 11,
                'expiration' => 0,
                'spark' => self::sightingSpark($rows),
                'reporters' => array(
                    array('org' => 'CIRCL', 'count' => 10),
                    array('org' => 'CthulhuSPRL.be', 'count' => 5),
                    array('org' => 'Botvrij.eu', 'count' => 2),
                ),
                'last' => __('12 days ago'),
            ),
            'sighting_rows' => $rows,
            'sighting_series' => self::sightingSeries($rows, $decay, $created),
            'sighting_notes' => self::sightingNotes(__(
                'Eleven of these 17 reports are false positives, and the'
                . ' NIDS curve steps up six times — once per actual'
                . ' sighting, never once per contradiction. On a public'
                . ' resolver that is the whole story: the reports that'
                . ' disagree outnumber the ones that agree, and the score'
                . ' has never heard about it.'
            )),
            'decay' => $decay,
            'warninglists' => array(
                array(
                    'name' => 'List of known IPv4 public DNS resolvers',
                    'version' => '20250802',
                    'category' => 'false_positive',
                    'matched' => '8.8.8.8',
                    'type' => 'string',
                ),
            ),
            'warninglists_checked' => 84,
            'correlations' => array(
                'count' => 21904,
                'over_correlating' => true,
                'threshold' => 50,
            ),
            'relationships' => self::benignRelationships(),
            'enrichment' => $enrichment,
            'external' => array(
                'feeds' => array(),
                'servers' => 1,
                'sightingdb' => 0,
            ),
            /*
             * No galaxy cluster and no feed carries this value, so two
             * of the three undated kinds simply do not arise — which
             * is why the off-axis strip is built from what the value
             * has rather than from a fixed list of three. An empty
             * strip on a resolver is the truth; three zeroes would be
             * an insinuation.
             */
            'timeline' => self::timeline(array(
                'occurrences' => $occurrences,
                'sightings' => $rows,
                'analyst' => $analyst,
                'tags' => $tags,
                'galaxies' => array(),
                'feeds' => array(),
                'publications' => self::benignPublications(),
                'window' => array(
                    'from' => '2025-08-01',
                    'to' => self::TODAY,
                ),
                'acl_note' => __(
                    'Four of this value\'s nine occurrences are on'
                    . ' events you cannot see, and nothing they'
                    . ' contribute is in this chronology.'
                ),
            )),
            /*
             * Recorded and empty, which is not the same as unrecorded:
             * audit logging came on after everything that has ever
             * happened to a resolver everybody already trusts.
             */
            'history' => self::benignHistory($occurrences),
            'verdict' => self::benignVerdict(
                self::decaySpan($rows, $created, $decay[0])
            ),
        );
    }

    /**
     * Five visible occurrences across six events: a resolver named in
     * malware configuration and dynamic-analysis output, plus the one
     * organisation that reported it out of an exfiltration incident and
     * set `to_ids` on it.
     *
     * @return array
     */
    private static function benignOccurrences()
    {
        $clear = array(
            'local' => 0,
            'Tag' => array(
                'name' => 'tlp:clear',
                'colour' => '#FFFFFF',
                'is_galaxy' => false,
            ),
        );
        $osint = array(
            'local' => 0,
            'Tag' => array(
                'name' => 'type:OSINT',
                'colour' => '#004646',
                'is_galaxy' => false,
            ),
        );
        $falsePositive = array(
            'local' => 0,
            'Tag' => array(
                'name' => 'false-positive:risk="high"',
                'colour' => '#33FF00',
                'is_galaxy' => false,
            ),
        );
        $reviewed = array(
            'local' => 1,
            'Tag' => array(
                'name' => 'workflow:state="reviewed"',
                'colour' => '#3F51B5',
                'is_galaxy' => false,
            ),
        );

        return array(
            array(
                'Attribute' => array(
                    'id' => 4902118,
                    'uuid' => '7b1c2d3e-4f50-4617-a8b9-c0d1e2f30415',
                    'event_id' => 1301,
                    'object_id' => 0,
                    'type' => 'ip-dst',
                    'category' => 'Network activity',
                    'to_ids' => 0,
                    'distribution' => 3,
                    'sharing_group_id' => 0,
                    'comment' => 'Resolver the sample used, not an'
                        . ' indicator',
                    'first_seen' => '2025-08-14T07:22:00+00:00',
                    'last_seen' => '2025-08-21T18:40:00+00:00',
                    'timestamp' => 1755801600,
                    'deleted' => 0,
                    'object_relation' => null,
                ),
                'Event' => array(
                    'id' => 1301,
                    'info' => 'Emotet malspam campaign - full IOC set',
                    'published' => 1,
                    'orgc_id' => 1,
                    'user_id' => 3,
                    'Orgc' => array('id' => 1, 'name' => 'CIRCL'),
                ),
                'Object' => array('id' => null, 'name' => null),
                'SharingGroup' => array('id' => null, 'name' => null),
                'AttributeTag' => array(
                    $clear, $osint, $falsePositive, $reviewed,
                ),
            ),
            array(
                'Attribute' => array(
                    'id' => 4899043,
                    'uuid' => '8c2d3e4f-5061-4728-b9ca-d1e2f3041526',
                    'event_id' => 1298,
                    'object_id' => 90886,
                    'type' => 'domain|ip',
                    'category' => 'Network activity',
                    'to_ids' => 0,
                    'distribution' => 3,
                    'sharing_group_id' => 0,
                    'comment' => 'DNS server in the malware configuration',
                    'first_seen' => '2025-07-30T11:05:00+00:00',
                    'last_seen' => '2025-07-30T11:05:00+00:00',
                    'timestamp' => 1753873500,
                    'deleted' => 0,
                    'object_relation' => 'ip',
                ),
                'Event' => array(
                    'id' => 1298,
                    'info' => 'AsyncRAT sample analysis - config dump',
                    'published' => 1,
                    'orgc_id' => 2,
                    'user_id' => 8,
                    'Orgc' => array('id' => 2, 'name' => 'CthulhuSPRL.be'),
                ),
                'Object' => array(
                    'id' => 90886,
                    'name' => 'network-connection',
                ),
                'SharingGroup' => array('id' => null, 'name' => null),
                'AttributeTag' => array($clear, $falsePositive),
            ),
            array(
                'Attribute' => array(
                    'id' => 4884560,
                    'uuid' => '9d3e4f50-6172-4839-cadb-e2f304152637',
                    'event_id' => 1288,
                    'object_id' => 0,
                    'type' => 'ip-src',
                    'category' => 'Network activity',
                    'to_ids' => 0,
                    'distribution' => 2,
                    'sharing_group_id' => 0,
                    'comment' => 'Queries seen towards this resolver during'
                        . ' the tunnelling attempt',
                    'first_seen' => '2025-06-11T02:47:00+00:00',
                    'last_seen' => '2025-06-11T05:19:00+00:00',
                    'timestamp' => 1749614340,
                    'deleted' => 0,
                    'object_relation' => null,
                ),
                'Event' => array(
                    'id' => 1288,
                    'info' => 'DNS tunnelling attempt against a member',
                    'published' => 1,
                    'orgc_id' => 3,
                    'user_id' => 5,
                    'Orgc' => array('id' => 3, 'name' => 'Team-CIRCL'),
                ),
                'Object' => array('id' => null, 'name' => null),
                'SharingGroup' => array('id' => null, 'name' => null),
                'AttributeTag' => array($clear),
            ),
            /*
             * The occurrence the verdict argues with. Kept in the visible
             * five on purpose: a page that states a contradiction and
             * then hides the row behind it is asking to be believed.
             */
            array(
                'Attribute' => array(
                    'id' => 4871209,
                    'uuid' => 'ae4f5061-7283-494a-dbec-f30415263748',
                    'event_id' => 1276,
                    'object_id' => 0,
                    'type' => 'ip-dst',
                    'category' => 'Network activity',
                    'to_ids' => 1,
                    'distribution' => 1,
                    'sharing_group_id' => 0,
                    'comment' => 'Blocked at the perimeter after the alert',
                    'first_seen' => '2025-04-18T13:31:00+00:00',
                    'last_seen' => '2025-04-18T13:31:00+00:00',
                    'timestamp' => 1745069460,
                    'deleted' => 0,
                    'object_relation' => null,
                ),
                'Event' => array(
                    'id' => 1276,
                    'info' => 'Exfiltration over DNS - incident 2025-0442',
                    'published' => 1,
                    'orgc_id' => 4,
                    'user_id' => 11,
                    'Orgc' => array('id' => 4, 'name' => 'ORGNAME'),
                ),
                'Object' => array('id' => null, 'name' => null),
                'SharingGroup' => array('id' => null, 'name' => null),
                'AttributeTag' => array($clear),
            ),
            array(
                'Attribute' => array(
                    'id' => 4855731,
                    'uuid' => 'bf506172-8394-4a5b-ecfd-041526374859',
                    'event_id' => 1259,
                    'object_id' => 0,
                    'type' => 'ip-dst',
                    'category' => 'Network activity',
                    'to_ids' => 0,
                    'distribution' => 3,
                    'sharing_group_id' => 0,
                    'comment' => 'Withdrawn - imported in error from the'
                        . ' feed, kept for provenance',
                    'first_seen' => '2024-11-02T09:00:00+00:00',
                    'last_seen' => '2024-11-02T09:00:00+00:00',
                    'timestamp' => 1730538000,
                    'deleted' => 1,
                    'object_relation' => null,
                ),
                'Event' => array(
                    'id' => 1259,
                    'info' => 'Botvrij.eu feed import 2024-11-02',
                    'published' => 1,
                    'orgc_id' => 5,
                    'user_id' => 14,
                    'Orgc' => array('id' => 5, 'name' => 'Botvrij.eu'),
                ),
                'Object' => array('id' => null, 'name' => null),
                'SharingGroup' => array('id' => null, 'name' => null),
                'AttributeTag' => array($clear, $osint),
            ),
        );
    }

    /**
     * The benign case's rail. Five organisations report it once each,
     * so the organisation group is five bars of equal length — which is
     * the reading: nobody has looked at this value twice.
     *
     * @return array
     */
    private static function benignOccurrenceFacets()
    {
        return array(
            'visible' => 5,
            'total' => 9,
            'groups' => array(
                'organisation' => array(
                    self::facetRow('CIRCL', '1', 1),
                    self::facetRow('CthulhuSPRL.be', '2', 1),
                    self::facetRow('Team-CIRCL', '3', 1),
                    self::facetRow('ORGNAME', '4', 1),
                    self::facetRow('Botvrij.eu', '5', 1),
                ),
                'type' => array(
                    self::facetRow('ip-dst', 'ip-dst', 3),
                    self::facetRow('domain|ip', 'domain-ip', 1),
                    self::facetRow('ip-src', 'ip-src', 1),
                ),
                'category' => array(
                    self::facetRow('Network activity', 'network-activity', 5),
                ),
                'ids' => array(
                    self::facetRow(__('to_ids set'), 'set', 1),
                    self::facetRow(__('to_ids unset'), 'unset', 4),
                ),
                'distribution' => array(
                    self::distributionFacet(3, 3),
                    self::distributionFacet(2, 1),
                    self::distributionFacet(1, 1),
                ),
                'sharing_group' => array(),
                'tag' => array(
                    self::tagFacet('tlp:clear', '#FFFFFF', 0, 'tlp-clear', 5),
                    self::tagFacet('type:OSINT', '#004646', 0, 'type-osint', 2),
                    self::tagFacet(
                        'false-positive:risk="high"',
                        '#33FF00',
                        0,
                        'false-positive-risk-high',
                        2
                    ),
                    self::tagFacet(
                        'workflow:state="reviewed"',
                        '#3F51B5',
                        1,
                        'workflow-state-reviewed',
                        1
                    ),
                ),
                'state' => array(),
            ),
            'seen_spark' => array(
                1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
                0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
                0, 0, 1, 0, 0, 0, 0, 0, 0, 0,
                1, 0, 0, 0, 0, 0, 1, 0, 1, 1,
            ),
            'seen_from' => '2024-11-02',
            'seen_to' => '2025-08-21',
            'seen_unset' => 0,
            'deleted' => 1,
            'banner_note' => array(
                'chip' => 'ip-dst',
                'banner' => 6,
                'rail' => 3,
            ),
        );
    }

    /**
     * The taxonomies on a value nobody wants to act on: TLP wide open,
     * an explicit `false-positive` rating, and a review that has already
     * happened.
     *
     * @return array
     */
    private static function benignTags()
    {
        return array(
            array(
                'taxonomy' => 'tlp',
                'conflict' => false,
                'tags' => array(
                    array(
                        'name' => 'tlp:clear',
                        'colour' => '#FFFFFF',
                        'count' => 9,
                        'local' => false,
                        'orgs' => array(
                            'CIRCL', 'CthulhuSPRL.be', 'Team-CIRCL',
                            'ORGNAME', 'Botvrij.eu',
                        ),
                    ),
                ),
            ),
            array(
                'taxonomy' => 'false-positive',
                'conflict' => false,
                'tags' => array(
                    array(
                        'name' => 'false-positive:risk="high"',
                        'colour' => '#33FF00',
                        'count' => 3,
                        'local' => false,
                        'orgs' => array('CIRCL', 'CthulhuSPRL.be'),
                    ),
                ),
            ),
            array(
                'taxonomy' => 'admiralty-scale',
                'conflict' => false,
                'scale' => array(
                    'label' => __('Source reliability'),
                    'position' => 2,
                    'of' => 6,
                    'reading' => __('B — usually reliable'),
                ),
                'tags' => array(
                    array(
                        'name' => 'admiralty-scale:source-reliability="b"',
                        'colour' => '#00B050',
                        'count' => 2,
                        'local' => false,
                        'orgs' => array('CIRCL'),
                    ),
                ),
            ),
            array(
                'taxonomy' => 'type',
                'conflict' => false,
                'tags' => array(
                    array(
                        'name' => 'type:OSINT',
                        'colour' => '#004646',
                        'count' => 4,
                        'local' => false,
                        'orgs' => array('CIRCL', 'Botvrij.eu'),
                    ),
                ),
            ),
            array(
                'taxonomy' => 'workflow',
                'conflict' => false,
                'tags' => array(
                    array(
                        'name' => 'workflow:state="reviewed"',
                        'colour' => '#3F51B5',
                        'count' => 2,
                        'local' => true,
                        'orgs' => array('CIRCL'),
                    ),
                ),
            ),
        );
    }

    /**
     * The notes and opinions on a value the analysts have already argued
     * about — which is why the opinion split is one of the
     * contradictions the verdict carries rather than something the page
     * averages away.
     *
     * @return array
     */
    private static function benignAnalystData()
    {
        return array(
            'total' => 5,
            'notes' => 2,
            'opinions' => 3,
            'Note' => array(
                array(
                    'uuid' => 'c0d1e2f3-0415-4263-9748-59a6b7c8d9e0',
                    'note' => 'This is Google Public DNS. It turns up in'
                        . ' almost every dynamic-analysis run because the'
                        . ' sample resolves through it. Worth keeping for'
                        . ' context, not worth blocking.',
                    'authors' => 'alice@circl.lu',
                    'created' => '2025-08-21 09:12:33',
                    'distribution' => 3,
                    'Org' => array('id' => 1, 'name' => 'CIRCL'),
                ),
                array(
                    'uuid' => 'd1e2f304-1526-4374-8859-a6b7c8d9e0f1',
                    'note' => 'Off our blocklist after the 2025-0442'
                        . ' review. The alert was the exfiltration'
                        . ' traffic, not the resolver carrying it. The'
                        . ' attribute stays as it is until the incident'
                        . ' report is closed.',
                    'authors' => 'dave@orgname.example',
                    'created' => '2025-08-04 16:48:20',
                    'distribution' => 3,
                    'Org' => array('id' => 4, 'name' => 'ORGNAME'),
                ),
            ),
            'Opinion' => array(
                array(
                    'uuid' => 'e2f30415-2637-4859-a6b7-c8d9e0f10213',
                    'opinion' => 8,
                    'comment' => 'Context only. We would object to this'
                        . ' being distributed with to_ids set.',
                    'authors' => 'alice@circl.lu',
                    'created' => '2025-08-21 09:15:02',
                    'distribution' => 3,
                    'Org' => array('id' => 1, 'name' => 'CIRCL'),
                ),
                array(
                    'uuid' => 'f3041526-3748-4960-b7c8-d9e0f1021324',
                    'opinion' => 15,
                    'comment' => 'Agreed. It is in the config because'
                        . ' every config has a resolver in it.',
                    'authors' => 'bob@cthulhu.example',
                    'created' => '2025-08-19 10:33:41',
                    'distribution' => 3,
                    'Org' => array('id' => 2, 'name' => 'CthulhuSPRL.be'),
                ),
                array(
                    'uuid' => '04152637-4859-4a71-c8d9-e0f102132435',
                    'opinion' => 70,
                    'comment' => 'It carried the exfiltrated data out of'
                        . ' our network. I take the point about shared'
                        . ' infrastructure and I still want it flagged.',
                    'authors' => 'carol@orgname.example',
                    'created' => '2025-08-05 08:27:55',
                    'distribution' => 3,
                    'Org' => array('id' => 4, 'name' => 'ORGNAME'),
                ),
            ),
        );
    }

    /**
     * The verdict that resolves away from malicious.
     *
     * Carries exactly the keys the malicious verdict does, plus the
     * `warninglist` the conflicted one uses — a benign call almost
     * always rests on a listing, and the band that explains what the
     * category does and does not claim is the same band either way.
     *
     * The score is support for the disposition, not a malice reading:
     * 91 says the benign call is well evidenced. That is the same axis
     * the malicious verdict's 84 is on, which is what makes the two
     * comparable at a glance.
     *
     * @param array $nidsCurve The NIDS decay score over the same 90
     *                         days, computed from the sighting rows
     * @return array
     */
    private static function benignVerdict(array $nidsCurve)
    {
        return array(
            'disposition' => 'BENIGN',
            'score' => 91,
            'confidence' => 'high',
            'summary' => __(
                'Every organisation that reported this address reported'
                . ' it as infrastructure a sample used rather than as an'
                . ' indicator to act on. It is Google Public DNS, it sits'
                . ' on a false_positive-category warninglist, and eleven'
                . ' of the seventeen sightings are false positives. One'
                . ' organisation still sets to_ids on its occurrence'
                . ' after an exfiltration incident — a defensible reading'
                . ' of that incident, and not a reading of the address.'
            ),
            'profile' => 'default-v3',
            'computed_at' => null,
            'acl_note' => __(
                '4 occurrences you cannot see were excluded from this'
                . ' assessment.'
            ),
            'warninglist' => array(
                'name' => 'List of known IPv4 public DNS resolvers',
                'version' => '20250802',
                'category' => 'false_positive',
                'matched' => '8.8.8.8',
                'type' => 'string',
                'note' => __(
                    'Category `false_positive` means reports about this'
                    . ' value are usually collateral — the sample really'
                    . ' did resolve through this address, and the address'
                    . ' is still not the indicator. It does not say the'
                    . ' reporting organisations were wrong about their'
                    . ' incidents.'
                ),
            ),
            'ledger' => self::benignLedger(),
            'conflicts' => array(
                array(
                    'kind' => 'to_ids',
                    'title' => __('to_ids disagreement: 1 yes / 8 no'),
                    'note' => __(
                        'Not netted off. ORGNAME set it during an'
                        . ' exfiltration incident and has not cleared it,'
                        . ' which is a judgement about the incident'
                        . ' rather than about the address.'
                    ),
                    'yes' => 1,
                    'no' => 8,
                    'evidence' => __(
                        '1 occurrence sets yes, 8 set no, across 4'
                        . ' organisations'
                    ),
                    'expanded' => true,
                    'rows' => array(
                        array(
                            'event_id' => 1276,
                            'event_info' => 'Exfiltration over DNS -'
                                . ' incident 2025-0442',
                            'org' => 'ORGNAME',
                            'type' => 'ip-dst',
                            'to_ids' => 1,
                            'category' => 'Network activity',
                            'comment' => 'Blocked at the perimeter after'
                                . ' the alert',
                        ),
                        array(
                            'event_id' => 1301,
                            'event_info' => 'Emotet malspam campaign -'
                                . ' full IOC set',
                            'org' => 'CIRCL',
                            'type' => 'ip-dst',
                            'to_ids' => 0,
                            'category' => 'Network activity',
                            'comment' => 'Resolver the sample used, not an'
                                . ' indicator',
                        ),
                        array(
                            'event_id' => 1298,
                            'event_info' => 'AsyncRAT sample analysis -'
                                . ' config dump',
                            'org' => 'CthulhuSPRL.be',
                            'type' => 'domain|ip',
                            'to_ids' => 0,
                            'category' => 'Network activity',
                            'comment' => 'DNS server in the malware'
                                . ' configuration',
                        ),
                    ),
                    'actions' => array(
                        array(
                            'label' => __('Clear to_ids on the 1 …'),
                            'icon' => 'fas fa-code-compare',
                            'colour' => 'var(--vp-conflict)',
                        ),
                        array(
                            'label' => __('Propose change to ORGNAME'),
                            'icon' => 'fas fa-code-pull-request',
                            'colour' => 'var(--correlation)',
                        ),
                    ),
                    'confirm_note' => __(
                        'Both actions confirm first, listing 1 row in 1'
                        . ' event owned by another organisation.'
                    ),
                ),
                array(
                    'kind' => 'opinion',
                    'title' => __('Opinion split: 70 against 8 and 15'),
                    'note' => __(
                        'Two clusters and no middle. The mean of 31 is'
                        . ' the one number here that describes nobody, so'
                        . ' the profile does not compute one.'
                    ),
                    'yes' => 1,
                    'no' => 2,
                    'evidence' => __(
                        '3 opinions across 4 organisations; the fourth'
                        . ' states none'
                    ),
                    'expanded' => false,
                    'rows' => array(),
                    'actions' => array(),
                    'confirm_note' => null,
                ),
            ),
            'orgs' => array(
                array(
                    'org' => 'CIRCL',
                    'occurrences' => 4,
                    'sightings' => 10,
                    'fp' => 7,
                    'opinion' => 8,
                    'to_ids' => __('no'),
                    'reliability' => 'B',
                    'reads' => __('Public resolver, context only'),
                    'side' => 'benign',
                ),
                array(
                    'org' => 'CthulhuSPRL.be',
                    'occurrences' => 3,
                    'sightings' => 5,
                    'fp' => 3,
                    'opinion' => 15,
                    'to_ids' => __('no'),
                    'reliability' => 'B',
                    'reads' => __('In the config, not an indicator'),
                    'side' => 'benign',
                ),
                array(
                    'org' => 'Botvrij.eu',
                    'occurrences' => 1,
                    'sightings' => 2,
                    'fp' => 1,
                    'opinion' => null,
                    'to_ids' => __('no'),
                    'reliability' => 'C',
                    'reads' => __('Withdrawn from the feed'),
                    'side' => 'benign',
                ),
                array(
                    'org' => 'ORGNAME',
                    'occurrences' => 1,
                    'sightings' => 0,
                    'fp' => 0,
                    'opinion' => 70,
                    'to_ids' => __('yes'),
                    'reliability' => 'D',
                    'reads' => __('Carried exfiltrated data — keep it'
                        . ' flagged'),
                    'side' => 'malicious',
                ),
            ),
            /*
             * Reconciles with the ledger: the six supporting rows sum to
             * 106, the two against to -15, and the total to the 91 the
             * hero states. `Decay` and `Known-good listing` are split
             * apart even though both come from the Lifecycle panel,
             * because a reader tracing 38 should land on one row rather
             * than on a pair that has to be pulled apart first.
             */
            'composition' => array(
                array(
                    'label' => __('Known-good listing'),
                    'points' => 38,
                    'colour' => 'var(--warninglist)',
                ),
                array(
                    'label' => __('False-positive sightings'),
                    'points' => 26,
                    'colour' => 'var(--sighting)',
                ),
                array(
                    'label' => __('Decay'),
                    'points' => 16,
                    'colour' => 'var(--correlation)',
                ),
                array(
                    'label' => __('Reporting intent'),
                    'points' => 13,
                    'colour' => 'var(--event)',
                ),
                array(
                    'label' => __('Absence of corroboration'),
                    'points' => 13,
                    'colour' => 'var(--galaxy)',
                ),
                array(
                    'label' => __('Signals against'),
                    'points' => -15,
                    'colour' => 'var(--bs-danger)',
                ),
            ),
            'composition_note' => __(
                'The number is support for BENIGN, not a malice reading:'
                . ' 91 says the benign call is well evidenced, on the'
                . ' same scale the malicious verdict\'s 84 sits on.'
            ),
            'curves' => array(
                array(
                    'label' => __('Synthesised verdict'),
                    'colour' => 'var(--vp-ben)',
                    'data' => self::benignVerdictCurve(),
                ),
                array(
                    'label' => __('NIDS decay score'),
                    'colour' => 'var(--vp-conflict)',
                    'dashed' => true,
                    'data' => $nidsCurve,
                ),
            ),
            'curves_span' => __('90 days'),
            'curves_note' => __(
                'The step on 2025-06-24 is the address being added to the'
                . ' public-resolver warninglist. Before that the page'
                . ' called it SUSPICIOUS on the strength of the same four'
                . ' reports — the evidence did not change, the profile\'s'
                . ' knowledge of it did.'
            ),
            'not_counted' => array(
                array(
                    'title' => __('4 occurrences'),
                    'note' => __(
                        'Outside your ACL — excluded, not hidden. The'
                        . ' score you see is the score for your'
                        . ' permissions.'
                    ),
                ),
                array(
                    'title' => __('21,904 correlations'),
                    'note' => __(
                        'Far above the over-correlation threshold, so'
                        . ' correlation is not treated as evidence here.'
                        . ' It is itself a hint that the value is shared'
                        . ' infrastructure.'
                    ),
                ),
                array(
                    'title' => __('The reverse DNS record'),
                    'note' => __(
                        '`dns.google` corroborates and the profile still'
                        . ' ignores it: a PTR record is attacker-'
                        . 'controlled for most values, and a rule that'
                        . ' only holds where it happens to be trustworthy'
                        . ' is not a rule.'
                    ),
                ),
            ),
            /*
             * The falsification list matters more here than anywhere
             * else on the page. A benign verdict is the one that tells a
             * reader to stop looking, so it owes them the conditions
             * under which they should start again.
             */
            'changers' => array(
                array(
                    'direction' => 'down',
                    'text' => __(
                        'A sighting burst — 3 or more from 2+ orgs inside'
                        . ' 7 days → SUSPICIOUS, whatever the listing'
                        . ' says.'
                    ),
                ),
                array(
                    'direction' => 'down',
                    'text' => __(
                        'Removal from the false_positive warninglist →'
                        . ' back to scoring on the reports alone, which'
                        . ' currently stand at 15.'
                    ),
                ),
                array(
                    'direction' => 'down',
                    'text' => __(
                        'A threat-actor galaxy on any occurrence →'
                        . ' CONFLICTED, the way a known-category hit does'
                        . ' on a reported value.'
                    ),
                ),
            ),
            'changer_actions' => array(
                array(
                    'label' => __('Report a sighting'),
                    'icon' => 'fas fa-eye',
                    'colour' => 'var(--vp-ben)',
                    'emphasis' => true,
                ),
                array(
                    'label' => __('Record an opinion'),
                    'icon' => 'fas fa-scale-balanced',
                    'colour' => 'var(--analystData)',
                ),
                array(
                    'label' => __('Notify me if the verdict changes'),
                    'icon' => 'fas fa-bell',
                    'colour' => 'var(--correlation)',
                ),
            ),
        );
    }

    /**
     * The same four groups the malicious ledger uses, read the other
     * way: ▲ is a row that supports the stated disposition, so on this
     * value the heavy rows are the ones arguing nobody should act.
     *
     * The two rows against are real and stay in — a benign verdict that
     * hid the reporting breadth behind it would be making the reader's
     * decision for them.
     *
     * @return array
     */
    private static function benignLedger()
    {
        return array(
            array(
                'kind' => __('Reporting'),
                'note' => __('who reported it, and how widely'),
                'signals' => array(
                    array(
                        'direction' => 'down',
                        'weight' => 'moderate',
                        'contribution' => -11,
                        'signal' => __(
                            '4 organisations carry an occurrence of it'
                        ),
                        'evidence' => __(
                            'Breadth argues for any value, and it argues'
                            . ' for this one too'
                        ),
                        'source' => __('Occurrences'),
                        'as_of' => '2025-08-21',
                    ),
                    array(
                        'direction' => 'up',
                        'weight' => 'moderate',
                        'contribution' => 13,
                        'signal' => __('8 of 9 occurrences set to_ids = no'),
                        'evidence' => __(
                            'The reporting organisations did not intend'
                            . ' it to fire a rule'
                        ),
                        'source' => __('Occurrences'),
                        'as_of' => '2025-08-21',
                    ),
                ),
            ),
            array(
                'kind' => __('Sightings'),
                'note' => __('who has seen it, and how recently'),
                'signals' => array(
                    array(
                        'direction' => 'up',
                        'weight' => 'strong',
                        'contribution' => 26,
                        'signal' => __(
                            '11 false-positive sightings from 3 orgs'
                        ),
                        'evidence' => __(
                            'First 2024-11-02, most recent 2025-08-14'
                        ),
                        'source' => __('Sightings'),
                        'as_of' => '2025-08-14',
                    ),
                    array(
                        'direction' => 'down',
                        'weight' => 'weak',
                        'contribution' => -4,
                        'signal' => __(
                            '6 ordinary sightings, last 12 days ago'
                        ),
                        'evidence' => __(
                            'No burst: 1 sighting in the last 30 days'
                        ),
                        'source' => __('Sightings'),
                        'as_of' => '2025-08-12',
                    ),
                ),
            ),
            array(
                'kind' => __('Attribution'),
                'note' => __('what it has been linked to'),
                'signals' => array(
                    array(
                        'direction' => 'up',
                        'weight' => 'weak',
                        'contribution' => 7,
                        'signal' => __(
                            'No galaxy and no technique on any occurrence'
                        ),
                        'evidence' => __('9 occurrences, 0 clusters'),
                        'source' => __('Context'),
                        'as_of' => '2025-08-21',
                    ),
                ),
            ),
            array(
                'kind' => __('Lifecycle'),
                'note' => __('whether it is still worth acting on'),
                'signals' => array(
                    array(
                        'direction' => 'up',
                        'weight' => 'strong',
                        'contribution' => 38,
                        'signal' => __(
                            'Hits List of known IPv4 public DNS resolvers'
                        ),
                        'evidence' => __(
                            'category false_positive · exact match ·'
                            . ' list version 20250802'
                        ),
                        'source' => __('Lifecycle'),
                        'as_of' => '2025-08-24',
                    ),
                    array(
                        'direction' => 'up',
                        'weight' => 'moderate',
                        'contribution' => 16,
                        'signal' => __(
                            'Decayed under both models (4/100 and 0/100)'
                        ),
                        'evidence' => __('Thresholds 60 and 50'),
                        'source' => __('Lifecycle'),
                        'as_of' => '2025-08-24',
                    ),
                    array(
                        'direction' => 'up',
                        'weight' => 'weak',
                        'contribution' => 6,
                        'signal' => __('Listed by no enabled feed'),
                        'evidence' => __('0 of 41 feeds carry it'),
                        'source' => __('External'),
                        'as_of' => '2025-08-24',
                    ),
                ),
            ),
        );
    }

    /**
     * The weight of each case over the same 90 days. They cross: the
     * warninglist entry landed before the second phishing wave, so for
     * six weeks the benign reading was the stronger one.
     *
     * @return array
     */
    private static function conflictedWeightMalicious()
    {
        return array(
            22, 24, 23, 25, 24, 23, 22, 28, 27, 26,
            25, 24, 23, 22, 21, 20, 19, 26, 25, 24,
            23, 22, 21, 20, 19, 27, 26, 25, 24, 23,
            22, 24, 33, 41, 48, 47, 46, 50, 55, 54,
        );
    }

    /**
     * @return array
     */
    private static function conflictedWeightBenign()
    {
        return array(
            8, 9, 9, 10, 11, 11, 12, 13, 14, 15,
            16, 17, 18, 19, 20, 21, 22, 31, 32, 33,
            34, 35, 36, 36, 37, 38, 38, 39, 39, 40,
            40, 41, 41, 41, 40, 40, 39, 39, 39, 39,
        );
    }

    /**
     * Support for the benign reading. Flat and unremarkable until
     * 2025-06-24, when the address was added to the public-resolver
     * warninglist and the profile learned in one step what the analysts
     * had been saying in the notes for months.
     *
     * @return array
     */
    private static function benignVerdictCurve()
    {
        return array(
            44, 45, 44, 46, 45, 47, 46, 48, 47, 49,
            48, 50, 49, 82, 83, 84, 83, 85, 84, 86,
            85, 86, 87, 86, 88, 87, 88, 89, 88, 89,
            90, 89, 90, 91, 90, 91, 90, 91, 91, 91,
        );
    }

    /* ==================================================================
     * Sightings
     * ==================================================================
     * The Sightings tab draws one chart over two axes: the reports that
     * arrived, and the decay score they move. Everything below is
     * derived from one list of rows per value — the bars, the curve, the
     * legend, the table and the Overview's sparkline all come out of the
     * same array, so they cannot tell five different stories about the
     * same 47 reports.
     *
     * The curve in particular is computed rather than drawn, using
     * MISP's own polynomial: `base * (1 - (t / lifetime) ^ (1 /
     * decay_rate))`, with `t` the days since the last sighting of type
     * 0. That is what makes the tab's central claim provable rather than
     * asserted — a false positive is type 1, so it cannot appear in `t`,
     * so it cannot move the line.
     */

    /**
     * One sighting, in the shape `Sighting::listSightings` returns.
     *
     * `type` stays MISP's int rather than a label because the three are
     * not degrees of one thing: only type 0 moves a score.
     *
     * @param string $date `Y-m-d H:i`
     * @param string $org Reporting organisation
     * @param int $type 0 sighting, 1 false positive, 2 expiration
     * @param int $eventId The occurrence it was filed against — a
     *                     value-scoped list otherwise loses which of ten
     *                     occurrences was actually seen
     * @param string $attributeType
     * @param string|null $source Free text the reporter supplied, which
     *                            most sightings do not carry
     * @return array
     */
    private static function sightingRow(
        $date,
        $org,
        $type,
        $eventId,
        $attributeType,
        $source = null
    ) {
        return array(
            'date' => $date,
            'org' => $org,
            'type' => $type,
            'source' => $source,
            'against' => array(
                'event' => $eventId,
                'type' => $attributeType,
            ),
        );
    }

    /**
     * Whole days from one date to another, negative if $to is earlier.
     *
     * Explicitly UTC so a bucket boundary cannot move when the host's
     * clocks do — every date in this class is a literal, and the day
     * arithmetic over them has to be as fixed as they are.
     *
     * @param string $from `Y-m-d`
     * @param string $to `Y-m-d`
     * @return int
     */
    private static function dayDiff($from, $to)
    {
        $utc = new DateTimeZone('UTC');
        $a = new DateTimeImmutable($from . ' 00:00:00', $utc);
        $b = new DateTimeImmutable($to . ' 00:00:00', $utc);
        return (int)$a->diff($b)->format('%r%a');
    }

    /**
     * @param string $date `Y-m-d`
     * @param int $days
     * @return string
     */
    private static function addDays($date, $days)
    {
        $utc = new DateTimeZone('UTC');
        $d = new DateTimeImmutable($date . ' 00:00:00', $utc);
        return $d->modify(($days >= 0 ? '+' : '') . $days . ' days')
            ->format('Y-m-d');
    }

    /**
     * MISP's default decay function, as `DecayingModelBase` computes it.
     *
     * @param array $model `base`, `lifetime`, `decay_rate`
     * @param int $days Days since the last type-0 sighting
     * @return int
     */
    private static function decayScore(array $model, $days)
    {
        if ($days >= $model['lifetime']) {
            return 0;
        }
        if ($days <= 0) {
            return (int)$model['base'];
        }
        $decayed = pow(
            $days / $model['lifetime'],
            1 / $model['decay_rate']
        );
        return (int)round($model['base'] * (1 - $decayed));
    }

    /**
     * The last thing that reset the decay clock at or before `$at`.
     *
     * An attribute nobody has sighted still has a score, because MISP
     * decays it from its own timestamp — so a value with no sightings
     * falls back to `$created` rather than having no curve at all.
     *
     * @param array $rows
     * @param string $created `Y-m-d`
     * @param string $at `Y-m-d`
     * @return array `date`, `org`, `days`
     */
    private static function lastResetAt(array $rows, $created, $at)
    {
        $reset = array('date' => $created, 'org' => null);
        foreach ($rows as $row) {
            if ($row['type'] !== self::SIGHTING) {
                continue;
            }
            $day = substr($row['date'], 0, 10);
            if (self::dayDiff($day, $at) < 0) {
                continue;
            }
            if (self::dayDiff($reset['date'], $day) >= 0) {
                $reset = array('date' => $day, 'org' => $row['org']);
            }
        }
        $reset['days'] = self::dayDiff($reset['date'], $at);
        return $reset;
    }

    /**
     * The decaying models that apply to a value, with today's score and
     * its provenance filled in rather than asserted.
     *
     * `decayed` is derived from the score, and `permanently_under` from
     * the base: a model whose base score is below its own threshold can
     * never cross it for this value however often it is sighted, and
     * that is a different claim from "it has decayed", so the rail card
     * gets to say which one it is looking at.
     *
     * @param array $rows
     * @param string $created `Y-m-d`, the attribute's first seen
     * @param array $specs `model`, `base`, `lifetime`, `decay_rate`,
     *                     `threshold`
     * @return array
     */
    private static function decayModels(array $rows, $created, array $specs)
    {
        $models = array();
        foreach ($specs as $spec) {
            $reset = self::lastResetAt($rows, $created, self::TODAY);
            $spec['score'] = self::decayScore($spec, $reset['days']);
            $spec['decayed'] = $spec['score'] < $spec['threshold'];
            $spec['permanently_under'] = $spec['base'] < $spec['threshold'];
            $spec['reset_on'] = $reset['date'];
            $spec['reset_by'] = $reset['org'];
            $models[] = $spec;
        }
        return $models;
    }

    /**
     * One model's score on each of the given dates.
     *
     * @param array $rows
     * @param string $created
     * @param array $model
     * @param array $dates `Y-m-d` each
     * @return array
     */
    private static function decayCurve(
        array $rows,
        $created,
        array $model,
        array $dates
    ) {
        $points = array();
        foreach ($dates as $date) {
            if (self::dayDiff($created, $date) < 0) {
                // Before the attribute existed there is no score to
                // draw, and zero is a score. A gap is the honest mark.
                $points[] = null;
                continue;
            }
            $reset = self::lastResetAt($rows, $created, $date);
            $points[] = self::decayScore($model, $reset['days']);
        }
        return $points;
    }

    /**
     * The same curve resampled to a fixed number of points over the last
     * 90 days, which is the shape the Verdict tab's rail card draws.
     *
     * The Verdict tab and the Sightings tab therefore plot one number
     * two ways rather than two numbers under one name.
     *
     * @param array $rows
     * @param string $created
     * @param array $model
     * @param int $points
     * @return array
     */
    private static function decaySpan(
        array $rows,
        $created,
        array $model,
        $points = 40
    ) {
        $dates = array();
        for ($i = 0; $i < $points; $i++) {
            $daysAgo = (int)round((($points - 1 - $i) * 90) / ($points - 1));
            $dates[] = self::addDays(self::TODAY, -$daysAgo);
        }
        return self::decayCurve($rows, $created, $model, $dates);
    }

    /**
     * The Overview card's sparkline: 90 days in 40 columns, counting
     * sightings only.
     *
     * Derived from the same rows the tab charts, so the Overview and the
     * Sightings tab cannot disagree about how busy the last 90 days
     * were.
     *
     * @param array $rows
     * @return array
     */
    private static function sightingSpark(array $rows)
    {
        $columns = 40;
        $spark = array_fill(0, $columns, 0);
        $from = self::addDays(self::TODAY, -89);
        foreach ($rows as $row) {
            if ($row['type'] !== self::SIGHTING) {
                continue;
            }
            $offset = self::dayDiff($from, substr($row['date'], 0, 10));
            if ($offset < 0) {
                continue;
            }
            $column = (int)floor(($offset * $columns) / 90);
            $spark[min($column, $columns - 1)]++;
        }
        return $spark;
    }

    /**
     * The tab's chart data: the rows bucketed per range, and each
     * model's score sampled onto the same axis.
     *
     * Three things are worth stating about the shape.
     *
     * `by_org` is positional, aligned with `orgs`, because Chart.js
     * wants one dataset per organisation and a stack order that does not
     * change between buckets.
     *
     * A range is only offered when it is a window this value can
     * actually be looked at through: `Last 365 days` is not listed for a
     * value that has existed for 344, because it would draw the same
     * chart as `All time` behind a different label, and a control that
     * changes nothing is worse than one that is absent.
     *
     * The default range is the narrowest one that holds every sighting.
     * A sparse value defaulting to 90 days is a nearly empty chart, and
     * the reader would have to discover the control to find out it is
     * not the whole truth.
     *
     * @param array $rows
     * @param array $models Already through `decayModels`
     * @param string $created `Y-m-d`, the attribute's first seen
     * @return array
     */
    private static function sightingSeries(array $rows, array $models, $created)
    {
        $orgs = array();
        $totals = array(
            'total' => count($rows),
            'sighting' => 0,
            'fp' => 0,
            'expiration' => 0,
        );
        $latest = null;
        foreach ($rows as $row) {
            $orgs[$row['org']] = ($orgs[$row['org']] ?? 0) + 1;
            if ($row['type'] === self::FALSE_POSITIVE) {
                $totals['fp']++;
            } elseif ($row['type'] === self::EXPIRATION) {
                $totals['expiration']++;
            } else {
                $totals['sighting']++;
            }
            if ($latest === null || strcmp($row['date'], $latest) > 0) {
                $latest = $row['date'];
            }
        }
        arsort($orgs);

        $age = self::dayDiff($created, self::TODAY);
        $windows = array(90);
        if ($age > 365) {
            $windows[] = 365;
        }
        $windows[] = null;

        $ranges = array();
        $default = null;
        foreach ($windows as $days) {
            $range = self::sightingRange(
                $rows,
                $models,
                $created,
                $days,
                array_keys($orgs)
            );
            $ranges[] = $range;
            if ($default === null && $range['in_range'] === $totals['total']) {
                $default = $range['key'];
            }
        }

        return array(
            'today' => self::TODAY,
            'first' => $created,
            'orgs' => array_keys($orgs),
            'org_counts' => $orgs,
            'totals' => $totals,
            'last' => $latest,
            'ranges' => $ranges,
            // A value with no sightings still has ranges to look at, and
            // the last of them is the one that holds its whole history.
            'default_range' => $default === null ? 'all' : $default,
        );
    }

    /**
     * One range of the series: its buckets, its curves and its label.
     *
     * @param array $rows
     * @param array $models
     * @param string $created
     * @param int|null $days null for all time
     * @param array $orgs Stack order
     * @return array
     */
    private static function sightingRange(
        array $rows,
        array $models,
        $created,
        $days,
        array $orgs
    ) {
        $from = $days === null
            ? $created
            : self::addDays(self::TODAY, -($days - 1));
        // Daily columns only make sense while there are fewer of them
        // than the chart has pixels; past a quarter the bucket is a week
        // and the caption says so rather than leaving the reader to
        // infer it from bar widths.
        $step = ($days !== null && $days <= 90) ? 1 : 7;

        $buckets = array();
        $cursor = self::TODAY;
        while (self::dayDiff($from, $cursor) >= 0) {
            $start = self::addDays($cursor, -($step - 1));
            if (self::dayDiff($from, $start) < 0) {
                $start = $from;
            }
            array_unshift($buckets, array(
                'from' => $start,
                'to' => $cursor,
                'label' => self::bucketLabel($cursor),
                'by_org' => array_fill(0, count($orgs), 0),
                'fp' => 0,
                'expiration' => 0,
            ));
            $cursor = self::addDays($start, -1);
        }
        $buckets[count($buckets) - 1]['label'] = __('today');

        $index = array_flip($orgs);
        $inRange = 0;
        foreach ($rows as $row) {
            $day = substr($row['date'], 0, 10);
            if (self::dayDiff($from, $day) < 0) {
                continue;
            }
            foreach ($buckets as $i => $bucket) {
                if (self::dayDiff($day, $bucket['to']) < 0) {
                    continue;
                }
                if (self::dayDiff($bucket['from'], $day) < 0) {
                    continue;
                }
                $inRange++;
                if ($row['type'] === self::FALSE_POSITIVE) {
                    $buckets[$i]['fp']++;
                } elseif ($row['type'] === self::EXPIRATION) {
                    $buckets[$i]['expiration']++;
                } else {
                    $buckets[$i]['by_org'][$index[$row['org']]]++;
                }
                break;
            }
        }

        $dates = array_column($buckets, 'to');
        $curves = array();
        foreach ($models as $model) {
            $curves[] = array(
                'model' => $model['model'],
                'threshold' => $model['threshold'],
                'points' => self::decayCurve($rows, $created, $model, $dates),
            );
        }

        return array(
            'key' => $days === null ? 'all' : (string)$days,
            'label' => $days === null
                ? sprintf(__('All time · from %s'), $created)
                : sprintf(__('Last %s days'), $days),
            'days' => $days,
            'from' => $from,
            'to' => self::TODAY,
            'step' => $step,
            'step_label' => $step === 1
                ? __('one column per day')
                : __('one column per week'),
            'buckets' => $buckets,
            'curves' => $curves,
            'in_range' => $inRange,
        );
    }

    /**
     * @param string $date `Y-m-d`
     * @return string
     */
    private static function bucketLabel($date)
    {
        $d = new DateTimeImmutable(
            $date . ' 00:00:00',
            new DateTimeZone('UTC')
        );
        return $d->format('j M');
    }

    /**
     * The two sentences the tab must not omit, per value.
     *
     * @param string $fp What the value's own false positives did
     * @return array
     */
    private static function sightingNotes($fp)
    {
        return array(
            'fp_moves_nothing' => $fp,
            'policy' => __(
                'Sightings you can see. This instance\'s sighting policy'
                . ' hides sightings reported by other organisations on'
                . ' events your organisation does not own, so this count'
                . ' is yours, not the instance\'s.'
            ),
        );
    }

    /**
     * The models that score the C2 address.
     *
     * NIDS keeps it well above threshold because it is sighted every few
     * days; the Phishing model's base score for this value is 34, which
     * is below its own threshold of 50, so no amount of sighting can
     * ever carry it over.
     *
     * @return array
     */
    private static function maliciousModels()
    {
        return array(
            array(
                'model' => 'NIDS Simple Decaying Model',
                'base' => 78,
                'lifetime' => 14,
                'decay_rate' => 0.3,
                'threshold' => 60,
            ),
            array(
                'model' => 'Phishing Model',
                'base' => 34,
                'lifetime' => 21,
                'decay_rate' => 0.3,
                'threshold' => 50,
            ),
        );
    }

    /**
     * @return array
     */
    private static function conflictedModels()
    {
        return array(
            array(
                'model' => 'Phishing Model',
                'base' => 66,
                'lifetime' => 21,
                'decay_rate' => 0.3,
                'threshold' => 50,
            ),
            array(
                'model' => 'NIDS Simple Decaying Model',
                'base' => 41,
                'lifetime' => 14,
                'decay_rate' => 0.3,
                'threshold' => 60,
            ),
        );
    }

    /**
     * @return array
     */
    private static function benignModels()
    {
        return array(
            array(
                'model' => 'NIDS Simple Decaying Model',
                'base' => 18,
                'lifetime' => 14,
                'decay_rate' => 0.3,
                'threshold' => 60,
            ),
            array(
                'model' => 'Phishing Model',
                'base' => 0,
                'lifetime' => 21,
                'decay_rate' => 0.3,
                'threshold' => 50,
            ),
        );
    }

    /**
     * Expand a row table into sighting rows.
     *
     * The tables are written positionally because 127 of them read as a
     * table and not as 127 objects; this is where the columns get their
     * names back.
     *
     * @param array $table
     * @return array
     */
    private static function sightingRows(array $table)
    {
        $rows = array();
        foreach ($table as $row) {
            $rows[] = self::sightingRow(
                $row[0],
                $row[1],
                $row[2],
                $row[3],
                $row[4],
                isset($row[5]) ? $row[5] : null
            );
        }
        return $rows;
    }

    /**
     * 47 reports on the C2 address, every one of them inside the
     * last 90 days: the value sat in MISP for eight months before
     * anybody said they had seen it. That is the reading `All time`
     * exists to give, and it is not one the 90-day window can.
     *
     * One of the 47 is a false positive, on 2025-08-01, so the
     * chart has a contradiction to draw and the curve has a chance
     * to visibly not move.
     *
     * Columns: date · organisation · type · event · attribute
     * type · source.
     *
     * @return array
     */
    private static function maliciousSightingRows()
    {
        return self::sightingRows(array(
            array('2025-05-27 17:52', 'CIRCL', 0, 1279, 'domain|ip'),
            array('2025-06-01 06:19', 'CIRCL', 0, 1265, 'ip-dst'),
            array('2025-06-02 16:13', 'CthulhuSPRL.be', 0, 1265, 'ip-dst'),
            array('2025-06-10 02:06', 'CthulhuSPRL.be', 0, 1265, 'ip-dst'),
            array('2025-06-13 06:30', 'Team-CIRCL', 0, 1279, 'domain|ip'),
            array('2025-06-14 10:04', 'CthulhuSPRL.be', 0, 1265, 'ip-dst'),
            array('2025-06-14 22:35', 'CIRCL', 0, 1272, 'ip-src'),
            array('2025-06-18 03:42', 'CthulhuSPRL.be', 0, 1279, 'domain|ip'),
            array('2025-06-18 05:50', 'Team-CIRCL', 0, 1251, 'ip-dst'),
            array('2025-06-18 19:28', 'CthulhuSPRL.be', 0, 1251, 'ip-dst'),
            array('2025-06-19 10:08', 'CIRCL', 0, 1284, 'ip-dst'),
            array('2025-06-19 20:06', 'Team-CIRCL', 0, 1291, 'ip-dst'),
            array('2025-06-20 10:00', 'CIRCL', 0, 1251, 'ip-dst'),
            array('2025-06-20 14:05', 'CIRCL', 0, 1291, 'ip-dst'),
            array('2025-06-22 07:37', 'CIRCL', 0, 1291, 'ip-dst'),
            array('2025-06-22 19:25', 'CIRCL', 0, 1284, 'ip-dst'),
            array('2025-07-01 05:24', 'CIRCL', 0, 1284, 'ip-dst'),
            array('2025-07-02 12:08', 'CthulhuSPRL.be', 0, 1284, 'ip-dst'),
            array('2025-07-04 14:06', 'ORGNAME', 0, 1279, 'domain|ip'),
            array('2025-07-09 00:31', 'CIRCL', 0, 1265, 'ip-dst'),
            array('2025-07-15 04:52', 'CthulhuSPRL.be', 0, 1279, 'domain|ip'),
            array('2025-07-15 12:42', 'CthulhuSPRL.be', 0, 1279, 'domain|ip'),
            array('2025-07-15 17:28', 'Team-CIRCL', 0, 1272, 'ip-src'),
            array('2025-07-16 11:58', 'Team-CIRCL', 0, 1279, 'domain|ip'),
            array('2025-07-19 20:58', 'CthulhuSPRL.be', 0, 1251, 'ip-dst'),
            array('2025-07-25 19:27', 'CthulhuSPRL.be', 0, 1291, 'ip-dst'),
            array('2025-07-25 22:16', 'ORGNAME', 0, 1265, 'ip-dst'),
            array('2025-07-29 16:35', 'Team-CIRCL', 0, 1265, 'ip-dst'),
            array('2025-08-01 11:47', 'ORGNAME', 1, 1265, 'ip-dst', 'triage'),
            array('2025-08-11 01:23', 'CthulhuSPRL.be', 0, 1284, 'ip-dst'),
            array('2025-08-11 21:08', 'CIRCL', 0, 1284, 'ip-dst'),
            array('2025-08-12 01:00', 'CIRCL', 0, 1291, 'ip-dst'),
            array('2025-08-15 10:46', 'ORGNAME', 0, 1279, 'domain|ip'),
            array('2025-08-16 07:46', 'CthulhuSPRL.be', 0, 1284, 'ip-dst'),
            array('2025-08-17 09:41', 'CthulhuSPRL.be', 0, 1251, 'ip-dst'),
            array('2025-08-18 07:20', 'CIRCL', 0, 1291, 'ip-dst'),
            array('2025-08-18 20:41', 'CIRCL', 0, 1279, 'domain|ip'),
            array('2025-08-19 01:43', 'CIRCL', 0, 1284, 'ip-dst'),
            array('2025-08-19 05:41', 'CIRCL', 0, 1291, 'ip-dst'),
            array('2025-08-19 17:27', 'CIRCL', 0, 1251, 'ip-dst'),
            array('2025-08-20 02:24', 'CthulhuSPRL.be', 0, 1279, 'domain|ip'),
            array('2025-08-20 10:35', 'Team-CIRCL', 0, 1272, 'ip-src'),
            array('2025-08-21 08:09', 'CIRCL', 0, 1284, 'ip-dst'),
            array('2025-08-21 20:40', 'CIRCL', 0, 1272, 'ip-src'),
            array('2025-08-21 23:13', 'Team-CIRCL', 0, 1251, 'ip-dst'),
            array('2025-08-22 08:03', 'CIRCL', 0, 1284, 'ip-dst', 'sightingdb'),
            array('2025-08-22 16:20', 'CIRCL', 0, 1279, 'domain|ip'),
        ));
    }

    /**
     * 63 reports on the CDN edge, spread over six months rather
     * than bunched: a shared address is sighted by whoever happens
     * to be looking at the time. Nine false positives and two
     * expirations, so this is the value where all three type
     * toggles have something to toggle.
     *
     * Columns: date · organisation · type · event · attribute
     * type · source.
     *
     * @return array
     */
    private static function conflictedSightingRows()
    {
        return self::sightingRows(array(
            array('2025-02-05 21:15', 'Botvrij.eu', 0, 1388, 'ip-dst'),
            array('2025-02-10 01:58', 'CthulhuSPRL.be', 0, 1309, 'domain|ip'),
            array('2025-02-15 11:20', 'CIRCL', 0, 1402, 'domain|ip'),
            array('2025-02-15 11:53', 'CthulhuSPRL.be', 0, 1388, 'ip-dst'),
            array('2025-02-21 15:55', 'CIRCL', 0, 1402, 'domain|ip'),
            array('2025-02-25 05:56', 'CIRCL', 0, 1341, 'ip-src'),
            array('2025-03-17 10:20', 'Botvrij.eu', 0, 1388, 'ip-dst'),
            array('2025-03-17 14:44', 'CthulhuSPRL.be', 0, 1341, 'ip-src'),
            array('2025-03-19 14:02', 'ORGNAME', 1, 1341, 'ip-src'),
            array('2025-04-01 03:58', 'CIRCL', 0, 1309, 'domain|ip'),
            array('2025-04-08 01:03', 'CIRCL', 0, 1309, 'domain|ip'),
            array('2025-04-11 01:37', 'CthulhuSPRL.be', 0, 1341, 'ip-src'),
            array('2025-04-11 05:41', 'CIRCL', 0, 1388, 'ip-dst'),
            array('2025-04-14 03:09', 'CIRCL', 0, 1341, 'ip-src'),
            array('2025-04-18 01:50', 'CIRCL', 0, 1402, 'domain|ip'),
            array('2025-04-21 19:27', 'Botvrij.eu', 0, 1388, 'ip-dst'),
            array('2025-04-23 06:34', 'Botvrij.eu', 0, 1341, 'ip-src'),
            array('2025-04-26 22:33', 'Botvrij.eu', 0, 1341, 'ip-src'),
            array('2025-04-27 09:31', 'CthulhuSPRL.be', 1, 1309, 'domain|ip'),
            array('2025-04-28 12:29', 'CIRCL', 0, 1402, 'ip-dst'),
            array('2025-04-30 04:59', 'ORGNAME', 0, 1402, 'ip-dst'),
            array('2025-05-08 16:55', 'Botvrij.eu', 1, 1388, 'ip-dst',
                'allowlist'),
            array('2025-05-12 19:07', 'Botvrij.eu', 0, 1402, 'ip-dst'),
            array('2025-05-13 02:33', 'Botvrij.eu', 0, 1402, 'ip-dst'),
            array('2025-05-17 17:29', 'CthulhuSPRL.be', 0, 1388, 'ip-dst'),
            array('2025-05-30 10:12', 'CIRCL', 1, 1402, 'ip-dst'),
            array('2025-06-02 03:14', 'CIRCL', 2, 1341, 'ip-src'),
            array('2025-06-09 03:06', 'Botvrij.eu', 0, 1309, 'domain|ip'),
            array('2025-06-10 08:39', 'CIRCL', 0, 1341, 'ip-src'),
            array('2025-06-12 06:43', 'Botvrij.eu', 0, 1309, 'domain|ip'),
            array('2025-06-14 08:44', 'Botvrij.eu', 1, 1402, 'domain|ip'),
            array('2025-06-18 22:07', 'CIRCL', 0, 1388, 'ip-dst'),
            array('2025-06-20 11:15', 'CIRCL', 0, 1309, 'domain|ip'),
            array('2025-06-21 08:50', 'CIRCL', 0, 1341, 'ip-src'),
            array('2025-06-24 09:01', 'Botvrij.eu', 0, 1402, 'domain|ip'),
            array('2025-06-24 23:54', 'Botvrij.eu', 0, 1402, 'ip-dst'),
            array('2025-06-25 00:29', 'Botvrij.eu', 0, 1402, 'ip-dst'),
            array('2025-06-25 23:14', 'CthulhuSPRL.be', 0, 1388, 'ip-dst'),
            array('2025-06-29 19:20', 'CIRCL', 1, 1388, 'ip-dst', 'allowlist'),
            array('2025-07-01 10:14', 'CIRCL', 0, 1341, 'ip-src'),
            array('2025-07-10 12:50', 'CIRCL', 0, 1402, 'domain|ip'),
            array('2025-07-11 07:58', 'CthulhuSPRL.be', 1, 1341, 'ip-src'),
            array('2025-07-13 08:38', 'CIRCL', 0, 1341, 'ip-src'),
            array('2025-07-15 04:28', 'CIRCL', 0, 1309, 'domain|ip'),
            array('2025-07-16 11:43', 'CIRCL', 0, 1402, 'domain|ip'),
            array('2025-07-21 01:57', 'CthulhuSPRL.be', 0, 1341, 'ip-src'),
            array('2025-07-24 20:28', 'Botvrij.eu', 0, 1402, 'domain|ip'),
            array('2025-07-25 01:49', 'ORGNAME', 0, 1309, 'domain|ip'),
            array('2025-07-26 13:37', 'CIRCL', 1, 1402, 'ip-dst'),
            array('2025-07-27 06:37', 'ORGNAME', 0, 1402, 'ip-dst'),
            array('2025-08-04 02:48', 'ORGNAME', 2, 1309, 'domain|ip'),
            array('2025-08-08 12:56', 'CthulhuSPRL.be', 0, 1402, 'ip-dst'),
            array('2025-08-09 03:36', 'CthulhuSPRL.be', 0, 1309, 'domain|ip'),
            array('2025-08-09 11:05', 'Botvrij.eu', 1, 1309, 'domain|ip',
                'allowlist'),
            array('2025-08-14 10:02', 'Botvrij.eu', 0, 1309, 'domain|ip'),
            array('2025-08-14 11:11', 'CthulhuSPRL.be', 0, 1388, 'ip-dst'),
            array('2025-08-15 17:02', 'Botvrij.eu', 0, 1402, 'domain|ip'),
            array('2025-08-17 05:30', 'Botvrij.eu', 0, 1341, 'ip-src'),
            array('2025-08-18 12:54', 'CIRCL', 0, 1388, 'ip-dst'),
            array('2025-08-19 00:33', 'CIRCL', 0, 1309, 'domain|ip'),
            array('2025-08-19 15:36', 'CIRCL', 0, 1402, 'domain|ip'),
            array('2025-08-19 19:05', 'CthulhuSPRL.be', 0, 1309, 'domain|ip'),
            array('2025-08-23 17:26', 'CIRCL', 0, 1402, 'ip-dst'),
        ));
    }

    /**
     * 17 reports on the resolver over ten months, eleven of them
     * false positives — the sparse case, and the one that makes the
     * range control necessary: six rows fall in the last 90 days,
     * so a chart that opened there would be hiding two thirds of
     * what is known about the value.
     *
     * Columns: date · organisation · type · event · attribute
     * type · source.
     *
     * @return array
     */
    private static function benignSightingRows()
    {
        return self::sightingRows(array(
            array('2024-11-14 08:22', 'CIRCL', 1, 1259, 'ip-dst'),
            array('2024-11-28 14:09', 'CthulhuSPRL.be', 0, 1288, 'ip-src'),
            array('2024-12-03 15:41', 'CthulhuSPRL.be', 1, 1276, 'ip-dst'),
            array('2024-12-14 10:32', 'CIRCL', 0, 1298, 'domain|ip'),
            array('2025-01-01 06:48', 'CIRCL', 0, 1301, 'ip-dst'),
            array('2025-01-09 11:17', 'CIRCL', 1, 1301, 'ip-dst', 'allowlist'),
            array('2025-02-21 09:03', 'CIRCL', 1, 1288, 'ip-src'),
            array('2025-03-15 17:52', 'Botvrij.eu', 1, 1298, 'domain|ip'),
            array('2025-04-02 13:29', 'CthulhuSPRL.be', 1, 1301, 'ip-dst'),
            array('2025-04-09 00:42', 'CIRCL', 0, 1301, 'ip-dst'),
            array('2025-05-18 10:44', 'CIRCL', 1, 1276, 'ip-dst', 'allowlist'),
            array('2025-06-06 07:15', 'CIRCL', 1, 1259, 'ip-dst'),
            array('2025-06-27 19:38', 'CthulhuSPRL.be', 1, 1298, 'domain|ip'),
            array('2025-07-14 14:42', 'CIRCL', 0, 1301, 'ip-dst'),
            array('2025-07-19 12:06', 'CIRCL', 1, 1288, 'ip-src'),
            array('2025-08-11 11:16', 'Botvrij.eu', 0, 1259, 'ip-dst'),
            array('2025-08-12 14:50', 'CthulhuSPRL.be', 1, 1301, 'ip-dst',
                'allowlist'),
        ));
    }

    /* ==================================================================
     * Relationships
     * ------------------------------------------------------------------
     * Three notions of "related", kept apart because they are not
     * degrees of one thing. Co-occurrence and near-match are rows in
     * MISP's correlation table; an asserted relationship is a sentence
     * somebody wrote, and it is never counted into the correlation
     * total.
     * ================================================================== */

    /**
     * A correlated value, rolled up one row per distinct value.
     *
     * Columns: value · type · category · shared events · organisations ·
     * last together · distribution · object template · tag names ·
     * event ids.
     *
     * `shared_events` is how many of this value's events the two appear
     * in together, not how many correlation rows there are: MISP writes
     * one row per attribute pair, so a value in four shared events is
     * at least four rows.
     *
     * @param array $table
     * @return array
     */
    private static function relValueRows(array $table)
    {
        $rows = array();
        foreach ($table as $row) {
            $rows[] = array(
                'value' => $row[0],
                'type' => $row[1],
                'category' => $row[2],
                'shared_events' => $row[3],
                'orgs' => $row[4],
                'last_together' => $row[5],
                'distribution' => $row[6],
                'sharing_group' => $row[6] === 4
                    ? array('id' => 7, 'name' => 'CIRCL private sector')
                    : array('id' => null, 'name' => null),
                'object' => $row[7],
                'tags' => self::relTags($row[8]),
                'events' => $row[9],
            );
        }
        return $rows;
    }

    /**
     * The same correlations rolled up by event instead of by value.
     *
     * Columns: event id · info · date · organisation · shared values ·
     * distribution · tag names.
     *
     * @param array $table
     * @return array
     */
    private static function relEventRows(array $table)
    {
        $rows = array();
        foreach ($table as $row) {
            $rows[] = array(
                'event' => array(
                    'id' => $row[0],
                    'info' => $row[1],
                    'date' => $row[2],
                ),
                'org' => $row[3],
                'shared_values' => $row[4],
                'distribution' => $row[5],
                'sharing_group' => $row[5] === 4
                    ? array('id' => 7, 'name' => 'CIRCL private sector')
                    : array('id' => null, 'name' => null),
                'tags' => self::relTags($row[6]),
            );
        }
        return $rows;
    }

    /**
     * And rolled up by the object the correlated attributes sit in.
     *
     * Columns: object id · template · event id · organisation · related
     * values · relations.
     *
     * @param array $table
     * @return array
     */
    private static function relObjectRows(array $table)
    {
        $rows = array();
        foreach ($table as $row) {
            $rows[] = array(
                'object' => array('id' => $row[0], 'name' => $row[1]),
                'event' => $row[2],
                'org' => $row[3],
                'values' => $row[4],
                'relations' => $row[5],
            );
        }
        return $rows;
    }

    /**
     * The other attributes of an object this value is itself part of.
     *
     * Not a correlation at all: a join on `Attribute.object_id` over
     * occurrences the page has already fetched, with the edge named by
     * `ObjectReference.relationship_type`. Structural rather than
     * statistical, which is why it is listed above the ranked table.
     *
     * Columns: object id · template · relation · value · type · event
     * id · organisation.
     *
     * @param array $table
     * @return array
     */
    private static function relSiblingRows(array $table)
    {
        $rows = array();
        foreach ($table as $row) {
            $rows[] = array(
                'object' => array('id' => $row[0], 'name' => $row[1]),
                'relation' => $row[2],
                'value' => $row[3],
                'type' => $row[4],
                'event' => $row[5],
                'org' => $row[6],
            );
        }
        return $rows;
    }

    /**
     * The sibling section, aggregated from the raw join.
     *
     * One row per `(object template, relation, sibling value)` triple
     * with a distinct-object count beside it, which is the whole of
     * phase 18: the same sibling seen five hundred times is one row
     * that says five hundred, and five hundred distinct siblings rank
     * and page instead of scrolling.
     *
     * Small values keep their raw table here rather than a hand-written
     * aggregate, so that the aggregation of three rows demonstrably
     * stays three rows.
     *
     * @param array $table Raw rows, as relSiblingRows reads them.
     * @param array $meta Overrides — `hidden`, `page_size`, `cap`.
     * @return array
     */
    private static function relSiblings(array $table, array $meta = array())
    {
        $groups = array();
        $objects = array();
        foreach (self::relSiblingRows($table) as $row) {
            $template = $row['object']['name'];
            $key = $template . "\0" . $row['relation'] . "\0" . $row['value'];
            if (!isset($groups[$key])) {
                $groups[$key] = array(
                    'object' => $template,
                    'relation' => $row['relation'],
                    'value' => $row['value'],
                    'type' => $row['type'],
                    'objects' => array(),
                    'events' => array(),
                    'orgs' => array(),
                );
            }
            $groups[$key]['objects'][$row['object']['id']] = true;
            $groups[$key]['events'][$row['event']] = true;
            $groups[$key]['orgs'][$row['org']] = true;
            $objects[$row['object']['id']] = true;
        }

        $rows = array();
        foreach ($groups as $group) {
            $events = array_keys($group['events']);
            $held = count($group['objects']);
            $rows[] = array(
                'object' => $group['object'],
                'relation' => $group['relation'],
                'value' => $group['value'],
                'type' => $group['type'],
                'objects' => $held,
                'events' => count($events),
                /*
                 * Aggregating to a triple loses the object ids, so
                 * "which object" becomes a drill-down this pass does
                 * not build. The event link survives only where the
                 * row stands for a single object and so cannot be
                 * ambiguous about which event it means.
                 */
                'event' => $held === 1 && count($events) === 1
                    ? $events[0]
                    : null,
                'orgs' => array_keys($group['orgs']),
                'org_total' => count($group['orgs']),
            );
        }

        return self::relSiblingSection($rows, $meta + array(
            'total' => count($rows),
            'raw' => count($table),
            'objects' => count($objects),
            'in_objects' => count($objects),
        ));
    }

    /**
     * The same section for a value whose raw join is too large to write
     * down, given as the aggregate a `GROUP BY` would return.
     *
     * The listed rows are a ranked prefix and the totals are the real
     * ones, which is the idiom the co-occurrence pane beside it already
     * uses for 1,462 correlations — `1–8 of 32 (494 in total)` states
     * the cut instead of implying there is none.
     *
     * Columns: template · relation · value · type · objects · events ·
     * organisations · distinct organisations.
     *
     * @param array $table
     * @param array $meta
     * @return array
     */
    private static function relSiblingGroups(array $table, array $meta)
    {
        $rows = array();
        foreach ($table as $row) {
            $rows[] = array(
                'object' => $row[0],
                'relation' => $row[1],
                'value' => $row[2],
                'type' => $row[3],
                'objects' => (int)$row[4],
                'events' => (int)$row[5],
                'event' => null,
                'orgs' => $row[6],
                'org_total' => isset($row[7])
                    ? (int)$row[7]
                    : count($row[6]),
            );
        }
        return self::relSiblingSection($rows, $meta);
    }

    /**
     * Rank the rows and state the section's own bounds.
     *
     * PHP 8's sort is stable, so rows holding the same number of
     * objects keep the order they were authored in — which is what
     * lets a three-row value render exactly as it did before the
     * aggregation existed.
     *
     * @param array $rows
     * @param array $meta
     * @return array
     */
    private static function relSiblingSection(array $rows, array $meta)
    {
        usort($rows, function ($a, $b) {
            return $b['objects'] - $a['objects'];
        });

        $inObjects = (int)$meta['in_objects'];
        $limit = isset($meta['cap'])
            ? (int)$meta['cap']
            : self::SIBLING_JOIN_CAP;

        return array(
            'rows' => $rows,
            /*
             * Distinct triples the value has, listed or not. The badge
             * prints this and not `count($rows)`: phase 17 §9.4 found
             * the old badge stating a page as if it were a total.
             */
            'total' => (int)$meta['total'],
            'raw' => (int)$meta['raw'],
            'objects' => (int)$meta['objects'],
            'in_objects' => $inObjects,
            'cap' => array(
                'limit' => $limit,
                'applied' => $inObjects > $limit,
            ),
            /*
             * Sibling attributes inside an object the viewer can open
             * whose own distribution still keeps them out. Counted
             * where it can be, named where it cannot.
             */
            'hidden' => isset($meta['hidden']) ? (int)$meta['hidden'] : 0,
            'page_size' => isset($meta['page_size'])
                ? (int)$meta['page_size']
                : 8,
        );
    }

    /**
     * A CIDR containment row, re-derived rather than read.
     *
     * The correlation table records no provenance, so nothing in it
     * says a row came from the CIDR engine; the block, the prefix and
     * the address count are all recomputed at render time from the
     * network-block attribute.
     *
     * Columns: block · prefix · event id · organisation · distribution.
     *
     * @param array $table
     * @return array
     */
    private static function relCidrRows(array $table)
    {
        $rows = array();
        foreach ($table as $row) {
            $prefix = (int)$row[1];
            $rows[] = array(
                'block' => $row[0],
                'prefix' => $prefix,
                // What the block actually covers, so "closeness" is
                // grounded in a number rather than in a bar's width.
                'addresses' => 1 << (32 - $prefix),
                'event' => $row[2],
                'org' => $row[3],
                'distribution' => $row[4],
            );
        }
        return $rows;
    }

    /**
     * An analyst-asserted relationship — a claim, never a table row.
     *
     * Columns: relationship type · direction · target kind · target id ·
     * target label · text · organisation · date · distribution.
     *
     * @param array $table
     * @return array
     */
    private static function relClaims(array $table)
    {
        $claims = array();
        foreach ($table as $row) {
            $claims[] = array(
                'relationship_type' => $row[0],
                'direction' => $row[1],
                'target' => array(
                    'kind' => $row[2],
                    'id' => $row[3],
                    'label' => $row[4],
                ),
                'text' => $row[5],
                'org' => $row[6],
                'date' => $row[7],
                'distribution' => $row[8],
            );
        }
        return $claims;
    }

    /**
     * Tag records by name, so a row carries the real chip rather than
     * the tag's name as text.
     *
     * @param array $names
     * @return array
     */
    private static function relTags(array $names)
    {
        $colours = array(
            'tlp:amber' => '#FFC000',
            'tlp:green' => '#33FF00',
            'pap:amber' => '#FFC000',
            'type:OSINT' => '#004646',
            'workflow:state="reviewed"' => '#3F51B5',
        );
        $tags = array();
        foreach ($names as $name) {
            $tags[] = array(
                'name' => $name,
                'colour' => isset($colours[$name])
                    ? $colours[$name]
                    : '#6C757D',
                'is_galaxy' => strpos($name, 'misp-galaxy:') === 0,
            );
        }
        return $tags;
    }

    /**
     * The C2 address: 31 correlation rows, three of them near-matches,
     * and four claims nobody counted into the 31.
     *
     * The arithmetic is the point of the tab. 28 co-occurrence rows and
     * 3 CIDR rows make the 31 the Overview's lifecycle card prints and
     * the tab bar repeats. The 4 asserted claims are not correlations
     * and are not added to it — they are counted apart and said so, in
     * words, on the rail.
     *
     * @return array
     */
    private static function maliciousRelationships()
    {
        return array(
            'summary' => array(
                'correlations' => 31,
                'cooccurrence' => 28,
                'near' => 3,
                'asserted' => 4,
                'recorded' => null,
            ),
            'cooccurrence' => array(
                'suppressed' => false,
                'stored' => 28,
                'visible' => 24,
                'hidden' => 4,
                'distinct_values' => 18,
                'events' => 6,
                'page_size' => 8,
                'siblings' => self::maliciousRelationSiblings(),
                'rollups' => array(
                    'value' => array(
                        'total' => 18,
                        'rows' => self::maliciousRelationValues(),
                    ),
                    'event' => array(
                        'total' => 6,
                        'rows' => self::maliciousRelationEvents(),
                    ),
                    'object' => array(
                        'total' => 3,
                        'rows' => self::maliciousRelationObjects(),
                    ),
                ),
                'facets' => self::maliciousRelationFacets(),
                'categories' => array(
                    'Network activity',
                    'Payload delivery',
                ),
            ),
            'near' => array(
                'matches' => 3,
                'engines_active' => 1,
                'engines_idle' => 2,
                'threshold' => 40,
                'engines' => array(
                    array(
                        'id' => 'cidr',
                        'state' => 'active',
                        'rows' => self::relCidrRows(array(
                            array('185.234.216.0/22', 22, 1291,
                                'CthulhuSPRL.be', 3),
                            array('185.234.192.0/18', 18, 1265,
                                'ORGNAME', 1),
                            array('185.234.0.0/16', 16, 1265,
                                'ORGNAME', 1),
                        )),
                    ),
                    array(
                        'id' => 'ssdeep',
                        'state' => 'not_applicable',
                        'rows' => array(),
                    ),
                    array(
                        'id' => 'tld',
                        'state' => 'absent',
                        'rows' => array(),
                    ),
                ),
            ),
            'asserted' => array(
                'total' => 4,
                'orgs' => 3,
                'hidden' => 2,
                'occurrences' => 10,
                'claims' => self::relClaims(array(
                    array(
                        'related-to',
                        'outbound',
                        'Event',
                        1291,
                        'Phishing kit hosted on compromised WordPress',
                        __(
                            'Same operator as the kit in this event —'
                            . ' the panel login and the C2 answer on the'
                            . ' same certificate, and the kit posts its'
                            . ' harvested credentials straight here.'
                        ),
                        'CIRCL',
                        '2025-08-04',
                        1,
                    ),
                    array(
                        'similar-to',
                        'outbound',
                        'GalaxyCluster',
                        'APT28',
                        'Threat actor · Sofacy',
                        __(
                            'Infrastructure pattern matches the cluster:'
                            . ' /22 leased from the same reseller, same'
                            . ' three ports, same fortnight rotation.'
                            . ' Similarity, not attribution.'
                        ),
                        'Team-CIRCL',
                        '2025-06-21',
                        3,
                    ),
                    array(
                        'derived-from',
                        'inbound',
                        'Object',
                        90188,
                        'file · emotet-loader.dll',
                        __(
                            'The loader in this object resolves the C2'
                            . ' from an embedded list; this address is'
                            . ' the first entry of it, so the address'
                            . ' was derived from the sample.'
                        ),
                        'CthulhuSPRL.be',
                        '2025-05-09',
                        4,
                    ),
                    array(
                        'connects-to',
                        'outbound',
                        'Attribute',
                        4831577,
                        'ip-dst · 185.234.219.24',
                        __(
                            'Beaconing observed from the sandbox run'
                            . ' recorded in event 1291 — 8443/tcp every'
                            . ' 300 seconds for the whole capture.'
                        ),
                        'CIRCL',
                        '2025-04-30',
                        1,
                    ),
                )),
            ),
            'graph' => array(
                'edges' => 7,
                'nodes' => array(
                    'co' => array('domain', 'sha256', 'url'),
                    'near' => array('network-block', 'network-block'),
                    'human' => array('Event', 'Object'),
                ),
            ),
            'settings' => array(
                'correlation_limit' => 20,
                'ssdeep_threshold' => 40,
                'excluded' => false,
            ),
        );
    }

    /**
     * The 18 distinct values, ranked by shared events.
     *
     * 4 + 3 + 2 and fifteen ones: 24 shared-event memberships over 18
     * values, which is the visible half of the 28 stored rows. The
     * remaining 4 point into the seventh event, which this viewer
     * cannot open.
     *
     * @return array
     */
    private static function maliciousRelationValues()
    {
        $amber = array('tlp:amber');
        $green = array('tlp:green');
        $amberOsint = array('tlp:amber', 'type:OSINT');

        return self::relValueRows(array(
            array('update.cdn-analytics.net', 'domain', 'Network activity',
                4, array('CIRCL', 'CthulhuSPRL.be', 'Team-CIRCL', 'ORGNAME'),
                '2025-08-19', 3, 'domain-ip', $amberOsint,
                array(1251, 1265, 1279, 1284)),
            array('9f2c1b7ae4d8035c19f0b2a6d7c48e1a3b5f90d2'
                . 'c6e8471a90bd35f2e7c81046', 'sha256', 'Payload delivery',
                3, array('CIRCL', 'Team-CIRCL'), '2025-07-02', 3, 'file',
                $amber, array(1251, 1272, 1284)),
            array('http://update.cdn-analytics.net/wp/x.php', 'url',
                'Network activity', 2, array('CthulhuSPRL.be'),
                '2025-06-11', 1, null, $amberOsint, array(1265, 1291)),
            array('185.234.219.31', 'ip-dst', 'Network activity', 1,
                array('CIRCL'), '2025-05-28', 4, null, $green,
                array(1272)),
            array('invoice_2025_08.doc', 'filename', 'Payload delivery', 1,
                array('Team-CIRCL'), '2025-04-17', 1, null, $amber,
                array(1272)),
            array('cdn-analytics.net', 'domain', 'Network activity', 1,
                array('CthulhuSPRL.be'), '2025-02-08', 1, null, $amber,
                array(1291)),
            array('3a7f0d91c4b8e25a6f03d7c19b4e8025'
                . 'a1c9f36d70b8e42159cd03a6f7b81e94', 'sha256',
                'Payload delivery', 1, array('CIRCL'), '2025-06-05', 3,
                'file', $amber, array(1251)),
            array('panel.cdn-analytics.net', 'domain', 'Network activity',
                1, array('CIRCL'), '2025-05-14', 3, null, $amberOsint,
                array(1284)),
            array('http://panel.cdn-analytics.net/gate.php', 'url',
                'Network activity', 1, array('CIRCL'), '2025-05-02', 3,
                null, $amber, array(1284)),
            array('emotet-loader.dll', 'filename', 'Payload delivery', 1,
                array('ORGNAME'), '2025-02-19', 0, null, $amber,
                array(1265)),
            array('185.234.216.7', 'ip-dst', 'Network activity', 1,
                array('Team-CIRCL'), '2025-01-09', 4, null, $green,
                array(1272)),
            array('b12e94a07f3c8d5619e2b0a4c7d83f16'
                . '90ea25c8b7f04d3169ac52e8b0f79d34', 'sha256',
                'Payload delivery', 1, array('CthulhuSPRL.be'),
                '2025-03-27', 1, null, $amber, array(1291)),
            array('mail.cdn-analytics.net', 'domain', 'Network activity',
                1, array('ORGNAME'), '2025-01-22', 0, null, $amber,
                array(1265)),
            array('8443', 'port', 'Network activity', 1,
                array('CthulhuSPRL.be'), '2025-06-29', 1,
                'network-connection', $amber, array(1291)),
            array('setup_x86.exe', 'filename', 'Payload delivery', 1,
                array('CIRCL'), '2025-04-08', 3, 'file', $amberOsint,
                array(1251)),
            array('185.234.219.99', 'ip-dst', 'Network activity', 1,
                array('CIRCL'), '2025-03-14', 3, null, $green,
                array(1284)),
            array('dl.cdn-analytics.net', 'domain', 'Network activity', 1,
                array('Team-CIRCL'), '2025-07-30', 4, null, $amber,
                array(1272)),
            array('c9d03e6a15b78f24c0e9a3d6b8175029'
                . '4e7f01a63bd8c25e07f19a4b3c6d80e5', 'sha256',
                'Payload delivery', 1, array('ORGNAME'), '2025-02-27', 0,
                null, $amber, array(1265)),
        ));
    }

    /**
     * The same 24 memberships rolled up by event.
     *
     * @return array
     */
    private static function maliciousRelationEvents()
    {
        return self::relEventRows(array(
            array(1265, 'Suspicious download hosts, April 2025',
                '2025-04-11', 'ORGNAME', 5, 0, array('tlp:amber')),
            array(1272, 'Mass scanning activity against .lu netblocks',
                '2025-05-02', 'Team-CIRCL', 5, 4, array('tlp:green')),
            array(1284, 'OSINT - Emotet malspam campaign targeting .lu',
                '2025-07-14', 'CIRCL', 5, 3,
                array('tlp:amber', 'type:OSINT')),
            array(1251, 'OSINT - Emotet infrastructure, autumn 2024',
                '2024-10-08', 'CIRCL', 4, 3,
                array('tlp:amber', 'type:OSINT')),
            array(1291, 'Phishing kit hosted on compromised WordPress',
                '2025-08-02', 'CthulhuSPRL.be', 4, 1, array('tlp:amber')),
            array(1279, 'OSINT - Emotet infrastructure, June 2025',
                '2025-06-19', 'CIRCL', 1, 3, array('tlp:amber')),
        ));
    }

    /**
     * And by the object the correlated attribute sits in. Only ten of
     * the 24 do sit in one, which is why this roll-up is the shortest
     * of the three rather than a third view of the same length.
     *
     * @return array
     */
    private static function maliciousRelationObjects()
    {
        return self::relObjectRows(array(
            array(90188, 'file', 1251, 'CIRCL', 3,
                array('filename', 'sha256', 'sha1')),
            array(89771, 'domain-ip', 1279, 'CIRCL', 1,
                array('domain', 'ip', 'first-seen')),
            array(90412, 'network-connection', 1291, 'CthulhuSPRL.be', 1,
                array('ip-dst', 'dst-port')),
        ));
    }

    /**
     * The other attributes of the two objects this value is part of.
     *
     * Ten occurrences sit in two objects, so the aggregate is the raw
     * join: three triples, one object each. The section reads the same
     * as it did before phase 18 and its badge still says three.
     *
     * @return array
     */
    private static function maliciousRelationSiblings()
    {
        return self::relSiblings(array(
            array(89771, 'domain-ip', 'domain', 'update.cdn-analytics.net',
                'domain', 1279, 'CIRCL'),
            array(89771, 'domain-ip', 'first-seen',
                '2025-06-02T08:14:00+00:00', 'datetime', 1279, 'CIRCL'),
            array(90412, 'network-connection', 'dst-port', '8443', 'port',
                1291, 'CthulhuSPRL.be'),
        ));
    }

    /**
     * Six counted groups over the 18 rows.
     *
     * A count here is a `GROUP BY` on the correlation table, not a
     * tally of the page: `Event 1265` says 5 whether the reader is on
     * page one or page three, and would still say 5 at 1,847. The
     * per-event counts sum past 18 because a value can share more than
     * one event, which is the correct reading and not a mistake.
     *
     * @return array
     */
    private static function maliciousRelationFacets()
    {
        return array(
            'event' => array(
                self::facetRow('#1265 Suspicious download hosts',
                    '1265', 5),
                self::facetRow('#1272 Mass scanning activity',
                    '1272', 5),
                self::facetRow('#1284 Emotet malspam campaign',
                    '1284', 5),
                self::facetRow('#1251 Emotet infrastructure, autumn 2024',
                    '1251', 4),
                self::facetRow('#1291 Phishing kit on WordPress',
                    '1291', 4),
                self::facetRow('#1279 Emotet infrastructure, June 2025',
                    '1279', 1),
            ),
            'organisation' => array(
                self::facetRow('CIRCL', 'circl', 8),
                self::facetRow('CthulhuSPRL.be', 'cthulhusprl-be', 5),
                self::facetRow('Team-CIRCL', 'team-circl', 5),
                self::facetRow('ORGNAME', 'orgname', 4),
            ),
            'type' => array(
                self::facetRow('domain', 'domain', 5),
                self::facetRow('sha256', 'sha256', 4),
                self::facetRow('filename', 'filename', 3),
                self::facetRow('ip-dst', 'ip-dst', 3),
                self::facetRow('url', 'url', 2),
                self::facetRow('port', 'port', 1),
            ),
            'object' => array(
                self::facetRow('file', 'file', 3),
                self::facetRow('domain-ip', 'domain-ip', 1),
                self::facetRow('network-connection',
                    'network-connection', 1),
            ),
            'tag' => array(
                self::tagFacet('tlp:amber', '#FFC000', 0,
                    'tlp-amber', 15),
                self::tagFacet('type:OSINT', '#004646', 0,
                    'type-osint', 4),
                self::tagFacet('tlp:green', '#33FF00', 0,
                    'tlp-green', 3),
            ),
            'distribution' => array(
                self::distributionFacet(3, 7),
                self::distributionFacet(1, 5),
                self::distributionFacet(0, 3),
                self::distributionFacet(4, 3),
            ),
        );
    }

    /**
     * A shared hosting address: 1,847 correlations, and the pane that
     * has to page rather than cap.
     *
     * Nothing else about the tab changes at this cardinality, which is
     * the reading the three-section split exists to give. The near-match
     * block still has four rows because CIDR containment is bounded by
     * how many network blocks contain one address, not by how popular
     * the address is; the asserted section still has three claims,
     * because people write them one at a time.
     *
     * Only 24 of the 1,462 distinct values are carried here. The pane
     * says so rather than implying the fixture holds them all —
     * `value_pager` prints `1–8 of 24` and `(1,462 in total)` beside it.
     *
     * @return array
     */
    private static function conflictedRelationships()
    {
        return array(
            'summary' => array(
                'correlations' => 1847,
                'cooccurrence' => 1843,
                'near' => 4,
                'asserted' => 3,
                'recorded' => null,
            ),
            'cooccurrence' => array(
                'suppressed' => false,
                'stored' => 1843,
                'visible' => 1747,
                'hidden' => 96,
                'distinct_values' => 1462,
                'events' => 5,
                'page_size' => 8,
                'siblings' => self::relSiblings(array(
                    array(96331, 'domain-ip', 'domain',
                        'secure-mybank-lu.com', 'domain', 1402, 'CIRCL'),
                    array(96331, 'domain-ip', 'first-seen',
                        '2025-07-19T21:40:00+00:00', 'datetime', 1402,
                        'CIRCL'),
                    array(94018, 'domain-ip', 'domain',
                        'login-portal-verify.net', 'domain', 1309,
                        'CIRCL'),
                )),
                'rollups' => array(
                    'value' => array(
                        'total' => 1462,
                        'rows' => self::conflictedRelationValues(),
                    ),
                    'event' => array(
                        'total' => 5,
                        'rows' => self::relEventRows(array(
                            array(1402,
                                'Phishing campaign impersonating a .lu bank',
                                '2025-07-19', 'CIRCL', 812, 3,
                                array('tlp:amber')),
                            array(1388, 'Gamaredon infrastructure, July batch',
                                '2025-07-04', 'CthulhuSPRL.be', 486, 1,
                                array('tlp:amber')),
                            array(1341,
                                'Suspicious traffic against a member portal',
                                '2025-05-21', 'ORGNAME', 271, 0,
                                array('tlp:green')),
                            array(1309,
                                'Phishing kit reuse across .lu targets',
                                '2025-03-12', 'CIRCL', 149, 3,
                                array('tlp:amber', 'type:OSINT')),
                            array(1356, 'Cloudflare-fronted C2 survey',
                                '2025-06-02', 'Team-CIRCL', 29, 4,
                                array('tlp:green')),
                        )),
                    ),
                    'object' => array(
                        'total' => 2,
                        'rows' => self::relObjectRows(array(
                            array(96331, 'domain-ip', 1402, 'CIRCL', 611,
                                array('domain', 'ip', 'first-seen')),
                            array(94018, 'domain-ip', 1309, 'CIRCL', 138,
                                array('domain', 'ip')),
                        )),
                    ),
                ),
                'facets' => self::conflictedRelationFacets(),
                'categories' => array(
                    'Network activity',
                    'Payload delivery',
                ),
            ),
            'near' => array(
                'matches' => 4,
                'engines_active' => 1,
                'engines_idle' => 2,
                'threshold' => 40,
                'engines' => array(
                    array(
                        'id' => 'cidr',
                        'state' => 'active',
                        'rows' => self::relCidrRows(array(
                            array('104.21.32.0/20', 20, 1402, 'CIRCL', 3),
                            array('104.21.0.0/16', 16, 1388,
                                'CthulhuSPRL.be', 1),
                            array('104.16.0.0/13', 13, 1341, 'ORGNAME', 0),
                            array('104.0.0.0/8', 8, 1341, 'ORGNAME', 0),
                        )),
                    ),
                    array(
                        'id' => 'ssdeep',
                        'state' => 'not_applicable',
                        'rows' => array(),
                    ),
                    array(
                        'id' => 'tld',
                        'state' => 'absent',
                        'rows' => array(),
                    ),
                ),
            ),
            'asserted' => array(
                'total' => 3,
                'orgs' => 2,
                'hidden' => 1,
                'occurrences' => 9,
                'claims' => self::relClaims(array(
                    array(
                        'related-to',
                        'outbound',
                        'Event',
                        1402,
                        'Phishing campaign impersonating a .lu bank',
                        __(
                            'The phishing hostname resolved here for the'
                            . ' fortnight of the campaign. Shared'
                            . ' hosting, so the address is evidence of'
                            . ' the front and not of the operator.'
                        ),
                        'CIRCL',
                        '2025-07-22',
                        3,
                    ),
                    array(
                        'similar-to',
                        'inbound',
                        'Attribute',
                        5103011,
                        'domain|ip · secure-mybank-lu.com|104.21.34.198',
                        __(
                            'Same reverse proxy as the other .lu bank'
                            . ' lookalikes this quarter — the second half'
                            . ' of the pair is the interesting one, and'
                            . ' it is this address.'
                        ),
                        'CthulhuSPRL.be',
                        '2025-06-08',
                        1,
                    ),
                    array(
                        'connects-to',
                        'outbound',
                        'GalaxyCluster',
                        'Gamaredon Group',
                        'Threat actor · Primitive Bear',
                        __(
                            'Recorded because the address answered for a'
                            . ' Gamaredon domain for four days. Weak: the'
                            . ' host is shared and thousands of unrelated'
                            . ' names answer from it.'
                        ),
                        'CIRCL',
                        '2025-04-30',
                        3,
                    ),
                )),
            ),
            'graph' => array(
                'edges' => 7,
                'nodes' => array(
                    'co' => array('domain', 'domain', 'url'),
                    'near' => array('network-block', 'network-block'),
                    'human' => array('Event', 'Attribute'),
                ),
            ),
            'settings' => array(
                'correlation_limit' => 20,
                'ssdeep_threshold' => 40,
                'excluded' => false,
            ),
        );
    }

    /**
     * 24 of the 1,462, which is what the pager's `(1,462 in total)`
     * exists to say. Ranked the same way as the malicious value's, so
     * the reader who has seen one recognises the other.
     *
     * @return array
     */
    private static function conflictedRelationValues()
    {
        $amber = array('tlp:amber');
        $green = array('tlp:green');
        $amberOsint = array('tlp:amber', 'type:OSINT');
        $network = 'Network activity';
        $payload = 'Payload delivery';

        return self::relValueRows(array(
            array('secure-mybank-lu.com', 'domain', $network, 4,
                array('CIRCL', 'CthulhuSPRL.be', 'ORGNAME'), '2025-08-23',
                3, 'domain-ip', $amberOsint, array(1309, 1341, 1388, 1402)),
            array('login-portal-verify.net', 'domain', $network, 3,
                array('CIRCL', 'ORGNAME'), '2025-08-11', 3, 'domain-ip',
                $amber, array(1309, 1341, 1402)),
            array('https://secure-mybank-lu.com/auth/session', 'url',
                $network, 3, array('CIRCL'), '2025-08-04', 3, null,
                $amber, array(1309, 1388, 1402)),
            array('mybank-lu-support.com', 'domain', $network, 2,
                array('CIRCL', 'CthulhuSPRL.be'), '2025-07-28', 1, null,
                $amber, array(1388, 1402)),
            array('104.21.34.201', 'ip-dst', $network, 2,
                array('Team-CIRCL'), '2025-07-16', 4, null, $green,
                array(1341, 1356)),
            array('verify-account-lu.net', 'domain', $network, 2,
                array('ORGNAME'), '2025-07-09', 0, null, $amber,
                array(1341, 1402)),
            array('https://login-portal-verify.net/lu/', 'url', $network,
                1, array('CIRCL'), '2025-06-30', 3, null, $amberOsint,
                array(1309)),
            array('kit_bank_lu.zip', 'filename', $payload, 1,
                array('CthulhuSPRL.be'), '2025-06-24', 1, null, $amber,
                array(1388)),
            array('7d41ac09e8b25f36104ba7c9d2e08153'
                . '6ba09c47e1d82f350ae96b73c04d1f28', 'sha256', $payload,
                1, array('CthulhuSPRL.be'), '2025-06-18', 1, null, $amber,
                array(1388)),
            array('portal-mybank.lu', 'domain', $network, 1,
                array('CIRCL'), '2025-06-11', 3, null, $amber,
                array(1402)),
            array('104.21.34.77', 'ip-dst', $network, 1,
                array('Team-CIRCL'), '2025-06-02', 4, null, $green,
                array(1356)),
            array('https://verify-account-lu.net/otp', 'url', $network, 1,
                array('ORGNAME'), '2025-05-27', 0, null, $amber,
                array(1341)),
            array('mybank-lu.click', 'domain', $network, 1,
                array('ORGNAME'), '2025-05-21', 0, null, $green,
                array(1341)),
            array('otp_form.php', 'filename', $payload, 1,
                array('CIRCL'), '2025-05-14', 3, null, $amber,
                array(1402)),
            array('e0b71cd4a8253f09617ac0d3b8e42159'
                . 'cd7f03a6b18e945207cf3a61d8b0e472', 'sha256', $payload,
                1, array('CIRCL'), '2025-05-06', 3, null, $amber,
                array(1309)),
            array('bank-lu-secure.info', 'domain', $network, 1,
                array('CthulhuSPRL.be'), '2025-04-28', 1, null, $amber,
                array(1388)),
            array('104.21.35.14', 'ip-dst', $network, 1,
                array('CIRCL'), '2025-04-19', 3, null, $green,
                array(1402)),
            array('https://mybank-lu-support.com/reset', 'url', $network,
                1, array('CthulhuSPRL.be'), '2025-04-11', 1, null,
                $amberOsint, array(1388)),
            array('session_keeper.js', 'filename', $payload, 1,
                array('CIRCL'), '2025-04-02', 3, null, $amber,
                array(1309)),
            array('cdn-mybank-lu.net', 'domain', $network, 1,
                array('Team-CIRCL'), '2025-03-25', 4, null, $green,
                array(1356)),
            array('a3f80c62b91d47e50a28c6f1b70d9e43'
                . '82c05a7e61bd39f048ac2b57e0d813f6', 'sha256', $payload,
                1, array('ORGNAME'), '2025-03-18', 0, null, $amber,
                array(1341)),
            array('secure-lu-banking.top', 'domain', $network, 1,
                array('CIRCL'), '2025-03-12', 3, null, $amber,
                array(1309)),
            array('104.21.33.240', 'ip-dst', $network, 1,
                array('CthulhuSPRL.be'), '2025-03-04', 1, null, $green,
                array(1388)),
            array('phish_bundle.tar.gz', 'filename', $payload, 1,
                array('CIRCL'), '2025-02-24', 3, null, $amber,
                array(1402)),
        ));
    }

    /**
     * The counts that do not move when the list pages.
     *
     * This is the graft's whole justification, so the numbers are
     * deliberately larger than anything the pane renders: `Event 1402`
     * says 812 while the page shows eight rows. A count of the page
     * would say eight, and would be wrong in a way the reader could not
     * see.
     *
     * @return array
     */
    private static function conflictedRelationFacets()
    {
        return array(
            'event' => array(
                self::facetRow('#1402 Phishing campaign impersonating a bank',
                    '1402', 812),
                self::facetRow('#1388 Gamaredon infrastructure', '1388', 486),
                self::facetRow('#1341 Suspicious traffic, member portal',
                    '1341', 271),
                self::facetRow('#1309 Phishing kit reuse', '1309', 149),
                self::facetRow('#1356 Cloudflare-fronted C2 survey',
                    '1356', 29),
            ),
            'organisation' => array(
                self::facetRow('CIRCL', 'circl', 733),
                self::facetRow('CthulhuSPRL.be', 'cthulhusprl-be', 402),
                self::facetRow('ORGNAME', 'orgname', 341),
                self::facetRow('Team-CIRCL', 'team-circl', 96),
            ),
            'type' => array(
                self::facetRow('domain', 'domain', 981),
                self::facetRow('url', 'url', 274),
                self::facetRow('ip-dst', 'ip-dst', 118),
                self::facetRow('sha256', 'sha256', 63),
                self::facetRow('filename', 'filename', 26),
            ),
            'object' => array(
                self::facetRow('domain-ip', 'domain-ip', 749),
            ),
            'tag' => array(
                self::tagFacet('tlp:amber', '#FFC000', 0,
                    'tlp-amber', 1104),
                self::tagFacet('tlp:green', '#33FF00', 0,
                    'tlp-green', 358),
                self::tagFacet('type:OSINT', '#004646', 0,
                    'type-osint', 212),
            ),
            'distribution' => array(
                self::distributionFacet(3, 796),
                self::distributionFacet(1, 402),
                self::distributionFacet(0, 235),
                self::distributionFacet(4, 29),
            ),
        );
    }

    /**
     * The resolver everybody has: past `MISP.correlation_limit`, so
     * MISP stored nothing at all.
     *
     * 21,904 is not a row count. It is the `occurrence` column of the
     * `over_correlating_values` row MISP wrote instead of correlating —
     * which is why the first section renders a suppressed band and not
     * an empty state. "No rows" here means "too many to store", the
     * opposite of "none".
     *
     * The other two sections are untouched by that. CIDR containment is
     * re-derived at render time from the network-block attributes and
     * never reads the correlation table, and an analyst claim is not a
     * correlation at all — so both still have rows on a value whose
     * correlations were never written.
     *
     * @return array
     */
    private static function benignRelationships()
    {
        return array(
            'summary' => array(
                /*
                 * `correlations` is what the tab bar and the Overview
                 * print; `cooccurrence` is what MISP actually stored,
                 * which is nothing. `recorded` is the number the two
                 * differ by — the `occurrence` column of the
                 * `over_correlating_values` row written in place of
                 * the correlations. The tab's job here is to make that
                 * gap legible rather than to hide it behind a zero.
                 */
                'correlations' => 21904,
                'cooccurrence' => 0,
                'near' => 3,
                'asserted' => 2,
                'recorded' => 21904,
            ),
            'cooccurrence' => array(
                'suppressed' => true,
                'stored' => 0,
                'visible' => 0,
                'hidden' => 0,
                'distinct_values' => 0,
                'events' => 0,
                'page_size' => 8,
                'siblings' => self::relSiblings(array(
                    array(90886, 'domain-ip', 'domain', 'dns.google',
                        'domain', 1298, 'CthulhuSPRL.be'),
                )),
                'rollups' => array(
                    'value' => array('total' => 0, 'rows' => array()),
                    'event' => array('total' => 0, 'rows' => array()),
                    'object' => array('total' => 0, 'rows' => array()),
                ),
                'facets' => null,
                'categories' => array(),
            ),
            'near' => array(
                'matches' => 3,
                'engines_active' => 1,
                'engines_idle' => 2,
                'threshold' => 40,
                'engines' => array(
                    array(
                        'id' => 'cidr',
                        'state' => 'active',
                        'rows' => self::relCidrRows(array(
                            array('8.8.8.0/24', 24, 1288, 'Team-CIRCL', 3),
                            array('8.8.0.0/16', 16, 1276, 'ORGNAME', 3),
                            array('8.0.0.0/9', 9, 1259, 'Botvrij.eu', 3),
                        )),
                    ),
                    array(
                        'id' => 'ssdeep',
                        'state' => 'not_applicable',
                        'rows' => array(),
                    ),
                    array(
                        'id' => 'tld',
                        'state' => 'absent',
                        'rows' => array(),
                    ),
                ),
            ),
            'asserted' => array(
                'total' => 2,
                'orgs' => 2,
                'hidden' => 0,
                'occurrences' => 9,
                'claims' => self::relClaims(array(
                    array(
                        'related-to',
                        'outbound',
                        'Event',
                        1288,
                        'DNS tunnelling attempt against a member',
                        __(
                            'The resolver is the carrier, not the'
                            . ' target. Recorded so the event keeps its'
                            . ' shape; the address itself is Google'
                            . ' Public DNS and should never be blocked.'
                        ),
                        'Team-CIRCL',
                        '2025-06-14',
                        3,
                    ),
                    array(
                        'similar-to',
                        'inbound',
                        'Attribute',
                        4884560,
                        'ip-src · 8.8.4.4',
                        __(
                            'Its sibling resolver. Both are on every'
                            . ' allowlist worth having, and both keep'
                            . ' turning up in exfiltration captures for'
                            . ' the same uninteresting reason.'
                        ),
                        'CIRCL',
                        '2025-02-27',
                        3,
                    ),
                )),
            ),
            'graph' => array(
                'edges' => 4,
                'nodes' => array(
                    'co' => array(),
                    'near' => array('network-block', 'network-block'),
                    'human' => array('Event', 'Attribute'),
                ),
            ),
            'settings' => array(
                'correlation_limit' => 20,
                'ssdeep_threshold' => 40,
                'excluded' => false,
            ),
        );
    }

    /**
     * Every panel of the tab in its own empty state.
     *
     * Not one shared "nothing here": the three notions fail differently
     * and a reader has to be able to tell "the engine stored nothing"
     * from "no engine applies" from "nobody has said anything". The
     * settings card is the exception — what MISP is configured to count
     * is true whether or not this value has anything to count.
     *
     * @return array
     */
    private static function emptyRelationships()
    {
        return array(
            'summary' => array(
                'correlations' => 0,
                'cooccurrence' => 0,
                'near' => 0,
                'asserted' => 0,
                'recorded' => null,
            ),
            'cooccurrence' => array(
                'suppressed' => false,
                'stored' => 0,
                'visible' => 0,
                'hidden' => 0,
                'distinct_values' => 0,
                'events' => 0,
                'page_size' => 8,
                'siblings' => self::relSiblings(array()),
                'rollups' => array(
                    'value' => array('total' => 0, 'rows' => array()),
                    'event' => array('total' => 0, 'rows' => array()),
                    'object' => array('total' => 0, 'rows' => array()),
                ),
                // Null rather than groups of zeroes: a facet bar of
                // zeroes claims there are rows to narrow.
                'facets' => null,
                'categories' => array(),
            ),
            'near' => array(
                'matches' => 0,
                'engines_active' => 0,
                'engines_idle' => 0,
                'threshold' => 40,
                'engines' => array(),
            ),
            'asserted' => array(
                'total' => 0,
                'orgs' => 0,
                'hidden' => 0,
                'occurrences' => 0,
                'claims' => array(),
            ),
            'graph' => array(
                'edges' => 0,
                'nodes' => array(
                    'co' => array(),
                    'near' => array(),
                    'human' => array(),
                ),
            ),
            'settings' => array(
                'correlation_limit' => 20,
                'ssdeep_threshold' => 40,
                'excluded' => false,
            ),
        );
    }

    /* ==============================================================
     * Enrichment
     * --------------------------------------------------------------
     * Modules are matched on a type, so every builder here starts
     * from one, and the catalogues below are the list MISP would
     * report from `getEnabledModules()` filtered to that type.
     *
     * Nothing in this data is an intelligence claim. A returned
     * element is a type and a shape — the value it carries is drawn
     * as a withheld bar, because no third party was queried to
     * produce this page and inventing what one would have said is the
     * one thing this tab must not do.
     * ============================================================== */

    /**
     * One module as the rail reads it.
     *
     * Columns: name · kind · spends quota · leaves the building ·
     * state · elements still standing · of which new · ran at, in the
     * last run · last ran at, ever · seconds taken · result shape ·
     * days since it last ran.
     *
     * `elements` is what the module returned *and that still stands*:
     * a dismissed element is subtracted, so the rail's counts and the
     * header's "awaiting review" are the same quantity.
     *
     * @param array $row
     * @return array
     */
    private static function enrichModuleRow(array $row)
    {
        return array(
            'name' => $row[0],
            'kind' => $row[1],
            'cost' => array('quota' => $row[2], 'external' => $row[3]),
            'state' => $row[4],
            'elements' => $row[5],
            'new' => $row[6],
            'ran_at' => $row[7],
            'last_ran_at' => $row[8],
            'took' => $row[9],
            'shape' => $row[10],
            'stale_days' => $row[11],
        );
    }

    /**
     * @param array $table
     * @return array
     */
    private static function enrichModuleRows(array $table)
    {
        $rows = array();
        foreach ($table as $row) {
            $rows[] = self::enrichModuleRow($row);
        }
        return $rows;
    }

    /**
     * One loose attribute a module returned.
     *
     * Columns: type · to_ids · date · provenance · bar width, in rem
     * · the other modules that returned the same value.
     *
     * The provenance column is `new`, `known` — the value already
     * exists in MISP, which is what stops an analyst adding a
     * duplicate — or null for an element that is neither.
     *
     * @param array $table
     * @return array
     */
    private static function enrichAttrRows(array $table)
    {
        $rows = array();
        foreach ($table as $row) {
            $rows[] = array(
                'type' => $row[0],
                'to_ids' => $row[1],
                'date' => $row[2],
                'is_new' => $row[3] === 'new',
                'known' => $row[3] === 'known',
                'width' => $row[4],
                'also' => isset($row[5]) ? $row[5] : array(),
            );
        }
        return $rows;
    }

    /**
     * One object a module returned, in the `misp_standard` shape.
     *
     * An object is new only when every attribute in it is new: half a
     * new object is a template that gained a relation, which is not
     * the same claim and does not get the chip.
     *
     * @param string $name Object template name
     * @param bool $isNew
     * @param array $table Columns: relation · type · bar width
     * @return array
     */
    private static function enrichObject($name, $isNew, array $table)
    {
        $elements = array();
        foreach ($table as $row) {
            $elements[] = array(
                'relation' => $row[0],
                'type' => $row[1],
                'width' => $row[2],
            );
        }
        return array(
            'name' => $name,
            'attributes' => count($elements),
            'is_new' => $isNew,
            'elements' => $elements,
        );
    }

    /**
     * The `All results` row and the pane behind it, derived from the
     * per-module results rather than stated.
     *
     * The rail's whole cost is cross-module reading and this row is
     * what buys it back, so its numbers have to be the modules' own
     * numbers — written down twice they would drift the first time a
     * module's result changed.
     *
     * @param array $modules
     * @param array $results
     * @return array
     */
    private static function enrichMerge(array $modules, array $results)
    {
        $objects = array();
        $attributes = array();
        $answered = 0;
        foreach ($modules as $module) {
            $name = $module['name'];
            if (empty($results[$name]) || $module['elements'] < 1) {
                continue;
            }
            $answered++;
            $result = $results[$name];
            foreach ($result['objects'] as $object) {
                $object['module'] = $name;
                $objects[] = $object;
            }
            foreach ($result['attributes'] as $attribute) {
                $attribute['module'] = $name;
                $attributes[] = $attribute;
            }
        }

        $elements = 0;
        $new = 0;
        foreach ($objects as $object) {
            $elements += $object['attributes'];
            $new += $object['is_new'] ? $object['attributes'] : 0;
        }
        foreach ($attributes as $attribute) {
            $elements++;
            $new += $attribute['is_new'] ? 1 : 0;
        }

        return array(
            'modules' => $answered,
            'elements' => $elements,
            'new' => $new,
            'objects' => $objects,
            'attributes' => $attributes,
        );
    }

    /**
     * Everything the tab reads, assembled so the header, the rail and
     * the pane cannot disagree about a count.
     *
     * @param array $spec
     * @return array
     */
    private static function enrichment(array $spec)
    {
        $modules = $spec['modules'];
        $results = isset($spec['results']) ? $spec['results'] : array();

        $pending = 0;
        foreach ($modules as $module) {
            $pending += $module['elements'];
        }

        return array(
            'type' => $spec['type'],
            'type_inferred' => !empty($spec['type_inferred']),
            'last_run' => isset($spec['last_run'])
                ? $spec['last_run']
                : null,
            'pending' => $pending,
            'timeout' => 10,
            'cortex_timeout' => 120,
            'service' => $spec['service'],
            'modules' => $modules,
            'results' => $results,
            'merged' => self::enrichMerge($modules, $results),
        );
    }

    /**
     * The nine modules MISP has enabled for an IP on this instance.
     *
     * `$runs` maps a module name onto the columns that differ per
     * value — state, counts and timings. A module absent from it has
     * never been run against the value, which is the majority case.
     *
     * @param array $runs
     * @return array
     */
    private static function ipModules(array $runs)
    {
        // name · kind · spends quota · leaves the building
        $catalogue = array(
            array('virustotal', 'expansion', true, true),
            array('shodan', 'expansion', true, true),
            array('circl_passivedns', 'expansion', false, true),
            array('ipasn', 'expansion', false, true),
            array('reversedns', 'expansion', false, true),
            array('threatminer', 'expansion', true, true),
            array('urlhaus', 'expansion', false, true),
            array('rbl', 'expansion', false, true),
            array('Abuse_Finder_3_0', 'cortex', false, true),
        );
        return self::modulesFrom($catalogue, $runs);
    }

    /**
     * @param array $runs
     * @return array
     */
    private static function domainModules(array $runs)
    {
        $catalogue = array(
            array('virustotal', 'expansion', true, true),
            array('circl_passivedns', 'expansion', false, true),
            array('dns', 'expansion', false, true),
            array('whois', 'expansion', false, true),
            array('urlhaus', 'expansion', false, true),
            array('threatminer', 'expansion', true, true),
            array('Abuse_Finder_3_0', 'cortex', false, true),
        );
        return self::modulesFrom($catalogue, $runs);
    }

    /**
     * @param array $runs
     * @return array
     */
    private static function hashModules(array $runs)
    {
        $catalogue = array(
            array('virustotal', 'expansion', true, true),
            array('hashlookup', 'expansion', false, true),
            array('malwarebazaar', 'expansion', false, true),
            array('threatminer', 'expansion', true, true),
        );
        return self::modulesFrom($catalogue, $runs);
    }

    /**
     * @param array $runs
     * @return array
     */
    private static function urlModules(array $runs)
    {
        $catalogue = array(
            array('virustotal', 'expansion', true, true),
            array('urlhaus', 'expansion', false, true),
            array('urlscan', 'expansion', true, true),
            array('Abuse_Finder_3_0', 'cortex', false, true),
        );
        return self::modulesFrom($catalogue, $runs);
    }

    /**
     * @param array $catalogue
     * @param array $runs
     * @return array
     */
    private static function modulesFrom(array $catalogue, array $runs)
    {
        // never · no elements · none new · not in this run · never ran
        // · no timing · no shape · not stale, unused
        $untouched = array('never', 0, 0, null, null, null, null, null);
        $table = array();
        foreach ($catalogue as $entry) {
            $table[] = array_merge(
                $entry,
                isset($runs[$entry[0]]) ? $runs[$entry[0]] : $untouched
            );
        }
        return self::enrichModuleRows($table);
    }

    /**
     * Which type MISP would match modules on for a value that has no
     * occurrence, and so no attribute row to read a type from.
     *
     * The page's subject is a value string, and `ComplexTypeTool`
     * classifies one by shape. That is a weaker claim than reading an
     * attribute's type and the panel says so — but it is the only
     * honest way a value nobody has ever recorded can show a rail at
     * all, and the rail is what tells the reader what running would
     * cost.
     *
     * @param string $value
     * @return string|null
     */
    private static function inferType($value)
    {
        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return 'ip-dst';
        }
        if (preg_match('/^[a-f0-9]{32}$/i', $value)) {
            return 'md5';
        }
        if (preg_match('/^[a-f0-9]{40}$/i', $value)) {
            return 'sha1';
        }
        if (preg_match('/^[a-f0-9]{64}$/i', $value)) {
            return 'sha256';
        }
        if (preg_match('#^https?://\S+$#i', $value)) {
            return 'url';
        }
        $domain = '/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+'
            . '[a-z]{2,}$/i';
        if (preg_match($domain, $value)) {
            return 'domain';
        }
        return null;
    }

    /**
     * The module catalogue for an inferred type, or an empty rail.
     *
     * A value MISP cannot classify has no valid module, and that is a
     * real state rather than a missing one: there is nothing to offer
     * to run, and the panel says which of the two it is.
     *
     * @param string|null $type
     * @return array
     */
    private static function modulesForType($type)
    {
        if ($type === 'ip-dst') {
            return self::ipModules(array());
        }
        if ($type === 'domain') {
            return self::domainModules(array());
        }
        if ($type === 'url') {
            return self::urlModules(array());
        }
        if (in_array($type, array('md5', 'sha1', 'sha256'), true)) {
            return self::hashModules(array());
        }
        return array();
    }

    /**
     * The populated case the mockup drew: a run that answered on six
     * modules, timed out on one, came back empty-handed on one, and
     * left the Cortex analyser out altogether.
     *
     * @return array
     */
    private static function maliciousEnrichment()
    {
        // state · elements · new · ran at · last ran · took · shape ·
        // stale days
        $ran = '2025-08-24 09:14';
        $modules = self::ipModules(array(
            'virustotal' => array(
                'ok', 6, 2, $ran, $ran, 1.8, 'misp_standard', 1),
            'shodan' => array(
                'timeout', 0, 0, $ran, $ran, 10.0, null, 1),
            'circl_passivedns' => array(
                'ok', 9, 1, $ran, $ran, 0.7, 'misp_standard', 1),
            'ipasn' => array(
                'ok', 1, 0, $ran, $ran, 0.4, 'simplified', 1),
            'reversedns' => array(
                'ok', 3, 0, $ran, $ran, 0.2, 'simplified', 1),
            'threatminer' => array(
                'ok', 2, 0, $ran, $ran, 2.4, 'simplified', 1),
            'urlhaus' => array(
                'ok', 2, 0, $ran, $ran, 0.9, 'misp_standard', 1),
            'rbl' => array(
                'none', 0, 0, $ran, $ran, 3.1, null, 1),
            /*
             * Ran, but not in this run. That is a fifth rail wording
             * and its own group: a module MISP did not include is not
             * a module that answered with nothing, and neither is a
             * module nobody has ever tried.
             */
            'Abuse_Finder_3_0' => array(
                'never', 0, 0, null, '2025-06-02 11:40', null, null, 83),
        ));

        $previous = '2025-08-11 14:02';
        $results = array(
            'virustotal' => array(
                'delta' => array(
                    'new' => 2,
                    'previous_run' => $previous,
                    'unchanged' => 4,
                ),
                'objects' => array(
                    self::enrichObject('virustotal-report', false, array(
                        array('permalink', 'link', 13),
                        array('detection-ratio', 'text', 4),
                        array('last-submission', 'datetime', 7),
                    )),
                ),
                'attributes' => self::enrichAttrRows(array(
                    array('hostname', true, '2025-08-24', 'new', 11,
                        array('circl_passivedns', 'reversedns')),
                    array('domain', true, '2025-08-24', 'known', 9),
                    array('url', true, '2025-08-24', 'new', 15),
                )),
                'dismissed' => 1,
            ),
            'circl_passivedns' => array(
                'delta' => array(
                    'new' => 1,
                    'previous_run' => $previous,
                    'unchanged' => 8,
                ),
                'objects' => array(
                    self::enrichObject('passive-dns', false, array(
                        array('rrname', 'text', 10),
                        array('rrtype', 'text', 3),
                        array('time-first', 'datetime', 7),
                        array('time-last', 'datetime', 7),
                    )),
                    self::enrichObject('passive-dns', false, array(
                        array('rrname', 'text', 12),
                        array('rrtype', 'text', 3),
                        array('time-first', 'datetime', 7),
                        array('time-last', 'datetime', 7),
                    )),
                ),
                'attributes' => self::enrichAttrRows(array(
                    array('hostname', false, '2025-08-24', 'new', 12,
                        array('virustotal', 'reversedns')),
                )),
                'dismissed' => 0,
            ),
            'ipasn' => array(
                'delta' => array(
                    'new' => 0,
                    'previous_run' => $previous,
                    'unchanged' => 1,
                ),
                'objects' => array(),
                'attributes' => self::enrichAttrRows(array(
                    array('AS', false, '2025-08-24', null, 5),
                )),
                'dismissed' => 0,
            ),
            'reversedns' => array(
                'delta' => array(
                    'new' => 0,
                    'previous_run' => $previous,
                    'unchanged' => 3,
                ),
                'objects' => array(),
                'attributes' => self::enrichAttrRows(array(
                    array('hostname', false, '2025-08-24', 'known', 11,
                        array('virustotal', 'circl_passivedns')),
                    array('hostname', false, '2025-08-24', null, 13),
                    array('hostname', false, '2025-08-24', null, 9),
                )),
                'dismissed' => 0,
            ),
            'threatminer' => array(
                'delta' => array(
                    'new' => 0,
                    'previous_run' => $previous,
                    'unchanged' => 2,
                ),
                'objects' => array(),
                'attributes' => self::enrichAttrRows(array(
                    array('domain', true, '2025-08-24', 'known', 10),
                    array('hostname', false, '2025-08-24', null, 12),
                )),
                'dismissed' => 0,
            ),
            'urlhaus' => array(
                'delta' => array(
                    'new' => 0,
                    'previous_run' => $previous,
                    'unchanged' => 2,
                ),
                'objects' => array(
                    self::enrichObject('url', false, array(
                        array('url', 'url', 15),
                        array('resource_path', 'text', 8),
                    )),
                ),
                'attributes' => array(),
                'dismissed' => 0,
            ),
        );

        return self::enrichment(array(
            'type' => 'ip-dst',
            'last_run' => $ran,
            'service' => array(
                'reachable' => true,
                'checked' => '2025-08-25 07:02',
                'note' => null,
            ),
            'modules' => $modules,
            'results' => $results,
        ));
    }

    /**
     * The run that was cut short: three modules answered and then the
     * module service stopped answering, which is why the other six
     * were never tried.
     *
     * Service-down is a distinct state from "nothing queried yet" and
     * this is where the tab renders it — a rail of dashed rows under
     * an unreachable service means something different from the same
     * rail under a healthy one.
     *
     * @return array
     */
    private static function conflictedEnrichment()
    {
        $ran = '2025-08-22 16:30';
        $modules = self::ipModules(array(
            'virustotal' => array(
                'ok', 4, 3, $ran, $ran, 2.1, 'misp_standard', 3),
            'shodan' => array(
                'timeout', 0, 0, $ran, $ran, 10.0, null, 3),
            'ipasn' => array(
                'none', 0, 0, $ran, $ran, 0.5, null, 3),
        ));

        $results = array(
            'virustotal' => array(
                'delta' => array(
                    'new' => 3,
                    'previous_run' => '2025-07-30 09:12',
                    'unchanged' => 1,
                ),
                'objects' => array(
                    self::enrichObject('virustotal-report', true, array(
                        array('permalink', 'link', 13),
                        array('detection-ratio', 'text', 4),
                        array('last-submission', 'datetime', 7),
                    )),
                ),
                'attributes' => self::enrichAttrRows(array(
                    array('domain', false, '2025-08-22', 'known', 9),
                )),
                'dismissed' => 0,
            ),
        );

        return self::enrichment(array(
            'type' => 'ip-dst',
            'last_run' => $ran,
            'service' => array(
                'reachable' => false,
                'checked' => '2025-08-25 07:02',
                'note' => __(
                    'misp-modules did not answer within the 1 second'
                    . ' MISP allows the module list. Nothing here is'
                    . ' stale because of it — the six untried modules'
                    . ' were never tried, which is a different claim.'
                ),
            ),
            'modules' => $modules,
            'results' => $results,
        ));
    }

    /**
     * The quiet case: a run this morning where everything that
     * answered was already in MISP, and one module answered with
     * nothing at all.
     *
     * @return array
     */
    private static function benignEnrichment()
    {
        $ran = '2025-08-25 06:40';
        $previous = '2025-08-18 06:40';
        $modules = self::ipModules(array(
            'virustotal' => array(
                'ok', 1, 0, $ran, $ran, 1.4, 'simplified', 0),
            'ipasn' => array(
                'ok', 1, 0, $ran, $ran, 0.3, 'simplified', 0),
            'reversedns' => array(
                'ok', 1, 0, $ran, $ran, 0.2, 'simplified', 0),
            'rbl' => array(
                'none', 0, 0, $ran, $ran, 2.8, null, 0),
        ));

        $results = array(
            'virustotal' => array(
                'delta' => array(
                    'new' => 0,
                    'previous_run' => $previous,
                    'unchanged' => 1,
                ),
                'objects' => array(),
                'attributes' => self::enrichAttrRows(array(
                    array('domain', false, '2025-08-25', 'known', 9,
                        array('reversedns')),
                )),
                'dismissed' => 0,
            ),
            'ipasn' => array(
                'delta' => array(
                    'new' => 0,
                    'previous_run' => $previous,
                    'unchanged' => 1,
                ),
                'objects' => array(),
                'attributes' => self::enrichAttrRows(array(
                    array('AS', false, '2025-08-25', null, 5),
                )),
                'dismissed' => 0,
            ),
            'reversedns' => array(
                'delta' => array(
                    'new' => 0,
                    'previous_run' => $previous,
                    'unchanged' => 1,
                ),
                'objects' => array(),
                'attributes' => self::enrichAttrRows(array(
                    array('hostname', false, '2025-08-25', 'known', 10,
                        array('virustotal')),
                )),
                'dismissed' => 2,
            ),
        );

        return self::enrichment(array(
            'type' => 'ip-dst',
            'last_run' => $ran,
            'service' => array(
                'reachable' => true,
                'checked' => '2025-08-25 07:02',
                'note' => null,
            ),
            'modules' => $modules,
            'results' => $results,
        ));
    }

    /**
     * Nothing queried yet — the majority case in production, and the
     * one this tab is shaped around.
     *
     * The rail is full and every row carries what running it would
     * cost. There is no run to be stale against, no delta and no
     * results, and nothing has been sent anywhere.
     *
     * @param string $value
     * @return array
     */
    private static function unknownEnrichment($value)
    {
        $type = self::inferType($value);

        return self::enrichment(array(
            'type' => $type,
            'type_inferred' => true,
            'last_run' => null,
            'service' => array(
                'reachable' => true,
                'checked' => '2025-08-25 07:02',
                'note' => null,
            ),
            'modules' => self::modulesForType($type),
            'results' => array(),
        ));
    }

    /* ==================================================================
     * Analyst data tab
     * ==================================================================
     * Two readings of one set of notes and opinions: where each
     * organisation stands on the 0-100 scale, and the argument in the
     * order it happened.
     *
     * Every number the standing panel prints is derived here from the
     * rows themselves — the mean, the ten buckets, the empty middle,
     * the per-organisation note counts and last activity. Nothing in
     * MISP computes any of them (`05-analyst.md` §11), so the panel
     * says `computed at render` and this is where the computing is.
     */

    /**
     * The five bands MISP itself uses for an opinion, so the word on
     * this page is the word the product uses.
     *
     * @param int $score
     * @return string
     */
    private static function opinionBand($score)
    {
        if ($score >= 81) {
            return __('Strongly agree');
        }
        if ($score >= 61) {
            return __('Agree');
        }
        if ($score >= 41) {
            return __('Neutral');
        }
        if ($score >= 21) {
            return __('Disagree');
        }
        return __('Strongly disagree');
    }

    /**
     * Which way an opinion argues about the value.
     *
     * The band word and the reading are two different things: MISP
     * calls 61-80 "Agree", and what it agrees with is the claim that
     * the value is hostile. The Verdict tab's histogram already reads
     * the axis this way and this tab follows it — the contradiction
     * `05-analyst.md` §11 records.
     *
     * @param int $score
     * @return string malicious | benign | none
     */
    private static function opinionReads($score)
    {
        if ($score > 50) {
            return 'malicious';
        }
        if ($score < 50) {
            return 'benign';
        }
        // Exactly 50 argues neither way, and inventing a side for it
        // would be the page's own claim rather than the analyst's.
        return 'none';
    }

    /**
     * Which of the ten bands a score falls in. 0-10 is the first band
     * and every band after it is ten wide, which is what makes 100 the
     * last band rather than an eleventh.
     *
     * @param int $score
     * @return int 0-9
     */
    private static function opinionBucket($score)
    {
        $score = max(0, min(100, (int)$score));
        return $score <= 10 ? 0 : (int)ceil($score / 10) - 1;
    }

    /**
     * @return array The ten band labels, in the Verdict tab's spelling
     *               so the two histograms read as one object.
     */
    private static function opinionBucketLabels()
    {
        return array(
            '0–10', '11–20', '21–30', '31–40', '41–50',
            '51–60', '61–70', '71–80', '81–90', '91–100',
        );
    }

    /**
     * Everything the standing panel states about the set as a whole.
     *
     * Derived rather than written down, so a fixture that changes one
     * organisation's opinion cannot leave behind a mean, a gap and a
     * histogram describing the old set. The Verdict tab's opinion card
     * reads the same array, which is what stops one value carrying two
     * different distributions on two tabs.
     *
     * @param array $orgs Rows from `analystStanding`
     * @return array
     */
    private static function opinionAggregate(array $orgs)
    {
        $scores = array();
        foreach ($orgs as $org) {
            $scores[] = (int)$org['score'];
        }
        sort($scores);
        $n = count($scores);

        $buckets = array();
        foreach (self::opinionBucketLabels() as $label) {
            $buckets[] = array('label' => $label, 'count' => 0);
        }
        foreach ($scores as $score) {
            $buckets[self::opinionBucket($score)]['count']++;
        }
        $empty = 0;
        foreach ($buckets as $bucket) {
            if ($bucket['count'] === 0) {
                $empty++;
            }
        }

        $mean = array_sum($scores) / $n;
        /*
         * One decimal only when the mean is not a whole number. `62.5`
         * is a fact about four opinions; `63` would be a rounding this
         * panel has no reason to perform on the one number it is
         * already asking the reader to distrust.
         */
        $meanLabel = $mean == (int)$mean
            ? (string)(int)$mean
            : (string)round($mean, 1);

        /*
         * The widest run with no opinion in it — the empty middle the
         * whole panel is arranged around. Measured between two
         * opinions somebody actually holds rather than between two
         * band edges, because that is the claim being made.
         */
        $gap = null;
        for ($i = 1; $i < $n; $i++) {
            $span = $scores[$i] - $scores[$i - 1];
            if ($gap === null || $span > $gap['points']) {
                $gap = array(
                    'from' => $scores[$i - 1],
                    'to' => $scores[$i],
                    'points' => $span,
                );
            }
        }

        $nearest = null;
        foreach ($scores as $score) {
            $distance = abs($mean - $score);
            if ($nearest === null || $distance < $nearest) {
                $nearest = $distance;
            }
        }

        $clusters = self::opinionClusters($scores, $gap);

        return array(
            'n' => $n,
            'orgs' => count($orgs),
            'mean' => $mean,
            'mean_label' => $meanLabel,
            'mean_nearest' => $nearest,
            /*
             * Whether the mean describes a reading nobody holds. Five
             * points is half a band: closer than that and striking the
             * number through would be theatre, further and it is the
             * panel's whole point.
             */
            'mean_orphan' => $nearest !== null && $nearest >= 5,
            'buckets' => $buckets,
            'empty_bands' => $empty,
            'gap' => $gap,
            'clusters' => $clusters,
            'note' => self::opinionNote($clusters, $gap, $n, count($orgs)),
        );
    }

    /**
     * The two positions, or the one.
     *
     * Split at the widest gap and nowhere else. Splitting at every gap
     * over some width turns four opinions into four clusters and says
     * nothing; the reader's question is where the set divides, and a
     * set divides in one place. Below two bands there is no division
     * worth naming and the whole set is one position.
     *
     * @param array $scores Sorted ascending
     * @param array|null $gap The widest gap, from `opinionAggregate`
     * @return array
     */
    private static function opinionClusters(array $scores, $gap)
    {
        if ($gap === null || $gap['points'] < 20) {
            return array($scores);
        }

        $low = array();
        $high = array();
        foreach ($scores as $score) {
            if ($score <= $gap['from']) {
                $low[] = $score;
            } else {
                $high[] = $score;
            }
        }
        return array($low, $high);
    }

    /**
     * The sub-line under the panel title, shaped by what the numbers
     * turned out to be rather than written once and left to go stale.
     *
     * @param array $clusters
     * @param array|null $gap
     * @param int $n
     * @param int $orgs
     * @return string
     */
    private static function opinionNote(array $clusters, $gap, $n, $orgs)
    {
        $bits = array();
        $bits[] = sprintf(
            __('%1$s from %2$s'),
            __n('%s opinion', '%s opinions', $n, $n),
            __n('%s organisation', '%s organisations', $orgs, $orgs)
        );

        if (count($clusters) < 2 || $gap === null) {
            $bits[] = __('one position, no disagreement to read');
            return implode(' · ', $bits);
        }

        $bits[] = sprintf(
            __('two positions %s apart'),
            __n(
                '%s point',
                '%s points',
                $gap['points'],
                $gap['points']
            )
        );
        $bits[] = sprintf(
            __('nothing between %1$s and %2$s'),
            $gap['from'],
            $gap['to']
        );

        return implode(' · ', $bits);
    }

    /**
     * One organisation's position, with the parts of it that are
     * properties of the thread rather than of the opinion — how many
     * notes it wrote, when it was last heard from — read off the
     * thread instead of restated beside it.
     *
     * @param array $opinions org => array(score, date)
     * @param array $thread
     * @return array
     */
    private static function analystStanding(array $opinions, array $thread)
    {
        $rows = array();
        foreach ($opinions as $org => $held) {
            $notes = 0;
            $last = $held['date'];
            self::walkThread($thread, function ($item) use (
                $org,
                &$notes,
                &$last
            ) {
                if ($item['org'] !== $org) {
                    return;
                }
                if ($item['kind'] === 'note') {
                    $notes++;
                }
                if ($item['date'] > $last) {
                    $last = $item['date'];
                }
            });

            $score = (int)$held['score'];
            $rows[] = array(
                'org' => $org,
                'score' => $score,
                'label' => self::opinionBand($score),
                'reads' => self::opinionReads($score),
                'date' => $held['date'],
                'notes' => $notes,
                'last' => $last,
            );
        }

        /*
         * Highest first. The panel is read as a scale, and a scale that
         * starts in the middle because that is the order the rows were
         * written is not one.
         */
        usort($rows, function ($a, $b) {
            return $b['score'] - $a['score'];
        });

        return $rows;
    }

    /**
     * @param array $thread
     * @param callable $fn Called with every item at every depth
     * @return void
     */
    private static function walkThread(array $thread, $fn)
    {
        foreach ($thread as $item) {
            $fn($item);
            if (!empty($item['children'])) {
                self::walkThread($item['children'], $fn);
            }
        }
    }

    /**
     * What the thread contains, counted rather than declared.
     *
     * Top-level items are what the tab counts, because a reply is
     * written on an item and not on the value. The replies are counted
     * separately and said separately.
     *
     * @param array $thread
     * @return array
     */
    private static function analystCounts(array $thread)
    {
        $counts = array(
            'items' => count($thread),
            'opinions' => 0,
            'notes' => 0,
            'replies' => 0,
        );
        foreach ($thread as $item) {
            if ($item['kind'] === 'opinion') {
                $counts['opinions']++;
            } else {
                $counts['notes']++;
            }
        }
        self::walkThread($thread, function ($item) use (&$counts) {
            $counts['replies'] += count($item['children']);
        });

        return $counts;
    }

    /**
     * One thread item. The defaults are the ordinary case, so a row
     * only states what is unusual about it.
     *
     * @param array $spec
     * @return array
     */
    private static function analystItem(array $spec)
    {
        $item = array_merge(array(
            'kind' => 'note',
            'org' => 'CIRCL',
            'author' => null,
            'date' => null,
            'distribution' => 3,
            'sharing_group' => null,
            'language' => null,
            'score' => null,
            /*
             * What the item rates. An opinion written on a note rates
             * the note, and `05-analyst.md` §5 makes this explicit
             * rather than leaving the template to infer it from the
             * attachment: keeping it out of the aggregate is the whole
             * reason the distinction exists.
             */
            'rates' => 'value',
            'body' => '',
            'attached_to' => array('kind' => 'attribute'),
            'children' => array(),
            'max_depth_reached' => false,
        ), $spec);

        if ($item['kind'] !== 'opinion') {
            $item['label'] = null;
            $item['reads'] = 'none';
            return $item;
        }

        $item['label'] = self::opinionBand((int)$item['score']);
        // It has a reading, but not one about this value.
        $item['reads'] = $item['rates'] === 'value'
            ? self::opinionReads((int)$item['score'])
            : 'none';

        return $item;
    }

    /**
     * Assemble the tab's two panels from one thread.
     *
     * @param array $opinions org => array(score, date)
     * @param array $thread
     * @param string|null $aclNote
     * @return array
     */
    private static function analystTab(
        array $opinions,
        array $thread,
        $aclNote = null
    ) {
        $standing = self::analystStanding($opinions, $thread);

        return array(
            'counts' => self::analystCounts($thread),
            'standing' => array(
                'orgs' => $standing,
                /*
                 * Null, not a mean of zero. Zero is a reading somebody
                 * could have meant; the absence of any opinion is not.
                 */
                'aggregate' => empty($standing)
                    ? null
                    : self::opinionAggregate($standing),
            ),
            'thread' => $thread,
            'acl_note' => $aclNote,
        );
    }

    /**
     * The malicious value's argument.
     *
     * Six items and three replies. The two notes and the two most
     * recent opinions are the same rows the Overview preview shows, so
     * the text a reader met on the first tab is the text they meet
     * again here rather than a second version of it.
     *
     * @return array
     */
    private static function maliciousAnalystTab()
    {
        $preview = self::maliciousAnalystData();
        $notes = $preview['Note'];
        $opinions = $preview['Opinion'];

        $thread = array(
            self::analystItem(array(
                'kind' => 'note',
                'org' => 'CIRCL',
                'author' => $notes[0]['authors'],
                'date' => '2025-08-22',
                'body' => $notes[0]['note'],
                'attached_to' => array(
                    'kind' => 'attribute',
                    'type' => 'ip-dst',
                    'event' => 1284,
                ),
                'children' => array(
                    self::analystItem(array(
                        'kind' => 'note',
                        'org' => 'Team-CIRCL',
                        'author' => 'erin@team-circl.example',
                        'date' => '2025-08-20',
                        'distribution' => 2,
                        'attached_to' => array(
                            'kind' => 'object',
                            'name' => 'network-connection',
                            'event' => 1284,
                        ),
                        'body' => "Same certificate on 8443 as well."
                            . " Fingerprint is in the"
                            . " `network-connection` object on this"
                            . " event.",
                    )),
                ),
            )),
            self::analystItem(array(
                'kind' => 'opinion',
                'score' => $opinions[0]['opinion'],
                'org' => 'CIRCL',
                'author' => $opinions[0]['authors'],
                'date' => '2025-08-21',
                'body' => $opinions[0]['comment'],
                'attached_to' => array(
                    'kind' => 'attribute',
                    'type' => 'ip-dst',
                    'event' => 1284,
                ),
                'children' => array(
                    self::analystItem(array(
                        'kind' => 'note',
                        'org' => 'CthulhuSPRL.be',
                        'author' => 'bob@cthulhu.example',
                        'date' => '2025-08-20',
                        'language' => 'en',
                        'distribution' => 4,
                        'sharing_group' => 'Sharing group A',
                        'attached_to' => array(
                            'kind' => 'attribute',
                            'type' => 'ip-dst',
                            'event' => 1284,
                        ),
                        /*
                         * The one body written as markdown. MISP stores
                         * it and renders none of it today, which is
                         * what makes rendering it here a decision this
                         * tab is making rather than a detail — see
                         * `05-analyst.md` §11.
                         */
                        'body' => "#### What our telemetry has\n"
                            . "Three beacons between 2025-08-04 and"
                            . " 2025-08-19, all to 8080, all from the"
                            . " same subnet.\n\n"
                            . "- TLS certificate matches the June"
                            . " infrastructure\n"
                            . "- JA3 `a0e9f5b8c4d3` on every"
                            . " connection\n"
                            . "- No traffic at all after 2025-08-19\n\n"
                            . "> The gap since the 19th is the part I"
                            . " would not read as retirement yet.",
                        'children' => array(
                            self::analystItem(array(
                                'kind' => 'opinion',
                                'score' => 68,
                                'rates' => 'note',
                                'org' => 'Team-CIRCL',
                                'author' => 'erin@team-circl.example',
                                'date' => '2025-08-19',
                                'distribution' => 2,
                                'attached_to' => array('kind' => 'note'),
                                'body' => 'Useful summary. The JA3 is'
                                    . ' the part worth circulating.',
                                'max_depth_reached' => true,
                            )),
                        ),
                    )),
                ),
            )),
            self::analystItem(array(
                'kind' => 'opinion',
                'score' => $opinions[1]['opinion'],
                'org' => 'ORGNAME',
                'author' => $opinions[1]['authors'],
                'date' => '2025-08-18',
                'distribution' => 1,
                'body' => $opinions[1]['comment'],
                'attached_to' => array(
                    'kind' => 'attribute',
                    'type' => 'ip-src',
                    'event' => 1291,
                ),
            )),
            self::analystItem(array(
                'kind' => 'opinion',
                'score' => 75,
                'org' => 'CthulhuSPRL.be',
                'author' => 'bob@cthulhu.example',
                'date' => '2025-08-11',
                'body' => 'Two of our customers saw the same beacon'
                    . ' interval. Good enough for us.',
                'attached_to' => array(
                    'kind' => 'attribute',
                    'type' => 'ip-dst',
                    'event' => 1288,
                ),
            )),
            self::analystItem(array(
                'kind' => 'note',
                'org' => 'CthulhuSPRL.be',
                'author' => $notes[1]['authors'],
                'date' => '2025-08-06',
                'body' => $notes[1]['note'],
                /*
                 * Event-level, and the one item on the tab that shows
                 * what that costs: it is inherited by every occurrence
                 * in the event rather than said about this value.
                 */
                'attached_to' => array(
                    'kind' => 'event',
                    'event' => 1288,
                ),
            )),
            self::analystItem(array(
                'kind' => 'opinion',
                'score' => 60,
                'org' => 'Team-CIRCL',
                'author' => 'erin@team-circl.example',
                'date' => '2025-07-29',
                'distribution' => 0,
                'body' => 'We have it, but only from the shared feed.'
                    . ' Nothing of our own.',
                'attached_to' => array(
                    'kind' => 'attribute',
                    'type' => 'ip-dst',
                    'event' => 1279,
                ),
            )),
        );

        return self::analystTab(
            array(
                'CIRCL' => array('score' => 85, 'date' => '2025-08-21'),
                'CthulhuSPRL.be' => array(
                    'score' => 75,
                    'date' => '2025-08-11',
                ),
                'Team-CIRCL' => array('score' => 60, 'date' => '2025-07-29'),
                'ORGNAME' => array('score' => 30, 'date' => '2025-08-18'),
            ),
            $thread,
            __(
                'Notes and opinions on occurrences outside your'
                . ' distribution scope are not listed. MISP scopes the'
                . ' query and does not report what it withheld, so this'
                . ' note cannot carry a number.'
            )
        );
    }

    /**
     * The conflicted value's argument: two positions forty-eight points
     * apart with nothing between them, and one opinion from an
     * organisation that holds no occurrence of the value at all.
     *
     * That last row is not decoration. Analyst data hangs off an
     * object UUID, so any organisation that can see the attribute can
     * write about it — which is why this tab's list of organisations
     * is not the Verdict tab's list of organisations.
     *
     * @return array
     */
    private static function conflictedAnalystTab()
    {
        $preview = self::conflictedAnalystData();
        $notes = $preview['Note'];
        $opinions = $preview['Opinion'];

        $thread = array(
            self::analystItem(array(
                'kind' => 'opinion',
                'score' => $opinions[0]['opinion'],
                'org' => 'CIRCL',
                'author' => $opinions[0]['authors'],
                'date' => '2025-08-23',
                'body' => $opinions[0]['comment'],
                'attached_to' => array(
                    'kind' => 'attribute',
                    'type' => 'ip-dst',
                    'event' => 1402,
                ),
            )),
            self::analystItem(array(
                'kind' => 'note',
                'org' => 'ORGNAME',
                'author' => $notes[0]['authors'],
                'date' => '2025-08-20',
                'body' => $notes[0]['note'],
                'attached_to' => array(
                    'kind' => 'event',
                    'event' => 1402,
                ),
                'children' => array(
                    self::analystItem(array(
                        'kind' => 'note',
                        'org' => 'CthulhuSPRL.be',
                        'author' => 'bob@cthulhu.example',
                        'date' => '2025-08-19',
                        'attached_to' => array(
                            'kind' => 'event',
                            'event' => 1402,
                        ),
                        'body' => "Both can be true:\n\n"
                            . "- the hostnames are phishing\n"
                            . "- the address is shared edge"
                            . " infrastructure\n\n"
                            . "Blocking the second to reach the first"
                            . " is what we are arguing about.",
                        'children' => array(
                            self::analystItem(array(
                                'kind' => 'opinion',
                                'score' => 72,
                                'rates' => 'note',
                                'org' => 'CIRCL',
                                'author' => 'alice@circl.lu',
                                'date' => '2025-08-19',
                                'attached_to' => array('kind' => 'note'),
                                'body' => 'This is the clearest statement'
                                    . ' of the disagreement so far.',
                                'max_depth_reached' => true,
                            )),
                        ),
                    )),
                ),
            )),
            self::analystItem(array(
                'kind' => 'opinion',
                'score' => $opinions[1]['opinion'],
                'org' => 'ORGNAME',
                'author' => $opinions[1]['authors'],
                'date' => '2025-08-20',
                'body' => $opinions[1]['comment'],
                'attached_to' => array(
                    'kind' => 'attribute',
                    'type' => 'ip-dst',
                    'event' => 1402,
                ),
            )),
            self::analystItem(array(
                'kind' => 'note',
                'org' => 'CIRCL',
                'author' => $notes[1]['authors'],
                'date' => '2025-08-19',
                'body' => $notes[1]['note'],
                'attached_to' => array(
                    'kind' => 'attribute',
                    'type' => 'domain|ip',
                    'event' => 1397,
                ),
            )),
            self::analystItem(array(
                'kind' => 'opinion',
                'score' => 60,
                'org' => 'CthulhuSPRL.be',
                'author' => 'bob@cthulhu.example',
                'date' => '2025-08-14',
                'distribution' => 2,
                'body' => 'Abused, and we still block the hostname'
                    . ' rather than the address.',
                'attached_to' => array(
                    'kind' => 'attribute',
                    'type' => 'domain|ip',
                    'event' => 1397,
                ),
            )),
            self::analystItem(array(
                'kind' => 'opinion',
                'score' => 12,
                'org' => 'Team-CIRCL',
                'author' => 'erin@team-circl.example',
                'date' => '2025-08-12',
                'distribution' => 1,
                'body' => 'We hold nothing on this address. Recording'
                    . ' the position because it keeps arriving in'
                    . ' other people\'s blocklists.',
                /*
                 * The organisation with no occurrence of the value: it
                 * wrote on somebody else's attribute, which is the only
                 * kind of target analyst data has.
                 */
                'attached_to' => array(
                    'kind' => 'attribute',
                    'type' => 'ip-dst',
                    'event' => 1402,
                ),
            )),
            self::analystItem(array(
                'kind' => 'note',
                'org' => 'CthulhuSPRL.be',
                'author' => 'bob@cthulhu.example',
                'date' => '2025-08-08',
                'body' => 'Six hostnames on this edge in our own'
                    . ' passive DNS, four of them unrelated to the'
                    . ' phishing.',
                'attached_to' => array(
                    'kind' => 'object',
                    'name' => 'domain-ip',
                    'event' => 1397,
                ),
            )),
        );

        return self::analystTab(
            array(
                'CIRCL' => array('score' => 80, 'date' => '2025-08-23'),
                'CthulhuSPRL.be' => array(
                    'score' => 60,
                    'date' => '2025-08-14',
                ),
                'Team-CIRCL' => array('score' => 12, 'date' => '2025-08-12'),
                'ORGNAME' => array('score' => 10, 'date' => '2025-08-20'),
            ),
            $thread,
            __(
                'Notes and opinions on occurrences outside your'
                . ' distribution scope are not listed. MISP scopes the'
                . ' query and does not report what it withheld, so this'
                . ' note cannot carry a number.'
            )
        );
    }

    /**
     * The benign value's argument: two organisations reading it as
     * context and one that will not let it go.
     *
     * @return array
     */
    private static function benignAnalystTab()
    {
        $preview = self::benignAnalystData();
        $notes = $preview['Note'];
        $opinions = $preview['Opinion'];

        $thread = array(
            self::analystItem(array(
                'kind' => 'opinion',
                'score' => $opinions[0]['opinion'],
                'org' => 'CIRCL',
                'author' => $opinions[0]['authors'],
                'date' => '2025-08-21',
                'body' => $opinions[0]['comment'],
                'attached_to' => array(
                    'kind' => 'attribute',
                    'type' => 'ip-dst',
                    'event' => 1150,
                ),
            )),
            self::analystItem(array(
                'kind' => 'note',
                'org' => 'CIRCL',
                'author' => $notes[0]['authors'],
                'date' => '2025-08-21',
                'body' => $notes[0]['note'],
                'attached_to' => array(
                    'kind' => 'attribute',
                    'type' => 'ip-dst',
                    'event' => 1150,
                ),
            )),
            self::analystItem(array(
                'kind' => 'opinion',
                'score' => $opinions[1]['opinion'],
                'org' => 'CthulhuSPRL.be',
                'author' => $opinions[1]['authors'],
                'date' => '2025-08-19',
                'body' => $opinions[1]['comment'],
                'attached_to' => array(
                    'kind' => 'object',
                    'name' => 'network-connection',
                    'event' => 1150,
                ),
            )),
            self::analystItem(array(
                'kind' => 'opinion',
                'score' => $opinions[2]['opinion'],
                'org' => 'ORGNAME',
                'author' => $opinions[2]['authors'],
                'date' => '2025-08-05',
                'distribution' => 0,
                'body' => $opinions[2]['comment'],
                'attached_to' => array(
                    'kind' => 'attribute',
                    'type' => 'ip-dst',
                    'event' => 1163,
                ),
                'children' => array(
                    self::analystItem(array(
                        'kind' => 'note',
                        'org' => 'CIRCL',
                        'author' => 'alice@circl.lu',
                        'date' => '2025-08-05',
                        'attached_to' => array(
                            'kind' => 'attribute',
                            'type' => 'ip-dst',
                            'event' => 1163,
                        ),
                        'body' => 'Noted. The exfiltration is the finding;'
                            . ' the resolver is where it went out.',
                    )),
                ),
            )),
            self::analystItem(array(
                'kind' => 'note',
                'org' => 'ORGNAME',
                'author' => $notes[1]['authors'],
                'date' => '2025-08-04',
                'body' => $notes[1]['note'],
                'attached_to' => array(
                    'kind' => 'event',
                    'event' => 1163,
                ),
            )),
        );

        return self::analystTab(
            array(
                'CIRCL' => array('score' => 8, 'date' => '2025-08-21'),
                'CthulhuSPRL.be' => array(
                    'score' => 15,
                    'date' => '2025-08-19',
                ),
                'ORGNAME' => array('score' => 70, 'date' => '2025-08-05'),
            ),
            $thread,
            __(
                'Notes and opinions on occurrences outside your'
                . ' distribution scope are not listed. MISP scopes the'
                . ' query and does not report what it withheld, so this'
                . ' note cannot carry a number.'
            )
        );
    }


    /**
     * The publication record of the six occurrences the viewer can
     * see, keyed by event.
     *
     * Two dates per event and no more, because `first_publication` and
     * `publish_timestamp` are the only two MISP keeps. Where they are
     * the same the event was published once; where they differ, every
     * publication between them happened and is not recorded anywhere
     * outside the audit log.
     *
     * Event 1265 is absent because it has never been published, which
     * is a different fact from an event whose publications are not
     * recorded.
     *
     * @return array
     */
    private static function maliciousPublications()
    {
        return array(
            1251 => array(
                'info' => 'OSINT - Emotet infrastructure, autumn 2024',
                'org' => 'CIRCL',
                'first' => '2024-09-16 10:00:00',
                'last' => '2024-10-01 11:20:00',
            ),
            1272 => array(
                'info' => 'Mass scanning activity against .lu netblocks',
                'org' => 'Team-CIRCL',
                'first' => '2025-06-19 12:05:00',
                'last' => '2025-06-19 12:05:00',
            ),
            1279 => array(
                'info' => 'OSINT - Emotet infrastructure, June 2025',
                'org' => 'CIRCL',
                'first' => '2025-06-03 09:40:00',
                'last' => '2025-06-27 08:15:00',
            ),
            1291 => array(
                'info' => 'Phishing kit hosted on compromised WordPress',
                'org' => 'CthulhuSPRL.be',
                'first' => '2025-07-31 09:12:00',
                'last' => '2025-07-31 09:12:00',
            ),
            1284 => array(
                'info' => 'OSINT - Emotet malspam campaign targeting .lu',
                'org' => 'CIRCL',
                'first' => '2025-08-12 10:30:00',
                'last' => '2025-08-20 11:04:00',
            ),
        );
    }

    /**
     * Every event holding the CDN edge is published, and the two most
     * recent were republished — which is what a value on shared
     * infrastructure looks like: the events move on without it.
     *
     * @return array
     */
    private static function conflictedPublications()
    {
        return array(
            1309 => array(
                'info' => 'Phishing kit reuse across .lu targets',
                'org' => 'CIRCL',
                'first' => '2025-02-04 09:00:00',
                'last' => '2025-03-11 11:05:00',
            ),
            1341 => array(
                'info' => 'Suspicious traffic against a member portal',
                'org' => 'ORGNAME',
                'first' => '2025-05-14 15:45:00',
                'last' => '2025-05-14 15:45:00',
            ),
            1388 => array(
                'info' => 'Gamaredon infrastructure, July batch',
                'org' => 'CthulhuSPRL.be',
                'first' => '2025-07-03 10:15:00',
                'last' => '2025-07-29 08:40:00',
            ),
            1402 => array(
                'info' => 'Phishing campaign impersonating a .lu bank',
                'org' => 'CIRCL',
                'first' => '2025-08-18 08:00:00',
                'last' => '2025-08-23 20:10:00',
            ),
        );
    }

    /**
     * The resolver's events, one of them a feed import — which is the
     * ordinary way a benign value ends up in MISP at all.
     *
     * @return array
     */
    private static function benignPublications()
    {
        return array(
            1259 => array(
                'info' => 'Botvrij.eu feed import 2024-11-02',
                'org' => 'Botvrij.eu',
                'first' => '2024-11-02 09:30:00',
                'last' => '2024-11-02 09:30:00',
            ),
            1276 => array(
                'info' => 'Exfiltration over DNS - incident 2025-0442',
                'org' => 'ORGNAME',
                'first' => '2025-04-19 08:05:00',
                'last' => '2025-04-19 08:05:00',
            ),
            1288 => array(
                'info' => 'DNS tunnelling attempt against a member',
                'org' => 'Team-CIRCL',
                'first' => '2025-06-11 09:40:00',
                'last' => '2025-06-12 14:20:00',
            ),
            1298 => array(
                'info' => 'AsyncRAT sample analysis - config dump',
                'org' => 'CthulhuSPRL.be',
                'first' => '2025-07-30 16:00:00',
                'last' => '2025-07-30 16:00:00',
            ),
            1301 => array(
                'info' => 'Emotet malspam campaign - full IOC set',
                'org' => 'CIRCL',
                'first' => '2025-08-14 10:10:00',
                'last' => '2025-08-21 19:05:00',
            ),
        );
    }

    /* ==================================================================
     * Timeline tab
     * ------------------------------------------------------------------
     * One array of dated entries, and one of the facts that carry no
     * date at all. The panel derives its spine, its lanes and its
     * chronology from these two and from nothing else, so a count it
     * charts cannot disagree with a count it lists.
     *
     * Almost nothing here is typed in. The sightings are the same rows
     * the Sightings tab draws, the edits are the occurrences' own
     * `timestamp`, and the seen spans are their `first_seen` and
     * `last_seen` — because a chronology holding its own copy of those
     * dates is a chronology that can contradict the tab beside it.
     * ================================================================== */

    /**
     * @param array $spec Keys: occurrences, sightings, analyst, tags,
     *                    galaxies, feeds, publications, window, and
     *                    optionally audit_recorded, acl_note,
     *                    feeds_as_of
     * @return array
     */
    private static function timeline(array $spec)
    {
        $entries = array_merge(
            self::timelineSightings($spec['sightings']),
            self::timelinePublications($spec['publications']),
            self::timelineAnalyst($spec['analyst']),
            self::timelineEdits($spec['occurrences']),
            self::timelineSpans($spec['occurrences'])
        );

        usort($entries, function ($a, $b) {
            return strcmp($a['at'], $b['at']);
        });

        return array(
            'entries' => $entries,
            'undated' => self::timelineUndated($spec),
            'window' => $spec['window'],
            /*
             * `MISP.log_new_audit` defaults to false, so this is false
             * on every value here. It gates the edit lane's hatch
             * rather than the lane itself: one point per occurrence is
             * still a point, and hiding it would overstate the loss.
             */
            'audit_recorded' => !empty($spec['audit_recorded']),
            'acl_note' => isset($spec['acl_note'])
                ? $spec['acl_note']
                : null,
        );
    }

    /**
     * One entry per sighting, from the rows the Sightings tab already
     * has. Exact to the minute, and the only source on this tab that
     * is.
     *
     * @param array $rows `sightingRow()` shapes
     * @return array
     */
    private static function timelineSightings(array $rows)
    {
        $sources = array(
            self::SIGHTING => 'sighting',
            self::FALSE_POSITIVE => 'false_positive',
            self::EXPIRATION => 'expiration',
        );
        $notes = array(
            self::SIGHTING => null,
            self::FALSE_POSITIVE => __(
                'Type 1. Moves no decay score: MISP resets the clock on'
                . ' type-0 sightings only.'
            ),
            self::EXPIRATION => __(
                'Type 2. An organisation retiring the value, not'
                . ' contradicting it.'
            ),
        );

        $out = array();
        $total = count($rows);
        $n = 0;
        foreach ($rows as $row) {
            $n++;
            $type = (int)$row['type'];
            $title = $row['org'];
            if ($row['source'] !== null) {
                $title = sprintf(
                    __('%1$s — source %2$s'),
                    $row['org'],
                    $row['source']
                );
            }
            $note = $notes[$type];
            if ($note === null) {
                $note = sprintf(
                    __('Sighting %1$s of %2$s'),
                    $n,
                    $total
                );
            }
            $out[] = array(
                'at' => $row['date'] . ':00',
                'source' => $sources[$type],
                'precision' => 'exact',
                'title' => $title,
                'note' => $note,
                'org' => $row['org'],
                'ref' => array(
                    'attribute' => null,
                    'event' => $row['against']['event'],
                ),
                'span_to' => null,
            );
        }
        return $out;
    }

    /**
     * Two points per event, and never a history.
     *
     * `events.first_publication` and `events.publish_timestamp` are the
     * only two publications MISP keeps; an event published five times
     * still has exactly these. Where they are the same the event was
     * published once, and one entry is the whole truth.
     *
     * @param array $events Event id => info, org, first, last
     * @return array
     */
    private static function timelinePublications(array $events)
    {
        $out = array();
        foreach ($events as $id => $event) {
            $title = sprintf(
                __('event %1$s — %2$s'),
                $id,
                $event['info']
            );
            $once = $event['first'] === $event['last'];
            $out[] = array(
                'at' => $event['first'],
                'source' => 'publication',
                'precision' => 'first_last',
                'title' => $title,
                'note' => $once
                    ? __('Its only publication.')
                    : sprintf(
                        __(
                            'Its first publication. The latest is %s,'
                            . ' and nothing between the two is'
                            . ' recorded.'
                        ),
                        substr($event['last'], 0, 10)
                    ),
                'org' => $event['org'],
                'ref' => array('attribute' => null, 'event' => $id),
                'span_to' => null,
            );
            if ($once) {
                continue;
            }
            $out[] = array(
                'at' => $event['last'],
                'source' => 'publication',
                'precision' => 'first_last',
                'title' => $title,
                'note' => sprintf(
                    __(
                        'Its latest publication. The first was %s, and'
                        . ' nothing between the two is recorded.'
                    ),
                    substr($event['first'], 0, 10)
                ),
                'org' => $event['org'],
                'ref' => array('attribute' => null, 'event' => $id),
                'span_to' => null,
            );
        }
        return $out;
    }

    /**
     * Notes and opinions, from the records that carry a `created`.
     *
     * These are the rows the Overview preview and the Analyst data tab
     * both show, so the chronology quotes the text a reader has already
     * met rather than a second version of it.
     *
     * @param array $analyst The value's `analyst` array
     * @return array
     */
    private static function timelineAnalyst(array $analyst)
    {
        $out = array();
        foreach ($analyst['Note'] as $note) {
            $out[] = array(
                'at' => $note['created'],
                'source' => 'note',
                'precision' => 'exact',
                'title' => sprintf(
                    __('%1$s — “%2$s”'),
                    $note['authors'],
                    $note['note']
                ),
                'note' => sprintf(
                    __('%s · analyst note'),
                    $note['Org']['name']
                ),
                'org' => $note['Org']['name'],
                'ref' => array('attribute' => null, 'event' => null),
                'span_to' => null,
            );
        }
        foreach ($analyst['Opinion'] as $opinion) {
            $out[] = array(
                'at' => $opinion['created'],
                'source' => 'opinion',
                'precision' => 'exact',
                'title' => sprintf(
                    __('%1$s — %2$s / 100, “%3$s”'),
                    $opinion['Org']['name'],
                    $opinion['opinion'],
                    $opinion['comment']
                ),
                'note' => sprintf(
                    __('%s · analyst opinion'),
                    $opinion['authors']
                ),
                'org' => $opinion['Org']['name'],
                'ref' => array('attribute' => null, 'event' => null),
                'span_to' => null,
            );
        }
        return $out;
    }

    /**
     * One point per occurrence, and it is the latest one.
     *
     * `attributes.timestamp` says *when* an occurrence last changed and
     * never *what* changed or *how many times* — that is the audit log,
     * and it is off by default. So the title names the occurrence and
     * stops: an edit row claiming a field and a value would be
     * inventing the record this tab exists to be honest about.
     *
     * @param array $occurrences `fetchAttributes` shapes
     * @return array
     */
    private static function timelineEdits(array $occurrences)
    {
        $out = array();
        foreach ($occurrences as $occurrence) {
            $attribute = $occurrence['Attribute'];
            $out[] = array(
                'at' => gmdate('Y-m-d H:i:s', $attribute['timestamp']),
                'source' => 'edit',
                'precision' => 'latest',
                'title' => sprintf(
                    __('attribute %1$s in event %2$s — last modified'),
                    $attribute['id'],
                    $attribute['event_id']
                ),
                'note' => __(
                    'attributes.timestamp. What changed, and every'
                    . ' earlier edit, is not recorded.'
                ),
                'org' => $occurrence['Event']['Orgc']['name'],
                'ref' => array(
                    'attribute' => $attribute['id'],
                    'event' => $attribute['event_id'],
                ),
                'span_to' => null,
            );
        }
        return $out;
    }

    /**
     * One row per occurrence that carries a `first_seen`, and no
     * merging.
     *
     * Ten occurrences can hold ten different spans for one value, and
     * flattening them into one needs an aggregation rule nobody has
     * agreed on. The rule here is to invent none: each span stays its
     * own row, labelled with the occurrence it came from.
     *
     * A span is also a different kind of claim from everything else on
     * this axis — when the value was *active*, not when the record
     * changed — and the note says so on every row.
     *
     * @param array $occurrences `fetchAttributes` shapes
     * @return array
     */
    private static function timelineSpans(array $occurrences)
    {
        $out = array();
        foreach ($occurrences as $occurrence) {
            $attribute = $occurrence['Attribute'];
            if (empty($attribute['first_seen'])) {
                continue;
            }
            $from = self::plainTime($attribute['first_seen']);
            $to = empty($attribute['last_seen'])
                ? null
                : self::plainTime($attribute['last_seen']);
            if ($to === $from) {
                $to = null;
                $note = __(
                    'One instant, not a span. A claim about the value,'
                    . ' not a change to the record.'
                );
            } elseif ($to === null) {
                $note = __(
                    'The span is open — no last_seen. A claim about the'
                    . ' value, not a change to the record.'
                );
            } else {
                $note = sprintf(
                    __(
                        'Closes %s. A claim about the value, not a'
                        . ' change to the record.'
                    ),
                    substr($to, 0, 10)
                );
            }
            $out[] = array(
                'at' => $from,
                'source' => 'seen',
                'precision' => 'exact',
                'title' => sprintf(
                    __('attribute %1$s — first seen'),
                    $attribute['id']
                ),
                'note' => $note,
                'org' => $occurrence['Event']['Orgc']['name'],
                'ref' => array(
                    'attribute' => $attribute['id'],
                    'event' => $attribute['event_id'],
                ),
                'span_to' => $to,
            );
        }
        return $out;
    }

    /**
     * What the tab promises and MISP cannot place on any axis.
     *
     * Three kinds, and they are not the same kind of missing. Tags and
     * galaxy clusters have no date column in any schema MISP ships, so
     * no setting and no upgrade would date them. Feed appearances have
     * exactly one timestamp per feed, rewritten on every refresh, which
     * dates the fetch rather than the value — a real timestamp that
     * answers a different question, which is why it is carried as
     * `as_of` and never as `at`.
     *
     * @param array $spec As given to `timeline()`
     * @return array
     */
    private static function timelineUndated(array $spec)
    {
        $out = array();

        $tags = array();
        foreach ($spec['tags'] as $taxonomy) {
            foreach ($taxonomy['tags'] as $tag) {
                $tags[] = array(
                    'label' => $tag['name'],
                    'colour' => $tag['colour'],
                );
            }
        }
        if (!empty($tags)) {
            $out[] = array(
                'kind' => __('Tags'),
                'count' => count($tags),
                'reason' => __(
                    'attribute_tags and event_tags carry no created or'
                    . ' modified column, on any instance. A tag can be'
                    . ' dated only by an audit_logs row.'
                ),
                'chips' => $tags,
                'as_of' => null,
            );
        }

        $clusters = array();
        foreach ($spec['galaxies'] as $galaxy) {
            $clusters[] = array(
                'label' => $galaxy['name'],
                'colour' => null,
            );
        }
        if (!empty($clusters)) {
            $out[] = array(
                'kind' => __('Galaxy clusters'),
                'count' => count($clusters),
                'reason' => __(
                    'Cluster attachments are tags underneath, and'
                    . ' inherit the same missing column.'
                ),
                'chips' => $clusters,
                'as_of' => null,
            );
        }

        $feeds = array();
        foreach ($spec['feeds'] as $feed) {
            $feeds[] = array(
                'label' => $feed['name'],
                'colour' => null,
            );
        }
        if (!empty($feeds)) {
            $out[] = array(
                'kind' => __('Feed appearances'),
                'count' => count($feeds),
                'reason' => __(
                    'The feed cache is a Redis set of hashes with one'
                    . ' timestamp for the whole feed, rewritten on'
                    . ' every refresh. It dates the fetch, not the'
                    . ' value.'
                ),
                'chips' => $feeds,
                'as_of' => isset($spec['feeds_as_of'])
                    ? $spec['feeds_as_of']
                    : null,
            );
        }

        return $out;
    }

    /**
     * `2025-08-12T09:14:00+00:00` to `2025-08-12 09:14:00`.
     *
     * The occurrence rows carry ISO-8601 because that is what MISP
     * stores in `first_seen`; the timeline sorts and bins on plain
     * strings, so it needs the one shape every entry shares.
     *
     * @param string $iso
     * @return string
     */
    private static function plainTime($iso)
    {
        $at = new DateTimeImmutable($iso);
        return $at->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }


    /* ==================================================================
     * History tab
     * ------------------------------------------------------------------
     * One list of audit entries per occurrence, plus one list of the
     * entries that belong to the value's events rather than to any
     * single copy of it. Every number the panel shows — the header, the
     * section counts, the action mixes, the four facet groups — is
     * tallied from those two lists here, so no count on the tab can
     * disagree with the rows underneath it.
     *
     * What a row is filed under is the point of the tab, and it is not
     * always this value: `AuditLogBehavior` files an edit under the
     * title the edit *produced*, so occurrence 4830441's first three
     * entries name the address it held before someone corrected a
     * typo'd octet. They are in scope because the scope is the
     * attribute id. A `model_title` match would have dropped them.
     * ================================================================== */

    /**
     * @param array $spec Keys: recorded, occurrences, groups (attribute
     *                    id => rows, newest first), event_entries,
     *                    total_occurrences, viewer_org, vocab
     * @return array
     */
    private static function history(array $spec)
    {
        $occurrences = $spec['occurrences'];
        $authored = isset($spec['groups']) ? $spec['groups'] : array();
        $eventEntries = isset($spec['event_entries'])
            ? $spec['event_entries']
            : array();

        $groups = array();
        $all = $eventEntries;
        /*
         * One section per occurrence the viewer can open, not one per
         * occurrence that happens to have entries: an occurrence
         * nobody has touched since audit logging came on is a fact
         * about the value, and a missing section would read as one
         * fewer copy of it.
         */
        foreach ($occurrences as $occurrence) {
            $id = (int)$occurrence['Attribute']['id'];
            $entries = isset($authored[$id]) ? $authored[$id] : array();
            $group = array(
                'attribute_id' => $id,
                'event_id' => (int)$occurrence['Attribute']['event_id'],
                'event_info' => $occurrence['Event']['info'],
                'org' => $occurrence['Event']['Orgc']['name'],
                'deleted' => !empty($occurrence['Attribute']['deleted']),
                'count' => count($entries),
                'last' => null,
                'mix' => array(),
                'entries' => array(),
            );
            foreach ($entries as $row) {
                $row['event_id'] = $group['event_id'];
                $row['event_info'] = $group['event_info'];
                $row['attribute_id'] = $id;
                $group['entries'][] = $row;
                $action = $row['action'];
                $group['mix'][$action] = (isset($group['mix'][$action])
                    ? $group['mix'][$action]
                    : 0) + 1;
                if ($group['last'] === null
                    || $row['created'] > $group['last']
                ) {
                    $group['last'] = $row['created'];
                }
            }
            $groups[] = $group;
            $all = array_merge($all, $group['entries']);
        }

        usort($groups, function ($a, $b) {
            return strcmp((string)$b['last'], (string)$a['last']);
        });

        $events = array();
        $first = null;
        $last = null;
        foreach ($all as $row) {
            $events[$row['event_id']] = true;
            if ($first === null || $row['created'] < $first) {
                $first = $row['created'];
            }
            if ($last === null || $row['created'] > $last) {
                $last = $row['created'];
            }
        }
        /*
         * The band's two numbers count the events that *hold* a visible
         * occurrence, not the events that produced an entry. An event
         * of the viewer's own with nothing logged against it is still
         * an event they see every entry on, and counting entries here
         * would make the ACL statement move with the audit log.
         */
        $viewerEvents = array();
        $otherEvents = array();
        foreach ($groups as $group) {
            if ($group['org'] === $spec['viewer_org']) {
                $viewerEvents[$group['event_id']] = true;
            } else {
                $otherEvents[$group['event_id']] = true;
            }
        }

        $visible = count($occurrences);
        return array(
            'recorded' => !empty($spec['recorded']),
            'entries' => count($all),
            'occurrences' => $visible,
            'events' => count($events),
            /*
             * How many occurrences the viewer cannot open. Their entry
             * counts are not obtainable either — the count is itself a
             * fact about an event they may not read — which is why the
             * footer states the number of occurrences and stops.
             */
            'hidden' => $spec['total_occurrences'] - $visible,
            'total_occurrences' => $spec['total_occurrences'],
            'viewer_org' => $spec['viewer_org'],
            'viewer_events' => count($viewerEvents),
            'other_events' => count($otherEvents),
            'first' => $first,
            'last' => $last,
            'facets' => self::auditFacets($all, $spec['vocab']),
            /*
             * Carried through so the panel orders an occurrence's
             * action-mix segments the same way in every section. Two
             * bars a reader is meant to compare have to put the same
             * action in the same place.
             */
            'vocab' => $spec['vocab'],
            'groups' => $groups,
            'event_entries' => $eventEntries,
        );
    }

    /**
     * The four counted facet groups, tallied over every row the panel
     * holds.
     *
     * Action and model are counted against a vocabulary rather than
     * against what turned up, so a zero survives into the rail: *no
     * undelete* and *no object entry* are both things the reader came
     * here to learn, and a row that is simply absent tells them
     * nothing. Organisation and actor have no vocabulary — an
     * organisation that never touched this value is not a fact about
     * it — so those two are what the rows carry, largest first.
     *
     * @param array $entries
     * @param array $vocab Keys: action, model
     * @return array Four lists of `facetRow()` shapes
     */
    private static function auditFacets(array $entries, array $vocab)
    {
        $tallies = array(
            'action' => array_fill_keys($vocab['action'], 0),
            'model' => array_fill_keys($vocab['model'], 0),
            'org' => array(),
            'actor' => array(),
        );
        foreach ($entries as $row) {
            foreach (array('action', 'model', 'org') as $key) {
                $value = $row[$key];
                $tallies[$key][$value] = (isset($tallies[$key][$value])
                    ? $tallies[$key][$value]
                    : 0) + 1;
            }
            $actor = self::auditActor($row);
            $tallies['actor'][$actor] = (isset($tallies['actor'][$actor])
                ? $tallies['actor'][$actor]
                : 0) + 1;
        }
        arsort($tallies['org']);
        arsort($tallies['actor']);

        $out = array();
        foreach ($tallies as $key => $counts) {
            $rows = array();
            foreach ($counts as $value => $count) {
                $rows[] = self::facetRow(
                    $key === 'action'
                        ? AuditActionMeta::label($value)
                        : $value,
                    self::auditSlug((string)$value),
                    $count
                );
            }
            $out[$key] = $rows;
        }
        return $out;
    }

    /**
     * Who a row is attributed to.
     *
     * `AuditLogsController::__applyAuditAcl` hands a non-site-admin the
     * user only where the user is in their own organisation, so
     * everyone else is their organisation and nothing finer. Filed
     * under the organisation rather than under *unknown*, because which
     * organisation acted is the part that survived.
     *
     * @param array $row
     * @return string
     */
    private static function auditActor(array $row)
    {
        if (!empty($row['actor'])) {
            return $row['actor'];
        }
        return sprintf(__('%s (unnamed)'), $row['org']);
    }

    /**
     * @param string $text
     * @return string A facet token: the rail and the rows both use this
     *                one function, so they cannot slug differently.
     */
    private static function auditSlug($text)
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
    }

    /**
     * One audit row, with everything optional defaulted.
     *
     * @param array $spec Keys: created, action, org; optionally model,
     *                    model_id, model_title, actor, request_type,
     *                    change, renamed, subject, note
     * @return array
     */
    private static function auditRow(array $spec)
    {
        $row = array(
            'created' => $spec['created'],
            'action' => $spec['action'],
            'model' => isset($spec['model']) ? $spec['model'] : 'Attribute',
            'model_id' => isset($spec['model_id'])
                ? $spec['model_id']
                : null,
            'model_title' => isset($spec['model_title'])
                ? $spec['model_title']
                : null,
            'actor' => isset($spec['actor']) ? $spec['actor'] : null,
            'org' => $spec['org'],
            'request_type' => isset($spec['request_type'])
                ? $spec['request_type']
                : 0,
            'change' => isset($spec['change']) ? $spec['change'] : null,
            'renamed' => !empty($spec['renamed']),
            /*
             * What the row is about beyond its action: a tag name, a
             * cluster. An edit needs none — the field names in `change`
             * already say what it touched, and writing them twice is
             * how the two come to disagree.
             */
            'subject' => isset($spec['subject']) ? $spec['subject'] : null,
            'note' => isset($spec['note']) ? $spec['note'] : null,
        );
        $row['event_id'] = isset($spec['event_id'])
            ? $spec['event_id']
            : null;
        $row['event_info'] = isset($spec['event_info'])
            ? $spec['event_info']
            : null;
        $row['attribute_id'] = null;
        return $row;
    }

    /**
     * One field of a diff.
     *
     * @param string $field
     * @param string $was Empty string where the field had no value
     * @param string $is
     * @return array
     */
    private static function auditChange($field, $was, $is)
    {
        return array('field' => $field, 'was' => $was, 'is' => $is);
    }

    /**
     * The publication entries, from the same array the Timeline tab
     * draws its publication lane from.
     *
     * `first` and `last` are the only two publications MISP keeps
     * outside the audit log, so where they differ this produces two
     * rows and not the unknown number that actually happened. That is
     * the same reading the Timeline tab gives them, arrived at from the
     * same array rather than from a second copy of it.
     *
     * @param array $events Event id => info, org, first, last
     * @param array $actors Event id => actor, absent where the
     *                      publishing organisation is not the viewer's
     * @return array
     */
    private static function auditPublications(array $events, array $actors)
    {
        $out = array();
        foreach ($events as $id => $event) {
            $dates = $event['first'] === $event['last']
                ? array($event['first'])
                : array($event['first'], $event['last']);
            foreach ($dates as $index => $date) {
                $out[] = self::auditRow(array(
                    'created' => $date,
                    'action' => 'publish',
                    'model' => 'Event',
                    'model_id' => $id,
                    'model_title' => $event['info'],
                    'actor' => isset($actors[$id]) ? $actors[$id] : null,
                    'org' => $event['org'],
                    'event_id' => $id,
                    'event_info' => $event['info'],
                    'note' => count($dates) === 1
                        ? __('Its only publication.')
                        : ($index === 0
                            ? __(
                                'Its first publication. Any between'
                                . ' this and the latest are not'
                                . ' recorded.'
                            )
                            : __(
                                'Its latest publication. Any between'
                                . ' the first and this are not'
                                . ' recorded.'
                            )),
                ));
            }
        }
        usort($out, function ($a, $b) {
            return strcmp($b['created'], $a['created']);
        });
        return $out;
    }

    /**
     * The action and model vocabularies the rail counts against.
     *
     * Ten actions rather than `AuditActionMeta`'s sixteen: the local
     * variants and `publish_sightings` and `instantiate` cannot reach
     * an attribute or its event by any path this page describes, and a
     * rail of six permanent zeroes would bury the three that mean
     * something.
     *
     * @return array
     */
    private static function auditVocab()
    {
        return array(
            'action' => array(
                'add', 'edit', 'tag', 'remove_tag', 'galaxy',
                'remove_galaxy', 'publish', 'soft_delete', 'undelete',
                'delete',
            ),
            'model' => array(
                'Attribute', 'Event', 'Object', 'ShadowAttribute',
            ),
        );
    }

    /**
     * The C2 address's audit record: thirty-eight entries over six
     * visible occurrences and the five events that carry them.
     *
     * Read as a story it is one address that four organisations kept
     * disagreeing about the IDS flag on, one occurrence whose value was
     * a typo for five weeks, and one that was soft-deleted a year ago
     * and never restored.
     *
     * @param array $occurrences The value's visible occurrences
     * @return array
     */
    private static function maliciousHistory(array $occurrences)
    {
        $value = '185.234.219.24';
        $typo = 'cdn-status.top|185.234.219.240';
        $fixed = 'cdn-status.top|185.234.219.24';

        $groups = array(
            4831022 => array(
                self::auditRow(array(
                    'created' => '2025-08-19 21:22:00',
                    'action' => 'edit',
                    'model_id' => 4831022,
                    'model_title' => $value,
                    'actor' => 'alice@circl.lu',
                    'org' => 'CIRCL',
                    'request_type' => 1,
                    'change' => array(
                        self::auditChange('to_ids', '0', '1'),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-08-19 21:20:00',
                    'action' => 'edit',
                    'model_id' => 4831022,
                    'model_title' => $value,
                    'actor' => 'alice@circl.lu',
                    'org' => 'CIRCL',
                    'change' => array(
                        self::auditChange(
                            'comment',
                            'Seen in Emotet malspam',
                            'C2 confirmed by sandbox run'
                        ),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-08-18 14:05:00',
                    'action' => 'tag',
                    'model_id' => 4831022,
                    'model_title' => $value,
                    'subject' => 'tlp:amber',
                    'actor' => 'alice@circl.lu',
                    'org' => 'CIRCL',
                )),
                self::auditRow(array(
                    'created' => '2025-08-18 14:04:00',
                    'action' => 'galaxy',
                    'model_id' => 4831022,
                    'model_title' => $value,
                    'subject' => 'Sofacy',
                    'actor' => 'alice@circl.lu',
                    'org' => 'CIRCL',
                )),
                self::auditRow(array(
                    'created' => '2025-08-15 09:12:00',
                    'action' => 'edit',
                    'model_id' => 4831022,
                    'model_title' => $value,
                    'actor' => 'bob@circl.lu',
                    'org' => 'CIRCL',
                    'change' => array(
                        self::auditChange(
                            'first_seen',
                            '',
                            '2025-08-12T09:14:00+00:00'
                        ),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-08-14 16:40:00',
                    'action' => 'remove_tag',
                    'model_id' => 4831022,
                    'model_title' => $value,
                    'subject' => 'tlp:green',
                    'actor' => 'bob@circl.lu',
                    'org' => 'CIRCL',
                    'note' => __(
                        'Added a minute earlier and taken off again —'
                        . ' the audit log keeps both, and the'
                        . ' occurrence shows neither.'
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-08-14 16:39:00',
                    'action' => 'tag',
                    'model_id' => 4831022,
                    'model_title' => $value,
                    'subject' => 'tlp:green',
                    'actor' => 'bob@circl.lu',
                    'org' => 'CIRCL',
                )),
                self::auditRow(array(
                    'created' => '2025-08-13 11:02:00',
                    'action' => 'edit',
                    'model_id' => 4831022,
                    'model_title' => $value,
                    'actor' => 'alice@circl.lu',
                    'org' => 'CIRCL',
                    'request_type' => 1,
                    'change' => array(
                        self::auditChange(
                            'comment',
                            '',
                            'Seen in Emotet malspam'
                        ),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-08-12 10:28:00',
                    'action' => 'add',
                    'model_id' => 4831022,
                    'model_title' => $value,
                    'actor' => 'alice@circl.lu',
                    'org' => 'CIRCL',
                    'request_type' => 1,
                )),
            ),
            4831577 => array(
                self::auditRow(array(
                    'created' => '2025-07-30 14:00:00',
                    'action' => 'edit',
                    'model_id' => 4831577,
                    'model_title' => $value,
                    'org' => 'CthulhuSPRL.be',
                    'change' => array(
                        self::auditChange('to_ids', '1', '0'),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-07-30 13:58:00',
                    'action' => 'edit',
                    'model_id' => 4831577,
                    'model_title' => $value,
                    'org' => 'CthulhuSPRL.be',
                    'change' => array(
                        self::auditChange(
                            'comment',
                            'Phishing kit C2',
                            'Shared hosting - IDS flag cleared'
                        ),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-07-29 10:22:00',
                    'action' => 'tag',
                    'model_id' => 4831577,
                    'model_title' => $value,
                    'subject' => 'tlp:amber',
                    'org' => 'CthulhuSPRL.be',
                )),
                self::auditRow(array(
                    'created' => '2025-07-29 09:40:00',
                    'action' => 'add',
                    'model' => 'ShadowAttribute',
                    'model_id' => 21877,
                    'model_title' => $value,
                    'actor' => 'erin@circl.lu',
                    'org' => 'CIRCL',
                    'note' => __(
                        'A proposal against this occurrence, by an'
                        . ' organisation that cannot edit it. Still'
                        . ' pending — nothing here changed the'
                        . ' attribute.'
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-07-28 18:44:00',
                    'action' => 'edit',
                    'model_id' => 4831577,
                    'model_title' => $value,
                    'org' => 'CthulhuSPRL.be',
                    'change' => array(
                        self::auditChange(
                            'category',
                            'Network activity',
                            'Payload delivery'
                        ),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-07-27 08:30:00',
                    'action' => 'add',
                    'model_id' => 4831577,
                    'model_title' => $value,
                    'org' => 'CthulhuSPRL.be',
                    'request_type' => 1,
                )),
            ),
            4830441 => array(
                self::auditRow(array(
                    'created' => '2025-06-27 08:00:00',
                    'action' => 'edit',
                    'model_id' => 4830441,
                    'model_title' => $fixed,
                    'actor' => 'carol@circl.lu',
                    'org' => 'CIRCL',
                    'change' => array(
                        self::auditChange(
                            'last_seen',
                            '2025-06-14T00:00:00+00:00',
                            '2025-06-27T08:00:00+00:00'
                        ),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-06-14 11:47:00',
                    'action' => 'edit',
                    'model_id' => 4830441,
                    'model_title' => $fixed,
                    'actor' => 'carol@circl.lu',
                    'org' => 'CIRCL',
                    'renamed' => true,
                    'change' => array(
                        self::auditChange('value', $typo, $fixed),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-06-05 09:20:00',
                    'action' => 'tag',
                    'model_id' => 4830441,
                    'model_title' => $typo,
                    'subject' => 'type:OSINT',
                    'actor' => 'carol@circl.lu',
                    'org' => 'CIRCL',
                )),
                self::auditRow(array(
                    'created' => '2025-06-03 09:35:00',
                    'action' => 'edit',
                    'model_id' => 4830441,
                    'model_title' => $typo,
                    'actor' => 'carol@circl.lu',
                    'org' => 'CIRCL',
                    'change' => array(
                        self::auditChange('to_ids', '0', '1'),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-06-02 08:05:00',
                    'action' => 'add',
                    'model_id' => 4830441,
                    'model_title' => $typo,
                    'actor' => 'carol@circl.lu',
                    'org' => 'CIRCL',
                )),
            ),
            4829903 => array(
                self::auditRow(array(
                    'created' => '2025-06-19 12:00:00',
                    'action' => 'edit',
                    'model_id' => 4829903,
                    'model_title' => $value,
                    'org' => 'Team-CIRCL',
                    'change' => array(
                        self::auditChange('to_ids', '1', '0'),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-06-18 16:20:00',
                    'action' => 'tag',
                    'model_id' => 4829903,
                    'model_title' => $value,
                    'subject' => 'tlp:green',
                    'org' => 'Team-CIRCL',
                )),
                self::auditRow(array(
                    'created' => '2025-06-18 16:10:00',
                    'action' => 'add',
                    'model_id' => 4829903,
                    'model_title' => $value,
                    'org' => 'Team-CIRCL',
                    'request_type' => 1,
                )),
            ),
            4828810 => array(
                self::auditRow(array(
                    'created' => '2025-04-10 00:00:00',
                    'action' => 'edit',
                    'model_id' => 4828810,
                    'model_title' => $value,
                    'org' => 'ORGNAME',
                    'change' => array(
                        self::auditChange('to_ids', '0', '1'),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-04-09 15:32:00',
                    'action' => 'add',
                    'model_id' => 4828810,
                    'model_title' => $value,
                    'org' => 'ORGNAME',
                )),
            ),
            4827334 => array(
                self::auditRow(array(
                    'created' => '2024-10-01 00:00:00',
                    'action' => 'soft_delete',
                    'model_id' => 4827334,
                    'model_title' => $value,
                    'actor' => 'dave@circl.lu',
                    'org' => 'CIRCL',
                    'note' => __(
                        'History from here on rather than current'
                        . ' state. Never restored.'
                    ),
                )),
                self::auditRow(array(
                    'created' => '2024-09-16 09:55:00',
                    'action' => 'tag',
                    'model_id' => 4827334,
                    'model_title' => $value,
                    'subject' => 'tlp:amber',
                    'actor' => 'dave@circl.lu',
                    'org' => 'CIRCL',
                )),
                self::auditRow(array(
                    'created' => '2024-09-14 08:20:00',
                    'action' => 'add',
                    'model_id' => 4827334,
                    'model_title' => $value,
                    'actor' => 'dave@circl.lu',
                    'org' => 'CIRCL',
                )),
            ),
        );

        /*
         * The two event tags, and the publications derived from the
         * array the Timeline tab uses. Both belong to the value's story
         * and to none of its occurrences: repeating six of them inside
         * six sections would turn six entries into thirty-six.
         */
        $eventEntries = array_merge(
            array(
                self::auditRow(array(
                    'created' => '2025-06-27 08:10:00',
                    'action' => 'tag',
                    'model' => 'Event',
                    'model_id' => 1279,
                    'model_title' => 'OSINT - Emotet infrastructure,'
                        . ' June 2025',
                    'subject' => 'type:OSINT',
                    'actor' => 'carol@circl.lu',
                    'org' => 'CIRCL',
                    'event_id' => 1279,
                    'note' => __(
                        'Five minutes before the republication below,'
                        . ' which is what it was for.'
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-06-19 11:58:00',
                    'action' => 'tag',
                    'model' => 'Event',
                    'model_id' => 1272,
                    'model_title' => 'Mass scanning activity against'
                        . ' .lu netblocks',
                    'subject' => 'tlp:green',
                    'org' => 'Team-CIRCL',
                    'event_id' => 1272,
                )),
            ),
            self::auditPublications(
                self::maliciousPublications(),
                array(
                    1284 => 'alice@circl.lu',
                    1279 => 'carol@circl.lu',
                    1251 => 'dave@circl.lu',
                )
            )
        );

        return self::history(array(
            'recorded' => true,
            'occurrences' => $occurrences,
            'groups' => $groups,
            'event_entries' => $eventEntries,
            'total_occurrences' => 10,
            'viewer_org' => 'CIRCL',
            'vocab' => self::auditVocab(),
        ));
    }

    /**
     * The CDN edge's audit record, and the reason this tab pages.
     *
     * One of its five occurrences carries twenty-eight entries on its
     * own — twenty-two of them the same argument about the IDS flag —
     * which is what a value on shared infrastructure looks like when
     * the disagreement is written as a record instead of a score. Two
     * of the five sit on one event, which is the case no per-event
     * audit log can separate.
     *
     * @param array $occurrences The value's visible occurrences
     * @return array
     */
    private static function conflictedHistory(array $occurrences)
    {
        $value = '104.21.34.198';
        $pair = 'secure-login-lu.example|104.21.34.198';
        $old = 'account-verify-lu.example|104.21.34.198';

        $groups = array(
            5102884 => array(
                self::auditRow(array(
                    'created' => '2025-08-23 19:05:00',
                    'action' => 'edit',
                    'model_id' => 5102884,
                    'model_title' => $value,
                    'actor' => 'carol@circl.lu',
                    'org' => 'CIRCL',
                    'change' => array(
                        self::auditChange(
                            'last_seen',
                            '2025-08-22T11:00:00+00:00',
                            '2025-08-23T19:45:00+00:00'
                        ),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-08-20 09:30:00',
                    'action' => 'tag',
                    'model_id' => 5102884,
                    'model_title' => $value,
                    'subject' => 'tlp:green',
                    'actor' => 'carol@circl.lu',
                    'org' => 'CIRCL',
                )),
                self::auditRow(array(
                    'created' => '2025-08-18 07:02:00',
                    'action' => 'edit',
                    'model_id' => 5102884,
                    'model_title' => $value,
                    'actor' => 'erin@circl.lu',
                    'org' => 'CIRCL',
                    'change' => array(
                        self::auditChange('to_ids', '0', '1'),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-08-18 06:45:00',
                    'action' => 'add',
                    'model_id' => 5102884,
                    'model_title' => $value,
                    'actor' => 'erin@circl.lu',
                    'org' => 'CIRCL',
                    'request_type' => 1,
                )),
            ),
            5103011 => array(
                self::auditRow(array(
                    'created' => '2025-08-23 19:06:00',
                    'action' => 'edit',
                    'model_id' => 5103011,
                    'model_title' => $pair,
                    'actor' => 'carol@circl.lu',
                    'org' => 'CIRCL',
                    'change' => array(
                        self::auditChange(
                            'last_seen',
                            '2025-08-22T11:00:00+00:00',
                            '2025-08-23T19:45:00+00:00'
                        ),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-08-18 06:50:00',
                    'action' => 'edit',
                    'model_id' => 5103011,
                    'model_title' => $pair,
                    'actor' => 'erin@circl.lu',
                    'org' => 'CIRCL',
                    'change' => array(
                        self::auditChange('to_ids', '1', '0'),
                    ),
                    'note' => __(
                        'The same analyst set the flag on the other'
                        . ' occurrence of this value on this event five'
                        . ' minutes later.'
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-08-18 06:31:00',
                    'action' => 'add',
                    'model_id' => 5103011,
                    'model_title' => $pair,
                    'actor' => 'erin@circl.lu',
                    'org' => 'CIRCL',
                    'request_type' => 1,
                )),
            ),
            5098720 => array(
                self::auditRow(array(
                    'created' => '2025-07-29 08:12:00',
                    'action' => 'edit',
                    'model_id' => 5098720,
                    'model_title' => $value,
                    'org' => 'CthulhuSPRL.be',
                    'change' => array(
                        self::auditChange(
                            'last_seen',
                            '2025-07-20T09:00:00+00:00',
                            '2025-07-29T08:12:00+00:00'
                        ),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-07-14 10:40:00',
                    'action' => 'galaxy',
                    'model_id' => 5098720,
                    'model_title' => $value,
                    'subject' => 'Gamaredon',
                    'org' => 'CthulhuSPRL.be',
                )),
                self::auditRow(array(
                    'created' => '2025-07-04 08:15:00',
                    'action' => 'tag',
                    'model_id' => 5098720,
                    'model_title' => $value,
                    'subject' => 'tlp:amber',
                    'org' => 'CthulhuSPRL.be',
                )),
                self::auditRow(array(
                    'created' => '2025-07-02 11:20:00',
                    'action' => 'edit',
                    'model_id' => 5098720,
                    'model_title' => $value,
                    'org' => 'CthulhuSPRL.be',
                    'change' => array(
                        self::auditChange(
                            'category',
                            'Network activity',
                            'Payload delivery'
                        ),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-07-02 11:05:00',
                    'action' => 'add',
                    'model_id' => 5098720,
                    'model_title' => $value,
                    'org' => 'CthulhuSPRL.be',
                    'request_type' => 1,
                )),
            ),
            5076193 => array(
                self::auditRow(array(
                    'created' => '2025-05-14 08:00:00',
                    'action' => 'edit',
                    'model_id' => 5076193,
                    'model_title' => $value,
                    'org' => 'ORGNAME',
                    'change' => array(
                        self::auditChange(
                            'comment',
                            'Seen in web logs',
                            'Seen in web logs - likely just the CDN'
                        ),
                    ),
                )),
                self::auditRow(array(
                    'created' => '2025-05-14 07:55:00',
                    'action' => 'add',
                    'model_id' => 5076193,
                    'model_title' => $value,
                    'org' => 'ORGNAME',
                )),
            ),
            5061455 => self::conflictedChurn($old),
        );

        $eventEntries = array_merge(
            array(
                self::auditRow(array(
                    'created' => '2025-08-18 08:00:00',
                    'action' => 'tag',
                    'model' => 'Event',
                    'model_id' => 1402,
                    'model_title' => 'Phishing campaign impersonating'
                        . ' a .lu bank',
                    'subject' => 'tlp:green',
                    'actor' => 'carol@circl.lu',
                    'org' => 'CIRCL',
                    'event_id' => 1402,
                )),
                self::auditRow(array(
                    'created' => '2025-05-14 15:40:00',
                    'action' => 'tag',
                    'model' => 'Event',
                    'model_id' => 1341,
                    'model_title' => 'Suspicious traffic against a'
                        . ' member portal',
                    'subject' => 'tlp:clear',
                    'org' => 'ORGNAME',
                    'event_id' => 1341,
                )),
            ),
            self::auditPublications(
                self::conflictedPublications(),
                array(
                    1402 => 'carol@circl.lu',
                    1309 => 'carol@circl.lu',
                )
            )
        );

        return self::history(array(
            'recorded' => true,
            'occurrences' => $occurrences,
            'groups' => $groups,
            'event_entries' => $eventEntries,
            'total_occurrences' => 9,
            'viewer_org' => 'CIRCL',
            'vocab' => self::auditVocab(),
        ));
    }

    /**
     * Twenty-eight entries on occurrence 5061455, twenty-two of which
     * are `to_ids` set, cleared and set again over the five weeks
     * between the value arriving and the event's last publication.
     *
     * The flips are generated rather than typed out: spelling
     * twenty-two of them says nothing the loop does not, and it is the
     * even count that matters — an odd one would leave the occurrence
     * on a flag the Occurrences tab does not show.
     *
     * @param string $title The value the occurrence held then
     * @return array
     */
    private static function conflictedChurn($title)
    {
        $rows = array(
            self::auditRow(array(
                'created' => '2025-03-11 12:00:00',
                'action' => 'edit',
                'model_id' => 5061455,
                'model_title' => $title,
                'actor' => 'carol@circl.lu',
                'org' => 'CIRCL',
                'change' => array(
                    self::auditChange(
                        'comment',
                        'account-verify-lu.example - CDN?',
                        'account-verify-lu.example'
                    ),
                ),
                'note' => __(
                    'The last word on it. The flag stayed set, and'
                    . ' three organisations still disagree.'
                ),
            )),
            self::auditRow(array(
                'created' => '2025-02-20 09:01:00',
                'action' => 'tag',
                'model_id' => 5061455,
                'model_title' => $title,
                'subject' => 'tlp:green',
                'actor' => 'erin@circl.lu',
                'org' => 'CIRCL',
            )),
            self::auditRow(array(
                'created' => '2025-02-20 09:00:00',
                'action' => 'remove_tag',
                'model_id' => 5061455,
                'model_title' => $title,
                'subject' => 'tlp:clear',
                'actor' => 'erin@circl.lu',
                'org' => 'CIRCL',
            )),
            self::auditRow(array(
                'created' => '2025-02-04 10:00:00',
                'action' => 'galaxy',
                'model_id' => 5061455,
                'model_title' => $title,
                'subject' => 'T1583.003',
                'actor' => 'carol@circl.lu',
                'org' => 'CIRCL',
            )),
            self::auditRow(array(
                'created' => '2025-02-03 08:45:00',
                'action' => 'tag',
                'model_id' => 5061455,
                'model_title' => $title,
                'subject' => 'tlp:clear',
                'actor' => 'carol@circl.lu',
                'org' => 'CIRCL',
            )),
            self::auditRow(array(
                'created' => '2025-02-03 08:40:00',
                'action' => 'add',
                'model_id' => 5061455,
                'model_title' => $title,
                'actor' => 'carol@circl.lu',
                'org' => 'CIRCL',
                'request_type' => 1,
            )),
        );

        $actors = array(
            'carol@circl.lu', 'erin@circl.lu', 'frank@circl.lu',
        );
        $start = new DateTimeImmutable('2025-02-06 09:00:00');
        for ($i = 0; $i < 22; $i++) {
            $to = $i % 2 === 0 ? '0' : '1';
            $rows[] = self::auditRow(array(
                'created' => $start
                    ->add(new DateInterval('PT' . ($i * 2160) . 'M'))
                    ->format('Y-m-d H:i:s'),
                'action' => 'edit',
                'model_id' => 5061455,
                'model_title' => $title,
                'actor' => $actors[$i % 3],
                'org' => 'CIRCL',
                'request_type' => $i % 4 === 0 ? 1 : 0,
                'change' => array(
                    self::auditChange(
                        'to_ids',
                        $to === '0' ? '1' : '0',
                        $to
                    ),
                ),
            ));
        }

        usort($rows, function ($a, $b) {
            return strcmp($b['created'], $a['created']);
        });
        return $rows;
    }

    /**
     * The resolver's audit record, which is empty.
     *
     * `MISP.log_new_audit` was turned on after everything that has ever
     * happened to this value, so the log works and has nothing to say
     * about it. That is a different claim from the log not running, and
     * §9's two states are worded to keep them apart.
     *
     * @param array $occurrences
     * @return array
     */
    private static function benignHistory(array $occurrences)
    {
        return self::history(array(
            'recorded' => true,
            'occurrences' => $occurrences,
            'groups' => array(),
            'event_entries' => array(),
            'total_occurrences' => 9,
            'viewer_org' => 'CIRCL',
            'vocab' => self::auditVocab(),
        ));
    }

    /* ==================================================================
     * The fourth demo value — occurrence scale
     * ------------------------------------------------------------------
     * `45.155.205.233` exists because of what phase 17 §9.2 found: the
     * first three demo values carry 10, 9 and 9 occurrences, so every
     * panel that scales on the occurrence count was designed and
     * verified against a value that never stressed it. This one carries
     * 812, and it is over-correlating, so the Relationships tab's
     * correlation band says nothing was stored and the object-siblings
     * section is the only content left on it.
     *
     * Almost nothing here is typed in row by row. The occurrences are
     * generated from an explicit plan — 601 `ip-dst`, 402 at
     * distribution 3, 683 inside an object — and every count the page
     * states about them is tallied back from the rows that were
     * generated, so the plan and the page cannot drift apart.
     *
     * The permutations are index arithmetic on multipliers coprime with
     * 748 rather than anything random: the fixture has to render the
     * same page twice.
     * ================================================================== */

    /**
     * The 23 organisations that have reported this address.
     *
     * Index plus one is the `Orgc.id`, which is what the facet rail
     * matches an organisation on.
     *
     * @return array
     */
    private static function fluxOrgs()
    {
        return array(
            'CIRCL', 'CthulhuSPRL.be', 'Team-CIRCL', 'ORGNAME',
            'Botvrij.eu', 'CERT-EU', 'NCSC-NL', 'CERT.PL', 'CERT-SE',
            'CIRCL-Sandbox', 'ANSSI', 'CERT-Bund', 'NASK', 'SK-CERT',
            'CERT-LV', 'CSIRT-IE', 'CERT-EE', 'CIRCL-Partners',
            'GOVCERT.LU', 'CERT-RO', 'CERT-GR', 'CERT-SI', 'CERT-MT',
        );
    }

    /**
     * The tags an occurrence of this value can carry, in MISP's
     * `AttributeTag` shape.
     *
     * @return array
     */
    private static function fluxTagPool()
    {
        return array(
            'amber' => array(
                'local' => 0,
                'Tag' => array(
                    'name' => 'tlp:amber',
                    'colour' => '#FFC000',
                    'is_galaxy' => false,
                ),
            ),
            'green' => array(
                'local' => 0,
                'Tag' => array(
                    'name' => 'tlp:green',
                    'colour' => '#33FF00',
                    'is_galaxy' => false,
                ),
            ),
            'osint' => array(
                'local' => 0,
                'Tag' => array(
                    'name' => 'type:OSINT',
                    'colour' => '#004646',
                    'is_galaxy' => false,
                ),
            ),
            'pap' => array(
                'local' => 0,
                'Tag' => array(
                    'name' => 'PAP:AMBER',
                    'colour' => '#FFC000',
                    'is_galaxy' => false,
                ),
            ),
            'qakbot' => array(
                'local' => 0,
                'Tag' => array(
                    'name' => 'misp-galaxy:malware="QakBot"',
                    'colour' => '#8B5CF6',
                    'is_galaxy' => true,
                ),
            ),
            'reviewed' => array(
                'local' => 1,
                'Tag' => array(
                    'name' => 'workflow:state="reviewed"',
                    'colour' => '#3F51B5',
                    'is_galaxy' => false,
                ),
            ),
        );
    }

    /**
     * The 748 occurrences the viewer can open, of 812 the value has.
     *
     * Shaped exactly like `maliciousOccurrences()` — a
     * `fetchAttributes` result — so the Occurrences tab, the Timeline's
     * edit lane and the History tab read it without knowing it was
     * generated.
     *
     * The plan, and it is the plan the rest of the page states:
     *
     *   type          601 ip-dst · 118 ip-src · 29 domain|ip
     *   category      664 Network activity · 71 Payload delivery
     *                 · 13 External analysis
     *   to_ids        592 set · 156 unset
     *   distribution  402 all · 96 connected · 171 community
     *                 · 52 sharing group · 27 own org
     *   objects       683 in one — 397 domain-ip, 229
     *                 network-connection, 57 ip-port — and 65 bare
     *   deleted       11
     *
     * @return array
     */
    private static function fluxOccurrences()
    {
        $orgs = self::fluxOrgs();
        $tags = self::fluxTagPool();
        $titles = array(
            'Fast-flux C2 rotation, batch %d',
            'QakBot distribution infrastructure, week %d',
            'Bulletproof hosting sweep %d',
            'OSINT - C2 panel harvest %d',
            'Member-reported beaconing, case %d',
            'Sinkhole telemetry export %d',
            'Phishing kit callback set %d',
            'Botnet tracker snapshot %d',
        );
        $comments = array(
            'Beaconing every 300s, TLS on 8443',
            'Same self-signed certificate as the June set',
            'Seen in a QakBot config dump',
            'Reported by a member, not independently confirmed',
            'Panel answered until the provider pulled the range',
        );

        $n = 748;
        $events = 137;
        $from = '2024-06-11';
        $span = self::dayDiff($from, '2025-08-23');
        $rows = array();

        /*
         * Type is assigned up front rather than inside the loop,
         * because two of the plan's rules constrain each other: a
         * `domain|ip` composite has no object slot to sit in, so the 29
         * composites have to come out of the 65 occurrences that are
         * not in an object. Deciding type independently and correcting
         * it afterwards is what makes 29 collapse to 2.
         */
        $types = array_fill(0, $n, 'ip-dst');
        $bare = array();
        for ($k = 0; $k < $n; $k++) {
            if ((($k * 401) % $n) >= 683) {
                $bare[] = $k;
            }
        }
        foreach (array_slice($bare, 0, 29) as $k) {
            $types[$k] = 'domain|ip';
        }
        $sources = 0;
        for ($k = 0; $k < $n && $sources < 118; $k++) {
            $slot = ($k * 313) % $n;
            if ($types[$slot] === 'ip-dst') {
                $types[$slot] = 'ip-src';
                $sources++;
            }
        }

        for ($i = 0; $i < $n; $i++) {
            /*
             * Newest first, and the event index descends with it, so
             * the higher event id is the more recent one — which is
             * what a reader assumes of an id MISP hands out in order.
             */
            $e = $events - 1 - intdiv($i * $events, $n);
            $eventId = 1500 + $e;
            $orgIndex = $e % count($orgs);
            $day = self::addDays($from, $span - intdiv($i * $span, $n - 1));

            $catSlot = ($i * 97) % $n;
            $idsSlot = ($i * 587) % $n;
            $distSlot = ($i * 199) % $n;
            $objSlot = ($i * 401) % $n;
            $delSlot = ($i * 461) % $n;
            $tagSlot = ($i * 137) % $n;

            $type = $types[$i];

            if ($catSlot < 664) {
                $category = 'Network activity';
            } elseif ($catSlot < 735) {
                $category = 'Payload delivery';
            } else {
                $category = 'External analysis';
            }

            if ($distSlot < 402) {
                $distribution = 3;
            } elseif ($distSlot < 498) {
                $distribution = 2;
            } elseif ($distSlot < 669) {
                $distribution = 1;
            } elseif ($distSlot < 721) {
                $distribution = 4;
            } else {
                $distribution = 0;
            }

            $objectId = 0;
            $template = null;
            $relation = null;
            if ($objSlot < 683) {
                if ($objSlot < 397) {
                    $template = 'domain-ip';
                    $relation = 'ip';
                } elseif ($objSlot < 626) {
                    $template = 'network-connection';
                    $relation = 'ip-dst';
                } else {
                    $template = 'ip-port';
                    $relation = 'ip';
                }
                $objectId = 92000 + $objSlot;
            }

            $unset = ($i % 37) === 0;
            $clock = sprintf('%02d:%02d:00+00:00', ($i * 7) % 24,
                ($i * 13) % 60);
            $firstSeen = $unset ? null : $day . 'T' . $clock;
            $lastSeen = (!$unset && ($i % 5) !== 0)
                ? self::addDays($day, 1 + ($i % 9)) . 'T' . $clock
                : null;

            $attributeTags = array();
            if ($tagSlot < 431) {
                $attributeTags[] = $tags['amber'];
            } elseif ($tagSlot < 578) {
                $attributeTags[] = $tags['green'];
            }
            if (($i % 3) === 0) {
                $attributeTags[] = $tags['osint'];
            }
            if (($i % 5) === 0) {
                $attributeTags[] = $tags['pap'];
            }
            if (($i % 7) === 0) {
                $attributeTags[] = $tags['qakbot'];
            }
            if (($i % 23) === 0) {
                $attributeTags[] = $tags['reviewed'];
            }

            $row = array(
                'Attribute' => array(
                    'id' => 5200000 + $i * 7,
                    'uuid' => sprintf(
                        '%08x-%04x-4%03x-8%03x-%012x',
                        3400000 + $i * 7919,
                        ($i * 31) % 0xffff,
                        $i % 0xfff,
                        ($i * 17) % 0xfff,
                        5200000000 + $i * 104729
                    ),
                    'event_id' => $eventId,
                    'object_id' => $objectId,
                    'type' => $type,
                    'category' => $category,
                    'to_ids' => $idsSlot < 592 ? 1 : 0,
                    'distribution' => $distribution,
                    'sharing_group_id' => $distribution === 4 ? 7 : 0,
                    'comment' => ($i % 6) === 0
                        ? $comments[intdiv($i, 6) % count($comments)]
                        : '',
                    'first_seen' => $firstSeen,
                    'last_seen' => $lastSeen,
                    'timestamp' => self::plainStamp($day),
                    'deleted' => $delSlot < 11 ? 1 : 0,
                    'object_relation' => $relation,
                ),
                'Event' => array(
                    'id' => $eventId,
                    'info' => sprintf(
                        $titles[$e % count($titles)],
                        1 + intdiv($e, count($titles))
                    ),
                    'published' => ($e % 9) === 0 ? 0 : 1,
                    'orgc_id' => $orgIndex + 1,
                    'user_id' => 3 + ($e % 17),
                    'Orgc' => array(
                        'id' => $orgIndex + 1,
                        'name' => $orgs[$orgIndex],
                    ),
                ),
                'Object' => array('id' => $objectId ?: null,
                    'name' => $template),
                'SharingGroup' => $distribution === 4
                    ? array('id' => 7, 'name' => 'CIRCL private sector')
                    : array('id' => null, 'name' => null),
                'AttributeTag' => $attributeTags,
            );

            // A handful carry a pending proposal, so the `state` facet
            // group has something to narrow on at this scale too.
            if (($i % 149) === 0) {
                $row['proposal_count'] = 1;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * A `Y-m-d` at midday UTC, as an integer timestamp.
     *
     * Midday rather than midnight so a timezone the reader's browser
     * applies cannot move the day.
     *
     * @param string $day
     * @return int
     */
    private static function plainStamp($day)
    {
        $d = new DateTimeImmutable(
            $day . ' 12:00:00',
            new DateTimeZone('UTC')
        );
        return (int)$d->format('U');
    }

    /**
     * Every count the Occurrences tab states, tallied from the rows.
     *
     * Nothing here is a second copy of the plan in `fluxOccurrences()`:
     * a tally that disagreed with the rows would be a tally this method
     * could not produce.
     *
     * @param array $occurrences
     * @return array
     */
    private static function fluxTally(array $occurrences)
    {
        $tally = array(
            'type' => array(),
            'category' => array(),
            'ids' => array('set' => 0, 'unset' => 0),
            'distribution' => array(),
            'organisation' => array(),
            'sharing_group' => array(),
            'tag' => array(),
            'state' => array('proposal' => 0),
            'orgs' => array(),
            'events' => array(),
            'published' => array(),
            'objects' => array(),
            'templates' => array(),
            'deleted' => 0,
            'seen_unset' => 0,
        );

        foreach ($occurrences as $row) {
            $attribute = $row['Attribute'];
            $event = $row['Event'];
            $type = $attribute['type'];
            $category = $attribute['category'];
            $level = (int)$attribute['distribution'];
            $orgId = (int)$event['Orgc']['id'];

            $tally['type'][$type] = 1 + (isset($tally['type'][$type])
                ? $tally['type'][$type] : 0);
            $tally['category'][$category] = 1
                + (isset($tally['category'][$category])
                    ? $tally['category'][$category] : 0);
            $tally['ids'][empty($attribute['to_ids'])
                ? 'unset' : 'set']++;
            $tally['distribution'][$level] = 1
                + (isset($tally['distribution'][$level])
                    ? $tally['distribution'][$level] : 0);
            $tally['organisation'][$orgId] = 1
                + (isset($tally['organisation'][$orgId])
                    ? $tally['organisation'][$orgId] : 0);
            $tally['orgs'][$event['Orgc']['name']] = 1
                + (isset($tally['orgs'][$event['Orgc']['name']])
                    ? $tally['orgs'][$event['Orgc']['name']] : 0);
            $tally['events'][$event['id']] = true;
            if (!empty($event['published'])) {
                $tally['published'][$event['id']] = true;
            }
            if ($level === 4 && !empty($row['SharingGroup']['id'])) {
                $sg = (int)$row['SharingGroup']['id'];
                $tally['sharing_group'][$sg] = 1
                    + (isset($tally['sharing_group'][$sg])
                        ? $tally['sharing_group'][$sg] : 0);
            }
            foreach ($row['AttributeTag'] as $attributeTag) {
                // Galaxy tags are not a facet: the column does not
                // draw them, and a filter on something invisible is
                // not a filter.
                if (!empty($attributeTag['Tag']['is_galaxy'])) {
                    continue;
                }
                $name = $attributeTag['Tag']['name'];
                if (!isset($tally['tag'][$name])) {
                    $tally['tag'][$name] = array(
                        'colour' => $attributeTag['Tag']['colour'],
                        'local' => (int)$attributeTag['local'],
                        'count' => 0,
                    );
                }
                $tally['tag'][$name]['count']++;
            }
            if (!empty($row['proposal_count'])) {
                $tally['state']['proposal']++;
            }
            if (!empty($attribute['deleted'])) {
                $tally['deleted']++;
            }
            if (empty($attribute['first_seen'])
                && empty($attribute['last_seen'])
            ) {
                $tally['seen_unset']++;
            }
            if (!empty($attribute['object_id'])) {
                $tally['objects'][$attribute['object_id']] = true;
                $name = $row['Object']['name'];
                $tally['templates'][$name] = 1
                    + (isset($tally['templates'][$name])
                        ? $tally['templates'][$name] : 0);
            }
        }

        arsort($tally['type']);
        arsort($tally['category']);
        arsort($tally['distribution']);
        arsort($tally['organisation']);
        arsort($tally['orgs']);
        arsort($tally['templates']);
        uasort($tally['tag'], function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        return $tally;
    }

    /**
     * Forty buckets across the value's seen span, each counting the
     * occurrences whose first/last-seen interval covers it.
     *
     * @param array $occurrences
     * @param string $from
     * @param string $to
     * @return array
     */
    private static function fluxSeenSpark(array $occurrences, $from, $to)
    {
        $buckets = array_fill(0, 40, 0);
        $span = max(1, self::dayDiff($from, $to));
        foreach ($occurrences as $row) {
            $first = $row['Attribute']['first_seen'];
            $last = $row['Attribute']['last_seen'];
            if (empty($first) && empty($last)) {
                continue;
            }
            $start = substr($first ?: $last, 0, 10);
            $end = substr($last ?: $first, 0, 10);
            $a = (int)floor(self::dayDiff($from, $start) * 40 / $span);
            $b = (int)floor(self::dayDiff($from, $end) * 40 / $span);
            for ($k = max(0, $a); $k <= min(39, $b); $k++) {
                $buckets[$k]++;
            }
        }
        return $buckets;
    }

    /**
     * The facet rail, built from the tally.
     *
     * `visible` is the occurrences the viewer can open, which at this
     * scale is also the number of rows the table holds — the rail's own
     * note promises the counts cover them, and it has to be able to
     * keep that promise.
     *
     * @param array $occurrences
     * @param array $tally
     * @return array
     */
    private static function fluxOccurrenceFacets(
        array $occurrences,
        array $tally
    ) {
        $orgs = self::fluxOrgs();

        $organisation = array();
        foreach ($tally['organisation'] as $id => $count) {
            $organisation[] = self::facetRow(
                $orgs[$id - 1],
                (string)$id,
                $count
            );
        }

        $type = array();
        foreach ($tally['type'] as $name => $count) {
            $type[] = self::facetRow(
                $name,
                trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)),
                    '-'),
                $count
            );
        }

        $category = array();
        foreach ($tally['category'] as $name => $count) {
            $category[] = self::facetRow(
                $name,
                trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)),
                    '-'),
                $count
            );
        }

        $distribution = array();
        foreach ($tally['distribution'] as $level => $count) {
            $distribution[] = self::distributionFacet($level, $count);
        }

        $sharingGroup = array();
        foreach ($tally['sharing_group'] as $id => $count) {
            $sharingGroup[] = self::facetRow(
                'CIRCL private sector',
                (string)$id,
                $count
            );
        }

        $tag = array();
        foreach ($tally['tag'] as $name => $row) {
            $tag[] = self::tagFacet(
                $name,
                $row['colour'],
                $row['local'],
                trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)),
                    '-'),
                $row['count']
            );
        }

        $state = array();
        if (!empty($tally['state']['proposal'])) {
            $state[] = self::facetRow(
                __('With a pending proposal'),
                'proposal',
                $tally['state']['proposal']
            );
        }

        return array(
            'visible' => count($occurrences),
            'total' => 812,
            'groups' => array(
                'organisation' => $organisation,
                'type' => $type,
                'category' => $category,
                'ids' => array(
                    self::facetRow(__('to_ids set'), 'set',
                        $tally['ids']['set']),
                    self::facetRow(__('to_ids unset'), 'unset',
                        $tally['ids']['unset']),
                ),
                'distribution' => $distribution,
                'sharing_group' => $sharingGroup,
                'tag' => $tag,
                'state' => $state,
            ),
            'seen_spark' => self::fluxSeenSpark(
                $occurrences,
                '2024-06-11',
                '2025-08-23'
            ),
            'seen_from' => '2024-06-11',
            'seen_to' => '2025-08-23',
            'seen_unset' => $tally['seen_unset'],
            'deleted' => $tally['deleted'],
            'banner_note' => array(
                'chip' => 'ip-dst',
                'banner' => 649,
                'rail' => isset($tally['type']['ip-dst'])
                    ? $tally['type']['ip-dst']
                    : 0,
            ),
        );
    }

    /**
     * Tags aggregated across every occurrence, grouped by taxonomy.
     *
     * @param array $tally
     * @return array
     */
    private static function fluxTags(array $tally)
    {
        $count = function ($name) use ($tally) {
            return isset($tally['tag'][$name])
                ? $tally['tag'][$name]['count']
                : 0;
        };

        return array(
            array(
                'taxonomy' => 'tlp',
                /*
                 * A conflict at 23 reporters, and still a conflict: the
                 * panel's job is to say the two readings are both in
                 * use, not to decide that at this scale it stops
                 * mattering.
                 */
                'conflict' => true,
                'tags' => array(
                    array(
                        'name' => 'tlp:amber',
                        'colour' => '#FFC000',
                        'count' => $count('tlp:amber'),
                        'local' => false,
                        'orgs' => array('CIRCL', 'CthulhuSPRL.be',
                            'CERT-EU'),
                    ),
                    array(
                        'name' => 'tlp:green',
                        'colour' => '#33FF00',
                        'count' => $count('tlp:green'),
                        'local' => false,
                        'orgs' => array('Team-CIRCL', 'Botvrij.eu'),
                    ),
                ),
            ),
            array(
                'taxonomy' => 'pap',
                'conflict' => false,
                'tags' => array(
                    array(
                        'name' => 'PAP:AMBER',
                        'colour' => '#FFC000',
                        'count' => $count('PAP:AMBER'),
                        'local' => false,
                        'orgs' => array('CIRCL'),
                    ),
                ),
            ),
            array(
                'taxonomy' => 'type',
                'conflict' => false,
                'tags' => array(
                    array(
                        'name' => 'type:OSINT',
                        'colour' => '#004646',
                        'count' => $count('type:OSINT'),
                        'local' => false,
                        'orgs' => array('CIRCL', 'Botvrij.eu'),
                    ),
                ),
            ),
            array(
                'taxonomy' => 'workflow',
                'conflict' => false,
                'tags' => array(
                    array(
                        'name' => 'workflow:state="reviewed"',
                        'colour' => '#3F51B5',
                        'count' => $count('workflow:state="reviewed"'),
                        'local' => true,
                        'orgs' => array('CIRCL'),
                    ),
                ),
            ),
        );
    }

    /**
     * 418 sightings over the fourteen months, skewed towards the four
     * organisations that watch this range closely and thinning out
     * across the other nineteen.
     *
     * Nine false positives and one expiration, so all three type
     * toggles have something to toggle.
     *
     * @return array
     */
    private static function fluxSightingRows()
    {
        $orgs = self::fluxOrgs();
        $n = 418;
        $from = '2024-06-11';
        $span = self::dayDiff($from, '2025-08-23');
        $falsePositives = array(37, 84, 121, 168, 203, 259, 301, 355, 394);
        $expiration = 276;
        $types = array('ip-dst', 'ip-dst', 'ip-dst', 'ip-src', 'ip-dst',
            'domain|ip', 'ip-dst', 'ip-src');
        $table = array();

        for ($j = 0; $j < $n; $j++) {
            $day = self::addDays($from, intdiv($j * $span, $n - 1));
            $weight = ($j * 17) % 100;
            if ($weight < 26) {
                $org = $orgs[0];
            } elseif ($weight < 45) {
                $org = $orgs[1];
            } elseif ($weight < 60) {
                $org = $orgs[2];
            } elseif ($weight < 72) {
                $org = $orgs[3];
            } else {
                $org = $orgs[4 + ($j % 19)];
            }

            if (in_array($j, $falsePositives, true)) {
                $type = self::FALSE_POSITIVE;
            } elseif ($j === $expiration) {
                $type = self::EXPIRATION;
            } else {
                $type = self::SIGHTING;
            }

            $table[] = array(
                $day . sprintf(' %02d:%02d', ($j * 11) % 24, ($j * 29) % 60),
                $org,
                $type,
                1500 + intdiv($j * 137, $n),
                $types[$j % count($types)],
                ($j % 61) === 0 ? 'sightingdb' : null,
            );
        }

        return self::sightingRows($table);
    }

    /**
     * @return array
     */
    private static function fluxModels()
    {
        return array(
            array(
                'model' => 'NIDS Simple Decaying Model',
                'base' => 93,
                'lifetime' => 30,
                'decay_rate' => 0.3,
                'threshold' => 60,
            ),
            array(
                'model' => 'Phishing Model',
                'base' => 71,
                'lifetime' => 21,
                'decay_rate' => 0.3,
                'threshold' => 50,
            ),
        );
    }

    /**
     * Who reported it and who has seen it, per organisation, tallied
     * from the rows rather than restated beside them.
     *
     * @param array $tally
     * @param array $sightings
     * @return array
     */
    private static function fluxVerdictOrgs(array $tally, array $sightings)
    {
        $seen = array();
        $fp = array();
        foreach ($sightings as $row) {
            $org = $row['org'];
            $seen[$org] = 1 + (isset($seen[$org]) ? $seen[$org] : 0);
            if ($row['type'] === self::FALSE_POSITIVE) {
                $fp[$org] = 1 + (isset($fp[$org]) ? $fp[$org] : 0);
            }
        }

        /*
         * Opinion, to_ids stance and reliability are editorial and are
         * not derivable from a row count, so they are given for the
         * four organisations the summary names and defaulted for the
         * nineteen it does not.
         */
        $stance = array(
            'CIRCL' => array(90, 'yes', 'B'),
            'CthulhuSPRL.be' => array(85, 'yes', 'B'),
            'Team-CIRCL' => array(80, 'mixed', 'B'),
            'ORGNAME' => array(45, 'no', 'D'),
        );

        $rows = array();
        foreach ($tally['orgs'] as $org => $occurrences) {
            $own = isset($stance[$org])
                ? $stance[$org]
                : array(70, 'yes', 'C');
            $rows[] = array(
                'org' => $org,
                'occurrences' => $occurrences,
                'sightings' => isset($seen[$org]) ? $seen[$org] : 0,
                'fp' => isset($fp[$org]) ? $fp[$org] : 0,
                'opinion' => $own[0],
                'to_ids' => __($own[1]),
                'reliability' => $own[2],
            );
        }

        return $rows;
    }

    /**
     * The object-siblings section at the scale phase 18 exists for.
     *
     * The listed rows are the ranked prefix; the totals are the real
     * ones. The arithmetic, so that a later reader can check it rather
     * than take it:
     *
     *   683 objects hold the value, the join reads the most recent 500
     *   500 objects hold 926 sibling attributes:
     *     domain-ip           291 × domain, 96 × first-seen
     *     network-connection  168 × dst-port, 168 × layer4-protocol,
     *                         121 × hostname
     *     ip-port             41 × dst-port, 41 × first-seen
     *   those 926 collapse to 494 distinct triples, of which 32 are
     *   listed and account for 459 of the 926
     *
     * Two shapes of "too many" in one table, which is the whole reason
     * one aggregation serves both: `layer4-protocol tcp` is 168 objects
     * carrying one fact and collapses to a single row, while the flux
     * domains are 247 distinct facts and rank and page.
     *
     * @return array
     */
    private static function fluxSiblings()
    {
        $everyone = array('CIRCL', 'CthulhuSPRL.be');
        $nc = 'network-connection';

        return self::relSiblingGroups(
            array(
                array($nc, 'layer4-protocol', 'tcp', 'text', 168, 61,
                    $everyone, 19),
                array($nc, 'dst-port', '8443', 'port', 138, 52,
                    $everyone, 17),
                array('ip-port', 'dst-port', '8443', 'port', 33, 19,
                    array('CIRCL', 'CERT-EU'), 9),
                array($nc, 'dst-port', '443', 'port', 19, 14,
                    array('Team-CIRCL', 'NCSC-NL'), 7),
                array($nc, 'dst-port', '9001', 'port', 11, 8,
                    array('CIRCL', 'CERT.PL'), 5),
                array('domain-ip', 'domain', 'cdn-edge-7.flux-a.top',
                    'domain', 9, 7, $everyone, 4),
                array('ip-port', 'dst-port', '8080', 'port', 8, 6,
                    array('Botvrij.eu', 'CERT-SE'), 4),
                array('domain-ip', 'domain', 'update-check.flux-a.top',
                    'domain', 7, 6, array('CIRCL', 'CERT-EU'), 3),
                array($nc, 'hostname', 'panel.flux-a.top', 'hostname',
                    6, 5, $everyone, 3),
                array('domain-ip', 'domain', 'stat-collect.flux-b.top',
                    'domain', 5, 4, array('CthulhuSPRL.be'), 2),
                array('domain-ip', 'domain', 'img-cache.flux-b.top',
                    'domain', 4, 4, array('CIRCL'), 2),
                array('domain-ip', 'domain', 'ocsp-relay.flux-c.top',
                    'domain', 4, 3, array('NASK', 'CERT-PL'), 2),
                array($nc, 'hostname', 'c2-b.flux-a.top', 'hostname',
                    4, 3, array('Team-CIRCL'), 2),
                array('domain-ip', 'domain', 'push-api.flux-c.top',
                    'domain', 3, 3, array('CIRCL'), 1),
                array('domain-ip', 'domain', 'ntp-sync.flux-c.top',
                    'domain', 3, 2, array('ANSSI'), 1),
                array('domain-ip', 'domain', 'font-cdn.flux-d.top',
                    'domain', 3, 2, array('CERT-Bund'), 1),
                array($nc, 'hostname', 'gate.flux-b.top', 'hostname',
                    3, 3, array('CIRCL'), 1),
                array($nc, 'hostname', 'relay.flux-c.top', 'hostname',
                    3, 2, array('GOVCERT.LU'), 1),
                array('domain-ip', 'domain', 'api-v2.flux-d.top',
                    'domain', 2, 2, array('CIRCL'), 1),
                array('domain-ip', 'domain', 'assets.flux-d.top',
                    'domain', 2, 2, array('CERT-EE'), 1),
                array('domain-ip', 'domain', 'beacon.flux-e.top',
                    'domain', 2, 1, array('SK-CERT'), 1),
                array('domain-ip', 'domain', 'check-in.flux-e.top',
                    'domain', 2, 2, array('CERT-LV'), 1),
                array('domain-ip', 'domain', 'dl-stage.flux-e.top',
                    'domain', 2, 2, array('CSIRT-IE'), 1),
                array('domain-ip', 'domain', 'health.flux-f.top',
                    'domain', 2, 1, array('CERT-RO'), 1),
                array('domain-ip', 'domain', 'log-drop.flux-f.top',
                    'domain', 2, 2, array('CERT-GR'), 1),
                array('domain-ip', 'domain', 'mirror.flux-f.top',
                    'domain', 2, 2, array('CERT-SI'), 1),
                array('domain-ip', 'domain', 'node-14.flux-g.top',
                    'domain', 2, 1, array('CERT-MT'), 1),
                array('domain-ip', 'domain', 'ping.flux-g.top',
                    'domain', 2, 2, array('CIRCL-Partners'), 1),
                array($nc, 'hostname', 'edge-1.flux-d.top', 'hostname',
                    2, 2, array('CIRCL'), 1),
                array($nc, 'hostname', 'edge-2.flux-d.top', 'hostname',
                    2, 1, array('CERT-EU'), 1),
                array($nc, 'hostname', 'sink.flux-e.top', 'hostname',
                    2, 2, array('NCSC-NL'), 1),
                array($nc, 'hostname', 'tunnel.flux-f.top', 'hostname',
                    2, 2, array('Botvrij.eu'), 1),
            ),
            array(
                'total' => 494,
                'raw' => 926,
                'objects' => 500,
                'in_objects' => 683,
                /*
                 * Attributes inside an object the viewer can open whose
                 * own distribution still keeps them out. Countable,
                 * unlike the occurrences behind an unreadable event, so
                 * it is counted.
                 */
                'hidden' => 57,
                'page_size' => 8,
            )
        );
    }

    /**
     * The Relationships tab for a value the engine gave up on.
     *
     * `over_correlating` is true, so section one stores nothing and
     * renders the suppressed band: the siblings section above it is the
     * only content on the tab, which is exactly the case phase 17 §9.3
     * said had to be looked at rather than inferred.
     *
     * @param array $tally
     * @return array
     */
    private static function fluxRelationships(array $tally)
    {
        return array(
            'summary' => array(
                'correlations' => 0,
                'recorded' => 812,
                'near' => 4,
                'asserted' => 3,
                'siblings' => 494,
                'cooccurrence' => 0,
            ),
            'cooccurrence' => array(
                'suppressed' => true,
                'stored' => 0,
                'visible' => 0,
                'hidden' => 0,
                'distinct_values' => 0,
                'events' => 0,
                'page_size' => 8,
                'siblings' => self::fluxSiblings(),
                'rollups' => array(
                    'value' => array('total' => 0, 'rows' => array()),
                    'event' => array('total' => 0, 'rows' => array()),
                    'object' => array('total' => 0, 'rows' => array()),
                ),
                'facets' => null,
                'categories' => array(),
            ),
            'near' => array(
                'matches' => 4,
                'engines_active' => 1,
                'engines_idle' => 2,
                'engines' => array(
                    array(
                        'id' => 'cidr',
                        'state' => 'active',
                        'rows' => self::relCidrRows(array(
                            array('45.155.205.0/24', 24, 1636,
                                'CIRCL', 3),
                            array('45.155.204.0/22', 22, 1621,
                                'CERT-EU', 3),
                            array('45.155.192.0/19', 19, 1588,
                                'NCSC-NL', 1),
                            array('45.155.0.0/16', 16, 1547,
                                'ORGNAME', 1),
                        )),
                    ),
                    array(
                        'id' => 'ssdeep',
                        'state' => 'not_applicable',
                        'rows' => array(),
                    ),
                    array(
                        'id' => 'tld',
                        'state' => 'absent',
                        'rows' => array(),
                    ),
                ),
            ),
            'asserted' => array(
                'total' => 3,
                'orgs' => 2,
                'hidden' => 1,
                'occurrences' => 812,
                'claims' => self::relClaims(array(
                    array(
                        'related-to',
                        'outbound',
                        'GalaxyCluster',
                        'QakBot',
                        'Malware',
                        __(
                            'Every occurrence we hold sits in a QakBot'
                            . ' distribution event. The address rotates'
                            . ' faster than the campaign does, which is'
                            . ' what the fast-flux reading rests on.'
                        ),
                        'CIRCL',
                        '2025-07-02',
                        41,
                    ),
                    array(
                        'similar-to',
                        'outbound',
                        'Attribute',
                        '45.155.205.198',
                        'ip-dst',
                        __(
                            'Same /24, same three ports, same reseller.'
                            . ' Similarity, and deliberately not'
                            . ' attribution.'
                        ),
                        'CERT-EU',
                        '2025-05-14',
                        9,
                    ),
                    array(
                        'derived-from',
                        'inbound',
                        'Object',
                        92417,
                        'network-connection',
                        __(
                            'Extracted from a beacon capture rather than'
                            . ' reported directly.'
                        ),
                        'CthulhuSPRL.be',
                        '2025-03-28',
                        2,
                    ),
                )),
            ),
            'settings' => array(
                'correlation_limit' => 20,
                'ssdeep_threshold' => 40,
                'excluded' => false,
            ),
            /*
             * The `co` band is empty and the other two are not, which
             * is the sketch of a suppressed value: nothing the engine
             * stored, four blocks the CIDR pass re-derives, three
             * things an analyst asserted.
             */
            'graph' => array(
                'edges' => 7,
                'nodes' => array(
                    'co' => array(),
                    'near' => array('network-block', 'network-block',
                        'network-block', 'network-block'),
                    'human' => array('GalaxyCluster', 'Attribute',
                        'Object'),
                ),
            ),
        );
    }

    /**
     * @return array
     */
    private static function fluxEnrichment()
    {
        $ran = '2025-08-24 07:55';
        $previous = '2025-08-17 07:55';
        $modules = self::ipModules(array(
            'virustotal' => array(
                'ok', 8, 3, $ran, $ran, 2.6, 'misp_standard', 0),
            'shodan' => array(
                'ok', 4, 1, $ran, $ran, 3.1, 'misp_standard', 0),
            'circl_passivedns' => array(
                'ok', 12, 4, $ran, $ran, 1.9, 'misp_standard', 0),
            'ipasn' => array(
                'ok', 1, 0, $ran, $ran, 0.3, 'simplified', 0),
            'reversedns' => array(
                'none', 0, 0, $ran, $ran, 0.2, null, 0),
            'urlhaus' => array(
                'ok', 3, 1, $ran, $ran, 1.1, 'misp_standard', 0),
            'rbl' => array(
                'ok', 2, 0, $ran, $ran, 2.9, 'simplified', 0),
        ));

        $results = array(
            'virustotal' => array(
                'delta' => array(
                    'new' => 3,
                    'previous_run' => $previous,
                    'unchanged' => 5,
                ),
                'objects' => array(
                    self::enrichObject('virustotal-report', false, array(
                        array('permalink', 'link', 41),
                        array('detection-ratio', 'text', 12),
                    )),
                ),
                'attributes' => self::enrichAttrRows(array(
                    array('domain', true, '2025-08-24', 'new', 3,
                        array('circl_passivedns')),
                    array('url', true, '2025-08-24', 'new', 2),
                )),
                'dismissed' => 1,
            ),
            'circl_passivedns' => array(
                'delta' => array(
                    'new' => 4,
                    'previous_run' => $previous,
                    'unchanged' => 8,
                ),
                'objects' => array(),
                'attributes' => self::enrichAttrRows(array(
                    array('domain', true, '2025-08-24', 'new', 6,
                        array('virustotal')),
                    array('domain', false, '2025-08-24', 'known', 9),
                )),
                'dismissed' => 3,
            ),
            'ipasn' => array(
                'delta' => array(
                    'new' => 0,
                    'previous_run' => $previous,
                    'unchanged' => 1,
                ),
                'objects' => array(),
                'attributes' => self::enrichAttrRows(array(
                    array('AS', false, '2025-08-24', null, 7),
                )),
                'dismissed' => 0,
            ),
        );

        return self::enrichment(array(
            'type' => 'ip-dst',
            'last_run' => $ran,
            'service' => array(
                'reachable' => true,
                'checked' => '2025-08-24 08:10',
                'note' => null,
            ),
            'modules' => $modules,
            'results' => $results,
        ));
    }

    /**
     * @return array
     */
    private static function fluxAnalystData()
    {
        return array(
            'total' => 5,
            'notes' => 2,
            'opinions' => 3,
            'Note' => array(
                array(
                    'uuid' => 'a7b8c9d0-1122-4334-b556-7788990011ff',
                    'note' => 'The /24 has been rotating hostnames every'
                        . ' few hours since June 2024. Treat the address'
                        . ' as infrastructure, not as a campaign'
                        . ' indicator.',
                    'authors' => 'alice@circl.lu',
                    'created' => '2025-08-23 09:12:40',
                    'distribution' => 3,
                    'Org' => array('id' => 1, 'name' => 'CIRCL'),
                ),
                array(
                    'uuid' => 'b8c9d0e1-2233-4445-c667-8899001122ff',
                    'note' => 'Provider stopped answering abuse mail in'
                        . ' March. Nothing has been taken down.',
                    'authors' => 'bob@cthulhu.example',
                    'created' => '2025-06-30 14:05:11',
                    'distribution' => 3,
                    'Org' => array('id' => 2, 'name' => 'CthulhuSPRL.be'),
                ),
            ),
            'Opinion' => array(
                array(
                    'uuid' => 'c9d0e1f2-3344-4556-d778-9900112233ff',
                    'opinion' => 90,
                    'comment' => 'Continuous beaconing from three of our'
                        . ' sensors.',
                    'authors' => 'alice@circl.lu',
                    'created' => '2025-08-22 16:40:00',
                    'distribution' => 3,
                    'Org' => array('id' => 1, 'name' => 'CIRCL'),
                ),
                array(
                    'uuid' => 'd0e1f203-4455-4667-e889-0011223344ff',
                    'opinion' => 45,
                    'comment' => 'Ours were all scanner noise. The'
                        . ' address is real, the C2 reading is not ours'
                        . ' to make.',
                    'authors' => 'carol@orgname.example',
                    'created' => '2025-08-09 10:21:05',
                    'distribution' => 3,
                    'Org' => array('id' => 4, 'name' => 'ORGNAME'),
                ),
            ),
        );
    }

    /**
     * Five items, one reply. Short on purpose: this value is here for
     * occurrence scale, and an argument at 812 occurrences is the same
     * argument as at ten.
     *
     * @return array
     */
    private static function fluxAnalystTab()
    {
        $preview = self::fluxAnalystData();
        $notes = $preview['Note'];
        $opinions = $preview['Opinion'];

        $thread = array(
            self::analystItem(array(
                'kind' => 'note',
                'org' => 'CIRCL',
                'author' => $notes[0]['authors'],
                'date' => $notes[0]['created'],
                'body' => $notes[0]['note'],
                'children' => array(
                    self::analystItem(array(
                        'kind' => 'opinion',
                        'org' => 'CIRCL',
                        'author' => $opinions[0]['authors'],
                        'date' => $opinions[0]['created'],
                        'score' => $opinions[0]['opinion'],
                        'body' => $opinions[0]['comment'],
                        'attached_to' => array('kind' => 'note'),
                        'rates' => 'value',
                    )),
                ),
            )),
            self::analystItem(array(
                'kind' => 'opinion',
                'org' => 'ORGNAME',
                'author' => $opinions[1]['authors'],
                'date' => $opinions[1]['created'],
                'score' => $opinions[1]['opinion'],
                'body' => $opinions[1]['comment'],
                'rates' => 'value',
            )),
            self::analystItem(array(
                'kind' => 'note',
                'org' => 'CthulhuSPRL.be',
                'author' => $notes[1]['authors'],
                'date' => $notes[1]['created'],
                'body' => $notes[1]['note'],
            )),
            self::analystItem(array(
                'kind' => 'opinion',
                'org' => 'CERT-EU',
                'author' => 'ops@cert.europa.example',
                'date' => '2025-05-14 11:02:00',
                'score' => 80,
                'body' => 'Consistent with the /24 assertion we filed.',
                'rates' => 'value',
            )),
        );

        return array_merge($preview, self::analystTab(
            array(
                'CIRCL' => array('score' => 90, 'date' => '2025-08-22'),
                'ORGNAME' => array('score' => 45, 'date' => '2025-08-09'),
                'CERT-EU' => array('score' => 80, 'date' => '2025-05-14'),
            ),
            $thread,
            __(
                'Organisations whose events you cannot open may have'
                . ' argued about this value too. 64 of its 812'
                . ' occurrences are behind that line.'
            )
        ));
    }

    /**
     * The publication record of every published event that holds this
     * value, dated from the occurrences themselves.
     *
     * All 121, not a recent slice: the Timeline's lane counters count
     * what falls inside the window, so a slice would be consistent at
     * 30 days and would quietly lose 110 publications the moment a
     * reader widened it to all time.
     *
     * @param array $occurrences
     * @return array
     */
    private static function fluxPublications(array $occurrences)
    {
        $out = array();
        foreach ($occurrences as $row) {
            $event = $row['Event'];
            if (empty($event['published'])) {
                continue;
            }
            /*
             * From the attribute's own timestamp and not from
             * `first_seen`: 21 occurrences carry no seen dates at all,
             * and a fallback day would file their events under whatever
             * that day happened to be.
             */
            $day = gmdate('Y-m-d', (int)$row['Attribute']['timestamp']);
            $id = $event['id'];
            /*
             * An event is published once, after the last thing in it
             * was written, so the newest occurrence in it dates the
             * publication and the oldest is not a second one.
             */
            if (!isset($out[$id]) || $day > $out[$id]['day']) {
                $out[$id] = array(
                    'day' => $day,
                    'info' => $event['info'],
                    'org' => $event['Orgc']['name'],
                );
            }
        }

        $publications = array();
        foreach ($out as $id => $row) {
            $at = self::addDays($row['day'], 1) . ' 08:00:00';
            $publications[$id] = array(
                'info' => $row['info'],
                'org' => $row['org'],
                'first' => $at,
                'last' => $at,
            );
        }
        return $publications;
    }

    /**
     * The audit rail, over the occurrences it can afford to detail.
     *
     * `history()` renders one collapsible section per occurrence it is
     * handed, and `hidden` is `total_occurrences` minus the ones it was
     * given — so handing it a sample would make the panel's footer
     * state a number of ACL-hidden occurrences that is not the ACL's.
     * It gets all 748, and 748 sections is phase 17 §9.8's prediction
     * rendered rather than argued.
     *
     * @param array $occurrences
     * @return array
     */
    private static function fluxHistory(array $occurrences)
    {
        return self::history(array(
            'recorded' => true,
            'occurrences' => $occurrences,
            'groups' => array(),
            'event_entries' => array(),
            'total_occurrences' => 812,
            'viewer_org' => 'CIRCL',
            'vocab' => self::auditVocab(),
        ));
    }

    /**
     * @return array
     */
    private static function fluxVerdictCurve()
    {
        $curve = array();
        for ($d = 0; $d < 90; $d++) {
            /*
             * A value sighted almost daily never gets to decay: the
             * curve is a slow climb with the shallow saw of the gaps,
             * which is the honest shape for continuous reporting.
             */
            $curve[] = 82 + (int)round(11 * $d / 89) - (($d % 7) === 6
                ? 2
                : 0);
        }
        return $curve;
    }

    /**
     * @return array
     */
    private static function fluxLedger()
    {
        return array(
            array(
                'kind' => __('Reporting'),
                'note' => __('who reported it, and how widely'),
                'signals' => array(
                    array(
                        'direction' => 'up',
                        'weight' => 'strong',
                        'contribution' => 31,
                        'signal' => __(
                            '23 independent organisations reported it'
                        ),
                        'evidence' => __(
                            '748 occurrences across 137 events, no'
                            . ' single organisation holding more than a'
                            . ' tenth of them'
                        ),
                        'source' => __('Occurrences'),
                        'as_of' => '2025-08-23',
                    ),
                    array(
                        'direction' => 'up',
                        'weight' => 'moderate',
                        'contribution' => 7,
                        'signal' => __('121 of 137 events are published'),
                        'evidence' => __(
                            'Published events carry more weight than'
                            . ' drafts'
                        ),
                        'source' => __('Occurrences'),
                        'as_of' => '2025-08-23',
                    ),
                ),
            ),
            array(
                'kind' => __('Sightings'),
                'note' => __('who has seen it, and how recently'),
                'signals' => array(
                    array(
                        'direction' => 'up',
                        'weight' => 'strong',
                        'contribution' => 24,
                        'signal' => __(
                            '418 sightings from 23 orgs, last yesterday'
                        ),
                        'evidence' => __(
                            'Reported almost daily for fourteen months,'
                            . ' so the decay clock never runs'
                        ),
                        'source' => __('Sightings'),
                        'as_of' => '2025-08-23',
                    ),
                    array(
                        'direction' => 'down',
                        'weight' => 'moderate',
                        'contribution' => -5,
                        'signal' => __(
                            '9 false-positive sightings from 6 orgs'
                        ),
                        'evidence' => __(
                            'Scanner noise against a whole /24, which is'
                            . ' what a shared bulletproof range produces'
                        ),
                        'source' => __('Sightings'),
                        'as_of' => '2025-08-14',
                    ),
                ),
            ),
            array(
                'kind' => __('Attribution'),
                'note' => __('what it has been tied to'),
                'signals' => array(
                    array(
                        'direction' => 'up',
                        'weight' => 'strong',
                        'contribution' => 17,
                        'signal' => __('QakBot, on 107 occurrences'),
                        'evidence' => __(
                            'One galaxy cluster, asserted by CIRCL and'
                            . ' carried on the occurrences themselves'
                        ),
                        'source' => __('Relationships'),
                        'as_of' => '2025-07-02',
                    ),
                ),
            ),
            array(
                'kind' => __('Lifecycle'),
                'note' => __('how long, and how continuously'),
                'signals' => array(
                    array(
                        'direction' => 'up',
                        'weight' => 'moderate',
                        'contribution' => 12,
                        'signal' => __(
                            'Fourteen months without a month of silence'
                        ),
                        'evidence' => '2024-06-11 → 2025-08-23',
                        'source' => __('Timeline'),
                        'as_of' => '2025-08-23',
                    ),
                    array(
                        'direction' => 'up',
                        'weight' => 'moderate',
                        'contribution' => 7,
                        'signal' => __('Last reported yesterday'),
                        'evidence' => __(
                            'Recency, on a value whose last sighting is'
                            . ' never old'
                        ),
                        'source' => __('Sightings'),
                        'as_of' => '2025-08-23',
                    ),
                ),
            ),
        );
    }

    /**
     * The verdict for a value the correlation engine gave up on.
     *
     * The score rests on breadth, sightings and continuity, and on
     * nothing the engine stored — because for this value the engine
     * stored nothing. `not_counted` says so rather than leaving a
     * reader to notice the Relationships tab is empty and wonder
     * whether the score used it.
     *
     * @param array $nidsCurve
     * @param array $tally
     * @param array $sightings
     * @return array
     */
    private static function fluxVerdict(
        array $nidsCurve,
        array $tally,
        array $sightings
    ) {
        return array(
            'disposition' => 'MALICIOUS',
            'score' => 93,
            'confidence' => 'high',
            'summary' => __(
                'Twenty-three organisations report this address as'
                . ' fast-flux command-and-control infrastructure, over'
                . ' 748 occurrences in 137 events and fourteen months of'
                . ' near-daily sightings. It hits no warninglist. It is'
                . ' also past MISP.correlation_limit, so the engine'
                . ' stored no correlation for it at all — the disposition'
                . ' rests on reporting breadth and continuity, and the'
                . ' ledger says which signals were available.'
            ),
            'profile' => 'default-v3',
            'computed_at' => null,
            'acl_note' => __(
                '64 occurrences you cannot see were excluded from this'
                . ' assessment.'
            ),
            'ledger' => self::fluxLedger(),
            'conflicts' => array(
                array(
                    'kind' => 'to_ids',
                    'title' => __(
                        'to_ids disagreement: 592 yes / 156 no'
                    ),
                    'note' => __(
                        'Not netted off, and at this many reporters it is'
                        . ' not an anomaly either: whether a shared'
                        . ' bulletproof address should fire an IDS rule'
                        . ' is a per-occurrence editorial call.'
                    ),
                    'yes' => 592,
                    'no' => 156,
                    'evidence' => __(
                        '592 occurrences set yes, 156 set no, across 23'
                        . ' organisations'
                    ),
                    'expanded' => false,
                    'rows' => array(),
                    'actions' => array(),
                    'confirm_note' => null,
                ),
                array(
                    'kind' => 'tlp',
                    'title' => __('TLP disagreement: amber vs green'),
                    'note' => __(
                        'Both readings are in use. With 137 events from'
                        . ' 23 organisations, a single TLP is the'
                        . ' unlikely outcome.'
                    ),
                    'yes' => isset($tally['tag']['tlp:amber'])
                        ? $tally['tag']['tlp:amber']['count']
                        : 0,
                    'no' => isset($tally['tag']['tlp:green'])
                        ? $tally['tag']['tlp:green']['count']
                        : 0,
                    'evidence' => __(
                        'Counted over the occurrences you can open'
                    ),
                    'expanded' => false,
                    'rows' => array(),
                    'actions' => array(),
                    'confirm_note' => null,
                ),
            ),
            'orgs' => self::fluxVerdictOrgs($tally, $sightings),
            'composition' => array(
                array(
                    'label' => __('Reporting breadth'),
                    'points' => 38,
                    'colour' => 'var(--event)',
                ),
                array(
                    'label' => __('Sightings'),
                    'points' => 24,
                    'colour' => 'var(--sighting)',
                ),
                array(
                    'label' => __('Attribution'),
                    'points' => 17,
                    'colour' => 'var(--galaxy)',
                ),
                array(
                    'label' => __('Lifecycle'),
                    'points' => 19,
                    'colour' => 'var(--correlation)',
                ),
                array(
                    'label' => __('Signals against'),
                    'points' => -5,
                    'colour' => 'var(--bs-danger)',
                ),
            ),
            'composition_note' => __(
                'Weights come from the default-v3 profile. Correlation'
                . ' contributes nothing here, and not because it scored'
                . ' zero: there was nothing stored to score.'
            ),
            'curves' => array(
                array(
                    'label' => __('Synthesised verdict'),
                    'colour' => 'var(--vp-mal)',
                    'data' => self::fluxVerdictCurve(),
                ),
                array(
                    'label' => __('NIDS decay score'),
                    'colour' => 'var(--vp-conflict)',
                    'dashed' => true,
                    'data' => $nidsCurve,
                ),
            ),
            'curves_span' => __('90 days'),
            'curves_note' => __(
                'Almost flat, and that is the finding: a value sighted'
                . ' every few days never decays, so the curve records'
                . ' continuity rather than events.'
            ),
            'not_counted' => array(
                array(
                    'title' => __('64 occurrences'),
                    'note' => __(
                        'Outside your ACL — excluded, not hidden. The'
                        . ' score you see is the score for your'
                        . ' permissions.'
                    ),
                ),
                array(
                    'title' => __('Every correlation'),
                    'note' => __(
                        'The value is past MISP.correlation_limit, so'
                        . ' MISP stored none. Nothing was weighed and'
                        . ' found wanting; there was nothing to weigh.'
                    ),
                ),
                array(
                    'title' => __('Object siblings'),
                    'note' => __(
                        '494 distinct siblings across 683 objects are'
                        . ' structure, not corroboration: the same'
                        . ' reporter filing the same object shape 300'
                        . ' times is one practice, not 300 signals.'
                    ),
                ),
            ),
            'changers' => array(
                array(
                    'direction' => 'down',
                    'text' => __(
                        'A warninglist hit of category known → CONFLICTED'
                        . ' immediately, whatever the score.'
                    ),
                ),
                array(
                    'direction' => 'down',
                    'text' => __(
                        'No sighting for 30 days → decay takes the NIDS'
                        . ' score under its 60 threshold.'
                    ),
                ),
                array(
                    'direction' => 'down',
                    'text' => __(
                        'The provider withdrawing the range would leave'
                        . ' the reporting breadth and remove the'
                        . ' continuity — which is the signal doing most'
                        . ' of the work here.'
                    ),
                ),
            ),
            'changer_actions' => array(
                array(
                    'label' => __('Mark false positive'),
                    'icon' => 'fas fa-flag',
                    'colour' => 'var(--vp-mal)',
                    'emphasis' => true,
                ),
                array(
                    'label' => __('Record an opinion'),
                    'icon' => 'fas fa-scale-balanced',
                    'colour' => 'var(--analystData)',
                ),
                array(
                    'label' => __('Notify me if the verdict changes'),
                    'icon' => 'fas fa-bell',
                    'colour' => 'var(--correlation)',
                ),
            ),
        );
    }

    /**
     * A fast-flux C2 node at occurrence scale: 812 occurrences, 137
     * events, 23 organisations, and past `MISP.correlation_limit` so
     * the engine stored nothing.
     *
     * @return array
     */
    private static function flux()
    {
        $created = '2024-06-11';
        $occurrences = self::fluxOccurrences();
        $tally = self::fluxTally($occurrences);
        $rows = self::fluxSightingRows();
        $decay = self::decayModels($rows, $created, self::fluxModels());
        $enrichment = self::fluxEnrichment();
        $analyst = self::fluxAnalystTab();
        $tags = self::fluxTags($tally);
        $galaxies = array(
            array(
                'name' => 'QakBot',
                'kind' => __('Malware') . ' · Qbot',
                'n' => 107,
            ),
            array(
                'name' => 'T1071.001',
                'kind' => __('Attack pattern')
                    . ' · Application Layer Protocol: Web Protocols',
                'n' => 41,
            ),
        );
        $feeds = array(
            array(
                'name' => 'CIRCL OSINT Feed',
                'provider' => 'CIRCL',
                'events' => 31,
            ),
            array(
                'name' => 'Botvrij.eu Data',
                'provider' => 'Botvrij.eu',
                'events' => 12,
            ),
            array(
                'name' => 'URLhaus C2 export',
                'provider' => 'abuse.ch',
                'events' => 6,
            ),
        );
        $orgCount = count($tally['orgs']);
        $eventCount = count($tally['events']);

        return array(
            'value' => '45.155.205.233',
            'types' => array(
                array('type' => 'ip-dst',
                    'count' => $tally['type']['ip-dst']),
                array('type' => 'ip-src',
                    'count' => $tally['type']['ip-src']),
                array('type' => 'domain|ip',
                    'count' => $tally['type']['domain|ip']),
            ),
            'value2_note' => sprintf(
                __('%d occurrences have it as the second half of a'
                    . ' domain|ip'),
                $tally['type']['domain|ip']
            ),
            'counts' => array(
                'occurrences' => 812,
                'sightings' => count($rows),
                /*
                 * The engine stored nothing, so what the Relationships
                 * tab has to list is the siblings, the near-matches and
                 * the assertions. A zero here would send a reader past
                 * a tab that has 501 rows on it.
                 */
                'relationships' => 501,
                'enrichment' => $enrichment['pending'],
                'analyst' => $analyst['counts']['items'],
            ),
            'facts' => array(
                array(
                    'label' => __('First seen'),
                    'value' => $created,
                    'sub' => __('14 months ago'),
                    'tab' => 'timeline',
                ),
                array(
                    'label' => __('Last seen'),
                    'value' => '2025-08-23',
                    'sub' => __('yesterday'),
                    'tab' => 'timeline',
                ),
                array(
                    'label' => __('Occurrences'),
                    'value' => '812',
                    'sub' => __('3 types'),
                    'tab' => 'occurrences',
                ),
                array(
                    'label' => __('Events'),
                    'value' => (string)$eventCount,
                    'sub' => sprintf(
                        __('%d published'),
                        count($tally['published'])
                    ),
                    'tab' => 'occurrences',
                ),
                array(
                    'label' => __('Organisations'),
                    'value' => (string)$orgCount,
                    'sub' => sprintf(__('CIRCL + %d'), $orgCount - 1),
                    'tab' => 'verdict',
                ),
                array(
                    'label' => __('Sightings'),
                    'value' => (string)count($rows),
                    'sub' => __('9 false positives'),
                    'tab' => 'sightings',
                ),
            ),
            'pivots' => array(
                array(
                    'label' => __('Containing CIDR'),
                    'hint' => '45.155.205.0/24',
                ),
                array(
                    'label' => __('ASN'),
                    'hint' => 'AS204601 Flux-Host',
                ),
                array(
                    'label' => __('Geolocation'),
                    'hint' => __('Chișinău, MD'),
                ),
                array(
                    'label' => __('Ports seen'),
                    'hint' => '443, 8080, 8443, 9001',
                ),
                array(
                    'label' => __('Passive DNS'),
                    'hint' => __('247 hostnames'),
                ),
            ),
            'occurrences' => $occurrences,
            'occurrence_stats' => array(
                'total' => 812,
                'shown' => count($occurrences),
                'hidden' => 812 - count($occurrences),
                'events' => $eventCount,
                'orgs' => $orgCount,
                'deleted' => $tally['deleted'],
            ),
            'occurrence_acl_note' => sprintf(
                __(
                    'Showing %1$d of 812 occurrences. %2$d are hidden by'
                    . ' distribution rules on events owned by other'
                    . ' organisations.'
                ),
                count($occurrences),
                812 - count($occurrences)
            ),
            'occurrence_facets' => self::fluxOccurrenceFacets(
                $occurrences,
                $tally
            ),
            'tags' => $tags,
            'galaxies' => $galaxies,
            'analyst' => $analyst,
            'sightings' => array(
                'total' => count($rows),
                'fp' => 9,
                'expiration' => 1,
                'spark' => self::sightingSpark($rows),
                'reporters' => self::fluxReporters($rows),
                'last' => __('yesterday'),
            ),
            'sighting_rows' => $rows,
            'sighting_series' => self::sightingSeries($rows, $decay, $created),
            'sighting_notes' => self::sightingNotes(__(
                'Nine false positives over fourteen months, none of them'
                . ' consecutive. MISP resets the decay clock on type-0'
                . ' sightings only, so they are visible on the axis and'
                . ' move no score.'
            )),
            'decay' => $decay,
            'warninglists' => array(),
            'warninglists_checked' => 84,
            'correlations' => array(
                'count' => 0,
                'over_correlating' => true,
                'threshold' => 50,
            ),
            'relationships' => self::fluxRelationships($tally),
            'enrichment' => $enrichment,
            'external' => array(
                'feeds' => $feeds,
                'servers' => 4,
                'sightingdb' => 18402,
            ),
            'timeline' => self::timeline(array(
                'occurrences' => $occurrences,
                'sightings' => $rows,
                'analyst' => $analyst,
                'tags' => $tags,
                'galaxies' => $galaxies,
                'feeds' => $feeds,
                'publications' => self::fluxPublications($occurrences),
                'window' => array(
                    'from' => '2025-07-26',
                    'to' => self::TODAY,
                ),
                'feeds_as_of' => '2025-08-24 05:30:00',
                'acl_note' => __(
                    '64 of this value\'s 812 occurrences are on events'
                    . ' you cannot see. Nothing they contribute is in'
                    . ' this chronology, and how much that is cannot be'
                    . ' counted.'
                ),
            )),
            'history' => self::fluxHistory($occurrences),
            'verdict' => self::fluxVerdict(
                self::decaySpan($rows, $created, $decay[0]),
                $tally,
                $rows
            ),
        );
    }

    /**
     * Who has reported seeing it, in order, tallied from the rows.
     *
     * @param array $rows
     * @return array
     */
    private static function fluxReporters(array $rows)
    {
        $seen = array();
        foreach ($rows as $row) {
            $seen[$row['org']] = 1
                + (isset($seen[$row['org']]) ? $seen[$row['org']] : 0);
        }
        arsort($seen);
        $out = array();
        foreach ($seen as $org => $count) {
            $out[] = array('org' => $org, 'count' => $count);
        }
        return $out;
    }
}
