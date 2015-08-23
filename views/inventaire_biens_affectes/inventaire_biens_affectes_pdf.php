<?php
/**
 * Created by PhpStorm.
 * User: gautier
 * Date: 09/08/15
 * Time: 17:14
 */

// module/Inventaire
define("__LOCALE__","fr_FR");
$vt_registre = $this->getVar("registre");

?>
<html>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<head>
    <style type="text/css">
        @page {margin-top: 1cm;margin-left: 1cm;margin-bottom:3cm;margin-right: 1cm;}
        body {font:10pt helvetica;}
        h1, h2, h3, h4 {font-family:helvetica;}
        p {/*margin:0 0 3 0;*/}
        table.table-content {
            width:100%;
        }
        thead, th {
            font-weight: normal;
            text-align: left;
        }
        td.photo {vertical-align: top;text-align: right;font-size: 8pt;width:130pt;}
        td.label {background:#f0f0f0;color:#555555;font-size: 7pt;width:85pt;text-align:right;text-transform:lowercase;padding:1pt 3pt 1pt 3pt;}
        td.content {font-size: 7pt; }
        td.content b {font-size: 8pt;font-weight: bold;}
        td.vrsp {background-color:blue;}
        .hero-unit{display:block;padding:3pt;margin:2pt;margin-bottom:10pt;border-bottom:1pt solid gray;}
        .hero-unit h1, .hero-unit h2, .hero-unit h3, .hero-unit h4 {margin:0px;line-height:0.8;letter-spacing:-1pt;}
        .hero-unit h1 {margin-bottom:6pt;font-size:25pt;}
        .hero-unit h2 {margin-bottom:5pt;font-size:20pt;}
        .hero-unit h3 {margin-bottom:4pt;font-size:17pt;}
        .hero-unit h4 {margin-bottom:3pt;font-size:14pt;}
        .hero-unit p{font-size:10pt;line-height:10pt;}
        #page-de-titre {font-size:7pt;}
        #page-de-titre .placeholder {background:#f5f5f5;margin-top:2pt;margin-bottom:2pt;padding:5pt;height:1.5cm;}
        #page-de-titre DIV {width:4.2cm;}
        #page-de-titre H3 {color:#f5f5f5;font-size:9pt;line-height:5pt;margin:0pt;padding:0pt;}
        #page-de-titre H4.surtitre {color:#d5d5d5;font-size:18pt;line-height:7pt;letter-spacing:3pt;margin:0pt;padding:0pt;}
        #page-de-titre #titre {position:absolute;top:4cm;left:5cm;width:9cm;font-size:9pt;}
        #page-de-titre #titre H1 {color:#68B604;}
        #page-de-titre #dates-registre {position:absolute;top:12cm;left:5cm;width:9cm;font-size:9pt;}
        #page-de-titre #dates-registre H3,
        #page-de-titre #dates-registre H4 {display:none;}
        #page-de-titre #nom-adresse-musee {position:absolute;bottom:0cm;left:0cm;}
        #page-de-titre #nom-adresse-personnemorale {position:absolute;bottom:0cm;left:4.75cm;}
        #page-de-titre #lieu-conservation {position:absolute;bottom:0cm;left:9.45cm;}
        #page-de-titre #paraphe-responsable-scientifique {position:absolute;bottom:0cm;left:14.2cm;}
        .center {text-align:center;}
        .fin-impression {background:gray;text-align:right;color:white;font-size:7pt;}
        .page-break {page-break-before: always;}
        #footer {position: fixed; bottom:0;left:0;width:100%;height:2.2cm;padding: 0;margin-bottom:-3.5cm; margin-left: -1cm;margin-right: -1cm;background-color:#ffffff;z-index:10;}
        #paraphe {position: fixed; bottom:0;left:0;border:1px solid gray;width:4.3cm;margin-bottom:-2cm;z-index:11;font:7pt helvetica;height:1.7cm;}
    </style>
</head>
<body>

<script type="text/php">
    if ( isset($pdf) ) {
        $font = Font_Metrics::get_font("JosefinSans");
		$mm2coords = 2.835;
        $pdf->page_text(194 * $mm2coords, 282 * $mm2coords, "{PAGE_NUM}/{PAGE_COUNT}", $font, 8, array(0,0,0));
	}
</script>

<div id="page-de-titre">
    <div id="titre">
        <?php
        include("inventaire_templates_pdf/insertions-pdf-inventaire/titre.html");
        ?>
    </div>
    <div id="dates-registre">
        <?php
        include("inventaire_templates_pdf/insertions-pdf-commun/dates-registre.html");
        ?>
    </div>
    <div id="nom-adresse-musee">
        <?php
        include("inventaire_templates_pdf/insertions-pdf-commun/nom-adresse-musee.html");
        ?>
    </div>
    <div id="nom-adresse-personnemorale">
        <?php
        include("inventaire_templates_pdf/insertions-pdf-commun/nom-adresse-personnemorale.html");
        ?>
    </div>
    <div id="lieu-conservation">
        <?php
        include("inventaire_templates_pdf/insertions-pdf-inventaire/lieu-conservation.html");
        ?>
    </div>
    <div id="paraphe-responsable-scientifique">
        <?php
        include("inventaire_templates_pdf/insertions-pdf-inventaire/paraphe-responsable-scientifique.html");
        ?>
    </div>
</div>

<div class="page-break"></div>
<!-- page blanche en dos de page de titre -->

<div id="pages-liminaires">
    <div id="contenu">
        <?php
        include("inventaire_templates_pdf/insertions-pdf-inventaire/pages-liminaires.html");
        ?>
    </div>
</div>

<div class="page-break"></div>

<!-- page blanche après les pages liminaires -->

<?php
$labels = array("numinv" => "Numéro d'inventaire");
foreach($vt_registre->getObjects() as $vt_object) {
    if($vt_object->validated) {
?>
    <div class="hero-unit">
        <div style="clear:both; position:relative;">
            <table class="table-content">
                <thead>
                <tr>
                    <th>
                        <small>N° inventaire</small><br/><b><?php print $vt_object->numinv_display; //1 ?></b>
                    </th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td class="label">Désignation</td>
                    <td class="content"><?php print $vt_object->designation; //8?></td>
                    <td class="photo" rowspan="6">
                        <?php print $vt_object->numinv_display; //1 ?><br/>
                        <?php if($vt_object->file) : ?>
                            <img src="<?php print __CA_BASE_DIR__; ?>/app/plugins/museesDeFrance/assets/photos/<?php print $vt_object->file; //1 ?>" style="width:120pt;">
                        <?php else : ?>
                            <img src="<?php print __CA_BASE_DIR__."/app/plugins/museesDeFrance/views/images/pas-de-vignette.jpg"; ?>" style="width:120pt;">
                        <?php endif; ?>
                    </td>
                </tr>
                <tr><td class="label">Mode d'acquisition</td><td class="content"><?php print $vt_object->mode_acquisition; //2?></td></tr>
                <tr><td class="label">Nom du donateur, testateur ou vendeur</td><td class="content"><?php print $vt_object->donateur; //3?></td></tr>
                <tr><td class="label">Date et références de l'acte d'acquisition et d'affectation au musée</td><td class="content"><?php print $vt_object->date_acquisition; ?></td></tr>
                <tr><td class="label">Avis des instances scientifiques</td><td class="content"><?php print $vt_object->avis; //5?></td></tr>
                <tr><td class="label">Prix d'achat - subvention publique</td><td class="content"><?php print $vt_object->prix; //6 ?></td></tr>
                <tr><td class="label">Date d'inscription au registre d'inventaire</td><td class="content" colspan="2"><?php print $vt_object->date_inscription; //7 ?></td></tr>
                <tr><td class="label">Marques et inscriptions</td><td class="content" colspan="2"><?php print $vt_object->inscription; //9?></td></tr>
                <tr><td class="label">Matériaux/Techniques</td><td class="content"  colspan="2"><?php print $vt_object->materiaux; //10?></td></tr>
                <tr><td class="label">Mesures</td><td class="content" colspan="2"><?php print $vt_object->mesures; //12?></td></tr>
                <tr><td class="label">Indications particulières sur l'état du bien au moment de l'acquisition</td><td class="content" colspan="2"><?php print $vt_object->etat; //13?></td></tr>
                <tr><td class="label">Auteur, collecteur, fabricant, commanditaire...</td><td class="content" colspan="2"><?php print $vt_object->auteur; //14 ?></td></tr>
                <tr><td class="label">Epoque, datation ou date de récolte</td><td class="content" colspan="2"><?php print $vt_object->epoque; //15 ?></td></tr>
                <tr><td class="label">Fonction d'usage</td><td class="content" colspan="2"><?php print $vt_object->utilisation; //16 ?></td></tr>
                <tr><td class="label">Provenance géographique</td><td class="content" colspan="2"><?php print $vt_object->provenance; //17 ?></td></tr>
                <tr><td class="label">Observations</td><td class="content" colspan="2"><?php print $vt_object->observations; //18 ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    }
}
?>
<p class="fin-impression">
    fin de l'impression
</p>

<div class="page-break"></div>

<!-- page blanche après la fin de l'impression -->

<div id="pages-libres-fin-registre">
    <?php include("inventaire_templates_pdf/insertions-pdf-commun/pages-blanches-fin-registre.html"); ?>
</div>
<div id="footer">
    <div id="paraphe">
        Paraphe du responsable scientifique
    </div>
</div>