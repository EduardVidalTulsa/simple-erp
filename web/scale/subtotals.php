<?php
/************
*
*	Resum de venedors (subtotals) de la pantalla de pesar, per refrescar-lo via AJAX.
*	Així, si una dependenta afegeix una línia en una altra bàscula, aquí es veu sense prémer res.
*	El client envia el hash del que té; només es torna l'html si ha canviat.
*
************/
require("login.php");
header('Content-Type: application/json');
if(!$login){
	echo json_encode(array());
	exit;
}
// no cal la sessió per a res més: alliberem el bloqueig perquè tlinea.php no s'esperi
session_write_close();

list($html, $mida) = peu_venedors_html($w, $h, $prefixrelacio, $link, $ID_Empresa, $scale_uri, $decimalsing, $thousandsing);
$hash = md5($html.$mida);
if(($_POST['hash'] ?? '') === $hash) echo json_encode(array('hash' => $hash));
else echo json_encode(array('hash' => $hash, 'html' => $html, 'mida' => $mida));
?>
