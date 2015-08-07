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
    parent::__construct($po_request, $po_response, $pa_view_paths);
}

    public function Index()
{
    $this->render('inventaire_biens_deposes_index_html.php');
}

}