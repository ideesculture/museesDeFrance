<?php
/**
 * Created by PhpStorm.
 * User: gautier
 * Date: 07/08/15
 * Time: 09:16
 */

require_once("BaseObjectInventaire.php");
if(is_file(__CA_APP_DIR__."/plugins/museesDeFrance/lib/inventaire/helpers/local/mapping_biensdeposes.php")) {
    require_once(__CA_APP_DIR__."/plugins/museesDeFrance/lib/inventaire/helpers/local/mapping_biensdeposes.php");
} else {
    require_once(__CA_APP_DIR__."/plugins/museesDeFrance/lib/inventaire/helpers/mapping_biensdeposes.php");
}


class BienDepose extends BaseObjectInventaire {
    public $numdep;

    function __construct($num = null)
    {
        $this->tablename = "inventaire_depot";
        $this->fields = array("numdep","numinv","date_ref_acte_depot","date_entree", "proprietaire", "date_ref_acte_fin" ,
            "date_inscription","designation","inscription","materiaux","techniques","mesures","etat","epoque",
            "utilisation","provenance","observations");
        $this->mapping = getMappingBiensDeposes();

        parent::__construct($num);
    }
}