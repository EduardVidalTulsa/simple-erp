<?php
/**
 * Pàgina del torn d'un client (hi arriba des de mobil.php després d'agafar torn amb el QR).
 * ?q=<codi del dia>&n=<número intern>&h=<HH:MM>&s=<signatura>: no es lliga a cap client, tot va a l'enllaç.
 * Diu quin torn té, a quina hora l'ha agafat i, cada 10 s, quanta gent té davant (mobil.php?estat=1).
 */
require __DIR__ . '/../carrega.php';
require __DIR__ . '/torn.php';
$link = plugin_db();
$q = preg_replace('/[^a-f0-9]/', '', (string)($_GET['q'] ?? ''));
$n = (int)($_GET['n'] ?? 0);
$hora = preg_match('/^\d\d:\d\d$/', (string)($_GET['h'] ?? '')) ? $_GET['h'] : '';
$s = (string)($_GET['s'] ?? '');
[$usuari, $cfg] = torn_usuari($link, $q, true) ?? [null, []];
$valid = $usuari !== null && $n > 0 && $hora !== '' && hash_equals(torn_signa($cfg['token'], $n, $hora), $s);
$emp = $valid ? torn_empresa($link, $usuari) : 0;
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex');
$nom = '';
if($emp > 0) {
	$st = $link->prepare("SELECT Value FROM `Empresa` WHERE ID=?");
	$st->bind_param('i', $emp);
	$st->execute();
	$nom = (string)($st->get_result()->fetch_row()[0] ?? '');
	$inicial = torn_estat($link, $emp);
} else http_response_code(404);
?><!doctype html>
<html lang="ca"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<link rel="icon" href="icona-192.png"><link rel="apple-touch-icon" href="icona-192.png">
<meta name="theme-color" content="#2f6fb3">
<title><?= $emp > 0 ? 'Torn ' . torn_mostra($n) : 'Torn' ?></title>
<style>
html,body{margin:0;min-height:100%;background:#f4f4f2;color:#222;font-family:system-ui,sans-serif}
body{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:20px;padding:24px 16px;box-sizing:border-box;text-align:center;min-height:100vh}
h1{font-size:18px;font-weight:600;margin:0;opacity:.75}
.gran{font-size:min(38vw,170px);font-weight:bold;line-height:1;color:#2f6fb3}
.t{font-size:22px}
.t b{font-size:1.3em}
.petit{font-size:15px;opacity:.7}
.ara{background:#2e8b57;color:#fff;border-radius:16px;padding:18px 22px;font-size:26px;font-weight:bold}
.passat{background:#b36b2f;color:#fff;border-radius:16px;padding:14px 18px;font-size:18px}
.arbre{max-width:340px;background:#e6f2e6;color:#24572f;border-radius:16px;padding:14px 18px;font-size:16px;line-height:1.4}
.arbre .e{font-size:44px;line-height:1;display:block;margin-bottom:6px}
@media(prefers-color-scheme:dark){html,body{background:#1b1b1b;color:#eee}.gran{color:#6fa8e6}.arbre{background:#1f3324;color:#bfe3c6}}
</style></head><body>
<?php if($emp <= 0): ?>
<div class="t">Aquest torn ja no és vàlid.</div>
<div class="petit">Els torns es reinicien cada dia. Torna a escanejar el QR de la pantalla de la botiga.</div>
<?php else: ?>
<h1><?= htmlspecialchars($nom) ?></h1>
<div class="t">El teu torn és el</div>
<div class="gran"><?= torn_mostra($n) ?></div>
<div class="petit">L'has agafat a les <b><?= $hora ?></b></div>
<div class="t" id="davant">&nbsp;</div>
<div class="petit" id="atenem">&nbsp;</div>
<div class="arbre"><span class="e">🌳</span><b>Gràcies per salvar paper!</b><br>Deixa aquesta pàgina oberta: s'actualitza sola cada 10 segons.</div>
<div class="petit" id="act">&nbsp;</div>
<script>
(function(){
 var Q=<?= json_encode($q) ?>,N=<?= $n ?>,avisat=false;
 function $(i){return document.getElementById(i)}
 function pinta(d){
  if(!d||typeof d.actual_n!=='number')return;
  var davant=N-d.actual_n-1;
  if(davant==-1){$('davant').innerHTML='<div class="ara">És el teu torn! 🙋<br>Passa al taulell</div>';$('atenem').textContent='';
   if(!avisat&&navigator.vibrate){navigator.vibrate([300,150,300]);}avisat=true;}
  else if(davant<-1){$('davant').innerHTML='<div class="passat">El teu torn ja ha passat.<br>Digues-ho al taulell 😉</div>';$('atenem').textContent='Ara atenem el número '+d.actual;}
  else{$('davant').innerHTML=davant==0?'<b>Ets el següent!</b>':(davant==1?'Tens <b>1</b> persona davant':'Tens <b>'+davant+'</b> persones davant');
   $('atenem').textContent=d.actual?'Ara atenem el número '+d.actual:'Encara no hem començat a atendre';}
  var a=new Date();$('act').textContent='Actualitzat a les '+a.toLocaleTimeString('ca',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
 }
 function estat(){fetch('mobil.php?q='+Q+'&estat=1',{cache:'no-store'}).then(function(r){return r.json()}).then(pinta).catch(function(){});}
 pinta(<?= json_encode($inicial) ?>);
 setInterval(function(){if(document.visibilityState==='visible')estat()},10000);
 document.addEventListener('visibilitychange',function(){if(document.visibilityState==='visible')estat()});
})();
</script>
<?php endif; ?>
</body></html>
