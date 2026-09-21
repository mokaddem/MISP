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
/*
 * The subtitle is one line and a description need not be: a fork
 * carries its origin line and then everything the source said. Flatten
 * the newlines, keep the opening, and leave the rest to the sections
 * below, which are what the description was describing.
 *
 * `h()` because `headerSection.ctp` echoes this one raw — unlike
 * `headerTitle`, which it escapes and which has a separate
 * `headerTitleHtml` entry for callers with markup to pass.
 *
 * PCRE under `/u` rather than `mb_substr()`, as on the index: the
 * PRD's render harnesses run a CLI PHP without mbstring.
 */
$summary = trim(preg_replace('/\s+/u', ' ', (string)$profile['description']));
if (preg_match('/^(.{320})./u', $summary, $m)) {
    $summary = $m[1];
    $cut = strrpos($summary, ' ');
    if ($cut !== false && $cut > 160) {
        $summary = substr($summary, 0, $cut);
    }
    $summary = rtrim($summary, " ,;:") . '…';
}
$this->set('headerDescription', h($summary));
$this->set('headerActions', $actions);
$this->set('headerActionGroups', array('navigate' => array('mode' => 'none')));

echo $this->element('AnalystProfiles/workbench', array('editable' => false));
