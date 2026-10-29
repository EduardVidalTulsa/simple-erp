<?php
$webname=$_SERVER['HTTP_HOST'] ?? 'localhost';
$ownername=$GLOBALS['empresa_nom'] ?? '';
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
