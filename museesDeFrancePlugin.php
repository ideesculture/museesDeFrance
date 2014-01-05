<?php

 
	class museesDeFrancePlugin extends BaseApplicationPlugin {
		# -------------------------------------------------------
		private $opo_config;
		private $ops_plugin_path;
		# -------------------------------------------------------
		public function __construct($ps_plugin_path) {
			$this->ops_plugin_path = $ps_plugin_path;
			$this->description = _t('Permet de générer les PV de récolement.');
			parent::__construct();
			$this->opo_config = Configuration::load($ps_plugin_path.'/conf/museesDeFrance.conf');
		}
		# -------------------------------------------------------
		/**
		 * Override checkStatus() to return true - the ampasFrameImporterPlugin plugin always initializes ok
		 */
		public function checkStatus() {
			return array(
				'description' => $this->getDescription(),
				'errors' => array(),
				'warnings' => array(),
				'available' => ((bool)$this->opo_config->get('enabled'))
			);
		}

        # -------------------------------------------------------
        /**
         * Insert into ObjectEditor info (side bar)
         */

        public function hookCanHandleGetAsLinkTarget(array $va_params = array()) {
            return array(1=>"zorg");
        }
        public function hookAppendToEditorInspector(array $va_params = array()) {
            $t_item = $va_params["t_item"];

            // basic zero-level error detection
            if (!isset($t_item)) return false;

            // fetching content of already filled vs_buf_append to surcharge if present (cumulative plugins)
            if (isset($va_params["vs_buf_append"])) {
                $vs_buf = $va_params["vs_buf_append"];
            } else {
                $vs_buf = "";
            }

            $vs_table_name = $t_item->tableName();
            $vn_item_id = $t_item->getPrimaryKey();
            $vn_code = $t_item->getTypeCode();

            $vs_inventaire_url = $this->opo_config->get('ExternalURLInventaire');
            $vs_depot_url = $this->opo_config->get('ExternalURLDepot');

            if ($vs_table_name == "ca_objects") {

                if (in_array($vn_code,$this->opo_config->get('TypesInventaire'))) {
                    // biens acquis
                    $vs_url = $vs_inventaire_url;
                    $vs_inventaire_link_text = "Afficher dans l'inventaire";
                    $vs_action = "afficherObjet/".$vn_item_id;
                } elseif (in_array($vn_code,$this->opo_config->get('TypesEnsembleComplexe'))) {
                    // ensemble complexe
                    $vs_url = $vs_inventaire_url;
                    $vs_inventaire_link_text = "Recopier dans l'inventaire";
                    $vs_action = "afficherEnsembleComplexe/".$vn_item_id;
                } elseif (in_array($vn_code,$this->opo_config->get('TypesDepot'))) {
                    // biens déposés
                    $vs_url = $vs_depot_url;
                    $vs_inventaire_link_text = "Afficher dans le registre des biens&nbsp;déposés";
                    $vs_action = "afficherObjet/".$vn_item_id;
                }

                if ($vs_inventaire_link_text) $vs_buf = "<a href=\"".$vs_url."/".$vs_action."\" style=\"text-decoration:none;margin:10px 0px;display:inline-block;\" target='_blank'>"
                        ."<img src=\"".__CA_URL_ROOT__."/app/plugins/museesDeFrance/views/images/inventaire_16x16.png\" border='0' class='form-button-left' style=\"margin-left:15px;margin-right:10px;\" >"
                        .$vs_inventaire_link_text
                        ."</a>";

            }

            if ($vs_table_name == "ca_sets") {
                // Check if set content is objects from table_num value, 57 = ca_objects, see ca_models/ca_sets.php L.89
                if($t_item->get("table_num") == "57") {
                    $vs_action = "updateSet/".$vn_item_id;

                    $vs_buf = "<div style=\"text-decoration:none;margin:10px 10px;\">"
                          ."<img class='form-button-left' src=\"".__CA_URL_ROOT__."/app/plugins/museesDeFrance/views/images/inventaire_16x16.png\" border='0' class='form-button-left' style=\"margin:5px 10px 50px 0px;\">"
                          ."<a href=\"".$vs_inventaire_url."/".$action."\" target='_blank'>Importer dans l'inventaire des <b>biens affectés</b></a>"
                          ."<hr style=\"border: 1px solid white;\" />"
                          ."<a href=\"".$vs_depot_url."/".$action."\" target='_blank'>Importer dans l'inventaire des <b>biens déposés</b></a>"
                          ."</div>";
                }
            }

            $va_params["caEditorInspectorAppend"] = $vs_buf;
            return $va_params;

        }

            # -------------------------------------------------------
		/**
		 * Insert activity menu
		 */
		public function hookRenderMenuBar($pa_menu_bar) {
			if ($o_req = $this->getRequest()) {
				if (!$o_req->user->canDoAction('can_use_recolementsmf_plugin')) { return true; }
				
				if (isset($pa_menu_bar['recolementsmf_menu'])) {
					$va_menu_items = $pa_menu_bar['recolementsmf_menu']['navigation'];
					if (!is_array($va_menu_items)) { $va_menu_items = array(); }
				} else {
					$va_menu_items = array();
				}
				$va_menu_items['recolementsmf'] = array(
					'displayName' => _t('PV de récolement'),
					"default" => array(
						'module' => 'museesDeFrance',
						'controller' => 'Recolement', 
						'action' => 'Index'
					)
				);
				
				$va_menu_items['smf2'] = array(
					'displayName' => _t("Registre des biens affectés"),
					"default" => array(
						'module' => 'museesDeFrance',
						'controller' => 'External', 
						'action' => 'biens'
					)
				);
				
				$va_menu_items['smf3'] = array(
					'displayName' => _t("Registre des biens déposés"),
					"default" => array(
						'module' => 'museesDeFrance',
						'controller' => 'External', 
						'action' => 'depots'
					)
				);
				
				$pa_menu_bar['recolementsmf_menu'] = array(
					'displayName' => _t("Procédures<br/>réglementaires"),
					'navigation' => $va_menu_items
				);
			} 
			
			return $pa_menu_bar;
		}
		# -------------------------------------------------------
		/**
		 * Add plugin user actions
		 */
		public function hookGetRoleActionList($pa_role_list) {
			$pa_role_list['plugin_recolementsmf'] = array(
				'label' => _t('plugin Récolement SMF'),
				'description' => _t('Actions pour le plugin Récolement SMF'),
				'actions' => museesDeFrancePlugin::getRoleActionList()
			);
	
			return $pa_role_list;
		}

		public function hookRenderWidgets($pa_widgets_config) {
			$pa_widgets_config["museesDeFranceRecolementInfo"] = array(
				"domain" => array(
					"module" => "museesDeFrance",
					"controller"=>"Recolement"),
				"handler"=> array(
					"module" => "museesDeFrance",
					"controller" => "Recolement",
					"action" => 'Info',
					"isplugin" => true),
				"requires" => array(),
				"parameters" => array()
			);
			return $pa_widgets_config;
		}
		# -------------------------------------------------------
		/**
		 * Get plugin user actions
		 */
		static public function getRoleActionList() {
			return array(
				'can_use_recolementsmf_plugin' => array(
					'label' => _t('Peut utiliser les fonctions du plugin Musées de France'),
					'description' => _t('L\'utilisateur peut utiliser les fonctions du plugin Musées de France.')
				)
			);	
		}
		# -------------------------------------------------------
	}
?>