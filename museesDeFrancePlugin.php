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

		if($va_params["caEditorInspectorAppend"]) {
			$vs_buf = $va_params["caEditorInspectorAppend"];
		}
		
		$vs_table_name = $t_item->tableName();
		$vn_item_id = $t_item->getPrimaryKey();
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

            $va_menu_items['smf4'] = array(
                'displayName' => _t("Export Joconde"),
                "default" => array(
                    'module' => 'museesDeFrance',
                    'controller' => 'Joconde',
                    'action' => 'Index'
                )
            );

            if($this->opo_config->get('installProfileThesaurus')) {
                $va_menu_items['smf5'] = array(
                    'displayName' => _t("Installation"),
                    "default" => array(
                        'module' => 'museesDeFrance',
                        'controller' => 'InstallProfileThesaurus',
                        'action' => 'Index'
                    )
                );
            }

			$va_menu_items['smf6'] = array(
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
	
		print "<script type='text/javascript'>".file_get_contents(__CA_APP_DIR__."/plugins/museesDeFrance/assets/js/delimiteur.js")."</script>";
		print "<link rel='stylesheet' href='".__CA_URL_ROOT__."/app/plugins/museesDeFrance/assets/css/delimiteur.css' type='text/css' media='all'> yea";

		// --- Arborescent thesaurus picker (ALL SMFThesaurus InformationService elements) ---
		// Loaded globally; the attach script is a no-op on pages that contain no
		// matching InformationService field. Instead of pinning a single element
		// (174) / thesaurus (th294), we publish a MAP element_id -> thesaurus_id
		// for EVERY datatype=20 metadata element whose service is SMFThesaurus,
		// so the widget attaches to domaine/epoque/fonctions/useMethod and any
		// future SMFThesaurus element. Every thesaurus (th285 included) is served
		// by the SAME "tout client + IndexedDB" path from the static JSON stores
		// under SMF_THESAURUS_BASE_URL; there is no server browse endpoint.
		$vs_mdf_base = __CA_URL_ROOT__."/app/plugins/museesDeFrance/assets";
		$va_thesaurus_map = $this->getSMFThesaurusMap();
		print "<link rel='stylesheet' href='".$vs_mdf_base."/css/smfThesaurusBrowser.css' type='text/css' media='all'>";
		print "<script type='text/javascript'>"
			."window.SMF_THESAURUS_MAP = ".json_encode($va_thesaurus_map, JSON_UNESCAPED_SLASHES).";"
			."window.SMF_THESAURUS_BASE_URL = ".json_encode($vs_mdf_base."/thesauri").";"
			."</script>";
		print "<script type='text/javascript' src='".$vs_mdf_base."/js/smfThesaurusBrowser.js'></script>";
		print "<script type='text/javascript' src='".$vs_mdf_base."/js/smfThesaurusAttach.js'></script>";

		return $pa_menu_bar;
	}

	# -------------------------------------------------------
	/**
	 * Build the element_id -> thesaurus_id map for every InformationService
	 * (datatype 20) metadata element whose service is "SMFThesaurus".
	 *
	 * Candidate element_ids are fetched with a PREPARED query on
	 * ca_metadata_elements (datatype 20 only); the per-element `service` and
	 * `thesaurus` values are then read through the ca_metadata_elements MODEL
	 * API (getSetting), never by unserializing the raw `settings` blob here.
	 *
	 * @return array { "<element_id>": "<thesaurus_id>", ... }
	 */
	private function getSMFThesaurusMap() {
		$va_map = array();
		try {
			if (!class_exists('ca_metadata_elements') && defined('__CA_MODELS_DIR__')) {
				require_once(__CA_MODELS_DIR__ . '/ca_metadata_elements.php');
			}
			$o_db = new Db();
			// datatype 20 = InformationService (see attribute_types.conf).
			$qr = $o_db->query(
				"SELECT element_id FROM ca_metadata_elements WHERE datatype = ?",
				array(20)
			);
			if (!$qr) { return $va_map; }

			while ($qr->nextRow()) {
				$vn_element_id = (int) $qr->get('element_id');
				if ($vn_element_id <= 0) { continue; }

				$t_element = new ca_metadata_elements($vn_element_id);
				if (!$t_element->getPrimaryKey()) { continue; }

				$vs_service = trim((string) $t_element->getSetting('service'));
				if ($vs_service !== 'SMFThesaurus') { continue; }

				$vs_thesaurus = trim((string) $t_element->getSetting('thesaurus'));
				if ($vs_thesaurus === '' || !preg_match('/^th[0-9]+$/', $vs_thesaurus)) { continue; }

				$va_map[(string) $vn_element_id] = $vs_thesaurus;
			}
		} catch (Exception $e) {
			// Non-fatal: on any failure the widget simply attaches to nothing.
			return array();
		}
		return $va_map;
	}
	# -------------------------------------------------------

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
