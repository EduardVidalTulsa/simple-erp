<?php
require_once(__DIR__."/magic.php"); // parse_magic_code(), comú amb relacions/
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
function whatsapp($missatge, $telefon_o_grup = '') {
    // ordre configurada a keys.php ($whatsapp_cmd), p. ex. 'npx mudslide send {to} {msg}'
    global $whatsapp_cmd;
    if (empty($whatsapp_cmd) || $telefon_o_grup == '') return ['output' => [], 'return_code' => -1];
    $comanda = strtr($whatsapp_cmd, ['{to}' => escapeshellarg($telefon_o_grup), '{msg}' => escapeshellarg($missatge)]);

    // Executa la comanda
    exec($comanda, $out, $ret);

    // Torna un array amb sortida i codi de retorn
    return [
        'output' => $out,
        'return_code' => $ret
    ];
}
function sms_mikrotik($missatge, $telefon){
	// enviar un SMS amb l'ordre configurada a keys.php ($sms_cmd), p. ex. per un router MikroTik amb LTE
	global $sms_cmd;
	if (empty($sms_cmd)) return;
	exec(strtr($sms_cmd, ['{to}' => escapeshellarg($telefon), '{msg}' => escapeshellarg($missatge)]));
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
		else return 0;
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
		$tlinea=round($f['units']*$f['preu'],2);
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
			if($index_relacio['equivalency_charge']==1)
            			$index_relacio['EC'][$f['EQUIVALENCY_CHARGE']]=$index_relacio['EC'][$f['EQUIVALENCY_CHARGE']]+$f['Base'];
			$index_relacio['Base']=$index_relacio['Base']+$f['Base'];
			$index_relacio['Total']=$index_relacio['Total']+$f['Base']+$f['tiva']+$f['tec'];
			$index_relacio['IVA'][$f['IVA']]=$index_relacio['IVA'][$f['IVA']]+$f['tiva'];
			$index_relacio['B'][$f['IVA']]=$index_relacio['B'][$f['IVA']]+$f['Base'];
			$index_relacio['T'][$f['IVA']]=$index_relacio['T'][$f['IVA']]+$f['tline']+$f['tec'];
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
function rel2rel($r,$link){

	//parteixo que $r ja es una relació amb tot detall per tant hauria de ser privat de deetall_reklació
	$year= substr($r['data'] ?? '', 0, 4);
	
	if (($link->query("SHOW TABLES LIKE '{$year}_Empresa_{$r['Empresa']['ID']}_rel2rel'"))->num_rows===0){
		return $r;
	}
	$sql="select * from `{$year}_Empresa_{$r['Empresa']['ID']}_rel2rel` where `ID`='{$r['ID']}' and `ID_tipus_relacio`='{$r['ID_tipus_relacio']}'";
	$r2r=$link->query($sql);
	if($r2r->num_rows>0){
		while ($r2=$r2r->fetch_assoc()){
			$suuid="SELECT `uuid` FROM `{$year}_Empresa_{$r['Empresa']['ID']}_index_relacio` WHERE `ID`='{$r2['ID_inclosa']}' and `ID_tipus_relacio`='{$r2['ID_tipus_relacio_inclosa']}' ";
			$uuid=$link->query($suuid)->fetch_assoc()['uuid'];
			$rinc=detallrelacio($uuid,$r['Empresa']['ID'],$year,$link);
			$r['rel2rel'][]=$rinc;
			$dt=new DateTime($rinc['data']);
			$r['active_lines'][$r['activelines']]['urlde']=($rinc['tipus_relacio']['Value']." {$rinc['Empresa']['ID']}.{$rinc['ID']} amb data ".$dt->format("d/m/Y"));
			$r['active_lines'][$r['activelines']]['hidden_now']=$dt->format("Y-m-d");
			$r['activelines']++;
			
			foreach ($rinc['active_lines'] as $l){
				$r['active_lines'][$r['activelines']]=$l;
				$r['activelines']++;
			}
			$r['Base']=$r['Base']+$rinc['Base'];
			$r['Total']=$r['Total']+$rinc['Total'];
			$r['Cost']=$r['Cost']+$rinc['Cost'];
			$faig=Array ('IVA','B','T','C','EC');
			foreach ($faig as $f)foreach ($rinc[$f] as $k=> $suma)$r[$f][$k]=$r[$f][$k]+$suma;
		}
	}
	//pre($r);
	return $r;

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
        foreach ($index_relacio['T'] as $k=>$v){
            $nou=round($v*(1-($index_relacio['discount']/100)),2);
            $index_relacio['T'][$k]=$nou;
            $index_relacio['Total']=$nou+$index_relacio['Total'];
            $index_relacio['IVA'][$k]=$nou-round($nou/(1+($k/100)),2);
            $index_relacio['B'][$k]=$nou-$index_relacio['IVA'][$k];
            $index_relacio['Base']=$index_relacio['Base']+$index_relacio['B'][$k];
        }
    }
    else{
        $index_relacio['Total']=0;
        $index_relacio['Base']=0;
        //fer el descompte per cada b[iva]
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
    if(is_array($index_relacio['active_lines']))sort($index_relacio['active_lines']);//força el vector de active_lines a començar per 0
    if(is_array($index_relacio['deleted_lines']))sort($index_relacio['deleted_lines']);//força el vector de active_lines a començar per 0
    $index_relacio['tipus_relacio']=$link->query("SELECT * from tipus_relacio where
    ID='{$index_relacio['ID_tipus_relacio']}'")->fetch_assoc();
    $index_relacio=rel2rel($index_relacio,$link);
    return $index_relacio;
}
/**
 * Tanca una relació oberta com a cobrada amb la via de pagament $via (taula Pagament).
 * Si no és una factura (3) ni bàscula heredada (9), la passa al tipus de relació de la via
 * de pagament (factura, o tiquet/proforma per a les empreses amb mxgestio) amb un ID nou.
 * La fan servir total.php i el cobrament amb el QR del TPV (tiquettpv.php).
 * Retorna $r actualitzada (ID, ID_tipus_relacio, tipus_relacio, ID_Pagament) o false si falla.
 */
function tanca_relacio_pagament($r,$via,$link,$prefixrelacio,$ID_Empresa){
	global $tanca_relacio_error;
	$tanca_relacio_error='';
	$via=(int)$via;
	$qupdate_lines='';
	if(($r['ID_tipus_relacio']!=3)and($r['ID_tipus_relacio']!=9)and($r['relacio_oberta']==1)){//assignem el tipus de relació depenent de quina sigui la forma de "pagament"
		// si és una factura no es pot canviar de forma de tipus de relació
		//es pot triar entre factura (si paguen)o altres tipus relacions proforma, albarà, pressupost...
		//possiblement si hi ha molta i molta gent treballant, potser faria falta bloquejar temporalment index_relacio
		//per que no assignin el mateix ID a la vegada.
		$NEW_tipus_relacio=mysql1r("select ID_tipus_relacio from Pagament where ID='$via'",$link);
		$ID_Empresa_a_mxgestio=mysql1r("SELECT `ID_Empresa_a_mxgestio` FROM `Empresa` WHERE `ID` = '$ID_Empresa'",$link);
		if(($ID_Empresa_a_mxgestio=='1')&&($NEW_tipus_relacio=='3')&&($r['phone']==""))$NEW_tipus_relacio=10;//si es un tiquet, sera tiquet
		if(($ID_Empresa_a_mxgestio=='1')&&($NEW_tipus_relacio=='3')&&($r['phone']!=""))$NEW_tipus_relacio=4; //si es un cient connegut, vodra factura proforma
		$link->query("lock tables `{$prefixrelacio}index_relacio` write ,`{$prefixrelacio}line`   write ");
		$NEWID=mysql1r("select COALESCE(MAX(ID), 0)+1 from `{$prefixrelacio}index_relacio` where ID_tipus_relacio='$NEW_tipus_relacio' ",$link);
		$qupdate_lines="UPDATE `{$prefixrelacio}line` SET`ID` = '$NEWID',`ID_tipus_relacio` = '$NEW_tipus_relacio' WHERE `ID`='{$r['ID']}' AND `ID_tipus_relacio` = '{$r['ID_tipus_relacio']}' ";
		//modifico els preus a les comandes per que no surtin valorades
		if($NEW_tipus_relacio=='7')$qupdate_lines="UPDATE `{$prefixrelacio}line` SET`ID` = '$NEWID',`ID_tipus_relacio` = '$NEW_tipus_relacio', `cost` = '0' WHERE `ID`='{$r['ID']}' AND `ID_tipus_relacio` = '{$r['ID_tipus_relacio']}'  ";
	}
	else {
		$link->query("lock tables `{$prefixrelacio}index_relacio` write ,`{$prefixrelacio}line`   write ");
		$NEW_tipus_relacio=$r['ID_tipus_relacio'];
		$NEWID=$r['ID'];
	}
	$qtanca="UPDATE `{$prefixrelacio}index_relacio` SET `relacio_oberta` = '0', `ID_Pagament`='$via', `ID`='$NEWID',`ID_tipus_relacio` ='$NEW_tipus_relacio'
		WHERE `ID`='{$r['ID']}' AND `ID_tipus_relacio` = '{$r['ID_tipus_relacio']}' ";
	if(!$link->query($qtanca)){
		$tanca_relacio_error=mysqli_error($link)." no l'he trobada $qtanca";
		$link->query("unlock tables");
		return false;
	}
	if($qupdate_lines!="")if(!$link->query($qupdate_lines)){
		$tanca_relacio_error=mysqli_error($link)." error assignant línees";
		$link->query("unlock tables");
		return false;
	}
	$link->query("unlock tables");
	$r['ID_Pagament']=$via;
	$r['relacio_oberta']=0;
	$r['ID_tipus_relacio']=$NEW_tipus_relacio;
	$r['ID']=$NEWID;
	$r['tipus_relacio']['ID']=$NEW_tipus_relacio;
	$r['tipus_relacio']['Value']=mysql1r("SELECT Value from tipus_relacio where ID='$NEW_tipus_relacio'",$link);
	return $r;
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
    $base='\raggedleft{'.number_format_trim($r['B'][$ultimiva],2,$decimalsing,$thousandsing)."€}";
    $iva='\raggedleft{'.number_format_trim($ultimiva,2,$decimalsing,$thousandsing).'\%}';
    $tiva='\raggedleft{'.number_format_trim($r['IVA'][$ultimiva],2,$decimalsing,$thousandsing)."€}";
    $total='\raggedleft{'.number_format_trim($r['Total'],2,$decimalsing,$thousandsing)."€}";
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
$email_display = !empty($r['Empresa']['email_public']) ? $r['Empresa']['email_public'] : $r['Empresa']['email'];
$tex.=$r['Empresa']['Direccio'].'\\\\
'.$r['Empresa']['Poblacio'].'\\\\
NIF '.$r['Empresa']['NIF'].'\\\\
Tel '.$r['Empresa']['Phone'].'\\\\
\href{mailto:'.$email_display.'}{'.$email_display.'}\\\\
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
    $tex.=str_replace('#','\#',str_replace('%','\%','\footnotesize\raggedleft{'.(!empty($v['ID_product'])||$v['ID_product']=='0' ?$v['ID_product']:' ').'}&'.str_replace('&','\&',$v['urlde'])));
    if($v['lotnumber']!='')$tex.=str_replace('$','\$',str_replace('#','\#',str_replace('&','\&',str_replace('%','\%'," Lot: ".urldecode($v['lotnumber'])))));
    $tex.="&";
    if($v['bulk']===0)$tex.=( !empty($v['units'])?number_format_trim($v['units'],3,$decimalsing,$thousandsing):' ');
    else $tex.=(!empty($v['units'])?number_format($v['units'],3,$decimalsing,$thousandsing):' ');
    $tex.=(!empty($v['u'])?$v['u']:' ')."&".( !empty($v['cost'])?number_format($v['cost'],2,$decimalsing,$thousandsing)."€/{$v['u']}":' ');
    if($v['discount']>0)$tex.=' -'.number_format_trim($v['discount'],2,$decimalsing,$thousandsing).'\% ';
    if($r['tax_detall']==1){
        $tex.=( !empty($v['IVA'])?'&\footnotesize'.number_format_trim($v['IVA'],2,$decimalsing,$thousandsing).'\% ':'& ');
        $tex.=( !empty($v['Base'])?"&".number_format($v['Base'],2,$decimalsing,$thousandsing)."€".'\\\\':'& \\\\ ');
    }
    else $tex.="&".( !empty($v['tline'])?number_format($v['tline'],2,$decimalsing,$thousandsing)."€":' ').'\\\\';
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
function nextColumn(string $col): string {
    $n = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($col);
    return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n + 1);
}
function llistarelacions($e,$my,$do="llista1a"){
    //listo relacions
    global $w,$h,$decimalsing,$thousandsing, $scale_uri;
    if($_SESSION['format']==="pdf")$unite="pdfunite ";
    $prefixrelacio="{$_REQUEST['anyinici']}_Empresa_{$e['ID']}_";
    if(!mysql1r("SHOW TABLES LIKE '{$prefixrelacio}index_relacio' ;",$my)){
        echo "<div/><div> no hi ha relacions per aquesta empresa aquest any a la base de dades";
        return(0);
    }
    if (isset($_REQUEST['ID_Pagament']) && $_REQUEST['ID_Pagament']!=='' && $_REQUEST['ID_Pagament']>-1)$and_tipus_pagament="and `ID_Pagament`='{$_REQUEST['ID_Pagament']}'";
    if ($_REQUEST['ID_client']>-1)$and_client="and `ID_client`='{$_REQUEST['ID_client']}'";
    //todo  beta
    if ($_REQUEST['ID_client']>0)$client_arxiu="_{$_REQUEST['ID_client']}";
    $data_arxiu="_{$_REQUEST['mesinici']}-{$_REQUEST['diainici']}_to_{$_REQUEST['mesfi']}-{$_REQUEST['diafi']}";
    if ($_REQUEST['ID_client']==-2)$and_client="and `ID_client`>'1'";
    //fi todo beta
    $q="SELECT * FROM {$prefixrelacio}index_relacio where ( `data` between '{$_REQUEST['anyinici']}/{$_REQUEST['mesinici']}/{$_REQUEST['diainici']}' AND '{$_REQUEST['anyinici']}/{$_REQUEST['mesfi']}/{$_REQUEST['diafi']}') AND `ID_tipus_relacio`='{$_REQUEST['ID_tipus_relacio']}' $and_tipus_pagament $and_client  ORDER BY `ID` DESC ";
    //debugtelegram($q);
    //if($do==="rel2rel")pre($q);
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
        //$l=$my->query($q) or die( $q.$my->error);
        $l=$my->query($q) or die( $q.$my->error);
        $existeixpdf=true;
        if($l->num_rows>=1){
            //echo $q;
            while($f=$l->fetch_assoc()){
                unset($r);
                $r=detallrelacio($f['uuid'],$e['ID'],$_REQUEST['anyinici'],$my);
                $trelacions[]=$r;
                $nrels++;
                if ((!in_array($r['ID_client'],$_SESSION['llistaclients']['ID']))&&($do==='llista1a')){
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
                //debugtelegram($r);
                $t['cost']=$r['Total']+$t['cost'];
                foreach ($r['B'] as $IVA => $base){
                    $t['B'][$IVA]=$base+$t['B'][$IVA];
                    $t['IVA'][$IVA]=$t['IVA'][$IVA]+$r['IVA'][$IVA];
                }
                foreach ($r['EC'] as $EC=>$value){
                    $t['EC'][$EC]=$t['EC'][$EC]+$value;
                }
                $t['cost_price']=$r['Cost']+$t['cost_price'];//debugtelegram($t,"t");
                $tipus_index = ($_REQUEST['ID_tipus_relacio'] ?? 0) - 1;
                if(isset($_SESSION['llista']['tipus_relacio'][1][$tipus_index])){
                    $tipus_key = $_SESSION['llista']['tipus_relacio'][1][$tipus_index];
                    $t[$tipus_key] = ($t[$tipus_key] ?? 0) + 1;
                }
                if ($_SESSION['format']=="pdf"){

                    $unite.=" ../pdf/{$r['uuid']}.pdf";
                    //$actual=file_exists("../pdf/{$r['uuid']}");
                    if(!file_exists("../pdf/{$r['uuid']}.pdf")){
                        if($r['ID_client']!=0)pdf($r,$prefixrelacio,true);
                        $existeixpdf=false;
                    }
                }
            }
            if(is_array($_SESSION['llistaclients'])){
                global $fontsize,$height;
                if($do==="llista1a")select2llistes_js("ID_client",$_SESSION['llistaclients']['ID'],$_SESSION['llistaclients']['fiscal_name'],
                    $_REQUEST['ID_client']?$_REQUEST['ID_client']:0,"no",false,
                    "style=\"vertical-align:unset; font-size: {$fontsize}px;  text-align: left;  height:{$height}px;\"");
            }
            if($do==="llista1a")	chek_js("resum",$checked=($_REQUEST['resum']?1:0),$valor="yes");

            tdiv();

            if($do==="llista1a")tform();

            divclass("caixa","style =\"background:#ACAB00; position: relative;top:3px; text-align: center;height: unset;width:unset; padding:1%;display: inline-block; \"");
            if($_REQUEST['resum']){
                divclass("caixadescripcio","style =\"background:#ACAB00;text-align: justify; position: relative; top: 5px; display: inline-block; left:0px; padding:1%; width:98%; font-size:30px;height: unset;\"");//width:".($w<400?($w):350)."px;

                echo "En aquest periode les {$nrels} relacions \"{$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]}\" estan valorades en ".number_format_trim($t['cost'],2,$decimalsing,$thousandsing).'€'." i el cost de compra ha sigut (aprox) de {$t['cost_price']} deixant un marge brut de ".(is_numeric($t['cost_price'])?round($t['cost']-$t['cost_price'],2):"NaN")."€ <br/>" ;
                foreach ($t['B'] as $IVA=>$base){
                    echo "IVA {$IVA}%: Base={$base}€, €IVA={$t['IVA'][$IVA]} <br/>";
                }
                foreach ($t['EC'] as $EC=>$valor){
                    echo "Carreg eq: {$EC}={$valor}€ <br/>";
                }
            }
            else {
                divclass("caixadescripcio","style =\"background:#ACAB00;text-align: justify; position: relative; top: 5px; display: inline-block; left:0px; padding:1%; width:98%; font-size:30px;height: unset;\"");//width:".($w<400?($w):350)."px;
                echo "En aquest periode les {$l->num_rows} relacions \"{$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]}\" estan valorades en ".number_format_trim($t['cost'],2,$decimalsing,$thousandsing).'€<br/>';
                foreach ($t['B'] as $IVA=>$base){
                    echo "IVA {$IVA}%: Base={$base}€, €IVA={$t['IVA'][$IVA]} <br/>";
                }
                foreach ($t['EC'] as $EC=>$valor){
                    echo "recàrreg eq: {$EC}={$valor}€ <br/>";
                }

            }
            //todo beta
            if ($_SESSION['format']=="spreadsheet"){ ainf("{$prefixrelacio}{$client_arxiu}{$data_arxiu}.ods","💾"); echo"espera que la plana acabi!";} tdiv();
            //fi todo beta
            unset($r);
            //ksort($trelacions);
            //ksort($_SESSION['llistaclients']['ID']);
            //ksort($_SESSION['llistaclients']['fiscal_name']);
            //debugtelegram($_SESSION['llistaclients']);
            $trelacions=array_reverse($trelacions);//?supsoo per que comenci a treballar amb la primera?
            //pre($trelacions[0]);
            if ($_SESSION['format']=="spreadsheet"){
                require_once '../vendor/autoload.php';
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                //\PhpOffice\PhpSpreadsheet\Settings::setLocale(\PhpOffice\PhpSpreadsheet\Locale::ca-AD);
                $spreadsheet->getProperties()
                ->setTitle("{$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]} de '{$_REQUEST['anyinici']}/{$_REQUEST['mesinici']}/{$_REQUEST['diainici']}' AND '{$_REQUEST['anyinici']}/{$_REQUEST['mesfi']}/{$_REQUEST['diafi']}  ")
                ->setSubject("Aquestes son els/les {$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]} de l'empresa {$trelacions[0]['Empresa']['Value']} i també una fulla que conté totes les línees d'aquestes ")
                ->setDescription('Arxiu generat automàgicament al servidor '.gethostname().' el @'.date("Y-m-d H:i:s"));
                $spreadsheet->setActiveSheetIndex(0);
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle("{$l->num_rows} {$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]}");
                $sheet->getColumnDimension('A')->setAutoSize(true);
                $sheet->getColumnDimension('B')->setAutoSize(true);
                $sheet->getColumnDimension('C')->setAutoSize(true);
                $sheet->getColumnDimension('D')->setAutoSize(true);
                $sheet->getColumnDimension('E')->setAutoSize(true);
                $sheet->getColumnDimension('F')->setAutoSize(true);
                $sheet->getColumnDimension('G')->setAutoSize(true);
                $sheet->getColumnDimension('H')->setAutoSize(true);
                $sheet->getColumnDimension('I')->setAutoSize(true);
                $sheet->getColumnDimension('J')->setAutoSize(true);
                $sheet->getColumnDimension('K')->setAutoSize(true);
                $spreadsheet->getActiveSheet()->setCellValue('A1',"Codi");
                $spreadsheet->getActiveSheet()->setCellValue('B1',"Unitats");
                $spreadsheet->getActiveSheet()->setCellValue('C1',"Tipus unitari");
                $spreadsheet->getActiveSheet()->setCellValue('D1',"Preu");
                $spreadsheet->getActiveSheet()->setCellValue('E1',"Lot");
                $spreadsheet->getActiveSheet()->setCellValue('F1',"Descripcio");
                $spreadsheet->getActiveSheet()->setCellValue('G1',$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]);
                $i0=2;
                $spreadsheet->createSheet()->setTitle("Resum contable de  {$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]}");
                $spreadsheet->setActiveSheetIndex(1);

                $sheet->getColumnDimension('A')->setWidth(10);//codi relació
                $spreadsheet->getActiveSheet()->setCellValue('A1',"Codi {$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]}");

                $sheet->getColumnDimension('B')->setAutoSize(true);//Nom fiscal client
                $spreadsheet->getActiveSheet()->setCellValue('B1',"Nom fiscal client");
                $sheet->getColumnDimension('C')->setAutoSize(true);//NIF  client
                $spreadsheet->getActiveSheet()->setCellValue('C1',"NIF client");
                $sheet->getColumnDimension('D')->setAutoSize(true);//data
                $spreadsheet->getActiveSheet()->setCellValue('D1',"Data");
                $sheet->getColumnDimension('E')->setAutoSize(true);//BASE
                $sheet->getColumnDimension('F')->setAutoSize(true);//TOTAL IVA INCLÓS
                $spreadsheet->getActiveSheet()->setCellValue('E1',"Base");
                $spreadsheet->getActiveSheet()->setCellValue('F1',"Total IVA inclós");
                $columna="F";
                foreach ($t['B'] as $iva=>$base){
                    $columna=nextColumn($columna);
                    $spreadsheet->getActiveSheet()->setCellValue($columna.'1',"Base {$iva}%");
                    $sheet->getColumnDimension($columna)->setAutoSize(true);//TOTAL IVA INCLÓS
                    $columna=nextColumn($columna);
                    $spreadsheet->getActiveSheet()->setCellValue($columna.'1',"IVA {$iva}%");
                    $sheet->getColumnDimension($columna)->setAutoSize(true);//TOTAL IVA INCLÓS
                }
            }
            foreach ($trelacions as $k=>$r){
                if ($_SESSION['format']=="spreadsheet"){
                    $spreadsheet->setActiveSheetIndex(1);//la fulla 1 és la fulla resum
                    $sheet = $spreadsheet->getActiveSheet();
                    $li=$k+2; //Per què escric a la linea numero de relació i funciona? sí ja que és la fulla 2, on hi ha el resum
                    $spreadsheet->getActiveSheet()->setCellValue("A{$li}","{$r['Empresa']['ID']}.{$r['ID']} ");
                    $spreadsheet->getActiveSheet()->setCellValue("B{$li}",urldecode("{$r['fiscal_name']}"));
                    $spreadsheet->getActiveSheet()->setCellValue("C{$li}","{$r['NIF']} ");
                    $spreadsheet->getActiveSheet()->setCellValue("D{$li}",$r['data']);
                    $spreadsheet->getActiveSheet()->setCellValue("E{$li}",$r['Base']);
                    $spreadsheet->getActiveSheet()->setCellValue("F{$li}",$r['Total']);
                    $columna="F";
                    foreach ($r['B'] as $iva=>$base){
                        $columna=nextColumn($columna);
                        $spreadsheet->getActiveSheet()->setCellValue($columna.$li,$base);
                        $sheet->getColumnDimension($columna)->setAutoSize(true);
                        $columna=nextColumn($columna);
                        $spreadsheet->getActiveSheet()->setCellValue($columna.$li,$r['IVA'][$iva]);
                        $sheet->getColumnDimension($columna)->setAutoSize(true);//TOTAL IVA INCLÓS
                    }
                }
                $n=2;//número de línea on començo a escriure les línees
                //pre("soc la $k {$r['ID']}");
                //if($_REQUEST['mesinici']==$_REQUEST['mesfi']){
                //todo beta
                //if ($_SESSION['format']=="spreadsheet")if($nrels<100){//creo fitxa per cada una de les 99 relacions
                if ($_SESSION['format']=="spreadsheet")if($k<201){//creo fitxa per cada una de les primeres 99 relacions

                    //fi todo beta
                    $spreadsheet->createSheet()->setTitle("{$r['tipus_relacio']['Value']} {$r['Empresa']['ID']}.{$r['ID']} ");
                    $spreadsheet->setActiveSheetIndex($k+2);//passo a 3 abans era +2, intento arreglar el que surt
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
                    $spreadsheet->getActiveSheet()->setCellValue('G8',"{$r['Base']}");
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
                    // Detectar si és una línia informativa de rel2rel (només té urlde, no té description ni ID_product normal)
                    $es_linea_informativa = (isset($linea['urlde']) && !isset($linea['description']));
                    
                    //todo beta
                    //if($_REQUEST['mesinici']==$_REQUEST['mesfi']){
                    if ($_SESSION['format']=="spreadsheet")if($k<201){//creo fitxa per cada una de les primeres 99 relacions
                        //if ($_SESSION['format']=="spreadsheet")if($nrels<100){
                        //fi todo beta
                        $spreadsheet->setActiveSheetIndex($k+2);//0 linees totals, 1, resum, $k(0)+2 primera relació
                        
                        if($es_linea_informativa){
                            // Línia informativa: només descripció
                            $spreadsheet->getActiveSheet()->setCellValue('A'.$n,'');
                            $spreadsheet->getActiveSheet()->setCellValue('B'.$n,'');
                            $spreadsheet->getActiveSheet()->setCellValue('C'.$n,'');
                            $spreadsheet->getActiveSheet()->setCellValue('D'.$n,'');
                            $spreadsheet->getActiveSheet()->setCellValue('E'.$n,'');
                            $spreadsheet->getActiveSheet()->setCellValue('F'.$n,urldecode($linea['urlde']));
                        } else {
                            // Línia normal
                            $spreadsheet->getActiveSheet()->setCellValue('A'.$n,$linea['ID_product']);
                            $spreadsheet->getActiveSheet()->setCellValue('B'.$n,$linea['units']);
                            $spreadsheet->getActiveSheet()->setCellValue('C'.$n,$linea['bulk']==1?"Kg":"Units");
                            $spreadsheet->getActiveSheet()->setCellValue('D'.$n,number_format_trim($linea['cost'],2));
                            $spreadsheet->getActiveSheet()->setCellValue('E'.$n,$linea['IVA']);
                            $spreadsheet->getActiveSheet()->setCellValue('F'.$n,urldecode($linea['description']));
                        }
                        $spreadsheet->setActiveSheetIndex(0);
                    }
                    if ($_SESSION['format']=="spreadsheet"){
                        $spreadsheet->setActiveSheetIndex(0);
                        //debugtelegram($spreadsheet->getActiveSheet(),"estic al 0?");
                        
                        if($es_linea_informativa){
                            // Línia informativa a la fulla principal: només descripció
                            $spreadsheet->getActiveSheet()->setCellValue('A'.$i0,'');
                            $spreadsheet->getActiveSheet()->setCellValue('B'.$i0,'');
                            $spreadsheet->getActiveSheet()->setCellValue('C'.$i0,'');
                            $spreadsheet->getActiveSheet()->setCellValue('D'.$i0,'');
                            $spreadsheet->getActiveSheet()->setCellValue('E'.$i0,'');
                            $spreadsheet->getActiveSheet()->setCellValue('F'.$i0,urldecode($linea['urlde']));
                            $spreadsheet->getActiveSheet()->setCellValue('G'.$i0,"{$r['Empresa']['ID']}.{$r['ID']} ".urldecode("{$r['fiscal_name']}"));
                        } else {
                            // Línia normal
                            $spreadsheet->getActiveSheet()->setCellValue('A'.$i0,$linea['ID_product']);
                            $spreadsheet->getActiveSheet()->setCellValue('B'.$i0,$linea['units']);
                            $spreadsheet->getActiveSheet()->setCellValue('C'.$i0,$linea['bulk']==1?"Kg":"Units");
                            $spreadsheet->getActiveSheet()->setCellValue('D'.$i0,number_format_trim($linea['cost'],2));
                            $spreadsheet->getActiveSheet()->setCellValue('E'.$i0,$linea['lotnumber']);
                            $spreadsheet->getActiveSheet()->setCellValue('F'.$i0,urldecode($linea['description']));
                            $spreadsheet->getActiveSheet()->setCellValue('G'.$i0,"{$r['Empresa']['ID']}.{$r['ID']} ".urldecode("{$r['fiscal_name']}"));
                        }
                    }
                    $n++;
                    $i0++;
                    
                    // No afegir línees informatives al resum
                    if(!$es_linea_informativa){
                        $desc_key = ($linea['description'] ?? '') . "";
                        $cost_key = number_format_trim($linea['cost'],2);
                        $resum[$desc_key][$cost_key]['ID_product']=$linea['ID_product'];
                        $resum[$desc_key][$cost_key]['units']=($resum[$desc_key][$cost_key]['units'] ?? 0)+$linea['units'];
                        $resum[$desc_key][$cost_key]['bulk']=$linea['bulk'];
                        $resum[$desc_key][$cost_key]['description']=urldecod(($linea['description'] ?? '') . '');
                        $resum[$desc_key][$cost_key]['cost']=number_format_trim($linea['cost'],2);
                    }
                }
                //}//if ($_SESSION['format']=="spreadsheet")
                //tdiv();
                //divclass("caixadescripcio","style =\"background:#ACAB00; position:relative; top:110px; left:5px; width:".($w)."px; height:815px; font-size:33px;padding:1%;\"");
                divclass("caixadescripcio","style =\"background:#ACAB01; text-align: center; position: relative; top: 5px; display: inline-block; left:0px; width:".($w<400?($w):350)."px;".($r['deletedlines']>1?" color:red;":"")." font-size:22px;height: unset;\"");//width:".($w<420?($w):420)."px;
                //echo $k;
                $rel2rel="SELECT * FROM `{$prefixrelacio}rel2rel` WHERE `ID`='{$r['ID']}' AND `ID_tipus_relacio`='{$r['ID_tipus_relacio']}'  limit 0,1";
                if (($my->query("SHOW TABLES LIKE '{$prefixrelacio}rel2rel'"))->num_rows===1){
                    $rel2rel=$my->query($rel2rel)->fetch_assoc();
                    if($rel2rel['ID']==$r['ID'])
                        ainf("./?do=rel2rel&ID_client={$r['ID_client']}&ID_tipus_relacio_new={$r['ID_tipus_relacio']}&uuid={$r['uuid']}&ID_Empresa={$_SESSION['rel']['Empresa']['ID']}&year={$_REQUEST['anyinici']}&ID_tipus_relacio={$rel2rel['ID_tipus_relacio_inclosa']}","✍️");
                    else ainf("./?do=edit&uuid={$r['uuid']}&ID_Empresa={$_SESSION['rel']['Empresa']['ID']}&year={$_REQUEST['anyinici']}","✍️");
                }
                else ainf("./?do=edit&uuid={$r['uuid']}&ID_Empresa={$_SESSION['rel']['Empresa']['ID']}&year={$_REQUEST['anyinici']}","✍️");
                ainf("../scale/tiquet.php?uuid={$r['uuid']}&ID_Empresa={$_SESSION['rel']['Empresa']['ID']}&year={$_REQUEST['anyinici']}&close=true","🧾");
                if($r['ID_Pagament']=='1')echo"💶";
                if($r['ID_Pagament']=='2')echo"💳";
                if($r['ID_Pagament']>'2')echo"🏦";
                global $uri,$pdf_public_host;
                if($pdf_public_host) ainf("{$pdf_public_host}/pdf/{$r['uuid']}.pdf?updated=".time(),"🖺");
                    else ainf("../pdf/{$r['uuid']}.pdf?updated=".time(),"🖺");
                    if($r['phone']!=""){
                        if (strlen($r['phone']) == 9 && $r['phone'][0] != '+') $r['phone']="+34".$r['phone'];
                        ainf("tel:{$r['phone']}","📲");
                        if($pdf_public_host)$body=urlencode("{$pdf_public_host}/pdf/{$r['uuid']}.pdf");
                            else $body=urlencode("{$uri}/pdf/{$r['uuid']}.pdf");//.html_relacio($r));
                            //$mailbody=rawurlencode(html_relacio($r));
                            //debugtelegram($r,'relacio a mati','302887425');
                            $subject=str_replace(" ","%20","Enviem enllaç a {$r['tipus_relacio']['Value']} de la data {$r['data']} i Valor {$r['Total']}€ ");
                            if ($pdf_public_host)ainf("sms:{$r['phone']}?body=Enviem%20{$r['tipus_relacio']['Value']}%20{$pdf_public_host}/pdf/{$r['uuid']}.pdf ","📟");
                                else ainf("sms:{$r['phone']}?body=Enviem%20{$r['tipus_relacio']['Value']}%20{$uri}/pdf/{$r['uuid']}.pdf ","📟");
                                ainf("https://api.whatsapp.com/send?phone={$r['phone']}&text=$subject$body",imgr("../scale/img/whatsapp.svg","22px","22px"));
                                if(isset($r['email'])){
                                    //ainf("mailto:{$r['email']}?subject=$subject&body=$mailbody",imgr("{$scale_uri}img/thunderbird.svg",'20px','20px'));
                                    ainf("mailto:{$r['email']}?subject=$subject&body=$body","📧");
                                    ainf("./?do=pdf&uuid={$r['uuid']}&ID_Empresa={$_SESSION['rel']['Empresa']['ID']}&year={$_REQUEST['anyinici']}","📨");

                                }
                                br();
                                if($do==="llista1a")	echo urldecode($r['fiscal_name']) . " {$e['ID']}.{$r['ID']}";
                                if($do==="rel2rel"){
                                    $sql="select * from `{$prefixrelacio}rel2rel` where `ID_inclosa`='{$r['ID']}' AND `ID_tipus_relacio_inclosa`='{$r['ID_tipus_relacio']}'";
                                    $r2r=$my->query($sql);//pre($sql);
                                    if($r2r->num_rows==0){
                                        chek_js("relinc",false,$r['ID']);
                                        echo "afegeix {$r['tipus_relacio']['Value']}: {$e['ID']}.{$r['ID']}";
                                    }
                                    else{
                                        echo "{$r['tipus_relacio']['Value']} ja és a dintre";
                                    }
                                }
                                br();
                    }else echo " <br/> {$e['ID']}.{$r['ID']}"; //else del if phone
                    echo "({$r['activelines']}) ({$r['deletedlines']})". number_format($r['Total'],2,$decimalsing,$thousandsing)."€ @";
                    //br();
                    //echo $r['active_lines'][1]['hidden_now'].($r['activelines']>1?"-".substr($r['active_lines'][$r['activelines']]['hidden_now'],11):"");
                    //br();
                    if ($r['activelines']>0)echo substr($r['active_lines'][0]['hidden_now'],5,11);
                    echo ($r['activelines']>1?"-".(strtotime($r['active_lines'][($r['activelines']-1)]['hidden_now'])-strtotime($r['active_lines'][0]['hidden_now']))."s":"");

                //pre($r);
                tdiv();
                //tdiv();
                //tdiv();

            }
            if ($_SESSION['format']=="spreadsheet"){
                //usort($resum,'cmp3d');
                //debugtelegram ($resum);
                //pre($resum);
                /*$objects = array();
                 *				foreach ($data as $description => $prices) {
                 *   						foreach ($prices as $cost => $details) {
                 *      							 $objects[] = array(
                 *       						    'description' => $details['description'],
                 *       						    'cost' => $cost,
                 *       						    'units' => $details['units'],
                 *           						'details' => $details
                 *       						);
            }
            }*/
                // Ordenem l'array d'objectes
                //usort($objects, 'cmp3d');
                usort($resum, 'cmp3d');
                //asort($resum);
                $i0=2;//debugtelegram($resum);
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
                        $sheet->getColumnDimension('K')->setWidth(15);
                        $sheet->getColumnDimension('L')->setWidth(35);
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
    if ($_SESSION['format']=="pdf"){
        //p("$unite...");
        //p("espera 50 segons!");
        //sleep (50);
        $length=substr(str_shuffle("123456789"),0,2);
        for ($i=0;$i<6;$i++)$random.=str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ");
        $newkey=substr($random, 0, $length);
        $sortida="{$newkey}.{$_SESSION['llista']['tipus_relacio'][1][($_REQUEST['ID_tipus_relacio']-1)]}_client_{$_REQUEST['client']}_.pdf";
        if($existeixpdf){
            exec("$unite ../pdf/{$sortida}");
            echo ar("../pdf/{$sortida}","factures unides?","_new");
        }
        if($existeixpdf==false){p("encara no tenim els pdf per fer l'agregació prem f5");}//echo $unite;
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
// Obre una caixa de la pantalla de pesar (pes, caixapreu, caixatlinea, caixadescripcio,
// Descripcio) amb l'estil segons si la pantalla és vertical ($w<$h) o horitzontal.
function caixa_tlinea($nom){
	global $w,$h;
	if($w<$h){
		if($nom=='Descripcio')divclass('Descripcio');
		else divclass($nom,"style =width:".($w-25)."px;");
		return;
	}
	switch($nom){
		case 'pes':
			divclass('pes',"style = height:".($h/5)."px;top:2px;width:".(($w/2)-15)."px;");break;
		case 'caixapreu':
			divclass('caixapreu',"style =height:".($h/5)."px;top:".(($h/5)+2)."px;width:".(($w/2)-15)."px;");break;
		case 'caixatlinea':
			divclass('caixatlinea',"style =height:".($h/5)."px;top:".(2*(($h/5)+2))."px;width:".(($w/2)-15)."px;");break;
		case 'caixadescripcio':
			divclass('caixadescripcio',"style =height:".(2*($h/5))."px;top:".(3*(($h/5)+2))."px;width:".(($w/2)-15)."px;");break;
		case 'Descripcio':
			divclass('Descripcio',"style =width:".(($w/2)-5)."px;text-aling:center;");break;
	}
}
function tlineapes($pes,$u="Kg"){
	global $decimalsing,$thousandsing;
	caixa_tlinea('pes');
	echo "{$abono}".number_format(($pes),3,$decimalsing,$thousandsing);
	echo "$u&nbsp;";
	tdiv();
}
function tlineapreu($string){
	caixa_tlinea('caixapreu');
	echo $string.'&nbsp;</div>';
}
function tlineavalor($preu){
	global $decimalsing,$thousandsing;
	caixa_tlinea('caixatlinea');
	echo number_format($preu,2,$decimalsing,$thousandsing).'€&nbsp;';
	tdiv();
}

function tlineadescripcio($string){
	caixa_tlinea('caixadescripcio');
	caixa_tlinea('Descripcio');
	echo $string;
	tdiv();tdiv();
}
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
	global $uri,$link,$pdf_public_host;
	
	// Comprovar si el camp url_public existeix a la taula Empresa
	$check = $link->query("SHOW COLUMNS FROM Empresa LIKE 'url_public'");
	if($check->num_rows == 0){
		// El camp no existeix, el creem
		$link->query("ALTER TABLE Empresa ADD COLUMN url_public VARCHAR(255) DEFAULT NULL");
	}
	
	//if($debug)pre($r);
    $empresa=$r['Empresa'];
    $email_display = !empty($empresa['email_public']) ? $empresa['email_public'] : $empresa['email'];
    $idrelacio = "{$r['tipus_relacio']['Value']}: {$r['Empresa']['ID']}.{$r['ID']}";
    $data = date("d-m-Y", strtotime($r['data'] ?? 'now'));
    $nom_client = htmlspecialchars($r['fiscal_name'] ?? '');
    $direccio = htmlspecialchars($r['fiscal_address'] ?? '');
    $poblacio = htmlspecialchars($r['Poblacio'] ?? " ");
    $pagat = htmlspecialchars($r['ID_Pagament'] ?? '');
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
                Email: '.(!empty($r['Empresa']['email_public']) ? $r['Empresa']['email_public'] : $r['Empresa']['email']).'</p>
            </div>
            <div class="right">
                <h1>'.$r['tipus_relacio']['Value'].'</h1>
                <p><strong>Data:</strong> '.$data.'</p>
		<p><strong>'.$r['tipus_relacio']['Value'].' núm.:</strong> '.$r['Empresa']['ID'].'.'.$r['ID'].'</p>
		<p align="left" >'.urldecode($r['fiscal_name'] ?? '').'<br/>
		NIF: '.urldecode($r['NIF'] ?? '').'<br/>
		'.urldecode($r['fiscal_address'] ?? '').'<br/>
		'.urldecode($r['Poblacio'] ?? '').'<br/>
		'.urldecode($r['phone'] ?? '').'</p>
		
            </div>
        </div>

        <table>
            <tr>
                <th>Codi</th>
                <th>Descripció</th>
                <th>Quantitat</th>
                <th>Preu '.$ivainclos.'</th>
                <th>Import</th>
            </tr>';

    // Afegir línies de productes
    
    foreach ($r['active_lines'] as $v) {
        // Si codi és 0 i unitats també són 0, mostrar només la descripció
        if($v['ID_product'] == 0 && $v['units'] == 0) {
            $html .= '<tr>
            <td class="right">&nbsp;</td>
            <td>'.htmlspecialchars($v['urlde']).'</td>
            <td class="right">&nbsp;</td>
            <td class="right">&nbsp;</td>';
            if($r['tax_detall']==1)$html.='<td class="right">&nbsp;</td>
											<td class="right">&nbsp;</td>';
            else $html.='<td class="right">&nbsp;</td>';
			$html.='</tr>';
            continue;
        }
        
        $html .= '<tr>
            <td class="right">'.($v['ID_product'] > 0 ? htmlspecialchars($v['ID_product']) : '&nbsp;').'</td>
            <td>'.htmlspecialchars($v['urlde']).'</td>
            <td class="right">'.number_format_trim($v['units'], 3, ',', '.').' '.$v['u'].'</td>
            <td class="right">'.number_format_trim($v['cost'], 2, ',', '.').'€/'.$v['u'].'</td>';
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
// Usar url_public si està ple, si no usar l'URL local
if(!empty($empresa['url_public'])){
    $html.=ar("https://{$empresa['url_public']}/pdf/{$r['uuid']}.pdf");
} else {
    $port=$_SERVER['SERVER_PORT']==80?"http":"https";
    if($pdf_public_host)$html.=ar("{$pdf_public_host}/pdf/{$r['uuid']}.pdf");
    else $html.=ar("{$port}://{$_SERVER['HTTP_HOST']}/pdf/{$r['uuid']}.pdf");
}
//$html.=prer($_SERVER);
$html.="
<br/>
L’informem que les seves dades personals, que puguin constar en aquesta comunicació, estan incorporades en un fitxer propietat de {$empresa['Value']}, amb la finalitat d'enviar-li comunicacions de caire comercial o de prestació de serveis. Si desitja exercitar els drets d'accés, rectificació, cancel·lació o oposició pot dirigir-se per escrit a la següent direcció: {$empresa['Direccio']} {$empresa['Poblacio']}
<br/>
En el cas que no desitgi rebre més comunicacions a través del correu electrònic, pot enviar un missatge a la següent adreça electrònica: {$email_display}<br/>
<small>

Advertència:
No respongeu aquest correu, no el llegim. Si convé escriviu a  {$email_display}<br/>

La informació inclosa en aquest e-mail és CONFIDENCIAL, essent per a ús exclusiu del destinatari a dalt esmentat. Si vostè llegeix aquest missatge i no és el destinatari indicat, li informem que està totalment prohibida qualsevol utilització, divulgació, distribució i/o reproducció d’aquesta comunicació, total o parcial, sense autorització expressa en virtut de la legislació vigent. Si ha rebut aquest missatge per error, li preguem que ens ho notifiqui immediatament per aquesta via i procedeixi a la seva eliminació juntament amb els fitxers annexes sense llegir-lo, ni difondre, ni emmagatzemar o copiar el seu contingut.<br/>

Advertencia:
No responga a este correo, no lo leemos. Si hace falta escriba a {$email_display}<br/>
La Información incluida en este e-mail es CONFIDENCIAL, siendo para uso exclusivo del destinatario arriba mencionado. Si Ud. lee este mensaje y no es el destinatario indicado, le informamos que está totalmente prohibida cualquier utilización, divulgación, distribución y/o reproducción de esta comunicación, total o parcial, sin autorización expresa en virtud de la legislación vigente. Si ha recibido este mensaje por error, le rogamos nos lo notifique inmediatamente por esta vía y proceda a su eliminación junto con sus ficheros anexos sin leerlo, ni difundir, ni almacenar o copiar su contenido.
</small>
";
}
$html.='
</body>
</html>';

    return $html;
}

/**
 * ============================================================================
 * FUNCIONS REFACTORITZADES D'INDEX.PHP
 * ============================================================================
 * Aquestes funcions converteixen l'switch gegant d'index.php en codi modular
 */

/**
 * Mostra la pantalla principal de pesatge
 */
function mostrar_pantalla_pesa($w, $h, $prefixrelacio, $link, $ID_Empresa, $scale_uri, $decimalsing, $thousandsing) {
    global $c;
    
    $_SESSION['pes0'] = 0;
    estil_capcal(false);
    form($_SERVER['DOCUMENT_URI'].'#'.$c[0], 'GET');
    hidden("m[estat]", "tracto");
    divclass("caixa");
    
    if($w < $h) {
        divclass("caixacodi", "style=\"width: ".($w-15)."px;\"");
    } else {
        divclass("caixacodi", "style=\"width: ".(($w/2)+10)."px; left:".($w/2)."px\"");
    }
    ?>
    <div id="codi">
        <div style=" display:table-cell; vertical-align:middle; width:<?=$w-15?>px;height:115px; position:relative; top: 8px">
            <input type="search" name="magic" size=8 id="magic" placeholder="Codi, * / + ." class="form-control" autofocus autocomplete="off" onChange="this.form.submit()"/>
        </div>
    </div>
    <?php
    tdiv();
    sumit1('e', "style =\"font-size:1px; position:absolute; left: -50px;top : -10px\"");
    tdiv();
    div("dades");
    tdiv();
    tform();
    
    mostrar_javascript_pesa();
    mostrar_peu_venedors($w, $h, $prefixrelacio, $link, $ID_Empresa, $scale_uri, $decimalsing, $thousandsing);
    mostrar_divs_fixos($w, $h);
}

/**
 * JavaScript per la pantalla de pesatge
 */
function mostrar_javascript_pesa() {
    global $ID_Empresa, $idbascula;
    echo '
    <script src="jquery-3.4.1.min.js"></script>
    <script>
        $(document).ready(function(){
            var ultimtlinea = null;
            function tlinea(ID){
                $.ajax({
                    url:"tlinea.php",
                    method:"POST",
                    data:{"ID":ID,"w":window.innerWidth,"h":window.innerHeight},
                    success:function(data){
                        // només si canvia: si no, la previsualització d\'etiqueta (<img>) es recarregaria cada 300 ms
                        if(data === ultimtlinea) return;
                        ultimtlinea = data;
                        $(dades).html(data);
                    }
                });
            };
            function actualitzarInfo(){
                $.ajax({
                    url:"tlinea_info.php",
                    method:"POST",
                    data:{"w":window.innerWidth,"h":window.innerHeight},
                    success:function(data){
                        var info = JSON.parse(data);
                        $("#ultim_linia").html(info.ultim);
                        $("#info_hora").html(info.hora);
                    }
                });
            };
            // 300 ms: pesa.py llegeix la bàscula cada ~0,8 s, més sovint no aporta res
            setInterval(function() {
                document.getElementById("magic").focus();
                tlinea(document.getElementById("magic").value);
                actualitzarInfo();
            }, 300);
        });
    </script>';
}

/**
 * Mostra el peu amb els venedors actius avui
 */
function mostrar_peu_venedors($w, $h, $prefixrelacio, $link, $ID_Empresa, $scale_uri, $decimalsing, $thousandsing) {
    $FROMindex_relacio = "FROM {$prefixrelacio}index_relacio";
    $qva = "SELECT ID_Venedor $FROMindex_relacio WHERE `data` = CURDATE() OR `relacio_oberta`='1'
            GROUP BY ID_Venedor ORDER BY ID_Venedor LIMIT 8";
    
    $venedors_avui = $link->query($qva);
    
    if($w < $h) {
        $mida_lletra = 56 - (($venedors_avui->num_rows - 2) * 10);
    } else {
        $mida_lletra = 64 - (($venedors_avui->num_rows - 3) * 5);
    }
    
    if($w < $h) {
        $scaixa = "style =\"position:absolute;background-color:#ACAB00;
                   top:720px; left:-5px; width:".(($w)+8)."px;
                   height:".($h-720)."px; font-size:{$mida_lletra}px; line-height: 1.7;\"";
    } else {
        $scaixa = "style =\"position:absolute;
                   top:110px; left: ".(($w/2)-18)."px; width:".(($w/2)+80)."px; height:".($h-17)."px;
                   font-size:{$mida_lletra}px;line-height: 1.4;text-align:center;\"";
    }
    
    divclass('caixatotal', $scaixa);
    
    while($v = $venedors_avui->fetch_object()) {
        mostrar_info_venedor($v, $FROMindex_relacio, $link, $ID_Empresa, $scale_uri, $decimalsing, $thousandsing);
    }
}

/**
 * Mostra la informació d'un venedor
 */
function mostrar_info_venedor($v, $FROMindex_relacio, $link, $ID_Empresa, $scale_uri, $decimalsing, $thousandsing) {
    $qid = "SELECT MAX(ID) AS ID $FROMindex_relacio WHERE `ID_Venedor`='{$v->ID_Venedor}'";
    $id = $link->query($qid)->fetch_assoc();
    
    $quuid = "SELECT MAX(uuid) AS uuid $FROMindex_relacio WHERE `ID_Venedor`='{$v->ID_Venedor}'";
    $uuid = $link->query($quuid)->fetch_assoc();
    
    $r = detallrelacio($uuid['uuid'], $ID_Empresa, date("Y"), $link);
    $nom_venedor = explode(" ", $r['Nom_Venedor'] ? $r['Nom_Venedor'] : "");
    
    $relacions_obertes = mysql1r("SELECT SUM(`relacio_oberta`) $FROMindex_relacio WHERE `ID_Venedor`='{$r['ID_Venedor']}'", $link);
    
    if($relacions_obertes > 1) {
        a("{$scale_uri}?search=/{$v->ID_Venedor}&m[estat]=subtotal", substr($nom_venedor[0], 0, 3).' error');
    } else {
        mostrar_accions_venedor($r, $uuid, $nom_venedor, $scale_uri, $decimalsing, $thousandsing);
    }
    br();
}

/**
 * Mostra les accions disponibles per un venedor
 */
function mostrar_accions_venedor($r, $uuid, $nom_venedor, $scale_uri, $decimalsing, $thousandsing) {
    if($r['relacio_oberta'] == '1') {
        if($r['tipus_relacio']['bool_allow_payment'] == 1) {
            a("{$scale_uri}total.php?via=1&ID={$r['ID']}&ID_tipus_relacio={$r['ID_tipus_relacio']}&uuid={$uuid['uuid']}", "&#128182;");
        }
        a("{$scale_uri}?search=/{$r['ID_Venedor']}&m[estat]=subtotal", 
          $r['activelines'].' '.substr($nom_venedor[0], 0, 2).' '.number_format($r['Total'], 2, $decimalsing, $thousandsing));
        if($r['tipus_relacio']['bool_allow_payment'] == 1) {
            a("{$scale_uri}total.php?via=2&ID={$r['ID']}&ID_tipus_relacio={$r['ID_tipus_relacio']}&uuid={$uuid['uuid']}", "&#128179;");
        }
    } else {
        if($r['tipus_relacio']['bool_allow_payment'] == 1) {
            if($r['ID_Pagament'] == 2) {
                a("{$scale_uri}total.php?via=1&ID={$r['ID']}&ID_tipus_relacio={$r['ID_tipus_relacio']}&uuid={$uuid['uuid']}", "&#128182;");
            }
            if($r['ID_Pagament'] == 1) {
                a("{$scale_uri}total.php?via=2&ID={$r['ID']}&ID_tipus_relacio={$r['ID_tipus_relacio']}&uuid={$uuid['uuid']}", "&#128179;");
            }
        }
        // tot el text és enllaç per imprimir el tiquet
        a("{$scale_uri}tiquet.php?uuid={$r['uuid']}",
          $r['activelines'].' '.substr($nom_venedor[0], 0, 2).' '.number_format($r['Total'], 2, $decimalsing, $thousandsing).' &#128424;');
    }
}

/**
 * Mostra la darrera línia afegida
 */
function mostrar_ultima_linia($w, $h) {
    if(!isset($_SESSION['lastline'])) {
        return;
    }
    
    $lastline = $_SESSION['lastline'];
    $background = $lastline[$lastline['table']]["units"] > 0 ? "" : "background:red";
    
    if($w < $h) {
        $style = "style =\"$background;position:absolute; top:265px;
                  left:25px;width:480px; font-size:25px;padding:2px;\"";
    } else {
        $style = "style =\"$background;position:absolute; top:".($h-180)."px;
                  left:25px;width:".($w/2)."px; font-size:25px;padding:2px;\"";
    }
    
    div('caixapreu', $style);
    
    $ku = $lastline[$lastline['table']]['bulk'] == '1' ? 'Kg' : 'Un';
    echo 'ultim: '.$lastline[$lastline['table']]['units']."$ku ".
         $lastline[$lastline['table']]['description'];
    
    tdiv();
}

/**
 * Mostra divs fixos (hora i última línia) que s'actualitzen via JavaScript
 */
function mostrar_divs_fixos($w, $h) {
    // Div per mostrar última línia (es posiciona al lateral dret o baix)
    echo '<div id="ultim_linia"></div>';
    
    // Div per mostrar hora i ID bàscula (es posiciona SOTA última línia)
    echo '<div id="info_hora"></div>';
    
    // JavaScript per actualitzar aquests divs amb les mides correctes del client
    echo '<script>
    function actualitzarDivsFixos() {
        var w = window.innerWidth;
        var h = window.innerHeight;
        
        // Actualitzar posició div última línia
        var ultimLiniaDiv = document.getElementById("ultim_linia");
        if(ultimLiniaDiv) {
            if(w < h) {
                ultimLiniaDiv.style = "position:absolute; top:265px; left:25px; width:480px; font-size:25px; padding:2px;";
            } else {
                ultimLiniaDiv.style = "position:absolute; top:" + (h-180) + "px; left:25px; width:" + (w/2) + "px; font-size:25px; padding:2px;";
            }
        }
        
        // Actualitzar posició div hora (SOTA última línia)
        var infoHoraDiv = document.getElementById("info_hora");
        if(infoHoraDiv) {
            if(w < h) {
                infoHoraDiv.style = "position:absolute; top:295px; left:25px; width:480px; height:13px; font-size:15px; padding:2px;";
            } else {
                infoHoraDiv.style = "position:absolute; top:" + (h-150) + "px; left:25px; width:" + (w/2) + "px; height:13px; font-size:15px; padding:2px;";
            }
        }
    }
    
    // Cridar en carregar i quan canviï la mida de finestra
    actualitzarDivsFixos();
    window.addEventListener("resize", actualitzarDivsFixos);
    </script>';
}

/**
 * Processa el codi rebut al camp magic i retorna acció a fer
 * @return array ['redirect' => URL] o ['error' => missatge] o ['formulari' => dades]
 */
function processar_codi_magic($ID, $link, $scale_uri) {
    $ID = trim($ID);
    
    if($ID === '') {
        return ['redirect' => $scale_uri];
    }
    
    if($ID === 'tancament') {
        return ['redirect' => $scale_uri.'/tancament.php'];
    }
    
    if($ID === 'reimprimeix') {
        return ['redirect' => $scale_uri.'/llistarel.php'];
    }
    
    if(strlen($ID) == 13 && $ID[0] == 2) {
        return processar_codi_legacy($ID, $link, $scale_uri);
    }
    
    if(is_numeric($ID) && strlen($ID) > 8 && $ID[0] != 2) {
        return processar_ean($ID, $link, $scale_uri);
    }
    

    if(strpos($ID, 'sing') !== false) {
        return processar_sing_in($ID, $scale_uri);
    }
    
    if(preg_match('/^\d+\.\d+$/', $ID)) {
        return ['info' => 'codi_paquet', 'ID' => $ID];
    }
    if (is_numeric($ID))
        return ['ID'=>$ID];
    return ['error' => 'Codi no reconegut'];
}

/**
 * Processa codi de barres legacy
 */
function processar_codi_legacy($ID, $link, $scale_uri) {
    if(legacyscale($ID) == 1) {
        legacyinsert($ID);
    }
    return ['redirect' => $scale_uri];
}

/**
 * Processa codi EAN i converteix a ID intern
 */
function processar_ean($ID, $link, $scale_uri) {
    $ID_intern = mysql1r("SELECT ID FROM ean WHERE Value='{$ID}'", $link);
    if($ID_intern) {
        return ['redirect' => $scale_uri."/?m[estat]=tracto&magic=$ID_intern"];
    }
    return ['redirect' => $scale_uri];
}

/**
 * Aplica 10% de descompte a l'última línia
 */
function aplicar_descompte_ultima_linia($link, $scale_uri) {
    if(!isset($_SESSION['lastline'])) {
        return $scale_uri.'?discount=no';
    }
    
    $l = $_SESSION['lastline'];
    $q = "UPDATE `{$l['table']}` SET `discount` = '10'
          WHERE `ID` = '{$l['ID']}' AND `ID_tipus_relacio` = '{$l[$l['table']]['ID_tipus_relacio']}'
          AND `line` = '{$l[$l['table']]['line']}'";
    
    if($link->query($q) === FALSE) {
        return $scale_uri.'?discount=mysqlerror' ;
    }
    
    return $scale_uri.'?discount=yes';
}

/**
 * Processa sign in at work
 */
function processar_sing_in($ID, $scale_uri) {
    global $w, $h, $ID_Empresa, $singkey, $sing_uri;
    
    // Format: sing+nom+[cognom]+telefon
    // Exemple: sing+anna o sing+anna+34600000000 o sing+anna+torrador+34600000000
    $parts = explode('+', $ID);
    
    $nom = '';
    $telefon = '';
    
    // Processar les parts
    if (count($parts) >= 2) {
        $nom = $parts[1]; // Primer sempre és el nom
        
        // Buscar el telèfon (última part que comença amb dígits)
        for ($i = count($parts) - 1; $i >= 2; $i--) {
            if (preg_match('/^\d/', $parts[$i])) {
                $telefon = $parts[$i];
                // Les parts entre nom i telèfon són cognoms
                if ($i > 2) {
                    $nom .= ' ' . implode(' ', array_slice($parts, 2, $i - 2));
                }
                break;
            }
        }
        
        // Si no hi ha telèfon trobat, totes les parts després del nom són cognoms
        if (empty($telefon) && count($parts) > 2) {
            $nom .= ' ' . implode(' ', array_slice($parts, 2));
        }
    }
    
    // Si el telèfon no té prefix +, afegir-lo si és espanyol (empieza amb 6 o 7)
    if (!empty($telefon) && $telefon[0] != '+' && preg_match('/^[67]/', $telefon)) {
        $telefon = '+34' . $telefon;
    }
    
    return ['redirect' => $sing_uri."?w=$w&h=$h&id=".urlencode($parts[1]).
            "&nom=".urlencode($nom)."&telefon=".urlencode($telefon).
            "&ID_Empresa=$ID_Empresa&singkey=$singkey&return_uri=".urlencode($scale_uri)];
}

/**
 * Case TRACTO - Processa el codi magic rebut
 * Suporta format teclat numèric (*, +) i format mòbil (x, l, d, i)
 */
function case_tracto($ID, $link, $scale_uri, $idbascula, $w, $h, $decimalsing, $thousandsing) {
    global $ivageneric, $ID_Empresa, $prefixrelacio, $ID_tipus_relacio, $decimalstoshowunits, $decimalstoshowmoney;
    
    $repesa = 8;
    $tempsrepesa = 0.7;
    // QR del tiquet digital del TPV del banc: el cobra (tiquettpv.php)
    if(function_exists('tpv_qr_url') && tpv_qr_url($ID) !== false) {
        $link->close();
        header("Location: {$scale_uri}tiquettpv.php?qr=".urlencode($ID));
        die;
    }
    // Descompte -10 (comprovar ABANS de parsejar perquè el parser interpreta - com abono)
    if($ID === '--10') {
        $r = aplicar_descompte_ultima_linia($link, $scale_uri);
        header("Location: $r");
        echo "que faig aqui? ";
        die;
    }

    
    // Etiquetes **codi o ***codi (abans del parsing perquè comencen amb **)
    if(substr($ID, 0, 2) === '**') {
        processar_imprimir_etiquetes($ID, $link, $scale_uri, $ID_Empresa);
        die;
    }
    
    // Mostrar ajuda *** sol
    if($ID == '***') {
        $link->close();
        header("Location: $scale_uri");
        die;
    }
    

    
    // Tancament (text alfanumèric - comprovar ABANS de parsejar)
    if($ID === 'tancament') {
        $link->close();
        header("Location: {$scale_uri}/tancament.php");
        die;
    }
    
    // Reimprimeix (text alfanumèric - comprovar ABANS de parsejar)
    if($ID === 'reimprimeix') {
        $link->close();
        header("Location: {$scale_uri}/llistarel.php");
        die;
    }
    
    // Sign in at work (text alfanumèric - comprovar ABANS de parsejar)
    if(strpos($ID, 'sing') !== false) {
        list($case, $codi) = explode('+', $ID);
        global $w, $h, $singkey, $sing_uri;
        $link->close();
        header("Location: {$sing_uri}?w=$w&h=$h&id=".urlencode($codi).
               "&ID_Empresa=$ID_Empresa&singkey=$singkey&return_uri=".urlencode($scale_uri));
        die;
    }
    
    // Comandes /  (comprovar ABANS de parsejar perquè parse_magic_code les marca com invalid)
    if($ID === '/') {
        $link->close();
        header("Location: $scale_uri");
        die;
    }
    if($ID[0] == '/' && is_numeric($ID[1])) {
        $link->close();
        header("Location: $scale_uri");
        die;
    }
    if($ID === '//') {
        $link->close();
        header("Location: $scale_uri");
        die;
    }
    if($ID[0] == '/' && $ID[1] == '/' && is_numeric($ID[2])) {
        $codi = substr($ID, 2);
        $link->close();
        header("Location: {$scale_uri}recepta.php?ID=$codi");
        die;
    }

    // Parsejar el codi màgic
    $parsed = parse_magic_code($ID, 'scale');
    
    // Si és invalid (text alfanumèric a scale), redirigir
    if($parsed['tipus'] === 'invalid') {
        $link->close();
        header("Location: $scale_uri");
        die;
    }
    
    // Extreure variables del parsing
    $tipus = $parsed['tipus'];
    $ID = $parsed['ID'];
    $units = $parsed['units'];
    $lotnumber = $parsed['lot'];
    $expirationdate = $parsed['data'];
    $abono = $parsed['abono'];
    $paquet = $parsed['paquet'];
    
    // Casos especials que necessiten formulari
    $formularilinea = false;
    $o = [];
    $IVA = '';
    $bulk = 0;
    
    // Casos especials amb redirect directe
    if($tipus === 'buit') {
        $link->close();
        header("Location: $scale_uri");
        die;
    }
    
    // Codi de barres legacy (13 dígits començant per 2)
    if($tipus === 'legacy') {
        if(legacyscale($ID) == 1) {
            legacyinsert($ID);
        }
        $link->close();
        header("Location: $scale_uri");
        die;
    }
    
    // Codi EAN llarg (més de 8 dígits, no comença per 2)
    if($tipus === 'ean') {
        $ID_trobat = mysql1r("SELECT ID from ean where Value='{$ID}'", $link);
        $link->close();
        header("Location: $scale_uri/?m[estat]=tracto&magic=$ID_trobat");
        die;
    }
    
    // Codi.paquet
    if($tipus === 'paquet') {
        $ID_amb_paquet = $ID . '.' . $paquet;
        $dades = processar_codi_paquet_complet($ID_amb_paquet, $units, $abono, $link, $decimalsing, $thousandsing);
        if($dades) {
            list($o, $IVA, $bulk, $units, $ID) = $dades;
            $formularilinea = true;
        } else {
            header("Location: $scale_uri");
            echo "mal paquet?";
            die;
        }
    }
    // Preu directe
    elseif($tipus === 'preu_directe') {
        $ID_complet = '+' . $parsed['preu'];
        if($parsed['units']) $ID_complet .= '*' . $parsed['units'];
        if($parsed['iva']) $ID_complet .= '+' . $parsed['iva'];
        
        $dades = processar_preu_directe($ID_complet, $units, $abono, $link, $idbascula, $repesa, $tempsrepesa, $scale_uri, $w, $h, $ivageneric);
        if($dades) {
            list($o, $IVA, $bulk, $units, $ID) = $dades;
            $formularilinea = true;
        }
    }
    // Codi producte normal
    elseif($tipus === 'codi') {
        $dades = processar_codi_producte($ID, $units, $abono, $link, $idbascula, $repesa, $tempsrepesa, $scale_uri, $w, $h);
        if($dades) {
            list($o, $IVA, $bulk, $units) = $dades;
            $formularilinea = true;
        } else {
            $link->close();
            header("Location: $scale_uri");
            echo "problemes amb el codi?";
            die;
        }
    }
    // Mostrar ajuda * sol
    elseif($ID == '*') {
        $link->close();
        header("Location: $scale_uri");
        die;
    }
    // Modificar unitats última línia *unitats
    elseif($ID[0] == '*' && is_numeric(substr($ID, 1))) {
        modificar_unitats_ultima_linia($ID, $link, $scale_uri);
    }
    // Mostrar ajuda / sol
    elseif($ID == '/') {
        $link->close();
        header("Location: $scale_uri");
        die;
    }
    // Mostrar subtotal /venedor
    elseif($ID[0] == '/' && is_numeric($ID[1])) {
        $link->close();
        header("Location: $scale_uri");
        die;
    }
    // Mostrar receptes disponibles //
    elseif($ID == '//') {
        $link->close();
        header("Location: $scale_uri");
        die;
    }
    // Imprimir receptes //codi
    elseif($ID[0] == '/' && $ID[1] == '/' && is_numeric($ID[2])) {
        $codi = substr($ID, 2);
        header("Location: {$scale_uri}recepta.php?ID=$codi");
        die;
    }
    // Default: codi no reconegut
    else {
        $link->close();
        header("Location: $scale_uri");
        die;
    }
    
    // Si hem arribat aquí amb $formularilinea, mostrem el formulari
    if($formularilinea) {
        mostrar_formulari_linia($o, $IVA, $bulk, $units, $ID, $lotnumber, $expirationdate, $w, $h, $scale_uri, $decimalsing, $thousandsing, $decimalstoshowunits, $decimalstoshowmoney);
    }
}

/**
 * Processa codi.paquet completament
 */
function processar_codi_paquet_complet($ID_complet, $units, $abono, $link, $decimalsing, $thousandsing) {
    list($ID, $package_type) = explode('.', $ID_complet);
    
    $package = $link->query("SELECT * FROM package_format WHERE `ID`=$ID AND `ID_package_type`=$package_type 
                             AND `hidden_date_time` = (SELECT MAX(`hidden_date_time`) FROM package_format 
                             WHERE `ID`='$ID' AND `ID_package_type`='$package_type')")->fetch_assoc();
    
    if(!is_array($package)) {
        return false;
    }
    
    $product = $link->query("SELECT * FROM tarifes WHERE `ID`=$ID AND `ultimpreu`=1")->fetch_assoc();
    $product['pvp'] = package_pvp($product, $package, $link);
    
    if(!$product['pvp']) {
        if($units > 0) {
            $units = ($package['weight'] / 1000) * $units;
        } else {
            $units = $package['weight'] / 1000;
        }
        $product['pvp'] = mysql1r("SELECT pvp FROM scalelist WHERE ID=$ID", $link);
    } else {
        $product["bool_in_bulk"] = 0;
    }
    
    if(is_numeric($units) && $units != " ") {
        $units = $abono . $units;
        $bulk = 0;
    } else {
        $units = $abono . '1';
        $bulk = 0;
    }
    
    $o[3] = $product["bool_in_bulk"];
    $o[4] = $product['pvp'];
    $o[1] = $link->query("SELECT description FROM package_description WHERE `ID`='$ID' 
                         AND `ID_package_type`='$package_type' AND `lang`='ca'")->fetch_assoc()['description'];
    
    if($o[1] == "") {
        $o[1] = $link->query("SELECT Value FROM productnamesurlencoded WHERE `ID`='$ID' 
                             AND `lang`='ca'")->fetch_assoc()['Value'];
        $ppes = mysql1r("SELECT `weight` FROM `package_format` WHERE `ID`='$ID' 
                        AND `ID_package_type`='$package_type' AND `hidden_date_time` = 
                        (SELECT MAX(`hidden_date_time`) FROM package_format WHERE `ID`='$ID' 
                        AND `ID_package_type`='$package_type')", $link);
        if($ppes > 1000) {
            $ppes = number_format_trim(($ppes / 1000), 3, $decimalsing, $thousandsing) . "Kg";
        } else {
            $ppes = $ppes . "g";
        }
        $o[1] .= " $ppes";
    }
    
    $o[1] = urldecod($o[1]);
    $o[5] = $product['pv4'];
    $IVA = trim(mysql1r("SELECT `Value` FROM IVA WHERE ID={$product['tipusiva']}", $link), '%');
    
    return [$o, $IVA, $bulk, $units, $ID];
}

/**
 * Processa codi de producte normal
 */
function processar_codi_producte($ID, $units, $abono, $link, $idbascula, $repesa, $tempsrepesa, $scale_uri, $w, $h) {
    $sql = "SELECT exist, value, formatpvp, bulk, pvp, cost FROM scalelist WHERE ID=$ID";
    $o = mysql2vector($sql, $link);
    
    if($o[0] != 1) {
        return false;
    }
    
    if($o[3] == 1) { // Granel
        if(is_numeric($units) && $units != " ") {
            $units = $abono . $units;
            $bulk = 1;
        } else {
            $pes = mysql1r("SELECT pes FROM ultimpes WHERE ID=$idbascula", $link) / 1000;
            if($pes == 0) {
                gestionar_pes_invalid($scale_uri, $repesa, $tempsrepesa, $w, $h, $ID);
            }
            $units = $abono . $pes;
            $bulk = 1;
        }
    } else { // Envasat
        if(is_numeric($units) && $units != " ") {
            $units = $abono . $units;
            $bulk = 0;
        } else {
            $units = $abono . '1';
            $bulk = 0;
        }
    }
    
    $tipusIVA = mysql1r("SELECT `tipusiva` FROM `tarifes` WHERE `ultimpreu` = '1' AND `ID` = '$ID'", $link);
    $IVA = trim(mysql1r("SELECT `Value` FROM IVA WHERE ID=$tipusIVA", $link), '%');
    $o['1'] = urldecode($o['1']);
    
    return [$o, $IVA, $bulk, $units];
}

/**
 * Gestiona quan el pes és invàlid (0)
 */
// $reintent: URL a on tornar (amb location.replace) en lloc de tracto&magic=$magic (preu directe)
function gestionar_pes_invalid($scale_uri, $repesa, $tempsrepesa, $w, $h, $magic, $reintent = null) {
    global $link;
    
    $link->close();
    $_SESSION['pes0']++;
    
    if($_SESSION['pes0'] >= $repesa) {
        $_SESSION['pes0'] = 0;
        estil_capcal(false);
        div('error', "style =\"position:absolute; top:" . ($h / 2) . "px;left:25px;font-size:75px;padding:2px;center;\"");
        a($scale_uri, 'pes invàlid apreta aqui sobre');
        tdiv();
        die;
    } else {
        estil_capcal(false);
        div('error', "style =\"position:absolute; top:" . ($h / 2) . "px;left:25px;font-size:75px;padding:2px;center;\"");
        a($scale_uri, "esperant pes correcte o apreta aqui [{$_SESSION['pes0']}]");
        tdiv();
        if($reintent === null)
            echo "<script>setTimeout(function(){window.location.href=\"{$scale_uri}?m[estat]=tracto&magic={$magic}\"}," . ($tempsrepesa * 1000) . ")</script>";
        else
            echo "<script>setTimeout(function(){location.replace(\"{$reintent}\")},". ($tempsrepesa * 1000) .")</script>";
        die;
    }
}

/**
 * Processa preu directe +preu(*units)(+iva)
 * Formats suportats:
 * Teclat numèric: +preu, +preu*units, +preu+iva, +preu*units+iva
 * Mòbil: +preu, +preuxunits, +preuiiva, +preuxunitsiiva
 */
function processar_preu_directe($ID, $units, $abono, $link, $idbascula, $repesa, $tempsrepesa, $scale_uri, $w, $h, $ivageneric) {
    unset($units);
    $IVA = '';
    
    // Treure el prefix +
    $ID = substr($ID, 1);
    
    // Convertir format mòbil a estàndard: xunits→*units, iiva→+iva
    if(strpos($ID, 'x') !== false || strpos($ID, 'i') !== false) {
        $ID = str_replace('x', '*', $ID);
        $ID = preg_replace('/i(\d+)/', '+$1', $ID);
    }
    
    // Parsejar format estàndard: preu(*units)(+iva)
    $parts_iva = explode('+', $ID);
    if(count($parts_iva) > 1) {
        $ID = $parts_iva[0];
        $IVA = $parts_iva[1];
    }
    
    // Parsejar preu*units
    if(strpos($ID, '*')) {
        list($o[4], $units) = explode('*', $ID);
    } else {
        $o[4] = $ID;
        $units = null;
    }
    
    // Determinar si són unitats comptades o pes
    if(is_numeric($units)) {
        // Unitats especificades
        $units = $abono . $units;
        $bulk = 0;
        $o[3] = 0;
    } else {
        // Pes de la balança
        $o[3] = 1;
        $pes = mysql1r("SELECT pes FROM ultimpes WHERE ID=$idbascula", $link) / 1000;
        
        if($pes == 0) {
            gestionar_pes_invalid($scale_uri, $repesa, $tempsrepesa, $w, $h, null, "{$scale_uri}?m[estat]=extra&id={$o['4']}");
        }
        $units = $abono . $pes;
        $bulk = 1;
    }
    
    // Si no s'ha especificat IVA, usar el genèric
    if($IVA == '' || !is_numeric($IVA)) {
        $IVA = $ivageneric;
    }
    
    $o[1] = "ARTICLE GENERIC AMB $IVA% d'IVA";
    $ID = 0;
    $o['5'] = round($o['4'] / 1.83, 2);
    
    // Validar que l'IVA existeix a la base de dades
    $iva_valid = mysql1r("SELECT Value FROM IVA WHERE Value='$IVA'", $link);
    if(!$iva_valid) {
        $link->close();
        if(!isset($a)) header("Location: $scale_uri?error=iva_invalid");
        die;
    }
    
    return [$o, $IVA, $bulk, $units, $ID];
}

/**
 * Processa imprimir etiquetes **codi o ***codi
 */
function processar_imprimir_etiquetes($ID, $link, $scale_uri, $ID_Empresa) {
    if($ID[2] == '*') {
        // ***codi - Etiqueta de producte
        $pattern = '/^\*\*\*(\d+)(?:\.(\d+))?\*?(\d+)?(?:\+(\d+))?(?:\+(.*))?$/';
        $matches = [];
        
        if(preg_match($pattern, $ID, $matches)) {
            $codi = $matches[1];
            $package = isset($matches[2]) && $matches[2] !== '' ? $matches[2] : '';
            $units = isset($matches[3]) && $matches[3] !== '' ? $matches[3] : '';
            $lot = isset($matches[4]) && $matches[4] !== '' ? $matches[4] : '';
            $bbf = isset($matches[5]) && $matches[5] !== '' ? $matches[5] : '';
        } else {
            // Si no fa match el pattern, provem simplement ***codi
            $codi = substr($ID, 3); // Treu els tres asteriscs
            $package = '';
            $units = '';
            $lot = '';
            $bbf = '';
        }
        
        if($units === "") $units = 1;
        
        // Comprovar si és etiqueta especial (existeix SVG directe)
        global $midaetiqueta;
        $svg_especial = $_SERVER['DOCUMENT_ROOT']."/scale/img/{$codi}{$midaetiqueta}.svg";
        if(!file_exists($svg_especial)) {
            $svg_especial = $_SERVER['DOCUMENT_ROOT']."/scale/img/{$codi}.svg";
        }
        $es_especial = file_exists($svg_especial);
        
        // Validar que el producte existeixi (excepte especials)
        if(!$es_especial && !mysql1r("SELECT `id` FROM `scalelist` WHERE `id` = '$codi'", $link)) {
            header("Location: $scale_uri");
            $link->close();
            die;
        }
        
        if(!mysql1r("SHOW COLUMNS FROM imprimeix LIKE 'package'", $link)) {
            try {
                $link->query("ALTER TABLE `imprimeix` ADD `package` SMALLINT UNSIGNED NULL AFTER `ID_product`, 
                             ADD `lot` VARCHAR(255) NULL AFTER `package`, ADD `bbf` VARCHAR(255) NULL AFTER `lot`");
            } catch(Exception $e) {
                debugtelegram("Error creant columnes: " . mysqli_error($link), gethostname());
            }
        }
        
        $q = "INSERT INTO `imprimeix` (`ID_product`, `units`, `preu_or_etiqueta`, `package`, `lot`, `bbf`)
              VALUES ('$codi', '$units', '1', " . ($package === '' ? "NULL" : "'$package'") . ", " . 
              ($lot === '' ? "NULL" : "'$lot'") . ", " . ($bbf === '' ? "NULL" : "'$bbf'") . ")";
        
        try {
            if($link->query($q) === FALSE) {
                debugtelegram("Error INSERT etiqueta ***: " . $q . " Error: " . mysqli_error($link), gethostname());
            }
        } catch(Exception $e) {
            debugtelegram("Exception inserint etiqueta: " . $q . " " . $e->getMessage(), gethostname());
        }
    } else {
        // **codi - Etiqueta de preu
        // Validació: només permetre format vàlid **codi o **codi*units o **codi.package*units
        if(!preg_match('/^\*\*[0-9]+(\.[0-9]+)?(\*[0-9]+)?$/', $ID)) {
            // Format invàlid, ignorem i tornem sense fer res
            $link->close();
            header("Location: $scale_uri");
            die;
        }
        
        $parts = explode('.', $ID);
        $ID = $parts[0];
        $package = isset($parts[1]) ? $parts[1] : '';
        
        $parts2 = explode('*', substr($ID, 2));
        $codi = $parts2[0];
        $units = isset($parts2[1]) && $parts2[1] !== '' ? $parts2[1] : null;
        
        if(!isset($units)) $units = 1;
        
        if($codi == mysql1r("SELECT `id` FROM `scalelist` WHERE `id` = '$codi'", $link)) {
            if($package == "") {
                $q = "INSERT INTO `imprimeix` (`ID_product`, `units`, `preu_or_etiqueta`)
                      VALUES ('$codi', '$units', '0')";
                try {
                    $link->query($q);
                } catch(Exception $e) {
                    debugtelegram($q . mysqli_error($link));
                    header("Location: $scale_uri");
                    die;
                }
            } else {
                if(!mysql1r("SHOW COLUMNS FROM imprimeix LIKE 'package'", $link)) {
                    try {
                        $link->query("ALTER TABLE `imprimeix` ADD `package` SMALLINT UNSIGNED NULL AFTER `ID_product`, 
                                     ADD `lot` VARCHAR(255) NULL AFTER `package`, ADD `bbf` VARCHAR(255) NULL AFTER `lot`");
                    } catch(Exception $e) {
                        a("./", h2r("no he pogut crear la columna package"));
                        die;
                    }
                }
                list($package, $units) = explode('*', $package);
                if($units == "") $units = 1;
                
                $q = "INSERT INTO `imprimeix` (`ID_product`, `units`, `package`, `preu_or_etiqueta`)
                      VALUES ('$codi', '$units', '$package', '0')";
                
                if($link->query($q) === FALSE) {
                    echo $q . mysqli_error($link);
                    die;
                }
            }
        }
    }
    
    header("Location: {$scale_uri}");
    $link->close();
    die;
}

/**
 * Modifica unitats de l'última línia
 */
function modificar_unitats_ultima_linia($ID, $link, $scale_uri) {
    global $prefixrelacio;
    
    if(!isset($_SESSION['lastline']) || !isset($_SESSION['lastline']['ID'])) {
        $link->close();
        header("Location: $scale_uri?error=no-lastline");
        die;
    }
    
    $l = $_SESSION['lastline'];
    $ID_relacio = $l['ID'];
    
    // Comprovar si la relació està oberta
    $relacio_oberta = mysql1r("SELECT relacio_oberta FROM {$prefixrelacio}index_relacio WHERE ID=$ID_relacio", $link);
    
    if($relacio_oberta != 1) {
        $link->close();
        header("Location: $scale_uri?error=relacio-tancada");
        die;
    }
    
    $multiplicador = intval(substr($ID, 1));
    
    // Actualitzar unitats: multiplicar les unitats actuals per el multiplicador
    $q = "UPDATE `{$l['table']}` SET `units` = `units` * $multiplicador
          WHERE `ID` = '{$l['ID']}' AND `ID_tipus_relacio` = '{$l[$l['table']]['ID_tipus_relacio']}'
          AND `line` = '{$l[$l['table']]['line']}'";
    
    if($link->query($q) === FALSE) {
        echo $q . "<br/>" . mysqli_error($link);
        die;
    }
    
    // Actualitzar la sessió amb les noves unitats
    $_SESSION['lastline'][$l['table']]['units'] = $_SESSION['lastline'][$l['table']]['units'] * $multiplicador;
    
    $link->close();
    header("Location: $scale_uri?multiply=ok");
    die;
}

/**
 * Mostra formulari per entrar línia de venda
 */
function mostrar_formulari_linia($o, $IVA, $bulk, $units, $ID, $lotnumber, $expirationdate, $w, $h, $scale_uri, $decimalsing, $thousandsing, $decimalstoshowunits, $decimalstoshowmoney) {
    global $c;
    
    estil_capcal(false);
    form($scale_uri, 'GET');
    divclass("caixa", "style =\"background:#f40e85;\" ");
    
    if($w < $h) {
        $scaixa = "style =\"background:#ACAB00;\" ";
    } else {
        $scaixa = "style =\"background:#ACAB00;height:" . ($h / 5) . "px;width: " . (($w / 2) - 90) . "px; left:" . (($w / 2) + 80) . "px\" ";
    }
    
    divclass("caixacodi", $scaixa);
    
    if($w < $h) {
        echo "<div style=\" display:table-cell; text-align: center; vertical-align:middle; width:" . (($w / 2) - 90) . "px; height:119px; font-size:33px; color:#defeca; \">";
        echo '<input type="search" name="venedor" size=7 id="magic" placeholder="Codi venedor o Comanda" class="form-control" autofocus autocomplete="off" onChange="this.form.submit()"; style=" display:table-cell; vertical-align:middle; width:524px; height:80px; position:relative;  font-size: 33px;" />';
    } else {
        echo "<div style=\" display:table-cell; text-align: center; vertical-align:middle; width:594px; height:119px; font-size:33px; color:#defeca; \">";
        echo '<input type="search" name="venedor" size=7 id="magic" placeholder="Codi venedor o 0" class="form-control" autofocus autocomplete="off" onChange="this.form.submit()"; style=" display:table-cell; vertical-align:middle; width:' . (($w / 2) - 110) . 'px; height:80px; position:relative;  font-size: 33px;" />';
    }
    
    echo "</div>";
    tdiv();
    
    if($w > $h) $scaixa = "style =\"background:#ACAB00;top:5px\" ";
    divclass("pes", $scaixa);
    echo number_format($units, $decimalstoshowunits, $decimalsing, $thousandsing);
    echo $o[3] == 1 ? "Kg" : "U";
    tdiv();
    
    $sep = 20;
    if($w > $h) $scaixa = "style =\"background:#ACAB00;top:" . (($h / 5) + $sep) . "px\" ";
    divclass("caixapreu", $scaixa);
    echo number_format($o[4], $decimalstoshowmoney, $decimalsing, $thousandsing);
    echo $o[3] == 1 ? "€/Kg" : "€/U";
    tdiv();
    
    if($w > $h) $scaixa = "style =\"background:#ACAB00;top:" . ((2 * ($h / 5)) + $sep) . "px\" ";
    divclass("caixatlinea", $scaixa);
    echo number_format($units * $o[4], $decimalstoshowmoney, $decimalsing, $thousandsing);
    echo "€";
    tdiv();
    
    if($w > $h) $scaixa = "style =\"background:#ACAB00;top:" . ((3 * ($h / 5)) + $sep) . "px\" ";
    divclass("caixadescripcio", $scaixa);
    divclass('Descripcio');
    echo $o[1];
    tdiv();
    tdiv();
    tdiv();
    
    $m["ID_product"] = $ID;
    $m["description"] = $o[1];
    if($expirationdate != "") $m["description"] .= " Consumir abans: $expirationdate";
    $m["units"] = $units;
    $m["cost"] = $o[4] / (($IVA / 100) + 1);
    $m["cost_price"] = $o['5'];
    $m["lotnumber"] = $lotnumber;
    $m["IVA"] = $IVA;
    $m["estat"] = 'entralinea';
    $m["bulk"] = $bulk;
    $m['ID_package_type'] = $package_type ?? 0;
    
    hidden("m", $m);
    sumit1("e", "style =\"font-size:1px; position:absolute; left: -30px; top: -10px ;\"");
    tdiv();
    tform();
}

/**
 * Case ENTRALINEA - Entra una línia de venda per un venedor
 */
function case_entralinea($ID_venedor, $link, $scale_uri) {
    global $ID_Empresa, $prefixrelacio, $ID_tipus_relacio, $Venda_detall;
    
    // Comprova si el venedor existeix
    if(!is_numeric($ID_venedor) || $ID_venedor != mysql1r("SELECT ID FROM {$ID_Empresa}_Venedor WHERE ID='$ID_venedor'", $link)) {
        $link->close();
        header("Location: $scale_uri");
        die;
    }
    
    // Crea taules si no existeixen
    if(!mysql1r("SHOW TABLES LIKE '{$prefixrelacio}index_relacio'", $link)) {
        $link->query("CREATE TABLE `{$prefixrelacio}index_relacio` LIKE `meta_index_relacio`");
    }
    if(!mysql1r("SHOW TABLES LIKE '{$prefixrelacio}line'", $link)) {
        $link->query("CREATE TABLE `{$prefixrelacio}line` LIKE `meta_line`");
    }
    if(!mysql1r("SHOW COLUMNS FROM `{$prefixrelacio}line` LIKE 'ID_package_type'", $link)) {
        $link->query("ALTER TABLE `{$prefixrelacio}line` ADD `ID_package_type` TINYINT UNSIGNED NULL DEFAULT '0' AFTER `ID_product`");
    }
    
    // Busca relació oberta del venedor
    $ID_relacio = mysql1r("SELECT MAX(ID) FROM `{$prefixrelacio}index_relacio` 
                          WHERE ID_Venedor=$ID_venedor AND relacio_oberta=1", $link);
    
    $new = false;
    if(!$ID_relacio) {
        $new = true;
        $ID_relacio = mysql1r("SELECT MAX(ID)+1 FROM `{$prefixrelacio}index_relacio` 
                              WHERE ID_tipus_relacio=$ID_tipus_relacio", $link);
    }
    if(!$ID_relacio) {
        $ID_relacio = 1;
        $new = true;
    }
    
    // Prepara dades de la línia
    $m["{$prefixrelacio}line"] = $_REQUEST['m'];
    $m["ID"] = $ID_relacio;
    
    if($new) {
        $m["{$prefixrelacio}line"]["ID_tipus_relacio"] = $ID_tipus_relacio;
    } else {
        $m["{$prefixrelacio}line"]["ID_tipus_relacio"] = mysql1r("SELECT ID_tipus_relacio FROM `{$prefixrelacio}index_relacio` 
                                                                   WHERE ID_Venedor=$ID_venedor AND relacio_oberta=1", $link);
    }
    
    $m["do"] = "new";
    $m['table'] = "{$prefixrelacio}line";
    $m["{$prefixrelacio}line"]["line"] = $new ? 1 : mysql1r("SELECT MAX(line)+1 FROM `{$prefixrelacio}line` 
                                                             WHERE ID=$ID_relacio AND ID_tipus_relacio={$m["{$prefixrelacio}line"]["ID_tipus_relacio"]}", $link);
    
    if($m["{$prefixrelacio}line"]["line"] == "") {
        $m["{$prefixrelacio}line"]["line"] = 1;
    }
    
    // Si és nova relació, crea l'índex
    if($new) {
        $m2['table'] = $prefixrelacio . 'index_relacio';
        $m2['ID'] = $ID_relacio;
        $m2['do'] = 'new';
        $d['ID_Venedor'] = $ID_venedor;
        $d['ID_client'] = 0;
        $d['ID_tipus_relacio'] = $ID_tipus_relacio;
        $d['fiscal_name'] = $Venda_detall;
        $d['data']['mday'] = date("j");
        $d['data']['mon'] = date("n");
        $d['data']['year'] = date("Y");
        $d['uuid'] = uniqid($ID_Empresa);
        $m2[$m2['table']] = $d;
        new_update($m2, $link);
    }
    
    // Entra la línia
    new_update($m, $link, true);
    
    // Si no és nova, assigna per telèfon si cal
    if(!$new) {
        $uuid = mysql1r("SELECT `uuid` FROM `{$prefixrelacio}index_relacio` 
                        WHERE ID_Venedor=$ID_venedor AND relacio_oberta=1", $link);
        $r = detallrelacio($uuid, $ID_Empresa, date("Y"), $link);
        if($r['phone'] != '') {
            asigna_relacio_per_telefon($r, $r['phone'], $ID_Empresa, date("Y"), $link);
        }
    }
    
    unset($_SESSION['lastline']);
    $_SESSION['lastline'] = $m;
    
    $link->close();
    header("Location: $scale_uri");
    die;
}

/**
 * Case SUBTOTAL - Mostra el subtotal d'una relació
 */
function case_subtotal($ID_venedor, $link, $scale_uri, $w, $h, $prefixrelacio, $ID_Empresa, $decimalsing, $thousandsing, $decimalstoshowmoney, $decimalstoshowunits) {
    estil_capcal(false);
    form($scale_uri, 'GET');
    hidden("m[estat]", 'tracto_subtotal');
    hidden("while", $_REQUEST['while']);
    divclass("caixa");
    
    if($w < $h) {
        divclass("caixacodi", "style=\"height: 215px;\"");
    } else {
        divclass("caixacodi", "style=\"top:2px;left:3px;height:" . ($h - 10) . "px;width:" . ($w - 10) . "px\"");
    }
    
    hidden('Venedor', $ID_venedor);
    
    // Obté UUID de la relació oberta
    $FROMindex_relacio = "FROM {$prefixrelacio}index_relacio WHERE ID_venedor=$ID_venedor AND relacio_oberta=1";
    $quuid = "SELECT MAX(ID) AS ID, uuid $FROMindex_relacio";
    $uuid = $link->query($quuid)->fetch_assoc();
    
    $r = detallrelacio($uuid['uuid'], $ID_Empresa, date("Y"), $link);
    
    // Placeholder segons tipus client
    $placeholder = "&#128241;"; // Símbol mòbil
    if($r['tipus_relacio']['bool_allow_payment'] == "1") {
        $placeholder .= "1&#128182;2&#128179;11..15"; // Bitllet i targeta
    }
    
    // Input magic
    if($w < $h) {
        echo '<div style="display:table-cell;vertical-align:left;width:580px;position:absolute;top: 8px;left:6px;">';
    } else {
        echo '<div style="display:table-cell;vertical-align:left;width:' . (($w / 2) - 30) . 'px;position:absolute;top: 8px;left=-3px;">';
    }
    
    echo '<input type="search" name="magic" size=10 id="magic" placeholder="' . $placeholder . '" class="form-control" autocomplete="off" autofocus style="font-size:47px"/>';
    
    // Select pagament
    if($r['tipus_relacio']['bool_allow_payment'] == "1") {
        $v = mysql2array("SELECT `ID`, CONCAT(`ID`, `Value`) FROM `Pagament` WHERE `ID` <= '10'", $link);
    } else {
        $v[0] = array(0, 1, 2);
        $v[1] = array("Tria", "Continua editant {$r['tipus_relacio']['Value']}", "Deixa de editar {$r['tipus_relacio']['Value']}");
    }
    
    if($r['ID_tipus_relacio'] == 1) {
        $v = mysql2array("SELECT `ID`, CONCAT(`ID`, ' ', `Value`) FROM Pagament", $link);
    }
    
    echo '</div>';
    
    if($w < $h) {
        echo '<div style="display:table-cell; vertical-align:middle; width:580px; top:80px;position:absolute;">';
    } else {
        echo '<div style="display:table-cell; vertical-align:middle; width:580px; left:' . (($w / 2) - 30) . 'px;top:2px;position:absolute;">';
    }
    
    select2llistes_js('viapagament', $v[0], $v[1], 0, 'no', false, "class=\"select-css\"");
    tdiv();
    tdiv();
    tdiv();
    
    // Caixa descripció
    if($w < $h) {
        $scaixa = "style =\"background:#ACAB00; position:relative; top:220px; left:5px; width:580px; height:815px; font-size:33px;padding:1%;\"";
    } else {
        $scaixa = "style =\"background:#ACAB00; position:relative; top:110px; left:5px; width:" . $w . "px; height:815px; font-size:33px;padding:1%;\"";
    }
    
    divclass('caixadescripcio', $scaixa);
    
    if($r['phone'] == '') {
        echo "Subtotal {$r['tipus_relacio']['Value']} " . mysql1r("SELECT Value FROM {$ID_Empresa}_Venedor WHERE ID='{$r['ID_Venedor']}'", $link);
    } else {
        echo "{$r['tipus_relacio']['Value']} a " . urldecode($r['fiscal_name']) . " {$r['phone']}";
    }
    
    if($_REQUEST['while'] == "yes") {
        br();
        echo "abans de sortir d'aqui has de tancar la relació anterior, 1 i intro per efectiu, 2 per tarjeta...'";
        br();
    }
    
    if($w < $h) br();
    echo " Ref:" . $r['activelines'];
    
    hidden('uuid', $uuid['uuid']);
    
    $total = "Total";
    if($r['discount'] > 0) $total .= " (Desc. -{$r['discount']}%)";
    $total .= ": " . number_format($r['Total'], $decimalstoshowmoney, $decimalsing, $thousandsing) . "€";
    $ja_redimit_vals = val_import_redimit_relacio($uuid['uuid'], $link, $ID_Empresa);
    if($ja_redimit_vals > 0) {
        $total .= " · Xec regal: -" . number_format($ja_redimit_vals, $decimalstoshowmoney, $decimalsing, $thousandsing) . "€"
            . " · Pendent: " . number_format(max(0, $r['Total'] - $ja_redimit_vals), $decimalstoshowmoney, $decimalsing, $thousandsing) . "€";
    }
    echo $total;
    
    // Mostra línies
    $parell = 0;
    if($r['activelines'] > 0) {
        foreach(array_reverse($r['active_lines']) as $v) {
            if($parell == 0) {
                div("Descripcio", "style=\"position:relative;padding: 10px; background-color: #00acab;\"");
                $parell = 1;
            } else {
                div("Descripcio", "style=\"position:relative;padding: 10px; background-color: #acab00;\"");
                $parell = 0;
            }
            
            $nom = $v['urlde'];
            if($w < $h) {
                divclass('linea', "style=\"display: table-cell; text-align:left; width: 580px;\"");
            } else {
                divclass('linea', "style=\"display: table-cell; text-align:left; width:" . ($w - 20) . "px;\"");
            }
            
            $linea = "$nom ";
            if($w < $h) $linea .= "<br/>";
            
            if($v['u'] == "Kg") {
                $linea .= number_format($v['units'], $decimalstoshowunits, $decimalsing, $thousandsing) . $v['u'] . '*';
            } else {
                $linea .= number_format_trim($v['units'], $decimalstoshowunits, $decimalsing, $thousandsing) . $v['u'] . '*';
            }
            
            $linea .= number_format($v['cost'], $decimalstoshowmoney, $decimalsing, $thousandsing) . '€/' . $v['u'];
            if($v['discount'] > 0) $linea .= ' -' . number_format_trim($v['discount'], 2, $decimalsing, $thousandsing) . '%';
            $linea .= "=";
            $linea .= number_format($v['tline'], $decimalstoshowmoney, $decimalsing, $thousandsing) . '€';
            echo $linea;
            tdiv();
            
            divclass('elimina', "style=\"display: ruby-base; text-align:right; font-size: 150%; vertical-align: middle;\"");
            if($v['line'] > 1) a("./newrelation.php?line={$v['line']}&ID_venedor=$ID_venedor", "N");
            echo "&nbsp;&nbsp;  ";
            a("./elimina.php?ID={$r['ID']}&ID_tipus_relacio={$r['ID_tipus_relacio']}&line={$v['line']}&Venedor=$ID_venedor", 'X');
            tdiv();
            tdiv();
        }
    } else {
        echo "<br/><br/>no hi ha línees actives";
    }
    
    tform();
    tdiv();
}

/**
 * Case TRACTO_SUBTOTAL - Processa accions del subtotal
 */
function case_tracto_subtotal($link, $scale_uri, $prefixrelacio) {
    global $ID_Empresa;

    // QR d'un xec regal llegit al subtotal: l'aplica com a descompte/pagament de la venda oberta
    // (accepta el prefix actual XEC- i també l'antic VAL-, per als xecs ja emesos abans del canvi de nom)
    if(val_es_possible_codi($_REQUEST['magic'] ?? '')) {
        header("Location: {$scale_uri}val.php?codi=" . urlencode(trim($_REQUEST['magic'])) . "&uuid=" . urlencode($_REQUEST['uuid'] ?? ''));
        die;
    }
    // QR del TPV llegit al subtotal: si l'import quadra, cobra aquesta relació
    if(function_exists('tpv_qr_url') && tpv_qr_url($_REQUEST['magic'] ?? '') !== false) {
        header("Location: {$scale_uri}tiquettpv.php?qr=".urlencode($_REQUEST['magic'])."&uuid=".urlencode($_REQUEST['uuid'] ?? ''));
        die;
    }
    // Si magic està buit i viapagament és 0 o buit, tornar al case per defecte (pesa)
    $_REQUEST['magic'] = str_replace(" ", "", ($_REQUEST['magic'] ?? ''));
    $vp = $_REQUEST['viapagament'] ?? '0';
    if($_REQUEST['magic'] === '' && ($vp === '0' || $vp === '')) {
        header("Location: $scale_uri");
        die;
    }
    
    $r = detallrelacio($_REQUEST['uuid'] ?? null, $ID_Empresa, date("Y"), $link);
    if(!$r) {
        header("Location: $scale_uri");
        die;
    }
    
    $a = null;
    
    // Assignar telèfon (amb protecció d'errors)
    try {
        if(($_REQUEST['magic'][0] ?? '') == '+' || (strlen($_REQUEST['magic']) == 9)) {
            $a = asigna_relacio_per_telefon($r, trim($_REQUEST['magic']), $ID_Empresa, date("Y"), $link);
        } else if(($r['phone'] ?? '') != '') {
            $a = asigna_relacio_per_telefon($r, $r['phone'], $ID_Empresa, date("Y"), $link);
        }
    } catch (Throwable $e) {
        // Si peti, tornar al case per defecte (no reiniciar navegador)
        header("Location: $scale_uri");
        die;
    }
    
    // Descompte --5
    if($_REQUEST['magic'] == '--5') {
        $q = "UPDATE `{$prefixrelacio}index_relacio` SET `discount` = '5' 
              WHERE `ID` = '{$r['ID']}' AND `ID_tipus_relacio` = '{$r['ID_tipus_relacio']}'";
        if($link->query($q) === FALSE) {
            echo $q . mysqli_error($link);
            die;
        }
        $a = true;
    }
    
    $data = '';
    
    // Pagament via magic
    if(($r['tipus_relacio']['bool_allow_payment'] == "1") && 
       ($_REQUEST['magic'] == mysql1r("SELECT ID FROM Pagament WHERE `ID`='{$_REQUEST['magic']}'", $link)) &&
       ($_REQUEST['magic'] > 0)) {
        $data = "total.php?via={$_REQUEST['magic']}&ID={$r['ID']}&ID_tipus_relacio={$r['ID_tipus_relacio']}&uuid={$r['uuid']}";
        $a = false;
    }
    
    // Pagament via select
    if(($r['tipus_relacio']['bool_allow_payment'] == "1") && ($_REQUEST['viapagament'] > 0)) {
        $data = "total.php?via={$_REQUEST['viapagament']}&ID={$r['ID']}&ID_tipus_relacio={$r['ID_tipus_relacio']}&uuid={$r['uuid']}";
        $a = false;
    } else {
        switch($_REQUEST['viapagament']) {
            case 2:
                $q = "UPDATE `{$prefixrelacio}index_relacio` SET `relacio_oberta` = '0' 
                      WHERE `ID` = '{$r['ID']}' AND `ID_tipus_relacio` = '{$r['ID_tipus_relacio']}'";
                $link->query($q);
                $a = false;
                break;
            default:
                $a = false;
                break;
        }
    }
    
    if($_REQUEST['viapagament'] == "0") {
        $a = false;
    }
    
    // Loop forçat si while=yes
    if($_REQUEST['while'] == "yes" && $_REQUEST['viapagament'] == 0 && $_REQUEST['magic'] == "") {
        header("Location: $scale_uri?m[estat]=subtotal&search=/{$_REQUEST['Venedor']}&while=yes");
        die;
    }
    
    unset($_SESSION['r']);
    $_SESSION['r'] = $_REQUEST['r'];
    
    if($a === true) {
        header("Location: $scale_uri?m[estat]=subtotal&search=/{$_REQUEST['Venedor']}&ups={$_REQUEST['viapagament']}");
    } else if($a === false && $data !== '') {
        header("Location: {$scale_uri}{$data}");
    } else {
        // Si magic no es reconeix o està buit
        // Si el paner és buit (0 línies actives), tornar a la pàgina d'inici
        if($r['activelines'] == 0) {
            header("Location: $scale_uri");
        } else {
            header("Location: $scale_uri?m[estat]=subtotal&search=/{$_REQUEST['Venedor']}&ups={$_REQUEST['viapagament']}");
        }
    }
    die;
}

/**
 * Processa etiquetes de producte ***ID[.paquet][*unitats][+lot][+data]
 */
function case_tres_asteriscs($magic, $link) {
    // Detectar si acaba amb punt (***5042.)
    if(preg_match('/^\*\*\*(\d+)\.$/', $magic, $matches_punt)) {
        $ID = $matches_punt[1];
        
        // Comprovar si el producte existeix
        $producte_existeix = mysql1r("SELECT ID FROM productnamesurlencoded WHERE ID={$ID} AND lang='ca'", $link);
        
        if(!$producte_existeix) {
            echo "<span style=\"color:red;font-weight:bold;\">ERROR:</span>";
            echo "<br/><br/>";
            echo "El producte {$ID} no existeix a la base de dades";
            return;
        }
        
        // Mostrar paquets disponibles
        $paquets = $link->query("SELECT DISTINCT ID_package_type FROM package_format WHERE ID={$ID} ORDER BY ID_package_type");
        
        if($paquets->num_rows > 0) {
            echo "<strong>Paquets disponibles per al producte {$ID}:</strong>";
            echo "<br/><br/>";
            while($p = $paquets->fetch_assoc()) {
                echo "<span style=\"color:#ff6600;\">***{$ID}.{$p['ID_package_type']}</span>";
                echo "<br/>";
            }
        } else {
            echo "<span style=\"color:#888;\">Aquest producte no té paquets definits</span>";
            echo "<br/><br/>";
            echo "Prova amb: <span style=\"color:#ff6600;\">***{$ID}</span>";
        }
        return;
    }
    
    // Parsejar ***ID[.paquet][*unitats][+lot][+data]
    $pattern = '/^\*\*\*(\d+)(?:\.(\d+))?(?:\*(\d+))?(?:\+([^+]+))?(?:\+(.+))?$/';
    
    if (!preg_match($pattern, $magic, $matches)) {
        echo "<span style=\"color:red;font-weight:bold;\">ERROR:</span>";
        echo "<br/><br/>";
        echo "Format invàlid per etiqueta de producte";
        echo "<br/><br/>";
        echo "Format correcte: ***codi[.paquet][*unitats][+lot][+data]";
        return;
    }
    
    $ID = $matches[1];
    $paquet = isset($matches[2]) && $matches[2] !== '' ? $matches[2] : null;
    $unitats = isset($matches[3]) && $matches[3] !== '' ? $matches[3] : 1;
    $lot = isset($matches[4]) ? $matches[4] : null;
    $data = isset($matches[5]) ? $matches[5] : null;
    
    // Comprovar si és etiqueta especial (10000-10999)
    if ($ID >= 10000 && $ID < 11000) {
        // Etiquetes especials: usar SVG directe
        global $midaetiqueta;
        $svg_file = $_SERVER['DOCUMENT_ROOT']."/scale/img/{$ID}{$midaetiqueta}.svg";
        
        if (!file_exists($svg_file)) {
            $svg_file = $_SERVER['DOCUMENT_ROOT']."/scale/img/{$ID}.svg";
        }
        
        if (!file_exists($svg_file)) {
            echo "Etiqueta especial {$ID} no trobada";
            return;
        }
        
        $svg_content = file_get_contents($svg_file);
        
        // Substituir $any per l'any corresponent (any vinent si estem més enllà de gener)
        $mes_actual = date('n'); // 1-12
        $any_actual = date('Y');
        $any_a_mostrar = ($mes_actual > 1) ? ($any_actual + 1) : $any_actual;
        $svg_content = str_replace('$any', $any_a_mostrar, $svg_content);
        
        echo "<strong>Previsualització etiqueta especial:</strong> ";
        echo "<span style=\"font-size:1.5em;color:#ff6600;\">✕ $unitats</span><br/>";
        echo "Codi: $ID<br/><br/>";
        echo '<div style="background:white;padding:10px;display:inline-block;max-width:100%;overflow:auto;">';
        echo $svg_content;
        echo '</div>';
        
        return;
    }
    
    // Etiquetes de producte normals: generar SVG directe (sense escriure fitxers)
    require_once('phpgeneral/etiquetapreu.php');
    
    // Primer comprovar si el producte existeix
    $producte_existeix = mysql1r("SELECT ID FROM productnamesurlencoded WHERE ID={$ID} AND lang='ca'", $link);
    
    if(!$producte_existeix) {
        echo "<span style=\"color:red;font-weight:bold;\">ERROR:</span>";
        echo "<br/><br/>";
        echo "El producte {$ID} no existeix a la base de dades";
        return;
    }
    
    // Si demana un paquet específic, comprovar que existeix
    if($paquet) {
        $paquet_existeix = mysql1r("SELECT ID FROM package_format WHERE ID={$ID} AND ID_package_type={$paquet} AND hidden_date_time = (SELECT MAX(hidden_date_time) FROM package_format WHERE ID={$ID} AND ID_package_type={$paquet})", $link);
        
        if(!$paquet_existeix) {
            echo "<span style=\"color:red;font-weight:bold;\">ERROR:</span>";
            echo "<br/><br/>";
            echo "El paquet {$ID}.{$paquet} no existeix";
            echo "<br/><br/>";
            
            // Mostrar paquets disponibles
            $paquets = $link->query("SELECT DISTINCT ID_package_type FROM package_format WHERE ID={$ID} ORDER BY ID_package_type");
            if($paquets->num_rows > 0) {
                echo "<strong>Paquets disponibles per al producte {$ID}:</strong>";
                echo "<br/>";
                while($p = $paquets->fetch_assoc()) {
                    echo "***{$ID}.{$p['ID_package_type']}";
                    echo "<br/>";
                }
            } else {
                echo "Aquest producte no té paquets definits";
            }
            return;
        }
    }
    
    $print = [
        'ID_product' => $ID,
        'package' => $paquet ?: '',
        'units' => $unitats,
        'lot' => $lot,
        'bbf' => $data
    ];
    
    $svg_content = generar_etiqueta_producte_svg($print, $link);
    
    if (!$svg_content) {
        echo "<span style=\"color:red;font-weight:bold;\">ERROR:</span>";
        echo "<br/><br/>";
        echo "No s'ha pogut generar l'etiqueta de producte";
        echo "<br/><br/>";
        
        // Mostrar paquets disponibles si n'hi ha
        $paquets = $link->query("SELECT DISTINCT ID_package_type FROM package_format WHERE ID={$ID} ORDER BY ID_package_type");
        if($paquets->num_rows > 0) {
            echo "<strong>Paquets disponibles:</strong>";
            echo "<br/>";
            while($p = $paquets->fetch_assoc()) {
                echo "***{$ID}.{$p['ID_package_type']}";
                echo "<br/>";
            }
        }
        return;
    }
    
    echo "<strong>Previsualització etiqueta de producte:</strong> ";
    echo "<span style=\"font-size:1.5em;color:#ff6600;\">✕ $unitats</span><br/>";
    echo "Codi: $ID";
    if ($paquet) echo " - Paquet: $paquet";
    if ($lot) echo " - Lot: $lot";
    if ($data) echo " - Data: $data";
    echo "<br/><br/>";
    echo '<div style="background:white;padding:10px;display:inline-block;max-width:100%;overflow:auto;">';
    echo $svg_content;
    echo '</div>';
}

/**
 * Inicialitza la taula de receptes si no existeix
 */
function inicialitzar_taula_receptes($link) {
    // Comprovar si la taula existeix
    $table_exists = $link->query("SHOW TABLES LIKE 'receptes'")->num_rows > 0;
    
    if(!$table_exists) {
        // Crear taula
        $create_table = "CREATE TABLE `receptes` (
            `ID` INT NOT NULL AUTO_INCREMENT,
            `nom` VARCHAR(100) NOT NULL,
            `contingut` TEXT NOT NULL,
            `activa` TINYINT(1) DEFAULT 1,
            `ordre` INT DEFAULT 0,
            PRIMARY KEY (`ID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $link->query($create_table);
        
        // Inserir receptes per defecte
        $receptes_default = [
            [
                'nom' => 'Panellets',
                'contingut' => "PANELLETS

INGREDENTS:
• 500g farina d'ametlla
• 300g o 400g de sucre
• Una patata mitjana o dues de petites
• ou
• pinyons, ametlles, codony, coco, fruita confitada, xocolata, etc.

PREPARACIÓ:
Bullim les patates amb pell (o al microones embolicades amb una bossa de plàstic), un cop toves, les escorrem, les pelem i preparem un puré. A continuació hi afegim el sucre, la farina d'ametlla (i si ens agrada llimona ratllada) i ho barregem.
Suggeriment: deixar reposar la massa unes 3 hores.

De pinyons:
Agafem trossets de massa i fem boles. Passem les boles pel rovell d'ou batut i les arrebossem amb pinyons.

D'ametlla:
Agafem trossets de la massa i fem petites barres rectangulars. Les passem per rovell d'ou i seguidament ho arrebossem amb granet o palet d'ametlla.

De codony:
Aplanem la massa i a sobre hi posem un tros de codony quadrat. Emboliquem el codony amb la massa com si fos un braç de gitano i després el tallem a trossos al nostre gust.

De xocolata:
Agafem un bon tros de massa i hi barregem xocolata en pols. Els arrebossarem amb granet de xocolata. Podem fer formes diferents i divertides.

De fruites:
Agafem la massa, fem boles i al damunt de cada bola hi posem alguna fruita confitada: una cirera, una mica de taronja...

De coco:
Agafem el mateix volum de massa i de coco i ho barregem ben barrejat. Podem fer els panellets de coco amb forma de piràmide. Pintem les puntes de la piràmide amb rovell d'ou.

COCCIÓ:
Col·loquem els panellets a la plata del forn i els courem a 225-250 graus de 8 a 10 minuts aproximadament.",
                'ordre' => 1
            ],
            [
                'nom' => 'Bunyols de l\'Empordà',
                'contingut' => "BUNYOLS DE L'EMPORDÀ

INGREDENTS: al gust, però més o menys:
• 500g farina de força
• 150g sucre blanc
• 2 ous
• 125ml llet
• 30g llevat fresc
• 25g canella en pols (opcional)
• essència de llimona
• 50g~150g matafaluga en pols (anís en espècie)
• 1 gotet de anís líquid de llimona
• sucre de vainlla
• ratlladura d'una llimona",
                'ordre' => 2
            ],
            [
                'nom' => 'Enviaments a domicili',
                'contingut' => "ENVIAMENTS A DOMICILI

Si viviu lluny o voleu que us enviem els
nostres productes a casa, no dubteu en
escriure'ns a via whatsapp o telegram",
                'ordre' => 3
            ]
        ];
        
        foreach($receptes_default as $recepta) {
            $stmt = $link->prepare("INSERT INTO `receptes` (`nom`, `contingut`, `ordre`) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $recepta['nom'], $recepta['contingut'], $recepta['ordre']);
            $stmt->execute();
        }
    }
}

/**
 * Obté totes les receptes actives
 */
function obtenir_receptes($link) {
    inicialitzar_taula_receptes($link);
    
    $result = $link->query("SELECT `ID`, `nom`, `contingut` FROM `receptes` WHERE `activa`=1 ORDER BY `ordre`, `ID`");
    $receptes = [];
    
    while($row = $result->fetch_assoc()) {
        $receptes[$row['ID']] = $row;
    }
    
    return $receptes;
}

/**
 * Obté una recepta per ID
 */
function obtenir_recepta($ID, $link) {
    inicialitzar_taula_receptes($link);
    
    $stmt = $link->prepare("SELECT `ID`, `nom`, `contingut` FROM `receptes` WHERE `ID`=? AND `activa`=1");
    $stmt->bind_param("i", $ID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}
?>
