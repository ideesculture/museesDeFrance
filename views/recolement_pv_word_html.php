<?php 

	$InfosPv = $this->getVar('InfosPv');
	//var_dump($InfosPv);die();
	require_once __CA_BASE_DIR__ . '/app/plugins/museesDeFrance/helpers/Template.php';
	$template = new PHPWord_Template(__CA_BASE_DIR__."/app/plugins/museesDeFrance/conf/PV_recolement.docx");
	
	/*
	 * Définition des valeurs
	 */
 	
	$template->setValue("preferred_labels", $InfosPv["info"]["campagne_nom"]);
 	$template->setValue("date_campagne", $InfosPv["info"]["campagne_date"]);
 	$template->setValue("campagne_caracteristiques", html_entity_decode($InfosPv["info"]["campagne_caracteristiques"],ENT_QUOTES,"UTF-8"));
 	$template->setValue("campagne_moyens", html_entity_decode($InfosPv["info"]["campagne_moyens"],ENT_QUOTES,"UTF-8"));
	$template->setValue("campagne_champs_champs", html_entity_decode($InfosPv["info"]["campagne_champs_champs"],ENT_QUOTES,"UTF-8"));
	$template->setValue("campagne_champs_note",html_entity_decode($InfosPv["info"]["campagne_champs_note"],ENT_QUOTES,"UTF-8"));
	$template->setValue("campagne_sci",html_entity_decode($InfosPv["info"]["contenu_scientifique"],ENT_QUOTES,"UTF-8"));
	$template->setValue("objets_vus",$InfosPv["nb"]["objets_vus"]);
	$template->setValue("objets_non_vus", $InfosPv["nb"]["objets_non_vus"]);
	$template->setValue("objets_manquants", $InfosPv["nb"]["objets_manquants"]);
	$template->setValue("objets_detruits", $InfosPv["nb"]["objets_detruits"]);
	$template->setValue("objets_non_inventories", $InfosPv["nb"]["objets_non_inventories"]);
	$template->setValue("objets_inventories_plusieurs_fois",$InfosPv["nb"]["objets_inventories_plusieurs_fois"]);
	$template->setValue("objets_marques", $InfosPv["nb"]["objets_marques"]);
	$template->setValue("objets_non_marques", $InfosPv["nb"]["objets_non_marques"]);
	$template->setValue("objets_exposes", $InfosPv["nb"]["objets_exposes"]);
	$template->setValue("objets_en_reserve", $InfosPv["nb"]["objets_en_reserve"]);
	$template->setValue("total_objets_recoles", $InfosPv["nb"]["objets_recoles"]);
	$template->setValue("defaut_integrite", $InfosPv["defaut_integrite"]);
	$template->setValue("etat_des_collections_par_categorie", $InfosPv["etat_des_collections_par_categorie"]);
	$template->setValue("photographies_existantes", $InfosPv["nb"]["photos"]);
	$template->setValue("objets_presentants_pb_identification", $InfosPv["nb"]["identification_pb"]);
	
	$template->setValue("liste_obj_non_vus", ($InfosPv["liste_obj_non_vus"] ? $InfosPv["liste_obj_non_vus"] : "NÉANT"));
	$template->setValue("liste_obj_manquants", ($InfosPv["liste_obj_manquants"] ? $InfosPv["liste_obj_manquants"] : "NÉANT"));
	$template->setValue("liste_obj_detruits", ($InfosPv["liste_obj_detruits"] ? $InfosPv["liste_obj_detruits"] : "NÉANT"));
	$template->setValue("liste_obj_non_inventories", ($InfosPv["liste_obj_non_inventories"] ? $InfosPv["liste_obj_non_inventories"] : "NÉANT"));
	$template->setValue("liste_obj_inventories_plusieurs_fois", ($InfosPv["liste_obj_inventories_plusieurs_fois"] ? $InfosPv["liste_obj_inventories_plusieurs_fois"] : "NÉANT"));
	
	//var_dump($InfosPv["liste_objets_non_vus"]);die();
	
	foreach($InfosPv["constatEtat"] as $etat) {
		$template->setValue("etat_collection.".$etat["idno"].".nb", $etat["count"]);
	}
	//$template->setValue("")

	$template->save(__CA_BASE_DIR__."/app/tmp/PV_recolement_".$InfosPv["info"]["idno"].".docx");
	
?>

<h1>Procès-verbal de récolement</h1>

<p>Vous pouvez télécharger depuis cette page le fichier au format .docx (Microsoft Word).<br/> Ce fichier est lisible également avec OpenOffice ou LibreOffice.</p>

<a class="form-button" href="<?php print __CA_URL_ROOT__;?>/app/tmp/PV_recolement_<?php print $InfosPv["info"]["idno"];?>.docx">
	<span class="form-button">
		<img class="form-button-left" src="<?php print __CA_URL_ROOT__;?>/app/plugins/museesDeFrance/views/page_white_word.png" align=center>
		&nbsp; télécharger
	</span>
</a>
