<?php
// Condicions d'ús i protecció de dades: es llegeixen de la taula Condicions (versió, idioma, contingut).
// /condicions            -> versió vigent, en l'idioma del navegador (ca per defecte)
// /condicions?lang=es    -> en castellà
// /condicions?v=2026-11-21 -> una versió concreta (la que va acceptar un client)
error_reporting(0);
require_once($_SERVER['DOCUMENT_ROOT'] . '/../keys.php');
$db = new mysqli($myserver, $myuser, $mypassword, $mydatabase);
$db->set_charset('utf8mb4');
$idiomes = ['ca' => 'Català', 'es' => 'Castellano', 'fr' => 'Français', 'en' => 'English'];
$lang = $_GET['lang'] ?? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'ca', 0, 2);
if (!isset($idiomes[$lang])) $lang = 'ca';
$v = (string)($_GET['v'] ?? '');
if ($v !== '') {
    $st = $db->prepare("SELECT versio, idioma, titol, contingut, hash, data_publicacio FROM Condicions WHERE versio=? AND idioma IN (?, 'ca') ORDER BY idioma=? DESC LIMIT 1");
    $st->bind_param('sss', $v, $lang, $lang);
} else {
    $st = $db->prepare("SELECT versio, idioma, titol, contingut, hash, data_publicacio FROM Condicions WHERE vigent=1 AND idioma IN (?, 'ca') ORDER BY idioma=? DESC, versio DESC LIMIT 1");
    $st->bind_param('ss', $lang, $lang);
}
$st->execute();
$c = $st->get_result()->fetch_assoc();
if (!$c) { http_response_code(404); echo 'No trobat'; exit; }
header('Cache-Control: public, max-age=300');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
header('X-Frame-Options: DENY');
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="<?= $e($c['idioma']) ?>"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $e($c['titol']) ?></title>
<style>
:root{--bg:#fff;--fg:#222;--mut:#666;--lin:#ddd;--acc:#2f6fb3}
@media(prefers-color-scheme:dark){:root{--bg:#1b1b1b;--fg:#eee;--mut:#aaa;--lin:#333;--acc:#7fb0e6}}
body{margin:0;background:var(--bg);color:var(--fg);font:17px/1.55 system-ui,sans-serif}
main{max-width:46rem;margin:0 auto;padding:24px 16px 60px}
h1{font-size:1.6rem;margin:.2em 0 .1em}h2{font-size:1.15rem;margin:1.6em 0 .3em}
a{color:var(--acc)}.meta{color:var(--mut);font-size:.85rem;border-bottom:1px solid var(--lin);padding-bottom:12px;margin-bottom:8px}
.idiomes{float:right}li{margin:.3em 0}
</style></head><body><main>
<div class="idiomes"><?php foreach ($idiomes as $k => $n) if ($k !== $c['idioma']) echo '<a href="?lang=' . $e($k) . ($v !== '' ? '&amp;v=' . $e($v) : '') . '">' . $e($n) . '</a> '; ?></div>
<h1><?= $e($c['titol']) ?></h1>
<div class="meta">Versió <?= $e($c['versio']) ?> · <?= $e(substr($c['data_publicacio'], 0, 10)) ?> · empremta <?= $e(substr($c['hash'], 0, 12)) ?></div>
<?= $c['contingut'] /* HTML propi guardat a la base de dades */ ?>
</main></body></html>
