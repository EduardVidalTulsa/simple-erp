<?php
/*
 * PDFs públics de les relacions. nginx envia aquí tots els /pdf/<doc>.pdf (?doc=<doc>&pdf=1).
 *  - <doc> = uuid d'una relació anònima: es pot veure sempre (com abans).
 *  - <doc> = token signat (enllac_pdf a phpgeneral/lib.php): relació amb dades personals, 24 h.
 *  - El personal que ha entrat a l'ERP els veu tots.
 *  - Si un enllaç ha caducat, es pot demanar un enllaç nou al correu de la relació.
 * Si el PDF ja existeix es serveix directament; si no (o amb ?updated) es fa com sempre: es mostra
 * la relació i quan p.php l'ha fet, s'hi va.
 */
	$uri=$_SERVER['SERVER_PORT']==80?'http://':'https://';
	$uri.=$_SERVER['HTTP_HOST'];
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

// només alfanumèric: després va al SQL i a noms de fitxer
$doc=preg_replace('/[^a-zA-Z0-9]/','',$_REQUEST['doc'] ?? $_REQUEST['uuid'] ?? '');
$uuid=$doc; $per_token=false; $caducat=false;
if($doc!=='' && $doc[0]==='t' && ($tk=pdf_token_llegeix($doc))){ $uuid=$tk['uuid']; $per_token=true; $caducat=$tk['caducat']; }
$fitxer="{$_SERVER['DOCUMENT_ROOT']}/pdf/$uuid.pdf";

// el javascript de més avall pregunta aquí si p.php ja ha refet el pdf (no ha de tornar a encuar-lo)
if(isset($_REQUEST['ready'])){
    clearstatcache();
    $mtime=file_exists($fitxer)?filemtime($fitxer):0;
    $pendent=false;
    $l=$link->query("SHOW TABLES LIKE '%\\_Empresa\\_%\\_pdf'");
    while(!$pendent and $t=$l->fetch_row())
        $pendent=$link->query("SELECT 1 FROM `{$t[0]}` WHERE `uuid`='$uuid' AND `pdf_fet`='0'")->num_rows>0;
    echo (!$pendent and $mtime>=(int)$_REQUEST['ready'])?$mtime:0;
    die;
}

// la relació (sense tocar res: buscauuid() la tornaria a encuar)
function relacio_per_uuid($uuid,$link){
	if($uuid==='')return null;
	$l=$link->query("SHOW TABLES LIKE '%\\_Empresa\\_%\\_index\\_relacio'");
	while($t=$l->fetch_row()){
		$f=$link->query("SELECT * FROM `{$t[0]}` WHERE `uuid`='".$link->real_escape_string($uuid)."'")->fetch_assoc();
		if($f){ $a=explode('_',$t[0]); $f['_any']=$a[0]; $f['_empresa']=$a[2]; return $f; }
	}
	return null;
}
$r=relacio_per_uuid($uuid,$link);
$sessio_ok=!empty($_SESSION['login']) || !empty($_SESSION['preus_login']);
$permes=$sessio_ok || empty($pdf_token_secret) || ($per_token && !$caducat) || ($r && !relacio_personal($r));

if(!$permes && !$r && !$per_token){
	// encara no és a la còpia local d'aquesta seu (o no existeix): no en sabem res
	echo "aquest identificador no existeix a la copia local de les bases de dades, potser encara esta a la copia local de la botiga, torna a la seguent hora o be demana-ho per correu a la teva botiga";
	die;
}
if(!$permes){
	header('Cache-Control: no-store');
	estil_capcal("Document");
	echo '<div style="max-width:640px;margin:40px auto;font-size:20px;line-height:1.5">';
	echo $caducat ? "<p>Aquest enllaç ja ha caducat: dura 24 hores.</p>"
	              : "<p>Aquest document té dades personals: només s'hi pot entrar amb l'enllaç que s'envia per correu, que dura 24 hores.</p>";
	echo "<p>El document també t'ha arribat adjunt al correu.</p>";
	$email=$r ? trim(urldecode((string)$r['email'])) : '';
	if($r && correuvalid($email)){
		$amagat=preg_replace('/^(.).*(@.*)$/','$1***$2',$email);
		if(($_SERVER['REQUEST_METHOD'] ?? '')==='POST' && isset($_POST['reenvia'])){
			$marca=sys_get_temp_dir().'/pdf-reenvia-'.md5($uuid);
			if(is_file($marca) && time()-filemtime($marca)<600){
				echo "<p>Ja t'hem enviat un enllaç nou fa poc a <b>".htmlspecialchars($amagat)."</b>. Mira el correu (i la carpeta de correu brossa).</p>";
			} else {
				touch($marca);
				$empresa=$link->query("SELECT * FROM Empresa WHERE `ID`='".(int)$r['_empresa']."'")->fetch_assoc();
				$enllac=enllac_pdf($r, $uri);
				require_once("{$_SERVER['DOCUMENT_ROOT']}/vendor/autoload.php");
				try{
					$mail=new PHPMailer\PHPMailer\PHPMailer(true);
					$mail->isSMTP(); $mail->Host=$em['host']; $mail->SMTPAuth=true; $mail->Timeout=15;
					$mail->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; $mail->Port=587;
					$mail->Username=$em['user']; $mail->Password=$em['passwd']; $mail->CharSet='UTF-8';
					$mail->setFrom($empresa['email'], $empresa['Value']);
					$mail->addAddress($email);
					$mail->Subject='Enllaç al vostre document';
					$mail->isHTML(true);
					$mail->Body='<p>Aquí teniu un enllaç nou al document, vàlid durant 24 hores:</p><p><a href="'.htmlspecialchars($enllac).'">'.htmlspecialchars($enllac).'</a></p>';
					$mail->AltBody="Enllaç nou al document (vàlid 24 hores): $enllac";
					$mail->send();
					echo "<p>T'hem enviat un enllaç nou a <b>".htmlspecialchars($amagat)."</b>.</p>";
				}catch(Throwable $e){
					@unlink($marca);
					error_log("pdf reenvia $uuid: ".$e->getMessage());
					echo "<p>No s'ha pogut enviar el correu. Torna-ho a provar més tard.</p>";
				}
			}
		} else {
			echo '<form method="post"><input type="hidden" name="reenvia" value="1">'
				.'<button type="submit" style="font-size:20px;padding:10px 20px">Envia\'m un enllaç nou a '.htmlspecialchars($amagat).'</button></form>';
		}
	}
	echo '</div>';
	die;
}

// el pdf ja hi és: es dona tal qual (sense ?updated, que vol dir que s'ha de refer)
clearstatcache();
if(isset($_REQUEST['pdf']) && empty($_REQUEST['updated']) && is_file($fitxer)){
	header('Content-Type: application/pdf');
	header('Content-Disposition: inline; filename="'.$uuid.'.pdf"');
	header('Content-Length: '.filesize($fitxer));
	header('Cache-Control: '.($r && relacio_personal($r) ? 'private, no-store' : 'public, max-age=300'));
	header('X-Robots-Tag: noindex');
	readfile($fitxer);
	die;
}

// com sempre: (re)fer el pdf i mostrar la relació mentre p.php el fa
$t0=time();
$resultat=buscauuid($uuid,$link);
if($resultat==FALSE) echo "aquest identificador no existeix a la copia local de les bases de dades, potser encara esta a la copia local de la botiga, torna a la seguent hora o be demana-ho per correu a la teva botiga";
if(is_array($resultat) && $resultat['uuid']==$uuid){
 $desti="{$uri}/pdf/$doc.pdf";
 echo html_relacio($resultat);
echo "<script>
    var inici=Date.now();
    (function mira(){
        fetch('/pdf/?doc=$doc&ready=$t0').then(r=>r.text()).then(function(m){
            m=parseInt(m);
            if(m>0) window.location.href='$desti?v='+m;
            else if(Date.now()-inici>120000) window.location.href='$desti';
            else setTimeout(mira,2000);
        }).catch(function(){setTimeout(mira,2000);});
    })();
    </script>";
}
?>
