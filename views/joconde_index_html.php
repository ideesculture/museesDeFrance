<?php 
	$folders = $this->getVar("folders");
	$folders_contents = $this->getVar("folders_contents");	
	$base_url =  __CA_URL_ROOT__."/app/plugins/museesDeFrance/export-joconde";
?>

<h1>Export Joconde</h1>
<p>Pour réaliser un export vers Joconde, placez les enregistrements à exporter dans l'ensemble intitulé <a href="<?php print __CA_URL_ROOT__; ?>/index.php/find/SearchObjects/Index/search/set%253Ajoconde">"Joconde"</a> <small><a href="<?php print __CA_URL_ROOT__; ?>/index.php/manage/sets/SetEditor/Edit/Screen17/set_id/41">Ajouter/Retirer des objets</a></small></p>

<p>Les différents exports réalisés pour Joconde sont listés ici.</p>

<ul>
<?php foreach($folders as $folder) : ?>
	<li><?php print $folder; ?> <a href="<?php print $base_url."/".$folder.".zip"; ?>"><span style="color:white;background:darkgrey;font-size:8px;padding:2px 4px;border-radius:6px;">ZIP</span></a</li>
<?php if($folders_contents[$folder]) : ?>
	<ul>
<?php	foreach($folders_contents[$folder] as $key=>$content) :
			if($content != "." && $content != ".."): ?>
				<li><a href="<?php print __CA_URL_ROOT__; ?>/app/plugins/museesDeFrance/export-joconde/<?php print $folder."/".$content; ?>"><?php print $content; ?></a></li>
<?php		endif;
		endforeach; ?>
	</ul>
<?php	endif;?>
<?php endforeach; ?>
</ul>

<p><a href="<?php print __CA_URL_ROOT__; ?>/index.php/museesDeFrance/Joconde/Export" class="form-button 1487955007"><span class="form-button"><?php print caNavIcon(__CA_NAV_ICON_GO__); ?> Générer un export Joconde</span></a></p>

<hr/>
<small>
<p>Le module museesDeFrance pour CollectiveAccess est réalisé par <a href="http://www.ideesculture.com">idéesculture</a> – <a href="http://www.culture.gouv.fr/documentation/joconde/fr/partenaires/AIDEMUSEES/format-export.htm#refmis">Documentation de l'export Joconde</a></p>
</small>
