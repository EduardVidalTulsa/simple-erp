<?php
// Manifest de la pàgina de clients: start_url porta el token, perquè l'app instal·lada obri directament la cua.
header('Content-Type: application/manifest+json');
header('Cache-Control: no-store');
$t = preg_replace('/[^a-zA-Z0-9]/', '', (string)($_GET['token'] ?? ''));
echo json_encode([
	'id' => '/plugins/torn/client.php',
	'name' => 'Torns', 'short_name' => 'Torns',
	'start_url' => "/plugins/torn/client.php?token=$t",
	'scope' => '/plugins/torn/',
	'display' => 'fullscreen', 'orientation' => 'portrait',
	'background_color' => '#f4f4f2', 'theme_color' => '#2f6fb3',
	'icons' => [
		['src' => '/plugins/torn/icona-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
		['src' => '/plugins/torn/icona-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
	],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
