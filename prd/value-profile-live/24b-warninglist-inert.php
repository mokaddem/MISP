<?php

/**
 * Scratch shell: render the co-occurrence panel twice over one profile
 * — once through the current template, once through HEAD's — and diff
 * the bytes. `24b-relationships.md` §7 asks that a value with no listed
 * neighbours render byte-identically to what it did before B5, and a
 * grep for absent markup is not that claim.
 *
 * Needs `value_relation_cooccurrence_head.ctp` beside the real element,
 * written with `git show HEAD:…`. Both are deleted after the run.
 *
 * Not part of the application. Deleted after the run.
 */
App::uses('Controller', 'Controller');
App::uses('View', 'View');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('ACLComponent', 'Controller/Component');

class ValueWarninglistInertShell extends AppShell
{
    public $uses = array('User', 'ValueProfile');

    public function main()
    {
        $value = $this->args[0] ?? '';
        $user = $this->User->getAuthUser(1);
        $profile = $this->ValueProfile->forRelationCooccurrence($user,
            $value);

        $request = new CakeRequest('/values/viewRelationCooccurrence');
        $request->params['controller'] = 'values';
        $request->params['action'] = 'viewRelationCooccurrence';
        $request->params['named'] = array();
        $request->params['pass'] = array();
        $controller = new Controller($request, new CakeResponse());
        $controller->theme = 'Overmind';
        $controller->helpers = array('Html', 'Form', 'Session', 'Time',
            'Number', 'OrgImg', 'DistributionLevel', 'Image');
        $controller->components = array(
            'Acl' => array('className' => 'ACL'),
        );
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

        $now = $view->element('Values/View/value_relation_cooccurrence');
        $was = $view->element(
            'Values/View/value_relation_cooccurrence_head'
        );
        file_put_contents('/tmp/b5-head.html', $was);
        file_put_contents('/tmp/b5-now.html', $now);
        $this->out($value);
        $this->out(sprintf('  HEAD %d bytes · now %d bytes',
            strlen($was), strlen($now)));
        if ($was === $now) {
            $this->out('  IDENTICAL');
            return;
        }
        $this->out('  DIFFERENT');
        $a = explode("\n", $was);
        $b = explode("\n", $now);
        $shown = 0;
        for ($i = 0; $i < max(count($a), count($b)); $i++) {
            $left = $a[$i] ?? '<eof>';
            $right = $b[$i] ?? '<eof>';
            if ($left === $right) {
                continue;
            }
            $this->out('  - ' . substr($left, 0, 150));
            $this->out('  + ' . substr($right, 0, 150));
            if (++$shown >= 15) {
                $this->out('  ...');
                return;
            }
        }
    }
}
