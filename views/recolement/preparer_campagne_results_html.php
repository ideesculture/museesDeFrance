<h1>Génération des fiches de récolement</h1>

<?php
$campagne = $this->getVar('Campagne');
$vn_recolements_crees = $this->getVar('RecolementsCrees');
?>
<style>
	progress {
		display: block;
		width: 140px;
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
		<th></th>
		<th>Campagne</th>
		<th>Localisation</th>
		<th>Type de collection (champ couvert)</th>
		<th>Dates prévisionnelles</th>
		<th>Dates effectives</th>
		<th>Procès verbal</th>
		<th>Nombre d'objets récolés</th>
	</tr>
	<tr>
		<td>
			<a href="<?php print __CA_URL_ROOT__; ?>/index.php/editor/occurrences/OccurrenceEditor/Summary/occurrence_id/<?php print $campagne["occurrence_id"]; ?>"
			   title="Modifier la campagne">
				<img src="<?php print __CA_URL_ROOT__; ?>/app/plugins/museesDeFrance/assets/icons/edit.png">
			</a>
		</td>
		<td>
			<a href="<?php print __CA_URL_ROOT__ . "/index.php/museesDeFrance/Recolement/Pv/?idno=" . $campagne["idno"]; ?>"
			   title="Générer le PV">
				<?php print $campagne["idno"]; ?> :
				<big><?php print $campagne["name"]; ?></big>
			</a>
		</td>
		<td>
			<?php print $campagne["localisation"]; ?>
			<small><?php print $campagne["localisation_code"]; ?></small>
		</td>
		<td><?php print $campagne["champs"]; ?></td>
		<td><?php print $campagne["date_campagne_prev"]; ?></td>
		<td><?php print $campagne["date_campagne"]; ?></td>
		<td><?php print $campagne["date_campagne_pv"]; ?></td>
		<td>
			<a href="<?php print __CA_URL_ROOT__ . "/index.php/museesDeFrance/Recolement/Pv/?idno=" . $campagne["idno"]; ?>"
			   title="Générer le PV">
				<progress id="avancement" value="<?php print (int)$campagne["recolements_done"]; ?>"
				          max="<?php print $campagne["recolements_total"]; ?>"></progress>
			</a>
			<?php print $campagne["recolements_done"]; ?>/<?php print $campagne["recolements_total"]; ?>
			<span style="float:right;">
			<a href="<?php print __CA_URL_ROOT__ . "/index.php/museesDeFrance/Recolement/PreparerCampagne/?idno=" . $campagne["idno"]; ?>"
			   title="Générer des fiches de récolements depuis un ensemble d'objets">
				<img src="<?php print __CA_URL_ROOT__ ?>/app/plugins/museesDeFrance/assets/icons/add.png"/>
			</a>
		</span>
		</td>
	</tr>
</table>
<p><?php print $vn_recolements_crees; ?> fiches de récolement ont été créées pour la campagne.</p>
<a href="<?php print __CA_URL_ROOT__; ?>/index.php/museesDeFrance/Recolement/Index">Retour à la liste des campagnes</a>
