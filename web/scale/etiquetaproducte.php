<?php
/************
*
* Generador d'etiquetes de producte (amb ingredients, QR, etc.)
* 
* Control d'accés:
*   - Localhost: sempre permès
*   - Amb ID_Empresa: permès (per cridar des de p.php amb curl sense login)
*   - Altrament: requereix login
*
* Paràmetres:
*   - ID: codi del producte (obligatori)
*   - package: tipus de paquet (opcional)
*   - lot: número de lot (opcional)
*   - bbf: data caducitat/consum preferent (opcional)
*   - units: número d'etiquetes a imprimir (per defecte 1)
*   - midaetiqueta: mida de l'etiqueta (opcional: "" o "57x45")
*   - print: true per imprimir, false per només mostrar SVG (per defecte false)
*   - ID_Empresa: ID de l'empresa per saber impressora (defecte: 1 si no login, login['ID_Empresa'] si login)
*   - debug: true per deixar fitxers SVG a /tmp/ i veure missatges debug
*
* Exemples:
*   etiquetaproducte.php?ID=5042 - Mostra SVG del producte 5042
*   etiquetaproducte.php?ID=5042&ID_Empresa=32&print=true - Imprimeix etiqueta (sense login, per p.php)
*   etiquetaproducte.php?ID=5042&package=2&lot=L123&bbf=2024-12-31&units=5&print=true - Imprimeix 5 etiquetes amb login
*   etiquetaproducte.php?ID=5042&midaetiqueta=57x45&debug=true - Debug: mostra SVG i deixa fitxers a /tmp/
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
require '../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

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

$ID_product = intval($_REQUEST['ID']);
$package = isset($_REQUEST['package']) ? $_REQUEST['package'] : null;
$lot = isset($_REQUEST['lot']) ? $_REQUEST['lot'] : null;
$bbf = isset($_REQUEST['bbf']) ? $_REQUEST['bbf'] : null;
$units = isset($_REQUEST['units']) ? intval($_REQUEST['units']) : 1;
$print = isset($_REQUEST['print']) && $_REQUEST['print'] === 'true';
$debug = isset($_REQUEST['debug']) && $_REQUEST['debug'] === 'true';

if($debug){
	debugtelegram([
		'ID_product' => $ID_product,
		'package' => $package,
		'lot' => $lot,
		'bbf' => $bbf,
		'units' => $units,
		'print' => $print
	], "etiquetaproducte.php params");
}

// Comprovar si és etiqueta especial (SVG directe a img/)
$svg_especial = "{$_SERVER['DOCUMENT_ROOT']}/scale/img/{$ID_product}{$midaetiqueta}.svg";
if(!file_exists($svg_especial)) {
	$svg_especial = "{$_SERVER['DOCUMENT_ROOT']}/scale/img/{$ID_product}.svg";
}
$es_especial = file_exists($svg_especial);

if($debug){
	debugtelegram("es_especial=$es_especial, svg_especial=$svg_especial", "etiquetaproducte.php check especial");
}

// Comprovar si és producte normal
$es_producte_normal = mysql1r("SELECT ID FROM `productnamesurlencoded` where `ID`={$ID_product}",$link);

if($debug){
	debugtelegram("es_producte_normal=$es_producte_normal", "etiquetaproducte.php check normal");
}

if($es_producte_normal){
	// PRODUCTE NORMAL - generar etiqueta amb ingredients, QR, etc.
	if($debug) debugtelegram("Processant producte normal $ID_product");
	
	// Preparar array amb info del producte
	$print_data = [
		'ID_product' => $ID_product,
		'package' => $package ? $package : "",
		'lot' => $lot ? $lot : "",
		'bbf' => $bbf ? $bbf : "",
		'units' => $units
	];

	if(!$print){
		// MOSTRAR SVG (previsualització): QR incrustat, sense escriure fitxers
		$svg = generar_etiqueta_producte_svg($print_data, $link);
		if($svg === false){
			http_response_code(500);
			die("Error: No s'ha pogut generar l'etiqueta del producte $ID_product");
		}
		header('Content-Type: image/svg+xml');
		echo $svg;
		$link->close();
		exit;
	}

	// Generar el fitxer SVG de l'etiqueta
	$nf = etiquetaproducte($print_data, $link);
	
	if(!$nf){
		http_response_code(500);
		die("Error: No s'ha pogut generar l'etiqueta del producte $ID_product");
	}
	
	if($debug){
		debugtelegram("SVG generat: $nf", "etiquetaproducte.php");
	}
	
	if($print){
		// IMPRIMIR ETIQUETA
		if($debug) debugtelegram("Imprimint $units etiqueta(es) del producte $ID_product");
		
		$empresa = $link->query("SELECT * FROM Empresa where `ID`='{$ID_Empresa}'")->fetch_assoc();
		
		// Renderitzar a PostScript localment (ja tenim el fitxer a /tmp)
		exec("inkscape $nf -o /tmp/tmp.ps 2>&1", $output, $return_code);
		
		if($return_code !== 0 && $debug){
			debugtelegram("Error inkscape: ".implode("\n", $output), "etiquetaproducte.php");
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
					debugtelegram("Error lp: ".implode("\n", $output), "etiquetaproducte.php");
				}
			}
		}
		
		// Netejar fitxers temporals
		if(!$debug){
			unlink($nf);
			unlink('/tmp/tmp.ps');
		}
		
		echo "OK - Impreses $units etiqueta(es) del producte $ID_product";
		
	} else {
		// MOSTRAR SVG (sense imprimir)
		if($debug) debugtelegram("Mostrant SVG del producte $ID_product");
		
		header('Content-Type: image/svg+xml');
		readfile($nf);
		
		// Netejar després de mostrar
		if(!$debug){
			unlink($nf);
		}
	}
	
} elseif($es_especial) {
	// ETIQUETA ESPECIAL - SVG directe des de img/
	if($debug) debugtelegram("Processant etiqueta especial $ID_product");
	
	// Llegir el SVG i substituir $any si cal
	$svg_source = "{$_SERVER['DOCUMENT_ROOT']}/scale/img/{$ID_product}{$midaetiqueta}.svg";
	if(!file_exists($svg_source)) {
		$svg_source = "{$_SERVER['DOCUMENT_ROOT']}/scale/img/{$ID_product}.svg";
	}
	
	$svg_content = file_get_contents($svg_source);
	
	// Substituir $any per l'any corresponent (any vinent si estem més enllà de gener)
	if(strpos($svg_content, '$any') !== false) {
		$mes_actual = date('n');
		$any_actual = date('Y');
		$any_a_mostrar = ($mes_actual > 1) ? ($any_actual + 1) : $any_actual;
		$svg_content = str_replace('$any', $any_a_mostrar, $svg_content);
	}
	
	if($print){
		// IMPRIMIR ETIQUETA ESPECIAL
		if($debug) debugtelegram("Imprimint $units etiqueta(es) especial $ID_product");
		
		$empresa = $link->query("SELECT * FROM Empresa where `ID`='{$ID_Empresa}'")->fetch_assoc();
		
		// Guardar a /tmp amb l'any substituït
		$tmp_svg = "/tmp/{$ID_product}{$midaetiqueta}.svg";
		file_put_contents($tmp_svg, $svg_content);
		
		// Renderitzar a PostScript localment
		exec("inkscape $tmp_svg -o /tmp/tmp.ps 2>&1", $output, $return_code);
		
		if($return_code !== 0 && $debug){
			debugtelegram("Error inkscape: ".implode("\n", $output), "etiquetaproducte.php");
		}
		
		// Imprimir: si printerserver és diferent, usar SSH, sinó local
		$is_remote_printer = !empty($empresa['printerserver']) && 
		                     $empresa['printerserver'] !== 'localhost' && 
		                     $empresa['printerserver'] !== '127.0.0.1';
		
		if($is_remote_printer){
			exec("scp /tmp/tmp.ps {$empresa['printerserver']}:/tmp/");
			for ($i = 1; $i <= $units; $i++){
				exec("ssh {$empresa['printerserver']} \"lp /tmp/tmp.ps -d {$empresa['sticker_printer']}\"");
			}
			if(!$debug){
				exec("ssh {$empresa['printerserver']} \"rm -f /tmp/tmp.ps\"");
			}
		} else {
			for ($i = 1; $i <= $units; $i++){
				exec("lp /tmp/tmp.ps -d {$empresa['sticker_printer']} 2>&1", $output, $return_code);
				if($return_code !== 0 && $debug){
					debugtelegram("Error lp: ".implode("\n", $output), "etiquetaproducte.php");
				}
			}
		}
		
		// Netejar
		if(!$debug){
			unlink($tmp_svg);
			unlink('/tmp/tmp.ps');
		}
		
		echo "OK - Impreses $units etiqueta(es) especial $ID_product";
		
	} else {
		// MOSTRAR SVG ESPECIAL
		if($debug) debugtelegram("Mostrant SVG especial $ID_product");
		
		header('Content-Type: image/svg+xml');
		echo $svg_content;
	}
	
} else {
	// No és ni producte normal ni especial
	http_response_code(404);
	die("Error: Producte $ID_product no existeix ni té SVG especial");
}

$link->close();
?>
