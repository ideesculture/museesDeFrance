<?php
    $vs_plugin_dir = $this->getVar("plugin_dir");
    $vt_registre = $this->getVar("registre");

    MetaTagManager::addLink('stylesheet', __CA_URL_ROOT__."/app/plugins/museesDeFrance/assets/css/themes/blue/style.css",'text/css');
    AssetLoadManager::register('tableList');


?>

<h1>Inventaire des biens affectés</h1>
<table id="registre_biens_affectes" class="tablesorter">
<?php
    $vs_registre_class = $vt_registre->objectmodel;
    $vt_object = new $vs_registre_class;

    print $vt_object->getHtmlTableHeaderRow();
?>
<tbody>
<?php

    foreach($vt_registre->getObjects() as $vt_object) {
        print $vt_object->getHtmlTableRow();
    }
?>
</tbody>
</table>
<div id="pager" class="pager">
<script type="text/javascript">
    jQuery(document).ready(function()
        {
            jQuery("#registre_biens_affectes").tablesorter().tablesorterPager({container: $("#pager")});
        }
    );
</script>