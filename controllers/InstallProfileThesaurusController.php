<?php

// Affiche les messages de débuggage
$DEBUG = false;
// Affiche plus d'informations à l'écran
$VERBOSE = false;

// Limitation en nb de lignes des traitements de fichier pour le débuggage
$limitation_fichier = 0;

// Désactivation de l'indexation pour la recherche
//define("__CA_DONT_DO_SEARCH_INDEXING__", true);

require_once(__CA_LIB_DIR__ . '/core/Configuration.php');
// Inclusions nécessaires des fichiers de providence
//require_once(__CA_LIB_DIR__.'/core/Db.php');
require_once(__CA_MODELS_DIR__."/ca_storage_locations.php");
require_once(__CA_MODELS_DIR__ . '/ca_objects.php');
require_once(__CA_MODELS_DIR__ . '/ca_occurrences.php');
require_once(__CA_MODELS_DIR__."/ca_entities.php");
require_once(__CA_MODELS_DIR__."/ca_users.php");
require_once(__CA_MODELS_DIR__."/ca_lists.php");
require_once(__CA_MODELS_DIR__."/ca_list_items.php");
require_once(__CA_MODELS_DIR__."/ca_locales.php");
require_once(__CA_MODELS_DIR__."/ca_collections.php");
require_once(__CA_LIB_DIR__.'/core/Parsers/DelimitedDataParser.php');

/*
 * Helpers
 */ 
require_once(__CA_APP_DIR__."/helpers/utilityHelpers.php");
// defines __CA_MDF_THESAURI__ informations on available thesauri
require_once(__CA_BASE_DIR__.'/app/plugins/museesDeFrance/helpers/ThesaurusDMF.php');

require_once(__CA_BASE_DIR__.'/app/plugins/museesDeFrance/lib/migration_functionlib.php');

/**
 * Class InstallProfileThesaurusController
 */
class InstallProfileThesaurusController extends ActionController
{
	# -------------------------------------------------------

    // Helper for thesaurus files informations : filename reference, nb of empty lines at its start, etc.
    protected $opo_config; // plugin configuration file
	protected $opa_infos_campagnes_par_recolement_decennal;


	# -------------------------------------------------------
	# InstallProfileThesaurusController constructor
	# -------------------------------------------------------
    /**
     * @param RequestHTTP $po_request
     * @param ResponseHTTP $po_response
     * @param null $pa_view_paths
     */
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
	}

    /****************************************************************
     * Fonction de traitement des fichiers de liste
     ****************************************************************/
    public function traiteFichierDMF($t_filename,$t_idno_prefix,$t_list_description,$nb_lignes_vides=0,$ligne_limite=0, $pourcentage=100, $pourcentage_debut=0) {
        $thescode = str_replace("lex","",$t_idno_prefix);

        global $pn_locale_id, $VERBOSE, $DEBUG;

        $t_locale = new ca_locales();
        $pn_locale_id = $t_locale->loadLocaleByCode('fr_FR');		// default locale_id
        $t_list = new ca_lists();

        $vn_list_item_type_concept = $t_list->getItemIDFromList('list_item_types', 'concept');
        $vn_list_item_label_synonym = $t_list->getItemIDFromList('list_item_label_types', 'uf');
        $vn_place_other= $t_list->getItemIDFromList('places_types', 'other');

        $result= 0;
        $row = 1;
        $parent = array ();
        $nb_tab_pre=0;

        $explode_separator_array = array();
        $explode_separator_array[1]["separator"]=" = ";
        $explode_separator_array[1]["label_type"]=$vn_list_item_label_synonym;

        $t_filename = __CA_BASE_DIR__."/app/plugins/museesDeFrance/assets/thesaurus/txt/".$t_filename;
        if (($handle = fopen($t_filename, "r")) !== FALSE) {
            if (!$vn_list_id=getListID($t_list,"dmf_".$t_idno_prefix,$t_list_description)) {
                print json_encode("Impossible de trouver la liste dmf_".$t_idno_prefix." !.\n");
                die();
            } else {
                //print "{ 'thesaurus' : 'Liste dmf_".$t_idno_prefix." : $vn_list_id'";
            }
            $contenu_fichier = file_get_contents($t_filename);
            $total=substr_count($contenu_fichier, "\n")+1;
            $contenu_fichier="";

            $data="";
            $parent_selected=0;

            // Tableau pour conserver une trace des codes identiques et suffixer par un nombre : domn_amerique3 par exemple
            $code_counter = array();
            setlocale(LC_CTYPE, 'en_US');

            while (($data = fgets($handle)) !== FALSE) {
                $libelle = str_replace("\t", "", $data);
                $libelle = str_replace("\n", "", $libelle);
                $libelle = str_replace("\r", "", $libelle);

                $libelles = explode(" = ",$libelle);
                $encoded_libelle = caRemoveAccents($libelles[0]);
                $encoded_libelle=preg_replace('/[^a-z\d]+/i', '_', $encoded_libelle);

                //var_dump($encoded_libelle);die();
                if (strlen($encoded_libelle) <= 30 ) {
                    $identifier = $thescode."_".$encoded_libelle;
                } else {
                    $encoded_libelle = substr($encoded_libelle,0,30);
                    if (!isset($code_counter[$encoded_libelle])) {
                        $code_counter[$encoded_libelle]=1;
                    }
                    $identifier = $thescode."_".$encoded_libelle.$code_counter[$encoded_libelle];
                    $code_counter[$encoded_libelle]++;
                }

                // comptage du nb de tabulation pour connaître le terme parent
                $nb_tab = substr_count($data,"\t");
                $row++;

                // Si aucune information n'est à afficher, on affiche une barre de progression
                //if ((!$DEBUG) && (!$VERBOSE)) {
                //    show_status($row, $total);

                if ($row % 5 == 0) {
                    $d = array('thesaurus' => "Liste dmf_".$t_idno_prefix , 'progress' => $pourcentage_debut+round($pourcentage*$row/$total,2));
                    echo json_encode($d) . PHP_EOL;
                    ob_flush();
                    flush();
                }

                //sleep(1);
                //print ",\n{ 'progression' : '".."'}";
                //}

                if (($row > $nb_lignes_vides + 1) && ($libelle !="")) {

                    if ($row == $ligne_limite) {
                        //print ",\n{ 'limite atteinte' : '".$ligne_limite."' }";
                        break;
                        //die();
                    }

                    // si plus d'une tabulation
                    if (($nb_tab_pre != $nb_tab) && ($nb_tab > 0)) {
                        $parent_selected=$parent[$nb_tab - 1];
                    } elseif ($nb_tab == 0) {
                        $parent_selected=0;
                    }

                    // insertion dans la liste
                    if ($vn_item_id=getItemID($t_list,$vn_list_id,$vn_list_item_type_concept,$identifier,$libelle,"",1,0, $parent_selected, $row - $nb_lignes_vides, $explode_separator_array )) {
                        //if ($VERBOSE) print "LIST ITEM CREATED : ".$libelle."";
                    } else {
                        //print ",\n{ 'LIST ITEM CREATION FAILED' : '".$libelle."'}";
                        die();
                    }

                    //print $nb_tab_pre." ".$nb_tab." - parent :".$parent_selected." ".$lexutil;
                    // si au moins 1 tabulation, conservation de l'item pour l'appeler comme parent
                    // $vn_item_id=$nb_tab;
                    $parent[$nb_tab]=$vn_item_id;

                }

                $nb_tab_pre=$nb_tab;
            }
            fclose($handle);
            //if ($VERBOSE) { print "dmf_".$t_idno_prefix." treated.\n";}
            $d = array('thesaurus' => "Liste dmf_".$t_idno_prefix , 'progress' => $pourcentage_debut+$pourcentage);
            echo json_encode($d) . PHP_EOL;
            ob_flush();
            flush();

            $result = true;
            //print "\n}";
            //die();
        } else {
            //print "le fichier n'a pu être ouvert.";
            $result=false;
        }
        return $result;
    }

    /************************************************************************************************
     * Fonction de nettoyage des listes : déplacement au premier niveau sous __ des termes utilisés
     ***********************************************************************************************/
    public function moveUsedTermsDMF($thesaurus_code, $pourcentage=100, $pourcentage_debut=0) {

        $t_locale = new ca_locales();
        $pn_locale_id = $t_locale->loadLocaleByCode('fr_FR');		// default locale_id
        $t_list = new ca_lists();

        $vn_list_item_type_concept = $t_list->getItemIDFromList('list_item_types', 'concept');
        $vn_list_item_label_synonym = $t_list->getItemIDFromList('list_item_label_types', 'uf');

        if (!$vn_list_id=getListID($t_list,"dmf_".$thesaurus_code,"")) {
            print json_encode("Impossible de trouver la liste dmf_".$thesaurus_code." !.\n");
            die();
        }

        // Searching or creating parent for used terms
        $vn_underscoreunderscore_id=getItemID($t_list,$vn_list_id,$vn_list_item_type_concept,"__used_terms","ALIGN : used_terms","",1,0, 0, null);

        $o_data = new Db();
        $vs_request = "select distinct ca_list_items.item_id from ca_list_labels LEFT OUTER JOIN ca_lists ON ca_list_labels.list_id = ca_lists.list_id
                 LEFT OUTER JOIN ca_list_items ON ca_lists.list_id = ca_list_items.list_id
                 LEFT OUTER JOIN ca_attribute_values ON ca_list_items.item_id = ca_attribute_values.item_id
                 LEFT OUTER JOIN ca_list_item_labels ON ca_list_item_labels.item_id = ca_attribute_values.item_id
                 LEFT OUTER JOIN ca_attributes ON ca_attribute_values.attribute_id = ca_attributes.attribute_id
            WHERE table_num = 57 AND ca_list_labels.list_id = $vn_list_id;";
        $qr_c = $o_data->query($vs_request);
        $vn_numrows = $qr_c->numRows();
        $va_results = $qr_c->getAllRows();
        $i=0;
        foreach($va_results as $va_result) {
            // Progress
            $d = array('thesaurus' => "Liste dmf_".$thesaurus_code , 'progress' => $pourcentage_debut+round($pourcentage*$i/$vn_numrows,2));
            echo json_encode($d) . PHP_EOL;
            ob_flush();
            flush();

            $vt_list_item = new ca_list_items($va_result["item_id"]);
            $vt_list_item->setMode(ACCESS_WRITE);
            $vt_list_item->set(array("parent_id"=>$vn_underscoreunderscore_id));
            $vt_list_item->update();
            $i++;
        }
        // Progress end
        $d = array('thesaurus' => "Liste dmf_".$thesaurus_code , 'progress' => $pourcentage_debut+$pourcentage);
        echo json_encode($d) . PHP_EOL;
        ob_flush();
        flush();

        return true;
    }

    /************************************************************************************************
     * Fonction de nettoyage des listes : déplacement au premier niveau sous __ des termes utilisés
     ***********************************************************************************************/
    public function reaffectTermsDMF($thesaurus_code, $pourcentage=100, $pourcentage_debut=0) {

        $t_locale = new ca_locales();
        $pn_locale_id = $t_locale->loadLocaleByCode('fr_FR');       // default locale_id
        $t_list = new ca_lists();

        $vn_list_item_type_concept = $t_list->getItemIDFromList('list_item_types', 'concept');
        $vn_list_item_label_synonym = $t_list->getItemIDFromList('list_item_label_types', 'uf');

        if (!$vn_list_id=getListID($t_list,"dmf_".$thesaurus_code,"")) {
            print json_encode("Impossible de trouver la liste dmf_".$thesaurus_code." !.\n");
            die();
        }

        // Searching or creating parent for used terms
        $vn_underscoreunderscore_id=getItemID($t_list,$vn_list_id,$vn_list_item_type_concept,"__used_terms","ALIGN : used_terms","",1,0, 0, null);

        $o_data = new Db();

        $vs_request = "
            select cali.item_id as 'old', calil.`name_singular`, calil2.name_singular, cali2.item_id as 'new', cali2.list_id from ca_list_items cali left join ca_list_item_labels calil on cali.item_id=calil.item_id left join ca_list_item_labels calil2 on calil2.item_id !=cali.item_id and calil2.name_singular=calil.name_singular join ca_list_items cali2 on cali2.item_id=calil2.item_id and cali2.list_id=$vn_list_id WHERE cali.parent_id = $vn_underscoreunderscore_id;";

        $qr_c = $o_data->query($vs_request);
        $vn_numrows = $qr_c->numRows();
        $va_results = $qr_c->getAllRows();

        $i=0;
        foreach($va_results as $va_result) {
            // Progress
            $d = array('thesaurus' => "Liste dmf_".$thesaurus_code , 'progress' => $pourcentage_debut+round($pourcentage*$i/$vn_numrows,2));
            echo json_encode($d) . PHP_EOL;
            ob_flush();
            flush();

            $vt_list_item = new ca_list_items($va_result["old"]);
            $vt_list_item->setMode(ACCESS_WRITE);

            // update relationships
            $va_tables = array(
                'ca_objects', 'ca_entities', 'ca_places', 'ca_occurrences', 'ca_collections', 'ca_storage_locations', 'ca_list_items', 'ca_loans', 'ca_movements', 'ca_tours', 'ca_tour_stops', 'ca_object_representations', 'ca_list_items'
            );
            foreach($va_tables as $vs_table) {
                $results = $vt_list_item->moveRelationships($vs_table, $va_result["new"]);
            }

            // update existing metadata attributes to use remapped value
            $results = $vt_list_item->moveAuthorityElementReferences($$va_result["new"]);

            
            // update simple attributes
            $vs_cleanup_request = "
            update ca_attribute_values set item_id=".$va_result["new"].", value_longtext1=".$va_result["new"]."
            where item_id=".$va_result["old"];
            $qr_cleanup_c = $o_data->query($vs_cleanup_request);

            // delete old values
            $vt_list_item->delete(true);
            $i++;
        }
        // Progress end
        $d = array('thesaurus' => "Liste dmf_".$thesaurus_code , 'progress' => $pourcentage_debut+$pourcentage);
        echo json_encode($d) . PHP_EOL;
        ob_flush();
        flush();

        return true;
    }

    /************************************************************************************************
     * Fonction de nettoyage des listes : déplacement au premier niveau sous __ des termes utilisés
     ***********************************************************************************************/
    public function deleteUnusedTermsDMF($thesaurus_code, $pourcentage=100, $pourcentage_debut=0) {

        $t_locale = new ca_locales();
        $pn_locale_id = $t_locale->loadLocaleByCode('fr_FR');		// default locale_id
        $t_list = new ca_lists();

        $vn_list_item_type_concept = $t_list->getItemIDFromList('list_item_types', 'concept');
        $vn_list_item_label_synonym = $t_list->getItemIDFromList('list_item_label_types', 'uf');

        if (!$vn_list_id=getListID($t_list,"dmf_".$thesaurus_code,"")) {
            print json_encode("Impossible de trouver la liste dmf_".$thesaurus_code." !.\n");
            die();
        }

        // Searching or creating parent for used terms
        $vn_underscoreunderscore_id=getItemID($t_list,$vn_list_id,$vn_list_item_type_concept,"__used_terms","ALIGN : used_terms","",1,0, 0, null);

        $o_data = new Db();
        $vs_request = "select item_id from ca_list_items
                 WHERE list_id = $vn_list_id AND parent_id IS NOT NULL
                 AND item_id != $vn_underscoreunderscore_id
                 AND item_id not in (select distinct ca_list_items.item_id from ca_list_labels
                  LEFT OUTER JOIN ca_lists ON ca_list_labels.list_id = ca_lists.list_id
                  LEFT OUTER JOIN ca_list_items ON ca_lists.list_id = ca_list_items.list_id
                  LEFT OUTER JOIN ca_attribute_values ON ca_list_items.item_id = ca_attribute_values.item_id
                  LEFT OUTER JOIN ca_list_item_labels ON ca_list_item_labels.item_id = ca_attribute_values.item_id
                  LEFT OUTER JOIN ca_attributes ON ca_attribute_values.attribute_id = ca_attributes.attribute_id
                  WHERE table_num = 57 AND ca_list_labels.list_id = $vn_list_id
                 )";

        $qr_c = $o_data->query($vs_request);

        $vn_numrows = $qr_c->numRows();
        $va_results = $qr_c->getAllRows();
        $i=0;
        foreach($va_results as $va_result) {
            // Progress
            $d = array('thesaurus' => "Suppression non utilisés de la liste dmf_".$thesaurus_code , 'progress' => $pourcentage_debut + round($pourcentage*$i/$vn_numrows,2));
            echo json_encode($d) . PHP_EOL;
            ob_flush();
            flush();

            $vt_list_item = new ca_list_items($va_result["item_id"]);
            $vt_list_item->setMode(ACCESS_WRITE);
            $vt_list_item->delete(true);
            $i++;
        }
        // Progress end
        $d = array('thesaurus' => "Suppression non utilisés de la liste dmf_".$thesaurus_code , 'progress' => $pourcentage_debut+$pourcentage);
        echo json_encode($d) . PHP_EOL;
        ob_flush();
        flush();

        return true;
    }


    /****************************************************************
     * Fonction de traitement du fichier de lieux
     ****************************************************************/
    private function traiteFichierLieuDMF($t_filename,$t_idno_prefix,$nb_lignes_vides=0,$ligne_limite=0) {
        global $pn_locale_id, $VERBOSE, $DEBUG;
        global $vn_list_item_type_concept,$vn_list_item_label_synonym,$vn_place_other;
        global $t_list;

        $result= 0;
        $row = 1;
        $parent = array ();
        $nb_tab_pre=0;

        $explode_separator_array = array();
        $explode_separator_array[1]["separator"]=" = ";
        $explode_separator_array[1]["label_type"]=$vn_list_item_label_synonym;

        print "traitement des lieux\n";
        print __CA_BASE_DIR__."/app/plugins/museesDeFrance/assets/thesaurus/txt/".$t_filename."<br/>";die();
        if (($handle = fopen(__CA_BASE_DIR__."/app/plugins/museesDeFrance/assets/thesaurus/txt/".$t_filename, "r")) !== FALSE) {
            $contenu_fichier = file_get_contents($t_filename);
            $total=substr_count($contenu_fichier, "\n");
            $contenu_fichier="";

            $data="";
            $parent_selected=1;

            while (($data = fgets($handle)) !== FALSE) {
                $libelle = str_replace("\t", "", $data);
                $libelle = str_replace("\r\n", "", $libelle);

                // comptage du nb de tabulation pour connaître le terme parent
                $nb_tab = substr_count($data,"\t");
                $row++;

                // Si aucune information n'est à afficher, on affiche une barre de progression
                //if ((!$DEBUG) && (!$VERBOSE)) {
                //    show_status($row, $total);
                //}

                if (($row > $nb_lignes_vides + 1) && ($libelle !="")) {

                    if ($row == $ligne_limite) {
                        print "limite atteinte : ".$ligne_limite." \n";
                        break;
                        //die();
                    }

                    // si plus d'une tabulation
                    if (($nb_tab_pre != $nb_tab) && ($nb_tab > 0)) {
                        $parent_selected=$parent[$nb_tab - 1];
                    } elseif ($nb_tab == 0) {
                        $parent_selected=1;
                    }

                    // débuggage
                    if ($DEBUG) print "(".$parent_selected.") ".$nb_tab." ".$libelle;

                    // insertion dans la liste
                    if ($vn_place_id=getPlaceID($libelle, $t_idno_prefix."_".($row-$nb_lignes_vides), $vn_place_other, $parent_selected, $explode_separator_array)) {
                    } else {
                        print "PLACE CREATION FAILED : ".$libelle." ";
                        die();
                    }

                    $parent[$nb_tab]=$vn_place_id;

                }

                $nb_tab_pre=$nb_tab;
            }
            fclose($handle);
            if ($VERBOSE) { print "dmf_".$t_idno_prefix." treated.\n";}
            $result = true;
        } else {
            print "le fichier n'a pu être ouvert.";
            $result=false;
        }
        return $result;
    }

	# -------------------------------------------------------
    /**
     *
     */
    public function Index()
	{
		//$this->view->setVar('campagnes', $this->opa_infos_campagnes);
        if(!is_file(__CA_BASE_DIR__."/install/profiles/xml/joconde-sans-thesaurus.xml")) {
            $this->view->setVar('joconde_available', "false");
        }
		$this->render('index_install_profile_thesaurus_html.php');
	}

	# -------------------------------------------------------
    /**
     *
     */
    public function Profile()
	{
        if(!is_file(__CA_BASE_DIR__."/install/profiles/xml/joconde-sans-thesaurus.xml")) {
            $this->view->setVar('joconde_available', "false");
            if(!copy(__CA_BASE_DIR__."/app/plugins/museesDeFrance/assets/profile/joconde-sans-thesaurus-archives-documentation.xml",__CA_BASE_DIR__."/install/profiles/xml/joconde-sans-thesaurus.xml")) {
                $this->view->setVar('joconde_installed', "false");
            } else {
                $this->view->setVar('joconde_installed', "true");
            }
        } else {
            $this->view->setVar('joconde_available', "true");
        }

		$this->render('install_profile_html.php');
	}
	# -------------------------------------------------------
    /**
     *
     */
    public function Thesaurus()
	{
		$this->view->setVar('variable', "value");
		$this->render('install_thesaurus_html.php');
	}

    /**
     *
     */
    public function Align()
    {
        $this->view->setVar('variable', "value");
        $this->render('align_thesaurus_html.php');
    }

    /**
     *
     */
    public function ThesaurusImportAjax()
    {
        if (__CA_MDF_THESAURI__[$_GET["thesaurus"]] !== null) return false;

        global $limitation_fichier;
        //type octet-stream. make sure apache does not gzip this type, else it would get buffered
        header('Content-Type: text/octet-stream');
        header('Cache-Control: no-cache'); // recommended to prevent caching of event data.

        $vs_thes_code = $_GET["thesaurus"];

        if($vs_thes_code=="lexlieux") {
                $this->traiteFichierLieuDMF(
                    __CA_MDF_THESAURI__[$vs_thes_code]["filename"],
                    $vs_thes_code,
                    __CA_MDF_THESAURI__[$vs_thes_code]["ignoreFirstLines"],
                    $limitation_fichier
                );            
        } else {
                $this->traiteFichierDMF(
                    __CA_MDF_THESAURI__[$vs_thes_code]["filename"],
                    $vs_thes_code,
                    __CA_MDF_THESAURI__[$vs_thes_code]["label"],
                    __CA_MDF_THESAURI__[$vs_thes_code]["ignoreFirstLines"],
                    $limitation_fichier
                );
        }

        exit();
    }

    /**
     *
     */
    public function ThesaurusAlignAjax()
    {

        $vs_thes_code = $_GET["thesaurus"];

        if (__CA_MDF_THESAURI__[$_GET["thesaurus"]] !== null) return false;
        
        // This won't do the trick for places thesaurus, so returning false
        if ($vs_thes_code == "lexlieux") return false;

        global $limitation_fichier;
        //type octet-stream. make sure apache does not gzip this type, else it would get buffered
        header('Content-Type: text/octet-stream');
        header('Cache-Control: no-cache'); // recommended to prevent caching of event data.

        $this->moveUsedTermsDMF($vs_thes_code,10,0);
        $this->deleteUnusedTermsDMF($vs_thes_code,15,10);
        $this->traiteFichierDMF(
                    __CA_MDF_THESAURI__[$vs_thes_code]["filename"],
                    $vs_thes_code,
                    __CA_MDF_THESAURI__[$vs_thes_code]["label"],
                    __CA_MDF_THESAURI__[$vs_thes_code]["ignoreFirstLines"],
                    $limitation_fichier,
                    50,
                    25
                );
        $this->reaffectTermsDMF("lexdomn",25,75);

        exit();
    }

    /**
     *
     */
    public function ThesaurusImport()
    {
        $this->view->setVar('thesaurus', $_GET["thesaurus"]);
        $this->render('install_thesaurus_import_html.php');
    }

    /**
     *
     */
    public function ThesaurusAlign()
    {
        $this->view->setVar('thesaurus', $_GET["thesaurus"]);
        $this->render('align_thesaurus_launch_html.php');
    }



    # -------------------------------------------------------
	# Sidebar info handler
	# -------------------------------------------------------
    /**
     * @param $pa_parameters
     * @return mixed|null|string
     */
    public function Info($pa_parameters)
	{
		$this->view->setVar('variable', "value");
		return $this->render('widget_install_profile_thesaurus_html.php', true);
	}
}

?>