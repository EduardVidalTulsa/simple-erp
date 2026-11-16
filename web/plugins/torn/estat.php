<?php
// Per al giny de scale: GET torna l'estat; POST a=mes|menys mou el torn actual.
require __DIR__ . '/../carrega.php';
require __DIR__ . '/torn.php';
[$link, $cfg, $user] = plugin_endpoint_sessio('torn');
$emp = (int)mysqli_fetch_row($link->query("SELECT ID_Empresa FROM users WHERE username='" . $link->real_escape_string($user) . "'"))[0];
$a = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['a'] ?? '') : '';
echo json_encode($a === 'mes' ? torn_mou($link, $emp, 1) : ($a === 'menys' ? torn_mou($link, $emp, -1) : torn_estat($link, $emp)));
