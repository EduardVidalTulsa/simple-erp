<?php
require_once('login.php');
// imprimeixo tiquet
if($login==false){
	echo "nologin";
	$link->close();
	die;
}
require '../vendor/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
require_once('phpgeneral/receipt.php');
$recepta['1'] =[
    "INGREDENTS:",
    "• 500g farina d’ametlla",
    "• 300g o 400g de sucre",
    "• Una patata mitjana o dues de petites",
    "• ou",
    "• pinyons, ametlles, codony, coco, fruita confitada, xocolata, etc.",
    "PREPARACIÓ:",
    "Bullim les patates amb pell (o al microones embolicades amb una bossa de plàstic), un cop toves, les escorrem, les pelem i preparem un puré. A continuació hi afegim el sucre, la farina d’ametlla (i si ens agrada llimona ratllada) i ho barregem.",
    "Suggeriment: deixar reposar la massa unes 3 hores.",
    "De pinyons:",
    "Agafem trossets de massa i fem boles. Passem les boles pel rovell d'ou batut i les arrebossem amb pinyons.",
    "D’ametlla:",
    "Agafem trossets de la massa i fem petites barres rectangulars. Les passem per rovell d’ou i seguidament ho arrebossem amb granet o palet d’ametlla.",
    "De codony:",
    "Aplanem la massa i a sobre hi posem un tros de codony quadrat. Emboliquem el codony amb la massa com si fos un braç de gitano i després el tallem a trossos al nostre gust.",
    "De xocolata:",
    "Agafem un bon tros de massa i hi barregem xocolata en pols. Els arrebossarem amb granet de xocolata. Podem fer formes diferents i divertides.",
    "De fruites:",
    "Agafem la massa, fem boles i al damunt de cada bola hi posem alguna fruita confitada: una cirera, una mica de taronja...",
    "De coco:",
    "Agafem el mateix volum de massa i de coco i ho barregem ben barrejat. Podem fer els panellets de coco amb forma de piràmide. Pintem les puntes de la piràmide amb rovell d’ou.",
    "Etc",
    "COCCIÓ:",
    "Col·loquem els panellets a la plata del forn i els courem  a 225-250 graus de 8 a 10 minuts aproximadament.",
];
$recepta['2'] =[
"   Bunyols de l'Empordà",
"INGREDENTS: al gust, però més o menys:",
    "• 500g farina de força",
    "• 150g sucre blanc",
    "• 2 ous",
    "• 125ml llet",
    "• 30g llevat fresc",
    "• 25g canella en pols (opcional)",
    "• essència de llimona",
    "• 50g~150g matafaluga en pols (anís en espècie)",
    "• 1 gotet de anís líquid de llimona",
    "• sucre de vainlla",
    "• ratlladura d'una llimona",];

$recepta['3']=[
"	Enviaments a domicili",
"Si viviu lluny o voleu que us enviem els",
"nostres productes a casa, no dubteu en ",
"escriure'ns a via whatsapp o telegram ",
];
$tail=[$_SESSION['Empresa']['termal_head_1'] ?? '', $_SESSION['Empresa']['termal_head_2'] ?? ''];
date_default_timezone_set('Europe/Andorra');

//debugtelegram($_SESSION);
// pre($_REQUEST);die;
$ticket="out.lp";
//debugtelegram($_SESSION['Empresa']);
if ($_SESSION['Empresa']['bool_ticket_printer']=='1'){
try {
$connector = new FilePrintConnector("out.lp");
$printer = new PrinterTiquet($connector);
/* Initialize */
$printer -> initialize();
$printer -> selectCharacterTable($taula);
/* Text */
//head:

$printer -> setJustification(Printer::JUSTIFY_CENTER);
$printer -> capcalera($_SESSION['Empresa']);// si tiquet_cut_before_head1_bool, ja la va imprimir l'anterior (tallacapcalera)
$printer -> setJustification(Printer::JUSTIFY_CENTER);
$printer -> selectPrintMode('0');
$printer -> text("{$_SESSION['Empresa']['termal_head_2']}\n");
$printer -> text("{$_SESSION['Empresa']['Phone']}\n");

$printer -> feed(1);
$printer -> setJustification(Printer::JUSTIFY_LEFT);
$printer -> selectPrintMode('0');

foreach ($recepta[$_REQUEST['ID']] as $lineaOriginal){
	$lineaWrap = customWordWrap($lineaOriginal, $llrecepta);
	$linees = explode("\n", $lineaWrap);
    	foreach($linees as $linea) {
        	$printer->text($linea . "\n");
    	}
}
foreach ($tail as $lineaOriginal){
	$lineaWrap = customWordWrap($lineaOriginal, $lldescompte);
	$linees = explode("\n", $lineaWrap);
    	foreach($linees as $linea) {
        	$printer->text($linea . "\n");
    	}
}

$printer -> setJustification(Printer::JUSTIFY_CENTER);
$printer -> selectPrintMode('16');

$printer -> text("{$_SESSION['Empresa']['termal_tail_1']}\n");
$printer -> selectPrintMode('0');
$printer -> text("Gràcies per la vostra compra\n");

$printer -> tallacapcalera($_SESSION['Empresa']);
$printer -> close();
// pre();print_r($r);tpre();
ticket_print_command($ticket);
//debugtelegram($r,$_SERVER['HOST_NAME']."tiquetrelacio",$Empresa['telegram_relacions']);
`rm $ticket -f`;
//echo `cat out.lp`;
 	} catch (Exception $e) {
		debugtelegram("tiquet.php" . $e -> getMessage() . "\n",$_SERVER['HTTP_HOST']);
		unset ($r['Empresa']);
		$rel="{$r['tipus_relacio']['Value']}: $ID_Empresa.{$r['ID']}";
		unset ($r['tipus_relacio']);
		debugtelegram($r,"{$_SERVER['HTTP_HOST']} -> $rel:",$_SESSION['Empresa']['telegram_relacions']);
		
	}
}
else {
	//la empresa no té impresora de tiquets
	unset ($r['Empresa']);
	$rel="{$r['tipus_relacio']['Value']}: $ID_Empresa.{$r['ID']}";
	unset ($r['tipus_relacio']);
	debugtelegram($r,"{$_SERVER['HTTP_HOST']} -> $rel:",$_SESSION['Empresa']['telegram_relacions']);
}
//todo redirigir a a4.php si es el cas
if ($_REQUEST['close'])echo "<script>window.open('', '_self', ''); window.close();</script>";
else header("Location: $scale_uri");
?>
