<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require "../../../../setup.php";

require_once(__CA_MODELS_DIR__ . '/ca_lists.php');
require_once(__CA_MODELS_DIR__ . '/ca_list_items.php');
require_once(__CA_MODELS_DIR__ . '/ca_places.php');

$files = [
    "https://opentheso.huma-num.fr/opentheso/api/all/theso?id=th284&format=json", // Lieux

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
    foreach ($principauxParent as $name => $parent) {
        $parent_id = $parent;
        $parent = $data_array[$parent];
        $parentSubElement = treatChild($parent["enfant"], []);
        $parent["subElement"] = $parentSubElement;
        unset($parent["enfant"]);
        $table[] = $parent;
    }

    foreach ($table as $item) {
        var_dump($item["nom"]);
        if ($item["nom"] == "affixe") {
            //TODO ON CREE UNE LISTE
            $list_affixe = new ca_lists();
            $list_affixe->load(["list_code" => "affixe_lieu", "deleted" => 0]);
            $list_affixe->setMode(ACCESS_WRITE);
            if (!$list_id_affixe = $list_affixe->getPrimaryKey()) {
                $list_affixe->set(["list_code" => "affixe_lieu"]);
                $list_id_affixe = $list_affixe->insert();
                if ($list_affixe->numErrors()) {
                    var_dump($list_affixe->getErrors());
                    die();
                }
            }
            $list_affixe->removeAllLabels();
            $list_affixe->addLabel(["name" => "Affixe des lieux"], 2, null, true);

            $list_affixe->update();

            // ON IMPORTE SES ENFANTS
            if ($item["subElement"]) {
                insertListItem($item["subElement"], null, $list_id_affixe);
            }
            continue;
        }

        $vt_place = new ca_places();
        $vt_place->load(["idno" => $item["identifier"], "deleted" => 0]);
        $vt_place->setMode(ACCESS_WRITE);

        if (!$vt_place->getPrimaryKey()) {
            $vt_place->set("hierarchy_id", 533);
            $vt_place->set(["idno" => $item['identifier'], "access" => 1, "status" => 2, "type_id" => 129, "parent_id" => 1, "locale_id" => 2]);
            $vt_place->insert();
            if ($vt_place->numErrors()) {
                var_dump($vt_place->getErrors());
                die();
            }
        }
        $vt_place->removeAllLabels();
        $vt_place->addLabel(["name" => $item["nom"]], 2, null, true);
        $vt_place->update();

        if ($vt_place->numErrors()) {
            var_dump($vt_place->getErrors());
            die();
        }

        if ($item["subElement"]) {
            insertPlace($item["subElement"], $vt_place->getPrimaryKey());
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
            if ($parent_id != null) {
                $vt_item->set(["idno" => $child['identifier'], "list_id" => $list_id, "access" => 1, "status" => 2, "is_enabled" => 1, "item_value" => $child["nom"], "type_id" => 2, "parent_id" => $parent_id]);
            } else {
                $vt_item->set(["idno" => $child['identifier'], "list_id" => $list_id, "access" => 1, "status" => 2, "is_enabled" => 1, "item_value" => $child["nom"], "type_id" => 2]);
            }
            $vt_item->insert();
        }
        $vt_item->removeAllLabels();
        if ($parent_id != null) {
            $vt_item->set(["parent_id" => $parent_id, "is_enabled" => 1]);
        } else {
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

function insertPlace($children, $parent_id)
{
    foreach ($children as $child) {
        var_dump($child["nom"]);
        $vt_place = new ca_places();
        $vt_place->load(["idno" => $child["identifier"], "deleted" => 0]);
        $vt_place->setMode(ACCESS_WRITE);
        if (!$vt_place->getPrimaryKey()) {
            $vt_place->setMode(ACCESS_WRITE);
            $vt_place->set("hierarchy_id", 533);
            if ($parent_id != null) {
                $vt_place->set(["idno" => $child['identifier'], "access" => 1, "status" => 2, "type_id" => 129, "parent_id" => $parent_id]);
            } else {
                $vt_place->set(["idno" => $child['identifier'], "access" => 1, "status" => 2, "type_id" => 129]);
            }
            $vt_place->insert();
        }
        if ($parent_id != null) {
            $vt_place->set(["parent_id" => $parent_id]);
        }
        $vt_place->removeAllLabels();
        $vt_place->addLabel(["name" => $child["nom"]], 2, null, true);
        $vt_place->update();
        if ($vt_place->numErrors()) {
            var_dump($vt_place->getErrors());
            die();
        }
        if ($child["subElement"]) {
            insertPlace($child["subElement"], $vt_place->getPrimaryKey());
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
