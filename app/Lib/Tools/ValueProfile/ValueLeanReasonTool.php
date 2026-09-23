<?php

/**
 * Why the lean is the lean, in one sentence.
 *
 * `ValueSummaryTool`'s sibling and `ValueChangersTool`'s: all three
 * turn a finished assessment into English, all three are pure
 * functions of the array the engine returned, and none of them touches
 * a database or a view. Staying pure is what lets a test harness reach
 * the exits no instance happens to occupy.
 *
 * **Eight exits, not seven.** `ValueLeanTool::leanFor()` has seven and
 * `ValueVerdictTool`'s lean-disputed check is the eighth — the only one
 * decided *after* the ledger, and without its own sentence it would
 * borrow the sentence of the lean it had just overturned.
 *
 * **Why every exit gets a sentence.** Of `leanFor()`'s seven exits only
 * a loaded escalation produces prose of its own. Without this, an
 * ordinary value would state *Asserted threat* and never say by whom,
 * on what count, or against which threshold — *Asserted threat,
 * quality 19* with a provenance line carrying only *Computed at render
 * · Analyst profile default-v1*. The stances that decide it —
 * `threat_orgs`, `benign_orgs`, the share and the supermajority in
 * force — are computed on every value, and this is what reads them.
 *
 * **It reads the exit rather than re-deriving it.** `decided_by` is
 * the engine's own name for the branch it took, and the false-positive
 * listing and the benign supermajority are why that matters: both
 * answer `benign`, and which one a value takes depends on an order of
 * writing that is invisible from outside the function.
 *
 * **Nothing here is a second opinion.** Every clause names a key the
 * engine emitted. A sentence disagreeing with the badge above it or
 * the organisations table below it would be a defect in the wording,
 * not a second computation.
 *
 * **It says what the record asserts, never what the value is.**
 * *Organisations assert this is a threat* is
 * a statement about the record; *this is a threat* is the claim an
 * engine reading MISP tables cannot make.
 */
class ValueLeanReasonTool
{
    /**
     * The sentence for one assessment.
     *
     * @param array $verdict What the engine returned
     * @return string|null Null where there is nothing to say — an
     *                     unnamed exit, or the empty record, whose hero
     *                     has already said it
     */
    public static function reasonFor(array $verdict)
    {
        $decidedBy = isset($verdict['decided_by'])
            ? $verdict['decided_by']
            : null;
        $stances = isset($verdict['stances'])
            && is_array($verdict['stances'])
            ? $verdict['stances']
            : array();
        if ($decidedBy === null || $decidedBy === 'nothing_visible') {
            return null;
        }

        $threat = (int)(isset($stances['threat_orgs'])
            ? $stances['threat_orgs'] : 0);
        $benign = (int)(isset($stances['benign_orgs'])
            ? $stances['benign_orgs'] : 0);
        $total = (int)(isset($stances['orgs'])
            ? $stances['orgs'] : $threat + $benign);
        $threshold = self::thresholdLabel($stances);

        switch ($decidedBy) {
            case 'escalation':
                /*
                 * The one exit that already has prose, and it is
                 * better prose than this file could write: the rule
                 * was authored for exactly this value's shape. The
                 * provenance line carries the rule itself, so this
                 * says only which exit was taken rather than two
                 * sentences saying one thing.
                 */
                return __('A conflict rule decided this reading.');

            case 'false_positive_listed':
                /*
                 * The false-positive listing. The warninglist band
                 * beside this one names the list; what it cannot say
                 * is that the organisations were *given the chance* to
                 * override it and did not reach the bar.
                 */
                /*
                 * *The threat stances run 1 of 2* rather than *1 of 2
                 * assert*, and `no_supermajority` below takes the same
                 * treatment for the same reason: these two exits are
                 * the ones where the numerator can be 1 under a plural
                 * total, and a verb after it agrees with neither
                 * consistently. `__n()` cannot help — it picks one
                 * plural form for the whole string, and the
                 * `no_supermajority` sentence has two counts that
                 * disagree about which form they want. The two
                 * supermajority exits keep their verb because a
                 * supermajority guarantees the numerator is plural
                 * whenever the total is.
                 */
                return sprintf(
                    __('A warninglist marks this a false positive, and'
                        . ' only %1$s of %2$s organisations call it a'
                        . ' threat, short of this profile\'s %3$s bar.'),
                    $threat,
                    $total,
                    $threshold
                );

            case 'threat_supermajority':
                return sprintf(
                    __n(
                        '%1$s of %2$s organisation asserts this is a'
                            . ' threat, meeting this profile\'s %3$s'
                            . ' bar.',
                        '%1$s of %2$s organisations assert this is a'
                            . ' threat, meeting this profile\'s %3$s'
                            . ' bar.',
                        $total
                    ),
                    $threat,
                    $total,
                    $threshold
                );

            case 'benign_supermajority':
                return sprintf(
                    __n(
                        '%1$s of %2$s organisation reports this as'
                            . ' harmless, meeting this profile\'s %3$s'
                            . ' bar.',
                        '%1$s of %2$s organisations report this as'
                            . ' harmless, meeting this profile\'s %3$s'
                            . ' bar.',
                        $total
                    ),
                    $benign,
                    $total,
                    $threshold
                );

            case 'lean_disputed':
                /*
                 * The lean-disputed check. It is not one of
                 * `leanFor()`'s seven — the lean it names was counted
                 * there and then overturned by the ledger, which is
                 * why `ValueVerdictTool` rewrites `decided_by` on its
                 * way out. Without that the band would print the
                 * *overturned* reading's sentence: a **Contested**
                 * badge over *2 of 2 organisations report this as
                 * harmless*.
                 *
                 * It names the counted lean rather than hiding it,
                 * because that half is still true and is what the
                 * stance bar directly above is drawing.
                 */
                $counted = isset($verdict['derived_lean'])
                    ? $verdict['derived_lean']
                    : null;
                if ($counted === 'benign') {
                    return __('The organisations report this as'
                        . ' harmless, but the lean evidence argues'
                        . ' threat.');
                }
                if ($counted === 'threat') {
                    return __('The organisations call this a threat,'
                        . ' but the lean evidence argues harmless.');
                }
                return __('The lean evidence disputes what the'
                    . ' organisations reported.');

            case 'no_supermajority':
                /*
                 * No supermajority, and the exit that most needs a
                 * sentence. A value drawn as contested with two cases
                 * beside each other, where the reason is *neither side
                 * reached the bar* — which no other surface on this
                 * page says, so the contested layout would explain
                 * itself only on the values an escalation happened to
                 * catch.
                 */
                return sprintf(
                    __('Neither side reaches this profile\'s %1$s bar'
                        . ' (%2$s to %3$s).'),
                    $threshold,
                    $threat,
                    $benign
                );
        }

        return null;
    }

    /**
     * The supermajority, as a reader meets it.
     *
     * A percentage rather than the stored fraction, and rounded: the
     * shipped default is `0.66`, which is *not* two thirds, so spelling
     * it *two thirds* would print a threshold the engine does not use.
     * `66%` is what it is.
     *
     * @param array $stances
     * @return string
     */
    private static function thresholdLabel(array $stances)
    {
        $share = isset($stances['supermajority'])
            && is_numeric($stances['supermajority'])
            ? (float)$stances['supermajority']
            : null;
        if ($share === null) {
            return __('majority');
        }
        return sprintf(__('%s%%'), (int)round($share * 100));
    }
}
