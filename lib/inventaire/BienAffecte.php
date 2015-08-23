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
        $this->fields = array("ca_id", "numinv","numinv_sort","numinv_display", "designation", "designation_display", "mode_acquisition", "donateur", "date_acquisition", "avis", "prix", "date_inscription", "date_inscription_display", "observations", "inscription", "materiaux", "techniques", "mesures", "etat", "auteur", "auteur_display", "epoque", "utilisation","provenance","validated");

        $this->mapping = getMappingBiensAffectes();

        parent::__construct($num);
    }

    function getHtmlTableHeaderRow() {
        return "<thead><tr><th class='list-header-unsorted'>Numéro d'inventaire</th><th class='list-header-unsorted'>Désignation</th><th class='list-header-unsorted'>Auteur</th><th class='list-header-unsorted'>Date d'inscription</th><th class='list-header-nosort'></th></tr></thead>";
    }
}