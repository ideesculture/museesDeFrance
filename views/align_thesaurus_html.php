<?php
    require_once(__CA_APP_DIR__."/plugins/museesDeFrance/helpers/ThesaurusDMF.php");
?>
<h1>Alignement des thésaurus</h1>

<p>Permet la mise à jour d'un thésaurus en déplaçant les termes employés dans une branche spécifique</p>
<p><b>Attention</b> <i>Ce traitement n'est à utiliser que si vous n'avez utilisé le thésaurus que dans les objets (aucun lien avec les collections, entités, etc.).</i></p>
<p>Les anciens termes du thésaurus vont être déplacés dans une nouvelle branche, puis raccrochés en se basant sur leur dénomination sur les nouvelles branches du thésaurus. Les thésaurus des Musées de France n'exploitant pas d'identifiant unique, ce traitement est basé uniquement sur les libellés des termes.</p>

<p>Choisissez ci-dessous le thésaurus à aligner :</p>

<?php
$va_thesauri = ThesaurusDMF();

foreach($va_thesauri as $vs_thes_code => $va_thesaurus) :
    if ($vs_thes_code == "lexlieux") continue;
?>

<a href='http://<?php print __CA_SITE_HOSTNAME__.__CA_URL_ROOT__;?>/index.php/museesDeFrance/InstallProfileThesaurus/ThesaurusAlign?thesaurus=<?php print $vs_thes_code ?>'
   class='form-button'>
    <span class='form-button'>
        <img src='<?php print __CA_URL_ROOT__; ?>/themes/default/graphics/arrows/breadcrumbloc.png' border='0' alt='Save' class='form-button-left' style='padding-right: 10px;'/> <?php print $va_thesaurus["label"]; ?> <small style="color:lightgray;"><?php print $vs_thes_code ?></small> 
    </span>
</a>
<br/>

<?php endforeach; ?>
