<?php
require('login.php');
if(!$login){
	echo "Es requereix usuari per entrar aqui.";	
	die;
}	
$r=&$_REQUEST;$s=&$_SESSION;
if(!$_REQUEST['ID']){echo "ID de producte necesari";die;}
require_once('phpgeneral/GoogleTranslate.php');
use \Statickidz\GoogleTranslate;
//tractar $request
$_REQUEST['lang_app']?$lang_app=$_REQUEST['lang_app']:$lang_app='ca';
if(!isset($_REQUEST['ID_package_type']))$_REQUEST['ID_package_type']="1";
if($_REQUEST['do']=='update'){
	$d=array_map("urlencode",$_REQUEST['m']['productnamesurlencoded']);
	//pre($d);
	if($lang_app!='ca'){
		$d['link-rewrite']=mysql1r("Select `link-rewrite` from `productnamesurlencoded` where `ID`='{$_REQUEST['ID']}' and `lang`='ca'",$link);
		$d['bool_available_now']=mysql1r("Select `bool_available_now` from `productnamesurlencoded` where `ID`='{$_REQUEST['ID']}' and `lang`='ca'",$link);
	}
	//debugtelegram($_REQUEST);
	//if($d['Informacio_nutricional']==="")$d['Informacio_nutricional']=" ";
	if($d['bool_available_now']=="")$d['bool_available_now']="0";
	$u="UPDATE `productnamesurlencoded` SET
	`ID`='{$_REQUEST['ID']}',
	`lang`='{$lang_app}',
	`value`='{$d['value']}',
	`origen`='{$d['origen']}',
	`ingredients`='{$d['ingredients']}',
	`advertencia`='{$d['advertencia']}',
	`link-rewrite`='{$d['link-rewrite']}',
	`bool_available_now`='{$d['bool_available_now']}',
	`description_short`='{$d['description_short']}',
	`Informacio_nutricional`='{$d['Informacio_nutricional']}'
	where `ID`='{$_REQUEST['ID']}' and `lang`='$lang_app'";
	//debugtelegram($u);
	$link->query($u)or die($u.$link->error);
	$value=str_replace("'","\'",urldecode($d['value']));
	$u="Update `productnames` SET `ID`='{$_REQUEST['ID']}',`value`='$value'
	 WHERE `ID`='{$_REQUEST['ID']}' and `lang`='$lang_app'";
	if($lang_app=='ca') $link->query($u)or die($u.$link->error);
}
if($_REQUEST['do']=="bool_bulk"){
	$sql="update tarifes set bool_in_bulk='{$_REQUEST['bool_bulk']}' where `ID`= '{$_REQUEST['ID']}' AND  `ultimpreu`='1'";
	//pre($sql);
	$link->query($sql);
}

if($_REQUEST['do']=="new_package_type"){
	//pre($_REQUEST);
	$insert="INSERT INTO `package_format` (`ID`,`ID_package_type`, `bool_in_a_box`, `units_package`, `weight`, `gross_weight`, `height`, `lenght`, `width`, `fromlot`, `hidden_date_time`)
VALUES ('{$r['ID']}', '{$r['ID_package_type']}', '{$r['bool_in_a_box']}', '{$r['units_package']}', '{$r['weight']}', '{$r['gross_weight']}', '{$r['height']}', '{$r['lenght']}', '{$r['width']}', '{$r['fromlot']}', now());";
	$link->query($insert)or die($insert.$link->error);
}
if($r['do']=="package_description"){
	if(isset($r['description']))$description=urlencode($r['description']);
	if(isset($r['weight_text']))$weight_text=urlencode($r['weight_text']);
	$delete="DELETE from `package_description` where `ID`='{$r['ID']}' AND `ID_package_type`={$r['ID_package_type']} AND `lang`='{$lang_app}' ";
	$insert="Insert into `package_description` (`ID`,`ID_package_type`,`lang`, `description`, `weight_text`) VALUES ('{$r['ID']}','{$r['ID_package_type']}','{$lang_app}','$description','$weight_text') ";
	$link->query($delete)or die($delete.$link->error);
	$link->query($insert)or die($insert.$link->error);

}
if($r['do']=="package_shop"){
	//pre($r);
	$link->query("delete from package_botiga where `ID`='{$r['ID']}' and `ID_package_type`='{$r['ID_package_type']}' and `ID_shop`={$r['ID_shop']}");
	if(is_numeric($r["priority"]) && intval($r['priority']))
		$priority=$r['priority'];
	else $priority=0;
	if($r['aviable']=="yes"){
		$insert="insert into `package_botiga` (`ID`,`ID_package_type`,`ID_shop`,`Priority`) values ('{$r['ID']}','{$r['ID_package_type']}','{$r['ID_shop']}',$priority)";
		$link->query($insert)or die($insert.$link->error);

	}
}
//pre($_REQUEST); 
if($r['do']=='IVA'){
	$update="update `tarifes` SET `tipusiva`='{$r['IVA']}' where ID='{$r['ID']}'AND ultimpreu='1';";
	$link->query($update);
	debugtelegram("l'usuari [{$s['user']}] ha canviat l'iva del codi {$r['ID']} ",$_SERVER['HTTP_HOST'],"1001429283981");
	//debugtelegram($_SESSION);
}
if($r['do']=='package_pvp'){
	$pvp=trim($r['pvp']);
	$delete="delete from package_pvp where ID='{$r['ID']}'and ID_package_type='{$r['ID_package_type']}'";
	$insert="insert into `package_pvp` (ID, ID_package_type, pvp) values ('{$r['ID']}','{$r['ID_package_type']}','{$pvp}')";
	$nom=mysql1r("select `value` from `productnamesurlencoded` where `ID`='{$r['ID']}'and `lang`='ca'",$link);
	$nom.=" ".mysql1r("select `weight` from `package_format` where `ID`='{$r['ID']}'and `ID_package_type`='{$r['ID_package_type']}' AND `hidden_date_time` = (  SELECT MAX(`hidden_date_time`) from package_format where `ID` ='{$r['ID']}' AND `ID_package_type`='{$r['ID_package_type']}')",$link)."g";
	$link->query($delete);
	$link->query($insert);
	debugtelegram("l'usuari [{$s['user']}] ha canviat el pvp del codi {$r['ID']}.{$r['ID_package_type']} $nom \n ara és $pvp",$_SERVER['HTTP_HOST'],"1001429283981");
	debugtelegram("l'usuari [{$s['user']}] ha canviat el pvp del codi {$r['ID']}.{$r['ID_package_type']} $nom \n al proper canvi d'hora ho teniu a les bascules ",$_SERVER['HTTP_HOST'],"550677873");


}
if($r['do']=='delete_package_pvp'){
	$delete="delete from package_pvp where ID='{$r['ID']}'and ID_package_type='{$r['ID_package_type']}'";
	$link->query($delete);
	debugtelegram("l'usuari [{$s['user']}] ha eliminat el pvp del codi {$r['ID']}.{$r['ID_package_type']} \n ara es calcula per defecte",$_SERVER['HTTP_HOST'],"1001429283981");
	//debugtelegram("l'usuari [{$s['user']}] ha eliminat el pvp del codi {$r['ID']}.{$r['ID_package_type']} \n ara es calcula per defecte",$_SERVER['HTTP_HOST'],"550677873");
}

if($lang_app!='ca'){
	//miro si la fitxa en l' altre idioma existeix, si no la tradueixo i la poso
	$old=mysql2vectorassoc("Select * from productnamesurlencoded
		where  `ID` ='{$_REQUEST['ID']}' and `lang`='{$_REQUEST['lang_app']}'",$link);	
	$ca=mysql2vectorassoc("Select * from productnamesurlencoded
		where  `ID` ='{$_REQUEST['ID']}' and `lang`='ca'  ",$link);
	
	try{
		if(!isset($old)){
		//echo "tradueixo";
		//die;
		$t=new GoogleTranslate();
		$v= urlencode($t->translate('ca',$lang_app,urldecode($ca['value'])));
		$o= urlencode($t->translate('ca',$lang_app,urldecode($ca['origen'])));
		$i=urlencode( $t->translate('ca',$lang_app,urldecode($ca['ingredients'])));
		$a=urlencode( $t->translate('ca',$lang_app,urldecode($ca['advertencia'])));
		$d=urlencode( $t->translate('ca',$lang_app,urldecode($ca['description_short'])));
		$in="INSERT INTO `productnamesurlencoded` (`ID`,`lang`,`value`,`origen`,`ingredients`,`advertencia`,`link-rewrite`,`available_now`,`description_short`)
			VALUES ('{$_REQUEST['ID']}','$lang_app','$v','$o','$i','$a','{$ca['link-rewrite']}','{$ca['available_now']}','$d') ";
		$link->query($in)or die(errorr($in.' '.$link->error));
		//echo "$in";

		}
		}catch(Exception $e){
			echo "No he pogut traduïr el producte al $lang_app";
		}

	$Producte=urldecode($ca['value'])." en $lang_app";
}
else $Producte=urldecod(mysql1r("Select value from productnamesurlencoded where `ID` ='{$_REQUEST['ID']}' and `lang`='ca' ",$link));
estil_capcal_fitxa("$Producte [{$_REQUEST['ID']}]",$_SERVER['REQUEST_URI']);
//select idiomastyle="display: inline-block; vertical-align: top;"
divclass('col','style="display: inline-block; vertical-align: top;"');
form("./fitxa.php","GET");

hidden('ID',$_REQUEST['ID']);
hidden('lang_app',$lang_app);
if(isset($_REQUEST['ID_package_type']))hidden('ID_package_type',$_REQUEST['ID_package_type']);
else hidden('ID_package_type',1);
hidden('do',"IVA");
$IVA=mysql2array("select `ID`, concat(Value, '% ', Description) from IVA",$link);
select2llistes_js("IVA",$IVA[0],$IVA[1],mysql1r("SELECT tipusiva from tarifes where ID={$r['ID']} and ultimpreu=1",$link));
tform();
form('./fitxa.php','GET');
$lang=mysql2array("select Value,Name from lang",$link);
hidden('ID',$_REQUEST['ID']);
if(isset($r['ID_package_type']))hidden('ID_package_type',$_REQUEST['ID_package_type']);
echo "tria idioma per editar fitxa:<br/> ";
select2llistes_js("lang_app",$lang[0],$lang[1],$lang_app);
tform();
//formulari idioma
$m['do']='update';
$m['id']=$_REQUEST['ID'];
$m['table']='productnamesurlencoded';
$m['lang_app']=$lang_app;
$m['traductor']=true;
form('./fitxa.php','POST');
hidden("lang_app",$lang_app);
hidden('do',$m['do']);
hidden('ID',$_REQUEST['ID']);
tableform($m,$link);
sumit1('modifica');
tform();
tdiv();
	divclass('col','style="display: inline-block; vertical-align: top;"');
h3("Formulari Paquets");
	$sqlantic="select * from package_format where `ID` ='{$_REQUEST['ID']}' AND `ID_package_type`='".(isset($_REQUEST['ID_package_type'])?$_REQUEST['ID_package_type']:1)."' AND `hidden_date_time` = (  SELECT MAX(`hidden_date_time`) from package_format where `ID` ='{$_REQUEST['ID']}' AND `ID_package_type`='".(isset($_REQUEST['ID_package_type'])?$_REQUEST['ID_package_type']:1)."') ";
		//pre($sqlantic);
	$antic=$link->query($sqlantic)->fetch_assoc();
	if(!isset($antic)){
		$existeixpaquet=false;
		p("no hi ha cap paquet així,\n es mostren dades genèriques".$link->error);
	}else $existeixpaquet=true;
	$antic['bool_bulk']=$link->query("select bool_in_bulk from tarifes where `ID` ='{$_REQUEST['ID']}' AND `ultimpreu`='1';")->fetch_assoc()['bool_in_bulk'];

	//pre($antic);
	$valors_defecte=$link->query("select * from package_type where `ID`='{$_REQUEST['ID_package_type']}'")->fetch_assoc();
	$antic['description']=urldecod($link->query("select description from package_description where `ID`='{$_REQUEST['ID']}' AND `ID_package_type`='{$r['ID_package_type']}' AND `lang`='$lang_app'")->fetch_assoc()['description']);
	$antic['weight_text']=urldecod($link->query("select weight_text from package_description where `ID`='{$_REQUEST['ID']}' AND `ID_package_type`='{$r['ID_package_type']}' AND `lang`='$lang_app'")->fetch_assoc()['weight_text']);
	if($antic['description']=="" && $antic['weight_text']=="" && $lang_app!="ca"){
		$t=new GoogleTranslate();
		$ca=mysql2vectorassoc("Select * from package_description where  `ID` ='{$_REQUEST['ID']}' and `ID_package_type`='{$r['ID_package_type']}' and `lang`='ca'  ",$link);
		try{
			if($ca['description']!="")
				$description=urlencode($t->translate('ca',$lang_app,urldecode($ca['description'])));
			if($ca['weight_text']!="")
				$weight_text=urlencode($t->translate('ca',$lang_app,urldecode($ca['weight_text'])));
			$insert="Insert into `package_description` (`ID`,`ID_package_type`,`lang`, `description`, `weight_text`) VALUES ('{$r['ID']}','{$r['ID_package_type']}','{$lang_app}','$description','$weight_text') ";
			if ($description!="" && $weight_text!=""){
				$link->query($insert)or die(errorr($in.' '.$link->error));
				$antic['description']=urldecode($description);
				$antic['weight_text']=urldecode($weight_text);
			}
		}catch(Exception $e){
			echo "No he pogut traduïr package_description al $lang_app";
		}
		
	}
	//pre($valors_defecte);
	$m['id']=$_REQUEST['ID'];
	$m['table']='package_format';
	$m['lang_app']=$lang_app;
	$m['traductor']=true;
	form('./fitxa.php','GET');//formulari package_type
	hidden('ID',$_REQUEST['ID']);
	hidden("lang_app",$lang_app);
	$granel = Array (1=>"Granel", 0=>"En unitats");
	//pre($antic);
	$box = Array (1=>"En caixes", 0=>"En sacs");
	if($antic["bool_bulk"]==0)$package_type=mysql2array("Select `ID`, concat (`ID`,' ',`Value`) from package_type order by ID",$link);
	else $package_type=mysql2array("Select `ID`, concat (`ID`,' ',`Value`) from package_type where `ID` > '0' Order by ID",$link);
	echo "Tipus de paquet";br(); select2llistes_js("ID_package_type",$package_type[0],$package_type[1],(isset($_REQUEST['ID_package_type'])?$_REQUEST['ID_package_type']:1));
	tform();
	form('./fitxa.php','GET');//formulari descripció
	hidden("lang_app",$lang_app);
	hidden('ID',$_REQUEST['ID']);
	hidden('do','package_description');
	if(isset($_REQUEST['ID_package_type']))hidden('ID_package_type',$_REQUEST['ID_package_type']);
	else hidden('ID_package_type',1);
	echo "Descripció del paquet en $lang_app: ";br();text_js("description",$size=30,$antic["description"]);br();
	echo "Descripció del pes en $lang_app: ";br();text_js("weight_text",$size=30,$antic["weight_text"]);br();
	sumit1("modifica textos");
	tform();
if($lang_app=="ca"){
	form('./fitxa.php','GET');//formulari granel
	hidden('ID',$_REQUEST['ID']);
	hidden("lang_app",$lang_app);
	hidden('do',"bool_bulk");
	echo "Granel, si es ven per pes";br(); select1llista_js("bool_bulk",$granel,$antic['bool_bulk']);
	tform();
	form('./fitxa.php','GET'); //formulari mides fisiques
	hidden('ID',$_REQUEST['ID']);
	hidden('lang_app',$lang_app);
	hidden('do',"new_package_type");
	if(isset($_REQUEST['ID_package_type']))hidden('ID_package_type',$_REQUEST['ID_package_type']);
	else hidden('ID_package_type',1);
	echo "Format que ens arriba: ";br(); select1llista("bool_in_a_box",$box,$antic['bool_in_a_box']?$antic['bool_in_a_box']:"0");br();
	echo "Unitats al paquet:";
	br();text("units_package",$size=30,$antic["units_package"]?$antic["units_package"]:'1');br();
	//if($antic['bool_bulk']==1)
	echo "Pes net en grams!: ";
	br();text("weight",$size=30,$antic["weight"]?$antic["weight"]:$valors_defecte['weight']);br();
	echo "Pes brut en grams!: ";br();text("gross_weight",$size=30,$antic["gross_weight"]?$antic["gross_weight"]:$valors_defecte['gross_weight']);br();
	echo "Alçada en mil·límetres!: ";br();text("height",$size=30,$antic["height"]?$antic["height"]:$valors_defecte['height']);br();
	echo "Longitut en mil·límetres!: ";br();text("lenght",$size=30,$antic["lenght"]?$antic["lenght"]:$valors_defecte['lenght']);br();
	echo "Amplada en mil·límetres!: ";br();text("width",$size=30,$antic["width"]?$antic["width"]:$valors_defecte['width']);br();
	echo "Mides i format a partir del lot: ";br();text("fromlot",$size=30);br();
	//tableform($m,$link);
	sumit1('crea paquet');
tform();
	tdiv();
	if($existeixpaquet){
		divclass('col','style="display: inline-block; vertical-align: top;"');
		h3("disponibilitat del<br/> paquet i prioritat");
		$botigues=mysql2array("select * from botigues",$link);
		//pre($botigues);echo"foreach <br/>";
		foreach ($botigues[1] as $k=>$b){
			form("./fitxa.php","GET");//formulari disponibilitat
			hidden('ID',$_REQUEST['ID']);
			hidden('lang_app',$lang_app);
			if(isset($_REQUEST['ID_package_type']))hidden('ID_package_type',$_REQUEST['ID_package_type']);
			else hidden('ID_package_type',1);
			hidden('do',"package_shop");
			hidden('ID_shop',$k);
			echo "$b:";br();
			$anticshop=$link->query("SELECT * from package_botiga where `ID`='{$r['ID']}' and `ID_package_type`='{$r['ID_package_type']}' AND `ID_shop`=$k")->fetch_assoc();
			chek_js("aviable",isset($anticshop));
			number_js("priority",3,200,1,"no",$anticshop["Priority"]);
			br();
			tform();
		}
		tdiv();
		divclass('col','style="display: inline-block; vertical-align: top;"');
		form("./fitxa.php","GET");
		hidden('ID',$_REQUEST['ID']);
		hidden('lang_app',$lang_app);
		hidden('ID_package_type',$_REQUEST['ID_package_type']);
		hidden('do',"package_pvp");
		$antic['pvp']=mysql1r("select pvp from package_pvp where ID='{$r['ID']}' AND `ID_package_type`='{$r['ID_package_type']}'",$link);
		//pre($antic);
		h3("Sobreescriure pvp");
		if($antic['bool_bulk']==1)$antic['weight']=$antic['weight']/1000;
		if($antic['bool_bulk']==0)$antic['weight']=$antic['units_package'];
		if(is_numeric($antic['pvp']))$calculat=$pvp;
		else {	
			$pattern = "/(pvp|pv\d+)(ii)?\*(\d+\.\d+)/";
			$matches = [];
			if ((is_string($antic['pvp']))&&(preg_match($pattern, $antic['pvp'], $matches))) {
				$tarifa=$matches[1];
				$ii=!empty($matches[2]);
				$guany=$matches[3];
				$preu=mysql1r("select {$tarifa} from tarifes where ID='{$r['ID']}' and ultimpreu='1'",$link);
				$tipusiva=mysql1r("select tipusiva from tarifes where ID='{$r['ID']}' and ultimpreu='1'",$link);
				if($ii)$iva=mysql1r("select Value from IVA where ID='{$tipusiva}'",$link);
				else $iva=0;
				$calculat=$antic['weight']*($preu*(1+($iva/100))*$guany);
				p("calcul=(unitats/pesenkg)*(preutarifa+(IVA si ii))*guany)");
				p("calcul= {$antic['weight']}*($preu*(1+($iva/100))*$guany)");
			}
			else $calculat="ERROR a la formula!";
		}
		if(!isset($antic['pvp']))$calculat="No hi ha preu sobreescrit";
		$projectat=round(mysql1r("select pvp from tarifes where ID='{$r['ID']}' and ultimpreu='1'",$link)*$antic['weight'],2);
		p("preu projectat: $projectat, preu ara: $calculat");
		p("podeu posar el preu directe o bé una formula tipus pv1ii*1.10 <br/>
		 on pv1ii és el preu de la tarifa 1 'pv1' més l'iva que toqui 'ii'");
		text_js("pvp",$size=30,$antic["pvp"]?$antic["pvp"]:$projectat);
		a("./fitxa.php?do=delete_package_pvp&ID={$r['ID']}&ID_package_type={$r['ID_package_type']}","X");br();
		sumit1("nou preu");
		//pre($antic);
		tform();
		tdiv();
	}
}

//pre($_SESSION); 	
require('tanca.php');
?>

