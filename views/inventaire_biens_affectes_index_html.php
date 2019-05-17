<?php
    $vs_plugin_dir = $this->getVar("plugin_dir");
    $vt_registre = $this->getVar("registre");
    $vt_objects = $vt_registre->getObjects($vs_year, $num_start, $designation);
    $vn_obj_nb = count($vt_objects);
    $va_years = $vt_registre->getYears();

    $vs_year = $this->getVar("year");
    $vb_hide_drafts = $this->getVar("hide_drafts");

    $num_start = $this->getVar("num_start");
    $designation = $this->getVar("designation");

    $is_validator = $this->getVar("validator");

    MetaTagManager::addLink('stylesheet', __CA_URL_ROOT__."/app/plugins/museesDeFrance/assets/css/museesDeFrance.css",'text/css');
    MetaTagManager::addLink('stylesheet', __CA_URL_ROOT__."/app/plugins/museesDeFrance/assets/css/themes/blue/style.css",'text/css');
    AssetLoadManager::register('tableList');


?>

<h1>Inventaire des biens affectés</h1>
<?php switch ($vn_obj_nb) { ?>
<?php case "0": ?>
<?php break; ?>
<?php case "1": ?>
        <div class="searchNav">
            Le registre comporte 1 objet, c'est un bon début.</div>
<?php break; ?>
        <?php default: ?>
<div class="searchNav inventaireNav" id="searchNav" style="display:block;clear:both;position:relative !important;top:0 !important;">
        <form>
    <div id="pager" class="pager nav">
            <a href="#" class="prev button"/>&lsaquo; Previous</a>
            <input type="text" class="pagedisplay"/>
            <a href="#" class="next button"/>Next &rsaquo;</a>
    </div>
    <span class="parpage" style="float:left;">
        <select class="pagesize">
            <option selected="selected" value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
        objets par page
    </span>
    Le registre comporte <?php print $vn_obj_nb; ?> objets.
        </form>
</div>
<?php } ?>
<div class="divide"><!-- empty --></div>
<div style="clear: both;"><!-- empty --></div>
<div id="searchRefineBox" class="inventaireRefineBox" style="display: none;">
    <div class="bg">
        <div id="searchRefineContent">
            <div class="startBrowsingBy">Filtrer les résultats</div>

            <?php print caFormTag($this->request,"Index", "inventaire_filter",null,"post","multipart/form-data","_top", array("submitOnReturn"=>true)); ?>
                <span><input type="checkbox" name="hidedrafts" <?php if ($vb_hide_drafts) print "checked"; ?>/> Masquer les brouillons</span>

                <span>Filtrer par année <SELECT name="year" size="1">
                        <OPTION value="">-</OPTION>
                        <?php foreach($va_years as $year) {
                            print "<OPTION ".($year==$vs_year ? "SELECTED" :"").">".$year."</OPTION>";
                        } ?>
                    </SELECT></span>

                <span>Par début de numéro <input name="num_start" type="text" size="14" value="<?php print $num_start; ?>" /></span>

                <span>Par désignation <input name="designation" type="text" size="22" value="<?php print $designation; ?>" /></span>
            </form>
        </div>
        <a href="#" id="hideRefine" onclick="jQuery('#searchRefineBox').slideUp();jQuery('#showRefine').show();"><img src="<?php print __CA_URL_ROOT__; ?>/themes/default/graphics/buttons/glyphicons_191_circle_minus.png" alt="glyphicons_191_circle_minus" border="0"></a>
        <a href="#" id="hideRefine" onclick="jQuery('#inventaire_filter').submit();"><img src="<?php print __CA_URL_ROOT__; ?>/themes/default/graphics/buttons/glyphicons_193_circle_ok.png" alt="glyphicons_193_circle_ok.png" border="0"></a>
        <div style="clear:both;"></div>
    </div><!-- end bg -->
</div>
<div style="clear: both;"><!-- empty --></div>
<div class="sectionBox">
    <a href="#" id="showRefine" onclick="jQuery('#searchRefineBox').slideDown();jQuery('#showRefine').hide();" data-original-title="Affiner les résultats" style="display: block;">
        <?php print caNavIcon($this->request,__CA_NAV_BUTTON_FILTER__); ?>

    </a>
    <table id="registre_biens_affectes" class="listtable">
    <?php
        $vs_registre_class = $vt_registre->objectmodel;
        $vt_object = new $vs_registre_class;

        print $vt_object->getHtmlTableHeaderRow();
    ?>
    <tbody>
    <?php
    $i = 0;
    foreach($vt_registre->getObjects($vs_year, $num_start, $designation) as $vt_object) {
        // Ignore object if draft mode is off and object hasn't been validated
        if($vb_hide_drafts && $vt_object->get(validated) === "0") continue;

        print ($i % 2 == 0 ? "<tr>" : "<tr class='odd'>" );

        print "<td><a href='".caNavUrl($this->request,"editor/objects","ObjectEditor","Edit",array("object_id"=>$vt_object->get("ca_id")))."'>".$vt_object->numinv_display."</a>";
        if($vt_object->file) {
            print "<br/><img src=\"".__CA_URL_ROOT__."/app/plugins/museesDeFrance/assets/photos/".$vt_object->file."\" style=\"width:50px;\">";
        }
        print "</td>
            <td><a href='".caNavUrl($this->request,"*","*","Transfer",array("id"=>$vt_object->get("ca_id")))."'>".$vt_object->designation_display."</a></td>
            <td>".$vt_object->auteur_display."</td>
            <td>".$vt_object->date_inscription_display."</td>
            <td>";
        if ((!$vt_object->validated) && ($is_validator)) {
            print "<a href='".caNavUrl($this->request,"museesDeFrance","InventaireBiensAffectes","Validate",array("object_id"=>$vt_object->get("ca_id")))."'>
            <img src='".__CA_URL_ROOT__."/themes/default/graphics/buttons/glyphicons_198_ok.png' alt='glyphicons_198_ok' border='0' align='middle'>
            </a>
            <a href='".caNavUrl($this->request,"museesDeFrance","InventaireBiensAffectes","Remove",array("object_id"=>$vt_object->get("ca_id")))."'>
            <img src='".__CA_URL_ROOT__."/themes/default/graphics/buttons/glyphicons_197_remove.png' alt='glyphicons_197_remove' border='0' align='middle'>
            </a>";
        }
            print "</td>";
        print "</tr>";
        $i++;
    }
    ?>
    </tbody>
    </table>

    <?php if (!$vn_obj_nb) : ?><div class="searchNav">Le registre est vide.</div><?php endif; ?>

    <script type="text/javascript">
        jQuery(document).ready(function()
            {
                jQuery("#registre_biens_affectes").tablesorter().tablesorterPager({container: $("#searchNav")}); ;
            }
        );

    </script>

</div>

<?php print caFormControlBox(
    caNavButton($this->request,__CA_NAV_ICON_PDF__,"Générer le PDF","", "*", '*', 'GeneratePDF').
    caNavButton($this->request, __CA_NAV_ICON_IMAGE__, "Afficher les photos", "", "*", "*", "Photos"),
    null,
    null
); ?>

<div class="editorBottomPadding"><!-- empty --></div>
<div class="editorBottomPadding"><!-- empty --></div>
