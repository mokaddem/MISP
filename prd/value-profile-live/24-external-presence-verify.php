<?php

/**
 * Scratch shell: assert searchCaches attributes each cached event uuid to
 * exactly the source that holds it. Deleted after the run.
 */
class ValueExternalVerifyShell extends AppShell
{
    public $uses = array('Feed', 'Server', 'MispAttribute');

    public function main()
    {
        $this->normalisationGap();
        $redis = $this->Feed->setupRedis();
        $rows = $this->MispAttribute->find('all', array(
            'recursive' => -1,
            'fields' => array('DISTINCT Attribute.value1'),
            'conditions' => array('Attribute.deleted' => 0, 'Attribute.value1 !=' => ''),
            'limit' => 40000,
        ));

        $checked = 0;
        $wrongAttribution = 0;
        $missing = 0;
        $totalUuids = 0;
        $multiSource = 0;
        $examples = array();

        foreach ($rows as $r) {
            $raw = $r['Attribute']['value1'];
            $v = strtolower(trim($raw));
            // both hashes, matching what searchCaches now looks up
            $hashes = array_values(array_unique(array(md5($v), md5($raw))));

            $inFeed = false;
            $inServer = false;
            foreach ($hashes as $h) {
                $inFeed = $inFeed || $redis->sismember('misp:feed_cache:combined', $h);
                $inServer = $inServer || $redis->sismember('misp:server_cache:combined', $h);
            }
            if (!$inFeed && !$inServer) {
                continue;
            }
            $checked++;

            $truth = array('feed' => array(), 'server' => array());
            foreach ($hashes as $h) {
                foreach ($redis->smembers('misp:feed_cache:event_uuid_lookup:' . $h) as $m) {
                    list($id, $u) = explode('/', $m);
                    $truth['feed'][$id][$u] = true;
                }
                foreach ($redis->smembers('misp:server_cache:event_uuid_lookup:' . $h) as $m) {
                    list($id, $u) = explode('/', $m);
                    $truth['server'][$id][$u] = true;
                }
            }
            if (count($truth['feed']) > 1 || count($truth['server']) > 1) {
                $multiSource++;
            }

            foreach ($this->Feed->searchCaches($raw, false) as $hit) {
                $f = $hit['Feed'];
                if (empty($f['uuid'])) {
                    continue;
                }
                $scope = ($f['type'] === 'MISP Server') ? 'server' : 'feed';
                $expected = isset($truth[$scope][$f['id']]) ? $truth[$scope][$f['id']] : array();
                foreach ($f['uuid'] as $u) {
                    $totalUuids++;
                    if (!isset($expected[$u])) {
                        $wrongAttribution++;
                        if (count($examples) < 3) {
                            $examples[] = $v . ' -> ' . $scope . ' ' . $f['id'] . ' / ' . $u;
                        }
                    }
                }
                foreach (array_keys($expected) as $u) {
                    if (!in_array($u, $f['uuid'])) {
                        $missing++;
                        if (count($examples) < 3) {
                            $examples[] = 'MISSING ' . $v . ' -> ' . $scope . ' ' . $f['id'] . ' / ' . $u;
                        }
                    }
                }
            }
        }

        $this->out('');
        $this->out('values with a cache hit checked: ' . $checked);
        $this->out('  of which the lookup set names >1 source: ' . $multiSource);
        $this->out('event uuids returned by searchCaches:   ' . $totalUuids);
        $this->out('  attributed to a source that does not hold them: ' . $wrongAttribution);
        $this->out('  held by the source but not returned:            ' . $missing);
        foreach ($examples as $e) {
            $this->out('  ' . $e);
        }
    }

    /**
     * The values cached under md5($raw) that the lowercased lookup could not
     * reach. Each should now be found, and its lowercase form should not
     * suddenly start hitting.
     */
    private function normalisationGap()
    {
        $redis = $this->Feed->setupRedis();
        $values = array(
            'XOR',
            'US',
            'POST',
            'https://x.com/Malwarehunterr/status/2071679859819237847',
            'https://x.com/Malwarehunterr/status/2071599266104275219',
            'https://x.com/Fact_Finder03/status/2071564839936242109',
        );
        $this->out('=== the normalisation gap, by value ===');
        foreach ($values as $v) {
            $rawIn = $redis->sismember('misp:feed_cache:combined', md5($v)) ? 'yes' : 'no';
            $lowIn = $redis->sismember('misp:feed_cache:combined', md5(strtolower(trim($v)))) ? 'yes' : 'no';
            $this->out(sprintf(
                '  %-56s cached raw=%-3s cached lowercased=%-3s searchCaches hits=%d',
                substr($v, 0, 56), $rawIn, $lowIn, count($this->Feed->searchCaches($v, false))
            ));
        }
    }
}
