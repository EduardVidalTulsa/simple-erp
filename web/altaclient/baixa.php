<?php
// Baixa dels tiquets per correu. L'enllaç surt al peu de cada tiquet (scale/p.php) i va signat amb $pdf_token_secret,
// que és el mateix a la central i a les seus. El GET només mostra un botó; el POST fa la baixa (així els escàners
// d'enllaços dels correus no la fan). Deixa el correu com a no verificat (p.php ja no hi envia), ho apunta a
// Clients_consentiment i avisa de seguida les seus.
require __DIR__ . '/boot.php';
require_once __DIR__ . '/../scale/phpgeneral/destinataris.php';
if (!ac_es_central()) { http_response_code(404); exit; }
$db = ac_db();
$I = ac_i18n();
$lang = ac_lang();
$T = $I[$lang];
$c = (int)($_POST['c'] ?? $_GET['c'] ?? 0);
$e = trim((string)($_POST['e'] ?? $_GET['e'] ?? ''));
$s = (string)($_POST['s'] ?? $_GET['s'] ?? '');
$invalid = fn() => ac_pagina($lang, $T['baixa_title'], '<p>' . h($T['invalid']) . '</p>', 404);
if ($c <= 0 || !filter_var($e, FILTER_VALIDATE_EMAIL) || !hash_equals(tiquet_signa_baixa($c, $e), $s)) $invalid();

$cl = $db->query("SELECT email, email_verificat FROM Clients WHERE ID=$c")->fetch_assoc();
if (!$cl) $invalid();
$princ = strtolower(urldecode((string)$cl['email'])) === strtolower($e);
$st = $db->prepare("SELECT ID, verificat FROM Clients_email WHERE ID_Client=? AND LOWER(Value)=LOWER(?)");
$st->bind_param('is', $c, $e); $st->execute();
$ce = $st->get_result()->fetch_assoc();
if (!$princ && !$ce) $invalid();
$E = ['{EMAIL}' => $e];
$actiu = ($princ && (int)$cl['email_verificat'] === 1) || ($ce && (int)$ce['verificat'] === 1);
if (!$actiu) ac_pagina($lang, $T['baixa_title'], '<p>' . h(strtr($T['baixa_ja'], $E)) . '</p>');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ac_pagina($lang, $T['baixa_title'], '<p>' . h(strtr($T['baixa_text'], $E)) . '</p><form method="post" action="baixa.php">'
        . '<input type="hidden" name="c" value="' . $c . '"><input type="hidden" name="e" value="' . h($e) . '">'
        . '<input type="hidden" name="s" value="' . h($s) . '"><input type="hidden" name="lang" value="' . h($lang) . '">'
        . '<button type="submit">' . h($T['baixa_button']) . '</button></form>');
}

if ($princ) $db->query("UPDATE Clients SET email_verificat=0, email_verificat_data=NOW() WHERE ID=$c");
$st = $db->prepare("UPDATE Clients_email SET verificat=0, verificat_data=NOW() WHERE ID_Client=? AND LOWER(Value)=LOWER(?)");
$st->bind_param('is', $c, $e); $st->execute();
$cond = ac_condicions($db, $lang);
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? ''); $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
$acc = 'baixa';
$st = $db->prepare("INSERT INTO Clients_consentiment (ID_Client, email, accio, versio_condicions, hash_condicions, ip, user_agent) VALUES (?,?,?,?,?,?,?)");
$st->bind_param('issssss', $c, $e, $acc, $cond['versio'], $cond['hash'], $ip, $ua); $st->execute();
ac_push($db, $c);
ac_pagina($lang, $T['baixa_title'], '<p>' . h(strtr($T['baixa_ok'], $E)) . '</p>');
