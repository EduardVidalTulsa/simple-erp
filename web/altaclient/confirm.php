<?php
// Confirmació d'un correu. L'enllaç del correu (GET) només mostra un botó: el POST és el que consumeix el token (així els
// escàners d'enllaços dels correus no el gasten). Crea el client en confirmar l'adreça principal i avisa de seguida les seus.
require __DIR__ . '/boot.php';
if (!ac_es_central()) { http_response_code(404); exit; }
$db = ac_db();
ac_neteja($db);
$I = ac_i18n();
$t = (string)($_POST['t'] ?? $_GET['t'] ?? '');
$pag = function ($lang, $clau, $extra = '', $codi = 200) use ($I) { ac_pagina($lang, $I[$lang][$clau . '_title'] ?? $I[$lang]['title'], $extra ?: '<p>' . h($I[$lang][$clau]) . '</p>', $codi); };
$l0 = ac_lang();
if (!preg_match('/^[0-9a-f]{48}$/', $t)) ac_pagina($l0, $I[$l0]['title'], '<p>' . h($I[$l0]['invalid']) . '</p>', 404);

$st = $db->prepare("SELECT pe.ID AS pe_id, pe.email, pe.principal, pe.confirmat_data, p.* FROM Clients_pendents_email pe JOIN Clients_pendents p ON p.ID=pe.ID_pendent WHERE pe.token_hash=?");
$h = hash('sha256', $t); $st->bind_param('s', $h); $st->execute();
$r = $st->get_result()->fetch_assoc();
if (!$r) ac_pagina($l0, $I[$l0]['title'], '<p>' . h($I[$l0]['invalid']) . '</p>', 404);
$lang = isset($I[$r['idioma']]) ? $r['idioma'] : 'ca';
$T = $I[$lang];
if (strtotime($r['caduca']) < time()) ac_pagina($lang, $T['title'], '<p>' . h($T['expired']) . '</p>', 410);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ac_pagina($lang, $T['confirm_title'], '<p>' . h($T['confirm_text']) . '</p><form method="post" action="confirm.php"><input type="hidden" name="t" value="' . h($t) . '"><button type="submit">' . h($T['confirm_button']) . '</button></form>');
}

$ip = (string)($_SERVER['REMOTE_ADDR'] ?? ''); $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
if ((int)$r['principal'] === 1) {
    if ($r['estat'] === 'creat' || $r['confirmat_data']) ac_pagina($lang, $T['ok_title'], '<p>' . h($T['already']) . '</p>');
    [$id, $err] = ac_crea_client($db, $r, $ip, $ua);
    if ($err === 'already') ac_pagina($lang, $T['ok_title'], '<p>' . h($T['already']) . '</p>');
    if ($err) ac_pagina($lang, $T['title'], '<p>' . h($T['conflict']) . '</p>', 409);
    ac_push($db, $id);
    ac_pagina($lang, $T['ok_title'], '<p>' . h($T['ok_text']) . '</p>');
}
// correu addicional
if ($r['confirmat_data']) ac_pagina($lang, $T['ok_title'], '<p>' . h($T['already']) . '</p>');
$st = $db->prepare("UPDATE Clients_pendents_email SET confirmat_data=NOW() WHERE ID=? AND confirmat_data IS NULL"); $pe = (int)$r['pe_id']; $st->bind_param('i', $pe); $st->execute();
if ($r['estat'] === 'creat' && $r['ID_Client']) {
    $cid = (int)$r['ID_Client'];
    $st = $db->prepare("UPDATE Clients_email SET verificat=1, verificat_data=NOW() WHERE ID_Client=? AND Value=?"); $st->bind_param('is', $cid, $r['email']); $st->execute();
    $acc = 'confirmacio'; $st = $db->prepare("INSERT INTO Clients_consentiment (ID_Client, email, accio, versio_condicions, hash_condicions, ip, user_agent) VALUES (?,?,?,?,?,?,?)");
    $st->bind_param('issssss', $cid, $r['email'], $acc, $r['versio_condicions'], $r['hash_condicions'], $ip, $ua); $st->execute();
    ac_push($db, $cid);
    ac_pagina($lang, $T['ok_title'], '<p>' . h($T['ok_extra']) . '</p>');
}
ac_pagina($lang, $T['ok_title'], '<p>' . h($T['ok_extra_wait']) . '</p>');
