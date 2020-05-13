<h1>Campagnes de récolement</h1>

<?php
$campagnes = $this->getVar('campagnes');
$campagnes_par_rd = $this->getVar('campagnes_par_rd');

if (!isset($campagnes_par_rd) || !$campagnes_par_rd) {
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

    <button type="button" id="reload_button" onclick="ajax_stream()";>Rafraîchir</button>
	<script>
	  function ajax_stream()
    {
        console.log("ajax_stream");
				
        if (!window.XMLHttpRequest)
        {
            log_message("Your browser does not support the native XMLHttpRequest object.");
            return;
        }

        try
        {

            var xhr = new XMLHttpRequest();
            xhr.previous_text = '';

            xhr.onerror = function() { log_message("[XHR] Fatal Error."); };
            xhr.onreadystatechange = function()
            {
                try
                {
                    if (xhr.readyState > 2)
                    {

                        var new_response = xhr.responseText;
                        
                        //var result = JSON.parse( new_response );
												//ici, il faut refaire la facade avec les nouvelles infos délivrées par le ajax
												//result.campagnes_par_recolement_decennal
                        window.location.reload();
                    }
                }
                catch (e)
                {
                    //log_message("<b>[XHR] Exception: " + e + "</b>");
                }


            };
            console.log("<?php print __CA_URL_ROOT__; ?>/index.php/museesDeFrance/Recolement/computeInfosAjax");
            
            xhr.open("GET", "<?php print __CA_URL_ROOT__; ?>/index.php/museesDeFrance/Recolement/computeInfosAjax", true);
            xhr.send("Making request...");
        }
        catch (e)
        {
            log_message("<b>[XHR] Exception: " + e + "</b>");
        }
    }
	
	</script>
	
	<script type="text/javascript" src="http://www.google.com/jsapi"></script>
	<script src='<?php print __CA_URL_ROOT__; ?>/js/jquery/jQueryRotateCompressed.2.2.js'
	        type='text/javascript'></script>

	<?php if ($campagnes_par_rd) {foreach ($campagnes_par_rd as $rd_name => $rd) { ?>
        <?php $i++; ?>
        <h1 id="museesDeFrance_rc<?php _p($i); ?>_h1"
            style="display:block;width:730px;background-color:#e6e6e6;border-radius:6px;padding:8px;">
            <span id="museesDeFrance_rc<?php _p($i); ?>_img"> <?php print $rd_name; ?></span>
        </h1>
        <script type="text/javascript">
            $("#museesDeFrance_rc<?php _p($i);?>_h1").click(function () {
                $("#museesDeFrance_rc<?php _p($i);?>").slideToggle();
                $("#museesDeFrance_rc<?php _p($i);?>_img").rotate({
                    duration: 1000,
                    angle: 0,
                    animateTo: 180
                });
            });
        </script>
        <div id="museesDeFrance_rc<?php _p($i); ?>" style="<?php print ($i != 1 ? "display:none;" : ""); ?>">
            <script type="text/javascript">
                google.load("visualization", "1", {packages: ["corechart"]});
                google.setOnLoadCallback(drawChart);
                function drawChart() {
                    var data<?php _p($i);?> = google.visualization.arrayToDataTable([
                        ['Récolés', 'Récolés', 'A récoler', {role: 'annotation'}],
                        <?php
                        foreach ($rd["recolements"] as $campagne) :
                        ?>
                        ['<?php print $campagne["name"]; ?>', <?php print (int) $campagne["recolements_done"]; ?>, <?php print $campagne["recolements_total"] - $campagne["recolements_done"]; ?>, '<?php print $campagne["idno"]; ?>'],
                        <?php
                        endforeach;
                        ?>
                    ]);

                    var options<?php _p($i);?> = {
                        width: 744,
                        height: 200,
                        legend: {position: 'none', maxLines: 3},
                        bar: {groupWidth: '75%'},
                        isStacked: true,
                        colors: ['#1ab4c8', '#d4d4d4'],
                        chartArea: {left: '1%', top: 0, width: '98%', height: '90%'},
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

                    var chart<?php _p($i);?> = new google.visualization.BarChart(document.getElementById('chart_div<?php _p($i);?>'));
                    chart<?php _p($i);?>.draw(data<?php _p($i);?>, options<?php _p($i);?>);
                }
            </script>
            <h2>Suivi graphique</h2>

            <div id="chart_div<?php _p($i); ?>" style="width: 744px; height: 220px;"></div>

            <h2>Tableau de progression</h2>
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
                <?php
                if (is_array($rd["recolements"])) {
                    foreach ($rd["recolements"] as $campagne) :
                        ?>
                        <tr>
                            <td>
                                <a href="<?php print __CA_URL_ROOT__; ?>/index.php/editor/occurrences/OccurrenceEditor/Summary/occurrence_id/<?php print $campagne["occurrence_id"]; ?>"
                                   title="Modifier la campagne">
                                    <?php print caNavIcon(__CA_NAV_ICON_EDIT__); ?>
                                </a>
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
                                        <progress id="avancement"
                                                  value="<?php print (int)$campagne["recolements_done"]; ?>"
                                                  max="<?php print $campagne["recolements_total"]; ?>"></progress>
                                    </a>
                                    <?php print $campagne["recolements_done"]; ?>/<?php print $campagne["recolements_total"]; ?>
                                    <span style="float:right;">
									<a href="<?php print __CA_URL_ROOT__ . "/index.php/museesDeFrance/Recolement/PreparerCampagne/?idno=" . $campagne["idno"]; ?>"
                                       title="Générer des fiches de récolements depuis un ensemble d'objets">
                                        <img
                                            src="<?php print __CA_URL_ROOT__ ?>/themes/default/graphics/buttons/add.png"/>
                                    </a>
								</span>
                                <?php
                                endif;
                                ?>

                            </td>
                        </tr>
                    <?php

                    endforeach;
                }
                ?>
            </table>
            <a class="form-button"
               href="<?php print __CA_URL_ROOT__; ?>/index.php/museesDeFrance/Recolement/TableauSuivi/?rd_name=<?php print $rd_name; ?>">
	<span class="form-button">
	Générer le tableau de suivi
	</span>
            </a>
        </div>
    <?php
    }}
	?>

<?php } ?>
<div style="height:100px;"></div>

 
