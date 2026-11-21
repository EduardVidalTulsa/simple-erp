<?php
//login part
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
require_once('../../keys.php');
require_once('phpgeneral/estil.php');
require_once('phpgeneral/taula.php');
require_once('phpgeneral/mysqli.php');
require_once('phpgeneral/lib.php');
require_once('vars.php');
require_once('../scale/phpgeneral/autentica.php');
session_start();
$link=new mysqli($myserver,$myuser,$mypassword,$mydatabase);
//$lang=new lang();
$lang='ca';
if(isset($_REQUEST['tancar'])){
	session_unset();
}
// usuari, contrasenya i (des de fora, amb $volclaullarga) clau temporal: veure ../scale/phpgeneral/autentica.php
$intent=isset($_REQUEST['user']) && $_REQUEST['user']!="";
if($intent) {
	$_SESSION['user']=$_REQUEST['user'];
	$_SESSION['contrasenya']=$_REQUEST['contra'] ?? '';
	$_SESSION['temporalkey']=$_REQUEST['temporalkey'] ?? '';
}
$login=false;
// sessió ja validada aquí o a /scale (amb la clau temporal si calia)
if((!empty($_SESSION['preus_login']) || !empty($_SESSION['login'])) && ($_SESSION['user'] ?? '')!='') $login=true;
elseif(isset($_SESSION['user']) && $_SESSION['user']!=""){
	[$login,$login_error]=autentica($link,$_SESSION['user'],$_SESSION['contrasenya'] ?? '',$_SESSION['temporalkey'] ?? '',$intent);
	if($login){ $_SESSION['preus_login']=true; unset($_SESSION['temporalkey']); }
}
// usuaris que no poden tocar preus (keys.php: $usuaris_sense_preus)
if(in_array($_SESSION['user'] ?? '', $usuaris_sense_preus ?? [], true))$login=false;
?>
