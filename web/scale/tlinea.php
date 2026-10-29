<?php
/************
*
*	Tractament de magic mentre no hi ha intro, part ajax omple a un div de index anomenat dades
*	també fa el hack de mostrar-se dintre de index.php quan es el que necesitem alla
*	es el mateix que ensenyem aqui /n venedor p.ex.
*
************/
require("login.php");
if($_REQUEST['key']==$tlineakey){
	$_SESSION['login']==TRUE;
	$login=true;
	$prefixrelacio=date('Y')."_Empresa_"."{$_REQUEST['ID_Empresa']}".'_';
}
if($login){
	$pes=mysql1r("Select pes from ultimpes where ID=$idbascula",$link);
	// el mateix parser que en prémer Intro (case_tracto), perquè la previsualització no enganyi
	$p=parse_magic_code($_REQUEST['ID'],'scale');
	$abono=$p['abono'] ?? '';
	switch($p['tipus']){
		case 'codi': $ID=$p['ID']; $units=$p['units']; $lotnumber=$p['lot']; break;
		case 'paquet': $IDp=$p['ID'].'.'.$p['paquet']; $ID='package'; $units=$p['units']; $lotnumber=$p['lot']; break;
		case 'preu_directe': $ID='+'; break;
		case 'ean': $ID=mysql1r("SELECT ID FROM ean WHERE Value='{$p['ID']}'",$link) ?: $p['ID']; break;
		case 'buit': $ID=''; break;
		default: $ID=$_REQUEST['ID']; // * ** / i legacy: els casos de sota miren el text
	}
    switch($ID){
        // TODO case si ID leng es codi de barres!
	case '-10':
			//afegeixo un 10% de descompte a l'ultima línea.
			$scaixa="style =\"background:#ACAB00; position:absolute; top:120px; left:5px; width:580px; height:815px; font-size:33px;padding:1%;\"";
       	divclass('caixadescripcio',$scaixa);
			echo "Afegirè un 10% de descompte a l'ultima fila entrada :<br/>".urldecod($_SESSION['lastline'][$_SESSION['lastline']['table']]['description']);
// 			pre($_SESSION['lastline']);
			tdiv();
			break;
    case '':
        caixa_tlinea('pes');
			echo "{$abono}".number_format(($pes/1000),3,$decimalsing,$thousandsing);
			echo "Kg&nbsp;";
		tdiv();
        caixa_tlinea('caixapreu');
			mysqli_query($link,"UPDATE `ultimpes` SET `preu` = '0' WHERE `ID` = '$idbascula';");
			echo"€/Kg&nbsp;";
		tdiv();
		caixa_tlinea('caixatlinea');
			echo "€&nbsp;";
		tdiv();

		caixa_tlinea('caixadescripcio');
		caixa_tlinea('Descripcio');
			echo "entra <br/> codi o *, /, +, -";
// 			pre($_REQUEST['w']);
			tdiv();
		tdiv();
        break;
	case "package":
		//case preg_match('/^\d+\.\d+$/', $ID):
		//debugtelegram("es un codi.paquet. $IDp",gethostname());
		list($ID,$package_type)=explode('.',$IDp);
		$qpack="SELECT * from package_format where `ID`=$ID AND `ID_package_type`=$package_type AND `hidden_date_time` = ( SELECT MAX(`hidden_date_time`) from package_format where `ID` ='$ID' AND `ID_package_type`='$package_type')";
		//pre($qpack);
		$package=$link->query($qpack)->fetch_assoc();
		//pre($package);
		if(!is_array($package)){
			tlineapreu("0",$abono);
			tlineapes("0","Kg");
			tlineavalor("0",$abono);
			tlineadescripcio("Codi no vàlid");
			break;

		}
		$product=$link->query("SELECT * from tarifes where `ID`=$ID AND `ultimpreu`=1")->fetch_assoc();
		$product['pvp']=package_pvp($product,$package,$link);//retorna falç si no troba un preu sobreescrit
		if(!$product['pvp']){
			if($units>0)$units=($package['weight']/1000)*$units;
			else $units=$package['weight']/1000;
			$product['pvp']=mysql1r("Select pvp from scalelist where ID=$ID",$link);
		}
		else {
			$product["bool_in_bulk"]=0;//té un preu entrat, per tant es ven per caixa/paquet,altrament es queda com està a tarifes
		}
		if(is_numeric($units)&&($units!=" ")){//codi*unitats
			$units=$abono.$units;
		}
		else{//codi i una unitat
			$units=$abono.'1';
		}
		tlineapreu(number_format_trim(round($product['pvp'],2),2,$decimalsing,$thousandsing)."€/".($product["bool_in_bulk"]==0?"u":"Kg"));
		tlineapes($units,($product['bool_in_bulk']==1?"Kg":"U"));

//		tlineapes(number_format_trim($units,3,$decimalsing,$thousandsing),($product['bool_in_bulk']==1?"Kg":"U"));
		tlineavalor($product['pvp']*$units);
		$sqldes="select description from package_description where `ID`='{$ID}' AND `ID_package_type`='{$package_type}' AND `lang`='ca'";
		$description=urldecod($link->query($sqldes)->fetch_assoc()['description']);
		$sqldes="select value from scalelist where `ID`='{$ID}'";
		if(!isset($description)){
			$description=urldecod($link->query($sqldes)->fetch_assoc()['value']);

			$ppes=mysql1r("select `weight` from `package_format` where `ID`='{$ID}'and `ID_package_type`='{$package_type}'AND `hidden_date_time` = (  SELECT MAX(`hidden_date_time`) from package_format where `ID` ='{$ID}' AND `ID_package_type`='{$package_type}')",$link,true);
			if($ppes>1000)$ppes=number_format_trim(($ppes/1000),3,$decimalsing,$thousandsing)."Kg";
			else $ppes=$ppes."g";

			$description.=" $ppes";
		}
		tlineadescripcio($description);
		//tlineadescripcio(print_r($product));
		break;

    case ((is_numeric($ID['0']))&&(is_numeric($ID))&&($ID!='')):
        //$codi=substr
        $sql="select exist,value, formatpvp, bulk, pvp from scalelist where ID=$ID ";
        //echo 'entro';
        $o= mysql2vector($sql,$link);
        if($o[0]==1){#article existeix
			caixa_tlinea('caixapreu');
			echo number_format($o[4],2,$decimalsing,$thousandsing).($o[3]==1?'€/Kg':'€/uni').'&nbsp;</div>';
				if($o[3]==1){
					if(is_numeric($units)&&($units!=" ")){
							caixa_tlinea('pes');
						echo "{$abono}".number_format($units,3,
											$decimalsing,$thousandsing)."Kg&nbsp;";
								tdiv();
						caixa_tlinea('caixatlinea');
						echo "{$abono}".number_format($o[4]*$units,2,$decimalsing,$thousandsing).'€&nbsp;';
								tdiv();
						}
						else{
							caixa_tlinea('pes');
			echo "{$abono}".number_format(($pes/1000),3,
										$decimalsing,$thousandsing); echo "Kg&nbsp;";tdiv();
		caixa_tlinea('caixatlinea');
							echo"{$abono}".
							number_format(($pes/1000)*$o[4],2,$decimalsing,$thousandsing).'€&nbsp;';
							tdiv();
							$preu=round($o[4]*100);
							mysqli_query($link,"UPDATE `ultimpes` SET `preu` = '$preu' WHERE `ID` = '$idbascula';");
						}
		}
		else{
			if(is_numeric($units)&&($units!=" ")){
				caixa_tlinea('pes');
		echo "{$abono}".number_format_trim( $units,3,$decimalsing,$thousandsing).
					" unitats&nbsp;";tdiv();
				caixa_tlinea('caixatlinea');

				echo "{$abono}".
					number_format( $o[4]*$units,2,$decimalsing,$thousandsing).'€'; tdiv();
			}
			else{
				caixa_tlinea('pes');
				echo "{$abono}1 unitat&nbsp;";tdiv();
				caixa_tlinea('caixatlinea');
				echo "{$abono}".number_format( $o[4],2,$decimalsing,$thousandsing).
					'€&nbsp;'; tdiv();
			}
			}
           	caixa_tlinea('caixadescripcio');
			caixa_tlinea('Descripcio');
			echo urldecode($o['1']);tdiv();tdiv();
        }
		else{
            caixa_tlinea('pes');
		echo "{$abono}".number_format(($pes/1000),3,$decimalsing,$thousandsing); 
			echo " Kg&nbsp;";tdiv();
            caixa_tlinea('caixapreu');
			echo"€/Kg&nbsp;";tdiv();
            mysqli_query($link,"UPDATE `ultimpes` SET `preu` = '0' WHERE `ID` = '$idbascula';");
            caixa_tlinea('caixatlinea');	echo "€&nbsp;";tdiv();
            caixa_tlinea('caixadescripcio');
			caixa_tlinea('Descripcio');
			echo "Codi no vàlid";tdiv();tdiv();
        }
        break;
    case ($ID[0]=='+')://preu directe
        $preu=is_numeric($p['preu'])?$p['preu']:0;
        $units=$p['units'];
        $iva=is_numeric($p['iva'])?$p['iva']:$ivageneric;
        if (is_numeric($units)){
			caixa_tlinea('pes');
			echo "{$abono}".number_format($units,2,$decimalsing,$thousandsing)."U&nbsp;";tdiv();
            caixa_tlinea('caixapreu');
			echo number_format($preu,2,$decimalsing,$thousandsing)."€/U&nbsp;";tdiv();
            caixa_tlinea('caixatlinea');
			echo "{$abono}".number_format( $preu*$units,2,
						$decimalsing,$thousandsing).'€&nbsp;'; tdiv();
            caixa_tlinea('caixadescripcio');
		caixa_tlinea('Descripcio');
			echo "Article sense codi amb un iva del {$iva}%";tdiv();tdiv();

        }
        else {
            caixa_tlinea('pes');
			echo "{$abono}".number_format(($pes/1000),3,$decimalsing,$thousandsing);
			echo "Kg&nbsp;";tdiv();
            caixa_tlinea('caixapreu');
			echo number_format($preu,2,$decimalsing,$thousandsing)."€/Kg&nbsp;";tdiv();
			caixa_tlinea('caixatlinea');			echo "{$abono}".number_format( ($pes/1000)*$preu,2,$decimalsing,$thousandsing).'€&nbsp;';
			tdiv();
            $preu=$preu*100;
            mysqli_query($link,"UPDATE `ultimpes` SET `preu` = '$preu' WHERE `ID` = '$idbascula';");
            caixa_tlinea('caixadescripcio');
		caixa_tlinea('Descripcio');
			echo "Article sense codi amb un iva del {$iva}%";tdiv();tdiv();
        }
        break;
	case (($ID['0']=='/')&&(is_numeric($ID['1'])&&substr($ID,-1)=='.'))://recarrego ultim
			$ID_Empresa_a_mxgestio=mysql1r("select ID_Empresa_a_mxgestio FROM Empresa where ID=$ID_Empresa ",$link);
// 			echo 'recarrego';
		  	$ID_Venedor=substr($ID,1,-1);
		    	$hiharelaciooberta=mysql1r("select count(*)FROM {$prefixrelacio}index_relacio WHERE ID_venedor=$ID_Venedor  and relacio_oberta=1 And `editable`='1'",$link);// and ID_tipus_relacio=$ID_tipus_relacio
			if($hiharelaciooberta==0){
				$relacions_avui=mysql2array("SELECT `ID`, `ID_tipus_relacio` FROM  {$prefixrelacio}index_relacio WHERE `data` = current_date() and ID_Venedor=$ID_Venedor",$link);
				//debugtelegram($relacions_avui);debugtelegram(count($relacions_avui[0]));
				if(isset($relacions_avui[0][0])){
					$ultima="SELECT `ID`,`ID_tipus_relacio` FROM {$prefixrelacio}line WHERE ";
					for($i=0;$i<count($relacions_avui[0]);$i++){
						$ultima.="(`ID`='{$relacions_avui[0][$i]}' AND `ID_tipus_relacio`='{$relacions_avui[1][$i]}')";
						if($i<(count($relacions_avui[0])-1))$ultima.=' OR ';
					}
					$ultima.=" ORDER BY `hidden_now` DESC LIMIT 1";
					$ultima=$link->query($ultima)->fetch_assoc();
					//debugtelegram($ultima);die;
					$link->query("UPDATE {$prefixrelacio}index_relacio SET relacio_oberta=1 WHERE ID='{$ultima['ID']}' AND ID_tipus_relacio='{$ultima['ID_tipus_relacio']}'");
					$link->query("DELETE FROM {$prefixrelacio}pdf WHERE `ID` = '{$ultima['ID']}' AND `ID_tipus_relacio` = '{$ultima['ID_tipus_relacio']}';");
				}
				/*
				if($ID_Empresa_a_mxgestio==1)$tipus=4;//reobro la proforma o bé la factura *depen si té un programa de facturació a part o no.
				else $tipus=3;
				$qultima="SELECT `ID`, `ID_tipus_relacio` FROM `{$prefixrelacio}line` ORDER BY `hidden_now` DESC LIMIT 1";
					//$qultima="select MAX(ID) from {$prefixrelacio}index_relacio WHERE ID_venedor=$ID_Venedor  and relacio_oberta=0 and ID_tipus_relacio=$tipus and editable=1";
				$qu=$link->query($qultima) or die ($qultima.$link->error);
			     	$u=$qu->fetch_assoc();
				$ultima=$u['ID'];//mysql1r($qultima,$link);
				$tipus=$u['ID_tipus_relacio'];
				//debugtelegram($qultima);
				if($ultima>0){
					$link->query("UPDATE {$prefixrelacio}index_relacio SET relacio_oberta=1 WHERE ID=$ultima and ID_tipus_relacio=$tipus");
					$link->query("DELETE FROM {$prefixrelacio}pdf WHERE `ID` = '$ultima' AND `ID_tipus_relacio` = '$tipus';");
				}
			 */
			}
			$link->close();
		  	echo "<script>location.replace(\"{$scale_uri}\")</script>";
			break;
	case (preg_match('/^\*\*\*(\d+)(?:\.(\d+))?\*?(\d+)?(?:\+(\d+))?(?:\+(.*))?$/',$ID,$m)==1):
		// previsualització ***codi[.paquet][*unitats][+lot][+data] (mateix patró que processar_imprimir_etiquetes)
		$codi=$m[1]; $paquet=$m[2]??''; $unitats=($m[3]??'')!==''?$m[3]:1; $lot=$m[4]??''; $bbf=$m[5]??'';
		caixa_etiqueta();
		echo "<strong>***$codi".($paquet!==''?".$paquet":'')."</strong> ✕ $unitats";
		if($lot!=='')echo " · Lot: ".htmlspecialchars($lot);
		if($bbf!=='')echo " · ".htmlspecialchars($bbf);
		br();
		img_etiqueta("etiquetaproducte.php?".http_build_query(['ID'=>$codi,'package'=>$paquet,'lot'=>$lot,'bbf'=>$bbf]),"Codi $codi: no hi ha etiqueta de producte");
		tdiv();
		$link->close();
		die;
	case (preg_match('/^\*\*(\d+)(?:\.(\d+))?(?:\*(\d+))?$/',$ID,$m)==1):
		// previsualització **codi[.paquet][*unitats]: etiqueta de preu
		$codi=$m[1]; $paquet=$m[2]??''; $unitats=($m[3]??'')!==''?$m[3]:1;
		caixa_etiqueta();
		$a=$link->query("select * from scalelist where id='$codi'")->fetch_assoc();
		if($a['id']==$codi){
			echo "<strong>**$codi".($paquet!==''?".$paquet":'')."</strong> ✕ $unitats · ".urldecode($a['value'])." · ".number_format($a['pvp'],2,$decimalsing,$thousandsing).($a['bulk']==1?'€/Kg':'€/uni');
			br();
			img_etiqueta("etiquetapreu.php?".http_build_query(['ID'=>$codi,'package'=>$paquet]),"No s'ha pogut generar l'etiqueta de preu");
		}
		else echo "Codi $codi, no vàlid";
		tdiv();
		$link->close();
		die;

	case (($ID['0']=='*')&&($ID['1']=='*')):
		$scaixa="style =\"background:#ACAB00; position:absolute; top:120px; left:5px; width:580px; height:815px; font-size:33px;padding:1%;\"";
       		divclass('caixadescripcio',$scaixa);
		echo"**codiproducte <br/> imprimeix una etiqueta si en vols més d'una fes:";
		br();
		echo "**codiproducte*numero_etiquetes";
		die;
		break;	
	case (($ID['0']=='*')&&(is_numeric(substr($ID,1)))):
			//echo 'modifico unitats ultima línea';
			$link->close();
			if (is_numeric(substr($ID,1))){
				$scaixa="style =\"background:#ACAB00; position:absolute; top:120px; left:5px; width:580px; height:815px; font-size:33px;padding:1%;\"";
       			divclass('caixadescripcio',$scaixa);
// 				pre($_SESSION);
				echo "Canvio les unitats de: ".brr(). urldecode($_SESSION['lastline'][$_SESSION['lastline']['table']]['description']);
				tdiv();
			}
			else echo"<script> window.location.href = \"$scale_uri\";</script>";
// 			$ID_Venedor=substr($ID,1);
// 			$FROMindex_relacio="FROM {$prefixrelacio}index_relacio WHERE ID_venedor=$ID_Venedor and relacio_oberta=1 and ID_tipus_relacio=$ID_tipus_relacio";
// 			$r=mysql2vectorassoc("SELECT * $FROMindex_relacio",$link)or header ;
			break;

    case (($ID['0']=='/')&&((is_numeric($ID['1'])))):
	//mostra el subtotal només implementat per *nvenedor anyway
	//list($accio,$venedor)=explode('*'||'/',$ID);
// 			echo 'subtotal';
	$ID_venedor=substr($ID,1);
	if($w<$h)$scaixa="style =\"background:#ACAB00; position:absolute; top:120px; left:5px; width:580px; height:815px; font-size:33px;padding:1%;\"";
	else $scaixa="style =\"background:#ACAB00; position:absolute; top:10px; left:5px; width:".(($w/2)-30)."px; height:815px; font-size:33px;padding:1%;\"";
       	divclass('caixadescripcio',$scaixa);
		$FROMindex_relacio="FROM {$prefixrelacio}index_relacio WHERE ID_venedor=$ID_venedor and relacio_oberta=1 ";//and ID_tipus_relacio=$ID_tipus_relacio";
		//$ID_relacio=mysql1r("SELECT ID $FROMindex_relacio",$link);
		$r=$link->query("SELECT max(ID) AS ID $FROMindex_relacio")->fetch_assoc();
		if($r['ID']!=''){
			echo"<script> window.location.href = \"$scale_uri?search=$ID&m[estat]=subtotal\";</script>";
		}
		else echo "No hi ha cap tiquet obert, vols recuperar el anterior? <br/>
					Has de posar /$ID_venedor. per carregar l'anterior";
		tdiv();
		break;
	} //s'acaba el switch id
	// aqui fem el peu de pagina, amb l'ultima línea, el rellotge i els tiquets del dia

	if($w<$h)div('hora',"style =\"position:absolute; top:680"."px; left:".(($w)-400)."px; width:".($w)."px;  height:13px; font-size:15px;padding:2px;\"");
	else div('hora',"style =\"position:absolute; bottom:".(15)."px; left:".(($w/2)-150)."px; width:".($w/2)."px;  height:13px; font-size:15px;padding:2px;\"");
	echo "id:{$ID_Empresa}.$idbascula ".date("H:i:s");
	tdiv();
    tdiv();
}
else echo "nologin";
// require('tanca.php');

// caixa de la previsualització d'etiquetes (** i ***), a la meitat esquerra
function caixa_etiqueta(){
	global $w,$h;
	if($w<$h)$amplada=$w-10;
	else $amplada=$w/2-20;
	divclass('caixadescripcio',"style =\"background:#ACAB00; position:absolute; top:5px; left:5px; width:{$amplada}px; height:".($h-20)."px; font-size:20px;padding:5px;overflow:auto;z-index:10;\"");
}
// l'SVG el serveixen etiquetaproducte.php / etiquetapreu.php (sense print=true no imprimeixen)
function img_etiqueta($src,$error){
	global $h;
	echo "<img src=\"".htmlspecialchars($src)."\" style=\"background:white;width:100%;height:auto;max-height:".($h-90)."px;object-fit:contain;margin-top:5px;\" onerror=\"this.outerHTML='".htmlspecialchars(addslashes($error))."'\"/>";
}
?>
