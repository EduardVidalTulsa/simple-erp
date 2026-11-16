<?php
// Estat i canvi de la porta per al giny de scale: GET torna {"obert":bool}; POST a=obre|tanca la canvia.
require __DIR__ . '/../carrega.php';
[$link, $cfg, $user] = plugin_endpoint_sessio('porta');

function porta_rpc($host, $metode, $params) {
	$host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', $host);
	if($host === '') return null;
	$ch = curl_init("http://$host/rpc/$metode?" . http_build_query($params));
	curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 2]);
	$r = curl_exec($ch);
	return $r === false ? null : json_decode($r, true);
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
	$a = $_POST['a'] ?? '';
	if($a === 'obre' || $a === 'tanca') {
		$ok = porta_rpc($cfg['host'], 'Switch.Set', ['id' => 0, 'on' => $a === 'obre' ? 'true' : 'false']) !== null;
		error_log("plugin porta: " . ($ok ? ($a === 'obre' ? 'desbloquejada' : 'bloquejada') : 'ERROR') . " per $user");
	}
}
$s = porta_rpc($cfg['host'], 'Switch.GetStatus', ['id' => 0]);
echo json_encode($s === null ? ['error' => 'shelly'] : ['obert' => (bool)$s['output']]);
