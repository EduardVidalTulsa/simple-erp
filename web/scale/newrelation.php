<?php
/************
*
* creo una nova relació a partir de linea n 
* 6 d'abril 2023
*
************/
require("login.php");
if(!$login) die;
$ID_venedor=$_REQUEST['ID_venedor'];
$line=$_REQUEST['line'];
$uuid=mysql1r("select `uuid` from `{$prefixrelacio}index_relacio`  where
	ID_Venedor=$ID_venedor	and relacio_oberta=1",$link);
$rold=detallrelacio($uuid,$ID_Empresa,date("Y"),$link);
//tanco la vella oct 2023 2 linees:
$qtanca="UPDATE `{$prefixrelacio}index_relacio` SET `relacio_oberta` = '1', `ID_Pagament`='0' WHERE `ID`='{$rold['ID']}' AND `ID_tipus_relacio` = '{$rold['ID_tipus_relacio']}' ";
if(!$link->query($qtanca)) {error(mysqli_error($link));echo "no l'he trobada $qtanca ";die;}
//creo l'index de la nova relació
$ID_relacio=mysql1r("select COALESCE(MAX(ID),0)+1 from `{$prefixrelacio}index_relacio` where ID_tipus_relacio=$ID_tipus_relacio",$link);
$m2['table']=$prefixrelacio.'index_relacio';
$m2['ID']=$ID_relacio;
$m2['do']='new';
$d['ID_Venedor']=$ID_venedor;
$d['ID_client']=0;
$d['ID_tipus_relacio']=$ID_tipus_relacio;//relació nova tipus default
$d['fiscal_name']=$Venda_detall;
$d['data']['mday']=date("j");
$d['data']['mon']=date("n");
$d['data']['year']=date("Y");
$d['uuid']=uniqid($ID_Empresa);
$d['relacio_oberta']=1;//caldra modificar total.php per que miri si n'hi ha una creada per assignar relacio_oberta=1. ja modificat!
$m2[$m2['table']]=$d;
//new_update($m2,$link);//,true);
//pre($rold);
//pre($m2);
$linesold=$rold['activelines']+$rold['deletedlines'];
$newline=1;
for ($i=$line;$i<=$linesold;$i++){
	//moc les lines anteriors a la nova relació
	$update="UPDATE {$prefixrelacio}line SET `ID`={$ID_relacio},`ID_tipus_relacio`='$ID_tipus_relacio',`line`='$newline' where `ID`='{$rold['ID']}' AND`ID_tipus_relacio`='{$rold['ID_tipus_relacio']}' AND `line`='$i'";

	$newline++;
	pre($update);
	$link->query($update)or die("No he pugut actualitzar la línea".$link->error);
}
	new_update($m2,$link);//,true);

header("Location: $scale_uri?m[estat]=subtotal&search=/{$_REQUEST['ID_venedor']}&while=yes");
?>
