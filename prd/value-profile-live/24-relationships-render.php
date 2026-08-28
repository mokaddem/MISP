<?php
App::uses('View', 'View');
App::uses('ThemeView', 'View');
App::uses('Controller', 'Controller');

/**
 * Scratch shell: render the Value Profile Relationships elements
 * against real data, with no HTTP session.
 *
 * Written for phase 24, whose verification had no session cookie for
 * the instance. It drives the same elements through the same view class
 * and theme the controller does, with `debug = 2` so a missing array key
 * or an undefined variable lands in the markup instead of being
 * swallowed. The output is a file per panel, which is what §12.4 of the
 * phase document asserts against.
 *
 * **Not part of the application.** It lives beside the phase document
 * that explains it rather than under `app/Console/Command/`, because
 * §14.8 of the contract declines to build test scaffolding into MISP.
 * To run it, copy it into `app/Console/Command/` for the duration:
 *
 *   cp prd/value-profile-live/24-relationships-render.php \
 *      app/Console/Command/ValueRenderShell.php
 *   app/Console/cake ValueRender panels 1 8.8.8.8 /tmp/vp
 *   rm app/Console/Command/ValueRenderShell.php
 *
 * The `$panels` map is the only Relationships-specific thing in it; a
 * later phase converting another tab changes that line and nothing else.
 *
 * One artefact of running outside HTTP, and it is not a bug in the page:
 * `Html->assetUrl` resolves against the console's own path, so an asset
 * URL in the output reads `/var/www/MISP/app/Console/js/…`. Rewrite the
 * prefix before serving a fragment to a browser.
 */
class ValueRenderShell extends AppShell
{
    public $uses = array('User');

    private $panels = array(
        'value_relation_cooccurrence' => 'forRelationCooccurrence',
        'value_relation_near_match' => 'forRelationNearMatch',
        'value_relation_asserted' => 'forRelationAsserted',
        'value_relation_graph' => 'forRelationGraph',
        'value_relation_settings' => 'forRelationSettings',
    );

    /**
     * cake ValueRender panels <userId> <value> [outDir]
     */
    public function panels()
    {
        $user = $this->User->getAuthUser((int)$this->args[0]);
        $value = $this->args[1];
        $outDir = isset($this->args[2]) ? $this->args[2] : '/tmp/vp';
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }
        Configure::write('CurrentUserId', $user['id']);
        // Notices and warnings must land in the fragment, not be
        // swallowed: a `d-none` band or a missing key is exactly what
        // this pass is looking for.
        Configure::write('debug', 2);

        foreach ($this->panels as $element => $method) {
            ClassRegistry::removeObject('ValueProfile');
            $profile = ClassRegistry::init('ValueProfile');
            $data = $profile->$method($user, $value);
            $html = $this->render($element, $data, $user);
            $path = $outDir . '/' . $element . '.html';
            file_put_contents($path, $html);
            $this->out(sprintf('%-32s %7d bytes  %s',
                $element, strlen($html), $path));
        }
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
            // AppController sets this on every request, ajax included.
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
}
