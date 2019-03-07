<?php
/* ----------------------------------------------------------------------
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


error_reporting(E_ERROR | E_PARSE | E_NOTICE);
	
require_once("../../setup.php");
$_SERVER['HTTP_HOST'] = 'localhost';
$DEBUG = FALSE;
define("SKIP_IMAGES",FALSE);
//Pour différencier les imports successifs
$IMPORTPHP_BASEPATH="./images/";

require_once(__CA_LIB_DIR__.'/core/Db.php');
require_once(__CA_MODELS_DIR__.'/ca_locales.php');
require_once(__CA_MODELS_DIR__.'/ca_objects.php');
require_once(__CA_MODELS_DIR__."/ca_storage_locations.php");	
require_once(__CA_MODELS_DIR__."/ca_entities.php");
require_once(__CA_MODELS_DIR__."/ca_places.php");
require_once(__CA_MODELS_DIR__."/ca_users.php");
require_once(__CA_MODELS_DIR__."/ca_lists.php");
require_once(__CA_MODELS_DIR__."/ca_collections.php");


$_ = new Zend_Translate('gettext', __CA_APP_DIR__.'/locale/fr_FR/messages.mo', 'fr_FR');

$t_locale = new ca_locales();
$pn_locale_id = $t_locale->loadLocaleByCode('fr_FR');		// default locale_id

include_once("../../dmf/status_bar.php");
include_once("../../migration_functionlib.php");

$t_list = new ca_lists();
$t_rel_types = new ca_relationship_types();

$object_count = 0;
$temps_debut = microtime(true);
// Enable garbage collector
gc_enable();

// Désactivation de l'indexation pour la recherche
define("__CA_DONT_DO_SEARCH_INDEXING__", true);

$mappings = simplexml_load_file('mapping.xml');

$dirname = "../set_aqua";
if (!$dir = opendir($dirname)) {
	print "probleme ouverture répertoire";
	die();
}

while($entry = readdir($dir)) {
	if (!is_dir($dirname."/".$entry)) {
		$record = simplexml_load_file($dirname."/".$entry);
		
		//idno
		print $record->{$mappings->idno->origin};
		
		$t_object = new ca_objects();
		$t_object->setMode(ACCESS_WRITE);
		
		// CREATING THE OBJECT
		$t_object->set('idno', $record->{$mappings->idno->origin});
		$t_object->set('status', $record->{$mappings->status->fixed});
		$t_object->set('access', $record->{$mappings->status->fixed});
		if (substr_compare($mappings->object_type->type,"list-value",0)) {
			$object_info = explode(":", $mappings->object_type->type);
			$vn_object_type_id = $t_list->getItemIDFromList($object_info[1],$mappings->object_type->fixed);
		}
		$t_object->set('type_id', $vn_object_type_id);
		$t_object->insert();
		if ($errors=join('; ', $t_object->getErrors())) { 
			print "ERROR ADDING OBJECT $IDNO : ".$errors."\n"; 
		} else {
			print "OBJECT $IDNO INSERTED\n";
		}
		
		// Path for parsing
		$tags=array(
					array("name"=>"FIELD", "attribute"=>"NAME"),
					array("name"=>"IMAGE")
			);
		$tag="FIELD";
		$attribute="NAME";
		
		// Acquiring the record as a DomDocument to allow non-naming parsing
		$record_dom = dom_import_simplexml($record);
		foreach($tags as $tag) {
			loopForTags($record_dom, $tag["name"], $tag["attribute"]);
		}
		
		$temps_fin = microtime(true);
		echo 'Temps d\'execution : '.round($temps_fin - $temps_debut, 4)."\n";
	}
}


/*************************************/

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

function findAdequateRule($rulename,$value) {
	global $mappings;
	
	foreach($mappings->element as $element) {
		if ($element->origin == "$rulename") {
			if (treatRuleElement($element, $value)) return true;
		}
	}
	return false;
}

function loopForTags($domObject, $tag, $attribute=NULL) {
	$elements = $domObject->getElementsByTagName($tag);
	foreach ($elements as $element ) {
		if (!$attribute) $rulename=$tag;
		if ($attribute && $element->hasAttributes()) {
			// Récup attribut
			$attribute_value = $element->attributes->getNamedItem($attribute)->nodeValue;
			$rulename = $tag.":".$attribute."=\"".$attribute_value."\"";
		}
		// Récup valeur
		$value=$element->firstChild->nodeValue;
		// TODO : treat each rulename/value pair
		if (!findAdequateRule($rulename,$value,1)); 
		//print "regle inconnue $rulename\n";
	}
}

function treatRuleElement($element,$global_value,$iteration=0) {
	global $t_object, $t_list;

	if(($element->has_subfields) && ($element->extraction != "")) {

		// EXTRACTION : regexp extraction is directly used only at first level, detected with has_subfields
		// TODO : get another way to detect crawling level, maybe by name ?
		preg_match_all($element->extraction, $global_value, $values);
		$values=$values[1];

		// EXTRACTION allows to ITERATE
		if ($element->iterate) $iterate=true;
	
	} elseif($element->separator) { 
		
		// SEPARATOR : Exploding value by separator if needed
		$values=explode($element->separator,$global_value);

	} else 
		$values=array($global_value);
	
	foreach($values as $value) {
		if($iterate) $iteration++;

		// TYPE : If a type is specified, modify the value to adequate one
		if($element->type) {
			$type=explode(":",$element->type);
			switch ($type[0]) {
				case "list-value":
				 	// TYPE is a list, replacing the value by the one found in the list, if no value found this value is created
				 	if (!$value = $t_list->getItemIDFromList($type[1], $value)) break;
					break;
				case "date":
					if($date=strstr($value,"vers",true)) $value="ca ".$date;
					break;
			}
			if($type[0] == "list-value") {
			}
		}

		// SUBFIELD : if has_subfields, treat each occurrence as a separate field
		if($element->has_subfields) {
			foreach($element->subfield as $subfield) {

			// EXTRACTION : regexp extraction
			preg_match($subfield->extraction, $value, $subvalue);
			$subvalue=$subvalue[1];

			// TREAT THE SUBFIELDS
			treatRuleElement($subfield, $subvalue,$iteration);
			
			}
		} else {
			
			// TARGET : data insertion target, # used a numbering joker
			if($target = $element->target) {
				$target = str_replace("#", $iteration, $element->target);				

				// OK, we have a valid target and a value, let's go
				importValueToTarget($target,$value);				

			} else {
				//print "No target as this level, value : ".$value."\n";
			}
		}
		//print "multiple ".$element->multiple_targets."\n";
	}
	return true;
}

function importValueToTarget($target,$value) {
	global $t_object,$pn_locale_id,$SKIP_IMAGES;
	
	print $target." : ".$value."\n";
	$target=explode(".",$target);
	if ($target[0]=="ca_object_labels") {
		// object name
		$t_object->addLabel(array('name' => $value), $pn_locale_id, null, true);
	} elseif($target[0] == "ca_objects") {
		// object fields
		$target=$target[1];
		$vn_field[$target]=$value;
		$vn_field['locale_id']=$pn_locale_id;
		$t_object->addAttribute($vn_field, $target);
		$t_object->update();
	} elseif($target[0]=="ca_object_representation") {
		// representation url
		if($target[1] =="url") {
			importRepresentationFromURL($value);
		}
	}
	if ($t_object->numErrors()) {
		print "ERROR ADDING FIELD {$target} TO OBJECT : ".join('; ', $t_object->getErrors())."\n";
		die();
	}
}

function importRepresentationFromURL($image_url) {
	global $t_object, $IMPORTPHP_BASEPATH;

	$image_filename = cleanString(basename($image_url));
	$imagedata=file_get_contents($image_url);
	if ($imagedata) {
		file_put_contents($IMPORTPHP_BASEPATH."/".$image_filename,$imagedata);
		$t_object->addRepresentation($IMPORTPHP_BASEPATH."/".$image_filename, $pn_rep_type_id, $pn_locale_id, 0, 1, (($image_num ==1) ? 1 : 0));
		$t_object->update();
		if ($t_object->numErrors()) { 
			print "Error adding image $image_filename to object $IDNO : ".join('; ', $t_object->getErrors())."\n";
			return false;
		} else {
			print "Image $image_filename added to object $IDNO\n";
			exec("rm -f ".$IMPORTPHP_BASEPATH."/".$image_filename);
			return true;
		}
	}
}

?>