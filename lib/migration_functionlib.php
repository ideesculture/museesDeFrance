<?php

// ----------------------------------------------------------------------
function getListID($t_list,$list_code,$list_name="") {
	global $pn_locale_id;

	// create vocabulary list record (if it doesn't exist already)
	if (!$t_list->load(array('list_code' => $list_code))) {
		$t_list->setMode(ACCESS_WRITE);
		$t_list->set('list_code', $list_code);
		$t_list->set('is_system_list', 0);
		$t_list->set('is_hierarchical', 1);
		$t_list->set('use_as_vocabulary', 1);
		$t_list->insert();
		
		if ($t_list->numErrors()) {
			print "ERROR: couldn't create ca_list row for $list_code: ".join('; ', $t_list->getErrors())."\n";
			die;
		}
		
		$t_list->addLabel(array('name' => $list_name), $pn_locale_id, null, true);
	}
	$vn_list_id = $t_list->getPrimaryKey();

	return $vn_list_id;	
}
// ----------------------------------------------------------------------

function ExistsItemID($t_list,$vn_list_id,$ps_idno) {
	global $pn_locale_id;

	if($vn_item_id=$t_list->getItemIDFromList($vn_list_id,$ps_idno)) {
		return $vn_item_id;
	} else {
		return false;
	}	
	
}

function getItemID($t_list,$vn_list_id,$pn_type_id,$ps_idno,$libelle,$comment,$pb_is_enabled=1,$pb_is_default = 0, $pn_parent_id=0,$pn_rank = null, $explode_separator_array = null) {
	global $pn_locale_id,$DEBUG;
	
	if ($explode_separator_array) $label_type = $explode_separator_array[1]["label_type"];
	
	if(($vn_item_id = ExistsItemID($t_list,$vn_list_id,$ps_idno)) == false) {
		if ($DEBUG) print "l'item n'existe pas, on va le créer $ps_idno.\n"; 
		$t_item = new ca_list_items();
		$t_item->setMode(ACCESS_WRITE);
		$t_item->set('list_id', $vn_list_id);
		$t_item->set('item_value', $ps_idno);
		$t_item->set('is_enabled', $pb_is_enabled ? 1 : 0);
		$t_item->set('is_default', $pb_is_default ? 1 : 0);
		$t_item->set('parent_id', $pn_parent_id);
		$t_item->set('type_id', $pn_type_id);
		$t_item->set('idno', $ps_idno);
		$t_item->set('access', 1);
		
		
		if (!is_null($pn_rank)) { $t_item->set('rank', $pn_rank); }
		
		$t_item->insert();
		if ($t_item->numErrors()) { 
			$this->errors = array_merge($this->errors, $t_item->errors);
			var_dump($this->errors);die();
			return false;
		}

		//var_dump($t_item);
		if (($explode_separator_array) && ( strpos($libelle,$explode_separator_array[1]["separator"]) > 0) ) {
			// Un séparateur défini et trouvé dans le libellé, on casse selon le séparateur et on crée les titres secondaires avec le bon type
			$libelles = explode($explode_separator_array[1]["separator"],$libelle);
			// Pour chaque libellé individuel, si numéro 0 : libellé principal, si numéro > 0 synonyme
			foreach( $libelles as $key => $value){
				$t_item->addLabel(array('name_singular' => $value , 'name_plural' => $value, 'description' => $comment),$pn_locale_id, ($key == 0 ? null : $label_type) , ($key == 0 ? true : false));
			}
		} else {
			// Pas de séparateur, un seul libellé à traiter en libellé principal
			$t_item->addLabel(array('name_singular' => $libelle, 'name_plural' => $libelle, 'description' => $comment),$pn_locale_id, null, true);									
		}
		if ($t_item->numErrors()) {
			print "PROBLEM WITH ITEM {$ps_idno}: ".join('; ', $t_object->getErrors())."\n";
		}
		$vn_item_id=ExistsItemID($t_list,$vn_list_id,$ps_idno);
	} 
	return $vn_item_id;
	
}

// ----------------------------------------------------------------------
function addObjectSimpleAttribute($t_object,$ATTRIBUTE_CONTENT,$attribute_field,$text_when_empty="") {
	global $pn_locale_id;
	
	if (($ATTRIBUTE_CONTENT=="") && ($text_when_empty !="")) $ATTRIBUTE_CONTENT=$text_when_empty;
	$t_object->addAttribute(array(
			'locale_id' => $pn_locale_id,
			$attribute_field => $ATTRIBUTE_CONTENT
	), $attribute_field);		
	if ($t_object->numErrors()) {
		print "ERROR ADDING $attribute_field TO OBJECT {$ID_NUMBER}: ".join('; ', $t_object->getErrors())."\n";
		return false;
	}
	return true;
}

function getStorageLocationIDfromPartialName($ps_location, $vn_loc_type_id, $ps_location_alternate="") {
	global $pn_locale_id;
	global $VERBOSE;
	global $DEBUG;
	
	$t_loc = new ca_storage_locations();
	$t_label = $t_loc->getLabelTableInstance();
	$location_id="";
	
	$o_data = new Db();
	$qr_c = $o_data->query("
			SELECT location_id
			FROM ca_storage_location_labels
			WHERE left(name,locate(\"|\",name)-1) = ? 
		", $ps_location);
		
	if ($qr_c->nextRow()) {		
		$location_id = (int)$qr_c->get('location_id');
		if ($DEBUG) print "Localisation trouvée ".$location_id."\n";
	} else {
		// pas de résultat trouvé avec une partie du nom, on cherche sur le global
		$location_id = getStorageLocationID($ps_location, $vn_loc_type_id, $ps_location_alternate);
	}
	return $location_id;
	 	
}	

// ----------------------------------------------------------------------
function getStorageLocationID($ps_location, $vn_loc_type_id, $ps_location_alternate="") {
	global $pn_locale_id;
	global $VERBOSE;
	
	$t_loc = new ca_storage_locations();
	$t_label = $t_loc->getLabelTableInstance();
	if (!$t_label->load(array('name' => $ps_location."|".$ps_location_alternate))) {
		if ($VERBOSE) {print "CREATING LOCATION $ps_location|$ps_location_alternate\n";}
		// insert location
		$t_loc->setMode(ACCESS_WRITE);
		$t_loc->set('locale_id', $pn_locale_id);
		$t_loc->set('type_id', $vn_loc_type_id);
		$t_loc->set('access', 1);
		$t_loc->set('status', 2);


		$t_loc->addAttribute(array(
			'locale_id' => $pn_locale_id,
			'altID' => $ps_location
		), 'altID');


		if ($ps_location_alternate != "") {
			$t_loc->addAttribute(array(
				'locale_id' => $pn_locale_id,
				'description' => $ps_location_alternate
			), 'description');
		}

		
		$t_loc->insert();
		
		if ($t_loc->numErrors()) {
			print "ERROR INSERTING location ($ps_location|$ps_location_alternate): ".join('; ', $t_loc->getErrors())."\n";
			return null;
		}
		$t_loc->addLabel(array(
			'name' => $ps_location."|".$ps_location_alternate
		), $pn_locale_id, null, true);
		
		
		$vn_location_id = $t_loc->getPrimaryKey();
	} else {
		if ($VERBOSE) print "\tFound Location $ps_location|$ps_location_alternate\n";
		$vn_location_id = $t_label->get('location_id');
	}
	
	return $vn_location_id;
}
// ----------------------------------------------------------------------	
function getCollectionID($ps_collection, $ps_collection_idno, $pn_collection_type_id) {
	global $pn_locale_id;
	global $VERBOSE;
	
	$t_loc = new ca_collections();
	$t_label = $t_loc->getLabelTableInstance();
	if (!$t_label->load(array('name' => $ps_collection))) {
		if ($VERBOSE) print "CREATING COLLECTION {$ps_collection}\n";
		// insert collection
		$t_loc->setMode(ACCESS_WRITE);
		$t_loc->set('locale_id', $pn_locale_id);
		$t_loc->set('type_id', $pn_collection_type_id);
		$t_loc->set('access', 1);
		$t_loc->set('status', 2);
		$t_loc->set('idno', $ps_collection_idno);
		
		$t_loc->addAttribute(array(
			'locale_id' => $pn_locale_id,
			'name' => $ps_collection
		), 'name');
		
		$t_loc->insert();
		
		if ($t_loc->numErrors()) {
			print "ERROR INSERTING COLLECTION ($ps_collection): ".join('; ', $t_loc->getErrors())."\n";
			return null;
		}
		$t_loc->addLabel(array(
			'name' => $ps_collection
		), $pn_locale_id, null, true);
		
		
		$vn_collection_id = $t_loc->getPrimaryKey();
	} else {
	
		if ($VERBOSE) print "\t\t Found Collection {$ps_collection}\n";
		$vn_collection_id = $t_label->get('collection_id');
	}
	
	return $vn_collection_id;
}
// ----------------------------------------------------------------------	
function getOccurrenceID($ps_occurrence, $ps_occurrence_idno, $pn_occurrence_type_id) {
	global $pn_locale_id;
	global $VERBOSE;
	
	$t_loc = new ca_occurrences();
	$t_label = $t_loc->getLabelTableInstance();
	if (!$t_label->load(array('name' => $ps_occurrence))) {
		if ($VERBOSE) print "CREATING OCCURRENCE {$ps_occurrence}\n";
		// insert occurrence
		$t_loc->setMode(ACCESS_WRITE);
		$t_loc->set('locale_id', $pn_locale_id);
		$t_loc->set('type_id', $pn_occurrence_type_id);
		$t_loc->set('access', 1);
		$t_loc->set('status', 2);
		$t_loc->set('idno', $ps_occurrence_idno);
		
		$t_loc->addAttribute(array(
			'locale_id' => $pn_locale_id,
			'name' => $ps_occurrence
		), 'name');
		
		$t_loc->insert();
		
		if ($t_loc->numErrors()) {
			print "ERROR INSERTING occurrence ($ps_occurrence): ".join('; ', $t_loc->getErrors())."\n";
			return null;
		}
		$t_loc->addLabel(array(
			'name' => $ps_occurrence
		), $pn_locale_id, null, true);
		
		
		$vn_occurrence_id = $t_loc->getPrimaryKey();
	} else {
	
		if ($VERBOSE) print "\t\t Found occurrence {$ps_occurrence}\n";
		$vn_occurrence_id = $t_label->get('occurrence_id');
	}
	
	return $vn_occurrence_id;
}
// ----------------------------------------------------------------------	
function getPlaceID($ps_place, $ps_place_idno, $pn_place_type_id, $ps_place_parent_id=1, $explode_separator_array=NULL, $ps_place_hierarchy_id=NULL ) {
	global $pn_locale_id, $t_list;
	global $VERBOSE, $DEBUG;
	
	if ($explode_separator_array) $label_type = $explode_separator_array[1]["label_type"];
	
	$t_loc_valuetoparse="";
	$t_loc = new ca_places();
	$t_label = $t_loc->getLabelTableInstance();

	if (!$pl_place_hierarchy_id) $ps_place_hierarchy_id = $t_list->getItemIDFromList('place_hierarchies', 'root');

	
	if (!$t_label->load(array('name' => $ps_place))) {
		if ($VERBOSE) print "\tCREATING PLACE {$ps_place}\n";
		// insert place
		$t_loc->setMode(ACCESS_WRITE);
		$t_loc->set('locale_id', $pn_locale_id);
		$t_loc->set('type_id', $pn_place_type_id);
		$t_loc->set('access', 1);
		$t_loc->set('status', 2);
		$t_loc->set('idno', $ps_place_idno);
		$t_loc->set('parent_id', $ps_place_parent_id);
		$t_loc->set('hierarchy_id', $ps_place_hierarchy_id);

		//Insertion
		$t_loc->insert();
		
		//var_dump($t_item);
		if (($explode_separator_array) && ( strpos($ps_place,$explode_separator_array[1]["separator"]) > 0) ) {			
			// Un séparateur défini et trouvé dans le libellé, on casse selon le séparateur et on crée les titres secondaires avec le bon type
			$libelles = explode($explode_separator_array[1]["separator"],$ps_place);
			// Pour chaque libellé individuel, si numéro 0 : libellé principal, si numéro > 0 synonyme
			foreach( $libelles as $key => $value){
				$t_loc->addLabel(array('name' => $value),$pn_locale_id, ($key == 0 ? null : $label_type) , ($key == 0 ? true : false));
				// La valeur a utiliser pour le géoréférencement est la première
				if ($key==0) $t_loc_valuetoparse=$value;
			}
		} else {
			// 1 seul libellé, libellé principal
				$t_loc->addLabel(array('name' => $ps_place),$pn_locale_id, null, true);
				$t_loc_valuetoparse=$ps_place;
		}
		
		if ($t_loc->numErrors()) {
			print "ERROR INSERTING PLACE ($ps_place): ".join('; ', $t_loc->getErrors())."\n";
			return null;
		}
		
		$vn_place_id = $t_loc->getPrimaryKey();
		
		// Georeferencing
		$t_loc_coordinates = new GeocodeAttributeValue();
		if ($DEBUG) print "georéférencement : ".$t_loc_valuetoparse."\n";
		$t_loc_coordinates_values = $t_loc_coordinates->parseValue($t_loc_valuetoparse, null);
		//getDisplayValue($t_loc_coordinates_values);
		if ($t_loc_coordinates_values["value_longtext2"]) {
			$t_loc_coordinates_display=sprintf("%s [%s]",$t_loc_coordinates_values["value_longtext1"],$t_loc_coordinates_values["value_longtext2"]);
			$t_loc->addAttribute(array(
				'locale_id' => $pn_locale_id,
				georeference => $t_loc_coordinates_display
			), 'georeference');
			$t_loc->update();
			if ($t_loc->numErrors()) {
				print "\tERROR UPDATING {$t_loc_valuetoparse} WITH GEOREF '{$t_loc_coordinates_display}': ".join('; ', $t_loc->getErrors())."\n";
				continue;
			}
			if ($DEBUG) print $t_loc_coordinates_display."\n";
		}
		$t_loc_coordinates = null;
		
	} else {
		if ($VERBOSE) print "\tFound Place {$ps_place}\n";
		$vn_place_id = $t_label->get('place_id');
	}
	
	return $vn_place_id;
}
// ----------------------------------------------------------------------
function getEntityID($ps_forename, $ps_surname_with_date, $pn_type_id, $pn_source_id, $other_fields = null) {
	global $pn_locale_id, $vn_date_created, $vn_date_dateUnspecified, $vn_individual, $vn_undefined;
	global $VERBOSE;
	$t_entity = new ca_entities();
	//découpage sur le | pour séparer la date
	$ps_name_table=explode("|",$ps_surname_with_date);
	$ps_date=trim($ps_name_table[1]);
	//découpage sur la virgule pour séparer la profession
	$ps_surname_table=explode(",",$ps_name_table[0]);
	$ps_profession=trim($ps_surname_table[1]);
	$ps_surname=trim($ps_surname_table[0]);	
	if (($ps_surname_space_pos=strrpos($ps_surname_table[0]," ")) && ($pn_type_id == $vn_individual)) {
		// si un espace et personne physique, découpage sur l'espace pour séparer le nom et le prénom
		$ps_forename=substr($ps_surname_table[0],0,$ps_surname_space_pos);
		$ps_surname=substr($ps_surname_table[0],$ps_surname_space_pos+1);
	}
	else {
		// sinon pas de découpe, le tout part dans le nom ex : Ancienne cure
		$ps_surname=trim($ps_surname_table[0]);
	}
	if ($VERBOSE) print "ps_forename : ".$ps_forename." / ps_surname : ".$ps_surname." / ps_profession : ".$ps_profession." / ps_date : ".$ps_date."\n";
	$vn_date = NULL;
	if (sizeof($va_entity_ids = $t_entity->getEntityIDsByName($ps_forename, $ps_surname)) == 0) {
		if ($VERBOSE) print "\tCREATING ENTITY  {$ps_surname},{$ps_forename}\n";
		// insert person
		$t_entity->setMode(ACCESS_WRITE);
		$t_entity->set('locale_id', $pn_locale_id);
		$t_entity->set('type_id', $pn_type_id);
		$t_entity->set('source_id', $pn_source_id);
		$t_entity->set('access', 1);
		$t_entity->set('status', 2);
		if ($ps_profession=trim($ps_profession)) {
			$t_entity->addAttribute(array(
				'locale_id' => $pn_locale_id,
				'entityWork' => $ps_profession
			), 'entityWork');
		}				
		if ($ps_date=trim($ps_date)) {
			$vn_date['dates_value']=$ps_date;
			$vn_date['dates_types']=$vn_date_dateUnspecified;											
			$t_entity->addAttribute($vn_date, 'date');			
		}		

		$t_entity->insert();

		$t_entity->addLabel(array(
			'forename' => $ps_forename, 'surname' => $ps_surname
		), $pn_locale_id, null, true);

		if ($t_entity->numErrors()) {
			print "ERROR INSERTING entity ($ps_forename/$ps_surname): ".join('; ', $t_entity->getErrors())."\n";
			return null;
		}
		if ($other_fields) {
			// If additionnal fields were given, add them to the newly created entity
			foreach($other_fields as $metadata => $value) {
				$t_entity->addAttribute($value, $metadata);
			}
			$t_entity->update();
			if ($t_entity->numErrors()) {
				print "ERROR UPDATING entity ($ps_forename/$ps_surname): ".join('; ', $t_entity->getErrors())."\n";
				return null;
			}
		}
		$vn_entity_id = $t_entity->getPrimaryKey();
	} else {
		if ($VERBOSE) print "\tFound Entity {$ps_forename}/{$ps_surname}\n";
		$vn_entity_id = array_shift($va_entity_ids);
	}
	
	return $vn_entity_id;
}

function insertRelationEntitiesXPlaces($entity_id,$place_id,$type_id,$rank=1) {
	global $DEBUG;
	
	$o_data = new Db();
	$qr_c = $o_data->query("
			SELECT count(*) c
			FROM ca_entities_x_places
			WHERE entity_id = ? AND place_id = ? AND type_id = ?
		", $entity_id,$place_id,$type_id);
		
	if ($qr_c->nextRow()) {		
		if ($qr_c->get('c') == 0) {
			$o_data->query("INSERT INTO ca_entities_x_places (entity_id,place_id,type_id,rank) SELECT ?,?,?,?",$entity_id,$place_id,$type_id,$rank);
		} else {
			if ($DEBUG) print "Relation déjà présente entité $entity_id - lieu $place_id (".(int)$qr_c->get('c')." fois)\n";
		}
	} else {
		return false;
	}
	return true;
}

function updateObjetLot($object_idno,$lot_id) {
	global $DEBUG;
	
	$o_data = new Db();
	$qr_c = $o_data->query("
			UPDATE ca_objects
			SET lot_id = ?
			WHERE idno = ?
		", $lot_id,$object_idno);
		
	return true;
}


function show_status($done, $total, $size=30) {
	/*
	Copyright (c) 2010, dealnews.com, Inc.
	All rights reserved.
	*/

    static $start_time;

    // if we go over our bound, just ignore it
    if($done > $total) return;

    if(empty($start_time)) $start_time=time();
    $now = time();

    $perc=(double)($done/$total);

    $bar=floor($perc*$size);

    $status_bar="\r[";
    $status_bar.=str_repeat("=", $bar);
    if($bar<$size){
        $status_bar.=">";
        $status_bar.=str_repeat(" ", $size-$bar);
    } else {
        $status_bar.="=";
    }

    $disp=number_format($perc*100, 0);

    $status_bar.="] $disp%  $done/$total";

    $rate = ($now-$start_time)/$done;
    $left = $total - $done;
    $eta = round($rate * $left, 2);

    $elapsed = $now - $start_time;

    $status_bar.= " ".number_format($elapsed)."s reste ".number_format($eta)."s";

    echo "$status_bar";

    //echo "\r";

    // when done, send a newline
    if($done == $total) {
        echo "\n";
    }

}

function cls()
{
    array_map(create_function('$a', 'print chr($a);'), array(27, 91, 72, 27, 91, 50, 74));
}  


function htmlvardump() {
	ob_start(); 
	$var = func_get_args(); 
	call_user_func_array('var_dump', $var); 
	return ob_get_clean();
}

	
function cleanupDate($DATATION) {
	//nettoyage des indications de dates approximatives
	$DATATION=str_replace("?","",$DATATION);
	//remplacement du "vers" à la fin de la date par un ca au début
	$DATATION=preg_replace('/(.*) vers/', 'ca \1', $DATATION);
	//remplacement des dates sous la forme "1920 entre ; 1930 et"
	$DATATION=preg_replace('/(.*) entre ; (.*) et/', '\1-\2', $DATATION);
	//remplacement des dates sous la forme "1920 ; 1930"
	$DATATION=preg_replace('/(\d+) ; (\d+)/', '\1-\2', $DATATION);	
	$DATATION=str_replace("<> ","ca ",$DATATION);
	$DATATION=str_replace("<approximatif> ","ca ",$DATATION);
	$DATATION=str_replace("< ","ca ",$DATATION);
	$DATATION=str_replace("> ","ca ",$DATATION);
	$DATATION=str_replace("<","ca ",$DATATION);
	$DATATION=str_replace(">","ca ",$DATATION);
	$DATATION=str_replace("évent. ","ca ",$DATATION);
	$DATATION=str_replace(".","/",$DATATION);
	// le trim doit être fait avant les preg_replace portant sur toute la chaîne
	$DATATION=trim($DATATION);
	//transformation 1920-30 en 1920-1930
	$DATATION=preg_replace('/^(19|20)(\d{2})-(\d{1,2})$/', '\1\2-\1\3', $DATATION);			
	//transformation des dates (JJ/MM/AAAA) au format américain (MM/JJ/AAAA)
	$DATATION=preg_replace("/^(\d{2})\/(\d{2})\/(\d{4})$/", '\2/\1/\3', $DATATION);
	return $DATATION;
}
?>