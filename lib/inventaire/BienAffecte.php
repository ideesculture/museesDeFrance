<?php
/**
 * Created by PhpStorm.
 * User: gautier
 * Date: 07/08/15
 * Time: 09:16
 */

require_once("BaseObjectInventaire.php");
require_once("helpers/mapping_biensaffectes.php");

class BienAffecte extends BaseObjectInventaire {
    // serie of basic content properties
    //public $id;
    //public $ca_id;
    //public $numinv;
    public $numinv;
    public $numinv_sort;
    public $numinv_display;
    //public $designation;
    //public $designation_display;
    public $mode_acquisition;
    public $donateur;
    public $date_acquisition;
    public $avis;
    public $prix;
    public $date_inscription;
    public $date_inscription_display;
    public $observations;
    public $inscriptions;
    //public $materiaux;
    //public $techniques;
    //public $mesure;
    //public $etat;
    public $auteur;
    public $auteur_display;
    //public $epoque;
    //public $utilisation;
    //public $provenance;
    //public $validated;

    function __construct($num = null)
    {
        $this->tablename = "inventaire_inventaire";
        $this->fields = array("ca_id", "numinv","numinv_sort","numinv_display", "designation", "designation_display", "mode_acquisition", "donateur", "date_acquisition", "avis", "prix", "date_inscription", "date_inscription_display", "observations", "inscription", "materiaux", "techniques", "mesures", "etat", "auteur", "auteur_display", "epoque", "utilisation","provenance");

        $this->mapping = getMappingBiensAffectes();

        parent::__construct($num);
    }

    function getHtmlTableHeaderRow() {
        return "<thead><tr><th>Numéro d'inventaire</th><th>Désignation</th><th>Auteur</th><th>Date d'inscription</th><th>Fonctions</th></tr></thead>";
    }

    function getHtmlTableRowContent() {
        return "<td>".$this->numinv_display."</td><td>".$this->designation_display."</td><td>".$this->auteur_display."</td><td>".$this->date_inscription_display."</td><td>
        <img src='/themes/default/graphics/buttons/glyphicons_198_ok.png' alt='glyphicons_198_ok' border='0' align='middle'>
        <img src='/themes/default/graphics/buttons/glyphicons_197_remove.png' alt='glyphicons_197_remove' border='0' align='middle'>
        <img src='/themes/default/graphics/buttons/glyphicons_211_right_arrow.png' alt='glyphicons_211_right_arrow' border='0' align='middle'>
        </td>";
    }
}