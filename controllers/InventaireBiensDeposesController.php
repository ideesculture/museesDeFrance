<?php
require_once(__CA_APP_DIR__."/plugins/museesDeFrance/lib/inventaire/BienDepose.php");
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
        $this->render('inv_bd/inventaire_biens_deposes_index_html.php');
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
        $this->render('inv_bd/inventaire_biens_deposes_transfert_html.php');
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

    # -------------------------------------------------------
    # Sidebar info handler
    # -------------------------------------------------------
    public function Info($pa_parameters) {
        return $this->render('inventaireBiensAffectes/widget_inventaire_info_html.php', true);
    }

    public function RenderPdf() {
        $wkhtmltopdf_app = $this->opo_external_app_config->get('wkhtmltopdf_app');
        print "<style>html, body {font-family: monospace;}</style>";
        $command = 'cd '.__CA_APP_DIR__.'/plugins/museesDeFrance/tmp/ && '.$wkhtmltopdf_app.' --footer-right "[page]/[topage]" --footer-font-size 8 inventaire.html inventaire.pdf';
        //print $command;
        $result = liveExecuteWkhtmlToPdfCommand($command);

        if($result['exit_status'] === 0){
            // do something if command execution succeeds
            print "---------------------<br/>";
            print "<a target='_blank' href='".__CA_URL_ROOT__."/app/plugins/museesDeFrance/tmp/inventaire.pdf'>Télécharger l'inventaire généré</a> <small>Attention, fichier souvent de plus 100 MO.</small>";

        } else {
            print "Error : ".$result['exit_status'];
        }
        die();
    }
}