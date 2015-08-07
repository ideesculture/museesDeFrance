<?php
/**
 * Created by PhpStorm.
 * User: gautier
 * Date: 07/08/15
 * Time: 09:16
 */

require_once("BaseObjectInventaire.php");

class BienAffecte extends BaseObjectInventaire {
    function __construct($num = null)
    {
        $this->tablename = "inventaire_inventaire";
        $this->fields = array("numinv","designation","materiaux","techniques","mesures","etat","epoque","utilisation","provenance");

        parent::__construct($num);
    }
}