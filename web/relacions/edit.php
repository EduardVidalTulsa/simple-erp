<?php
// edit.php - Edició AJAX de relacions
$fontsize = 28;
$ampleformulari = 385;
$any = date('Y');

require('../scale/login.php');

if(!$login) {
    require('../scale/tanca.php');
    die();
}

$r = &$_REQUEST;
$s = &$_SESSION;
$uri = $_SERVER['SERVER_PORT'] == 80 ? 'http://' : 'https://';
$uri .= $_SERVER['HTTP_HOST'];

// Validar paràmetres
if(!isset($r['uuid']) || !isset($r['ID_Empresa']) || !isset($r['year'])) {
    die("Paràmetres incorrectes");
}

$uuid = $r['uuid'];
$ID_Empresa = (int)$r['ID_Empresa'];
$year = (int)$r['year'];

// Comprovar i crear taules si no existeixen
$prefix = "{$year}_Empresa_{$ID_Empresa}_";
if(!mysql1r("SHOW TABLES LIKE '{$prefix}index_relacio'", $link)) {
    $link->query("CREATE TABLE `{$prefix}index_relacio` LIKE `meta_index_relacio`;");
}
if(!mysql1r("SHOW TABLES LIKE '{$prefix}line'", $link)) {
    $link->query("CREATE TABLE `{$prefix}line` LIKE `meta_line`;");
}

// Control de permisos: comprovar si l'usuari pot editar aquesta empresa
// Per ara simplement comprovem que estigui logat
// TODO: implementar control de permisos d'empresa
/*
$user_empresa = mysql1r("SELECT ID_Empresa FROM users WHERE username='{$s['user']}'", $link);
$can_see_all = mysql1r("SELECT bool_see_all_empreses FROM users WHERE username='{$s['user']}'", $link);

if($user_empresa != $ID_Empresa && $can_see_all != 1) {
    die("No tens permisos per editar aquesta empresa");
}
*/

// Si la relació n'inclou d'altres (rel2rel) no s'edita aquí: redirigim a ?do=rel2rel d'index.php, igual que fa la llista
if(mysql1r("SHOW TABLES LIKE '{$prefix}rel2rel'", $link)) {
    $rel2rel = $link->query("SELECT ir.ID_client, ir.ID_tipus_relacio, r2r.ID_tipus_relacio_inclosa 
        FROM `{$prefix}rel2rel` r2r 
        INNER JOIN `{$prefix}index_relacio` ir ON ir.ID = r2r.ID AND ir.ID_tipus_relacio = r2r.ID_tipus_relacio 
        WHERE ir.uuid='" . $link->real_escape_string($uuid) . "' LIMIT 1")->fetch_assoc();
    if($rel2rel) {
        header("Location: ./?do=rel2rel&ID_client={$rel2rel['ID_client']}&ID_tipus_relacio_new={$rel2rel['ID_tipus_relacio']}&uuid=" . urlencode($uuid) . "&ID_Empresa={$ID_Empresa}&year={$year}&ID_tipus_relacio={$rel2rel['ID_tipus_relacio_inclosa']}");
        exit();
    }
}

// Carregar la relació
if(!function_exists('detallrelacio')) {
    require_once('lib.php');
}

// Comprovar si la relació existeix
$rel_exists = mysql1r("SELECT COUNT(*) FROM {$year}_Empresa_{$ID_Empresa}_index_relacio WHERE uuid='$uuid'", $link);

if(!$rel_exists) {
    // La relació no existeix, redirigir al flux de creació normal
    echo "<h2>Relació no trobada</h2>";
    echo "<p>El UUID <strong>$uuid</strong> no existeix.</p>";
    echo "<p><a href='./?do=new&ID_Empresa=$ID_Empresa&year=$year'>Crear nova relació</a></p>";
    echo "<p><a href='./'>Tornar a la llista</a></p>";
    die();
}

$rel = detallrelacio($uuid, $ID_Empresa, $year, $link);

if(!$rel) {
    die("Error carregant la relació");
}
if ($_REQUEST['ID_Empresa']==100){
    pre($rel);
    debugtelegram($rel);

}
// Carregar mètodes de pagament i si aquest tipus de relació permet pagament
$pagaments = [];
$allow_payment = $rel['tipus_relacio']['bool_allow_payment'] ?? 0;
if($allow_payment) {
    $q_pag = $link->query("SELECT * FROM Pagament WHERE ID_tipus_relacio='{$rel['ID_tipus_relacio']}' OR ID='0' ORDER BY ID");
    while($pag = $q_pag->fetch_assoc()) {
        $pagaments[] = $pag;
    }
}


// Comprovar si pot editar (si no és tiquet/factura o no està en rel2rel)
// TODO: arreglar nom camp rel2rel
$is_in_rel2rel = 0; // mysql1r("SELECT COUNT(*) FROM {$year}_Empresa_{$ID_Empresa}_rel2rel WHERE uuid_fill='$uuid'", $link);
$can_make_editable = ($rel['ID_tipus_relacio'] != 2 && $rel['ID_tipus_relacio'] != 3 && $is_in_rel2rel == 0);

?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Editar Relació <?=$uuid?></title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" type="text/css" href="<?=$uri?>/scale/estil.css">
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 10px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .magic-input {
            width: 100%;
            font-size: <?=$fontsize?>px;
            padding: 12px;
            margin-bottom: 10px;
            border: 2px solid #889ccf;
            border-radius: 5px;
            box-sizing: border-box;
        }
        #search-results {
            position: absolute;
            z-index: 1000;
            width: calc(100% - 20px);
            max-width: 600px;
            background: white;
            border: 2px solid #889ccf;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .search-item:hover {
            background: #e0e0e0;
        }
        .line-form {
            background: #f0f0f0;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            display: grid;
            gap: 5px;
            align-items: center;
        }
        /* Desktop */
        @media (min-width: 768px) {
            .line-form {
                grid-template-columns: minmax(200px, 3fr) 80px 70px 70px 60px 100px 80px;
                max-width: 1200px;
            }
            .line-form input, .line-form select {
                padding: 5px;
                font-size: 14px;
            }
        }
        /* Mobile */
        @media (max-width: 767px) {
            body {
                padding: 5px;
            }
            .magic-input {
                font-size: 18px;
                padding: 10px;
            }
            .line-form {
                grid-template-columns: 1fr;
                padding: 8px;
            }
            .line-form input, .line-form select {
                padding: 8px;
                font-size: 16px;
                width: 100%;
            }
            .line-form label {
                display: block;
                font-weight: bold;
                margin-bottom: 3px;
                font-size: 14px;
            }
            .line-number {
                font-size: 18px;
                font-weight: bold;
                color: #889ccf;
            }
        }
        .line-form.deleted {
            background: #ffcccc;
            opacity: 0.7;
        }
        .index-form {
            background: #889ccf;
            color: white;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
        }
        .index-form label {
            display: flex;
            flex-direction: column;
            margin: 5px 0;
            font-weight: bold;
        }
        .index-form input, .index-form select {
            margin-top: 3px;
            padding: 8px;
            font-size: 16px;
            border-radius: 3px;
            border: 1px solid #ccc;
        }
        .index-form button {
            padding: 10px 20px;
            font-size: 16px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        .readonly-notice {
            background: #ffeb3b;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
        }
        .make-editable-btn {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        .lines-container {
            margin: 20px 0;
        }
        .btn-delete, .btn-restore, .btn-update {
            padding: 8px 12px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
            margin: 2px;
        }
        .btn-delete {
            background: #f44336;
            color: white;
        }
        .btn-restore {
            background: #4CAF50;
            color: white;
        }
        .btn-update {
            background: #2196F3;
            color: white;
        }
        .sortir a {
            padding: 10px;
            margin: 5px;
            display: inline-block;
        }
        @media (max-width: 767px) {
            .sortir {
                font-size: 14px;
            }
            .sortir a {
                padding: 8px;
                margin: 3px;
            }
        }
    </style>
</head>
<body>
    <a href="./"><h1>Relacions</h1></a>
    
    <div class="sortir">
        <a href="./?do=new">Nova</a>
        <a href="./">Llista</a>
        <a href="../scale/">Bascula</a>
        <a href="./">Tornar</a>
    </div>

    <?php if($rel['editable'] == 0): ?>
        <div class="readonly-notice">
            ⚠️ Aquesta relació NO és editable
            <?php if($can_make_editable): ?>
                <button class="make-editable-btn" onclick="makeEditable()">Fer editable</button>
            <?php else: ?>
                <br>(És un tiquet/factura o està en una relació parent)
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Línia màgica -->
    <div style="margin: 20px 0;">
        <input type="search" 
               class="magic-input" 
               id="magic-line" 
               placeholder="Codi*unitats+lot+caducitat | Cerca text | +preu*unitats+lot+caducitat+iva"
               autocomplete="off"
               <?=$rel['editable'] == 0 ? 'disabled' : ''?>
               autofocus>
        <div id="search-results" style="display:none; background:white; border:1px solid #ccc; max-height:200px; overflow-y:auto;"></div>
    </div>

    <!-- Contenidor de línies (carregat via AJAX) -->
    <div class="lines-container" id="lines-container">
        <?php include('edit_lines.php'); ?>
    </div>

    <!-- Formulari index_relacio -->
    <div class="index-form">
        <h3>Dades de la relació</h3>
        <form id="form-index">
            <input type="hidden" name="uuid" value="<?=$uuid?>">
            <input type="hidden" name="ID_Empresa" value="<?=$ID_Empresa?>">
            <input type="hidden" name="year" value="<?=$year?>">
            
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px;">
                <label>Client (ID): 
                    <input type="text" name="ID_client" value="<?=$rel['ID_client']?>" readonly style="width:80px; background:#eee;">
                </label>
                
                <label>Nom fiscal: 
                    <input type="text" name="fiscal_name" value="<?=htmlspecialchars(urldecode(urldecode($rel['fiscal_name'])))?>" <?=$rel['editable'] == 0 ? 'readonly style="background:#eee;"' : 'onchange="autoUpdateIndex()"'?>>
                </label>
                
                <label>Nom comercial: 
                    <input type="text" name="comercial_name" value="<?=htmlspecialchars(urldecode(urldecode($rel['comercial_name'])))?>" <?=$rel['editable'] == 0 ? 'readonly style="background:#eee;"' : 'onchange="autoUpdateIndex()"'?>>
                </label>
                
                <label>NIF: 
                    <input type="text" name="NIF" value="<?=htmlspecialchars($rel['NIF'])?>" <?=$rel['editable'] == 0 ? 'readonly style="background:#eee;"' : 'onchange="autoUpdateIndex()"'?>>
                </label>
                
                <label>Adreça fiscal: 
                    <input type="text" name="fiscal_address" value="<?=htmlspecialchars(urldecode(urldecode($rel['fiscal_address'])))?>" <?=$rel['editable'] == 0 ? 'readonly style="background:#eee;"' : 'onchange="autoUpdateIndex()"'?>>
                </label>
                
                <label>Adreça lliurament: 
                    <input type="text" name="deliver_address" value="<?=htmlspecialchars(urldecode(urldecode($rel['deliver_address'])))?>" <?=$rel['editable'] == 0 ? 'readonly style="background:#eee;"' : 'onchange="autoUpdateIndex()"'?>>
                </label>
                
                <label>Població: 
                    <input type="text" name="Poblacio" value="<?=htmlspecialchars(urldecode(urldecode($rel['Poblacio'])))?>" <?=$rel['editable'] == 0 ? 'readonly style="background:#eee;"' : 'onchange="autoUpdateIndex()"'?>>
                </label>
                
                <label>Telèfon: 
                    <input type="text" name="phone" value="<?=htmlspecialchars($rel['phone'])?>" <?=$rel['editable'] == 0 ? 'readonly style="background:#eee;"' : 'onchange="autoUpdateIndex()"'?>>
                </label>
                
                <label>Email: 
                    <input type="email" name="email" value="<?=htmlspecialchars(urldecode($rel['email']))?>" <?=$rel['editable'] == 0 ? 'readonly style="background:#eee;"' : 'onchange="autoUpdateIndex()"'?>>
                </label>
                
                <label>Descompte %: 
                    <?php if($rel['editable'] == 1): ?>
                        <select name="discount" onchange="autoUpdateIndex()">
                            <option value="0" <?=$rel['discount'] == 0 ? 'selected' : ''?>>0%</option>
                            <option value="5" <?=$rel['discount'] == 5 ? 'selected' : ''?>>5%</option>
                            <option value="10" <?=$rel['discount'] == 10 ? 'selected' : ''?>>10%</option>
                            <option value="25" <?=$rel['discount'] == 25 ? 'selected' : ''?>>25%</option>
                        </select>
                    <?php else: ?>
                        <input type="text" value="<?=$rel['discount']?>%" readonly style="width:60px; background:#eee;">
                    <?php endif; ?>
                </label>
                
                <label>Mostrar preus:
                    <?php if($rel['editable'] == 1): ?>
                        <select name="tax_detall" onchange="changeTaxDetall()">
                            <option value="0" <?=$rel['tax_detall'] == 0 ? 'selected' : ''?>>Amb IVA inclòs</option>
                            <option value="1" <?=$rel['tax_detall'] == 1 ? 'selected' : ''?>>Sense IVA (detallat)</option>
                        </select>
                    <?php else: ?>
                        <input type="text" value="<?=$rel['tax_detall'] == 0 ? 'Amb IVA inclòs' : 'Sense IVA (detallat)'?>" readonly style="width:150px; background:#eee;">
                    <?php endif; ?>
                </label>
                
                <label>Recàrrec equivalència: 
                    <input type="checkbox" name="equivalency_charge" value="1" <?=$rel['equivalency_charge'] == 1 ? 'checked' : ''?> <?=$rel['editable'] == 0 ? 'disabled' : 'onchange="autoUpdateIndex()"'?>>
                </label>
                
                <?php if($allow_payment && count($pagaments) > 0): ?>
                <label>Via de pagament:
                    <?php if($rel['editable'] == 1): ?>
                        <select name="ID_Pagament" onchange="autoUpdateIndex()">
                            <?php foreach($pagaments as $pag): ?>
                                <option value="<?=$pag['ID']?>" <?=$rel['ID_Pagament'] == $pag['ID'] ? 'selected' : ''?>>
                                    <?=htmlspecialchars($pag['Value'])?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" value="<?=htmlspecialchars($rel['ID_Pagament'])?>" readonly style="width:150px; background:#eee;">
                    <?php endif; ?>
                </label>
                <?php endif; ?>
            </div>
        </form>
        
        <div style="margin-top: 15px;">
            <strong>Total:</strong> <?=number_format($rel['Total'], 2)?>€ | 
            <strong>Base:</strong> <?=number_format($rel['Base'], 2)?>€
        </div>
    </div>

    <!-- Previsualització de la relació -->
    <div style="margin-top: 30px; padding: 20px; background: white; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="color: #333; margin-top: 0;">Previsualització</h3>
        <div id="preview-container" style="border: 1px solid #ddd; padding: 15px; background: white; max-width: 100%; overflow: auto; color: #000;">
            <p style="text-align: center; color: #999;">Carregant previsualització...</p>
        </div>
    </div>

    <script>
    const uuid = '<?=$uuid?>';
    const ID_Empresa = <?=$ID_Empresa?>;
    const year = <?=$year?>;
    const isEditable = <?=$rel['editable']?>;
    let searchTimeout;

    // Processar línia màgica
    $('#magic-line').on('keydown', function(e) {
        if(e.key === 'Enter') {
            e.preventDefault();
            const value = $(this).val().trim();
            if(value) {
                processLine(value);
            }
        } else {
            // Cerca en temps real per text alfanumèric
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const value = $(this).val().trim();
                if(value && !value.match(/^[\d\+\-]/)) {
                    searchProducts(value);
                } else {
                    $('#search-results').hide();
                }
            }, 300);
        }
    });

    // Processar línia (codi, preu directe, etc)
    function processLine(value) {
        $.ajax({
            url: 'edit_ajax.php',
            method: 'POST',
            data: {
                action: 'add_line',
                uuid: uuid,
                ID_Empresa: ID_Empresa,
                year: year,
                line: value
            },
            success: function(response) {
                if(response.success) {
                    $('#magic-line').val('');
                    reloadLines();
                } else {
                    alert('Error: ' + response.error);
                }
            },
            error: function() {
                alert('Error de connexió');
            }
        });
    }

    // Cercar productes
    function searchProducts(query) {
        $.ajax({
            url: 'edit_ajax.php',
            method: 'POST',
            data: {
                action: 'search',
                query: query,
                ID_Empresa: ID_Empresa,
                uuid: uuid,
                year: year
            },
            success: function(response) {
                if(response.success && response.results.length > 0) {
                    let html = '';
                    response.results.forEach(item => {
                        html += `<div class="search-item" onclick="selectProduct('${item.codi}')" style="padding:5px; cursor:pointer; border-bottom:1px solid #eee;">
                            <strong>${item.codi}</strong> - ${item.description} (${item.price}€)
                        </div>`;
                    });
                    $('#search-results').html(html).show();
                } else {
                    $('#search-results').hide();
                }
            }
        });
    }

    // Seleccionar producte de la cerca
    function selectProduct(codi) {
        $('#magic-line').val(codi);
        $('#search-results').hide();
        processLine(codi);
    }

    // Recarregar línies
    function reloadLines() {
        $('#lines-container').load('edit_lines.php?uuid=' + uuid + '&ID_Empresa=' + ID_Empresa + '&year=' + year);
        // Actualitzar previsualització automàticament després de recarregar línies
        setTimeout(reloadPreview, 500);
    }

    // Recarregar previsualització
    function reloadPreview() {
        $('#preview-container').html('<p style="text-align: center; color: #999;">Carregant...</p>');
        $('#preview-container').load('edit_preview.php?uuid=' + uuid + '&ID_Empresa=' + ID_Empresa + '&year=' + year);
    }

    // Carregar previsualització inicial
    $(document).ready(function() {
        reloadPreview();
    });

    // Actualitzar línia
    function updateLine(line) {
        console.log('updateLine() cridat per línia:', line); // DEBUG
        const form = $('#line-form-' + line);
        let data = form.serializeArray();
        
        // Convertir comes a punts per camps numèrics
        data = data.map(item => {
            if(['units', 'cost', 'discount'].includes(item.name)) {
                item.value = item.value.replace(',', '.');
            }
            return item;
        });
        
        console.log('Enviant dades línia:', data); // DEBUG
        
        $.ajax({
            url: 'edit_ajax.php',
            method: 'POST',
            data: $.param(data) + '&action=update_line',
            dataType: 'json',
            success: function(response) {
                console.log('Resposta update línia:', response); // DEBUG
                if(response.success) {
                    reloadLines();
                } else {
                    alert('Error: ' + response.error);
                }
            },
            error: function() {
                alert('Error de connexió');
            }
        });
    }

    // Esborrar/restaurar línia
    function toggleDeleteLine(line, currentState) {
        $.ajax({
            url: 'edit_ajax.php',
            method: 'POST',
            data: {
                action: 'toggle_delete',
                uuid: uuid,
                ID_Empresa: ID_Empresa,
                year: year,
                line: line,
                deleted: currentState == 1 ? 0 : 1
            },
            success: function(response) {
                if(response.success) {
                    reloadLines();
                }
            }
        });
    }

    // Fer relació editable
    function makeEditable() {
        if(confirm('Segur que vols fer aquesta relació editable?')) {
            $.ajax({
                url: 'edit_ajax.php',
                method: 'POST',
                data: {
                    action: 'make_editable',
                    uuid: uuid,
                    ID_Empresa: ID_Empresa,
                    year: year
                },
                success: function(response) {
                    if(response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.error);
                    }
                }
            });
        }
    }

    // Actualitzar index automàticament
    function autoUpdateIndex() {
        console.log('autoUpdateIndex() cridat!'); // DEBUG
        const form = $('#form-index');
        // Assegurar que els checkboxes s'envien sempre (0 o 1)
        const formData = form.serializeArray();
        const data = {};
        formData.forEach(item => data[item.name] = item.value);
        
        // Afegir checkboxes no marcats com a 0
        if(!$('input[name="equivalency_charge"]').is(':checked')) {
            data['equivalency_charge'] = 0;
        }
        
        data['action'] = 'update_index';
        console.log('Enviant dades:', data); // DEBUG
        
        $.ajax({
            url: 'edit_ajax.php',
            method: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                console.log('Resposta:', response); // DEBUG
                if(response.success) {
                    // Recarregar només previsualització, no tota la pàgina
                    setTimeout(reloadPreview, 300);
                } else {
                    alert('Error: ' + response.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error actualitzant:', xhr.responseText);
            }
        });
    }

    // Prevenir submit del formulari (ja no cal botó)
    $('#form-index').on('submit', function(e) {
        e.preventDefault();
        return false;
    });

    // Canviar tax_detall (recarrega pàgina per mostrar preus correctes)
    function changeTaxDetall() {
        const form = $('#form-index');
        $.ajax({
            url: 'edit_ajax.php',
            method: 'POST',
            data: form.serialize() + '&action=update_index&uuid=' + uuid + '&ID_Empresa=' + ID_Empresa + '&year=' + year,
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.error);
                }
            }
        });
    }
    </script>
</body>
</html>
