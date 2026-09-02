<?php

/**
 * Scratch shell: render a relations panel twice over one profile — once
 * through the current template, once through HEAD's — and diff the
 * bytes. `24b-relationships.md` §7 asks that a value with no listed
 * neighbours render byte-identically to what it did before B5, and a
 * grep for absent markup is not that claim.
 *
 * Needs `<element>_head.ctp` beside the real element, written with
 * `git show HEAD:…`. Both are deleted after the run.
 *
 *   cake ValueWarninglistInert <value> [cooccurrence|dated]
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

    private $panels = array(
        'cooccurrence' => array(
            'method' => 'forRelationCooccurrence',
            'element' => 'value_relation_cooccurrence',
        ),
        'dated' => array(
            'method' => 'forRelationDated',
            'element' => 'value_relation_dated',
        ),
    );

    public function main()
    {
        $value = $this->args[0] ?? '';
        $which = $this->args[1] ?? 'cooccurrence';
        $panel = $this->panels[$which];
        $user = $this->User->getAuthUser(1);
        $method = $panel['method'];
        $profile = $this->ValueProfile->$method($user, $value);

        $request = new CakeRequest('/values/viewRelation');
        $request->params['controller'] = 'values';
        $request->params['action'] = 'viewRelation';
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

        $now = $view->element('Values/View/' . $panel['element']);
        $was = $view->element('Values/View/' . $panel['element'] . '_head');
        file_put_contents('/tmp/b5-head.html', $was);
        file_put_contents('/tmp/b5-now.html', $now);
        $this->out($value . ' · ' . $which);
        $this->out(sprintf('  HEAD %d bytes · now %d bytes',
            strlen($was), strlen($now)));
        $this->out($was === $now ? '  IDENTICAL' : '  DIFFERENT');
    }
}
