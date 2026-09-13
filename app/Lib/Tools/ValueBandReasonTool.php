<?php

/**
 * What the ledger's total banded as, and against what.
 *
 * The third of `10-wiring.md`'s axis passes and the smallest, because
 * quality is the axis that always had its working: the ledger prints
 * every signal and its points, and §5.1's invariant is that those rows
 * sum to the number in the hero exactly. Nothing about the arithmetic
 * was missing.
 *
 * **The band was.** A reader saw `Quality low · 19 / 100` and a ledger
 * summing to 19, and nothing on the page said what `low` means — where
 * `medium` starts, how far off this record is, or whether the points
 * are even what decided it. Lean has named its supermajority since
 * §18 and relevance its TTL since §17; this is the setting quality
 * was still not naming.
 *
 * **And twice the band is not the points at all.** The thin-record
 * clamp lowers it — a record with one source and no sightings cannot
 * pass `low` however well it scores — and `quality_high_min_signals`
 * holds it — points past the `high` floor on too few independent
 * readings stay `medium`. Both are stated in the profile, both are
 * deliberate, and a reader meeting either sees a number and a band
 * that contradict each other with no explanation in sight. The
 * falsifiability card says what would *change* it, which is a
 * different sentence from what *happened*.
 *
 * `ValueLeanReasonTool`'s sibling, and the same contract: a pure
 * function of the array the engine returned, so
 * `04-lean-bands-harness.php` can reach the two reasons no value on
 * this instance occupies. Neither is exotic — the clamp needs a
 * single-source record scoring past 30, and the instance's best
 * single-source record scores 9.
 */
class ValueBandReasonTool
{
    /**
     * The sentence for one assessment's band.
     *
     * @param array $verdict What the engine returned
     * @return string|null Null where the ledger's own empty state has
     *                     already said it
     */
    public static function reasonFor(array $verdict)
    {
        $reason = isset($verdict['band_reason']['reason'])
            ? $verdict['band_reason']['reason']
            : null;
        $floors = isset($verdict['band_reason']['floors'])
            && is_array($verdict['band_reason']['floors'])
            ? $verdict['band_reason']['floors']
            : array();
        if ($reason === null || $reason === 'no_signal') {
            /*
             * *No signal contributed to this assessment* is already
             * the ledger's empty state, in the place a reader is
             * looking when they want it. A second sentence under an
             * empty table is the gap §13's hero guard avoids.
             */
            return null;
        }

        $band = isset($verdict['band']) ? $verdict['band'] : 'none';
        $quality = (int)(isset($verdict['quality'])
            ? $verdict['quality'] : 0);
        $fired = (int)(isset($verdict['signals']['fired'])
            ? $verdict['signals']['fired'] : 0);
        $medium = (int)(isset($floors['medium']) ? $floors['medium'] : 30);
        $high = (int)(isset($floors['high']) ? $floors['high'] : 60);

        switch ($reason) {
            case 'clamped':
                /*
                 * The one case where the number on the page and the
                 * band on the page are not the same statement, so it
                 * says both: what the points band as, and what is
                 * overriding them. Phrased as the profile's rule
                 * rather than as a fact about the value — an analyst
                 * who disagrees edits three numbers
                 * (`04-dispositions.md` §6).
                 */
                return sprintf(
                    __('The points alone would band this %1$s. This'
                        . ' profile holds it at %2$s: a record from one'
                        . ' source with no sightings does not pass'
                        . ' %2$s, however much that source reports.'),
                    self::bandWord(isset($floors['would_be'])
                        ? $floors['would_be'] : 'medium'),
                    self::bandWord($band)
                );

            case 'min_signals':
                return sprintf(
                    __n(
                        'The points are past the %1$s floor of %2$s,'
                            . ' but %1$s needs %3$s signals and %4$s'
                            . ' fired.',
                        'The points are past the %1$s floor of %2$s,'
                            . ' but %1$s needs %3$s signals and %4$s'
                            . ' fired.',
                        $fired
                    ),
                    self::bandWord('high'),
                    $high,
                    (int)(isset($floors['min_signals'])
                        ? $floors['min_signals'] : 4),
                    $fired
                );
        }

        /*
         * The ordinary case, and it says the floor rather than the
         * distance to it: *9 points short of medium* is the
         * falsifiability card's sentence, and it is already written
         * there for the record it applies to. This one is about where
         * the boundary is.
         */
        switch ($band) {
            case 'high':
                return sprintf(
                    __('Past this profile\'s %1$s floor of %2$s, the'
                        . ' top band.'),
                    self::bandWord('high'),
                    $high
                );
            case 'medium':
                return sprintf(
                    __('Past this profile\'s %1$s floor of %2$s;'
                        . ' %3$s starts at %4$s.'),
                    self::bandWord('medium'),
                    $medium,
                    self::bandWord('high'),
                    $high
                );
            case 'low':
                return sprintf(
                    __('Under this profile\'s %1$s floor of %2$s.'),
                    self::bandWord('medium'),
                    $medium
                );
        }
        /*
         * A band this file does not know. It is not `none` — that is
         * `no_signal` above — so it is a band a profile or a later
         * phase added, and the honest answer is to say nothing rather
         * than to guess which floor it sits against.
         */
        unset($quality);
        return null;
    }

    /**
     * A band, as the page says it.
     *
     * One writer, for the same reason `ValueRelevanceTool::stateLabel()`
     * is one: the hero, the ledger's foot and the falsifiability card
     * all name bands, and the copy that does not get updated is the one
     * that prints the stored key.
     *
     * @param string|null $band
     * @return string
     */
    public static function bandWord($band)
    {
        $words = array(
            'none' => __('none'),
            'low' => __('low'),
            'medium' => __('medium'),
            'high' => __('high'),
        );
        return isset($words[$band]) ? $words[$band] : (string)$band;
    }
}
