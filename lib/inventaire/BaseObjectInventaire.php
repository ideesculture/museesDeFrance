<?php
/**
 * Created by PhpStorm.
 * User: gautier
 * Date: 07/08/15
 * Time: 09:14
 */

require_once("InterfaceInventaire.php");
require_once(__CA_LIB_DIR__."/core/Db.php");

class BaseObjectInventaire implements InterfaceInventaire {

    // serie of basic content properties
    public $id;
    public $ca_id;
    public $ca_idno;
    public $numinv;
    public $designation;
    public $designation_display;
    public $materiaux;
    public $techniques;
    public $mesures;
    public $etat;
    public $epoque;
    public $utilisation;
    public $provenance;

    public $date_inscription;
    public $date_inscription_display;

    public $validated;

    // photo asset
    public $file;

    // search field name for num, default is "numinv"
    public $numtype;

    // flag on if record already in the db
    private $exists;

    // db storage table name
    public $tablename;
    // array of field names for storage, should correspond to basic content properties names
    public $fields;

    // configuration facilities
    private $opo_config;
    // db object
    private $opo_db;

    function __construct() {

        if (!isset($this->numtype)) {
            $this->numtype = "numinv";
        }
        if (!isset($this->tablename)) {
            $this->tablename = "inventaire_inventaire";
            $this->fields = array("numinv","designation");
        }

        $ps_plugin_path = __CA_BASE_DIR__ . "/app/plugins/museesDeFrance";
        if (file_exists($ps_plugin_path . '/conf/local/museesDeFrance.conf')) {
            $this->opo_config = Configuration::load($ps_plugin_path . '/conf/local/museesDeFrance.conf');
        } else {
            $this->opo_config = Configuration::load($ps_plugin_path . '/conf/museesDeFrance.conf');
        }

        $this->opo_db = New Db("", array(
            "username" => 	$this->opo_config->get("db_user"),
            "password" => 	$this->opo_config->get("db_password"),
            "host" =>	 	$this->opo_config->get("db_host"),
            "database" =>	$this->opo_config->get("db_database"),
            "type" =>		"mysql"
        ));
    }

    /**
     * Test if this BaseObjectInventaire already exists inside the database or an arbitrary one based on its num
     * @param $num null or int : null if testing $this, int of a num if testing a non loaded object
     * @return bool true (object exists in the DB), false (object doesn't exist)
     */
    private function _exists() {
        if(!isset($this->id)) return false;
        $qr_res = $this->opo_db->query("SELECT id FROM ".$this->tablename." WHERE id=".$this->id);

        if ($qr_res->numRows() > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Load object properties from the DB
     * @param $num null or int : null if testing $this, int of a num if testing a non loaded object
     * @return bool
     */
    private function _load() {
        $num=$this->{$this->numtype};
        $qr_res = $this->opo_db->query("SELECT * FROM ".$this->tablename." WHERE ".$this->numtype." = ? LIMIT 1",$num);
        //var_dump($vs_request);die();
        if ($qr_res->numRows() > 0) {
            $qr_res->nextRow();
            $va_row = $qr_res->getRow();
            foreach($va_row as $name => $value) {
                if (property_exists(get_class($this), $name)) {
                    $this->$name = $value;
                }
            }
            $this->id=$qr_res->get("id");
            $this->_loadFile();
            return true;
        } else {
            return false;
        }
    }

    private function _loadFile() {
        if(!$this->id) return false;
        $vs_request="SELECT file FROM ".$this->tablename."_photo WHERE record_id=".$this->id;
        //var_dump($vs_request);die();

        $qr_res = $this->opo_db->query($vs_request);
        if ($qr_res->numRows() == 1) {
            $qr_res->nextRow();
            $this->file = $qr_res->get("file");
            return true;
        } else {
            return false;
        }
    }

    /**
     * Load object properties from the DB
     * @param $num null or int : null if testing $this, int of a num if testing a non loaded object
     * @return bool
     */
    public function loadByCaID($vn_object_id) {
        if(!$vn_object_id) return false;
        $vs_request = "SELECT * FROM ".$this->tablename." WHERE ca_id = ".$vn_object_id." LIMIT 1";
        $qr_res = $this->opo_db->query($vs_request);
        if ($qr_res->numRows() > 0) {
            $qr_res->nextRow();
            $va_row = $qr_res->getRow();
            foreach($va_row as $name => $value) {
                if (property_exists(get_class($this), $name)) {
                    $this->$name = $value;
                }
            }
            $this->id=$qr_res->get("id");
            $this->_loadFile();
            return true;
        } else {
            return false;
        }
    }

    /**
     * Dynamic getter, can get existing properties even for children objects
     * @param $name
     * @return bool
     */
    function get($name) {
        if (property_exists(get_class($this), $name)) {
            return $this->$name;
        }
        return false;
    }

    /**
     * Dynamic setter, can set existing properties even for children objects
     * @param $name
     * @param $value
     * @return bool
     */
    function set($name, $value) {
        if (($name == "validated") || ($this->validated)) return false;
        if (property_exists($this, $name)) {
            $this->$name = $value;
            return true;
        }
        return false;
    }

    function fill($t_object) {
        $t_locale = new \ca_locales();
        $locale_id = $t_locale->loadLocaleByCode('fr_FR'); // Stockage explicite en français
        unset($t_locale);

        $this->set("ca_id", $t_object->get("object_id"));

        /*foreach($this->mapping  as $name => $field) {
            $tempstring = "";
            foreach ($field as $values) {
                if($pt_object->get($values["field"])){
                    if ($values["prefixe"]) {
                        $tempstring .= $values["prefixe"];
                    }
                    $tempstring .= $pt_object->get($values["field"], array("convertCodesToDisplayText"=>true));
                    //var_dump($pt_object->get($values["field"], array("convertCodesToDisplayText"=>true)));
                    if ($values["suffixe"]) {
                        $tempstring .= $values["suffixe"];
                    }
                }
            }
            // Converting quotes to french typographic quotes
            $tempstring = str_replace("'","’",$tempstring);
            // Escaping double quotes to allow safe MySQL insertion
            $tempstring = str_replace("\"","\\\"",$tempstring);
            if ($tempstring) $this->set($name, $tempstring);
        }*/

       foreach ($this->mapping as $target => $fields) {
            $response_global = "";
            foreach($fields as $attribute) {
                $response = "";
                $field = $attribute["field"];
                
                /*if($field == "ca_objects.dimensions") {
	                var_dump($t_object->get("$field"));
	                die();
                }*/
                $data = explode(".",$field);
                if(isset($attribute["template"])) {
	                $response = $t_object->getWithTemplate($attribute["template"]);
	                $response_global .= $attribute["prefixe"].$response.$attribute["suffixe"];
	                continue;
                } else {
	                switch($data[0]) {
                    case "ca_entities" :
                        $entities = $t_object->getRelatedItems("ca_entities",array("restrictToRelationshipTypes"=>$attribute["relationshipTypes"]));
                        foreach($entities as $entity) {
                            $response = ($response ? $response.", " : "").$entity["displayname"];
                        }
                        break;
                    case "ca_places" :
                        $places = $t_object->getRelatedItems("ca_places",array("restrictToRelationshipTypes"=>$attribute["relationshipTypes"]));
                        foreach($places as $place) {
                            $response = ($response ? $response.", " : "").$place["name"];
                        }
                        break;
                    case "ca_objects" :
                    default:
                        if ($field != "ca_objects.nonpreferred_labels") {
                            // GESTION DES OPTIONS POUR LE get()
                            $options = array("convertCodesToDisplayText"=>"true", "locale"=>$locale_id);
                            if ($attribute["options"]) $options = array_merge($options,$attribute["options"]);
                            // RECUPERATION DU CHAMP POUR L'AFFICHAGE
                            $response = $t_object->get($field, $options);

                            // POST-TRAITEMENT
                            if (($attribute["post-treatment"]) && ($response)) {
                                switch($attribute["post-treatment"]) {
                                    // Conversion monétaire
                                    case 'convertcurrencytoeuros' :
                                        if ($response) {
                                            preg_match('/([[:graph:]]*) ([[:graph:]]*)/i',$response, $matches);
                                            if ($matches[1] != "EUR") {
                                                $conversionresult = $this->convertcurrency($matches[1], "EUR", $matches[2]);
                                                if($conversionresult) {
                                                    $response=$conversionresult;
                                                } else {
                                                    throw new \Exception("Erreur dans la conversion de devise de ".$response." en euros ($response).");
                                                }
                                            } else {
                                                $response = $matches[2];
                                            }
                                            // Remplacement du point par la virgule
                                            $response = str_replace(".", ",",$response)." €";
                                        }
                                        break;
                                    // Conversion vers une date au format JJ/MM/AAAA
                                    case 'caDateToUnixTimestamp' :
                                        $response = date('d/m/Y',caDateToUnixTimestamp($response));
                                        break;
                                    case 'keepOnlyFirstValue':
	                                    $response=reset(explode(";", $response));
	                                    break;
                                    case 'ddmmYYYY':
                                        $o_tep = new TimeExpressionParser();
                                        $o_tep->setLanguage("en_US");
                                        $o_tep->parse($response);
                                        $parsed_date = reset($o_tep->getHistoricTimestamps());
                                        $year_parsed_date = round($parsed_date);
                                        $month_parsed_date = substr("0".(round($parsed_date*100 - $year_parsed_date*100)),-2);
                                        $day_parsed_date = round($parsed_date*10000 - $year_parsed_date*10000 - $month_parsed_date*100);
                                        $response = $day_parsed_date."/".$month_parsed_date."/".$year_parsed_date;
                                        //var_dump(caDateToHistoricTimestamps($response));
										break;

                                    // Post-traitement non reconnu
                                    default :
                                        throw new \Exception("Post-traitement non reconnu : ".$attribute["post-treatment"]);
                                }
                            }
                        } else {
                            // non preferred_labels
                            $nonpreferred_labels = $t_object->get('ca_objects.nonpreferred_labels', array('returnAsArray' => true));
                            if (sizeof($nonpreferred_labels)>0) { $nonpreferred_labels = reset($nonpreferred_labels); }
                            //var_dump($attribute["otherLabelTypeId"]);
                            //var_dump($nonpreferred_labels);
                            //die();
                            if (sizeof($nonpreferred_labels)>0) {
                                foreach($nonpreferred_labels as $nonpreferred_label) {
                                    if ($nonpreferred_label["type_id"] == $attribute["otherLabelTypeId"]) {
                                        $response .= ($response ? ", ": "").$nonpreferred_label["name"];
                                    }
                                }
                            }
                        }
                        break;
                }
                }
                $response_global .= ($response !="" ? $attribute["prefixe"].$response.$attribute["suffixe"] : "");
            }
           // Converting quotes to french typographic quotes
           $response_global = str_replace("'","’",$response_global);
           // Escaping double quotes to allow safe MySQL insertion
           $response_global = str_replace("\"","\\\"",$response_global);

            // DEFINITION DE L'ATTRIBUT
            $this->set($target, !$response_global ? "non renseigné" : $response_global);
        }
		//die();
        return  true;
    }

    function save() {
        foreach($this->fields as $vs_field) {
            if ($vs_field == "validated") continue;
            if (property_exists(get_class($this),$vs_field)) {
                $va_fields[] = $vs_field;
                $va_values[] = $this->get($vs_field);
            }
        }
        if (!$this->_exists()) {
            // object doesn't exist, insert
            $vs_request = "INSERT INTO ".$this->tablename." (".implode(", ",$va_fields).") VALUES  (\"".implode("\", \"",$va_values)."\")";
            //var_dump($vs_request);die();
            $this->opo_db->query($vs_request);
            $this->loadByCaID($this->ca_id);
            return $this->id;
        } else {
            // object exists, update
            $vs_request = "UPDATE ".$this->tablename." SET ";
            for ($i = 0, $size = count($va_fields); $i < $size; $i++) {
                // escaping the result for valid SQL
                $vs_value = str_replace("\"","\\\"",$va_values[$i]);
                // inserting the value inside the request
                $vs_request .= $va_fields[$i]."=\"".$vs_value."\", ";
            }
            // trick : reuse the $i loop var to finish the request without a trailing comma
            $vs_request .= "validated=\"".($this->validated ? 1 : 0)."\"";
            $vs_request .= " WHERE id=".$this->id;
            $this->opo_db->query($vs_request);

            return $this->id;
        }
    }

    function copyPhoto($pt_object) {
        // Fetching primary media info
        $media = $pt_object->getPrimaryRepresentation(array('large'));
        if ($media && is_file($media["paths"]["large"])) {
            // if we've a media, copy it
            $target = __CA_BASE_DIR__ . "/app/plugins/museesDeFrance/assets/photos/".basename($media["paths"]["large"]);
            if (!copy($media["paths"]["large"], $target)) {
                // copy has crashed
                throw new \Exception("Impossible de recopier le fichier image dans museesDeFrance/assets/photos.");
            }
        } else {
            // no media defined
            $return["error"]="Pas de représentation primaire";
            return $return;
        }
        $file = basename($media["paths"]["large"]);
        $vs_request = "REPLACE INTO ".$this->tablename."_photo (record_id,file) VALUES (".$this->id.",\"".$file."\")";
        //var_dump($vs_request);die();

        $this->opo_db->query($vs_request);
        $return["file"] = $file;
        return $return;
    }

    function delete() {
        if ($this->validated == true) { return false; }
        $vs_request = "DELETE FROM ".$this->tablename." WHERE id=".$this->get("id");
        //var_dump($vs_request);die();

        $this->opo_db->query($vs_request);
        return true;
    }

    function validate($pb_save = true) {
        if ($this->validated == false) {
            $this->date_inscription=date("Y-m-d");
            $this->date_inscription_display=date("d/m/Y");
            $this->validated = true;
            $this->save();
        } else {
            return false;
        }
    }

    function unvalidate($pb_save = true) {
        if ($this->validated == true) {
            $this->validated = false;

            $this->date_inscription="";
            $this->date_inscription_display="";

            if ($pb_save) $this->save();
            return true;
        } else {
            return false;
        }
    }

    function getPhoto() {
        return true;
    }

    function getTitle() {
        return $this->designation;
    }

    function getInventaireNumber() {
        return $this->numinv;
    }
    function getHTML() {
        return true;
    }
    function getJSON() {
        return true;
    }


}