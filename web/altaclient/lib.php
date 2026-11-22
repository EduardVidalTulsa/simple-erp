<?php
$uri=$_SERVER['SERVER_PORT']==80?'http://':'https://';
$uri.=$_SERVER['HTTP_HOST'];
$args=explode("/",$_SERVER['REQUEST_URI']);
$preg_net="/^(?=.{4,255}$)(?![_.-])(?!.*[_.]{2})[a-zA-Z 0-9._-]+(?<![_.])$/";
if($args[2]=='lib.php')header("Location: {$uri}/{$args[1]}");
function send_web($web){
	echo  preg_replace('/\s\s+/', ' ',str_replace(Array("\n","\t"),"",$web));
}
// filter_var($str, FILTER_SANITIZE_STRING);
function formulari_altaclient($r=false){
	global $s,$w,$h,$link,$preg_campnet,$preg_net;
	$fontsize=15;
	include("simple-php-captcha.php");
	$_SESSION['captcha'] = simple_php_captcha();
	// tot el que ve de $_REQUEST s'escapa abans d'anar a l'HTML: si no, es pot injectar HTML/JS
	// (p.ex. value="x" onmouseover="..." o un <script>) tornant a mostrar el formulari
	$evalue = htmlspecialchars((string)($_REQUEST['Value'] ?? ''), ENT_QUOTES);
	$efiscal_address = htmlspecialchars((string)($_REQUEST['fiscal_address'] ?? ''), ENT_QUOTES);
	$eemail = htmlspecialchars((string)($_REQUEST['email'] ?? ''), ENT_QUOTES);
$web="
<div class='register_form'><form ACTION =\"{$s['app']['uri']}\" METHOD=\"POST\">
<input type=text name=\"Value\"  MAXLENGTH=30 size=10  id=\"Value\" value=\"{$evalue}\" placeholder=\" Raó social, Cognoms, Nom ex: Fruits Secs Tulsà SC / Pi i Noguera, Pere \" class=\"form-control\" autofocus autocomplete=\"off\"
style=\" width: 99%; height: 40px; font-size:{$fontsize}px; text-align: left;\">
".($r?($r['Value']?'':'<br/>Invalid user'.(preg_match($preg_net,$_REQUEST['Value'])?':'.$evalue:' due invalid characters')):'')."
<input type=text name=\"fiscal_address\"  MAXLENGTH=30 size=10  id=\"fiscal_address\" value=\"{$efiscal_address}\" placeholder=\"Direcció fiscal ex: C/Santa Eugènia 163\" class=\"form-control\"
 autocomplete=\"off\"
style=\" width: 99%; height: 40px; font-size:{$fontsize}px; text-align: left;\">
".($r?($r['fiscal_address']?'':'<br/>Invalid user'.(preg_match($preg_net,$_REQUEST['Value'])?':'.$evalue:' due invalid characters')):'')."

<br/>
<input type=text name=\"email\"  MAXLENGTH=30 size=10 placeholder=\"Your well protected e-mail \" value=\"".($r['validemail']?$eemail:'')."\"  class=\"form-control\" autofocus autocomplete=\"off\"
style=\" width: 99%; height: 40px;\"
>".($r['validemail']?'':($r?'<br/>No valid email '.(strip_tags($_REQUEST['email'])?'':': '.$eemail):''))."
<br/>

<input type=text name=\"captcha\"  MAXLENGTH=30 size=10 placeholder=\"Resolve Captcha\" class=\"form-control\" autofocus autocomplete=\"off\"

style=\" width: 72%; height: 40px;\"
> <img src='{$_SESSION['captcha']['image_src']}' alt=\"CAPTCHA code\" style=\" vertical-align:middle; width: 20%; height: 40px;\">

".($_SESSION['failed captcha']?'<br/>Captcha was failed :(':'')."
<br/>
<input type=\"checkbox\" name=\"acceptterms\"".($r['acceptterms']?'checked':'')." ><a href=\"{$uri_head}/about/\"target=\"_blank\">Accept terms & conditions</a> <br/>
".($r['acceptterms']?'':($r?'<br/>You must accept terms for register':''))."
<input name='sumit' value='Register' type='submit'></div>
";
	//send_web($web);
echo $web;
unset($_SESSION['failed captcha']);
}
?>
