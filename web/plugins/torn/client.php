<?php
/**
 * Pàgina per als clients (un mòbil o tauleta en mode quiosc): ?token=<token de la configuració>.
 * GET: la pàgina. GET &estat=1: JSON de la cua. POST (fetch): agafa torn i imprimeix el tiquet.
 * A sota del botó hi ha un QR (canvia cada dia) cap a mobil.php, per agafar torn des del mòbil propi sense paper.
 * Si la dependenta apaga els torns (botó ON/OFF del giny), la pantalla mostra el QR d'alta de client (torn_url_alta).
 * El token identifica l'usuari de scale que té el connector actiu; l'empresa és la d'aquest usuari.
 */
require __DIR__ . '/../carrega.php';
require __DIR__ . '/torn.php';
$link = plugin_db();
$token = (string)($_GET['token'] ?? '');
[$usuari, $cfg] = torn_usuari($link, $token) ?? [null, []];
if($usuari === null) { http_response_code(404); exit; }
$emp = torn_empresa($link, $usuari);
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
// QR cap a la pàgina del mòbil del client; adreça pública configurable (la del quiosc pot ser de la xarxa local)
$base = rtrim(trim((string)($cfg['url_publica'] ?? '')), '/');
if($base === '') $base = 'https://' . preg_replace('/[^a-zA-Z0-9.:\-]/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
$qr_url = "$base/plugins/torn/mobil.php?q=" . torn_codi_dia($token);
require_once($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');
$qr_svg = (new \Endroid\QrCode\Writer\SvgWriter())->write(\Endroid\QrCode\QrCode::create($qr_url)->setSize(300)->setMargin(0))->getString();
$qr_svg = preg_replace('/^<\?xml[^>]*>\s*/', '', $qr_svg);
$alta_svg = (new \Endroid\QrCode\Writer\SvgWriter())->write(\Endroid\QrCode\QrCode::create(torn_url_alta($cfg, $base))->setSize(300)->setMargin(0))->getString();
$alta_svg = preg_replace('/^<\?xml[^>]*>\s*/', '', $alta_svg);
$actiu = torn_estat($link, $emp)['actiu'];
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
body{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;text-align:center}
.mode{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3vh}
body.tancat #modetorn,body:not(.tancat) #modealta{display:none}
#modealta h1{font-size:min(9vw,6vh);margin:0}
#modealta .t{font-size:min(5.5vw,3.6vh);line-height:1.35;max-width:90vw}
#modealta .c{background:#fff;padding:2vh;border-radius:14px;line-height:0}
#modealta svg{width:min(70vw,42vh);height:auto}
#esperant{font-size:min(7vw,5vh)}
#esperant b{font-size:1.4em}
button{width:min(70vw,44vh);height:min(70vw,44vh);border-radius:50%;border:0;background:#2f6fb3;color:#fff;
 font-size:min(11vw,8vh);font-weight:bold;box-shadow:0 10px 0 #1d4a7a;cursor:pointer;line-height:1.1}
button:active{transform:translateY(8px);box-shadow:0 2px 0 #1d4a7a}
button:disabled{background:#7a9cc4}
#avis{position:fixed;inset:0;display:none;flex-direction:column;align-items:center;justify-content:center;background:#2e8b57;color:#fff}
#avis .n{font-size:min(45vw,40vh);font-weight:bold;line-height:1}
#avis .t{font-size:min(8vw,6vh)}
#ara{font-size:min(5vw,3.5vh);opacity:.7}
#qr{display:flex;align-items:center;gap:4vw;text-align:left;font-size:min(4.5vw,3vh);line-height:1.25}
#qr .c{background:#fff;padding:1.2vh;border-radius:10px;line-height:0}
#qr svg{width:min(34vw,20vh);height:auto}
@media(prefers-color-scheme:dark){html,body{background:#1b1b1b;color:#eee}}
</style></head><body<?= $actiu ? '' : ' class="tancat"' ?>>
<div class="mode" id="modetorn">
<div id="esperant">&nbsp;</div>
<button id="b">Agafa<br>torn</button>
<div id="ara">&nbsp;</div>
<div id="qr"><div class="c"><?= $qr_svg ?></div><div>📱 <b>O agafa torn<br>amb el teu mòbil</b><br>sense paper 🌳</div></div>
</div>
<div class="mode" id="modealta">
<h1>Fes-te client!</h1>
<div class="c"><?= $alta_svg ?></div>
<div class="t">📱 Escaneja el codi i dona't d'alta.<br>Rebràs els tiquets per correu<br>i estalviarem paper 🌳</div>
</div>
<div id="avis"><div class="t">El teu torn és el</div><div class="n" id="avisn"></div><div class="t" id="avist"></div><div class="t" style="font-size:min(5vw,3.5vh);margin-top:3vh">Agafa el tiquet 🧾</div></div>
<script>
var T=<?= json_encode($token) ?>,b=document.getElementById('b');
function pinta(d){if(!d||typeof d.esperant!=='number')return;
 document.body.classList.toggle('tancat',d.actiu===false);
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
// el QR canvia cada dia: recarrega la pàgina quan canvia la data
var avui=new Date().toDateString();setInterval(function(){if(new Date().toDateString()!==avui)location.reload();},60000);
</script>
</body></html>
