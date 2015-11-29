<?php
    $vn_id = $this->getVar("id");
    $vs_idno = $this->getVar("idno");
    $vs_name = $this->getVar("name");
    $vt_object = $this->getVar("object");

    $vb_is_validated = ($vt_object->get("validated") == "1");

    MetaTagManager::addLink('stylesheet', __CA_URL_ROOT__."/app/plugins/museesDeFrance/assets/css/museesDeFrance.css",'text/css');

?>
<h1><?php print $vs_name." [".$vs_idno."]"; ?></h1>
<?php if($vb_is_validated): ?>
<p>Cet objet est inscrit dans le registre d'inventaire, cette fiche n'est plus modifiable.</p>
<?php else : ?>
<p>Cet objet est en attente d'inscription dans le registre d'inventaire.</p>
<?php endif; ?>

<table class="inventaire-object-display">
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
                <img src="<?php print __CA_URL_ROOT__."/app/plugins/museesDeFrance/assets/photos/".$vt_object->file; //1 ?>" style="width:120pt;">
            <?php else : ?>
                <img src="<?php print __CA_URL_ROOT__."/app/plugins/museesDeFrance/views/images/pas-de-vignette.jpg"; ?>" style="width:120pt;">
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

<?php print caFormControlBox(
    caNavButton($this->request, __CA_NAV_BUTTON_SCROLL_LT__, "Retour", "", "*", "*", "Index"),
    null,
    (!$vb_is_validated ? caNavButton($this->request, __CA_NAV_BUTTON_COMMIT__, "Inscrire à l'inventaire", "", "*", "*", 'Validate',array("id"=>$vn_id)) : null)
);
?>
