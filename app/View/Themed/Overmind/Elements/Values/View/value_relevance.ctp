<?php
/**
 * How fresh this value is, and what made it so — as a rail card.
 *
 * The decay card's replacement (`prd/analyst-profile/06-staleness.md`
 * §4). That card showed one bar per decaying model, each a score MISP
 * computes largely from the value's tags multiplied by a time factor —
 * the same tags the assessment's quality ledger scores directly, with a
 * per-row audit trail and none of the double counting. This card keeps
 * the time factor and drops the base score, so what is on screen is one
 * quantity a reader can act on: **how much shelf life is left, measured
 * from the last time somebody new confirmed the value.**
 *
 * Everything the number is made of is on the card, because a TTL with
 * no provenance is how a reader concludes the page is wrong about a
 * value they know well (§3.4): the clock's date and who supplied it,
 * the type that supplied the TTL and whether the value's other types
 * would have given a different one, and the rule in force. The
 * corroboration timeline underneath is the aggregation rule's second
 * half — naming what holds the number, which is what phase 23 decided
 * for the decay maximum and this inherits (§4.1).
 *
 * Three honest states it must never render as a blank or a zero:
 *
 *   - **nothing has ever corroborated it.** The majority case in
 *     production — one organisation, no sightings — where the clock
 *     falls back to the value's own encoding date and says so.
 *   - **the timeline is not trustworthy.** No `first_seen`, or an
 *     encoding date that lags the event's own dates, so the elapsed
 *     time is measured from something that is not an observation
 *     (§3.6).
 *   - **the sighting half could not be read**, because MISP flagged the
 *     value as over-correlating and the budget left its rows unfetched.
 *
 * Lazily loaded from ValuesController::viewRelevance.
 *
 * @var array $valueProfile
 * @var string $valueB64
 */
App::uses('ValueRelevanceTool', 'Tools');
$relevance = $valueProfile['relevance'];
$sightings = $valueProfile['sightings'];
$notes = $valueProfile['sighting_notes'];

$clockLabels = array(
    'last_independent_corroboration' => __('last independent'
        . ' corroboration'),
    'last_sighting' => __('last sighting'),
    'last_occurrence' => __('last occurrence'),
);
$kindLabels = array(
    'org_joined' => __('new organisation'),
    'foreign_sighting' => __('independent sighting'),
    'sighting' => __('sighting'),
    'occurrence' => __('occurrence'),
    'fallback' => __('own encoding date'),
);

$state = $relevance['state'];
$clock = $relevance['clock'];
$ttl = $relevance['ttl'];
?>
<div class="card shadow-sm mb-3 vp-panel vp-aside"
     style="--vp-panel-color: var(--correlation);">

    <div class="vp-aside-head">
        <i class="fas fa-hourglass-half"
           style="color: var(--correlation);"></i>
        <span class="vp-aside-title"><?= __('Shelf life') ?></span>
        <?php if ($state !== null): ?>
            <span class="vp-aside-meta">
                <?= h(sprintf(
                    __('TTL %s days'),
                    $ttl['days']
                )) ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="p-3 d-flex flex-column gap-3">

        <?php if ($state === null): ?>
            <?php
            /*
             * No clock rather than an expired one. A value this reader
             * holds no occurrence of has no date to measure from, and
             * printing `expired` would be inventing an assertion in
             * order to age it — the same distinction the decay card
             * drew between *no model* and *a score of nothing*.
             */
            ?>
            <div class="vp-empty vp-empty-inline">
                <i class="fas fa-hourglass-half"></i>
                <span><?= __('Nothing is recorded for this value, so'
                    . ' there is no clock to run.') ?></span>
            </div>
        <?php else: ?>

            <div class="vp-shelf vp-shelf-<?= h($state) ?>">

                <div class="vp-shelf-head">
                    <span class="vp-shelf-state">
                        <?= h(ValueRelevanceTool::stateLabel($state)) ?>
                    </span>
                    <span class="vp-shelf-days">
                        <?php if ($relevance['runway_days'] > 0): ?>
                            <?= h(sprintf(
                                __n(
                                    '%s day left',
                                    '%s days left',
                                    $relevance['runway_days']
                                ),
                                $relevance['runway_days']
                            )) ?>
                        <?php elseif ($relevance['runway_days'] === 0): ?>
                            <?= __('expires today') ?>
                        <?php else: ?>
                            <?= h(sprintf(
                                __n(
                                    '%s day over',
                                    '%s days over',
                                    -$relevance['runway_days']
                                ),
                                -$relevance['runway_days']
                            )) ?>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="vp-shelf-track"
                     title="<?= h(sprintf(
                         __('%1$s days elapsed of a %2$s day TTL'),
                         $relevance['elapsed_days'],
                         $ttl['days']
                     )) ?>">
                    <span class="vp-shelf-fill"
                          style="width: <?=
                              (int)round(
                                  $relevance['runway'] * 100
                              ) ?>%;"></span>
                    <span class="vp-shelf-mark"
                          title="<?= h(__('where aging begins')) ?>"
                          style="left: <?=
                              (int)round(
                                  $relevance['aging_fraction'] * 100
                              ) ?>%;"></span>
                </div>

                <?php if (!empty($relevance['uncertain'])): ?>
                    <?php
                    /*
                     * The measurement that tripped, never a bare
                     * *uncertain*. This is the example that forced the
                     * three-axis model: a phishing URL encoded two
                     * months after the incident, where a page reading
                     * the encoding date as an observation date says
                     * `current` about infrastructure that died in June.
                     * The uncertainty has somewhere to go now, and this
                     * is it.
                     */
                    ?>
                    <div class="vp-shelf-why">
                        <?= h(sprintf(
                            __('The timeline is uncertain: %s. The'
                                . ' elapsed time above is measured from'
                                . ' an encoding date, which is later'
                                . ' than whatever it stands for — so it'
                                . ' is a lower bound.'),
                            $relevance['uncertain_note']
                        )) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($clock['fallback'])): ?>
                    <div class="vp-shelf-why">
                        <?= h(__('Nothing has independently corroborated'
                            . ' this value — one organisation reporting'
                            . ' it is the claim, not its confirmation.'
                            . ' The clock runs from the value\'s own'
                            . ' most recent encoding instead.')) ?>
                    </div>
                <?php endif; ?>

                <div class="vp-shelf-prov">
                    <?php if (!empty($clock['fallback'])): ?>
                        <?= h(sprintf(
                            __('Encoded %1$s by %2$s'),
                            date('Y-m-d', $clock['at']),
                            $clock['by'] === null
                                ? __('an unnamed organisation')
                                : $clock['by']
                        )) ?>
                    <?php else: ?>
                        <?= h(sprintf(
                            __('Last corroborated %1$s by %2$s — %3$s'),
                            date('Y-m-d', $clock['at']),
                            $clock['by'] === null
                                ? __('an unnamed organisation')
                                : $clock['by'],
                            $kindLabels[$clock['kind']] ?? $clock['kind']
                        )) ?>
                    <?php endif; ?>
                    ·
                    <?= h(sprintf(
                        __('clock: %s'),
                        $clockLabels[$clock['setting']]
                            ?? $clock['setting']
                    )) ?>
                </div>

                <div class="vp-shelf-prov">
                    <?php if ($ttl['type'] === null): ?>
                        <?= h(sprintf(
                            __('TTL %s days, the profile\'s default —'
                                . ' this value has no type to take one'
                                . ' from'),
                            $ttl['days']
                        )) ?>
                    <?php else: ?>
                        <?php
                        /*
                         * Where the number came from (D18). A bucket is
                         * named because *730 days, very long* is a
                         * setting a reader can find in the editor,
                         * where a bare 730 is a number they then have
                         * to go and look up. An override says so
                         * because it is the thing the buckets could not
                         * express.
                         */
                        $bucketNames = array(
                            'short' => __('short'),
                            'medium' => __('medium'),
                            'long' => __('long'),
                            'very_long' => __('very long'),
                        );
                        if (($ttl['from'] ?? null) === 'bucket'
                            && isset($bucketNames[$ttl['bucket']])
                        ) {
                            $provenance = sprintf(
                                __(', %s bucket'),
                                $bucketNames[$ttl['bucket']]
                            );
                        } elseif (($ttl['from'] ?? null) === 'override') {
                            $provenance = __(', its own override');
                        } else {
                            $provenance = __(', which the profile does'
                                . ' not name — so this is its default');
                        }
                        ?>
                        <?= h(sprintf(
                            __('TTL %1$s days from %2$s%3$s'),
                            $ttl['days'],
                            $ttl['type'],
                            $provenance
                        )) ?>
                        <?php if (!empty($ttl['spread'])): ?>
                            <?php
                            /*
                             * §3.4's honest state. A value occurring as
                             * both `ip-src` and `ip-dst` has two TTLs
                             * and the page picks one; saying which, and
                             * what the others were, is the difference
                             * between a number and a judgement a reader
                             * can argue with.
                             */
                            $others = array();
                            foreach ($ttl['candidates'] as $candidate) {
                                if ($candidate['type'] === $ttl['type']) {
                                    continue;
                                }
                                $others[] = sprintf(
                                    '%s %s',
                                    $candidate['type'],
                                    $candidate['days']
                                );
                            }
                            ?>
                            ·
                            <?= h(sprintf(
                                __('%1$s rule, over %2$s'),
                                $ttl['rule'],
                                implode(__(', '), $others)
                            )) ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="vp-shelf-prov">
                    <?= h(sprintf(
                        __('Expires %s'),
                        date('Y-m-d', $relevance['expires_at'])
                    )) ?>
                </div>

                <?php if (empty($clock['rows_read'])): ?>
                    <div class="vp-shelf-why">
                        <?= h(__('MISP has flagged this value as too'
                            . ' common to correlate, so its individual'
                            . ' reports were not fetched. The clock'
                            . ' above is the organisation half only —'
                            . ' an independent sighting could be more'
                            . ' recent than it says.')) ?>
                    </div>
                <?php endif; ?>

            </div>

            <?php if (!empty($clock['events'])): ?>
                <?php
                /*
                 * The corroboration timeline, newest first and capped:
                 * a value corroborated forty times does not need forty
                 * rows to make the point, and the newest one is the
                 * clock. The cap is a cap and not a permission, so it
                 * says how many it left out (§14.6).
                 */
                $events = array_reverse($clock['events']);
                $shown = array_slice($events, 0, 6);
                ?>
                <div>
                    <div class="vp-shelf-prov">
                        <?= h(__('What has reset this clock')) ?>
                    </div>
                    <ul class="vp-shelf-events">
                        <?php foreach ($shown as $i => $event): ?>
                            <li class="vp-shelf-event<?= $i === 0
                                ? ' vp-shelf-event-held'
                                : '' ?>">
                                <span class="vp-shelf-event-date">
                                    <?= h(date('Y-m-d', $event['at'])) ?>
                                </span>
                                <span class="vp-shelf-event-who">
                                    <?= h(empty($event['by'])
                                        ? __('unnamed')
                                        : $event['by']) ?>
                                </span>
                                <span class="vp-shelf-event-kind">
                                    <?= h($kindLabels[$event['kind']]
                                        ?? $event['kind']) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (count($events) > count($shown)): ?>
                        <div class="vp-shelf-prov">
                            <?= h(sprintf(
                                __n(
                                    '%s earlier corroboration is not'
                                        . ' listed.',
                                    '%s earlier corroborations are not'
                                        . ' listed.',
                                    count($events) - count($shown)
                                ),
                                count($events) - count($shown)
                            )) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p class="vp-aside-note">
                <?= h(__(
                    'The line on the chart is this bar over time: its'
                    . ' last point is the number above, and it steps'
                    . ' back up on each of the dates listed here.'
                )) ?>
                <?= h(__(
                    'A false positive or an expiration is drawn on that'
                    . ' chart and resets nothing — a report arguing'
                    . ' against the value cannot extend its shelf life.'
                )) ?>
            </p>

            <?php if (!empty($relevance['sightings_excluded'])): ?>
                <p class="vp-aside-note">
                    <?= h(sprintf(
                        __n(
                            'One self-sighting is not counted as'
                                . ' corroboration, by this profile\'s'
                                . ' own exclusion.',
                            '%s self-sightings are not counted as'
                                . ' corroboration, by this profile\'s'
                                . ' own exclusion.',
                            $relevance['sightings_excluded']
                        ),
                        $relevance['sightings_excluded']
                    )) ?>
                </p>
            <?php endif; ?>

            <?php
            /*
             * The instance's sighting policy, which is what
             * `05-exclusions.md` §7.2 leaves standing when it removes
             * every per-value statement about a reader's permissions:
             * this says what the *instance* is configured to do, on
             * every value, and reveals nothing about the one on screen.
             */
            ?>
            <div class="vp-acl-note vp-acl-note-band">
                <i class="fas fa-user-shield"></i>
                <span><?= h($notes['policy']) ?></span>
            </div>

            <?php if ($relevance['profile'] !== null): ?>
                <p class="vp-aside-note">
                    <?= h(sprintf(
                        __('Every number here comes from the %s profile:'
                            . ' the clock, the curve and the per-type'
                            . ' TTL are all settings an analyst can'
                            . ' edit.'),
                        $relevance['profile']
                    )) ?>
                </p>
            <?php endif; ?>

        <?php endif; ?>

    </div>

</div>
