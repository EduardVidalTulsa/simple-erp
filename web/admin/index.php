<?php
/**
 * /admin: activa, desactiva i configura els connectors (web/plugins/<nom>/plugin.json).
 * Hi entren els usuaris de scale amb `users`.pot_gestionar_plugins=1 (root per defecte).
 */
$app = "admin";
require('../scale/login.php');
if(!$login) { require('../scale/tanca.php'); $link->close(); die; }
require_once('../plugins/carrega.php');
$link->set_charset('utf8mb4');

if(!mysql1r("SHOW COLUMNS FROM `users` LIKE 'pot_gestionar_plugins'", $link)) {
	$link->query("ALTER TABLE `users` ADD `pot_gestionar_plugins` tinyint(4) unsigned NOT NULL DEFAULT 0
		COMMENT 'pot activar i configurar connectors a /admin'");
	$link->query("UPDATE `users` SET `pot_gestionar_plugins`=1 WHERE `username` IN ('root','admin')");
}
$usuari = (string)$_SESSION['user'];
$st = $link->prepare("SELECT pot_gestionar_plugins FROM `users` WHERE username=?");
$st->bind_param('s', $usuari);
$st->execute();
if(($st->get_result()->fetch_row()[0] ?? '0') != '1') {
	echo "No tens permís per gestionar els connectors. Demana que t'activin `pot_gestionar_plugins` a `users`.";
	$link->close(); die;
}
plugins_taules($link);
if(empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['admin_csrf'];
$plugins = plugins_disponibles();

/** Valors dels camps enviats pel formulari, segons els tipus de plugin.json */
function admin_valors($meta) {
	$c = [];
	foreach($meta['config'] as $camp => $d) {
		$v = $_POST['camp'][$camp] ?? '';
		switch($d['tipus'] ?? 'text') {
			case 'bool':   $c[$camp] = !empty($v); break;
			case 'number': $c[$camp] = ($v === '' ? '' : (is_numeric($v) ? $v + 0 : '')); break;
			default:       $c[$camp] = str_replace("\r\n", "\n", trim((string)$v));
		}
	}
	return $c;
}

$missatge = '';
// el formulari d'entrada (user/contra) també és un POST: només són accions les que porten «accio»
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accio'])) {
	$nom = $_POST['plugin'] ?? '';
	if(!hash_equals($csrf, (string)($_POST['csrf'] ?? '')) || !isset($plugins[$nom])) {
		http_response_code(400); echo "Petició no vàlida"; die;
	}
	$meta = $plugins[$nom];
	$accio = $_POST['accio'] ?? '';
	if($accio === 'actiu') {
		$a = !empty($_POST['valor']) ? 1 : 0;
		$cfg = json_encode(plugin_config($link, $nom), JSON_UNESCAPED_UNICODE);
		$st = $link->prepare("INSERT INTO `plugin` (plugin, actiu, config) VALUES (?,?,?) ON DUPLICATE KEY UPDATE actiu=VALUES(actiu)");
		$st->bind_param('sis', $nom, $a, $cfg);
		$st->execute();
		$missatge = "«{$meta['titol']}» " . ($a ? 'activat' : 'desactivat');
	}
	elseif($accio === 'config_lloc') {
		$cfg = json_encode(admin_valors($meta), JSON_UNESCAPED_UNICODE);
		$a = plugin_actiu($link, $nom) ? 1 : 0;
		$st = $link->prepare("INSERT INTO `plugin` (plugin, actiu, config) VALUES (?,?,?) ON DUPLICATE KEY UPDATE config=VALUES(config)");
		$st->bind_param('sis', $nom, $a, $cfg);
		$st->execute();
		$missatge = "Configuració de «{$meta['titol']}» desada";
	}
	elseif($accio === 'usuari_afegeix' || $accio === 'usuari_config') {
		$u = (string)($_POST['username'] ?? '');
		$st = $link->prepare("SELECT COUNT(*) FROM users WHERE username=?");
		$st->bind_param('s', $u);
		$st->execute();
		if($u !== '' && $st->get_result()->fetch_row()[0] > 0) {
			$valors = admin_valors($meta);
			// un token buit es genera sol, així el connector ja funciona en activar-lo
			foreach($meta['config'] as $camp => $d)
				if(($d['tipus'] ?? '') === 'token' && ($valors[$camp] ?? '') === '') $valors[$camp] = bin2hex(random_bytes(16));
			$cfg = json_encode($valors, JSON_UNESCAPED_UNICODE);
			$st = $link->prepare("INSERT INTO `plugin_usuari` (username, plugin, config) VALUES (?,?,?) ON DUPLICATE KEY UPDATE config=VALUES(config)");
			$st->bind_param('sss', $u, $nom, $cfg);
			$st->execute();
			$missatge = "«{$meta['titol']}» per a l'usuari $u desat";
		}
	}
	elseif($accio === 'usuari_treu') {
		$u = (string)($_POST['username'] ?? '');
		$st = $link->prepare("DELETE FROM `plugin_usuari` WHERE plugin=? AND username=?");
		$st->bind_param('ss', $nom, $u);
		$st->execute();
		$missatge = "«{$meta['titol']}» tret a l'usuari $u";
	}
	error_log("admin plugins: $missatge (per $usuari)");
	header('Location: ./?m=' . urlencode($missatge) . '#' . $nom, true, 303);
	die;
}

$h = fn($s) => htmlspecialchars((string)$s);
$usuaris = array_column($link->query("SELECT username FROM users ORDER BY username")->fetch_all(MYSQLI_ASSOC), 'username');
$base = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https://' : 'http://';
$base .= $_SERVER['HTTP_HOST'];

/** Camps d'un formulari de configuració */
function admin_camps($meta, $valors, $h) {
	$o = '';
	foreach($meta['config'] as $camp => $d) {
		$v = $valors[$camp] ?? '';
		$n = 'camp[' . $h($camp) . ']';
		$o .= '<label class="camp"><span>' . $h($d['etiqueta'] ?? $camp) . '</span>';
		switch($d['tipus'] ?? 'text') {
			case 'bool':
				$o .= '<input type="checkbox" name="' . $n . '" value="1"' . ($v ? ' checked' : '') . '>'; break;
			case 'number':
				$o .= '<input type="number" name="' . $n . '" value="' . $h($v) . '">'; break;
			case 'token':
				$o .= '<span class="fila"><input type="text" class="mono" name="' . $n . '" value="' . $h($v) . '" readonly>'
					. '<button type="button" class="sec" onclick="genera(this)">Genera</button></span>'; break;
			default:
				$o .= str_contains((string)($d['per_defecte'] ?? ''), "\n") || str_contains((string)$v, "\n")
					? '<textarea name="' . $n . '" rows="3">' . $h($v) . '</textarea>'
					: '<input type="text" name="' . $n . '" value="' . $h($v) . '">';
		}
		if(!empty($d['ajuda'])) $o .= '<small>' . $h($d['ajuda']) . '</small>';
		$o .= '</label>';
	}
	return $o;
}
?><!doctype html>
<html lang="ca"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Connectors</title>
<style>
:root{--fons:#f4f4f2;--targeta:#fff;--text:#222;--suau:#666;--vora:#ddd;--blau:#2f6fb3;--verd:#2e8b57;--vermell:#b33a3a}
@media(prefers-color-scheme:dark){:root{--fons:#1b1b1b;--targeta:#262626;--text:#eee;--suau:#aaa;--vora:#444}}
body{margin:0;padding:16px;background:var(--fons);color:var(--text);font:16px system-ui,sans-serif}
main{max-width:900px;margin:0 auto}
h1{font-size:1.5rem}
.targeta{background:var(--targeta);border:1px solid var(--vora);border-radius:10px;padding:16px;margin:16px 0}
.cap{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.cap h2{margin:0;font-size:1.2rem}
.desc{color:var(--suau);margin:.5rem 0 1rem}
.estat{font-size:.85rem;padding:2px 8px;border-radius:10px;color:#fff}
button{border:0;border-radius:8px;padding:.5rem 1rem;color:#fff;background:var(--blau);cursor:pointer;font-size:.95rem}
button.verd{background:var(--verd)}button.vermell{background:var(--vermell)}button.sec{background:#777}
.camp{display:flex;flex-direction:column;gap:4px;margin:.6rem 0}
.camp span{font-weight:600}.camp small{color:var(--suau)}
input[type=text],input[type=number],textarea,select{font:inherit;padding:.4rem;border:1px solid var(--vora);border-radius:6px;background:var(--fons);color:var(--text);max-width:100%;box-sizing:border-box}
input[type=text],textarea{width:100%}
.mono{font-family:monospace;font-size:.85rem}
.fila{display:flex;gap:6px}
.usuari{border-top:1px solid var(--vora);padding-top:.8rem;margin-top:.8rem}
.enllac{font-family:monospace;font-size:.8rem;word-break:break-all}
.missatge{background:var(--verd);color:#fff;padding:.6rem 1rem;border-radius:8px}
form.en-linia{display:inline}
</style></head><body><main>
<h1>Connectors de <?= $h($_SERVER['HTTP_HOST']) ?></h1>
<p class="desc">Cada servidor té els seus: el que actives aquí només val per a les bàscules que fan servir aquest servidor.</p>
<?php if(!empty($_GET['m'])): ?><p class="missatge"><?= $h($_GET['m']) ?></p><?php endif; ?>
<?php foreach($plugins as $nom => $meta): $actiu = plugin_actiu($link, $nom); ?>
<section class="targeta" id="<?= $h($nom) ?>">
 <div class="cap">
  <h2><?= $h($meta['titol']) ?> <span class="estat" style="background:<?= $actiu ? 'var(--verd)' : '#888' ?>"><?= $actiu ? 'actiu' : 'inactiu' ?></span></h2>
  <form method="post" class="en-linia">
   <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="plugin" value="<?= $h($nom) ?>">
   <input type="hidden" name="accio" value="actiu"><input type="hidden" name="valor" value="<?= $actiu ? '' : '1' ?>">
   <button class="<?= $actiu ? 'vermell' : 'verd' ?>"><?= $actiu ? 'Desactiva' : 'Activa' ?></button>
  </form>
 </div>
 <p class="desc"><?= $h($meta['descripcio']) ?></p>
 <?php if($meta['abast'] === 'lloc' && $meta['config']): ?>
  <form method="post">
   <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="plugin" value="<?= $h($nom) ?>">
   <input type="hidden" name="accio" value="config_lloc">
   <?= admin_camps($meta, plugin_config($link, $nom), $h) ?>
   <button>Desa</button>
  </form>
 <?php elseif($meta['abast'] === 'usuari'):
	$st = $link->prepare("SELECT username, CAST(config AS BINARY) AS config FROM plugin_usuari WHERE plugin=? ORDER BY username");
	$st->bind_param('s', $nom);
	$st->execute();
	$files = $st->get_result()->fetch_all(MYSQLI_ASSOC);
	if(!$files): ?><p class="desc">Cap usuari el té actiu.</p><?php endif;
	foreach($files as $f): $cfg = (json_decode((string)$f['config'], true) ?: []) + plugin_config_defecte($nom); ?>
  <div class="usuari">
   <div class="cap"><b>Usuari <?= $h($f['username']) ?></b>
    <form method="post" class="en-linia" onsubmit="return confirm('Treure aquest connector a l\'usuari <?= $h($f['username']) ?>?')">
     <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="plugin" value="<?= $h($nom) ?>">
     <input type="hidden" name="accio" value="usuari_treu"><input type="hidden" name="username" value="<?= $h($f['username']) ?>">
     <button class="vermell">Treu</button>
    </form>
   </div>
   <form method="post">
    <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="plugin" value="<?= $h($nom) ?>">
    <input type="hidden" name="accio" value="usuari_config"><input type="hidden" name="username" value="<?= $h($f['username']) ?>">
    <?= admin_camps($meta, $cfg, $h) ?>
    <?php if(!empty($meta['enllac_usuari'])):
		$url = $base . preg_replace_callback('/\{(\w+)\}/', fn($m) => rawurlencode((string)($cfg[$m[1]] ?? '')), $meta['enllac_usuari']); ?>
     <p class="enllac">Enllaç: <a href="<?= $h($url) ?>" target="_blank" rel="noreferrer"><?= $h($url) ?></a></p>
    <?php endif; ?>
    <button>Desa</button>
   </form>
  </div>
  <?php endforeach; ?>
  <form method="post" class="usuari">
   <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="plugin" value="<?= $h($nom) ?>">
   <input type="hidden" name="accio" value="usuari_afegeix">
   <b>Activa'l per a un usuari de scale</b>
   <label class="camp"><span>Usuari</span>
    <select name="username"><?php foreach($usuaris as $u): ?><option><?= $h($u) ?></option><?php endforeach; ?></select></label>
   <?= admin_camps($meta, plugin_config_defecte($nom), $h) ?>
   <button class="verd">Activa</button>
  </form>
 <?php endif; ?>
</section>
<?php endforeach; ?>
</main>
<script>
function genera(b){var i=b.previousElementSibling,a=new Uint8Array(16);crypto.getRandomValues(a);
 i.value=Array.from(a,function(x){return x.toString(16).padStart(2,'0')}).join('');}
</script>
</body></html>
