<?php
$va_setList = $this->getVar('SetList');
$vs_campagne_idno = $this->getVar('CampagneIdno');
?>

<h1>Choisir un ensemble d'objet pour préparer le récolement</h1>
<p>Tous les objets présents dans l'ensemble vont se voir attribuer une nouvelle fiche de récolement.</p>
<p>Cette action ne peut pas être annulée.</p>
<form action="<?php print __CA_URL_ROOT__ . "/index.php/museesDeFrance/Recolement/PreparerCampagne/"; ?>" method="post">
	<input type="hidden" name="idno" value="<?php print $vs_campagne_idno; ?>"/>
	<select name="set_id">
		<?php
		foreach ($va_setList as $vn_id => $vs_label) :
			?>
			<option value="<?php print $vn_id; ?>"><?php print $vs_label; ?></option>
		<?php
		endforeach;
		?>
	</select>
	<input type="submit" value="Submit">
</form>