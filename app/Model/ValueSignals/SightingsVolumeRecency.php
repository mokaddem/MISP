<?php

/**
 * How much this value has been seen, and how recently.
 *
 * Volume and recency in one row rather than two, because that is how
 * the fixture reports it — *"47 sightings from 4 orgs, last 2 days
 * ago"* — and because they are not independent readings: four hundred
 * sightings that all stopped a year ago is one statement, not a strong
 * one and a weak one to be netted.
 *
 * **Logarithmic in volume, and that is the judgement.** The step from
 * 1 sighting to 10 says far more than the step from 400 to 410, so the
 * contribution saturates: `saturation` is the count at which the signal
 * is fully paid, and everything beyond it is worth the cap. Measured
 * against the fixture, `cap 24` with `saturation 50` puts
 * `185.234.219.24`'s 47 sightings at +24 and the flux value's 418 at
 * +24 — which is what the fixture authored for both.
 *
 * **Recency is a factor, not an addend**, so it cannot rescue a value
 * nobody has seen: a stale sighting history scales the whole row down
 * rather than subtracting a fixed penalty from it.
 *
 * Absence fires as `none_recent`, and only on genuine absence — a
 * sighting set an exclusion emptied is not a value nobody sighted, and
 * `ValueSignalBase::absenceFires()` is where that rule lives (§4.2).
 */
class SightingsVolumeRecency extends ValueSignalBase
{
    public $id = 'sightings.volume_recency';
    public $group = 'Sightings';
    public $default_band = 'strong';
    public $evidence_class = self::EVIDENCE_ROW;
    public $reads = array('sightings');
    public $absence_key = 'none_recent';
    public $source = 'Sightings';

    public function __construct()
    {
        $this->description = __(
            'Sighting volume, organisation spread and recency.'
        );
        $this->points_schema = array(
            'cap' => array(
                'type' => 'int',
                'default' => 24,
                'label' => __('Points at saturation'),
            ),
            'none_recent' => array(
                'type' => 'int',
                'default' => -4,
                'label' => __('Points when nobody has sighted it'),
            ),
        );
        $this->config_schema = array(
            'saturation' => array(
                'type' => 'int',
                'default' => 50,
                'label' => __('Sightings at which the signal is fully'
                    . ' paid'),
            ),
            'stale_days' => array(
                'type' => 'int',
                'default' => 90,
                'label' => __('Age past which sightings are discounted'),
            ),
            'stale_factor' => array(
                'type' => 'float',
                'default' => 0.5,
                'label' => __('What a stale sighting history is worth'),
            ),
        );
    }

    public function evaluate(array $context, array $config)
    {
        $sightings = isset($context['sightings'])
            ? $context['sightings']
            : array();
        $total = (int)($sightings['total'] ?? 0);
        if ($total === 0) {
            if (!$this->absenceFires($config, $context, 'sightings')) {
                return null;
            }
            return $this->row(
                $this->points($config, 'none_recent'),
                __('Nobody has sighted this value'),
                __('No sighting from any organisation'),
                $context
            );
        }

        $saturation = max(1, (int)$this->setting($config, 'saturation'));
        $volume = min(1.0, log(1 + $total) / log(1 + $saturation));
        $last = (int)($sightings['last_stamp'] ?? 0);
        $ageDays = $last > 0
            ? (int)floor((($context['now'] ?? time()) - $last) / 86400)
            : null;
        $stale = ($ageDays === null
            || $ageDays > (int)$this->setting($config, 'stale_days'));
        $factor = $stale
            ? (float)$this->setting($config, 'stale_factor')
            : 1.0;
        $points = $this->capped(
            $this->points($config, 'cap') * $volume * $factor,
            $this->points($config, 'cap')
        );

        $orgs = (int)($sightings['orgs'] ?? 0);
        $signal = sprintf(
            __('%1$s from %2$s, last %3$s'),
            sprintf(
                $total === 1 ? __('%d sighting') : __('%d sightings'),
                $total
            ),
            sprintf(
                $orgs === 1 ? __('%d org') : __('%d orgs'),
                $orgs
            ),
            $ageDays === null
                ? __('an unknown time ago')
                : $this->agoPhrase($ageDays)
        );
        $recent = (int)($sightings['recent'] ?? 0);
        $recentDays = (int)($sightings['recent_days'] ?? 30);
        $evidence = sprintf(
            __('%1$d in the last %2$d days'),
            $recent,
            $recentDays
        );
        if ($stale) {
            $evidence .= sprintf(
                __('; discounted to %d%% as a stale history'),
                (int)round($factor * 100)
            );
        }

        return $this->row(
            $points,
            $signal,
            $evidence,
            $context,
            $this->stampAsOf($last, $context)
        );
    }
}
