<h1>Installation des thésaurus</h1>

<p>Retrouvez ici ces vocabulaires téléchargeables aux formats RTF et TXT pour mieux choisir lesquels installer : <a href="http://www.culture.gouv.fr/documentation/joconde/fr/partenaires/AIDEMUSEES/telechargement.htm">http://www.culture.gouv.fr/documentation/joconde/fr/partenaires/AIDEMUSEES/telechargement.htm</a></p>
<p>Choisissez ci-dessous les thésaurus à installer :</p>

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
    "lexrepr"=>"Liste des sujets représentés",
    "lexlieux"=>"Liste des lieux"
);

foreach($va_thesauri as $vs_code => $vs_desc) : ?>

<a href='http://<?php print __CA_SITE_HOSTNAME__.__CA_URL_ROOT__;?>/index.php/museesDeFrance/InstallProfileThesaurus/ThesaurusImport?thesaurus=<?php print $vs_code ?>'
   class='form-button'>
    <span class='form-button'>
        <img src='/themes/default/graphics/buttons/go.png' border='0' alt='Save' class='form-button-left' style='padding-right: 10px;'/> <?php print $vs_desc ?>
    </span>
</a>
<br/>
<?php endforeach; ?>
