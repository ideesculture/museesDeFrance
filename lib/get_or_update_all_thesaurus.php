<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require "../../../../setup.php";

require_once(__CA_MODELS_DIR__ . '/ca_lists.php');
require_once(__CA_MODELS_DIR__ . '/ca_list_items.php');

$files = [
    "https://opentheso.huma-num.fr/opentheso/api/all/theso?id=th289&format=json", // Epoque
    "https://opentheso.huma-num.fr/opentheso/api/all/theso?id=th288&format=json", // Inscription
    "https://opentheso.huma-num.fr/opentheso/api/all/theso?id=th294&format=json", // Domaine
    "https://opentheso.huma-num.fr/opentheso/api/all/theso?id=th290&format=json", // Dénomination
    "https://opentheso.huma-num.fr/opentheso/api/all/theso?id=th287&format=json", // Période
    "https://opentheso.huma-num.fr/opentheso/api/all/theso?id=th291&format=json", // Technique 
    "https://opentheso.huma-num.fr/opentheso/api/all/theso?id=th292&format=json", // Découverte
    "https://opentheso.huma-num.fr/opentheso/api/all/theso?id=th295&format=json", // École
    "https://opentheso.huma-num.fr/opentheso/api/all/theso?id=th298&format=json", // Genèse
    "https://opentheso.huma-num.fr/opentheso/api/all/theso?id=th304&format=json", // Utilisation
    "https://opentheso.huma-num.fr/opentheso/api/all/theso?id=th302&format=json", // Rôle
    "https://opentheso.huma-num.fr/opentheso/api/all/theso?id=th286&format=json", // Source de la Représentation
    "https://opentheso.huma-num.fr/opentheso/api/all/theso?id=th285&format=json", // Représentation
];
$tableauDeCorrespondance = [
    ["nom" => "Liste d'autorités Époques", "list_code" => "th289"], 
    ["nom" => "Liste d'autorités Inscriptions", "list_code" => "th288"],
    ["nom" => "Liste d'autorités Domaines", "list_code" => "th294"],
    ["nom" => "Liste d'autorités Dénomination", "list_code" => "th290"],
    ["nom" => "Liste d'autorités Périodes", "list_code" => "th287"],
    ["nom" => "Liste d'autorités Techniques", "list_code" => "th291"],
    ["nom" => "Liste d'autorités Découverte", "list_code" => "th292"],
    ["nom" => "Liste d'autorités Écoles", "list_code" => "th295"],
    ["nom" => "Liste d'autorités Genèse", "list_code" => "th298"],
    ["nom" => "Liste d'autorités Utilisation", "list_code" => "th304"],
    ["nom" => "Liste d'autorités Rôle", "list_code" => "th302"],
    ["nom" => "Liste d'autorités Source de la représentation", "list_code" => "th286"],
    ["nom" => "Liste d'autorités Représentation", "list_code" => "th285"]
];
foreach ($files as $file_key => $file) {
    $json = file_get_contents($file);

    $datas = json_decode($json, true);
    $data_array = [];


    foreach ($datas as $key => $node) {
        $gotChildren = false;
        if (!empty($node["http://www.w3.org/2004/02/skos/core#narrower"])) {
            foreach ($node["http://www.w3.org/2004/02/skos/core#narrower"] as $children) {
                $gotChildren[] = $children["value"];
            }
        }
        $data_array[$key] = ["identifier" => reset($node["http://purl.org/dc/terms/identifier"])["value"], "nom" => reset($node["http://www.w3.org/2004/02/skos/core#prefLabel"])["value"], "enfant" => $gotChildren];
    }

    foreach ($datas as $key => $node) {
        if (empty($node["http://www.w3.org/2004/02/skos/core#broader"])) {
            
            $principauxParent[] = $key;
        }
    }
    $table = [];
    foreach ($principauxParent as $name =>$parent) {
         $parent_id = $parent;
        $parent = $data_array[$parent];
        $parentSubElement = treatChild($parent["enfant"], []);
        $parent["subElement"] = $parentSubElement;
        unset($parent["enfant"]);
        $table[] = $parent;
    }
    $list = new ca_lists();
    $list->load(["list_code" => $tableauDeCorrespondance[$file_key]["list_code"], "deleted" => 0]);
    $list->setMode(ACCESS_WRITE);
    if (!$list_id = $list->getPrimaryKey()) {
        print "Creating list : ".$tableauDeCorrespondance[$file_key]["nom"]." with list code : ".$tableauDeCorrespondance[$file_key]["list_code"];
        $list->set(["list_code" => $tableauDeCorrespondance[$file_key]["list_code"]]);
        $list_id = $list->insert();
        if ($list->numErrors()) {
            var_dump($list->getErrors());
            die();
        }
    }
    $list->removeAllLabels();
    $list->addLabel(["name" => $tableauDeCorrespondance[$file_key]["nom"]], 2, null, true);

    $list->update();
    foreach ($table as $item) {

        $vt_item = new ca_list_items();
        $vt_item->load(["idno" => $item["identifier"], "deleted" => 0, "list_id" => $list_id]);
        $vt_item->setMode(ACCESS_WRITE);

        if (!$vt_item->getPrimaryKey()) {
            $vt_item->set(["idno" => $item['identifier'], "list_id" => $list_id, "access" => 1, "status" => 2, "is_enabled" => 1, "item_value" => $item["nom"], "type_id" => 2]);
            $vt_item->insert();
        }
        $vt_item->removeAllLabels();
        $vt_item->addLabel(["name_singular" => $item["nom"], "name_plural" => $item["nom"]], 2, null, true);
        $vt_item->set(["is_enabled" => 1]);

        $vt_item->update();

        if ($vt_item->numErrors()) {
            var_dump($vt_item->getErrors());
            die();
        }

        if ($item["subElement"]) {
            insertListItem($item["subElement"], $vt_item->getPrimaryKey(), $list_id);
        }
    }
}


function insertListItem($children, $parent_id, $list_id)
{
    foreach ($children as $child) {
        $vt_item = new ca_list_items();
        $vt_item->load(["idno" => $child["identifier"], "deleted" => 0, "list_id" => $list_id]);
        $vt_item->setMode(ACCESS_WRITE);
        if (!$vt_item->getPrimaryKey()) {
            $vt_item->setMode(ACCESS_WRITE);
            if ($parent_id != null){
                $vt_item->set(["idno" => $child['identifier'], "list_id" => $list_id, "access" => 1, "status" => 2, "is_enabled" => 1, "item_value" => $child["nom"], "type_id" => 2, "parent_id" => $parent_id]);
            }else{
                $vt_item->set(["idno" => $child['identifier'], "list_id" => $list_id, "access" => 1, "status" => 2, "is_enabled" => 1, "item_value" => $child["nom"], "type_id" => 2]);
            }
            $vt_item->insert();
        }
        $vt_item->removeAllLabels();
        if ($parent_id!=null){
            $vt_item->set(["parent_id" => $parent_id, "is_enabled" => 1]);
        }else{
            $vt_item->set(["is_enabled" => 1]);
        }
        $vt_item->addLabel(["name_singular" => $child["nom"], "name_plural" => $child["nom"]], 2, null, true);
        $vt_item->update();
        if ($vt_item->numErrors()) {
            var_dump($vt_item->getErrors());
            die();
        }
        if ($child["subElement"]) {
            insertListItem($child["subElement"], $vt_item->getPrimaryKey(), $list_id);
        }
    }
}

function treatChild($children, $subElement)
{
    global $data_array;
    foreach ($children as $child) {
        $temp = $data_array[$child];
        if ($temp["enfant"] != false) {
            $subElementTemp = [];
            $temp["subElement"] = treatChild($temp["enfant"], $subElementTemp);
        }
        unset($temp["enfant"]);
        array_push($subElement, $temp);
    }
    return $subElement;
}
