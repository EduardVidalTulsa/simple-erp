-- SPDX-License-Identifier: GPL-3.0-or-later
-- simple-erp database structure (no data). The per-year/per-shop tables (YYYY_Empresa_N_line,
-- _index_relacio, _pdf, _rel2rel), cashkeeper and receptes are created by the application,
-- copying the meta_* templates at the end of this file.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `tarifes` (
  `ID` int(10) unsigned NOT NULL,
  `data_hora` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user` varchar(255) NOT NULL,
  `id_dealer` int(11) NOT NULL,
  `id_familia` int(11) NOT NULL,
  `bool_in_bulk` tinyint(4) NOT NULL DEFAULT 1,
  `cost_price` decimal(9,5) NOT NULL,
  `pvp` decimal(9,5) NOT NULL,
  `pvpi` varchar(255) NOT NULL,
  `pv1` decimal(9,5) NOT NULL,
  `pv1i` varchar(255) NOT NULL,
  `pv2` decimal(9,5) NOT NULL,
  `pv2i` varchar(255) NOT NULL,
  `pv3` decimal(9,5) NOT NULL,
  `pv3i` varchar(255) NOT NULL,
  `pv4` decimal(9,5) NOT NULL,
  `pv4i` varchar(255) NOT NULL,
  `historic` int(10) unsigned NOT NULL DEFAULT 1,
  `ultimpreu` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `tipusiva` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `PLU` int(11) NOT NULL DEFAULT 1 COMMENT '0 ja està a la bascula, 1 falta actualitzar a la bascula, 2, es un nou',
  PRIMARY KEY (`ID`,`historic`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `package_format` (
  `ID` int(11) NOT NULL COMMENT 'Codi del producte',
  `ID_package_type` smallint(6) NOT NULL DEFAULT 1 COMMENT '1 format majorista',
  `bool_in_a_box` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'SI 0 en sac si 1 en caixa',
  `units_package` smallint(5) unsigned DEFAULT 1 COMMENT 'Unitats al paquet',
  `weight` int(11) NOT NULL DEFAULT 5000 COMMENT 'en grams',
  `gross_weight` int(11) NOT NULL DEFAULT 5010 COMMENT 'pes brut en grams',
  `height` int(11) NOT NULL DEFAULT 300 COMMENT 'alçada en milimetres',
  `lenght` int(11) NOT NULL DEFAULT 400 COMMENT 'longitut en milimetres',
  `width` int(11) NOT NULL DEFAULT 250 COMMENT 'amplada en milimetres',
  `fromlot` char(1) NOT NULL COMMENT 'el primer lot que tindrà aquesta mesura',
  `hidden_date_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`,`ID_package_type`,`hidden_date_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `productnamesurlencoded` (
  `ID` int(11) NOT NULL,
  `lang` varchar(4) NOT NULL DEFAULT 'ca',
  `value` varchar(200) NOT NULL,
  `origen` varchar(255) DEFAULT 'Espanya EU' COMMENT 'description1 \\n',
  `ingredients` text DEFAULT '' COMMENT 'description2 \\n',
  `advertencia` mediumtext DEFAULT 'Pot contenir traces de cacauet, altres fruits secs, gluten, sèsam i soja (degut a la manipulació en el mateix establiment). Protegir de la llum solar. Preservar d\'olors agressius. Conservar en un lloc net, fresc i sec.' COMMENT 'description3 \\n',
  `link-rewrite` varchar(40) DEFAULT './',
  `Informacio_nutricional` mediumtext DEFAULT NULL,
  `bool_available_now` tinyint(4) DEFAULT 1,
  `description_short` mediumtext DEFAULT '',
  `bool_eco` tinyint(4) DEFAULT 0 COMMENT 'es eco?',
  PRIMARY KEY (`ID`,`lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `IVA` (
  `ID` tinyint(3) unsigned NOT NULL,
  `Value` decimal(6,2) NOT NULL,
  `Description` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `Pagament` (
  `ID` tinyint(3) unsigned NOT NULL,
  `Value` varchar(255) NOT NULL,
  `ID_tipus_relacio` int(11) NOT NULL,
  `lang` varchar(2) NOT NULL DEFAULT 'ca',
  `bool_allow_payment` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `ID_tipus_relacio` (`ID_tipus_relacio`),
  CONSTRAINT `Pagament_ibfk_1` FOREIGN KEY (`ID_tipus_relacio`) REFERENCES `tipus_relacio` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `Empresa` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Value` varchar(255) NOT NULL,
  `Fiscal_Name` varchar(255) DEFAULT NULL,
  `Direccio` varchar(255) NOT NULL,
  `Poblacio` varchar(255) DEFAULT NULL,
  `Phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_public` varchar(255) DEFAULT NULL COMMENT 'Email públic per documents',
  `condicions` text DEFAULT NULL COMMENT 'Condicions personalitzades \r\n   per documents',
  `NIF` varchar(12) NOT NULL,
  `sell_wholesale` varchar(12) NOT NULL DEFAULT '0',
  `termal_head_1` varchar(25) DEFAULT NULL,
  `termal_head_2` varchar(35) DEFAULT NULL,
  `termal_head_3` varchar(32) DEFAULT NULL,
  `termal_tail_1` varchar(35) DEFAULT NULL,
  `tiquet_cut_before_head1_bool` tinyint(4) NOT NULL DEFAULT 0 COMMENT '1: imprimeix termal_head_1 del proper tiquet abans de tallar (GS V 0); 0: capçalera al principi i cut()',
  `bool_ticket_printer` tinyint(4) DEFAULT 0 COMMENT 'té impresora de tiquets?',
  `sticker_printer` varchar(35) DEFAULT NULL,
  `a4_printer` varchar(35) DEFAULT NULL,
  `printerserver` varchar(35) DEFAULT 'localhost',
  `bool_print_pdf` tinyint(4) DEFAULT 0,
  `telegram_relacions` varchar(35) DEFAULT NULL,
  `scale_uri` varchar(35) DEFAULT NULL,
  `scale_lang` varchar(35) DEFAULT 'ca',
  `caixa_uri` varchar(35) DEFAULT NULL COMMENT 'si té les bascules antigues i hem de conectar al cashkeeper',
  `cashkeeper` varchar(35) DEFAULT NULL COMMENT 'windows que el controla',
  `cashkeeper_1cent` varchar(35) DEFAULT '1' COMMENT '0 si no l''accepta',
  `ID_Empresa_a_mxgestio` int(10) unsigned NOT NULL DEFAULT 0,
  `url_public` varchar(255) DEFAULT NULL,
  `seu_central` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'aquesta empresa marca quina és la seu central (on es pot crear makevoucher)',
  `url_seu` varchar(255) NOT NULL DEFAULT '' COMMENT 'url pública del lloc/seu d''aquesta empresa, p.ex. https://botiga.example.com',
  `host_ssh` varchar(255) NOT NULL DEFAULT '' COMMENT 'host SSH on fer ssh/scp per imprimir a la impressora d''aquesta seu (buit = local, impressora d''aquesta mateixa màquina)',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `Clients` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Value` varchar(255) NOT NULL COMMENT 'Nom Cognoms/ raó social',
  `fiscal_address` varchar(255) NOT NULL COMMENT 'Adreça Fiscal',
  `Poblacio` varchar(255) DEFAULT NULL COMMENT 'Codi postal, població',
  `NIF` varchar(255) DEFAULT NULL,
  `bool_equivalency_charge` int(11) NOT NULL DEFAULT 0 COMMENT 'Paga recàrreg d''equivalència?',
  `bool_Active` int(11) NOT NULL DEFAULT 1 COMMENT 'Accepta protecció dades?',
  `id_tarifa` int(11) DEFAULT 0 COMMENT 'Tarifa 10 pvp, 1  1000, 2, 2000, 3 3000, 4 germans vidal',
  `bool_tax_detall` int(11) DEFAULT 1 COMMENT 'Vol l''IVA desglosat?',
  `comercial_name` varchar(255) DEFAULT NULL COMMENT 'Nom comercial',
  `deliver_address` varchar(255) DEFAULT NULL COMMENT 'Adreça d''entrega',
  `email` varchar(255) DEFAULT NULL COMMENT 'Correu electrònic',
  PRIMARY KEY (`ID`),
  KEY `id_tarifa` (`id_tarifa`),
  CONSTRAINT `Clients_ibfk_1` FOREIGN KEY (`id_tarifa`) REFERENCES `tarifa` (`ID`) ON DELETE NO ACTION,
  CONSTRAINT `Clients_ibfk_2` FOREIGN KEY (`id_tarifa`) REFERENCES `tarifa` (`ID`),
  CONSTRAINT `Clients_ibfk_3` FOREIGN KEY (`id_tarifa`) REFERENCES `tarifa` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `scalelist` AS SELECT
 NULL AS `id`,
 NULL AS `value`,
 NULL AS `exist`,
 NULL AS `formatpvp`,
 NULL AS `bulk`,
 NULL AS `pvp`,
 NULL AS `cost`,
 NULL AS `pv4i`,
 NULL AS `unitat` */;
SET character_set_client = @saved_cs_client;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `tipus_relacio` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Value` varchar(255) NOT NULL,
  `lang` varchar(2) NOT NULL DEFAULT 'ca',
  `bool_allow_payment` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `users` (
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `ID_Empresa` int(10) unsigned NOT NULL,
  `url_key` varchar(255) NOT NULL,
  `id_bascula` varchar(255) NOT NULL DEFAULT '1',
  `bascula_vertical` tinyint(4) NOT NULL DEFAULT 1,
  `pot_veure_les_altres_empreses` tinyint(4) unsigned NOT NULL DEFAULT 0,
  `pot_fer_vals` tinyint(4) unsigned NOT NULL DEFAULT 0 COMMENT 'pot crear xecs regal/descompte a /makevoucher',
  KEY `ID_Empresa` (`ID_Empresa`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`ID_Empresa`) REFERENCES `Empresa` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish2_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `package_description` (
  `ID` int(10) unsigned NOT NULL,
  `ID_package_type` smallint(5) unsigned NOT NULL,
  `lang` varchar(2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `weight_text` varchar(255) NOT NULL COMMENT 'pes mínim, pes aprox...',
  PRIMARY KEY (`ID`,`ID_package_type`,`lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `package_pvp` (
  `ID` int(11) NOT NULL,
  `ID_package_type` int(11) NOT NULL,
  `pvp` varchar(255) NOT NULL COMMENT 'Preu venta public iva inclós o bé formula',
  PRIMARY KEY (`ID`,`ID_package_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `imprimeix` (
  `ID` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `ID_product` int(11) NOT NULL,
  `package` smallint(5) unsigned DEFAULT NULL,
  `lot` varchar(255) DEFAULT NULL,
  `bbf` varchar(255) DEFAULT NULL,
  `units` smallint(6) NOT NULL,
  `preu_or_etiqueta` tinyint(4) NOT NULL COMMENT '0 preu 1 etiqueta',
  PRIMARY KEY (`ID`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `equivalency_charge` (
  `ID_iva` int(10) unsigned NOT NULL COMMENT 'PER CADA IVA HI HA UNA TAXA',
  `TAX` decimal(6,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `ultimpes` (
  `ID` tinyint(3) unsigned NOT NULL COMMENT 'idbascula',
  `pes` int(10) unsigned NOT NULL COMMENT 'pes en grams',
  `preu` int(10) unsigned NOT NULL COMMENT 'preu*100'
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `tarifa` (
  `ID` int(11) NOT NULL,
  `Value` varchar(255) NOT NULL,
  `lang` varchar(255) NOT NULL DEFAULT 'ca',
  `column_name` varchar(255) NOT NULL DEFAULT 'pvp',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Ei que syncmaria no copia aquesta taula, si ho modifiques canvia-ho a tots els clients!';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `ean` (
  `ID` int(11) unsigned NOT NULL,
  `ID_package_type` smallint(6) unsigned NOT NULL DEFAULT 0,
  `Value` decimal(13,0) unsigned NOT NULL,
  PRIMARY KEY (`ID`,`Value`),
  UNIQUE KEY `ID_ID_package_type_Value` (`ID`,`ID_package_type`,`Value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='zbarcam /dev/video0  -q --raw  | while read LINE; do beep; xdotool type $LINE; xdotool key KP_Enter; done';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `Clients_phone` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ID_Client` int(11) NOT NULL,
  `Value` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `prefered` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Value` (`Value`),
  KEY `ID_Client` (`ID_Client`),
  CONSTRAINT `Clients_phone_ibfk_1` FOREIGN KEY (`ID_Client`) REFERENCES `Clients` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `productnames` (
  `ID` int(11) NOT NULL,
  `lang` varchar(4) NOT NULL DEFAULT 'ca',
  `value` varchar(40) NOT NULL,
  `a_bascula` tinyint(3) NOT NULL DEFAULT 0 COMMENT '0 actualitzada, 1 per actualitzar, 2 producte nou',
  `a_bascula_anna` tinyint(3) NOT NULL DEFAULT 0 COMMENT '0 actualitzada, 1 per actualitzar, 2 producte nou',
  PRIMARY KEY (`ID`,`lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `1_Venedor` (
  `ID` tinyint(3) unsigned NOT NULL,
  `Value` varchar(255) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `ID` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `dealers` (
  `ID` int(10) unsigned NOT NULL,
  `Value` varchar(255) NOT NULL,
  `hidden_user` varchar(255) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `hydra_origen` (
  `CODI_IDENT` int(11) DEFAULT NULL,
  `SECC_MAQUI` int(11) DEFAULT NULL,
  `CODI_SUB` int(11) DEFAULT NULL,
  `CODI_FAM` int(11) DEFAULT NULL,
  `PLU` int(11) DEFAULT NULL,
  `CODIGO` int(11) DEFAULT NULL,
  `CODI_PES` varchar(1) DEFAULT NULL,
  `TIPO_IVA` int(11) DEFAULT NULL,
  `PRECIO` int(11) DEFAULT NULL,
  `USATRAMOS` varchar(5) DEFAULT NULL,
  `PRC100G` varchar(5) DEFAULT NULL,
  `EUROS` decimal(5,2) DEFAULT NULL,
  `PRC3` int(11) DEFAULT NULL,
  `ETQ` int(11) DEFAULT NULL,
  `PREF` int(11) DEFAULT NULL,
  `FCB` int(11) DEFAULT NULL,
  `NING` int(11) DEFAULT NULL,
  `CADUCIDAD` int(11) DEFAULT NULL,
  `TARA` int(11) DEFAULT NULL,
  `TIP_LET1` int(11) DEFAULT NULL,
  `TIP_LET2` int(11) DEFAULT NULL,
  `TIP_LET3` int(11) DEFAULT NULL,
  `TIP_LET4` int(11) DEFAULT NULL,
  `TIP_LET5` int(11) DEFAULT NULL,
  `TIP_LET6` int(11) DEFAULT NULL,
  `TIP_LET7` int(11) DEFAULT NULL,
  `TIP_LET8` int(11) DEFAULT NULL,
  `TIP_LET9` int(11) DEFAULT NULL,
  `TIP_LET0` int(11) DEFAULT NULL,
  `TIP_LETX` int(11) DEFAULT NULL,
  `DES_PLU1` varchar(25) DEFAULT NULL,
  `DES_PLU2` varchar(25) DEFAULT NULL,
  `DES_PLU3` varchar(25) DEFAULT NULL,
  `DES_PLU4` varchar(25) DEFAULT NULL,
  `DES_PLU5` varchar(25) DEFAULT NULL,
  `DES_PLU6` varchar(25) DEFAULT NULL,
  `DES_PLU7` varchar(25) DEFAULT NULL,
  `DES_PLU8` varchar(25) DEFAULT NULL,
  `DES_PLU9` varchar(25) DEFAULT NULL,
  `DES_PLU0` varchar(25) DEFAULT NULL,
  `DES_PLUX` varchar(25) DEFAULT NULL,
  `DES_PLU11` varchar(25) DEFAULT NULL,
  `DES_PLU12` varchar(25) DEFAULT NULL,
  `DES_PLU13` varchar(25) DEFAULT NULL,
  `DES_PLU14` varchar(25) DEFAULT NULL,
  `DES_PLU15` varchar(25) DEFAULT NULL,
  `DES_PLU16` varchar(25) DEFAULT NULL,
  `DES_PLU17` varchar(25) DEFAULT NULL,
  `DES_PLU18` varchar(25) DEFAULT NULL,
  `DES_PLU19` varchar(25) DEFAULT NULL,
  `DES_PLU20` varchar(25) DEFAULT NULL,
  `TRAN_PLU` varchar(1) DEFAULT NULL,
  `TRAN_PLUSC10` int(11) DEFAULT NULL,
  `TRAN_TEXTO` varchar(1) DEFAULT NULL,
  `TRAN_TEXTOSC10` int(11) DEFAULT NULL,
  `TRAN_TX1` varchar(1) DEFAULT NULL,
  `TRAN_TX1SC10` int(11) DEFAULT NULL,
  `ART_CB` varchar(12) DEFAULT NULL,
  `TRAN_CB` varchar(1) DEFAULT NULL,
  `TRAN_CBSC10` int(11) DEFAULT NULL,
  `BORRADO` varchar(5) DEFAULT NULL,
  `TRAN_EN` int(11) DEFAULT NULL,
  `TRAN_EL` int(11) DEFAULT NULL,
  `TRAN_EP` int(11) DEFAULT NULL,
  `TRAN_TEXTOEL` varchar(1) DEFAULT NULL,
  `GRUPO_CONSERV` int(11) DEFAULT NULL,
  `BALENV` varchar(5) DEFAULT NULL,
  `lMix` varchar(5) DEFAULT NULL,
  `Poid` int(11) DEFAULT NULL,
  `lTraza` varchar(5) DEFAULT NULL,
  `Traza` int(11) DEFAULT NULL,
  `Column_2etq` int(11) DEFAULT NULL,
  `retirada` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `package_type` (
  `ID` smallint(6) NOT NULL COMMENT 'Codi tipus de paquet',
  `Value` varchar(255) NOT NULL DEFAULT '' COMMENT '"Descripció"',
  `lang` varchar(2) NOT NULL DEFAULT 'ca',
  `bool_in_a_box` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'SI 0 en sac si 1 en caixa',
  `weight` int(11) NOT NULL DEFAULT 5000 COMMENT 'en grams/unitats que conté',
  `gross_weight` int(11) NOT NULL DEFAULT 5010 COMMENT 'pes brut en grams',
  `height` int(11) NOT NULL DEFAULT 300 COMMENT 'alçada en milimetres',
  `lenght` int(11) NOT NULL DEFAULT 400 COMMENT 'longitut en milimetres',
  `width` int(11) NOT NULL DEFAULT 250 COMMENT 'amplada en milimetres',
  `fromlot` char(1) NOT NULL COMMENT 'el primer lot que tindrà aquesta mesura',
  `hidden_date_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`,`lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='En aquesta posem els valors per defecte de cada tipus de paquet';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `package_botiga` (
  `ID` int(10) unsigned NOT NULL COMMENT 'ID producte',
  `ID_package_type` int(10) unsigned NOT NULL,
  `ID_shop` tinyint(3) unsigned NOT NULL,
  `Priority` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Prioritat per les comandes',
  PRIMARY KEY (`ID`,`ID_package_type`,`ID_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `families` (
  `ID` int(11) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `botigues` (
  `ID` int(11) unsigned NOT NULL,
  `Value` varchar(255) NOT NULL,
  `Direccio` tinytext NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `ID` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `scalelist`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_uca1400_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */

/*!50001 VIEW `scalelist` AS select `t`.`ID` AS `id`,`p`.`value` AS `value`,'1' AS `exist`,concat(replace(`t`.`pvp`,'.',','),if(`t`.`bool_in_bulk` = 1,'€/Kg','€/uni')) AS `formatpvp`,`t`.`bool_in_bulk` AS `bulk`,`t`.`pvp` AS `pvp`,`t`.`pv4` AS `cost`,`t`.`pv4i` AS `pv4i`,if(`t`.`bool_in_bulk` = 1,'€/Kg','€/uni') AS `unitat` from (`productnamesurlencoded` `p` join `tarifes` `t` on(`t`.`ID` = `p`.`ID`)) where `p`.`lang` = 'ca' and `t`.`cost_price` <> 0 and (`t`.`ID`,`t`.`data_hora`) in (select `tarifes`.`ID`,max(`tarifes`.`data_hora`) from `tarifes` where `tarifes`.`data_hora` < curdate() group by `tarifes`.`ID`) order by `t`.`ID` <> 0 desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
-- templates copied with CREATE TABLE ... LIKE for every year and shop
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `meta_index_relacio` (
  `ID` int(11) NOT NULL,
  `ID_client` int(11) NOT NULL,
  `ID_tipus_relacio` int(11) NOT NULL,
  `ID_Venedor` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `ID_Pagament` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `tax_detall` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `editable` tinyint(4) unsigned NOT NULL DEFAULT 1,
  `relacio_oberta` tinyint(4) unsigned NOT NULL DEFAULT 1,
  `data` date NOT NULL DEFAULT current_timestamp(),
  `equivalency_charge` int(11) NOT NULL DEFAULT 0,
  `fiscal_name` varchar(255) NOT NULL,
  `NIF` varchar(255) NOT NULL DEFAULT '',
  `fiscal_address` varchar(255) NOT NULL DEFAULT '',
  `Poblacio` varchar(255) DEFAULT NULL,
  `comercial_name` varchar(255) NOT NULL DEFAULT '',
  `deliver_address` varchar(255) NOT NULL DEFAULT '',
  `phone` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) DEFAULT NULL,
  `discount` varchar(255) NOT NULL DEFAULT '0',
  `uuid` varchar(255) NOT NULL,
  `tryton_invoice_id` int(11) DEFAULT NULL,
  `bool_tryton_sync` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`,`ID_tipus_relacio`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `ID_tipus_relacio` (`ID_tipus_relacio`),
  KEY `ID_Pagament` (`ID_Pagament`),
  CONSTRAINT `meta_index_relacio_ibfk_1` FOREIGN KEY (`ID_tipus_relacio`) REFERENCES `tipus_relacio` (`ID`),
  CONSTRAINT `meta_index_relacio_ibfk_2` FOREIGN KEY (`ID_Pagament`) REFERENCES `Pagament` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `meta_line` (
  `ID` bigint(20) unsigned NOT NULL COMMENT 'id de relació',
  `ID_tipus_relacio` int(11) NOT NULL,
  `line` int(10) unsigned NOT NULL COMMENT 'numero de línea',
  `ID_product` int(10) unsigned NOT NULL,
  `description` varchar(255) NOT NULL,
  `units` decimal(8,3) NOT NULL,
  `bulk` tinyint(3) unsigned DEFAULT 1,
  `cost` float(25,10) NOT NULL,
  `cost_price` decimal(8,2) NOT NULL DEFAULT 0.00,
  `IVA` decimal(6,2) NOT NULL COMMENT '% d''iva',
  `EQUIVALENCY_CHARGE` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT '% RECARREG',
  `hidden_now` datetime NOT NULL,
  `lotnumber` varchar(255) NOT NULL DEFAULT '',
  `discount` tinyint(255) NOT NULL DEFAULT 0 COMMENT '% de descompte',
  `deleted_line` tinyint(255) NOT NULL DEFAULT 0 COMMENT 'la linea ha estat eliminada',
  PRIMARY KEY (`ID`,`ID_tipus_relacio`,`line`),
  KEY `ID_tipus_relacio` (`ID_tipus_relacio`),
  CONSTRAINT `meta_line_ibfk_2` FOREIGN KEY (`ID_tipus_relacio`) REFERENCES `tipus_relacio` (`ID`) ON DELETE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `meta_pdf` (
  `ID` int(10) unsigned NOT NULL,
  `ID_tipus_relacio` int(11) unsigned NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `pdf_fet` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `email_enviat` tinyint(3) NOT NULL DEFAULT 0,
  `print_A4` tinyint(4) NOT NULL DEFAULT 0,
  `fiscal_name` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` char(255) NOT NULL,
  `datetime_creation` datetime NOT NULL COMMENT 'a l''hora que es crea, cron enviarà el mail al cap de mínim 5 minuts',
  `LaTeX` text CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  PRIMARY KEY (`ID`,`ID_tipus_relacio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `lang` (
  `ID` tinyint(3) unsigned NOT NULL,
  `Value` varchar(4) NOT NULL,
  `Name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `llistapvp` AS SELECT
 NULL AS `id`,
 NULL AS `value`,
 NULL AS `cost`,
 NULL AS `pvp`,
 NULL AS `pvpi`,
 NULL AS `pv1`,
 NULL AS `pv1i`,
 NULL AS `pv2`,
 NULL AS `pv2i`,
 NULL AS `pv3`,
 NULL AS `pv3i`,
 NULL AS `pv4`,
 NULL AS `pv4i`,
 NULL AS `tipusiva`,
 NULL AS `modificat`,
 NULL AS `datacrua`,
 NULL AS `a_bascula`,
 NULL AS `a_bascula_anna`,
 NULL AS `bool_in_bulk` */;
SET character_set_client = @saved_cs_client;
/*!50001 DROP VIEW IF EXISTS `llistapvp`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_uca1400_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */

/*!50001 VIEW `llistapvp` AS select lpad(`p`.`ID`,4,0) AS `id`,`p`.`value` AS `value`,replace(`t`.`cost_price`,'.',',') AS `cost`,replace(`t`.`pvp`,'.',',') AS `pvp`,replace(`t`.`pvpi`,'.',',') AS `pvpi`,replace(`t`.`pv1`,'.',',') AS `pv1`,replace(`t`.`pv1i`,'.',',') AS `pv1i`,replace(`t`.`pv2`,'.',',') AS `pv2`,replace(`t`.`pv2i`,'.',',') AS `pv2i`,replace(`t`.`pv3`,'.',',') AS `pv3`,replace(`t`.`pv3i`,'.',',') AS `pv3i`,replace(`t`.`pv4`,'.',',') AS `pv4`,replace(`t`.`pv4i`,'.',',') AS `pv4i`,`t`.`tipusiva` AS `tipusiva`,date_format(`t`.`data_hora`,' %Y %m %d') AS `modificat`,`t`.`data_hora` AS `datacrua`,`p`.`a_bascula` AS `a_bascula`,`p`.`a_bascula_anna` AS `a_bascula_anna`,`t`.`bool_in_bulk` AS `bool_in_bulk` from (`productnames` `p` join `tarifes` `t` on(`t`.`ID` = `p`.`ID`)) where `t`.`ultimpreu` = 1 and `t`.`cost_price` <> '0' order by `p`.`ID` <> 0 desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
SET FOREIGN_KEY_CHECKS=1;
