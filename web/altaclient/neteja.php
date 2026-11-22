<?php
// Esborra les sol·licituds d'alta no confirmades (a les 48 h) i els reptes usats. Cron: cada hora.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
require __DIR__ . '/boot.php';
$db = ac_db();
$n = (int)$db->query("SELECT COUNT(*) FROM Clients_pendents WHERE caduca < NOW()")->fetch_row()[0];
ac_neteja($db);
echo date('c') . " sol·licituds caducades esborrades: $n\n";
