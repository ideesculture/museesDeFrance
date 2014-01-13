<?php

$InfosPv = $this->getVar('InfosPv');
?>

<h1>Liste des objets manquants/non vus</h1>

<h2>
	<a href="<?php print __CA_URL_ROOT__; ?>/index.php/editor/occurrences/OccurrenceEditor/Summary/occurrence_id/<?php print $InfosPv["info"]["occurrence_id"]; ?>">
		<img src="<?php print __CA_URL_ROOT__; ?>/themes/default/graphics/buttons/edit.png"></a>
	<?php print $InfosPv["info"]["campagne_nom"] ?>
</h2>
<table class="listtable">
	<tr>
		<td>Numéro de campagne</td>
		<td><?php print $InfosPv["info"]["idno"]; ?></td>
	</tr>
	<tr class="odd">
		<td>Date de campagne</td>
		<td><?php print $InfosPv["info"]["campagne_date"]; ?></td>
	</tr>
	<tr>
		<td>Caractéristiques</td>
		<td><?php print $InfosPv["info"]["campagne_caracteristiques"]; ?></td>
	</tr>
	<tr class="odd">
		<td>Moyens</td>
		<td><?php print $InfosPv["info"]["campagne_moyens"]; ?></td>
	</tr>
	<tr>
		<td>Champs couverts</td>
		<td><?php print $InfosPv["info"]["campagne_champs_champs"]; ?></td>
	</tr>
	<tr>
		<td>Contenu scientifique de la campagne</td>
		<td><?php print $InfosPv["info"]["contenu_scientifique"]; ?></td>
	</tr>
	<tr class="odd">
		<td>
			<small>Note</small>
		</td>
		<td>
			<small><?php print $InfosPv["info"]["campagne_champs_note"]; ?></small>
		</td>
	</tr>
	<tr class="odd">
		<td>Objets manquants</td>
		<td><?php print (int)$InfosPv["nb"]["objets_manquants"]; ?></td>
	</tr>
	<tr class="odd">
		<td>Objets non vus</td>
		<td><?php print (int)$InfosPv["nb"]["objets_non_vus"]; ?></td>
	</tr>
</table>

<br/><br/>
<?php print $InfosPv["liste_objets_manquants_non_vus_html"]; ?>
<br/><br/><br/><br/><br/><br/><br/><br/>
