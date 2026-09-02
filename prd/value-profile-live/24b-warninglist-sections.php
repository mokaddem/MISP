<?php

/**
 * Scratch shell: read the warninglist marking back off the object-
 * scoped folds — the sibling table and the dated relations — the way
 * `24b-warninglist-verify.php` does for the ranked one.
 *
 * Not part of the application. Deleted after the run.
 */
class ValueWlSectionsShell extends AppShell
{
    public $uses = array('User', 'ValueProfile');

    public function main()
    {
        $value = $this->args[0] ?? '8.8.8.8';
        $user = $this->User->getAuthUser(1);
        $profile = $this->ValueProfile->forRelationCooccurrence($user,
            $value, array('fresh' => true));
        $co = $profile['relationships']['cooccurrence'];
        $this->section('siblings', $co['siblings'], 'sibwarninglist');

        $dated = $this->ValueProfile->forRelationDated($user, $value);
        $this->section('dated', $dated['relationships']['dated'],
            'datedwarninglist');
    }

    private function section($name, array $data, $facetKey)
    {
        $this->out('=== ' . $name . ' ===');
        $this->out(sprintf('rows %d · total %d · checked %s · listed %s',
            count($data['rows']),
            $data['total'],
            isset($data['warninglists_checked'])
                ? $data['warninglists_checked'] : '-',
            isset($data['warninglists_listed'])
                ? $data['warninglists_listed'] : '-'));

        $facets = isset($data['facets'][$facetKey])
            ? $data['facets'][$facetKey]
            : array();
        if (empty($facets)) {
            $this->out('  facet: (absent)');
        }
        foreach ($facets as $entry) {
            $this->out(sprintf('  facet %-44s %-5s listed %s',
                $entry['value'],
                $entry['count'],
                isset($entry['listed']) ? $entry['listed'] : '-'));
        }

        $shown = 0;
        foreach ($data['rows'] as $row) {
            if (empty($row['warninglists'])) {
                continue;
            }
            $names = array();
            foreach ($row['warninglists'] as $list) {
                $names[] = $list['name'] . '/' . $list['matched'];
            }
            $tokens = array();
            foreach ($row['tokens'] as $token) {
                if (strpos($token, $facetKey . ':') === 0) {
                    $tokens[] = $token;
                }
            }
            $this->out(sprintf('  %-34s %-9s %s',
                mb_substr($row['value'], 0, 34),
                $row['type'],
                implode(' | ', $names)));
            $this->out('      ' . implode(' ', $tokens));
            if (++$shown >= 6) {
                break;
            }
        }
        /*
         * One unlisted row's tokens too: the complement is what the
         * switch matches, and a fold that forgot to stamp it would
         * still look right from the listed rows alone.
         */
        foreach ($data['rows'] as $row) {
            if (!empty($row['warninglists'])) {
                continue;
            }
            $tokens = array();
            foreach ($row['tokens'] as $token) {
                if (strpos($token, $facetKey . ':') === 0) {
                    $tokens[] = $token;
                }
            }
            $this->out('  unlisted ' . mb_substr($row['value'], 0, 30)
                . ' -> ' . (empty($tokens) ? '(no token)'
                    : implode(' ', $tokens)));
            break;
        }
        $this->out('');
    }
}
