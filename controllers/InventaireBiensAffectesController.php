<?php
require_once(__CA_APP_DIR__."/plugins/museesDeFrance/lib/inventaire/BienAffecte.php");

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

    public function Modification()
    {
        $vo_bienaffecte = new BienAffecte("1");
        $vo_bienaffecte->set("epoque","Louis XV");
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