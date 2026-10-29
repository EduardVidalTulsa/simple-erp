<?php
require("login.php");
//pre($_REQUEST);
// die;
debugtelegram("fitxa:".$_REQUEST['id'],$_REQUEST['return_uri']);
if($_REQUEST['singkey']==$singkey)$login=true;
if ($login==false){
	estil_capcal(false);
	div('error',"style =\"position:absolute; top:".($h/2)."px;left:25px;font-size:75px;padding:2px;center;\"");
	a($_REQUEST['return_uri'],"Hi ha hagut un error al fitxar? l'administrador ha estat avisat, prem aqui i torna-hi'");
	debugtelegram($_REQUEST,"error al fitxar");
	tdiv();
	//sleep(10);

	//echo "<script>location.replace(\"{$_REQUEST['return_uri']}\");</script>";
	//echo "<script>setTimeout(function(){window.location.replace(\"{$_REQUEST['return_uri']}\")},2000);</script>";

	$link->close();
//	header("Location: {$_REQUEST['return_uri']}");
	die;

}
mysql_quer("INSERT INTO `SingInAtWork` (`ID`,`ID_Empresa`,`diahora`)
VALUES ('{$_REQUEST['id']}', '{$_REQUEST['ID_Empresa']}', now());",$link);
$scale_uri=urldecode($_REQUEST['return_uri']);
//a($scale_uri);
$link->close();
estil_capcal(false);
div('error',"style =\"position:absolute; top:".($h/2)."px;left:25px;font-size:75px;padding:2px;center;\"");
a($_REQUEST['return_uri'],"[{$_REQUEST['id']}] Fitxa correcte, espera un moment o prem aqui");
tdiv();
//sleep(10);

//echo "<script>location.replace(\"{$_REQUEST['return_uri']}\");</script>";
echo "<script>setTimeout(function(){window.location.replace(\"{$_REQUEST['return_uri']}\")},2000);</script>";

//die;
//header("Location: {$scale_uri}");die;
?>
