<?php
/*Bascula personalitzada
*/

// Validació de seguretat: rebutjar patrons invàlids ABANS de carregar res
if(isset($_REQUEST['magic'])){
    $magic_test = $_REQUEST['magic'];
    // Rebutjar tots els codis ** que NO siguin numèrics vàlids
    // Vàlids: **123, **123*5, **123.2, **123.2*5, ***123, ***123.1*3+lot+data
    // Invàlids: **123*, **123#, **abc, qualsevol altre caràcter no permès
    if(preg_match('/^\*\*/', $magic_test)) {
        // És un codi d'etiqueta, validar format estricte
        // Format: **[*]codi[.package][*units][+lot][+data]
        if(!preg_match('/^\*\*\*?\d+(\.\d+)?(\*\d+)?(\+[^+]+)?(\+.*)?$/', $magic_test)){
            // Format invàlid, redirigir a la pàgina principal
            header("Location: " . ($_SERVER['REQUEST_SCHEME'] ?? 'https') . "://" . $_SERVER['HTTP_HOST'] . "/scale/");
            die;
        }
    }
}

require('login.php');
//pre($_SESSION);
if($login){
    	$_REQUEST['m']['estat']!=false?$estat=$_REQUEST['m']['estat']:$estat='pesa';
    	// a tracto (tria venedor) deixem l'últim preu a la bàscula
    	if(!in_array($estat, ['tracto','extra']))
    		mysqli_query($link,"UPDATE `ultimpes` SET `preu` = '0' WHERE `ID` = '$idbascula'");
	if($_REQUEST['m']['estat']=="extra"){
		$estat='tracto';
		$_REQUEST['magic']='+'.$_REQUEST['id'];
 		//echo $_REQUEST['magic'];
 		//die;
	}
	switch ($estat){
	case "pesa":
			// Utilitzem la funció refactoritzada a lib.php
			mostrar_pantalla_pesa($w, $h, $prefixrelacio, $link, $ID_Empresa, $scale_uri, $decimalsing, $thousandsing);
		break;
		case "tracto": // Quan hem rebut algo al magic
			// Utilitzem la funció refactoritzada a lib.php
			case_tracto($_REQUEST['magic'], $link, $scale_uri, $idbascula, $w, $h, $decimalsing, $thousandsing);
	break;
	case "entralinea":
		// Utilitzem la funció refactoritzada a lib.php
		case_entralinea($_REQUEST['venedor'], $link, $scale_uri);
		break;
	case "subtotal":
		// Utilitzem la funció refactoritzada a lib.php
		$ID_venedor = substr($_REQUEST['search'], 1);
		case_subtotal($ID_venedor, $link, $scale_uri, $w, $h, $prefixrelacio, $ID_Empresa, $decimalsing, $thousandsing, $decimalstoshowmoney, $decimalstoshowunits);
		break;
	case "tracto_subtotal":
		// Utilitzem la funció refactoritzada a lib.php
		case_tracto_subtotal($link, $scale_uri, $prefixrelacio);
		break;
	}
}
//fi swich estat
	else {// echo "login false 2";
        require('tanca.php');
    }
//$link->close();
?>
