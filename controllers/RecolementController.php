<?php

 	require_once(__CA_LIB_DIR__.'/core/Configuration.php');
 	require_once(__CA_MODELS_DIR__.'/ca_occurrences.php');
 	require_once(__CA_LIB_DIR__.'/ca/Search/OccurrenceSearch.php');

 	class RecolementController extends ActionController {
 		# -------------------------------------------------------
 		protected $opo_config;		// plugin configuration file
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
	 		
	 		//unset($pv_info["liste_objets_html"]);
	 		//var_dump($pv_info);die();
 			return $pv_info;
 		}
 		# -------------------------------------------------------
 		public function Index() {
 			$o_search = new OccurrenceSearch();
 			$qr_hits = $o_search->search("ca_occurrences.type_id:118");
 			while($qr_hits->nextHit()){
 				$idno = $qr_hits->get('ca_occurrences.idno');
 				//print $idno."\n";
 				$campagne = new ca_occurrences();
 				$campagne->load(array('idno' => $idno));
 				$campagnes[$idno][idno] = $campagne->get("idno");
 				$campagnes[$idno][name] = $campagne->get("preferred_labels");
 				$campagnes[$idno][date_campagne] = $campagne->get("date_campagne_c");
 				//var_dump($t_occurrence);die();
 			}
 			//var_dump($campagnes);die();
 			$this->view->setVar('campagnes', $campagnes);
 			$this->render('recolement_list_html.php');
 		}
 		# -------------------------------------------------------
 		public function Pv() {
 			$InfosPv = $this->CalculerPv($_GET["idno"]);
 			if ($InfosPv === false) die("Impossible de récupérer les informations de la campagne de récolement ".$_GET["idno"]);
 			$this->view->setVar('InfosPv', $InfosPv);
 			$this->render('recolement_pv_html.php');
 		}
 		# -------------------------------------------------------
 		public function PvWord() {
 			$InfosPv = $this->CalculerPv($_GET["idno"],array("liste_annexes" => true));
 			if ($InfosPv === false) die("Impossible de récupérer les informations de la campagne de récolement ".$_GET["idno"]);
 			$this->view->setVar('InfosPv', $InfosPv);
 			$this->render('recolement_pv_word_html.php');
 		}
 	}
 ?>