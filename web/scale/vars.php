<?php
// de keys.php si hi són (es fa servir també des de dins de funcions: per això $GLOBALS)
$webname=$GLOBALS['web_nom'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
$ownername=$GLOBALS['empresa_nom'] ?? '';
$mytwitter=$GLOBALS['mytwitter'] ?? '';
$myfacebook=$GLOBALS['myfacebook'] ?? '';
$mytelegram=$GLOBALS['mytelegram'] ?? '';
$myinstagram=$GLOBALS['myinstagram'] ?? '';
$avaiable_languages=array('eo' ,'en','es','ca');
$any=date('Y');
$decimalsing=",";
$decimalstoshowmoney=2;
$decimalstoshowunits=3;
$thousandsing=" ";
$ID_tipus_relacio=1;//tipus de relació amb que comenćo els tiquets 0 -->No assignat 1 -> relació per assignar
$ID_tipus_relacio_legacy=9;//tipus de relació legacy scale
$ivageneric=10;
$Venda_detall="Venda al detall";
?>
