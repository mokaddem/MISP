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
 * It is one sentence and two clauses. What it must never be is a
 * fourth opinion: every clause names a key the engine emitted, so a
 * sentence that disagreed with the badge above it would be a defect in
 * the wording rather than a second computation.
 */
class ValueSummaryTool
{
    /**
     * The hero's sentence: what the record asserts, then how much
     * record there is.
     *
     * **Relevance is not in it.** Days read as one clause at the tail
     * of a sentence were the axis a reader had to dig out; the hero
     * draws them as a figure of their own instead
     * (`value_verdict_runway.ctp`), the way the hover card does.
     *
     * **Nothing here is a second opinion.** Every clause is a reading
     * of a key the engine already emitted, so a sentence that
     * disagreed with the badge above it would be a bug in the wording
     * rather than a second computation. The bands are named, never
     * re-derived.
     *
     * @param array $verdict What the engine returned
     * @return string|null Null when there is nothing to say, which is
     *                     not the same as the empty string
     */
    public static function summaryFor(array $verdict)
    {
        $lean = isset($verdict['lean']) ? $verdict['lean'] : null;
        $band = isset($verdict['band']) ? $verdict['band'] : 'none';

        /*
         * A lean of `none` is the one case with no two-part sentence
         * to build: there is no record to be thin. One clause, and it
         * is the whole assessment.
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

        $clauses = array(self::leanClause($lean,
            isset($verdict['decided_by']) ? $verdict['decided_by'] : null));
        $quality = self::qualityClause($band);
        if ($quality === null) {
            /*
             * A lean with no scored evidence behind it: the record says
             * something and nothing has been weighed. Saying *the
             * record behind that is* and then naming no band would read
             * as a sentence that lost its ending.
             */
            return implode(' ', $clauses);
        }
        $clauses[] = $quality . '.';
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
     * **Contested says which kind of contradiction**, because there
     * are two and a reader acts on them differently. On the
     * verification instance the ten contested values split five and
     * five: `no_supermajority`, where the organisations that reported
     * the value disagree with each other and there is no ledger row on
     * either side; and `lean_disputed`, where they agree and the
     * evidence about the value disputes them. Both printed *"What is
     * recorded here contradicts itself"* and nothing else, which is
     * true of both and tells a reader neither.
     *
     * An `escalation` keeps the bare clause: a conflict rule carries
     * its own prose and the hero quotes it a few lines down, so naming
     * the kind here would be the page saying it twice in different
     * words.
     *
     * @param string $lean
     * @param string|null $decidedBy `ValueLeanTool`'s exit
     * @return string
     */
    private static function leanClause($lean, $decidedBy = null)
    {
        switch ($lean) {
            case 'threat':
                return __('What is recorded here reads as a threat.');
            case 'benign':
                return __('What is recorded here reads as benign.');
        }
        switch ($decidedBy) {
            case 'no_supermajority':
                return __('What is recorded here contradicts itself: the'
                    . ' organisations that reported it do not agree.');
            case 'lean_disputed':
                return __('What is recorded here contradicts itself: the'
                    . ' evidence about the value disputes the'
                    . ' organisations that reported it.');
        }
        return __('What is recorded here contradicts itself.');
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
}
