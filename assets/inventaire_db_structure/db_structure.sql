# ************************************************************
# Sequel Pro SQL dump
# Version 4096
#
# http://www.sequelpro.com/
# http://code.google.com/p/sequel-pro/
#
# Hôte: 127.0.0.1 (MySQL 5.6.24)
# Base de données: inventaire_annonay
# Temps de génération: 2015-08-08 13:01:43 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Affichage de la table inventaire_depot
# ------------------------------------------------------------

CREATE TABLE `inventaire_depot` (
  `id` int(20) NOT NULL AUTO_INCREMENT,
  `ca_id` int(20) DEFAULT NULL,
  `numdep` varchar(255) NOT NULL,
  `numdep_sort` varchar(255) DEFAULT NULL,
  `numdep_display` varchar(255) DEFAULT NULL,
  `numinv` text,
  `acte_depot` text,
  `date_prisencharge` date DEFAULT NULL,
  `proprietaire` text,
  `acte_fin_depot` text,
  `date_inscription` text,
  `date_inscription_display` text,
  `designation` text,
  `designation_display` text,
  `inscription` text,
  `materiaux` text,
  `techniques` text,
  `mesures` text,
  `etat` text,
  `auteur` text,
  `auteur_display` text,
  `epoque` text,
  `utilisation` text,
  `provenance` text,
  `observations` text,
  `validated` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `numdep` (`numdep`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Affichage de la table inventaire_depot_photo
# ------------------------------------------------------------

CREATE TABLE `inventaire_depot_photo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `depot_id` int(11) NOT NULL,
  `credits` text NOT NULL,
  `file` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventaire_id` (`depot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Affichage de la table inventaire_inventaire
# ------------------------------------------------------------

CREATE TABLE `inventaire_inventaire` (
  `id` int(20) NOT NULL AUTO_INCREMENT,
  `ca_id` int(20) DEFAULT NULL,
  `numinv` varchar(255) NOT NULL,
  `numinv_sort` varchar(255) DEFAULT NULL,
  `numinv_display` varchar(255) DEFAULT NULL,
  `designation` text,
  `designation_display` text,
  `mode_acquisition` text,
  `donateur` text,
  `date_acquisition` text,
  `avis` text,
  `prix` text,
  `date_inscription` text,
  `date_inscription_display` text,
  `observations` text,
  `inscription` text,
  `materiaux` text,
  `techniques` text,
  `mesures` text,
  `etat` text,
  `auteur` text,
  `auteur_display` text,
  `epoque` text,
  `utilisation` text,
  `provenance` text,
  `validated` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `numinv` (`numinv`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Affichage de la table inventaire_inventaire_photo
# ------------------------------------------------------------

CREATE TABLE `inventaire_inventaire_photo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inventaire_id` int(11) NOT NULL,
  `credits` text NOT NULL,
  `file` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventaire_id` (`inventaire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
