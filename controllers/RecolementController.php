<?php

 	require_once(__CA_LIB_DIR__.'/core/Configuration.php');
 	require_once(__CA_MODELS_DIR__.'/ca_occurrences.php');
 	require_once(__CA_LIB_DIR__.'/ca/Search/OccurrenceSearch.php');
	require_once(__CA_LIB_DIR__.'/ca/Search/SetSearch.php');


 	class RecolementController extends ActionController {
 		# -------------------------------------------------------
 		protected $opo_config;		// plugin configuration file
	    protected $opa_infos_campagnes;
	    protected $opa_infos_global;
 		# -------------------------------------------------------
 		#
 		# -------------------------------------------------------
 		public function __construct(&$po_request, &$po_response, $pa_view_paths=null) {
 			parent::__construct($po_request, $po_response, $pa_view_paths);
 			
 			if (!$this->request->user->canDoAction('can_use_recolementsmf_plugin')) {
 				$this->response->setRedirect($this->request->config->get('error_display_url').'/n/3000?r='.urlencode($this->request->getFullUrlPath()));
 				return;
 			}
 			
 			$this->opo_config = Configuration::load(__CA_APP_DIR__.'/plugins/museesDeFrance/conf/museesDeFrance.conf');

		    $va_infos = $this->_computeInfos();
		    $this->opa_infos_global = $va_infos["global"];
		    $this->opa_infos_campagnes = $va_infos["campagnes"];
 		}

	    private function _copyAttributes($t_instance_from, $t_instance_to, $va_element_codes) {
		    global $g_ui_locale_id;

		    $vs_table = $t_instance_from->tableName();
		    foreach($va_element_codes as $vs_element_code) {
			    $va_values = $t_instance_from->get("{$vs_table}.{$vs_element_code}", array("returnAsArray" => true, "returnAllLocales" => true, 'forDuplication' => true));
			    if (!is_array($va_values)) { continue; }

			    foreach($va_values as $vn_id => $va_values_by_locale) {
				    foreach($va_values_by_locale as $vn_locale_id => $va_values_by_attr_id) {
					    foreach($va_values_by_attr_id as $vn_attribute_id => $va_val) {
						    $va_val['locale_id'] = ($vn_locale_id) ? $vn_locale_id : $g_ui_locale_id;
						    $t_instance_to->addAttribute($va_val, $vs_element_code);
					    }
				    }
			    }
		    }
		    $t_instance_to->setMode(ACCESS_WRITE);
		    $t_instance_to->update();

		    if($t_instance_to->numErrors()) {
			    return join('; ', $t_instance_to->getErrors());
		    }
		    return true;
	    }

	    private function _computeInfos () {
		    $o_search = new OccurrenceSearch();
		    $qr_hits = $o_search->search("ca_occurrences.type_id:118");
		    while($qr_hits->nextHit()){
			    $global["nb_campagnes"]++;
			    $idno = $qr_hits->get('ca_occurrences.idno');
			    //print $idno."\n";
			    $campagne = new ca_occurrences();
			    $campagne->load(array('idno' => $idno));
			    $campagnes[$idno]["occurrence_id"] = $campagne->get("occurrence_id");
			    $campagnes[$idno]["localisation"] = $campagne->get("ca_storage_locations.preferred_labels");
			    $campagnes[$idno]["localisation_code"] = $campagne->get("ca_storage_locations.idno");
			    $campagnes[$idno]["caracterisation"] = $campagne->get("campagne_caracterisation",array("convertCodesToDisplayText"=>true));
			    $campagnes[$idno]["champs"] = $campagne->get("campagne_champs_c");
			    $campagnes[$idno]["conditionnement"] = $campagne->get("campagne_conditionnement");
			    $campagnes[$idno]["nombre"] = "A CALCULER";
			    $campagnes[$idno]["accessibilite"] = $campagne->get("campagne_accessibilite");
			    $campagnes[$idno]["idno"] = $campagne->get("idno");
			    $campagnes[$idno]["name"] = $campagne->get("preferred_labels");
			    $campagnes[$idno]["date_campagne"] = $campagne->get("date_campagne_c");
			    $campagnes[$idno]["date_campagne_prev"] = $campagne->get("campagne_date_prev");
			    $campagnes[$idno]["intervenants"] = $campagne->get("ca_entities");
			    $campagnes[$idno]["date_campagne_pv"] = $campagne->get("campagne_date_pv");
			    $va_recolements_idnos = $campagne->get("ca_occurrences.related.idno",array("returnAsArray" => 1));
			    $campagnes[$idno]["recolements_total"] = count($va_recolements_idnos);
			    $global["recolements_total"] = $global["recolements_total"] + $campagnes[$idno]["recolements_total"];
			    $vn_recolements = 0;
			    foreach($va_recolements_idnos as $vs_recolement_idno) {
				    $t_recolement = new ca_occurrences();
				    $t_recolement->load(array('idno' => $vs_recolement_idno));
				    $vs_done = $t_recolement->get('done',array("convertCodesToDisplayText"=>true));
				    if ($vs_done == "oui") $vn_recolements++;
			    }
			    $campagnes[$idno]["recolements_done"] = $vn_recolements;
			    $global["recolements_done"] = $global["recolements_done"] + $campagnes[$idno]["recolements_done"];
		    }
		    $global["recolements_left"] = $global["recolements_total"] - $global["recolements_done"];
		    return array("global"=>$global,"campagnes"=>$campagnes);
	    }

 		private function extractFirstElementOfArray($array) {
			if(is_array($array)) {
				return array_shift(array_slice($array,0,1));
			} else {
				return false;
			}
		}
 		
 		private function CalculerPv($idno, $options = array()) {
 		
 			if (isset($options["liste_annexes"]) && $options["liste_annexes"]) {
	 			$inclure_liste_annexes = true;
 			}
 		
 			$campagne = new ca_occurrences();
 			$limite_liste_recolements = $this->opo_config->get('LimiteListeRecolements');
 			$load = $campagne->load(array('idno' => $idno));
 			if (!$load) return false;
 			
 			// Calcul du nb de récolements liés à la campagne
 			$nb_recolements = count($campagne->get("ca_occurrences.related.idno",array("returnAsArray" => 1)));
 			$pv_info = array();
 			$pv_info["info"]["idno"]=$idno;
 			$pv_info["info"]["occurrence_id"] = $campagne->get("occurrence_id");
 			$campagne_methode_c = $campagne->get("campagne_methode_c",array("returnAsArray" => 1));
 			$campagne_methode_c_first = $this->extractFirstElementOfArray($campagne_methode_c);
 			$pv_info["info"]["campagne_caracteristiques"] = $campagne_methode_c_first["campagne_caracteristiques"];
			$pv_info["info"]["campagne_moyens"] = $campagne_methode_c_first["campagne_moyens"];
			$campagne_champs_c = $campagne->get("campagne_champs_c",array("returnAsArray" => 1));
			$campagne_champs_c_first = $this->extractFirstElementOfArray($campagne_champs_c);
			$pv_info["info"]["campagne_champs_champs"] = $campagne_champs_c_first["campagne_champs_champs"];
			$pv_info["info"]["campagne_champs_note"] = $campagne_champs_c_first["campagne_champs_note"];
			$pv_info["info"]["campagne_nom"] = $campagne->get("preferred_labels");
			$pv_info["info"]["campagne_date"] = $campagne->get("date_campagne_c");
			$pv_info["info"]["contenu_scientifique"] = $campagne->get("campagne_sci");
			
			// Initialisation du tableau des états
			$constatEtat_types = $this->opo_config->get('constatEtat');

			foreach($constatEtat_types as $type) {
				$list_item = new ca_list_items();
				$list_item->load(array('idno'=>$type));
				$id = $list_item->get('item_id');
				$pv_info["constatEtat"][$id]["label"] = $list_item->get("preferred_labels");
				$pv_info["constatEtat"][$id]["idno"] = $list_item->get("idno");
			}
			$pv_info["constatEtat"][""]["idno"] = "[vide]";
			
			// Initialisation du tableau des états globaux
			$etat_global_types = $this->opo_config->get('etat_global');
			foreach($etat_global_types as $type) {
				$list_item = new ca_list_items();
				$list_item->load(array('idno'=>$type));
				$id = $list_item->get('item_id');
				$pv_info["etat_global"][$id]["label"] = $list_item->get("preferred_labels");
				$pv_info["etat_global"][$id]["idno"] = $list_item->get("idno");
			}
			$pv_info["etat_global"][""]["idno"] = "[vide]";

 			foreach ($campagne->get("ca_occurrences.related.idno",array("returnAsArray" => 1)) as $idno) {
 				$recolement = new ca_occurrences();
 				$recolement->load(array('idno' => $idno));
 				// Objets exposés/en réserve
 				switch($recolement->get("mention_localisation", array("convertCodesToDisplayText" => 1))) {
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
 					$pv_info["nb"]["objets_marques"] ++;
 				} else {
 					$pv_info["nb"]["objets_non_marques"] ++;
 				}
 				// Objets liés
 				$objets_lies = $recolement->get("ca_objects.related.idno",array("returnAsArray" => 1));
 				$nb_objets_lies = count($objets_lies);
 				if ($nb_objets_lies == 0) {
 					$pv_info["nb"]["objets_non_inventories"] ++;
 					if ($inclure_liste_annexes) {
	 					$pv_info["liste_obj_non_inventories"] .= 
	 						"[Fiche récolement ".$recolement->get("idno")."] ".$recolement->get("preferred_labels")."<w:br/>";
 					}
 				} elseif ($nb_objets_lies > 1) {
 					$pv_info["nb"]["objets_inventories_plusieurs_fois"] ++;
 					if ($inclure_liste_annexes) {
	 					$pv_info["liste_obj_inventories_plusieurs_fois"] .= 
	 						"[Fiche récolement ".$recolement->get("idno")."] ".$recolement->get("preferred_labels")." - numéros d'inventaire : ".$recolement->get("ca_objects.related.idno")."<w:br/>";
 					}
 				}
 				foreach($objets_lies as $objet_lie) {
 					$t_object = new ca_objects();
 					$t_object->load(array('idno'=>$objet_lie));
 					$pv_info["nb"]["photos"] = $pv_info["nb"]["photos"] + $t_object->getRepresentationCount();
 				}
 				// Objets récolés
 				if($recolement->get("recolement", array("convertCodesToDisplayText" => 1)) == "Récolés") {
 					$pv_info["nb"]["objets_recoles"]++;
 				}
 				// Vus/Manquants/Détruits
 				switch($recolement->get("presence_bien", array("convertCodesToDisplayText" => 1))) {
 					case "Vus" :
 						$pv_info["nb"]["objets_vus"]++;
 						break;
 					case "Manquants" :
 						$pv_info["nb"]["objets_manquants"]++;
 						if ($inclure_liste_annexes) {
	 						$pv_info["liste_obj_manquants"] .= 
	 							"[".$recolement->get("ca_objects.related.idno")."] ".$recolement->get("ca_objects.related.preferred_labels")."<w:br/>";
 						}
 						break;
 					case "Non vus" :
 						$pv_info["nb"]["objets_non_vus"]++;
 						if ($inclure_liste_annexes) {
	 						$pv_info["liste_obj_non_vus"] .= 
	 							"[".$recolement->get("ca_objects.related.idno")."] ".$recolement->get("ca_objects.related.preferred_labels")."<w:br/>";
 						}
 						break;
 					case "Détruits" :
 						$pv_info["nb"]["objets_detruits"]++;
 						if ($inclure_liste_annexes) {
	 						$pv_info["liste_obj_detruits"] .= 
	 							"[".$recolement->get("ca_objects.related.idno")."] ".$recolement->get("ca_objects.related.preferred_labels")."<w:br/>";
 						}
 						break;
 				}
 				
 				// Difficulté d'identification
 				if ($recolement->get("identification_pb",array("convertCodesToDisplayText" => 1)) == "oui") {
 					$pv_info["nb"]["identification_pb"]++;
 				}
				// Constat d'état 			
 				$constat_etat_array = $recolement->get("constatEtat",array("returnAsArray" => 1,"convertCodesToDisplayText" => 0));
 				$constat_etat_array = $this->extractFirstElementOfArray($constat_etat_array);
 				$etat_global_type = $constat_etat_array["etat_global"];
 				$pv_info["etat_global"][$etat_global_type]["count"]++;
 				$constat_etat_type = $constat_etat_array["constat_etat"];
 				$pv_info["constatEtat"][$constat_etat_type]["count"]++;
 			}
 			
 			if ($nb_recolements <= $limite_liste_recolements) {
 				$pv_info["liste_objets_html"] = "<table class=\"listtable\">".
 						"<tr><th></th><th>Titre</th><th>Récolé</th><th>Vu/non vu</th><th>Emplacement</th></tr>";
 		 		// Affichage des X premières lignes
 		 		foreach ($campagne->get("ca_occurrences.related.idno",array("returnAsArray" => 1)) as $idno) {
 		 			$recolement = new ca_occurrences();
 		 			$recolement->load(array('idno' => $idno));
 		 			
	 				$line++;
	 				$pv_info["liste_objets_html"] .=
	 				"<tr ".($line == 1 ? " class=odd" : "")."><td><a href=\"".__CA_URL_ROOT__."/index.php/editor/occurrences/OccurrenceEditor/Summary/occurrence_id/".$recolement->get("occurrence_id")."\"><img src=\"".__CA_URL_ROOT__."/themes/default/graphics/buttons/edit.png\"></td>".
					"<td><b>".$recolement->get("preferred_labels")."</b></td>".
					"<td>".$recolement->get("recolement", array("convertCodesToDisplayText" => 1))."</td>".
					"<td>".$recolement->get("presence_bien", array("convertCodesToDisplayText" => 1))."</td>".
					"<td>".$recolement->get("mention_localisation", array("convertCodesToDisplayText" => 1))."</b></td>";
					if ($line==2) $line=0;
 		 		}
 		 		$pv_info["liste_objets_html"] .= "</table>";
 			}
 			/*if ($liste_objets_non_vus) {
	 			print "<table border=1>".$liste_objets_non_vus."</table>";die();
	 		}*/

		    $va_recolements_idnos = $campagne->get("ca_occurrences.related.idno",array("returnAsArray" => 1));
		    $pv_info["info"]["recolements_total"] = count($va_recolements_idnos);
		    $vn_recolements = 0;
		    foreach($va_recolements_idnos as $vs_recolement_idno) {
			    $t_recolement = new ca_occurrences();
			    $t_recolement->load(array('idno' => $vs_recolement_idno));
			    $vs_done = $t_recolement->get('done',array("convertCodesToDisplayText"=>true));
			    if ($vs_done == "oui") $vn_recolements++;
		    }
		    $pv_info["info"]["recolements_done"] = $vn_recolements;

		    //unset($pv_info["liste_objets_html"]);
	 		//var_dump($pv_info);die();
 			return $pv_info;
 		}
 		# -------------------------------------------------------
 		public function Index() {
 			$this->view->setVar('campagnes', $this->opa_infos_campagnes);
 			$this->render('recolement_list_html.php');
 		}
 		# -------------------------------------------------------
 		public function TableauSuivi() {
 			$o_search = new OccurrenceSearch();
 			$qr_hits = $o_search->search("ca_occurrences.type_id:118");
 			while($qr_hits->nextHit()){
 				$idno = $qr_hits->get('ca_occurrences.idno');
 				//print $idno."\n";
 				$campagne = new ca_occurrences();
 				$campagne->load(array('idno' => $idno));
 				$campagnes[$idno]["occurrence_id"] = $campagne->get("occurrence_id");
 				$campagnes[$idno]["localisation"] = $campagne->get("ca_storage_locations.preferred_labels");
 				$campagnes[$idno]["localisation_code"] = $campagne->get("ca_storage_locations.idno");
 				$campagnes[$idno]["caracterisation"] = $campagne->get("campagne_caracterisation",array("convertCodesToDisplayText"=>true));
 				$campagnes[$idno]["champs"] = $campagne->get("campagne_champs_c");
 				$campagnes[$idno]["conditionnement"] = $campagne->get("campagne_conditionnement");
 				$campagnes[$idno]["accessibilite"] = $campagne->get("campagne_accessibilite");
 				$campagnes[$idno]["idno"] = $campagne->get("idno");
 				$campagnes[$idno]["name"] = $campagne->get("preferred_labels");
 				$campagnes[$idno]["date_campagne"] = $campagne->get("date_campagne_c");
 				$campagnes[$idno]["date_campagne_prev"] = $campagne->get("campagne_date_prev");
 				$campagnes[$idno]["intervenants"] = $campagne->get("ca_entities");
 				$campagnes[$idno]["date_campagne_pv"] = $campagne->get("campagne_date_pv");
 				$campagnes[$idno]["nombre"] = count($campagne->get("ca_occurrences.related.idno",array("returnAsArray" => 1)));
 				//var_dump($t_occurrence);die();
 			}
 			//var_dump($campagnes);die();
 			$this->view->setVar('campagnes', $campagnes);
 			$this->render('recolement_tableau_suivi_html.php');
 		}
	    # -------------------------------------------------------
	    public function Pv() {
		    $InfosPv = $this->CalculerPv($_GET["idno"]);
		    if ($InfosPv === false) die("Impossible de récupérer les informations de la campagne de récolement ".$_GET["idno"]);

		    $this->view->setVar('InfosPv', $InfosPv);
		    $this->render('recolement_pv_html.php');
	    }
	    # -------------------------------------------------------
	    private function _createRecolement($vn_object_id = null, $vn_campagne_id = null) {
		    if (!$vn_object_id) return false;

		    $t_rel_types = new ca_relationship_types();
		    $t_list = new ca_lists();
		    $t_locale = new ca_locales();
		    $vn_rel_occ_occ = $t_rel_types->getRelationshipTypeID('ca_occurrences_x_occurrences', 'related');
		    $vn_recole_obj_occ = $t_rel_types->getRelationshipTypeID('ca_objects_x_occurrences', 'recole');
		    $vn_marbre = $t_list->getItemIDFromList('occurrence_types', 'recolement');
		    $pn_locale_id = $t_locale->loadLocaleByCode('fr_FR');

		    $t_object = new ca_objects($vn_object_id);
		    $vs_label = $t_object->get('ca_objects.preferred_labels.name');

		    $t_recolement = new ca_occurrences();
		    $t_recolement->setMode(ACCESS_WRITE);
		    $t_recolement->set('idno', $t_object->get("idno")."RECOL");
		    $t_recolement->set('status', 0);
		    $t_recolement->set('access', 1);
		    $t_recolement->set('type_id', $vn_marbre);


		    $vn_recolement_id = $t_recolement->insert();
		    if ($t_recolement->numErrors()) print "ERROR INSERTING :".join('; ', $t_recolement->getErrors())."\n";

		    // Recopie des attributs Dimensions et Constat d'état depuis l'objet lié au récolement
		    $result = $this->_copyAttributes($t_object,$t_recolement,array('dimensions','constatEtat'));
		    if ($result !== true) {
			    var_dump($result);
			    print "Erreur : la copie d'attributs a échoué.";
			    return false;
		    }

		    if ($vn_campagne_id) $t_recolement->addRelationship('ca_occurrences',$vn_campagne_id,$vn_rel_occ_occ);
		    $t_recolement->addRelationship('ca_objects',$vn_object_id,$vn_recole_obj_occ);
		    $t_recolement->update();
		    $t_recolement->addLabel(array('name' => $vs_label), $pn_locale_id, null, true);
		    $t_recolement->update();
		    if ($t_recolement->numErrors()) {
			    print "ERROR INSERTING :".join('; ', $t_recolement->getErrors())."\n";
			    return false;
		    }

		    return true;
	    }
	    # -------------------------------------------------------
	    public function PreparerCampagne() {

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
		    if(!isset($vs_set_id)) {
			    // select a set
			    // and ask for validation
			    $sets = new ca_sets();
			    $set_search = new SetSearch();
			    $qr_results = $set_search->search("*");    // ... or whatever text you like

			    while($qr_results->nextHit()) {
				    if ($qr_results->get('table_num') == 57) {
					    // if the set contains object (num 57) create an array('id' => 'label',...)
					    $va_setList[$qr_results->get('set_id')]= $qr_results->get('preferred_labels');
				    }
                }
			    $this->view->setVar('SetList', $va_setList);
			    $this->view->setVar('CampagneIdno', $vs_campagne_idno);
			    $this->render('preparer_campagne_select_set_html.php');
		    } else {
			    // do a search and create Recolement for all found objects
			    $set = new ca_sets($vs_set_id);
			    $va_object_ids = $set->getItemRowIDs();
			    foreach($va_object_ids as $vn_object_id=>$nil) {
				    $this->_createRecolement($vn_object_id, $vn_campagne_id);
			    }
			    // do the calc
			    // print the results

			    $va_campagne["occurrence_id"] = $campagne->get("occurrence_id");
			    $va_campagne["localisation"] = $campagne->get("ca_storage_locations.preferred_labels");
			    $va_campagne["localisation_code"] = $campagne->get("ca_storage_locations.idno");
			    $va_campagne["caracterisation"] = $campagne->get("campagne_caracterisation",array("convertCodesToDisplayText"=>true));
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
			    $va_recolements_idnos = $campagne->get("ca_occurrences.related.idno",array("returnAsArray" => 1));
			    $va_campagne["recolements_total"] = count($va_recolements_idnos);
			    $vn_recolements = 0;
			    foreach($va_recolements_idnos as $vs_recolement_idno) {
				    $t_recolement = new ca_occurrences();
				    $t_recolement->load(array('idno' => $vs_recolement_idno));
				    $vs_done = $t_recolement->get('done',array("convertCodesToDisplayText"=>true));
				    if ($vs_done == "oui") $vn_recolements++;
			    }
			    $va_campagne["recolements_done"] = $vn_recolements;

			    $this->view->setVar('RecolementsCrees', count($va_object_ids));
			    $this->view->setVar('Campagne', $va_campagne);
			    $this->render('preparer_campagne_results_html.php');
		    }
	    }
	    # -------------------------------------------------------
 		public function PvWord() {
 			$InfosPv = $this->CalculerPv($_GET["idno"],array("liste_annexes" => true));
 			if ($InfosPv === false) die("Impossible de récupérer les informations de la campagne de récolement ".$_GET["idno"]);
 			$this->view->setVar('InfosPv', $InfosPv);
 			$this->render('recolement_pv_word_html.php');
 		}

		# -------------------------------------------------------
		# Sidebar info handler
		# -------------------------------------------------------
		public function Info($pa_parameters) {
			$this->view->setVar('campagnes', $this->opa_infos_campagnes);
			$this->view->setVar('global', $this->opa_infos_global);
			return $this->render('widget_recolement_info_html.php', true);
		}
 	}
 ?>