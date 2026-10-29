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
    estil_capcal("llista de preus detall");
divclass('sortir');
// 	a("{$_SERVER['SCRIPT_NAME']}?tancar=true","sortir");
    ainf("./nou.php","nou",'_new');echo" ";
	a("./?tancar=true","sortir");
	tdiv();

//     pre();print_r($_SESSION);tpre();
	$q="	select  ID, value, pvp from llistapvp"; //si fiques un or amb ultimpreu=0 podras buscar id_dealers antics també :)
	//$ldealer=mysql2array('select id, value from dealers ORDER by `value`',$link); l'he posat al inici de la plana
 //echo $q;
    $i=0;
	$d=mysqli_query($link,$q)or die (errorr($q.mysqli_error($link)));
    tab();
    $i=1;
	while($c=mysqli_fetch_row($d)){
		//pre();	print_r($c); tpre();
    	echo $c[0];
        tod();
        echo $c[1];
        tod();
        echo $c[2];
        if ($i%3==0)tor();
        echo tod();

		$i++;
     }
    ttable();
    $i--;
    h3c("He mostrat $i productes");
}
require('tanca.php');
?>