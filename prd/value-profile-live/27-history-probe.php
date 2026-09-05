<?php
/**
 * Phase 27 probe — exercises ValueProfile::forHistory against the real
 * database and renders value_history.ctp, with no HTTP session.
 *
 * Not product code. `prd/value-profile-live/27-history-probe.php` is the
 * copy kept with the phase.
 */
App::uses('AppShell', 'Console/Command');
App::uses('View', 'View');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('Controller', 'Controller');
App::uses('ACLComponent', 'Controller/Component');

class Phase27Shell extends AppShell
{
    public $uses = array('ValueProfile', 'User');

    public function getOptionParser()
    {
        $parser = parent::getOptionParser();
        $parser->addOption('all', array('boolean' => true));
        $parser->addOption('off', array('boolean' => true));
        return $parser;
    }

    public function main()
    {
        $values = func_get_args();
        if (empty($values)) {
            $values = array('8.8.8.8', '193.161.193.99', '443',
                '2.2.2.2', 'google.com');
        }
        $userIds = array(1, 4);
        foreach ($userIds as $userId) {
            $user = $this->User->getAuthUser($userId);
            if (empty($user)) {
                $this->out("user $userId: absent");
                continue;
            }
            Configure::write('CurrentUserId', $userId);
            if ($this->params['off']) {
                // State 2, displayed by flipping in-process. Writing
                // the setting with `Admin setSetting` as root leaves
                // config.php unreadable by www-data and 302-loops the
                // instance, and this state needs no persistence.
                Configure::write('MISP.log_new_audit', false);
            }
            $this->out(sprintf(
                "\n=== %s (org %s, site admin: %s) ===",
                $user['email'],
                $user['Organisation']['name'],
                empty($user['Role']['perm_site_admin']) ? 'no' : 'yes'
            ));
            foreach ($values as $value) {
                $this->probe($user, $value);
            }
        }
    }

    private function probe(array $user, $value)
    {
        $db = ConnectionManager::getDataSource('default');
        $db->fullDebug = true;
        $ref = new ReflectionProperty(get_class($db), '_queriesLogMax');
        $ref->setAccessible(true);
        $ref->setValue($db, 100000);
        $before = count($db->getLog(false, false)['log']);
        $start = microtime(true);
        $profile = $this->ValueProfile->forHistory(
            $user,
            $value,
            array('window' => $this->params['all'] ? 'all' : null)
        );
        $ms = (microtime(true) - $start) * 1000;
        $queries = count($db->getLog(false, false)['log']) - $before;
        $h = $profile['history'];
        $actors = array();
        $rows = $h['event_entries'];
        foreach ($h['groups'] as $group) {
            foreach ($group['entries'] as $row) {
                $rows[] = $row;
            }
        }
        $diffs = 0;
        foreach ($rows as $row) {
            $actors[(string)$row['actor']] = true;
            if (!empty($row['change'])) {
                $diffs++;
            }
        }
        unset($actors['']);
        $this->out(sprintf(
            '%-18s recorded=%s corpus=%-7s shown=%-5s sections=%-5s'
            . ' outside=%-6s events=%-4s diffs=%-5s actors=%-2s'
            . ' Q=%-3s %.0fms',
            $value,
            $h['recorded'] ? 'y' : 'n',
            $h['entries'],
            $h['shown'],
            $h['occurrences'],
            $h['outside'],
            count($h['event_entries']),
            $diffs,
            count($actors),
            $queries,
            $ms
        ));
        if (!empty($actors)) {
            $this->out('    actors: ' . implode(', ', array_keys($actors)));
        }
        $window = $h['window'];
        $this->out(sprintf(
            '    window: %s   span: %s   chart bars: %s',
            $window === null
                ? 'all time'
                : $window['from'] . ' → ' . $window['to'],
            $h['span'] === null
                ? '—'
                : $h['span']['from'] . ' → ' . $h['span']['to'],
            $h['chart'] === null ? '—' : count($h['chart']['counts'])
        ));
        $models = array();
        foreach ($h['facets']['model'] as $facet) {
            if ($facet['count'] > 0) {
                $models[] = $facet['label'] . ' ' . $facet['count'];
            }
        }
        $this->out('    models: ' . implode(', ', $models));
        if (isset($h['knowable'])) {
            $k = $h['knowable'];
            $this->out(sprintf(
                '    knowable: %d occurrences, last edit %s,'
                . ' %d publications, %d sightings',
                $k['occurrences'],
                $k['edited'] === null
                    ? '—'
                    : date('Y-m-d', $k['edited']),
                count($k['publications']),
                $k['sightings']
            ));
        }
        $this->consistency($h);
        $this->render($user, $value, $profile);
    }

    /**
     * Phase 16's rule, re-run over live rows: every number in the panel
     * is derived from `groups` and `event_entries`, so they have to
     * agree with each other.
     */
    private function consistency(array $h)
    {
        $problems = array();
        $sum = count($h['event_entries']);
        foreach ($h['groups'] as $group) {
            $sum += $group['count'];
            if ($group['count'] !== count($group['entries'])) {
                $problems[] = 'group count != rows';
            }
            if (array_sum($group['mix']) !== $group['count']) {
                $problems[] = 'mix != count on ' . $group['attribute_id'];
            }
            if ($group['total'] < $group['count']) {
                $problems[] = 'total < count on ' . $group['attribute_id'];
            }
        }
        if ($sum !== $h['shown']) {
            $problems[] = "sections+events ($sum) != shown ({$h['shown']})";
        }
        if ($h['shown'] > $h['entries']) {
            $problems[] = 'shown > corpus';
        }
        if (count($h['groups']) !== $h['occurrences']) {
            $problems[] = 'groups != occurrences';
        }
        foreach (array('action', 'model', 'org', 'actor') as $key) {
            $total = 0;
            foreach ($h['facets'][$key] as $facet) {
                $total += $facet['count'];
            }
            if ($total !== $h['shown']) {
                $problems[] = "facet $key sums $total, shown {$h['shown']}";
            }
        }
        $this->out('    consistency: ' . (empty($problems)
            ? 'ok'
            : implode('; ', $problems)));
    }

    private function render(array $user, $value, array $profile)
    {
        $request = new CakeRequest('/values/viewHistory');
        $request->params['controller'] = 'values';
        $request->params['action'] = 'viewHistory';
        $request->params['named'] = array();
        $request->params['pass'] = array();
        $controller = new Controller($request, new CakeResponse());
        $controller->theme = 'Overmind';
        $controller->helpers = array('Html', 'Form', 'Session', 'Time',
            'Number', 'OrgImg', 'DistributionLevel', 'Image');
        $controller->components = array('Acl' => array('className' => 'ACL'));
        $controller->constructClasses();
        $acl = new ACLComponent($controller->Components);
        $acl->request = $request;
        $view = new View($controller);
        $view->viewVars = array(
            'baseurl' => '',
            'me' => $user,
            'aclComponent' => $acl,
            'isSiteAdmin' => (bool)$user['Role']['perm_site_admin'],
            'valueProfile' => $profile,
            'valueB64' => base64_encode($value),
        );
        try {
            $html = $view->element('Values/View/value_history');
        } catch (Exception $e) {
            $this->out('    RENDER FAILED: ' . $e->getMessage());
            return;
        }
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($value));
        $path = TMP . 'phase27-' . $user['id'] . '-' . $slug . '.html';
        file_put_contents($path, $html);
        $this->out(sprintf(
            '    rendered: %d bytes, %d sections drawn → %s',
            strlen($html),
            substr_count($html, 'data-vp-audit-section'),
            $path
        ));
    }
}
