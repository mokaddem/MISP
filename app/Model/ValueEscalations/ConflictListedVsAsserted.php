<?php

App::uses('ValueLeanTool', 'Tools');

/**
 * A list says the value is not an indicator, and nearly everyone
 * reporting it says it is.
 *
 * This rule is the guard on the lean derivation's own precedence. A
 * `false_positive` hit beats a minority of threat stances on purpose —
 * that is what makes a value flipping to benign the day it lands on the
 * public-resolver list a *rule* taking precedence rather than a
 * hundred-point swing in a score. But precedence stops being defensible
 * once the stances are nearly unanimous the other way: at that point
 * letting the list win silently discards a deliberate judgement by
 * several organisations, and letting the stances win silently discards
 * a deliberate judgement by whoever curated the list.
 *
 * So the contradiction is the answer, and both judgements survive it.
 * The value keeps its `false_positive` hit on the page and keeps the
 * organisations asserting against it, and the reader is told the two
 * disagree instead of being handed the winner.
 *
 * The threshold is the profile's own `lean_supermajority` by default,
 * which is the point of stating it as a word rather than a number: this
 * rule fires exactly where the derivation would otherwise have called
 * the value a threat on the strength of the stances alone.
 */
class ConflictListedVsAsserted extends ValueEscalationBase
{
    public $id = 'conflict:listed-vs-asserted';
    public $reads = array('warninglist', 'orgs');
    public $source = 'Lifecycle';

    public function __construct()
    {
        $this->description = __(
            'A list marks the value as a false positive while a'
            . ' supermajority of organisations report it as a threat.'
        );
        $this->when_schema = array(
            'warninglist_category' => array(
                'type' => 'string',
                'default' => 'false_positive',
                'label' => __('The category of list that must match'),
            ),
            'threat_share_at_least' => array(
                'type' => 'string',
                'default' => 'supermajority',
                'label' => __('Share of organisations asserting it,'
                    . ' as a fraction or the word supermajority'),
            ),
        );
    }

    public function fires(array $context, array $config)
    {
        $category = $this->when($config, 'warninglist_category');
        $lists = $this->listsInCategory($context, $category);
        if (empty($lists)) {
            return null;
        }
        $share = isset($context['stances']['threat_share'])
            ? (float)$context['stances']['threat_share']
            : 0.0;
        $threshold = $this->shareThreshold(
            $config,
            'threat_share_at_least',
            $context
        );
        if (!ValueLeanTool::atLeast($share, $threshold)) {
            return null;
        }
        $threatOrgs = isset($context['stances']['threat_orgs'])
            ? (int)$context['stances']['threat_orgs']
            : 0;
        return array(
            'prose' => sprintf(
                __('A warninglist marks this as a false positive and'
                    . ' %1$d of %2$d organisations report it as a'
                    . ' threat regardless. Both judgements are'
                    . ' deliberate; the page will not pick one.'),
                $threatOrgs,
                $this->orgCount($context)
            ),
            'evidence' => sprintf(
                __('%1$s · %2$d%% of organisations assert it'),
                implode(', ', $lists),
                (int)round($share * 100)
            ),
        );
    }
}
