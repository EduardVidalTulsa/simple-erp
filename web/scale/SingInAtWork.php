<?php
require("login.php");
// return_uri només pot apuntar a aquest servidor o a una seu coneguda (evita XSS i redirecció oberta)
function return_uri_segura($u,$link){
	$u=(string)$u; $defecte='/scale/';
	if(preg_match('#^https?%3A#i',$u))$u=urldecode($u);
	if($u===''||preg_match('/[\x00-\x20"\'<>\\\\]/',$u))return $defecte;
	if($u[0]==='/')return (strlen($u)>1&&($u[1]==='/'||$u[1]==='\\'))?$defecte:$u;
	$p=parse_url($u);
	if(!$p||!in_array(strtolower($p['scheme']??''),['http','https'],true)||empty($p['host']))return $defecte;
	$hosts=[strtolower($_SERVER['HTTP_HOST']??'')];
	$r=$link->query("SELECT scale_uri,url_seu,url_public FROM Empresa");
	if($r)foreach($r->fetch_all(MYSQLI_ASSOC) as $e)foreach($e as $v)if($v&&($h=parse_url($v,PHP_URL_HOST)))$hosts[]=strtolower($h);
	return in_array(strtolower($p['host']),$hosts,true)?$u:$defecte;
}
$ret=return_uri_segura($_REQUEST['return_uri']??'',$link);
//pre($_REQUEST);
// die;
debugtelegram("fitxa:".$_REQUEST['id'],$_REQUEST['return_uri']);
if($_REQUEST['singkey']==$singkey)$login=true;
if ($login==false){
	estil_capcal(false);
	div('error',"style =\"position:absolute; top:".($h/2)."px;left:25px;font-size:75px;padding:2px;center;\"");
	a($ret,"Hi ha hagut un error al fitxar? l'administrador ha estat avisat, prem aqui i torna-hi'");
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
VALUES ('".(int)$_REQUEST['id']."', '".(int)$_REQUEST['ID_Empresa']."', now());",$link);
$scale_uri=$ret;
//a($scale_uri);
$link->close();
estil_capcal(false);
div('error',"style =\"position:absolute; top:".($h/2)."px;left:25px;font-size:75px;padding:2px;center;\"");
a($ret,"[".(int)$_REQUEST['id']."] Fitxa correcte, espera un moment o prem aqui");
tdiv();
//sleep(10);

//echo "<script>location.replace(\"{$_REQUEST['return_uri']}\");</script>";
echo "<script>setTimeout(function(){window.location.replace(".json_encode($ret,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).")},2000);</script>";

//die;
//header("Location: {$scale_uri}");die;
?>
