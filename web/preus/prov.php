<?php
require('login.php');
if($login){
    estil_capcal('Edició proveidors',$_SERVER['REQUEST_URI']);
    divclass('sortir');
// 	a("{$_SERVER['SCRIPT_NAME']}?tancar=true","sortir");
//     ainf("./nou.php","nou",'_new');echo" ";
	a("./?tancar=true","sortir");
	tdiv();

    form($_SERVER['SCRIPT_NAME'],'POST');
		$config['table']='dealers';
		if($_REQUEST['do']){
            $config['do']=$_REQUEST['do'];
            $config['id']=$_REQUEST['id'];
        }
        else $config['do']='new';
		$config['lang_app']='ca';
		$config['today']='yes';
		if(is_array($_REQUEST['m'])){
			$config['object']=$_REQUEST['m'];
			//$config['object']['caixa']['import_total']=str_replace(',','.',$config['object']['caixa']['import_total']);
			$config['object']['dealers']['hidden_user']=$_SESSION['user'];
// 			if(($config['object']['dealers']['id_viadepago']==100)){
				//tractar a part
// 			}
			new_update($config,$link);
			unset($config['object']['dealers']);
		}
		tableform($config,$link);
		if($config['do']=="update"){
            hidden('do','update');
            hidden('id',$config['id']);
        }
		sumit1('entra');
		tform();
		br();
		if($config['do']=="update")a("{$_SERVER['SCRIPT_NAME']}","nou registre ");
    my_llista($link,"Llista de proveïdors","Llista_prov");
}
require('tanca.php');
?>