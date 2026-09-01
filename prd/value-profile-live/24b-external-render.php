<?php
App::uses('View', 'View');
App::uses('ThemeView', 'View');
App::uses('Controller', 'Controller');

/**
 * Scratch shell: render the outside-this-instance section and the
 * Overview card it details, for one reader and one value, with no HTTP
 * session.
 *
 * B3's variant of `24-relationships-render.php` — same view class, same
 * theme, `debug = 2` so a missing key lands in the markup. It renders
 * *both* surfaces in one pass because the pair's whole contract is that
 * they cannot disagree, and it prints the fragment's plain text as well
 * as writing the HTML, so the sentence a reader actually gets can be
 * asserted without a browser.
 *
 * A fourth argument names an extra role flag to force on, which is how
 * the `perm_view_feed_correlations` reader B3 tightened the rule for is
 * reproduced without writing to `roles`.
 *
 * **Not part of the application.** Copy it in for the duration:
 *
 *   cp prd/value-profile-live/24b-external-render.php \
 *      app/Console/Command/ValueExtRenderShell.php
 *   app/Console/cake ValueExtRender one 1 24576 /tmp/vp-b3
 *   rm app/Console/Command/ValueExtRenderShell.php
 */
class ValueExtRenderShell extends AppShell
{
    public $uses = array('User', 'Feed', 'Server');

    private $panels = array(
        'value_relation_external' => 'forRelationExternal',
        'value_external' => 'forExternal',
    );

    /**
     * cake ValueExtRender one <userId> <value> [outDir] [forcedPerm]
     */
    public function one()
    {
        $user = $this->User->getAuthUser((int)$this->args[0]);
        $value = $this->args[1];
        $outDir = isset($this->args[2]) ? $this->args[2] : '/tmp/vp-b3';
        if (!empty($this->args[3])) {
            $user['Role'][$this->args[3]] = 1;
        }
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }
        Configure::write('CurrentUserId', $user['id']);
        Configure::write('debug', 2);

        $this->out(sprintf('reader: %s (%s / org %s)%s',
            $user['email'], $user['Role']['name'],
            $user['Organisation']['name'],
            empty($this->args[3]) ? '' : ' + ' . $this->args[3]));
        $this->out('value:  ' . $value);
        $this->out('');

        foreach ($this->panels as $element => $method) {
            ClassRegistry::removeObject('ValueProfile');
            $profile = ClassRegistry::init('ValueProfile');
            $data = $profile->$method($user, $value);
            $html = $this->render($element, $data, $user);
            $slug = $element . '-u' . $user['id'] . '-'
                . substr(md5($value . '|' . (string)@$this->args[3]), 0, 6);
            $path = $outDir . '/' . $slug . '.html';
            file_put_contents($path, $html);
            $this->out('--- ' . $element . '  (' . $path . ')');
            $this->out($this->text($html));
            $this->out('');
        }
    }

    /**
     * The fragment's visible text, collapsed — what the reader gets.
     */
    private function text($html)
    {
        $t = preg_replace('#<(script|style)\b.*?</\1>#si', ' ', $html);
        $t = preg_replace('#<(details|tr|div|p|li|h\d)\b#i', "\n<\$1", $t);
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
        $lines = array();
        foreach (explode("\n", $t) as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line));
            if ($line !== '') {
                $lines[] = '    ' . $line;
            }
        }
        return implode("\n", $lines);
    }

    private function render($element, array $data, array $user)
    {
        $controller = new Controller(new CakeRequest(null, false),
            new CakeResponse());
        $controller->theme = 'Overmind';
        $controller->viewPath = 'Values';
        $controller->layout = false;
        $controller->set(array(
            'valueProfile' => $data,
            'valueB64' => rtrim(strtr(base64_encode($data['value']),
                '+/', '-_'), '='),
            'baseurl' => '',
            'queryVersion' => '203',
            'me' => $user,
            'isSiteAdmin' => !empty($user['Role']['perm_site_admin']),
        ));
        $controller->helpers = array('Html', 'Form');
        $view = new ThemeView($controller);
        $view->theme = 'Overmind';
        ob_start();
        try {
            $html = $view->element('Values/View/' . $element);
        } catch (Exception $e) {
            $html = 'EXCEPTION: ' . get_class($e) . ': '
                . $e->getMessage() . "\n" . $e->getTraceAsString();
        }
        $noise = ob_get_clean();
        return $noise . $html;
    }

    /**
     * cake ValueExtRender miss — a local value in no cache, for the
     * novelty state.
     */
    public function miss()
    {
        $redis = $this->Feed->setupRedis();
        $attr = ClassRegistry::init('MispAttribute');
        $rows = $attr->find('all', array(
            'recursive' => -1,
            'fields' => array('DISTINCT Attribute.value1'),
            'conditions' => array('Attribute.deleted' => 0),
            'limit' => 6000,
        ));
        $found = array();
        foreach ($rows as $row) {
            $v = $row['Attribute']['value1'];
            if ($v === '' || $v === null || strlen($v) > 60) {
                continue;
            }
            $md5 = md5(strtolower(trim($v)));
            if (!$redis->sismember('misp:feed_cache:combined', $md5)
                && !$redis->sismember('misp:server_cache:combined', $md5)
            ) {
                $found[] = $v;
                if (count($found) >= 8) {
                    break;
                }
            }
        }
        $this->out('values in no cache: ' . implode(' | ', $found));
    }
}
