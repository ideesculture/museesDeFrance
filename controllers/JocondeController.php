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
require_once(__CA_APP_DIR__ . '/plugins/museesDeFrance/lib/JocondeExporter.php');

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

	# -------------------------------------------------------
	public function deleteExport() {
		// Get export name from request
		$exportName = $this->request->getParameter('export', pString);

		$redirectUrl = __CA_URL_ROOT__ . '/index.php/museesDeFrance/Joconde/Index';

		if (empty($exportName)) {
			$this->notification->addNotification(_t("Nom d'export manquant"), __NOTIFICATION_TYPE_ERROR__);
			$this->response->setRedirect($redirectUrl);
			return;
		}

		// Validate export name to prevent directory traversal attacks
		if (strpos($exportName, '..') !== false || strpos($exportName, '/') !== false || strpos($exportName, '\\') !== false) {
			$this->notification->addNotification(_t("Nom d'export invalide"), __NOTIFICATION_TYPE_ERROR__);
			$this->response->setRedirect($redirectUrl);
			return;
		}

		$export_folder = __CA_APP_DIR__."/plugins/museesDeFrance/export-joconde/";
		$export_dir = $export_folder . $exportName;
		$export_zip = $export_folder . $exportName . ".zip";

		// Check if export exists
		if (!is_dir($export_dir) && !file_exists($export_zip)) {
			$this->notification->addNotification(_t("Export introuvable : %1", $exportName), __NOTIFICATION_TYPE_ERROR__);
			$this->response->setRedirect($redirectUrl);
			return;
		}

		$errors = [];

		// Delete directory if exists
		if (is_dir($export_dir)) {
			if (!$this->deleteDirectory($export_dir)) {
				$errors[] = _t("Impossible de supprimer le dossier");
			}
		}

		// Delete ZIP file if exists
		if (file_exists($export_zip)) {
			if (!unlink($export_zip)) {
				$errors[] = _t("Impossible de supprimer le fichier ZIP");
			}
		}

		if (empty($errors)) {
			$this->notification->addNotification(_t("Export %1 supprimé avec succès", $exportName), __NOTIFICATION_TYPE_INFO__);
		} else {
			$this->notification->addNotification(_t("Erreurs lors de la suppression : %1", implode(', ', $errors)), __NOTIFICATION_TYPE_ERROR__);
		}

		$this->response->setRedirect($redirectUrl);
	}

	# -------------------------------------------------------
	/**
	 * Copy file and set proper permissions
	 * @param string $source Source file path
	 * @param string $dest Destination file path
	 * @return bool Success status
	 */
	private function copyWithPermissions($source, $dest) {
		$result = copy($source, $dest);
		if ($result) {
			@chmod($dest, 0664);
		}
		return $result;
	}

	# -------------------------------------------------------
	/**
	 * Recursively delete a directory
	 * @param string $dir Directory path to delete
	 * @return bool Success status
	 */
	private function deleteDirectory($dir) {
		if (!file_exists($dir)) {
			return true;
		}

		if (!is_dir($dir)) {
			// Try to change permissions before deletion
			@chmod($dir, 0664);
			return @unlink($dir);
		}

		// Try to change directory permissions to allow deletion
		@chmod($dir, 0775);

		foreach (scandir($dir) as $item) {
			if ($item == '.' || $item == '..') {
				continue;
			}

			$itemPath = $dir . DIRECTORY_SEPARATOR . $item;

			// Try to change permissions before deletion
			if (is_dir($itemPath)) {
				@chmod($itemPath, 0775);
			} else {
				@chmod($itemPath, 0664);
			}

			if (!$this->deleteDirectory($itemPath)) {
				return false;
			}
		}

		return @rmdir($dir);
	}

	# -------------------------------------------------------
	public function Export() {
		$exporter = new JocondeExporter(
			$this->opo_config,
			__CA_APP_DIR__ . '/plugins/museesDeFrance'
		);
		$result = $exporter->run();

		if (!empty($result['anti_duplicate'])) {
			return $this->render('joconde_deuxieme_export_html.php');
		}
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
