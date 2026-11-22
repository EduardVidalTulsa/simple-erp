<?php
// Àrea de clients: entrar amb el correu (enllaç o codi de 6 xifres, sense contrasenya), veure tiquets i factures,
// telèfons, correus secundaris i baixa. Només a la central; les seus hi redirigeixen. Funcions a lib.php.
require __DIR__ . '/lib.php';
if (!ac_es_central()) {   // només el servidor de l'alta ($ALTCHA_KEY); els altres hi redirigeixen si hi ha $altaclient_url
    $q = isset($_GET['lang']) ? '?lang=' . rawurlencode(substr((string)$_GET['lang'], 0, 2)) : '';
    if (empty($altaclient_url) || !preg_match('#^https://#', $altaclient_url)) { http_response_code(404); exit; }
    header('Location: ' . ac_base() . '/client/' . $q, true, 302);
    exit;
}
session_name('erp_client_pre');
session_set_cookie_params(['path' => '/client/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
session_start();
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
header('X-Frame-Options: DENY'); header('Referrer-Policy: no-referrer'); header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$db = ac_db();
ac_neteja($db);
cc_neteja($db);
$lang = ac_lang();
$T = cc_i18n($lang);
$POST = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$a = $POST ? (string)($_POST['a'] ?? '') : '';
$BASE = ac_base() . '/client/';
if (empty($_SESSION['cc_csrf'])) $_SESSION['cc_csrf'] = bin2hex(random_bytes(16));

function cc_pagina($titol, $cos, $codi = 200, $s = null) {
    global $lang, $T;
    http_response_code($codi);
    header('Content-Type: text/html; charset=utf-8');
    $langs = '';
    foreach (['ca' => 'Català', 'es' => 'Castellano', 'fr' => 'Français', 'en' => 'English'] as $k => $n)
        if ($k !== $lang) $langs .= '<a href="./?lang=' . $k . '">' . h($n) . '</a>';
    $surt = $s ? '<form method="post" action="./" class="surt"><input type="hidden" name="a" value="surt"><input type="hidden" name="csrf" value="' . h($s['csrf']) . '">'
               . '<input type="hidden" name="lang" value="' . h($lang) . '"><button type="submit">' . h($T['c_logout']) . '</button></form>' : '';
    echo '<!doctype html><html lang="' . h($lang) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex"><title>' . h($titol) . '</title><link rel="stylesheet" href="style.css"></head><body><main>'
       . '<div class="cap"><div class="lang">' . $langs . '</div>' . $surt . '</div><h1>' . h($titol) . '</h1>' . $cos
       . '</main><script src="pow.js"></script></body></html>';
    exit;
}
function cc_p($txt, $cls = '') { return '<p' . ($cls ? ' class="' . $cls . '"' : '') . '>' . h($txt) . '</p>'; }
function cc_hidden(array $v) { $o = ''; foreach ($v as $k => $x) $o .= '<input type="hidden" name="' . h($k) . '" value="' . h($x) . '">'; return $o; }
function cc_torna($flash = null, $q = '') {
    global $lang;
    if ($flash) $_SESSION['cc_flash'] = $flash;
    header('Location: ./?lang=' . $lang . $q, true, 303);
    exit;
}

// ---------- enllaç per entrar (?t=): el GET només mostra el botó, el POST el gasta ----------
if (isset($_GET['t']) || $a === 'enllac') {
    $tok = (string)($_POST['t'] ?? $_GET['t'] ?? '');
    $t = cc_token($db, 'login', $tok);
    if (!$t) cc_pagina($T['c_title'], cc_p($T['c_expired']) . '<p><a href="./?lang=' . h($lang) . '">' . h($T['c_title']) . '</a></p>', 410);
    // el JavaScript (pow.js) prem el botó sol; sense JavaScript, el prem el client
    if (!$POST) cc_pagina($T['c_title'], cc_p($T['c_link_text']) . '<form method="post" action="./" id="auto">'
        . cc_hidden(['a' => 'enllac', 't' => $tok, 'lang' => $lang]) . '<button type="submit">' . h($T['c_enter']) . '</button></form>');
    if (!cc_token_usa($db, $t['ID'], 3) || !cc_clients($db, $t['email'])) cc_pagina($T['c_title'], cc_p($T['c_expired']), 410);
    cc_obre_sessio($db, $t['email']);
    unset($_SESSION['cc_login']);
    cc_torna();
}

// ---------- confirmació d'un correu secundari (?e=) ----------
if (isset($_GET['e']) || $a === 'confirma') {
    $tok = (string)($_POST['e'] ?? $_GET['e'] ?? '');
    $t = cc_token($db, 'email', $tok);
    if (!$t) cc_pagina($T['c_emails'], cc_p($T['c_expired']), 410);
    $nom = urldecode((string)($db->query("SELECT Value FROM Clients WHERE ID=" . (int)$t['ID_Client'])->fetch_row()[0] ?? ''));
    $E = ['{EMAIL}' => $t['email'], '{CLIENT}' => $nom];
    if (!$POST) cc_pagina($T['c_emails'], cc_p(strtr($T['c_email_confirm'], $E)) . '<form method="post" action="./">'
        . cc_hidden(['a' => 'confirma', 'e' => $tok, 'lang' => $lang]) . '<button type="submit">' . h($T['confirm_button']) . '</button></form>');
    if (!cc_token_usa($db, $t['ID'])) cc_pagina($T['c_emails'], cc_p($T['c_expired']), 410);
    $cid = (int)$t['ID_Client'];
    $st = $db->prepare("UPDATE Clients_email SET verificat=1, verificat_data=NOW() WHERE ID_Client=? AND LOWER(Value)=?");
    $st->bind_param('is', $cid, $t['email']); $st->execute();
    if ($st->affected_rows < 1) cc_pagina($T['c_emails'], cc_p($T['c_expired']), 410);   // l'han tret mentrestant
    $cond = ac_condicions($db, $lang); $ip = cc_ip(); $ua = cc_ua(); $acc = 'confirmacio';
    $st = $db->prepare("INSERT INTO Clients_consentiment (ID_Client, email, accio, versio_condicions, hash_condicions, ip, user_agent) VALUES (?,?,?,?,?,?,?)");
    $st->bind_param('issssss', $cid, $t['email'], $acc, $cond['versio'], $cond['hash'], $ip, $ua); $st->execute();
    ac_push($db, $cid);
    cc_pagina($T['c_emails'], cc_p(strtr($T['c_email_ok'], $E)));
}

$s = cc_sessio($db);

// ---------- sense sessió: demanar l'enllaç i el codi ----------
if (!$s) {
    $csrf_ok = $POST && hash_equals((string)$_SESSION['cc_csrf'], (string)($_POST['csrf'] ?? ''));
    $form_codi = function ($err = '') use ($T, $lang) {
        $email = (string)($_SESSION['cc_email'] ?? '');
        cc_pagina($T['c_sent_title'], ($err ? '<div class="err">' . h($err) . '</div>' : '') . cc_p(strtr($T['c_sent'], ['{EMAIL}' => $email]))
            . '<form method="post" action="./">' . cc_hidden(['a' => 'codi', 'csrf' => $_SESSION['cc_csrf'], 'lang' => $lang])
            . '<div class="f"><label for="codi">' . h($T['c_code']) . '</label><input type="text" id="codi" name="codi" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required autofocus></div>'
            . '<button type="submit">' . h($T['c_enter']) . '</button></form><p><a href="./?lang=' . h($lang) . '&amp;nou=1">' . h($T['c_other_email']) . '</a></p>');
    };
    if ($a === 'demana' && $csrf_ok) {
        $email = cc_norm_email(mb_substr((string)($_POST['email'] ?? ''), 0, 190));
        if (trim((string)($_POST['website'] ?? '')) !== '') { $_SESSION['cc_email'] = $email; $_SESSION['cc_login'] = 0; $form_codi(); }   // parany
        $err = '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = $T['err_email'];
        elseif (!ac_repte_valid($db, (string)($_POST['altcha'] ?? ''))) $err = $T['err_captcha'];
        elseif (cc_comptador($db, 'ip', cc_ip(), 'login') >= 10 || cc_comptador($db, 'email', $email, 'login') >= 5) $err = $T['c_too_many'];
        if (!$err) {
            $_SESSION['cc_email'] = $email;
            $_SESSION['cc_login'] = 0;
            if (cc_clients($db, $email)) {   // si no és de cap client, la pantalla és la mateixa (no diem qui és client)
                [$tok, $codi, $id] = cc_token_nou($db, 'login', $email, null, CC_LOGIN_MIN, $lang, true);
                $_SESSION['cc_login'] = $id;
                ac_envia_correu($db, $email, $T['c_mail_subject'], strtr($T['c_mail'], ['{LINK}' => $BASE . '?t=' . $tok . '&lang=' . $lang, '{CODE}' => $codi]));
            }
            $form_codi();
        }
        $_SESSION['cc_err'] = $err;
    }
    if ($a === 'codi' && $csrf_ok && isset($_SESSION['cc_login'])) {
        $id = (int)$_SESSION['cc_login'];
        $t = $id > 0 ? cc_codi_valid($db, $id, (string)($_POST['codi'] ?? '')) : null;
        if ($t && cc_token_usa($db, $t['ID']) && cc_clients($db, $t['email'])) {
            cc_obre_sessio($db, $t['email']);
            unset($_SESSION['cc_login'], $_SESSION['cc_email']);
            cc_torna();
        }
        $queden = $id > 0 ? (int)($db->query("SELECT intents<5 AND usat IS NULL AND caduca>NOW() FROM Clients_token WHERE ID=$id")->fetch_row()[0] ?? 0) : 1;
        if ($id === 0 && (int)($_SESSION['cc_falsos'] = ($_SESSION['cc_falsos'] ?? 0) + 1) >= 5) $queden = 0;   // igual que un correu de client
        if ($queden) $form_codi($T['c_bad_code_retry']);
        unset($_SESSION['cc_login'], $_SESSION['cc_falsos']);
        $_SESSION['cc_err'] = $T['c_bad_code'];
    }
    if (isset($_SESSION['cc_login']) && !isset($_GET['nou']) && !$POST) $form_codi();
    unset($_SESSION['cc_login']);
    $err = (string)($_SESSION['cc_err'] ?? ''); unset($_SESSION['cc_err']);
    cc_pagina($T['c_title'], ($err ? '<div class="err">' . h($err) . '</div>' : '') . cc_p($T['c_intro'], 'intro')
        . '<form method="post" action="./" id="entra" data-verifying="' . h($T['verifying']) . '" data-ready="' . h($T['ready']) . '" data-error="' . h($T['pow_error']) . '">'
        . cc_hidden(['a' => 'demana', 'csrf' => $_SESSION['cc_csrf'], 'lang' => $lang, 'altcha' => ''])
        . '<div class="hp"><label for="website">Web</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>'
        . '<div class="f"><label for="email">' . h($T['c_email']) . '</label><input type="email" id="email" name="email" maxlength="190" autocomplete="email" required autofocus></div>'
        . '<div id="powstatus"></div><button type="submit">' . h($T['c_send']) . '</button></form>'
        . '<p class="peu"><a href="../altaclient/?lang=' . h($lang) . '">' . h($T['title']) . '</a></p>');
}

// ---------- amb sessió ----------
if ($POST && !hash_equals($s['csrf'], (string)($_POST['csrf'] ?? ''))) cc_torna(['err', $T['err_csrf']]);
if ($a === 'surt') { cc_tanca_sessio($db); cc_torna(); }
if ($a === 'tria') { cc_tria_client($db, $s, (int)($_POST['id'] ?? 0)); cc_torna(); }
$csrf = cc_hidden(['csrf' => $s['csrf'], 'lang' => $lang]);

if (isset($_GET['canvia']) || !$s['ID_Client']) {   // el correu és de més d'un client
    $o = '';
    foreach ($s['clients'] as $id => $nom) $o .= '<form method="post" action="./" class="tria">' . $csrf . cc_hidden(['a' => 'tria', 'id' => $id]) . '<button type="submit">' . h($nom) . '</button></form>';
    cc_pagina($T['c_title'], cc_p($T['c_choose']) . $o, 200, $s);
}
$cid = (int)$s['ID_Client'];
$client = $db->query("SELECT ID, Value, NIF, email, email_verificat, fiscal_address, Poblacio, comercial_name, deliver_address, bool_tax_detall, bool_equivalency_charge FROM Clients WHERE ID=$cid")->fetch_assoc();
$principal = cc_norm_email(urldecode((string)$client['email']));

// baixa: el correu amb què ha entrat deixa de rebre tiquets (a tots els seus clients) i se li tanquen les sessions
if ($a === 'baixa') {
    $e = $s['email'];
    $cond = ac_condicions($db, $lang); $ip = cc_ip(); $ua = cc_ua(); $acc = 'baixa';
    foreach (array_keys($s['clients']) as $id) {
        $id = (int)$id;
        $c = $db->query("SELECT email FROM Clients WHERE ID=$id")->fetch_row();
        if (cc_norm_email(urldecode((string)$c[0])) === $e) $db->query("UPDATE Clients SET email_verificat=0, email_verificat_data=NOW() WHERE ID=$id");
        $st = $db->prepare("UPDATE Clients_email SET verificat=0, verificat_data=NOW() WHERE ID_Client=? AND LOWER(Value)=?");
        $st->bind_param('is', $id, $e); $st->execute();
        $st = $db->prepare("INSERT INTO Clients_consentiment (ID_Client, email, accio, versio_condicions, hash_condicions, ip, user_agent) VALUES (?,?,?,?,?,?,?)");
        $st->bind_param('issssss', $id, $e, $acc, $cond['versio'], $cond['hash'], $ip, $ua); $st->execute();
        ac_push($db, $id);
    }
    cc_tanca_sessions_email($db, $e);
    cc_galeta('', 1);
    cc_pagina($T['c_baixa'], cc_p(strtr($T['c_baixa_ok'], ['{EMAIL}' => $e])));
}
if (isset($_GET['baixa'])) {
    cc_pagina($T['c_baixa'], cc_p(strtr($T['c_baixa_text'], ['{EMAIL}' => $s['email']])) . '<form method="post" action="./">' . $csrf . cc_hidden(['a' => 'baixa'])
        . '<button type="submit" class="perill">' . h($T['c_baixa_button']) . '</button></form><p><a href="./?lang=' . h($lang) . '">' . h($T['c_title']) . '</a></p>', 200, $s);
}

$telefons = $db->query("SELECT ID, Value FROM Clients_phone WHERE ID_Client=$cid ORDER BY prefered DESC, ID")->fetch_all(MYSQLI_ASSOC);
$correus = $db->query("SELECT ID, Value, verificat FROM Clients_email WHERE ID_Client=$cid ORDER BY ID")->fetch_all(MYSQLI_ASSOC);

// dades del client (tot menys el NIF i el correu principal, que es canvien al taulell)
$CAMPS = ['Value' => ['name_label', true], 'comercial_name' => ['comercial_label', false], 'fiscal_address' => ['fiscal_label', true],
          'Poblacio' => ['poblacio_label', true], 'deliver_address' => ['deliver_label', false]];
if ($a === 'dades') {
    $v = [];
    foreach ($CAMPS as $c => [$lbl, $obl]) {
        $v[$c] = mb_substr(trim(strip_tags((string)($_POST[$c] ?? ''))), 0, 255);
        if ($obl && $v[$c] === '') cc_torna(['err', $T['err_required']]);
    }
    $u = array_map('urlencode', $v);   // a Clients els textos van amb urlencode()
    $st = $db->prepare("SELECT ID FROM Clients WHERE Value=? AND ID<>?"); $st->bind_param('si', $u['Value'], $cid); $st->execute();
    if ($st->get_result()->num_rows) cc_torna(['err', $T['err_name']]);
    $td = isset($_POST['bool_tax_detall']) ? 1 : 0; $eq = isset($_POST['bool_equivalency_charge']) ? 1 : 0;
    $st = $db->prepare("UPDATE Clients SET Value=?, comercial_name=?, fiscal_address=?, Poblacio=?, deliver_address=?, bool_tax_detall=?, bool_equivalency_charge=? WHERE ID=?");
    $st->bind_param('sssssiii', $u['Value'], $u['comercial_name'], $u['fiscal_address'], $u['Poblacio'], $u['deliver_address'], $td, $eq, $cid);
    if (!$st->execute()) cc_torna(['err', $T['c_error']]);
    if ($st->affected_rows) { ac_push($db, $cid); error_log("client: el client $cid ha canviat les seves dades des de /client/"); }
    cc_torna(['ok', $T['c_saved']]);
}
if ($a === 'tel_afegeix') {
    $t = cc_norm_tel(mb_substr((string)($_POST['tel'] ?? ''), 0, 30));
    if (count($telefons) >= CC_MAX_TEL) cc_torna(['err', $T['c_phone_max']]);
    if (!preg_match('/^\+?\d{6,15}$/', $t)) cc_torna(['err', $T['err_phone']]);
    $st = $db->prepare("SELECT ID_Client FROM Clients_phone WHERE Value=?"); $st->bind_param('s', $t); $st->execute();
    $ja = $st->get_result()->fetch_row();
    if ($ja) cc_torna(['err', (int)$ja[0] === $cid ? $T['c_phone_dup'] : $T['c_phone_taken']]);
    $st = $db->prepare("INSERT INTO Clients_phone (ID_Client, Value) VALUES (?,?)"); $st->bind_param('is', $cid, $t);
    if (!$st->execute()) cc_torna(['err', $T['c_error']]);
    ac_push($db, $cid);
    cc_torna(['ok', $T['c_saved']]);
}
if ($a === 'tel_treu') {
    if (count($telefons) <= 1) cc_torna(['err', $T['c_phone_last']]);
    $st = $db->prepare("DELETE FROM Clients_phone WHERE ID=? AND ID_Client=?"); $tid = (int)($_POST['id'] ?? 0); $st->bind_param('ii', $tid, $cid); $st->execute();
    if ($st->affected_rows) ac_push($db, $cid);
    cc_torna(['ok', $T['c_saved']]);
}
if ($a === 'mail_afegeix') {
    $e = cc_norm_email(mb_substr((string)($_POST['email'] ?? ''), 0, 190));
    if (!filter_var($e, FILTER_VALIDATE_EMAIL)) cc_torna(['err', $T['err_email']]);
    if ($e === $principal || in_array($e, array_map(fn($x) => cc_norm_email($x['Value']), $correus), true)) cc_torna(['err', $T['c_email_exists']]);
    if (count($correus) >= CC_MAX_EMAILS) cc_torna(['err', $T['c_email_max']]);
    if (cc_comptador($db, 'ID_Client', (string)$cid, 'email') >= 5 || cc_comptador($db, 'email', $e, 'email') >= 3) cc_torna(['err', $T['c_too_many']]);
    $st = $db->prepare("INSERT INTO Clients_email (ID_Client, Value, verificat) VALUES (?,?,0)"); $st->bind_param('is', $cid, $e);
    if (!$st->execute()) cc_torna(['err', $T['c_error']]);
    [$tok] = cc_token_nou($db, 'email', $e, $cid, CC_EMAIL_H * 60, $lang, false);
    $nom = urldecode((string)$client['Value']);
    ac_envia_correu($db, $e, $T['c_email_mail_subject'], strtr($T['c_email_mail'], ['{CLIENT}' => $nom, '{LINK}' => $BASE . '?e=' . $tok . '&lang=' . $lang]));
    cc_torna(['ok', strtr($T['c_email_sent'], ['{EMAIL}' => $e])]);
}
if ($a === 'mail_treu') {
    $mid = (int)($_POST['id'] ?? 0);
    $m = null; foreach ($correus as $x) if ((int)$x['ID'] === $mid) $m = $x;
    if ($m) {
        $db->query("DELETE FROM Clients_email WHERE ID=$mid AND ID_Client=$cid");
        $st = $db->prepare("DELETE FROM Clients_token WHERE tipus='email' AND ID_Client=? AND email=?"); $st->bind_param('is', $cid, $m['Value']); $st->execute();
        if ((int)$m['verificat'] === 1) {
            $cond = ac_condicions($db, $lang); $ip = cc_ip(); $ua = cc_ua(); $acc = 'baixa';
            $st = $db->prepare("INSERT INTO Clients_consentiment (ID_Client, email, accio, versio_condicions, hash_condicions, ip, user_agent) VALUES (?,?,?,?,?,?,?)");
            $st->bind_param('issssss', $cid, $m['Value'], $acc, $cond['versio'], $cond['hash'], $ip, $ua); $st->execute();
        }
        ac_push($db, $cid);
    }
    cc_torna(['ok', $T['c_saved']]);
}

// ---------- panell ----------
$flash = $_SESSION['cc_flash'] ?? null; unset($_SESSION['cc_flash']);
$o = $flash ? '<div class="' . ($flash[0] === 'ok' ? 'ok' : 'err') . '">' . h($flash[1]) . '</div>' : '';
$o .= '<p class="client"><b>' . h(urldecode((string)$client['Value'])) . '</b>' . ($client['NIF'] !== '' ? ' · ' . h(urldecode((string)$client['NIF'])) : '')
    . (count($s['clients']) > 1 ? ' · <a href="./?lang=' . h($lang) . '&amp;canvia=1">' . h($T['c_change_client']) . '</a>' : '') . '</p>';

// dades
$o .= '<details class="dades"' . (isset($_GET['dades']) ? ' open' : '') . '><summary>' . h($T['c_data']) . '</summary><form method="post" action="./">' . $csrf . cc_hidden(['a' => 'dades']);
foreach ($CAMPS as $c => [$lbl, $obl])
    $o .= '<div class="f"><label for="' . $c . '">' . h($T[$lbl]) . ($obl ? ' *' : '') . '</label><input type="text" id="' . $c . '" name="' . $c . '" maxlength="255" value="'
        . h(urldecode((string)$client[$c])) . '"' . ($obl ? ' required' : '') . '></div>';
$o .= '<div class="f"><label>' . h($T['nif_label']) . '</label><input type="text" value="' . h(urldecode((string)$client['NIF'])) . '" disabled></div>'
    . '<div class="chk"><input type="checkbox" id="tax" name="bool_tax_detall"' . ((int)$client['bool_tax_detall'] ? ' checked' : '') . '><label for="tax">' . h($T['tax_label']) . '</label></div>'
    . '<div class="chk"><input type="checkbox" id="charge" name="bool_equivalency_charge"' . ((int)$client['bool_equivalency_charge'] ? ' checked' : '') . '><label for="charge">' . h($T['charge_label']) . '</label></div>'
    . cc_p($T['c_nif_note'], 'nota') . '<button type="submit">' . h($T['c_save']) . '</button></form></details>';

// documents: data, document i botiga a dalt; base, IVA per tipus i total a sota
$disp = cc_tipus_client($db, $cid);
$filtre = (int)($_GET['tipus'] ?? 0);
if (!isset($disp[$filtre])) $filtre = 0;
$anys = cc_anys_client($db, $cid, $filtre);
$fany = (int)($_GET['any'] ?? 0);
if (!isset($anys[$fany])) $fany = 0;
$qf = ($filtre ? '&amp;tipus=' . $filtre : '') . ($fany ? '&amp;any=' . $fany : '');
$docs = cc_documents($db, $cid, 0, $filtre, $fany);   // tots els dels últims CC_ANYS anys, agrupats per any
$emp = cc_empreses($db);
$eur = fn($x) => h(number_format((float)$x, 2, ',', '.') . ' €');
$pc = fn($x) => h(rtrim(rtrim(number_format((float)$x, 2, ',', ''), '0'), ',') . ' %');
$o .= '<h2>' . h($T['c_docs']) . '</h2>';
if (count($disp) > 1) {
    $qa = $fany ? '&amp;any=' . $fany : '';
    $o .= '<p class="filtre"><a href="./?lang=' . h($lang) . $qa . '"' . ($filtre ? '' : ' class="actiu"') . '>' . h($T['c_all']) . '</a>';
    foreach ($disp as $tp => $n) $o .= '<a href="./?lang=' . h($lang) . '&amp;tipus=' . $tp . $qa . '"' . ($filtre === $tp ? ' class="actiu"' : '') . '>' . h(($T['t' . $tp] ?? $tp) . ' (' . $n . ')') . '</a>';
    $o .= '</p>';
}
if (count($anys) > 1) {
    $qt = $filtre ? '&amp;tipus=' . $filtre : '';
    $o .= '<p class="filtre anys"><a href="./?lang=' . h($lang) . $qt . '"' . ($fany ? '' : ' class="actiu"') . '>' . h($T['c_all_years']) . '</a>';
    foreach ($anys as $y => $n) $o .= '<a href="./?lang=' . h($lang) . $qt . '&amp;any=' . $y . '"' . ($fany === $y ? ' class="actiu"' : '') . '>' . $y . ' (' . $n . ')</a>';
    $o .= '</p>';
}
if (!$docs) $o .= cc_p($T['c_no_docs'], 'intro');
else {
    $resum = cc_resum_anys($db, $cid, $fany);
    $pinta_resum = function ($a) use ($resum, $T, $eur, $pc) {
        $y = $resum[(int)$a] ?? null;
        if (!$y || !$y['n']) return '';
        $o = '<div class="resum"><div class="t">' . h(strtr($T['c_summary'], ['{ANY}' => (int)$a])) . '</div><div class="l2"><span>' . h($T['c_base']) . ' ' . $eur($y['base']) . '</span>';
        foreach ($y['iva'] as $p => $q) $o .= '<span>' . h($T['c_iva']) . ' ' . $pc($p) . ' ' . $eur($q) . '</span>';
        foreach ($y['re'] as $p => $q) $o .= '<span>' . h($T['c_re']) . ' ' . $pc($p) . ' ' . $eur($q) . '</span>';
        return $o . '<span class="tot">' . h($T['c_total']) . ' ' . $eur($y['total']) . '</span></div><div class="nota">'
            . h($T[$y['sense_rel2rel'] ? 'c_summary_note_old' : 'c_summary_note']) . '</div></div>';
    };
    $any_act = null;
    foreach ($docs as $d) {
        if ($d['any'] !== $any_act) {
            $o .= ($any_act === null ? '' : '</ul>' . $pinta_resum($any_act)) . '<h3 class="any">' . (int)$d['any'] . '</h3><ul class="docs">';
            $any_act = $d['any'];
        }
        $tipus = $T['t' . (int)$d['ID_tipus_relacio']] ?? '';
        $x = cc_detall($db, $d);
        $imp = '';
        if ($x['total'] != 0) {
            $imp = '<span>' . h($T['c_base']) . ' ' . $eur($x['base']) . '</span>';
            foreach ($x['iva'] as $p => [$b, $q]) $imp .= '<span>' . h($T['c_iva']) . ' ' . $pc($p) . ' ' . $eur($q) . '</span>';
            foreach ($x['re'] as $p => $q) $imp .= '<span>' . h($T['c_re']) . ' ' . $pc($p) . ' ' . $eur($q) . '</span>';
            $imp .= '<span class="tot">' . h($T['c_total']) . ' ' . $eur($x['total']) . '</span>';
        }
        $o .= '<li><div class="l1"><span class="data">' . h(date('d/m/Y', strtotime($d['data']))) . '</span> <a href="' . h(cc_enllac_pdf($d['uuid'])) . '" target="_blank" rel="noopener">'
            . h($tipus . ' ' . (int)$d['empresa'] . '.' . (int)$d['ID']) . '</a> <span class="botiga">' . h($emp[(int)$d['empresa']] ?? '') . '</span></div>'
            . ($imp ? '<div class="l2">' . $imp . '</div>' : '') . '</li>';
    }
    $o .= '</ul>' . $pinta_resum($any_act) . cc_p(strtr($T['c_pdf_note'], []) . ' ' . strtr($T['c_years'], ['{ANY}' => (int)date('Y') - CC_ANYS]), 'nota');
}

// telèfons
$o .= '<h2>' . h($T['c_phones']) . '</h2><ul class="llista">';
foreach ($telefons as $t) {
    $o .= '<li><span>' . h($t['Value']) . '</span>' . (count($telefons) > 1 ? '<form method="post" action="./">' . $csrf . cc_hidden(['a' => 'tel_treu', 'id' => $t['ID']])
        . '<button type="submit">' . h($T['c_remove']) . '</button></form>' : '') . '</li>';
}
$o .= '</ul>';
if (count($telefons) < CC_MAX_TEL) $o .= '<form method="post" action="./" class="row">' . $csrf . cc_hidden(['a' => 'tel_afegeix'])
    . '<input type="tel" name="tel" maxlength="30" placeholder="' . h($T['c_phone_new']) . '" aria-label="' . h($T['c_phone_new']) . '" required><button type="submit">' . h($T['c_add']) . '</button></form>';

// correus
$o .= '<h2>' . h($T['c_emails']) . '</h2><ul class="llista">';
if ($principal !== '') $o .= '<li><span>' . h($principal) . ' <small>(' . h($T['c_main']) . ((int)$client['email_verificat'] !== 1 ? ', ' . h($T['c_pending']) : '') . ')</small></span></li>';
foreach ($correus as $m) {
    $o .= '<li><span>' . h($m['Value']) . ((int)$m['verificat'] !== 1 ? ' <small>(' . h($T['c_pending']) . ')</small>' : '') . '</span>'
        . '<form method="post" action="./">' . $csrf . cc_hidden(['a' => 'mail_treu', 'id' => $m['ID']]) . '<button type="submit">' . h($T['c_remove']) . '</button></form></li>';
}
$o .= '</ul>' . cc_p($T['c_main_note'], 'nota');
if (count($correus) < CC_MAX_EMAILS) $o .= '<form method="post" action="./" class="row">' . $csrf . cc_hidden(['a' => 'mail_afegeix'])
    . '<input type="email" name="email" maxlength="190" placeholder="' . h($T['c_email_new']) . '" aria-label="' . h($T['c_email_new']) . '" required><button type="submit">' . h($T['c_add']) . '</button></form>';

$o .= '<p class="peu"><a href="./?lang=' . h($lang) . '&amp;baixa=1" class="perill">' . h($T['c_baixa']) . '</a> · <a href="' . h(ac_url_condicions(ac_condicions($db, $lang))) . '">' . h($T['c_terms']) . '</a></p>';
cc_pagina($T['c_title'], $o, 200, $s);
