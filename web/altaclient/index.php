<?php
// Alta pública de clients (només a la central). Flux: formulari -> sol·licitud pendent -> correu de confirmació -> confirm.php crea el client.
// Les seus redirigeixen aquí (els clients els reparteix la central). El personal (login de scale) va a staff.php.
session_start();
if (($_SESSION['login'] ?? false) === true) { require __DIR__ . '/staff.php'; exit; }

require __DIR__ . '/boot.php';
if (!ac_es_central()) {   // només la central té la clau del repte: les altres seus hi redirigeixen ($altaclient_url)
    $q = isset($_GET['lang']) ? '?lang=' . rawurlencode(substr((string)$_GET['lang'], 0, 2)) : '';
    if (empty($altaclient_url) || !preg_match('#^https://#', $altaclient_url)) { http_response_code(404); exit; }
    header('Location: ' . rtrim($altaclient_url, '/') . '/' . $q, true, 302);
    exit;
}
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
header('X-Frame-Options: DENY'); header('Referrer-Policy: no-referrer'); header('Cache-Control: no-store');

$db = ac_db();
ac_neteja($db);
$lang = ac_lang();
$T = ac_i18n()[$lang];
$cond = ac_condicions($db, $lang);
if (empty($_SESSION['ac_csrf'])) $_SESSION['ac_csrf'] = bin2hex(random_bytes(16));

$in = fn($k, $max = 255) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $max);
$v = ['value' => '', 'fiscal_address' => '', 'poblacio' => '', 'nif' => '', 'comercial_name' => '', 'deliver_address' => '', 'email' => '',
      'emails' => [], 'telefons' => [''], 'bool_tax_detall' => 0, 'bool_equivalency_charge' => 0];
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    foreach (['value', 'fiscal_address', 'poblacio', 'comercial_name', 'deliver_address'] as $k) $v[$k] = $in($k);
    $v['nif'] = $in('nif', 30);
    $v['email'] = strtolower($in('email', 190));
    $v['bool_tax_detall'] = isset($_POST['bool_tax_detall']) ? 1 : 0;
    $v['bool_equivalency_charge'] = isset($_POST['bool_equivalency_charge']) ? 1 : 0;
    $v['emails'] = []; $v['telefons'] = [];
    foreach (array_slice((array)($_POST['emails'] ?? []), 0, 3) as $e) { $e = strtolower(mb_substr(trim(strip_tags((string)$e)), 0, 190)); if ($e !== '') $v['emails'][] = $e; }
    foreach (array_slice((array)($_POST['telefon'] ?? []), 0, 3) as $t) {
        $t = (string)$t;
        if (strpos($t, '+') !== false && strpos($t, '+') !== 0) $t = str_replace('+', '', $t);   // el + només al principi
        $t = preg_replace('/[^0-9+]/', '', $t);
        if ($t !== '') $v['telefons'][] = $t;
    }
    $v['emails'] = array_values(array_unique($v['emails']));
    $v['telefons'] = array_values(array_unique($v['telefons']));

    if (!hash_equals((string)$_SESSION['ac_csrf'], (string)($_POST['csrf'] ?? ''))) $errors[] = $T['err_csrf'];
    elseif (trim((string)($_POST['website'] ?? '')) !== '') {   // camp parany: els robots l'ompl
        ac_pagina($lang, $T['sent_title'], '<p>' . h(strtr($T['sent_text'], ['{EMAILS}' => $v['email']])) . '</p>');
    } else {
        foreach (['value', 'fiscal_address', 'poblacio', 'nif', 'email'] as $k) if ($v[$k] === '') { $errors[] = $T['err_required']; break; }
        $totsCorreus = array_merge([$v['email']], $v['emails']);
        foreach ($totsCorreus as $e) if ($e !== '' && !filter_var($e, FILTER_VALIDATE_EMAIL)) { $errors[] = $T['err_email']; break; }
        if (in_array($v['email'], $v['emails'], true)) $v['emails'] = array_values(array_diff($v['emails'], [$v['email']]));
        if (!$v['telefons']) $errors[] = $T['err_required'];
        foreach ($v['telefons'] as $t) if (!preg_match('/^\+?\d{6,15}$/', $t)) { $errors[] = $T['err_phone']; break; }
        if (empty($_POST['acceptterms'])) $errors[] = $T['err_terms'];
        $errors = array_values(array_unique($errors));

        if (!$errors && !ac_repte_valid($db, (string)($_POST['altcha'] ?? ''))) $errors[] = $T['err_captcha'];

        if (!$errors) {   // límits: per IP i per adreça (evita fer servir el formulari per atabalar un tercer)
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            $st = $db->prepare("SELECT COUNT(*) FROM Clients_pendents WHERE ip=? AND creat > NOW() - INTERVAL 1 HOUR"); $st->bind_param('s', $ip); $st->execute();
            if ((int)$st->get_result()->fetch_row()[0] >= 5) $errors[] = $T['err_rate'];
            foreach ($totsCorreus as $e) {
                $st = $db->prepare("SELECT COUNT(*) FROM Clients_pendents_email pe JOIN Clients_pendents p ON p.ID=pe.ID_pendent WHERE pe.email=? AND p.creat > NOW() - INTERVAL 24 HOUR");
                $st->bind_param('s', $e); $st->execute();
                if ((int)$st->get_result()->fetch_row()[0] >= 3) { $errors[] = $T['err_rate']; break; }
            }
        }
        if (!$errors) {   // duplicats (com abans: nom i telèfon)
            $st = $db->prepare("SELECT 1 FROM Clients WHERE Value=?"); $uv = urlencode($v['value']); $st->bind_param('s', $uv); $st->execute();
            if ($st->get_result()->num_rows) $errors[] = $T['err_name'];
            foreach ($v['telefons'] as $t) {
                $st = $db->prepare("SELECT 1 FROM Clients_phone WHERE Value=?"); $st->bind_param('s', $t); $st->execute();
                if ($st->get_result()->num_rows) { $errors[] = $T['err_phone_dup']; break; }
            }
        }
        if (!$errors) {
            $dades = json_encode(['value' => $v['value'], 'fiscal_address' => $v['fiscal_address'], 'poblacio' => $v['poblacio'], 'nif' => $v['nif'],
                'comercial_name' => $v['comercial_name'], 'deliver_address' => $v['deliver_address'], 'email' => $v['email'], 'telefons' => $v['telefons'],
                'bool_tax_detall' => $v['bool_tax_detall'], 'bool_equivalency_charge' => $v['bool_equivalency_charge']], JSON_UNESCAPED_UNICODE);
            $caduca = date('Y-m-d H:i:s', time() + AC_CADUCA_H * 3600);
            $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
            $st = $db->prepare("INSERT INTO Clients_pendents (caduca, idioma, versio_condicions, hash_condicions, ip, user_agent, dades) VALUES (?,?,?,?,?,?,?)");
            $st->bind_param('sssssss', $caduca, $lang, $cond['versio'], $cond['hash'], $ip, $ua, $dades); $st->execute();
            $pid = $db->insert_id;
            $enviats = [];
            foreach ($totsCorreus as $i => $e) {
                $tok = bin2hex(random_bytes(24)); $th = hash('sha256', $tok); $princ = $i === 0 ? 1 : 0;
                $st = $db->prepare("INSERT INTO Clients_pendents_email (ID_pendent, email, principal, token_hash) VALUES (?,?,?,?)");
                $st->bind_param('isis', $pid, $e, $princ, $th); $st->execute();
                $cos = strtr($T[$princ ? 'mail_main' : 'mail_extra'], ['{LINK}' => ac_base() . '/altaclient/confirm.php?t=' . $tok, '{COND}' => ac_url_condicions($cond)]);
                $ok = ac_envia_correu($db, $e, $T['mail_subject'], $cos);
                if ($ok) $enviats[] = $e;
                elseif ($princ) {   // sense el correu principal no hi ha res a confirmar: s'esborra tot
                    $db->query("DELETE FROM Clients_pendents_email WHERE ID_pendent=$pid"); $db->query("DELETE FROM Clients_pendents WHERE ID=$pid");
                    $errors[] = $T['err_mail']; break;
                }
            }
            if (!$errors) ac_pagina($lang, $T['sent_title'], '<p>' . h(strtr($T['sent_text'], ['{EMAILS}' => implode(', ', $enviats)])) . '</p>');
        }
    }
}
if (!$v['telefons']) $v['telefons'] = [''];

// ---------- formulari ----------
header('Content-Type: text/html; charset=utf-8');
$camp = function ($id, $label, $val, $ex = '', $req = false, $type = 'text', $max = 255) use ($T) {
    return '<div class="f"><label for="' . $id . '">' . h($T[$label]) . ($req ? ' *' : '') . '</label><input type="' . $type . '" id="' . $id . '" name="' . $id . '" maxlength="' . $max . '" value="' . h($val) . '"'
         . ($ex ? ' placeholder="' . h($T[$ex]) . '"' : '') . ($req ? ' required' : '') . ' autocomplete="off"></div>';
};
$fila = fn($name, $type, $val, $T) => '<div class="row"><input type="' . $type . '" name="' . $name . '" maxlength="190" value="' . h($val) . '" autocomplete="off"><button type="button" class="rm">' . h($T['remove']) . '</button></div>';
?><!doctype html>
<html lang="<?= h($lang) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title><?= h($T['title']) ?></title><link rel="stylesheet" href="style.css"></head><body><main>
<div class="lang"><?php foreach (ac_i18n() as $k => $t) if ($k !== $lang) echo '<a href="?lang=' . h($k) . '">' . h($t['idioma_nom']) . '</a>'; ?></div>
<h1><?= h($T['title']) ?></h1>
<p class="intro"><?= h($T['intro']) ?></p>
<?php if ($errors): ?><div class="err" role="alert"><b><?= h($T['err_head']) ?></b><ul><?php foreach ($errors as $e) echo '<li>' . h($e) . '</li>'; ?></ul></div><?php endif; ?>
<form id="altaform" method="post" action="./" data-verifying="<?= h($T['verifying']) ?>" data-ready="<?= h($T['ready']) ?>" data-error="<?= h($T['pow_error']) ?>" data-remove="<?= h($T['remove']) ?>">
<input type="hidden" name="csrf" value="<?= h($_SESSION['ac_csrf']) ?>"><input type="hidden" name="lang" value="<?= h($lang) ?>"><input type="hidden" name="altcha" id="altcha" value="">
<div class="hp" aria-hidden="true"><label>Web</label><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
<?= $camp('value', 'name_label', $v['value'], 'name_ex', true) ?>
<?= $camp('fiscal_address', 'fiscal_label', $v['fiscal_address'], 'fiscal_ex', true) ?>
<?= $camp('poblacio', 'poblacio_label', $v['poblacio'], 'poblacio_ex', true) ?>
<?= $camp('nif', 'nif_label', $v['nif'], '', true, 'text', 30) ?>
<?= $camp('comercial_name', 'comercial_label', $v['comercial_name']) ?>
<?= $camp('deliver_address', 'deliver_label', $v['deliver_address']) ?>
<?= $camp('email', 'email_label', $v['email'], '', true, 'email', 190) ?>
<div class="f"><label><?= h($T['email_extra_label']) ?></label><div id="emails"><?php foreach ($v['emails'] as $e) echo $fila('emails[]', 'email', $e, $T); ?></div>
<button type="button" data-add="email"><?= h($T['add_email']) ?></button></div>
<div class="f"><label><?= h($T['phone_label']) ?> *</label><div id="phones"><?php foreach ($v['telefons'] as $t) echo $fila('telefon[]', 'tel', $t, $T); ?></div>
<button type="button" data-add="phone"><?= h($T['add_phone']) ?></button></div>
<div class="chk"><input type="checkbox" id="tax" name="bool_tax_detall" <?= $v['bool_tax_detall'] ? 'checked' : '' ?>><label for="tax"><?= h($T['tax_label']) ?></label></div>
<div class="chk"><input type="checkbox" id="charge" name="bool_equivalency_charge" <?= $v['bool_equivalency_charge'] ? 'checked' : '' ?>><label for="charge"><?= h($T['charge_label']) ?></label></div>
<div class="chk"><input type="checkbox" id="terms" name="acceptterms" value="1" required><label for="terms"><?= strtr($T['consent'], ['{URL}' => h(ac_url_condicions($cond))]) ?> *</label></div>
<div id="powstatus" aria-live="polite"></div>
<button type="submit" disabled><?= h($T['submit']) ?></button>
<p class="req"><?= h($T['required']) ?></p>
<noscript><p class="err">JavaScript</p></noscript>
</form>
<script src="form.js"></script>
</main></body></html>
