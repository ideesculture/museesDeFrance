<?php

$InfosPv = $this->getVar('InfosPv');
?>

<h1>Informations du PV de récolement</h1>

<h2>
	<a href="<?php print __CA_URL_ROOT__; ?>/index.php/editor/occurrences/OccurrenceEditor/Summary/occurrence_id/<?php print $InfosPv["info"]["occurrence_id"]; ?>">
		<img src="<?php print __CA_URL_ROOT__; ?>/themes/default/graphics/buttons/edit.png"></a>
	<?php print $InfosPv["info"]["campagne_nom"] ?>
</h2>

<style>
	progress {
		display: block;
		width: 90%;
		height: 2em;
		margin: .5em 0;
		border-radius: 5px;
		background-color: #d4d4d4;
	}

	progress::-webkit-progress-bar {
		border-radius: 5px;
		background-color: #d4d4d4;
	}

	progress::-webkit-progress-value {
		border-radius: 5px;
		background-color: #1ab4c8;
		background-size: 40px 40px;
	}

	progress::-moz-progress-bar {
		border-radius: 5px;
		background-color: #1ab4c8;
		background-size: 40px 40px;
		-moz-animation: progress 8s linear infinite;
		animation: progress 8s linear infinite;
	}
</style>

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
	<tr>
		<td>Avancement</td>
		<td>
			<progress id="avancement" value="<?php print (int)$campagne["recolements_done"]; ?>"
				    max="<?php print $campagne["recolements_total"]; ?>" style="float:left;"></progress>
			<span style="float:right;margin:10px;">
				<a href="<?php print __CA_URL_ROOT__ . "/index.php/museesDeFrance/Recolement/PreparerCampagne/?idno=" .$InfosPv["info"]["idno"]; ?>"
					title="Générer des fiches de récolements depuis un ensemble d'objets">
				<img src="<?php print __CA_URL_ROOT__ ?>/themes/default/graphics/buttons/add.png"/>
				</a>
			</span>
		</td>
	</tr>
	<tr class="odd">
		<td>Objets vus</td>
		<td><?php print (int)$InfosPv["nb"]["objets_vus"]; ?></td>
	</tr>
	<tr>
		<td>Objets manquants</td>
		<td><?php print (int)$InfosPv["nb"]["objets_manquants"]; ?></td>
	</tr>
	<tr class="odd">
		<td>Objets non vus</td>
		<td><?php print (int)$InfosPv["nb"]["objets_non_vus"]; ?></td>
	</tr>
	<tr>
		<td>Objets détruits</td>
		<td><?php print (int)$InfosPv["nb"]["objets_detruits"]; ?></td>
	</tr>
	<tr class="odd">
		<td>Objets non inventoriés</td>
		<td><?php print (int)$InfosPv["nb"]["objets_non_inventories"]; ?></td>
	</tr>
	<tr>
		<td>Objets inventoriés plusieurs fois</td>
		<td><?php print (int)$InfosPv["nb"]["objets_inventories_plusieurs_fois"]; ?></td>
	</tr>
	<tr class="odd">
		<td>Objets marqués</td>
		<td><?php print (int)$InfosPv["nb"]["objets_marques"]; ?></td>
	</tr>
	<tr>
		<td>Objets non marqués</td>
		<td><?php print (int)$InfosPv["nb"]["objets_non_marques"]; ?></td>
	</tr>
	<tr class="odd">
		<td>Objets exposés</td>
		<td><?php print (int)$InfosPv["nb"]["objets_exposes"]; ?></td>
	</tr>
	<tr>
		<td>Objets en réserve</td>
		<td><?php print (int)$InfosPv["nb"]["objets_en_reserve"]; ?></td>
	</tr>
	<tr class="odd">
		<td>Total objets récolés</td>
		<td><?php print (int)$InfosPv["nb"]["objets_recoles"]; ?></td>
	</tr>
</table>

<a class="form-button"
   href="<?php print __CA_URL_ROOT__; ?>/index.php/museesDeFrance/Recolement/PvWord/?idno=<?php print $InfosPv["info"]["idno"]; ?>">
	<span class="form-button">
	Générer le procès-verbal
	</span>
</a>
<br/><br/>
<?php print $InfosPv["liste_objets_html"]; ?>
<br/><br/><br/><br/><br/><br/><br/><br/>
