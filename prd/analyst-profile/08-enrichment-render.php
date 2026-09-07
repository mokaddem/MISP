<?php
App::uses('View', 'View');
App::uses('ThemeView', 'View');
App::uses('Controller', 'Controller');

/**
 * Scratch shell: render the Enrichment tab for one reader, one value
 * and one declaration, with no HTTP session.
 *
 * `../value-profile-live/24b-external-render.php`'s variant for phase
 * 7 — same view class, same theme, `debug = 2` so a missing key lands
 * in the markup. It exists because the probe asserts the *data* and
 * the thing phase 7 actually ships is a **tab that arrives with boxes
 * ticked**: whether the ticks reach the markup, whether the tray line
 * matches them, and whether a condition renders when there is no rail
 * to hang it on are three questions no assertion about an array can
 * answer.
 *
 * Four cases, chosen so that each one is a state the other three are
 * not:
 *
 *   - `bare` — nothing declared. The strip must not render at all.
 *   - `local` — a local and an external module declared under
 *     `local_only`: one box ticked, one condition, and the tray
 *     saying nothing leaves.
 *   - `open` — the same declaration under `allow_external`: both
 *     ticked, no condition, and the tray counting one outbound query.
 *   - `broken` — a disabled module and two unusable types, so the
 *     strip carries conditions the rail cannot.
 *
 * **Not part of the application.** Copy it in for the duration:
 *
 *   cp prd/analyst-profile/08-enrichment-render.php \
 *      app/Console/Command/ValueEnrichRenderShell.php
 *   app/Console/cake ValueEnrichRender all 1 8.8.8.8 /tmp/vp-p7
 *   rm app/Console/Command/ValueEnrichRenderShell.php
 */
class ValueEnrichRenderShell extends AppShell
{
    public $uses = array('User', 'Module');

    /** Where misp-modules listens; see the probe's `__preflight`. */
    const MODULES_PORT = 6666;

    private $cases = array(
        'bare' => array('auto_run' => array(),
            'cost_posture' => 'local_only'),
        'local' => array(
            'auto_run' => array(
                'text' => array('convert_markdown_to_pdf'),
                'ip-dst' => array('circl_passivedns'),
            ),
            'cost_posture' => 'local_only',
        ),
        'open' => array(
            'auto_run' => array(
                'text' => array('convert_markdown_to_pdf'),
                'ip-dst' => array('circl_passivedns'),
            ),
            'cost_posture' => 'allow_external',
        ),
        'broken' => array(
            'auto_run' => array(
                'ip-dst' => array('virustotal', 'VirusTotal'),
                'md5' => array('hashlookup'),
                'sha256' => array('hashlookup'),
            ),
            'cost_posture' => 'allow_external',
        ),
    );

    /**
     * cake ValueEnrichRender all <userId> <value> [outDir]
     */
    public function all()
    {
        $user = $this->User->getAuthUser((int)$this->args[0]);
        $value = $this->args[1];
        $outDir = isset($this->args[2]) ? $this->args[2] : '/tmp/vp-p7';
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }
        Configure::write('CurrentUserId', $user['id']);
        Configure::write('debug', 2);
        if (!is_array($this->Module->getModules('Enrichment'))) {
            Configure::write(
                'Plugin.Enrichment_services_port',
                self::MODULES_PORT
            );
            $this->out('NOTE: corrected the modules port in-process');
        }

        $this->out(sprintf('reader: %s (%s / org %s)',
            $user['email'], $user['Role']['name'],
            $user['Organisation']['name']));
        $this->out('value:  ' . $value);

        foreach ($this->cases as $name => $enrichment) {
            ClassRegistry::removeObject('ValueProfile');
            $profile = ClassRegistry::init('ValueProfile');
            $data = $profile->forEnrichment($user, $value, array(
                'profile' => array(
                    'name' => 'render-' . $name,
                    'parameters' => array('enrichment' => $enrichment),
                ),
            ));
            $html = $this->render($data, $user);
            $path = $outDir . '/enrichment-' . $name . '.html';
            file_put_contents($path, $html);
            $this->out('');
            $this->out('=== ' . $name . '  (' . $path . ')');
            $this->out(sprintf(
                '    ticked: %d of %d checkboxes · strip: %s',
                $this->ticked($html),
                substr_count($html, 'data-vp-e-select='),
                strpos($html, 'vp-e-profile') === false
                    ? 'absent'
                    : 'present'
            ));
            $this->out($this->text($html));
        }
    }

    /**
     * cake ValueEnrichRender down <userId> <value> [outDir]
     *
     * The case the strip exists for: no rail at all. A reader whose
     * profile names two modules must still be told what became of the
     * declaration, and the tab's own empty state is about the service
     * rather than about them.
     */
    public function down()
    {
        $user = $this->User->getAuthUser((int)$this->args[0]);
        $value = $this->args[1];
        $outDir = isset($this->args[2]) ? $this->args[2] : '/tmp/vp-p7';
        Configure::write('CurrentUserId', $user['id']);
        Configure::write('debug', 2);
        Configure::write(
            'Plugin.Enrichment_services_url',
            'http://127.0.0.1'
        );
        Configure::write('Plugin.Enrichment_services_port', 1);
        $profile = ClassRegistry::init('ValueProfile');
        $data = $profile->forEnrichment($user, $value, array(
            'profile' => array(
                'name' => 'render-down',
                'parameters' => array(
                    'enrichment' => $this->cases['local'],
                ),
            ),
        ));
        $html = $this->render($data, $user);
        $path = $outDir . '/enrichment-down.html';
        file_put_contents($path, $html);
        $this->out('');
        $this->out('=== down  (' . $path . ')');
        $this->out(sprintf(
            '    ticked: %d of %d checkboxes · strip: %s',
            $this->ticked($html),
            substr_count($html, 'data-vp-e-select='),
            strpos($html, 'vp-e-profile') === false
                ? 'absent'
                : 'present'
        ));
        $this->out($this->text($html));
    }

    /**
     * Ticked boxes, counted off the input tags.
     *
     * Not `substr_count($html, 'checked')`, which was the first
     * version and reported *"1 of 0 checkboxes"* on the service-down
     * case — the word was in the strip's own sentence, *"none of these
     * could be checked against what this instance offers"*. A
     * diagnostic that counts prose is worse than none.
     *
     * @param string $html
     * @return int
     */
    private function ticked($html)
    {
        return preg_match_all(
            '/<input\b[^>]*\bchecked\b/i',
            $html
        );
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

    private function render(array $data, array $user)
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
            $html = $view->element('Values/View/value_enrichment');
        } catch (Exception $e) {
            $html = 'EXCEPTION: ' . get_class($e) . ': '
                . $e->getMessage() . "\n" . $e->getTraceAsString();
        }
        $noise = ob_get_clean();
        return $noise . $html;
    }
}
