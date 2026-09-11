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
 * **Every fact above is required; none of them was required to be a
 * paragraph.** The card shipped with fourteen lines of explanation
 * around ten lines of data — a TTL line that printed the engine's whole
 * resolution (`shortest rule, over ip-src 90, text 180`), two sentences
 * on what the neighbouring chart does, one on what does *not* move it,
 * and `lower bound`, `encoding date` and `first_seen` where plain words
 * exist. This pass keeps the facts and moves the second half of each to
 * its element's `title`: the per-type day counts, the clock setting's
 * name, the false-positive rule, what an over-correlating value is.
 * Hovers, not sentences, so §3.4's honest state survives at a third of
 * the height. The labels this card invented — `new organisation`,
 * `independent sighting` — carry their definition the same way, which
 * they never did in any form.
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
/*
 * Every label above is a term of art this card invented, and a reader
 * meeting `new organisation` in a list of dates has no way to tell it
 * from `independent sighting`. The sentence that separates them is a
 * hover rather than a row of its own, because six rows of definitions
 * beside six rows of data is the density this pass is removing.
 */
$kindHints = array(
    'org_joined' => __('An organisation that had not reported this'
        . ' value before now has.'),
    'foreign_sighting' => __('A sighting from an organisation other'
        . ' than the one that reported the value.'),
    'sighting' => __('Somebody reported seeing this value.'),
    'occurrence' => __('This value appeared in an event.'),
    'fallback' => __('The value\'s own date, with nothing confirming'
        . ' it.'),
);
$stateHints = array(
    'current' => __('Inside its lifetime.'),
    'aging' => __('Still inside its lifetime, but past the point'
        . ' where this profile stops calling it current.'),
    'expired' => __('Past its lifetime. Re-check it before'
        . ' acting on it.'),
    'uncertain' => __('The age below is a minimum, not a'
        . ' measurement.'),
);

$state = $relevance['state'];
$clock = $relevance['clock'];
$ttl = $relevance['ttl'];
?>
<div class="card shadow-sm mb-3 vp-panel vp-aside"
     style="--vp-panel-color: var(--correlation);">

    <div class="vp-aside-head">
        <i class="fas fa-hourglass-half"
           title="<?= h(__('How long this value counts as current after'
               . ' the last time somebody confirmed it.')) ?>"
           style="color: var(--correlation);"></i>
        <span class="vp-aside-title"><?= __('Lifetime') ?></span>
        <?php if ($state !== null): ?>
            <?php
            /*
             * `TTL 90 days` twice over: an acronym a reader has to
             * expand, in front of a number the card then spends three
             * lines accounting for. The whole length, said plainly, is
             * what the days-left figure beside it is measured against.
             */
            ?>
            <span class="vp-aside-meta"
                  title="<?= h(__('The full lifetime. The days left'
                      . ' below are what is unused of it.')) ?>">
                <?= h(sprintf(
                    __('%s days in total'),
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
                    <span class="vp-shelf-state"<?=
                        isset($stateHints[$state])
                            ? ' title="' . h($stateHints[$state]) . '"'
                            : '' ?>>
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

                <?php
                /*
                 * The assumed days are drawn, not just stated. A track
                 * whose fill silently included 30 days nobody measured
                 * is the failure this replaced in a new costume — so
                 * the assumed stretch is its own hatched segment,
                 * between what the rows support and what is left.
                 */
                $assumed = (int)($relevance['assumed_days'] ?? 0);
                /*
                 * In the track's own coordinates, which are runway
                 * remaining and not elapsed days: the assumption ate
                 * the stretch between the runway as drawn and the
                 * runway the record alone supports, so it sits
                 * immediately right of the fill and ends where the bar
                 * would have ended without it.
                 */
                $runwayPct = (int)round($relevance['runway'] * 100);
                $recordedPct = (int)round(
                    ($relevance['recorded_runway'] ?? $relevance['runway'])
                    * 100
                );
                $assumedPct = max(0, $recordedPct - $runwayPct);
                ?>
                <div class="vp-shelf-track"
                     title="<?= h($assumed > 0
                         ? sprintf(
                             __('%1$s of %2$s days used: %3$s on the'
                                 . ' record, %4$s assumed'),
                             $relevance['elapsed_days'],
                             $ttl['days'],
                             $relevance['recorded_days'],
                             $assumed
                         )
                         : sprintf(
                             __('%1$s of %2$s days used'),
                             $relevance['elapsed_days'],
                             $ttl['days']
                         )) ?>">
                    <span class="vp-shelf-fill"
                          style="width: <?=
                              (int)round(
                                  $relevance['runway'] * 100
                              ) ?>%;"></span>
                    <?php if ($assumed > 0): ?>
                        <span class="vp-shelf-assumed"
                              title="<?= h(sprintf(
                                  __n(
                                      '%s day the profile assumes,'
                                          . ' because nothing records'
                                          . ' when this was seen',
                                      '%s days the profile assumes,'
                                          . ' because nothing records'
                                          . ' when this was seen',
                                      $assumed
                                  ),
                                  $assumed
                              )) ?>"
                              style="left: <?= $runwayPct ?>%; width: <?=
                                  $assumedPct ?>%;"></span>
                    <?php endif; ?>
                    <span class="vp-shelf-mark"
                          title="<?= h(__('where it stops counting as current')) ?>"
                          style="left: <?=
                              (int)round(
                                  $relevance['aging_fraction'] * 100
                              ) ?>%;"></span>
                </div>

                <?php if (!empty($relevance['uncertain'])): ?>
                    <?php
                    /*
                     * **The assumption, in the words of an assumption.**
                     * This block used to print a measured lag — MISP's
                     * `Event.date` against a row-modification timestamp
                     * — which was not a measurement of anything. What
                     * it says now is what the profile is doing and by
                     * how much, because a number the reader cannot see
                     * is a number they cannot argue with, and this one
                     * moves the days-left figure directly above it.
                     */
                    ?>
                    <div class="vp-shelf-why">
                        <?php
                        /*
                         * `uncertain_note` is not repeated here. With
                         * one reason left it says the same sentence
                         * back, and the card printed *nothing records
                         * when this value was seen (no occurrence
                         * records when it was first seen)*. The note
                         * still serves the two panels that have room
                         * for nothing longer.
                         */
                        ?>
                        <?= h(__('Nothing records when this value was'
                            . ' seen — only when its rows were last'
                            . ' written.')) ?>
                        <?php if ($assumed > 0): ?>
                            <?= h(sprintf(
                                __n(
                                    'So the %1$s profile reads it as'
                                        . ' %2$s day older than its'
                                        . ' %3$s days on the record —'
                                        . ' the hatched part of the bar'
                                        . ' above.',
                                    'So the %1$s profile reads it as'
                                        . ' %2$s days older than its'
                                        . ' %3$s days on the record —'
                                        . ' the hatched part of the bar'
                                        . ' above.',
                                    $assumed
                                ),
                                $relevance['profile'] ?? __('active'),
                                $assumed,
                                $relevance['recorded_days']
                            )) ?>
                            <?= h(__('That is an assumption, not a'
                                . ' reading: it makes the value count'
                                . ' as old sooner, and never changes'
                                . ' the date its lifetime ends.')) ?>
                        <?php else: ?>
                            <?= h(__('Its age is a minimum, not a'
                                . ' measurement.')) ?>
                        <?php endif; ?>
                        <?php if (!empty($relevance['assumed_capped'])): ?>
                            <?= h(sprintf(
                                __('The profile would have assumed %s'
                                    . ' days; the rest is not applied,'
                                    . ' because an assumption may never'
                                    . ' expire a value on its own.'),
                                $relevance['assumed_setting']
                            )) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($clock['fallback'])): ?>
                    <div class="vp-shelf-why"
                         title="<?= h(__('One organisation reporting a'
                             . ' value is the claim, not its'
                             . ' confirmation.')) ?>">
                        <?= h(__('Nobody else has confirmed this value,'
                            . ' so the clock runs from the day it was'
                            . ' last added instead.')) ?>
                    </div>
                <?php endif; ?>

                <?php
                /*
                 * `clock: last independent corroboration` said the
                 * setting's name and nothing about what it does, next
                 * to a kind it usually repeats word for word. It is the
                 * line's hover now — a reader who wants to know which
                 * profile knob produced this date can still find it,
                 * and one who does not is left with a plain sentence.
                 */
                $clockTitle = sprintf(
                    __('This profile resets the clock on the %s.'),
                    $clockLabels[$clock['setting']] ?? $clock['setting']
                );
                ?>
                <div class="vp-shelf-prov" title="<?= h($clockTitle) ?>">
                    <?php if (!empty($clock['fallback'])): ?>
                        <?= h(sprintf(
                            __('Added %1$s by %2$s'),
                            date('Y-m-d', $clock['at']),
                            $clock['by'] === null
                                ? __('an unnamed organisation')
                                : $clock['by']
                        )) ?>
                    <?php else: ?>
                        <?= h(sprintf(
                            __('Last confirmed %1$s by %2$s (%3$s)'),
                            date('Y-m-d', $clock['at']),
                            $clock['by'] === null
                                ? __('an unnamed organisation')
                                : $clock['by'],
                            $kindLabels[$clock['kind']] ?? $clock['kind']
                        )) ?>
                    <?php endif; ?>
                </div>

                <?php
                /*
                 * This line read `TTL 90 days from ip-dst, short bucket
                 * · shortest rule, over ip-src 90, text 180` — the
                 * whole resolution, in the order the engine computed
                 * it. The facts §3.4 requires are *which type supplied
                 * the number* and *that the others disagreed*; the
                 * per-type list proving it is the hover, because a
                 * reader who has to parse four `type days` pairs to
                 * learn `they disagree` has been handed the engine's
                 * working rather than its answer.
                 */
                $bucketNames = array(
                    'short' => __('short'),
                    'medium' => __('medium'),
                    'long' => __('long'),
                    'very_long' => __('very long'),
                );
                $ruleWords = array(
                    'shortest' => __('the shortest'),
                    'longest' => __('the longest'),
                    'most_common' => __('the most common'),
                );
                $ttlTitle = null;
                if ($ttl['type'] === null) {
                    $ttlLine = __('Nothing here has a type to take a'
                        . ' lifetime from, so this is the profile\'s'
                        . ' default.');
                } else {
                    if (($ttl['from'] ?? null) === 'bucket'
                        && isset($bucketNames[$ttl['bucket']])
                    ) {
                        $ttlLine = sprintf(
                            __('Set for %1$s, in the %2$s bucket'),
                            $ttl['type'],
                            $bucketNames[$ttl['bucket']]
                        );
                    } elseif (($ttl['from'] ?? null) === 'override') {
                        $ttlLine = sprintf(
                            __('Set for %s, as its own override'),
                            $ttl['type']
                        );
                    } else {
                        $ttlLine = sprintf(
                            __('No lifetime set for %s, so this is'
                                . ' the profile\'s default'),
                            $ttl['type']
                        );
                    }
                    if (!empty($ttl['spread'])) {
                        $days = array_column($ttl['candidates'], 'days');
                        $ttlLine .= sprintf(
                            __(' — %1$s of its %2$s types, which run'
                                . ' %3$s to %4$s days.'),
                            $ruleWords[$ttl['rule']] ?? $ttl['rule'],
                            count($ttl['candidates']),
                            min($days),
                            max($days)
                        );
                        $each = array();
                        foreach ($ttl['candidates'] as $candidate) {
                            $each[] = sprintf(
                                __('%1$s: %2$s days'),
                                $candidate['type'],
                                $candidate['days']
                            );
                        }
                        $ttlTitle = implode(__(' · '), $each);
                    } else {
                        $ttlLine .= __('.');
                    }
                }
                ?>
                <div class="vp-shelf-prov"<?= $ttlTitle === null
                    ? ''
                    : ' title="' . h($ttlTitle) . '"' ?>>
                    <?= h($ttlLine) ?>
                </div>

                <div class="vp-shelf-prov">
                    <?= h(sprintf(
                        __('Expires %s'),
                        date('Y-m-d', $relevance['expires_at'])
                    )) ?>
                </div>

                <?php if (empty($clock['rows_read'])): ?>
                    <div class="vp-shelf-why"
                         title="<?= h(__('MISP flags a value as'
                             . ' over-correlating when it appears in so'
                             . ' many events that reading them all'
                             . ' would cost more than the answer is'
                             . ' worth.')) ?>">
                        <?= h(__('This value is too common for MISP to'
                            . ' correlate, so its individual reports'
                            . ' were not read. A sighting could be newer'
                            . ' than the date above.')) ?>
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
                                <span class="vp-shelf-event-kind"<?=
                                    isset($kindHints[$event['kind']])
                                        ? ' title="' . h(
                                            $kindHints[$event['kind']]
                                        ) . '"'
                                        : '' ?>>
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
                                    '%s earlier confirmation is not'
                                        . ' listed.',
                                    '%s earlier confirmations are not'
                                        . ' listed.',
                                    count($events) - count($shown)
                                ),
                                count($events) - count($shown)
                            )) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php
            /*
             * Four lines pointing at a chart in the next column, two of
             * them spent on what *does not* move it. Kept, because the
             * chart and this card are the same quantity drawn twice and
             * a reader who misses that reads them as disagreeing — but
             * at the length of a caption, with the false-positive rule
             * on the hover rather than in its own sentence.
             */
            ?>
            <p class="vp-aside-note"
               title="<?= h(__('A false positive is drawn on that chart'
                   . ' too. A report arguing against a value never'
                   . ' extends its lifetime.')) ?>">
                <?= h(__(
                    'The chart plots this bar over time: it steps back'
                    . ' up on each of the dates listed here, and never'
                    . ' on a false positive.'
                )) ?>
            </p>

            <?php if (!empty($relevance['sightings_excluded'])): ?>
                <p class="vp-aside-note"
                   title="<?= h(__('The profile excludes sightings an'
                       . ' organisation files on its own report.')) ?>">
                    <?= h(sprintf(
                        __n(
                            'One self-sighting does not count as a'
                                . ' confirmation here.',
                            '%s self-sightings do not count as'
                                . ' confirmations here.',
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
                <i class="fas fa-user-shield"
                   title="<?= h(__('What your account is allowed to'
                       . ' see')) ?>"></i>
                <span><?= h($notes['policy']) ?></span>
            </div>

            <?php if ($relevance['profile'] !== null): ?>
                <p class="vp-aside-note"
                   title="<?= h(__('The clock, the curve and the day'
                       . ' counts per type are all profile'
                       . ' settings.')) ?>">
                    <?= h(sprintf(
                        __('Every number here is a setting of the %s'
                            . ' profile, which an analyst can edit.'),
                        $relevance['profile']
                    )) ?>
                </p>
            <?php endif; ?>

        <?php endif; ?>

    </div>

</div>
