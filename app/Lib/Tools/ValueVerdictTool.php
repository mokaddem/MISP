<?php

App::uses('ValueSignalLoader', 'Tools');
App::uses('ValueStatsTool', 'Tools');
/*
 * Loaded here rather than by the three signals that use it: a signal
 * file is discovered from the filesystem and required by the loader,
 * which is not a place `App::uses` has run — and the engine is the one
 * thing guaranteed to be in memory before any signal evaluates.
 */
App::uses('ValueTrustTool', 'Tools');
App::uses('ValueLeanTool', 'Tools');
App::uses('ValueChangersTool', 'Tools');
App::uses('ValueRelevanceTool', 'Tools');

/**
 * The accumulator: a profile plus a value's facts, in; a ledger that
 * sums to a number, out.
 *
 * The thing the Assessment tab has been blocked on since the skeleton
 * pass — `value-profile-page.md` §5 has said *"the page displays a
 * verdict; it does not compute one"* since the first commit — and phase
 * 2 of prd/analyst-profile/. Under D11 what it computes is the
 * assessment's **quality** axis: lean is derived categorically
 * (phase 3) and relevance is its own axis (phase 5), so this file is
 * the ledger and the number, not the whole judgement.
 *
 * ## The mechanism, and why it is one loop
 *
 * ```
 * for each enabled signal in profile.signals:
 *     evaluate it against the context           → an outcome
 *     fired:          points = f(outcome, points)   emit a ledger row
 *     silent:         no row and no note
 *     could not run:  a not_counted entry
 *
 * polarity = +1 (threat lean) | −1 (benign lean)
 * row      = points × polarity
 * quality  = Σ anchored rows
 * direction of a row = sign(row)
 * ```
 *
 * **The sum is the quality by construction rather than by
 * convention** (`01-profile.md` §5.1). There is no second code path
 * that could disagree with the ledger — no normalisation, no
 * calibration, no post-processing — which is what makes a profile diff
 * renderable and what lets the page claim the explanation *is* the
 * score. Every guard in this file that looks defensive is protecting
 * that one property: a signal returning a float, a string or an array
 * would break it silently, so a malformed row is treated as *could not
 * run* and named on the page instead.
 *
 * **`direction` is derived, never stored.** It is the sign of the
 * anchored row, so the same declaration renders upward on a threat lean
 * and downward on a benign one. A profile storing a direction would
 * have to keep it in step with the sign of its own points.
 *
 * ## What it does not do
 *
 * No view dependency of any kind, and that is a requirement rather
 * than a preference (`01-profile.md` §5.5): phase 10 calls this from a
 * REST path over a batch of values, so a signal reaching for `$this->
 * Html` or a helper would make that phase a rewrite.
 *
 * It also stores nothing. The assessment is computed at render time,
 * which is what makes per-viewer weighting cheap and gives a
 * user-defined signal no sync blast radius.
 *
 * ## The seventh rule, which lives here rather than with the lean
 *
 * The lean's categorical rules are `ValueLeanTool`'s and run before any
 * scoring. One of them cannot: **a lean whose anchored quality comes
 * out below zero becomes contested**, because a negative quality means
 * the ledger, row by row, disputes the assertion the record itself
 * makes. That can only be known after the sum, so it is applied here —
 * the rows are re-anchored threat-signed, the lean becomes contested
 * and the tug shows the two sides. It is the state the design's earlier
 * one-number model had no way to express: it would have printed a
 * negative score under the word MALICIOUS.
 *
 * ## What is still an input
 *
 * **The exclusions.** Only `evidence.window` is read here, because the
 * evidence budget is what bounds a context this file has to score; the
 * rest of the section reaches signals as `$context['excluded']`.
 */
class ValueVerdictTool
{
    /** The ledger's group order, and the composition card's. */
    const GROUP_ORDER = array(
        'Reporting',
        'Sightings',
        'Attribution',
        'Lifecycle',
    );

    /** The quality bands, weakest first, so one can be capped to another. */
    const BANDS = array('none', 'low', 'medium', 'high');

    /** Lean → the ledger's polarity. */
    const POLARITY = array(
        'threat' => 1,
        'benign' => -1,
        'contested' => 1,
        'none' => 1,
    );

    /**
     * The model that owns the data, injected the way `Event.php` does
     * it with `new TrendingTool($this)`. Only `verdictFor()` uses it;
     * `assess()` is pure and needs nothing.
     *
     * @var Model|null
     */
    private $model;

    /**
     * @param Model|null $model Something answering
     *                          `verdictContextFor()` and carrying an
     *                          `AnalystProfile` — `ValueProfile`, in
     *                          practice
     */
    public function __construct($model = null)
    {
        $this->model = $model;
    }

    /**
     * The whole computation for one value: resolve the profile, build
     * the context, score it.
     *
     * **This is the one method that takes `$user`**, and §14.5 of the
     * live contract says no `Value*` tool does. The exception is argued
     * in `03-signals.md` §2.1 rather than assumed: every count an
     * assessment reads is already the viewer's (§14.6), and a tool
     * computing an assessment from data it could not scope would be
     * computing somebody else's. What it does not do is fetch — the
     * context comes from the injected model, which is where the queries
     * and the ACL live, and phase 10 swaps a batch builder in behind
     * the same seam.
     *
     * @param array $user
     * @param string $value
     * @param array $options `lean`, `profile`, `context`, plus
     *                       whatever the context builder takes
     * @return array
     */
    public function verdictFor(array $user, $value,
        array $options = array()
    ) {
        $profile = isset($options['profile'])
            ? $options['profile']
            : $this->profileFor($user);
        $context = isset($options['context'])
            ? $options['context']
            : $this->contextFor($user, $value, $profile, $options);
        return $this->assess($context, $profile, $options);
    }

    /**
     * The accumulator. No database, no models, no `$user` — hand it a
     * context and a profile and it returns the same array every time,
     * which is what lets the page, the simulator and phase 10's worker
     * agree.
     *
     * @param array $context From the context builder; the contract is
     *                       documented on `ValueSignalBase`
     * @param array|null $profile An `AnalystProfile` row, unwrapped, or
     *                            null when scoring is switched off
     * @param array $options `lean` forces one instead of deriving it,
     *                       which is how a ledger is scored against a
     *                       stated lean rather than a counted one
     * @return array
     */
    public function assess(array $context, $profile,
        array $options = array()
    ) {
        $derived = $this->derive($context, $profile, $options);
        $lean = $derived['lean'];
        $polarity = isset(self::POLARITY[$lean])
            ? self::POLARITY[$lean]
            : 1;
        /*
         * A `none` lean has no ledger. **And neither has a value with
         * no occurrence this viewer can see**, whatever lean the caller
         * forced — the lean's own first rule, stated here as a fact
         * about the context, because the absence keys would otherwise
         * fire on emptiness: no warninglist hit (+6), no galaxy (−7),
         * nobody sighted it (−4) are all true of a value that does not
         * exist for this reader, and scoring them is the engine reading
         * its own blindness as evidence.
         */
        $occurrences = isset($context['occurrences']['total'])
            ? (int)$context['occurrences']['total']
            : 0;
        if ($lean === 'none' || $occurrences === 0) {
            return $this->nothingToAssess($derived, $polarity,
                $context, $profile);
        }
        $entries = $this->signalEntries($profile);

        $rows = array();
        $notCounted = array();
        $counts = array(
            'configured' => count($entries),
            'evaluated' => 0,
            'fired' => 0,
            'silent' => 0,
            'not_counted' => 0,
        );

        foreach ($entries as $entry) {
            $id = isset($entry['id']) ? $entry['id'] : null;
            if ($id === null || $id === '') {
                continue;
            }
            if (array_key_exists('enabled', $entry)
                && empty($entry['enabled'])
            ) {
                // Not evaluated, and not recorded either: a disabled
                // signal emits nothing at all (§3).
                $counts['configured']--;
                continue;
            }
            $outcome = $this->runSignal($id, $entry, $context);
            if ($outcome['state'] === 'not_counted') {
                $notCounted[] = $outcome['entry'];
                $counts['not_counted']++;
                continue;
            }
            $counts['evaluated']++;
            if ($outcome['state'] === 'silent') {
                $counts['silent']++;
                continue;
            }
            $rows[] = $this->anchor(
                $outcome['row'],
                $entry,
                $outcome['signal'],
                $polarity
            );
            $counts['fired']++;
        }

        foreach ($this->setAside($context) as $note) {
            $notCounted[] = $note;
        }

        $quality = $this->sum($rows);

        /*
         * Rule 7. A ledger that sums below zero against the lean it was
         * anchored to is a record disputing its own assertion, and the
         * honest state for that is contested — not a negative gauge
         * under a confident word. The rows go back to threat-signed,
         * which is how a contested ledger renders, and the tug below
         * shows the two sides that could not be reconciled.
         *
         * It cannot run twice: re-anchoring a negative sum with a
         * polarity of −1 makes it positive, and a polarity of +1 leaves
         * it where it was with the lean already contested.
         */
        if ($quality < 0) {
            $rows = $this->reanchor($rows, $polarity);
            $quality = $this->sum($rows);
            $lean = 'contested';
            $polarity = 1;
        }
        $ledger = $this->group($rows);

        return $this->verdict(array(
            'lean' => $lean,
            'derived' => $derived,
            'polarity' => $polarity,
            'quality' => $quality,
            'band' => self::qualityBand(
                $quality,
                $counts['fired'],
                $profile,
                $context
            ),
            'ledger' => $ledger,
            'tug' => $this->tug($rows, $polarity),
            'not_counted' => $notCounted,
            'counts' => $counts,
            'context' => $context,
            'profile' => $profile,
        ));
    }

    /**
     * The lean this assessment is anchored to, counted or forced.
     *
     * A forced lean skips the rules entirely — it is how a ledger is
     * scored against a stated lean rather than a counted one, which is
     * what a profile simulator and a re-anchoring regression both need.
     * The stances still come back, because they cost nothing and the
     * falsifiability line needs them either way.
     *
     * @param array $context
     * @param array|null $profile
     * @param array $options
     * @return array
     */
    private function derive(array $context, $profile, array $options)
    {
        $lean = new ValueLeanTool();
        if (!isset($options['lean'])) {
            return $lean->leanFor($context, $profile);
        }
        return array(
            'lean' => $options['lean'],
            'rule' => null,
            'stances' => $lean->stancesFor($context, $profile),
            'rule_errors' => array(),
        );
    }

    /**
     * A value with nothing to assess: no lean to anchor to, or no
     * occurrence this viewer can see.
     *
     * The same array shape, so a caller never has to ask which kind of
     * assessment it is holding — and the profile is still named,
     * because the profile *was* in force; it simply had nothing to
     * weigh.
     *
     * @param array $derived
     * @param int $polarity
     * @param array $context
     * @param array|null $profile
     * @return array
     */
    private function nothingToAssess(array $derived, $polarity,
        array $context, $profile
    ) {
        return $this->verdict(array(
            'lean' => $derived['lean'],
            'derived' => $derived,
            'polarity' => $polarity,
            'quality' => 0,
            'band' => 'none',
            'ledger' => array(),
            'tug' => $this->tug(array(), $polarity),
            'not_counted' => array(),
            'counts' => array('configured' => 0, 'evaluated' => 0,
                'fired' => 0, 'silent' => 0, 'not_counted' => 0),
            'context' => $context,
            'profile' => $profile,
        ));
    }

    /**
     * The exact-sum invariant, in the one place that computes it.
     *
     * @param array $rows
     * @return int
     */
    private function sum(array $rows)
    {
        $quality = 0;
        foreach ($rows as $row) {
            $quality += $row['contribution'];
        }
        return $quality;
    }

    /**
     * Put a ledger back to threat-signed, which is how a contested
     * ledger renders: no side to support, so no polarity to apply.
     *
     * @param array $rows
     * @param int $polarity The polarity they were anchored with
     * @return array
     */
    private function reanchor(array $rows, $polarity)
    {
        foreach ($rows as $index => $row) {
            $contribution = (int)$row['contribution'] * $polarity;
            $rows[$index]['contribution'] = $contribution;
            $rows[$index]['direction'] = $contribution < 0
                ? 'down'
                : 'up';
        }
        return $rows;
    }

    /**
     * The two one-sided sums a contested layout puts against each
     * other: what supports the record's assertion, and what disputes
     * it.
     *
     * Both come out of the same rows the other leans render — there is
     * no third bucket and no separate computation, which is what lets a
     * reader add the ledger up by hand and arrive at the bar.
     *
     * **Two keys, not five.** The fixture's third *unresolved* wedge
     * was never derivable from anything (`review-2026-09-02.md` B3) and
     * it stayed here as a hard zero until the layout stopped reading
     * it, which it did in phase 9; the `malicious` / `benign` aliases
     * went with D11's rename in the same pass. What made the zero worth
     * chasing rather than leaving is `10-wiring.md` §7.8: the wedge and
     * the foot printed directly under it counted *different things*
     * under one word, and the fixture concealed it by supplying both.
     *
     * @param array $rows
     * @param int $polarity
     * @return array
     */
    private function tug(array $rows, $polarity)
    {
        $support = 0;
        $dispute = 0;
        foreach ($rows as $row) {
            $threatSigned = (int)$row['contribution'] * $polarity;
            if ($threatSigned >= 0) {
                $support += $threatSigned;
            } else {
                $dispute -= $threatSigned;
            }
        }
        return array(
            'support' => $support,
            'dispute' => $dispute,
        );
    }

    /**
     * The return shape, in one place so the two paths into it cannot
     * drift apart.
     *
     * @param array $parts
     * @return array
     */
    private function verdict(array $parts)
    {
        $lean = $parts['lean'];
        $quality = $parts['quality'];
        $ledger = $parts['ledger'];
        $context = $parts['context'];
        $profile = $parts['profile'];
        $derived = $parts['derived'];

        $verdict = array(
            'lean' => $lean,
            'derived_lean' => $derived['lean'],
            'rule' => $derived['rule'],
            'rule_errors' => $derived['rule_errors'],
            'stances' => $derived['stances'],
            /*
             * Which of the seven exits produced the lean. Carried so a
             * reader of this array never has to re-derive the
             * derivation's own precedence — `ValueLeanTool::answer()`
             * says what goes wrong when it does.
             */
            'decided_by' => $derived['decided_by'],
            'polarity' => $parts['polarity'],
            'quality' => $quality,
            'band' => $parts['band'],
            /*
             * Assembled here rather than beside either `band`, because
             * there are two of those — the scored path and the
             * no-signal one — and a key computed in one of them is a
             * key a template reads as missing on the other. §8's
             * lesson about defaulting in one place, applied to a key
             * that is produced rather than defaulted.
             */
            'band_reason' => self::bandReason(
                $parts['quality'],
                $parts['counts']['fired'],
                $parts['profile'],
                $parts['context']
            ),
            /*
             * The second axis, assembled beside the quality and not out
             * of it. It reads the same context and the same profile,
             * emits no ledger row, and is computed here rather than by
             * the caller so that one `assess()` returns the whole
             * assessment — the page, the simulator and phase 10's
             * worker cannot then disagree about a value's relevance
             * while agreeing about its quality.
             *
             * **Nothing below reads it**, which is D11 held to
             * mechanically: the ledger, the band, the tug and the
             * composition are all computed already, so an axis added
             * here cannot alter any of them. §6 item 3 asserts exactly
             * that — the lean and the quality are byte-identical with
             * relevance at `current` and at `expired`.
             */
            'relevance' => ValueRelevanceTool::relevanceFor(
                $context,
                $profile
            ),
            'ledger' => $ledger,
            'tug' => $parts['tug'],
            'composition' => ValueStatsTool::verdictComposition($ledger),
            'not_counted' => $parts['not_counted'],
            'signals' => $parts['counts'],
            'profile' => $this->profileName($profile),
            'profile_id' => $profile === null
                ? null
                : (isset($profile['id']) ? (int)$profile['id'] : null),
            'profile_revision' => $profile === null
                ? null
                : (isset($profile['revision'])
                    ? (int)$profile['revision']
                    : null),
            'computed_at' => isset($context['now'])
                ? (int)$context['now']
                : time(),
            'as_of' => isset($context['as_of'])
                ? $context['as_of']
                : date('Y-m-d'),
        );

        /*
         * Last, because a falsifiability line is derived *from* the
         * assessment — the lean probe re-runs the rules and the quality
         * probe re-runs the banding, and both need the finished answer
         * to say what would move it.
         */
        $changers = new ValueChangersTool();
        $verdict['changers'] = $changers->changersFor(
            $verdict,
            $context,
            $profile
        );

        return $verdict;
    }

    /**
     * One signal: resolve it, decide whether it may run, run it, and
     * check what it returned.
     *
     * Every failure path lands in the same place — `not_counted`, with
     * the id named and a reason — because §4.4 and §8.5 want an
     * unavailable signal *on the page*: a quality number computed from
     * eight of nine configured signals and presented as if nine ran is
     * exactly the quiet lie `01-profile.md` §1.3 forbids.
     *
     * @param string $id
     * @param array $entry The profile's entry for it
     * @param array $context
     * @return array `state` of fired|silent|not_counted, plus `row`
     *               and `signal`, or `entry`
     */
    private function runSignal($id, array $entry, array $context)
    {
        $signal = ValueSignalLoader::get($id);
        if ($signal === null) {
            return $this->cannotRun(
                $id,
                $this->missingReason($id),
                'unavailable'
            );
        }
        $blocked = $this->budgetBlocks($signal, $context);
        if ($blocked !== null) {
            return $this->cannotRun($id, $blocked, 'nodata');
        }
        $unreadable = $this->unreadable($signal, $context);
        if ($unreadable !== null) {
            return $this->cannotRun($id, $unreadable, 'nodata');
        }
        try {
            $row = $signal->evaluate($context, $entry);
        } catch (Throwable $e) {
            /*
             * A dropped file's `evaluate()` throwing must not take the
             * page down (§8.5), and the exception text is what an admin
             * needs to fix it.
             */
            return $this->cannotRun(
                $id,
                sprintf(
                    __('The signal failed: %s'),
                    $e->getMessage()
                ),
                'broken'
            );
        }
        if ($row === null) {
            return array('state' => 'silent');
        }
        $invalid = $this->rowFault($row);
        if ($invalid !== null) {
            return $this->cannotRun($id, $invalid, 'broken');
        }
        return array(
            'state' => 'fired',
            'row' => $row,
            'signal' => $signal,
        );
    }

    /**
     * Why a returned row cannot be trusted, or null when it can.
     *
     * `contribution` has to be an integer, and this is the check that
     * protects the exact-sum invariant from third-party code: a float
     * would make the ledger sum to something the printed rows do not,
     * and it would do it invisibly.
     *
     * @param mixed $row
     * @return string|null
     */
    private function rowFault($row)
    {
        if (!is_array($row)) {
            return __('The signal returned something that is not a'
                . ' ledger row.');
        }
        if (!array_key_exists('contribution', $row)) {
            return __('The row carries no contribution.');
        }
        if (!is_int($row['contribution'])) {
            return __('The contribution is not a whole number, so the'
                . ' ledger could not sum to the quality.');
        }
        if (empty($row['signal']) || !is_string($row['signal'])) {
            return __('The row states no signal.');
        }
        return null;
    }

    /**
     * Whether §2.3's budget stops this signal running at all.
     *
     * The hot-value tier: a value MISP itself flagged as
     * over-correlating is one a time window cannot bound, because a
     * live campaign puts everything inside 90 days. Row-hungry signals
     * bow out and say so; the aggregate-class ones still fire, and the
     * quality is computed from what could be read.
     *
     * @param object $signal
     * @param array $context
     * @return string|null
     */
    private function budgetBlocks($signal, array $context)
    {
        if (empty($context['budget']['hot'])) {
            return null;
        }
        if ($signal->evidence_class !== ValueSignalBase::EVIDENCE_ROW) {
            return null;
        }
        return __('Too much data — this value is flagged as'
            . ' over-correlating, so the rows this signal reads were'
            . ' not fetched.');
    }

    /**
     * Whether a fact this signal declared it reads could not be read.
     *
     * `$context['missing']` is how the context builder reports a
     * source it could not reach — a feed cache that has never been
     * populated, a sighting policy that hid everything. A signal
     * scoring that as *absent* would turn a gap into evidence, which
     * §4.2's exclusion rule forbids for the same reason.
     *
     * @param object $signal
     * @param array $context
     * @return string|null
     */
    private function unreadable($signal, array $context)
    {
        if (empty($context['missing'])) {
            return null;
        }
        foreach ($signal->reads as $key) {
            if (isset($context['missing'][$key])) {
                return $context['missing'][$key];
            }
        }
        return null;
    }

    /**
     * §4.4 and §8.5 differ only in the reason string: an id this
     * instance does not have at all, against one it has but could not
     * load.
     *
     * @param string $id
     * @return string
     */
    private function missingReason($id)
    {
        foreach (ValueSignalLoader::errors() as $file => $reason) {
            if (strpos($reason, '`' . $id . '`') !== false) {
                return sprintf(
                    __('The implementation did not load (%1$s): %2$s'),
                    $file,
                    $reason
                );
            }
        }
        return __('This instance has no implementation for that'
            . ' signal, so the profile weighted something that could'
            . ' not run.');
    }

    /**
     * @param string $id
     * @param string $reason
     * @param string $kind `unavailable`, `nodata` or `broken`
     * @return array
     */
    private function cannotRun($id, $reason, $kind)
    {
        return array(
            'state' => 'not_counted',
            'entry' => array(
                'title' => $id,
                'note' => $reason,
                /*
                 * `reason` is the render-level grouping — what a reader
                 * can do about the entry — and `kind` with `source` are
                 * the diagnostic detail behind it. A signal that could
                 * not run is always `nodata` however it failed: the
                 * three ways it can fail matter to whoever fixes it,
                 * and to a reader they are one statement, *this was not
                 * counted and not by anybody's choice*.
                 */
                'reason' => 'nodata',
                'kind' => $kind,
                'source' => 'signal',
                'id' => $id,
            ),
        );
    }

    /**
     * The budget's own `not_counted` entries — the evidence window,
     * stated as the profile policy it is.
     *
     * @param array $context
     * @return array
     */
    private function setAside(array $context)
    {
        $notes = array();
        /*
         * The profile's own exclusions, computed where the filtering
         * happened. They lead the block because they are the analyst's
         * decisions rather than the engine's, and so the only rows a
         * reader can do anything about.
         */
        if (!empty($context['exclusions'])
            && is_array($context['exclusions'])
        ) {
            foreach ($context['exclusions'] as $note) {
                $notes[] = $note;
            }
        }
        $budget = isset($context['budget'])
            ? $context['budget']
            : array();
        if (!empty($budget['window_days'])) {
            $notes[] = array(
                'title' => __('Long history'),
                'note' => sprintf(
                    __('Scored from the last %d days of row evidence.'
                        . ' Counts and dates are whole-history.'),
                    (int)$budget['window_days']
                ),
                'reason' => 'policy',
                'kind' => 'policy',
                'source' => 'exclusion',
                'id' => 'evidence.window',
            );
        }
        if (!empty($budget['hot'])) {
            $notes[] = array(
                'title' => __('Over-correlating value'),
                'note' => __('MISP has flagged this value as too'
                    . ' common to correlate, so the signals that read'
                    . ' individual rows were not evaluated.'),
                'reason' => 'nodata',
                'kind' => 'nodata',
                'source' => 'budget',
                'id' => 'over_correlating_values',
            );
        }
        return $notes;
    }

    /**
     * Turn a fired row into a ledger row: the group and the band come
     * from the profile, the anchoring and the direction from the lean.
     *
     * @param array $row What the implementation returned
     * @param array $entry The profile's entry
     * @param object $signal
     * @param int $polarity
     * @return array
     */
    private function anchor(array $row, array $entry, $signal, $polarity)
    {
        $contribution = (int)$row['contribution'] * $polarity;
        return array(
            'kind' => !empty($entry['group'])
                ? $entry['group']
                : $signal->group,
            'direction' => $contribution < 0 ? 'down' : 'up',
            'contribution' => $contribution,
            'signal' => $row['signal'],
            'evidence' => isset($row['evidence'])
                ? $row['evidence']
                : '',
            'source' => isset($row['source'])
                ? $row['source']
                : $signal->source,
            'as_of' => isset($row['as_of']) ? $row['as_of'] : '',
            'id' => $signal->id,
        );
    }

    /**
     * Group the rows the way `value_verdict_ledger.ctp` renders them —
     * by kind, in a fixed order, with the group's note.
     *
     * Grouped by kind rather than sorted by weight for the reason the
     * template's own docblock gives: an analyst checking whether the
     * sightings were counted twice wants them next to each other.
     *
     * @param array $rows
     * @return array
     */
    private function group(array $rows)
    {
        $byKind = array();
        foreach ($rows as $row) {
            $byKind[$row['kind']][] = $row;
        }
        $ordered = array();
        $kinds = array_keys($byKind);
        $known = array_values(self::GROUP_ORDER);
        $custom = array_values(array_diff($kinds, $known));
        foreach (array_merge($known, $custom) as $kind) {
            if (empty($byKind[$kind])) {
                continue;
            }
            $ordered[] = array(
                'kind' => $kind,
                'note' => $this->groupNote($kind),
                'signals' => $byKind[$kind],
            );
        }
        return $ordered;
    }

    /**
     * The one-line note under a group heading, as the fixture words
     * them. A custom group gets no note rather than a guessed one.
     *
     * A switch rather than a map, so the strings are literals where
     * `__()` sees them — a translated string built from a variable is
     * one the extractor never finds.
     *
     * @param string $kind
     * @return string
     */
    private function groupNote($kind)
    {
        switch ($kind) {
            case 'Reporting':
                return __('who reported it, and how widely');
            case 'Sightings':
                return __('who has seen it, and how recently');
            case 'Attribution':
                return __('what it has been linked to');
            case 'Lifecycle':
                return __('whether it is still worth acting on');
        }
        return '';
    }

    /**
     * The four-segment gauge's band — the field the page used to call
     * `confidence` and could never say where it came from.
     *
     * Bands are calibration and the stakes are deliberately low: a
     * misplaced band miscolours a gauge, it does not change what the
     * record asserts. That is the whole reason the three axes were
     * split apart.
     *
     * `quality_high_min_signals` is what stops one heavy row buying a
     * `high` band on its own: a value's quality is high when several
     * independent readings agree, not when one signal is generous.
     *
     * @param int $quality
     * @param int $fired
     * @param array|null $profile
     * @param array $context Needed for the thin-record clamp; an empty
     *                       array asks for the unclamped band, which
     *                       is how a caller finds out whether the
     *                       clamp is what is holding a band down
     * @return string `none`, `low`, `medium` or `high`
     */
    public static function qualityBand($quality, $fired, $profile,
        array $context = array()
    ) {
        if ($fired === 0) {
            return 'none';
        }
        $thresholds = self::section($profile, 'thresholds');
        $bands = isset($thresholds['quality_bands'])
            ? $thresholds['quality_bands']
            : array();
        $high = isset($bands['high']) ? (int)$bands['high'] : 60;
        $medium = isset($bands['medium']) ? (int)$bands['medium'] : 30;
        $minSignals = isset($thresholds['quality_high_min_signals'])
            ? (int)$thresholds['quality_high_min_signals']
            : 4;
        if ($quality >= $high && $fired >= $minSignals) {
            $band = 'high';
        } elseif ($quality >= $medium) {
            $band = 'medium';
        } else {
            $band = 'low';
        }
        return self::clamped($band, $thresholds, $context);
    }

    /**
     * Which of the band's four ways out produced this band, and the
     * numbers it was decided against.
     *
     * `qualityBand()` answers *what* and has three callers that only
     * want that; this answers *why* without changing its signature.
     * The ledger has printed the arithmetic since §8 and the hero the
     * band since the skeleton pass, and between them sat the one thing
     * neither said: the floor. Lean names its supermajority and
     * relevance names its TTL since `10-wiring.md` §17 and §18 — the
     * profile setting that decided the state belongs on the page, and
     * quality was the axis still not naming one.
     *
     * **`clamped` is detected, not predicted.** The band is computed
     * twice, once with the context and once without, and a difference
     * is the clamp — the same trick `ValueChangersTool::clampPhrase()`
     * uses, and for the same reason: the clamp's conditions live in
     * the profile and re-reading them here would be a second
     * implementation of them.
     *
     * @param int $quality
     * @param int $fired
     * @param array|null $profile
     * @param array $context
     * @return array `reason` — `no_signal`, `clamped`, `min_signals`
     *               or `points` — and `floors`, the numbers in force
     */
    public static function bandReason($quality, $fired, $profile,
        array $context = array()
    ) {
        $thresholds = self::section($profile, 'thresholds');
        $bands = isset($thresholds['quality_bands'])
            ? $thresholds['quality_bands']
            : array();
        $clamp = isset($thresholds['thin_record_clamp'])
            && is_array($thresholds['thin_record_clamp'])
            ? $thresholds['thin_record_clamp']
            : array();
        $floors = array(
            'medium' => isset($bands['medium']) ? (int)$bands['medium'] : 30,
            'high' => isset($bands['high']) ? (int)$bands['high'] : 60,
            'min_signals' => isset($thresholds['quality_high_min_signals'])
                ? (int)$thresholds['quality_high_min_signals']
                : 4,
            'clamp_band' => isset($clamp['max_band'])
                ? $clamp['max_band']
                : null,
            'clamp_orgs' => isset($clamp['max_orgs'])
                ? (int)$clamp['max_orgs']
                : null,
            'clamp_sightings' => isset($clamp['max_sightings'])
                ? (int)$clamp['max_sightings']
                : null,
        );

        if ((int)$fired === 0) {
            return array('reason' => 'no_signal', 'floors' => $floors);
        }
        $withContext = self::qualityBand($quality, $fired, $profile,
            $context);
        $unclamped = self::qualityBand($quality, $fired, $profile);
        if ($withContext !== $unclamped) {
            $floors['would_be'] = $unclamped;
            return array('reason' => 'clamped', 'floors' => $floors);
        }
        if ($quality >= $floors['high'] && $fired < $floors['min_signals']) {
            return array(
                'reason' => 'min_signals',
                'floors' => $floors,
            );
        }
        return array('reason' => 'points', 'floors' => $floors);
    }

    /**
     * The thin-record clamp: a ceiling on the band for a record with
     * one source and nothing corroborating it.
     *
     * It exists because the weights alone cannot express it, which was
     * measured rather than assumed. A single organisation reporting the
     * same value every month for over a year, carried by a few feeds,
     * reaches the `medium` band under any weighting that still says
     * something useful about the values that *do* have corroboration —
     * the positives such a record can honestly attain simply sum past
     * the floor. That is not obviously the wrong answer, but it is not
     * what an analyst means by *how much can I trust this*, and the
     * place to say so is the band rather than the arithmetic: a clamp
     * leaves the ledger's own sum intact and visible, where a weighting
     * would have shrunk every signal to hide one shape.
     *
     * So it is stated as its own threshold, in the profile, with the
     * whole condition named — how many sources still count as one, how
     * many sightings still count as none, and what the ceiling is. An
     * analyst who disagrees edits three numbers; an analyst who wants
     * no clamp deletes the section.
     *
     * A caller passing no context gets the unclamped band, which is
     * how the falsifiability line finds out whether the clamp is what
     * is holding a record down.
     *
     * @param string $band
     * @param array $thresholds
     * @param array $context
     * @return string
     */
    private static function clamped($band, array $thresholds,
        array $context
    ) {
        if (empty($context)) {
            return $band;
        }
        $clamp = isset($thresholds['thin_record_clamp'])
            && is_array($thresholds['thin_record_clamp'])
            ? $thresholds['thin_record_clamp']
            : array();
        if (empty($clamp['max_band'])) {
            return $band;
        }
        $ceiling = array_search($clamp['max_band'], self::BANDS, true);
        $reached = array_search($band, self::BANDS, true);
        if ($ceiling === false || $reached === false
            || $reached <= $ceiling
        ) {
            return $band;
        }
        $orgs = isset($context['occurrences']['orgs'])
            ? (int)$context['occurrences']['orgs']
            : 0;
        $sightings = isset($context['sightings']['total'])
            ? (int)$context['sightings']['total']
            : 0;
        $maxOrgs = isset($clamp['max_orgs'])
            ? (int)$clamp['max_orgs']
            : 1;
        $maxSightings = isset($clamp['max_sightings'])
            ? (int)$clamp['max_sightings']
            : 0;
        if ($orgs <= $maxOrgs && $sightings <= $maxSightings) {
            return $clamp['max_band'];
        }
        return $band;
    }

    /**
     * The profile's `signals` list, or nothing when there is no
     * profile.
     *
     * A viewer with no profile in force is a real state, not an error:
     * `AnalystProfile::resolveFor()` returns null when a site admin has
     * disabled the default, and the page then has a lean and no
     * quality (§9 items 6 and 7).
     *
     * @param array|null $profile
     * @return array
     */
    private function signalEntries($profile)
    {
        $signals = self::section($profile, 'signals');
        if (!is_array($signals)) {
            return array();
        }
        $entries = array();
        foreach ($signals as $entry) {
            if (is_array($entry)) {
                $entries[] = $entry;
            }
        }
        return $entries;
    }

    /**
     * One `parameters` section, whichever shape the profile arrived in
     * — the model's `afterFind` decodes `parameters` into an array, and
     * a caller building a profile by hand may hand over the parameters
     * alone.
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
     * What the hero names as the profile in force.
     *
     * A profile whose `parameters` would not parse still gets named:
     * the profile *was* the thing that weighted the assessment,
     * imperfectly, and naming something else would make the hero's
     * sentence untrue (phase 1's `afterFind`, §3.1 there).
     *
     * @param array|null $profile
     * @return string|null
     */
    private function profileName($profile)
    {
        if (!is_array($profile)) {
            return null;
        }
        return isset($profile['name']) ? $profile['name'] : null;
    }

    /**
     * @param array $user
     * @return array|null
     */
    private function profileFor(array $user)
    {
        if ($this->model === null) {
            return null;
        }
        return ClassRegistry::init('AnalystProfile')->resolveFor($user);
    }

    /**
     * @param array $user
     * @param string $value
     * @param array|null $profile
     * @param array $options
     * @return array
     */
    private function contextFor(array $user, $value, $profile,
        array $options
    ) {
        if ($this->model === null
            || !method_exists($this->model, 'verdictContextFor')
        ) {
            throw new InvalidArgumentException(
                __('ValueVerdictTool needs either a context or a model'
                    . ' that can build one.')
            );
        }
        return $this->model->verdictContextFor(
            $user,
            $value,
            $profile,
            $options
        );
    }
}
