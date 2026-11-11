<?php
/**
 * Redempció d'un xec regal/descompte al subtotal: ?codi=<llegit del QR o teclejat>&uuid=<venda oberta>
 * Es llegeix igual que el QR del TPV: el lector escriu al camp "magic" del subtotal i
 * case_tracto_subtotal() (phpgeneral/lib.php) redirigeix aquí quan detecta el prefix XEC-.
 * Aplica com a molt el que quedi pendent de pagar; si el xec no cobreix tota la venda,
 * la relació queda oberta per pagar la resta amb efectiu/targeta com sempre.
 */
require("login.php");
if($login == false) { $link->close(); header("Location: {$scale_uri}"); die; }

function val_pantalla($titol, $text, $color = '#ACAB00', $botons = [], $segons = 8) {
	global $scale_uri;
	estil_capcal(false);
	echo "<div style=\"background:$color;position:absolute;top:5px;left:5px;right:5px;bottom:5px;padding:20px;font-size:40px;overflow:auto;\">";
	echo "<div style=\"font-size:60px;font-weight:bold;\">" . htmlspecialchars($titol) . "</div>";
	echo "<div style=\"margin:20px 0;\">$text</div>";
	foreach($botons as $b) {
		echo "<a href=\"" . htmlspecialchars($b[0]) . "\" style=\"display:inline-block;margin:10px 20px 10px 0;padding:20px 30px;background:#fff;color:#000;border-radius:10px;font-size:45px;text-decoration:none;\">{$b[1]}</a>";
	}
	echo "<a href=\"" . htmlspecialchars($scale_uri) . "\" style=\"display:inline-block;margin:10px 0;padding:20px 30px;background:#333;color:#fff;border-radius:10px;font-size:45px;text-decoration:none;\">Tornar</a>";
	if($segons > 0) echo "<script>setTimeout(function(){location.replace(" . json_encode($scale_uri) . ")}, " . ($segons * 1000) . ");</script>";
	echo "</div></body></html>";
}

$codi = trim($_REQUEST['codi'] ?? '');
$uuid = $_REQUEST['uuid'] ?? '';
$r = detallrelacio($uuid, $ID_Empresa, date('Y'), $link);

if(!is_array($r) || !isset($r['ID']) || $r['activelines'] < 1 || $r['relacio_oberta'] != 1) {
	val_pantalla("No hi ha venda oberta", "Torna a la bàscula i obre la venda abans d'escanejar el xec.", '#f0a050');
	$link->close(); die;
}

$val = val_cerca($codi, $link, $ID_Empresa);
if(!val_es_valid($val)) {
	$motiu = !$val ? "Aquest codi no és un xec d'aquesta botiga."
		: ($val['estat'] != 'emes' ? "Aquest xec ja s'ha gastat o s'ha anul·lat." : "Aquest xec ha caducat.");
	val_pantalla("Xec no vàlid", htmlspecialchars($motiu), '#e06060');
	$link->close(); die;
}

$ja_redimit = val_import_redimit_relacio($r['uuid'], $link, $ID_Empresa);
$pendent = round($r['Total'] - $ja_redimit, 2);
if($pendent <= 0) {
	val_pantalla("Ja està tot pagat", "Aquesta venda ja estava coberta amb xecs regal.", '#f0a050');
	$link->close(); die;
}

$import_aplicat = min((float)$val['import'], $pendent);
if(!val_redimeix($val['codi'], $import_aplicat, $r, $link, $ID_Empresa)) {
	val_pantalla("Xec ja gastat", "Algú l'ha fet servir just ara mateix.", '#f0a050');
	$link->close(); die;
}

$queda = round($pendent - $import_aplicat, 2);
$text = "Xec de " . number_format($val['import'], 2, $decimalsing, $thousandsing) . "€ · aplicat "
	. number_format($import_aplicat, 2, $decimalsing, $thousandsing) . "€";

if($queda <= 0) {
	// el xec cobreix tota la venda: la tanquem com a pagada
	$r2 = tanca_relacio_pagament($r, VAL_VIA_PAGAMENT, $link, $prefixrelacio, $ID_Empresa);
	if($r2 === false) {
		val_pantalla("Error", htmlspecialchars($tanca_relacio_error), '#e06060', [], 0);
		$link->close(); die;
	}
	if($r2['email'] != '') $r2['email'] = urldecode($r2['email']);
	if($r2['phone'] != '') pdf($r2, $prefixrelacio);
	val_pantalla("Cobrat amb xec regal", $text, '#7fc97f',
		[["{$scale_uri}tiquet.php?uuid={$r2['uuid']}", "&#128424; Imprimir tiquet"]], 15);
}
else {
	$text .= "<br/>Queda per pagar: <b>" . number_format($queda, 2, $decimalsing, $thousandsing) . "€</b> amb efectiu/targeta.";
	val_pantalla("Xec aplicat", $text, '#7fc97f',
		[["{$scale_uri}?m[estat]=tracto_subtotal&search=/{$r['ID_Venedor']}&uuid={$r['uuid']}", "Continuar cobrant"]], 0);
}
$link->close();
?>
