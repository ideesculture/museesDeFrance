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
        $qr_res = $this->opo_db->query("SELECT count(*) as number FROM ".$this->tablename);
        if ($qr_res->numRows() > 0) {
            $qr_res->nextRow();
            return $qr_res->get("number");
        } else {
            return false;
        }
    }

    function getObjects($year = null) {
        $qr_res = $this->opo_db->query("SELECT ".$this->numtype." FROM ".$this->tablename);
        $va_results = array();
        if ($qr_res->numRows() > 0) {
            while($qr_res->nextRow()) {
                $object_num = $qr_res->get($this->numtype);
                $vt_object = new $this->objectmodel($object_num);
                $va_results[] = $vt_object;
            }
            return $va_results;
        } else {
            return false;
        }
    }
}