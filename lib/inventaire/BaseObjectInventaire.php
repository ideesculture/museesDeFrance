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
    public $mesure;
    public $etat;
    public $epoque;
    public $utilisation;
    public $provenance;
    public $validated;

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

    function __construct($num = null) {

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

        if (isset($num) && $this->_exists($num)) {
            $this->_load($num);
        } else {
            $this->numinv=$num;
        }
    }

    /**
     * Test if this BaseObjectInventaire already exists inside the database or an arbitrary one based on its num
     * @param $num null or int : null if testing $this, int of a num if testing a non loaded object
     * @return bool true (object exists in the DB), false (object doesn't exist)
     */
    private function _exists($num = null) {
        if(!$num) $num=$this->{$this->numtype};
        $qr_res = $this->opo_db->query("SELECT id FROM ".$this->tablename." WHERE ".$this->numtype." = ?",$num);
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
    private function _load($num = null) {
        if(!$num) $num=$this->{$this->numtype};
        $qr_res = $this->opo_db->query("SELECT * FROM ".$this->tablename." WHERE ".$this->numtype." = ? LIMIT 1",$num);
        if ($qr_res->numRows() > 0) {
            $qr_res->nextRow();
            $va_row = $qr_res->getRow();
            foreach($va_row as $name => $value) {
                if (property_exists(get_class($this), $name)) {
                    $this->$name = $value;
                }
            }
            $this->id=$qr_res->get("id");
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
        $qr_res = $this->opo_db->query("SELECT * FROM ".$this->tablename." WHERE ca_id = ? LIMIT 1",$vn_object_id);
        if ($qr_res->numRows() > 0) {
            $qr_res->nextRow();
            $va_row = $qr_res->getRow();
            foreach($va_row as $name => $value) {
                if (property_exists(get_class($this), $name)) {
                    $this->$name = $value;
                }
            }
            $this->id=$qr_res->get("id");
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

    function fill($pt_object) {
        $this->set("ca_id", $pt_object->get("object_id"));
        foreach($this->mapping  as $name => $field) {
            $tempstring = "";
            foreach ($field as $values) {
                if($pt_object->get($values["field"])){
                    if ($values["prefixe"]) {
                        $tempstring .= $values["prefixe"];
                    }
                    $tempstring .= $pt_object->get($values["field"]);

                    if ($values["suffixe"]) {
                        $tempstring .= $values["suffixe"];
                    }
                }
            }
            if ($tempstring) $this->set($name, $tempstring);
        }
        return  true;
    }

    function save() {
        foreach($this->fields as $vs_field) {
            if (property_exists(get_class($this),$vs_field)) {
                $va_fields[] = $vs_field;
                $va_values[] = $this->get($vs_field);
            }
        }
        if (!$this->_exists()) {
            // object doesn't exist, insert
            $vs_request = "INSERT INTO ".$this->tablename." (".implode(", ",$va_fields).") VALUES  (\"".implode("\", \"",$va_values)."\")";
            $this->opo_db->query($vs_request);
        } else {
            // object exists, update
            $vs_request = "UPDATE ".$this->tablename." SET ";
            for ($i = 0, $size = count($va_fields); $i < $size; $i++) {
                $vs_request .= $va_fields[$i]."=\"".$va_values[$i]."\", ";
            }
            // trick : reuse the $i loop var to finish the request without a trailing comma
            $vs_request .= "validated=\"".$this->validated."\"";
            $vs_request .= " WHERE id=".$this->id;
            //var_dump($vs_request);die();
            $this->opo_db->query($vs_request);
        }
    }

    function delete() {
        if ($this->validated == true) { return false; }
        $vs_request = "DELETE FROM ".$this->tablename." WHERE id=".$this->get("id");
        $this->opo_db->query($vs_request);
        return true;
    }

    function validate($pb_save = true) {
        if ($this->validated == false) {
            $this->validated = true;
            $this->save();
        } else {
            return false;
        }
    }

    function unvalidate($pb_save = true) {
        if ($this->validated == true) {
            $this->validated = false;
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