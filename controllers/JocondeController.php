<?php

require_once(__CA_LIB_DIR__ . '/Configuration.php');
require_once(__CA_MODELS_DIR__ . '/ca_objects.php');
require_once(__CA_MODELS_DIR__ . '/ca_sets.php');
require_once(__CA_MODELS_DIR__ . '/ca_occurrences.php');
require_once(__CA_LIB_DIR__ . '/Search/ObjectSearch.php');
require_once(__CA_LIB_DIR__ . '/Search/OccurrenceSearch.php');
require_once(__CA_LIB_DIR__ . '/Search/SetSearch.php');
require_once(__CA_MODELS_DIR__. '/ca_data_exporters.php');
require_once(__CA_LIB_DIR__ . '/Search/SetSearch.php');

function Zip($source, $destination)
{
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true)
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file)
        {
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
                continue;

            $file = realpath($file);

            if (is_dir($file) === true)
            {
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            }
            else if (is_file($file) === true)
            {
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }
    }
    else if (is_file($source) === true)
    {
        $zip->addFromString(basename($source), file_get_contents($source));
    }

    return $zip->close();
}

class JocondeController extends ActionController
{
	# -------------------------------------------------------
	protected $opo_config; // plugin configuration file
	# -------------------------------------------------------
	#
	# -------------------------------------------------------
	public function __construct(&$po_request, &$po_response, $pa_view_paths = null) {
		parent::__construct($po_request, $po_response, $pa_view_paths);

		if (!$this->request->user->canDoAction('can_use_recolementsmf_plugin')) {
			$this->response->setRedirect($this->request->config->get('error_display_url') . '/n/2320?r=' . urlencode($this->request->getFullUrlPath()));
			return;
		}
		$ps_plugin_path = __CA_BASE_DIR__ . "/app/plugins/museesDeFrance";

		if (file_exists($ps_plugin_path . '/conf/local/museesDeFrance.conf')) {
			$this->opo_config = Configuration::load($ps_plugin_path . '/conf/local/museesDeFrance.conf');
		} else {
			$this->opo_config = Configuration::load($ps_plugin_path . '/conf/museesDeFrance.conf');
		}
	}


	# -------------------------------------------------------
	public function Index() {
		//$this->view->setVar('campagnes', $this->opa_infos_campagnes);
		//$this->view->setVar('campagnes_par_rd', $this->opa_infos_campagnes_par_recolement_decennal);
		$export_folder = __CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/";
		$folders = scandir($export_folder);
		$folders_contents = array();
		foreach($folders as $key=>$folder) {
			if(($folder == ".." || $folder == "." || $folder == "_referentiel") || !is_dir($export_folder.$folder)) {
				unset($folders[$key]);
			} else {
				$folders_contents[$folder] = scandir($export_folder.$folder);
			}
		}

		$this->view->setVar('folders', $folders);
		$this->view->setVar('folders_contents', $folders_contents);
		
		$this->render('joconde_index_html.php');
	}

	public function Export() {
		$museo = $this->opo_config->get('museo');
		
		$numexport = file_get_contents(__CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/_referentiel/.numexport");
		$numexport=$numexport*1+1;
		file_put_contents(__CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/_referentiel/.numexport", $numexport);
		
		
		// Atom size of the date is up to the minute, don't allow less
		$lastdate = @file_get_contents(__CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/_referentiel/.dateexport");
		$date = date("Ymd_Hi");
		if($date == $lastdate) {
			return $this->render('joconde_deuxieme_export_html.php');
		}
		file_put_contents(__CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/_referentiel/.dateexport", $date);
		
		
		// file and directory name
		$refexport = "J_".$museo."-".str_pad($numexport, 4, "0", STR_PAD_LEFT)."_".$date;
		
		
		$vt_sl_search = new ObjectSearch();
		$qr_results = $vt_sl_search->search('set:"joconde"');

		$vt_set = new ca_sets();
		$vt_set->load(["deleted" => 0, "set_code" => "joconde"]);
		$setitems = explode(";", $vt_set->getWithTemplate("<unit relativeTo='ca_set_items'>^ca_set_items.row_id</unit>"));
		$exporter = new ca_data_exporters();	
		
		@mkdir(__CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/".$refexport);
		@mkdir(__CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/".$refexport."/media");

		$results =[];

		// Exporting the medias
		$object_nb = 0;
		$images_nb = 0;
		$nb_sans_img = 0;
		$nb_sans_credits = 0;
		$images_non_exportees_nb = 0;
		$results = [];
		foreach ($setitems as $item) {
			$object_nb++;
			$vt_object = new ca_objects($item);
			$representation_id = $vt_object->getPrimaryRepresentationID();
			$medianame = "";
			if($representation_id) {
				//print $representation_id.",";
				$images_nb++;
				$vt_representation = new ca_object_representations($representation_id);
				$vs_media_name = $vt_representation->get("original_filename");
				$vs_media_path = $vt_representation->getMediaPath("media","large");
				$medianame = $museo."00_".$representation_id."_1.jpg";
				$mediapath = __CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/".$refexport."/media/".$medianame;
				$mediaSize = getimagesize($vs_media_path);
				if($mediaSize[0] >= $mediaSize[1]){
					// Cas 1 : l'image est plus haute que large (portrait) ou bien carrée

					if ($mediaSize[0] > 1200){
						// Retaille l'image
						$percent = $mediaSize[0] / 1200;
						
						if(copy($vs_media_path, $mediapath) == true){
							$img = resize_image($mediapath, $mediaSize[1]*$percent, $mediaSize[0]*$percent);
						}else{
							$medianame = "";
							$images_non_exportees_nb++; 
						}
					}elseif ($mediaSize[0] <= 1200 && $mediaSize[0] >= 480){
						if (!copy($vs_media_path, $mediapath)){
							$images_non_exportees_nb++; 
						}
					}else {
						$medianame = "";
						$imgTooSmall[] = [$vs_media_name,$vt_object->getWithTemplate("^ca_objects.idno"),$museo.$item,"taille inférieure à 640 x 480 pixels"];
						$images_non_exportees_nb++; 
					}
				}else{
					// Cas 2 : l'image est plus large que haute (paysage)
					if ($mediaSize[1] > 1600 ){
						$percent = $mediaSize[1] / 1600;
						if(copy($vs_media_path, $mediapath) == true){
							$img = resize_image($mediapath, $mediaSize[1]*$percent, $mediaSize[0]*$percent);
							$test2 = getimagesize($mediapath);
							if ($test2[0] > 1200){
								$percent = $test2[0] / 1200;
								$img = resize_image($mediapath, $mediaSize[1]*$percent, $mediaSize[0]*$percent);
							}
						}else{
							$medianame = "";
							$images_non_exportees_nb++; 
						}					
					} elseif( $mediaSize[1] <= 1600 && $mediaSize[1] >= 640){
						if (!copy($vs_media_path, $mediapath)){
							$images_non_exportees_nb++; 
							$medianame = "";
						}
					}else{
						$images_non_exportees_nb++; 
						$medianame = "";
						$imgTooSmall[] = [$vs_media_name,$vt_object->getWithTemplate("^ca_objects.idno"),$museo.$item,"taille inférieure à 640 x 480 pixels"];

					}

				}

				// $images_non_exportees_nb++; 
			} else {
				// Pas d'image
				$nb_sans_img++;
				//print "Pas d'image\n";
			}
			$credits = $vt_object->get("credits_photo");
			if (empty($credits) && $medianame != ""){
				$nb_sans_credits++;
				$sans_credit[] =[$medianame, $vt_object->getWithTemplate("^ca_objects.idno"),$museo.$item];
			}
			$image = "Non";
			if ($medianame != ""){
				$image = "Oui";
			}
			$results[] = [
				$museo.$item,
				$vt_object->getWithTemplate("^ca_objects.idno <ifdef code='ca_objects.otherNumber.objectNo'>; <unit relativeto='ca_objects.otherNumber'> ^ca_objects.otherNumber.objectNo (^ca_objects.otherNumber.objectNumberType)</unit></ifdef>"),
				$vt_object->getWithTemplate("^ca_objects.domaine"),
				$vt_object->getWithTemplate("<unit relativeTo='ca_objects.joconde_denomination_c'>^ca_objects.joconde_denomination_c.joconde_denomination (^ca_objects.joconde_denomination_c.precisions_deno)</unit>"),
				$vt_object->getWithTemplate("^ca_objects.appellation"),
				$vt_object->getWithTemplate("^ca_objects.preferred_labels"),
				$vt_object->getWithTemplate("<unit relativeTo='ca_entities' restrictToRelationshipTypes='creation_auteur, creation_createur'>^ca_entities.preferred_labels.displayname (^relationship_typename)</unit>"),
				$vt_object->getWithTemplate("<unit relativeTo='ca_entities' restrictToRelationshipTypes='creation_auteur, creation_createur' delimiter='#'>^ca_entities.preferred_labels.surname : ^ca_entities.vitalDates.lieu_naissance, ^ca_entities.vitalDates.birth <ifdef code='ca_entities.vitalDates.death'>; ^ca_entities.vitalDates.lieu_deces, ^ca_entities.vitalDates.death</ifdef></unit>"),
				$vt_object->getWithTemplate("^ca_objects.ecole"),
				$vt_object->getWithTemplate("^ca_objects.anciennes_attributions"),
				$vt_object->getWithTemplate(""), // TODO: Lieux quand bonne hiérarchie
				$vt_object->getWithTemplate(""), // TODO: Préicison Lieux quand bonne hiérarchie
				$vt_object->getWithTemplate("^ca_objects.objectProductionDate"),
				$vt_object->getWithTemplate("^ca_objects.dateMillesime"),
				$vt_object->getWithTemplate("^ca_objects.per_orig_copir_joconde"),
				$vt_object->getWithTemplate("^ca_objects.epoque"),
				$vt_object->getWithTemplate("^ca_objects.util_dest"),
				$vt_object->getWithTemplate("^ca_objects.precisions_utili_dest"),
				$vt_object->getWithTemplate("^ca_objects.datePeriod_dest.datePeriod_datation_dest"),
				$vt_object->getWithTemplate("^ca_objects.dateutil"),
				$vt_object->getWithTemplate("<unit relativeTo='ca_objects.materiaux_tech_c'>^ca_objects.materiaux_tech_c.materiaux (^ca_objects.materiaux_tech_c.techniques)</unit>"),
				$vt_object->getWithTemplate("<unit relativeTo='ca_objects.dimensions'><ifdef code='ca_objects.dimensions.circumference'>Dia. ^ca_objects.dimensions.circumference ;</ifdef><ifdef code='ca_objects.dimensions.dimensions_depth'>Pr. ^ca_objects.dimensions.dimensions_depth ; </ifdef><ifdef code='ca_objects.dimensions.dimensions_height'>H. ^ca_objects.dimensions.dimensions_height <ifdef code='ca_objects.dimensions.type_dimensions'>(^ca_objects.dimensions.type_dimensions)</ifdef>;</ifdef><ifdef code='ca_objects.dimensions.dimensions_poids'>P. ^ca_objects.dimensions.dimensions_poids ;</ifdef><ifdef code='ca_objects.dimensions.dimensions_width'>l. ^ca_objects.dimensions.dimensions_width ;</ifdef><ifdef code='ca_objects.dimensions.dimensions_length'>L. ^ca_objects.dimensions.dimensions_length ; </ifdef><ifdef code='ca_objects.dimensions.epaisseur'>Ep. ^ca_objects.dimensions.epaisseur</ifdef></unit>"),
				$vt_object->getWithTemplate("<unit relativeTo='ca_objects.joconde_etat_c'>^ca_objects.joconde_etat_c.joconde_etat (^ca_objects.joconde_etat_c.joconde_etat_date) </unit>"),
				$vt_object->getWithTemplate("^ca_objects.description"),
				$vt_object->getWithTemplate("^ca_objects.genese"),
				$vt_object->getWithTemplate("^ca_objects.historique"),
				$vt_object->getWithTemplate("^ca_objects.geoHistorique"),
				$vt_object->getWithTemplate("TODO LIEU (lieu de découverte) ; ^ca_objects.site_type ; ^ca_objects.useMethod ; ^ca_objects.useDate.useDate_date (^ca_objects.useDate.useDate_type) ; <unit relativeTo='ca_entities' restrictToRelationshipTypes='decouvreur'>^ca_entities.preferred_labels.displayname (^relationship_typename)</unit>"), // TODO : LIEU DE DECOUVERTE EN ATTENTE REFONTE LIEU
				$vt_object->getWithTemplate("^ca_objects.precision_decouverte"), 
				$vt_object->getWithTemplate("^ca_objects.element_decoratif.element_decoratif_decor"),
				$vt_object->getWithTemplate("^ca_objects.element_decoratif.element_decoratif_precisions"),
				$vt_object->getWithTemplate("^ca_objects.element_decoratif.element_decoratif_date"),
				$vt_object->getWithTemplate("^ca_objects.source_repr"), 
				$vt_object->getWithTemplate("^ca_objects.onomastique"),
				$vt_object->getWithTemplate("<unit relativeTo='ca_storage_locations' restrictToRelationshipTypes='stockage'>^ca_storage_locations.preferred_labels</unit>"),
				$vt_object->getWithTemplate("^ca_objects.type_propriete ; ^ca_objects.AcquisitionMode.AcquisitionMode_l ; <unit relativeTo='ca_entities' restrictToRelationshipTypes='proprietaire'>^ca_entities.preferred_labels.displayname</unit> ; <unit relativeTo='ca_entities' restrictToRelationshipTypes='etablissement'>^ca_entities.preferred_labels.displayname</unit> "),
				$vt_object->getWithTemplate("<ifdef code='ca_objects.date_inventaire'> ^ca_objects.date_inventaire (Date d'inscription au registre d'inventaire) ;</ifdef> <ifdef code='ca_objects.date_ref_acteAcquisition'>^ca_objects.date_ref_acteAcquisition.date_acteAcquisition -  ca_objects.date_ref_acteAcquisition.ref_acteAcquisition (Date et références de l'acte d'acquisition) </ifdef>"), 
				$vt_object->getWithTemplate("<unit relativeTo='ca_entities' restrictToRelationshipTypes='depositaire'>^ca_entities.address.city ; ^ca_entities.preferred_labels.displayname</unit>"),
				$vt_object->getWithTemplate("^ca_objects.date_ref_acteDepot.date_acteDepot"),
				$vt_object->getWithTemplate("^ca_objects.anciensDepots"),
				$vt_object->getWithTemplate("^ca_objects.anciennes_appartenances"),
				$vt_object->getWithTemplate("^ca_objects.exposition"),
				$vt_object->getWithTemplate("^ca_objects.bibliography"),
				$vt_object->getWithTemplate("^ca_objects.comments"),
				$credits,
				$image,
				$museo, 
				$vt_object->getWithTemplate("<unit relativeTo='ca_entities' restrictToRelationshipTypes='notice_redacteur'>^ca_entities.preferred_labels.displayname</unit>"),
				$medianame
			];

		}
		$headers = ["REF", "INV", "DOMN", "DENO","APPL", "TITR", "AUTR", "PAUT", "ECOL", "ATTR", "LIEUX","PLIEUX", "PERI", "MILL", "PEOC", "EPOQ", "UTIL", "PUTI", "PERU", "MILU", "TECH", "DIMS", "ETAT", "DESC", "GENE", "HIST", "GEOHI", "DECV", "PDEC", "REPR","PREP" , "DREP", "SREP", "ONOM", "LOCA", "STAT", "DACQ","DEPO", "DDPT", "ADPT", "APTN", "EXPO","BIBL", "COMM", "COPY", "PHOT", "IMAGE", "MUSEO", "REDA", "REFIM"  ];

		$textFile = fopen(__CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/".$refexport."/media/".$refexport.".txt", "w");
		$text = join("|", $headers)."\n";
		foreach ($results as $result){
			$text .= join ('|', $result)."\n";
		}
		$textFile = fwrite($textFile, $text);

		fclose($textFile);
		//var_dump($results);die();
		//die();
		$qr_results = $vt_sl_search->search('set:"joconde"');

		$result = $exporter->exportRecordsFromSearchResult("export_joconde", $setitems, __CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/".$refexport."/".$refexport.".txt");
		//die();
		
		$zipFile = __CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/".$refexport.".zip";
		$dirToZip = __CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/".$refexport;
		$date = date("j/m/Y");
		// RAPPORT	
		$rapport = $this->opo_config->get('NomMusee').", ".$this->opo_config->get('Commune').", ".$this->opo_config->get('museo')."\n";
		$rapport .= "Date de l'export : ".$date."\n";
		$rapport .= $refexport."\n";
		$rapport .= $object_nb." notices exportées/".$object_nb." notices sélectionnées\n";
		$rapport .= "\n";
		$rapport .= ((int) $images_non_exportees_nb)." image(s) non exportée(s)\n";
		$rapport .= "NOMIMAGE|NUMINV|REF|RAISON \n";
		foreach ($imgTooSmall as $img){
			$rapport .= join("|", $img)."\n";
		}
		$rapport .= "\n";
		$rapport .= $nb_sans_credits." images sans crédits\n";
		$rapport .= "NOMIMAGE|NUMINV|REF\n";
		foreach ($sans_credit as $img){
			$rapport .= join("|", $img)."\n";
		}
		$rapport .= "\n";
		//$rapport .= ((int) $nb_sans_img)." notices sans image(s)\n";
		//$rapport .= "NUMINV|REF\n";


		//$rapport .= "0 image(s) dont le champ PHOT n'est pas renseigné.\n";
		
		file_put_contents(__CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/".$refexport."/rapport.txt", $rapport);

		// Get real path for our folder
		$rootPath = realpath($dirToZip);
		
		// Initialize archive object
		Zip($dirToZip, $zipFile);

		return $this->render('joconde_export_genere_html.php');
	}

	# -------------------------------------------------------
	# Sidebar info handler
	# -------------------------------------------------------
	public function Info($pa_parameters)
	{
		$this->view->setVar('campagnes_rd', $this->opa_infos_campagnes_par_recolement_decennal);
		return $this->render('widget_recolement_info_html.php', true);
	}
}

?>
