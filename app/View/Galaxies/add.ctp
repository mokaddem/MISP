<?php
    $modelForForm = 'Galaxy';
    echo $this->element('genericElements/Form/genericForm', array(
        'form' => $this->Form,
        'data' => array(
            'title' => $action === 'add' ? __('Add Galaxy') : __('Edit Galaxy'),
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
                    'field' => 'extends_existing',
                    'type' => 'checkbox',
                    'default' => false,
                ),
                array(
                    'field' => 'extends_id',
                    'class' => 'large-left-margin',
                    'options' => $galaxyNames
                ),
                '<div id="extended_galaxy_preview" class="panel-container large-left-margin" style="display: inline-block;"></div>',
                array(
                    'field' => 'kill_chain_order',
                    'type' => 'textarea',
                    'rows' => 1
                ),
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
            )
        )
    ));
    echo $this->element('/genericElements/SideMenu/side_menu', array('menuList' => 'event-collection', 'menuItem' => $this->action === 'add' ? 'add' : 'editEvent'));
?>

<script type="text/javascript">
    var galaxies = <?php echo json_encode($galaxies); ?>;
    $('#GalaxyDistribution').change(function() {
        checkSharingGroup('Galaxy');
    });

    $('#GalaxyExtendsExisting').change(function() {
        checkExtends();
    });

    $('#GalaxyExtendsId').change(function() {
        previewGalaxyBasedOnUuids();
    });

    $(document).ready(function() {
        checkSharingGroup('Galaxy');
        previewGalaxyBasedOnUuids();
        checkExtends();
    });

    function checkExtends() {
        if ($('#GalaxyExtendsExisting').prop('checked')) {
            $('#GalaxyExtendsId').show();
            $('#GalaxyExtendsId').closest("div").show();
            $('#extended_galaxy_preview').show();
        } else {
            $('#GalaxyExtendsId').hide();
            $('#GalaxyExtendsId').closest("div").hide();
            $('#extended_galaxy_preview').hide();
        }
    }


    function previewGalaxyBasedOnUuids() {
        var currentValue = $("#GalaxyExtendsId").val();
        var galaxyExtendContainer = $("#extended_galaxy_preview");
        if (currentValue == '') {
            galaxyExtendContainer.hide();
        } else {
            var selectedGalaxy = galaxies[currentValue]['Galaxy'];
            if (selectedGalaxy === undefined) {
                galaxyExtendContainer.hide();
            } else {
                galaxyExtendContainer.empty();
                var toAdd = [];
                Object.keys(selectedGalaxy).forEach(function(k) {
                    var value = selectedGalaxy[k];
                    galaxyExtendContainer.append(
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
