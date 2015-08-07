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

        $vo_biendepose = new BienDepose($vs_idno);
        $vo_biendepose->set("designation",$vs_name);
        $vo_biendepose->save();
        $this->view->setVar('idno', $vs_idno);
        $this->view->setVar('name', $vs_name);
        $this->render('inventaire_biens_deposes_transfert_html.php');
    }

    public function Modification()
    {
        $vo_biendepose = new BienDepose("1");
        $vo_biendepose->set("epoque","Louis XV");
        $vo_biendepose->set("designation","Le titre");
        $vo_biendepose->save();
    }

    public function Creation()
    {
        $vo_biendepose = new BienDepose("2");
        $vo_biendepose->set("designation","Titre 2");
        $vo_biendepose->set("epoque","Louis XVI");
        $vo_biendepose->save();
        $vo_biendepose->set("avis","bon");
        $vo_biendepose->save();
        $vo_biendepose->validate();
        $vo_biendepose->set("avis","mauvais");
        $vo_biendepose->save();
    }
}