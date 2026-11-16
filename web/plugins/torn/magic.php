<?php
// Codis del camp de codi de scale: ++ passa al torn següent, +- torna un torn enrere.
return function($link, $codi, $cfg, $scale_uri) {
	if($codi !== '++' && $codi !== '+-') return false;
	require_once __DIR__ . '/torn.php';
	$emp = (int)($_SESSION['ID_Empresa'] ?? 0);
	if($emp > 0) torn_mou($link, $emp, $codi === '++' ? 1 : -1);
	$link->close();
	header("Location: $scale_uri");
	return true;
};
