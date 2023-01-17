-- Adminer 4.1.0 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

CREATE TABLE `yourTableName` (
  `ticketID` int(10) unsigned NOT NULL,
  `ticketSluzba` varchar(100) NOT NULL,
  `ticketSluzbaID` int(10) unsigned NOT NULL,
  `ticketCreated` datetime NOT NULL,
  `ticketREF` varchar(25) NOT NULL,
  `laTicketID` varchar(25) NOT NULL,
  PRIMARY KEY (`ticketID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- 2023-01-17 21:19:51