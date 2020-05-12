<?php
$vt_registre = $this->getVar("registre");

?>
<h1>Inventaire</h1>
<p>Génération de l'inventaire PDF depuis le HTML généré...</p>
<iframe style="width:100%;height:500px;" src="<?php print __CA_URL_ROOT__; ?>/app/plugins/museesDeFrance/render_pdf.php">
</iframe>