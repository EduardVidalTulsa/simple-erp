<?php
// Àrea de clients (/client/): entrar amb el correu (enllaç o codi d'un sol ús, sense contrasenya), veure els tiquets i
// factures, canviar telèfons, gestionar els correus secundaris i donar-se de baixa. Només a la central, com l'alta.
// Fa servir les funcions de l'alta (../altaclient/core.php): BD, correu, idiomes, prova de treball, consentiment, push.
require __DIR__ . '/../altaclient/boot.php';

const CC_LOGIN_MIN = 15;         // minuts de validesa de l'enllaç i el codi per entrar
const CC_EMAIL_H = 48;           // hores per confirmar un correu secundari
const CC_SESSIO_DIES = 30;
const CC_GALETA = 'erp_client';
const CC_MAX_TEL = 5, CC_MAX_EMAILS = 3;
const CC_TIPUS = [2, 3, 4, 5, 7, 10];   // albarà, factura, proforma, pressupost, comanda, tiquet

function cc_i18n($lang) {
    static $t = null;
    $t ??= require __DIR__ . '/i18n.php';
    return ac_i18n()[$lang] + $t[$lang];
}

function cc_norm_email($e) { return strtolower(trim((string)$e)); }

function cc_norm_tel($t) {
    $t = (string)$t;
    if (strpos($t, '+') !== false && strpos($t, '+') !== 0) $t = str_replace('+', '', $t);   // el + només al principi
    return preg_replace('/[^0-9+]/', '', $t);
}

/** Clients amb aquest correu verificat (principal o secundari): [ID => nom] */
function cc_clients($db, $email) {
    $e = cc_norm_email($email);
    if ($e === '') return [];
    $u = strtolower(urlencode($e));
    $st = $db->prepare("SELECT ID, Value FROM Clients WHERE email_verificat=1 AND bool_Active=1 AND (LOWER(email)=? OR LOWER(email)=?)
                        UNION SELECT c.ID, c.Value FROM Clients_email ce JOIN Clients c ON c.ID=ce.ID_Client
                        WHERE ce.verificat=1 AND c.bool_Active=1 AND LOWER(ce.Value)=? ORDER BY ID");
    $st->bind_param('sss', $u, $e, $e);
    $st->execute();
    $r = [];
    foreach ($st->get_result()->fetch_all() as $f) $r[(int)$f[0]] = urldecode((string)$f[1]);
    return $r;
}

function cc_ip() { return (string)($_SERVER['REMOTE_ADDR'] ?? ''); }
function cc_ua() { return mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255); }

/** Quants tokens d'aquest tipus s'han demanat l'última hora per a aquest camp */
function cc_comptador($db, $camp, $valor, $tipus) {
    $st = $db->prepare("SELECT COUNT(*) FROM Clients_token WHERE tipus=? AND `$camp`=? AND creat > NOW() - INTERVAL 1 HOUR");
    $st->bind_param('ss', $tipus, $valor);
    $st->execute();
    return (int)$st->get_result()->fetch_row()[0];
}

/** Crea un token d'un sol ús; torna [token de l'enllaç, codi de 6 xifres o null, ID] */
function cc_token_nou($db, $tipus, $email, $ID_Client, $minuts, $lang, $amb_codi) {
    $tok = bin2hex(random_bytes(24));
    $codi = $amb_codi ? str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT) : null;
    $th = hash('sha256', $tok);
    $ch = $codi === null ? null : hash('sha256', $th . '|' . $codi);
    $ip = cc_ip();
    $st = $db->prepare("INSERT INTO Clients_token (tipus, email, ID_Client, token_hash, codi_hash, idioma, ip, caduca)
                        VALUES (?,?,?,?,?,?,?, NOW() + INTERVAL ? MINUTE)");
    $st->bind_param('ssissssi', $tipus, $email, $ID_Client, $th, $ch, $lang, $ip, $minuts);
    $st->execute();
    return [$tok, $codi, $db->insert_id];
}

/** Token vàlid (no usat ni caducat) de l'enllaç */
function cc_token($db, $tipus, $tok) {
    if (!preg_match('/^[0-9a-f]{48}$/', (string)$tok)) return null;
    $st = $db->prepare("SELECT * FROM Clients_token WHERE tipus=? AND token_hash=? AND usat IS NULL AND caduca > NOW()");
    $h = hash('sha256', $tok);
    $st->bind_param('ss', $tipus, $h);
    $st->execute();
    return $st->get_result()->fetch_assoc() ?: null;
}

/** Compta un ús del token i el tanca en arribar a $max (l'enllaç per entrar en té 3: els escàners de correu el poden obrir abans
 *  que el client). Torna false si ja estava tancat; si ho intenten alhora, el comptador no deixa passar-se de $max. */
function cc_token_usa($db, $ID, $max = 1) {
    $max = max(1, (int)$max);
    $db->query("UPDATE Clients_token SET usos=usos+1, usat=IF(usos>=$max, NOW(), NULL) WHERE ID=" . (int)$ID . " AND usat IS NULL AND usos < $max");
    return $db->affected_rows === 1;
}

/** Comprova el codi de 6 xifres d'una petició de login (5 intents) */
function cc_codi_valid($db, $ID, $codi) {
    $db->query("UPDATE Clients_token SET intents=intents+1 WHERE ID=" . (int)$ID . " AND tipus='login' AND usat IS NULL AND caduca > NOW() AND intents < 5");
    if ($db->affected_rows !== 1) return null;
    $t = $db->query("SELECT * FROM Clients_token WHERE ID=" . (int)$ID)->fetch_assoc();
    $codi = preg_replace('/\D/', '', (string)$codi);
    return ($t && strlen($codi) === 6 && hash_equals((string)$t['codi_hash'], hash('sha256', $t['token_hash'] . '|' . $codi))) ? $t : null;
}

// ---------- sessió (galeta pròpia, a la BD només el hash) ----------
function cc_galeta($valor, $caduca) {
    setcookie(CC_GALETA, $valor, ['expires' => $caduca, 'path' => '/client/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
}

function cc_obre_sessio($db, $email) {
    $tok = bin2hex(random_bytes(32));
    $h = hash('sha256', $tok); $ip = cc_ip(); $ua = cc_ua(); $d = CC_SESSIO_DIES;
    $cl = cc_clients($db, $email);
    $id = count($cl) === 1 ? array_key_first($cl) : null;
    $st = $db->prepare("INSERT INTO Clients_sessio (token_hash, email, ID_Client, caduca, ip, user_agent) VALUES (?,?,?, NOW() + INTERVAL ? DAY, ?, ?)");
    $st->bind_param('ssiiss', $h, $email, $id, $d, $ip, $ua);
    $st->execute();
    cc_galeta($tok, time() + $d * 86400);
}

/** Sessió activa: ['email', 'ID_Client', 'clients' => [ID => nom], 'csrf'] o null. Si el correu ja no és de cap client, la tanca. */
function cc_sessio($db) {
    $tok = (string)($_COOKIE[CC_GALETA] ?? '');
    if (!preg_match('/^[0-9a-f]{64}$/', $tok)) return null;
    $h = hash('sha256', $tok);
    $st = $db->prepare("SELECT * FROM Clients_sessio WHERE token_hash=? AND caduca > NOW()");
    $st->bind_param('s', $h);
    $st->execute();
    $s = $st->get_result()->fetch_assoc();
    if (!$s) { cc_galeta('', 1); return null; }
    $cl = cc_clients($db, $s['email']);
    if (!$cl) { cc_tanca_sessio($db); return null; }
    $id = (int)$s['ID_Client'];
    if (!isset($cl[$id])) $id = count($cl) === 1 ? array_key_first($cl) : 0;
    $st = $db->prepare("UPDATE Clients_sessio SET darrer=NOW(), ID_Client=NULLIF(?,0) WHERE token_hash=?");
    $st->bind_param('is', $id, $h);
    $st->execute();
    return ['email' => $s['email'], 'ID_Client' => $id, 'clients' => $cl, 'hash' => $h,
            'csrf' => substr(hash_hmac('sha256', 'csrf', $tok), 0, 32)];
}

function cc_tria_client($db, $s, $id) {
    if (!isset($s['clients'][(int)$id])) return false;
    $st = $db->prepare("UPDATE Clients_sessio SET ID_Client=? WHERE token_hash=?");
    $id = (int)$id; $st->bind_param('is', $id, $s['hash']); $st->execute();
    return true;
}

function cc_tanca_sessio($db) {
    $tok = (string)($_COOKIE[CC_GALETA] ?? '');
    if ($tok !== '') {
        $st = $db->prepare("DELETE FROM Clients_sessio WHERE token_hash=?");
        $h = hash('sha256', $tok); $st->bind_param('s', $h); $st->execute();
    }
    cc_galeta('', 1);
}

/** Tanca totes les sessions d'un correu (baixa) */
function cc_tanca_sessions_email($db, $email) {
    $st = $db->prepare("DELETE FROM Clients_sessio WHERE email=?");
    $st->bind_param('s', $email); $st->execute();
}

function cc_neteja($db) {
    $db->query("DELETE FROM Clients_token WHERE caduca < NOW() - INTERVAL 1 DAY");
    $db->query("DELETE FROM Clients_sessio WHERE caduca < NOW()");
}

// ---------- documents ----------
const CC_ANYS = 5;   // historial: de l'any actual menys 5 ençà (obligació legal de conservar-los 5 anys: el 2026 es mostra des del 2021)

/** Taules index_relacio dels últims CC_ANYS anys: [[taula, any, empresa], ...] */
function cc_taules($db) {
    static $r = null;
    if ($r !== null) return $r;
    $r = [];
    foreach ($db->query("SHOW TABLES LIKE '%\\_Empresa\\_%\\_index\\_relacio'")->fetch_all() as $t)
        if (preg_match('/^(\d{4})_Empresa_(\d+)_index_relacio$/', $t[0], $m) && (int)$m[1] >= (int)date('Y') - CC_ANYS) $r[] = $m;
    return $r;
}

/** Anys amb documents del client (per al filtre): [any => quants] */
function cc_anys_client($db, $ID_Client, $filtre = 0) {
    $id = (int)$ID_Client; $tipus = in_array((int)$filtre, CC_TIPUS, true) ? (string)(int)$filtre : implode(',', CC_TIPUS); $parts = [];
    foreach (cc_taules($db) as $m) $parts[] = "SELECT {$m[1]} a FROM `{$m[0]}` WHERE ID_client=$id AND ID_tipus_relacio IN ($tipus) AND relacio_oberta=0 AND uuid<>''";
    if (!$parts) return [];
    $r = [];
    foreach ($db->query("SELECT a, COUNT(*) FROM (" . implode(' UNION ALL ', $parts) . ") x GROUP BY a ORDER BY a DESC")->fetch_all() as $f) $r[(int)$f[0]] = (int)$f[1];
    return $r;
}

/** Tipus de document que té el client (per al filtre): [ID_tipus => quants] */
function cc_tipus_client($db, $ID_Client) {
    $id = (int)$ID_Client; $tipus = implode(',', CC_TIPUS); $parts = [];
    foreach (cc_taules($db) as $m) $parts[] = "SELECT ID_tipus_relacio t FROM `{$m[0]}` WHERE ID_client=$id AND ID_tipus_relacio IN ($tipus) AND relacio_oberta=0 AND uuid<>''";
    if (!$parts) return [];
    $r = [];
    foreach ($db->query("SELECT t, COUNT(*) FROM (" . implode(' UNION ALL ', $parts) . ") x GROUP BY t ORDER BY t")->fetch_all() as $f) $r[(int)$f[0]] = (int)$f[1];
    return $r;
}

/** Tiquets, factures... del client a totes les seus (últims CC_ANYS anys), els més nous primer; $filtre: un ID_tipus o 0 */
function cc_documents($db, $ID_Client, $limit = 0, $filtre = 0, $any = 0) {
    $id = (int)$ID_Client;
    if ($id <= 0) return [];
    $tipus = in_array((int)$filtre, CC_TIPUS, true) ? (string)(int)$filtre : implode(',', CC_TIPUS);
    $parts = [];
    foreach (cc_taules($db) as $m) {
        if ($any && (int)$m[1] !== (int)$any) continue;
        $parts[] = "SELECT ID, ID_tipus_relacio, data, uuid, {$m[2]} AS empresa, {$m[1]} AS `any` FROM `{$m[0]}`
                    WHERE ID_client=$id AND ID_tipus_relacio IN ($tipus) AND relacio_oberta=0 AND uuid<>''";
    }
    if (!$parts) return [];
    $sql = implode(' UNION ALL ', $parts) . ' ORDER BY data DESC, ID DESC' . ($limit > 0 ? ' LIMIT ' . ((int)$limit + 1) : '');
    return $db->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function cc_empreses($db) {
    static $e = null;
    if ($e === null) { $e = []; foreach ($db->query("SELECT ID, Value FROM Empresa")->fetch_all() as $f) $e[(int)$f[0]] = (string)$f[1]; }
    return $e;
}

/** Enllaç signat al PDF (24 h), el mateix format que enllac_pdf() de scale/phpgeneral/lib.php */
function cc_enllac_pdf($uuid) {
    global $pdf_token_secret;
    $uuid = preg_replace('/[^A-Za-z0-9]/', '', (string)$uuid);
    if (empty($pdf_token_secret)) return '/pdf/' . $uuid . '.pdf';
    $exp = str_pad(dechex(time() + 24 * 3600), 8, '0', STR_PAD_LEFT);
    return '/pdf/t' . $exp . $uuid . substr(hash_hmac('sha256', $exp . $uuid, (string)$pdf_token_secret), 0, 20) . '.pdf';
}

/** Imports del document amb detallrelacio() de l'ERP (descomptes inclosos; les factures sumen els seus albarans per rel2rel):
 *  ['base' => x, 'total' => x, 'iva' => [% => [base, quota]], 're' => [% => quota]] */
function cc_detall($db, $d) {
    static $carregat = false, $memo = [];
    $k = $d['empresa'] . '|' . $d['any'] . '|' . $d['uuid'];
    if (isset($memo[$k])) return $memo[$k];
    if (!$carregat) {
        foreach (['mysqli.php', 'taula.php', 'lib.php'] as $f) require_once __DIR__ . '/../scale/phpgeneral/' . $f;
        $carregat = true;
    }
    $er = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
    try {
        $r = detallrelacio($db->real_escape_string((string)$d['uuid']), (int)$d['empresa'], (int)$d['any'], $db);
        $o = ['base' => (float)($r['Base'] ?? 0), 'total' => (float)($r['Total'] ?? 0), 'iva' => [], 're' => []];
        foreach ((array)($r['B'] ?? []) as $pc => $b) if ((float)$b != 0 || (float)($r['IVA'][$pc] ?? 0) != 0) $o['iva'][$pc] = [(float)$b, (float)($r['IVA'][$pc] ?? 0)];
        ksort($o['iva'], SORT_NUMERIC);
        foreach ((array)($r['EC'] ?? []) as $pc => $q) if ((float)$pc > 0 && (float)$q != 0) $o['re'][$pc] = (float)$q;
    } catch (Throwable $e) {
        $o = ['base' => 0.0, 'total' => 0.0, 'iva' => [], 're' => []];
    }
    error_reporting($er);
    return $memo[$k] = $o;
}

/**
 * Resum per any per al model 347: suma de factures, tiquets i albarans que no són a cap factura (rel2rel).
 * Si d'un any/empresa no hi ha rel2rel a la central, no se sap quins albarans estan facturats i no es compten.
 * @return array [any => ['base', 'total', 'iva' => [% => quota], 're' => [% => quota], 'n', 'sense_rel2rel' => bool]]
 */
function cc_resum_anys($db, $ID_Client, $any = 0) {
    $docs = cc_documents($db, $ID_Client, 0, 0, $any);
    $facturats = []; $te_rel2rel = [];
    foreach ($docs as $d) {
        $k = $d['any'] . '_' . $d['empresa'];
        if (isset($te_rel2rel[$k])) continue;
        $t = "{$d['any']}_Empresa_{$d['empresa']}_rel2rel";
        $te_rel2rel[$k] = $db->query("SHOW TABLES LIKE '$t'")->num_rows > 0;
        if ($te_rel2rel[$k]) foreach ($db->query("SELECT ID_inclosa, ID_tipus_relacio_inclosa FROM `$t`")->fetch_all() as $f) $facturats["$k|{$f[0]}|{$f[1]}"] = true;
    }
    $r = [];
    foreach ($docs as $d) {
        $a = (int)$d['any']; $k = $d['any'] . '_' . $d['empresa']; $tp = (int)$d['ID_tipus_relacio'];
        $r[$a] ??= ['base' => 0.0, 'total' => 0.0, 'iva' => [], 're' => [], 'n' => 0, 'sense_rel2rel' => false];
        if ($tp === 2) {
            if (!$te_rel2rel[$k]) { $r[$a]['sense_rel2rel'] = true; continue; }
            if (isset($facturats["$k|{$d['ID']}|2"])) continue;
        } elseif ($tp !== 3 && $tp !== 10) continue;
        $x = cc_detall($db, $d);
        $r[$a]['base'] += $x['base']; $r[$a]['total'] += $x['total']; $r[$a]['n']++;
        foreach ($x['iva'] as $pc => [$b, $q]) $r[$a]['iva'][$pc] = ($r[$a]['iva'][$pc] ?? 0) + $q;
        foreach ($x['re'] as $pc => $q) $r[$a]['re'][$pc] = ($r[$a]['re'][$pc] ?? 0) + $q;
    }
    foreach ($r as &$y) { ksort($y['iva'], SORT_NUMERIC); ksort($y['re'], SORT_NUMERIC); }
    return $r;
}
