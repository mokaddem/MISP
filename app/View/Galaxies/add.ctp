<?php
    $modelForForm = 'Galaxy';
    echo $this->element('genericElements/Form/genericForm', array(
        'form' => $this->Form,
        'data' => array(
            'title' => $action == 'add' ? __('Add Galaxy') : __('Edit Galaxy'),
            'model' => $modelForForm,
            'fields' => array(
                array(
                    'field' => 'namespace',
                    'type' => 'text',
                    'stayInLine' => true
                ),
                array(
                    'field' => 'name',
                    'type' => 'text',
                    'stayInLine' => true
                ),
                array(
                    'field' => 'icon',
                    'type' => 'text'
                ),
                array(
                    'field' => 'fork_id',
                    'label' => 'Forked Galaxy',
                    'class' => 'large-left-margin',
                    'options' => $galaxyNames,
                    'empty' => array(-1 => '-- No fork --'),
                    'default' => isset($forkId) ? $forkId : -1
                ),
                '<div id="fork_galaxy_preview" class="panel-container large-left-margin" style="display: inline-block;"></div>',
                array(
                    'field' => 'description',
                    'type' => 'textarea'
                ),
                array(
                    'field' => 'distribution',
                    'options' => $distributionLevels,
                    'default' => isset($galaxy['Galaxy']['distribution']) ? $galaxy['Galaxy']['distribution'] : $initialDistribution,
                    'stayInLine' => 1
                ),
                array(
                    'field' => 'sharing_group_id',
                    'options' => $sharingGroups,
                    'label' => __("Sharing Group")
                ),
                array(
                    'field' => 'kill_chain_order',
                    'type' => 'textarea',
                    'rows' => 1
                ),
                array(
                    'field' => 'values',
                    'label' => __("Cluster values"),
                    'type' => 'textarea',
                ),
            )
        )
    ));
    echo $this->element('/genericElements/SideMenu/side_menu', array('menuList' => 'galaxies', 'menuItem' => $this->action === 'add' ? 'add' : 'edit'));
?>

<script type="text/javascript">
    var galaxies = <?php echo json_encode($galaxies); ?>;
    $('#GalaxyDistribution').change(function() {
        checkSharingGroup('Galaxy');
    });

    $('#GalaxyForkId').change(function() {
        checkFork();
        previewGalaxyBasedOnUuids();
    });

    $(document).ready(function() {
        checkSharingGroup('Galaxy');
        previewGalaxyBasedOnUuids();
        checkFork();
    });

    function checkFork() {
        if ($('#GalaxyForkId').val() != -1) {
            $('#fork_galaxy_preview').show();
        } else {
            $('#fork_galaxy_preview').hide();
        }
    }


    function previewGalaxyBasedOnUuids() {
        var currentValue = $("#GalaxyForkId").val();
        var galaxyForkContainer = $("#fork_galaxy_preview");
        if (currentValue == -1) {
            galaxyForkContainer.hide();
        } else {
            var selectedGalaxy = galaxies[currentValue]['Galaxy'];
            if (selectedGalaxy === undefined) {
                galaxyForkContainer.hide();
            } else {
                galaxyForkContainer.empty();
                var toAdd = [];
                Object.keys(selectedGalaxy).forEach(function(k) {
                    var value = selectedGalaxy[k];
                    galaxyForkContainer.append(
                        $('<div/>')
                            .append($('<strong/>').text(k + ': '))
                            .append($('<span/>').text(value))
                    )                    
                });
            }
        }
    }
</script>
<?php echo $this->Js->writeBuffer(); // Write cached scripts
