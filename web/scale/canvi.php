<?php
/************
*
* faig proves
*
************/
require("login.php");
if(!$login){$link->close();die;}
$decimalsing=',';
$thousandsing=' ';
estil_capcal(false);
divclass("caixa");
form("{$scale_uri}canvi.php");
hidden("uuid",$_REQUEST['uuid']);
        if($w<$h)divclass('pes',"style =width:".($w-25)."px; height:50px;");
	else divclass('pes',"style = height:".($h)."px;top:2px;width:".(($w/2)-15)."px;");
$cobra=detallrelacio($_REQUEST['uuid'],$ID_Empresa,date('Y'),$link)['Total'];
echo "Cobra: ".number_format($cobra,2,$decimalsing,$thousandsing)."€";
br();
if($_REQUEST['magic']>$cobra)echo "Torna: ".number_format($_REQUEST['magic']-$cobra,2,$decimalsing)."€";
//detallrelacio($_REQUEST['uuid'],$ID_Empresa,date('Y'),$link)['Total']);
tdiv();
if($w<$h)divclass("caixacodi","style=\"width: ".($w-15)."px;\"");
else divclass("caixacodi","style=\"width: ".(($w/2)+10)."px; left:".($w/2)."px\"");
?>
<div id="codi">
<div style=" display:table-cell; vertical-align:middle; width:<?=$w-15?>px;height:115px; position:relative; top: 8px">
<input type="search" name="magic" size=8 id="magic" placeholder="Entregat" class="form-control" value="<?=$_REQUEST['magic']?>" onfocus="this.select(); " autofocus autocomplete="off" onChange="this.form.submit()"/>
€</div>
<?php
tdiv();	if($w<$h)$scaixa="style =\"background:#ACAB00; position:absolute; top:120px; left:5px; width:580px; height:815px; font-size:88px;padding:1%;\"";
	else $scaixa="style =\"background:#ACAB00; position:absolute; top:150px; left:5px; width:".(($w/2)-30)."px; height:815px; font-size:88px;padding:1%;\"";
divclass('caixadescripcio',$scaixa);
a($scale_uri,"Següent client");
tdiv();
tdiv();
$link->close();
