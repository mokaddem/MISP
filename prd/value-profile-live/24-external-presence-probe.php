<?php

/**
 * Scratch shell: probe what Feed::searchCaches() actually returns for a
 * value, now that a feed and a server are cached on this instance.
 *
 * Not part of the application. Deleted after the run.
 */
class ValueExternalProbeShell extends AppShell
{
    public $uses = array('Feed', 'Server', 'MispAttribute');

    private $mispFeeds = array();
    private $otherFeeds = array();
    private $servers = array();

    public function main()
    {
        $this->state();
        $this->classify();
    }

    private function state()
    {
        $redis = $this->Feed->setupRedis();
        $this->out('=== cache state ===');
        $feeds = $this->Feed->find('all', array(
            'conditions' => array('caching_enabled' => 1),
            'recursive' => -1,
            'fields' => array('Feed.id', 'Feed.name', 'Feed.source_format',
                'Feed.enabled', 'Feed.lookup_visible'),
        ));
        foreach ($feeds as $f) {
            $f = $f['Feed'];
            if ($f['source_format'] === 'misp') {
                $this->mispFeeds[$f['id']] = $f;
            } else {
                $this->otherFeeds[$f['id']] = $f;
            }
        }
        $servers = $this->Server->find('all', array(
            'conditions' => array('caching_enabled' => 1),
            'recursive' => -1,
            'fields' => array('Server.id', 'Server.name'),
        ));
        foreach ($servers as $s) {
            $this->servers[$s['Server']['id']] = $s['Server'];
        }
        $this->out('misp-format cached feeds: ' . implode(', ', array_keys($this->mispFeeds)));
        $this->out('other-format cached feeds: ' . implode(', ', array_keys($this->otherFeeds)));
        $this->out('cached servers: ' . implode(', ', array_keys($this->servers)));
        $this->out('');
    }

    private function classify()
    {
        $redis = $this->Feed->setupRedis();
        $rows = $this->MispAttribute->find('all', array(
            'recursive' => -1,
            'fields' => array('DISTINCT Attribute.value1'),
            'conditions' => array('Attribute.deleted' => 0, 'Attribute.value1 !=' => ''),
            'limit' => 40000,
        ));

        $stats = array(
            'scanned' => count($rows),
            'anyHit' => 0,
            'multiMispFeed' => 0,
            'onlyOtherFormatFeed' => 0,
            'serverOnly' => 0,
            'uuidSpansFeeds' => 0,
            'normGap' => 0,
            'noUuidDespiteMispHit' => 0,
        );
        $uuidCounts = array();
        $misattributed = null;
        $onlyCsvExample = null;
        $normGapExamples = array();

        foreach ($rows as $r) {
            $v = $r['Attribute']['value1'];
            $h = md5(strtolower(trim($v)));

            // normalisation gap: raw hash present, lowercased hash absent
            if (strtolower($v) !== $v) {
                if ($redis->sismember('misp:feed_cache:combined', md5($v))
                    && !$redis->sismember('misp:feed_cache:combined', $h)) {
                    $stats['normGap']++;
                    if (count($normGapExamples) < 6) {
                        $normGapExamples[] = $v;
                    }
                }
            }

            $inFeed = $redis->sismember('misp:feed_cache:combined', $h);
            $inServer = $redis->sismember('misp:server_cache:combined', $h);
            if (!$inFeed && !$inServer) {
                continue;
            }
            $stats['anyHit']++;

            $mispHits = array();
            $otherHits = array();
            if ($inFeed) {
                foreach ($this->mispFeeds as $id => $f) {
                    if ($redis->sismember('misp:feed_cache:' . $id, $h)) {
                        $mispHits[] = $id;
                    }
                }
                foreach ($this->otherFeeds as $id => $f) {
                    if ($redis->sismember('misp:feed_cache:' . $id, $h)) {
                        $otherHits[] = $id;
                    }
                }
            }
            $serverHits = array();
            if ($inServer) {
                foreach ($this->servers as $id => $s) {
                    if ($redis->sismember('misp:server_cache:' . $id, $h)) {
                        $serverHits[] = $id;
                    }
                }
            }

            if (count($mispHits) > 1) {
                $stats['multiMispFeed']++;
                if ($misattributed === null) {
                    $misattributed = array($v, $mispHits);
                }
            }
            if (empty($mispHits) && !empty($otherHits) && empty($serverHits)) {
                $stats['onlyOtherFormatFeed']++;
                if ($onlyCsvExample === null) {
                    $onlyCsvExample = array($v, $otherHits);
                }
            }
            if (empty($mispHits) && empty($otherHits) && !empty($serverHits)) {
                $stats['serverOnly']++;
            }

            $fu = $redis->smembers('misp:feed_cache:event_uuid_lookup:' . $h);
            $su = $redis->smembers('misp:server_cache:event_uuid_lookup:' . $h);
            $n = count($fu) + count($su);
            $uuidCounts[] = $n;
            if (!empty($mispHits) && empty($fu)) {
                $stats['noUuidDespiteMispHit']++;
            }
            $srcInUuids = array();
            foreach ($fu as $u) {
                $srcInUuids[explode('/', $u)[0]] = true;
            }
            if (count($srcInUuids) > 1) {
                $stats['uuidSpansFeeds']++;
                if ($misattributed === null || count($misattributed) < 3) {
                    $misattributed = array($v, $mispHits, array_keys($srcInUuids));
                }
            }
        }

        $this->out('=== classification over ' . $stats['scanned'] . ' distinct local values ===');
        foreach ($stats as $k => $val) {
            $this->out(sprintf('  %-24s %d', $k, $val));
        }
        if (!empty($uuidCounts)) {
            sort($uuidCounts);
            $this->out(sprintf(
                '  remote events per hitting value: min=%d median=%d p95=%d max=%d  (zero for %d)',
                $uuidCounts[0],
                $uuidCounts[intdiv(count($uuidCounts), 2)],
                $uuidCounts[(int)floor(count($uuidCounts) * 0.95)],
                end($uuidCounts),
                count(array_filter($uuidCounts, function ($n) { return $n === 0; }))
            ));
        }
        $this->out('');

        if (!empty($normGapExamples)) {
            $this->out('normalisation-gap values (in cache under md5(raw), invisible to searchCaches):');
            foreach ($normGapExamples as $ex) {
                $this->out('  ' . $ex);
            }
            $this->out('');
        }

        if ($onlyCsvExample !== null) {
            $this->out('Overview-only example (non-misp feed, no remote event): '
                . $onlyCsvExample[0] . ' -> feeds ' . implode(',', $onlyCsvExample[1]));
        }
        if ($misattributed !== null) {
            $this->out('=== multi-source uuid case: ' . $misattributed[0] . ' ===');
            $this->dumpValue($misattributed[0], $redis);
        } else {
            $this->out('No value hit more than one misp-format feed in this sample.');
            $this->out('Constructing the case directly against the uuid sets instead:');
            $this->syntheticMisattribution($redis);
        }
    }

    /**
     * Find any cached value whose global uuid set names more than one feed,
     * by scanning the uuid-lookup keys rather than local attribute values.
     */
    private function syntheticMisattribution($redis)
    {
        $it = null;
        $checked = 0;
        $found = 0;
        while (($keys = $redis->scan($it, 'misp:feed_cache:event_uuid_lookup:*', 1000)) !== false) {
            foreach ($keys as $key) {
                $checked++;
                $members = $redis->smembers($key);
                $src = array();
                foreach ($members as $m) {
                    $src[explode('/', $m)[0]] = true;
                }
                if (count($src) > 1) {
                    $found++;
                    if ($found <= 3) {
                        $this->out('  ' . $key);
                        $this->out('    feeds named in set: ' . implode(',', array_keys($src)));
                        $this->out('    members: ' . json_encode(array_slice($members, 0, 6)));
                    }
                }
            }
            if ($checked > 60000) {
                break;
            }
        }
        $this->out(sprintf('  scanned %d uuid-lookup keys, %d name more than one feed',
            $checked, $found));
    }

    private function dumpValue($v, $redis)
    {
        $h = md5(strtolower(trim($v)));
        $this->out('raw feed uuid set:   ' . json_encode(
            $redis->smembers('misp:feed_cache:event_uuid_lookup:' . $h)));
        $full = $this->Feed->searchCaches($v, false);
        foreach ($full as $hit) {
            $f = $hit['Feed'];
            $this->out(sprintf('  type=%-12s id=%s name=%s uuids=%d',
                $f['type'], $f['id'], $f['name'],
                isset($f['uuid']) ? count($f['uuid']) : 0));
            if (!empty($f['direct_urls'])) {
                foreach (array_slice($f['direct_urls'], 0, 6) as $u) {
                    $this->out('    ' . $u['url']);
                }
            }
        }
    }
}
