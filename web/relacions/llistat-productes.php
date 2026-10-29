<?php
/**
 * Llistat de productes (scalelist) amb codi, nom, preu i unitats venudes per botiga.
 * Versió: HTML (Ctrl+P → Guardar com PDF al navegador).
 */

// Autenticació (com tots els scripts de relacions/)
require __DIR__ . '/../scale/login.php';
if(!$login) { require __DIR__ . '/../scale/tanca.php'; $link->close(); die; }

// Empreses a incloure (les que venen físicament): $empreses de keys.php
global $empreses;
if (empty($empreses)) $empreses = [1 => 'Botiga 1'];

$any = date('Y');

// Consultar tots els productes de scalelist, ordenats alfabèticament
$productes = [];
$q = "SELECT s.id, s.value, s.pvp FROM scalelist s ORDER BY s.value ASC";
$r = $link->query($q);
while ($row = $r->fetch_assoc()) {
    $productes[$row['id']] = [
        'id' => $row['id'],
        'nom' => urldecode($row['value'] ?: ''),
        'pvp' => $row['pvp'] ?? 0,
        'vendes' => [],
    ];
}

if (empty($productes)) {
    die("<p>No hi ha productes a scalelist.</p>");
}

// Per cada empresa, sumar unitats venudes
foreach ($empreses as $eid => $enom) {
    $taula = "{$any}_Empresa_{$eid}_line";
    $chk = $link->query("SHOW TABLES LIKE '$taula'");
    if ($chk->num_rows == 0) continue;

    $q = "SELECT ID_product, ROUND(SUM(units), 3) AS total FROM $taula 
          WHERE deleted_line = 0 AND units > 0 
          GROUP BY ID_product";
    $r2 = $link->query($q);
    while ($row = $r2->fetch_assoc()) {
        $pid = $row['ID_product'];
        if (isset($productes[$pid])) {
            $productes[$pid]['vendes'][$eid] = (float)$row['total'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ca">
<head>
<meta charset="UTF-8">
<title>Llistat de Productes</title>
<style>
@media print {
    @page { size: landscape; margin: 10mm; }
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 9px; color: #222; padding: 15px; }
h1 { font-size: 16px; text-align: center; margin-bottom: 3px; }
.sub { font-size: 11px; text-align: center; color: #666; margin-bottom: 10px; }
table { width: 100%; border-collapse: collapse; }
thead th { 
    background: #2c3e50; color: #fff; padding: 5px 4px; text-align: left; 
    font-weight: 700; font-size: 8px; white-space: nowrap; position: sticky; top: 0;
}
tbody td { padding: 3px 4px; border-bottom: 1px solid #ddd; vertical-align: top; }
tbody tr:nth-child(even) { background: #f9f9f9; }
tbody tr:hover { background: #e8f4fd; }
.codi { width: 35px; text-align: center; font-family: monospace; font-weight: 600; }
.nom { max-width: 180px; overflow: hidden; text-overflow: ellipsis; }
.preu { width: 50px; text-align: right; }
.vendes { width: 55px; text-align: right; font-family: monospace; }
.sin-preu { color: #c0392b; font-weight: bold; }
.footer { text-align: center; margin-top: 12px; font-size: 8px; color: #999; }
.no-print { text-align: center; margin: 10px 0; }
.no-print button { padding: 8px 20px; font-size: 14px; cursor: pointer; background: #2c3e50; color: #fff; border: none; border-radius: 4px; }
.no-print button:hover { background: #34495e; }
</style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()">🖨️ Imprimir / Desar com PDF</button>
</div>

<h1>Llistat de Productes</h1>
<div class="sub">Ordenat alfabèticament · <?= date('d/m/Y H:i') ?> · Total: <?= count($productes) ?> productes</div>

<table>
<thead>
<tr>
    <th class="codi">Codi</th>
    <th class="nom">Producte</th>
    <th class="preu">Preu</th>
    <?php foreach ($empreses as $enom): ?>
    <th class="vendes"><?= $enom ?></th>
    <?php endforeach; ?>
</tr>
</thead>
<tbody>
<?php 
$pvp_err = 0;
foreach ($productes as $p): 
    $sense_preu = ($p['pvp'] == 0 || $p['pvp'] === null || $p['pvp'] === '');
    if ($sense_preu) $pvp_err++;
    $preu_txt = $sense_preu ? '<span class="sin-preu">*</span>' : number_format($p['pvp'], 2, ',', '.');
    $nom = $p['nom'] ?: '(sense nom)';
?>
<tr>
    <td class="codi"><?= $p['id'] ?></td>
    <td class="nom"><?= htmlspecialchars($nom) ?></td>
    <td class="preu"><?= $preu_txt ?></td>
    <?php foreach ($empreses as $eid => $enom): 
        $v = isset($p['vendes'][$eid]) ? number_format($p['vendes'][$eid], 3, ',', '.') : '-';
    ?>
    <td class="vendes"><?= $v ?></td>
    <?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="footer">
    Total: <?= count($productes) ?> productes 
    <?php if ($pvp_err > 0): ?>
    · <span style="color:#c0392b"><?= $pvp_err ?> productes sense preu (*)</span>
    <?php endif; ?>
</div>

</body>
</html>
