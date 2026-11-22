<?php if(!isset($plugin_cfg)) exit; /* Giny de torns de scale: l'inclou scale_plugins_mostra() amb $plugin_cfg i $plugin_url */ ?>
<div style="display:flex;align-items:center;gap:10px;background:rgba(0,0,0,.7);color:#fff;padding:6px 10px;border-radius:30px;font:16px system-ui,sans-serif">
 <button id="torno" type="button" tabindex="-1" title="Encén o apaga els torns (apagats: el quiosc mostra el QR d'alta de client)" style="height:46px;min-width:58px;border:0;border-radius:23px;background:#2e8b57;color:#fff;font:bold 15px system-ui,sans-serif;cursor:pointer">ON</button>
 <button id="tornm" type="button" tabindex="-1" style="width:46px;height:46px;border:0;border-radius:50%;background:#666;color:#fff;font-size:28px;line-height:1;cursor:pointer">−</button>
 <div id="tornc" style="text-align:center;min-width:86px;line-height:1.05">
  <div style="font-size:13px;opacity:.8">Torn</div>
  <div id="tornn" style="font-size:34px;font-weight:bold">–</div>
  <div id="torne" style="font-size:13px;opacity:.8">&nbsp;</div>
 </div>
 <button id="tornp" type="button" tabindex="-1" style="width:46px;height:46px;border:0;border-radius:50%;background:#2f6fb3;color:#fff;font-size:28px;line-height:1;cursor:pointer">+</button>
</div>
<script>
(function(){
 var url=<?= json_encode($plugin_url . 'estat.php') ?>,ocupat=false;
 var n=document.getElementById('tornn'),e=document.getElementById('torne'),o=document.getElementById('torno'),actiu=true;
 function pinta(d){if(d&&typeof d.actual==='number'){actiu=d.actiu!==false;
   n.textContent=d.actual||'–';e.textContent=!actiu?'apagat':(d.esperant==1?'1 esperant':d.esperant+' esperant');
   o.textContent=actiu?'ON':'OFF';o.style.background=actiu?'#2e8b57':'#8b2e2e';
   ['tornc','tornp','tornm'].forEach(function(i){document.getElementById(i).style.opacity=actiu?1:.45});}
  else{n.textContent='⚠';e.textContent='';}}
 function crida(a){var o={};if(a){var f=new FormData();f.append('a',a);o={method:'POST',body:f};}
  return fetch(url,o).then(function(r){return r.json()}).then(pinta).catch(function(){pinta(null)});}
 [['tornp','mes'],['tornm','menys'],['torno',function(){return actiu?'off':'on'}]].forEach(function(x){var b=document.getElementById(x[0]);
  // que els botons no prenguin el focus al camp del lector de codis
  ['mousedown','pointerdown','touchstart'].forEach(function(ev){b.addEventListener(ev,function(e){e.preventDefault()},{passive:false})});
  function fes(ev){ev.preventDefault();if(ocupat)return;ocupat=true;crida(typeof x[1]==='function'?x[1]():x[1]).then(function(){ocupat=false});}
  b.addEventListener('click',fes);b.addEventListener('touchend',fes);});
 crida();setInterval(function(){if(!ocupat)crida()},3000);
})();
</script>
