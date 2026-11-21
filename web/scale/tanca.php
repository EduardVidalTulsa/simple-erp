<?php
if($login==false){
//    echo "formulari";
	//formulari d'entrada
estil_capcal("{$_SERVER['HTTP_HOST']}@login");// form($_SERVER['DOCUMENT_URI'],'POST');
form($_SERVER['PHP_SELF'],'POST');
divclass("row");
divclass("row");
// echo 'user';br();echo'<input name="user" value="usuari" onfocus="this.select();" autofocus />';
    echo 'user';br();echo'<input name="user" value="" size=5 autofocus />';
//text('user');
tdiv();divclass("row");
echo 'contrasenya';br();
pass('contra',5);
if(!empty($login_error)){br();echo "<b>".htmlspecialchars($login_error)."</b>";br();}
if(function_exists('cal_clau_temporal') && cal_clau_temporal()){
	br();echo 'Contrasenya temporal';br();
	text('temporalkey',5);
}
tdiv();divclass("row");
sumit1('Entrar!');
// address("Aqui has de posar usuari i contrasenya",left);
tdiv();
tdiv();
tform();
}
// else {
// divclass('sortir');
// 	a("{$_SERVER['SCRIPT_NAME']}?tancar=true","sortir");
// 	a("./?tancar=true","sortir");
// 	tdiv();
// }

//estil_capcal($webname,'https://'.$webname);
//$_SESSION['lang']=prefered_language($avaiable_languages,_SERVER['HTTP_ACCEPT_LANGUAGE']);
//echo '$_SERVER[\'REQUEST_URI\']'.$_SERVER['HTTP_ACCEPT_LANGUAGE'].$_SERVER['REQUEST_URI'].'//'.$_SESSION['lang'][1];
//phpinfo();

estil_tancament($link,__FILE__);

?>
