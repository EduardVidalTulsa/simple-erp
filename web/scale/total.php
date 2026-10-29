<?php
require("login.php");
if ($login==false){$link->close();header("Location: {$scale_uri}");die;}
//$link->query("lock tables `{$prefixrelacio}index_relacio` write, `Empresa` write, `{$ID_Empresa}_Venedor` write, `{$prefixrelacio}line`   write ");
$r=detallrelacio($_REQUEST['uuid'],$ID_Empresa,date("Y"),$link);

//Aqui faria falta la lògica de via de pago, en cas que n'hi hagi... Ara no en tenim i si convé tornar enrera abans del update
//switch ($_REQUEST['via']){
// 	 case 1: //efectiu
//  case 2: //tarjeta presencial
//  case 3: //bizum
//  case 4: //tarjeta no presencial
//  case 5: //coinbas c

$r['ID_Pagament']=$_REQUEST['via'];

if(($r['ID_tipus_relacio']!=3)and($r['ID_tipus_relacio']!=9)and($r['relacio_oberta']==1)){//assignem el tipus de relació depenent de quina sigui la forma de "pagament"
        // si és una factura no es pot canviar de forma de tipus de relació 
	//es pot triar entre factura (si paguen)o altres tipus relacions proforma, albarà, pressupost...
	//possiblement si hi ha molta i molta gent treballant, potser faria falta bloquejar temporalment index_relacio 
	//per que no assignin el mateix ID a la vegada. 
	$NEW_tipus_relacio=mysql1r("select ID_tipus_relacio from Pagament where ID='{$_REQUEST['via']}'",$link);
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

$qtanca="UPDATE `{$prefixrelacio}index_relacio` SET `relacio_oberta` = '0', `ID_Pagament`='{$_REQUEST['via']}', `ID`='$NEWID',`ID_tipus_relacio` ='$NEW_tipus_relacio'
		WHERE `ID`='{$r['ID']}' AND `ID_tipus_relacio` = '{$r['ID_tipus_relacio']}' ";
if(!$link->query($qtanca)) {error(mysqli_error($link));echo "no l'he trobada $qtanca ";die;}
if($qupdate_lines!="")if(!$link->query($qupdate_lines)) {error(mysqli_error($link));echo "error assignant línees";die;}
$link->query("unlock tables");

$r['email']==""?:$r['email']=urldecode($r['email']);
$r['ID_tipus_relacio']=$NEW_tipus_relacio;
$r['ID']=$NEWID;
$r['tipus_relacio']['ID']=$NEW_tipus_relacio;
$r['tipus_relacio']['Value']=mysql1r("SELECT Value from tipus_relacio where ID='$NEW_tipus_relacio'",$link);

if(($_SESSION['Empresa']['cashkeeper']!="") && ($r['ID_Pagament']==1)) {

	// mantenim exactament les variables originals
	$service_port = 16501;
	$address = $_SESSION['Empresa']['cashkeeper'];

	// Comprovem ràpidament si el port està obert
	$fp = @fsockopen($address, $service_port, $errno, $errstr, 1);
	if (!$fp) {
		br();
		br();
		br();
		br();
		h1(ar("{$scale_uri}canvi.php?uuid={$r['uuid']}", "cobrar a mà amb ajuda"));
		br();
		br();
		br();
		br();
		br();
		br();
		h1(ar("{$scale_uri}", "pantalla bascula"));

		die("Cashkeeper no contesta. Vols ajuda per cobrar o tornar a la pantalla principal?");
	}
	fclose($fp);

	// Creem la taula cashkeeper si no existeix
	$create_table_sql = "
	CREATE TABLE IF NOT EXISTS cashkeeper (
		id INT AUTO_INCREMENT PRIMARY KEY,
		uuid VARCHAR(36) NOT NULL,
		total INT NOT NULL,
		data_creacio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		enviada TINYINT(1) DEFAULT 0,
		error VARCHAR(255) DEFAULT NULL
		) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4;
		";
		$link->query($create_table_sql) or die("No s'ha pogut crear la taula cashkeeper: ".$link->error);

		// Calcula total en cèntims
		$total = intval(number_format($r['Total'],2)*100);
		if($_SESSION['Empresa']['cashkeeper_1cent']==0){
			$ultim = $total % 10;
			if (in_array($ultim, [1,6,9])) $total -= 1;
		}

		// Inserta a la taula cashkeeper
		$stmt = $link->prepare("INSERT INTO cashkeeper (uuid,total) VALUES (?,?)");
		$stmt->bind_param("si", $r['uuid'], $total);
		$stmt->execute() or die("Error inserint a cashkeeper: ".$stmt->error);
}


/*//Afegit a partir de newrelation.php: ho trec al oct 2023 perque si fan nova relació en deixo dos d'obertes fisn que les cobrin i mostro error
$uuid=mysql1r("SELECT MAX(`uuid`) FROM {$prefixrelacio}index_relacio WHERE relacio_oberta ='0' AND `ID_tipus_relacio`='$ID_tipus_relacio' AND `ID_Venedor`='{$r['ID_Venedor']}'",$link,true);
$update="UPDATE {$prefixrelacio}index_relacio set relacio_oberta ='1' where `ID_tipus_relacio`=$ID_tipus_relacio and `ID_Venedor`={$r['ID_Venedor']} and `uuid`='$uuid' ";
//debugtelegram("$update");
if($uuid!="")$link->query($update) or die ("$update failed".$link->error); //fins aqui canvis de new relacion
*/
//ajuda canvi 
//Miro si existeix la columna, i despres envio el cobrament a canvi.php octubre 2023
if(mysql1r("
SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = '{$ID_Empresa}_Venedor' AND COLUMN_NAME = 'ajuda_canvi';

",$link))if(($_SESSION['Empresa']['cashkeeper']=="")&&($r['ID_Pagament']==1)&&(mysql1r("SELECT `ajuda_canvi` FROM `{$ID_Empresa}_Venedor` WHERE `ID`='{$r['ID_Venedor']}'",$link)==1)){
	header("Location: {$scale_uri}canvi.php?uuid={$r['uuid']}");
	die;
}
//fi ajuda canvi
if($r['phone']!=''){
	pdf($r,$prefixrelacio);

}


unset ($_SESSION['r']);
$link->close();
//header("Location: {$scale_uri}tiquet.php?uuid={$r['uuid']}");
header("Location: {$scale_uri}?{$r['uuid']}");
?>
