<?php
App::uses('RailCard', 'Tools/RailCards');
App::uses('RedisTool', 'Tools');

/**
 * Rail cards for a feed. $feed is ['Feed' => [...]] as FeedsController::view
 * loads it, with cached_elements set.
 */
class FeedRailCards
{
    const STALE_AFTER = 86400;
    const OVERLAP_LIMIT = 5;
    const FETCH_LIMIT = 5;

    /**
     * Shape, title and icon of the cards loaded after first paint.
     *
     * @return array
     */
    private function heads()
    {
        return [
            'feed-overlap' => ['list', __('Overlap'), 'fas fa-circle-half-stroke'],
            'feed-fetches' => ['list', __('Last fetches'), 'fas fa-download'],
        ];
    }

    /**
     * @param string $cardId
     * @param int $feedId
     * @return array
     */
    public function slot($cardId, $feedId)
    {
        list($shape, $title, $icon) = $this->heads()[$cardId];
        return RailCard::slot($shape, $cardId, $title, $icon,
            '/feeds/railCard/' . (int)$feedId . '/' . $cardId);
    }

    /**
     * @param string $cardId
     * @param array $feed
     * @return array
     * @throws NotFoundException
     */
    public function lazy($cardId, array $feed)
    {
        switch ($cardId) {
            case 'feed-overlap':
                return $this->overlap($feed);
            case 'feed-fetches':
                return $this->fetches($feed);
        }
        throw new NotFoundException(__('Invalid rail card.'));
    }

    /**
     * @param array $feed
     * @return array
     */
    public function freshness(array $feed)
    {
        $f = $feed['Feed'];
        $cached = (int)($f['cached_elements'] ?? 0);
        $ts = $this->cacheTimestamp((int)$f['id']);
        if (empty($f['caching_enabled'])) {
            $state = 'muted';
            $headline = __('Caching is off, so the feed is not compared with your data.');
        } else if ($ts === null) {
            $state = 'danger';
            $headline = __('Never cached.');
        } else if (time() - $ts >= self::STALE_AFTER) {
            $state = 'warn';
            $headline = __('Cached %s.', RailCard::ago($ts));
        } else {
            $state = 'ok';
            $headline = __('Cached %s.', RailCard::ago($ts));
        }
        $items = [
            ['label' => __('Cached values'), 'value' => number_format($cached)],
            ['label' => __('Last cached'), 'value' => $ts === null ? __('Never') : date('Y-m-d H:i', $ts)],
            ['label' => __('Pulling'), 'value' => $f['enabled'] ? __('On') : __('Off')],
        ];
        return RailCard::status('feed-freshness', __('Freshness'), 'fas fa-clock-rotate-left', $state, $headline, $items);
    }

    /**
     * The other cached feeds and servers sharing the most values with this
     * one. One cardinality per source; the members are never fetched.
     *
     * @param array $feed
     * @return array
     */
    public function overlap(array $feed)
    {
        $id = (int)$feed['Feed']['id'];
        $cached = (int)($feed['Feed']['cached_elements'] ?? 0);
        $sources = [];
        foreach (ClassRegistry::init('Feed')->find('all', [
            'recursive' => -1,
            'conditions' => ['Feed.caching_enabled' => 1, 'Feed.id !=' => $id],
            'fields' => ['Feed.id', 'Feed.name'],
        ]) as $row) {
            $sources[] = ['feed', $row['Feed']['id'], $row['Feed']['name']];
        }
        foreach (ClassRegistry::init('Server')->find('all', [
            'recursive' => -1,
            'conditions' => ['Server.caching_enabled' => 1],
            'fields' => ['Server.id', 'Server.name'],
        ]) as $row) {
            $sources[] = ['server', $row['Server']['id'], $row['Server']['name']];
        }
        $rows = [];
        if ($cached > 0 && !empty($sources)) {
            $redis = RedisTool::init();
            $mine = 'misp:feed_cache:' . $id;
            foreach ($sources as list($scope, $sourceId, $name)) {
                $count = $this->intersectionSize($redis, $mine, 'misp:' . $scope . '_cache:' . $sourceId);
                if ($count > 0) {
                    $rows[] = [
                        'label' => $name,
                        'href' => '/' . $scope . 's/view/' . $sourceId,
                        'icon' => $scope === 'feed' ? 'fas fa-rss' : 'fas fa-server',
                        'meta' => [$scope === 'feed' ? __('Feed') : __('Server')],
                        'count' => $count,
                        'share' => round($count / $cached, 4),
                    ];
                }
            }
        }
        usort($rows, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        $hidden = count($rows) - self::OVERLAP_LIMIT;
        // the Coverage tab only exists while the feed is cached
        $coverage = empty($feed['Feed']['caching_enabled']) ? null : '#tab-coverage';
        list(, $title, $icon) = $this->heads()['feed-overlap'];
        return RailCard::rows(
            'feed-overlap',
            $title,
            $icon,
            array_slice($rows, 0, self::OVERLAP_LIMIT),
            $hidden > 0 ? ['label' => __n('%s more source', '%s more sources', $hidden, $hidden), 'href' => $coverage] : null,
            [
                'link' => $coverage ? ['label' => __('Coverage'), 'href' => $coverage] : null,
                'empty' => empty($feed['Feed']['caching_enabled'])
                    ? __('Enable caching to compare this feed with other sources.')
                    : __('Shares no value with another cached source.'),
                'lazy' => true,
            ]
        );
    }

    /**
     * Recent fetch jobs that covered this feed. Fetches started for all
     * feeds at once say "all feeds" in their input.
     *
     * @param array $feed
     * @return array
     */
    public function fetches(array $feed)
    {
        $id = (int)$feed['Feed']['id'];
        $jobs = ClassRegistry::init('Job')->find('all', [
            'recursive' => -1,
            'conditions' => [
                'Job.job_type' => 'fetch_feeds',
                'Job.job_input' => ['Feed: ' . $id, 'Feed: all'],
            ],
            'fields' => ['Job.id', 'Job.job_input', 'Job.status', 'Job.message', 'Job.date_modified'],
            'order' => ['Job.id' => 'DESC'],
            'limit' => self::FETCH_LIMIT,
        ]);
        $statuses = [
            Job::STATUS_WAITING => [__('Waiting'), 'muted'],
            Job::STATUS_RUNNING => [__('Running'), 'info'],
            Job::STATUS_FAILED => [__('Failed'), 'danger'],
            Job::STATUS_COMPLETED => [__('Done'), 'ok'],
        ];
        $rows = [];
        foreach ($jobs as $job) {
            $j = $job['Job'];
            list($label, $tone) = $statuses[(int)$j['status']] ?? [__('Unknown'), 'muted'];
            // ServerShell completes a fetch job either way and reports failures only in its message
            if ((int)$j['status'] === Job::STATUS_COMPLETED
                && preg_match('/(\d+) feeds pulled successfully, (\d+) feeds could not be pulled/', $j['message'], $m)
                && (int)$m[2] > 0) {
                list($label, $tone) = (int)$m[1] > 0 ? [__('Partly failed'), 'warn'] : [__('Failed'), 'danger'];
            }
            $rows[] = [
                'label' => $j['message'] ?: $label,
                'icon' => 'fas fa-download',
                'meta' => [
                    $j['date_modified'],
                    $j['job_input'] === 'Feed: all' ? __('all feeds') : __('this feed'),
                ],
                'badge' => ['label' => $label, 'tone' => $tone],
                'tone' => in_array($tone, ['warn', 'danger'], true) ? $tone : null,
            ];
        }
        list(, $title, $icon) = $this->heads()['feed-fetches'];
        return RailCard::rows(
            'feed-fetches',
            $title,
            $icon,
            $rows,
            ['label' => __('All jobs'), 'href' => '/jobs/index'],
            ['empty' => __('Never fetched.'), 'lazy' => true]
        );
    }

    private function cacheTimestamp($id)
    {
        $ts = RedisTool::init()->get('misp:feed_cache_timestamp:' . $id);
        return $ts === false ? null : (int)$ts;
    }

    private function intersectionSize($redis, $a, $b)
    {
        try {
            $count = $redis->rawCommand('SINTERCARD', 2, $a, $b);
        } catch (Exception $e) {
            $count = false;
        }
        // SINTERCARD needs Redis 7
        return $count === false ? count($redis->sInter($a, $b)) : (int)$count;
    }
}
