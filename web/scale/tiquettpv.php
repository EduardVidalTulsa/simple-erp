<?php
/**
 * Cobrament amb el QR del tiquet digital del TPV del banc.
 *   ?qr=<text llegit>[&uuid=<relació del subtotal>]  baixa i desa el PDF i cobra la venda amb el mateix import
 *   ?rebut=<ID>&uuid=<uuid>                         quan hi ha més d'una venda amb el mateix import, la triada
 * Només es cobren factures i relation_to_assing obertes (com total.php via=2).
 * Si no lliga amb cap venda, el rebut queda desat igualment sense relació.
 */
require("login.php");
if($login==false){$link->close();header("Location: {$scale_uri}");die;}

function tpv_pantalla($titol, $text, $color = '#ACAB00', $botons = [], $segons = 10) {
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
	// el lector de QR escriu com un teclat: un altre QR llegit aquí també es processa
	echo "<form method=\"GET\" action=\"tiquettpv.php\" style=\"position:absolute;left:-1000px;\"><input name=\"qr\" id=\"qr\" autofocus autocomplete=\"off\"/></form>";
	echo "<script>setInterval(function(){document.getElementById('qr').focus();},300);</script>";
	echo "</div></body></html>";
}

function tpv_descripcio($r) {
	global $decimalsing, $thousandsing;
	$nom = $r['phone'] != '' ? urldecode($r['fiscal_name']) . " {$r['phone']}" : $r['Nom_Venedor'];
	return htmlspecialchars("{$r['tipus_relacio']['Value']} {$r['Empresa']['ID']}.{$r['ID']} · $nom · {$r['activelines']} línies · "
		. number_format($r['Total'], 2, $decimalsing, $thousandsing) . " €");
}

// cobra la relació oberta, o si ja s'havia cobrat amb el botó de targeta, només hi lliga el rebut
function tpv_aplica($r, $rebut) {
	global $link, $prefixrelacio, $ID_Empresa, $tanca_relacio_error;
	if($r['relacio_oberta'] != 1) {
		tpv_lliga_rebut($r, $rebut['ID'], $link, $ID_Empresa);
		tpv_cobrat($r, $rebut, "Rebut lligat (ja estava cobrada amb targeta)");
		return;
	}
	$r2 = tpv_cobra_relacio($r, $rebut['ID'], $link, $prefixrelacio, $ID_Empresa);
	if($r2 === false) tpv_pantalla("Error", htmlspecialchars($tanca_relacio_error), '#e06060', [], 0);
	else tpv_cobrat($r2, $rebut);
}

function tpv_cobrat($r, $rebut, $titol = null) {
	global $scale_uri, $decimalsing, $thousandsing;
	$text = tpv_descripcio($r) . "<br/>Targeta " . htmlspecialchars(trim("{$rebut['marca']} {$rebut['targeta']}"))
		. " · Ref. " . htmlspecialchars($rebut['referencia']);
	tpv_pantalla($titol ?? "Cobrat amb targeta " . number_format($rebut['import'], 2, $decimalsing, $thousandsing) . " €", $text, '#7fc97f',
		[["{$scale_uri}tiquet.php?uuid={$r['uuid']}", "&#128424; Imprimir tiquet"]], 15);
}

$taula = tpv_taula($link, $ID_Empresa);
$canal = $_SESSION['Empresa']['telegram_relacions'] ?? false;

// 1) Venda triada d'entre les que tenien el mateix import
if(isset($_REQUEST['rebut'])) {
	$rebut = $link->query("SELECT * FROM `$taula` WHERE ID='" . (int)$_REQUEST['rebut'] . "'")->fetch_assoc();
	$r = detallrelacio($link->real_escape_string($_REQUEST['uuid'] ?? ''), $ID_Empresa, date('Y'), $link);
	if(!$rebut || $rebut['uuid'] != '') {
		tpv_pantalla("Rebut ja cobrat", "Aquest rebut ja està lligat a una venda.", '#f0a050');
	}
	elseif(!tpv_es_candidata($r, $rebut['import'], $link, $ID_Empresa)) {
		tpv_pantalla("No es pot cobrar", "Aquesta venda ja no està oberta o l'import no quadra.", '#f0a050');
	}
	else tpv_aplica($r, $rebut);
	$link->close();
	die;
}

// 2) QR llegit
$url = tpv_qr_url($_REQUEST['qr'] ?? '');
if($url === false) {
	tpv_pantalla("QR no reconegut", "No és el QR d'un tiquet del TPV:<br/><small>" . htmlspecialchars($_REQUEST['qr'] ?? '') . "</small>", '#e06060');
	$link->close();
	die;
}
$rebut = tpv_desa_rebut($url, $link, $ID_Empresa, $idbascula, $error);
if($rebut === false) {
	debugtelegram("No he pogut llegir el tiquet del TPV ($error): $url", $_SERVER['HTTP_HOST'], $canal);
	tpv_pantalla("No puc llegir el tiquet", htmlspecialchars($error) . "<br/>Torna a llegir el QR o cobra amb el botó de targeta.", '#e06060', [], 0);
	$link->close();
	die;
}
$imp = number_format($rebut['import'], 2, $decimalsing, $thousandsing) . " €";

if(!empty($rebut['uuid'])) {
	$r = detallrelacio($rebut['uuid'], $ID_Empresa, date('Y'), $link);
	tpv_pantalla("Aquest tiquet ja està cobrat", (is_array($r) ? tpv_descripcio($r) : '') . "<br/>Ref. " . htmlspecialchars($rebut['referencia']), '#f0a050',
		is_array($r) ? [["{$scale_uri}tiquet.php?uuid={$r['uuid']}", "&#128424; Imprimir tiquet"]] : []);
	$link->close();
	die;
}

if(!tpv_es_cobrament($rebut)) {
	$motiu = $rebut['resposta'] !== '' && $rebut['resposta'] !== '00' ? "DENEGADA (resposta {$rebut['resposta']})" : ($rebut['operacio'] ?: 'operació desconeguda');
	debugtelegram("Tiquet TPV $motiu $imp ref {$rebut['referencia']}: desat sense venda", $_SERVER['HTTP_HOST'], $canal);
	tpv_pantalla("No és un cobrament", htmlspecialchars("$motiu · $imp · Ref. {$rebut['referencia']}") . "<br/>L'he desat sense lligar-lo a cap venda.", '#e06060', [], 0);
	$link->close();
	die;
}

// obertes amb el mateix import, i les que ja s'han cobrat amb el botó de targeta abans de llegir el QR
$trobades = array_merge(tpv_busca_relacions($rebut['import'], $link, $prefixrelacio, $ID_Empresa),
	tpv_busca_cobrades($rebut['import'], $link, $prefixrelacio, $ID_Empresa));
$triada = null;
foreach($trobades as $r) if(($_REQUEST['uuid'] ?? '') !== '' && $r['uuid'] === $_REQUEST['uuid']) $triada = $r;
if(!$triada && count($trobades) == 1) $triada = $trobades[0];

if($triada) tpv_aplica($triada, $rebut);
elseif(count($trobades) == 0) {
	debugtelegram("Tiquet TPV $imp ref {$rebut['referencia']} {$rebut['targeta']}: cap venda oberta amb aquest import, desat sense venda", $_SERVER['HTTP_HOST'], $canal);
	tpv_pantalla("Cap venda de $imp", "No hi ha cap venda oberta de $imp.<br/>He desat el rebut (Ref. " . htmlspecialchars($rebut['referencia'])
		. ") sense venda. Quan hagis entrat la venda, torna a llegir el QR i s'hi lligarà.", '#f0a050', [], 0);
}
else {
	$botons = [];
	foreach($trobades as $r) $botons[] = ["{$scale_uri}tiquettpv.php?rebut={$rebut['ID']}&uuid={$r['uuid']}",
		tpv_descripcio($r) . (!empty($r['ja_cobrada']) ? " (ja cobrada &#128179;)" : '')];
	tpv_pantalla("Hi ha " . count($trobades) . " vendes de $imp", "Quina has cobrat?", '#ACAB00', $botons, 0);
}
$link->close();
?>
