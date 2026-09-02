<?php

/**
 * Scratch shell: read B5's fold back off the live instance — the
 * warninglist facet, the row field, the tokens and the two counts the
 * caption prints — plus the narrowing the facet offers.
 *
 * Not part of the application. Deleted after the run.
 */
class ValueWarninglistVerifyShell extends AppShell
{
    public $uses = array('User', 'ValueProfile');

    public function main()
    {
        $value = $this->args[0] ?? '8.8.8.8';
        $filterKey = $this->args[1] ?? null;
        $filterVal = $this->args[2] ?? null;
        $user = $this->User->getAuthUser(1);
        $options = array('fresh' => true);
        if ($filterKey !== null) {
            $options['filters'] = array($filterKey => array($filterVal));
            $options['fresh'] = false;
        }
        $t = microtime(true);
        $profile = $this->ValueProfile->forRelationCooccurrence($user,
            $value, $options);
        $ms = (microtime(true) - $t) * 1000;
        $co = $profile['relationships']['cooccurrence'];

        $this->out(sprintf('%s%s — %.0f ms', $value,
            $filterKey === null ? '' : " [$filterKey=$filterVal]", $ms));
        $this->out(sprintf('distinct %s · carried %s · matched %s',
            number_format($co['distinct_values']),
            number_format(count($co['rollups']['value']['rows'])),
            number_format($co['matched'])));
        $this->out(sprintf('warninglists_checked %s · listed %s',
            $co['warninglists_checked'], $co['warninglists_listed']));

        $this->out('--- facet: warninglist ---');
        if (empty($co['facets']['warninglist'])) {
            $this->out('  (absent)');
        }
        foreach ($co['facets']['warninglist'] as $entry) {
            $this->out(sprintf('  %-42s count %-6s listed %s',
                $entry['value'] . '  "' . ($entry['label'] ?? '') . '"',
                number_format($entry['count']),
                $entry['listed'] ?? '-'));
        }

        $this->out('--- first 8 rows ---');
        $shown = 0;
        foreach ($co['rollups']['value']['rows'] as $row) {
            $marks = array();
            foreach ($row['warninglists'] as $list) {
                $marks[] = $list['name'] . '/' . $list['matched'];
            }
            $wlTokens = array();
            foreach ($row['tokens'] as $token) {
                if (strpos($token, 'warninglist:') === 0) {
                    $wlTokens[] = $token;
                }
            }
            $this->out(sprintf('  %-30s %-9s %-28s %s',
                mb_substr($row['value'], 0, 30),
                $row['type'],
                implode(',', $wlTokens),
                implode(' | ', $marks)));
            if (++$shown >= 8) {
                break;
            }
        }
        $this->out('');
    }
}
