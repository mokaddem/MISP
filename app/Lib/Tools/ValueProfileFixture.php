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
        if ($value === '104.21.34.198') {
            return self::conflicted();
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
                    'data' => self::decayCurveNids(),
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
                'enrichment' => 4,
                'analyst' => 7,
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
            'occurrences' => self::conflictedOccurrences(),
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
            'tags' => self::conflictedTags(),
            'galaxies' => array(
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
            ),
            'analyst' => self::conflictedAnalystData(),
            'sightings' => array(
                'total' => 63,
                'fp' => 9,
                'expiration' => 2,
                'spark' => self::conflictedSpark(),
                'reporters' => array(
                    array('org' => 'CIRCL', 'count' => 26),
                    array('org' => 'Botvrij.eu', 'count' => 19),
                    array('org' => 'CthulhuSPRL.be', 'count' => 13),
                    array('org' => 'ORGNAME', 'count' => 5),
                ),
                'last' => __('yesterday'),
            ),
            'decay' => array(
                array(
                    'model' => 'Phishing Model',
                    'score' => 66,
                    'threshold' => 50,
                    'decayed' => false,
                    'curve' => self::conflictedCurvePhishing(),
                ),
                array(
                    'model' => 'NIDS Simple Decaying Model',
                    'score' => 41,
                    'threshold' => 60,
                    'decayed' => true,
                    'curve' => self::conflictedCurveNids(),
                ),
            ),
            'warninglists' => array(
                array(
                    'name' => 'List of known Cloudflare IP ranges',
                    'version' => '20250714',
                    'category' => 'known',
                    'matched' => '104.21.0.0/20',
                ),
            ),
            'warninglists_checked' => 84,
            'correlations' => array(
                'count' => 1847,
                'over_correlating' => true,
                'threshold' => 50,
            ),
            'external' => array(
                'feeds' => array(
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
                ),
                'servers' => 3,
                'sightingdb' => 84109,
            ),
            'verdict' => self::conflictedVerdict(),
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
    private static function conflictedVerdict()
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
            'opinions' => array(
                'n' => 7,
                'mean' => 46,
                'buckets' => array(
                    array('label' => '0–10', 'count' => 0),
                    array('label' => '11–20', 'count' => 3),
                    array('label' => '21–30', 'count' => 0),
                    array('label' => '31–40', 'count' => 0),
                    array('label' => '41–50', 'count' => 1),
                    array('label' => '51–60', 'count' => 0),
                    array('label' => '61–70', 'count' => 1),
                    array('label' => '71–80', 'count' => 1),
                    array('label' => '81–90', 'count' => 1),
                    array('label' => '91–100', 'count' => 0),
                ),
                'note' => __(
                    'Bimodal, not uncertain. The mean of 46 is the one'
                    . ' number on this page that means nothing — two'
                    . ' clusters, no middle. CthulhuSPRL.be 82 / 78'
                    . ' against CIRCL 15 / 20 / 12.'
                ),
            ),
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

    /**
     * A CDN edge is sighted constantly, in a way a dedicated C2 is not:
     * a high floor rather than bursts.
     *
     * @return array
     */
    private static function conflictedSpark()
    {
        return array(
            1, 2, 1, 3, 2, 1, 2, 4, 2, 1,
            2, 1, 3, 2, 1, 0, 1, 2, 3, 2,
            1, 2, 5, 3, 2, 1, 2, 1, 3, 4,
            2, 1, 2, 3, 6, 4, 2, 3, 5, 3,
        );
    }

    /**
     * Two phishing waves keep the score alive: it decays between them
     * and is bumped back over the threshold each time.
     *
     * @return array
     */
    private static function conflictedCurvePhishing()
    {
        return array(
            58, 55, 52, 49, 47, 44, 42, 55, 52, 49,
            47, 44, 42, 40, 38, 36, 34, 46, 44, 41,
            39, 37, 35, 33, 31, 44, 42, 40, 38, 36,
            34, 32, 48, 62, 71, 68, 65, 70, 74, 66,
        );
    }

    /**
     * The NIDS model has no phishing bump to carry it, so it crosses its
     * threshold and stays under.
     *
     * @return array
     */
    private static function conflictedCurveNids()
    {
        return array(
            74, 71, 69, 66, 64, 61, 59, 66, 63, 61,
            58, 56, 54, 51, 49, 47, 45, 52, 50, 48,
            46, 44, 42, 40, 38, 46, 44, 42, 40, 38,
            36, 35, 42, 50, 55, 52, 49, 51, 53, 41,
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
}
