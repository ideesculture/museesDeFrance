<?php
/**
 * Created by PhpStorm.
 * User: gautier
 * Date: 07/08/15
 * Time: 15:55
 */

function getMappingBiensAffectes() {
    $va_mapping = array(
        // Correspondance des champs pour l'import dans les BIENS ACQUIS
        "numinv" => array(
            array(
                "field" => 'ca_objects.idno',
                "prefixe" => "numéro d'inventaire : <b>",
                "suffixe" => "</b><br/>"
            )
        ),
        "numinv_sort" => array(
            array(
                "field" => 'ca_objects.idno_sort'
            )
        ),
        "numinv_display" => array(
            array(
                "field" => 'ca_objects.idno'
            )
        ),
        "mode_acquisition" => array(
            array(
                "field" => 'ca_objects.AcquisitionMode',
                "prefixe" => "mode d'acquisition : <b>",
                "suffixe" => "</b><br/>"
            )
        ),
        'donateur'=>array(
            array(
                "field" => 'ca_entities.displayname',
                "relationshipTypes" => "origine_donateur", // donateur
                "prefixe" => "donateur : <b>",
                "suffixe" => "</b><br/>"
            ), array(
                "field" => 'ca_entities.displayname',
                "relationshipTypes" => "origine_testateur", // testateur
                "prefixe" => "testateur : <b>",
                "suffixe" => "</b><br/>"
            ), array(
                "field" => 'ca_entities.displayname',
                "relationshipTypes" => "origine_vendeur", // vendeur
                "prefixe" => "vendeur : <b>",
                "suffixe" => "</b><br/>"
            )
        ),
        'date_acquisition'=>array( //date_acteAcquisition
            array(
                "field" => 'ca_objects.date_ref_acteAcquisition.date_acteAcquisition',
                "post-treatment" => 'caDateToUnixTimestamp',
                "prefixe" => "date de l'acte d'acquisition : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.date_ref_acteAcquisition.ref_acteAcquisition',
                "prefixe" => "référence de l'acte d'acquisition : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'date_affectation',
                "prefixe" => "date d'affectation au musée : <b>",
                "post-treatment" => 'caDateToUnixTimestamp',
                "suffixe" => "</b><br/>"
            )
        ),
        'avis'=>array(
            array(
                "field" => 'ca_objects.avisScientifiques.instance',
                "prefixe" => "instance : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.avisScientifiques.avis_sens',
                "prefixe" => "sens de l'avis : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.avisScientifiques.date_avis',
                "post-treatment" => 'caDateToUnixTimestamp',
                "prefixe" => "date de l'avis : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.avisScientifiques.commentaire_avis',
                "prefixe" => "commentaire sur l'avis : <b>",
                "suffixe" => "</b><br/>"
            )
        ),
        'prix'=>array(
            array(
                "field" => 'ca_objects.prix',
                "prefixe" => "prix : <b>",
                "suffixe" => " </b><br/>"
            ), //OK
            array(
                "field" => 'ca_objects.mentionConcours',
                "prefixe" => "mention des concours publics : <b>",
                "suffixe" => "</b><br/>"
            )
        ),
        'date_inscription'=>array(
            // COLONNE 7
            array("field" => 'ca_objects.date_inventaire',
                "post-treatment" => 'caDateToUnixTimestamp')),
        'date_inscription_display'=>array(
            array("field" => 'ca_objects.date_inventaire',
                "post-treatment" => 'caDateToUnixTimestamp')
        ),
        'designation'=>array(
            // COLONNE 8
            array(
                "field" => 'ca_objects.domaine',
                "prefixe" => "domaine (catégorie du bien) : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.preferred_labels',
                "prefixe" => "titre : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.element_decoratif_decor',
                "prefixe" => "représentation (décor porté) : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.element_decoratif_precisions',
                "prefixe" => "précisions sur la représentation (décor porté) : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.nonpreferred_labels',
                "otherLabelTypeId" => "53",
                "prefixe" => "appellation : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.nonpreferred_labels',
                "otherLabelTypeId" => "54",
                "prefixe" => "dénomination : <b>",
                "suffixe" => "</b><br/>"
            )
        ),
        'designation_display'=>array(
            //array("field" => 'ca_objects.preferred_labels')),//OK
            array(
                "prefixe" => "<small>",
                "field" => 'ca_objects.domaine',
                "suffixe" => "</small><br/>"
            ),
            array(
                "field" => 'ca_objects.preferred_labels'
            )
        ),
        'inscription'=>array(
            array(
                "field" => '<unit relativeTo="ca_objects.inscription_c">^ca_objects.inscription_c.inscription_type <ifdef code="ca_objects.inscription_c.inscription_alphabet|ca_objects.inscription_c.inscription_langue">(</ifdef>^ca_objects.inscription_c.inscription_alphabet <ifdef code="ca_objects.inscription_c.inscription_alphabet,ca_objects.inscription_c.inscription_langue">, </ifdef>^ca_objects.inscription_c.inscription_langue <ifdef code="ca_objects.inscription_c.inscription_alphabet|ca_objects.inscription_c.inscription_langue">)</ifdef></unit>',
                "prefixe" => "Inscriptions : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => '<unit relativeTo="ca_objects.inscription_c">^ca_objects.inscription_c.inscription_txt<ifdef code="ca_objects.inscription_c.inscription_emplacement"> (^ca_objects.inscription_c.inscription_emplacement)</ifdef></unit> <ifdef="ca_objects.pins">; ^ca_objects.pins</ifdef>',
                "prefixe" => "Précisions sur les inscriptions : <b>",
                "suffixe" => "</b><br/>"
            ),
            
        ),
        'materiaux'=>array(
            // COLONNE 10
            array(
                "field" => '<unit relativeTo="ca_objects.materiaux_tech_c" delimiter=";">^ca_objects.materiaux_tech_c.materiaux <ifdef code="ca_objects.materiaux_tech_c.techniques|ca_objects.materiaux_tech_c.materiaux_affixe1|ca_objects.materiaux_tech_c.materiaux_affixe2">(</ifdef>^ca_objects.materiaux_tech_c.techniques<ifdef code="ca_objects.materiaux_tech_c.techniques,ca_objects.materiaux_tech_c.materiaux_affixe1">,</ifdef> ^ca_objects.materiaux_tech_c.materiaux_affixe1<ifdef code="ca_objects.materiaux_tech_c.materiaux_affixe1,ca_objects.materiaux_tech_c.materiaux_affixe2">,</ifdef>^ca_objects.materiaux_tech_c.materiaux_affixe2<ifdef code="ca_objects.materiaux_tech_c.techniques|ca_objects.materiaux_tech_c.materiaux_affixe1|ca_objects.materiaux_tech_c.materiaux_affixe2">)</ifdef></unit></unit>',
                "prefixe" => "matériaux/techniques : <b>",
                "suffixe" => "</b><br/>"
            )
        ),
        'mesures'=>array(
            // COLONNE 12
            array(
                "field" => '<unit relativeTo="ca_objects.dimensions" delimiter=";"><ifdef code="ca_objects.dimensions.circumference">D. ^ca_objects.dimensions.circumference, </ifdef><ifdef code="ca_objects.dimensions.dimensions_depth">P. ^ca_objects.dimensions.dimensions_depth, </ifdef><ifdef code="ca_objects.dimensions.dimensions_height">H. ^ca_objects.dimensions.dimensions_height, </ifdef><ifdef code="ca_objects.dimensions.dimensions_poids">Pds. ^ca_objects.dimensions.dimensions_poids, </ifdef><ifdef code="ca_objects.dimensions.dimensions_width">l. ^ca_objects.dimensions.dimensions_width</ifdef><ifdef code="ca_objects.dimensions.type_dimensions"> (^ca_objects.dimensions.type_dimensions)</ifdef></unit>',
                "prefixe" => "dimensions : ",
                "suffixe" => "<br/>"
            )
        ),
        'etat'=>array(
            // COLONNE 13
            array(
                "field" => 'ca_objects.etat_acqui_depot',
                "prefixe" => "etat au moment de l'acquisition : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.constatEtat.constat_date',
                "post-treatment" => 'caDateToUnixTimestamp',
                "prefixe" => "date du constat d'état : <b>",
                "suffixe" => "</b><br/>"
            )
        ),
        'auteur'=>array(
            // COLONNE 14
            array(
                "field" => 'ca_entities.preferred_labels',
                "relationshipTypes" => "creation_auteur", // auteur
                "prefixe" => "auteur : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_entities.preferred_labels',
                "relationshipTypes" => "executant",
                "prefixe" => "exécutant : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_entities.preferred_labels',
                "relationshipTypes" => "origine_collector",
                "prefixe" => "collecteur : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => '<unit relativeTo="ca_objects.ecole">^ca_objects.ecole.ecole_ecole<ifdef code="ca_objects.ecole.ecole_affixe1|ca_objects.ecole.ecole_affixe2"> (</ifdef> ^ca_objects.ecole.ecole_affixe1<ifdef code="ca_objects.ecole.ecole_affixe1,ca_objects.ecole.ecole_affixe2">,</ifdef> ^ca_objects.ecole.ecole_affixe2<ifdef code="ca_objects.ecole.ecole_affixe1|ca_objects.ecole.ecole_affixe2">)</ifdef></unit>',
                "prefixe" => "école : <b>",
                "suffixe" => "</b><br/>"
            )
        ),
        'auteur_display'=>array(
            array(
                "field" => 'ca_entities.preferred_labels',
                "relationshipTypes" => "creation_auteur" // auteur
            )
        ),
        'epoque'=>array(
            // COLONNE 15
            array(
                "field" => 'ca_objects.periode_crea_exec',
                "prefixe" => "Période de création / exécution  : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.peru',
                "prefixe" => "Période d'utilisation / destination  : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.dateMillesime',
                "prefixe" => "Millésime de création / exécution :  <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.milu',
                "prefixe" => "Millésime d'utilisation / destination : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => '<unit relativeTo="ca_objects.epoque"><ifdef code="ca_objects.epoque.epoque_epoque">Epoque : ^ca_objects.epoque.epoque_epoque, </ifdef>
                <ifdef code="ca_objects.epoque.epoque_style">Style : ^ca_objects.epoque.epoque_style,</ifdef>
                <ifdef code="ca_objects.epoque.epoque_style">Mouvement : ^ca_objects.epoque.epoque_mouvement</ifdef></unit>',
                "prefixe" => "Epoque / style / mouvement  : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.useDate',
                "post-treatment" => 'caDateToUnixTimestamp',
                "prefixe" => "Date de collecte ou de découverte : <b>",
                "suffixe" => "</b><br/>"
            )
        ),
        'utilisation'=>array(
            // COLONNE 16
            array(
                "field" => '<unit relativeTo="ca_objects.util">^ca_objects.util.util_util <ifdef code="ca_objects.util.util_affixe2 ">(^ca_objects.util.util_affixe2)</ifdef></unit>',
                "prefixe" => "fonction d'usage : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.puti',
                "prefixe" => "précisions : <b>",
                "suffixe" => "</b><br/>"
            )
        ),
        'provenance'=>array(
            // COLONNE 17
            array(
                "field" => 'ca_places.preferred_labels',
                "relationshipTypes" => "created",
                "prefixe" => "Lieux de création ou d'éxécution : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.preci_lieu_crea_exec',
                "prefixe" => "Précisions sur le lieux de création ou d'éxécution : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.geoHistorique',
                "prefixe" => "géographie historique : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_places.preferred_labels',
                "relationshipTypes" => "utilisation",
                "prefixe" => "lieux d'utilisation ou de destination : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.preci_lieu_util_dest',
                "prefixe" => "Précisions sur le lieux d'utilisation ou de destination : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_places.preferred_labels',
                "relationshipTypes" => "decouverte,collecte,recolte",
                "prefixe" => "lieu de découverte, collecte, récolte : <b>",
                "suffixe" => "</b><br/>"
            )
        ),
        'observations'=>array(
            // COLONNE 18
            array(
                "field" => 'ca_objects.date_presence',
                "post-treatment" => 'caDateToUnixTimestamp',
                "prefixe" => "première date attestée dans le musée si origine inconnue : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.anciennes_appartenances',
                "prefixe" => "utilisateur illustre, premier et dernier propriétaire : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.otherNumber',
                "prefixe" => "ancien ou autre numéro d'inventaire : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.mention_radiation',
                "prefixe" => "mentions apportées en cas de radiation : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.date_vol',
                "post-treatment" => 'caDateToUnixTimestamp',
                "prefixe" => "date de vol ou de disparition : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.date_bien_retrouve',
                "post-treatment" => 'caDateToUnixTimestamp',
                "prefixe" => "date à laquelle le bien a été retrouvé : <b>",
                "suffixe" => "</b><br/>"
            ),
            array(
                "field" => 'ca_objects.inv_ensemble_complexe_c',
                "prefixe" => "sous-inventaire dans le cas d'un ensemble complexe : <b>",
                "suffixe" => "</b><br/>"
            )
        )
    );

    return $va_mapping;
}
