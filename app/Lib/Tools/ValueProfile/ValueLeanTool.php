<?php

App::uses('ValueSignalLoader', 'Tools/ValueProfile');
App::uses('ValueEscalationBase', 'Model/ValueEscalations');

/**
 * The categorical half of an assessment: what does the record assert
 * this value is?
 *
 * Three axes make up an assessment — what the record says the value is,
 * whether that still matters, and how much of it can be trusted. This
 * file owns the first. It is deliberately not a number: an engine
 * reading MISP's own tables can say *"four organisations flagged this
 * as an indicator and nobody contradicted them"*, and that is a
 * statement about the record rather than about the value. The quality
 * ledger says how thin or thick that record is; the lean says which way
 * it points.
 *
 * ## Stances are counted per organisation, never per occurrence
 *
 * One organisation putting the same value in forty events with `to_ids`
 * set is one voice, not forty. Counting occurrences would let a single
 * prolific reporter outvote everyone else on the instance, and it is
 * the same independence argument the reporting signal already makes
 * about corroboration.
 *
 * ```
 * threat_orgs   orgs holding at least one occurrence with to_ids = 1
 * benign_orgs   orgs whose every occurrence has to_ids = 0
 * threat_share  threat_orgs / (threat_orgs + benign_orgs)
 * ```
 *
 * ## The rules, first match wins
 *
 * ```
 * 1. nothing this viewer can see                      → none
 * 2. an enabled conflict rule fires                   → contested, named
 * 3. a false_positive list matched and the stances
 *    are not a supermajority the other way            → benign
 * 4. threat_share ≥ lean_supermajority                → threat
 * 5. threat_share ≤ 1 − lean_supermajority            → benign
 * 6. otherwise                                        → contested
 * ```
 *
 * **Rule 3 sits before rule 4 on purpose**, and it is what makes a
 * value's history legible. The day an address lands on the
 * public-resolver warninglist, its lean flips from a rule-6 muddle to
 * rule-3 benign on exactly the same evidence — the profile's knowledge
 * changed, not the rows. Under a score that flip would have needed a
 * hundred-point swing out of a signal worth forty-four, which is to say
 * it would not have happened at all.
 *
 * Rule 2 sits before rule 3 because precedence needs a guard: a
 * `false_positive` list against nearly unanimous threat stances is a
 * collision of two deliberate judgements, and the conflict rule that
 * names it has to get there before either one wins quietly.
 *
 * There is a seventh rule, and it lives with the ledger rather than
 * here: a lean whose anchored quality comes out below zero becomes
 * contested, because the record is then disputing its own assertion.
 * That one cannot be decided before scoring, so `ValueVerdictTool`
 * applies it.
 *
 * ## Why a rule that could not run is reported
 *
 * A signal that fails to load is named in the ledger's `not_counted`
 * list. A conflict rule has no ledger row to be missing from — it
 * contributes nothing to the quality — so a rule that could not run
 * would leave the lean unescalated with no trace anywhere the reader
 * looks. That is a silent change of answer, so `rule_errors` comes back
 * beside the lean and is rendered with it.
 */
class ValueLeanTool
{
    /** The four states a lean can take. */
    const LEANS = array('threat', 'benign', 'contested', 'none');

    /**
     * How close to a share threshold counts as reaching it.
     *
     * Not defensive rounding — a fencepost that a strict comparison
     * gets wrong. A threshold written as `0.66` is not `0.66` in
     * binary, and neither is `1 - 0.66`: a value held by 34 of 100
     * organisations computes a share very slightly *above* the mirror
     * of the shipped supermajority, so a strict `<=` reads the mirror
     * boundary as a stance split and the derivation stops being
     * symmetric. The threshold an analyst typed has to mean what it
     * says on both sides, so the comparison carries a tolerance far
     * smaller than one organisation can move any real share.
     */
    const SHARE_TOLERANCE = 1e-9;

    /**
     * Whether a share reaches a threshold, at the boundary as well as
     * past it.
     *
     * @param float $share
     * @param float $threshold
     * @return bool
     */
    public static function atLeast($share, $threshold)
    {
        return (float)$share >= (float)$threshold - self::SHARE_TOLERANCE;
    }

    /**
     * The whole derivation for one value.
     *
     * @param array $context From the context builder, ACL-scoped
     * @param array|null $profile An `AnalystProfile` row, unwrapped, or
     *                            null when no profile is in force
     * @return array `lean`, `rule`, `stances` and `rule_errors`
     */
    public function leanFor(array $context, $profile = null)
    {
        $stances = $this->stancesFor($context, $profile);
        $errors = $this->ruleErrors($profile);

        /*
         * Rule 1. Stated as a fact about the context rather than about
         * the value: a value with no occurrence *this viewer can see*
         * has no record to lean on, and an engine that read its own
         * blindness as evidence would call every invisible value
         * benign.
         */
        if ($this->nothingVisible($context, $stances)) {
            return $this->answer('none', null, $stances, $errors,
                'nothing_visible');
        }

        // Rule 2.
        $fired = $this->firstRuleFiring($context, $profile, $stances);
        if ($fired !== null) {
            return $this->answer(
                'contested',
                $fired,
                $stances,
                $errors,
                'escalation'
            );
        }

        $supermajority = $stances['supermajority'];
        $share = $stances['threat_share'];

        // Rule 3.
        if ($this->falsePositiveListed($context)
            && !self::atLeast($share, $supermajority)
        ) {
            return $this->answer('benign', null, $stances, $errors,
                'false_positive_listed');
        }
        /*
         * Rules 4 and 5. Stated as *a supermajority on either side*
         * rather than as one comparison and its arithmetic mirror,
         * because that is what the rule means and it is the form that
         * cannot come out asymmetric.
         */
        if (self::atLeast($share, $supermajority)) {
            return $this->answer('threat', null, $stances, $errors,
                'threat_supermajority');
        }
        if (self::atLeast(1 - $share, $supermajority)) {
            return $this->answer('benign', null, $stances, $errors,
                'benign_supermajority');
        }
        // Rule 6.
        return $this->answer('contested', null, $stances, $errors,
            'no_supermajority');
    }

    /**
     * The stance count, and the threshold it is read against.
     *
     * Public because it is the input to every conflict rule and to the
     * lean's own falsifiability line, and because a caller that has
     * already derived a lean should not have to count the stances
     * again to explain it.
     *
     * @param array $context
     * @param array|null $profile
     * @return array
     */
    public function stancesFor(array $context, $profile = null)
    {
        $orgs = isset($context['orgs']) && is_array($context['orgs'])
            ? $context['orgs']
            : array();
        $threat = 0;
        $benign = 0;
        foreach ($orgs as $org) {
            if (!empty($org['to_ids_yes'])) {
                $threat++;
            } elseif (!empty($org['to_ids_no'])) {
                $benign++;
            }
            /*
             * An organisation whose stance columns are both zero holds
             * no occurrence and so casts no vote. It cannot happen from
             * the context builder's own query; it can happen to a
             * caller assembling a context by hand, and counting it in
             * the denominator would dilute everybody else's vote with
             * a row that says nothing.
             */
        }
        $counted = $threat + $benign;
        return array(
            'threat_orgs' => $threat,
            'benign_orgs' => $benign,
            'orgs' => $counted,
            'threat_share' => $counted === 0
                ? 0.0
                : $threat / $counted,
            'supermajority' => self::supermajority($profile),
        );
    }

    /**
     * Whether the context has anything to lean on.
     *
     * Two ways it may not, and they are the same answer: no occurrence
     * at all, or occurrences whose stances could not be read. The
     * second is only reachable from a hand-built context, and treating
     * it as `none` is the conservative reading — a share computed from
     * a zero denominator would be a number with no evidence under it.
     *
     * @param array $context
     * @param array $stances
     * @return bool
     */
    private function nothingVisible(array $context, array $stances)
    {
        $occurrences = isset($context['occurrences']['total'])
            ? (int)$context['occurrences']['total']
            : 0;
        return $occurrences === 0 || $stances['orgs'] === 0;
    }

    /**
     * Whether any list that matched resolved to `false_positive`.
     *
     * The resolved category is read off the individual hits rather than
     * off the summary, because a value can hit a resolver list and a
     * CDN list at once and each rule is entitled to see its own.
     *
     * @param array $context
     * @return bool
     */
    private function falsePositiveListed(array $context)
    {
        $hits = isset($context['warninglist']['hits'])
            ? $context['warninglist']['hits']
            : array();
        foreach ($hits as $hit) {
            if (($hit['category'] ?? null) === 'false_positive') {
                return true;
            }
        }
        return false;
    }

    /**
     * The first enabled conflict rule that fires, in the profile's own
     * order.
     *
     * **List order decides**, and it is the profile author's to
     * arrange. Two rules can be true of the same value — a value on
     * both a CDN list and a resolver list, reported by a supermajority,
     * satisfies both shipped rules — and picking the one that reads
     * best on that instance is an editorial call, not something to
     * derive from the rules themselves.
     *
     * @param array $context
     * @param array|null $profile
     * @param array $stances
     * @return array|null
     */
    private function firstRuleFiring(array $context, $profile,
        array $stances
    ) {
        $context['stances'] = $stances;
        foreach ($this->ruleEntries($profile) as $entry) {
            $id = $entry['id'];
            $rule = ValueSignalLoader::get(
                $id,
                ValueSignalLoader::SUBJECT_ESCALATION
            );
            if ($rule === null) {
                continue;
            }
            if (!$this->usable($rule, $entry, $context)) {
                continue;
            }
            $outcome = $this->run($rule, $entry, $context);
            if ($outcome === null) {
                continue;
            }
            return array(
                'id' => $id,
                'prose' => $outcome['prose'],
                'evidence' => isset($outcome['evidence'])
                    ? $outcome['evidence']
                    : '',
                'source' => $rule->source,
                /*
                 * The two keys the fixture's own rule block uses, so a
                 * template reading it today needs no change to read a
                 * computed rule. They go with the fixture when the
                 * templates are renamed.
                 */
                'name' => $id,
                'text' => $outcome['prose'],
            );
        }
        return null;
    }

    /**
     * Run one rule, treating a throw as *did not fire*.
     *
     * A dropped file whose `fires()` throws must not take the page
     * down; the loader's error list is where the admin's copy of the
     * problem goes, and `ruleErrors()` is where the reader's does.
     *
     * @param object $rule
     * @param array $entry
     * @param array $context
     * @return array|null
     */
    private function run($rule, array $entry, array $context)
    {
        try {
            $outcome = $rule->fires($context, $entry);
        } catch (Throwable $e) {
            return null;
        }
        if (!is_array($outcome) || empty($outcome['prose'])
            || !is_string($outcome['prose'])
        ) {
            return null;
        }
        return $outcome;
    }

    /**
     * Whether a rule may run at all: its evidence readable, and its
     * profile entry legal.
     *
     * @param object $rule
     * @param array $entry
     * @param array $context
     * @return bool
     */
    private function usable($rule, array $entry, array $context)
    {
        if (!empty($entry['emits'])
            && $entry['emits'] !== ValueEscalationBase::EMITS
        ) {
            return false;
        }
        if (empty($context['missing'])) {
            return true;
        }
        foreach ($rule->reads as $key) {
            if (isset($context['missing'][$key])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Every reason the reader deserves for a rule that did not get to
     * decide: a profile weighting a rule this instance does not have, a
     * file that would not load, an entry asking for a lean a conflict
     * rule may not emit.
     *
     * All three are the same kind of problem — the lean on screen was
     * derived without a rule the profile said to apply — and all three
     * are invisible unless something says so.
     *
     * @param array|null $profile
     * @return array `id` and `note`
     */
    private function ruleErrors($profile)
    {
        $loaderErrors = ValueSignalLoader::errors(
            ValueSignalLoader::SUBJECT_ESCALATION
        );
        $errors = array();
        foreach ($this->ruleEntries($profile) as $entry) {
            $id = $entry['id'];
            if (!empty($entry['emits'])
                && $entry['emits'] !== ValueEscalationBase::EMITS
            ) {
                $errors[] = array(
                    'id' => $id,
                    'note' => sprintf(
                        __('The profile asks this conflict rule to'
                            . ' emit `%1$s`; a conflict rule may only'
                            . ' emit `%2$s`, so it did not run.'),
                        (string)$entry['emits'],
                        ValueEscalationBase::EMITS
                    ),
                );
                continue;
            }
            if (ValueSignalLoader::get(
                $id,
                ValueSignalLoader::SUBJECT_ESCALATION
            ) !== null) {
                continue;
            }
            $errors[] = array(
                'id' => $id,
                'note' => $this->missingReason($id, $loaderErrors),
            );
        }
        return $errors;
    }

    /**
     * A rule this instance does not have at all, against one it has but
     * could not load — the same distinction the ledger draws for a
     * signal, and the same two sentences.
     *
     * @param string $id
     * @param array $loaderErrors
     * @return string
     */
    private function missingReason($id, array $loaderErrors)
    {
        foreach ($loaderErrors as $file => $reason) {
            if (strpos($reason, '`' . $id . '`') !== false) {
                return sprintf(
                    __('The rule did not load (%1$s): %2$s'),
                    $file,
                    $reason
                );
            }
        }
        return __('This instance has no implementation for that'
            . ' conflict rule, so a contradiction the profile wanted'
            . ' named could not be checked.');
    }

    /**
     * The profile's enabled `escalations` entries, in order.
     *
     * A disabled rule is not an error and not reported: the analyst
     * turned it off, which is the whole point of the section.
     *
     * @param array|null $profile
     * @return array
     */
    private function ruleEntries($profile)
    {
        $entries = array();
        foreach (self::section($profile, 'escalations') as $entry) {
            if (!is_array($entry) || empty($entry['id'])) {
                continue;
            }
            if (array_key_exists('enabled', $entry)
                && empty($entry['enabled'])
            ) {
                continue;
            }
            $entries[] = $entry;
        }
        return $entries;
    }

    /**
     * The share of organisations that makes a stance decisive.
     *
     * Public because the editor asks the same question: a rule whose
     * threshold *follows the profile* has to say in its own box which
     * number it is following, and a second copy of this rule in the
     * form would be a placeholder that lies the day somebody stores
     * `0.4`.
     *
     * @param array|null $profile
     * @return float
     */
    public static function supermajority($profile)
    {
        $thresholds = self::section($profile, 'thresholds');
        if (isset($thresholds['lean_supermajority'])
            && is_numeric($thresholds['lean_supermajority'])
        ) {
            $given = (float)$thresholds['lean_supermajority'];
            /*
             * A threshold at or below a half would make rules 4 and 5
             * both true of the same value and the first one win, which
             * is a coin toss wearing a threshold's clothes. Above 1 it
             * can never be met. Either way the profile is asking for
             * something the derivation cannot express, so the shipped
             * meaning of *supermajority* stands.
             */
            if ($given > 0.5 && $given <= 1.0) {
                return $given;
            }
        }
        return 0.66;
    }

    /**
     * One `parameters` section, whichever shape the profile arrived in.
     *
     * @param array|null $profile
     * @param string $name
     * @return array
     */
    private static function section($profile, $name)
    {
        if (!is_array($profile)) {
            return array();
        }
        if (isset($profile['parameters'][$name])
            && is_array($profile['parameters'][$name])
        ) {
            return $profile['parameters'][$name];
        }
        if (isset($profile[$name]) && is_array($profile[$name])) {
            return $profile[$name];
        }
        return array();
    }

    /**
     * The return shape, in one place so the seven exits cannot drift
     * apart.
     *
     * **`decided_by` names the exit**, because the alternative is a
     * reader of this array re-deriving which rule fired from the lean
     * and the stances — and the derivation has a precedence that is
     * invisible from outside. Rules 3 and 5 both answer `benign`, and
     * a value that is false-positive listed *and* benign by
     * supermajority takes rule 3 because it is written first. Anything
     * composing prose about *why* has to know that, and asking it to
     * work it out again is how two implementations of one rule drift
     * apart.
     *
     * @param string $lean
     * @param array|null $rule
     * @param array $stances
     * @param array $errors
     * @param string $decidedBy Which of the seven exits this is
     * @return array
     */
    private function answer($lean, $rule, array $stances,
        array $errors, $decidedBy
    ) {
        return array(
            'lean' => $lean,
            'rule' => $rule,
            'stances' => $stances,
            'rule_errors' => $errors,
            'decided_by' => $decidedBy,
        );
    }
}
