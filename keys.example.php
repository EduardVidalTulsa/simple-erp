<?php
// SPDX-License-Identifier: GPL-3.0-or-later
//
// simple-erp configuration. Copy it to keys.php next to the web/ directory (one level above the
// document root, so it is never served) and fill in your values. keys.php is never committed.

// --- database (MariaDB/MySQL) ---
$myserver = 'localhost';
$myuser = 'simple_erp';
$mypassword = 'change-me';
$mydatabase = 'simple_erp';

// --- shop / company (printed on labels, tickets and PDFs) ---
$empresa_nom = 'Example Shop';            // shown on the login screen and tickets
$web_etiqueta = 'example.org';            // printed on price labels; the QR goes to https://<this>/<product id>
$condicions_url = '';                     // sales conditions link in the PDF footer ('' = none)
$pdf_public_url = '';                     // public base URL where invoice/ticket PDFs are published
                                          // ('' = do not offer PDF links/QR codes), e.g. https://example.org/pdf
$pdf_scp_target = '';                     // scp target to publish PDFs, e.g. user@host:/srv/pdf/ ('' = off)
// shops (Empresa.ID => name) for the product sales listing
$empreses = [1 => 'Shop 1'];

// --- Telegram notifications ('' = off) ---
$telegramtoken = '';
$telegrambotname = '';
$telegramdebugchannel = '';
$telegram_canal_preus = '';               // chat id notified when a price changes

// --- WhatsApp and SMS (shell commands, '' = off). {to} and {msg} are replaced, already quoted ---
$whatsapp_cmd = '';                       // e.g. 'npx mudslide send {to} {msg}'
$sms_cmd = '';                            // e.g. 'ssh admin@router "/tool sms send lte1 phone-number={to} message={msg}"'

// --- e-mail (PHPMailer, SMTP with STARTTLS on 587) ---
$em['host'] = 'smtp.example.org';
$em['user'] = 'shop@example.org';
$em['passwd'] = 'change-me';

// --- shared secrets for the scale and staff clock-in endpoints (long random strings) ---
$tlineakey = 'change-me';
$singkey = 'change-me';
$sing_uri = 'https://example.org/scale/SingInAtWork.php';

// --- printing ---
$receipt_printer_model = 'NP-350';        // 'NP-350', 'csi 100' or 'citizen 2000' (see scale/phpgeneral/receipt.php)
$receipt_printer_ip = '';                 // network receipt printer (RAW 9100); '' = USB on $receipt_printer_host
$receipt_printer_host = 'localhost';      // machine with the USB receipt printer (reached by ssh)
$tag_destination = '';                    // scp destination for label files ('' = print locally)
$midaetiqueta = '';                       // label size suffix: '' (default) or '57x45'
$localuser = '';                          // '' runs pdflatex/inkscape directly; 'user@' runs them through ssh user@localhost
$smbshare = '';                           // optional SMB share for exports
$smbuser = '';
$smbpasswd = '';

// Local shell command, run directly or through "ssh $localuser localhost" when PHP itself is not
// allowed to run pdflatex/inkscape (set $localuser above).
function localcmd($cmd)
{
	global $localuser;
	return $localuser === '' ? $cmd : "ssh {$localuser}localhost " . escapeshellarg($cmd);
}

// Sends a receipt (ESC/POS file in web/scale/) to the receipt printer. Returns false on failure.
function ticket_print_command($filename, $host = null)
{
	global $receipt_printer_ip, $receipt_printer_host;
	$fitxer = "{$_SERVER['DOCUMENT_ROOT']}/scale/$filename";
	if (!empty($receipt_printer_ip)) {
		$fp = @fsockopen($receipt_printer_ip, 9100, $errno, $errstr, 3);
		if (!$fp) {
			debugtelegram("Cannot reach the receipt printer $receipt_printer_ip:9100 ($errstr)", gethostname());
			return false;
		}
		fwrite($fp, file_get_contents($fitxer));
		fclose($fp);
		return true;
	}
	// USB printer on another machine: copy the file and write it to the first /dev/usb/lp*
	$host = $host ?: $receipt_printer_host;
	$h = escapeshellarg("root@$host");
	exec("scp " . escapeshellarg($fitxer) . " $h:/tmp/" . escapeshellarg($filename), $o, $r1);
	exec("ssh $h 'cat /tmp/" . escapeshellcmd($filename) . " > \$(ls /dev/usb/lp* | head -1)'", $o, $r2);
	return $r1 == 0 && $r2 == 0;
}

// Builds a PDF from a TeX file in web/scale/pdf/ (or web/scale2/pdf/).
function crea_pdf($nf)
{
	exec("cd {$_SERVER['DOCUMENT_ROOT']}/scale/pdf && texi2pdf " . escapeshellarg($nf));
}
function crea_pdf2($nf)
{
	exec("cd {$_SERVER['DOCUMENT_ROOT']}/scale2/pdf && texi2pdf " . escapeshellarg($nf));
}
