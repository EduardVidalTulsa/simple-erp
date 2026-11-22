<?php
/**
 * Torn des del mòbil propi del client (s'hi arriba pel QR del quiosc): ?q=<codi del dia>.
 * Agafa el torn de la seu (1–999, el mateix comptador que el botó del quiosc) sense imprimir res i
 * sense lligar-lo a cap client, i porta el client a tornclient.php (enllaç signat amb el seu torn i l'hora).
 * L'enllaç es desa al localStorage: si torna a escanejar el QR el mateix dia, va directe al seu torn.
 * GET &estat=1: JSON de la cua (també el fa servir tornclient.php). POST: agafa torn.
 */
require __DIR__ . '/../carrega.php';
require __DIR__ . '/torn.php';
$link = plugin_db();
$q = preg_replace('/[^a-f0-9]/', '', (string)($_GET['q'] ?? ''));
[$usuari, $cfg] = torn_usuari($link, $q, true) ?? [null, []];
$emp = $usuari === null ? 0 : torn_empresa($link, $usuari);
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex');

if($emp > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
	header('Content-Type: application/json');
	// l'antirebot de torn_agafa() (2 s) és compartit amb el quiosc: es reintenta uns quants cops
	for($i = 0; $i < 4; $i++) {
		$r = torn_agafa($link, $emp);
		if($r['nou'] || !empty($r['tancat'])) break;
		usleep(700000);
	}
	if($r['nou']) {
		$hora = date('H:i');
		$r['url'] = 'tornclient.php?' . http_build_query(['q' => $q, 'n' => $r['n'], 'h' => $hora, 's' => torn_signa($cfg['token'], $r['n'], $hora)]);
		error_log("plugin torn: empresa $emp torn {$r['torn']} pel QR (davant {$r['davant']})");
	}
	echo json_encode($r + ['dia' => date('Y-m-d')]);
	exit;
}
if($emp > 0 && isset($_GET['estat'])) {
	header('Content-Type: application/json');
	echo json_encode(torn_estat($link, $emp) + ['dia' => date('Y-m-d')]);
	exit;
}
$nom = '';
if($emp > 0) {
	$st = $link->prepare("SELECT Value FROM `Empresa` WHERE ID=?");
	$st->bind_param('i', $emp);
	$st->execute();
	$nom = (string)($st->get_result()->fetch_row()[0] ?? '');
} else http_response_code(404);
?><!doctype html>
<html lang="ca"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<link rel="icon" href="icona-192.png"><link rel="apple-touch-icon" href="icona-192.png">
<meta name="theme-color" content="#2f6fb3">
<title>Torn<?= $nom !== '' ? ' · ' . htmlspecialchars($nom) : '' ?></title>
<style>
html,body{margin:0;min-height:100%;background:#f4f4f2;color:#222;font-family:system-ui,sans-serif}
body{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:22px;padding:24px 16px;box-sizing:border-box;text-align:center;min-height:100vh}
h1{font-size:18px;font-weight:600;margin:0;opacity:.75}
.gran{font-size:min(38vw,170px);font-weight:bold;line-height:1;color:#2f6fb3}
.t{font-size:22px}
.t b{font-size:1.3em}
.petit{font-size:15px;opacity:.7}
button{width:min(70vw,260px);height:min(70vw,260px);border-radius:50%;border:0;background:#2f6fb3;color:#fff;
 font-size:min(10vw,44px);font-weight:bold;box-shadow:0 10px 0 #1d4a7a;cursor:pointer;line-height:1.1}
button:active{transform:translateY(8px);box-shadow:0 2px 0 #1d4a7a}
button:disabled{background:#7a9cc4}
.ara{background:#2e8b57;color:#fff;border-radius:16px;padding:18px 22px;font-size:24px;font-weight:bold}
.arbre{max-width:340px;background:#e6f2e6;color:#24572f;border-radius:16px;padding:14px 18px;font-size:16px;line-height:1.4}
.arbre .e{font-size:44px;line-height:1;display:block;margin-bottom:6px}
.amaga{display:none!important}
@media(prefers-color-scheme:dark){html,body{background:#1b1b1b;color:#eee}.gran{color:#6fa8e6}.arbre{background:#1f3324;color:#bfe3c6}}
</style></head><body>
<?php if($emp <= 0): ?>
<div class="t">Aquest codi ja no és vàlid.</div>
<div class="petit">Torna a escanejar el QR de la pantalla de la botiga.</div>
<?php else: ?>
<h1><?= htmlspecialchars($nom) ?></h1>

<div class="t" id="cua">&nbsp;</div>
<button id="b">Agafa<br>torn</button>
<div class="t amaga" id="tancat">Ara mateix no donem torns:<br><b>passa directament al taulell</b> 😊</div>

<div class="arbre"><span class="e">🌳</span><b>Agafa torn sense paper!</b><br>Al teu mòbil veuràs quanta gent tens davant.</div>
<?php endif; ?>
<script>
(function(){
 if(!document.getElementById('b'))return;
 var Q=<?= json_encode($q) ?>,CLAU='torn_'+Q,b=document.getElementById('b'),cua=document.getElementById('cua');
 // si ja té torn d'avui (el codi del QR canvia cada dia), directe a la pàgina del seu torn
 try{var meu=JSON.parse(localStorage.getItem(CLAU)||'null');if(meu&&meu.url){location.replace(meu.url);return;}}catch(e){}
 function pinta(d){if(!d||typeof d.esperant!=='number')return;
  var t=d.actiu===false;b.classList.toggle('amaga',t);cua.classList.toggle('amaga',t);document.getElementById('tancat').classList.toggle('amaga',!t);
  cua.innerHTML=d.esperant==0?'No hi ha ningú esperant':(d.esperant==1?'Hi ha <b>1</b> persona esperant':'Hi ha <b>'+d.esperant+'</b> persones esperant');}
 function estat(){fetch('?q='+Q+'&estat=1',{cache:'no-store'}).then(function(r){return r.json()}).then(pinta).catch(function(){});}
 b.addEventListener('click',function(){
  b.disabled=true;
  fetch('?q='+Q,{method:'POST'}).then(function(r){return r.json()}).then(function(d){
   if(d.nou&&d.url){try{localStorage.setItem(CLAU,JSON.stringify({url:d.url}))}catch(e){}
    if(navigator.vibrate)navigator.vibrate(150);location.href=d.url;}
   else{b.disabled=false;pinta(d);if(d.tancat)estat();}
  }).catch(function(){b.disabled=false;});
 });
 estat();setInterval(function(){if(document.visibilityState==='visible')estat()},10000);
})();
</script>
</body></html>
