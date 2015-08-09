<?php
    $vs_plugin_dir = $this->getVar("plugin_dir");
    $vt_registre = $this->getVar("registre");

    MetaTagManager::addLink('stylesheet', __CA_URL_ROOT__."/app/plugins/museesDeFrance/assets/css/museesDeFrance.css",'text/css');
    MetaTagManager::addLink('stylesheet', __CA_URL_ROOT__."/app/plugins/museesDeFrance/assets/css/themes/blue/style.css",'text/css');
    AssetLoadManager::register('tableList');


?>

<h1>Inventaire des biens affectés</h1>
<div class="control-box rounded">
    <div class="control-box-left-content">
        <a class='form-button'>
            <span class='form-button'>
                <img src='/themes/default/graphics/buttons/glyphicons_036_file.png' border='0' class='form-button-left' style='padding-right: 10px' align='middle'/>
                Importer/mettre à jour un objet
            </span>
        </a>
        <a class='form-button'>
            <span class='form-button'>
                <img src='/themes/default/graphics/buttons/glyphicons_319_sort.png' border='0' class='form-button-left' style='padding-right: 10px' align='middle' />
                Importer/mettre à jour un ensemble
            </span>
        </a>
    </div>
</div>
<div class="searchNav">Le registre comporte X objets validés.</div>
<div class="divide"><!-- empty --></div>
<div style="clear: both;"><!-- empty --></div>
<div id="searchRefineBox" style="display: none;"><div class="bg">
        <div id="searchRefineContent"><div class="startBrowsingBy">Filtrer les résultats</div>
            <span><input type="checkbox" name="showDrafts"/> Afficher les brouillons</span>

            <span>Filtrer par année <SELECT name="year" size="1">
                    <OPTION>2013
                    <OPTION>2014
                    <OPTION>2015
                </SELECT></span>

            <span>Par numéro de dépôt <input type="text" size="8"/></span>

            <span>Par désignation <input type="text" size="22"/></span>

        </div>
        <a href="#" id="hideRefine" onclick="jQuery('#searchRefineBox').slideUp();jQuery('#showRefine').show();"><img src="/themes/default/graphics/buttons/glyphicons_191_circle_minus.png" alt="glyphicons_191_circle_minus" border="0"></a>
        <div style="clear:both;"></div>
    </div><!-- end bg --></div>
<div style="clear: both;"><!-- empty --></div>
<div class="sectionBox">
<a href="#" id="showRefine" onclick="jQuery('#searchRefineBox').slideDown();jQuery('#showRefine').hide();" data-original-title="Affiner les résultats" style="display: block;"><img src="/themes/default/graphics/buttons/glyphicons_119_table.png" alt="glyphicons_119_table" border="0"></a>
<table id="registre_biens_affectes" class="tablesorter">
<?php
    $vs_registre_class = $vt_registre->objectmodel;
    $vt_object = new $vs_registre_class;

    print $vt_object->getHtmlTableHeaderRow();
?>
<tbody>
<?php
$i = 0;
foreach($vt_registre->getObjects() as $vt_object) {
    print ($i % 2 == 0 ? "<tr>" : "<tr class='odd'>" );
    print $vt_object->getHtmlTableRowContent();
    print "</tr>";
    $i++;
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
</div>
</div>

<div class="control-box rounded">
    <div class="control-box-left-content">
        <a class='form-button'>
            <span class='form-button'>
                <img src='/themes/default/graphics/buttons/glyphicons_359_file_export.png' border='0' class='form-button-left'
                     style='padding-right: 10px' align='middle'/>
                Générer le PDF
            </span>
        </a>
    </div>
    <div class="control-box-right-content">
        <a class='form-button'>
            <span class='form-button'>
                <img src='/themes/default/graphics/buttons/glyphicons_138_picture.png' border='0' class='form-button-left'
                     style='padding-right: 10px' align='middle'/>
                Afficher les photos
            </span>
        </a>
    </div>
    <div class="control-box-middle-content">
    </div>
</div>
