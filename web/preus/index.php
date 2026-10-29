<?php
/*
Programa canvi de preus


He copiat de la base de dades d'hydra els articles i els  he importat així...

Insert into tarifes (ID, `user`, `id_dealer`, data_hora, id_familia,bool_in_bulk,cost_price,pvp,pvpi,pv1,pv1i,pv2,pv2i,pv3,pv3i,historic,ultimpreu,tipusiva) 
select `CODIGO`, 'hydra','0',  NOW(), `CODI_FAM`, IF(STRCMP(`CODI_PES`,'w')= 0, 1, 0),(`EUROS`/1.83),`EUROS`,1.83, 
((`EUROS`/1.83)*1.36), 1.36, ((`EUROS`/1.83)*1.21),1.21, ((`EUROS`/1.83)*1.15),1.15,1,1,`TIPO_IVA`
FROM `hydra_origen`

Insert into productnames (`ID`,value)
select `CODIGO`, `DES_PLU1` FROM `hydra_origen`
UPDATE `tarifes` SET

`id_familia` = '1'

WHERE `id_familia` = '0'


TODO

poder triar el camp que ordenes
a nou.php que recordi l'ultim nou de la sessió per poder estalviar pases.

*/

//error_reporting(-1);
require('login.php');

if($login){
    estil_capcal("Canvis de preu");
divclass('sortir');
// 	a("{$_SERVER['SCRIPT_NAME']}?tancar=true","sortir");
    ainf("./nou.php","nou",'_new');echo" ";
	a("./?tancar=true","sortir");
	tdiv();


        //tracto el filtre
	$ldealer=mysql2array('select id, value from dealers ORDER by `value`',$link);
	$lfamilia=mysql2array('select ID, `value` from families ORDER by `ID`',$link);
// 	if($_REQUEST['showfilter'])$_SESSION['showfilter']=$_REQUEST['showfilter'];
	//tracto formulari de filtre
	//
	if(!isset($_REQUEST['ID'])&&!isset($_REQUEST['pag'])){//canviar de pàgina no toca els filtres
	    if($_REQUEST['cerca']!='')$_SESSION['cerca']=$_REQUEST['cerca'];
        else unset($_SESSION['cerca']);
	    if($_REQUEST['f']['id_dealer']!='0')$_SESSION['f']['id_dealer']=$_REQUEST['f']['id_dealer'];
        else unset($_SESSION['f']['id_dealer']);
	    if($_REQUEST['f']['id_familia']!='0')$_SESSION['f']['id_familia']=$_REQUEST['f']['id_familia'];
        else unset($_SESSION['f']['id_familia']);
    }
  // pre();print_r($_REQUEST);print_r($_SESSION);tpre();

    //formulari filtre
// 	if($_SESSION['showfilter']=='no')a($_SERVER['DOCUMENT_URI'].'?showfilter=yes',h3cr('Mostra filtres'));
// 	else {
// 		a($_SERVER['DOCUMENT_URI'].'?showfilter=no',h3cr('Oculta filtres'));
// 		divclass('block');
		form($_SERVER['DOCUMENT_URI'].'#'.$c[0],'POST');
		divclass('row');
        divclass('block');
		echo'Producte:';//<input name="filtre" value="" autofocus />';
		br();
// 		text_js('f['producte']',15,$_SESSION['f']['producte'],255,yes,(((isset($_REQUEST['f']['producte'])||$_REQUEST[user])&&!isset($_REQUEST['ID']))?true:false));
    ?>     <input type="search" name="cerca"  placeholder="Entra codi o texte a buscar" value="<?php echo htmlspecialchars($_SESSION['cerca']??'')?>" class="form-control" 
                  onfocus="this.select();"<?php echo ((isset($_REQUEST['cerca'])||$_REQUEST['user'])&&!isset($_REQUEST['ID']))?"autofocus":"" ?> autocomplete="off" onChange="this.form.submit()"  /><?php
		tdiv();divclass('block');
		echo'Família';
		br();
        tform();form($_SERVER['DOCUMENT_URI'].'#'.$c['0'],'post');
		select2llistes_js('f[id_familia]',$lfamilia['0'],$lfamilia['1'],$_SESSION['f']['id_familia'],'yes',($_REQUEST['f']['id_familia']&&!isset($_REQUEST['ID']))?true:false);
		tdiv();divclass('block');
		ainf('prov.php','Proveïdor','_new');
		br();
        tform();form($_SERVER['DOCUMENT_URI'].'#'.$c[0],'post');
		select2llistes_js('f[id_dealer]',$ldealer['0'],$ldealer['1'],$_SESSION['f']['id_dealer'],'yes',($_REQUEST['f']['id_dealer']&&!isset($_REQUEST['ID']))?true:false);
		tdiv();divclass('block');
		sumit1('filtre');
		tform();
		tdiv();
		tdiv();
// 	}
	//tracto formulari modificació
	if($_POST['ID']){
// 		pre();print_r($_POST);tpre();
		//primer busquem que ha canviat de l'ultima vegada i a partir d'aqui modificar els altres preus a partir de les formules. 
		//el sistema només permet canviar una sola entrada cada vegada aixi que en principi es fàcil, si canvia el preu de cost, s'agafen els pv*i per calcular els preus de venda. 
		//si canvia algun pv* es canvia el pv*i
		//si canvia el pv*i canviem el preu de venda.
		$ultimpreu="SELECT `ID`, cost_price,id_familia,
        pvp,pvpi, pv1,pv1i,pv2,pv2i,pv3,pv3i, historic,tipusiva,id_dealer,pv4,pv4i,bool_in_bulk
			     FROM `tarifes` where ultimpreu=1 and ID='{$_POST['ID']}';
		";
		$ultim=mysql2array($ultimpreu,$link);
        $nom=urldecod(mysql1r("select value from productnames where ID={$_REQUEST['ID']}",$link));
        //INSERT INTO `tarifes` (`ID`, `id_gestio`, `data_hora`, `user`, `id_dealer`, `id_familia`, `Description`, `bool_in_bulk`, `cost_price`, `pvp`, `pvpi`, `pv1`, `pv1i`, `pv2`, `pv2i`, `pv3`, `pv3i`, `ultimpreu`)
        //VALUES ('9', '0009', now(), 'root', '1', '1', 'marcona crua', '1', '8.00', '14.33', '1.8', '9', '', '8.5', '', '8.3', '', '1');
        $insert="INSERT INTO `tarifes` (`ID`,
                        `data_hora`,`user`, `id_dealer`, `id_familia`, 
                        `cost_price`, `pvp`,`pvpi`, `pv1`, 
                        `pv1i`, `pv2`, `pv2i`, `pv3`, `pv3i`,`historic`,ultimpreu,tipusiva,pv4,pv4i,bool_in_bulk)";
	$insert.="Values ('{$_POST['ID']}',now(),'{$_SESSION['user']}','{$_POST['id_dealer']}','{$ultim[2][0]}','{$_POST['cost_price']}',";
        foreach ($_POST as $k=>$v){
//   pre();print_r($k.'--'.$v);tpre();
			switch ($k){
			case 'cost_price':
                    if($v!=$ultim[1][0]){//ha canviat preu cost canvia pvp, pv1, pv2,pv3
//                         echo 'cost_price';
                        $pvpi=$_POST['pvpi']>1?$_POST['pvpi']:1.8;
                        $pv1i=$_POST['pv1i']>1?$_POST['pv1i']:1.8;
                        $pv2i=$_POST['pv2i']>1?$_POST['pv2i']:1.8;
                        $pv3i=$_POST['pv3i']>1?$_POST['pv3i']:1.8;
                        $pv4i=$_POST['pv4i']>1?$_POST['pv4i']:1.1;
                        $pvp=round($v*$pvpi,2);//si es volen posar regles de mínim de marge aqui es un bon lloc
                        $pv1=round($v*$pv1i,2);
                        $pv2=round($v*$pv2i,2);
                        $pv3=round($v*$pv3i,2);
                        $pv4=round($v*$pv4i,2);
                        $insert.="'$pvp','$pvpi','$pv1','$pv1i','$pv2','$pv2i','$pv3','$pv3i',";
                        $telegramme=urlencode("Hola l'usuari {$_SESSION['user']} acaba de canviar el preu de cost de {$_REQUEST['ID']} $nom
preu cost antic {$ultim[1][0]}
preu cost nou $v
Els preus de venda queden així 
pvp $pvp
Tarifa 1000 $pv1
Tarifa 2000 $pv2
Tarifa 3000 $pv3
Tarifa 10 $pv4
");
                    }
				break;
            case 'pvp':
                    if($v!=$ultim[3][0]){
                        //echo "pvp>$v";
//                         $vstd=floatval(str_replace(',','.'.$v));
                        $pvp=round($v,2);
                        $pvpi=round($v/$ultim[1][0],2);
                        $insert.="'$pvp','$pvpi','{$ultim[5][0]}','{$ultim[6][0]}','{$ultim[7][0]}','{$ultim[8][0]}','{$ultim[9][0]}','{$ultim[10][0]}',";
                        $telegramme=urlencode("Hola l'usuari {$_SESSION['user']} acaba de canviar el preu venta al detall de {$_REQUEST['ID']} $nom \n nou preu : $pvp");
                    }
                    break;
            case 'pv1':
                    if($v!=$ultim[5][0]){
//                         $v=str_replace(',','.'.$v);
                        $pv1=round($v,2);
                        $pv1i=round($v/$ultim[1][0],2);
                        $telegramme=urlencode("Hola l'usuari {$_SESSION['user']} acaba de canviar el preu venta a la tarifa 1000 de {$_REQUEST['ID']} $nom \n nou preu : $pv1");
                        $insert.="'{$ultim[3][0]}','{$ultim[4][0]}','$pv1','$pv1i','{$ultim[7][0]}','{$ultim[8][0]}','{$ultim[9][0]}','{$ultim[10][0]}',";
                    }
                    break;
            case 'pv2':
                   if($v!=$ultim[7][0]){//
//                         $$v=str_replace(',','.'.$v);
                        $pv2=round($v,2);
                        $pv2i=round($v/$ultim[1][0],2);
                        $insert.="'{$ultim[3][0]}','{$ultim[4][0]}','{$ultim[5][0]}','{$ultim[6][0]}','$pv2','$pv2i','{$ultim[9][0]}','{$ultim[10][0]}',";
                        $telegramme=urlencode("Hola l'usuari {$_SESSION['user']} acaba de canviar el preu venta a la tarifa 2000 de {$_REQUEST['ID']} $nom \n nou preu : $pv2");

                    }
                    break;
           case 'pv3':
                    if($v!=$ultim[9][0]){
//                         $v=str_replace(',','.'.$v);
                        $pv3=round($v,2);
                        $pv3i=round($v/$ultim[1][0],2);
                        $insert.="'{$ultim[3][0]}','{$ultim[4][0]}','{$ultim[5][0]}','{$ultim[6][0]}','{$ultim[7][0]}','{$ultim[8][0]}','$pv3','$pv3i',";
                        $telegramme=urlencode("Hola l'usuari {$_SESSION['user']} acaba de canviar el preu venta a la tarifa 3000 de {$_REQUEST['ID']} $nom \n nou preu : $pv3");
                    }
                    break;
           case 'pv4':
                    if($v!=$ultim[14][0]){

                    $pv4=round($v,2);
                    $pv4i=round($v/$ultim[1][0],2);
                    $insert.="'{$ultim[3][0]}','{$ultim[4][0]}','{$ultim[5][0]}','{$ultim[6][0]}','{$ultim[7][0]}','{$ultim[8][0]}','{$ultim[9][0]}','{$ultim[10][0]}',";
                       $telegramme=urlencode("Hola l'usuari {$_SESSION['user']} acaba de canviar el preu venta a la tarifa 10 de {$_REQUEST['ID']} $nom \n nou preu : $pv4");
                     }
                    break;
           case 'pv4i':
                    if($v!=$ultim[15][0]){
                    $pv4i=round($v,2);
                    $pv4=round($v*$ultim[1][0],20);
                    $insert.="'{$ultim[3][0]}','{$ultim[4][0]}','{$ultim[5][0]}','{$ultim[6][0]}','{$ultim[7][0]}','{$ultim[8][0]}','{$ultim[9][0]}','{$ultim[10][0]}',";
                    $telegramme=urlencode("Hola l'usuari {$_SESSION['user']} acaba de canviar el MARGE COMERCIAL  a la tarifa 10 de {$_REQUEST['ID']} $nom \n nou preu : $pv4 \n nou marge comercial $pv3i");     
}
                    break;
           case 'pvpi':
                    if($v!=$ultim[4][0]){
                        $pvpi=round($v,2);
                        $pvp=round($ultim[1][0]*$v,2);
                        $insert.="'$pvp','$pvpi','{$ultim[5][0]}','{$ultim[6][0]}','{$ultim[7][0]}','{$ultim[8][0]}','{$ultim[9][0]}','{$ultim[10][0]}',";
                        $telegramme=urlencode("Hola l'usuari {$_SESSION['user']} acaba de canviar el MARGE COMERCIAL  a la tarifa de venda publica de {$_REQUEST['ID']} $nom \n nou preu : $pvp \n nou marge comercial $pvpi");

                    }
                    break;
         break;
            case 'pv1i':
                    if($v!=$ultim[6][0]){
//                         $v=str_replace(',','.'.$v);
                        $pv1i=round($v,2);
                        $pv1=round($ultim[1][0]*$v,2);
                        $insert.="'{$ultim[3][0]}','{$ultim[4][0]}','$pv1','$pv1i','{$ultim[7][0]}','{$ultim[8][0]}','{$ultim[9][0]}','{$ultim[10][0]}',";
                        $telegramme=urlencode("Hola l'usuari {$_SESSION['user']} acaba de canviar el MARGE COMERCIAL  a la tarifa 1000 de {$_REQUEST['ID']} $nom \n nou preu : $pv1 \n nou marge comercial $pv1i");                    }
                    break;
            case 'pv2i':
                   if($v!=$ultim[8][0]){//
//                         $$v=str_replace(',','.'.$v);
                        $pv2i=round($v,2);
                        $pv2=round($ultim[1][0]*$v,2);
                        $insert.="'{$ultim[3][0]}','{$ultim[4][0]}','{$ultim[5][0]}','{$ultim[6][0]}','$pv2','$pv2i','{$ultim[9][0]}','{$ultim[10][0]}',";
                        $telegramme=urlencode("Hola l'usuari {$_SESSION['user']} acaba de canviar el MARGE COMERCIAL  a la tarifa 2000 de {$_REQUEST['ID']} $nom \n nou preu : $pv2 \n nou marge comercial $pv2i");
                    }
                    break;
           case 'pv3i':
                    if($v!=$ultim[10][0]){
//                         $v=str_replace(',','.'.$v);
                        $pv3i=round($v,2);
                        $pv3=round($ultim[1][0]*$v,2);
                        $insert.="'{$ultim[3][0]}','{$ultim[4][0]}','{$ultim[5][0]}','{$ultim[6][0]}','{$ultim[7][0]}','{$ultim[8][0]}','$pv3','$pv3i',";

                        $telegramme=urlencode("Hola l'usuari {$_SESSION['user']} acaba de canviar el MARGE COMERCIAL  a la tarifa 3000 de {$_REQUEST['ID']} $nom \n nou preu : $pv3 \n nou marge comercial $pv3i");  
                    }
                    break; 
            case 'id_dealer':
                    if($v!=$ultim[13][0]){
                        $insert.="'{$ultim[3][0]}','{$ultim[4][0]}','{$ultim[5][0]}','{$ultim[6][0]}','{$ultim[7][0]}','{$ultim[8][0]}','{$ultim[9][0]}','{$ultim[10][0]}',";
                        $dealer=urldecode(mysql1r("select Value from dealers where ID={$v}",$link));
                        $telegramme=urlencode("Hola l'usuari {$_SESSION['user']} diu que per a  {$_REQUEST['ID']} $nom té un nou proveïdor: \n $dealer");
                    }
                    break;

			}
		}
        if(!isset($pv4))$pv4=$ultim[14][0];
	if(!isset($pv4i))$pv4i=$ultim[15][0];
        $insert.="'".($ultim[11][0]+1)."','1','".($ultim[12][0])."','$pv4','$pv4i','{$ultim[16][0]}');";
		$update="UPDATE `tarifes` SET
		`ultimpreu` = '0'
		WHERE `ID` = {$_POST['ID']} and historic = {$ultim[11][0]} ";
	//insert del preu nou i desactivar l'anterior en una sola transacció, així mai queden dos ultimpreu=1
	$link->begin_transaction();
	try{
		mysqli_query($link,$insert)or throw new Exception($insert.mysqli_error($link));
		mysqli_query($link,$update)or throw new Exception($update.mysqli_error($link));
		$link->commit();
	}catch(Throwable $e){
		$link->rollback();
		die(errorr($e->getMessage()));
	}
	

        global $telegram_canal_preus;
        if($telegramtoken!='' && $telegram_canal_preus!='')
            $tlgrm= file_get_contents("https://api.telegram.org/bot$telegramtoken/sendMessage?chat_id=$telegram_canal_preus&text=$telegramme");
	}

//     pre();print_r($_SESSION);tpre();
	$from="	FROM `tarifes`".
    (!isset($_SESSION['cerca'])?' where ultimpreu=1 ':",
    productnames where `tarifes`.`ID`=productnames.ID and ( value like
    '%".str_replace(' ','%',$_SESSION['cerca'])."%'
    or `tarifes`.`ID` like '%".trim($_SESSION['cerca'])."%'
    )and lang='$lang' and ultimpreu='1'").
     //"where ultimpreu=1".
	//($_SESSION['f']['producte']==''?'':" AND Description like '%".str_replace(' ','%',$_SESSION['f']['producte']."%'")).
	//($_SESSION['f']['producte']==''?'':" AND ID=(select ID from productnames where value like '%".str_replace(' ','%',$_SESSION['f']['producte']."%'" )." and lang='$lang')").
	((!isset($_SESSION['f']['id_familia']))?'':" AND id_familia='{$_SESSION['f']['id_familia']}'").
	((!isset($_SESSION['f']['id_dealer']))?'':" AND id_dealer='{$_SESSION['f']['id_dealer']}'");
	//si fiques un or amb ultimpreu=0 podras buscar id_dealers antics també :)

	//paginació de tots els productes, els últims preus canviats primer
	$perpagina=50;
	$total=mysql1r("SELECT count(*) $from",$link);
	$pagines=max(1,ceil($total/$perpagina));
	$pag=min(max(1,intval($_REQUEST['pag']??1)),$pagines);
	$q="SELECT `tarifes`.`ID`,id_dealer,bool_in_bulk, cost_price, pvp,pvpi, pv1,pv1i,pv2,pv2i,pv3,pv3i,data_hora,pv4,pv4i $from
	order by `tarifes`.`data_hora` desc, `tarifes`.`ID` limit ".(($pag-1)*$perpagina).",$perpagina";
	$paginacio=function() use ($pag,$pagines,$total){
		$url=strtok($_SERVER['REQUEST_URI'],'?');
		divclass('paginacio');
		if($pag>1){a("$url?pag=1","« primera");echo " ";a("$url?pag=".($pag-1),"‹ anterior");}
		echo " pàgina $pag de $pagines ($total productes) ";
		if($pag<$pagines){a("$url?pag=".($pag+1),"següent ›");echo " ";a("$url?pag=$pagines","última »");}
		tdiv();
	};
	$paginacio();

	//$ldealer=mysql2array('select id, value from dealers ORDER by `value`',$link); l'he posat al inici de la plana
// 	   echo $q;
    $i=0;
	$d=mysqli_query($link,$q)or die (errorr($q.mysqli_error($link)));
	while($c=mysqli_fetch_row($d)){
		//pre();	print_r($c); tpre();
		foreach ($c as $v){
			//echo "$c -- > $v";
		}
		form($_SERVER['DOCUMENT_URI'].'#'.$c[0],'post');
		//form("./#{$c[0]}","GET");
		echo"<a ID=\"{$c[0]}\"></a>";
	if(mysql1r("select `bool_available_now`  from productnamesurlencoded where ID='{$c[0]}' and lang='$lang'",$link)){	
		if ($i%2==0)divclass('row');
		else divclass('rowimparell');
	}
	else divclass('roweliminat');
		divclass('blockllarg');
		//br();
		//echo'Descripció';
		if($link->query("select * from package_pvp where `ID`='{$c[0]}' ")->num_rows>0)$pvpsobre=" +preus";
		else $pvpsobre="";
		echo ainf('./fitxa.php?ID='.$c[0],"[{$c[0]}] ".strtolower(urldecode(mysql1r("select value from productnamesurlencoded where ID='{$c[0]}' and lang='$lang'",$link))).$pvpsobre,'_new');
		tdiv();divclass('block');
		hidden('ID',$c[0]);
		hidden('f',$_SESSION['f']);
		echo'Proveïdor actual';
		br();
		select2llistes_js('id_dealer',$ldealer[0],$ldealer[1],$c[1],'yes');
		//tod();
		tdiv();divclass('block');
		//echo'granel?'; aixo ho posem a +dades
		//br();
		//chek_js('bool_in_bulk',$c[2]);
		//tdiv();divclass('block');
		echo'Cost: ';
		br();
		text_js('cost_price',3,$c[3],15,'no','no',true);
		tdiv();divclass('block');
		echo'PVP: ';
		br();
		text_js('pvp',3,$c[4],15,'no','no',true);
		tdiv();divclass('block');
		echo'PVPi: ';
		br();
		text_js('pvpi',3,$c[5],15,'no','no',true);
		tdiv();divclass('block');
		echo'1000: ';
		br();
		text_js('pv1',3,$c[6],15,'no','no',true);
		tdiv();divclass('block');
		echo'1000i: ';
		br();
		text_js('pv1i',3,$c[7],15,'no','no',true);
		tdiv();divclass('block');
		echo'2000: ';
		br();
		text_js('pv2',3,$c[8],15,'no','no',true);
		tdiv();divclass('block');
		echo'2000i: ';
		br();
		text_js('pv2i',3,$c[9],15,'no','no',true);
		tdiv();divclass('block');
		echo'3000: ';
		br();
		text_js('pv3',3,$c[10],15,'no','no',true);
		tdiv();divclass('block');
		echo'3000i: ';
		br();
		text_js('pv3i',3,$c[11],15,'no','no',true);
        tdiv();divclass('block');
		echo'10: ';
		br();
		text_js('pv4',3,$c[13],15,'no','no',true);
		tdiv();divclass('block');
		echo'10i: ';
		br();
		text_js('pv4i',3,$c[14],15,'no','no',true);
        tdiv();divclass('block');
		 echo 'ultim preu';br();
         echo $c[12];
        tdiv();
		tdiv();
		tform();
        $i++;
	}
	br();
	$paginacio();
    h3c("He mostrat $i productes");
}
require('tanca.php');
?>
