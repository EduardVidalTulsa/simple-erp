<!--/aquest arxiu retorna el preu i la descripció d'un article /-->
<?php
require("login.php");
if($login){
    $ID=$_REQUEST[ID];
    $sql="select {$_REQUEST[get]} from scalelist where ID=$ID ";
    if(is_numeric($ID)){
        $o= mysql1r($sql,$link);
        echo $o!=""?$o:($_REQUEST[get]=='value'?"codi no trobat":"");
    }
    else echo"";
}//require('tanca.php');
?>