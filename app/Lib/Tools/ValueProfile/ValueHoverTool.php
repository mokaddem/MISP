<?php

App::uses('ValueLean', 'Tools/ValueProfile');
App::uses('ValueStatsTool', 'Tools/ValueProfile');

/**
 * The hover card's payload, folded from an assessment already made.
 *
 * The card is shown when a reader hovers a value anywhere in MISP, and
 * it answers one question: *does this change what I do next*. It is not
 * a small Value Profile — the page says what is true about a value, and
 * this says whether the reader needs the page.
 *
 * **It issues no query and adds none.** Everything below is folded from
 * the verdict envelope and the context that verdict scored, both of
 * which one `forVerdict()` already built. That is the whole cost
 * argument: a hover that cost a second read would be seven more queries
 * on a table with fifty rows in it, and a reader sweeping a column
 * would pay for the page they did not open. `38-hover-card.md` §8.
 *
 * It follows that the card can never disagree with the Assessment tab.
 * It is not a cheaper assessment drawn from cheaper facts; it is the
 * same array, read down to what fits in 400px.
 *
 * @see ValueProfile::forHoverCard
 */
class ValueHoverTool
{
    /**
     * Type chips drawn before the rest are counted rather than named.
     *
     * Three fits the card's width at every type name MISP ships;
     * `domain|ip` and `email-src` together already fill a line.
     */
    const TYPE_CHIPS = 3;

    /**
     * The longest a signal line may be before it is cut.
     *
     * The card rations prose to one clipped line (proposal C's rule),
     * and the engine's signal texts run to about ninety characters —
     * *"47 sightings from 4 orgs, last 2 days ago"* fits, *"5 of 7
     * events are published, and the two drafts are the oldest"* does
     * not. Cut rather than wrapped: a third line of prose on this card
     * is the card becoming the panel it summarises.
     */
    const SIGNAL_CHARS = 58;

    /**
     * What each warninglist category actually claims.
     *
     * The wording is load-bearing and is the Assessment tab's own
     * (`value_verdict_warninglist.ctp`). Neither category means the
     * reporting organisations were wrong, and a card that shortened
     * either into *known false positive* would say exactly that.
     */
    const WARNINGLIST_NOTES = array(
        'known' => 'Shared infrastructure. It argues nothing about the'
            . ' reports.',
        'false_positive' => 'Reports about this value are usually'
            . ' collateral.',
    );

    /**
     * The categories as a reader says them.
     *
     * The stored values are `known` and `false_positive`, and a chip
     * reading `FALSE_POSITIVE` is a column name on a card that has no
     * room to explain it was a column name.
     */
    const WARNINGLIST_LABELS = array(
        'known' => 'Known',
        'false_positive' => 'False positive',
    );

    /**
     * The card, from one assessment.
     *
     * @param array $verdict The `verdict` half of `forVerdict()`
     * @param array $context The context that verdict scored
     * @param int|null $now Unix time; the caller's, so a render and the
     *                      clock it prints cannot drift apart
     * @return array The payload `value_hover_card.ctp` reads
     */
    public static function cardFor(array $verdict, array $context,
        $now = null
    ) {
        $now = $now === null ? time() : (int)$now;
        $occurrences = $context['occurrences'] ?? array();
        $sightings = $context['sightings'] ?? array();

        /*
         * A value MISP flagged as over-correlating never had its
         * sighting rows or its galaxies fetched — the budget's `hot`
         * tier skips both — so the card must not draw a zero where the
         * engine declined to look. `false` is *none*; `null` is *not
         * read*, and the template says so.
         */
        $hot = !empty($context['budget']['hot']);

        return array(
            'value' => $context['value'],
            'types' => self::types($context),
            'lean' => $verdict['lean'],
            'quality' => $verdict['quality'],
            'band' => $verdict['band'],
            'relevance' => self::relevance($verdict),
            'counts' => array(
                'orgs' => (int)($occurrences['orgs'] ?? 0),
                'events' => (int)($occurrences['events'] ?? 0),
                'occurrences' => (int)($occurrences['total'] ?? 0),
                'sightings' => $hot ? null : (int)($sightings['total'] ?? 0),
            ),
            'sightings' => $hot ? null : self::sightings($sightings, $now),
            'seen' => self::seen($occurrences, $now),
            'warninglist' => self::warninglist($context),
            'signal' => self::signal($verdict),
            'galaxy' => $hot ? null : self::galaxy($context),
            'summary' => $verdict['summary'] ?? null,
            'hot' => $hot,
        );
    }

    /**
     * The type chips, and how many did not fit.
     *
     * @param array $context
     * @return array `shown` and `more`
     */
    private static function types(array $context)
    {
        $types = $context['types'] ?? array();
        return array(
            'shown' => array_slice($types, 0, self::TYPE_CHIPS),
            'more' => max(0, count($types) - self::TYPE_CHIPS),
        );
    }

    /**
     * The shelf-life readout, as a number and a word.
     *
     * The card draws relevance as a reading opposite *last seen*, so it
     * needs the days as a figure and the state as its caption. A value
     * with no dates to age it by has **no** figure — not zero, which
     * would read as *expires today* — and the template prints a dash
     * against the `uncertain` caption.
     *
     * @param array $verdict
     * @return array
     */
    private static function relevance(array $verdict)
    {
        $relevance = $verdict['relevance'] ?? array();
        $state = $relevance['state'] ?? 'uncertain';
        $uncertain = $state === 'uncertain'
            || !empty($relevance['uncertain']);
        $runway = $relevance['runway_days'] ?? null;
        return array(
            'state' => $state,
            'uncertain' => $uncertain,
            /*
             * Signed as the engine signs it: negative is days since it
             * expired, and the template prints the sign rather than an
             * absolute value under an *expired* caption, because
             * `-42 EXPIRED` and `42 EXPIRED` differ by a reading.
             */
            'runway_days' => $uncertain || $runway === null
                ? null
                : (int)$runway,
        );
    }

    /**
     * The sightings channel: the totals, and the 90-day signed spark.
     *
     * The spark is `ValueStatsTool::sightingSpark`'s own array — the
     * one the Overview's Sightings card draws — so the shape a reader
     * learns on the card is the shape on the page. Folded, never
     * refetched.
     *
     * @param array $sightings The context's sighting facts
     * @param int $now
     * @return array
     */
    private static function sightings(array $sightings, $now)
    {
        $last = $sightings['last_stamp'] ?? null;
        return array(
            'total' => (int)($sightings['total'] ?? 0),
            'fp' => (int)($sightings['fp'] ?? 0),
            'expiration' => (int)($sightings['expiration'] ?? 0),
            'spark' => $sightings['spark'] ?? array(),
            'last' => $last === null
                ? null
                : ValueStatsTool::agoPhrase($last, $now),
        );
    }

    /**
     * First and last seen, and the far commoner case of neither.
     *
     * Only 6.2% of attributes on the verification instance carry a
     * `first_seen` and 16.2% a `last_seen`, so *not recorded* is this
     * channel's ordinary reading rather than its edge case. It is said
     * in words, because a blank cell on a card this small reads as a
     * card that failed to load.
     *
     * @param array $occurrences
     * @param int $now
     * @return array
     */
    private static function seen(array $occurrences, $now)
    {
        $out = array();
        foreach (array('first' => 'oldest', 'last' => 'newest') as
                 $key => $column
        ) {
            $stamp = $occurrences[$column] ?? null;
            $out[$key] = array(
                'recorded' => $stamp !== null,
                'date' => $stamp === null
                    ? null
                    : date('Y-m-d', (int)$stamp),
                'ago' => $stamp === null
                    ? null
                    : ValueStatsTool::agoPhrase($stamp, $now),
            );
        }
        return $out;
    }

    /**
     * The warninglist hit, with the claim its category actually makes.
     *
     * @param array $context
     * @return array|null Null where nothing matched
     */
    private static function warninglist(array $context)
    {
        $hit = $context['warninglist'] ?? null;
        if (empty($hit) || empty($hit['hits'])) {
            return null;
        }
        $first = $hit['hits'][0];
        $category = $first['category'] ?? 'known';
        return array(
            'name' => $first['name'] ?? null,
            'version' => $first['version'] ?? null,
            'category' => $category,
            'category_label' => self::WARNINGLIST_LABELS[$category]
                ?? $category,
            'match' => $first['match'] ?? null,
            'note' => self::WARNINGLIST_NOTES[$category] ?? null,
            'more' => max(0, count($hit['hits']) - 1),
        );
    }

    /**
     * The heaviest signal in the ledger, clipped to one line.
     *
     * One, not three: the ledger is proposal B's card and this is C's.
     * What survives the shrink is the single row that moved the
     * assessment furthest, so the number on the card is never a figure
     * with nothing behind it.
     *
     * @param array $verdict
     * @return array|null
     */
    private static function signal(array $verdict)
    {
        $best = null;
        foreach ($verdict['ledger'] ?? array() as $group) {
            foreach ($group['signals'] ?? array() as $signal) {
                if ($best === null
                    || abs($signal['contribution'])
                        > abs($best['contribution'])
                ) {
                    $best = $signal;
                }
            }
        }
        if ($best === null) {
            return null;
        }
        return array(
            'text' => self::clip($best['signal']),
            'contribution' => (int)$best['contribution'],
            'direction' => $best['contribution'] < 0 ? 'down' : 'up',
        );
    }

    /**
     * The named threat, where the record names one.
     *
     * A cluster rather than a technique: *APT28* is what makes a value
     * mean something to a reader mid-sweep, and `T1071.001` is a thing
     * they look up on the page.
     *
     * @param array $context
     * @return array|null
     */
    private static function galaxy(array $context)
    {
        $clusters = $context['galaxies']['clusters'] ?? array();
        if (empty($clusters)) {
            return null;
        }
        $first = reset($clusters);
        return array(
            'name' => $first['name'] ?? null,
            'kind' => $first['kind'] ?? null,
            'more' => max(0, count($clusters) - 1),
        );
    }

    /**
     * Cut on a word boundary, with an ellipsis that says it was cut.
     *
     * @param string $text
     * @return string
     */
    private static function clip($text)
    {
        if (mb_strlen($text) <= self::SIGNAL_CHARS) {
            return $text;
        }
        $cut = mb_substr($text, 0, self::SIGNAL_CHARS);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > self::SIGNAL_CHARS / 2) {
            $cut = mb_substr($cut, 0, $space);
        }
        return rtrim($cut, " ,;:") . '…';
    }
}
