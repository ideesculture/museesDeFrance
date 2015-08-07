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
        $this->tablename = "inventaire_inventaire";
        $this->fields = array("numinv","designation","materiaux","techniques","mesures","etat","epoque","utilisation","provenance");

        parent::__construct($po_request, $po_response, $pa_view_paths);
    }

    public function Index()
    {
        $this->render('inventaire_biens_affectes_index_html.php');
    }

    public function Transfert()
    {
        $vs_object_id = $this->opo_request->getParameter("id",pInteger);
        $vt_object = new ca_objects($vs_object_id);
        $vo_bienaffecte = new BienAffecte($vt_object->get("idno"));
        $vo_bienaffecte->set("designation",$vt_object->get("name"));
        $vo_bienaffecte->save();

        $this->view->setVar('idno', $vt_object->get("idno"));
        $this->view->setVar('name', $vt_object->get("name"));
        $this->render('inventaire_biens_affectes_transfert_html.php');
    }

    public function Modification()
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