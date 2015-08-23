<?php
    $vs_plugin_dir = $this->getVar("plugin_dir");
    $vt_registre = $this->getVar("registre");
    $vn_obj_nb = $this->getVar("objects_nb");

    MetaTagManager::addLink('stylesheet', __CA_URL_ROOT__."/app/plugins/museesDeFrance/assets/css/museesDeFrance.css",'text/css');
    MetaTagManager::addLink('stylesheet', __CA_URL_ROOT__."/app/plugins/museesDeFrance/assets/css/themes/blue/style.css",'text/css');
    AssetLoadManager::register('tableList');


?>

<h1>Inventaire des biens affectés</h1>

<?php switch ($vn_obj_nb) { ?>
<?php case "0": ?>
<?php break; ?>
<?php case "1": ?>
        <div class="searchNav">Le registre comporte 1 objet, c'est un bon début.</div>
<?php break; ?>
        <?php default: ?>
<div class="searchNav">Le registre comporte <?php print $vn_obj_nb; ?> objets.</div>
<?php } ?>
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
<a href="#" id="showRefine" onclick="jQuery('#searchRefineBox').slideDown();jQuery('#showRefine').hide();" data-original-title="Affiner les résultats" style="display: block;">
    <?php print caNavIcon($this->request,__CA_NAV_BUTTON_FILTER__); ?>
</a>
<?php
    $vs_registre_class = $vt_registre->objectmodel;
    $vt_object = new $vs_registre_class;

?>
    <div style="clear: both;"><!-- empty --></div>
<?php
$i = 0;
foreach($vt_registre->getObjects() as $vt_object) {
    if($vt_object->file) {
        print "<div style='float:left;width:30%;border:1px solid #dddddd;background: #eeeeee;padding:10px;margin:10px;'>";

        print "<img src=\"".__CA_URL_ROOT__."/app/plugins/museesDeFrance/assets/photos/".$vt_object->file."\" style=\"width:100%;\">";
        print "<p><a href='".caNavUrl($this->request,"editor/objects","ObjectEditor","Edit",array("object_id"=>$vt_object->get("ca_id")))."'>".$vt_object->numinv_display."</a></p>";
        print "<p>".$vt_object->designation_display."</p>";
        print "</div>";
    }

    $i++;
}
?>
<?php if (!$vn_obj_nb) : ?><div class="searchNav">Le registre est vide.</div><?php endif; ?>

</div>

<?php print caFormControlBox(
    null,
    null,
    caNavButton($this->request,__CA_NAV_BUTTON_RIGHT_ARROW__,"Afficher le registre","", $this->request->getModulePath(), $this->request->getController(), 'Index')
); ?>

<div class="editorBottomPadding"><!-- empty --></div>
<div class="editorBottomPadding"><!-- empty --></div>
