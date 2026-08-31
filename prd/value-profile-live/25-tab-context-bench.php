<?php

/**
 * Scratch shell: what one Relationships tab render actually costs, per
 * endpoint, cold and warm.
 *
 * The tab fires six ajax requests. Three of them assemble sections the
 * other three already assembled, which is §15.1 item 1. This measures
 * which of those repeats is worth holding and which is noise.
 *
 * Not part of the application. To run it:
 *
 *   cp prd/value-profile-live/25-tab-context-bench.php \
 *      app/Console/Command/TabContextBenchShell.php
 *   app/Console/cake TabContextBench run 1 8.8.8.8
 *   rm app/Console/Command/TabContextBenchShell.php
 */
class TabContextBenchShell extends AppShell
{
    public $uses = array('User');

    /** The six endpoints, in the order the page fires them. */
    private $endpoints = array(
        'viewRelationCooccurrence' => 'forRelationCooccurrence',
        'viewRelationNearMatch' => 'forRelationNearMatch',
        'viewRelationExternal' => 'forRelationExternal',
        'viewRelationAsserted' => 'forRelationAsserted',
        'viewRelationGraph' => 'forRelationGraph',
        'viewRelationSettings' => 'forRelationSettings',
    );

    public function run()
    {
        $user = $this->User->getAuthUser((int)$this->args[0]);
        $value = $this->args[1];
        Configure::write('CurrentUserId', $user['id']);

        foreach (array('cold', 'warm') as $pass) {
            if ($pass === 'cold') {
                $this->flushScan();
            }
            $this->out('=== ' . $pass . ' — ' . $value . ' ===');
            $total = 0;
            foreach ($this->endpoints as $action => $method) {
                // a fresh model per endpoint, as a real request has
                ClassRegistry::removeObject('ValueProfile');
                $profile = ClassRegistry::init('ValueProfile');
                $t = microtime(true);
                $profile->$method($user, $value);
                $ms = (microtime(true) - $t) * 1000;
                $total += $ms;
                $this->out(sprintf('  %-28s %8.1f ms', $action, $ms));
            }
            $this->out(sprintf('  %-28s %8.1f ms', 'sum of six', $total));
            $this->out('');
        }
    }

    private function flushScan()
    {
        try {
            $redis = RedisTool::init();
        } catch (Exception $e) {
            $this->out('(no redis)');
            return;
        }
        foreach (array('relation_scan', 'relation_digest') as $bucket) {
            $keys = $redis->keys('misp:value_profile:' . $bucket . ':*');
            if (!empty($keys)) {
                $redis->del($keys);
            }
        }
    }
}
