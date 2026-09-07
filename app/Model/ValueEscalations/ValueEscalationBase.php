<?php

/**
 * What a value escalation is: a class that names a contradiction the
 * counting rules would otherwise flatten.
 *
 * The lean derivation counts organisational stances and picks a side
 * once one side has a supermajority. That counting is right most of the
 * time and wrong in a specific, recognisable way: when two pieces of
 * evidence are *both true* and point opposite ways, arithmetic picks the
 * heavier one and throws the other away. An escalation is how a profile
 * says *"this particular pair of facts is a contradiction, and the
 * honest answer is to say so"*.
 *
 * So an escalation never contributes points and never overrides a
 * number. It replaces the categorical answer with `contested` and
 * carries the sentence that explains which two facts collided. An
 * analyst who wants *"my own infrastructure, never flag it"* wants an
 * exclusion or a benign stance — not a rule that reports a conflict
 * where the analyst has already decided there is none.
 *
 * **Escalations are discovered from the filesystem**, exactly as
 * signals are: the shipped rules live beside this file, an instance
 * admin drops their own in `app/Lib/ValueEscalations/`, and a dropped
 * file changes no answer until a profile lists its id in `escalations`.
 * Discovery makes a rule available; a profile makes it active. The
 * `conflict:` namespace on the shipped ids always implied siblings;
 * this is how they arrive without a MISP release.
 *
 * ## The one asymmetry with a signal, and it matters
 *
 * A signal that will not load is reported in the quality ledger's
 * `not_counted` list, because a quality computed from eight of nine
 * signals and presented as if nine ran is a quiet lie. An escalation
 * contributes nothing to the ledger, so it has nowhere in `not_counted`
 * to be reported — and a rule that could not run leaves the lean
 * unescalated, which is a *silent change of answer*. The loader's error
 * list therefore has to reach the reader, not only the admin's log,
 * which is why `ValueLeanTool` returns `rule_errors` alongside the
 * lean.
 *
 * ## The class to write
 *
 * ```php
 * class ConflictMyRule extends ValueEscalationBase
 * {
 *     public $id = 'conflict:my-rule';
 *     public $reads = array('warninglist');
 *
 *     public function fires(array $context, array $config)
 *     {
 *         if (empty($context['warninglist']['category'])) {
 *             return null;                       // does not apply
 *         }
 *         return array(
 *             'prose' => __('The two facts that collided, in a'
 *                 . ' sentence a reader can check.'),
 *             'evidence' => 'the rows behind it',
 *         );
 *     }
 * }
 * ```
 *
 * Return `null` to stay quiet. Anything else is the rule firing, and
 * the prose is rendered where the lean is stated — so it is written for
 * an analyst reading the page, not for a log.
 *
 * ## The context
 *
 * The same array every signal reads, already scoped to the viewer, plus
 * one key the lean derivation adds before any rule runs:
 *
 * ```
 * stances  ['threat_orgs','benign_orgs','orgs','threat_share',
 *           'supermajority']
 * ```
 *
 * `threat_share` is the fraction of organisations holding at least one
 * `to_ids = 1` occurrence, counted per organisation rather than per
 * occurrence — one org spamming forty events is one vote. `supermajority`
 * is the profile's own threshold, resolved, so a rule written against
 * *"a supermajority"* means whatever the profile in force means by it.
 */
abstract class ValueEscalationBase
{
    /**
     * The loader's handshake, in the shape of its in-tree precedents.
     * A class that subclasses this and overrides nothing else still
     * answers it, so what it proves is that the file's class was
     * constructed rather than merely defined.
     */
    const LOADED = 'CONFLICT NAMED';

    /** The only lean an escalation may emit. */
    const EMITS = 'contested';

    /** The profile's key for this rule, e.g. `conflict:listed-vs-asserted`. */
    public $id = 'to-override';

    /** One line, shown in the editor's palette. */
    public $description = 'to-override';

    /**
     * The lean this rule produces. Constrained to `contested`: a rule
     * that could name a side would be a score override wearing a
     * different hat, and that is the one thing the design took out.
     */
    public $emits = self::EMITS;

    /**
     * The keys this rule reads from `when` — its thresholds, in the
     * same shape a signal's `points_schema` uses, so the editor can
     * render a form for a rule it has never seen.
     *
     * `key => array('type' => 'int'|'float'|'string', 'label' => …,
     * 'default' => …)`.
     */
    public $when_schema = array();

    /**
     * Which context keys this rule needs. A rule whose evidence could
     * not be read does not fire, and says why.
     */
    public $reads = array();

    /** Which panel a reader should go and argue with the rule in. */
    public $source = 'Lifecycle';

    public $version = 1;

    /** Set by the loader on anything found under `app/Lib/`. */
    public $is_custom = false;

    public function checkLoading()
    {
        return self::LOADED;
    }

    /**
     * The identity the loader hands the editor's palette.
     *
     * @return array
     */
    public function getConfig()
    {
        return array(
            'id' => $this->id,
            'description' => $this->description,
            'emits' => $this->emits,
            'when_schema' => $this->when_schema,
            'reads' => $this->reads,
            'source' => $this->source,
            'version' => $this->version,
            'is_custom' => $this->is_custom,
        );
    }

    /**
     * Whether the contradiction this rule names is present.
     *
     * @param array $context The value's aggregated facts, ACL-scoped,
     *                       plus `stances`
     * @param array $config This rule's entry from `profile.escalations`
     * @return array|null null = quiet; otherwise `prose` and, where
     *                    there is one, `evidence`
     */
    abstract public function fires(array $context, array $config);

    /**
     * Whether a profile entry makes sense for this rule.
     *
     * An unknown `when` key is *not* an error: a profile written
     * against a later version of a rule has to survive a downgrade, the
     * same way a signal's unknown points key does.
     *
     * @param array $entry This rule's entry from `profile.escalations`
     * @return array Error strings, empty when the entry is usable
     */
    public function validateEntry(array $entry)
    {
        $errors = array();
        if (isset($entry['emits']) && $entry['emits'] !== self::EMITS) {
            $errors[] = sprintf(
                __('%1$s: a conflict rule may only emit `%2$s`.'),
                $this->id,
                self::EMITS
            );
        }
        $when = isset($entry['when']) && is_array($entry['when'])
            ? $entry['when']
            : array();
        foreach ($this->when_schema as $key => $spec) {
            if (!array_key_exists($key, $when)) {
                continue;
            }
            $error = $this->checkValue($when[$key], $spec, $key);
            if ($error !== null) {
                $errors[] = $error;
            }
        }
        return $errors;
    }

    /**
     * @param mixed $given
     * @param array $spec
     * @param string $key
     * @return string|null
     */
    private function checkValue($given, array $spec, $key)
    {
        $type = isset($spec['type']) ? $spec['type'] : 'int';
        if ($type === 'int' && !is_int($given)) {
            return sprintf(
                __('%1$s: `when.%2$s` must be a whole number.'),
                $this->id,
                $key
            );
        }
        if ($type === 'float' && !is_int($given) && !is_float($given)) {
            return sprintf(
                __('%1$s: `when.%2$s` must be a number.'),
                $this->id,
                $key
            );
        }
        if ($type === 'string' && !is_string($given)) {
            return sprintf(
                __('%1$s: `when.%2$s` must be a word.'),
                $this->id,
                $key
            );
        }
        return null;
    }

    /**
     * One `when` value, falling back to the schema's default so that a
     * profile carrying half a map still runs the rule.
     *
     * @param array $config This rule's profile entry
     * @param string $key
     * @param mixed $fallback Used when the schema has no default
     * @return mixed
     */
    protected function when(array $config, $key, $fallback = null)
    {
        if (isset($config['when'])
            && array_key_exists($key, $config['when'])
        ) {
            return $config['when'][$key];
        }
        if (isset($this->when_schema[$key]['default'])) {
            return $this->when_schema[$key]['default'];
        }
        return $fallback;
    }

    /**
     * A share threshold, which a profile may state either as a number
     * or as the word `supermajority`.
     *
     * The word is the useful form: a rule written against *"a
     * supermajority of organisations"* should mean whatever the profile
     * in force means by that, and an analyst who moves
     * `lean_supermajority` from 0.66 to 0.75 should not have to
     * remember to move it in two places as well.
     *
     * @param array $config
     * @param string $key
     * @param array $context
     * @return float
     */
    protected function shareThreshold(array $config, $key,
        array $context
    ) {
        $given = $this->when($config, $key, 'supermajority');
        if (is_numeric($given)) {
            return (float)$given;
        }
        return isset($context['stances']['supermajority'])
            ? (float)$context['stances']['supermajority']
            : 0.66;
    }

    /**
     * How many organisations hold this value, which is what every
     * shipped rule means by *reports*: an organisation is one voice
     * however many events it puts the value in.
     *
     * @param array $context
     * @return int
     */
    protected function orgCount(array $context)
    {
        if (isset($context['stances']['orgs'])) {
            return (int)$context['stances']['orgs'];
        }
        return isset($context['orgs']) ? count($context['orgs']) : 0;
    }

    /**
     * The names of the lists that resolved to one category, for the
     * evidence line under the rule.
     *
     * @param array $context
     * @param string $category
     * @return array
     */
    protected function listsInCategory(array $context, $category)
    {
        $names = array();
        $hits = isset($context['warninglist']['hits'])
            ? $context['warninglist']['hits']
            : array();
        foreach ($hits as $hit) {
            if (($hit['category'] ?? null) === $category) {
                $names[] = $hit['name'];
            }
        }
        return $names;
    }
}
