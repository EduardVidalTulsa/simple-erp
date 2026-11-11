<?php
/**
 * Xecs regal / descompte: codi d'un sol ús, en una taula pròpia per empresa `Empresa_{ID}_voucher`
 * (sense prefix d'any, perquè un xec pot caducar més enllà de l'any en curs). Es creen des de
 * /makevoucher (impresos a la impressora de tiquets amb un QR) i es redimeixen des de scale/val.php,
 * llegit al subtotal igual que el QR del TPV del banc.
 * Mai es descompta més del que falti pagar de la venda (import_aplicat = min(import, pendent));
 * si el xec no cobreix tota la venda, la relació queda oberta per pagar la resta com sempre.
 * La taula de cada empresa és només d'aquesta empresa: no cal cap columna ID_Empresa ni comprovar-la,
 * ja ve garantit per quina taula es consulta.
 */

if(!defined('VAL_VIA_PAGAMENT')) define('VAL_VIA_PAGAMENT', 5); // Pagament.ID "Xec regal"
if(!defined('VAL_PREFIX')) define('VAL_PREFIX', 'XEC-');
if(!defined('VAL_PREFIX_LEGACY')) define('VAL_PREFIX_LEGACY', 'VAL-'); // prefix antic, abans de dir-li "xec" en lloc de "val"

/** Detecta si un text llegit (del lector de QR o teclejat) pot ser un codi de xec regal, amb el prefix actual o l'antic */
function val_es_possible_codi($s) {
	$s = trim((string)$s);
	return stripos($s, VAL_PREFIX) === 0 || stripos($s, VAL_PREFIX_LEGACY) === 0;
}

/** Nom de la taula de vals d'una empresa */
function val_taula_nom($ID_Empresa) {
	return "Empresa_" . (int)$ID_Empresa . "_voucher";
}

/** Crea la taula d'aquesta empresa si no existeix, i la via de pagament/permís globals. */
function val_taula($link, $ID_Empresa) {
	static $fetes = [];
	$taula = val_taula_nom($ID_Empresa);
	if(isset($fetes[$taula])) return $taula;
	$link->query("CREATE TABLE IF NOT EXISTS `$taula` (
		`ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`codi` varchar(40) NOT NULL,
		`import` decimal(10,2) NOT NULL,
		`estat` enum('emes','redimit','anulat') NOT NULL DEFAULT 'emes',
		`data_emissio` datetime NOT NULL DEFAULT current_timestamp(),
		`data_caducitat` date DEFAULT NULL,
		`ID_relacio_emissio` int(11) DEFAULT NULL,
		`ID_tipus_relacio_emissio` int(11) DEFAULT NULL,
		`uuid_emissio` varchar(255) DEFAULT NULL,
		`creat_per` varchar(255) DEFAULT NULL,
		`import_aplicat` decimal(10,2) DEFAULT NULL,
		`data_redempcio` datetime DEFAULT NULL,
		`uuid_redempcio` varchar(255) DEFAULT NULL,
		`ID_relacio_redempcio` int(11) DEFAULT NULL,
		`ID_tipus_relacio_redempcio` int(11) DEFAULT NULL,
		PRIMARY KEY (`ID`),
		UNIQUE KEY `codi` (`codi`),
		KEY `uuid_redempcio` (`uuid_redempcio`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	if(!isset($fetes['__global__'])) {
		$link->query("INSERT IGNORE INTO `Pagament` (`ID`,`Value`,`ID_tipus_relacio`,`lang`,`bool_allow_payment`)
			VALUES (" . VAL_VIA_PAGAMENT . ",'Xec regal',3,'ca',0)");
		// permís per crear xecs a /makevoucher: només els usuaris amb aquesta columna a 1
		if(!mysql1r("SHOW COLUMNS FROM `users` LIKE 'pot_fer_vals'", $link)) {
			$link->query("ALTER TABLE `users` ADD `pot_fer_vals` tinyint(4) unsigned NOT NULL DEFAULT 0
				COMMENT 'pot crear xecs regal/descompte a /makevoucher'");
			$link->query("UPDATE `users` SET `pot_fer_vals`=1 WHERE `username`='root'");
		}
		$fetes['__global__'] = true;
	}
	$fetes[$taula] = true;
	return $taula;
}

/** Codi d'un sol ús imprevisible (128 bits d'atzar): XEC-<32 hex en majúscules> */
function val_genera_codi() {
	return VAL_PREFIX . strtoupper(bin2hex(random_bytes(16)));
}

/** Normalitza un codi llegit pel lector de QR o teclejat a mà */
function val_normalitza_codi($s) {
	return strtoupper(trim((string)$s));
}

/** Crea un xec nou per a aquesta empresa. Retorna el codi generat. */
function val_crea($import, $ID_Empresa, $link, $data_caducitat = null, $creat_per = null,
	$uuid_emissio = null, $ID_relacio_emissio = null, $ID_tipus_relacio_emissio = null) {
	$taula = val_taula($link, $ID_Empresa);
	$codi = val_genera_codi();
	$stmt = $link->prepare("INSERT INTO `$taula`
		(codi, import, data_caducitat, creat_per, uuid_emissio, ID_relacio_emissio, ID_tipus_relacio_emissio)
		VALUES (?,?,?,?,?,?,?)");
	$stmt->bind_param('sdsssii', $codi, $import, $data_caducitat, $creat_per,
		$uuid_emissio, $ID_relacio_emissio, $ID_tipus_relacio_emissio);
	$stmt->execute();
	return $codi;
}

/** Busca un xec pel seu codi, dins la taula d'aquesta empresa (sigui quin sigui l'estat) */
function val_cerca($codi, $link, $ID_Empresa) {
	$taula = val_taula($link, $ID_Empresa);
	$codi = $link->real_escape_string(val_normalitza_codi($codi));
	return $link->query("SELECT * FROM `$taula` WHERE codi='$codi'")->fetch_assoc();
}

/** El xec es pot gastar ara? (ja ve garantit que és d'aquesta empresa, per la taula on s'ha buscat) */
function val_es_valid($val) {
	if(!is_array($val) || !isset($val['ID'])) return false;
	if($val['estat'] != 'emes') return false;
	if($val['data_caducitat'] && $val['data_caducitat'] < date('Y-m-d')) return false;
	return true;
}

/** Suma dels xecs ja redimits en aquesta relació (per si en gasten més d'un a la mateixa venda) */
function val_import_redimit_relacio($uuid, $link, $ID_Empresa) {
	if(!$uuid) return 0.0;
	$taula = val_taula($link, $ID_Empresa);
	$uuid = $link->real_escape_string($uuid);
	return (float)mysql1r("SELECT COALESCE(SUM(import_aplicat),0) FROM `$taula`
		WHERE uuid_redempcio='$uuid' AND estat='redimit'", $link);
}

/**
 * Redimeix el xec de forma atòmica: només l'actualitza si encara estava 'emes', per evitar
 * que es gasti dues vegades si es llegeix el mateix QR dues vegades a la vegada (dues caixes).
 * Retorna true si l'ha pogut gastar, false si algú altre ja l'havia gastat just abans.
 */
function val_redimeix($codi, $import_aplicat, $r, $link, $ID_Empresa) {
	$taula = val_taula($link, $ID_Empresa);
	$codi = val_normalitza_codi($codi);
	$stmt = $link->prepare("UPDATE `$taula` SET estat='redimit', import_aplicat=?, data_redempcio=NOW(),
		uuid_redempcio=?, ID_relacio_redempcio=?, ID_tipus_relacio_redempcio=?
		WHERE codi=? AND estat='emes'");
	$stmt->bind_param('dsiis', $import_aplicat, $r['uuid'], $r['ID'], $r['ID_tipus_relacio'], $codi);
	$stmt->execute();
	return $stmt->affected_rows === 1;
}

/** Anul·la un xec que no s'ha arribat a imprimir/lliurar (no es podrà gastar) */
function val_anula($codi, $link, $ID_Empresa) {
	$taula = val_taula($link, $ID_Empresa);
	$codi = val_normalitza_codi($codi);
	$stmt = $link->prepare("UPDATE `$taula` SET estat='anulat' WHERE codi=? AND estat='emes'");
	$stmt->bind_param('s', $codi);
	$stmt->execute();
	return $stmt->affected_rows === 1;
}

/** Xecs gastats en aquesta relació (per imprimir-los al peu del tiquet) */
function val_redimits_relacio($uuid, $link, $ID_Empresa) {
	$taula = val_taula($link, $ID_Empresa);
	$uuid = $link->real_escape_string($uuid);
	$q = $link->query("SELECT codi, import_aplicat FROM `$taula`
		WHERE uuid_redempcio='$uuid' AND estat='redimit' ORDER BY ID");
	$vals = [];
	while($v = $q->fetch_assoc()) $vals[] = $v;
	return $vals;
}

/** Imprimeix al peu del tiquet els xecs regal gastats en aquesta venda */
function val_imprimeix_peu($printer, $r, $link, $ID_Empresa) {
	global $decimalsing, $thousandsing, $euro, $taula;
	foreach(val_redimits_relacio($r['uuid'], $link, $ID_Empresa) as $v) {
		$printer->feed(1);
		$printer->setJustification(\Mike42\Escpos\Printer::JUSTIFY_CENTER);
		$printer->selectPrintMode();
		$printer->text("Pagat amb xec regal\n");
		$printer->text(substr($v['codi'], -8) . "  -"
			. number_format($v['import_aplicat'], 2, $decimalsing, $thousandsing) . " ");
		$printer->selectCharacterTable($taula);
		$printer->textRaw(chr($euro));
		$printer->selectCharacterTable();
		$printer->text("\n");
	}
}

/** Totes les empreses que ja tenen taula de vals creada (per llistar-los a totes des de /makevoucher) */
function val_empreses_amb_taula($link) {
	$empreses = [];
	$q = $link->query("SELECT ID, Value FROM `Empresa` ORDER BY Value");
	while($e = $q->fetch_assoc()) {
		if(mysql1r("SHOW TABLES LIKE '" . val_taula_nom($e['ID']) . "'", $link)) $empreses[] = $e;
	}
	return $empreses;
}

/** Xecs d'una empresa (o de totes les que ja en tenen, si $totes_empreses), els més nous primer */
function val_llista($link, $ID_Empresa, $totes_empreses = false, $limit = 200) {
	$vals = [];
	if($totes_empreses) {
		foreach(val_empreses_amb_taula($link) as $e) {
			$taula = val_taula_nom($e['ID']);
			$q = $link->query("SELECT * FROM `$taula` ORDER BY ID DESC LIMIT " . (int)$limit);
			while($v = $q->fetch_assoc()) {
				$v['ID_Empresa'] = (int)$e['ID'];
				$v['botiga'] = $e['ID'] . " -> " . $e['Value'];
				$vals[] = $v;
			}
		}
		usort($vals, fn($a, $b) => strtotime($b['data_emissio']) <=> strtotime($a['data_emissio']));
		$vals = array_slice($vals, 0, $limit);
	}
	else {
		$taula = val_taula($link, $ID_Empresa);
		$q = $link->query("SELECT * FROM `$taula` ORDER BY ID DESC LIMIT " . (int)$limit);
		while($v = $q->fetch_assoc()) { $v['ID_Empresa'] = (int)$ID_Empresa; $vals[] = $v; }
	}
	return $vals;
}
