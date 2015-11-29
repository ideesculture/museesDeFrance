<?php
require_once(__CA_APP_DIR__."/plugins/museesDeFrance/lib/inventaire/BienAffecte.php");
require_once(__CA_APP_DIR__."/plugins/museesDeFrance/lib/inventaire/RegistreBiensAffectes.php");
require_once(__CA_LIB_DIR__.'/core/Parsers/dompdf/dompdf_config.inc.php');
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
        // Check if draft parameter is strictly equal to 0, every other value allows to display drafts
        $vb_hide_drafts = false;
        if (($this->request->getParameter("draft",pInteger) === "0") || ($this->request->getParameter("hidedrafts",pString) === "on")) {
            $vb_hide_drafts = true;
        }
        $this->view->setVar("hide_drafts",$vb_hide_drafts);

        $year = $this->request->getParameter("year",pString);
        $this->view->setVar("year",$year);

        $num_start = $this->request->getParameter("num_start",pString);
        $this->view->setVar("num_start",$num_start);

        $designation = $this->request->getParameter("designation",pString);
        $this->view->setVar("designation",$designation);

        $vt_registre = new RegistreBiensAffectes();
        $this->view->setVar("registre",$vt_registre);

        $this->render('inventaire_biens_affectes/inventaire_biens_affectes_index_html.php');
    }

    public function Photos()
    {
        $vt_registre = new RegistreBiensAffectes();
        $this->view->setVar("registre",$vt_registre);

        $this->view->setVar('objects_nb',$vt_registre->count());
        $this->render('inventaire_biens_affectes/inventaire_biens_affectes_photos_html.php');
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
        $vo_bienaffecte->copyPhoto($vt_object);

        $this->view->setVar('idno', $vs_idno);
        $this->view->setVar('name', $vs_name);
        $this->view->setVar('id', $vs_object_id);
        $this->view->setVar('object', $vo_bienaffecte);
        $this->render('inventaire_biens_affectes/inventaire_biens_affectes_transfer_html.php');
    }

    public function TransferSetAjax()
    {
        // Force error_reporting if errors are printed on screen
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

        $vs_set_id = $this->request->getParameter("id",pInteger);

        $vt_set = new ca_sets($vs_set_id);

        $va_object_ids = array_keys($vt_set->getItemRowIDs());
        $progression = 0;
        $max_progression = count($va_object_ids);

        //type octet-stream. make sure apache does not gzip this type, else it would get buffered
        header('Content-Type: text/octet-stream');
        header('Cache-Control: no-cache'); // recommended to prevent caching of event data.

        foreach($va_object_ids as $vs_object_id) {
            // Progress
            $d = array('message' => "Transfert des objets depuis l'ensemble ".$vs_set_id , 'progress' => round($progression/$max_progression,2)*100);
            echo json_encode($d) . PHP_EOL;
            ob_flush();
            flush();

            $vt_object = new ca_objects($vs_object_id);

            $vs_idno = $vt_object->get("idno");
            $vs_name = $vt_object->get("ca_objects.preferred_labels.name");

            $vo_bienaffecte = new BienAffecte();
            //var_dump($vo_bienaffecte);die();
            $vo_bienaffecte->loadByCaID($vs_object_id);
            $vo_bienaffecte->fill($vt_object);
            $vo_bienaffecte->save();
            $vo_bienaffecte->copyPhoto($vt_object);
            $progression++;
        }
        // Progress end
        $d = array('message' => "Transfert des objets depuis l'ensemble ".$vs_set_id , 'progress' => 100);
        echo json_encode($d) . PHP_EOL;
        ob_flush();
        flush();

        exit();
    }

    public function TransferSet()
    {
        $vs_set_id = $this->request->getParameter("id",pInteger);

        $this->view->setVar('id', $vs_set_id);

        $this->render('inventaire_biens_affectes/inventaire_biens_affectes_transfer_set_html.php');
    }

    public function Validate()
    {
        $vs_object_id = $this->request->getParameter("object_id",pInteger);

        $vt_object = new ca_objects($vs_object_id);
        $vs_idno = $vt_object->get("idno");
        $vs_name = $vt_object->get("ca_objects.preferred_labels.name");

        $vo_bienaffecte = new BienAffecte();
        $vo_bienaffecte->loadByCaID($vs_object_id);
        $vo_bienaffecte->validate();

        $this->view->setVar('idno', $vs_idno);
        $this->view->setVar('name', $vs_name);
        $this->view->setVar('id', $vs_object_id);

        $this->render('inventaire_biens_affectes/inventaire_biens_affectes_validate_html.php');
    }

    public function ValidateAll()
    {
        $vt_registre = new RegistreBiensAffectes();
        $va_objects = $vt_registre->getObjects();
        foreach($va_objects as $vt_inventaire_object) {
            $vt_inventaire_object->validate();
        }
        exit();
    }

    public function Unvalidate()
    {
        $vs_object_id = $this->request->getParameter("object_id",pInteger);

        $vt_object = new ca_objects($vs_object_id);
        $vs_idno = $vt_object->get("idno");
        $vs_name = $vt_object->get("ca_objects.preferred_labels.name");

        $vo_bienaffecte = new BienAffecte();
        $vo_bienaffecte->loadByCaID($vs_object_id);
        $vo_bienaffecte->unvalidate();
        //var_dump($vo_bienaffecte);
        //die();

        $this->view->setVar('idno', $vs_idno);
        $this->view->setVar('name', $vs_name);
        $this->view->setVar('id', $vs_object_id);

        $this->render('inventaire_biens_affectes/inventaire_biens_affectes_unvalidate_html.php');
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

        $this->render('inventaire_biens_affectes/inventaire_biens_affectes_remove_html.php');
    }


    public function Modify()
    {
        $vo_bienaffecte = new BienAffecte("1");
        $vo_bienaffecte->set("epoque","Louis XV");
        $vo_bienaffecte->set("designation","Le titre");
        $vo_bienaffecte->save();
    }

    public function About() {
        $this->render('inventaire_about_html.php');
    }

    public function GeneratePDF() {
        $vt_registre = new RegistreBiensAffectes();
        $this->view->setVar("registre",$vt_registre);

        $dompdf = new DOMPDF();
        //$this->view->setVar('PDFRenderer', $dompdf->getCurrentRendererCode());

        $this->view->setVar('pageWidth', "210mm");
        $this->view->setVar('pageHeight', "297mm");
        $this->view->setVar('marginTop', '1cm');
        $this->view->setVar('marginRight', '1cm');
        $this->view->setVar('marginBottom', '3cm');
        $this->view->setVar('marginLeft', '1cm');

        $vs_content = $this->render("inventaire_biens_affectes_pdf.php");
        var_dump($vs_content );
        die();
        $dompdf->load_html($vs_content);
        $dompdf->render();
        $dompdf->stream("inventaire_biens_affectes.pdf");
    }

    # -------------------------------------------------------
    # Sidebar info handler
    # -------------------------------------------------------
    public function Info($pa_parameters)
    {
        return $this->render('widget_inventaire_info_html.php', true);
    }

}