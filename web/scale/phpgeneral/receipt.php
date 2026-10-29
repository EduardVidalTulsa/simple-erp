<?php
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\EscposImage;

if($receipt_printer_model=="citizen 2000"){
	//citizen
	$euro=213;//a citizen 213
	$taula=2;//a citizen 2
	$fcap=184;//184
	$f2=8;//'41'
	$f3=9;
	$fref=40;//40
	$llpreus=66;//66
	$lldescompte=48;//48
	$llref=25;//25
	$llrecepta=49;
	$llates=49;//49
	$maxespais=64;
}
if($receipt_printer_model=="csi 100"){
// 	die;
	//csi 100
	$euro=128;//a citizen 213
	$taula=16;//a citizen 2
	$fcap=177;//184
	$f2=8;//'41'
	$f3=9;
	$fref=8;//40
	$llpreus=58;//66
	$lldescompte=42;//48
	$llref=30;//25
	$llrecepta=49;
	$llates=43;//49
	$maxespais=56;

}
if($receipt_printer_model=="NP-350"){
// 	die;
	//csi 100
	$euro=128;//a citizen 213
	$taula=16;//a citizen 2
	$fcap=184;//184 font de la capçalera
	$f2=8;//'41'
	$f3=9;
	$fref=8;//40
	$llpreus=66;//66 caracters del que tingui el mateix numero de font (factura ... preus data)
	$lldescompte=48;//48
	$llrecepta=49;
	$llref=30;//25
	$llates=49;//49 longitut atés per 
	$maxespais=64; //aixo si que es llpreus

}
// punts (1/203") que avancem abans d'imprimir la capçalera del proper tiquet, perquè
// el tall caigui entre el final d'aquest i la capçalera: si talla l'última línia, puja'l;
// si queda massa blanc, baixa'l
if(!isset($tall_punts))$tall_punts=56;

if (!extension_loaded('mbstring'))debugtelegram("Aquest servidor no té mbstring habilitat, pots habilitar-ho a php.ini?",$_SERVER['HTTP_HOST']);

class PrinterTiquet extends Printer{
	// Empresa.tiquet_cut_before_head1_bool=0: cada tiquet comença amb la seva capçalera (aquí)
	// i acaba amb cut(). A 1 no fa res, la capçalera ja la va imprimir tallacapcalera().
	public function capcalera($empresa){
		global $fcap,$taula;
		if(!empty($empresa['tiquet_cut_before_head1_bool']))return;
		$this -> setJustification(Printer::JUSTIFY_CENTER);
		$this -> selectCharacterTable($taula);
		$this -> selectPrintMode($fcap);
		$this -> text("{$empresa['termal_head_1']}\n");
		$this -> selectPrintMode();
	}
	// Empresa.tiquet_cut_before_head1_bool=1: en lloc de cut() (GS V 65 n, que fa córrer el paper
	// fins a la ganiveta), aprofitem el tros entre el capçal i la ganiveta per imprimir la capçalera
	// del proper tiquet i tallem sense avançar (GS V 0). A 0, cut() normal.
	public function tallacapcalera($empresa){
		global $fcap,$taula,$tall_punts;
		if(empty($empresa['tiquet_cut_before_head1_bool'])){
			$this -> cut();
			return;
		}
		$capcalera=$empresa['termal_head_1'];
		$this -> selectPrintMode();
		$this -> getPrintConnector() -> write(Printer::ESC."J".chr($tall_punts));
		$this -> setJustification(Printer::JUSTIFY_CENTER);
		$this -> selectCharacterTable($taula);
		$this -> selectPrintMode($fcap);
		$this -> text("$capcalera\n");
		$this -> selectPrintMode();
		$this -> getPrintConnector() -> write(Printer::GS."V".chr(0));
	}
	public function line($v,$gran=true){
		// $gran: font A, hi caben $lldescompte caràcters; si no, font B amb $maxespais
		global $decimalsing,$thousandsing,$euro,$taula,$maxespais,$lldescompte;
		$this -> selectPrintMode($gran ? Printer::MODE_FONT_A : Printer::MODE_FONT_B);
		$max = $gran ? $lldescompte : $maxespais;
		$this-> setJustification(Printer::JUSTIFY_LEFT);
		$linea="{$v['urlde']} ";
		if($v['lotnumber']!='')$linea.="Lot: {$v['lotnumber']} ";
		if($v['bulk']==0)$num1=number_format_trim($v['units'],3,$decimalsing,$thousandsing);
		else $num1=number_format($v['units'],3,$decimalsing,$thousandsing);
		$num1.=$v['u']." ".number_format($v['cost'],2,$decimalsing,$thousandsing).chr($euro)."/{$v['u']} ";
		if($v['discount']>0)$num1.='-'.number_format_trim($v['discount'],2,$decimalsing,$thousandsing).'% ';
		if($v['tax_detall']==1){
			$num1.='IVA '.number_format_trim($v['IVA'],2,$decimalsing,$thousandsing).'% ';
			if(($v['EQUIVALENCY_CHARGE']>0)&&($_SESSION['Empresa']['sell_wholesale']==1))
				{$num1.='RE '.number_format_trim($v['EQUIVALENCY_CHARGE'],2,$decimalsing,$thousandsing).'% ';}
			$num1.=number_format($v['Base'],2,$decimalsing,$thousandsing).chr($euro);
		}
		if($v['tax_detall']==0)$num1.=number_format($v['tline'],2,$decimalsing,$thousandsing).chr($euro);
		if (extension_loaded('mbstring')) {
			 $espaisocupats = mb_strlen($linea, 'UTF-8') + mb_strlen($num1, 'UTF-8');
		} 
		else {
        		$espaisocupats = strlen($linea) + strlen($num1);
		}
	//	debugtelegram("$linea \n strlen: " . strlen($linea) + strlen($num1) . ", mb_strlen: " . mb_strlen($linea, 'UTF-8')+ mb_strlen($num1, 'UTF-8')." espaisocupats=$espaisocupats", $_SERVER['HTTP_HOST']);
	//	$espaisocupats=strlen($linea)+strlen($num1);
		$espaisblancs=$max-$espaisocupats;
		if ($espaisocupats>=$max){
			$text="$linea\n";
			$num1=str_pad($num1,$max,' ',STR_PAD_LEFT);
		}
		else $text=str_pad($linea, $max-strlen($num1));
		$this->text($text);
		$this -> selectCharacterTable($taula);
		$this ->textRaw($num1);
		$this -> selectCharacterTable();
		$this -> text("\n");
		/*$this -> selectPrintMode();
		$this->text("{$maxespais}=".strlen($num1));
		$this->text("+".strlen($linea)."+{$espaisblancs}=");
		$this ->text(strlen($text).'+'.strlen($num1)."\n");*/
	}
	public function euro(){
		global $taula;
		$this -> selectCharacterTable($taula);
	    $chars = str_repeat(' ', 256);
	    for ($i = 0; $i < 255; $i++) {
	        $chars[$i] = ($i > 32 && $i != 127) ? chr($i) : ' ';
	    }
		//return($chars[$euro]);
		$this->textRaw($chars[$euro]);
		$this -> selectCharacterTable();
	}
}
function customWordWrap($string, $width) {
    $words = explode(' ', $string);
    $output = '';
    $line = '';

    foreach ($words as $word) {
        if ((strlen($line) + strlen($word)) < $width) {
            $line .= $word . ' ';
        } else {
            $output .= trim($line) . "\n";
            $line = $word . ' ';
        }
    }
    $output .= trim($line);
    return $output;
}

?>
