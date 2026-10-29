<?php
$app="relacions";
require('../scale/login.php');
if(!$login){require ('../scale/tanca.php');$link->close(); die;}
$r=&$_REQUEST;$s=&$_SESSION;
$crea="Alter Table `users` ADD `pot_veure_les_altres_empreses` tinyint NOT NULL DEFAULT '0' COMMENT '".date("D Y/m/d G:i:s")."';";
Try{ 
	mysqli_query($link,$crea);
}catch (Exception $e){
	echo "";//ignore error, means print field already exist
}
$files = glob(__DIR__ . '/*.ods');

foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file); // Borrem l'arxiu
    }
}
//debugtelegram($_SESSION);
$pot_veure_les_altres_empreses=mysql1r("select pot_veure_les_altres_empreses from users where `username`='{$s['user']}'",$link);
if(isset($r['emp'])&&$pot_veure_les_altres_empreses=='1')$s['rel']['Empresa']['ID']=$r['emp'];
if(isset($r['format']))$s['format']=$r['format'];
if(!isset($s['rel']['Empresa']['ID']))$s['rel']['Empresa']['ID']=$s['Empresa']['ID'];
if($s['Empresa']['ID']==$s['rel']['Empresa']['ID']){
 //usuari treballant a la mateixa empresa
	$s['rel']['Empresa']['prefixrelacio']=$prefixrelacio;
	
}
if($s['Empresa']['ID']!=$s['rel']['Empresa']['ID']){
 //usuari treballant a una altra empresa
	$s['rel']['Empresa']['prefixrelacio']=date('Y')."_Empresa_"."{$s['rel']['Empresa']['ID']}".'_';
}
if($s['w']>800)$fontsize=24;
else $fontsize=12;
$fontsize=$fontsize+12;
if($s['w']>800){
	$wuni=70;
	$wbulk=65;
	$wlot=180;
	$wcost=90;
	$wiva=80;
	$wactiva=50;
	$wtotal=130;
	$wdiscount=50;
	$wdes=$s['w']-$wuni-$wbulk-$wlot-$wcost-$wiva-$wactiva-$wtotal-$wdiscount-100;
}
else{
	$wuni=($s['w']/2)-15;
	$wbulk=($s['w']/2)-15;
	$wdes=($s['w']/2)-15;
	$wlot=($s['w']/2)-15;
	$wiva=($s['w']/5)-15;
	$wactiva=($s['w']/5)-15;
	$wcost=($s['w']/5)-15;
	$wdiscount=($s['w']/5)-15;
	$wtotal=($s['w']-$wactiva-$wcost-$wiva-$wdiscount-30);
}
$extracss="
.linebox {
    position:relative;
font-family: Century Gothic,CenturyGothic,AppleGothic,sans-serif; 
color: #ffffff; 
    top:0px;
    left:0px;
width:100%;
/*height:100%;*/
font-size: 18px; 
/* font-weight: 16px;  */
text-align:  center; 
background: #889ccf; 
margin:0px; 
overflow: hidden; 
padding: 0px; 
/*border-radius: 35px 0px 35px 0px; 
-moz-border-radius: 35px 0px 35px 0px; 
-webkit-border-radius: 35px 0px 35px 0px; 
border: 0px solid #5878ca;*/
}
.rellinea {text-align: center;
background: #889ccf;
	width:100%; display: inline-block;
}

";
estil_capcal(false,"./",true,false,$extracss);
if(isset($s['do'])){
	$r['do']=$s['do'];
	unset($s['do']);
}
switch ($_REQUEST['do']){
case 'new':
	form();
	//debugtelegram($s);
	$tipus_relacio=mysql2array("SELECT * FROM `tipus_relacio` where `ID`!='1'",$link);
	//$_SESSION['llista']['tipus_relacio']=$tipus_relacio;
	select2llistes_js("ID_tipus_relacio",$tipus_relacio[0],$tipus_relacio[1],$_REQUEST['ID_tipus_relacio']?$_REQUEST['ID_tipus_relacio']:1,
		"no",false,
		"style=\"vertical-align:unset; font-size: {$fontsize}px;  text-align: left;  height:{$height}px;\"");
	hidden('do','busca_client');
	tform();
	break;
case 'busca_client':
	//$s['do']='tria_client';
	form();
	hidden('do','tria_client');
	?>
		<input type="search" name="tria_client" size="13" id="magic" placeholder="Nom o &#128241;" class="form-control"  autofocus autocomplete="off" onFocus="input.focus();" onChange="this.form.submit()"/><?php
	br();
	hidden('ID_tipus_relacio',$r['ID_tipus_relacio']);//tria_venedor();
	tform();
	 break;
case 'tria_client':
	if ($r['tria_client']==''){
		$link->close();
		header("Location: {$s[$app]['uri']}?do=busca_client&ID_tipus_relacio={$s['ID_tipus_relacio']}");
		die;
	}
	$tria_telefon=str_replace(" ","",$r['tria_client']);
	$IDxtelefon=mysql1r("select ID_Client from Clients_phone WHERE `Value`='{$tria_telefon}'",$link);
	if ($IDxtelefon!=""){
		$link->close();
		header("Location: {$s[$app]['uri']}?do=crea_index&ID_Client={$IDxtelefon}&ID_tipus_relacio={$r['ID_tipus_relacio']}");
		die;
	}
	$tria_client=str_replace(" ","%",$r['tria_client']);
	$search=mysql2array("select ID,Value from Clients where `Value` like '%$tria_client%'",$link);
	if (!isset($search)){
		$link->close();
		header("Location: {$s[$app]['uri']}?do=busca_client&ID_tipus_relacio={$r['ID_tipus_relacio']}&noresult=yes");
		die;
	}
	if(count($search['0'])==1){
		$link->close();
		header("Location: {$s[$app]['uri']}?do=crea_index&ID_Client={$search['0']['0']}&ID_tipus_relacio={$r['ID_tipus_relacio']}");
		die;
	}
	$se[0][]=0;
	$se[1][]= "Tria entre: ".(count($search['0']))." Clients";
	foreach ($search[0] as $k=>$v){
		$se[0][]=$v;
		$se[1][]=$search[1][$k];
	}
	form();
	select2llistes_js('ID_Client',$se[0],$se[1],0,'yes',true,"style=\"vertical-align:unset; font-size: {$fontsize}px;  text-align: left;  Width:".($s['w']-5)."px;\"");
	br();
	hidden('do','crea_index');
	hidden('ID_tipus_relacio',$r['ID_tipus_relacio']);//tria_venedor();
	tform();
	break;
case 'crea_index':
	// Assegurar que tenim year i ID_Empresa
	$year = isset($r['year']) && $r['year'] ? $r['year'] : date('Y');
	$ID_Empresa = $s['rel']['Empresa']['ID'];
	
	$year_prefix = "{$year}_Empresa_{$ID_Empresa}_";
	if(!mysql1r("SHOW TABLES LIKE '{$year_prefix}index_relacio' ;",$link)){
		$link->query("CREATE TABLE `{$year_prefix}index_relacio` LIKE `meta_index_relacio`;");
	}
	if(!mysql1r("SHOW TABLES LIKE '{$year_prefix}line';",$link)){
		$link->query("CREATE TABLE `{$year_prefix}line` LIKE `meta_line`;");
	}
	if(!$ID_relacio){
		$new=true;
		$ID_relacio=mysql1r("select MAX(ID)+1 from `{$year_prefix}index_relacio` where `ID_tipus_relacio`='{$r['ID_tipus_relacio']}'",$link);
	}
	if(!$ID_relacio){//aixo només pasa un cop l'any o per instalació nova se suposa
		$ID_relacio=1;
		$new=true;
	}
	if($new){//creo referencia al index_relacio
		$cl=$link->query("Select * from Clients where `ID`='{$r['ID_Client']}'")->fetch_assoc();
		//pre($cl);
		$m2['table']=$year_prefix.'index_relacio';
		$m2['ID']=$ID_relacio;
		$m2['do']='new';
		$d['ID_Venedor']=0;
		$d['ID_client']=$cl['ID'];
		$d['ID_tipus_relacio']=$r['ID_tipus_relacio'];
		$d['fiscal_name']=urldecode($cl['Value']);
		$d['tax_detall']=$cl['bool_tax_detall'];
		$d['equivalency_charge']=$cl['bool_equivalency_charge'];
		$d['NIF']=$cl['NIF'];
		$d['relacio_oberta']='0';
		$d['editable']='1';  // Sempre editable quan es crea
		$d['fiscal_address']=urldecode($cl['fiscal_address']);
		$d['Poblacio']=urldecode($cl['Poblacio']);
		$d['comercial_name']=$cl['comercial_name'];
		$d['deliver_address']=$cl['deliver_address'];
		$d['phone']=mysql1r("select `Value` from `Clients_phone` where `ID_Client`='{$cl['ID']}'",$link);
		$d['email']=$cl['email'];
		$d['data']['mday']=$r['mday']?$r['mday']:date("j");
		$d['data']['mon']=$r['mon']?$r['mon']:date("n");
		$d['data']['year']=$r['year']?$r['year']:date("Y");
		$d['uuid']=uniqid($ID_Empresa);
		$m2[$m2['table']]=$d;
		//pre($m2);
		new_update($m2,$link);//,true);
	}
	$link->close();
	if($r['return']==='rel2rel') header("Location: {$s[$app]['uri']}?do=rel2rel&ID={$ID_relacio}&uuid={$d['uuid']}&ID_Empresa={$ID_Empresa}&year={$year}&mday={$r['mday']}&mon={$r['mon']}&ID_client={$r['ID_Client']}&ID_tipus_relacio={$r['old']}&ID_tipus_relacio_new={$r['ID_tipus_relacio']}&relinc={$r['relinc']}");
	else header("Location: {$s[$app]['uri']}?do=edit&uuid={$d['uuid']}&ID_Empresa={$ID_Empresa}&year={$year}");
	die;
	break;
case 'update_payment': //r =$_REQUEST i $s=$_SESSION
	$s2=($_SESSION['update_payment']);
	$u="UPDATE `{$r['year']}_Empresa_{$s2['Empresa']['ID']}_index_relacio` SET `ID_Pagament`='{$r['viapagament']}', `relacio_oberta`='0' WHERE `ID`='{$s2['ID']}' AND `ID_tipus_relacio`='{$s2['ID_tipus_relacio']}'";
	if(($s2['tipus_relacio']['bool_allow_payment']=="0")&&($r['viapagament']=='1'))
		//echo "<script>window.open('', '_self', ''); window.close();</script>";
		header("Location: {$s[$app]['uri']}?do=edit&uuid={$s2['uuid']}&ID_Empresa={$s2['Empresa']['ID']}&year={$r['year']}");
	else{
		$link->query($u)or die(ar("{$s[$app]['uri']}?do=edit&uuid={$s['update_payment']['uuid']}&ID_Empresa={$s['update_payment']['ID_Empresa']}&year={$r['year']}","your data can not fit in database<br/>".$link->error));
		echo "<script>window.open('', '_self', ''); window.close();</script>";
	}
	break;
case 'pdf':
	//echo "printing?";
	if($_REQUEST['uuid']==''){$link->close(); header("{$s[$app]['uri']}");}
	if(isset($_REQUEST['ID_Empresa']))$ID_Empresa=$_REQUEST['ID_Empresa'];
	if(isset($_REQUEST['year']))$year=$_REQUEST['year'];
	else $year=date("Y");
	$prefixrelacio="{$year}_Empresa_{$ID_Empresa}_";
	$rel=detallrelacio($_REQUEST['uuid'],$ID_Empresa,$year,$link);
	if(!correuvalid($rel['email']))$rel['email']=urldecode($rel['email']);
	pdf($rel,$prefixrelacio);
	//pre($r);
	echo "<script>window.open('', '_self', ''); window.close();</script>";
break;
case 'edit':
	// Assegurar que tenim ID_Empresa i year
	$uuid = $_REQUEST['uuid'];
	$ID_Empresa = isset($_REQUEST['ID_Empresa']) ? $_REQUEST['ID_Empresa'] : $s['rel']['Empresa']['ID'];
	$year = isset($_REQUEST['year']) ? $_REQUEST['year'] : date('Y');
	
	// Redirigir al nou edit.php AJAX
	header("Location: edit.php?uuid={$uuid}&ID_Empresa={$ID_Empresa}&year={$year}");
	exit();
	break;
	unset($_SESSION['update_payment']);
	//borro el pdf i el registre de pdf impresos, ja que si edito ja no val
	exec(localcmd("
		rm {$_SERVER['DOCUMENT_ROOT']}/pdf/{$_REQUEST['uuid']}.pdf;
	"));
	if (($link->query("SHOW TABLES LIKE ' {$_REQUEST['year']}_Empresa_{$s['rel']['Empresa']['ID']}_pdf'"))->num_rows===1){
		$q="DELETE FROM {$_REQUEST['year']}_Empresa_{$s['rel']['Empresa']['ID']}_pdf WHERE `uuid`='{$_REQUEST['uuid']}';";
		$link->query($q);
	}
	$rel=detallrelacio($_REQUEST['uuid'],$s['rel']['Empresa']['ID'],$_REQUEST['year'],$link);
	if ($rel['editable']==1){
	//function asigna_relacio_per_telefon($r,$tel,$ID_Empresa,$year,$link,$debug=false);
	//asigna_relacio_per_telefon($rel,$rel['phone'],$s['ID_Empresa'],$_REQUEST['year'],$link);
	form();
	hidden('do',"newline");
	hidden('uuid',$r['uuid']);
	hidden('year',$r['year']);
	?>	<input type="search" name="newline"  id="magic" placeholder="busca producte o (codi o +preu directe (*/x unitats(+/l lotnumber(+/d caducitat))))" class="form-control" autofocus autocomplete="off" onChange="this.form.submit()" style="font-size: <?=$fontsize?>px;  text-align: left;  Width:100%;"/><?php
	br();
	tform();
	if($rel['activelines']>0 || $rel['deletedlines']>0){
		divclass("linebox");
		if($rel['activelines']>0){
			$b=0;
			for($i=$rel['activelines']-1;$i>=0;$i--){
				//echo "edito ";br();
				$line=$rel['active_lines'][$i];
				$b++;
				relacionsformlinea($rel,$line,$b);
			}
		}
		if($rel['deletedlines']>0){
			$b=0;
			for($i=$rel['deletedlines']-1;$i>=0;$i--){
				//echo "edito ";br();
				$line=$rel['deleted_lines'][$i];
				relacionsformlinea($rel,$line,$b++);
			}
		}
		tdiv();
	}
	//echo "En aquesta relació {$rel['tipus_relacio']['Value']} hi ha {$rel['activelines']} linees actives";
	form_index($rel);
	//pre($s);

	}//if editable	
	else {
		echo "<h2>Aquesta relació no és editable</h2>";
		echo "<p>UUID: {$_REQUEST['uuid']} - Empresa: {$s['rel']['Empresa']['ID']} - Any: {$_REQUEST['year']}</p>";
		echo "<p><a href='./'>Torna a la llista</a></p>";
	}
	break;
case 'updateline':
	$rel=detallrelacio($_REQUEST['uuid'],$s['rel']['Empresa']['ID'],$_REQUEST['year'],$link);
	$val=urlencode(trim($r['description']));
	$units=str_replace(' ','',str_replace(',','.',trim($r['units'])));//trec espais del numero i canvio la coma per el punt per poder entrar a la base de dades
	if(!is_numeric($units)){ 
		a("{$s[$app]['uri']}?do=edit&uuid={$r['uuid']}&ID_Empresa={$s['rel']['Empresa']['ID']}&year={$r['year']}",errorr("\"{$r['units']}\" no és un valor vàlid per les unitats"));
		$link->close();
		die;
	}
	$discount=str_replace(' ','',str_replace(',','.',trim($r['discount'])));
	//debugtelegram($discount);
	if($discount=="")$discount=0;
	if(!is_numeric($discount)){ 
		a("{$s[$app]['uri']}?do=edit&uuid={$r['uuid']}&ID_Empresa={$s['rel']['Empresa']['ID']}&year={$r['year']}",errorr("\"{$r['discount']}\" no és un valor vàlid per el descompte"));
		$link->close();
		die;
	}
	//necesitem el iva antic per calcular el preu 
	$iva=mysql1r("SELECT `IVA` from `{$r['year']}_Empresa_{$s['rel']['Empresa']['ID']}_line` WHERE `ID` = '{$rel['ID']}' AND `ID_tipus_relacio` = '{$rel['ID_tipus_relacio']}' AND `line` = '{$r['line']}';" ,$link);
	$cost=str_replace(',','.',trim($r['cost']));
	if(!is_numeric($cost)){ 
		a("{$s[$app]['uri']}?do=edit&uuid={$r['uuid']}&ID_Empresa={$s['rel']['Empresa']['ID']}&year={$r['year']}",errorr("\"{$r['cost']}\" no és un valor vàlid per el preu"));
		$link->close();
		die;
	}
	if($rel['tax_detall']==0){
		$cost=$cost/(1+($iva/100));
	}
	$iva=mysql1r("SELECT `Value` from `IVA` where `ID`='{$r['IVA']}' " ,$link);//aqui poso el tipus d'iva nou
	if($rel['equivalency_charge']==1){
		$eq=mysql1r("SELECT `TAX` from `equivalency_charge` where `ID_iva`='{$r['IVA']}' " ,$link);
	}
	else $eq=0;
	$lot=urlencode(trim($r['lotnumber']));
	$u="UPDATE `{$r['year']}_Empresa_{$s['rel']['Empresa']['ID']}_line` SET `description` = '$val',`units` = '$units',`bulk` = '{$r['bulk']}',`cost` = '$cost',`IVA` = '$iva',`discount`='$discount',`EQUIVALENCY_CHARGE` = '$eq',`hidden_now` = now(),`lotnumber` = '$lot',`deleted_line` = '{$r['deleted_line']}'
WHERE `ID` = '{$rel['ID']}' AND `ID_tipus_relacio` = '{$rel['ID_tipus_relacio']}' AND `line` = '{$r['line']}';";
	//a("{$s[$app]['uri']}?do=edit&uuid={$r['uuid']}&ID_Empresa={$s['ID_Empresa']}&year={$r['year']}",errorr($u));
	//die;
	$link->query($u)or die(ar("{$s[$app]['uri']}?do=edit&uuid={$r['uuid']}&ID_Empresa={$s['rel']['Empresa']['ID']}&year={$r['year']}","your data can not fit in database<br/>".$link->error));

	header("Location: {$s[$app]['uri']}?do=edit&uuid={$r['uuid']}&ID_Empresa={$s['rel']['Empresa']['ID']}&year={$r['year']}");
	break;
case 'newline':
	$rel=detallrelacio($_REQUEST['uuid'],$s['rel']['Empresa']['ID'],$_REQUEST['year'],$link);
	$line['do']="relacions no ajax";
	$line['line']=$_REQUEST['newline'];
	//$newline=newline_extract($line,$rel,$link);
	//tot aixo ho substituirà el newline_extract/*
	 //quan hem rebut algo al magic
  	//debugtelegram($_REQUEST);
	//debugtelegram($rel);
	//pre($rel);
  	//die;
	$ID=trim($_REQUEST['newline']);
	if($ID['0']=='-'){
		$ID=substr($ID,1);
		$abono='-';
	}
	if(is_numeric($ID[0])){//el primer caracter es un número, per tant un codi
				//afegeixo d com a marca de datacaducitat 11/04/2023
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

	}
	//no es un numeral, per tant nous casos apareixen... codis de barres, els que comencen amb +/*- ...
	//debugtelegram("id".$ID);
	//die;
	if (preg_match('/^\d+\.\d+$/', $ID)){
		//package
		//debugtelegram($rel,"relacio");
		//debugtelegram($s,"sessio");
		//debugtelegram($r,"request");
		list($ID,$package_type)=explode('.',$ID);
		$package=$link->query("SELECT * from package_format where `ID`=$ID AND `ID_package_type`=$package_type AND  `hidden_date_time` = (  SELECT MAX(`hidden_date_time`) from package_format where `ID` ='{$ID}' AND `ID_package_type`='{$package_type}')")->fetch_assoc();
		if(!is_array($package)){header("Location: {$s[$app]['uri']}/?do=edit&uuid={$r['uuid']}&{$r['ID_Empresa']}&year={$r['year']}"); die;}
		$product=$link->query("SELECT * from tarifes where `ID`=$ID AND `ultimpreu`=1")->fetch_assoc();
		//$package_pvp=$link->query("SELECT * from package_pvp where `ID`=$IDproduct AND `ID_package_type`=$package_type")->fetch_assoc;
		//if(isset($package_pvp))
		$product['pvp']=package_pvp($product,$package,$link);//retorna falç si no troba un preu sobreescrit
		if(!$product['pvp']){
			//debugtelegram($package);\
			//debugtelegram($units);
			if($units>0)$units=($package['weight']/1000)*$units;
			else $units=$package['weight']/1000;
			$product['pvp']=mysql1r("Select pvp from scalelist where ID=$ID",$link);
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
		$tipusIVA=$product['tipusiva'];
	}
	else switch ($ID){//no package
		case '':
			$link->close();
			//echo 'cas buit';die;
			header("Location: {$s[$app]['uri']}?do=edit&uuid={$r['uuid']}&ID_Empresa={$s['rel']['Empresa']['ID']}&year={$r['year']}");
		break;
		case ((strlen($ID)==13)&&($ID[0]==2)):
			echo 'codi barres legacy';
			if (legacyscale($ID)==1){
				legacyinsert($ID);
			}
			$link->close();
			header("Location: {$s[$app]['uri']}?do=edit&uuid={$r['uuid']}&ID_Empresa={$s['rel']['Empresa']['ID']}&year={$r['year']}");
			die;
		break;
		case ((is_numeric($ID))&&(strlen($ID)>8)&&($ID[0]!=2))://codi de barres, no tots tenen 13 de longitut, per exemple el oli de coco cuinar
			//echo "ean?";
			$ID=mysql1r("SELECT ID from ean where Value='{$ID}'",$link);
			$link->close();
			header("Location: {$s[$app]['uri']}/?do=newline&magic=$ID&ID_Empresa={$s['rel']['Empresa']['ID']}&year={$r['year']}");
		break;
		case is_numeric($ID[0]):
			$sql="select exist,value, formatpvp, bulk, pvp,cost from scalelist where ID='$ID' ";
 			//echo $sql;br();die;
			$o=$link->query($sql) or die(header("Location: {$s[$app]['uri']}"));
			$o=mysql2vector($sql,$link);
			$tarifa=mysql1r("Select `id_tarifa` from `Clients` where `ID`='{$rel['ID_client']}' ",$link);
			$tarifa=mysql1r("Select `column_name` from `tarifa` where `ID`='$tarifa' ",$link);
			$cost="sense iva";
			$o[4]=mysql1r("SELECT `{$tarifa}` from `tarifes` where `ID`='$ID' and `ultimpreu`='1'",$link);
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
					$link->close();
					//debugtelegram("bad code a newline");
					header("Location: {$s[$app]['uri']}?do=edit&uuid={$r['uuid']}&ID_Empresa={$s['rel']['Empresa']['ID']}&year={$r['year']}");
				}
				//Aqui sabem que ja podrem fer el el formulari per entrar la línea a algun venedor //aixo no aplica a relacions, ve que vaig copiar el codi de scale
				break;
			case (($ID[0]=='+')&&(is_numeric($ID[1])))://preu directe
				unset ($units);
				//debugtelegram($ID);
				if(strpos($ID,'*'))list($o[4],$units)=explode('*',substr($ID,1));//$o[4]=pvp
				else list($o[4],$altres)=explode('/',substr($ID,1));
				$ID=0;
				if (is_numeric($units)){
				 //preu directe unitats contades
					$units=$abono.$units;
					$bulk=0;
					$o[3]=0;//$o[3]=boolean_bulk
				}
				if(!is_numeric($units)){
					//preu directe unitats granel
					$o[3]=1;
					$units=0;
					$bulk=1;
		    		}
				if($IVA=='')$IVA=$ivageneric; //$ivageneric=10 a vars.php
				$tipusIVA=mysql1r("SELECT `ID` FROM `IVA` WHERE `Value` = '$IVA'",$link);				
				$o[1]="ARTICLE GENERIC AMB $IVA% d'IVA";
				$o['5']=round($o['4']/1.83,2);
			break;
			case (!is_numeric($ID[0])):
				//es un text?
				$cerca=str_replace(" ","%",$ID);
				form();
				hidden("do",'newline');
				hidden("uuid",$r['uuid']);
				hidden("year",$r['year']);
				$list=mysql2array("select id,concat('[',id,'] ',value) as conc from scalelist where `value` like '%$cerca%' Order by `value`",$link);
				//pre($list);
				if(!is_Array($list)){
					$link->close();
					a("{$s[$app]['uri']}?do=edit&uuid={$r['uuid']}&ID_Empresa={$s['rel']['Empresa']['ID']}&year={$r['year']}",errorr("\"{$ID}\" no ha retornat cap producte"));

					die;
				}
				$list[0][]=0;
				$list[1][]="Tria d'entre els resultats";
				//pre($list);
				select2llistes_js('newline',$list[0],$list[1],0,false,false,"style=\"font-size: {$fontsize}px; text-align: left;  Width:99%;\"");
				tform();
				$link->close();
				die;
				break;
	}	//final switch id de case tracto*/
	$line["do"]="new";
	$prefixrelacio="{$r['year']}_Empresa_{$s['rel']['Empresa']['ID']}_";
	$line['table']="{$prefixrelacio}line";//"{$r['year']}_Empresa_{$s['rel']['Empresa']['ID']}_line";
	$line['ID']=$rel['ID'];
	$m["ID_product"]=$ID;
	$m["description"]=$o[1];
	if($expirationdate!="")$m["description"].=" Consumir abans: {$expirationdate}";
	$m["units"]=$units;
	//TODO cost ha de ser el de la tarifa que li toqui al client si units=>1 i també als updates
	if(isset($cost))$m['cost']=$o['4'];
	else $m["cost"]=$o[4]/(($IVA/100)+1);
	unset($cost);
	$m["cost_price"]=$o['5'];
	$m["lotnumber"]=$lotnumber;
	$m["IVA"]=$IVA;
	if($rel['equivalency_charge']=='1'){
		$m['EQUIVALENCY_CHARGE']=mysql1r("SELECT `TAX` from equivalency_charge where ID_iva=$tipusIVA",$link);
	}
	$m['lotnumber']=$lotnumber;
	$m["bulk"]=$bulk;// aqui acaba lo que comento i substitueix el //newline_extract*/
	$line["{$prefixrelacio}line"]=$newline;//new amb newline_extract
	$line["{$prefixrelacio}line"]=$m;//antic mentre no tinc el newline_extract
	$line["{$prefixrelacio}line"]['ID_tipus_relacio']=$rel['ID_tipus_relacio'];
	$line["{$prefixrelacio}line"]["line"]=mysql1r("Select max(line)+1 from `{$prefixrelacio}line` 
		where ID={$rel['ID']} and ID_tipus_relacio='{$rel["ID_tipus_relacio"]}'",$link,true);
	if ($line["{$prefixrelacio}line"]["line"]=='')$line["{$prefixrelacio}line"]["line"]=1;
	new_update($line,$link);
	//debugtelegram($line);
	header("Location: {$s[$app]['uri']}?do=edit&uuid={$rel['uuid']}&ID_Empresa={$s['rel']['Empresa']['ID']}&year={$r['year']}");
	//hidden("m",$m);
	//pre($m);
	//}$v[0]=array(0,1,2);
				$v[1]=array("Tria","Continua editant {$r['tipus_relacio']['Value']}","Deixa de editar {$r['tipus_relacio']['Value']} ");
	break;//final case newline

	/////////////////////////////////////////////////////// rel2rel
	
	case "rel2rel":
		if (($link->query("SHOW TABLES LIKE ' {$_REQUEST['year']}_Empresa_{$s['Empresa']['ID']}_pdf'"))->num_rows===1){
			$q="DELETE FROM {$_REQUEST['year']}_Empresa_{$s['rel']['Empresa']['ID']}_pdf WHERE `uuid`='{$_REQUEST['uuid']}';";
			$link->query($q);
		}
		//pre($s);
		if(isset($r['uuid'])){
			$rel=detallrelacio($r['uuid'],$s['Empresa']['ID'],$r['year'],$link);
		}
		if(isset($r['uuid']) && isset($r['viapagament'])&& ($r['viapagament']!=$rel['ID_Pagament'])){
			$sql="UPDATE `{$r['year']}_Empresa_{$s['rel']['Empresa']['ID']}_index_relacio` SET `ID_Pagament`='{$r['viapagament']}'where `ID`='{$rel['ID']}' AND `ID_tipus_relacio`='{$rel['ID_tipus_relacio']}'";
			$link->query($sql);
			$rel=detallrelacio($r['uuid'],$s['Empresa']['ID'],$r['year'],$link);			
		}
		if (($link->query("SHOW TABLES LIKE '{$r['year']}_Empresa_{$s['rel']['Empresa']['ID']}_rel2rel'"))->num_rows===0){
			try{//creo taula
				if(!$link->query("CREATE TABLE `{$r['year']}_Empresa_{$s['rel']['Empresa']['ID']}_rel2rel`(`ID` int unsigned NOT NULL, `ID_tipus_relacio` int(11) NOT NULL,`ID_inclosa` int unsigned NOT NULL,`ID_tipus_relacio_inclosa` int(11) NOT NULL, FOREIGN KEY (`ID_tipus_relacio`) REFERENCES `tipus_relacio` (`ID`), FOREIGN KEY (`ID_tipus_relacio_inclosa`) REFERENCES `tipus_relacio` (`ID`))"))
					throw new Exception("No he pogut crear la taula: " . $link->error);
			}catch (Exception $e){  echo $e->getMessage();die;	}
			$link->query("ALTER TABLE `{$r['year']}_Empresa_{$s['rel']['Empresa']['ID']}_rel2rel`
ADD UNIQUE `ID_ID_tipus_relacio_ID_inclosa_ID_tipus_relacio_inclosa` (`ID`, `ID_tipus_relacio`, `ID_inclosa`, `ID_tipus_relacio_inclosa`);");
		}
		if($r["relinc"]){
			if(!isset($r['ID'])){
				//creo index_relació
				header("Location: {$s[$app]['uri']}/?do=crea_index&return=rel2rel&relinc={$r['relinc']}&ID_Client={$r['ID_client']}&mday={$r['mday']}&mon={$r['mon']}&year={$r['year']}&ID_tipus_relacio={$r['ID_tipus_relacio_new']}&old={$r['ID_tipus_relacio']}");
			}
			$rel2rel_prefix = "{$r['year']}_Empresa_{$s['rel']['Empresa']['ID']}_";
			$sql="insert into `{$rel2rel_prefix}rel2rel` (`ID`,`ID_tipus_relacio`,`ID_inclosa`,`ID_tipus_relacio_inclosa`) VALUES ('{$r['ID']}','{$r['ID_tipus_relacio_new']}','{$r['relinc']}','{$r['ID_tipus_relacio']}')";	
			$wasthere="select * from `{$rel2rel_prefix}rel2rel` where `ID_inclosa`='{$r['relinc']}' AND `ID_tipus_relacio_inclosa`='{$r['ID_tipus_relacio']}'";
			//echo $wasthere;
			$r2r=$link->query($wasthere);
			if($r2r->num_rows===0) $link->query($sql);
		}
		divclass('fulla',"style=\" text-align: center; background: #889ccf; width:98%; padding:1%; display: inline-block; \"");h3("Això crea una nova relació amb relacions a dintre a la data escollida");
		form();

		hidden("do","rel2rel");
		if(!isset($r['uuid'])){
			// Si ja hi ha un client seleccionat, mostrem el nom
			if(isset($r['ID_client']) && $r['ID_client'] > 0){
				$client_nom = urldecode(mysql1r("SELECT `Value` FROM Clients WHERE ID='{$r['ID_client']}'",$link));
				echo '<div style="display: inline-block; margin: 5px; vertical-align: top;">';
				echo '<strong style="color: #fff;">Client: '.$client_nom.'</strong> ';
				echo '<a href="?do=rel2rel" style="color: #fff; text-decoration: underline; font-size: 12px;">(canviar)</a>';
				echo '<input type="hidden" name="ID_client" value="'.$r['ID_client'].'" />';
				echo '</div>';
			} else {
				echo '<div style="display: inline-block; margin: 5px; vertical-align: top; position: relative;">';
				echo '<input type="text" id="client_search" placeholder="Cerca client..." style="padding: 5px; width: 250px; font-size: 14px;" autocomplete="off" autofocus />';
				echo '<input type="hidden" name="ID_client" id="ID_client" value="" />';
				echo '<div id="client_results" style="border: 1px solid #ccc; max-height: 200px; overflow-y: auto; display: none; background: white; position: absolute; z-index: 1000; width: 250px; left: 0;"></div>';
				echo '</div>';
			}
			echo '<script>
			const searchInput = document.getElementById("client_search");
			if(searchInput) {
				const resultsDiv = document.getElementById("client_results");
				const hiddenInput = document.getElementById("ID_client");
				let selectedClientName = "";
				
				searchInput.addEventListener("input", function() {
					const search = this.value;
					if(search.length < 1) {
						resultsDiv.style.display = "none";
						return;
					}
					
					fetch("ajax_clients.php?search=" + encodeURIComponent(search))
						.then(r => r.json())
						.then(data => {
							if(data.length === 0) {
								resultsDiv.innerHTML = "<div style=\\"padding: 5px; color: #666;\\">Cap client trobat</div>";
								resultsDiv.style.display = "block";
							} else if(data.length === 1) {
								hiddenInput.value = data[0].id;
								selectedClientName = data[0].name;
								searchInput.value = data[0].name;
								resultsDiv.style.display = "none";
								// Saltar al següent camp
								const nextInput = searchInput.closest("form").querySelector("select");
								if(nextInput) nextInput.focus();
							} else {
								resultsDiv.innerHTML = "";
								data.forEach(client => {
									const div = document.createElement("div");
									div.style.padding = "5px";
									div.style.cursor = "pointer";
									div.style.color = "#000";
									div.textContent = client.name;
									div.addEventListener("mouseenter", () => div.style.background = "#eee");
									div.addEventListener("mouseleave", () => div.style.background = "white");
									div.addEventListener("click", () => {
										hiddenInput.value = client.id;
										searchInput.value = client.name;
										selectedClientName = client.name;
										resultsDiv.style.display = "none";
										// Saltar al següent camp
										const nextInput = searchInput.closest("form").querySelector("select");
										if(nextInput) nextInput.focus();
									});
									resultsDiv.appendChild(div);
								});
								resultsDiv.style.display = "block";
							}
						});
				});
				
				searchInput.addEventListener("blur", function() {
					setTimeout(() => resultsDiv.style.display = "none", 200);
					if(selectedClientName) {
						searchInput.value = selectedClientName;
					}
				});
				
				document.addEventListener("click", function(e) {
					if(e.target !== searchInput && e.target !== resultsDiv) {
						resultsDiv.style.display = "none";
					}
				});
			}
			</script>';
			$relt=mysql2array("select `ID`, `Value` from `tipus_relacio` where `ID`!='1'",$link);
			$relt[1][0]="Quin tipus relació creem?";
			// Autofocus al primer select si no tenim client encara
			$autofocus_new = (!isset($r['ID_client']) || $r['ID_client'] <= 0);
			select2llistes_js("ID_tipus_relacio_new",$relt[0],$relt[1],$r['ID_tipus_relacio_new'],'no',$autofocus_new);
			$relt=mysql2array("select `ID`, `Value` from `tipus_relacio` where `ID`!='1'AND `ID`!='3'",$link);
			$relt[1][0]="Quin tipus relació incluim?";
			// Autofocus al segon select si ja tenim client i tipus_relacio_new, però no tipus_relacio encara
			$autofocus_inc = (isset($r['ID_client']) && $r['ID_client'] > 0 && 
			                  isset($r['ID_tipus_relacio_new']) && $r['ID_tipus_relacio_new'] > 0 &&
			                  (!isset($r['ID_tipus_relacio']) || $r['ID_tipus_relacio'] <= 0));
			select2llistes_js("ID_tipus_relacio",$relt[0],$relt[1],$r['ID_tipus_relacio'],'no',$autofocus_inc);
		}
		else {
			hidden("ID_client",$r['ID_client']);
			hidden("ID",$r['ID']);
			hidden("uuid",$r['uuid']);
			hidden("ID_tipus_relacio_new",$r['ID_tipus_relacio_new']);
			hidden("ID_tipus_relacio",$r['ID_tipus_relacio']);
		}
		
		// Inicialitzar sessió rel2rel només si entrem sense paràmetres (només ?do=rel2rel)
		$params_count = count($_GET);
		$only_do = ($params_count == 1 && isset($_GET['do']));
		
		if($only_do){
			// Nova instància, reiniciem els valors de sessió
			$s['rel2rel']['mday'] = null;
			$s['rel2rel']['mon'] = null;
			$s['rel2rel']['year'] = null;
		}
		
		// Gestió de l'autofocus per les dates
		$autofocus_mday = false;
		$autofocus_mon = false;
		$autofocus_year = false;
		
		// Autofocus a mday si tenim ID_tipus_relacio seleccionat
		if(isset($r['ID_tipus_relacio']) && $r['ID_tipus_relacio'] > 0){
			// Si mday ha canviat, guardem-lo i posem autofocus a mon
			if(isset($r['mday']) && isset($s['rel2rel']['mday']) && $r['mday'] != $s['rel2rel']['mday']){
				$s['rel2rel']['mday'] = $r['mday'];
				$autofocus_mon = true;
			}
			// Si mon ha canviat, guardem-lo i posem autofocus a year
			elseif(isset($r['mon']) && isset($s['rel2rel']['mon']) && $r['mon'] != $s['rel2rel']['mon']){
				$s['rel2rel']['mon'] = $r['mon'];
				$autofocus_year = true;
			}
			// Sinó, autofocus a mday
			else {
				if(!isset($s['rel2rel']['mday'])){
					$s['rel2rel']['mday'] = $r['mday'] ?? date('j');
				}
				if(!isset($s['rel2rel']['mon'])){
					$s['rel2rel']['mon'] = $r['mon'] ?? date('n');
				}
				$autofocus_mday = true;
			}
		}
		
		$nyear[date('Y')-1]=date('Y')-1;
		$nyear[date('Y')]=date('Y');
		for($i=1;$i<=12;$i++)$nmesos[$i]=$i;
		for($i=1;$i<=31;$i++)$ndies[$i]=$i;
		$llistamesos=array("","gener","febrer","mar&ccedil;","abril","maig","juny","juliol","agost","setembre","octubre","novembre","desembre");
		//number_js("diainici",1,31,1,$_REQUEST['mday']?$_REQUEST['mday']:date('D'));
		if(!isset($r['uuid'])){
			select1llista_js("mday",$ndies,$r['mday']?$r['mday']:date('j'),$autofocus_mday,false);
			select2llistes_js("mon",$nmesos,$llistamesos,$r['mon']?$r['mon']:date("n"),"no",$autofocus_mon);
			select1llista_js("year",$nyear,$r['year']?$r['year']:date('Y'),$autofocus_year,false);
		}
		if(isset($r['uuid'])){
			$rel=detallrelacio($r['uuid'],$s['Empresa']['ID'],$r['year'],$link);
			if($rel['tipus_relacio']['bool_allow_payment']=="1"){
				$viap=mysql2array("SELECT `ID`, `Value` FROM `Pagament` WHERE `ID` <= '10'",$link);//factura, proforma o bascula heredada
				select2llistes_js('viapagament',$viap[0],$viap[1],$rel['ID_Pagament'],'no',false);
				hidden("mday",$r['mday']);
				hidden("mon",$r['mon']);
				hidden("year",$r['year']);
			}
		}	
		//tform();
		//form();
		//hidden("do","rel2rel");
		//hidden("ID_client",$r['ID_client']);
		//hidden("ID_tipus_relacio_new",$r['ID_tipus_relacio_new']);
		//hidden("ID_tipus_relacio",$r['ID_tipus_relacio']);

		if (isset($r['ID']))hidden("ID",$r['ID']);
		if(isset($r['uuid']))hidden("uuid",$r['uuid']);
		$r['anyinici']=$r['year']?$r['year']:date("Y");
		$r['diainici']="01";
		$r['mesinici']='01';
		$r['diafi']="31";
		$r['mesfi']='12';
		$r['ID_client'];
		//echo  $r['anyinici'];
		//debugtelegram($s,"rel2rel 631");
		//pre($s['rel']['Empresa']['ID']);
		llistarelacions($s['rel']['Empresa'],$link,"rel2rel");
		tform();
	//	tdiv();tdiv();
		if(isset($r['uuid']))echo html_relacio($rel,false);
	//	pre($rel);
		break;


		///////////////////////////////////////////////// default:
default:
	$_SESSION['Empresa']['prefixrelacio']=$prefixrelacio;
	divclass('fulla',"style=\" text-align: center; background: #889ccf; width:98%; padding:1%; display: inline-block; \"");
	//h2('Llistat relacions');
	$fontsize=20;
	$meswidth=180;
	$yearwidth=$meswidth-40;
	$daywidth=70;
	$height=55;
	//global $_REQUEST;
	//pre($_REQUEST);
	//tria_data($inici);tria_data($final);
	$fi['year']=$_REQUEST['anyinici']?$_REQUEST['anyinici']:date("Y");
	$fi['mon']=$_REQUEST['mesfi']?$_REQUEST['mesfi']:date("m");
	$fi['mday']=31;
	if($_REQUEST['mesinici']!=$_SESSION['mesinici']){
		if($_REQUEST['mesinici']>$_REQUEST['mesfi'])$_REQUEST['mesfi']=$_REQUEST['mesinici'];
		$_SESSION['mesinici']=$_REQUEST['mesinici'];
	}
	if($_REQUEST['mesfi']!=$_SESSION['mesfi']){
		if($_REQUEST['mesinici']>$_REQUEST['mesfi'])$_REQUEST['mesinici']=$_REQUEST['mesfi'];
		$_SESSION['mesfi']=$_REQUEST['mesfi'];
		$_REQUEST['diafi']=final_de_mes($fi)['mday'];
	}
	form();
	//echo "Lista @:";
	//br();
	divclass("data","style=\" display: inline-block; \"");
	for($y=2020;$y<=date('Y');$y++)$nyear[$y]=$y;
	for($i=1;$i<=12;$i++)$nmesos[$i]=$i;
	/*if($_REQUEST['format']=="spreadsheet" or $s['format']=='php'){
		//debugtelegram($s['format']);
		$s['format']="spreadsheet";
		a("./?format=php","🐘"); 
	
	}
	if($_REQUEST['format']=="php" or !isset($s['format'])or $s['format']=='spreadsheet'){//$s['format']!="spreadsheet"){
		//debugtelegram($s['format']);
		$s['format']="php";
		a("./?format=spreadsheet&".urlencod("",$_REQUEST),"💾"); }
	 */
	$formatvalue=Array("php","php+spreadsheet","php+pdf ⚠️ tarda!");
	$format=Array("php","spreadsheet","pdf");
	select2llistes_js("format",$format,$formatvalue,$_REQUEST['format']?$_REQUEST['format']:"php",'no',false,
		"style=\"vertical-align:unset; font-size: {$fontsize}px;  text-align: left; width:{$meswidht}px; height:{$height}px;\"");
	if($pot_veure_les_altres_empreses){
		$emp=mysql2array("Select ID, email from Empresa where email!=' '",$link);
		select2llistes_js("emp",$emp[0],$emp[1],$s['rel']['Empresa']['ID'],"no",false,"style=\"vertical-align:unset; font-size: {$fontsize}px;  text-align: left; width:{$meswidht}px; height:{$height}px;\"");
		//pre($s);
	}
	$llistamesos=array("","gener","febrer","mar&ccedil;","abril","maig","juny","juliol","agost","setembre","octubre","novembre","desembre");
	select1llista_js("anyinici",$nyear,$_REQUEST['anyinici']?$_REQUEST['anyinici']:date("Y"),false,
		"style=\"vertical-align:unset; font-size:  {$fontsize}px;  text-align: left;  height:{$height}px;\"");//width:{$yearwidth}p
	select2llistes_js("mesinici",$nmesos,$llistamesos,
		$_REQUEST['mesinici']?($_REQUEST['mesinici']>$_REQUEST['mesfi']?$_REQUEST['mesfi']:$_REQUEST['mesinici']):date("m"),
		"no",false,"style=\"vertical-align:unset; font-size: {$fontsize}px;  text-align: left; width:{$meswidht}px; height:{$height}px;\"");	
	number_js("diainici",1,31,1,
		isset($_REQUEST['diainici'])?false:true,
		$_REQUEST['diainici']?$_REQUEST['diainici']:1,
		"style=\"font-size: {$fontsize}px; width:{$daywidth}px; height:{$height}px;\"");
	tdiv();
	divclass("data","style=\" display: inline-block; \"");
	select2llistes_js("mesfi",$nmesos,$llistamesos,
		$_REQUEST['mesfi']?$_REQUEST['mesfi']:date("m"),
		"no",false,"style=\"vertical-align:unset; font-size: {$fontsize}px;  text-align: left; width:{$meswidht}px; height:{$height}px;\"");
	//echo "final de mes:".final_de_mes($fi)['mday'];
	number_js("diafi",1,final_de_mes($fi)['mday'],1,
		/*isset($_REQUEST['diafi'])?($_REQUEST['diainici']?false:true):true,*/false,//mai serà focus o bé anula comentari i serà focus despres de triar dia inici
		$_REQUEST['diafi']?($_REQUEST['diafi']>final_de_mes($fi)['mday']?final_de_mes($fi)['mday']:$_REQUEST['diafi']):final_de_mes($fi)['mday'],
		"style=\"font-size: {$fontsize}px; width:{$daywidth}px; height:{$height}px;\"");
	$tipus_relacio=mysql2array("SELECT * FROM `tipus_relacio` WHERE `ID`>'0'",$link);
	$_SESSION['llista']['tipus_relacio']=$tipus_relacio;
	select2llistes_js("ID_tipus_relacio",$tipus_relacio[0],$tipus_relacio[1],$_REQUEST['ID_tipus_relacio']?$_REQUEST['ID_tipus_relacio']:1,
		"no",false,
		"style=\"vertical-align:unset; font-size: {$fontsize}px;  text-align: left;  height:{$height}px;\"");
	if(in_array($_REQUEST['ID_tipus_relacio'], array(3,4))){
		$Pagament=mysql2array("SELECT * FROM `Pagament` Where `ID_tipus_relacio`='3'or `ID`='0'",$link);
		// -1 = sense filtre; 0 queda com a "No pagat"
		array_unshift($Pagament[0],-1);
		array_unshift($Pagament[1],"Tots els pagaments");
		$_SESSION['llista']['Pagament']=$Pagament;
		select2llistes_js("ID_Pagament",$Pagament[0],$Pagament[1],(isset($_REQUEST['ID_Pagament'])&&$_REQUEST['ID_Pagament']!=='')?$_REQUEST['ID_Pagament']:-1,
			"no",false,
			"style=\"vertical-align:unset; font-size: {$fontsize}px;  text-align: left;  height:{$height}px;\"");
	//	 if(is_array($_SESSION['llistaclients'])){
	//		select2llistes_js("ID_client",$_SESSION['llistaclients']['ID'],$_SESSION['llistaclients']['fiscal_name'],
	//			$_REQUEST['ID_client']?$_REQUEST['ID_client']:0,"no",false,
	//			"style=\"vertical-align:unset; font-size: {$fontsize}px;  text-align: left;  height:{$height}px;\"");
	//	}
	}
	//pre($tipus_relacio);
	$t=llistarelacions($_SESSION['rel']['Empresa'],$link);
	tdiv();
	//debugtelegram("relacions default");
break;
}
divclass('sortir');
if(!$login)echo 'ko';
a("./?do=new","Nova");echo" ";
a("./","Llista");echo" ";
a("../scale/","Bascula");echo " ";
a("./?do=rel2rel","Anidament");echo " ";
a("./?tancar=true","Sortir"); echo $_SESSION['Empresa']['Value'];
if($s['Empresa']['ID']!=$s['rel']['Empresa']['ID']){
	echo " [";
	echo mysql1r("Select email from Empresa where ID='{$s['rel']['Empresa']['ID']}'",$link);
	echo"]";
}
tdiv();
estil_tancament($link,__FILE__);
?> 
