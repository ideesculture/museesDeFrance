<?php
/**
 * Created by PhpStorm.
 * User: gautier
 * Date: 07/08/15
 * Time: 09:16
 */

require_once("BaseObjectInventaire.php");
require_once("helpers/mapping_biensaffectes.php");

class BienAffecte extends BaseObjectInventaire {
    function __construct($num = null)
    {
        $this->tablename = "inventaire_inventaire";
        $this->fields = array("numinv","designation","materiaux","techniques","mesures","etat","epoque","utilisation","provenance");
        $this->mapping = getMappingBiensAffectes();

        parent::__construct($num);
    }
}