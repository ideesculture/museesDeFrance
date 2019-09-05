<?php
/**
 * Created by PhpStorm.
 * User: gautier
 * Date: 08/08/15
 * Time: 09:31
 */

require_once("InterfaceRegistre.php");
require_once("BaseObjectInventaire.php");

class BaseRegistre implements InterfaceRegistre {

    public $objectmodel;

    // search field name for num, default is "numinv"
    public $numtype;

    // db storage table name
    public $tablename;
    // array of field names for storage, should correspond to basic content properties names
    public $fields;

    // configuration facilities
    public $opo_config;
    // db object
    public $opo_db;

    function __construct($num = null) {

        if (!isset($this->numtype)) {
            $this->numtype = "numinv";
        }
        if (!isset($this->tablename)) {
            $this->tablename = "inventaire_inventaire";
            $this->fields = array("numinv","designation");
        }
        if (!isset($this->objectmodel)) {
            $this->objectmodel = "BienAffecte";
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

    public function count() {
        $qr_res = $this->opo_db->query("SELECT count(*) as number FROM ".$this->tablename." WHERE validated=1");
        if ($qr_res->numRows() > 0) {
            $qr_res->nextRow();
            return $qr_res->get("number");
        } else {
            return false;
        }
    }

    public function getYears() {
        $qr_res = $this->opo_db->query("SELECT distinct right(date_inscription_display,4) as years FROM ".$this->tablename);
        $va_results=array();
        if ($qr_res->numRows() > 0) {
            while($qr_res->nextRow()) {
                $va_results[] = $qr_res->get("years");
            }
            return $va_results;
        } else {
            return false;
        }
    }


    function getObjects($year = null, $num_start = null, $designation = null) {
        $vs_request = "SELECT ca_id FROM ".$this->tablename;
        if($year) $vs_request_where =" WHERE right(date_inscription_display,4)=\"".$year."\"";
        if($num_start) $vs_request_where .=
            ($vs_request_where == "" ? " WHERE ": " AND ")
            .$this->numtype."_display LIKE \"".$num_start."%\"";
        if($designation) $vs_request_where .=
            ($vs_request_where == "" ? " WHERE ": " AND ")
            ."designation LIKE \"%".$designation."%\"";
        $vs_request_order = " ORDER BY numinv_sort ASC";    
        $qr_res = $this->opo_db->query($vs_request.$vs_request_where.$vs_request_order);
        $va_results = array();
        if ($qr_res->numRows() > 0) {
            while($qr_res->nextRow()) {
                $vt_object = new $this->objectmodel;
                $vt_object->loadByCaID($qr_res->get("ca_id"));
                $va_results[] = $vt_object;
            }
            return $va_results;
        } else {
            return false;
        }
    }
}
