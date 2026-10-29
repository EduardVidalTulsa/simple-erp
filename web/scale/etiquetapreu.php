<?php
/************
*
* Generador d'etiquetes de preu (etiquetes simples sense ingredients ni QR)
* 
* Control d'accés:
*   - Localhost: sempre permès
*   - Amb ID_Empresa: permès (per cridar des de p.php amb curl sense login)
*   - Altrament: requereix login
*
* Paràmetres:
*   - ID: codi del producte (obligatori)
*   - package: tipus de paquet (opcional)
*   - units: número d'etiquetes a imprimir (per defecte 1)
*   - midaetiqueta: mida de l'etiqueta (opcional: "" o "57x45")
*   - print: true per imprimir, false per només mostrar SVG (per defecte false)
*   - ID_Empresa: ID de l'empresa per saber impressora (defecte: 1 si no login, login['ID_Empresa'] si login)
*   - debug: true per deixar fitxers SVG a /tmp/ i veure missatges debug
*
* Exemples:
*   etiquetapreu.php?ID=5042 - Mostra SVG etiqueta preu del producte 5042
*   etiquetapreu.php?ID=5042&ID_Empresa=32&print=true - Imprimeix etiqueta (sense login, per p.php)
*   etiquetapreu.php?ID=5042&package=2&units=5&print=true - Imprimeix 5 etiquetes amb login
*   etiquetapreu.php?ID=5042&midaetiqueta=57x45&debug=true - Debug: mostra SVG i deixa fitxers a /tmp/
*
************/

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
date_default_timezone_set('Europe/Andorra');

// Control d'accés: 
// - Localhost: sempre permès
// - Si es passa ID_Empresa: permès (per cridar des de p.php amb curl)
// - Altrament: requerir login
$is_localhost = preg_match('/^127\.0\.0\.1$|^::1$|^localhost$/i', $_SERVER['REMOTE_ADDR']);
$te_id_empresa = isset($_REQUEST['ID_Empresa']) && is_numeric($_REQUEST['ID_Empresa']);

if($is_localhost || $te_id_empresa){
	// Accés permès: carregar configuració mínima sense login
	require_once("{$_SERVER['DOCUMENT_ROOT']}/../keys.php");
	require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/lib.php");
	require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/mysqli.php");
	require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/vars.php");
	session_start();
	$link = new mysqli($myserver,$myuser,$mypassword,$mydatabase);
	$lang = 'ca';
	$login = true;
	// Carregar empresa del paràmetre o per defecte 1
	$ID_Empresa = isset($_REQUEST['ID_Empresa']) ? intval($_REQUEST['ID_Empresa']) : 1;
} else {
	// Accés extern sense ID_Empresa: requerir login complet
	require("login.php");
	if(!$login){
		http_response_code(403);
		die("Accés denegat");
	}
	// Agafar ID_Empresa del login
	$ID_Empresa = $login['ID_Empresa'];
}

require_once('phpgeneral/etiquetapreu.php');

// Permetre sobreescriure $midaetiqueta via GET/POST
if(isset($_REQUEST['midaetiqueta'])){
	$midaetiqueta = $_REQUEST['midaetiqueta'];
}
// Si no està definit, usar valor per defecte buit
if(!isset($midaetiqueta)){
	$midaetiqueta = "";
}

// Validar paràmetres
if(!isset($_REQUEST['ID']) || !is_numeric($_REQUEST['ID'])){
	http_response_code(400);
	die("Error: Paràmetre ID obligatori i ha de ser numèric");
}

$ID = intval($_REQUEST['ID']);
$package = isset($_REQUEST['package']) ? $_REQUEST['package'] : null;
$units = isset($_REQUEST['units']) ? intval($_REQUEST['units']) : 1;
$print = isset($_REQUEST['print']) && $_REQUEST['print'] === 'true';
$debug = isset($_REQUEST['debug']) && $_REQUEST['debug'] === 'true';

if($debug){
	debugtelegram([
		'ID' => $ID,
		'package' => $package,
		'units' => $units,
		'midaetiqueta' => $midaetiqueta,
		'print' => $print
	], "etiquetapreu.php params");
}

// Obtenir dades del producte
$d = $link->query("select * from scalelist where ID='$ID'")->fetch_assoc();

if(!$d || $d['id'] != $ID){
	http_response_code(404);
	die("Error: Producte $ID no existeix");
}

$decimalsing = ',';
$thousandsing = ' ';

// Preparar dades per l'etiqueta
$pvp = number_format($d['pvp'], 2, $decimalsing, $thousandsing);
$nom = strtoupper(urldecode($d['value']));
$d['bulk'] == 1 ? $u = 'Kg' : $u = 'uni';
$origen = urldecod(mysql1r("SELECT origen from productnamesurlencoded where ID={$ID} and lang='ca'", $link));
if($origen == "") $origen = 'Preguntar a botiga';

// Si té package, obtenir info del package
if($package){
	$sql = "select * from package_format where ID={$ID} and ID_package_type='{$package}'";
	if($debug) debugtelegram($sql, "sql package");
	
	$package_data = $link->query($sql)->fetch_assoc();
	
	if(!isset($package_data) || !$package_data) {
		http_response_code(404);
		die("Error: El paquet $ID.$package no existeix");
	}
	
	// Obtenir descripció del package si existeix
	try{ 
		$package_description = $link->query("select * from package_description where ID={$ID} and ID_package_type='{$package}' and `lang`='ca'")->fetch_assoc();
	} catch (Exception $e){
		$package_description = null;
	}
	
	$product = $link->query("SELECT * from tarifes where `ID`=$ID AND `ultimpreu`=1")->fetch_assoc();
	$product['pvp'] = package_pvp($product, $package_data, $link);
	
	if(!$product['pvp']){
		http_response_code(400);
		die("Error: $ID.$package no té un preu sobreescrit");
	}
	
	$pvp = number_format($product['pvp'], 2, $decimalsing, $thousandsing);
	
	if(isset($package_description['description']) && $package_description['description']){
		$nom = strtoupper(urldecode($package_description['description']));
	} else {
		$nom = strtoupper(urldecode($d['value']));
		$weight = mysql1r("select `weight` from `package_format` where `ID`='{$ID}' and `ID_package_type`='{$package}' AND `hidden_date_time` = (SELECT MAX(`hidden_date_time`) from package_format where `ID` ='{$ID}' AND `ID_package_type`='{$package}')", $link);
		if($weight){
			if($weight > 1000){
				$nom .= " " . number_format($weight/1000, 2, $decimalsing, $thousandsing) . "Kg";
			} else {
				$nom .= " " . $weight . "g";
			}
		}
	}
	
	$u = 'uni';
}

// Generar SVG
$svg = generar_etiqueta_preu_svg($ID, $pvp, $nom, $u, $origen, $package ? $package : "");

if(!$svg){
	http_response_code(500);
	die("Error: No s'ha pogut generar l'etiqueta de preu");
}

if($print){
	// IMPRIMIR ETIQUETA
	if($debug) debugtelegram("Imprimint $units etiqueta(es) de preu del producte $ID");
	
	$empresa = $link->query("SELECT * FROM Empresa where `ID`='{$ID_Empresa}'")->fetch_assoc();
	
	// Guardar SVG a /tmp (si debug=true, no s'esborrarà després)
	$nf = "/tmp/etiquetapreu_{$ID}" . ($package ? "_{$package}" : "") . "_{$midaetiqueta}.svg";
	file_put_contents($nf, $svg);
	
	// Renderitzar a PostScript localment
	exec("inkscape $nf -o /tmp/tmp.ps 2>&1", $output, $return_code);
	
	if($return_code !== 0 && $debug){
		debugtelegram("Error inkscape: ".implode("\n", $output), "etiquetapreu.php");
	}
	
	// Imprimir: si printerserver és diferent, usar SSH, sinó local
	$is_remote_printer = !empty($empresa['printerserver']) && 
	                     $empresa['printerserver'] !== 'localhost' && 
	                     $empresa['printerserver'] !== '127.0.0.1';
	
	if($is_remote_printer){
		// Impressora remota: copiar i imprimir via SSH
		exec("scp /tmp/tmp.ps {$empresa['printerserver']}:/tmp/");
		for ($i = 1; $i <= $units; $i++){
			exec("ssh {$empresa['printerserver']} \"lp /tmp/tmp.ps -d {$empresa['sticker_printer']}\"");
		}
		if(!$debug){
			exec("ssh {$empresa['printerserver']} \"rm -f /tmp/tmp.ps\"");
		}
	} else {
		// Impressora local: imprimir directament
		for ($i = 1; $i <= $units; $i++){
			exec("lp /tmp/tmp.ps -d {$empresa['sticker_printer']} 2>&1", $output, $return_code);
			if($return_code !== 0 && $debug){
				debugtelegram("Error lp: ".implode("\n", $output), "etiquetapreu.php");
			}
		}
	}
	
	// Netejar fitxers temporals
	if(!$debug){
		unlink($nf);
		unlink('/tmp/tmp.ps');
	}
	
	echo "OK - Impreses $units etiqueta(es) de preu del producte $ID";
	
} else {
	// MOSTRAR SVG (sense imprimir)
	if($debug) debugtelegram("Mostrant SVG de preu del producte $ID");
	
	header('Content-Type: image/svg+xml');
	echo $svg;
}

$link->close();
?>
