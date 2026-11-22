<?php
// Funcions de l'alta pública de clients (només a la central). Cap secret aquí: les claus són a keys.php ($ALTCHA_KEY, $em).
use PHPMailer\PHPMailer\PHPMailer;

const AC_MAXN = 50000;          // dificultat de la prova de treball (uns 1-2 s al navegador)
const AC_CADUCA_H = 48;         // hores de validesa de l'enllaç de confirmació

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Aquest servidor fa l'alta pública si té la clau del repte ($ALTCHA_KEY a keys.php) */
function ac_es_central() { return strlen(ac_clau()) >= 32; }

/** Adreça pública d'aquest servidor: $altaclient_url de keys.php (sense /altaclient) o l'adreça per on s'ha obert */
function ac_base() {
    global $altaclient_url;
    $u = preg_replace('#/altaclient/?$#', '', rtrim((string)($altaclient_url ?? ''), '/'));
    return $u !== '' ? $u : 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function ac_i18n() { static $t = null; return $t ??= require __DIR__ . '/i18n.php'; }

function ac_lang($forcat = null) {
    $t = ac_i18n();
    $l = strtolower(substr((string)($forcat ?? ($_GET['lang'] ?? $_POST['lang'] ?? '')), 0, 2));
    if (isset($t[$l])) return $l;
    $l = strtolower(substr((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 2));
    return isset($t[$l]) ? $l : 'ca';
}

function ac_db() {
    global $myserver, $myuser, $mypassword, $mydatabase;
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = new mysqli($myserver, $myuser, $mypassword, $mydatabase);
    if ($db->connect_error) { http_response_code(503); exit('bd'); }
    $db->set_charset('utf8mb4');
    return $db;
}

/** Versió vigent de les condicions en l'idioma demanat (si no n'hi ha, català): [versio, hash, idioma] */
function ac_condicions($db, $lang) {
    $st = $db->prepare("SELECT versio, hash, idioma FROM Condicions WHERE vigent=1 AND idioma IN (?, 'ca') ORDER BY idioma=? DESC, versio DESC LIMIT 1");
    $st->bind_param('ss', $lang, $lang);
    $st->execute();
    return $st->get_result()->fetch_assoc() ?: ['versio' => '', 'hash' => '', 'idioma' => 'ca'];
}

function ac_url_condicions($c) { return ac_base() . '/condicions/?lang=' . rawurlencode($c['idioma']) . '&v=' . rawurlencode($c['versio']); }

// ---------- prova de treball (protocol compatible amb ALTCHA, sense tercers) ----------
function ac_clau() { global $ALTCHA_KEY; return (string)$ALTCHA_KEY; }

function ac_repte() {
    $salt = bin2hex(random_bytes(12)) . '?expires=' . (time() + 1800);
    $n = random_int(0, AC_MAXN);
    $ch = hash('sha256', $salt . $n);
    return ['algorithm' => 'SHA-256', 'challenge' => $ch, 'maxnumber' => AC_MAXN, 'salt' => $salt, 'signature' => hash_hmac('sha256', $ch, ac_clau())];
}

function ac_repte_valid($db, $b64) {
    $p = json_decode((string)base64_decode((string)$b64, true), true);
    if (!is_array($p)) return false;
    foreach (['algorithm', 'challenge', 'number', 'salt', 'signature'] as $k) if (!isset($p[$k]) || is_array($p[$k])) return false;
    if ($p['algorithm'] !== 'SHA-256' || strlen(ac_clau()) < 32) return false;
    if (!hash_equals(hash_hmac('sha256', (string)$p['challenge'], ac_clau()), (string)$p['signature'])) return false;
    if (!preg_match('/^[0-9a-f]{24}\?expires=(\d+)$/', (string)$p['salt'], $m) || (int)$m[1] < time()) return false;
    if (!ctype_digit((string)$p['number']) || (int)$p['number'] > AC_MAXN) return false;
    if (!hash_equals(hash('sha256', $p['salt'] . (int)$p['number']), (string)$p['challenge'])) return false;
    $st = $db->prepare("INSERT IGNORE INTO Clients_altcha_usats (challenge) VALUES (?)");   // un repte només serveix un cop
    $c = (string)$p['challenge'];
    $st->bind_param('s', $c);
    $st->execute();
    return $st->affected_rows === 1;
}

// ---------- correu ----------
function ac_envia_correu($db, $to, $subject, $text) {
    if (($d = getenv('AC_MAIL_DIR'))) {   // només per a proves des de la línia d'ordres: desa el correu en lloc d'enviar-lo   // només per a proves: desa el correu en lloc d'enviar-lo
        file_put_contents($d . '/' . md5($to . microtime(true)) . '.eml', "To: $to\nSubject: $subject\n\n$text");
        return true;
    }
    global $em, $empresa_nom;
    require_once __DIR__ . '/../vendor/autoload.php';
    $emp = $db->query("SELECT email_public FROM Empresa WHERE ID=1")->fetch_assoc();
    $html = '<div style="font:16px/1.5 system-ui,sans-serif;max-width:36rem">'
          . preg_replace('~(https://[^\s<]+)~', '<a href="$1">$1</a>', nl2br(h($text))) . '</div>';
    try {
        $m = new PHPMailer(true);
        $m->isSMTP(); $m->Host = $em['host']; $m->SMTPAuth = true; $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $m->Port = 587; $m->Username = $em['user']; $m->Password = $em['passwd']; $m->CharSet = 'UTF-8';
        $m->Timeout = 12;
        $m->setFrom($emp['email_public'] ?: $em['user'], (string)($empresa_nom ?? ''));
        $m->addAddress($to);
        $m->isHTML(true); $m->Subject = $subject; $m->Body = $html; $m->AltBody = $text;
        $m->send();
        return true;
    } catch (Throwable $e) {
        error_log('altaclient correu: ' . $e->getMessage());
        return false;
    }
}

// ---------- pàgina ----------
function ac_pagina($lang, $titol, $cos, $codi = 200) {
    http_response_code($codi);
    header('Content-Type: text/html; charset=utf-8');
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
    header('X-Frame-Options: DENY'); header('Referrer-Policy: no-referrer'); header('Cache-Control: no-store');
    echo '<!doctype html><html lang="' . h($lang) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex"><title>' . h($titol) . '</title><link rel="stylesheet" href="style.css"></head><body><main>'
       . '<h1>' . h($titol) . '</h1>' . $cos . '</main></body></html>';
    exit;
}

// ---------- neteja (RGPD: les sol·licituds no confirmades s'esborren a les 48 h) ----------
function ac_neteja($db) {
    $db->query("DELETE pe FROM Clients_pendents_email pe JOIN Clients_pendents p ON p.ID=pe.ID_pendent WHERE p.caduca < NOW()");
    $db->query("DELETE FROM Clients_pendents WHERE caduca < NOW()");
    $db->query("DELETE FROM Clients_altcha_usats WHERE usat < NOW() - INTERVAL 2 HOUR");
}

// ---------- crea el client quan es confirma el correu principal ----------
/** @return array [ID_client|null, error|null] */
function ac_crea_client($db, $p, $ip, $ua) {
    $d = json_decode($p['dades'], true) ?: [];
    $u = fn($v) => urlencode((string)($v ?? ''));
    $db->begin_transaction();
    try {
        // bloqueja la sol·licitud: si es prem el botó dues vegades alhora, només el primer crea el client
        $q = $db->query("SELECT estat FROM Clients_pendents WHERE ID=" . (int)$p['ID'] . " FOR UPDATE")->fetch_row();
        if (!$q || $q[0] !== 'pendent') { $db->rollback(); return [null, 'already']; }
        $st = $db->prepare("SELECT ID FROM Clients WHERE Value=?"); $v = $u($d['value']); $st->bind_param('s', $v); $st->execute();
        if ($st->get_result()->num_rows) { $db->rollback(); return [null, 'conflict']; }
        foreach ((array)$d['telefons'] as $t) {
            $st = $db->prepare("SELECT ID FROM Clients_phone WHERE Value=?"); $st->bind_param('s', $t); $st->execute();
            if ($st->get_result()->num_rows) { $db->rollback(); return [null, 'conflict']; }
        }
        $st = $db->prepare("INSERT INTO Clients (Value, fiscal_address, Poblacio, NIF, bool_equivalency_charge, bool_Active, id_tarifa, bool_tax_detall, comercial_name, deliver_address, email, email_verificat, email_verificat_data)
                            VALUES (?,?,?,?,?,1,NULLIF(" . (int)($GLOBALS['altaclient_tarifa'] ?? 0) . ",0),?,?,?,?,1,NOW())");   // tarifa: $altaclient_tarifa de keys.php
        $a = [$u($d['value']), $u($d['fiscal_address']), $u($d['poblacio']), $u($d['nif']), (int)$d['bool_equivalency_charge'], (int)$d['bool_tax_detall'],
              $u($d['comercial_name']), $u($d['deliver_address']), $u($d['email'])];
        $st->bind_param('ssssiisss', ...$a); $st->execute();
        if (!$st->affected_rows) throw new Exception($st->error);
        $id = $db->insert_id;
        foreach ((array)$d['telefons'] as $t) {
            $st = $db->prepare("INSERT INTO Clients_phone (ID_Client, Value) VALUES (?,?)"); $st->bind_param('is', $id, $t); $st->execute();
        }
        $r = $db->query("SELECT email, confirmat_data FROM Clients_pendents_email WHERE ID_pendent=" . (int)$p['ID'] . " AND principal=0");
        foreach ($r->fetch_all(MYSQLI_ASSOC) as $x) {
            $ver = $x['confirmat_data'] ? 1 : 0;
            $st = $db->prepare("INSERT IGNORE INTO Clients_email (ID_Client, Value, verificat, verificat_data) VALUES (?,?,?,?)");
            $st->bind_param('isis', $id, $x['email'], $ver, $x['confirmat_data']); $st->execute();
        }
        $ca = $db->prepare("INSERT INTO Clients_consentiment (ID_Client, email, accio, data, versio_condicions, hash_condicions, ip, user_agent) VALUES (?,?,?,?,?,?,?,?)");
        $acc = 'alta'; $em = $d['email'];
        $ca->bind_param('isssssss', $id, $em, $acc, $p['creat'], $p['versio_condicions'], $p['hash_condicions'], $p['ip'], $p['user_agent']); $ca->execute();
        $acc = 'confirmacio'; $now = date('Y-m-d H:i:s');
        $ca->bind_param('isssssss', $id, $em, $acc, $now, $p['versio_condicions'], $p['hash_condicions'], $ip, $ua); $ca->execute();
        $db->query("UPDATE Clients_pendents_email SET confirmat_data=NOW() WHERE ID_pendent=" . (int)$p['ID'] . " AND principal=1");
        $st = $db->prepare("UPDATE Clients_pendents SET estat='creat', ID_Client=?, dades='{}' WHERE ID=?"); $pid = (int)$p['ID']; $st->bind_param('ii', $id, $pid); $st->execute();
        $db->commit();
        return [$id, null];
    } catch (Throwable $e) {
        $db->rollback();
        error_log('altaclient crea: ' . $e->getMessage());
        return [null, 'error'];
    }
}

/** Si hi ha diverses seus amb la vostra pròpia sincronització (sync/lib-push.php amb sync_push_client), la central les avisa de seguida. */
function ac_push($db, $id) {
    $lib = __DIR__ . '/../sync/lib-push.php';
    if (!is_file($lib)) return;
    try {
        require_once $lib;
        $res = sync_push_client($db, $id);
        foreach ($res as $node => $r) if (!$r['ok']) error_log("altaclient push a $node: " . $r['error']);
    } catch (Throwable $e) {
        error_log('altaclient push: ' . $e->getMessage());
    }
}
