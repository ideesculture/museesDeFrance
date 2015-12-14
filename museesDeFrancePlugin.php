<?php


class museesDeFrancePlugin extends BaseApplicationPlugin
{
	# -------------------------------------------------------
	private $opo_config;
	private $ops_plugin_path;

	# -------------------------------------------------------
	public function __construct($ps_plugin_path)
	{
		$this->ops_plugin_path = $ps_plugin_path;
		$this->description = _t('Fonctionnalités pour les musées labellisés Musées de France par le Ministère de la Culture français.');
		parent::__construct();
		$ps_plugin_path = __CA_BASE_DIR__ . "/app/plugins/museesDeFrance";

		if (file_exists($ps_plugin_path . '/conf/local/museesDeFrance.conf')) {
			$this->opo_config = Configuration::load($ps_plugin_path . '/conf/local/museesDeFrance.conf');
		} else {
			$this->opo_config = Configuration::load($ps_plugin_path . '/conf/museesDeFrance.conf');
		}
	}
	# -------------------------------------------------------
	/**
	 * Override checkStatus() to return true - the ampasFrameImporterPlugin plugin always initializes ok
	 */
	public function checkStatus()
	{
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
	public function hookAppendToEditorInspector(array $va_params = array())
	{
        MetaTagManager::addLink('stylesheet', __CA_URL_ROOT__."/app/plugins//museesDeFrance/assets/css/museesDeFrance.css",'text/css');

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
		if(method_exists($t_item,"getTypeCode")) {
			$vn_code = $t_item->getTypeCode();



			if ($vs_table_name == "ca_objects") {

				$vs_inventaire_url = caNavUrl($this->getRequest(), "museesDeFrance", "InventaireBiensAffectes", "Transfer", array("id"=>$vn_item_id));
				$vs_depot_url = caNavUrl($this->getRequest(), "museesDeFrance", "InventaireBiensDeposes", "Transfer", array("id"=>$vn_item_id));

				if (in_array($vn_code, $this->opo_config->get('TypesInventaire'))) {
					// biens acquis
					$vs_inventaire_link_text_affectes = "Afficher dans l'inventaire";

				} elseif (in_array($vn_code, $this->opo_config->get('TypesEnsembleComplexe'))) {
					// ensemble complexe
					$vs_inventaire_link_text = "Recopier dans l'inventaire";
				} elseif (in_array($vn_code, $this->opo_config->get('TypesDepot'))) {
					// biens déposés
					$vs_inventaire_link_text_deposes = "Afficher dans le registre des biens&nbsp;déposés";
				}

				if ($vs_inventaire_link_text_affectes)
					$vs_buf = "<div style=\"text-align:center;width:100%;margin-top:10px;\">"
						. "<a href=\"" . $vs_inventaire_url . "\" class='form-button-gradient'>"
						. "<img class='form-button-left' src=\"" . __CA_URL_ROOT__ . "/app/plugins/museesDeFrance/views/images/inventaire_16x16.png\" border='0'>"
						. $vs_inventaire_link_text_affectes
						. "</a></div>";
				if ($vs_inventaire_link_text_deposes)
					$vs_buf = "<div style=\"text-align:center;width:100%;margin-top:10px;\">"
						. "<a href=\"" . $vs_depot_url . "\" class='form-button-gradient'>"
						. "<img class='form-button-left' src=\"" . __CA_URL_ROOT__ . "/app/plugins/museesDeFrance/views/images/inventaire_16x16.png\" border='0'>"
						. $vs_inventaire_link_text_deposes
						. "</a></div>";

			}

			if ($vs_table_name == "ca_sets") {

				$vs_inventaire_url = caNavUrl($this->getRequest(), "museesDeFrance", "InventaireBiensAffectes", "TransferSet", array("id"=>$vn_item_id));
				$vs_depot_url = caNavUrl($this->getRequest(), "museesDeFrance", "InventaireBiensDeposes", "TransferSet", array("id"=>$vn_item_id));

				// Check if set content is objects from table_num value, 57 = ca_objects, see ca_models/ca_sets.php L.89
				if ($t_item->get("table_num") == "57") {
					$vs_action = "updateSet/" . $vn_item_id;

					$vs_buf = "<div style=\"text-align:center;width:100%;margin-top:10px;\">"
						. "<a href=\"" . $vs_inventaire_url . "\" class='form-button-gradient'>"
						. "<img class='form-button-left' src=\"" . __CA_URL_ROOT__ . "/app/plugins/museesDeFrance/views/images/inventaire_16x16.png\" border='0'>"
						. "Importer dans l'inventaire<br/> des <b>biens affectés</b>"
						. "</a><a href=\"" . $vs_depot_url . "\" class='form-button-gradient'>"
						. "<img class='form-button-left' src=\"" . __CA_URL_ROOT__ . "/app/plugins/museesDeFrance/views/images/inventaire_16x16.png\" border='0'>"
						. "Importer dans l'inventaire<br/> des <b>biens déposés</b>"
						. "</a></div>";
				}
			}

			$va_params["caEditorInspectorAppend"] = $vs_buf;
		}

		return $va_params;

	}

	# -------------------------------------------------------
	/**
	 * Insert activity menu
	 */
	public function hookRenderMenuBar($pa_menu_bar)
	{
		if ($o_req = $this->getRequest()) {
			if (!$o_req->user->canDoAction('can_use_recolementsmf_plugin')) {
				return true;
			}

			if (isset($pa_menu_bar['recolementsmf_menu'])) {
				$va_menu_items = $pa_menu_bar['recolementsmf_menu']['navigation'];
				if (!is_array($va_menu_items)) {
					$va_menu_items = array();
				}
			} else {
				$va_menu_items = array();
			}
			$va_menu_items['recolementsmf'] = array(
				'displayName' => _t('Suivi du récolement'),
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
                    'controller' => 'InventaireBiensAffectes',
                    'action' => 'Index'
                )
            );

            $va_menu_items['smf3'] = array(
                'displayName' => _t("Registre des biens déposés"),
                "default" => array(
                    'module' => 'museesDeFrance',
                    'controller' => 'InventaireBiensDeposes',
                    'action' => 'Index'
                )
            );

            if($this->opo_config->get('installProfileThesaurus')) {
                $va_menu_items['smf4'] = array(
                    'displayName' => _t("Installation"),
                    "default" => array(
                        'module' => 'museesDeFrance',
                        'controller' => 'InstallProfileThesaurus',
                        'action' => 'Index'
                    )
                );
            }

			$va_menu_items['smf5'] = array(
				'displayName' => _t("A propos"),
				"default" => array(
					'module' => 'museesDeFrance',
					'controller' => 'InventaireBiensAffectes',
					'action' => 'About'
				)
			);

			$pa_menu_bar['recolementsmf_menu'] = array(
				'displayName' => _t("Procédures<br/>réglementaires"),
				'navigation' => $va_menu_items
			);
		}

		return $pa_menu_bar;
	}

	public function hookRenderWidgets($pa_widgets_config)
	{
		$pa_widgets_config["museesDeFranceRecolementInfo"] = array(
			"domain" => array(
				"module" => "museesDeFrance",
				"controller" => "Recolement"),
			"handler" => array(
				"module" => "museesDeFrance",
				"controller" => "Recolement",
				"action" => 'Info',
				"isplugin" => true),
			"requires" => array(),
			"parameters" => array()
		);
		$pa_widgets_config["museesDeFranceInventaireBiensAffectesInfo"] = array(
			"domain" => array(
				"module" => "museesDeFrance",
				"controller" => "InventaireBiensAffectes"),
			"handler" => array(
				"module" => "museesDeFrance",
				"controller" => "InventaireBiensAffectes",
				"action" => 'Info',
				"isplugin" => true),
			"requires" => array(),
			"parameters" => array()
		);
		$pa_widgets_config["museesDeFranceInventaireBiensDeposesInfo"] = array(
			"domain" => array(
				"module" => "museesDeFrance",
				"controller" => "InventaireBiensDeposes"),
			"handler" => array(
				"module" => "museesDeFrance",
				"controller" => "InventaireBiensDeposes",
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
				'label' => "Can use MuseesDeFrance plugin",
				'description' => "Can use MuseesDeFrance plugin"
			),
		);
	}

	# -------------------------------------------------------
	/**
	 * Add plugin user actions
	 */
	public function hookGetRoleActionList($pa_role_list) {
		$pa_role_list['plugin_museesDeFrancePlugin'] = array(
			'label' => _t('Plugin MuseesDeFrance'),
			'description' => _t('Actions pour le plugin MuseesDeFrance'),
			'actions' => museesDeFrancePlugin::getRoleActionList()
		);

		return $pa_role_list;
	}
}

?>