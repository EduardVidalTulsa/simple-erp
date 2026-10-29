<?php
/*
Aquest script està pensat per executar-se en segon pla a través d'una crida periòdica
a la línea de comandes tipus:
  while [ 1 ] ; do curl http[s?]://$_SERVER['HTTP_HOST']/scale/p.php\?ID\=$ID_Empresa[&debug\=TRUE?] ; sleep 10;done;
Només un dels ordinadors de la granja ha de fer correr això. 
En cas contrari surtirien etiquetes duplicades
mireu a /etc/rc.local
ara també compila els .tex en .pdf i els envia per telegram i opcionalment per correu.


*/
date_default_timezone_set('Europe/Andorra');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
$lockfile = '/tmp/scale_p.lock';
if(isset($_REQUEST['debug']))$debug=true;
if (file_exists($lockfile) && (time() - filemtime($lockfile)) < 300) {
    // Si el fitxer de bloqueig existeix i és més recent que 5 minuts, sortim.
	if(!$debug) die("Script ja en execució.");
	else echo "diu que el fitxer existeix, seguim";
} else {
    // Creem o actualitzem el fitxer de bloqueig
    touch($lockfile);
    // l'esborrem encara que l'script peti: si no, la cua d'etiquetes queda parada 5 minuts
    register_shutdown_function(function() use ($lockfile){ @unlink($lockfile); });
}

require_once('../../keys.php');
require_once('phpgeneral/estil.php');
require_once('phpgeneral/taula.php');
require_once('phpgeneral/mysqli.php');
require_once('phpgeneral/lib.php');
require_once('phpgeneral/etiquetapreu.php');
require_once('vars.php');
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use League\OAuth2\Client\Provider\Google;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\ValidationException;

session_start();

if($_REQUEST['saluda']){echo"bon dia!\n";
	debugtelegram($_REQUEST);
}
if(!isset($_REQUEST['ID']))$_REQUEST['ID']=100;
$link=new mysqli($myserver,$myuser,$mypassword,$mydatabase);
$empresa=$link->query("SELECT * FROM Empresa where `ID`='{$_REQUEST['ID']}'")->fetch_assoc();
//debugtelegram($empresa);
#$args=explode("/",$_SERVER['REQUEST_URI']);//$lang=new lang();
$lang='ca';
function tag_print_command($tag_filename,$empresa){
	//global $empresa;
	//debugtelegram($empresa);
	//debugtelegram(" ssh {$empresa['printerserver']} \" lp /tmp/tmp.ps -d {$empresa['sticker_printer']}\" ");
	//exec ("scp /tmp/tmp.ps {$empresa['printerserver']}:/tmp/")
	global $debug;
	// només si el render ha creat el .ps (si no, abans sortia l'etiqueta anterior)
	$do=" ssh {$empresa['printerserver']} \" test -s $tag_filename && lp $tag_filename -d {$empresa['sticker_printer']}\" ";
	if($debug)debugtelegram($do,"tag_print");
	exec($do);

}
function pmail($taula,$empresa){
	global $localuser,$link, $em, $pdf_public_host, $pdf_scp_target, $label_qr_host;
	$q="SELECT * FROM $taula WHERE (`pdf_fet`='0' ) AND (`datetime_creation` <= DATE_SUB( now(), INTERVAL 10 SECOND ))";
	if ($empresa['bool_print_pdf']==1){
		$qprint="SELECT * FROM $taula WHERE (`pdf_fet`='0' or `print_A4`='0') AND (`datetime_creation` <= DATE_SUB( now(), INTERVAL 3 SECOND))";
		if ($link->query($qprint)->num_rows>='1')$q=$qprint;
	}	
	//echo $q;br();echo$qprint;
	//debugtelegram($q,"{$_SERVER['HTTP_HOST']}",$empresa['telegram_relacions']);
	
	$i=$link->query($q)or die ($q.$link->error);
	while ($pdf=$i->fetch_assoc()){
		$a=explode("_",$taula);
		$r=detallrelacio($pdf['uuid'],$a['2'],$a['0'],$link);
		$f=fopen("../pdf/{$pdf['uuid']}.tex",'w')or die ('no he pugut crear l\'arxiu '.$r['uuid'].'.tex'.$_SERVER['HTTP_HOST']);
		fwrite($f,urldecode($pdf['LaTeX']));
		if($debug)debugtelegram($pdf);
		fclose($f);
		//localuser set in keys.php usualy empty but if ssh localhost from http fail (in rpi often happen) set to "user@" in keys.php or whatever
		exec(localcmd("
cd {$_SERVER['DOCUMENT_ROOT']}/pdf; 
pdflatex -interaction=nonstopmode {$pdf['uuid']}.tex; 
chown http * -f; 
rm {$_SERVER['DOCUMENT_ROOT']}/pdf/{$pdf['uuid']}.tex -f;
rm {$_SERVER['DOCUMENT_ROOT']}/pdf/*.aux -f;
rm {$_SERVER['DOCUMENT_ROOT']}/pdf/*.out -f;
rm {$_SERVER['DOCUMENT_ROOT']}/pdf/*.log -f;
"));
		//exec("cd {$_SERVER['DOCUMENT_ROOT']}/scale/pdf; pdflatex -interaction=nonstopmode  {$pdf['uuid']}.tex; chown http * -f",$rexec);
		//exec("cd {$_SERVER['DOCUMENT_ROOT']}/scale/pdf; cp {$pdf['uuid']}.pdf {$_SERVER['DOCUMENT_ROOT']}/pdf",$rexec);
		if($pdf_scp_target)exec ("scp {$_SERVER['DOCUMENT_ROOT']}/scale/pdf/{$pdf['uuid']}.pdf {$pdf_scp_target} ");
		if($pdf_public_host)$public="{$pdf_public_host}/pdf/{$pdf['uuid']}.pdf";
		else $public="https://{$_SERVER['HTTP_HOST']}/pdf/{$pdf['uuid']}.pdf";
		$telegramme=urlencode("Hola hem fet: ".mysql1r("SELECT `Value` FROM `tipus_relacio` WHERE `ID`='{$pdf['ID_tipus_relacio']}'",$link)." {$empresa['ID']}.{$pdf['ID']} \na nom de ".urldecode("{$pdf['fiscal_name']}+{$pdf['email']}")."\nté el teléfon: {$pdf['phone']}\n".$public);
		//$public=urlencode($public);
		$response = file_get_contents("https://api.telegram.org/bot$telegramtoken/sendMessage?chat_id={$empresa['telegram_relacions']}&text=$telegramme" );
		debugtelegram(urldecode($telegramme),"{$_SERVER['HTTP_HOST']}",$empresa['telegram_relacions']);
		//$data[ 'chat_id' => '-'.$empresa['telegram_relacions'] , 'document' => $public ];
		//$response = file_get_contents("https://api.telegram.org/bot$telegramtoken/sendDocument?chat_id=-{$empresa['telegram_relacions']}&document=$public");
		//update pdf_fet aqui
		$u="UPDATE `$taula` SET `pdf_fet` = '1' WHERE `ID` = '{$pdf['ID']}' AND `ID_tipus_relacio` = '{$pdf['ID_tipus_relacio']}' ";
		$link->query($u)or die(debugtelegram($_SERVER['HTTP_HOST'].$u.$link->error));
		if (correuvalid($pdf['email']) and ($pdf['email_enviat']<1)and ($pdf['email_enviat']>-4)){
			$mail = new PHPMailer(true);
			try {
				//Server settings
			 	//$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
				$mail->isSMTP();                                            // Send using SMTP
				$mail->Host       = $em['host'];                    // Set the SMTP server to send through
	    			$mail->SMTPAuth   = true;                                   // Enable SMTP authentication
	    			//$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
	    			$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
	  	 		$mail->Port       = 587;                                    // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
				//$mail->Port       = 465;                                    //https://github.com/PHPMailer/PHPMailer/blob/master/examples/gmail_xoauth.php
				//$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //2022-06-27
	    			$mail->Username   = $em['user'];                     		// SMTP username
	    			$mail->Password   = $em['passwd'];                               // SMTP password
				$mail->CharSet = 'UTF-8';
	   		 	//Recipients
	    			$mail->setFrom($empresa['email'], $empresa['Value']);
	    			//$mail->setFrom($em['user'], $empresa['Value']);
	    			$mail->addAddress($pdf['email'],urldecode($pdf['fiscal_name']));     // Add a recipient
	    			$mail->addBCC($empresa['email']);
	    		// Attachments
	    			$mail->addAttachment("{$_SERVER['DOCUMENT_ROOT']}/pdf/{$pdf['uuid']}.pdf");         // Add attachments
			// Content
	    			$mail->isHTML(true);                                  // Set email format to HTML
				$mail->Subject = 'Enviem '.
					mysql1r("SELECT `Value` FROM `tipus_relacio` WHERE `ID`='{$pdf['ID_tipus_relacio']}'",$link).": 
				{$empresa['ID']}.{$pdf['ID']}";
	    			$mail->Body  = html_relacio($r) ;
				$mail->AltBody = "
Moltes gràcies per confiar amb nosaltres
L’informem que les seves dades personals, que puguin constar en aquesta comunicació, estan incorporades en un fitxer propietat de {$empresa['Value']}, amb la finalitat d'enviar-li comunicacions de caire comercial o de prestació de serveis. Si desitja exercitar els drets d'accés, rectificació, cancel•lació o oposició pot dirigir-se per escrit a la següent direcció: {$empresa['Direccio']} {$empresa['Poblacio']}  En el cas que no desitgi rebre més comunicacions a través del correu electrònic, pot enviar un missatge a la següent adreça electrònica: {$empresa['email']}

Advertència: 
No respongeu aquest correu, no el llegim. Si convé escriviu a  {$empresa['email']}
La informació inclosa en aquest e-mail és CONFIDENCIAL, essent per a ús exclusiu del destinatari a dalt esmentat. Si vostè llegeix aquest missatge i no és el destinatari indicat, li informem que està totalment prohibida qualsevol utilització, divulgació, distribució i/o reproducció d’aquesta comunicació, total o parcial, sense autorització expressa en virtut de la legislació vigent. Si ha rebut aquest missatge per error, li preguem que ens ho notifiqui immediatament per aquesta via i procedeixi a la seva eliminació juntament amb els fitxers annexes sense llegir-lo, ni difondre, ni emmagatzemar o copiar el seu contingut.

Advertencia:
No responga a este correo, no lo leemos. Si hace falta escriba a {$empresa['email']}
La Información incluida en este e-mail es CONFIDENCIAL, siendo para uso exclusivo del destinatario arriba mencionado. Si Ud. lee este mensaje y no es el destinatario indicado, le informamos que está totalmente prohibida cualquier utilización, divulgación, distribución y/o reproducción de esta comunicación, total o parcial, sin autorización expresa en virtud de la legislación vigente. Si ha recibido este mensaje por error, le rogamos nos lo notifique inmediatamente por esta vía y proceda a su eliminación junto con sus ficheros anexos sin leerlo, ni difundir, ni almacenar o copiar su contenido.
";

 			$mail->send();
			//echo 'Message has been sent';
			//debugtelegram("correu enviat a {$pdf['email']}","{$_SERVER['HTTP_HOST']}");
			//update pdf enviat aqui/scale/p.php?ID=1&debug=yes
			$u="UPDATE `$taula` SET `email_enviat` = '1' WHERE `ID` = '{$pdf['ID']}' AND `ID_tipus_relacio` = '{$pdf['ID_tipus_relacio']}' ";
			$link->query($u)or die(debugtelegram($_SERVER['HTTP_HOST'].$u.$link->error));
		} 
		catch (Exception $e) {
			if ($pdf['email_enviat']=='')$pdf['email_enviat']=-1;
			else $pdf['email_enviat']--;
			$u="UPDATE `$taula` SET `datetime_creation` = now() , `email_enviat`= '{$pdf['email_enviat']}'  WHERE `ID` = '{$pdf['ID']}' AND `ID_tipus_relacio` = '{$pdf['ID_tipus_relacio']}' ";
			$link->query($u)or die(debugtelegram($_SERVER['HTTP_HOST'].$u.$link->error));
			if($debug)debugtelegram(date('His')."/>correu NO enviat a {$pdf['email']}\nError: {$mail->ErrorInfo}","{$_SERVER['HTTP_HOST']}");
			//debugtelegram($mail);
			//debugtelegram($u);
		}	
		}//fi if correu_valid
		else { 
			echo "no mail";
			if ($pdf['email']=="") debugtelegram("PDF sense correu","{$_SERVER['HTTP_HOST']}",$empresa['telegram_relacions']);
			else debugtelegram('correu no valid '.$pdf['email'],"{$_SERVER['HTTP_HOST']}");
			$u="UPDATE `$taula` SET `email_enviat` = '2' WHERE `ID` = '{$pdf['ID']}' AND `ID_tipus_relacio` = '{$pdf['ID_tipus_relacio']}' ";
			$link->query($u)or die(debugtelegram($_SERVER['HTTP_HOST'].$u.$link->error));
		}
		if(($empresa['bool_print_pdf']=='1')&&($pdf['print_A4']=='0')){
        	debugtelegram("imprimeixo una copia de ".mysql1r("SELECT `Value` FROM `tipus_relacio` WHERE `ID`='{$pdf['ID_tipus_relacio']}'",$link)." {$empresa['ID']}.{$pdf['ID']}",$_SERVER['HTTP_HOST'],$empresa['telegram_relacions']);
			exec ("scp {$_SERVER['DOCUMENT_ROOT']}/scale/pdf/{$pdf['uuid']}.pdf {$empresa['printerserver']}:/tmp ");
			exec ("ssh {$empresa['printerserver']} \"lp /tmp/{$pdf['uuid']}.pdf -d {$empresa['a4_printer']}\"");
		}
		if(($empresa['bool_print_pdf']=='1')&&($pdf['ID_tipus_relacio']=='2')&&($pdf['print_A4']=='0')){
        	debugtelegram("imprimeixo una 2a copia de Albarà",$_SERVER['HTTP_HOST'],$empresa['telegram_relacions']);
			exec ("scp {$_SERVER['DOCUMENT_ROOT']}/scale/pdf/{$pdf['uuid']}.pdf {$empresa['printerserver']}:/tmp ");
			exec ("ssh {$empresa['printerserver']} \"lp /tmp/{$pdf['uuid']}.pdf -d {$empresa['a4_printer']}\"");
			//exec ("ssh {$empresa['printerserver']} \"lp /tmp/{$pdf['uuid']}.pdf -d {$empresa['a4_printer']}\"");
		}
		if($empresa['bool_print_pdf']=='1'){
			$u="UPDATE `$taula` SET `print_A4` = '1' WHERE `ID` = '{$pdf['ID']}' AND `ID_tipus_relacio` = '{$pdf['ID_tipus_relacio']}'";
		  	$link->query($u)or die(debugtelegram($_SERVER['HTTP_HOST'].$u.$link->error));
		}
		$rm=localcmd(" rm {$_SERVER['DOCUMENT_ROOT']}/scale/pdf/* -f ");
		//debugtelegram($localuser."es localuser");
		//exec ($rm);
		}//fi del while dels pdf
}



//main 
//
//
//
//
//part etiquetes de preu: 
$qprint=$link->query("SELECT * FROM imprimeix");
if($debug)debugtelegram("p.php entra a imprimeix?");
while($print=$qprint->fetch_assoc()){
	if($debug)debugtelegram($print);
	$borra="DELETE FROM `imprimeix` WHERE `ID` = '{$print['ID']}';";
   	if($link->query($borra)===FALSE){ debugtelegram($q.mysqli_error($link),gethostname());die;}
	if($print['preu_or_etiqueta']==0){
		$decimalsing=',';
		$thousandsing=' ';
		$ID=$print['ID_product'];
		$d=$link->query("select * from scalelist where ID='$ID'")->fetch_assoc();
		$pvp=number_format($d['pvp'],2,$decimalsing,$thousandsing);
		$nom=strtoupper(urldecode($d['value']));
		$d['bulk']==1?$u='Kg':$u='uni';
		$origen=urldecod(mysql1r("SELECT origen from productnamesurlencoded where ID={$print['ID_product']} and lang='ca'",$link));
		if($origen=="") $origen='Preguntar a botiga';
		$nf=urlencode(str_pad($ID,4, "0", STR_PAD_LEFT).'_'.urlencode(str_replace(' ','_',$nom)).$midaetiqueta.'.svg');
		//debugtelegram($midaetiqueta,gethostname());
		if(!$print['package'])etiquetapreu($ID,$pvp,$nom,$u,$origen,$nf);
		else {
			$sql="select * from package_format where ID={$ID} and ID_package_type='{$print['package']}'";
			if($debug)debugtelegram($sql,"sql");
			$package=$link->query($sql)->fetch_assoc();
			$nf=urlencode(str_pad($ID,4, "0", STR_PAD_LEFT).".{$print['package']}_".urlencode(str_replace(' ','_',$nom)).'.svg');
			if($debug)debugtelegram($nf,"nom_arxiu");
			if(!isset($package)){
	 		//	debugtelegram("$ID.{$print['package']} no existeix");
				debugtelegram("$ID.{$print['package']} no existeix",gethostname(),$empresa['telegram_relacions']);
				die;
			}
			//else debugtelegram ($package,"package_format");
			try{ 
				$package_description=$link->query("select * from package_description where ID={$ID} and ID_package_type='{$print['package']}' and `lang`='ca'")->fetch_assoc();
			}catch (Exception $e){debugtelegram("no he pugut aconseguir package_description {$ID}.{$print['package']}","{$_SERVER['HTTP_HOST']}",$empresa['telegram_relacions']);die;}
			$product=$link->query("SELECT * from tarifes where `ID`=$ID AND `ultimpreu`=1")->fetch_assoc();
			$product['pvp']=package_pvp($product,$package,$link);//retorna falç si no troba un preu sobreescrit
			if(!$product['pvp']){
				debugtelegram("{$ID}.{$print['package']} $nom no té un preu sobreescrit per imprimir-lo");
				die;
			}
			else $pvp=number_format($product['pvp'],2,$decimalsing,$thousandsing);
			if(isset($package_description['description']))$nom=urldecode($package_description['description']);
			else if(isset($print['package']))$nom.=" ".mysql1r("select `weight` from `package_format` where `ID`='{$ID}'and `ID_package_type`='{$print['package']}'AND `hidden_date_time` = (  SELECT MAX(`hidden_date_time`) from package_format where `ID` ='{$ID}' AND `ID_package_type`='{$print['package']}')",$link)."g";
			//debugtelegram("select `weight` from package_format where `ID`='{$ID}'and `ID_package_type`='{$print['package']}'AND `hidden_date_time` = (  SELECT MAX(`hidden_date_time`) from package_format where `ID` ='{$ID}' AND `ID_package_type`='{$print['package']}')");
			
			etiquetapreu($ID,$pvp,$nom,"uni",$origen,$nf,$print['package']);
		}

		//$f=fopen($nf,'w')or die ;
		//fwrite($f, $arxiu);
		//fclose($f);
		#$scp=localcmd("scp {$_SERVER['DOCUMENT_ROOT']}/scale/$nf {$empresa['printerserver']}:/tmp ");
		//$cp['sh']=localcmd("scp {$_SERVER['DOCUMENT_ROOT']}/scale/$nf {$localuser}localhost:/tmp ");
		$cp['sh']="whoami ";
		exec ($cp['sh'],$cp['sortida'],$cp['variable retorn']) ;
		if($debug)debugtelegram($cp);
		unset($cp);
		$cp['sh']="cp {$_SERVER['DOCUMENT_ROOT']}/scale/$nf /tmp/ -v";
		$cp['sh']=localcmd("scp {$_SERVER['DOCUMENT_ROOT']}/scale/$nf {$localuser}localhost:/tmp ");
		$cp['sh']=localcmd("cp {$_SERVER['DOCUMENT_ROOT']}/scale/$nf /tmp -v");
		exec ($cp['sh'],$cp['sortida'],$cp['variable retorn']) ;
		if($debug){
			if(file_exists("{$_SERVER['DOCUMENT_ROOT']}/scale/$nf "))debugtelegram("$nf existeix i és vàlid");
			else debugtelegram("{$_SERVER['DOCUMENT_ROOT']}/scale/$nf no existeix");
		}
		if($debug)debugtelegram($cp);
		// un .ps per etiqueta: si el render falla no s'imprimeix una etiqueta anterior
		$ps="/tmp/{$ID}_preu.ps";
		exec (localcmd(" rm -f $ps "));
		exec ("ssh {$empresa['printerserver']} \" rm -f $ps \"");
		//$render="ssh {$empresa['printerserver']} \" inkscape /tmp/$nf -o /tmp/tmp.ps \"";
		$render=localcmd(" inkscape /tmp/$nf -o $ps ");
		$scp=localcmd("scp $ps {$empresa['printerserver']}:/tmp ");
		if($debug)debugtelegram($render,gethostname());
			if($debug)debugtelegram($scp,gethostname());
		exec($render);
		exec($scp);
	for ($i=1;$i<=$print['units'];$i++)tag_print_command($ps,$empresa);
		//borro svg creat
		if(!$debug){
			exec (localcmd(" rm -f {$_SERVER['DOCUMENT_ROOT']}/scale/$nf $ps "));
			//exec ("ssh {$empresa['printerserver']} \" rm -f /tmp/$nf \"");
			exec ("ssh {$empresa['printerserver']} \" rm -f $ps \"");

		}
	}
       	else {//és una etiqueta de producte
//      	       echo imprimeixo;
	if($print['ID_product']==mysql1r("SELECT ID FROM `productnamesurlencoded` where `ID`={$print['ID_product']}",$link)){
			//echo "etiqueta de producte";
			if($debug)pre($print);
			$nf=etiquetaproducte($print,$link);
			if($print['package']!="")$p=".".$print['package'];
			//echo Endroid\QrCode\QrCode::VERSION;
			if($print['lot']!="")$print['lot']="+".$print['lot'];
			if($print['bbf']!="")$print['bbf']="+".$print['bbf'];
				$url="{$label_qr_host}/{$print['ID_product']}$p{$print['lot']}{$print['bbf']}";
			$writer= new SvgWriter();
			//use Endroid\QrCode\QrCode;
			$qrCode = new QrCode($url);
			$qrCode->setMargin(4); 
			$result=$writer->write($qrCode);
			$result->saveToFile(__DIR__.'/q'.$nf);
			if($debug)debugtelegram($nf,gethostname()." nf");
			if($debug)debugtelegram($print,gethostname()." print");
			$scp="scp {$_SERVER['DOCUMENT_ROOT']}/scale/$nf {$empresa['printerserver']}:/tmp";
			exec ($scp) ;
			$scp="scp {$_SERVER['DOCUMENT_ROOT']}/scale/q$nf {$empresa['printerserver']}:/tmp";
			exec ($scp) ;
			$ps="/tmp/{$print['ID_product']}_producte.ps";
			exec(" ssh {$empresa['printerserver']} \"rm -f $ps; inkscape /tmp/$nf -o $ps \"");
			for ($i=1;$i<=$print['units'];$i++)tag_print_command($ps,$empresa);
			if($_REQUEST['ID']!='100')exec (localcmd(" rm -f {$_SERVER['DOCUMENT_ROOT']}/scale/q$nf "));
			if($_REQUEST['ID']!='100')exec (localcmd(" rm -f {$_SERVER['DOCUMENT_ROOT']}/scale/$nf "));
			if(!$debug){
				exec ("ssh {$empresa['printerserver']} \" rm -f /tmp/q$nf \"");
				exec ("ssh {$empresa['printerserver']} \" rm -f /tmp/$nf \"");
				exec ("ssh {$empresa['printerserver']} \" rm -f $ps \"");
			}
		}
	    else{
			//debugtelegram("imprimeixo {$_SERVER['DOCUMENT_ROOT']}/scale/img/{$print['ID_product']}.svg",gethostname());
			$scp="scp {$_SERVER['DOCUMENT_ROOT']}/scale/img/{$print['ID_product']}{$midaetiqueta}.svg {$empresa['printerserver']}:/tmp";
			exec ($scp) ;
			//debugtelegram($scp);
			$ps="/tmp/{$print['ID_product']}_especial.ps";
			exec(" ssh {$empresa['printerserver']} \"rm -f $ps; inkscape /tmp/{$print['ID_product']}{$midaetiqueta}.svg -o $ps \"");
			for ($i=1;$i<=$print['units'];$i++)tag_print_command($ps,$empresa);
			if($_REQUEST['ID']!='100')exec ("ssh {$empresa['printerserver']} \" rm -f $ps \"");
			if($_REQUEST['ID']!='100')exec ("ssh {$empresa['printerserver']} \" rm -f /tmp/{$print['ID_product']}.svg \"");
		} //exec ("ssh  {$empresa['printerserver']} \"lp /home/etiquetes/{$print['ID_product']}.ps -d  {$empresa['sticker_printer']}\"");
	}

}
//part dels pdf i mails 
//$taula=date('Y')."_Empresa_{$empresa['ID']}_pdf";//ara $taula es dinàmic envia tot lo que hi hagi al servidor, segurament acabarem centralitzant aquesta feina a ametlles
$q="show tables like '%_pdf'";
$l=$link->query($q);
while($f=$l->fetch_row()){
	//pre($f);
	if($f[0]!="meta_pdf"){
		$a=explode("_",$f[0]);
		//pre($a);
		$taula=$f[0];
		$empresa=$link->query("SELECT * FROM Empresa where `ID`='{$a[2]}'")->fetch_assoc();
		if($debug)debugtelegram($empresa);
		if($debug)debugtelegram($taula,"entro aqui:");
		pmail($taula,$empresa);
	}
}
$link->close();
unlink($lockfile);
?>
