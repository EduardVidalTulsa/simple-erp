<?php
require '../vendor/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\EscposImage;
require("login.php");
require_once("phpgeneral/receipt.php"); // PrinterTiquet i tallacapcalera, comú amb tiquet.php
if(!$login){
	header("Location: $scale_uri");
	die;
}
$ticket="out.lp";
try {
$connector = new FilePrintConnector("out.lp");
$printer = new PrinterTiquet($connector);
/* Initialize */
$printer -> initialize();
$printer -> selectCharacterTable(2);
$printer -> text("Total Empresa $ID_Empresa ". date(" j/m/Y h:i:s A")."\n");

function tancament($q){
	global $link,$printer,$ID_Empresa;
	echo $q;
	$qlist=$link->query($q)or die("merda".$link->error);
	$printer->text($q);
	while($rel = $qlist->fetch_assoc()){
		$r=detallrelacio($rel['uuid'],$ID_Empresa,date("Y"),$link);
        //pre($r);
		/*
		tot aixó no ho necesitem per ara
		foreach ($r['IVA']as $iva=>$viva){
				$t['B'][$iva]=$t[ 'B'][$iva]+$r['B'][$iva];
				$t['T'][$iva]=$t[ 'T'][$iva]+$r['T'][$iva];
		}*/ 
		$t['via'][$r['ID_Pagament']][$r["ID_Venedor"]]["Total"]=$t['via'][$r['ID_Pagament']][$r["ID_Venedor"]]["Total"]+$r["Total"];
		$t['venedor'][$r["ID_Venedor"]]["Total"]=$t['venedor'][$r["ID_Venedor"]]["Total"]+$r["Total"];
		$t['via'][$r['ID_Pagament']]["Total"]=$t['via'][$r['ID_Pagament']]["Total"]+$r["Total"];
	}
	foreach	 ($t['via']as $ID_Pagament => $valors){
		br();
		$printer -> text( "cobrat en ".mysql1r("select value from Pagament where ID=$ID_Pagament",$link).":\n");
		br();
		$printer -> text("{$valors['Total']}\n");
	}
	//pre($t['via']);
    $suma=$t['via'][1]['Total']+$t['via'][2]['Total'];
    echo $suma; br();
	$printer -> text("Suma d'efectiu i tarjetes : $suma \n");


	br();
}
a($scale_uri,h1r("tornar"));
br();
$obert="select count(*) from {$prefixrelacio}index_relacio WHERE `data`=curdate() and relacio_oberta='1'";
if(mysql1r($obert,$link)>0){debugtelegram("hi ha ".mysql1r($obert,$link)." tiquets oberts, no puc fer el tancament de caixa ", $_SESSION['Empresa']['Value'], $_SESSION['Empresa']['telegram_relacions']);
header("Location: $scale_uri");
die;

}
$q="select * from {$prefixrelacio}index_relacio WHERE `data`=subdate(curdate(), 2) and `ID_tipus_relacio`='3'";
tancament($q);//abans d'ahir
$printer->feed(1);
$q="select * from {$prefixrelacio}index_relacio WHERE `data`=subdate(curdate(), 1) and `ID_tipus_relacio`='3'";
tancament($q);//ahir
$printer->feed(1);
$q="select * from {$prefixrelacio}index_relacio WHERE `data`=curdate() and `ID_tipus_relacio`='3'";
tancament($q);//avui

$printer -> tallacapcalera($_SESSION['Empresa']);
$printer -> close();
// pre();print_r($r);tpre();
ticket_print_command($ticket);
 `rm $ticket -f`;
echo `echo out.lp`;
header("Location: $scale_uri");

 	} catch (Exception $e) {
     echo "Couldn't print to this printer: " . $e -> getMessage() . "\n";}
?>
