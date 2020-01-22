<?php
    echo '<div class="index">';
    echo $this->element('/genericElements/IndexTable/index_table', array(
        'data' => array(
            'data' => $galaxyList,
            'top_bar' => array(
                'children' => array(
                    array(
                        'type' => 'simple',
                        'children' => array(
                            array(
                                'active' => $context === 'all',
                                'url' => $baseurl . '/galaxies/index/context:all',
                                'text' => __('All'),
                            ),
                            array(
                                'active' => $context === 'default',
                                'url' => $baseurl . '/galaxies/index/context:default',
                                'text' => __('Default'),
                            ),
                            array(
                                'active' => $context === 'org',
                                'url' => $baseurl . '/galaxies/index/context:org',
                                'text' => __('My Galaxies'),
                            ),
                            array(
                                'active' => $context === 'orgc',
                                'url' => $baseurl . '/galaxies/index/context:orgc',
                                'text' => __('My Created Galaxies'),
                            )
                        )
                    ),
                    array(
                        'type' => 'search',
                        'button' => __('Filter'),
                        'placeholder' => __('Enter value to search'),
                        'data' => '',
                        'searchKey' => 'value',
                        'value' => $searchall
                    )
                )
            ),
            'fields' => array(
                array(
                    'name' => __('Galaxy Id'),
                    'sort' => 'Galaxy.id',
                    'element' => 'links',
                    'class' => 'short',
                    'data_path' => 'Galaxy.id',
                    'url' => $baseurl . '/galaxies/view/%s'
                ),
                array(
                    'name' => __('Icon'),
                    'element' => 'icon',
                    'class' => 'short',
                    'data_path' => 'Galaxy.icon',
                ),
                array(
                    'name' => __('Owner Org'),
                    'class' => 'short',
                    'element' => 'org',
                    'data_path' => 'Org',
                    'fields' => array(
                        'allow_picture' => true,
                        'default_org' => 'MISP'
                    ),
                    'requirement' => $isSiteAdmin || (Configure::read('MISP.showorgalternate') && Configure::read('MISP.showorg'))
                ),
                array(
                    'name' => __('Creator Org'),
                    'class' => 'short',
                    'element' => 'org',
                    'data_path' => 'Orgc',
                    'fields' => array(
                        'allow_picture' => true,
                        'default_org' => 'MISP'
                    ),
                    'requirement' => (Configure::read('MISP.showorg') || $isAdmin) || (Configure::read('MISP.showorgalternate') && Configure::read('MISP.showorg'))
                ),
                array(
                    'name' => __('Default'),
                    'class' => 'short',
                    'data_path' => 'Galaxy.default',
                ),
                array(
                    'name' => __('Name'),
                    'sort' => 'name',
                    'class' => 'short',
                    'data_path' => 'Galaxy.name',
                    'element' => 'extended_by',
                    'fields' => array(
                        'extend_data_path' => 'Galaxy.extended_by',
                        'extend_link_path' => 'Galaxy.uuid',
                        'extend_link_title' => 'Galaxy.name'
                    )
                ),
                array(
                    'name' => __('version'),
                    'class' => 'short',
                    'data_path' => 'Galaxy.version',
                ),
                array(
                    'name' => __('Namespace'),
                    'class' => 'short',
                    'data_path' => 'Galaxy.namespace',
                ),
                array(
                    'name' => __('Description'),
                    'data_path' => 'Galaxy.description',
                ),
                array(
                    'name' => __('Distribution'),
                    'element' => 'distribution_levels',
                    'data_path' => 'Galaxy.distribution',
                )
            ),
            'title' => __('Galaxy index'),
            'actions' => array(
                array(
                    'url' => '/galaxies/view',
                    'url_params_data_paths' => array(
                        'Galaxy.id'
                    ),
                    'icon' => 'eye',
                    'dbclickAction' => true
                ),
                array(
                    'url' => '/galaxies/export',
                    'url_params_data_paths' => array(
                        'Galaxy.id'
                    ),
                    'icon' => 'download',
                ),
                array(
                    'url' => '/galaxies/add',
                    'url_named_params_data_paths' => array(
                        'forkId' => 'Galaxy.id'
                    ),
                    'icon' => 'code-branch'
                ),
                array(
                    'url' => '/galaxies/edit',
                    'url_params_data_paths' => array(
                        'Galaxy.id'
                    ),
                    'icon' => 'edit',
                    'complex_requirement' => array(
                        'function' => function($row, $options) {
                            return ($options['me']['org_id'] == $options['datapath']['org']);
                        },
                        'options' => array(
                            'me' => $me,
                            'datapath' => array(
                                'org' => 'Galaxy.org_id'
                            )
                        )
                    ),
                ),
                array(
                    'url' => '/galaxies/delete',
                    'url_params_data_paths' => array(
                        'Galaxy.id'
                    ),
                    'postLink' => true,
                    'postLinkConfirm' => __('Are you sure you want to delete the Galaxy?'),
                    'icon' => 'trash'
                ),
            )
        )
    ));
    echo '</div>';
    echo $this->element('/genericElements/SideMenu/side_menu', array('menuList' => 'galaxies', 'menuItem' => 'index'));
?>
<script type="text/javascript">
    var passedArgsArray = <?php echo $passedArgs; ?>;
    if (passedArgsArray['context'] === undefined) {
        passedArgsArray['context'] = 'pending';
    }
    $(document).ready(function() {
        $('#quickFilterButton').click(function() {
            runIndexQuickFilter('/context:' + passedArgsArray['context']);
        });
        $('#quickFilterField').on('keypress', function (e) {
            if(e.which === 13) {
                runIndexQuickFilter('/context:' + passedArgsArray['context']);
            }
        });
    });
</script>
