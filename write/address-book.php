<?php

/*
Uguisu アドレスブック

*/


chdir("../");

require("config/config.php");
require($CONFIG['authScript']);
require($CONFIG['loggerClass']);

require($CONFIG['addressBookClass']);

UguisuAddressBook::initialize();


if(!isset($_GET['mode'])) $_GET['mode'] = "";

switch($_GET['mode']){
case "save":
	if(!isset($_POST['mailAddress'])) exit("[ERROR] No set parameter.");
	if(!isset($_POST['contactName'])) exit("[ERROR] No set parameter.");
	if(!isset($_POST['memo']))        exit("[ERROR] No set parameter.");
	
	UguisuAddressBook::save($_POST['mailAddress'],$_POST['contactName'],$_POST['memo']);
	header("Location: ./address-book.php");
	exit();
}


$addressList = UguisuAddressBook::getAddressList();






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
<title>Uguisu: Address book</title>
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
div#edit-form form input.submit-button{
	/* width:100%; */
}

ul#address-list{
	margin:0.5em;
	padding:0;
	list-style-type:none;
}
ul#address-list li{
	margin:0;
	padding:0;
}
ul#address-list li h2{
	margin:0;
	padding:0;
	font-size:80%;
}
ul#address-list li div.button-panel{
	background:#eee;
	border:1px solid #ccc;
	padding:0.5em;
}

ul#address-list li p.mail-address{
	margin:0;
	padding:0;
	font-size:80%;
}

</style>
<script type="text/JavaScript">

function flipButtonPanel(id){
	targetId = "button-panel-"+id;
	if(document.getElementById(targetId).style.display=="none"){
		document.getElementById(targetId).style.display="";
	}else{
		document.getElementById(targetId).style.display="none";
	}
}

addressList = new Array();
<?php
foreach($addressList as $i => $address){
	echo "addressList.push(new Array(";
	//echo "\"".addslashes(htmlspecialchars($address['address']))."\",";
	//echo "\"".addslashes(htmlspecialchars($address['name']))."\",";
	//echo "\"".addslashes(htmlspecialchars($address['memo']))."\"";
	echo "\"".addslashes($address['address'])."\",";
	echo "\"".addslashes($address['name'])."\",";
	echo "\"".addslashes($address['memo'])."\"";
	echo "));\n";
	//if($i >= 2) break;
	//IEの場合は、メールアドレスに <!-- このようなコメントタグがあるとコメントとして処理をしてしまう。
	//IEのバグであり、かつレアケースのため対応しない。攻撃に使えるわけでもないと思う。
}
?>

function setForm(targetId,addressId){
	
	a = addressList[addressId][0];
	if(!a.match(/[\, <>]/)){
		a = "<"+a+">";
	}
	
	
	if(parent.writePaneRight.document.getElementById(targetId).value){
		parent.writePaneRight.document.getElementById(targetId).value += ","+a;
	}else{
		parent.writePaneRight.document.getElementById(targetId).value = a;
	}
}
function setEdit(id){
	document.getElementById("mailAddress").value=addressList[id][0];
	document.getElementById("contactName").value=addressList[id][1];
	document.getElementById("memo").value=addressList[id][2];
	window.scrollTo(0,0);
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
<form action="address-book.php?mode=save" method="post">
<h3>メールアドレス</h3>
<input class="text-input" name="mailAddress" id="mailAddress" title="連絡先リスト内でユニーク" /><br />
<h3>名前</h3>
<input class="text-input" name="contactName" id="contactName" title="空欄で登録するとアドレス削除" /><br />
<h3>メモ</h3>
<input class="text-input" name="memo" id="memo" /><br />
<div style="text-align:center;">
<input class="submit-button" type="submit" value="登録" />
</div>
</form>
</div>

<ul id="address-list">
<script type="text/JavaScript">
for(i in addressList){
	document.write("<li>\n");
	document.write("<h2>");
	document.write("<a href=\"javascript:flipButtonPanel("+i+");void(0);\" title=\""+addressList[i][0]+"\">");
	document.write(htmlspecialchars(addressList[i][1]));
	document.write("</a>");
	document.write("</h2>\n");
	document.write("<div id=\"button-panel-"+i+"\" class=\"button-panel\" style=\"display:none;\" >");
	document.write("<p class=\"mail-address\">"+htmlspecialchars(addressList[i][0])+"<br />"+htmlspecialchars(addressList[i][2])+"</p>");
	document.write("<input type=\"button\" onClick=\"setForm('form_to',"+i+");\" value=\"To\" />\n");
	document.write("<input type=\"button\" onClick=\"setForm('form_cc',"+i+");\" value=\"Cc\" />\n");
	document.write("<input type=\"button\" onClick=\"setForm('form_bcc',"+i+");\" value=\"Bcc\" />\n");
	document.write("<input type=\"button\" onClick=\"setEdit("+i+");\" value=\"Edit\" />\n");
	document.write("</div>");
	document.write("</li>\n");
}
</script>
</ul>

<?php Logger::output($CONFIG['debugLevel']); ?>

</body>
</html>
