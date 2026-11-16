<?php
/**
 * Pàgina per als clients (un mòbil o tauleta en mode quiosc): ?token=<token de la configuració>.
 * GET: la pàgina. GET &estat=1: JSON de la cua. POST (fetch): agafa torn i imprimeix el tiquet.
 * El token identifica l'usuari de scale que té el connector actiu; l'empresa és la d'aquest usuari.
 */
require __DIR__ . '/../carrega.php';
require __DIR__ . '/torn.php';
$link = plugin_db();
$token = (string)($_GET['token'] ?? '');
$usuari = null;
if(strlen($token) >= 16 && plugin_actiu($link, 'torn')) {
	plugins_taules($link);
	foreach($link->query("SELECT username, CAST(config AS BINARY) AS config FROM plugin_usuari WHERE plugin='torn'")->fetch_all(MYSQLI_ASSOC) as $f) {
		$c = json_decode((string)$f['config'], true) ?: [];
		if(!empty($c['token']) && hash_equals((string)$c['token'], $token)) { $usuari = $f['username']; break; }
	}
}
if($usuari === null) { http_response_code(404); exit; }
$st = $link->prepare("SELECT ID_Empresa FROM users WHERE username=?");
$st->bind_param('s', $usuari);
$st->execute();
$emp = (int)($st->get_result()->fetch_row()[0] ?? 0);
if($emp <= 0) { http_response_code(404); exit; }
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex');

if($_SERVER['REQUEST_METHOD'] === 'POST') {
	header('Content-Type: application/json');
	$r = torn_agafa($link, $emp);
	if($r['nou']) {
		$r['imprès'] = torn_imprimeix($link, $emp, $r['torn'], $r['davant']);
		error_log("plugin torn: empresa $emp torn {$r['torn']} (davant {$r['davant']})" . ($r['imprès'] ? '' : ' SENSE IMPRIMIR'));
	}
	echo json_encode($r);
	exit;
}
if(isset($_GET['estat'])) {
	header('Content-Type: application/json');
	echo json_encode(torn_estat($link, $emp));
	exit;
}
$t = urlencode($token);
?><!doctype html>
<html lang="ca"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="robots" content="noindex">
<link rel="manifest" href="manifest.php?token=<?= $t ?>">
<link rel="icon" href="icona-192.png"><link rel="apple-touch-icon" href="icona-192.png">
<meta name="theme-color" content="#2f6fb3">
<meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes">
<title>Torns</title>
<style>
html,body{margin:0;height:100%;background:#f4f4f2;color:#222;font-family:system-ui,sans-serif;-webkit-user-select:none;user-select:none}
body{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4vh;padding:16px;box-sizing:border-box;text-align:center}
#esperant{font-size:min(7vw,5vh)}
#esperant b{font-size:1.4em}
button{width:min(80vw,60vh);height:min(80vw,60vh);border-radius:50%;border:0;background:#2f6fb3;color:#fff;
 font-size:min(11vw,8vh);font-weight:bold;box-shadow:0 10px 0 #1d4a7a;cursor:pointer;line-height:1.1}
button:active{transform:translateY(8px);box-shadow:0 2px 0 #1d4a7a}
button:disabled{background:#7a9cc4}
#avis{position:fixed;inset:0;display:none;flex-direction:column;align-items:center;justify-content:center;background:#2e8b57;color:#fff}
#avis .n{font-size:min(45vw,40vh);font-weight:bold;line-height:1}
#avis .t{font-size:min(8vw,6vh)}
#ara{font-size:min(5vw,3.5vh);opacity:.7}
@media(prefers-color-scheme:dark){html,body{background:#1b1b1b;color:#eee}}
</style></head><body>
<div id="esperant">&nbsp;</div>
<button id="b">Agafa<br>torn</button>
<div id="ara">&nbsp;</div>
<div id="avis"><div class="t">El teu torn és el</div><div class="n" id="avisn"></div><div class="t" id="avist"></div><div class="t" style="font-size:min(5vw,3.5vh);margin-top:3vh">Agafa el tiquet 🧾</div></div>
<script>
var T=<?= json_encode($token) ?>,b=document.getElementById('b');
function pinta(d){if(!d||typeof d.esperant!=='number')return;
 document.getElementById('esperant').innerHTML=d.esperant==0?'No hi ha ningú esperant':(d.esperant==1?'Hi ha <b>1</b> persona esperant':'Hi ha <b>'+d.esperant+'</b> persones esperant');
 document.getElementById('ara').textContent=d.actual?'Ara atenem el número '+d.actual:'';}
function estat(){fetch('?token='+encodeURIComponent(T)+'&estat=1',{cache:'no-store'}).then(function(r){return r.json()}).then(pinta).catch(function(){});}
b.addEventListener('click',function(){
 b.disabled=true;
 fetch('?token='+encodeURIComponent(T),{method:'POST'}).then(function(r){return r.json()}).then(function(d){
  pinta(d);
  if(d.nou){document.getElementById('avisn').textContent=d.torn;
   document.getElementById('avist').textContent=d.davant==0?'Ets el següent!':(d.davant==1?'Tens 1 persona davant':'Tens '+d.davant+' persones davant');
   var a=document.getElementById('avis');a.style.display='flex';setTimeout(function(){a.style.display='none';b.disabled=false;},6000);}
  else setTimeout(function(){b.disabled=false;},1500);
 }).catch(function(){b.disabled=false;});
});
// manté la pantalla encesa mentre la pàgina és oberta (cal HTTPS)
var wl=null;function desperta(){if('wakeLock' in navigator&&document.visibilityState==='visible')navigator.wakeLock.request('screen').then(function(w){wl=w}).catch(function(){});}
document.addEventListener('visibilitychange',desperta);desperta();
estat();setInterval(estat,3000);
</script>
</body></html>
