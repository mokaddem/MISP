<?php

/**
 * Somebody who looked at this value said it was wrong.
 *
 * The clearest negative evidence MISP holds, and the row that carries
 * the benign fixture value: eleven false-positive sightings on
 * `8.8.8.8`, worth −26 threat-signed, which the benign lean's polarity
 * renders as **+26 supporting benign** without the declaration
 * changing (§2, `04-dispositions.md` §2).
 *
 * **Capped, because a false positive is a judgement and not a
 * measurement.** One organisation can file forty; the cap is what stops
 * that outweighing every other signal on the page, and the row names
 * the organisations so a reader can see whether the forty were one
 * voice.
 *
 * **Silent on absence, deliberately.** No false positive is the normal
 * state of nearly every value on an instance, and a ledger row saying
 * so on all of them is noise that would drown the rows that mean
 * something. The catalogue's *fires on absence: no* (§6) is this
 * decision.
 */
class SightingsFalsePositive extends ValueSignalBase
{
    public $id = 'sightings.false_positive';
    public $group = 'Sightings';
    public $default_band = 'moderate';
    public $evidence_class = self::EVIDENCE_ROW;
    public $reads = array('sightings');
    public $source = 'Sightings';

    public function __construct()
    {
        $this->description = __(
            'False-positive sightings, and how many organisations filed'
            . ' them.'
        );
        $this->points_schema = array(
            'per' => array(
                'type' => 'int',
                'default' => -3,
                'label' => __('Points per false-positive sighting'),
            ),
            'per_extra_org' => array(
                'type' => 'int',
                'default' => -4,
                'label' => __('Further points per organisation beyond'
                    . ' the first'),
            ),
            'cap' => array(
                'type' => 'int',
                'default' => -26,
                'label' => __('Most this signal may contribute'),
            ),
        );
        /*
         * The per-sighting points, not the per-extra-org bonus: one
         * more sighting from an organisation already filing them is
         * linear to the cap, and it is also the change a reader can
         * actually make. A sighting from a *new* organisation is worth
         * more than this says, which is the safe direction for a
         * falsifier to err in.
         */
        $this->unit = array(
            'points' => 'per',
            'cap' => 'cap',
            'one' => __('One more false-positive sighting'),
            'many' => __('%d more false-positive sightings'),
        );
    }

    public function evaluate(array $context, array $config)
    {
        $sightings = isset($context['sightings'])
            ? $context['sightings']
            : array();
        $fp = (int)($sightings['fp'] ?? 0);
        if ($fp === 0) {
            return null;
        }
        $orgs = max(1, (int)($sightings['fp_orgs'] ?? 1));
        $points = $this->capped(
            $this->points($config, 'per') * $fp
                + $this->points($config, 'per_extra_org') * ($orgs - 1),
            $this->points($config, 'cap')
        );

        $names = isset($sightings['fp_org_names'])
            ? $sightings['fp_org_names']
            : array();
        if ($fp === 1) {
            $signal = empty($names)
                ? __('1 false-positive sighting')
                : sprintf(
                    __('1 false-positive sighting (%s)'),
                    $names[0]
                );
        } else {
            $signal = sprintf(
                __('%1$d false-positive sightings from %2$d'
                    . ' %3$s'),
                $fp,
                $orgs,
                $orgs === 1 ? __('org') : __('orgs')
            );
        }

        return $this->row(
            $points,
            $signal,
            empty($names)
                ? __('Filed by organisations that are not named to you')
                : implode(', ', $names),
            $context,
            $this->stampAsOf(
                $sightings['fp_last_stamp'] ?? null,
                $context
            )
        );
    }
}
