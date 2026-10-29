<?php
if(!function_exists('contrasenya_ok')){
	// contrasenyes amb password_hash(); les antigues en text pla també es comproven (bases de dades antigues)
	function contrasenya_ok($desada, $donada){
		if($desada===null || $desada==='' || $donada==='') return false;
		if(preg_match('/^\$(2y|2a|argon2i|argon2id)\$/', $desada)) return password_verify($donada, $desada);
		return hash_equals((string)$desada, (string)$donada);
	}
}
//login part
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
require_once('../../keys.php');
require_once('phpgeneral/estil.php');
require_once('phpgeneral/taula.php');
require_once('phpgeneral/mysqli.php');
require_once('phpgeneral/lib.php');
require_once('vars.php');
session_start();
$link=new mysqli($myserver,$myuser,$mypassword,$mydatabase);
//$lang=new lang();
$lang='ca';
if(isset($_REQUEST['tancar'])){
	session_unset();
}
if(isset($_REQUEST['user']) &&($_REQUEST['user']!="")) {
	$_SESSION['user']=$_REQUEST['user'];
	      $_SESSION['contrasenya']=($_REQUEST["contra"]);
}
if(isset($_SESSION['user'])&& ($_SESSION['user']!=""))	if(contrasenya_ok(mysql1r("SELECT `password` from `users` where `username`='".$link->real_escape_string($_SESSION['user'])."'",$link),$_SESSION["contrasenya"])& (($_SESSION['user']!="") && $_SESSION["contrasenya"]!=""))
$login=true;
else $login=false;
?>
