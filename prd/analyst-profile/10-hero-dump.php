<?php
App::uses('ValueVerdictTool', 'Tools');
App::uses('ValueRelevanceTool', 'Tools');
App::uses('ValueLean', 'Tools');

/**
 * The live assessment, printed field by field.
 *
 * Written to draft the hero's sentence (§13) against arrays that exist
 * rather than against the fixture — which is how `value-profile-live/`
 * went wrong the first time — and kept because it earned its keep twice
 * more:
 *
 *   - it found `conflict:listed-vs-asserted` writing prose that no
 *     layout printed (§13.3), because the rule was in the array and
 *     nowhere in the markup;
 *   - it found the Assessment tab and the Sightings tab disagreeing
 *     about the relevance axis (§14.3), by printing both clocks side by
 *     side on one value.
 *
 * Both are the same shape of defect — something computed and not shown,
 * or shown twice and differently — and neither is visible from a
 * rendered page or from a harness. `--sightings` is the flag that
 * catches the second: it prints the axis as each tab computes it.
 *
 * **Not part of the application.** Copy it in for the duration:
 *
 *   docker cp prd/analyst-profile/10-hero-dump.php \
 *     misp-core:/var/www/MISP/app/Console/Command/HeroDumpShell.php
 *   app/Console/cake HeroDump 8.8.8.8 github.com --sightings
 */
class HeroDumpShell extends AppShell
{
    public $uses = array('User', 'ValueProfile');

    /**
     * @return ConsoleOptionParser
     */
    public function getOptionParser()
    {
        return parent::getOptionParser()->addOption('sightings', array(
            'boolean' => true,
            'help' => 'Also print the relevance axis as the Sightings'
                . ' tab computes it, for comparison',
        ));
    }

    /**
     * @return void
     */
    public function main()
    {
        $user = $this->User->getAuthUser(1, true);
        /*
         * `AnalystData::setUser()` reads this and nothing else, and a
         * console has no session to fill it — without it the analyst
         * union `with_opinions` asks for dies on a null user in
         * `rearrangeSharingGroup`. A property of running outside a
         * request, not of the endpoint.
         */
        Configure::write('CurrentUserId', (int)$user['id']);
        foreach ($this->args as $value) {
            $v = $this->ValueProfile->forVerdict($user, $value,
                array('with_opinions' => true))['verdict'];
            $this->out('');
            $this->out('=== ' . $value . ' ===');

            $this->line('summary', $v['summary']);
            $this->out('');
            $this->line('lean', $v['lean']);
            $this->line('derived_lean', $v['derived_lean']);
            $this->line('rule', $v['rule']);
            $this->line('stances', $v['stances']);
            $this->out('');
            $this->line('quality', $v['quality']);
            $this->line('band', $v['band']);
            $this->line('polarity', $v['polarity']);
            $this->line('tug', $v['tug']);
            $this->line('signals', $v['signals']);
            $this->line('ledger groups', count($v['ledger']));
            $this->out('');

            $r = $v['relevance'];
            $this->line('relevance.state', $r['state']);
            $this->line('  reason', $r['reason']);
            $this->line('  runway_days', $r['runway_days']);
            $this->line('  elapsed_days', $r['elapsed_days']);
            $this->line('  uncertain', $r['uncertain']);
            $this->line('  clock', array(
                $r['clock']['kind'],
                $r['clock']['by'],
                'rows_read' => $r['clock']['rows_read'],
            ));
            $this->out('');

            $this->line('cases', array_map(function ($case) {
                return $case['side'] . ' ' . $case['weight']
                    . ' (' . count($case['rows']) . ')';
            }, $v['cases']));
            $this->line('conflicts', array_column($v['conflicts'],
                'title'));
            $this->line('orgs', count($v['orgs']));
            $this->line('opinions', $v['opinions'] === null
                ? null
                : $v['opinions']['n'] . ' · mean '
                    . $v['opinions']['mean_label']);
            $this->line('changers', array_column($v['changers'], 'axis'));
            $this->line('not_counted', array_column($v['not_counted'],
                'title'));

            if (empty($this->params['sightings'])) {
                continue;
            }
            /*
             * The same axis, as the other tab computes it. They read
             * one tool and two contexts — the assessment's is bounded
             * by the evidence budget — so a value MISP has flagged as
             * over-correlating is where they used to part company.
             */
            $this->out('');
            $other = $this->ValueProfile->forRelevance($user, $value);
            $o = isset($other['relevance']) ? $other['relevance'] : array();
            $this->line('Sightings tab state',
                isset($o['state']) ? $o['state'] : null);
            $this->line('  runway_days',
                isset($o['runway_days']) ? $o['runway_days'] : null);
            $this->line('  clock kind',
                isset($o['clock']['kind']) ? $o['clock']['kind'] : null);
        }
    }

    /**
     * @param string $label
     * @param mixed $value
     * @return void
     */
    private function line($label, $value)
    {
        $this->out(sprintf('%-22s %s', $label, json_encode($value)));
    }
}
