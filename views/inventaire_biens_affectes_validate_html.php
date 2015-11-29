<?php
    $vn_id = $this->getVar("id");
    $vs_idno = $this->getVar("idno");
    $vs_name = $this->getVar("name");

?>
<h1><?php print $vs_name." [".$vs_idno."]"; ?> <br/><small>ajouté à l'inventaire des Biens Affectés</small></h1>
<p>L'objet <em><?php print $vs_name." [".$vs_idno."]"; ?></em> a été transféré dans le registre d'inventaire.</p>
