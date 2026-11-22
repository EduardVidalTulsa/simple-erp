<?php
/**
 * Comptador de torns d'una empresa: taula Empresa_<ID>_torn (una sola fila, no se sincronitza).
 * Internament els números no tenen sostre dins del dia; es mostren de 1 a 999 (torn_mostra).
 * ultim = darrer número donat; actual = número que s'està atenent; esperant = ultim - actual.
 * actiu = interruptor de la dependenta (giny): apagat, no es donen torns i el quiosc mostra el QR d'alta de client.
 */

function torn_taula($link, $ID_Empresa) {
	$t = "Empresa_" . (int)$ID_Empresa . "_torn";
	static $fetes = [];
	if(!isset($fetes[$t])) {
		$link->query("CREATE TABLE IF NOT EXISTS `$t` (
			`ID` tinyint unsigned NOT NULL DEFAULT 1,
			`data` date NOT NULL,
			`ultim` int unsigned NOT NULL DEFAULT 0,
			`actual` int unsigned NOT NULL DEFAULT 0,
			`darrer_tiquet` datetime(3) DEFAULT NULL,
			PRIMARY KEY (`ID`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$link->query("ALTER TABLE `$t` ADD COLUMN IF NOT EXISTS `actiu` tinyint(1) NOT NULL DEFAULT 1");
		$link->query("INSERT IGNORE INTO `$t` (ID, data) VALUES (1, CURDATE())");
		$fetes[$t] = true;
	}
	return $t;
}

function torn_mostra($n) {
	return $n <= 0 ? 0 : (($n - 1) % 999) + 1;
}

/** Executa $fn(&$fila) amb la fila bloquejada (i reiniciada si és d'un altre dia) i la desa */
function torn_transaccio($link, $ID_Empresa, $fn) {
	$t = torn_taula($link, $ID_Empresa);
	$link->begin_transaction();
	$f = $link->query("SELECT data, ultim, actual, darrer_tiquet, actiu FROM `$t` WHERE ID=1 FOR UPDATE")->fetch_assoc();
	// el comptador es reinicia cada dia; l'interruptor es manté
	if($f['data'] !== date('Y-m-d')) $f = ['data' => date('Y-m-d'), 'ultim' => 0, 'actual' => 0, 'darrer_tiquet' => null, 'actiu' => $f['actiu']];
	$f['ultim'] = (int)$f['ultim'];
	$f['actual'] = (int)$f['actual'];
	$f['actiu'] = (int)$f['actiu'];
	$extra = $fn($f);
	$st = $link->prepare("UPDATE `$t` SET data=?, ultim=?, actual=?, darrer_tiquet=?, actiu=? WHERE ID=1");
	$st->bind_param('siisi', $f['data'], $f['ultim'], $f['actual'], $f['darrer_tiquet'], $f['actiu']);
	$st->execute();
	$link->commit();
	return torn_resum($f) + (is_array($extra) ? $extra : []);
}

function torn_resum($f) {
	// actual_n: número intern, perquè el mòbil del client sàpiga quanta gent té davant encara que es passi de 999
	return ['actual' => torn_mostra($f['actual']), 'esperant' => max(0, $f['ultim'] - $f['actual']),
	        'ultim' => torn_mostra($f['ultim']), 'actual_n' => $f['actual'], 'actiu' => (bool)$f['actiu']];
}

function torn_estat($link, $ID_Empresa) {
	return torn_transaccio($link, $ID_Empresa, function(&$f) {});
}

/** La dependenta passa al següent (+1) o torna enrere (-1) */
function torn_mou($link, $ID_Empresa, $pas) {
	return torn_transaccio($link, $ID_Empresa, function(&$f) use ($pas) {
		if($pas > 0 && $f['actual'] < $f['ultim']) $f['actual']++;
		if($pas < 0 && $f['actual'] > 0) $f['actual']--;
	});
}

/** Interruptor de la dependenta: $on true/false encén o apaga els torns (null: l'inverteix) */
function torn_interruptor($link, $ID_Empresa, $on = null) {
	return torn_transaccio($link, $ID_Empresa, function(&$f) use ($on) {
		$f['actiu'] = $on === null ? 1 - $f['actiu'] : ($on ? 1 : 0);
	});
}

/** Un client agafa torn. Antirebot: si fa menys de 2 s del darrer tiquet, no en dona cap de nou. */
function torn_agafa($link, $ID_Empresa) {
	return torn_transaccio($link, $ID_Empresa, function(&$f) {
		if(!$f['actiu']) return ['nou' => false, 'tancat' => true];
		$ara = microtime(true);
		if($f['darrer_tiquet'] && $ara - strtotime($f['darrer_tiquet']) < 2) return ['nou' => false];
		$f['ultim']++;
		$f['darrer_tiquet'] = date('Y-m-d H:i:s', (int)$ara);
		return ['nou' => true, 'torn' => torn_mostra($f['ultim']), 'n' => $f['ultim'], 'davant' => max(0, $f['ultim'] - $f['actual'] - 1)];
	});
}

/**
 * Codi del QR per agafar torn des del mòbil propi: canvia cada dia (HMAC del token del quiosc),
 * així el QR fotografiat no serveix l'endemà i el token del quiosc no surt mai de la botiga.
 */
function torn_codi_dia($token, $dia = null) {
	return substr(hash_hmac('sha256', 'torn-qr|' . ($dia ?? date('Y-m-d')), (string)$token), 0, 16);
}

/** Signatura de l'enllaç de tornclient.php (número intern + hora), perquè ningú s'inventi un torn */
function torn_signa($token, $n, $hora, $dia = null) {
	return substr(hash_hmac('sha256', "torn-client|" . ($dia ?? date('Y-m-d')) . "|$n|$hora", (string)$token), 0, 16);
}

/** Usuari de scale (i la seva config) amb el connector actiu que té aquest token, o aquest codi del dia si $per_codi */
function torn_usuari($link, $clau, $per_codi = false) {
	$clau = (string)$clau;
	if(strlen($clau) < 16 || !plugin_actiu($link, 'torn')) return null;
	plugins_taules($link);
	foreach($link->query("SELECT username, CAST(config AS BINARY) AS config FROM plugin_usuari WHERE plugin='torn'")->fetch_all(MYSQLI_ASSOC) as $f) {
		$c = json_decode((string)$f['config'], true) ?: [];
		if(empty($c['token']) || strlen($c['token']) < 16) continue;
		$esperat = $per_codi ? torn_codi_dia($c['token']) : (string)$c['token'];
		if(hash_equals($esperat, $clau)) return [$f['username'], $c];
	}
	return null;
}

function torn_empresa($link, $username) {
	$st = $link->prepare("SELECT ID_Empresa FROM users WHERE username=?");
	$st->bind_param('s', $username);
	$st->execute();
	return (int)($st->get_result()->fetch_row()[0] ?? 0);
}

/** Adreça de l'alta de client (QR del quiosc quan els torns estan apagats) */
function torn_url_alta($cfg, $base = '') {
	$u = trim((string)($cfg['url_alta'] ?? ''));
	return $u !== '' ? $u : rtrim((string)$base, '/') . '/altaclient/';
}

/** Imprimeix el tiquet del torn a la impressora de tiquets d'aquest servidor (ticket_print_command de keys.php) */
function torn_imprimeix($link, $ID_Empresa, $torn, $davant) {
	if(!function_exists('ticket_print_command')) return false;
	require_once($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');
	$st = $link->prepare("SELECT termal_head_1 FROM `Empresa` WHERE ID=?");
	$st->bind_param('i', $ID_Empresa);
	$st->execute();
	$cap = (string)($st->get_result()->fetch_row()[0] ?? '');
	$ascii = function($s) { $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s); return $t === false ? $s : $t; };
	$fitxer = 'torn_' . bin2hex(random_bytes(6)) . '.lp';
	$ruta = $_SERVER['DOCUMENT_ROOT'] . "/scale/$fitxer";
	$p = new \Mike42\Escpos\Printer(new \Mike42\Escpos\PrintConnectors\FilePrintConnector($ruta));
	$p->initialize();
	$p->setJustification(\Mike42\Escpos\Printer::JUSTIFY_CENTER);
	if($cap !== '') { $p->setEmphasis(true); $p->text($ascii($cap) . "\n"); $p->setEmphasis(false); }
	$p->feed(1);
	$p->setTextSize(3, 3);
	$p->text("TORN\n");
	$p->setTextSize(8, 8);
	$p->text("$torn\n");
	$p->setTextSize(1, 1);
	$p->feed(1);
	$p->setTextSize(2, 2);
	$p->text($davant == 0 ? "Ets el seguent!\n" : ($davant == 1 ? "Teniu davant\n1 persona\n" : "Teniu davant\n$davant persones\n"));
	$p->setTextSize(1, 1);
	$p->feed(1);
	$p->text(date('d/m/Y H:i') . "\n");
	$p->feed(2);
	$p->cut();
	$p->close();
	$ok = ticket_print_command($fitxer) !== false;
	@unlink($ruta);
	return $ok;
}
