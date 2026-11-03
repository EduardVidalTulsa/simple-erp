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
use Mike42\Escpos\EscposImage;
require_once('phpgeneral/receipt.php');

date_default_timezone_set('Europe/Andorra');
// pre($_REQUEST);die;
if($_REQUEST['uuid']==''){$link->close(); header("$scale_uri");}
if(isset($_REQUEST['ID_Empresa']))$ID_Empresa=$_REQUEST['ID_Empresa'];
if(isset($_REQUEST['year']))$year=$_REQUEST['year'];
else $year=date("Y");
$r=detallrelacio($_REQUEST['uuid'],$ID_Empresa,$year,$link);

$taula_index="{$year}_Empresa_{$ID_Empresa}_index_relacio";
// pre($r);die;
/*ALTER TABLE `2021_Empresa_100_index_relacio`
fADD `print` tinyint NOT NULL DEFAULT '0' AFTER `uuid` COMMENT current_timestamp()';
 */;
if(!mysql1r("SHOW COLUMNS FROM `{$taula_index}` LIKE 'print'",$link,1)){
	$crea="ALTER TABLE  `$taula_index` ADD `print` tinyint NOT NULL DEFAULT '0' COMMENT '".date("D Y/m/d G:i:s")."';";
	//mysqli_query($link,"select print from `$taula` ") or
	//echo $crea;
	try{ 
		mysqli_query($link,$crea);
	}catch (Exception $e){
		echo "";//ignore error, means print field already exist
	}
}
$recopia=mysql1r("select print from $taula_index where `uuid`='{$_REQUEST['uuid']}'; ",$link);
mysqli_query($link,"UPDATE $taula_index SET `print` = '1' WHERE  `uuid`='{$_REQUEST['uuid']}'");
//debugtelegram($_SESSION['Empresa']);
if ($_SESSION['Empresa']['bool_ticket_printer']=='1'){
try {
$ticket=$r['uuid'].'.lp';
$connector = new FilePrintConnector(__DIR__."/$ticket");
$printer = new PrinterTiquet($connector);
/* Initialize */
$printer -> initialize();
$printer -> selectCharacterTable($taula);
/* Text */
//head:

$printer -> setJustification(Printer::JUSTIFY_CENTER);
$printer -> capcalera($r['Empresa']);// si tiquet_cut_before_head1_bool, ja la va imprimir l'anterior (tallacapcalera)
$printer -> setJustification(Printer::JUSTIFY_CENTER);
$printer -> selectPrintMode($f2);
$printer -> text("{$r['Empresa']['termal_head_2']}\n");
$printer -> text("{$r['Empresa']['termal_head_3']}\n");
$printer -> selectPrintMode($f3);
$idrelacio="{$r['tipus_relacio']['Value']}:{$r['Empresa']['ID']}.{$r['ID']}";
if($recopia>0)$idrelacio.='*';// * = reimpressió, estalvia la línia "Reimpresió de tiquet"
if($r['tax_detall']==1){
	$preus="PREUS IVA NO INClÒS";//linees actives
	}
else $preus="PREUS IVA INClÒS";
	$data=" Data:".latindate($r['data']).' '. date("H:i")."\n";
$preus=str_pad($preus,$llpreus-(strlen($idrelacio)+strlen($data)),' ',STR_PAD_BOTH);
$printer->text($idrelacio.$preus.$data);
	$printer->feed(1);
$printer -> selectPrintMode();
	$printer -> setJustification(Printer::JUSTIFY_CENTER);
if($r['ID_client']>1)$printer -> text(" Client:".urldecode($r['fiscal_name']));
if($r['NIF']!='')$printer -> text(" NIF:".urldecode($r['NIF']));
if($r['fiscal_address']!='')$printer -> text("\nD. Fiscal:".urldecode($r['fiscal_address'])."\n");
if($r['comercial_name']!='')$printer -> text(" Nom comercial: ".urldecode($r['comercial_name'])."\n");
if($r['deliver_address']!='')$printer -> text(" Direccio entrega: ".urldecode($r['deliver_address'])."\n");
if($r['phone']!='')$printer -> text(" Telèfon: ".urldecode($r['phone'])."\n");
// $printer->feed(1);
	$printer -> setJustification(Printer::JUSTIFY_RIGHT);
foreach ($r['active_lines'] AS $v){
	$v['tax_detall']=$r['tax_detall'];
	$printer->line($v);
}
$printer -> setJustification(Printer::JUSTIFY_LEFT);
$printer -> selectPrintMode();
// $printer -> selectCharacterTable();
$printer->feed(1);
	$printer-> selectCharacterTable();
$descompte="-".$r['discount']."%";
if($r['discount']!='0')
	$printer->text(str_pad("Descompte:",$lldescompte-strlen($descompte)).$descompte."\n");
unset($detall);
		$printer-> selectCharacterTable($taula);
// if($index_relacio['tax_detall']==1){
	$base=number_format($r['Base'],2,$decimalsing,$thousandsing).chr($euro);
	$printer ->textRaw(str_pad("Base imponible:",$lldescompte-strlen($base)).$base);
	$printer->text("\n");
// }
//arsort($r['IVA'],SORT_NUMERIC);

foreach ($r['IVA'] AS $iva => $v){
	if ($v!=''){
		$fiva=number_format_trim($iva,2,$decimalsing,$thousandsing);
		$v=number_format_trim($v,2,$decimalsing,$thousandsing);
		$detall.=" {$fiva}%: $v".chr($euro);
	}
}
$iva=str_pad("IVA :",$lldescompte-strlen($detall));

	$printer->textRaw($iva.$detall);

// arsort($r['EC']);
if(($r['Empresa']['sell_wholesale']=='1')&&($r['equivalency_charge']==1)){
	$printer->text("\nR.E. ");
	foreach ($r['EC'] AS $ec => $v){
		$v=number_format_trim($v,2,$decimalsing,$thousandsing);
		if ($v!='')$printer -> text(" RE {$ec}%:  $v");$printer->euro();
	}
}
$printer->feed(1);
$printer-> selectCharacterTable();
$printer -> selectPrintMode($fref);
$apagar="A Pagar:".number_format($r['Total'],2,$decimalsing,$thousandsing).chr($euro)."\n";
// $printer -> text(str_pad("Ref.: {$r['activelines']}",$llref-strlen($apagar)));
$printer -> selectPrintMode('56');
   $printer -> setJustification(Printer::JUSTIFY_RIGHT);
	$printer -> selectCharacterTable($taula);
$printer -> textRaw($apagar);
$printer -> setJustification(Printer::JUSTIFY_LEFT);
$printer -> selectPrintMode();
	$printer -> text(str_pad("Atés per: {$r['Nom_Venedor']}",$llates-strlen($r['Empresa']['termal_tail_1'])));

$printer -> selectPrintMode('16');
$printer -> text("{$r['Empresa']['termal_tail_1']}");
	$printer -> selectPrintMode();
	$printer -> text("\n");
$printer-> selectPrintMode('0');
$printer -> setJustification(Printer::JUSTIFY_CENTER);
$printer -> text("Gràcies per la vostra compra\n");
	if(isset($r['email']))$r['email']=urldecode($r['email']);
	if (correuvalid($r['email'])){
		$printer -> text("Us hem enviat copia en pdf de la factura a\n {$r['email']}\n");
	}
// peu: rebuts del TPV del banc (QR llegit a la bàscula) amb referència, targeta i QR
try {
	tpv_imprimeix_peu($printer, $r, $year, $ID_Empresa, $link);
} catch (Throwable $e) {
	debugtelegram("tiquet.php rebut TPV: ".$e->getMessage(), $_SERVER['HTTP_HOST']);
}
//linees borrades
if($r['deletedlines']>0){
	//$printer -> cut(66);
	$printer -> selectPrintMode();
	$printer -> text("Linees borrades \n");
	foreach ($r['deleted_lines'] AS $v){
		$printer -> line($v,false);
	}
}
$printer -> tallacapcalera($r['Empresa']);
$printer -> close();
// pre();print_r($r);tpre();
$print_ok = ticket_print_command($ticket);
//debugtelegram($r,$_SERVER['HOST_NAME']."tiquetrelacio",$Empresa['telegram_relacions']);
`rm *.lp -f`;
if($print_ok === false) {
    $link->close();
    $close_param = $_REQUEST['close'] ? '&close=1' : '';
    header("Location: {$scale_uri}tiquet_simplificat.php?uuid={$r['uuid']}&year=$year&ID_Empresa=$ID_Empresa{$close_param}");
    die;
}
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
	debugtelegram("La empresa no té assignada impresora de tiquets",$_SERVER['HTTP_HOST']);
	debugtelegram($r,"{$_SERVER['HTTP_HOST']} -> $rel:",$_SESSION['Empresa']['telegram_relacions']);
}
//todo redirigir a a4.php si es el cas
if ($_REQUEST['close'])echo "<script>window.open('', '_self', ''); window.close();</script>";
else header("Location: $scale_uri");
?>
