<?php
function tallarCadena($cadena, $max) {
    if (strlen($cadena) > $max) {
        $paraules = explode(" ", $cadena);
        $cadena_tallada = "";
        foreach ($paraules as $paraula) {
            if (strlen($cadena_tallada . $paraula) <= $max) {
                $cadena_tallada .= " " . $paraula;
            } else {
                break;
            }
        }
        $cadena = trim($cadena_tallada);
    }
    return $cadena;
}

// SVG de l'etiqueta de preu com a string (l'imprimeix etiquetapreu() i el previsualitza tlinea.php)
function generar_etiqueta_preu_svg($ID,$pvp,$nom,$u,$origen,$package=""){
	global $midaetiqueta, $link;
	$bool_eco=mysql1r("select `bool_eco` from `productnamesurlencoded` where `ID`='{$ID}' and `lang`='ca'",$link);
	if($bool_eco==1)$eco="eco";
	$file="{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/etiquetapreu{$eco}{$midaetiqueta}.svg";
	if(!file_exists($file))return false;
	$nom1=tallarCadena($nom, 16);
	$nom2 = str_replace($nom1,'', $nom);
	try{$ingredients=urldecod(mysql1r("select `ingredients` from `productnamesurlencoded` where `ID`='{$ID}' and `lang`='ca'",$link));
	}catch(exception $e){debugtelegram("No he aconseguit els ingredients per fer l'etiqueta preu de $ID'",gethostname());}
	if($_SESSION['user']==100)debugtelegram($ingredients,"ingredients@".gethostname());
	if($ingredients=="Producte Natural")$ingredients="";
	if($package!="")$package=". $package";
	global $web_etiqueta;
	return strtr(file_get_contents($file),array('$web'=>$web_etiqueta,'$pvp'=>$pvp,'$u'=>$u,'$origen'=>$origen,'$ingredients'=>$ingredients, '$nom'=>$nom1,'$nom2'=>$nom2, '$ID'=>$ID, '$pack'=>$package));
}
function etiquetapreu($ID,$pvp,$nom,$u,$origen,$nf,$package=""){
	$svg=generar_etiqueta_preu_svg($ID,$pvp,$nom,$u,$origen,$package);
	if($svg!==false)file_put_contents($nf,$svg);
}
// SVG de l'etiqueta de producte com a string. $qrid: fitxer del QR (p.php el crea a q$nf);
// si és null el QR va incrustat (previsualització)
function generar_etiqueta_producte_svg($print,$link,$qrid=null){
	global $midaetiqueta;
	//debugtelegram($midaetiqueta,gethostname()." mida etiqueta");
	$file="{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/etiquetaproducte{$midaetiqueta}.svg";
	if(!file_exists($file))return false;
	$decimalsing=',';
	$thousandsing=' ';
	$product=$link->query("select * from `productnamesurlencoded` where `ID`='{$print['ID_product']}' and `lang`='ca'")->fetch_assoc();
	if(!$product)return false;
	$product['bool_in_bulk']=mysql1r("select bool_in_bulk from `tarifes` where `ID`='{$print['ID_product']}' AND `ultimpreu`='1'",$link);
	if($qrid===null){
		// mateixa URL que posa p.php al QR
		$p=$print['package']!=""?".".$print['package']:"";
		global $web_etiqueta;
		$url="$web_etiqueta/{$print['ID_product']}$p".($print['lot']!=""?"+".$print['lot']:"").($print['bbf']!=""?"+".$print['bbf']:"");
		if(!class_exists('Endroid\QrCode\QrCode'))require_once(__DIR__.'/../../vendor/autoload.php');
		$qrCode=new \Endroid\QrCode\QrCode($url);
		$qrCode->setMargin(4);
		$qrid='data:image/svg+xml;base64,'.base64_encode((new \Endroid\QrCode\Writer\SvgWriter())->write($qrCode)->getString());
	}
	if(!$product['bool_in_bulk'] && $print['package']==="")$print['package']="0";
	$package=$link->query("select * from `package_format` where `ID`='{$print['ID_product']}' AND `ID_package_type`='{$print['package']}'
		AND `hidden_date_time` = (  SELECT MAX(`hidden_date_time`) from package_format where `ID` ='{$print['ID_product']}' AND `ID_package_type`='{$print['package']}')")->fetch_assoc();
	$package['description']=mysql1r("select `description` from `package_description` where `ID`='{$print['ID_product']}' AND `ID_package_type`='{$print['package']}' and `lang`='ca'",$link);
	if($package['description']!="")$product['value']=$package['description'];
	//debugtelegram($product);
	if($product['bool_in_bulk']==1 && $package['weight']<"1000")$u="g";
	if($product['bool_in_bulk']==1 && $package['weight']>="1000"){$u="kg";$package['weight']=$package['weight']/1000;}
	if($product['bool_in_bulk']==0 && $package['units_package']==1)$u="g";
	$nom=urldecode($product['value']);
	if($print['package']=="0")$print['package']="";
	if($print['package']!="")$print['package']=".{$print['package']}";
	if($product['advertencia']!="")$nu1="Advertencia: ".urldecode($product['advertencia'])."\n";
	$nu1.=urldecod($product['Informacio_nutricional']);
	if($print['lot'])$lot="Lot: ".$print['lot'];
	if($print['bbf'])$bbf="Consumir preferent abans de {$print['bbf']}";
	if(!$print['lot']&&!$print['bbf'])$bbf="Consum preferent i lot, veure envàs";
	global $web_etiqueta;
	return strtr(file_get_contents($file),array('$web'=>$web_etiqueta,'$nom'=>$nom,'$unitats'=>$package['weight'], '$u'=>$u,'$ingredients'=>urldecode($product['ingredients']),'$origen'=>urldecode($product['origen']),'$nu1'=>$nu1,'$ID'=>$product['ID'],'$pa'=>$print['package'],'$lot'=>$lot,'$bbf'=>$bbf,'$QRID'=>$qrid));
}
function etiquetaproducte($print,$link){
	$f=urlencode(str_pad($print['ID_product'],4, "0", STR_PAD_LEFT)."_{$print['package']}");
	$nf="e{$f}.svg";
	$svg=generar_etiqueta_producte_svg($print,$link,"q$nf");
	if($svg===false)return false;
	file_put_contents($nf,$svg);
	//debugtelegram($nf,gethostname());
	return($nf);

}
