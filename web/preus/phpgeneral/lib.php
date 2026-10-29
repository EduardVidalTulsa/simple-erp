<?php
function debugtelegram($debug,$valor='debug',$telegramchannel=FALSE,$return=FALSE){
	global $telegramtoken,$telegramdebugchannel;
	if($telegramchannel==FALSE)$telegramchannel=$telegramdebugchannel;
	if (is_array($debug)){
		foreach ($debug AS $k=> $v){
			$telegramme.=debugtelegram($v,"$valor".'['.$k.']',$telegramchannel,true)."\n";
		}
		if(empty($debug))$telegramme="$valor: empty array";
	}
	else $telegramme="$valor: ".urldecode($debug!=''?$debug:'empty value');
	if($return)return $telegramme;
	else $response = file_get_contents("https://api.telegram.org/bot$telegramtoken/sendMessage?chat_id=-$telegramchannel&text=".urlencode($telegramme!=''?$telegramme:'Debug return empty') );

	//$resposta=file_get_contents("https://api.telegram.org/bot$telegramtoken/getUpdates?offset=0&limit=1");
	//$update = json_decode($response, true);
	//if($telegramchannel!=$telegramdebugchannel){pre($response); echo "update"; tpre();}
	
}
function myErrorHandler($errno, $errstr, $errfile, $errline){
   /* if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting, so let it fall
        // through to the standard PHP error handler
        return false;
    }
	*/
	  // $errstr may need to be escaped:
    $errstr = htmlspecialchars($errstr);
    switch ($errno) {
    case E_USER_ERROR:
        $d=debugtelegram( "<b>My ERROR</b> [$errno] $errstr<br />\n",true);
        $d.=debugtelegram( "  Fatal error on line $errline in file $errfile",true);
      	$d.=debugtelegram(", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n",true);
        $d.=debugtelegram( "Aborting...<br />\n",true);
	debugtelegram($d);
        exit(1);

    case E_USER_WARNING:
        debugtelegram("<b>My WARNING</b> [$errno] $errstr<br />\n");
        break;

    case E_USER_NOTICE:
        debugtelegram("<b>My NOTICE</b> [$errno] $errstr<br />\n");
        break;
        case E_WARNING:
		 $_SESSION['E_WARNING'].="E_WARNING $errstr on on line: $errline@$errfile\n";
		break;
	case E_NOTICE:
		 $_SESSION['E_NOTICE'].="E_NOTICE $errstr on on line: $errline@$errfile\n";
		break;
    default:
        debugtelegram("Unknown error type: [$errno] $errstr on line: $errline@$errfile <br />\n");
        break;
    }

    /* Don't execute PHP internal error handler */
    return true;

}

function normalize($cadena){
    //treu dead keys
      $originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞ
ßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
    $modificadas = 'aaaaaaaceeeeiiiidnoooooouuuuy
bsaaaaaaaceeeeiiiidnoooooouuuyybyRr';
    $cadena = utf8_decode($cadena);
    $cadena = strtr($cadena, utf8_decode($originales), $modificadas);
    $cadena = strtolower($cadena);
    return utf8_encode($cadena);
}
function treuapostrof($cadena){
    return str_replace("'", ' ',$cadena);
}
?>
