<?php
$app="altaclient";
require('../scale/login.php');
require('lib.php');
$r=&$_REQUEST;$s=&$_SESSION;
//pre($s['user']);
if (($_SERVER["REQUEST_METHOD"] == "GET") && (!empty($r['value']))&&$_REQUEST['do']=="altaclient") {
	// Recollir les dades del formulari
    $value = strip_tags($_REQUEST['value']);
    $fiscal_address = strip_tags($_REQUEST['fiscal_address']);
    $poblacio = strip_tags($_REQUEST['poblacio']);
    $nif = strip_tags($_REQUEST['nif']);
    $bool_equivalency_charge = isset($_REQUEST['bool_equivalency_charge']) ? 1 : 0;
    $bool_active = isset($_REQUEST['bool_active']) ? 1 : 0;
    if($login)$id_tarifa = isset($_REQUEST['id_tarifa']) ? intval($_REQUEST['id_tarifa']) : 0;
    else $id_tarifa =10;
    $bool_tax_detall = isset($_REQUEST['bool_tax_detall']) ? 1 : 0;
    $comercial_name = strip_tags($_REQUEST['comercial_name']);
    $deliver_address = strip_tags($_REQUEST['deliver_address']);
    $email = strip_tags($_REQUEST['email']);
    $telefons = array();
    for ($i = 1; $i <= 10; $i++) {
       	if(isset($_GET['telefon' . $i])) {
           	$telefon = $_GET['telefon' . $i];
           	// Si hi ha un signe '+' no en la primera posició, l'eliminem
           	if (strpos($telefon, '+') !== false && strpos($telefon, '+') !== 0) {
            	$telefon = str_replace('+', '', $telefon);
           	}
            $telefons[] = trim(preg_replace('/[^0-9\+]/', '', $telefon));
        }
	}
	if(isset($telefons))pre($telefons);
	// Comprova si els camps requerits no estan buits
	if (empty($value) || empty($fiscal_address) || empty($poblacio) || empty($nif) || empty($email)) {
    		$error_message = "Tots els camps requerits han d'estar plens.";
	} 
	else {
    		// Comprova si el valor del client ja existeix
    		$sql_check_value = "SELECT * FROM Clients WHERE Value = '".urlencode($value)."'";
    		$result_check_value = mysqli_query($link, $sql_check_value);
		
    		if (mysqli_num_rows($result_check_value) > 0) {
        		$error_message = "El nom del client ja existeix.";
    		} 
		else {
        		// Comprova si el valor del telèfon del client ja existeix
        		$phone_exists = false;
       		 	foreach ($telefons as $telefon) {
       		 	    	$sql_check_phone = "SELECT * FROM Clients_phone WHERE Value = '$telefon'";
       		 	    	//pre($sql);
      		  	    	$result_check_phone = mysqli_query($link, $sql_check_phone);
				
        		    	if (mysqli_num_rows($result_check_phone) > 0) {
        		    		$phone_exists = true;
        		        	break;
        		    	}
        		}
			
		        if ($phone_exists) {
		            $error_message = "El valor del telèfon del client ja existeix.";
			} 
			else {
		        	// Totes les comprovacions han passat, així que procedeix a inserir les dades
		            	// Aquí va el codi per inserir les dades a la base de dades
		               	// Escapar les dades per evitar SQL injection
		    		$uvalue = urlencode($value);
		    		$ufiscal_address =urlencode($fiscal_address);
		    		$upoblacio =urlencode( $poblacio);
		    		$unif =urlencode( $nif);
		    		if(isset($comercial_name))$ucomercial_name = urlencode($comercial_name);
		    		if(isset($deliver_address))$udeliver_address = urlencode($deliver_address);
		    		$uemail =urlencode($email);
		
		    		// Iniciar transacció
		    		mysqli_autocommit($link, false);
				// Afegim el client a la base de dades    // Inserir les dades del nou client a la taula "Clients"
		     		$sql = "INSERT INTO Clients (Value, fiscal_address, Poblacio, NIF, bool_equivalency_charge, bool_Active, id_tarifa, bool_tax_detall, comercial_name, deliver_address, email)
		            	VALUES ('$uvalue', '$ufiscal_address', '$upoblacio', '$unif', $bool_equivalency_charge, $bool_active, $id_tarifa, $bool_tax_detall, '$ucomercial_name', '$udeliver_address', '$uemail')";
                    $result = mysqli_query($link, $sql);
                    $client_id = mysqli_insert_id($link);
				//$client_id=7462;
		    		debugtelegram($sql);
                    foreach ($telefons as $telefon) {
                        $sql = "INSERT INTO Clients_phone (ID_Client, Value) VALUES ('$client_id', '$telefon')";
		    			if($telefon!=""){
                            debugtelegram($sql);
                            mysqli_query($link, $sql);
                        }
                    }
	        	}
		}
	}
	// Si hi ha un missatge d'error, mostra'l i torna a mostrar el formulari amb les dades anteriors
	if (isset($error_message)) {
		mysqli_rollback($link);
	    echo "<p style='color: red;'>$error_message</p>";
	}
	if (!isset($error_message)) {
		mysqli_commit($link);
		//header("https://");
		$a="Client donat d'alta correctament amb aquestes dades:\n codi client: $client_id \n Nom fiscal $value \ndireccio fiscal  $fiscal_address \nPoblacio $poblacio \nnif $nif\nemail $email\nnomcomercial $comercial_name\ndeliver_address $deliver_address\n IVA desglosat? $bool_tax_detall\n recarreg? $bool_equivalency_charge\n telefons:";
        pre( $a);
        pre($telefons);
        foreach ($telefons as $tel){
            $a.=$tel.", ";
        }
        debugtelegram($a,gethostname()); // canal per defecte de keys.php; posa-hi un 3r argument si vols un altre canal
		//pre($_GET);
	    exit;
	}
}//fi del tractament del post
$translations = [
    'ca' => [
        'title' => "Alta Client",
        'name_label' => "Nom Cognoms/ raó social:",
        'name_label_ex' => "Pere Pi / Fruits del Pi SL",
        'fiscal_address_label' => "Adreça Fiscal:",
        'fiscal_address_label_ex' => "Carrer Major, 165, Bx 1a",
        'poblacio_label' => "Codi postal, població:",
	'poblacio_label_ex' => "17006 Girona, Catalunya, Espanya",
	'postal_code_label' => "Codi postal:",
        'postal_code_ex' => "17006",
	'city_label' => "Població:",
        'city_label_ex' => "Girona",
	'country_label' => "País:",
        'country_label_ex' => "Catalunya",
	'nif_label' => "NIF:",

        'charge_label' => "Paga recàrrec d'equivalència?",
        'active_label' => "Accepta protecció dades i condicions d'ús?",
        'tarifa_label' => "Tarifa:",
        'tax_detall_label' => "Vol l'IVA desglosat?",
        'comercial_name_label' => "Nom comercial:",
        'deliver_address_label' => "Adreça d'entrega:",
        'email_label' => "Correu electrònic:",
        'submit_btn' => "Enviar",
	'telefon' => "Telèfon (Si no es espanyol afegiu +codipais)", 
	'afegir_telefon'=>"Afegir un telefon",
    "requerits"=>"Els camps marcats amb * son obligatoris",
    ],
    'es' => [
        'title' => "Alta Cliente",
        'name_label' => "Nombre Apellidos/ razón social:",
        'name_label_ex' => "Fulano de Tal / Fulano y Sotano SL",
        'fiscal_address_label' => "Dirección Fiscal:",
        'fiscal_address_label_ex' => "Calle mayor 165, Bx 1a",
        'poblacio_label' => "Código postal, población:",
        'poblacio_label_ex' => "28013 Madrid, Madrid, Spain",
	'postal_code_label' => "Código postal:",
        'postal_code_ex' => "17006",
	'city_label' => "Población:",
        'city_label_ex' => "Girona",
	'country_label' => "País:",
        'country_label_ex' => "Catalunya",
        'nif_label' => "NIF:",
        'charge_label' => "¿Paga recargo de equivalencia?",
        'active_label' => "¿Acepta protección de datos y condiciones de uso?",
        'tarifa_label' => "Tarifa:",
        'tax_detall_label' => "¿Quiere el IVA desglosado?",
        'comercial_name_label' => "Nombre comercial:",
        'deliver_address_label' => "Dirección de entrega:",
        'email_label' => "Correo electrónico:",
        'submit_btn' => "Enviar",
                'telefon' => "Teléfono (Si no es español, añadir +códigopais)",
                'afegir_telefon'=>"Añadir un teléfono",
                "requerits" => "Los campos marcados con * son obligatorios",

    ],
    'fr' => [
        'title' => "Inscription Client",
        'name_label' => "Nom Prénoms/ raison sociale:",
        'name_label_ex' => "Pierre Dupont / Fruits du Pont SARL",
        'fiscal_address_label' => "Adresse fiscale:",
        'fiscal_address_label_ex' => "5 Rue du castellet",
        'poblacio_label' => "Code postal, ville:",
	'poblacio_label_ex' => "66000 Perpinyà, Catalunya, France ",
	'postal_code_label' => "Code postal:",
        'postal_code_ex' => "66000",
	'city_label' => "Ville:",
        'city_label_ex' => "Perpinyà",
	'country_label' => "País:",
        'country_label_ex' => "Catalunya",
	
        'nif_label' => "NIF:",
        'charge_label' => "Paye la taxe d'équivalence? [espagne]",
        'active_label' => "Accepte la protection des données et les conditions d'usage?",
        'tarifa_label' => "Tarif:",
        'tax_detall_label' => "Voulez-vous la TVA détaillée?",
        'comercial_name_label' => "Nom commercial:",
        'deliver_address_label' => "Adresse de livraison:",
        'email_label' => "Courrier électronique:",
        'submit_btn' => "Envoyer",
                'telefon' => "Téléphone (Si ce n'est pas espagnol, ajouter +codepays)",
                'afegir_telefon'=>"Ajouter un téléphone",
                "requerits" => "Les champs marqués d'un * sont obligatoires",

    ],
    'en' => [
        'title' => "New Client Registration",
        'name_label' => "Full Name/ Corporate Name:",
        'name_label_ex' => "John Doe / Doe nuts LLC",
        'fiscal_address_label' => "Fiscal Address:",
        'fiscal_address_label_ex' => "1 Main Street ",
        'poblacio_label' => "Postal code, city:",
	'poblacio_label_ex' => "74103 Tulsa, Oklahoma ",
	'postal_code_label' => "Postal Code:",
        'postal_code_ex' => "74103",
	'city_label' => "City",
        'city_label_ex' => "Tulsa",
	'country_label' => "Country:",
        'country_label_ex' => "Oklahoma, USA",
	
        'nif_label' => "NIF:",
        'charge_label' => "Subject to equivalence surcharge? [spain]",
        'active_label' => "Accepts data protection and user conditions?",
        'tarifa_label' => "Rate:",
        'tax_detall_label' => "Wants detailed VAT?",
        'comercial_name_label' => "Commercial Name:",
        'deliver_address_label' => "Delivery Address:",
        'email_label' => "email:",
        'submit_btn' => "submit",
                'telefon' => "Phone (If it's not Spanish, add +countrycode)",
                'afegir_telefon'=>"Add phone",
                "requerits" => "Fields marked with * are mandatory",

    ],
];
function getUserLanguage() {
    $languages = ['ca', 'es', 'fr', 'en'];
    $user_language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);

    if (in_array($user_language, $languages)) {
        return $user_language;
    } else {
        return 'ca'; // Idioma per defecte
    }
}
$language = getUserLanguage();
$strings = $translations[$language];
//debugtelegram($r);
?>
<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $strings['title']; ?></title>
</head>
<body>
    <h1><?php echo $strings['title']; if ($login)echo " [{$s['user']}]" ?></h1>
    <form action="./" method="get">
    <?php hidden("do","altaclient");?>
    <?php if ($login){
$tarifa=mysql2array("Select ID, Value from tarifa where ID!='4'",$link);

  ?>
        <label for="id_tarifa"><?php echo $strings['tarifa_label']; ?></label>

<?php select2llistes("id_tarifa",$tarifa[0],$tarifa[1],10,"no",false,"style=\"vertical-align:unset; font-size: {$fontsize}px;  text-align: left; width:{$meswidht}px; height:{$height}px;\""); if (!$login)br();}
?>
        <label for="value"><?php echo $strings['name_label']; ?>*</label>
	<input type="text" name="value" id="value" value="<?php echo htmlspecialchars(strip_tags(strval($r['value']))); ?>" placeholder="<?php echo $strings['name_label_ex'];?> " required><?php if(!$login)echo '<br>';?>

        <label for="fiscal_address"><?php echo $strings['fiscal_address_label']; ?>*</label>
        <input type="text" name="fiscal_address" id="fiscal_address" value="<?php echo htmlspecialchars(strip_tags(strval($fiscal_address))); ?>" placeholder="<?php echo $strings['fiscal_address_label_ex'];?> " required><?php if(!$login)echo '<br>';?>

        <label for="poblacio"><?php echo $strings['poblacio_label']; ?>*</label>
        <input type="text" name="poblacio" id="poblacio" value="<?php echo htmlspecialchars(strip_tags(strval($poblacio))); ?>" placeholder="<?php echo $strings['poblacio_label_ex'];?> "  required > <?php if(!$login)echo '<br>';?>

        <label for="nif"><?php echo $strings['nif_label']; ?>*</label>
        <input type="text" name="nif" id="nif" value="<?php echo htmlspecialchars(strip_tags(strval($nif))); ?>" required> <?php if($login)echo '<br>';?>
         <label for="comercial_name"><?php echo $strings['comercial_name_label']; ?></label>
        <input type="text" name="comercial_name" id="comercial_name"> <?php if(!$login)echo '<br>';?>

        <label for="deliver_address"><?php echo $strings['deliver_address_label']; ?></label>
        <input type="text" name="deliver_address" id="deliver_address"> <?php if(!$login)echo '<br>';?>

        <label for="email"><?php echo $strings['email_label']; ?>*</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars(strip_tags(strval($email))); ?>" id="email" required> <?php if(!$login)echo '<br>';?>
           <div id="telefons">
    <?php if (empty($telefons)): ?>
    <div>
        <label><?php echo $strings['telefon'];?> * 1:</label>
        <input type="text" name="telefon1" value=""><br><span class="error" id="error_telefon1"></span>
    </div>
    <?php else: ?>
        <?php foreach ($telefons as $index => $telefon): ?>
        <div>
            <label><?php echo $strings['telefon'];?> <?php echo $index + 1;?>:</label>
            <input type="text" name="telefon<?php echo $index + 1;?>" value="<?php echo htmlspecialchars(strip_tags(strval($telefon))); ?>"><br>
            <span class="error" id="error_telefon<?php echo $index + 1;?>"></span>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Botó per afegir un nou número de telèfon -->

<button type="button" onclick="afegirTelefon()"><?php echo $strings['afegir_telefon'];?></button><br/>
<label for="bool_tax_detall"><?php echo $strings['tax_detall_label']; ?></label>
        <input type="checkbox" name="bool_tax_detall" id="bool_tax_detall"> <?php if(!$login)echo '<br>';?>
        <label for="bool_equivalency_charge"><?php echo $strings['charge_label']; ?></label>
        <input type="checkbox" name="bool_equivalency_charge" id="bool_equivalency_charge"> <?php if(!$login)echo '<br>';?>
        <label for="bool_active"><?php echo $strings['active_label']; ?>*</label>
        <input type="checkbox" name="bool_active" id="bool_active" <?php if ($login){ ?>checked<?php }?> > <?php if(!$login)echo '<br>';?>







<script>
var telefonsInicials = <?php if(!empty($telefons))echo json_encode($telefons);else echo '[ 1 ]'; ?>;
var numTelefons = telefonsInicials.length;
function actualitzarNomsInputs() {
    var inputs = document.querySelectorAll('#telefons input');
    for (var i = 0; i < inputs.length; i++) {
        inputs[i].name = 'telefon' + (i + 1);
    }
	    numTelefons = inputs.length;
	comprovarTelefonsRepetits();

}

function afegirTelefon() {
	if (numTelefons >= 3) {
        return;
    }
    numTelefons++;

    // Crea una nova div amb un label i una caixa de text per al número de telèfon
    var div = document.createElement("div");    
        div.innerHTML = '<label><?php echo $strings['telefon'];?> ' + numTelefons + ':</label>' +
        '<input type="text" name="telefon' + numTelefons + '" value="">' +
        '<button type="button" onclick="eliminarTelefon(this)">X</button><br>' +
        '<span class="error" id="error_telefon' + numTelefons + '"></span>';

    // Afegeix la nova div a la div "telefons"
    document.getElementById("telefons").appendChild(div);
    // Afegeix un esdeveniment 'change' al nou input creat
    var nouInput = div.querySelector('input');
    nouInput.addEventListener('change', comprovarTelefonsRepetits);

    // Actualitza els noms dels inputs existents
    actualitzarNomsInputs();

}

function eliminarTelefon(button) {
    // Esborra la div que conté el botó "X"
	button.parentNode.parentNode.removeChild(button.parentNode);
	 numTelefons--;
    actualitzarNomsInputs();


    // Comprova els números de telèfon repetits
	comprovarTelefonsRepetits();

}
// Comprova inicialment els números de telèfon repetits
comprovarTelefonsRepetits();

// Comprova els números de telèfon repetits cada vegada que un input canvia
document.querySelectorAll('#telefons input').forEach(function(input) {
    input.addEventListener('change', comprovarTelefonsRepetits);
});
</script>


        <input type="submit" value="<?php echo $strings['submit_btn']; ?>"><br/>
        <?php echo $strings['requerits'];?>
    </form>
<?php

if (!$login)die;
h2("Edició de clients:");
//if($_SERVER['HTTP_HOST']=="beta"){if(isset($r))pre($r);}

h3("Filtre");echo "comodí [%]";br();
if($r['do']=='filtre'){
    // Definir filtres només si el valor no està buit
    $filtresPermesos = ['fvalue' => 'Value', 'ffiscal_address' => 'fiscal_address', 'fpoblacio' => 'poblacio', 'fnif' => 'NIF', 'fid_tarifa' => 'id_tarifa','fphone'=>'phone','fdeliver_address'=>'deliver_address','fcomercial_name'=>'comercial_name','femail'=>'email'];
    foreach ($filtresPermesos as $clauRebuda => $clauFiltre) {
        if (!empty($r[$clauRebuda])) {
            $s['filtre'][$clauFiltre] = str_replace(' ', '%', $r[$clauRebuda]);
        } elseif (isset($s['filtre'][$clauFiltre])) {
            unset($s['filtre'][$clauFiltre]);
        }
    }

    // Per els valors boolean, no cal substituir espais
    $filtresBoolean = ['bool_equivalency_charge', 'bool_active', 'bool_tax_detall'];
    foreach ($filtresBoolean as $clauBool) {

            $s['filtre'][$clauBool] = $r["f".$clauBool];
}



}
//if($_SERVER['HTTP_HOST']=="beta"){if(isset($s['filtre']))pre($s['filtre']);}
$where = "WHERE `ID` > '0'";
foreach ($s['filtre'] as $key => $value) {
     // Escapar els apòstrofs i eliminar HTML
    if($value!=""){
        $safe_value = addslashes(strip_tags($value));

        if ($key == 'phone' && !empty($value)) {
            // Subconsulta per telèfons
            $where .= " AND `ID` IN (SELECT `ID_Client` FROM `Clients_phone` WHERE `Value` LIKE '%$safe_value%')";
        } elseif (strpos($key, 'bool_') === 0) {
            // Filtre per camps booleans
            	$booleanValue = $value;
		if($booleanValue==1 OR $booleanValue==0)$where .= " AND `$key` = '$booleanValue'";
        } elseif (!empty($value)) {
            // Filtres generals
            $where .= " AND `$key` LIKE '%$safe_value%'";
        }
    }
}
form();
hidden("do","filtre");
$tarifa=mysql2array("Select ID, Value from tarifa ",$link);

  ?>
        <label for="fid_tarifa"><?php echo $strings['tarifa_label']; ?></label>

<?php select2llistes_js("fid_tarifa",$tarifa[0],$tarifa[1],$s['filtre']['id_tarifa'],"no",false,"style=\"vertical-align:unset; font-size: {$fontsize}px;  text-align: left; width:{$meswidht}px; height:{$height}px;\"");
//br();
?>
<label for="fvalue"><?php echo $strings['name_label']; ?></label>
	<input type="text" name="fvalue" id="value" value="<?php if($s['filtre']['Value'])echo htmlspecialchars(str_replace('%', ' ', $s['filtre']['Value'])); ?>" placeholder="<?php echo $strings['name_label_ex'];?>" onChange="this.form.submit()"> 

        <label for="ffiscal_address"><?php echo $strings['fiscal_address_label']; ?></label>
        <input type="text" name="ffiscal_address" id="fiscal_address" value="<?php if($s['filtre']['fiscal_address']) echo htmlspecialchars(str_replace('%', ' ', $s['filtre']['fiscal_address'])); ?>" placeholder="<?php echo $strings['fiscal_address_label_ex'];?> " onChange="this.form.submit()">
        <label for="fpoblacio"><?php echo $strings['poblacio_label']; ?></label>
        <input type="text" name="fpoblacio" id="poblacio" value="<?php if($s['filtre']['poblacio'])echo htmlspecialchars(str_replace('%', ' ', $s['filtre']['poblacio'])); ?>" placeholder="<?php echo $strings['poblacio_label_ex'];?> " onChange="this.form.submit()" >

        <label for="fnif"><?php echo $strings['nif_label']; ?></label>
        <input type="text" name="fnif" id="nif" value="<?php if($s['filtre']['NIF'])echo htmlspecialchars(str_replace('%', ' ', $s['filtre']['NIF'])); ?>" onChange="this.form.submit()">
        <label for="fcomercial_name"><?php echo $strings['comercial_name_label']; ?></label>
        <input type="text" name="fcomercial_name" id="comercial_name" value="<?php if($s['filtre']['comercial_name'])echo htmlspecialchars(str_replace('%', ' ', $s['filtre']['comercial_name'])); ?>" onChange="this.form.submit()" >

        <label for="deliver_address"><?php echo $strings['deliver_address_label']; ?></label>
        <input type="text" name="fdeliver_address" id="deliver_address" value="<?php if($s['filtre']['deliver_address'])echo htmlspecialchars(str_replace('%', ' ', $s['filtre']['deliver_address'])); ?>" onChange="this.form.submit()">

        <label for="email"><?php echo $strings['email_label']; ?></label>
        <input type="email" name="femail" value="<?php if($s['filtre']['email'])echo htmlspecialchars(str_replace('%', ' ', $s['filtre']['email'])); ?>" id="email"   onChange="this.form.submit()">
        <label><?php echo $strings['telefon'];?></label>
        <input type="text" name="fphone" value="<?php if($s['filtre']['phone'])echo htmlspecialchars(str_replace('%', ' ', $s['filtre']['phone'])); ?>" onChange="this.form.submit()">
        </br>

	<?php   if(!isset($s['filtre']['bool_equivalency_charge']))$s['filtre']['bool_equivalency_charge']=2;
            if(!isset($s['filtre']['bool_tax_detall']))$s['filtre']['bool_tax_detall']=2;
            if(!isset($s['filtre']['bool_active']))$s['filtre']['bool_active']=1;
    $eq=Array(0=>$strings['charge_label']." NO" ,1=>$strings['charge_label']." SÍ",2=>$strings['charge_label']." Indiferent");
    //pre($eq);
    select1llista_js("fbool_equivalency_charge",$eq,$s['filtre']['bool_equivalency_charge'],false);// br();
    $iva=Array(0=>$strings['tax_detall_label']." NO" ,1=>$strings['tax_detall_label']." SÍ",2=>$strings['tax_detall_label']." Indiferent");
    select1llista_js("fbool_tax_detall",$iva,$s['filtre']['bool_tax_detall'],false); //br();
    $actiu=Array(0=>" NO Actiu" ,1=>" Actiu ",2=>" Indiferent");
    select1llista_js("fbool_active",$actiu,$s['filtre']['bool_active'],false);// br();
    ?>
<?php tform();
//$numclients=mysql1r("select count(*)Clients ".str_replace("`ID` > '0' AND","",$where),$link);
$numclients=mysql1r("select count(*) from Clients ".$where,$link);
//paginació 
$clientsPerPagina = 20; // Nombre de clients per pàgina
$totalPagines = ceil($numclients / $clientsPerPagina); // Total de pàgines
if(isset($r['pagina']))$s['f']['pagina']=$r['pagina'];

$paginaActual = isset($s['f']['pagina']) ? (int)$s['f']['pagina'] : 1; // Pàgina actual

// Calcula el LIMIT i el OFFSET per la consulta SQL
$offset = ($paginaActual - 1) * $clientsPerPagina;
$limit = $clientsPerPagina;

h3("Llista [$numclients]");
$clients="Select * from Clients $where  LIMIT $limit OFFSET $offset";
if($_SERVER['HTTP_HOST']=="beta"){pre($clients);}
if ($r['do'] == "update_phone") {
    $phoneID = intval($r['pID']);
    $newPhone = trim($r['phone']);
    // Validar el format del número de telèfon
    if (preg_match('/^\+?[0-9 ]+$/', $newPhone)) {
        $newPhone = mysqli_real_escape_string($link, $newPhone);

        $updatePhoneQuery = "UPDATE Clients_phone SET Value = '$newPhone' WHERE ID = $phoneID";
        if (mysqli_query($link, $updatePhoneQuery)) {
            echo "Telèfon actualitzat correctament.";
        } else {
            echo "Error actualitzant el telèfon: " . mysqli_error($link);
        }
    } else {
        echo "Format de telèfon invàlid.";
    }
}

if ($r['do'] == "add_phone") {
    $clientID = intval($r['ID_Client']);
    $newPhone = trim($r['phone']);
    // Validar el format del número de telèfon
    if (preg_match('/^\+?[0-9 ]+$/', $newPhone)) {
        $newPhone = mysqli_real_escape_string($link, $newPhone);

        $addPhoneQuery = "INSERT INTO Clients_phone (ID_Client, Value) VALUES ($clientID, '$newPhone')";
        if (mysqli_query($link, $addPhoneQuery)) {
            echo "Telèfon afegit correctament.";
        } else {
            echo "Error afegint el telèfon: " . mysqli_error($link);
        }
    } else {
        echo "Format de telèfon invàlid.";
    }
}
if($r['do']=='update_client'){
$update="UPDATE `Clients` SET
`Value` = '".urlencod($r['uvalue'])."',
`fiscal_address` = '".urlencod($r['ufiscal_address'])."',
`Poblacio` = '".urlencod($r['upoblacio'])."',
`NIF` = '".urlencod($r['unif'])."',
`bool_equivalency_charge` = '".($r['ubool_equivalency_charge']=='on'?"1":"0")."',
`bool_Active` = '".($r['ubool_active']=='on'?"1":"0")."',
`id_tarifa` = '{$r['uid_tarifa']}',
`bool_tax_detall` = '".($r['ubool_tax_detall']=='on'?"1":"0")."',
`comercial_name` = '".urlencod($r['ucomercial_name'])."',
`deliver_address` = '".urlencod($r['udeliver_address'])."',
`email` = '".urlencod($r['uemail'])."'
WHERE `ID` = '{$r['uID']}';";
try{
 $link->query($update);
}catch (exception $e){
	debugtelegram($update.mysqli_error($link));
	header("Location: {$s[$app]['uri']}");die; }
//if($_SERVER['HTTP_HOST']=="beta"){pre($r);pre($update);}
}

$d=mysqli_query($link,$clients)or die (errorr($clients.mysqli_error($link)));
$colorActual = true; // Comença amb true, canviarà a cada client

while($c=mysqli_fetch_assoc($d)){
	$colorDiv = $colorActual ? "white" : "#ACAB22";
	$colorActual = !$colorActual; // Canvia el color per al següent client

    // Inicia el div amb el color corresponent
    echo "<div style='background-color: {$colorDiv};'>";
    form();
    hidden("uID",$c['ID']);
    hidden("do","update_client");
    ?>
	    <input type="text" name="uvalue" id="value" value="<?php echo htmlspecialchars(urldecod($c['Value'])); ?>" placeholder="<?php echo $strings['name_label_ex'];?> " required onchange="this.form.submit();" style='background-color:<?php echo $colorDiv;?>'>

    <input type="text" name="ufiscal_address" id="fiscal_address" value="<?php echo htmlspecialchars(urldecod($c['fiscal_address'])); ?>" placeholder="<?php echo $strings['fiscal_address_label_ex'];?> " required onchange="this.form.submit()" style='background-color:<?php echo $colorDiv;?>'>
    <input type="text" name="upoblacio" id="poblacio" value="<?php echo htmlspecialchars(urldecod($c['Poblacio'])); ?>" placeholder="<?php echo $strings['poblacio_label_ex'];?> " required onchange="this.form.submit()" style='background-color:<?php echo $colorDiv;?>'>
    <input type="text" name="unif" id="nif" value="<?php echo htmlspecialchars(urldecod($c['NIF'])); ?>" placeholder="<?php echo $strings['nif_label'];?> " required onchange="this.form.submit()" style='background-color:<?php echo $colorDiv;?>'>

    <?php
    select2llistes_js("uid_tarifa", $tarifa[0], $tarifa[1], $c['id_tarifa'], "no", false, "style=\"vertical-align:unset; font-size: {$fontsize}px;  text-align: left; width:{$meswidht}px; height:{$height}px;\"");
    ?>
    <input type="text" name="ucomercial_name" id="comercial_name" value="<?php echo htmlspecialchars(urldecod($c['comercial_name'])); ?>" onchange="this.form.submit()" placeholder="<?php echo $strings['comercial_name_label'];?> " style='background-color:<?php echo $colorDiv;?>'>
    <input type="text" name="udeliver_address" id="deliver_address" value="<?php echo htmlspecialchars(urldecod($c['deliver_address'])); ?>" onchange="this.form.submit()" placeholder="<?php echo $strings['deliver_address_label'];?> " style='background-color:<?php echo $colorDiv;?>'>
    <input type="email" name="uemail" value="<?php echo htmlspecialchars(urldecod($c['email'])); ?>" id="email" placeholder="<?php echo $strings['email_label'];?> " required onchange="this.form.submit()">
    <?php echo "R.E.?";?><input type="checkbox" name="ubool_equivalency_charge" id="bool_equivalency_charge" <?php if($c['bool_equivalency_charge']==1) echo "checked"; ?> onchange="this.form.submit()">
    <?php echo "IVA des.?";?><input type="checkbox" name="ubool_tax_detall" id="bool_tax_detall" <?php if($c['bool_tax_detall']==1) echo "checked"; ?> onchange="this.form.submit()">
    <?php echo "Actiu?";?><input type="checkbox" name="ubool_active" id="bool_active" <?php if($c['bool_Active']==1) echo "checked"; ?> onchange="this.form.submit()">
    <?php
    if ($c['bool_Active']==0)a("./?do=elimina_client&ID={$c['ID']}","X");
    tform();
    //br();
    $i=1;
    $phones=$link->query("SELECT * from Clients_phone where `ID_Client`='{$c['ID']}'");
    div();
    while($p=mysqli_fetch_assoc($phones)){
	    echo "<form style='display: inline-block; margin-right: 10px;' action='' method='post'>";
    	    if($i==1)echo $strings['telefon'];
	    hidden("do","update_phone");
	    hidden("pID",$p['ID']);
	    echo " $i:";
	    text_js("phone",10,$p['Value'],20,FALSE,FALSE,FALSE,"style='background-color:$colorDiv'",$strings['afegir_telefon']);
	    tform();
	    $i++;
    }
    echo "<form style='display: inline-block; margin-right: 10px;' action='' method='post'>";
    echo " $i:";
    hidden("do","add_phone");
    hidden("ID_Client",$c['ID']);
    text_js("phone",10,"",20,FALSE,FALSE,FALSE,"style='background-color:$colorDiv'",$strings['afegir_telefon']);
    tform();
    tdiv();
    tdiv();
}
//imprimeix paginació

for ($i = 1; $i <= $totalPagines; $i++) {
    if ($i == $paginaActual) {
        echo "$i "; // La pàgina actual no és un enllaç
    } else {
        echo "<a href='?pagina=$i'>$i</a> "; // Altres pàgines són enllaços
    }
}

?>
