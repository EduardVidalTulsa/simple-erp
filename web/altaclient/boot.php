<?php
// Arrencada comuna de l'alta pública: claus de la BD i del repte, i funcions. Només té sentit a la central.
require_once $_SERVER['DOCUMENT_ROOT'] . '/../keys.php';
if (!empty($altaclient_secrets) && is_file($altaclient_secrets)) {   // opcional: les claus en un fitxer a part (keys.php)
    require_once $altaclient_secrets;
    unset($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $SYNC_TOKENS);   // l'alta pública no necessita el root ni els tokens
}
require_once __DIR__ . '/core.php';
