<?php

/*
Uguisu メール送信フォーム


@param
reply: 引用元メールアドレス
replyAll: boolean trueなら、全員に返信(Cc引継ぎ)

account,to,cc,bcc,from,reply-to,references,subject,body
:メールフォームに配置
*/


chdir("../");

require("config/config.php");
require($CONFIG['authScript']);
require($CONFIG['loggerClass']);
require($CONFIG['accountSettingFile']);
//require($CONFIG['accountListClass']);

//require($CONFIG['accountClass']);
//require($CONFIG['mailAddressCheckClass']);

require($CONFIG['mailSendClass']);
UguisuMailSend::initialize();

require($CONFIG['smtpClass']);

if(!isset($_GET['mode'])) $_GET['mode'] = "";

//$mailAddressChecker = new MailAddressChecker();

$aryErr = array();
$MAIL   = array();

//$_GET = array_map("encode_from_get",$_GET);

if($_GET['mode'] == "submit"){
	
	//送信処理
	list($result,$aryErr) = UguisuMailSend::sendMail($_POST);
	if(!count($aryErr) && $result){
		//送信完了
		include("submit-complete.php");
		exit();
		
	}else if(!count($aryErr)){
		//送信エラー、けっこう重大なエラーなので、HTMLの書式を無視して表示する
		echo "メール送信エラー<br />";
		echo "<pre>\n";
		echo htmlspecialchars(UguisuMailSend::getSmtpLog());
		echo "</pre>\n";
	}
}



//POSTクエリ、GETクエリをMAILにマッピング
$mailDataIndex=array('account','to','cc','bcc','from','reply-to','references','subject','body');
foreach($mailDataIndex as $i){
	if(isset($_POST[$i])){
		$MAIL[$i] = $_POST[$i];
	}else if(isset($_GET[$i])){
		$MAIL[$i] = $_GET[$i];
	}else{
		$MAIL[$i] = "";
	}
}

if(isset($_POST['account']) && isset($ACCOUNT[$_POST['account']])){
	$account = $_POST['account'];
}else if(isset($_GET['account']) && isset($ACCOUNT[$_GET['account']])){
	$account = $_GET['account'];
}else{
	$keys = array_keys($ACCOUNT);
	$account = $keys[0];
}

//replyの引数があったら、受信したメールから関連ヘッダを検索
if(isset($_GET['reply']) && $_GET['reply']){
	$MAIL = UguisuMailSend::getReplyParameter($_GET['account'],$_GET['reply']);
	
	$MAIL['body'] = "\n\n".$MAIL['body']; //引用本文の前に改行2つ
	if(isset($_GET['replyAll']) && $_GET['replyAll']){
		//
		
	}else{
		//全員に返信しない場合はCc不要
		$MAIL['cc'] = "";
	}
}

//メールアドレスを正規化
//$addressCheckIndex=array('to','cc','bcc','reply-to','from');
//foreach($addressCheckIndex as $i){
//	if($MAIL[$i]) $MAIL[$i] = $mailAddressChecker->trimMulti($MAIL[$i]);
//}


/**
 * エラーメッセージをプリント
 */
function printError($key){
	global $aryErr;
	if(isset($aryErr[$key]) && $aryErr[$key]){
		print "<strong class=\"error\">".$aryErr[$key]."</strong>\n";
	}
}

header("Content-Type: text/html; charset=".BASE_ENCODING);
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
?><!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo BASE_ENCODING; ?>" />
<meta http-equiv="Cache-Control" content="no-cache" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Expires" content="0" />
<link rel="stylesheet" type="text/css" href="../css/submit-form.css" />
<script type="text/JavaScript">
var aryMailA = new Array();
<?php
foreach($ACCOUNT as $i => $ary){
	echo "aryMailA['".$i."'] = '".$ary['smtp-a-cc']."|||".$ary['smtp-a-bcc']."';\n";
}
?>

function changeAccount(myAccount){
	document.getElementById('form_from').value="<"+myAccount+">";
	document.getElementById('form_reply-to').value="<"+myAccount+">";
	
	var aryTmp = aryMailA[myAccount].split("|||");
	
	if(!document.getElementById('form_subject').value){
		document.getElementById('form_cc').value  = aryTmp[0];
	}else{
		if(!document.getElementById('form_cc').value){
			document.getElementById('form_cc').value  = aryTmp[0];
		}
	}
	
	document.getElementById('form_bcc').value = aryTmp[1]; //bccは無条件で書き換え
}

inputIds = new Array(
	"form_account","form_to","form_cc","form_bcc","form_from",
	"form_reply-to","form_references","form_subject"
);


function resize(){
	form_body_height_min = 200;
	form_body_height_dec = 310;
	//form_body_height_dec = 290;
	
	h = document.documentElement.clientHeight;
	w = document.documentElement.clientWidth;
	document.getElementById('table-mailform').style.height=h+"px";
	
	inputWidth = Math.floor(w * 0.8) - 5;
	for(var i in inputIds){
		document.getElementById(inputIds[i]).style.width=inputWidth+"px";
	}
	
	//document.getElementById('table-mailform').style.width=w+"px";
	
	bh = h - form_body_height_dec;
	if(bh < form_body_height_min) bh = form_body_height_min; //>
	document.getElementById('form_body').style.height=bh+"px";
	document.getElementById('from_body_td').style.height=bh+"px";
}

function countChar(){
	c = document.getElementById("form_body").value.length;
	alert("文字数: "+c);
	//document.getElementById("char-count").value = "文字数: "+c;
}

function createUrl(){
	var linkTitle = "Mail";
	var paramList = {
		"form_account"   : "account",
		"form_to"        : "to",
		"form_cc"        : "cc",
		"form_bcc"       : "bcc",
		"form_from"      : "from",
		"form_reply-to"  : "reply-to",
		"form_references": "references",
		"form_subject"   : "subject",
		"form_body"      : "body"
	}
	var url = location.protocol + "//"+location.host+location.pathname+"?";
	for(var key in paramList){
		if(document.getElementById(key).value){
			url += paramList[key]+"="+encodeURIComponent(document.getElementById(key).value)+"&";
			if(key == "form_subject"){
				linkTitle = document.getElementById(key).value;
			}
		}
	}
	
	document.getElementById("pageUrlArea").innerHTML= '<a href="'+url+'" target="_blank">'+linkTitle+'</a>';
	alert(url);
}
	
	
	
</script>

<title>Uguisu: mail submit form</title>
</head>
<body onLoad="changeAccount('<?php echo $account; ?>');resize();" onResize="resize();">
<table id="table-mailform">
<form name="form1" id="form1" action="submit-form.php?mode=submit" method="post" enctype="multipart/form-data" onSubmit="return confirm('送信してよろしいですか?');" >

<tr class="input-line">
<th>Account:</th>
<td>
<select name="account" id="form_account" onChange="changeAccount(this.value);" >
<?php
foreach($ACCOUNT as $i => $aryTmp){
	echo "<option value=\"".$i."\" ".($account==$i?"selected":"")." >".$i."</option>\n";
}
?>
</select>
</td>
</tr>

<tr class="input-line">
<th>To:</th>
<td>
<?php printError('to'); ?>
<input name="to" id="form_to" value="<?=htmlspecialchars($MAIL['to'])?>" class="text_input" /></td>
</tr>

<tr class="input-line">
<th>Cc:</th>
<td>
<?php printError('cc'); ?>
<input name="cc" id="form_cc" value="<?=htmlspecialchars($MAIL['cc'])?>" class="text_input" /></td>
</tr>

<tr class="input-line">
<th>Bcc:</th>
<td>
<?php printError('bcc'); ?>
<input name="bcc" id="form_bcc" value="<?=htmlspecialchars($MAIL['bcc'])?>" class="text_input" /></td>
</tr>

<tr class="input-line">
<th>From:</th>
<td>
<?php printError('from'); ?>
<input name="from" id="form_from" value="<?=htmlspecialchars($MAIL['from'])?>" class="text_input" /></td>
</tr>

<tr class="input-line">
<th>Reply-To:</th>
<td>
<?php printError('reply-to'); ?>
<input name="reply-to" id="form_reply-to" value="<?=htmlspecialchars($MAIL['reply-to'])?>" class="text_input" /></td>
</tr>

<tr class="input-line">
<th>References:</th>
<td><input name="references" id="form_references" value="<?=htmlspecialchars($MAIL['references'])?>" class="text_input" /></td>
</tr>

<tr class="input-line">
<th>Subject:</th>
<td><input name="subject" id="form_subject" value="<?=htmlspecialchars($MAIL['subject'])?>" class="text_input" /></td>
</tr>

<tr class="body-area">
<td colspan="2" id="from_body_td"><textarea name="body" id="form_body" value="" /><?=htmlspecialchars($MAIL['body'])?></textarea></td>
</tr>

<tr class="input-line">
<th>Attach1:</th>
<td><input name="attach1" id="attach1" type="file" class="file_input" size="50" /></td>
</tr>

<tr class="input-line">
<th>Attach2:</th>
<td><input name="attach2" id="attach2" type="file" class="file_input" size="50" /></td>
</tr>

<tr class="blank-line">
<td colspan="2">
<input type="submit" value="送信" style="font-weight:bold;" /> 
<input type="button" id="char-count" value="文字数をカウント" onClick="countChar();" title="クリックして本文の文字数をカウント" />
<input type="button" id="pageUrl" value="このページのURL" onClick="createUrl();" title="To,件名などをデフォルトセットした送信フォームURLを作成" />
<span id="pageUrlArea"></span>
</td>
</tr>
</form>
</table>

<?php Logger::output($CONFIG['debugLevel']); ?>

</body>
</html>
