<?php
//echo "pdf";
	$uri=$_SERVER['SERVER_PORT']==80?'http://':'https://';
	$uri.=$_SERVER['HTTP_HOST'];
	$args=explode("/",$_SERVER['REQUEST_URI']);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
date_default_timezone_set('Europe/Andorra');
require_once("{$_SERVER['DOCUMENT_ROOT']}/../keys.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/estil.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/taula.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/lib.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/mysqli.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/vars.php");
session_start();
$link=new mysqli($myserver,$myuser,$mypassword,$mydatabase);
$lang='ca';
//estil_capcal("");
//echo "Hola!";
$uuid=preg_replace('/[^a-zA-Z0-9]/','',$_REQUEST['uuid'] ?? ''); // només alfanumèric: buscauuid() el posa al SQL
// el javascript de més avall pregunta aquí si p.php ja ha refet el pdf (no ha de tornar a encuar-lo)
if(isset($_REQUEST['ready'])){
    clearstatcache();
    $f="{$_SERVER['DOCUMENT_ROOT']}/pdf/$uuid.pdf";
    $mtime=file_exists($f)?filemtime($f):0;
    $pendent=false;
    $l=$link->query("SHOW TABLES LIKE '%\\_Empresa\\_%\\_pdf'");
    while(!$pendent and $t=$l->fetch_row())
        $pendent=$link->query("SELECT 1 FROM `{$t[0]}` WHERE `uuid`='$uuid' AND `pdf_fet`='0'")->num_rows>0;
    echo (!$pendent and $mtime>=(int)$_REQUEST['ready'])?$mtime:0;
    die;
}
$t0=time();
$resultat=buscauuid($uuid,$link);

//echo "Sembla que la  en format pdf no està llesta! <br/>";
if($resultat==FALSE) echo "aquest identificador no existeix a la copia local de les bases de dades, potser encara esta a la copia local de la botiga, torna a la seguent hora, gracies!";
if($resultat['uuid']==$uuid){
    //echo "La relació tipus \"{$resultat['tipus_relacio']['Value']}\" amb codi $uuid ha estat trobada i en uns minuts estarà preparada, quan estigui sereu redirigits a l'arxiu en qüestió";
 $uri="{$uri}/pdf/$uuid.pdf";
 //pre($resultat);
 echo html_relacio($resultat);
// p.php només agafa feines de més de 10s, esperem que el pdf sigui més nou que $t0; ?v= perquè el navegador no tregui el vell de la cau
echo "<script>
    var inici=Date.now();
    (function mira(){
        fetch('/pdf/?uuid=$uuid&ready=$t0').then(r=>r.text()).then(function(m){
            m=parseInt(m);
            if(m>0) window.location.href='$uri?v='+m;
            else if(Date.now()-inici>120000) window.location.href='$uri';
            else setTimeout(mira,2000);
        }).catch(function(){setTimeout(mira,2000);});
    })();
    </script>";
}
//estil_tancament($link,__FILE__);
?>
