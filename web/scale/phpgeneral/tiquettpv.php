<?php
/**
 * Tiquet digital del TPV del banc (Redsys).
 *
 * El TPV mostra un QR amb un enllaç al PDF del rebut, p. ex.
 *   https://apis.redsys.es/acquirement/commerces-channel/ticket-tpv/v1/pdfcph?tkn=...&ts=...
 * La bàscula el llegeix pel lector com si fos un codi entrat al camp magic.
 * Llavors baixem el PDF, en traiem l'import, la referència i la targeta, el desem a
 * {any}_Empresa_{ID}_pdf_tiquettpv i, si hi ha una factura o relation_to_assing oberta
 * amb el mateix import, la tanquem com a cobrada amb targeta (igual que total.php via=2).
 * Al tiquet de la bàscula (tiquet.php) s'imprimeix al peu la referència, la targeta i el QR.
 */

// ID de Pagament "Tarjeta TPV"
if(!defined('TPV_VIA_TARGETA')) define('TPV_VIA_TARGETA', 2);
// tipus de relació que es poden cobrar amb un rebut del TPV: relation_to_assing i factura
if(!defined('TPV_TIPUS_RELACIO')) define('TPV_TIPUS_RELACIO', '1,3');

/**
 * Nom de la taula de l'any, i la crea si no existeix (cada any en surt una de nova).
 * ID_tipus_relacio, ID_relacio i uuid poden ser NULL: rebuts que no lliguen amb cap relació.
 */
function tpv_taula($link, $ID_Empresa, $year = null) {
	if($year === null) $year = date('Y');
	$year = (int)$year;
	$ID_Empresa = (int)$ID_Empresa;
	$taula = "{$year}_Empresa_{$ID_Empresa}_pdf_tiquettpv";
	static $fetes = [];
	if(isset($fetes[$taula])) return $taula;
	$link->query("CREATE TABLE IF NOT EXISTS `$taula` (
		`ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`datetime` datetime DEFAULT NULL COMMENT 'data i hora de l''operació (del PDF)',
		`import` decimal(10,2) NOT NULL,
		`ID_tipus_relacio` int(11) DEFAULT NULL,
		`ID_relacio` int(11) DEFAULT NULL,
		`uuid` varchar(255) DEFAULT NULL COMMENT 'uuid de la relació (no canvia en passar a factura)',
		`referencia` varchar(64) NOT NULL,
		`targeta` varchar(32) NOT NULL DEFAULT '' COMMENT 'tal com surt al PDF, ************1234',
		`marca` varchar(32) NOT NULL DEFAULT '',
		`operacio` varchar(32) NOT NULL DEFAULT '',
		`resposta` varchar(8) NOT NULL DEFAULT '',
		`url` text NOT NULL,
		`pdf` mediumblob NOT NULL,
		`text` text NOT NULL,
		`id_bascula` int(11) DEFAULT NULL,
		`datetime_lectura` datetime NOT NULL DEFAULT current_timestamp(),
		PRIMARY KEY (`ID`),
		UNIQUE KEY `referencia` (`referencia`),
		KEY `relacio` (`ID_relacio`,`ID_tipus_relacio`),
		KEY `uuid` (`uuid`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	$fetes[$taula] = true;
	return $taula;
}

/**
 * Si el text llegit és el QR d'un tiquet del TPV, retorna l'URL net; si no, false.
 * Un lector configurat amb teclat US sobre un sistema amb teclat espanyol escriu
 * "httpsÑ--apis.redsys.es-..." en lloc de "https://apis.redsys.es/...": ho desfem.
 */
function tpv_qr_url($s) {
	$s = trim((string)$s);
	if($s === '' || stripos($s, 'redsys') === false) return false;
	if(stripos($s, 'httpsÑ') === 0 || strpos($s, ':') === false) {
		$s = strtr($s, ['Ñ' => ':', 'ñ' => ';', '-' => '/', '_' => '?', '¡' => '=', '/' => '&', '?' => '_', "'" => '-']);
	}
	if(stripos($s, 'http') !== 0) $s = 'https://' . ltrim($s, '/');
	$u = parse_url($s);
	if(!$u || strtolower($u['scheme'] ?? '') !== 'https') return false;
	$host = strtolower($u['host'] ?? '');
	// només baixem de Redsys
	if($host !== 'redsys.es' && substr($host, -strlen('.redsys.es')) !== '.redsys.es') return false;
	if(empty($u['query']) || strpos($u['query'], 'tkn=') === false) return false;
	return $s;
}

/** Baixa el PDF. Retorna el contingut o false. */
function tpv_baixa_pdf($url, &$error = null) {
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 3,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_TIMEOUT => 20,
		CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
	]);
	$pdf = curl_exec($ch);
	$codi = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if($pdf === false) $error = curl_error($ch);
	curl_close($ch);
	if($pdf === false) return false;
	if($codi != 200 || substr($pdf, 0, 4) !== '%PDF') {
		$error = "el banc ha respost $codi i no és un PDF";
		return false;
	}
	return $pdf;
}

/** Treu el text del PDF amb pdftotext (poppler). */
function tpv_pdf_text($pdf) {
	$f = tempnam(sys_get_temp_dir(), 'tpv');
	file_put_contents($f, $pdf);
	$text = shell_exec('pdftotext -layout ' . escapeshellarg($f) . ' - 2>/dev/null');
	@unlink($f);
	return (string)$text;
}

/**
 * Llegeix les dades del rebut (català o castellà).
 * Retorna import (amb signe: les devolucions en negatiu), referencia, targeta, marca,
 * operacio, resposta, datetime.
 */
function tpv_llegeix_text($text) {
	$d = ['import' => null, 'referencia' => '', 'targeta' => '', 'marca' => '', 'operacio' => '',
	      'resposta' => '', 'datetime' => null];
	if(preg_match('/(?:Total|Importe|Import)\s*:?\s*(-?[\d.]*\d,\d{2})\s*(?:EUR|€)/iu', $text, $m))
		$d['import'] = (float)str_replace(['.', ','], ['', '.'], $m[1]);
	if(preg_match('/Refer\S*\s*:?\s*([A-Za-z0-9]+)/u', $text, $m)) $d['referencia'] = $m[1];
	elseif(preg_match('/Id\s+operaci\S*\s*:?\s*([A-Za-z0-9]+)/iu', $text, $m)) $d['referencia'] = $m[1];
	// número de targeta emmascarat (la línia del comerç també en porta un, *****1234)
	foreach(preg_split('/\R/u', $text) as $l) {
		if(preg_match('/Comer|TPV|S\/N/iu', $l)) continue;
		if(preg_match('/(\*{3,}\s?\d{4})\b/u', $l, $m)) { $d['targeta'] = str_replace(' ', '', $m[1]); break; }
	}
	if(preg_match('/(?:Targeta|Tarjeta|Card)\s*:?\s*([A-Za-z][A-Za-z ]*?)\s*$/mu', $text, $m)) $d['marca'] = trim($m[1]);
	if(preg_match('/^\s*(VENDA|VENTA|DEVOLUCI\S*|ANUL\S*|PREAUTORITZACI\S*|PREAUTORIZACI\S*)\s*$/mu', $text, $m))
		$d['operacio'] = mb_strtoupper($m[1]);
	if(preg_match('/(?:Resposta|Respuesta)\s*:?\s*(\d+)/iu', $text, $m)) $d['resposta'] = $m[1];
	if(preg_match('/(?:Data|Fecha)\s*:?\s*(\d{2})\/(\d{2})\/(\d{4})\s+Hora\s*:?\s*(\d{2}:\d{2}(?::\d{2})?)/iu', $text, $m))
		$d['datetime'] = "{$m[3]}-{$m[2]}-{$m[1]} {$m[4]}";
	if($d['import'] !== null && preg_match('/^(DEVOLUCI|ANUL)/', $d['operacio'])) $d['import'] = -abs($d['import']);
	return $d;
}

/** Operació acceptada pel banc i que és un cobrament (es pot lligar a una venda) */
function tpv_es_cobrament($d) {
	return ($d['resposta'] === '' || $d['resposta'] === '00')
		&& ($d['operacio'] === '' || in_array($d['operacio'], ['VENDA', 'VENTA']))
		&& $d['import'] !== null && $d['import'] != 0;
}

/**
 * Factures i relation_to_assing OBERTES amb el mateix import.
 * Retorna un array de detallrelacio().
 */
function tpv_busca_relacions($import, $link, $prefixrelacio, $ID_Empresa) {
	$trobades = [];
	$q = $link->query("SELECT uuid FROM `{$prefixrelacio}index_relacio`
		WHERE relacio_oberta=1 AND ID_tipus_relacio IN (" . TPV_TIPUS_RELACIO . ")");
	while($row = $q->fetch_assoc()) {
		$r = detallrelacio($row['uuid'], $ID_Empresa, date('Y'), $link);
		if(!is_array($r) || $r['activelines'] < 1) continue;
		if(abs(round($r['Total'], 2) - round($import, 2)) < 0.005) $trobades[] = $r;
	}
	return $trobades;
}

/** Rebuts del TPV lligats a una relació (per imprimir-los al peu del tiquet) */
function tpv_rebuts_relacio($r, $year, $ID_Empresa, $link) {
	$taula = tpv_taula($link, $ID_Empresa, $year);
	$uuid = $link->real_escape_string($r['uuid']);
	$q = $link->query("SELECT ID, `datetime`, import, referencia, targeta, marca, url FROM `$taula`
		WHERE uuid='$uuid' ORDER BY ID");
	$rebuts = [];
	while($t = $q->fetch_assoc()) $rebuts[] = $t;
	return $rebuts;
}

/**
 * Tanca la relació com a cobrada amb targeta i hi lliga el rebut.
 * Fa el mateix que total.php (tanca_relacio_pagament) més el PDF per als clients amb telèfon.
 * Retorna la relació ja tancada (amb l'ID i el tipus nous) o false.
 */
function tpv_cobra_relacio($r, $ID_rebut, $link, $prefixrelacio, $ID_Empresa) {
	$r = tanca_relacio_pagament($r, TPV_VIA_TARGETA, $link, $prefixrelacio, $ID_Empresa);
	if($r === false) return false;
	$taula = tpv_taula($link, $ID_Empresa);
	$stmt = $link->prepare("UPDATE `$taula` SET ID_tipus_relacio=?, ID_relacio=?, uuid=? WHERE ID=?");
	$stmt->bind_param('iisi', $r['ID_tipus_relacio'], $r['ID'], $r['uuid'], $ID_rebut);
	$stmt->execute();
	if($r['email'] != '') $r['email'] = urldecode($r['email']);
	if($r['phone'] != '') pdf($r, $prefixrelacio);
	return $r;
}

/**
 * Desa el rebut (o retorna el que ja hi havia amb la mateixa referència).
 * Retorna la fila (array) de la taula, o false amb $error.
 */
function tpv_desa_rebut($url, $link, $ID_Empresa, $idbascula, &$error = null) {
	$pdf = tpv_baixa_pdf($url, $error);
	if($pdf === false) return false;
	$text = tpv_pdf_text($pdf);
	$d = tpv_llegeix_text($text);
	if($d['import'] === null) { $error = "no trobo l'import al PDF"; return false; }
	if($d['referencia'] === '') $d['referencia'] = 'sense-ref-' . md5($pdf);
	$taula = tpv_taula($link, $ID_Empresa);
	$ref = $link->real_escape_string($d['referencia']);
	$ja = $link->query("SELECT * FROM `$taula` WHERE referencia='$ref'")->fetch_assoc();
	if($ja) { $ja['repetit'] = true; return $ja; }
	$stmt = $link->prepare("INSERT INTO `$taula`
		(`datetime`, import, referencia, targeta, marca, operacio, resposta, url, pdf, `text`, id_bascula)
		VALUES (?,?,?,?,?,?,?,?,?,?,?)");
	$nul = null;
	$bascula = (int)$idbascula;
	$stmt->bind_param('sdssssssbsi', $d['datetime'], $d['import'], $d['referencia'], $d['targeta'],
		$d['marca'], $d['operacio'], $d['resposta'], $url, $nul, $text, $bascula);
	$stmt->send_long_data(8, $pdf);
	$stmt->execute();
	$d['ID'] = $link->insert_id;
	$d['url'] = $url;
	$d['ID_relacio'] = null;
	$d['ID_tipus_relacio'] = null;
	$d['uuid'] = null;
	$d['repetit'] = false;
	return $d;
}

/** Imprimeix al peu del tiquet els rebuts del TPV lligats a la relació */
function tpv_imprimeix_peu($printer, $r, $year, $ID_Empresa, $link) {
	global $decimalsing, $thousandsing;
	foreach(tpv_rebuts_relacio($r, $year, $ID_Empresa, $link) as $t) {
		$printer->feed(1);
		$printer->setJustification(\Mike42\Escpos\Printer::JUSTIFY_CENTER);
		$printer->selectPrintMode();
		$printer->text("Pagat amb targeta " . trim("{$t['marca']} {$t['targeta']}") . "\n");
		$printer->text("Ref.: {$t['referencia']}  " . number_format($t['import'], 2, $decimalsing, $thousandsing) . " EUR\n");
		if($t['datetime']) $printer->text(date('d/m/Y H:i:s', strtotime($t['datetime'])) . "\n");
		$printer->qrCode($t['url'], \Mike42\Escpos\Printer::QR_ECLEVEL_L, 5);
		$printer->text("Rebut del banc\n");
	}
}
?>
