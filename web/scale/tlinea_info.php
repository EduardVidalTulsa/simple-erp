<?php
/************
*
*	Retorna informació de l'última línia i hora per actualitzar divs fixos via AJAX
*
************/
require("login.php");

if($login){
    $info = array();
    
    // Generar HTML per última línia
    $ultim_html = '';
    if(isset($_SESSION['lastline'])) {
        $lastline = $_SESSION['lastline'];
        $background = $lastline[$lastline['table']]["units"] > 0 ? "" : "background:red;";
        $ku = $lastline[$lastline['table']]['bulk'] == '1' ? 'Kg' : 'Un';
        
        if($background) {
            $ultim_html .= '<div style="' . $background . '">';
        }
        $ultim_html .= 'ultim: '.$lastline[$lastline['table']]['units'].$ku.' '.
                       $lastline[$lastline['table']]['description'];
        if($background) {
            $ultim_html .= '</div>';
        }
    }
    
    // Generar HTML per hora i ID
    $hora_html = "id:{$ID_Empresa}.{$idbascula} ".date("H:i:s");
    
    $info['ultim'] = $ultim_html;
    $info['hora'] = $hora_html;
    
    echo json_encode($info);
}
else {
    echo json_encode(array('ultim' => '', 'hora' => ''));
}
?>
