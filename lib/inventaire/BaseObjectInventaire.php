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

    public $id;
    public $numinv;
    public $designation;
    public $materiaux;
    public $techniques;
    public $mesure;
    public $etat;
    public $epoque;
    public $utilisation;
    public $provenance;
    public $validated;

    // flag on if record already in the db
    private $exists;

    private $tablename;
    private $fields;

    private $opo_config;
    private $opo_db;

    function __construct($numinv = null) {

        if (!isset($this->tablename)) {
            $this->tablename = "inventaire_inventaire";
            $this->fields = array("numinv","designation","materiaux","techniques","mesures","etat","epoque","utilisation","provenance");
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

        if (isset($numinv) && $this->_exists($numinv)) {
            $this->exists = true;
            $this->_load($numinv);
        } else {
            $this->numinv=$numinv;
            $this->exists = false;
        }

    }

    private function _exists($numinv) {
        $qr_res = $this->opo_db->query("SELECT id FROM ".$this->tablename." WHERE numinv = ?",$numinv);
        if ($qr_res->numRows() > 0) {
            return true;
        } else {
            return false;
        }
    }

    private function _load($numinv) {
        $qr_res = $this->opo_db->query("SELECT * FROM ".$this->tablename." WHERE numinv = ? LIMIT 1",$numinv);
        if ($qr_res->numRows() > 0) {
            $qr_res->nextRow();
            $va_row = $qr_res->getRow();
            foreach($va_row as $name => $value) {
                if (property_exists(get_class($this), $name)) {
                    $this->$name = $value;
                }
            }
            return true;
        } else {
            return false;
        }
    }

    function get($name) {
        if (property_exists(get_class($this), $name)) {
            return $this->$name;
        }
        return false;
    }

    function set($name, $value) {
        if (($name == "validated") || ($this->validated)) return false;
        if (property_exists(get_class($this), $name)) {
            $this->$name = $value;
            return true;
        }
        return false;
    }

    function save() {
        foreach($this->fields as $vs_field) {
            if (property_exists(get_class($this),$vs_field)) {
                $va_fields[] = $vs_field;
                $va_values[] = $this->get($vs_field);
            }
        }
        if (!$this->exists) {
            // object doesn't exist, insert
            $vs_request = "INSERT INTO ".$this->tablename." (".implode(", ",$va_fields).") VALUES  (\"".implode("\", \"",$va_values)."\")";
            //var_dump($vs_request);die();
            $this->opo_db->query($vs_request);
        } else {
            // object exists, update
            $vs_request = "UPDATE ".$this->tablename." SET ";
            for ($i = 0, $size = count($va_fields)-1; $i < $size; $i++) {
                $vs_request .= $va_fields[$i]."=\"".$va_values[$i]."\", ";
            }
            // trick : reuse the $i loop var to finish the request without a trailing comma
            $vs_request .= $va_fields[$size]."=".$va_values[$size];
            $vs_request .= " WHERE id=".$this->get("id");
            var_dump($vs_request);die();
            $this->opo_db->query($vs_request);
        }
    }

    function delete() {
        if ($this->validated == true) { return false; }
        $vs_request = "DELETE FROM ".$this->tablename." WHERE id=".$this->get("id");
        var_dump($vs_request);die();
        $this->opo_db->query($vs_request);
        return true;
    }

    function validate($pb_save = true) {
        if ($this->validated == false) {
            $this->validated = true;
            if ($pb_save) $this->save();
            return true;
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