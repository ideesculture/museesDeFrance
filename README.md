plugin museesDeFrance pour CollectiveAccess
================================
![image](https://raw.githubusercontent.com/ideesculture/museesDeFrance/master/museesDeFrance.png)

Nous avons bâti autour de CollectiveAccess un plugin spécifique et cohérent permettant de réaliser la génération des registres de l'inventaire informatisé suivant les normes des musées de France, mais aussi d'assurer la réalisation et le suivi du récolement des collections.
Tout cela dans une interface simple et agréable.

**IMPORTANT** 

La version actuelle de ce plugin ne supporte plus les versions de Providence antérieures à la **version 2**

## Les fonctionnalités

### Dans CollectiveAccess

- de l'écran Vue d'un objet : bouton Afficher dans l'inventaire (réalise un import si l'objet n'est pas déjà présent dans l'inventaire, met à jour si l'objet est présent et n'est pas encore validé)

- depuis le menu Procédures réglementaires : réaliser un PV de récolement, accéder au Registre des biens affectés ou au Registre des biens déposés

### Dans l'application inventaire

- gestion des utilisateurs (droit de validation spécifique défini dans la configuration, droits d'accès gérés dans les droits d'accès classiques de CollectiveAccess)
- biens acquis/affectés : transférer un objet à l'inventaire, valider un objet,
- afficher la liste d'un objet (paginée, filtrable validés/validés+brouillons)
- actions possibles : inscrire à l'inventaire, retirer de la sélection, afficher dans CA, afficher en planche contact les photos des objets, afficher les détails d'un objet

## Un stockage spécifique pour les données de l'inventaire

Une fois qu'un objet est inscrit à l'inventaire, cette ligne de l'inventaire n'est plus censée être modifiable. CollectiveAccess permet cette modification à tout moment. Lors de l'ajout d'un objet à l'inventaire les données de celui-ci sont recopiées dans un deuxième, soit dans la même base (comportement par défaut, surtout à des fins de de test), soit dans une base de données spécifique distincte.

## La documentation

Ce projet a un wiki sur github : https://github.com/ideesculture/museesDeFrance/wiki

## Rapporter un bug

Contactez nous par email à contact@ideesculture.com ou mieux, saisissez un ticket sur github : https://github.com/ideesculture/museesDeFrance/issues
