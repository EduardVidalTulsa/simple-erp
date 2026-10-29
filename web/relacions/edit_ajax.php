<?php
// edit_ajax.php - Gestió AJAX de les accions d'edició
error_reporting(0); // Suprimir warnings que trenquen el JSON
header('Content-Type: application/json');

// No carreguem login.php per evitar redirects, només comprovem sessió
session_start();

if(!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    echo json_encode(['success' => false, 'error' => 'No autenticat']);
    die();
}

// Carregar només les funcions necessàries
require_once("{$_SERVER['DOCUMENT_ROOT']}/../keys.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/mysqli.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/magic.php"); // parse_magic_code(), comú amb scale

$link = new mysqli($myserver, $myuser, $mypassword, $mydatabase);

if(!function_exists('detallrelacio')) {
    require_once('lib.php');
}

$r = &$_REQUEST;
$s = &$_SESSION;

$action = $r['action'] ?? '';

// Funció per crear taules si no existeixen
function ensureTablesExist($ID_Empresa, $year, $link) {
    $prefix = "{$year}_Empresa_{$ID_Empresa}_";
    if(!mysql1r("SHOW TABLES LIKE '{$prefix}index_relacio'", $link)) {
        $link->query("CREATE TABLE `{$prefix}index_relacio` LIKE `meta_index_relacio`;");
    }
    if(!mysql1r("SHOW TABLES LIKE '{$prefix}line'", $link)) {
        $link->query("CREATE TABLE `{$prefix}line` LIKE `meta_line`;");
    }
}

switch($action) {
    case 'add_line':
        addLine();
        break;
    
    case 'update_line':
        updateLine();
        break;
    
    case 'toggle_delete':
        toggleDelete();
        break;
    
    case 'search':
        searchProducts();
        break;
    
    case 'make_editable':
        makeEditable();
        break;
    
    case 'update_index':
        updateIndex();
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Acció no vàlida']);
}

function addLine() {
    global $r, $s, $link;
    
    $uuid = $r['uuid'];
    $ID_Empresa = (int)$r['ID_Empresa'];
    $year = (int)$r['year'];
    $lineInput = trim($r['line']);
    
    // Assegurar que les taules existeixen
    ensureTablesExist($ID_Empresa, $year, $link);
    
    // Comprovar permisos
    if(!checkPermissions($ID_Empresa)) {
        echo json_encode(['success' => false, 'error' => 'No tens permisos']);
        return;
    }
    
    $rel = detallrelacio($uuid, $ID_Empresa, $year, $link);
    
    if($rel['editable'] != 1) {
        echo json_encode(['success' => false, 'error' => 'Relació no editable']);
        return;
    }
    
    // Processar la línia (reutilitzem la lògica del case 'newline' d'index.php)
    $result = processNewLine($lineInput, $rel, $link, $year);
    
    if($result['success']) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Error desconegut']);
    }
}

function processNewLine($ID, $rel, $link, $year) {
    // Mateix parser que la bàscula (scale/phpgeneral/magic.php), amb el format de lletres
    $p = parse_magic_code($ID, 'relacions');
    $abono = $p['abono'] ?? '';
    $units = $p['units'] ?? 1;
    $lotnumber = $p['lot'] ?? '';
    $expirationdate = $p['data'] ?? '';
    $ID = $p['original'] ?? '';

    if($p['tipus'] === 'buit' || $p['tipus'] === 'invalid') {
        return ['success' => false, 'error' => "Codi '$ID' no vàlid"];
    }

    // +preu[*unitats][+iva]: sense IVA escrit, l'IVA genèric (10%) com a index.php
    if($p['tipus'] === 'preu_directe') {
        $iva_value = is_numeric($p['iva']) ? $p['iva'] : 10;
        $iva_id = mysql1r("SELECT ID FROM IVA WHERE Value='$iva_value'", $link);
        if($iva_id === null || $iva_id === false || $iva_id === '') {
            return ['success' => false, 'error' => "IVA $iva_value% no existeix"];
        }
        return insertLine($rel, "Preu directe", $abono . $units, $p['preu'], $iva_id, '', $link, 0, '', 0, $year);
    }

    // Obtenir tarifa del client
    $price_column = 'pvp';
    if($rel['ID_client']) {
        $tarifa_id = mysql1r("SELECT `id_tarifa` FROM `Clients` WHERE `ID`='{$rel['ID_client']}'", $link);
        if($tarifa_id !== null && $tarifa_id !== false) {
            $price_column = mysql1r("SELECT `column_name` FROM `tarifa` WHERE `ID`='$tarifa_id'", $link) ?: 'pvp';
        }
    }

    if($p['tipus'] === 'paquet') {
        return processNewPackage($p, $rel, $price_column, $link, $year);
    }

    $codi = $p['ID'];
    if($p['tipus'] === 'ean') {
        $codi = mysql1r("SELECT ID FROM ean WHERE Value='$codi'", $link);
        if(!$codi) return ['success' => false, 'error' => "Codi de barres {$p['ID']} no trobat"];
    }
    // Codis que comencen amb lletra (p.ex. GR061): si el codi existeix tal qual, és un codi; si no, és text
    $es_codi = $p['tipus'] !== 'text';
    if(!$es_codi) {
        $codi = $link->real_escape_string($codi);
        $es_codi = mysql1r("SELECT COUNT(*) FROM productnamesurlencoded WHERE ID='$codi'", $link) > 0;
    }
    if($es_codi) {
        // Buscar producte per ID (el codi és l'ID del producte)
        $product = $link->query("SELECT pn.ID, pn.value as description, t.$price_column as price, t.tipusiva as ID_iva, t.bool_in_bulk as bulk 
            FROM productnamesurlencoded pn 
            INNER JOIN tarifes t ON t.ID = pn.ID AND t.ultimpreu=1
            WHERE pn.ID='$codi' AND pn.lang='ca' LIMIT 1")->fetch_assoc();
        
        if(!$product) {
            return ['success' => false, 'error' => "Producte amb codi $codi no trobat"];
        }
        
        $description = $product['description'];
        $cost = $product['price'];
        $iva_id = isset($product['ID_iva']) ? $product['ID_iva'] : 2; // 2 = 21% per defecte
        $ID_product = $product['ID'];
        $bulk = $product['bulk'] ?? 0;
        
        return insertLine($rel, $description, $abono . $units, $cost, $iva_id, $lotnumber, $link, $ID_product, $expirationdate, $bulk, $year);
    }

    // Alfanumèric: cerca per descripció
    $product = cercaProductes($ID, $price_column, $link, 1)[0] ?? null;
    
    if(!$product) {
        return ['success' => false, 'error' => "No s'ha trobat cap producte amb '$ID'"];
    }
    
    $description = $product['description'];
    $cost = $product['price'];
    $iva_id = isset($product['ID_iva']) ? $product['ID_iva'] : 2; // 2 = 21% per defecte
    $ID_product = $product['ID'];
    $bulk = $product['bulk'] ?? 0;
    
    return insertLine($rel, $description, 1, $cost, $iva_id, '', $link, $ID_product, '', $bulk, $year);
}

// codi.paquet[*N]: si el paquet té preu propi, N paquets a aquell preu;
// si no, N vegades el pes del paquet al preu del producte (com processar_codi_paquet_complet a scale)
function processNewPackage($p, $rel, $price_column, $link, $year) {
    $ID = (int)$p['ID'];
    $tipus = (int)$p['paquet'];
    $n = is_numeric($p['units']) ? $p['units'] : 1;
    $package = $link->query("SELECT * FROM package_format WHERE `ID`=$ID AND `ID_package_type`=$tipus
        AND `hidden_date_time` = (SELECT MAX(`hidden_date_time`) FROM package_format WHERE `ID`=$ID AND `ID_package_type`=$tipus)")->fetch_assoc();
    $product = $link->query("SELECT * FROM tarifes WHERE `ID`=$ID AND `ultimpreu`=1")->fetch_assoc();
    if(!$package || !$product) {
        return ['success' => false, 'error' => "Paquet $ID.$tipus no trobat"];
    }
    $description = $link->query("SELECT description FROM package_description WHERE `ID`=$ID AND `ID_package_type`=$tipus AND `lang`='ca'")->fetch_assoc()['description'] ?? '';
    if($description == '') {
        $description = mysql1r("SELECT value FROM productnamesurlencoded WHERE ID=$ID AND lang='ca'", $link);
        $pes = $package['weight'] > 1000 ? str_replace('.', ',', $package['weight'] / 1000) . 'Kg' : $package['weight'] . 'g';
        $description .= urlencode(" $pes");
    }
    $preu_paquet = package_pvp($product, $package, $link);
    if($preu_paquet) {
        $units = $n;
        $cost = $preu_paquet;
        $bulk = 0;
    } else {
        $units = ($package['weight'] / 1000) * $n;
        $cost = $product[$price_column];
        $bulk = $product['bool_in_bulk'];
    }
    return insertLine($rel, $description, $p['abono'] . $units, $cost, $product['tipusiva'], $p['lot'] ?? '', $link, $ID, $p['data'] ?? '', $bulk, $year);
}

// Cerca productes per text. Les descripcions estan urlencoded (espais com a +, accents com a %C3%..),
// així que filtrem en PHP sobre el text descodificat, sense accents ni majúscules, i hi han de ser totes les paraules
function cercaProductes($text, $price_column, $link, $limit = 20) {
    $normalitza = fn($t) => transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $t);
    $paraules = preg_split('/\s+/', $normalitza(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
    if(!$paraules) return [];
    
    $result = $link->query("SELECT pn.ID, pn.ID as codi, pn.value as description, t.$price_column as price, t.tipusiva as ID_iva, t.bool_in_bulk as bulk 
        FROM productnamesurlencoded pn 
        INNER JOIN tarifes t ON t.ID = pn.ID AND t.ultimpreu=1
        WHERE pn.lang='ca'
        GROUP BY pn.ID
        ORDER BY pn.ID ASC");
    
    $products = [];
    while($row = $result->fetch_assoc()) {
        $on = $normalitza($row['ID'] . ' ' . urldecode($row['description']));
        foreach($paraules as $paraula) {
            if(strpos($on, $paraula) === false) continue 2;
        }
        $products[] = $row;
        if(count($products) >= $limit) break;
    }
    return $products;
}

function insertLine($rel, $description, $units, $cost, $iva_id, $lotnumber, $link, $ID_product = 0, $expirationdate = '', $bulk = 0, $year = null) {
    $ID_Empresa = $rel['Empresa']['ID'];
    if($year === null) {
        $year = date('Y'); // Fallback per compatibilitat
    }
    
    // Obtenir següent número de línia
    $nextLine = mysql1r("SELECT IFNULL(MAX(line), 0) + 1 FROM {$year}_Empresa_{$ID_Empresa}_line WHERE ID='{$rel['ID']}' AND ID_tipus_relacio='{$rel['ID_tipus_relacio']}'", $link);
    
    // NO fer urlencode - la descripció ja ve urlencoded de productnamesurlencoded
    // $description ja està com ha de ser
    // $lotnumber es deixa tal qual (si cal urlencode es farà quan s'entra manualment)
    
    // Si hi ha data de caducitat, afegir-la a la descripció
    if(!empty($expirationdate)) {
        // Afegir la data tal com ve, sense formatatge
        $description .= urlencode(" Consumiu preferent abans del : $expirationdate");
    }
    
    // Si iva_id és numèric, obtenim el valor, sinó assumim que ja és el valor
    if(is_numeric($iva_id)) {
        $iva_value = mysql1r("SELECT Value FROM IVA WHERE ID='$iva_id'", $link);
        if($iva_value === null || $iva_value === false) $iva_value = 21; // Si no troba, per defecte 21%
    } else {
        $iva_value = $iva_id ?: 21; // Per defecte 21%
    }
    
    if($rel['tax_detall'] == 0) {
        $cost = $cost / (1 + ($iva_value / 100));
    }
    
    $eq = 0;
    if($rel['equivalency_charge'] == 1) {
        $eq = mysql1r("SELECT TAX FROM equivalency_charge WHERE ID_iva='$iva_id'", $link) ?: 0;
    }
    
    $q = "INSERT INTO {$year}_Empresa_{$ID_Empresa}_line 
        (ID, ID_tipus_relacio, line, ID_product, description, units, cost, IVA, EQUIVALENCY_CHARGE, lotnumber, deleted_line, bulk, discount, hidden_now) 
        VALUES 
        ('{$rel['ID']}', '{$rel['ID_tipus_relacio']}', '$nextLine', '$ID_product', '$description', '$units', '$cost', '$iva_value', '$eq', '$lotnumber', 0, '$bulk', 0, NOW())";
    
    if($link->query($q)) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => $link->error];
    }
}

function updateLine() {
    global $r, $link;
    
    $uuid = $r['uuid'];
    $ID_Empresa = (int)$r['ID_Empresa'];
    $year = (int)$r['year'];
    $line = (int)$r['line'];
    
    // Assegurar que les taules existeixen
    ensureTablesExist($ID_Empresa, $year, $link);
    
    if(!checkPermissions($ID_Empresa)) {
        echo json_encode(['success' => false, 'error' => 'No tens permisos']);
        return;
    }
    
    $rel = detallrelacio($uuid, $ID_Empresa, $year, $link);
    
    if($rel['editable'] != 1) {
        echo json_encode(['success' => false, 'error' => 'Relació no editable']);
        return;
    }
    
    $description = urlencode(trim($r['description']));
    $units = str_replace(',', '.', trim($r['units']));
    $cost = str_replace(',', '.', trim($r['cost']));
    $discount = str_replace(',', '.', trim($r['discount'])) ?: 0;
    $lotnumber = urlencode(trim($r['lotnumber']));
    $iva_id = (int)$r['IVA'];
    
    $iva_value = mysql1r("SELECT Value FROM IVA WHERE ID='$iva_id'", $link);
    
    if($rel['tax_detall'] == 0) {
        $cost = $cost / (1 + ($iva_value / 100));
    }
    
    $eq = 0;
    if($rel['equivalency_charge'] == 1) {
        $eq = mysql1r("SELECT TAX FROM equivalency_charge WHERE ID_iva='$iva_id'", $link) ?: 0;
    }
    
    $q = "UPDATE {$year}_Empresa_{$ID_Empresa}_line SET 
        description='$description', 
        units='$units', 
        cost='$cost', 
        IVA='$iva_value', 
        discount='$discount', 
        EQUIVALENCY_CHARGE='$eq',
        lotnumber='$lotnumber',
        hidden_now=NOW()
        WHERE ID='{$rel['ID']}' AND ID_tipus_relacio='{$rel['ID_tipus_relacio']}' AND line='$line'";
    
    if($link->query($q)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $link->error]);
    }
}

function toggleDelete() {
    global $r, $link;
    
    $uuid = $r['uuid'];
    $ID_Empresa = (int)$r['ID_Empresa'];
    $year = (int)$r['year'];
    $line = (int)$r['line'];
    $deleted = (int)$r['deleted'];
    
    // Assegurar que les taules existeixen
    ensureTablesExist($ID_Empresa, $year, $link);
    
    if(!checkPermissions($ID_Empresa)) {
        echo json_encode(['success' => false, 'error' => 'No tens permisos']);
        return;
    }
    
    $rel = detallrelacio($uuid, $ID_Empresa, $year, $link);
    
    if($rel['editable'] != 1) {
        echo json_encode(['success' => false, 'error' => 'Relació no editable']);
        return;
    }
    
    $q = "UPDATE {$year}_Empresa_{$ID_Empresa}_line SET deleted_line='$deleted' 
        WHERE ID='{$rel['ID']}' AND ID_tipus_relacio='{$rel['ID_tipus_relacio']}' AND line='$line'";
    
    if($link->query($q)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $link->error]);
    }
}

function searchProducts() {
    global $r, $link;
    
    $query = trim($r['query']);
    $ID_Empresa = (int)$r['ID_Empresa'];
    
    // Obtenir la relació actual per saber quin preu usar
    $uuid = $_REQUEST['uuid'] ?? '';
    $year = $_REQUEST['year'] ?? date('Y');
    
    // Obtenir tarifa del client de la relació actual
    $price_column = 'pvp'; // Per defecte
    if($uuid) {
        $rel = detallrelacio($uuid, $ID_Empresa, $year, $link);
        if($rel && $rel['ID_client']) {
            $tarifa_id = mysql1r("SELECT `id_tarifa` FROM `Clients` WHERE `ID`='{$rel['ID_client']}'", $link);
            if($tarifa_id !== null && $tarifa_id !== false) {
                $price_column = mysql1r("SELECT `column_name` FROM `tarifa` WHERE `ID`='$tarifa_id'", $link) ?: 'pvp';
            }
        }
    }
    
    // Buscar productes únics (agrupats per ID) amb l'últim preu
    $products = [];
    
    foreach(cercaProductes($query, $price_column, $link, 20) as $row) {
        $products[] = [
            'codi' => $row['codi'],
            'description' => urldecode($row['description']), // Decodificar el nom
            'price' => number_format($row['price'], 2, '.', '')
        ];
    }
    
    echo json_encode(['success' => true, 'results' => $products]);
}

function makeEditable() {
    global $r, $link;
    
    $uuid = $r['uuid'];
    $ID_Empresa = (int)$r['ID_Empresa'];
    $year = (int)$r['year'];
    
    // Assegurar que les taules existeixen
    ensureTablesExist($ID_Empresa, $year, $link);
    
    if(!checkPermissions($ID_Empresa)) {
        echo json_encode(['success' => false, 'error' => 'No tens permisos']);
        return;
    }
    
    // Comprovar que no sigui tiquet/factura ni estigui en rel2rel
    $rel = detallrelacio($uuid, $ID_Empresa, $year, $link);
    $is_in_rel2rel = mysql1r("SELECT COUNT(*) FROM {$year}_Empresa_{$ID_Empresa}_rel2rel WHERE uuid_fill='$uuid'", $link);
    
    if($rel['ID_tipus_relacio'] == 2 || $rel['ID_tipus_relacio'] == 3 || $is_in_rel2rel > 0) {
        echo json_encode(['success' => false, 'error' => 'No es pot fer editable (és tiquet/factura o està en rel2rel)']);
        return;
    }
    
    $q = "UPDATE {$year}_Empresa_{$ID_Empresa}_index_relacio SET editable=1 WHERE uuid='$uuid'";
    
    if($link->query($q)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $link->error]);
    }
}

function updateIndex() {
    global $r, $link;
    
    $uuid = $r['uuid'];
    $ID_Empresa = (int)$r['ID_Empresa'];
    $year = (int)$r['year'];
    
    // Assegurar que les taules existeixen
    ensureTablesExist($ID_Empresa, $year, $link);
    
    if(!checkPermissions($ID_Empresa)) {
        echo json_encode(['success' => false, 'error' => 'No tens permisos']);
        return;
    }
    
    $rel = detallrelacio($uuid, $ID_Empresa, $year, $link);
    
    if($rel['editable'] != 1) {
        echo json_encode(['success' => false, 'error' => 'Relació no editable']);
        return;
    }
    
    $ID_client = (int)$r['ID_client'];
    $discount = str_replace(',', '.', trim($r['discount'])) ?: 0;
    $tax_detall = (int)($r['tax_detall'] ?? 0);
    $equivalency_charge = (int)($r['equivalency_charge'] ?? 0);
    $ID_Pagament = isset($r['ID_Pagament']) ? (int)$r['ID_Pagament'] : 0;
    
    // Camps de text amb urlencode
    $fiscal_name = urlencode(trim($r['fiscal_name']));
    $comercial_name = urlencode(trim($r['comercial_name']));
    $NIF = trim($r['NIF']);
    $fiscal_address = urlencode(trim($r['fiscal_address']));
    $deliver_address = urlencode(trim($r['deliver_address']));
    $Poblacio = urlencode(trim($r['Poblacio']));
    $phone = trim($r['phone']);
    $email = trim($r['email']);
    
    $q = "UPDATE {$year}_Empresa_{$ID_Empresa}_index_relacio SET 
        ID_client='$ID_client',
        discount='$discount',
        tax_detall='$tax_detall',
        equivalency_charge='$equivalency_charge',
        ID_Pagament='$ID_Pagament',
        fiscal_name='$fiscal_name',
        comercial_name='$comercial_name',
        NIF='$NIF',
        fiscal_address='$fiscal_address',
        deliver_address='$deliver_address',
        Poblacio='$Poblacio',
        phone='$phone',
        email='$email'
        WHERE uuid='$uuid'";
    
    if($link->query($q)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $link->error]);
    }
}

function checkPermissions($ID_Empresa) {
    global $s, $link;
    
    // Per ara simplement retornem true si està logat
    // TODO: implementar control real de permisos
    return true;
    
    /*
    $user_empresa = mysql1r("SELECT ID_Empresa FROM users WHERE username='{$s['user']}'", $link);
    $can_see_all = mysql1r("SELECT bool_see_all_empreses FROM users WHERE username='{$s['user']}'", $link);
    
    return ($user_empresa == $ID_Empresa || $can_see_all == 1);
    */
}
?>
