<?php

 	require_once(__CA_LIB_DIR__.'/core/Configuration.php');
 	require_once(__CA_MODELS_DIR__.'/ca_occurrences.php');
 	require_once(__CA_LIB_DIR__.'/ca/Search/OccurrenceSearch.php');

 	class ExternalController extends ActionController {
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
 			
 			$this->opo_config = Configuration::load(__CA_APP_DIR__.'/plugins/recolementSmf/conf/recolementSmf.conf');
 		}
 		# -------------------------------------------------------
 		public function Index() {
 			$this->view->setVar('url', $this->opo_config->get('ExternalURLInventaire'));
 			$this->redirect($this->opo_config->get('ExternalURLInventaire'));
 		}
 		# -------------------------------------------------------
 		public function Biens() {
 			$this->view->setVar('url', $this->opo_config->get('ExternalURLInventaire'));
 			$this->redirect($this->opo_config->get('ExternalURLInventaire'));
 		}
 		# -------------------------------------------------------
 		public function Depots() {
 			$this->view->setVar('url', $this->opo_config->get('ExternalURLDepot'));
 			$this->redirect($this->opo_config->get('ExternalURLDepot'));
		}
 		# -------------------------------------------------------
 	 	public function Inventaire() {
 			$this->view->setVar('url', $this->opo_config->get('ExternalURLInventaire'));
 			$this->redirect($this->opo_config->get('ExternalURLInventaire'));
 		}
 		# -------------------------------------------------------
 	}
 ?>