<?php
/* ----------------------------------------------------------------------
 * harvest-joconde.php
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * Harvesting joconde french national database by idéesculture
 * ----------------------------------------------------------------------
 */
error_reporting(E_ERROR | E_PARSE | E_NOTICE);
	
require_once("/Users/gautier/web/granet/providence/setup.php");
$IMPORTPHP_BASEPATH = "/Users/gautier/web/granet/providence/app/plugins/museesDeFrance/command-line/medias";
$min_notice = 1;
$max_notice= 10000;
// Désactivation de l'indexation pour la recherche
define("__CA_DONT_DO_SEARCH_INDEXING__", false);
define("__CA_DONT_DO_HIERARCHICAL_REINDEXING_AFTER_IMPORT__", false);


	$_SERVER['HTTP_HOST'] = 'localhost';
	//error_reporting(-1); ini_set('display_errors', 1);	
	$DEBUG = FALSE;
	define("SKIP_IMAGES",false);
	//Pour différencier les imports successifs
	$suffixe = "";

	require_once(__CA_LIB_DIR__.'/core/Db.php');
	require_once(__CA_MODELS_DIR__.'/ca_locales.php');
	require_once(__CA_MODELS_DIR__.'/ca_objects.php');
	require_once(__CA_MODELS_DIR__."/ca_storage_locations.php");	
	require_once(__CA_MODELS_DIR__."/ca_entities.php");
	require_once(__CA_MODELS_DIR__."/ca_places.php");
	require_once(__CA_MODELS_DIR__."/ca_users.php");
	require_once(__CA_MODELS_DIR__."/ca_lists.php");
	require_once(__CA_MODELS_DIR__."/ca_collections.php");
	
	require_once(__CA_LIB_DIR__.'/core/Parsers/DelimitedDataParser.php');

	
	$_ = new Zend_Translate('gettext', __CA_APP_DIR__.'/locale/fr_FR/messages.mo', 'fr_FR');
	
	$t_locale = new ca_locales();
	$pn_locale_id = $t_locale->loadLocaleByCode('fr_FR');		// default locale_id
	
	include_once("../lib/migration_functionlib.php");
	
	$t_list = new ca_lists();
	$t_rel_types = new ca_relationship_types();
	
	// Get List values by code. The codes used depend upon how your installation is configured. 
	$vn_object_type_id = 			$t_list->getItemIDFromList('object_types', 'acq_art');
	$pn_rep_type_id = 				$t_list->getItemIDFromList('object_representation_types', 'front');
	$vn_list_item_type_concept =	$t_list->getItemIDFromList('list_item_types', 'concept');		
	$vn_entity_source_id = 			$t_list->getItemIDFromList('entity_sources', 'i1');
	$vn_individual = 				$t_list->getItemIDFromList('entity_types', 'ind');
	$vn_related_creator_type_id = 	$t_rel_types->getRelationshipTypeID('ca_objects_x_entities', 'creation_createur');
	$vn_related_curated_place_type_id = 	$t_rel_types->getRelationshipTypeID('ca_objects_x_places', 'conservation_place');
	$vn_place_source_id = 			$t_list->getItemIDFromList('place_sources', 'blank');
	$vn_place_other_type_id = 		$t_list->getItemIDFromList('place_types', 'other');
	$ps_place_hierarchy_id = 		$t_list->getItemIDFromList('place_hierarchies', 'isadg');
    $vn_datePeriod_type = $t_list->getItemIDFromList("type_periode","creation");

	$object_count = 0;
	$temps_debut = microtime(true);
	// Enable garbage collector
	gc_enable();


	
	//Création d'un fichier pour pouvoir relire tout ce qui n'a pas été importé
	$fichier = fopen('nonimporte.txt','w+');
	//fputs($fichier, "<head><title></title><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /></head><body>");
	
for ( $notice=$min_notice ; $notice<=$max_notice ; $notice++) {
	
	$record = new DomDocument();
						
	// ----------------------------------------------------------------------
	// process main data
    if (is_file("aquarelle/aquarelle-".$notice.".xml")) {
        if (!$record->load("aquarelle/aquarelle-".$notice.".xml")) {
            echo("Couldn't parse data\n");
            break 1;
        }

        $result = "";

        if($record->hasChildNodes() == true) {
            // READING RECORD INFO
            // The record contains fields

            // Treat REQ field
            //<REQ>02110003079</REQ>
            $IDNO = $record->getElementsByTagName('REQ')->item(0)->nodeValue;
            $IDNO .= $suffixe;

            //Treat ONLINE field
            $ONLINE = $record->getElementsByTagName('ONLINE')->item(0)->nodeValue;

            //Treat IMAGE fields
            $images_array= array();
            $IMAGES = $record->getElementsByTagName('IMAGE');
            $image_num=1;
            foreach($IMAGES as $IMAGE) {
                $images_array[$image_num] = $IMAGE->nodeValue;
                $image_num++;
            }

            //Treat FIELD fields
            $FIELDS = $record->getElementsByTagName('FIELD');
            $fields_array= array();
            foreach($FIELDS as $FIELD) {
                if ($FIELD->hasAttributes()) {
                    $field_name = $FIELD->attributes->getNamedItem('NAME')->nodeValue;
                    $field_value = $FIELD->nodeValue;
                    $fields_array[$field_name]=$field_value;
                }
            }
            // CREATING CA OBJECT RECORD
            $t_object = new ca_objects();
            $t_object->setMode(ACCESS_WRITE);
            $t_object->set('idno', $IDNO);
            $t_object->set('status', 0);
            $t_object->set('access', 1);
            $t_object->set('type_id', $vn_object_type_id);
            $t_object->insert();
            if ($errors=join('; ', $t_object->getErrors())) {
                print "ERROR ADDING OBJECT $IDNO : ".$errors."\n";
            } else {
                print "OBJECT $IDNO INSERTED\n";
            }

            // TITLE
            //<FIELD NAME="Titre">L'Amour entre Vénus et Bacchus</FIELD>
            $TITLE = $fields_array["Titre"];
            if ($TITLE=="") $TITLE="Inconnu";
            $t_object->addLabel(array('name' => $TITLE.$suffixe), $pn_locale_id, null, true);
            if ($t_object->numErrors()) {
                print "ERROR ADDING TITLE $TITLE TO OBJECT $IDNO : ".join('; ', $t_object->getErrors())."\n";
            } else {
                print "TITLE $TITLE ADDED TO OBJECT $IDNO\n";
            }
            unset($fields_array["Titre"]);

            //IMAGES
            if (!SKIP_IMAGES) {
                $image_num=1;
                foreach($images_array as $image_num => $image_url) {
                    $image_filename = cleanString(basename($image_url));
                    $imagedata=file_get_contents($image_url);
                    if ($imagedata) {
                        file_put_contents($IMPORTPHP_BASEPATH."/".$image_filename,$imagedata);
                        $t_object->addRepresentation($IMPORTPHP_BASEPATH."/".$image_filename, $pn_rep_type_id, $pn_locale_id, 0, 1, (($image_num ==1) ? 1 : 0));
                        $t_object->update();
                        if ($t_object->numErrors()) {
                            print "Error adding image $image_filename to object $IDNO : ".join('; ', $t_object->getErrors())."\n";
                        } else {
                            print "Image $image_filename added to object $IDNO\n";
                        }
                    }
                    $image_num++;
                }
            }

            //DOMAINE
            //<FIELD NAME="Domaine">dessin</FIELD>
            $DOMAINE = $fields_array["Domaine"];
            if ($DOMAINE) {
                $vn_list_id=getListID($t_list,'dmf_lexdomn','');
                $vn_item_id=getItemID($t_list,$vn_list_id,$vn_list_item_type_concept,cleanString($DOMAINE),$DOMAINE,$DOMAINE,"");
                if ($vn_item_id) {
                    addObjectSimpleAttribute($t_object, $vn_item_id, "domaine");
                    $t_object->update();
                }
            }
            unset($fields_array["Domaine"]);

            //AUTEUR
            //<FIELD NAME="Auteur/exécutant">HUGUET Jean François</FIELD>
            $AUTEUR = $fields_array["Auteur/exécutant"];
            //Précisions auteur/exécutant
            //<FIELD NAME="Précision auteur/exécutant">HUGUET : Rennes, 1679 ; Rennes, 1749</FIELD>
            if ($fields_array["Précision auteur/exécutant"]) $PRECISION_AUTEUR =
                array("precision_entite" => array("locale_id" => $pn_locale_id, "precision_entite" => $fields_array["Précision auteur/exécutant"]));

            if ($AUTEUR) {
                $vn_contact_id = getEntityID('', $AUTEUR, $vn_individual, $vn_entity_source_id, $PRECISION_AUTEUR);
                if ($vn_contact_id) {
                    $t_object->addRelationship('ca_entities',$vn_contact_id, $vn_related_creator_type_id);
                    if ($t_object->numErrors()) {
                        print "Error adding entity (Auteur) $AUTEUR to object $IDNO : ".join('; ', $t_object->getErrors())."\n";
                    } else {
                        print "Auteur $AUTEUR added to object $IDNO\n";
                    }
                }
            }
            unset($fields_array["Auteur/exécutant"]);
            unset($fields_array["Précision auteur/exécutant"]);

            //Millésime création/exécution
            //<FIELD NAME="Millésime création/exécution">1737</FIELD>
            if ($value=$fields_array["Millésime création/exécution"]) {
                $value=retraiteDate($value);
                addAttributeFromFieldsArray($t_object,$value,"objectProductionDate");
            }


            unset($fields_array["Millésime création/exécution"]);

            //Matériaux/techniques
            //<FIELD NAME="Matériaux/techniques">pierre noire ; aquarelle ; lavis noir ; gouache blanche ; papier (blanc)</FIELD>
            $values=NULL;$value=NULL;
            if ($values=$fields_array["Matériaux/techniques"]) {
                $values=str_replace(", ", " ; ", $values);
                $values=explode(" ; ", $values);
                foreach($values as $value) {
                    $vn_list_id=getListID($t_list,'dmf_lextech','');
                    $vn_item_id=getItemID($t_list,$vn_list_id,$vn_list_item_type_concept,cleanString($value),$value,$value,"");
                    if ($vn_item_id) {
                        addObjectSimpleAttribute($t_object, $vn_item_id, "materiaux");
                        $t_object->update();
                    }
                }
            }
            unset($fields_array["Matériaux/techniques"]);

            //Mesures
            //<FIELD NAME="Mesures">mesures en cm : H. 20.1 ; l. 22.1</FIELD>
            $values=NULL;$value=NULL;$HAUTEUR=0;$LARGEUR=0;
            if ($values=$fields_array["Mesures"]) {
                $values=str_replace("mesures en cm : ", "", $values, $replace_count);
                if ($replace_count) {
                    $values=explode(" ; ", $values);
                    foreach($values as $value) {
                        if(strstr($value, "H.")) $HAUTEUR=str_replace("H. ", "", $value);
                        if(strstr($value, "l.")) $LARGEUR=str_replace("l. ", "", $value);
                    }
                    if ($HAUTEUR) {
                        $HAUTEUR = $HAUTEUR * 1;
                        $HAUTEUR = trim($HAUTEUR)." cm";
                        $vn_dimensions['dimensions_height'] = $HAUTEUR;
                    }
                    if ($LARGEUR) {
                        $LARGEUR = $LARGEUR * 1;
                        $LARGEUR = trim($LARGEUR)." cm";
                        $vn_dimensions['dimensions_width'] = $LARGEUR;
                    }
                    $vn_dimensions['dimensions_type'] = $t_list->getItemIDFromList('dimension_types', 'blank');
                    $t_object->addAttribute($vn_dimensions, 'dimensions');
                    $t_object->update();
                    if ($t_object->numErrors()) print "PROBLEM WITH DIMENSIONS {$IDNO}: ".join('; ', $t_object->getErrors())."\n";
                }
            }
            unset($fields_array["Mesures"]);

            //<FIELD NAME="Inscriptions">signé, daté ; inscription</FIELD>
            //<FIELD NAME="Précision inscriptions">signé, daté : HUGUET IN ET DEL 1737 ; inscription d'origine : SI TOST QUE DES L'ENFANCE ON SE LIVRE A BACCUS ON COURT A TOUTE BRIDE DANS LE SEIN DE VENUS, ET CES DEUX PASSIONS APELLENT LA TROISIEME TOUT EST FUREUR, TOUT EST EXTREME EN IMITANT CE PETIT DIEU COMME LUY ON DEVIENT COMPLICES DES TROIS PLUS DETESTABLES VICES QUE CAUSENT LES FEMMES LE VIN LE JEU</FIELD>
            $vn_inscriptions = array();
            if($fields_array["Inscriptions"] || $fields_array["Précision inscriptions"]) {
                $fields_inscriptions = explode(" ; ",$fields_array["Inscriptions"]);
                $fields_prec_inscriptions = explode(" ; ",$fields_array["Précision inscriptions"]);
                if (count($fields_inscriptions) != count($fields_prec_inscriptions)) {
                    //si pas le même nombre de précisions que d'inscription, toutes les précisions vont dans le premier container.
                    $fields_prec_inscriptions = array($fields_array["Précision inscriptions"]);
                }
                foreach($fields_inscriptions as $num => $inscription) {
                    $vn_list_id=getListID($t_list,'dmf_lexinsc','');
                    $vn_item_id=getItemID($t_list,$vn_list_id,$vn_list_item_type_concept,cleanstring($inscription),$inscription,$comment);
                    $vn_inscriptions=array(
                        "inscription_type" => $vn_item_id,
                        "inscription_precision" => $fields_prec_inscriptions[$num],
                        "locale_id" => $pn_locale_id
                    );
                    $t_object->addAttribute($vn_inscriptions, 'inscription_c');
                    $t_object->update();
                    if ($t_object->numErrors()) {
                        print "PROBLEM WITH INSCRIPTIONS {$IDNO} $inscription : ".join('; ', $t_object->getErrors())."\n";
                        die();
                    }
                }
            }
            unset($fields_array["Inscriptions"]);
            unset($fields_array["Précision inscriptions"]);

            //<FIELD NAME="Numéro d'inventaire">Inv 794.1.2799</FIELD>
            if($fields_array["Numéro d'inventaire"]) {
                $vn_list_id=getListID($t_list,'other_number_type','');
                $vn_inv_id=ExistsItemID($t_list,$vn_list_id,"inv");
                $t_object->addAttribute(
                    array(
                        "objectNo" => $fields_array["Numéro d'inventaire"],
                        "objectNumberType" => $vn_inv_id
                    ),
                    'otherNumber'
                );
                $t_object->update();
            }
            unset($fields_array["Numéro d'inventaire"]);

            //<FIELD NAME="Date acquisition">1791</FIELD>
            if($fields_array["Date acquisition"]) {
                $t_object->addAttribute(array("acquisitionDate" => $fields_array["Date acquisition"]),'acquisitionDate');
                $t_object->update();
            }
            unset($fields_array["Date acquisition"]);

            //<FIELD NAME="Date dépôt/changement affectation">1794</FIELD>date_depot
            if($fields_array["Date dépôt/changement affectation"]) {
                $t_object->addAttribute(array("date_depot" => $fields_array["Date dépôt/changement affectation"]),'date_depot');
                $t_object->update();
            }
            unset($fields_array["Date dépôt/changement affectation"]);

            //<FIELD NAME="Rédacteur">Olivia Savatier</FIELD>
            if($fields_array["Rédacteur"]) {
                $t_object->addAttribute(array("redacteur" => $fields_array["Rédacteur"]),'redacteur');
                $t_object->update();
            }
            unset($fields_array["Rédacteur"]);

            //<ONLINE><![CDATA[http://www.culture.gouv.fr/public/mistral/joconde_fr?ACTION=RETROUVER&NUMBER=&REQ=02110003079%3AREF]]></ONLINE>
            if($ONLINE) {
                $t_object->addAttribute(
                    array(
                        "url_source" => $fields_array["Voir la notice en ligne sur la base Joconde de la DMF"],
                        "url_entry" => $ONLINE
                    ),
                    'external_link'
                );
                $t_object->update();
            }

            //<FIELD NAME="Crédits photographiques">© Jean-Manuel Salingue</FIELD>
            if($fields_array["Crédits photographiques"]) {
                $t_object->addAttribute(
                    array(
                        "credits_photo" => $fields_array["Crédits photographiques"],
                        "locale_id" => $pn_locale_id
                    ),
                    'credits_photo'
                );
                $t_object->update();
                unset($fields_array["Crédits photographiques"]);
            }

            //<FIELD NAME="Copyright notice">© Rennes, musée des beaux-arts, © Service des musées de France, 2012</FIELD>
            if($fields_array["Copyright notice"]) {
                $t_object->addAttribute(
                    array(
                        "copyright_notice" => $fields_array["Copyright notice"],
                        "locale_id" => $pn_locale_id
                    ),
                    'copyright_notice'
                );
                $t_object->update();
                unset($fields_array["Copyright notice"]);
            }

            //<FIELD NAME="Ecole">France</FIELD>
            if($ecole=$fields_array["Ecole"]) {
                $vn_list_id=getListID($t_list,'dmf_lexecol','');
                $vn_item_id=getItemID($t_list,$vn_list_id,$vn_list_item_type_concept,cleanstring($ecole),$ecole,"");
                $vn_ecole=array(
                    "ecole" => $vn_item_id,
                );
                $t_object->addAttribute($vn_ecole, 'ecole');
                $t_object->update();
                if ($t_object->numErrors()) {
                    print "PROBLEM WITH ECOLE {$IDNO} $ecole : ".join('; ', $t_object->getErrors())."\n";
                    die();
                }
                unset($fields_array["Ecole"]);
            }

            //<FIELD NAME="Période création/exécution">2e quart 18e siècle</FIELD>
            if($fields_array["Période création/exécution"]) {
                $periodes=explode(" ; ", $fields_array["Période création/exécution"]);
                foreach($periodes as $periode) {
                    $vn_list_id=getListID($t_list,'dmf_lexperi','');
                    $vn_item_id=getItemID($t_list,$vn_list_id,$vn_list_item_type_concept,cleanstring($periode),$periode,"");
                    $t_object->addAttribute(array("datePeriod" => $vn_item_id,"datePeriod_type" => $vn_datePeriod_type), 'datePeriod');
                    $t_object->update();
                    if ($t_object->numErrors()) {
                        print "PROBLEM WITH PERIODE {$IDNO} $periode : ".join('; ', $t_object->getErrors())."\n";
                        die();
                    }
                }
                unset($fields_array["Période création/exécution"]);
            }



            //<FIELD NAME="Sujet représenté">scène mythologique (Vénus, nuée, char, colombe, Cupidon, verre à pied, Bacchus, bouteille : vin, tonneau, feuillu, carquois, flèche, chèvre, félidé, décor de jardin)</FIELD>
            if($fields_array["Sujet représenté"]) {
                $t_object->addAttribute(array("sujet" => $fields_array["Sujet représenté"], "locale_id" => $pn_locale_id), 'sujet');
                $sujets=explode(" ; ",$fields_array["Sujet représenté"]);
                foreach($sujets as $n1 => $sujet) {
                    preg_match_all("/([^\(]+) \((.*)\)/i",$sujet,$out, PREG_PATTERN_ORDER);
                    $t_object->addAttribute(array("sujet".$n1 => $out[1][0], "locale_id" => $pn_locale_id), "sujet".$n1);
                    $i=1;
                    foreach($determinants=explode(", ",$out[2][0]) as $n2 => $determinant) {
                        $t_object->addAttribute(array("element".$n1 => $determinant, "locale_id" => $pn_locale_id), "element".$n1);
                        $i++;
                    }
                }
                $t_object->update();
                if ($t_object->numErrors()) {
                    print "ERROR ADDING Sujet représenté ".$fields_array["Sujet représenté"]." FOR OBJECT {$IDNO}: ".join('; ', $t_object->getErrors())."\n";
                }
                unset($fields_array["Sujet représenté"]);
            }

            //<FIELD NAME="Lieu de conservation">Rennes ; musée des beaux-arts</FIELD>
            if($conservation_place=$fields_array["Lieu de conservation"]) {
                $vn_place_id = getPlaceID($conservation_place,cleanstring($conservation_place), $vn_place_other_type_id, 1, NULL, $ps_place_hierarchy_id);
                if ($vn_place_id) {
                    $t_object->addRelationship('ca_places',$vn_place_id, $vn_related_curated_place_type_id);
                    $t_object->update();
                    if ($t_object->numErrors()) {
                        print "ERROR ADDING PLACE {$conservation_place} FOR OBJECT {$IDNO}: ".join('; ', $t_object->getErrors())."\n";
                    }
                }
                unset($fields_array["Lieu de conservation"]);
            }

            //<FIELD NAME="Statut juridique">propriété de l'Etat ; saisie révolutionnaire</FIELD>
            if($fields_array["Statut juridique"]) {
                $t_object->addAttribute(
                    array(
                        "statut_juridique" => $fields_array["Statut juridique"],
                        "locale_id" => $pn_locale_id
                    ),
                    'statut_juridique'
                );
                $t_object->update();
                unset($fields_array["Statut juridique"]);
            }

            //<FIELD NAME="Anciennes appartenances">Collection privée, ROBIEN Christophe-Paul de</FIELD>
            if($fields_array["Anciennes appartenances"]) {
                $t_object->addAttribute(
                    array(
                        "anciennes_appartenances" => $fields_array["Anciennes appartenances"],
                        "locale_id" => $pn_locale_id
                    ),
                    'anciennes_appartenances'
                );
                $t_object->update();
                unset($fields_array["Anciennes appartenances"]);
            }

            //<FIELD NAME="Dépôt/changement affectation">dépôt ; Rennes ; musée des beaux-arts</FIELD>
            if($fields_array["Dépôt/changement affectation"]) {
                $t_object->addAttribute(
                    array(
                        "depot" => $fields_array["Dépôt/changement affectation"],
                        "locale_id" => $pn_locale_id
                    ),
                    'depot'
                );
                $t_object->update();
                unset($fields_array["Dépôt/changement affectation"]);
            }

            //<FIELD NAME="Bibliographie">Cat. 1884 C 125 n° 1</FIELD>
            if($fields_array["Bibliographie"]) {
                $t_object->addAttribute(
                    array(
                        "bibliography" => $fields_array["Bibliographie"],
                        "locale_id" => $pn_locale_id
                    ),
                    'bibliography'
                );
                $t_object->update();
                unset($fields_array["Bibliographie"]);
            }

            fputs($fichier,"OBJET : ".$IDNO."\n");
            fputs($fichier,htmlvardump($fields_array));
            fputs($fichier,"------------------------\n");

            $object_count ++;

        } else {
            // EMPTY RECORD
        }
        $object_count++;
    }

}

print $object_count." objects inserted.\n";

if (__CA_DONT_DO_HIERARCHICAL_REINDEXING_AFTER_IMPORT__ === false) {
    print "Reconstruction des indices hiérarchiques\n";
    $o_dm = Datamodel::load();

    $va_table_names = $o_dm->getTableNames();

    foreach($va_table_names as $vs_table) {
        if ($o_instance = $o_dm->getInstanceByTableName($vs_table)) {
            if ($o_instance->isHierarchical()) {
                if (!$o_instance->rebuildAllHierarchicalIndexes()) {
                    $o_instance->rebuildHierarchicalIndex();
                }
            }
        }
    }
}

//fputs($fichier,"</body>\n</html>");
fclose($fichier);
	
$temps_fin = microtime(true);
echo 'Temps d\'execution : '.round($temps_fin - $temps_debut, 4)."\n";

// ----------------------------------------------------------------------
// cleanString
// Cleans a string of any accent, blank character, etc.
//
// parameters
//		$string (string) : string to clean
// returns
//		(string) : string cleaned up
// ----------------------------------------------------------------------
function cleanString($string){
	$string = preg_replace("'(\xBB|\xAB|!|\xA1|%|,|:|;|\(|\)|\&|\"|\'|\.|-|\/|\?| |\\\)'", '', $string);
	$translit = array('Á'=>'A','À'=>'A','Â'=>'A','Ä'=>'A','Ã'=>'A','Å'=>'A','Ç'=>'C','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E','Í'=>'I','Ï'=>'I','Î'=>'I','Ì'=>'I','Ñ'=>'N','Ó'=>'O','Ò'=>'O','Ô'=>'O','Ö'=>'O','Õ'=>'O','Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','Ý'=>'Y','á'=>'a','à'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a','ç'=>'c','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ñ'=>'n','ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ý'=>'y','ÿ'=>'y');
	$string = strtr($string, $translit);
	return $string;
}

function statusbar($done, $total) {
	if (PHP_SAPI === 'cli') {
		// Command line
   		show_status($done, $total);
	} else {
		print "/";
		if ($done==$total) print " DONE !\n";
		// Inside Apache
	}
}

function addAttributeFromFieldsArray($t_object,$field_name,$field_value,$options=NULL) {
	global $IDNO;
	
	if(!$options) {
		if(!is_array($field_value)) {
			addObjectSimpleAttribute($t_object, $field_name, $field_value);
			$t_object->update();
			if ($t_object->numErrors()) {
				print "Error adding $field_name to object $IDNO : ".join('; ', $t_object->getErrors())."\n";
				return false;
			}
			return true; 
		} else {
		}
	}
	return false;
	
}

function getNodeInnerHTML($elem) {
       return simplexml_import_dom($elem)->asXML();
}
function innerXML($node) { 
	$doc = $node->ownerDocument; 
	$frag = $doc->createDocumentFragment(); 
	foreach ($node->childNodes as $child) { 
		$frag->appendChild($child->cloneNode(TRUE));
	} 
	return $doc->saveXML($frag); 
}
	
function retraiteDate($date_text) {
    $DATATION = $date_text;
    if ($DATATION=="1950-1900") $DATATION="1900-1950"; //00802
    if ($DATATION=="18") $DATATION="ca 1800"; //00828
    if ($DATATION=="1922 ou 24") $DATATION="ca 1922"; //01141
    if ($DATATION=="????époque gothique") $DATATION="ca 1190-1650"; //00926
    //nettoyage des indications de dates approximatives
    $DATATION=str_replace("?","",$DATATION);
    $DATATION=str_replace("<> ","ca ",$DATATION);
    $DATATION=str_replace("<approximatif> ","ca ",$DATATION);
    $DATATION=str_replace("< ","ca ",$DATATION);
    $DATATION=str_replace("> ","ca ",$DATATION);
    $DATATION=str_replace("<","ca ",$DATATION);
    $DATATION=str_replace(">","ca ",$DATATION);
    $DATATION=str_replace("évent. ","ca ",$DATATION);
    $DATATION=str_replace(".","/",$DATATION);
    // Remplace 1720-1730 environ par ca 1720-1730
    $DATATION=preg_replace("/^(\d{4})-(\d{4}) environ$/", 'ca \1-\2', $DATATION);
    // Remplace 1720 environ par ca 1720
    $DATATION=trim(preg_replace("/(\d{4}) environ/", 'ca \1', $DATATION));

    // le trim doit être fait avant les preg_replace portant sur toute la chaîne
    $DATATION=trim($DATATION);
    //transformation "1899 vers" en "vers 1899"
    $DATATION=preg_replace('/^(.*) vers$/', 'circa \1', $DATATION);
    //transformation 1920-30 en 1920-1930
    $DATATION=preg_replace('/^(19|20)(\d{2})-(\d{1,2})$/', '\1\2-\1\3', $DATATION);
    //transformation 1920-30 en 1920-1930
    $DATATION=preg_replace('/^(19|20)(\d{2})-(\d{1,2})$/', '\1\2-\1\3', $DATATION);
    //transformation 1740/45 en 1740-1745
    $DATATION=preg_replace('/^(\d{2})(\d{2})\/(\d{2})$/', '\1\2-\1\3', $DATATION);
    //transformation des dates (JJ/MM/AAAA) au format américain (MM/JJ/AAAA)
    $DATATION=preg_replace("/^(\d{2})\/(\d{2})\/(\d{4})$/", '\2/\1/\3', $DATATION);
    return $DATATION;
}

?>