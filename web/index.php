<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Portada: pública (entrar, àrea de clients, alta) i, amb la sessió del personal, accés a bàscula, preus i relacions.
// ?entra = formulari d'entrada de sempre (scale/login.php); ?surt = tanca la sessió.
$app = "root";
require("../keys.php");
$uri = ($_SERVER['SERVER_PORT'] == 80 ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'];
// enllaços antics /pdf/<uuid>.pdf que arribin aquí
if (preg_match('#^/pdf/([a-zA-Z0-9]+)\.pdf(?:\?.*)?$#', $_SERVER['REQUEST_URI'], $m)) { header("Location: {$uri}/pdf/?uuid={$m[1]}"); die; }
if (explode('/', $_SERVER['REQUEST_URI'])[1] === 'pdf') die;

if (isset($_GET['entra']) || !empty($_POST['user'])) {
    // scale/login.php vol la mida de la pantalla (w, h) i, si no la té, redirigeix a ./?w=…: la demanem aquí per no perdre ?entra
    if (isset($_GET['entra']) && !isset($_REQUEST['w'])) {
        echo '<!doctype html><script>location.replace("/?entra=1&w="+innerWidth+"&h="+innerHeight)</script>';
        die;
    }
    require("scale/login.php");
    if (!$login) { require('scale/tanca.php'); $link->close(); die; }
    header('Location: /', true, 303);
    die;
}
session_start();
if (isset($_GET['surt'])) { session_unset(); session_destroy(); header('Location: /', true, 303); die; }
$logat = !empty($_SESSION['login']);

$nom = '';
if ($logat) $nom = (string)($_SESSION['Empresa']['Value'] ?? '');
if ($nom === '') {
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli($myserver, $myuser, $mypassword, $mydatabase);
    if (!$db->connect_error) {
        $r = $db->query("SELECT Value FROM Empresa WHERE ID>0 ORDER BY seu_central DESC, ID LIMIT 1");
        if ($r && ($f = $r->fetch_row())) $nom = urldecode((string)$f[0]);
        $db->close();
    }
}
if ($nom === '') $nom = $empresa_nom ?? 'simple-erp';
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$usuari = $logat ? (string)($_SESSION['user'] ?? '') : '';
header('X-Frame-Options: DENY'); header('Referrer-Policy: same-origin');

// targetes: [enllaç, icona, [títols ca/es/fr/en], [textos ca/es/fr/en]]
$ICONA = [
 'entra' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
 'compte' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/>',
 'alta' => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M19 8v6M16 11h6"/>',
 'scale' => '<path d="M12 3v18"/><path d="M5 21h14"/><path d="M4 7h16"/><path d="M4 7l-3 7a4 4 0 0 0 6 0z"/><path d="M20 7l-3 7a4 4 0 0 0 6 0z"/>',
 'preus' => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
 'relacions' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>',
];
$publiques = [
 ['/?entra=1', 'entra', ['Entra', 'Entrar', 'Connexion', 'Sign in'],
  ["Personal de botiga: bàscula, preus i documents.", 'Personal de tienda: báscula, precios y documentos.', 'Personnel du magasin : balance, prix et documents.', 'Shop staff: scale, prices and documents.']],
 ['/client/', 'compte', ['El meu compte', 'Mi cuenta', 'Mon compte', 'My account'],
  ["Els teus tiquets i factures, amb el correu i sense contrasenya.", 'Tus tiques y facturas, con tu correo y sin contraseña.', 'Vos tickets et factures, avec votre e-mail et sans mot de passe.', 'Your receipts and invoices, with your e-mail and no password.']],
 ['/altaclient/', 'alta', ['Fes-te client', 'Hazte cliente', 'Devenez client', 'Become a client'],
  ["Rep els tiquets per correu i consulta'ls quan vulguis.", 'Recibe los tiques por correo y consúltalos cuando quieras.', 'Recevez vos tickets par e-mail et consultez-les quand vous voulez.', 'Get your receipts by e-mail and check them whenever you like.']],
];
$privades = [
 ['/scale/', 'scale', ['Bàscula', 'Báscula', 'Balance', 'Scale'],
  ['Venda a pes o per unitats, tiquets, etiquetes i caixa.', 'Venta a peso o por unidades, tiques, etiquetas y caja.', "Vente au poids ou à l'unité, tickets, étiquettes et caisse.", 'Selling by weight or unit, receipts, labels and cash.']],
 ['/preus/', 'preus', ['Preus', 'Precios', 'Prix', 'Prices'],
  ["Articles, tarifes, famílies, IVA i historial de preus.", 'Artículos, tarifas, familias, IVA e historial de precios.', 'Articles, tarifs, familles, TVA et historique des prix.', 'Products, price lists, families, VAT and price history.']],
 ['/relacions/', 'relacions', ['Relacions', 'Relaciones', 'Documents', 'Documents'],
  ['Albarans, factures, pressupostos i comandes en PDF.', 'Albaranes, facturas, presupuestos y pedidos en PDF.', 'Bons de livraison, factures, devis et commandes en PDF.', 'Delivery notes, invoices, quotes and orders as PDF.']],
];
$petits = [
 ['/altaclient/', ['Clients', 'Clientes', 'Clients', 'Clients']],
 ['/makevoucher/', ['Xecs regal', 'Cheques regalo', 'Chèques cadeaux', 'Gift vouchers']],
 ['/admin/', ['Connectors', 'Conectores', 'Modules', 'Plugins']],
 ['/?surt=1', ['Surt', 'Salir', 'Sortir', 'Sign out']],
];
$LL = ['ca', 'es', 'fr', 'en'];
$t = function (array $v) use ($LL, $h) { $o = ''; foreach ($LL as $i => $l) $o .= '<span data-l="' . $l . '">' . $h($v[$i]) . '</span>'; return $o; };
$targeta = function ($c) use ($ICONA, $t, $h) {
    return '<a class="card" href="' . $h($c[0]) . '"><span class="ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
        . $ICONA[$c[1]] . '</svg></span><span class="tx"><b>' . $t($c[2]) . '</b><small>' . $t($c[3]) . '</small></span><span class="fl" aria-hidden="true">→</span></a>';
};
?><!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($nom) ?></title>
<meta name="description" content="<?= $h($nom) ?>: tiquets i factures dels clients, alta de client i accés del personal.">
<meta name="theme-color" content="#7a4a22">
<style>
:root{--bg:#f7f1e8;--bg2:#efe4d3;--surface:rgba(255,255,255,.78);--text:#2b2118;--muted:#76685a;--line:#e4d7c4;--accent:#9a5b26;--accent2:#c98a46;--shadow:0 1px 2px rgba(60,40,20,.06),0 8px 24px rgba(60,40,20,.08)}
@media (prefers-color-scheme:dark){:root{--bg:#17130f;--bg2:#211a14;--surface:rgba(40,32,25,.82);--text:#efe6da;--muted:#a8998a;--line:#3a3027;--accent:#e0a465;--accent2:#b9783c;--shadow:0 1px 2px rgba(0,0,0,.3),0 8px 24px rgba(0,0,0,.35)}}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%}
body{background:radial-gradient(1200px 600px at 85% -10%,var(--bg2),transparent 60%),radial-gradient(900px 500px at -10% 110%,var(--bg2),transparent 55%),var(--bg);color:var(--text);font:16px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;min-height:100vh;display:flex;flex-direction:column}
.wrap{width:100%;max-width:980px;margin:0 auto;padding:0 16px}
header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 0}
.marca{display:flex;align-items:center;gap:10px;font-weight:700;letter-spacing:.01em;min-width:0}
@media(max-width:420px){.lang button{padding:3px 7px}}
.marca svg{width:30px;height:30px;color:var(--accent)}
.lang{display:flex;gap:4px;flex:none}
.lang button{border:1px solid var(--line);background:var(--surface);color:var(--muted);padding:3px 9px;border-radius:999px;cursor:pointer;font:inherit;font-size:.8rem}
.lang button[aria-pressed=true]{color:var(--text);border-color:var(--accent)}
.hero{padding:clamp(28px,7vw,72px) 0 clamp(18px,4vw,36px)}
.hero h1{font-size:clamp(2rem,6vw,3.3rem);line-height:1.08;margin:0 0 14px;letter-spacing:-.02em}
.hero h1 em{font-style:normal;background:linear-gradient(90deg,var(--accent),var(--accent2));-webkit-background-clip:text;background-clip:text;color:transparent}
.hero p{color:var(--muted);font-size:clamp(1rem,2.4vw,1.15rem);max-width:620px;margin:0}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin:10px 0 18px}
a.card{display:flex;align-items:center;gap:14px;text-decoration:none;color:inherit;background:var(--surface);backdrop-filter:blur(6px);border:1px solid var(--line);border-radius:16px;padding:18px;box-shadow:var(--shadow);transition:transform .15s,border-color .15s}
a.card:hover,a.card:focus-visible{transform:translateY(-2px);border-color:var(--accent);outline:none}
.ic{flex:none;width:48px;height:48px;border-radius:14px;display:grid;place-items:center;color:#fff;background:linear-gradient(135deg,var(--accent2),var(--accent))}
.ic svg{width:26px;height:26px}
.tx{flex:1;min-width:0}.tx b{display:block;font-size:1.08rem}.tx small{display:block;color:var(--muted);font-size:.92rem;line-height:1.4}
.fl{color:var(--muted);font-size:1.2rem;transition:transform .15s}a.card:hover .fl{transform:translateX(3px);color:var(--accent)}
.petits{display:flex;flex-wrap:wrap;gap:8px;margin:4px 0 0}
.petits a{border:1px solid var(--line);background:var(--surface);color:var(--text);text-decoration:none;padding:6px 14px;border-radius:999px;font-size:.92rem}
.petits a:hover{border-color:var(--accent)}
.hola{color:var(--muted);margin:0 0 12px}
footer{margin-top:auto;padding:30px 0 34px;color:var(--muted);font-size:.88rem;display:flex;flex-wrap:wrap;gap:6px 18px}
footer a{color:var(--muted)}
.galetes{flex-basis:100%;font-size:.8rem;opacity:.85}
[data-l]{display:none}
html[lang=ca] [data-l=ca],html[lang=es] [data-l=es],html[lang=fr] [data-l=fr],html[lang=en] [data-l=en]{display:revert}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <div class="marca"><svg viewBox="0 0 32 32" aria-hidden="true"><path fill="currentColor" d="M16 2c6 5 10 10.5 10 16.5C26 25 21.5 30 16 30S6 25 6 18.5C6 12.5 10 7 16 2z" opacity=".9"/><path d="M16 7c-1.5 6-1.5 15 0 20" stroke="#fff" stroke-width="1.4" fill="none" opacity=".55" stroke-linecap="round"/></svg><span><?= $h($nom) ?></span></div>
    <div class="lang" role="group" aria-label="Idioma"><button type="button" data-set="ca">CA</button><button type="button" data-set="es">ES</button><button type="button" data-set="fr">FR</button><button type="button" data-set="en">EN</button></div>
  </header>
<?php if (!$logat): ?>
  <section class="hero">
    <h1><span data-l="ca">Els teus tiquets, <em>sempre a mà</em></span><span data-l="es">Tus tiques, <em>siempre a mano</em></span><span data-l="fr">Vos tickets, <em>toujours à portée</em></span><span data-l="en">Your receipts, <em>always at hand</em></span></h1>
    <p><?= $t(["Consulta les factures i els tiquets de les teves compres, fes-te client per rebre'ls per correu o entra com a personal de botiga.", 'Consulta las facturas y los tiques de tus compras, hazte cliente para recibirlos por correo o entra como personal de tienda.', 'Consultez les factures et tickets de vos achats, devenez client pour les recevoir par e-mail ou connectez-vous en tant que personnel.', 'See the invoices and receipts of your purchases, become a client to get them by e-mail, or sign in as shop staff.']) ?></p>
  </section>
  <nav class="grid"><?php foreach ($publiques as $c) echo $targeta($c); ?></nav>
<?php else: ?>
  <section class="hero">
    <h1><span data-l="ca">Hola, <em><?= $h($usuari) ?></em></span><span data-l="es">Hola, <em><?= $h($usuari) ?></em></span><span data-l="fr">Bonjour, <em><?= $h($usuari) ?></em></span><span data-l="en">Hello, <em><?= $h($usuari) ?></em></span></h1>
    <p><?= $t(['On vols anar?', '¿Adónde quieres ir?', 'Où voulez-vous aller ?', 'Where would you like to go?']) ?></p>
  </section>
  <nav class="grid"><?php foreach ($privades as $c) echo $targeta($c); ?></nav>
  <nav class="petits"><?php foreach ($petits as $c) echo '<a href="' . $h($c[0]) . '">' . $t($c[1]) . '</a>'; ?></nav>
<?php endif; ?>
</div>
<div class="wrap">
  <footer>
    <a href="/condicions/"><?= $t(["Condicions d'ús i protecció de dades", 'Condiciones de uso y protección de datos', "Conditions d'utilisation et protection des données", 'Terms of use and privacy']) ?></a>
    <a href="https://github.com/EduardVidalTulsa/simple-erp">simple-erp</a>
    <span class="galetes"><?= $t(["Només fem servir galetes tècniques, necessàries per entrar i per recordar l'idioma; cap d'analítica ni de publicitat.", 'Solo usamos cookies técnicas, necesarias para entrar y recordar el idioma; ninguna de analítica ni de publicidad.', "Nous n'utilisons que des cookies techniques, nécessaires pour se connecter et retenir la langue ; aucune de mesure d'audience ni de publicité.", 'We only use technical cookies, needed to sign in and to remember the language; no analytics or advertising cookies.']) ?></span>
  </footer>
</div>
<script>
(function(){
  var L=['ca','es','fr','en'],pref=null;
  try{pref=localStorage.getItem('portada-lang')}catch(e){}
  if(L.indexOf(pref)<0){pref=(navigator.language||'ca').slice(0,2);if(L.indexOf(pref)<0)pref='ca'}
  function set(l){document.documentElement.lang=l;document.querySelectorAll('.lang button').forEach(function(b){b.setAttribute('aria-pressed',b.dataset.set===l?'true':'false')});
    document.querySelectorAll('a[href^="/client/"],a[href^="/altaclient/"]').forEach(function(a){a.href=a.getAttribute('href').split('?')[0]+'?lang='+l});
    try{localStorage.setItem('portada-lang',l)}catch(e){}}
  document.querySelectorAll('.lang button').forEach(function(b){b.addEventListener('click',function(){set(b.dataset.set)})});
  set(pref);
})();
</script>
</body>
</html>
