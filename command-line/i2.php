<?php
/* ----------------------------------------------------------------------
 * support/import/projects/import_eggshell_data.php : 
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2010 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 *
 * This source code is free and modifiable under the terms of 
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * ----------------------------------------------------------------------
 */
	require_once("../setup.php");
	$_SERVER['HTTP_HOST'] = 'localhost';
	
	$DEBUG = TRUE;
	define("SKIP_IMAGES",TRUE);

	require_once(__CA_LIB_DIR__.'/core/Db.php');
	require_once(__CA_MODELS_DIR__.'/ca_locales.php');
	require_once(__CA_MODELS_DIR__.'/ca_objects.php');
	require_once(__CA_MODELS_DIR__."/ca_storage_locations.php");	
	require_once(__CA_MODELS_DIR__."/ca_entities.php");
	require_once(__CA_MODELS_DIR__."/ca_users.php");
	require_once(__CA_MODELS_DIR__."/ca_lists.php");
	require_once(__CA_MODELS_DIR__."/ca_collections.php");
	
	require_once(__CA_LIB_DIR__.'/core/Parsers/DelimitedDataParser.php');

	
	$_ = new Zend_Translate('gettext', __CA_APP_DIR__.'/locale/fr_FR/messages.mo', 'fr_FR');
	
	$t_locale = new ca_locales();
	$pn_locale_id = $t_locale->loadLocaleByCode('fr_FR');		// default locale_id
	
	$IMPORTPHP_BASEPATH = "/Users/gautier/Documents/github/collectiveaccess/providence-french/import_joconde";
	include_once("../dmf/status_bar.php");
	include_once("../migration_functionlib.php");
	
	$t_list = new ca_lists();
	$t_rel_types = new ca_relationship_types();
	
	// Get List values by code. The codes used depend upon how your installation is configured. 
	$vn_object_type_id = 			$t_list->getItemIDFromList('object_types', 'art');
	$pn_rep_type_id = 				$t_list->getItemIDFromList('object_representation_types', 'front');
	$vn_list_item_type_concept =	$t_list->getItemIDFromList('list_item_types', 'concept');		
	$vn_entity_source_id = 			$t_list->getItemIDFromList('entity_sources', 'i1');
	$vn_individual = 				$t_list->getItemIDFromList('entity_types', 'ind');
	$vn_related_creator_type_id = 	$t_rel_types->getRelationshipTypeID('ca_objects_x_entities', 'creator');
	

	$temps_debut = microtime(true);
	// Enable garbage collector
	gc_enable();
	
	$inscription="zpzez";
	$vn_list_id=getListID($t_list,'dmf_lexinsc','');
	$vn_item_id=getItemID($t_list,$vn_list_id,$vn_list_item_type_concept,cleanstring($inscription),$inscription,$comment);
	
	print "id :".$vn_item_id."\n";
	
$temps_fin = microtime(true);
echo 'Temps d\'execution : '.round($temps_fin - $temps_debut, 2)."s \n";

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

?>