<?php
// edit_preview.php - Genera previsualització HTML de la relació
error_reporting(0);
session_start();

if(!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    die("No autenticat");
}

require_once("{$_SERVER['DOCUMENT_ROOT']}/../keys.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/mysqli.php");

$link = new mysqli($myserver, $myuser, $mypassword, $mydatabase);

// Definir variables globals necessàries per html_relacio
$uri = "http://{$_SERVER['HTTP_HOST']}";

if(!function_exists('detallrelacio')) {
    require_once('lib.php');
}

$uuid = $_REQUEST['uuid'];
$ID_Empresa = (int)$_REQUEST['ID_Empresa'];
$year = (int)$_REQUEST['year'];

$rel = detallrelacio($uuid, $ID_Empresa, $year, $link);

if(!$rel) {
    echo "<p>No s'ha pogut carregar la relació</p>";
    exit();
}

// Afegir CSS override per assegurar que es veu bé dins del preview
echo '<style>
    body { background: white !important; color: black !important; }
    .factura-container { background: white !important; color: black !important; }
    h1, h2, h3, p, td, th, div { color: black !important; }
</style>';

// Generar HTML de la relació
echo html_relacio($rel, false); // false per no imprimir protecció de dades
?>
