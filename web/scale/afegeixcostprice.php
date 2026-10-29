<?php 
/*
 SELECT SQL_CALC_FOUND_ROWS `pv4`, MAX(`historic`), `data_hora` FROM `tarifes` WHERE `ID` = '1' AND `data_hora` < '2020-01-01 11:22:22' GROUP BY `pv4`, `data_hora` ORDER BY `historic` DESC LIMIT 1
 *
 */
require('../../keys.php');
require('login.php');
if(!$login)die;
$any=date('Y');
$q="select * from {$any}_Empresa_{$ID_Empresa}_line where cost_price=0";
//echo $q;
if($llista=$link->query($q)){
	//echo 'tinc llista';
	while ($f = $llista->fetch_assoc()){
		//pre($f);
		$date=decodificadatetime($f['hidden_now']);
		$date['minutes']='00';
		$date['secons']='00';
		$date=datetime2mysql($date);
		//pre($date);
		if($f['ID_product']>0){
			$cost_price=mysql1r(" SELECT `pv4` FROM `tarifes` WHERE `ID` = '{$f['ID_product']}' AND `data_hora` < '$date' GROUP BY `pv4`, `data_hora` ORDER BY `historic` DESC LIMIT 1",$link);
			//si he fet syncmaria abans de fer la lineea i és producte nou:
			$date=decodificadatetime($f['hidden_now']);
			$date=datetime2mysql($date);
			if($cost_price=='')$cost_price=mysql1r(" SELECT `pv4` FROM `tarifes` WHERE `ID` = '{$f['ID_product']}' AND `data_hora` < '$date' GROUP BY `pv4`, `data_hora` ORDER BY `historic` DESC LIMIT 1",$link);


		}
		else $cost_price=round($f['cost']>0?$f['cost']*0.50:0,2);
		//echo $cost_price; br();
		if($cost_price!=$f['cost_price']){
			$u="UPDATE `{$any}_Empresa_{$ID_Empresa}_line` SET `cost_price` = '$cost_price' WHERE `ID` = '{$f['ID']}' AND `ID_tipus_relacio` = '{$f['ID_tipus_relacio']}' AND `line` = '{$f['line']}';";
			echo "<br/>$u";
			$link->query($u)or die ($u.$link->error);
		}
	}
}
else echo 'no tinc llista';
echo 'done';
	
?>
