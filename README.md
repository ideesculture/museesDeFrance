# Plugin museesDeFrance pour CollectiveAccess

![image](https://raw.githubusercontent.com/ideesculture/museesDeFrance/master/museesDeFrance.png)

**Plugin officiellement validé par le Service des Musées de France (SMF) du Ministère de la Culture français**, listé sur [culture.gouv.fr](https://www.culture.gouv.fr) :

- **Édition informatisée des registres d'inventaire** : validation [décembre 2013](https://www.culture.gouv.fr/thematiques/musees/pour-les-professionnels/conserver-et-gerer-les-collections/informatiser-les-collections-d-un-musee-de-france/informatisation-reglementaire-des-collections-d-un-musee-de-france/procedure-de-validation-des-fonctionnalites-d-edition-informatisee-des-registres-d-inventaire-et-de-depots-des-outils-informatiques-des-musees-de-f)
- **Fonctionnalités liées au récolement décennal** : validation [janvier 2014](https://www.culture.gouv.fr/thematiques/musees/pour-les-professionnels/conserver-et-gerer-les-collections/informatiser-les-collections-d-un-musee-de-france/informatisation-reglementaire-des-collections-d-un-musee-de-france/procedure-de-validation-des-fonctionnalites-liees-au-recolement-decennal-des-collections-des-musees-de-france)
- **Export Joconde direct** : validation en cours, premier déploiement au Musée du Château de Mayenne

Ce plugin construit autour de [CollectiveAccess](https://collectiveaccess.org) (référencé sur le [SILL ID 498](https://code.gouv.fr/sill/detail?id=498) depuis mai 2024) répond aux quatre obligations majeures imposées par la loi française du 4 janvier 2002 sur les Musées de France : inventaire réglementaire infalsifiable, marquage des pièces, suivi des dépôts, récolement décennal.

> Maintenu par [IdéesCulture](https://www.ideesculture.com) (SAS française, Le Mans, depuis 2012) — intégrateur de CollectiveAccess pour les institutions culturelles francophones. 107 institutions accompagnées, dont 5 ministères français.

**IMPORTANT** : La version actuelle de ce plugin ne supporte plus les versions de Providence antérieures à la **version 2**.

## Fonctionnalités principales

### Dans CollectiveAccess

- depuis l'écran Vue d'un objet : bouton **Afficher dans l'inventaire** (import si nouveau, mise à jour si présent et non encore validé)
- depuis le menu **Procédures réglementaires** : réaliser un PV de récolement, accéder au Registre des biens affectés ou au Registre des biens déposés

### Menu « Procédures réglementaires » du plugin

- gestion des utilisateurs avec droit de validation spécifique
- biens acquis/affectés : transfert à l'inventaire, validation, liste paginée filtrable
- **inscription au registre d'inventaire** avec verrouillage réglementaire post-inscription (la ligne devient infalsifiable — exigence loi 2002)
- numérotation à 3 segments (année.lot.objet) conforme SMF
- **récolement décennal** : campagnes, statut par pièce (vu sur place, vu hors les murs, manquant, détruit, en restauration…), génération automatique des PV au format SMF
- gestion des dépôts entrants/sortants (registres séparés)
- **export POP / Joconde** : mapping natif vers la nomenclature nationale, transfert XML conforme

## Déploiements documentés

| Institution | Spécificité |
|---|---|
| Musée du Château de Mayenne | Archéologie médiévale, premier export Joconde en cours |
| Musée des Alpilles | Fonds ethnographique, connecteur POP |
| Musée de l'Imprimerie et de la Communication Graphique (Lyon) | Imprimés rares + bibliothèque d'étude unifiée |
| Musée Malartre | Véhicules photographiés + scans 3D intégrés aux fiches |
| Centre d'Histoire de la Résistance et de la Déportation (Lyon) | Objets + archives + témoignages oraux dans la même base |
| Communauté de l'Ouest Rhodanien | Mutualisation Écomusée du Haut-Beaujolais + Musée Thimonnier |

## Installation et configuration

Cf. la documentation française complète sur [museesDeFranceDocumentation](https://github.com/ideesculture/museesDeFranceDocumentation).

### Thésaurus SMF externalisés (profil Joconde v4)

À partir du profil `profil_joconde_v4`, les thésaurus SMF/Joconde (hébergés sur Opentheso) sont servis via **InformationService** — lus depuis des stores JSON livrés avec le plugin — au lieu d'être chargés dans `ca_list_items`. L'installation est nettement plus légère (pas de harvest en base, index de recherche non gonflé) et la saisie est assistée par un widget de sélection arborescent.

Procédure de déploiement complète (pré-requis serveur, install d'une nouvelle instance, migration d'une instance existante, vérifications) : **[DEPLOIEMENT_thesaurus_v4.md](DEPLOIEMENT_thesaurus_v4.md)**.

> Ce document est destiné à être repris dans une page du [wiki du dépôt](https://github.com/ideesculture/museesDeFrance/wiki).

## Licence

GPL v3 — compatible avec la licence amont de CollectiveAccess.

## Support et intégration

- Documentation utilisateur : [museesDeFranceDocumentation](https://github.com/ideesculture/museesDeFranceDocumentation)
- Support institutionnel : [contact@ideesculture.com](mailto:contact@ideesculture.com)
- Site IdéesCulture : [www.ideesculture.com](https://www.ideesculture.com/fr/musees-de-france)
- Fiche Wikidata du plugin (à venir) — fiche [CollectiveAccess Q2982932](https://www.wikidata.org/wiki/Q2982932)

## Liens institutionnels

- [Service des Musées de France — Procédures de validation](https://www.culture.gouv.fr/thematiques/musees/pour-les-professionnels/conserver-et-gerer-les-collections/informatiser-les-collections-d-un-musee-de-france)
- [SILL — Socle Interministériel des Logiciels Libres, ID 498](https://code.gouv.fr/sill/detail?id=498)
- [Comptoir du Libre — fiche CollectiveAccess](https://comptoir-du-libre.org/fr/softwares/877)