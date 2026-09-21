<?php
/**
 * A profile, read-only.
 *
 * The same two panes as the editor with the inputs replaced by what
 * they hold. Not disabled inputs: a disabled input still looks like
 * something you could have changed, and this page is for a profile
 * that is somebody else's.
 *
 * With `?value=` each weight is shown beside the contribution it
 * produced on that value. Without one the column stays empty rather
 * than inventing a number.
 *
 * @var array $profile
 * @var bool $editable
 * @var array $bench
 */
App::uses('ValueUrlTool', 'Tools/ValueProfile');

echo $this->element('genericElements/assetLoader', array(
    'css' => array('value-palette', 'analyst-profile'),
    'js' => array('analyst-profile'),
));

$focus = $bench['focus'];
$query = $focus === null
    ? array()
    : array('value' => ValueUrlTool::encode($focus));

$actions = array(
    array(
        'type' => 'navigate',
        'label' => __('Simulate'),
        'icon' => 'flask',
        'url' => $this->Html->url(array(
            'action' => 'simulate', $profile['id'], '?' => $query)),
    ),
    array(
        'type' => 'navigate',
        'label' => __('Export'),
        'icon' => 'file-export',
        'url' => $this->Html->url(array('action' => 'export', $profile['id'])),
    ),
);
if (!$editable) {
    /*
     * The one way in. There is no blank form: an empty `parameters`
     * names no signal and scores nothing, so a new profile is a copy
     * of one that already works.
     */
    $actions[] = array(
        'type' => 'action',
        'label' => __('Fork to me'),
        'icon' => 'code-branch',
        'class' => 'btn btn-primary',
        'url' => array('action' => 'fork', $profile['id']),
    );
    if (!empty($me['Role']['perm_admin'])
        || !empty($me['Role']['perm_site_admin'])
    ) {
        $actions[] = array(
            'type' => 'action',
            'label' => __('Fork to my organisation'),
            'icon' => 'users',
            'url' => array('action' => 'fork', $profile['id'],
                '?' => array('for_org' => 1)),
        );
    }
} else {
    $actions[] = array(
        'type' => 'navigate',
        'label' => __('Edit'),
        'icon' => 'pen',
        'class' => 'btn btn-primary',
        'url' => $this->Html->url(array(
            'action' => 'edit', $profile['id'], '?' => $query)),
    );
}

$this->set('headerTitle', $profile['name']);
$this->set('headerBreadcrumb', array(
    array('label' => __('Analyst Profiles'),
        'url' => array('action' => 'index')),
    $profile['name'],
));
$this->set('headerCountText', sprintf(__('rev %s'), $profile['revision']));
$this->set('headerCount', $profile['revision']);
$this->set('headerDescription', $profile['description']);
$this->set('headerActions', $actions);
$this->set('headerActionGroups', array('navigate' => array('mode' => 'none')));

echo $this->element('AnalystProfiles/workbench', array('editable' => false));
