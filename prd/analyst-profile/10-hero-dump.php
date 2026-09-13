<?php
App::uses('ValueVerdictTool', 'Tools');
App::uses('ValueRelevanceTool', 'Tools');
App::uses('ValueLean', 'Tools');

/**
 * What the hero has to compose from, printed for real values.
 *
 * `summary` is D11's one open point and the only key on the Assessment
 * tab whose producer is a piece of writing rather than a fold. Writing
 * it against the fixture is how `value-profile-live/` went wrong; this
 * prints the live assessment's lean-side, quality-side and
 * relevance-side fields for whatever values are named on the command
 * line, so the sentence is drafted against arrays that exist.
 *
 * **Not part of the application.** Copy it in for the duration:
 *
 *   docker cp prd/analyst-profile/10-hero-dump.php \
 *     misp-core:/var/www/MISP/app/Console/Command/HeroDumpShell.php
 *   app/Console/cake HeroDump 8.8.8.8 1.1.1.1
 */
class HeroDumpShell extends AppShell
{
    public $uses = array('User', 'ValueProfile');

    public function main()
    {
        $user = $this->User->getAuthUser(1, true);
        foreach ($this->args as $value) {
            $out = $this->ValueProfile->forVerdict($user, $value);
            $v = $out['verdict'];
            $this->out('');
            $this->out('=== ' . $value . ' ===');
            $this->out('lean            ' . json_encode($v['lean']));
            $this->out('derived_lean    '
                . json_encode($v['derived_lean'] ?? null));
            $this->out('polarity        ' . json_encode($v['polarity']));
            $this->out('quality         ' . json_encode($v['quality']));
            $this->out('band            ' . json_encode($v['band']));
            $this->out('rule            ' . json_encode($v['rule']));
            $this->out('signals         ' . json_encode($v['signals']));
            $this->out('stances         ' . json_encode($v['stances']));
            $this->out('tug             ' . json_encode($v['tug']));
            $r = $v['relevance'];
            $this->out('relevance.state ' . json_encode($r['state']));
            $this->out('  reason        ' . json_encode($r['reason']));
            $this->out('  runway_days   ' . json_encode($r['runway_days']));
            $this->out('  elapsed_days  ' . json_encode($r['elapsed_days']));
            $this->out('  ttl           ' . json_encode($r['ttl']));
            $this->out('  clock         ' . json_encode($r['clock']));
            $this->out('  uncertain     ' . json_encode($r['uncertain']));
            $this->out('  unc_note      '
                . json_encode($r['uncertain_note']));
            $this->out('orgs rows       ' . count($v['orgs']));
            $this->out('ledger groups   ' . count($v['ledger']));
            $this->out('changers        ' . json_encode($v['changers']));
            $this->out("summary         " . json_encode($v["summary"]));
        }
    }
}
