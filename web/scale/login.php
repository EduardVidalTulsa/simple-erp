<?php
if(!function_exists('contrasenya_ok')){
	// contrasenyes amb password_hash(); les antigues en text pla també es comproven (bases de dades antigues)
	function contrasenya_ok($desada, $donada){
		if($desada===null || $desada==='' || $donada==='') return false;
		if(preg_match('/^\$(2y|2a|argon2i|argon2id)\$/', $desada)) return password_verify($donada, $desada);
		return hash_equals((string)$desada, (string)$donada);
	}
}
//ini_set('display_errors', 1); ini_set('display_startup_errors', 1); 
//login part
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
//error_reporting(E_ALL );
date_default_timezone_set('Europe/Andorra');
// A la bàscula (quiosc), si peta alguna cosa (mysqli llança excepcions des de PHP 8.1)
// no ens podem quedar a la pantalla de l'error: es desa al log i es torna a /scale/.
// Les crides ajax (tlinea, tlinea_info) responen 503 buit i el següent interval ho torna a provar.
if(strpos($_SERVER['SCRIPT_NAME'] ?? '', '/scale/') === 0){
	set_exception_handler(function($e){
		error_log("scale {$_SERVER['REQUEST_URI']}: ".get_class($e).": ".$e->getMessage()." a ".$e->getFile().":".$e->getLine());
		if(!headers_sent())http_response_code(503);
		if(($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') == 'XMLHttpRequest')return;
		echo '<meta http-equiv="refresh" content="2;url=/scale/">'
			.'<div style="font-size:60px;padding:40px;">Error, torno a començar...</div>'
			.'<script>setTimeout(function(){location.replace("/scale/")},2000)</script>';
	});
}
require_once("{$_SERVER['DOCUMENT_ROOT']}/../keys.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/estil.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/taula.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/lib.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/tiquettpv.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/valsregal.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/mysqli.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/vars.php");
session_start();
$link=new mysqli($myserver,$myuser,$mypassword,$mydatabase);
$lang='ca';
if(isset($_REQUEST['tancar'])){
	session_unset();echo "he tancat";
}
//
//
//			Tema mida pantalla:
//
//
if(isset($_REQUEST['w']))$_SESSION['w']=$_REQUEST['w'];
if(isset($_REQUEST['h']))$_SESSION['h']=$_REQUEST['h'];
if (isset($_SESSION['vertical'])&&($_SESSION['vertical']==true)&&($_REQUEST['w']>$_REQUEST['h'])){
	unset ($_SESSION['w']);
	$_SESSION['vertical']=false;
}
if (isset($_SESSION['vertical'])&&($_SESSION['vertical']==false)&&($_REQUEST['h']>$_REQUEST['w'])){
	unset ($_SESSION['w']);
	$_SESSION['vertical']=true;
}
if(!isset($_SESSION['w'])){
	if(isset($_REQUEST['w']))$_SESSION['w']=$_REQUEST['w'];
	if(isset($_REQUEST['h']))$_SESSION['h']=$_REQUEST['h'];

	//echo"<script> window.location.href = \"$scale_uri?w=\"+window.innerWidth+\"&h=\"+screen.availHeight;</script>";
	if(!isset($_REQUEST['w']))echo"<script> window.location.href = \"./?w=\"+window.innerWidth+\"&h=\"+window.innerHeight;</script>";
}
$w=$_SESSION['w'];
$h=$_SESSION['h'];
if($w<$h)$_SESSION['vertical']=true;

if(!isset($_SESSION['login']))$_SESSION['login']=false;
if( $_SESSION['login']!=true){
	if(isset($_REQUEST['user']) &&($_REQUEST['user']!="")){
    		$_SESSION['user']=$_REQUEST['user'];
		$_SESSION['contrasenya']=($_REQUEST["contra"]);
		//debugtelegram($_REQUEST['user']);
    	}
    	if(isset($_SESSION['user'])&& ($_SESSION['user']!=""))	
        if(contrasenya_ok(mysql1r("SELECT `password` from `users` where `username`='".$link->real_escape_string($_SESSION['user'])."'",$link),$_SESSION["contrasenya"]) &
		(($_SESSION['user']!="") && $_SESSION["contrasenya"]!="")){
		if($volclaullarga){if (!preg_match('/^192\.168\..*|^10\..*|^127\.0\.0\.1$/',$_SERVER['REMOTE_ADDR'])){
			$my=mysqli_connect($myserver,$myuser,$mypassword,'adminer');
			$temporalkey=$my->query("SELECT `value` from `key` where `ID`='1'")->fetch_row()[0];
			if($_REQUEST['temporalkey']==$temporalkey){
				$_SESSION['login']=true;
				debugtelegram("{$w}x{$h} login\n{$_SESSION['user']}@{$_SERVER['REMOTE_ADDR']}",$_SERVER['HTTP_HOST']);
        			$login=true;
			}
		}
		}
		else{ 
			$_SESSION['login']=true;
			debugtelegram("{$w}x{$h} login\n{$_SESSION['user']}@{$_SERVER['REMOTE_ADDR']}",$_SERVER['HTTP_HOST']);
        		$login=true;
		}
    	}
    	else $login=false;
	}	
else $login=true;
	$uri=$_SERVER['SERVER_PORT']==80?'http://':'https://';
	$uri.=$_SERVER['HTTP_HOST'];
	$args=explode("/",$_SERVER['REQUEST_URI']);
if($login){
		$scale_uri=$_SERVER['SERVER_PORT']==80?'http://':'https://';
                $scale_uri.=$_SERVER['HTTP_HOST'];
                $args=explode("/",$_SERVER['REQUEST_URI']);
                if($args[1]!='scale'){
				if(isset($app)){
					$_SESSION[$app]['uri']=$scale_uri."/{$args[1]}/";
				}
				$scale_uri.="/scale/";
		}
		else $scale_uri.="/{$args[1]}/";
		if (!isset($_SESSION['ID_Empresa'])){
		$ID_Empresa=mysql1r("SELECT `ID_Empresa` FROM `users` where
			`username`='{$_SESSION['user']}'",$link);
		$idbascula=mysql1r("SELECT `id_bascula` FROM `users` where
			`username`='{$_SESSION['user']}'",$link);

		//$scale_uri=mysql1r("SELECT `scale_uri` FROM `Empresa` where
		//	`ID`='{$ID_Empresa}'",$link);
		$_SESSION['Empresa']=$link->query("SELECT * FROM `Empresa` where
			`ID`='{$ID_Empresa}'")->fetch_assoc();
// 		debugtelegram($_SESSION['Empresa']);
		$_SESSION['ID_Empresa']=$ID_Empresa;
		$_SESSION['idbascula']=$idbascula;
		$scale_lang=$_SESSION['Empresa']['scale_lang'];
		$_SESSION['scale_uri']=$scale_uri;
		$Empresa['scale_uri']=$scale_uri;
		$prefixrelacio=date('Y')."_Empresa_"."$ID_Empresa".'_';
	}
	else {
		$ID_Empresa=$_SESSION['ID_Empresa'];
		$idbascula=$_SESSION['idbascula'];
		$scale_uri=$_SESSION['scale_uri'];
		$Empresa['scale_uri']=$scale_uri;
		$prefixrelacio=date('Y')."_Empresa_"."$ID_Empresa".'_';
	}
}
else { 
	if(isset($app)){
		$_SESSION[$app]['uri']=$uri."/{$args[1]}/?login=no&u={$_SESSION['user']}";
	}
}
?>
