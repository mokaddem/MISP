<?php
    $modelForForm = 'Galaxy';
    $origGalaxy = isset($origGalaxy) ? $origGalaxy : array();
    $origGalaxyHtmlPreview = '';
    if (isset($origGalaxyMeta)) {
        foreach ($origGalaxyMeta as $key => $value) {
            if (is_array($value)) {
                $origGalaxyHtmlPreview .= sprintf('<div><b>%s: </b><div data-toggle="json" class="large-left-margin">%s</div></div>', h($key), json_encode($value));
            } else {
                $origGalaxyHtmlPreview .= sprintf('<div><b>%s: </b>%s</div>', h($key), h($value));
            }
        }
    }
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
                    'type' => 'text',
                    'div' => array('style' => 'position: relative;')
                ),
                !isset($origGalaxyMeta) ? '' : sprintf('<div id="fork_galaxy_preview" class="panel-container fork-galaxy-preview"><h5>%s</h5>%s</div>',
                    __('Forked Galaxy data'),
                    $origGalaxyHtmlPreview
                ),
                array(
                    'field' => 'forkid',
                    'type' => 'hidden',
                    'default' => isset($forkId) ? $forkId : ''
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
    var origGalaxy = <?php echo json_encode($origGalaxy); ?>;
    $('#GalaxyDistribution').change(function() {
        checkSharingGroup('Galaxy');
    });
    $('#GalaxyIcon').on('input', function() {
        updateFAIcon(this);
    });

    $(document).ready(function() {
        checkSharingGroup('Galaxy');
        $('[data-toggle=\"json\"]').each(function() {
        $(this).attr('data-toggle', '')
            .html(syntaxHighlightJson($(this).text().trim()));
        });
        $('#GalaxyIcon').parent().append('<i class="position-absolute" style="top: 34px; right: 20px;"></i>');
        updateFAIcon(document.getElementById('GalaxyIcon'));
    });

    function updateFAIcon(elem) {
        var val = $(elem).val();
        $(elem).parent().find('i').attr('class', 'position-absolute fa-' + val + ' ' + getFAClass(val));
    }
</script>
<?php echo $this->Js->writeBuffer(); // Write cached scripts
