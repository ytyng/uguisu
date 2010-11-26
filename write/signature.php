<?php

/*
Uguisu 署名ツール

*/


chdir("../");

require("config/config.php");
require($CONFIG['authScript']);
require($CONFIG['loggerClass']);
require($CONFIG['signatureClass']);

UguisuSignature::initialize();

if(!isset($_GET['mode'])) $_GET['mode'] = "";
switch($_GET['mode']){
case "save":
	if(!isset($_POST['title']))  exit("[ERROR] No set parameter.");
	if(!isset($_POST['bodytext'])) exit("[ERROR] No set parameter.");
	
	UguisuSignature::save($_POST['title'],$_POST['bodytext']);
	//Logger::output($CONFIG['debugLevel']);
	header("Location: ./signature.php");
	exit();
}

$signatureList = UguisuSignature::getSignatureList();

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
<title>Uguisu: Signature</title>
<link rel="stylesheet" type="text/css" href="../css/default.css" />
<style>
div#edit-form{
	margin:0.5em;
	padding:0.5em;
	background:#eee;
	border:1px solid #aaa;
}

div#edit-form form{
	margin:0;
	padding:0;
}
div#edit-form h3{
	margin:0;
	padding:0;
	font-size:75%;
}

div#edit-form form input.text-input{
	width:100%;
	font-size:75%;
}

textarea#signature-bodytext{
	width:100%;
	height:100px;
	font-size:80%;
}

ul#signature-list{
	margin:0.5em;
	padding:0;
	list-style-type:none;
}
ul#signature-list li{
	margin:0;
	padding:0.5em 0;
	border:solid #999;
	border-width:0 0 2px 0;
}
ul#signature-list li h2{
	margin:0;
	padding:0;
	font-size:80%;
	color:#777;
}
ul#signature-list li pre{
	margin:0;
	padding:0.5em;
	font-size:85%;
}

ul#signature-list li div.button-panel{
	text-align:right;
	font-size:80%;
}



</style>
<script type="text/JavaScript">

signatureList = new Array();
<?php
foreach($signatureList as $i => $signature){
	echo "signatureList.push(new Array(";
	echo "\"".rawurlencode($signature['title'])."\",";
	echo "\"".rawurlencode($signature['bodytext'])."\"";
	echo "));\n";
	//if($i >= 2) break;
}
?>

function editSignature(i){
	document.getElementById('signature-title').value  = decodeURIComponent(signatureList[i][0]);
	document.getElementById('signature-bodytext').value = decodeURIComponent(signatureList[i][1]);
}


function useSignature(i){
	t = parent.writePaneRight.document.getElementById("form_body");
	s = decodeURIComponent(signatureList[i][1]);
	
	r=t.value.match(/From\:[^\n]+\nDate\:[^\n]+\n>/);
	if(r){
		i = r.index;
		t.value = t.value.substr(0,i) + "\n" + s + "\n"+t.value.substr(i,t.value.length-i);
	}else{
		t.value += s;
	}
}


function htmlspecialchars(str){
	str = str.replace(/</g,"&lt;");
	str = str.replace(/>/g,"&gt;");
	str = str.replace(/\"/g,"&quot;");
	return str;
}

</script>
</head>
<body>

<div id="header">
<a href="address-book.php">アドレス帳</a> | 
<a href="signature.php">署名</a>
</div>

<div id="edit-form">
<form action="signature.php?mode=save" method="post">
<h3>タイトル</h3>
<input id="signature-title" type="text" name="title" class="text-input" /><br />
<h3>署名(定型文)</h3>
<textarea id="signature-bodytext" name="bodytext"></textarea><br />
<div style="text-align:center;">
<input type="submit" value="登録" />
</div>
</form>
</div>

<ul id="signature-list">

<script type="text/JavaScript">
for(i in signatureList){
	document.write("<li>");
	document.write("<h2>");
	document.write(htmlspecialchars(decodeURIComponent(signatureList[i][0])));
	document.write("</h2>");
	document.write("<pre>");
	document.write(htmlspecialchars(decodeURIComponent(signatureList[i][1])));
	document.write("</pre>");
	document.write("<div class=\"button-panel\">");
	document.write("<a href=\"javascript:useSignature("+i+");void(0);\">挿入</a>\n");
	document.write("<a href=\"javascript:editSignature("+i+");void(0);\">編集</a>\n");
	document.write("</div>");
	document.write("</li>\n");
}
</script>
</ul>

<?php Logger::output($CONFIG['debugLevel']); ?>
</body>
</html>