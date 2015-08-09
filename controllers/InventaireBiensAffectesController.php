<?php
require_once(__CA_APP_DIR__."/plugins/museesDeFrance/lib/inventaire/BienAffecte.php");
require_once(__CA_APP_DIR__."/plugins/museesDeFrance/lib/inventaire/RegistreBiensAffectes.php");
require_once(__CA_MODELS_DIR__."/ca_objects.php");

class InventaireBiensAffectesController extends ActionController
{
    # -------------------------------------------------------
    #
    # -------------------------------------------------------
    public function __construct(&$po_request, &$po_response, $pa_view_paths = null)
    {
        parent::__construct($po_request, $po_response, $pa_view_paths);

        // Global vars for all children views
        $this->view->setVar('plugin_dir', __CA_BASE_DIR__."/app/plugins/museesDeFrance");
        $this->view->setVar('plugin_url', __CA_URL_ROOT__."/app/plugins/museesDeFrance");
    }

    public function Index()
    {
        $vt_registre = new RegistreBiensAffectes();
        $this->view->setVar("registre",$vt_registre);

        $this->view->setVar('objects_nb',$vt_registre->count());
        $this->render('inventaire_biens_affectes_index_html.php');
    }

    public function Transfer()
    {
        $vs_object_id = $this->request->getParameter("id",pInteger);

        $vt_object = new ca_objects($vs_object_id);

        $vs_idno = $vt_object->get("idno");
        $vs_name = $vt_object->get("ca_objects.preferred_labels.name");

        $vo_bienaffecte = new BienAffecte();
        //var_dump($vo_bienaffecte);die();
        $vo_bienaffecte->loadByCaID($vs_object_id);
        $vo_bienaffecte->fill($vt_object);
        $vo_bienaffecte->save();

        $this->view->setVar('idno', $vs_idno);
        $this->view->setVar('name', $vs_name);
        $this->view->setVar('id', $vs_object_id);
        $this->render('inventaire_biens_affectes_transfer_html.php');
    }

    public function Validate()
    {
        $vs_object_id = $this->request->getParameter("object_id",pInteger);

        $vt_object = new ca_objects($vs_object_id);
        $vs_idno = $vt_object->get("idno");
        $vs_name = $vt_object->get("ca_objects.preferred_labels.name");

        $vo_bienaffecte = new BienAffecte();
        $vo_bienaffecte->loadByCaID($vs_object_id);
        //var_dump($vo_bienaffecte);
        //die();

        $this->view->setVar('idno', $vs_idno);
        $this->view->setVar('name', $vs_name);
        $this->view->setVar('id', $vs_object_id);

        $this->render('inventaire_biens_affectes_validate_html.php');
    }

    public function Remove()
    {
        $vs_object_id = $this->request->getParameter("object_id",pInteger);

        $vt_object = new ca_objects($vs_object_id);
        $vs_idno = $vt_object->get("idno");
        $vs_name = $vt_object->get("ca_objects.preferred_labels.name");

        $vo_bienaffecte = new BienAffecte();
        $vo_bienaffecte->loadByCaID($vs_object_id);
        $vb_result = $vo_bienaffecte->delete();
        if (!$vb_result) die("Impossible de supprimer un objet validé.");
        //var_dump($vo_bienaffecte);
        //die();

        $this->view->setVar('idno', $vs_idno);
        $this->view->setVar('name', $vs_name);
        $this->view->setVar('id', $vs_object_id);

        $this->render('inventaire_biens_affectes_remove_html.php');
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

    public function About() {
        $this->render('inventaire_about_html.php');
    }

    # -------------------------------------------------------
    # Sidebar info handler
    # -------------------------------------------------------
    public function Info($pa_parameters)
    {
        return $this->render('widget_inventaire_info_html.php', true);
    }

}