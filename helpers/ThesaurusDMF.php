<?php 

/**
 * ThesaurusDMF helper : returns filename, code, label for a thesaurus distributed by french ministry of culture
 */
function ThesaurusDMF() {
	$ThesaurusDMF = array(
        "lexdomn" => array(
        	"filename"=>"lexdomn.txt",
        	"code"=>"lexdomn",
			"label"=>"DMF : Liste des domaines",
			"ignoreFirstLines"=>"16"
		),
		"lextech" => array(
			"filename"=>"lextechA.txt",
        	"code"=>"lextech",
			"label"=>"DMF : Liste des techniques",
			"ignoreFirstLines"=>"5"
		),
		"lexmateriaux" => array(
			"filename"=>"lextechB.txt",
        	"code"=>"lexmateriaux",
			"label"=>"DMF : Liste des matériaux",
			"ignoreFirstLines"=>"5"
		),
		"lexautr" => array(
			"filename"=>"lexautr.txt",
        	"code"=>"lexautr",
			"label"=>"DMF : Liste des auteurs",
			"ignoreFirstLines"=>"6"
		),
		"lexautrole" => array(
			"filename"=>"lexautrole.txt",
        	"code"=>"lexautrole",
			"label"=>"DMF : Liste des rôles des auteurs/exécutants",
			"ignoreFirstLines"=>"5"
		),
		"lexdecv" => array(
			"filename"=>"lexdecvA.txt",
        	"code"=>"lexdecv",
			"label"=>"DMF : Liste des méthodes de collecte",
			"ignoreFirstLines"=>"6"
		),
		"lexsite" => array(
			"filename"=>"lexdecvB.txt",
        	"code"=>"lexsite",
			"label"=>"DMF : Liste des méthodes de types de sites et lieux géographiques de découverte",
			"ignoreFirstLines"=>"5"
		),
		"lexdeno" => array(
			"filename"=>"lexdeno.txt",
        	"code"=>"lexdeno",
			"label"=>"DMF : Liste des dénominations",
			"ignoreFirstLines"=>"5"
		),
		"lexecol" => array(
			"filename"=>"lexecol.txt",
        	"code"=>"lexecol",
			"label"=>"DMF : Liste des écoles",
			"ignoreFirstLines"=>"5"
		),
		"lexepoq" => array(
			"filename"=>"lexepoq.txt",
        	"code"=>"lexepoq",
			"label"=>"DMF : Liste des époques / styles",
			"ignoreFirstLines"=>"4"
		),
		"lexgene" => array(
			"filename"=>"lexgene.txt",
        	"code"=>"lexgene",
			"label"=>"DMF : Liste des stades de création (genèse des oeuvres)",
			"ignoreFirstLines"=>"5"
		),
		"lexinsc" => array(
			"filename"=>"lexinsc.txt",
        	"code"=>"lexinsc",
			"label"=>"DMF : Liste des types d’inscriptions)",
			"ignoreFirstLines"=>"4"
		),
		"lexperi" => array(
			"filename"=>"lexperi.txt",
        	"code"=>"lexperi",
			"label"=>"DMF : Liste des datations en siècle ou millénaire (périodes de création, d'exécution et d'utilisation)",
			"ignoreFirstLines"=>"6"
		),
		"lexsrep" => array(
			"filename"=>"lexsrep.txt",
        	"code"=>"lexsrep",
			"label"=>"DMF : Liste des sources de la représentation",
			"ignoreFirstLines"=>"4"
		),
		"lexstat" => array(
			"filename"=>"lexstat.txt",
        	"code"=>"lexstat",
			"label"=>"DMF : Liste des termes autorisés du statut juridique de l'objet",
			"ignoreFirstLines"=>"5"
		),
		"lexutil" => array(
			"filename"=>"lexutil.txt",
        	"code"=>"lexutil",
			"label"=>"DMF : Liste des utilisations - destinations",
			"ignoreFirstLines"=>"5"
		),
		"lexrepr" => array(
			"filename"=>"lexrepr.txt",
        	"code"=>"lexrepr",
			"label"=>"DMF : Liste des sujets représentés",
			"ignoreFirstLines"=>"7"
		),
		"lexlieux" => array(
			"filename"=>"lexlieux.txt",
        	"code"=>"lexlieux",
			"ignoreFirstLines"=>"5"
		)
	);

	return $ThesaurusDMF;
}
