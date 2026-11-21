<?php
/*
 * Autenticació comuna (scale, preus i els mòduls que fan servir scale/login.php).
 *
 * - Contrasenyes: password_hash() o, les antigues, text pla (contrasenya_ok).
 * - Xarxes de confiança: local, VPN (WireGuard) i Tailscale. Des de fora, si keys.php té
 *   $volclaullarga = true, cal també la clau temporal (adminer.key ID 1, es renova cada nit),
 *   o la d'una altra seu copiada a $clau_temporal_fitxer.
 *   Les xarxes es poden canviar a keys.php amb $xarxes_confianca (llista de CIDR).
 * - Límit d'intents: 5 errors des d'una mateixa adreça i 15 minuts de bloqueig.
 */
if (!function_exists('contrasenya_ok')) {
	function contrasenya_ok($desada, $donada) {
		if ($desada === null || $desada === '' || $donada === '') return false;
		if (preg_match('/^\$(2y|2a|argon2i|argon2id)\$/', $desada)) return password_verify($donada, $desada);
		return hash_equals((string)$desada, (string)$donada);
	}
}

// l'adreça $ip és dins la xarxa $cidr (IPv4 o IPv6)?
function ip_dins_xarxa($ip, $cidr) {
	$p = explode('/', $cidr);
	$a = @inet_pton($ip); $b = @inet_pton($p[0]);
	if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;
	$bits = isset($p[1]) ? (int)$p[1] : strlen($a) * 8;
	$bytes = intdiv($bits, 8); $resta = $bits % 8;
	if (substr($a, 0, $bytes) !== substr($b, 0, $bytes)) return false;
	if ($resta) { $m = (0xff << (8 - $resta)) & 0xff; if ((ord($a[$bytes]) & $m) !== (ord($b[$bytes]) & $m)) return false; }
	return true;
}

function ip_de_confianca($ip = null) {
	global $xarxes_confianca;
	$ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '');
	$xarxes = $xarxes_confianca ?? ['127.0.0.0/8', '::1/128', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16',
		'100.64.0.0/10', 'fd7a:115c:a1e0::/48'];   // els dos últims: Tailscale
	foreach ($xarxes as $x) if (ip_dins_xarxa($ip, $x)) return true;
	return false;
}

// cal la clau temporal per entrar des d'aquesta adreça?
function cal_clau_temporal() {
	return !empty($GLOBALS['volclaullarga']) && !ip_de_confianca();
}

function clau_temporal_ok($donada) {
	global $myserver, $myuser, $mypassword, $clau_temporal_fitxer;
	if ((string)$donada === '') return false;
	// clau d'una altra seu, copiada a un fitxer (keys.php: $clau_temporal_fitxer)
	if (!empty($clau_temporal_fitxer)) {
		$k = is_readable($clau_temporal_fitxer) ? trim((string)file_get_contents($clau_temporal_fitxer)) : '';
		return $k !== '' && hash_equals($k, (string)$donada);
	}
	try {
		$my = mysqli_connect($myserver, $myuser, $mypassword, 'adminer');
		$fila = $my->query("SELECT `value` FROM `key` WHERE `ID`='1'")->fetch_row();
		$my->close();
	} catch (Throwable $e) { return false; }
	return !empty($fila[0]) && hash_equals((string)$fila[0], (string)$donada);
}

function login_fitxer_intents() {
	return sys_get_temp_dir() . '/erp-login-' . md5($_SERVER['REMOTE_ADDR'] ?? '') . '.txt';
}
function login_bloquejat() {
	$f = login_fitxer_intents();
	if (!is_file($f)) return false;
	$d = explode(' ', (string)@file_get_contents($f)) + [0, 0];
	if (time() - (int)$d[1] > 900) { @unlink($f); return false; }
	return (int)$d[0] >= 5;
}
function login_fallada() {
	$f = login_fitxer_intents();
	$d = is_file($f) ? explode(' ', (string)@file_get_contents($f)) + [0, 0] : [0, time()];
	if (time() - (int)$d[1] > 900) $d = [0, time()];
	@file_put_contents($f, ((int)$d[0] + 1) . ' ' . (int)$d[1], LOCK_EX);
}

/*
 * Comprova usuari, contrasenya i (des de fora) clau temporal. $intent: l'usuari acaba d'enviar
 * el formulari (només llavors compta com a intent fallit; la sessió torna a comprovar-se a cada petició).
 * Torna [true|false, missatge d'error].
 */
function autentica($link, $usuari, $contrasenya, $clau, $intent = true) {
	if (login_bloquejat()) return [false, "Massa intents fallits des d'aquesta adreça: torna-hi d'aquí a 15 minuts."];
	$usuari = (string)$usuari; $contrasenya = (string)$contrasenya;
	$ok = $usuari !== '' && $contrasenya !== '' &&
		contrasenya_ok(mysql1r("SELECT `password` FROM `users` WHERE `username`='" . $link->real_escape_string($usuari) . "'", $link), $contrasenya);
	$error = $ok ? '' : 'Usuari o contrasenya incorrectes.';
	if ($ok && cal_clau_temporal() && !clau_temporal_ok($clau)) { $ok = false; $error = 'Des de fora de la xarxa cal la contrasenya temporal.'; }
	if ($ok) { @unlink(login_fitxer_intents()); return [true, '']; }
	if ($intent) login_fallada();
	return [false, $intent ? $error : ''];
}
