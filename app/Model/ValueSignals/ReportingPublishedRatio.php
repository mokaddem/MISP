<?php

/**
 * How much of the reporting was actually published.
 *
 * A draft event is a claim its own organisation has not yet stood
 * behind, so the same evidence in a published event is worth more.
 * *"5 of 7 events are published"* and *"121 of 137 events are
 * published"* land at +9 and +7 and share the `moderate` band: the
 * band tracks the signal rather than the number.
 *
 * **Scaled rather than banded**, because the ratio is the evidence: a
 * value published in five of seven events is not in the same position
 * as one published in one of seven, and two thresholds would flatten
 * that into three cases. `scale` is what full publication is worth and
 * the contribution is the ratio of it.
 *
 * **A published event is never worth zero.** Once anything is
 * published the row floors at one point, so a value in one published
 * event out of two hundred drafts reads as thin support rather than as
 * none — a zero-contribution row in a ledger that sums exactly is a
 * row a reader cannot act on.
 */
class ReportingPublishedRatio extends ValueSignalBase
{
    public $id = 'reporting.published_ratio';
    public $group = 'Reporting';
    public $evidence_class = self::EVIDENCE_AGGREGATE;
    public $reads = array('publication');
    public $tab = 'occurrences';

    public function __construct()
    {
        $this->description = __(
            'What share of the events carrying this value are'
            . ' published.'
        );
        $this->points_schema = array(
            'scale' => array(
                'type' => 'int',
                'default' => 9,
                'label' => __('Points at full publication'),
            ),
            'none' => array(
                'type' => 'int',
                'default' => -2,
                'label' => __('Points when nothing is published'),
            ),
        );
        $this->config_schema = array(
            'min_events' => array(
                'type' => 'int',
                'default' => 1,
                'label' => __('Events needed before this fires'),
            ),
        );
    }

    public function evaluate(array $context, array $config)
    {
        $publication = isset($context['publication'])
            ? $context['publication']
            : array();
        $events = (int)($publication['events'] ?? 0);
        $published = (int)($publication['published'] ?? 0);
        if ($events < (int)$this->setting($config, 'min_events')
            || $events === 0
        ) {
            return null;
        }
        if ($published === 0) {
            return $this->row(
                $this->points($config, 'none'),
                $events === 1
                    ? __('The only event carrying it is unpublished')
                    : sprintf(
                        __('None of %d events is published'),
                        $events
                    ),
                __('An unpublished event is a claim its own'
                    . ' organisation has not stood behind yet'),
                $context
            );
        }
        $scale = $this->points($config, 'scale');
        $points = (int)round($scale * $published / $events);
        // The floor follows the author's sign rather than assuming it.
        if ($scale > 0) {
            $points = max(1, $points);
        } elseif ($scale < 0) {
            $points = min(-1, $points);
        }
        return $this->row(
            $points,
            sprintf(
                __('%1$d of %2$d events are published'),
                $published,
                $events
            ),
            __('Published events carry more weight than unpublished'
                . ' ones'),
            $context
        );
    }
}
