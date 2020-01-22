<?php
    echo $this->element('/genericElements/SideMenu/side_menu', array('menuList' => 'galaxies', 'menuItem' => 'view'));
?>

<?php
    $table_data = array();
    $table_data[] = array('key' => __('Galaxy ID'), 'value' => $galaxy['Galaxy']['id']);
    $table_data[] = array(
        'key' => __('Creator org'),
        'html' => $galaxy['Galaxy']['orgc_id'] == 0 ? 
            '<img src="' . $baseurl . '/img/orgs/MISP.png" width="24" height="24" style="padding-bottom:3px;" title="' . __('Default Galaxy from MISP Project') . '" />' :
            sprintf(
                '<a href="%s/organisations/view/%s">%s</a>',
                $baseurl,
                h($galaxy['Galaxy']['orgc_id']),
                h($galaxy['Galaxy']['orgc_id'])
            )
    );
    $table_data[] = array(
        'key' => __('Name'),
        'html' => sprintf(
            '%s %s',
            h($galaxy['Galaxy']['name']),
            $galaxy['Galaxy']['default'] ? '<img src="' . $baseurl . '/img/orgs/MISP.png" width="24" height="24" style="padding-bottom:3px;" title="' . __('Default Galaxy from MISP Project') . '" />' : ''
        )
    );
    $table_data[] = array('key' => __('Namespace'), 'value' => $galaxy['Galaxy']['namespace']);
    $table_data[] = array('key' => __('UUID'), 'value' => $galaxy['Galaxy']['uuid']);
    $table_data[] = array('key' => __('Description'), 'value' => $galaxy['Galaxy']['description']);
    $table_data[] = array('key' => __('Version'), 'value' => $galaxy['Galaxy']['version']);
    $table_data[] = array('key' => __('Distribution'), 'value' => $galaxy['Galaxy']['distribution']);
    $extendByLinks = array();
    foreach($galaxy['Galaxy']['extended_by'] as $extendGalaxy) {
        $element = $this->element('genericElements/IndexTable/Fields/links', array(
            'url' => '/galaxies/view/',
            'row' => $extendGalaxy,
            'field' => array(
                'data_path' => 'Galaxy.uuid',
                'title' => sprintf('%s > %s', $extendGalaxy['Galaxy']['namespace'], $extendGalaxy['Galaxy']['name'])
            ),
        ));
        $extendByLinks[] = sprintf('<li>%s</li>', $element);
    }
    $table_data[] = array(
        'key' => __('Extended By'),
        'html' => sprintf('<ul>%s</ul>', implode('', $extendByLinks))
    );
    $kco = '';
    if (isset($galaxy['Galaxy']['kill_chain_order'])) {
        $kco = '<strong>' . __('Kill chain order') . '</strong> <span class="useCursorPointer fa fa-expand" onclick="$(\'#killChainOrder\').toggle(\'blind\')"></span>';
        $kco .= '<div id="killChainOrder" class="hidden" style="border: 1px solid #000; border-radius: 5px; padding: 3px; background: #f4f4f4; margin-left: 20px;">' . json_encode($galaxy['Galaxy']['kill_chain_order']) . '</div>';
    }
?>

<div class='view'>
    <div class="row-fluid">
        <div class="span8">
            <h2>
                <span class="<?php echo $this->FontAwesome->findNamespace($galaxy['Galaxy']['icon']); ?> fa-<?php echo h($galaxy['Galaxy']['icon']); ?>"></span>&nbsp;
                <?php echo h($galaxy['Galaxy']['name']); ?> galaxy
            </h2>
            <?php if (!empty($galaxy['Galaxy']['extended_from'])): ?>
                <h5 style="padding-left: 10px;">
                    <?php 
                        echo $this->element('genericElements/IndexTable/Fields/extended_by', array(
                            'row' => $galaxy,
                            'field' => array(
                                'parent' => '',
                                'url' => $baseurl . '/galaxies/view/%s',
                                'data_path' => 'Galaxy.extended_from.Galaxy.uuid',
                                'title' => sprintf('%s > %s', $galaxy['Galaxy']['extended_from']['Galaxy']['namespace'], $galaxy['Galaxy']['extended_from']['Galaxy']['name']),
                                'fields' => array(
                                    'extend_data_path' => 'Galaxy.extended_from',
                                    'extend_link_path' => 'Galaxy.uuid',
                                )
                            )
                        ));
                    ?>
                </h5>
            <?php endif; ?>
            <?php echo $this->element('genericElements/viewMetaTable', array('table_data' => $table_data)); ?>
            <?php echo $kco; ?>
        </div>
    </div>
    <div id="clusters_div"></div>
</div>

<script type="text/javascript">
$(document).ready(function () {
    <?php
        $uri = "/galaxy_clusters/index/" . $galaxy['Galaxy']['id'];
        if (isset($passedArgsArray)) {
            $uri .= '/searchall:' . $passedArgsArray['all'];
        }
    ?>
    $.get("<?php echo $uri;?>", function(data) {
        $("#clusters_div").html(data);
    });

    var $kco = $('#killChainOrder');
    if ($kco.length > 0) {
        var j = syntaxHighlightJson($kco.text(), 8)
    }
    $kco.html(j);
});
</script>
