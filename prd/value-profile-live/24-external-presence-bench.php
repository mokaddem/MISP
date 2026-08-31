<?php

/**
 * Scratch shell: does checking two hashes instead of one cost anything, and
 * does feed size matter? Deleted after the run.
 */
class ValueExternalBenchShell extends AppShell
{
    public $uses = array('Feed', 'Server');

    const ITER = 400;

    private $hitting = array(
        'text/html',
        'application/x-dosexec',
        'zxzhjlk.artenadigital.com',
        'frostapp.fr',
        'mailout-us.gmx.ru',
    );
    private $missing = array(
        'this-value-is-not-in-any-feed-1',
        'this-value-is-not-in-any-feed-2',
    );

    public function main()
    {
        $redis = $this->Feed->setupRedis();
        $this->sismemberVsCardinality($redis);
        $this->searchCachesCost();
        $this->doubleHashCost($redis);
        $this->pipelined($redis);
    }

    private function ms($t)
    {
        return sprintf('%0.4f ms', $t * 1000);
    }

    /** Is sismember sensitive to how big the set is? */
    private function sismemberVsCardinality($redis)
    {
        $this->out('=== sismember latency vs set cardinality ===');
        $sets = array(
            'misp:feed_cache:62' => 'Malware Bazaar',
            'misp:feed_cache:41' => 'URLHaus',
            'misp:feed_cache:2' => 'Botvrij',
            'misp:feed_cache:1' => 'CIRCL OSINT',
            'misp:feed_cache:64' => 'Threatfox',
            'misp:feed_cache:combined' => 'combined',
        );
        foreach ($sets as $key => $name) {
            $card = $redis->scard($key);
            $h = md5('text/html');
            $t = microtime(true);
            for ($i = 0; $i < self::ITER; $i++) {
                $redis->sismember($key, $h);
            }
            $per = (microtime(true) - $t) / self::ITER;
            $this->out(sprintf('  %-14s scard=%-10d %s per call', $name, $card, $this->ms($per)));
        }
        $this->out('');
    }

    /** What one searchCaches call costs today, hit and miss. */
    private function searchCachesCost()
    {
        $this->out('=== Feed::searchCaches, as shipped ===');
        foreach (array('hit' => $this->hitting, 'miss' => $this->missing) as $label => $values) {
            $t = microtime(true);
            $n = 0;
            for ($i = 0; $i < self::ITER; $i++) {
                foreach ($values as $v) {
                    $this->Feed->searchCaches($v, false);
                    $n++;
                }
            }
            $per = (microtime(true) - $t) / $n;
            $this->out(sprintf('  %-5s %s per value', $label, $this->ms($per)));
        }
        $this->out('');
    }

    /**
     * The redis half of the fix: every membership test and every uuid read,
     * once against the lowercased hash and once against the raw one.
     */
    private function doubleHashCost($redis)
    {
        $this->out('=== redis calls: one hash vs two ===');
        $feeds = $this->Feed->find('all', array(
            'conditions' => array('caching_enabled' => 1),
            'recursive' => -1,
            'fields' => array('Feed.id', 'Feed.source_format'),
        ));
        $servers = $this->Server->find('all', array(
            'conditions' => array('caching_enabled' => 1),
            'recursive' => -1,
            'fields' => array('Server.id'),
        ));

        $mixedCase = array(
            'XOR',
            'POST',
            'https://x.com/Malwarehunterr/status/2071679859819237847',
            'https://x.com/Fact_Finder03/status/2071599266104275219',
            'US',
        );

        foreach (array('all-lowercase' => $this->hitting, 'mixed-case' => $mixedCase) as $label => $values) {
        $this->out('  -- ' . $label . ' --');
        foreach (array(1, 2) as $hashCount) {
            $t = microtime(true);
            $calls = 0;
            for ($i = 0; $i < self::ITER; $i++) {
                foreach ($values as $v) {
                    $hashes = $hashCount === 1
                        ? array(md5(strtolower(trim($v))))
                        : array(md5(strtolower(trim($v))), md5($v));
                    $hashes = array_unique($hashes);
                    foreach ($hashes as $h) {
                        $redis->sismember('misp:feed_cache:combined', $h);
                        $calls++;
                        foreach ($feeds as $f) {
                            $redis->sismember('misp:feed_cache:' . $f['Feed']['id'], $h);
                            $calls++;
                            if ($f['Feed']['source_format'] === 'misp') {
                                $redis->smembers('misp:feed_cache:event_uuid_lookup:' . $h);
                                $calls++;
                            }
                        }
                        $redis->sismember('misp:server_cache:combined', $h);
                        $calls++;
                        foreach ($servers as $s) {
                            $redis->sismember('misp:server_cache:' . $s['Server']['id'], $h);
                            $redis->smembers('misp:server_cache:event_uuid_lookup:' . $h);
                            $calls += 2;
                        }
                    }
                }
            }
            $elapsed = microtime(true) - $t;
            $perValue = $elapsed / (self::ITER * count($values));
            $this->out(sprintf(
                '    %d hash(es): %s per value, %0.1f redis calls per value',
                $hashCount, $this->ms($perValue), $calls / (self::ITER * count($values))
            ));
        }
        }
        $this->out('');
    }

    /** The same two-hash work, but as one round trip. */
    private function pipelined($redis)
    {
        $this->out('=== two hashes, pipelined into one round trip ===');
        $feeds = $this->Feed->find('all', array(
            'conditions' => array('caching_enabled' => 1),
            'recursive' => -1,
            'fields' => array('Feed.id', 'Feed.source_format'),
        ));
        $servers = $this->Server->find('all', array(
            'conditions' => array('caching_enabled' => 1),
            'recursive' => -1,
            'fields' => array('Server.id'),
        ));

        $t = microtime(true);
        for ($i = 0; $i < self::ITER; $i++) {
            foreach ($this->hitting as $v) {
                $hashes = array_unique(array(md5(strtolower(trim($v))), md5($v)));
                $pipe = $redis->pipeline();
                foreach ($hashes as $h) {
                    $pipe->sismember('misp:feed_cache:combined', $h);
                    foreach ($feeds as $f) {
                        $pipe->sismember('misp:feed_cache:' . $f['Feed']['id'], $h);
                        if ($f['Feed']['source_format'] === 'misp') {
                            $pipe->smembers('misp:feed_cache:event_uuid_lookup:' . $h);
                        }
                    }
                    $pipe->sismember('misp:server_cache:combined', $h);
                    foreach ($servers as $s) {
                        $pipe->sismember('misp:server_cache:' . $s['Server']['id'], $h);
                        $pipe->smembers('misp:server_cache:event_uuid_lookup:' . $h);
                    }
                }
                $pipe->exec();
            }
        }
        $per = (microtime(true) - $t) / (self::ITER * count($this->hitting));
        $this->out(sprintf('  %s per value', $this->ms($per)));
        $this->out('');

        $this->out('=== extrapolation: cost is per source, not per entry ===');
        $sources = count($feeds) + count($servers);
        $this->out(sprintf('  this instance has %d cached sources', $sources));
        foreach (array(6, 20, 50, 98) as $n) {
            $this->out(sprintf(
                '  %-3d sources: %d unpipelined calls for two hashes, 1 round trip if pipelined',
                $n, 2 * (2 + $n * 2)
            ));
        }
    }
}
