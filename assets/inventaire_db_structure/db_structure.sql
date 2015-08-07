# ************************************************************
# Sequel Pro SQL dump
# Version 4096
#
# http://www.sequelpro.com/
# http://code.google.com/p/sequel-pro/
#
# Hôte: 127.0.0.1 (MySQL 5.6.24)
# Base de données: inventaire_annonay
# Temps de génération: 2015-08-07 12:29:58 +0000
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

DROP TABLE IF EXISTS `inventaire_depot`;

CREATE TABLE `inventaire_depot` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numdep` varchar(255) NOT NULL,
  `numinv` text,
  `date_ref_acte_depot` text,
  `date_entree` date DEFAULT NULL,
  `proprietaire` text,
  `date_ref_acte_fin` text,
  `date_inscription` date DEFAULT NULL,
  `designation` text,
  `inscription` text,
  `materiaux` text,
  `techniques` text,
  `mesures` text,
  `etat` text,
  `auteur` text,
  `epoque` text,
  `utilisation` text,
  `provenance` text,
  `observations` text,
  `validated` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `numdep` (`numdep`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Affichage de la table inventaire_inventaire
# ------------------------------------------------------------

DROP TABLE IF EXISTS `inventaire_inventaire`;

CREATE TABLE `inventaire_inventaire` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numinv` varchar(255) NOT NULL,
  `designation` text,
  `mode_acquisition` text,
  `donateur` text,
  `date_acquisition` text,
  `avis` text,
  `prix` text,
  `date_inscription` text,
  `observations` text,
  `inscription` text,
  `materiaux` text,
  `techniques` text,
  `mesures` text,
  `etat` text,
  `auteur` text,
  `epoque` text,
  `utilisation` text,
  `provenance` text,
  `validated` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `numinv` (`numinv`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Affichage de la table inventaire_photo
# ------------------------------------------------------------

DROP TABLE IF EXISTS `inventaire_photo`;

CREATE TABLE `inventaire_photo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inventaire_id` int(11) NOT NULL,
  `credits` text NOT NULL,
  `file` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventaire_id` (`inventaire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Affichage de la table inventaire_users
# ------------------------------------------------------------

DROP TABLE IF EXISTS `inventaire_users`;

CREATE TABLE `inventaire_users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `display_name` varchar(50) DEFAULT NULL,
  `password` varchar(128) NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
