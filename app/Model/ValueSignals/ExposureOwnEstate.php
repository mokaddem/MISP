<?php

/**
 * The viewer's own organisation's sightings, set against everyone
 * else's.
 *
 * `sightings.volume_recency` counts how much the value was seen; this
 * row says whether it was seen *by you* — the nearest MISP gets to
 * "is this in my estate", since it holds sightings rather than
 * telemetry.
 *
 * Two poles: your organisation sighted it, or only others did. The
 * second stays silent when an exclusion or the evidence window removed
 * sightings, since your own could be among them. Both are worth
 * nothing by default, so the row adds a line without counting the
 * same sightings a second time.
 */
class ExposureOwnEstate extends ValueSignalBase
{
    public $id = 'exposure.own_estate';
    public $group = 'Sightings';
    public $evidence_class = self::EVIDENCE_ROW;
    public $reads = array('sightings');
    public $tab = 'sightings';

    public function __construct()
    {
        $this->description = __(
            'Whether your organisation has sighted this value, against'
            . ' everyone else\'s sightings.'
        );
        $this->points_schema = array(
            'own' => array(
                'type' => 'int',
                'default' => 0,
                'label' => __('Points when your organisation sighted it'),
            ),
            'others_only' => array(
                'type' => 'int',
                'default' => 0,
                'label' => __('Points when only other organisations'
                    . ' sighted it'),
            ),
        );
    }

    public function evaluate(array $context, array $config)
    {
        $own = (int)($context['viewer']['org_id'] ?? 0);
        $seen = isset($context['sightings']['seen'])
            && is_array($context['sightings']['seen'])
            ? $context['sightings']['seen']
            : array();
        $total = (int)($seen['total'] ?? 0);
        if ($own === 0 || $total === 0) {
            return null;
        }
        $mine = (int)($seen['by_org'][$own] ?? 0);
        $others = $total - $mine;

        if ($mine === 0) {
            if (!empty($context['excluded']['sightings'])) {
                return null;
            }
            return $this->row(
                $this->points($config, 'others_only'),
                __('Sighted elsewhere, not by your organisation'),
                sprintf(
                    $others === 1
                        ? __('%d sighting, from another organisation')
                        : __('%d sightings, all from other'
                            . ' organisations'),
                    $others
                ),
                $context,
                $this->stampAsOf($seen['last_stamp'] ?? null, $context)
            );
        }

        $last = (int)($seen['by_org_last'][$own] ?? 0);
        $signal = sprintf(
            $mine === 1
                ? __('Your organisation sighted it once, %s')
                : __('Your organisation sighted it %2$d times, last %1$s'),
            $last > 0
                ? $this->agoPhrase((int)floor(
                    (($context['now'] ?? time()) - $last) / 86400
                ))
                : __('at an unknown time'),
            $mine
        );
        return $this->row(
            $this->points($config, 'own'),
            $signal,
            $others === 0
                ? __('No other organisation has sighted it')
                : sprintf(
                    $others === 1
                        ? __('%d more sighting from other organisations')
                        : __('%d more sightings from other'
                            . ' organisations'),
                    $others
                ),
            $context,
            $this->stampAsOf($last, $context)
        );
    }
}
