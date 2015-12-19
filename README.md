plugin museesDeFrance pour CollectiveAccess
================================
![image](https://raw.githubusercontent.com/ideesculture/museesDeFrance/master/museesDeFrance.png)

Nous avons bâti autour de CollectiveAccess une application spécifique permettant de réaliser la géné- ration des registres de l'inventaire informatisé suivant les normes des musées de France.
Tout cela dans une interface simple et agréable.

## Les fonctionnalités

### Dans CollectiveAccess

- de l'écran Vue d'un objet : bouton Afficher dans l'inventaire (réalise un import si l'objet n'est pas déjà présent dans l'inventaire, met à jour si l'objet est présent et n'est pas encore validé)

- depuis le menu Procédures réglementaires : réaliser un PV de récolement, accéder au Registre des biens affectés ou au Registre des biens déposés

### Dans l'application inventaire

- gestion des utilisateurs (pas de login nécs saire pour la consultation, droit de valida- tion spécifique)
- biens acquis/affectés : transférer un objet à l'inventaire, valider un objet,
- afficher la liste d'un objet (paginée, filtrable validés/validés+brouillons)
- actions possibles : inscrire à l'inventaire, retirer de la sélection, afficher dans CA, afficher en planche contact les photos des objets, afficher les détails d'un objet

## L'application inventaire

Une fois qu'un objet est inscrit à l'inventaire, cette ligne de l'inventaire n'est plus censée être modifiable. CollectiveAccess permet cette modification à tout moment. Nous avons donc fait le choix d'une deuxième application, qui nécessite CollectiveAccess pour fonctionner et qui ne sert qu'à figer les enregistrements des objets une fois ceux-ci inscrits dans l'inventaire.

Cette application Inventaire est disponible ici : [https://github.com/ideesculture/inventaire](https://github.com/ideesculture/inventaire)

## La documentation

Ce projet a un wiki sur github : https://github.com/ideesculture/museesDeFrance/wiki

## Rapporter un bug

Contactez nous par email à contact@ideesculture.com ou mieux, saisissez un ticket sur github : https://github.com/ideesculture/museesDeFrance/issues
