-- SPDX-License-Identifier: GPL-3.0-or-later
-- simple-erp demo data: generic lookup tables plus an example shop, user and products.
-- Login: admin / admin  (change it!)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
SET SESSION sql_mode = CONCAT(@@sql_mode, ',NO_AUTO_VALUE_ON_ZERO');

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
INSERT IGNORE INTO `IVA` VALUES (0,10.00,'IVA Reduït');
INSERT IGNORE INTO `IVA` VALUES (1,4.00,'IVA Super reduït');
INSERT IGNORE INTO `IVA` VALUES (2,21.00,'IVA ');
INSERT IGNORE INTO `IVA` VALUES (3,0.00,'Extent d\'IVA (med. i f.reg.)');
INSERT IGNORE INTO `IVA` VALUES (4,7.50,'IVA Pasta i olis de grans');
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
INSERT IGNORE INTO `Pagament` VALUES (0,'No pagat',0,'ca',0);
INSERT IGNORE INTO `Pagament` VALUES (1,'Efectiu',3,'ca',0);
INSERT IGNORE INTO `Pagament` VALUES (2,'Tarjeta TPV',3,'ca',0);
INSERT IGNORE INTO `Pagament` VALUES (3,'Rebut bancari',3,'ca',1);
INSERT IGNORE INTO `Pagament` VALUES (4,'Transferència bancària',3,'ca',0);
INSERT IGNORE INTO `Pagament` VALUES (10,'Pendent <-cal autorització',3,'ca',0);
INSERT IGNORE INTO `Pagament` VALUES (11,'Pressupost',5,'ca',0);
INSERT IGNORE INTO `Pagament` VALUES (12,'Albarà',2,'ca',0);
INSERT IGNORE INTO `Pagament` VALUES (13,'Inventari',6,'ca',0);
INSERT IGNORE INTO `Pagament` VALUES (14,'Comanda ',7,'ca',0);
INSERT IGNORE INTO `Pagament` VALUES (15,'traspàs entre botigues',8,'ca',0);
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
INSERT IGNORE INTO `tipus_relacio` VALUES (0,'TRIA Tipus','ca',0);
INSERT IGNORE INTO `tipus_relacio` VALUES (1,'Relation_to_assing','ca',1);
INSERT IGNORE INTO `tipus_relacio` VALUES (2,'Albarà','ca',0);
INSERT IGNORE INTO `tipus_relacio` VALUES (3,'Factura','ca',1);
INSERT IGNORE INTO `tipus_relacio` VALUES (4,'Proforma','ca',1);
INSERT IGNORE INTO `tipus_relacio` VALUES (5,'Pressupost','ca',0);
INSERT IGNORE INTO `tipus_relacio` VALUES (6,'Inventari','ca',0);
INSERT IGNORE INTO `tipus_relacio` VALUES (7,'Comanda','ca',0);
INSERT IGNORE INTO `tipus_relacio` VALUES (8,'Traspàs','ca',0);
INSERT IGNORE INTO `tipus_relacio` VALUES (9,'Bascula heredada','ca',1);
INSERT IGNORE INTO `tipus_relacio` VALUES (10,'Tiquet','ca',1);
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
INSERT IGNORE INTO `equivalency_charge` VALUES (0,1.40);
INSERT IGNORE INTO `equivalency_charge` VALUES (1,0.50);
INSERT IGNORE INTO `equivalency_charge` VALUES (2,5.20);
INSERT IGNORE INTO `equivalency_charge` VALUES (3,0.00);
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
INSERT IGNORE INTO `package_type` VALUES (0,'Format unitari ','ca',1,5000,5010,300,400,250,'','2023-04-29 20:14:10');
INSERT IGNORE INTO `package_type` VALUES (1,'Format Majorista','ca',0,5000,5010,300,400,250,'','2023-04-29 19:32:49');
INSERT IGNORE INTO `package_type` VALUES (2,'Format Bosseta unitària','ca',0,50,50,100,50,20,'','2023-04-29 19:51:33');
INSERT IGNORE INTO `package_type` VALUES (10,'Format majorista reembasat  a casa','ca',0,22680,23000,300,600,400,'','2023-06-12 10:14:21');
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

INSERT IGNORE INTO Empresa (ID, Value, Fiscal_Name, Direccio, Poblacio, Phone, email, NIF, termal_head_1, termal_head_2, termal_head_3, termal_tail_1, bool_ticket_printer, bool_print_pdf, cashkeeper_1cent)
VALUES (0, 'TRIA EMPRESA', '', '', '', '', '', '', '', '', '', '', 0, 0, 1),
       (1, 'Example Shop', 'Example Shop S.L.', 'Example Street 1', '00000 Example Town', '000 000 000', 'shop@example.org', 'B00000000',
        'EXAMPLE SHOP', 'Example Shop S.L.', 'Example Street 1', 'Thank you!', 0, 0, 1);

INSERT IGNORE INTO users (username, password, ID_Empresa, url_key, id_bascula, bascula_vertical, pot_veure_les_altres_empreses)
VALUES ('admin', '$2y$12$cMGIwrGf8m4aNpXmVZcKBOt1hgQeGQmtnOdEKS.Oyce3E3eyrDY8O', 1, '', 1, 0, 1);

INSERT IGNORE INTO `1_Venedor` (ID, Value) VALUES (1, 'Anna'), (2, 'Pau');
INSERT IGNORE INTO families (ID, value) VALUES (1, 'Nuts');
INSERT IGNORE INTO dealers (ID, Value, hidden_user) VALUES (0, 'No dealer', 'admin');
INSERT IGNORE INTO botigues (ID, Value, Direccio) VALUES (1, 'Example Shop', 'Example Street 1');
INSERT IGNORE INTO Clients (ID, Value, fiscal_address, Poblacio, NIF, bool_Active) VALUES (1, 'Generic customer', '', '', '', 1);

INSERT IGNORE INTO tarifes (ID, data_hora, user, id_dealer, id_familia, bool_in_bulk, cost_price, pvp, pvpi, pv1, pv1i, pv2, pv2i, pv3, pv3i, pv4, pv4i, historic, ultimpreu, tipusiva)
VALUES (1, '2020-01-01 00:00:00', 'admin', 0, 1, 1, 10.00, 18.00, 1.80, 15.00, 1.50, 13.00, 1.30, 12.00, 1.20, 11.00, 1.10, 1, 1, 0),
       (2, '2020-01-01 00:00:00', 'admin', 0, 1, 1, 12.00, 22.00, 1.83, 18.00, 1.50, 16.00, 1.33, 14.50, 1.21, 13.50, 1.13, 1, 1, 0),
       (3, '2020-01-01 00:00:00', 'admin', 0, 1, 0,  0.80,  1.50, 1.88,  1.30, 1.63,  1.20, 1.50,  1.10, 1.38,  1.00, 1.25, 1, 1, 2);
INSERT IGNORE INTO productnames (ID, lang, value) VALUES (1, 'ca', 'Ametlla torrada'), (2, 'ca', 'Avellana torrada'), (3, 'ca', 'Bossa de paper');
INSERT IGNORE INTO productnamesurlencoded (ID, lang, value) VALUES (1, 'ca', 'Ametlla+torrada'), (2, 'ca', 'Avellana+torrada'), (3, 'ca', 'Bossa+de+paper');

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
INSERT IGNORE INTO `lang` VALUES
(1,'ca','Català'),
(2,'es','Castellano'),
(3,'en','English'),
(4,'fr','Français'),
(5,'eo','Esperanto');
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
-- last weight and price of each scale (one row per scale, users.id_bascula)
INSERT IGNORE INTO ultimpes (ID, pes, preu) VALUES (1, 0, 0), (2, 0, 0), (3, 0, 0);
SET FOREIGN_KEY_CHECKS=1;
