<?php
/**
 * Created by PhpStorm.
 * User: gautier
 * Date: 08/08/15
 * Time: 09:33
 */

require_once("BaseRegistre.php");

class RegistreBiensAffectes extends BaseRegistre {

    function __construct($num = null)
    {
        $this->tablename = "inventaire_inventaire";
        $this->fields = array("numinv","designation","materiaux","techniques","mesures","etat","epoque","utilisation","provenance");
        $this->numtype = "numinv";
        $this->objectmodel = "BienAffecte";

        parent::__construct($num);
    }

}