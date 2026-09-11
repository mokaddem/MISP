<?php

/**
 * The assessment's second axis: does what the record asserts still
 * matter operationally, today?
 *
 * Phase 5 of the Analyst Profile (`prd/analyst-profile/06-staleness.md`),
 * reworked as the relevance axis by D11
 * (`prd/analyst-profile/12-assessment.md` §2.2). It replaces the page's
 * reading of MISP's decaying models, which answered *how bad is this*
 * and *how stale is this* in one number while the quality ledger
 * already answers the first from more evidence with an audit trail
 * (D7). What is taken is the time factor; what is left is the base
 * score.
 *
 * **No ledger row, ever.** Relevance is its own axis, so nothing here
 * returns points and nothing here can move the lean or the quality sum.
 * That is not a style rule: the first draft emitted staleness as
 * threat-signed points, under which silence promoted a value to
 * definite BENIGN and a freshly-confirmed `8.8.8.8` fell *out* of it
 * (`review-2026-09-02.md` A6). An axis that never touches the other
 * two cannot do either.
 *
 * **Pure and static, and it takes no `$user`** (`00-contract.md` §14.5).
 * Every fact it reads is on the context the owning model built, already
 * scoped to the viewer; it issues no query and resolves no permission.
 *
 * ## The four states
 *
 * ```
 * current              runway ≥ aging_fraction
 * aging                0 < runway < aging_fraction
 * expired              elapsed ≥ ttl
 * timeline uncertain   the clock itself is not trustworthy (§3.6)
 * ```
 *
 * The discontinuity at the boundary is deliberate: **expiry is an
 * event, not a gradient.** A value one day past its TTL reads
 * differently from one a day before it, because that is what a TTL
 * means and it is what makes the relevance falsifiability line worth
 * stating.
 *
 * **Uncertainty is a flag as well as a state**, and the order the two
 * resolve in is an argument rather than a preference. An encoding date
 * is *later* than the observation it stands for, so elapsed-time
 * measured from it is a **lower bound** on the true elapsed time. A
 * lower bound already past the TTL is past it on any honest reading, so
 * `expired` survives an untrustworthy clock; `current` and `aging` do
 * not, and degrade to `timeline uncertain`. Both facts travel, so the
 * page can read *"expired · timeline uncertain"* — which is exactly
 * what `12-assessment.md` §3 says the late-encoded phishing URL should
 * say.
 */
class ValueRelevanceTool
{
    /**
     * How far back a runway series may run.
     *
     * Inherited from the retired `ValueDecayTool`, argument intact:
     * the runway is the only dense series in the sightings payload — a
     * count can be sparse because most days have none, a shelf life
     * cannot because every day has one — so this is the number that
     * bounds the fragment. `0.0.0.0` first appears in 2015, which is
     * 3,948 daily samples for a value whose reports are all in the last
     * two years.
     *
     * What did **not** come across is that class's `OCCURRENCE_CAP`.
     * The decay curve was an envelope over one curve per occurrence, so
     * it needed a cap and the cap needed an argument about which
     * occurrences could hold the maximum. A runway is computed from
     * aggregates and a list of corroboration dates, so its cost does
     * not track the occurrence count at all and there is nothing to cap.
     */
    const SPAN_CAP_DAYS = 1095;

    /** Clock settings, in the order the editor should offer them. */
    const CLOCKS = array(
        'last_independent_corroboration',
        'last_sighting',
        'last_occurrence',
    );

    /** `type_rule` settings. */
    const TYPE_RULES = array('shortest', 'longest', 'most_common');

    /** The states the axis can report, in clock order. */
    const STATES = array('current', 'aging', 'expired', 'uncertain');

    /**
     * The shelf-life buckets a type may be assigned to (D18).
     *
     * Four, not three, and the reason is arithmetic rather than taste:
     * the shipped table uses five distinct values (60, 90, 120, 365,
     * 730), and three buckets cannot hold them without moving `url`
     * off 60 and the whole 120-day group onto some other number —
     * which would change how long real values stay relevant on every
     * instance running the default. Four plus one override reproduces
     * the shipped table exactly.
     */
    const BUCKETS = array('short', 'medium', 'long', 'very_long');

    /** What each bucket is worth when a profile does not say. */
    const BUCKET_DAYS = array(
        'short' => 90,
        'medium' => 120,
        'long' => 365,
        'very_long' => 730,
    );

    /** Defaults for a profile that names none, or for no profile. */
    const DEFAULTS = array(
        'clock' => 'last_independent_corroboration',
        // A float, so that `section()` returns one type whether the
        // profile named the speed or not: `1 !== 1.0` and a caller
        // comparing the resolved section against a default should not
        // have to know which branch produced it.
        'decay_speed' => 1.0,
        'type_rule' => 'shortest',
        'aging_fraction' => 0.33,
        'lag_uncertain_days' => 30,
        'ttl_default' => 180,
    );

    /**
     * The relevance axis for one value.
     *
     * @param array $context From the context builder — `now`, `types`,
     *                       `occurrences`, `orgs`, `temporal` and
     *                       `corroboration`
     * @param array|null $profile An `AnalystProfile` row, unwrapped, or
     *                            null when the section is absent
     * @return array
     */
    public static function relevanceFor(array $context, $profile = null)
    {
        $section = self::section($profile);
        $now = isset($context['now']) ? (int)$context['now'] : time();
        $ttl = self::ttlFor($context, $section);
        $clock = self::clockFor($context, $section);
        $precision = self::precisionFor($context, $section);

        /*
         * No occurrence this viewer can see is not a stale value and
         * not a fresh one: there is no clock to run and no type to take
         * a TTL from. The lean's own first rule says `none` here, and
         * this axis says the same thing by having no state — a page
         * that printed `expired` for a value it holds nothing about
         * would be inventing an assertion in order to age it.
         */
        if ($clock['at'] === null) {
            return array(
                'state' => null,
                'reason' => 'no_record',
                'clock' => $clock,
                'ttl' => $ttl,
                'precision' => $precision,
                'runway' => null,
                'elapsed_days' => null,
                'runway_days' => null,
                'expires_at' => null,
                'uncertain' => false,
                'uncertain_note' => null,
            );
        }

        $elapsedDays = self::daysBetween($clock['at'], $now);
        $runway = self::runway(
            $elapsedDays,
            $ttl['days'],
            $section['decay_speed']
        );
        $state = self::stateFor(
            $elapsedDays,
            $ttl['days'],
            $runway,
            $section['aging_fraction'],
            $precision['uncertain']
        );

        return array(
            'state' => $state,
            'reason' => null,
            'clock' => $clock,
            'ttl' => $ttl,
            'precision' => $precision,
            'runway' => $runway,
            'elapsed_days' => $elapsedDays,
            'runway_days' => $ttl['days'] - $elapsedDays,
            'expires_at' => $clock['at'] + ($ttl['days'] * 86400),
            'uncertain' => $precision['uncertain'],
            'uncertain_note' => $precision['note'],
            'aging_fraction' => $section['aging_fraction'],
            'decay_speed' => $section['decay_speed'],
        );
    }

    /**
     * The curve, lifted rather than called.
     *
     * `DecayingModelsFormulas/Polynomial.php:17` is
     * `base × (1 − (elapsed / lifetime)^(1 / decay_speed))`, clamped at
     * zero, and at `decay_speed = 1` the exponent is 1 and this is
     * linear (D8). The two are not alternatives — one contains the
     * other — which is why *"polynomial or linear"* was a false choice.
     *
     * §3.1 recommends lifting the one-line expression over passing a
     * synthetic `DecayingModel` array through a class that expects a
     * real one, and the recommendation stands for a reason this phase
     * proved: `computeScore()` takes `($model, $attribute, $base,
     * $elapsed)` and the page now has no model and no attribute to give
     * it. Passing two empty arrays to get a fraction out is coupling
     * with nothing on the other end of it.
     *
     * With `base = 1` the result *is* the fraction of shelf life left:
     * 1 at zero elapsed, 0 at the TTL.
     *
     * @param float $elapsedDays
     * @param int $ttlDays
     * @param float $speed
     * @return float In `[0, 1]`
     */
    public static function runway($elapsedDays, $ttlDays, $speed = 1)
    {
        $ttlDays = (float)$ttlDays;
        if ($ttlDays <= 0) {
            // A TTL of nothing expires on arrival. Not a division.
            return 0.0;
        }
        $speed = (float)$speed;
        if ($speed <= 0) {
            $speed = 1.0;
        }
        $ratio = (float)$elapsedDays / $ttlDays;
        if ($ratio <= 0) {
            return 1.0;
        }
        if ($ratio >= 1) {
            return 0.0;
        }
        return max(0.0, min(1.0, 1 - pow($ratio, 1 / $speed)));
    }

    /**
     * Which state a runway and an elapsed time make.
     *
     * @param float $elapsedDays
     * @param int $ttlDays
     * @param float $runway
     * @param float $aging
     * @param bool $uncertain
     * @return string
     */
    public static function stateFor($elapsedDays, $ttlDays, $runway,
        $aging, $uncertain = false
    ) {
        if ($elapsedDays >= $ttlDays) {
            /*
             * Expiry wins over uncertainty, and the reason is the
             * direction of the bound rather than a preference between
             * two labels — see the class note. The uncertainty is not
             * swallowed: it travels beside the state.
             */
            return 'expired';
        }
        if ($uncertain) {
            return 'uncertain';
        }
        return $runway >= $aging ? 'current' : 'aging';
    }

    /**
     * What a state is called on screen.
     *
     * Two surfaces render this axis — the value page's relevance card
     * and the profile editor's bench — and each one keeping its own
     * copy of four words is how `uncertain` came to be printed raw in
     * one of them while the other said *timeline uncertain*. One
     * writer, the way the clock and `type_rule` lists were settled.
     *
     * The uncertainty flag is deliberately not composed in here. It is
     * a second thing that is true at once, and a caller that wants
     * both says so itself rather than receiving a sentence it cannot
     * take apart again.
     *
     * @param string|null $state
     * @return string
     */
    public static function stateLabel($state)
    {
        $labels = array(
            'current' => __('current'),
            'aging' => __('aging'),
            'expired' => __('expired'),
            'uncertain' => __('timeline uncertain'),
        );
        if ($state === null || !isset($labels[$state])) {
            return (string)$state;
        }
        return $labels[$state];
    }

    /**
     * The TTL in force, and every candidate it was chosen from.
     *
     * `185.234.219.24` occurs as both `ip-src` and `ip-dst`, and MISP's
     * per-attribute decay never had to answer which TTL a *value* takes.
     * `shortest` is the default because it is the conservative reading —
     * a value that is stale in any of its roles is worth re-checking —
     * and because the alternative silently extends a short-lived
     * indicator's life on the strength of a type it barely appears as.
     *
     * **The candidates travel whatever the rule chose**, because §3.4
     * requires the panel to name which type supplied the number and
     * that others were shorter or longer. A single number with no
     * provenance is how a reader concludes the page is wrong about a
     * value they know well.
     *
     * @param array $context
     * @param array $section From section()
     * @return array `days`, `type`, `rule`, `candidates`, `spread`
     */
    public static function ttlFor(array $context, array $section)
    {
        $rule = $section['type_rule'];
        $table = $section['ttl_days'];
        $default = $section['ttl_default'];
        $assigned = isset($section['ttl_types'])
            ? $section['ttl_types']
            : array();
        $overrides = isset($section['ttl_overrides'])
            ? $section['ttl_overrides']
            : array();
        $candidates = array();
        foreach (self::typeList($context) as $type => $count) {
            /*
             * Where the number came from, so the page can say *90 days,
             * short* rather than quoting a bare number a reader then
             * has to go and look up. An override is named as one
             * because it is the thing the buckets could not express.
             */
            if (isset($overrides[$type])) {
                $from = 'override';
            } elseif (isset($assigned[$type])) {
                $from = 'bucket';
            } else {
                $from = 'default';
            }
            $candidates[$type] = array(
                'type' => $type,
                'days' => isset($table[$type])
                    ? (int)$table[$type]
                    : $default,
                'count' => $count,
                'named' => isset($table[$type]),
                'from' => $from,
                'bucket' => isset($assigned[$type]) && $from === 'bucket'
                    ? $assigned[$type]
                    : null,
            );
        }
        if (empty($candidates)) {
            return array(
                'days' => $default,
                'type' => null,
                'rule' => $rule,
                'candidates' => array(),
                'spread' => false,
                'from_default' => true,
                'from' => 'default',
                'bucket' => null,
            );
        }
        $chosen = self::chooseType($candidates, $rule);
        $days = array_column($candidates, 'days');
        return array(
            'days' => $chosen['days'],
            'type' => $chosen['type'],
            'rule' => $rule,
            'candidates' => array_values($candidates),
            'spread' => min($days) !== max($days),
            'from_default' => !$chosen['named'],
            'from' => $chosen['from'],
            'bucket' => $chosen['bucket'],
        );
    }

    /**
     * The candidate the rule picks.
     *
     * `most_common` breaks a tie on the shortest TTL rather than on
     * whichever type the query happened to return first: a rule whose
     * answer depends on row order is a rule that changes its mind
     * between two page loads.
     *
     * @param array $candidates
     * @param string $rule
     * @return array
     */
    private static function chooseType(array $candidates, $rule)
    {
        $best = null;
        foreach ($candidates as $candidate) {
            if ($best === null) {
                $best = $candidate;
                continue;
            }
            if ($rule === 'longest') {
                $better = $candidate['days'] > $best['days'];
            } elseif ($rule === 'most_common') {
                $better = $candidate['count'] > $best['count']
                    || ($candidate['count'] === $best['count']
                        && $candidate['days'] < $best['days']);
            } else {
                $better = $candidate['days'] < $best['days'];
            }
            if ($better) {
                $best = $candidate;
            }
        }
        return $best;
    }

    /**
     * The clock: what last confirmed this value, and who.
     *
     * **This is the load-bearing decision, more than the curve.** MISP's
     * own answer is *last sighting*, and its consequence is that a
     * heavily-sighted value never decays — the term does nothing for
     * exactly the values with the most activity. The default here is
     * `last_independent_corroboration`: the most recent of
     *
     *   - an occurrence created by an organisation that did not already
     *     hold one, or
     *   - a sighting from an organisation other than the occurrence's
     *     reporter,
     *
     * because the clock should reset when *someone new confirms it*, and
     * an org re-sighting its own report for the two-hundredth time is
     * activity rather than corroboration.
     *
     * **The earliest organisation is not corroboration.** It is the
     * original report, which is the thing being corroborated — so the
     * organisation joins are read in date order and the first one is
     * dropped. On a single-organisation value that leaves nothing, the
     * clock falls back to the value's own most recent encoding, and
     * `fallback` says so: this is the majority case in production
     * (§6 item 7) and it must render as a stated condition rather than
     * as a blank.
     *
     * @param array $context
     * @param array $section
     * @return array `at`, `kind`, `by`, `held_by`, `fallback`, `events`
     */
    public static function clockFor(array $context, array $section)
    {
        $events = self::eventsFor($context, $section['clock']);
        $newest = null;
        foreach ($events as $event) {
            if ($newest === null || $event['at'] > $newest['at']) {
                $newest = $event;
            }
        }
        /*
         * Whether the sighting half of the clock was readable at all.
         * A value MISP flagged as over-correlating has its rows left
         * unfetched by the budget, and a sighting policy can hide them
         * — so a clock running off the organisation joins alone must
         * say which half it is missing rather than presenting half an
         * answer as a whole one.
         */
        $available = empty($context['budget']['hot'])
            && !isset($context['missing']['sightings']);
        if ($newest !== null) {
            return array(
                'setting' => $section['clock'],
                'at' => $newest['at'],
                'kind' => $newest['kind'],
                'by' => isset($newest['by']) ? $newest['by'] : null,
                'held_by' => isset($newest['held_by'])
                    ? $newest['held_by']
                    : null,
                'fallback' => false,
                'events' => $events,
                'rows_read' => $available,
            );
        }
        $fallback = self::fallbackFor($context);
        return array(
            'setting' => $section['clock'],
            'at' => $fallback === null ? null : $fallback['at'],
            'kind' => 'fallback',
            'by' => $fallback === null ? null : $fallback['by'],
            'held_by' => null,
            'fallback' => true,
            'events' => array(),
            'rows_read' => $available,
        );
    }

    /**
     * Every dated event the chosen clock counts, ascending.
     *
     * The list, not just its maximum, because the runway series needs
     * it: the clock moves over history, so the shelf life at a past day
     * is measured from the newest corroboration that had *happened by*
     * that day. Taking the current clock and walking backwards would
     * draw a value corroborated last week as having been fresh in 2019.
     *
     * That mistake has a precedent in this corpus. The retired decay
     * code took `max(report, attribute date)` unconditionally and so
     * applied a reset on days that preceded it, drawing a four-month
     * plateau at full score on a model whose lifetime was three days —
     * visible the moment the chart was drawn and invisible to every
     * assertion before it. `runwaySeries()` walks forward for that
     * reason.
     *
     * @param array $context
     * @param string $clock
     * @return array Ascending by `at`; each `at`, `kind`, `by`,
     *               optionally `held_by`
     */
    public static function eventsFor(array $context, $clock)
    {
        $events = array();
        if ($clock === 'last_occurrence') {
            foreach (self::orgRows($context) as $org) {
                if (empty($org['newest'])) {
                    continue;
                }
                $events[] = array(
                    'at' => (int)$org['newest'],
                    'kind' => 'occurrence',
                    'by' => self::orgLabel($org),
                );
            }
        } elseif ($clock === 'last_sighting') {
            $events = self::sightingEvents($context, 'sightings');
        } else {
            $events = array_merge(
                self::joinEvents($context),
                self::sightingEvents($context, 'foreign')
            );
        }
        usort($events, function ($a, $b) {
            return $a['at'] === $b['at'] ? 0 : ($a['at'] < $b['at'] ? -1 : 1);
        });
        return $events;
    }

    /**
     * When each organisation other than the first one joined.
     *
     * @param array $context
     * @return array
     */
    private static function joinEvents(array $context)
    {
        $joins = array();
        foreach (self::orgRows($context) as $org) {
            if (empty($org['oldest'])) {
                continue;
            }
            $joins[] = array(
                'at' => (int)$org['oldest'],
                'kind' => 'org_joined',
                'by' => self::orgLabel($org),
            );
        }
        usort($joins, function ($a, $b) {
            return $a['at'] === $b['at'] ? 0 : ($a['at'] < $b['at'] ? -1 : 1);
        });
        // The first organisation is the report, not its corroboration.
        array_shift($joins);
        return $joins;
    }

    /**
     * The sighting half of the clock, from the day-folded reports the
     * context carries.
     *
     * Every entry is a whole report rather than a bare stamp, so every
     * row of the corroboration timeline has a name on it and the
     * maximum below cannot pick an unlabelled duplicate over a labelled
     * one — a tie the first version could lose on a report filed at
     * exactly midnight.
     *
     * @param array $context
     * @param string $which `sightings` or `foreign`
     * @return array
     */
    private static function sightingEvents(array $context, $which)
    {
        $corroboration = isset($context['corroboration'])
            ? $context['corroboration']
            : array();
        $entries = isset($corroboration[$which . '_days'])
            ? $corroboration[$which . '_days']
            : array();
        $kind = $which === 'foreign' ? 'foreign_sighting' : 'sighting';
        $events = array();
        foreach ($entries as $entry) {
            $events[] = array(
                'at' => (int)$entry['at'],
                'kind' => $kind,
                'by' => isset($entry['name']) ? $entry['name'] : null,
                'held_by' => array(
                    'attribute_id' => $entry['attribute_id'] ?? null,
                    'event_id' => $entry['event_id'] ?? null,
                ),
            );
        }
        return $events;
    }

    /**
     * The clock with nothing to corroborate it: the value's own most
     * recent encoding.
     *
     * **An encoding date, deliberately, and this is where §3.6 earns
     * its keep.** MISP's `Attribute.timestamp` is when a row was
     * written, not when anything was observed, so a value whose only
     * date is this one has an unknown observation date — and the
     * precision check turns that into `timeline uncertain` rather than
     * into a confident `current`. The fallback is not a guess dressed
     * up as a measurement; it is the measurement the record actually
     * supports, labelled as such.
     *
     * @param array $context
     * @return array|null
     */
    private static function fallbackFor(array $context)
    {
        $newest = isset($context['occurrences']['newest'])
            ? (int)$context['occurrences']['newest']
            : 0;
        if ($newest <= 0) {
            return null;
        }
        $by = null;
        foreach (self::orgRows($context) as $org) {
            if ((int)($org['newest'] ?? 0) === $newest) {
                $by = self::orgLabel($org);
                break;
            }
        }
        return array('at' => $newest, 'by' => $by);
    }

    /**
     * Whether the clock can be trusted at all (§3.6).
     *
     * The example that forced D11: a phishing URL encoded two months
     * after the incident with no `first_seen`, so the clock falls back
     * to a creation timestamp and the axis would call a dead campaign
     * *current*. Both detecting facts are already in the rows, and both
     * arrive as single-row aggregates:
     *
     *   - `first_seen` absent on every occurrence — the observation
     *     date is unknown and only the encoding date is known;
     *   - a created-to-published lag beyond `lag_uncertain_days` — the
     *     encoding date is a poor proxy for the observation date.
     *
     * The same two facts feed `record.temporal_precision` in the
     * quality ledger, which says the quieter thing beside this one: a
     * record that cannot date its own observations is a weaker record.
     * Two readings, two homes, one pair of measurements.
     *
     * @param array $context
     * @param array $section
     * @return array `uncertain`, `reasons`, `note`, plus the raw counts
     */
    public static function precisionFor(array $context, array $section)
    {
        $temporal = isset($context['temporal'])
            ? $context['temporal']
            : array();
        $occurrences = (int)($temporal['occurrences'] ?? 0);
        $dated = (int)($temporal['with_first_seen'] ?? 0);
        $lag = isset($temporal['max_lag_days'])
            && $temporal['max_lag_days'] !== null
            ? (int)$temporal['max_lag_days']
            : null;
        $limit = (int)$section['lag_uncertain_days'];
        $reasons = array();
        if ($occurrences > 0 && $dated === 0) {
            $reasons[] = array('key' => 'no_first_seen');
        }
        if ($lag !== null && $lag > $limit) {
            $reasons[] = array(
                'key' => 'lag',
                'days' => $lag,
                'limit' => $limit,
            );
        }
        return array(
            'uncertain' => !empty($reasons),
            'reasons' => $reasons,
            'note' => self::precisionNote($reasons),
            'occurrences' => $occurrences,
            'with_first_seen' => $dated,
            'max_lag_days' => $lag,
            'lag_limit' => $limit,
        );
    }

    /**
     * The measurement that tripped, as the sentence the panels print.
     *
     * Worded here rather than in each template because two panels show
     * it at two scales and a state this specific must not be paraphrased
     * differently in each. §3.6 requires the number: *never a silently
     * wrong `current`*, and never a bare *uncertain* either.
     *
     * @param array $reasons
     * @return string|null
     */
    private static function precisionNote(array $reasons)
    {
        if (empty($reasons)) {
            return null;
        }
        $parts = array();
        foreach ($reasons as $reason) {
            if ($reason['key'] === 'lag') {
                $parts[] = sprintf(
                    __('encoded %s days after the event\'s own dates'),
                    $reason['days']
                );
            } else {
                $parts[] = __('no first_seen on any occurrence');
            }
        }
        return implode(__(' · '), $parts);
    }

    /**
     * The runway over a day grid, for the chart overlay.
     *
     * Two quantities rather than two estimates of one. The verdict's
     * curves used to plot the synthesised score against a dashed NIDS
     * decay score — a second decay opinion on a page that will have
     * one — where this plots **evidence against remaining shelf life**,
     * which is a question an analyst actually has.
     *
     * The walk is forward and the event list is sorted, so this is
     * linear in days plus events rather than a scan per day. `null`
     * before the first day anything is known: a gap is the honest mark,
     * and zero is a value that has expired rather than one with nothing
     * recorded.
     *
     * @param array $relevance From relevanceFor()
     * @param array $grid Ascending unix timestamps, one per day
     * @param int|null $first The value's own first encoding, so days
     *                        before the record starts draw nothing
     * @return array One point per grid day: float in `[0, 1]`, or null
     */
    public static function runwaySeries(array $relevance, array $grid,
        $first = null
    ) {
        $ttl = $relevance['ttl']['days'];
        $speed = isset($relevance['decay_speed'])
            ? $relevance['decay_speed']
            : self::DEFAULTS['decay_speed'];
        $events = isset($relevance['clock']['events'])
            ? $relevance['clock']['events']
            : array();
        $stamps = array();
        foreach ($events as $event) {
            $stamps[] = (int)$event['at'];
        }
        sort($stamps);
        /*
         * With nothing corroborating it the clock is the fallback, and
         * the fallback is a single stamp — so the series is that one
         * stamp's runway from the day it lands.
         */
        if (empty($stamps) && $relevance['clock']['at'] !== null) {
            $stamps = array((int)$relevance['clock']['at']);
        }
        $points = array();
        $next = 0;
        $count = count($stamps);
        $held = null;
        foreach ($grid as $at) {
            while ($next < $count && $stamps[$next] <= $at) {
                $held = $stamps[$next];
                $next++;
            }
            $from = $held;
            if ($from === null && $first !== null && $first <= $at) {
                // Before anything corroborated it the shelf life runs
                // from the record's own start, which is the same
                // fallback the state uses.
                $from = (int)$first;
            }
            if ($from === null) {
                $points[] = null;
                continue;
            }
            $points[] = self::runway(
                self::daysBetween($from, $at),
                $ttl,
                $speed
            );
        }
        return $points;
    }

    /**
     * What would move this axis — the relevance falsifiability line
     * (`04-dispositions.md` §8), solved for the boundary rather than
     * guessed at.
     *
     * One line per axis and the cheapest one, so this answers the
     * question the state raises rather than every question it could:
     * a value that is current says how long it has, an aging one says
     * the same, an expired one says what would bring it back, and an
     * uncertain one says what measurement would settle it — which is
     * the only case where the answer is not a number of days.
     *
     * @param array $relevance
     * @return array|null `axis`, `direction`, `text`
     */
    public static function changerFor(array $relevance)
    {
        if ($relevance['state'] === null) {
            return null;
        }
        if ($relevance['state'] === 'uncertain') {
            return array(
                'axis' => 'relevance',
                'direction' => 'up',
                'text' => __(
                    'A first_seen on any occurrence would date the'
                    . ' observation rather than its encoding — the'
                    . ' timeline stops being uncertain.'
                ),
            );
        }
        if ($relevance['state'] === 'expired') {
            return array(
                'axis' => 'relevance',
                'direction' => 'up',
                'text' => sprintf(
                    __('One independent corroboration resets the clock —'
                        . ' the assessment is current again for %s days.'),
                    $relevance['ttl']['days']
                ),
            );
        }
        $days = (int)ceil($relevance['runway_days']);
        return array(
            'axis' => 'relevance',
            'direction' => 'down',
            'text' => sprintf(
                __n(
                    'No independent corroboration for %s more day — the'
                        . ' assessment expires.',
                    'No independent corroboration for %s more days — the'
                        . ' assessment expires.',
                    $days
                ),
                $days
            ),
        );
    }

    /**
     * The clock's sighting half, folded from the rows.
     *
     * Lives here rather than in the owning model because it *is* the
     * clock's definition — *a sighting from an organisation other than
     * the occurrence's reporter* — and a definition split between a
     * tool and a model is a definition two readers will disagree about.
     * It takes rows and a map, issues nothing, and so is checkable
     * without an instance.
     *
     * **Type 0 only.** A false positive and an expiration are reports
     * that argue *against* the value; counting either as corroboration
     * would let a value be kept current by the community disputing it.
     * The retired decay code drew the same line for the same reason and
     * the Sightings tab exists to make it visible.
     *
     * **Folded to one report a day, and whole-history.** The runway
     * series samples a day at a time, so a second sighting on a day
     * that already has one cannot move any curve; and the clock is a
     * whole-history aggregate by declaration (`03-signals.md` §2.3,
     * `06-staleness.md` §3.3) because a windowed clock could not tell
     * stale-since-91-days from stale-since-three-years while the TTL
     * table reaches 730. So the caller must fold **before** applying
     * `evidence.window` — which is the one exclusion this clock does
     * not see — and after `sightings.self`, which is the same
     * argument in a different place.
     *
     * **The fold keeps the day's newest report whole, not just its
     * date.** The first version kept bare stamps and labelled only the
     * newest of all of them, on the reasoning that the curve reads
     * dates and nothing reads a name off the rest. Rendered, that put
     * *unnamed* on five of the six rows of the corroboration timeline —
     * and naming what supplied the clock is the half of the
     * aggregation rule §4.1 says is not a decoration. A name per
     * distinct day costs 39 strings on this instance's busiest value,
     * so the reasoning was thrift about nothing.
     *
     * @param array $rows Rows as `Sighting::listSightings` returns
     * @param array $occurrences From `Value::sightedOccurrenceIdsFor` —
     *                           flat, keyed by attribute id
     * @return array The `corroboration` context block
     */
    public static function corroborationFrom(array $rows,
        array $occurrences
    ) {
        $days = array();
        $foreignDays = array();
        $undecidable = 0;
        foreach ($rows as $row) {
            if ((int)($row['Sighting']['type'] ?? 0) !== 0) {
                continue;
            }
            $at = (int)($row['Sighting']['date_sighting'] ?? 0);
            if ($at <= 0) {
                continue;
            }
            $day = strtotime(date('Y-m-d', $at));
            $entry = self::sightingEntry($row, $at);
            if (!isset($days[$day]) || $at > $days[$day]['at']) {
                $days[$day] = $entry;
            }
            $orgId = (int)($row['Sighting']['org_id'] ?? 0);
            $id = (int)($row['Sighting']['attribute_id'] ?? 0);
            $author = isset($occurrences[$id])
                ? (int)($occurrences[$id]['orgc_id'] ?? 0)
                : 0;
            if ($orgId === 0 || $author === 0) {
                /*
                 * An anonymised report, or an occurrence whose reporter
                 * this viewer's row does not name. Independence is then
                 * a comparison that cannot be made, and the honest
                 * answer is to leave the sighting out of the
                 * *independent* half and say how many were skipped —
                 * the same conservative direction `sightings.self`
                 * takes from the other side, and phase 4's §7.3 is the
                 * reason this count exists at all: a fold that reports
                 * nothing looks exactly like a fold with nothing to do.
                 */
                $undecidable++;
                continue;
            }
            if ($orgId === $author) {
                continue;
            }
            if (!isset($foreignDays[$day])
                || $at > $foreignDays[$day]['at']
            ) {
                $foreignDays[$day] = $entry;
            }
        }
        ksort($days);
        ksort($foreignDays);
        return array(
            'sightings_days' => array_values($days),
            'foreign_days' => array_values($foreignDays),
            'last_sightings' => empty($days)
                ? null
                : end($days),
            'last_foreign' => empty($foreignDays)
                ? null
                : end($foreignDays),
            'undecidable' => $undecidable,
        );
    }

    /**
     * One labelled sighting, in the shape the clock names its source
     * with.
     *
     * @param array $row
     * @param int $at
     * @return array
     */
    private static function sightingEntry(array $row, $at)
    {
        $name = $row['Organisation']['name'] ?? '';
        return array(
            'at' => $at,
            'org_id' => (int)($row['Sighting']['org_id'] ?? 0),
            'name' => $name === '' ? __('Others') : $name,
            'attribute_id' => (int)($row['Sighting']['attribute_id'] ?? 0),
            'event_id' => (int)($row['Sighting']['event_id'] ?? 0),
        );
    }

    /**
     * The `relevance` section, with every default filled in.
     *
     * The seventh top-level section (D11 promoted it out of the
     * `signals` list, because it configures an axis rather than a
     * ledger row). A profile naming none of these still produces the
     * shipped behaviour, which is `01-profile.md` §1.3's *defaults that
     * work unedited* applied to this axis.
     *
     * An unrecognised `clock` or `type_rule` falls back to the default
     * rather than throwing: a profile is a hand-edited JSON document
     * and a typo in it must not take a page down.
     *
     * @param array|null $profile
     * @return array
     */
    public static function section($profile)
    {
        $section = array();
        if (is_array($profile)) {
            $parameters = isset($profile['parameters'])
                ? $profile['parameters']
                : $profile;
            if (isset($parameters['relevance'])
                && is_array($parameters['relevance'])
            ) {
                $section = $parameters['relevance'];
            }
        }
        $clock = isset($section['clock']) ? $section['clock'] : null;
        $rule = isset($section['type_rule'])
            ? $section['type_rule']
            : null;
        $shelf = self::shelfLife($section);
        $table = $shelf['ttl_days'];
        $default = $shelf['ttl_default'];
        return array(
            'ttl_buckets' => $shelf['ttl_buckets'],
            'ttl_types' => $shelf['ttl_types'],
            'ttl_overrides' => $shelf['ttl_overrides'],
            'clock' => in_array($clock, self::CLOCKS, true)
                ? $clock
                : self::DEFAULTS['clock'],
            'type_rule' => in_array($rule, self::TYPE_RULES, true)
                ? $rule
                : self::DEFAULTS['type_rule'],
            'decay_speed' => isset($section['decay_speed'])
                && is_numeric($section['decay_speed'])
                && (float)$section['decay_speed'] > 0
                ? (float)$section['decay_speed']
                : self::DEFAULTS['decay_speed'],
            'aging_fraction' => isset($section['aging_fraction'])
                && is_numeric($section['aging_fraction'])
                ? (float)$section['aging_fraction']
                : self::DEFAULTS['aging_fraction'],
            'lag_uncertain_days' => isset($section['lag_uncertain_days'])
                && is_numeric($section['lag_uncertain_days'])
                ? (int)$section['lag_uncertain_days']
                : self::DEFAULTS['lag_uncertain_days'],
            'ttl_days' => $table,
            'ttl_default' => $default,
        );
    }

    /**
     * Shelf life, from whichever of the two shapes the profile carries
     * (D18).
     *
     * **Current shape.** `ttl_buckets` gives each of the four buckets a
     * day count, `ttl_types` assigns a type to a bucket,
     * `ttl_overrides` gives a type its own day count, and `ttl_default`
     * covers every type nobody named. An override beats a bucket
     * assignment, because it exists for the type the buckets cannot
     * express.
     *
     * **Pre-D18 shape.** A flat `ttl_days` map — the one every existing
     * fork carries, because `AnalystProfile::updateDefaults()` never
     * touches a fork. It reads as *no buckets, and every named type is
     * its own override*, which is exactly what it already meant. The
     * reading is exact rather than approximate, and it is why the
     * override list exists at all: an old fork keeps behaving
     * identically until somebody opens the editor. Without this a fork
     * would resolve no TTLs and every value in it would silently change
     * shelf life, with nobody having edited anything.
     *
     * Both shapes come out as one **effective type => days** map, so
     * `ttlFor()` and `chooseType()` compare days and `shortest` keeps
     * meaning shortest. Comparing bucket ordinals instead would be a
     * different rule wearing the same name.
     *
     * @param array $section The raw `relevance` section
     * @return array `ttl_buckets`, `ttl_types`, `ttl_overrides`,
     *               `ttl_days` (effective), `ttl_default`
     */
    private static function shelfLife(array $section)
    {
        /*
         * The two shapes do not blend. A document carrying any of the
         * current keys is read as a current one and its `ttl_days` is
         * ignored outright — a half-edited fork whose stale flat map
         * shadowed its own bucket assignments would resolve to a blend
         * of two shapes, which is unpredictable and which no analyst
         * asked for. `merge()` drops the legacy key on save for the
         * same reason.
         */
        $current = false;
        foreach (array('ttl_buckets', 'ttl_types', 'ttl_overrides',
            'ttl_default') as $key
        ) {
            if (isset($section[$key])) {
                $current = true;
            }
        }
        $buckets = self::BUCKET_DAYS;
        if (isset($section['ttl_buckets'])
            && is_array($section['ttl_buckets'])
        ) {
            foreach ($section['ttl_buckets'] as $name => $days) {
                if (!isset($buckets[$name]) || !is_numeric($days)
                    || (int)$days <= 0
                ) {
                    continue;
                }
                $buckets[$name] = (int)$days;
            }
        }

        $legacy = !$current && isset($section['ttl_days'])
            && is_array($section['ttl_days'])
            ? $section['ttl_days']
            : array();
        $default = null;
        if (array_key_exists('default', $legacy)
            && is_numeric($legacy['default'])
        ) {
            $default = (int)$legacy['default'];
        }
        unset($legacy['default']);
        if (isset($section['ttl_default'])
            && is_numeric($section['ttl_default'])
        ) {
            $default = (int)$section['ttl_default'];
        }
        if ($default === null || $default <= 0) {
            $default = self::DEFAULTS['ttl_default'];
        }

        $types = array();
        if (isset($section['ttl_types'])
            && is_array($section['ttl_types'])
        ) {
            foreach ($section['ttl_types'] as $type => $bucket) {
                if (!is_string($bucket) || !isset($buckets[$bucket])) {
                    continue;
                }
                $types[(string)$type] = $bucket;
            }
        }

        // The legacy map's named types become overrides, which is
        // exactly what they already meant.
        $overrides = array();
        foreach ($legacy as $type => $days) {
            if (!is_numeric($days) || (int)$days <= 0) {
                continue;
            }
            $overrides[(string)$type] = (int)$days;
        }
        if (isset($section['ttl_overrides'])
            && is_array($section['ttl_overrides'])
        ) {
            foreach ($section['ttl_overrides'] as $type => $days) {
                if (!is_numeric($days) || (int)$days <= 0) {
                    continue;
                }
                $overrides[(string)$type] = (int)$days;
            }
        }

        $effective = array();
        foreach ($types as $type => $bucket) {
            $effective[$type] = $buckets[$bucket];
        }
        foreach ($overrides as $type => $days) {
            $effective[$type] = $days;
        }

        return array(
            'ttl_buckets' => $buckets,
            'ttl_types' => $types,
            'ttl_overrides' => $overrides,
            'ttl_days' => $effective,
            'ttl_default' => $default,
        );
    }

    /**
     * Whole days between two stamps, measured between calendar days.
     *
     * The same rule `ValueStatsTool::agoPhrase` uses, and for the same
     * reason: a TTL is a number of days, so a clock that ticks over at
     * an arbitrary time of day would put a value on either side of its
     * own expiry depending on when the page was opened.
     *
     * @param int $from
     * @param int $to
     * @return int
     */
    public static function daysBetween($from, $to)
    {
        return (int)floor(
            (strtotime(date('Y-m-d', (int)$to))
                - strtotime(date('Y-m-d', (int)$from))) / 86400
        );
    }

    /**
     * @param array $context
     * @return array type => occurrences
     */
    private static function typeList(array $context)
    {
        $types = array();
        foreach (($context['types'] ?? array()) as $row) {
            $name = is_array($row)
                ? ($row['type'] ?? null)
                : $row;
            if ($name === null || $name === '') {
                continue;
            }
            $types[$name] = is_array($row)
                ? (int)($row['count'] ?? 0)
                : 0;
        }
        return $types;
    }

    /**
     * @param array $context
     * @return array
     */
    private static function orgRows(array $context)
    {
        return isset($context['orgs']) && is_array($context['orgs'])
            ? $context['orgs']
            : array();
    }

    /**
     * @param array $org
     * @return string
     */
    private static function orgLabel(array $org)
    {
        return isset($org['name']) && $org['name'] !== ''
            ? $org['name']
            : __('Unknown organisation');
    }
}
