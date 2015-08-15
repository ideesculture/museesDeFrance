<h1>Alignement des thésaurus</h1>

<p>Permet la mise à jour d'un thésaurus en déplaçant les termes employés dans une branche spécifique</p>
<p><b>Attention</b> <i>Ce traitement n'est à utiliser que si vous n'avez utilisé le thésaurus que dans les objets (aucun lien avec les collections, entités, etc.).</i></p>
<p>Les anciens termes du thésaurus vont être déplacés dans une nouvelle branche, puis raccrochés en se basant sur leur dénomination sur les nouvelles branches du thésaurus. Les thésaurus des Musées de France n'exploitant pas d'identifiant unique, ce traitement est basé uniquement sur les libellés des termes.</p>

<p>Choisissez ci-dessous le thésaurus à aligner :</p>

<?php
$va_thesauri = array(
    "lextech"=>"Liste des techniques",
    "lexmateriaux"=>"Liste des matériaux",
    //"lexautr"=>"Liste des auteurs",
    "lexautrole"=>"Liste des rôles des auteurs/exécutants",
    // remarque pour lexdecv : séparation méthode de collecte dans lexdecv / types de site et lieux dans lexsite
    "lexdecv"=>"Liste des méthodes de collecte",
    "lexsite"=>"Liste des méthodes de types de sites et lieux géographiques de découverte",
    "lexdeno"=>"Liste des dénominations",
    "lexdomn"=>"Liste des domaines",
    "lexecol"=>"Liste des écoles",
    "lexepoq"=>"Liste des époques / styles",
    "lexgene"=>"Liste des stades de création (genèse des oeuvres)",
    "lexinsc"=>"Liste des types d'inscriptions",
    "lexperi"=>"Liste des datations en siècle ou millénaire (périodes de création, d'exécution et d'utilisation)",
    "lexsrep"=>"Liste des sources de la représentation",
    "lexstat"=>"Liste des termes autorisés du statut juridique de l'objet",
    "lexutil"=>"Liste des utilisations - destinations",
    "lexrepr"=>"Liste des sujets représentés"
);

foreach($va_thesauri as $vs_code => $vs_desc) : ?>

<a href='http://<?php print __CA_SITE_HOSTNAME__.__CA_URL_ROOT__;?>/index.php/museesDeFrance/InstallProfileThesaurus/ThesaurusAlign?thesaurus=<?php print $vs_code ?>'
   class='form-button'>
    <span class='form-button'>
        <img src='<?php print __CA_URL_ROOT__; ?>/themes/default/graphics/buttons/go.png' border='0' alt='Save' class='form-button-left' style='padding-right: 10px;'/> <?php print $vs_desc ?>
    </span>
</a>
<br/>
<?php endforeach; ?>
