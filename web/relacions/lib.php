<?php
function debugtelegram($debug,$valor='debug',$telegramchannel=FALSE,$return=FALSE){
	global $telegramtoken,$telegramdebugchannel;
	if($telegramchannel==FALSE)$telegramchannel=$telegramdebugchannel;
	if (is_array($debug)){
		foreach ($debug AS $k=> $v){
			$telegramme.=debugtelegram($v,"$valor".'['.$k.']',$telegramchannel,true)."\n";
		}
		if(empty($debug))$telegramme="$valor: empty array";
	}
	else $telegramme="$valor: ".urldecode($debug!=''?$debug:'empty value');
	if($return)return $telegramme;
	else {
		$msg = $telegramme!='' ? $telegramme : 'Debug return empty';
		global $telegrambotname;
		if($telegrambotname!='' && $telegramchannel==$telegramdebugchannel && strpos($msg,"@$telegrambotname")===false) $msg="@$telegrambotname ".$msg;
		if($telegramtoken!='' && $telegramchannel!='')
			$response = @file_get_contents("https://api.telegram.org/bot$telegramtoken/sendMessage?chat_id=-$telegramchannel&text=".urlencode($msg) );
	}

	//$resposta=file_get_contents("https://api.telegram.org/bot$telegramtoken/getUpdates?offset=0&limit=1");
	//$update = json_decode($response, true);
	//if($telegramchannel!=$telegramdebugchannel){pre($response); echo "update"; tpre();}
	
}
function myErrorHandler($errno, $errstr, $errfile, $errline){
   /* if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting, so let it fall
        // through to the standard PHP error handler
        return false;
    }
	*/
	  // $errstr may need to be escaped:
    $errstr = htmlspecialchars($errstr);
    switch ($errno) {
    case E_USER_ERROR:
        $d=debugtelegram( "<b>My ERROR</b> [$errno] $errstr<br />\n",true);
        $d.=debugtelegram( "  Fatal error on line $errline in file $errfile",true);
      	$d.=debugtelegram(", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n",true);
        $d.=debugtelegram( "Aborting...<br />\n",true);
	debugtelegram($d);
        exit(1);

    case E_USER_WARNING:
        debugtelegram("<b>My WARNING</b> [$errno] $errstr<br />\n");
        break;

    case E_USER_NOTICE:
        debugtelegram("<b>My NOTICE</b> [$errno] $errstr<br />\n");
        break;
        case E_WARNING:
		 $_SESSION['E_WARNING'].="E_WARNING $errstr on on line: $errline@$errfile\n";
		break;
	case E_NOTICE:
		 $_SESSION['E_NOTICE'].="E_NOTICE $errstr on on line: $errline@$errfile\n";
		break;
    default:
        debugtelegram("Unknown error type: [$errno] $errstr on line: $errline@$errfile <br />\n");
        break;
    }

    /* Don't execute PHP internal error handler */
    return true;

}
function prefered_language(array $available_languages, $http_accept_language) {
    $available_languages = array_flip($available_languages);
    $langs = [];
    preg_match_all('~([\w-]+)(?:[^,\d]+([\d.]+))?~', strtolower($http_accept_language), $matches, PREG_SET_ORDER);
    foreach($matches as $match) {
        list($a, $b) = explode('-', $match[1]) + array('', '');
        $value = isset($match[2]) ? (float) $match[2] : 1.0;
        if(isset($available_languages[$match[1]])) {
            $langs[$match[1]] = $value;
            continue;
        }
        if(isset($available_languages[$a])) {
            $langs[$a] = $value - 0.1;
        }
    }
    arsort($langs);
    return $langs;
}
function number_format_trim($value,$maxdecimal=0,$decimalsing='.',$thousandsing=','){
		if(is_numeric($value))return rtrim(rtrim(number_format($value,$maxdecimal,$decimalsing,$thousandsing),0),$decimalsing);
}
function normalize($cadena){
    //treu dead keys
      $originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞ
ßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
    $modificadas = 'aaaaaaaceeeeiiiidnoooooouuuuy
bsaaaaaaaceeeeiiiidnoooooouuuyybyRr';
    $cadena = utf8_decode($cadena);
    $cadena = strtr($cadena, utf8_decode($originales), $modificadas);
    $cadena = strtolower($cadena);
    return utf8_encode($cadena);
}
function treuapostrof($cadena){
    return str_replace("'", ' ',$cadena);
}
function neteja_email($email) {
    // Comprovar si l'email està codificat amb URLencode
    if (strpos($email, '%') !== false || strpos($email, '+') !== false) {
        $email = urldecode($email);
    }
    
    // Validar que l'email resultant és correcte
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    
    return false; // Retorna false si l'email no és vàlid
}


function tlinea($index_relacio,$qllista,$link){
if($llista=$link->query($qllista)){
	while ($f = $llista->fetch_assoc()){
		$preu = isset($f['preu']) ? $f['preu'] : (isset($f['cost']) ? $f['cost'] : 0);
		$tlinea=round($f['units']*$preu,2);
		if($f['bulk'])$f['u']='Kg';
		else $f['u']='uni';
		$f['urlde']=urldecode($f['description']);
		if($index_relacio['tax_detall']==0){
			//iva inclos: si la relacio es amb iva inclós el descompte va sobre el preu amb iva
			$f['cost']=$f['cost']*(1+($f['IVA']/100));
			$f['tline']=$f['units']*($f['cost']*(1-($f['discount']/100)));
			$f['Base']=round($f['tline']*(1-($f['IVA']/100)),2);
			$f['cost']=round($f['cost'],2);
			$f['tline']=round($f['tline'],2);

		}
		else{//si no, el descompte va sobre el preu de base
			$f['Base']=round($f['units']*$f['cost']*(1-($f['discount']/100)),2);
			$f['tline']=round($f['Base']*(1+($f['IVA']/100)),2);
		}
		//if($index_relacio['equivalency_charge']==1)
			//$f['tec']=round($f['tline']*($f['EQUIVALENCY_CHARGE']/100),2);
			//$f['tec']=round($f['tline']);
		$f['tiva']=$f['tline']-$f['Base'];
		if($f['deleted_line']==0){//si es línea activa sumem la línea als sumatoris totals
			$tec = isset($f['tec']) ? $f['tec'] : 0;
			if($index_relacio['equivalency_charge']==1)
            			$index_relacio['EC'][$f['EQUIVALENCY_CHARGE']]=$index_relacio['EC'][$f['EQUIVALENCY_CHARGE']]+$f['Base'];
			$index_relacio['Base']=$index_relacio['Base']+$f['Base'];
			$index_relacio['Total']=$index_relacio['Total']+$f['Base']+$f['tiva']+$tec;
			$index_relacio['IVA'][$f['IVA']]=$index_relacio['IVA'][$f['IVA']]+$f['tiva'];
			$index_relacio['B'][$f['IVA']]=$index_relacio['B'][$f['IVA']]+$f['Base'];
			$index_relacio['T'][$f['IVA']]=$index_relacio['T'][$f['IVA']]+$f['tline']+$tec;
			if(isset($f['cost_price'])){
				$index_relacio['C'][$f['IVA']]=$index_relacio['C'][$f['IVA']]+(round($f['units']*$f['cost_price'],2));
				$index_relacio['Cost']=$index_relacio['Cost']+(round(round($f['units']*$f['cost_price'],2)*(1+($f['IVA']/100)),2));
			}
			$index_relacio['active_lines'][$f['line']]=$f;
			if($f['discount']!=0)$index_relacio['discountinline']=true;
		}
		else $index_relacio['deleted_lines'][$f['line']]=$f;
	}
}
return $index_relacio;
}
function detallrelacio($uuid,$ID_Empresa,$year,$link,$debug=false){
//retorna tota una relació dintre una matriu.
$FROMindex_relacio="FROM {$year}_Empresa_{$ID_Empresa}_index_relacio WHERE uuid='$uuid'";
if($debug)echo"SELECT * $FROMindex_relacio";
$index_relacio=$link->query("SELECT * $FROMindex_relacio")->fetch_assoc();
$FROMactive_lines="FROM {$year}_Empresa_{$ID_Empresa}_line WHERE ID_tipus_relacio='{$index_relacio['ID_tipus_relacio']}' and ID='{$index_relacio['ID']}' and line>0 and deleted_line=0 ";
$FROMdeleted_lines="FROM {$year}_Empresa_{$ID_Empresa}_line where ID_tipus_relacio='{$index_relacio['ID_tipus_relacio']}' and ID='{$index_relacio['ID']}' and line>0 and deleted_line=1";
$index_relacio['Nom_Venedor']=mysql1r("SELECT Value from {$ID_Empresa}_Venedor where ID='{$index_relacio['ID_Venedor']}'",$link);
$index_relacio['activelines']=mysql1r("SELECT COUNT(*) $FROMactive_lines",$link);
$index_relacio['deletedlines']=mysql1r("SELECT COUNT(*) $FROMdeleted_lines",$link);
$FROM="FROM {$year}_Empresa_{$ID_Empresa}_line WHERE ID_tipus_relacio='{$index_relacio['ID_tipus_relacio']}' and ID='{$index_relacio['ID']}' and line>0";
$qllista="SELECT * $FROM ORDER BY line";
//poso els contadors a zero perque si fas un print_r estiguin a la part de dalt, no per a res més
$index_relacio['Base']=0;
$index_relacio['Total']=0;
$index_relacio['Cost']='0';
$index_relacio['discountinline']=false;
// $qiva=$link->query("SELECT * from IVA ORDER BY Value");
$index_relacio['Empresa']=$link->query("SELECT * from Empresa where ID='$ID_Empresa'")->fetch_assoc();
$qiva=$link->query("SELECT IVA $FROMactive_lines group by IVA ORDER BY IVA");
while($iva=$qiva->fetch_assoc()){
	$index_relacio['IVA'][$iva['IVA']]=0;
	$index_relacio['B'][$iva['IVA']]=0;
	$index_relacio['T'][$iva['IVA']]=0;
	$index_relacio['C'][$iva['IVA']]=0;
// 	$qec=$link->query("SELECT * from equivalency_charge where ID_iva={$iva['ID']}");
	$qec=$link->query("SELECT EQUIVALENCY_CHARGE $FROMactive_lines AND IVA='{$iva['IVA']}' group by EQUIVALENCY_CHARGE ");
	while($eq=$qec->fetch_assoc()){
		$index_relacio['EC'][$eq['EQUIVALENCY_CHARGE']]=0;
	}
}
if(($index_relacio['Empresa']['sell_wholesale']=='1')&&($index_relacio['equivalency_charge']==1))$index_relacio['tax_detall']=1;//per assegurar-se

$index_relacio=tlinea($index_relacio,$qllista,$link);	//$index_relacio=tlinea($index_relacio,false,$qllista_eliminades,$link);

//if($debug)pre($index_relacio);
//aplicar el descompte total si convé i calcular el EC també serveix amb discount==0 però son milisegons aqui a calcular
	if($index_relacio['tax_detall']==0)		{
// 			echo "hi ha discount sense detall";
		$index_relacio['Total']=0;
		$index_relacio['Base']=0;
		//fer el descompte per cada [t][iva]
		if(isset($index_relacio['T']) && is_array($index_relacio['T'])) {
			foreach ($index_relacio['T'] as $k=>$v){
				$nou=round($v*(1-($index_relacio['discount']/100)),2);
				$index_relacio['T'][$k]=$nou;
				$index_relacio['Total']=$nou+$index_relacio['Total'];
				$index_relacio['IVA'][$k]=$nou-round($nou/(1+($k/100)),2);
				$index_relacio['B'][$k]=$nou-$index_relacio['IVA'][$k];
				$index_relacio['Base']=$index_relacio['Base']+$index_relacio['B'][$k];
			}
		}
	}
	else{
		$index_relacio['Total']=0;
		$index_relacio['Base']=0;
		//fer el descompte per cada b[iva]
		if(isset($index_relacio['B']) && is_array($index_relacio['B'])) {
			foreach ($index_relacio['B'] as $k=>$v){
				$nou=round($v*(1-($index_relacio['discount']/100)),2);
				$index_relacio['B'][$k]=$nou;
				$index_relacio['IVA'][$k]=round($nou*($k/100),2);
				$index_relacio['Base']=$index_relacio['Base']+$nou;
				if($index_relacio['Empresa']['sell_wholesale']=='1'){
	            			$tax_ec=mysql1r("SELECT equivalency_charge $FROMactive_lines and IVA='$k'",$link);
					$index_relacio['EC'][$tax_ec]=round($nou*($tax_ec/100),2);
				}
				else unset($index_relacio['EC']);//si no venc al major no puc cobrar req.
				$index_relacio['T'][$k]=$nou+$index_relacio['IVA'][$k]+$index_relacio['EC'][$tax_ec];
				$index_relacio['Total']=$index_relacio['T'][$k]+$index_relacio['Total'];
			}
		}
	}
if(isset($index_relacio['active_lines']) && is_array($index_relacio['active_lines']))sort($index_relacio['active_lines']);//força el vector de active_lines a començar per 0
if(isset($index_relacio['deleted_lines']) && is_array($index_relacio['deleted_lines']))sort($index_relacio['deleted_lines']);//força el vector de active_lines a començar per 0
$index_relacio['tipus_relacio']=$link->query("SELECT * from tipus_relacio where
	ID='{$index_relacio['ID_tipus_relacio']}'")->fetch_assoc();
return $index_relacio;
}
function asigna_relacio_per_telefon($r,$tel,$ID_Empresa,$year,$link,$debug=false){
	$a=false;
	if($debug)pre($r);
	if (substr($tel, 0, 2) == "+3" && substr($tel, 2, 1) == "4") {
		$spaintel=substr($tel, 3);
		debugtelegram($spaintel);
		$ID_Client=mysql1r("SELECT ID_Client FROM Clients_phone WHERE Value='$tel' OR Value='$spaintel'  ",$link,$debug);
	}
	else $ID_Client=mysql1r("SELECT ID_Client FROM Clients_phone WHERE Value='$tel'  ",$link,$debug);
	if($ID_Client!=''){
        if($debug)echo 'tinc client';
		$nouclient=$link->query("select * from Clients where ID='$ID_Client' AND bool_Active=1")->fetch_assoc();//or mysqli.error() ;
 		if($ID_Client!=$nouclient['ID'])$a=false;
		pre($nouclient);
		$FROMindex_relacio="FROM {$year}_Empresa_{$ID_Empresa}_index_relacio WHERE uuid='{$r['uuid']}'";
		$FROM="FROM {$year}_Empresa_{$ID_Empresa}_line WHERE ID_tipus_relacio='{$r['ID_tipus_relacio']}' and ID='{$r['ID']}' and line>0 ";
		if($debug)echo"SELECT * $FROMindex_relacio";
		$index_relacio=$link->query("SELECT * $FROMindex_relacio")->fetch_assoc();
		$update_index="UPDATE `{$year}_Empresa_{$ID_Empresa}_index_relacio` SET
		`ID_client` = '$ID_Client',
		`tax_detall` = '{$nouclient['bool_tax_detall']}',
		`equivalency_charge` = '{$nouclient['bool_equivalency_charge']}',
		`fiscal_name` = '{$nouclient['Value']}',
		`NIF` = '{$nouclient['NIF']}',
		`fiscal_address` = '{$nouclient['fiscal_address']}',
		`comercial_name` = '{$nouclient['comercial_name']}',
		`deliver_address` = '{$nouclient['deliver_address']}',
		`phone` = '$tel',
		`email` = '{$nouclient['email']}',
        	`Poblacio`='{$nouclient['Poblacio']}'
		WHERE `ID` = '{$r['ID']}' AND `ID_tipus_relacio` = '{$r['ID_tipus_relacio']}';";
		$a=true;
	}
	else { if($debug)echo' telefon anonim';
	if($tel!=$r['phone']){
		$update_index="UPDATE `{$year}_Empresa_{$ID_Empresa}_index_relacio` SET
		`phone` = '$tel'
		WHERE `ID` = '{$r['ID']}' AND `ID_tipus_relacio` = '{$r['ID_tipus_relacio']}';";
		$a=true;
		}
    }
	//if($debug)
		pre($update_index);
	if($a){
		$a=$link->query($update_index);
		if(!$a)echo"ha fallat $update_index";
	}
	if(($a)&&(($ID_Client!=''))) {
		$tarifa=mysql1r("SELECT column_name from tarifa where ID='{$nouclient['id_tarifa']}'",$link,$debug);
		$qllista="SELECT * $FROM ORDER BY line";
		if($llista=$link->query($qllista)){
			while ($f = $llista->fetch_assoc()){
				if($f['units']>=1){
					if($nouclient['id_tarifa']!=10){// sino es detall les altres tarifes venen sense iva
						$noupreu=mysql1r("SELECT $tarifa FROM tarifes where ID='{$f['ID_product']}' AND ultimpreu='1'",$link,$debug);

						$ulinea="update `{$year}_Empresa_{$ID_Empresa}_line` SET cost='$noupreu',`discount`='0'
						WHERE `ID` = '{$r['ID']}' AND `ID_tipus_relacio` = '{$r['ID_tipus_relacio']}' AND `line`='{$f['line']}';";
						if($debug)pre($ulinea);
						if($noupreu!="")$a=$link->query($ulinea);
						if(!$a)break;
					}
				}
				if($nouclient['bool_equivalency_charge']==1){
					//aplico recàrreg a cada línea
					$id_iva=mysql1r("SELECT ID from IVA where `Value`='{$f['IVA']}'",$link);
					$rec=mysql1r("Select TAX FROM `equivalency_charge`  WHERE `ID_iva` = '$id_iva'",$link);
					$ulinea="update `{$year}_Empresa_{$ID_Empresa}_line` SET EQUIVALENCY_CHARGE='$rec'
					WHERE `ID` = '{$r['ID']}' AND `ID_tipus_relacio` = '{$r['ID_tipus_relacio']}' AND `line`='{$f['line']}';";
					if($debug)pre($ulinea);
					$a=$link->query($ulinea);
					if(!$a)break;
				}
			}
		}
	}
	return $a;
}
function pdf($r,$prefixrelacio,$noenviar=false){
	global $decimalsing,$thousandsing,$link;#,$prefixrelacio;
//  pre($r);
	$idrelacio="{$r['tipus_relacio']['Value']}&{$r['Empresa']['ID']}.{$r['ID']}";
	$data=latindate($r['data']);
	$nom_client=str_replace('$','\$',str_replace('#','\#',str_replace('&','\&',str_replace('%','\%',urldecode($r['fiscal_name'])))));
	$direccio=str_replace('$','\$',str_replace('#','\#',str_replace('&','\&',str_replace('%','\%',urldecode($r['fiscal_address'])))));
	if($r['deliver_address']='')$direccio.="\\\\ Deliver Address: \\\\".str_replace('$','\$',str_replace('#','\#',str_replace('&','\&',str_replace('%','\%',urldecode($r['deliver_address'])))));
	$poblacio=str_replace('$','\$',str_replace('#','\#',str_replace('&','\&',str_replace('%','\%',urldecode($r['Poblacio']?? " ")))));
	$pagat=mysql1r("Select Value from Pagament where `ID`='{$r['ID_Pagament']}'",$link);
	$espaidescripcio=110;
	if($r['tax_detall']==0){
		$espaidescripcio=$espaidescripcio-14;
		$ivainclos='IVA inclós';
	}
	else {
		$espaidescripcio="90";
		$ivainclos='sense IVA&IVA';
		$coliva='r|';
	}
	 if($r['discountinline']== 1)$espaidescripcio=$espaidescripcio-5;
	$linees=0;
	$discount=$r['discount']=='0'?'':'Descompte';
	 if(($r['Empresa']['sell_wholesale']=='1')&&($r['equivalency_charge'] == 1)){
		$prec='\%r. equivalència';
		$rec='recàrrec eq.';
	 }
	$peu='\hline
	Base&\%IVA&IVA&'.$prec.'&'.$rec.'&'.$discount.'&T.'.$r['tipus_relacio']['Value'].'\\\\
	\hline';
	// 	pre($r['IVA']);
	if($r['Empresa']['sell_wholesale']==0)$r['equivalency_charge']= 0;
	foreach ($r['IVA'] AS $iva => $tiva){
			 $base='\raggedleft{'.number_format($r['B'][$iva],2,$decimalsing,$thousandsing)."€}";
			 $fiva='\raggedleft{'.number_format_trim($iva,2,$decimalsing,$thousandsing).'\%}';
		     	 $ftiva='\raggedleft{'.number_format($tiva,2,$decimalsing,$thousandsing)."€}";
			 if(($r['equivalency_charge'] == 0)){
				 $lb[$iva]="$base&$fiva&{$ftiva}&&&&".'\\\\';
			 }
			 else{
				 	$lrec=0;
				 foreach($r['EC'] AS $rec=>$trec){
					 if($linees==$lrec){
						 	$frec='\raggedleft{'.number_format_trim($rec,2,$decimalsing,$thousandsing).'\%}';
							$ftrec='\raggedleft{'.number_format($trec,2,$decimalsing,$thousandsing)."€}";
						 	$lb[$iva]="$base&$fiva&{$ftiva}&$frec&$ftrec&&".'\\\\';
					 }
					 $lrec++;
				}
			 }
// 			 echo"soc fent el peu aqui $iva, $ftiva ".$lb[$iva].brr();
			 $ultimiva=$iva;
			 $linees++;
		 }
// 		echo 'pagat;'.$pagat;
		$base='\raggedleft{'.number_format($r['B'][$ultimiva],2,$decimalsing,$thousandsing)."€}";
		$iva='\raggedleft{'.number_format_trim($ultimiva,2,$decimalsing,$thousandsing).'\%}';
		$tiva='\raggedleft{'.number_format($r['IVA'][$ultimiva],2,$decimalsing,$thousandsing)."€}";
		$total='\raggedleft{'.number_format($r['Total'],2,$decimalsing,$thousandsing)."€}";
		$discount=$r['discount']=='0'?'':'\raggedleft{'.number_format_trim($r['discount'],2,$decimalsing,$thousandsing).'\%}';
		//\multirow[〈vpos〉]{〈nrows〉}[〈bigstruts〉]{〈width〉}[〈vmove〉]{〈text〉
		//\multirow[t]{5}{*}[-\shiftdown]{\Huge\bfseries B}
// 		 if($r['equivalency_charge'] == 0)
		if($total>999)$lb[$ultimiva]=$base.'&'.$iva.'&'.$tiva.'&'.$frec.'&'.$ftrec.'&'.$discount.'&\multirow[r]{'.$linees.'}{*}{}{'.$total.'}\\\\';
	 	else $lb[$ultimiva]=$base.'&'.$iva.'&'.$tiva.'&'.$frec.'&'.$ftrec.'&'.$discount.'&\multirow[r]{'.$linees.'}{*}{}{\Large '.$total.'}\\\\';
// 		else{//amb recàrreg
// 			$lb[$ultimiva]=$base.'&'.$iva.'&'.$tiva.'&$frec&&'.$discount.'&\multirow[r]{'.$linees.'}{*}{}{\Large '.$total.'}\\\\';
// 		}
//  		pre($lb);
		foreach ($lb AS $linea){
			$peu.=$linea.'
			';
		}
		$peu.='
		\hline';
	$tex='\documentclass[a4paper,10pt]{article}
\usepackage[utf8]{inputenc}
\usepackage{eurosym}
\usepackage{hyperref}
\hypersetup{
    colorlinks=true,
    linkcolor=blue,
    filecolor=magenta,
    urlcolor=cyan,
    pdftitle={'."{$r['tipus_relacio']['Value']}: {$r['Empresa']['ID']}.{$r['ID']} {$r['Empresa']['Value']}".'},
    pdfauthor={'.$r['Empresa']['Value'].'}
}
\DeclareUnicodeCharacter{20AC}{\euro}
\usepackage[spanish]{babel}
\addtolength{\voffset}{-1in}%desplaçament vertical
\setlength{\headheight}{50mm}
\addtolength{\hoffset}{-1.2in}%per defecte esta desplaçat 25.4mm (1 in)
\setlength{\textwidth}{18cm}
%\setlength{\textheight{16cm}}
%marges
%\setlength{\topmargin{15mm}}


%opening
\title{'.$idrelacio.' }
\author{'.$r['Empresa']['Value'].'}
%\usepackage{fontspec}
\usepackage{fancyhdr}
\usepackage{longtable}
\usepackage{graphics}
\usepackage{multirow}
\usepackage{array}
\newcolumntype{L}[1]{>{\raggedright\let\newline\\\arraybackslash\hspace{0pt}}m{#1}}
\newcolumntype{C}[1]{>{\centering\let\newline\\\arraybackslash\hspace{0pt}}m{#1}}
\newcolumntype{R}[1]{>{\raggedleft\let\newline\\hspace{0pt}}m{#1}}
\pagestyle{fancy}%format que permet no estar al estandar article de latex
\begin{document}
\lhead{
	\Huge '.$r['Empresa']['Value'].' \normalsize \\\\
';	if($r['Empresa']['Fiscal_Name']!='')$tex.=$r['Empresa']['Fiscal_Name'].'\\\\';
		$tex.=$r['Empresa']['Direccio'].'\\\\
    '.$r['Empresa']['Poblacio'].'\\\\
	NIF '.$r['Empresa']['NIF'].'\\\\
	Tel '.$r['Empresa']['Phone'].'\\\\
	\href{mailto:'.$r['Empresa']['email'].'}{'.$r['Empresa']['email'].'}\\\\
	\vspace{2em}
	\begin{tabular}{|l|l|}
		\hline
		'.$idrelacio.'\\\\
		Data&'.$data.' 	\\\\
	\hline
	\end{tabular}
	\\
	\hspace{35ex}%\Huge \ '.$r['tipus_relacio']['Value'].'
	\normalsize 
	}
\chead{}
\rhead{
	\rotatebox{12}{
		\scalebox{2}[4]{'.$r['tipus_relacio']['Value'].'}
		}
		\\\\
	\begin{tabular}{|p{8.5cm}|}
		\hline
		'.$nom_client.'\\\\
		NIF:'.$r['NIF'].' \\\\
		'.$direccio.'\\\\
		'.$poblacio.'\\\\
		Telef: '.$r['phone'].' \\\\
		\hline
  	\end{tabular}\\\\
	\vspace{2em}
	}

\begin{longtable}{|p{1cm}|p{'.$espaidescripcio.'mm}|r|r|r|'.$coliva.'}
	\hline
	Codi&Descripció\hspace{'.$espaidescripcio.'mm}&Quantitat&Preu '.$ivainclos.'&Import\\\\
	\hline
	\endhead
';
	foreach ($r['active_lines'] as $v){
		$tex.=str_replace('#','\#',str_replace('%','\%','\footnotesize\raggedleft{'.$v['ID_product'].'}&'.str_replace('&','\&',$v['urlde'])));
		if($v['lotnumber']!='')$tex.=str_replace('$','\$',str_replace('#','\#',str_replace('&','\&',str_replace('%','\%'," Lot: ".urldecode($v['lotnumber'])))));
		$tex.="&";
		if($v['bulk']==0)$tex.=number_format_trim($v['units'],3,$decimalsing,$thousandsing);
		else $tex.=number_format($v['units'],3,$decimalsing,$thousandsing);
		$tex.=$v['u']."&".number_format($v['cost'],2,$decimalsing,$thousandsing)."€/{$v['u']}";
		if($v['discount']>0)$tex.=' -'.number_format_trim($v['discount'],2,$decimalsing,$thousandsing).'\% ';
		if($r['tax_detall']==1){
			$tex.='&\footnotesize'.number_format_trim($v['IVA'],2,$decimalsing,$thousandsing).'\% ';
			$tex.="&".number_format($v['Base'],2,$decimalsing,$thousandsing)."€".'\\\\';
		}
		else $tex.="&".number_format($v['tline'],2,$decimalsing,$thousandsing)."€".'\\\\';
	}
	$tex.='
    \hline\end{longtable}
%\vspace{1cm}
\begin{table}[b]
	\begin{tabular}{|p{1.9cm}|p{1.8cm}|p{2cm}|p{2.6cm}|p{2.2cm}|p{1.9cm}||p{2cm}|}
		'.$peu.'
	\end{tabular}
\end{table}
\lfoot{Línees: '.$r['activelines'].'}';
global $condicions_url;
// condicions de venda: Empresa.condicions o $condicions_url de keys.php; si no n'hi ha, cap peu
$cond=!empty($r['Empresa']['condicions'])?$r['Empresa']['condicions']:$condicions_url;
if(!empty($cond)){
	$cond_url=(preg_match('#^https?://#',$cond)?'':'https://').$cond;
	$tex.='\cfoot{Consulteu condicions de venda a \href{'.$cond_url.'}{'.preg_replace('#^https?://#','',$cond).'}}
';
}
$tex.='\rfoot{'.$pagat.'}
\end{document}
';
// 	pre($tex);
	//$nf=$r['uuid'].'.tex';
	//exec ("rm {$_SERVER['DOCUMENT_ROOT']}/scale/pdf/{$r['uuid']}.*  -f ");
	//exec ("rm {$_SERVER['DOCUMENT_ROOT']}/scale/pdf/*  -f ");
	// unlink ("pdf/*");//no feia res per aixo l'he comentat
 	//$f=fopen("{$_SERVER['DOCUMENT_ROOT']}/scale/pdf/".$nf,'w')or die ;
	//fwrite($f, $tex);
	//fclose($f);
	//creo el pdf
	//crea_pdf($nf);
	//guardo el tex a la base de dades per crear la factura amb el cron
	$tex=urlencode($tex);
	if(!mysql1r("SHOW TABLES LIKE '{$prefixrelacio}pdf' ;",$link)){
		$link->query("CREATE TABLE `{$prefixrelacio}pdf` LIKE `meta_pdf`;");
	}
	$link->query("DELETE FROM `{$prefixrelacio}pdf` WHERE `uuid` = '{$r['uuid']}'");
	$r['email']=neteja_email($r['email']);
	$i="INSERT INTO `{$prefixrelacio}pdf` (`ID`, `ID_tipus_relacio`,  `uuid`,`print_A4`, `fiscal_name`,`phone`,`email`, `LaTeX`,  `datetime_creation`)
	VALUES ('{$r['ID']}', '{$r['ID_tipus_relacio']}',  '{$r['uuid']}','1','{$r['fiscal_name']}', '{$r['phone']}','{$r['email']}','$tex', now());";
    if($noenviar==TRUE){
		$i="INSERT INTO `{$prefixrelacio}pdf` (`ID`, `ID_tipus_relacio`,  `uuid`,`email_enviat`,`print_A4`, `fiscal_name`,`phone`,`email`, `LaTeX`,  `datetime_creation`)
		VALUES ('{$r['ID']}', '{$r['ID_tipus_relacio']}',  '{$r['uuid']}','1','0','{$r['fiscal_name']}', '{$r['phone']}','{$r['email']}','$tex', now());";
		debugtelegram("m'han dit que faci un pdf sense enviar",$_SERVER['HTTP_HOST']);
	}
    else  debugtelegram("m'han dit que faci un pdf i s'ha d'enviar",$_SERVER['HTTP_HOST']);
	//pre($r);
	$link->query($i)or die($i.brr().$link->error);
}
function controlDigit($ean){
  $ean= substr($ean,0,12);
  $par=0;
  $impar=0;
  $first=1;

  // Empezamos por el final
  for ($i=strlen($ean)-1; $i>=0; $i--){
    if($first%2 == 0){
      $par += $ean[$i];
    }else{
      $impar += $ean[$i]*3;
    }
    $first++;
  }
  $control = ($par+$impar)%10;
  if($control > 0){$control = 10 - $control;}
  return $control;
}
function legacyscale($codi){
//     comprovo el codi entrat
//     echo $codi;br();echo strlen($codi); br();echo controldigit($codi);br();
	global $prefixrelacio,$link;//si volguesim fer la merda del thunderbird, caldria afegir $scale_uri;
    if (strlen($codi)==13){
		$FROMindex_relacio="FROM {$prefixrelacio}index_relacio";// where ID_tipus_relacio=3";
		$qva="SELECT fiscal_name $FROMindex_relacio where `data` = curdate() AND ID_tipus_relacio=9 and fiscal_name=$codi";
		if((substr($codi,-1,1) == substr(controldigit($codi),-1,1))&&(!mysql1r("SHOW TABLES LIKE '{$prefixrelacio}index_relacio' ;",$link,true)))return true;//si no existeix la taula que la creara el programa a partir d'aquest true
		if ($codi!=mysql1r($qva,$link)){
           		 if(substr($codi,0,1)==2)return( substr($codi,-1,1) == substr(controldigit($codi),-1,1));
        		}
			else {
				estil_capcal(false);
				br();
				br();
				a($scale_uri,h1r("codi ja entrat"));die;
			}
            return false;
        }
/*    else {
        // miro si esta al servidor central per si es del major
        if (($codi!=mysql1r("select codi_factura from caixa where codi_factura=$codi and hidden_now >curdate()-1 ",$link2))|| $codi==0)
            return false;
        else return true;
    }*/
}
function legacyinsert($codi){
	global $ID_tipus_relacio_legacy,$prefixrelacio,$link;
	$valor=intval(substr($codi,7,5));
    $ID_venedor=substr($codi,1,2);
    $tiquet=substr($codi,3,4);
	if(!mysql1r("SHOW TABLES LIKE '{$prefixrelacio}index_relacio' ;",$link)){
		$link->query("CREATE TABLE `{$prefixrelacio}index_relacio` LIKE `meta_index_relacio`;");
	}if(!mysql1r("SHOW TABLES LIKE '{$prefixrelacio}line';",$link)){
		$link->query("CREATE TABLE `{$prefixrelacio}line` LIKE `meta_line`;");
	}
	$ID_relacio=mysql1r("select MAX(ID)+1 from `{$prefixrelacio}index_relacio`  where
		id_tipus_relacio='$ID_tipus_relacio_legacy'",$link); 			
	if(!$ID_relacio){//aixo només pasa un cop l'any o per instalació nova se suposa
		$ID_relacio=1;
	}
	$m['table']=$prefixrelacio.'line';
	$m['ID']=$ID_relacio;
	$m['do']='new';
	$m["{$prefixrelacio}line"]['ID_tipus_relacio']=$ID_tipus_relacio_legacy;
	$m["{$prefixrelacio}line"]['line']=1;
	$m["{$prefixrelacio}line"]['ID_product']=1;
	$m["{$prefixrelacio}line"]['description']='legacy';
	$m["{$prefixrelacio}line"]['units']=1;
	$m["{$prefixrelacio}line"]['bulk']=1;
	$m["{$prefixrelacio}line"]['cost']=$valor/100;
	$m["{$prefixrelacio}line"]['IVA']='0';
	$m["{$prefixrelacio}line"]['EQUIVALENCY_CHARGE']=0;
	$m2['table']=$prefixrelacio.'index_relacio';
	$m2['ID']=$ID_relacio;
	$m2['do']='new';
	$d['ID_client']=1;
	$d['ID_Venedor']=$ID_venedor;
	$d['ID_tipus_relacio']=$ID_tipus_relacio_legacy;
	$d['tax_detall']=0;
	$d['fiscal_name']=$codi;
	$d['ID_Pagament']=2;
	$d['relacio_oberta']=1;
	$d['data']['mday']=date('j');
	$d['data']['mon']=date('n');
	$d['data']['year']=date('Y');
	$d['uuid']=uniqid($ID_Empresa);
	$m2[$m2['table']]=$d;
	new_update($m2,$link);
	new_update($m,$link);

}
//final bascula heredada
//
function llistarelacions($e,$my){
	//listo relacions 
	global $w,$h,$decimalsing,$thousandsing, $scale_uri;
	$prefixrelacio="{$_REQUEST['anyinici']}_Empresa_{$e['ID']}_";
	if(!mysql1r("SHOW TABLES LIKE '{$prefixrelacio}index_relacio' ;",$my)){
		echo "<div/><div> no hi ha relacions per aquesta empresa aquest any a la base de dades";
		return(0);
	}
	if ($_REQUEST['ID_Pagament']>0)$and_tipus_pagament="and `ID_Pagament`='{$_REQUEST['ID_Pagament']}'";
	if ($_REQUEST['ID_client']>-1)$and_client="and `ID_client`='{$_REQUEST['ID_client']}'";
	//todo  beta
	if ($_REQUEST['ID_client']>0)$client_arxiu="_{$_REQUEST['ID_client']}";
	$data_arxiu="_{$_REQUEST['mesinici']}-{$_REQUEST['diainici']}_to_{$_REQUEST['mesfi']}-{$_REQUEST['diafi']}";
	if ($_REQUEST['ID_client']==-2)$and_client="and `ID_client`>'1'";
	//fi todo beta
	$q="SELECT * FROM {$prefixrelacio}index_relacio where ( `data` between '{$_REQUEST['anyinici']}/{$_REQUEST['mesinici']}/{$_REQUEST['diainici']}' AND '{$_REQUEST['anyinici']}/{$_REQUEST['mesfi']}/{$_REQUEST['diafi']}') AND `ID_tipus_relacio`='{$_REQUEST['ID_tipus_relacio']}' $and_tipus_pagament $and_client  ORDER BY `ID` DESC ";
	//debugtelegram($q);
	//pre($q);
	//todo beta
	$nrels=mysql1r("SELECT count(*) FROM {$prefixrelacio}index_relacio where ( `data` between '{$_REQUEST['anyinici']}/{$_REQUEST['mesinici']}/{$_REQUEST['diainici']}' AND '{$_REQUEST['anyinici']}/{$_REQUEST['mesfi']}/{$_REQUEST['diafi']}') AND `ID_tipus_relacio`='{$_REQUEST['ID_tipus_relacio']}' $and_tipus_pagament $and_client  ORDER BY `ID` DESC ",$my);
	//fi todo beta
	if(isset($_REQUEST['ID_tipus_relacio'])){
		//tenim llista!
		//pre($_SESSION);
		unset($_SESSION['llistaclients']);
		$_SESSION['llistaclients']['ID'][-2]=-2;
		$_SESSION['llistaclients']['fiscal_name'][-2]="Tots els clients menys detall";
		$_SESSION['llistaclients']['ID'][-1]=-1;
		$_SESSION['llistaclients']['fiscal_name'][-1]="Tots els clients";
		$l=$my->query($q) or die( $q.$my->error);
		$l=$my->query($q) or die( $q.$my->error);
		if($l->num_rows>=1){
			//echo $q;
			while($f=$l->fetch_assoc()){
				unset($r);
				$r=detallrelacio($f['uuid'],$e['ID'],$_REQUEST['anyinici'],$my);
				if (!in_array($r['ID_client'],$_SESSION['llistaclients']['ID'])){ 
					$_SESSION['llistaclients']['ID'][]=$r['ID_client'];
					$_SESSION['llistaclients']['fiscal_name'][]=urldecode($r['fiscal_name']);
				}
				$i=0;
				//do{
				//	$i++;
				//}while (is_array($trelacions[(($r['activelines']*10000)+$i)]));
				////}while (is_array($trelacions[(($r['Total']*10000)+$i)]));//(is_array($trelacions[(($r['Total']*10000)+$i)]));
				//$trelacions[(($r['activelines']*10000)+$i)]=$r;
				//$trelacions[(($r['Total']*10000)+$i)]=$r;
				$trelacions[]=$r;
				$t['cost']=$r['Total']+$t['cost'];
				$t['cost_price']=$r['Cost']+$t['cost_price'];
				$t[$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]]=$t[$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]]+1;
			}
			if(is_array($_SESSION['llistaclients'])){
				global $fontsize,$height;
				select2llistes_js("ID_client",$_SESSION['llistaclients']['ID'],$_SESSION['llistaclients']['fiscal_name'],
				$_REQUEST['ID_client']?$_REQUEST['ID_client']:0,"no",false,
				"style=\"vertical-align:unset; font-size: {$fontsize}px;  text-align: left;  height:{$height}px;\"");
			}
			chek_js("resum",$checked=($_REQUEST['resum']?1:0),$valor="yes");
			
			tdiv();

			tform();

			divclass("caixa","style =\"background:#ACAB00; position: relative;top:3px; text-align: center;height: unset;width:unset; padding:1%;display: inline-block; \"");
			if($_REQUEST['resum']){
				divclass("caixadescripcio","style =\"background:#ACAB00;text-align: justify; position: relative; top: 5px; display: inline-block; left:0px; padding:1%; width:98%; font-size:30px;height: unset;\"");//width:".($w<400?($w):350)."px;
				echo "En aquest periode les {$l->num_rows} relacions \"{$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]}\" estan valorades en ".number_format_trim($t['cost'],2,$decimalsing,$thousandsing).'€'." i el cost de compra ha sigut (aprox) de {$t['cost_price']} deixant un marge brut de ".(is_numeric($t['cost_price'])?round($t['cost']-$t['cost_price'],2):"NaN")."€"; 
			}
			else {
			 	divclass("caixadescripcio","style =\"background:#ACAB00;text-align: justify; position: relative; top: 5px; display: inline-block; left:0px; padding:1%; width:98%; font-size:30px;height: unset;\"");//width:".($w<400?($w):350)."px;
				echo "En aquest periode les {$l->num_rows} relacions \"{$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]}\" estan valorades en ".number_format_trim($t['cost'],2,$decimalsing,$thousandsing).'€';


			}
			//todo beta
			if ($_SESSION['format']=="spreadsheet"){ ainf("{$prefixrelacio}{$client_arxiu}{$data_arxiu}.ods","💾"); echo"espera que la plana acabi!";} tdiv();
			//fi todo beta
			unset($r);
			//ksort($trelacions);
			//ksort($_SESSION['llistaclients']['ID']);
			//ksort($_SESSION['llistaclients']['fiscal_name']);
			//debugtelegram($_SESSION['llistaclients']);
			$trelacions=array_reverse($trelacions);	
			//pre($trelacions[0]);
			if ($_SESSION['format']=="spreadsheet"){
				require_once '../vendor/autoload.php';				
				$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
				//\PhpOffice\PhpSpreadsheet\Settings::setLocale(\PhpOffice\PhpSpreadsheet\Locale::ca-AD);
				$spreadsheet->getProperties()
    					->setTitle("{$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]} de '{$_REQUEST['anyinici']}/{$_REQUEST['mesinici']}/{$_REQUEST['diainici']}' AND '{$_REQUEST['anyinici']}/{$_REQUEST['mesfi']}/{$_REQUEST['diafi']}  ")
					->setSubject("Aquestes son els/les {$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]} de l'empresa {$trelacions[0]['Empresa']['Value']} i també una fulla que conté totes les línees d'aquestes ")
					->setDescription('Arxiu generat automàgicament al servidor el @'.date("Y-m-d H:i:s"));
				$sheet = $spreadsheet->getActiveSheet();
				$sheet->setTitle("{$l->num_rows} {$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]}");
				$sheet->getColumnDimension('A')->setWidth(10);
				$sheet->getColumnDimension('B')->setWidth(10);
				$sheet->getColumnDimension('C')->setWidth(15);
				$sheet->getColumnDimension('D')->setWidth(20);
				$sheet->getColumnDimension('E')->setWidth(10);
				$sheet->getColumnDimension('F')->setWidth(75);
				$sheet->getColumnDimension('G')->setWidth(35);
				$spreadsheet->getActiveSheet()->setCellValue('A1',"Codi");
				$spreadsheet->getActiveSheet()->setCellValue('B1',"Unitats");
				$spreadsheet->getActiveSheet()->setCellValue('C1',"Tipus unitari");
				$spreadsheet->getActiveSheet()->setCellValue('D1',"Preu");
				$spreadsheet->getActiveSheet()->setCellValue('E1',"Lot");
				$spreadsheet->getActiveSheet()->setCellValue('F1',"Descripcio");
				$spreadsheet->getActiveSheet()->setCellValue('G1',"{$r['tipus_relacio']['Value']} {$r['Empresa']['ID']}.{$r['ID']} ");
				$i0=2;
			}
			foreach ($trelacions as $k=>$r){
				if ($_SESSION['format']=="spreadsheet"){
					$n=2;
					//pre("soc la $k {$r['ID']}");
					//if($_REQUEST['mesinici']==$_REQUEST['mesfi']){
					//todo beta
					if($nrels<100){
					//fi todo beta
						$spreadsheet->createSheet()->setTitle("{$r['tipus_relacio']['Value']} {$r['Empresa']['ID']}.{$r['ID']} ");
						$spreadsheet->setActiveSheetIndex($k+1);
						$sheet = $spreadsheet->getActiveSheet();
						$sheet->getColumnDimension('A')->setWidth(10);
						$sheet->getColumnDimension('B')->setWidth(10);
						$sheet->getColumnDimension('C')->setWidth(15);
						$sheet->getColumnDimension('D')->setWidth(20);
						$sheet->getColumnDimension('E')->setWidth(10);
						$sheet->getColumnDimension('F')->setWidth(75);
						$sheet->getColumnDimension('G')->setWidth(35);
						$sheet->getColumnDimension('H')->setWidth(75);
						//$sheet->getColumnDimension('I')->setWidth(75);
						//$sheet->getColumnDimension('J')->setWidth(75);
						//$sheet->getColumnDimension('K')->setWidth(75);
						$spreadsheet->getActiveSheet()->setCellValue('A1',"Codi");
						$spreadsheet->getActiveSheet()->setCellValue('B1',"Unitats");
						$spreadsheet->getActiveSheet()->setCellValue('C1',"Tipus unitari");
						$spreadsheet->getActiveSheet()->setCellValue('D1',"Preu sense IVA");
						$spreadsheet->getActiveSheet()->setCellValue('E1',"IVA");
						$spreadsheet->getActiveSheet()->setCellValue('F1',"Descripcio");
						$spreadsheet->getActiveSheet()->setCellValue('G1',"{$r['tipus_relacio']['Value']} {$r['Empresa']['ID']}.{$r['ID']} @ {$r['data']}");
						$spreadsheet->getActiveSheet()->setCellValue('H1',urldecode("{$r['fiscal_name']}"));
						//$spreadsheet->getActiveSheet()->setCellValue('I1',"{$r['data']}");
						$spreadsheet->getActiveSheet()->setCellValue('G3',"Linees actives");
						$spreadsheet->getActiveSheet()->setCellValue('G4',"{$r['activelines']}");
						$spreadsheet->getActiveSheet()->setCellValue('G5',"Total en Euros");
						$spreadsheet->getActiveSheet()->setCellValue('G6',"{$r['Total']}");
						//todo beta
						$spreadsheet->getActiveSheet()->setCellValue('G7',"Base imponible");
						$spreadsheet->getActiveSheet()->setCellValue('G8',"{$r['base']}");
						$i=9;
						foreach($r['IVA']as $iva=>$valor){
							$spreadsheet->getActiveSheet()->setCellValue("G{$i}","IVA $iva:");
							$spreadsheet->getActiveSheet()->setCellValue("H{$i}","$valor");
							$i++;
						}
						$i++;
						foreach($r['B']as $iva=>$valor){
							$spreadsheet->getActiveSheet()->setCellValue("G{$i}","Base IVA $iva:");
							$spreadsheet->getActiveSheet()->setCellValue("H{$i}","$valor");
							$i++;
						}
						//fi todo beta
					}	
					foreach( $r["active_lines"] as $linea){
						//pre($linea);
						//todo beta
						//if($_REQUEST['mesinici']==$_REQUEST['mesfi']){
						if($nrels<100){
						//fi todo beta
							$spreadsheet->setActiveSheetIndex($k+1);
							$spreadsheet->getActiveSheet()->setCellValue('A'.$n,$linea['ID_product']);
							$spreadsheet->getActiveSheet()->setCellValue('B'.$n,$linea['units']);
							$spreadsheet->getActiveSheet()->setCellValue('C'.$n,$linea['bulk']==1?"Kg":"Units");
							$spreadsheet->getActiveSheet()->setCellValue('D'.$n,number_format_trim($linea['cost'],2));
							$spreadsheet->getActiveSheet()->setCellValue('E'.$n,$linea['IVA']);
							$spreadsheet->getActiveSheet()->setCellValue('F'.$n,urldecode($linea['description']));
							$spreadsheet->setActiveSheetIndex(0);
						}
						$spreadsheet->getActiveSheet()->setCellValue('A'.$i0,$linea['ID_product']);
						$spreadsheet->getActiveSheet()->setCellValue('B'.$i0,$linea['units']);
						$spreadsheet->getActiveSheet()->setCellValue('C'.$i0,$linea['bulk']==1?"Kg":"Units");
						$spreadsheet->getActiveSheet()->setCellValue('D'.$i0,number_format_trim($linea['cost'],2));
						$spreadsheet->getActiveSheet()->setCellValue('E'.$i0,$linea['lotnumber']);
						$spreadsheet->getActiveSheet()->setCellValue('F'.$i0,urldecode($linea['description']));
						$spreadsheet->getActiveSheet()->setCellValue('G'.$i0,"{$r['Empresa']['ID']}.{$r['ID']} ".urldecode("{$r['fiscal_name']}"));
						$n++;
						$i0++;
						$resum[$linea['description']][number_format($linea['cost'],2)]['ID_product']=$linea['ID_product'];
						$resum[$linea['description']][number_format($linea['cost'],2)]['units']=$resum[$linea['description']][number_format($linea['cost'],2)]['units']+$linea['units'];
						$resum[$linea['description']][number_format($linea['cost'],2)]['bulk']=$linea['bulk'];
						$resum[$linea['description']][number_format($linea['cost'],2)]['description']=urldecode($linea['description']);
						$resum[$linea['description']][number_format($linea['cost'],2)]['cost']=number_format($linea['cost'],2);

					}					
				}
				//divclass("caixadescripcio","style =\"background:#ACAB00; position:relative; top:110px; left:5px; width:".($w)."px; height:815px; font-size:33px;padding:1%;\"");
				divclass("caixadescripcio","style =\"background:#ACAB00; text-align: center; position: relative; top: 5px; display: inline-block; left:0px; width:".($w<400?($w):350)."px;".($r['deletedlines']>1?" color:red;":"")." font-size:22px;height: unset;\"");//width:".($w<420?($w):420)."px;
				//echo $k;
				ainf("./?do=edit&uuid={$r['uuid']}&ID_Empresa={$_SESSION['rel']['Empresa']['ID']}&year={$_REQUEST['anyinici']}","✍️");
				ainf("../scale/tiquet.php?uuid={$r['uuid']}&ID_Empresa={$_SESSION['rel']['Empresa']['ID']}&year={$_REQUEST['anyinici']}&close=true","🧾");
				if($r['ID_Pagament']=='1')echo"💶";
				if($r['ID_Pagament']=='2')echo"💳";
				if($r['ID_Pagament']>'2')echo"🏦";
				ainf("../pdf/{$r['uuid']}.pdf","🖺");
				if($r['phone']!=""){
					if (strlen($r['phone']) == 9 && $r['phone'][0] != '+') $r['phone']="+34".$r['phone'];
					ainf("tel:{$r['phone']}","📲");
					global $pdf_public_url;
					$pdf_base=!empty($pdf_public_url)?$pdf_public_url:"{$uri}/pdf";
					$body=urlencode("$pdf_base/{$r['uuid']}.pdf");//.html_relacio($r));
					$mailbody=rawurlencode(html_relacio($r));
					//debugtelegram($r,'relacio a mati','302887425');
					$subject=str_replace(" ","%20","Enviem enllaç a {$r['tipus_relacio']['Value']} de la data {$r['data']} i Valor {$r['Total']}€ ");
					ainf("sms:{$r['phone']}?body=Enviem%20{$r['tipus_relacio']['Value']}%20$pdf_base/{$r['uuid']}.pdf ","📟");
					ainf("https://api.whatsapp.com/send?phone={$r['phone']}&text=$subject$body",imgr("../scale/img/whatsapp.svg","22px","22px"));
					if(isset($r['email'])){
						//ainf("mailto:{$r['email']}?subject=$subject&body=$mailbody",imgr("{$scale_uri}img/thunderbird.svg",'20px','20px'));
						ainf("mailto:{$r['email']}?subject=$subject&body=$body","📧");
						ainf("./?do=pdf&uuid={$r['uuid']}&ID_Empresa={$_SESSION['rel']['Empresa']['ID']}&year={$_REQUEST['anyinici']}","📨");
					}
				}
				br();
				echo urldecode($r['fiscal_name'])." {$e['ID']}.{$r['ID']}";
				br();
				echo "({$r['activelines']}) ({$r['deletedlines']})". number_format($r['Total'],2,$decimalsing,$thousandsing)."€ @";
				//br();
				//echo $r['active_lines'][1]['hidden_now'].($r['activelines']>1?"-".substr($r['active_lines'][$r['activelines']]['hidden_now'],11):"");
				//br();
				if ($r['activelines']>0)echo substr($r['active_lines'][0]['hidden_now'],5,11);
				echo ($r['activelines']>1?"-".(strtotime($r['active_lines'][($r['activelines']-1)]['hidden_now'])-strtotime($r['active_lines'][0]['hidden_now']))."s":"");

				//pre($r);
				
				tdiv();

			}
			if ($_SESSION['format']=="spreadsheet"){
				//usort($resum,'cmp3d');
				//debugtelegram ($resum);
				//pre($resum);
				/*$objects = array();
					foreach ($data as $description => $prices) {
    						foreach ($prices as $cost => $details) {
       							 $objects[] = array(
        						    'description' => $details['description'],
        						    'cost' => $cost,
        						    'units' => $details['units'],
            						'details' => $details
        						);
    						}
					}*/
				// Ordenem l'array d'objectes
				//usort($objects, 'cmp3d');
				usort($resum, 'cmp3d');
				//asort($resum);
				$i0=2;debugtelegram($resum);
				foreach ($resum as $k=> $tpro){
					foreach ($tpro as $cost=>$data){
						$spreadsheet->setActiveSheetIndex(0);
						$spreadsheet->getActiveSheet()->setCellValue('H1',"Codi");
						$spreadsheet->getActiveSheet()->setCellValue('I1',"Preu");
						$spreadsheet->getActiveSheet()->setCellValue('J1',"Sum units");
						$spreadsheet->getActiveSheet()->setCellValue('K1',"tipus");
						$spreadsheet->getActiveSheet()->setCellValue('L1',"Descripcio");
						$sheet->getColumnDimension('H')->setWidth(10);
						$sheet->getColumnDimension('I')->setWidth(12);
						$sheet->getColumnDimension('J')->setWidth(12);
			 			$sheet->getColumnDimension('K')->setWidth(75);
						$spreadsheet->getActiveSheet()->setCellValue('H'.$i0,$data['ID_product']);
						$spreadsheet->getActiveSheet()->setCellValue('I'.$i0,$data['cost']);
						$spreadsheet->getActiveSheet()->setCellValue('J'.$i0,$data['units']);
						$spreadsheet->getActiveSheet()->setCellValue('K'.$i0,$data['bulk']==1?"Kg":"Units");
						$spreadsheet->getActiveSheet()->setCellValue('L'.$i0,urldecode($data['description']));
						
						$i0++;
					}
				}
				$writer = new \PhpOffice\PhpSpreadsheet\Writer\Ods($spreadsheet);
				//todo beta
				$writer->save("{$prefixrelacio}{$client_arxiu}{$data_arxiu}.ods");
				//fi todo beta
			} 
			//array_multisort($trelacions,SORT_DESC,'Total');
			//sort($trelacions);
			//pre($trelacions);
			tdiv();
		}
		else{ 
			tdiv();
			tform();
			h2("No hi ha {$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]} en aquest periode");
			//pre($_REQUEST);
		}

	}
	return($t);
	//pre($e);
}
function relacionsformlinea($rel,$line,$number){
	global $decimalsing,$thousandsing,$fontsize,$link,$s,$r;
	//$fontsize=$fontsize-2;
			if($line['deleted_line']=='1')$ratllat="text-decoration:line-through; color: red;";
			form();//'./',"POST");
			divclass('rellinea');//,"style=\" text-align: center; background: #889ccf; width:100%; display: inline-block; \"");
			hidden('do',"updateline");
			hidden('uuid',$r['uuid']);
			hidden('year',$r['year']);
			hidden('line',$line['line']);
			text_js('description',false,$line['urlde'],255,false,false,false,"style=\"font-size: {$fontsize}px; $ratllat text-align: left;  Width:98%;\"");
			divclass('Quantitats');
			text_js('units',false,number_format_trim($line['units'],3,$decimalsing,$thousandsing),8,false,false,true,"style=\" $ratllat font-size: {$fontsize}px;  text-align: left;  Width:5%;\"");
			$bulk=array(0=>'unitat',1=>"Kg");
			select1llista_js('bulk',$bulk,$line['bulk'],false,"style=\"font-size: {$fontsize}px; $ratllat text-align: left;  Width:{$wbulk}px;\"");
			text_js('lotnumber',false,urldecode($line['lotnumber']),255,false,false,false,"style=\"font-size: {$fontsize}px; $ratllat text-align: left;  Width:10%;\"","no s'ha entrat lot");	
			$IVA=mysql2array("SELECT ID,`Value` from `IVA` order by `Value` ",$link);
			foreach($IVA[1] as $k=>$v){
				$IVA[2][$k]=number_format_trim($v,2,$decimalsing,$thousandsing)."%";
			}
			$oldiva=mysql1r("SELECT ID from `IVA` where `Value`='{$line['IVA']}' " ,$link);
			$cost=$line['cost'];//detall_relacio et calcula el cost amb iva o sense iva depenent si l'index vol veure les taxes
			text_js('cost',false,number_format($line['cost'],2,$decimalsing,$thousandsing),8,false,false,true,"style=\"font-size: {$fontsize}px; $ratllat text-align: left;  Width:7%;\"");
			select2llistes_js('IVA',$IVA[0],$IVA[2],$oldiva,false,false,"style=\"font-size: {$fontsize}px; $ratllat  text-align: left;  Width:{$wiva}px;\"");
			$deleted_line=array(0=>"Activa",1=>'Eliminada');
			select1llista_js('deleted_line',$deleted_line,$line['deleted_line'],false,"style=\"font-size: {$fontsize}px;  $ratllat  text-align: left;  Width:5%;\"");
			text_js('discount',false,$line['discount']=='0'?'':number_format_trim($line['discount'],2,$decimalsing,$thousandsing),8,false,false,true,"style=\"font-size: {$fontsize}px; $ratllat text-align: right;  Width:10%;\"");
			$totalstyle="font-size: {$fontsize}px;  text-align: right; position: relative; display: inline-grid;";
			divclass("box","style=\" Width:".($wtotal)."px; $totalstyle $ratllat\"");
			if($rel['tax_detall']==1)echo number_format_trim($line['Base'],2,$decimalsing,$thousandsing)."€";
			else echo number_format_trim($line['tline'],2,$decimalsing,$thousandsing)."€";
			tform();
}


function form_index($rel){
	global $fontsize,$decimalsing,$thousandsing,$link,$_REQUEST;
	divclass('fulla',"style=\" text-align: center; background: #889ccf; width:98%; padding:1%; display: inline-flex; \"");	
	divclass("caixa","style =\"background:#ACAB00; text-align: center; position: relative; top: 5px; display: inline-table; left:0px; padding:8px; font-size:{$fontsize}px;width:550px;height: 174px; \"");
	echo "{$rel['tipus_relacio']['Value']}:{$rel['Empresa']['ID']}.{$rel['ID']}";
	echo " conté: {$rel['activelines']} linees actives</br>";
		tab();echo"Base";
	todr();echo"%IVA";
	todr();echo"IVA";
	if(($rel['Empresa']['sell_wholesale']=='1')&&($rel['equivalency_charge']==1)){
		todr();echo"%RecEq";
		todr();echo"RecEq";
	}
	if($rel['discount']>0){todr();echo"Desc";}
	todr();echo"Total";
	$i=0;
	foreach ($rel['B'] as $k=>$v){
		tor();echo number_format_trim($v,2,$decimalsing,$thousandsing)."€";
		todr();echo number_format_trim($k,2,$decimalsing,$thousandsing).'%';
		todr();echo number_format_trim($rel['IVA'][$k],2,$decimalsing,$thousandsing)."€";
		if(($rel['Empresa']['sell_wholesale']=='1')&&($rel['equivalency_charge']==1)){
			$i2=0;
			foreach($rel['EC'] as $eck=>$ecv){
				if($i2==$i){
					todr();echo number_format_trim($eck,2,$decimalsing,$thousandsing).'%';
					todr();echo number_format_trim($ecv,2,$decimalsing,$thousandsing).'€';
				}
				$i2++;
			}
			if($rel['discount']>0){todr();echo number_format_trim($rel['discount'],2,$decimalsing,$thousandsing).'%';}
		}
		$i++;
	}echo "<td rowspan=\"$i\">";echo number_format_trim($rel['Total'],2,$decimalsing,$thousandsing).'€';
	ttable();
	
	tdiv();
	divclass("caixa","style =\"background:#ACAB00; text-align: center; position: relative; top: 5px; display: inline-block; left:0px;width:100%; font-size:{$fontsize}px; \"");form();
	hidden('do',"update_payment");
	//if($rel['tipus_relacio']['bool_allow_payment']=="1")$v=mysql2array("SELECT `ID`, concat (`ID`,`Value`) FROM `Pagament` WHERE `ID` <= '10'",$link);//factura, proforma o bascula heredada
	if($rel['tipus_relacio']['bool_allow_payment']=="1")$viap=mysql2array("SELECT `ID`, `Value` FROM `Pagament` WHERE `ID` <= '10'",$link);//factura, proforma o bascula heredada
	else { 
		$viap[0]=array(0,1,2);
		$viap[1]=array("Tria","Continua editant {$rel['tipus_relacio']['Value']}","Deixa de editar {$rel['tipus_relacio']['Value']} ");
	}
	//echo '<div style=" display:table-cell; vertical-align:middle; width:580px;  left:'.(($w/2)-30).'px;top:2px;position:absolute; ">';
	select2llistes_js('viapagament',$viap[0],$viap[1],$rel['ID_Pagament'],'no',false,"class=\"select-css\"");
	$_SESSION['update_payment']=$rel;
	hidden('year',$_REQUEST['year']);
	tform();
	//tdiv();
	tdiv();
	tdiv();
	divclass("caixa","style =\"background:#ACAB00; text-align: center; position: relative; top: 5px; display: inline-block; left:0px;width:100%; font-size:{$fontsize}px; \"");
	form();
	hidden('do','update_index');
	/*
	 * echo "Codi Client:<br/>";
	text_js('ID',false,$rel['ID_client'],8,false,false,true,"style=\"font-size: {$fontsize}px; text-align: right;  Width:75%;\"");
	echo "<br/>NIF:<br/>";
	text_js('ID',false,$rel['NIF'],8,false,false,true,"style=\"font-size: {$fontsize}px; text-align: right;  Width:75%;\"");
	//echo "<br/>NI  fdjklasj l;kfalksjl fkasdjf al;sdfjask l;fdjkasl ;fjdksal; fdjdsakl fdjasdkl fdjdaslkf djdaskl fdjaslkf djdaslk fdjadslk fdjaksl; fjadskl fjF:<br/>jfkldas fjkldsa ;fjkdlsa;f jkdasl f;jaksldf askdlf; ajskd fghipurequ eiorqwopafsl zcxm/.M <br/>";

	pre($rel);
	 */
	  echo "ID Client:<br/>";
    echo "<input type=\"text\" id=\"client-id\" name=\"client-id\" value=\"{$rel['ID_client']}\" style=\"font-size: {$fontsize}px; text-align: right;  Width:75%;\" />";
    echo "<br/>NIF:<br/>";
    echo "<input type=\"text\" id=\"client-nif\" name=\"client-nif\" value=\"{$rel['NIF']}\" style=\"font-size: {$fontsize}px; text-align: right;  Width:75%;\" />";
    echo "<br/>Nom fiscal:<br/>";
    echo "<input type=\"text\" id=\"client-fiscal-name\" name=\"client-fiscal-name\" value=\"{$rel['fiscal_name']}\" style=\"font-size: {$fontsize}px; text-align: right;  Width:75%;\" />";
    echo "<br/>Direcció fiscal:<br/>";
    echo "<input type=\"text\" id=\"client-fiscal-address\" name=\"client-fiscal-address\" value=\"{$rel['fiscal_address']}\" style=\"font-size: {$fontsize}px; text-align: right;  Width:75%;\" />";
    echo "<br/>Població:<br/>";
    echo "<input type=\"text\" id=\"client-poblacio\" name=\"client-poblacio\" value=\"{$rel['Poblacio']}\" style=\"font-size: {$fontsize}px; text-align: right;  Width:75%;\" />";
    echo "<br/>Nom comercial:<br/>";
    echo "<input type=\"text\" id=\"client-comercial-name\" name=\"client-comercial-name\" value=\"{$rel['comercial_name']}\" style=\"font-size: {$fontsize}px; text-align: right;  Width:75%;\" />";
    echo "<br/>Direcció d'entrega:<br/>";
    echo "<input type=\"text\" id=\"client-deliver-address\" name=\"client-deliver-address\" value=\"{$rel['deliver_address']}\" style=\"font-size: {$fontsize}px; text-align: right;  Width:75%;\" />";
    echo "<br/>Telèfon:<br/>";
    echo "<input type=\"text\" id=\"client-phone\" name=\"client-phone\" value=\"{$rel['phone']}\" style=\"font-size: {$fontsize}px; text-align: right;  Width:75%;\" />";
    echo "<br/>Correu electrònic:<br/>";
    echo "<input type=\"text\" id=\"client-email\" name=\"client-email\" value=\"{$rel['email']}\" style=\"font-size: {$fontsize}px; text-align: right;  Width:75%;\" />";
    echo "<br/>Descompte:<br/>";
    echo "<input type=\"text\" id=\"client-discount\" name=\"client-discount\" value=\"{$rel['discount']}\" style=\"font-size: {$fontsize}px; text-align: right;  Width:75%;\" />";
	tform();
//	tdiv();
	tdiv();
}

function compararunitatsvenudes($a,$b){
	 if ($a['units'] == $b['units']) {
        return 0;
    }
    return ($a['units'] < $b['units']) ? 1 : -1;
}
function cmp3d($a,$b){
	if ($a['description'] == $b['description']) {
        if ($a['cost'] == $b['cost']) {
            return $a['units'] - $b['units'];
        }
        return $a['cost'] - $b['cost'];
    }
    return strcmp($a['description'], $b['description']);
}
function package_pvp($product,$package,$link){//retorna el pvp amb iva
	$package_pvp=$link->query("SELECT * from package_pvp where `ID`={$product['ID']} AND `ID_package_type`={$package['ID_package_type']}")->fetch_assoc();
	//debugtelegram($product,"producte");debugtelegram($package,"paquet");
	if($product['bool_in_bulk']==1)$package['weight']=$package['weight']/1000;//si es granel pasem de grams a kilos
	if($product['bool_in_bulk']==0)$package['weight']=$package['units_package'];//si es granel pasem de grams a kilos
	if(!isset($package_pvp))return false ;
	$tipusiva=mysql1r("select tipusiva from tarifes where ID='{$product['ID']}'and ultimpreu='1'",$link);
	$iva=mysql1r("select Value from IVA where ID='{$tipusiva}'",$link);
	if(is_numeric($package_pvp['pvp']))return ($package_pvp['pvp']);
	$pattern = "/(pvp|pv\d+)(ii)?\*(\d+\.\d+)/";
	$matches = [];
	if ((is_string($package_pvp['pvp']))&&(preg_match($pattern, $package_pvp['pvp'], $matches))) {
		$tarifa=$matches[1];
		$ii=!empty($matches[2]);
		$guany=$matches[3];
		$preu=mysql1r("select {$tarifa} from tarifes where ID='{$product['ID']}' and ultimpreu='1'",$link);
		$tipusiva=mysql1r("select tipusiva from tarifes where ID='{$r['ID']}' and ultimpreu='1'",$link);
		if($ii) return ($package['weight']*$preu*(1+($iva/100))*$guany);
		else{ 
			$iva=0;
			return ($package['weight']*($preu*(1+($iva/100))*$guany));//son el mateix no sé si a preus haure de semrpe posar ii?
		}
	}
	else return false;
}
function urldecod($string) {
  if ($string == NULL) {
    return NULL;
  } else {
    return urldecode($string);
  }
}
function tlineapes($pes,$u="Kg"){
	global $w,$h,$decimalsing,$thousandsing;
	if($w<$h)divclass('pes',"style =width:".($w-25)."px;");
	else divclass('pes',"style = height:".($h/5)."px;top:2px;width:".(($w/2)-15)."px;");
	echo "{$abono}".number_format(($pes),3,$decimalsing,$thousandsing);
	echo "$u&nbsp;";
	tdiv();
}
function tlineapreu($string){
	global $w,$h;
	if($w<$h)divclass('caixapreu',"style =width:".($w-25)."px;");
	else divclass('caixapreu',"style =height:".($h/5)."px;top:".(($h/5)+2)."px;width:".(($w/2)-15)."px;");
	echo $string.'&nbsp;</div>';
}
function tlineavalor($preu){
	global $w,$h,$link,$idbascula,$decimalsing,$thousandsing;
	if($w<$h)divclass('caixatlinea',"style =width:".($w-25)."px;");
	else divclass('caixatlinea',"style =height:".($h/5)."px;top:".(2*(($h/5)+2))."px;width:".(($w/2)-15)."px;");
	echo number_format($preu,2,$decimalsing,$thousandsing).'€&nbsp;';
	tdiv();
}

function tlineadescripcio($string){
	global $w,$h;
	if($w<$h)divclass('caixadescripcio',"style =width:".($w-25)."px;");
	else divclass('caixadescripcio',"style =height:".(2*($h/5))."px;top:".(3*(($h/5)+2))."px;width:".(($w/2)-15)."px;");
	if($w<$h)divclass('Descripcio');
	else divclass('Descripcio',"style =width:".(($w/2)-5)."px;text-aling:center;");
	echo $string;
	tdiv();tdiv();
}
function newline_extract($line,$rel,$link){
	$ID=trim($line['newline']);
	if($ID['0']=='-'){
		$ID=substr($ID,1);
		$abono='-';
	}
	if(is_numeric($ID[0])){
		if(!strpos($ID,'*')&&!strpos($ID,'+')&&!strpos($ID,'x')&&!strpos($ID,'l'))$ID=$ID;//no conte ni * ni + ni x, ni l --> x sinonim de * l sinonim de +
		if((!strpos($ID,'*')) && (strpos($ID,'+')))list($ID,$lotnumber)=explode("+",$ID);// no*si+
		if((!strpos($ID,'x')) && (strpos($ID,'l')))list($ID,$lotnumber)=explode("l",$ID);// no x sil
		if(strpos($ID,'*')&&(!strpos($ID,'+')))list($ID,$units)=explode("*",$ID);// si * no +
		if(strpos($ID,'x')&&(!strpos($ID,'l')))list($ID,$units)=explode("x",$ID);// si x no l
		if(strpos($ID,'*')&&strpos($ID,'+')){ //si * si +
			list($ID,$units)=explode("*",$ID);
			list($units,$lotnumber,$expirationdate)=explode("+",$units);
		}
		if(strpos($ID,'x')&&strpos($ID,'l')){ //si X si L
			list($ID,$units)=explode("x",$ID);
			list($units,$lotnumber)=explode("l",$units);
			list($lotnumber,$expirationdate)=explode("d",$lotnumber);
		}
		if(strpos($lotnumber,'+'))list($lotnumber,$expirationdate)=explode("+",$lotnumber);

	}
	if($ID['0']=='+'){
		//preu directe
		$ID=substr($ID,1);
		//no conte ni * ni + ni x, ni l --> x sinonim de * l sinonim de +
		if(!strpos($ID,'*')&&!strpos($ID,'+')&&!strpos($ID,'x')&&!strpos($ID,'l')){$preudirecte=$ID;}
		if((!strpos($ID,'*')) && (strpos($ID,'+')))list($ID,$lotnumber)=explode("+",$ID);// no*si+
			if((!strpos($ID,'x')) && (strpos($ID,'l')))list($ID,$lotnumber)=explode("l",$ID);// no x sil
		if(strpos($ID,'*')&&(!strpos($ID,'+')))list($ID,$units)=explode("*",$ID);// si * no +
		if(strpos($ID,'x')&&(!strpos($ID,'l')))list($ID,$units)=explode("x",$ID);// si x no l
		if(strpos($ID,'*')&&strpos($ID,'+')){ //si * si +
			list($ID,$units)=explode("*",$ID);
			list($units,$lotnumber,$expirationdate)=explode("+",$units);
		}
		if(strpos($ID,'x')&&strpos($ID,'l')){ //si X si L
			list($ID,$units)=explode("x",$ID);
			list($units,$lotnumber)=explode("l",$units);
			list($lotnumber,$expirationdate)=explode("d",$lotnumber);
		}
		if(strpos($lotnumber,'+'))list($lotnumber,$expirationdate)=explode("+",$lotnumber);
		$preudirecte=$ID;
		$ID="preudirecte";
	}
	if($ID=='')return(FALSE);
	if ((strlen($ID)==13)&&($ID[0]==2)){
	//	echo 'codi barres legacy';
		if (legacyscale($ID)==1){
			legacyinsert($ID);
		}
		return(TRUE);
	}
	if((is_numeric($ID))&&(strlen($ID)>8)&&($ID[0]!=2)){
	//codi de barres, no tots tenen 13 de longitut, per exemple el oli de coco cuinar
	//echo "ean?";
		$ID=mysql1r("SELECT ID from ean where Value='{$ID}'",$link);
		$line['newline']=$ID;
		return newline_extract($ID);
	}
	if(preg_match('/^\d+\.\d+$/', $ID)){//codi.paquet
		list($ID,$package_type)=explode('.',$ID);
		$package=$link->query("SELECT * from package_format where `ID`=$ID AND `ID_package_type`=$package_type AND  `hidden_date_time` = (  SELECT MAX(`hidden_date_time`) from package_format where `ID` ='{$ID}' AND `ID_package_type`='{$package_type}')")->fetch_assoc();
		if(!is_array($package)){return(FALSE);}
		$product=$link->query("SELECT * from tarifes where `ID`=$ID AND `ultimpreu`=1")->fetch_assoc();
		//$package_pvp=$link->query("SELECT * from package_pvp where `ID`=$IDproduct AND `ID_package_type`=$package_type")->fetch_assoc;
		//if(isset($package_pvp))
		$tarifa=mysql1r("SELECT `column_name` from `tarifa` where `ID`= (SELECT `id_tarifa` FROM Clients where `ID`='{$rel['ID_client']}')",$link);
		if ($tarifa=='pvp')//només busco preu pvp del paquet si la tarifa del Client és pvp
		$product['pvp']=package_pvp($product,$package,$link);//retorna falç si no troba un preu sobreescrit
		if(!$product['pvp']){
			//debugtelegram($package);\
			//debugtelegram($units);
			if($product['bool_in_bulk']==1){
				if($units>0)$units=($package['weight']/1000)*$units;
				else $units=$package['weight']/1000;
			}
			$product['pvp']=mysql1r("Select `$tarifa` from `tarifes` where ID=$ID and ultimpreu='1'",$link);
		}
		else $product["bool_in_bulk"]=0;
		//debugtelegram($product,"product");
		//debugtelegram($package,"pack");
		if(is_numeric($units)&&($units!=" ")){//codi*unitats
			$units=$abono.$units;
			$bulk=0;
		}
		else{//codi i una unitat
			$units=$abono.'1';
			$bulk=0;
		}
		//dades del formulari
		$o[3]=$product["bool_in_bulk"];
		$o[4]=$product['pvp'];
		$sqldes="select description from package_description where `ID`='{$ID}' AND `ID_package_type`='{$package_type}' AND `lang`='ca'";
		$o[1]=$link->query($sqldes)->fetch_assoc()['description'];
		//debugtelegram($sqldes);
		//debugtelegram($o,"o");
		$pes=number_format_trim(($package['weight']/1000),3,$decimalsing,$thousandsing);
		if($o[1]==""){
			$o[1]=$link->query("select Value from productnamesurlencoded where `ID`='{$ID}' AND `lang`='ca'")->fetch_assoc()['Value'];// * {$pes}kg";
			$ppes=mysql1r("select `weight` from `package_format` where `ID`='{$ID}'and `ID_package_type`='{$package_type}'AND `hidden_date_time` = (  SELECT MAX(`hidden_date_time`) from package_format where `ID` ='{$ID}' AND `ID_package_type`='{$package_type}')",$link);
			if($ppes>1000)$ppes=number_format_trim(($ppes/1000),3,$decimalsing,$thousandsing)."Kg";
			else $ppes=$ppes."g";
			$o[1].=" $ppes";
		}
		$o[1]=urldecod($o[1]);
		$o[5]=$product['pv4'];
		$IVA=$IVA=trim(mysql1r("SELECT `Value` from IVA where ID={$product['tipusiva']}",$link),'%');
		$formularilinea=true;
		//debugtelegram($ID);
	}
	if(is_numeric($ID[0])){//si ID és un codi de producte i no tenia codi.paquet
		$sql="select exist,value, formatpvp, bulk, pvp,cost from scalelist where ID=$ID ";
	 	//echo $sql;br();die;
		$o=$link->query($sql) or die(header("Location: {$s[$app]['uri']}"));
		$o=mysql2vector($sql,$link);
		$tarifa=mysql1r("Select `id_tarifa` from `Clients` where `ID`='{$rel['ID_client']}' ",$link);
		$tarifa=mysql1r("Select `column_name` from `tarifa` where `ID`='$tarifa' ",$link);
		$cost="sense iva";
		$o[4]=mysql1r("SELECT `{$tarifa}` from `tarifes` where `ID`=$ID and `ultimpreu`='1'",$link);
		if($o[0]==1){//codi vàlid
			$units=str_replace(",",".",$units);//aixo ens canvia la coma per el punt per poder entrar-ho a la base de dades
			if($o[3]==1){//granel
				if(is_numeric($units)&&($units!=" ")){//codi*unitats granel
					$units=$abono.$units;
					$bulk=1;
				}
				else{//codi i no han posat pes
					//echo "no han posat pes";
					$units=0;
					$bulk=1;
						
				}
			}
			else{ //envasat
				if(is_numeric($units)&&($units!=" ")){//codi*unitats
					$units=$abono.$units;
					$bulk=0;
				}
				else{//codi i una unitat
					$units=$abono.'1';
					$bulk=0;
				}
			}
			$tipusIVA=mysql1r("SELECT `tipusiva` FROM `tarifes` WHERE `ultimpreu` = '1' AND `ID` = '$ID'",$link);
			$IVA=trim(mysql1r("SELECT `Value` from IVA where ID=$tipusIVA",$link),'%');
			$o['1']=urldecode($o['1']);
		}
		else{//codi invalid
			return(FALSE);
		}
	}
}//end newline_extract
function buscauuid($uuid,$link){
	$q="show tables like '%_index_relacio'";
	$l=$link->query($q);
	while($f=$l->fetch_row()){
		if($f[0]=="meta_index_relacio")continue;
		$a=explode("_",$f[0]);
		//pre($a);pre($f[0]);
		$taula=$f[0];
		$empresa=$link->query("SELECT * FROM Empresa where `ID`='{$a[2]}'")->fetch_assoc();
		$q="SELECT * FROM $taula where `uuid`='$uuid'";
		$index=mysql2vectorassoc($q,$link);
		if($index['uuid']!=$uuid)continue;
		try{
			$r=detallrelacio($uuid,$empresa['ID'],$a['0'],$link);
		}catch (Exception $e){
			echo "";//ignore error, means print field already exist
		}
		//pre($r);
		$existeixtaula="show tables like '{$a['0']}_Empresa_{$a['2']}_pdf'";
		//echo $existeixtaula;
		$existeixtaula=$link->query($existeixtaula)->fetch_row();
		//pre($existeixtaula);
		if($existeixtaula['0']=="{$a['0']}_Empresa_{$a['2']}_pdf"){
			//echo "{$existeixtaula['0']} existeix";
			$jaexisteixperfer=mysql1r("select uuid from {$a['0']}_Empresa_{$a['2']}_pdf where `uuid`='$uuid' and `pdf_fet`='0' ",$link);
		}
		if($jaexisteixperfer!=$uuid) $fesdenou=true;
		$prefixrelacio="{$a['0']}_Empresa_{$a['2']}_";
		if($fesdenou){
		    echo "Estic fent el document pdf";
		   	if(!correuvalid($r['email']))$r['email']=urldecode($r['email']);	
			pdf($r,$prefixrelacio,true);
		}
	}
	if($r['uuid']!=$uuid)return(FALSE);
	else return($r);
}
function html_relacio($r,$imprimeixpotrecciodades=true) {
	global $uri,$link;
	//if($debug)pre($r);
    $empresa=$r['Empresa'];
    $idrelacio = "{$r['tipus_relacio']['Value']}: {$r['Empresa']['ID']}.{$r['ID']}";
    $data = date("d-m-Y", strtotime($r['data']));
    $nom_client = htmlspecialchars($r['fiscal_name']);
    $direccio = htmlspecialchars($r['fiscal_address']);
    $poblacio = htmlspecialchars($r['Poblacio'] ?? " ");
    $pagat = htmlspecialchars($r['ID_Pagament']);
    $espaidescripcio = 110;
    $ivainclos = ($r['tax_detall'] == 0) ? "IVA inclòs" : "Sense IVA </th><th> IVA";

    // Inici HTML
    $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.$idrelacio.'</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .factura-container { max-width: 800px; margin: auto; padding: 20px; border: 1px solid #000; }
        .factura-header { display: flex; justify-content: space-between; }
        .factura-header div { width: 48%; }
        h1 { text-align: center; font-size: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid black; padding: 8px; text-align: left; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .total { font-size: 18px; font-weight: bold; text-align: right; }
    </style>
</head>
<body>
    <div class="factura-container">
        <div class="factura-header">
            <div>
                <h2>'.$r['Empresa']['Value'].'</h2>
                <p>'.($r['Empresa']['Fiscal_Name']?$r['Empresa']['Fiscal_Name'].'<br/>':'').$r['Empresa']['Direccio'].'<br/>
                '.$r['Empresa']['Poblacio'].'<br/>
                NIF: '.$r['Empresa']['NIF'].'<br/>
                Tel: '.$r['Empresa']['Phone'].'<br/>
                Email: '.$r['Empresa']['email'].'</p>
            </div>
            <div class="right">
                <h1>'.$r['tipus_relacio']['Value'].'</h1>
                <p><strong>Data:</strong> '.$data.'</p>
		<p><strong>'.$r['tipus_relacio']['Value'].' núm.:</strong> '.$r['Empresa']['ID'].'.'.$r['ID'].'</p>
		<p align="left" >'.urldecode($r['fiscal_name']).'<br/>
		NIF: '.urldecode($r['NIF']).'<br/>
		'.urldecode($r['fiscal_address']).'<br/>
		'.urldecode($r['Poblacio']).'<br/>
		'.urldecode($r['phone']).'</p>
		
            </div>
        </div>

        <table>
            <tr>
                <th>Codi</th>
                <th>Descripció</th>
                <th>Lot</th>
                <th>Quantitat</th>
                <th>Preu '.$ivainclos.'</th>
                <th>Import</th>
            </tr>';

    // Afegir línies de productes
    
    foreach ($r['active_lines'] as $v) {
        $lotnumber = !empty($v['lotnumber']) ? htmlspecialchars(urldecode($v['lotnumber'])) : '';
        $html .= '<tr>
            <td class="right">'.htmlspecialchars($v['ID_product']).'</td>
            <td>'.htmlspecialchars($v['urlde']).'</td>
            <td>'.($lotnumber ?: '-').'</td>
            <td class="right">'.number_format($v['units'], 3, ',', '.').' '.$v['u'].'</td>
            <td class="right">'.number_format($v['cost'], 2, ',', '.').'€/'.$v['u'].'</td>';
            if($r['tax_detall']==1)$html.='<td class="right">'.number_format_trim($v['IVA'], 2, ',', '.').'%</td>
											<td class="right">'.number_format_trim($v['Base'], 2, ',', '.').'€</td>';
            else $html.='<td class="right">'.number_format_trim($v['tline'], 2, ',', '.').'€</td>';
			$html.='</tr>';
    }

    // Taula del resum (IVA, total, etc.)
    $html .= '</table>
        <table>
            <tr>
                <th>Base</th>
                <th>% IVA</th>
                <th>IVA</th>
                <th>Total</th>
            </tr>';

if(isset($r['IVA'])){
	$num_iva = count($r['IVA']); // Comptem quants tipus d'IVA hi ha
$comptador = 0; // Comptador d'iteracions

foreach ($r['IVA'] as $iva => $tiva) {
    $base = number_format($r['B'][$iva], 2, ',', '.').'€';
    $fiva = number_format($iva, 2, ',', '.').'%';
    $ftiva = number_format($tiva, 2, ',', '.').'€';

    // Incrementem el comptador
    $comptador++;

    $html .= '<tr>
        <td class="right">'.$base.'</td>
        <td class="right">'.$fiva.'</td>
        <td class="right">'.$ftiva.'</td>';

    // Només imprimim $total si és l'última línia
    if ($comptador == $num_iva) {
        $total = number_format($r['Total'], 2, ',', '.').'€';
        $html .= '<td class="total">'.$total.'</td>';
    } else {
        $html .= '<td></td>'; // Cel·la buida per mantenir l'alineació
    }

    $html .= '</tr>';
}
}

    $html .= '</table>
        <p><strong>Pagat:</strong> '.mysql1r("Select Value from Pagament where `ID`='{$r['ID_Pagament']}'",$link).'</p>
    </div>';
if($imprimeixpotrecciodades){
	$html.=
    "
Moltes gràcies per confiar amb nosaltres
Podeu trobar la copia en pdf d'aquest document a ";
$port=$_SERVER['SERVER_PORT']==80?"http":"https";
global $pdf_public_url;
$html.=ar((!empty($pdf_public_url)?$pdf_public_url:"{$port}://{$_SERVER['HTTP_HOST']}/pdf")."/{$r['uuid']}.pdf");
//$html.=prer($_SERVER);
$html.="
<br/>
L’informem que les seves dades personals, que puguin constar en aquesta comunicació, estan incorporades en un fitxer propietat de {$empresa['Value']}, amb la finalitat d'enviar-li comunicacions de caire comercial o de prestació de serveis. Si desitja exercitar els drets d'accés, rectificació, cancel·lació o oposició pot dirigir-se per escrit a la següent direcció: {$empresa['Direccio']} {$empresa['Poblacio']}
<br/>
En el cas que no desitgi rebre més comunicacions a través del correu electrònic, pot enviar un missatge a la següent adreça electrònica: {$empresa['email']}<br/>
<small>

Advertència:
No respongeu aquest correu, no el llegim. Si convé escriviu a  {$empresa['email']}<br/>

La informació inclosa en aquest e-mail és CONFIDENCIAL, essent per a ús exclusiu del destinatari a dalt esmentat. Si vostè llegeix aquest missatge i no és el destinatari indicat, li informem que està totalment prohibida qualsevol utilització, divulgació, distribució i/o reproducció d’aquesta comunicació, total o parcial, sense autorització expressa en virtut de la legislació vigent. Si ha rebut aquest missatge per error, li preguem que ens ho notifiqui immediatament per aquesta via i procedeixi a la seva eliminació juntament amb els fitxers annexes sense llegir-lo, ni difondre, ni emmagatzemar o copiar el seu contingut.<br/>

Advertencia:
No responga a este correo, no lo leemos. Si hace falta escriba a {$empresa['email']}<br/>
La Información incluida en este e-mail es CONFIDENCIAL, siendo para uso exclusivo del destinatario arriba mencionado. Si Ud. lee este mensaje y no es el destinatario indicado, le informamos que está totalmente prohibida cualquier utilización, divulgación, distribución y/o reproducción de esta comunicación, total o parcial, sin autorización expresa en virtud de la legislación vigente. Si ha recibido este mensaje por error, le rogamos nos lo notifique inmediatamente por esta vía y proceda a su eliminación junto con sus ficheros anexos sin leerlo, ni difundir, ni almacenar o copiar su contenido.
</small>
";
}
$html.='
</body>
</html>';

    return $html;
}
?>
