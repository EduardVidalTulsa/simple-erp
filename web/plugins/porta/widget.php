<?php if(!isset($plugin_cfg)) exit; /* Giny de la porta: l'inclou scale_plugins_mostra() amb $plugin_cfg i $plugin_url */ ?>
<div style="display:flex;align-items:center;gap:8px;background:rgba(0,0,0,.65);color:#fff;padding:6px 10px;border-radius:30px;font:16px system-ui,sans-serif">
 <span id="portal"><?= htmlspecialchars($plugin_cfg['nom']) ?>…</span>
 <button id="portab" type="button" tabindex="-1" style="position:relative;width:76px;height:40px;border:0;border-radius:20px;background:#777;padding:0;cursor:pointer">
  <span id="portak" style="position:absolute;top:4px;left:4px;width:32px;height:32px;border-radius:50%;background:#fff;transition:left .2s"></span>
 </button>
</div>
<script>
(function(){
 var url=<?= json_encode($plugin_url . 'estat.php') ?>,nom=<?= json_encode($plugin_cfg['nom']) ?>;
 var b=document.getElementById('portab'),k=document.getElementById('portak'),l=document.getElementById('portal'),obert=null,ocupat=false;
 function pinta(d){
  if(d&&typeof d.obert==='boolean'){obert=d.obert;
   b.style.background=obert?'#2e8b57':'#b33a3a';k.style.left=obert?'40px':'4px';l.textContent=nom+(obert?' 🔓':' 🔒');}
  else{obert=null;b.style.background='#777';l.textContent=nom+' ⚠';}
 }
 function crida(cos){return fetch(url,cos?{method:'POST',body:cos}:{}).then(function(r){return r.json()}).then(pinta).catch(function(){pinta(null)});}
 // que el botó no prengui el focus al camp del lector de codis
 ['mousedown','pointerdown','touchstart'].forEach(function(ev){b.addEventListener(ev,function(e){e.preventDefault()},{passive:false})});
 function canvia(e){e.preventDefault();if(ocupat||obert===null)return;ocupat=true;
  var f=new FormData();f.append('a',obert?'tanca':'obre');crida(f).then(function(){ocupat=false});}
 b.addEventListener('click',canvia);b.addEventListener('touchend',canvia);
 crida();setInterval(function(){if(!ocupat)crida()},10000);
})();
</script>
