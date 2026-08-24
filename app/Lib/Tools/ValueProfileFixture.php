<?php

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
    );

    /**
     * @param string $value A refanged value, as MISP stores it.
     * @return array
     */
    public static function forValue($value)
    {
        if ($value === '185.234.219.24') {
            return self::malicious();
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
                'enrichment' => 9,
                'analyst' => 6,
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
            'occurrences' => self::maliciousOccurrences(),
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
            'tags' => self::maliciousTags(),
            'galaxies' => array(
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
            ),
            'analyst' => self::maliciousAnalystData(),
            'sightings' => array(
                'total' => 47,
                'fp' => 1,
                'expiration' => 0,
                'spark' => self::sightingSpark(),
                'reporters' => array(
                    array('org' => 'CIRCL', 'count' => 21),
                    array('org' => 'CthulhuSPRL.be', 'count' => 14),
                    array('org' => 'Team-CIRCL', 'count' => 8),
                    array('org' => 'ORGNAME', 'count' => 4),
                ),
                'last' => __('2 days ago'),
            ),
            'decay' => array(
                array(
                    'model' => 'NIDS Simple Decaying Model',
                    'score' => 78,
                    'threshold' => 60,
                    'decayed' => false,
                    'curve' => self::decayCurveNids(),
                ),
                array(
                    'model' => 'Phishing Model',
                    'score' => 34,
                    'threshold' => 50,
                    'decayed' => true,
                    'curve' => self::decayCurvePhishing(),
                ),
            ),
            'warninglists' => array(),
            'warninglists_checked' => 84,
            'correlations' => array(
                'count' => 31,
                'over_correlating' => false,
                'threshold' => 50,
            ),
            'external' => array(
                'feeds' => array(
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
                ),
                'servers' => 2,
                'sightingdb' => 1204,
            ),
            'verdict' => self::maliciousVerdict(),
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
     * @return array
     */
    private static function maliciousVerdict()
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
                    'rows' => array(
                        array(
                            'event_id' => 1284,
                            'org' => 'CIRCL',
                            'to_ids' => 1,
                            'category' => 'Network activity',
                            'comment' => 'Emotet C2, port 8080',
                        ),
                        array(
                            'event_id' => 1272,
                            'org' => 'Team-CIRCL',
                            'to_ids' => 0,
                            'category' => 'Network activity',
                            'comment' => 'Scanning source, low confidence',
                        ),
                        array(
                            'event_id' => 1265,
                            'org' => 'ORGNAME',
                            'to_ids' => 0,
                            'category' => 'Payload delivery',
                            'comment' => 'Reported by a member',
                        ),
                    ),
                    'actions' => array(
                        __('Set to_ids on all visible occurrences'),
                        __('Clear to_ids on all visible occurrences'),
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
                    'rows' => array(),
                    'actions' => array(),
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
                array(
                    'label' => __('Contradictions'),
                    'points' => -14,
                    'colour' => 'var(--bs-danger)',
                ),
            ),
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
            'tags' => array(),
            'galaxies' => array(),
            'analyst' => array(
                'total' => 0,
                'notes' => 0,
                'opinions' => 0,
                'Note' => array(),
                'Opinion' => array(),
            ),
            'sightings' => array(
                'total' => 0,
                'fp' => 0,
                'expiration' => 0,
                'spark' => array(),
                'reporters' => array(),
                'last' => null,
            ),
            'decay' => array(),
            'warninglists' => array(),
            'warninglists_checked' => 84,
            'correlations' => array(
                'count' => 0,
                'over_correlating' => false,
                'threshold' => 50,
            ),
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
     * 90 days of sightings, bucketed into 40 columns.
     *
     * @return array
     */
    private static function sightingSpark()
    {
        return array(
            0, 1, 0, 0, 2, 1, 0, 3, 1, 0,
            0, 0, 1, 2, 4, 1, 0, 0, 1, 0,
            2, 3, 1, 0, 0, 1, 0, 0, 2, 1,
            0, 1, 3, 5, 2, 1, 0, 2, 4, 1,
        );
    }

    /**
     * Decay score over the same 90 days: sightings bump it back up.
     *
     * @return array
     */
    private static function decayCurveNids()
    {
        return array(
            92, 90, 87, 85, 88, 86, 83, 91, 88, 85,
            82, 79, 81, 84, 90, 87, 84, 81, 83, 80,
            85, 88, 86, 83, 80, 82, 79, 76, 81, 79,
            76, 78, 84, 90, 88, 85, 82, 85, 90, 78,
        );
    }

    /**
     * @return array
     */
    private static function decayCurvePhishing()
    {
        return array(
            71, 68, 65, 62, 64, 61, 58, 63, 60, 57,
            54, 51, 53, 56, 61, 58, 55, 52, 54, 51,
            55, 58, 55, 52, 49, 51, 48, 45, 49, 47,
            44, 46, 51, 56, 53, 50, 47, 49, 53, 34,
        );
    }
}
