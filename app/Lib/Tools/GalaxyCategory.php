<?php

/**
 * What role a galaxy's clusters play, so a panel can ask for the ones
 * that name a threat without hardcoding a list of galaxy names.
 *
 * MISP records nothing of the kind. `galaxies` carries `type`, `name`,
 * `namespace`, `icon` and `kill_chain_order`, and `namespace` groups by
 * *publisher* — misp, mitre, tidal, disarm, nist-nice — not by what the
 * clusters underneath are for. So the 130 galaxies misp-galaxy ships
 * are, to any consumer, one undifferentiated set in which Threat Actor
 * sits beside UKHSA Culture Collections, Firearms and Cancer.
 *
 * **Where this belongs, and it is not here.** The right home for this
 * is a `category` field on each galaxy in the misp-galaxy repository,
 * ingested by `Galaxy::__load_galaxies` into a column and editable per
 * galaxy in the UI, so an administrator can classify the galaxies they
 * created themselves. This file is the interim table for the consumers
 * that need the answer now, and it should be deleted in favour of that
 * field rather than grown. Until then a galaxy absent from the table
 * is *unrecognised*, not judged harmless, and the two callers below
 * skip it — including every locally created galaxy, whose `type` is a
 * bare UUID no shipped table can predict.
 *
 * **Why a named threat is a galaxy cluster and nothing else.** Measured
 * on the development instance, 2026-09-03:
 *
 *   freetext tags        10,840 exist, 173 used on events. Ranked by
 *                        event count the top two are the word
 *                        `malware` (165 events) and ` C2` (72), and
 *                        one malware family carries seven spellings
 *                        (`LummaC2`, `Lumma`, `LummaStealer`,
 *                        `lummaC`, ...). A card ranking these leads
 *                        with a category noun and double-counts
 *                        everything it does recognise.
 *   taxonomies           the three that sound like they name threats
 *                        classify instead: `malware_classification`
 *                        by category, `ms-caro-malware` by type and
 *                        platform, `adversary` by infrastructure
 *                        status. The only namespaced tags here that
 *                        do name one — `Threat:Sofacy/APT28`,
 *                        `Banker: TrickBot` — are absent from
 *                        `taxonomies`: freetext with a colon in it.
 *   galaxy clusters      curated, deduplicated, each with a page to
 *                        link to and an ACL to check.
 *
 * Attack patterns are excluded from `named-threat` on purpose: *where
 * in the intrusion* is a different question from *who*, it is answered
 * by its own group on the same card, and it is the single largest
 * galaxy on most values (21 of `8.8.8.8`'s 26 event clusters), so
 * folding it in would bury the names it sits beside.
 *
 * Pure and static, and it takes no `$user`:
 * prd/value-profile-live/00-contract.md §14.5.
 * prd/value-profile-live/24b-relationships.md §10.
 */
class GalaxyCategory
{
    /**
     * Clusters that name something conducting or constituting an
     * intrusion. The dividing line is curation, not menace: a
     * surveillance vendor is in because that galaxy is a curated list
     * of firms selling intrusion capability, while `intelligence-agency`
     * is out because it is a reference list of every agency there is.
     */
    const NAMED_THREAT = 'named-threat';

    /** Adversary behaviour — the *where in the intrusion* question. */
    const TECHNIQUE = 'technique';

    /** Who was hit: sector, place, target description. */
    const VICTIM = 'victim';

    /** Mitigations, countermeasures and controls. */
    const DEFENSIVE = 'defensive';

    /** Detection content: rules, analytics, data sources. */
    const DETECTION = 'detection';

    /** What is exposed: assets, platforms, services. */
    const TARGETING = 'targeting';

    /**
     * Present so the table records the near-misses rather than staying
     * silent about them. `producer` and `intelligence-agency` are the
     * two that read like threats and are not: the first names who
     * published the intelligence, the second is a directory.
     */
    const CONTEXT = 'context';

    const ACTOR = 'actor';
    const CAMPAIGN = 'campaign';
    const MALWARE = 'malware';
    const TOOL = 'tool';

    /**
     * Galaxy `type` => array(category, kind).
     *
     * Keyed on `type` because that is the string inside every tag name
     * (`misp-galaxy:<type>="<cluster>"`) and the value of
     * `galaxy_clusters.type`, so a caller holding either matches
     * without a join.
     *
     * The `deprecated`-namespace MITRE galaxies are listed alongside
     * their replacements: they still carry historical event tags — 44
     * events on `mitre-enterprise-attack-intrusion-set` here — and a
     * value's events are frequently years old.
     *
     * Unlisted on purpose: the non-security galaxies (firearms,
     * disease, ammunitions, uavs, ukhsa-culture-collections, naics,
     * NACE, nato, handicap, nice-framework-*), the maturity and
     * self-assessment frameworks (cti-cmm-1-3, bitns, plot4ai,
     * sod-matrix, tea-matrix, veris-framework, rsit, scor-*), and the
     * typologies that classify rather than name (disarm-actortypes).
     */
    private static $table = array(
        // Actors.
        'threat-actor' => array(self::NAMED_THREAT, self::ACTOR),
        'mitre-intrusion-set' => array(self::NAMED_THREAT, self::ACTOR),
        'mitre-enterprise-attack-intrusion-set' =>
            array(self::NAMED_THREAT, self::ACTOR),
        'mitre-mobile-attack-intrusion-set' =>
            array(self::NAMED_THREAT, self::ACTOR),
        'mitre-pre-attack-intrusion-set' =>
            array(self::NAMED_THREAT, self::ACTOR),
        'mitre-ics-groups' => array(self::NAMED_THREAT, self::ACTOR),
        'groups' => array(self::NAMED_THREAT, self::ACTOR),
        'microsoft-activity-group' =>
            array(self::NAMED_THREAT, self::ACTOR),
        '360net-threat-actor' => array(self::NAMED_THREAT, self::ACTOR),
        'surveillance-vendor' => array(self::NAMED_THREAT, self::ACTOR),
        'canada-listed-terrorist-entities' =>
            array(self::NAMED_THREAT, self::ACTOR),

        // Campaigns.
        'campaigns' => array(self::NAMED_THREAT, self::CAMPAIGN),

        // Malware.
        'malpedia' => array(self::NAMED_THREAT, self::MALWARE),
        'ransomware' => array(self::NAMED_THREAT, self::MALWARE),
        'backdoor' => array(self::NAMED_THREAT, self::MALWARE),
        'banker' => array(self::NAMED_THREAT, self::MALWARE),
        'stealer' => array(self::NAMED_THREAT, self::MALWARE),
        'wiper' => array(self::NAMED_THREAT, self::MALWARE),
        'rat' => array(self::NAMED_THREAT, self::MALWARE),
        'botnet' => array(self::NAMED_THREAT, self::MALWARE),
        'cryptominers' => array(self::NAMED_THREAT, self::MALWARE),
        'android' => array(self::NAMED_THREAT, self::MALWARE),
        'stalkerware' => array(self::NAMED_THREAT, self::MALWARE),
        'mitre-malware' => array(self::NAMED_THREAT, self::MALWARE),
        'mitre-enterprise-attack-malware' =>
            array(self::NAMED_THREAT, self::MALWARE),
        'mitre-mobile-attack-malware' =>
            array(self::NAMED_THREAT, self::MALWARE),
        'mitre-ics-software' => array(self::NAMED_THREAT, self::MALWARE),

        // Tooling, including the dual-use families whose own
        // descriptions say they are abused rather than authored for it.
        'tool' => array(self::NAMED_THREAT, self::TOOL),
        'mitre-tool' => array(self::NAMED_THREAT, self::TOOL),
        'mitre-enterprise-attack-tool' =>
            array(self::NAMED_THREAT, self::TOOL),
        'mitre-mobile-attack-tool' =>
            array(self::NAMED_THREAT, self::TOOL),
        'exploit-kit' => array(self::NAMED_THREAT, self::TOOL),
        'tds' => array(self::NAMED_THREAT, self::TOOL),
        'rmm-tool' => array(self::NAMED_THREAT, self::TOOL),
        'software' => array(self::NAMED_THREAT, self::TOOL),

        // Behaviour. `attack-pattern` is the ATT&CK-shaped kind whose
        // galaxy carries `kill_chain_order`, which is what a tactic
        // roll-up reads; `technique` is every other framework's own
        // technique list; `tactic` names a phase directly.
        'mitre-attack-pattern' =>
            array(self::TECHNIQUE, 'attack-pattern'),
        'mitre-enterprise-attack-attack-pattern' =>
            array(self::TECHNIQUE, 'attack-pattern'),
        'mitre-mobile-attack-attack-pattern' =>
            array(self::TECHNIQUE, 'attack-pattern'),
        'mitre-pre-attack-attack-pattern' =>
            array(self::TECHNIQUE, 'attack-pattern'),
        'mitre-atlas-attack-pattern' =>
            array(self::TECHNIQUE, 'attack-pattern'),
        'mitre-ics-techniques' =>
            array(self::TECHNIQUE, 'attack-pattern'),
        'cmtmf-attack-pattern' =>
            array(self::TECHNIQUE, 'attack-pattern'),
        'financial-fraud' => array(self::TECHNIQUE, 'attack-pattern'),
        'gsma-motif' => array(self::TECHNIQUE, 'attack-pattern'),
        'amitt-misinformation-pattern' =>
            array(self::TECHNIQUE, 'technique'),
        'disarm-techniques' => array(self::TECHNIQUE, 'technique'),
        'dima-techniques' => array(self::TECHNIQUE, 'technique'),
        'technique' => array(self::TECHNIQUE, 'technique'),
        'atrm' => array(self::TECHNIQUE, 'technique'),
        'cloud-security' => array(self::TECHNIQUE, 'technique'),
        'first-dns' => array(self::TECHNIQUE, 'technique'),
        'sparta-techniques' => array(self::TECHNIQUE, 'technique'),
        'tmss' => array(self::TECHNIQUE, 'technique'),
        'mitre-fraud-framework' => array(self::TECHNIQUE, 'technique'),
        'bhadra-framework' => array(self::TECHNIQUE, 'technique'),
        'mitre-ics-tactics' => array(self::TECHNIQUE, 'tactic'),
        'sparta-tactics' => array(self::TECHNIQUE, 'tactic'),
        'tactic' => array(self::TECHNIQUE, 'tactic'),
        'human-layer-kill-chain' => array(self::TECHNIQUE, 'tactic'),

        // Who was hit.
        'sector' => array(self::VICTIM, 'sector'),
        'cert-eu-govsector' => array(self::VICTIM, 'sector'),
        'country' => array(self::VICTIM, 'location'),
        'region' => array(self::VICTIM, 'location'),
        'target-information' => array(self::VICTIM, 'target'),

        // What to do about it.
        'mitre-course-of-action' =>
            array(self::DEFENSIVE, 'course-of-action'),
        'mitre-enterprise-attack-course-of-action' =>
            array(self::DEFENSIVE, 'course-of-action'),
        'mitre-mobile-attack-course-of-action' =>
            array(self::DEFENSIVE, 'course-of-action'),
        'mitre-atlas-course-of-action' =>
            array(self::DEFENSIVE, 'course-of-action'),
        'disarm-countermeasures' =>
            array(self::DEFENSIVE, 'course-of-action'),
        'preventive-measure' =>
            array(self::DEFENSIVE, 'course-of-action'),
        'mitre-d3fend' => array(self::DEFENSIVE, 'course-of-action'),
        'engage-framework' =>
            array(self::DEFENSIVE, 'course-of-action'),
        'sparta-mitigations' =>
            array(self::DEFENSIVE, 'course-of-action'),
        'cyfun-control-catalogue-2023' =>
            array(self::DEFENSIVE, 'control'),
        'cyfun-assurance-requirements-2023' =>
            array(self::DEFENSIVE, 'control'),

        // How it would be caught.
        'sigma-rules' => array(self::DETECTION, 'rule'),
        'agent-threat-rules' => array(self::DETECTION, 'rule'),
        'disarm-detections' => array(self::DETECTION, 'strategy'),
        'x-mitre-detection-strategy' =>
            array(self::DETECTION, 'strategy'),
        'x-mitre-analytic' => array(self::DETECTION, 'strategy'),
        'mitre-data-source' => array(self::DETECTION, 'data-source'),
        'mitre-data-component' =>
            array(self::DETECTION, 'data-source'),

        // What is exposed.
        'mitre-ics-assets' => array(self::TARGETING, 'asset'),
        'mitre-ics-levels' => array(self::TARGETING, 'asset'),
        'it-infrastructure-equipment' =>
            array(self::TARGETING, 'asset'),
        'operating-system' => array(self::TARGETING, 'platform'),
        'online-service' => array(self::TARGETING, 'service'),

        // Near-misses, recorded so they are not mistaken for
        // oversights.
        'producer' => array(self::CONTEXT, 'producer'),
        'intelligence-agency' =>
            array(self::CONTEXT, 'organisation'),
        'software-vendor' => array(self::CONTEXT, 'organisation'),
        'china-defence-universities' =>
            array(self::CONTEXT, 'organisation'),
        'entity' => array(self::CONTEXT, 'organisation'),
        'branded-vulnerability' =>
            array(self::CONTEXT, 'vulnerability'),
        'references' => array(self::CONTEXT, 'reference'),
    );

    /**
     * @param string $galaxyType `galaxies.type`
     * @return array|null category and kind, or null if unrecognised
     */
    public static function of($galaxyType)
    {
        $galaxyType = (string)$galaxyType;
        if (!isset(self::$table[$galaxyType])) {
            return null;
        }
        return array(
            'category' => self::$table[$galaxyType][0],
            'kind' => self::$table[$galaxyType][1],
        );
    }

    /**
     * @param string $galaxyType
     * @return bool False for an unrecognised galaxy, which is the
     *     answer for every locally created one until the category
     *     lives on the galaxy itself.
     */
    public static function isNamedThreat($galaxyType)
    {
        $found = self::of($galaxyType);
        return $found !== null
            && $found['category'] === self::NAMED_THREAT;
    }

    /**
     * @param string $galaxyType
     * @return string|null `actor`, `campaign`, `malware`, `tool` for a
     *     named threat; the category's own kind otherwise
     */
    public static function kindOf($galaxyType)
    {
        $found = self::of($galaxyType);
        return $found === null ? null : $found['kind'];
    }

    /**
     * Every galaxy type in one category, for a caller that has to ask
     * in SQL rather than over rows already read.
     *
     * @param string $category One of the constants above
     * @return array Galaxy types
     */
    public static function typesIn($category)
    {
        $types = array();
        foreach (self::$table as $type => $pair) {
            if ($pair[0] === $category) {
                $types[] = $type;
            }
        }
        return $types;
    }
}
