<?php
/**
 * A qui s'envia el tiquet per correu (p.php) i l'enllaç per deixar de rebre'l (altaclient/baixa.php, a la central).
 * Només correus verificats (RGPD): el principal del client si Clients.email_verificat=1, més els de Clients_email
 * amb verificat=1 (còpies). Sense client (ID 0) o sense cap correu verificat no s'envia res.
 * Clients.email es desa amb urlencode(); Clients_email.Value, no.
 */

/** @return string[] correus verificats del client, el principal primer, sense repetits */
function tiquet_destinataris($link, $ID_client) {
	$id = (int)$ID_client;
	if($id <= 0) return [];
	$tots = [];
	try {
		$c = $link->query("SELECT email, email_verificat FROM Clients WHERE ID=$id")->fetch_assoc();
		if($c && (int)$c['email_verificat'] === 1) $tots[] = urldecode((string)$c['email']);
		$r = $link->query("SELECT Value FROM Clients_email WHERE ID_Client=$id AND verificat=1 ORDER BY prefered DESC, ID");
		foreach($r->fetch_all() as $x) $tots[] = (string)$x[0];
	} catch(Throwable $e) {
		error_log("tiquet_destinataris($id): " . $e->getMessage());
	}
	$u = [];
	foreach($tots as $e) {
		$e = trim($e);
		if(filter_var($e, FILTER_VALIDATE_EMAIL)) $u[strtolower($e)] = $e;
	}
	return array_values($u);
}

/** Signatura de l'enllaç de baixa: $pdf_token_secret és el mateix a la central i a totes les seus */
function tiquet_signa_baixa($ID_client, $email) {
	global $pdf_token_secret;
	return substr(hash_hmac('sha256', 'baixa|' . (int)$ID_client . '|' . strtolower(trim((string)$email)), (string)$pdf_token_secret), 0, 32);
}

/** Enllaç a la pàgina de baixa de la central (Empresa 1 = central, url_seu; si no, $altaclient_url de keys.php) */
function tiquet_enllac_baixa($link, $ID_client, $email) {
	static $base = null;
	if($base === null) {
		try { $base = rtrim((string)$link->query("SELECT url_seu FROM Empresa WHERE ID=1")->fetch_row()[0], '/'); } catch(Throwable $e) { $base = ''; }
		if(!preg_match('#^https://#', $base)) { global $altaclient_url; $base = rtrim((string)($altaclient_url ?? ''), '/'); $base = preg_replace('#/altaclient$#', '', $base); }
	}
	if($base === '') return '';   // sense adreça pública no hi ha enllaç de baixa
	return $base . '/altaclient/baixa.php?' . http_build_query(['c' => (int)$ID_client, 'e' => $email, 's' => tiquet_signa_baixa($ID_client, $email)]);
}
