<?php
/**
 * The workbench: seven sections, one open, and the value under
 * assessment beside them.
 *
 * @var array $profile
 * @var bool $editable
 * @var array $bench
 */
App::uses('ValueUrlTool', 'Tools');

echo $this->element('genericElements/assetLoader', array(
    'css' => array('value-palette', 'analyst-profile'),
    'js' => array('analyst-profile'),
));

$focus = $bench['focus'];
$query = $focus === null
    ? array()
    : array('value' => ValueUrlTool::encode($focus));

$this->set('headerTitle', $profile['name']);
$this->set('headerBreadcrumb', __('Analyst Profiles') . ' > ' . $profile['name']);
$this->set('headerCountText', sprintf(__('rev %s'), $profile['revision']));
$this->set('headerCount', $profile['revision']);
$this->set('headerDescription', __('Seven sections, one open at a time.'
    . ' Nothing is normalised, so the contribution column on the right'
    . ' adds up to the quality exactly.'));
$this->set('headerActions', array(
    array(
        'type' => 'navigate',
        'label' => __('Expand the bench'),
        'icon' => 'up-right-and-down-left-from-center',
        'url' => $this->Html->url(array(
            'action' => 'simulate', $profile['id'], '?' => $query)),
    ),
    array(
        'type' => 'navigate',
        'label' => __('Export'),
        'icon' => 'file-export',
        'url' => $this->Html->url(array('action' => 'export', $profile['id'])),
    ),
    array(
        'type' => 'modal',
        'label' => __('Save'),
        'icon' => 'save',
        'url' => '#',
        'onClick' => 'analystProfileSave',
    ),
));
$this->set('headerActionGroups', array('navigate' => array('mode' => 'none')));

echo $this->element('AnalystProfiles/workbench');
