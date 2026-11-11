<?php
/**
 * Ús (només CLI, cridat per SSH des d'una altra seu): php imprimeix_remot.php <fitxer.lp>
 * El fitxer ja ha d'estar copiat (per scp) a aquest mateix directori. Crida el
 * ticket_print_command() d'aquesta seu (el del keys.php d'aquesta instal·lació), que ja sap
 * arribar a la impressora física d'aquí, i l'esborra en acabar.
 */
if(PHP_SAPI !== 'cli') { http_response_code(403); die; }
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__); // ticket_print_command() el fa servir i la CLI no el posa
require_once(__DIR__ . '/../../keys.php');

$fitxer = $argv[1] ?? '';
if($fitxer === '' || !preg_match('/^[A-Za-z0-9_.-]+\.lp$/', $fitxer) || !file_exists(__DIR__ . "/$fitxer")) {
	fwrite(STDERR, "fitxer invàlid o no trobat\n");
	exit(1);
}
$ok = ticket_print_command($fitxer);
@unlink(__DIR__ . "/$fitxer");
echo $ok ? "OK\n" : "FAIL\n";
exit($ok ? 0 : 1);
