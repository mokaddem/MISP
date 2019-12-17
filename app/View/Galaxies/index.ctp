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
                    'name' => __('Id'),
                    'sort' => 'id',
                    'class' => 'short',
                    'data_path' => 'Galaxy.id',
                ),
                array(
                    'name' => __('Icon'),
                    'element' => 'icon',
                    'class' => 'short',
                    'data_path' => 'Galaxy.icon',
                ),
                array(
                    'name' => __('Org'),
                    'class' => 'short',
                    // 'element' => 'org',
                    'data_path' => 'Galaxy.org_id',
                ),
                array(
                    'name' => __('Name'),
                    'sort' => 'name',
                    'class' => 'short',
                    'data_path' => 'Galaxy.name',
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
                )
            ),
            'title' => __('Galaxy index'),
            'actions' => array(
                array(
                    'url' => '/galaxies/view',
                    'url_params_data_paths' => array(
                        'Galaxy.id'
                    ),
                    'icon' => 'eye'
                ),
                array(
                    'url' => '/galaxies/add',
                    'url_named_params_data_paths' => array(
                        'forkId' => 'Galaxy.id'
                    ),
                    'icon' => 'code-branch'
                ),
                array(
                    'url' => '/galaxies/delete',
                    'postLink' => true,
                    'postLinkConfirm' => __('Are you sure you want to delete the Galaxy?'),
                    'icon' => 'trash'
                ),
            )
        )
    ));
    echo '</div>';
    echo $this->element('/genericElements/SideMenu/side_menu', array('menuList' => 'sync', 'menuItem' => 'list_communities'));
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
