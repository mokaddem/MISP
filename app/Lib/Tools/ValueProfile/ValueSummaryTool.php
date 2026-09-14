<?php

/**
 * The Assessment hero's sentence.
 *
 * `ValueChangersTool`'s sibling, and here for the same reason it is:
 * both turn a finished assessment into English, both are pure
 * functions of the array the engine returned, and neither touches a
 * database or a view. §14.5 puts the queries and the ACL in the model
 * and the arithmetic in a tool — prose over an array the model already
 * holds is the tool's side of that line, and being the tool's side is
 * what lets `04-lean-bands-harness.php` reach the branches no value on
 * a given instance happens to occupy.
 *
 * It is one sentence and three clauses. What it must never be is a
 * fourth opinion: every clause names a key the engine emitted, so a
 * sentence that disagreed with the badge above it or the shelf-life
 * chart beside it would be a defect in the wording rather than a
 * second computation.
 */
class ValueSummaryTool
{
    /**
     * The hero's sentence — D11's one open point, and the only place on
     * the tab where all three axes are read together.
     *
     * **Three axes in one line without three competing numbers**
     * (`12-assessment.md` §7). The hero already prints the lean as a
     * badge and the quality as a band and a number; what it has never
     * printed is **relevance**, which until now reached the tab only as
     * a chart in the rail (§9.4). So the sentence carries one number
     * and it is the one nothing else in the hero carries: days.
     *
     * The order is lean, then quality, then relevance, because that is
     * the order the questions are asked in — *what does this say*, *how
     * much is behind it*, *does it still hold*. D11 §3's three test
     * readings come out of it directly: a late-encoded phishing URL
     * reads *thin record, shelf life ran out*, and an old well-attested
     * hash reads *well evidenced, shelf life ran out* — which are the
     * two halves of *"asserted threat · thin record · likely over"* and
     * *"well-documented historic threat"* said in sentences.
     *
     * **Nothing here is a second opinion.** Every clause is a reading
     * of a key the engine already emitted, so a sentence that
     * disagreed with the badge above it or the chart beside it would be
     * a bug in the wording rather than a second computation. The bands
     * are named, never re-derived; `runway_days` is printed, never
     * recomputed.
     *
     * @param array $verdict What the engine returned
     * @return string|null Null when there is nothing to say, which is
     *                     not the same as the empty string
     */
    public static function summaryFor(array $verdict)
    {
        $lean = isset($verdict['lean']) ? $verdict['lean'] : null;
        $band = isset($verdict['band']) ? $verdict['band'] : 'none';
        $relevance = isset($verdict['relevance'])
            ? $verdict['relevance']
            : array();

        /*
         * A lean of `none` is the one case with no three-part sentence
         * to build: there is no record to be thin, and no clock to run
         * (`ValueRelevanceTool` answers `no_record` for exactly the
         * same values). One clause, and it is the whole assessment.
         *
         * It is also the branch that reaches `summary` first —
         * `value_verdict_card.ctp` prints the prose only where there
         * are no ledger rows to list instead, which `10-wiring.md`
         * §2.2 records as the reason the sparse value was the one that
         * broke.
         */
        if ($lean === 'none' || $lean === null) {
            return __('Nothing you can see records this value, so there'
                . ' is nothing to assess.');
        }

        $clauses = array(self::leanClause($lean));
        $quality = self::qualityClause($band);
        $shelf = self::relevanceClause($relevance);
        if ($quality === null) {
            /*
             * A lean with no scored evidence behind it: the record says
             * something and nothing has been weighed. Saying *the
             * record behind that is* and then naming no band would read
             * as a sentence that lost its ending.
             */
            return implode(' ', $clauses);
        }
        $clauses[] = $shelf === null
            ? $quality . '.'
            : sprintf(__('%1$s, and %2$s'), $quality, $shelf);
        return implode(' ', $clauses);
    }

    /**
     * What the record asserts, as the sentence's first clause.
     *
     * It deliberately does **not** repeat `ValueLean::label()`, which
     * the badge two lines above already prints. A sentence opening
     * *"Asserted threat."* under a badge reading *Asserted threat* is
     * a caption, not a summary.
     *
     * @param string $lean
     * @return string
     */
    private static function leanClause($lean)
    {
        switch ($lean) {
            case 'threat':
                return __('What is recorded here reads as a threat.');
            case 'benign':
                return __('What is recorded here reads as benign.');
            default:
                return __('What is recorded here contradicts itself.');
        }
    }

    /**
     * How much record there is, named by its band.
     *
     * The band and not the number: the number is printed beside the
     * badge, and a sentence repeating it would be the second of the
     * three competing numbers D11 §7 rules out. *Thin* is D11 §3's own
     * word for `low`.
     *
     * @param string $band
     * @return string|null Null where nothing was weighed
     */
    private static function qualityClause($band)
    {
        switch ($band) {
            case 'high':
                return __('The record behind that is well evidenced');
            case 'medium':
                return __('The record behind that is moderately'
                    . ' evidenced');
            case 'low':
                return __('The record behind that is thin');
            default:
                return null;
        }
    }

    /**
     * Whether it still holds, and for how much longer.
     *
     * The one number in the sentence. `runway_days` is read as the
     * relevance card and the rail's chart read it — the same field, so
     * the three cannot drift apart the way phase 5 §7.2's bar and
     * series did.
     *
     * The uncertainty is folded in rather than appended as a second
     * sentence, because it qualifies the days and nothing else: a
     * reader who takes *53 days left* away without it has taken away
     * the wrong number.
     *
     * @param array $relevance
     * @return string|null Null where there is no clock to run
     */
    private static function relevanceClause(array $relevance)
    {
        $state = isset($relevance['state']) ? $relevance['state'] : null;
        if ($state === null) {
            return null;
        }
        $days = isset($relevance['runway_days'])
            ? (int)$relevance['runway_days']
            : 0;
        $uncertain = !empty($relevance['uncertain']);

        if ($state === 'expired') {
            $over = sprintf(
                __n(
                    'its shelf life ran out %d day ago',
                    'its shelf life ran out %d days ago',
                    max(1, -$days)
                ),
                max(1, -$days)
            );
            return $uncertain
                ? sprintf(
                    __('%s — on a timeline nothing records.'),
                    $over
                )
                : $over . '.';
        }
        if ($days <= 0) {
            return __('it expires today.');
        }
        /*
         * `aging` says the same number differently rather than adding
         * one, because the hero draws no state label: without this the
         * only thing separating *current* from *aging* in the sentence
         * would be a figure the reader has to know the TTL to judge.
         */
        $left = $state === 'aging'
            ? sprintf(
                __n(
                    'it is most of the way through its shelf life, with'
                        . ' %d day left',
                    'it is most of the way through its shelf life, with'
                        . ' %d days left',
                    $days
                ),
                $days
            )
            : sprintf(
                __n(
                    'it has %d day of shelf life left',
                    'it has %d days of shelf life left',
                    $days
                ),
                $days
            );
        /*
         * `uncertain` arrives two ways — as the state itself, when the
         * timeline is the most notable thing about the axis, and as a
         * flag beside `current` or `aging`. Both mean the same thing to
         * a reader, so both get the same words; what differs is only
         * whether a state label was going to be printed anyway.
         */
        if ($state === 'uncertain' || $uncertain) {
            return sprintf(
                __('%s — on a timeline nothing records.'),
                $left
            );
        }
        return $left . '.';
    }
}
