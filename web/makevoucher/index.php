<?php
/**
 * Crea un xec regal/descompte: primer en mostra la vista prèvia a la web (import, caducitat,
 * QR, codi) i només s'imprimeix a la impressora de tiquets quan es prem "Imprimeix".
 * Només hi poden entrar els usuaris amb `users`.pot_fer_vals=1.
 * Si l'usuari té `pot_veure_les_altres_empreses=1` (com root) pot triar per a quina botiga
 * és el xec; els altres només en poden fer per a la seva pròpia botiga. scale/val.php
 * comprova que el xec només es pugui gastar a la botiga per la qual es va crear.
 */
$app = "makevoucher";
require('../scale/login.php');
if(!$login) { require('../scale/tanca.php'); $link->close(); die; }

require '../vendor/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
require_once('../scale/phpgeneral/receipt.php');

// makevoucher només es pot fer servir des de la seu central (Empresa.seu_central=1): des de
// qualsevol altra seu redirigeix a la URL de la central, perquè el xec sempre es creï
// (i es desi) a la BD de la central i no es perdi en cap sincronització.
$central = $link->query("SELECT url_seu FROM `Empresa` WHERE seu_central=1 LIMIT 1")->fetch_assoc();
if($central && $central['url_seu'] != '') {
	$host_central = parse_url($central['url_seu'], PHP_URL_HOST);
	if(strcasecmp($_SERVER['HTTP_HOST'] ?? '', $host_central) !== 0) {
		header("Location: " . rtrim($central['url_seu'], '/') . "/makevoucher/");
		$link->close(); die;
	}
}

if(mysql1r("SELECT pot_fer_vals FROM `users` WHERE `username`='{$_SESSION['user']}'", $link) != '1') {
	echo "No tens permís per crear xecs regal. Demana que t'activin `pot_fer_vals` a `users`.";
	$link->close(); die;
}

$pot_triar_botiga = mysql1r("SELECT pot_veure_les_altres_empreses FROM `users` WHERE `username`='{$_SESSION['user']}'", $link) == '1';
// hi ha botigues amb el mateix nom (Value): es desambigua amb l'email, igual que fa relacions/index.php
$botigues = [];
if($pot_triar_botiga) {
	$q = $link->query("SELECT ID, Value, email FROM `Empresa` ORDER BY Value");
	while($e = $q->fetch_assoc()) {
		$botigues[(int)$e['ID']] = "{$e['ID']} → {$e['Value']}" . ($e['email'] != '' && $e['email'] != ' ' ? " ({$e['email']})" : '');
	}
}

/** Imprimeix el xec (capçalera de l'empresa triada + import + QR + codi + agraïment) a la impressora de tiquets */
function val_imprimeix_ticket($val, $empresa) {
	global $decimalsing, $thousandsing, $fcap, $euro, $taula;
	if(($empresa['bool_ticket_printer'] ?? '') != '1') return false;
	$fitxer = "val_{$val['codi']}.lp";
	$connector = new FilePrintConnector(__DIR__ . "/../scale/$fitxer");
	$printer = new Printerfestuc($connector);
	$printer->initialize();
	$printer->setJustification(Printer::JUSTIFY_CENTER);
	$printer->capcalera($empresa);
	$printer->setJustification(Printer::JUSTIFY_CENTER);
	$printer->selectPrintMode($fcap);
	$printer->text("XEC REGAL\n");
	// el símbol € no es pot escriure amb text() normal (es menja la taula de caràcters de la
	// impressora): cal seleccionar la taula i imprimir el byte crud, igual que a tiquet.php
	$printer->selectCharacterTable($taula);
	$printer->textRaw(number_format($val['import'], 2, $decimalsing, $thousandsing) . " " . chr($euro));
	$printer->selectCharacterTable();
	$printer->text("\n");
	$printer->selectPrintMode();
	$printer->feed(1);
	if(!empty($val['data_caducitat'])) $printer->text("Caduca: " . date('d/m/Y', strtotime($val['data_caducitat'])) . "\n");
	$printer->feed(1);
	// sense etiqueta al costat del QR: ja surt "XEC REGAL" i l'import més amunt, no cal repetir-ho
	tpv_imprimeix_qr($printer, $val['codi'], 300, null);
	$printer->feed(1);
	$printer->text($val['codi'] . "\n");
	$printer->feed(1);
	$printer->text("Moltes gràcies per la teva confiança!\n");
	$printer->text("T'esperem ben aviat a la botiga\nper gaudir del teu xec regal.\n");
	$printer->tallacapcalera($empresa);
	$printer->close();
	$ruta = __DIR__ . "/../scale/$fitxer";
	if(($empresa['host_ssh'] ?? '') != '') {
		$ok = val_imprimeix_remot($ruta, $fitxer, $empresa['host_ssh']);
	}
	else {
		$ok = ticket_print_command($fitxer);
		@unlink($ruta);
	}
	return $ok;
}

/** Envia el .lp per scp a la seu remota i hi crida el seu propi ticket_print_command (scale/imprimeix_remot.php) */
function val_imprimeix_remot($ruta_local, $fitxer, $host_ssh) {
	$host_ssh = preg_replace('/[^a-zA-Z0-9.\-]/', '', $host_ssh); // és d'Empresa.host_ssh, però per si algú hi fica brossa
	// assumeix que la seu remota té el mateix arrel web que aquesta (mateix install.sh/docker a totes dues)
	$scale_dir = $_SERVER['DOCUMENT_ROOT'] . '/scale';
	$desti = "root@{$host_ssh}:{$scale_dir}/" . $fitxer;
	exec("scp -o BatchMode=yes -o ConnectTimeout=10 " . escapeshellarg($ruta_local) . " " . escapeshellarg($desti) . " 2>&1", $out, $rc);
	@unlink($ruta_local);
	if($rc !== 0) return false;
	exec("ssh -o BatchMode=yes -o ConnectTimeout=20 " . escapeshellarg("root@{$host_ssh}")
		. " " . escapeshellarg("php {$scale_dir}/imprimeix_remot.php " . escapeshellarg($fitxer)) . " 2>&1", $out2, $rc2);
	return $rc2 === 0 && in_array('OK', $out2);
}

/** QR com a SVG incrustat, per a la vista prèvia (no passa per la impressora) */
function val_qr_svg($codi) {
	$qr = QrCode::create($codi);
	$svg = (new SvgWriter())->write($qr)->getString();
	return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function val_nom_empresa($ID_Empresa, $link) {
	return mysql1r("SELECT Value FROM `Empresa` WHERE ID='" . (int)$ID_Empresa . "'", $link);
}

$pas = 'form';
$missatge = '';
$val = null;
$val_ID_Empresa = null; // de quina empresa és el $val actual (la seva taula no ho porta a dins)

if($_SERVER['REQUEST_METHOD'] == 'POST') {
	$accio = $_POST['accio'] ?? '';

	if($accio == 'crea') {
		$import = (float)str_replace(',', '.', $_POST['import'] ?? '0');
		$caducitat = ($_POST['caducitat'] ?? '') !== '' ? $_POST['caducitat'] : null;
		$ID_Empresa_val = $pot_triar_botiga ? (int)($_POST['ID_Empresa'] ?? 0) : $ID_Empresa;
		if($pot_triar_botiga && !isset($botigues[$ID_Empresa_val])) {
			$missatge = "Tria una botiga.";
		}
		elseif($import <= 0) {
			$missatge = "Posa un import més gran que 0.";
		}
		else {
			$codi = val_crea($import, $ID_Empresa_val, $link, $caducitat, $_SESSION['user']);
			$val = val_cerca($codi, $link, $ID_Empresa_val);
			$val_ID_Empresa = $ID_Empresa_val;
			$pas = 'preview';
		}
	}
	elseif($accio == 'imprimeix' || $accio == 'anula') {
		$val_ID_Empresa = $pot_triar_botiga ? (int)($_POST['ID_Empresa'] ?? 0) : $ID_Empresa;
		if($accio == 'imprimeix') {
			$val = val_cerca($_POST['codi'] ?? '', $link, $val_ID_Empresa);
			if($val) {
				$empresa = $link->query("SELECT * FROM `Empresa` WHERE ID='" . (int)$val_ID_Empresa . "'")->fetch_assoc();
				$imprès = val_imprimeix_ticket($val, $empresa);
				$missatge = $imprès ? "Enviat a la impressora." : "No s'ha pogut imprimir: apunta el codi!";
				$pas = 'preview';
			}
		}
		else {
			val_anula($_POST['codi'] ?? '', $link, $val_ID_Empresa);
			$missatge = "Xec anul·lat, no es podrà gastar.";
			$pas = 'form';
		}
	}
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Xec regal</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family:sans-serif;font-size:22px;padding:20px;max-width:900px;margin:auto;">
<h1>Xec regal / descompte</h1>
<?php if($missatge): ?><p style="padding:15px;background:#eef;border-radius:8px;"><?= $missatge ?></p><?php endif; ?>

<?php if($pas == 'form'): ?>
<form method="post">
	<input type="hidden" name="accio" value="crea">
	<?php if($pot_triar_botiga): ?>
	<label>Botiga:<br>
		<select name="ID_Empresa" required style="font-size:22px;">
			<option value="0" selected disabled>Tria botiga</option>
			<?php foreach($botigues as $id => $nom): ?>
			<option value="<?= $id ?>"><?= htmlspecialchars($nom) ?></option>
			<?php endforeach; ?>
		</select>
	</label><br><br>
	<?php else: ?>
	Botiga: <b><?= htmlspecialchars(val_nom_empresa($ID_Empresa, $link)) ?></b><br><br>
	<?php endif; ?>
	<label>Import (€):<br>
	<input type="text" name="import" required autofocus style="font-size:22px;width:150px;" placeholder="20"></label>
	<br><br>
	<label>Caduca (opcional):<br>
	<input type="date" name="caducitat" style="font-size:22px;"></label>
	<br><br>
	<button type="submit" style="font-size:22px;padding:10px 30px;">Crea</button>
</form>

<?php elseif($pas == 'preview' && $val): ?>
<div style="text-align:center;border:2px solid #ccc;border-radius:12px;padding:20px;">
	<p>Botiga: <b><?= htmlspecialchars($botigues[$val_ID_Empresa] ?? val_nom_empresa($val_ID_Empresa, $link)) ?></b></p>
	<p style="font-size:38px;margin:10px 0;"><b><?= number_format($val['import'], 2, $decimalsing, $thousandsing) ?>€</b></p>
	<?php if($val['data_caducitat']): ?><p>Caduca: <?= date('d/m/Y', strtotime($val['data_caducitat'])) ?></p><?php endif; ?>
	<img src="<?= val_qr_svg($val['codi']) ?>" style="width:220px;height:220px;">
	<p style="font-family:monospace;font-size:16px;word-break:break-all;"><?= htmlspecialchars($val['codi']) ?></p>

	<form method="post" style="display:inline-block;margin:5px;">
		<input type="hidden" name="accio" value="imprimeix">
		<input type="hidden" name="codi" value="<?= htmlspecialchars($val['codi']) ?>">
		<input type="hidden" name="ID_Empresa" value="<?= $val_ID_Empresa ?>">
		<button type="submit" style="font-size:20px;padding:10px 20px;">&#128424; Imprimeix</button>
	</form>
	<form method="post" style="display:inline-block;margin:5px;" onsubmit="return confirm('Anul·lar aquest xec? No es podrà gastar.');">
		<input type="hidden" name="accio" value="anula">
		<input type="hidden" name="codi" value="<?= htmlspecialchars($val['codi']) ?>">
		<input type="hidden" name="ID_Empresa" value="<?= $val_ID_Empresa ?>">
		<button type="submit" style="font-size:20px;padding:10px 20px;">Anul·la</button>
	</form>
</div>
<br>
<a href="./">&larr; Fer-ne un altre</a>
<?php endif; ?>

<h2>Xecs creats</h2>
<table style="width:100%;border-collapse:collapse;font-size:16px;">
<tr style="text-align:left;border-bottom:2px solid #ccc;">
	<th style="padding:6px;">Data</th>
	<?php if($pot_triar_botiga): ?><th style="padding:6px;">Botiga</th><?php endif; ?>
	<th style="padding:6px;">Import</th>
	<th style="padding:6px;">Estat</th>
	<th style="padding:6px;">Caducitat</th>
	<th style="padding:6px;">Accions</th>
</tr>
<?php foreach(val_llista($link, $ID_Empresa, $pot_triar_botiga) as $v): ?>
<tr style="border-bottom:1px solid #eee;">
	<td style="padding:6px;"><?= date('d/m/Y', strtotime($v['data_emissio'])) ?></td>
	<?php if($pot_triar_botiga): ?><td style="padding:6px;"><?= htmlspecialchars($v['botiga'] ?? '') ?></td><?php endif; ?>
	<td style="padding:6px;"><?= number_format($v['import'], 2, $decimalsing, $thousandsing) ?>€</td>
	<td style="padding:6px;">
		<?php if($v['estat'] == 'emes'): ?>
			Per gastar
		<?php elseif($v['estat'] == 'redimit'): ?>
			Gastat el <?= date('d/m/Y', strtotime($v['data_redempcio'])) ?>
		<?php else: ?>
			Anul·lat
		<?php endif; ?>
	</td>
	<td style="padding:6px;"><?= $v['data_caducitat'] ? date('d/m/Y', strtotime($v['data_caducitat'])) : '-' ?></td>
	<td style="padding:6px;white-space:nowrap;">
		<form method="post" style="display:inline;">
			<input type="hidden" name="accio" value="imprimeix">
			<input type="hidden" name="codi" value="<?= htmlspecialchars($v['codi']) ?>">
			<input type="hidden" name="ID_Empresa" value="<?= (int)$v['ID_Empresa'] ?>">
			<button type="submit" title="Reimprimeix" style="font-size:18px;">&#128424;</button>
		</form>
		<?php if($v['estat'] == 'emes'): ?>
		<form method="post" style="display:inline;" onsubmit="return confirm('Anul·lar aquest xec? No es podrà gastar.');">
			<input type="hidden" name="accio" value="anula">
			<input type="hidden" name="codi" value="<?= htmlspecialchars($v['codi']) ?>">
			<input type="hidden" name="ID_Empresa" value="<?= (int)$v['ID_Empresa'] ?>">
			<button type="submit" title="Anul·la" style="font-size:18px;">&#10060;</button>
		</form>
		<?php endif; ?>
	</td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>
