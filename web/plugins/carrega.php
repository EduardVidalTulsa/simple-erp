<?php
/**
 * Connectors (plugins) de simple-erp.
 *
 * Cada connector és una carpeta web/plugins/<nom>/ amb un plugin.json:
 *   titol, descripcio, abast ("lloc": un sol interruptor per a tot el servidor,
 *   o "usuari": s'activa i es configura per a cada usuari de scale), per_defecte
 *   (actiu o no mentre ningú l'ha tocat a /admin) i config (camps: tipus text|number|bool|token,
 *   per_defecte, ajuda).
 * Fitxers opcionals d'un connector d'abast usuari:
 *   widget.php  giny que scale/index.php mostra a baix a la dreta (té $plugin_cfg i $plugin_url)
 *   magic.php   retorna function($link, $codi, $cfg, $scale_uri): bool, per atendre codis
 *               teclejats al camp de codi de scale (true = l'ha atès i ja ha redirigit)
 * L'estat es desa a les taules `plugin` i `plugin_usuari` (es creen soles; no se sincronitzen
 * entre seus). La pàgina d'administració és /admin/.
 */

function plugin_nom_valid($nom) {
	return is_string($nom) && preg_match('/^[a-z0-9_-]{1,32}$/', $nom) && is_file(__DIR__ . "/$nom/plugin.json");
}

/** [nom => metadades de plugin.json] de tots els connectors instal·lats */
function plugins_disponibles() {
	static $tots = null;
	if($tots !== null) return $tots;
	$tots = [];
	foreach(glob(__DIR__ . '/*/plugin.json') as $f) {
		$nom = basename(dirname($f));
		if(!preg_match('/^[a-z0-9_-]{1,32}$/', $nom)) continue;
		$m = json_decode(file_get_contents($f), true);
		if(!is_array($m)) continue;
		$m += ['titol' => $nom, 'descripcio' => '', 'abast' => 'lloc', 'per_defecte' => false, 'config' => []];
		$tots[$nom] = $m;
	}
	ksort($tots);
	return $tots;
}

function plugin_meta($nom) {
	return plugins_disponibles()[$nom] ?? null;
}

function plugins_taules($link) {
	static $fet = false;
	if($fet) return;
	$fet = true;
	$link->query("CREATE TABLE IF NOT EXISTS `plugin` (
		`plugin` varchar(32) NOT NULL,
		`actiu` tinyint(1) NOT NULL DEFAULT 1,
		`config` text DEFAULT NULL,
		PRIMARY KEY (`plugin`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	$link->query("CREATE TABLE IF NOT EXISTS `plugin_usuari` (
		`username` varchar(64) NOT NULL,
		`plugin` varchar(32) NOT NULL,
		`config` text DEFAULT NULL,
		PRIMARY KEY (`username`, `plugin`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Valors per defecte dels camps de configuració d'un connector */
function plugin_config_defecte($nom) {
	$c = [];
	foreach((plugin_meta($nom)['config'] ?? []) as $camp => $d) $c[$camp] = $d['per_defecte'] ?? '';
	return $c;
}

function plugin_fila($link, $nom) {
	plugins_taules($link);
	$st = $link->prepare("SELECT actiu, CAST(config AS BINARY) AS config FROM `plugin` WHERE plugin=?");
	$st->bind_param('s', $nom);
	$st->execute();
	return $st->get_result()->fetch_assoc();
}

/** Està actiu a tot el servidor? (si ningú l'ha tocat, el que digui per_defecte) */
function plugin_actiu($link, $nom) {
	$m = plugin_meta($nom);
	if(!$m) return false;
	$f = plugin_fila($link, $nom);
	return $f ? (bool)$f['actiu'] : (bool)$m['per_defecte'];
}

/** Configuració del connector per a tot el servidor */
function plugin_config($link, $nom) {
	$f = plugin_fila($link, $nom);
	$c = $f ? (json_decode((string)$f['config'], true) ?: []) : [];
	return $c + plugin_config_defecte($nom);
}

/** Configuració d'un connector d'abast usuari per a $user, o null si no el té actiu */
function plugin_config_usuari($link, $nom, $user) {
	if(!plugin_nom_valid($nom) || !plugin_actiu($link, $nom)) return null;
	plugins_taules($link);
	$st = $link->prepare("SELECT CAST(config AS BINARY) AS config FROM `plugin_usuari` WHERE plugin=? AND username=?");
	$user = (string)$user;
	$st->bind_param('ss', $nom, $user);
	$st->execute();
	$f = $st->get_result()->fetch_assoc();
	if(!$f) return null;
	return (json_decode((string)$f['config'], true) ?: []) + plugin_config_defecte($nom);
}

/** [nom => config] dels connectors d'abast usuari actius per a $user */
function plugins_usuari($link, $user) {
	plugins_taules($link);
	$st = $link->prepare("SELECT plugin FROM `plugin_usuari` WHERE username=? ORDER BY plugin");
	$user = (string)$user;
	$st->bind_param('s', $user);
	$st->execute();
	$r = [];
	foreach($st->get_result()->fetch_all(MYSQLI_ASSOC) as $f) {
		$c = plugin_config_usuari($link, $f['plugin'], $user);
		if($c !== null) $r[$f['plugin']] = $c;
	}
	return $r;
}

/** Mostra els ginys dels connectors de l'usuari de la sessió (a baix a la dreta de scale) */
function scale_plugins_mostra($link) {
	$user = $_SESSION['user'] ?? '';
	if($user === '') return;
	$ginys = [];
	foreach(plugins_usuari($link, $user) as $nom => $plugin_cfg) {
		if(!is_file(__DIR__ . "/$nom/widget.php")) continue;
		$plugin_url = "/plugins/$nom/";
		ob_start();
		include __DIR__ . "/$nom/widget.php";
		$ginys[] = ob_get_clean();
	}
	if(!$ginys) return;
	echo '<div id="scale-plugins" style="position:fixed;right:8px;bottom:18px;z-index:99999;display:flex;'
		. 'flex-direction:column;align-items:flex-end;gap:8px;user-select:none">' . implode('', $ginys) . '</div>';
}

/** Dona als connectors de l'usuari l'oportunitat d'atendre un codi del camp de codi de scale */
function scale_plugins_magic($link, $codi, $scale_uri) {
	$user = $_SESSION['user'] ?? '';
	if($user === '' || !is_string($codi) || $codi === '') return false;
	foreach(plugins_usuari($link, $user) as $nom => $cfg) {
		$f = __DIR__ . "/$nom/magic.php";
		if(!is_file($f)) continue;
		$fn = include $f;
		if(is_callable($fn) && $fn($link, $codi, $cfg, $scale_uri)) return true;
	}
	return false;
}

/**
 * Per als fitxers que el navegador crida en segon pla (JSON): comprova la sessió de scale
 * i que l'usuari tingui el connector actiu. Retorna [$link, $cfg, $user] o respon 403.
 */
function plugin_endpoint_sessio($nom) {
	if(session_status() !== PHP_SESSION_ACTIVE) session_start();
	$user = (string)($_SESSION['user'] ?? '');
	$ok = ($_SESSION['login'] ?? false) === true && $user !== '';
	session_write_close();
	header('Content-Type: application/json');
	header('Cache-Control: no-store');
	if($ok) {
		$link = plugin_db();
		$cfg = plugin_config_usuari($link, $nom, $user);
		if($cfg !== null) return [$link, $cfg, $user];
	}
	http_response_code(403);
	echo '{"error":"no"}';
	exit;
}

/** Connexió a la base de dades amb la configuració de keys.php (fora de l'arrel web) */
function plugin_db() {
	global $myserver, $myuser, $mypassword, $mydatabase;
	if(!isset($myserver)) require_once($_SERVER['DOCUMENT_ROOT'] . '/../keys.php');
	$link = new mysqli($myserver, $myuser, $mypassword, $mydatabase);
	$link->set_charset('utf8mb4');
	return $link;
}
