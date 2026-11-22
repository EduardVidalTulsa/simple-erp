<?php
require __DIR__ . '/boot.php';
if (!ac_es_central()) { http_response_code(404); exit; }
header('Content-Type: application/json'); header('Cache-Control: no-store');
echo json_encode(ac_repte());
