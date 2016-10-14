<h1>Campagnes de récolement</h1>

<?php
$campagnes = $this->getVar('campagnes');

if (!isset($campagnes) || !$campagnes) {
	?>
	<p>Aucune campagne de récolement n'est accessible.</p>
	<p>Si vous avez bien créé des campagnes de récolement mais que celles-ci ne sont pas visibles dans cet écran,
		veuillez <a href="<?php print __CA_URL_ROOT__; ?>/index.php/administrate/maintenance/SearchReindex/Index">réindexer
			la base</a>.</p>
<?php
} else {
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
	<script type="text/javascript" src="https://www.google.com/jsapi"></script>
	<script type="text/javascript">
		google.load("visualization", "1", {packages: ["corechart"]});
		google.setOnLoadCallback(drawChart);
		function drawChart() {
			var data = google.visualization.arrayToDataTable([
				['Récolés', 'Récolés', 'A récoler', { role: 'annotation' } ],
				<?php
				foreach ($campagnes as $campagne) :
				?>
				['<?php print $campagne["name"]; ?>', <?php print (int) $campagne["recolements_done"]; ?>, <?php print $campagne["recolements_total"] - $campagne["recolements_done"]; ?>, '<?php print $campagne["idno"]; ?>'],
				<?php
				endforeach;
				?>
			]);

			var options = {
				width: 744,
				height: 200,
				legend: { position: 'none', maxLines: 3 },
				bar: { groupWidth: '75%' },
				isStacked: true,
				colors: ['#1ab4c8', '#d4d4d4'],
				chartArea: {left: 0, top: 0, width: '100%', height: '90%'},
				hAxis: {
					textStyle: {
						color: "#cccccc"
					},
					textPosition: 'out',
					gridlines: {
						color: "#EEEEEE"
					},
					baselineColor: '#EEEEEE'
				},
				vAxis: {
					textPosition: 'none',
					gridlines: {
						color: "#EEEEEE"
					},
					baselineColor: '#EEEEEE'
				}
			};

			var chart = new google.visualization.BarChart(document.getElementById('chart_div'));
			chart.draw(data, options);
		}
	</script>
	<h2>Suivi graphique</h2>
	<div id="chart_div" style="width: 744px; height: 220px;"></div>
	<h2>Tableau de progression</h2>
	<table class="listtable">
		<tr>
			<th></th>
			<th>Récolement décennal</th>
			<th>Campagne</th>
			<th>Localisation</th>
			<th>Type de collection (champ couvert)</th>
			<th>Dates prévisionnelles</th>
			<th>Dates effectives</th>
			<th>Procès verbal</th>
			<th>Nombre d'objets récolés</th>
		</tr>
		<?php
		foreach ($campagnes as $campagne) :
			?>
			<tr>
				<td>
					<a href="<?php print __CA_URL_ROOT__; ?>/index.php/editor/occurrences/OccurrenceEditor/Summary/occurrence_id/<?php print $campagne["occurrence_id"]; ?>"
					   title="Modifier la campagne">
						<img src="<?php print __CA_URL_ROOT__; ?>/themes/default/graphics/buttons/edit.png">
					</a>
				</td>
				<td>
					<?php print $campagne["recolement_decennal"]; ?>
				</td>
				<td>
					<a href="<?php print __CA_URL_ROOT__ . "/index.php/museesDeFrance/Recolement/Pv/?idno=" . $campagne["idno"]; ?>"
					   title="Accéder aux fiches ou générer le PV">
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
					<?php
					if ($campagne["recolements_total"] == 0) :
						?>
						<span style="float:right;">
			<a class="form-button"
			   href="<?php print __CA_URL_ROOT__ . "/index.php/museesDeFrance/Recolement/PreparerCampagne/?idno=" . $campagne["idno"]; ?>"
			   title="Générer des fiches de récolements depuis un ensemble d'objets">
				<span class="form-button">Générer les fiches</span>
			</a>
		</span>
					<?php
					else :
						?>
						<a href="<?php print __CA_URL_ROOT__ . "/index.php/museesDeFrance/Recolement/Pv/?idno=" . $campagne["idno"]; ?>"
						   title="Accéder aux fiches ou générer le PV">
							<progress id="avancement" value="<?php print (int)$campagne["recolements_done"]; ?>"
							          max="<?php print $campagne["recolements_total"]; ?>"></progress>
						</a>
						<?php print $campagne["recolements_done"]; ?>/<?php print $campagne["recolements_total"]; ?>
						<span style="float:right;">
			<a href="<?php print __CA_URL_ROOT__ . "/index.php/museesDeFrance/Recolement/PreparerCampagne/?idno=" . $campagne["idno"]; ?>"
			   title="Générer des fiches de récolements depuis un ensemble d'objets">
				<img src="<?php print __CA_URL_ROOT__ ?>/themes/default/graphics/buttons/add.png"/>
			</a>
		</span>
					<?php
					endif;
					?>

				</td>
			</tr>
		<?php
		endforeach;
		?>
	</table>
	<a class="form-button"
	   href="<?php print __CA_URL_ROOT__; ?>/index.php/museesDeFrance/Recolement/TableauSuivi/?idno=<?php print $InfosPv["info"]["idno"]; ?>">
	<span class="form-button">
	Générer le tableau de suivi
	</span>
	</a>
<?php
}
?>


 
