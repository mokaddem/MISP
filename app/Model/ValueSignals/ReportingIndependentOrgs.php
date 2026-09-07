<?php

/**
 * How many organisations independently hold an occurrence of this value.
 *
 * The heaviest row in the fixture's malicious ledger (+28 from four
 * organisations) and the page's own argument for what corroboration
 * means: an organisation is one voice however many events it puts the
 * value in, which is the same independence rule the lean derivation
 * applies to `to_ids` stances (`04-dispositions.md` §3).
 *
 * **Aggregate evidence, so never windowed** (§2.3). A `COUNT DISTINCT`
 * over the orgs holding an occurrence is cheap at any cardinality, and
 * bounding it to 90 days would make a long-lived value look narrowly
 * reported for no reason but its age.
 *
 * One organisation still fires. *"1 organisation reported it"* is the
 * median value's own row, and it is worth `per_org` rather than
 * nothing — what makes the median record thin is the rest of the
 * ledger, not a signal refusing to speak (§7.4).
 */
class ReportingIndependentOrgs extends ValueSignalBase
{
    public $id = 'reporting.independent_orgs';
    public $group = 'Reporting';
    public $default_band = 'strong';
    public $evidence_class = self::EVIDENCE_AGGREGATE;
    public $reads = array('orgs');
    public $source = 'Occurrences';

    public function __construct()
    {
        $this->description = __(
            'How many organisations independently report this value.'
        );
        $this->points_schema = array(
            'per_org' => array(
                'type' => 'int',
                'default' => 7,
                'label' => __('Points per reporting organisation'),
            ),
            'cap' => array(
                'type' => 'int',
                'default' => 28,
                'label' => __('Most this signal may contribute'),
            ),
        );
        $this->config_schema = array(
            'named' => array(
                'type' => 'int',
                'default' => 4,
                'label' => __('Organisations named in the evidence line'),
            ),
        );
    }

    public function evaluate(array $context, array $config)
    {
        $orgs = isset($context['orgs']) ? $context['orgs'] : array();
        if (empty($orgs)) {
            return null;
        }
        $names = array();
        foreach ($orgs as $org) {
            if (!empty($org['name'])) {
                $names[] = $org['name'];
            }
        }
        $count = count($orgs);
        $points = $this->capped(
            $this->points($config, 'per_org') * $count,
            $this->points($config, 'cap')
        );
        $named = (int)$this->setting($config, 'named');
        $shown = array_slice($names, 0, max(1, $named));
        $evidence = implode(', ', $shown);
        if (count($names) > count($shown)) {
            $evidence .= sprintf(
                __(' and %d more'),
                count($names) - count($shown)
            );
        }
        return $this->row(
            $points,
            $count === 1
                ? __('1 organisation reported it')
                : sprintf(
                    __('%d independent organisations reported it'),
                    $count
                ),
            $evidence,
            $context,
            $this->stampAsOf(
                $context['occurrences']['newest'] ?? null,
                $context
            )
        );
    }
}
