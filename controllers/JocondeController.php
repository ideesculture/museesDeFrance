<?php

require_once(__CA_LIB_DIR__ . '/core/Configuration.php');
require_once(__CA_MODELS_DIR__ . '/ca_objects.php');
require_once(__CA_MODELS_DIR__ . '/ca_sets.php');
require_once(__CA_MODELS_DIR__ . '/ca_occurrences.php');
require_once(__CA_LIB_DIR__ . '/ca/Search/ObjectSearch.php');
require_once(__CA_LIB_DIR__ . '/ca/Search/OccurrenceSearch.php');
require_once(__CA_LIB_DIR__ . '/ca/Search/SetSearch.php');
require_once(__CA_MODELS_DIR__. '/ca_data_exporters.php');
require_once(__CA_LIB_DIR__ . '/ca/Search/SetSearch.php');

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
		
		$exporter = new ca_data_exporters();	
		
		@mkdir(__CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/".$refexport);
		@mkdir(__CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/".$refexport."/media");

		// Exporting the medias
		$object_nb = 0;
		$images_nb = 0;
		$images_non_exportees_nb = "";
		while ($qr_results->nextHit()) {
			$object_nb++;
			$vt_object = new ca_objects($qr_results->get("object_id"));
			
			$representation_id = $vt_object->getPrimaryRepresentationID();
			
			if($representation_id) {
				//print $representation_id.",";
				$images_nb++;
				$vt_representation = new ca_object_representations($representation_id);
				$vs_media_path = $vt_representation->getMediaPath("media","joconde1200px");
				copy($vs_media_path, __CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/".$refexport."/media/".$museo."00_".$representation_id."_1.jpg");
				// Si image pas exportable :
				// $images_non_exportees_nb++; 
			} else {
				// Pas d'image
				//print "Pas d'image\n";
			}
		}
		//die();
		$qr_results = $vt_sl_search->search('set:"joconde"');

		$result = $exporter->exportRecordsFromSearchResult("export_joconde", $qr_results, __CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/".$refexport."/".$refexport.".txt");
		//die();
		
		$zipFile = __CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/".$refexport.".zip";
		$dirToZip = __CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/".$refexport;
		
		// RAPPORT	
		$rapport = $this->opo_config->get('NomMusee').", ".$this->opo_config->get('Commune').", ".$this->opo_config->get('museo')."\n";
		$rapport .= $date."\n";
		$rapport .= $refexport."\n";
		$rapport .= $object_nb." notices exportées/".$object_nb." notices sélectionnées\n";
		$rapport .= "\n";
		$rapport .= ((int) $images_non_exportees_nb)." image(s) non exportée(s)\n";
		//$rapport .= "[EN COURS] ici nom de fichier des images non exportées, numéro d'inventaire de l'objet concerné, référence de la notice associée\n";
		$rapport .= "0 image(s) dont le champ PHOT n'est pas renseigné.\n";
		
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
