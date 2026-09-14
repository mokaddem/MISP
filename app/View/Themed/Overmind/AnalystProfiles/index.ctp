<?php
/**
 * Every profile this reader may see, with the one in force named
 * first and unmistakably.
 *
 * The commonest confusion this feature can create is an analyst
 * editing a profile that is not weighting their pages — they forked,
 * forgot, and their organisation's still wins, or their own is
 * disabled. So the rail is the resolution order and every row carries
 * its own standing, which is computed rather than drawn.
 *
 * @var array $profiles
 * @var array|null $in_force
 * @var bool $scoring_off
 * @var array $loader_errors
 * @var array $comparison_set
 * @var int $comparison_limit
 */
App::uses('ValueUrlTool', 'Tools/ValueProfile');

echo $this->element('genericElements/assetLoader', array(
    'css' => array('value-palette', 'analyst-profile'),
    'js' => array('analyst-profile'),
));

$default = null;
$scopes = array(
    3 => array('label' => __('Yours'), 'count' => 0),
    2 => array('label' => __('Your organisation'), 'count' => 0),
    0 => array('label' => __('Instance default'), 'count' => 0),
);
$others = 0;
foreach ($profiles as $profile) {
    if (isset($scopes[$profile['scope_rank']])) {
        $scopes[$profile['scope_rank']]['count']++;
    } else {
        $others++;
    }
    if (!empty($profile['default'])) {
        $default = $profile;
    }
}

$canForkForOrg = !empty($me['Role']['perm_admin'])
    || !empty($me['Role']['perm_site_admin']);

$create = array();
if ($default !== null) {
    $create[] = array(
        'type' => 'action',
        'label' => __('Fork the instance default'),
        'icon' => 'code-branch',
        'url' => array('action' => 'fork', $default['id']),
        'title' => sprintf(
            __('A copy of %s that you own and can edit.'),
            $default['name']
        ),
    );
}
$create[] = array(
    'type' => 'navigate',
    'label' => __('Import JSON'),
    'icon' => 'file-import',
    'url' => array('action' => 'import'),
    'title' => __("Somebody else's, exported from their instance."),
);

$this->set('headerTitle', __('Analyst Profiles'));
$this->set('headerBreadcrumb', __('Analyst Profiles'));
$this->set('headerCount', count($profiles));
$this->set('headerDescription', __('The judgements the value assessment'
    . ' is computed from. Exactly one is in force for you.'));
$this->set('headerActions', array(array(
    'type' => 'dropdown',
    'label' => __('New profile'),
    'icon' => 'plus',
    'class' => 'btn btn-outline-primary',
    'children' => $create,
)));
?>

<div class="ap-page">
    <div class="container-fluid">
        <?= $this->element('AnalystProfiles/loader_errors', array(
            'loader_errors' => $loader_errors,
        )) ?>

        <div class="wb">
            <div class="wb-panehead wb-panehead-left">
                <span><?= h(__('The profiles')) ?></span>
                <em><?= h(__('resolution stops at the first enabled one')) ?></em>
            </div>
            <div class="wb-panehead wb-panehead-right">
                <span><?= h(__('The bench')) ?></span>
                <em><?= h(__('travels with you into the editor')) ?></em>
            </div>

            <nav class="wb-rail">
                <div class="wb-rail-title"><?= h(__('Resolution order')) ?></div>
                <?php $step = 1; ?>
                <?php foreach ($scopes as $scope): ?>
                    <button type="button" class="wb-rail-item" disabled>
                        <span><?= h($step++) ?>&nbsp;&nbsp;<?= h($scope['label']) ?></span>
                        <span class="c"><?= h($scope['count']) ?></span>
                    </button>
                <?php endforeach; ?>
                <?php if ($others > 0): ?>
                    <button type="button" class="wb-rail-item" disabled>
                        <span><?= h(__('Other owners')) ?></span>
                        <span class="c"><?= h($others) ?></span>
                    </button>
                <?php endif; ?>
                <p class="wb-rail-note">
                    <?= $scoring_off
                        ? h(__('Nothing is in force, so no value on this'
                            . ' instance is scored for you at all.'))
                        : h(__('Resolution walks these three in order and'
                            . ' stops at the first enabled profile, which'
                            . ' is why a profile you have just forked is'
                            . ' not yet the one weighting your pages.')) ?>
                </p>
            </nav>

            <div class="wb-body">
                <table class="wb-tbl">
                    <thead>
                        <tr>
                            <th style="width:31%"><?= h(__('Profile')) ?></th>
                            <th><?= h(__('Standing')) ?></th>
                            <th style="width:8%"><?= h(__('Signals')) ?></th>
                            <th style="width:11%"><?= h(__('Counters')) ?></th>
                            <th style="width:13%"><?= h(__('Last change')) ?></th>
                            <th style="width:15%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profiles as $profile): ?>
                            <tr class="<?= $profile['in_force'] ? 'is-hot' : '' ?>">
                                <td>
                                    <div class="fw-semibold"><?= h($profile['name']) ?></div>
                                    <div class="wb-sub">
                                        <?= h($profile['owner']) ?> &middot;
                                        <span class="wb-id"><?= h(substr(
                                            $profile['uuid'], 0, 8)) ?></span>
                                    </div>
                                    <?php if (!empty($profile['description'])): ?>
                                        <?php
                                        /*
                                         * The first sentence only. A
                                         * fork carries its origin line
                                         * and then whatever the source
                                         * said, which on the shipped
                                         * default is a page of it.
                                         */
                                        $line = strtok($profile['description'],
                                            "\n");
                                        if (strlen($line) > 150) {
                                            $line = substr($line, 0, 147) . '…';
                                        }
                                        ?>
                                        <div class="wb-sub mt-1"><?= h($line) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['unparseable'])): ?>
                                        <div class="mt-1">
                                            <span class="pill t-missing"><?= h(
                                                __('document will not parse')
                                            ) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $this->element('AnalystProfiles/standing',
                                        array('profile' => $profile)) ?>
                                </td>
                                <td class="num">
                                    <?= h($profile['signals']['enabled']) ?>&nbsp;/&nbsp;<?= h(
                                        $profile['signals']['configured']) ?>
                                </td>
                                <td class="wb-sub num">
                                    <?= h(sprintf(__('rev %s'), $profile['revision'])) ?><br>
                                    <?= h(sprintf(__('v %s'), $profile['version'])) ?>
                                </td>
                                <td class="wb-sub num">
                                    <?= str_replace(' ', '<br>',
                                        h($profile['modified'])) ?>
                                </td>
                                <td>
                                    <?php if ($profile['editable']): ?>
                                        <a class="btn btn-sm btn-primary py-0 px-2"
                                           href="<?= h($this->Html->url(array(
                                               'action' => 'edit',
                                               $profile['id']))) ?>"><?= h(__('Edit')) ?></a>
                                    <?php endif; ?>
                                    <a class="btn btn-sm btn-outline-secondary py-0 px-2"
                                       href="<?= h($this->Html->url(array(
                                           'action' => 'view',
                                           $profile['id']))) ?>"><?= h(__('View')) ?></a>
                                    <a class="btn btn-sm btn-outline-secondary py-0 px-2"
                                       href="<?= h($this->Html->url(array(
                                           'action' => 'simulate',
                                           $profile['id']))) ?>"><?= h(__('Simulate')) ?></a>
                                    <a class="btn btn-sm btn-outline-secondary py-0 px-2"
                                       href="<?= h($this->Html->url(array(
                                           'action' => 'export',
                                           $profile['id']))) ?>"><?= h(__('Export')) ?></a>
                                    <?php if ($profile['editable']
                                        && !$profile['enabled']): ?>
                                        <?= $this->Form->postLink(
                                            __('Enable'),
                                            array('action' => 'enable',
                                                $profile['id']),
                                            array('class' => 'btn btn-sm'
                                                . ' btn-outline-primary py-0 px-2'),
                                            $in_force === null
                                                ? false
                                                : sprintf(
                                                    __('You may hold one'
                                                        . ' enabled profile.'
                                                        . ' Enabling this one'
                                                        . ' disables %s — it'
                                                        . ' is not deleted.'),
                                                    $in_force['name']
                                                )
                                        ) ?>
                                    <?php elseif ($profile['editable']
                                        && $profile['enabled']
                                        && empty($profile['default'])): ?>
                                        <?= $this->Form->postLink(
                                            __('Disable'),
                                            array('action' => 'disable',
                                                $profile['id']),
                                            array('class' => 'btn btn-sm'
                                                . ' btn-outline-secondary py-0 px-2')
                                        ) ?>
                                    <?php endif; ?>
                                    <?php if (!$profile['in_force']
                                        || !empty($profile['default'])): ?>
                                        <?= $this->Form->postLink(
                                            __('Fork to me'),
                                            array('action' => 'fork',
                                                $profile['id']),
                                            array('class' => 'btn btn-sm'
                                                . ' btn-primary py-0 px-2')
                                        ) ?>
                                        <?php if ($canForkForOrg): ?>
                                            <?= $this->Form->postLink(
                                                __('Fork to my organisation'),
                                                array('action' => 'fork',
                                                    $profile['id'],
                                                    '?' => array('for_org' => 1)),
                                                array('class' => 'btn btn-sm'
                                                    . ' btn-outline-primary py-0 px-2')
                                            ) ?>
                                        <?php endif; ?>
                                        <?php if (!$profile['editable']): ?>
                                            <div class="wb-sub mt-1">
                                                <?= h(__('No Edit: this one is'
                                                    . ' not yours. Forking is'
                                                    . ' how you change it.')) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($profile['editable']
                                        && empty($profile['default'])): ?>
                                        <?= $this->Form->postLink(
                                            __('Delete'),
                                            array('action' => 'delete',
                                                $profile['id']),
                                            array('class' => 'btn btn-sm'
                                                . ' btn-outline-danger py-0 px-2'),
                                            sprintf(
                                                __('Delete %s? A profile is a'
                                                    . ' tuned judgement and'
                                                    . ' this cannot be'
                                                    . ' undone. Disabling it'
                                                    . ' keeps it.'),
                                                $profile['name']
                                            )
                                        ) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <aside class="wb-bench">
                <div class="wb-bench-inner">
                    <?php if ($scoring_off): ?>
                        <div class="wb-empty">
                            <div class="fw-semibold"><?= h(__('Nothing in force')) ?></div>
                            <p class="mb-1 mt-1">
                                <?= h(__('No profile applies to you, so no value'
                                    . ' on this instance is scored. The value'
                                    . ' pages still show their evidence; none'
                                    . ' of them shows a quality.')) ?>
                            </p>
                            <?php if ($default !== null): ?>
                                <?= $this->Form->postLink(
                                    __('Enable the instance default'),
                                    array('action' => 'enable', $default['id']),
                                    array('class' => 'btn btn-sm'
                                        . ' btn-outline-primary py-0 px-2 mt-1')
                                ) ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="bench-live"><?= h(__('scoring is on')) ?></div>
                        <p class="mt-2 mb-2" style="font-size:.8125rem;line-height:1.5">
                            <span class="fw-semibold"><?= h($in_force['name']) ?></span>
                            <?= h(__('is what every value page you open is'
                                . ' scored by, until you enable another.')) ?>
                        </p>
                    <?php endif; ?>

                    <div class="bench-sec">
                        <span><?= h(__('Values you pinned')) ?></span>
                        <span class="num"><?= h(sprintf(
                            __('%1$s of %2$s'),
                            count($comparison_set),
                            $comparison_limit
                        )) ?></span>
                    </div>
                    <p class="wb-sub mt-1 mb-2">
                        <?= h(__('A pinned value is one you want every profile'
                            . ' change judged against, instead of judging it'
                            . ' on whichever value you happened to arrive'
                            . ' from.')) ?>
                    </p>
                    <?php if (empty($comparison_set)): ?>
                        <div class="wb-empty">
                            <div class="fw-semibold"><?= h(__('Nothing pinned yet')) ?></div>
                            <p class="mb-0 mt-1">
                                <?= h(__('Pin a value and its two columns appear'
                                    . ' here. Open a profile, put a value on'
                                    . ' the bench and press Pin — arriving'
                                    . ' from a value page benches that value'
                                    . ' for the visit, and does not pin'
                                    . ' it.')) ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <table class="wb-tbl">
                            <tbody>
                                <?php foreach ($comparison_set as $value): ?>
                                    <tr>
                                        <td class="wb-id"><?= h($value) ?></td>
                                        <td class="r wb-sub">
                                            <?= $this->Form->postLink(
                                                __('unpin'),
                                                array('action' => 'unpin',
                                                    ValueUrlTool::encode($value)),
                                                array('class' => 'wb-sub')
                                            ) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="wb-sub mt-2 mb-0">
                            <?= h(__('They follow you into the editor, where each'
                                . ' gets a quality under the profile you are'
                                . ' editing, and into the simulator, where each'
                                . ' gets a second column.')) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>
</div>
