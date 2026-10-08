<?php

/**
 * The viewer's own organisation is the only one reporting this value.
 *
 * `reporting.independent_orgs` already scores one organisation; this
 * row says *whose* assertion it is, because a record resting on your
 * own reporting alone is one you cannot cite back to yourself as
 * corroboration.
 *
 * Worth nothing by default, so it adds a line without moving a number
 * that `independent_orgs` has already priced.
 */
class ReportingSelfReportedOnly extends ValueSignalBase
{
    public $id = 'reporting.self_reported_only';
    public $group = 'Reporting';
    public $evidence_class = self::EVIDENCE_AGGREGATE;
    public $reads = array('orgs');
    public $tab = 'occurrences';

    public function __construct()
    {
        $this->description = __(
            'Whether your organisation is the only one reporting this'
            . ' value.'
        );
        $this->points_schema = array(
            'only_you' => array(
                'type' => 'int',
                'default' => 0,
                'label' => __('Points when only your organisation'
                    . ' reports it'),
            ),
        );
    }

    public function evaluate(array $context, array $config)
    {
        $own = (int)($context['viewer']['org_id'] ?? 0);
        $orgs = isset($context['orgs']) ? $context['orgs'] : array();
        if ($own === 0 || count($orgs) !== 1
            || (int)($orgs[0]['id'] ?? 0) !== $own
        ) {
            return null;
        }
        $events = (int)($context['occurrences']['events'] ?? 0);
        $evidence = $events === 1
            ? __('In one of your events, and no other organisation\'s')
            : sprintf(
                __('In %d of your events, and no other organisation\'s'),
                $events
            );
        $feeds = (int)($context['feeds']['count'] ?? 0);
        if ($feeds > 0) {
            $evidence .= sprintf(
                $feeds === 1
                    ? __('; %d feed also lists it')
                    : __('; %d feeds also list it'),
                $feeds
            );
        }
        return $this->row(
            $this->points($config, 'only_you'),
            __('Only your organisation reported it'),
            $evidence,
            $context,
            $this->stampAsOf(
                $context['occurrences']['newest'] ?? null,
                $context
            )
        );
    }
}
