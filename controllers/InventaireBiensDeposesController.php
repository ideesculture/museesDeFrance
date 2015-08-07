<?php
/**
 * Created by PhpStorm.
 * User: gautier
 * Date: 07/08/15
 * Time: 09:00
 */
class InventaireBiensDeposesController extends ActionController
{
    # -------------------------------------------------------
    #
    # -------------------------------------------------------
    public function __construct(&$po_request, &$po_response, $pa_view_paths = null)
{
    $this->tablename = "inventaire_depot";
    $this->fields = array("numdep","numinv","date_ref_acte_depot","date_entree", "proprietaire", "date_ref_acte_fin" ,
        "date_inscription","designation","inscription","materiaux","techniques","mesures","etat","epoque",
        "utilisation","provenance","observations");

    parent::__construct($po_request, $po_response, $pa_view_paths);
}

    public function Index()
{
    $this->render('inventaire_biens_deposes_index_html.php');
}

}