<?php
// Imprimeix etiqueta simplificada de venda (fallback quan no hi ha impressora de tiquets)
// Paràmetres: uuid, year, ID_Empresa, close (opcional)
require_once('login.php');
if($login == false) { echo "nologin"; $link->close(); die; }

require '../vendor/autoload.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

date_default_timezone_set('Europe/Andorra');

if(empty($_REQUEST['uuid'])) { $link->close(); header("Location: $scale_uri"); die; }
if(isset($_REQUEST['ID_Empresa'])) $ID_Empresa = $_REQUEST['ID_Empresa'];
if(isset($_REQUEST['year'])) $year = $_REQUEST['year'];
else $year = date("Y");

$r = detallrelacio($_REQUEST['uuid'], $ID_Empresa, $year, $link);
if(!is_array($r)) { $link->close(); header("Location: $scale_uri"); die; }

$empresa = $link->query("SELECT * FROM Empresa WHERE `ID`='$ID_Empresa'")->fetch_assoc();
$link->close();

// Genera QR apuntant al PDF públic
global $pdf_public_url;
$qr_url = (!empty($pdf_public_url) ? $pdf_public_url : "https://{$_SERVER['HTTP_HOST']}/pdf") . "/{$r['uuid']}.pdf";
$writer = new SvgWriter();
$qrCode = new QrCode($qr_url);
$qrCode->setMargin(2);
$qr_result = $writer->write($qrCode);
$qr_data_uri = 'data:image/svg+xml;base64,' . base64_encode($qr_result->getString());

// Carrega plantilla SVG
$svg = file_get_contents(__DIR__ . '/img/tiquet_simplificat.svg');

// Substitueix placeholders de text
$total_fmt = number_format($r['Total'], 2, $decimalsing, $thousandsing);
$svg = str_replace(
    "{\$r['Empresa']['Value']}{\$r['Empresa']['NIF']}",
    htmlspecialchars(urldecode($r['Empresa']['Value']) . ' ' . $r['Empresa']['NIF']),
    $svg
);
$svg = str_replace(
    "{\$r['tipus_relacio']}:{\$r['Empresa']['ID']}.{\$r['ID']}",
    htmlspecialchars($r['tipus_relacio']['Value'] . ':' . $r['Empresa']['ID'] . '.' . $r['ID']),
    $svg
);
$svg = str_replace(
    "A pagar: {\$r['Total']€.",
    "A pagar: {$total_fmt}€.",
    $svg
);
$svg = str_replace(
    "Línees actives:{\$r['activelines']} ",
    "Línees actives:{$r['activelines']} ",
    $svg
);

// Substitueix el rectangle QR per una imatge amb el QR real (rect és multilinea, usar DOTALL)
$qr_image = '<image x="33.6087" y="272.22836" width="20.264069" height="20.264069"'
          . ' id="qr_image" href="' . $qr_data_uri . '" />';
$svg = preg_replace('/<rect[^>]*inkscape:label="qr".*?\/>/s', $qr_image, $svg);

// Guarda SVG a /tmp
$uuid = $r['uuid'];
$svg_path = "/tmp/{$uuid}.svg";
$ps_path  = "/tmp/{$uuid}.ps";
file_put_contents($svg_path, $svg);

// Converteix SVG→PS amb inkscape via ssh root@localhost (igual que p.php)
$inkscape_cmd = localcmd("inkscape $svg_path -o $ps_path");
exec($inkscape_cmd, $ink_out, $ink_ret);

// Imprimeix via printerserver (igual que tag_print_command a p.php)
$lp_cmd = "ssh {$empresa['printerserver']} \"lp $ps_path -d {$empresa['sticker_printer']}\"";
exec($lp_cmd, $lp_out, $lp_ret);

// Neteja
@unlink($svg_path);
@unlink($ps_path);

if($_REQUEST['close']) echo "<script>window.open('', '_self', ''); window.close();</script>";
else header("Location: $scale_uri");
?>
