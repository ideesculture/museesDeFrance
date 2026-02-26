<?php
/**
 * Configuration des templates pour l'export Joconde
 *
 * Ce fichier définit les templates CollectiveAccess utilisés pour générer
 * les champs de l'export Joconde.
 *
 * Pour personnaliser ces templates, créez un fichier
 * conf/local/joconde_templates.php qui retournera un tableau
 * avec les mêmes clés. Les valeurs définies dans le fichier local
 * écraseront celles définies ici.
 */

return [
	// En-têtes des champs Joconde (dans l'ordre d'export)
	'headers' => ["REF", "INV", "DOMN", "DENO","APPL", "TITR", "AUTR", "PAUT", "ECOL", "ATTR", "LIEUX","PLIEUX", "PERI", "MILL", "PEOC", "EPOQ", "UTIL", "PUTI", "PERU", "MILU", "TECH", "DIMS", "ETAT", "DESC", "GENE", "HIST", "GEOHI", "DECV", "PDEC", "REPR","PREP" , "DREP", "SREP", "ONOM", "LOCA", "STAT", "DACQ","DEPO", "DDPT", "ADPT", "APTN", "EXPO","BIBL", "COMM", "PHOT", "REFIM", "MUSEO", "REDA"],

	// Templates pour chaque champ (dans l'ordre correspondant aux headers)
	// Note: Les variables $museo, $item, $credits, $medianame sont calculées dynamiquement
	'templates' => [
		'REF' => function($vt_object, $museo, $item) {
			return $museo.$item;
		},
		'INV' => "^ca_objects.idno <ifdef code='ca_objects.otherNumber.objectNo'>; <unit relativeto='ca_objects.otherNumber'> ^ca_objects.otherNumber.objectNo (^ca_objects.otherNumber.objectNumberType)</unit></ifdef>",
		'DOMN' => "^ca_objects.domaine",
		'DENO' => "<unit relativeTo='ca_objects.joconde_denomination_c'>^ca_objects.joconde_denomination_c.joconde_denomination (^ca_objects.joconde_denomination_c.precisions_deno)</unit>",
		'APPL' => "^ca_objects.appellation",
		'TITR' => "^ca_objects.preferred_labels",
		'AUTR' => "<unit relativeTo='ca_entities' restrictToRelationshipTypes='creation_auteur, creation_createur'>^ca_entities.preferred_labels.displayname</unit>",
		'PAUT' => "<unit relativeTo='ca_entities' restrictToRelationshipTypes='creation_auteur, creation_createur' delimiter='#'><ifdef code='ca_entities.vitalDates.birth'>^ca_entities.vitalDates.lieu_naissance, ^ca_entities.vitalDates.birth </ifdef><ifdef code='ca_entities.vitalDates.death'>; ^ca_entities.vitalDates.lieu_deces, ^ca_entities.vitalDates.death</ifdef></unit>",
		'ECOL' => "^ca_objects.ecole",
		'ATTR' => "^ca_objects.anciennes_attributions",
		'LIEUX' => "", // TODO: Lieux quand bonne hiérarchie
		'PLIEUX' => "", // TODO: Précision Lieux quand bonne hiérarchie
		'PERI' => "^ca_objects.objectProductionDate",
		'MILL' => "^ca_objects.dateMillesime",
		'PEOC' => "^ca_objects.per_orig_copir_joconde",
		'EPOQ' => "^ca_objects.epoque",
		'UTIL' => "^ca_objects.util_dest",
		'PUTI' => "^ca_objects.precisions_utili_dest",
		'PERU' => "^ca_objects.datePeriod_dest.datePeriod_datation_dest",
		'MILU' => "^ca_objects.dateutil",
		'TECH' => "<unit relativeTo='ca_objects.materiaux_tech_c'>^ca_objects.materiaux_tech_c.materiaux (^ca_objects.materiaux_tech_c.techniques)</unit>",
		'DIMS' => "<unit relativeTo='ca_objects.dimensions'><ifdef code='ca_objects.dimensions.circumference'>Dia. ^ca_objects.dimensions.circumference ;</ifdef><ifdef code='ca_objects.dimensions.dimensions_depth'>Pr. ^ca_objects.dimensions.dimensions_depth ; </ifdef><ifdef code='ca_objects.dimensions.dimensions_height'>H. ^ca_objects.dimensions.dimensions_height <ifdef code='ca_objects.dimensions.type_dimensions'>(^ca_objects.dimensions.type_dimensions)</ifdef>;</ifdef><ifdef code='ca_objects.dimensions.dimensions_width'>L. ^ca_objects.dimensions.dimensions_width ;</ifdef><ifdef code='ca_objects.dimensions.dimensions_length'>H. ^ca_objects.dimensions.dimensions_length ; </ifdef><ifdef code='ca_objects.dimensions.epaisseur'>Ep. ^ca_objects.dimensions.epaisseur</ifdef></unit>",
		'ETAT' => "<unit relativeTo='ca_objects.joconde_etat_c'>^ca_objects.joconde_etat_c.joconde_etat (^ca_objects.joconde_etat_c.joconde_etat_date) </unit>",
		'DESC' => "^ca_objects.description",
		'GENE' => "^ca_objects.genese",
		'HIST' => "^ca_objects.historique",
		'GEOHI' => "^ca_objects.geoHistorique",
		'DECV' => "", // TODO: LIEU DE DECOUVERTE EN ATTENTE REFONTE LIEU
		'PDEC' => "^ca_objects.precision_decouverte",
		'REPR' => "^ca_objects.element_decoratif.element_decoratif_decor",
		'PREP' => "^ca_objects.element_decoratif.element_decoratif_precisions",
		'DREP' => "^ca_objects.element_decoratif.element_decoratif_date",
		'SREP' => "^ca_objects.source_repr",
		'ONOM' => "^ca_objects.onomastique",
		'LOCA' => "<unit relativeTo='ca_storage_locations' restrictToRelationshipTypes='stockage'>^ca_storage_locations.preferred_labels</unit>",
		'STAT' => "^ca_objects.type_propriete ; ^ca_objects.AcquisitionMode ; <unit relativeTo='ca_entities' restrictToRelationshipTypes='proprietaire'>^ca_entities.preferred_labels.displayname</unit> ; Musée du Château de Mayenne",
		'DACQ' => "<ifdef code='ca_objects.date_inventaire'> ^ca_objects.date_inventaire (Date d'inscription au registre d'inventaire) ;</ifdef> <ifdef code='ca_objects.date_ref_acteAcquisition'>^ca_objects.date_ref_acteAcquisition.date_acteAcquisition - ^ca_objects.date_ref_acteAcquisition.ref_acteAcquisition (Date et références de l'acte d'acquisition) </ifdef>",
		'DEPO' => "<unit relativeTo='ca_entities' restrictToRelationshipTypes='depositaire'>^ca_entities.address.city ; ^ca_entities.preferred_labels.displayname</unit>",
		'DDPT' => "^ca_objects.date_ref_acteDepot.date_acteDepot",
		'ADPT' => "^ca_objects.anciensDepots",
		'APTN' => "^ca_objects.anciennes_appartenances",
		'EXPO' => "^ca_objects.exposition",
		'BIBL' => "^ca_objects.bibliography",
		'COMM' => "^ca_objects.com_joconde",
		'PHOT' => function($vt_object, $museo, $item, $credits) {
			return $credits;
		},
		'REFIM' => function($vt_object, $museo, $item, $credits, $medianame) {
			return $medianame;
		},
		'MUSEO' => function($vt_object, $museo) {
			return $museo;
		},
		'REDA' => "<unit relativeTo='ca_entities' restrictToRelationshipTypes='notice_redacteur'>^ca_entities.preferred_labels.displayname</unit>"
	]
];
