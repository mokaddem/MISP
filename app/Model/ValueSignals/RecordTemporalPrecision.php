<?php

/**
 * Whether the record can date its own observations.
 *
 * The signal D11 added, and the quality reading of the two facts that
 * make the relevance axis say *timeline uncertain*
 * (`06-staleness.md` §3.6): no `first_seen` on any occurrence, so only
 * the encoding date is known, and an encoding date that lags the
 * event's own dates far enough to be a poor proxy for it.
 *
 * The example that forced the three-axis model was a phishing URL
 * encoded two months after the incident. Relevance says *the timeline
 * is uncertain*; this says the quieter thing next to it — **a record
 * that cannot date its own observations is a weaker record** — and the
 * two readings finally have separate homes.
 *
 * **Aggregate evidence, not row evidence.** Both facts arrive as
 * single-row aggregates — a `SUM` over `first_seen` and a `MAX` of the
 * lag — so they are cheap at any cardinality and are read
 * whole-history like every other aggregate (§2.3). A hot value keeps
 * this row when the sighting and galaxy signals bow out, which is the
 * right way round: what it measures is how honest the record's dates
 * are, and that is exactly the sort of thing worth knowing about a
 * value too big to read.
 */
class RecordTemporalPrecision extends ValueSignalBase
{
    public $id = 'record.temporal_precision';
    public $group = 'Lifecycle';
    public $evidence_class = self::EVIDENCE_AGGREGATE;
    public $reads = array('temporal');
    public $source = 'Timeline';

    public function __construct()
    {
        $this->description = __(
            'Whether the occurrences date their own observations, or'
            . ' only their encoding.'
        );
        $this->points_schema = array(
            'dated' => array(
                'type' => 'int',
                'default' => 4,
                'label' => __('Points when an occurrence carries'
                    . ' first_seen'),
            ),
            'undated' => array(
                'type' => 'int',
                'default' => -6,
                'label' => __('Points when none does'),
            ),
            'lagged' => array(
                'type' => 'int',
                'default' => -4,
                'label' => __('Further points when the encoding lags the'
                    . ' event'),
            ),
        );
        $this->config_schema = array(
            'lag_days' => array(
                'type' => 'int',
                'default' => 30,
                'label' => __('Lag beyond which the encoding date is a'
                    . ' poor proxy'),
            ),
        );
    }

    public function evaluate(array $context, array $config)
    {
        $temporal = isset($context['temporal'])
            ? $context['temporal']
            : array();
        $occurrences = (int)($temporal['occurrences'] ?? 0);
        if ($occurrences === 0) {
            return null;
        }
        $dated = (int)($temporal['with_first_seen'] ?? 0);
        $lag = $temporal['max_lag_days'] ?? null;
        $lagDays = (int)$this->setting($config, 'lag_days');
        $lagged = ($lag !== null && $lag > $lagDays);

        $points = $dated > 0
            ? $this->points($config, 'dated')
            : $this->points($config, 'undated');
        if ($lagged) {
            $points += $this->points($config, 'lagged');
        }

        $evidence = array();
        $evidence[] = $dated > 0
            ? sprintf(
                __('%1$d of %2$d occurrences set first_seen'),
                $dated,
                $occurrences
            )
            : __('no first_seen on any occurrence');
        if ($lagged) {
            $evidence[] = sprintf(
                __('encoded %d days after the event\'s own dates'),
                (int)$lag
            );
        }

        /*
         * The prose names whichever fact is doing the work. Both are in
         * the evidence line either way, because a row saying only
         * *"undated"* on a value that is also two months late has
         * hidden half of what it measured.
         */
        if ($dated === 0) {
            $signal = __('The record dates only its own encoding');
        } elseif ($lagged) {
            $signal = __('Dated, but encoded well after the event');
        } else {
            $signal = __('The record dates its own observations');
        }

        return $this->row(
            $points,
            $signal,
            implode('; ', $evidence),
            $context
        );
    }
}
