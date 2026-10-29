<?php
/*
*
*			Funcions per dibuixar xhtml més ràpidament que de costum :)
*			Eduard Vidal i Tulsà festuc@amena.com
*/
function ebr(){echo "<br/>
";}
function eimg($link,$width="",$height="",$alt=""){
	$r= "<img src=\"$link\" alt=\"$alt\" ";
	if(($width!="")||($heigth!=""))$r.="style=\"border: 0px solid ;";
	if($width!="")$r.="width: $width;";
	if($height!="")$r.="height: $height; ";
	$r.="\"/>";
	return $r;
	}
function img($link,$width="",$height="",$alt=""){echo imgr($link,$width,$height,$alt);}
function br(){return "<br/>";}
function etria_dataihora($today,$nom="dataihora",$idioma=ct, $diff_years_past=10, $diff_yers_begin=0){
//version 2.1 8-11-2002 :D
$llistamesos=array(" ","gener","febrer","mar&ccedil;","abril","maig","juny","juliol","agost","setembre","octubre","novembre","desembre");
$listameses=array(" ","Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Setiembre","Octubre","Noviembre","Diciembre");
for($i=1;$i<=13;$i++)$nmesos[$i]=$i;
for($i=1;$i<=32;$i++)$ndies[$i]=$i;
for($i=$today[year]-$diff_years_past;$i<=$today[year]+$diff_years_begin;$i++)$nyear[$i]=$i;
for($i=0;$i<60;$i++)$segons[$i]=$i;
for($i=0;$i<24;$i++)$hora[$i]=$i;
select2llistes($nom."[hours]",$hora,$hora,$today[hours]);
echo ":";
select2llistes($nom."[minutes]",$segons,$segons,$today[minutes]);
echo"/";
select2llistes($nom."[mday]",$ndies,$ndies,$today[mday]);
if($idioma==ct)select2llistes($nom."[mon]",$nmesos,$llistamesos,$today[mon]);
if($idioma==es)select2llistes($nom."[mon]",$nmesos,$listameses,$today[mon]);
select2llistes($nom."[year]",$nyear,$nyear,$today[year]);

}

function etria_data($today,$nom="data",$idioma=ct, $diff_years_past=10, $diff_yers_begin=0){
	$llistamesos=array("","gener","febrer","mar&ccedil;","abril","maig","juny","juliol","agost","setembre","octubre","novembre","desembre");
	$listameses=array("","Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Setiembre","Octubre","Noviembre","Diciembre");
	for($i=1;$i<=12;$i++)$nmesos[$i]=$i;
	for($i=1;$i<=31;$i++)$ndies[$i]=$i;
	for($i=$today[year]-$diff_years_past;$i<=$today[year]+$diff_years_begin;$i++)$nyear[$i]=$i;
	$avui=getdate();
	select2llistes($nom."[mday]",$ndies,$ndies,$today[mday]);
	if($idioma==ct)select2llistes($nom."[mon]",$nmesos,$llistamesos,$today[mon]);
	if($idioma==es)select2llistes($nom."[mon]",$nmesos,$listameses,$today[mon]);
	select2llistes($nom."[year]",$nyear,$nyear,$today[year]);
}	
function esumit($sumit="Ejecutar",$reset="Vaciar Formulario"){
echo' <br/>
<input name="sumit" value="'.$sumit.'" type="submit"/>
<input name="reset" value="'.$reset.'" type="reset"/>
';
}
function esumit1($sumit="Ejecutar"){
echo'
<input name="'.$sumit.'" value="'.$sumit.'" type="submit"/>
';
}//funcions de dibuixar les taules
function table($contingut,$parametres=""){
	$r="<table \"$parametres\">\n";
	$r.="<tbody>\n$contingut</tbody>";
	$r.="\n</table>";
	return $r;
}
function tr($contingut){
	$r="<tr>\n";
	$r.=$contingut;
	$r.="</tr>\n";
	return $r;
}
function td($contingut,$param=""){
	$param?$r="	<td \"$param\">":$r="	<td>";
	$r.="\n	$contingut";
	$r.="\n	</td>\n";
	return $r;
}
?>
