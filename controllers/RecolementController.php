<?php

require_once(__CA_LIB_DIR__ . '/core/Configuration.php');
require_once(__CA_MODELS_DIR__ . '/ca_objects.php');
require_once(__CA_MODELS_DIR__ . '/ca_occurrences.php');
require_once(__CA_LIB_DIR__ . '/ca/Search/OccurrenceSearch.php');
require_once(__CA_LIB_DIR__ . '/ca/Search/SetSearch.php');


class RecolementController extends ActionController
{
	# -------------------------------------------------------
	protected $opo_config; // plugin configuration file
	protected $opa_infos_campagnes_par_recolement_decennal;
	# -------------------------------------------------------
	#
	# -------------------------------------------------------
	public function __construct(&$po_request, &$po_response, $pa_view_paths = null)
	{
		parent::__construct($po_request, $po_response, $pa_view_paths);

		if (!$this->request->user->canDoAction('can_use_recolementsmf_plugin')) {
			$this->response->setRedirect($this->request->config->get('error_display_url') . '/n/3000?r=' . urlencode($this->request->getFullUrlPath()));
			return;
		}
		$ps_plugin_path = __CA_BASE_DIR__ . "/app/plugins/museesDeFrance";

		if (file_exists($ps_plugin_path . '/conf/local/museesDeFrance.conf')) {
			$this->opo_config = Configuration::load($ps_plugin_path . '/conf/local/museesDeFrance.conf');
		} else {
			$this->opo_config = Configuration::load($ps_plugin_path . '/conf/museesDeFrance.conf');
		}

		$va_infos = $this->_computeInfos();
		$this->opa_infos_campagnes_par_recolement_decennal = $va_infos["campagnes_par_recolement_decennal"];
		
		$now = time(); 
		$date = $va_infos["date"];
		$interval = $now - $date; 
		if($interval > 24*60*60){
			$this->_createCache();
		}

			
	}

	private function _copyAttributes($t_instance_from, $t_instance_to, $va_element_codes_from, $va_element_codes_to = null)
	{
		global $g_ui_locale_id;

		// if no target setp or target not similar to source, target element = source element
		if ( !$va_element_codes_to ) $va_element_codes_to=$va_element_codes_from;
		if ( count($va_element_codes_to) != count($va_element_codes_from) ) return false;
		$vs_table = $t_instance_from->tableName();
		foreach ($va_element_codes_from as $vs_num => $vs_element_code) {
			$va_values = $t_instance_from->get("{$vs_table}.{$vs_element_code}", array("returnAsArray" => true, "returnAllLocales" => true, 'forDuplication' => true));
			if (!is_array($va_values)) {
				continue;
			}

			foreach ($va_values as $vn_id => $va_values_by_locale) {
				foreach ($va_values_by_locale as $vn_locale_id => $va_values_by_attr_id) {
					foreach ($va_values_by_attr_id as $vn_attribute_id => $va_val) {
						$va_val['locale_id'] = ($vn_locale_id) ? $vn_locale_id : $g_ui_locale_id;
						$va_val[$va_element_codes_to[$vs_num]] = $va_val[$va_element_codes_from[$vs_num]];
						unset($va_val[$va_element_codes_from[$vs_num]]);
						$t_instance_to->addAttribute($va_val, $va_element_codes_to[$vs_num]);
					}
				}
			}
		}
		$t_instance_to->setMode(ACCESS_WRITE);
		$t_instance_to->update();

		if ($t_instance_to->numErrors()) {
			var_dump(join('; ', $t_instance_to->getErrors()));
			die();
			return join('; ', $t_instance_to->getErrors());
		}
		return true;
	}
	
	private function _createCache()
	{
		$o_search = new OccurrenceSearch();
		$qr_hits = $o_search->search("ca_occurrences.type_id:118");
		while ($qr_hits->nextHit()) {
			$global["nb_campagnes"]++;
			$idno = $qr_hits->get('ca_occurrences.idno');
			//print $idno."\n";
			$campagne = new ca_occurrences();
			$campagne->load(array('idno' => $idno));
			$recolement_decennal = $campagne->get("recolement_decennal", array("convertCodesToDisplayText" => true));

			// freaky thing just to be sure we always have the recolement_decennal value, even inside infos of a recolement via $idno
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["recolement_decennal"] = $recolement_decennal;

			$va_recolements_idnos = $campagne->get("ca_occurrences.related.idno", array("returnAsArray" => 1));
			
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["localisation"] = $campagne->get("ca_storage_locations.preferred_labels");
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["recolement_decennal"] = $campagne->get("recolement_decennal", array("convertCodesToDisplayText" => true));
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["occurrence_id"] = $campagne->get("occurrence_id");
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["localisation"] = $campagne->get("ca_storage_locations.preferred_labels");
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["localisation_code"] = $campagne->get("ca_storage_locations.idno");
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["caracterisation"] = $campagne->get("campagne_caracterisation", array("convertCodesToDisplayText" => true));
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["champs"] = $campagne->get("campagne_champs_c");
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["conditionnement"] = $campagne->get("campagne_conditionnement");
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["accessibilite"] = $campagne->get("campagne_accessibilite");
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["idno"] = $campagne->get("idno");
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["name"] = $campagne->get("preferred_labels");
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["date_campagne"] = $campagne->get("date_campagne_c");
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["date_campagne_prev"] = $campagne->get("campagne_date_prev");
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["intervenants"] = $campagne->get("ca_entities");
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["date_campagne_pv"] = $campagne->get("campagne_date_pv");
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["recolements_total"] = count($va_recolements_idnos);
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["nombre"] = count($va_recolements_idnos);
			$campagnes_rd[$recolement_decennal]["global"]["recolements_total"] = $campagnes_rd[$recolement_decennal]["global"]["recolements_total"] + count($va_recolements_idnos);
			$vn_recolements = 0;
			if($va_recolements_idnos){
				foreach ($va_recolements_idnos as $vs_recolement_idno) {
					$t_recolement = new ca_occurrences();
					$t_recolement->load(array('idno' => $vs_recolement_idno));
					$vs_done = $t_recolement->get('done', array("convertCodesToDisplayText" => true));
					if ($vs_done == "oui") $vn_recolements++;
				}
			}
			$campagnes_rd[$recolement_decennal]["recolements"][$idno]["recolements_done"] = $vn_recolements;
			$campagnes_rd[$recolement_decennal]["global"]["recolements_done"] = $campagnes_rd[$recolement_decennal]["global"]["recolements_done"] + $vn_recolements;
		}
		$campagnes_rd[$recolement_decennal]["global"]["recolements_left"] = $campagnes_rd[$recolement_decennal]["global"]["recolements_left"] - $campagnes_rd[$recolement_decennal]["global"]["recolements_done"];
	
		//jsonify the data & saves it in a file
		$json = json_encode(array("campagnes_par_recolement_decennal" => $campagnes_rd, "date" => time())); 
		file_put_contents(__CA_BASE_DIR__ . '/app/plugins/museesDeFrance/rd_data.json' , $json); 
	}

	//builds the cache if it does not exists and send back an array containing infos
	private function _computeInfos()
	{
		if(!file_exists(__CA_BASE_DIR__ . '/app/plugins/museesDeFrance/rd_data.json')){
			$this->_createCache();
		}
		$tab = json_decode(file_get_contents(__CA_BASE_DIR__ . '/app/plugins/museesDeFrance/rd_data.json'), true);
		return $tab;
	}

	public function computeInfosAjax()
	{
		$this->_createCache();
		$tab = json_decode(file_get_contents(__CA_BASE_DIR__ . '/app/plugins/museesDeFrance/rd_data.json'), true);
		$this->opa_infos_campagnes_par_recolement_decennal = $tab["campagnes_par_recolement_decennal"];
		
		//echo json_encode($tab) . PHP_EOL;
		print "{\"result\":\"ok\"}"; 
		exit; 
	}
	
	private function extractFirstElementOfArray($array)
	{
		if (is_array($array)) {
			return array_shift(array_slice($array, 0, 1));
		} else {
			return false;
		}
	}

	private function CalculerPv($idno, $options = array(), $page = 1, $filter=null)
	{

		if (isset($options["liste_annexes"]) && $options["liste_annexes"]) {
			$inclure_liste_annexes = true;
		}

		$campagne = new ca_occurrences();
		$limite_liste_recolements = $this->opo_config->get('LimiteListeRecolements');
		$load = $campagne->load(array('idno' => $idno));
		if (!$load) return false;

		// Calcul du nb de récolements liés à la campagne
		$nb_recolements = count($campagne->get("ca_occurrences.related.idno", array("returnAsArray" => 1)));
		$pv_info = array();
		$pv_info["info"]["recolement_decennal"] = $campagne->get("recolement_decennal", array("convertCodesToDisplayText" => true));
		$pv_info["info"]["idno"] = $idno;
		$pv_info["info"]["occurrence_id"] = $campagne->get("occurrence_id");
		$campagne_methode_c = $campagne->get("campagne_methode_c", array("returnAsArray" => 1));
		$campagne_methode_c_first = $this->extractFirstElementOfArray($campagne_methode_c);
		$pv_info["info"]["campagne_caracteristiques"] = $campagne_methode_c_first["campagne_caracteristiques"];
		$pv_info["info"]["campagne_moyens"] = $campagne_methode_c_first["campagne_moyens"];
		$campagne_champs_c = $campagne->get("campagne_champs_c", array("returnAsArray" => 1));
		$campagne_champs_c_first = $this->extractFirstElementOfArray($campagne_champs_c);
		$pv_info["info"]["campagne_champs_champs"] = $campagne_champs_c_first["campagne_champs_champs"];
		$pv_info["info"]["campagne_champs_note"] = $campagne_champs_c_first["campagne_champs_note"];
		$pv_info["info"]["campagne_nom"] = $campagne->get("preferred_labels");
		$pv_info["info"]["campagne_date"] = $campagne->get("date_campagne_c");
		$pv_info["info"]["contenu_scientifique"] = $campagne->get("campagne_sci");

		// Initialisation du tableau des états
		$constatEtat_types = $this->opo_config->get('constatEtat');

		foreach ($constatEtat_types as $type) {
			$list_item = new ca_list_items();
			$list_item->load(array('idno' => $type));
			$id = $list_item->get('item_id');
			$pv_info["constatEtat"][$id]["label"] = $list_item->get("preferred_labels");
			$pv_info["constatEtat"][$id]["idno"] = $list_item->get("idno");
		}
		$pv_info["constatEtat"][""]["idno"] = "[vide]";

		// Initialisation du tableau des états globaux
		$etat_global_types = $this->opo_config->get('etat_global');
		foreach ($etat_global_types as $type) {
			$list_item = new ca_list_items();
			$list_item->load(array('idno' => $type));
			$id = $list_item->get('item_id');
			$pv_info["etat_global"][$id]["label"] = $list_item->get("preferred_labels");
			$pv_info["etat_global"][$id]["idno"] = $list_item->get("idno");
		}
		$pv_info["etat_global"][""]["idno"] = "[vide]";

		foreach ($campagne->get("ca_occurrences.related.idno", array("returnAsArray" => 1)) as $idno) {
			$recolement = new ca_occurrences();
			$recolement->load(array('idno' => $idno));
			// Objets exposés/en réserve
			switch ($recolement->get("mention_localisation", array("convertCodesToDisplayText" => 1))) {
				case "En réserve externe" :
				case "En réserve interne" :
					$pv_info["nb"]["objets_en_reserve"]++;
					break;
				case "Exposés":
					$pv_info["nb"]["objets_exposes"]++;
					break;
			}
			// Inscriptions
			$inscription_recolement_first = $this->extractFirstElementOfArray($recolement->get("inscription_recolement", array("convertCodesToDisplayText" => 1, "returnAsArray" => 1)));
			$inscription_reco_marque = $inscription_recolement_first["inscription_reco_marque"];
			if ($inscription_reco_marque == "oui") {
				$pv_info["nb"]["objets_marques"]++;
			} else {
				$pv_info["nb"]["objets_non_marques"]++;
			}
			// Objets liés
			$objets_lies = $recolement->get("ca_objects.related.idno", array("returnAsArray" => 1));
			$nb_objets_lies = count($objets_lies);
			if ($nb_objets_lies == 0) {
				$pv_info["nb"]["objets_non_inventories"]++;
				if ($inclure_liste_annexes) {
					$pv_info["liste_obj_non_inventories"] .=
						"[Fiche récolement " . $recolement->get("idno") . "] " . $recolement->get("preferred_labels") . "<w:br/>";
				}
			} elseif ($nb_objets_lies > 1) {
				$pv_info["nb"]["objets_inventories_plusieurs_fois"]++;
				if ($inclure_liste_annexes) {
					$pv_info["liste_obj_inventories_plusieurs_fois"] .=
						"[Fiche récolement " . $recolement->get("idno") . "] " . $recolement->get("preferred_labels") . " - numéros d'inventaire : " . $recolement->get("ca_objects.related.idno") . "<w:br/>";
				}
			}
			foreach ($objets_lies as $objet_lie) {
				$t_object = new ca_objects();
				$t_object->load(array('idno' => $objet_lie));
				$pv_info["nb"]["photos"] = $pv_info["nb"]["photos"] + $t_object->getRepresentationCount();
			}
			// Objets récolés
			if ($recolement->get("recolement", array("convertCodesToDisplayText" => 1)) == "Récolés") {
				$pv_info["nb"]["objets_recoles"]++;
			}
			// Vus/Manquants/Détruits
			switch ($recolement->get("presence_bien", array("convertCodesToDisplayText" => 1))) {
				case "Vus" :
					$pv_info["nb"]["objets_vus"]++;
					break;
				case "Manquants" :
					$pv_info["nb"]["objets_manquants"]++;
					if ($inclure_liste_annexes) {
						$pv_info["liste_obj_manquants"] .=
							"[" . $recolement->get("ca_objects.related.idno") . "] " . $recolement->get("ca_objects.related.preferred_labels") . "<w:br/>";
					}
					break;
				case "Non vus" :
					$pv_info["nb"]["objets_non_vus"]++;
					if ($inclure_liste_annexes) {
						$pv_info["liste_obj_non_vus"] .=
							"[" . $recolement->get("ca_objects.related.idno") . "] " . $recolement->get("ca_objects.related.preferred_labels") . "<w:br/>";
					}
					break;
				case "Détruits" :
					$pv_info["nb"]["objets_detruits"]++;
					if ($inclure_liste_annexes) {
						$pv_info["liste_obj_detruits"] .=
							"[" . $recolement->get("ca_objects.related.idno") . "] " . $recolement->get("ca_objects.related.preferred_labels") . "<w:br/>";
					}
					break;
			}

			// Difficulté d'identification
			if ($recolement->get("identification_pb", array("convertCodesToDisplayText" => 1)) == "oui") {
				$pv_info["nb"]["identification_pb"]++;
			}
			// Constat d'état
			$constat_etat_array = $recolement->get("constatEtat", array("returnAsArray" => 1, "convertCodesToDisplayText" => 0));
			$constat_etat_array = $this->extractFirstElementOfArray($constat_etat_array);
			$etat_global_type = $constat_etat_array["etat_global"];
			$pv_info["etat_global"][$etat_global_type]["count"]++;
			$constat_etat_type = $constat_etat_array["constat_etat"];
			$pv_info["constatEtat"][$constat_etat_type]["count"]++;
		}

		$campagne_id = $campagne->get("occurrence_id");

		$o_data = new Db();
		$query_limite = "LIMIT $page, $limite_liste_recolements";
		$query = "
    		SELECT occurrence_left_id as id
    		FROM ca_occurrences_x_occurrences
    		WHERE occurrence_right_id=$campagne_id
 		";
		if (!$filter) {
			$query.=$query_limite;
		}

		$qr_result = $o_data->query($query);
		$total = $o_data->query("SELECT * FROM ca_occurrences_x_occurrences WHERE occurrence_right_id=$campagne_id")->numRows();
		if ($page > 1) $pagination = "<a href='".__CA_URL_ROOT__."/index.php/museesDeFrance/Recolement/Pv/?idno=$idno&page=".($page -1 )."&f=".$filter."'><img src=".__CA_URL_ROOT__."/themes/default/graphics/arrows/arrow_left_gray.gif
		' alt=''/> Page précédente</a> ";
		if ($page*$limite_liste_recolements < $total )
			$pagination .= "<a href='".__CA_URL_ROOT__."/index.php/museesDeFrance/Recolement/Pv/?idno=$idno&page=".($page +1 )."&f=".$filter."'>Page suivante <img src=".__CA_URL_ROOT__."/themes/default/graphics/arrows/arrow_right_gray.gif
		' alt=''/></a>";

		$pv_info["liste_objets_html"] .= $pagination;
		if (!$filter) $pv_info["liste_objets_html"] .= "<table class=\"listtable\">" .
			"<tr><th></th><th>Titre</th><th>Récolé <a href='".__CA_URL_ROOT__."/index.php/museesDeFrance/Recolement/Pv/?idno=$idno&f=r'>oui</a> /
			 <a href='".__CA_URL_ROOT__."/index.php/museesDeFrance/Recolement/Pv/?idno=$idno&f=nr'>non</a>
			 </a></th><th>Vu/non vu</th><th>Emplacement</th></tr>";
		if ($filter) $pv_info["liste_objets_html"] .= "<table class=\"listtable\">" .
			"<tr><th></th><th>Titre</th><th>Récolé <a href='".__CA_URL_ROOT__."/index.php/museesDeFrance/Recolement/Pv/?idno=$idno'>tous</a>
			 </a></th><th>Vu/non vu</th><th>Emplacement</th></tr>";
		// Affichage des X premières lignes

		if ($filter == "r") $filter_value ="oui";
		if ($filter == "nr") $filter_value ="";

		$line = 0;

		while($qr_result->nextRow()) {
			$recolement = new ca_occurrences($qr_result->get('id'));
			if((!$filter) || ($recolement->get('done', array("convertCodesToDisplayText" => true)) == $filter_value)) {
				$line++;
				$pv_info["liste_objets_html"] .=
					"<tr " . ($line == 1 ? " class=odd" : "") . "><td><a href=\"" . __CA_URL_ROOT__ . "/index.php/editor/occurrences/OccurrenceEditor/Edit/occurrence_id/" . $occurrence_id . "\"><img src=\"" . __CA_URL_ROOT__ . "/themes/default/graphics/buttons/edit.png\"></td>" .
					"<td><b>" . $recolement->get("preferred_labels") . "</b>" .
					($recolement->get('done', array("convertCodesToDisplayText" => true)) == "oui" ? "<span class='done'></span>" : "<span class='todo'></span>").
					"</td>" .
					"<td>" . $recolement->get("recolement", array("convertCodesToDisplayText" => 1)) . "</td>" .
					"<td>" . $recolement->get("presence_bien", array("convertCodesToDisplayText" => 1)) . "</td>" .
					"<td>" . $recolement->get("mention_localisation", array("convertCodesToDisplayText" => 1)) . "</b></td>";
				if ($line == 2) $line = 0;

			}
		}
		$pv_info["liste_objets_html"] .= "</table>";

		$pv_info["liste_objets_html"] .= $pagination;

		$va_recolements_idnos = $campagne->get("ca_occurrences.related.idno", array("returnAsArray" => 1));
		$pv_info["info"]["recolements_total"] = count($va_recolements_idnos);
		$vn_recolements = 0;

		foreach ($va_recolements_idnos as $vs_recolement_idno) {
			$t_recolement = new ca_occurrences();
			$t_recolement->load(array('idno' => $vs_recolement_idno));
			$vs_done = $t_recolement->get('done', array("convertCodesToDisplayText" => true));
			if ($vs_done == "oui") $vn_recolements++;
		}
		$pv_info["info"]["recolements_done"] = $vn_recolements;

		return $pv_info;
	}

	# -------------------------------------------------------
	public function Index()
	{
		//$this->view->setVar('campagnes', $this->opa_infos_campagnes);
		$this->view->setVar('campagnes_par_rd', $this->opa_infos_campagnes_par_recolement_decennal);
		$this->render('recolement_list_grouped_html.php');
	}

	# -------------------------------------------------------
	public function TableauSuivi()
	{
		$ps_rd = $this->request->getParameter('rd_name', pString);

		$this->view->setVar('campagnes', $this->opa_infos_campagnes_par_recolement_decennal[$ps_rd]["recolements"]);
		$this->view->setVar('rd', $ps_rd);
		$this->render('recolement_tableau_suivi_html.php');
	}
	# -------------------------------------------------------
	public function Pv()
	{
		if (isset($_GET["page"]) && ($_GET["page"]>1)) {
			$page = $_GET["page"];
		} else {
			$page = 1;
		}
		$InfosPv = $this->CalculerPv($_GET["idno"],array(), $page, $_GET["f"]);
		if ($InfosPv === false) die("Impossible de récupérer les informations de la campagne de récolement " . $_GET["idno"]);

		$this->view->setVar('InfosPv', $InfosPv);
		$this->render('recolement_pv_html.php');
	}
	# -------------------------------------------------------
	private function _createRecolement($vn_object_id = null, $vn_campagne_id = null, $vn_former_recolement_id = null)
	{
		if (!$vn_object_id) return false;

		$t_rel_types = new ca_relationship_types();
		$t_list = new ca_lists();
		$t_locale = new ca_locales();
		$vn_rel_occ_occ = $t_rel_types->getRelationshipTypeID('ca_occurrences_x_occurrences', 'related');
		$vn_recole_obj_occ = $t_rel_types->getRelationshipTypeID('ca_objects_x_occurrences', 'recole');
		$vn_recolement_type = $t_list->getItemIDFromList('occurrence_types', 'recolement');
		$pn_locale_id = $t_locale->loadLocaleByCode('fr_FR');

		$t_object = new ca_objects($vn_object_id);
		$vs_label = $t_object->get('ca_objects.preferred_labels.name');

		$t_recolement = new ca_occurrences();
		$t_recolement->setMode(ACCESS_WRITE);
		if ($vn_campagne_id) {
			$campagne = new ca_occurrences($vn_campagne_id);
			$campagne_idno = $campagne->get('idno');
		}
		$t_recolement->set('idno', $t_object->get("idno") . "RECOL" . $campagne_idno);
		$t_recolement->set('status', 0);
		$t_recolement->set('access', 1);
		$t_recolement->set('type_id', $vn_recolement_type);


		$vn_recolement_id = $t_recolement->insert();
		if ($t_recolement->numErrors()) print "ERROR INSERTING :" . join('; ', $t_recolement->getErrors()) . "\n";

		if ($vn_former_recolement_id) {
			// we have a forme recolement, copy some data from
			$result = $t_recolement->copyAttributesFrom($vn_former_recolement_id, array('restrictToAttributesByCodes'=>array(
				'dimensions', 'constatEtat', 'domaine', 'materiaux_tech_c', 'datePeriod', 'dateMillesime', 'objectProductionDate', 'useMethod', 'credits_photo',
				'recol_presence_inventaire_c', 'numinventaire_expertise', 'recol_presence_num_c', 'conformite',
				'source_type', 'inscription_recolement', 'recolement_suites_c', 'recol_suites_plaintes_c', 'recol_ensemble_complexe'
			)));
			$t_recolement->update();
			$t_former_recolement = new ca_occurrences($vn_former_recolement_id);
			$result = $this->_copyAttributes($t_former_recolement,$t_recolement,
				array('presence_bien'),
				array('presence_bien_precedent'));

			// getting older value of mention_localisation, as it is a container we need to map old value to new ones
			$va_mention_localisation_prec = reset($t_former_recolement->get("mention_localisation", array("returnAsArray" => true, 'forDuplication' => true)));
			$va_mention_localisation_prec["mention_localisation_prec_loc"] = $va_mention_localisation_prec["mention_localisation_loc"];
			unset($va_mention_localisation_prec["mention_localisation_loc"]);
			$va_mention_localisation_prec["mention_localisation_prec_date"] = $va_mention_localisation_prec["mention_localisation_date"];
			unset($va_mention_localisation_prec["mention_localisation_date"]);

			// patchy solution for date parsing needed to go DD/MM/YYYY
			$vs_date_recolement = $t_former_recolement->get('recolement_date');
			$vs_locale = setlocale(LC_TIME, 0);
			setlocale(LC_TIME, 'fr_FR');
			foreach(range(1, 12) as $i){
				$month = strftime('%B', mktime(0, 0, 0, $i));
				// Date cleaning for recolement and mention_localisation
				$vs_date_recolement = str_ireplace(" ".$month." ","/".$i."/",$vs_date_recolement);
				$va_mention_localisation_prec["mention_localisation_prec_date"] = str_ireplace(" ".$month." ","/".$i."/",
					$va_mention_localisation_prec["mention_localisation_prec_date"]);
			}
			setlocale(LC_TIME, $vs_locale);
			
			$t_recolement->addAttribute($va_mention_localisation_prec,"mention_localisation_precedent");
			$t_recolement->update();
				
			$t_recolement->addAttribute(array("recolement_date_precedent"=>$vs_date_recolement),"recolement_date_precedent");
			$t_recolement->update();

			$t_recolement->addAttribute(array("recol_numfiche_recol_prec"=>$t_former_recolement->get('idno')),"recol_numfiche_recol_prec");
			$t_recolement->update();

			// Copy storage locations relations
			$va_storage_locations_rel = $t_former_recolement->get("ca_storage_locations",array("returnAsArray" => 1));
			foreach($va_storage_locations_rel as $va_storage_location_rel) {
				$t_recolement->addRelationship(
					"ca_storage_locations", // relation table name
					$va_storage_location_rel["location_id"], // row id to link
					$va_storage_location_rel["relationship_type_id"] // type id
				);
				$t_recolement->update();
			}

			// Creating a relation former/new
			$t_recolement->addRelationship(
				"ca_occurrences",
				$vn_former_recolement_id,
				"related"
			);
			$t_recolement->update();
		} else {
			// no former recolement, copy some data from the object
			$result = $this->_copyAttributes($t_object, $t_recolement, array(
				'dimensions', 'constatEtat', 'domaine', 'materiaux_tech_c', 'datePeriod', 'dateMillesime', 'objectProductionDate', 'useMethod', 'credits_photo'
			));
		}

		if ($vn_campagne_id) {
			$t_recolement->addRelationship('ca_occurrences', $vn_campagne_id, $vn_rel_occ_occ);
		}
		$t_recolement->addRelationship('ca_objects', $vn_object_id, $vn_recole_obj_occ);
		$t_recolement->update();
		$t_recolement->addLabel(array('name' => $vs_label), $pn_locale_id, null, true);
		$t_recolement->update();
		if ($t_recolement->numErrors()) {
			print "ERROR INSERTING :" . join('; ', $t_recolement->getErrors()) . "\n";
			return false;
		}

		return true;
	}

	# -------------------------------------------------------
	public function PreparerCampagne()
	{

		if (isset($_GET["idno"])) {
			$vs_campagne_idno = $_GET["idno"];
		}
		if (isset($_POST["idno"])) {
			$vs_campagne_idno = $_POST["idno"];
		}
		if (!isset($vs_campagne_idno)) {
			die("Erreur : aucun identifiant de campagne.");
		}
		$campagne = new ca_occurrences();
		$campagne->load(array('idno' => $vs_campagne_idno));
		$campagne->setMode(ACCESS_WRITE);
		$vn_campagne_id = $campagne->get("occurrence_id");

		$vs_set_id = $_POST["set_id"];
		if (!isset($vs_set_id)) {
			// select a set
			// and ask for validation
			$sets = new ca_sets();
			$set_search = new SetSearch();
			$qr_results = $set_search->search("*");

			while ($qr_results->nextHit()) {
				if ($qr_results->get('table_num') == 57) {
					// if the set contains object (num 57) create an array('id' => 'label',...)
					$va_setList[$qr_results->get('set_id')] = $qr_results->get('preferred_labels');
				}
			}
			$this->view->setVar('SetList', $va_setList);
			$this->view->setVar('CampagneIdno', $vs_campagne_idno);
			$this->render('preparer_campagne_select_set_html.php');
		} else {
			// do a search and create Recolement for all found objects
			$set = new ca_sets($vs_set_id);
			$va_object_ids = $set->getItemRowIDs();
			$va_recolements_related = array();
			foreach ($va_object_ids as $vn_object_id => $nil) {
				$t_object = new ca_objects($vn_object_id);
				// Check and get occurrences linked to this object to this if we have a former recolement
				$va_occurrences_related = reset($t_object->get("ca_occurrences.related", array("returnAsArray" => 1)));
				foreach($va_occurrences_related as $vs_ref => $va_occurrence_related) {
					if($va_occurrence_related["item_type_id"] != 119) continue;
					// $va_recolement_related contains id of the recolement occurrences linked
					$va_recolements_related[] = $va_occurrence_related["occurrence_id"];
				}
				if (sizeof($va_occurrences_related)) {
					// create a new recolement occurrence from last recolement added to the objet
					arsort($va_recolements_related);
					$vn_recolement_id = reset($va_recolements_related);
				}
				$this->_createRecolement($vn_object_id, $vn_campagne_id, $vn_recolement_id);
			}
			// do the calc
			// print the results

			$va_campagne["occurrence_id"] = $campagne->get("occurrence_id");
			$va_campagne["localisation"] = $campagne->get("ca_storage_locations.preferred_labels");
			$va_campagne["localisation_code"] = $campagne->get("ca_storage_locations.idno");
			$va_campagne["caracterisation"] = $campagne->get("campagne_caracterisation", array("convertCodesToDisplayText" => true));
			$va_campagne["champs"] = $campagne->get("campagne_champs_c");
			$va_campagne["conditionnement"] = $campagne->get("campagne_conditionnement");
			$va_campagne["nombre"] = "A CALCULER";
			$va_campagne["accessibilite"] = $campagne->get("campagne_accessibilite");
			$va_campagne["idno"] = $campagne->get("idno");
			$va_campagne["name"] = $campagne->get("preferred_labels");
			$va_campagne["date_campagne"] = $campagne->get("date_campagne_c");
			$va_campagne["date_campagne_prev"] = $campagne->get("campagne_date_prev");
			$va_campagne["intervenants"] = $campagne->get("ca_entities");
			$va_campagne["date_campagne_pv"] = $campagne->get("campagne_date_pv");
			$va_recolements_idnos = $campagne->get("ca_occurrences.related.idno", array("returnAsArray" => 1));
			$va_campagne["recolements_total"] = count($va_recolements_idnos);
			$vn_recolements = 0;
			foreach ($va_recolements_idnos as $vs_recolement_idno) {
				$t_recolement = new ca_occurrences();
				$t_recolement->load(array('idno' => $vs_recolement_idno));
				$vs_done = $t_recolement->get('done', array("convertCodesToDisplayText" => true));
				if ($vs_done == "oui") $vn_recolements++;
			}
			$va_campagne["recolements_done"] = $vn_recolements;

			$this->view->setVar('RecolementsCrees', count($va_object_ids));
			$this->view->setVar('Campagne', $va_campagne);
			$this->render('preparer_campagne_results_html.php');
		}
	}

	# -------------------------------------------------------
	public function PvWord()
	{
		$InfosPv = $this->CalculerPv($_GET["idno"], array("liste_annexes" => true));
		if ($InfosPv === false) die("Impossible de récupérer les informations de la campagne de récolement " . $_GET["idno"]);
		$this->view->setVar('InfosPv', $InfosPv);
		$this->render('recolement_pv_word_html.php');
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