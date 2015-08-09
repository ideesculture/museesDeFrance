<?php
    $vn_id = $this->getVar("id");
    $vs_idno = $this->getVar("idno");
    $vs_name = $this->getVar("name");

?>
<h1><?php print $vs_name." [".$vs_idno."]"; ?> <br/><small>Transfert dans l'inventaire des biens affectés</small></h1>
<p>Vous avez préparé les informations de l'objet <em><?php print $vs_name." [".$vs_idno."]"; ?></em> pour les transférer dans le registre d'inventaire.</p>
<p>Attention, ces données ne seront définitivement écrites qu'à la validation de l'objet dans l'inventaire.</p>
<p>Une fois l'objet validé, vous ne pourrez plus modifier ses informations.</p>
<p>Voulez vous valider l'ajout de l'objet dans l'inventaire ?</p>
<?php
    print caNavButton(
        $this->request,
        __CA_NAV_BUTTON_APPROVE__,
        "Valider",
        '',
        $this->request->getModulePath(),
        $this->request->getController(),
        'Validate',
        array("id"=>$vn_id));
?>

