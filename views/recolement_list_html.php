<h1>Impression des PV de récolement</h1>

<?php 
$campagnes = $this->getVar('campagnes');

if (!isset($campagnes) || !$campagnes) {
	?>
	Aucune campagne de récolement n'est accessible.
	<?php	
} else {
	foreach ($campagnes as $campagne) {
		print "<p><a href=".__CA_URL_ROOT__."/index.php/museesDeFrance/Recolement/Pv/?idno=".$campagne["idno"].">".$campagne["idno"]." : ".
			$campagne["name"]."</a> <small>[".$campagne["date_campagne"]."]</small></p>";
	}
}
?>


 
