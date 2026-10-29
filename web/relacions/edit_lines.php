<?php
// edit_lines.php - Carrega les línies d'una relació (AJAX)
session_start();
if(!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    die("No autenticat");
}

// Carregar només les funcions necessàries
require_once("{$_SERVER['DOCUMENT_ROOT']}/../keys.php");
require_once("{$_SERVER['DOCUMENT_ROOT']}/scale/phpgeneral/mysqli.php");

$link = new mysqli($myserver, $myuser, $mypassword, $mydatabase);

if(!function_exists('detallrelacio')) {
    require_once('lib.php');
}

$uuid = $_REQUEST['uuid'];
$ID_Empresa = (int)$_REQUEST['ID_Empresa'];
$year = (int)$_REQUEST['year'];

$rel = detallrelacio($uuid, $ID_Empresa, $year, $link);

// Tipus d'IVA disponibles
$ivas = [];
$qiva = $link->query("SELECT * FROM IVA ORDER BY Value");
while($iva = $qiva->fetch_assoc()) {
    $ivas[] = $iva;
}

// Mostrar línies actives (de l'última a la primera)
if($rel['activelines'] > 0) {
    for($i = $rel['activelines'] - 1; $i >= 0; $i--) {
        $line = $rel['active_lines'][$i];
        renderLine($line, $rel, $ivas, $ID_Empresa, $year);
    }
}

// Mostrar línies esborrades
if($rel['deletedlines'] > 0) {
    echo "<div style='margin-top:20px; padding-top:20px; border-top: 2px dashed #ccc;'>";
    echo "<h4>Línies esborrades</h4>";
    for($i = $rel['deletedlines'] - 1; $i >= 0; $i--) {
        $line = $rel['deleted_lines'][$i];
        renderLine($line, $rel, $ivas, $ID_Empresa, $year);
    }
    echo "</div>";
}

if($rel['activelines'] == 0 && $rel['deletedlines'] == 0) {
    echo "<div style='text-align:center; padding:30px; color:#999;'>";
    echo "<p>Encara no hi ha cap línia. Comença a afegir productes amb la línia màgica!</p>";
    echo "</div>";
}

function renderLine($line, $rel, $ivas, $ID_Empresa, $year) {
    $isDeleted = $line['deleted_line'] == 1;
    $isEditable = $rel['editable'] == 1;
    $readonly = !$isEditable ? 'readonly' : '';
    $disabled = !$isEditable ? 'disabled' : '';
    
    $lineClass = $isDeleted ? 'line-form deleted' : 'line-form';
    
    // Mobile: mostrar amb etiquetes, Desktop: sense etiquetes
    ?>
    <form class="<?=$lineClass?>" id="line-form-<?=$line['line']?>">
        <input type="hidden" name="uuid" value="<?=$rel['uuid']?>">
        <input type="hidden" name="ID_Empresa" value="<?=$ID_Empresa?>">
        <input type="hidden" name="year" value="<?=$year?>">
        <input type="hidden" name="line" value="<?=$line['line']?>">
        
        <div class="line-number" style="display:none;">Línia #<?=$line['line']?></div>
        
        <div>
            <label class="mobile-label" style="display:none;">Descripció:</label>
            <input type="text" name="description" value="<?=htmlspecialchars(urldecode($line['description']))?>" <?=$readonly?> placeholder="Descripció" style="width:100%;" onblur="updateLine(<?=$line['line']?>)">
        </div>
        
        <div>
            <label class="mobile-label" style="display:none;">Unitats:</label>
            <input type="text" name="units" value="<?=str_replace('.', ',', number_format($line['units'], 3, '.', ''))?>" <?=$readonly?> placeholder="Unitats" style="width:80px;" onblur="updateLine(<?=$line['line']?>)">
        </div>
        
        <div>
            <label class="mobile-label" style="display:none;">Preu:</label>
            <input type="text" name="cost" value="<?=str_replace('.', ',', number_format($line['cost'], 2, '.', ''))?>" <?=$readonly?> placeholder="Preu" style="width:70px;" onblur="updateLine(<?=$line['line']?>)">
        </div>
        
        <div>
            <label class="mobile-label" style="display:none;">IVA:</label>
            <select name="IVA" <?=$disabled?> style="width:70px;" onchange="updateLine(<?=$line['line']?>)">
                <?php foreach($ivas as $iva): ?>
                    <option value="<?=$iva['ID']?>" <?=abs($line['IVA'] - $iva['Value']) < 0.01 ? 'selected' : ''?>>
                        <?=$iva['Value']?>%
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label class="mobile-label" style="display:none;">Desc %:</label>
            <?php if($isEditable): ?>
                <select name="discount" style="width:60px;" onchange="updateLine(<?=$line['line']?>)">
                    <option value="0" <?=$line['discount'] == 0 ? 'selected' : ''?>>0%</option>
                    <option value="-5" <?=$line['discount'] == -5 ? 'selected' : ''?>>-5%</option>
                    <option value="-10" <?=$line['discount'] == -10 ? 'selected' : ''?>>-10%</option>
                </select>
            <?php else: ?>
                <input type="text" value="<?=$line['discount']?>%" readonly style="width:60px;">
            <?php endif; ?>
        </div>
        
        <div>
            <label class="mobile-label" style="display:none;">Lot:</label>
            <input type="text" name="lotnumber" value="<?=htmlspecialchars(urldecode($line['lotnumber']))?>" <?=$readonly?> placeholder="Lot" style="width:100px;" onblur="updateLine(<?=$line['line']?>)">
        </div>
        
        <div>
            <?php if($isEditable): ?>
                <button type="button" class="btn-update" onclick="updateLine(<?=$line['line']?>)">💾</button>
                <?php if($isDeleted): ?>
                    <button type="button" class="btn-restore" onclick="toggleDeleteLine(<?=$line['line']?>, 1)">↩️</button>
                <?php else: ?>
                    <button type="button" class="btn-delete" onclick="toggleDeleteLine(<?=$line['line']?>, 0)">🗑️</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </form>
    
    <style>
        @media (max-width: 767px) {
            .mobile-label {
                display: block !important;
            }
            .line-number {
                display: block !important;
            }
        }
    </style>
    <?php
}
?>
