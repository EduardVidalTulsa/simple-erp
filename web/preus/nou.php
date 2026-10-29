<?php //nou producte
require('login.php');
if($login){
    $ldealer=mysql2array('select id, `Value` from dealers ORDER by `value`',$link);
    $ldealer[1]=array_map('urldecode',$ldealer[1]);//abans amb la funció urldecoder de la BD
    $lfamilia=mysql2array('select ID, `value` from families ORDER by `ID`',$link);
    $liva=mysql2array('select ID, `Value` from IVA ORDER by `ID`',$link);
//tracto el form
    if(isset($_REQUEST['cost']))$_REQUEST['cost']=str_replace(',','.',$_REQUEST['cost']);
    if(isset($_REQUEST['ID'])&&(!ctype_digit($_REQUEST['ID'])||!is_numeric($_REQUEST['cost'])))
        error("Cal un codi numèric i un preu de cost");
    elseif(isset($_REQUEST['ID']))
       if($_REQUEST['ID']!=mysql1r("select ID from `tarifes` where `ID`={$_REQUEST['ID']} ",$link)){
        $insert="INSERT INTO `tarifes`(`ID`,`data_hora`,`user`,`id_dealer`, `id_familia`,`cost_price`,
        `historic`,ultimpreu,tipusiva,bool_in_bulk,
        `pvp`,`pvpi`,`pv1`,`pv1i`,`pv2`,`pv2i`,`pv3`,`pv3i`,pv4,pv4i
        )";
            $insert.="Values ('{$_REQUEST['ID']}',now(),'{$_SESSION['user']}','{$_REQUEST['id_dealer']}',
            '{$_REQUEST['id_familia']}','{$_REQUEST['cost']}',1,1,{$_REQUEST['id_IVA']},'{$_REQUEST['forma']}','";
           $insert.=round($_REQUEST['cost']*2.14,2);
           $insert.="','2.14','";
           $insert.=$_REQUEST['cost']*1.39;
           $insert.="','1.39','";
           $insert.=$_REQUEST['cost']*1.25;
           $insert.="','1.25','";
           $insert.=$_REQUEST['cost']*1.18;
           $insert.="','1.18','";
           $insert.=$_REQUEST['cost']*1.11;
           $insert.="','1.11')";
//            echo $insert;
           $value=str_replace("'","\'",$_REQUEST['descripcio']);
          mysqli_query($link,$insert)or die(errorr($insert.mysqli_error($link)));
          $insert="INSERT INTO `productnames`(`ID`,`value`)Values ('{$_REQUEST['ID']}','{$value}')";
	  mysqli_query($link,$insert)or die(errorr($insert.mysqli_error($link)));
	  $value=urlencode($_REQUEST['descripcio']);
          $insert="INSERT INTO `productnamesurlencoded`(`ID`,`value`)Values ('{$_REQUEST['ID']}','{$value}')";
          mysqli_query($link,$insert)or die(errorr($insert.mysqli_error($link)));
          header("Location: ./?cerca={$_REQUEST['ID']}");
        echo "no header?";
    }
    else error("Aquest codi ja existeix tria un altre!");
//fi del tractament
    estil_capcal('Entra nou producte');
    divclass('sortir');
	a("./?tancar=true","sortir");
	tdiv();
    form($_SERVER['REQUEST_URI'],'GET');
    divclass('row');
    divclass('block');
    echo'codi';
    br();
    text('ID',3,'',5,true);
    tdiv();divclass('block');
    echo 'descripció en català';
    br();
    text('descripcio',20,$_REQUEST['descripcio'],25);
    tdiv();divclass('block');
    echo'pesat?';
    br();
    $forma0=Array('1','0');
    $forma1=Array('a pes','a unitat');
    //forma=["W" => "pesat","U" => "unitari",];
    select2llistes('forma',$forma0,$forma1,$_REQUEST['forma']);
    tdiv();divclass('block');
    echo'Proveïdor actual';
	br();
	select2llistes('id_dealer',$ldealer[0],$ldealer[1],$_REQUEST['id_dealer']);
    tdiv();divclass('block');
    echo'Família de productes';
	br();
	select2llistes('id_familia',$lfamilia[0],$lfamilia[1],$_REQUEST['id_familia']);
    tdiv();divclass('block');
    echo'Tipus IVA';
	br();
	select2llistes('id_IVA',$liva[0],$liva[1],$_REQUEST['id_IVA']);
    tdiv();divclass('block');
    echo'Preu Cost';
    br();
    //number('cost',0,500,0.01);
    text('cost',4,$_REQUEST['cost']);

    tdiv();divclass('block');
sumit1('Entra');

    tdiv();tdiv();
    tform();
    pc("s'apliquen els marges estàndars 2.14 (pvp), 1.39, 1.25, 1.18, i 1.11, un cop entrat podreu modificar-lo com qualsevol altre producte");

}
require('tanca.php');
?>
