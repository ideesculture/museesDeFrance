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
    public function Transfert()
    {
        $vs_object_id = $this->request->getParameter("id",pInteger);

        $vt_object = new ca_objects($vs_object_id);
        $vs_idno = $vt_object->get("idno");
        $vs_name = $vt_object->get("ca_objects.preferred_labels.name");

        $vo_bienaffecte = new BienAffecte($vs_idno);
        $vo_bienaffecte->set("designation",$vs_name);
        $vo_bienaffecte->save();
        //var_dump($vo_bienaffecte);
        //die();

        $this->view->setVar('idno', $vs_idno);
        $this->view->setVar('name', $vs_name);
        $this->render('inventaire_biens_deposes_transfert_html.php');
    }

}