<?php

/**
 * The decay series for a value, and the rule that turns ten
 * per-attribute curves into one.
 *
 * `DecayingModel::getScoreOvertime($user, $model_id, $attribute_id, …)`
 * scores **one attribute**. A value is a set of them, so a value-scoped
 * curve needs an aggregation rule, and
 * prd/value-profile-live/00-contract.md §14.5 named this class as the
 * one home for that decision while leaving it open.
 *
 * **The rule, decided here: the per-day maximum across occurrences,
 * labelled with the occurrence it came from.** A value is as live as
 * its best-corroborated occurrence. A mean would let a stale duplicate
 * drag down a value somebody reported an hour ago, and a minimum would
 * make adding an occurrence a way to lower a score. The cost of max is
 * that it is not monotone in evidence — an untagged occurrence carrying
 * a model's `default_base_score` can outrank a well-sighted one whose
 * taxonomies score it low — which is exactly why the label is part of
 * the rule rather than a decoration on it. The rail card prints which
 * occurrence holds the number, so a reader who disagrees with the
 * number can see where to go and argue with it.
 *
 * **Pure and static, and it takes no `$user`** (§14.5). It issues no
 * query and resolves no permission: the owning model hands it a
 * sighting set that `Sighting::listSightings` has already scoped, and
 * scores that MISP's own formula classes computed.
 *
 * **What it does not own: the formula.** `computeScore` lives in
 * `app/Model/DecayingModelsFormulas/`, and `ValueProfile` calls it —
 * the fixture reimplemented MISP's polynomial and this deliberately
 * does not. This class owns the *sampling*: which day grid, what
 * elapsed time each occurrence has on each day of it, and which
 * occurrence wins.
 */
class ValueDecayTool
{
    /**
     * How many occurrences contribute a curve.
     *
     * The envelope is a maximum, so a cap can only lower it: what the
     * chart draws over a capped set is a **lower bound** on the value's
     * score, which is a statable property rather than an unknown error.
     * `ValueProfile::decaySet()` chooses which occurrences fill the cap
     * and argues there why the bound is tight — every occurrence that
     * has been reported, plus the newest of the rest.
     *
     * The number is the product that matters: 100 occurrences over the
     * 1,095-day span cap, at two models, is 219,000 `computeScore`
     * calls. §11 of the tab's brief costed the naive shape — hourly,
     * per attribute, per model — at twenty `getScoreOvertime` loops for
     * ten occurrences; a daily grid over a bounded span is what makes
     * this a page rather than a job.
     */
    const OCCURRENCE_CAP = 100;

    /**
     * How far back the day grid may run.
     *
     * Three years. The span is the value's whole life, and on a real
     * instance that is not a bounded quantity: `0.0.0.0` first appears
     * in 2015, which is 3,948 daily samples per model for a value whose
     * sightings are all in the last two years. The curve is the only
     * dense series in the payload — a count can be sparse because most
     * days have none, a score cannot because every day has one — so
     * this is the number that bounds the fragment.
     *
     * When it bites the panel says so, because a cap is not a
     * permission (§14.6).
     */
    const SPAN_CAP_DAYS = 1095;

    /**
     * Which reports reset a decay clock, per occurrence.
     *
     * Type 0 only, which is the whole claim the Sightings tab exists to
     * make visible: `getScoreOvertime` asks
     * `listSightings(…, $sightingsType = 0)`, so a false positive and
     * an expiration are drawn on the chart and move no line. Deriving
     * the curve from the same rows the table lists makes that true by
     * construction rather than by assertion.
     *
     * @param array $sightings Rows as `Sighting::listSightings` returns
     * @return array attribute id => ascending unix timestamps
     */
    public static function resetStamps(array $sightings)
    {
        $resets = array();
        foreach ($sightings as $sighting) {
            if ((int)$sighting['Sighting']['type'] !== 0) {
                continue;
            }
            $id = (int)$sighting['Sighting']['attribute_id'];
            $resets[$id][] = (int)$sighting['Sighting']['date_sighting'];
        }
        foreach ($resets as &$stamps) {
            sort($stamps);
        }
        unset($stamps);
        return $resets;
    }

    /**
     * One occurrence's elapsed time on each day of the grid.
     *
     * **Two kinds of thing reset the clock, and both are events with a
     * date.** A report is the obvious one.
     * `DecayingModelBase::computeCurrentScore` adds the other: if the
     * attribute was modified after its last report, the clock restarts
     * at the modification, because an analyst touching a row is a
     * statement about it. So the clock start on any given day is the
     * latest reset that has *happened by* that day — the newest report
     * at or before it, or the attribute's own date, whichever is later
     * and whichever has occurred.
     *
     * **The "has occurred" half is the whole of this function and it was
     * wrong first time.** Taking `max(report, attribute date)`
     * unconditionally applies the attribute's date as a reset on days
     * that precede it, which pins elapsed time at zero and draws a flat
     * line at full base score across every day between an occurrence's
     * first report and its last edit. On `8.8.8.8` that was a
     * four-month plateau at 78 on a model whose lifetime is three days —
     * visible the moment the chart was drawn in a browser and invisible
     * to every assertion before it.
     *
     * At the last grid point every occurrence's date has occurred, so
     * this reduces to exactly `computeCurrentScore`'s rule: the current
     * score is unaffected, and only the history it is the end of
     * changes.
     *
     * `null` where no reset has happened yet: before an occurrence's
     * earliest evidence there is no score to draw, and zero is a score.
     * A gap is the honest mark.
     *
     * The walk is forward and the reset list is sorted, so this is
     * linear in days plus reports rather than a scan per day.
     *
     * @param array $resets Ascending unix timestamps, from resetStamps
     * @param int $fallback The occurrence's own date — `last_seen` if it
     *                      has one, else its timestamp, which is the
     *                      pair `computeCurrentScore` picks from
     * @param array $grid Ascending unix timestamps, one per day
     * @return array One entry per grid point: seconds, or null
     */
    public static function elapsed(array $resets, $fallback, array $grid)
    {
        $elapsed = array();
        $next = 0;
        $count = count($resets);
        $last = null;
        foreach ($grid as $at) {
            while ($next < $count && $resets[$next] <= $at) {
                $last = $resets[$next];
                $next++;
            }
            $from = $last;
            if ($fallback <= $at && ($from === null || $fallback > $from)) {
                $from = $fallback;
            }
            if ($from === null) {
                $elapsed[] = null;
                continue;
            }
            $elapsed[] = $at - $from;
        }
        return $elapsed;
    }

    /**
     * Collapse occurrences that share a base score into one candidate
     * carrying, per day, the smallest elapsed time any of them has.
     *
     * **Exact, not an approximation, and only for one formula.**
     * MISP's `Polynomial` score is
     * `base × (1 − (elapsed / lifetime) ^ (1 / speed))`, clamped at
     * zero: strictly non-increasing in elapsed time for a fixed base. So
     * among occurrences with equal base scores the one with the smallest
     * elapsed time *is* the maximum, and evaluating the other twenty-two
     * only to discard them is work with a known answer.
     *
     * The caller must not use this for a formula whose score depends on
     * anything else about the attribute. `PolynomialExtended` zeroes a
     * score from the attribute's own `retention` tags and `Sightings`
     * ignores elapsed time altogether, so two occurrences with one base
     * score can legitimately differ under both. `ValueProfile` checks
     * the formula class and takes the per-occurrence path otherwise.
     *
     * Measured on the verification instance, `8.8.8.8` — 23
     * occurrences, 2 models, a 1,095-day span, and two distinct base
     * scores between them: the chart panel went from 172 ms to 86 ms and
     * the decay rail, which does the same envelope work with the ACL
     * caches already warm, from 52 ms to 16 ms. The scores are
     * identical either way, which is what makes this an optimisation
     * rather than a change.
     *
     * @param array $bases attribute id => `base` and `event_id`
     * @param array $elapsedFor attribute id => per-day elapsed, from
     *                          elapsed()
     * @return array Candidates, each `base`, `elapsed`, `owner`,
     *               `event` — the last two per day
     */
    public static function groupByBase(array $bases, array $elapsedFor)
    {
        $groups = array();
        foreach ($bases as $id => $meta) {
            if (!isset($elapsedFor[$id])) {
                continue;
            }
            // A string key so that 95.0 and 95 are one group, and so
            // that two scores differing past the fourth decimal are
            // not — `DecayingModel::__adjustParameters` already rounds
            // its inputs to four.
            $key = number_format((float)$meta['base'], 4, '.', '');
            if (!isset($groups[$key])) {
                $groups[$key] = array(
                    'base' => (float)$meta['base'],
                    'elapsed' => $elapsedFor[$id],
                    'owner' => array_fill(
                        0,
                        count($elapsedFor[$id]),
                        $id
                    ),
                    'event' => array_fill(
                        0,
                        count($elapsedFor[$id]),
                        $meta['event_id']
                    ),
                );
                foreach ($groups[$key]['elapsed'] as $i => $seconds) {
                    if ($seconds === null) {
                        $groups[$key]['owner'][$i] = null;
                        $groups[$key]['event'][$i] = null;
                    }
                }
                continue;
            }
            $group = &$groups[$key];
            foreach ($elapsedFor[$id] as $i => $seconds) {
                if ($seconds === null) {
                    continue;
                }
                if ($group['elapsed'][$i] === null
                    || $seconds < $group['elapsed'][$i]
                ) {
                    $group['elapsed'][$i] = $seconds;
                    $group['owner'][$i] = $id;
                    $group['event'][$i] = $meta['event_id'];
                }
            }
            unset($group);
        }
        return array_values($groups);
    }

    /**
     * The per-day maximum over the candidate curves, and who holds it.
     *
     * This is the aggregation rule. `owner` is the same length as
     * `points` because the occupant changes along the curve: a value
     * whose newest report lands on a different occurrence each week has
     * an envelope made of several occurrences' curves, and a single
     * label on the whole line would be wrong for most of it.
     *
     * @param array $candidates Each `points`, plus `owner` and
     *                          `event_id` — either one id each, or one
     *                          per day as groupByBase returns
     * @return array `points`, `owner`, `event`
     */
    public static function envelope(array $candidates)
    {
        $length = 0;
        foreach ($candidates as $candidate) {
            $length = max($length, count($candidate['points']));
        }
        $points = array_fill(0, $length, null);
        $owner = array_fill(0, $length, null);
        $event = array_fill(0, $length, null);
        foreach ($candidates as $candidate) {
            foreach ($candidate['points'] as $i => $score) {
                if ($score === null) {
                    continue;
                }
                if ($points[$i] === null || $score > $points[$i]) {
                    $points[$i] = $score;
                    /*
                     * `owner` is per day when the candidate came from
                     * groupByBase — the occurrence holding a group's
                     * minimum changes along the curve — and a single id
                     * when it is one occurrence's own curve.
                     */
                    $owner[$i] = is_array($candidate['owner'])
                        ? $candidate['owner'][$i]
                        : $candidate['owner'];
                    $event[$i] = is_array($candidate['event_id'])
                        ? $candidate['event_id'][$i]
                        : $candidate['event_id'];
                }
            }
        }
        return array(
            'points' => $points,
            'owner' => $owner,
            'event' => $event,
        );
    }

    /**
     * The last thing that reset this value's clock, over a set of
     * occurrences, and where it happened.
     *
     * The rail card's provenance line. Under the maximum rule the
     * answer belongs to one occurrence rather than to the value, which
     * is why the event id travels with it.
     *
     * @param array $resets From resetStamps
     * @param array $occurrences As `Value::occurrenceIdsFor` returns
     * @return array|null `at`, `attribute_id`, `event_id`
     */
    public static function lastReset(array $resets, array $occurrences)
    {
        $best = null;
        foreach ($resets as $id => $stamps) {
            if (empty($stamps) || !isset($occurrences[$id])) {
                continue;
            }
            $at = end($stamps);
            if ($best === null || $at > $best['at']) {
                $best = array(
                    'at' => $at,
                    'attribute_id' => $id,
                    'event_id' => $occurrences[$id]['event_id'],
                );
            }
        }
        return $best;
    }
}
