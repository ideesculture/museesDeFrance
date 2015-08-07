<?php
require_once(__CA_APP_DIR__."/plugins/museesDeFrance/lib/inventaire/BienAffecte.php");
require_once(__CA_MODELS_DIR__."/ca_objects.php");

class InventaireBiensAffectesController extends ActionController
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
        $this->render('inventaire_biens_affectes_index_html.php');
    }

    public function Transfer()
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
        $this->render('inventaire_biens_affectes_transfer_html.php');
    }

    public function Validate()
    {
        $vs_object_id = $this->request->getParameter("id",pInteger);

        $vt_object = new ca_objects($vs_object_id);
        $vs_idno = $vt_object->get("idno");

        $vo_bienaffecte = new BienAffecte($vs_idno);
        $vo_bienaffecte->validate();
        //var_dump($vo_bienaffecte);
        //die();

        $this->render('inventaire_biens_affectes_validate_html.php');
    }

    public function Modify()
    {
        $vo_bienaffecte = new BienAffecte("1");
        $vo_bienaffecte->set("epoque","Louis XV");
        $vo_bienaffecte->set("designation","Le titre");
        $vo_bienaffecte->save();
    }

    public function Creation()
    {
        $vo_bienaffecte = new BienAffecte("2");
        $vo_bienaffecte->set("designation","Titre 2");
        $vo_bienaffecte->set("epoque","Louis XVI");
        $vo_bienaffecte->save();
        $vo_bienaffecte->set("avis","bon");
        $vo_bienaffecte->save();
        $vo_bienaffecte->validate();
        $vo_bienaffecte->set("avis","mauvais");
        $vo_bienaffecte->save();
    }
}